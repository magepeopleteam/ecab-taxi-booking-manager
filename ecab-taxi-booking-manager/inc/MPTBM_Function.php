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
		public static function get_price($post_id, $distance = 1000, $duration = 3600, $start_place = '', $destination_place = '', $waiting_time = 0, $two_way = 1, $fixed_time = 0)
		{
			
			// Force fresh pricing calculations to prevent caching issues on repeated searches
			$is_transport_result_page = false;
			$is_ajax_search = false;
			
			// Check if we're on the transport result page by various methods
			if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'transport-result') !== false) {
				$is_transport_result_page = true;
			}
			
			// Check if current page template is transport_result.php
			if (is_page() && get_page_template_slug() === 'transport_result.php') {
				$is_transport_result_page = true;
			}
			
			// Check if we're on the custom search result page from settings
			$search_result_slug = MP_Global_Function::get_settings('mptbm_general_settings', 'enable_view_search_result_page');
			if (!empty($search_result_slug) && isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], $search_result_slug) !== false) {
				$is_transport_result_page = true;
			}
			
			// Check if this is an AJAX search request
			if (defined('DOING_AJAX') && DOING_AJAX && 
				(isset($_POST['action']) && (
					$_POST['action'] === 'get_mptbm_map_search_result' || 
					$_POST['action'] === 'get_mptbm_map_search_result_redirect'
				))) {
				$is_ajax_search = true;
			}
			
			if ($is_transport_result_page || $is_ajax_search) {
				// Clear pricing-specific cache groups for fresh calculations
				wp_cache_flush_group('mptbm_pricing');
				wp_cache_flush_group('weather_pricing');
				wp_cache_flush_group('traffic_data');
				
				// Also clear specific location-based transients if start/end places are provided
				if (!empty($start_place) && !empty($destination_place)) {
					$location_cache_key = md5($start_place . $destination_place);
					delete_transient('weather_pricing_' . $location_cache_key);
					delete_transient('traffic_data_' . $location_cache_key);
				}
			}

			// Get price display type
			$price_display_type = MP_Global_Function::get_post_info($post_id, 'mptbm_price_display_type', 'normal');
			
			// If price display type is zero, return 0
			if ($price_display_type === 'zero') {
				return 0;
			}
			
			// If price display type is custom message, store it in a transient and return 0
			if ($price_display_type === 'custom_message') {
				$custom_message = MP_Global_Function::get_post_info($post_id, 'mptbm_custom_price_message', '');
				set_transient('mptbm_custom_price_message_' . $post_id, $custom_message, HOUR_IN_SECONDS);
				return 0;
			}

			// Get price basis information
			$price_based = MP_Global_Function::get_post_info($post_id, 'mptbm_price_based');
			$original_price_based = get_transient('original_price_based');

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
				// Check if the session is active
				if (session_status() !== PHP_SESSION_ACTIVE) {
					// Start the session if it's not active
					session_start();
				}
				$initial_price = (float) MP_Global_Function::get_post_info($post_id, 'mptbm_initial_price');
				$min_price = (float) MP_Global_Function::get_post_info($post_id, 'mptbm_min_price');
				$return_min_price = MP_Global_Function::get_post_info($post_id, 'mptbm_min_price_return');

				$waiting_price = (float) MP_Global_Function::get_post_info($post_id, 'mptbm_waiting_price', 0) * (float) $waiting_time;

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
				$selected_start_date = get_transient('start_date_transient');
				$selected_start_time = get_transient('start_time_schedule_transient');
				$datetime_discount_applied = false;
				$day_discount_applied = false;
				$date_range_matched = false;
				$original_price = $price;

				// Get toggle states for both discount types
				$datetime_discount_enabled = get_post_meta($post_id, 'mptbm_datetime_discount_enabled', true);
				$day_discount_enabled = get_post_meta($post_id, 'mptbm_day_discount_enabled', true);

				if (strpos($selected_start_time, '.') !== false) {
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

			session_write_close();

			// Apply filters for dynamic pricing (weather, traffic, etc.) if addons are available
			if (has_filter('mptbm_calculate_price')) {
				$extra_data = array();

				// Try to get coordinates from various sources for weather/traffic pricing
				$pickup_lat = get_transient('mptbm_pickup_lat') ?: get_transient('pickup_lat_transient');
				$pickup_lng = get_transient('mptbm_pickup_lng') ?: get_transient('pickup_lng_transient');
				$drop_lat = get_transient('mptbm_drop_lat') ?: get_transient('drop_lat_transient');
				$drop_lng = get_transient('mptbm_drop_lng') ?: get_transient('drop_lng_transient');

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

				$selected_start_date = get_transient('start_date_transient') ?: '';
				$selected_start_time = get_transient('start_time_schedule_transient') ?: '';

				$price = apply_filters('mptbm_calculate_price', $price, $post_id, $selected_start_date, $selected_start_time, $extra_data);
			}

			

			$wc_check = MP_Global_Function::check_woocommerce();

			if ($wc_check == 1) {
				$_product_id = MP_Global_Function::get_post_info($post_id, 'link_wc_product', $post_id);

				$product = wc_get_product($_product_id);

				if ($product) {
					$is_taxable = $product->is_taxable();

					if ($is_taxable) {
						// Get tax rates for this product
						$tax_rates = WC_Tax::get_rates($product->get_tax_class());

						if (!empty($tax_rates)) {
							// Calculate tax on the final price
							$taxes = WC_Tax::calc_tax($price, $tax_rates, false);

							if (!empty($taxes)) {
								$tax_amount = array_sum($taxes);
								// Add tax to the price
								$price = $price + $tax_amount;
							} 
						} 
					}
				} 
			} 


			return $price;
		}

		public static function get_extra_service_price_by_name($post_id, $service_name)
		{
			$display_extra_services = MP_Global_Function::get_post_info($post_id, 'display_mptbm_extra_services', 'on');
			$service_id = MP_Global_Function::get_post_info($post_id, 'mptbm_extra_services_id', $post_id);
			$extra_services = MP_Global_Function::get_post_info($service_id, 'mptbm_extra_service_infos', []);
			$price = 0;
			if ($display_extra_services == 'on' && is_array($extra_services) && sizeof($extra_services) > 0) {
				foreach ($extra_services as $service) {
					$ex_service_name = array_key_exists('service_name', $service) ? $service['service_name'] : '';
					if ($ex_service_name == $service_name) {
						return array_key_exists('service_price', $service) ? $service['service_price'] : 0;
					}
				}
			}
			return $price;
		}
		//************Location*******************//
		public static function location_exit($post_id, $start_place, $destination_place)
		{
			$price_based = MP_Global_Function::get_post_info($post_id, 'mptbm_price_based');

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
			}
			return true;
		}
		public static function get_all_start_location($post_id = '')
		{
			$all_location = [];
			if ($post_id && $post_id > 0) {
				$manual_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_manual_price_info', []);
				$terms_location_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_terms_start_location', []);
				if (sizeof($manual_prices) > 0) {
					foreach ($manual_prices as $manual_price) {
						$start_location = array_key_exists('start_location', $manual_price) ? $manual_price['start_location'] : '';
						if ($start_location) {
							$all_location[] = $start_location;
						}
					}
				}
			} else {
				$all_posts = MPTBM_Query::query_transport_list('manual');
				if ($all_posts->found_posts > 0) {
					$posts = $all_posts->posts;
					foreach ($posts as $post) {
						$post_id = $post->ID;
						$manual_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_manual_price_info', []);
						$terms_location_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_terms_price_info', []);
						if (sizeof($manual_prices) > 0) {
							foreach ($manual_prices as $manual_price) {
								$start_location = array_key_exists('start_location', $manual_price) ? $manual_price['start_location'] : '';
								if ($start_location) {
									$all_location[] = $start_location;
								}
							}
						}
						if (sizeof($terms_location_prices) > 0) {
							foreach ($terms_location_prices as $terms_location_price) {
								$start_location = array_key_exists('start_location', $terms_location_price) ? $terms_location_price['start_location'] : '';
								if ($start_location) {
									$all_location[] = $start_location;
								}
							}
						}
					}
				}
			}
			return array_unique($all_location);
		}
		public static function get_end_location($start_place, $post_id = '')
		{
			$all_location = [];
			if ($post_id && $post_id > 0) {
				$manual_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_manual_price_info', []);
				if (sizeof($manual_prices) > 0) {
					foreach ($manual_prices as $manual_price) {
						$start_location = array_key_exists('start_location', $manual_price) ? $manual_price['start_location'] : '';
						$end_location = array_key_exists('end_location', $manual_price) ? $manual_price['end_location'] : '';
						if ($start_location && $end_location && $start_location == $start_place) {
							$all_location[] = $end_location;
						}
					}
				}
			} else {
				$all_posts = MPTBM_Query::query_transport_list('manual');
				if ($all_posts->found_posts > 0) {
					$posts = $all_posts->posts;
					foreach ($posts as $post) {
						$post_id = $post->ID;
						$manual_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_manual_price_info', []);
						$terms_location_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_terms_price_info', []);
						if (sizeof($manual_prices) > 0) {
							foreach ($manual_prices as $manual_price) {
								$start_location = array_key_exists('start_location', $manual_price) ? $manual_price['start_location'] : '';
								$end_location = array_key_exists('end_location', $manual_price) ? $manual_price['end_location'] : '';
								if ($start_location && $end_location && $start_location == $start_place) {
									$all_location[] = $end_location;
								}
							}
						}
						if (sizeof($terms_location_prices) > 0) {
							foreach ($terms_location_prices as $terms_location_price) {
								$start_location = array_key_exists('start_location', $terms_location_price) ? $terms_location_price['start_location'] : '';
								$end_location = array_key_exists('end_location', $terms_location_price) ? $terms_location_price['end_location'] : '';
								if ($start_location && $end_location && $start_location == $start_place) {
									$all_location[] = $end_location;
								}
							}
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
	}
	new MPTBM_Function();
}
