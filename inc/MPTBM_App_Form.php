<?php
/*
 * The search form of the mobile app, defined by the website's own booking form.
 *
 * The website's booking form shows tabs (Hourly, Distance, Flat rate ...) as configured on the page that
 * holds it, and each tab asks for different things (templates/registration/get_details.php). The app must
 * offer exactly the same, so this class reproduces those conditions from the same settings and the app only
 * draws what it is told. Nothing here prices a trip.
 */
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_App_Form')) {
	class MPTBM_App_Form {
		/** website tab key => array(label key, default label, pricing mode, Pro only) - registration_layout.php */
		const TABS = array(
			'distance' => array('distance_tab_label', 'Distance', 'dynamic', false),
			'hourly' => array('hourly_tab_label', 'Hourly', 'fixed_hourly', false),
			'manual' => array('flat_rate_tab_label', 'Flat rate', 'manual', false),
			'fixed_route' => array('fixed_route_tab_label', 'Fixed Route', 'fixed_route', false),
			'daily' => array('daily_tab_label', 'Daily', 'fixed_daily', false),
			'fixed_map' => array('fixed_distance_tab_label', 'Fixed Distance', 'fixed_distance', true),
			'fixed_zone_pickup' => array('fixed_zone_pickup_tab_label', 'Fixed Zone Pickup', 'fixed_zone', true),
			'fixed_zone_dropoff' => array('fixed_zone_dropoff_tab_label', 'Fixed Zone Dropoff', 'fixed_zone_dropoff', true),
		);
		/** The website's own default when a tabbed form names no tabs. */
		const DEFAULT_TABS = 'distance,hourly,manual';

		/** Tab keys, in the order the site shows them. */
		public static function tab_keys(): array {
			$configured = trim((string) MP_Global_Function::get_settings('mptbm_app_settings', 'trip_tabs', ''));
			$raw = '' !== $configured ? $configured : self::detect_tabs();
			$keys = array();
			foreach (explode(',', $raw) as $key) {
				$key = sanitize_key(trim($key));
				$key = 'flat-rate' === $key ? 'manual' : ('fixed_distance' === $key ? 'fixed_map' : ('fixed_zone' === $key ? 'fixed_zone_pickup' : $key));
				if (isset(self::TABS[$key]) && (!self::TABS[$key][3] || class_exists('MPTBM_Dependencies_Pro')) && !in_array($key, $keys, true)) {
					$keys[] = $key;
				}
			}
			return $keys ?: explode(',', self::DEFAULT_TABS);
		}

		/** The `tabs="..."` of the booking form on the site's pages (shortcode, block or Elementor), else the website default. */
		private static function detect_tabs(): string {
			$cached = get_transient('mptbm_app_detected_tabs');
			if (is_string($cached)) {
				return $cached;
			}
			global $wpdb;
			$found = '';
			$sources = array_merge(
				(array) $wpdb->get_col("SELECT post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE '%mptbm_booking%' LIMIT 20"),
				(array) $wpdb->get_col("SELECT post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE '%mptbm%tabs%' LIMIT 20"),
				(array) $wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND meta_value LIKE '%mptbm_booking%' LIMIT 20")
			);
			foreach ($sources as $content) {
				$content = stripslashes((string) $content);
				// A form that turns the tabs on (tab="yes") and names them, as a shortcode or as block / widget settings.
				if (preg_match('/tab\\\\?"?\\s*[=:]\\s*\\\\?"yes/i', $content) && preg_match('/tabs\\\\?"?\\s*[=:]\\s*\\\\?"([a-z_,\\s-]+)/i', $content, $m)) {
					$found = $m[1];
					break;
				}
			}
			$found = '' !== $found ? $found : self::DEFAULT_TABS;
			set_transient('mptbm_app_detected_tabs', $found, HOUR_IN_SECONDS);
			return $found;
		}

		private static function setting(string $key, string $default) {
			return MP_Global_Function::get_settings('mptbm_general_settings', $key, $default);
		}

		/** @return array the tabs of the site's form, each with the fields it asks for */
		public static function definition(): array {
			$pro = class_exists('MPTBM_Dependencies_Pro');
			$filters = $pro ? get_option('mptbm_search_filter_settings', array()) : array();
			$filters = is_array($filters) ? $filters : array();
			$maximums = array('passenger' => 0, 'bag' => 0, 'hand_luggage' => 0);
			foreach (MP_Global_Function::get_all_post_id('mptbm_rent') as $id) {
				$maximums['passenger'] = max($maximums['passenger'], (int) get_post_meta($id, 'mptbm_maximum_passenger', true));
				$maximums['bag'] = max($maximums['bag'], (int) get_post_meta($id, 'mptbm_maximum_bag', true));
				$maximums['hand_luggage'] = max($maximums['hand_luggage'], (int) get_post_meta($id, 'mptbm_maximum_hand_luggage', true));
			}
			$capacity = array();
			foreach (array('passenger' => 'enable_max_passenger_filter', 'bag' => 'enable_max_bag_filter', 'hand_luggage' => 'enable_max_hand_luggage_filter') as $name => $option) {
				if ('yes' === ($filters[$option] ?? 'no') && $maximums[$name] > 0) {
					$capacity[$name] = array('min' => 'bag' === $name || 'hand_luggage' === $name ? 0 : 1, 'max' => $maximums[$name]);
				}
			}

			$minimum_hours = max(1, (int) self::setting('minimum_booking_hours', '0'));
			$minimum_days = max(1, (int) self::setting('minimum_booking_days', '1'));
			$returns = 'enable' === MPTBM_Function::get_general_settings('taxi_return', 'enable') && 'disable' !== self::setting('transfer_type', 'enable');
			$waiting = 'enable' === MPTBM_Function::get_general_settings('taxi_waiting_time', 'enable');
			$stops_on = 'yes' === self::setting('mptbm_extra_stop_between_pickup_dropoff', 'no');
			$tabs = array();
			foreach (self::tab_keys() as $key) {
				list($label_key, $label, $mode) = self::TABS[$key];
				$hourly = 'fixed_hourly' === $mode;
				$daily = 'fixed_daily' === $mode;
				$pickup = in_array($mode, array('manual', 'fixed_zone', 'fixed_route'), true) ? $mode : 'map';
				if ('fixed_route' === $mode || ($hourly && 'disable' === self::setting('disable_dropoff_hourly', 'enable'))) {
					$dropoff = 'none';
				} elseif ('manual' === $mode || 'fixed_zone_dropoff' === $mode) {
					$dropoff = $mode;
				} else {
					$dropoff = 'map';
				}
				$tabs[] = array(
					'key' => $key,
					'label' => wp_strip_all_tags(mptbm_get_translation($label_key, __($label, 'ecab-taxi-booking-manager'))),
					'price_based' => $mode,
					'pickup' => $pickup,
					'dropoff' => $dropoff,
					'extra_stops' => $stops_on && !in_array($mode, array('fixed_zone', 'fixed_zone_dropoff', 'fixed_hourly', 'manual', 'fixed_route'), true) ? max(1, (int) self::setting('mptbm_max_extra_stops', '3')) : 0,
					'return' => $returns && !$hourly,
					'waiting' => $waiting && !$hourly,
					'duration' => $hourly ? array('unit' => 'hour', 'min' => $minimum_hours, 'max' => 12) : ($daily ? array('unit' => 'day', 'min' => $minimum_days, 'max' => 30) : null),
					'passengers' => 'yes' === self::setting('show_number_of_passengers', 'no'),
					'capacity' => (object) $capacity,
				);
			}
			return $tabs;
		}
	}
}
