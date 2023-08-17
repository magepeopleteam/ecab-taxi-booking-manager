<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('MPTBM_Function')) {
		class MPTBM_Function {
			public function __construct() {}
			//**************Support multi Language*********************//
			public static function post_id_multi_language($post_id) {
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
			public static function all_details_template() {
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
			public static function details_template_path(): string {
				$tour_id = get_the_id();
				$template_name = MP_Global_Function::get_post_info($tour_id, 'mptbm_theme_file', 'default.php');
				$file_name = 'themes/' . $template_name;
				$dir = MPTBM_PLUGIN_DIR . '/templates/' . $file_name;
				if (!file_exists($dir)) {
					$file_name = 'themes/default.php';
				}
				return self::template_path($file_name);
			}
			public static function template_path($file_name): string {
				$template_path = get_stylesheet_directory() . '/mptbm_templates/';
				$default_dir = MPTBM_PLUGIN_DIR . '/templates/';
				$dir = is_dir($template_path) ? $template_path : $default_dir;
				$file_path = $dir . $file_name;
				return locate_template(array('mptbm_templates/' . $file_name)) ? $file_path : $default_dir . $file_name;
			}
			//************************//
			public static function get_general_settings($key, $default = '') {
				return MP_Global_Function::get_settings('mptbm_general_settings', $key, $default);
			}
			public static function get_cpt(): string {
				return 'mptbm_rent';
			}
			public static function get_name() {
				return self::get_general_settings('label', esc_html__('Transportation', 'mptbm_plugin'));
			}
			public static function get_slug() {
				return self::get_general_settings('slug', 'transportation');
			}
			public static function get_icon() {
				return self::get_general_settings('icon', 'dashicons-car');
			}
			public static function get_category_label() {
				return self::get_general_settings('category_label', esc_html__('Category', 'mptbm_plugin'));
			}
			public static function get_category_slug() {
				return self::get_general_settings('category_slug', 'transportation-category');
			}
			public static function get_organizer_label() {
				return self::get_general_settings('organizer_label', esc_html__('Organizer', 'mptbm_plugin'));
			}
			public static function get_organizer_slug() {
				return self::get_general_settings('organizer_slug', 'transportation-organizer');
			}
			//*************************************************************Full Custom Function******************************//
			//*************Date*********************************//
			public static function get_date($post_id, $expire = false) {
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
				}
				else {
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
			public static function get_all_dates($price_based = 'dynamic', $expire = false) {
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
			public static function get_price($post_id, $distance = 1000, $duration = 3600, $start_place = '', $destination_place = ''): string {
				$price = '';
				$price_based = MP_Global_Function::get_post_info($post_id, 'mptbm_price_based');
				if ($price_based == 'distance') {
					$price = MP_Global_Function::get_post_info($post_id, 'mptbm_km_price') * $distance / 1000;
				}
				elseif ($price_based == 'duration') {
					$price = MP_Global_Function::get_post_info($post_id, 'mptbm_hour_price') * $duration / 3600;
				}
				elseif ($price_based == 'distance_duration') {
					$price = MP_Global_Function::get_post_info($post_id, 'mptbm_hour_price') * $duration / 3600 + MP_Global_Function::get_post_info($post_id, 'mptbm_km_price') * $distance / 1000;
				}
				else {
					$manual_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_manual_price_info', []);
					if (sizeof($manual_prices) > 0) {
						foreach ($manual_prices as $manual_price) {
							$start_location = array_key_exists('start_location', $manual_price) ? $manual_price['start_location'] : '';
							$end_location = array_key_exists('end_location', $manual_price) ? $manual_price['end_location'] : '';
							if ($start_place == $start_location && $destination_place == $end_location) {
								$price = array_key_exists('price', $manual_price) ? $manual_price['price'] : '';
							}
						}
					}
				}
				return $price;
			}
			public static function get_extra_service_price_by_name($post_id, $service_name) {
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
			public static function location_exit($post_id, $start_place, $destination_place) {
				$price_based = MP_Global_Function::get_post_info($post_id, 'mptbm_price_based');
				if ($price_based == 'manual') {
					$manual_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_manual_price_info', []);
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
			public static function get_all_start_location($post_id = '') {
				$all_location = [];
				if ($post_id && $post_id > 0) {
					$manual_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_manual_price_info', []);
					if (sizeof($manual_prices) > 0) {
						foreach ($manual_prices as $manual_price) {
							$start_location = array_key_exists('start_location', $manual_price) ? $manual_price['start_location'] : '';
							if ($start_location) {
								$all_location[] = $start_location;
							}
						}
					}
				}
				else {
					$all_posts = MPTBM_Query::query_transport_list('manual');
					if ($all_posts->found_posts > 0) {
						$posts = $all_posts->posts;
						foreach ($posts as $post) {
							$post_id = $post->ID;
							$manual_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_manual_price_info', []);
							if (sizeof($manual_prices) > 0) {
								foreach ($manual_prices as $manual_price) {
									$start_location = array_key_exists('start_location', $manual_price) ? $manual_price['start_location'] : '';
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
			public static function get_end_location($start_place, $post_id = '') {
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
				}
				else {
					$all_posts = MPTBM_Query::query_transport_list('manual');
					if ($all_posts->found_posts > 0) {
						$posts = $all_posts->posts;
						foreach ($posts as $post) {
							$post_id = $post->ID;
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
						}
					}
				}
				return array_unique($all_location);
			}
		}
		new MPTBM_Function();
	}