<?php
	/*
	   * @Author 		engr.sumonazma@gmail.com
	   * Copyright: 	mage-people.com
	   */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('MPTBM_Settings')) {
		class MPTBM_Settings {
			public function __construct() {
				add_action('add_meta_boxes', [$this, 'settings_meta']);
			}
			//************************//
			public function settings_meta() {
				$label = MPTBM_Function::get_name();
				$cpt = MPTBM_Function::get_cpt();
				add_meta_box('mp_meta_box_panel', $label . __('Information Settings '.'<span class="version"> V'.MPTBM_PLUGIN_VERSION.'</span>', 'ecab-taxi-booking-manager'), array($this, 'settings'), $cpt, 'normal', 'high');
			}
			//******************************//
			public function settings() {
				$post_id = get_the_id();
				wp_nonce_field('mptbm_transportation_type_nonce', 'mptbm_transportation_type_nonce');
				?>
                <input type="hidden" name="mptbm_post_id" value="<?php echo esc_attr($post_id); ?>"/>
                <div class="mpStyle mptbm_settings">
                    <div class="mpTabs leftTabs">
                        <ul class="tabLists">
                            <li data-tabs-target="#mptbm_general_info">
                                <span class="pe-1 fas fa-tools"></span><?php esc_html_e('General Info', 'ecab-taxi-booking-manager'); ?>
                            </li>
                            <li data-tabs-target="#mptbm_settings_date">
                                <span class="pe-1 fas fa-calendar-alt"></span><?php esc_html_e('Date', 'ecab-taxi-booking-manager'); ?>
                            </li>
                            <li data-tabs-target="#mptbm_settings_pricing">
                                <span class="pe-1 fas fa-hand-holding-usd"></span><?php esc_html_e('Pricing', 'ecab-taxi-booking-manager'); ?>
                            </li>
                            <li data-tabs-target="#mptbm_settings_ex_service">
                                <span class="pe-1 fas fa-puzzle-piece"></span><?php esc_html_e('Extra Service', 'ecab-taxi-booking-manager'); ?>
                            </li>
							<?php
								if (class_exists('MPTBM_Plugin_Pro')) {
									?>
                                    <li data-tabs-target="#mptbm_setting_operation_area">
                                        <span class="pe-1 fas fa-map-marker-alt"></span><?php esc_html_e('Operation Area', 'ecab-taxi-booking-manager'); ?>
                                    </li>
									<?php do_action('add_mptbm_settings_tab_after_ex_service'); ?>
									<?php
								}
							?>
                            <li data-tabs-target="#wbtm_settings_tax">
                                <span class="fas fa-hand-holding-usd"></span><?php esc_html_e('Tax Configure', 'ecab-taxi-booking-manager'); ?>
                            </li>
                        </ul>
                        <div class="tabsContent">
							<?php do_action('add_mptbm_settings_tab_content', $post_id); ?>
                        </div>
                    </div>
                </div>
				<?php
			}
			public static function description_array($key) {
				$des = array(
					'mptbm_display_faq' => esc_html__('Frequently Asked Questions about this tour that customers need to know', 'ecab-taxi-booking-manager'),
					'mptbm_display_why_choose_us' => esc_html__('Why choose us section, write a key feature list that tourist get Trust to book. you can switch it off.', 'ecab-taxi-booking-manager'),
					'why_chose_us' => esc_html__('Please add why to book feature list one by one.', 'ecab-taxi-booking-manager'),
					'mptbm_display_activities' => esc_html__('By default Activities type is ON but you can keep it off by switching this option', 'ecab-taxi-booking-manager'),
					'activities' => esc_html__('Add a list of tour activities for this tour.', 'ecab-taxi-booking-manager'),
					'mptbm_activity_name' => esc_html__('The name is how it appears on your site.', 'ecab-taxi-booking-manager'),
					'mptbm_activity_description' => esc_html__('The description is not prominent by default; however, some themes may show it.', 'ecab-taxi-booking-manager'),
					'mptbm_display_related' => esc_html__('Please select a related transport from this list.', 'ecab-taxi-booking-manager'),
					'mptbm_section_title_style' => esc_html__('By default Section title is style one', 'ecab-taxi-booking-manager'),
					'mptbm_ticketing_system' => esc_html__('By default, the ticket purchase system is open. Once you check the availability, you can choose the system that best suits your needs.', 'ecab-taxi-booking-manager'),
					'mptbm_display_seat_details' => esc_html__('By default Seat Info is ON but you can keep it off by switching this option', 'ecab-taxi-booking-manager'),
					'mptbm_display_get_question' => esc_html__('By default Display Get a Questions is ON but you can keep it off by switching this option', 'ecab-taxi-booking-manager'),
					'mptbm_display_sidebar' => esc_html__('By default Sidebar Widget is Off but you can keep it ON by switching this option', 'ecab-taxi-booking-manager'),
					'mptbm_display_duration' => esc_html__('By default Duration is ON but you can keep it off by switching this option', 'ecab-taxi-booking-manager'),
					'mptbm_contact_phone' => esc_html__('Please Enter contact phone no', 'ecab-taxi-booking-manager'),
					'mptbm_contact_text' => esc_html__('Please Enter Contact Section Text', 'ecab-taxi-booking-manager'),
					'mptbm_contact_email' => esc_html__('Please Enter contact phone email', 'ecab-taxi-booking-manager'),
					//================//
					'display_mptbm_features' => esc_html__('By default slider is ON but you can keep it off by switching this option', 'ecab-taxi-booking-manager'),
					'display_mp_slider' => esc_html__('By default slider is ON but you can keep it off by switching this option', 'ecab-taxi-booking-manager'),
					'display_mptbm_extra_services' => esc_html__('By default Extra services is ON but you can keep it off by switching this option', 'ecab-taxi-booking-manager'),
					'mptbm_extra_services_global' => esc_html__('Please add your global extra service which add any transport', 'ecab-taxi-booking-manager'),
					'mptbm_extra_services_id' => esc_html__('Please select your global extra service', 'ecab-taxi-booking-manager'),
					//================//
					'mp_slider_images' => esc_html__('Please upload images for gallery', 'ecab-taxi-booking-manager'),
					//''          => esc_html__( '', 'ecab-taxi-booking-manager' ),
					//================//
					'mptbm_initial_price' => esc_html__('The initial price that will be added as the starting price', 'ecab-taxi-booking-manager'),
					'mptbm_price_based' => esc_html__('This is a price calculation model, price will vary based on your choice', 'ecab-taxi-booking-manager'),
					'mptbm_km_price' => esc_html__('Set Price per KM', 'ecab-taxi-booking-manager'),
					'mptbm_hour_price' => esc_html__('Set Price per Hour', 'ecab-taxi-booking-manager'),
					'mptbm_waiting_price' => esc_html__('Set waiting price per hour', 'ecab-taxi-booking-manager'),
				);
				$des = apply_filters('mptbm_filter_description_array', $des);
				return $des[$key];
			}
			public static function info_text($key) {
				$data = self::description_array($key);
				if ($data) {
					?>
					<?php echo esc_html($data); ?>
					<?php
				}
			}
		}
		new MPTBM_Settings();
	}
