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
			public function default_text($day) {
				if ($day == 'default') {
					esc_html_e('Please select', 'ecab-taxi-booking-manager');
				}
				else {
					esc_html_e('Default', 'ecab-taxi-booking-manager');
				}
			}
			public function time_slot($time, $stat_time = '', $end_time = '') {
				if ($stat_time >= 0 || $stat_time == '') {
					$time_count = $stat_time == '' ? 0 : $stat_time;
					$end_time = $end_time != '' ? $end_time : 48*30;
					
					for ($i = 30; $i <= $end_time; $i += 30) {
						// Calculate hours and minutes
						$hours = floor($i / 60);
						$minutes = $i % 60;

						// Generate the data-value as hours + fraction (minutes / 60)
						$data_value = $hours + ($minutes / 100);

						// Format the time for display
						$time_formatted = sprintf('%02d:%02d', $hours, $minutes);
					?>
						<option  value="<?php echo esc_attr($data_value);?>" <?php echo esc_attr($time != '' && $time == $data_value ? 'selected' : '');?>><?php echo esc_html(MP_Global_Function::date_format($time_formatted, 'time')); ?></option>
					<?php }
				}
			}
			
			public function end_time_slot($post_id, $day, $start_time) {
				$end_name = 'mptbm_' . $day . '_end_time';
				$default_end_time = $day == 'default' ? 24 : '';
				$end_time = MP_Global_Function::get_post_info($post_id, $end_name, $default_end_time);
				?>
				<label>
					<select class="formControl " name="<?php echo esc_attr($end_name); ?>">
						<?php if ($start_time == '') { ?>
							<option value="" selected><?php $this->default_text($day); ?></option>
						<?php } ?>
						<?php $this->time_slot($end_time, $start_time); ?>
					</select>
				</label>
				<?php
			}
			/*************************************/
			public function get_mptbm_end_time_slot() {
				$post_id = isset($_REQUEST['post_id']) ? MP_Global_Function::data_sanitize($_REQUEST['post_id']) : '';
				$day = isset($_REQUEST['day_name']) ? MP_Global_Function::data_sanitize($_REQUEST['day_name']) : '';
				$start_time = isset($_REQUEST['start_time']) ? MP_Global_Function::data_sanitize($_REQUEST['start_time']) : '';
				$this->end_time_slot($post_id, $day, $start_time);
				die();
			}
			public function time_slot_tr($post_id, $day) {
				$start_name = 'mptbm_' . $day . '_start_time';
				$default_start_time = $day == 'default' ? 0.5 : '';
				
				$start_time = MP_Global_Function::get_post_info($post_id, $start_name, $default_start_time);
				
				$end_name = 'mptbm_' . $day . '_end_time';
				$default_end_time = $day == 'default' ? 24 : '';
				
				$end_time = MP_Global_Function::get_post_info($post_id, $end_name, $default_end_time);
				
				?>
				<tr>
					<th style="text-transform: capitalize;"><?php echo esc_html($day); ?></th>
					<td class="mptbm_start_time" data-day-name="<?php echo esc_attr($day); ?>">
						<label>
							<select class="formControl" name="<?php echo esc_attr($start_name); ?>">
								<option value="" <?php echo esc_attr($start_time == '' ? 'selected' : ''); ?>>
									<?php $this->default_text($day); ?>
								</option>
								<?php $this->time_slot($start_time); ?>
							</select>
						</label>
					</td>
					<td class="textCenter">
						<strong><?php esc_html_e('To', 'ecab-taxi-booking-manager'); ?></strong>
					</td>
					<td class="mptbm_end_time">
					<select class="formControl" name="<?php echo esc_attr($end_name); ?>">
								<option value="" <?php echo esc_attr($end_time == '' ? 'selected' : ''); ?>>
									<?php $this->default_text($day); ?>
								</option>
								<?php $this->time_slot($end_time); ?>
							</select>
						
					</td>
					
				</tr>
				<?php
			}
			
			public function date_settings($post_id) {
				$date_format = MP_Global_Function::date_picker_format();
				$now = date_i18n($date_format, strtotime(current_time('Y-m-d')));
				$date_type = MP_Global_Function::get_post_info($post_id, 'mptbm_date_type', 'repeated');
				?>
				<div class="tabsItem" data-tabs="#mptbm_settings_date">
					<h2><?php esc_html_e('Date Settings', 'ecab-taxi-booking-manager'); ?></h2>
					<p><?php _e('Here you can configure date.', 'ecab-taxi-booking-manager'); ?></p>
					<!-- General Date config -->
					<section class="bg-light">
						<h6><?php _e('General Date Configuration', 'ecab-taxi-booking-manager'); ?></h6>
						<span><?php _e('Here you can configure general date', 'ecab-taxi-booking-manager'); ?></span>
					</section>
					
					<section>
						<label class="label">
							<div>
								<h6><?php esc_html_e('Date Type', 'ecab-taxi-booking-manager'); ?><span class="textRequired">&nbsp;*</span></h6>
								<span class="desc"><?php _e('Specifies the date type: "Repeated" for recurring dates, or "Particular" for a specific date', "ecab-taxi-booking-manager"); ?></span>
							</div>
							<select class="formControl" name="mptbm_date_type" data-collapse-target required>
								<option disabled selected><?php esc_html_e('Please select ...', 'ecab-taxi-booking-manager'); ?></option>
								<option value="particular" data-option-target="#mp_particular" <?php echo esc_attr($date_type == 'particular' ? 'selected' : ''); ?>><?php esc_html_e('Particular', 'ecab-taxi-booking-manager'); ?></option>
								<option value="repeated" data-option-target="#mp_repeated" <?php echo esc_attr($date_type == 'repeated' ? 'selected' : ''); ?>><?php esc_html_e('Repeated', 'ecab-taxi-booking-manager'); ?></option>
							</select>
						</label>
					</section>
					<section data-collapse="#mp_particular" class="<?php echo esc_attr($date_type == 'particular' ? 'mActive' : ''); ?>">
						<label class="label" style="align-items: start;">
							<div>
								<h6><?php esc_html_e('Particular Dates', 'ecab-taxi-booking-manager'); ?></h6>
								<span class="desc"><?php esc_html_e('Add Particular Dates', 'ecab-taxi-booking-manager'); ?></span>
							</div>
							
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
									<?php MP_Custom_Layout::add_new_button(esc_html__('Add New Particular date', 'ecab-taxi-booking-manager')); ?>
									<div class="mp_hidden_content">
										<div class="mp_hidden_item">
											<?php $this->particular_date_item('mptbm_particular_dates[]'); ?>
										</div>
									</div>
								</div>
						</label>
					</section>
					<?php
						$repeated_start_date = MP_Global_Function::get_post_info($post_id, 'mptbm_repeated_start_date');
						$hidden_repeated_start_date = $repeated_start_date ? gmdate('Y-m-d', strtotime($repeated_start_date)) : '';
						$visible_repeated_start_date = $repeated_start_date ? date_i18n($date_format, strtotime($repeated_start_date)) : '';
						$repeated_after = MP_Global_Function::get_post_info($post_id, 'mptbm_repeated_after', 1);
						$active_days = MP_Global_Function::get_post_info($post_id, 'mptbm_active_days', 60);
						$available_for_all_time = MP_Global_Function::get_post_info($post_id, 'mptbm_available_for_all_time', 'on');
						$active = $available_for_all_time == 'off' ? '' : 'mActive';
						$checked = $available_for_all_time == 'off' ? '' : 'checked';
						
					?>
					<section data-collapse="#mp_repeated" class="<?php echo esc_attr($date_type == 'repeated' ? 'mActive' : ''); ?>">
						<label class="label">
							<div>
								<h6><?php esc_html_e('Repeated Start Date', 'ecab-taxi-booking-manager'); ?><span class="textRequired">&nbsp;*</span></h6>
								<span class="desc"><?php esc_html_e('Sets the start date for recurring services', 'ecab-taxi-booking-manager'); ?></span>
							</div>
							<div >
								<input type="hidden" name="mptbm_repeated_start_date" value="<?php echo esc_attr($hidden_repeated_start_date); ?>" required/>
								<input type="text" readonly required name="" class="formControl date_type" value="<?php echo esc_attr($visible_repeated_start_date); ?>" placeholder="<?php echo esc_attr($now); ?>"/>
							</div>
						</label>
					</section>
					
					<section data-collapse="#mp_repeated" class="<?php echo esc_attr($date_type == 'repeated' ? 'mActive' : ''); ?>">
						<label class="label">
							<div>
								<h6><?php esc_html_e('Repeated after', 'ecab-taxi-booking-manager'); ?><span class="textRequired">&nbsp;*</span></h6>
								<span class="desc"><?php esc_html_e('Defines the number of days after which the service or event will repeat', 'ecab-taxi-booking-manager'); ?></span>
							</div>
							<input type="text" name="mptbm_repeated_after" class="formControl mp_number_validation" value="<?php echo esc_attr($repeated_after); ?>"/>
						</label>
					</section>
					
					<section data-collapse="#mp_repeated" class="<?php echo esc_attr($date_type == 'repeated' ? 'mActive' : ''); ?>">
						<label class="label">
							<div>
								<h6><?php esc_html_e('Maximum Advanced Day Booking', 'ecab-taxi-booking-manager'); ?><span class="textRequired">&nbsp;*</span></h6>
								<span class="desc"><?php esc_html_e('Sets the maximum number of days in advance a booking can be made', 'ecab-taxi-booking-manager'); ?></span>
							</div>
							<input type="text" name="mptbm_active_days" class="formControl mp_number_validation" value="<?php echo esc_attr($active_days); ?>"/>
						</label>
					</section>
					
					<section>
						<label class="label">
							<div>
								<h6><?php esc_html_e('Make Transport Available For 24 Hours', 'ecab-taxi-booking-manager'); ?></h6>
								<span class="desc"><?php MPTBM_Settings::info_text('display_mptbm_features'); ?></span>
							</div>
							<?php MP_Custom_Layout::switch_button('mptbm_available_for_all_time', $checked); ?>
						</label>
					</section>
					
					<section class="bg-light" style="margin-top: 20px;">
						<h6><?php _e('Schedule Date Configuration', 'ecab-taxi-booking-manager'); ?></h6>
						<span><?php _e('Here you can configure Schedule date.', 'ecab-taxi-booking-manager'); ?></span>
					</section>
					<section>
						<table>
							<thead>
							<tr>
								<th><?php esc_html_e('Day', 'ecab-taxi-booking-manager'); ?></th>
								<th><?php esc_html_e('Start Time', 'ecab-taxi-booking-manager'); ?></th>
								<th><?php esc_html_e('To', 'ecab-taxi-booking-manager'); ?></th>
								<th><?php esc_html_e('End Time', 'ecab-taxi-booking-manager'); ?></th>
								
							</tr>
							</thead>
							<tbody>
							<?php $this->time_slot_tr($post_id, 'default');
							$days = MP_Global_Function::week_day();
							foreach ($days as $key => $day) {
								$this->time_slot_tr($post_id, $key);
							}
							?>
							</tbody>
						</table>
					</section>
					<!-- End Schedule date config -->
					
					<section class="bg-light" style="margin-top: 20px;">
						
						<h6><?php _e('Off Days & Dates Configuration', 'ecab-taxi-booking-manager'); ?></h6>
						<span><?php _e('Here you can configure Off Days & Dates.', 'ecab-taxi-booking-manager'); ?></span>
						
					</section>

					<section data-collapse="#mp_repeated" class="<?php echo esc_attr($date_type == 'repeated' ? 'mActive' : ''); ?>">
						<label class="label">
							<div>
								<h6><?php esc_html_e('Off Day', 'ecab-taxi-booking-manager'); ?></h6>
								<span class="desc"><?php esc_html_e('Select checkbox for off day', 'ecab-taxi-booking-manager'); ?></span>
							</div>
							<div>
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
											<span class="customCheckbox me-1"><?php echo esc_html($day); ?></span>
										</label>
									<?php } ?>
								</div>
							</div>
						</label>
					</section>

					<section>
						<label class="label" style="align-items: start;">
							<div>
								<h6><?php esc_html_e('Off Dates', 'ecab-taxi-booking-manager'); ?></h6>
								<span class="desc"><?php esc_html_e('Add off dates', 'ecab-taxi-booking-manager'); ?></span>
							</div>
							<div class="mp_settings_area">
								<div class="mp_item_insert mp_sortable_area mb-1">
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
								<?php MP_Custom_Layout::add_new_button(esc_html__('Add New Off date', 'ecab-taxi-booking-manager')); ?>
								<div class="mp_hidden_content">
									<div class="mp_hidden_item">
										<?php $this->particular_date_item('mptbm_off_dates[]'); ?>
									</div>
								</div>
							</div>
						</label>
					</section>

					<!-- End Off days and date config -->
				</div>
				<?php
			}
			public function particular_date_item($name, $date = '') {
				$date_format = MP_Global_Function::date_picker_format();
				$now = date_i18n($date_format, strtotime(current_time('Y-m-d')));
				$hidden_date = $date ? gmdate('Y-m-d', strtotime($date)) : '';
				$visible_date = $date ? date_i18n($date_format, strtotime($date)) : '';
				?>
				<div class="mp_remove_area my-1">
					<div class="justifyBetween bg-light p-1">
						<label class="col_8">
							<input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($hidden_date); ?>"/>
							<input value="<?php echo esc_attr($visible_date); ?>" class="formControl date_type" placeholder="<?php echo esc_attr($now); ?>"/>
						</label>
						<?php MP_Custom_Layout::move_remove_button(); ?>
					</div>

				</div>
				<?php
			}
			/*************************************/
			public function save_date_time_settings($post_id) {
				if (!isset($_POST['mptbm_transportation_type_nonce']) || !wp_verify_nonce(sanitize_text_field( wp_unslash ($_POST['mptbm_transportation_type_nonce'])), 'mptbm_transportation_type_nonce') && defined('DOING_AUTOSAVE') && DOING_AUTOSAVE && !current_user_can('edit_post', $post_id)) {
					return;
				}
				if (get_post_type($post_id) == MPTBM_Function::get_cpt()) {
					//************************************//
					$mptbm_date_type = isset($_POST['mptbm_date_type']) ? sanitize_text_field($_POST['mptbm_date_type']) : '';
					update_post_meta($post_id, 'mptbm_date_type', $mptbm_date_type);
					//**********************//
					


					$particular_dates = isset($_POST['mptbm_particular_dates']) ? array_map('sanitize_text_field',$_POST['mptbm_particular_dates']) : [];
					$particular = array();
					if (sizeof($particular_dates) > 0) {
						foreach ($particular_dates as $particular_date) {
							if ($particular_date) {
								$particular[] = gmdate('Y-m-d', strtotime($particular_date));
							}
						}
					}
					
					$mptbm_available_for_all_time = isset($_POST['mptbm_available_for_all_time']) && sanitize_text_field($_POST['mptbm_available_for_all_time'])? 'on' : 'off';
					update_post_meta($post_id, 'mptbm_available_for_all_time', $mptbm_available_for_all_time);

					update_post_meta($post_id, 'mptbm_particular_dates', $particular);
					//*************************//
					$repeated_start_date =  isset($_POST['mptbm_repeated_start_date']) ? sanitize_text_field($_POST['mptbm_repeated_start_date']) : '';
					$repeated_start_date = $repeated_start_date ? gmdate('Y-m-d', strtotime($repeated_start_date)) : '';
					update_post_meta($post_id, 'mptbm_repeated_start_date', $repeated_start_date);
					$repeated_after = isset($_POST['mptbm_repeated_after']) ? sanitize_text_field($_POST['mptbm_repeated_after']) : '';
					update_post_meta($post_id, 'mptbm_repeated_after', $repeated_after);
					$active_days = isset($_POST['mptbm_active_days']) ? sanitize_text_field($_POST['mptbm_active_days']) : '';
					update_post_meta($post_id, 'mptbm_active_days', $active_days);
					//**********************//
					if(isset($_POST['mptbm_off_days'])){
						$off_days_arr = explode(',', $_POST['mptbm_off_days']);
						$off_days = is_array($off_days_arr) ? array_map('sanitize_text_field',$off_days_arr) : [];
						$off_days = implode(',', $off_days);
						
						update_post_meta($post_id, 'mptbm_off_days', $off_days);
					}
					
					//**********************//
					$off_dates = isset($_POST['mptbm_off_dates']) && is_array($_POST['mptbm_off_dates']) ? array_map('sanitize_text_field',$_POST['mptbm_off_dates']) : [];
					$_off_dates = array();
					if (sizeof($off_dates) > 0) {
						foreach ($off_dates as $off_date) {
							if ($off_date) {
								$_off_dates[] = gmdate('Y-m-d', strtotime($off_date));
							}
						}
					}
					update_post_meta($post_id, 'mptbm_off_dates', $_off_dates);
					$this->save_schedule($post_id, 'default');
					$days = MP_Global_Function::week_day();
					foreach ($days as $key => $day) {
						$this->save_schedule($post_id, $key);
					}
					
				}
			}
			public  function get_submit_info($key, $default = '') {
				return $this->data_sanitize($_POST[$key] ?? $default);
			}
			public function data_sanitize($data) {
				if (is_string($data)) {
					// Attempt to unserialize safely
					$unserialized = @unserialize($data, ['allowed_classes' => false]);
					if ($unserialized !== false || $data === 'b:0;') {
						$data = $this->data_sanitize($unserialized);
					} else {
						// Sanitize string
						$data = sanitize_text_field(stripslashes(wp_strip_all_tags($data)));
					}
				} elseif (is_array($data)) {
					// Recursively sanitize arrays
					foreach ($data as &$value) {
						$value = $this->data_sanitize($value);
					}
				}
				return $data;
			}
			public function save_schedule($post_id, $day) {
				$start_name = 'mptbm_' . $day . '_start_time';
				$start_time = $this->get_submit_info($start_name);
				update_post_meta($post_id, $start_name, $start_time);
				$end_name = 'mptbm_' . $day . '_end_time';
				$end_time = $this->get_submit_info($end_name);
				update_post_meta($post_id, $end_name, $end_time);
				
			}
		}
		new MPTBM_Date_Settings();
	}