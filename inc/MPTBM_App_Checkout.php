<?php
/*
 * WooCommerce checkout for the mobile app.
 *
 * WC_Checkout::process_checkout() validates, creates the order, takes payment and then ends the
 * request with wp_send_json()/redirect, so it cannot be called from a REST handler. This runs the same
 * steps in the same order - every action and filter the shop and its plugins hook into still fires -
 * but returns the order (or the messages) instead of ending the request, and leaves taking payment to
 * the caller, which knows whether the chosen method is offline or finishes in a web page.
 *
 * Loaded on demand (MPTBM_App_Booking::checkout()): WooCommerce is not loaded yet when the plugin files are.
 */
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.

if (class_exists('WC_Checkout') && !class_exists('MPTBM_App_Checkout')) {
	class MPTBM_App_Checkout extends WC_Checkout {
		/**
		 * Validate the posted checkout ($_POST) against the current cart and create the order.
		 *
		 * @return WC_Order|WP_Error The error carries 'messages' (all of them) and 'fields' (field key => message).
		 */
		public function place() {
			try {
				do_action('woocommerce_before_checkout_process');
				if (WC()->cart->is_empty()) {
					return new WP_Error('mptbm_app_cart_empty', esc_html__('Your booking session expired. Please search again.', 'ecab-taxi-booking-manager'), array('status' => 410));
				}
				wc_clear_notices();
				do_action('woocommerce_checkout_process'); // the plugin validates its custom fields here

				$errors = new WP_Error();
				$posted = $this->get_posted_data();
				try {
					$this->update_session($posted);
				} catch (WC_Data_Exception $e) {
					if ('customer_invalid_billing_email' !== $e->getErrorCode()) {
						throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
					}
				}
				$this->validate_checkout($posted, $errors);
				foreach ($errors->errors as $code => $messages) {
					foreach ($messages as $message) {
						wc_add_notice($message, 'error', $errors->get_error_data($code));
					}
				}
				if (wc_notice_count('error') > 0) {
					return $this->failure('mptbm_app_checkout_invalid', 400);
				}

				$this->process_customer($posted);
				$order_id = $this->create_order($posted);
				if (is_wp_error($order_id)) {
					throw new Exception($order_id->get_error_message());
				}
				$order = wc_get_order($order_id);
				if (!$order) {
					throw new Exception(__('Unable to create order.', 'woocommerce'));
				}
				do_action('woocommerce_checkout_order_processed', $order_id, $posted, $order); // creates the taxi booking
				return $order;
			} catch (Exception $e) {
				wc_add_notice($e->getMessage(), 'error');
				return $this->failure('mptbm_app_checkout_failed', 400);
			}
		}

		/** Turn the queued WooCommerce error notices into one WP_Error. */
		private function failure(string $code, int $status): WP_Error {
			$messages = array();
			$fields = array();
			foreach (wc_get_notices('error') as $notice) {
				$text = trim(wp_strip_all_tags(html_entity_decode((string) ($notice['notice'] ?? ''), ENT_QUOTES, 'UTF-8')));
				if ('' === $text) {
					continue;
				}
				$messages[] = $text;
				if (!empty($notice['data']['id'])) {
					$fields[(string) $notice['data']['id']] = $text;
				}
			}
			wc_clear_notices();
			$messages = $messages ?: array(esc_html__('We could not complete your booking. Please try again.', 'ecab-taxi-booking-manager'));
			return new WP_Error($code, $messages[0], array('status' => $status, 'messages' => $messages, 'fields' => $fields));
		}
	}
}
