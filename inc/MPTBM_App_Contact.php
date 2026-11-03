<?php
/*
 * The mobile-app settings a customer (the taxi company) fills in: an optional Google Maps key for the app's map and the
 * support email and phone passengers can reach in an emergency. Plain validators, so what the app receives is always
 * safe to put in a link (mailto:, tel:) or a page.
 */
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_App_Contact')) {
	class MPTBM_App_Contact {
		/** @return string a Google Maps (browser) key, or '' when empty or not shaped like one */
		public static function google_key($raw): string {
			$key = trim((string) $raw);
			return preg_match('/^[A-Za-z0-9_\-]{20,100}$/', $key) ? $key : '';
		}

		/** @return string the phone number to show, digits with an optional leading + and common separators; '' when not a phone number */
		public static function phone($raw): string {
			$phone  = trim(preg_replace('/\s+/', ' ', (string) $raw));
			$digits = preg_replace('/\D/', '', $phone);
			if (!preg_match('/^\+?[0-9 ()\-.]+$/', $phone) || strlen($digits) < 5 || strlen($digits) > 15) {
				return '';
			}
			return $phone;
		}

		/** @return string the number as a tel: target (no spaces or punctuation) */
		public static function phone_link(string $phone): string {
			if ('' === $phone) {
				return '';
			}
			return ('+' === substr($phone, 0, 1) ? '+' : '') . preg_replace('/\D/', '', $phone);
		}

		/** @return array{provider:string, google_key:string} how the app draws its map: Google when the company gave a key, else OpenStreetMap */
		public static function app_map(): array {
			$key = self::google_key(MP_Global_Function::get_settings('mptbm_app_settings', 'app_google_maps_key', ''));
			return array('provider' => '' !== $key ? 'google' : 'openstreetmap', 'google_key' => $key);
		}

		/** @return array{email:string, phone:string, phone_link:string} */
		public static function support(): array {
			$email = sanitize_email((string) MP_Global_Function::get_settings('mptbm_app_settings', 'support_email', ''));
			if ('' === $email) {
				// Until the company sets one: the address the shop's own customer emails come from.
				$email = sanitize_email((string) get_option('woocommerce_email_from_address', get_option('admin_email')));
			}
			$phone = self::phone(MP_Global_Function::get_settings('mptbm_app_settings', 'support_phone', ''));
			return array('email' => $email, 'phone' => $phone, 'phone_link' => self::phone_link($phone));
		}
	}
}
