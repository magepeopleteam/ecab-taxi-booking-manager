<?php
/*
 * The mobile-app settings a customer (the taxi company) fills in: the
 * support email and phone passengers can reach in an emergency. Plain validators, so what the app receives is always
 * safe to put in a link (mailto:, tel:) or a page.
 */
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_App_Contact')) {
	class MPTBM_App_Contact {
		/** A logo saved before the fix reads back as "http://2179"; turn it into the image ID it was so the picker and the app see the image. */
		public static function repair_logo($value) {
			if (is_array($value) && isset($value['brand_logo']) && is_string($value['brand_logo']) && preg_match('#^https?://(\d+)/?$#', $value['brand_logo'], $m)) {
				$value['brand_logo'] = (int) $m[1];
			}
			return $value;
		}

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

		/**
		 * How the app draws its map: exactly what the website's Map API Settings say.
		 * 'google' (with the site's own browser key) when the site's map is Google and has a usable key; 'tomtom' when the site
		 * measures routes with TomTom (the app draws the road TomTom found, on OpenStreetMap tiles, as the website does);
		 * 'none' when the site's map is off; otherwise 'openstreetmap'. The TomTom key itself never leaves the server.
		 *
		 * @return array{provider:string, google_key:string}
		 */
		public static function app_map(): array {
			$site = 'mptbm_map_api_settings';
			$mode = MP_Global_Function::get_settings($site, 'display_map', 'openstreetmap');
			if ('disable' === $mode) {
				return array('provider' => 'none', 'google_key' => '');
			}
			if ('enable' === $mode) {
				$key = self::google_key(MP_Global_Function::get_settings($site, 'gmap_api_key', ''));
				if ('' !== $key) {
					return array('provider' => 'google', 'google_key' => $key);
				}
			}
			if (self::uses_tomtom()) {
				return array('provider' => 'tomtom', 'google_key' => '');
			}
			return array('provider' => 'openstreetmap', 'google_key' => '');
		}

		/** @return bool the site routes with TomTom and has entered its key */
		public static function uses_tomtom(): bool {
			return 'tomtom' === MP_Global_Function::get_settings('mptbm_map_api_settings', 'fallback_routing_provider', 'osrm')
				&& '' !== trim((string) MP_Global_Function::get_settings('mptbm_map_api_settings', 'tomtom_api_key', ''));
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

add_filter('option_mptbm_app_settings', array('MPTBM_App_Contact', 'repair_logo'));
