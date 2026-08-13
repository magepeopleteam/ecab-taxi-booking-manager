<?php
/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.
if (!class_exists('MPTBM_Settings_Global')) {
	class MPTBM_Settings_Global
	{
		
		protected $settings_api;
		public function __construct()
		{
			$this->settings_api = new MAGE_Setting_API;
			add_action('admin_menu', array($this, 'global_settings_menu'));
			add_action('admin_init', array($this, 'admin_init'));
			add_action('admin_enqueue_scripts', array($this, 'enqueue_settings_assets'));
			add_filter('mp_settings_sec_reg', array($this, 'settings_sec_reg'), 10);
			add_filter('mp_settings_sec_fields', array($this, 'settings_sec_fields'), 10);
			add_filter('filter_mp_global_settings', array($this, 'global_taxi'), 10);
		}
		public function enqueue_settings_assets($hook_suffix)
		{
			if (strpos((string) $hook_suffix, 'mptbm_settings_page') === false) {
				return;
			}

			$css_relative = 'assets/admin/mptbm_global_settings.css';
			$js_relative = 'assets/admin/mptbm_global_settings.js';
			$css_path = MPTBM_PLUGIN_DIR . '/' . $css_relative;
			$js_path = MPTBM_PLUGIN_DIR . '/' . $js_relative;

			wp_enqueue_style(
				'mptbm-global-settings',
				MPTBM_PLUGIN_URL . '/' . $css_relative,
				array('mptbm-shell'),
				file_exists($css_path) ? filemtime($css_path) : MPTBM_PLUGIN_VERSION
			);
			wp_enqueue_script(
				'mptbm-global-settings',
				MPTBM_PLUGIN_URL . '/' . $js_relative,
				array('jquery'),
				file_exists($js_path) ? filemtime($js_path) : MPTBM_PLUGIN_VERSION,
				true
			);
			wp_localize_script('mptbm-global-settings', 'mptbmGlobalSettings', array(
				'sectionDescription' => esc_html__('Review and update the options in this configuration area.', 'ecab-taxi-booking-manager'),
				'saveHint'           => esc_html__('Review your changes before saving.', 'ecab-taxi-booking-manager'),
				'unsaved'            => esc_html__('Unsaved changes', 'ecab-taxi-booking-manager'),
				'saving'             => esc_html__('Saving settings…', 'ecab-taxi-booking-manager'),
			));
		}
		public function global_settings_menu()
		{
			$cpt = MPTBM_Function::get_cpt();
			add_submenu_page('edit.php?post_type=' . $cpt, esc_html__('Settings', 'ecab-taxi-booking-manager'), esc_html__('Settings', 'ecab-taxi-booking-manager'), 'manage_options', 'mptbm_settings_page', array($this, 'settings_page'));
		}
		public function settings_page()
		{
			$sections = $this->get_settings_sections();
			$section_count = count($sections);
			$guideline_url = admin_url('edit.php?post_type=' . MPTBM_Function::get_cpt() . '&page=mptbm_guideline_page');
			MPTBM_Admin_Shell::render_shell_open();
?>
			<div class="mpStyle mp_global_settings mptbm-modern-global-settings">
				<header class="mptbm-global-settings-hero">
					<div class="mptbm-global-settings-hero-copy">
						<span class="mptbm-global-settings-hero-icon" aria-hidden="true">
							<i class="fas fa-sliders-h"></i>
						</span>
						<div>
							<span class="mptbm-global-settings-eyebrow"><?php esc_html_e('System configuration', 'ecab-taxi-booking-manager'); ?></span>
							<h1><?php esc_html_e('Settings', 'ecab-taxi-booking-manager'); ?></h1>
							<p><?php esc_html_e('Manage booking behavior, maps, payments, currency, and integrations from one organized workspace.', 'ecab-taxi-booking-manager'); ?></p>
						</div>
					</div>
					<div class="mptbm-global-settings-hero-actions">
						<span class="mptbm-global-settings-section-pill">
							<i class="fas fa-layer-group" aria-hidden="true"></i>
							<?php
							echo esc_html(
								sprintf(
									_n('%d configuration area', '%d configuration areas', $section_count, 'ecab-taxi-booking-manager'),
									$section_count
								)
							);
							?>
						</span>
						<a class="mptbm-global-settings-help" href="<?php echo esc_url($guideline_url); ?>">
							<i class="far fa-life-ring" aria-hidden="true"></i>
							<?php esc_html_e('View guideline', 'ecab-taxi-booking-manager'); ?>
						</a>
					</div>
				</header>

				<?php if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') : ?>
					<div class="mptbm-settings-saved-banner" role="status">
						<i class="fas fa-check-circle" aria-hidden="true"></i>
						<span><?php esc_html_e('Settings saved successfully.', 'ecab-taxi-booking-manager'); ?></span>
					</div>
				<?php endif; ?>

				<div class="mptbm-global-settings-toolbar">
					<div class="mptbm-global-settings-toolbar-meta">
						<span class="mptbm-global-settings-current-section"></span>
						<span class="mptbm-global-settings-change-status" aria-live="polite"></span>
					</div>
				</div>

				<div class="mpPanel">
					<div class="mpPanelBody mp_zero">
						<div class="mpTabs leftTabs">
							<aside class="mptbm-global-settings-nav">
								<div class="mptbm-global-settings-nav-heading">
									<span><?php esc_html_e('Configuration', 'ecab-taxi-booking-manager'); ?></span>
									<small><?php esc_html_e('Choose an area to manage', 'ecab-taxi-booking-manager'); ?></small>
								</div>
								<?php $this->settings_api->show_navigation(); ?>
								<div class="mptbm-global-settings-nav-note">
									<i class="fas fa-shield-alt" aria-hidden="true"></i>
									<span><?php esc_html_e('Settings are protected by WordPress permissions and validation.', 'ecab-taxi-booking-manager'); ?></span>
								</div>
							</aside>
							<div class="tabsContent">
								<?php $this->settings_api->show_forms(); ?>
							</div>
						</div>
					</div>
				</div>
			</div>
<?php
			MPTBM_Admin_Shell::render_shell_close();
		}

		public function admin_init()
		{
			$this->settings_api->set_sections($this->get_settings_sections());
			$this->settings_api->set_fields($this->get_settings_fields());
			$this->settings_api->admin_init();
		}
		public function get_settings_sections()
		{
			$sections = array();
			return apply_filters('mp_settings_sec_reg', $sections);
		}
		public function get_settings_fields()
		{
			$settings_fields = array();
			return apply_filters('mp_settings_sec_fields', $settings_fields);
		}
		public function settings_sec_reg($default_sec): array
		{
			$label = MPTBM_Function::get_name();
			$sections = array(
				array(
					'id' => 'mptbm_map_api_settings',
					'icon' => 'fa fa-map',
					'title' => esc_html__('Map API Settings', 'ecab-taxi-booking-manager')
				),
				array(
					'id' => 'mptbm_general_settings',
					'icon' => 'fas fa-car-alt',
					'title' => $label . ' ' . esc_html__('Settings', 'ecab-taxi-booking-manager')
				),
			array(
				'id' => 'mptbm_rest_api_settings',
				'icon' => 'fas fa-code',
				'title' => esc_html__('REST API Settings', 'ecab-taxi-booking-manager')
			),
			
		);
			
			// Add QR Code Settings section only if QR Addon class exists
			if (class_exists('Ecab_Taxi_Booking_QR_Addon')) {
				$sections[] = array(
					'id' => 'mptbm_qr_settings',
					'icon' => 'fas fa-qrcode',
					'title' => esc_html__('QR Code Settings', 'ecab-taxi-booking-manager')
				);
			}
			
			return array_merge($default_sec, $sections);
		}
		public function settings_sec_fields($default_fields): array
		{
			$gm_api_url = 'https://developers.google.com/maps/documentation/javascript/get-api-key';
			$label = MPTBM_Function::get_name();

			
			

			$settings_fields = array(
				'mptbm_general_settings' => apply_filters('filter_mptbm_general_settings', array(
					array(
						'name' => 'transfer_type',
						'label' => esc_html__('Disable/Enable Transfer Type', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('If you want to disable Transfer Type, please select disable. default enable', 'ecab-taxi-booking-manager'),
						'type' => 'select',
						'default' => 'enable',
						'options' => array(
							'enable' => esc_html__('Enable', 'ecab-taxi-booking-manager'),
							'disable' => esc_html__('Disable', 'ecab-taxi-booking-manager')
						)
					),
					array(
						'name' => 'taxi_return',
						'label' => esc_html__('Disable/ Enable Taxi Return', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('If you want to disable taxi return, please select disable. default enable', 'ecab-taxi-booking-manager'),
						'type' => 'select',
						'default' => 'enable',
						'options' => array(
							'enable' => esc_html__('Enable', 'ecab-taxi-booking-manager'),
							'disable' => esc_html__('Disabled', 'ecab-taxi-booking-manager')
						)
					),
					array(
						'name' => 'taxi_waiting_time',
						'label' => esc_html__('Disable/ Enable Taxi Waiting Time', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('If you want to disable taxi Waiting Time, please select disable. default enable', 'ecab-taxi-booking-manager'),
						'type' => 'select',
						'default' => 'enable',
						'options' => array(
							'enable' => esc_html__('Enable', 'ecab-taxi-booking-manager'),
							'disable' => esc_html__('Disabled', 'ecab-taxi-booking-manager')
						)
					),
					array(
						'name' => 'disable_dropoff_hourly',
						'label' => esc_html__('Disable/Enable drop off location in hourly pricing', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('If you want to disable drop off location in hourly pricing, please select disable. default enable', 'ecab-taxi-booking-manager'),
						'type' => 'select',
						'default' => 'enable',
						'options' => array(
							'enable' => esc_html__('Enable', 'ecab-taxi-booking-manager'),
							'disable' => esc_html__('Disable', 'ecab-taxi-booking-manager')
						)
					),
					array(
						'name' => 'minimum_booking_hours',
						'label' => esc_html__('Minimum Booking Hours (Hourly Pricing)', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Minimum hours required for hourly bookings. Bookings below this won\'t be allowed. Select 0 to disable minimum restriction.', 'ecab-taxi-booking-manager'),
						'type' => 'select',
						'default' => '0',
						'options' => array(
							'1' => esc_html__('1 Hour', 'ecab-taxi-booking-manager'),
							'2' => esc_html__('2 Hours', 'ecab-taxi-booking-manager'),
							'3' => esc_html__('3 Hours', 'ecab-taxi-booking-manager'),
							'4' => esc_html__('4 Hours', 'ecab-taxi-booking-manager'),
							'5' => esc_html__('5 Hours', 'ecab-taxi-booking-manager'),
							'6' => esc_html__('6 Hours', 'ecab-taxi-booking-manager'),
							'7' => esc_html__('7 Hours', 'ecab-taxi-booking-manager'),
							'8' => esc_html__('8 Hours', 'ecab-taxi-booking-manager'),
							'9' => esc_html__('9 Hours', 'ecab-taxi-booking-manager'),
							'10' => esc_html__('10 Hours', 'ecab-taxi-booking-manager'),
						)
					),
					array(
						'name' => 'payment_system',
						'label' => esc_html__('Payment System', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Please Select Payment System.', 'ecab-taxi-booking-manager'),
						'type' => 'multicheck',
						'default' => array(
							'direct_order' => 'direct_order',
							'woocommerce' => 'woocommerce'
						),
						'options' => array(
							'direct_order' => esc_html__('Pay on service', 'ecab-taxi-booking-manager'),
							'woocommerce' => esc_html__('woocommerce Payment', 'ecab-taxi-booking-manager'),
						)
					),
					array(
						'name' => 'direct_book_status',
						'label' => esc_html__('Pay on service Booked Status', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Please Select when and which order status service Will be Booked/Reduced in Pay on service.', 'ecab-taxi-booking-manager'),
						'type' => 'select',
						'default' => 'completed',
						'options' => array(
							'pending' => esc_html__('Pending', 'ecab-taxi-booking-manager'),
							'completed' => esc_html__('completed', 'ecab-taxi-booking-manager')
						)
					),
					array(
						'name' => 'label',
						'label' => $label . ' ' . esc_html__('Label', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('If you like to change the label in the dashboard menu, you can change it here.', 'ecab-taxi-booking-manager'),
						'type' => 'text',
						'default' => 'Transportation'
					),
					array(
						'name' => 'slug',
						'label' => $label . ' ' . esc_html__('Slug', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Please enter the slug name you want. Remember, after changing this slug; you need to flush permalink; go to', 'ecab-taxi-booking-manager') . '<strong>' . esc_html__('Settings-> Permalinks', 'ecab-taxi-booking-manager') . '</strong> ' . esc_html__('hit the Save Settings button.', 'ecab-taxi-booking-manager'),
						'type' => 'text',
						'default' => 'transportation'
					),
					array(
						'name' => 'icon',
						'label' => $label . ' ' . esc_html__('Icon', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('If you want to change the  icon in the dashboard menu, you can change it from here, and the Dashboard icon only supports the Dashicons, So please go to ', 'ecab-taxi-booking-manager') . '<a href=https://developer.wordpress.org/resource/dashicons/#calendar-alt target=_blank>' . esc_html__('Dashicons Library.', 'ecab-taxi-booking-manager') . '</a>' . esc_html__('and copy your icon code and paste it here.', 'ecab-taxi-booking-manager'),
						'type' => 'text',
						'default' => 'dashicons-car'
					),
					array(
						'name' => 'category_label',
						'label' => $label . ' ' . esc_html__('Category Label', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('If you want to change the  category label in the dashboard menu, you can change it here.', 'ecab-taxi-booking-manager'),
						'type' => 'text',
						'default' => 'Category'
					),
					array(
						'name' => 'category_slug',
						'label' => $label . ' ' . esc_html__('Category Slug', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Please enter the slug name you want for category. Remember after change this slug you need to flush permalink, Just go to  ', 'ecab-taxi-booking-manager') . '<strong>' . esc_html__('Settings-> Permalinks', 'ecab-taxi-booking-manager') . '</strong> ' . esc_html__('hit the Save Settings button.', 'ecab-taxi-booking-manager'),
						'type' => 'text',
						'default' => 'transportation-category'
					),
					array(
						'name' => 'organizer_label',
						'label' => $label . ' ' . esc_html__('Organizer Label', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('If you want to change the  category label in the dashboard menu you can change here', 'ecab-taxi-booking-manager'),
						'type' => 'text',
						'default' => 'Organizer'
					),
					array(
						'name' => 'organizer_slug',
						'label' => $label . ' ' . esc_html__('Organizer Slug', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Please enter the slug name you want for the  organizer. Remember, after changing this slug, you need to flush the permalinks. Just go to ', 'ecab-taxi-booking-manager') . '<strong>' . esc_html__('Settings-> Permalinks', 'ecab-taxi-booking-manager') . '</strong> ' . esc_html__('hit the Save Settings button.', 'ecab-taxi-booking-manager'),
						'type' => 'text',
						'default' => 'transportation-organizer'
					),
					array(
						'name' => 'expire',
						'label' => $label . ' ' . esc_html__('Expired  Visibility', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('If you want to visible expired  ?, please select ', 'ecab-taxi-booking-manager') . '<strong> ' . esc_html__('Yes', 'ecab-taxi-booking-manager') . '</strong>' . esc_html__('or to make it hidden, select', 'ecab-taxi-booking-manager') . '<strong> ' . esc_html__('No', 'ecab-taxi-booking-manager') . '</strong>' . esc_html__('. Default is', 'ecab-taxi-booking-manager') . '<strong>' . esc_html__('No', 'ecab-taxi-booking-manager') . '</strong>',
						'type' => 'select',
						'default' => 'no',
						'options' => array(
							'yes' => esc_html__('Yes', 'ecab-taxi-booking-manager'),
							'no' => esc_html__('No', 'ecab-taxi-booking-manager')
						)
					),
					array(
						'name' => 'enable_view_search_result_page',
						'label' => $label . ' ' . esc_html__('Show Search Result In A Different Page', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Enter page slug (e.g., my-search-results) or full URL. The plugin will automatically assign the correct template to any page you specify. Leave blank if you dont want to enable this setting. Works with any WordPress permalink structure.', 'ecab-taxi-booking-manager'),
						'type' => 'text',
						'placeholder' => 'my-search-results',
						'default' => '',
					),
					array(
						'name' => 'enable_view_find_location_page',
						'label' => $label . ' ' . esc_html__('Take user to another page if location can not be found', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Enter page slug (e.g., taxi-help) or full URL. Leave blank if you dont want to enable this setting. Works with any WordPress permalink structure.', 'ecab-taxi-booking-manager'),
						'type' => 'text',
						'placeholder' => 'taxi-help'
					),
					array(
						'name' => 'enable_buffer_time',
						'label' => $label . ' ' . esc_html__('Buffer Time', 'ecab-taxi-booking-manager'),
						// Text domain was missing on the first half, so the sentence could never
						// be translated and stayed English on localised sites while the red
						// "Settings --> General --> Timezone" tail beside it did translate.
						'desc' => esc_html__('Enter buffer time per minutes. Also you have to change the timezone from', 'ecab-taxi-booking-manager') . '<strong style="color: red;">' . esc_html__('WordPress Settings --> General --> Timezone', 'ecab-taxi-booking-manager') . '</strong>',
						'type' => 'text',
						'placeholder' => 'Ex:10'
						),
					array(
						'name' => 'mptbm_pickup_interval_time',
						'label' => $label . ' ' . esc_html__('Interval of pickup/return time in frontend', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Select frontend interval pickup and return time', 'ecab-taxi-booking-manager'),
						'ecab-taxi-booking-manager' . '<strong> ' . esc_html__('Yes', 'ecab-taxi-booking-manager') . '</strong>' . esc_html__('or to make it hidden, select', 'ecab-taxi-booking-manager') . '<strong> ' . esc_html__('No', 'ecab-taxi-booking-manager') . '</strong>' . esc_html__('. Default is', 'ecab-taxi-booking-manager') . '<strong>' . esc_html__('No', 'ecab-taxi-booking-manager') . '</strong>',
						'type' => 'select',
						'default' => 30,
						'options' => array(
							30 => esc_html__('30', 'ecab-taxi-booking-manager'),
							15 => esc_html__('15', 'ecab-taxi-booking-manager'),
							10 => esc_html__('10', 'ecab-taxi-booking-manager'),
							5 => esc_html__('5', 'ecab-taxi-booking-manager'),
						)
					),
					array(
						'name' => 'enable_return_in_different_date',
						'label' => $label . ' ' . esc_html__('Enable return in different date', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Select yes if you want to enable different date return field', 'ecab-taxi-booking-manager'),
						'ecab-taxi-booking-manager' . '<strong> ' . esc_html__('Yes', 'ecab-taxi-booking-manager') . '</strong>' . esc_html__('or to make it hidden, select', 'ecab-taxi-booking-manager') . '<strong> ' . esc_html__('No', 'ecab-taxi-booking-manager') . '</strong>' . esc_html__('. Default is', 'ecab-taxi-booking-manager') . '<strong>' . esc_html__('No', 'ecab-taxi-booking-manager') . '</strong>',
						'type' => 'select',
						'default' => 'no',
						'options' => array(
							'yes' => esc_html__('Yes', 'ecab-taxi-booking-manager'),
							'no' => esc_html__('No', 'ecab-taxi-booking-manager')
						)
					),
					array(
						'name' => 'mptbm_extra_stop_between_pickup_dropoff',
						'label' => $label . ' ' . esc_html__('Add extra stops between pickup and dropoff location', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Select yes if you want to enable adding one or more extra stops between pickup and dropoff location', 'ecab-taxi-booking-manager'),
						'type' => 'select',
						'default' => 'no',
						'options' => array(
							'yes' => esc_html__('Yes', 'ecab-taxi-booking-manager'),
							'no' => esc_html__('No', 'ecab-taxi-booking-manager')
						)
					),
					array(
						'name' => 'mptbm_max_extra_stops',
						'label' => $label . ' ' . esc_html__('Maximum number of extra stops', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('How many extra stops a customer can add to a single trip (used when extra stops are enabled above).', 'ecab-taxi-booking-manager'),
						'type' => 'number',
						'default' => 3
					),
					array(
						'name' => 'enable_filter_via_features',
						'label' => $label . ' ' . esc_html__('Enable filter via features', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Select yes if you want to enable filter via passenger and bags', 'ecab-taxi-booking-manager'),
						'ecab-taxi-booking-manager' . '<strong> ' . esc_html__('Yes', 'ecab-taxi-booking-manager') . '</strong>' . esc_html__('or to make it hidden, select', 'ecab-taxi-booking-manager') . '<strong> ' . esc_html__('No', 'ecab-taxi-booking-manager') . '</strong>' . esc_html__('. Default is', 'ecab-taxi-booking-manager') . '<strong>' . esc_html__('No', 'ecab-taxi-booking-manager') . '</strong>',
						'type' => 'select',
						'default' => 'no',
						'options' => array(
							'yes' => esc_html__('Yes', 'ecab-taxi-booking-manager'),
							'no' => esc_html__('No', 'ecab-taxi-booking-manager')
						)
					),
					array(
						'name' => 'show_summary_mobile',
						'label' => esc_html__('Show Summary in Mobile Version', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Select yes if you want to show the summary section in mobile devices. Default is Yes', 'ecab-taxi-booking-manager'),
						'type' => 'select',
						'default' => 'yes',
						'options' => array(
							'yes' => esc_html__('Yes', 'ecab-taxi-booking-manager'),
							'no' => esc_html__('No', 'ecab-taxi-booking-manager')
						)
					),
					// array(
					// 	'name' => 'show_number_of_passengers',
					// 	'label' => esc_html__('Show Number of Passengers', 'ecab-taxi-booking-manager'),
					// 	'desc' => esc_html__('If you want to show the Number of Passengers field, select Yes. Default is Yes', 'ecab-taxi-booking-manager'),
					// 	'type' => 'select',
					// 	'default' => 'no',
					// 	'options' => array(
					// 		'yes' => esc_html__('Yes', 'ecab-taxi-booking-manager'),
					// 		'no' => esc_html__('No', 'ecab-taxi-booking-manager')
					// 	)
					// ),
					array(
						'name' => 'no_transport_message',
						'label' => esc_html__('No Transport Available Message', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Customize the message shown when no transport is available. You can use HTML tags for styling or select from predefined templates below.', 'ecab-taxi-booking-manager'),
						'type' => 'textarea',
						'default' => '<h3>No Transport Available !</h3>'
					),
					array(
						'name' => 'no_transport_templates',
						'label' => esc_html__('Predefined Templates', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Select a predefined template for the No Transport message', 'ecab-taxi-booking-manager'),
						'type' => 'select',
						'default' => 'default',
						'options' => array(
							'default' => esc_html__('Default', 'ecab-taxi-booking-manager'),
							'template1' => esc_html__('Template 1 - With Icon', 'ecab-taxi-booking-manager'),
							'template2' => esc_html__('Template 2 - With Description', 'ecab-taxi-booking-manager'),
							'template3' => esc_html__('Template 3 - With Contact Info', 'ecab-taxi-booking-manager')
						)
					),
					// array(
					// 	'name' => 'single_page_checkout',
					// 	'label' => esc_html__('Disable single page checkout', 'ecab-taxi-booking-manager'),
					// 	'desc' => esc_html__('If you want to disable single page checkout, please select Yes.That means active woocommerce checkout page active', 'ecab-taxi-booking-manager'),
					// 	'type' => 'select',
					// 	'default' => 'yes',
					// 	'options' => array(
					// 		'yes' => esc_html__('Yes', 'ecab-taxi-booking-manager'),
					// 		'no' => esc_html__('No', 'ecab-taxi-booking-manager')
					// 	)
					// )
				)),
				'mptbm_map_api_settings' => apply_filters('filter_mptbm_map_api_settings', array(
					array(
						'name' => 'display_map',
						'label' => esc_html__('Pricing system based on  map', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('If you want to disable Pricing system based on  map, please select Without map. default openstreet map', 'ecab-taxi-booking-manager'),
						'type' => 'select',
						'default' => 'openstreetmap',
						'options' => array(
							'enable' => esc_html__('Google map', 'ecab-taxi-booking-manager'),
							'openstreetmap' => esc_html__('Openstreetmap', 'ecab-taxi-booking-manager'),
							'disable' => esc_html__('Without map api', 'ecab-taxi-booking-manager'),

						)
					),
					array(
						'name' => 'show_map_on_search_result',
						'label' => esc_html__('Show Map on Search Result Page', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Show or hide the route map on the vehicle search result page. This is a master switch - selecting No hides the map everywhere regardless of the booking form/shortcode\'s own map option.', 'ecab-taxi-booking-manager'),
						'type' => 'select',
						'default' => 'yes',
						'options' => array(
							'yes' => esc_html__('Yes', 'ecab-taxi-booking-manager'),
							'no' => esc_html__('No', 'ecab-taxi-booking-manager')
						)
					),
					array(
						'name' => 'gmap_api_key',
						'label' => esc_html__('Google MAP API', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Please enter your Google Maps API key in this Options.', 'ecab-taxi-booking-manager') . '<a class="" href=' . $gm_api_url . ' target="_blank">Click Here to get google api key</a>',
						'type' => 'text',
						'default' => ''
					),
					array(
						'name' => 'gmap_server_api_key',
						'label' => esc_html__('Google MAP API Key (Server Side)', 'ecab-taxi-booking-manager'),
						// Fares are calculated from a distance this site looks up itself, using
						// Google's Distance Matrix/Directions Web Service APIs. Google rejects
						// referrer-restricted keys on those APIs ("API keys with referer
						// restrictions cannot be used with this API"), and a referrer restriction
						// is the correct setting for the key above, which the browser uses. When
						// the same restricted key is used for both, every server-side lookup is
						// denied and pricing silently falls back to OSRM - a different road
						// network, so a different distance than the map shows.
						'desc' => esc_html__('Optional. Used only for the server-side distance lookup that fares are calculated from. Leave empty to reuse the key above. If the key above has HTTP referrer (website) restrictions, Google will reject it here, and trips get priced from the OpenStreetMap fallback instead - which can quote a noticeably different distance than the map shows. In that case add a second key restricted by IP address (or unrestricted) with the Distance Matrix API and Directions API enabled.', 'ecab-taxi-booking-manager'),
						'type' => 'text',
						'default' => ''
					),
					array(
						'name' => 'fallback_routing_provider',
						// This field does two different jobs depending on the map mode, so it
						// says two different things. Under OpenStreetMap it IS the thing that
						// measures every fare. Under Google map it is only a backup - which is
						// not obvious from a bare "Routing Service" label sitting next to a
						// Google API key, and reads as a competing setting. Both variants are
						// rendered and assets/admin/mptbm_global_settings.js shows whichever
						// matches the selected map mode (same approach as use_shortest_route).
						'label' => sprintf(
							'<span data-routing-label="openstreetmap">%1$s</span><span data-routing-label="enable" style="display:none">%2$s</span>',
							esc_html__('Routing Service (distance measurement)', 'ecab-taxi-booking-manager'),
							esc_html__('Backup Routing Service', 'ecab-taxi-booking-manager')
						),
						// OpenStreetMap's routing is free and needs no account, but it can only
						// route over roads that have actually been mapped into OSM. Where a road
						// is missing, the router detours around it and that detour is billed to
						// the customer as real distance. TomTom runs its own road network, so it
						// answers correctly there - and issues keys without a billing account,
						// which is normally the real obstacle to a server-side key.
						'desc' => sprintf(
							'<span data-routing-desc="openstreetmap">%1$s</span><span data-routing-desc="enable" style="display:none">%2$s</span>',
							esc_html__('Which service measures the driving distance that fares are calculated from. OpenStreetMap is free and needs no account, but it can only route over roads mapped into OpenStreetMap - where a road is missing it takes a long detour and the customer is charged for it. If the quoted distance is longer than the real route, switch to TomTom: a free key allows 2,500 requests per day, needs no credit card, and uses TomTom\'s own road data. Falls back to OpenStreetMap automatically if TomTom is unreachable.', 'ecab-taxi-booking-manager'),
							esc_html__('Google measures the distance while it can, and this is only used when it cannot - most often because the Google key is restricted to your website, which Google does not accept for server-side requests. That fallback is silent, so leaving it on OpenStreetMap means fares can quietly be measured on roads OpenStreetMap has not mapped, and quoted longer than the real route. Set it to TomTom if the warning about the Google lookup ever appears.', 'ecab-taxi-booking-manager')
						),
						'type' => 'select',
						'default' => 'osrm',
						'options' => array(
							'osrm' => esc_html__('OpenStreetMap / OSRM (free, no account)', 'ecab-taxi-booking-manager'),
							'tomtom' => esc_html__('TomTom (free key, own road data - more accurate)', 'ecab-taxi-booking-manager'),
						)
					),
					array(
						'name' => 'tomtom_api_key',
						'label' => esc_html__('TomTom API Key', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Required when the routing service above is set to TomTom. Get a free key at', 'ecab-taxi-booking-manager') . ' <a href="https://developer.tomtom.com/" target="_blank" rel="noopener">developer.tomtom.com</a> ' . esc_html__('- registration is free, no credit card is needed, and the free allowance is 2,500 requests per day. Enable the "Routing" API on the key.', 'ecab-taxi-booking-manager'),
						'type' => 'text',
						'default' => ''
					),
					array(
						'name' => 'fare_distance_source',
						'label' => esc_html__('Fare Distance Source', 'ecab-taxi-booking-manager'),
						// The safe default is the server measuring the route itself, since
						// nothing the customer sends can influence it. The 'browser' option is
						// for sites whose only Google key is referrer-restricted: Google refuses
						// those server-side, so every fare is silently priced off the
						// OpenStreetMap fallback, which overcharges wherever OSM's road data is
						// incomplete. See MPTBM_Transport_Search::resolve_trip_distance() and
						// MPTBM_Function::validate_client_trip() for the bounds applied.
						'desc' => esc_html__('Which distance the fare is calculated from. "Server" is the most tamper-proof and is recommended when you have a working server-side Google key. Choose "Browser" if you cannot add a server-side key: it prices the trip on the accurate distance Google already calculated in the customer\'s browser, after the server checks that figure against the straight-line distance and rejects anything shorter than physically possible. Use Browser when the fare does not match the distance shown on the map.', 'ecab-taxi-booking-manager'),
						'type' => 'select',
						'default' => 'server',
						'options' => array(
							'server' => esc_html__('Server (most secure, needs a server-side API key)', 'ecab-taxi-booking-manager'),
							'browser' => esc_html__('Browser, verified server-side (no extra API key needed)', 'ecab-taxi-booking-manager'),
						)
					),
					array(
						'name' => 'use_shortest_route',
						'label' => esc_html__('Use Shortest Distance Route', 'ecab-taxi-booking-manager'),
						// Two variants, only one shown at a time - assets/admin/mptbm_global_settings.js
						// toggles between them (by [data-shortest-route-desc]) as the select changes,
						// so the description always reflects the currently-picked option instead of
						// permanently listing both at once.
						'desc' => sprintf(
							'<span data-shortest-route-desc="no">%1$s</span><span data-shortest-route-desc="yes" style="display:none">%2$s</span>',
							esc_html__('Price trips using the route Google/OSRM recommends as best (balances time and distance - generally the route a driver would actually navigate).', 'ecab-taxi-booking-manager'),
							esc_html__('Compare all available route alternatives and always price the one with the smallest distance - this can lower quoted fares but may not match the route actually driven.', 'ecab-taxi-booking-manager')
						),
						'type' => 'select',
						'default' => 'no',
						'options' => array(
							'no' => esc_html__('No (Recommended route)', 'ecab-taxi-booking-manager'),
							'yes' => esc_html__('Yes (Always shortest distance)', 'ecab-taxi-booking-manager'),
						)
					),
					array(
						'name' => 'mp_latitude',
						'label' => esc_html__('Your Location Latitude', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Please type Your Location Latitude.This are mandatory for google map show. To find latitude please ', 'ecab-taxi-booking-manager') . '<a href="https://www.latlong.net/" target="_blank">' . esc_html__('Click Here', 'ecab-taxi-booking-manager') . '</a>',
						'type' => 'text',
						'default' => '23.81234828905659'
					),
					array(
						'name' => 'mp_longitude',
						'label' => esc_html__('Your Location Longitude', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Please type Your Location Longitude .This are mandatory for google map show. To find latitude please ', 'ecab-taxi-booking-manager') . '<a href="https://www.latlong.net/" target="_blank">' . esc_html__('Click Here', 'ecab-taxi-booking-manager') . '</a>',
						'type' => 'text',
						'default' => '90.41069652669002'
					),
					array(
						'name' => 'mp_country',
						'label' => esc_html__('Country Location', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Select your country Location.This are mandatory for google map show.', 'ecab-taxi-booking-manager'),
						'type' => 'select',
						'default' => 'BD',
						'options' => MP_Global_Function::get_country_list()
					),
					array(
						'name' => 'mp_country_restriction',
						'label' => esc_html__('Restrict Search To Country', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Restrict search to specified to country', 'ecab-taxi-booking-manager'),
						'type' => 'select',
						'default' => 'no',
						'options' => array(
							'yes' => esc_html__('Yes', 'ecab-taxi-booking-manager'),
							'no' => esc_html__('No', 'ecab-taxi-booking-manager')
						)
					)
				)),
			'mptbm_rest_api_settings' => apply_filters('filter_mptbm_rest_api_settings', array(
				array(
					'name' => 'enable_rest_api',
					'label' => esc_html__('Enable REST API', 'ecab-taxi-booking-manager'),
					'desc' => esc_html__('Enable or disable the REST API for taxi booking operations', 'ecab-taxi-booking-manager'),
					'type' => 'select',
					'default' => 'no',
					'options' => array(
						'yes' => esc_html__('Enable', 'ecab-taxi-booking-manager'),
						'no' => esc_html__('Disable', 'ecab-taxi-booking-manager')
					)
				),
				array(
					'name' => 'api_base_url',
					'label' => esc_html__('API Base URL', 'ecab-taxi-booking-manager'),
					'desc' => esc_html__('The base URL for your REST API endpoints', 'ecab-taxi-booking-manager'),
					'type' => 'text',
					'default' => site_url('wp-json/ecab-taxi/v1/'),
					'readonly' => true
				),
				array(
					'name' => 'enable_api_key_auth',
					'label' => esc_html__('Enable API Key Authentication', 'ecab-taxi-booking-manager'),
					'desc' => esc_html__('Allow authentication using API keys for server-to-server communication', 'ecab-taxi-booking-manager'),
					'type' => 'select',
					'default' => 'yes',
					'options' => array(
						'yes' => esc_html__('Enable', 'ecab-taxi-booking-manager'),
						'no' => esc_html__('Disable', 'ecab-taxi-booking-manager')
					)
				),
				array(
					'name' => 'api_key_expiry',
					'label' => esc_html__('API Key Expiry (days)', 'ecab-taxi-booking-manager'),
					'desc' => esc_html__('Number of days after which API keys expire (0 for no expiry)', 'ecab-taxi-booking-manager'),
					'type' => 'number',
					'default' => '365'
				),
				array(
					'name' => 'enable_app_passwords',
					'label' => esc_html__('Enable Application Passwords', 'ecab-taxi-booking-manager'),
					'desc' => esc_html__('Allow users to generate application passwords for API access', 'ecab-taxi-booking-manager'),
					'type' => 'select',
					'default' => 'yes',
					'options' => array(
						'yes' => esc_html__('Enable', 'ecab-taxi-booking-manager'),
						'no' => esc_html__('Disable', 'ecab-taxi-booking-manager')
					)
				),
				array(
					'name' => 'rate_limit_enabled',
					'label' => esc_html__('Enable Rate Limiting', 'ecab-taxi-booking-manager'),
					'desc' => esc_html__('Limit the number of API requests per minute', 'ecab-taxi-booking-manager'),
					'type' => 'select',
					'default' => 'yes',
					'options' => array(
						'yes' => esc_html__('Enable', 'ecab-taxi-booking-manager'),
						'no' => esc_html__('Disable', 'ecab-taxi-booking-manager')
					)
				),
				array(
					'name' => 'rate_limit_requests',
					'label' => esc_html__('Rate Limit (requests/minute)', 'ecab-taxi-booking-manager'),
					'desc' => esc_html__('Maximum number of API requests allowed per minute per API key', 'ecab-taxi-booking-manager'),
					'type' => 'number',
					'default' => '100'
				),
				array(
					'name' => 'allowed_user_roles',
					'label' => esc_html__('API Access Roles', 'ecab-taxi-booking-manager'),
					'desc' => esc_html__('Select which user roles can generate API keys', 'ecab-taxi-booking-manager'),
					'type' => 'multicheck',
					'default' => array(
						'administrator' => 'administrator'
					),
					'options' => array(
						'administrator' => esc_html__('Administrator', 'ecab-taxi-booking-manager'),
						'editor' => esc_html__('Editor', 'ecab-taxi-booking-manager'),
						'shop_manager' => esc_html__('Shop Manager', 'ecab-taxi-booking-manager')
					)
				),
				array(
					'name' => 'api_logging',
					'label' => esc_html__('Enable API Logging', 'ecab-taxi-booking-manager'),
					'desc' => esc_html__('Log API requests for debugging and monitoring', 'ecab-taxi-booking-manager'),
					'type' => 'select',
					'default' => 'yes',
					'options' => array(
						'yes' => esc_html__('Enable', 'ecab-taxi-booking-manager'),
						'no' => esc_html__('Disable', 'ecab-taxi-booking-manager')
					)
				),
				array(
					'name' => 'cors_enabled',
					'label' => esc_html__('Enable CORS', 'ecab-taxi-booking-manager'),
					'desc' => esc_html__('Enable Cross-Origin Resource Sharing for web applications', 'ecab-taxi-booking-manager'),
					'type' => 'select',
					'default' => 'yes',
					'options' => array(
						'yes' => esc_html__('Enable', 'ecab-taxi-booking-manager'),
						'no' => esc_html__('Disable', 'ecab-taxi-booking-manager')
					)
				),
				array(
					'name' => 'cors_allowed_origins',
					'label' => esc_html__('Allowed Origins', 'ecab-taxi-booking-manager'),
					'desc' => esc_html__('Comma-separated list of allowed origins (* for all)', 'ecab-taxi-booking-manager'),
					'type' => 'textarea',
					'default' => '*'
				)
			)),
				'mptbm_buffer_settings' => apply_filters('filter_mptbm_buffer_settings', array(
					array(
						'name' => 'buffer_time',
						'label' => esc_html__('Buffer Time', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Please enter the buffer time in minutes.', 'ecab-taxi-booking-manager'),
						'type' => 'text',
						'default' => '10'
					)
				)),
				// Conditionally add QR settings fields
				// Only add if Ecab_Taxi_Booking_QR_Addon exists
			);
			if (class_exists('Ecab_Taxi_Booking_QR_Addon')) {
				$settings_fields['mptbm_qr_settings'] = apply_filters('filter_mptbm_qr_settings', array(
					array(
						'name' => 'mptbm_enable_qr_code',
						'label' => esc_html__('Enable QR Code', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('If you want to enable QR Code, please select Yes. Default is No', 'ecab-taxi-booking-manager'),
						'type' => 'select',
						'default' => 'no',
						'options' => array(
							'yes' => esc_html__('Yes', 'ecab-taxi-booking-manager'),
							'no' => esc_html__('No', 'ecab-taxi-booking-manager')
						)
					),
					array(
						'name' => 'mptbm_allowed_user_roles',
						'label' => esc_html__('Allowed User Role', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Select the user role that can access the QR Code. Default is Administrator', 'ecab-taxi-booking-manager'),
						'type' => 'mp_select2_role',
						'default' => ['administrator'],
						'options' => []
					)
				));
			}
			
			return array_merge($default_fields, $settings_fields);
		}
		public function global_taxi($default_sec)
		{
			$label = MPTBM_Function::get_name();
			$sections = array(
				array(
					'name' => 'set_book_status',
					'label' => $label . ' ' . esc_html__('Seat Booked Status', 'ecab-taxi-booking-manager'),
					'desc' => esc_html__('Please Select when and which order status Seat Will be Booked/Reduced.', 'ecab-taxi-booking-manager'),
					'type' => 'multicheck',
					'default' => array(
						'processing' => 'processing',
						'completed' => 'completed'
					),
					'options' => array(
						'on-hold' => esc_html__('On Hold', 'ecab-taxi-booking-manager'),
						'pending' => esc_html__('Pending', 'ecab-taxi-booking-manager'),
						'processing' => esc_html__('Processing', 'ecab-taxi-booking-manager'),
						'completed' => esc_html__('Completed', 'ecab-taxi-booking-manager'),
					)
				),
				array(
					'name' => 'km_or_mile',
					'label' =>  $label . ' ' . esc_html__('Duration By Kilometer or Mile', 'ecab-taxi-booking-manager'),
					'type' => 'select',
					'default' => 'km',
					'options' => array(
						'km' => esc_html__('Kilometer', 'ecab-taxi-booking-manager'),
						'mile' => esc_html__('Mile', 'ecab-taxi-booking-manager')
					)
				),
			);
			return array_merge($default_sec, $sections);
		}


	}
	new  MPTBM_Settings_Global();
}
