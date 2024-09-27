<?php
	/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('MPTBM_Status')) {
		class MPTBM_Status {
			public function __construct() {
				add_action('admin_menu', array($this, 'status_menu'));
			}
			public function status_menu() {
				$cpt = MPTBM_Function::get_cpt();
				add_submenu_page('edit.php?post_type=' . $cpt, esc_html__('Status', 'ecab-taxi-booking-manager'), '<span style="color:yellow">' . esc_html__('Status', 'ecab-taxi-booking-manager') . '</span>', 'manage_options', 'mptbm_status_page', array($this, 'status_page'));
			}
			public function status_page() {
				$label = MPTBM_Function::get_name();
				$wc_i = MP_Global_Function::check_woocommerce();
				$wc_i_text = $wc_i == 1 ? esc_html__('Yes', 'ecab-taxi-booking-manager') : esc_html__('No', 'ecab-taxi-booking-manager');
				$wp_v = get_bloginfo('version');
				$wc_v = WC()->version;
				$from_name = get_option('woocommerce_email_from_name');
				$from_email = get_option('woocommerce_email_from_address');
				?>
				<div class="wrap"></div>
				<div class="mpStyle">
					<?php do_action('mp_status_notice_sec'); ?>
					<div class=_dShadow_6_adminLayout">
						<h2 class="textCenter"><?php echo esc_html($label) . '  ' . esc_html__('For Woocommerce Environment Status', 'ecab-taxi-booking-manager'); ?></h2>
						<div class="divider"></div>
						<table>
							<tbody>
							<tr>
								<th data-export-label="WC Version"><?php esc_html_e('WordPress Version : ', 'ecab-taxi-booking-manager'); ?></th>
								<th class="<?php echo esc_attr($wp_v > 5.5 ? 'textSuccess' : 'textWarning'); ?>">
									<span class="<?php echo esc_attr($wp_v > 5.5 ? 'far fa-check-circle' : 'fas fa-exclamation-triangle'); ?> mR_xs"></span><?php echo esc_html($wp_v); ?>
								</th>
							</tr>
							<tr>
								<th data-export-label="WC Version"><?php esc_html_e('Woocommerce Installed : ', 'ecab-taxi-booking-manager'); ?></th>
								<th class="<?php echo esc_attr($wc_i == 1 ? 'textSuccess' : 'textWarning'); ?>">
									<span class="<?php echo esc_attr($wc_i == 1 ? 'far fa-check-circle' : 'fas fa-exclamation-triangle'); ?> mR_xs"></span><?php echo esc_html($wc_i_text); ?>
								</th>
							</tr>
							<?php if ($wc_i == 1) { ?>
								<tr>
									<th data-export-label="WC Version"><?php esc_html_e('Woocommerce Version : ', 'ecab-taxi-booking-manager'); ?></th>
									<th class="<?php echo esc_attr($wc_v > 4.8 ? 'textSuccess' : 'textWarning'); ?>">
										<span class="<?php echo esc_attr($wc_v > 4.8 ? 'far fa-check-circle' : 'fas fa-exclamation-triangle'); ?> mR_xs"></span><?php echo esc_html($wc_v); ?>
									</th>
								</tr>
								<tr>
									<th data-export-label="WC Version"><?php esc_html_e('Name : ', 'ecab-taxi-booking-manager'); ?></th>
									<th class="<?php echo esc_attr($from_name ? 'textSuccess' : 'textWarning'); ?>">
										<span class="<?php echo esc_attr($from_name ? 'far fa-check-circle' : 'fas fa-exclamation-triangle'); ?> mR_xs"></span><?php echo esc_html($from_name); ?>
									</th>
								</tr>
								<tr>
									<th data-export-label="WC Version"><?php esc_html_e('Email Address : ', 'ecab-taxi-booking-manager'); ?></th>
									<th class="<?php echo esc_attr($from_email ? 'textSuccess' : 'textWarning'); ?>">
										<span class="<?php echo esc_attr($from_email ? 'far fa-check-circle' : 'fas fa-exclamation-triangle'); ?> mR_xs"></span><?php echo esc_html($from_email); ?>
									</th>
								</tr>
							<?php }
								do_action('mp_status_table_item_sec'); ?>
							</tbody>
						</table>
					</div>
				</div>
				<?php
			}
		}
		new MPTBM_Status();
	}