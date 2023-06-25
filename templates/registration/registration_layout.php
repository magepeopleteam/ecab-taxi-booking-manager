<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		exit;
	}
?>
	<div class="mpTabsNext _mT">
		<div class="tabListsNext">
			<div data-tabs-target-next="#mptbm_pick_up_details" class="tabItemNext active" data-open-text="1" data-close-text=" " data-open-icon="" data-close-icon="fas fa-check" data-add-class="success">
				<h4 class="circleIcon" data-class>
					<span class="mp_zero" data-icon></span>
					<span class="mp_zero" data-text>1</span>
				</h4>
				<h6 class="circleTitle" data-class><?php esc_html_e('Enter Ride Details', 'mptbm_plugin'); ?></h6>
			</div>
			<div data-tabs-target-next="#mptbm_search_result" class="tabItemNext" data-open-text="2" data-close-text="" data-open-icon="" data-close-icon="fas fa-check" data-add-class="success">
				<h4 class="circleIcon" data-class>
					<span class="mp_zero" data-icon></span>
					<span class="mp_zero" data-text>2</span>
				</h4>
				<h6 class="circleTitle" data-class><?php esc_html_e('Choose a vehicle', 'mptbm_plugin'); ?></h6>
			</div>
			<div data-tabs-target-next="#mptbm_booking_cart" class="tabItemNext" data-open-text="3" data-close-text="" data-open-icon="" data-close-icon="fas fa-check" data-add-class="success">
				<h4 class="circleIcon" data-class>
					<span class="mp_zero" data-icon></span>
					<span class="mp_zero" data-text>3</span>
				</h4>
				<h6 class="circleTitle" data-class><?php esc_html_e('Booking Cart', 'mptbm_plugin'); ?></h6>
			</div>
		</div>
		<div class="tabsContentNext">
			<div data-tabs-next="#mptbm_pick_up_details" class="active">
				<?php include(MPTBM_Function::template_path('registration/get_details.php')); ?>
			</div>
			<div data-tabs-next="#mptbm_search_result">
				<div class="mptbm_map_search_result"></div>
			</div>
			<div data-tabs-next="#mptbm_booking_cart"></div>
		</div>
		<div class="dNone">
			<button type="button" class="mpBtn nextTab_prev">
				<span>&longleftarrow;<?php esc_html_e('Previous', 'mptbm_plugin'); ?></span>
			</button>
			<div></div>
			<button type="button" class="themeButton nextTab_next">
				<span><?php esc_html_e('Next', 'mptbm_plugin'); ?>&longrightarrow;</span>
			</button>
		</div>
	</div>
<?php
