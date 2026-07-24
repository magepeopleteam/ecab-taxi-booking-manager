<?php
/**
 * Runtime security regression checks.
 *
 * Run with: wp --path=/var/www/html/magepeople eval-file wp-content/plugins/ecab-taxi-booking-manager/tests/security-regression.php
 */

if (!defined('ABSPATH')) {
	die;
}

$failures = array();
$check = static function ($condition, $message) use (&$failures): void {
	if (!$condition) {
		$failures[] = $message;
	}
};

$check(post_type_exists('mptbm_rent'), 'Transportation CPT is not registered.');
$check(post_type_exists('mptbm_booking'), 'Booking CPT is not registered.');
$check(post_type_exists('mptbm_service_book'), 'Extra-service booking CPT is not registered.');
$booking_type = get_post_type_object('mptbm_booking');
$service_type = get_post_type_object('mptbm_service_book');
$check($booking_type && !$booking_type->show_in_rest && !$booking_type->publicly_queryable, 'Booking CPT must remain private.');
$check($service_type && !$service_type->show_in_rest && !$service_type->publicly_queryable, 'Extra-service booking CPT must remain private.');

foreach (array('administrator', 'shop_manager') as $role_name) {
	$role = get_role($role_name);
	$check($role && $role->has_cap('manage_mptbm_transportation'), "{$role_name} is missing the transportation capability.");
}

foreach (array(
	'wp_ajax_nopriv_get_mptbm_order_list',
	'wp_ajax_nopriv_update_service_status',
	'wp_ajax_nopriv_update_service_status_driver_panel',
	'wp_ajax_nopriv_get_mptbm_ex_service',
	'wp_ajax_nopriv_mep_wl_ajax_license_activate',
	'wp_ajax_nopriv_mep_wl_ajax_license_deactivate',
) as $hook) {
	$check(false === has_action($hook), "Dangerous public AJAX hook remains registered: {$hook}");
}

global $wpdb;
$secret_column = $wpdb->get_row("SHOW COLUMNS FROM `{$wpdb->prefix}mptbm_api_keys` LIKE 'api_secret'"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$check($secret_column && stripos((string) $secret_column->Type, 'varchar(255)') !== false, 'API secret column is not large enough for password hashes.');

$vehicle_id = 0;
$booking_id = 0;
try {
	$vehicle_id = wp_insert_post(array(
		'post_type'   => 'mptbm_rent',
		'post_status' => 'publish',
		'post_title'  => 'MPTBM Security Regression Vehicle',
	));
	$check(!is_wp_error($vehicle_id) && $vehicle_id > 0, 'Could not create the temporary vehicle.');
	if ($vehicle_id && !is_wp_error($vehicle_id)) {
		update_post_meta($vehicle_id, 'mptbm_maximum_passenger', 2);
		$_POST['mptbm_max_passenger'] = 3;
		$selection = MPTBM_Function::validate_checkout_selections($vehicle_id);
		$check(is_wp_error($selection) && $selection->get_error_code() === 'mptbm_capacity', 'Capacity tampering was not rejected.');
		unset($_POST['mptbm_max_passenger']);

		$start = wp_date('Y-m-d H:i:s', current_time('timestamp') + DAY_IN_SECONDS);
		$booking_id = wp_insert_post(array(
			'post_type'   => 'mptbm_booking',
			'post_status' => 'publish',
			'post_title'  => 'MPTBM Security Regression Booking',
		));
		if ($booking_id && !is_wp_error($booking_id)) {
			update_post_meta($booking_id, 'mptbm_id', $vehicle_id);
			update_post_meta($booking_id, 'mptbm_date', $start);
			update_post_meta($booking_id, 'mptbm_duration', HOUR_IN_SECONDS);
			update_post_meta($booking_id, 'mptbm_transport_quantity', 1);
			update_post_meta($booking_id, 'mptbm_order_status', 'pending');
			$token = MPTBM_Function::get_booking_access_token($booking_id, true);
			$check(strlen($token) === 48, 'Booking access token has the wrong length.');
			$check(MPTBM_Function::verify_booking_access_token($booking_id, $token), 'Valid booking token was rejected.');
			$check(!MPTBM_Function::verify_booking_access_token($booking_id, str_repeat('x', 48)), 'Invalid booking token was accepted.');
			$available = MPTBM_Function::get_available_quantity($vehicle_id, $start, '', true, HOUR_IN_SECONDS);
			$check($available === 0, 'Overlapping single-vehicle inventory was not blocked.');
		}
	}
} finally {
	unset($_POST['mptbm_max_passenger']);
	if ($booking_id && !is_wp_error($booking_id)) {
		wp_delete_post($booking_id, true);
	}
	if ($vehicle_id && !is_wp_error($vehicle_id)) {
		wp_delete_post($vehicle_id, true);
	}
}

$check(class_exists('Google_Client'), 'Updated Google API client did not autoload.');
$check(class_exists('Google_Service_Calendar'), 'Updated Google Calendar service did not autoload.');

if ($failures) {
	foreach ($failures as $failure) {
		WP_CLI::warning($failure);
	}
	WP_CLI::error(count($failures) . ' security regression check(s) failed.');
}

WP_CLI::success('All MPTBM security regression checks passed.');
