<?php
/**
 * Mobile-app bookings (ecab-app/v1): the payment method of a booking is the one WooCommerce validated and stored
 * on the order, and only that gateway, while the shop offers it, may take the payment.
 *
 * - The `billing` object only fills in the checkout's own customer fields: it cannot replace the payment method,
 *   the terms markers or the booking's own fields (quantity, places, passengers ...).
 * - finish() runs the order's own gateway, looked up among the gateways the shop currently offers. A disabled
 *   offline gateway (cash on delivery ...) never moves an order to processing.
 *
 * Runs against the site's real WooCommerce and vehicles, with map services stubbed. Needs one published vehicle
 * priced by distance. Every order, booking and user it creates is deleted and the settings it changes restored.
 *
 * Run with: wp --path=/var/www/html/magepeople eval-file wp-content/plugins/ecab-taxi-booking-manager/tests/app-payment-binding.php
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

// --- Gateways: cash on delivery (offline, switched on and off) and a card gateway finished on a web page --
$saved_cod = get_option('woocommerce_cod_settings', null);
$set_cod = static function (bool $on) use ($saved_cod): void {
	update_option('woocommerce_cod_settings', array_merge(is_array($saved_cod) ? $saved_cod : array(), array('enabled' => $on ? 'yes' : 'no', 'enable_for_methods' => array(), 'enable_for_virtual' => 'yes')));
	WC()->payment_gateways()->init(); // gateways read their settings when constructed
};
if (!class_exists('MPTBM_Test_Bind_Card_Gateway')) {
	class MPTBM_Test_Bind_Card_Gateway extends WC_Payment_Gateway {
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
	$gateways[] = 'MPTBM_Test_Bind_Card_Gateway';
	return $gateways;
});

$_SERVER['REMOTE_ADDR'] = '203.0.113.41';
$users = array();
$clear_limits = static function () use (&$users) {
	foreach (array('public', 'auth', 'quote', 'booking', 'register', 'login') as $bucket) {
		delete_transient('mptbm_app_rl_' . md5($bucket . '|' . $_SERVER['REMOTE_ADDR']));
	}
	foreach ($users as $user_id) {
		foreach (array('booking', 'auth_user') as $bucket) {
			delete_transient('mptbm_app_rl_' . md5($bucket . '|u' . $user_id));
		}
	}
	delete_transient('mptbm_route_rate_' . md5($_SERVER['REMOTE_ADDR']));
};
$call = static function (string $method, string $route, array $body = array(), string $token = ''): WP_REST_Response {
	$request = new WP_REST_Request($method, '/ecab-app/v1' . $route);
	if ($body) {
		$request->set_header('content-type', 'application/json');
		$request->set_body(wp_json_encode($body));
	}
	if ('' !== $token) {
		$request->set_header('authorization', 'Bearer ' . $token);
	}
	return rest_do_request($request);
};
$paid_orders = static function (): int {
	$count = 0;
	foreach (wc_get_orders(array('limit' => -1, 'status' => array_keys(wc_get_order_statuses()))) as $order) {
		$count += $order->is_paid() ? 1 : 0;
	}
	return $count;
};
$all_orders = static function (): int {
	return count(wc_get_orders(array('limit' => -1, 'return' => 'ids', 'status' => array_keys(wc_get_order_statuses()))));
};

$created_orders = array();
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

try {
	$set_cod(true);
	$clear_limits();
	$email = 'apptest-bind-' . wp_generate_password(6, false) . '@example.com';
	$registered = $call('POST', '/auth/register', array('email' => $email, 'password' => 'Passw0rd!' . wp_generate_password(6, false), 'first_name' => 'App', 'last_name' => 'Binder', 'phone' => '5550100'))->get_data();
	$user_id = (int) ($registered['user']['id'] ?? 0);
	$users[] = $user_id;
	$token = (string) ($registered['access_token'] ?? '');
	$check($user_id > 0 && '' !== $token, 'a customer can register through the app');

	$billing = static function (array $extra = array()) use ($email): array {
		return array_merge(array(
			'billing_first_name' => 'App', 'billing_last_name' => 'Binder', 'billing_country' => 'US',
			'billing_address_1' => '1 Main Street', 'billing_city' => 'New York', 'billing_postcode' => '10001', 'billing_state' => 'NY',
			'billing_phone' => '5550100', 'billing_email' => $email,
		), $extra);
	};

	$when = gmdate('Y-m-d', time() + 4 * DAY_IN_SECONDS);
	// Each booking takes the vehicle for its pickup time, so every scenario books its own hour.
	$quote_at = static function (string $time) use ($call, $clear_limits, $when): array {
		$clear_limits();
		return (array) $call('POST', '/quote', array(
			'price_based' => 'dynamic', 'start' => array('name' => 'Test Pickup', 'lat' => 23.8433, 'lng' => 90.3978), 'end' => array('name' => 'Test Dropoff', 'lat' => 23.7806, 'lng' => 90.4167),
			'date' => $when, 'time' => $time, 'passengers' => 1,
		))->get_data();
	};
	$quote = $quote_at('08:00');
	if (empty($quote['vehicles'])) {
		$cleanup();
		echo "SKIPPED: no published vehicle priced by distance could take the test trip.\n";
		return;
	}
	$vehicle_id = (int) $quote['vehicles'][0]['id'];

	$book = static function (array $body, string $time) use ($call, $quote_at, $vehicle_id, $token, $clear_limits, &$created_orders): array {
		$quote = $quote_at($time);
		$clear_limits();
		$response = $call('POST', '/bookings', array_merge(array('quote_id' => (string) ($quote['quote_id'] ?? ''), 'vehicle_id' => $vehicle_id, 'accept_terms' => true), $body), $token);
		$data = $response->get_data();
		if (!empty($data['booking']['id'])) {
			$created_orders[] = (int) $data['booking']['id'];
		}
		return array($response->get_status(), $data);
	};

	// --- 1. What the billing object may set (the posted checkout) -------------------------------------------
	wc_load_cart();
	$posted = new ReflectionMethod('MPTBM_App_Booking', 'posted_fields');
	$posted->setAccessible(true);
	$trip = array('start' => array('name' => 'Test Pickup'), 'end' => array('name' => 'Test Dropoff'), 'price_based' => 'dynamic', 'date' => $when, 'time' => '11:00');
	$post = wp_unslash($posted->invoke(null, array(
		'payment_method' => 'testcard',
		'order_notes' => 'From the notes field',
		'billing' => $billing(array(
			'payment_method' => 'cod', 'terms' => 'on', 'terms-field' => '1', 'transport_quantity' => '5', 'link_id' => '1',
			'mptbm_start_place' => 'Somewhere else', 'mptbm_passengers' => '9', 'mptbm_original_price_base' => 'manual',
			'start_place_coordinates' => '1,1', 'woocommerce-process-checkout-nonce' => 'x', 'createaccount' => '1', 'not_a_field' => 'x',
			'order_comments' => 'From the order section',
		)),
	), $trip, $vehicle_id, array()));
	$check('testcard' === ($post['payment_method'] ?? ''), 'billing.payment_method cannot replace the payment method');
	$check(1 === (int) ($post['transport_quantity'] ?? 0), 'billing cannot change the quantity');
	$check('Test Pickup' === ($post['mptbm_start_place'] ?? ''), 'billing cannot change the pickup place');
	$check('dynamic' === ($post['mptbm_original_price_base'] ?? ''), 'billing cannot change the pricing mode');
	$check(!isset($post['mptbm_passengers']), 'billing cannot add booking fields');
	foreach (array('link_id', 'start_place_coordinates', 'woocommerce-process-checkout-nonce', 'createaccount', 'not_a_field') as $key) {
		$check(!isset($post[$key]), "billing cannot post `$key`");
	}
	if (!wc_terms_and_conditions_checkbox_enabled()) {
		$check(!isset($post['terms']) && !isset($post['terms-field']), 'billing cannot post the terms markers');
	}
	$check('App' === ($post['billing_first_name'] ?? '') && $email === ($post['billing_email'] ?? ''), 'billing fields of the checkout are posted');
	$check('From the order section' === ($post['order_comments'] ?? ''), 'order fields of the checkout are posted');

	// --- 2. The reported attack: order paid with one method, a disabled offline gateway run instead -------
	$set_cod(false);
	$paid_before = $paid_orders();
	$orders_before = $all_orders();
	list($status, $data) = $book(array('billing' => $billing(array('payment_method' => 'testcard')), 'payment_method' => 'cod'), '09:00');
	$check(201 !== $status, 'a booking cannot name a disabled offline gateway behind another method (got ' . $status . ')');
	$check($paid_before === $paid_orders(), 'no order became paid');
	$check($orders_before === $all_orders(), 'the refused booking left no order behind');
	$check(empty($data['payment']['type']) || 'offline' !== $data['payment']['type'], 'no offline payment was started');

	// --- 3. The billing object cannot swap the method the order is placed with -----------------------------
	$set_cod(true);
	list($status, $data) = $book(array('billing' => $billing(array('payment_method' => 'cod')), 'payment_method' => 'testcard'), '10:00');
	$check(201 === $status, 'a card booking is placed (got ' . $status . ')');
	$check('testcard' === ($data['booking']['payment_method'] ?? ''), 'the order is placed with the requested method, not billing.payment_method');
	$check('pending' === ($data['booking']['status'] ?? '') && !empty($data['booking']['needs_payment']), 'the card booking waits for payment');
	$check('online' === ($data['payment']['type'] ?? '') && 'testcard' === ($data['payment']['method'] ?? ''), 'the card booking is handed a payment page');
	$card_order_id = (int) ($data['booking']['id'] ?? 0);

	// --- 4. A cash booking still works when the shop offers cash ------------------------------------------
	list($status, $data) = $book(array('billing' => $billing(), 'payment_method' => 'cod'), '12:00');
	$check(201 === $status, "a cash booking is placed (got $status: " . wp_json_encode($data["data"]["messages"] ?? $data["message"] ?? "") . ")");
	$check('processing' === ($data['booking']['status'] ?? ''), 'a cash booking is confirmed');
	$check('offline' === ($data['payment']['type'] ?? '') && 'cod' === ($data['payment']['method'] ?? ''), 'a cash booking reports the offline method');
	$cash_total = (float) ($data['booking']['total'] ?? -1);

	// --- 5. A smuggled quantity does not change the fare --------------------------------------------------
	list($status, $data) = $book(array('billing' => $billing(array('transport_quantity' => '5', 'mptbm_passengers' => '9')), 'payment_method' => 'cod'), '14:00');
	$check(201 === $status && abs((float) ($data['booking']['total'] ?? -2) - $cash_total) < 0.01, 'billing.transport_quantity does not change the total');

	// --- 6. finish() runs only the order's own gateway, and only while the shop offers it ----------------
	$finish = new ReflectionMethod('MPTBM_App_Booking', 'finish');
	$finish->setAccessible(true);
	$run_finish = static function (WC_Order $order, string $method) use ($finish, $user_id) {
		// Before the fix finish() took the method to run from the request; now it always uses the order's.
		return 3 === $finish->getNumberOfParameters() ? $finish->invoke(null, $order, $method, $user_id) : $finish->invoke(null, $order, $user_id);
	};
	$card_order = wc_get_order($card_order_id);
	if ($card_order) {
		$result = $run_finish($card_order, 'cod'); // cash is on, but the order is a card order
		$card_order = wc_get_order($card_order_id);
		$check(!$card_order->is_paid() && 'pending' === $card_order->get_status(), 'cash cannot be run on a card order');
		$check('offline' !== ($result['payment']['type'] ?? ''), 'a card order is never reported as an offline payment');

		$set_cod(false);
		$card_order->set_payment_method('cod'); // a cash order, after the shop switched cash off
		$card_order->save();
		$result = $run_finish(wc_get_order($card_order_id), 'cod');
		$card_order = wc_get_order($card_order_id);
		$check(!$card_order->is_paid() && 'pending' === $card_order->get_status(), 'a disabled cash gateway never confirms an order');
		$check('offline' !== ($result['payment']['type'] ?? ''), 'a disabled cash gateway is not reported as the payment');
	} else {
		$check(false, 'the card order exists for the finish() checks');
	}

	// --- 7. Switching an unpaid booking to a disabled method is refused ------------------------------------
	$clear_limits();
	$switch = $call('POST', '/bookings/' . $card_order_id . '/pay-link', array('method' => 'cheque'), $token);
	$check(400 === $switch->get_status(), 'switching to a method the shop does not offer is refused');
	$check(!wc_get_order($card_order_id)->is_paid(), 'the refused switch leaves the booking unpaid');
} catch (Throwable $e) {
	$failures[] = 'Unexpected ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine();
} finally {
	$cleanup();
}

if ($failures) {
	echo "FAILED (" . count($failures) . " of $checks checks):\n - " . implode("\n - ", $failures) . "\n";
	exit(1);
}
echo "OK ($checks checks)\n";
