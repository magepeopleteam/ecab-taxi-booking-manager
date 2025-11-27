<?php
if (!function_exists('mptbm_get_translation')) {
	require_once dirname(__DIR__) . '/inc/mptbm-translation-helper.php';
}
/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.
if (!class_exists('MPTBM_Woocommerce')) {
	class MPTBM_Woocommerce
	{
		private $custom_order_data = array(); // Property to store the data
		private $ordered_item_name;
		private $error;
		public function __construct()
		{
			$this->error = new WP_Error();
			add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 90, 3);
			add_action('woocommerce_before_calculate_totals', array($this, 'before_calculate_totals'), 90);
			add_filter('woocommerce_cart_item_thumbnail', array($this, 'cart_item_thumbnail'), 90, 3);
			add_filter('woocommerce_get_item_data', array($this, 'get_item_data'), 90, 2);
			// Replace displayed quantity on order/thank-you using transport quantity meta
			add_filter('woocommerce_order_item_quantity_html', array($this, 'filter_order_item_quantity'), 10, 2);
			//************//
			add_action('woocommerce_after_checkout_validation', array($this, 'after_checkout_validation'));
			add_action('woocommerce_checkout_create_order_line_item', array($this, 'checkout_create_order_line_item'), 90, 4);

			add_action('woocommerce_checkout_order_processed', array($this, 'checkout_order_processed'), 90, 3);
			add_action('woocommerce_store_api_checkout_order_processed', array($this, 'checkout_order_processed'), 90, 3);
			add_filter('woocommerce_order_status_changed', array($this, 'order_status_changed'));
			/*****************************/
			add_action('wp_ajax_mptbm_add_to_cart', [$this, 'mptbm_add_to_cart']);
			add_action('wp_ajax_nopriv_mptbm_add_to_cart', [$this, 'mptbm_add_to_cart']);

			// Add custom checkout fields
			add_action('woocommerce_after_order_notes', array($this, 'render_custom_checkout_fields'));
			add_action('woocommerce_checkout_process', array($this, 'validate_custom_checkout_fields'));
			add_action('woocommerce_checkout_update_order_meta', array($this, 'save_custom_checkout_fields'));

			// Show custom fields in admin order details
			add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_custom_fields_in_admin_order'), 10, 1);
			// Show custom fields in customer and admin emails
			add_filter('woocommerce_email_order_meta_fields', array($this, 'add_custom_fields_to_email'), 10, 3);

			add_action('wp_ajax_mptbm_file_upload', array($this, 'ajax_file_upload'));
			add_action('wp_ajax_nopriv_mptbm_file_upload', array($this, 'ajax_file_upload'));
		}

		public function add_cart_item_data($cart_item_data, $product_id)
		{
			$mptbm_original_price_base = isset($_POST['mptbm_original_price_base']) ? sanitize_text_field($_POST['mptbm_original_price_base']) : '';
			
			$quantity = isset($_POST['transport_quantity']) ? sanitize_text_field($_POST['transport_quantity']) : 1;
			$cart_item_data['mptbm_transport_quantity'] = $quantity;
			$linked_id = MP_Global_Function::get_post_info($product_id, 'link_mptbm_id', $product_id);
			$post_id = is_string(get_post_status($linked_id)) ? $linked_id : $product_id;
			if (get_post_type($post_id) == MPTBM_Function::get_cpt()) {
				$distance = isset($_COOKIE['mptbm_distance']) ? absint($_COOKIE['mptbm_distance']) : '';
				$duration = isset($_COOKIE['mptbm_duration']) ? absint($_COOKIE['mptbm_duration']) : '';
				$start_place = isset($_POST['mptbm_start_place']) ? sanitize_text_field($_POST['mptbm_start_place']) : '';
				$end_place = isset($_POST['mptbm_end_place']) ? sanitize_text_field($_POST['mptbm_end_place']) : '';
				$waiting_time = isset($_POST['mptbm_waiting_time']) ? sanitize_text_field($_POST['mptbm_waiting_time']) : 0;
				$return = isset($_POST['mptbm_taxi_return']) ? sanitize_text_field($_POST['mptbm_taxi_return']) : 1;
				$fixed_hour = isset($_POST['mptbm_fixed_hours']) ? sanitize_text_field($_POST['mptbm_fixed_hours']) : 0;
				
				// Store date, time, and coordinates in transients for dynamic pricing
				$booking_date = isset($_POST['mptbm_date']) ? sanitize_text_field($_POST['mptbm_date']) : '';
				$booking_time = isset($_POST['mptbm_time']) ? sanitize_text_field($_POST['mptbm_time']) : '';
				$pickup_lat = isset($_POST['pickup_lat']) ? sanitize_text_field($_POST['pickup_lat']) : '';
				$pickup_lng = isset($_POST['pickup_lng']) ? sanitize_text_field($_POST['pickup_lng']) : '';
				$drop_lat = isset($_POST['drop_lat']) ? sanitize_text_field($_POST['drop_lat']) : '';
				$drop_lng = isset($_POST['drop_lng']) ? sanitize_text_field($_POST['drop_lng']) : '';
				
				if (!empty($booking_date)) {
					set_transient('start_date_transient', $booking_date, HOUR_IN_SECONDS);
				}
				if (!empty($booking_time)) {
					set_transient('start_time_schedule_transient', $booking_time, HOUR_IN_SECONDS);
				}
				if (!empty($pickup_lat) && !empty($pickup_lng)) {
					set_transient('mptbm_pickup_lat', $pickup_lat, HOUR_IN_SECONDS);
					set_transient('mptbm_pickup_lng', $pickup_lng, HOUR_IN_SECONDS);
				}
				if (!empty($drop_lat) && !empty($drop_lng)) {
					set_transient('mptbm_drop_lat', $drop_lat, HOUR_IN_SECONDS);
					set_transient('mptbm_drop_lng', $drop_lng, HOUR_IN_SECONDS);
				}
				
				// Also store in session as backup
				if (session_status() === PHP_SESSION_NONE) {
					session_start();
				}
				if (!empty($pickup_lat) && !empty($pickup_lng)) {
					$_SESSION['pickup_lat'] = $pickup_lat;
					$_SESSION['pickup_lng'] = $pickup_lng;
				}
				if (!empty($drop_lat) && !empty($drop_lng)) {
					$_SESSION['drop_lat'] = $drop_lat;
					$_SESSION['drop_lng'] = $drop_lng;
				}
				
				// Calculate single-unit transport price
				$price = MPTBM_Function::get_price($post_id, $distance, $duration, $start_place, $end_place, $waiting_time, $return, $fixed_hour);
				$wc_price = MP_Global_Function::wc_price($post_id, $price);
				$raw_price = MP_Global_Function::price_convert_raw($wc_price);
				// Multiply transport unit price by quantity
				$transport_total_price = $raw_price * $quantity;
				// Calculate extra service total (single add-on, not per transport unit)
				$extra_services = self::cart_extra_service_info($post_id);
				$extra_total_price = 0;
				foreach ($extra_services as $svc) {
					$extra_total_price += ($svc['service_price'] * $svc['service_quantity']);
				}
				// Final total: transport plus extra services
				$total_price = $transport_total_price + $extra_total_price;
				$cart_item_data['mptbm_date'] = isset($_POST['mptbm_date']) ? sanitize_text_field($_POST['mptbm_date']) : '';
				$cart_item_data['mptbm_taxi_return'] = $return;
				$cart_item_data['mptbm_waiting_time'] = $waiting_time;
				$cart_item_data['mptbm_start_place'] = wp_strip_all_tags($start_place);
				$cart_item_data['mptbm_end_place'] = wp_strip_all_tags($end_place);
				$cart_item_data['mptbm_distance'] = $distance;
				$cart_item_data['mptbm_distance_text'] = isset($_COOKIE['mptbm_distance_text']) ? sanitize_text_field($_COOKIE['mptbm_distance_text']) : '';
				$cart_item_data['mptbm_duration'] = $duration;
				$cart_item_data['mptbm_fixed_hours'] = $fixed_hour;
				$cart_item_data['mptbm_duration_text'] = isset($_COOKIE['mptbm_duration_text']) ? sanitize_text_field($_COOKIE['mptbm_duration_text']) : '';
				$cart_item_data['mptbm_base_price'] = $raw_price;
				$cart_item_data['mptbm_extra_service_info'] = self::cart_extra_service_info($post_id);
				$cart_item_data['mptbm_tp'] = $total_price;
				$cart_item_data['line_total'] = $total_price;
				$cart_item_data['line_subtotal'] = $total_price;
				$cart_item_data['mptbm_passengers'] = isset($_POST['mptbm_passengers']) ? absint($_POST['mptbm_passengers']) : 1;
				if ($return > 1 && MP_Global_Function::get_settings('mptbm_general_settings', 'enable_return_in_different_date') == 'yes') {
					$return_target_date = isset($_POST['mptbm_return_date']) ? sanitize_text_field($_POST['mptbm_return_date']) : '';
					$return_target_time = isset($_POST['mptbm_return_time']) ? sanitize_text_field($_POST['mptbm_return_time']) : '';
					$cart_item_data['mptbm_return_target_date'] = $return_target_date;
					$cart_item_data['mptbm_return_target_time'] = $return_target_time;
				}
				$cart_item_data['original_price_based'] = isset($_POST['mptbm_original_price_base']) && $_POST['mptbm_original_price_base'] ? sanitize_text_field($_POST['mptbm_original_price_base']) : (isset($_POST['mptbm_price_based']) ? sanitize_text_field($_POST['mptbm_price_based']) : '');
				$cart_item_data = apply_filters('mptbm_add_cart_item', $cart_item_data, $post_id);
			}
			$cart_item_data['mptbm_id'] = $post_id;
			// echo '<pre>';print_r($cart_item_data);echo '</pre>';
			return $cart_item_data;
		}
		public function before_calculate_totals($cart_object)
		{

			foreach ($cart_object->cart_contents as $value) {
				$post_id = array_key_exists('mptbm_id', $value) ? $value['mptbm_id'] : 0;
				if (get_post_type($post_id) == MPTBM_Function::get_cpt()) {
					$total_price = $value['mptbm_tp'];
					if (isset($_SESSION['geo_fence_post_' . $post_id])) {
						// Extract amount from session
						$session_data = $_SESSION['geo_fence_post_' . $post_id];
						// Check if session data contains the amount
						if (isset($session_data[0])) {
							// Add the amount to the price
							$total_price += (float)$session_data[0];
						}
					}
					$value['data']->set_price($total_price);
					$value['data']->set_regular_price($total_price);
					$value['data']->set_sale_price($total_price);
					$value['data']->get_price();
				}
			}
		}
		public function cart_item_thumbnail($thumbnail, $cart_item)
		{
			$mptbm_id = array_key_exists('mptbm_id', $cart_item) ? $cart_item['mptbm_id'] : 0;
			if (get_post_type($mptbm_id) == MPTBM_Function::get_cpt()) {
				$thumbnail = '<div class="bg_image_area" data-href="' . get_the_permalink($mptbm_id) . '"><div data-bg-image="' . MP_Global_Function::get_image_url($mptbm_id) . '"></div></div>';
			}
			return $thumbnail;
		}
		public function get_item_data($item_data, $cart_item)
		{
			$post_id = array_key_exists('mptbm_id', $cart_item) ? $cart_item['mptbm_id'] : 0;
			if (get_post_type($post_id) == MPTBM_Function::get_cpt()) {
				$original_price_based = isset($cart_item['original_price_based']) ? $cart_item['original_price_based'] : '';
				$disable_dropoff_hourly = MP_Global_Function::get_settings('mptbm_general_settings', 'disable_dropoff_hourly', 'enable');
				ob_start();
				$this->show_cart_item($cart_item, $post_id);
				do_action('mptbm_show_cart_item', $cart_item, $post_id);
				$cart_html = ob_get_clean();
				// Remove Drop-Off Location row if needed
				if ($original_price_based === 'fixed_hourly' && $disable_dropoff_hourly === 'disable') {
					$cart_html = preg_replace('/<li>.*?Drop-Off Location.*?<\/li>/is', '', $cart_html);
				}
				$item_data[] = array('key' => esc_html__('Booking Details ', 'ecab-taxi-booking-manager'), 'value' => $cart_html);
			}
			return $item_data;
		}
		//**************//
		public function after_checkout_validation()
		{
			global $woocommerce;
			$items = $woocommerce->cart->get_cart();
			foreach ($items as $values) {
				$post_id = array_key_exists('mptbm_id', $values) ? $values['mptbm_id'] : 0;
				if (get_post_type($post_id) == MPTBM_Function::get_cpt()) {
					do_action('mptbm_validate_cart_item', $values, $post_id);
				}
			}
		}


		public function checkout_create_order_line_item($item, $cart_item_key, $values)
		{
			$this->ordered_item_name = $item->get_name();
			$post_id = array_key_exists('mptbm_id', $values) ? $values['mptbm_id'] : 0;
			if (get_post_type($post_id) == MPTBM_Function::get_cpt()) {
				$original_price_based = isset($values['original_price_based']) ? $values['original_price_based'] : '';
				$disable_dropoff_hourly = MP_Global_Function::get_settings('mptbm_general_settings', 'disable_dropoff_hourly', 'enable');
				$date = isset($values['mptbm_date']) ? $values['mptbm_date'] : '';
				$start_location = isset($values['mptbm_start_place']) ? $values['mptbm_start_place'] : '';
				$end_location = isset($values['mptbm_end_place']) ? $values['mptbm_end_place'] : '';
				$item->add_meta_data(esc_html__('Pickup Location ', 'ecab-taxi-booking-manager'), $start_location);
				if (!($original_price_based === 'fixed_hourly' && $disable_dropoff_hourly === 'disable')) {
					$item->add_meta_data(esc_html__('Drop-Off Location ', 'ecab-taxi-booking-manager'), $end_location);
				}
				$distance = isset($values['mptbm_distance']) ? $values['mptbm_distance'] : '';
				$distance_text = isset($values['mptbm_distance_text']) ? $values['mptbm_distance_text'] : '';
				$duration = isset($values['mptbm_duration']) ? $values['mptbm_duration'] : '';
				$duration_text = isset($values['mptbm_duration_text']) ? $values['mptbm_duration_text'] : '';
				$base_price = isset($values['mptbm_base_price']) ? $values['mptbm_base_price'] : '';
				$return = $values['mptbm_taxi_return'] ?? '';
				$waiting_time = $values['mptbm_waiting_time'] ?? '';
				$fixed_time = $values['mptbm_fixed_hours'] ?? 0;
				$extra_service = $values['mptbm_extra_service_info'] ?? [];
				$price = isset($values['mptbm_tp']) ? $values['mptbm_tp'] : '';
				$transport_quantity = isset($values['mptbm_transport_quantity']) ? $values['mptbm_transport_quantity'] : 1;
				$item->set_quantity( $transport_quantity );
				if ($original_price_based !== 'manual') {
					$item->add_meta_data(esc_html__('Approximate Distancee ', 'ecab-taxi-booking-manager'), $distance_text);
					$item->add_meta_data(esc_html__('Approximate Time ', 'ecab-taxi-booking-manager'), $duration_text);
				}

				if ($waiting_time && $waiting_time > 0) {
					$item->add_meta_data(mptbm_get_translation('extra_waiting_hours_label', __('Extra Waiting Hours', 'ecab-taxi-booking-manager')), $waiting_time . ' ' . mptbm_get_translation('hours_in_waiting_label', __('Hour', 'ecab-taxi-booking-manager')));
				}
				if ($fixed_time && $fixed_time > 0) {
					$item->add_meta_data(mptbm_get_translation('service_times_label', __('Service Times', 'ecab-taxi-booking-manager')), $fixed_time . ' ' . mptbm_get_translation('hours_in_waiting_label', __('Hour', 'ecab-taxi-booking-manager')));
				}
				$item->add_meta_data(esc_html__('Date ', 'ecab-taxi-booking-manager'), esc_html(MP_Global_Function::date_format($date)));
				$item->add_meta_data(esc_html__('Time ', 'ecab-taxi-booking-manager'), esc_html(MP_Global_Function::date_format($date, 'time')));
				$item->add_meta_data(esc_html__('Transport Quantity ', 'ecab-taxi-booking-manager'), $transport_quantity);
				// Add passenger count to order meta only if the setting is enabled
				$show_passengers = MP_Global_Function::get_settings('mptbm_general_settings', 'show_number_of_passengers', 'no');
				if ($show_passengers === 'yes') {
					$passengers = isset($values['mptbm_passengers']) ? absint($values['mptbm_passengers']) : 1;
					$item->add_meta_data(esc_html__('Number of Passengers', 'ecab-taxi-booking-manager'), $passengers);
					$item->add_meta_data('_mptbm_passengers', $passengers);
				}

				if ($return && $return > 1) {
					$item->add_meta_data(esc_html__('Transfer Type', 'ecab-taxi-booking-manager'), esc_html__('Return ', 'ecab-taxi-booking-manager'));
					if (MP_Global_Function::get_settings('mptbm_general_settings', 'enable_return_in_different_date') == 'yes') {
						$return_date = $values['mptbm_return_target_date'] ?? '';
						$return_time = $values['mptbm_return_target_time'] ?? '';

						if ($return_time !== "") {
							if ($return_time !== "0") {
								// Convert return time to hours and minutes
								$time_parts = explode('.', $return_time);
								$hours = isset($time_parts[0]) ? $time_parts[0] : 0;
								$decimal_part = isset($time_parts[1]) ? $time_parts[1] : 0;
								$interval_time = MPTBM_Function::get_general_settings('mptbm_pickup_interval_time');

								if ($interval_time == "5" || $interval_time == "15") {
									if ($decimal_part != 3) {
										$minutes = isset($decimal_part) ? (int) $decimal_part * 1 : 0; // Multiply by 1 to convert to minutes
									} else {
										$minutes = isset($decimal_part) ? (int) $decimal_part * 10 : 0; // Multiply by 10 to convert to minutes
									}
								} else {
									$minutes = isset($decimal_part) ? (int) $decimal_part * 1 : 0; // Multiply by 1 to convert to minutes
								}
							} else {
								$hours = 0;
								$minutes = 0;
							}
						} else {
							$hours = 0;
							$minutes = 0;
						}

						// Format hours and minutes
						$return_time_formatted = sprintf('%02d:%02d', $hours, $minutes);

						// Combine date and time if both are available
						$return_date_time = $return_date ? gmdate("Y-m-d", strtotime($return_date)) : "";
						if ($return_date_time && $return_time !== "") {
							$return_date_time .= " " . $return_time_formatted;
						}


						$item->add_meta_data(esc_html__('Return Date', 'ecab-taxi-booking-manager'), esc_html(MP_Global_Function::date_format($return_date_time)));
						$item->add_meta_data(esc_html__('Return Time', 'ecab-taxi-booking-manager'), esc_html(MP_Global_Function::date_format($return_date_time, 'time')));
						$item->add_meta_data('_mptbm_return_date', $return_date);
						$item->add_meta_data('_mptbm_return_time', $return_time);
					}
					$item->add_meta_data(esc_html__('Transport Quantity', 'ecab-taxi-booking-manager'), $transport_quantity);
				}
				$price_display_type = MP_Global_Function::get_post_info($post_id, 'mptbm_price_display_type');
				if ($price_display_type === 'custom_message') {
					$custom_message = MP_Global_Function::get_post_info($post_id, 'mptbm_custom_price_message');
					$item->add_meta_data(esc_html__('Price ', 'ecab-taxi-booking-manager'), esc_html($custom_message));
				} else {
					$item->add_meta_data(esc_html__('Price ', 'ecab-taxi-booking-manager'), wp_kses_post(wc_price($base_price)));
				}
				if (sizeof($extra_service) > 0) {
					$item->add_meta_data(esc_html__('Optional Service ', 'ecab-taxi-booking-manager'), '');
					foreach ($extra_service as $service) {
						$item->add_meta_data(esc_html__('Services Name ', 'ecab-taxi-booking-manager'), $service['service_name']);
						$item->add_meta_data(esc_html__('Services Quantity ', 'ecab-taxi-booking-manager'), $service['service_quantity']);
						$item->add_meta_data(esc_html__('Price ', 'ecab-taxi-booking-manager'), esc_html(' ( ') . wp_kses_post(wc_price($service['service_price'])) . esc_html(' X ') . esc_html($service['service_quantity']) . esc_html(') = ') . wp_kses_post(wc_price($service['service_price'] * $service['service_quantity'])));
					}
				}
				if (class_exists('MPTBM_Plugin_Ecab_Calendar_Addon')) {
					// Prepare date and time for Google Calendar format
					$formatted_date = MP_Global_Function::date_format($date);
					$formatted_time = MP_Global_Function::date_format($date, 'time');
					// Combine the provided formatted date and time
					$date_time_string = $formatted_date . ' ' . $formatted_time; // Combine date and time as a single string

					// Get the WordPress time zone
					$timezone = new DateTimeZone(wp_timezone_string());

					// Create DateTime object with the combined date and time, and apply WordPress time zone
					$start_date_time = new DateTime($date_time_string, $timezone);

					// Convert to UTC (Google Calendar requires UTC time format)
					$start_date_time->setTimezone(new DateTimeZone('UTC'));

					// Format date and time for Google Calendar
					$formatted_date_time = $start_date_time->format('Ymd\THis\Z'); // Start time in Google Calendar format

					// For the event end time (assuming 1 hour duration)
					$end_date_time = clone $start_date_time;
					$end_date_time->modify('+2  hour'); // Set the end time to 1 hour later
					$formatted_end_time = $end_date_time->format('Ymd\THis\Z'); // End time in Google Calendar format
					$driver_id = get_post_meta($post_id, 'mptbm_selected_driver', true);
					if ($driver_id) {
						$driver_info = get_userdata($driver_id);
						$driver_name = $driver_info->display_name;
						$driver_email = $driver_info->user_email;
					} else {
						$driver_name = '';
						$driver_email = '';
					}

					// Build the details string conditionally
					$details = "Transport service from " . $start_location . " to " . $end_location;
					if ($driver_email) {
						$details .= ". Driver email: " . $driver_email;
					}
					if ($driver_name) {
						$driver_name = $driver_name;
					}

					// Create Google Calendar link
					$google_calendar_link = "https://www.google.com/calendar/render?action=TEMPLATE&text="
						. urlencode($this->ordered_item_name) // Event title
						. "&dates=" . $formatted_date_time . "/" . $formatted_end_time // Start and end times
						. "&details=" . urlencode($details)
						. "&location=" . urlencode($start_location)
						. "&sf=true&output=xml";

					// Add Google Calendar link as meta data
					$item->add_meta_data(
						esc_html__('Add this event to your Google Calendar', 'ecab-taxi-booking-manager'),
						'<a href="' . esc_url($google_calendar_link) . '" target="_blank">' . esc_html__('Add this event to your Google Calendar', 'ecab-taxi-booking-manager') . '</a>'
					);
				}
				$item->add_meta_data('_mptbm_id', $post_id);
				$item->add_meta_data('_mptbm_date', $date);
				$item->add_meta_data('_mptbm_start_place', $start_location);
				if (!($original_price_based === 'fixed_hourly' && $disable_dropoff_hourly === 'disable')) {
					$item->add_meta_data('_mptbm_end_place', $end_location);
				}
				$item->add_meta_data('_mptbm_taxi_return', $return);
				$item->add_meta_data('_mptbm_waiting_time', $waiting_time);
				$item->add_meta_data('_mptbm_fixed_hours', $fixed_time);
				$item->add_meta_data('_mptbm_distance', $distance);
				$item->add_meta_data('_mptbm_distance_text', $distance_text);
				$item->add_meta_data('_mptbm_duration', $duration);
				$item->add_meta_data('_mptbm_duration_text', $duration_text);
				$item->add_meta_data('_mptbm_base_price', $base_price);
				$item->add_meta_data('_mptbm_tp', $price);
				$item->add_meta_data('_mptbm_service_info', $extra_service);
				$item->add_meta_data('_mptbm_transport_quantity', $transport_quantity);

				do_action('mptbm_checkout_create_order_line_item', $item, $values);
			}
		}
		public function checkout_order_processed($order_id)
		{

			$result   = ! is_numeric( $order_id ) ? json_decode( $order_id ) : [ 0 ];
			$order_id = ! is_numeric( $order_id ) ? $result->id : $order_id;
			if ( ! $order_id ) {
				return;
			}

			// Send email notification
			$admin_email = get_option('admin_email');
	//wp_mail($admin_email, 'MPTBM Order Processed', 'Order ID: ' . $order_id);
			if ($order_id) {

				$order = wc_get_order($order_id);

				// Get all meta data
				$meta_data = $order->get_meta_data();

				// Initialize an associative array to store meta keys and values
				$meta_array = [];

				foreach ($meta_data as $meta) {
					// Get the meta key and value
					$meta_key = $meta->get_data()['key'];
					$meta_value = $meta->get_data()['value'];

					// Store the key-value pair in the associative array
					$meta_array[$meta_key] = $meta_value;
				}

				// Unset any meta keys you don't want to include
				unset($meta_array['_billing_address_index']);
				unset($meta_array['_shipping_address_index']);
				unset($meta_array['is_vat_exempt']);
				// Add the filtered custom order data to the meta array
				if (!empty($this->custom_order_data)) {
					foreach ($this->custom_order_data as $key => $value) {
						$meta_array[$key] = $value;
					}
				}
				$order_status = $order->get_status();
				$order_meta = get_post_meta($order_id);
				$payment_method = isset($order_meta['_payment_method_title'][0]) ? $order_meta['_payment_method_title'][0] : '';
				$user_id = isset($order_meta['_customer_user'][0]) ? $order_meta['_customer_user'][0] : '';

				if ($order_status != 'failed') {
					foreach ($order->get_items() as $item_id => $item) {
						$post_id = MP_Global_Function::get_order_item_meta($item_id, '_mptbm_id');
						if (get_post_type($post_id) == MPTBM_Function::get_cpt()) {
							$date = MP_Global_Function::get_order_item_meta($item_id, '_mptbm_date');
							$date = $date ? MP_Global_Function::data_sanitize($date) : '';
							$start_place = MP_Global_Function::get_order_item_meta($item_id, '_mptbm_start_place');
							$start_place = $start_place ? MP_Global_Function::data_sanitize($start_place) : '';
							$end_place = MP_Global_Function::get_order_item_meta($item_id, '_mptbm_end_place');
							$end_place = $end_place ? MP_Global_Function::data_sanitize($end_place) : '';
							$waiting_time = MP_Global_Function::get_order_item_meta($item_id, '_mptbm_waiting_time');
							$waiting_time = $waiting_time ? MP_Global_Function::data_sanitize($waiting_time) : '';
							$return = MP_Global_Function::get_order_item_meta($item_id, '_mptbm_taxi_return');
							$return = $return ? MP_Global_Function::data_sanitize($return) : '';
							if ($return > 1 && MP_Global_Function::get_settings('mptbm_general_settings', 'enable_return_in_different_date') == 'yes') {
								$return_target_date = MP_Global_Function::get_order_item_meta($item_id, '_mptbm_return_date');
								$return_target_time = MP_Global_Function::get_order_item_meta($item_id, '_mptbm_return_time');
								$data['mptbm_return_target_date'] = $return_target_date;
								$data['mptbm_return_target_time'] = $return_target_time;
							}
							$fixed_time = MP_Global_Function::get_order_item_meta($item_id, '_mptbm_fixed_hours');
							$fixed_time = $fixed_time ? MP_Global_Function::data_sanitize($fixed_time) : '';
							$distance = MP_Global_Function::get_order_item_meta($item_id, '_mptbm_distance');
							$distance = $distance ? MP_Global_Function::data_sanitize($distance) : '';
							$duration = MP_Global_Function::get_order_item_meta($item_id, '_mptbm_duration');
							$duration = $duration ? MP_Global_Function::data_sanitize($duration) : '';
							$base_price = MP_Global_Function::get_order_item_meta($item_id, '_mptbm_base_price');
							$base_price = $base_price ? MP_Global_Function::data_sanitize($base_price) : '';
							$service = MP_Global_Function::get_order_item_meta($item_id, '_mptbm_service_info');
							$service_info = $service ? MP_Global_Function::data_sanitize($service) : [];
							$price = MP_Global_Function::get_order_item_meta($item_id, '_mptbm_tp');
							$price = $price ? MP_Global_Function::data_sanitize($price) : [];
							$transport_quantity = MP_Global_Function::get_order_item_meta($item_id, '_mptbm_transport_quantity');
							$quantity = $transport_quantity ? MP_Global_Function::data_sanitize($transport_quantity) : 1;
							
							// Add meta array data to the $data array
							$data = array_merge($meta_array, [
								'mptbm_id' => $post_id,
								'mptbm_date' => $date,
								'mptbm_start_place' => $start_place,
								'mptbm_end_place' => $end_place,
								'mptbm_waiting_time' => $waiting_time,
								'mptbm_taxi_return' => $return,
								'mptbm_fixed_hours' => $fixed_time,
								'mptbm_distance' => $distance,
								'mptbm_duration' => $duration,
								'mptbm_base_price' => $base_price,
								'mptbm_order_id' => $order_id,
								'mptbm_order_status' => $order_status,
								'mptbm_payment_method' => $order->get_payment_method_title(),
								'mptbm_user_id' => $user_id,
								'mptbm_tp' => $price,
								'mptbm_service_info' => $service_info,
								'mptbm_billing_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
								'mptbm_billing_email' => $order->get_billing_email(),
								'mptbm_billing_phone' => $order->get_billing_phone(),
								'mptbm_target_pickup_interval_time' => MPTBM_Function::get_general_settings('mptbm_pickup_interval_time', '30'),
								'mptbm_transport_quantity' => $quantity
							]);

							// Only add passenger count if the setting is enabled
							$show_passengers = MP_Global_Function::get_settings('mptbm_general_settings', 'show_number_of_passengers', 'no');
							if ($show_passengers === 'yes') {
								$data['mptbm_passengers'] = MP_Global_Function::get_order_item_meta($item_id, '_mptbm_passengers') ?? 1;
							}

							$booking_data = apply_filters('add_mptbm_booking_data', $data, $post_id);
							self::add_cpt_data('mptbm_booking', $booking_data['mptbm_billing_name'], $booking_data);

							if (sizeof($service_info) > 0) {
								foreach ($service_info as $service) {
									$ex_data = [
										'mptbm_id' => $post_id,
										'mptbm_date' => $date,
										'mptbm_order_id' => $order_id,
										'mptbm_order_status' => $order_status,
										'mptbm_service_name' => $service['service_name'],
										'mptbm_service_quantity' => $service['service_quantity'],
										'mptbm_service_price' => $service['service_price'],
										'mptbm_payment_method' => $payment_method,
										'mptbm_user_id' => $user_id
									];
									self::add_cpt_data('mptbm_service_booking', '#' . $order_id . $ex_data['mptbm_service_name'], $ex_data);
								}
							}
						}
					}
				}
				$data['mptbm_item_name'] = $this->ordered_item_name;

				$driver_id = get_post_meta($post_id, 'mptbm_selected_driver', true);
				if ($driver_id) {
					$driver_info = get_userdata($driver_id);
					$data['mptbm_item_driver_name'] = $driver_info->display_name;
					$data['mptbm_item_driver_email'] = $driver_info->user_email;
					$data['mptbm_item_driver_phone'] = get_user_meta($driver_id, 'user_phone', true);
				}
				do_action('mptbm_checkout_order_processed', $data);
			}
		}
		public function order_status_changed($order_id)
		{
			$order = wc_get_order($order_id);
			$order_status = $order->get_status();
			foreach ($order->get_items() as $item_id => $item_values) {
				$post_id = MP_Global_Function::get_order_item_meta($item_id, '_mptbm_id');
				if (get_post_type($post_id) == MPTBM_Function::get_cpt()) {
					if ($order->has_status('processing') || $order->has_status('pending') || $order->has_status('on-hold') || $order->has_status('completed') || $order->has_status('cancelled') || $order->has_status('refunded') || $order->has_status('failed') || $order->has_status('requested')) {
						$this->wc_order_status_change($order_status, $post_id, $order_id);
					}
				}
			}
		}
		//**************************//
		public function show_cart_item($cart_item, $post_id)
		{
			$date = array_key_exists('mptbm_date', $cart_item) ? $cart_item['mptbm_date'] : '';

			$start_location = array_key_exists('mptbm_start_place', $cart_item) ? $cart_item['mptbm_start_place'] : '';
			$end_location = array_key_exists('mptbm_end_place', $cart_item) ? $cart_item['mptbm_end_place'] : '';
			$base_price = array_key_exists('mptbm_base_price', $cart_item) ? $cart_item['mptbm_base_price'] : '';
			$return = array_key_exists('mptbm_taxi_return', $cart_item) ? $cart_item['mptbm_taxi_return'] : '';
			$waiting_time = array_key_exists('mptbm_waiting_time', $cart_item) ? $cart_item['mptbm_waiting_time'] : '';
			$fixed_time = array_key_exists('mptbm_fixed_hours', $cart_item) ? $cart_item['mptbm_fixed_hours'] : '';
			$extra_service = array_key_exists('mptbm_extra_service_info', $cart_item) ? $cart_item['mptbm_extra_service_info'] : [];
			$original_price_based = isset($cart_item['original_price_based']) ? $cart_item['original_price_based'] : '';
			$disable_dropoff_hourly = MP_Global_Function::get_settings('mptbm_general_settings', 'disable_dropoff_hourly', 'enable');
?>
			<div class="mpStyle">
				<?php do_action('mptbm_before_cart_item_display', $cart_item, $post_id); ?>
				<div class="dLayout_xs">
					<ul class="cart_list">
						<li>
							<span class="fas fa-map-marker-alt"></span>
							<h6 class="_mR_xs"><?php esc_html_e('Pickup Location', 'ecab-taxi-booking-manager'); ?> :</h6>
							<span><?php echo esc_html($start_location); ?></span>
						</li>
						<?php if (!($original_price_based === 'fixed_hourly' && $disable_dropoff_hourly === 'disable')): ?>
						<li>
							<span class="fas fa-map-marker-alt"></span>
							<h6 class="_mR_xs"><?php echo mptbm_get_translation('dropoff_location_label', __('Drop-Off Location', 'ecab-taxi-booking-manager')); ?> :</h6>
							<span><?php echo esc_html($end_location); ?></span>
						</li>
						<?php endif; ?>
						<?php
						if ($original_price_based !== 'manual') {
						?>
							<li>
								<span class="fas fa-route"></span>
								<h6 class="_mR_xs"><?php esc_html_e('Approximate Distance', 'ecab-taxi-booking-manager'); ?> :</h6>
								<span><?php echo esc_html($cart_item['mptbm_distance_text']); ?></span>
							</li>
							<li>
								<span class="far fa-clock"></span>
								<h6 class="_mR_xs"><?php esc_html_e('Approximate Time', 'ecab-taxi-booking-manager'); ?> :</h6>
								<span><?php echo esc_html($cart_item['mptbm_duration_text']); ?></span>
							</li>
						<?php } ?>
						<li>
							<span class="far fa-calendar-alt"></span>
							<h6 class="_mR_xs"><?php esc_html_e('Date', 'ecab-taxi-booking-manager'); ?> :</h6>
							<span><?php echo esc_html(MP_Global_Function::date_format($date)); ?></span>
						</li>
						<li>
							<span class="far fa-clock"></span>
							<h6 class="_mR_xs"><?php esc_html_e('Time : ', 'ecab-taxi-booking-manager'); ?></h6>
							<span><?php echo esc_html(MP_Global_Function::date_format($date, 'time')); ?></span>
						</li>
						<?php if ($return && $return > 1) { ?>
							<li>
								<h6 class="_mR_xs"><?php esc_html_e('Transfer Type', 'ecab-taxi-booking-manager'); ?> :</h6>
								<span><?php esc_html_e('Return', 'ecab-taxi-booking-manager'); ?></span>
							</li>

							<?php if (MP_Global_Function::get_settings('mptbm_general_settings', 'enable_return_in_different_date') == 'yes') {

								$return_date = array_key_exists('mptbm_return_target_date', $cart_item) ? $cart_item['mptbm_return_target_date'] : '';
								$return_time = array_key_exists('mptbm_return_target_time', $cart_item) ? $cart_item['mptbm_return_target_time'] : '';
								if ($return_time !== "") {
									if ($return_time !== "0") {
										// Convert return time to hours and minutes
										$time_parts = explode('.', $return_time);
										$hours = isset($time_parts[0]) ? $time_parts[0] : 0;
										$decimal_part = isset($time_parts[1]) ? $time_parts[1] : 0;
										$interval_time = MPTBM_Function::get_general_settings('mptbm_pickup_interval_time');

										if ($interval_time == "5" || $interval_time == "15") {
											if ($decimal_part != 3) {
												$minutes = isset($decimal_part) ? (int) $decimal_part * 1 : 0; // Multiply by 1 to convert to minutes
											} else {
												$minutes = isset($decimal_part) ? (int) $decimal_part * 10 : 0; // Multiply by 10 to convert to minutes
											}
										} else {
											$minutes = isset($decimal_part) ? (int) $decimal_part * 1 : 0; // Multiply by 1 to convert to minutes
										}
									} else {
										$hours = 0;
										$minutes = 0;
									}
								} else {
									$hours = 0;
									$minutes = 0;
								}

								// Format hours and minutes
								$return_time_formatted = sprintf('%02d:%02d', $hours, $minutes);

								// Combine date and time if both are available
								$return_date_time = $return_date ? gmdate("Y-m-d", strtotime($return_date)) : "";
								if ($return_date_time && $return_time !== "") {
									$return_date_time .= " " . $return_time_formatted;
								}



							?>
								<li>
									<span class="far fa-calendar-alt"></span>
									<h6 class="_mR_xs"><?php esc_html_e('Return Date', 'ecab-taxi-booking-manager'); ?> :</h6>
									<span><?php echo esc_html(MP_Global_Function::date_format($return_date_time)); ?></span>
								</li>
								<li>
									<span class="far fa-clock"></span>
									<h6 class="_mR_xs"><?php esc_html_e('Return Time', 'ecab-taxi-booking-manager'); ?> :</h6>
									<span><?php echo esc_html(MP_Global_Function::date_format($return_date_time, 'time')); ?></span>
								</li>
							<?php } ?>
						<?php } ?>
						<?php if ($waiting_time && $waiting_time > 0) { ?>
							<li>
								<h6 class="_mR_xs"><?php echo mptbm_get_translation('extra_waiting_hours_label', __('Extra Waiting Hours', 'ecab-taxi-booking-manager')); ?> :</h6>
								<span><?php echo esc_html($waiting_time); ?><?php echo mptbm_get_translation('hours_in_waiting_label', __('Hours', 'ecab-taxi-booking-manager')); ?></span>
							</li>
						<?php } ?>
						<?php if ($fixed_time && $fixed_time > 0) { ?>
							<li>
								<h6 class="_mR_xs"><?php echo mptbm_get_translation('service_times_label', __('Service Times', 'ecab-taxi-booking-manager')); ?> :</h6>
								<span><?php echo esc_html($fixed_time); ?><?php echo mptbm_get_translation('hours_in_waiting_label', __('Hours', 'ecab-taxi-booking-manager')); ?></span>
							</li>
						<?php } ?>
						<?php 
						$show_passengers = MP_Global_Function::get_settings('mptbm_general_settings', 'show_number_of_passengers', 'no');
						if ($show_passengers === 'yes') { 
						?>
						<li>
							<span class="fas fa-users"></span>
							<h6 class="_mR_xs"><?php esc_html_e('Number of Passengers', 'ecab-taxi-booking-manager'); ?> :</h6>
							<span><?php echo esc_html($cart_item['mptbm_passengers']); ?></span>
						</li>
						<?php } ?>
						<li>
							<span class="fa fa-tag"></span>
							<h6 class="_mR_xs"><?php esc_html_e('Base Price : ', 'ecab-taxi-booking-manager'); ?></h6>
							<?php 
							$price_display_type = MP_Global_Function::get_post_info($post_id, 'mptbm_price_display_type');
							if ($price_display_type === 'custom_message') {
								$custom_message = MP_Global_Function::get_post_info($post_id, 'mptbm_custom_price_message');
								echo esc_html($custom_message);
							} else {
								echo wp_kses_post(wc_price($base_price));
							}
							?>
						</li>
						<?php
						// Display transport quantity
						$transport_quantity = isset($cart_item['mptbm_transport_quantity']) ? absint($cart_item['mptbm_transport_quantity']) : 1;
						?>
						<li>
							<span class="fas fa-car"></span>
							<h6 class="_mR_xs"><?php esc_html_e('Transport Quantity', 'ecab-taxi-booking-manager'); ?> :</h6>
							<span><?php echo esc_html($transport_quantity); ?></span>
						</li>
						<?php do_action('mptbm_cart_item_display', $cart_item, $post_id); ?>
					</ul>
				</div>
				<?php if (sizeof($extra_service) > 0) { ?>
					<h5 class="_mB_xs"><?php esc_html_e('Extra Services', 'ecab-taxi-booking-manager'); ?></h5>
					<?php foreach ($extra_service as $service) { ?>
						<div class="dLayout_xs">
							<ul class="cart_list">
								<li>
									<h6 class="_mR_xs"><?php esc_html_e('Name : ', 'ecab-taxi-booking-manager'); ?></h6>
									<span><?php echo esc_html($service['service_name']); ?></span>
								</li>
								<li>
									<h6 class="_mR_xs"><?php esc_html_e('Quantity : ', 'ecab-taxi-booking-manager'); ?></h6>
									<span><?php echo esc_html($service['service_quantity']); ?></span>
								</li>
								<li>
									<h6 class="_mR_xs"><?php esc_html_e('Price : ', 'ecab-taxi-booking-manager'); ?></h6>
									<span><?php echo esc_html(' ( ') . wp_kses_post(wc_price($service['service_price'])) . esc_html(' X ') . esc_html($service['service_quantity']) . esc_html(' ) =') . wp_kses_post(wc_price($service['service_price'] * $service['service_quantity'])); ?></span>
								</li>
							</ul>
						</div>
					<?php } ?>
				<?php } ?>
				<?php do_action('mptbm_after_cart_item_display', $cart_item, $post_id); ?>
			</div>
			<?php
		}
		public function wc_order_status_change($order_status, $post_id, $order_id)
		{
			$args = array(
				'post_type' => 'mptbm_booking',
				'posts_per_page' => -1,
				'meta_query' => array(
					'relation' => 'AND',
					array(
						array(
							'key' => 'mptbm_id',
							'value' => $post_id,
							'compare' => '='
						),
						array(
							'key' => 'mptbm_order_id',
							'value' => $order_id,
							'compare' => '='
						)
					)
				)
			);
			$loop = new WP_Query($args);
			foreach ($loop->posts as $user) {
				$user_id = $user->ID;
				update_post_meta($user_id, 'mptbm_order_status', $order_status);
			}
			$args = array(
				'post_type' => 'mptbm_service_booking',
				'posts_per_page' => -1,
				'meta_query' => array(
					'relation' => 'AND',
					array(
						array(
							'key' => 'mptbm_id',
							'value' => $post_id,
							'compare' => '='
						),
						array(
							'key' => 'mptbm_order_id',
							'value' => $order_id,
							'compare' => '='
						)
					)
				)
			);
			$loop = new WP_Query($args);
			foreach ($loop->posts as $user) {
				$user_id = $user->ID;
				update_post_meta($user_id, 'mptbm_order_status', $order_status);
			}
		}
		//**********************//
		public static function cart_extra_service_info($post_id): array
		{
			$start_date = isset($_POST['mptbm_date']) ? sanitize_text_field($_POST['mptbm_date']) : '';
			$service_name = isset($_POST['mptbm_extra_service']) ? array_map('sanitize_text_field', $_POST['mptbm_extra_service']) : [];
			$service_quantity = isset($_POST['mptbm_extra_service_qty']) ? array_map('sanitize_text_field', $_POST['mptbm_extra_service_qty']) : [];
			$extra_service = array();
			if (sizeof($service_name) > 0) {
				for ($i = 0; $i < count($service_name); $i++) {
					if ($service_name[$i] && $service_quantity[$i] > 0) {
						$price = MPTBM_Function::get_extra_service_price_by_name($post_id, $service_name[$i]);
						$wc_price = MP_Global_Function::wc_price($post_id, $price);
						$raw_price = MP_Global_Function::price_convert_raw($wc_price);
						$extra_service[$i]['service_name'] = $service_name[$i];
						$extra_service[$i]['service_quantity'] = $service_quantity[$i];
						$extra_service[$i]['service_price'] = $raw_price;
						$extra_service[$i]['mptbm_date'] = $start_date ?? '';
					}
				}
			}
			return $extra_service;
		}
		public function get_cart_total_price($post_id)
		{
			$distance = isset($_COOKIE['mptbm_distance']) ? absint($_COOKIE['mptbm_distance']) : '';
			$duration = isset($_COOKIE['mptbm_duration']) ? absint($_COOKIE['mptbm_duration']) : '';
			$start_place = isset($_POST['mptbm_start_place']) ? sanitize_text_field($_POST['mptbm_start_place']) : '';
			$end_place = isset($_POST['mptbm_end_place']) ? sanitize_text_field($_POST['mptbm_end_place']) : '';
			$waiting_time = isset($_POST['mptbm_waiting_time']) ? sanitize_text_field($_POST['mptbm_waiting_time']) : 0;
			$return = isset($_POST['mptbm_taxi_return']) ? sanitize_text_field($_POST['mptbm_taxi_return']) : 1;
			$fixed_hour = isset($_POST['mptbm_fixed_hours']) ? sanitize_text_field($_POST['mptbm_fixed_hours']) : 0;
			$price = MPTBM_Function::get_price($post_id, $distance, $duration, $start_place, $end_place, $waiting_time, $return, $fixed_hour);
			$wc_price = MP_Global_Function::wc_price($post_id, $price);
			$raw_price = MP_Global_Function::price_convert_raw($wc_price);
			$service_name = isset($_POST['mptbm_extra_service']) ? array_map('sanitize_text_field', $_POST['mptbm_extra_service']) : [];
			$service_quantity = isset($_POST['mptbm_extra_service_qty']) ? array_map('absint', $_POST['mptbm_extra_service_qty']) : [];
			if (sizeof($service_name) > 0) {
				for ($i = 0; $i < count($service_name); $i++) {
					if ($service_name[$i]) {
						if (array_key_exists($i, $service_quantity) && isset($service_quantity[$i])) {
							$raw_price = $raw_price + MPTBM_Function::get_extra_service_price_by_name($post_id, $service_name[$i]) * $service_quantity[$i];
						} else {
							$raw_price = $raw_price + MPTBM_Function::get_extra_service_price_by_name($post_id, $service_name[$i]);
						}
					}
				}
			}
			$wc_price = MP_Global_Function::wc_price($post_id, $raw_price);
			return MP_Global_Function::price_convert_raw($wc_price);
		}
		public static function add_cpt_data($cpt_name, $title, $meta_data = array(), $status = 'publish', $cat = array())
		{
			$new_post = array(
				'post_title' => $title,
				'post_content' => '',
				'post_category' => $cat,
				'tags_input' => array(),
				'post_status' => $status,
				'post_type' => $cpt_name
			);

			$post_id = wp_insert_post($new_post);
			if (sizeof($meta_data) > 0) {
				foreach ($meta_data as $key => $value) {
					update_post_meta($post_id, $key, $value);
				}
			}
			if ($cpt_name == 'mptbm_booking') {
				$mptbm_pin = $meta_data['mptbm_user_id'] . $meta_data['mptbm_order_id'] . $meta_data['mptbm_id'] . $post_id;
				update_post_meta($post_id, 'mptbm_pin', $mptbm_pin);
			}
		}
		/****************************/
		public function mptbm_add_to_cart()
			{
				
				$quantity = isset($_POST['transport_quantity']) ? sanitize_text_field($_POST['transport_quantity']) : 1;
				$link_id = absint($_POST['link_id']);
				$product_id = apply_filters('woocommerce_add_to_cart_product_id', $link_id);
				$passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity);
				$product_status = get_post_status($product_id);
				$mptbm_original_price_base = isset($_POST['mptbm_original_price_base']) ? sanitize_text_field($_POST['mptbm_original_price_base']) : '';
				// Remove all previous items from cart
				WC()->cart->empty_cart();

				ob_start();
				if ($passed_validation && WC()->cart->add_to_cart($product_id, $quantity) && 'publish' === $product_status) {
					$checkout_system = MP_Global_Function::get_settings('mptbm_general_settings', 'single_page_checkout', 'yes');
					if ($checkout_system == 'yes') {
						echo wc_get_checkout_url();
					} else {
			?>
						<div class="dLayout woocommerce-page">
							<?php echo do_shortcode('[woocommerce_checkout]'); ?>
						</div>
			<?php
					}
				}
				echo ob_get_clean();
				die();
			}

		/**
		 * Override order-item quantity HTML with transport quantity meta.
		 *
		 * @param string       $quantity_html Original quantity HTML.
		 * @param WC_Order_Item $item Order item instance.
		 * @return string Modified quantity HTML.
		 */
		public function filter_order_item_quantity($quantity_html, $item) {
			$transport_quantity = $item->get_meta('mptbm_transport_quantity', true);
			if ($transport_quantity && absint($transport_quantity) > 0) {
				return ' <strong class="product-quantity">&times;&nbsp;' . absint($transport_quantity) . '</strong>';
			}
			return $quantity_html;
		}

		public function render_custom_checkout_fields($checkout) {
			// Load fields from both admin settings and pro addon
			$settings = get_option('mptbm_checkout_fields_settings', array());
			$fields_admin = isset($settings['fields']) ? $settings['fields'] : array();

			// Pro addon fields
			$fields_pro = array();
			$pro_options = get_option('mptbm_custom_checkout_fields', array());
			if (is_array($pro_options)) {
				foreach ($pro_options as $section => $section_fields) {
					if (is_array($section_fields)) {
						foreach ($section_fields as $key => $field) {
							// Only include if not deleted and not disabled
							if ((empty($field['deleted']) || $field['deleted'] !== 'deleted') && (empty($field['disabled']) || $field['disabled'] !== '1')) {
								$fields_pro[$key] = $field;
							}
						}
					}
				}
			}

			// Merge fields, pro fields override admin if same key
			$fields = array_merge($fields_admin, $fields_pro);

			// Always sort fields by 'order' before rendering (if available)
			uasort($fields, function($a, $b) {
				return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
			});

			$logic = array();
			foreach ($fields as $key => $field) {
				if (!empty($field['conditional']['field'])) {
					$logic[$key] = $field['conditional'];
				}
			}
			// Output logic as JS variable
			echo '<script>window.mptbmFieldLogic = ' . json_encode($logic) . ';</script>';
			foreach ($fields as $key => $field) {
				if (empty($field['show'])) continue;
				$label = $field['label'];
				$placeholder = $field['placeholder'] ?? '';
				$type = $field['type'];
				$value = $checkout->get_value($key);
				$required = !empty($field['required']);
				$field_id = 'mptbm_field_' . esc_attr($key);
				echo '<p class="form-row form-row-wide mptbm-checkout-field" id="row_' . esc_attr($key) . '" data-field="' . esc_attr($key) . '">';
				echo '<label for="' . esc_attr($field_id) . '">' . esc_html($label);
				if ($required) echo ' <span class="required">*</span>';
				echo '</label>';
				if (in_array($type, array('text','number','email','date','datetime-local'))) {
					echo '<input type="' . esc_attr($type) . '" class="input-text" name="' . esc_attr($key) . '" id="' . esc_attr($field_id) . '" placeholder="' . esc_attr($placeholder) . '" value="' . esc_attr($value) . '" ' . ($required ? 'required' : '') . ' />';
				} elseif ($type === 'textarea') {
					echo '<textarea name="' . esc_attr($key) . '" id="' . esc_attr($field_id) . '" class="input-text" placeholder="' . esc_attr($placeholder) . '" ' . ($required ? 'required' : '') . '>' . esc_textarea($value) . '</textarea>';
				} elseif ($type === 'select') {
					$options = isset($field['options']) ? (is_array($field['options']) ? $field['options'] : array_map('trim', explode(',', $field['options']))) : array();
					echo '<select name="' . esc_attr($key) . '" id="' . esc_attr($field_id) . '" class="input-select" ' . ($required ? 'required' : '') . '>';
					echo '<option value="">' . esc_html__('Select', 'ecab-taxi-booking-manager') . '</option>';
					foreach ($options as $opt_key => $opt_val) {
						$opt = is_array($field['options']) ? $opt_key : $opt_val;
						$opt_label = is_array($field['options']) ? $opt_val : $opt_val;
						echo '<option value="' . esc_attr($opt) . '"' . selected($value, $opt, false) . '>' . esc_html($opt_label) . '</option>';
					}
					echo '</select>';
				} elseif ($type === 'checkbox') {
					$options = isset($field['options']) ? (is_array($field['options']) ? $field['options'] : array_map('trim', explode(',', $field['options']))) : array();
					foreach ($options as $opt_key => $opt_val) {
						$opt = is_array($field['options']) ? $opt_key : $opt_val;
						$opt_label = is_array($field['options']) ? $opt_val : $opt_val;
						echo '<label><input type="checkbox" name="' . esc_attr($key) . '[]" value="' . esc_attr($opt) . '"' . (is_array($value) && in_array($opt, $value) ? ' checked' : '') . '> ' . esc_html($opt_label) . '</label> ';
					}
				} elseif ($type === 'radio') {
					$options = isset($field['options']) ? (is_array($field['options']) ? $field['options'] : array_map('trim', explode(',', $field['options']))) : array();
					foreach ($options as $opt_key => $opt_val) {
						$opt = is_array($field['options']) ? $opt_key : $opt_val;
						$opt_label = is_array($field['options']) ? $opt_val : $opt_val;
						echo '<label><input type="radio" name="' . esc_attr($key) . '" value="' . esc_attr($opt) . '"' . ($value == $opt ? ' checked' : '') . '> ' . esc_html($opt_label) . '</label> ';
					}
				} elseif ($type === 'file') {
					$upload_nonce = wp_create_nonce('mptbm_file_upload');
					echo '<input type="file" class="input-file mptbm-file-upload" data-field="' . esc_attr($key) . '" data-nonce="' . esc_attr($upload_nonce) . '" accept="image/*,application/pdf,.doc,.docx,.jpg,.jpeg,.png">';
					echo '<input type="hidden" name="' . esc_attr($key) . '" id="' . esc_attr($field_id) . '_hidden" value="' . esc_attr($value) . '">';
					echo '<span class="mptbm-file-upload-status"></span>';
				}
				echo '</p>';
			}
			// Enqueue JS for AJAX upload and conditional logic
			add_action('wp_footer', array($this, 'enqueue_checkout_fields_js'));
		}

		public function validate_custom_checkout_fields() {
			$settings = get_option('mptbm_checkout_fields_settings', array());
			$fields = isset($settings['fields']) ? $settings['fields'] : array();
			foreach ($fields as $key => $field) {
				if (empty($field['show'])) continue;
				$required = !empty($field['required']);
				$label = $field['label'];
				$type = $field['type'];
				if ($required) {
					if ($type === 'checkbox') {
						if (empty($_POST[$key]) || !is_array($_POST[$key])) {
							wc_add_notice(sprintf(__('%s is a required field.', 'ecab-taxi-booking-manager'), $label), 'error');
						}
					} else {
						if (empty($_POST[$key])) {
							wc_add_notice(sprintf(__('%s is a required field.', 'ecab-taxi-booking-manager'), $label), 'error');
						}
					}
				}
			}
		}

		public function save_custom_checkout_fields($order_id) {
			$settings = get_option('mptbm_checkout_fields_settings', array());
			$fields = isset($settings['fields']) ? $settings['fields'] : array();
			foreach ($fields as $key => $field) {
				if (empty($field['show'])) continue;
				$type = $field['type'];
				if ($type === 'checkbox') {
					$value = isset($_POST[$key]) ? array_map('sanitize_text_field', (array)$_POST[$key]) : array();
					update_post_meta($order_id, $key, $value);
				} else {
					$value = isset($_POST[$key]) ? sanitize_text_field($_POST[$key]) : '';
					update_post_meta($order_id, $key, $value);
				}
			}
		}

		public function display_custom_fields_in_admin_order($order) {
			$settings = get_option('mptbm_checkout_fields_settings', array());
			$fields = isset($settings['fields']) ? $settings['fields'] : array();
			if (!$fields) return;
			echo '<div class="mptbm-custom-fields"><h3>' . esc_html__('Custom Booking Details', 'ecab-taxi-booking-manager') . '</h3><table>';
			foreach ($fields as $key => $field) {
				if (empty($field['show'])) continue;
				$label = (string)($field['label'] ?? '');
				$type = $field['type'];
				$value = get_post_meta($order->get_id(), $key, true);
				if ($value !== '' && $value !== null) {
					if ($type === 'file') {
						$file_url = esc_url((string)$value);
						$file_name = basename((string)$file_url);
						echo '<tr><th style="text-align:left;">' . esc_html($label) . ':</th><td><a href="' . $file_url . '" target="_blank" rel="noopener">' . esc_html($file_name) . '</a></td></tr>';
					} else if (is_array($value)) {
						$value = implode(', ', array_map(function($v){ return esc_html((string)$v); }, $value));
						echo '<tr><th style="text-align:left;">' . esc_html($label) . ':</th><td>' . $value . '</td></tr>';
					} else {
						$value = esc_html((string)$value);
						echo '<tr><th style="text-align:left;">' . esc_html($label) . ':</th><td>' . $value . '</td></tr>';
					}
				}
			}
			echo '</table></div>';
		}

		public function add_custom_fields_to_email($fields, $sent_to_admin, $order) {
			$settings = get_option('mptbm_checkout_fields_settings', array());
			$all_fields = isset($settings['fields']) ? $settings['fields'] : array();
			$custom_fields = array();
			foreach ($all_fields as $key => $field) {
				if (empty($field['show'])) continue;
				$label = (string)($field['label'] ?? '');
				$type = $field['type'];
				$value = get_post_meta($order->get_id(), $key, true);
				if ($value !== '' && $value !== null) {
					if ($type === 'file') {
						$file_url = esc_url((string)$value);
						$file_name = basename((string)$file_url);
						$custom_fields[$key] = array('label' => $label, 'value' => '<a href="' . $file_url . '" target="_blank" rel="noopener">' . esc_html($file_name) . '</a>');
					} else if (is_array($value)) {
						$value = implode(', ', array_map(function($v){ return esc_html((string)$v); }, $value));
						$custom_fields[$key] = array('label' => $label, 'value' => $value);
					} else {
						$value = esc_html((string)$value);
						$custom_fields[$key] = array('label' => $label, 'value' => $value);
					}
				}
			}
			return array_merge($fields, $custom_fields);
		}

		public function enqueue_checkout_fields_js() {
			?>
			<script>
			jQuery(function($){
				// Conditional logic
				function checkFieldLogic() {
					var logic = window.mptbmFieldLogic || {};
					$('.mptbm-checkout-field').each(function(){
						var field = $(this).data('field');
						if (logic[field] && logic[field].field) {
							var parentVal = $('[name="'+logic[field].field+'"]').val();
							var show = true;
							switch (logic[field].operator) {
								case 'equals': show = (parentVal == logic[field].value); break;
								case 'not_equals': show = (parentVal != logic[field].value); break;
								case 'empty': show = (!parentVal); break;
								case 'not_empty': show = (!!parentVal); break;
							}
							if (show) { $(this).show().find(':input').prop('disabled', false); }
							else { $(this).hide().find(':input').prop('disabled', true); }
						}
					});
				}
				$(document).on('change', '.mptbm-checkout-field :input', checkFieldLogic);
				checkFieldLogic();

				// File upload
				$(document).on('change', '.mptbm-file-upload', function(){
					var fileInput = this;
					var field = $(this).data('field');
					var nonce = $(this).data('nonce');
					var formData = new FormData();
					formData.append('action', 'mptbm_file_upload');
					formData.append('file', fileInput.files[0]);
					formData.append('field', field);
					formData.append('nonce', nonce);
					var status = $(this).siblings('.mptbm-file-upload-status');
					status.text('Uploading...');
					$.ajax({
						url: '<?php echo admin_url('admin-ajax.php'); ?>',
						type: 'POST',
						data: formData,
						contentType: false,
						processData: false,
						success: function(resp){
							if(resp.success && resp.data.url){
								$('#mptbm_field_'+field+'_hidden').val(resp.data.url);
								status.html('<a href="'+resp.data.url+'" target="_blank">'+resp.data.name+'</a>');
							}else{
								status.text(resp.data && resp.data.message ? resp.data.message : 'Upload failed');
							}
						},
						error: function(){ status.text('Upload error'); }
					});
				});
			});
			</script>
			<?php
		}

		public function ajax_file_upload() {
			check_ajax_referer('mptbm_file_upload', 'nonce');
			if (empty($_FILES['file'])) {
				wp_send_json_error(array('message' => __('No file uploaded.', 'ecab-taxi-booking-manager')));
			}
			$file = $_FILES['file'];
			$allowed_types = array('image/jpeg','image/png','image/gif','application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document');
			$max_size = 5 * 1024 * 1024; // 5MB
			if (!in_array($file['type'], $allowed_types)) {
				wp_send_json_error(array('message' => __('Invalid file type.', 'ecab-taxi-booking-manager')));
			}
			if ($file['size'] > $max_size) {
				wp_send_json_error(array('message' => __('File too large (max 5MB).', 'ecab-taxi-booking-manager')));
			}
			$upload = wp_handle_upload($file, array('test_form' => false));
			if (isset($upload['error'])) {
				wp_send_json_error(array('message' => $upload['error']));
			}
			wp_send_json_success(array('url' => $upload['url'], 'name' => basename($upload['file'])));
		}
	}
	new MPTBM_Woocommerce();
}
