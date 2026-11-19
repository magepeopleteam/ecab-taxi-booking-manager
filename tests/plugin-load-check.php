<?php
/**
 * A plugin counts as active only when WordPress loads it in this request.
 *
 * The active-plugins option keeps a plugin whose folder is gone (deleted over FTP, or
 * missing during or after a failed update). check_woocommerce() used to report such a
 * WooCommerce as active, so the booking flow ran in WooCommerce mode without WooCommerce
 * and every add-to-cart reached WC() and fataled.
 *
 * Run with: wp --path=/path/to/site eval-file wp-content/plugins/ecab-taxi-booking-manager/tests/plugin-load-check.php
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

if (!class_exists('MP_Global_Function')) {
	echo "SKIPPED: activate the free eCab plugin first.\n";
	exit(1);
}

$missing = 'zz-missing-core/zz-missing-core.php';
$listed  = static function ($plugins) use ($missing) {
	$plugins   = (array) $plugins;
	$plugins[] = $missing;
	return $plugins;
};

try {
	$loads = new ReflectionMethod('MP_Global_Function', 'plugin_loads');
	$loads->setAccessible(true);

	add_filter('option_active_plugins', $listed);
	$check(is_plugin_active($missing), 'the missing plugin is listed as active (the state a deleted folder leaves behind)');
	$check(false === $loads->invoke(null, $missing), 'a listed plugin whose files are missing does not count as loaded');
	remove_filter('option_active_plugins', $listed);

	$check(true === $loads->invoke(null, 'ecab-taxi-booking-manager/MPTBM_Plugin.php'), 'the running free plugin counts as loaded');

	if (class_exists('WooCommerce')) {
		$check(1 === MP_Global_Function::check_woocommerce(), 'a loaded WooCommerce is reported as active');
		$check(class_exists('MPTBM_Booking_Mode') && MPTBM_Booking_Mode::is_woocommerce() === ('woocommerce' === MPTBM_Booking_Mode::get_mode()), 'the booking mode can still be WooCommerce');
		// Only loaded when WooCommerce is missing; its hooks are admin-only and harmless here.
		require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Woo_Installer.php';
		$installer = (new ReflectionClass('MPTBM_Woo_Installer'))->newInstanceWithoutConstructor();
		$active    = new ReflectionMethod($installer, 'is_woo_active');
		$active->setAccessible(true);
		$check(true === $active->invoke($installer), 'the WooCommerce installer sees a loaded WooCommerce as active');
	} else {
		$check(1 !== MP_Global_Function::check_woocommerce(), 'WooCommerce that is not loaded is never reported as active');
	}
} catch (Throwable $e) {
	$failures[] = 'Unexpected ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine();
} finally {
	remove_filter('option_active_plugins', $listed);
}

if ($failures) {
	echo 'FAILED (' . count($failures) . " of $checks checks):\n - " . implode("\n - ", $failures) . "\n";
	exit(1);
}
echo "OK ($checks checks)\n";
