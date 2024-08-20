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
			public function tab_content($post_id) {
				?>
				<div class="tabsItem" data-tabs="#wbtm_settings_tax">
					<h3><?php esc_html_e('Tax Configuration', 'ecab-taxi-booking-manager'); ?></h3>
					<p><?php esc_html_e('Tax Configuration settings.', 'ecab-taxi-booking-manager'); ?></p>
					<?php
						$tax_status = MP_Global_Function::get_post_info($post_id, '_tax_status');
						$tax_class = MP_Global_Function::get_post_info($post_id, '_tax_class');
						$all_tax_class = MP_Global_Function::all_tax_list();
					?>
					<div class="_dLayout_padding_bgLight">
						<div class="col_6 _dFlex_fdColumn">
							<label>
								<?php esc_html_e('Tax Settings Information', 'ecab-taxi-booking-manager'); ?> 
							</label>
							<span><?php esc_html_e('Here you can configure tax settings.', 'ecab-taxi-booking-manager'); ?></span>
						</div>
					</div>
					<?php if (get_option('woocommerce_calc_taxes') == 'yes') { ?>
						<div class="">
							<div class="_dLayout_dFlex_justifyBetween_alignCenter">
								<div class="col_6 _dFlex_fdColumn">
									<label>
										<?php esc_html_e('Tax status', 'ecab-taxi-booking-manager'); ?>
									</label>
									<span>
										<?php esc_html_e('Select tax status type.', 'ecab-taxi-booking-manager'); ?>
									</span>
								</div>
								<div class="col_6 textRight">
									<select class="formControl max_300" name="_tax_status">
											<option disabled selected><?php esc_html_e('Please Select', 'bus-ticket-booking-with-seat-reservation');  ?></option>
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

							<div class="_dLayout_dFlex_justifyBetween_alignCenter">
								<div class="col_6 _dFlex_fdColumn">
									<label>
										<?php esc_html_e('Tax class', 'ecab-taxi-booking-manager'); ?>
									</label>
									<?php MPTBM_Settings::info_text('tax_class'); ?>
								</div>
								<div class="col_6 textRight">
									<select class="formControl max_300" name="_tax_class">
										<option disabled selected><?php esc_html_e('Please Select', 'bus-ticket-booking-with-seat-reservation');  ?></option>
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
						</div>
					<?php }else{ ?>
						<div class="_dLayout_dFlex_justifyCenter">
							<?php MPTBM_Layout::msg(esc_html__('Tax not active. Please add Tax settings from woocommerce.', 'ecab-taxi-booking-manager')); ?>
						</div>
					<?php } ?>
				</div>
				<?php
			}
			public function settings_save($post_id) {
				if (!isset($_POST['mptbm_transportation_type_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mptbm_transportation_type_nonce'])), 'mptbm_transportation_type_nonce') && defined('DOING_AUTOSAVE') && DOING_AUTOSAVE && !current_user_can('edit_post', $post_id)) {
					return;
				}
				if (get_post_type($post_id) == MPTBM_Function::get_cpt()) {
					$tax_status = MP_Global_Function::get_submit_info('_tax_status','none');
					$tax_class = MP_Global_Function::get_submit_info('_tax_class');
					update_post_meta($post_id, '_tax_status', $tax_status);
					update_post_meta($post_id, '_tax_class', $tax_class);
				}
			}
		}
		new MPTBM_Tax_Settings();
	}