<?php
/**
 * Request handlers that must check who is asking and what they may change.
 *
 * - The vehicle editor's save_post handler writes only for the editor's own submission (nonce + edit_post),
 *   only on vehicles; any other save of a vehicle keeps its switches.
 * - Analytics data needs the Analytics screen's capability, not just its nonce.
 * - The public geocode cache keeps the first coordinates stored for a place and caps writes per client.
 * - A vehicle's theme template is a file name inside templates/themes/, never a path out of it.
 *
 * Run with: wp --path=/var/www/html/magepeople eval-file wp-content/plugins/ecab-taxi-booking-manager/tests/security-hardening.php
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

if (!class_exists('MPTBM_Function')) {
	echo "SKIPPED: E-cab Taxi Booking Manager is not active.\n";
	return;
}
// Admin-only classes are loaded in wp-admin; load the two under test the same way.
foreach (array('MPTBM_Rent_Custom_Editor' => '/Admin/MPTBM_Rent_Custom_Editor.php', 'MPTBM_Analytics_Dashboard' => '/Admin/MPTBM_Analytics_Dashboard.php') as $class => $file) {
	if (!class_exists($class)) {
		require_once MPTBM_PLUGIN_DIR . $file;
	}
}

/** Run an admin-ajax action and return the decoded JSON reply. */
$ajax = static function (string $action, array $post) {
	$_POST    = $post;
	$_REQUEST = $post;
	add_filter('wp_doing_ajax', '__return_true');
	$die = static function () {
		return static function () {
			throw new RuntimeException('ajax-done');
		};
	};
	add_filter('wp_die_ajax_handler', $die);
	ob_start();
	try {
		do_action($action);
	} catch (RuntimeException $e) {
		// wp_send_json() ended the request.
	}
	$out = (string) ob_get_clean();
	remove_filter('wp_die_ajax_handler', $die);
	remove_filter('wp_doing_ajax', '__return_true');
	$_POST = $_REQUEST = array();
	return json_decode($out, true);
};

$created_posts = array();
$created_users = array();
$saved_server  = $_SERVER['REMOTE_ADDR'] ?? null;

try {
	$admin_id = (int) wp_insert_user(array('user_login' => 'zzsec_admin_' . wp_generate_password(6, false), 'user_pass' => wp_generate_password(20), 'user_email' => 'zzsec-admin-' . wp_generate_password(6, false) . '@example.com', 'role' => 'administrator'));
	$sub_id   = (int) wp_insert_user(array('user_login' => 'zzsec_sub_' . wp_generate_password(6, false), 'user_pass' => wp_generate_password(20), 'user_email' => 'zzsec-sub-' . wp_generate_password(6, false) . '@example.com', 'role' => 'subscriber'));
	$created_users = array($admin_id, $sub_id);

	// --- 1. Vehicle editor save_post handler ---------------------------------------------------------------
	wp_set_current_user($admin_id);
	$vehicle = (int) wp_insert_post(array('post_type' => MPTBM_Function::get_cpt(), 'post_status' => 'publish', 'post_title' => 'zzsec vehicle'));
	$created_posts[] = $vehicle;
	update_post_meta($vehicle, 'mptbm_display_taxi_base_fare_pricing', 'on');
	update_post_meta($vehicle, 'mptbm_inclusive_manual_locations', 'on');

	$_POST = array();
	wp_update_post(array('ID' => $vehicle, 'post_title' => 'zzsec vehicle renamed')); // quick edit, REST, code ...
	$check('on' === get_post_meta($vehicle, 'mptbm_display_taxi_base_fare_pricing', true), 'a save from outside the editor keeps the base-fare switch');
	$check('on' === get_post_meta($vehicle, 'mptbm_inclusive_manual_locations', true), 'a save from outside the editor keeps the manual-locations switch');

	$page = (int) wp_insert_post(array('post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'zzsec post'));
	$created_posts[] = $page;
	$_POST = array('mptbm_display_taxi_base_fare_pricing' => 'on', 'mptbm_transportation_type_nonce' => wp_create_nonce('mptbm_transportation_type_nonce'));
	wp_update_post(array('ID' => $page, 'post_title' => 'zzsec post saved'));
	$check('' === get_post_meta($page, 'mptbm_display_taxi_base_fare_pricing', true), 'saving another post type writes no vehicle fields');

	wp_set_current_user($sub_id);
	$_POST = array('mptbm_display_taxi_base_fare_pricing' => 'off', 'mptbm_transportation_type_nonce' => wp_create_nonce('mptbm_transportation_type_nonce'));
	do_action('save_post', $vehicle, get_post($vehicle), true);
	$check('on' === get_post_meta($vehicle, 'mptbm_display_taxi_base_fare_pricing', true), 'a user who cannot edit the vehicle cannot change it');

	wp_set_current_user($admin_id);
	$_POST = array('mptbm_display_taxi_base_fare_pricing' => 'on', 'mptbm_transportation_type_nonce' => 'bad-nonce');
	update_post_meta($vehicle, 'mptbm_display_taxi_base_fare_pricing', 'off');
	do_action('save_post', $vehicle, get_post($vehicle), true);
	$check('off' === get_post_meta($vehicle, 'mptbm_display_taxi_base_fare_pricing', true), 'a forged editor submission changes nothing');

	$_POST = array('mptbm_display_taxi_base_fare_pricing' => 'on', 'mptbm_transportation_type_nonce' => wp_create_nonce('mptbm_transportation_type_nonce'));
	do_action('save_post', $vehicle, get_post($vehicle), true);
	$check('on' === get_post_meta($vehicle, 'mptbm_display_taxi_base_fare_pricing', true), 'the editor itself still saves the switch on');
	$check('off' === get_post_meta($vehicle, 'mptbm_inclusive_manual_locations', true), 'the editor itself still saves an unticked switch off');
	$_POST = array();

	// --- 2. Analytics data -------------------------------------------------------------------------------
	wp_set_current_user($sub_id);
	$reply = $ajax('wp_ajax_mptbm_get_analytics_data', array('nonce' => wp_create_nonce('mptbm_analytics_nonce')));
	$check(is_array($reply) && false === $reply['success'] && !isset($reply['data']['total_bookings']), 'a subscriber gets no analytics, even with a nonce');
	wp_set_current_user($admin_id);
	$reply = $ajax('wp_ajax_mptbm_get_analytics_data', array('nonce' => wp_create_nonce('mptbm_analytics_nonce'), 'start_date' => '2026-01-01', 'end_date' => '2026-12-31'));
	$check(is_array($reply) && true === $reply['success'] && isset($reply['data']['total_bookings']), 'an administrator still gets analytics');

	// --- 3. Public geocode cache -------------------------------------------------------------------------
	wp_set_current_user(0);
	$_SERVER['REMOTE_ADDR'] = '203.0.113.' . wp_rand(100, 200);
	delete_transient('mptbm_geocode_set_' . md5($_SERVER['REMOTE_ADDR']));
	$nonce = wp_create_nonce('mptbm_geocode_cache');
	$place = 'zzsec landmark ' . wp_generate_password(6, false);
	$ajax('wp_ajax_nopriv_mptbm_geocode_cache_set', array('nonce' => $nonce, 'q' => $place, 'result_lat' => '23.81', 'result_lng' => '90.41'));
	$ajax('wp_ajax_nopriv_mptbm_geocode_cache_set', array('nonce' => $nonce, 'q' => $place, 'result_lat' => '-33.86', 'result_lng' => '151.20'));
	$got = $ajax('wp_ajax_nopriv_mptbm_geocode_cache_get', array('nonce' => $nonce, 'q' => $place));
	$check(is_array($got) && !empty($got['success']) && abs((float) $got['data']['lat'] - 23.81) < 0.0001, 'a cached place keeps its first coordinates');

	$last = null;
	$written = array($place);
	for ($i = 0; $i < 31; $i++) {
		$written[] = $name = 'zzsec flood ' . $i . ' ' . wp_generate_password(6, false);
		$last = $ajax('wp_ajax_nopriv_mptbm_geocode_cache_set', array('nonce' => $nonce, 'q' => $name, 'result_lat' => '1', 'result_lng' => '1'));
	}
	$check(is_array($last) && false === $last['success'], 'one client cannot keep writing new places');
	$flood_last = $ajax('wp_ajax_nopriv_mptbm_geocode_cache_get', array('nonce' => $nonce, 'q' => end($written)));
	$check(is_array($flood_last) && empty($flood_last['success']), 'a write over the limit is not stored');
	$key_method = new ReflectionMethod('MPTBM_Dependencies', 'geocode_cache_key');
	$key_method->setAccessible(true);
	$deps = (new ReflectionClass('MPTBM_Dependencies'))->newInstanceWithoutConstructor();
	foreach ($written as $name) {
		delete_transient($key_method->invoke($deps, sanitize_text_field($name), null, null));
	}
	delete_transient('mptbm_geocode_set_' . md5($_SERVER['REMOTE_ADDR']));

	// --- 4. Theme template of a vehicle -------------------------------------------------------------------
	global $post;
	$saved_post = $post;
	$post = get_post($vehicle);
	setup_postdata($post);
	foreach (array('../registration/get_details.php', '../../../../wp-config.php', '..\\..\\index.php') as $evil) {
		update_post_meta($vehicle, 'mptbm_theme_file', $evil);
		$path = MPTBM_Function::details_template_path();
		$check('default.php' === basename($path) && false === strpos($path, '..'), "the theme file `$evil` resolves to default.php");
	}
	update_post_meta($vehicle, 'mptbm_theme_file', 'default.php');
	$check('default.php' === basename(MPTBM_Function::details_template_path()), 'the default theme file still resolves');
	$post = $saved_post;
	wp_reset_postdata();
} catch (Throwable $e) {
	$failures[] = 'Unexpected ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine();
} finally {
	$_POST = $_REQUEST = array();
	foreach ($created_posts as $id) {
		wp_delete_post($id, true);
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ($created_users as $id) {
		if ($id) {
			wp_delete_user($id);
		}
	}
	if (null !== $saved_server) {
		$_SERVER['REMOTE_ADDR'] = $saved_server;
	}
	wp_set_current_user(0);
}

if ($failures) {
	echo "FAILED (" . count($failures) . " of $checks checks):\n - " . implode("\n - ", $failures) . "\n";
	exit(1);
}
echo "OK ($checks checks)\n";
