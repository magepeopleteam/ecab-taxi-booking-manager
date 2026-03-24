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

	$distance = $distance ?? (isset($_COOKIE['mptbm_distance']) ?absint($_COOKIE['mptbm_distance']): '');
	$duration = $duration ?? (isset($_COOKIE['mptbm_duration']) ?absint($_COOKIE['mptbm_duration']): '');
	$label = $label ?? MPTBM_Function::get_name();
	$date = $date ?? '';
	$start_place = $start_place ?? (isset($_REQUEST['mptbm_start_place']) ? sanitize_text_field($_REQUEST['mptbm_start_place']) : '');
	$end_place = $end_place ?? (isset($_REQUEST['mptbm_end_place']) ? sanitize_text_field($_REQUEST['mptbm_end_place']) : '');
	$extra_stop_place = $extra_stop_place ?? (isset($_REQUEST['mptbm_extra_stop_place']) ? sanitize_text_field($_REQUEST['mptbm_extra_stop_place']) : '');
	$two_way = $two_way ?? 1;
	$waiting_time = $waiting_time ?? 0;
	$fixed_time = $fixed_time ?? '';
	$return_date_time = $return_date_time ?? '';
	$price_based = $price_based ?? '';
	$post_id = $summary_post_id ?? '';
	$km_or_mile = MP_Global_Function::get_settings('mp_global_settings', 'km_or_mile', 'km');
	// Get price display type and custom message if post_id is available
	if ($post_id) {
		$price_display_type = MP_Global_Function::get_post_info($post_id, 'mptbm_price_display_type', 'normal');
		$custom_message = MP_Global_Function::get_post_info($post_id, 'mptbm_custom_price_message', '');
	}
	
	// Check if summary should be shown in mobile
	$show_summary_mobile = MP_Global_Function::get_settings('mptbm_general_settings', 'show_summary_mobile', 'yes');
	$is_mobile = wp_is_mobile();
	$show_summary = true;
	
	// Hide summary if it's mobile and setting is set to 'no'
	if ($is_mobile && $show_summary_mobile === 'no') {
		$show_summary = false;
	}
	$disable_dropoff_hourly = MP_Global_Function::get_settings('mptbm_general_settings', 'disable_dropoff_hourly', 'enable');
	// Check pro plugin settings for passenger and bag filters
	$pro_active = class_exists('MPTBM_Dependencies_Pro');
	$search_filter_settings = $pro_active ? get_option('mptbm_search_filter_settings', array()) : array();
	$enable_max_passenger_filter = isset($search_filter_settings['enable_max_passenger_filter']) ? $search_filter_settings['enable_max_passenger_filter'] : 'no';
	$enable_max_bag_filter = isset($search_filter_settings['enable_max_bag_filter']) ? $search_filter_settings['enable_max_bag_filter'] : 'no';
	$enable_max_hand_luggage_filter = isset($search_filter_settings['enable_max_hand_luggage_filter']) ? $search_filter_settings['enable_max_hand_luggage_filter'] : 'no';
?>
	<?php if ($show_summary): ?>
	<div class="leftSidebar">
		<div class="">
			<div class="mp_sticky_on_scroll summary-box">
				<div class="_dFlex_fdColumn">
					<h3><?php echo mptbm_get_translation('summary_label', __('SUMMARY', 'ecab-taxi-booking-manager')); ?></h3>
					<div class="divider"></div>

					<h6 class="_mB_xs"><?php echo mptbm_get_translation('pickup_date_label', __('Pickup Date', 'ecab-taxi-booking-manager')); ?></h6>
					<p class="_textLight_1"><?php echo esc_html(MP_Global_Function::date_format($date)); ?></p>
					<div class="divider"></div>
					<h6 class="_mB_xs"><?php echo mptbm_get_translation('pickup_time_label', __('Pickup Time', 'ecab-taxi-booking-manager')); ?></h6>
					<p class="_textLight_1"><?php echo esc_html(MP_Global_Function::date_format($date, 'time')); ?></p>
					<div class="divider"></div>
					<h6 class="_mB_xs"><?php echo mptbm_get_translation('pickup_location_label', __('Pickup Location', 'ecab-taxi-booking-manager')); ?></h6>
					<?php if($price_based == 'manual'){ ?>
						<p class="_textLight_1 "><?php echo esc_html(MPTBM_Function::get_taxonomy_name_by_slug( $start_place,'locations' )); ?></p>
					<?php } elseif (($price_based == 'fixed_zone') && strpos($start_place, 'term_') === 0) {
						// Resolve term_XX to location name
						$term_id = absint(str_replace('term_', '', $start_place));
						$start_place_display = $start_place;
						$term = get_term($term_id, 'locations');
						if ($term && !is_wp_error($term)) {
							$start_place_display = $term->name;
						} else {
							// Fallback: try direct DB query
							global $wpdb;
							$term_name = $wpdb->get_var($wpdb->prepare(
								"SELECT name FROM {$wpdb->terms} WHERE term_id = %d",
								$term_id
							));
							if ($term_name) {
								$start_place_display = $term_name;
							}
						}
						?>
						<p class="_textLight_1 "><?php echo esc_html($start_place_display); ?></p>
					<?php } else { ?>
						<p class="_textLight_1 "><?php echo esc_html($start_place); ?></p>
					<?php } ?>
			
			<?php 
			// Display Extra Stop Location if setting is enabled and data exists
			$extra_stop_enabled = MP_Global_Function::get_settings('mptbm_general_settings', 'mptbm_extra_stop_between_pickup_dropoff', 'no');
			// Ensure we have the latest value from request if not already set
			if (empty($extra_stop_place) && isset($_REQUEST['mptbm_extra_stop_place'])) {
				$extra_stop_place = sanitize_text_field($_REQUEST['mptbm_extra_stop_place']);
			}
			
			if ($extra_stop_enabled === 'yes' && !empty($extra_stop_place) && 
			    !in_array($price_based, ['fixed_zone', 'fixed_zone_dropoff', 'fixed_hourly', 'fixed_price', 'fixed_zone_pickup'])) {
			?>
				<div class="divider"></div>
				<h6 class="_mB_xs"><?php echo mptbm_get_translation('extra_stop_location_label', __('Extra Stop Location', 'ecab-taxi-booking-manager')); ?></h6>
				<?php 
				$extra_stop_display = $extra_stop_place;
				if ($price_based == 'manual') {
					$extra_stop_display = MPTBM_Function::get_taxonomy_name_by_slug($extra_stop_place, 'locations');
				} elseif (strpos($extra_stop_place, 'term_') === 0) {
					// Resolve term_XX to location name
					$term_id = absint(str_replace('term_', '', $extra_stop_place));
					$term = get_term($term_id, 'locations');
					if ($term && !is_wp_error($term)) {
						$extra_stop_display = $term->name;
					} else {
						// Fallback: try direct DB query
						global $wpdb;
						$term_name = $wpdb->get_var($wpdb->prepare(
							"SELECT name FROM {$wpdb->terms} WHERE term_id = %d",
							$term_id
						));
						if ($term_name) {
							$extra_stop_display = $term_name;
						}
					}
				}
				?>
				<p class="_textLight_1"><?php echo esc_html($extra_stop_display); ?></p>
			<?php } ?>
			
					
					
					<?php if (!($price_based == 'fixed_hourly' && $disable_dropoff_hourly === 'disable')): ?>
						<div class="divider"></div>
						<div>
							<h6 class="_mB_xs"><?php echo mptbm_get_translation('dropoff_location_label', __('Drop-Off Location', 'ecab-taxi-booking-manager')); ?></h6>
							<?php 
							$end_place_display = $end_place;
							if($price_based == 'manual'){ 
								$end_place_display = MPTBM_Function::get_taxonomy_name_by_slug( $end_place,'locations' );
							} elseif(($price_based == 'fixed_zone' || $price_based == 'fixed_zone_dropoff') && strpos($end_place, 'term_') === 0) {
								// Resolve term_XX to location name
								$term_id = absint(str_replace('term_', '', $end_place));
								$term = get_term($term_id, 'locations');
								if ($term && !is_wp_error($term)) {
									$end_place_display = $term->name;
								} else {
									// Fallback: try to get by slug
									$term = get_term_by('slug', $end_place, 'locations');
									if ($term && !is_wp_error($term)) {
										$end_place_display = $term->name;
									} else {
										// Final fallback: try direct DB query
										global $wpdb;
										$term_name = $wpdb->get_var($wpdb->prepare(
											"SELECT name FROM {$wpdb->terms} WHERE term_id = %d",
											$term_id
										));
										if ($term_name) {
											$end_place_display = $term_name;
										}
									}
								}
							}
							?>
							<p class="_textLight_1 "><?php echo esc_html($end_place_display); ?></p>

						</div>
					<?php endif; ?>
					
					<?php if($price_based != 'manual' && $price_based != 'fixed_hourly'){ ?> 
						<div class="divider"></div>
						<div class="divider"></div>
						<h6 class="_mB_xs"><?php echo mptbm_get_translation('total_distance_label', __('Total Distance', 'ecab-taxi-booking-manager')); ?></h6>
						<?php 
							// First try to get text from cookies/request
							$distance_text = isset($_COOKIE['mptbm_distance_text']) ? $_COOKIE['mptbm_distance_text'] : (isset($_REQUEST['mptbm_distance_text']) ? $_REQUEST['mptbm_distance_text'] : '');
							
							// If text is missing but we have raw value, calculate it
							if (empty($distance_text) && !empty($distance)) {
								$distance_in_meters = floatval($distance);
								if ($km_or_mile == 'mile') {
									$dist_val = $distance_in_meters * 0.000621371;
									$distance_text = round($dist_val, 1) . ' miles';
								} else {
									$dist_val = $distance_in_meters / 1000;
									$distance_text = round($dist_val, 1) . ' km';
								}
							}

							$duration_text = isset($_COOKIE['mptbm_duration_text']) ? $_COOKIE['mptbm_duration_text'] : (isset($_REQUEST['mptbm_duration_text']) ? $_REQUEST['mptbm_duration_text'] : '');
							
							// If duration text is missing but we have raw value
							if (empty($duration_text) && !empty($duration)) {
								$duration_seconds = intval($duration);
								$hours = floor($duration_seconds / 3600);
								$minutes = round(($duration_seconds % 3600) / 60);
								
								if ($hours > 0) {
									$duration_text = sprintf(__('%d Hour %d Min', 'ecab-taxi-booking-manager'), $hours, $minutes);
								} else {
									$duration_text = sprintf(__('%d Min', 'ecab-taxi-booking-manager'), $minutes);
								}
							}
						?>
						<?php if ($two_way > 1) { 
							// If we calculated it ourselves, we can just double the numeric part or re-calculate
							if (!empty($distance) && empty($_COOKIE['mptbm_distance_text']) && empty($_REQUEST['mptbm_distance_text'])) {
								// We have raw distance, so just double raw distance and format
								$total_dist = floatval($distance) * 2;
								if ($km_or_mile == 'mile') {
									$val = $total_dist * 0.000621371;
									$display_dist = round($val, 1) . ' MILE';
								} else {
									$val = $total_dist / 1000;
									$display_dist = round($val, 1) . ' KM';
								}
							} else {
								// Fallback to parsing the text (legacy behavior)
								$distance_value = floatval($distance_text) * 2; 
								$display_dist = $distance_value ." ". ucfirst($km_or_mile);
							}
						?>
							<p class="_textLight_1 mptbm_total_distance">
								<?php echo esc_html($display_dist); ?>
							</p>
						<?php }else{ ?>
						<p class="_textLight_1 mptbm_total_distance"><?php echo esc_html($distance_text); ?></p>
						<?php }?>
						<div class="divider"></div>
						<h6 class="_mB_xs"><?php echo mptbm_get_translation('total_time_label', __('Total Time', 'ecab-taxi-booking-manager')); ?></h6>
						<p class="_textLight_1 mptbm_total_time"><?php echo esc_html($duration_text); ?></p>
					<?php } ?>
					
					
					<?php if($two_way>1){ 
						?>
						<div class="divider"></div>
						<h6 class="_mB_xs"><?php echo mptbm_get_translation('transfer_type_label', __('Transfer Type', 'ecab-taxi-booking-manager')); ?></h6>
						<p class="_textLight_1"><?php echo mptbm_get_translation('return_label', __('Return', 'ecab-taxi-booking-manager')); ?></p>
						<?php if(!empty($return_date_time)){ ?>
                            <div class="divider"></div>
                             <h6 class="_mB_xs"><?php echo mptbm_get_translation('return_date_label', __('Return Date', 'ecab-taxi-booking-manager')); ?></h6>
                             <p class="_textLight_1"><?php echo esc_html(MP_Global_Function::date_format($return_date_time)); ?></p>
                            <div class="divider"></div>
                             <h6 class="_mB_xs"><?php echo mptbm_get_translation('return_time_label', __('Return Time', 'ecab-taxi-booking-manager')); ?></h6>
                             <p class="_textLight_1"><?php echo esc_html(MP_Global_Function::date_format($return_date_time,'time')); ?></p>
                        <?php } ?>
					<?php } ?>
					<?php if($waiting_time>0){ ?>
						<div class="divider"></div>
						<h6 class="_mB_xs"><?php echo mptbm_get_translation('extra_waiting_hours_label', __('Extra Waiting Hours', 'ecab-taxi-booking-manager')); ?></h6>
						<p class="_textLight_1"><?php echo esc_html($waiting_time); ?>&nbsp;<?php echo mptbm_get_translation('hours_in_waiting_label', __('Hours', 'ecab-taxi-booking-manager')); ?></p>
					<?php } ?>
					<div class="divider"></div>
					<?php if ($pro_active && $enable_max_passenger_filter === 'yes') { ?>
						<div class="divider"></div>
						<h6 class="_mB_xs"><?php echo mptbm_get_translation('passengers_label', __('Passengers', 'ecab-taxi-booking-manager')); ?></h6>
						<p class="_textLight_1 mptbm_summary_passenger">
							<?php
							$selected_passenger = isset($_REQUEST['mptbm_max_passenger']) ? absint($_REQUEST['mptbm_max_passenger']) : '';
							if ($selected_passenger !== '') {
								echo esc_html($selected_passenger);
							} else {
								echo '—';
							}
							?>
						</p>
					<?php } ?>

					<?php if ($pro_active && $enable_max_bag_filter === 'yes') { ?>
				<div class="divider"></div>
				<h6 class="_mB_xs"><?php echo mptbm_get_translation('bags_label', __('Bags', 'ecab-taxi-booking-manager')); ?></h6>
				<p class="_textLight_1 mptbm_summary_bag">
					<?php
					$selected_bag = isset($_REQUEST['mptbm_max_bag']) ? absint($_REQUEST['mptbm_max_bag']) : '';
					if ($selected_bag !== '') {
						echo esc_html($selected_bag);
					} else {
						echo '—';
					}
					?>
				</p>
			<?php } ?>
			<?php if ($pro_active && $enable_max_hand_luggage_filter === 'yes') { ?>
				<div class="divider"></div>
				<h6 class="_mB_xs"><?php echo mptbm_get_translation('hand_luggage_label', __('Hand Luggage', 'ecab-taxi-booking-manager')); ?></h6>
				<p class="_textLight_1 mptbm_summary_hand_luggage">
					<?php
					$selected_hand_luggage = isset($_REQUEST['mptbm_max_hand_luggage']) ? absint($_REQUEST['mptbm_max_hand_luggage']) : '';
					if ($selected_hand_luggage !== '') {
						echo esc_html($selected_hand_luggage);
					} else {
						echo '—';
					}
					?>
				</p>
			<?php } ?>
					<?php if($fixed_time && $fixed_time>0){ ?>
						<div class="divider"></div>
						<h6 class="_mB_xs"><?php echo mptbm_get_translation('service_times_label', __('Service Times', 'ecab-taxi-booking-manager')); ?></h6>
						<p class="_textLight_1"><?php echo esc_html($fixed_time); ?> &nbsp;<?php echo mptbm_get_translation('hours_in_waiting_label', __('Hours', 'ecab-taxi-booking-manager')); ?></p>
					<?php } ?>
					
					<div class="mptbm_transport_summary">
						<div class="divider"></div>
						<h6 class="_mB_xs"><?php echo esc_html($label) . ' ' . esc_html__(' Details', 'ecab-taxi-booking-manager') ?></h6>
						<div class="_textColor_4 justifyBetween">
							<div class="_dFlex_alignCenter">
								<span class="fas fa-check-square _textTheme_mR_xs"></span>
								<span class="mptbm_product_name"></span>
							</div>
							<?php if (isset($price_display_type) && $price_display_type === 'custom_message' && !empty($custom_message)): ?>
								<span class="mptbm_product_price _textTheme"><?php echo wp_kses_post($custom_message); ?></span>
							<?php else: ?>
								<span class="mptbm_product_price _textTheme"></span>
							<?php endif; ?>
						</div>
						<div class="mptbm_base_price_detail"></div>
						<div class="mptbm_extra_service_summary"></div>
						<div class="divider"></div>
						<div class="justifyBetween">
							<h4><?php esc_html_e('Total : ', 'ecab-taxi-booking-manager'); ?></h4>
							<h6 class="mptbm_product_total_price"></h6>
						</div>
					</div>
				</div>
				<div class="divider"></div>
				<button type="button" class="_mpBtn_fullWidth mptbm_get_vehicle_prev">
					<span>&longleftarrow; &nbsp;<?php esc_html_e('Previous', 'ecab-taxi-booking-manager'); ?></span>
				</button>
			</div>
		</div>
	</div>
	<?php endif; ?>
<?php
// Populate passengers/bags summary from the current form selections (if available)
add_action('wp_footer', function() {
    // Get values from request if available (for initial load)
    $initial_passenger = isset($_REQUEST['mptbm_max_passenger']) ? absint($_REQUEST['mptbm_max_passenger']) : '';
    $initial_bag = isset($_REQUEST['mptbm_max_bag']) ? absint($_REQUEST['mptbm_max_bag']) : '';
    $initial_hand_luggage = isset($_REQUEST['mptbm_max_hand_luggage']) ? absint($_REQUEST['mptbm_max_hand_luggage']) : '';
    ?>
<script>
    (function($){
        function updateSummaryCounts() {
            var passenger = $('#mptbm_max_passenger').val() || $('#mptbm_passengers').val() || '<?php echo esc_js($initial_passenger); ?>' || '';
            var bag = $('#mptbm_max_bag').val() || '<?php echo esc_js($initial_bag); ?>' || '';
            var hand_luggage = $('#mptbm_max_hand_luggage').val() || '<?php echo esc_js($initial_hand_luggage); ?>' || '';

            // Fallback to data stored on selected vehicle (if present)
            var selectedItem = $('.mptbm_single_item.active');
            if (!passenger && selectedItem.length) {
                passenger = selectedItem.data('passenger');
            }
            if (!bag && selectedItem.length) {
                bag = selectedItem.data('bag');
            }
            if (!hand_luggage && selectedItem.length) {
                hand_luggage = selectedItem.data('hand_luggage');
            }

            $('.mptbm_summary_passenger').text(passenger ? passenger : '—');
            $('.mptbm_summary_bag').text(bag ? bag : '—');
            $('.mptbm_summary_hand_luggage').text(hand_luggage ? hand_luggage : '—');
            
            // DEBUG: Log summary updates
            if (typeof console !== 'undefined') {
                console.log('=== SUMMARY UPDATE ===');
                console.log('Passenger:', passenger);
                console.log('Bag:', bag);
                console.log('Hand Luggage:', hand_luggage);
            }
        }

        $(document).ready(function(){
            updateSummaryCounts();
            $(document).on('change', '#mptbm_max_passenger, #mptbm_passengers, #mptbm_max_bag, #mptbm_max_hand_luggage', updateSummaryCounts);
            $(document).on('click', '.mptbm_single_item', updateSummaryCounts);
        });
    })(jQuery);
</script>
<?php });
