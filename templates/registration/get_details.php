<?php
	/*
 * @Author 		engr.sumonazma@gmail.com
 * Copyright: 	mage-people.com
 */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly
	$price_based = $price_based ?? '';
	$price_based = $price_based ?? '';
	$all_dates = MPTBM_Function::get_all_dates($price_based);
	//echo '<pre>';print_r($all_dates);echo '</pre>';
	$form_style = $form_style ?? 'horizontal';
	$form_style_class = $form_style == 'horizontal' ? 'inputHorizontal' : 'inputInline';
	$area_class = $price_based == 'manual' ? ' ' : 'justifyBetween';
	$area_class = $form_style != 'horizontal' ? 'mptbm_form_details_area fdColumn' : $area_class;
	if (sizeof($all_dates) > 0) {
		$taxi_return = MPTBM_Function::get_general_settings('taxi_return', 'enable');
		$waiting_time_check = MPTBM_Function::get_general_settings('taxi_waiting_time', 'enable');
		?>
		<div class="<?php echo esc_attr($area_class); ?> ">
			<div class="_dLayout mptbm_search_area <?php echo esc_attr($form_style_class); ?> <?php echo esc_attr($price_based == 'manual' ? 'mAuto' : ''); ?>">
				<div class="mpForm">
					<input type="hidden" name="mptbm_price_based" value="<?php echo esc_attr($price_based); ?>"/>
					<input type="hidden" name="mptbm_post_id" value=""/>
					<div class="inputList">
						<label class="fdColumn">
							<input type="hidden" id="mptbm_map_start_date" value=""/>
							<span><?php esc_html_e('Pickup Date', 'mptbm_plugin'); ?></span>
							<input type="text" id="mptbm_start_date" class="formControl" placeholder="<?php esc_attr_e('Select Date', 'mptbm_plugin'); ?>" value="" readonly/>
							<span class="far fa-calendar-alt mptbm_left_icon allCenter"></span>
						</label>
					</div>
					<div class="inputList mp_input_select">
						<input type="hidden" id="mptbm_map_start_time" value=""/>
						<label class="fdColumn">
							<span><?php esc_html_e('Pickup Time', 'mptbm_plugin'); ?></span>
							<input type="text" class="formControl" placeholder="<?php esc_html_e('Please Select Time', 'mptbm_plugin'); ?>" value="" readonly/>
							<span class="far fa-clock mptbm_left_icon allCenter"></span>
						</label>
						<ul class="mp_input_select_list">
							<?php for ($i = 0; $i <= 23.5; $i = $i + 0.5) { ?>
								<li data-value="<?php echo esc_attr($i); ?>"><?php echo MP_Global_Function::date_format(date('H:i', $i * 3600), 'time'); ?></li>
							<?php } ?>
						</ul>
					</div>
					<div class="inputList">
						<label class="fdColumn ">
							<span><i class="fas fa-map-marker-alt _textTheme_mR_xs"></i><?php esc_html_e('Pickup Location', 'mptbm_plugin'); ?></span>
							<?php if ($price_based == 'manual') { ?>
								<?php $all_start_locations = MPTBM_Function::get_all_start_location(); ?>
								<select id="mptbm_manual_start_place" class="formControl">
									<option selected disabled><?php esc_html_e(' Select Pick-Up Location', 'mptbm_plugin'); ?></option>
									<?php if (sizeof($all_start_locations) > 0) { ?>
										<?php foreach ($all_start_locations as $start_location) { ?>
											<option value="<?php echo esc_attr($start_location); ?>"><?php echo esc_html($start_location); ?></option>
										<?php } ?>
									<?php } ?>
								</select>
							<?php } else { ?>
								<input type="text" id="mptbm_map_start_place" class="formControl" placeholder="<?php esc_html_e('Enter Pick-Up Location', 'mptbm_plugin'); ?>" value=""/>
							<?php } ?>
						</label>
					</div>
					<div class="inputList">
						<label class="fdColumn mptbm_manual_end_place">
							<span><i class="fas fa-map-marker-alt _textTheme_mR_xs"></i><?php esc_html_e('Drop-Off Location', 'mptbm_plugin'); ?></span>
							<?php if ($price_based == 'manual') { ?>
								<select class="formControl mptbm_map_end_place" id="mptbm_manual_end_place">
									<option selected disabled><?php esc_html_e(' Select Destination Location', 'mptbm_plugin'); ?></option>
								</select>
							<?php } else { ?>
								<input type="text" id="mptbm_map_end_place" class="formControl" placeholder="<?php esc_html_e(' Enter Drop-Off Location', 'mptbm_plugin'); ?>" value=""/>
							<?php } ?>
						</label>
					</div>
				</div>
				<div class="mpForm">
					<?php if ($taxi_return == 'enable') { ?>
						<div class="inputList">
							<label class="fdColumn">
								<span><?php esc_html_e('Transfer Type', 'mptbm_plugin'); ?></span>
								<select class="formControl" name="mptbm_taxi_return">
									<option value="1" selected><?php esc_html_e('One Way', 'mptbm_plugin'); ?></option>
									<option value="2"><?php esc_html_e('Return', 'mptbm_plugin'); ?></option>
								</select>
							</label>
						</div>
					<?php } ?>
					<?php if ($waiting_time_check == 'enable') { ?>
						<div class="inputList">
							<label class="fdColumn">
								<span><?php esc_html_e('Extra Waiting Hours', 'mptbm_plugin'); ?></span>
								<select class="formControl" name="mptbm_waiting_time">
									<option value="" selected><?php esc_html_e('No Waiting', 'mptbm_plugin'); ?></option>
									<option value="1"><?php esc_html_e('1 Hour', 'mptbm_plugin'); ?></option>
									<option value="2"><?php esc_html_e('2 Hours', 'mptbm_plugin'); ?></option>
									<option value="3"><?php esc_html_e('3 Hours', 'mptbm_plugin'); ?></option>
									<option value="4"><?php esc_html_e('4 Hours', 'mptbm_plugin'); ?></option>
									<option value="5"><?php esc_html_e('5 Hours', 'mptbm_plugin'); ?></option>
									<option value="6"><?php esc_html_e('6 Hours', 'mptbm_plugin'); ?></option>
								</select>
							</label>
						</div>
					<?php } ?>
					<?php if ($taxi_return != 'enable') { ?>
						<div class="inputList"></div>
					<?php } ?>
					<?php if ($waiting_time_check != 'enable') { ?>
						<div class="inputList"></div>
					<?php } ?>
					<div class="inputList"></div>
					<?php if ($form_style == 'horizontal') { ?>
						<div class="divider"></div>
					<?php } ?>
					<div class="inputList">
						<span>&nbsp;</span>
						<button type="button" class="_themeButton_fullWidth" id="mptbm_get_vehicle">
							<span class="fas fa-search-location mR_xs"></span>
							<?php esc_html_e('Search', 'mptbm_plugin'); ?>
						</button>
					</div>
				</div>
			</div>
			<?php if ($price_based != 'manual') { ?>
				<div class="mptbm_map_area fdColumn">
					<div class="fullHeight">
						<div id="mptbm_map_area"></div>
					</div>
					<div class="_dLayout mptbm_distance_time">
						<div class="_equalChild_separatorRight">
							<div class="_dFlex_pR_xs">
								<h1 class="_mR">
									<span class="fas fa-route textTheme"></span>
								</h1>
								<div class="fdColumn">
									<h6><?php esc_html_e('TOTAL DISTANCE', 'mptbm_plugin'); ?></h6>
									<strong class="mptbm_total_distance"><?php esc_html_e(' 0 KM', 'mptbm_plugin'); ?></strong>
								</div>
							</div>
							<div class="dFlex">
								<h1 class="_mLR">
									<span class="fas fa-clock textTheme"></span>
								</h1>
								<div class="fdColumn">
									<h6><?php esc_html_e('TOTAL TIME', 'mptbm_plugin'); ?></h6>
									<strong class="mptbm_total_time"><?php esc_html_e('0 Hour', 'mptbm_plugin'); ?></strong>
								</div>
							</div>
						</div>
					</div>
				</div>
			<?php } ?>
		</div>
		<div class="_fullWidth get_details_next_link">
			<div class="divider"></div>
			<div class="justifyBetween">
				<button type="button" class="mpBtn nextTab_prev">
					<span>&larr; &nbsp;<?php esc_html_e('Previous', 'mptbm_plugin'); ?></span>
				</button>
				<div></div>
				<button type="button" class="_themeButton_min_200 nextTab_next">
					<span><?php esc_html_e('Next', 'mptbm_plugin'); ?>&nbsp; &rarr;</span>
				</button>
			</div>
		</div>
		<?php do_action('mp_load_date_picker_js', '#mptbm_start_date', $all_dates); ?>
	<?php } else { ?>
		<div class="dLayout">
			<h3 class="_textDanger_textCenter">
				<?php esc_html_e('Now All Service closed.', 'mptbm_plugin'); ?>
			</h3>
		</div>
		<?php
	}
