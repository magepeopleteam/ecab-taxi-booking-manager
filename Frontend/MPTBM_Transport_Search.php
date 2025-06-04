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
				$tabs = $params['tabs'] ?: 'distance,hourly,manually';
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
					if ($tab_id === 'distance' || $tab_id === 'hourly' || $tab_id === 'flat-rate') {
						ob_start(); // Start output buffering
						if($tab_id === 'distance'){
							$price_based = 'dynamic';
						}else if($tab_id === 'hourly'){
							$price_based = 'fixed_hourly';
						}else{
							$price_based = 'manual';
							$form_style = 'inline';
						}
						include MPTBM_Function::template_path('registration/get_details.php');
						$content = ob_get_clean(); // Get the template output
						echo $content;
					}
				}
				wp_die(); // End AJAX call
			}
			public function get_mptbm_map_search_result() {
					$distance = isset($_COOKIE['mptbm_distance']) ? absint($_COOKIE['mptbm_distance']) : '';
					$duration = isset($_COOKIE['mptbm_duration']) ? absint($_COOKIE['mptbm_duration']) : '';
					// if ($distance && $duration) {
						include(MPTBM_Function::template_path('registration/choose_vehicles.php'));
					// }
				
				die(); // Ensure further execution stops after outputting the JavaScript
			}
			public function get_mptbm_map_search_result_redirect(){
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
					$redirect_url = isset($_POST['mptbm_enable_view_search_result_page']) ? sanitize_text_field($_POST['mptbm_enable_view_search_result_page']) : '';
					if($redirect_url == ''){
						$redirect_url = 'transport-result';	
					}
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