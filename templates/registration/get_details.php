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
$map_type = MP_Global_Function::get_settings('mptbm_map_api_settings', 'display_map', 'openstreetmap');

set_transient('original_price_based', $price_based);

$map = $map ?? 'yes';
$map = strtolower($map); // Normalize the value to lowercase

$all_dates = MPTBM_Function::get_all_dates($price_based);
$form_style = $form_style ?? 'horizontal';
$disable_dropoff_hourly = MP_Global_Function::get_settings('mptbm_general_settings', 'disable_dropoff_hourly', 'enable');
if ($price_based === 'fixed_hourly' && $disable_dropoff_hourly === 'disable') {
    $form_style = 'inline';
    $map = 'no';
    $hide_dropoff = true;
} else {
    $hide_dropoff = false;
}
$form_style_class = $form_style == 'horizontal' ? 'inputHorizontal' : 'inputInline';
$area_class = $price_based == 'manual' ? ' ' : 'justifyBetween';
$area_class = $form_style != 'horizontal' ? 'mptbm_form_details_area fdColumn' : $area_class;
$mptbm_all_transport_id = MP_Global_Function::get_all_post_id('mptbm_rent');
$mptbm_available_for_all_time = false;
$mptbm_schedule = [];
$min_schedule_value = 0;
$max_schedule_value = 24;
$loop = 1;
$day_specific_times = [];

foreach ($mptbm_all_transport_id as $key => $value) {
	if (MP_Global_Function::get_post_info($value, 'mptbm_available_for_all_time') == 'on') {
		$mptbm_available_for_all_time = true;
	}
}

if ($mptbm_available_for_all_time == false) {
	$all_schedules = [];
	$day_specific_times = [];
	
	foreach ($mptbm_all_transport_id as $key => $value) {
		$transport_schedule = MPTBM_Function::get_schedule($value);
		if (!empty($transport_schedule)) {
			$all_schedules[] = $transport_schedule;
			
			// Debug: Log each transport's schedule
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log("Transport {$value} schedule: " . print_r($transport_schedule, true));
			}
		}
	}
	
	// Process all schedules to find min/max times for each day
	foreach ($all_schedules as $schedule) {
		foreach ($schedule as $day => $times) {
			if (is_array($times) && count($times) >= 2) {
				$start_time = floatval($times[0]);
				$end_time = floatval($times[1]);
				
				// Store times for each day
				if (!isset($day_specific_times[$day])) {
					$day_specific_times[$day] = ['start' => [], 'end' => []];
				}
				$day_specific_times[$day]['start'][] = $start_time;
				$day_specific_times[$day]['end'][] = $end_time;
				
				// Debug: Log schedule data
				if (defined('WP_DEBUG') && WP_DEBUG) {
					error_log("Schedule for {$day}: start={$start_time}, end={$end_time}");
				}
			}
		}
	}
	
	// Debug: Log final day specific times
	if (defined('WP_DEBUG') && WP_DEBUG) {
		error_log("Final day specific times: " . print_r($day_specific_times, true));
	}
	
	// Debug: Log Wednesday specifically
	if (isset($day_specific_times['wednesday'])) {
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log("Wednesday times - Start: " . print_r($day_specific_times['wednesday']['start'], true));
			error_log("Wednesday times - End: " . print_r($day_specific_times['wednesday']['end'], true));
		}
	}
	
	// Calculate global min/max from all available times
	$all_start_times = [];
	$all_end_times = [];
	foreach ($day_specific_times as $day => $times) {
		$all_start_times = array_merge($all_start_times, $times['start']);
		$all_end_times = array_merge($all_end_times, $times['end']);
	}
	
	if (!empty($all_start_times) && !empty($all_end_times)) {
		$min_schedule_value = min($all_start_times);
		$max_schedule_value = max($all_end_times);
	} else {
		// If no schedules found, set default values
		$min_schedule_value = 0.5; // 30 minutes
		$max_schedule_value = 24;   // 24 hours
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

// Use our custom buffer time function instead of the main plugin's buffer time
if (function_exists('ecab_get_buffer_time')) {
    $buffer_time = ecab_get_buffer_time();
} else {
    // Fallback to main plugin's buffer time if our function doesn't exist
    $buffer_time = (int) MP_Global_Function::get_settings('mptbm_general_settings', 'enable_buffer_time');
}

$current_time = time();
$current_hour = wp_date('H', $current_time);
$current_minute = wp_date('i', $current_time);

// Convert to total minutes since midnight local time
$current_minutes = intval($current_hour) * 60 + intval($current_minute);

// Calculate buffer end time in minutes since midnight
$buffer_end_minutes = $current_minutes + $buffer_time;

// Ensure buffer_end_minutes is not negative
$buffer_end_minutes = max($buffer_end_minutes, 0);

// Calculate how many full days the buffer covers
$days_to_hide = floor($buffer_end_minutes / 1440); // 1440 = minutes per day

// If buffer goes beyond one or more full days
if ($days_to_hide > 0 && !empty($all_dates)) {
    // Remove as many days as the buffer covers (today + next days)
    for ($i = 0; $i < $days_to_hide && !empty($all_dates); $i++) {
        array_shift($all_dates);
    }

    // Adjust remaining buffer minutes for the last day
    $buffer_end_minutes = $buffer_end_minutes % 1440;
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
	
	$disable_dropoff_hourly = MP_Global_Function::get_settings('mptbm_general_settings', 'disable_dropoff_hourly', 'enable');
	if ($price_based === 'fixed_hourly' && $disable_dropoff_hourly === 'disable') {
	    $form_style = 'inline';
	    $map = 'no';
	    $hide_dropoff = true;
	} else {
	    $hide_dropoff = false;
	}
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
				<input type='hidden' id="mptbm_map_type" name="mptbm_map_type" value="<?php echo esc_attr($map_type); ?>" />
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
				<?php if (!($hide_dropoff && $price_based === 'fixed_hourly')): ?>
<div class="inputList">
    <label class="fdColumn mptbm_manual_end_place">
        <span><?php echo mptbm_get_translation('dropoff_location_label', __('Drop-Off Location', 'ecab-taxi-booking-manager')); ?></span>
        <?php if ($price_based == 'manual') { ?>
            <select class="formControl mptbm_map_end_place" id="mptbm_manual_end_place">
                <option class="textCapitalize" selected disabled><?php echo mptbm_get_translation('select_destination_location_label', __(' Select Destination Location', 'ecab-taxi-booking-manager')); ?></option>
            </select>
        <?php } else { ?>
            <input type="text" id="mptbm_map_end_place" class="formControl textCapitalize" placeholder="<?php echo mptbm_get_translation('enter_dropoff_location_placeholder', __(' Enter Drop-Off Location', 'ecab-taxi-booking-manager')); ?>" value="" />
        <?php } ?>
        <i class="fas fa-map-marker-alt mptbm_left_icon allCenter"></i>
    </label>
</div>
<?php else: ?>
<input type="hidden" id="mptbm_map_end_place" />
<?php endif; ?>
<input type="hidden" name="mptbm_original_price_base" value="<?php echo esc_attr($price_based); ?>" />
<?php if ($hide_dropoff && $price_based === 'fixed_hourly') : ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var pickup = document.getElementById('mptbm_map_start_place');
    var dropoff = document.getElementById('mptbm_map_end_place');
    if (pickup && dropoff) {
        function syncDropoff() {
            dropoff.value = pickup.value;
        }
        pickup.addEventListener('input', syncDropoff);
        syncDropoff();
    }
});
</script>
<?php endif; ?>
				<?php if ($pro_active && $enable_max_passenger_filter === 'yes'): ?>
				<div class="inputList mp_input_select">
					<label class="fdColumn">
						<span><?php echo mptbm_get_translation('max_passenger_label', __('Maximum Passenger', 'ecab-taxi-booking-manager')); ?></span>
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
						<span> <?php echo mptbm_get_translation('max_bag_label', __('Maximum Bag', 'ecab-taxi-booking-manager')); ?> </span>
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
				$location_page_url = MPTBM_Function::get_page_url_from_slug(MP_Global_Function::get_settings('mptbm_general_settings', 'enable_view_find_location_page'));
				if ($location_page_url) {
				?>
					<a href="<?php echo esc_url($location_page_url); ?>" class="mptbm_find_location_btn"><?php echo mptbm_get_translation('click_here_label', __('Click here', 'ecab-taxi-booking-manager')); ?></a>
					<?php echo mptbm_get_translation('if_you_are_not_able_to_find_your_desired_location_label', __('If you are not able to find your desired location', 'ecab-taxi-booking-manager')); ?>
				<?php
				}
				?>
			</div>
			<div class="mpForm">
				<?php 
				$transfer_type_setting = MP_Global_Function::get_settings('mptbm_general_settings', 'transfer_type', 'enable');
				if ($taxi_return == 'enable' && $price_based != 'fixed_hourly' && $transfer_type_setting !== 'disable') { ?>
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
								<option value="1">1 <?php echo mptbm_get_translation('hours_in_waiting_label', __('Hour', 'ecab-taxi-booking-manager')); ?></option>
								<option value="2">2 <?php echo mptbm_get_translation('hours_in_waiting_label', __('Hours', 'ecab-taxi-booking-manager')); ?></option>
								<option value="3">3 <?php echo mptbm_get_translation('hours_in_waiting_label', __('Hours', 'ecab-taxi-booking-manager')); ?></option>
								<option value="4">4 <?php echo mptbm_get_translation('hours_in_waiting_label', __('Hours', 'ecab-taxi-booking-manager')); ?></option>
								<option value="5">5 <?php echo mptbm_get_translation('hours_in_waiting_label', __('Hours', 'ecab-taxi-booking-manager')); ?></option>
								<option value="6">6 <?php echo mptbm_get_translation('hours_in_waiting_label', __('Hours', 'ecab-taxi-booking-manager')); ?></option>
							</select>
							<i class="far fa-clock mptbm_left_icon allCenter"></i>
						</label>
					</div>
				<?php } ?>
				<?php if ($price_based == 'fixed_hourly') { 
					$minimum_booking_hours = MP_Global_Function::get_settings('mptbm_general_settings', 'minimum_booking_hours', '0');
					$minimum_booking_hours = intval($minimum_booking_hours);
					$max_hours = 12; // Maximum hours to show in dropdown
					// If setting is 0 (disabled), start from 1 hour
					$start_hours = ($minimum_booking_hours == 0) ? 1 : $minimum_booking_hours;
				?>
					<div class="inputList">
						<label class="fdColumn">
							<span><?php echo mptbm_get_translation('select_hours_label', __('Select Hours', 'ecab-taxi-booking-manager')); ?></span>
							<select class="formControl" name="mptbm_fixed_hours" id="mptbm_fixed_hours">
								<?php for ($i = $start_hours; $i <= $max_hours; $i++) { 
									$selected = ($i == $start_hours) ? 'selected' : '';
									$hour_label = '';
									switch ($i) {
										case 1: $hour_label = mptbm_get_translation('one_hour_label', __('1 Hour', 'ecab-taxi-booking-manager')); break;
										case 2: $hour_label = mptbm_get_translation('two_hours_label', __('2 Hours', 'ecab-taxi-booking-manager')); break;
										case 3: $hour_label = mptbm_get_translation('three_hours_label', __('3 Hours', 'ecab-taxi-booking-manager')); break;
										case 4: $hour_label = mptbm_get_translation('four_hours_label', __('4 Hours', 'ecab-taxi-booking-manager')); break;
										case 5: $hour_label = mptbm_get_translation('five_hours_label', __('5 Hours', 'ecab-taxi-booking-manager')); break;
										case 6: $hour_label = mptbm_get_translation('six_hours_label', __('6 Hours', 'ecab-taxi-booking-manager')); break;
										case 7: $hour_label = mptbm_get_translation('seven_hours_label', __('7 Hours', 'ecab-taxi-booking-manager')); break;
										case 8: $hour_label = mptbm_get_translation('eight_hours_label', __('8 Hours', 'ecab-taxi-booking-manager')); break;
										case 9: $hour_label = mptbm_get_translation('nine_hours_label', __('9 Hours', 'ecab-taxi-booking-manager')); break;
										case 10: $hour_label = mptbm_get_translation('ten_hours_label', __('10 Hours', 'ecab-taxi-booking-manager')); break;
										case 11: $hour_label = mptbm_get_translation('eleven_hours_label', __('11 Hours', 'ecab-taxi-booking-manager')); break;
										case 12: $hour_label = mptbm_get_translation('twelve_hours_label', __('12 Hours', 'ecab-taxi-booking-manager')); break;
										default: $hour_label = sprintf(__('%d Hours', 'ecab-taxi-booking-manager'), $i); break;
									}
								?>
									<option value="<?php echo esc_attr($i); ?>" <?php echo $selected; ?>><?php echo esc_html($hour_label); ?></option>
								<?php } ?>
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
		<?php 
		$map_key = get_option('mptbm_map_api_settings',true);
		$default_latitude = MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_latitude', '40.7128');
		$default_longitude = MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_longitude', '-74.0060');
		?>
		
		<?php if($map_type === 'openstreetmap'): ?>
		<!-- OpenStreetMap CSS -->
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
		<!-- OpenStreetMap JavaScript -->
		<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
		<script>
		// Pass the configured coordinates to JavaScript
		var mptbm_default_lat = <?php echo esc_js($default_latitude); ?>;
		var mptbm_default_lng = <?php echo esc_js($default_longitude); ?>;
		</script>
		<style>
		#mptbm_map_area {
			height: 86% !important;
			width: 100% !important;
			border: 1px solid #ddd;
			border-radius: 4px;
		}
		.mptbm-osm-autocomplete {
			position: absolute;
			top: 100%;
			left: 0;
			right: 0;
			background: white;
			border: 1px solid #ddd;
			border-radius: 4px;
			max-height: 200px;
			overflow-y: auto;
			z-index: 9999;
			display: none;
			box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
			margin-top: 2px;
		}
		.inputList label {
			position: relative;
		}
		</style>
		<?php endif; ?>
		<span class="mptbm-map-warning" style="display:none"><?php _e('Map Authentication Failed! Please contact site admin.','ecab-taxi-booking-manager'); ?></span>
		<div class="mptbm_map_area fdColumn" style="display: <?php echo ($price_based != 'manual' && $map === 'yes' && !($hide_dropoff && $price_based === 'fixed_hourly')) ? 'block' : 'none'; ?>;">
			<div class="fullHeight">
				<?php if($map_type === 'openstreetmap'): ?>
					<div id="mptbm_map_area"></div>
				<?php elseif($map_type === 'enable' && !empty($map_key['gmap_api_key'])): ?>
					<div id="mptbm_map_area"></div>
				<?php elseif($map_type === 'enable' && empty($map_key['gmap_api_key'])): ?>
					<div class="mptbm-map-warning"><h6>
						<?php _e('Google Map API key not configured! Please contact site admin.','ecab-taxi-booking-manager'); ?></h6>
					</div>
				<?php else: ?>
					<div class="mptbm-map-warning"><h6>
						<?php _e('Map functionality is disabled.','ecab-taxi-booking-manager'); ?></h6>
					</div>
				<?php endif; ?>
			</div>
			<div class="_dLayout mptbm_distance_time">
				<div class="_equalChild_separatorRight">
					<div class="_dFlex_pR_xs">
						<h1 class="_mR">
							<span class="mi mi-car-journey textTheme"></span>
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
							<span class="mi mi-clock-three textTheme"></span>
						</h1>
						<div class="fdColumn">
							<div class="fdColumn">
								<h6><?php echo mptbm_get_translation('total_time_label', __('TOTAL TIME', 'ecab-taxi-booking-manager')); ?></h6>
								<strong class="mptbm_total_time"><?php echo mptbm_get_translation('zero_hour_label', __('0 Hour', 'ecab-taxi-booking-manager')); ?></strong>
							</div>
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
	<script>
	// Day-specific time ranges
	var dayTimeRanges = <?php echo wp_json_encode(isset($day_specific_times) ? $day_specific_times : []); ?>;
	
	// Debug: Log the complete day time ranges
	console.log('Complete Day Time Ranges:', dayTimeRanges);
	
	function updateTimeRangeForDay(selectedDate) {
		if (!selectedDate) return;
		
		// Get the day name from the selected date
		var date = new Date(selectedDate);
		var dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
		var dayName = dayNames[date.getDay()];
		
		
		
		// Find the time range for this specific day
		var dayTimes = dayTimeRanges[dayName];
		if (dayTimes && dayTimes.start.length > 0 && dayTimes.end.length > 0) {
			// For each day, find the earliest start time and latest end time
			// This ensures we get the correct range for that specific day
			var minTime = Math.min.apply(Math, dayTimes.start);
			var maxTime = Math.max.apply(Math, dayTimes.end);
			
			console.log('Day-specific range for', dayName, ':', minTime, 'to', maxTime);
			console.log('Available start times for', dayName, ':', dayTimes.start);
			console.log('Available end times for', dayName, ':', dayTimes.end);
			
			// Update the time picker options
			updateTimePickerOptions(minTime, maxTime);
		} else {
			console.log('No specific times for', dayName, ', using global range');
			// Use global range if no specific day times
			updateTimePickerOptions(<?php echo $min_schedule_value; ?>, <?php echo $max_schedule_value; ?>);
		}
	}
	
	function updateTimePickerOptions(minTime, maxTime) {
		// Convert to minutes for easier calculation
		var minMinutes = Math.floor(minTime) * 60 + (minTime % 1) * 100;
		var maxMinutes = Math.floor(maxTime) * 60 + (maxTime % 1) * 100;
		var intervalTime = <?php echo $interval_time; ?>;
		
		// Clear existing options
		jQuery('.start_time_list li').remove();
		jQuery('.return_time_list li').remove();
		
		// Generate new options
		for (var i = minMinutes; i <= maxMinutes; i += intervalTime) {
			var hours = Math.floor(i / 60);
			var minutes = i % 60;
			var dataValue = hours + (minutes / 100);
			var timeFormatted = String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
			var dataTime = String(hours).padStart(2, '0') + '.' + String(minutes).padStart(2, '0');
			
			// Add to start time list
			jQuery('.start_time_list').append('<li data-value="' + dataValue.toFixed(2) + '" data-time="' + dataTime + '">' + timeFormatted + '</li>');
			
			// Add to return time list
			jQuery('.return_time_list').append('<li data-value="' + dataValue.toFixed(2) + '" data-time="' + dataTime + '">' + timeFormatted + '</li>');
		}
		
		
	}
	
	
	
	// Update time range when date is selected
	jQuery(document).ready(function() {
		// Initialize with global range on page load
		updateTimePickerOptions(<?php echo $min_schedule_value; ?>, <?php echo $max_schedule_value; ?>);
		
		jQuery('#mptbm_start_date').on('change', function() {
			updateTimeRangeForDay(jQuery(this).val());
		});
		
		jQuery('#mptbm_return_date').on('change', function() {
			updateTimeRangeForDay(jQuery(this).val());
		});
		
		// Also trigger on date picker selection
		jQuery(document).on('click', '.ui-datepicker-calendar td a', function() {
			setTimeout(function() {
				var selectedDate = jQuery('#mptbm_start_date').val();
				if (selectedDate) {
					updateTimeRangeForDay(selectedDate);
				}
			}, 100);
		});
	});
	</script>
	
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
