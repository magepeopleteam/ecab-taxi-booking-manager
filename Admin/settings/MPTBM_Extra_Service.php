<?php
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('MPTBM_Extra_Service')) {
		class MPTBM_Extra_Service {
			public function __construct() {
				add_action('admin_menu', array($this, 'extra_service_menu'));
				add_action('mptbm_extra_service_item', array($this, 'extra_service_item'));
			}
			public function extra_service_menu() {
				$cpt = MPTBM_Function::get_cpt();
				add_submenu_page('edit.php?post_type=' . $cpt, __('Extra Services', 'mptbm_plugin'), __('Extra Services', 'mptbm_plugin'), 'manage_options', 'mptbm_extra_service_page', array($this, 'extra_service_page'));
			}
			public function extra_service_page() {
				if (isset($_POST['mptbm_extra_services_page'])) {
					$this->save_extra_service();
				}
				$extra_services = get_option('mptbm_extra_services');
				$display = get_option('display_mptbm_extra_services', 'on');
				$active = $display == 'off' ? '' : 'mActive';
				$checked = $display == 'off' ? '' : 'checked';
				?>
                <div class="wrap"></div>
                <div class="mpStyle">
                    <form method="post" action="">
                        <div class=_dShadow_6_adminLayout_max_1200">
                            <h5 class="dFlex">
                                <span class="mR"><?php esc_html_e('On/Off Extra Service Settings', 'mptbm_plugin'); ?></span>
								<?php MP_Custom_Layout::switch_button('display_mptbm_extra_services', $checked); ?>
                            </h5>
							<?php MPTBM_Settings::info_text('display_mptbm_extra_services'); ?>
                            <div data-collapse="#display_mptbm_extra_services" class="mp_settings_area mT <?php echo esc_attr($active); ?>">
                                <div class="divider"></div>
                                <div class="ovAuto mt_xs">
                                    <table>
                                        <thead>
                                        <tr>
                                            <th><span><?php esc_html_e('Service Icon', 'mptbm_plugin'); ?></span></th>
                                            <th><span><?php esc_html_e('Service Name', 'mptbm_plugin'); ?></span></th>
                                            <th><span><?php esc_html_e('Short description', 'mptbm_plugin'); ?></span></th>
                                            <th><span><?php esc_html_e('Service Price', 'mptbm_plugin'); ?></span></th>
                                            <th><span><?php esc_html_e('Qty Box Type', 'mptbm_plugin'); ?></span></th>
                                            <th><span><?php esc_html_e('Action', 'mptbm_plugin'); ?></span></th>
                                        </tr>
                                        </thead>
                                        <tbody class="mp_sortable_area mp_item_insert">
										<?php
											if ($extra_services && is_array($extra_services) && sizeof($extra_services) > 0) {
												foreach ($extra_services as $extra_service) {
													$this->extra_service_item($extra_service);
												}
											}
										?>
                                        </tbody>
                                    </table>
                                </div>
								<?php MP_Custom_Layout::add_new_button(esc_html__('Add Extra New Service', 'mptbm_plugin')); ?>
								<?php do_action('add_mp_hidden_table', 'mptbm_extra_service_item'); ?>
                            </div>
                            <div class="divider"></div>
                            <div class="justifyEnd">
                                <button type="submit" class="dButton" name="mptbm_extra_services_page"><span><?php esc_html_e('Save Extra Services', 'mptbm_plugin'); ?>&longrightarrow;</span></button>
                            </div>
                        </div>
                    </form>
                </div>
				<?php
			}
			public function extra_service_item($field = array()) {
				$field = $field ?: array();
				$images = $field && is_array($field) ? $field : array();
				$service_icon = array_key_exists('service_icon', $field) ? $field['service_icon'] : '';
				$service_name = array_key_exists('service_name', $field) ? $field['service_name'] : '';
				$service_price = array_key_exists('service_price', $field) ? $field['service_price'] : '';
				$input_type = array_key_exists('service_qty_type', $field) ? $field['service_qty_type'] : 'inputbox';
				$description = array_key_exists('extra_service_description', $field) ? $field['extra_service_description'] : '';
				$icon = $image = "";
				if ($service_icon) {
					if (preg_match('/\s/', $service_icon)) {
						$icon = $service_icon;
					} else {
						$image = $service_icon;
					}
				}
				?>
                <tr class="mp_remove_area">
                    <td>
						<?php do_action('mp_add_icon_image', 'service_icon[]', $icon, $image); ?>
                    </td>
                    <td>
                        <label>
                            <input type="text" class="formControl mp_name_validation" name="service_name[]" placeholder="<?php esc_attr_e('EX: Driver', 'mptbm_plugin'); ?>" value="<?php echo esc_attr($service_name); ?>"/>
                        </label>
                    </td>
                    <td>
                        <label>
                            <textarea class="formControl" name="extra_service_description[]" placeholder="<?php esc_attr_e('EX: Description', 'mptbm_plugin'); ?>"><?php echo esc_html($description); ?></textarea>
                        </label>
                    </td>
                    <td>
                        <label>
                            <input type="number" pattern="[0-9]*" step="0.01" class="formControl mp_price_validation" name="service_price[]" placeholder="<?php esc_attr_e('EX: 10', 'mptbm_plugin'); ?>" value="<?php echo esc_attr($service_price); ?>"/>
                        </label>
                    </td>
                    <td>
                        <label>
                            <select name="service_qty_type[]" class='formControl'>
                                <option value="inputbox" <?php echo esc_attr($input_type == 'inputbox' ? 'selected' : ''); ?>><?php esc_html_e('Input Box', 'mptbm_plugin'); ?></option>
                                <option value="dropdown" <?php echo esc_attr($input_type == 'dropdown' ? 'selected' : ''); ?>><?php esc_html_e('Dropdown List', 'mptbm_plugin'); ?></option>
                            </select>
                        </label>
                    </td>
                    <td><?php MP_Custom_Layout::move_remove_button(); ?></td>
                </tr>
				<?php
			}
			public function save_extra_service() {
				if (isset($_POST['mptbm_extra_services_page'])) {
					$new_extra_service = array();
					$extra_icon = MP_Global_Function::get_submit_info('service_icon', array());
					$extra_names = MP_Global_Function::get_submit_info('service_name', array());
					$extra_price = MP_Global_Function::get_submit_info('service_price', array());
					$extra_qty_type = MP_Global_Function::get_submit_info('service_qty_type', array());
					$extra_service_description = MP_Global_Function::get_submit_info('extra_service_description', array());
					$extra_count = count($extra_names);
					for ($i = 0; $i < $extra_count; $i++) {
						if ($extra_names[$i] && $extra_price[$i] >= 0) {
							$new_extra_service[$i]['service_icon'] = $extra_icon[$i] ?? '';
							$new_extra_service[$i]['service_name'] = $extra_names[$i];
							$new_extra_service[$i]['service_price'] = $extra_price[$i];
							$new_extra_service[$i]['service_qty_type'] = $extra_qty_type[$i] ?? 'inputbox';
							$new_extra_service[$i]['extra_service_description'] = $extra_service_description[$i] ?? '';
						}
					}
					$extra_service_data = apply_filters('filter_mptbm_extra_service_data', $new_extra_service);
					update_option('mptbm_extra_services', $extra_service_data);
					$display = MP_Global_Function::get_submit_info('display_mptbm_extra_services') ? 'on' : 'off';
					update_option('display_mptbm_extra_services', $display);
				}
			}
		}
		new MPTBM_Extra_Service();
	}