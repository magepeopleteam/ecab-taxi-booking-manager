<?php
/**
 * Customer mobile-app API (ecab-app/v1): config, vehicles, and the auth/token lifecycle.
 *
 * Dispatches in-process through rest_do_request(), so no HTTP and no network is used.
 * It creates one throwaway customer and deletes it again.
 *
 * Run with: wp --path=/var/www/html/magepeople eval-file wp-content/plugins/ecab-taxi-booking-manager/tests/app-api.php
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

$call = static function (string $method, string $route, array $body = array(), string $token = ''): WP_REST_Response {
	$request = new WP_REST_Request($method, '/ecab-app/v1' . $route);
	if ($body) {
		$request->set_header('content-type', 'application/json');
		$request->set_body(wp_json_encode($body));
		$request->set_body_params($body);
	}
	if ('' !== $token) {
		$request->set_header('authorization', 'Bearer ' . $token);
	}
	return rest_do_request($request);
};

// Rate-limit counters are per IP; start from zero so a re-run is deterministic.
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
foreach (array('public', 'auth', 'register', 'login', 'refresh', 'forgot') as $bucket) {
	delete_transient('mptbm_app_rl_' . md5($bucket . '|' . $_SERVER['REMOTE_ADDR']));
}

// --- Public reads ---------------------------------------------------------------------------
$config = $call('GET', '/config');
$check(200 === $config->get_status(), 'config is public');
$data = $config->get_data();
foreach (array('api_version', 'site', 'currency', 'map', 'booking', 'payment', 'features', 'price_modes') as $key) {
	$check(array_key_exists($key, $data), "config has $key");
}
$check(!isset($data['map']['api_key']) && !isset($data['map']['gmap_api_key']), 'config never exposes a map API key');
$check(is_bool($data['features']['pro']) && $data['features']['cancel_booking'] === $data['features']['pro'], 'pro-only features follow Pro');

$check($data['features']['pdf_ticket'] === ($data['features']['pro'] && class_exists('\\Mpdf\\Mpdf')), 'receipts are offered only when Pro and its PDF library are present');
$check(array_key_exists('brand', $data) && array_key_exists('primary', $data['brand']), 'config carries the brand colour');
foreach (array('', 'red', '#12345', '#GGGGGG', '#1f8a4c') as $input) {
	update_option('mptbm_app_settings', array('brand_color' => $input));
	$brand = $call('GET', '/config')->get_data()['brand'];
	$expected = '#1f8a4c' === $input ? '#ffffff' : '';
	$check(('#1f8a4c' === $input ? '#1f8a4c' : '') === $brand['primary'] && $expected === $brand['on_primary'], "brand colour '$input' is " . ('#1f8a4c' === $input ? 'accepted with white text' : 'ignored'));
}
update_option('mptbm_app_settings', array('brand_color' => '#fde047'));
$check('#111827' === $call('GET', '/config')->get_data()['brand']['on_primary'], 'a light brand colour gets dark text');
delete_option('mptbm_app_settings');

$vehicles = $call('GET', '/vehicles', array());
$check(200 === $vehicles->get_status() && is_array($vehicles->get_data()), 'vehicles list is public');
$check(isset($vehicles->get_headers()['X-WP-Total']), 'vehicles list reports a total');
$check(404 === $call('GET', '/vehicles/999999999')->get_status(), 'unknown vehicle is 404');

$draft_id = wp_insert_post(array('post_type' => MPTBM_Function::get_cpt(), 'post_status' => 'draft', 'post_title' => 'Draft taxi'));
$check(404 === $call('GET', '/vehicles/' . $draft_id)->get_status(), 'draft vehicle is not exposed');
wp_delete_post($draft_id, true);

// --- Auth lifecycle -------------------------------------------------------------------------
$email    = 'apptest-' . wp_generate_password(8, false) . '@example.com';
$password = 'Passw0rd!' . wp_generate_password(6, false);

$check(400 === $call('POST', '/auth/register', array('email' => 'not-an-email', 'password' => $password, 'first_name' => 'A'))->get_status(), 'invalid email rejected');
$check(400 === $call('POST', '/auth/register', array('email' => $email, 'password' => 'short', 'first_name' => 'A'))->get_status(), 'weak password rejected');

$registered = $call('POST', '/auth/register', array('email' => $email, 'password' => $password, 'first_name' => 'App', 'last_name' => 'Tester', 'phone' => '555'));
$check(201 === $registered->get_status(), 'register succeeds');
$tokens  = $registered->get_data();
$user_id = (int) ($tokens['user']['id'] ?? 0);
$check($user_id > 0 && !empty($tokens['access_token']) && !empty($tokens['refresh_token']), 'register returns user and both tokens');
$check(!isset($tokens['user']['user_pass']), 'user payload carries no password hash');
$check(409 === $call('POST', '/auth/register', array('email' => $email, 'password' => $password, 'first_name' => 'A'))->get_status(), 'duplicate email is 409');

$check(401 === $call('GET', '/me')->get_status(), '/me without a token is 401');
$check(401 === $call('GET', '/me', array(), $tokens['access_token'] . 'x')->get_status(), 'tampered token is 401');
$check($email === ($call('GET', '/me', array(), $tokens['access_token'])->get_data()['email'] ?? ''), '/me returns the token owner');

$other_payload = rtrim(strtr(base64_encode(wp_json_encode(array('uid' => 1, 'exp' => time() + 3600, 'ver' => 0))), '+/', '-_'), '=');
$check(401 === $call('GET', '/me', array(), $other_payload . '.' . explode('.', $tokens['access_token'])[1])->get_status(), 'a signature cannot be reused for another user');

$check(401 === $call('POST', '/auth/login', array('username' => $email, 'password' => 'wrong'))->get_status(), 'wrong password is 401');
$check(200 === $call('POST', '/auth/login', array('username' => $email, 'password' => $password))->get_status(), 'login succeeds');

$phone = $call('PUT', '/me', array('phone' => '999'), $tokens['access_token'])->get_data();
$check('999' === ($phone['phone'] ?? ''), 'profile update saves the phone');

$refreshed = $call('POST', '/auth/refresh', array('refresh_token' => $tokens['refresh_token']));
$check(200 === $refreshed->get_status(), 'refresh succeeds');
$check(401 === $call('POST', '/auth/refresh', array('refresh_token' => $tokens['refresh_token']))->get_status(), 'a refresh token works only once');
$check(401 === $call('POST', '/auth/refresh', array('refresh_token' => '1.guess'))->get_status(), 'guessed refresh token is 401');

$new_access = $refreshed->get_data()['access_token'];
$check(200 === $call('POST', '/auth/logout', array(), $new_access)->get_status(), 'logout succeeds');
$check(401 === $call('GET', '/me', array(), $new_access)->get_status(), 'access token dies at logout');
$check(401 === $call('POST', '/auth/refresh', array('refresh_token' => $refreshed->get_data()['refresh_token']))->get_status(), 'refresh token dies at logout');

$forgot_known   = $call('POST', '/auth/forgot', array('email' => $email));
$forgot_unknown = $call('POST', '/auth/forgot', array('email' => 'nobody-' . wp_generate_password(6, false) . '@example.com'));
$check($forgot_known->get_data() === $forgot_unknown->get_data(), 'forgot-password answers the same for known and unknown emails');

// --- Throttling -----------------------------------------------------------------------------
delete_transient('mptbm_app_rl_' . md5('login|' . $_SERVER['REMOTE_ADDR']));
$last = 0;
for ($i = 0; $i < 12; $i++) {
	$last = $call('POST', '/auth/login', array('username' => $email, 'password' => 'wrong'))->get_status();
}
$check(429 === $last, 'repeated failed logins are throttled');

// --- Cleanup --------------------------------------------------------------------------------
require_once ABSPATH . 'wp-admin/includes/user.php';
if ($user_id) {
	wp_delete_user($user_id);
}
foreach (array('public', 'auth', 'register', 'login', 'refresh', 'forgot') as $bucket) {
	delete_transient('mptbm_app_rl_' . md5($bucket . '|' . $_SERVER['REMOTE_ADDR']));
}

if ($failures) {
	echo "FAILED (" . count($failures) . " of $checks checks):\n - " . implode("\n - ", $failures) . "\n";
	exit(1);
}
echo "OK ($checks checks)\n";
