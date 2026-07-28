<?php
/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.
if (!class_exists('MPTBM_Function')) {
	class MPTBM_Function
	{
		private static $fixed_route_matches = array();

		public static function fixed_route_found($post_id): bool
		{
			return !empty(self::$fixed_route_matches[(int) $post_id]);
		}

		/**
		 * Create a non-sequential customer-facing booking reference.
		 *
		 * The previous reference concatenated user/order/vehicle/post IDs, which made it
		 * predictable and unsuitable as either an identifier or an access credential.
		 */
		public static function create_booking_reference(): string
		{
			return strtoupper(wp_generate_password(16, false, false));
		}

		/**
		 * Return the private capability token for a booking, creating it when requested.
		 */
		public static function get_booking_access_token($booking_id, $create = false): string
		{
			$booking_id = absint($booking_id);
			if (!$booking_id || get_post_type($booking_id) !== 'mptbm_booking') {
				return '';
			}

			$token = (string) get_post_meta($booking_id, 'mptbm_access_token', true);
			if ($token !== '' || !$create) {
				return $token;
			}

			$token = wp_generate_password(48, false, false);
			if (!add_post_meta($booking_id, 'mptbm_access_token', $token, true)) {
				$token = (string) get_post_meta($booking_id, 'mptbm_access_token', true);
			}
			return $token;
		}

		public static function verify_booking_access_token($booking_id, $token): bool
		{
			$stored = self::get_booking_access_token($booking_id, false);
			return $stored !== '' && is_string($token) && hash_equals($stored, $token);
		}

		/**
		 * Verify a Book Now request with the fresh nonce rendered in the vehicle results.
		 *
		 * Guest-facing booking pages are commonly page-cached. Their page-level search
		 * nonce can therefore expire while the vehicle-result AJAX response is fresh.
		 * Prefer that response's purpose-specific nonce, but temporarily accept the
		 * legacy search nonce so cached JavaScript from the previous plugin version does
		 * not break during a rolling deployment.
		 */
		public static function verify_add_to_cart_nonce(): bool
		{
			$nonce = isset($_POST['mptbm_add_to_cart_nonce'])
				? sanitize_text_field(wp_unslash($_POST['mptbm_add_to_cart_nonce']))
				: '';
			if ($nonce !== '' && wp_verify_nonce($nonce, 'mptbm_add_to_cart')) {
				return true;
			}

			$legacy_nonce = isset($_POST['nonce'])
				? sanitize_text_field(wp_unslash($_POST['nonce']))
				: '';
			return $legacy_nonce !== '' && (bool) wp_verify_nonce($legacy_nonce, 'mptbm_transport_search');
		}

		public static function normalize_coordinates($coordinates): array
		{
			if (is_string($coordinates)) {
				$coordinates = json_decode(wp_unslash($coordinates), true);
			}
			if (!is_array($coordinates)) {
				return array();
			}
			$lat = isset($coordinates['latitude']) ? $coordinates['latitude'] : ($coordinates['lat'] ?? null);
			$lng = isset($coordinates['longitude']) ? $coordinates['longitude'] : ($coordinates['lng'] ?? null);
			if (!is_numeric($lat) || !is_numeric($lng) || (float) $lat < -90 || (float) $lat > 90 || (float) $lng < -180 || (float) $lng > 180) {
				return array();
			}
			return array('latitude' => (float) $lat, 'longitude' => (float) $lng);
		}

		public static function set_search_context(array $context): void
		{
			if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
				session_start();
			}
			if (session_status() !== PHP_SESSION_ACTIVE) {
				return;
			}
			$context['created_at'] = time();
			$_SESSION['mptbm_search_context'] = $context;
			session_write_close();
		}

		public static function get_search_context(): array
		{
			if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
				session_start();
			}
			$context = session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['mptbm_search_context']) && is_array($_SESSION['mptbm_search_context'])
				? $_SESSION['mptbm_search_context']
				: array();
			if (session_status() === PHP_SESSION_ACTIVE) {
				session_write_close();
			}
			return $context;
		}

		/**
		 * Validate that checkout is using the server-side search which produced the quote.
		 * Returns the context or a WP_Error suitable for a customer-facing checkout error.
		 */
		public static function get_checkout_search_context($start_place, $end_place, $price_based = '')
		{
			$context = self::get_search_context();
			if (!$context || empty($context['created_at']) || (time() - (int) $context['created_at']) > HOUR_IN_SECONDS) {
				return new WP_Error('mptbm_quote_expired', __('Your fare quote expired. Please search for the trip again.', 'ecab-taxi-booking-manager'));
			}
			if (strcasecmp(trim((string) $start_place), trim((string) ($context['start_place'] ?? ''))) !== 0
				|| strcasecmp(trim((string) $end_place), trim((string) ($context['end_place'] ?? ''))) !== 0) {
				return new WP_Error('mptbm_quote_mismatch', __('Trip details changed. Please search again to receive a verified fare.', 'ecab-taxi-booking-manager'));
			}
			if ($price_based && sanitize_key($price_based) !== sanitize_key($context['price_based'] ?? '')) {
				return new WP_Error('mptbm_quote_mode', __('The pricing mode changed. Please search for the trip again.', 'ecab-taxi-booking-manager'));
			}
			$distance_modes = array('dynamic', 'fixed_distance', 'fixed_map');
			if (in_array(sanitize_key($context['price_based'] ?? ''), $distance_modes, true) && empty($context['distance_verified'])) {
				return new WP_Error('mptbm_quote_unverified', __('The route could not be verified by the server. Please try the search again.', 'ecab-taxi-booking-manager'));
			}
			if (sanitize_key($context['price_based'] ?? '') === 'fixed_hourly') {
				$hours = (float) ($context['fixed_time'] ?? 0);
				if ($hours <= 0 || $hours > 168) {
					return new WP_Error('mptbm_quote_hours', __('Please select a valid hourly booking duration.', 'ecab-taxi-booking-manager'));
				}
			}
			return $context;
		}

		/** Validate capacity and extra-service selections shared by every checkout mode. */
		public static function validate_checkout_selections($post_id)
		{
			$limits = array(
				'mptbm_max_passenger'    => 'mptbm_maximum_passenger',
				'mptbm_max_bag'          => 'mptbm_maximum_bag',
				'mptbm_max_hand_luggage' => 'mptbm_maximum_hand_luggage',
			);
			foreach ($limits as $request_key => $meta_key) {
				$requested = isset($_POST[$request_key]) ? absint($_POST[$request_key]) : 0;
				$maximum = absint(get_post_meta($post_id, $meta_key, true));
				if ($maximum && $requested > $maximum) {
					return new WP_Error('mptbm_capacity', __('The selected vehicle does not have enough passenger or luggage capacity.', 'ecab-taxi-booking-manager'));
				}
			}

			$allowed = wp_list_pluck(self::get_available_extra_services($post_id), 'service_name');
			$names = isset($_POST['mptbm_extra_service']) ? array_values(array_map('sanitize_text_field', (array) wp_unslash($_POST['mptbm_extra_service']))) : array();
			$qtys = isset($_POST['mptbm_extra_service_qty']) ? array_values(array_map('absint', (array) wp_unslash($_POST['mptbm_extra_service_qty']))) : array();
			foreach (array_filter($names) as $index => $name) {
				if (!in_array($name, $allowed, true) || (isset($qtys[$index]) && $qtys[$index] > 100)) {
					return new WP_Error('mptbm_extra_service', __('An invalid extra service was selected.', 'ecab-taxi-booking-manager'));
				}
			}
			return true;
		}

		/** Recalculate the optional base-to-pickup/drop-off charge from server data. */
		public static function calculate_base_location_price($post_id, array $context): float
		{
			$settings = self::get_base_price_settings($post_id);
			$base     = array_map('trim', explode(',', (string) $settings['coords']));
			$pickup   = $context['start_coords'] ?? array();
			$dropoff  = $context['end_coords'] ?? array();
			if (count($base) !== 2 || !is_numeric($base[0]) || !is_numeric($base[1]) || !$pickup || !$dropoff) {
				return 0.0;
			}

			$distance = 0.0;
			$duration = 0.0;
			if ('yes' === $settings['charge_pickup']) {
				$data = self::get_server_distance($base[0], $base[1], $pickup['latitude'], $pickup['longitude']);
				if (is_array($data)) {
					$distance += (float) ($data['distance'] ?? 0);
					$duration += (float) ($data['duration'] ?? 0);
				}
			}
			if ('yes' === $settings['charge_dropoff']) {
				$data = self::get_server_distance($dropoff['latitude'], $dropoff['longitude'], $base[0], $base[1]);
				if (is_array($data)) {
					$distance += (float) ($data['distance'] ?? 0);
					$duration += (float) ($data['duration'] ?? 0);
				}
			}

			$km = $distance / 1000;
			if ($km < (float) $settings['threshold']) {
				return 0.0;
			}
			$charged_km = max(0, $km - (float) $settings['threshold']);
			$charged_hr = $km > 0 ? ($duration / 3600) * ($charged_km / $km) : 0;
			return max(0.0, ($charged_km * (float) $settings['price_km']) + ($charged_hr * (float) $settings['price_hour']));
		}

		//**************Support multi Language*********************//
		public static function post_id_multi_language($post_id)
		{
			if (function_exists('wpml_loaded')) {
				global $sitepress;
				$default_language = function_exists('wpml_loaded') ? $sitepress->get_default_language() : get_locale();
				return apply_filters('wpml_object_id', $post_id, MPTBM_Function::get_cpt(), TRUE, $default_language);
			}
			if (function_exists('pll_get_post_translations')) {
				$defaultLanguage = function_exists('pll_default_language') ? pll_default_language() : get_locale();
				$translations = function_exists('pll_get_post_translations') ? pll_get_post_translations($post_id) : [];
				return sizeof($translations) > 0 ? $translations[$defaultLanguage] : $post_id;
			}
			return $post_id;
		}
		//***********Template********************//
		public static function all_details_template()
		{
			$template_path = get_stylesheet_directory() . '/mptbm_templates/themes/';
			$default_path = MPTBM_PLUGIN_DIR . '/templates/themes/';
			$dir = is_dir($template_path) ? glob($template_path . "*") : glob($default_path . "*");
			$names = array();
			foreach ($dir as $filename) {
				if (is_file($filename)) {
					$file = basename($filename);
					$name = str_replace("?>", "", strip_tags(file_get_contents($filename, false, null, 24, 16)));
					$names[$file] = $name;
				}
			}
			$name = [];
			foreach ($names as $key => $value) {
				$name[$key] = $value;
			}
			return apply_filters('filter_mptbm_details_template', $name);
		}

		public static function get_feature_bag($post_id)
		{
			return get_post_meta($post_id, "mptbm_maximum_bag", 0);
		}

		public static function get_feature_passenger($post_id)
		{
			return get_post_meta($post_id, "mptbm_maximum_passenger", 0);
		}

	public static function get_schedule($post_id)
	{
		$days = MP_Global_Function::week_day();
		$days_name = array_keys($days);
		$schedule = [];
		
		// Get default times
		$default_start_time = get_post_meta($post_id, "mptbm_default_start_time", true);
		$default_end_time = get_post_meta($post_id, "mptbm_default_end_time", true);
		
		foreach ($days_name as $name) {
			$start_time = get_post_meta($post_id, "mptbm_" . $name . "_start_time", true);
			$end_time = get_post_meta($post_id, "mptbm_" . $name . "_end_time", true);
			
			// If day-specific times are empty or set to 'default', use default times
			if($start_time == '' || $start_time == 'default'){
				$start_time = $default_start_time;
			}
			if($end_time == '' || $end_time == 'default'){
				$end_time = $default_end_time;
			}
			
			// Only add to schedule if we have valid times
			if ($start_time !== "" && $end_time !== "" && $start_time !== null && $end_time !== null) {
				$schedule[$name] = [floatval($start_time), floatval($end_time)];
			}
		}
		
		// If no day-specific schedules found, add default schedule
		if (empty($schedule) && $default_start_time !== "" && $default_end_time !== "") {
			$schedule['default'] = [floatval($default_start_time), floatval($default_end_time)];
		}
		
		return $schedule;
	}

		public static function details_template_path(): string
		{
			$tour_id = get_the_id();
			$template_name = MP_Global_Function::get_post_info($tour_id, 'mptbm_theme_file', 'default.php');
			$file_name = 'themes/' . $template_name;
			$dir = MPTBM_PLUGIN_DIR . '/templates/' . $file_name;
			if (!file_exists($dir)) {
				$file_name = 'themes/default.php';
			}
			return self::template_path($file_name);
		}

		public static function get_taxonomy_name_by_slug($slug, $taxonomy)
		{
			global $wpdb;

			// Prepare the query
			$query = $wpdb->prepare(
				"SELECT t.name 
                 FROM {$wpdb->terms} t
                 INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                 WHERE t.slug = %s AND tt.taxonomy = %s",
				$slug,
				$taxonomy
			);

			// Execute the query
			$term_name = $wpdb->get_var($query);

			return $term_name;
		}

		public static function template_path($file_name): string
		{
			$template_path = get_stylesheet_directory() . '/mptbm_templates/';
			$default_dir = MPTBM_PLUGIN_DIR . '/templates/';
			$dir = is_dir($template_path) ? $template_path : $default_dir;
			$file_path = $dir . $file_name;
			return locate_template(array('mptbm_templates/' . $file_name)) ? $file_path : $default_dir . $file_name;
		}
		//************************//
		public static function get_general_settings($key, $default = '')
		{
			return MP_Global_Function::get_settings('mptbm_general_settings', $key, $default);
		}
		public static function get_cpt(): string
		{
			return 'mptbm_rent';
		}
		/**
		 * Whether the plugin should run in WooCommerce mode.
		 * WooCommerce is optional: when inactive, the plugin runs in standalone mode
		 * (custom booking + payment flow). Use this for readable mode checks.
		 */
		public static function is_wc_active(): bool
		{
			return MP_Global_Function::check_woocommerce() === 1;
		}
		/**
		 * Whether a booking can actually be completed end-to-end.
		 * Three routes qualify: the WooCommerce cart/checkout, the Pro plugin's custom
		 * (standalone) checkout, or the free built-in Offline method handled by
		 * MPTBM_Offline_Checkout. If none is available there is no way to take payment,
		 * so booking must be blocked rather than left to fail silently at the final step.
		 */
		public static function is_booking_available(): bool
		{
			return self::is_wc_active() || class_exists('MPTBM_Plugin_Pro') || self::offline_payment_enabled();
		}
		/**
		 * Whether the built-in Offline payment method is enabled on the Payments tab.
		 *
		 * Offline is the one custom payment method that is part of the FREE plugin: it
		 * needs no online processor, so it can be configured and enabled without Pro
		 * (PayPal & Stripe configuration stays Pro-only). Stored as
		 * mptbm_payment_settings[mptbm_offline_enable].
		 *
		 * Single source of truth - callers must not read the option key directly.
		 */
		public static function offline_payment_enabled(): bool
		{
			return MP_Global_Function::get_settings('mptbm_payment_settings', 'mptbm_offline_enable', 'off') === 'on';
		}
		public static function get_name()
		{
			return self::get_general_settings('label', esc_html__('Transportation', 'ecab-taxi-booking-manager'));
		}
		public static function get_slug()
		{
			return self::get_general_settings('slug', 'transportation');
		}
		public static function get_icon()
		{
			return self::get_general_settings('icon', 'dashicons-car');
		}
		public static function get_category_label()
		{
			return self::get_general_settings('category_label', esc_html__('Category', 'ecab-taxi-booking-manager'));
		}
		public static function get_category_slug()
		{
			return self::get_general_settings('category_slug', 'transportation-category');
		}
		public static function get_organizer_label()
		{
			return self::get_general_settings('organizer_label', esc_html__('Organizer', 'ecab-taxi-booking-manager'));
		}
		public static function get_organizer_slug()
		{
			return self::get_general_settings('organizer_slug', 'transportation-organizer');
		}
		
		/**
		 * Convert a page slug to a proper WordPress URL
		 * 
		 * @param string $slug_or_url The slug or URL to convert
		 * @return string The proper URL
		 */
		public static function get_page_url_from_slug($slug_or_url)
		{
			// If it's already a full URL, return it as is
			if (filter_var($slug_or_url, FILTER_VALIDATE_URL)) {
				return $slug_or_url;
			}
			
			// If it's empty, return empty
			if (empty($slug_or_url)) {
				return '';
			}
			
			// Try to find the page by slug
			$page = get_page_by_path($slug_or_url, OBJECT, 'page');
			if ($page) {
				return get_permalink($page->ID);
			}
			
			// Fallback to home URL with slug
			return home_url('/' . $slug_or_url . '/');
		}
		//*************************************************************Full Custom Function******************************//
		//*************Date*********************************//
		public static function get_date($post_id, $expire = false)
		{
			$now = current_time('Y-m-d');
			$date_type = MP_Global_Function::get_post_info($post_id, 'mptbm_date_type', 'repeated');
			$all_dates = [];
			$off_days = MP_Global_Function::get_post_info($post_id, 'mptbm_off_days');
			$all_off_days = explode(',', $off_days);
			$all_off_dates = MP_Global_Function::get_post_info($post_id, 'mptbm_off_dates', array());
			$off_dates = [];
			foreach ($all_off_dates as $off_date) {
				$off_dates[] = date('Y-m-d', strtotime($off_date));
			}
			if ($date_type == 'repeated') {
				$start_date = MP_Global_Function::get_post_info($post_id, 'mptbm_repeated_start_date', $now);
				if (strtotime($now) >= strtotime($start_date) && !$expire) {
					$start_date = $now;
				}
				$repeated_after = MP_Global_Function::get_post_info($post_id, 'mptbm_repeated_after', 1);
				$active_days = MP_Global_Function::get_post_info($post_id, 'mptbm_active_days', 10) - 1;
				$end_date = date('Y-m-d', strtotime($start_date . ' +' . $active_days . ' day'));
				$dates = MP_Global_Function::date_separate_period($start_date, $end_date, $repeated_after);
				foreach ($dates as $date) {
					$date = $date->format('Y-m-d');
					$day = strtolower(date('l', strtotime($date)));
					if (!in_array($date, $off_dates) && !in_array($day, $all_off_days)) {
						$all_dates[] = $date;
					}
				}
			} else {
				$particular_date_lists = MP_Global_Function::get_post_info($post_id, 'mptbm_particular_dates', array());
				if (sizeof($particular_date_lists)) {
					foreach ($particular_date_lists as $particular_date) {
						if ($particular_date && ($expire || strtotime($now) <= strtotime($particular_date)) && !in_array($particular_date, $off_dates) && !in_array($particular_date, $all_off_days)) {
							$all_dates[] = $particular_date;
						}
					}
				}
			}
			return apply_filters('mptbm_get_date', $all_dates, $post_id);
		}

		// Labels for the "Reason" dropdown on the manual Vehicle Availability toggle.
		// Shared by the admin editor, the admin vehicle list column, and the front-end search result.
		public static function get_availability_reason_labels()
		{
			return [
				'maintenance'        => esc_html__('Maintenance', 'ecab-taxi-booking-manager'),
				'booked'             => esc_html__('Booked (external)', 'ecab-taxi-booking-manager'),
				'accident'           => esc_html__('Accident', 'ecab-taxi-booking-manager'),
				'repair'             => esc_html__('Repair', 'ecab-taxi-booking-manager'),
				'cleaning'           => esc_html__('Cleaning', 'ecab-taxi-booking-manager'),
				'driver_unavailable' => esc_html__('Driver Unavailable', 'ecab-taxi-booking-manager'),
				'other'              => esc_html__('Other', 'ecab-taxi-booking-manager'),
			];
		}

		// Human-readable reason a vehicle was manually marked unavailable (custom note for "Other").
		public static function get_availability_reason_text($post_id)
		{
			$reason = get_post_meta($post_id, 'mptbm_availability_reason', true);
			$note = get_post_meta($post_id, 'mptbm_availability_reason_note', true);
			if ($reason === 'other' && $note) {
				return $note;
			}
			$labels = self::get_availability_reason_labels();
			return isset($labels[$reason]) ? $labels[$reason] : esc_html__('Unavailable', 'ecab-taxi-booking-manager');
		}

		// Remaining inventory quantity for a vehicle at a given date/time, based on
		// the "Booking Interval Time (minutes)" setting and overlapping bookings.
		// Used by the "automatic" Availability Check Mode to decide search-result inclusion.
		public static function acquire_inventory_lock($post_id, $timeout = 5): bool
		{
			global $wpdb;
			$key = 'mptbm_inventory_' . absint($post_id);
			return '1' === (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $key, absint($timeout)));
		}

		public static function release_inventory_lock($post_id): void
		{
			global $wpdb;
			$key = 'mptbm_inventory_' . absint($post_id);
			$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $key));
		}

		public static function get_available_quantity($post_id, $start_date, $start_time_formatted = '', $force_single_quantity = false, $trip_duration = 0, $return_datetime = '')
		{
			$total_quantity = $force_single_quantity ? 1 : (int) MP_Global_Function::get_post_info($post_id, 'mptbm_quantity', 1);
			$available_quantity = $total_quantity;

			if (!$start_date) {
				return $available_quantity;
			}

			$start_datetime = strtotime(trim($start_date . ' ' . (string) $start_time_formatted));
			if (!$start_datetime) {
				return $available_quantity;
			}

			$booking_interval_time = (int) MP_Global_Function::get_post_info($post_id, 'mptbm_booking_interval_time', 0);
			$buffer_seconds = max(0, $booking_interval_time * 60);
			$target_end = $start_datetime + max(60, absint($trip_duration));
			$requested_intervals = array(array($start_datetime, $target_end));
			$return_timestamp = $return_datetime ? strtotime($return_datetime) : false;
			if ($return_timestamp) {
				$requested_intervals[] = array($return_timestamp, $return_timestamp + max(60, absint($trip_duration)));
			}

			$query = new WP_Query([
				'post_type' => 'mptbm_booking',
				'post_status' => array('publish', 'pending', 'private', 'draft'),
				'posts_per_page' => -1,
				'meta_query' => [
					[
						'key' => 'mptbm_id',
						'value' => $post_id,
						'compare' => '='
					]
				]
			]);

			if ($query->have_posts()) {
				while ($query->have_posts()) {
					$query->the_post();
					$status = sanitize_key((string) get_post_meta(get_the_ID(), 'mptbm_order_status', true));
					if (in_array($status, array('cancelled', 'refunded', 'failed'), true)) {
						continue;
					}
					$booking_datetime = get_post_meta(get_the_ID(), 'mptbm_date', true);
					$booking_transport_quantity = (int) get_post_meta(get_the_ID(), 'mptbm_transport_quantity', true);
					$booking_transport_quantity = $booking_transport_quantity ?: 1;
					$booking_timestamp = strtotime($booking_datetime);
					$booking_duration = absint(get_post_meta(get_the_ID(), 'mptbm_duration', true));
					if (!$booking_duration) {
						$booking_duration = (int) round((float) get_post_meta(get_the_ID(), 'mptbm_fixed_hours', true) * HOUR_IN_SECONDS);
					}
					$existing_intervals = array();
					if ($booking_timestamp) {
						$existing_intervals[] = array($booking_timestamp, $booking_timestamp + max(60, $booking_duration));
					}
					$existing_return_date = (string) get_post_meta(get_the_ID(), 'mptbm_return_target_date', true);
					$existing_return_time = (string) get_post_meta(get_the_ID(), 'mptbm_return_target_time', true);
					$existing_return = $existing_return_date ? strtotime(trim($existing_return_date . ' ' . $existing_return_time)) : false;
					if ($existing_return) {
						$existing_intervals[] = array($existing_return, $existing_return + max(60, $booking_duration));
					}

					$overlaps = false;
					foreach ($requested_intervals as $requested) {
						foreach ($existing_intervals as $existing) {
							if ($requested[0] < ($existing[1] + $buffer_seconds) && $requested[1] > ($existing[0] - $buffer_seconds)) {
								$overlaps = true;
								break 2;
							}
						}
					}
					if ($overlaps) {
						$available_quantity -= $booking_transport_quantity;
					}
				}
			}
			wp_reset_postdata();

			return $available_quantity;
		}

		public static function get_all_dates($price_based = 'dynamic', $expire = false)
		{
			$all_posts = MPTBM_Query::query_transport_list($price_based);
			$all_dates = [];
			if ($all_posts->found_posts > 0) {
				$posts = $all_posts->posts;
				foreach ($posts as $post) {
					$post_id = $post->ID;
					$dates = MPTBM_Function::get_date($post_id, $expire);
					$all_dates = array_merge($all_dates, $dates);
				}
			}

			$all_dates = array_unique($all_dates);
			usort($all_dates, "MP_Global_Function::sort_date");
			return $all_dates;
		}

        //*************Price*********************************//
		public static function get_price($post_id, $distance = 1000, $duration = 3600, $start_place = '', $destination_place = '', $waiting_time = 0, $two_way = 1, $fixed_time = 0, $end_coords = null)
		{
			$price = 0;
			$search_context = self::get_search_context();
			self::$fixed_route_matches[(int) $post_id] = false;

            $operation_area_type = MP_Global_Function::get_post_info($post_id, 'mptbm_operation_area_type', '' );

			// Get price display type
			$price_display_type = MP_Global_Function::get_post_info($post_id, 'mptbm_price_display_type', 'normal');

			// If price display type is zero, return 0
			if ($price_display_type === 'zero') {
				return 0;
			}

			// If price display type is custom message, store it in a transient and return 0
			if ($price_display_type === 'custom_message') {
				return 0;
			}

			// Get price basis information
			$price_based = MP_Global_Function::get_post_info($post_id, 'mptbm_price_based');
			/**
			 * The price model the customer originally searched with. It normally comes
			 * from a transient the search step sets (MPTBM_Transport_Search), which means
			 * anything recomputing a fare OUTSIDE that flow - e.g. an admin re-pricing an
			 * existing booking - would match no branch below and silently get 0.
			 *
			 * The filter lets such callers supply the booking's own stored base for the
			 * duration of one calculation instead of writing to the shared transient,
			 * which is global and would corrupt a live customer's in-progress search.
			 *
			 * @param string $original_price_based Verified search pricing mode.
			 * @param int    $post_id              Transportation post being priced.
			 */
			$context_price_based = isset($search_context['price_based']) ? sanitize_key($search_context['price_based']) : '';
			$original_price_based = apply_filters('mptbm_original_price_based', $context_price_based ?: 'dynamic', $post_id);

			// If original price basis is fixed_hourly but current price basis is distance, return false
			if ($original_price_based === 'fixed_hourly' && $price_based === 'distance') {
				return false;
			}

			// Check if mptbm_distance_tier_enabled Distance Tier Pricing addon is active and apply tier pricing if available
			$tier_price = false;
			if (class_exists('MPTBM_Distance_Tier_Pricing')) {
				$tier_price = MPTBM_Distance_Tier_Pricing::calculate_tier_price(
					$post_id, $distance, $duration, $start_place, $destination_place, $waiting_time, $two_way, $fixed_time
				);
			}

			$price = 0.0;  // Initialize price as a float

			// If tier price is available, use it as the base price
			if ($tier_price !== false) {
				$price = $tier_price;
			} else {
				// Start the session only if it isn't active AND output hasn't started —
				// session_start() sets a cookie header, so calling it after headers are
				// sent emits a warning. If we can't start one, the code below degrades
				// gracefully (the session is only used as a recompute cache here).
				if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
					session_start();
				}

				$display_taxi_base_fare = MP_Global_Function::get_post_info($post_id, 'mptbm_display_taxi_base_fare_pricing' );
                if( $display_taxi_base_fare === 'on' ){
                    $initial_price = (float) MP_Global_Function::get_post_info($post_id, 'mptbm_initial_price');
                    $min_price = (float) MP_Global_Function::get_post_info($post_id, 'mptbm_min_price');
                    $return_min_price = MP_Global_Function::get_post_info($post_id, 'mptbm_min_price_return');
                    $waiting_price = (float) MP_Global_Function::get_post_info($post_id, 'mptbm_waiting_price', 0) * (float) $waiting_time;
                }else{
                    $initial_price = 0;
                    $min_price = 0;
                    $return_min_price = 0;
                    $waiting_price = 0;
                }

				if ($price_based == 'inclusive' && $original_price_based == 'dynamic') {
					$hour_price = (float) MP_Global_Function::get_post_info($post_id, 'mptbm_hour_price');
					$km_price = (float) MP_Global_Function::get_post_info($post_id, 'mptbm_km_price');
					$price = $hour_price * ((float) $duration / 3600) + $km_price * ((float) $distance / 1000);
				} elseif ($price_based == 'distance' && $original_price_based == 'dynamic') {
					$km_price = (float) MP_Global_Function::get_post_info($post_id, 'mptbm_km_price');
					$price = $km_price * ((float) $distance / 1000);
				} elseif ($price_based == 'duration' && ($original_price_based == 'fixed_hourly' || $original_price_based == 'dynamic')) {
					$hour_price = (float) MP_Global_Function::get_post_info($post_id, 'mptbm_hour_price');
					$price = $hour_price * ((float) $duration / 3600);
				} elseif ($price_based == 'distance_duration' && $original_price_based == 'dynamic') {
					$hour_price = (float) MP_Global_Function::get_post_info($post_id, 'mptbm_hour_price');
					$km_price = (float) MP_Global_Function::get_post_info($post_id, 'mptbm_km_price');
					$price = $hour_price * ((float) $duration / 3600) + $km_price * ((float) $distance / 1000);
				} elseif (($price_based == 'inclusive' || $price_based == 'fixed_hourly') && $original_price_based == 'fixed_hourly') {
					$hour_price = (float) MP_Global_Function::get_post_info($post_id, 'mptbm_hour_price');
					$price = $hour_price * (float) $fixed_time;
				} elseif ($price_based == 'distance' && $original_price_based == 'fixed_hourly') {
					$km_price = (float) MP_Global_Function::get_post_info($post_id, 'mptbm_km_price');
					$price = $km_price * ((float) $distance / 1000);
				} elseif (($price_based == 'inclusive' || $price_based == 'fixed_distance' || $price_based == 'fixed_map') && ($original_price_based == 'fixed_distance' || $original_price_based == 'fixed_map')) {
					$fixed_zone_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_fixed_map_route_price_info', []);

					$fixed_map_area_to_area_price_info = MP_Global_Function::get_post_info($post_id, 'mptbm_fixed_map_area_to_area_price_info', []);
					$operation_area_fixed_map_type = MP_Global_Function::get_post_info($post_id, 'mptbm_operation_area_fixed_map_type', 'zone_to_location');

					$found_zone_price = false;

                    if( $operation_area_fixed_map_type === 'zone_to_location' ){
                        if (!empty($fixed_zone_prices) && is_array($fixed_zone_prices)) {
							$pickup_lat = $search_context['start_coords']['latitude'] ?? null;
							$pickup_lng = $search_context['start_coords']['longitude'] ?? null;
							$dropoff_lat = $search_context['end_coords']['latitude'] ?? null;
							$dropoff_lng = $search_context['end_coords']['longitude'] ?? null;

                            if ($pickup_lat && $pickup_lng && $dropoff_lat && $dropoff_lng) {
                                $pickup_coords = ['lat' => $pickup_lat, 'lng' => $pickup_lng];
                                $dropoff_coords = ['lat' => $dropoff_lat, 'lng' => $dropoff_lng];

                                foreach ($fixed_zone_prices as $fixed_zone_price) {
                                    $start_location = $fixed_zone_price['start_location'] ?? '';
                                    $end_location = $fixed_zone_price['end_location'] ?? '';

                                    $start_match = self::is_point_in_fixed_zone($start_location, $pickup_coords);
                                    $end_match = self::is_point_in_fixed_zone($end_location, $dropoff_coords);

                                    if ($start_match && $end_match) {
                                        $price = (float) ($fixed_zone_price['price'] ?? 0);
                                        $found_zone_price = true;
										self::$fixed_route_matches[(int) $post_id] = true;
                                        break;
                                    }
                                }
                            }
                        }
                    }else{
                        if (!empty($fixed_map_area_to_area_price_info) && is_array($fixed_map_area_to_area_price_info)) {
							$area_to_area_pickup_lat = $search_context['start_coords']['latitude'] ?? null;
							$area_to_area_pickup_lng = $search_context['start_coords']['longitude'] ?? null;
							$area_to_area_dropoff_lat = $search_context['end_coords']['latitude'] ?? null;
							$area_to_area_dropoff_lng = $search_context['end_coords']['longitude'] ?? null;

                            if ($area_to_area_pickup_lat && $area_to_area_pickup_lng && $area_to_area_dropoff_lat && $area_to_area_dropoff_lng) {
                                $area_to_area_pickup_coords = ['lat' => $area_to_area_pickup_lat, 'lng' => $area_to_area_pickup_lng];
                                $area_to_area_dropoff_coords = ['lat' => $area_to_area_dropoff_lat, 'lng' => $area_to_area_dropoff_lng];

                                foreach ($fixed_map_area_to_area_price_info as $fixed_map_area_to_area_price) {
                                    $area_to_area_start_location = $fixed_map_area_to_area_price['start_location'] ?? '';
                                    $area_to_area_end_location = $fixed_map_area_to_area_price['end_location'] ?? '';

                                    $area_to_area_start_match = self::is_point_in_fixed_zone($area_to_area_start_location, $area_to_area_pickup_coords);
                                    $area_to_area_end_match = self::is_point_in_fixed_zone($area_to_area_end_location, $area_to_area_dropoff_coords);

                                    if ($area_to_area_start_match && $area_to_area_end_match ) {
                                        $price = (float) ($fixed_map_area_to_area_price['price'] ?? 0);
                                        $found_zone_price = true;
										self::$fixed_route_matches[(int) $post_id] = true;
                                        break;
                                    }
                                }
                            }
                        }
                    }


					if (!$found_zone_price) {

                        $area_based_pricing = get_post_meta( $post_id, 'mptbm_operation_area_pricing', array() );

						$match_type = isset($_SESSION['mptbm_fixed_distance_match_' . $post_id]) ? $_SESSION['mptbm_fixed_distance_match_' . $post_id] : 'partial';

                        $match_operation_area_id = isset($_SESSION['mptbm_operation_area_match_' . $post_id]) ? $_SESSION['mptbm_operation_area_match_' . $post_id] : '';


                        $area_price_data = [];
                        if( $match_operation_area_id && is_array( $area_based_pricing ) && !empty( $area_based_pricing[0] ) ){
                            $area_post_id = 'post_'.$match_operation_area_id;
                            $area_price_data = isset( $area_based_pricing[0][$area_post_id] ) ? $area_based_pricing[0][$area_post_id] : [];
                        }


                        $km_price = (float) MP_Global_Function::get_post_info($post_id, 'mptbm_km_price');
						$fixed_map_price = MP_Global_Function::get_post_info($post_id, 'mptbm_fixed_map_price');
                        $hour_price = (float) MP_Global_Function::get_post_info($post_id, 'mptbm_hour_price');

                        if( is_array( $area_price_data ) && !empty( $area_price_data ) ){
                            if(isset( $area_price_data['fixed'] ) &&  $area_price_data['fixed'] > 0){
                                $fixed_map_price = $area_price_data['fixed'];
                            }

                            if(isset( $area_price_data['per_km'] ) &&  $area_price_data['per_km'] > 0){
                                $km_price = $area_price_data['per_km'];
                            }

                            if(isset( $area_price_data['per_hour'] ) &&  $area_price_data['per_hour'] > 0){
                                $hour_price = $area_price_data['per_hour'];
                            }

                        }

						if ($match_type === 'full' && (float)$fixed_map_price > 0) {
							$price = (float) $fixed_map_price;
						} else {
							// Fallback to Distance + Duration
							$price = ($hour_price * ((float) $duration / 3600)) + ($km_price * ((float) $distance / 1000));
						}
					}
				}
				elseif (($price_based == 'inclusive' || $price_based == 'fixed_zone' || $price_based == 'fixed_zone_dropoff') && ($original_price_based == 'fixed_zone' || $original_price_based == 'fixed_zone_dropoff')) {
					$fixed_zone_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_fixed_zone_price_info', []);

					if (!empty($fixed_zone_prices) && is_array($fixed_zone_prices)) {
						// Use original_price_based to determine the mode (pickup vs dropoff)
						$mode = $original_price_based ?: $price_based;

						foreach ($fixed_zone_prices as $index => $fixed_zone_price) {
							$start_location = $fixed_zone_price['start_location'] ?? '';
							$end_location = $fixed_zone_price['end_location'] ?? '';

							if ($mode === 'fixed_zone_dropoff') {
								// For dropoff: destination_place must match end_location exactly
								if ($destination_place !== $end_location) {
									continue;
								}
								// Check if start (pickup) is in the zone using geo-fence
								// In dropoff mode, end_coords parameter contains the searched pickup coordinates
								if (!empty($end_coords)) {
									$is_in_zone = self::is_point_in_fixed_zone($start_location, $end_coords);
									if ($is_in_zone) {
										$price = (float) ($fixed_zone_price['price'] ?? 0);
										break;
									}
								}
							} else {
								// For pickup (fixed_zone): start_place must match start_location exactly
								if ($start_place !== $start_location) {
									continue;
								}
								// Check if destination is in the end zone using geo-fence
								if (!empty($end_coords)) {
									$is_in_zone = self::is_point_in_fixed_zone($end_location, $end_coords);
									if ($is_in_zone) {
										$price = (float) ($fixed_zone_price['price'] ?? 0);
										break;
									}
								} else {
									if ($destination_place === $end_location) {
										$price = (float) ($fixed_zone_price['price'] ?? 0);
										break;
									}
								}
							}
						}
					}
				}
				elseif ((trim($price_based) == 'inclusive' || trim($price_based) == 'manual') && trim($original_price_based) == 'manual') {
					$manual_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_manual_price_info', []);
					$term_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_terms_price_info', []);
					$manual_prices = array_merge($manual_prices, $term_prices);

					if (sizeof($manual_prices) > 0) {
						foreach ($manual_prices as $manual_price) {
							$start_location = array_key_exists('start_location', $manual_price) ? $manual_price['start_location'] : '';
							$end_location = array_key_exists('end_location', $manual_price) ? $manual_price['end_location'] : '';
							if ($start_place == $start_location && $destination_place == $end_location) {
								$price = (float) ($manual_price['price'] ?? 0);
							}
						}
					}
				}

				if ($initial_price > 0) {
					$price += $initial_price;
				}

				// Only apply minimum price if we're not in a fixed_hourly to distance conversion
				if ($min_price > 0 && $min_price > $price && !($original_price_based == 'fixed_hourly' && $price_based == 'distance')) {
					$price = $min_price;

					if ($return_min_price > 0 && $two_way > 1) {
						$price = $price + $return_min_price;
					} elseif ($return_min_price == '' && $two_way > 1) {
						$price = $price * 2;
					}
				} elseif ($two_way > 1) {
					$price = $price * 2;
				}

				if ($waiting_time > 0) {
					$price += $waiting_price;
				}

				if ($two_way > 1) {
					$return_discount = MP_Global_Function::get_post_info($post_id, 'mptbm_return_discount', 0);

					if (is_string($return_discount) && strpos($return_discount, '%') !== false) {
						$percentage = floatval(rtrim($return_discount, '%'));
						$discount_amounts = ($percentage / 100) * $price;
						$price -= $discount_amounts;
					} elseif (is_numeric($return_discount)) {
						$price -= (float)$return_discount;
					}
				}
			}

			// Now apply datewise discount if addon is active
			if (class_exists('MPTBM_Datewise_Discount_Addon')) {
				$selected_start_date = $search_context['start_date'] ?? '';
				$selected_start_time = $search_context['start_time'] ?? '';
				$datetime_discount_applied = false;
				$day_discount_applied = false;
				$date_range_matched = false;
				$original_price = $price;

				// Get toggle states for both discount types
				$datetime_discount_enabled = get_post_meta($post_id, 'mptbm_datetime_discount_enabled', true);
				$day_discount_enabled = get_post_meta($post_id, 'mptbm_day_discount_enabled', true);

				if (strpos($selected_start_time, ':') !== false) {
					// Already formatted as H:i (or H:i:s) by the search form's data-time attribute; keep the real minutes.
					$time_parts = explode(':', $selected_start_time);
					$selected_start_time = sprintf('%02d:%02d', (int) $time_parts[0], (int) ($time_parts[1] ?? 0));
				} elseif (strpos($selected_start_time, '.') !== false) {
					$selected_start_time = sprintf('%02d:%02d', floor($selected_start_time), ($selected_start_time - floor($selected_start_time)) * 60);
				} else {
					$selected_start_time = sprintf('%02d:00', $selected_start_time);
				}

				// Apply Date and Time Wise Discount if enabled
				if ($datetime_discount_enabled === 'on') {
					$discounts = MP_Global_Function::get_post_info($post_id, 'mptbm_discounts', []);
					if (!empty($discounts)) {
						foreach ($discounts as $discount) {
							$start_date = isset($discount['start_date']) ? date('Y-m-d', strtotime($discount['start_date'])) : '';
							$end_date = isset($discount['end_date']) ? date('Y-m-d', strtotime($discount['end_date'])) : '';
							$time_slots = isset($discount['time_slots']) ? $discount['time_slots'] : [];

							if (strtotime($selected_start_date) >= strtotime($start_date) &&
								strtotime($selected_start_date) <= strtotime($end_date)) {

								$date_range_matched = true;

								$time_slot_matched = false;
								foreach ($time_slots as $slot) {
									$start_time = isset($slot['start_time']) ? sanitize_text_field($slot['start_time']) : '';
									$end_time = isset($slot['end_time']) ? sanitize_text_field($slot['end_time']) : '';

									if (strpos($start_time, '.') !== false) {
										$start_time = sprintf('%02d:%02d', floor($start_time), ($start_time - floor($start_time)) * 60);
									}
									if (strpos($end_time, '.') !== false) {
										$end_time = sprintf('%02d:%02d', floor($end_time), ($end_time - floor($end_time)) * 60);
									}

									if (strtotime($start_time) > strtotime($end_time)) {
										if (strtotime($selected_start_time) >= strtotime($start_time) ||
											strtotime($selected_start_time) <= strtotime($end_time)) {

											$percentage = floatval(rtrim($slot['percentage'], '%'));
											$type = isset($slot['type']) ? $slot['type'] : 'increase';

											$discount_amount = ($percentage / 100) * $original_price;

											if ($type === 'decrease') {
												$price -= abs($discount_amount);
											} else {
												$price += $discount_amount;
											}
											$datetime_discount_applied = true;
											$time_slot_matched = true;
										}
									} else {
										if (strtotime($selected_start_time) >= strtotime($start_time) &&
											strtotime($selected_start_time) <= strtotime($end_time)) {

											$percentage = floatval(rtrim($slot['percentage'], '%'));
											$type = isset($slot['type']) ? $slot['type'] : 'increase';

											$discount_amount = ($percentage / 100) * $original_price;

											if ($type === 'decrease') {
												$price -= abs($discount_amount);
											} else {
												$price += $discount_amount;
											}
											$datetime_discount_applied = true;
											$time_slot_matched = true;
										}
									}
								}

								if (!empty($time_slots) && !$time_slot_matched) {
									continue;
								}
							}
						}
					}
				}

				// Apply Day-based discount if enabled and no date-range discount was applied
				// Check if addon is handling both date-time and day-based discounts
				$skip_day_discount = apply_filters('mptbm_skip_day_discount_when_both_enabled', false, $post_id);

				if ($day_discount_enabled === 'on' && !empty($selected_start_date) && !$date_range_matched && !$skip_day_discount) {
					$day_of_week = strtolower(date('l', strtotime($selected_start_date)));

					// Get day-based discounts
					$day_discounts = get_post_meta($post_id, 'mptbm_day_discounts', true);
					if (is_array($day_discounts) && isset($day_discounts[$day_of_week]) &&
						$day_discounts[$day_of_week]['status'] === 'active') {

						$day_data = $day_discounts[$day_of_week];
						$amount = floatval($day_data['amount']);

						if ($amount > 0) {
							if ($day_data['amount_type'] === 'percentage') {
								$discount_amount = ($amount / 100) * $original_price;
							} else {
								$discount_amount = $amount;
							}

							if ($day_data['type'] === 'decrease') {
								$price -= $discount_amount;
							} else {
								$price += $discount_amount;
							}
							$day_discount_applied = true;
						}
					}
				}

				// Weather and Traffic pricing is now handled by the filter below to avoid double application
			}

            if( !empty( $operation_area_type ) && $operation_area_type === 'geo-fence-operation-area-type' ){
                if (isset($_SESSION['geo_fence_post_' . $post_id])) {
                    $session_data = $_SESSION['geo_fence_post_' . $post_id];
                    if (isset($session_data[0])) {
                        if (isset($session_data[1]) && $session_data[1] == 'geo-fence-fixed-price') {
                            $price += (float) $session_data[0];
                        } else {
                            $price += ((float) $session_data[0] / 100) * $price;
                        }
                    }
                }
            }


			session_write_close();

			// Apply filters for dynamic pricing (weather, traffic, etc.) if addons are available
			if (has_filter('mptbm_calculate_price')) {
				$extra_data = array();

				// Try to get coordinates from various sources for weather/traffic pricing
				$pickup_lat = $search_context['start_coords']['latitude'] ?? null;
				$pickup_lng = $search_context['start_coords']['longitude'] ?? null;
				$drop_lat = $search_context['end_coords']['latitude'] ?? null;
				$drop_lng = $search_context['end_coords']['longitude'] ?? null;

				// Fallback to session data
				if (empty($pickup_lat) || empty($pickup_lng)) {
					$pickup_lat = isset($_SESSION['pickup_lat']) ? $_SESSION['pickup_lat'] : '';
					$pickup_lng = isset($_SESSION['pickup_lng']) ? $_SESSION['pickup_lng'] : '';
				}
				if (empty($drop_lat) || empty($drop_lng)) {
					$drop_lat = isset($_SESSION['drop_lat']) ? $_SESSION['drop_lat'] : '';
					$drop_lng = isset($_SESSION['drop_lng']) ? $_SESSION['drop_lng'] : '';
				}

				// Final fallback to POST data (for AJAX requests)
				if (empty($pickup_lat) || empty($pickup_lng)) {
					$pickup_lat = isset($_POST['origin_lat']) ? $_POST['origin_lat'] : (isset($_POST['pickup_lat']) ? $_POST['pickup_lat'] : '');
					$pickup_lng = isset($_POST['origin_lng']) ? $_POST['origin_lng'] : (isset($_POST['pickup_lng']) ? $_POST['pickup_lng'] : '');
				}
				if (empty($drop_lat) || empty($drop_lng)) {
					$drop_lat = isset($_POST['dest_lat']) ? $_POST['dest_lat'] : (isset($_POST['drop_lat']) ? $_POST['drop_lat'] : '');
					$drop_lng = isset($_POST['dest_lng']) ? $_POST['dest_lng'] : (isset($_POST['drop_lng']) ? $_POST['drop_lng'] : '');
				}

				if (!empty($pickup_lat) && !empty($pickup_lng)) {
					$extra_data['origin_lat'] = floatval($pickup_lat);
					$extra_data['origin_lng'] = floatval($pickup_lng);
				}
				if (!empty($drop_lat) && !empty($drop_lng)) {
					$extra_data['dest_lat'] = floatval($drop_lat);
					$extra_data['dest_lng'] = floatval($drop_lng);
				}

				$selected_start_date = $search_context['start_date'] ?? '';
				$selected_start_time = $search_context['start_time'] ?? '';

				$price = apply_filters('mptbm_calculate_price', $price, $post_id, $selected_start_date, $selected_start_time, $extra_data);
			}




			return (float) $price;

		}

		/**
		 * Every extra service a transportation offers, as name => price rows.
		 *
		 * Resolves the same way the booking form does: services can live on the vehicle
		 * itself or on a shared service post referenced by mptbm_extra_services_id, and
		 * an empty array is returned when the vehicle has them switched off.
		 *
		 * @return array<int,array{service_name:string,service_price:float}>
		 */
		public static function get_available_extra_services($post_id): array
		{
			$display_extra_services = MP_Global_Function::get_post_info($post_id, 'display_mptbm_extra_services', 'on');
			if ($display_extra_services != 'on') {
				return [];
			}
			$service_id = MP_Global_Function::get_post_info($post_id, 'mptbm_extra_services_id', $post_id);
			$extra_services = MP_Global_Function::get_post_info($service_id, 'mptbm_extra_service_infos', []);
			if (!is_array($extra_services)) {
				return [];
			}
			$services = [];
			foreach ($extra_services as $service) {
				$name = array_key_exists('service_name', $service) ? $service['service_name'] : '';
				if ($name === '') {
					continue;
				}
				$services[] = [
					'service_name'  => $name,
					'service_price' => (float) (array_key_exists('service_price', $service) ? $service['service_price'] : 0),
				];
			}
			return $services;
		}
		public static function get_extra_service_price_by_name($post_id, $service_name)
		{
			foreach (self::get_available_extra_services($post_id) as $service) {
				if ($service['service_name'] == $service_name) {
					return $service['service_price'];
				}
			}
			return 0;
		}
		/**
		 * Check if coordinates fall within a fixed_zone end location (operation area polygon or location term radius)
		 * 
		 * @param string $end_location The end_location from fixed_zone config (post_XX or term_XX)
		 * @param array $end_coords Array with 'latitude' and 'longitude' keys
		 * @return bool True if coordinates are within the zone
		 */
		public static function is_point_in_fixed_zone($end_location, $end_coords) {
			if (empty($end_location) || empty($end_coords)) {
				return false;
			}
			
			// Normalize coordinates format
			$lat = isset($end_coords['latitude']) ? floatval($end_coords['latitude']) : (isset($end_coords['lat']) ? floatval($end_coords['lat']) : 0);
			$lng = isset($end_coords['longitude']) ? floatval($end_coords['longitude']) : (isset($end_coords['lng']) ? floatval($end_coords['lng']) : 0);
			
			if ($lat == 0 && $lng == 0) {
				return false;
			}
			
			// Check if end_location is an operation area (post_XX)
			if (strpos($end_location, 'post_') === 0) {
				$area_id = absint(str_replace('post_', '', $end_location));
				$operation_area_type = get_post_meta($area_id, 'mptbm-operation-type', true);
				
				// Get polygon coordinates
				$coord_key = 'mptbm-coordinates-three';
				if ($operation_area_type === 'geo-matched-operation-area-type') {
					$coord_key = 'mptbm-coordinates-four';
				} elseif ($operation_area_type === 'geo-fence-operation-area-type') {
					$coord_key = 'mptbm-coordinates-one';
				}
				
				$flat_coords = get_post_meta($area_id, $coord_key, true);
				
				if (!is_array($flat_coords) || count($flat_coords) < 6) {
					return false;
				}
				
				// Convert flat array to polygon format
				$polygon = [];
				for ($i = 0; $i < count($flat_coords); $i += 2) {
					$polygon[] = [
						'latitude' => floatval($flat_coords[$i]),
						'longitude' => floatval($flat_coords[$i + 1])
					];
				}
				
				// Point in polygon check
				return self::point_in_polygon($lat, $lng, $polygon);
			}
			// Check if end_location is a location term (term_XX)
			elseif (strpos($end_location, 'term_') === 0) {
				$term_id = absint(str_replace('term_', '', $end_location));
				$geo_location = get_term_meta($term_id, 'mptbm_geo_location', true);
				
				if (empty($geo_location)) {
					return false;
				}
				
				// Parse term geo location (format: "lat,lng")
				$term_coords = explode(',', $geo_location);
				if (count($term_coords) !== 2) {
					return false;
				}
				
				$term_lat = floatval(trim($term_coords[0]));
				$term_lng = floatval(trim($term_coords[1]));
				
				// Calculate distance between points (in km)
				$distance = self::haversine_distance($lat, $lng, $term_lat, $term_lng);
				
				// Consider within 1km radius as "within zone" for location terms
				$radius_km = 1;
				return $distance <= $radius_km;
			}
			
			return false;
		}

		public static function get_base_price_settings($post_id) {

            $taxi_base_location_pricing = MP_Global_Function::get_post_info( $post_id, 'mptbm_display_taxi_base_location_pricing', 'off' );
            $settings = [
                'location_id'    => '',
                'coords'         => '',
                'price_km'       => 0,
                'price_hour'     => 0,
                'threshold'      => 0,
                'charge_pickup'  => 'no',
                'charge_dropoff' => 'no',
            ];

            if( $taxi_base_location_pricing === 'on' ){
                $location_id = MP_Global_Function::get_post_info($post_id, 'mptbm_base_price_location');
                $coords = '';
                if ($location_id) {
                    $coords = get_term_meta($location_id, 'mptbm_geo_location', true);
                }

                $settings = [
                    'location_id' => $location_id,
                    'coords'      => $coords,
                    'price_km'    => (float)MP_Global_Function::get_post_info($post_id, 'mptbm_base_price_km', 0),
                    'price_hour'  => (float)MP_Global_Function::get_post_info($post_id, 'mptbm_base_price_hour', 0),
                    'threshold'   => (float)MP_Global_Function::get_post_info($post_id, 'mptbm_base_min_threshold', 0),
                    'charge_pickup' => MP_Global_Function::get_post_info($post_id, 'mptbm_charge_base_pickup', 'no'),
                    'charge_dropoff' => MP_Global_Function::get_post_info($post_id, 'mptbm_charge_base_dropoff', 'no'),
                ];
            }

			return $settings;
		}
		
		/**
		 * Point in polygon algorithm (ray casting)
		 */
		private static function point_in_polygon($lat, $lng, $polygon) {
			$inside = false;
			$n = count($polygon);
			
			for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
				$xi = $polygon[$i]['latitude'];
				$yi = $polygon[$i]['longitude'];
				$xj = $polygon[$j]['latitude'];
				$yj = $polygon[$j]['longitude'];
				
				$intersect = (($yi > $lng) != ($yj > $lng)) &&
					($lat < ($xj - $xi) * ($lng - $yi) / ($yj - $yi + 0.0000001) + $xi);
				
				if ($intersect) {
					$inside = !$inside;
				}
			}
			
			return $inside;
		}
		
		/**
		 * Calculate distance between two points using Haversine formula
		 */
		private static function haversine_distance($lat1, $lng1, $lat2, $lng2) {
			$earth_radius = 6371; // km
			
			$lat1_rad = deg2rad($lat1);
			$lat2_rad = deg2rad($lat2);
			$delta_lat = deg2rad($lat2 - $lat1);
			$delta_lng = deg2rad($lng2 - $lng1);
			
			$a = sin($delta_lat / 2) * sin($delta_lat / 2) +
				cos($lat1_rad) * cos($lat2_rad) *
				sin($delta_lng / 2) * sin($delta_lng / 2);
			
			$c = 2 * atan2(sqrt($a), sqrt(1 - $a));
			
			return $earth_radius * $c;
		}
		
		//************Location*******************//
		public static function location_exit($post_id, $start_place, $destination_place, $end_coords = null)
		{
			$price_based = MP_Global_Function::get_post_info($post_id, 'mptbm_price_based');
			$search_context = self::get_search_context();
				$original_price_based = $search_context['price_based'] ?? 'dynamic';

			if ($price_based == 'manual') {
				$manual_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_manual_price_info', []);
				$terms_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_terms_price_info', []);
				$manual_prices = array_merge($manual_prices, $terms_prices);
				if (sizeof($manual_prices) > 0) {
					$exit = 0;
					foreach ($manual_prices as $manual_price) {
						$start_location = array_key_exists('start_location', $manual_price) ? $manual_price['start_location'] : '';
						$end_location = array_key_exists('end_location', $manual_price) ? $manual_price['end_location'] : '';
						if ($start_place == $start_location && $destination_place == $end_location) {
							$exit = 1;
						}
					}
					return $exit > 0;
				}
				return false;
			} elseif ($price_based == 'fixed_zone' || $price_based == 'fixed_zone_dropoff') {
				$fixed_zone_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_fixed_zone_price_info', []);
				
				// Use original_price_based to determine the mode (pickup vs dropoff)
				$mode = $original_price_based ?: $price_based;
				
				if (!empty($fixed_zone_prices) && is_array($fixed_zone_prices)) {
					foreach ($fixed_zone_prices as $index => $fixed_zone_price) {
						$start_location = $fixed_zone_price['start_location'] ?? '';
						$end_location = $fixed_zone_price['end_location'] ?? '';
						
						if ($mode === 'fixed_zone_dropoff') {
							// Destination must match end_location
							if ($destination_place !== $end_location) {
								continue;
							}
							// Start must be in zone
							if (!empty($end_coords)) {
								$is_in_zone = self::is_point_in_fixed_zone($start_location, $end_coords);
								if ($is_in_zone) {
									return true;
								}
							}
						} else {
							// Start location must match exactly
							if ($start_place !== $start_location) {
								continue;
							}
							
							// For end_location, we need to check if destination coordinates fall within the zone
							if (!empty($end_coords)) {
								$is_in_zone = self::is_point_in_fixed_zone($end_location, $end_coords);
								if ($is_in_zone) {
									return true;
								}
							} else {
								if ($destination_place === $end_location) {
									return true;
								}
							}
						}
					}
				}
				return false; // No matching fixed zone price found
			}
			// For other pricing modes (dynamic, fixed_distance, fixed_hourly, etc.), return true by default
			// as location_exit check is not required for those modes
			return true;
		}
		public static function get_all_start_location($post_id = '', $price_based = 'manual')
		{
			$all_location = [];
			
			$should_include_manual = ($price_based === 'manual' || $price_based === '');
			$should_include_fixed_zone = ($price_based === 'fixed_zone' || $price_based === 'fixed_zone_dropoff');

			$collect_locations = function($prices) use (&$all_location, $price_based) {
				if (sizeof($prices) > 0) {
					foreach ($prices as $price_row) {
						$location = ($price_based === 'fixed_zone_dropoff') ? ($price_row['end_location'] ?? '') : ($price_row['start_location'] ?? '');
						
						if (!$location) {
							continue;
						}

						if ($price_based === 'fixed_zone' || $price_based === 'fixed_zone_dropoff') {
							// Only allow taxonomy locations with geo-location flag; skip operation areas
							if (strpos($location, 'term_') !== 0) {
								continue;
							}
							$term_id = absint(str_replace('term_', '', $location));
							$has_geo = get_term_meta($term_id, 'mptbm_geo_location', true);
							if (!$has_geo) {
								continue;
							}
						}

						$all_location[] = $location;
					}
				}
			};

			if ($post_id && $post_id > 0) {
				if ($should_include_manual) {
				$manual_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_manual_price_info', []);
					$terms_location_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_terms_price_info', []);
					$collect_locations($manual_prices);
					$collect_locations($terms_location_prices);
				}
				if ($should_include_fixed_zone) {
					$fixed_zone_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_fixed_zone_price_info', []);
					$collect_locations($fixed_zone_prices);
				}
			} else {
				$query_price_based = $price_based ? $price_based : 'manual';
				$all_posts = MPTBM_Query::query_transport_list($query_price_based);
				if ($all_posts->found_posts > 0) {
					$posts = $all_posts->posts;
					foreach ($posts as $post) {
						$post_id = $post->ID;
						if ($should_include_manual) {
						$manual_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_manual_price_info', []);
						$terms_location_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_terms_price_info', []);
							$collect_locations($manual_prices);
							$collect_locations($terms_location_prices);
						}
						if ($should_include_fixed_zone) {
							$fixed_zone_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_fixed_zone_price_info', []);
							$collect_locations($fixed_zone_prices);
						}
					}
				}
			}
			return array_unique($all_location);
		}
		public static function get_end_location($start_place, $post_id = '', $price_based = 'manual')
		{
			$all_location = [];
			$should_include_manual = ($price_based === 'manual' || $price_based === '');
			$should_include_fixed_zone = ($price_based === 'fixed_zone');

			$collect_locations = function($prices) use (&$all_location, $start_place, $price_based) {
				if (sizeof($prices) > 0) {
					foreach ($prices as $price_row) {
						$start_location = array_key_exists('start_location', $price_row) ? $price_row['start_location'] : '';
						$end_location = array_key_exists('end_location', $price_row) ? $price_row['end_location'] : '';
						if ($start_location && $end_location && $start_location == $start_place) {
							if ($price_based === 'fixed_zone') {
								// Only allow taxonomy locations with geo-location flag; skip operation areas
								if (strpos($end_location, 'term_') !== 0) {
									continue;
								}
								$term_id = absint(str_replace('term_', '', $end_location));
								$has_geo = get_term_meta($term_id, 'mptbm_geo_location', true);
								if (!$has_geo) {
									continue;
								}
							}
							$all_location[] = $end_location;
						}
					}
				}
			};

			if ($post_id && $post_id > 0) {
				if ($should_include_manual) {
					$manual_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_manual_price_info', []);
					$collect_locations($manual_prices);
				}
				if ($should_include_fixed_zone) {
					$fixed_zone_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_fixed_zone_price_info', []);
					$collect_locations($fixed_zone_prices);
				}
			} else {
				$query_price_based = $price_based ? $price_based : 'manual';
				$all_posts = MPTBM_Query::query_transport_list($query_price_based);
				if ($all_posts->found_posts > 0) {
					$posts = $all_posts->posts;
					foreach ($posts as $post) {
						$post_id = $post->ID;
						if ($should_include_manual) {
						$manual_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_manual_price_info', []);
						$terms_location_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_terms_price_info', []);
							$collect_locations($manual_prices);
							$collect_locations($terms_location_prices);
						}
						if ($should_include_fixed_zone) {
							$fixed_zone_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_fixed_zone_price_info', []);
							$collect_locations($fixed_zone_prices);
						}
					}
				}
			}
			return array_unique($all_location);
		}
		// Default ECAB Taxi Booking checkout fields
		public static function get_default_checkout_fields() {
			return [
				'flight_no' => [
					'label' => __('Flight No', 'ecab-taxi-booking-manager'),
					'type' => 'text',
					'required' => false,
					'show' => true,
					'placeholder' => __('Enter your flight number', 'ecab-taxi-booking-manager'),
				],
				'passport_no' => [
					'label' => __('Passport No', 'ecab-taxi-booking-manager'),
					'type' => 'text',
					'required' => false,
					'show' => true,
					'placeholder' => __('Enter your passport number', 'ecab-taxi-booking-manager'),
				],
				'pickup_location' => [
					'label' => __('Pickup Location', 'ecab-taxi-booking-manager'),
					'type' => 'text',
					'required' => true,
					'show' => true,
					'placeholder' => __('Enter pickup location', 'ecab-taxi-booking-manager'),
				],
				'dropoff_location' => [
					'label' => __('Drop-off Location', 'ecab-taxi-booking-manager'),
					'type' => 'text',
					'required' => true,
					'show' => true,
					'placeholder' => __('Enter drop-off location', 'ecab-taxi-booking-manager'),
				],
				'pickup_datetime' => [
					'label' => __('Pickup Date & Time', 'ecab-taxi-booking-manager'),
					'type' => 'datetime-local',
					'required' => true,
					'show' => true,
					'placeholder' => __('Select pickup date and time', 'ecab-taxi-booking-manager'),
				],
				'num_passengers' => [
					'label' => __('Number of Passengers', 'ecab-taxi-booking-manager'),
					'type' => 'number',
					'required' => true,
					'show' => true,
					'placeholder' => __('Enter number of passengers', 'ecab-taxi-booking-manager'),
				],
				'luggage_details' => [
					'label' => __('Luggage Details', 'ecab-taxi-booking-manager'),
					'type' => 'text',
					'required' => false,
					'show' => true,
					'placeholder' => __('Enter luggage details', 'ecab-taxi-booking-manager'),
				],
				'contact_phone' => [
					'label' => __('Contact Phone', 'ecab-taxi-booking-manager'),
					'type' => 'text',
					'required' => true,
					'show' => true,
					'placeholder' => __('Enter contact phone', 'ecab-taxi-booking-manager'),
				],
				'special_instructions' => [
					'label' => __('Special Instructions', 'ecab-taxi-booking-manager'),
					'type' => 'textarea',
					'required' => false,
					'show' => true,
					'placeholder' => __('Any special instructions?', 'ecab-taxi-booking-manager'),
				],
				'email_address' => [
					'label' => __('Email Address', 'ecab-taxi-booking-manager'),
					'type' => 'email',
					'required' => true,
					'show' => true,
					'placeholder' => __('Enter your email address', 'ecab-taxi-booking-manager'),
				],
			];
		}

		/**
		 * Get current weather condition for pricing calculation
		 *
		 * @param float $lat Latitude
		 * @param float $lng Longitude
		 * @param string $api_key OpenWeatherMap API key
		 * @return string Weather condition key
		 */
		public static function get_weather_condition_for_pricing($lat, $lng, $api_key) {
			if (empty($api_key) || empty($lat) || empty($lng)) {
				return '';
			}
			
			// Check cache first (cache for 15 minutes)
			$cache_key = 'weather_pricing_' . md5($lat . '_' . $lng);
			$cached_weather = get_transient($cache_key);
			if ($cached_weather !== false) {
				return $cached_weather;
			}
			
			$url = "https://api.openweathermap.org/data/2.5/weather?lat={$lat}&lon={$lng}&appid={$api_key}&units=metric";
			
			$response = wp_remote_get($url, array(
				'timeout' => 10,
				'headers' => array(
					'User-Agent' => 'WordPress Taxi Pricing Plugin'
				)
			));
			
			if (is_wp_error($response)) {
				return '';
			}
			
			$body = wp_remote_retrieve_body($response);
			$data = json_decode($body, true);
			
			if (empty($data) || !isset($data['weather'][0]['main'])) {
				return '';
			}
			
			$weather_main = strtolower($data['weather'][0]['main']);
			$weather_desc = strtolower($data['weather'][0]['description']);
			$temp = isset($data['main']['temp']) ? $data['main']['temp'] : 20;
			$wind_speed = isset($data['wind']['speed']) ? $data['wind']['speed'] * 3.6 : 0; // Convert m/s to km/h
			
			$condition = '';
			
			// Determine weather condition based on API response
			switch ($weather_main) {
				case 'rain':
					$condition = (strpos($weather_desc, 'heavy') !== false) ? 'heavy_rain' : 'rain';
					break;
				case 'drizzle':
					$condition = 'rain';
					break;
				case 'snow':
					$condition = 'snow';
					break;
				case 'fog':
				case 'mist':
				case 'haze':
					$condition = 'fog';
					break;
				case 'thunderstorm':
					$condition = 'storm';
					break;
				case 'clear':
				case 'clouds':
					// Check for extreme temperatures
					if ($temp < 0) {
						$condition = 'extreme_cold';
					} elseif ($temp > 35) {
						$condition = 'extreme_heat';
					}
					break;
			}
			
			// Check for high wind regardless of other conditions
			if ($wind_speed > 25) {
				$condition = 'high_wind';
			}
			
			// Cache the result for 15 minutes
			set_transient($cache_key, $condition, 15 * MINUTE_IN_SECONDS);
			
			return $condition;
		}

		/**
		 * Get traffic condition based on route duration for pricing calculation
		 *
		 * @param float $origin_lat Origin latitude
		 * @param float $origin_lng Origin longitude
		 * @param float $dest_lat Destination latitude
		 * @param float $dest_lng Destination longitude
		 * @param string $google_api_key Google Maps API key
		 * @return array Traffic condition data
		 */
		public static function get_traffic_condition_for_pricing($origin_lat, $origin_lng, $dest_lat, $dest_lng, $google_api_key) {
						
			if (empty($google_api_key) || empty($origin_lat) || empty($origin_lng) || empty($dest_lat) || empty($dest_lng)) {
				return array('condition' => '', 'multiplier' => 1.0);
			}
			
			// Check cache first (cache for 5 minutes)
			$cache_key = 'traffic_data_' . md5($origin_lat . $origin_lng . $dest_lat . $dest_lng);
			$cached_data = get_transient($cache_key);
			if ($cached_data !== false) {
				return $cached_data;
			}
			
			// Build Google Maps API URLs
			$base_params = "origin={$origin_lat},{$origin_lng}&destination={$dest_lat},{$dest_lng}&key={$google_api_key}";
			$url_with_traffic = "https://maps.googleapis.com/maps/api/directions/json?{$base_params}&departure_time=now&traffic_model=best_guess";
			$url_without_traffic = "https://maps.googleapis.com/maps/api/directions/json?{$base_params}";
			
			
			
			// Get both responses
			$response_with_traffic = wp_remote_get($url_with_traffic);
			$response_without_traffic = wp_remote_get($url_without_traffic);
			
			if (is_wp_error($response_with_traffic)) {
				return array('condition' => '', 'multiplier' => 1.0);
			}
			
			if (is_wp_error($response_without_traffic)) {
				return array('condition' => '', 'multiplier' => 1.0);
			}
			
			$body_with_traffic = wp_remote_retrieve_body($response_with_traffic);
			$body_without_traffic = wp_remote_retrieve_body($response_without_traffic);
			
			
			
			$data_with_traffic = json_decode($body_with_traffic, true);
			$data_without_traffic = json_decode($body_without_traffic, true);
			
			if (!isset($data_with_traffic['routes'][0]['legs'][0]) || !isset($data_without_traffic['routes'][0]['legs'][0])) {
				return array('condition' => '', 'multiplier' => 1.0);
			}
			
			// Get durations
			$duration_with_traffic = $data_with_traffic['routes'][0]['legs'][0]['duration_in_traffic']['value'] ?? 
									$data_with_traffic['routes'][0]['legs'][0]['duration']['value'];
			$duration_without_traffic = $data_without_traffic['routes'][0]['legs'][0]['duration']['value'];
			
			
			
			// Calculate traffic multiplier
			$traffic_multiplier = $duration_with_traffic / $duration_without_traffic;
			
			// Determine traffic condition
			$condition = '';
			if ($traffic_multiplier >= 2.0) {
				$condition = 'severe';
			} elseif ($traffic_multiplier >= 1.5) {
				$condition = 'heavy';
			} elseif ($traffic_multiplier >= 1.2) {
				$condition = 'moderate';
			} else {
				$condition = 'light';
			}
			
			$result = array(
				'condition' => $condition,
				'multiplier' => $traffic_multiplier
			);
			
			
			// Cache the result for 5 minutes
			set_transient($cache_key, $result, 300);
			
			return $result;
		}

		/**
		 * Whitelist Google Maps API script from CookieAdmin blocking
		 * 
		 * @param string $tag The script tag
		 * @param string $handle The script handle
		 * @param string $src The script source
		 * @return string The modified script tag
		 */
		public static function whitelist_google_maps_script($tag, $handle, $src) {
			if ($handle === 'mptbm_map_api') {
				// Restore the script type to text/javascript if it was changed to text/plain
				$tag = str_replace('type="text/plain"', 'type="text/javascript"', $tag);
				
				// Remove CookieAdmin category attributes that cause blocking
				$tag = preg_replace('/data-cookieadmin-category="[^"]*"/', '', $tag);
			}
			return $tag;
		}

		
		// Helper to calculate distance server-side
		public static function get_server_distance($start_lat, $start_lng, $end_lat, $end_lng) {
			if (!$start_lat || !$start_lng || !$end_lat || !$end_lng) {
				return false;
			}
			
			// Try Google Maps Distance Matrix API first if Key exists
			$api_key = MP_Global_Function::get_settings('mptbm_map_api_settings', 'map_api_key');
			if ($api_key) {
				$url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins={$start_lat},{$start_lng}&destinations={$end_lat},{$end_lng}&mode=driving&key={$api_key}";
				$response = wp_remote_get($url);
				if (!is_wp_error($response)) {
					$body = wp_remote_retrieve_body($response);
					$data = json_decode($body, true);
					if (isset($data['rows'][0]['elements'][0]['status']) && $data['rows'][0]['elements'][0]['status'] === 'OK') {
						return [
							'distance' => $data['rows'][0]['elements'][0]['distance']['value'], // meters
							'duration' => $data['rows'][0]['elements'][0]['duration']['value']  // seconds
						];
					}
				}
			}

			// Fallback to OSRM (Open Source Routing Machine)
			// Note: OSRM uses {lng},{lat} order
			$osrm_url = "http://router.project-osrm.org/route/v1/driving/{$start_lng},{$start_lat};{$end_lng},{$end_lat}?overview=false";
			$response = wp_remote_get($osrm_url);
			if (!is_wp_error($response)) {
				$body = wp_remote_retrieve_body($response);
				$data = json_decode($body, true);
				if (isset($data['code']) && $data['code'] === 'Ok' && isset($data['routes'][0])) {
					return [
						'distance' => $data['routes'][0]['distance'], // meters
						'duration' => $data['routes'][0]['duration']  // seconds
					];
				}
			}
			
			return false;
		}

		// Multi-stop version of get_server_distance(): $waypoints is an ordered array of
		// ['lat' => .., 'lng' => ..] pairs - pickup first, dropoff last, any extra stops in between.
		// Returns the total distance/duration across the whole route (all legs combined).
		// With exactly 2 waypoints this is equivalent to get_server_distance().
		public static function get_server_distance_multi($waypoints) {
			$waypoints = array_values(array_filter($waypoints, function ($p) {
				return isset($p['lat'], $p['lng']) && $p['lat'] !== '' && $p['lng'] !== '';
			}));

			if (count($waypoints) < 2) {
				return false;
			}

			if (count($waypoints) === 2) {
				return self::get_server_distance($waypoints[0]['lat'], $waypoints[0]['lng'], $waypoints[1]['lat'], $waypoints[1]['lng']);
			}

			// Google Directions API supports intermediate waypoints in one call.
			$api_key = MP_Global_Function::get_settings('mptbm_map_api_settings', 'map_api_key');
			if ($api_key) {
				$origin = $waypoints[0]['lat'] . ',' . $waypoints[0]['lng'];
				$destination = end($waypoints)['lat'] . ',' . end($waypoints)['lng'];
				$middle = array_slice($waypoints, 1, -1);
				$waypoints_param = implode('|', array_map(function ($p) {
					return $p['lat'] . ',' . $p['lng'];
				}, $middle));

				$url = "https://maps.googleapis.com/maps/api/directions/json?origin={$origin}&destination={$destination}&mode=driving&key={$api_key}";
				if ($waypoints_param) {
					$url .= '&waypoints=' . rawurlencode($waypoints_param);
				}
				$response = wp_remote_get($url);
				if (!is_wp_error($response)) {
					$body = wp_remote_retrieve_body($response);
					$data = json_decode($body, true);
					if (isset($data['status']) && $data['status'] === 'OK' && !empty($data['routes'][0]['legs'])) {
						$distance = 0;
						$duration = 0;
						foreach ($data['routes'][0]['legs'] as $leg) {
							$distance += $leg['distance']['value'];
							$duration += $leg['duration']['value'];
						}
						return ['distance' => $distance, 'duration' => $duration];
					}
				}
			}

			// Fallback to OSRM - its route endpoint natively accepts more than 2 coordinates
			// and returns one combined distance/duration for the whole ordered route.
			$coords = implode(';', array_map(function ($p) {
				return $p['lng'] . ',' . $p['lat']; // OSRM uses {lng},{lat} order
			}, $waypoints));
			$osrm_url = "http://router.project-osrm.org/route/v1/driving/{$coords}?overview=false";
			$response = wp_remote_get($osrm_url);
			if (!is_wp_error($response)) {
				$body = wp_remote_retrieve_body($response);
				$data = json_decode($body, true);
				if (isset($data['code']) && $data['code'] === 'Ok' && isset($data['routes'][0])) {
					return [
						'distance' => $data['routes'][0]['distance'],
						'duration' => $data['routes'][0]['duration'],
					];
				}
			}

			return false;
		}
	}
	new MPTBM_Function();
}
