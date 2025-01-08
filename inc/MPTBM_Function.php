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
			$all_empty = true;
			$schedule = [];
			foreach ($days_name as $name) {
				$start_time = get_post_meta($post_id, "mptbm_" . $name . "_start_time", true);
				$end_time = get_post_meta($post_id, "mptbm_" . $name . "_end_time", true);
				if ($start_time !== "" && $end_time !== "") {
					$schedule[$name] = [$start_time, $end_time];
				}
			}
			foreach ($schedule as $times) {
				if (!empty($times[0]) || !empty($times[1])) {
					$all_empty = false;
					break;
				}
			}
			if ($all_empty) {
				$default_start_time = get_post_meta($post_id, "mptbm_default_start_time", true);
				$default_end_time = get_post_meta($post_id, "mptbm_default_end_time", true);
				$schedule['default'] = [$default_start_time, $default_end_time];
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
			$price = 0.0;  // Initialize price as a float
			$return_discount_amount = 0;
			// Check if the session is active
			if (session_status() !== PHP_SESSION_ACTIVE) {
				// Start the session if it's not active
				session_start();
			}
			$initial_price = (float) MP_Global_Function::get_post_info($post_id, 'mptbm_initial_price');
			$min_price = (float) MP_Global_Function::get_post_info($post_id, 'mptbm_min_price');
			$return_min_price = MP_Global_Function::get_post_info($post_id, 'mptbm_min_price_return');
			$price_based = MP_Global_Function::get_post_info($post_id, 'mptbm_price_based');

			$original_price_based = get_transient('original_price_based');
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
			} elseif ((trim($price_based) == 'inclusive' || trim($price_based) == 'manual') && trim($original_price_based) == 'manual') {
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


			if ($waiting_time > 0) {
				$price += $waiting_price;
			}

			if ($initial_price > 0) {
				$price += $initial_price;
			}

			if ($min_price > 0 && $min_price > $price) {

				$price = $min_price;


				if ($return_min_price > 0 && $two_way > 1) {
					$price = $price + $return_min_price;
				} elseif ($return_min_price == '' && $two_way > 1) {
					$price = $price * 2;
				}
			} elseif ($two_way > 1) {
				$price = $price * 2;
			}

			if ($two_way > 1) {
				$return_discount_amount = self::calculate_return_discount($post_id, $price); // Calculate discount for two-way
				$price -= $return_discount_amount; // Apply discount
			}
			if ($min_price > 0) {
				if ($price < $min_price) {
					$price = $min_price;
					$return_discount_amount = 0;
				}

				if ($return_discount_amount > 0 && $min_price < $return_discount_amount) {

					if ($return_discount_amount >= $min_price) {
						$return_discount_amount = 0;
						$price = $min_price;
					} else {
						$price -= $return_discount_amount;
					}
				}
			}

			if (class_exists('MPTBM_Datewise_Discount_Addon')) {
    $selected_start_date = isset($_POST["start_date"]) ? sanitize_text_field($_POST["start_date"]) : "";
    $selected_start_time = isset($_POST["start_time"]) ? sanitize_text_field($_POST["start_time"]) : "";

    if (strpos($selected_start_time, '.') !== false) {
        $selected_start_time = sprintf('%02d:%02d', floor($selected_start_time), ($selected_start_time - floor($selected_start_time)) * 60);
    } else {
        $selected_start_time = sprintf('%02d:00', $selected_start_time);
    }

    $discounts = MP_Global_Function::get_post_info($post_id, 'mptbm_discounts', []);

    if (!empty($discounts)) {
        foreach ($discounts as $discount) {
            $start_date = isset($discount['start_date']) ? date('Y-m-d', strtotime($discount['start_date'])) : '';
            $end_date = isset($discount['end_date']) ? date('Y-m-d', strtotime($discount['end_date'])) : '';
            $time_slots = isset($discount['time_slots']) ? $discount['time_slots'] : [];

            if (strtotime($selected_start_date) >= strtotime($start_date) && strtotime($selected_start_date) <= strtotime($end_date)) {
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
                        if (strtotime($selected_start_time) >= strtotime($start_time) || strtotime($selected_start_time) <= strtotime($end_time)) {
                            $percentage = floatval(rtrim($slot['percentage'], '%'));
                            $type = isset($slot['type']) ? $slot['type'] : 'increase';

                            $discount_amount = ($percentage / 100) * $price;

                            if ($type === 'decrease') {
                                $price -= abs($discount_amount);
                            } else {
                                $price += $discount_amount;
                            }
                        }
                    } else {
                        if (strtotime($selected_start_time) >= strtotime($start_time) && strtotime($selected_start_time) <= strtotime($end_time)) {
                            $percentage = floatval(rtrim($slot['percentage'], '%'));
                            $type = isset($slot['type']) ? $slot['type'] : 'increase';

                            $discount_amount = ($percentage / 100) * $price;

                            if ($type === 'decrease') {
                                $price -= abs($discount_amount);
                            } else {
                                $price += $discount_amount;
                            }
                        }
                    }
                }
            }
        }
    }
}
			// Check if session key exists for the specific post_id
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
			//delete_transient('original_price_based');
			return $price;
		}
		public static function calculate_return_discount($post_id, $price)
		{
			$return_discount = MP_Global_Function::get_post_info($post_id, 'mptbm_return_discount', 0);

			// Check if the return discount is a percentage or a fixed amount
			if (strpos($return_discount, '%') !== false) {
				// It's a percentage discount
				$percentage = floatval(trim($return_discount, '%'));
				$return_discount_amount = ($percentage / 100) * $price;
			} else {
				// It's a fixed amount discount
				$return_discount_amount = floatval($return_discount);
			}

			return $return_discount_amount;
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
	}
	new MPTBM_Function();
}
