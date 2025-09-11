<?php
/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.
//echo '<pre>';print_r();echo '</pre>';y.
if (!class_exists('MPTBM_Dummy_Import')) {
	class MPTBM_Dummy_Import
	{
		public function __construct()
		{
			add_action('admin_init', array($this, 'dummy_import'), 99);
		}
		public function dummy_import()
		{
			$dummy_post_inserted = get_option('mptbm_dummy_already_inserted', 'no');
			$count_existing_event = wp_count_posts('mptbm_rent')->publish;
			$plugin_active = MP_Global_Function::check_plugin('ecab-taxi-booking-manager', 'MPTBM_Plugin.php');
			if ($count_existing_event == 0 && $plugin_active == 1 && $dummy_post_inserted != 'yes') {
				$dummy_post_data = $this->dummy_post_data();
				$this->add_post($dummy_post_data);
				$this->location_taxonomy();
				$this->driver_status_taxanomy();
				flush_rewrite_rules();
				update_option('mptbm_dummy_already_inserted', 'yes');
			}
		}
		public function add_post($dummy_cpt)
		{
			$pre_extra_service_id =0;
			if (array_key_exists('custom_post', $dummy_cpt)) {
				foreach ($dummy_cpt['custom_post'] as $custom_post => $dummy_post) {
					unset($args);
					$args = array(
						'post_type' => $custom_post,
						'posts_per_page' => -1,
					);
					unset($post);
					$post = new WP_Query($args);
					if ($post->post_count == 0) {
						foreach ($dummy_post as $dummy_data) {
							$args = array();
							if (isset($dummy_data['name'])) {
								$args['post_title'] = $dummy_data['name'];
							}
							if (isset($dummy_data['content'])) {
								$args['post_content'] = $dummy_data['content'];
							}
							$args['post_status'] = 'publish';
							$args['post_type'] = $custom_post;
							$post_id = wp_insert_post($args);
							$pre_extra_service_id = $this->get_extra_service_last_id( 'mptbm_extra_services' );
							if (array_key_exists('post_data', $dummy_data)) {
								foreach ($dummy_data['post_data'] as $meta_key => $data) {
									if ($meta_key == 'feature_image') {
										$url = $data;
										$image = media_sideload_image($url, $post_id, null, 'id');
										set_post_thumbnail($post_id, $image);
									}
									if ($meta_key == 'mptbm_extra_services_id') {
										update_post_meta($post_id, $meta_key, $pre_extra_service_id);
									} else {
										update_post_meta($post_id, $meta_key, $data);
									}
								}
							}
						}
					}
				}
			}
		}
		public function get_extra_service_last_id( $post_type ) {
			$latest_post = get_posts([
				'post_type'      => $post_type,
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
				'fields'         => 'ids',
			]);
		
			return ! empty( $latest_post ) ? $latest_post[0] : null;
		}

		public function location_taxonomy(): array
		{
			$taxonomy_data = array(
				'locations' => array(
					'Dhaka',
					'Chittagong',
					'Sylhet',
					'Rajshahi'
				),

			);

			foreach ($taxonomy_data as $taxonomy => $terms) {
				foreach ($terms as $term) {
					wp_insert_term($term, $taxonomy);
				}
			}

			return $taxonomy_data;
		}

		public function driver_status_taxanomy()
		{
			$taxonomy_data = array(
				'mptbm_service_status' => array(
					'Received',
					'Declined',
					'Processing',
					'Completed'
				),

			);
			foreach ($taxonomy_data as $taxonomy => $terms) {
				foreach ($terms as $term) {
					$term_insert_result = wp_insert_term($term, $taxonomy);
				}
			}
			return $taxonomy_data;
		}


		public function dummy_post_data(): array
		{

			$feature_image[0] = 'https://img.freepik.com/free-photo/white-sport-sedan-with-colorful-tuning-road_114579-5044.jpg';
			$feature_image[1] = 'https://img.freepik.com/free-photo/blue-car-driving-road_114579-4056.jpg';
			$feature_image[2] = 'https://img.freepik.com/free-photo/yellow-sport-car-with-black-autotuning-highway-front-view_114579-5060.jpg';
			$feature_image[3] = 'https://img.freepik.com/free-photo/black-luxury-jeep-driving-road_114579-4058.jpg';
			$feature_image[4] = 'https://img.freepik.com/free-photo/vintage-sedan-car-driving-road_114579-5065.jpg';
			$feature_image[5] = 'https://img.freepik.com/free-photo/sport-car-with-black-white-autotuning-driving-forest_114579-4076.jpg';
			$feature_image[6] = 'https://img.freepik.com/free-photo/black-luxury-jeep-driving-road_114579-4058.jpg';
			$feature_image[7] = 'https://img.freepik.com/free-photo/grey-luxury-sedan-car-sunset_114579-4045.jpg';
			return [
				'custom_post' => [
					'mptbm_extra_services' => [
						0 => [
							'name' => 'Pre-defined Extra Services',
							'post_data' => array(
								'feature_image' => $feature_image[0],
								'mptbm_extra_service_infos' => array(
									0 => array(
										'service_icon' => 'mi mi-child',
										'service_name' => 'Child Seat',
										'service_price' => '50',
										'service_qty_type' => 'inputbox',
										'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
									),
									1 => array(
										'service_icon' => 'mi mi-flower-bouquet',
										'service_name' => 'Bouquet of Flowers',
										'service_price' => '150',
										'service_qty_type' => 'inputbox',
										'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
									),
									2 => array(
										'service_icon' => 'mi mi-juice',
										'service_name' => 'Welcome Drink',
										'service_price' => '30',
										'service_qty_type' => 'inputbox',
										'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
									),
									3 => array(
										'service_icon' => 'mi mi-plane',
										'service_name' => 'Airport Assistance and Hostess Service',
										'service_price' => '30',
										'service_qty_type' => 'inputbox',
										'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
									),
									4 => array(
										'service_icon' => 'mi mi-user-police',
										'service_name' => 'Bodyguard Service',
										'service_price' => '30',
										'service_qty_type' => 'inputbox',
										'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
									),
								)
							)
						],
					],
					
					'mptbm_rent' => [
						0 => [
							'name' => 'BMW 5 Series',
							'post_data' => [
								'feature_image' => $feature_image[1],
								//General_settings
								'mptbm_features' => [
									0 => array(
										'label' => 'Name',
										'icon' => 'mi mi-car-alt',
										'image' => '',
										'text' => 'BMW 5 Series Long'
									),
									1 => array(
										'label' => 'Model',
										'icon' => 'mi mi-badge-check',
										'image' => '',
										'text' => 'EXPRW'
									),
									2 => array(
										'label' => 'Engine',
										'icon' => 'mi mi-engine',
										'image' => '',
										'text' => '3000'
									),
									3 => array(
										'label' => 'Fuel Type',
										'icon' => 'mi mi-gas-pump-alt',
										'image' => '',
										'text' => 'Diesel'
									),
									4 => array(
										'label' => 'Transmission',
										'icon' => 'mi mi-steering-wheel',
										'image' => '',
										'text' => 'Automatic'
									),
									5 => array(
										'label' => 'Seating Capacity',
										'icon' => 'mi mi-person-seat',
										'image' => '',
										'text' => '5'
									),
								],
								//price_settings
								'mptbm_price_based' => 'inclusive',
								'mptbm_km_price' => 1.2,
								'mptbm_hour_price' => 10,
								'mptbm_terms_price_info' => [
									[
										'start_location' => 'chittagong',
										'end_location' => 'dhaka',
										'price' => 10
									],
									[
										'start_location' => 'chittagong',
										'end_location' => 'rajshahi',
										'price' => 20
									],
									[
										'start_location' => 'chittagong',
										'end_location' => 'sylhet',
										'price' => 30
									]
								],
								'display_mptbm_extra_services' => 'on',
								'mptbm_extra_services_id' => '',
								//faq_settings
								'mptbm_display_faq' => 'on',
								'mptbm_faq' => [
									0 => [
										'title' => 'What can I expect to see at The Mentalist at Planet Hollywood Resort and Casino?',
										'content' => 'Comedy, magic and mind-reading! The Mentalist has the ability to get inside the minds of audience members, revealing everything from their names, hometowns and anniversaries to their wildest wishes.',
									],
									1 => [
										'title' => 'Where is The Mentalist located?',
										'content' => 'The V Theater is located inside the Miracle Mile Shops at the Planet Hollywood Resort & Casino.',
									],
									2 => [
										'title' => 'Can I purchase alcohol at the venue during The Mentalist!?',
										'content' => 'Absolutely! Drinks are available for purchase at the Showgirl Bar outside of the theater and may be brought into the showroom, however, no other outside food or drink will be allowed in the theater.',
									],
									3 => [
										'title' => 'Is The Mentalist appropriate for children?',
										'content' => 'Due to language, this show is recommended for guests 16 years old and over.',
									],
									4 => [
										'title' => 'Do I need to exchange my ticket upon arrival at The Mentalist!?',
										'content' => 'Please pick up your tickets at the V Theater Box Office with a valid photo ID for the lead traveler at least 30 minutes prior to show time (box office opens at 11 am). Seating will begin 15 minutes before showtime.',
									],
								],
								//why chose us_settings
								'mptbm_display_why_choose_us' => 'on',
								'mptbm_why_choose_us' => [
									0 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
									1 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
									2 => 'Watch as Gerry McCambridge performs comedy and magic',
								],
								//gallery_settings
								'mp_slider_images' => [10, 20, 30, 40, 50, 60, 70, 80, 90, 100],
								//date_settings
								'mptbm_available_for_all_time' => 'on',
								'mptbm_active_days' => '60',
								'mptbm_default_start_time' => '0.5',
								'mptbm_default_end_time' => '23.5',
								//extras_settings
								'mptbm_display_contact' => 'on',
								'mptbm_email' => 'example.gmail.com',
								'mptbm_phone' => '123456789',
								'mptbm_text' => 'Do not hesitate to give us a call. We are an expert team and we are happy to talk to you.',
							]
						],
						1 => [
							'name' => 'Cadillac Escalade Limousine',
							'post_data' => [
								'feature_image' => $feature_image[2],
								//General_settings
								'mptbm_features' => [
									0 => array(
										'label' => 'Name',
										'icon' => 'mi mi-car-alt',
										'image' => '',
										'text' => 'BMW 5 Series Long'
									),
									1 => array(
										'label' => 'Model',
										'icon' => 'mi mi-badge-check',
										'image' => '',
										'text' => 'EXPRW'
									),
									2 => array(
										'label' => 'Engine',
										'icon' => 'mi mi-engine',
										'image' => '',
										'text' => '3000'
									),
									3 => array(
										'label' => 'Fuel Type',
										'icon' => 'mi mi-gas-pump-alt',
										'image' => '',
										'text' => 'Diesel'
									),
									4 => array(
										'label' => 'Transmission',
										'icon' => 'mi mi-steering-wheel',
										'image' => '',
										'text' => 'Automatic'
									),
									5 => array(
										'label' => 'Seating Capacity',
										'icon' => 'mi mi-person-seat',
										'image' => '',
										'text' => '5'
									),
								],
								//price_settings
								'mptbm_price_based' => 'inclusive',
								'mptbm_km_price' => 1.2,
								'mptbm_hour_price' => 10,
								'mptbm_terms_price_info' => [
									[
										'start_location' => 'chittagong',
										'end_location' => 'dhaka',
										'price' => 10
									],
									[
										'start_location' => 'chittagong',
										'end_location' => 'rajshahi',
										'price' => 20
									],
									[
										'start_location' => 'chittagong',
										'end_location' => 'sylhet',
										'price' => 30
									]
								],
								//Extra Services
								'display_mptbm_extra_services' => 'on',
								'mptbm_extra_services_id' => '',
								//faq_settings
								'mptbm_display_faq' => 'on',
								'mptbm_faq' => [
									0 => [
										'title' => 'What can I expect to see at The Mentalist at Planet Hollywood Resort and Casino?',
										'content' => 'Comedy, magic and mind-reading! The Mentalist has the ability to get inside the minds of audience members, revealing everything from their names, hometowns and anniversaries to their wildest wishes.',
									],
									1 => [
										'title' => 'Where is The Mentalist located?',
										'content' => 'The V Theater is located inside the Miracle Mile Shops at the Planet Hollywood Resort & Casino.',
									],
									2 => [
										'title' => 'Can I purchase alcohol at the venue during The Mentalist!?',
										'content' => 'Absolutely! Drinks are available for purchase at the Showgirl Bar outside of the theater and may be brought into the showroom, however, no other outside food or drink will be allowed in the theater.',
									],
									3 => [
										'title' => 'Is The Mentalist appropriate for children?',
										'content' => 'Due to language, this show is recommended for guests 16 years old and over.',
									],
									4 => [
										'title' => 'Do I need to exchange my ticket upon arrival at The Mentalist!?',
										'content' => 'Please pick up your tickets at the V Theater Box Office with a valid photo ID for the lead traveler at least 30 minutes prior to show time (box office opens at 11 am). Seating will begin 15 minutes before showtime.',
									],
								],
								//why chose us_settings
								'mptbm_display_why_choose_us' => 'on',
								'mptbm_why_choose_us' => [
									0 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
									1 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
									2 => 'Watch as Gerry McCambridge performs comedy and magic',
								],
								//date_settings
								'mptbm_available_for_all_time' => 'on',
								'mptbm_active_days' => '60',
								'mptbm_default_start_time' => '0.5',
								'mptbm_default_end_time' => '23.5',
								//gallery_settings
								'mp_slider_images' => [10, 20, 30, 40, 50, 60, 70, 80, 90, 100],
								//extras_settings
								'mptbm_display_contact' => 'on',
								'mptbm_email' => 'example.gmail.com',
								'mptbm_phone' => '123456789',
								'mptbm_text' => 'Do not hesitage to give us a call. We are an expert team and we are happy to talk to you.',
							]
						],
						2 => [
							'name' => 'Hummer New York Limousine',
							'post_data' => [
								'feature_image' => $feature_image[3],
								//General_settings
								'mptbm_features' => [
									0 => array(
										'label' => 'Name',
										'icon' => 'mi mi-car-alt',
										'image' => '',
										'text' => 'BMW 5 Series Long'
									),
									1 => array(
										'label' => 'Model',
										'icon' => 'mi mi-badge-check',
										'image' => '',
										'text' => 'EXPRW'
									),
									2 => array(
										'label' => 'Engine',
										'icon' => 'mi mi-engine',
										'image' => '',
										'text' => '3000'
									),
									3 => array(
										'label' => 'Fuel Type',
										'icon' => 'mi mi-gas-pump-alt',
										'image' => '',
										'text' => 'Diesel'
									),
									4 => array(
										'label' => 'Transmission',
										'icon' => 'mi mi-steering-wheel',
										'image' => '',
										'text' => 'Automatic'
									),
									5 => array(
										'label' => 'Seating Capacity',
										'icon' => 'mi mi-person-seat',
										'image' => '',
										'text' => '5'
									),
								],
								//price_settings
								'mptbm_price_based' => 'inclusive',
								'mptbm_km_price' => 1.2,
								'mptbm_hour_price' => 10,
								'mptbm_terms_price_info' => [
									[
										'start_location' => 'chittagong',
										'end_location' => 'dhaka',
										'price' => 10
									],
									[
										'start_location' => 'chittagong',
										'end_location' => 'rajshahi',
										'price' => 20
									],
									[
										'start_location' => 'chittagong',
										'end_location' => 'sylhet',
										'price' => 30
									]
								],
								//Extra Services
								'display_mptbm_extra_services' => 'on',
								'mptbm_extra_services_id' => '',
								//faq_settings
								'mptbm_display_faq' => 'on',
								'mptbm_faq' => [
									0 => [
										'title' => 'What can I expect to see at The Mentalist at Planet Hollywood Resort and Casino?',
										'content' => 'Comedy, magic and mind-reading! The Mentalist has the ability to get inside the minds of audience members, revealing everything from their names, hometowns and anniversaries to their wildest wishes.',
									],
									1 => [
										'title' => 'Where is The Mentalist located?',
										'content' => 'The V Theater is located inside the Miracle Mile Shops at the Planet Hollywood Resort & Casino.',
									],
									2 => [
										'title' => 'Can I purchase alcohol at the venue during The Mentalist!?',
										'content' => 'Absolutely! Drinks are available for purchase at the Showgirl Bar outside of the theater and may be brought into the showroom, however, no other outside food or drink will be allowed in the theater.',
									],
									3 => [
										'title' => 'Is The Mentalist appropriate for children?',
										'content' => 'Due to language, this show is recommended for guests 16 years old and over.',
									],
									4 => [
										'title' => 'Do I need to exchange my ticket upon arrival at The Mentalist!?',
										'content' => 'Please pick up your tickets at the V Theater Box Office with a valid photo ID for the lead traveler at least 30 minutes prior to show time (box office opens at 11 am). Seating will begin 15 minutes before showtime.',
									],
								],
								//why chose us_settings
								'mptbm_display_why_choose_us' => 'on',
								'mptbm_why_choose_us' => [
									0 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
									1 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
									2 => 'Watch as Gerry McCambridge performs comedy and magic',
								],
								//gallery_settings
								'mp_slider_images' => [10, 20, 30, 40, 50, 60, 70, 80, 90, 100],
								//date_settings
								'mptbm_available_for_all_time' => 'on',
								'mptbm_active_days' => '60',
								'mptbm_default_start_time' => '0.5',
								'mptbm_default_end_time' => '23.5',
								//extras_settings
								'mptbm_display_contact' => 'on',
								'mptbm_email' => 'example.gmail.com',
								'mptbm_phone' => '123456789',
								'mptbm_text' => 'Do not hesitage to give us a call. We are an expert team and we are happy to talk to you.',
							]
						],
						3 => [
							'name' => 'Cadillac Escalade SUV',
							'post_data' => [
								'feature_image' => $feature_image[4],
								//General_settings
								'mptbm_features' => [
									0 => array(
										'label' => 'Name',
										'icon' => 'mi mi-car-alt',
										'image' => '',
										'text' => 'BMW 5 Series Long'
									),
									1 => array(
										'label' => 'Model',
										'icon' => 'mi mi-badge-check',
										'image' => '',
										'text' => 'EXPRW'
									),
									2 => array(
										'label' => 'Engine',
										'icon' => 'mi mi-engine',
										'image' => '',
										'text' => '3000'
									),
									3 => array(
										'label' => 'Fuel Type',
										'icon' => 'mi mi-gas-pump-alt',
										'image' => '',
										'text' => 'Diesel'
									),
									4 => array(
										'label' => 'Transmission',
										'icon' => 'mi mi-steering-wheel',
										'image' => '',
										'text' => 'Automatic'
									),
									5 => array(
										'label' => 'Seating Capacity',
										'icon' => 'mi mi-person-seat',
										'image' => '',
										'text' => '5'
									),
								],
								//price_settings
								'mptbm_price_based' => 'inclusive',
								'mptbm_km_price' => 1.2,
								'mptbm_hour_price' => 10,
								'mptbm_terms_price_info' => [
									[
										'start_location' => 'chittagong',
										'end_location' => 'dhaka',
										'price' => 10
									],
									[
										'start_location' => 'chittagong',
										'end_location' => 'rajshahi',
										'price' => 20
									],
									[
										'start_location' => 'chittagong',
										'end_location' => 'sylhet',
										'price' => 30
									]
								],
								//Extra Services
								'display_mptbm_extra_services' => 'on',
								'mptbm_extra_services_id' => '',
								//faq_settings
								'mptbm_display_faq' => 'on',
								'mptbm_faq' => [
									0 => [
										'title' => 'What can I expect to see at The Mentalist at Planet Hollywood Resort and Casino?',
										'content' => 'Comedy, magic and mind-reading! The Mentalist has the ability to get inside the minds of audience members, revealing everything from their names, hometowns and anniversaries to their wildest wishes.',
									],
									1 => [
										'title' => 'Where is The Mentalist located?',
										'content' => 'The V Theater is located inside the Miracle Mile Shops at the Planet Hollywood Resort & Casino.',
									],
									2 => [
										'title' => 'Can I purchase alcohol at the venue during The Mentalist!?',
										'content' => 'Absolutely! Drinks are available for purchase at the Showgirl Bar outside of the theater and may be brought into the showroom, however, no other outside food or drink will be allowed in the theater.',
									],
									3 => [
										'title' => 'Is The Mentalist appropriate for children?',
										'content' => 'Due to language, this show is recommended for guests 16 years old and over.',
									],
									4 => [
										'title' => 'Do I need to exchange my ticket upon arrival at The Mentalist!?',
										'content' => 'Please pick up your tickets at the V Theater Box Office with a valid photo ID for the lead traveler at least 30 minutes prior to show time (box office opens at 11 am). Seating will begin 15 minutes before showtime.',
									],
								],
								//why chose us_settings
								'mptbm_display_why_choose_us' => 'on',
								'mptbm_why_choose_us' => [
									0 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
									1 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
									2 => 'Watch as Gerry McCambridge performs comedy and magic',
								],
								//gallery_settings
								'mp_slider_images' => [10, 20, 30, 40, 50, 60, 70, 80, 90, 100],
								//date_settings
								'mptbm_available_for_all_time' => 'on',
								'mptbm_active_days' => '60',
								'mptbm_default_start_time' => '0.5',
								'mptbm_default_end_time' => '23.5',
								//extras_settings
								'mptbm_display_contact' => 'on',
								'mptbm_email' => 'example.gmail.com',
								'mptbm_phone' => '123456789',
								'mptbm_text' => 'Do not hesitate to give us a call. We are an expert team and we are happy to talk to you.',
							]
						],
						4 => [
							'name' => 'Ford Tourneo',
							'post_data' => [
								'feature_image' => $feature_image[5],
								//General_settings
								'mptbm_features' => [
									0 => array(
										'label' => 'Name',
										'icon' => 'mi mi-car-alt',
										'image' => '',
										'text' => 'BMW 5 Series Long'
									),
									1 => array(
										'label' => 'Model',
										'icon' => 'mi mi-badge-check',
										'image' => '',
										'text' => 'EXPRW'
									),
									2 => array(
										'label' => 'Engine',
										'icon' => 'mi mi-engine',
										'image' => '',
										'text' => '3000'
									),
									3 => array(
										'label' => 'Fuel Type',
										'icon' => 'mi mi-gas-pump-alt',
										'image' => '',
										'text' => 'Diesel'
									),
									4 => array(
										'label' => 'Transmission',
										'icon' => 'mi mi-steering-wheel',
										'image' => '',
										'text' => 'Automatic'
									),
									5 => array(
										'label' => 'Seating Capacity',
										'icon' => 'mi mi-person-seat',
										'image' => '',
										'text' => '5'
									),
								],
								//price_settings
								'mptbm_price_based' => 'inclusive',
								'mptbm_km_price' => 1.2,
								'mptbm_hour_price' => 10,
								'mptbm_terms_price_info' => [
									[
										'start_location' => 'chittagong',
										'end_location' => 'dhaka',
										'price' => 10
									],
									[
										'start_location' => 'chittagong',
										'end_location' => 'rajshahi',
										'price' => 20
									],
									[
										'start_location' => 'chittagong',
										'end_location' => 'sylhet',
										'price' => 30
									]
								],
								'display_mptbm_extra_services' => 'on',
								'mptbm_extra_services_id' => '',
								//faq_settings
								'mptbm_display_faq' => 'on',
								'mptbm_faq' => [
									0 => [
										'title' => 'What can I expect to see at The Mentalist at Planet Hollywood Resort and Casino?',
										'content' => 'Comedy, magic and mind-reading! The Mentalist has the ability to get inside the minds of audience members, revealing everything from their names, hometowns and anniversaries to their wildest wishes.',
									],
									1 => [
										'title' => 'Where is The Mentalist located?',
										'content' => 'The V Theater is located inside the Miracle Mile Shops at the Planet Hollywood Resort & Casino.',
									],
									2 => [
										'title' => 'Can I purchase alcohol at the venue during The Mentalist!?',
										'content' => 'Absolutely! Drinks are available for purchase at the Showgirl Bar outside of the theater and may be brought into the showroom, however, no other outside food or drink will be allowed in the theater.',
									],
									3 => [
										'title' => 'Is The Mentalist appropriate for children?',
										'content' => 'Due to language, this show is recommended for guests 16 years old and over.',
									],
									4 => [
										'title' => 'Do I need to exchange my ticket upon arrival at The Mentalist!?',
										'content' => 'Please pick up your tickets at the V Theater Box Office with a valid photo ID for the lead traveler at least 30 minutes prior to show time (box office opens at 11 am). Seating will begin 15 minutes before showtime.',
									],
								],
								//why chose us_settings
								'mptbm_display_why_choose_us' => 'on',
								'mptbm_why_choose_us' => [
									0 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
									1 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
									2 => 'Watch as Gerry McCambridge performs comedy and magic',
								],
								//gallery_settings
								'mp_slider_images' => [10, 20, 30, 40, 50, 60, 70, 80, 90, 100],
								//date_settings
								'mptbm_available_for_all_time' => 'on',
								'mptbm_active_days' => '60',
								'mptbm_default_start_time' => '0.5',
								'mptbm_default_end_time' => '23.5',
								//extras_settings
								'mptbm_display_contact' => 'on',
								'mptbm_email' => 'example.gmail.com',
								'mptbm_phone' => '123456789',
								'mptbm_text' => 'Do not hesitate to give us a call. We are an expert team and we are happy to talk to you.',
							]
						],
						5 => [
							'name' => 'Mercedes-Benz E220',
							'post_data' => [
								'feature_image' => $feature_image[6],
								//General_settings
								'mptbm_features' => [
									0 => array(
										'label' => 'Name',
										'icon' => 'mi mi-car-alt',
										'image' => '',
										'text' => 'BMW 5 Series Long'
									),
									1 => array(
										'label' => 'Model',
										'icon' => 'mi mi-badge-check',
										'image' => '',
										'text' => 'EXPRW'
									),
									2 => array(
										'label' => 'Engine',
										'icon' => 'mi mi-engine',
										'image' => '',
										'text' => '3000'
									),
									3 => array(
										'label' => 'Fuel Type',
										'icon' => 'mi mi-gas-pump-alt',
										'image' => '',
										'text' => 'Diesel'
									),
									4 => array(
										'label' => 'Transmission',
										'icon' => 'mi mi-steering-wheel',
										'image' => '',
										'text' => 'Automatic'
									),
									5 => array(
										'label' => 'Seating Capacity',
										'icon' => 'mi mi-person-seat',
										'image' => '',
										'text' => '5'
									),
								],
								//price_settings
								'mptbm_price_based' => 'inclusive',
								'mptbm_km_price' => 1.2,
								'mptbm_hour_price' => 10,
								'mptbm_terms_price_info' => [
									[
										'start_location' => 'chittagong',
										'end_location' => 'dhaka',
										'price' => 10
									],
									[
										'start_location' => 'chittagong',
										'end_location' => 'rajshahi',
										'price' => 20
									],
									[
										'start_location' => 'chittagong',
										'end_location' => 'sylhet',
										'price' => 30
									]
								],
								'display_mptbm_extra_services' => 'on',
								'mptbm_extra_services_id' => '',
								//faq_settings
								'mptbm_display_faq' => 'on',
								'mptbm_faq' => [
									0 => [
										'title' => 'What can I expect to see at The Mentalist at Planet Hollywood Resort and Casino?',
										'content' => 'Comedy, magic and mind-reading! The Mentalist has the ability to get inside the minds of audience members, revealing everything from their names, hometowns and anniversaries to their wildest wishes.',
									],
									1 => [
										'title' => 'Where is The Mentalist located?',
										'content' => 'The V Theater is located inside the Miracle Mile Shops at the Planet Hollywood Resort & Casino.',
									],
									2 => [
										'title' => 'Can I purchase alcohol at the venue during The Mentalist!?',
										'content' => 'Absolutely! Drinks are available for purchase at the Showgirl Bar outside of the theater and may be brought into the showroom, however, no other outside food or drink will be allowed in the theater.',
									],
									3 => [
										'title' => 'Is The Mentalist appropriate for children?',
										'content' => 'Due to language, this show is recommended for guests 16 years old and over.',
									],
									4 => [
										'title' => 'Do I need to exchange my ticket upon arrival at The Mentalist!?',
										'content' => 'Please pick up your tickets at the V Theater Box Office with a valid photo ID for the lead traveler at least 30 minutes prior to show time (box office opens at 11 am). Seating will begin 15 minutes before showtime.',
									],
								],
								//why chose us_settings
								'mptbm_display_why_choose_us' => 'on',
								'mptbm_why_choose_us' => [
									0 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
									1 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
									2 => 'Watch as Gerry McCambridge performs comedy and magic',
								],
								//gallery_settings
								'mp_slider_images' => [10, 20, 30, 40, 50, 60, 70, 80, 90, 100],
								//date_settings
								'mptbm_available_for_all_time' => 'on',
								'mptbm_active_days' => '60',
								'mptbm_default_start_time' => '0.5',
								'mptbm_default_end_time' => '23.5',
								//extras_settings
								'mptbm_display_contact' => 'on',
								'mptbm_email' => 'example.gmail.com',
								'mptbm_phone' => '123456789',
								'mptbm_text' => 'Do not hesitage to give us a call. We are an expert team and we are happy to talk to you.',
							]
						],
						6 => [
							'name' => 'Fiat Panda',
							'post_data' => [
								'feature_image' => $feature_image[7],
								//General_settings
								'mptbm_features' => [
									0 => array(
										'label' => 'Name',
										'icon' => 'mi mi-car-alt',
										'image' => '',
										'text' => 'BMW 5 Series Long'
									),
									1 => array(
										'label' => 'Model',
										'icon' => 'mi mi-badge-check',
										'image' => '',
										'text' => 'EXPRW'
									),
									2 => array(
										'label' => 'Engine',
										'icon' => 'mi mi-engine',
										'image' => '',
										'text' => '3000'
									),
									3 => array(
										'label' => 'Fuel Type',
										'icon' => 'mi mi-gas-pump-alt',
										'image' => '',
										'text' => 'Diesel'
									),
									4 => array(
										'label' => 'Transmission',
										'icon' => 'mi mi-engine',
										'image' => '',
										'text' => 'Automatic'
									),
									5 => array(
										'label' => 'Seating Capacity',
										'icon' => 'mi mi-person-seat',
										'image' => '',
										'text' => '5'
									),
								],
								//price_settings
								'mptbm_price_based' => 'inclusive',
								'mptbm_km_price' => 1.2,
								'mptbm_hour_price' => 10,
								'mptbm_terms_price_info' => [
									[
										'start_location' => 'chittagong',
										'end_location' => 'dhaka',
										'price' => 10
									],
									[
										'start_location' => 'chittagong',
										'end_location' => 'rajshahi',
										'price' => 20
									],
									[
										'start_location' => 'chittagong',
										'end_location' => 'sylhet',
										'price' => 30
									]
								],
								'display_mptbm_extra_services' => 'on',
								'mptbm_extra_services_id' => '',
								//faq_settings
								'mptbm_display_faq' => 'on',
								'mptbm_faq' => [
									0 => [
										'title' => 'What can I expect to see at The Mentalist at Planet Hollywood Resort and Casino?',
										'content' => 'Comedy, magic and mind-reading! The Mentalist has the ability to get inside the minds of audience members, revealing everything from their names, hometowns and anniversaries to their wildest wishes.',
									],
									1 => [
										'title' => 'Where is The Mentalist located?',
										'content' => 'The V Theater is located inside the Miracle Mile Shops at the Planet Hollywood Resort & Casino.',
									],
									2 => [
										'title' => 'Can I purchase alcohol at the venue during The Mentalist!?',
										'content' => 'Absolutely! Drinks are available for purchase at the Showgirl Bar outside of the theater and may be brought into the showroom, however, no other outside food or drink will be allowed in the theater.',
									],
									3 => [
										'title' => 'Is The Mentalist appropriate for children?',
										'content' => 'Due to language, this show is recommended for guests 16 years old and over.',
									],
									4 => [
										'title' => 'Do I need to exchange my ticket upon arrival at The Mentalist!?',
										'content' => 'Please pick up your tickets at the V Theater Box Office with a valid photo ID for the lead traveler at least 30 minutes prior to show time (box office opens at 11 am). Seating will begin 15 minutes before showtime.',
									],
								],
								//why chose us_settings
								'mptbm_display_why_choose_us' => 'on',
								'mptbm_why_choose_us' => [
									0 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
									1 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
									2 => 'Watch as Gerry McCambridge performs comedy and magic',
								],
								//gallery_settings
								'mp_slider_images' => [10, 20, 30, 40, 50, 60, 70, 80, 90, 100],
								//date_settings
								'mptbm_available_for_all_time' => 'on',
								'mptbm_active_days' => '60',
								'mptbm_default_start_time' => '0.5',
								'mptbm_default_end_time' => '23.5',
								//extras_settings
								'mptbm_display_contact' => 'on',
								'mptbm_email' => 'example.gmail.com',
								'mptbm_phone' => '123456789',
								'mptbm_text' => 'Do not hesitage to give us a call. We are an expert team and we are happy to talk to you.',
							]
						],
					]
				]
			];
		}
	}
	new MPTBM_Dummy_Import();
}
