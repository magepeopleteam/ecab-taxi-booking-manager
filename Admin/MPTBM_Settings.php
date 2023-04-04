<?php
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'MPTBM_Settings' ) ) {
		class MPTBM_Settings {
			public function __construct() {
				add_action( 'add_meta_boxes', [ $this, 'settings_meta' ] );
			}
			//************************//
			public function settings_meta() {
				$label = MPTBM_Function::get_name();
				$cpt   = MPTBM_Function::get_cpt_name();
				add_meta_box( 'mp_meta_box_panel', '<span class="fas fa-cogs"></span>' . $label . esc_html__( ' Information Settings : ', 'mptbm_plugin' ) . get_the_title( get_the_id() ), array( $this, 'settings' ), $cpt, 'normal', 'high' );
			}
			//******************************//
			public function settings() {
				$post_id = get_the_id();
				$label   = MPTBM_Function::get_name();
				wp_nonce_field('mptbm_transportation_type_nonce', 'mptbm_transportation_type_nonce');
				?>
				<div class="mpStyle">
					<div class="mpTabs leftTabs">
						<ul class="tabLists">
							<li data-tabs-target="#mptbm_general_info">
								<span class="fas fa-tools"></span><?php esc_html_e( 'General Info', 'mptbm_plugin' ); ?>
							</li>
							<li data-tabs-target="#mptbm_settings_pricing">
								<span class="fas fa-hand-holding-usd"></span><?php esc_html_e( 'Pricing', 'mptbm_plugin' ); ?>
							</li>
							<li data-tabs-target="#mptbm_settings_extra_service">
								<span class="fas fa-hand-holding-usd"></span><?php esc_html_e( 'Extra Service', 'mptbm_plugin' ); ?>
							</li>
							<li data-tabs-target="#mptbm_settings_gallery">
								<span class="fas fa-images"></span><?php esc_html_e( 'Gallery ', 'mptbm_plugin' ); ?>
							</li>
						</ul>
						<div class="tabsContent tab-content">
							<?php
								do_action( 'add_mptbm_settings_tab_content', $post_id );
							?>
						</div>
					</div>
				</div>
				<?php
			}
			public static function description_array( $key ) {
				$des = array(
					'start_price'                  => esc_html__( 'Price Starts  are displayed on the tour details and tour list pages. If you would like to hide them, you can do so by switching the option.', 'mptbm_plugin' ),
					'max_people'                   => esc_html__( 'This tour only allows a maximum of X people. This number is displayed for informational purposes only and can be hidden by switching the option.', 'mptbm_plugin' ),
					'age_range'                    => esc_html__( 'The age limit for this tour is X to Y years old. This is for information purposes only.', 'mptbm_plugin' ),
					'start_place'                  => esc_html__( 'This will be the starting point for the tour group. The tour will begin from here.', 'mptbm_plugin' ),
					'location'                     => esc_html__( 'Please select the name of the location you wish to create a tour for. If you would like to create a new location, please go to the Tour page.', 'mptbm_plugin' ),
					'full_location'                => esc_html__( 'Please Type Full Address of the location, it will use for the google map', 'mptbm_plugin' ),
					'short_des'                    => esc_html__( 'For a Tour short description, toggle this switching option.', 'mptbm_plugin' ),
					'duration'                     => esc_html__( 'Please enter the number of days and nights for your tour package.', 'mptbm_plugin' ),
					'mptbm_new_location_name'       => esc_html__( 'Please add the new location to the location list when creating a tour.', 'mptbm_plugin' ),
					'mptbm_location_description'    => esc_html__( 'The description is not always visible by default, but some themes may display it.', 'mptbm_plugin' ),
					'mptbm_location_address'        => esc_html__( 'Please Enter the Full Address of Your Location', 'mptbm_plugin' ),
					'mptbm_location_country'        => esc_html__( 'Please select your tour location country from the list below.', 'mptbm_plugin' ),
					'mptbm_location_image'          => esc_html__( 'Please select an image for your tour location.', 'mptbm_plugin' ),
					'mptbm_display_registration'    => esc_html__( "If you don't want to use the tour registration feature, you can just keep it turned off.", 'mptbm_plugin' ),
					'mptbm_short_code'              => esc_html__( 'You can display this Ticket type list with the add to cart button anywhere on your website by copying the shortcode and using it on any post or page.', 'mptbm_plugin' ),
					'mptbm_display_schedule'        => esc_html__( 'Please find the detailed timeline for you tour as day 1, day 2 etc.', 'mptbm_plugin' ),
					'add_new_feature_popup'        => esc_html__( 'To include or exclude a feature from your tour, please select it from the list below. To create a new feature, go to the Tour page.', 'mptbm_plugin' ),
					'mptbm_display_include_service' => esc_html__( 'The price of this tour includes the service, which you can keep hidden by turning it off.', 'mptbm_plugin' ),
					'mptbm_display_exclude_service' => esc_html__( 'The price of this tour excludes the service, which you can keep hidden by turning it off.', 'mptbm_plugin' ),
					'mptbm_feature_name'            => esc_html__( 'The name is how it appears on your site.', 'mptbm_plugin' ),
					'mptbm_feature_description'     => esc_html__( 'The description is not prominent by default; however, some themes may show it.', 'mptbm_plugin' ),
					'mptbm_display_hiphop'          => esc_html__( 'By default Places You\'ll See  is ON but you can keep it off by switching this option', 'mptbm_plugin' ),
					'mptbm_place_you_see'           => esc_html__( 'Please Select Place Name. To create new place, go Tour->Places; or click on the Create New Place button', 'mptbm_plugin' ),
					'mptbm_place_name'              => esc_html__( 'The name is how it appears on your site.', 'mptbm_plugin' ),
					'mptbm_place_description'       => esc_html__( 'The description is not prominent by default; however, some themes may show it.', 'mptbm_plugin' ),
					'mptbm_place_image'             => esc_html__( 'Please Select Place Image.', 'mptbm_plugin' ),
					'mptbm_display_faq'             => esc_html__( 'Frequently Asked Questions about this tour that customers need to know', 'mptbm_plugin' ),
					'mptbm_display_why_choose_us'   => esc_html__( 'Why choose us section, write a key feature list that tourist get Trust to book. you can switch it off.', 'mptbm_plugin' ),
					'why_chose_us'                 => esc_html__( 'Please add why to book feature list one by one.', 'mptbm_plugin' ),
					'mptbm_display_activities'      => esc_html__( 'By default Activities type is ON but you can keep it off by switching this option', 'mptbm_plugin' ),
					'activities'                   => esc_html__( 'Add a list of tour activities for this tour.', 'mptbm_plugin' ),
					'mptbm_activity_name'           => esc_html__( 'The name is how it appears on your site.', 'mptbm_plugin' ),
					'mptbm_activity_description'    => esc_html__( 'The description is not prominent by default; however, some themes may show it.', 'mptbm_plugin' ),
					'mptbm_display_related'         => esc_html__( 'Please select a related tour from this list.', 'mptbm_plugin' ),
					'mptbm_section_title_style'     => esc_html__( 'By default Section title is style one', 'mptbm_plugin' ),
					'mptbm_ticketing_system'        => esc_html__( 'By default, the ticket purchase system is open. Once you check the availability, you can choose the system that best suits your needs.', 'mptbm_plugin' ),
					'mptbm_display_seat_details'    => esc_html__( 'By default Seat Info is ON but you can keep it off by switching this option', 'mptbm_plugin' ),
					'mptbm_display_tour_type'       => esc_html__( 'By default Tour type is ON but you can keep it off by switching this option', 'mptbm_plugin' ),
					'mptbm_display_hotels'          => esc_html__( 'By default Display hotels is ON but you can keep it off by switching this option', 'mptbm_plugin' ),
					'mptbm_display_get_question'    => esc_html__( 'By default Display Get a Questions is ON but you can keep it off by switching this option', 'mptbm_plugin' ),
					'mptbm_display_sidebar'         => esc_html__( 'By default Sidebar Widget is Off but you can keep it ON by switching this option', 'mptbm_plugin' ),
					'mptbm_display_duration'        => esc_html__( 'By default Duration is ON but you can keep it off by switching this option', 'mptbm_plugin' ),
					'mptbm_related_tour'            => esc_html__( 'Please add related  Tour', 'mptbm_plugin' ),
					'mptbm_contact_phone'           => esc_html__( 'Please Enter contact phone no', 'mptbm_plugin' ),
					'mptbm_contact_text'            => esc_html__( 'Please Enter Contact Section Text', 'mptbm_plugin' ),
					'mptbm_contact_email'           => esc_html__( 'Please Enter contact phone email', 'mptbm_plugin' ),
					'mptbm_type'                    => esc_html__( 'By default Type is General', 'mptbm_plugin' ),
					'mptbm_display_advance'         => esc_html__( 'By default Advance option is Off but you can keep it On by switching this option', 'mptbm_plugin' ),
					'mptbm_display_extra_advance'   => esc_html__( 'By default Advance option is on but you can keep it off by switching this option', 'mptbm_plugin' ),
					'mptbm_display_hotel_distance'  => esc_html__( 'Please add Distance Description', 'mptbm_plugin' ),
					'mptbm_display_hotel_rating'    => esc_html__( 'Please Select Hotel rating ', 'mptbm_plugin' ),
					'mptbm_display_tour_guide'      => esc_html__( 'You can keep off tour guide information by switching this option', 'mptbm_plugin' ),
					'mptbm_tour_guide'              => esc_html__( 'To add tour guide information, simply select an option from the list below.', 'mptbm_plugin' ),
					//======Slider==========//
					'mptbm_display_slider'         => esc_html__( 'By default slider is ON but you can keep it off by switching this option', 'mptbm_plugin' ),
					'mptbm_slider_images'          => esc_html__( 'Please upload images for gallery', 'mptbm_plugin' ),
					//''          => esc_html__( '', 'mptbm_plugin' ),
				);
				$des = apply_filters( 'mptbm_filter_description_array', $des );
				return $des[ $key ];
			}
			public static function info_text( $key ) {
				$data = self::description_array( $key );
				if ( $data ) {
					?>
					<i class="info_text">
						<span class="fas fa-info-circle"></span>
						<?php echo esc_html( $data ); ?>
					</i>
					<?php
				}
			}
		}
		new MPTBM_Settings();
	}