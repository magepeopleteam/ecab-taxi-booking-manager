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
				add_action('wp_ajax_get_mptbm_vehicle_time_availability', [$this, 'get_mptbm_vehicle_time_availability']);
				add_action('wp_ajax_nopriv_get_mptbm_vehicle_time_availability', [$this, 'get_mptbm_vehicle_time_availability']);
				/**************************/
				add_action('wp_ajax_get_mptbm_extra_service', [$this, 'get_mptbm_extra_service']);
				add_action('wp_ajax_nopriv_get_mptbm_extra_service', [$this, 'get_mptbm_extra_service']);
				/*******************************/
				add_action('wp_ajax_get_mptbm_extra_service_summary', [$this, 'get_mptbm_extra_service_summary']);
				add_action('wp_ajax_nopriv_get_mptbm_extra_service_summary', [$this, 'get_mptbm_extra_service_summary']);
				/**************************/
				add_action('wp_ajax_load_get_details_page', [$this, 'load_get_details_page']);
				add_action('wp_ajax_nopriv_load_get_details_page', [$this, 'load_get_details_page']);
				/**************************/
				add_action('wp_ajax_mptbm_refresh_search_nonce', [$this, 'refresh_search_nonce']);
				add_action('wp_ajax_nopriv_mptbm_refresh_search_nonce', [$this, 'refresh_search_nonce']);
				/**************************/
				add_action('wp_ajax_get_mptbm_route_distance', [$this, 'get_mptbm_route_distance']);
				add_action('wp_ajax_nopriv_get_mptbm_route_distance', [$this, 'get_mptbm_route_distance']);
			}
			/**
			 * Issue a freshly generated booking nonce.
			 *
			 * Full-page caches (WP Rocket / LiteSpeed / Cloudflare APO / host cache) serve
			 * logged-out visitors a cached copy of the booking page whose embedded WordPress
			 * nonce expires after ~24h. The search AJAX then fails check_ajax_referer() with a
			 * 403/-1 for guests only ("works when logged in, fails when logged out"), because
			 * logged-in users bypass the page cache and always receive a fresh nonce.
			 *
			 * admin-ajax.php is never full-page cached, so a nonce generated here is always
			 * live for the current (guest) session. Handing a CSRF token to any visitor is
			 * exactly what a normal page render already does, so this endpoint needs no nonce
			 * of its own.
			 */
			public function refresh_search_nonce(): void {
				// Belt-and-suspenders: admin-ajax.php isn't touched by typical
				// page-cache plugins, but a "cache everything" CDN/edge rule
				// could still catch it -- and caching the one endpoint that
				// exists specifically to hand out a fresh nonce would defeat
				// its entire purpose.
				nocache_headers();
				wp_send_json_success(array(
					'search_nonce'      => wp_create_nonce('mptbm_transport_search'),
					'add_to_cart_nonce' => wp_create_nonce('mptbm_add_to_cart'),
				));
			}
			public function transport_search($params) {
				$display_map = MP_Global_Function::get_settings('mptbm_map_api_settings', 'display_map', 'enable');
				$price_based = $params['price_based'] ?: 'dynamic';
				$vehicle_id = !empty($params['vehicle_id']) ? absint($params['vehicle_id']) : 0;
				if ($vehicle_id && $this->validate_post_access($vehicle_id)) {
					$price_based = $this->vehicle_search_price_mode($vehicle_id);
				}
				$price_based = $display_map == 'disable' && !$vehicle_id ? 'manual' : $price_based;
				$progressbar = $params['progressbar'] ?: 'yes';
				$form_style= $params['form'] ?: 'horizontal';
				$map= $params['map'] ?: 'yes';
				$map = $display_map == 'disable' ? 'no' : $map;
				$tab = $params['tab'] ?: 'no';
				$tabs = $params['tabs'] ?: 'distance,hourly,manual';
				$pickup = isset($params['pickup']) ? sanitize_text_field($params['pickup']) : '';
				$dropoff = isset($params['dropoff']) ? sanitize_text_field($params['dropoff']) : '';
				$pickup_zone = isset($params['pickup_zone']) ? sanitize_text_field($params['pickup_zone']) : '';
				$dropoff_zone = isset($params['dropoff_zone']) ? sanitize_text_field($params['dropoff_zone']) : '';
				// Display-only waypoint names for the shortcode's `stops` attribute -
				// shown between pickup/dropoff purely as text, never geocoded or added
				// to the routed waypoint list, so they never affect distance or price.
				$display_stops = isset($params['stops']) ? array_filter(array_map('trim', explode(',', sanitize_text_field($params['stops'])))) : array();
				ob_start();
				do_shortcode('[shop_messages]');
				echo ob_get_clean();
				//echo '<pre>';print_r($params);echo '</pre>';
				include(MPTBM_Function::template_path('registration/registration_layout.php'));
			}
			function load_get_details_page() {
				$this->verify_search_request();
				if (isset($_POST['tab_id'])) {
					$tab_id = sanitize_text_field($_POST['tab_id']); // Sanitize input
					$form_style = sanitize_text_field($_POST['form_style']);
					$map = sanitize_text_field($_POST['map']); // Changed from $display_map to $map
					$vehicle_id = isset($_POST['vehicle_id']) ? absint($_POST['vehicle_id']) : 0;
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
				// This response embeds a freshly-generated mptbm_add_to_cart
				// nonce (see choose_vehicles.php) -- if a CDN/reverse-proxy or an
				// overly broad caching rule caches it, every visitor who then
				// gets served that cached copy inherits the same nonce, which
				// stops working once it ages past WordPress's ~24h nonce
				// lifetime. That's the "Book Now sometimes doesn't work" pattern.
				nocache_headers();
				$this->verify_search_request(true);
				// Debug logging for search initiation
				
				$price_based = $this->requested_search_price_mode();
				$_POST['price_based'] = $price_based;
				
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
				$start_coords = $this->posted_coordinates('start_place_coordinates');
				$end_coords = $this->posted_coordinates('end_place_coordinates');
				
				$server_data = false;
				if (!empty($start_coords) && !empty($end_coords)) {
					$s_lat = isset($start_coords['latitude']) ? $start_coords['latitude'] : '';
					$s_lng = isset($start_coords['longitude']) ? $start_coords['longitude'] : '';
					$e_lat = isset($end_coords['latitude']) ? $end_coords['latitude'] : '';
					$e_lng = isset($end_coords['longitude']) ? $end_coords['longitude'] : '';
					$server_data = $this->resolve_trip_distance($s_lat, $s_lng, $e_lat, $e_lng);
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
					MPTBM_Function::set_search_context(array(
						'distance'          => $server_data ? absint($server_data['distance']) : 0,
						'duration'          => $server_data ? absint($server_data['duration']) : 0,
						'distance_verified' => (bool) $server_data,
						'start_place'       => isset($_POST['start_place']) ? sanitize_text_field(wp_unslash($_POST['start_place'])) : '',
						'end_place'         => isset($_POST['end_place']) ? sanitize_text_field(wp_unslash($_POST['end_place'])) : '',
						'start_coords'      => MPTBM_Function::normalize_coordinates($start_coords),
						'end_coords'        => MPTBM_Function::normalize_coordinates($end_coords),
						'price_based'       => $price_based,
						'start_date'        => isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '',
						'start_time'        => isset($_POST['start_time']) ? sanitize_text_field(wp_unslash($_POST['start_time'])) : '',
						'booking_datetime'  => trim((isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '') . ' ' . str_replace('.', ':', isset($_POST['start_time']) ? sanitize_text_field(wp_unslash($_POST['start_time'])) : '')),
						'waiting_time'      => isset($_POST['waiting_time']) ? absint($_POST['waiting_time']) : 0,
						'two_way'           => isset($_POST['two_way']) ? max(1, absint($_POST['two_way'])) : 1,
						'fixed_time'        => isset($_POST['fixed_time']) ? max(0, (float) $_POST['fixed_time']) : 0,
						'extra_stop_places' => isset($_POST['mptbm_extra_stop_place']) ? array_values(array_filter(array_map('sanitize_text_field', (array) wp_unslash($_POST['mptbm_extra_stop_place'])))) : array(),
						'extra_stop_count'  => isset($_POST['mptbm_extra_stop_place']) ? count(array_filter((array) $_POST['mptbm_extra_stop_place'])) : 0,
						'return_date'       => isset($_POST['return_date']) ? sanitize_text_field(wp_unslash($_POST['return_date'])) : '',
						'return_time'       => isset($_POST['return_time']) ? sanitize_text_field(wp_unslash($_POST['return_time'])) : '',
						'return_datetime'   => trim((isset($_POST['return_date']) ? sanitize_text_field(wp_unslash($_POST['return_date'])) : '') . ' ' . str_replace('.', ':', isset($_POST['return_time']) ? sanitize_text_field(wp_unslash($_POST['return_time'])) : '')),
					));
				
				
				
				// Bind the verified request mode directly while the result template prices
				// vehicles. The session remains the cross-request checkout record, but this
				// avoids a missing/stale session selecting the wrong formula in this request.
				$pricing_mode_filter = static function($original_mode, $priced_vehicle_id) use ($price_based) {
					return $price_based;
				};
				add_filter('mptbm_original_price_based', $pricing_mode_filter, 99, 2);
				include(MPTBM_Function::template_path('registration/choose_vehicles.php'));
				remove_filter('mptbm_original_price_based', $pricing_mode_filter, 99);
				
			
			die(); // Ensure further execution stops after outputting the JavaScript
			}
			public function get_mptbm_map_search_result_redirect(){
				// Same caching hazard as get_mptbm_map_search_result() above --
				// this also renders choose_vehicles.php with a fresh nonce.
				nocache_headers();
				$this->verify_search_request(true);
				// Debug logging for redirect search initiation
				
				$price_based = $this->requested_search_price_mode();
				$_POST['price_based'] = $price_based;
				
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
				$start_coords = $this->posted_coordinates('start_place_coordinates');
				$end_coords = $this->posted_coordinates('end_place_coordinates');
				
				$server_data = false;
				if (!empty($start_coords) && !empty($end_coords)) {
					$s_lat = isset($start_coords['latitude']) ? $start_coords['latitude'] : '';
					$s_lng = isset($start_coords['longitude']) ? $start_coords['longitude'] : '';
					$e_lat = isset($end_coords['latitude']) ? $end_coords['latitude'] : '';
					$e_lng = isset($end_coords['longitude']) ? $end_coords['longitude'] : '';


					$server_data = $this->resolve_trip_distance($s_lat, $s_lng, $e_lat, $e_lng);
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
					MPTBM_Function::set_search_context(array(
						'distance'          => $server_data ? absint($server_data['distance']) : 0,
						'duration'          => $server_data ? absint($server_data['duration']) : 0,
						'distance_verified' => (bool) $server_data,
						'start_place'       => isset($_POST['start_place']) ? sanitize_text_field(wp_unslash($_POST['start_place'])) : '',
						'end_place'         => isset($_POST['end_place']) ? sanitize_text_field(wp_unslash($_POST['end_place'])) : '',
						'start_coords'      => MPTBM_Function::normalize_coordinates($start_coords),
						'end_coords'        => MPTBM_Function::normalize_coordinates($end_coords),
						'price_based'       => $price_based,
						'start_date'        => isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '',
						'start_time'        => isset($_POST['start_time']) ? sanitize_text_field(wp_unslash($_POST['start_time'])) : '',
						'booking_datetime'  => trim((isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '') . ' ' . str_replace('.', ':', isset($_POST['start_time']) ? sanitize_text_field(wp_unslash($_POST['start_time'])) : '')),
						'waiting_time'      => isset($_POST['waiting_time']) ? absint($_POST['waiting_time']) : 0,
						'two_way'           => isset($_POST['two_way']) ? max(1, absint($_POST['two_way'])) : 1,
						'fixed_time'        => isset($_POST['fixed_time']) ? max(0, (float) $_POST['fixed_time']) : 0,
						'extra_stop_places' => isset($_POST['mptbm_extra_stop_place']) ? array_values(array_filter(array_map('sanitize_text_field', (array) wp_unslash($_POST['mptbm_extra_stop_place'])))) : array(),
						'extra_stop_count'  => isset($_POST['mptbm_extra_stop_place']) ? count(array_filter((array) $_POST['mptbm_extra_stop_place'])) : 0,
						'return_date'       => isset($_POST['return_date']) ? sanitize_text_field(wp_unslash($_POST['return_date'])) : '',
						'return_time'       => isset($_POST['return_time']) ? sanitize_text_field(wp_unslash($_POST['return_time'])) : '',
						'return_datetime'   => trim((isset($_POST['return_date']) ? sanitize_text_field(wp_unslash($_POST['return_date'])) : '') . ' ' . str_replace('.', ':', isset($_POST['return_time']) ? sanitize_text_field(wp_unslash($_POST['return_time'])) : '')),
					));
					$pricing_mode_filter = static function($original_mode, $priced_vehicle_id) use ($price_based) {
						return $price_based;
					};
					add_filter('mptbm_original_price_based', $pricing_mode_filter, 99, 2);
					include(MPTBM_Function::template_path('registration/choose_vehicles.php'));
					remove_filter('mptbm_original_price_based', $pricing_mode_filter, 99);
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
					
					echo esc_url_raw($redirect_url);
				die(); // Ensure further execution stops after outputting the JavaScript
			}

			public function get_mptbm_end_place() {
				$this->verify_search_request();
				include(MPTBM_Function::template_path('registration/get_end_place.php'));
				die();
			}

			public function get_mptbm_vehicle_time_availability() {
				nocache_headers();
				$this->verify_search_request();

				$vehicle_id = isset($_POST['vehicle_id']) ? absint($_POST['vehicle_id']) : 0;
				$date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : '';
				$times = isset($_POST['times']) ? array_map('sanitize_text_field', (array) wp_unslash($_POST['times'])) : array();
				$price_based = isset($_POST['price_based']) ? sanitize_key(wp_unslash($_POST['price_based'])) : 'dynamic';
				$date_parts = explode('-', $date);

				if ($vehicle_id && !$this->validate_post_access($vehicle_id)) {
					wp_send_json_error(array('message' => esc_html__('Invalid transportation.', 'ecab-taxi-booking-manager')), 400);
				}
				if (count($date_parts) !== 3 || !checkdate((int) $date_parts[1], (int) $date_parts[2], (int) $date_parts[0])) {
					wp_send_json_error(array('message' => esc_html__('Invalid pickup date.', 'ecab-taxi-booking-manager')), 400);
				}

				if ($vehicle_id) {
					$inventory_enabled = get_post_meta($vehicle_id, 'mptbm_enable_inventory', true) === 'yes';
					$interval_minutes = max(0, (int) get_post_meta($vehicle_id, 'mptbm_booking_interval_time', true));
					$unavailable_times = MPTBM_Function::get_unavailable_time_slots($vehicle_id, $date, $times, !$inventory_enabled);
					/* translators: %d: booking interval in minutes. */
					$unavailable_title = $interval_minutes > 0
						? sprintf(esc_html__('Unavailable because this time overlaps a booking or its %d-minute interval.', 'ecab-taxi-booking-manager'), $interval_minutes)
						: esc_html__('Unavailable because this time overlaps an existing booking.', 'ecab-taxi-booking-manager');
				} else {
					$allowed_price_modes = array('dynamic', 'manual', 'fixed_hourly', 'fixed_zone', 'fixed_zone_dropoff', 'fixed_distance', 'fixed_map');
					$price_based = in_array($price_based, $allowed_price_modes, true) ? $price_based : 'dynamic';
					$unavailable_times = array_values(array_filter(array_map(function ($time) {
						$time = str_replace(':', '.', trim((string) $time));
						return preg_match('/^(?:[01]\d|2[0-3])\.[0-5]\d$/', $time) ? $time : '';
					}, array_slice($times, 0, 288))));
					$vehicles = MPTBM_Query::query_transport_list($price_based);

					foreach ($vehicles->posts as $vehicle) {
						$check_mode = get_post_meta($vehicle->ID, 'mptbm_availability_check_mode', true) ?: 'automatic';
						if ($check_mode === 'manual' && get_post_meta($vehicle->ID, 'mptbm_availability_status', true) === 'unavailable') {
							continue;
						}

						$inventory_enabled = get_post_meta($vehicle->ID, 'mptbm_enable_inventory', true) === 'yes';
						$vehicle_unavailable = MPTBM_Function::get_unavailable_time_slots($vehicle->ID, $date, $times, !$inventory_enabled);
						$unavailable_times = array_values(array_intersect($unavailable_times, $vehicle_unavailable));
						if (!$unavailable_times) {
							break;
						}
					}

					$interval_minutes = 0;
					$unavailable_title = esc_html__('Unavailable because all matching vehicles are booked for this time.', 'ecab-taxi-booking-manager');
				}

				wp_send_json_success(array(
					'unavailable_times' => $unavailable_times,
					'unavailable_label' => $vehicle_id ? esc_html__('Booked', 'ecab-taxi-booking-manager') : esc_html__('Fully booked', 'ecab-taxi-booking-manager'),
					'unavailable_title' => $unavailable_title,
					'interval_minutes' => $interval_minutes,
				));
			}

			// Builds the ordered pickup -> stop 1 -> ... -> dropoff waypoint list from POST and
			// resolves the total route distance/duration in one call. With no extra stops this
			// behaves exactly like the old direct pickup->dropoff calculation.
			private function get_server_distance_with_stops($start_lat, $start_lng, $end_lat, $end_lng) {
				$waypoints = $this->trip_waypoints($start_lat, $start_lng, $end_lat, $end_lng);
				if (!$waypoints) {
					return false;
				}

				return MPTBM_Function::get_server_distance_multi($waypoints);
			}

			// The ordered waypoint list for this search, or false when pickup/drop-off
			// coordinates are missing. Split out of get_server_distance_with_stops() so the
			// same list can also be used to sanity-check a browser-reported distance.
			private function trip_waypoints($start_lat, $start_lng, $end_lat, $end_lng) {
				if (!$start_lat || !$start_lng || !$end_lat || !$end_lng) {
					return false;
				}

				$waypoints = [['lat' => $start_lat, 'lng' => $start_lng]];

				$stop_coords_raw = isset($_POST['mptbm_extra_stop_place_coordinates']) ? $_POST['mptbm_extra_stop_place_coordinates'] : [];
				if (!is_array($stop_coords_raw)) {
					$stop_coords_raw = [$stop_coords_raw];
				}
				foreach (array_slice($stop_coords_raw, 0, 10) as $coord_str) {
					$coord_str = sanitize_text_field($coord_str);
					if (!$coord_str) {
						continue;
					}
					$parts = array_map('trim', explode(',', $coord_str));
					if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])
						&& (float) $parts[0] >= -90 && (float) $parts[0] <= 90
						&& (float) $parts[1] >= -180 && (float) $parts[1] <= 180) {
						$waypoints[] = ['lat' => (float) $parts[0], 'lng' => (float) $parts[1]];
					}
				}

				$waypoints[] = ['lat' => $end_lat, 'lng' => $end_lng];

				return $waypoints;
			}

			/**
			 * The distance and duration this search will be priced on.
			 *
			 * Default ('server') is unchanged: the server looks the route up itself, which
			 * is the safest source because nothing the customer sends can influence it.
			 *
			 * 'browser' exists for sites whose Google key is restricted to their own domain
			 * - a correct and common setup for the key that draws the map, but one Google
			 * refuses for server-side requests. Those sites get no Google answer on the
			 * server at all and silently price every trip off the OpenStreetMap fallback,
			 * which can quote a long detour where OSM's road data is incomplete and
			 * overcharge the customer. In that mode the accurate figure the browser already
			 * has is used instead, but only after MPTBM_Function::validate_client_trip()
			 * bounds it against the straight-line distance, and the server-side lookup still
			 * takes over whenever the reported figure doesn't hold up.
			 */
			private function resolve_trip_distance($start_lat, $start_lng, $end_lat, $end_lng) {
				$source = MP_Global_Function::get_settings('mptbm_map_api_settings', 'fare_distance_source', 'server');

				if ($source === 'browser') {
					$waypoints = $this->trip_waypoints($start_lat, $start_lng, $end_lat, $end_lng);
					if ($waypoints) {
						$claimed_distance = isset($_POST['mptbm_distance']) ? (float) $_POST['mptbm_distance'] : 0;
						$claimed_duration = isset($_POST['mptbm_duration']) ? (float) $_POST['mptbm_duration'] : 0;
						$verified = MPTBM_Function::validate_client_trip($claimed_distance, $claimed_duration, $waypoints);
						if ($verified) {
							return $verified;
						}
					}
				}

				return $this->get_server_distance_with_stops($start_lat, $start_lng, $end_lat, $end_lng);
			}

			/**
			 * The route distance/duration as the SERVER measures it, for the map's own
			 * "Total Distance / Total Time" bar.
			 *
			 * In OpenStreetMap mode the booking form used to call the public OSRM
			 * endpoint straight from the browser while the fare was measured separately
			 * on the server. Two independent lookups of the same trip means the number on
			 * screen and the number charged can disagree - and once a routing service
			 * other than OSRM is configured (Map API Settings > Routing Service) they
			 * disagree by design, because the browser would still be asking OSRM.
			 *
			 * Answering from the server instead collapses that back to one measurement,
			 * through the same resolver the price uses, so the bar and the fare can only
			 * ever show the same distance.
			 */
			public function get_mptbm_route_distance() {
				nocache_headers();
				$this->verify_search_request(true);

				$start = $this->posted_coordinates('start_place_coordinates');
				$end   = $this->posted_coordinates('end_place_coordinates');
				if (empty($start) || empty($end)) {
					wp_send_json_error(array('message' => esc_html__('Pick-up and drop-off coordinates are required.', 'ecab-taxi-booking-manager')));
				}

				$data = $this->resolve_trip_distance(
					$start['latitude'], $start['longitude'],
					$end['latitude'], $end['longitude']
				);
				if (!$data) {
					wp_send_json_error(array('message' => esc_html__('The route could not be calculated.', 'ecab-taxi-booking-manager')));
				}

				wp_send_json_success(array(
					'distance'      => (float) $data['distance'],
					'duration'      => (float) $data['duration'],
					'distance_text' => MPTBM_Function::format_distance_text($data['distance']),
					'duration_text' => MPTBM_Function::format_duration_text($data['duration']),
					'provider'      => isset($data['provider']) ? $data['provider'] : '',
				));
			}

			private function posted_coordinates($key): array {
				return isset($_POST[$key]) ? MPTBM_Function::normalize_coordinates($_POST[$key]) : array();
			}

			/**
			 * Convert a vehicle's admin pricing model into the public search-mode token.
			 *
			 * Distance, duration, distance + duration, and combined pricing all use the
			 * map-driven dynamic form; the vehicle's own meta selects the exact formula
			 * later in MPTBM_Function::get_price().
			 */
			private function vehicle_search_price_mode($vehicle_id): string {
				$model = sanitize_key((string) get_post_meta(absint($vehicle_id), 'mptbm_price_based', true));
				switch ($model) {
					case 'manual':
						return 'manual';
					case 'fixed_hourly':
						return 'fixed_hourly';
					case 'fixed_distance':
					case 'fixed_map':
						return 'fixed_map';
					case 'fixed_zone':
					case 'fixed_zone_dropoff':
						return $model;
					default:
						return 'dynamic';
				}
			}

			/** Use the locked single-page vehicle's model instead of trusting a posted mode. */
			private function requested_search_price_mode(): string {
				$requested = isset($_POST['price_based']) ? sanitize_key(wp_unslash($_POST['price_based'])) : 'dynamic';
				$allowed = array('dynamic', 'manual', 'fixed_hourly', 'fixed_distance', 'fixed_zone', 'fixed_zone_dropoff', 'fixed_map');
				$requested = in_array($requested, $allowed, true) ? $requested : 'dynamic';
				$vehicle_id = isset($_POST['mptbm_source_vehicle_id']) ? absint($_POST['mptbm_source_vehicle_id']) : 0;

				if (!$vehicle_id) {
					return $requested;
				}
				if (!$this->validate_post_access($vehicle_id)) {
					wp_send_json_error(array('message' => esc_html__('Invalid transportation.', 'ecab-taxi-booking-manager')), 400);
				}

				return $this->vehicle_search_price_mode($vehicle_id);
			}

			private function verify_search_request($rate_limit_route = false): void {
				check_ajax_referer('mptbm_transport_search', 'nonce');
				if (!$rate_limit_route) {
					return;
				}

				$client_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
				$rate_key = 'mptbm_route_rate_' . md5($client_ip);
				$request_count = (int) get_transient($rate_key);
				if ($request_count >= 20) {
					wp_send_json_error(array('message' => esc_html__('Too many route searches. Please wait a few minutes and try again.', 'ecab-taxi-booking-manager')), 429);
				}
				set_transient($rate_key, $request_count + 1, 5 * MINUTE_IN_SECONDS);
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
				$this->verify_search_request();
				$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
				
				// Security check: validate post access
				if (!$this->validate_post_access($post_id)) {
					wp_die(esc_html__('Invalid request or post not found.', 'ecab-taxi-booking-manager'), esc_html__('Error', 'ecab-taxi-booking-manager'), array('response' => 403));
				}
				
				include(MPTBM_Function::template_path('registration/extra_service.php'));
				die();
			}
			public function get_mptbm_extra_service_summary() {
				$this->verify_search_request();
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
