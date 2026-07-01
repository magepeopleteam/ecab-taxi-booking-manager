<?php
	/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('MPTBM_Admin')) {
		class MPTBM_Admin {
			public function __construct() {
			if (is_admin()) {
				$this->load_file();
				// NOTE: MPTBM_Dummy_Import self-instantiates at the bottom of its own
				// file. Instantiating it again here caused the demo popup to render
				// twice (duplicate element IDs broke the progress bar).
				$this->init_api_documentation();
				add_filter('use_block_editor_for_post_type', [$this, 'disable_gutenberg'], 10, 2);
				add_filter('wp_mail_content_type', array($this, 'email_content_type'));
				add_action('upgrader_process_complete', [$this, 'flush_rewrite'], 0);
			}
			}
			public function flush_rewrite() {
				flush_rewrite_rules();
			}
			private function load_file(): void {
			require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Dummy_Import.php';
			require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_CPT.php';
			require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Taxonomy_Meta.php';
			require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Status.php';
			require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Guideline.php';
			require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_License.php';
			require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Analytics_Dashboard.php';
			require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_API_Documentation.php';

			require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Transportation.php';
			require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Rent_Custom_Editor.php';

				
				//****************Global settings************************//
				require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Settings_Global.php';
				//****************Taxi settings************************//
				require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Settings.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/settings/MPTBM_General_Settings.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/settings/MPTBM_Price_Settings.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/settings/MPTBM_Extra_Service.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/settings/MPTBM_Operation_Areas.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/settings/MPTBM_Date_Settings.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/settings/MPTBM_Base_Price_Settings.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/settings/MPTBM_Tax_Settings.php';
                require_once MPTBM_PLUGIN_DIR . '/Admin/settings/MPTBM_taxi_Date_Advanced_Settings.php';
                require_once MPTBM_PLUGIN_DIR . '/Admin/settings/MPTBM_AJax_Handler.php';
                require_once MPTBM_PLUGIN_DIR . '/Admin/settings/MPTBM_Right_Side_Content_Settings.php';
				//****************Payment settings (WooCommerce / Custom Payment)********** */
				// Self-instantiating; methods guard WooCommerce availability internally so
				// the Payments tab renders in both WC and standalone (no-WC) modes.
				require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_WC_Payment_Manager.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/settings/MPTBM_Payment_Settings.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Payment_Notice.php';
				//****************Woocommerce Checkout*********************** */
				// WooCommerce checkout integration only loads when WooCommerce is active.
				if (MP_Global_Function::check_woocommerce() == 1) {
					require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Wc_Checkout_Billing.php';
					require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Wc_Checkout_Fields.php';
					require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Wc_Checkout_Order.php';
					require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Wc_Checkout_Settings.php';
					require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Wc_Checkout_Shipping.php';
				}

			}
		public function init_api_documentation() {
			new MPTBM_API_Documentation();
		}
			//************Disable Gutenberg************************//
			public function disable_gutenberg($current_status, $post_type) {
				$user_status = MP_Global_Function::get_settings('mp_global_settings', 'disable_block_editor', 'yes');
				if ($post_type === MPTBM_Function::get_cpt() && $user_status == 'yes') {
					return false;
				}
				return $current_status;
			}
			//*************************//
			public function email_content_type() {
				return "text/html";
			}
		}
		new MPTBM_Admin();
	}