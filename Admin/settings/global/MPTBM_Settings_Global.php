<?php
	/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('MPTBM_Settings_Global')) {
		class MPTBM_Settings_Global {
			protected $settings_api;
			public function __construct() {
				$this->settings_api = new MAGE_Setting_API;
				add_action('admin_menu', array($this, 'global_settings_menu'));
				add_action('admin_init', array($this, 'admin_init'));
				add_filter('mp_settings_sec_reg', array($this, 'settings_sec_reg'), 10);
				add_filter('mp_settings_sec_fields', array($this, 'settings_sec_fields'), 10);
			}
			public function global_settings_menu() {
				$cpt = MPTBM_Function::get_cpt();
				add_submenu_page('edit.php?post_type=' . $cpt, esc_html__('Global Settings', 'mptbm_plugin'), esc_html__(' Settings', 'mptbm_plugin'), 'manage_options', 'mptbm_settings_page', array($this, 'settings_page'));
			}
			public function settings_page() {
				$label = MPTBM_Function::get_name();
				?>
				<div class="mp_settings_panel_header">
					<h3>
						<?php echo esc_html($label . esc_html__(' Global Settings', 'mptbm_plugin')); ?>
					</h3>
				</div>
				<div class="mp_settings_panel">
					<?php $this->settings_api->show_navigation(); ?>
					<?php $this->settings_api->show_forms(); ?>
				</div>
				<?php
			}
			public function admin_init() {
				$this->settings_api->set_sections($this->get_settings_sections());
				$this->settings_api->set_fields($this->get_settings_fields());
				$this->settings_api->admin_init();
			}
			public function get_settings_sections() {
				$sections = array();
				return apply_filters('mp_settings_sec_reg', $sections);
			}
			public function get_settings_fields() {
				$settings_fields = array();
				return apply_filters('mp_settings_sec_fields', $settings_fields);
			}
			public function settings_sec_reg($default_sec): array {
				$sections = array(
					array(
						'id' => 'mptbm_general_settings',
						'title' => __('General Settings', 'mptbm_plugin')
					),
					array(
						'id' => 'mptbm_map_api_settings',
						'title' => __('Google Map Settings', 'mptbm_plugin')
					),
					array(
						'id' => 'mp_style_settings',
						'title' => __('Style Settings', 'mptbm_plugin')
					),
					array(
						'id' => 'mp_add_custom_css',
						'title' => __('Custom CSS', 'mptbm_plugin')
					)
				);
				return array_merge($default_sec, $sections);
			}
			public function settings_sec_fields($default_fields): array {
				$gm_api_url = 'https://developers.google.com/maps/documentation/javascript/get-api-key';
				$label = MPTBM_Function::get_name();
				$current_date = current_time('Y-m-d');
				$settings_fields = array(
					'mptbm_general_settings' => apply_filters('filter_mptbm_general_settings', array(
						array(
							'name' => 'disable_block_editor',
							'label' => esc_html__('Disable Block/Gutenberg Editor', 'mptbm_plugin'),
							'desc' => esc_html__('If you want to disable WordPress\'s new Block/Gutenberg editor, please select Yes.', 'mptbm_plugin'),
							'type' => 'select',
							'default' => 'yes',
							'options' => array(
								'yes' => esc_html__('Yes', 'mptbm_plugin'),
								'no' => esc_html__('No', 'mptbm_plugin')
							)
						),
						array(
							'name' => 'set_book_status',
							'label' => esc_html__('Seat Booked Status', 'mptbm_plugin'),
							'desc' => esc_html__('Please Select when and which order status Seat Will be Booked/Reduced.', 'mptbm_plugin'),
							'type' => 'multicheck',
							'default' => array(
								'processing' => 'processing',
								'completed' => 'completed'
							),
							'options' => array(
								'on-hold' => esc_html__('On Hold', 'mptbm_plugin'),
								'pending' => esc_html__('Pending', 'mptbm_plugin'),
								'processing' => esc_html__('Processing', 'mptbm_plugin'),
								'completed' => esc_html__('Completed', 'mptbm_plugin'),
							)
						),
						array(
							'name' => 'date_format',
							'label' => esc_html__('Date Picker Format', 'mptbm_plugin'),
							'desc' => esc_html__('If you want to change Date Picker Format, please select format. Default  is D d M , yy.', 'mptbm_plugin'),
							'type' => 'select',
							'default' => 'D d M , yy',
							'options' => array(
								'yy-mm-dd' => $current_date,
								'yy/mm/dd' => date_i18n('Y/m/d', strtotime($current_date)),
								'yy-dd-mm' => date_i18n('Y-d-m', strtotime($current_date)),
								'yy/dd/mm' => date_i18n('Y/d/m', strtotime($current_date)),
								'dd-mm-yy' => date_i18n('d-m-Y', strtotime($current_date)),
								'dd/mm/yy' => date_i18n('d/m/Y', strtotime($current_date)),
								'mm-dd-yy' => date_i18n('m-d-Y', strtotime($current_date)),
								'mm/dd/yy' => date_i18n('m/d/Y', strtotime($current_date)),
								'd M , yy' => date_i18n('j M , Y', strtotime($current_date)),
								'D d M , yy' => date_i18n('D j M , Y', strtotime($current_date)),
								'M d , yy' => date_i18n('M  j, Y', strtotime($current_date)),
								'D M d , yy' => date_i18n('D M  j, Y', strtotime($current_date)),
							)
						),
						array(
							'name' => 'date_format_short',
							'label' => esc_html__('Short Date  Format', 'mptbm_plugin'),
							'desc' => esc_html__('If you want to change Short Date  Format, please select format. Default  is M , Y.', 'mptbm_plugin'),
							'type' => 'select',
							'default' => 'M , Y',
							'options' => array(
								'M , Y' => date_i18n('M , Y', strtotime($current_date)),
								'M , y' => date_i18n('M , y', strtotime($current_date)),
								'M - Y' => date_i18n('M - Y', strtotime($current_date)),
								'M - y' => date_i18n('M - y', strtotime($current_date)),
								'F , Y' => date_i18n('F , Y', strtotime($current_date)),
								'F , y' => date_i18n('F , y', strtotime($current_date)),
								'F - Y' => date_i18n('F - y', strtotime($current_date)),
								'F - y' => date_i18n('F - y', strtotime($current_date)),
								'm - Y' => date_i18n('m - Y', strtotime($current_date)),
								'm - y' => date_i18n('m - y', strtotime($current_date)),
								'm , Y' => date_i18n('m , Y', strtotime($current_date)),
								'm , y' => date_i18n('m , y', strtotime($current_date)),
								'F' => date_i18n('F', strtotime($current_date)),
								'm' => date_i18n('m', strtotime($current_date)),
								'M' => date_i18n('M', strtotime($current_date)),
							)
						),
						array(
							'name' => 'payment_system',
							'label' => esc_html__('Payment System', 'mptbm_plugin'),
							'desc' => esc_html__('Please Select Payment System.', 'mptbm_plugin'),
							'type' => 'multicheck',
							'default' => array(
								'direct_order' => 'direct_order',
								'woocommerce' => 'woocommerce'
							),
							'options' => array(
								'direct_order' => esc_html__('Pay on service', 'mptbm_plugin'),
								'woocommerce' => esc_html__('woocommerce Payment', 'mptbm_plugin'),
							)
						),
						array(
							'name' => 'direct_book_status',
							'label' => esc_html__('Pay on service Booked Status', 'mptbm_plugin'),
							'desc' => esc_html__('Please Select when and which order status service Will be Booked/Reduced in Pay on service.', 'mptbm_plugin'),
							'type' => 'select',
							'default' => 'completed',
							'options' => array(
								'pending' => esc_html__('Pending', 'mptbm_plugin'),
								'completed' => esc_html__('completed', 'mptbm_plugin')
							)
						),
						array(
							'name' => 'label',
							'label' => $label . ' ' . esc_html__('Label', 'mptbm_plugin'),
							'desc' => esc_html__('If you like to change the label in the dashboard menu, you can change it here.', 'mptbm_plugin'),
							'type' => 'text',
							'default' => 'Transportation'
						),
						array(
							'name' => 'slug',
							'label' => $label . ' ' . esc_html__('Slug', 'mptbm_plugin'),
							'desc' => esc_html__('Please enter the slug name you want. Remember, after changing this slug; you need to flush permalink; go to', 'mptbm_plugin') . '<strong>' . esc_html__('Settings-> Permalinks', 'mptbm_plugin') . '</strong> ' . esc_html__('hit the Save Settings button.', 'mptbm_plugin'),
							'type' => 'text',
							'default' => 'transportation'
						),
						array(
							'name' => 'icon',
							'label' => $label . ' ' . esc_html__('Icon', 'mptbm_plugin'),
							'desc' => esc_html__('If you want to change the  icon in the dashboard menu, you can change it from here, and the Dashboard icon only supports the Dashicons, So please go to ', 'mptbm_plugin') . '<a href=https://developer.wordpress.org/resource/dashicons/#calendar-alt target=_blank>' . esc_html__('Dashicons Library.', 'mptbm_plugin') . '</a>' . esc_html__('and copy your icon code and paste it here.', 'mptbm_plugin'),
							'type' => 'text',
							'default' => 'dashicons-car'
						),
						array(
							'name' => 'category_label',
							'label' => $label . ' ' . esc_html__('Category Label', 'mptbm_plugin'),
							'desc' => esc_html__('If you want to change the  category label in the dashboard menu, you can change it here.', 'mptbm_plugin'),
							'type' => 'text',
							'default' => 'Category'
						),
						array(
							'name' => 'category_slug',
							'label' => $label . ' ' . esc_html__('Category Slug', 'mptbm_plugin'),
							'desc' => esc_html__('Please enter the slug name you want for category. Remember after change this slug you need to flush permalink, Just go to  ', 'mptbm_plugin') . '<strong>' . esc_html__('Settings-> Permalinks', 'mptbm_plugin') . '</strong> ' . esc_html__('hit the Save Settings button.', 'mptbm_plugin'),
							'type' => 'text',
							'default' => 'transportation-category'
						),
						array(
							'name' => 'organizer_label',
							'label' => $label . ' ' . esc_html__('Organizer Label', 'mptbm_plugin'),
							'desc' => esc_html__('If you want to change the  category label in the dashboard menu you can change here', 'mptbm_plugin'),
							'type' => 'text',
							'default' => 'Organizer'
						),
						array(
							'name' => 'organizer_slug',
							'label' => $label . ' ' . esc_html__('Organizer Slug', 'mptbm_plugin'),
							'desc' => esc_html__('Please enter the slug name you want for the  organizer. Remember, after changing this slug, you need to flush the permalinks. Just go to ', 'mptbm_plugin') . '<strong>' . esc_html__('Settings-> Permalinks', 'mptbm_plugin') . '</strong> ' . esc_html__('hit the Save Settings button.', 'mptbm_plugin'),
							'type' => 'text',
							'default' => 'transportation-organizer'
						),
						array(
							'name' => 'expire',
							'label' => $label . ' ' . esc_html__('Expired  Visibility', 'mptbm_plugin'),
							'desc' => esc_html__('If you want to visible expired  ?, please select ', 'mptbm_plugin') . '<strong> ' . esc_html__('Yes', 'mptbm_plugin') . '</strong>' . esc_html__('or to make it hidden, select', 'mptbm_plugin') . '<strong> ' . esc_html__('No', 'mptbm_plugin') . '</strong>' . esc_html__('. Default is', 'mptbm_plugin') . '<strong>' . esc_html__('No', 'mptbm_plugin') . '</strong>',
							'type' => 'select',
							'default' => 'no',
							'options' => array(
								'yes' => esc_html__('Yes', 'mptbm_plugin'),
								'no' => esc_html__('No', 'mptbm_plugin')
							)
						),
						
					)),
					'mptbm_map_api_settings' => apply_filters('filter_mptbm_map_api_settings', array(
						array(
							'name'    => 'gmap_api_key',
							'label'   => esc_html__( 'Google MAP API', 'mptbm_plugin' ),
							'desc'    => esc_html__( 'Please enter your Google Maps API key in this Options.', 'mptbm_plugin' ).'<a class="btn button" href=' . $gm_api_url . ' target="_blank">Click Here to get google api key</a>',
							'type'    => 'text',
							'default' => ''
						),
						
						array(
							'name' => 'mp_latitude',
							'label' => esc_html__('Your Location Latitude', 'mptbm_plugin'),
							'desc' => esc_html__('Please type Your Location Latitude.This are mandatory for google map show. To find latitude please ', 'mptbm_plugin').'<a href="https://www.latlong.net/" target="_blank">'.esc_html__('Click Here', 'mptbm_plugin').'</a>',
							'type' => 'text',
							'default' => '23.81234828905659'
						),
						array(
							'name' => 'mp_longitude',
							'label' => esc_html__('Your Location Longitude', 'mptbm_plugin'),
							'desc' => esc_html__('Please type Your Location Longitude .This are mandatory for google map show. To find latitude please ', 'mptbm_plugin').'<a href="https://www.latlong.net/" target="_blank">'.esc_html__('Click Here', 'mptbm_plugin').'</a>',
							'type' => 'text',
							'default' => '90.41069652669002'
						),
						array(
							'name' => 'mp_country',
							'label' => esc_html__('Country Location', 'mptbm_plugin'),
							'desc' => esc_html__('Select your country Location.This are mandatory for google map show.', 'mptbm_plugin'),
							'type' => 'select',
							'default' => 'BD',
							'options' => MP_Global_Function::get_country_list()
						),
					)),
					'mp_style_settings' => apply_filters('filter_mp_style_settings', array(
						array(
							'name' => 'theme_color',
							'label' => esc_html__('Theme Color', 'mptbm_plugin'),
							'desc' => esc_html__('Select Default Theme Color', 'mptbm_plugin'),
							'type' => 'color',
							'default' => '#0793C9'
						),
						array(
							'name' => 'theme_alternate_color',
							'label' => esc_html__('Theme Alternate Color', 'mptbm_plugin'),
							'desc' => esc_html__('Select Default Theme Alternate  Color that means, if background theme color then it will be text color.', 'mptbm_plugin'),
							'type' => 'color',
							'default' => '#fff'
						),
						array(
							'name' => 'default_text_color',
							'label' => esc_html__('Default Text Color', 'mptbm_plugin'),
							'desc' => esc_html__('Select Default Text  Color.', 'mptbm_plugin'),
							'type' => 'color',
							'default' => '#000'
						),
						array(
							'name' => 'default_font_size',
							'label' => esc_html__('Default Font Size', 'mptbm_plugin'),
							'desc' => esc_html__('Type Default Font Size(in PX Unit).', 'mptbm_plugin'),
							'type' => 'number',
							'default' => '15'
						),
						array(
							'name' => 'font_size_h1',
							'label' => esc_html__('Font Size h1 Title', 'mptbm_plugin'),
							'desc' => esc_html__('Type Font Size Main Title(in PX Unit).', 'mptbm_plugin'),
							'type' => 'number',
							'default' => '35'
						),
						array(
							'name' => 'font_size_h2',
							'label' => esc_html__('Font Size h2 Title', 'mptbm_plugin'),
							'desc' => esc_html__('Type Font Size h2 Title(in PX Unit).', 'mptbm_plugin'),
							'type' => 'number',
							'default' => '25'
						),
						array(
							'name' => 'font_size_h3',
							'label' => esc_html__('Font Size h3 Title', 'mptbm_plugin'),
							'desc' => esc_html__('Type Font Size h3 Title(in PX Unit).', 'mptbm_plugin'),
							'type' => 'number',
							'default' => '22'
						),
						array(
							'name' => 'font_size_h4',
							'label' => esc_html__('Font Size h4 Title', 'mptbm_plugin'),
							'desc' => esc_html__('Type Font Size h4 Title(in PX Unit).', 'mptbm_plugin'),
							'type' => 'number',
							'default' => '20'
						),
						array(
							'name' => 'font_size_h5',
							'label' => esc_html__('Font Size h5 Title', 'mptbm_plugin'),
							'desc' => esc_html__('Type Font Size h5 Title(in PX Unit).', 'mptbm_plugin'),
							'type' => 'number',
							'default' => '18'
						),
						array(
							'name' => 'font_size_h6',
							'label' => esc_html__('Font Size h6 Title', 'mptbm_plugin'),
							'desc' => esc_html__('Type Font Size h6 Title(in PX Unit).', 'mptbm_plugin'),
							'type' => 'number',
							'default' => '16'
						),
						array(
							'name' => 'button_font_size',
							'label' => esc_html__('Button Font Size ', 'mptbm_plugin'),
							'desc' => esc_html__('Type Font Size Button(in PX Unit).', 'mptbm_plugin'),
							'type' => 'number',
							'default' => '18'
						),
						array(
							'name' => 'button_color',
							'label' => esc_html__('Button Text Color', 'mptbm_plugin'),
							'desc' => esc_html__('Select Button Text  Color.', 'mptbm_plugin'),
							'type' => 'color',
							'default' => '#FFF'
						),
						array(
							'name' => 'button_bg',
							'label' => esc_html__('Button Background Color', 'mptbm_plugin'),
							'desc' => esc_html__('Select Button Background  Color.', 'mptbm_plugin'),
							'type' => 'color',
							'default' => '#222'
						),
						array(
							'name' => 'font_size_label',
							'label' => esc_html__('Label Font Size ', 'mptbm_plugin'),
							'desc' => esc_html__('Type Font Size Label(in PX Unit).', 'mptbm_plugin'),
							'type' => 'number',
							'default' => '18'
						),
						array(
							'name' => 'warning_color',
							'label' => esc_html__('Warning Color', 'mptbm_plugin'),
							'desc' => esc_html__('Select Warning  Color.', 'mptbm_plugin'),
							'type' => 'color',
							'default' => '#E67C30'
						),
						array(
							'name' => 'section_bg',
							'label' => esc_html__('Section Background color', 'mptbm_plugin'),
							'desc' => esc_html__('Select Background  Color.', 'mptbm_plugin'),
							'type' => 'color',
							'default' => '#FAFCFE'
						),
					)),
					'mp_add_custom_css' => apply_filters('filter_mp_add_custom_css', array(
						array(
							'name' => 'custom_css',
							'label' => esc_html__('Custom CSS', 'mptbm_plugin'),
							'desc' => esc_html__('Write Your Custom CSS Code Here', 'mptbm_plugin'),
							'type' => 'textarea',
						)
					))
				);
				return array_merge($default_fields, $settings_fields);
			}
		}
		new  MPTBM_Settings_Global();
	}