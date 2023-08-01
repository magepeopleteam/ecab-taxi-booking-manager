<?php
	/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'MPTBM_Dummy_Import' ) ) {
		class MPTBM_Dummy_Import {
			public function __construct() {
				$this->dummy_import();
			}
			private function dummy_import() {
				$dummy_post = get_option( 'mptbm_dummy_already_inserted' );
				$all_post   = MP_Global_Function::query_post_type( 'mptbm_rent' );
				if ( $all_post->post_count == 0 && $dummy_post != 'yes' ) {
					$dummy_data = $this->dummy_data();
					foreach ( $dummy_data as $type => $dummy ) {
						if ( $type == 'taxonomy' ) {
							foreach ( $dummy as $taxonomy => $dummy_taxonomy ) {
								$check_taxonomy = MP_Global_Function::get_taxonomy( $taxonomy );
								if ( is_string( $check_taxonomy ) || sizeof( $check_taxonomy ) == 0 ) {
									foreach ( $dummy_taxonomy as $taxonomy_data ) {
										wp_insert_term( $taxonomy_data['name'], $taxonomy );
									}
								}
								//echo '<pre>'; print_r( $query); echo '</pre>';
							}
						}
						if ( $type == 'custom_post' ) {
							foreach ( $dummy as $custom_post => $dummy_post ) {
								$post = MP_Global_Function::query_post_type( $custom_post );
								if ( $post->post_count == 0 ) {
									foreach ( $dummy_post as $dummy_data ) {
										$title   = $dummy_data['name'];
										$post_id = wp_insert_post( [
											'post_title'  => $title,
											'post_status' => 'publish',
											'post_type'   => $custom_post
										] );
										if ( array_key_exists( 'post_data', $dummy_data ) ) {
											foreach ( $dummy_data['post_data'] as $meta_key => $data ) {
												update_post_meta( $post_id, $meta_key, $data );
											}
										}
									}
								}
							}
						}
					}
					update_option( 'mptbm_dummy_already_inserted', 'yes' );
				}
			}
			public function dummy_data(): array {
				return [
					'custom_post' => [
						'mptbm_rent' => [
							0 => [
								'name'      => 'BMW 5 Series',
								'post_data' => [
									'mp_thumbnail'        => '100',
									//General_settings
									'mptbm_features'=>[
										array(
											'label' => 'Name',
											'icon' => 'fas fa-car-side',
											'image' => '',
											'text' => 'BMW 5 Series Long'
										),
										array(
											'label' => 'Model',
											'icon' => 'fas fa-car',
											'image' => '',
											'text' => 'EXPRW'
										),
										array(
											'label' => 'Engine',
											'icon' => 'fas fa-cogs',
											'image' => '3000',
											'text' => ''
										),
										array(
											'label' => 'Fuel Type',
											'icon' => 'fas fa-gas-pump',
											'image' => '',
											'text' => 'Diesel'
										),
										array(
											'label' => 'Maximum Passenger',
											'icon' => 'fas fa-users',
											'image' => '',
											'text' => '4'
										),
										array(
											'label' => 'Maximum Bag',
											'icon' => 'fas fa-briefcase',
											'image' => '',
											'text' => '3'
										)
									],
									//price_settings
									'mptbm_price_based'           => 'distance',
									'mptbm_km_price'              => 1.2,
									'mptbm_hour_price'            => 10,
									'mptbm_manual_price_info'     => [
										0 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Khulna',
											'price'          => 150,
										],
										1 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Rajshahi',
											'price'          => 200,
										],
										2 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Shylet',
											'price'          => 170,
										],
										4 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Chattogram',
											'price'          => 250,
										],
										5 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Feni',
											'price'          => 150,
										],
									],
									'mptbm_extra_service_data'     => [
										0 => [
											'service_icon' => '',
											'service_name'   => 'Driver',
											'service_qty_type'          => 'inputbox',
											'extra_service_description'          => 150,
											'price'          => 50,
										]
									],
									//faq_settings
									'mptbm_display_faq'           => 'on',
									'mptbm_faq'                   => [
										0 => [
											'title'   => 'What can I expect to see at The Mentalist at Planet Hollywood Resort and Casino?',
											'content' => 'Comedy, magic and mind-reading! The Mentalist has the ability to get inside the minds of audience members, revealing everything from their names, hometowns and anniversaries to their wildest wishes.',
										],
										1 => [
											'title'   => 'Where is The Mentalist located?',
											'content' => 'The V Theater is located inside the Miracle Mile Shops at the Planet Hollywood Resort & Casino.',
										],
										2 => [
											'title'   => 'Can I purchase alcohol at the venue during The Mentalist!?',
											'content' => 'Absolutely! Drinks are available for purchase at the Showgirl Bar outside of the theater and may be brought into the showroom, however, no other outside food or drink will be allowed in the theater.',
										],
										3 => [
											'title'   => 'Is The Mentalist appropriate for children?',
											'content' => 'Due to language, this show is recommended for guests 16 years old and over.',
										],
										4 => [
											'title'   => 'Do I need to exchange my ticket upon arrival at The Mentalist!?',
											'content' => 'Please pick up your tickets at the V Theater Box Office with a valid photo ID for the lead traveler at least 30 minutes prior to show time (box office opens at 11 am). Seating will begin 15 minutes before showtime.',
										],
									],
									//why chose us_settings
									'mptbm_display_why_choose_us' => 'on',
									'mptbm_why_choose_us'         => [
										0 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
										1 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
										2 => 'Watch as Gerry McCambridge performs comedy and magic',
									],
									//gallery_settings
									'mp_slider_images'         => [ 120, 130, 140, 150, 160, 170, 180, 190, 200, 210, 220, 230, 240, 250, 260, 270, 280, 290, 300 ],
									//extras_settings
									'mptbm_display_contact'       => 'on',
									'mptbm_email'                 => 'example.gmail.com',
									'mptbm_phone'                 => '123456789',
									'mptbm_text'                  => 'Do not hesitage to give us a call. We are an expert team and we are happy to talk to you.',
								]
							],
							1 => [
								'name'      => 'Cadillac Escalade Limousine',
								'post_data' => [
									'mp_thumbnail'        => '100',
									//General_settings
									'mptbm_name'                  => 'Cadillac Escalade Limousine',
									'mptbm_model'                 => 'CADESR',
									'mptbm_engine'                => '2500',
									'mptbm_interior_color'        => "Laser Blue",
									'mptbm_power'                 => 305,
									'mptbm_fuel_type'             => 'Diesel',
									'mptbm_length'                => '7.1 meters',
									'mptbm_exterior_color'        => 'silver',
									'mptbm_transmission'          => 'Manual',
									'mptbm_extras'                => 'Leather Seats, LED Lighting, Radio',
									//price_settings
									'mptbm_price_based'           => 'duration',
									'mptbm_km_price'              => 1.5,
									'mptbm_hour_price'            => 20,
									'mptbm_manual_price_info'     => [
										0 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Khulna',
											'price'          => 150,
										],
										1 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Rajshahi',
											'price'          => 200,
										],
										2 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Shylet',
											'price'          => 170,
										],
										4 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Chattogram',
											'price'          => 250,
										],
										5 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Feni',
											'price'          => 150,
										],
									],
									'mptbm_extra_service_data'     => [
										0 => [
											'service_icon' => '',
											'service_name'   => 'Driver',
											'service_qty_type'          => 'inputbox',
											'extra_service_description'          => 150,
											'price'          => 50,
										]
									],
									//faq_settings
									'mptbm_display_faq'           => 'on',
									'mptbm_faq'                   => [
										0 => [
											'title'   => 'What can I expect to see at The Mentalist at Planet Hollywood Resort and Casino?',
											'content' => 'Comedy, magic and mind-reading! The Mentalist has the ability to get inside the minds of audience members, revealing everything from their names, hometowns and anniversaries to their wildest wishes.',
										],
										1 => [
											'title'   => 'Where is The Mentalist located?',
											'content' => 'The V Theater is located inside the Miracle Mile Shops at the Planet Hollywood Resort & Casino.',
										],
										2 => [
											'title'   => 'Can I purchase alcohol at the venue during The Mentalist!?',
											'content' => 'Absolutely! Drinks are available for purchase at the Showgirl Bar outside of the theater and may be brought into the showroom, however, no other outside food or drink will be allowed in the theater.',
										],
										3 => [
											'title'   => 'Is The Mentalist appropriate for children?',
											'content' => 'Due to language, this show is recommended for guests 16 years old and over.',
										],
										4 => [
											'title'   => 'Do I need to exchange my ticket upon arrival at The Mentalist!?',
											'content' => 'Please pick up your tickets at the V Theater Box Office with a valid photo ID for the lead traveler at least 30 minutes prior to show time (box office opens at 11 am). Seating will begin 15 minutes before showtime.',
										],
									],
									//why chose us_settings
									'mptbm_display_why_choose_us' => 'on',
									'mptbm_why_choose_us'         => [
										0 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
										1 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
										2 => 'Watch as Gerry McCambridge performs comedy and magic',
									],
									//gallery_settings
									'mp_slider_images'         => [ 120, 130, 140, 150, 160, 170, 180, 190, 200, 210, 220, 230, 240, 250, 260, 270, 280, 290, 300 ],
									//extras_settings
									'mptbm_display_contact'       => 'on',
									'mptbm_email'                 => 'example.gmail.com',
									'mptbm_phone'                 => '123456789',
									'mptbm_text'                  => 'Do not hesitage to give us a call. We are an expert team and we are happy to talk to you.',
								]
							],
							2=> [
								'name'      => 'Hummer New York Limousine',
								'post_data' => [
									'mp_thumbnail'        => '100',
									//General_settings
									'mptbm_name'                  => 'Hummer New York Limousine',
									'mptbm_model'                 => 'HUMYL',
									'mptbm_engine'                => '3500',
									'mptbm_interior_color'        => "Laser Blue",
									'mptbm_power'                 => 305,
									'mptbm_fuel_type'             => 'Diesel',
									'mptbm_length'                => '6.1 meters',
									'mptbm_exterior_color'        => 'silver',
									'mptbm_transmission'          => 'Manual',
									'mptbm_extras'                => 'Leather Seats, LED Lighting, Radio',
									//price_settings
									'mptbm_price_based'           => 'manual',
									'mptbm_km_price'              => 1.5,
									'mptbm_hour_price'            => 20,
									'mptbm_manual_price_info'     => [
										0 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Khulna',
											'price'          => 150,
										],
										1 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Rajshahi',
											'price'          => 200,
										],
										2 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Shylet',
											'price'          => 170,
										],
										4 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Chattogram',
											'price'          => 250,
										],
										5 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Feni',
											'price'          => 150,
										],
									],
									'mptbm_extra_service_data'     => [
										0 => [
											'service_icon' => '',
											'service_name'   => 'Driver',
											'service_qty_type'          => 'inputbox',
											'extra_service_description'          => 150,
											'price'          => 50,
										]
									],
									//faq_settings
									'mptbm_display_faq'           => 'on',
									'mptbm_faq'                   => [
										0 => [
											'title'   => 'What can I expect to see at The Mentalist at Planet Hollywood Resort and Casino?',
											'content' => 'Comedy, magic and mind-reading! The Mentalist has the ability to get inside the minds of audience members, revealing everything from their names, hometowns and anniversaries to their wildest wishes.',
										],
										1 => [
											'title'   => 'Where is The Mentalist located?',
											'content' => 'The V Theater is located inside the Miracle Mile Shops at the Planet Hollywood Resort & Casino.',
										],
										2 => [
											'title'   => 'Can I purchase alcohol at the venue during The Mentalist!?',
											'content' => 'Absolutely! Drinks are available for purchase at the Showgirl Bar outside of the theater and may be brought into the showroom, however, no other outside food or drink will be allowed in the theater.',
										],
										3 => [
											'title'   => 'Is The Mentalist appropriate for children?',
											'content' => 'Due to language, this show is recommended for guests 16 years old and over.',
										],
										4 => [
											'title'   => 'Do I need to exchange my ticket upon arrival at The Mentalist!?',
											'content' => 'Please pick up your tickets at the V Theater Box Office with a valid photo ID for the lead traveler at least 30 minutes prior to show time (box office opens at 11 am). Seating will begin 15 minutes before showtime.',
										],
									],
									//why chose us_settings
									'mptbm_display_why_choose_us' => 'on',
									'mptbm_why_choose_us'         => [
										0 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
										1 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
										2 => 'Watch as Gerry McCambridge performs comedy and magic',
									],
									//gallery_settings
									'mp_slider_images'         => [ 120, 130, 140, 150, 160, 170, 180, 190, 200, 210, 220, 230, 240, 250, 260, 270, 280, 290, 300 ],
									//extras_settings
									'mptbm_display_contact'       => 'on',
									'mptbm_email'                 => 'example.gmail.com',
									'mptbm_phone'                 => '123456789',
									'mptbm_text'                  => 'Do not hesitage to give us a call. We are an expert team and we are happy to talk to you.',
								]
							],
							3=> [
								'name'      => 'Cadillac Escalade SUV',
								'post_data' => [
									'mp_thumbnail'        => '100',
									//General_settings
									'mptbm_name'                  => 'Cadillac Escalade SUV',
									'mptbm_model'                 => 'CASUV',
									'mptbm_engine'                => '2800',
									'mptbm_interior_color'        => "Blue",
									'mptbm_power'                 => 285,
									'mptbm_fuel_type'             => 'Diesel',
									'mptbm_length'                => '5.6 meters',
									'mptbm_exterior_color'        => 'silver',
									'mptbm_transmission'          => 'Manual',
									'mptbm_extras'                => 'Leather Seats, LED Lighting, Radio',
									//price_settings
									'mptbm_price_based'           => 'manual',
									'mptbm_km_price'              => 1.5,
									'mptbm_hour_price'            => 20,
									'mptbm_manual_price_info'     => [
										0 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Khulna',
											'price'          => 150,
										],
										1 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Rajshahi',
											'price'          => 200,
										],
										2 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Shylet',
											'price'          => 170,
										],
										4 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Chattogram',
											'price'          => 250,
										],
										5 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Feni',
											'price'          => 150,
										],
									],
									'mptbm_extra_service_data'     => [
										0 => [
											'service_icon' => '',
											'service_name'   => 'Driver',
											'service_qty_type'          => 'inputbox',
											'extra_service_description'          => 150,
											'price'          => 50,
										],
									],
									//faq_settings
									'mptbm_display_faq'           => 'on',
									'mptbm_faq'                   => [
										0 => [
											'title'   => 'What can I expect to see at The Mentalist at Planet Hollywood Resort and Casino?',
											'content' => 'Comedy, magic and mind-reading! The Mentalist has the ability to get inside the minds of audience members, revealing everything from their names, hometowns and anniversaries to their wildest wishes.',
										],
										1 => [
											'title'   => 'Where is The Mentalist located?',
											'content' => 'The V Theater is located inside the Miracle Mile Shops at the Planet Hollywood Resort & Casino.',
										],
										2 => [
											'title'   => 'Can I purchase alcohol at the venue during The Mentalist!?',
											'content' => 'Absolutely! Drinks are available for purchase at the Showgirl Bar outside of the theater and may be brought into the showroom, however, no other outside food or drink will be allowed in the theater.',
										],
										3 => [
											'title'   => 'Is The Mentalist appropriate for children?',
											'content' => 'Due to language, this show is recommended for guests 16 years old and over.',
										],
										4 => [
											'title'   => 'Do I need to exchange my ticket upon arrival at The Mentalist!?',
											'content' => 'Please pick up your tickets at the V Theater Box Office with a valid photo ID for the lead traveler at least 30 minutes prior to show time (box office opens at 11 am). Seating will begin 15 minutes before showtime.',
										],
									],
									//why chose us_settings
									'mptbm_display_why_choose_us' => 'on',
									'mptbm_why_choose_us'         => [
										0 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
										1 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
										2 => 'Watch as Gerry McCambridge performs comedy and magic',
									],
									//gallery_settings
									'mp_slider_images'         => [ 120, 130, 140, 150, 160, 170, 180, 190, 200, 210, 220, 230, 240, 250, 260, 270, 280, 290, 300 ],
									//extras_settings
									'mptbm_display_contact'       => 'on',
									'mptbm_email'                 => 'example.gmail.com',
									'mptbm_phone'                 => '123456789',
									'mptbm_text'                  => 'Do not hesitage to give us a call. We are an expert team and we are happy to talk to you.',
								]
							],
							4=> [
								'name'      => 'Ford Tourneo',
								'post_data' => [
									'mp_thumbnail'        => '100',
									//General_settings
									'mptbm_name'                  => 'Ford Tourneo',
									'mptbm_model'                 => 'FORD_DD',
									'mptbm_engine'                => '3200',
									'mptbm_interior_color'        => "Blue",
									'mptbm_power'                 => 285,
									'mptbm_fuel_type'             => 'Octane',
									'mptbm_length'                => '5.6 meters',
									'mptbm_exterior_color'        => 'silver',
									'mptbm_transmission'          => 'Manual',
									'mptbm_extras'                => 'Leather Seats, LED Lighting, Radio',
									//price_settings
									'mptbm_price_based'           => 'manual',
									'mptbm_km_price'              => 1.5,
									'mptbm_hour_price'            => 20,
									'mptbm_manual_price_info'     => [
										0 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Khulna',
											'price'          => 150,
										],
										1 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Rajshahi',
											'price'          => 200,
										],
										2 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Shylet',
											'price'          => 170,
										],
										4 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Chattogram',
											'price'          => 250,
										],
										5 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Feni',
											'price'          => 150,
										],
									],
									'mptbm_extra_service_data'     => [
										0 => [
											'service_icon' => '',
											'service_name'   => 'Driver',
											'service_qty_type'          => 'inputbox',
											'extra_service_description'          => 150,
											'price'          => 50,
										],
									],
									//faq_settings
									'mptbm_display_faq'           => 'on',
									'mptbm_faq'                   => [
										0 => [
											'title'   => 'What can I expect to see at The Mentalist at Planet Hollywood Resort and Casino?',
											'content' => 'Comedy, magic and mind-reading! The Mentalist has the ability to get inside the minds of audience members, revealing everything from their names, hometowns and anniversaries to their wildest wishes.',
										],
										1 => [
											'title'   => 'Where is The Mentalist located?',
											'content' => 'The V Theater is located inside the Miracle Mile Shops at the Planet Hollywood Resort & Casino.',
										],
										2 => [
											'title'   => 'Can I purchase alcohol at the venue during The Mentalist!?',
											'content' => 'Absolutely! Drinks are available for purchase at the Showgirl Bar outside of the theater and may be brought into the showroom, however, no other outside food or drink will be allowed in the theater.',
										],
										3 => [
											'title'   => 'Is The Mentalist appropriate for children?',
											'content' => 'Due to language, this show is recommended for guests 16 years old and over.',
										],
										4 => [
											'title'   => 'Do I need to exchange my ticket upon arrival at The Mentalist!?',
											'content' => 'Please pick up your tickets at the V Theater Box Office with a valid photo ID for the lead traveler at least 30 minutes prior to show time (box office opens at 11 am). Seating will begin 15 minutes before showtime.',
										],
									],
									//why chose us_settings
									'mptbm_display_why_choose_us' => 'on',
									'mptbm_why_choose_us'         => [
										0 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
										1 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
										2 => 'Watch as Gerry McCambridge performs comedy and magic',
									],
									//gallery_settings
									'mp_slider_images'         => [ 120, 130, 140, 150, 160, 170, 180, 190, 200, 210, 220, 230, 240, 250, 260, 270, 280, 290, 300 ],
									//extras_settings
									'mptbm_display_contact'       => 'on',
									'mptbm_email'                 => 'example.gmail.com',
									'mptbm_phone'                 => '123456789',
									'mptbm_text'                  => 'Do not hesitage to give us a call. We are an expert team and we are happy to talk to you.',
								]
							],
							5=> [
								'name'      => 'Mercedes-Benz E220',
								'post_data' => [
									'mp_thumbnail'        => '100',
									//General_settings
									'mptbm_name'                  => 'Mercedes-Benz E220',
									'mptbm_model'                 => 'Mercedes',
									'mptbm_engine'                => '3200',
									'mptbm_interior_color'        => "Black",
									'mptbm_power'                 => 285,
									'mptbm_fuel_type'             => 'Octane',
									'mptbm_length'                => '5.6 meters',
									'mptbm_exterior_color'        => 'silver',
									'mptbm_transmission'          => 'Manual',
									'mptbm_extras'                => 'Leather Seats, LED Lighting, Radio',
									//price_settings
									'mptbm_price_based'           => 'distance',
									'mptbm_km_price'              => 1.8,
									'mptbm_hour_price'            => 20,
									'mptbm_manual_price_info'     => [
										0 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Khulna',
											'price'          => 150,
										],
										1 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Rajshahi',
											'price'          => 200,
										],
										2 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Shylet',
											'price'          => 170,
										],
										4 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Chattogram',
											'price'          => 250,
										],
										5 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Feni',
											'price'          => 150,
										],
									],
									'mptbm_extra_service_data'     => [
										0 => [
											'service_icon' => '',
											'service_name'   => 'Driver',
											'service_qty_type'          => 'inputbox',
											'extra_service_description'          => 150,
											'price'          => 50,
										],
									],
									//faq_settings
									'mptbm_display_faq'           => 'on',
									'mptbm_faq'                   => [
										0 => [
											'title'   => 'What can I expect to see at The Mentalist at Planet Hollywood Resort and Casino?',
											'content' => 'Comedy, magic and mind-reading! The Mentalist has the ability to get inside the minds of audience members, revealing everything from their names, hometowns and anniversaries to their wildest wishes.',
										],
										1 => [
											'title'   => 'Where is The Mentalist located?',
											'content' => 'The V Theater is located inside the Miracle Mile Shops at the Planet Hollywood Resort & Casino.',
										],
										2 => [
											'title'   => 'Can I purchase alcohol at the venue during The Mentalist!?',
											'content' => 'Absolutely! Drinks are available for purchase at the Showgirl Bar outside of the theater and may be brought into the showroom, however, no other outside food or drink will be allowed in the theater.',
										],
										3 => [
											'title'   => 'Is The Mentalist appropriate for children?',
											'content' => 'Due to language, this show is recommended for guests 16 years old and over.',
										],
										4 => [
											'title'   => 'Do I need to exchange my ticket upon arrival at The Mentalist!?',
											'content' => 'Please pick up your tickets at the V Theater Box Office with a valid photo ID for the lead traveler at least 30 minutes prior to show time (box office opens at 11 am). Seating will begin 15 minutes before showtime.',
										],
									],
									//why chose us_settings
									'mptbm_display_why_choose_us' => 'on',
									'mptbm_why_choose_us'         => [
										0 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
										1 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
										2 => 'Watch as Gerry McCambridge performs comedy and magic',
									],
									//gallery_settings
									'mp_slider_images'         => [ 120, 130, 140, 150, 160, 170, 180, 190, 200, 210, 220, 230, 240, 250, 260, 270, 280, 290, 300 ],
									//extras_settings
									'mptbm_display_contact'       => 'on',
									'mptbm_email'                 => 'example.gmail.com',
									'mptbm_phone'                 => '123456789',
									'mptbm_text'                  => 'Do not hesitage to give us a call. We are an expert team and we are happy to talk to you.',
								]
							],
							6=> [
								'name'      => 'Fiat Panda',
								'post_data' => [
									'mp_thumbnail'        => '100',
									//General_settings
									'mptbm_name'                  => 'Fiat Panda',
									'mptbm_model'                 => 'FIAT',
									'mptbm_engine'                => '2200',
									'mptbm_interior_color'        => "White",
									'mptbm_power'                 => 285,
									'mptbm_fuel_type'             => 'Octane',
									'mptbm_length'                => '5.6 meters',
									'mptbm_exterior_color'        => 'silver',
									'mptbm_transmission'          => 'Automatic',
									'mptbm_extras'                => 'Leather Seats, LED Lighting, Radio',
									//price_settings
									'mptbm_price_based'           => 'Duration',
									'mptbm_km_price'              => 1.8,
									'mptbm_hour_price'            => 20,
									'mptbm_manual_price_info'     => [
										0 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Khulna',
											'price'          => 150,
										],
										1 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Rajshahi',
											'price'          => 200,
										],
										2 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Shylet',
											'price'          => 170,
										],
										4 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Chattogram',
											'price'          => 250,
										],
										5 => [
											'start_location' => 'Dhaka',
											'end_location'   => 'Feni',
											'price'          => 150,
										],
									],
									'mptbm_extra_service_data'     => [
										0 => [
											'service_icon' => '',
											'service_name'   => 'Driver',
											'service_qty_type'          => 'inputbox',
											'extra_service_description'          => 150,
											'price'          => 50,
										],
									],
									//faq_settings
									'mptbm_display_faq'           => 'on',
									'mptbm_faq'                   => [
										0 => [
											'title'   => 'What can I expect to see at The Mentalist at Planet Hollywood Resort and Casino?',
											'content' => 'Comedy, magic and mind-reading! The Mentalist has the ability to get inside the minds of audience members, revealing everything from their names, hometowns and anniversaries to their wildest wishes.',
										],
										1 => [
											'title'   => 'Where is The Mentalist located?',
											'content' => 'The V Theater is located inside the Miracle Mile Shops at the Planet Hollywood Resort & Casino.',
										],
										2 => [
											'title'   => 'Can I purchase alcohol at the venue during The Mentalist!?',
											'content' => 'Absolutely! Drinks are available for purchase at the Showgirl Bar outside of the theater and may be brought into the showroom, however, no other outside food or drink will be allowed in the theater.',
										],
										3 => [
											'title'   => 'Is The Mentalist appropriate for children?',
											'content' => 'Due to language, this show is recommended for guests 16 years old and over.',
										],
										4 => [
											'title'   => 'Do I need to exchange my ticket upon arrival at The Mentalist!?',
											'content' => 'Please pick up your tickets at the V Theater Box Office with a valid photo ID for the lead traveler at least 30 minutes prior to show time (box office opens at 11 am). Seating will begin 15 minutes before showtime.',
										],
									],
									//why chose us_settings
									'mptbm_display_why_choose_us' => 'on',
									'mptbm_why_choose_us'         => [
										0 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
										1 => 'Enjoy a taste of Las Vegas glitz at the mind-bending magic show',
										2 => 'Watch as Gerry McCambridge performs comedy and magic',
									],
									//gallery_settings
									'mp_slider_images'         => [ 120, 130, 140, 150, 160, 170, 180, 190, 200, 210, 220, 230, 240, 250, 260, 270, 280, 290, 300 ],
									//extras_settings
									'mptbm_display_contact'       => 'on',
									'mptbm_email'                 => 'example.gmail.com',
									'mptbm_phone'                 => '123456789',
									'mptbm_text'                  => 'Do not hesitage to give us a call. We are an expert team and we are happy to talk to you.',
								]
							],
						]
					]
				];
			}
		}
	}