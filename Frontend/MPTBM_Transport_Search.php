<?php
	/*
 * @Author 		engr.sumonazma@gmail.com
 * Copyright: 	mage-people.com
 */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('MPTBM_Transport_Search')) {
		class MPTBM_Transport_Search {
			public function __construct() {
				add_action('mptbm_transport_search', [$this, 'transport_search'], 10, 1);
				//add_action('mptbm_transport_search_form', [$this, 'transport_search_form'], 10, 2);
				/*******************/
				add_action('wp_ajax_get_mptbm_map_search_result', [$this, 'get_mptbm_map_search_result']);
				add_action('wp_ajax_nopriv_get_mptbm_map_search_result', [$this, 'get_mptbm_map_search_result']);
				add_action('wp_ajax_get_mptbm_map_search_result_redirect', [$this, 'get_mptbm_map_search_result_redirect']);
				add_action('wp_ajax_nopriv_get_mptbm_map_search_result_redirect', [$this, 'get_mptbm_map_search_result_redirect']);
				/*********************/
				add_action('wp_ajax_get_mptbm_end_place', [$this, 'get_mptbm_end_place']);
				add_action('wp_ajax_nopriv_get_mptbm_end_place', [$this, 'get_mptbm_end_place']);
				/**************************/
				add_action('wp_ajax_get_mptbm_extra_service', [$this, 'get_mptbm_extra_service']);
				add_action('wp_ajax_nopriv_get_mptbm_extra_service', [$this, 'get_mptbm_extra_service']);
				/*******************************/
				add_action('wp_ajax_get_mptbm_extra_service_summary', [$this, 'get_mptbm_extra_service_summary']);
				add_action('wp_ajax_nopriv_get_mptbm_extra_service_summary', [$this, 'get_mptbm_extra_service_summary']);
				/**************************/
				add_action('wp_ajax_load_get_details_page', [$this, 'load_get_details_page']);
				add_action('wp_ajax_nopriv_load_get_details_page', [$this, 'load_get_details_page']);
			}
			public function transport_search($params) {
				$display_map = MP_Global_Function::get_settings('mptbm_map_api_settings', 'display_map', 'enable');
				$price_based = $params['price_based'] ?: 'dynamic';
				$price_based = $display_map == 'disable' ? 'manual' : $price_based;
				$progressbar = $params['progressbar'] ?: 'yes';
				$form_style= $params['form'] ?: 'horizontal';
				$map= $params['map'] ?: 'yes';
				$map = $display_map == 'disable' ? 'no' : $map;
				$tab = $params['tab'] ?: 'no';
				$tabs = $params['tabs'] ?: 'distance,hourly,manual';
				ob_start();
				do_shortcode('[shop_messages]');
				echo ob_get_clean();
				//echo '<pre>';print_r($params);echo '</pre>';
				include(MPTBM_Function::template_path('registration/registration_layout.php'));
			}
			function load_get_details_page() {
				if (isset($_POST['tab_id'])) {
					$tab_id = sanitize_text_field($_POST['tab_id']); // Sanitize input
					$form_style = sanitize_text_field($_POST['form_style']);
					$map = sanitize_text_field($_POST['map']); // Changed from $display_map to $map
					// Include the correct template based on the tab
					if ($tab_id === 'distance' || $tab_id === 'hourly' || $tab_id === 'flat-rate' || $tab_id === 'custom' || $tab_id === 'fixed_distance' || $tab_id === 'fixed_zone' || $tab_id === 'fixed_zone_dropoff') {
						ob_start(); // Start output buffering
						
						if($tab_id === 'distance'){
							$price_based = 'dynamic';
							include MPTBM_Function::template_path('registration/get_details.php');
						}else if($tab_id === 'hourly'){
							$price_based = 'fixed_hourly';
							include MPTBM_Function::template_path('registration/get_details.php');
						}else if($tab_id === 'flat-rate'){
							$price_based = 'manual';
							$form_style = 'inline';
							include MPTBM_Function::template_path('registration/get_details.php');
						}else if($tab_id === 'fixed_distance'){
							$price_based = 'fixed_distance';
							include MPTBM_Function::template_path('registration/get_details.php');
						}else if($tab_id === 'fixed_zone'){
							$price_based = 'fixed_zone';
							include MPTBM_Function::template_path('registration/get_details.php');
						}else if($tab_id === 'fixed_zone_dropoff'){
							$price_based = 'fixed_zone_dropoff';
							include MPTBM_Function::template_path('registration/get_details.php');
						}else if($tab_id === 'custom'){
							do_action('mptbm_render_custom');
						}
						
						$content = ob_get_clean(); // Get the template output
						echo $content;
					}
				}
				wp_die(); // End AJAX call
			}
			public function get_mptbm_map_search_result() {
				// Debug logging for search initiation
				
				// Clear location-based pricing cache for fresh calculations on each search
				// This prevents cached pricing data from affecting subsequent searches with same locations
				// BUT preserve essential pricing data like original_price_based
				global $wpdb;
				$cache_patterns = array(
					'weather_pricing_%',
					'traffic_data_%',
					'mptbm_custom_price_message_%'
				);

				foreach ($cache_patterns as $pattern) {
					$deleted_transients = $wpdb->query($wpdb->prepare(
						"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
						'_transient_' . $pattern
					));
					$deleted_timeouts = $wpdb->query($wpdb->prepare(
						"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
						'_transient_timeout_' . $pattern
					));
				}
				// Ensure original_price_based is set for proper pricing calculations
				// Ensure original_price_based is set for proper pricing calculations
				$price_based = isset($_POST['price_based']) ? sanitize_text_field($_POST['price_based']) : 'dynamic';
				if ($price_based == 'fixed_distance') {
					set_transient('original_price_based', 'fixed_distance', HOUR_IN_SECONDS);
				}
				set_transient('original_price_based', $price_based, HOUR_IN_SECONDS);
				
				// Buffer time validation
				$buffer_time = (int) MP_Global_Function::get_settings('mptbm_general_settings', 'enable_buffer_time');
				
				if ($buffer_time > 0) {
					$start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
					$start_time = isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : '';
					
					if ($start_date && $start_time) {
						// Convert start time to proper format
						$time_parts = explode('.', $start_time);
						$hours = isset($time_parts[0]) ? intval($time_parts[0]) : 0;
						$minutes = isset($time_parts[1]) ? intval($time_parts[1]) : 0;
						
						// Create datetime string
						$booking_datetime = $start_date . ' ' . sprintf('%02d:%02d', $hours, $minutes);
						$booking_timestamp = strtotime($booking_datetime);
						$current_timestamp = time();
						
						// Calculate time difference in minutes
						$time_difference = ($booking_timestamp - $current_timestamp) / 60;
						
						if ($time_difference < $buffer_time) {
							// Return error response
							wp_send_json_error(array(
								'message' => sprintf(
									esc_html__('Booking must be at least %d minutes in advance. Please select a later time.', 'ecab-taxi-booking-manager'),
									$buffer_time
								)
							));
							die();
						}
					}
				}
				
				// Secure: Calculate distance server-side using coordinates passed from JS
				$start_coords = isset($_POST['start_place_coordinates']) ? $_POST['start_place_coordinates'] : [];
				$end_coords = isset($_POST['end_place_coordinates']) ? $_POST['end_place_coordinates'] : [];
				
				$server_data = false;
				if (!empty($start_coords) && !empty($end_coords)) {
					$s_lat = isset($start_coords['latitude']) ? $start_coords['latitude'] : '';
					$s_lng = isset($start_coords['longitude']) ? $start_coords['longitude'] : '';
					$e_lat = isset($end_coords['latitude']) ? $end_coords['latitude'] : '';
					$e_lng = isset($end_coords['longitude']) ? $end_coords['longitude'] : '';
					
					// Set transients for pricing logic in MPTBM_Function::get_price
					set_transient('pickup_lat_transient', $s_lat, HOUR_IN_SECONDS);
					set_transient('pickup_lng_transient', $s_lng, HOUR_IN_SECONDS);
					set_transient('drop_lat_transient', $e_lat, HOUR_IN_SECONDS);
					set_transient('drop_lng_transient', $e_lng, HOUR_IN_SECONDS);

					$server_data = MPTBM_Function::get_server_distance($s_lat, $s_lng, $e_lat, $e_lng);
				}

				if ($server_data) {
					$distance = $server_data['distance'];
					$duration = $server_data['duration'];
					
					// Store in Session for Cart Validation
					if (session_status() === PHP_SESSION_NONE) session_start();
					$_SESSION['mptbm_secure_distance'] = $distance;
					$_SESSION['mptbm_secure_duration'] = $duration;
					$_SESSION['mptbm_secure_start_place'] = isset($_POST['start_place']) ? sanitize_text_field($_POST['start_place']) : '';
					$_SESSION['mptbm_secure_end_place'] = isset($_POST['end_place']) ? sanitize_text_field($_POST['end_place']) : '';
					session_write_close();
				} else {
					// Fallback (Less Secure, but keeps functionality if server API fails)
					// We still try to use POST over Cookie as per previous fix
					$distance = isset($_POST['mptbm_distance']) ? absint($_POST['mptbm_distance']) : (isset($_COOKIE['mptbm_distance']) ? absint($_COOKIE['mptbm_distance']) : '');
					$duration = isset($_POST['mptbm_duration']) ? absint($_POST['mptbm_duration']) : (isset($_COOKIE['mptbm_duration']) ? absint($_COOKIE['mptbm_duration']) : '');
				}
				
				
				
				// if ($distance && $duration) {
					include(MPTBM_Function::template_path('registration/choose_vehicles.php'));
				// }
				
			
			die(); // Ensure further execution stops after outputting the JavaScript
			}
			public function get_mptbm_map_search_result_redirect(){
				// Debug logging for redirect search initiation
				
				// Clear location-based pricing cache for fresh calculations on each search
				// This prevents cached pricing data from affecting subsequent searches with same locations
				// BUT preserve essential pricing data like original_price_based
				global $wpdb;
				$cache_patterns = array(
					'weather_pricing_%',
					'traffic_data_%',
					'mptbm_custom_price_message_%'
				);

				foreach ($cache_patterns as $pattern) {
					$deleted_transients = $wpdb->query($wpdb->prepare(
						"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
						'_transient_' . $pattern
					));
					$deleted_timeouts = $wpdb->query($wpdb->prepare(
						"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
						'_transient_timeout_' . $pattern
					));
				}
				// Ensure original_price_based is set for proper pricing calculations
				$price_based = isset($_POST['price_based']) ? sanitize_text_field($_POST['price_based']) : 'dynamic';
				if ($price_based == 'fixed_distance') {
					set_transient('original_price_based', 'fixed_distance', HOUR_IN_SECONDS);
				}
				set_transient('original_price_based', $price_based, HOUR_IN_SECONDS);
				
				// Buffer time validation
				$buffer_time = (int) MP_Global_Function::get_settings('mptbm_general_settings', 'enable_buffer_time');
				
				if ($buffer_time > 0) {
					$start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
					$start_time = isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : '';
					
					if ($start_date && $start_time) {
						// Convert start time to proper format
						$time_parts = explode('.', $start_time);
						$hours = isset($time_parts[0]) ? intval($time_parts[0]) : 0;
						$minutes = isset($time_parts[1]) ? intval($time_parts[1]) : 0;
						
						// Create datetime string
						$booking_datetime = $start_date . ' ' . sprintf('%02d:%02d', $hours, $minutes);
						$booking_timestamp = strtotime($booking_datetime);
						$current_timestamp = time();
						
						// Calculate time difference in minutes
						$time_difference = ($booking_timestamp - $current_timestamp) / 60;
						
						if ($time_difference < $buffer_time) {
							// Return error response
							wp_send_json_error(array(
								'message' => sprintf(
									esc_html__('Booking must be at least %d minutes in advance. Please select a later time.', 'ecab-taxi-booking-manager'),
									$buffer_time
								)
							));
							die();
						}
					}
				}
				
				ob_start(); // Start output buffering
					
				// Secure: Calculate distance server-side using coordinates passed from JS
				$start_coords = isset($_POST['start_place_coordinates']) ? $_POST['start_place_coordinates'] : [];
				$end_coords = isset($_POST['end_place_coordinates']) ? $_POST['end_place_coordinates'] : [];
				
				$server_data = false;
				if (!empty($start_coords) && !empty($end_coords)) {
					$s_lat = isset($start_coords['latitude']) ? $start_coords['latitude'] : '';
					$s_lng = isset($start_coords['longitude']) ? $start_coords['longitude'] : '';
					$e_lat = isset($end_coords['latitude']) ? $end_coords['latitude'] : '';
					$e_lng = isset($end_coords['longitude']) ? $end_coords['longitude'] : '';
					
					$server_data = MPTBM_Function::get_server_distance($s_lat, $s_lng, $e_lat, $e_lng);
				}

				if ($server_data) {
					$distance = $server_data['distance'];
					$duration = $server_data['duration'];
					
					// Store in Session
					if (session_status() === PHP_SESSION_NONE) session_start();
					$_SESSION['mptbm_secure_distance'] = $distance;
					$_SESSION['mptbm_secure_duration'] = $duration;
					$_SESSION['mptbm_secure_start_place'] = isset($_POST['start_place']) ? sanitize_text_field($_POST['start_place']) : '';
					$_SESSION['mptbm_secure_end_place'] = isset($_POST['end_place']) ? sanitize_text_field($_POST['end_place']) : '';
					session_write_close();
				} else {
					$distance = isset($_POST['mptbm_distance']) ? absint($_POST['mptbm_distance']) : (isset($_COOKIE['mptbm_distance']) ? absint($_COOKIE['mptbm_distance']) : '');
					$duration = isset($_POST['mptbm_duration']) ? absint($_POST['mptbm_duration']) : (isset($_COOKIE['mptbm_duration']) ? absint($_COOKIE['mptbm_duration']) : '');
				}
					// if ($distance && $duration) {
						include(MPTBM_Function::template_path('registration/choose_vehicles.php'));
					// }
					$content = ob_get_clean(); // Get the buffered content and clean the buffer
					// Store the content in a session variable
					session_start();
					$_SESSION['custom_content'] = $content;
					
					session_write_close(); // Close the session to release the lock
					$redirect_slug = isset($_POST['mptbm_enable_view_search_result_page']) ? sanitize_text_field($_POST['mptbm_enable_view_search_result_page']) : '';
					
					// If no slug is provided, get it from settings
					if(empty($redirect_slug)){
						$redirect_slug = MP_Global_Function::get_settings('mptbm_general_settings', 'enable_view_search_result_page');
					}
					
					// If still no slug, use default
					if(empty($redirect_slug)){
						$redirect_slug = 'transport-result';	
					}
					
					// Convert slug to proper WordPress page URL
					$redirect_url = MPTBM_Function::get_page_url_from_slug($redirect_slug);
					
					echo wp_json_encode($redirect_url);
				die(); // Ensure further execution stops after outputting the JavaScript
			}

			public function get_mptbm_end_place() {
				include(MPTBM_Function::template_path('registration/get_end_place.php'));
				die();
			}
			
			/**
			 * Validate post access for extra service endpoints
			 * Ensures post exists, is published, and is correct post type
			 */
			private function validate_post_access($post_id) {
				if (!$post_id || $post_id <= 0) {
					return false;
				}
				
				$post = get_post($post_id);
				
				// Check if post exists
				if (!$post) {
					return false;
				}
				
				// Check post type - must be transportation post type
				if (get_post_type($post_id) !== MPTBM_Function::get_cpt()) {
					return false;
				}
				
				// Check post status - must be published (not private, draft, etc.)
				if ($post->post_status !== 'publish') {
					return false;
				}
				
				return true;
			}
			
			public function get_mptbm_extra_service() {
				$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
				
				// Security check: validate post access
				if (!$this->validate_post_access($post_id)) {
					wp_die(esc_html__('Invalid request or post not found.', 'ecab-taxi-booking-manager'), esc_html__('Error', 'ecab-taxi-booking-manager'), array('response' => 403));
				}
				
				include(MPTBM_Function::template_path('registration/extra_service.php'));
				die();
			}
			public function get_mptbm_extra_service_summary() {
				$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
				
				// Security check: validate post access
				if (!$this->validate_post_access($post_id)) {
					wp_die(esc_html__('Invalid request or post not found.', 'ecab-taxi-booking-manager'), esc_html__('Error', 'ecab-taxi-booking-manager'), array('response' => 403));
				}
				
				include(MPTBM_Function::template_path('registration/extra_service_summary.php'));
				die();
			}
		}
		new MPTBM_Transport_Search();
	}