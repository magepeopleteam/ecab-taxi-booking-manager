<?php
/*
 * @Author 		engr.sumonazma@gmail.com
 * Copyright: 	mage-people.com
 */
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_Base_Price_Settings')) {
	class MPTBM_Base_Price_Settings {
		public function __construct() {
			add_action('add_mptbm_settings_tab_content', [$this, 'base_price_settings']);
			add_action('save_post', array($this, 'save_base_price_settings'), 99, 1);
		}

		public function base_price_settings($post_id) {
			$base_price_location = MP_Global_Function::get_post_info($post_id, 'mptbm_base_price_location', '');
			$base_price_km = MP_Global_Function::get_post_info($post_id, 'mptbm_base_price_km', '');
			$base_price_hour = MP_Global_Function::get_post_info($post_id, 'mptbm_base_price_hour', '');
			$base_min_threshold = MP_Global_Function::get_post_info($post_id, 'mptbm_base_min_threshold', '');
			$charge_base_pickup = MP_Global_Function::get_post_info($post_id, 'mptbm_charge_base_pickup', 'no');
			$charge_base_dropoff = MP_Global_Function::get_post_info($post_id, 'mptbm_charge_base_dropoff', 'no');
			
			// Get Locations for dropdown
			$locations = get_terms(array(
				'taxonomy' => 'locations',
				'hide_empty' => false,
			));
			?>
			<div class="tabsItem" data-tabs="#mptbm_settings_base_price">
				<h2><?php esc_html_e('Base Price Settings', 'ecab-taxi-booking-manager'); ?></h2>
				<p><?php _e('Configure base price settings based on location and distance/time.', 'ecab-taxi-booking-manager'); ?></p>

				<section class="bg-light">
					<h6><?php _e('Base Price Configuration', 'ecab-taxi-booking-manager'); ?></h6>
				</section>

				<section>
					<label class="label">
						<div>
							<h6><?php esc_html_e('Base Price Location', 'ecab-taxi-booking-manager'); ?></h6>
							<span class="desc"><?php esc_html_e('Select the base location for price calculation', 'ecab-taxi-booking-manager'); ?></span>
						</div>
						<select class="formControl" name="mptbm_base_price_location">
							<option value=""><?php esc_html_e('Select Location', 'ecab-taxi-booking-manager'); ?></option>
							<?php if (!empty($locations) && !is_wp_error($locations)) : ?>
								<?php foreach ($locations as $location) : 
									$geo = get_term_meta($location->term_id, 'mptbm_geo_location', true);
									if (empty($geo)) {
										continue;
									}
								?>
									<option value="<?php echo esc_attr($location->term_id); ?>" <?php selected($base_price_location, $location->term_id); ?>>
										<?php echo esc_html($location->name); ?>
									</option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>
					</label>
				</section>

				<section>
					<label class="label">
						<div>
							<h6><?php esc_html_e('Price per KM', 'ecab-taxi-booking-manager'); ?></h6>
							<span class="desc"><?php esc_html_e('Enter the price per kilometer from base location', 'ecab-taxi-booking-manager'); ?></span>
						</div>
						<input type="number" step="any" name="mptbm_base_price_km" class="formControl" value="<?php echo esc_attr($base_price_km); ?>" />
					</label>
				</section>

				<section>
					<label class="label">
						<div>
							<h6><?php esc_html_e('Price per Hour', 'ecab-taxi-booking-manager'); ?></h6>
							<span class="desc"><?php esc_html_e('Enter the price per hour from base location', 'ecab-taxi-booking-manager'); ?></span>
						</div>
						<input type="number" step="any" name="mptbm_base_price_hour" class="formControl" value="<?php echo esc_attr($base_price_hour); ?>" />
					</label>
				</section>

				<section>
					<label class="label">
						<div>
							<h6><?php esc_html_e('Minimum Threshold (Distance)', 'ecab-taxi-booking-manager'); ?></h6>
							<span class="desc"><?php esc_html_e('Distance free of charge from base price location', 'ecab-taxi-booking-manager'); ?></span>
						</div>
						<input type="number" step="any" name="mptbm_base_min_threshold" class="formControl" value="<?php echo esc_attr($base_min_threshold); ?>" />
					</label>
				</section>
				
				<section>
					<label class="label">
						<div>
							<h6><?php esc_html_e('Charge for Base to Pickup?', 'ecab-taxi-booking-manager'); ?></h6>
							<span class="desc"><?php esc_html_e('Enable to charge for distance/time from base location to pickup location', 'ecab-taxi-booking-manager'); ?></span>
						</div>
						<?php MP_Custom_Layout::switch_button('mptbm_charge_base_pickup', $charge_base_pickup == 'yes' ? 'checked' : ''); ?>
					</label>
				</section>

				<section>
					<label class="label">
						<div>
							<h6><?php esc_html_e('Charge for Base to Dropoff?', 'ecab-taxi-booking-manager'); ?></h6>
							<span class="desc"><?php esc_html_e('Enable to charge for distance/time from dropoff location back to base location', 'ecab-taxi-booking-manager'); ?></span>
						</div>
						<?php MP_Custom_Layout::switch_button('mptbm_charge_base_dropoff', $charge_base_dropoff == 'yes' ? 'checked' : ''); ?>
					</label>
				</section>

			</div>
			<?php
		}

		public function save_base_price_settings($post_id) {
			if (!isset($_POST['mptbm_transportation_type_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mptbm_transportation_type_nonce'])), 'mptbm_transportation_type_nonce')) {
				return;
			}
			
			if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
				return;
			}
			
			if (!current_user_can('edit_post', $post_id)) {
				return;
			}

			if (get_post_type($post_id) == MPTBM_Function::get_cpt()) {
				// Save Base Price Location
				if (isset($_POST['mptbm_base_price_location'])) {
					update_post_meta($post_id, 'mptbm_base_price_location', sanitize_text_field($_POST['mptbm_base_price_location']));
				}

				// Save Price per KM
				if (isset($_POST['mptbm_base_price_km'])) {
					update_post_meta($post_id, 'mptbm_base_price_km', sanitize_text_field($_POST['mptbm_base_price_km']));
				}

				// Save Price per Hour
				if (isset($_POST['mptbm_base_price_hour'])) {
					update_post_meta($post_id, 'mptbm_base_price_hour', sanitize_text_field($_POST['mptbm_base_price_hour']));
				}

				// Save Minimum Threshold
				if (isset($_POST['mptbm_base_min_threshold'])) {
					update_post_meta($post_id, 'mptbm_base_min_threshold', sanitize_text_field($_POST['mptbm_base_min_threshold']));
				}

				// Save Charge Base to Pickup
				$charge_base_pickup = isset($_POST['mptbm_charge_base_pickup']) ? 'yes' : 'no';
				update_post_meta($post_id, 'mptbm_charge_base_pickup', $charge_base_pickup);

				// Save Charge Base to Dropoff
				$charge_base_dropoff = isset($_POST['mptbm_charge_base_dropoff']) ? 'yes' : 'no';
				update_post_meta($post_id, 'mptbm_charge_base_dropoff', $charge_base_dropoff);
			}
		}
	}
	new MPTBM_Base_Price_Settings();
}
