<?php
/**
 * Fixed-hourly / fixed-daily duration validation.
 *
 * The customer-chosen hour/day count multiplies the vehicle's rate, so a fractional or
 * too-short value must be refused before any fare is built.
 *
 * Run with: wp --path=/var/www/html/magepeople eval-file wp-content/plugins/ecab-taxi-booking-manager/tests/fixed-duration.php
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

// 1. The normaliser: whole numbers only; everything else is 0 ("no valid duration").
foreach (array('1' => 1, '2' => 2, '12' => 12, '168' => 168, ' 3 ' => 3, '2.0' => 2, '007' => 7) as $in => $expected) {
	$check(MPTBM_Function::normalize_fixed_time((string) $in) === $expected, 'Whole value ' . var_export((string) $in, true) . ' should normalise to ' . $expected . '.');
}
foreach (array('0', '', '0.01', '0.001', '0.5', '1.5', '2.5', '-1', '-0', '1e2', '1e309', 'abc', 'NaN', 'INF', '12345') as $in) {
	$check(MPTBM_Function::normalize_fixed_time($in) === 0, 'Value ' . var_export($in, true) . ' should normalise to 0.');
}
$check(MPTBM_Function::normalize_fixed_time(3) === 3, 'An int from the session should be kept.');
$check(MPTBM_Function::normalize_fixed_time(3.0) === 3, 'A whole float left in the session by an earlier version should be kept.');
$check(MPTBM_Function::normalize_fixed_time(2.5) === 0, 'A fractional float should normalise to 0.');
$check(MPTBM_Function::normalize_fixed_time(-2) === 0, 'A negative int should normalise to 0.');
$check(MPTBM_Function::normalize_fixed_time(INF) === 0, 'INF should normalise to 0.');
$check(MPTBM_Function::normalize_fixed_time(array('1')) === 0, 'An array should normalise to 0.');
$check(MPTBM_Function::normalize_fixed_time(null) === 0, 'null should normalise to 0.');

// 2. The checkout gate, through a real session context (the same record checkout reads).
ini_set('session.use_cookies', '0');
ini_set('session.use_only_cookies', '0');
$session_ids = array();
$context_for = static function ($price_based, $fixed_time) use (&$session_ids) {
	if (session_status() === PHP_SESSION_ACTIVE) {
		session_write_close();
	}
	$session_id    = 'mptbmfixeddur' . count($session_ids);
	$session_ids[] = $session_id;
	session_id($session_id);
	session_start();
	MPTBM_Function::set_search_context(array(
		'distance'          => 0,
		'duration'          => 0,
		'distance_verified' => false,
		'start_place'       => 'Downtown',
		'end_place'         => 'Airport',
		'price_based'       => $price_based,
		'fixed_time'        => $fixed_time,
	));
	// set_search_context() closes the session; checkout reads it back the same way.
	session_id($session_id);
	session_start();
	return MPTBM_Function::get_checkout_search_context('Downtown', 'Airport', $price_based);
};
$accepted = static function ($result) {
	return is_array($result) && !is_wp_error($result);
};
$rejected_with = static function ($result, $code) {
	return is_wp_error($result) && $result->get_error_code() === $code;
};

$saved_settings = get_option('mptbm_general_settings', null);
try {
	$with_settings = static function (array $values) use ($saved_settings) {
		$current = is_array($saved_settings) ? $saved_settings : array();
		update_option('mptbm_general_settings', array_merge($current, $values));
	};

	// Hourly, "Minimum Booking Hours" off (0): the floor is still 1 hour.
	$with_settings(array('minimum_booking_hours' => '0', 'minimum_booking_days' => '1'));
	foreach (array('1', '2', '12', '168', '2.0', 3, 3.0) as $value) {
		$check($accepted($context_for('fixed_hourly', $value)), 'fixed_hourly should accept ' . var_export($value, true) . ' hour(s).');
	}
	foreach (array('0.01', '0.001', '0.0001', '0.5', '1.5', '0', '-1', '-5', '1e309', '1e-9', 'abc', '', '169', '1000', 0.01, 2.5, -1.0, INF, array('1')) as $value) {
		$check($rejected_with($context_for('fixed_hourly', $value), 'mptbm_quote_hours'), 'fixed_hourly should reject ' . var_export($value, true) . ' hour(s).');
	}

	// Hourly with the admin minimum set to 3 hours: shorter is refused, the minimum itself is fine.
	$with_settings(array('minimum_booking_hours' => '3'));
	$check($rejected_with($context_for('fixed_hourly', '1'), 'mptbm_quote_hours'), 'fixed_hourly should reject 1 hour when the minimum is 3.');
	$check($rejected_with($context_for('fixed_hourly', '2'), 'mptbm_quote_hours'), 'fixed_hourly should reject 2 hours when the minimum is 3.');
	$check($accepted($context_for('fixed_hourly', '3')), 'fixed_hourly should accept exactly the minimum of 3 hours.');
	$check($accepted($context_for('fixed_hourly', '12')), 'fixed_hourly should accept 12 hours when the minimum is 3.');

	// Daily keeps its existing bounds.
	$with_settings(array('minimum_booking_hours' => '0', 'minimum_booking_days' => '1'));
	foreach (array('1', '2', '30', '90') as $value) {
		$check($accepted($context_for('fixed_daily', $value)), 'fixed_daily should accept ' . var_export($value, true) . ' day(s).');
	}
	foreach (array('0', '0.5', '0.01', '-1', '1e309', 'abc', '91', '1.5') as $value) {
		$check($rejected_with($context_for('fixed_daily', $value), 'mptbm_quote_days'), 'fixed_daily should reject ' . var_export($value, true) . ' day(s).');
	}
	$with_settings(array('minimum_booking_days' => '2'));
	$check($rejected_with($context_for('fixed_daily', '1'), 'mptbm_quote_days'), 'fixed_daily should reject 1 day when the minimum is 2.');
	$check($accepted($context_for('fixed_daily', '2')), 'fixed_daily should accept exactly the minimum of 2 days.');
} finally {
	if (session_status() === PHP_SESSION_ACTIVE) {
		session_write_close();
	}
	foreach ($session_ids as $session_id) {
		$session_file = rtrim((string) session_save_path(), '/') . '/sess_' . $session_id;
		if ($session_file !== '/sess_' . $session_id && is_file($session_file)) {
			@unlink($session_file); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}
	if (null === $saved_settings) {
		delete_option('mptbm_general_settings');
	} else {
		update_option('mptbm_general_settings', $saved_settings);
	}
}

if ($failures) {
	foreach ($failures as $failure) {
		WP_CLI::warning($failure);
	}
	WP_CLI::error(count($failures) . ' of ' . $checks . ' fixed-duration check(s) failed.');
}

WP_CLI::success($checks . ' fixed-duration checks passed.');
