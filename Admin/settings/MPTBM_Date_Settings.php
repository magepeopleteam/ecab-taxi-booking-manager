<?php
	/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('MPTBM_Date_Settings')) {
		class MPTBM_Date_Settings {
			public function __construct() {
				add_action('add_mptbm_settings_tab_content', [$this, 'date_settings']);
				add_action('save_post', array($this, 'save_date_time_settings'), 99, 1);
			}
			public function date_settings($post_id) {
				$date_format = MP_Global_Function::date_picker_format();
				$now = date_i18n($date_format, strtotime(current_time('Y-m-d')));
				$date_type = MP_Global_Function::get_post_info($post_id, 'mptbm_date_type', 'repeated');
				?>
				<div class="tabsItem" data-tabs="#mptbm_settings_date">
					<h5><?php esc_html_e('Date Settings', 'mptbm_plugin'); ?></h5>
					<div class="divider"></div>
					<div class="mpTabs tabBorder">
						<ul class="tabLists">
							<li data-tabs-target="#mptbm_date_time_general">
								<span class="fas fa-home"></span><?php esc_html_e('General', 'mptbm_plugin'); ?>
							</li>
							<li data-tabs-target="#mptbm_date_time_off_day">
								<span class="fas fa-calendar-alt"></span><?php esc_html_e('Off Days & Dates', 'mptbm_plugin'); ?>
							</li>
						</ul>
						<div class="tabsContent tab-content">
							<div class="tabsItem" data-tabs="#mptbm_date_time_general">
								<table>
									<tbody>
									<tr>
										<th><?php esc_html_e('Date Type', 'mptbm_plugin'); ?>
											<span class="textRequired">&nbsp;*</span>
										</th>
										<td colspan="2">
											<label>
												<select class="formControl" name="mptbm_date_type" data-collapse-target required>
													<option disabled selected><?php esc_html_e('Please select ...', 'mptbm_plugin'); ?></option>
													<option value="particular" data-option-target="#mp_particular" <?php echo esc_attr($date_type == 'particular' ? 'selected' : ''); ?>><?php esc_html_e('Particular', 'mptbm_plugin'); ?></option>
													<option value="repeated" data-option-target="#mp_repeated" <?php echo esc_attr($date_type == 'repeated' ? 'selected' : ''); ?>><?php esc_html_e('Repeated', 'mptbm_plugin'); ?></option>
												</select>
											</label>
										</td>
									</tr>
									<tr data-collapse="#mp_particular" class="<?php echo esc_attr($date_type == 'particular' ? 'mActive' : ''); ?>">
										<th><?php esc_html_e('Particular Dates', 'mptbm_plugin'); ?></th>
										<td colspan="2">
											<div class="mp_settings_area">
												<div class="mp_item_insert mp_sortable_area">
													<?php
														$particular_date_lists = MP_Global_Function::get_post_info($post_id, 'mptbm_particular_dates', array());
														if (sizeof($particular_date_lists)) {
															foreach ($particular_date_lists as $particular_date) {
																if ($particular_date) {
																	$this->particular_date_item('mptbm_particular_dates[]', $particular_date);
																}
															}
														}
													?>
												</div>
												<?php MP_Custom_Layout::add_new_button(esc_html__('Add New Particular date', 'mptbm_plugin')); ?>
												<div class="mp_hidden_content">
													<div class="mp_hidden_item">
														<?php $this->particular_date_item('mptbm_particular_dates[]'); ?>
													</div>
												</div>
											</div>
										</td>
									</tr>
									<?php
										$repeated_start_date = MP_Global_Function::get_post_info($post_id, 'mptbm_repeated_start_date');
										$hidden_repeated_start_date = $repeated_start_date ? date('Y-m-d', strtotime($repeated_start_date)) : '';
										$visible_repeated_start_date = $repeated_start_date ? date_i18n($date_format, strtotime($repeated_start_date)) : '';
										$repeated_after = MP_Global_Function::get_post_info($post_id, 'mptbm_repeated_after', 1);
										$active_days = MP_Global_Function::get_post_info($post_id, 'mptbm_active_days', 10);
									?>
									<tr data-collapse="#mp_repeated" class="<?php echo esc_attr($date_type == 'repeated' ? 'mActive' : ''); ?>">
										<th>
											<?php esc_html_e('Repeated Start Date', 'mptbm_plugin'); ?>
											<span class="textRequired">&nbsp;*</span>
										</th>
										<td colspan="2">
											<label>
												<input type="hidden" name="mptbm_repeated_start_date" value="<?php echo esc_attr($hidden_repeated_start_date); ?>" required/>
												<input type="text" readonly required name="" class="formControl date_type" value="<?php echo esc_attr($visible_repeated_start_date); ?>" placeholder="<?php echo esc_attr($now); ?>"/>
											</label>
										</td>
									</tr>
									<tr data-collapse="#mp_repeated" class="<?php echo esc_attr($date_type == 'repeated' ? 'mActive' : ''); ?>">
										<th>
											<?php esc_html_e('Repeated after', 'mptbm_plugin'); ?>
											<span class="textRequired">&nbsp;*</span>
										</th>
										<td colspan="2">
											<label>
												<input type="text" name="mptbm_repeated_after" class="formControl mp_number_validation" value="<?php echo esc_attr($repeated_after); ?>"/>
											</label>
										</td>
									</tr>
									<tr data-collapse="#mp_repeated" class="<?php echo esc_attr($date_type == 'repeated' ? 'mActive' : ''); ?>">
										<th>
											<?php esc_html_e('Maximum advanced day booking', 'mptbm_plugin'); ?>
											<span class="textRequired">&nbsp;*</span>
										</th>
										<td colspan="2">
											<label>
												<input type="text" name="mptbm_active_days" class="formControl mp_number_validation" value="<?php echo esc_attr($active_days); ?>"/>
											</label>
										</td>
									</tr>
									</tbody>
								</table>
							</div>
							<div class="tabsItem" data-tabs="#mptbm_date_time_off_day">
								<table>
									<tr>
										<th><?php esc_html_e('Off Day', 'mptbm_plugin'); ?></th>
										<td colspan="2">
											<?php
												$off_days = MP_Global_Function::get_post_info($post_id, 'mptbm_off_days');
												$days = MP_Global_Function::week_day();
												$off_day_array = explode(',', $off_days);
											?>
											<div class="groupCheckBox">
												<input type="hidden" name="mptbm_off_days" value="<?php echo esc_attr($off_days); ?>"/>
												<?php foreach ($days as $key => $day) { ?>
													<label class="customCheckboxLabel">
														<input type="checkbox" <?php echo esc_attr(in_array($key, $off_day_array) ? 'checked' : ''); ?> data-checked="<?php echo esc_attr($key); ?>"/>
														<span class="customCheckbox"><?php echo esc_html($day); ?></span>
													</label>
												<?php } ?>
											</div>
										</td>
									</tr>
									<tr>
										<th><?php esc_html_e('Off Dates', 'mptbm_plugin'); ?></th>
										<td colspan="2">
											<div class="mp_settings_area">
												<div class="mp_item_insert mp_sortable_area">
													<?php
														$off_day_lists = MP_Global_Function::get_post_info($post_id, 'mptbm_off_dates', array());
														if (sizeof($off_day_lists)) {
															foreach ($off_day_lists as $off_day) {
																if ($off_day) {
																	$this->particular_date_item('mptbm_off_dates[]', $off_day);
																}
															}
														}
													?>
												</div>
												<?php MP_Custom_Layout::add_new_button(esc_html__('Add New Off date', 'mptbm_plugin')); ?>
												<div class="mp_hidden_content">
													<div class="mp_hidden_item">
														<?php $this->particular_date_item('mptbm_off_dates[]'); ?>
													</div>
												</div>
											</div>
										</td>
									</tr>
								</table>
							</div>
						</div>
					</div>
				</div>
				<?php
			}
			public function particular_date_item($name, $date = '') {
				$date_format = MP_Global_Function::date_picker_format();
				$now = date_i18n($date_format, strtotime(current_time('Y-m-d')));
				$hidden_date = $date ? date('Y-m-d', strtotime($date)) : '';
				$visible_date = $date ? date_i18n($date_format, strtotime($date)) : '';
				?>
				<div class="mp_remove_area">
					<div class="justifyBetween">
						<label class="col_8">
							<input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($hidden_date); ?>"/>
							<input value="<?php echo esc_attr($visible_date); ?>" class="formControl date_type" placeholder="<?php echo esc_attr($now); ?>"/>
						</label>
						<?php MP_Custom_Layout::move_remove_button(); ?>
					</div>
					<div class="divider"></div>
				</div>
				<?php
			}
			/*************************************/
			public function save_date_time_settings($post_id) {
				if (get_post_type($post_id) == MPTBM_Function::get_cpt()) {
					//************************************//
					$mptbm_date_type = MP_Global_Function::get_submit_info('mptbm_date_type');
					update_post_meta($post_id, 'mptbm_date_type', $mptbm_date_type);
					//**********************//
					$particular_dates = MP_Global_Function::get_submit_info('mptbm_particular_dates', array());
					$particular = array();
					if (sizeof($particular_dates) > 0) {
						foreach ($particular_dates as $particular_date) {
							if ($particular_date) {
								$particular[] = date('Y-m-d', strtotime($particular_date));
							}
						}
					}
					update_post_meta($post_id, 'mptbm_particular_dates', $particular);
					//*************************//
					$repeated_start_date = MP_Global_Function::get_submit_info('mptbm_repeated_start_date');
					$repeated_start_date = $repeated_start_date ? date('Y-m-d', strtotime($repeated_start_date)) : '';
					update_post_meta($post_id, 'mptbm_repeated_start_date', $repeated_start_date);
					$repeated_after = MP_Global_Function::get_submit_info('mptbm_repeated_after', 1);
					update_post_meta($post_id, 'mptbm_repeated_after', $repeated_after);
					$active_days = MP_Global_Function::get_submit_info('mptbm_active_days');
					update_post_meta($post_id, 'mptbm_active_days', $active_days);
					//**********************//
					$off_days = MP_Global_Function::get_submit_info('mptbm_off_days', array());
					update_post_meta($post_id, 'mptbm_off_days', $off_days);
					//**********************//
					$off_dates = MP_Global_Function::get_submit_info('mptbm_off_dates', array());
					$_off_dates = array();
					if (sizeof($off_dates) > 0) {
						foreach ($off_dates as $off_date) {
							if ($off_date) {
								$_off_dates[] = date('Y-m-d', strtotime($off_date));
							}
						}
					}
					update_post_meta($post_id, 'mptbm_off_dates', $_off_dates);
				}
			}
		}
		new MPTBM_Date_Settings();
	}