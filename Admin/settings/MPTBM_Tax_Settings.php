<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('MPTBM_Tax_Settings')) {
		class MPTBM_Tax_Settings {
			public function __construct() {
				add_action('add_mptbm_settings_tab_content', [$this, 'tab_content']);
				add_action('save_post', [$this, 'settings_save']);
			}
			public static function tab_content($post_id, $include_tab_wrapper = true) {
				?>
				<?php if ($include_tab_wrapper) : ?>
					<div class="tabsItem" data-tabs="#wbtm_settings_tax">
				<?php endif; ?>
					<?php
						$tax_status = MP_Global_Function::get_post_info($post_id, '_tax_status');
						$tax_class = MP_Global_Function::get_post_info($post_id, '_tax_class');
						$all_tax_class = MP_Global_Function::all_tax_list();
					?>
					<div class="mptbm_rent_editor_wrapper">
						<div class="mptbm_rent_editor_header">
							<div class="mptbm_rent_editor_title_group">
								<span class="mptbm_rent_editor_icon"><i class="fas fa-percentage"></i></span>
								<div>
									<h2 class="mptbm_rent_editor_title"><?php esc_html_e('Tax Configuration', 'ecab-taxi-booking-manager'); ?></h2>
									<p class="mptbm_rent_editor_subtitle"><?php esc_html_e('Configure and manage tax settings for this vehicle.', 'ecab-taxi-booking-manager'); ?></p>
								</div>
							</div>
						</div>
						<div class="mptbm_rent_editor_body">
							<?php if (get_option('woocommerce_calc_taxes') == 'yes') { ?>
								<div class="mptbm_taxi_advanced_card" style="margin-bottom: 0;">
									<div class="mptbm_taxi_advanced_card_header">
										<div class="mptbm_taxi_advanced_title_block">
											<label class="mptbm_rent_label"><?php esc_html_e('Tax status', 'ecab-taxi-booking-manager'); ?></label>
											<span class="desc"><?php esc_html_e('Select tax status type.', 'ecab-taxi-booking-manager'); ?></span>
										</div>
										<select class="formControl max_300" name="_tax_status">
											<option disabled <?php echo esc_attr(!$tax_status ? 'selected' : ''); ?>><?php esc_html_e('Please Select', 'ecab-taxi-booking-manager');  ?></option>
											<option value="taxable" <?php echo esc_attr($tax_status == 'taxable' ? 'selected' : ''); ?>>
												<?php esc_html_e('Taxable', 'ecab-taxi-booking-manager'); ?>
											</option>
											<option value="shipping" <?php echo esc_attr($tax_status == 'shipping' ? 'selected' : ''); ?>>
												<?php esc_html_e('Shipping only', 'ecab-taxi-booking-manager'); ?>
											</option>
											<option value="none" <?php echo esc_attr($tax_status == 'none' ? 'selected' : ''); ?>>
												<?php esc_html_e('None', 'ecab-taxi-booking-manager'); ?>
											</option>
										</select>
									</div>
								</div>

								<div class="mptbm_taxi_advanced_card" style="margin-top: 0; border-top: none;">
									<div class="mptbm_taxi_advanced_card_header">
										<div class="mptbm_taxi_advanced_title_block">
											<label class="mptbm_rent_label"><?php esc_html_e('Tax class', 'ecab-taxi-booking-manager'); ?></label>
											<span class="desc"><?php esc_html_e('Select tax class.', 'ecab-taxi-booking-manager'); ?></span>
										</div>
										<select class="formControl max_300" name="_tax_class">
											<option disabled <?php echo esc_attr(!$tax_class ? 'selected' : ''); ?>><?php esc_html_e('Please Select', 'ecab-taxi-booking-manager');  ?></option>
											<option value="standard" <?php echo esc_attr($tax_class == 'standard' ? 'selected' : ''); ?>>
												<?php esc_html_e('Standard', 'ecab-taxi-booking-manager'); ?>
											</option>
											<?php if (sizeof($all_tax_class) > 0) { ?>
												<?php foreach ($all_tax_class as $key => $class) { ?>
													<option value="<?php echo esc_attr($key); ?>" <?php echo esc_attr($tax_class == $key ? 'selected' : ''); ?>>
														<?php echo esc_html($class); ?>
													</option>
												<?php } ?>
											<?php } ?>
										</select>
									</div>
								</div>
							<?php }else{ ?>
								<div class="_dLayout_dFlex_justifyCenter">
									<?php MPTBM_Layout::msg(esc_html__('Tax not active. Please add Tax settings from woocommerce.', 'ecab-taxi-booking-manager')); ?>
								</div>
							<?php } ?>
						</div>
					</div>
				<?php if ($include_tab_wrapper) : ?>
					</div>
				<?php endif; ?>
				<?php
			}
			public function settings_save($post_id) {
				if (get_post_type($post_id) !== MPTBM_Function::get_cpt()
					|| (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
					|| wp_is_post_revision($post_id)
					|| !current_user_can('edit_post', $post_id)
					|| !isset($_POST['mptbm_transportation_type_nonce'])
					|| !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mptbm_transportation_type_nonce'])), 'mptbm_transportation_type_nonce')) {
					return;
				}
				if (get_post_type($post_id) == MPTBM_Function::get_cpt()) {
					$tax_status = MP_Global_Function::get_submit_info('_tax_status','none');
					$tax_class = MP_Global_Function::get_submit_info('_tax_class');
					$enable_tax = isset($_POST['mptbm_taxi_enable_tax']) ? 'on' : 'off';
					update_post_meta($post_id, '_tax_status', $tax_status);
					update_post_meta($post_id, '_tax_class', $tax_class);
					update_post_meta($post_id, 'mptbm_taxi_enable_tax', $enable_tax);
				}
			}
		}
		new MPTBM_Tax_Settings();
	}
