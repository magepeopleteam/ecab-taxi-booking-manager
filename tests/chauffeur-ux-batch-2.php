<?php
/**
 * Functional & security regression tests for Batch 2 Fare, Coupon & Deposit features (Free + PRO).
 * Run via: wp eval-file wp-content/plugins/ecab-taxi-booking-manager/tests/chauffeur-ux-batch-2.php
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

// 1. Surcharges & Distance bounds
$orig_gen = get_option('mptbm_general_settings', array());
$test_gen = is_array($orig_gen) ? $orig_gen : array();
$test_gen['airport_pickup_fee']   = 15;
$test_gen['rush_booking_hours']   = 4;
$test_gen['rush_booking_fee']     = 25;
$test_gen['min_trip_distance_km'] = 5;
$test_gen['max_trip_distance_km'] = 200;
update_option('mptbm_general_settings', $test_gen);

$s_airport_rush = MPTBM_Function::calculate_surcharges(0, array(
    'start_place'      => 'Heathrow Airport',
    'end_place'        => 'Central London',
    'booking_datetime' => gmdate('Y-m-d H:i', time() + 2 * 3600),
));
$assert(abs($s_airport_rush['airport_fee'] - 15.0) < 0.001, 'Airport surcharge applied');
$assert(abs($s_airport_rush['rush_fee'] - 25.0) < 0.001, 'Rush fee applied within 4h window');
$assert(abs($s_airport_rush['total'] - 40.0) < 0.001, 'Total surcharge = 40');

$s_city_later = MPTBM_Function::calculate_surcharges(0, array(
    'start_place'      => 'Mayfair',
    'end_place'        => 'Soho',
    'booking_datetime' => gmdate('Y-m-d H:i', time() + 48 * 3600),
));
$assert(abs($s_city_later['total'] - 0.0) < 0.001, 'No surcharge for city ride 48h out');

$assert(is_wp_error(MPTBM_Function::validate_distance_bounds(2000, 'dynamic')), 'Distance 2km rejected when min=5km');
$assert(is_wp_error(MPTBM_Function::validate_distance_bounds(250000, 'dynamic')), 'Distance 250km rejected when max=200km');
$assert(MPTBM_Function::validate_distance_bounds(50000, 'dynamic') === true, 'Distance 50km accepted');
$assert(MPTBM_Function::validate_distance_bounds(1000, 'manual') === true, 'Manual fixed route bypasses distance bounds');

// Tip sanitization
$assert(abs(MPTBM_Function::sanitize_tip_amount(-50) - 0.0) < 0.001, 'Negative tip clamped to 0');
$assert(abs(MPTBM_Function::sanitize_tip_amount(999999) - 10000.0) < 0.001, 'Excessive tip clamped to 10000');

update_option('mptbm_general_settings', $orig_gen);

// 2. PRO Native Coupons & Deposit Mode
if (!class_exists('MPTBM_Native_Checkout')) {
    $nc_file = WP_PLUGIN_DIR . '/ecab-taxi-booking-manager-pro/inc/MPTBM_Native_Checkout.php';
    if (file_exists($nc_file)) {
        require_once $nc_file;
    }
}
$orig_payout = get_option('mptbm_driver_settings', array());
$test_payout = is_array($orig_payout) ? $orig_payout : array();
$test_payout['native_deposit_percent'] = 25;
$test_payout['native_coupons']         = "VIP10:percent:10\nFLAT20:fixed:20";
update_option('mptbm_driver_settings', $test_payout);

$assert(MPTBM_Native_Checkout::get_deposit_percent() === 25, 'Deposit percent = 25');
$c1 = MPTBM_Native_Checkout::evaluate_native_coupon('vip10', 200.0);
$assert($c1['code'] === 'VIP10' && abs($c1['discount'] - 20.0) < 0.001, 'Percent coupon VIP10 = 20 off 200');
$c2 = MPTBM_Native_Checkout::evaluate_native_coupon('FLAT20', 15.0);
$assert($c2['code'] === 'FLAT20' && abs($c2['discount'] - 15.0) < 0.001, 'Fixed coupon capped at subtotal');
$c3 = MPTBM_Native_Checkout::evaluate_native_coupon('INVALID', 100.0);
$assert($c3['discount'] === 0.0, 'Invalid coupon returns 0');

update_option('mptbm_driver_settings', $orig_payout);

// 3. Portal Fare Summary rendering for Batch 2 fields
$b_id = wp_insert_post(array('post_type' => 'mptbm_booking', 'post_title' => 'ZZ Batch 2 Portal', 'post_status' => 'publish'));
update_post_meta($b_id, 'mptbm_pin', 'ECAB-B2-1');
update_post_meta($b_id, 'mptbm_billing_email', 'b2@example.com');
update_post_meta($b_id, 'mptbm_tp', 180.00);
update_post_meta($b_id, 'mptbm_base_price', 140.00);
update_post_meta($b_id, 'mptbm_airport_fee', 15.00);
update_post_meta($b_id, 'mptbm_rush_fee', 25.00);
update_post_meta($b_id, 'mptbm_tip_amount', 20.00);
update_post_meta($b_id, 'mptbm_coupon_code', 'VIP10');
update_post_meta($b_id, 'mptbm_coupon_discount', 20.00);
update_post_meta($b_id, 'mptbm_deposit_paid', 45.00);
update_post_meta($b_id, 'mptbm_balance_due', 135.00);

$_GET['mptbm_track_email'] = 'b2@example.com';
$_GET['mptbm_track_ref']   = 'ECAB-B2-1';
$html = MPTBM_Customer_Portal::render();
$assert(strpos($html, 'Airport Surcharge') !== false, 'Portal renders Airport Surcharge');
$assert(strpos($html, 'Rush Booking Fee') !== false, 'Portal renders Rush Booking Fee');
$assert(strpos($html, 'Chauffeur Tip') !== false, 'Portal renders Chauffeur Tip');
$assert(strpos($html, 'Promo (VIP10)') !== false, 'Portal renders Promo code');
$assert(strpos($html, 'Deposit Paid Online') !== false, 'Portal renders Deposit Paid Online');
$assert(strpos($html, 'Balance Due to Chauffeur') !== false, 'Portal renders Balance Due to Chauffeur');
unset($_GET['mptbm_track_email'], $_GET['mptbm_track_ref']);

wp_delete_post($b_id, true);

echo "PASSED={$passed} FAILED={$failed}\n";
exit($failed > 0 ? 1 : 0);
