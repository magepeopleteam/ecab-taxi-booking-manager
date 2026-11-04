<?php
/*
 * Bookings for the mobile app: the checkout form the site is configured with, a vehicle's extras,
 * creating a booking from a stored quote, the customer's own bookings, and the hand-off to a payment page.
 *
 * A booking is a normal WooCommerce order created by the website's own cart and checkout code
 * (MPTBM_Woocommerce hooks), so fares, order meta, the taxi booking record, e-mails and addons behave
 * exactly as for a booking made on the website. The app never sends a price: the fare is calculated
 * again on the server from the stored quote, and the booking is refused if it no longer matches.
 */
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_App_Booking')) {
	class MPTBM_App_Booking {
		const PAY_TOKEN_TTL = 10 * MINUTE_IN_SECONDS;
		const RETURN_META = '_mptbm_app_return';
		const PAY_TOKEN_REUSE = 60; // seconds a spent link still works for the same page load
		/** Gateways that finish on the server with no card form: the order can be placed and the customer is done. */
		const OFFLINE_GATEWAYS = array('cod', 'bacs', 'cheque');

		public static function init(): void {
			// After WooCommerce has registered its order types (it does so on init at priority 5): earlier, wc_get_order() returns false
			// and every payment link was reported as expired the moment it was opened.
			add_action('init', array(__CLASS__, 'pay_redirect'), 20);
			add_action('template_redirect', array(__CLASS__, 'return_page'), 1);
		}

		/* ------------------------------------------------------------------ plumbing */

		public static function available(): ?WP_Error {
			if (!class_exists('WooCommerce') || !class_exists('MPTBM_Booking_Mode') || !MPTBM_Booking_Mode::is_woocommerce()) {
				return new WP_Error('mptbm_app_booking_unsupported', esc_html__('Booking in the app is not available for this service yet.', 'ecab-taxi-booking-manager'), array('status' => 501));
			}
			return null;
		}

		/** Load the WooCommerce cart/session the way a front-end request has them. */
		private static function load_cart(): void {
			if (function_exists('wc_load_cart')) {
				wc_load_cart();
			}
		}

		public static function checkout(): WC_Checkout {
			require_once MPTBM_PLUGIN_DIR . '/inc/MPTBM_App_Checkout.php';
			return new MPTBM_App_Checkout();
		}

		/** The cart session and PHP session cookies are of no use to a stateless API client. */
		private static function drop_cookies(): void {
			if (!headers_sent()) {
				header_remove('Set-Cookie');
			}
		}

		/* ------------------------------------------------------------ checkout fields */

		/** Value stored on the customer for a checkout field, so the form can start filled in. */
		private static function default_value(string $key, int $user_id) {
			$value = $user_id ? (string) get_user_meta($user_id, $key, true) : '';
			return '' === $value && 'billing_country' === $key ? (string) WC()->countries->get_base_country() : $value;
		}

		private static function countries(): array {
			$out = array();
			foreach (WC()->countries->get_allowed_countries() as $code => $name) {
				$out[] = array('value' => $code, 'label' => $name);
			}
			return $out;
		}

		/**
		 * Save the billing address a customer edited in the app, into the same user meta the site's checkout prefills from.
		 * Only the fields the site's checkout asks for are taken (never the account email); a required one cannot be emptied,
		 * the country must be one the shop sells to, and a state must be one of that country's when it has a list.
		 *
		 * @param array<string,mixed> $values billing_* key => value
		 * @return true|WP_Error
		 */
		public static function save_billing(int $user_id, array $values) {
			self::load_cart();
			$fields = (array) (WC()->checkout()->get_checkout_fields()['billing'] ?? array());
			$clean = array();
			foreach ($values as $key => $value) {
				$key = (string) $key;
				if (!isset($fields[$key]) || 'billing_email' === $key || !is_scalar($value)) {
					continue;
				}
				$value = trim(sanitize_text_field((string) $value));
				if ('' === $value && !empty($fields[$key]['required'])) {
					/* translators: %s: field name */
					return new WP_Error('mptbm_app_address', sprintf(__('%s is required.', 'ecab-taxi-booking-manager'), wp_strip_all_tags((string) ($fields[$key]['label'] ?? $key))), array('status' => 400));
				}
				$clean[$key] = $value;
			}
			if (isset($clean['billing_country']) && '' !== $clean['billing_country']) {
				$clean['billing_country'] = strtoupper($clean['billing_country']);
				if (!isset(WC()->countries->get_allowed_countries()[$clean['billing_country']])) {
					return new WP_Error('mptbm_app_address', __('Please choose a country the shop serves.', 'ecab-taxi-booking-manager'), array('status' => 400));
				}
			}
			if (isset($clean['billing_state']) && '' !== $clean['billing_state']) {
				$country = $clean['billing_country'] ?? (string) get_user_meta($user_id, 'billing_country', true);
				$states = $country ? self::states($country) : array();
				if ($states && !in_array($clean['billing_state'], array_column($states, 'value'), true)) {
					return new WP_Error('mptbm_app_address', __('Please choose a valid state or region.', 'ecab-taxi-booking-manager'), array('status' => 400));
				}
			}
			foreach ($clean as $key => $value) {
				update_user_meta($user_id, $key, $value);
			}
			return true;
		}

		/** @return array the fields the site's checkout asks for, and what the customer must accept */
		public static function checkout_form(int $user_id = 0): array {
			self::load_cart();
			$sections = WC()->checkout()->get_checkout_fields();
			$fields = array();
			foreach (array('billing', 'order') as $section) {
				foreach ((array) ($sections[$section] ?? array()) as $key => $field) {
					$type = isset($field['type']) ? (string) $field['type'] : 'text';
					$options = array();
					if ('country' === $type) {
						$options = self::countries();
						$type = 'select';
					} elseif ('state' === $type) {
						$type = 'state'; // a list once the country is chosen (GET /checkout/states), free text if it has none
					} elseif ('select' === $type || 'radio' === $type) {
						foreach ((array) ($field['options'] ?? array()) as $value => $label) {
							$options[] = array('value' => (string) $value, 'label' => wp_strip_all_tags((string) $label));
						}
						$type = 'select';
					}
					$fields[] = array(
						'key' => (string) $key,
						'section' => $section,
						'label' => wp_strip_all_tags((string) ($field['label'] ?? $key)),
						'type' => $type,
						'required' => !empty($field['required']),
						'placeholder' => wp_strip_all_tags((string) ($field['placeholder'] ?? '')),
						'options' => $options,
						'value' => 'order' === $section ? '' : self::default_value((string) $key, $user_id),
						'validate' => array_values((array) ($field['validate'] ?? array())),
					);
				}
			}

			$terms = null;
			if (function_exists('wc_terms_and_conditions_checkbox_enabled') && wc_terms_and_conditions_checkbox_enabled()) {
				$terms = array(
					'required' => true,
					'text' => trim(wp_strip_all_tags(wc_replace_policy_page_link_placeholders(wc_get_terms_and_conditions_checkbox_text()))),
					'url' => wc_terms_and_conditions_page_id() ? (string) get_permalink(wc_terms_and_conditions_page_id()) : '',
				);
			}
			return array('fields' => $fields, 'terms' => $terms);
		}

		/** The states / regions WooCommerce knows for a country; empty when it has none (the field is then free text). */
		public static function states(string $country): array {
			$country = strtoupper(preg_replace('/[^A-Za-z]/', '', $country));
			if (2 !== strlen($country) || !isset(WC()->countries->get_allowed_countries()[$country])) {
				return array();
			}
			$states = array();
			foreach ((array) WC()->countries->get_states($country) as $code => $name) {
				$states[] = array('value' => (string) $code, 'label' => wp_strip_all_tags((string) $name));
			}
			return $states;
		}

		/* -------------------------------------------------------------------- extras */

		public static function extras(int $vehicle_id): array {
			$extras = array();
			foreach (MPTBM_Function::get_available_extra_services($vehicle_id) as $service) {
				$extras[] = array('name' => $service['service_name'], 'price' => (float) $service['service_price']);
			}
			$stoppages = array();
			foreach (MPTBM_Function::get_available_stoppages($vehicle_id) as $stoppage) {
				$stoppages[] = array(
					'id' => $stoppage['id'],
					'name' => wp_strip_all_tags($stoppage['name']),
					'description' => wp_strip_all_tags($stoppage['description']),
					'duration' => wp_strip_all_tags((string) $stoppage['duration']),
					'price' => (float) $stoppage['price'],
				);
			}
			return array('extras' => $extras, 'stoppages' => $stoppages);
		}

		/* ------------------------------------------------------------------- booking */

		private static function error(string $code, string $message, int $status = 400, array $data = array()): WP_Error {
			return new WP_Error($code, $message, array_merge(array('status' => $status), $data));
		}

		/**
		 * @param array $in quote_id, vehicle_id, extras [{name, qty}], stoppages [id], billing {field: value},
		 *                  payment_method, order_notes, accept_terms
		 * @return array|WP_Error the placed booking
		 */
		public static function create(array $in, int $user_id) {
			if ($unsupported = self::available()) {
				return $unsupported;
			}
			$quote = MPTBM_App_Quote::load((string) ($in['quote_id'] ?? ''));
			if (!$quote) {
				return self::error('mptbm_app_quote_expired', esc_html__('Your fare quote expired. Please search for the trip again.', 'ecab-taxi-booking-manager'), 410);
			}
			$vehicle_id = absint($in['vehicle_id'] ?? 0);
			$offered = $quote['vehicles'][$vehicle_id] ?? null;
			if (!$offered || empty($offered['available'])) {
				return self::error('mptbm_app_vehicle_unavailable', esc_html__('This vehicle is not available for the trip. Please choose another.', 'ecab-taxi-booking-manager'), 409);
			}
			if (($offered['price']['display_type'] ?? 'normal') !== 'normal') {
				return self::error('mptbm_app_price_on_request', esc_html__('This vehicle has no fixed price. Please contact us to book it.', 'ecab-taxi-booking-manager'), 409);
			}
			$trip = $quote['trip'];

			// Same trip, same session keys, same search context as when the fare was quoted - in a session of its
			// own, so a simultaneous request from the same phone cannot swap the search for another one.
			MPTBM_App_Quote::isolate_session();
			wp_set_current_user($user_id);
			self::load_cart();
			MPTBM_App_Quote::restore_session((array) $quote['session']);
			MPTBM_Function::set_search_context((array) $quote['context']);

			$extras = array();
			foreach (array_slice((array) ($in['extras'] ?? array()), 0, 50) as $extra) {
				if (is_array($extra) && !empty($extra['name']) && absint($extra['qty'] ?? 0) > 0) {
					$extras[] = array('name' => sanitize_text_field((string) $extra['name']), 'qty' => min(100, absint($extra['qty'])));
				}
			}
			$saved_post = $_POST;
			$_POST = self::posted_fields($in, $trip, $vehicle_id, $extras);
			// The website stores passengers and bags only when a Pro search filter asks for them; the
			// app always collects them and the driver needs them.
			$party = static function (array $data) use ($trip): array {
				$data['mptbm_passengers'] = $data['mptbm_passengers'] ?? (int) $trip['passengers'];
				if (!empty($trip['bags'])) {
					$data['mptbm_bags'] = $data['mptbm_bags'] ?? (int) $trip['bags'];
				}
				return $data;
			};
			add_filter('mptbm_add_cart_item', $party, 99);
			$record_party = static function ($item, $cart_item_key, $values): void {
				if (!empty($values['mptbm_passengers']) && '' === (string) $item->get_meta('_mptbm_passengers')) {
					$item->add_meta_data(mptbm_get_translation('number_of_passengers_label', __('Number of Passengers', 'ecab-taxi-booking-manager')), absint($values['mptbm_passengers']));
					$item->add_meta_data('_mptbm_passengers', absint($values['mptbm_passengers']));
				}
			};
			add_action('woocommerce_checkout_create_order_line_item', $record_party, 100, 3); // after the plugin's own line-item handler

			try {
				WC()->cart->empty_cart();
				wc_clear_notices();
				$product_id = class_exists('MPTBM_Hidden_Product') ? MPTBM_Hidden_Product::get_or_create_wc_product($vehicle_id) : 0;
				// WC_Cart::add_to_cart() does not validate; the website's add-to-cart handler runs this filter first
				// (extras, capacity and the verified quote are checked there), so the app must too.
				$product_id = (int) apply_filters('woocommerce_add_to_cart_product_id', $product_id);
				$passed = $product_id && apply_filters('woocommerce_add_to_cart_validation', true, $product_id, 1);
				$cart_key = $passed ? WC()->cart->add_to_cart($product_id, 1) : false;
				if (!$cart_key) {
					return self::cart_refused();
				}

				// The fare is calculated again by the cart; it must be the fare the customer was shown.
				$line = WC()->cart->get_cart_item($cart_key);
				$quoted = (float) $offered['price']['transport'];
				if (abs((float) ($line['mptbm_base_price'] ?? -1) - $quoted) > 0.01) {
					return self::error('mptbm_app_fare_changed', esc_html__('The fare for this trip has changed. Please search again.', 'ecab-taxi-booking-manager'), 409);
				}

				$order = self::checkout()->place();
				if (is_wp_error($order)) {
					return $order;
				}
				// Remember where the trip runs so My Trips can draw it (the website's order items keep only the names).
				$points = array();
				foreach (array('start', 'end') as $key) {
					if (isset($trip[$key]['lat'], $trip[$key]['lng'])) {
						$points[$key] = array('lat' => (float) $trip[$key]['lat'], 'lng' => (float) $trip[$key]['lng']);
					}
				}
				if ($points) {
					$order->update_meta_data('_mptbm_app_route', $points);
					$order->save();
				}
				return self::finish($order, sanitize_key((string) ($in['payment_method'] ?? '')), $user_id);
			} finally {
				remove_filter('mptbm_add_cart_item', $party, 99);
				remove_action('woocommerce_checkout_create_order_line_item', $record_party, 100);
				$_POST = $saved_post;
				if (function_exists('WC') && WC()->cart) {
					WC()->cart->empty_cart();
				}
				wc_clear_notices();
				MPTBM_App_Quote::end_session();
				self::drop_cookies();
			}
		}

		private static function cart_refused(): WP_Error {
			$message = esc_html__('We could not add this booking. Please search again.', 'ecab-taxi-booking-manager');
			foreach (wc_get_notices('error') as $notice) {
				$text = trim(wp_strip_all_tags((string) ($notice['notice'] ?? '')));
				if ('' !== $text) {
					$message = $text;
					break;
				}
			}
			wc_clear_notices();
			return self::error('mptbm_app_cart_refused', $message, 409);
		}

		/** What the website's booking form would have posted for this selection. */
		private static function posted_fields(array $in, array $trip, int $vehicle_id, array $extras): array {
			$post = array(
				'mptbm_start_place' => $trip['start']['name'],
				'mptbm_end_place' => $trip['end']['name'] ?? '',
				'mptbm_original_price_base' => $trip['price_based'],
				'mptbm_date' => $trip['date'] . ' ' . $trip['time'],
				'transport_quantity' => 1,
				'mptbm_extra_service' => array_column($extras, 'name'),
				'mptbm_extra_service_qty' => array_column($extras, 'qty'),
				'mptbm_stoppage_id' => array_values(array_filter(array_map('absint', (array) ($in['stoppages'] ?? array())))),
				'payment_method' => sanitize_key((string) ($in['payment_method'] ?? '')),
				'order_comments' => sanitize_textarea_field((string) ($in['order_notes'] ?? '')),
			);
			// Only the Pro search filters the customer actually used are posted, as the website form does.
			foreach (array('max_passenger', 'max_bag', 'max_hand_luggage') as $filter) {
				if (!empty($trip[$filter])) {
					$post['mptbm_' . $filter] = (int) $trip[$filter];
				}
			}
			// WooCommerce refuses any order that posts the `terms-field` marker without `terms`, so the marker is
			// only sent when the shop actually shows a terms checkbox (the same condition the checkout form uses).
			if (function_exists('wc_terms_and_conditions_checkbox_enabled') && wc_terms_and_conditions_checkbox_enabled()) {
				$post['terms-field'] = '1';
				if (!empty($in['accept_terms'])) {
					$post['terms'] = 'on';
				}
			}
			foreach ((array) ($in['billing'] ?? array()) as $key => $value) {
				if (is_string($key) && preg_match('/^[a-z0-9_]{1,60}$/i', $key) && (is_scalar($value))) {
					$post[$key] = (string) $value;
				}
			}
			// WordPress slashes $_POST on a normal request and the plugin and WooCommerce unslash it; do the same.
			return wp_slash($post);
		}

		/** Take payment where the server can, otherwise hand the customer a payment page. */
		private static function finish(WC_Order $order, string $method, int $user_id): array {
			$payment = array('method' => $method, 'title' => $order->get_payment_method_title(), 'type' => 'none', 'instructions' => '', 'pay_url' => '');
			if (!$order->needs_payment()) {
				$order->payment_complete();
			} else {
				$gateways = WC()->payment_gateways()->payment_gateways();
				$gateway = $gateways[$method] ?? null;
				if ($gateway && in_array($method, self::OFFLINE_GATEWAYS, true)) {
					$result = $gateway->process_payment($order->get_id());
					$payment['type'] = 'offline';
					$payment['instructions'] = trim(wp_strip_all_tags((string) ($gateway->instructions ?? '')));
					if (!is_array($result) || 'success' !== ($result['result'] ?? '')) {
						$order->update_status('failed', esc_html__('Offline payment could not be started from the app.', 'ecab-taxi-booking-manager'));
					}
				} else {
					$payment['type'] = 'online';
					$payment['pay_url'] = self::pay_link($order, $user_id);
				}
			}
			$order = wc_get_order($order->get_id()); // statuses may have moved while paying
			return array('booking' => self::summary($order), 'payment' => $payment);
		}

		/* -------------------------------------------------------------- reading orders */

		private static function order_items(WC_Order $order): array {
			$trips = array();
			$route = $order->get_meta('_mptbm_app_route');
			$point = static function ($key) use ($route) {
				$p = is_array($route) ? ($route[$key] ?? null) : null;
				return is_array($p) && isset($p['lat'], $p['lng']) ? array('lat' => (float) $p['lat'], 'lng' => (float) $p['lng']) : null;
			};
			foreach ($order->get_items() as $item) {
				$vehicle_id = (int) $item->get_meta('_mptbm_id');
				if (!$vehicle_id) {
					continue;
				}
				$services = $item->get_meta('_mptbm_service_info');
				$stoppages = $item->get_meta('_mptbm_stoppage_info');
				$stops = $item->get_meta('_mptbm_extra_stop_place');
				$trips[] = array(
					'vehicle_id' => $vehicle_id,
					'vehicle' => wp_strip_all_tags(get_the_title($vehicle_id)),
					'pickup' => wp_strip_all_tags((string) $item->get_meta('_mptbm_start_place')),
					'dropoff' => wp_strip_all_tags((string) $item->get_meta('_mptbm_end_place')),
					'pickup_point' => $point('start'),
					'dropoff_point' => $point('end'),
					'extra_stops' => array_map('wp_strip_all_tags', array_values(array_filter((array) $stops))),
					'date' => (string) $item->get_meta('_mptbm_date'),
					'return' => (int) $item->get_meta('_mptbm_taxi_return') > 1,
					'passengers' => (int) $item->get_meta('_mptbm_passengers'),
					'extras' => array_map(static function ($s) {
						return array('name' => wp_strip_all_tags((string) ($s['service_name'] ?? '')), 'qty' => (int) ($s['service_quantity'] ?? 1), 'price' => (float) ($s['service_price'] ?? 0));
					}, is_array($services) ? array_values($services) : array()),
					'stoppages' => array_map(static function ($s) {
						return array('name' => wp_strip_all_tags((string) ($s['stoppage_name'] ?? '')), 'price' => (float) ($s['stoppage_price'] ?? 0));
					}, is_array($stoppages) ? array_values($stoppages) : array()),
				);
			}
			return $trips;
		}

		/** Cancel and change requests are Pro features: the free plugin only offers them when Pro's handlers exist. */
		public static function changes_supported(): bool {
			return class_exists('MPTBM_Plugin_Pro') && class_exists('MPTBM_User_Dashboard');
		}

		private static function changeable(WC_Order $order): bool {
			return self::changes_supported() && in_array($order->get_status(), array('pending', 'processing'), true);
		}

		/**
		 * Where the ride itself is (the driver's ride status), as opposed to the order's payment status. Pro only; null while
		 * the driver has not set one. The app watches it to tell the passenger the ride started and finished.
		 *
		 * @return array{status:string,label:string}|null
		 */
		public static function ride_for(WC_Order $order): ?array {
			if (!self::changes_supported() || !in_array($order->get_status(), array('processing', 'completed'), true)) {
				return null;
			}
			$records = get_posts(array('post_type' => 'mptbm_booking', 'numberposts' => 1, 'post_status' => 'any', 'fields' => 'ids', 'meta_key' => 'mptbm_order_id', 'meta_value' => $order->get_id()));
			$slug = $records ? sanitize_key((string) get_post_meta($records[0], 'mptbm_service_status', true)) : '';
			if ('' === $slug) {
				return null;
			}
			$term = taxonomy_exists('mptbm_service_status') ? get_term_by('slug', $slug, 'mptbm_service_status') : false;
			return array('status' => $slug, 'label' => $term ? wp_strip_all_tags(html_entity_decode($term->name, ENT_QUOTES, 'UTF-8')) : ucwords(str_replace('-', ' ', $slug)));
		}

		/** The booking's driver, once the shop has assigned one and the booking is confirmed. */
		private static function driver_for(WC_Order $order): ?array {
			if (!self::changes_supported() || !in_array($order->get_status(), array('processing', 'completed'), true)) {
				return null;
			}
			$records = get_posts(array('post_type' => 'mptbm_booking', 'numberposts' => 1, 'post_status' => 'any', 'fields' => 'ids', 'meta_key' => 'mptbm_order_id', 'meta_value' => $order->get_id()));
			$driver_id = 0;
			if ($records) {
				$driver_id = (int) (get_post_meta($records[0], 'mptbm_selected_driver', true) ?: get_post_meta($records[0], 'mptbm_driver_id', true));
			}
			foreach ($order->get_items() as $item) {
				if ($driver_id) {
					break;
				}
				$driver_id = (int) get_post_meta((int) $item->get_meta('_mptbm_id'), 'mptbm_selected_driver', true);
			}
			$driver = $driver_id ? get_userdata($driver_id) : null;
			if (!$driver) {
				return null;
			}
			return array('name' => wp_strip_all_tags($driver->display_name), 'phone' => (string) get_user_meta($driver_id, 'billing_phone', true));
		}

		/**
		 * Pro's ticket/receipt PDF, when the PDF library is present and the booking is in a status the shop
		 * lets customers download tickets for. The link is Pro's own download URL, authorised by the order key
		 * and a nonce made for a signed-out visitor, so it opens in the phone's browser without a login.
		 */
		/** The PDF ticket and the thermal receipt are Pro features. The phone draws them, so no PDF library is needed on the site. */
		public static function receipts_supported(): bool {
			return class_exists('MPTBM_Plugin_Pro');
		}

		/**
		 * Everything the app needs to draw the PDF ticket and the thermal receipt itself - the phone makes the files, so
		 * nothing is opened on the website. Null when Pro is not active, or while the order is not in a status the site
		 * allows tickets for (the same rule the website uses).
		 */
		private static function receipt(WC_Order $order): ?array {
			if (!self::receipts_supported()) {
				return null;
			}
			$allowed = class_exists('MPTBM_Booking_Source') ? MPTBM_Booking_Source::ticket_allowed($order->get_status()) : in_array($order->get_status(), array('processing', 'completed'), true);
			if (!$allowed) {
				return null;
			}
			$settings = static function (string $key): string {
				return wp_strip_all_tags((string) MP_Global_Function::get_settings('mptbm_pdf_settings', $key));
			};
			$extras = 0.0;
			$stops = 0.0;
			$base_fee = 0.0;
			foreach ($order->get_items() as $item) {
				if (!(int) $item->get_meta('_mptbm_id')) {
					continue;
				}
				foreach ((array) $item->get_meta('_mptbm_service_info') as $service) {
					$extras += (float) ($service['service_price'] ?? 0) * (float) ($service['service_quantity'] ?? 0);
				}
				foreach ((array) $item->get_meta('_mptbm_stoppage_info') as $stop) {
					$stops += (float) ($stop['stoppage_price'] ?? 0);
				}
				$base_fee += (float) $item->get_meta('_mptbm_threshold_base_price');
			}
			$total = (float) $order->get_total();
			$discount = (float) $order->get_discount_total();
			$tax = (float) $order->get_total_tax();
			$transport = max(0.0, $total + $discount - $tax - $extras - $stops - $base_fee);
			$logo = (int) MP_Global_Function::get_settings('mptbm_pdf_settings', 'pdf_logo');
			$created = $order->get_date_created();
			$trips = self::order_items($order);
			$first = $order->get_items();
			$first = $first ? reset($first) : null;
			$distance = $first ? (string) $first->get_meta('_mptbm_distance_text') : '';
			$duration = $first ? (string) $first->get_meta('_mptbm_duration_text') : '';
			return array(
				'brand' => array(
					'name' => wp_strip_all_tags(get_bloginfo('name')),
					'address' => $settings('pdf_address'),
					'phone' => $settings('pdf_phone'),
					'logo' => $logo ? (string) wp_get_attachment_url($logo) : '',
				),
				'customer' => array(
					'name' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
					'email' => $order->get_billing_email(),
					'phone' => $order->get_billing_phone(),
				),
				'reference' => 'MPTBM-' . str_pad((string) $order->get_id(), 5, '0', STR_PAD_LEFT),
				'booked_on' => $created ? $created->date('Y-m-d H:i') : '',
				'distance_text' => wp_strip_all_tags($distance),
				'duration_text' => wp_strip_all_tags($duration),
				'transport' => round($transport, 4),
				'extras_total' => round($extras, 4),
				'stops_total' => round($stops, 4),
				'base_fee' => round($base_fee, 4),
				'discount' => round($discount, 4),
				'tax' => round($tax, 4),
				'paid' => $order->is_paid(),
				'trips' => count($trips),
			);
		}

		public static function summary(WC_Order $order): array {
			$created = $order->get_date_created();
			$change = (string) $order->get_meta('_mptbm_modification_request');
			return array(
				'receipt' => self::receipt($order),
				'can_cancel' => self::changeable($order),
				'can_modify' => self::changeable($order) && 'pending' !== $order->get_meta('_mptbm_modification_request_status'),
				'cancellation_requested' => 'pending-cancel' === $order->get_status(),
				'change_request' => '' === $change ? null : array('text' => wp_strip_all_tags($change), 'status' => (string) $order->get_meta('_mptbm_modification_request_status')),
				'driver' => self::driver_for($order),
				'ride' => self::ride_for($order),
				'id' => $order->get_id(),
				'number' => $order->get_order_number(),
				'status' => $order->get_status(),
				'status_label' => wc_get_order_status_name($order->get_status()),
				'created' => $created ? $created->date('c') : '',
				'total' => (float) $order->get_total(),
				'currency' => $order->get_currency(),
				'payment_title' => $order->get_payment_method_title(),
				'payment_method' => (string) $order->get_payment_method(),
				'needs_payment' => $order->needs_payment(),
				'trips' => self::order_items($order),
			);
		}

		private static function owned_order(int $order_id, int $user_id): ?WC_Order {
			$order = $order_id ? wc_get_order($order_id) : null;
			return ($order instanceof WC_Order && !($order instanceof WC_Order_Refund) && $user_id > 0 && (int) $order->get_customer_id() === $user_id && self::order_items($order)) ? $order : null;
		}

		public static function get(int $order_id, int $user_id) {
			if ($unsupported = self::available()) {
				return $unsupported;
			}
			$order = self::owned_order($order_id, $user_id);
			return $order ? self::summary($order) : self::error('mptbm_app_not_found', esc_html__('Booking not found.', 'ecab-taxi-booking-manager'), 404);
		}

		public static function list(int $user_id, int $page = 1, int $per_page = 20) {
			if ($unsupported = self::available()) {
				return $unsupported;
			}
			$result = wc_get_orders(array(
				'customer_id' => $user_id,
				'limit' => $per_page,
				'paged' => max(1, $page),
				'paginate' => true,
				'orderby' => 'date',
				'order' => 'DESC',
				'type' => 'shop_order',
				'status' => array_keys(wc_get_order_statuses()),
			));
			$bookings = array();
			foreach ($result->orders as $order) {
				if (self::order_items($order)) {
					$bookings[] = self::summary($order);
				}
			}
			return array('bookings' => $bookings, 'total' => (int) $result->total, 'pages' => (int) $result->max_num_pages);
		}

		/* ------------------------------------------------------- cancel / change requests */

		private static function changeable_order(int $order_id, int $user_id) {
			if ($unsupported = self::available()) {
				return $unsupported;
			}
			if (!self::changes_supported()) {
				return self::error('mptbm_app_changes_unsupported', esc_html__('Changing a booking is not available for this service.', 'ecab-taxi-booking-manager'), 501);
			}
			$order = self::owned_order($order_id, $user_id);
			if (!$order) {
				return self::error('mptbm_app_not_found', esc_html__('Booking not found.', 'ecab-taxi-booking-manager'), 404);
			}
			if (!in_array($order->get_status(), array('pending', 'processing'), true)) {
				return self::error('mptbm_app_not_changeable', esc_html__('This booking can no longer be changed.', 'ecab-taxi-booking-manager'), 409);
			}
			return $order;
		}

		/** Same effect as the website dashboard's cancel button: the order waits for the shop to approve. */
		public static function request_cancellation(int $order_id, int $user_id) {
			$order = self::changeable_order($order_id, $user_id);
			if (is_wp_error($order)) {
				return $order;
			}
			$order->update_meta_data('_mptbm_prev_status', $order->get_status());
			$order->save();
			$order->update_status('pending-cancel', esc_html__('Cancellation requested by user (mobile app)', 'ecab-taxi-booking-manager'));
			if (!class_exists('MPTBM_Notification_Hub')) {
				do_action('mptbm_notify', 'cancel_request', array(
					/* translators: %d: order number */
					'title' => sprintf(esc_html__('Cancellation requested for order #%d', 'ecab-taxi-booking-manager'), $order_id),
					'desc' => '',
					'link' => method_exists($order, 'get_edit_order_url') ? $order->get_edit_order_url() : '',
					'object_id' => $order_id,
				));
			}
			MPTBM_User_Dashboard::add_user_notification($user_id, 'info', sprintf(esc_html__('You requested cancellation for order #%d. Awaiting admin approval.', 'ecab-taxi-booking-manager'), $order_id));
			return self::summary(wc_get_order($order_id));
		}

		public static function request_change(int $order_id, int $user_id, string $text) {
			$text = trim(sanitize_textarea_field($text));
			if ('' === $text || mb_strlen($text) > 500) {
				return self::error('mptbm_app_bad_change', esc_html__('Describe the requested change in 500 characters or fewer.', 'ecab-taxi-booking-manager'));
			}
			$order = self::changeable_order($order_id, $user_id);
			if (is_wp_error($order)) {
				return $order;
			}
			if ('pending' === $order->get_meta('_mptbm_modification_request_status')) {
				return self::error('mptbm_app_change_pending', esc_html__('A change request is already waiting for review.', 'ecab-taxi-booking-manager'), 409);
			}
			$order->update_meta_data('_mptbm_modification_request', $text);
			$order->update_meta_data('_mptbm_modification_request_status', 'pending');
			$order->add_order_note(sprintf(esc_html__('Customer requested a booking change: %s', 'ecab-taxi-booking-manager'), $text));
			$order->save();
			do_action('mptbm_notify', 'modification_request', array(
				'title' => sprintf(esc_html__('Modification requested for order #%d', 'ecab-taxi-booking-manager'), $order_id),
				'desc' => $text,
				'link' => method_exists($order, 'get_edit_order_url') ? $order->get_edit_order_url() : '',
				'object_id' => $order_id,
			));
			MPTBM_User_Dashboard::add_user_notification($user_id, 'info', sprintf(esc_html__('Your change request for order #%d was sent for review.', 'ecab-taxi-booking-manager'), $order_id));
			return self::summary(wc_get_order($order_id));
		}

		/* ------------------------------------------------------------------- payment */

		/**
		 * A single-use link that signs the customer in inside the app's payment web view and lands on the
		 * order's payment page. Never issued to staff: their web session would be a shop-admin session.
		 */
		public static function pay_link(WC_Order $order, int $user_id): string {
			$user = get_userdata($user_id);
			if (!$user || user_can($user, 'edit_posts') || user_can($user, 'manage_woocommerce')) {
				return '';
			}
			$token = wp_generate_password(40, false, false);
			set_transient('mptbm_app_pay_' . hash('sha256', $token), array('user' => $user_id, 'order' => $order->get_id()), self::PAY_TOKEN_TTL);
			return add_query_arg('ecab_app_pay', $token, home_url('/'));
		}

		/**
		 * A payment link for an unpaid booking. With $method the customer first switches the booking to another payment
		 * method the shop offers (the website's order-pay page lets them do the same): an offline method (cash ...) is
		 * set up right away and no link is returned; an online one returns the link to pay with it.
		 *
		 * @return array|WP_Error pay_url ('' when nothing is to be opened) and, after a switch, booking + payment
		 */
		public static function new_pay_link(int $order_id, int $user_id, string $method = '', string $return_url = '') {
			if ($unsupported = self::available()) {
				return $unsupported;
			}
			$order = self::owned_order($order_id, $user_id);
			if (!$order) {
				return self::error('mptbm_app_not_found', esc_html__('Booking not found.', 'ecab-taxi-booking-manager'), 404);
			}
			if (!$order->needs_payment()) {
				return self::error('mptbm_app_nothing_to_pay', esc_html__('This booking does not need a payment.', 'ecab-taxi-booking-manager'), 409);
			}
			// Where the shop's "payment received" page sends the customer back to: the app's own address (never a web address).
			$return_url = self::return_url($return_url);
			if ('' !== $return_url) {
				$order->update_meta_data(self::RETURN_META, $return_url);
				$order->save();
			}
			if ('' !== $method && $method !== $order->get_payment_method()) {
				self::load_cart();
				$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->get_available_payment_gateways() : array();
				if (!isset($gateways[$method])) {
					return self::error('mptbm_app_bad_payment_method', esc_html__('Choose one of the payment methods offered.', 'ecab-taxi-booking-manager'), 400);
				}
				$order->set_payment_method($gateways[$method]);
				$order->save();
				$order->add_order_note(sprintf(
					/* translators: %s: payment method name */
					esc_html__('Customer chose another payment method in the app: %s', 'ecab-taxi-booking-manager'),
					wp_strip_all_tags($gateways[$method]->get_title())
				));
			}
			if ('' !== $method && in_array($order->get_payment_method(), self::OFFLINE_GATEWAYS, true)) {
				$done = self::finish($order, $order->get_payment_method(), $user_id);
				return array_merge(array('pay_url' => ''), $done);
			}
			$url = self::pay_link($order, $user_id);
			if ('' === $url) {
				return self::error('mptbm_app_pay_on_site', esc_html__('Please pay for this booking on the website.', 'ecab-taxi-booking-manager'), 403);
			}
			return '' !== $method ? array_merge(array('pay_url' => $url), self::finish_summary($order)) : array('pay_url' => $url);
		}

		/** @return string the app address to return to ('' unless it is a custom-scheme link such as ecabtaxi://pay-done): never http(s) or anything a browser would run */
		public static function return_url(string $url): string {
			$url = trim($url);
			if (strlen($url) > 300 || !preg_match('#^([a-z][a-z0-9+.\-]{1,40}):(//)?[A-Za-z0-9\-._~:/?\#\[\]@!$&()*+,;=%]*$#', $url, $m)) {
				return '';
			}
			$blocked = array('http', 'https', 'javascript', 'data', 'file', 'vbscript', 'blob', 'about', 'intent', 'content', 'ftp', 'ws', 'wss', 'mailto', 'tel', 'sms');
			return in_array(strtolower($m[1]), $blocked, true) ? '' : $url;
		}

		/**
		 * The shop's "order received" page, for a booking paid from the app: instead of the shop's thank-you page it sends the customer
		 * straight back into the app (which closes the in-app payment sheet). A button is there too, in case the phone wants a tap first.
		 */
		public static function return_page(): void {
			if (!function_exists('is_wc_endpoint_url') || !is_wc_endpoint_url('order-received')) {
				return;
			}
			$id = absint(get_query_var('order-received'));
			$key = isset($_GET['key']) ? sanitize_text_field(wp_unslash((string) $_GET['key'])) : '';
			$order = $id ? wc_get_order($id) : null;
			if (!$order instanceof WC_Order || '' === $key || !hash_equals((string) $order->get_order_key(), $key)) {
				return;
			}
			$return = self::return_url((string) $order->get_meta(self::RETURN_META));
			if ('' === $return) {
				return;
			}
			$target = $return . (false === strpos($return, '?') ? '?' : '&') . 'order=' . $id . '&paid=' . ($order->needs_payment() ? '0' : '1');
			nocache_headers();
			header('X-LiteSpeed-Cache-Control: no-cache');
			header('Content-Type: text/html; charset=UTF-8');
			$title = $order->needs_payment() ? esc_html__('Confirming your payment', 'ecab-taxi-booking-manager') : esc_html__('Payment received', 'ecab-taxi-booking-manager');
			echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>' . esc_html($title) . '</title>'
				. '<style>body{margin:0;font-family:-apple-system,Roboto,Arial,sans-serif;background:#f2f6f6;color:#14262a;display:flex;min-height:100vh;align-items:center;justify-content:center;text-align:center}'
				. '.c{padding:32px;max-width:360px}h1{font-size:22px;margin:0 0 8px}p{color:#5b6b6e;margin:0 0 24px}a{display:block;background:#0b7f85;color:#fff;text-decoration:none;font-weight:700;padding:16px;border-radius:12px}</style></head><body><div class="c"><h1>'
				. esc_html($title) . '</h1><p>' . esc_html__('Taking you back to the app…', 'ecab-taxi-booking-manager') . '</p><a href="' . esc_attr($target) . '">' . esc_html__('Return to the app', 'ecab-taxi-booking-manager') . '</a></div>'
				. '<script>setTimeout(function(){location.href=' . wp_json_encode($target, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';},400);</script></body></html>';
			exit;
		}

		private static function finish_summary(WC_Order $order): array {
			$order = wc_get_order($order->get_id());
			return array('booking' => self::summary($order), 'payment' => array('method' => $order->get_payment_method(), 'title' => $order->get_payment_method_title(), 'type' => 'online', 'instructions' => '', 'pay_url' => ''));
		}

		/**
		 * Spend a payment link. Single use: the token is gone whether or not it was valid.
		 *
		 * @return array|WP_Error ['user' => WP_User, 'order' => WC_Order]
		 */
		public static function consume_pay_token(string $token) {
			$key = 'mptbm_app_pay_' . hash('sha256', $token);
			$grant = get_transient($key);
			// Single use, except that the very same page may be loaded again for a minute: a web view reloads when a bot check
			// or a slow page interrupts it, and the customer must not be told the link is spent by their own reload.
			if (is_array($grant) && !empty($grant['used']) && time() - (int) $grant['used'] > self::PAY_TOKEN_REUSE) {
				$grant = false;
			}
			if (is_array($grant) && empty($grant['used'])) {
				$grant['used'] = time();
				set_transient($key, $grant, self::PAY_TOKEN_REUSE + 5);
			} elseif (!is_array($grant)) {
				delete_transient($key);
			}
			$order = is_array($grant) ? wc_get_order((int) $grant['order']) : null;
			$user = is_array($grant) ? get_userdata((int) $grant['user']) : null;
			if (!$order || !$user || (int) $order->get_customer_id() !== (int) $user->ID || !$order->needs_payment() || user_can($user, 'edit_posts') || user_can($user, 'manage_woocommerce')) {
				return new WP_Error('mptbm_app_pay_link_expired', esc_html__('This payment link has expired. Go back to the app and try again.', 'ecab-taxi-booking-manager'), array('status' => 403));
			}
			return array('user' => $user, 'order' => $order);
		}

		/** Visited inside the payment web view: sign in, then go to the order's payment page. */
		public static function pay_redirect(): void {
			if (!isset($_GET['ecab_app_pay']) || !class_exists('WooCommerce')) {
				return;
			}
			$grant = self::consume_pay_token(sanitize_text_field(wp_unslash((string) $_GET['ecab_app_pay'])));
			if (is_wp_error($grant)) {
				wp_die(esc_html($grant->get_error_message()), '', array('response' => 403));
			}
			nocache_headers();
			header('X-LiteSpeed-Cache-Control: no-cache');
			wp_set_current_user($grant['user']->ID);
			wp_set_auth_cookie($grant['user']->ID, false);
			wp_safe_redirect($grant['order']->get_checkout_payment_url());
			exit;
		}
	}
	MPTBM_App_Booking::init();
}
