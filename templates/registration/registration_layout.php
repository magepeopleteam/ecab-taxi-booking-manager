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
	<div class="mpTabsNext">
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

					$valid_tabs = [
						'distance' => 'distance',
						'hourly'   => 'hourly',
						'manual'   => 'flat-rate',
					];
					$valid_tabs = apply_filters('mptbm_register_valid_tabs', $valid_tabs);
					

					// ✅ Build available_tabs in the order of $tabs_array
					$available_tabs = [];
					foreach ($tabs_array as $tab_key) {
						$tab_key = trim($tab_key); // In case of whitespace
						if (isset($valid_tabs[$tab_key])) {
							$available_tabs[$tab_key] = $valid_tabs[$tab_key];
						}
					}

					                    // Set form_style to 'inline' only for manual tab
                    if (array_key_exists('manual', $available_tabs)) {
                        // We'll handle this dynamically in JavaScript
                        $manual_form_style = 'inline';
                    }

					

					

					$first_tab = key($available_tabs);
                    $original_form_style = $form_style ?: 'horizontal';
					$map = $map ?: 'yes';

					if ($first_tab == 'hourly') {
						$price_based = 'fixed_hourly';
					} else if ($first_tab == 'manual') {
						$price_based = 'manual';
					}
					
				?>
					<div class="mptb-tab-container">
					<ul class="mptb-tabs">
						<?php foreach ($available_tabs as $key => $tab_name) { 
							$tab_form_style = ($tab_name === 'flat-rate') ? 'inline' : $original_form_style;
						?>
							<li class="tab-link <?php echo ($key === $first_tab) ? 'current' : ''; ?>"
								mptbm-data-tab="<?php echo $tab_name; ?>"
								mptbm-data-map="<?php echo $map; ?>"
								mptbm-data-form-style="<?php echo $tab_form_style; ?>">

								<?php
								$label = '';
								if ($tab_name === 'distance') {
									$label = mptbm_get_translation('distance_tab_label', __('Distance', 'ecab-taxi-booking-manager'));
								} elseif ($tab_name === 'hourly') {
									$label = mptbm_get_translation('hourly_tab_label', __('Hourly', 'ecab-taxi-booking-manager'));
								} elseif ($tab_name === 'flat-rate') {
									
									$label = mptbm_get_translation('flat_rate_tab_label', __('Flat rate', 'ecab-taxi-booking-manager'));
								}else {
									$label = ucfirst(str_replace('-', ' ', $tab_name));
								}

								// Apply a dynamic filter for external customization
								echo apply_filters("mptbm_tab_label_{$tab_name}", $label, $tab_name);
								?>
							</li>
						<?php } ?>
					</ul>
						
						<?php foreach ($available_tabs as $key => $tab_name) { ?>
							<div id="<?php echo $tab_name; ?>" class="mptb-tab-content <?php echo ($key === $first_tab) ? 'current' : ''; ?>">
							
								<?php if ($key === $first_tab && $first_tab != 'custom') { 
									$current_form_style = ($tab_name === 'flat-rate') ? 'inline' : $original_form_style;
									$form_style = $current_form_style;
								?>
										
										<?php include MPTBM_Function::template_path('registration/get_details.php'); ?>
									<?php } else { ?>
										
										<?php do_action('mptbm_render_' . $tab_name); ?>
									<?php } ?>
							</div>
							
						<?php } ?>
						<div class="mptbm-hide-gif mptbm-gif">
							<img src="<?php echo plugin_dir_url(dirname(__DIR__)) . 'assets/images/loader.gif'; ?>" class="mptb-tabs-loader" />
						</div>
						
						<script>
						jQuery(document).ready(function($) {
							// Handle form style changes when tabs are clicked
							$('.mptb-tab-link').on('click', function() {
								var tabName = $(this).attr('mptbm-data-tab');
								var currentFormStyle = $(this).attr('mptbm-data-form-style');
								
								if (tabName === 'flat-rate') {
									// Set inline style for manual tab
									$(this).attr('mptbm-data-form-style', 'inline');
								} else {
									// Reset to original form style for other tabs
									$(this).attr('mptbm-data-form-style', '<?php echo $original_form_style; ?>');
								}
							});
						});
						</script>
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
