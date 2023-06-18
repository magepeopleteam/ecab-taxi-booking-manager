<?php
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('MPTBM_Query')) {
		class MPTBM_Query {
			public function __construct() {
			}
			public static function query_post_type($post_type): WP_Query {
				$args = array(
					'post_type' => $post_type,
					'posts_per_page' => -1,
					'post_status' => 'publish'
				);
				return new WP_Query($args);
			}
			public static function query_post_id($post_type): array {
				return get_posts(array(
					'fields' => 'ids',
					'posts_per_page' => -1,
					'post_type' => $post_type,
					'post_status' => 'publish'
				));
			}
			public static function query_transport_list($price_based): WP_Query {
				$args = array(
					'post_type' => array(MPTBM_Function::get_cpt()),
					'posts_per_page' => -1,
					'meta_key' => 'mptbm_price_based',
					'meta_value' => $price_based,
					'post_status' => 'publish'
				);
				return new WP_Query($args);
			}
			public static function query_all_service_sold($post_id, $date, $service_name = ''): WP_Query {
				$_seat_booked_status = MPTBM_Function::get_general_settings('set_book_status', array('processing', 'completed'));
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
			public static function get_order_meta($item_id, $key): string {
				global $wpdb;
				$table_name = $wpdb->prefix . "woocommerce_order_itemmeta";
				$results = $wpdb->get_results($wpdb->prepare("SELECT meta_value FROM $table_name WHERE order_item_id = %d AND meta_key = %s", $item_id, $key));
				foreach ($results as $result) {
					$value = $result->meta_value;
				}
				return $value ?? '';
			}
		}
		new MPTBM_Query();
	}