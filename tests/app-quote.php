<?php
/**
 * Mobile-app quote (POST /ecab-app/v1/quote): input validation and the operation-area /
 * geo-fence rules that the website leaves to browser JavaScript.
 *
 * Pricing itself is not re-tested here: the quote runs the website's own search pipeline, and
 * its figures were compared with the booking form's on a live site. No network is used; the
 * vehicles and areas are throwaway posts that are deleted at the end.
 *
 * Run with: wp --path=/var/www/html/magepeople eval-file wp-content/plugins/ecab-taxi-booking-manager/tests/app-quote.php
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

$_SERVER['REMOTE_ADDR'] = '203.0.113.20';
delete_transient('mptbm_app_rl_' . md5('quote|' . $_SERVER['REMOTE_ADDR']));

$quote = static function (array $body): WP_REST_Response {
	$request = new WP_REST_Request('POST', '/ecab-app/v1/quote');
	$request->set_header('content-type', 'application/json');
	$request->set_body(wp_json_encode($body));
	return rest_do_request($request);
};
$tomorrow = gmdate('Y-m-d', time() + DAY_IN_SECONDS);
$base     = array(
	'price_based' => 'dynamic',
	'start'       => array('name' => 'A', 'lat' => 23.80, 'lng' => 90.40),
	'end'         => array('name' => 'B', 'lat' => 23.78, 'lng' => 90.42),
	'date'        => $tomorrow,
	'time'        => '10:30',
);

// --- Validation (answers before any map lookup happens) -----------------------------------------
$status = static function (array $override) use ($quote, $base): int {
	return $quote(array_merge($base, $override))->get_status();
};
$check(400 === $status(array('price_based' => 'bogus')), 'unknown trip type is rejected');
$check(400 === $status(array('start' => array('name' => 'A', 'lat' => 'x', 'lng' => 1))), 'non-numeric coordinates are rejected');
$check(400 === $status(array('start' => array('name' => 'A', 'lat' => 91, 'lng' => 0))), 'latitude out of range is rejected');
$check(400 === $status(array('end' => null)), 'a distance trip needs a drop-off');
$check(400 === $status(array('start' => array('name' => '', 'lat' => 23.8, 'lng' => 90.4))), 'a pick-up without a name is rejected');
$check(400 === $status(array('date' => '2026-02-30')), 'impossible date is rejected');
$check(400 === $status(array('date' => 'tomorrow')), 'non-ISO date is rejected');
$check(400 === $status(array('time' => '25:00')), 'invalid time is rejected');
$check(400 === $status(array('price_based' => 'fixed_hourly', 'end' => null)), 'hourly trips need a duration');

// --- Throttling ------------------------------------------------------------------------------
$last = 0;
for ($i = 0; $i < 32; $i++) {
	$last = $status(array('time' => '99:99'));
}
$check(429 === $last, 'quotes are rate limited');
delete_transient('mptbm_app_rl_' . md5('quote|' . $_SERVER['REMOTE_ADDR']));

// --- Operation area / geo-fence rules --------------------------------------------------------------
$square = static function (float $lat, float $lng, float $size = 0.1): array {
	return array($lat, $lng, $lat + $size, $lng, $lat + $size, $lng + $size, $lat, $lng + $size);
};
$posts = array();
$make  = static function (string $type, array $meta) use (&$posts): int {
	$id      = wp_insert_post(array('post_type' => $type, 'post_status' => 'publish', 'post_title' => 'apptest ' . $type));
	$posts[] = $id;
	foreach ($meta as $key => $value) {
		update_post_meta($id, $key, $value);
	}
	return $id;
};
$trip = static function (string $mode, array $start, ?array $end) {
	return array(
		'price_based' => $mode,
		'start'       => array('name' => 'S', 'lat' => $start[0], 'lng' => $start[1]),
		'end'         => $end ? array('name' => 'E', 'lat' => $end[0], 'lng' => $end[1]) : array(),
	);
};
$inside_a  = array(10.05, 20.05);
$inside_a2 = array(10.06, 20.06);
$inside_b  = array(30.05, 40.05);
$outside   = array(50.0, 60.0);

$area_both = $make('mptbm_operate_areas', array('mptbm-operation-type' => 'fixed-operation-area-type', 'mptbm-coordinates-three' => $square(10, 20)));
$area_fence = $make('mptbm_operate_areas', array(
	'mptbm-operation-type' => 'geo-fence-operation-area-type',
	'mptbm-coordinates-one' => $square(10, 20),
	'mptbm-coordinates-two' => $square(30, 40),
	'mptbm-geo-fence-direction' => 'geo-fence-one-direction',
));
$area_fence_two = $make('mptbm_operate_areas', array(
	'mptbm-operation-type' => 'geo-fence-operation-area-type',
	'mptbm-coordinates-one' => $square(10, 20),
	'mptbm-coordinates-two' => $square(30, 40),
	'mptbm-geo-fence-direction' => 'geo-fence-two-direction',
));
$cpt = MPTBM_Function::get_cpt();

$free_vehicle = $make($cpt, array('mptbm_price_based' => 'distance'));
$check(MPTBM_App_Quote::area_covers_trip($free_vehicle, $trip('dynamic', $outside, $outside)), 'a vehicle with no operation area covers any trip');
$check(!MPTBM_App_Quote::area_covers_trip($free_vehicle, $trip('fixed_map', $inside_a, $inside_a2)), 'a distance-matched vehicle with no area never matches');

$v_both = $make($cpt, array('mptbm_price_based' => 'distance', 'mptbm_selected_operation_areas' => array($area_both)));
$check(MPTBM_App_Quote::area_covers_trip($v_both, $trip('dynamic', $inside_a, $inside_a2)), 'both points inside the area: covered');
$check(!MPTBM_App_Quote::area_covers_trip($v_both, $trip('dynamic', $inside_a, $outside)), 'drop-off outside the area: not covered');
$check(!MPTBM_App_Quote::area_covers_trip($v_both, $trip('dynamic', $outside, $inside_a)), 'pick-up outside the area: not covered');
$check(MPTBM_App_Quote::area_covers_trip($v_both, $trip('manual', $outside, $outside)), 'manual routes are not area-bound');

$v_one_way = $make($cpt, array('mptbm_price_based' => 'distance', 'mptbm_selected_operation_areas' => array($area_fence)));
$check(MPTBM_App_Quote::area_covers_trip($v_one_way, $trip('dynamic', $inside_a, $inside_b)), 'one-direction fence: area one to area two is covered');
$check(!MPTBM_App_Quote::area_covers_trip($v_one_way, $trip('dynamic', $inside_b, $inside_a)), 'one-direction fence: the reverse is not covered');
$check(MPTBM_App_Quote::area_covers_trip($v_one_way, $trip('dynamic', $inside_a, $inside_a2)), 'one-direction fence: within area one is covered');

$v_two_way = $make($cpt, array('mptbm_price_based' => 'distance', 'mptbm_selected_operation_areas' => array($area_fence_two)));
$check(MPTBM_App_Quote::area_covers_trip($v_two_way, $trip('dynamic', $inside_b, $inside_a)), 'two-direction fence: the reverse is covered');
$check(!MPTBM_App_Quote::area_covers_trip($v_two_way, $trip('dynamic', $outside, $inside_a)), 'two-direction fence: pick-up outside both areas is not covered');

$v_match = $make($cpt, array('mptbm_price_based' => 'distance', 'mptbm_operation_area_type' => 'geo-matched-operation-area-type', 'mptbm_selected_operation_areas' => array($area_both, $area_fence)));
$check(MPTBM_App_Quote::area_covers_trip($v_match, $trip('dynamic', $inside_a, $outside)), 'geo-matched vehicle: pick-up inside is enough');
$check(MPTBM_App_Quote::area_covers_trip($v_match, $trip('dynamic', $outside, $inside_a)), 'geo-matched vehicle: drop-off inside is enough');
$check(!MPTBM_App_Quote::area_covers_trip($v_match, $trip('dynamic', $outside, $outside)), 'geo-matched vehicle: both outside is not covered');

$v_fixed_distance = $make($cpt, array('mptbm_price_based' => 'fixed_distance', 'mptbm_selected_operation_areas' => array($area_both)));
$check(MPTBM_App_Quote::area_covers_trip($v_fixed_distance, $trip('fixed_map', $inside_a, $outside)), 'fixed distance: one point inside matches (partial)');
$check(!MPTBM_App_Quote::area_covers_trip($v_fixed_distance, $trip('fixed_map', $outside, $outside)), 'fixed distance: both outside does not match');
update_post_meta($v_fixed_distance, 'mptbm_operation_area_type', 'fixed-operation-area-type');
$check(!MPTBM_App_Quote::area_covers_trip($v_fixed_distance, $trip('fixed_map', $inside_a, $outside)), 'fixed distance with "both in" area type needs a full match');
$check(MPTBM_App_Quote::area_covers_trip($v_fixed_distance, $trip('fixed_map', $inside_a, $inside_a2)), 'fixed distance with "both in" area type: full match covered');

$check('fixed_map' === MPTBM_App_Quote::search_mode_for_model('fixed_distance'), 'fixed distance vehicles are searched as fixed_map');
$check('dynamic' === MPTBM_App_Quote::search_mode_for_model('inclusive') && 'dynamic' === MPTBM_App_Quote::search_mode_for_model('distance_duration'), 'combined and distance models are searched as dynamic');

foreach ($posts as $id) {
	wp_delete_post($id, true);
}

if ($failures) {
	echo "FAILED (" . count($failures) . " of $checks checks):\n - " . implode("\n - ", $failures) . "\n";
	exit(1);
}
echo "OK ($checks checks)\n";
