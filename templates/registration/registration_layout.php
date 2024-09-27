<?php
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
					<h6 class="circleTitle" data-class><?php esc_html_e('Enter Ride Details', 'ecab-taxi-booking-manager'); ?></h6>
				</div>
				<div data-tabs-target-next="#mptbm_search_result" class="tabItemNext" data-open-text="2" data-close-text="" data-open-icon="" data-close-icon="fas fa-check" data-add-class="success">
					<h4 class="circleIcon" data-class>
						<span class="mp_zero" data-icon></span>
						<span class="mp_zero" data-text>2</span>
					</h4>
					<h6 class="circleTitle" data-class><?php esc_html_e('Choose a vehicle', 'ecab-taxi-booking-manager'); ?></h6>
				</div>
				<div data-tabs-target-next="#mptbm_order_summary" class="tabItemNext" data-open-text="3" data-close-text="" data-open-icon="" data-close-icon="fas fa-check" data-add-class="success">
					<h4 class="circleIcon" data-class>
						<span class="mp_zero" data-icon></span>
						<span class="mp_zero" data-text>3</span>
					</h4>
					<h6 class="circleTitle" data-class><?php esc_html_e('Place Order', 'ecab-taxi-booking-manager'); ?></h6>
				</div>
			</div>
			<div class="tabsContentNext">
				<div data-tabs-next="#mptbm_pick_up_details" class="active mptbm_pick_up_details">
                    <?php //echo MPTBM_Function::template_path('registration/get_details.php'); ?>
					<?php include MPTBM_Function::template_path('registration/get_details.php'); ?>
				</div>
			</div>
		</div>
	</div>
<?php
