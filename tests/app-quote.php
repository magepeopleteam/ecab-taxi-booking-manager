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

// --- Place suggestions (validation only; the lookup itself needs the network) ----------------------
$call_get = static function (string $route, array $query = array()): array {
	$request = new WP_REST_Request('GET', '/ecab-app/v1' . $route);
	$request->set_query_params($query);
	return (array) rest_do_request($request)->get_data();
};
$places = static function (string $route, array $query): WP_REST_Response {
	$request = new WP_REST_Request('GET', '/ecab-app/v1' . $route);
	$request->set_query_params($query);
	return rest_do_request($request);
};
delete_transient('mptbm_app_rl_' . md5('places|' . $_SERVER['REMOTE_ADDR']));
$check(400 === $places('/places/search', array('q' => 'a'))->get_status(), 'a one-letter place search is rejected');
$check(400 === $places('/places/search', array())->get_status(), 'a place search needs a query');
$check(400 === $places('/places/search', array('q' => 'abc', 'lat' => '999', 'lng' => '1'))->get_status(), 'an out-of-range bias latitude is rejected');
$check(400 === $places('/places/reverse', array('lat' => '12'))->get_status(), 'reverse lookup needs both coordinates');
$check(400 === $places('/places/reverse', array('lat' => '12', 'lng' => '500'))->get_status(), 'reverse lookup rejects an impossible longitude');
$labels = new ReflectionMethod('MPTBM_App_Places', 'label');
$labels->setAccessible(true);
$check('Gulshan 1, Gulshan Avenue, Gulshan, Dhaka, Bangladesh' === $labels->invoke(null, array('name' => 'Gulshan 1', 'street' => 'Gulshan Avenue', 'district' => 'Gulshan', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh')), 'a place label drops repeated parts');
$check('' === $labels->invoke(null, array()), 'a place with no usable name has an empty label');
delete_transient('mptbm_app_rl_' . md5('places|' . $_SERVER['REMOTE_ADDR']));

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

// --- A quote is independent of other requests that share the phone's session cookie -------------------------
// The app runs one search per pricing mode at once. If they shared a PHP session, the last to finish would
// overwrite the saved search of the others and a later booking would be refused ("the pricing mode changed").
// Simulate that: while a 'dynamic' quote runs on the ambient session, another request writes a different
// mode's search into the same session. The stored quote must still describe the dynamic search.
$respond = static function ($body) {
	return array('headers' => array(), 'body' => wp_json_encode($body), 'response' => array('code' => 200, 'message' => 'OK'), 'cookies' => array(), 'filename' => null);
};
$stub = static function ($pre, $args, $url) use ($respond) {
	parse_str((string) (wp_parse_url($url)['query'] ?? ''), $query);
	$known = array('test pickup' => array(23.8433, 90.3978), 'test dropoff' => array(23.7806, 90.4167));
	if (false !== strpos($url, 'photon.komoot.io')) {
		$hit = $known[strtolower($query['q'] ?? '')] ?? null;
		return $respond(array('type' => 'FeatureCollection', 'features' => $hit ? array(array('geometry' => array('coordinates' => array($hit[1], $hit[0])), 'properties' => array('name' => $query['q']))) : array()));
	}
	if (false !== strpos($url, 'router.project-osrm.org')) {
		return $respond(array('code' => 'Ok', 'routes' => array(array('distance' => 9800, 'duration' => 900))));
	}
	return $pre;
};
add_filter('pre_http_request', $stub, 10, 3);
$saved_map_settings = get_option('mptbm_map_api_settings', null);
update_option('mptbm_map_api_settings', array_merge(is_array($saved_map_settings) ? $saved_map_settings : array(), array('display_map' => 'openstreetmap', 'gmap_api_key' => '', 'gmap_server_api_key' => '', 'fare_distance_source' => 'server', 'mptbm_address_verification' => 'strict')));
ini_set('session.use_cookies', '0');
ini_set('session.use_only_cookies', '0');
$ambient = 'apptestambientsession';
session_id($ambient);
$rival = static function ($items) use ($ambient) {
	$mine = session_id();
	if (PHP_SESSION_ACTIVE === session_status()) {
		session_write_close();
	}
	session_id($ambient); // the rival request, carrying the phone's session cookie
	session_start();
	$_SESSION['mptbm_search_context'] = array('price_based' => 'fixed_zone', 'created_at' => time());
	session_write_close();
	session_id($mine);
	return $items;
};
add_filter('mptbm_search_result_items', $rival, 5);
delete_transient('mptbm_app_rl_' . md5('quote|' . $_SERVER['REMOTE_ADDR']));
$raced = $quote(array_merge($base, array('date' => $tomorrow, 'start' => array('name' => 'Test Pickup', 'lat' => 23.8433, 'lng' => 90.3978), 'end' => array('name' => 'Test Dropoff', 'lat' => 23.7806, 'lng' => 90.4167))));
remove_filter('mptbm_search_result_items', $rival, 5);
$stored = get_transient('mptbm_app_quote_' . ($raced->get_data()['quote_id'] ?? 'none'));
$check(200 === $raced->get_status() && is_array($stored), 'the raced quote was made and stored: ' . wp_json_encode($raced->get_data()));
$check('dynamic' === ($stored['context']['price_based'] ?? ''), 'a quote keeps its own search when another request rewrites the shared session (got ' . ($stored['context']['price_based'] ?? 'nothing') . ')');
remove_filter('pre_http_request', $stub, 10);
null === $saved_map_settings ? delete_option('mptbm_map_api_settings') : update_option('mptbm_map_api_settings', $saved_map_settings);
$check(PHP_SESSION_ACTIVE !== session_status(), 'a quote leaves no session open');
session_id(wp_generate_password(32, false, false)); // back to a private id for the rest of this file

// --- Fixed routes and manual (listed) locations: no map points, the shop's own price table ----------------
$route_id = $make('mptbm_routes', array());
wp_update_post(array('ID' => $route_id, 'post_title' => 'Apptest City Tour'));
$route_vehicle = $make($cpt, array('mptbm_price_based' => 'fixed_route', 'mptbm_assigned_routes' => array(array('route_id' => $route_id, 'price' => 120))));
$manual_vehicle = $make($cpt, array('mptbm_price_based' => 'manual', 'mptbm_manual_price_info' => array(array('start_location' => 'Apptest A', 'end_location' => 'Apptest B', 'price' => 45))));
$list_quote = static function (array $body) use ($quote, $tomorrow): WP_REST_Response {
	delete_transient('mptbm_app_rl_' . md5('quote|' . $_SERVER['REMOTE_ADDR']));
	return $quote(array_merge(array('date' => $tomorrow, 'time' => '10:30', 'passengers' => 1), $body));
};
$by_id = static function (WP_REST_Response $r): array {
	return array_column((array) ($r->get_data()['vehicles'] ?? array()), null, 'id');
};
$route = $list_quote(array('price_based' => 'fixed_route', 'start' => array('name' => 'Apptest City Tour')));
$check(200 === $route->get_status(), 'a fixed route needs only its name: ' . wp_json_encode($route->get_data()));
$check(isset($by_id($route)[$route_vehicle]) && abs($by_id($route)[$route_vehicle]['price']['total'] - 120.0) < 0.01, 'the route is quoted at the vehicle\'s own price for it');
$check(!isset($by_id($list_quote(array('price_based' => 'fixed_route', 'start' => array('name' => 'No Such Route'))))[$route_vehicle]), 'an unknown route matches no vehicle');
$check(400 === $list_quote(array('price_based' => 'fixed_route', 'start' => array('name' => '  ')))->get_status(), 'a route without a name is refused');
$manual = $list_quote(array('price_based' => 'manual', 'start' => array('name' => 'Apptest A'), 'end' => array('name' => 'Apptest B')));
$check(isset($by_id($manual)[$manual_vehicle]) && abs($by_id($manual)[$manual_vehicle]['price']['total'] - 45.0) < 0.01, 'a listed pair is quoted at the shop\'s price for it');
$check(!isset($by_id($list_quote(array('price_based' => 'manual', 'start' => array('name' => 'Apptest A'), 'end' => array('name' => 'Apptest C'))))[$manual_vehicle]), 'a pair the shop has no price for matches no vehicle');
$check(400 === $list_quote(array('price_based' => 'manual', 'start' => array('name' => 'Apptest A')))->get_status(), 'a manual trip needs both ends');
$routes = $call_get('/locations', array('price_based' => 'fixed_route'));
$check(in_array('Apptest City Tour', array_column($routes, 'value'), true), 'the routes are listed for the app to offer');
$starts = $call_get('/locations', array('price_based' => 'manual'));
$check(in_array('Apptest A', array_column($starts, 'value'), true), 'manual start locations are listed');
$ends = $call_get('/locations', array('price_based' => 'manual', 'start' => 'Apptest A'));
$check(array('Apptest B') === array_column($ends, 'value'), 'only the destinations priced from that start are listed');

// --- Hourly trips: hours x the hourly rate, the drop-off is optional, and the website's capacity filters ----------
$hour_vehicle = $make($cpt, array('mptbm_price_based' => 'fixed_hourly', 'mptbm_hour_price' => 10, 'mptbm_maximum_passenger' => 3));
$hourly = $list_quote(array('price_based' => 'fixed_hourly', 'start' => array('name' => 'Anywhere', 'lat' => 23.8, 'lng' => 90.4), 'fixed_time' => 5, 'passengers' => 9));
$check(200 === $hourly->get_status(), 'an hourly trip needs no drop-off: ' . wp_json_encode($hourly->get_data()));
$check(isset($by_id($hourly)[$hour_vehicle]) && abs($by_id($hourly)[$hour_vehicle]['price']['total'] - 50.0) < 0.01, 'five hours at ten an hour is fifty');
$check(isset($by_id($hourly)[$hour_vehicle]), 'a party larger than the vehicle holds is not filtered out unless the website\'s capacity filter is used');
$check(400 === $list_quote(array('price_based' => 'fixed_hourly', 'start' => array('name' => 'Anywhere', 'lat' => 23.8, 'lng' => 90.4)))->get_status(), 'hourly trips still need the hours');
$filtered = $list_quote(array('price_based' => 'fixed_hourly', 'start' => array('name' => 'Anywhere', 'lat' => 23.8, 'lng' => 90.4), 'fixed_time' => 5, 'max_passenger' => 5));
$check(!isset($by_id($filtered)[$hour_vehicle]), 'the capacity filter hides a vehicle that cannot take that many passengers, as on the website');
$zone_start = $list_quote(array('price_based' => 'fixed_zone', 'start' => array('name' => 'term_999999'), 'end' => array('name' => 'Test Dropoff', 'lat' => 23.7806, 'lng' => 90.4167)));
$check(400 !== $zone_start->get_status() || false === strpos(wp_json_encode($zone_start->get_data()), 'pick-up location from the suggestions'), 'a fixed-zone pick-up is a list entry with no coordinates');
$check(400 === $list_quote(array('price_based' => 'dynamic', 'start' => array('name' => 'No coordinates'), 'end' => array('name' => 'Test Dropoff', 'lat' => 23.7806, 'lng' => 90.4167)))->get_status(), 'a distance trip still needs coordinates for both places');

// --- The geo-fence surcharge is priced once --------------------------------------------------------------
// get_price() already adds the fence fee, so the quote must not add the session's copy of it again.
$fence_vehicle = $make($cpt, array('mptbm_price_based' => 'distance'));
$prepare = new ReflectionMethod('MPTBM_App_Quote', 'prepare_vehicle');
$prepare->setAccessible(true);
$_SESSION['geo_fence_post_' . $fence_vehicle] = array(150, 'geo-fence-fixed-price');
$priced = $prepare->invoke(null, array('post_id' => $fence_vehicle, 'rating' => 0, 'fare' => array('transport' => 100.0, 'base_location' => 5.0, 'stops' => 2.0, 'display_type' => 'normal', 'unavailable' => false)), array('price_based' => 'dynamic', 'start' => array('lat' => 0, 'lng' => 0), 'end' => array('lat' => 0, 'lng' => 0), 'passengers' => 1, 'bags' => 0));
unset($_SESSION['geo_fence_post_' . $fence_vehicle]);
$check(is_array($priced) && abs($priced['price']['total'] - 107.0) < 0.001, 'the quoted total is transport + base + stops, with no second geo-fence fee');
$check(!isset($priced['price']['area_fee']), 'the quote no longer reports a separate area fee');

foreach ($posts as $id) {
	wp_delete_post($id, true);
}

if ($failures) {
	echo "FAILED (" . count($failures) . " of $checks checks):\n - " . implode("\n - ", $failures) . "\n";
	exit(1);
}
echo "OK ($checks checks)\n";
