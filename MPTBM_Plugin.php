<?php
	/**
	 * Plugin Name: E-cab taxi booking manager
	 * Plugin URI: http://mage-people.com
	 * Description: A Complete Transportation Solution for WordPress by MagePeople.
	 * Version: 1.0.0
	 * Author: MagePeople Team
	 * Author URI: http://www.mage-people.com/
	 * Text Domain: mptbm_plugin
	 * Domain Path: /languages/
	 * WC requires at least: 3.0.9
	 * WC tested up to: 5.0
	 */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('MPTBM_Plugin')) {
		class MPTBM_Plugin {
			public function __construct() {
				$this->load_plugin();
			}
			private function load_plugin(): void {
				include_once(ABSPATH . 'wp-admin/includes/plugin.php');
				if (!defined('MPTBM_PLUGIN_DIR')) {
					define('MPTBM_PLUGIN_DIR', dirname(__FILE__));
				}
				if (!defined('MPTBM_PLUGIN_URL')) {
					define('MPTBM_PLUGIN_URL', plugins_url() . '/' . plugin_basename(dirname(__FILE__)));
				}
				$this->load_global_file();
				if (MP_Global_Function::check_woocommerce() == 1) {
					add_action('activated_plugin', array($this, 'activation_redirect'), 90, 1);
					register_activation_hook(__FILE__, array($this, 'on_activation_page_create'));
					require_once MPTBM_PLUGIN_DIR . '/inc/MPTBM_Dependencies.php';
				}
				else {
					require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Quick_Setup.php';
					add_action('admin_notices', [$this, 'woocommerce_not_active']);
					add_action('activated_plugin', array($this, 'activation_redirect_setup'), 90, 1);
				}
			}
			public function load_global_file() {
				require_once MPTBM_PLUGIN_DIR . '/inc/global/MP_Global_Function.php';
				require_once MPTBM_PLUGIN_DIR . '/inc/global/MP_Global_Style.php';
				require_once MPTBM_PLUGIN_DIR . '/inc/global/MP_Custom_Layout.php';
				require_once MPTBM_PLUGIN_DIR . '/inc/global/MP_Custom_Slider.php';
				require_once MPTBM_PLUGIN_DIR . '/inc/global/MP_Select_Icon_image.php';
			}
			public function activation_redirect($plugin) {
				if ($plugin == plugin_basename(__FILE__)) {
					exit(wp_redirect(admin_url('edit.php?post_type=mptbm_rent&page=mptbm_quick_setup')));
				}
			}
			public function activation_redirect_setup($plugin) {
				if ($plugin == plugin_basename(__FILE__)) {
					exit(wp_redirect(admin_url('admin.php?post_type=mptbm_rent&page=mptbm_quick_setup')));
				}
			}
			public function on_activation_page_create(): void {
				if (!MP_Global_Function::get_page_by_slug('transport_booking')) {
					$transport_booking = array(
						'post_type' => 'page',
						'post_name' => 'transport_booking',
						'post_title' => 'Transport Booking',
						'post_content' => '[mptbm_booking]',
						'post_status' => 'publish',
					);
					wp_insert_post($transport_booking);
					flush_rewrite_rules();
				}
			}
			public function woocommerce_not_active() {
				$wc_install_url = get_admin_url() . 'plugin-install.php?s=woocommerce&tab=search&type=term';
				printf('<div class="error" style="background:red; color:#fff;"><p>%s</p></div>', __('You Must Install WooCommerce Plugin before activating E-cab taxi booking manager, Because It is dependent on Woocommerce Plugin. <a class="btn button" href=' . $wc_install_url . '>Click Here to Install</a>'));
			}
		}
		new MPTBM_Plugin();
	}