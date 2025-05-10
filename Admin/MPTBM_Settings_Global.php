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
			add_filter('mp_settings_sec_reg', array($this, 'settings_sec_reg'), 10);
			add_filter('mp_settings_sec_fields', array($this, 'settings_sec_fields'), 10);
			add_filter('filter_mp_global_settings', array($this, 'global_taxi'), 10);
		}
		public function global_settings_menu()
		{
			$cpt = MPTBM_Function::get_cpt();
			add_submenu_page('edit.php?post_type=' . $cpt, esc_html__('Global Settings', 'ecab-taxi-booking-manager'), esc_html__('Global Settings', 'ecab-taxi-booking-manager'), 'manage_options', 'mptbm_settings_page', array($this, 'settings_page'));
		}
		public function settings_page()
		{
?>
			<div class="mpStyle mp_global_settings">
				<div class="mpPanel">
					<div class="mpPanelHeader"><?php echo esc_html(esc_html__(' Global Settings', 'ecab-taxi-booking-manager')); ?></div>
					<div class="mpPanelBody mp_zero">
						<div class="mpTabs leftTabs">
							<?php $this->settings_api->show_navigation(); ?>
							<div class="tabsContent " style= "padding: 1% !important;" >
								<?php $this->settings_api->show_forms(); ?>
							</div>
						</div>
					</div>
				</div>
			</div>
<?php
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
					'icon' => 'fab fa-google',
					'title' => esc_html__('Google Map API Settings', 'ecab-taxi-booking-manager')
				),
				array(
					'id' => 'mptbm_general_settings',
					'icon' => 'fas fa-sliders-h',
					'title' => $label . ' ' . esc_html__('Settings', 'ecab-taxi-booking-manager')
				),
				array(
					'id' => 'mptbm_rest_api_settings',
					'icon' => 'fas fa-code',
					'title' => esc_html__('REST API Settings', 'ecab-taxi-booking-manager')
				)
			);
			return array_merge($default_sec, $sections);
		}
		public function settings_sec_fields($default_fields): array
		{
			$gm_api_url = 'https://developers.google.com/maps/documentation/javascript/get-api-key';
			$label = MPTBM_Function::get_name();

			
			

			$settings_fields = array(
				'mptbm_general_settings' => apply_filters('filter_mptbm_general_settings', array(
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
						'desc' => esc_html__('Enter page slug. Leave blank if you dont want to enable this setting', 'ecab-taxi-booking-manager'),
						'ecab-taxi-booking-manager' . '<strong> ' . esc_html__('Yes', 'ecab-taxi-booking-manager') . '</strong>' . esc_html__('or to make it hidden, select', 'ecab-taxi-booking-manager') . '<strong> ' . esc_html__('No', 'ecab-taxi-booking-manager') . '</strong>' . esc_html__('. Default is', 'ecab-taxi-booking-manager') . '<strong>' . esc_html__('No', 'ecab-taxi-booking-manager') . '</strong>',
						'type' => 'text',
						'placeholder' => 'transport-result',
						'default' => '',
					),
					array(
						'name' => 'enable_view_find_location_page',
						'label' => $label . ' ' . esc_html__('Take user to another page if location can not be found', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Enter page url. Leave blank if you dont want to enable this setting', 'ecab-taxi-booking-manager'),
						'ecab-taxi-booking-manager' . '<strong> ' . esc_html__('Yes', 'ecab-taxi-booking-manager') . '</strong>' . esc_html__('or to make it hidden, select', 'ecab-taxi-booking-manager') . '<strong> ' . esc_html__('No', 'ecab-taxi-booking-manager') . '</strong>' . esc_html__('. Default is', 'ecab-taxi-booking-manager') . '<strong>' . esc_html__('No', 'ecab-taxi-booking-manager') . '</strong>',
						'type' => 'text',
						'placeholder' => 'https://mysite.com/taxi'
					),
					array(
						'name' => 'enable_buffer_time',
						'label' => $label . ' ' . esc_html__('Buffer Time', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Enter buffer time per minutes. Also you have to change the timezone from') . '<strong style="color: red;">' . esc_html__('Settings --> General --> Timezone', 'ecab-taxi-booking-manager') . '</strong>',
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
						'label' => esc_html__('Pricing system based on google map', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('If you want to disable Pricing system based on google map, please select Without google map. default Google map', 'ecab-taxi-booking-manager'),
						'type' => 'select',
						'default' => 'enable',
						'options' => array(
							'enable' => esc_html__('Google map', 'ecab-taxi-booking-manager'),
							'disable' => esc_html__('Without google map', 'ecab-taxi-booking-manager')
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
						'label' => esc_html__('Enable Rest API', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('If you want to enable the REST API for external integrations, please select On. Default is Off.', 'ecab-taxi-booking-manager'),
						'type' => 'select',
						'default' => 'off',
						'options' => array(
							'on' => esc_html__('On', 'ecab-taxi-booking-manager'),
							'off' => esc_html__('Off', 'ecab-taxi-booking-manager')
						)
					),
					array(
						'name' => 'api_authentication',
						'label' => esc_html__('API Authentication', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Choose the authentication method for the REST API', 'ecab-taxi-booking-manager'),
						'type' => 'select',
						'default' => 'application_passwords',
						'options' => array(
							'application_passwords' => esc_html__('Application Passwords', 'ecab-taxi-booking-manager'),
							'basic_auth' => esc_html__('Basic Auth', 'ecab-taxi-booking-manager'),
							'api_key' => esc_html__('API Key', 'ecab-taxi-booking-manager')
						)
					),
					array(
						'name' => 'api_rate_limit',
						'label' => esc_html__('API Rate Limit', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Number of requests allowed per minute (0 for unlimited)', 'ecab-taxi-booking-manager'),
						'type' => 'number',
						'default' => '60'
					),
					array(
						'name' => 'api_keys',
						'label' => esc_html__('API Keys', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Manage API keys for external applications to authenticate with the API.', 'ecab-taxi-booking-manager'),
						'type' => 'text_html',
						'value' => '<div id="mptbm-api-keys-wrapper">
							<button type="button" id="mptbm-generate-key-btn" class="button button-primary">
								<span class="dashicons dashicons-plus"></span>' . esc_html__('Generate New API Key', 'ecab-taxi-booking-manager') . '
							</button>
							<div id="mptbm-key-form" style="display:none;">
								<h4>' . esc_html__('Generate API Key', 'ecab-taxi-booking-manager') . '</h4>
								<p>
									<label style="display:block; margin-bottom:5px;"><strong>' . esc_html__('Description:', 'ecab-taxi-booking-manager') . '</strong></label>
									<input type="text" id="mptbm-api-key-description" class="regular-text" style="width:100%;" placeholder="' . esc_attr__('My Application', 'ecab-taxi-booking-manager') . '">
								</p>
								<p class="description">' . esc_html__('Give this API key a descriptive name so you can identify it later.', 'ecab-taxi-booking-manager') . '</p>
								<p>
									<button type="button" id="mptbm-generate-api-key" class="button button-primary">' . esc_html__('Generate Key', 'ecab-taxi-booking-manager') . '</button>
									<button type="button" id="mptbm-cancel-api-key" class="button">' . esc_html__('Cancel', 'ecab-taxi-booking-manager') . '</button>
								</p>
							</div>
							<div id="mptbm-api-keys-table"></div>
						</div>'
					),
					array(
						'name' => 'api_debug_mode',
						'label' => esc_html__('Debug Mode', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Enable detailed error messages in API responses. Not recommended for production.', 'ecab-taxi-booking-manager'),
						'type' => 'select',
						'default' => 'off',
						'options' => array(
							'on' => esc_html__('On', 'ecab-taxi-booking-manager'),
							'off' => esc_html__('Off', 'ecab-taxi-booking-manager')
						)
					),
					array(
						'name' => 'api_documentation_link',
						'label' => esc_html__('API Documentation', 'ecab-taxi-booking-manager'),
						'desc' => esc_html__('Access complete API documentation including endpoints, authentication methods, and examples.', 'ecab-taxi-booking-manager'),
						'type' => 'text_html',
						'value' => '<a href="' . esc_url(admin_url('edit.php?post_type=mptbm_rent&page=mptbm_documentation&tab=api')) . '" class="button button-secondary api-docs-button" target="_blank">
							<span class="dashicons dashicons-media-document" style="margin-top:4px;"></span> 
							' . esc_html__('View API Documentation', 'ecab-taxi-booking-manager') . '
						</a>'
					)
				))
			);

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
