<?php
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'MPTBM_Dependencies' ) ) {
		class MPTBM_Dependencies {
			public function __construct() {
				add_action( 'init', array( $this, 'language_load' ) );
				$this->load_file();
				add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ), 90 );
				add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue' ), 90 );
				add_action( 'admin_head', array( $this, 'js_constant' ), 5 );
				add_action( 'wp_head', array( $this, 'js_constant' ), 5 );
			}
			public function language_load(): void {
				$plugin_dir = basename( dirname( __DIR__ ) ) . "/languages/";
				load_plugin_textdomain( 'mptbm_plugin', false, $plugin_dir );
			}
			private function load_file(): void {
				require_once MPTBM_PLUGIN_DIR . '/inc/MPTBM_Function.php';
				require_once MPTBM_PLUGIN_DIR . '/inc/MPTBM_Query.php';
				require_once MPTBM_PLUGIN_DIR . '/inc/MPTBM_Layout.php';
				
				require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Admin.php';
				require_once MPTBM_PLUGIN_DIR . '/Frontend/MPTBM_Frontend.php';
			}
			public function global_enqueue() {
				$api_key = MP_Global_Function::get_settings('mptbm_map_api_settings','gmap_api_key');
				if ($api_key) {
					wp_enqueue_script( 'mptbm_map', 'https://maps.googleapis.com/maps/api/js?libraries=places&amp;language=en&amp;key='.$api_key, array( 'jquery' ), time(), true );
				}else{
					if(strpos(home_url($_SERVER['REQUEST_URI']), 'post_type=mptbm_rent&page=mptbm_quick_setup') === false)
					{
						add_action('admin_notices', [$this, 'map_api_not_active']);
					}					
				}
				wp_enqueue_script( 'jquery' );
				wp_enqueue_script( 'jquery-ui-core' );
				wp_enqueue_script( 'jquery-ui-datepicker' );
				wp_localize_script( 'jquery', 'mptbm_ajax', array( 'mptbm_ajax' => admin_url( 'admin-ajax.php' ) ) );
				wp_enqueue_style( 'mp_jquery_ui', MPTBM_PLUGIN_URL . '/assets/helper/jquery-ui.min.css', array(), '1.13.2' );
				wp_enqueue_style( 'mp_font_awesome', '//cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css', array(), '5.15.4' );
				wp_enqueue_style( 'mp_select_2', MPTBM_PLUGIN_URL . '/assets/helper/select_2/select2.min.css', array(), '4.0.13' );
				wp_enqueue_script( 'mp_select_2', MPTBM_PLUGIN_URL . '/assets/helper/select_2/select2.min.js', array(), '4.0.13');
				wp_enqueue_style( 'mp_owl_carousel', MPTBM_PLUGIN_URL . '/assets/helper/owl_carousel/owl.carousel.min.css', array(), '2.3.4' );
				wp_enqueue_script( 'mp_owl_carousel', MPTBM_PLUGIN_URL . '/assets/helper/owl_carousel/owl.carousel.min.js', array(), '2.3.4');
				wp_enqueue_style( 'mp_plugin_global', MPTBM_PLUGIN_URL . '/assets/helper/mp_style/mp_style.css', array(), time() );
				wp_enqueue_script( 'mp_plugin_global', MPTBM_PLUGIN_URL . '/assets/helper/mp_style/mp_script.js', array( 'jquery' ), time(), true );
				do_action( 'add_mptbm_common_script' );
			}
			public function admin_enqueue() {
				$this->global_enqueue();
				wp_enqueue_editor();
				//admin script
				wp_enqueue_script( 'jquery-ui-sortable' );
				wp_enqueue_style( 'wp-color-picker' );
				wp_enqueue_script( 'wp-color-picker' );
				wp_enqueue_style( 'wp-codemirror' );
				wp_enqueue_script( 'wp-codemirror' );
				//********//
				wp_enqueue_script( 'mp_admin_settings', MPTBM_PLUGIN_URL . '/assets/admin/mp_admin_settings.js', array( 'jquery' ), time(), true );
				wp_enqueue_style( 'mp_admin_settings', MPTBM_PLUGIN_URL . '/assets/admin/mp_admin_settings.css', array(), time() );
				// custom
				wp_enqueue_script( 'mptbm_admin', MPTBM_PLUGIN_URL . '/assets/admin/mptbm_admin.js', array( 'jquery' ), time(), true );
				wp_enqueue_style( 'mptbm_admin', MPTBM_PLUGIN_URL . '/assets/admin/mptbm_admin.css', array(), time() );
				do_action( 'add_mptbm_admin_script' );
			}
			public function frontend_enqueue() {
				$this->global_enqueue();
				wp_enqueue_script( 'wc-checkout' );
				wp_enqueue_style( 'mptbm_style', MPTBM_PLUGIN_URL . '/assets/frontend/mptbm_style.css', array(), time() );
				wp_enqueue_script( 'mptbm_script', MPTBM_PLUGIN_URL . '/assets/frontend/mptbm_script.js', array( 'jquery' ), time(), true );
				wp_enqueue_script( 'mptbm_registration', MPTBM_PLUGIN_URL . '/assets/frontend/mptbm_registration.js', array( 'jquery' ), time(), true );
				wp_enqueue_style( 'mptbm_registration', MPTBM_PLUGIN_URL . '/assets/frontend/mptbm_registration.css', array(), time() );

				wp_enqueue_style('mptbm_checkout_front_style', MPTBM_PLUGIN_URL . '/assets/checkout/front/css/mptbm-pro-checkout-front.css', array(), time());
                wp_enqueue_script('mptbm_checkout_front_script', MPTBM_PLUGIN_URL . '/assets/checkout/front/js/mptbm-pro-checkout-front.js', array('jquery'), time(), true);
				
				do_action( 'add_mptbm_frontend_script' );
			}
			public function js_constant() {
				?>
				<script type="text/javascript">
					let mp_ajax_url = "<?php echo admin_url( 'admin-ajax.php' ); ?>";
					let mp_currency_symbol = "<?php echo get_woocommerce_currency_symbol(); ?>";
					let mp_currency_position = "<?php echo get_option( 'woocommerce_currency_pos' ); ?>";
					let mp_currency_decimal = "<?php echo wc_get_price_decimal_separator(); ?>";
					let mp_currency_thousands_separator = "<?php echo wc_get_price_thousand_separator(); ?>";
					let mp_num_of_decimal = "<?php echo get_option( 'woocommerce_price_num_decimals', 2 ); ?>";
					let mp_empty_image_url = "<?php echo esc_attr( MPTBM_PLUGIN_URL . '/assets/helper/images/no_image.png' ); ?>";
					let mp_date_format = "<?php echo esc_attr( MPTBM_Function::get_general_settings( 'date_format', 'D d M , yy' ) ); ?>";
					let mp_lat_lng = {lat: 23.81234828905659, lng: 90.41069652669002};
					const mp_map_options = {
						componentRestrictions: {country: "BD"},
						fields: ["address_components", "geometry"],
						types: ["address"],
					}
				</script>
				<?php
			}
			public function map_api_not_active(){
				//$gm_api_url = 'https://developers.google.com/maps/documentation/javascript/get-api-key';
				$gm_api_url = admin_url('edit.php?post_type=mptbm_rent&page=mptbm_settings_page#mptbm_map_api_settings');
				$label=MPTBM_Function::get_name();
				printf('<div class="error" style="background:red; color:#fff;"><p>%s</p></div>', __('You Must Add Google Map Api key for E-cab taxi booking manager, Because It is dependent on Google Map. Please enter your Google Maps API key in Plugin Options.<strong style="font-size: 17px;">'.$label.'>'.$label.' Settings>Map Api Settings</strong> <a class="btn button" href=' . $gm_api_url . ' target="_blank">Click Here to get google api key</a>'));
			}
		}
		new MPTBM_Dependencies();
	}