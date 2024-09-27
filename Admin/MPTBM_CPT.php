<?php
	/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('MPTBM_CPT')) {
		class MPTBM_CPT {
			public function __construct() {
				add_action('init', [$this, 'add_cpt']);
				add_filter( 'manage_mptbm_rent_posts_columns', array($this,'mptbm_rent_columns') ) ;
            	add_action( 'manage_mptbm_rent_posts_custom_column', array($this,'mptbm_rent_custom_column'),10,2 ) ;
            	add_filter( 'manage_edit-mptbm_rent_sortable_columns', array($this,'mptbm_rent_sortable_columns') ) ;
			}

			public function mptbm_rent_columns($columns){
				unset($columns['date']);
				$columns['mptbm_price_based']= esc_html__('Price based','booking-and-rental-manager-for-woocommerce');
				$columns['mptbm_km_price']      =  esc_html__('Kelometer price','booking-and-rental-manager-for-woocommerce');
				$columns['mptbm_hour_price']      =  esc_html__('Hourly price','booking-and-rental-manager-for-woocommerce');
				$columns['mptbm_waiting_price']      =  esc_html__('Waiting price','booking-and-rental-manager-for-woocommerce');
				$columns['author']      =  esc_html__('Author','booking-and-rental-manager-for-woocommerce');
				$columns['date']        = esc_html__('Date','booking-and-rental-manager-for-woocommerce');
				return $columns;
			}
	
			public function mptbm_rent_custom_column($columns,$post_id){
				switch($columns){
					case 'mptbm_price_based':
						$mptbm_price_based = esc_html__(get_post_meta($post_id,'mptbm_price_based',true));
						$item_price_based = [
							'distance' => 'Distance as google map',
							'duration' => 'Duration/Time as google map',
							'distance_duration' => 'Distance + Duration as google map',
							'manual' => 'Manual as fixed Location',
							'fixed_hourly' => 'Fixed Hourly',
						];
						foreach($item_price_based as $kay => $value):
							echo esc_html(($kay==$mptbm_price_based)?$value:'');
						endforeach;
					break;
					case 'mptbm_km_price':
						$mptbm_km_price = get_post_meta($post_id,'mptbm_km_price',true);
						echo esc_html($mptbm_km_price?$mptbm_km_price:'');
					break;
					case 'mptbm_hour_price':
						$mptbm_hour_price = get_post_meta($post_id,'mptbm_hour_price',true);
						echo esc_html($mptbm_hour_price?$mptbm_hour_price:'');
					break;
					case 'mptbm_waiting_price':
						$mptbm_waiting_price = get_post_meta($post_id,'mptbm_waiting_price',true);
						echo esc_html($mptbm_waiting_price?$mptbm_waiting_price:'');
					break;
				}
			}
	
			public function mptbm_rent_sortable_columns($columns){
				$columns['mptbm_price_based']='mptbm_price_based';
				$columns['mptbm_km_price']='mptbm_km_price';
				$columns['mptbm_hour_price']='mptbm_hour_price';
				$columns['mptbm_waiting_price']='mptbm_waiting_price';
				$columns['author']='author';
				return $columns;
			}


			public function add_cpt(): void {
				$cpt = MPTBM_Function::get_cpt();
				$label = MPTBM_Function::get_name();
				$slug = MPTBM_Function::get_slug();
				$icon = MPTBM_Function::get_icon();
				$labels = [
					'name' => $label,
					'singular_name' => $label,
					'menu_name' => $label,
					'name_admin_bar' => $label,
					'archives' => $label . ' ' . esc_html__(' List', 'ecab-taxi-booking-manager'),
					'attributes' => $label . ' ' . esc_html__(' List', 'ecab-taxi-booking-manager'),
					'parent_item_colon' => $label . ' ' . esc_html__(' Item:', 'ecab-taxi-booking-manager'),
					'all_items' => esc_html__('All ', 'ecab-taxi-booking-manager') . ' ' . $label,
					'add_new_item' => esc_html__('Add New ', 'ecab-taxi-booking-manager') . ' ' . $label,
					'add_new' => esc_html__('Add New ', 'ecab-taxi-booking-manager') . ' ' . $label,
					'new_item' => esc_html__('New ', 'ecab-taxi-booking-manager') . ' ' . $label,
					'edit_item' => esc_html__('Edit ', 'ecab-taxi-booking-manager') . ' ' . $label,
					'update_item' => esc_html__('Update ', 'ecab-taxi-booking-manager') . ' ' . $label,
					'view_item' => esc_html__('View ', 'ecab-taxi-booking-manager') . ' ' . $label,
					'view_items' => esc_html__('View ', 'ecab-taxi-booking-manager') . ' ' . $label,
					'search_items' => esc_html__('Search ', 'ecab-taxi-booking-manager') . ' ' . $label,
					'not_found' => $label . ' ' . esc_html__(' Not found', 'ecab-taxi-booking-manager'),
					'not_found_in_trash' => $label . ' ' . esc_html__(' Not found in Trash', 'ecab-taxi-booking-manager'),
					'featured_image' => $label . ' ' . esc_html__(' Feature Image', 'ecab-taxi-booking-manager'),
					'set_featured_image' => esc_html__('Set ', 'ecab-taxi-booking-manager') . ' ' . $label . ' ' . esc_html__(' featured image', 'ecab-taxi-booking-manager'),
					'remove_featured_image' => esc_html__('Remove ', 'ecab-taxi-booking-manager') . ' ' . $label . ' ' . esc_html__(' featured image', 'ecab-taxi-booking-manager'),
					'use_featured_image' => esc_html__('Use as featured image', 'ecab-taxi-booking-manager') . ' ' . $label . ' ' . esc_html__(' featured image', 'ecab-taxi-booking-manager'),
					'insert_into_item' => esc_html__('Insert into ', 'ecab-taxi-booking-manager') . ' ' . $label,
					'uploaded_to_this_item' => esc_html__('Uploaded to this ', 'ecab-taxi-booking-manager') . ' ' . $label,
					'items_list' => $label . ' ' . esc_html__(' list', 'ecab-taxi-booking-manager'),
					'items_list_navigation' => $label . ' ' . esc_html__(' list navigation', 'ecab-taxi-booking-manager'),
					'filter_items_list' => esc_html__('Filter ', 'ecab-taxi-booking-manager') . ' ' . $label . ' ' . esc_html__(' list', 'ecab-taxi-booking-manager')
				];
				$args = [
					'public' => false,
					'labels' => $labels,
					'menu_icon' => $icon,
					'supports' => ['title', 'thumbnail'],
					'show_in_rest' => true,
					'capability_type' => 'post',
					'publicly_queryable' => true,  // you should be able to query it
					'show_ui' => true,  // you should be able to edit it in wp-admin
					'exclude_from_search' => true,  // you should exclude it from search results
					'show_in_nav_menus' => false,  // you shouldn't be able to add it to menus
					'has_archive' => false,  // it shouldn't have archive page
					'rewrite' => ['slug' => $slug],
				];
				register_post_type($cpt, $args);
				$ex_args = array(
					'public' => false,
					'label' => esc_html__('Extra Services', 'ecab-taxi-booking-manager'),
					'supports' => array('title'),
					'show_in_menu' => 'edit.php?post_type=' . $cpt,
					'capability_type' => 'post',
					'publicly_queryable' => true,  // you should be able to query it
					'show_ui' => true,  // you should be able to edit it in wp-admin
					'exclude_from_search' => true,  // you should exclude it from search results
					'show_in_nav_menus' => false,  // you shouldn't be able to add it to menus
					'has_archive' => false,  // it shouldn't have archive page
					'rewrite' => false,
				);
				
				$dx_args = array(
					'public' => false,
					'label' => esc_html__('Operation Areas', 'ecab-taxi-booking-manager'),
					'supports' => array('title'),
					'show_in_menu' => 'edit.php?post_type=' . $cpt,
					'capability_type' => 'post',
					'publicly_queryable' => true,  // you should be able to query it
					'show_ui' => true,  // you should be able to edit it in wp-admin
					'exclude_from_search' => true,  // you should exclude it from search results
					'show_in_nav_menus' => false,  // you shouldn't be able to add it to menus
					'has_archive' => false,  // it shouldn't have archive page
					'rewrite' => false,
				);
				
				$calendar_args = array(
					'public' => false,
					'label' => esc_html__('Google Calendar', 'ecab-taxi-booking-manager'),
					'supports' => array('title'),
					'show_in_menu' => 'edit.php?post_type=' . $cpt,
					'capability_type' => 'post',
					'publicly_queryable' => true,  // you should be able to query it
					'show_ui' => true,  // you should be able to edit it in wp-admin
					'exclude_from_search' => true,  // you should exclude it from search results
					'show_in_nav_menus' => false,  // you shouldn't be able to add it to menus
					'has_archive' => false,  // it shouldn't have archive page
					'rewrite' => false,
				);

				$taxonomy_labels = array(
					'name' => esc_html__('Locations', 'ecab-taxi-booking-manager'),
					'singular_name' => esc_html__('Location', 'ecab-taxi-booking-manager'),
					'menu_name' => esc_html__('Locations', 'ecab-taxi-booking-manager'),
					'all_items' => esc_html__('All Locations', 'ecab-taxi-booking-manager'),
					'edit_item' => esc_html__('Edit Location', 'ecab-taxi-booking-manager'),
					'view_item' => esc_html__('View Location', 'ecab-taxi-booking-manager'),
					'update_item' => esc_html__('Update Location', 'ecab-taxi-booking-manager'),
					'add_new_item' => esc_html__('Add New Location', 'ecab-taxi-booking-manager'),
					'new_item_name' => esc_html__('New Location Name', 'ecab-taxi-booking-manager'),
					'search_items' => esc_html__('Search Locations', 'ecab-taxi-booking-manager'),
				);
			
				$taxonomy_args = array(
					'hierarchical' => false,
					'labels' => $taxonomy_labels,
					'show_ui' => true,
					'show_in_rest' => true,
					'query_var' => true,
					'rewrite' => array('slug' => 'locations'),  // Adjust the slug as needed
					'meta_box_cb' => false,
				);
			
				register_taxonomy('locations', $cpt, $taxonomy_args);
				register_post_type('mptbm_extra_services', $ex_args);
				if(class_exists('MPTBM_Plugin_Pro')){
					register_post_type('mptbm_operate_areas', $dx_args);
				}
				
			}
		}
		new MPTBM_CPT();
	}