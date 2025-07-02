<?php
/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.
if (!class_exists('MPTBM_CPT')) {
	class MPTBM_CPT
	{
		public function __construct()
		{
			add_action('init', [$this, 'add_cpt']);
			add_filter('manage_mptbm_rent_posts_columns', array($this, 'mptbm_rent_columns'));
			add_action('manage_mptbm_rent_posts_custom_column', array($this, 'mptbm_rent_custom_column'), 10, 2);
			add_filter('manage_edit-mptbm_rent_sortable_columns', array($this, 'mptbm_rent_sortable_columns'));
		}

		public function mptbm_rent_custom_column($columns,$post_id){
			switch($columns){
				case 'mptbm_price_based':
					$mptbm_price_based = esc_html__(get_post_meta($post_id,'mptbm_price_based',true));
				
					$item_price_based = [
						'inclusive' => 'Inclusive',
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

		public function mptbm_rent_columns($columns)
		{
			unset($columns['date']);
			$columns['mptbm_price_based'] = esc_html__('Price based', 'booking-and-rental-manager-for-woocommerce');
			$columns['mptbm_km_price']      =  esc_html__('Kilometer price', 'booking-and-rental-manager-for-woocommerce');
			$columns['mptbm_hour_price']      =  esc_html__('Hourly price', 'booking-and-rental-manager-for-woocommerce');
			$columns['mptbm_waiting_price']      =  esc_html__('Waiting price', 'booking-and-rental-manager-for-woocommerce');
			$columns['author']      =  esc_html__('Author', 'booking-and-rental-manager-for-woocommerce');
			$columns['date']        = esc_html__('Date', 'booking-and-rental-manager-for-woocommerce');
			return $columns;
		}

		public function update_service_status()
		{
			// Debug the $_POST data
			if (!isset($_POST['post_id']) || !isset($_POST['mptbm_service_status'])) {
				wp_send_json_error(__('Post data is missing', 'mptbm_plugin_pro'));
				wp_die();
			}

			// Validate the post ID and service status
			$post_id = intval($_POST['post_id']);
			$meta_value = sanitize_text_field($_POST['mptbm_service_status']);

			if (!get_post($post_id)) {
				wp_send_json_error(__('Invalid post ID', 'mptbm_plugin_pro'));
				wp_die();
			}

			// Debug the current post meta before updating
			$meta_key = 'mptbm_service_status';
			$prev_value = get_post_meta($post_id, $meta_key, true);
			$reference_id = get_post_meta($post_id, 'mptbm_pin', true);
			$service_status_default = MP_Global_Function::get_settings('mptbm_driver_settings', 'default_ride_status', 'Pending');

			if (empty($prev_value)) {
				$prev_value = $service_status_default;
			}

			// Check if meta is being updated
			if (update_post_meta($post_id, $meta_key, $meta_value)) {

				// Continue with the email logic
				$from_email = get_option('woocommerce_email_from_address');
				$from_name = get_option('woocommerce_email_from_name');
				$driver_admin_email = MP_Global_Function::get_settings('mptbm_driver_settings', 'driver_admin_email');

				$placeholders = [
					'{order_reference}' => $reference_id,
					'{service_status}' => $meta_value,
					'{old_service_status}' => $prev_value,
				];

				$subject = MP_Global_Function::get_settings('mptbm_driver_settings', 'status_change_subject', 'Order status has been changed');
				$content = MP_Global_Function::get_settings('mptbm_driver_settings', 'service_status_content', 'Order status has been changed');
				$content = str_replace(array_keys($placeholders), array_values($placeholders), $content);

				$headers = array(
					'Content-Type: text/html; charset=UTF-8',
					sprintf("From: %s <%s>", $from_name, $from_email),
				);

				// Send the email
				wp_mail($driver_admin_email, $subject, $content, $headers);
				wp_send_json_success(__('success', 'mptbm_plugin_pro'));
			} else {
				wp_send_json_error(__('Meta update failed', 'mptbm_plugin_pro'));
			}

			wp_die();
		}

		public function mptbm_rent_sortable_columns($columns)
		{
			$columns['mptbm_price_based'] = 'mptbm_price_based';
			$columns['mptbm_km_price'] = 'mptbm_km_price';
			$columns['mptbm_hour_price'] = 'mptbm_hour_price';
			$columns['mptbm_waiting_price'] = 'mptbm_waiting_price';
			$columns['author'] = 'author';
			return $columns;
		}


		public function add_cpt(): void
		{
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

			$service_status_labels = array(
				'name'                       => _x('Service Status', 'taxonomy general name', 'mptbm_plugin_pro'),
				'singular_name'              => _x('Service Status', 'taxonomy singular name', 'mptbm_plugin_pro'),
				'search_items'               => __('Search Service Status', 'mptbm_plugin_pro'),
				'popular_items'              => __('Popular Service Status', 'mptbm_plugin_pro'),
				'all_items'                  => __('All Service Status', 'mptbm_plugin_pro'),
				'parent_item'                => __('Parent Service Status', 'mptbm_plugin_pro'),
				'parent_item_colon'          => __('Parent Service Status:', 'mptbm_plugin_pro'),
				'edit_item'                  => __('Edit Service Status', 'mptbm_plugin_pro'),
				'update_item'                => __('Update Service Status', 'mptbm_plugin_pro'),
				'add_new_item'               => __('Add New Service Status', 'mptbm_plugin_pro'),
				'new_item_name'              => __('New Service Status Name', 'mptbm_plugin_pro'),
				'separate_items_with_commas' => __('Separate service Status with commas', 'mptbm_plugin_pro'),
				'add_or_remove_items'        => __('Add or remove service Status', 'mptbm_plugin_pro'),
				'choose_from_most_used'      => __('Choose from the most used service Status', 'mptbm_plugin_pro'),
				'not_found'                  => __('No service Status found.', 'mptbm_plugin_pro'),
				'menu_name'                  => __('Service Status', 'mptbm_plugin_pro'),
			);

			$service_status_args = array(
				'hierarchical'          => false,
				'labels'                => $service_status_labels,
				'show_ui'               => true, // Set to false to hide in post sidebar
				'show_admin_column'     => false, // Set to false to hide in admin columns
				'query_var'             => true,
				'rewrite'               => array('slug' => 'service-status'),
				'show_in_rest'          => false, // Set to false to hide from the block editor (Gutenberg)
				'show_in_nav_menus'     => false, // Set to false to hide from navigation menus
				'meta_box_cb' => false,
			);

			register_taxonomy('mptbm_service_status', $cpt, $service_status_args);
			register_taxonomy('locations', $cpt, $taxonomy_args);
			register_post_type('mptbm_extra_services', $ex_args);
			if (class_exists('MPTBM_Plugin_Pro')) {
				register_post_type('mptbm_operate_areas', $dx_args);
			}
		}
	}
	new MPTBM_CPT();
}
