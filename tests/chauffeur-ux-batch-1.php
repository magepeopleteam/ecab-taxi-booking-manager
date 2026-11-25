<?php
/**
 * Functional & security regression tests for Batch 1 Chauffeur UX features (Free + PRO).
 * Run via: wp eval-file wp-content/plugins/ecab-taxi-booking-manager/tests/chauffeur-ux-batch-1.php
 */
if (!defined('ABSPATH')) {
    exit(1);
}

$passed = 0;
$failed = 0;
$assert = function ($cond, $label) use (&$passed, &$failed) {
    if ($cond) {
        $passed++;
    } else {
        $failed++;
        fwrite(STDERR, "FAIL: {$label}\n");
    }
};

// 1. Airport location detection & waiting grace period
$assert(MPTBM_Function::is_airport_location('Heathrow Airport Terminal 5') === true, 'Airport keyword detected');
$assert(MPTBM_Function::is_airport_location('10 Downing Street, London') === false, 'City address not flagged as airport');

$orig_settings = get_option('mptbm_general_settings', array());
$test_settings = is_array($orig_settings) ? $orig_settings : array();
$test_settings['free_airport_waiting_minutes'] = 45;
$test_settings['free_city_waiting_minutes'] = 15;
update_option('mptbm_general_settings', $test_settings);

$assert(abs(MPTBM_Function::effective_waiting_hours(2, true) - 1.25) < 0.001, 'Airport 2h - 45m grace = 1.25h');
$assert(abs(MPTBM_Function::effective_waiting_hours(0.5, true) - 0.0) < 0.001, 'Airport 30m <= 45m grace = 0h');
$assert(abs(MPTBM_Function::effective_waiting_hours(1, false) - 0.75) < 0.001, 'City 1h - 15m grace = 0.75h');
$assert(abs(MPTBM_Function::effective_waiting_hours(0, true) - 0.0) < 0.001, 'Zero waiting hours stays 0');

// Restore settings
update_option('mptbm_general_settings', $orig_settings);

// 2. Create disposable vehicle + booking to test Portal rendering & security
$v_id = wp_insert_post(array('post_type' => 'mptbm_rent', 'post_title' => 'ZZ Unit Test Sedan', 'post_status' => 'publish'));
update_post_meta($v_id, 'mptbm_spec_make_model', 'BMW 7 Series');
update_post_meta($v_id, 'mptbm_spec_color', 'Midnight Blue');
update_post_meta($v_id, 'mptbm_spec_plate', 'CHAUF-77');

$b_id = wp_insert_post(array('post_type' => 'mptbm_booking', 'post_title' => 'ZZ Unit Test Booking', 'post_status' => 'publish'));
update_post_meta($b_id, 'mptbm_id', $v_id);
update_post_meta($b_id, 'mptbm_pin', 'ECAB-UT-1');
update_post_meta($b_id, 'mptbm_billing_email', 'unit@example.com');
update_post_meta($b_id, 'mptbm_start_place', 'Gatwick Airport');
update_post_meta($b_id, 'mptbm_end_place', 'Mayfair, London');
update_post_meta($b_id, 'mptbm_date', gmdate('Y-m-d H:i', time() + 72 * 3600));
update_post_meta($b_id, 'mptbm_order_status', 'pending');
update_post_meta($b_id, 'mptbm_flight_number', 'BA 274');
update_post_meta($b_id, 'mptbm_airport_terminal', 'South Terminal');
update_post_meta($b_id, 'mptbm_nameboard_text', 'MR. SMITH');
update_post_meta($b_id, 'mptbm_lead_passenger_name', 'John Smith');
update_post_meta($b_id, 'mptbm_lead_passenger_phone', '+44 7700 900999');
update_post_meta($b_id, 'mptbm_child_seats', 2);

// Render guest portal detail
$_GET['mptbm_track_email'] = 'unit@example.com';
$_GET['mptbm_track_ref']   = 'ECAB-UT-1';
$html = MPTBM_Customer_Portal::render();
$assert(strpos($html, 'BMW 7 Series') !== false, 'Portal shows vehicle make/model');
$assert(strpos($html, 'Midnight Blue') !== false, 'Portal shows vehicle color');
$assert(strpos($html, 'CHAUF-77') !== false, 'Portal shows vehicle plate');
$assert(strpos($html, 'BA 274') !== false, 'Portal shows flight number');
$assert(strpos($html, 'South Terminal') !== false, 'Portal shows terminal');
$assert(strpos($html, 'MR. SMITH') !== false, 'Portal shows nameboard');
$assert(strpos($html, 'John Smith') !== false, 'Portal shows lead passenger');
$assert(strpos($html, 'mptbm_portal_download_ics') !== false, 'Portal shows .ics calendar button');
$assert(strpos($html, 'Cancel Booking') !== false, 'Portal shows Cancel Booking button when >24h out');
$assert(strpos($html, 'Request Reschedule') !== false, 'Portal shows Request Reschedule button');
unset($_GET['mptbm_track_email'], $_GET['mptbm_track_ref']);

// 3. Security checks on portal authorization (via reflection on authorize_portal_action)
$ref_method = new ReflectionMethod('MPTBM_Customer_Portal', 'authorize_portal_action');
$ref_method->setAccessible(true);

wp_set_current_user(0);
$_POST = array('booking_id' => $b_id, 'nonce' => 'bad_nonce', 'ref' => '');
$res_bad_nonce = $ref_method->invoke(null, 'mptbm_portal_action_', 'POST');
$assert(is_wp_error($res_bad_nonce) && $res_bad_nonce->get_error_code() === 'bad_nonce', 'Rejects invalid nonce');

$good_nonce = wp_create_nonce('mptbm_portal_action_' . $b_id);
$_POST = array('booking_id' => $b_id, 'nonce' => $good_nonce, 'ref' => 'WRONG-TOKEN');
$res_wrong_ref = $ref_method->invoke(null, 'mptbm_portal_action_', 'POST');
$assert(is_wp_error($res_wrong_ref) && $res_wrong_ref->get_error_code() === 'forbidden', 'Rejects wrong guest token');

$valid_token = MPTBM_Function::get_booking_access_token($b_id, true);
$_POST = array('booking_id' => $b_id, 'nonce' => $good_nonce, 'ref' => $valid_token);
$res_ok = $ref_method->invoke(null, 'mptbm_portal_action_', 'POST');
$assert($res_ok === $b_id, 'Accepts valid nonce + guest access token');

// Cleanup
wp_delete_post($b_id, true);
wp_delete_post($v_id, true);
$_POST = array();

echo "PASSED={$passed} FAILED={$failed}\n";
exit($failed > 0 ? 1 : 0);
