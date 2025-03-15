<?php
/*
* @Author 		magePeople
* Copyright: 	mage-people.com
*/
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_Ride_History')) {
	class MPTBM_Ride_History {
		public function __construct() {
			// Register shortcode for ride history
			add_shortcode('mptbm_ride_history', array($this, 'ride_history_shortcode'));
			
			// Add scripts and styles
			add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
			
			// Ajax handlers for rebooking
			add_action('wp_ajax_mptbm_rebook_ride', array($this, 'rebook_ride'));
			add_action('wp_ajax_nopriv_mptbm_rebook_ride', array($this, 'rebook_ride_unauthorized'));
			
			// Add ride history tab to My Account
			add_filter('woocommerce_account_menu_items', array($this, 'add_ride_history_tab'));
			add_action('woocommerce_account_ride-history_endpoint', array($this, 'ride_history_content'));
			
			// Register new endpoint
			add_action('init', array($this, 'add_endpoints'));
			add_filter('query_vars', array($this, 'add_query_vars'));
		}
		
		/**
		 * Register new endpoint to use in My Account page
		 */
		public function add_endpoints() {
			add_rewrite_endpoint('ride-history', EP_ROOT | EP_PAGES);
			flush_rewrite_rules();
		}
		
		/**
		 * Add new query var
		 */
		public function add_query_vars($vars) {
			$vars[] = 'ride-history';
			return $vars;
		}
		
		/**
		 * Add new tab to My Account menu
		 */
		public function add_ride_history_tab($items) {
			// Add ride history tab after orders
			$new_items = array();
			
			foreach ($items as $key => $item) {
				$new_items[$key] = $item;
				
				if ($key === 'orders') {
					$new_items['ride-history'] = __('Ride History', 'ecab-taxi-booking-manager');
				}
			}
			
			return $new_items;
		}
		
		/**
		 * Ride history tab content
		 */
		public function ride_history_content() {
			echo do_shortcode('[mptbm_ride_history]');
		}
		
		/**
		 * Enqueue scripts and styles
		 */
		public function enqueue_scripts() {
			wp_enqueue_style('mptbm-ride-history-style', MPTBM_PLUGIN_URL . '/assets/frontend/css/mptbm-ride-history.css', array(), MPTBM_PLUGIN_VERSION);
			wp_enqueue_script('mptbm-ride-history-script', MPTBM_PLUGIN_URL . '/assets/frontend/js/mptbm-ride-history.js', array('jquery'), MPTBM_PLUGIN_VERSION, true);
			
			wp_localize_script('mptbm-ride-history-script', 'mptbm_ride_history', array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('mptbm_ride_history_nonce'),
				'loading_text' => __('Processing...', 'ecab-taxi-booking-manager'),
				'success_text' => __('Added to cart!', 'ecab-taxi-booking-manager'),
				'error_text' => __('Error occurred', 'ecab-taxi-booking-manager'),
				'rebook_text' => __('Book Again', 'ecab-taxi-booking-manager'),
				'login_required' => __('Please login to rebook', 'ecab-taxi-booking-manager')
			));
		}
		
		/**
		 * Shortcode for displaying ride history
		 */
		public function ride_history_shortcode($atts) {
			// If user is not logged in, show login message
			if (!is_user_logged_in()) {
				return '<div class="mptbm-login-required">' . 
					__('Please log in to view your ride history.', 'ecab-taxi-booking-manager') . 
					'<p><a href="' . wp_login_url(get_permalink()) . '" class="button">' . 
					__('Login', 'ecab-taxi-booking-manager') . '</a></p></div>';
			}
			
			$atts = shortcode_atts(array(
				'limit' => 10,
				'pagination' => 'yes'
			), $atts);
			
			ob_start();
			$this->display_ride_history($atts);
			return ob_get_clean();
		}
		
		/**
		 * Display ride history
		 */
		private function display_ride_history($atts) {
			$current_user = wp_get_current_user();
			$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
			$limit = intval($atts['limit']);
			
			// Get orders for current user that contain our taxi product type
			$args = array(
				'customer_id' => $current_user->ID,
				'limit' => $limit,
				'paged' => $paged,
				'paginate' => true
			);
			
			$orders = wc_get_orders($args);
			
			// Filter orders to only include those with our taxi products
			$taxi_orders = array();
			
			foreach ($orders->orders as $order) {
				foreach ($order->get_items() as $item) {
					$product_id = $item->get_product_id();
					$linked_id = MP_Global_Function::get_post_info($product_id, 'link_mptbm_id', $product_id);
					
					if (get_post_type($linked_id) == MPTBM_Function::get_cpt()) {
						$taxi_orders[] = $order;
						break;
					}
				}
			}
			
			// Display ride history
			include MPTBM_Function::template_path('registration/ride-history.php');
		}
		
		/**
		 * Get ride details from order item
		 */
		public function get_ride_details($order_id, $item_id) {
			$order = wc_get_order($order_id);
			if (!$order) {
				return false;
			}
			
			$item = $order->get_item($item_id);
			if (!$item) {
				return false;
			}
			
			$product_id = $item->get_product_id();
			$linked_id = MP_Global_Function::get_post_info($product_id, 'link_mptbm_id', $product_id);
			
			if (get_post_type($linked_id) != MPTBM_Function::get_cpt()) {
				return false;
			}
			
			// Get ride details from item meta
			$ride_details = array();
			$ride_details['product_id'] = $product_id;
			$ride_details['linked_id'] = $linked_id;
			
			// Get all item meta
			$item_meta = $item->get_meta_data();
			
			foreach ($item_meta as $meta) {
				$data = $meta->get_data();
				$key = $data['key'];
				$value = $data['value'];
				
				// Store relevant meta data
				if (strpos($key, 'mptbm_') === 0 || strpos($key, '_mptbm_') === 0) {
					$ride_details[$key] = $value;
				}
			}
			
			return $ride_details;
		}
		
		/**
		 * Ajax handler for rebooking
		 */
		public function rebook_ride() {
			// Verify nonce
			if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mptbm_ride_history_nonce')) {
				wp_send_json_error(array('message' => __('Security check failed', 'ecab-taxi-booking-manager')));
				die();
			}
			
			// Check if user is logged in
			if (!is_user_logged_in()) {
				wp_send_json_error(array('message' => __('Please login to rebook', 'ecab-taxi-booking-manager')));
				die();
			}
			
			// Get order and item IDs
			$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
			$item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
			
			if (!$order_id || !$item_id) {
				wp_send_json_error(array('message' => __('Invalid order or item', 'ecab-taxi-booking-manager')));
				die();
			}
			
			// Get ride details
			$ride_details = $this->get_ride_details($order_id, $item_id);
			
			if (!$ride_details) {
				wp_send_json_error(array('message' => __('Ride details not found', 'ecab-taxi-booking-manager')));
				die();
			}
			
			// Prepare cart item data
			$cart_item_data = array();
			
			// Get the necessary data for price calculation
			$post_id = $ride_details['linked_id'];
			
			// Extract required parameters from ride details
			$distance = isset($ride_details['_mptbm_distance']) ? $ride_details['_mptbm_distance'] : 0;
			$duration = isset($ride_details['_mptbm_duration']) ? $ride_details['_mptbm_duration'] : 0;
			$start_place = isset($ride_details['_mptbm_start_place']) ? $ride_details['_mptbm_start_place'] : '';
			$end_place = isset($ride_details['_mptbm_end_place']) ? $ride_details['_mptbm_end_place'] : '';
			$waiting_time = isset($ride_details['_mptbm_waiting_time']) ? $ride_details['_mptbm_waiting_time'] : 0;
			$return = isset($ride_details['_mptbm_taxi_return']) ? $ride_details['_mptbm_taxi_return'] : 1;
			$fixed_hour = isset($ride_details['_mptbm_fixed_hours']) ? $ride_details['_mptbm_fixed_hours'] : 0;
			$date = isset($ride_details['_mptbm_date']) ? $ride_details['_mptbm_date'] : date('Y-m-d');
			
			// Set cookies for distance and duration to ensure proper price calculation
			if ($distance) {
				setcookie('mptbm_distance', $distance, time() + 3600, '/');
				$_COOKIE['mptbm_distance'] = $distance;
			}
			
			if (isset($ride_details['_mptbm_distance_text'])) {
				setcookie('mptbm_distance_text', $ride_details['_mptbm_distance_text'], time() + 3600, '/');
				$_COOKIE['mptbm_distance_text'] = $ride_details['_mptbm_distance_text'];
			}
			
			if ($duration) {
				setcookie('mptbm_duration', $duration, time() + 3600, '/');
				$_COOKIE['mptbm_duration'] = $duration;
			}
			
			if (isset($ride_details['_mptbm_duration_text'])) {
				setcookie('mptbm_duration_text', $ride_details['_mptbm_duration_text'], time() + 3600, '/');
				$_COOKIE['mptbm_duration_text'] = $ride_details['_mptbm_duration_text'];
			}
			
			// Set transient for price calculation
			if (isset($ride_details['_mptbm_date'])) {
				set_transient('start_date_transient', $ride_details['_mptbm_date'], 3600);
			}
			
			// Calculate the price
			$price = MPTBM_Function::get_price($post_id, $distance, $duration, $start_place, $end_place, $waiting_time, $return, $fixed_hour);
			
			// Add required data to cart item
			$cart_item_data['mptbm_date'] = $date;
			$cart_item_data['mptbm_start_place'] = $start_place;
			$cart_item_data['mptbm_end_place'] = $end_place;
			$cart_item_data['mptbm_distance'] = $distance;
			$cart_item_data['mptbm_duration'] = $duration;
			$cart_item_data['mptbm_taxi_return'] = $return;
			$cart_item_data['mptbm_waiting_time'] = $waiting_time;
			$cart_item_data['mptbm_fixed_hours'] = $fixed_hour;
			$cart_item_data['link_mptbm_id'] = $post_id;
			
			// Add distance and duration text if available
			if (isset($ride_details['_mptbm_distance_text'])) {
				$cart_item_data['mptbm_distance_text'] = $ride_details['_mptbm_distance_text'];
			}
			
			if (isset($ride_details['_mptbm_duration_text'])) {
				$cart_item_data['mptbm_duration_text'] = $ride_details['_mptbm_duration_text'];
			}
			
			// Handle return date if available
			if ($return > 1 && MP_Global_Function::get_settings('mptbm_general_settings', 'enable_return_in_different_date') == 'yes') {
				if (isset($ride_details['_mptbm_return_date'])) {
					$cart_item_data['mptbm_return_target_date'] = $ride_details['_mptbm_return_date'];
				}
				
				if (isset($ride_details['_mptbm_return_time'])) {
					$cart_item_data['mptbm_return_target_time'] = $ride_details['_mptbm_return_time'];
				}
			}
			
			// Process extra services if available
			$extra_services = array();
			if (isset($ride_details['_mptbm_service_info']) && is_array($ride_details['_mptbm_service_info'])) {
				$service_info = $ride_details['_mptbm_service_info'];
				
				// Prepare extra service data for cart
				$service_names = array();
				$service_quantities = array();
				
				foreach ($service_info as $service) {
					if (isset($service['service_name']) && isset($service['service_quantity'])) {
						$service_names[] = $service['service_name'];
						$service_quantities[] = $service['service_quantity'];
					}
				}
				
				// Set POST data for extra services to be processed by cart_extra_service_info
				$_POST['mptbm_extra_service'] = $service_names;
				$_POST['mptbm_extra_service_qty'] = $service_quantities;
				$_POST['mptbm_date'] = $date;
				
				// Get extra service info
				$cart_item_data['mptbm_extra_service_info'] = MPTBM_Woocommerce::cart_extra_service_info($post_id);
			}
			
			// Set POST data for price calculation
			$_POST['mptbm_start_place'] = $start_place;
			$_POST['mptbm_end_place'] = $end_place;
			$_POST['mptbm_taxi_return'] = $return;
			$_POST['mptbm_waiting_time'] = $waiting_time;
			$_POST['mptbm_fixed_hours'] = $fixed_hour;
			$_POST['mptbm_date'] = $date;
			
			// Calculate total price
			$total_price = 0;
			
			// Get base price
			$wc_price = MP_Global_Function::wc_price($post_id, $price);
			$base_price = MP_Global_Function::price_convert_raw($wc_price);
			$total_price = $base_price;
			
			// Add extra service prices
			if (!empty($cart_item_data['mptbm_extra_service_info'])) {
				foreach ($cart_item_data['mptbm_extra_service_info'] as $service) {
					if (isset($service['service_price']) && isset($service['service_quantity'])) {
						$total_price += ($service['service_price'] * $service['service_quantity']);
					}
				}
			}
			
			// Set price data
			$cart_item_data['mptbm_base_price'] = $base_price;
			$cart_item_data['mptbm_tp'] = $total_price;
			$cart_item_data['line_total'] = $total_price;
			$cart_item_data['line_subtotal'] = $total_price;
			
			// Add to cart
			$product_id = $ride_details['product_id'];
			
			// Clear cart first to avoid conflicts
			WC()->cart->empty_cart();
			
			// Add to cart with proper data
			$added = WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);
			
			if ($added) {
				wp_send_json_success(array(
					'message' => __('Ride added to cart', 'ecab-taxi-booking-manager'),
					'redirect' => wc_get_cart_url()
				));
			} else {
				wp_send_json_error(array('message' => __('Failed to add ride to cart', 'ecab-taxi-booking-manager')));
			}
			
			die();
		}
		
		/**
		 * Ajax handler for unauthorized rebooking
		 */
		public function rebook_ride_unauthorized() {
			wp_send_json_error(array('message' => __('Please login to rebook', 'ecab-taxi-booking-manager')));
			die();
		}
	}
	
	new MPTBM_Ride_History();
}