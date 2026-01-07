<?php
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	/**
	 * Class MPTBM_Wc_Checkout_Default
	 *
	 * @since 1.0
	 *
	 * */
	if (!class_exists('MPTBM_Wc_Checkout_Default')) {
		class MPTBM_Wc_Checkout_Default {
			private $error;
			public function __construct() {
				$this->error = new WP_Error();
				add_action('mptbm_wc_checkout_tab_content', array($this, 'tab_content'), 10, 1);
				add_action('mptbm_save_checkout_fields_settings', [$this, 'mptbm_save_checkout_fields_settings']);

				add_action('admin_notices', array($this, 'mp_admin_notice'));
			}


			public function tab_content($contents) {
				$check_order_additional_information_section='';
				$check_order_review_section='';
				$check_disable_custom_checkout='';
				if (MPTBM_Wc_Checkout_Fields_Helper::hide_checkout_order_additional_information_section()) {
					$check_order_additional_information_section = 'checked';
				}
				if (MPTBM_Wc_Checkout_Fields_Helper::hide_checkout_order_review_section()) {
					$check_order_review_section = 'checked';
				}
				if (MPTBM_Wc_Checkout_Fields_Helper::disable_custom_checkout_system()) {
					$check_disable_custom_checkout = 'checked';
				}
				?>
                <div class="tab-content" id="mptbm_wc_checkout_settings">
                    <h2>Checkout Settings</h2>
                    <!-- <table class="wc_gateways wp-list-table widefat striped"> -->
                    <div>
                        <form method="POST">
                            <input type="hidden" name="action" value="mptbm_wc_checkout_settings"/>
                            <table class="wc_gateways wp-list-table widefat striped">
                                <tbody>
                                <tr>
                                    <td><label for="disable_custom_checkout_system"><span class="span-checkout-setting">Disable Custom Checkout System</span></label></td>
                                    <td><?php MPTBM_Wc_Checkout_Fields::switch_button('disable_custom_checkout_system', 'checkoutSettingsSwitchButton', 'disable_custom_checkout_system', $check_disable_custom_checkout, null); ?></td>
                                </tr>
                                <tr>
                                    <td><label for="hide_checkout_order_additional_information"><span class="span-checkout-setting">Hide Order Additional Information Section</span></label></td>
                                    <td><?php MPTBM_Wc_Checkout_Fields::switch_button('hide_checkout_order_additional_information', 'checkoutSettingsSwitchButton', 'hide_checkout_order_additional_information', $check_order_additional_information_section, null); ?></td>
                                </tr>
                                <tr>
                                    <td><label for="hide_checkout_order_review"><span class="span-checkout-setting">Hide Order Review Section</span></label></td>
                                    <td><?php MPTBM_Wc_Checkout_Fields::switch_button('hide_checkout_order_review', 'checkoutSettingsSwitchButton', 'hide_checkout_order_review', $check_order_review_section, null); ?></td>
                                </tr>
                                </tbody>
                            </table>
                            <div class="action-button">
                                <p class="submit">
									<?php wp_nonce_field('mptbm_wc_checkout_settings', 'mptbm_wc_checkout_settings_nonce'); ?>
                                    <input type="submit" name="submit" class="button-primary" value="Submit">
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
				<?php
			}
			public function mptbm_save_checkout_fields_settings() {
				if (!current_user_can('administrator')) {
					wp_die(esc_html__('You do not have sufficient permissions to access this page.'));
				}
				$action = isset($_POST['action']) ? sanitize_text_field($_POST['action']) : null;
				if (isset($action) && $action == 'mptbm_wc_checkout_settings') {
					if (check_admin_referer('mptbm_wc_checkout_settings', 'mptbm_wc_checkout_settings_nonce')) {
						$disable_custom_checkout_system = isset($_POST['disable_custom_checkout_system']) ? sanitize_text_field($_POST['disable_custom_checkout_system']) : null;
						$hide_checkout_order_additional_information = isset($_POST['hide_checkout_order_additional_information']) ? sanitize_text_field($_POST['hide_checkout_order_additional_information']) : null;
						$hide_checkout_order_review = isset($_POST['hide_checkout_order_review']) ? sanitize_text_field($_POST['hide_checkout_order_review']) : null;
						$options = get_option('mptbm_custom_checkout_fields');
						if (!is_array($options)) {
							$options = array();
						}
						$options['disable_custom_checkout_system'] = $disable_custom_checkout_system;
						$options['hide_checkout_order_additional_information'] = $hide_checkout_order_additional_information;
						$options['hide_checkout_order_review'] = $hide_checkout_order_review;
						update_option('mptbm_custom_checkout_fields', $options);
					}
					wp_redirect(admin_url('edit.php?post_type=' . MPTBM_Function::get_cpt() . '&page=mptbm_wc_checkout_fields'));
				}
			}
			public function mp_admin_notice() {
				MPTBM_Wc_Checkout_Fields::mp_error_notice($this->error);
			}
		}
		new MPTBM_Wc_Checkout_Default();
	}