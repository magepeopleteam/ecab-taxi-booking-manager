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

		/** Minutes since midnight of a schedule value such as 6.30 (06:30). Mirrors get_details.php's convertToMinutes(). */
		private static function minutes(float $value): int {
			$hours = floor($value);
			return (int) round($hours * 60 + ($value - $hours) * 100);
		}

		/**
		 * When a trip can be picked up, as the website's form works it out (get_details.php): the bookable dates, each
		 * weekday's opening window across the fleet, the interval between times, and the buffer that rules out the
		 * next few minutes or hours. The app builds its time list from this, so it offers exactly the website's.
		 *
		 * @return array dates (first bookable first), interval, day_ranges [weekday => [from, to] in minutes],
		 *               buffer_minutes, today, now_minutes, time_format
		 */
		public static function schedule(string $price_based): array {
			$interval = max(1, (int) MPTBM_Function::get_general_settings('mptbm_pickup_interval_time', '30'));
			$starts = array();
			$ends = array();
			$week = array_keys(MP_Global_Function::week_day());
			foreach (MP_Global_Function::get_all_post_id('mptbm_rent') as $id) {
				$all_time = get_post_meta($id, 'mptbm_available_for_all_time', true);
				$default_start = get_post_meta($id, 'mptbm_default_start_time', true);
				$default_end = get_post_meta($id, 'mptbm_default_end_time', true);
				foreach ($week as $day) {
					if ('' === $all_time || 'on' === $all_time) {
						$starts[$day][] = 0.5;
						$ends[$day][] = 24.0;
						continue;
					}
					$from = get_post_meta($id, 'mptbm_' . $day . '_start_time', true);
					$to = get_post_meta($id, 'mptbm_' . $day . '_end_time', true);
					$from = ('' === $from || 'default' === $from) ? $default_start : $from;
					$to = ('' === $to || 'default' === $to) ? $default_end : $to;
					if ('' === $from || '' === $to) {
						continue;
					}
					if ((float) $from > (float) $to) { // overnight: the website offers the whole day and filters later
						$starts[$day][] = 0.5;
						$ends[$day][] = 24.0;
						continue;
					}
					$starts[$day][] = (float) $from;
					$ends[$day][] = (float) $to;
				}
			}
			$ranges = array();
			foreach ($week as $day) {
				if (!empty($starts[$day]) && !empty($ends[$day])) {
					$ranges[$day] = array(self::minutes(min($starts[$day])), self::minutes(max($ends[$day])));
				}
			}
			$all_starts = $starts ? array_merge(...array_values($starts)) : array();
			$all_ends = $ends ? array_merge(...array_values($ends)) : array();
			$max_minutes = self::minutes($all_ends ? (float) max($all_ends) : 24.0);

			$dates = MPTBM_Function::get_all_dates('dynamic' === $price_based || '' === $price_based ? 'dynamic' : $price_based);
			$buffer = function_exists('ecab_get_buffer_time') ? (int) ecab_get_buffer_time() : (int) MP_Global_Function::get_settings('mptbm_general_settings', 'enable_buffer_time');
			$now = current_time('timestamp');
			$now_minutes = (int) wp_date('H', $now) * 60 + (int) wp_date('i', $now);
			$buffer_end = max($now_minutes + $buffer, 0);
			// Dates the buffer has already used up are dropped, exactly as the form does.
			for ($i = 0, $hide = (int) floor($buffer_end / 1440); $i < $hide && $dates; $i++) {
				array_shift($dates);
			}
			if ($buffer_end >= 1440) {
				$buffer_end %= 1440;
			}
			if ($dates && $buffer_end >= $max_minutes) {
				array_shift($dates);
			}
			return array(
				'dates' => array_slice(array_values($dates), 0, 120),
				'interval' => $interval,
				'day_ranges' => (object) $ranges,
				'buffer_minutes' => $buffer,
				'today' => wp_date('Y-m-d', $now),
				'now_minutes' => $now_minutes,
				'time_format' => (string) get_option('time_format', 'g:i a'),
			);
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
					'schedule' => self::schedule($mode),
				);
			}
			return $tabs;
		}
	}
}
