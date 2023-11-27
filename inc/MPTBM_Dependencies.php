<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('MPTBM_Dependencies')) {
		class MPTBM_Dependencies {
			public function __construct() {
				add_action('init', array($this, 'language_load'));
				$this->load_file();
				add_action('admin_enqueue_scripts', array($this, 'admin_enqueue'), 80);
				add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue'), 80);
				add_action('admin_head', array($this, 'js_constant'), 5);
				add_action('wp_head', array($this, 'js_constant'), 5);
			}
			public function language_load(): void {
				$plugin_dir = basename(dirname(__DIR__)) . "/languages/";
				load_plugin_textdomain('ecab-taxi-booking-manager', false, $plugin_dir);
			}
			private function load_file(): void {
				require_once MPTBM_PLUGIN_DIR . '/inc/MPTBM_Function.php';
				require_once MPTBM_PLUGIN_DIR . '/inc/MPTBM_Query.php';
				require_once MPTBM_PLUGIN_DIR . '/inc/MPTBM_Layout.php';
				require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Admin.php';
				require_once MPTBM_PLUGIN_DIR . '/Frontend/MPTBM_Frontend.php';
			}
			public function global_enqueue() {
				$api_key = MPTBM_Global_Function::get_settings('mptbm_map_api_settings', 'gmap_api_key');
				if ($api_key) {
					wp_enqueue_script('mptbm_map', 'https://maps.googleapis.com/maps/api/js?libraries=places&amp;language=en&amp;key=' . $api_key, array('jquery'), time(), true);
				}
				else {
					add_action('admin_notices', [$this, 'map_api_not_active']);
				}
				do_action('add_mptbm_common_script');
			}
			public function admin_enqueue() {
				$this->global_enqueue();
				// custom
				wp_enqueue_script('mptbm_admin', MPTBM_PLUGIN_URL . '/assets/admin/mptbm_admin.js', array('jquery'), time(), true);
				wp_enqueue_style('mptbm_admin', MPTBM_PLUGIN_URL . '/assets/admin/mptbm_admin.css', array(), time());
				do_action('add_mptbm_admin_script');
			}
			public function frontend_enqueue() {
				$this->global_enqueue();
				wp_enqueue_script('wc-checkout');
				//
				wp_enqueue_style('mptbm_style', MPTBM_PLUGIN_URL . '/assets/frontend/mptbm_style.css', array(), time());
				wp_enqueue_script('mptbm_script', MPTBM_PLUGIN_URL . '/assets/frontend/mptbm_script.js', array('jquery'), time(), true);
				wp_enqueue_script('mptbm_registration', MPTBM_PLUGIN_URL . '/assets/frontend/mptbm_registration.js', array('jquery'), time(), true);
				wp_enqueue_style('mptbm_registration', MPTBM_PLUGIN_URL . '/assets/frontend/mptbm_registration.css', array(), time());
				do_action('add_mptbm_frontend_script');
				
			}
			public function js_constant() {
				?>
				<script type="text/javascript">
					let mp_lat_lng = {
						lat: <?php echo esc_js(MPTBM_Global_Function::get_settings('mptbm_map_api_settings', 'mp_latitude', '23.81234828905659')); ?>,
						lng: <?php echo esc_js(MPTBM_Global_Function::get_settings('mptbm_map_api_settings', 'mp_longitude', '90.41069652669002')); ?>
					};
					const mp_map_options = {
						componentRestrictions: {country: "<?php echo esc_js(MPTBM_Global_Function::get_settings('mptbm_map_api_settings', 'mp_country', 'BD')); ?>"},
						fields: ["address_components", "geometry"],
						types: ["address"],
					}
				</script>
				<?php
			}
			public function map_api_not_active() {
				$gm_api_url = '';
				$label = MPTBM_Function::get_name();
				$text_1=esc_html__('You Must Add Google Map Api key for E-cab taxi booking manager, Because It is dependent on Google Map. Please enter your Google Maps API key in Plugin Options.','');
				$text_2=$label.'>'.$label.' '.esc_html__('Settings>Map Api Settings','');
				$text_4=esc_html__('Click Here to get google api key','');
				printf('<div class="error" style="background:red; color:#fff;"><p>%1$s<strong style="font-size: 17px;">%2$s</strong><a class="btn button" href="%3$s" target="_blank">%4$s</a></p></div>',$text_1,$text_2,$gm_api_url,$text_4);
			}
		}
		new MPTBM_Dependencies();
	}