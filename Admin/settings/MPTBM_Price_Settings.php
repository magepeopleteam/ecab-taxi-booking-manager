<?php
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('MPTBM_Price_Settings')) {
		class MPTBM_Price_Settings {
			public function __construct() {
				add_action('add_mptbm_settings_tab_content', [$this, 'price_settings'], 10, 1);
				add_action('mptbm_settings_save', [$this, 'save_price_settings'], 10, 1);
			}
			public function price_settings($post_id) {
				$price_based = MP_Global_Function::get_post_info($post_id, 'mptbm_price_based');
				$distance_price = MP_Global_Function::get_post_info($post_id, 'mptbm_km_price');
				$time_price = MP_Global_Function::get_post_info($post_id, 'mptbm_hour_price');
				$manual_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_manual_price_info', []);
				?>
                <div class="tabsItem" data-tabs="#mptbm_settings_pricing">
                    <h5><?php esc_html_e('Price Settings', 'mptbm_plugin'); ?></h5>
                    <div class="divider"></div>
                    <table>
                        <tbody>
                        <tr>
                            <th> <?php esc_html_e('Pricing based on', 'mptbm_plugin'); ?> </th>
                            <td>
                                <label>
                                    <select class="formControl" name="mptbm_price_based" data-collapse-target>
                                        <option disabled selected><?php esc_html_e('Please select ...', 'mptbm_plugin'); ?></option>
                                        <option value="distance" data-option-target="#mp_distance" <?php echo esc_attr($price_based == 'distance' ? 'selected' : ''); ?>><?php esc_html_e('Distance', 'mptbm_plugin'); ?></option>
                                        <option value="duration" data-option-target="#mp_duration" <?php echo esc_attr($price_based == 'duration' ? 'selected' : ''); ?>><?php esc_html_e('Duration/Time', 'mptbm_plugin'); ?></option>
                                        <option value="distance_duration" data-option-target data-option-target-multi="#mp_distance #mp_duration" <?php echo esc_attr($price_based == 'distance_duration' ? 'selected' : ''); ?>><?php esc_html_e('Distance + Duration', 'mptbm_plugin'); ?></option>
                                        <option value="manual" data-option-target="#mp_manual" <?php echo esc_attr($price_based == 'manual' ? 'selected' : ''); ?>><?php esc_html_e('Manual', 'mptbm_plugin'); ?></option>
                                    </select>
                                </label>
                            </td>
                            <td></td>
                        </tr>
                        <tr data-collapse="#mp_distance" class="<?php echo esc_attr($price_based == 'distance' || $price_based == 'distance_duration' ? 'mActive' : ''); ?>">
                            <th> <?php esc_html_e('Price/KM', 'mptbm_plugin'); ?> </th>
                            <td>
                                <label>
                                    <input class="formControl mp_price_validation" name="mptbm_km_price" value="<?php echo esc_attr($distance_price); ?>" type="text" placeholder="<?php esc_html_e('EX:10', 'mptbm_plugin'); ?>"/>
                                </label>
                            </td>
                            <td></td>
                        </tr>
                        <tr data-collapse="#mp_duration" class="<?php echo esc_attr($price_based == 'duration' || $price_based == 'distance_duration' ? 'mActive' : ''); ?>">
                            <th> <?php esc_html_e('Price/Hour', 'mptbm_plugin'); ?> </th>
                            <td>
                                <label>
                                    <input class="formControl mp_price_validation" name="mptbm_hour_price" value="<?php echo esc_attr($time_price); ?>" type="text" placeholder="<?php esc_html_e('EX:10', 'mptbm_plugin'); ?>"/>
                                </label>
                            </td>
                            <td></td>
                        </tr>
                        <tr data-collapse="#mp_manual" class="<?php echo esc_attr($price_based == 'manual' ? 'mActive' : ''); ?>">
                            <th> <?php esc_html_e('Manual Price', 'mptbm_plugin'); ?> </th>
                            <td colspan="2" class="mp_settings_area">
                                <table>
                                    <thead>
                                    <tr>
                                        <th><?php _e('Start Location', 'mptbm_plugin'); ?><span class="textRequired">&nbsp;*</span></th>
                                        <th><?php _e('End Location', 'mptbm_plugin'); ?><span class="textRequired">&nbsp;*</span></th>
                                        <th><?php _e('Price', 'mptbm_plugin'); ?><span class="textRequired">&nbsp;*</span></th>
                                        <th class="w_100"><?php _e('Action', 'mptbm_plugin'); ?></th>
                                    </tr>
                                    </thead>
                                    <tbody class="mp_sortable_area mp_item_insert">
									<?php
										if (sizeof($manual_prices) > 0) {
											foreach ($manual_prices as $manual_price) {
												$this->manual_price_item($manual_price);
											}
										}
									?>
                                    </tbody>
                                </table>
								<?php MP_Custom_Layout::add_new_button(esc_html__('Add New Price', 'mptbm_plugin')); ?>
								<?php $this->hidden_manual_price_item(); ?>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
				<?php
			}
			public function hidden_manual_price_item() {
				?>
                <div class="mp_hidden_content">
                    <table>
                        <tbody class="mp_hidden_item">
						<?php $this->manual_price_item(); ?>
                        </tbody>
                    </table>
                </div>
				<?php
			}
			public function manual_price_item($manual_price = array()) {
				$manual_price = $manual_price && is_array($manual_price) ? $manual_price : array();
				$start_location = array_key_exists('start_location', $manual_price) ? $manual_price['start_location'] : '';
				$end_location = array_key_exists('end_location', $manual_price) ? $manual_price['end_location'] : '';
				$price = array_key_exists('price', $manual_price) ? $manual_price['price'] : '';
				?>
                <tr class="mp_remove_area">
                    <td>
                        <label>
                            <input type="text" name="mptbm_manual_start_location[]" class="formControl mp_name_validation" value="<?php echo $start_location; ?>" placeholder="<?php esc_attr_e('EX:Dhaka', 'mptbm_plugin'); ?>"/>
                        </label>
                    </td>
                    <td>
                        <label>
                            <input type="text" name="mptbm_manual_end_location[]" class="formControl mp_name_validation" value="<?php echo $end_location; ?>" placeholder="<?php esc_attr_e('EX:Dhaka', 'mptbm_plugin'); ?>"/>
                        </label>
                    </td>
                    <td>
                        <label>
                            <input type="text" name="mptbm_manual_price[]" class="formControl mp_price_validation" value="<?php echo $price; ?>" placeholder="<?php esc_attr_e('EX:10 ', 'mptbm_plugin'); ?>"/>
                        </label>
                    </td>
                    <td>
						<?php MP_Custom_Layout::move_remove_button(); ?>
                    </td>
                </tr>
				<?php
			}
			public function save_price_settings($post_id) {
				if (get_post_type($post_id) == MPTBM_Function::get_cpt()) {
					$price_based = MP_Global_Function::get_submit_info('mptbm_price_based');
					update_post_meta($post_id, 'mptbm_price_based', $price_based);
					$distance_price = MP_Global_Function::get_submit_info('mptbm_km_price', 0);
					update_post_meta($post_id, 'mptbm_km_price', $distance_price);
					$hour_price = MP_Global_Function::get_submit_info('mptbm_hour_price', 0);
					update_post_meta($post_id, 'mptbm_hour_price', $hour_price);
					$manual_price_infos = array();
					$start_location = MP_Global_Function::get_submit_info('mptbm_manual_start_location', array());
					$end_location = MP_Global_Function::get_submit_info('mptbm_manual_end_location', array());
					$manual_price = MP_Global_Function::get_submit_info('mptbm_manual_price', array());
					if (sizeof($start_location) > 1 && sizeof($end_location) > 1 && sizeof($manual_price) > 0) {
						$count = 0;
						foreach ($start_location as $key => $location) {
							if ($location && $end_location[$key] && $manual_price[$key]) {
								$manual_price_infos[$count]['start_location'] = $location;
								$manual_price_infos[$count]['end_location'] = $end_location[$key];
								$manual_price_infos[$count]['price'] = $manual_price[$key];
								$count++;
							}
						}
					}
					update_post_meta($post_id, 'mptbm_manual_price_info', $manual_price_infos);
				}
			}
		}
		new MPTBM_Price_Settings();
	}