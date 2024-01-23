<?php
	/*
 * @Author 		engr.sumonazma@gmail.com
 * Copyright: 	mage-people.com
 */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly
	$label = MPTBM_Function::get_name();
	$start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
	$start_time = isset($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) * 3600 : '';
	$date = $start_date && $start_time ? date('Y-m-d', strtotime($start_date)) . ' ' . date('H:i', $start_time) : '';
	$start_place = isset($_POST['start_place']) ? sanitize_text_field($_POST['start_place']) : '';
	$end_place = isset($_POST['end_place']) ? sanitize_text_field($_POST['end_place']) : '';
	$two_way = isset($_POST['two_way']) ? absint($_POST['two_way']) : 1;
	$waiting_time = isset($_POST['waiting_time']) ? sanitize_text_field($_POST['waiting_time']) : 0;
	$fixed_time = isset($_POST['fixed_time']) ? sanitize_text_field($_POST['fixed_time']) : '';
?>
	<div data-tabs-next="#mptbm_search_result" class="mptbm_map_search_result">
		<input type="hidden" name="mptbm_post_id" value="" data-price=""/>
		<input type="hidden" name="mptbm_start_place" value="<?php echo esc_attr($start_place); ?>"/>
		<input type="hidden" name="mptbm_end_place" value="<?php echo esc_attr($end_place); ?>"/>
		<input type="hidden" name="mptbm_date" value="<?php echo esc_attr($date); ?>"/>
		<input type="hidden" name="mptbm_taxi_return" value="<?php echo esc_attr($two_way); ?>"/>
		<input type="hidden" name="mptbm_waiting_time" value="<?php echo esc_attr($waiting_time); ?>"/>
		<input type="hidden" name="mptbm_fixed_hours" value="<?php echo esc_attr($fixed_time); ?>"/>
		<div class="mp_sticky_section">
			<div class="flexWrap">
				<?php include(MPTBM_Function::template_path('registration/summary.php')); ?>
				<div class="mainSection ">
					<div class="mp_sticky_depend_area fdColumn">
						<?php
							$price_based = sanitize_text_field($_POST['price_based']);
							$all_posts = MPTBM_Query::query_transport_list($price_based);
							if ($all_posts->found_posts > 0) {
								$posts = $all_posts->posts;
								foreach ($posts as $post) {
									$post_id = $post->ID;
									include(MPTBM_Function::template_path('registration/vehicle_item.php'));
								}
							}
							else {
								?>
								<div class="_dLayout_mT_bgWarning" data-placeholder>
									<h3><?php esc_html_e('No Transport Available !', 'ecab-taxi-booking-manager'); ?></h3>
								</div>
							<?php } ?>
						<div class="mptbm_extra_service"></div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div data-tabs-next="#mptbm_order_summary" class="mptbm_order_summary">
		<div class="mp_sticky_section">
			<div class="flexWrap">
				<?php include(MPTBM_Function::template_path('registration/summary.php')); ?>
				<div class="mainSection ">
					<div class="mp_sticky_depend_area fdColumn mptbm_checkout_area">
					</div>
				</div>
			</div>
		</div>
	</div>
<?php
