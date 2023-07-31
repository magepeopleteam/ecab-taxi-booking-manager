<?php
    if (!defined('ABSPATH')) 
    {
        die;
    } // Cannot access pages directly.

    if (!class_exists('MPTBM_Dummy_Import')) 
    {

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        class MPTBM_Dummy_Import 
        {
            public function __construct() 
            {
                //echo "<pre>";print_r(get_post_meta(144));exit;
                //update_option('mptbm_dummy_already_inserted','no');exit;
                //echo "<pre>";print_r($this->get_post_id_by_ttle('Pre-defined Extra Services','mptbm_extra_services'));exit;
                add_action('deactivate_plugin', array($this, 'update_option'), 98);
                add_action('activated_plugin', array($this, 'update_option'), 98);
                add_action('admin_init', array($this, 'dummy_import'), 99);
            }

            function update_option() 
            {
                update_option('mptbm_dummy_already_inserted', 'no');
            }

            public static function check_plugin($plugin_dir_name, $plugin_file): int
            {
                include_once ABSPATH . 'wp-admin/includes/plugin.php';
                $plugin_dir = ABSPATH . 'wp-content/plugins/' . $plugin_dir_name;
                if (is_plugin_active($plugin_dir_name . '/' . $plugin_file)) 
                {
                    return 1;
                } 
                elseif (is_dir($plugin_dir)) 
                {
                    return 2;
                } 
                else 
                {
                    return 0;
                }
            }

            public function dummy_import() 
            {

                $dummy_post_inserted = get_option('mptbm_dummy_already_inserted','no');
                $count_existing_event = wp_count_posts('mptbm_rent')->publish;
                
                $plugin_active = self::check_plugin('Ecab-Taxi-Booking-Manager', 'MPTBM_Plugin.php');
                
                if ($count_existing_event == 0 && $plugin_active == 1 && $dummy_post_inserted != 'yes') 
                {
                    $dummy_taxonomies = $this->dummy_taxonomy();

                    if(array_key_exists('taxonomy', $dummy_taxonomies))
                    {
                        foreach ($dummy_taxonomies['taxonomy'] as $taxonomy => $dummy_taxonomy) 
                        { 
                            if (taxonomy_exists($taxonomy)) 
                            { 
                                $check_terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));

                                if (is_string($check_terms) || sizeof($check_terms) == 0) {
                                    foreach ($dummy_taxonomy as $taxonomy_data) {
                                        unset($term);
                                        $term = wp_insert_term($taxonomy_data['name'], $taxonomy);

                                        if (array_key_exists('tax_data', $taxonomy_data)) {
                                            foreach ($taxonomy_data['tax_data'] as $meta_key => $data) {
                                                update_term_meta($term['term_id'], $meta_key, $data);
                                            }
                                        }
                                    }
                                }

                            }

                        }

                    }

                    $dummy_cpt = $this->dummy_cpt();

                    if(array_key_exists('custom_post', $dummy_cpt))
                    {
                        $dummy_images = self::dummy_images();

                        foreach ($dummy_cpt['custom_post'] as $custom_post => $dummy_post) 
                        {
                            unset($args);
                            $args = array(
                                'post_type' => $custom_post,
                                'posts_per_page' => -1,
                            );

                            unset($post);
                            $post = new WP_Query($args);

                            if ($post->post_count == 0) 
                            {
                                foreach ($dummy_post as $dummy_data) 
                                {
                                    $args = array();
                                    if(isset($dummy_data['name']))$args['post_title'] = $dummy_data['name'];
                                    if(isset($dummy_data['content']))$args['post_content'] = $dummy_data['content'];
                                    $args['post_status'] = 'publish';
                                    $args['post_type'] = $custom_post;

                                    $post_id = wp_insert_post($args);

                                    if (array_key_exists('taxonomy_terms', $dummy_data) && count($dummy_data['taxonomy_terms'])) 
                                    {
                                        foreach ($dummy_data['taxonomy_terms'] as $taxonomy_term) 
                                        {
                                            wp_set_object_terms( $post_id, $taxonomy_term['terms'], $taxonomy_term['taxonomy_name'], true );
                                        }
                                    }

                                    if (array_key_exists('post_data', $dummy_data)) 
                                    {
                                        foreach ($dummy_data['post_data'] as $meta_key => $data) 
                                        {
                                            if ($meta_key == 'mp_slider_images') 
                                            {
                                                if(is_array($data))
                                                {
                                                    $thumnail_ids = array();

                                                    foreach($data as $url_index)
                                                    {
                                                        if(isset($dummy_images[$url_index]))
                                                        {
                                                            $thumnail_ids[] = $dummy_images[$url_index];
                                                        }
                                                        
                                                    }

                                                    update_post_meta($post_id,'mp_slider_images',$thumnail_ids);
                                                }
                                                else
                                                {
                                                    update_post_meta($post_id,'mp_slider_images',array(isset($dummy_images[$data])?$dummy_images[$data]:''));
                                                }

                                            } 
                                            else 
                                            {
                                                update_post_meta($post_id, $meta_key, $data);
                                            }

                                        }
                                    }
                                    flush_rewrite_rules();
                                    wp_reset_postdata();

                                }
                            }
                            flush_rewrite_rules();
                            wp_reset_postdata();
                        }
                    }
                    //$this->craete_pages();
                    flush_rewrite_rules();
                    update_option('mptbm_dummy_already_inserted', 'yes');
                }
            }

            public static function dummy_images()
            {
                $urls = array(
                    'https://img.freepik.com/free-photo/blue-villa-beautiful-sea-hotel_1203-5316.jpg',
                    'https://img.freepik.com/free-photo/beautiful-mountains-ratchaprapha-dam-khao-sok-national-park-surat-thani-province-thailand_335224-851.jpg',
                    'https://img.freepik.com/free-photo/photographer-taking-picture-ocean-coast_657883-287.jpg',
                    'https://img.freepik.com/free-photo/pileh-blue-lagoon-phi-phi-island-thailand_231208-1487.jpg',
                    'https://img.freepik.com/free-photo/godafoss-waterfall-sunset-winter-iceland-guy-red-jacket-looks-godafoss-waterfall_335224-673.jpg',

                );

                unset($image_ids);
                $image_ids = array();

                foreach($urls as $url)
                {
                    $image_ids[] = media_sideload_image($url, '0', $url, 'id');
                }

                return $image_ids;
            }

            public function get_post_id_by_ttle($title,$post_type)
            {
                $args = array(
                    'post_type' => $post_type,
                    'post_status' => 'publish',
                    'name' => $title,
                    'posts_per_page' => 1,
                );

                $posts = get_posts($args);

                if ($posts) 
                {
                    $post = $posts[0];
                    $post_id = $post->ID;
                    wp_reset_postdata();
                    return $post_id;
                }
                else 
                {
                    return false;
                }
            }

            public function dummy_taxonomy(): array {
                return [
                    'taxonomy' => [
                        
                    ],
                ];
            }
            public function dummy_cpt(): array {

                return [
                    'custom_post' => [
                        'mptbm_extra_services' => array(
                            0 => array(
                                'name'      => 'Pre-defined Extra Services',
                                'post_data' => array(

                                    'mptbm_extra_service_infos' => array(
                                        0 => array(
                                            'service_icon' => 'fas fa-baby',
                                            'service_name' => 'Child Seat',
                                            'service_price' => '50',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        1 => array(
                                            'service_icon' => 'fas fa-seedling',
                                            'service_name' => 'Bouquet of Flowers',
                                            'service_price' => '150',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        2 => array(
                                            'service_icon' => 'fas fa-wine-glass-alt',
                                            'service_name' => 'Welcome Drink',
                                            'service_price' => '30',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        3 => array(
                                            'service_icon' => 'fas fa-user-alt',
                                            'service_name' => 'Airport Assistance and Hostess Service',
                                            'service_price' => '30',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        4 => array(
                                            'service_icon' => 'fas fa-skating',
                                            'service_name' => 'Bodyguard Service',
                                            'service_price' => '30',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        
                                    )

                                )

                            ),
                        ),
                        'mptbm_rent' => [
							0 => [
								'name'      => 'BMW 5 Series',
								'post_data' => [
									'mp_thumbnail'        => '',
									//General_settings
									'mptbm_features'=>[
										0 => array(
											'label' => 'Name',
											'icon' => 'fas fa-car-side',
											'image' => '',
											'text' => 'BMW 5 Series Long'
										),
										1 => array(
											'label' => 'Model',
											'icon' => 'fas fa-car',
											'image' => '',
											'text' => 'EXPRW'
										),
										2 => array(
											'label' => 'Engine',
											'icon' => 'fas fa-cogs',
											'image' => '',
											'text' => '3000'
										),
										3 => array(
											'label' => 'Fuel Type',
											'icon' => 'fas fa-gas-pump',
											'image' => '',
											'text' => 'Diesel'
										),
										4 => array(
											'label' => 'Maximum Passenger',
											'icon' => 'fas fa-users',
											'image' => '',
											'text' => '4'
										),
										5 => array(
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
                                    'display_mptbm_extra_services' => 'on',
                                    'mptbm_extra_services_id' => $this->get_post_id_by_ttle('Pre-defined Extra Services','mptbm_extra_services') ? $this->get_post_id_by_ttle('Pre-defined Extra Services','mptbm_extra_services') : '',
									'mptbm_extra_service_data'     => [
										0 => array(
                                            'service_icon' => 'fas fa-baby',
                                            'service_name' => 'Child Seat',
                                            'service_price' => '50',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        1 => array(
                                            'service_icon' => 'fas fa-seedling',
                                            'service_name' => 'Bouquet of Flowers',
                                            'service_price' => '150',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        2 => array(
                                            'service_icon' => 'fas fa-wine-glass-alt',
                                            'service_name' => 'Welcome Drink',
                                            'service_price' => '30',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        3 => array(
                                            'service_icon' => 'fas fa-user-alt',
                                            'service_name' => 'Airport Assistance and Hostess Service',
                                            'service_price' => '30',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        4 => array(
                                            'service_icon' => 'fas fa-skating',
                                            'service_name' => 'Bodyguard Service',
                                            'service_price' => '30',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
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
									'mp_slider_images' => '', 
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
									'mp_thumbnail'        => '',
									//General_settings
									'mptbm_features'=>[
										0 => array(
											'label' => 'Name',
											'icon' => 'fas fa-car-side',
											'image' => '',
											'text' => 'Cadillac Escalade Limousine'
										),
										1 => array(
											'label' => 'Model',
											'icon' => 'fas fa-car',
											'image' => '',
											'text' => 'CADESR'
										),
										2 => array(
											'label' => 'Engine',
											'icon' => 'fas fa-cogs',
											'image' => '',
											'text' => '2500'
										),
										3 => array(
											'label' => 'Fuel Type',
											'icon' => 'fas fa-gas-pump',
											'image' => '',
											'text' => 'Diesel'
										),
										4 => array(
											'label' => 'Maximum Passenger',
											'icon' => 'fas fa-users',
											'image' => '',
											'text' => '4'
										),
										5 => array(
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
                                    //Extra Services
									'display_mptbm_extra_services' => 'on',
                                    'mptbm_extra_services_id' => $this->get_post_id_by_ttle('Pre-defined Extra Services','mptbm_extra_services') ? $this->get_post_id_by_ttle('Pre-defined Extra Services','mptbm_extra_services') : '',
									'mptbm_extra_service_data'     => [
										0 => array(
                                            'service_icon' => 'fas fa-baby',
                                            'service_name' => 'Child Seat',
                                            'service_price' => '50',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        1 => array(
                                            'service_icon' => 'fas fa-seedling',
                                            'service_name' => 'Bouquet of Flowers',
                                            'service_price' => '150',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        2 => array(
                                            'service_icon' => 'fas fa-wine-glass-alt',
                                            'service_name' => 'Welcome Drink',
                                            'service_price' => '30',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        3 => array(
                                            'service_icon' => 'fas fa-user-alt',
                                            'service_name' => 'Airport Assistance and Hostess Service',
                                            'service_price' => '30',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        4 => array(
                                            'service_icon' => 'fas fa-skating',
                                            'service_name' => 'Bodyguard Service',
                                            'service_price' => '30',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
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
									'mp_slider_images' => '',
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
                                    'mp_thumbnail'        => '',
									//General_settings
									'mptbm_features'=>[
										0 => array(
											'label' => 'Name',
											'icon' => 'fas fa-car-side',
											'image' => '',
											'text' => 'Hummer New York Limousine'
										),
										1 => array(
											'label' => 'Model',
											'icon' => 'fas fa-car',
											'image' => '',
											'text' => 'HUMYL'
										),
										2 => array(
											'label' => 'Engine',
											'icon' => 'fas fa-cogs',
											'image' => '',
											'text' => '3500'
										),
										3 => array(
											'label' => 'Fuel Type',
											'icon' => 'fas fa-gas-pump',
											'image' => '',
											'text' => 'Diesel'
										),
										4 => array(
											'label' => 'Maximum Passenger',
											'icon' => 'fas fa-users',
											'image' => '',
											'text' => '4'
										),
										5 => array(
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
                                    //Extra Services
									'display_mptbm_extra_services' => 'on',
                                    'mptbm_extra_services_id' => $this->get_post_id_by_ttle('Pre-defined Extra Services','mptbm_extra_services') ? $this->get_post_id_by_ttle('Pre-defined Extra Services','mptbm_extra_services') : '',
									'mptbm_extra_service_data'     => [
										0 => array(
                                            'service_icon' => 'fas fa-baby',
                                            'service_name' => 'Child Seat',
                                            'service_price' => '50',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        1 => array(
                                            'service_icon' => 'fas fa-seedling',
                                            'service_name' => 'Bouquet of Flowers',
                                            'service_price' => '150',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        2 => array(
                                            'service_icon' => 'fas fa-wine-glass-alt',
                                            'service_name' => 'Welcome Drink',
                                            'service_price' => '30',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        3 => array(
                                            'service_icon' => 'fas fa-user-alt',
                                            'service_name' => 'Airport Assistance and Hostess Service',
                                            'service_price' => '30',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        4 => array(
                                            'service_icon' => 'fas fa-skating',
                                            'service_name' => 'Bodyguard Service',
                                            'service_price' => '30',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
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
									'mp_slider_images' => '',
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
									'mp_thumbnail'        => '',
									//General_settings
									'mptbm_features'=>[
										0 => array(
											'label' => 'Name',
											'icon' => 'fas fa-car-side',
											'image' => '',
											'text' => 'Cadillac Escalade SUV'
										),
										1 => array(
											'label' => 'Model',
											'icon' => 'fas fa-car',
											'image' => '',
											'text' => 'CASUV'
										),
										2 => array(
											'label' => 'Engine',
											'icon' => 'fas fa-cogs',
											'image' => '',
											'text' => '2800'
										),
										3 => array(
											'label' => 'Fuel Type',
											'icon' => 'fas fa-gas-pump',
											'image' => '',
											'text' => 'Diesel'
										),
										4 => array(
											'label' => 'Maximum Passenger',
											'icon' => 'fas fa-users',
											'image' => '',
											'text' => '4'
										),
										5 => array(
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
                                    //Extra Services
									'display_mptbm_extra_services' => 'on',
                                    'mptbm_extra_services_id' => $this->get_post_id_by_ttle('Pre-defined Extra Services','mptbm_extra_services') ? $this->get_post_id_by_ttle('Pre-defined Extra Services','mptbm_extra_services') : '',
									'mptbm_extra_service_data'     => [
										0 => array(
                                            'service_icon' => 'fas fa-baby',
                                            'service_name' => 'Child Seat',
                                            'service_price' => '50',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        1 => array(
                                            'service_icon' => 'fas fa-seedling',
                                            'service_name' => 'Bouquet of Flowers',
                                            'service_price' => '150',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        2 => array(
                                            'service_icon' => 'fas fa-wine-glass-alt',
                                            'service_name' => 'Welcome Drink',
                                            'service_price' => '30',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        3 => array(
                                            'service_icon' => 'fas fa-user-alt',
                                            'service_name' => 'Airport Assistance and Hostess Service',
                                            'service_price' => '30',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        4 => array(
                                            'service_icon' => 'fas fa-skating',
                                            'service_name' => 'Bodyguard Service',
                                            'service_price' => '30',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
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
									'mp_slider_images' => '',
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
                                    'mp_thumbnail'        => '',
									//General_settings
									'mptbm_features'=>[
										0 => array(
											'label' => 'Name',
											'icon' => 'fas fa-car-side',
											'image' => '',
											'text' => 'Ford Tourneo'
										),
										1 => array(
											'label' => 'Model',
											'icon' => 'fas fa-car',
											'image' => '',
											'text' => 'FORD_DD'
										),
										2 => array(
											'label' => 'Engine',
											'icon' => 'fas fa-cogs',
											'image' => '',
											'text' => '3200'
										),
										3 => array(
											'label' => 'Fuel Type',
											'icon' => 'fas fa-gas-pump',
											'image' => '',
											'text' => 'Diesel'
										),
										4 => array(
											'label' => 'Maximum Passenger',
											'icon' => 'fas fa-users',
											'image' => '',
											'text' => '4'
										),
										5 => array(
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
									'display_mptbm_extra_services' => 'on',
                                    'mptbm_extra_services_id' => $this->get_post_id_by_ttle('Pre-defined Extra Services','mptbm_extra_services') ? $this->get_post_id_by_ttle('Pre-defined Extra Services','mptbm_extra_services') : '',
									'mptbm_extra_service_data'     => [
										0 => array(
                                            'service_icon' => 'fas fa-baby',
                                            'service_name' => 'Child Seat',
                                            'service_price' => '50',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        1 => array(
                                            'service_icon' => 'fas fa-seedling',
                                            'service_name' => 'Bouquet of Flowers',
                                            'service_price' => '150',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        2 => array(
                                            'service_icon' => 'fas fa-wine-glass-alt',
                                            'service_name' => 'Welcome Drink',
                                            'service_price' => '30',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        3 => array(
                                            'service_icon' => 'fas fa-user-alt',
                                            'service_name' => 'Airport Assistance and Hostess Service',
                                            'service_price' => '30',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        4 => array(
                                            'service_icon' => 'fas fa-skating',
                                            'service_name' => 'Bodyguard Service',
                                            'service_price' => '30',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
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
									'mp_slider_images' => '',
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
                                    'mp_thumbnail'        => '',
									//General_settings
									'mptbm_features'=>[
										0 => array(
											'label' => 'Name',
											'icon' => 'fas fa-car-side',
											'image' => '',
											'text' => 'Mercedes-Benz E220'
										),
										1 => array(
											'label' => 'Model',
											'icon' => 'fas fa-car',
											'image' => '',
											'text' => 'Mercedes'
										),
										2 => array(
											'label' => 'Engine',
											'icon' => 'fas fa-cogs',
											'image' => '',
											'text' => '3200'
										),
										3 => array(
											'label' => 'Fuel Type',
											'icon' => 'fas fa-gas-pump',
											'image' => '',
											'text' => 'Octane'
										),
										4 => array(
											'label' => 'Maximum Passenger',
											'icon' => 'fas fa-users',
											'image' => '',
											'text' => '4'
										),
										5 => array(
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
									'display_mptbm_extra_services' => 'on',
                                    'mptbm_extra_services_id' => $this->get_post_id_by_ttle('Pre-defined Extra Services','mptbm_extra_services') ? $this->get_post_id_by_ttle('Pre-defined Extra Services','mptbm_extra_services') : '',
									'mptbm_extra_service_data'     => [
										0 => array(
                                            'service_icon' => 'fas fa-baby',
                                            'service_name' => 'Child Seat',
                                            'service_price' => '50',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        1 => array(
                                            'service_icon' => 'fas fa-seedling',
                                            'service_name' => 'Bouquet of Flowers',
                                            'service_price' => '150',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        2 => array(
                                            'service_icon' => 'fas fa-wine-glass-alt',
                                            'service_name' => 'Welcome Drink',
                                            'service_price' => '30',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        3 => array(
                                            'service_icon' => 'fas fa-user-alt',
                                            'service_name' => 'Airport Assistance and Hostess Service',
                                            'service_price' => '30',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        4 => array(
                                            'service_icon' => 'fas fa-skating',
                                            'service_name' => 'Bodyguard Service',
                                            'service_price' => '30',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
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
									'mp_slider_images' => '',
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
                                    'mp_thumbnail'        => '',
									//General_settings
									'mptbm_features'=>[
										0 => array(
											'label' => 'Name',
											'icon' => 'fas fa-car-side',
											'image' => '',
											'text' => 'Fiat Panda'
										),
										1 => array(
											'label' => 'Model',
											'icon' => 'fas fa-car',
											'image' => '',
											'text' => 'FIAT'
										),
										2 => array(
											'label' => 'Engine',
											'icon' => 'fas fa-cogs',
											'image' => '',
											'text' => '2200'
										),
										3 => array(
											'label' => 'Fuel Type',
											'icon' => 'fas fa-gas-pump',
											'image' => '',
											'text' => 'Octane'
										),
										4 => array(
											'label' => 'Maximum Passenger',
											'icon' => 'fas fa-users',
											'image' => '',
											'text' => '4'
										),
										5 => array(
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
                                    'display_mptbm_extra_services' => 'on',
                                    'mptbm_extra_services_id' => $this->get_post_id_by_ttle('Pre-defined Extra Services','mptbm_extra_services') ? $this->get_post_id_by_ttle('Pre-defined Extra Services','mptbm_extra_services') : '',
									'mptbm_extra_service_data'     => [
										0 => array(
                                            'service_icon' => 'fas fa-baby',
                                            'service_name' => 'Child Seat',
                                            'service_price' => '50',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        1 => array(
                                            'service_icon' => 'fas fa-seedling',
                                            'service_name' => 'Bouquet of Flowers',
                                            'service_price' => '150',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        2 => array(
                                            'service_icon' => 'fas fa-wine-glass-alt',
                                            'service_name' => 'Welcome Drink',
                                            'service_price' => '30',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        3 => array(
                                            'service_icon' => 'fas fa-user-alt',
                                            'service_name' => 'Airport Assistance and Hostess Service',
                                            'service_price' => '30',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
                                        4 => array(
                                            'service_icon' => 'fas fa-skating',
                                            'service_name' => 'Bodyguard Service',
                                            'service_price' => '30',
                                            'service_qty_type' => 'inputbox',
                                            'extra_service_description' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.",
                                        ),
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
									'mp_slider_images' => '',
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

        new MPTBM_Dummy_Import();
    }