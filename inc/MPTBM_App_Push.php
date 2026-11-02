<?php
/*
 * Push notifications for the mobile app, sent through Expo's push service.
 *
 * The app registers its Expo push token for the signed-in customer; when one of that customer's taxi
 * bookings changes status, a short message is sent to their devices. Sending happens on `shutdown`
 * so the request that changed the order (often the admin saving it) is never held up, and a token the
 * service reports as dead is dropped.
 */
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_App_Push')) {
	class MPTBM_App_Push {
		const META = 'mptbm_app_push_tokens';
		const MAX_TOKENS = 5;
		const ENDPOINT = 'https://exp.host/--/api/v2/push/send';
		const TOKEN_PATTERN = '/^(Exponent|Expo)PushToken\[[A-Za-z0-9_\-]{10,60}\]$/';

		/** @var array<int, array> messages queued during this request */
		private static $queue = array();

		public static function init(): void {
			add_action('woocommerce_order_status_changed', array(__CLASS__, 'order_status_changed'), 20, 4);
			add_action('shutdown', array(__CLASS__, 'flush'));
		}

		/* --------------------------------------------------------------- tokens */

		public static function tokens(int $user_id): array {
			$tokens = get_user_meta($user_id, self::META, true);
			return is_array($tokens) ? $tokens : array();
		}

		public static function register(int $user_id, string $token) {
			if (!preg_match(self::TOKEN_PATTERN, $token)) {
				return new WP_Error('mptbm_app_bad_push_token', esc_html__('That is not a valid push token.', 'ecab-taxi-booking-manager'), array('status' => 400));
			}
			$tokens = self::tokens($user_id);
			unset($tokens[$token]);
			$tokens[$token] = time();
			arsort($tokens); // newest first; keep the most recent devices
			update_user_meta($user_id, self::META, array_slice($tokens, 0, self::MAX_TOKENS, true));
			// A device belongs to one account: drop the token from anyone who used it before.
			foreach (get_users(array('meta_key' => self::META, 'meta_compare' => 'EXISTS', 'exclude' => array($user_id), 'fields' => 'ID')) as $other) {
				self::remove((int) $other, $token);
			}
			return true;
		}

		public static function remove(int $user_id, string $token): void {
			$tokens = self::tokens($user_id);
			if (isset($tokens[$token])) {
				unset($tokens[$token]);
				$tokens ? update_user_meta($user_id, self::META, $tokens) : delete_user_meta($user_id, self::META);
			}
		}

		/* ------------------------------------------------------------- messages */

		/** @return array|null title/body for a status the customer should hear about */
		public static function message_for(string $new_status, string $vehicle): ?array {
			switch ($new_status) {
				case 'processing':
				case 'completed':
					return array(esc_html__('Booking confirmed', 'ecab-taxi-booking-manager'), sprintf(esc_html__('Your %s is booked.', 'ecab-taxi-booking-manager'), $vehicle));
				case 'cancelled':
					return array(esc_html__('Booking cancelled', 'ecab-taxi-booking-manager'), sprintf(esc_html__('Your %s booking was cancelled.', 'ecab-taxi-booking-manager'), $vehicle));
				case 'refunded':
					return array(esc_html__('Booking refunded', 'ecab-taxi-booking-manager'), sprintf(esc_html__('Your %s booking was refunded.', 'ecab-taxi-booking-manager'), $vehicle));
				case 'failed':
					return array(esc_html__('Payment failed', 'ecab-taxi-booking-manager'), esc_html__('We could not take the payment for your booking.', 'ecab-taxi-booking-manager'));
			}
			return null;
		}

		public static function order_status_changed($order_id, $old_status, $new_status, $order = null): void {
			$order = $order instanceof WC_Order ? $order : wc_get_order($order_id);
			if (!$order || !$order->get_customer_id() || $old_status === $new_status) {
				return;
			}
			$vehicle = '';
			foreach ($order->get_items() as $item) {
				$vehicle_id = (int) $item->get_meta('_mptbm_id');
				if ($vehicle_id) {
					$vehicle = wp_strip_all_tags(get_the_title($vehicle_id));
					break;
				}
			}
			if ('' === $vehicle) {
				return; // not a taxi booking
			}
			// "processing" right after a refusal-and-retry is the same news as the first time; do not repeat it.
			if (in_array($old_status, array('processing', 'completed'), true) && in_array($new_status, array('processing', 'completed'), true)) {
				return;
			}
			$message = self::message_for($new_status, $vehicle);
			$tokens = self::tokens((int) $order->get_customer_id());
			if (!$message || !$tokens) {
				return;
			}
			foreach (array_keys($tokens) as $token) {
				self::$queue[] = array(
					'user' => (int) $order->get_customer_id(),
					'message' => array('to' => $token, 'title' => $message[0], 'body' => $message[1], 'sound' => 'default', 'data' => array('booking_id' => (int) $order->get_id())),
				);
			}
		}

		/** Send what was queued. Public so tests can run it without ending the request. */
		public static function flush(): void {
			if (!self::$queue) {
				return;
			}
			$queue = self::$queue;
			self::$queue = array();
			$response = wp_remote_post(self::ENDPOINT, array(
				'timeout' => 8,
				'headers' => array('Accept' => 'application/json', 'Content-Type' => 'application/json'),
				'body' => wp_json_encode(array_column($queue, 'message')),
			));
			if (is_wp_error($response)) {
				return;
			}
			$body = json_decode(wp_remote_retrieve_body($response), true);
			foreach ((array) ($body['data'] ?? array()) as $index => $ticket) {
				if (isset($queue[$index]) && 'error' === ($ticket['status'] ?? '') && 'DeviceNotRegistered' === ($ticket['details']['error'] ?? '')) {
					self::remove($queue[$index]['user'], $queue[$index]['message']['to']);
				}
			}
		}

		/** For tests. */
		public static function queued(): array {
			return self::$queue;
		}
	}
	MPTBM_App_Push::init();
}
