<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('MPTBM_Quick_Setup')) {
		class MPTBM_Quick_Setup {
			public function __construct() {
				if (!class_exists('MPTBM_Dependencies')) {
					add_action('admin_enqueue_scripts', array($this, 'add_admin_scripts'));
				}
				add_action('admin_menu', array($this, 'quick_setup_menu'));
			}
			public function add_admin_scripts() {
				wp_enqueue_style('mp_plugin_global', MPTBM_PLUGIN_URL . '/assets/helper/mp_style/mp_style.css', array(), time());
				wp_enqueue_script('mp_plugin_global', MPTBM_PLUGIN_URL . '/assets/helper/mp_style/mp_script.js', array('jquery'), time(), true);
				wp_enqueue_script('mp_admin_settings', MPTBM_PLUGIN_URL . '/assets/admin/mp_admin_settings.js', array('jquery'), time(), true);
				wp_enqueue_style('mp_admin_settings', MPTBM_PLUGIN_URL . '/assets/admin/mp_admin_settings.css', array(), time());
				wp_enqueue_style('mp_font_awesome', '//cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css', array(), '5.15.4');
			}
			public function quick_setup_menu() {
				$status = MP_Global_Function::check_woocommerce();
				if ($status == 1) {
					add_submenu_page('edit.php?post_type=mptbm_rent', __('Quick Setup', 'mptbm_plugin'), '<span style="color:#10dd10">' . esc_html__('Quick Setup', 'mptbm_plugin') . '</span>', 'manage_options', 'mptbm_quick_setup', array($this, 'quick_setup'));
					add_submenu_page('mptbm_rent', esc_html__('Quick Setup', 'mptbm_plugin'), '<span style="color:#10dd10">' . esc_html__('Quick Setup', 'mptbm_plugin') . '</span>', 'manage_options', 'mptbm_quick_setup', array($this, 'quick_setup'));
				}
				else {
					add_menu_page(esc_html__('Transportation', 'mptbm_plugin'), esc_html__('Transportation', 'mptbm_plugin'), 'manage_options', 'mptbm_rent', array($this, 'quick_setup'), 'dashicons-car', 6);
					add_submenu_page('mptbm_rent', esc_html__('Quick Setup', 'mptbm_plugin'), '<span style="color:#10dd17">' . esc_html__('Quick Setup', 'mptbm_plugin') . '</span>', 'manage_options', 'mptbm_quick_setup', array($this, 'quick_setup'));
				}
			}
			public function quick_setup() {
				$status = MP_Global_Function::check_woocommerce();
				if (isset($_POST['active_woo_btn'])) {
					?>
					<script>
						dLoaderBody();
					</script>
					<?php
					activate_plugin('woocommerce/woocommerce.php');
					MPTBM_Plugin::on_activation_page_create();
					?>
					<script>
						(function ($) {
							"use strict";
							$(document).ready(function () {
								let mptbm_admin_location = window.location.href;
								mptbm_admin_location = mptbm_admin_location.replace('admin.php?post_type=mptbm_rent&page=mptbm_quick_setup', 'edit.php?post_type=mptbm_rent&page=mptbm_quick_setup');
								mptbm_admin_location = mptbm_admin_location.replace('admin.php?page=mptbm_rent', 'edit.php?post_type=mptbm_rent&page=mptbm_quick_setup');
								mptbm_admin_location = mptbm_admin_location.replace('admin.php?page=mptbm_quick_setup', 'edit.php?post_type=mptbm_rent&page=mptbm_quick_setup');
								window.location.href = mptbm_admin_location;
							});
						}(jQuery));
					</script>
					<?php
				}
				if (isset($_POST['install_and_active_woo_btn'])) {
					echo '<div style="display:none">';
					include_once(ABSPATH . 'wp-admin/includes/plugin-install.php');
					include_once(ABSPATH . 'wp-admin/includes/file.php');
					include_once(ABSPATH . 'wp-admin/includes/misc.php');
					include_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
					$plugin = 'woocommerce';
					$api = plugins_api('plugin_information', array(
						'slug' => $plugin,
						'fields' => array(
							'short_description' => false,
							'sections' => false,
							'requires' => false,
							'rating' => false,
							'ratings' => false,
							'downloaded' => false,
							'last_updated' => false,
							'added' => false,
							'tags' => false,
							'compatibility' => false,
							'homepage' => false,
							'donate_link' => false,
						),
					));
					$title = 'title';
					$url = 'url';
					$nonce = 'nonce';
					$woocommerce_plugin = new Plugin_Upgrader(new Plugin_Installer_Skin(compact('title', 'url', 'nonce', 'plugin', 'api')));
					$woocommerce_plugin->install($api->download_link);
					activate_plugin('woocommerce/woocommerce.php');
					MPTBM_Plugin::on_activation_page_create();
					echo '</div>';
					?>
					<script>
						(function ($) {
							"use strict";
							$(document).ready(function () {
								let mptbm_admin_location = window.location.href;
								mptbm_admin_location = mptbm_admin_location.replace('admin.php?post_type=mptbm_rent&page=mptbm_quick_setup', 'edit.php?post_type=mptbm_rent&page=mptbm_quick_setup');
								mptbm_admin_location = mptbm_admin_location.replace('admin.php?page=mptbm_rent', 'edit.php?post_type=mptbm_rent&page=mptbm_quick_setup');
								mptbm_admin_location = mptbm_admin_location.replace('admin.php?page=mptbm_quick_setup', 'edit.php?post_type=mptbm_rent&page=mptbm_quick_setup');
								window.location.href = mptbm_admin_location;
							});
						}(jQuery));
					</script>
					<?php
				}
				if (isset($_POST['finish_quick_setup'])) {
					$label = isset($_POST['mptbm_label']) ? sanitize_text_field($_POST['mptbm_label']) : 'Transportation';
					$slug = isset($_POST['mptbm_slug']) ? sanitize_text_field($_POST['mptbm_slug']) : 'transportation';
					$general_settings_data = get_option('mptbm_general_settings');
					$update_general_settings_arr = [
						'label' => $label,
						'slug' => $slug
					];
					$new_general_settings_data = is_array($general_settings_data) ? array_replace($general_settings_data, $update_general_settings_arr) : $update_general_settings_arr;
					update_option('mptbm_general_settings', $new_general_settings_data);
					wp_redirect(admin_url('edit.php?post_type=mptbm_rent'));
				}
				?>
				<div class="mpStyle">
					<div class=_dShadow_6_adminLayout">
						<form method="post" action="">
							<div class="mpTabsNext">
								<div class="tabListsNext _max_700_mAuto">
									<div data-tabs-target-next="#mptbm_qs_welcome" class="tabItemNext" data-open-text="1" data-close-text=" " data-open-icon="" data-close-icon="fas fa-check" data-add-class="success">
										<h4 class="circleIcon" data-class>
											<span class="mp_zero" data-icon></span>
											<span class="mp_zero" data-text>1</span>
										</h4>
										<h6 class="circleTitle" data-class><?php esc_html_e('Welcome', 'mptbm_plugin'); ?></h6>
									</div>
									<div data-tabs-target-next="#mptbm_qs_general" class="tabItemNext" data-open-text="2" data-close-text="" data-open-icon="" data-close-icon="fas fa-check" data-add-class="success">
										<h4 class="circleIcon" data-class>
											<span class="mp_zero" data-icon></span>
											<span class="mp_zero" data-text>2</span>
										</h4>
										<h6 class="circleTitle" data-class><?php esc_html_e('General', 'mptbm_plugin'); ?></h6>
									</div>
									<div data-tabs-target-next="#mptbm_qs_done" class="tabItemNext" data-open-text="3" data-close-text="" data-open-icon="" data-close-icon="fas fa-check" data-add-class="success">
										<h4 class="circleIcon" data-class>
											<span class="mp_zero" data-icon></span>
											<span class="mp_zero" data-text>3</span>
										</h4>
										<h6 class="circleTitle" data-class><?php esc_html_e('Done', 'mptbm_plugin'); ?></h6>
									</div>
								</div>
								<div class="tabsContentNext _infoLayout_mT">
									<?php
										$this->setup_welcome_content();
										$this->setup_general_content();
										$this->setup_content_done();
									?>
								</div>
								<?php if ($status == 1) { ?>
									<div class="justifyBetween">
										<button type="button" class="_mpBtn_dBR nextTab_prev">
											<span>&longleftarrow;<?php esc_html_e('Previous', 'mptbm_plugin'); ?></span>
										</button>
										<div></div>
										<button type="button" class="_themeButton_dBR nextTab_next">
											<span><?php esc_html_e('Next', 'mptbm_plugin'); ?>&longrightarrow;</span>
										</button>
									</div>
								<?php } ?>
							</div>
						</form>
					</div>
				</div>
				<?php
			}
			public function setup_welcome_content() {
				$status = MP_Global_Function::check_woocommerce();
				?>
				<div data-tabs-next="#mptbm_qs_welcome">
					<h2><?php esc_html_e('E-cab taxi Booking Manager For Woocommerce Plugin', 'mptbm_plugin'); ?></h2>
					<p class="mTB_xs"><?php esc_html_e('E-cab taxi booking manager Plugin for WooCommerce for your site, Please go step by step and choose some options to get started.', 'mptbm_plugin'); ?></p>
					<div class="_dLayout_mT_alignCenter justifyBetween">
						<h5>
							<?php if ($status == 1) {
								esc_html_e('Woocommerce already installed and activated', 'mptbm_plugin');
							}
							elseif ($status == 0) {
								esc_html_e('Woocommerce need to install and active', 'mptbm_plugin');
							}
							else {
								esc_html_e('Woocommerce already install , please activate it', 'mptbm_plugin');
							} ?>
						</h5>
						<?php if ($status == 1) { ?>
							<h5>
								<span class="fas fa-check-circle textSuccess"></span>
							</h5>
						<?php } elseif ($status == 0) { ?>
							<button class="_warningButton_dBR" type="submit" name="install_and_active_woo_btn"><?php esc_html_e('Install & Active Now', 'mptbm_plugin'); ?></button>
						<?php } else { ?>
							<button class="_themeButton_dBR" type="submit" name="active_woo_btn"><?php esc_html_e('Active Now', 'mptbm_plugin'); ?></button>
						<?php } ?>
					</div>
				</div>
				<?php
			}
			public function setup_general_content() {
				$label = MP_Global_Function::get_settings('mptbm_general_settings', 'label', 'Transportation');
				$slug = MP_Global_Function::get_settings('mptbm_general_settings', 'slug', 'transportation');
				?>
				<div data-tabs-next="#mptbm_qs_general">
					<div class="section">
						<h2><?php esc_html_e('General settings', 'mptbm_plugin'); ?></h2>
						<p class="mTB_xs"><?php esc_html_e('Choose some general option.', 'mptbm_plugin'); ?></p>
						<div class="_dLayout_mT">
							<label class="_fullWidth">
								<span class="min_200"><?php esc_html_e('Transportation Label:', 'mptbm_plugin'); ?></span>
								<input type="text" class="formControl" name="mptbm_label" value='<?php echo esc_attr($label); ?>'/>
							</label>
							<i class="info_text">
								<span class="fas fa-info-circle"></span>
								<?php esc_html_e('It will change the Transportation post type label on the entire plugin.', 'mptbm_plugin'); ?>
							</i>
							<div class="divider"></div>
							<label class="_fullWidth">
								<span class="min_200"><?php esc_html_e('Transportation Slug:', 'mptbm_plugin'); ?></span>
								<input type="text" class="formControl" name="mptbm_slug" value='<?php echo esc_attr($slug); ?>'/>
							</label>
							<i class="info_text">
								<span class="fas fa-info-circle"></span>
								<?php esc_html_e('It will change the Transportation slug on the entire plugin. Remember after changing this slug you need to flush permalinks. Just go to Settings->Permalinks hit the Save Settings button', 'mptbm_plugin'); ?>
							</i>
						</div>
					</div>
				</div>
				<?php
			}
			public function setup_content_done() {
				?>
				<div data-tabs-next="#mptbm_qs_done">
					<h2><?php esc_html_e('Finalize Setup', 'mptbm_plugin'); ?></h2>
					<p class="mTB_xs"><?php esc_html_e('You are about to Finish & Save Transportation Booking Manager For Woocommerce Plugin setup process', 'mptbm_plugin'); ?></p>
					<div class="mT allCenter">
						<button type="submit" name="finish_quick_setup" class="_themeButton_dBR"><?php esc_html_e('Finish & Save', 'mptbm_plugin'); ?></button>
					</div>
				</div>
				<?php
			}
		}
		new MPTBM_Quick_Setup();
	}