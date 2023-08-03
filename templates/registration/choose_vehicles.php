<?php
	/*
 * @Author 		engr.sumonazma@gmail.com
 * Copyright: 	mage-people.com
 */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly
	
	$label = MPTBM_Function::get_name();
	$start_date = MP_Global_Function::data_sanitize($_POST['start_date']);
	$start_time = MP_Global_Function::data_sanitize($_POST['start_time']);
	$date = $start_date . ' ' . $start_time;
	$start_place = MP_Global_Function::data_sanitize($_POST['start_place']);
	$end_place = MP_Global_Function::data_sanitize($_POST['end_place']);
?>
	<input type="hidden" name="mptbm_post_id" value="" data-price=""/>
	<input type="hidden" name="mptbm_start_place" value="<?php echo esc_attr($start_place); ?>"/>
	<input type="hidden" name="mptbm_end_place" value="<?php echo esc_attr($end_place); ?>"/>
	<input type="hidden" name="mptbm_date" value="<?php echo esc_attr($date); ?>"/>
	<div class="mp_sticky_section">
		<div class="flexWrap">
			<div class="leftSidebar">
			<?php include(MPTBM_Function::template_path('registration/summary.php')); ?>
			</div>
			<div class="mainSection ">
				<div class="mp_sticky_depend_area fdColumn">
					<?php
						$price_based = MP_Global_Function::data_sanitize($_POST['price_based']);
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
								<h3><?php esc_html_e('NO Transport Available !', 'mptbm_plugin'); ?></h3>
							</div>
						<?php } ?>
					<div class="mptbm_extra_service"></div>
				</div>
			</div>
		</div>
	</div>
<?php
