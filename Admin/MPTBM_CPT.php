<?php
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('MPTBM_CPT')) {
		class MPTBM_CPT {
			public function __construct() {
				add_action('init', [$this, 'add_cpt']);
			}
			public function add_cpt(): void {
				$label = MPTBM_Function::get_name();
				$slug = MPTBM_Function::get_slug();
				$icon = MPTBM_Function::get_icon();
				$labels = [
					'name' => $label,
					'singular_name' => $label,
					'menu_name' => $label,
					'name_admin_bar' => $label,
					'archives' => $label . ' ' . esc_html__(' List', 'mptbm_plugin'),
					'attributes' => $label . ' ' . esc_html__(' List', 'mptbm_plugin'),
					'parent_item_colon' => $label . ' ' . esc_html__(' Item:', 'mptbm_plugin'),
					'all_items' => esc_html__('All ', 'mptbm_plugin') . ' ' . $label,
					'add_new_item' => esc_html__('Add New ', 'mptbm_plugin') . ' ' . $label,
					'add_new' => esc_html__('Add New ', 'mptbm_plugin') . ' ' . $label,
					'new_item' => esc_html__('New ', 'mptbm_plugin') . ' ' . $label,
					'edit_item' => esc_html__('Edit ', 'mptbm_plugin') . ' ' . $label,
					'update_item' => esc_html__('Update ', 'mptbm_plugin') . ' ' . $label,
					'view_item' => esc_html__('View ', 'mptbm_plugin') . ' ' . $label,
					'view_items' => esc_html__('View ', 'mptbm_plugin') . ' ' . $label,
					'search_items' => esc_html__('Search ', 'mptbm_plugin') . ' ' . $label,
					'not_found' => $label . ' ' . esc_html__(' Not found', 'mptbm_plugin'),
					'not_found_in_trash' => $label . ' ' . esc_html__(' Not found in Trash', 'mptbm_plugin'),
					'featured_image' => $label . ' ' . esc_html__(' Feature Image', 'mptbm_plugin'),
					'set_featured_image' => esc_html__('Set ', 'mptbm_plugin') . ' ' . $label . ' ' . esc_html__(' featured image', 'mptbm_plugin'),
					'remove_featured_image' => esc_html__('Remove ', 'mptbm_plugin') . ' ' . $label . ' ' . esc_html__(' featured image', 'mptbm_plugin'),
					'use_featured_image' => esc_html__('Use as ' . $label . ' featured image', 'mptbm_plugin') . ' ' . $label . ' ' . esc_html__(' featured image', 'mptbm_plugin'),
					'insert_into_item' => esc_html__('Insert into ', 'mptbm_plugin') . ' ' . $label,
					'uploaded_to_this_item' => esc_html__('Uploaded to this ', 'mptbm_plugin') . ' ' . $label,
					'items_list' => $label . ' ' . esc_html__(' list', 'mptbm_plugin'),
					'items_list_navigation' => $label . ' ' . esc_html__(' list navigation', 'mptbm_plugin'),
					'filter_items_list' => esc_html__('Filter ', 'mptbm_plugin') . ' ' . $label . ' ' . esc_html__(' list', 'mptbm_plugin')
				];
				$args = [
					'public' => true,
					'labels' => $labels,
					'menu_icon' => $icon,
					'supports' => ['title', 'thumbnail'],
					'rewrite' => ['slug' => $slug],
					'show_in_rest' => true
				];
				register_post_type(MPTBM_Function::get_cpt(), $args);
			}
		}
		new MPTBM_CPT();
	}