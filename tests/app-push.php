<?php
/**
 * Mobile-app push notifications: token registration and the message sent when a booking changes status.
 * Expo's push service is stubbed, so no network is used. Creates one user and one order and deletes both.
 *
 * Run with: wp --path=/var/www/html/magepeople eval-file wp-content/plugins/ecab-taxi-booking-manager/tests/app-push.php
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
if (!class_exists('MPTBM_App_Push') || !class_exists('WooCommerce')) {
	echo "SKIPPED: WooCommerce and the app API are required.\n";
	return;
}

$_SERVER['REMOTE_ADDR'] = '203.0.113.40';
foreach (array('public', 'auth', 'register') as $bucket) {
	delete_transient('mptbm_app_rl_' . md5($bucket . '|' . $_SERVER['REMOTE_ADDR']));
}
$call = static function (string $method, string $route, array $body = array(), string $token = ''): WP_REST_Response {
	$request = new WP_REST_Request($method, '/ecab-app/v1' . $route);
	if ($body) {
		$request->set_header('content-type', 'application/json');
		$request->set_body(wp_json_encode($body));
		$request->set_query_params($body);
	}
	if ('' !== $token) {
		$request->set_header('authorization', 'Bearer ' . $token);
	}
	return rest_do_request($request);
};

$sent = array();
$dead = '';
add_filter('pre_http_request', static function ($pre, $args, $url) use (&$sent, &$dead) {
	if (false === strpos($url, 'exp.host/--/api/v2/push/send')) {
		return $pre;
	}
	$messages = json_decode($args['body'], true);
	$sent = array_merge($sent, $messages);
	$tickets = array_map(static function ($m) use ($dead) {
		return $m['to'] === $dead ? array('status' => 'error', 'details' => array('error' => 'DeviceNotRegistered')) : array('status' => 'ok');
	}, $messages);
	return array('headers' => array(), 'body' => wp_json_encode(array('data' => $tickets)), 'response' => array('code' => 200, 'message' => 'OK'), 'cookies' => array(), 'filename' => null);
}, 10, 3);

$users = array();
try {
	$a = $call('POST', '/auth/register', array('email' => 'apptest-push-a-' . wp_generate_password(6, false) . '@example.com', 'password' => 'Passw0rd!' . wp_generate_password(6, false), 'first_name' => 'Push'))->get_data();
	foreach (array('public', 'auth', 'register') as $bucket) {
		delete_transient('mptbm_app_rl_' . md5($bucket . '|' . $_SERVER['REMOTE_ADDR']));
	}
	$b = $call('POST', '/auth/register', array('email' => 'apptest-push-b-' . wp_generate_password(6, false) . '@example.com', 'password' => 'Passw0rd!' . wp_generate_password(6, false), 'first_name' => 'Other'))->get_data();
	$users = array((int) $a['user']['id'], (int) $b['user']['id']);
	$tok1 = 'ExponentPushToken[' . wp_generate_password(22, false) . ']';
	$tok2 = 'ExponentPushToken[' . wp_generate_password(22, false) . ']';

	$check(401 === $call('POST', '/push/register', array('token' => $tok1))->get_status(), 'registering a device needs a signed-in customer');
	$check(400 === $call('POST', '/push/register', array('token' => 'not-a-token'), $a['access_token'])->get_status(), 'a malformed token is refused');
	$check(400 === $call('POST', '/push/register', array('token' => "ExponentPushToken[x']"), $a['access_token'])->get_status(), 'a token with odd characters is refused');
	$check(200 === $call('POST', '/push/register', array('token' => $tok1), $a['access_token'])->get_status(), 'a device is registered');
	$call('POST', '/push/register', array('token' => $tok2), $a['access_token']);
	$check(2 === count(MPTBM_App_Push::tokens($users[0])), 'two devices can be registered');
	$call('POST', '/push/register', array('token' => $tok1), $b['access_token']);
	$check(!isset(MPTBM_App_Push::tokens($users[0])[$tok1]) && isset(MPTBM_App_Push::tokens($users[1])[$tok1]), 'a device belongs to the account that registered it last');
	$call('POST', '/push/register', array('token' => $tok1), $a['access_token']);

	for ($i = 0; $i < 6; $i++) {
		MPTBM_App_Push::register($users[0], 'ExponentPushToken[' . wp_generate_password(22, false) . ']');
	}
	$check(count(MPTBM_App_Push::tokens($users[0])) <= MPTBM_App_Push::MAX_TOKENS, 'a customer keeps at most the newest few devices');
	MPTBM_App_Push::register($users[0], $tok1);

	$vehicle_id = (int) (get_posts(array('post_type' => MPTBM_Function::get_cpt(), 'numberposts' => 1, 'fields' => 'ids', 'post_status' => 'publish'))[0] ?? 0);
	if (!$vehicle_id) {
		echo "SKIPPED: no published vehicle.\n";
		return;
	}
	$sent = array();
	MPTBM_App_Push::flush();
	$check(array() === $sent, 'nothing is sent when nothing changed');

	// The message builder itself, independent of order items.
	$msg = MPTBM_App_Push::message_for('cancelled', 'Fiat');
	$check('Booking cancelled' === $msg[0] && false !== strpos($msg[1], 'Fiat'), 'cancellations are announced');
	$check(null === MPTBM_App_Push::message_for('on-hold', 'Fiat') && null === MPTBM_App_Push::message_for('pending-cancel', 'Fiat'), 'in-between statuses stay quiet');
	$check('Payment failed' === MPTBM_App_Push::message_for('failed', 'Fiat')[0], 'failed payments are announced');

	// Drive the hook directly with a real line item so the whole path is covered.
	$line = new WC_Order_Item_Product();
	$line->set_name('Taxi');
	$line->add_meta_data('_mptbm_id', $vehicle_id);
	$order2 = wc_create_order(array('customer_id' => $users[0]));
	$order2->add_item($line);
	$order2->save();
	$sent = array();
	MPTBM_App_Push::order_status_changed($order2->get_id(), 'pending', 'processing', $order2);
	$check(count(MPTBM_App_Push::queued()) >= 1, 'a confirmation is queued for each device');
	$dead = $tok1;
	MPTBM_App_Push::flush();
	$check(!empty($sent) && 'Booking confirmed' === $sent[0]['title'] && $order2->get_id() === $sent[0]['data']['booking_id'], 'the push carries the title and the booking to open');
	$check(!isset(MPTBM_App_Push::tokens($users[0])[$tok1]), 'a device the push service reports as gone is dropped');
	MPTBM_App_Push::order_status_changed($order2->get_id(), 'processing', 'completed', $order2);
	$check(array() === MPTBM_App_Push::queued(), 'processing to completed is not announced twice');
	$guest = wc_create_order(array('customer_id' => 0));
	$guest->add_item($line);
	MPTBM_App_Push::order_status_changed($guest->get_id(), 'pending', 'processing', $guest);
	$check(array() === MPTBM_App_Push::queued(), 'guest orders are ignored');

	$call('DELETE', '/push/register', array('token' => $tok2), $a['access_token']);
	$check(!isset(MPTBM_App_Push::tokens($users[0])[$tok2]), 'a device can be unregistered');
	foreach (array($order2, $guest) as $o) {
		$o->delete(true);
	}
} finally {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ($users as $id) {
		wp_delete_user($id);
	}
}
if ($failures) {
	echo "FAILED (" . count($failures) . " of $checks checks):\n - " . implode("\n - ", $failures) . "\n";
	exit(1);
}
echo "OK ($checks checks)\n";
