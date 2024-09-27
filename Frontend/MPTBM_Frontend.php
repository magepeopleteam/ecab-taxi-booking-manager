<?php
	/*
	* @Author 		magePeople
	* Copyright: 	mage-people.com
	*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('MPTBM_Frontend')) {
		class MPTBM_Frontend {
			public function __construct() {
				$this->load_file();
				add_filter('single_template', array($this, 'load_single_template'));
			}
			private function load_file(): void {
				require_once MPTBM_PLUGIN_DIR . '/Frontend/MPTBM_Shortcodes.php';
				require_once MPTBM_PLUGIN_DIR . '/Frontend/MPTBM_Transport_Search.php';
				require_once MPTBM_PLUGIN_DIR . '/Frontend/MPTBM_Woocommerce.php';
				require_once MPTBM_PLUGIN_DIR . '/Frontend/MPTBM_Wc_Checkout_Fields_Helper.php';
			}
			public function load_single_template($template): string {
				global $post;
				if ($post->post_type && $post->post_type == MPTBM_Function::get_cpt()) {
					$template = MPTBM_Function::template_path('single_page/mptbm_details.php');
				}
				if ($post->post_type && $post->post_type == 'transport_booking') {
					$template = MPTBM_Function::template_path('single_page/transport_booking.php');
				}
				return $template;
			}
		}
		new MPTBM_Frontend();
	}