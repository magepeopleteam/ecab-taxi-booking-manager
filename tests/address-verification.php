<?php
/**
 * Pickup / drop-off coordinates must match the place names the booking shows.
 *
 * The fare is routed between posted coordinates while the order shows the posted names, so
 * "Downtown -> Airport" could be priced on two points 120 m apart (Patchstack, <= 2.1.6).
 * Every outbound request is stubbed (Photon, Google Geocoding, OSRM): no network is used.
 *
 * Run with: wp --path=/var/www/html/magepeople eval-file wp-content/plugins/ecab-taxi-booking-manager/tests/address-verification.php
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

// --- Stubbed map services -------------------------------------------------------------------
$downtown = array('latitude' => 40.7128, 'longitude' => -74.0060);
$poc_end  = array('latitude' => 40.7137, 'longitude' => -74.0050); // ~120 m from $downtown
$jfk      = array('latitude' => 40.6413, 'longitude' => -73.7781);

// label => list of [lat, lng, extent|null]; extent is Photon's [west, north, east, south].
$photon_places = array(
	'downtown'                => array(array(40.7130, -74.0062, null)),
	'airport'                 => array(array(40.7769, -73.8740, null)), // LaGuardia, ~13 km from downtown
	'jfk airport'             => array(array(40.6430, -73.7900, array(-73.8300, 40.6650, -73.7400, 40.6200))),
	'big park'                => array(array(40.7900, -73.9600, array(-73.9820, 40.8000, -73.9490, 40.7640))),
	'new york state'          => array(array(43.0000, -75.0000, array(-79.7600, 45.0100, -71.8500, 40.4900))),
	'stop one'                => array(array(40.7300, -73.9900, null)),
);
$http = array('photon' => 0, 'google' => 0, 'osrm' => 0);
$photon_down = false;
$google_status = 'OK';
$google_places = array(
	'jfk airport' => array(40.6413, -73.7781, array(40.6200, -73.8300, 40.6650, -73.7400)),
);
$osrm_down = false;
$respond = static function ($body) {
	return array('headers' => array(), 'body' => wp_json_encode($body), 'response' => array('code' => 200, 'message' => 'OK'), 'cookies' => array(), 'filename' => null);
};
add_filter('pre_http_request', static function ($pre, $args, $url) use (&$http, &$photon_down, &$google_status, &$osrm_down, $photon_places, $google_places, $respond) {
	$parts = wp_parse_url($url);
	parse_str($parts['query'] ?? '', $query);
	if (strpos($url, 'photon.komoot.io') !== false) {
		++$http['photon'];
		if ($photon_down) {
			return new WP_Error('http_request_failed', 'Photon down');
		}
		$features = array();
		foreach ($photon_places[strtolower($query['q'] ?? '')] ?? array() as $place) {
			$properties = array('name' => $query['q']);
			if ($place[2]) {
				$properties['extent'] = $place[2];
			}
			$features[] = array('geometry' => array('coordinates' => array($place[1], $place[0])), 'properties' => $properties);
		}
		return $respond(array('type' => 'FeatureCollection', 'features' => $features));
	}
	if (strpos($url, 'maps.googleapis.com/maps/api/geocode') !== false) {
		++$http['google'];
		if ($google_status !== 'OK') {
			return $respond(array('status' => $google_status, 'error_message' => 'stubbed ' . $google_status, 'results' => array()));
		}
		$place = $google_places[strtolower($query['address'] ?? '')] ?? null;
		if (!$place) {
			return $respond(array('status' => 'ZERO_RESULTS', 'results' => array()));
		}
		return $respond(array('status' => 'OK', 'results' => array(array('geometry' => array(
			'location' => array('lat' => $place[0], 'lng' => $place[1]),
			'viewport' => array('southwest' => array('lat' => $place[2][0], 'lng' => $place[2][1]), 'northeast' => array('lat' => $place[2][2], 'lng' => $place[2][3])),
		)))));
	}
	if (strpos($url, 'router.project-osrm.org') !== false) {
		++$http['osrm'];
		if ($osrm_down) {
			return new WP_Error('http_request_failed', 'OSRM down');
		}
		preg_match('#/driving/([^?]+)#', $url, $m);
		$points = array_map(static function ($pair) {
			list($lng, $lat) = array_map('floatval', explode(',', $pair));
			return array('lat' => $lat, 'lng' => $lng);
		}, explode(';', $m[1]));
		$meters = max(950, MPTBM_Function::great_circle_meters($points) * 1.3);
		return $respond(array('code' => 'Ok', 'routes' => array(array('distance' => $meters, 'duration' => $meters / 10))));
	}
	return $pre;
}, 10, 3);

$saved_map_settings = get_option('mptbm_map_api_settings', null);
$with_map_settings = static function (array $values) use ($saved_map_settings) {
	update_option('mptbm_map_api_settings', array_merge(is_array($saved_map_settings) ? $saved_map_settings : array(), $values));
};
$clear_caches = static function () {
	global $wpdb;
	$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_mptbm\\_addr\\_%' OR option_name LIKE '\\_transient\\_timeout\\_mptbm\\_addr\\_%' OR option_name LIKE '\\_transient%mptbm\\_route\\_rate\\_%'");
	delete_transient('mptbm_geocode_google_denied');
	delete_transient('mptbm_geocode_failure');
	wp_cache_flush();
};

ini_set('session.use_cookies', '0');
ini_set('session.use_only_cookies', '0');
$session_counter = 0;
$fresh_session = static function () use (&$session_counter) {
	if (session_status() === PHP_SESSION_ACTIVE) {
		session_write_close();
	}
	session_id('mptbmaddrcheck' . (++$session_counter));
	session_start();
	$_SESSION = array();
	session_write_close();
	session_id('mptbmaddrcheck' . $session_counter);
};

try {
	// OpenStreetMap mode, no Google key: Photon is the only geocoder.
	$with_map_settings(array('display_map' => 'openstreetmap', 'gmap_api_key' => '', 'gmap_server_api_key' => '', 'fare_distance_source' => 'server', 'mptbm_address_verification' => 'strict'));
	$clear_caches();

	// 1. The verifier.
	$r = MPTBM_Function::verify_place_coordinates('JFK Airport', $jfk);
	$check($r['verified'] && $r['reason'] === 'match' && $r['provider'] === 'photon', 'A name whose geocode is at the posted point should verify.');
	$r = MPTBM_Function::verify_place_coordinates('Airport', $poc_end);
	$check(!$r['verified'] && $r['reason'] === 'no_match', 'PoC drop-off ("Airport" posted 120 m from downtown) must not verify.');
	$r = MPTBM_Function::verify_place_coordinates('JFK Airport', $downtown);
	$check(!$r['verified'] && $r['reason'] === 'no_match', 'A real name posted with coordinates 20 km away must not verify.');
	$r = MPTBM_Function::verify_place_coordinates('Big Park', array('latitude' => 40.7700, 'longitude' => -73.9750));
	$check($r['verified'], 'A point inside the geocoded area (2 km from its centre point) should verify.');
	$r = MPTBM_Function::verify_place_coordinates('New York State', $downtown);
	$check(!$r['verified'], 'A state-sized area must not vouch for any point inside it.');
	$r = MPTBM_Function::verify_place_coordinates('Downtown', array('latitude' => 40.7128 + 0.012, 'longitude' => -74.0060));
	$check($r['verified'], 'A point 1.3 km from the geocoded point is within the default tolerance.');
	$r = MPTBM_Function::verify_place_coordinates('Downtown', array('latitude' => 40.7128 + 0.018, 'longitude' => -74.0060));
	$check(!$r['verified'], 'A point 2 km from the geocoded point is outside the default tolerance.');
	$r = MPTBM_Function::verify_place_coordinates('', $downtown);
	$check(!$r['verified'] && $r['reason'] === 'no_label', 'An empty name must not verify.');
	$r = MPTBM_Function::verify_place_coordinates('Downtown', array());
	$check(!$r['verified'] && $r['reason'] === 'no_coords', 'Missing coordinates must not verify.');
	$r = MPTBM_Function::verify_place_coordinates('Downtown', '{"latitude":95,"longitude":0}');
	$check(!$r['verified'] && $r['reason'] === 'no_coords', 'Out-of-range coordinates must not verify.');

	// Caching: a match is reused without asking again; an outage is not cached.
	$before = $http['photon'];
	$r = MPTBM_Function::verify_place_coordinates('JFK Airport', $jfk);
	$check($r['verified'] && $http['photon'] === $before, 'A verified pair should come from the cache.');
	$photon_down = true;
	$r = MPTBM_Function::verify_place_coordinates('Stop One', array('latitude' => 40.7300, 'longitude' => -73.9900));
	$check(!$r['verified'] && $r['reason'] === 'unavailable', 'No geocoder reachable should be reported as unavailable.');
	$failure = get_transient('mptbm_geocode_failure');
	$check(($failure['type'] ?? '') === 'unreachable', 'An outage should be recorded for the admin notice.');
	$photon_down = false;
	$r = MPTBM_Function::verify_place_coordinates('Stop One', array('latitude' => 40.7300, 'longitude' => -73.9900));
	$check($r['verified'], 'An outage must not be cached as a failure.');
	$check(get_transient('mptbm_geocode_failure') === false, 'Photon answering again should clear the outage notice.');

	// What the server's own Photon proxy fetched for the browser is reused without a request,
	// but only when it actually places the name at the posted point.
	$proxy_key = MPTBM_Function::osm_search_cache_key(array('q' => 'Corner Cafe', 'limit' => 5, 'lang' => 'en'), 'no', 'BD');
	set_transient($proxy_key, array(array('display_name' => 'Corner Cafe, New York', 'lat' => 40.7200, 'lon' => -74.0000)), 5 * MINUTE_IN_SECONDS);
	$before = $http['photon'];
	$r = MPTBM_Function::verify_place_coordinates('Corner Cafe', array('latitude' => 40.7201, 'longitude' => -74.0001));
	$check($r['verified'] && $r['provider'] === 'photon-cache' && $http['photon'] === $before, 'A cached proxy answer at the posted point should verify without asking Photon.');
	$r = MPTBM_Function::verify_place_coordinates('Corner Cafe', $jfk);
	$check(!$r['verified'] && $http['photon'] === $before + 1, 'A cached proxy answer elsewhere must not verify, and Photon is asked instead.');
	delete_transient($proxy_key);

	// The filter can supply a site's own verdict.
	$override = static function ($result, $label) {
		return $label === 'Depot Gate 3' ? array('verified' => true, 'reason' => 'match', 'provider' => 'site') : $result;
	};
	add_filter('mptbm_verify_place_coordinates', $override, 10, 2);
	$r = MPTBM_Function::verify_place_coordinates('Depot Gate 3', $downtown);
	$check($r['verified'] && $r['provider'] === 'site', 'mptbm_verify_place_coordinates should be able to verify a place.');
	remove_filter('mptbm_verify_place_coordinates', $override, 10);

	// 2. Google map mode: Google first, Photon as the fallback when Google refuses.
	$with_map_settings(array('display_map' => 'enable', 'gmap_api_key' => 'browser-key', 'gmap_server_api_key' => ''));
	$clear_caches();
	$r = MPTBM_Function::verify_place_coordinates('JFK Airport', $jfk);
	$check($r['verified'] && $r['provider'] === 'google', 'Google mode should verify with Google Geocoding first.');
	$clear_caches();
	$google_status = 'REQUEST_DENIED';
	$r = MPTBM_Function::verify_place_coordinates('JFK Airport', $jfk);
	$check($r['verified'] && $r['provider'] === 'photon', 'A refused Google key should fall back to Photon.');
	$failure = get_transient('mptbm_geocode_failure');
	$check(($failure['type'] ?? '') === 'google_denied' && strpos($failure['reason'], 'REQUEST_DENIED') === 0, 'A refused Google key should be recorded for the admin notice.');
	$google_calls = $http['google'];
	MPTBM_Function::verify_place_coordinates('Downtown', $downtown);
	$check($http['google'] === $google_calls, 'Google should not be asked again for an hour after refusing.');
	$google_status = 'OK';
	// OpenStreetMap mode never spends a leftover Google key, even when Photon finds nothing.
	$with_map_settings(array('display_map' => 'openstreetmap', 'gmap_api_key' => 'old-key', 'gmap_server_api_key' => 'old-server-key'));
	$clear_caches();
	$google_calls = $http['google'];
	MPTBM_Function::verify_place_coordinates('Airport', $poc_end);
	MPTBM_Function::verify_place_coordinates('Unknown Place', $downtown);
	$check($http['google'] === $google_calls, 'OpenStreetMap mode must not call Google Geocoding.');
	$with_map_settings(array('display_map' => 'openstreetmap', 'gmap_api_key' => '', 'gmap_server_api_key' => ''));
	$clear_caches();

	// 3. Which endpoints are bound per mode.
	$check(MPTBM_Function::coordinate_priced_endpoints('dynamic') === array('start', 'end', 'stops'), 'Distance pricing binds pickup, drop-off and stops.');
	$check(MPTBM_Function::coordinate_priced_endpoints('fixed_zone') === array('end'), 'fixed_zone binds the free-text drop-off.');
	$check(MPTBM_Function::coordinate_priced_endpoints('fixed_zone_dropoff') === array('start'), 'fixed_zone_dropoff binds the free-text pickup.');
	foreach (array('manual', 'fixed_hourly', 'fixed_daily', 'fixed_route', '') as $mode) {
		$check(MPTBM_Function::coordinate_priced_endpoints($mode) === array(), "Mode '$mode' prices without coordinates and binds nothing.");
	}

	// 4. The checkout gate (shared by WooCommerce, Offline and PRO native checkout).
	$gate = static function (array $extra, $mode = 'dynamic') use ($fresh_session) {
		$fresh_session();
		session_start();
		MPTBM_Function::set_search_context(array_merge(array(
			'distance' => 30977, 'duration' => 2400, 'distance_verified' => true,
			'start_place' => 'Downtown', 'end_place' => 'Airport', 'price_based' => $mode, 'fixed_time' => 2,
		), $extra));
		session_start();
		return MPTBM_Function::get_checkout_search_context('Downtown', 'Airport', $mode);
	};
	$is_code = static function ($result, $code) {
		return is_wp_error($result) && $result->get_error_code() === $code;
	};
	$check($is_code($gate(array()), 'mptbm_quote_location'), 'A distance quote without the address check (e.g. saved before the update) must be refused.');
	$check(is_array($gate(array('location_checked' => true, 'location_verified' => true))), 'A verified distance quote should be accepted.');
	$check($is_code($gate(array('location_checked' => true, 'location_verified' => false)), 'mptbm_quote_location'), 'Strict mode must refuse an unverified quote at checkout.');
	$check($is_code($gate(array(), 'fixed_zone'), 'mptbm_quote_location'), 'A fixed_zone quote without the address check must be refused.');
	$check(is_array($gate(array(), 'fixed_hourly')), 'Hourly quotes are not affected by the address check.');
	$with_map_settings(array('mptbm_address_verification' => 'flag'));
	$check(is_array($gate(array('location_checked' => true, 'location_verified' => false))), 'Flag mode should accept an unverified, checked quote.');
	$check($is_code($gate(array()), 'mptbm_quote_location'), 'Flag mode must still refuse a quote that was never checked.');
	$with_map_settings(array('mptbm_address_verification' => 'strict'));

	// 5. The record saved on orders/bookings.
	$record = MPTBM_Function::route_record(array(
		'start_coords' => $downtown, 'end_coords' => $jfk,
		'extra_stop_coords' => array(array('latitude' => 40.73, 'longitude' => -73.99), array()),
		'location_checked' => true, 'location_verified' => false,
	));
	$check($record['mptbm_start_coords'] === '40.712800,-74.006000' && $record['mptbm_end_coords'] === '40.641300,-73.778100', 'Route record should store both endpoints as lat,lng.');
	$check($record['mptbm_extra_stop_coords'] === '40.730000,-73.990000' && $record['mptbm_address_check'] === 'unverified', 'Route record should store stops and the check result.');
	$check(MPTBM_Function::route_record(array())['mptbm_address_check'] === '', 'An unchecked quote records no check result.');
	$url = MPTBM_Function::route_record_map_url($record);
	$check(strpos($url, 'origin=40.712800%2C-74.006000') !== false && strpos($url, 'destination=40.641300%2C-73.778100') !== false && strpos($url, 'waypoints=40.730000%2C-73.990000') !== false, 'Map link should route through the stored points.');
	$check(MPTBM_Function::route_record_map_url(array('mptbm_start_coords' => 'javascript:alert(1)', 'mptbm_end_coords' => '1.0,2.0')) === '', 'Map link must reject anything that is not a coordinate pair.');

	// 6. The search handler itself.
	$search = new MPTBM_Transport_Search();
	add_filter('wp_doing_ajax', '__return_true');
	$die_handler = static function () {
		return static function () {
			throw new RuntimeException('wp_die');
		};
	};
	add_filter('wp_die_ajax_handler', $die_handler);
	$run_search = static function (array $post) use ($search, $fresh_session, $clear_caches) {
		$fresh_session();
		$clear_caches();
		$_POST = array_merge(array(
			'action' => 'get_mptbm_map_search_result', 'nonce' => wp_create_nonce('mptbm_transport_search'),
			'price_based' => 'dynamic', 'start_date' => gmdate('Y-m-d', time() + 3 * DAY_IN_SECONDS), 'start_time' => '10.30',
		), $post);
		$_REQUEST = $_POST;
		ob_start();
		try {
			$search->get_mptbm_map_search_result();
		} catch (RuntimeException $e) {
			// wp_send_json_* ends the request through wp_die().
		}
		$json = json_decode((string) ob_get_clean(), true);
		session_start();
		$context = $_SESSION['mptbm_search_context'] ?? array();
		session_write_close();
		return array($json, $context);
	};
	$coords_json = static function (array $c) {
		return wp_json_encode($c);
	};

	// The PoC request, verbatim apart from the nonce.
	list($json, $context) = $run_search(array(
		'start_place' => 'Downtown', 'end_place' => 'Airport',
		'start_place_coordinates' => '{"latitude":40.7128,"longitude":-74.0060}',
		'end_place_coordinates' => '{"latitude":40.7137,"longitude":-74.0050}',
	));
	$check(is_array($json) && $json['success'] === false && strpos($json['data']['message'], 'drop-off') !== false, 'The PoC search must be refused with a drop-off message.');
	$check(empty($context), 'A refused search must not leave a quote in the session.');
	$check($http['osrm'] === 0, 'A refused search should not spend a routing request.');

	// The attack with a precise name, and labels swapped onto each other's coordinates.
	list($json) = $run_search(array(
		'start_place' => 'Downtown', 'end_place' => 'JFK Airport',
		'start_place_coordinates' => $coords_json($downtown), 'end_place_coordinates' => $coords_json($poc_end),
	));
	$check(is_array($json) && $json['success'] === false, 'A precise drop-off name with nearby coordinates must be refused.');
	list($json) = $run_search(array(
		'start_place' => 'JFK Airport', 'end_place' => 'Downtown',
		'start_place_coordinates' => $coords_json($downtown), 'end_place_coordinates' => $coords_json($downtown),
	));
	$check(is_array($json) && $json['success'] === false && strpos($json['data']['message'], 'pickup') !== false, 'A forged pickup must be refused with a pickup message.');

	// Extra stops: a named stop with no or misaligned coordinates is refused.
	list($json) = $run_search(array(
		'start_place' => 'Downtown', 'end_place' => 'JFK Airport',
		'start_place_coordinates' => $coords_json($downtown), 'end_place_coordinates' => $coords_json($jfk),
		'mptbm_extra_stop_place' => array('Stop One', 'JFK Airport'), 'mptbm_extra_stop_place_coordinates' => array('40.73,-73.99'),
	));
	$check(is_array($json) && $json['success'] === false && strpos($json['data']['message'], 'extra stop') !== false, 'Stops whose names and coordinates do not line up must be refused.');
	list($json) = $run_search(array(
		'start_place' => 'Downtown', 'end_place' => 'JFK Airport',
		'start_place_coordinates' => $coords_json($downtown), 'end_place_coordinates' => $coords_json($jfk),
		'mptbm_extra_stop_place' => array('Stop One'), 'mptbm_extra_stop_place_coordinates' => array('40.7135,-74.0055'),
	));
	$check(is_array($json) && $json['success'] === false, 'A named stop with coordinates elsewhere must be refused.');

	// fixed_zone binds its free-text drop-off.
	list($json) = $run_search(array(
		'price_based' => 'fixed_zone', 'start_place' => 'term_1', 'end_place' => 'JFK Airport',
		'start_place_coordinates' => '', 'end_place_coordinates' => $coords_json($downtown),
	));
	$check(is_array($json) && $json['success'] === false, 'A fixed_zone drop-off named far from its coordinates must be refused.');

	// A geocoder outage refuses the search (strict) with a "try again" message, not "re-pick".
	$photon_down = true;
	list($json) = $run_search(array(
		'start_place' => 'Downtown', 'end_place' => 'JFK Airport',
		'start_place_coordinates' => $coords_json($downtown), 'end_place_coordinates' => $coords_json($jfk),
	));
	$check(is_array($json) && $json['success'] === false && strpos($json['data']['message'], 'try again') !== false, 'A geocoder outage should refuse the search with a try-again message.');
	$photon_down = false;

	// Distance pricing never falls back to the browser's distance when routing fails.
	$osrm_down = true;
	list($json) = $run_search(array(
		'start_place' => 'Downtown', 'end_place' => 'JFK Airport',
		'start_place_coordinates' => $coords_json($downtown), 'end_place_coordinates' => $coords_json($jfk),
		'mptbm_distance' => '1', 'mptbm_duration' => '1',
	));
	$check(is_array($json) && $json['success'] === false && strpos($json['data']['message'], 'route') !== false, 'A failed route lookup must end a distance search instead of using the posted distance.');
	$osrm_down = false;

	// verify_trip_places() directly, for the outcomes that go on to render the results.
	$verify = new ReflectionMethod('MPTBM_Transport_Search', 'verify_trip_places');
	$verify->setAccessible(true);
	$_POST = array('start_place' => 'Downtown', 'end_place' => 'JFK Airport', 'mptbm_extra_stop_place' => array('Stop One'), 'mptbm_extra_stop_place_coordinates' => array('40.73,-73.99'));
	$clear_caches();
	$result = $verify->invoke($search, 'dynamic', $downtown, $jfk);
	$check($result['checked'] && $result['verified'] && count($result['stop_coords']) === 1, 'A genuine trip with a genuine stop should verify.');
	$result = $verify->invoke($search, 'fixed_hourly', $downtown, $poc_end);
	$check(!$result['checked'], 'Hourly searches are not address-checked.');
	$with_map_settings(array('mptbm_address_verification' => 'flag'));
	$_POST = array('start_place' => 'Downtown', 'end_place' => 'Airport');
	$result = $verify->invoke($search, 'dynamic', $downtown, $poc_end);
	$check($result['checked'] && !$result['verified'], 'Flag mode should let the PoC through as checked-but-unverified.');
	$with_map_settings(array('mptbm_address_verification' => 'strict'));

	remove_filter('wp_doing_ajax', '__return_true');
	remove_filter('wp_die_ajax_handler', $die_handler);
} finally {
	if (is_array($saved_map_settings)) {
		update_option('mptbm_map_api_settings', $saved_map_settings);
	} else {
		delete_option('mptbm_map_api_settings');
	}
	$clear_caches();
	$_POST = $_REQUEST = array();
}

if ($failures) {
	WP_CLI::error(sprintf("%d of %d checks failed:\n- %s", count($failures), $checks, implode("\n- ", $failures)));
}
WP_CLI::success(sprintf('All %d address-verification checks passed.', $checks));
