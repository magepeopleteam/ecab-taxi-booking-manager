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
				register_post_type('mptbm_extra_services', $ex_args);
			}
		}
		new MPTBM_CPT();
	}