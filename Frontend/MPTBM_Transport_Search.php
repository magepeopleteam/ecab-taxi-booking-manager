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
					if ($tab_id === 'distance' || $tab_id === 'hourly' || $tab_id === 'flat-rate' || $tab_id === 'custom') {
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
				error_log('=== MPTBM DEBUG: get_mptbm_map_search_result started ===');
				error_log('MPTBM DEBUG: POST data - ' . print_r($_POST, true));
				error_log('MPTBM DEBUG: COOKIE data - ' . print_r($_COOKIE, true));
				
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
					error_log("MPTBM DEBUG: Cleared {$deleted_transients} transients and {$deleted_timeouts} timeouts for pattern: {$pattern}");
				}
				
				// Ensure original_price_based is set for proper pricing calculations
				$price_based = isset($_POST['price_based']) ? sanitize_text_field($_POST['price_based']) : 'dynamic';
				set_transient('original_price_based', $price_based, HOUR_IN_SECONDS);
				error_log("MPTBM DEBUG: Set original_price_based transient to: {$price_based}");
				
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
				
				$distance = isset($_COOKIE['mptbm_distance']) ? absint($_COOKIE['mptbm_distance']) : '';
				$duration = isset($_COOKIE['mptbm_duration']) ? absint($_COOKIE['mptbm_duration']) : '';
				
				error_log("MPTBM DEBUG: Distance from cookie: {$distance}, Duration from cookie: {$duration}");
				error_log('MPTBM DEBUG: About to include choose_vehicles.php template');
				
				// if ($distance && $duration) {
					include(MPTBM_Function::template_path('registration/choose_vehicles.php'));
				// }
				
				error_log('=== MPTBM DEBUG: get_mptbm_map_search_result completed ===');
			
			die(); // Ensure further execution stops after outputting the JavaScript
			}
			public function get_mptbm_map_search_result_redirect(){
				// Debug logging for redirect search initiation
				error_log('=== MPTBM DEBUG: get_mptbm_map_search_result_redirect started ===');
				error_log('MPTBM DEBUG: POST data - ' . print_r($_POST, true));
				error_log('MPTBM DEBUG: COOKIE data - ' . print_r($_COOKIE, true));
				
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
					error_log("MPTBM DEBUG: Cleared {$deleted_transients} transients and {$deleted_timeouts} timeouts for pattern: {$pattern}");
				}
				
				// Ensure original_price_based is set for proper pricing calculations
				$price_based = isset($_POST['price_based']) ? sanitize_text_field($_POST['price_based']) : 'dynamic';
				set_transient('original_price_based', $price_based, HOUR_IN_SECONDS);
				error_log("MPTBM DEBUG: Set original_price_based transient to: {$price_based}");
				
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
					
					$distance = isset($_COOKIE['mptbm_distance']) ? absint($_COOKIE['mptbm_distance']) : '';
					$duration = isset($_COOKIE['mptbm_duration']) ? absint($_COOKIE['mptbm_duration']) : '';
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
			public function get_mptbm_extra_service() {
				include(MPTBM_Function::template_path('registration/extra_service.php'));
				die();
			}
			public function get_mptbm_extra_service_summary() {
				include(MPTBM_Function::template_path('registration/extra_service_summary.php'));
				die();
			}
		}
		new MPTBM_Transport_Search();
	}