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



$restrict_search_country = MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_country_restriction', 'no');

$country = MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_country', 'no');
$km_or_mile = MP_Global_Function::get_settings('mp_global_settings', 'km_or_mile', 'km');
$price_based = $price_based ?? '';
if (!class_exists('MPTBM_Dependencies_Pro') && in_array($price_based, ['fixed_distance', 'fixed_zone', 'fixed_zone_dropoff'])) {
	$price_based = 'dynamic';
}
$map_type = MP_Global_Function::get_settings('mptbm_map_api_settings', 'display_map', 'openstreetmap');

$map = $map ?? 'yes';
$map = strtolower($map); // Normalize the value to lowercase
// Separate from $map above (which is the shortcode/block's own "map" option
// and still only governs the pre-search form state): this is the global
// admin switch (Map API Settings > Show Map on Search Result Page) that
// controls the map specifically on the post-search results view, independent
// of whatever the shortcode's map option was set to.
$show_map_on_result = strtolower(MP_Global_Function::get_settings('mptbm_map_api_settings', 'show_map_on_search_result', 'yes'));

$vehicle_id = isset($vehicle_id) ? absint($vehicle_id) : 0;
$manual_route_map = $vehicle_id ? MP_Global_Function::get_post_info($vehicle_id, 'mptbm_manual_route_map', 'on') : 'on';
$manual_map_enabled = $price_based === 'manual' && $manual_route_map !== 'off' && $map === 'yes' && $map_type !== 'disable';
$manual_map_locations = array();

if ($manual_map_enabled) {
	$manual_vehicle_ids = $vehicle_id ? array($vehicle_id) : MP_Global_Function::get_all_post_id('mptbm_rent');
	$seen_manual_locations = array();

	foreach ($manual_vehicle_ids as $manual_vehicle_id) {
		if (!$vehicle_id && MP_Global_Function::get_post_info($manual_vehicle_id, 'mptbm_price_based', '') !== 'manual') {
			continue;
		}

		$manual_route_rows = array_merge(
			(array) MP_Global_Function::get_post_info($manual_vehicle_id, 'mptbm_manual_price_info', array()),
			(array) MP_Global_Function::get_post_info($manual_vehicle_id, 'mptbm_terms_price_info', array())
		);

		foreach ($manual_route_rows as $manual_route_row) {
			foreach (array('start_location', 'end_location') as $location_field) {
				$location_key = isset($manual_route_row[$location_field]) ? sanitize_text_field((string) $manual_route_row[$location_field]) : '';
				if ($location_key === '' || isset($seen_manual_locations[$location_key])) {
					continue;
				}

				$term = false;
				if (strpos($location_key, 'term_') === 0) {
					$term = get_term(absint(str_replace('term_', '', $location_key)), 'locations');
				} else {
					$term = get_term_by('slug', $location_key, 'locations');
				}

				$label = $term && !is_wp_error($term) ? $term->name : MPTBM_Function::get_taxonomy_name_by_slug($location_key, 'locations');
				$label = $label ?: $location_key;
				$latitude = null;
				$longitude = null;

				if ($term && !is_wp_error($term)) {
					$geo_location = (string) get_term_meta($term->term_id, 'mptbm_geo_location', true);
					$coordinates = array_map('trim', explode(',', $geo_location));
					if (count($coordinates) === 2 && is_numeric($coordinates[0]) && is_numeric($coordinates[1])) {
						$latitude = (float) $coordinates[0];
						$longitude = (float) $coordinates[1];
					}
				}

				$seen_manual_locations[$location_key] = true;
				$manual_map_locations[] = array(
					'key' => $location_key,
					'label' => $label,
					'lat' => $latitude,
					'lng' => $longitude,
				);
			}
		}
	}
}
$all_dates = $vehicle_id
	? MPTBM_Function::get_date($vehicle_id)
	: MPTBM_Function::get_all_dates($price_based);
$pickup = isset($pickup) ? sanitize_text_field($pickup) : '';
$dropoff = isset($dropoff) ? sanitize_text_field($dropoff) : '';
// Accept either a bare term ID ("12") or the internal "term_12" format so the
// shortcode author doesn't need to know the internal option-value format.
$pickup_zone = isset($pickup_zone) ? sanitize_text_field($pickup_zone) : '';
if ($pickup_zone !== '') {
	$pickup_zone_id = absint(str_replace('term_', '', $pickup_zone));
	$pickup_zone = $pickup_zone_id > 0 ? 'term_' . $pickup_zone_id : '';
}
$dropoff_zone = isset($dropoff_zone) ? sanitize_text_field($dropoff_zone) : '';
if ($dropoff_zone !== '') {
	$dropoff_zone_id = absint(str_replace('term_', '', $dropoff_zone));
	$dropoff_zone = $dropoff_zone_id > 0 ? 'term_' . $dropoff_zone_id : '';
}
// Display-only stop names from the shortcode's `stops` attribute - text only,
// never turned into coordinates, so they can never affect distance or price.
$display_stops = isset($display_stops) && is_array($display_stops) ? $display_stops : array();
// Shortcode's `route` attribute - lets a page (e.g. a landing page for one
// specific tour) pre-select a fixed_route by name instead of making the
// visitor pick it from the dropdown themselves.
$route = isset($route) ? sanitize_text_field($route) : '';
$form_style = $form_style ?? 'horizontal';
$disable_dropoff_hourly = MP_Global_Function::get_settings('mptbm_general_settings', 'disable_dropoff_hourly', 'enable');
// hide_dropoff hides the dropoff FIELD; hide_map_area is the separate
// question of whether the map itself shows - fixed_route wants the first
// (a named route already has its own end) but not the second (the map
// previews the selected route's stops).
$hide_map_area = false;
if ($price_based === 'fixed_hourly' && $disable_dropoff_hourly === 'disable') {
    $form_style = 'inline';
    $map = 'no';
    $hide_dropoff = true;
    $hide_map_area = true;
} elseif ($price_based === 'fixed_route') {
    // A predefined named route (e.g. "Paris City Tour") already implies its
    // own start and end - a separate dropoff field would be meaningless.
    // Kept on the default horizontal sidebar+map layout (same as distance/
    // fixed_map) rather than the compact inline flex-row: that row layout
    // was tuned for fixed_hourly's smaller field set and left fixed_route's
    // extra fields (route select, transfer type, waiting time, etc.)
    // uncompensated, producing a ragged, uneven row.
    $hide_dropoff = true;
} else {
    $hide_dropoff = false;
}
$form_style_class = $form_style == 'horizontal' ? 'inputHorizontal' : 'inputInline';
$area_class = $price_based == 'manual' ? ' ' : 'justifyBetween';
$area_class = $form_style != 'horizontal' ? 'mptbm_form_details_area fdColumn' : $area_class;
$mptbm_all_transport_id = $vehicle_id ? array($vehicle_id) : MP_Global_Function::get_all_post_id('mptbm_rent');
// Pickup/return time options are built from the Schedule Date Configuration of the
// vehicles actually in scope: just this one on a single-vehicle page, the whole
// fleet on the global search form. Offering the full 24 hours regardless (what this
// did before) let a customer pick 22:35 from a fleet that only runs 06:00-19:00 and
// get "No Transport Available" with nothing explaining why - the hours were only
// enforced later, at the results stage, in choose_vehicles.php.
//
// The reason it was turned off is real and is handled here: a vehicle set to
// 24-Hour Availability stores no per-day times at all, so it used to contribute
// nothing to the min/max and the picker collapsed to the hours of whichever
// restricted vehicle did store times. Such a vehicle now explicitly contributes the
// full day, so it widens the range instead of being invisible to it.
$mptbm_schedule = [];
$loop = 1;
$day_specific_times = [];

$mptbm_week_days = array_keys(MP_Global_Function::week_day());
$mptbm_day_start = [];   // day => [start floats]
$mptbm_day_end = [];     // day => [end floats]
// The window a 24-hour (or overnight) vehicle contributes. Deliberately the same
// 0.5-24 this form used to hard-code for everyone, so a site where every vehicle is
// 24h keeps precisely the option list it has today and only sites that actually
// configured a schedule see any change at all.
$mptbm_full_day_start = 0.5;
$mptbm_full_day_end = 24.0;

foreach ($mptbm_all_transport_id as $mptbm_schedule_post_id) {
	// Unset means 24h - the same default the toggle, the vehicle editor and
	// wptbm_get_schedule() all use, so a vehicle that never saved the field is not
	// silently treated as having no opening hours.
	$mptbm_all_time = get_post_meta($mptbm_schedule_post_id, 'mptbm_available_for_all_time', true);
	$mptbm_default_start = get_post_meta($mptbm_schedule_post_id, 'mptbm_default_start_time', true);
	$mptbm_default_end = get_post_meta($mptbm_schedule_post_id, 'mptbm_default_end_time', true);

	foreach ($mptbm_week_days as $mptbm_day) {
		if ($mptbm_all_time === '' || $mptbm_all_time === 'on') {
			$mptbm_day_start[$mptbm_day][] = $mptbm_full_day_start;
			$mptbm_day_end[$mptbm_day][] = $mptbm_full_day_end;
			continue;
		}

		$mptbm_day_start_time = get_post_meta($mptbm_schedule_post_id, 'mptbm_' . $mptbm_day . '_start_time', true);
		$mptbm_day_end_time = get_post_meta($mptbm_schedule_post_id, 'mptbm_' . $mptbm_day . '_end_time', true);
		if ($mptbm_day_start_time === '' || $mptbm_day_start_time === 'default') {
			$mptbm_day_start_time = $mptbm_default_start;
		}
		if ($mptbm_day_end_time === '' || $mptbm_day_end_time === 'default') {
			$mptbm_day_end_time = $mptbm_default_end;
		}
		if ($mptbm_day_start_time === '' || $mptbm_day_end_time === '') {
			continue;
		}

		$mptbm_day_start_time = floatval($mptbm_day_start_time);
		$mptbm_day_end_time = floatval($mptbm_day_end_time);
		// An overnight window (22:00-06:00) wraps midnight and cannot be expressed as
		// one linear min..max range - narrowing to 6..22 would hide exactly the hours
		// the vehicle is open. Offer the full day and let choose_vehicles.php, which
		// understands wrapping, do the filtering for this one.
		if ($mptbm_day_start_time > $mptbm_day_end_time) {
			$mptbm_day_start[$mptbm_day][] = $mptbm_full_day_start;
			$mptbm_day_end[$mptbm_day][] = $mptbm_full_day_end;
			continue;
		}
		$mptbm_day_start[$mptbm_day][] = $mptbm_day_start_time;
		$mptbm_day_end[$mptbm_day][] = $mptbm_day_end_time;
	}
}

// Shape the JS in this file's <script> block already expects: per weekday, the
// list of every in-scope vehicle's opening and closing time. It takes min(start)
// and max(end), so the picker spans the union - a time is offered when at least
// one vehicle could serve it, never only when all of them can.
foreach ($mptbm_week_days as $mptbm_day) {
	if (!empty($mptbm_day_start[$mptbm_day]) && !empty($mptbm_day_end[$mptbm_day])) {
		$day_specific_times[$mptbm_day] = array(
			'start' => array_values($mptbm_day_start[$mptbm_day]),
			'end'   => array_values($mptbm_day_end[$mptbm_day]),
		);
	}
}

// Range for the initial paint, before any date (and therefore any weekday) has
// been chosen: the widest window across the whole week. Falls back to the old
// full-day values when no vehicle has a usable schedule, so a site that has never
// configured one behaves exactly as it did.
$mptbm_all_starts = $mptbm_day_start ? array_merge(...array_values($mptbm_day_start)) : [];
$mptbm_all_ends = $mptbm_day_end ? array_merge(...array_values($mptbm_day_end)) : [];
$min_schedule_value = $mptbm_all_starts ? min($mptbm_all_starts) : 0.5;
$max_schedule_value = $mptbm_all_ends ? max($mptbm_all_ends) : 24;
// Ensure the schedule values are numeric
$min_schedule_value = floatval($min_schedule_value);
$max_schedule_value = floatval($max_schedule_value);

if (!function_exists('convertToMinutes')) {
	function convertToMinutes($schedule_value)
	{
		$hours = floor($schedule_value); // Get the hour part
		$minutes = ($schedule_value - $hours) * 100; // Convert decimal part to minutes
		// Rounded: 6.30 is stored as the float 6.3, and (6.3 - 6) * 100 lands on
		// 30.000000000000004, which drags that noise through every loop bound below.
		return (int) round($hours * 60 + $minutes);
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
	$enable_max_hand_luggage_filter = isset($search_filter_settings['enable_max_hand_luggage_filter']) ? $search_filter_settings['enable_max_hand_luggage_filter'] : 'no';

	// Use actual meta keys for dropdowns
	$mptbm_bags = [];
	$mptbm_passengers = [];
	$mptbm_hand_luggage = [];
	$mptbm_all_transport_id = $vehicle_id ? array($vehicle_id) : MP_Global_Function::get_all_post_id('mptbm_rent');
	foreach ($mptbm_all_transport_id as $post_id) {
		$bag = (int) get_post_meta($post_id, 'mptbm_maximum_bag', true);
		$passenger = (int) get_post_meta($post_id, 'mptbm_maximum_passenger', true);
		$hand_luggage = (int) get_post_meta($post_id, 'mptbm_maximum_hand_luggage', true);
		if ($bag > 0) $mptbm_bags[] = $bag;
		if ($passenger > 0) $mptbm_passengers[] = $passenger;
		if ($hand_luggage > 0) $mptbm_hand_luggage[] = $hand_luggage;
	}
	$max_bag = !empty($mptbm_bags) ? max($mptbm_bags) : 1;
	$max_passenger = !empty($mptbm_passengers) ? max($mptbm_passengers) : 1;
	$max_hand_luggage = !empty($mptbm_hand_luggage) ? max($mptbm_hand_luggage) : 1;
	
	$disable_dropoff_hourly = MP_Global_Function::get_settings('mptbm_general_settings', 'disable_dropoff_hourly', 'enable');
	$hide_map_area = false;
	if ($price_based === 'fixed_hourly' && $disable_dropoff_hourly === 'disable') {
	    $form_style = 'inline';
	    $map = 'no';
	    $hide_dropoff = true;
	    $hide_map_area = true;
	} elseif ($price_based === 'fixed_route') {
	    $hide_dropoff = true;
	} else {
	    $hide_dropoff = false;
	}
?>
	<div class="<?php echo esc_attr($area_class); ?> ">
	
		<div class="_dLayout mptbm_search_area <?php echo esc_attr($form_style_class); ?> <?php echo esc_attr(($price_based == 'manual') ? 'mAuto' : ''); ?>">
			<div class="mptbm_search_area_header">
				<span class="fas fa-search mptbm_search_area_header_icon"></span>
				<h3><?php echo mptbm_get_translation('route_planning_label', __('Route Planning', 'ecab-taxi-booking-manager')); ?></h3>
			</div>
			<div class="mpForm">
				<input type="hidden" id="mptbm_km_or_mile" name="mptbm_km_or_mile" value="<?php echo esc_attr($km_or_mile); ?>" />
				<input type="hidden" id="mptbm_use_shortest_route" value="<?php echo esc_attr(MP_Global_Function::get_settings('mptbm_map_api_settings', 'use_shortest_route', 'no')); ?>" />
				<input type="hidden" name="mptbm_price_based" value="<?php echo esc_attr($price_based); ?>" />
				<input type="hidden" name="mptbm_post_id" value="" />
				<input type="hidden" name="mptbm_source_vehicle_id" value="<?php echo esc_attr($vehicle_id ?? 0); ?>" />
				<input type='hidden' id="mptbm_enable_view_search_result_page" name="mptbm_enable_view_search_result_page" value="<?php echo MP_Global_Function::get_settings('mptbm_general_settings', 'enable_view_search_result_page') ?>" />
				<input type='hidden' id="mptbm_enable_return_in_different_date" name="mptbm_enable_return_in_different_date" value="<?php echo MP_Global_Function::get_settings('mptbm_general_settings', 'enable_return_in_different_date') ?>" />
				<input type='hidden' id="mptbm_enable_filter_via_features" name="mptbm_enable_filter_via_features" value="<?php echo MP_Global_Function::get_settings('mptbm_general_settings', 'enable_filter_via_features') ?>" />
				<input type='hidden' id="mptbm_buffer_end_minutes" name="mptbm_buffer_end_minutes" value="<?php echo $buffer_end_minutes; ?>" />
				<input type='hidden' id="mptbm_first_calendar_date" name="mptbm_first_calendar_date" value="<?php echo $all_dates[0]; ?>" />
				<input type='hidden' id="mptbm_country" name="mptbm_country" value="<?php echo $country; ?>" />
				<input type='hidden' id="mptbm_restrict_search_country" name="mptbm_restrict_search_country" value="<?php echo $restrict_search_country; ?>" />
				<input type='hidden' id="mptbm_map_type" name="mptbm_map_type" value="<?php echo esc_attr($map_type); ?>" />
				<input type="hidden" id="mptbm_calculated_distance" name="mptbm_calculated_distance" value="" />
				<input type="hidden" id="mptbm_calculated_duration" name="mptbm_calculated_duration" value="" />
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
						<span><?php echo $price_based == 'fixed_route' ? mptbm_get_translation('route_label', __('Route', 'ecab-taxi-booking-manager')) : mptbm_get_translation('pickup_location_label', __('Pickup Location', 'ecab-taxi-booking-manager')); ?></span>
						<?php
						if (!function_exists('mptbm_resolve_location_label')) {
							function mptbm_resolve_location_label($location_raw) {
								$label = '';
								
								if (strpos($location_raw, 'term_') === 0) {
									$term_id = absint(str_replace('term_', '', $location_raw));
									// Direct database query for term name
									global $wpdb;
									$label = $wpdb->get_var($wpdb->prepare(
										"SELECT name FROM {$wpdb->terms} WHERE term_id = %d",
										$term_id
									));
								} elseif (strpos($location_raw, 'post_') === 0) {
									$zone_id = absint(str_replace('post_', '', $location_raw));
									$label = get_the_title($zone_id);
								}

								if (!$label) {
									$label = MPTBM_Function::get_taxonomy_name_by_slug($location_raw, 'locations');
								}

								if (!$label) {
									$label = $location_raw; // final fallback
								}

								return $label;
							}
						}

						if ($price_based == 'manual' || $price_based == 'fixed_zone') {
						?>
							<?php
							$all_start_locations = MPTBM_Function::get_all_start_location($vehicle_id ?? 0, $price_based);
							// Only preselect when the requested zone is actually one of this
							// search's real options - a bad/unconfigured term ID from the
							// shortcode falls back to the original "please select" placeholder
							// instead of silently selecting nothing that matches a price row.
							$pickup_zone_matched = $pickup_zone !== '' && in_array($pickup_zone, $all_start_locations, true);
							?>
							<select id="mptbm_manual_start_place" class="mptbm_manual_start_place formControl">
								<option <?php echo $pickup_zone_matched ? '' : 'selected '; ?>disabled><?php echo mptbm_get_translation('select_pick_up_location_label', __(' Select Pick-Up Location', 'ecab-taxi-booking-manager')); ?></option>
								<?php if (sizeof($all_start_locations) > 0) { ?>
									<?php foreach ($all_start_locations as $start_location) { ?>
										<?php
											$start_label = mptbm_resolve_location_label($start_location);
											// Get geo coordinates for fixed_zone locations
											$geo_coords = '';
											if ($price_based === 'fixed_zone' && strpos($start_location, 'term_') === 0) {
												$term_id = absint(str_replace('term_', '', $start_location));
												$geo_location = get_term_meta($term_id, 'mptbm_geo_location', true);
												if ($geo_location) {
													$geo_coords = $geo_location; // format: "lat,lng"
												}
											}
											$is_preselected_zone = $pickup_zone_matched && $start_location === $pickup_zone;
										?>
										<option class="textCapitalize" value="<?php echo esc_attr($start_location); ?>" <?php echo $geo_coords ? 'data-geo="' . esc_attr($geo_coords) . '"' : ''; ?> data-label="<?php echo esc_attr($start_label); ?>" <?php echo $is_preselected_zone ? 'selected' : ''; ?>><?php echo esc_html($start_label); ?></option>
									<?php } ?>
								<?php } ?>
							</select>
						<?php } elseif ($price_based == 'fixed_zone_dropoff') { ?>
							<input type="text" id="mptbm_map_start_place" class="formControl" placeholder="<?php echo mptbm_get_translation('enter_pick_up_location_label', __('Enter Pick-Up Location', 'ecab-taxi-booking-manager')); ?>" value="<?php echo esc_attr($pickup); ?>" />
						<?php } elseif ($price_based == 'fixed_route') { ?>
							<?php
								$all_routes = MPTBM_Function::get_all_routes($vehicle_id ?? 0);
								// Shortcode's `route` attribute pre-selects this route - only when it's
								// actually one of this vehicle's real options, same safety rule as the
								// fixed_zone pickup/dropoff zone pre-selection above.
								$route_matched = $route !== '' && in_array($route, $all_routes, true);
							?>
							<!-- Deliberately its OWN id, not #mptbm_manual_start_place: that id is
							also matched by a global "block the native dropdown, replace it with a
							custom search wrapper" handler meant for manual mode's location picker
							(mptbm_registration.js). This route select never gets that custom wrapper
							built for it, so sharing the id made it silently unclickable/unreliable
							for real mouse users - a plain native <select> works fine here as-is. -->
							<select id="mptbm_route_select" class="formControl">
								<option <?php echo $route_matched ? '' : 'selected '; ?>disabled><?php echo mptbm_get_translation('select_route_label', __(' Select a Route', 'ecab-taxi-booking-manager')); ?></option>
								<?php foreach ($all_routes as $route_name) { ?>
									<option class="textCapitalize" value="<?php echo esc_attr($route_name); ?>" <?php echo ($route_matched && $route_name === $route) ? 'selected' : ''; ?>><?php echo esc_html($route_name); ?></option>
								<?php } ?>
							</select>
							<?php
								// wp_json_encode() returns false (not a string) if the route
								// name/waypoints contain invalid UTF-8 - a real risk, since
								// this is free-text an admin can paste from anywhere (Word,
								// Excel, etc. often leave behind invalid bytes). Echoing false
								// prints nothing, which would otherwise leave a bare
								// "window.mptbmRouteWaypoints = ;" - a hard JS syntax error
								// that stops every other script on the page from running too.
								$route_waypoints_map = MPTBM_Function::get_route_waypoints_map($vehicle_id ?? 0);
								$route_waypoints_json = wp_json_encode($route_waypoints_map, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
								if ($route_waypoints_json === false) {
									$route_waypoints_json = '{}';
								}
								// Pre-selected route's stops, set as the SAME window.mptbmDisplayStops
								// global the `stops` shortcode attribute already uses - the existing
								// DOMContentLoaded dispatch (mptbm_registration.js) picks this up and
								// plots it automatically, with no click needed. Only when actually
								// matched; otherwise leave it for the generic `stops` attribute (if any)
								// to set instead.
								$initial_display_stops = ($route_matched && isset($route_waypoints_map[$route]))
									? array_filter(array_map('trim', explode(',', $route_waypoints_map[$route])))
									: array();
								$initial_display_stops_json = wp_json_encode(array_values($initial_display_stops), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
								if ($initial_display_stops_json === false) {
									$initial_display_stops_json = '[]';
								}
							?>
							<script>
								// Consumed by mptbm_registration.js: when the route dropdown
								// changes, it looks up this route's waypoints and reuses the
								// same display-only geocode+marker logic as the `stops`
								// shortcode attribute - a map preview only, never fed into
								// distance/price (the fixed price is already looked up by name).
								window.mptbmRouteWaypoints = <?php echo $route_waypoints_json; ?>;
								<?php if (!empty($initial_display_stops)) : ?>
								window.mptbmDisplayStops = <?php echo $initial_display_stops_json; ?>;
								<?php endif; ?>
							</script>
						<?php } else { ?>
							<input type="text" id="mptbm_map_start_place" class="formControl" placeholder="<?php echo mptbm_get_translation('enter_pick_up_location_label', __('Enter Pick-Up Location', 'ecab-taxi-booking-manager')); ?>" value="<?php echo esc_attr($pickup); ?>" />
						<?php } ?>
						<i class="fas fa-map-marker-alt mptbm_left_icon allCenter"></i>
					</label>
				</div>
				<?php
					$extra_stop = MP_Global_Function::get_settings('mptbm_general_settings', 'mptbm_extra_stop_between_pickup_dropoff', 'no');
					$max_extra_stops = (int) MP_Global_Function::get_settings('mptbm_general_settings', 'mptbm_max_extra_stops', 3);
					$max_extra_stops = $max_extra_stops > 0 ? $max_extra_stops : 3;
					$excluded_price_based = ['fixed_zone', 'fixed_zone_dropoff', 'fixed_hourly', 'fixed_price', 'fixed_zone_pickup', 'manual', 'fixed_route'];
					if ($extra_stop == 'yes' && !in_array($price_based, $excluded_price_based)) {
				?>
					<div class="inputList mptbm_extra_stops_wrapper" data-max-stops="<?php echo esc_attr($max_extra_stops); ?>">
						<div class="mptbm_extra_stops_list"></div>
						<label class="fdColumn">
							<span class="mptbm_add_extra_stop_row" style="cursor: pointer; color: var(--color_theme); display: inline-flex; align-items: center; font-size: 14px; font-weight: 500; margin: 10px 0;">
								<i class="fas fa-plus-circle" style="margin-right: 5px;"></i> <?php echo mptbm_get_translation('add_extra_stop_label', __('Add Extra Stop', 'ecab-taxi-booking-manager')); ?>
							</span>
						</label>
						<template id="mptbm_extra_stop_row_template">
							<label class="fdColumn mptbm_extra_stop_row">
								<span><?php echo mptbm_get_translation('extra_stop_location_label', __('Extra Stop Location', 'ecab-taxi-booking-manager')); ?></span>
								<div style="position: relative; width: 100%;">
									<input type="text" class="formControl mptbm_extra_stop_place_input" name="mptbm_extra_stop_place[]" placeholder="<?php echo mptbm_get_translation('enter_extra_stop_location_placeholder', __('Enter Extra Stop Location', 'ecab-taxi-booking-manager')); ?>" value="" />
									<input type="hidden" class="mptbm_extra_stop_coords" name="mptbm_extra_stop_place_coordinates[]" value="" />
									<i class="fas fa-map-marker-alt mptbm_left_icon allCenter"></i>
								</div>
								<span class="mptbm_remove_extra_stop_row" style="display: inline-block; text-align: right; margin-top: 8px; margin-bottom: 8px; cursor: pointer; color: #dc3545; font-size: 12px; align-self: flex-end;">
									<i class="fas fa-times"></i> <?php echo mptbm_get_translation('remove_label', __('Remove', 'ecab-taxi-booking-manager')); ?>
								</span>
							</label>
						</template>
					</div>
				<?php } ?>
				<?php if (!($hide_dropoff)): ?>
<div class="inputList">
    <label class="fdColumn mptbm_manual_end_place">
        <span><?php echo mptbm_get_translation('dropoff_location_label', __('Drop-Off Location', 'ecab-taxi-booking-manager')); ?></span>
        <?php if ($price_based == 'manual') { ?>
            <select class="formControl mptbm_map_end_place" id="mptbm_manual_end_place">
                <option class="textCapitalize" selected disabled><?php echo mptbm_get_translation('select_destination_location_label', __(' Select Destination Location', 'ecab-taxi-booking-manager')); ?></option>
            </select>
        <?php } elseif ($price_based == 'fixed_zone_dropoff') { ?>
            <?php
            $all_end_locations = MPTBM_Function::get_all_start_location($vehicle_id ?? 0, $price_based);
            // Same safety rule as the pickup zone select: only preselect when the
            // requested zone is actually one of this search's real options.
            $dropoff_zone_matched = $dropoff_zone !== '' && in_array($dropoff_zone, $all_end_locations, true);
            ?>
            <select id="mptbm_manual_end_place" class="formControl mptbm_map_end_place">
                <option <?php echo $dropoff_zone_matched ? '' : 'selected '; ?>disabled><?php echo mptbm_get_translation('select_destination_location_label', __(' Select Destination Location', 'ecab-taxi-booking-manager')); ?></option>
                <?php if (sizeof($all_end_locations) > 0) { ?>
                    <?php foreach ($all_end_locations as $end_location) { ?>
                        <?php
                            $end_label = mptbm_resolve_location_label($end_location);
                            $geo_coords = '';
                            if (strpos($end_location, 'term_') === 0) {
                                $term_id = absint(str_replace('term_', '', $end_location));
                                $geo_location = get_term_meta($term_id, 'mptbm_geo_location', true);
                                if ($geo_location) {
                                    $geo_coords = $geo_location;
                                }
                            }
                            $is_preselected_zone = $dropoff_zone_matched && $end_location === $dropoff_zone;
                        ?>
                        <option class="textCapitalize" value="<?php echo esc_attr($end_location); ?>" <?php echo $geo_coords ? 'data-geo="' . esc_attr($geo_coords) . '"' : ''; ?> data-label="<?php echo esc_attr($end_label); ?>" <?php echo $is_preselected_zone ? 'selected' : ''; ?>><?php echo esc_html($end_label); ?></option>
                    <?php } ?>
                <?php } ?>
            </select>
        <?php } else { ?>
            <input type="text" id="mptbm_map_end_place" class="formControl textCapitalize" placeholder="<?php echo mptbm_get_translation('enter_dropoff_location_placeholder', __(' Enter Drop-Off Location', 'ecab-taxi-booking-manager')); ?>" value="<?php echo esc_attr($dropoff); ?>" />
        <?php } ?>
        <i class="fas fa-map-marker-alt mptbm_left_icon allCenter"></i>
    </label>
</div>
<?php else: ?>
<input type="hidden" id="mptbm_map_end_place" />
<?php endif; ?>
<?php if (!empty($display_stops)): ?>
<div class="mptbm_display_only_stops" style="margin:6px 0 14px;font-size:13px;color:#6b7280;">
    <strong style="color:#374151;"><?php echo mptbm_get_translation('via_stops_label', __('Via:', 'ecab-taxi-booking-manager')); ?></strong>
    <?php echo esc_html(implode(', ', $display_stops)); ?>
</div>
<?php
    // See the fixed_route waypoints block above for why this guards against
    // wp_json_encode() returning false (invalid UTF-8 in the shortcode's
    // `stops` text) instead of echoing it directly.
    $display_stops_json = wp_json_encode(array_values($display_stops), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if ($display_stops_json === false) {
        $display_stops_json = '[]';
    }
?>
<script>
    // Consumed by mptbm_registration.js to drop a marker for each name on
    // whichever map is active - display only, never fed into the routed
    // waypoint list, so distance/price stay based on pickup/dropoff alone.
    window.mptbmDisplayStops = <?php echo $display_stops_json; ?>;
</script>
<?php endif; ?>
<input type="hidden" name="mptbm_original_price_base" value="<?php echo esc_attr($price_based); ?>" />
<?php if ($hide_dropoff) : ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // fixed_hourly's pickup is the #mptbm_map_start_place text input; fixed_route's
    // is the #mptbm_route_select route <select> instead - check both so this
    // sync works for either mode that hides the dropoff field.
    var pickup = document.getElementById('mptbm_map_start_place') || document.getElementById('mptbm_route_select');
    var dropoff = document.getElementById('mptbm_map_end_place');
    if (pickup && dropoff) {
        function syncDropoff() {
            dropoff.value = pickup.value;
        }
        pickup.addEventListener('input', syncDropoff);
        pickup.addEventListener('change', syncDropoff);
        syncDropoff();
    }
});
</script>
<?php endif; ?>
<?php if ($pickup !== '' || $dropoff !== '') : ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Shortcode-supplied pickup/dropoff values were written straight into the
    // input value attributes above (no user interaction happened), so nothing
    // that normally listens for typing/selecting a place has fired yet. jQuery's
    // delegated change handlers here only react to jQuery's own trigger(), not
    // a plain native dispatchEvent - use jQuery (already loaded plugin-wide) so
    // this fires exactly as if the visitor had entered these values.
    //
    // Note: this file's inline <script> blocks deliberately avoid the
    // logical-AND operator (nested ifs / .every(Boolean) instead). Something
    // in this site's output pipeline sporadically HTML-entity-encodes that
    // bare double-ampersand token inside a <script> tag, which is a JS syntax
    // error that silently kills the whole block - it hit this plugin's own
    // pre-existing day-specific-time-range script the same way. Avoiding the
    // token here sidesteps that bug rather than depending on a fix elsewhere.
    var pickupField = document.getElementById('mptbm_map_start_place');
    var dropoffField = document.getElementById('mptbm_map_end_place');
    if ([pickupField, pickupField ? pickupField.value : null, typeof jQuery !== 'undefined'].every(Boolean)) {
        jQuery(pickupField).trigger('change');
    }
    if ([dropoffField, dropoffField ? dropoffField.value : null, typeof jQuery !== 'undefined'].every(Boolean)) {
        jQuery(dropoffField).trigger('change');
    }

    <?php if ($pickup !== '' && $dropoff !== '') : ?>
    // Draw the route on the preview map immediately. Normally the route only
    // draws once the visitor picks a suggestion from the address autocomplete
    // (place_changed for Google, the OSM suggestion click for OpenStreetMap) -
    // with both ends already supplied by the shortcode, no such selection ever
    // happens, so it's triggered here using the same functions those selection
    // handlers call.
    (function () {
        var pickupText = <?php echo wp_json_encode($pickup); ?>;
        var dropoffText = <?php echo wp_json_encode($dropoff); ?>;

        function isOsmMode() {
            var mapType = document.getElementById('mptbm_map_type');
            if (!mapType) return false;
            return mapType.value === 'openstreetmap';
        }

        // OSRM (the OSM route engine) needs actual coordinates, unlike
        // Google's Directions API, which can resolve plain address text on
        // its own - so the OSM path geocodes each address first via the
        // same admin-ajax proxy the plugin already uses elsewhere.
        function geocodeOsm(address, callback) {
            var url = mptbm_ajax.ajax_url + '?action=mptbm_osm_search&nonce=' + mptbm_ajax.osm_nonce + '&q=' + encodeURIComponent(address);
            fetch(url).then(function (r) { return r.json(); }).then(function (res) {
                var hasResults = false;
                if ([res, res ? res.success : null, res ? res.data : null].every(Boolean)) {
                    hasResults = res.data.length > 0;
                }
                if (hasResults) {
                    callback({ lat: res.data[0].lat, lon: res.data[0].lon, display_name: res.data[0].display_name || address });
                } else {
                    callback(null);
                }
            }).catch(function () { callback(null); });
        }

        // Pickup and dropoff don't depend on each other, so geocode both at
        // once instead of one after the other - roughly halves the time
        // before the preview route appears (was ~2 sequential round-trips
        // to the geocoder before the OSRM call could even start).
        function drawOsmRoute() {
            var startResult = null;
            var endResult = null;
            var startDone = false;
            var endDone = false;

            function tryDraw() {
                if (!startDone) return;
                if (!endDone) return;
                if (!startResult) return;
                if (!endResult) return;
                drawOsmMarkersAndRoute(startResult, endResult);
            }

            geocodeOsm(pickupText, function (start) {
                startResult = start;
                startDone = true;
                tryDraw();
            });
            geocodeOsm(dropoffText, function (end) {
                endResult = end;
                endDone = true;
                tryDraw();
            });
        }

        // A trimmed, visual-only version of the plugin's own OSM route
        // drawing (mptbm_calculate_osm_distance() in mptbm_registration.js,
        // which mptbm_handle_osm_address_selection() would otherwise call).
        // That shared function also calls mptbm_sync_distance_from_server(),
        // which re-verifies the distance through this site's own server -
        // and with a Google key configured under Map API Settings, that
        // hits Google's paid Distance Matrix API. Deliberately not reusing
        // it here: with both ends supplied by the shortcode (no visitor
        // interaction happened yet), every single page view would silently
        // spend a Google API call before anyone expressed real intent to
        // book. This draws the same marker + route preview using only the
        // free, public OSRM router - the actual verified distance/price
        // (Google if configured, OSRM otherwise) still runs normally the
        // moment the visitor clicks Search, exactly like any other booking.
        function drawOsmMarkersAndRoute(start, end) {
            if (typeof mptbm_ensure_osm_map_ready === 'function') {
                mptbm_ensure_osm_map_ready();
            }
            if (typeof L === 'undefined') return;
            if (!mptbm_osm_map) return;

            if (mptbm_osm_start_marker) { mptbm_osm_map.removeLayer(mptbm_osm_start_marker); }
            if (mptbm_osm_end_marker) { mptbm_osm_map.removeLayer(mptbm_osm_end_marker); }

            var startLat = parseFloat(start.lat);
            var startLng = parseFloat(start.lon);
            var endLat = parseFloat(end.lat);
            var endLng = parseFloat(end.lon);

            mptbm_osm_start_marker = L.marker([startLat, startLng]).addTo(mptbm_osm_map);
            mptbm_osm_start_marker.bindPopup(start.display_name);
            mptbm_osm_end_marker = L.marker([endLat, endLng]).addTo(mptbm_osm_map);
            mptbm_osm_end_marker.bindPopup(end.display_name);

            // Same public OSRM endpoint mptbm_calculate_osm_distance() uses for
            // the drawn shape - free, no key, not the site's own paid Google
            // lookup.
            var osrmUrl = 'https://router.project-osrm.org/route/v1/driving/'
                + startLng + ',' + startLat + ';' + endLng + ',' + endLat
                + '?overview=full&geometries=geojson';

            fetch(osrmUrl).then(function (r) { return r.json(); }).then(function (data) {
                var hasRoute = false;
                if ([data, data ? data.code : null, data ? data.routes : null].every(Boolean)) {
                    hasRoute = data.code === 'Ok' ? data.routes.length > 0 : false;
                }
                if (!hasRoute) return;

                var route = data.routes[0];
                var coordinates = route.geometry.coordinates.map(function (coord) {
                    return [coord[1], coord[0]];
                });

                if (mptbm_osm_route) { mptbm_osm_map.removeLayer(mptbm_osm_route); }
                mptbm_osm_route = L.polyline(coordinates, { color: '#ff4757', weight: 4, opacity: 0.9 }).addTo(mptbm_osm_map);
                mptbm_osm_map.fitBounds(mptbm_osm_route.getBounds().pad(0.1));

                var distanceKm = route.distance / 1000;
                var kmOrMileEl = document.getElementById('mptbm_km_or_mile');
                var kmOrMile = kmOrMileEl ? kmOrMileEl.value : 'km';
                var distanceText = kmOrMile === 'mile'
                    ? (distanceKm * 0.621371).toFixed(1) + ' MILE'
                    : distanceKm.toFixed(1) + ' KM';

                var durationMin = Math.round(route.duration / 60);
                var hours = Math.floor(durationMin / 60);
                var minutes = durationMin % 60;
                var durationText = hours > 0 ? (hours + ' Hour ' + minutes + ' Min') : (minutes + ' Min');

                var currentMapWrap = typeof mptbm_get_current_map_wrap === 'function' ? mptbm_get_current_map_wrap() : null;
                if (currentMapWrap) {
                    var distanceEl = currentMapWrap.querySelector('.mptbm_total_distance');
                    if (distanceEl) { distanceEl.textContent = ' ' + distanceText; }
                    var timeEl = currentMapWrap.querySelector('.mptbm_total_time');
                    if (timeEl) { timeEl.textContent = durationText; }
                    if (typeof jQuery !== 'undefined') {
                        jQuery(currentMapWrap).find('.mptbm_distance_time').slideDown('fast');
                    }
                }
            }).catch(function () { });
        }

        function drawGoogleRoute() {
            if (typeof mptbm_set_cookie_distance_duration === 'function') {
                mptbm_set_cookie_distance_duration(pickupText, dropoffText);
            }
        }

        var attempts = 0;
        function waitForMapThenDraw() {
            attempts++;
            if (isOsmMode()) {
                if (typeof L !== 'undefined') {
                    drawOsmRoute();
                    return;
                }
            } else if (typeof google !== 'undefined' ? google.maps : false) {
                drawGoogleRoute();
                return;
            }
            if (attempts < 25) {
                setTimeout(waitForMapThenDraw, 200);
            }
        }
        waitForMapThenDraw();
    })();
    <?php endif; ?>
});
</script>
<?php endif; ?>
<?php if ($pickup_zone_matched ?? false) : ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // The matching <option> above already has the "selected" attribute, but
    // nothing that reacts to a real user pick (placing the zone marker,
    // storing its coordinates) has run yet - dispatch the same 'change' this
    // dropdown fires on a manual selection so that existing logic does.
    var zoneSelect = document.getElementById('mptbm_manual_start_place');
    var zoneValue = <?php echo wp_json_encode($pickup_zone); ?>;
    if (!zoneSelect) return;
    if (zoneSelect.value !== zoneValue) return;

    function isOsmMode() {
        var mapType = document.getElementById('mptbm_map_type');
        if (!mapType) return false;
        return mapType.value === 'openstreetmap';
    }

    // The existing change-handler for this dropdown places the zone marker
    // only when the map object already exists, with no lazy init fallback
    // (unlike the autocomplete-selection path, which calls
    // mptbm_ensure_osm_map_ready() first) - dispatching before the map has
    // finished initializing would silently drop the marker. Wait for it.
    var mapAttempts = 0;
    function waitForMapThenDispatch() {
        mapAttempts++;
        var mapReady = isOsmMode()
            ? (typeof mptbm_osm_map !== 'undefined' ? !!mptbm_osm_map : false)
            : (typeof mptbm_map !== 'undefined' ? !!mptbm_map : false);
        if (mapReady) {
            // jQuery's delegated change handler for this dropdown (the one
            // that places the zone marker) only reacts to jQuery's own
            // trigger(), not a plain native dispatchEvent.
            if (typeof jQuery !== 'undefined') {
                jQuery(zoneSelect).trigger('change');
            }
            return;
        }
        if (mapAttempts < 25) {
            setTimeout(waitForMapThenDispatch, 200);
        }
    }
    waitForMapThenDispatch();
});
</script>
<?php endif; ?>
<?php if ($dropoff_zone_matched ?? false) : ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Mirrors the pickup_zone block above, for fixed_zone_dropoff's dropoff
    // dropdown instead of the pickup one.
    var zoneSelect = document.getElementById('mptbm_manual_end_place');
    var zoneValue = <?php echo wp_json_encode($dropoff_zone); ?>;
    if (!zoneSelect) return;
    if (zoneSelect.value !== zoneValue) return;

    function isOsmMode() {
        var mapType = document.getElementById('mptbm_map_type');
        if (!mapType) return false;
        return mapType.value === 'openstreetmap';
    }

    var mapAttempts = 0;
    function waitForMapThenDispatch() {
        mapAttempts++;
        var mapReady = isOsmMode()
            ? (typeof mptbm_osm_map !== 'undefined' ? !!mptbm_osm_map : false)
            : (typeof mptbm_map !== 'undefined' ? !!mptbm_map : false);
        if (mapReady) {
            if (typeof jQuery !== 'undefined') {
                jQuery(zoneSelect).trigger('change');
            }
            return;
        }
        if (mapAttempts < 25) {
            setTimeout(waitForMapThenDispatch, 200);
        }
    }
    waitForMapThenDispatch();
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
					<span class="fas fa-suitcase mptbm_left_icon allCenter"></span>
				</label>
			</div>
			<?php endif; ?>
			<?php if ($pro_active && $enable_max_hand_luggage_filter === 'yes'): ?>
			<div class="inputList mp_input_select">
				<label class="fdColumn">
					<span><?php echo mptbm_get_translation('max_hand_luggage_label', __('Maximum Hand Luggage', 'ecab-taxi-booking-manager')); ?></span>
					<select id="mptbm_max_hand_luggage" class="formControl" name="mptbm_max_hand_luggage">
						<?php for ($i = 0; $i <= $max_hand_luggage; $i++) { ?>
							<option value="<?php echo esc_attr($i); ?>"><?php echo esc_html($i); ?></option>
						<?php } ?>
					</select>
					<span class="fa fa-suitcase-rolling mptbm_left_icon allCenter"></span>
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
					<?php
						$one_way_label = mptbm_get_translation('one_way_label', __('One Way', 'ecab-taxi-booking-manager'));
						$return_label = mptbm_get_translation('return_label', __('Return', 'ecab-taxi-booking-manager'));
					?>
					<div class="inputList mptbm_select_proxy" data-proxy-for="mptbm_taxi_return">
						<label class="fdColumn">
							<span><?php echo mptbm_get_translation('transfer_type_label', __('Transfer Type', 'ecab-taxi-booking-manager')); ?></span>
							<input type="text" class="formControl" value="<?php echo esc_attr($one_way_label); ?>" readonly />
							<i class="fas fa-exchange-alt mptbm_left_icon allCenter"></i>
						</label>
						<ul class="mp_input_select_list">
							<li data-value="1"><?php echo esc_html($one_way_label); ?></li>
							<li data-value="2"><?php echo esc_html($return_label); ?></li>
						</ul>
						<select name="mptbm_taxi_return" id="mptbm_taxi_return" data-collapse-target class="mptbm_proxy_native_select">
							<option value="1" selected><?php echo esc_html($one_way_label); ?></option>
							<option data-option-target="#different_date_return" value="2"><?php echo esc_html($return_label); ?></option>
						</select>
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
					<?php
						$waiting_hour_word = mptbm_get_translation('hours_in_waiting_label', __('Hour', 'ecab-taxi-booking-manager'));
						$waiting_hours_word = mptbm_get_translation('hours_in_waiting_label', __('Hours', 'ecab-taxi-booking-manager'));
						$no_waiting_label = mptbm_get_translation('no_waiting_label', __('No Waiting', 'ecab-taxi-booking-manager'));
						$waiting_options = array(
							0 => $no_waiting_label,
							1 => '1 ' . $waiting_hour_word,
							2 => '2 ' . $waiting_hours_word,
							3 => '3 ' . $waiting_hours_word,
							4 => '4 ' . $waiting_hours_word,
							5 => '5 ' . $waiting_hours_word,
							6 => '6 ' . $waiting_hours_word,
						);
					?>
					<div class="inputList mptbm_select_proxy" data-proxy-for="mptbm_waiting_time">
						<label class="fdColumn">
							<span><?php echo mptbm_get_translation('extra_waiting_hours_label', __('Extra Waiting Hours', 'ecab-taxi-booking-manager')); ?></span>
							<input type="text" class="formControl" value="<?php echo esc_attr($no_waiting_label); ?>" readonly />
							<i class="far fa-clock mptbm_left_icon allCenter"></i>
						</label>
						<ul class="mp_input_select_list">
							<?php foreach ($waiting_options as $i => $waiting_label) { ?>
								<li data-value="<?php echo esc_attr($i); ?>"><?php echo esc_html($waiting_label); ?></li>
							<?php } ?>
						</ul>
						<select name="mptbm_waiting_time" id="mptbm_waiting_time" class="mptbm_proxy_native_select">
							<?php foreach ($waiting_options as $i => $waiting_label) { ?>
								<option value="<?php echo esc_attr($i); ?>" <?php echo $i === 0 ? 'selected' : ''; ?>><?php echo esc_html($waiting_label); ?></option>
							<?php } ?>
						</select>
					</div>
				<?php } ?>
				<?php if ($price_based == 'fixed_hourly' || $price_based == 'fixed_daily') {
					$mptbm_is_daily_pricing = ($price_based == 'fixed_daily');
					if ($mptbm_is_daily_pricing) {
						$minimum_booking_hours = MP_Global_Function::get_settings('mptbm_general_settings', 'minimum_booking_days', '1');
						$minimum_booking_hours = max(1, intval($minimum_booking_hours));
						$max_hours = 30; // Maximum days to show in dropdown
					} else {
						$minimum_booking_hours = MP_Global_Function::get_settings('mptbm_general_settings', 'minimum_booking_hours', '0');
						$minimum_booking_hours = intval($minimum_booking_hours);
						$max_hours = 12; // Maximum hours to show in dropdown
					}
					// If setting is 0 (disabled), start from 1 hour
					$start_hours = ($minimum_booking_hours == 0) ? 1 : $minimum_booking_hours;
				?>
					<?php
						$hour_labels = array();
						for ($i = $start_hours; $i <= $max_hours; $i++) {
							if ($mptbm_is_daily_pricing) {
								$hour_labels[$i] = ($i == 1)
									? mptbm_get_translation('one_day_label', __('1 Day', 'ecab-taxi-booking-manager'))
									: sprintf(__('%d Days', 'ecab-taxi-booking-manager'), $i);
								continue;
							}
							switch ($i) {
								case 1: $hour_labels[$i] = mptbm_get_translation('one_hour_label', __('1 Hour', 'ecab-taxi-booking-manager')); break;
								case 2: $hour_labels[$i] = mptbm_get_translation('two_hours_label', __('2 Hours', 'ecab-taxi-booking-manager')); break;
								case 3: $hour_labels[$i] = mptbm_get_translation('three_hours_label', __('3 Hours', 'ecab-taxi-booking-manager')); break;
								case 4: $hour_labels[$i] = mptbm_get_translation('four_hours_label', __('4 Hours', 'ecab-taxi-booking-manager')); break;
								case 5: $hour_labels[$i] = mptbm_get_translation('five_hours_label', __('5 Hours', 'ecab-taxi-booking-manager')); break;
								case 6: $hour_labels[$i] = mptbm_get_translation('six_hours_label', __('6 Hours', 'ecab-taxi-booking-manager')); break;
								case 7: $hour_labels[$i] = mptbm_get_translation('seven_hours_label', __('7 Hours', 'ecab-taxi-booking-manager')); break;
								case 8: $hour_labels[$i] = mptbm_get_translation('eight_hours_label', __('8 Hours', 'ecab-taxi-booking-manager')); break;
								case 9: $hour_labels[$i] = mptbm_get_translation('nine_hours_label', __('9 Hours', 'ecab-taxi-booking-manager')); break;
								case 10: $hour_labels[$i] = mptbm_get_translation('ten_hours_label', __('10 Hours', 'ecab-taxi-booking-manager')); break;
								case 11: $hour_labels[$i] = mptbm_get_translation('eleven_hours_label', __('11 Hours', 'ecab-taxi-booking-manager')); break;
								case 12: $hour_labels[$i] = mptbm_get_translation('twelve_hours_label', __('12 Hours', 'ecab-taxi-booking-manager')); break;
								default: $hour_labels[$i] = sprintf(__('%d Hours', 'ecab-taxi-booking-manager'), $i); break;
							}
						}
					?>
					<div class="inputList mp_input_select">
						<input type="hidden" name="mptbm_fixed_hours" id="mptbm_fixed_hours" value="<?php echo esc_attr($start_hours); ?>" />
						<label class="fdColumn">
							<span><?php echo $mptbm_is_daily_pricing ? mptbm_get_translation('select_days_label', __('Select Days', 'ecab-taxi-booking-manager')) : mptbm_get_translation('select_hours_label', __('Select Hours', 'ecab-taxi-booking-manager')); ?></span>
							<input type="text" class="formControl" value="<?php echo esc_attr($hour_labels[$start_hours]); ?>" readonly />
							<i class="far fa-clock mptbm_left_icon allCenter"></i>
						</label>
						<ul class="mp_input_select_list">
							<?php foreach ($hour_labels as $i => $hour_label) { ?>
								<li data-value="<?php echo esc_attr($i); ?>"><?php echo esc_html($hour_label); ?></li>
							<?php } ?>
						</ul>
					</div>
					<div class="mptbm_fixed_hours_warning" id="mptbm_fixed_hours_warning" style="display:none;">
						<i class="fas fa-exclamation-triangle"></i>
						<span></span>
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
					<?php if ($taxi_return != 'enable' && $price_based != 'fixed_hourly' && $price_based != 'fixed_daily') { ?>
						<div class="inputList"></div>
					<?php } ?>
					<?php if ($waiting_time_check != 'enable' && $price_based != 'fixed_hourly' && $price_based != 'fixed_daily') { ?>
						<div class="inputList"></div>
					<?php } ?>
					<?php if ($price_based == 'fixed_hourly' || $price_based == 'fixed_daily') { ?>
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
			height: 100% !important;
			width: 100% !important;
			border: 1px solid #ddd;
			border-radius: 12px;
			overflow: hidden;
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
		<div class="mptbm_map_area fdColumn" data-map="<?php echo esc_attr($map); ?>" data-show-map-result="<?php echo esc_attr($show_map_on_result); ?>" data-manual-map="<?php echo $manual_map_enabled ? 'yes' : 'no'; ?>" style="display: <?php echo (($price_based !== 'manual' || $manual_map_enabled) && $map === 'yes' && !($hide_map_area)) ? 'flex' : 'none'; ?>;">
			<div class="mptbm_map_area_header">
				<h6><span class="fas fa-map-marked-alt mR_xs"></span><?php echo $price_based === 'manual' ? esc_html__('Route Locations', 'ecab-taxi-booking-manager') : mptbm_get_translation('route_map_label', __('Route Map', 'ecab-taxi-booking-manager')); ?></h6>
				<button type="button" class="mptbm_map_collapse_toggle" aria-expanded="true" data-expand-text="<?php esc_attr_e('Show Map', 'ecab-taxi-booking-manager'); ?>" data-collapse-text="<?php esc_attr_e('Hide Map', 'ecab-taxi-booking-manager'); ?>">
					<span data-label><?php esc_html_e('Hide Map', 'ecab-taxi-booking-manager'); ?></span>
					<i class="fas fa-chevron-up"></i>
				</button>
			</div>
			<div class="mptbm_map_collapsible_body">
				<?php if ($manual_map_enabled && !empty($manual_map_locations)) : ?>
					<div class="mptbm_manual_map_legend" aria-label="<?php esc_attr_e('Configured route locations', 'ecab-taxi-booking-manager'); ?>">
						<span class="mptbm_manual_map_legend_title"><i class="fas fa-map-marker-alt"></i> <?php esc_html_e('Available route locations', 'ecab-taxi-booking-manager'); ?></span>
						<div class="mptbm_manual_map_location_pills">
							<?php foreach ($manual_map_locations as $manual_map_location) : ?>
								<span data-location-key="<?php echo esc_attr($manual_map_location['key']); ?>"><?php echo esc_html($manual_map_location['label']); ?></span>
							<?php endforeach; ?>
						</div>
						<small class="mptbm_manual_map_status" aria-live="polite"><?php esc_html_e('Locating route points…', 'ecab-taxi-booking-manager'); ?></small>
					</div>
					<script type="application/json" class="mptbm-manual-map-locations"><?php echo wp_json_encode($manual_map_locations); ?></script>
				<?php endif; ?>
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
				<?php if ($price_based !== 'manual' || $manual_map_enabled) : ?>
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
				<?php endif; ?>
			</div>
			<div class="mptbm_inline_search_results">
			<button type="button" class="mptbm_inline_results_reset" aria-label="<?php esc_attr_e('Reset search', 'ecab-taxi-booking-manager'); ?>" title="<?php esc_attr_e('Reset search', 'ecab-taxi-booking-manager'); ?>">
				<i class="fas fa-times"></i>
			</button>
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

	// WordPress's configured time format (Settings > General > Time Format,
	// e.g. "g:i a" for 12-hour or "H:i" for 24-hour) -- the pickup/return
	// time list is rebuilt client-side whenever the date changes, so it
	// needs its own formatter matching what MP_Global_Function::date_format()
	// already renders server-side for the initial page load.
	var mptbmTimeFormat = <?php echo wp_json_encode(get_option('time_format', 'g:i a')); ?>;

	function mptbmFormatTime(hours24, minutes) {
		var h24 = hours24 % 24;
		var h12 = h24 % 12;
		if (h12 === 0) h12 = 12;
		var isPM = h24 >= 12;
		var out = '';
		for (var idx = 0; idx < mptbmTimeFormat.length; idx++) {
			var ch = mptbmTimeFormat.charAt(idx);
			switch (ch) {
				case 'g': out += h12; break;
				case 'G': out += h24; break;
				case 'h': out += String(h12).padStart(2, '0'); break;
				case 'H': out += String(h24).padStart(2, '0'); break;
				case 'i': out += String(minutes).padStart(2, '0'); break;
				case 'a': out += isPM ? 'pm' : 'am'; break;
				case 'A': out += isPM ? 'PM' : 'AM'; break;
				case '\\':
					idx++;
					if (idx < mptbmTimeFormat.length) out += mptbmTimeFormat.charAt(idx);
					break;
				default: out += ch;
			}
		}
		return out;
	}



	function updateTimeRangeForDay(selectedDate) {
		if (!selectedDate) return;
		
		// Get the day name from the selected date
		var date = new Date(selectedDate);
		var dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
		var dayName = dayNames[date.getDay()];
		
		
		
		// Find the time range for this specific day
		var dayTimes = dayTimeRanges[dayName];
		// Built as a single boolean, not `dayTimes && dayTimes.start.length > 0 && ...`:
		// some content filter running on this page's output (WordPress's own
		// wptexturize/convert_chars, or a caching/optimizer plugin re-processing
		// the final HTML) was turning literal "&&" into the HTML entity
		// "&#038;&#038;" here, which is a hard JS syntax error that silently
		// kills every other inline script on the page - Search, the route
		// map preview, all of it. Avoiding the "&&" character sequence
		// entirely sidesteps whatever is doing that, regardless of the cause.
		var hasDayTimes = dayTimes ? (dayTimes.start.length > 0 ? dayTimes.end.length > 0 : false) : false;
		if (hasDayTimes) {
			// For each day, find the earliest start time and latest end time
			// This ensures we get the correct range for that specific day
			var minTime = Math.min.apply(Math, dayTimes.start);
			var maxTime = Math.max.apply(Math, dayTimes.end);



			// Update the time picker options
			updateTimePickerOptions(minTime, maxTime);
		} else {

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
			var timeFormatted = mptbmFormatTime(hours, minutes);
			var dataTime = String(hours).padStart(2, '0') + '.' + String(minutes).padStart(2, '0');
			
			// Add to start time list
			jQuery('.start_time_list').append('<li data-value="' + dataValue.toFixed(2) + '" data-time="' + dataTime + '">' + timeFormatted + '</li>');
			
			// Add to return time list
			jQuery('.return_time_list').append('<li data-value="' + dataValue.toFixed(2) + '" data-time="' + dataTime + '">' + timeFormatted + '</li>');
		}

		jQuery(document).trigger('mptbm_time_options_updated');
		
	}
	
	
	
	// Update time range when date is selected
	jQuery(document).ready(function() {
		// Initialize with global range on page load
		updateTimePickerOptions(<?php echo $min_schedule_value; ?>, <?php echo $max_schedule_value; ?>);
		
		jQuery('#mptbm_start_date').on('change', function() {
			var fp = this._flatpickr;
			if (fp && fp.selectedDates.length > 0) {
				var d = fp.selectedDates[0];
				var isoDate = d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
				updateTimeRangeForDay(isoDate);
			}
		});
		
		jQuery('#mptbm_return_date').on('change', function() {
			var fp = this._flatpickr;
			if (fp && fp.selectedDates.length > 0) {
				var d = fp.selectedDates[0];
				var isoDate = d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
				updateTimeRangeForDay(isoDate);
			}
		});
		
		// Also trigger on flatpickr date selection
		jQuery(document).on('click', '.flatpickr-day:not(.prevMonthDay):not(.nextMonthDay):not(.flatpickr-disabled)', function() {
			setTimeout(function() {
				var selectedDate = jQuery('#mptbm_map_start_date').val();
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
