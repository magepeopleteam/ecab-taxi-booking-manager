<?php
/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.
if (!class_exists('MPTBM_General_Settings')) {
	class MPTBM_General_Settings {
		public function __construct() {
			add_action('add_mptbm_settings_tab_content', [$this, 'general_settings']);
			add_action('add_hidden_mptbm_features_item', [$this, 'features_item']);
			add_action('save_post', [$this, 'save_general_settings']);
		}
		public function general_settings($post_id) {
			$max_passenger = MP_Global_Function::get_post_info($post_id, 'mptbm_maximum_passenger');
			$max_bag = MP_Global_Function::get_post_info($post_id, 'mptbm_maximum_bag');
			$display_features = MP_Global_Function::get_post_info($post_id, 'display_mptbm_features', 'on');
			$active = $display_features == 'off' ? '' : 'mActive';
			$checked = $display_features == 'off' ? '' : 'checked';
			$all_features = MP_Global_Function::get_post_info($post_id, 'mptbm_features');
			if (!$all_features) {
				$all_features = array(
					array(
						'label' => esc_html__('Name', 'ecab-taxi-booking-manager'),
						'icon' => 'fas fa-car-side',
						'image' => '',
						'text' => ''
					),
					array(
						'label' => esc_html__('Model', 'ecab-taxi-booking-manager'),
						'icon' => 'fas fa-car',
						'image' => '',
						'text' => ''
					),
					array(
						'label' => esc_html__('Engine', 'ecab-taxi-booking-manager'),
						'icon' => 'fas fa-cogs',
						'image' => '',
						'text' => ''
					),
					array(
						'label' => esc_html__('Fuel Type', 'ecab-taxi-booking-manager'),
						'icon' => 'fas fa-gas-pump',
						'image' => '',
						'text' => ''
					)
				);
			}
			?>
            <div class="tabsItem" data-tabs="#mptbm_general_info">
                <h2 ><?php esc_html_e('General Information Settings', 'ecab-taxi-booking-manager'); ?></h2>
				<p><?php esc_html_e('Basic Configuration', 'ecab-taxi-booking-manager'); ?></p>
                <div class="mp_settings_area">
					<section class="bg-light">
						<h6><?php esc_html_e('Feature Configuration', 'ecab-taxi-booking-manager'); ?></h6>
						<span ><?php esc_html_e('Here you can On/Off feature list and create new feature.', 'ecab-taxi-booking-manager'); ?></span>
					</section>
					<section>
						<label class="label">
							<div>
								<h6><?php esc_html_e('Maximum Passenger', 'ecab-taxi-booking-manager'); ?></h6>
								<span class="desc"><?php MPTBM_Settings::info_text('mptbm_maximum_passenger'); ?></span>
							</div>
							<input class="formControl mp_price_validation" name="mptbm_maximum_passenger" value="<?php echo esc_attr($max_passenger); ?>" type="text" placeholder="<?php esc_html_e('EX:4', 'ecab-taxi-booking-manager'); ?>" />
						</label>
					</section>
					<section>
						<label class="label">
							<div>
								<h6><?php esc_html_e('Maximum Bag', 'ecab-taxi-booking-manager'); ?></h6>
								<span class="desc"><?php MPTBM_Settings::info_text('mptbm_maximum_bag'); ?></span>
							</div>
							<input class="formControl mp_price_validation" name="mptbm_maximum_bag" value="<?php echo esc_attr($max_bag); ?>" type="text" placeholder="<?php esc_html_e('EX:4', 'ecab-taxi-booking-manager'); ?>" />
						</label>
					</section>
					<section>
						<label class="label">
							<div>
								<h6><?php esc_html_e('Vehicle Quantity', 'ecab-taxi-booking-manager'); ?></h6>
								<span class="desc"><?php esc_html_e('Enter the total number of this vehicle type available for booking', 'ecab-taxi-booking-manager'); ?></span>
							</div>
							<input class="formControl mp_price_validation" name="mptbm_vehicle_quantity" value="<?php echo esc_attr(MP_Global_Function::get_post_info($post_id, 'mptbm_vehicle_quantity', 1)); ?>" type="number" min="1" placeholder="<?php esc_html_e('EX: 5', 'ecab-taxi-booking-manager'); ?>" />
						</label>
					</section>
					<section>
						<label class="label">
							<div>
								<h6><?php esc_html_e('Taxi Reset Time (Minutes)', 'ecab-taxi-booking-manager'); ?></h6>
								<span class="desc"><?php esc_html_e('Enter the number of minutes after which a fully booked taxi becomes available again', 'ecab-taxi-booking-manager'); ?></span>
							</div>
							<input class="formControl mp_price_validation" name="mptbm_taxi_reset_minutes" value="<?php echo esc_attr(MP_Global_Function::get_post_info($post_id, 'mptbm_taxi_reset_minutes', 1440)); ?>" type="number" min="1" placeholder="<?php esc_html_e('EX: 1440 (24 hours)', 'ecab-taxi-booking-manager'); ?>" />
						</label>
					</section>
					<section >
						<label class="label">
							<div>
								<h6><?php esc_html_e('On/Off Feature Extra feature', 'ecab-taxi-booking-manager'); ?></h6>
								<span class="desc"><?php MPTBM_Settings::info_text('display_mptbm_features'); ?></span>
							</div>
							<?php MP_Custom_Layout::switch_button('display_mptbm_features', $checked); ?>
						</label>
					</section>
					<section data-collapse="#display_mptbm_features" class="<?php echo esc_attr($active); ?>">
							<table>
								<thead>
								<tr class="bg-dark">
									<th class="_w_150"><?php esc_html_e('Icon/Image', 'ecab-taxi-booking-manager'); ?></th>
									<th><?php esc_html_e('Label', 'ecab-taxi-booking-manager'); ?></th>
									<th><?php esc_html_e('Text', 'ecab-taxi-booking-manager'); ?></th>
									<th class="_w_125"><?php esc_html_e('Action', 'ecab-taxi-booking-manager'); ?></th>
								</tr>
								</thead>
								<tbody class="mp_sortable_area mp_item_insert">
								<?php
								if (is_array($all_features) && sizeof($all_features) > 0) {
									foreach ($all_features as $features) {
										$this->features_item($features);
									}
								} else {
									$this->features_item();
								}
								?>
								</tbody>
							</table>
							<div class="my-2"></div>
							<?php MP_Custom_Layout::add_new_button(esc_html__('Add New Item', 'ecab-taxi-booking-manager')); ?>
							<?php do_action('add_mp_hidden_table', 'add_hidden_mptbm_features_item'); ?>
					</section >
                </div>
            </div>
			<?php
		}
		public function features_item($features = array()) {
			$label = array_key_exists('label', $features) ? $features['label'] : '';
			$text = array_key_exists('text', $features) ? $features['text'] : '';
			$icon = array_key_exists('icon', $features) ? $features['icon'] : '';
			$image = array_key_exists('image', $features) ? $features['image'] : '';
			?>
            <tr class="mp_remove_area">
                <td valign="middle"><?php do_action('mp_add_icon_image', 'mptbm_features_icon_image[]', $icon, $image); ?></td>
                <td valign="middle">
                    <label>
                        <input class="formControl mp_name_validation" name="mptbm_features_label[]" value="<?php echo esc_attr($label); ?>"/>
                    </label>
                </td>
                <td valign="middle">
                    <label>
                        <input class="formControl mp_name_validation" name="mptbm_features_text[]" value="<?php echo esc_attr($text); ?>"/>
                    </label>
                </td>
                <td valign="middle"><?php MP_Custom_Layout::move_remove_button(); ?></td>
            </tr>
			<?php
		}
		public function save_general_settings($post_id) {
			if (!isset($_POST['mptbm_transportation_type_nonce']) || !wp_verify_nonce(sanitize_text_field( wp_unslash ($_POST['mptbm_transportation_type_nonce'])), 'mptbm_transportation_type_nonce') && defined('DOING_AUTOSAVE') && DOING_AUTOSAVE && !current_user_can('edit_post', $post_id)) {
				return;
			}
			if (get_post_type($post_id) == MPTBM_Function::get_cpt()) {
				$all_features = [];
				$max_passenger = isset($_POST['mptbm_maximum_passenger']) ? sanitize_text_field($_POST['mptbm_maximum_passenger']) : '';
				$max_bag = isset($_POST['mptbm_maximum_bag']) ? sanitize_text_field($_POST['mptbm_maximum_bag']) : '';
				$vehicle_quantity = isset($_POST['mptbm_vehicle_quantity']) ? absint($_POST['mptbm_vehicle_quantity']) : 1;
				$taxi_reset_minutes = isset($_POST['mptbm_taxi_reset_minutes']) ? absint($_POST['mptbm_taxi_reset_minutes']) : 1440;
				update_post_meta($post_id, 'mptbm_maximum_passenger', $max_passenger);
				update_post_meta($post_id, 'mptbm_maximum_bag', $max_bag);
				update_post_meta($post_id, 'mptbm_vehicle_quantity', $vehicle_quantity);
				update_post_meta($post_id, 'mptbm_taxi_reset_minutes', $taxi_reset_minutes);
				$display_features = isset($_POST['display_mptbm_features']) && sanitize_text_field($_POST['display_mptbm_features'])? 'on' : 'off';
				update_post_meta($post_id, 'display_mptbm_features', $display_features);
				$features_label = isset($_POST['mptbm_features_label']) ? array_map('sanitize_text_field',$_POST['mptbm_features_label']) : [];
				if (sizeof($features_label) > 0) {
					$features_text = isset($_POST['mptbm_features_text']) ? array_map('sanitize_text_field',$_POST['mptbm_features_text']) : [];
					$features_icon = isset($_POST['mptbm_features_icon_image']) ? array_map('sanitize_text_field',$_POST['mptbm_features_icon_image']) : [];
					$count = 0;
					foreach ($features_label as $label) {
						if ($label) {
							$all_features[$count]['label'] = $label;
							$all_features[$count]['text'] = $features_text[$count];
							$all_features[$count]['icon'] = '';
							$all_features[$count]['image'] = '';
							$current_image_icon = array_key_exists($count, $features_icon) ? $features_icon[$count] : '';
							if ($current_image_icon) {
								if (preg_match('/\s/', $current_image_icon)) {
									$all_features[$count]['icon'] = $current_image_icon;
								} else {
									$all_features[$count]['image'] = $current_image_icon;
								}
							}
							$count++;
						}
					}
				}
				update_post_meta($post_id, 'mptbm_features', $all_features);
			}
		}
	}
	new MPTBM_General_Settings();
}