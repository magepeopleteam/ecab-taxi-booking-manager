<?php
if (!function_exists('mptbm_get_translation')) {
	require_once dirname(__DIR__, 2) . '/inc/mptbm-translation-helper.php';
}
/*
 * @Author 		engr.sumonazma@gmail.com
 * Copyright: 	mage-people.com
 */
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly



delete_transient('original_price_based');
$restrict_search_country = MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_country_restriction', 'no');

$country = MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_country', 'no');
$km_or_mile = MP_Global_Function::get_settings('mp_global_settings', 'km_or_mile', 'km');
$price_based = $price_based ?? '';
set_transient('original_price_based', $price_based);

$map = $map ?? 'yes';
$map = strtolower($map); // Normalize the value to lowercase

$all_dates = MPTBM_Function::get_all_dates($price_based);
$form_style = $form_style ?? 'horizontal';
$form_style_class = $form_style == 'horizontal' ? 'inputHorizontal' : 'inputInline';
$area_class = $price_based == 'manual' ? ' ' : 'justifyBetween';
$area_class = $form_style != 'horizontal' ? 'mptbm_form_details_area fdColumn' : $area_class;
$mptbm_all_transport_id = MP_Global_Function::get_all_post_id('mptbm_rent');
$mptbm_available_for_all_time = false;
$mptbm_schedule = [];
$min_schedule_value = 0;
$max_schedule_value = 24;
$loop = 1;

foreach ($mptbm_all_transport_id as $key => $value) {
	if (MP_Global_Function::get_post_info($value, 'mptbm_available_for_all_time') == 'on') {
		$mptbm_available_for_all_time = true;
	}
}

if ($mptbm_available_for_all_time == false) {

	foreach ($mptbm_all_transport_id as $key => $value) {
		array_push($mptbm_schedule, MPTBM_Function::get_schedule($value));
	}
	foreach ($mptbm_schedule as $dayArray) {
		foreach ($dayArray as $times) {
			if (is_array($times)) {
				if ($loop) {
					$min_schedule_value = $times[0];
					$max_schedule_value = $times[0];
					$loop = 0;
				}
				// Loop through each element in the array
				foreach ($times as $time) {

					// Update the global smallest and largest values
					if ($time < $min_schedule_value) {
						$min_schedule_value = $time;
					}
					if ($time > $max_schedule_value) {
						$max_schedule_value = $time;
					}
				}
			}
		}
	}
}
// Ensure the schedule values are numeric
$min_schedule_value = floatval($min_schedule_value);
$max_schedule_value = floatval($max_schedule_value);

if (!function_exists('convertToMinutes')) {
	function convertToMinutes($schedule_value)
	{
		$hours = floor($schedule_value); // Get the hour part
		$minutes = ($schedule_value - $hours) * 100; // Convert decimal part to minutes
		return $hours * 60 + $minutes;
	}
}

$min_minutes = convertToMinutes($min_schedule_value);
$max_minutes = convertToMinutes($max_schedule_value);

$buffer_time = (int) MP_Global_Function::get_settings('mptbm_general_settings', 'enable_buffer_time');

$current_time = time();
$current_hour = wp_date('H', $current_time);
$current_minute = wp_date('i', $current_time);

// Convert to total minutes since midnight local time
$current_minutes = intval($current_hour) * 60 + intval($current_minute);

// Calculate buffer end time in minutes since midnight
$buffer_end_minutes = $current_minutes + $buffer_time;

// Ensure buffer_end_minutes is not negative
$buffer_end_minutes = max($buffer_end_minutes, 0);

// If buffer extends beyond current day, remove today from available dates
if ($buffer_end_minutes >= 1440) {
	// Remove today from available dates
	if (!empty($all_dates)) {
		array_shift($all_dates);
	}
	// Adjust buffer_end_minutes for next day
	$buffer_end_minutes = $buffer_end_minutes - 1440;
}

if (sizeof($all_dates) > 0) {
	$taxi_return = MPTBM_Function::get_general_settings('taxi_return', 'enable');
	$interval_time = MPTBM_Function::get_general_settings('mptbm_pickup_interval_time', '30');
	$interval_hours = $interval_time / 60;
	$waiting_time_check = MPTBM_Function::get_general_settings('taxi_waiting_time', 'enable');

	// Check if Pro plugin is active
	$pro_active = class_exists('MPTBM_Dependencies_Pro');
	// Get settings only if Pro is active
	$search_filter_settings = $pro_active ? get_option('mptbm_search_filter_settings', array()) : array();
	$enable_max_passenger_filter = isset($search_filter_settings['enable_max_passenger_filter']) ? $search_filter_settings['enable_max_passenger_filter'] : 'no';
	$enable_max_bag_filter = isset($search_filter_settings['enable_max_bag_filter']) ? $search_filter_settings['enable_max_bag_filter'] : 'no';

	// Use actual meta keys for dropdowns
	$mptbm_bags = [];
	$mptbm_passengers = [];
	$mptbm_all_transport_id = MP_Global_Function::get_all_post_id('mptbm_rent');
	foreach ($mptbm_all_transport_id as $post_id) {
		$bag = (int) get_post_meta($post_id, 'mptbm_maximum_bag', true);
		$passenger = (int) get_post_meta($post_id, 'mptbm_maximum_passenger', true);
		if ($bag > 0) $mptbm_bags[] = $bag;
		if ($passenger > 0) $mptbm_passengers[] = $passenger;
	}
	$max_bag = !empty($mptbm_bags) ? max($mptbm_bags) : 1;
	$max_passenger = !empty($mptbm_passengers) ? max($mptbm_passengers) : 1;
	
?>	
	<div class="<?php echo esc_attr($area_class); ?> ">
	
		<div class="_dLayout mptbm_search_area <?php echo esc_attr($form_style_class); ?> <?php echo esc_attr($price_based == 'manual' ? 'mAuto' : ''); ?>">
			<div class="mpForm">
				<input type="hidden" id="mptbm_km_or_mile" name="mptbm_km_or_mile" value="<?php echo esc_attr($km_or_mile); ?>" />
				<input type="hidden" name="mptbm_price_based" value="<?php echo esc_attr($price_based); ?>" />
				<input type="hidden" name="mptbm_post_id" value="" />
				<input type='hidden' id="mptbm_enable_view_search_result_page" name="mptbm_enable_view_search_result_page" value="<?php echo MP_Global_Function::get_settings('mptbm_general_settings', 'enable_view_search_result_page') ?>" />
				<input type='hidden' id="mptbm_enable_return_in_different_date" name="mptbm_enable_return_in_different_date" value="<?php echo MP_Global_Function::get_settings('mptbm_general_settings', 'enable_return_in_different_date') ?>" />
				<input type='hidden' id="mptbm_enable_filter_via_features" name="mptbm_enable_filter_via_features" value="<?php echo MP_Global_Function::get_settings('mptbm_general_settings', 'enable_filter_via_features') ?>" />
				<input type='hidden' id="mptbm_buffer_end_minutes" name="mptbm_buffer_end_minutes" value="<?php echo $buffer_end_minutes; ?>" />
				<input type='hidden' id="mptbm_first_calendar_date" name="mptbm_first_calendar_date" value="<?php echo $all_dates[0]; ?>" />
				<input type='hidden' id="mptbm_country" name="mptbm_country" value="<?php echo $country; ?>" />
				<input type='hidden' id="mptbm_restrict_search_country" name="mptbm_restrict_search_country" value="<?php echo $restrict_search_country; ?>" />
				<div class="inputList">
					<label class="fdColumn">
						<input type="hidden" id="mptbm_map_start_date" value="" />
						<span><?php echo mptbm_get_translation('pickup_date_label', __('Pickup Date', 'ecab-taxi-booking-manager')); ?></span>
						<input type="text" id="mptbm_start_date" class="formControl" placeholder="<?php echo mptbm_get_translation('select_date_label', __('Select Date', 'ecab-taxi-booking-manager')); ?>" value="" readonly />
						<span class="far fa-calendar-alt mptbm_left_icon allCenter"></span>
					</label>
				</div>

				<div class="inputList mp_input_select">
					<input type="hidden" id="mptbm_map_start_time" value="" />
					<label class="fdColumn">
						<span><?php echo mptbm_get_translation('pickup_time_label', __('Pickup Time', 'ecab-taxi-booking-manager')); ?></span>
						<input type="text" id="mptbm_start_time" class="formControl" placeholder="<?php echo mptbm_get_translation('please_select_time_label', __('Please Select Time', 'ecab-taxi-booking-manager')); ?>" value="" readonly />
						<span class="far fa-clock mptbm_left_icon allCenter"></span>
					</label>

					<ul class="mp_input_select_list start_time_list">
						<?php
						for ($i = $min_minutes; $i <= $max_minutes; $i += $interval_time) {

							// Calculate hours and minutes
							$hours = floor($i / 60);
							$minutes = $i % 60;

							// Generate the data-value as hours + fraction (minutes / 100)
							$data_value = $hours + ($minutes / 100);

							// Format the time for display
							$time_formatted = sprintf('%02d:%02d', $hours, $minutes);
							
							// Add a data-time attribute with the properly formatted time
							$data_time = sprintf('%02d.%02d', $hours, $minutes);
							
							// Ensure the data-value is properly formatted
							$data_value = sprintf('%.2f', $data_value);
						?>
							<li data-value="<?php echo esc_attr($data_value); ?>" data-time="<?php echo esc_attr($data_time); ?>"><?php echo esc_html(MP_Global_Function::date_format($time_formatted, 'time')); ?></li>
						<?php } ?>

					</ul>
					<ul class="start_time_list-no-dsiplay" style="display:none">
						<?php

						for ($i = $min_minutes; $i <= $max_minutes; $i += $interval_time) {

							// Calculate hours and minutes
							$hours = floor($i / 60);
							$minutes = $i % 60;

							// Generate the data-value as hours + fraction (minutes / 100)
							$data_value = $hours + ($minutes / 100);

							// Format the time for display
							$time_formatted = sprintf('%02d:%02d', $hours, $minutes);
							
							// Add a data-time attribute with the properly formatted time
							$data_time = sprintf('%02d.%02d', $hours, $minutes);
							
							// Ensure the data-value is properly formatted
							$data_value = sprintf('%.2f', $data_value);

						?>
							<li data-value="<?php echo esc_attr($data_value); ?>" data-time="<?php echo esc_attr($data_time); ?>"><?php echo esc_html(MP_Global_Function::date_format($time_formatted, 'time')); ?></li>
						<?php } ?>

					</ul>

				</div>
				<div class="inputList">
					<label class="fdColumn ">
						<span><?php echo mptbm_get_translation('pickup_location_label', __('Pickup Location', 'ecab-taxi-booking-manager')); ?></span>
						<?php if ($price_based == 'manual') {
						?>
							<?php $all_start_locations = MPTBM_Function::get_all_start_location(); ?>
							<select id="mptbm_manual_start_place" class="mptbm_manual_start_place formControl">
								<option selected disabled><?php echo mptbm_get_translation('select_pick_up_location_label', __(' Select Pick-Up Location', 'ecab-taxi-booking-manager')); ?></option>
								<?php if (sizeof($all_start_locations) > 0) { ?>
									<?php foreach ($all_start_locations as $start_location) { ?>
										<option class="textCapitalize" value="<?php echo esc_attr($start_location); ?>"><?php echo esc_html(MPTBM_Function::get_taxonomy_name_by_slug($start_location, 'locations')); ?></option>
									<?php } ?>
								<?php } ?>
							</select>
						<?php } else { ?>
							<input type="text" id="mptbm_map_start_place" class="formControl" placeholder="<?php echo mptbm_get_translation('enter_pick_up_location_label', __('Enter Pick-Up Location', 'ecab-taxi-booking-manager')); ?>" value="" />
							
						<?php } ?>
						<i class="fas fa-map-marker-alt mptbm_left_icon allCenter"></i>
					</label>
				</div>
				<div class="inputList">
					<label class="fdColumn mptbm_manual_end_place">
						<span><?php echo mptbm_get_translation('drop_off_location_label', __('Drop-Off Location', 'ecab-taxi-booking-manager')); ?></span>
						<?php if ($price_based == 'manual') { ?>
							<select class="formControl mptbm_map_end_place" id="mptbm_manual_end_place">
								<option class="textCapitalize" selected disabled><?php echo mptbm_get_translation('select_destination_location_label', __(' Select Destination Location', 'ecab-taxi-booking-manager')); ?></option>
							</select>
						<?php } else { ?>
							<input class="formControl textCapitalize" type="text" id="mptbm_map_end_place" class="formControl" placeholder="<?php echo mptbm_get_translation('enter_drop_off_location_label', __(' Enter Drop-Off Location', 'ecab-taxi-booking-manager')); ?>" value="" />
						<?php } ?>
						<i class="fas fa-map-marker-alt mptbm_left_icon allCenter"></i>
					</label>
				</div>
				<?php
				if (MP_Global_Function::get_settings('mptbm_general_settings', 'enable_view_find_location_page')) {
				?>
					<a href="<?php echo MP_Global_Function::get_settings('mptbm_general_settings', 'enable_view_find_location_page') ?>" class="mptbm_find_location_btn"><?php echo mptbm_get_translation('click_here_label', __('Click here', 'ecab-taxi-booking-manager')); ?></a>
					<?php echo mptbm_get_translation('if_you_are_not_able_to_find_your_desired_location_label', __('If you are not able to find your desired location', 'ecab-taxi-booking-manager')); ?>
				<?php
				}
				?>			
				<?php
				if (MP_Global_Function::get_settings('mptbm_general_settings', 'enable_view_find_location_page')) {
				?>
					<a href="<?php echo MP_Global_Function::get_settings('mptbm_general_settings', 'enable_view_find_location_page') ?>" class="mptbm_find_location_btn"><?php echo mptbm_get_translation('click_here_label', __('Click here', 'ecab-taxi-booking-manager')); ?></a>
					<?php echo mptbm_get_translation('if_you_are_not_able_to_find_your_desired_location_label', __('If you are not able to find your desired location', 'ecab-taxi-booking-manager')); ?>
				<?php
				}
				?>
				<?php if ($pro_active && $enable_max_passenger_filter === 'yes'): ?>
				<div class="inputList mp_input_select">
					<label class="fdColumn">
						<span><?php esc_html_e('Maximum Passenger', 'ecab-taxi-booking-manager'); ?></span>
						<select id="mptbm_max_passenger" class="formControl" name="mptbm_max_passenger">
							<?php for ($i = 1; $i <= $max_passenger; $i++) { ?>
								<option value="<?php echo esc_attr($i); ?>"><?php echo esc_html($i); ?></option>
							<?php } ?>
						</select>
						<span class="fas fa-users mptbm_left_icon allCenter"></span>
					</label>
				</div>
				<?php endif; ?>
				<?php if ($pro_active && $enable_max_bag_filter === 'yes'): ?>
				<div class="inputList mp_input_select">
					<label class="fdColumn">
						<span><?php esc_html_e('Maximum Bag', 'ecab-taxi-booking-manager'); ?></span>
						<select id="mptbm_max_bag" class="formControl" name="mptbm_max_bag">
							<?php for ($i = 0; $i <= $max_bag; $i++) { ?>
								<option value="<?php echo esc_attr($i); ?>"><?php echo esc_html($i); ?></option>
							<?php } ?>
						</select>
						<span class="fa fa-shopping-bag mptbm_left_icon allCenter"></span>
					</label>
				</div>
				<?php endif; ?>
			
				<?php
				if (MP_Global_Function::get_settings('mptbm_general_settings', 'enable_view_find_location_page')) {
				?>
					<a href="<?php echo MP_Global_Function::get_settings('mptbm_general_settings', 'enable_view_find_location_page') ?>" class="mptbm_find_location_btn"><?php esc_html_e('Click here', 'ecab-taxi-booking-manager'); ?></a>
					<?php esc_html_e('If you are not able to find your desired location', 'ecab-taxi-booking-manager'); ?>
				<?php
				}
				?>
			</div>
			<div class="mpForm">
				<?php if ($taxi_return == 'enable' && $price_based != 'fixed_hourly') { ?>
					<div class="inputList">
						<label class="fdColumn">
							<span><?php echo mptbm_get_translation('transfer_type_label', __('Transfer Type', 'ecab-taxi-booking-manager')); ?></span>
							<select class="formControl" name="mptbm_taxi_return" id="mptbm_taxi_return" data-collapse-target>
								<option value="1" selected><?php echo mptbm_get_translation('one_way_label', __('One Way', 'ecab-taxi-booking-manager')); ?></option>
								<option data-option-target="#different_date_return" value="2"><?php echo mptbm_get_translation('return_label', __('Return', 'ecab-taxi-booking-manager')); ?></option>
							</select>
							<i class="fas fa-exchange-alt mptbm_left_icon allCenter"></i>
						</label>
					</div>
					<?php
					if (MP_Global_Function::get_settings('mptbm_general_settings', 'enable_return_in_different_date') == 'yes') {
					?>
						<div class="inputList" data-collapse="#different_date_return">
							
							<label class="fdColumn">
								<input type="hidden" id="mptbm_map_return_date" value="" />
								<span><?php echo mptbm_get_translation('return_date_label', __('Return Date', 'ecab-taxi-booking-manager')); ?></span>
								<input type="text" id="mptbm_return_date" class="formControl" placeholder="<?php echo mptbm_get_translation('select_date_label', __('Select Date', 'ecab-taxi-booking-manager')); ?>" value="" readonly />
								<span class="far fa-calendar-alt mptbm_left_icon allCenter"></span>
							</label>
						</div>
						<div class="inputList mp_input_select" data-collapse="#different_date_return">
						<input type="hidden" id="mptbm_map_return_time" value="" />
	<label class="fdColumn">
		<span><?php echo mptbm_get_translation('return_time_label', __('Return Time', 'ecab-taxi-booking-manager')); ?></span>
		<input type="text" id="mptbm_return_time" class="formControl" placeholder="<?php echo mptbm_get_translation('please_select_time_label', __('Please Select Time', 'ecab-taxi-booking-manager')); ?>" value="" readonly />
		<span class="far fa-clock mptbm_left_icon allCenter"></span>
	</label>

	<ul class="mp_input_select_list return_time_list">
		<?php
		for ($i = $min_minutes; $i <= $max_minutes; $i += $interval_time) {

			// Calculate hours and minutes
			$hours = floor($i / 60);
			$minutes = $i % 60;

			// Generate the data-value as hours + fraction (minutes / 100)
			$data_value = $hours + ($minutes / 100);

			// Format the time for display
			$time_formatted = sprintf('%02d:%02d', $hours, $minutes);

			// Add a data-time attribute with the properly formatted time
			$data_time = sprintf('%02d.%02d', $hours, $minutes);

			// Ensure the data-value is properly formatted
			$data_value = sprintf('%.2f', $data_value);
		?>
			<li data-value="<?php echo esc_attr($data_value); ?>" data-time="<?php echo esc_attr($data_time); ?>"><?php echo esc_html(MP_Global_Function::date_format($time_formatted, 'time')); ?></li>
		<?php } ?>
	</ul>

	<ul class="return_time_list-no-dsiplay" style="display:none">
		<?php
		for ($i = $min_minutes; $i <= $max_minutes; $i += $interval_time) {

			// Calculate hours and minutes
			$hours = floor($i / 60);
			$minutes = $i % 60;

			// Generate the data-value as hours + fraction (minutes / 100)
			$data_value = $hours + ($minutes / 100);

			// Format the time for display
			$time_formatted = sprintf('%02d:%02d', $hours, $minutes);

			// Add a data-time attribute with the properly formatted time
			$data_time = sprintf('%02d.%02d', $hours, $minutes);

			// Ensure the data-value is properly formatted
			$data_value = sprintf('%.2f', $data_value);
		?>
			<li data-value="<?php echo esc_attr($data_value); ?>" data-time="<?php echo esc_attr($data_time); ?>"><?php echo esc_html(MP_Global_Function::date_format($time_formatted, 'time')); ?></li>
		<?php } ?>
	</ul>
						</div>
					<?php
					}
					?>


				<?php } ?>
				<?php if ($waiting_time_check == 'enable' && $price_based != 'fixed_hourly') { ?>
					<div class="inputList mp_input_select">
						<label class="fdColumn">
							<span><?php echo mptbm_get_translation('extra_waiting_hours_label', __('Extra Waiting Hours', 'ecab-taxi-booking-manager')); ?></span>
							<select class="formControl" name="mptbm_waiting_time" id="mptbm_waiting_time">
								<option value="0" selected><?php echo mptbm_get_translation('no_waiting_label', __('No Waiting', 'ecab-taxi-booking-manager')); ?></option>
								<option value="1"><?php echo mptbm_get_translation('one_hour_label', __('1 Hour', 'ecab-taxi-booking-manager')); ?></option>
								<option value="2"><?php echo mptbm_get_translation('two_hours_label', __('2 Hours', 'ecab-taxi-booking-manager')); ?></option>
								<option value="3"><?php echo mptbm_get_translation('three_hours_label', __('3 Hours', 'ecab-taxi-booking-manager')); ?></option>
								<option value="4"><?php echo mptbm_get_translation('four_hours_label', __('4 Hours', 'ecab-taxi-booking-manager')); ?></option>
								<option value="5"><?php echo mptbm_get_translation('five_hours_label', __('5 Hours', 'ecab-taxi-booking-manager')); ?></option>
								<option value="6"><?php echo mptbm_get_translation('six_hours_label', __('6 Hours', 'ecab-taxi-booking-manager')); ?></option>
							</select>
							<i class="far fa-clock mptbm_left_icon allCenter"></i>
						</label>
					</div>
				<?php } ?>
				<?php if ($price_based == 'fixed_hourly') { ?>
					<div class="inputList">
						<label class="fdColumn">
							<span><?php echo mptbm_get_translation('select_hours_label', __('Select Hours', 'ecab-taxi-booking-manager')); ?></span>
							<select class="formControl" name="mptbm_fixed_hours" id="mptbm_fixed_hours">
								<option value="1" selected><?php echo mptbm_get_translation('one_hour_label', __('1 Hour', 'ecab-taxi-booking-manager')); ?></option>
								<option value="2"><?php echo mptbm_get_translation('two_hours_label', __('2 Hours', 'ecab-taxi-booking-manager')); ?></option>
								<option value="3"><?php echo mptbm_get_translation('three_hours_label', __('3 Hours', 'ecab-taxi-booking-manager')); ?></option>
								<option value="4"><?php echo mptbm_get_translation('four_hours_label', __('4 Hours', 'ecab-taxi-booking-manager')); ?></option>
								<option value="5"><?php echo mptbm_get_translation('five_hours_label', __('5 Hours', 'ecab-taxi-booking-manager')); ?></option>
								<option value="6"><?php echo mptbm_get_translation('six_hours_label', __('6 Hours', 'ecab-taxi-booking-manager')); ?></option>
								<option value="7"><?php echo mptbm_get_translation('seven_hours_label', __('7 Hours', 'ecab-taxi-booking-manager')); ?></option>
								<option value="8"><?php echo mptbm_get_translation('eight_hours_label', __('8 Hours', 'ecab-taxi-booking-manager')); ?></option>
								<option value="9"><?php echo mptbm_get_translation('nine_hours_label', __('9 Hours', 'ecab-taxi-booking-manager')); ?></option>
								<option value="10"><?php echo mptbm_get_translation('ten_hours_label', __('10 Hours', 'ecab-taxi-booking-manager')); ?></option>
								<option value="11"><?php echo mptbm_get_translation('eleven_hours_label', __('11 Hours', 'ecab-taxi-booking-manager')); ?></option>
								<option value="12"><?php echo mptbm_get_translation('twelve_hours_label', __('12 Hours', 'ecab-taxi-booking-manager')); ?></option>
							</select>
							<i class="far fa-clock mptbm_left_icon allCenter"></i>
						</label>
					</div>
				<?php } ?>
				<?php 
				$show_passengers = MP_Global_Function::get_settings('mptbm_general_settings', 'show_number_of_passengers', 'no');
				if ($show_passengers === 'jumpa') { 
				?>
				<div class="inputList">
					<label class="fdColumn">
						<span><?php echo mptbm_get_translation('number_of_passengers_label', __('Number of Passengers', 'ecab-taxi-booking-manager')); ?></span>
						<input type="number" class="formControl" name="mptbm_passengers" id="mptbm_passengers" min="1" value="1" />
						<i class="fas fa-users mptbm_left_icon allCenter" style="position: absolute; left: 87%;"></i>
					</label>
				</div>
				<?php } ?>
				<?php if ($form_style == 'horizontal') { ?>
					<div class="divider"></div>
				<?php } ?>
				<div class="inputList justifyBetween _fdColumn">
					<span>&nbsp;</span>
					<button type="button" class="_themeButton_fullWidth" id="mptbm_get_vehicle">
						<span class="fas fa-search-location mR_xs"></span>
						<?php echo mptbm_get_translation('search_label', __('Search', 'ecab-taxi-booking-manager')); ?>
					</button>
				</div>
				<?php if ($form_style != 'horizontal') { ?>
					<?php if ($taxi_return != 'enable' && $price_based != 'fixed_hourly') { ?>
						<div class="inputList"></div>
					<?php } ?>
					<?php if ($waiting_time_check != 'enable' && $price_based != 'fixed_hourly') { ?>
						<div class="inputList"></div>
					<?php } ?>
					<?php if ($price_based == 'fixed_hourly') { ?>
						<div class="inputList"></div>
					<?php } ?>
					<div class="inputList"></div>
				<?php } ?>
			</div>
		</div>
		<?php $map_key = get_option('mptbm_map_api_settings',true);?>
		<span class="mptbm-map-warning" style="display:none"><?php _e('Google Map Authentication Failed! Please contact site admin.','ecab-taxi-booking-manager'); ?></span>
		<div class="mptbm_map_area fdColumn" style="display: <?php echo ($price_based != 'manual' && $map === 'yes') ? 'block' : 'none'; ?>;">
			<div class="fullHeight">
				<?php if(!empty($map_key['gmap_api_key'])): ?>
					<div id="mptbm_map_area"></div>
				<?php else: ?>
					<div class="mptbm-map-warning"><h6>
						<?php _e('Google Map not working! Please contact site admin.','ecab-taxi-booking-manager'); ?></h6>
					</div>
				<?php endif; ?>
			</div>
			<div class="_dLayout mptbm_distance_time">
				<div class="_equalChild_separatorRight">
					<div class="_dFlex_pR_xs">
						<h1 class="_mR">
							<span class="fas fa-route textTheme"></span>
						</h1>
						<div class="fdColumn">
							<h6><?php echo mptbm_get_translation('total_distance_label', __('TOTAL DISTANCE', 'ecab-taxi-booking-manager')); ?></h6>
							<?php if ($km_or_mile != 'km') { ?>
								<strong class="mptbm_total_distance"><?php echo mptbm_get_translation('zero_mile_label', __(' 0 MILE', 'ecab-taxi-booking-manager')); ?></strong>
							<?php } else { ?>
								<strong class="mptbm_total_distance"><?php echo mptbm_get_translation('zero_km_label', __(' 0 KM', 'ecab-taxi-booking-manager')); ?></strong>
							<?php } ?>
						</div>
					</div>
					<div class="dFlex">
						<h1 class="_mLR">
							<span class="fas fa-clock textTheme"></span>
						</h1>
						<div class="fdColumn">
							<h6><?php echo mptbm_get_translation('total_time_label', __('TOTAL TIME', 'ecab-taxi-booking-manager')); ?></h6>
							<strong class="mptbm_total_time"><?php echo mptbm_get_translation('zero_hour_label', __('0 Hour', 'ecab-taxi-booking-manager')); ?></strong>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="_fullWidth get_details_next_link">
		<div class="divider"></div>
		<div class="justifyBetween">
			<button type="button" class="mpBtn nextTab_prev">
				<span>&larr; &nbsp;<?php echo mptbm_get_translation('previous_label', __('Previous', 'ecab-taxi-booking-manager')); ?></span>
			</button>
			<div></div>
			<button type="button" class="_themeButton_min_200 nextTab_next">
				<span><?php echo mptbm_get_translation('next_label', __('Next', 'ecab-taxi-booking-manager')); ?>&nbsp; &rarr;</span>
			</button>
		</div>
	</div>
	<?php do_action('mp_load_date_picker_js', '#mptbm_start_date', $all_dates); ?>
	<?php do_action('mp_load_date_picker_js', '#mptbm_return_date', $all_dates); ?>
<?php } else { ?>
	<div class="dLayout">
		<h3 class="_textDanger_textCenter">

			<?php
			$transportaion_label = MPTBM_Function::get_name();

			// Translators comment to explain the placeholder
			/* translators: %s: transportation label */
			$translated_string = __("No %s configured for this price setting", 'ecab-taxi-booking-manager');

			$formatted_string = sprintf($translated_string, $transportaion_label);
			echo esc_html($formatted_string);
			?>
		</h3>
	</div>
<?php
}
