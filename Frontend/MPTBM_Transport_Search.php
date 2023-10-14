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
				/*********************/
				add_action('wp_ajax_get_mptbm_end_place', [$this, 'get_mptbm_end_place']);
				add_action('wp_ajax_nopriv_get_mptbm_end_place', [$this, 'get_mptbm_end_place']);
				/**************************/
				add_action('wp_ajax_get_mptbm_extra_service', [$this, 'get_mptbm_extra_service']);
				add_action('wp_ajax_nopriv_get_mptbm_extra_service', [$this, 'get_mptbm_extra_service']);
				/*******************************/
				add_action('wp_ajax_get_mptbm_extra_service_summary', [$this, 'get_mptbm_extra_service_summary']);
				add_action('wp_ajax_nopriv_get_mptbm_extra_service_summary', [$this, 'get_mptbm_extra_service_summary']);
			}
			public function transport_search($params) {
				$price_based = $params['price_based'] ?: 'dynamic';
				$progressbar = $params['progressbar'] ?: 'yes';
				$form_style= $params['form'] ?: 'horizontal';
				echo do_shortcode('[shop_messages]');
				//echo '<pre>';print_r($params);echo '</pre>';
				include(MPTBM_Function::template_path('registration/registration_layout.php'));
			}
			public function get_mptbm_map_search_result() {
				$distance = $_COOKIE['mptbm_distance'] ?? '';
				$duration = $_COOKIE['mptbm_duration'] ?? '';
				//if ($distance && $duration) {
					include(MPTBM_Function::template_path('registration/choose_vehicles.php'));
				//}
				die();
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