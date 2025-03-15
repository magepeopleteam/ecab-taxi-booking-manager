<?php
/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.
if (!class_exists('MPTBM_Availability')) {
	class MPTBM_Availability {
		public function __construct() {
			add_filter('mptbm_vehicle_available', array($this, 'check_vehicle_availability'), 10, 2);
			
			// Add debugging action
			add_action('wp_ajax_mptbm_debug_availability', array($this, 'debug_availability'));
			add_action('wp_ajax_nopriv_mptbm_debug_availability', array($this, 'debug_availability'));
		}
		
		/**
		 * Debug availability information
		 */
		public function debug_availability() {
			if (!isset($_REQUEST['post_id']) || !isset($_REQUEST['date'])) {
				wp_send_json_error('Missing required parameters');
				return;
			}
			
			$post_id = intval($_REQUEST['post_id']);
			$date = sanitize_text_field($_REQUEST['date']);
			
			$total_quantity = self::get_vehicle_quantity($post_id);
			$booked_count = self::get_booked_count($post_id, $date);
			$is_available = self::is_vehicle_available($post_id, $date);
			$reset_minutes = self::get_reset_minutes($post_id);
			
			// Get all bookings for this vehicle on this date
			global $wpdb;
			$booking_date = date('Y-m-d', strtotime($date));
			$query = $wpdb->prepare(
				"SELECT pm1.post_id as order_id, pm2.meta_value as booking_date, pm5.meta_value as booking_time, p.post_date, pm3.meta_value as order_status
				FROM {$wpdb->prefix}postmeta pm1
				JOIN {$wpdb->prefix}postmeta pm2 ON pm1.post_id = pm2.post_id
				JOIN {$wpdb->prefix}postmeta pm3 ON pm1.post_id = pm3.post_id
				LEFT JOIN {$wpdb->prefix}postmeta pm5 ON pm1.post_id = pm5.post_id AND pm5.meta_key = 'mptbm_start_time'
				JOIN {$wpdb->prefix}posts p ON pm1.post_id = p.ID
				WHERE pm1.meta_key = 'mptbm_id' AND pm1.meta_value = %d
				AND pm2.meta_key = 'mptbm_date' AND DATE(pm2.meta_value) = %s
				AND pm3.meta_key = 'mptbm_order_status' 
				AND pm3.meta_value IN ('processing', 'on-hold', 'completed')",
				$post_id,
				$booking_date
			);
			
			$bookings = $wpdb->get_results($query);
			$current_time = current_time('timestamp');
			
			$booking_details = array();
			foreach ($bookings as $booking) {
				$booking_time = !empty($booking->booking_time) ? $booking->booking_time : '00:00';
				$booking_timestamp = strtotime($booking->booking_date . ' ' . $booking_time);
				$reset_time = $booking_timestamp + ($reset_minutes * 60);
				$is_active = ($reset_time > $current_time) ? true : false;
				
				$booking_details[] = array(
					'order_id' => $booking->order_id,
					'booking_date' => $booking->booking_date,
					'booking_time' => $booking_time,
					'order_status' => $booking->order_status,
					'post_date' => $booking->post_date,
					'booking_timestamp' => date('Y-m-d H:i:s', $booking_timestamp),
					'reset_time' => date('Y-m-d H:i:s', $reset_time),
					'is_active' => $is_active
				);
			}
			
			$response = array(
				'post_id' => $post_id,
				'date' => $date,
				'total_quantity' => $total_quantity,
				'booked_count' => $booked_count,
				'is_available' => $is_available,
				'reset_minutes' => $reset_minutes,
				'current_time' => date('Y-m-d H:i:s', $current_time),
				'bookings' => $booking_details
			);
			
			wp_send_json_success($response);
		}
		
		/**
		 * Get the total quantity of vehicles available for a specific vehicle type
		 *
		 * @param int $post_id The vehicle post ID
		 * @return int The total quantity of vehicles
		 */
		public static function get_vehicle_quantity($post_id) {
			$quantity = get_post_meta($post_id, "mptbm_vehicle_quantity", true);
			return !empty($quantity) ? intval($quantity) : 1;
		}
		
		/**
		 * Get the reset time in minutes for a vehicle
		 *
		 * @param int $post_id The vehicle post ID
		 * @return int The reset time in minutes
		 */
		public static function get_reset_minutes($post_id) {
			$reset_minutes = get_post_meta($post_id, "mptbm_taxi_reset_minutes", true);
			return !empty($reset_minutes) ? intval($reset_minutes) : 1440; // Default 1440 minutes = 24 hours
		}
		
		/**
		 * Get the count of already booked vehicles for a specific date
		 *
		 * @param int $post_id The vehicle post ID
		 * @param string $date The date to check in Y-m-d format
		 * @return int The number of booked vehicles
		 */
		public static function get_booked_count($post_id, $date) {
			global $wpdb;
			
			// Get the date in Y-m-d format
			$booking_date = date('Y-m-d', strtotime($date));
			
			// Get the reset minutes for this vehicle
			$reset_minutes = self::get_reset_minutes($post_id);
			
			// Current time
			$current_time = current_time('timestamp');
			
			// Query to get all bookings for this vehicle on this date
			$query = $wpdb->prepare(
				"SELECT pm1.post_id as order_id, pm2.meta_value as booking_date, pm5.meta_value as booking_time, p.post_date
				FROM {$wpdb->prefix}postmeta pm1
				JOIN {$wpdb->prefix}postmeta pm2 ON pm1.post_id = pm2.post_id
				JOIN {$wpdb->prefix}postmeta pm3 ON pm1.post_id = pm3.post_id
				LEFT JOIN {$wpdb->prefix}postmeta pm5 ON pm1.post_id = pm5.post_id AND pm5.meta_key = 'mptbm_start_time'
				JOIN {$wpdb->prefix}posts p ON pm1.post_id = p.ID
				WHERE pm1.meta_key = 'mptbm_id' AND pm1.meta_value = %d
				AND pm2.meta_key = 'mptbm_date' AND DATE(pm2.meta_value) = %s
				AND pm3.meta_key = 'mptbm_order_status' 
				AND pm3.meta_value IN ('processing', 'on-hold', 'completed')",
				$post_id,
				$booking_date
			);
			
			$bookings = $wpdb->get_results($query);
			$count = 0;
			
			foreach ($bookings as $booking) {
				// Get the booking timestamp
				$booking_time = !empty($booking->booking_time) ? $booking->booking_time : '00:00';
				$booking_timestamp = strtotime($booking->booking_date . ' ' . $booking_time);
				
				// If booking timestamp is invalid, use the order creation date
				if ($booking_timestamp <= 0) {
					$booking_timestamp = strtotime($booking->post_date);
				}
				
				// If booking time + reset minutes is in the future, count it as booked
				if ($booking_timestamp + ($reset_minutes * 60) > $current_time) {
					$count++;
				}
			}
			
			// Also check for return bookings that overlap with this date
			$return_query = $wpdb->prepare(
				"SELECT pm1.post_id as order_id, pm3.meta_value as return_date, pm5.meta_value as return_time, p.post_date
				FROM {$wpdb->prefix}postmeta pm1
				JOIN {$wpdb->prefix}postmeta pm2 ON pm1.post_id = pm2.post_id
				JOIN {$wpdb->prefix}postmeta pm3 ON pm1.post_id = pm3.post_id
				JOIN {$wpdb->prefix}postmeta pm4 ON pm1.post_id = pm4.post_id
				LEFT JOIN {$wpdb->prefix}postmeta pm5 ON pm1.post_id = pm5.post_id AND pm5.meta_key = 'mptbm_return_start_time'
				JOIN {$wpdb->prefix}posts p ON pm1.post_id = p.ID
				WHERE pm1.meta_key = 'mptbm_id' AND pm1.meta_value = %d
				AND pm2.meta_key = 'mptbm_taxi_return' AND pm2.meta_value > 1
				AND pm3.meta_key = '_mptbm_return_date' AND DATE(pm3.meta_value) = %s
				AND pm4.meta_key = 'mptbm_order_status' 
				AND pm4.meta_value IN ('processing', 'on-hold', 'completed')",
				$post_id,
				$booking_date
			);
			
			$return_bookings = $wpdb->get_results($return_query);
			$return_count = 0;
			
			foreach ($return_bookings as $booking) {
				// Get the return booking timestamp
				$return_time = !empty($booking->return_time) ? $booking->return_time : '00:00';
				$return_timestamp = strtotime($booking->return_date . ' ' . $return_time);
				
				// If return timestamp is invalid, use the order creation date
				if ($return_timestamp <= 0) {
					$return_timestamp = strtotime($booking->post_date);
				}
				
				// If return booking time + reset minutes is in the future, count it as booked
				if ($return_timestamp + ($reset_minutes * 60) > $current_time) {
					$return_count++;
				}
			}
			
			return $count + $return_count;
		}
		
		/**
		 * Check if a vehicle is available for booking on a specific date
		 *
		 * @param int $post_id The vehicle post ID
		 * @param string $date The date to check in Y-m-d format
		 * @return bool True if vehicle is available, false otherwise
		 */
		public static function is_vehicle_available($post_id, $date) {
			$total_quantity = self::get_vehicle_quantity($post_id);
			$booked_count = self::get_booked_count($post_id, $date);
			
			return $booked_count < $total_quantity;
		}
		
		/**
		 * Filter callback to check vehicle availability
		 *
		 * @param bool $available Current availability status
		 * @param array $args Arguments containing post_id and date
		 * @return bool Updated availability status
		 */
		public function check_vehicle_availability($available, $args) {
			if (isset($args['post_id']) && isset($args['date'])) {
				return self::is_vehicle_available($args['post_id'], $args['date']);
			}
			return $available;
		}
	}
	new MPTBM_Availability();
}
