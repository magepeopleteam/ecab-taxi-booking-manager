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
	$start_place = $start_place ?? '';
	$end_place = $end_place ?? '';
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
	// Check feature filter setting
	$enable_filter_features = MP_Global_Function::get_settings('mptbm_general_settings', 'enable_filter_via_features', 'no');
?>
	<?php if ($show_summary): ?>
	<div class="leftSidebar">
		<div class="">
			<div class="mp_sticky_on_scroll summary-box">
				<div class="_dFlex_fdColumn">
					<h3><?php echo mptbm_get_translation('summary_label', __('SUMMARY', 'ecab-taxi-booking-manager')); ?></h3>
					<div class="divider"></div>

					<h6 class="_mB_xs"><?php esc_html_e('Pickup Date', 'ecab-taxi-booking-manager'); ?></h6>
					<p class="_textLight_1"><?php echo esc_html(MP_Global_Function::date_format($date)); ?></p>
					<div class="divider"></div>
					<h6 class="_mB_xs"><?php esc_html_e('Pickup Time', 'ecab-taxi-booking-manager'); ?></h6>
					<p class="_textLight_1"><?php echo esc_html(MP_Global_Function::date_format($date, 'time')); ?></p>
					<div class="divider"></div>
					<h6 class="_mB_xs"><?php esc_html_e('Pickup Location', 'ecab-taxi-booking-manager'); ?></h6>
					<?php if($price_based == 'manual'){ ?>
						<p class="_textLight_1 "><?php echo esc_html(MPTBM_Function::get_taxonomy_name_by_slug( $start_place,'locations' )); ?></p>
					<?php }else{ ?>
						<p class="_textLight_1 "><?php echo esc_html($start_place); ?></p>
					<?php } ?>
					
					
					<?php if (!($price_based == 'fixed_hourly' && $disable_dropoff_hourly === 'disable')): ?>
						<div class="divider"></div>
						<div>
							<h6 class="_mB_xs"><?php echo mptbm_get_translation('dropoff_location_label', __('Drop-Off Location', 'ecab-taxi-booking-manager')); ?></h6>
							<?php if($price_based == 'manual'){ ?>
								<p class="_textLight_1 "><?php echo esc_html(MPTBM_Function::get_taxonomy_name_by_slug( $end_place,'locations' )); ?></p>
							<?php }else{ ?>
								<p class="_textLight_1 "><?php echo esc_html($end_place); ?></p>
							<?php } ?>

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
						<p class="_textLight_1"><?php esc_html_e('Return', 'ecab-taxi-booking-manager'); ?></p>
						<?php if(!empty($return_date_time)){ ?>
                            <div class="divider"></div>
                            <h6 class="_mB_xs"><?php esc_html_e('Return Date', 'ecab-taxi-booking-manager'); ?></h6>
                            <p class="_textLight_1"><?php echo esc_html(MP_Global_Function::date_format($return_date_time)); ?></p>
                            <div class="divider"></div>
                            <h6 class="_mB_xs"><?php esc_html_e('Return Time', 'ecab-taxi-booking-manager'); ?></h6>
                            <p class="_textLight_1"><?php echo esc_html(MP_Global_Function::date_format($return_date_time,'time')); ?></p>
                        <?php } ?>
					<?php } ?>
					<?php if($waiting_time>0){ ?>
						<div class="divider"></div>
						<h6 class="_mB_xs"><?php echo mptbm_get_translation('extra_waiting_hours_label', __('Extra Waiting Hours', 'ecab-taxi-booking-manager')); ?></h6>
						<p class="_textLight_1"><?php echo esc_html($waiting_time); ?>&nbsp;<?php echo mptbm_get_translation('hours_in_waiting_label', __('Hours', 'ecab-taxi-booking-manager')); ?></p>
					<?php } ?>
					<div class="divider"></div>
					<?php if ($enable_filter_features == 'yes') { ?>
						<h6 class="_mB_xs"><?php esc_html_e('Passengers', 'ecab-taxi-booking-manager'); ?></h6>
						<p class="_textLight_1 mptbm_summary_passenger">
							<?php
							if (!empty($summary_passenger) || $summary_passenger === 0) {
								echo esc_html($summary_passenger);
							}
							?>
						</p>
						
						<div class="divider"></div>
						<?php if($summary_bag>0){ ?>
						<h6 class="_mB_xs"><?php esc_html_e('Bags', 'ecab-taxi-booking-manager'); ?></h6>
						<p class="_textLight_1 mptbm_summary_bag">
							<?php
							if (!empty($summary_bag) || $summary_bag === 0) {
								echo esc_html($summary_bag);
							}
							?>
						</p>
						<?php } ?>
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
add_action('wp_footer', function() { ?>
<script>
    (function($){
        function updateSummaryCounts() {
            var passenger = $('#mptbm_max_passenger').val() || $('#mptbm_passengers').val() || '';
            var bag = $('#mptbm_max_bag').val() || '';
            // Fallback to data stored on selected vehicle (if present)
            var selectedItem = $('.mptbm_single_item.active');
            if (!passenger && selectedItem.length) {
                passenger = selectedItem.data('passenger');
            }
            if (!bag && selectedItem.length) {
                bag = selectedItem.data('bag');
            }
            $('.mptbm_summary_passenger').text(passenger ? passenger : '—');
            $('.mptbm_summary_bag').text(bag ? bag : '—');
        }
        $(document).ready(function(){
            updateSummaryCounts();
            $(document).on('change', '#mptbm_max_passenger, #mptbm_passengers, #mptbm_max_bag', updateSummaryCounts);
            $(document).on('click', '.mptbm_single_item', updateSummaryCounts);
        });
    })(jQuery);
</script>
<?php });
