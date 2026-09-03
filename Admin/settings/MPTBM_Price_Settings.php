<?php
/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.
if (!class_exists('MPTBM_Price_Settings')) {
	class MPTBM_Price_Settings
	{
		public function __construct()
		{
			add_action('add_mptbm_settings_tab_content', [$this, 'price_settings'], 10, 1);
			add_action('save_post', [$this, 'save_price_settings'], 10, 1);

			add_action('wp_ajax_mptbm_operation_area_price_data_set', [$this, 'mptbm_operation_area_price_data_set']);
		}
		public function price_settings($post_id)
		{
			$initial_price = MP_Global_Function::get_post_info($post_id, 'mptbm_initial_price');
			$min_price = MP_Global_Function::get_post_info($post_id, 'mptbm_min_price');
			$return_min_price = MP_Global_Function::get_post_info($post_id, 'mptbm_min_price_return');
			$return_discount = MP_Global_Function::get_post_info($post_id, 'mptbm_return_discount');
			$display_map = MP_Global_Function::get_settings('mptbm_map_api_settings', 'display_map', 'enable');
			$price_based = MP_Global_Function::get_post_info($post_id, 'mptbm_price_based');
			$price_display_type = MP_Global_Function::get_post_info($post_id, 'mptbm_price_display_type', 'normal');
			$custom_price_message = MP_Global_Function::get_post_info($post_id, 'mptbm_custom_price_message', '');
			$price_based = $display_map == 'disable' ? 'manual' : $price_based;
			$distance_price = MP_Global_Function::get_post_info($post_id, 'mptbm_km_price');
			$time_price = MP_Global_Function::get_post_info($post_id, 'mptbm_hour_price');
			$time_price = MP_Global_Function::get_post_info($post_id, 'mptbm_hour_price');
			$day_price = MP_Global_Function::get_post_info($post_id, 'mptbm_day_price');
			$fixed_map_price = MP_Global_Function::get_post_info($post_id, 'mptbm_fixed_map_price');
			$manual_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_manual_price_info', []);
			// Route definitions (name + waypoints) now live once on the global
			// mptbm_routes CPT (Routes admin menu) - this vehicle only stores
			// which routes it offers and at what price (see route_price_item()).
			$assigned_routes = MP_Global_Function::get_post_info($post_id, 'mptbm_assigned_routes', []);
			$all_routes = get_posts([
				'post_type'   => 'mptbm_routes',
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			]);
			$fixed_zone_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_fixed_zone_price_info', []);
			$fixed_map_route_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_fixed_map_route_price_info', []);
			$terms_location_prices = MP_Global_Function::get_post_info($post_id, 'mptbm_terms_price_info', []);
			$location_terms = get_terms(array('taxonomy' => 'locations', 'hide_empty' => false));

			$all_zones = array();
			$location_zones = array(); // Geo-located locations (term_*)
			$operation_zones = array(); // Operation areas (post_*)
			
			if (!empty($location_terms) && !is_wp_error($location_terms)) {
				foreach ($location_terms as $term) {
					if (get_term_meta($term->term_id, 'mptbm_geo_location', true)) {
						$all_zones['term_' . $term->term_id] = $term->name . ' (Location)';
						$location_zones['term_' . $term->term_id] = $term->name . ' (Location)';
					}
				}
			}
			$op_areas = get_posts(array(
				'post_type' => 'mptbm_operate_areas',
				'posts_per_page' => -1,
				'meta_query' => array(
					array(
						'key' => 'mptbm-operation-type',
						'value' => 'fixed-operation-area-type'
					)
				)
			));
			if (!empty($op_areas)) {
				foreach ($op_areas as $area) {
					$all_zones['post_' . $area->ID] = $area->post_title . ' (Operation Area)';
					$operation_zones['post_' . $area->ID] = $area->post_title . ' (Operation Area)';
				}
			}

			$waiting_time_check = MPTBM_Function::get_general_settings('taxi_waiting_time', 'enable');
			$waiting_price = MP_Global_Function::get_post_info($post_id, 'mptbm_waiting_price');
			$distance_selected = $price_based == 'distance' ? 'selected' : '';
			$distance_selected = $display_map == 'disable' ? 'disabled' : $distance_selected;

			$duration_selected = $price_based == 'duration' ? 'selected' : '';
			$duration_selected = $display_map == 'disable' ? 'disabled' : $duration_selected;
			$distance_duration_selected = $price_based == 'distance_duration' ? 'selected' : '';
			$distance_duration_selected = $display_map == 'disable' ? 'disabled' : $distance_duration_selected;
			$fixed_hourly_selected = $price_based == 'fixed_hourly' ? 'selected' : '';
			$fixed_hourly_selected = $price_based == 'fixed_hourly' ? 'selected' : '';
			$fixed_hourly_selected = $display_map == 'disable' ? 'disabled' : $fixed_hourly_selected;

			$fixed_daily_selected = $price_based == 'fixed_daily' ? 'selected' : '';
			$fixed_daily_selected = $display_map == 'disable' ? 'disabled' : $fixed_daily_selected;

			$fixed_distance_selected = $price_based == 'fixed_distance' ? 'selected' : '';
			$fixed_distance_selected = $display_map == 'disable' ? 'disabled' : $fixed_distance_selected;
			
			$inclusive_selected = $price_based == 'inclusive' ? 'selected' : '';
			$gm_api_url = admin_url('edit.php?post_type=mptbm_rent&page=mptbm_settings_page');

?>
			<div class="tabsItem" data-tabs="#mptbm_settings_pricing">
				<h2><?php esc_html_e('Price Settings', 'ecab-taxi-booking-manager'); ?></h2>
				<p><?php esc_html_e('here you can set initial price, Waiting Time price, price calculation model', 'ecab-taxi-booking-manager'); ?></p>
				<!-- Add the nonce field here -->
				<?php wp_nonce_field('mptbm_price_settings_action', 'mptbm_price_settings_nonce'); ?>
				<section class="bg-light">
					<h6><?php esc_html_e('Price Settings', 'ecab-taxi-booking-manager'); ?></h6>
					<span><?php esc_html_e('Here you can set price', 'ecab-taxi-booking-manager'); ?></span>
				</section>
				<section>
					<label class="label">
						<div>
							<h6><?php esc_html_e('Initial Price', 'ecab-taxi-booking-manager'); ?></h6>
							<span class="desc"><?php MPTBM_Settings::info_text('mptbm_initial_price'); ?></span>
						</div>
						<input class="formControl mp_price_validation" name="mptbm_initial_price" value="<?php echo esc_attr($initial_price); ?>" type="text" placeholder="<?php esc_html_e('EX:10', 'ecab-taxi-booking-manager'); ?>" />
					</label>
				</section>
				<section>
					<label class="label">
						<div>
							<h6><?php esc_html_e('Minimum Price', 'ecab-taxi-booking-manager'); ?></h6>
							<span class="desc"><?php MPTBM_Settings::info_text('mptbm_minimum_price'); ?></span>
						</div>
						<input class="formControl mp_price_validation" name="mptbm_min_price" value="<?php echo esc_attr($min_price); ?>" type="text" placeholder="<?php esc_html_e('EX:10', 'ecab-taxi-booking-manager'); ?>" />
					</label>
				</section>
				<section>
					<label class="label">
						<div>
							<h6><?php esc_html_e('Return Minimum Price', 'ecab-taxi-booking-manager'); ?></h6>
							<span class="desc"><?php MPTBM_Settings::info_text('mptbm_return_minimum_price'); ?></span>
						</div>
						<input class="formControl mp_price_validation" name="mptbm_min_price_return" value="<?php echo esc_attr($return_min_price); ?>" type="text" placeholder="<?php esc_html_e('EX:10', 'ecab-taxi-booking-manager'); ?>" />
					</label>
				</section>
				<section>
					<label class="label">
						<div>
							<h6><?php esc_html_e('Return Discount', 'ecab-taxi-booking-manager'); ?></h6>
							<span class="desc"><?php MPTBM_Settings::info_text('mptbm_return_discount'); ?></span>
						</div>
						<input class="formControl " name="mptbm_return_discount" value="<?php echo esc_attr($return_discount); ?>" type="text" placeholder="<?php esc_html_e('EX:10', 'ecab-taxi-booking-manager'); ?>" />
					</label>
				</section>
				<?php if ($waiting_time_check == 'enable') { ?>
					<section class="<?php echo esc_attr($price_based == 'duration' || $price_based == 'distance' || $price_based == 'distance_duration' || $price_based == 'manual' ? 'mActive' : ''); ?>">
						<label class="label">
							<div>
								<h6><?php esc_html_e('Waiting Time Price/Hour', 'ecab-taxi-booking-manager'); ?></h6>
								<span class="desc"><?php MPTBM_Settings::info_text('mptbm_waiting_price'); ?></span>
							</div>
							<input class="formControl mp_price_validation" name="mptbm_waiting_price" value="<?php echo esc_attr($waiting_price); ?>" type="text" placeholder="<?php esc_html_e('EX:10', 'ecab-taxi-booking-manager'); ?>" />
						</label>
					</section>
				<?php } ?>
				<section>
					<label class="label">
						<div>
							<h6><?php esc_html_e('Pricing based on', 'ecab-taxi-booking-manager'); ?>
								<i class="fas fa-question-circle tooltip-icon" title="The Inclusive pricing model applies to all pricing models; when set, it works with all shortcodes as long as the relevant data is available in the fields"></i>
							</h6>
							<?php if ($display_map == 'disable') { ?>
								<span class="desc"><?php esc_html_e('To enable google map pricing option you must enable  ', 'ecab-taxi-booking-manager'); ?><a href="<?php echo esc_attr($gm_api_url); ?>"><?php esc_html_e('google map base pricing option', 'ecab-taxi-booking-manager'); ?></a></span>
							<?php } else { ?>
								<span class="desc"><?php MPTBM_Settings::info_text('mptbm_price_based'); ?></span>
							<?php } ?>
						</div>
						<div>
							<select class="formControl" name="mptbm_price_based" data-collapse-target>
								<option disabled><?php esc_html_e('Please select ...', 'ecab-taxi-booking-manager'); ?></option>
								<option value="inclusive" data-option-target data-option-target-multi="#mp_distance #mp_duration #mp_manual #mp_fixed_map" <?php echo esc_attr($inclusive_selected); ?>><?php esc_html_e('Inclusive', 'ecab-taxi-booking-manager'); ?></option>
								<option value="distance" data-option-target data-option-target-multi="#mp_distance" <?php echo esc_attr($distance_selected); ?>><?php esc_html_e('Distance as google map', 'ecab-taxi-booking-manager'); ?></option>
								<option value="duration" data-option-target data-option-target-multi="#mp_duration" <?php echo esc_attr($duration_selected); ?>><?php esc_html_e('Duration/Time as google map', 'ecab-taxi-booking-manager'); ?></option>
								<option value="distance_duration" data-option-target data-option-target-multi="#mp_distance #mp_duration" <?php echo esc_attr($distance_duration_selected); ?>><?php esc_html_e('Distance + Duration as google map', 'ecab-taxi-booking-manager'); ?></option>
								<option value="manual" data-option-target data-option-target-multi="#mp_manual" <?php echo esc_attr($price_based == 'manual' ? 'selected' : ''); ?>><?php esc_html_e('Manual as fixed Location', 'ecab-taxi-booking-manager'); ?></option>
								<option value="fixed_hourly" data-option-target="#mp_duration" <?php echo esc_attr($fixed_hourly_selected); ?>><?php esc_html_e('Fixed Hourly', 'ecab-taxi-booking-manager'); ?></option>
								<option value="fixed_daily" data-option-target="#mp_day" <?php echo esc_attr($fixed_daily_selected); ?>><?php esc_html_e('Fixed Daily', 'ecab-taxi-booking-manager'); ?></option>
								<option value="fixed_distance" data-option-target data-option-target-multi="#mp_distance #mp_duration #mp_fixed_map #mp_fixed_map_routes" <?php echo esc_attr($fixed_distance_selected); ?>><?php esc_html_e('Fixed with Map', 'ecab-taxi-booking-manager'); ?></option>
								<option value="fixed_zone" data-option-target data-option-target-multi="#mp_fixed_zone" <?php echo esc_attr($price_based == 'fixed_zone' ? 'selected' : ''); ?>><?php esc_html_e('Fixed Zone', 'ecab-taxi-booking-manager'); ?></option>
								<option value="fixed_route" data-option-target data-option-target-multi="#mp_fixed_route" <?php echo esc_attr($price_based == 'fixed_route' ? 'selected' : ''); ?>><?php esc_html_e('Fixed Route (predefined named route)', 'ecab-taxi-booking-manager'); ?></option>
							</select>
						</div>
					</label>
				</section>
				<section data-collapse="#mp_distance" class="<?php echo esc_attr($price_based == 'distance' || $price_based == 'distance_duration' ? 'mActive' : ''); ?>">
					<label class="label">
						<div>
							<h6>
								<?php printf(esc_html__('Price/%s', 'ecab-taxi-booking-manager'), esc_html(MPTBM_Function::distance_unit_label())); ?>
								<i class="fas fa-question-circle tooltip-icon" title="Price per kilometer is based on the selected pricing model: Distance (per km), Distance/Duration (per km or per hour), or Distance+Duration (combined distance and time charges)"></i>
							</h6>
							<span class="desc"><?php MPTBM_Settings::info_text('mptbm_km_price'); ?></span>
						</div>
						<input
							class="formControl mp_price_validation"
							name="mptbm_km_price"
							value="<?php echo esc_attr($distance_price); ?>"
							type="text"
							placeholder="<?php esc_html_e('EX:10', 'ecab-taxi-booking-manager'); ?>" />
					</label>
				</section>
				</section>
				
				<section data-collapse="#mp_fixed_map" class="<?php echo esc_attr($price_based == 'fixed_distance' ? 'mActive' : ''); ?>">
					<label class="label">
						<div>
							<h6><?php esc_html_e('Fixed with map price', 'ecab-taxi-booking-manager'); ?></h6>
							<span class="desc"><?php esc_html_e('Set the fixed price for map-based trips', 'ecab-taxi-booking-manager'); ?></span>
						</div>
						<input class="formControl mp_price_validation" name="mptbm_fixed_map_price" value="<?php echo esc_attr($fixed_map_price); ?>" type="text" placeholder="<?php esc_html_e('EX:10', 'ecab-taxi-booking-manager'); ?>" />
					</label>
				</section>

				<section data-collapse="#mp_duration" class="<?php echo esc_attr($price_based == 'duration' || $price_based == 'distance_duration' || $price_based == 'fixed_hourly' || $price_based == 'fixed_distance' ? 'mActive' : ''); ?>">
					<label class="label">
						<div>
							<h6><?php esc_html_e('Price/Hour', 'ecab-taxi-booking-manager'); ?>
								<i class="fas fa-question-circle tooltip-icon" title="Price per hour is based on the selected pricing model: Duration/Time (per hour), Distance+Duration (combined distance and time), or Fixed Hourly Price (flat rate per hour)"></i>
							</h6>
							<span class="desc"><?php MPTBM_Settings::info_text('mptbm_hour_price'); ?></span>
						</div>
						<input class="formControl mp_price_validation" name="mptbm_hour_price" value="<?php echo esc_attr($time_price); ?>" type="text" placeholder="<?php esc_html_e('EX:10', 'ecab-taxi-booking-manager'); ?>" />
					</label>
				</section>

				<section data-collapse="#mp_day" class="<?php echo esc_attr($price_based == 'fixed_daily' ? 'mActive' : ''); ?>">
					<label class="label">
						<div>
							<h6><?php esc_html_e('Price/Day', 'ecab-taxi-booking-manager'); ?></h6>
							<span class="desc"><?php esc_html_e('Flat rate charged per day for a Fixed Daily (multi-day rental) booking.', 'ecab-taxi-booking-manager'); ?></span>
						</div>
						<input class="formControl mp_price_validation" name="mptbm_day_price" value="<?php echo esc_attr($day_price); ?>" type="text" placeholder="<?php esc_html_e('EX:50', 'ecab-taxi-booking-manager'); ?>" />
					</label>
				</section>

				<!-- Manual price -->
				<section class="bg-light" style="margin-top: 20px;" data-collapse="#mp_manual">
					<h6><?php esc_html_e('Manual Price Settings', 'ecab-taxi-booking-manager'); ?></h6>
					<span><?php esc_html_e('Manual Price Settings', 'ecab-taxi-booking-manager'); ?></span>
				</section>
				<section class="<?php echo esc_attr($price_based == 'manual' ? 'mActive' : ''); ?>" data-collapse="#mp_manual">
					<div class="mp_settings_area">
						<table>
							<thead>
								<tr>
									<th><?php esc_html_e('Start Location', 'ecab-taxi-booking-manager'); ?><span class="textRequired">&nbsp;*</span></th>
									<th><?php esc_html_e('End Location', 'ecab-taxi-booking-manager'); ?><span class="textRequired">&nbsp;*</span></th>
									<th><?php esc_html_e('Price', 'ecab-taxi-booking-manager'); ?><span class="textRequired">&nbsp;*</span></th>
									<th class="_w_100"><?php esc_html_e('Action', 'ecab-taxi-booking-manager'); ?></th>
								</tr>
							</thead>
							<tbody class="mp_sortable_area mp_item_insert">
								<?php
								if (sizeof($manual_prices) > 0) {
									foreach ($manual_prices as $manual_price) {
										$this->manual_price_item($manual_price);
									}
								}
								if (sizeof($location_terms) > 0) {
									$this->location_terms_price_item($location_terms, $terms_location_prices);
								}
								?>
								<?php
								?>
							</tbody>
						</table>
						<div class="my-2"></div>
						<?php MP_Custom_Layout::add_new_button(esc_html__('Add New Price', 'ecab-taxi-booking-manager')); ?>
						<?php $this->hidden_manual_price_item($location_terms); ?>
					</div>
				</section>

				<!-- Fixed Route price -->
				<section class="bg-light" style="margin-top: 20px;" data-collapse="#mp_fixed_route">
					<h6><?php esc_html_e('Fixed Route Settings', 'ecab-taxi-booking-manager'); ?></h6>
					<span>
						<?php
						printf(
							/* translators: %s: link to the Routes admin menu */
							esc_html__('Assign routes to this vehicle and set this vehicle\'s price for each. Routes themselves (name + stops) are created once under %s.', 'ecab-taxi-booking-manager'),
							'<a href="' . esc_url(admin_url('edit.php?post_type=mptbm_routes')) . '" target="_blank">' . esc_html__('Routes', 'ecab-taxi-booking-manager') . '</a>'
						);
						?>
					</span>
				</section>
				<section class="<?php echo esc_attr($price_based == 'fixed_route' ? 'mActive' : ''); ?>" data-collapse="#mp_fixed_route">
					<div class="mp_settings_area">
						<?php if (empty($all_routes)) : ?>
							<p>
								<?php
								printf(
									/* translators: %s: link to add a new route */
									esc_html__('No routes exist yet. %s first, then come back here to assign it to this vehicle.', 'ecab-taxi-booking-manager'),
									'<a href="' . esc_url(admin_url('edit.php?post_type=mptbm_routes')) . '" target="_blank">' . esc_html__('Create a route', 'ecab-taxi-booking-manager') . '</a>'
								);
								?>
							</p>
						<?php else : ?>
							<table>
								<thead>
									<tr>
										<th><?php esc_html_e('Route', 'ecab-taxi-booking-manager'); ?><span class="textRequired">&nbsp;*</span></th>
										<th><?php esc_html_e('Price', 'ecab-taxi-booking-manager'); ?><span class="textRequired">&nbsp;*</span></th>
										<th class="_w_100"><?php esc_html_e('Action', 'ecab-taxi-booking-manager'); ?></th>
									</tr>
								</thead>
								<tbody class="mp_sortable_area mp_item_insert">
									<?php
									if (sizeof($assigned_routes) > 0) {
										foreach ($assigned_routes as $assigned_route) {
											$this->route_price_item($assigned_route, $all_routes);
										}
									}
									?>
								</tbody>
							</table>
							<div class="my-2"></div>
							<?php MP_Custom_Layout::add_new_button(esc_html__('Assign Another Route', 'ecab-taxi-booking-manager')); ?>
							<?php $this->hidden_route_price_item($all_routes); ?>
						<?php endif; ?>
					</div>
				</section>

                <!-- Operation Area based Pricing -->
                <section class="bg-light" style="margin-top: 20px;" data-collapse="#mp_fixed_map_routes">
                    <h6><?php esc_html_e('Operation Area Based Price Set', 'ecab-taxi-booking-manager'); ?></h6>
                    <span><?php esc_html_e('Set different pricing for each operation area based on transport type, distance, or time. Easily manage fixed, per km, and per hour rates without creating duplicate transports.', 'ecab-taxi-booking-manager'); ?></span>
                </section>
                <?php
                ?>
                <section class="<?php echo esc_attr($price_based == 'fixed_distance' ? 'mActive' : ''); ?>" data-collapse="#mp_fixed_map_routes">
                    <input type="hidden" id="mptbm_operation_zones" value='<?php echo json_encode($operation_zones); ?>'>

                    <div id="mptbm_priceContainer">

                        <?php
                        $pricing = get_post_meta( $post_id, 'mptbm_operation_area_pricing' );
                        $show_save_btn = 'none';
                        if ( !empty( $pricing[0] ) ) :
                            $show_save_btn = 'block';
                            foreach ($pricing as $key => $values) :

                                foreach ($values as $area_key => $value) :

                                    if( !empty( $value ) ) :

                                       $fixed_price = isset( $value['fixed'] ) ? $value['fixed'] : 0;
                                       $per_km_price = isset( $value['per_km'] ) ? $value['per_km'] : '';
                                       $fixed_per_hour = isset( $value['per_hour'] ) ? $value['per_hour'] : '';
                                        ?>

                                        <div class="row">

                                            <select class="mptbm_areaSelect">
                                                <option value="">Select Area</option>

                                                <?php foreach ($operation_zones as $key => $name) : ?>
                                                    <option value="<?php echo esc_attr($key); ?>"
                                                        <?php selected($area_key, $key); ?>>
                                                        <?php echo esc_html($name); ?>
                                                    </option>
                                                <?php endforeach; ?>

                                            </select>

                                            <input type="number" class="mptbm_area_fixed_price" value="<?php echo esc_attr( $fixed_price ); ?>" placeholder="Fixed Price">

                                            <input type="number" class="mptbm_area_km_price" value="<?php echo esc_attr( $per_km_price ); ?>" placeholder="Per KM">

                                            <input type="number" class="mptbm_area_hour_price" value="<?php echo esc_attr( $fixed_per_hour ); ?>" placeholder="Per Hour">

                                            <button type="button" class="mptbm_area_remove"><?php esc_html_e('Remove', 'ecab-taxi-booking-manager')?></button>

                                        </div>
                                    <?php endif; ?>
                            <?php endforeach; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    </div>

                    <div class="mptbm_area_based_pricing_set" style="display: flex; justify-content: space-between">
                        <?php MP_Custom_Layout::add_new_button(esc_html__('Add Area Price', 'ecab-taxi-booking-manager'), 'mptbm_addAreaPrice'); ?>
                        <button class="mptbm_saveAreaData" id="mptbm_saveAreaData" style="display: <?php echo esc_attr( $show_save_btn );?>"><?php esc_html_e('Save', 'ecab-taxi-booking-manager')?></button>
                    </div>

<!--                    <button id="mptbm_addAreaPrice" style="float: right">--><?php //esc_html_e('Add Area Price +', 'ecab-taxi-booking-manager')?><!--</button>-->
                </section>

				<!-- Fixed Map Route Overrides -->
				<section class="bg-light" style="margin-top: 20px;" data-collapse="#mp_fixed_map_routes">
					<h6><?php esc_html_e('Fixed Map Route Overrides', 'ecab-taxi-booking-manager'); ?></h6>
					<span><?php esc_html_e('Define fixed prices for specific routes when using "Fixed with Map" mode. These will override the calculated price for the specified routes.', 'ecab-taxi-booking-manager'); ?></span>
				</section>
				<section class="<?php echo esc_attr($price_based == 'fixed_distance' ? 'mActive' : ''); ?>" data-collapse="#mp_fixed_map_routes">
					<div class="mp_settings_area" id="mptbm_fixed_map_route_settings">
						<table>
							<thead>
								<tr>
									<th><?php esc_html_e('Start Zone', 'ecab-taxi-booking-manager'); ?><span class="textRequired">&nbsp;*</span></th>
									<th><?php esc_html_e('End Zone', 'ecab-taxi-booking-manager'); ?><span class="textRequired">&nbsp;*</span></th>
									<th><?php esc_html_e('Price', 'ecab-taxi-booking-manager'); ?><span class="textRequired">&nbsp;*</span></th>
									<th class="_w_100"><?php esc_html_e('Action', 'ecab-taxi-booking-manager'); ?></th>
								</tr>
							</thead>
							<tbody class="mp_sortable_area mp_item_insert">
								<?php
								if (sizeof($fixed_map_route_prices) > 0) {
									foreach ($fixed_map_route_prices as $fixed_map_route_price) {
										$this->fixed_map_route_price_item($location_zones, $operation_zones, $fixed_map_route_price);
									}
								}
								?>
							</tbody>
						</table>
						<div class="my-2"></div>
						<?php MP_Custom_Layout::add_new_button(esc_html__('Add New Route', 'ecab-taxi-booking-manager')); ?>
						<?php $this->hidden_fixed_map_route_price_item($location_zones, $operation_zones); ?>
					</div>
					<script>
					jQuery(document).ready(function($) {
						var mptbm_location_zones = <?php echo json_encode($location_zones); ?>;
						var mptbm_operation_zones = <?php echo json_encode($operation_zones); ?>;
						
						function updateEndZoneOptionsMap($startSelect) {
							var $row = $startSelect.closest('tr');
							var $endSelect = $row.find('select[name="mptbm_fixed_map_route_end_location[]"]');
							var startValue = $startSelect.val();
							var currentEndValue = $endSelect.val();
							
							$endSelect.find('option:not(:first)').remove();
							
							if (!startValue) {
								$.each(mptbm_operation_zones, function(id, name) {
									$endSelect.append('<option value="' + id + '">' + name + '</option>');
								});
							} else if (startValue.indexOf('term_') === 0) {
								$.each(mptbm_operation_zones, function(id, name) {
									$endSelect.append('<option value="' + id + '">' + name + '</option>');
								});
							} else if (startValue.indexOf('post_') === 0) {
								$.each(mptbm_location_zones, function(id, name) {
									$endSelect.append('<option value="' + id + '">' + name + '</option>');
								});
							}
							
							if (currentEndValue && $endSelect.find('option[value="' + currentEndValue + '"]').length) {
								$endSelect.val(currentEndValue);
							}
							if ($endSelect.hasClass('select2-hidden-accessible')) {
								$endSelect.trigger('change.select2');
							}
						}
						
						$(document).on('change', '#mptbm_fixed_map_route_settings select[name="mptbm_fixed_map_route_start_location[]"]', function() {
							updateEndZoneOptionsMap($(this));
						});
						
						$('#mptbm_fixed_map_route_settings select[name="mptbm_fixed_map_route_start_location[]"]').each(function() {
							var $row = $(this).closest('tr');
							var $endSelect = $row.find('select[name="mptbm_fixed_map_route_end_location[]"]');
							if (!$endSelect.val()) {
								updateEndZoneOptionsMap($(this));
							}
						});
						
						$(document).on('click', '#mptbm_fixed_map_route_settings .mp_add_item', function() {
							setTimeout(function() {
								$('#mptbm_fixed_map_route_settings .mp_item_insert tr:last select[name="mptbm_fixed_map_route_start_location[]"]').each(function() {
									updateEndZoneOptionsMap($(this));
								});
							}, 100);
						});
					});
					</script>
				</section>

				<!-- Fixed Route & Zone Pricing -->
				<section class="bg-light" style="margin-top: 20px;" data-collapse="#mp_fixed_zone">
					<h6><?php esc_html_e('Fixed Route & Zone Pricing', 'ecab-taxi-booking-manager'); ?></h6>
					<span><?php esc_html_e('Define fixed prices for specific routes between zones or locations for "Fixed Zone" mode.', 'ecab-taxi-booking-manager'); ?></span>
				</section>
				<section class="<?php echo esc_attr($price_based == 'fixed_zone' ? 'mActive' : ''); ?>" data-collapse="#mp_fixed_zone">
					<div class="mp_settings_area" id="mptbm_fixed_zone_settings">
						<table>
							<thead>
								<tr>
									<th><?php esc_html_e('Start Zone', 'ecab-taxi-booking-manager'); ?><span class="textRequired">&nbsp;*</span></th>
									<th><?php esc_html_e('End Zone', 'ecab-taxi-booking-manager'); ?><span class="textRequired">&nbsp;*</span></th>
									<th><?php esc_html_e('Price', 'ecab-taxi-booking-manager'); ?><span class="textRequired">&nbsp;*</span></th>
									<th class="_w_100"><?php esc_html_e('Action', 'ecab-taxi-booking-manager'); ?></th>
								</tr>
							</thead>
							<tbody class="mp_sortable_area mp_item_insert">
								<?php
								if (sizeof($fixed_zone_prices) > 0) {
									foreach ($fixed_zone_prices as $fixed_zone_price) {
										$this->fixed_zone_price_item($location_zones, $operation_zones, $fixed_zone_price);
									}
								}
								?>
							</tbody>
						</table>
						<div class="my-2"></div>
						<?php MP_Custom_Layout::add_new_button(esc_html__('Add New Price', 'ecab-taxi-booking-manager')); ?>
						<?php $this->hidden_fixed_zone_price_item($location_zones, $operation_zones); ?>
					</div>
					<script>
					jQuery(document).ready(function($) {
						// Store zone data for JavaScript access
						var mptbm_location_zones = <?php echo json_encode($location_zones); ?>;
						var mptbm_operation_zones = <?php echo json_encode($operation_zones); ?>;
						
						function updateEndZoneOptions($startSelect) {
							var $row = $startSelect.closest('tr');
							var $endSelect = $row.find('select[name="mptbm_fixed_zone_end_location[]"]');
							var startValue = $startSelect.val();
							var currentEndValue = $endSelect.val();
							
							// Clear end zone options
							$endSelect.find('option:not(:first)').remove();
							
							if (!startValue) {
								// If no start zone selected, show all operation areas by default
								$.each(mptbm_operation_zones, function(id, name) {
									$endSelect.append('<option value="' + id + '">' + name + '</option>');
								});
							} else if (startValue.indexOf('term_') === 0) {
								// Location selected in Start → Show only Operation Areas in End
								$.each(mptbm_operation_zones, function(id, name) {
									$endSelect.append('<option value="' + id + '">' + name + '</option>');
								});
							} else if (startValue.indexOf('post_') === 0) {
								// Operation Area selected in Start → Show only Locations in End
								$.each(mptbm_location_zones, function(id, name) {
									$endSelect.append('<option value="' + id + '">' + name + '</option>');
								});
							}
							
							// Restore previous selection if still valid
							if (currentEndValue && $endSelect.find('option[value="' + currentEndValue + '"]').length) {
								$endSelect.val(currentEndValue);
							}
							
							// Reinitialize select2 if active
							if ($endSelect.hasClass('select2-hidden-accessible')) {
								$endSelect.trigger('change.select2');
							}
						}
						
						// Handle start zone change
						$(document).on('change', '#mptbm_fixed_zone_settings select[name="mptbm_fixed_zone_start_location[]"]', function() {
							updateEndZoneOptions($(this));
						});
						
						// Initialize on page load for existing rows
						$('#mptbm_fixed_zone_settings select[name="mptbm_fixed_zone_start_location[]"]').each(function() {
							// Don't update if end zone already has a valid value (editing existing)
							var $row = $(this).closest('tr');
							var $endSelect = $row.find('select[name="mptbm_fixed_zone_end_location[]"]');
							if (!$endSelect.val()) {
								updateEndZoneOptions($(this));
							}
						});
						
						// Handle new row added
						$(document).on('click', '#mptbm_fixed_zone_settings .mp_add_item', function() {
							setTimeout(function() {
								$('#mptbm_fixed_zone_settings .mp_item_insert tr:last select[name="mptbm_fixed_zone_start_location[]"]').each(function() {
									updateEndZoneOptions($(this));
								});
							}, 100);
						});
					});
					</script>
				</section>

				<section>
					<label class="label">
						<div>
							<h6><?php esc_html_e('Price Display Type', 'ecab-taxi-booking-manager'); ?></h6>
							<span class="desc"><?php esc_html_e('Choose how the price should be displayed', 'ecab-taxi-booking-manager'); ?></span>
						</div>
						<select class="formControl" name="mptbm_price_display_type" data-collapse-target>
							<option value="normal" <?php selected($price_display_type, 'normal'); ?>><?php esc_html_e('Normal Price', 'ecab-taxi-booking-manager'); ?></option>
							<option value="zero" <?php selected($price_display_type, 'zero'); ?>><?php esc_html_e('Show as Zero (0.00)', 'ecab-taxi-booking-manager'); ?></option>
							<option value="custom_message" <?php selected($price_display_type, 'custom_message'); ?>><?php esc_html_e('Show Custom Message', 'ecab-taxi-booking-manager'); ?></option>
						</select>
					</label>
				</section>
				
				<section data-collapse="#custom_message_section" class="<?php echo esc_attr($price_display_type == 'custom_message' ? 'mActive' : ''); ?>">
					<label class="label">
						<div>
							<h6><?php esc_html_e('Custom Price Message', 'ecab-taxi-booking-manager'); ?></h6>
							<span class="desc"><?php esc_html_e('Message to display instead of price (e.g. "Price pending confirmation")', 'ecab-taxi-booking-manager'); ?></span>
						</div>
						<textarea class="formControl" name="mptbm_custom_price_message" rows="3"><?php echo esc_textarea($custom_price_message); ?></textarea>
					</label>
				</section>

			</div>
		<?php
		}
		public function hidden_manual_price_item($location_terms)
		{
		?>
			<div class="mp_hidden_content">
				<table>
					<tbody class="mp_hidden_item">
						<?php $this->location_terms_add_price_item($location_terms); ?>
					</tbody>
				</table>
			</div>
		<?php
		}
		public function fixed_map_route_price_item($location_zones, $operation_zones, $fixed_route = array())
		{
			$fixed_route = $fixed_route && is_array($fixed_route) ? $fixed_route : array();
			$start_location = array_key_exists('start_location', $fixed_route) ? $fixed_route['start_location'] : '';
			$end_location = array_key_exists('end_location', $fixed_route) ? $fixed_route['end_location'] : '';
			$price = array_key_exists('price', $fixed_route) ? $fixed_route['price'] : '';
			$all_start_zones = array_merge($location_zones, $operation_zones);
			$all_end_zones = array_merge($location_zones, $operation_zones);
		?>
			<tr class="mp_remove_area">
				<td>
					<label>
						<select name="mptbm_fixed_map_route_start_location[]" class="formControl add_mp_select2" style="width:100% !important; min-width:150px;">
							<option value=""><?php esc_html_e('Select Start Zone', 'ecab-taxi-booking-manager'); ?></option>
							<?php foreach ($all_start_zones as $zone_id => $zone_name) : ?>
								<?php $selected = ($start_location == $zone_id) ? 'selected' : ''; ?>
								<option value="<?php echo esc_attr($zone_id); ?>" <?php echo esc_attr($selected); ?>><?php echo esc_html($zone_name); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</td>
				<td>
					<label>
						<select name="mptbm_fixed_map_route_end_location[]" class="formControl add_mp_select2" style="width:100% !important; min-width:150px;">
							<option value=""><?php esc_html_e('Select End Zone', 'ecab-taxi-booking-manager'); ?></option>
							<?php foreach ($all_end_zones as $zone_id => $zone_name) : ?>
								<?php $selected = ($end_location == $zone_id) ? 'selected' : ''; ?>
								<option value="<?php echo esc_attr($zone_id); ?>" <?php echo esc_attr($selected); ?>><?php echo esc_html($zone_name); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</td>
				<td>
					<label>
						<input type="text" name="mptbm_fixed_map_route_price[]" class="formControl mp_price_validation" value="<?php echo esc_attr($price); ?>" placeholder="<?php esc_attr_e('EX:10 ', 'ecab-taxi-booking-manager'); ?>" />
					</label>
				</td>
				<td>
					<?php MP_Custom_Layout::move_remove_button(); ?>
				</td>
			</tr>
		<?php
		}
		public function hidden_fixed_map_route_price_item($location_zones, $operation_zones)
		{
		?>
			<div class="mp_hidden_content">
				<table>
					<tbody class="mp_hidden_item">
						<?php $this->fixed_map_route_price_item($location_zones, $operation_zones); ?>
					</tbody>
				</table>
			</div>
		<?php
		}
		public function fixed_zone_price_item($location_zones, $operation_zones, $fixed_zone = array())
		{
			$fixed_zone = $fixed_zone && is_array($fixed_zone) ? $fixed_zone : array();
			$start_location = array_key_exists('start_location', $fixed_zone) ? $fixed_zone['start_location'] : '';
			$end_location = array_key_exists('end_location', $fixed_zone) ? $fixed_zone['end_location'] : '';
			$price = array_key_exists('price', $fixed_zone) ? $fixed_zone['price'] : '';
			
			// Combine all zones for start (locations + operation areas)
			$all_start_zones = array_merge($location_zones, $operation_zones);
			// Combine all zones for end (will be filtered by JavaScript based on start selection)
			$all_end_zones = array_merge($location_zones, $operation_zones);
		?>
			<tr class="mp_remove_area">
				<td>
					<label>
						<select name="mptbm_fixed_zone_start_location[]" class="formControl add_mp_select2" style="width:100% !important; min-width:150px;">
							<option value=""><?php esc_html_e('Select Start Zone', 'ecab-taxi-booking-manager'); ?></option>
							<?php foreach ($all_start_zones as $zone_id => $zone_name) : ?>
								<?php $selected = ($start_location == $zone_id) ? 'selected' : ''; ?>
								<option value="<?php echo esc_attr($zone_id); ?>" <?php echo esc_attr($selected); ?>><?php echo esc_html($zone_name); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</td>
				<td>
					<label>
						<select name="mptbm_fixed_zone_end_location[]" class="formControl add_mp_select2" style="width:100% !important; min-width:150px;">
							<option value=""><?php esc_html_e('Select End Zone', 'ecab-taxi-booking-manager'); ?></option>
							<?php foreach ($all_end_zones as $zone_id => $zone_name) : ?>
								<?php $selected = ($end_location == $zone_id) ? 'selected' : ''; ?>
								<option value="<?php echo esc_attr($zone_id); ?>" <?php echo esc_attr($selected); ?>><?php echo esc_html($zone_name); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</td>
				<td>
					<label>
						<input type="text" name="mptbm_fixed_zone_price[]" class="formControl mp_price_validation" value="<?php echo esc_attr($price); ?>" placeholder="<?php esc_attr_e('EX:10 ', 'ecab-taxi-booking-manager'); ?>" />
					</label>
				</td>
				<td>
					<?php MP_Custom_Layout::move_remove_button(); ?>
				</td>
			</tr>
		<?php
		}
		public function hidden_fixed_zone_price_item($location_zones, $operation_zones)
		{
		?>
			<div class="mp_hidden_content">
				<table>
					<tbody class="mp_hidden_item">
						<?php $this->fixed_zone_price_item($location_zones, $operation_zones); ?>
					</tbody>
				</table>
			</div>
		<?php
		}
		public function manual_price_item($manual_price = array())
		{
			$manual_price = $manual_price && is_array($manual_price) ? $manual_price : array();
			$start_location = array_key_exists('start_location', $manual_price) ? $manual_price['start_location'] : '';
			$end_location = array_key_exists('end_location', $manual_price) ? $manual_price['end_location'] : '';
			$price = array_key_exists('price', $manual_price) ? $manual_price['price'] : '';
		?>
			<tr class="mp_remove_area">
				<td>
					<label>
						<input type="text" name="mptbm_manual_start_location[]" class="formControl mp_name_validation" value="<?php echo esc_attr($start_location); ?>" placeholder="<?php esc_attr_e('EX:Dhaka', 'ecab-taxi-booking-manager'); ?>" />
					</label>
				</td>
				<td>
					<label>
						<input type="text" name="mptbm_manual_end_location[]" class="formControl mp_name_validation" value="<?php echo esc_attr($end_location); ?>" placeholder="<?php esc_attr_e('EX:Dhaka', 'ecab-taxi-booking-manager'); ?>" />
					</label>
				</td>
				<td>
					<label>
						<input type="text" name="mptbm_manual_price[]" class="formControl mp_price_validation" value="<?php echo esc_attr($price); ?>" placeholder="<?php esc_attr_e('EX:10 ', 'ecab-taxi-booking-manager'); ?>" />
					</label>
				</td>
				<td>
					<?php MP_Custom_Layout::move_remove_button(); ?>
				</td>
			</tr>
			<?php
		}
		// Static (not just an instance method) so the modern MPTBM_Rent_Custom_Editor
		// screen can reuse this exact row markup too, without instantiating a second
		// MPTBM_Price_Settings (which would re-register its save_post/tab-content
		// hooks a second time).
		public static function route_price_item($assigned_route = array(), $all_routes = array())
		{
			$assigned_route = $assigned_route && is_array($assigned_route) ? $assigned_route : array();
			$selected_route_id = array_key_exists('route_id', $assigned_route) ? absint($assigned_route['route_id']) : 0;
			$price = array_key_exists('price', $assigned_route) ? $assigned_route['price'] : '';
		?>
			<tr class="mp_remove_area">
				<td>
					<label>
						<select name="mptbm_assigned_route_id[]" class="formControl">
							<option value="" <?php echo esc_attr($selected_route_id ? '' : 'selected'); ?> disabled><?php esc_html_e('Select a route', 'ecab-taxi-booking-manager'); ?></option>
							<?php foreach ($all_routes as $route_post) { ?>
								<option value="<?php echo esc_attr($route_post->ID); ?>" <?php echo esc_attr($selected_route_id === $route_post->ID ? 'selected' : ''); ?>><?php echo esc_html($route_post->post_title); ?></option>
							<?php } ?>
						</select>
					</label>
				</td>
				<td>
					<label>
						<input type="text" name="mptbm_assigned_route_price[]" class="formControl mp_price_validation" value="<?php echo esc_attr($price); ?>" placeholder="<?php esc_attr_e('EX:50', 'ecab-taxi-booking-manager'); ?>" />
					</label>
				</td>
				<td>
					<?php MP_Custom_Layout::move_remove_button(); ?>
				</td>
			</tr>
		<?php
		}
		public static function hidden_route_price_item($all_routes = array())
		{
		?>
			<div class="mp_hidden_content">
				<table>
					<tbody class="mp_hidden_item">
						<?php self::route_price_item(array(), $all_routes); ?>
					</tbody>
				</table>
			</div>
		<?php
		}
		public function location_terms_price_item($location_terms = array(), $terms_location_prices = array())
		{

			foreach ($terms_location_prices as $terms_location_price) {
				$start_location = $terms_location_price['start_location'];
				$end_location = $terms_location_price['end_location'];
				$terms_price = $terms_location_price['price'];
			?>


				<tr class="mp_remove_area">
					<td>
						<label>
							<select name="mptbm_terms_start_location[]" class="formControl mp_name_validation">
								<option value="">Select Start Location</option>
								<?php
								foreach ($location_terms as $term) {
									if ($start_location == $term->slug) {
										$selected = 'selected';
									} else {
										$selected = '';
									}
								?>
									<option value="<?php echo esc_attr($term->slug); ?>" <?php echo esc_attr($selected); ?>><?php echo esc_html($term->name); ?></option>
								<?php } ?>
							</select>
						</label>
					</td>

					<td>
						<label>
							<select name="mptbm_terms_end_location[]" class="formControl mp_name_validation">
								<option value="">Select End Location</option>
								<?php foreach ($location_terms as $term) : ?>
									<?php
									$selected = ($end_location == $term->slug) ? 'selected' : '';
									?>
									<option value="<?php echo esc_attr($term->slug); ?>" <?php echo  esc_attr($selected); ?>><?php echo esc_html($term->name); ?></option>
								<?php endforeach; ?>
							</select>

						</label>
					</td>

					<td>
						<label>
							<input type="text" name="mptbm_location_terms_price[]" class="formControl mp_price_validation" value="<?php echo esc_attr($terms_price); ?>" placeholder="<?php esc_attr_e('EX:10 ', 'ecab-taxi-booking-manager'); ?>" />
						</label>
					</td>

					<td>
						<?php MP_Custom_Layout::move_remove_button(); ?>
					</td>
				</tr>
			<?php
			}
		}
		public function location_terms_add_price_item($location_terms = array())
		{
			?>
			<tr class="mp_remove_area">
				<td>
					<label>
						<select name="mptbm_terms_start_location[]" class="formControl mp_name_validation">
							<option value="">Select Start Location</option>

							<?php foreach ($location_terms as $term) : ?>

								<?php

								// $selected = ($start_location == $term->slug) ? 'selected' : '';
								?>
								<option value="<?php echo esc_attr($term->slug); ?>"><?php echo esc_html($term->name); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</td>

				<td>
					<label>
						<select name="mptbm_terms_end_location[]" class="formControl mp_name_validation">
							<option value="">Select End Location</option>
							<?php foreach ($location_terms as $term) : ?>
								<option value="<?php echo esc_attr($term->slug); ?>"><?php echo esc_html($term->name); ?></option>
							<?php endforeach; ?>
						</select>

					</label>
				</td>

				<td>
					<label>
						<input type="text" name="mptbm_location_terms_price[]" class="formControl mp_price_validation" value="" placeholder="<?php esc_attr_e('EX:10 ', 'ecab-taxi-booking-manager'); ?>" />
					</label>
				</td>

				<td>
					<?php MP_Custom_Layout::move_remove_button(); ?>
				</td>
			</tr>
<?php

		}
		public function save_price_settings($post_id)
		{
//            error_log( print_r( [ '$_POSTPrice'  =>$_POST ], true ) );
			if (
				!isset($_POST['mptbm_price_settings_nonce']) ||
				!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mptbm_price_settings_nonce'])), 'mptbm_price_settings_action')
			) {
				return; // Exit if nonce is invalid
			}
			if (get_post_type($post_id) == MPTBM_Function::get_cpt()) {
				if (isset($_POST['mptbm_initial_price']) && !is_serialized($_POST['mptbm_initial_price']) && current_user_can('manage_options')) {
					$initial_price = filter_var($_POST['mptbm_initial_price'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
					update_post_meta($post_id, 'mptbm_initial_price', $initial_price);
				}

				$min_price = isset($_POST['mptbm_min_price']) ? sanitize_text_field($_POST['mptbm_min_price']) : '';
				update_post_meta($post_id, 'mptbm_min_price', $min_price);
				$return_min_price = isset($_POST['mptbm_min_price_return']) ? sanitize_text_field($_POST['mptbm_min_price_return']) : '';
				update_post_meta($post_id, 'mptbm_min_price_return', $return_min_price);
				$return_discount = isset($_POST['mptbm_return_discount']) ? sanitize_text_field($_POST['mptbm_return_discount']) : '';
				update_post_meta($post_id, 'mptbm_return_discount', $return_discount);
				$price_based = isset($_POST['mptbm_price_based']) ? sanitize_text_field($_POST['mptbm_price_based']) : '';
				update_post_meta($post_id, 'mptbm_price_based', $price_based);
				$distance_price = isset($_POST['mptbm_km_price']) ? sanitize_text_field($_POST['mptbm_km_price']) : 0;
				update_post_meta($post_id, 'mptbm_km_price', $distance_price);
				$hour_price = isset($_POST['mptbm_hour_price']) ? sanitize_text_field($_POST['mptbm_hour_price']) : 0;
				update_post_meta($post_id, 'mptbm_hour_price', $hour_price);
				$day_price = isset($_POST['mptbm_day_price']) ? sanitize_text_field($_POST['mptbm_day_price']) : 0;
				update_post_meta($post_id, 'mptbm_day_price', $day_price);
				$fixed_map_price = isset($_POST['mptbm_fixed_map_price']) ? sanitize_text_field($_POST['mptbm_fixed_map_price']) : 0;
				update_post_meta($post_id, 'mptbm_fixed_map_price', $fixed_map_price);
				$manual_price_infos = array();
				$start_location = isset($_POST['mptbm_manual_start_location']) ? array_map('sanitize_text_field', $_POST['mptbm_manual_start_location']) : [];
				$end_location = isset($_POST['mptbm_manual_end_location']) ? array_map('sanitize_text_field', $_POST['mptbm_manual_end_location']) : [];
				$manual_price = isset($_POST['mptbm_manual_price']) ? array_map('sanitize_text_field', $_POST['mptbm_manual_price']) : [];

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

				// Route name/waypoints now live once on the global mptbm_routes CPT
				// (Routes admin menu) - this vehicle only stores which route IDs it
				// offers and its own price for each (route_id is re-validated against
				// real published mptbm_routes posts, never trusted from POST directly).
				$assigned_route_infos = array();
				$assigned_route_ids = isset($_POST['mptbm_assigned_route_id']) ? array_map('absint', $_POST['mptbm_assigned_route_id']) : [];
				$assigned_route_prices = isset($_POST['mptbm_assigned_route_price']) ? array_map('sanitize_text_field', $_POST['mptbm_assigned_route_price']) : [];

				if (sizeof($assigned_route_ids) > 0) {
					$count = 0;
					foreach ($assigned_route_ids as $key => $route_id) {
						$price = isset($assigned_route_prices[$key]) ? $assigned_route_prices[$key] : '';
						if ($route_id && $price !== '' && get_post_type($route_id) === 'mptbm_routes') {
							$assigned_route_infos[$count]['route_id'] = $route_id;
							$assigned_route_infos[$count]['price'] = $price;
							$count++;
						}
					}
				}

				update_post_meta($post_id, 'mptbm_assigned_routes', $assigned_route_infos);

				$fixed_zone_price_infos = array();
				// "Fixed Zone" pricing has two editor UIs posting under different field
				// names for the same rows: the custom editor (active screen) posts
				// mptbm_zone_to_zone_route_*, the legacy `?editor=old` screen posts
				// mptbm_fixed_zone_*. Both save into mptbm_fixed_zone_price_info below, so
				// only read whichever set the submitted form actually populated - otherwise
				// whichever ran last would overwrite real data with an empty array.
				if (isset($_POST['mptbm_zone_to_zone_route_start_location'])) {
					$start_zone = array_map('sanitize_text_field', $_POST['mptbm_zone_to_zone_route_start_location']);
					$end_zone = isset($_POST['mptbm_zone_to_zone_route_end_location']) ? array_map('sanitize_text_field', $_POST['mptbm_zone_to_zone_route_end_location']) : [];
					$zone_price = isset($_POST['mptbm_zone_to_zone_route_price']) ? array_map('sanitize_text_field', $_POST['mptbm_zone_to_zone_route_price']) : [];
				} elseif (isset($_POST['mptbm_fixed_zone_start_location'])) {
					$start_zone = array_map('sanitize_text_field', $_POST['mptbm_fixed_zone_start_location']);
					$end_zone = isset($_POST['mptbm_fixed_zone_end_location']) ? array_map('sanitize_text_field', $_POST['mptbm_fixed_zone_end_location']) : [];
					$zone_price = isset($_POST['mptbm_fixed_zone_price']) ? array_map('sanitize_text_field', $_POST['mptbm_fixed_zone_price']) : [];
				} else {
					$start_zone = [];
					$end_zone = [];
					$zone_price = [];
				}

				if (count($start_zone) > 0) {
					$count = 0;
					foreach ($start_zone as $key => $location) {
						$e_zone = isset($end_zone[$key]) ? $end_zone[$key] : '';
						$z_price = isset($zone_price[$key]) ? $zone_price[$key] : '';

						if ($location && $e_zone && $z_price) {
							$fixed_zone_price_infos[$count]['start_location'] = $location;
							$fixed_zone_price_infos[$count]['end_location'] = $e_zone;
							$fixed_zone_price_infos[$count]['price'] = $z_price;
							$count++;
						}
					}
				}
				update_post_meta($post_id, 'mptbm_fixed_zone_price_info', $fixed_zone_price_infos);

				$fixed_map_route_price_infos = array();
				$start_map_route = isset($_POST['mptbm_fixed_map_route_start_location']) ? array_map('sanitize_text_field', $_POST['mptbm_fixed_map_route_start_location']) : [];
				$end_map_route = isset($_POST['mptbm_fixed_map_route_end_location']) ? array_map('sanitize_text_field', $_POST['mptbm_fixed_map_route_end_location']) : [];
				$map_route_price = isset($_POST['mptbm_fixed_map_route_price']) ? array_map('sanitize_text_field', $_POST['mptbm_fixed_map_route_price']) : [];

				if (count($start_map_route) > 0) {
					$count = 0;
					foreach ($start_map_route as $key => $location) {
						$e_route = isset($end_map_route[$key]) ? $end_map_route[$key] : '';
						$r_price = isset($map_route_price[$key]) ? $map_route_price[$key] : '';

						if ($location && $e_route && $r_price) {
							$fixed_map_route_price_infos[$count]['start_location'] = $location;
							$fixed_map_route_price_infos[$count]['end_location'] = $e_route;
							$fixed_map_route_price_infos[$count]['price'] = $r_price;
							$count++;
						}
					}
				}
				update_post_meta($post_id, 'mptbm_fixed_map_route_price_info', $fixed_map_route_price_infos);

				// "Fixed with Map: Zone To Zone" pricing is Custom-Editor-only - the
				// classic editor's own "Fixed Map Route" section has no zone-to-zone
				// fields and never posts mptbm_operation_area_fixed_map_type at all. So
				// only touch these two metas when the submitting form actually included
				// that field; otherwise every classic-editor save would force the type
				// back to 'zone_to_location' and wipe the zone-to-zone price rows.
				if (isset($_POST['mptbm_operation_area_fixed_map_type'])) {
					$mptbm_fixed_map_area_to_area_price_info = array();
					$start_map_area_to_area_route = isset($_POST['mptbm_fixed_map_route_zone_to_zone_start_location']) ? array_map('sanitize_text_field', $_POST['mptbm_fixed_map_route_zone_to_zone_start_location']) : [];
					$end_map_area_to_area_route = isset($_POST['mptbm_fixed_map_route_zone_to_zone_end_location']) ? array_map('sanitize_text_field', $_POST['mptbm_fixed_map_route_zone_to_zone_end_location']) : [];
					$map_route_area_to_area_price = isset($_POST['mptbm_fixed_map_route_zone_to_zone_price']) ? array_map('sanitize_text_field', $_POST['mptbm_fixed_map_route_zone_to_zone_price']) : [];

					if (count($start_map_area_to_area_route) > 0) {
						$count = 0;
						foreach ($start_map_area_to_area_route as $key => $location) {
							$e_route = isset($end_map_area_to_area_route[$key]) ? $end_map_area_to_area_route[$key] : '';
							$r_price = isset($map_route_area_to_area_price[$key]) ? $map_route_area_to_area_price[$key] : '';

							if ($location && $e_route && $r_price) {
								$mptbm_fixed_map_area_to_area_price_info[$count]['start_location'] = $location;
								$mptbm_fixed_map_area_to_area_price_info[$count]['end_location'] = $e_route;
								$mptbm_fixed_map_area_to_area_price_info[$count]['price'] = $r_price;
								$count++;
							}
						}
					}
					update_post_meta( $post_id, 'mptbm_fixed_map_area_to_area_price_info', $mptbm_fixed_map_area_to_area_price_info);

					$price_display_type = sanitize_text_field($_POST['mptbm_operation_area_fixed_map_type']);
					update_post_meta( $post_id, 'mptbm_operation_area_fixed_map_type', $price_display_type);
				}

				$terms_price_infos = array();
				$start_terms_location = isset($_POST['mptbm_terms_start_location']) ? array_map('sanitize_text_field', $_POST['mptbm_terms_start_location']) : [];
				$end_terms_location = isset($_POST['mptbm_terms_end_location']) ? array_map('sanitize_text_field', $_POST['mptbm_terms_end_location']) : [];
				$terms_price = isset($_POST['mptbm_location_terms_price']) ? array_map('sanitize_text_field', $_POST['mptbm_location_terms_price']) : [];


				if (sizeof($start_terms_location) > 0 && sizeof($end_terms_location) > 0 && sizeof($terms_price) > 0) {
                    
					$count = 0;
					foreach ($start_terms_location as $key => $location) {
						if ($location && $end_terms_location[$key] && $terms_price[$key]) {
							$terms_price_infos[$count]['start_location'] = $location;
							$terms_price_infos[$count]['end_location'] = $end_terms_location[$key];
							$terms_price_infos[$count]['price'] = $terms_price[$key];
							$count++;
						}
					}
                }




				update_post_meta($post_id, 'mptbm_terms_price_info', $terms_price_infos);
				$waiting_price = isset($_POST['mptbm_waiting_price']) ? sanitize_text_field($_POST['mptbm_waiting_price']) : '';
				update_post_meta($post_id, 'mptbm_waiting_price', $waiting_price);
				$stop_price = isset($_POST['mptbm_stop_price']) ? sanitize_text_field($_POST['mptbm_stop_price']) : '';
				update_post_meta($post_id, 'mptbm_stop_price', $stop_price);
				$price_display_type = isset($_POST['mptbm_price_display_type']) ? sanitize_text_field($_POST['mptbm_price_display_type']) : 'normal';
				update_post_meta($post_id, 'mptbm_price_display_type', $price_display_type);
				$custom_price_message = isset($_POST['mptbm_custom_price_message']) ? sanitize_textarea_field($_POST['mptbm_custom_price_message']) : '';
				update_post_meta($post_id, 'mptbm_custom_price_message', $custom_price_message);

                $this->get_area_based_pricing( $post_id, $_POST );
			}
		}

        public function get_area_based_pricing( $post_id, $POST ){

            // mptbm_area_based_post[] etc. are only rendered by the custom editor
            // (MPTBM_Rent_Custom_Editor.php). The classic `?editor=old` screen's own
            // "Operation Area Based Price Set" section saves separately via its own
            // AJAX call (mptbm_operation_area_price_data_set) and never posts these
            // fields on its main "Update" submit - so if we don't bail out here, that
            // submit falls through to update_post_meta() below with an empty array and
            // wipes out whatever the custom editor had saved.
            if ( ! isset( $POST['mptbm_area_based_post'] ) || ! is_array( $POST['mptbm_area_based_post'] ) ) {
                return;
            }

            $area_based_pricing = [];

            foreach ($POST['mptbm_area_based_post'] as $index => $area_post_id ) {
                $area_post_id = trim($area_post_id);
                if (empty($area_post_id)) {
                    continue;
                }

                $area_based_pricing[$area_post_id] = [
                    'fixed' => isset($POST['mptbm_area_based_fixed'][$index])
                        ? sanitize_text_field($POST['mptbm_area_based_fixed'][$index])
                        : '',

                    'per_km' => isset($POST['mptbm_area_based_per_km'][$index])
                        ? sanitize_text_field($POST['mptbm_area_based_per_km'][$index])
                        : '',

                    'per_hour' => isset($POST['mptbm_area_based_per_hour'][$index])
                        ? sanitize_text_field($POST['mptbm_area_based_per_hour'][$index])
                        : '',
                ];
            }

            if ( !$post_id ) {
                wp_send_json_error('Invalid data');
            }

            update_post_meta( $post_id, 'mptbm_operation_area_pricing', $area_based_pricing);

        }

        /**
         * Save the per-operation-area pricing rows for one transport.
         *
         * SECURITY: this writes a value that feeds straight into the fare customers are
         * charged (MPTBM_Function::get_price() reads mptbm_operation_area_pricing), so
         * every part of the request has to be established before the write:
         *
         *  - The nonce proves the request came from the editor screen. The browser has
         *    always sent one; nothing verified it, so the endpoint was reachable with any
         *    logged-in session and a plain HTTP client.
         *  - Authorisation is against THIS post. The previous current_user_can('edit_posts')
         *    is a generic capability the built-in Contributor role also holds, so it
         *    established nothing about the target: a contributor could rewrite the pricing
         *    of an administrator's transport just by passing its post_id.
         *  - The post type is checked, so an arbitrary post ID cannot be given plugin
         *    pricing meta.
         *  - The decoded payload is rebuilt field by field instead of being trusted as
         *    sent.
         */
        function mptbm_operation_area_price_data_set() {

            check_ajax_referer('mptbm_operation_area_price', 'nonce');

            $post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;

            if ( ! $post_id || get_post_type( $post_id ) !== MPTBM_Function::get_cpt() ) {
                wp_send_json_error('Invalid data');
            }

            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                wp_send_json_error('Permission denied');
            }

            $raw = isset( $_POST['area_price_data'] )
                ? json_decode( wp_unslash( $_POST['area_price_data'] ), true )
                : null;

            if ( ! is_array( $raw ) ) {
                wp_send_json_error('Invalid data');
            }

            // Rebuilt rather than stored as sent: only the three price fields the editor
            // produces survive, keyed the way get_area_based_pricing() keys them.
            $pricing = [];
            foreach ( $raw as $area_key => $row ) {
                $area_key = sanitize_key( $area_key );
                if ( $area_key === '' || ! is_array( $row ) ) {
                    continue;
                }
                $pricing[ $area_key ] = [
                    'fixed'    => isset( $row['fixed'] ) ? sanitize_text_field( $row['fixed'] ) : '',
                    'per_km'   => isset( $row['per_km'] ) ? sanitize_text_field( $row['per_km'] ) : '',
                    'per_hour' => isset( $row['per_hour'] ) ? sanitize_text_field( $row['per_hour'] ) : '',
                ];
            }

            // Save to meta
            update_post_meta( $post_id, 'mptbm_operation_area_pricing', $pricing);

            wp_send_json_success('Saved successfully');
        }
	}
	new MPTBM_Price_Settings();
}
