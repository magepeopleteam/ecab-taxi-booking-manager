<?php
	/*
 * @Author 		engr.sumonazma@gmail.com
 * Copyright: 	mage-people.com
 */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly
	if (!class_exists('MPTBM_Query')) {
		class MPTBM_Query {
			public function __construct() {
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
				$price_based_1=$price_based=='dynamic'?array(
					'key'=>'mptbm_price_based',
					'value'=>'distance',
					'compare'=>'=',
				):'';
				$price_based_2=$price_based=='dynamic'?array(
					'key'=>'mptbm_price_based',
					'value'=>'duration',
					'compare'=>'=',
				):'';
				$price_based_3=$price_based=='dynamic'?array(
					'key'=>'mptbm_price_based',
					'value'=>'distance_duration',
					'compare'=>'=',
				):'';
				$price_based_4=$price_based=='manual'?array(
					'key'=>'mptbm_price_based',
					'value'=>'manual',
					'compare'=>'=',
				):'';
				$args = array(
					'post_type' => array(MPTBM_Function::get_cpt()),
					'posts_per_page' => -1,
					'post_status' => 'publish',
					'meta_query'=>array(
						'relation'=>'OR',
						$price_based_1,$price_based_2,$price_based_3,$price_based_4
					)
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
		}
		new MPTBM_Query();
	}