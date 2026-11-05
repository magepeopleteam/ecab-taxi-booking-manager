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

		/**
		 * Queue a push for every phone a user has registered. Used by add-ons (the Pro driver and ride-status alerts);
		 * the message goes out with the other pushes when the request ends.
		 */
		public static function notify(int $user_id, string $title, string $body, array $data = array()): void {
			foreach (array_keys(self::tokens($user_id)) as $token) {
				self::$queue[] = array(
					'user' => $user_id,
					'message' => array('to' => $token, 'title' => $title, 'body' => $body, 'sound' => 'default', 'data' => $data),
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
			$result = self::deliver(array_column($queue, 'message'), 8);
			foreach ($result['tickets'] as $index => $ticket) {
				if (isset($queue[$index]) && 'error' === ($ticket['status'] ?? '') && 'DeviceNotRegistered' === ($ticket['details']['error'] ?? '')) {
					self::remove($queue[$index]['user'], $queue[$index]['message']['to']);
				}
			}
		}

		/**
		 * Send messages to Expo and say how each one went.
		 *
		 * Expo takes one project's devices per request. A site that serves two apps (the customer app and the driver app,
		 * or an installed app and Expo Go) has tokens from several projects, and Expo then refuses the whole request.
		 * When that happens the messages are sent again, one project at a time, using the grouping Expo returns.
		 *
		 * @param array<int, array> $messages Expo push messages (each has a "to").
		 * @return array{tickets: array<int, array>, errors: array<string,int>} one ticket per message, same order; error reasons with counts
		 */
		public static function deliver(array $messages, int $timeout = 20): array {
			$messages = array_values($messages);
			$tickets  = array();
			$errors   = array();
			if (!$messages) {
				return compact('tickets', 'errors');
			}
			$result = self::post($messages, $timeout);
			if ('PUSH_TOO_MANY_EXPERIENCE_IDS' === $result['refused']) {
				$groups = array();
				foreach ($result['groups'] as $tokens) {
					$groups[] = array_flip($tokens);
				}
				$buckets = array();
				foreach ($messages as $index => $message) {
					$bucket = 'rest';
					foreach ($groups as $g => $set) {
						if (isset($set[$message['to'] ?? ''])) {
							$bucket = 'g' . $g;
							break;
						}
					}
					// Without a usable grouping every device goes alone, so one odd app can never block the others.
					$buckets[$groups ? $bucket : 'm' . $index][] = $index;
				}
				foreach ($buckets as $indexes) {
					$part = self::post(array_map(static function ($i) use ($messages) {
						return $messages[$i];
					}, $indexes), $timeout);
					foreach ($indexes as $n => $i) {
						$tickets[$i] = $part['tickets'][$n] ?? self::failed_ticket($part['reason']);
					}
				}
			} else {
				foreach ($messages as $index => $message) {
					$tickets[$index] = $result['tickets'][$index] ?? self::failed_ticket($result['reason']);
				}
			}
			ksort($tickets);
			foreach ($tickets as $ticket) {
				if ('ok' !== ($ticket['status'] ?? '')) {
					$reason = self::reason((string) ($ticket['details']['error'] ?? ''), (string) ($ticket['message'] ?? ''));
					$errors[$reason] = ($errors[$reason] ?? 0) + 1;
				}
			}
			return compact('tickets', 'errors');
		}

		/** @return array{tickets: array, reason: string, refused: string, groups: array} */
		private static function post(array $messages, int $timeout): array {
			$out      = array('tickets' => array(), 'reason' => '', 'refused' => '', 'groups' => array());
			$response = wp_remote_post(self::ENDPOINT, array(
				'timeout' => $timeout,
				'headers' => array('Accept' => 'application/json', 'Content-Type' => 'application/json'),
				'body'    => wp_json_encode($messages),
			));
			if (is_wp_error($response)) {
				$out['reason'] = 'NETWORK:' . $response->get_error_message();
				return $out;
			}
			$status = (int) wp_remote_retrieve_response_code($response);
			$body   = json_decode(wp_remote_retrieve_body($response), true);
			$body   = is_array($body) ? $body : array();
			foreach ((array) ($body['errors'] ?? array()) as $error) {
				$code = (string) ($error['code'] ?? '');
				if ('' === $out['reason']) {
					$out['reason'] = '' !== $code ? $code . ':' . (string) ($error['message'] ?? '') : 'HTTP ' . $status;
				}
				if ('PUSH_TOO_MANY_EXPERIENCE_IDS' === $code) {
					$out['refused'] = $code;
					foreach ((array) ($error['details'] ?? array()) as $tokens) {
						if (is_array($tokens)) {
							$out['groups'][] = array_values(array_filter($tokens, 'is_string'));
						}
					}
				}
			}
			if ($status >= 400 && '' === $out['reason']) {
				$out['reason'] = 'HTTP ' . $status;
			}
			if (is_array($body['data'] ?? null)) {
				$out['tickets'] = array_values($body['data']);
				// Some replies mark each ticket instead of refusing the request; treat that the same way.
				foreach ($out['tickets'] as $ticket) {
					if ('PUSH_TOO_MANY_EXPERIENCE_IDS' === ($ticket['details']['error'] ?? '')) {
						$out['refused'] = 'PUSH_TOO_MANY_EXPERIENCE_IDS';
						foreach ((array) ($ticket['details']['experienceIdToTokens'] ?? $ticket['details']['experiences'] ?? array()) as $tokens) {
							if (is_array($tokens)) {
								$out['groups'][] = array_values(array_filter($tokens, 'is_string'));
							}
						}
					}
				}
			}
			return $out;
		}

		private static function failed_ticket(string $reason): array {
			$code = explode(':', $reason, 2);
			return array('status' => 'error', 'message' => $code[1] ?? '', 'details' => array('error' => $code[0]));
		}

		/** A short, human reason for the notifications screen. */
		public static function reason(string $code, string $message = ''): string {
			switch ($code) {
				case 'DeviceNotRegistered':
					return __('device no longer registered (app removed or reinstalled)', 'ecab-taxi-booking-manager');
				case 'InvalidCredentials':
				case 'MismatchSenderId':
					return __('the app has no valid Firebase push key in Expo', 'ecab-taxi-booking-manager');
				case 'MessageRateExceeded':
					return __('sending too fast, will work later', 'ecab-taxi-booking-manager');
				case 'MessageTooBig':
					return __('message too large', 'ecab-taxi-booking-manager');
				case 'PUSH_TOO_MANY_EXPERIENCE_IDS':
					return __('devices from different apps could not be separated', 'ecab-taxi-booking-manager');
				case 'NETWORK':
					return __('could not reach the push service', 'ecab-taxi-booking-manager');
				case '':
					return '' !== $message ? $message : __('unknown reason', 'ecab-taxi-booking-manager');
			}
			return '' !== $message ? $code . ': ' . $message : $code;
		}

		/** For tests. */
		public static function queued(): array {
			return self::$queue;
		}
	}
	MPTBM_App_Push::init();
}
