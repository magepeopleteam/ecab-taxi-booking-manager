<?php
	/*
* @Author 		magePeople
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly
	$post_id = absint($_POST['post_id']);
	if ($post_id && $post_id > 0) {
		$display_extra_services = MP_Global_Function::get_post_info($post_id, 'display_mptbm_extra_services', 'on');
		$service_id = MP_Global_Function::get_post_info($post_id, 'mptbm_extra_services_id', $post_id);
		$extra_services = MP_Global_Function::get_post_info($service_id, 'mptbm_extra_service_infos', []);
		if ($display_extra_services == 'on' && is_array($extra_services) && sizeof($extra_services) > 0) {
			foreach ($extra_services as $service) { ?><?php
				$service_name = array_key_exists('service_name', $service) ? $service['service_name'] : '';
				if ($service_name) {
					$service_price = array_key_exists('service_price', $service) ? $service['service_price'] : 0;
					$wc_price = MP_Global_Function::wc_price($post_id, $service_price);
					$service_price = MP_Global_Function::price_convert_raw($wc_price);
					?>
					<div data-extra-service="<?php echo esc_attr($service_name); ?>">
						<div class="_textLight_1_dFlex_flexWrap_justifyBetween">
							<div class="_dFlex_alignCenter">
								<span class="fas fa-check-square _textTheme_mR_xs"></span>
								<span><?php echo esc_html($service_name); ?></span>
							</div>
							<p>
								<span class="textTheme ex_service_qty">x1</span>&nbsp;|&nbsp;
								<span class="textTheme"><?php echo wp_kses_post(wc_price($service_price)); ?></span>
							</p>
						</div>
					</div>
					<?php
				}
			}
		}
	}