<?php
if (!function_exists('mptbm_get_translation')) {
	require_once dirname(__DIR__, 2) . '/inc/mptbm-translation-helper.php';
}
/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
if (!defined('ABSPATH')) {
	exit;
}
$progressbar = $progressbar ?? 'yes';
$progressbar_class = $progressbar == 'yes' ? '' : 'dNone';
?>
<div class="mpStyle mptbm_transport_search_area">
	<div class="mpTabsNext _mT">
		<div class="tabListsNext <?php echo esc_attr($progressbar_class); ?>">
			<div data-tabs-target-next="#mptbm_pick_up_details" class="tabItemNext active" data-open-text="1" data-close-text=" " data-open-icon="" data-close-icon="fas fa-check" data-add-class="success">
				<h4 class="circleIcon" data-class>
					<span class="mp_zero" data-icon></span>
					<span class="mp_zero" data-text>1</span>
				</h4>
				<h6 class="circleTitle" data-class><?php echo mptbm_get_translation('enter_ride_details_label', __('Enter Ride Details', 'ecab-taxi-booking-manager')); ?></h6>
			</div>
			<div data-tabs-target-next="#mptbm_search_result" class="tabItemNext" data-open-text="2" data-close-text="" data-open-icon="" data-close-icon="fas fa-check" data-add-class="success">
				<h4 class="circleIcon" data-class>
					<span class="mp_zero" data-icon></span>
					<span class="mp_zero" data-text>2</span>
				</h4>
				<h6 class="circleTitle" data-class><?php echo mptbm_get_translation('choose_a_vehicle_label', __('Choose a vehicle', 'ecab-taxi-booking-manager')); ?></h6>
			</div>
			<div data-tabs-target-next="#mptbm_order_summary" class="tabItemNext" data-open-text="3" data-close-text="" data-open-icon="" data-close-icon="fas fa-check" data-add-class="success">
				<h4 class="circleIcon" data-class>
					<span class="mp_zero" data-icon></span>
					<span class="mp_zero" data-text>3</span>
				</h4>
				<h6 class="circleTitle" data-class><?php echo mptbm_get_translation('place_order_label', __('Place Order', 'ecab-taxi-booking-manager')); ?></h6>
			</div>
		</div>
		<div class="tabsContentNext">
			<div data-tabs-next="#mptbm_pick_up_details" class="active mptbm_pick_up_details">
				<?php
				if ($tab == 'yes') {
					$tabs_array = explode(',', $tabs);
					$valid_tabs = ['distance' => 'distance', 'hourly' => 'hourly', 'manual' => 'flat-rate']; // Mapping to correct tab names
					$available_tabs = array_intersect_key($valid_tabs, array_flip($tabs_array)); // Filter valid tabs
					$first_tab = key($available_tabs); 
					if($first_tab == 'hourly'){
						$price_based = 'fixed_hourly';
					}else if($first_tab == 'manual'){
						$price_based = 'manual';
						$form_style = 'inline';
					}
				?>
					<div class="mptb-tab-container">
						<ul class="mptb-tabs">
							<?php foreach ($available_tabs as $key => $tab_name) { ?>
								<li class="tab-link <?php echo ($key === $first_tab) ? 'current' : ''; ?>" mptbm-data-tab="<?php echo $tab_name; ?>">
									<?php
									if ($tab_name === 'distance') {
										echo mptbm_get_translation('distance_tab_label', __('Distance', 'ecab-taxi-booking-manager'));
									} elseif ($tab_name === 'hourly') {
										echo mptbm_get_translation('hourly_tab_label', __('Hourly', 'ecab-taxi-booking-manager'));
									} elseif ($tab_name === 'flat-rate') {
										echo mptbm_get_translation('flat_rate_tab_label', __('Flat rate', 'ecab-taxi-booking-manager'));
									} else {
										echo ucfirst(str_replace('-', ' ', $tab_name));
									}
									?>
								</li>
							<?php } ?>
						</ul>

						<?php foreach ($available_tabs as $key => $tab_name) { ?>
							<div id="<?php echo $tab_name; ?>" class="mptb-tab-content <?php echo ($key === $first_tab) ? 'current' : ''; ?>">
								<?php if ($key === $first_tab) { ?>
									<?php include MPTBM_Function::template_path('registration/get_details.php'); ?>
								<?php } ?>
							</div>
						<?php } ?>
						<div class="mptbm-hide-gif mptbm-gif">
							<img src="<?php echo plugin_dir_url(dirname(__DIR__)) . 'assets/images/loader.gif'; ?>" class="mptb-tabs-loader" />
						</div>
					</div>
				<?php } else { ?>
					<div data-tabs-next="#mptbm_pick_up_details" class="active mptbm_pick_up_details">
						<?php include MPTBM_Function::template_path('registration/get_details.php'); ?>
					</div>
				<?php } ?>
			</div>
		</div>
	</div>
</div>
<?php
