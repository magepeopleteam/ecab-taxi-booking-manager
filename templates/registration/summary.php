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
?>
	<?php if ($show_summary): ?>
	<div class="leftSidebar">
		<div class="">
			<div class="mp_sticky_on_scroll">
				<div class="_dFlex_fdColumn">
					<h3><?php esc_html_e('SUMMARY', 'ecab-taxi-booking-manager'); ?></h3>
					<div class="divider"></div>

					<h6 class="_mB_xs"><?php esc_html_e('Pickup Date', 'ecab-taxi-booking-manager'); ?></h6>
					<p class="_textLight_1"><?php echo esc_html(MP_Global_Function::date_format($date)); ?></p>
					<div class="divider"></div>
					<h6 class="_mB_xs"><?php esc_html_e('Pickup Time', 'ecab-taxi-booking-manager'); ?></h6>
					<p class="_textLight_1"><?php echo esc_html(MP_Global_Function::date_format($date, 'time')); ?></p>
					<div class="divider"></div>
					<h6 class="_mB_xs"><?php esc_html_e('Pickup Location', 'ecab-taxi-booking-manager'); ?></h6>
					<?php if($price_based == 'manual'){ ?>
						<p class="_textLight_1 mptbm_manual_start_place"><?php echo esc_html(MPTBM_Function::get_taxonomy_name_by_slug( $start_place,'locations' )); ?></p>
					<?php }else{ ?>
						<p class="_textLight_1 mptbm_manual_start_place"><?php echo esc_html($start_place); ?></p>
					<?php } ?>
					
					
					<?php if (!($price_based == 'fixed_hourly' && $disable_dropoff_hourly === 'disable')): ?>
						<div class="divider"></div>
						<div>
							<h6 class="_mB_xs"><?php echo mptbm_get_translation('dropoff_location_label', __('Drop-Off Location', 'ecab-taxi-booking-manager')); ?></h6>
							<?php if($price_based == 'manual'){ ?>
								<p class="_textLight_1 mptbm_map_end_place"><?php echo esc_html(MPTBM_Function::get_taxonomy_name_by_slug( $end_place,'locations' )); ?></p>
							<?php }else{ ?>
								<p class="_textLight_1 mptbm_map_end_place"><?php echo esc_html($end_place); ?></p>
							<?php } ?>

						</div>
					<?php endif; ?>
					
					<?php if($price_based != 'manual' && $price_based != 'fixed_hourly'){ ?> 
						<div class="divider"></div>
						<h6 class="_mB_xs"><?php esc_html_e('Total Distance', 'ecab-taxi-booking-manager'); ?></h6>
						<p class="_textLight_1"><?php echo esc_html(isset($_COOKIE['mptbm_distance_text']) ? $_COOKIE['mptbm_distance_text'] : ''); ?></p>
						<div class="divider"></div>
						<h6 class="_mB_xs"><?php esc_html_e('Total Time', 'ecab-taxi-booking-manager'); ?></h6>
						<p class="_textLight_1"><?php echo esc_html(isset($_COOKIE['mptbm_duration_text']) ? $_COOKIE['mptbm_duration_text'] : ''); ?></p>
					<?php } ?>
					
					
					<?php if($two_way>1){ 
						?>
						<div class="divider"></div>
						<h6 class="_mB_xs"><?php esc_html_e('Transfer Type', 'ecab-taxi-booking-manager'); ?></h6>
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
