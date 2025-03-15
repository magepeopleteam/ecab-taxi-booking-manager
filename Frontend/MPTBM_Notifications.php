<?php
/*
* @Author 		magePeople
* Copyright: 	mage-people.com
*/
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_Notifications')) {
	class MPTBM_Notifications {
		public function __construct() {
			// Register scripts and styles
			add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
			
			// Add notification settings to plugin settings page
			add_filter('mptbm_settings_general_arr', array($this, 'add_notification_settings'));
			
			// Add notification permission request
			add_action('wp_footer', array($this, 'notification_permission_request'));
			
			// Add notification for order status changes
			add_action('woocommerce_order_status_changed', array($this, 'order_status_notification'), 10, 4);
			
			// Add notification for upcoming rides (daily cron)
			add_action('mptbm_daily_notification_check', array($this, 'check_upcoming_rides'));
			
			// Register cron job for daily notification check
			add_action('init', array($this, 'register_cron_job'));
			
			// AJAX handlers for notification settings
			add_action('wp_ajax_mptbm_save_notification_settings', array($this, 'save_notification_settings'));
			add_action('wp_ajax_nopriv_mptbm_save_notification_settings', array($this, 'save_notification_settings_unauthorized'));
			
			// AJAX handler for sending test notification
			add_action('wp_ajax_mptbm_send_test_notification', array($this, 'send_test_notification'));
			
			// AJAX handler for getting notifications
			add_action('wp_ajax_mptbm_get_notifications', array($this, 'get_notifications'));
			
			// AJAX handler for checking new notifications
			add_action('wp_ajax_mptbm_check_new_notifications', array($this, 'check_new_notifications'));
		}
		
		/**
		 * Register cron job for daily notification check
		 */
		public function register_cron_job() {
			if (!wp_next_scheduled('mptbm_daily_notification_check')) {
				wp_schedule_event(time(), 'daily', 'mptbm_daily_notification_check');
			}
		}
		
		/**
		 * Enqueue scripts and styles
		 */
		public function enqueue_scripts() {
			wp_enqueue_style('mptbm-notifications-style', MPTBM_PLUGIN_URL . '/assets/frontend/css/mptbm-notifications.css', array(), MPTBM_PLUGIN_VERSION);
			wp_enqueue_script('mptbm-notifications-script', MPTBM_PLUGIN_URL . '/assets/frontend/js/mptbm-notifications.js', array('jquery'), MPTBM_PLUGIN_VERSION, true);
			
			// Get current user ID
			$user_id = get_current_user_id();
			
			// Localize script with notification settings
			$notification_settings = array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('mptbm_notification_nonce'),
				'enabled' => MPTBM_Function::get_general_settings('notification_enabled', 'yes'),
				'icon' => $this->get_notification_icon_url(),
				'permission_title' => __('Enable Notifications', 'ecab-taxi-booking-manager'),
				'permission_text' => __('Get updates about your taxi bookings', 'ecab-taxi-booking-manager'),
				'permission_button' => __('Allow Notifications', 'ecab-taxi-booking-manager'),
				'permission_denied_text' => __('You have denied notification permissions. Please enable them in your browser settings to receive booking updates.', 'ecab-taxi-booking-manager'),
				'order_status_notifications' => MPTBM_Function::get_general_settings('order_status_notifications', 'yes'),
				'upcoming_ride_notifications' => MPTBM_Function::get_general_settings('upcoming_ride_notifications', 'yes'),
				'reminder_hours' => MPTBM_Function::get_general_settings('reminder_hours', '24'),
				'user_id' => $user_id,
				'rebook_text' => __('Book Again', 'ecab-taxi-booking-manager')
			);
			
			wp_localize_script('mptbm-notifications-script', 'mptbm_notifications', $notification_settings);
		}
		
		/**
		 * Get notification icon URL
		 */
		private function get_notification_icon_url() {
			$icon_url = MPTBM_PLUGIN_URL . '/assets/images/taxi-notification-icon.png';
			
			// Check if the icon file exists
			$icon_path = MPTBM_PLUGIN_DIR . '/assets/images/taxi-notification-icon.png';
			if (!file_exists($icon_path)) {
				// Use default icon
				$icon_url = MPTBM_PLUGIN_URL . '/assets/images/default-notification-icon.png';
			}
			
			return $icon_url;
		}
		
		/**
		 * Add notification settings to plugin settings page
		 */
		public function add_notification_settings($settings) {
			$notification_settings = array(
				array(
					'type' => 'section',
					'title' => __('Browser Notifications', 'ecab-taxi-booking-manager'),
					'description' => __('Configure browser notification settings for booking updates', 'ecab-taxi-booking-manager')
				),
				array(
					'id' => 'notification_enabled',
					'title' => __('Enable Notifications', 'ecab-taxi-booking-manager'),
					'details' => __('Enable browser notifications for booking updates', 'ecab-taxi-booking-manager'),
					'type' => 'select',
					'default' => 'yes',
					'args' => array(
						'yes' => __('Yes', 'ecab-taxi-booking-manager'),
						'no' => __('No', 'ecab-taxi-booking-manager')
					)
				),
				array(
					'id' => 'order_status_notifications',
					'title' => __('Order Status Notifications', 'ecab-taxi-booking-manager'),
					'details' => __('Send notifications when order status changes', 'ecab-taxi-booking-manager'),
					'type' => 'select',
					'default' => 'yes',
					'args' => array(
						'yes' => __('Yes', 'ecab-taxi-booking-manager'),
						'no' => __('No', 'ecab-taxi-booking-manager')
					)
				),
				array(
					'id' => 'upcoming_ride_notifications',
					'title' => __('Upcoming Ride Reminders', 'ecab-taxi-booking-manager'),
					'details' => __('Send reminders for upcoming rides', 'ecab-taxi-booking-manager'),
					'type' => 'select',
					'default' => 'yes',
					'args' => array(
						'yes' => __('Yes', 'ecab-taxi-booking-manager'),
						'no' => __('No', 'ecab-taxi-booking-manager')
					)
				),
				array(
					'id' => 'reminder_hours',
					'title' => __('Reminder Hours', 'ecab-taxi-booking-manager'),
					'details' => __('Hours before the ride to send a reminder notification', 'ecab-taxi-booking-manager'),
					'type' => 'text',
					'default' => '24'
				),
				array(
					'id' => 'test_notification',
					'title' => __('Test Notification', 'ecab-taxi-booking-manager'),
					'details' => __('Send a test notification to verify your setup', 'ecab-taxi-booking-manager'),
					'type' => 'button',
					'label' => __('Send Test Notification', 'ecab-taxi-booking-manager'),
					'class' => 'mptbm-test-notification-button'
				)
			);
			
			// Add notification settings to general settings
			return array_merge($settings, $notification_settings);
		}
		
		/**
		 * Add notification permission request to footer
		 */
		public function notification_permission_request() {
			// Only show if notifications are enabled
			if (MPTBM_Function::get_general_settings('notification_enabled', 'yes') === 'yes') {
				?>
				<div class="mptbm-notification-permission" style="display: none;">
					<div class="mptbm-notification-permission-content">
						<div class="mptbm-notification-permission-icon">
							<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="#4a6ee0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
								<path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
								<circle cx="7" cy="8" r="1" fill="#4a6ee0"></circle>
								<circle cx="17" cy="8" r="1" fill="#4a6ee0"></circle>
							</svg>
						</div>
						<div class="mptbm-notification-permission-text">
							<h3><?php esc_html_e('Enable Notifications', 'ecab-taxi-booking-manager'); ?></h3>
							<p><?php esc_html_e('Get updates about your taxi bookings', 'ecab-taxi-booking-manager'); ?></p>
						</div>
						<div class="mptbm-notification-permission-actions">
							<button class="mptbm-notification-permission-allow"><?php esc_html_e('Allow Notifications', 'ecab-taxi-booking-manager'); ?></button>
							<button class="mptbm-notification-permission-close">&times;</button>
						</div>
					</div>
				</div>
				<?php
			}
		}
		
		/**
		 * Send notification for order status changes
		 */
		public function order_status_notification($order_id, $old_status, $new_status, $order) {
			// Check if order status notifications are enabled
			if (MPTBM_Function::get_general_settings('order_status_notifications', 'yes') !== 'yes') {
				return;
			}
			
			// Check if this is a taxi booking order
			$has_taxi_booking = false;
			foreach ($order->get_items() as $item) {
				$product_id = $item->get_product_id();
				$linked_id = MP_Global_Function::get_post_info($product_id, 'link_mptbm_id', $product_id);
				
				if (get_post_type($linked_id) == MPTBM_Function::get_cpt()) {
					$has_taxi_booking = true;
					break;
				}
			}
			
			if (!$has_taxi_booking) {
				return;
			}
			
			// Get customer details
			$customer_id = $order->get_customer_id();
			if (!$customer_id) {
				return;
			}
			
			// Get notification data
			$notification_data = array(
				'title' => sprintf(__('Booking #%s Status Update', 'ecab-taxi-booking-manager'), $order->get_order_number()),
				'body' => sprintf(__('Your booking status has been updated to: %s', 'ecab-taxi-booking-manager'), wc_get_order_status_name($new_status)),
				'icon' => $this->get_notification_icon_url(),
				'url' => $order->get_view_order_url(),
				'order_id' => $order_id,
				'status' => $new_status
			);
			
			// Store notification data for the user
			$this->store_notification($customer_id, $notification_data);
		}
		
		/**
		 * Check for upcoming rides and send notifications
		 */
		public function check_upcoming_rides() {
			// Check if upcoming ride notifications are enabled
			if (MPTBM_Function::get_general_settings('upcoming_ride_notifications', 'yes') !== 'yes') {
				return;
			}
			
			// Get reminder hours
			$reminder_hours = intval(MPTBM_Function::get_general_settings('reminder_hours', '24'));
			
			// Get orders with upcoming rides
			$args = array(
				'status' => array('processing', 'on-hold', 'completed'),
				'limit' => -1,
				'date_created' => '>' . (time() - (30 * DAY_IN_SECONDS)) // Only check orders from the last 30 days
			);
			
			$orders = wc_get_orders($args);
			
			foreach ($orders as $order) {
				$order_id = $order->get_id();
				$customer_id = $order->get_customer_id();
				
				if (!$customer_id) {
					continue;
				}
				
				foreach ($order->get_items() as $item_id => $item) {
					$product_id = $item->get_product_id();
					$linked_id = MP_Global_Function::get_post_info($product_id, 'link_mptbm_id', $product_id);
					
					if (get_post_type($linked_id) != MPTBM_Function::get_cpt()) {
						continue;
					}
					
					// Get journey date and time
					$journey_date = $item->get_meta('mptbm_journey_date');
					$journey_time = $item->get_meta('mptbm_journey_time');
					
					if (!$journey_date || !$journey_time) {
						continue;
					}
					
					// Convert to timestamp
					$journey_timestamp = strtotime($journey_date . ' ' . $journey_time);
					$current_timestamp = current_time('timestamp');
					
					// Calculate hours until journey
					$hours_until_journey = ($journey_timestamp - $current_timestamp) / HOUR_IN_SECONDS;
					
					// Check if it's time to send a reminder
					if ($hours_until_journey > 0 && $hours_until_journey <= $reminder_hours && $hours_until_journey >= ($reminder_hours - 1)) {
						// Get ride details
						$start_place = $item->get_meta('mptbm_start_place');
						$end_place = $item->get_meta('mptbm_end_place');
						
						// Create notification data
						$notification_data = array(
							'title' => __('Upcoming Ride Reminder', 'ecab-taxi-booking-manager'),
							'body' => sprintf(__('Your ride from %1$s to %2$s is scheduled for %3$s at %4$s', 'ecab-taxi-booking-manager'), 
								$start_place, 
								$end_place, 
								$journey_date, 
								$journey_time
							),
							'icon' => $this->get_notification_icon_url(),
							'url' => $order->get_view_order_url(),
							'order_id' => $order_id,
							'type' => 'reminder'
						);
						
						// Store notification data for the user
						$this->store_notification($customer_id, $notification_data);
					}
				}
			}
		}
		
		/**
		 * Store notification data for a user
		 */
		private function store_notification($user_id, $notification_data) {
			// Get existing notifications
			$notifications = get_user_meta($user_id, 'mptbm_notifications', true);
			if (!is_array($notifications)) {
				$notifications = array();
			}
			
			// Add timestamp and ID to notification
			$notification_data['timestamp'] = current_time('timestamp');
			$notification_data['id'] = uniqid('notification_');
			
			// Add notification to the beginning of the array
			array_unshift($notifications, $notification_data);
			
			// Limit to 50 notifications
			if (count($notifications) > 50) {
				$notifications = array_slice($notifications, 0, 50);
			}
			
			// Update user meta
			update_user_meta($user_id, 'mptbm_notifications', $notifications);
			
			// Trigger browser notification via custom user meta that will be checked by JavaScript
			update_user_meta($user_id, 'mptbm_new_notification', $notification_data);
		}
		
		/**
		 * AJAX handler for getting notifications
		 */
		public function get_notifications() {
			// Verify nonce
			if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mptbm_notification_nonce')) {
				wp_send_json_error(array('message' => __('Security check failed', 'ecab-taxi-booking-manager')));
				die();
			}
			
			// Check if user is logged in
			if (!is_user_logged_in()) {
				wp_send_json_error(array('message' => __('You must be logged in to get notifications', 'ecab-taxi-booking-manager')));
				die();
			}
			
			$user_id = get_current_user_id();
			
			// Get notifications
			$notifications = get_user_meta($user_id, 'mptbm_notifications', true);
			if (!is_array($notifications)) {
				$notifications = array();
			}
			
			// Get unread count
			$unread_count = 0;
			foreach ($notifications as $notification) {
				if (isset($notification['read']) && !$notification['read']) {
					$unread_count++;
				}
			}
			
			wp_send_json_success(array(
				'notifications' => $notifications,
				'unread_count' => $unread_count
			));
			die();
		}
		
		/**
		 * AJAX handler for checking new notifications
		 */
		public function check_new_notifications() {
			// Verify nonce
			if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mptbm_notification_nonce')) {
				wp_send_json_error(array('message' => __('Security check failed', 'ecab-taxi-booking-manager')));
				die();
			}
			
			// Check if user is logged in
			if (!is_user_logged_in()) {
				wp_send_json_error(array('message' => __('You must be logged in to check notifications', 'ecab-taxi-booking-manager')));
				die();
			}
			
			$user_id = get_current_user_id();
			
			// Get last timestamp
			$last_timestamp = isset($_POST['last_timestamp']) ? intval($_POST['last_timestamp']) : 0;
			
			// Check for new notification
			$new_notification = get_user_meta($user_id, 'mptbm_new_notification', true);
			
			// If there's a new notification and it's newer than the last timestamp
			if ($new_notification && isset($new_notification['timestamp']) && $new_notification['timestamp'] > $last_timestamp) {
				// Delete the meta to avoid showing the same notification again
				delete_user_meta($user_id, 'mptbm_new_notification');
				
				wp_send_json_success(array(
					'new_notifications' => array($new_notification)
				));
			} else {
				wp_send_json_success(array(
					'new_notifications' => array()
				));
			}
			
			die();
		}
		
		/**
		 * AJAX handler for saving notification settings
		 */
		public function save_notification_settings() {
			// Verify nonce
			if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mptbm_notification_nonce')) {
				wp_send_json_error(array('message' => __('Security check failed', 'ecab-taxi-booking-manager')));
				die();
			}
			
			// Check if user is logged in
			if (!is_user_logged_in()) {
				wp_send_json_error(array('message' => __('You must be logged in to save settings', 'ecab-taxi-booking-manager')));
				die();
			}
			
			$user_id = get_current_user_id();
			$enabled = isset($_POST['enabled']) ? sanitize_text_field($_POST['enabled']) : 'no';
			$endpoint = isset($_POST['endpoint']) ? sanitize_text_field($_POST['endpoint']) : '';
			$keys = isset($_POST['keys']) ? $_POST['keys'] : array();
			
			// Sanitize keys
			$sanitized_keys = array();
			if (is_array($keys)) {
				foreach ($keys as $key => $value) {
					$sanitized_keys[sanitize_text_field($key)] = sanitize_text_field($value);
				}
			}
			
			// Save user notification settings
			update_user_meta($user_id, 'mptbm_notification_enabled', $enabled);
			update_user_meta($user_id, 'mptbm_notification_endpoint', $endpoint);
			update_user_meta($user_id, 'mptbm_notification_keys', $sanitized_keys);
			
			wp_send_json_success(array('message' => __('Notification settings saved', 'ecab-taxi-booking-manager')));
			die();
		}
		
		/**
		 * AJAX handler for unauthorized notification settings
		 */
		public function save_notification_settings_unauthorized() {
			wp_send_json_error(array('message' => __('You must be logged in to save settings', 'ecab-taxi-booking-manager')));
			die();
		}
		
		/**
		 * AJAX handler for sending test notification
		 */
		public function send_test_notification() {
			// Verify nonce
			if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mptbm_notification_nonce')) {
				wp_send_json_error(array('message' => __('Security check failed', 'ecab-taxi-booking-manager')));
				die();
			}
			
			// Check if user is logged in
			if (!is_user_logged_in()) {
				wp_send_json_error(array('message' => __('You must be logged in to send test notification', 'ecab-taxi-booking-manager')));
				die();
			}
			
			$user_id = get_current_user_id();
			
			// Create test notification data
			$notification_data = array(
				'title' => __('Test Notification', 'ecab-taxi-booking-manager'),
				'body' => __('This is a test notification from E-cab Taxi Booking Manager', 'ecab-taxi-booking-manager'),
				'icon' => $this->get_notification_icon_url(),
				'url' => home_url(),
				'type' => 'test'
			);
			
			// Store notification data for the user
			$this->store_notification($user_id, $notification_data);
			
			wp_send_json_success(array('message' => __('Test notification sent', 'ecab-taxi-booking-manager')));
			die();
		}
	}
	
	new MPTBM_Notifications();
}