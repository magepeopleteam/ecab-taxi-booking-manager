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
				$wc_v = ($wc_i == 1 && function_exists('WC')) ? WC()->version : esc_html__('Not installed', 'ecab-taxi-booking-manager');
				$from_name = get_option('woocommerce_email_from_name');
				$from_email = get_option('woocommerce_email_from_address');
				wp_enqueue_style('mptbm-status-style', MPTBM_PLUGIN_URL . '/assets/admin/css/status.css', array(), time());
				MPTBM_Admin_Shell::render_shell_open();
				?>
				<div class="mpStyle mptbm-status-page">
					<?php do_action('mp_status_notice_sec'); ?>
					<div class="mptbm-status-header">
						<div class="mptbm-status-heading">
							<span class="mptbm-status-header-icon" aria-hidden="true"><i class="fas fa-heartbeat"></i></span>
							<div>
								<p class="mptbm-status-eyebrow"><?php esc_html_e('System diagnostics', 'ecab-taxi-booking-manager'); ?></p>
								<h1><?php echo esc_html($label) . ' ' . esc_html__('For Woocommerce Environment Status', 'ecab-taxi-booking-manager'); ?></h1>
								<p><?php esc_html_e('A quick health check of your WooCommerce environment and plugin requirements.', 'ecab-taxi-booking-manager'); ?></p>
							</div>
						</div>
					</div>
					<div class="mptbm-status-card">
						<div class="mptbm-status-card-header">
							<i class="fas fa-server" aria-hidden="true"></i>
							<h2><?php esc_html_e('Environment Checks', 'ecab-taxi-booking-manager'); ?></h2>
						</div>
						<table class="mptbm-status-table">
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
				MPTBM_Admin_Shell::render_shell_close();
			}
		}
		new MPTBM_Status();
	}