<?php
	/*
 * @Author 		engr.sumonazma@gmail.com
 * Copyright: 	mage-people.com
 */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly
	$distance = $distance ?? $_COOKIE['mptbm_distance'];
	$duration = $duration ?? $_COOKIE['mptbm_duration'];
	$label = $label ?? MPTBM_Function::get_name();
	$date = $date ?? '';
	$start_place = $start_place ?? '';
	$end_place = $end_place ?? '';
?>
	<div class="leftSidebar">
		<div class="mp_sticky_area">
			<div class="mp_sticky_on_scroll">
				<div class="_dLayout_dFlex_fdColumn">
					<h3><?php esc_html_e('SUMMERY', 'mptbm_plugin'); ?></h3>
					<div class="dividerL"></div>
					<h6 class="_mB_xs"><?php esc_html_e('Pick-Up Date', 'mptbm_plugin'); ?></h6>
					<p class="_textLight_1"><?php echo MP_Global_Function::date_format($date); ?></p>
					<div class="dividerL"></div>
					<h6 class="_mB_xs"><?php esc_html_e('Pick-Up Time', 'mptbm_plugin'); ?></h6>
					<p class="_textLight_1"><?php echo MP_Global_Function::date_format($date, 'time'); ?></p>
					<div class="dividerL"></div>
					<h6 class="_mB_xs"><?php esc_html_e('Pick-Up Location', 'mptbm_plugin'); ?></h6>
					<p class="_textLight_1"><?php echo esc_html($start_place); ?></p>
					<div class="dividerL"></div>
					<h6 class="_mB_xs"><?php esc_html_e('Drop-Off Location', 'mptbm_plugin'); ?></h6>
					<p class="_textLight_1"><?php echo esc_html($end_place); ?></p>
					<div class="mptbm_transport_summary">
						<div class="dividerL"></div>
						<h6 class="_mB_xs"><?php echo esc_html($label) . ' ' . esc_html__(' Details', 'mptbm_plugin') ?></h6>
						<div class="_textLight_1 justifyBetween">
							<div class="_dFlex_alignCenter">
								<span class="fas fa-check-square _textTheme_mR_xs"></span>
								<span class="mptbm_product_name"></span>
							</div>
							<span class="mptbm_product_price _textTheme"></span>
						</div>
						<div class="mptbm_extra_service_summary"></div>
						<div class="dividerL"></div>
						<div class="justifyBetween">
							<h4><?php esc_html_e('Total : ', 'mptbm_plugin'); ?></h4>
							<h6 class="mptbm_product_total_price"></h6>
						</div>
					</div>
				</div>
				<div class="divider"></div>
				<button type="button" class="_mpBtn_fullWidth mptbm_get_vehicle_prev">
					<span>&longleftarrow; &nbsp;<?php esc_html_e('Previous', 'mptbm_plugin'); ?></span>
				</button>
			</div>
		</div>
	</div>
<?php
