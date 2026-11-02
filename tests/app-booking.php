<?php
/**
 * Mobile-app bookings (ecab-app/v1): checkout form, extras, placing a booking from a stored quote,
 * the customer's own bookings, and the payment hand-off.
 *
 * Runs against the site's real WooCommerce and vehicles. Map services are stubbed (Photon, OSRM), so no
 * network is used. Needs at least one published vehicle priced by distance and Cash on delivery available
 * (the test turns it on and restores the setting). Every order, booking and user it creates is deleted.
 *
 * Run with: wp --path=/var/www/html/magepeople eval-file wp-content/plugins/ecab-taxi-booking-manager/tests/app-booking.php
 */

if (!defined('ABSPATH')) {
	die;
}

$failures = array();
$checks   = 0;
$check    = static function ($condition, $message) use (&$failures, &$checks): void {
	++$checks;
	if (!$condition) {
		$failures[] = $message;
	}
};
$finish = static function () use (&$failures, &$checks): void {
	if ($failures) {
		echo "FAILED (" . count($failures) . " of $checks checks):\n - " . implode("\n - ", $failures) . "\n";
		exit(1);
	}
	echo "OK ($checks checks)\n";
};

if (!class_exists('WooCommerce') || !class_exists('MPTBM_App_Booking')) {
	echo "SKIPPED: WooCommerce and the app API are required.\n";
	return;
}

// --- Stubbed map services ----------------------------------------------------------------------
$places = array('test pickup' => array(23.8433, 90.3978), 'test dropoff' => array(23.7806, 90.4167));
$respond = static function ($body) {
	return array('headers' => array(), 'body' => wp_json_encode($body), 'response' => array('code' => 200, 'message' => 'OK'), 'cookies' => array(), 'filename' => null);
};
add_filter('pre_http_request', static function ($pre, $args, $url) use ($places, $respond) {
	parse_str((string) (wp_parse_url($url)['query'] ?? ''), $query);
	if (false !== strpos($url, 'photon.komoot.io')) {
		$hit = $places[strtolower($query['q'] ?? '')] ?? null;
		return $respond(array('type' => 'FeatureCollection', 'features' => $hit ? array(array('geometry' => array('coordinates' => array($hit[1], $hit[0])), 'properties' => array('name' => $query['q']))) : array()));
	}
	if (false !== strpos($url, 'router.project-osrm.org')) {
		return $respond(array('code' => 'Ok', 'routes' => array(array('distance' => 9800, 'duration' => 900))));
	}
	return $pre;
}, 10, 3);
$saved_map = get_option('mptbm_map_api_settings', null);
update_option('mptbm_map_api_settings', array_merge(is_array($saved_map) ? $saved_map : array(), array('display_map' => 'openstreetmap', 'gmap_api_key' => '', 'gmap_server_api_key' => '', 'fare_distance_source' => 'server', 'mptbm_address_verification' => 'strict')));

// --- Payment methods: Cash on delivery (offline) and a card gateway that finishes on a web page --------
$saved_cod = get_option('woocommerce_cod_settings', null);
update_option('woocommerce_cod_settings', array_merge(is_array($saved_cod) ? $saved_cod : array(), array('enabled' => 'yes', 'enable_for_methods' => array())));
if (!class_exists('MPTBM_Test_Card_Gateway')) {
	class MPTBM_Test_Card_Gateway extends WC_Payment_Gateway {
		public function __construct() {
			$this->id = 'testcard';
			$this->method_title = 'Test card';
			$this->title = 'Test card';
			$this->enabled = 'yes';
			$this->has_fields = true;
		}
		public function process_payment($order_id) {
			throw new Exception('A card gateway must never be charged from the app API.');
		}
	}
}
add_filter('woocommerce_payment_gateways', static function ($gateways) {
	$gateways[] = 'MPTBM_Test_Card_Gateway';
	return $gateways;
});
WC()->payment_gateways()->init();

$_SERVER['REMOTE_ADDR'] = '203.0.113.30';
$clear_limits = static function () {
	foreach (array('public', 'auth', 'quote', 'booking', 'register', 'login') as $bucket) {
		delete_transient('mptbm_app_rl_' . md5($bucket . '|' . $_SERVER['REMOTE_ADDR']));
	}
	delete_transient('mptbm_route_rate_' . md5($_SERVER['REMOTE_ADDR']));
};
$clear_limits();

$call = static function (string $method, string $route, array $body = array(), string $token = '', array $query = array()): WP_REST_Response {
	$request = new WP_REST_Request($method, '/ecab-app/v1' . $route);
	if ($body) {
		$request->set_header('content-type', 'application/json');
		$request->set_body(wp_json_encode($body));
	}
	if ($query) {
		$request->set_query_params($query);
	}
	if ('' !== $token) {
		$request->set_header('authorization', 'Bearer ' . $token);
	}
	return rest_do_request($request);
};

$created_orders = array();
$users = array();
$cleanup = static function () use (&$created_orders, &$users, $saved_map, $saved_cod): void {
	foreach ($created_orders as $order_id) {
		$order = wc_get_order($order_id);
		if ($order) {
			$order->delete(true);
		}
	}
	foreach (get_posts(array('post_type' => 'mptbm_booking', 'numberposts' => -1, 'post_status' => 'any', 'meta_key' => 'mptbm_order_id', 'meta_compare' => 'IN', 'meta_value' => $created_orders ?: array(0), 'fields' => 'ids')) as $id) {
		wp_delete_post($id, true);
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ($users as $user_id) {
		wp_delete_user($user_id);
	}
	null === $saved_map ? delete_option('mptbm_map_api_settings') : update_option('mptbm_map_api_settings', $saved_map);
	null === $saved_cod ? delete_option('woocommerce_cod_settings') : update_option('woocommerce_cod_settings', $saved_cod);
};

$register = static function (string $label) use ($call, &$users, $clear_limits): array {
	$clear_limits();
	$response = $call('POST', '/auth/register', array('email' => 'apptest-' . $label . '-' . wp_generate_password(6, false) . '@example.com', 'password' => 'Passw0rd!' . wp_generate_password(6, false), 'first_name' => 'App', 'last_name' => 'Booker', 'phone' => '5550100'));
	$data = $response->get_data();
	$users[] = (int) ($data['user']['id'] ?? 0);
	return $data;
};

try {
	$customer = $register('a');
	$other = $register('b');
	$token = $customer['access_token'];
	$billing = static function (array $override = array()) use ($customer): array {
		return array_merge(array(
			'billing_first_name' => 'App', 'billing_last_name' => 'Booker', 'billing_country' => 'US',
			'billing_address_1' => '1 Main Street', 'billing_city' => 'New York', 'billing_postcode' => '10001', 'billing_state' => 'NY',
			'billing_phone' => '5550100', 'billing_email' => $customer['user']['email'],
		), $override);
	};

	// --- The form the site's checkout asks for ---------------------------------------------------------
	$form = $call('GET', '/checkout/form', array(), $token)->get_data();
	$keys = array_column($form['fields'] ?? array(), 'key');
	$check(in_array('billing_first_name', $keys, true) && in_array('billing_email', $keys, true), 'the checkout form lists the billing fields');
	$prefilled = array_column($form['fields'], 'value', 'key');
	$check(($prefilled['billing_email'] ?? '') === $customer['user']['email'] || ($prefilled['billing_first_name'] ?? '') === 'App', 'the form starts filled in with the customer\'s details');
	$check(401 === $call('GET', '/checkout/form', array(), 'bogus')->get_status(), 'a bad token on the checkout form is refused');
	$state_field = array_values(array_filter($form['fields'], static function ($f) { return 'billing_state' === $f['key']; }))[0] ?? null;
	$check(!$state_field || 'state' === $state_field['type'], 'the state field is marked so the app can offer a list');
	$us = $call('GET', '/checkout/states', array(), '', array('country' => 'US'))->get_data();
	$check(count($us) > 40 && in_array('NY', array_column($us, 'value'), true), 'the states of a country come as a list');
	$check(array() === $call('GET', '/checkout/states', array(), '', array('country' => 'ZZ'))->get_data(), 'an unknown country has no states');
	$check(array() === $call('GET', '/checkout/states', array(), '', array('country' => 'USA'))->get_data(), 'a malformed country code has no states');

	// --- A quote to book from -------------------------------------------------------------------------
	$when = gmdate('Y-m-d', time() + 3 * DAY_IN_SECONDS);
	$quote = $call('POST', '/quote', array(
		'price_based' => 'dynamic', 'start' => array('name' => 'Test Pickup', 'lat' => 23.8433, 'lng' => 90.3978), 'end' => array('name' => 'Test Dropoff', 'lat' => 23.7806, 'lng' => 90.4167),
		'date' => $when, 'time' => '10:30', 'passengers' => 2,
	))->get_data();
	if (empty($quote['vehicles'])) {
		$cleanup();
		echo "SKIPPED: no published vehicle priced by distance could take the test trip.\n";
		return;
	}
	$vehicle = $quote['vehicles'][0];
	$vehicle_id = (int) $vehicle['id'];
	$fare = (float) $vehicle['price']['transport'];

	$extras = $call('GET', '/vehicles/' . $vehicle_id . '/extras')->get_data();
	$check(isset($extras['extras'], $extras['stoppages']), 'a vehicle lists its extras and stoppages');
	$check(404 === $call('GET', '/vehicles/999999999/extras')->get_status(), 'extras of an unknown vehicle are 404');
	$extra = $extras['extras'][0] ?? null;

	$book = static function (array $override = array(), ?string $as = null) use ($call, $quote, $vehicle_id, $billing, $token, $clear_limits): WP_REST_Response {
		$clear_limits();
		return $call('POST', '/bookings', array_merge(array(
			'quote_id' => $quote['quote_id'], 'vehicle_id' => $vehicle_id, 'billing' => $billing(), 'payment_method' => 'cod', 'accept_terms' => true,
		), $override), $as ?? $token);
	};
	$order_count = static function (): int {
		return count(wc_get_orders(array('limit' => -1, 'return' => 'ids', 'status' => array_keys(wc_get_order_statuses()))));
	};

	// --- Refusals leave nothing behind ------------------------------------------------------------------
	$before = $order_count();
	$check(401 === $call('POST', '/bookings', array('quote_id' => $quote['quote_id'], 'vehicle_id' => $vehicle_id))->get_status(), 'booking needs a signed-in customer');
	$check(410 === $book(array('quote_id' => str_repeat('a', 32)))->get_status(), 'an unknown quote is 410');
	$check(410 === $book(array('quote_id' => 'short'))->get_status(), 'a malformed quote id is 410');
	$check(409 === $book(array('vehicle_id' => 999999))->get_status(), 'a vehicle that was not in the quote is 409');

	$invalid = $book(array('billing' => $billing(array('billing_first_name' => '', 'billing_phone' => ''))));
	$check(400 === $invalid->get_status(), 'missing required checkout fields are refused');
	$fields = $invalid->get_data()['data']['fields'] ?? array();
	$check(isset($fields['billing_first_name']), 'the refusal names the field that is wrong');
	$check(400 === $book(array('payment_method' => 'nonsense'))->get_status(), 'an unknown payment method is refused');
	$check(400 === $book(array('billing' => $billing(array('billing_email' => 'not-an-email'))))->get_status(), 'an invalid email is refused');
	$bad_extra = $book(array('extras' => array(array('name' => 'No Such Extra', 'qty' => 1))));
	$check(409 === $bad_extra->get_status(), 'an extra the vehicle does not offer is refused (got ' . $bad_extra->get_status() . ': ' . wp_json_encode($bad_extra->get_data()) . ')');

	$tampered = get_transient('mptbm_app_quote_' . $quote['quote_id']);
	$tampered['vehicles'][$vehicle_id]['price']['transport'] = $fare + 5;
	set_transient('mptbm_app_quote_' . $quote['quote_id'], $tampered, HOUR_IN_SECONDS);
	$check(409 === $book()->get_status() && 'mptbm_app_fare_changed' === ($book()->get_data()['code'] ?? ''), 'a fare that no longer matches the quote is refused');
	$tampered['vehicles'][$vehicle_id]['price']['transport'] = $fare;
	set_transient('mptbm_app_quote_' . $quote['quote_id'], $tampered, HOUR_IN_SECONDS);
	$check($before === $order_count(), 'refused bookings create no order');
	$check(WC()->cart->is_empty(), 'refused bookings leave no cart behind');

	// --- Cash on delivery -----------------------------------------------------------------------------
	$bookings_before = count(get_posts(array('post_type' => 'mptbm_booking', 'numberposts' => -1, 'post_status' => 'any', 'fields' => 'ids')));
	$override = array('order_notes' => 'Please call on arrival');
	if ($extra) {
		$override['extras'] = array(array('name' => $extra['name'], 'qty' => 2));
	}
	$placed = $book($override);
	$check(201 === $placed->get_status(), 'a booking is placed: ' . wp_json_encode($placed->get_data()));
	$data = $placed->get_data();
	$order = wc_get_order((int) ($data['booking']['id'] ?? 0));
	if ($order) {
		$created_orders[] = $order->get_id();
	}
	if (!$order) { // nothing below can be checked without an order; say why and stop
		$cleanup();
		$finish();
		return;
	}
	$check((int) $order->get_customer_id() === (int) $customer['user']['id'], 'the order belongs to the customer');
	$check('offline' === ($data['payment']['type'] ?? ''), 'cash on delivery is an offline payment');
	$check(in_array($data['booking']['status'] ?? '', array('processing', 'on-hold'), true), 'cash on delivery is accepted straight away');
	$expected = $fare + ($extra ? 2 * (float) $extra['price'] : 0);
	$check($order && abs((float) $order->get_total() - $expected) < 0.01, "the order total is the quoted fare plus the extras ({$expected})");
	$trip = $data['booking']['trips'][0] ?? array();
	$check(($trip['pickup'] ?? '') === 'Test Pickup' && ($trip['dropoff'] ?? '') === 'Test Dropoff', 'the order records the pick-up and drop-off');
	$check(2 === ($trip['passengers'] ?? 0), 'the order records the passengers');
	$check(!$extra || 2 === ($trip['extras'][0]['qty'] ?? 0), 'the order records the extras');
	$check('Please call on arrival' === ($order ? $order->get_customer_note() : ''), 'the order notes reach the order');
	$bookings_after = count(get_posts(array('post_type' => 'mptbm_booking', 'numberposts' => -1, 'post_status' => 'any', 'fields' => 'ids')));
	$check($bookings_after === $bookings_before + 1, 'the plugin\'s own taxi booking record was created');
	$check(WC()->cart->is_empty(), 'the cart is empty after a booking');

	// The same vehicle at the same time cannot be booked twice.
	$again = $book();
	$check(400 === $again->get_status() && false !== stripos((string) $again->get_data()['message'], 'no longer available'), 'a vehicle that is already booked for that time is refused');

	// --- Online payment: the card form is a web page, never the API -----------------------------------------
	$later = $call('POST', '/quote', array(
		'price_based' => 'dynamic', 'start' => array('name' => 'Test Pickup', 'lat' => 23.8433, 'lng' => 90.3978), 'end' => array('name' => 'Test Dropoff', 'lat' => 23.7806, 'lng' => 90.4167),
		'date' => $when, 'time' => '16:30', 'passengers' => 2,
	))->get_data();
	$clear_limits();
	$online = $call('POST', '/bookings', array('quote_id' => $later['quote_id'], 'vehicle_id' => $vehicle_id, 'billing' => $billing(), 'payment_method' => 'testcard', 'accept_terms' => true), $token);
	$check(201 === $online->get_status(), 'an online-payment booking is placed: ' . wp_json_encode($online->get_data()));
	$online_data = $online->get_data();
	$online_order = wc_get_order((int) ($online_data['booking']['id'] ?? 0));
	if (!$online_order) {
		$cleanup();
		$finish();
		return;
	}
	$created_orders[] = $online_order->get_id();
	$check('online' === ($online_data['payment']['type'] ?? '') && 'pending' === ($online_data['booking']['status'] ?? ''), 'an online booking waits for payment');
	$pay_url = (string) ($online_data['payment']['pay_url'] ?? '');
	$check(0 === strpos($pay_url, home_url('/')) && false !== strpos($pay_url, 'ecab_app_pay='), 'the app is given a payment link');
	parse_str((string) wp_parse_url($pay_url, PHP_URL_QUERY), $link);
	$spent = MPTBM_App_Booking::consume_pay_token((string) ($link['ecab_app_pay'] ?? ''));
	$check(!is_wp_error($spent) && $spent['order']->get_id() === $online_order->get_id() && (int) $spent['user']->ID === (int) $customer['user']['id'], 'the link resolves to the customer\'s order');
	$check(is_wp_error(MPTBM_App_Booking::consume_pay_token((string) ($link['ecab_app_pay'] ?? ''))), 'a payment link works only once');
	$check(is_wp_error(MPTBM_App_Booking::consume_pay_token('guess')), 'a guessed payment link is refused');

	$fresh = $call('POST', '/bookings/' . $online_order->get_id() . '/pay-link', array(), $token);
	$check(200 === $fresh->get_status() && !empty($fresh->get_data()['pay_url']), 'a new payment link can be asked for');
	$check(404 === $call('POST', '/bookings/' . $online_order->get_id() . '/pay-link', array(), $other['access_token'])->get_status(), 'nobody else can ask for a link to this order');
	$check(409 === $call('POST', '/bookings/' . $order->get_id() . '/pay-link', array(), $token)->get_status(), 'an order that needs no payment gets no link');

	$staff_id = wp_insert_user(array('user_login' => 'apptest-staff-' . wp_generate_password(5, false), 'user_pass' => wp_generate_password(20), 'user_email' => 'apptest-staff-' . wp_generate_password(5, false) . '@example.com', 'role' => 'shop_manager'));
	$users[] = (int) $staff_id;
	$check('' === MPTBM_App_Booking::pay_link($online_order, (int) $staff_id), 'staff accounts never get a web-sign-in link');

	// --- The customer's own bookings --------------------------------------------------------------------------
	$list = $call('GET', '/bookings', array(), $token)->get_data();
	$ids = array_column($list['bookings'] ?? array(), 'id');
	$check(in_array($order->get_id(), $ids, true) && in_array($online_order->get_id(), $ids, true), 'the list holds the customer\'s bookings');
	$check(200 === $call('GET', '/bookings/' . $order->get_id(), array(), $token)->get_status(), 'a booking can be read by its owner');
	$check(404 === $call('GET', '/bookings/' . $order->get_id(), array(), $other['access_token'])->get_status(), 'another customer cannot read it');
	$check(array() === ($call('GET', '/bookings', array(), $other['access_token'])->get_data()['bookings'] ?? null), 'another customer\'s list is empty');
	$check(401 === $call('GET', '/bookings')->get_status(), 'the list needs a signed-in customer');

	// --- Cancel / change requests (Pro) and the driver ---------------------------------------------------------
	$pro = MPTBM_App_Booking::changes_supported();
	$detail = $call('GET', '/bookings/' . $order->get_id(), array(), $token)->get_data();
	$check($pro === $detail['can_cancel'] && $pro === $detail['can_modify'], 'cancel and change are offered exactly when Pro can handle them');
	if ($pro) {
		$clear_limits();
		$check(404 === $call('POST', '/bookings/' . $order->get_id() . '/change', array('text' => 'x'), $other['access_token'])->get_status(), 'nobody else can ask to change this booking');
		$check(400 === $call('POST', '/bookings/' . $order->get_id() . '/change', array('text' => '   '), $token)->get_status(), 'an empty change request is refused');
		$check(400 === $call('POST', '/bookings/' . $order->get_id() . '/change', array('text' => str_repeat('a', 501)), $token)->get_status(), 'an over-long change request is refused');
		$changed = $call('POST', '/bookings/' . $order->get_id() . '/change', array('text' => 'Pick me up at 11:00 instead'), $token);
		$check(200 === $changed->get_status() && 'pending' === ($changed->get_data()['change_request']['status'] ?? ''), 'a change request is recorded for review');
		$check(false === $changed->get_data()['can_modify'] && true === $changed->get_data()['can_cancel'], 'a second change request is not offered while one waits');
		$check(409 === $call('POST', '/bookings/' . $order->get_id() . '/change', array('text' => 'again'), $token)->get_status(), 'a second change request is refused');
		$check('Pick me up at 11:00 instead' === (string) wc_get_order($order->get_id())->get_meta('_mptbm_modification_request'), 'the request reaches the order for the admin');

		$cancelled = $call('POST', '/bookings/' . $order->get_id() . '/cancel', array(), $token);
		$check(200 === $cancelled->get_status() && true === $cancelled->get_data()['cancellation_requested'], 'a cancellation is requested, not executed');
		$check('pending-cancel' === wc_get_order($order->get_id())->get_status() && in_array(wc_get_order($order->get_id())->get_meta('_mptbm_prev_status'), array('processing', 'on-hold'), true), 'the order waits for the shop with its previous status remembered');
		$check(409 === $call('POST', '/bookings/' . $order->get_id() . '/cancel', array(), $token)->get_status(), 'a booking already awaiting cancellation cannot be cancelled again');
		$check(404 === $call('POST', '/bookings/' . $online_order->get_id() . '/cancel', array(), $other['access_token'])->get_status(), 'nobody else can cancel this booking');
		$check(401 === $call('POST', '/bookings/' . $online_order->get_id() . '/cancel')->get_status(), 'cancelling needs a signed-in customer');

		// a driver assigned by the shop shows up only on a confirmed booking
		$driver_id = wp_insert_user(array('user_login' => 'apptest-driver-' . wp_generate_password(5, false), 'user_pass' => wp_generate_password(20), 'user_email' => 'apptest-driver-' . wp_generate_password(5, false) . '@example.com', 'display_name' => 'Test Driver', 'role' => 'subscriber'));
		$users[] = (int) $driver_id;
		update_user_meta($driver_id, 'billing_phone', '5559999');
		$vehicle_of_order = (int) $trip['vehicle_id'];
		update_post_meta($vehicle_of_order, 'mptbm_selected_driver', $driver_id);
		$online_order->update_status('processing');
		$with_driver = $call('GET', '/bookings/' . $online_order->get_id(), array(), $token)->get_data();
		$check('Test Driver' === ($with_driver['driver']['name'] ?? '') && '5559999' === ($with_driver['driver']['phone'] ?? ''), 'a confirmed booking shows its driver');
		$online_order->update_status('pending');
		$check(null === $call('GET', '/bookings/' . $online_order->get_id(), array(), $token)->get_data()['driver'], 'an unconfirmed booking shows no driver');
		delete_post_meta($vehicle_of_order, 'mptbm_selected_driver');
	} else {
		$check(501 === $call('POST', '/bookings/' . $order->get_id() . '/cancel', array(), $token)->get_status(), 'without Pro, cancelling answers 501');
	}

	// --- A fixed route books like any other trip, priced from the shop's table ---------------------------------
	$fixtures = array();
	$route_post = wp_insert_post(array('post_type' => 'mptbm_routes', 'post_status' => 'publish', 'post_title' => 'Apptest Booking Tour'));
	$route_car = wp_insert_post(array('post_type' => MPTBM_Function::get_cpt(), 'post_status' => 'publish', 'post_title' => 'Apptest Route Car'));
	$fixtures = array($route_post, $route_car);
	update_post_meta($route_car, 'mptbm_price_based', 'fixed_route');
	update_post_meta($route_car, 'mptbm_assigned_routes', array(array('route_id' => $route_post, 'price' => 80)));
	$clear_limits();
	$route_quote = $call('POST', '/quote', array('price_based' => 'fixed_route', 'start' => array('name' => 'Apptest Booking Tour'), 'date' => $when, 'time' => '09:00', 'passengers' => 1))->get_data();
	$clear_limits();
	$route_booking = $call('POST', '/bookings', array('quote_id' => $route_quote['quote_id'] ?? '', 'vehicle_id' => $route_car, 'billing' => $billing(), 'payment_method' => 'cod', 'accept_terms' => true), $token);
	$check(201 === $route_booking->get_status(), 'a fixed-route booking is placed: ' . wp_json_encode($route_booking->get_data()));
	$route_order = wc_get_order((int) ($route_booking->get_data()['booking']['id'] ?? 0));
	if ($route_order) {
		$created_orders[] = $route_order->get_id();
		$check(abs((float) $route_order->get_total() - 80.0) < 0.01, 'the fixed-route order total is the route price');
		$check('Apptest Booking Tour' === ($route_booking->get_data()['booking']['trips'][0]['pickup'] ?? ''), 'the order records the route as the pick-up');
	}
	foreach ($fixtures as $fixture_id) {
		wp_delete_post($fixture_id, true);
	}

	$plain = wc_create_order(array('customer_id' => (int) $customer['user']['id']));
	$created_orders[] = $plain->get_id();
	$check(404 === $call('GET', '/bookings/' . $plain->get_id(), array(), $token)->get_status(), 'an order that is not a taxi booking is not exposed');
} finally {
	$cleanup();
}
$finish();
