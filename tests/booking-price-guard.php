<?php
/**
 * Booking price guard (Patchstack report 38828).
 *
 * A vehicle the search does not offer for the quoted pricing mode, or whose fare is not a real
 * price, can never be booked: get_price() returns false for a model the mode has no formula
 * for, every booking path refuses false or a fare of 0 (except vehicles shown at 0 on purpose),
 * and the cart re-checks each booking line before checkout. The vehicles are throwaway posts
 * that are deleted at the end; no network is used.
 *
 * Run with: wp --path=/var/www/html/magepeople eval-file wp-content/plugins/ecab-taxi-booking-manager/tests/booking-price-guard.php
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

$made     = array();
$vehicle  = static function (string $title, array $meta, string $status = 'publish') use (&$made): int {
	$id = (int) wp_insert_post(array('post_type' => 'mptbm_rent', 'post_status' => $status, 'post_title' => $title));
	foreach ($meta + array('mptbm_price_display_type' => 'normal') as $key => $value) {
		update_post_meta($id, $key, $value);
	}
	$made[] = $id;
	return $id;
};
// get_price() under one pricing mode, the way the search binds it.
$price_in = static function (int $id, string $mode, ...$args) {
	$bind = static function () use ($mode) {
		return $mode;
	};
	add_filter('mptbm_original_price_based', $bind, 99);
	$price = MPTBM_Function::get_price($id, ...$args);
	remove_filter('mptbm_original_price_based', $bind, 99);
	return $price;
};
$saved_post = $_POST;
$route      = array('Airport', 'Downtown');

try {
	$distance = $vehicle('Guard Distance Taxi', array('mptbm_price_based' => 'distance', 'mptbm_km_price' => 2));
	$hourly   = $vehicle('Guard Hourly Taxi', array('mptbm_price_based' => 'fixed_hourly', 'mptbm_hour_price' => 30));
	$manual   = $vehicle('Guard Manual Taxi', array(
		'mptbm_price_based'       => 'manual',
		'mptbm_manual_price_info' => array(array('start_location' => 'Airport', 'end_location' => 'Downtown', 'price' => 50)),
	));
	$zero     = $vehicle('Guard Zero Taxi', array('mptbm_price_based' => 'fixed_hourly', 'mptbm_hour_price' => 30, 'mptbm_price_display_type' => 'zero'));
	$message  = $vehicle('Guard Message Taxi', array('mptbm_price_based' => 'fixed_hourly', 'mptbm_price_display_type' => 'custom_message'));
	$draft    = $vehicle('Guard Draft Taxi', array('mptbm_price_based' => 'distance', 'mptbm_km_price' => 2), 'draft');

	/* 1. Which fares can be charged. */
	foreach (array('false' => false, 'null' => null, 'empty' => '', 'text' => 'abc', 'NAN' => NAN, 'INF' => INF, 'negative' => -5, 'zero' => 0, 'zero float' => 0.0, 'zero text' => '0') as $label => $bad) {
		$check(is_wp_error(MPTBM_Function::validate_transport_price($distance, $bad)), "A fare of {$label} was accepted for a normally priced vehicle.");
	}
	foreach (array(0.01, 30, '30.00') as $good) {
		$check(true === MPTBM_Function::validate_transport_price($distance, $good), "A real fare ({$good}) was refused.");
	}
	$check(true === MPTBM_Function::validate_transport_price($zero, 0), 'A vehicle shown at 0.00 on purpose could not be booked at 0.');
	$check(true === MPTBM_Function::validate_transport_price($message, 0), 'A custom-message vehicle could not be booked at 0.');
	$check(is_wp_error(MPTBM_Function::validate_transport_price($zero, false)), 'false was accepted for a vehicle shown at 0.00.');
	$check(is_wp_error(MPTBM_Function::validate_transport_price($zero, -1)), 'A negative fare was accepted for a vehicle shown at 0.00.');

	/* 2. Which vehicles the search offers per mode (the query the result list is built from). */
	$offered = static function (int $id, string $mode): bool {
		return MPTBM_Function::vehicle_offered_in_mode($id, $mode);
	};
	$check($offered($distance, 'dynamic'), 'A distance-priced vehicle is not offered for a distance search.');
	$check(!$offered($distance, 'fixed_hourly'), 'A distance-priced vehicle is offered for an hourly search (the reported bug).');
	$check(!$offered($distance, 'manual'), 'A distance-priced vehicle is offered for a manual-route search.');
	$check($offered($hourly, 'fixed_hourly'), 'An hourly vehicle is not offered for an hourly search.');
	$check(!$offered($hourly, 'dynamic'), 'An hourly vehicle is offered for a distance search.');
	$check($offered($manual, 'manual'), 'A manual-route vehicle is not offered for a manual search.');
	$check(!$offered($manual, 'fixed_daily'), 'A manual-route vehicle is offered for a daily search.');
	$check(!$offered($manual, 'fixed_hourly'), 'A manual-route vehicle is offered for an hourly search.');
	$check(!$offered($distance, ''), 'A vehicle is offered for an empty mode.');
	$check(!$offered($distance, 'bogus'), 'A vehicle is offered for an unknown mode.');
	$check(!$offered($draft, 'dynamic'), 'An unpublished vehicle is offered.');
	$check(!$offered((int) get_option('page_on_front') ?: 1, 'dynamic'), 'A post that is not a vehicle is offered.');

	/* 3. get_price(): false when the vehicle model has no formula for the mode, never 0. */
	$check(false === $price_in($distance, 'fixed_hourly', 0, 0, 'Airport', 'Downtown', 0, 1, 1), 'Distance vehicle under an hourly search did not return false.');
	$check(false === $price_in($manual, 'fixed_daily', 0, 0, 'Airport', 'Downtown', 0, 1, 1), 'Manual vehicle under a daily search did not return false (was priced at 0).');
	$check(false === $price_in($hourly, 'manual', 0, 0, 'Airport', 'Downtown', 0, 1, 0), 'Hourly vehicle under a manual search did not return false.');
	$check(30.0 === $price_in($hourly, 'fixed_hourly', 0, 0, 'Airport', 'Downtown', 0, 1, 1), 'Hourly fare changed.');
	$check(90.0 === $price_in($hourly, 'fixed_hourly', 0, 0, 'Airport', 'Downtown', 0, 1, 3), 'Three-hour fare changed.');
	$check(50.0 === $price_in($manual, 'manual', 0, 0, 'Airport', 'Downtown', 0, 1, 0), 'Manual route fare changed.');
	$zero_price = $price_in($zero, 'fixed_hourly', 0, 0, 'Airport', 'Downtown', 0, 1, 1);
	$check(is_numeric($zero_price) && 0.0 === (float) $zero_price, 'A vehicle shown at 0.00 no longer prices at 0.');

	/* 4. The quote gate: mode plus the vehicle's own route rows. */
	$quote = static function (string $mode, string $start = 'Airport', string $end = 'Downtown'): array {
		return array('price_based' => $mode, 'start_place' => $start, 'end_place' => $end);
	};
	$check(true === MPTBM_Function::validate_vehicle_for_quote($manual, $quote('manual')), 'Manual vehicle refused on its own route.');
	$refused = MPTBM_Function::validate_vehicle_for_quote($manual, $quote('manual', 'Airport', 'Harbour'));
	$check(is_wp_error($refused) && 'mptbm_vehicle_not_offered' === $refused->get_error_code(), 'Manual vehicle accepted on a route it has no price for.');
	$refused = MPTBM_Function::validate_vehicle_for_quote($distance, $quote('fixed_hourly'));
	$check(is_wp_error($refused) && 'mptbm_vehicle_not_offered' === $refused->get_error_code(), 'Distance vehicle accepted on an hourly quote.');
	$check(true === MPTBM_Function::validate_vehicle_for_quote($hourly, $quote('fixed_hourly')), 'Hourly vehicle refused on an hourly quote.');

	/* 5. The real WooCommerce cart, added to directly (WooCommerce 11 runs no validation filter there). */
	if (function_exists('wc_load_cart') && class_exists('MPTBM_Hidden_Product')) {
		wc_load_cart();
		$product = static function (int $id): int {
			return MPTBM_Hidden_Product::get_or_create_wc_product($id);
		};
		$hourly_quote = array(
			'price_based' => 'fixed_hourly', 'start_place' => 'Airport', 'end_place' => 'Downtown',
			'distance' => 0, 'duration' => 0, 'distance_verified' => false, 'fixed_time' => 1, 'two_way' => 1,
			'start_date' => '2026-12-01', 'start_time' => '10.00', 'booking_datetime' => '2026-12-01 10:00',
		);
		$post_quote = static function (string $mode, string $start = 'Airport', string $end = 'Downtown'): void {
			$_POST = array('mptbm_start_place' => $start, 'mptbm_end_place' => $end, 'mptbm_original_price_base' => $mode, 'transport_quantity' => 1);
		};
		$error_notices = static function (): string {
			return implode(' | ', wp_list_pluck(wc_get_notices('error'), 'notice'));
		};

		MPTBM_Function::set_search_context($hourly_quote);
		$check('fixed_hourly' === (MPTBM_Function::get_search_context()['price_based'] ?? ''), 'Could not store a search quote in the PHP session (test setup).');

		WC()->cart->empty_cart();
		wc_clear_notices();
		$post_quote('fixed_hourly');
		$key = WC()->cart->add_to_cart($product($distance), 1);
		$check(!$key && 0 === count(WC()->cart->get_cart()), 'The distance vehicle went into the cart on an hourly quote (the reported bug).');
		$check(false !== strpos($error_notices(), 'not available'), 'Refusing the distance vehicle left no customer-facing reason.');

		WC()->cart->empty_cart();
		wc_clear_notices();
		$key = WC()->cart->add_to_cart($product($hourly), 1);
		$line = $key ? WC()->cart->get_cart_item($key) : array();
		$check($key && 30.0 === (float) ($line['mptbm_tp'] ?? -1), 'The hourly vehicle no longer books at its own fare.');
		$check('fixed_hourly' === ($line['original_price_based'] ?? ''), 'The cart line does not record the quote mode.');

		// A quote with no posted mode used to skip the mode comparison entirely.
		WC()->cart->empty_cart();
		wc_clear_notices();
		$post_quote('');
		$check(!WC()->cart->add_to_cart($product($distance), 1), 'The distance vehicle went into the cart when no mode was posted.');

		// Re-cased place names still pass the quote check; the fare comes from the quote's own names.
		MPTBM_Function::set_search_context(array_merge($hourly_quote, array('price_based' => 'manual', 'fixed_time' => 0)));
		WC()->cart->empty_cart();
		wc_clear_notices();
		$post_quote('manual', 'airport', 'downtown');
		$key  = WC()->cart->add_to_cart($product($manual), 1);
		$line = $key ? WC()->cart->get_cart_item($key) : array();
		$check($key && 50.0 === (float) ($line['mptbm_tp'] ?? -1), 'Re-cased place names priced the manual route at ' . ($line['mptbm_tp'] ?? 'nothing') . ' instead of 50.');

		/* 6. The cart guard: lines already in a cart are re-checked before checkout. */
		$guard_errors = static function () use ($error_notices): string {
			wc_clear_notices();
			do_action('woocommerce_check_cart_items');
			return $error_notices();
		};
		$check('' === $guard_errors(), 'The cart guard refused a correctly priced line: ' . $error_notices());
		WC()->cart->cart_contents[$key]['mptbm_base_price'] = 0;
		WC()->cart->cart_contents[$key]['mptbm_tp']         = 0;
		$check(false !== strpos($guard_errors(), 'cannot be priced'), 'The cart guard let a line priced at 0 through.');
		WC()->cart->cart_contents[$key]['mptbm_base_price'] = 50;
		WC()->cart->cart_contents[$key]['mptbm_tp']         = 50;
		WC()->cart->cart_contents[$key]['original_price_based'] = 'fixed_hourly';
		$check(false !== strpos($guard_errors(), 'not available'), 'The cart guard let a line of a vehicle not offered in its mode through.');

		WC()->cart->empty_cart();
		wc_clear_notices();
	} else {
		$failures[] = 'WooCommerce is not active: the cart checks did not run.';
	}
} finally {
	$_POST = $saved_post;
	MPTBM_Function::set_search_context(array());
	foreach ($made as $id) {
		$product_id = (int) get_post_meta($id, 'link_wc_product', true);
		if ($product_id) {
			wp_delete_post($product_id, true);
		}
		wp_delete_post($id, true);
	}
}

if ($failures) {
	WP_CLI::error(sprintf("%d of %d booking price guard checks failed:\n- %s", count($failures), $checks, implode("\n- ", $failures)));
}
WP_CLI::success(sprintf('All %d booking price guard checks passed.', $checks));
