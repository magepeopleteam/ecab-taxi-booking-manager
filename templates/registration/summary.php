<?php
	if (!defined('ABSPATH')) {
		exit;
	}
	$distance = $distance ?? $_COOKIE['mptbm_distance'];
	$duration = $duration ?? $_COOKIE['mptbm_duration'];
	$label = $label ?? MPTBM_Function::get_name();
	$extra_services = $extra_services ?? get_option('mptbm_extra_services');
	$display_extra_services = $display_extra_services ?? get_option('display_mptbm_extra_services', 'on');
?>
	<div class="mp_sticky_area">
		<div class="_dLayout_dShadow_7_bRL_dFlex_fdColumn">
			<h3 class="_textCenter"><?php esc_html_e('SUMMERY', 'mptbm_plugin'); ?></h3>
			<div class="dividerL"></div>
			<h6 class="_mB_xs"><?php esc_html_e('Pick-Up Date', 'mptbm_plugin'); ?></h6>
			<p class="_textLight_1"><?php echo MP_Global_Function::date_format($_POST['start_date']); ?></p>
			<div class="dividerL"></div>
			<h6 class="_mB_xs"><?php esc_html_e('Pick-Up Time', 'mptbm_plugin'); ?></h6>
			<p class="_textLight_1"><?php echo MP_Global_Function::date_format($_POST['start_time'], 'time'); ?></p>
			<div class="dividerL"></div>
			<h6 class="_mB_xs"><?php esc_html_e('Pick-Up Location', 'mptbm_plugin'); ?></h6>
			<p class="_textLight_1"><?php echo MP_Global_Function::data_sanitize($_POST['start_place']); ?></p>
			<div class="dividerL"></div>
			<h6 class="_mB_xs"><?php esc_html_e('Drop-Off Location', 'mptbm_plugin'); ?></h6>
			<p class="_textLight_1"><?php echo MP_Global_Function::data_sanitize($_POST['end_place']); ?></p>
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
				<?php
					if ($display_extra_services == 'on' && is_array($extra_services) && sizeof($extra_services) > 0) {
						foreach ($extra_services as $service) { ?><?php
							$service_name = array_key_exists('service_name', $service) ? $service['service_name'] : '';
							$service_price = array_key_exists('service_price', $service) ? $service['service_price'] : 0;
							if ($service_name) {
								?>
								<div data-extra-service="<?php echo esc_attr($service_name); ?>">
									<div class="_textLight_1_dFlex_flexWrap_justifyBetween">
										<div class="_dFlex_alignCenter">
											<span class="fas fa-check-square _textTheme_mR_xs"></span>
											<span><?php echo esc_html($service_name); ?></span>
										</div>
										<p>
											<span class="textTheme ex_service_qty">x1</span>&nbsp;|&nbsp; <span class="textTheme"><?php echo wc_price($service_price); ?></span>
										</p>
									</div>
								</div>
								<?php
							}
						}
					}
				?>
				<div class="dividerL"></div>
				<div class="justifyBetween">
					<h4><?php esc_html_e('Total : ', 'mptbm_plugin'); ?></h4>
					<h6 class="mptbm_product_total_price"></h6>
				</div>
			</div>
		</div>
	</div>
<?php
