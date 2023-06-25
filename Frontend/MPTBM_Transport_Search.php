<?php
	/*
 * @Author 		engr.sumonazma@gmail.com
 * Copyright: 	mage-people.com
 */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('MPTBM_Transport_Search')) {
		class MPTBM_Transport_Search {
			public function __construct() {
				add_action('mptbm_transport_search', [$this, 'transport_search'], 10, 1);
				add_action('mptbm_transport_search_form', [$this, 'transport_search_form'], 10, 2);
				add_action('wp_ajax_get_mptbm_map_search_result', [$this, 'get_mptbm_map_search_result']);
				add_action('wp_ajax_nopriv_get_mptbm_map_search_result', [$this, 'get_mptbm_map_search_result']);
				add_action('wp_ajax_get_mptbm_end_place', [$this, 'get_mptbm_end_place']);
				add_action('wp_ajax_nopriv_get_mptbm_end_place', [$this, 'get_mptbm_end_place']);
				add_action('wp_ajax_get_mptbm_extra_service', [$this, 'get_mptbm_extra_service']);
				add_action('wp_ajax_nopriv_get_mptbm_extra_service', [$this, 'get_mptbm_extra_service']);
				add_action('wp_ajax_get_mptbm_extra_service_summary', [$this, 'get_mptbm_extra_service_summary']);
				add_action('wp_ajax_nopriv_get_mptbm_extra_service_summary', [$this, 'get_mptbm_extra_service_summary']);
			}
			public function transport_search($params) {
				$price_based = $params['price_based'] ?: 'distance';
				echo do_shortcode('[shop_messages]');
				$this->transport_search_form('', $price_based);
			}
			public function transport_search_form($post_id = '', $price_based = '') {
				?>
				<div class="mpStyle mptbm_transport_search_area">
					<form method="post" action="">
						<?php include(MPTBM_Function::template_path('registration/registration_layout.php')); ?>
					</form>
				</div>
				<?php
			}
			public function get_mptbm_map_search_result() {
				$distance = $_COOKIE['mptbm_distance'] ?? '';
				$duration = $_COOKIE['mptbm_duration'] ?? '';
				$label = MPTBM_Function::get_name();
				$start_date = MP_Global_Function::data_sanitize($_POST['start_date']);
				$start_time = MP_Global_Function::data_sanitize($_POST['start_time']);
				$date = $start_date . ' ' . $start_time;
				$start_place = MP_Global_Function::data_sanitize($_POST['start_place']);
				$end_place = MP_Global_Function::data_sanitize($_POST['end_place']);
				if ($distance && $duration) {
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
									<?php include(MPTBM_Function::template_path('registration/get_vehicles.php')); ?>
									<?php //include(MPTBM_Function::template_path('registration/extra_service.php')); ?>
									<div class="mptbm_extra_service"></div>
								</div>
							</div>
						</div>
					</div>
					<?php
				}
				die();
			}
			public function get_mptbm_end_place() {
				include(MPTBM_Function::template_path('registration/get_end_place.php'));
				die();
			}
			public function get_mptbm_extra_service() {
				include(MPTBM_Function::template_path('registration/extra_service.php'));
				die();
			}
			public function get_mptbm_extra_service_summary() {
				include(MPTBM_Function::template_path('registration/extra_service_summary.php'));
				die();
			}
		}
		new MPTBM_Transport_Search();
	}