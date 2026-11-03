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
// The app logo: the one chosen in Mobile App settings, else the site's own, else none.
$check(array_key_exists('logo', $call('GET', '/config')->get_data()['brand']), 'config carries the app logo');
$logo_id = wp_insert_attachment(array('post_title' => 'apptest logo', 'post_mime_type' => 'image/png', 'post_status' => 'inherit'), 'apptest-logo.png');
update_option('mptbm_app_settings', array('brand_logo' => $logo_id));
$check(false !== strpos((string) $call('GET', '/config')->get_data()['brand']['logo'], 'apptest-logo'), 'the logo chosen in Mobile App settings is used');
update_option('mptbm_app_settings', array('brand_logo' => 999999999));
$check('' === $call('GET', '/config')->get_data()['brand']['logo'] || false !== strpos($call('GET', '/config')->get_data()['brand']['logo'], 'http'), 'a logo that no longer exists falls back instead of breaking');
wp_delete_attachment($logo_id, true);
update_option('mptbm_app_settings', array('brand_color' => '#fde047'));
$check('#111827' === $call('GET', '/config')->get_data()['brand']['on_primary'], 'a light brand colour gets dark text');
delete_option('mptbm_app_settings');

// --- The search form follows the website's booking form -------------------------------------------------
$saved_app = get_option('mptbm_app_settings', null);
$saved_general = get_option('mptbm_general_settings', null);
$form = static function (array $app = array(), array $general = array()) use ($call, $saved_general): array {
	update_option('mptbm_app_settings', $app);
	update_option('mptbm_general_settings', array_merge(is_array($saved_general) ? $saved_general : array(), $general));
	delete_transient('mptbm_app_detected_tabs');
	return array_column($call('GET', '/config')->get_data()['search_form']['tabs'], null, 'key');
};
$order = static function (array $tabs): string {
	return implode(',', array_keys($tabs));
};
$tabs = $form(array('trip_tabs' => 'hourly, distance ,flat-rate,bogus,hourly'));
$check('hourly,distance,manual' === $order($tabs), 'the tabs follow the configured order, accept the website\'s names and drop unknown or repeated ones (got ' . $order($tabs) . ')');
$check('fixed_hourly' === $tabs['hourly']['price_based'] && 'dynamic' === $tabs['distance']['price_based'] && 'manual' === $tabs['manual']['price_based'], 'each tab runs its own pricing mode');
$check('Hourly' === $tabs['hourly']['label'] && 'Flat rate' === $tabs['manual']['label'], 'tab labels are the website\'s');
$check(array('unit' => 'hour', 'min' => 1, 'max' => 12) === $tabs['hourly']['duration'] && null === $tabs['distance']['duration'], 'only the hourly tab asks for hours');
$check(false === $tabs['hourly']['return'] && false === $tabs['hourly']['waiting'], 'the hourly tab has no return trip or waiting time, like the website');
$check('map' === $tabs['hourly']['dropoff'] && 'map' === $tabs['hourly']['pickup'], 'the hourly tab asks for a drop-off by default');
$check('manual' === $tabs['manual']['pickup'] && 'manual' === $tabs['manual']['dropoff'], 'the flat-rate tab picks both places from the shop\'s list');
$tabs = $form(array('trip_tabs' => 'hourly,daily,fixed_route'), array('disable_dropoff_hourly' => 'disable', 'minimum_booking_hours' => '3', 'minimum_booking_days' => '2', 'show_number_of_passengers' => 'yes'));
$check('none' === $tabs['hourly']['dropoff'], 'the hourly drop-off is hidden when the shop turns it off');
$check(3 === $tabs['hourly']['duration']['min'] && array('unit' => 'day', 'min' => 2, 'max' => 30) === $tabs['daily']['duration'], 'the shortest booking comes from the shop\'s settings');
$check('none' === $tabs['fixed_route']['dropoff'] && 'fixed_route' === $tabs['fixed_route']['pickup'], 'a fixed route is one choice with no drop-off');
$check(true === $tabs['daily']['passengers'], 'the passenger count is asked for when the shop turns it on');
$tabs = $form(array('trip_tabs' => 'distance'), array('mptbm_extra_stop_between_pickup_dropoff' => 'yes', 'mptbm_max_extra_stops' => '2', 'taxi_return' => 'enable', 'taxi_waiting_time' => 'enable', 'transfer_type' => 'enable', 'show_number_of_passengers' => 'no'));
$check(2 === $tabs['distance']['extra_stops'] && true === $tabs['distance']['return'] && true === $tabs['distance']['waiting'] && false === $tabs['distance']['passengers'], 'the distance tab offers stops, return and waiting time as configured');
$tabs = $form(array('trip_tabs' => 'distance,manual'), array('mptbm_extra_stop_between_pickup_dropoff' => 'yes', 'transfer_type' => 'disable'));
$check(0 === $tabs['manual']['extra_stops'] && false === $tabs['distance']['return'], 'stops are not offered on the flat-rate tab and the return trip can be switched off');
$tabs = $form(array());
$check(count($tabs) >= 1, 'with nothing configured the app still gets the website\'s tabs');
// --- Pickup dates and times: the website's own schedule ----------------------------------------------------------------
$sched = static function (array $general = array()) use ($form, $saved_general): array {
	update_option('mptbm_general_settings', array_merge(is_array($saved_general) ? $saved_general : array(), $general));
	return array_values($form(array('trip_tabs' => 'distance'), $general))[0]['schedule'];
};
$base_schedule = $sched();
$check(count($base_schedule['dates']) > 0 && $base_schedule['dates'] === array_values(array_unique($base_schedule['dates'])), 'there are bookable dates, each listed once');
$sorted = $base_schedule['dates'];
sort($sorted);
$check($sorted === $base_schedule['dates'] && $base_schedule['dates'][0] >= $base_schedule['today'], 'dates run from the first bookable day, in order, none in the past');
$check(30 === $base_schedule['interval'] && 'g:i a' === $base_schedule['time_format'] || $base_schedule['interval'] > 0, 'the interval between times is the shop\'s');
$check(15 === $sched(array('mptbm_pickup_interval_time' => '15'))['interval'], 'a different pickup interval in the settings reaches the app');
$check($base_schedule['now_minutes'] >= 0 && $base_schedule['now_minutes'] < 1440, 'the shop\'s clock is reported, in minutes since midnight');
$with_buffer = $sched(array('enable_buffer_time' => '1700'));
$check(1700 === $with_buffer['buffer_minutes'], 'the buffer is reported');
$check($with_buffer['dates'][0] > $base_schedule['today'], 'a buffer longer than the rest of today drops today, as the website does');
$minutes = new ReflectionMethod('MPTBM_App_Form', 'minutes');
$minutes->setAccessible(true);
$check(390 === $minutes->invoke(null, 6.30) && 600 === $minutes->invoke(null, 10.0) && 1440 === $minutes->invoke(null, 24.0), 'schedule values such as 6.30 mean 06:30, as on the website');
$check(isset($base_schedule['day_ranges']->monday) && $base_schedule['day_ranges']->monday[0] < $base_schedule['day_ranges']->monday[1], 'each open weekday has a window of times');

// --- The route readout (distance and time once both places are chosen) ----------------------------------------------------
$route_stub = static function ($pre, $args, $url) {
	if (false !== strpos($url, 'router.project-osrm.org')) {
		return array('headers' => array(), 'body' => wp_json_encode(array('code' => 'Ok', 'routes' => array(array('distance' => 12500, 'duration' => 1500)))), 'response' => array('code' => 200, 'message' => 'OK'), 'cookies' => array(), 'filename' => null);
	}
	return $pre;
};
add_filter('pre_http_request', $route_stub, 10, 3);
$saved_map_for_route = get_option('mptbm_map_api_settings', null);
update_option('mptbm_map_api_settings', array_merge(is_array($saved_map_for_route) ? $saved_map_for_route : array(), array('display_map' => 'openstreetmap', 'gmap_api_key' => '', 'gmap_server_api_key' => '')));
$route = static function (array $body) use ($call): WP_REST_Response {
	delete_transient('mptbm_app_rl_' . md5('places|' . $_SERVER['REMOTE_ADDR']));
	return $call('POST', '/route', $body);
};
$routed = $route(array('points' => array(array('lat' => 23.78, 'lng' => 90.41), array('lat' => 23.85, 'lng' => 90.39))));
$check(200 === $routed->get_status() && 12500.0 === (float) $routed->get_data()['distance'] && 1500.0 === (float) $routed->get_data()['duration'], 'the route between two places reports its distance and time: ' . wp_json_encode($routed->get_data()));
$check('' !== $routed->get_data()['distance_text'] && '' !== $routed->get_data()['duration_text'], 'and says them in words, in the shop\'s unit');
$check(400 === $route(array('points' => array(array('lat' => 23.78, 'lng' => 90.41))))->get_status(), 'one place is not a route');
$check(400 === $route(array('points' => array(array('lat' => 'x', 'lng' => 1), array('lat' => 1, 'lng' => 1))))->get_status(), 'places without coordinates are not a route');
$check(400 === $route(array('points' => array(array('lat' => 91, 'lng' => 0), array('lat' => 0, 'lng' => 0))))->get_status(), 'impossible coordinates are refused');
remove_filter('pre_http_request', $route_stub, 10);
null === $saved_map_for_route ? delete_option('mptbm_map_api_settings') : update_option('mptbm_map_api_settings', $saved_map_for_route);

null === $saved_app ? delete_option('mptbm_app_settings') : update_option('mptbm_app_settings', $saved_app);
null === $saved_general ? delete_option('mptbm_general_settings') : update_option('mptbm_general_settings', $saved_general);
delete_transient('mptbm_app_detected_tabs');

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
