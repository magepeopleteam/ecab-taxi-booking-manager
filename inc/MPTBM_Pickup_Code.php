<?php
/*
 * Pickup code: proves the person getting into the car is the passenger who booked.
 *
 * Every new booking gets a 6-digit code. The passenger sees it (and as a QR code) in the mobile app and on the order
 * pages; the driver asks for it, or scans the QR, at the pickup. The driver side (E-cab Pro) refuses to start the trip
 * until the code has been checked. This class only holds the code and its checking so the free plugin, Pro and the
 * apps all agree on one rule. It never sends the code anywhere itself.
 */
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_Pickup_Code')) {
	class MPTBM_Pickup_Code {
		const META = 'mptbm_pickup_code';
		const VERIFIED_META = 'mptbm_pickup_verified_at';
		const MAX_TRIES = 5;
		const LOCK_SECONDS = 600;

		public static function init(): void {
			add_action('woocommerce_order_details_after_order_table', array(__CLASS__, 'show_on_order'), 5);
			add_action('woocommerce_email_after_order_table', array(__CLASS__, 'show_in_email'), 20, 4);
		}

		/** Give a booking its code (once). Existing bookings are never given one later: they were made before the passenger could see it. */
		public static function ensure(int $booking_id): string {
			if ($booking_id <= 0 || 'mptbm_booking' !== get_post_type($booking_id)) {
				return '';
			}
			$code = (string) get_post_meta($booking_id, self::META, true);
			if ('' === $code) {
				$code = (string) wp_rand(100000, 999999);
				update_post_meta($booking_id, self::META, $code);
			}
			return $code;
		}

		public static function code(int $booking_id): string {
			return (string) get_post_meta($booking_id, self::META, true);
		}

		public static function has_code(int $booking_id): bool {
			return '' !== self::code($booking_id);
		}

		public static function is_verified(int $booking_id): bool {
			return '' !== (string) get_post_meta($booking_id, self::VERIFIED_META, true);
		}

		/** What the QR code on the passenger's phone holds. The order number lets the driver's app warn about a code for another trip. */
		public static function qr_payload(int $order_id, string $code): string {
			return 'ECAB-PICKUP:' . $order_id . ':' . $code;
		}

		/** The booking record of an order (the first one, as the rest of the app API does). */
		public static function booking_for_order(int $order_id): int {
			$ids = get_posts(array('post_type' => 'mptbm_booking', 'numberposts' => 1, 'post_status' => 'any', 'fields' => 'ids', 'meta_key' => 'mptbm_order_id', 'meta_value' => $order_id));
			return $ids ? (int) $ids[0] : 0;
		}

		/** Seconds left on a lock after too many wrong tries; 0 when not locked. */
		public static function locked_for(int $booking_id): int {
			$until = (int) get_transient('mptbm_pickup_lock_' . $booking_id);
			return $until > time() ? $until - time() : 0;
		}

		/**
		 * Check a code typed or scanned by the driver. Wrong tries are counted per booking so the 6 digits cannot be guessed.
		 *
		 * @return true|WP_Error
		 */
		public static function verify(int $booking_id, string $given, int $by_user = 0) {
			if (!self::has_code($booking_id)) {
				return new WP_Error('mptbm_pickup_no_code', __('This booking has no pickup code.', 'ecab-taxi-booking-manager'), array('status' => 400));
			}
			if (self::is_verified($booking_id)) {
				return true;
			}
			$left = self::locked_for($booking_id);
			if ($left > 0) {
				return new WP_Error('mptbm_pickup_locked', sprintf(
					/* translators: %d: minutes */
					__('Too many wrong codes. Try again in %d minutes, or ask the passenger to check the code in their app.', 'ecab-taxi-booking-manager'),
					max(1, (int) ceil($left / 60))
				), array('status' => 429));
			}
			$given = preg_replace('/\D+/', '', $given);
			if (!hash_equals(self::code($booking_id), (string) $given)) {
				$tries = (int) get_transient('mptbm_pickup_tries_' . $booking_id) + 1;
				if ($tries >= self::MAX_TRIES) {
					set_transient('mptbm_pickup_lock_' . $booking_id, time() + self::LOCK_SECONDS, self::LOCK_SECONDS);
					delete_transient('mptbm_pickup_tries_' . $booking_id);
				} else {
					set_transient('mptbm_pickup_tries_' . $booking_id, $tries, self::LOCK_SECONDS);
				}
				return new WP_Error('mptbm_pickup_wrong', __('That code does not match this trip.', 'ecab-taxi-booking-manager'), array('status' => 400));
			}
			self::mark($booking_id, $by_user, 'code', '');
			return true;
		}

		/** Record the pickup as confirmed without a code (Pro lets the shop allow this, with a reason). */
		public static function mark(int $booking_id, int $by_user, string $method, string $reason): void {
			delete_transient('mptbm_pickup_tries_' . $booking_id);
			delete_transient('mptbm_pickup_lock_' . $booking_id);
			update_post_meta($booking_id, self::VERIFIED_META, current_time('mysql'));
			update_post_meta($booking_id, 'mptbm_pickup_verified_by', $by_user);
			update_post_meta($booking_id, 'mptbm_pickup_verified_method', $method);
			if ('' !== $reason) {
				update_post_meta($booking_id, 'mptbm_pickup_skip_reason', $reason);
			}
			/**
			 * The passenger has been confirmed at the pickup.
			 *
			 * @param int    $booking_id Booking record id.
			 * @param int    $by_user    The driver's user id.
			 * @param string $method     'code' or 'manual'.
			 */
			do_action('mptbm_pickup_verified', $booking_id, $by_user, $method);
		}

		/** Whether a passenger should still see the code for this order. */
		private static function showable(WC_Order $order, int $booking_id): bool {
			return self::has_code($booking_id) && !self::is_verified($booking_id) && !in_array($order->get_status(), array('cancelled', 'refunded', 'failed', 'trash', 'completed'), true);
		}

		/** The passenger-facing lines for an order, used by the app API: null when there is nothing to show. */
		public static function for_order(WC_Order $order): ?array {
			$booking_id = self::booking_for_order($order->get_id());
			if (!$booking_id || !self::has_code($booking_id) || in_array($order->get_status(), array('cancelled', 'refunded', 'failed', 'trash'), true)) {
				return null;
			}
			$verified = self::is_verified($booking_id);
			$code = self::code($booking_id);
			return array(
				'code' => $verified ? '' : $code,
				'qr' => $verified ? '' : self::qr_payload($order->get_id(), $code),
				'verified' => $verified,
				'verified_at' => $verified ? (string) get_post_meta($booking_id, self::VERIFIED_META, true) : '',
			);
		}

		private static function box(string $code): string {
			return '<div class="mptbm-pickup-code" style="margin:16px 0;padding:14px 18px;border:2px dashed #0b7f85;border-radius:8px;text-align:center">'
				. '<div style="font-size:14px">' . esc_html__('Your pickup code', 'ecab-taxi-booking-manager') . '</div>'
				. '<div style="font-size:32px;font-weight:700;letter-spacing:6px;margin:4px 0">' . esc_html(implode(' ', str_split($code, 3))) . '</div>'
				. '<div style="font-size:13px">' . esc_html__('Tell this code to your driver when you meet, so they know you are the passenger who booked.', 'ecab-taxi-booking-manager') . '</div></div>';
		}

		/** Order page (thank-you and My account): shown to the person who owns the order only, because WooCommerce shows these pages only to them. */
		public static function show_on_order($order): void {
			if (!$order instanceof WC_Order) {
				return;
			}
			$booking_id = self::booking_for_order($order->get_id());
			if ($booking_id && self::showable($order, $booking_id)) {
				echo wp_kses_post(self::box(self::code($booking_id)));
			}
		}

		public static function show_in_email($order, $sent_to_admin = false, $plain_text = false, $email = null): void {
			if ($sent_to_admin || !$order instanceof WC_Order) {
				return;
			}
			$booking_id = self::booking_for_order($order->get_id());
			if (!$booking_id || !self::showable($order, $booking_id)) {
				return;
			}
			if ($plain_text) {
				echo "\n" . esc_html__('Your pickup code', 'ecab-taxi-booking-manager') . ': ' . esc_html(self::code($booking_id)) . "\n" . esc_html__('Tell this code to your driver when you meet.', 'ecab-taxi-booking-manager') . "\n";
				return;
			}
			echo wp_kses_post(self::box(self::code($booking_id)));
		}
	}
	MPTBM_Pickup_Code::init();
}
