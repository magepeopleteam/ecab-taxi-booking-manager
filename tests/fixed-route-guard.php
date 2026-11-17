<?php
/**
 * Fixed-route guard (Patchstack report 39801).
 *
 * A fixed-route vehicle is offered and priced only for a route it is assigned, picked by the
 * route's exact name. A name in different capitalisation, an unknown name or an unpublished
 * route used to pass the search gate (location_exit() had no fixed_route branch) and be priced
 * at 0 plus the base fare, so a guest could book the trip for the base fare alone. Now both
 * gates refuse it, every name the dropdown offers still books at its price, and location_exit()
 * refuses pricing models it does not know. The posts are throwaway and deleted at the end.
 *
 * Run with: wp --path=/var/www/html/magepeople eval-file wp-content/plugins/ecab-taxi-booking-manager/tests/fixed-route-guard.php
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

$made = array();
$post = static function (string $type, string $title, array $meta = array(), string $status = 'publish') use (&$made): int {
	$id = (int) wp_insert_post(array('post_type' => $type, 'post_status' => $status, 'post_title' => $title));
	foreach ($meta as $key => $value) {
		update_post_meta($id, $key, $value);
	}
	$made[] = $id;
	return $id;
};
$vehicle = static function (string $title, array $meta) use ($post): int {
	return $post('mptbm_rent', $title, $meta + array('mptbm_price_display_type' => 'normal'));
};
// Run one call under a pricing mode, the way the search binds it.
$in_mode = static function (string $mode, callable $call) {
	$bind = static function () use ($mode) {
		return $mode;
	};
	add_filter('mptbm_original_price_based', $bind, 99);
	try {
		return $call();
	} finally {
		remove_filter('mptbm_original_price_based', $bind, 99);
	}
};
$price = static function (int $id, string $place) use ($in_mode) {
	return $in_mode('fixed_route', static function () use ($id, $place) {
		return MPTBM_Function::get_price($id, 1000, 3600, $place, '');
	});
};
$listed = static function (int $id, string $place, string $mode = 'fixed_route', string $end = '') use ($in_mode): bool {
	return (bool) $in_mode($mode, static function () use ($id, $place, $end) {
		return MPTBM_Function::location_exit($id, $place, $end);
	});
};
$near = static function ($value, float $expected): bool {
	return is_numeric($value) && abs((float) $value - $expected) < 0.001;
};

try {
	$real     = $post('mptbm_routes', 'Real Route', array('mptbm_route_waypoints' => 'A,B'));
	$dashed   = $post('mptbm_routes', 'Airport - City', array('mptbm_route_waypoints' => 'C,D'));
	$spaced   = $post('mptbm_routes', 'Old  Town Loop', array('mptbm_route_waypoints' => 'E,F'));
	$draft    = $post('mptbm_routes', 'Draft Route', array('mptbm_route_waypoints' => 'G,H'), 'draft');
	$elsewhere = $post('mptbm_routes', 'Other Vehicle Route', array('mptbm_route_waypoints' => 'I,J'));

	// The lab vehicle from the report: route 80, base fare on with an initial price of 5.
	$cab = $vehicle('Guard Route Cab', array(
		'mptbm_price_based'                    => 'fixed_route',
		'mptbm_assigned_routes'                => array(
			array('route_id' => $real, 'price' => 80),
			array('route_id' => $dashed, 'price' => 60),
			array('route_id' => $spaced, 'price' => 40),
			array('route_id' => $draft, 'price' => 50),
		),
		'mptbm_display_taxi_base_fare_pricing' => 'on',
		'mptbm_initial_price'                  => 5,
	));
	// Same, but the base fare is a minimum price instead of an initial price.
	$minimum_cab = $vehicle('Guard Minimum Route Cab', array(
		'mptbm_price_based'                    => 'fixed_route',
		'mptbm_assigned_routes'                => array(array('route_id' => $real, 'price' => 80)),
		'mptbm_display_taxi_base_fare_pricing' => 'on',
		'mptbm_min_price'                      => 20,
	));
	$vehicle('Guard Other Cab', array(
		'mptbm_price_based'     => 'fixed_route',
		'mptbm_assigned_routes' => array(array('route_id' => $elsewhere, 'price' => 30)),
	));

	/* 1. The report's case: other capitalisations and unknown names are refused at both gates. */
	foreach (array('real route', 'REAL ROUTE', 'Real route', 'No Such Route', 'Other Vehicle Route', 'Draft Route', '') as $name) {
		$check(!$listed($cab, $name), "The search listed the route cab for \"{$name}\".");
		$check(false === $price($cab, $name), "\"{$name}\" was priced at " . wp_json_encode($price($cab, $name)) . ' instead of being refused.');
		$check(false === $price($minimum_cab, $name), "\"{$name}\" was priced at its minimum fare.");
		$check(is_wp_error(MPTBM_Function::validate_transport_price($cab, $price($cab, $name))), "A booking path would charge \"{$name}\".");
		$quote = MPTBM_Function::validate_vehicle_for_quote($cab, array('price_based' => 'fixed_route', 'start_place' => $name, 'end_place' => ''));
		$check(is_wp_error($quote) && 'mptbm_vehicle_not_offered' === $quote->get_error_code(), "A booking for \"{$name}\" passed the quote check.");
	}

	/* 2. The real route still books at route price + base fare. */
	$check($listed($cab, 'Real Route'), 'The search no longer lists the cab for its own route.');
	$check($near($price($cab, 'Real Route'), 85.0), 'Real Route is no longer 80 + 5 = 85: got ' . wp_json_encode($price($cab, 'Real Route')));
	$check($near($price($minimum_cab, 'Real Route'), 80.0), 'A route above the minimum price is no longer charged at the route price.');
	$check(true === MPTBM_Function::validate_vehicle_for_quote($cab, array('price_based' => 'fixed_route', 'start_place' => 'Real Route', 'end_place' => '')), 'The quote check refuses the real route.');

	/* 3. Every name the dropdown offers (entity-decoded, spaces folded by the search) still matches. */
	$decoded = static function (int $route_id): string {
		return html_entity_decode(get_the_title($route_id), ENT_QUOTES, 'UTF-8');
	};
	$expected = array($decoded($real) => 85.0, $decoded($dashed) => 65.0, $decoded($spaced) => 45.0);
	$offered  = array_values(MPTBM_Function::get_all_routes($cab));
	$check(array_keys($expected) === $offered, 'The dropdown should offer the 3 published routes: ' . wp_json_encode($offered));
	$check(false !== strpos($decoded($dashed), "\u{2013}"), 'The dashed route title was not texturized, so the decode case is not exercised.');
	foreach ($expected as $name => $want) {
		$posted = sanitize_text_field(wp_unslash((string) $name)); // What the search stores (MPTBM_Transport_Search).
		$check($listed($cab, $posted), "The search does not list the cab for the offered route \"{$name}\".");
		$check($near($price($cab, $posted), $want), "The offered route \"{$name}\" priced " . wp_json_encode($price($cab, $posted)) . " instead of {$want}.");
	}

	/* 4. A trailing space in the saved model does not skip the route check (get_price() trims it). */
	update_post_meta($cab, 'mptbm_price_based', 'fixed_route ');
	$check(!$listed($cab, 'real route'), 'A "fixed_route " vehicle skipped the route check.');
	$check($listed($cab, 'Real Route'), 'A "fixed_route " vehicle refused its own route.');
	update_post_meta($cab, 'mptbm_price_based', 'fixed_route');

	/* 5. Other models: map/clock-priced ones need no place match, unknown ones are refused. */
	$by_model = array(
		'distance'          => 'dynamic',
		'duration'          => 'dynamic',
		'distance_duration' => 'dynamic',
		'inclusive'         => 'dynamic',
		'fixed_hourly'      => 'fixed_hourly',
		'fixed_daily'       => 'fixed_daily',
		'fixed_distance'    => 'fixed_map',
		'fixed_map'         => 'fixed_map',
	);
	foreach ($by_model as $model => $mode) {
		$id = $vehicle("Guard {$model} Cab", array('mptbm_price_based' => $model));
		$check($listed($id, 'Anywhere', $mode, 'Somewhere'), "A {$model} vehicle is no longer listed under a {$mode} search.");
	}
	foreach (array('fixed_zone_pickup', 'future_model', '') as $model) {
		$id = $vehicle("Guard unknown {$model} Cab", array('mptbm_price_based' => $model));
		$check(!$listed($id, 'Anywhere', 'dynamic', 'Somewhere'), "A vehicle with the unknown model \"{$model}\" skipped the place check.");
	}

	/* 6. Manual and fixed-zone matching is unchanged. */
	$manual = $vehicle('Guard Manual Cab', array(
		'mptbm_price_based'       => 'manual',
		'mptbm_manual_price_info' => array(array('start_location' => 'Airport', 'end_location' => 'Downtown', 'price' => 50)),
	));
	$check($listed($manual, 'Airport', 'manual', 'Downtown'), 'A manual route is no longer listed.');
	$check(!$listed($manual, 'Airport', 'manual', 'Harbour'), 'A manual pair with no price was listed.');
	$zone = $vehicle('Guard Zone Cab', array(
		'mptbm_price_based'           => 'fixed_zone',
		'mptbm_fixed_zone_price_info' => array(array('start_location' => 'term_1', 'end_location' => 'term_2', 'price' => 70)),
	));
	$check($listed($zone, 'term_1', 'fixed_zone', 'term_2'), 'A fixed-zone row is no longer listed.');
	$check(!$listed($zone, 'term_1', 'fixed_zone', 'term_3'), 'A fixed-zone pair with no row was listed.');
} finally {
	foreach ($made as $id) {
		$product_id = (int) get_post_meta($id, 'link_wc_product', true);
		if ($product_id) {
			wp_delete_post($product_id, true);
		}
		wp_delete_post($id, true);
	}
}

if ($failures) {
	WP_CLI::error(sprintf("%d of %d fixed-route guard checks failed:\n- %s", count($failures), $checks, implode("\n- ", $failures)));
}
WP_CLI::success(sprintf('All %d fixed-route guard checks passed.', $checks));
