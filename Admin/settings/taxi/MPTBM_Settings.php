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
				add_action('save_post', array($this, 'save_settings'), 99, 1);
			}
			//************************//
			public function settings_meta() {
				$label = MPTBM_Function::get_name();
				$cpt = MPTBM_Function::get_cpt();
				add_meta_box('mp_meta_box_panel', '<span class="fas fa-cogs"></span>' . $label . esc_html__(' Information Settings : ', 'mptbm_plugin') . get_the_title(get_the_id()), array($this, 'settings'), $cpt, 'normal', 'high');
			}
			//******************************//
			public function settings() {
				$post_id = get_the_id();
				wp_nonce_field('mptbm_transportation_type_nonce', 'mptbm_transportation_type_nonce');
				?>
				<input type="hidden" name="mptbm_post_id" value="<?php echo esc_attr($post_id); ?>"/>
				<div class="mpStyle">
					<div class="mpTabs leftTabs">
						<ul class="tabLists">
							<li data-tabs-target="#mptbm_general_info">
								<span class="fas fa-tools"></span><?php esc_html_e('General Info', 'mptbm_plugin'); ?>
							</li>
							<li data-tabs-target="#mptbm_settings_date">
								<span class="fas fa-calendar-alt"></span><?php esc_html_e('Date', 'mptbm_plugin'); ?>
							</li>
							<li data-tabs-target="#mptbm_settings_pricing">
								<span class="fas fa-hand-holding-usd"></span><?php esc_html_e('Pricing', 'mptbm_plugin'); ?>
							</li>
							<li data-tabs-target="#mptbm_settings_ex_service">
								<span class="fas fa-hand-holding-usd"></span><?php esc_html_e('Extra Service', 'mptbm_plugin'); ?>
							</li>
							<?php do_action('add_mptbm_settings_tab_after_ex_service'); ?>
							<li data-tabs-target="#mptbm_settings_gallery">
								<span class="fas fa-images"></span><?php esc_html_e('Gallery ', 'mptbm_plugin'); ?>
							</li>
						</ul>
						<div class="tabsContent tab-content">
							<?php do_action('add_mptbm_settings_tab_content', $post_id); ?>
						</div>
					</div>
				</div>
				<?php
			}
			public static function description_array($key) {
				$des = array(
					'mptbm_display_faq' => esc_html__('Frequently Asked Questions about this tour that customers need to know', 'mptbm_plugin'),
					'mptbm_display_why_choose_us' => esc_html__('Why choose us section, write a key feature list that tourist get Trust to book. you can switch it off.', 'mptbm_plugin'),
					'why_chose_us' => esc_html__('Please add why to book feature list one by one.', 'mptbm_plugin'),
					'mptbm_display_activities' => esc_html__('By default Activities type is ON but you can keep it off by switching this option', 'mptbm_plugin'),
					'activities' => esc_html__('Add a list of tour activities for this tour.', 'mptbm_plugin'),
					'mptbm_activity_name' => esc_html__('The name is how it appears on your site.', 'mptbm_plugin'),
					'mptbm_activity_description' => esc_html__('The description is not prominent by default; however, some themes may show it.', 'mptbm_plugin'),
					'mptbm_display_related' => esc_html__('Please select a related transport from this list.', 'mptbm_plugin'),
					'mptbm_section_title_style' => esc_html__('By default Section title is style one', 'mptbm_plugin'),
					'mptbm_ticketing_system' => esc_html__('By default, the ticket purchase system is open. Once you check the availability, you can choose the system that best suits your needs.', 'mptbm_plugin'),
					'mptbm_display_seat_details' => esc_html__('By default Seat Info is ON but you can keep it off by switching this option', 'mptbm_plugin'),
					'mptbm_display_get_question' => esc_html__('By default Display Get a Questions is ON but you can keep it off by switching this option', 'mptbm_plugin'),
					'mptbm_display_sidebar' => esc_html__('By default Sidebar Widget is Off but you can keep it ON by switching this option', 'mptbm_plugin'),
					'mptbm_display_duration' => esc_html__('By default Duration is ON but you can keep it off by switching this option', 'mptbm_plugin'),
					'mptbm_contact_phone' => esc_html__('Please Enter contact phone no', 'mptbm_plugin'),
					'mptbm_contact_text' => esc_html__('Please Enter Contact Section Text', 'mptbm_plugin'),
					'mptbm_contact_email' => esc_html__('Please Enter contact phone email', 'mptbm_plugin'),
					//================//
					'display_mptbm_features' => esc_html__('By default slider is ON but you can keep it off by switching this option', 'mptbm_plugin'),
					'display_mp_slider' => esc_html__('By default slider is ON but you can keep it off by switching this option', 'mptbm_plugin'),
					'display_mptbm_extra_services' => esc_html__('By default Extra services is ON but you can keep it off by switching this option', 'mptbm_plugin'),
					'mptbm_extra_services_global' => esc_html__('Please add your global extra service which add any transport', 'mptbm_plugin'),
					'mptbm_extra_services_id' => esc_html__('Please select your global extra service', 'mptbm_plugin'),
					//================//
					'mp_slider_images' => esc_html__('Please upload images for gallery', 'mptbm_plugin'),
					//''          => esc_html__( '', 'mptbm_plugin' ),
				);
				$des = apply_filters('mptbm_filter_description_array', $des);
				return $des[$key];
			}
			public static function info_text($key) {
				$data = self::description_array($key);
				if ($data) {
					?>
					<i class="info_text">
						<span class="fas fa-info-circle"></span>
						<?php echo esc_html($data); ?>
					</i>
					<?php
				}
			}
			public function save_settings($post_id) {
				if (!isset($_POST['mptbm_transportation_type_nonce']) || !wp_verify_nonce($_POST['mptbm_transportation_type_nonce'], 'mptbm_transportation_type_nonce') && defined('DOING_AUTOSAVE') && DOING_AUTOSAVE && !current_user_can('edit_post', $post_id)) {
					return;
				}
				do_action('mptbm_settings_save', $post_id);
			}
		}
		new MPTBM_Settings();
	}