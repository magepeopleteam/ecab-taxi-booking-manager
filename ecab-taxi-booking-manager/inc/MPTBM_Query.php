<?php
/*
 * @Author 		engr.sumonazma@gmail.com
 * Copyright: 	mage-people.com
 */
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly
if (!class_exists('MPTBM_Query')) {
	class MPTBM_Query
	{
		public function __construct() {}
		public static function query_post_id($post_type): array
		{
			return get_posts(array(
				'fields' => 'ids',
				'posts_per_page' => -1,
				'post_type' => $post_type,
				'post_status' => 'publish'
			));
		}

		public static function query_operation_area_list($post_type)
		{
			// Initialize an array to store the results
			$result = array();

			// Retrieve posts
			$posts_array = get_posts(array(
				'posts_per_page' => -1,
				'post_type'      => $post_type,
				'post_status'    => 'publish',
			));

			// Iterate through each post
			foreach ($posts_array as $post) {
				// Get post ID
				$post_id = $post->ID;

				// Get post meta information based on operation type
				$mptbm_operation_type = get_post_meta($post_id, 'mptbm-operation-type', true);

				if ($mptbm_operation_type === 'fixed-operation-area-type') {
					// If operation type is 'fixed-operation-area-type', retrieve corresponding meta
					$mptbm_starting_location = get_post_meta($post_id, 'mptbm-starting-location-three', true);
					$mptbm_coordinates = get_post_meta($post_id, 'mptbm-coordinates-three', true);
					$result[] = array(
						'post_id' => $post_id,
						'operation_type' => $mptbm_operation_type,
						'starting_location_three' => isset($mptbm_starting_location) ? $mptbm_starting_location : null,
						'coordinates_three' => isset($mptbm_coordinates) ? $mptbm_coordinates : null,
					);
				} elseif ($mptbm_operation_type === 'geo-matched-operation-area-type') {
					// If operation type is 'geo-matched-operation-area-type', retrieve corresponding meta
					$mptbm_starting_location = get_post_meta($post_id, 'mptbm-starting-location-four', true);
					$mptbm_coordinates = get_post_meta($post_id, 'mptbm-coordinates-four', true);
					$result[] = array(
						'post_id' => $post_id,
						'operation_type' => $mptbm_operation_type,
						'starting_location_four' => isset($mptbm_starting_location) ? $mptbm_starting_location : null,
						'coordinates_four' => isset($mptbm_coordinates) ? $mptbm_coordinates : null,
					);
				} else {
					// Otherwise, retrieve meta for two sets of locations and coordinates
					$mptbm_starting_location_one = get_post_meta($post_id, 'mptbm-starting-location-one', true);
					$mptbm_coordinates_one = get_post_meta($post_id, 'mptbm-coordinates-one', true);
					$mptbm_starting_location_two = get_post_meta($post_id, 'mptbm-starting-location-two', true);
					$mptbm_coordinates_two = get_post_meta($post_id, 'mptbm-coordinates-two', true);
					$mptbm_geo_fence_increase_price_by = get_post_meta($post_id, 'mptbm-geo-fence-increase_price_by', true);
					if ($mptbm_geo_fence_increase_price_by == 'geo-fence-fixed-price') {
						$mpbtbm_operation_area_price = get_post_meta($post_id, 'mptbm-geo-fence-fixed-price-amount', true);
					} else {
						$mpbtbm_operation_area_price = get_post_meta($post_id, 'mptbm-geo-fence-percentage-amount', true);
					}
					$result[] = array(
						'post_id' => $post_id,
						'operation_type' => $mptbm_operation_type,
						'starting_location_one' => isset($mptbm_starting_location_one) ? $mptbm_starting_location_one : null,
						'coordinates_one' => isset($mptbm_coordinates_one) ? $mptbm_coordinates_one : null,
						'starting_location_two' => isset($mptbm_starting_location_two) ? $mptbm_starting_location_two : null,
						'coordinates_two' => isset($mptbm_coordinates_two) ? $mptbm_coordinates_two : null,
						'geo_fence_increase_price_by' => isset($mptbm_geo_fence_increase_price_by) ? $mptbm_geo_fence_increase_price_by : null,
						'mptbm_operation_area_price' => isset($mpbtbm_operation_area_price) ? $mpbtbm_operation_area_price : null,
					);
				}
			}

			// Return the result array
			return $result;
		}



		public static function query_transport_list($price_based = ''): WP_Query
		{
			// Original conditions based on $price_based
			$price_based_1 = !$price_based || $price_based == 'dynamic' ? array(
				'key' => 'mptbm_price_based',
				'value' => 'distance',
				'compare' => '=',
			) : '';

			$price_based_2 = !$price_based || $price_based == 'dynamic' ? array(
				'key' => 'mptbm_price_based',
				'value' => 'duration',
				'compare' => '=',
			) : '';

			$price_based_3 = !$price_based || $price_based == 'dynamic' ? array(
				'key' => 'mptbm_price_based',
				'value' => 'distance_duration',
				'compare' => '=',
			) : '';

			$price_based_4 = $price_based == 'manual' ? array(
				'key' => 'mptbm_price_based',
				'value' => 'manual',
				'compare' => '=',
			) : '';

			$price_based_5 = $price_based == 'fixed_hourly' ? array(
				'key' => 'mptbm_price_based',
				'value' => 'fixed_hourly',
				'compare' => '=',
			) : '';

			// New inclusive condition ($price_based_6)
			$price_based_6 = array(
				'key' => 'mptbm_price_based',
				'value' => array('inclusive'),
				'compare' => 'IN',
			);

			// Main query args
			$args = array(
				'post_type' => array(MPTBM_Function::get_cpt()),
				'posts_per_page' => -1,
				'post_status' => 'publish',
				'meta_query' => array(
					'relation' => 'OR',
					$price_based_1,
					$price_based_2,
					$price_based_3,
					$price_based_4,
					$price_based_5
				)
			);

			// Run the main query
			$main_query = new WP_Query($args);

			// Query for the inclusive values ($price_based_6)
			$args_inclusive = array(
				'post_type' => array(MPTBM_Function::get_cpt()),
				'posts_per_page' => -1,
				'post_status' => 'publish',
				'meta_query' => array(
					$price_based_6 // Inclusive condition
				)
			);

			// Run the second query
			$inclusive_query = new WP_Query($args_inclusive);

			// Merge the results of both queries
			$merged_posts = array_merge($main_query->posts, $inclusive_query->posts);

			// Return a new WP_Query object with merged posts
			return new WP_Query(array(
				'post_type' => array(MPTBM_Function::get_cpt()),
				'posts_per_page' => -1,
				'post_status' => 'publish',
				'post__in' => wp_list_pluck($merged_posts, 'ID') // Include all post IDs from merged result
			));
		}
		public static function query_all_service_sold($post_id, $date, $service_name = ''): WP_Query
		{
			$_seat_booked_status = MP_Global_Function::get_settings('mp_global_settings', 'set_book_status', array('processing', 'completed'));
			$seat_booked_status = !empty($_seat_booked_status) ? $_seat_booked_status : [];
			$type_filter = !empty($type) ? array(
				'key' => 'mptbm_service_name',
				'value' => $service_name,
				'compare' => '='
			) : '';
			$date_filter = !empty($date) ? array(
				'key' => 'mptbm_date',
				'value' => $date,
				'compare' => 'LIKE'
			) : '';
			$pending_status_filter = in_array('pending', $seat_booked_status) ? array(
				'key' => 'mptbm_order_status',
				'value' => 'pending',
				'compare' => '='
			) : '';
			$on_hold_status_filter = in_array('on-hold', $seat_booked_status) ? array(
				'key' => 'mptbm_order_status',
				'value' => 'on-hold',
				'compare' => '='
			) : '';
			$processing_status_filter = array(
				'key' => 'mptbm_order_status',
				'value' => 'processing',
				'compare' => '='
			);
			$completed_status_filter = array(
				'key' => 'mptbm_order_status',
				'value' => 'completed',
				'compare' => '='
			);
			$args = array(
				'post_type' => 'mptbm_service_booking',
				'posts_per_page' => -1,
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'relation' => 'AND',
						array(
							'key' => 'mptbm_id',
							'value' => $date,
							'compare' => '='
						),
						$type_filter,
						$date_filter
					),
					array(
						'relation' => 'OR',
						$pending_status_filter,
						$on_hold_status_filter,
						$processing_status_filter,
						$completed_status_filter
					)
				)
			);
			return new WP_Query($args);
		}
	}
	new MPTBM_Query();
}
