<?php
/*
 * Customer-facing REST API used by the E-cab mobile app (namespace ecab-app/v1).
 *
 * The existing ecab-taxi/v1 API (MPTBM_Rest_Api.php) is key + secret based and meant for
 * server-to-server integrations, so it cannot be shipped inside an app installed on
 * customers' phones. This API is for the customers themselves:
 *   - GET  /config            site bootstrap: currency, map, booking mode, payment methods
 *                             and which Pro/addon features exist (the app hides what is missing)
 *   - GET  /vehicles[/{id}]   published vehicles
 *   - GET  /locations         fixed-route pick-up / drop-off lists (manual pricing)
 *   - POST /auth/register|login|refresh|logout|forgot, GET|PUT /me
 *
 * Auth is a stateless HMAC-signed access token plus a rotating opaque refresh token. Every
 * token is revoked at once by bumping the user's token version (logout, password change).
 */
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_App_Api')) {
	class MPTBM_App_Api {
		const API_VERSION = 1;
		const NAMESPACE_ID = 'ecab-app/v1';
		const ACCESS_TTL = DAY_IN_SECONDS;
		const REFRESH_TTL = 30 * DAY_IN_SECONDS;
		const MAX_REFRESH_TOKENS = 5;
		const META_VERSION = 'mptbm_app_token_ver';
		const META_REFRESH = 'mptbm_app_refresh_tokens';

		public function __construct() {
			add_action('rest_api_init', array($this, 'register_routes'));
		}

		public static function is_enabled(): bool {
			$enabled = MP_Global_Function::get_settings('mptbm_rest_api_settings', 'enable_app_api', 'yes') !== 'no';
			return (bool) apply_filters('mptbm_app_api_enabled', $enabled);
		}

		public function register_routes() {
			if (!self::is_enabled()) {
				return;
			}
			$public = array($this, 'permission_public');
			$auth = array($this, 'permission_customer');

			register_rest_route(self::NAMESPACE_ID, '/config', array('methods' => 'GET', 'callback' => array($this, 'get_config'), 'permission_callback' => $public));

			register_rest_route(self::NAMESPACE_ID, '/vehicles', array(
				'methods' => 'GET',
				'callback' => array($this, 'get_vehicles'),
				'permission_callback' => $public,
				'args' => array(
					'page' => array('type' => 'integer', 'default' => 1, 'minimum' => 1),
					'per_page' => array('type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 50),
					'passengers' => array('type' => 'integer', 'minimum' => 1),
				),
			));
			register_rest_route(self::NAMESPACE_ID, '/vehicles/(?P<id>\d+)', array('methods' => 'GET', 'callback' => array($this, 'get_vehicle'), 'permission_callback' => $public));
			register_rest_route(self::NAMESPACE_ID, '/locations', array(
				'methods' => 'GET',
				'callback' => array($this, 'get_locations'),
				'permission_callback' => $public,
				'args' => array(
					'start' => array('type' => 'string'),
					'price_based' => array('type' => 'string', 'default' => 'manual', 'enum' => array('manual', 'fixed_zone', 'fixed_zone_dropoff')),
				),
			));

			register_rest_route(self::NAMESPACE_ID, '/quote', array(
				'methods' => 'POST',
				'callback' => array($this, 'get_quote'),
				'permission_callback' => array($this, 'permission_quote'),
			));

			register_rest_route(self::NAMESPACE_ID, '/auth/register', array(
				'methods' => 'POST',
				'callback' => array($this, 'register_customer'),
				'permission_callback' => $public,
				'args' => array(
					'email' => array('type' => 'string', 'required' => true),
					'password' => array('type' => 'string', 'required' => true),
					'first_name' => array('type' => 'string', 'required' => true),
					'last_name' => array('type' => 'string'),
					'phone' => array('type' => 'string'),
				),
			));
			register_rest_route(self::NAMESPACE_ID, '/auth/login', array(
				'methods' => 'POST',
				'callback' => array($this, 'login'),
				'permission_callback' => $public,
				'args' => array(
					'username' => array('type' => 'string', 'required' => true),
					'password' => array('type' => 'string', 'required' => true),
				),
			));
			register_rest_route(self::NAMESPACE_ID, '/auth/refresh', array(
				'methods' => 'POST',
				'callback' => array($this, 'refresh'),
				'permission_callback' => $public,
				'args' => array('refresh_token' => array('type' => 'string', 'required' => true)),
			));
			register_rest_route(self::NAMESPACE_ID, '/auth/logout', array('methods' => 'POST', 'callback' => array($this, 'logout'), 'permission_callback' => $auth));
			register_rest_route(self::NAMESPACE_ID, '/auth/forgot', array(
				'methods' => 'POST',
				'callback' => array($this, 'forgot_password'),
				'permission_callback' => $public,
				'args' => array('email' => array('type' => 'string', 'required' => true)),
			));

			register_rest_route(self::NAMESPACE_ID, '/me', array(
				array('methods' => 'GET', 'callback' => array($this, 'get_me'), 'permission_callback' => $auth),
				array('methods' => 'PUT', 'callback' => array($this, 'update_me'), 'permission_callback' => $auth),
			));
		}

		/* ------------------------------------------------------------------ permissions */

		public function permission_public($request) {
			return $this->rate_limit('public', 120, MINUTE_IN_SECONDS);
		}

		/** A quote geocodes and routes on the server, so it gets a tighter budget than plain reads. */
		public function permission_quote($request) {
			return $this->rate_limit('quote', 30, MINUTE_IN_SECONDS);
		}

		public function permission_customer($request) {
			$limited = $this->rate_limit('auth', 120, MINUTE_IN_SECONDS);
			if (is_wp_error($limited)) {
				return $limited;
			}
			$user_id = $this->authenticate($request);
			if (is_wp_error($user_id)) {
				return $user_id;
			}
			wp_set_current_user($user_id);
			return true;
		}

		/** Fixed-window counter per client IP and bucket. */
		private function rate_limit(string $bucket, int $max, int $window) {
			$ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
			$key = 'mptbm_app_rl_' . md5($bucket . '|' . $ip);
			$count = (int) get_transient($key);
			if ($count >= $max) {
				return new WP_Error('mptbm_app_rate_limited', esc_html__('Too many requests. Please try again shortly.', 'ecab-taxi-booking-manager'), array('status' => 429));
			}
			set_transient($key, $count + 1, $window);
			return true;
		}

		/* ----------------------------------------------------------------------- tokens */

		private function b64url_encode(string $data): string {
			return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
		}

		private function b64url_decode(string $data) {
			return base64_decode(strtr($data, '-_', '+/'), true);
		}

		private function sign(string $payload): string {
			return $this->b64url_encode(hash_hmac('sha256', $payload, wp_salt('auth'), true));
		}

		private function token_version(int $user_id): int {
			return (int) get_user_meta($user_id, self::META_VERSION, true);
		}

		private function issue_access_token(int $user_id): string {
			$payload = $this->b64url_encode(wp_json_encode(array(
				'uid' => $user_id,
				'exp' => time() + self::ACCESS_TTL,
				'ver' => $this->token_version($user_id),
			)));
			return $payload . '.' . $this->sign($payload);
		}

		private function bearer_token($request): string {
			$header = (string) $request->get_header('authorization');
			if ('' === $header && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
				$header = sanitize_text_field(wp_unslash($_SERVER['REDIRECT_HTTP_AUTHORIZATION']));
			}
			if ('' === $header) {
				// Some hosts strip Authorization before PHP sees it.
				$header = 'Bearer ' . (string) $request->get_header('x-ecab-token');
			}
			return preg_match('/^Bearer\s+(\S+)$/i', $header, $m) ? $m[1] : '';
		}

		/** @return int|WP_Error user id */
		private function authenticate($request) {
			$error = new WP_Error('mptbm_app_unauthorized', esc_html__('Please log in again.', 'ecab-taxi-booking-manager'), array('status' => 401));
			$token = $this->bearer_token($request);
			$parts = explode('.', $token);
			if (2 !== count($parts) || !hash_equals($this->sign($parts[0]), $parts[1])) {
				return $error;
			}
			$raw = $this->b64url_decode($parts[0]);
			$data = $raw ? json_decode($raw, true) : null;
			if (!is_array($data) || empty($data['uid']) || empty($data['exp']) || (int) $data['exp'] < time()) {
				return $error;
			}
			$user_id = (int) $data['uid'];
			if (!get_userdata($user_id) || (int) ($data['ver'] ?? -1) !== $this->token_version($user_id)) {
				return $error;
			}
			return $user_id;
		}

		private function issue_refresh_token(int $user_id): string {
			$token = $user_id . '.' . wp_generate_password(48, false, false);
			$stored = get_user_meta($user_id, self::META_REFRESH, true);
			$stored = is_array($stored) ? $stored : array();
			$now = time();
			$stored = array_filter($stored, static function ($expires) use ($now) {
				return (int) $expires > $now;
			});
			$stored[hash('sha256', $token)] = $now + self::REFRESH_TTL;
			arsort($stored); // newest expiry first, keep only the most recent devices
			update_user_meta($user_id, self::META_REFRESH, array_slice($stored, 0, self::MAX_REFRESH_TOKENS, true));
			return $token;
		}

		private function token_response(int $user_id): array {
			return array(
				'access_token' => $this->issue_access_token($user_id),
				'expires_in' => self::ACCESS_TTL,
				'refresh_token' => $this->issue_refresh_token($user_id),
				'user' => $this->prepare_user($user_id),
			);
		}

		private function revoke_all_tokens(int $user_id): void {
			update_user_meta($user_id, self::META_VERSION, $this->token_version($user_id) + 1);
			delete_user_meta($user_id, self::META_REFRESH);
		}

		/* ------------------------------------------------------------------------- auth */

		private function auth_error(string $code, string $message, int $status = 400) {
			return new WP_Error($code, $message, array('status' => $status));
		}

		public function register_customer($request) {
			$limited = $this->rate_limit('register', 10, HOUR_IN_SECONDS);
			if (is_wp_error($limited)) {
				return $limited;
			}
			if (!apply_filters('mptbm_app_allow_registration', true)) {
				return $this->auth_error('mptbm_app_registration_closed', esc_html__('Registration is closed.', 'ecab-taxi-booking-manager'), 403);
			}
			$email = sanitize_email((string) $request->get_param('email'));
			$password = (string) $request->get_param('password');
			$first = sanitize_text_field((string) $request->get_param('first_name'));
			if (!is_email($email)) {
				return $this->auth_error('mptbm_app_invalid_email', esc_html__('Enter a valid email address.', 'ecab-taxi-booking-manager'));
			}
			if (strlen($password) < 8 || strlen($password) > 128) {
				return $this->auth_error('mptbm_app_weak_password', esc_html__('Password must be 8 to 128 characters.', 'ecab-taxi-booking-manager'));
			}
			if ('' === $first) {
				return $this->auth_error('mptbm_app_missing_name', esc_html__('First name is required.', 'ecab-taxi-booking-manager'));
			}
			if (email_exists($email)) {
				return $this->auth_error('mptbm_app_email_exists', esc_html__('An account with this email already exists. Please log in.', 'ecab-taxi-booking-manager'), 409);
			}

			if (function_exists('wc_create_new_customer')) {
				$user_id = wc_create_new_customer($email, '', $password);
			} else {
				$user_id = wp_create_user(sanitize_user(current(explode('@', $email)), true) . wp_rand(100, 999), $password, $email);
			}
			if (is_wp_error($user_id)) {
				return $this->auth_error('mptbm_app_register_failed', $user_id->get_error_message());
			}
			wp_update_user(array(
				'ID' => $user_id,
				'first_name' => $first,
				'last_name' => sanitize_text_field((string) $request->get_param('last_name')),
				'display_name' => trim($first . ' ' . sanitize_text_field((string) $request->get_param('last_name'))),
			));
			update_user_meta($user_id, 'billing_first_name', $first);
			update_user_meta($user_id, 'billing_last_name', sanitize_text_field((string) $request->get_param('last_name')));
			update_user_meta($user_id, 'billing_email', $email);
			update_user_meta($user_id, 'billing_phone', sanitize_text_field((string) $request->get_param('phone')));

			return new WP_REST_Response($this->token_response((int) $user_id), 201);
		}

		public function login($request) {
			$username = sanitize_text_field((string) $request->get_param('username'));
			$password = (string) $request->get_param('password');
			// Throttle guesses per client and per account, then answer every failure identically.
			$limited = $this->rate_limit('login', 10, 15 * MINUTE_IN_SECONDS);
			if (is_wp_error($limited)) {
				return $limited;
			}
			$user = wp_authenticate($username, $password);
			if (is_wp_error($user)) {
				return $this->auth_error('mptbm_app_bad_login', esc_html__('Incorrect email or password.', 'ecab-taxi-booking-manager'), 401);
			}
			return new WP_REST_Response($this->token_response((int) $user->ID), 200);
		}

		public function refresh($request) {
			$limited = $this->rate_limit('refresh', 30, 15 * MINUTE_IN_SECONDS);
			if (is_wp_error($limited)) {
				return $limited;
			}
			$token = (string) $request->get_param('refresh_token');
			$hash = hash('sha256', $token);
			$user_id = (int) strtok($token, '.'); // refresh tokens are "<user id>.<random>"
			$stored = $user_id ? get_user_meta($user_id, self::META_REFRESH, true) : array();
			if (is_array($stored) && isset($stored[$hash]) && (int) $stored[$hash] > time()) {
				unset($stored[$hash]); // rotate: a refresh token works once
				update_user_meta($user_id, self::META_REFRESH, $stored);
				return new WP_REST_Response($this->token_response($user_id), 200);
			}
			return $this->auth_error('mptbm_app_bad_refresh', esc_html__('Session expired. Please log in again.', 'ecab-taxi-booking-manager'), 401);
		}

		public function logout($request) {
			$this->revoke_all_tokens(get_current_user_id());
			return new WP_REST_Response(array('success' => true), 200);
		}

		public function forgot_password($request) {
			$limited = $this->rate_limit('forgot', 5, HOUR_IN_SECONDS);
			if (is_wp_error($limited)) {
				return $limited;
			}
			$email = sanitize_email((string) $request->get_param('email'));
			if (is_email($email) && email_exists($email)) {
				retrieve_password($email);
			}
			// Same answer whether or not the account exists.
			return new WP_REST_Response(array('success' => true, 'message' => esc_html__('If an account exists for this email, a reset link has been sent.', 'ecab-taxi-booking-manager')), 200);
		}

		/* --------------------------------------------------------------------------- me */

		private function prepare_user(int $user_id): array {
			$user = get_userdata($user_id);
			return array(
				'id' => $user_id,
				'email' => $user->user_email,
				'first_name' => (string) get_user_meta($user_id, 'first_name', true),
				'last_name' => (string) get_user_meta($user_id, 'last_name', true),
				'phone' => (string) get_user_meta($user_id, 'billing_phone', true),
			);
		}

		public function get_me($request) {
			return new WP_REST_Response($this->prepare_user(get_current_user_id()), 200);
		}

		public function update_me($request) {
			$user_id = get_current_user_id();
			$updates = array('ID' => $user_id);
			foreach (array('first_name', 'last_name') as $field) {
				if (null !== $request->get_param($field)) {
					$updates[$field] = sanitize_text_field((string) $request->get_param($field));
				}
			}
			wp_update_user($updates);
			if (null !== $request->get_param('first_name')) {
				update_user_meta($user_id, 'billing_first_name', $updates['first_name']);
			}
			if (null !== $request->get_param('last_name')) {
				update_user_meta($user_id, 'billing_last_name', $updates['last_name']);
			}
			if (null !== $request->get_param('phone')) {
				update_user_meta($user_id, 'billing_phone', sanitize_text_field((string) $request->get_param('phone')));
			}
			$new_password = (string) $request->get_param('new_password');
			if ('' !== $new_password) {
				$user = get_userdata($user_id);
				if (!wp_check_password((string) $request->get_param('current_password'), $user->user_pass, $user_id)) {
					return $this->auth_error('mptbm_app_bad_password', esc_html__('Current password is incorrect.', 'ecab-taxi-booking-manager'), 403);
				}
				if (strlen($new_password) < 8 || strlen($new_password) > 128) {
					return $this->auth_error('mptbm_app_weak_password', esc_html__('Password must be 8 to 128 characters.', 'ecab-taxi-booking-manager'));
				}
				wp_set_password($new_password, $user_id);
				$this->revoke_all_tokens($user_id);
				return new WP_REST_Response($this->token_response($user_id), 200); // new session for this device
			}
			return new WP_REST_Response($this->prepare_user($user_id), 200);
		}

		/* ------------------------------------------------------------------------ quote */

		public function get_quote($request) {
			$params = $request->get_json_params();
			$result = MPTBM_App_Quote::quote(is_array($params) ? $params : $request->get_params());
			return is_wp_error($result) ? $result : new WP_REST_Response($result, 200);
		}

		/* ----------------------------------------------------------------------- config */

		private function plugin_active(string $needle): bool {
			$active = (array) get_option('active_plugins', array());
			if (is_multisite()) {
				$active = array_merge($active, array_keys((array) get_site_option('active_sitewide_plugins', array())));
			}
			foreach ($active as $plugin) {
				if (false !== strpos((string) $plugin, $needle)) {
					return true;
				}
			}
			return false;
		}

		private function payment_methods(): array {
			$methods = array();
			if (function_exists('WC') && WC()->payment_gateways()) {
				foreach (WC()->payment_gateways()->payment_gateways() as $id => $gateway) {
					if ('yes' !== $gateway->enabled) {
						continue;
					}
					$methods[] = array(
						'id' => (string) $id,
						'title' => wp_strip_all_tags($gateway->get_title()),
						'description' => wp_strip_all_tags($gateway->get_description()),
						'offline' => in_array($id, array('bacs', 'cheque', 'cod'), true),
					);
				}
			}
			return $methods;
		}

		private function currency_config(): array {
			$has_wc = function_exists('get_woocommerce_currency');
			return array(
				'code' => $has_wc ? get_woocommerce_currency() : 'USD',
				'symbol' => $has_wc ? html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8') : '$',
				'position' => $has_wc ? get_option('woocommerce_currency_pos', 'left') : 'left',
				'decimals' => function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2,
				'decimal_separator' => function_exists('wc_get_price_decimal_separator') ? wc_get_price_decimal_separator() : '.',
				'thousand_separator' => function_exists('wc_get_price_thousand_separator') ? wc_get_price_thousand_separator() : ',',
			);
		}

		public function get_config($request) {
			$logo_id = (int) get_theme_mod('custom_logo');
			$has_pro = class_exists('MPTBM_Plugin_Pro');
			$mode = class_exists('MPTBM_Booking_Mode') ? MPTBM_Booking_Mode::get_mode() : '';
			$general = 'mptbm_general_settings';

			$config = array(
				'api_version' => self::API_VERSION,
				'plugin_version' => defined('MPTBM_PLUGIN_VERSION') ? MPTBM_PLUGIN_VERSION : '',
				'site' => array(
					'name' => get_bloginfo('name'),
					'url' => home_url('/'),
					'logo' => $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '',
					'icon' => get_site_icon_url(192),
					'timezone' => wp_timezone_string(),
					'language' => get_locale(),
				),
				'currency' => $this->currency_config(),
				'distance_unit' => 'mile' === MP_Global_Function::get_settings('mp_global_settings', 'km_or_mile', 'km') ? 'mile' : 'km',
				'map' => array(
					'provider' => MP_Global_Function::get_settings('mptbm_map_api_settings', 'display_map', 'openstreetmap'),
					'default_lat' => (float) MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_latitude', '23.81234828905659'),
					'default_lng' => (float) MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_longitude', '90.41069652669002'),
					'country' => MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_country', 'BD'),
					'restrict_to_country' => 'yes' === MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_country_restriction', 'no'),
				),
				'booking' => array(
					'mode' => $mode,
					'minimum_booking_hours' => (float) MP_Global_Function::get_settings($general, 'minimum_booking_hours', 0),
					'max_advance_days' => (int) MP_Global_Function::get_settings($general, 'max_advance_days', 0),
					'return_on_different_date' => 'yes' === MP_Global_Function::get_settings($general, 'enable_return_in_different_date', 'no'),
					'extra_stops' => 'yes' === MP_Global_Function::get_settings($general, 'mptbm_extra_stop_between_pickup_dropoff', 'no'),
					'max_extra_stops' => (int) MP_Global_Function::get_settings($general, 'mptbm_max_extra_stops', 0),
					'show_passengers' => 'no' !== MP_Global_Function::get_settings($general, 'show_number_of_passengers', 'yes'),
					'no_vehicle_message' => (string) MP_Global_Function::get_settings($general, 'no_transport_message', ''),
				),
				'payment' => array(
					'methods' => 'custom' === $mode ? array() : $this->payment_methods(),
					'pay_url_template' => '', // filled in by the checkout endpoints
				),
				'features' => array(
					'pro' => $has_pro,
					'my_bookings' => true,
					'cancel_booking' => $has_pro,
					'modify_booking' => $has_pro,
					'driver_info' => $has_pro,
					'pdf_ticket' => $has_pro && $this->plugin_active('pdf'),
					'tier_pricing' => $this->plugin_active('distance-base-tier-pricing'),
					'peak_hour_pricing' => $this->plugin_active('taxi-peak-hour-pricing'),
				),
				'price_modes' => $this->price_modes(),
				'search_modes' => $this->search_modes(),
				'links' => array(
					'terms' => function_exists('wc_terms_and_conditions_page_id') && wc_terms_and_conditions_page_id() ? get_permalink(wc_terms_and_conditions_page_id()) : '',
					'privacy' => get_privacy_policy_url(),
				),
			);

			return new WP_REST_Response(apply_filters('mptbm_app_config', $config), 200);
		}

		/** Pricing modes in use by published vehicles, so the app shows only the forms that apply. */
		private function price_modes(): array {
			$modes = array();
			foreach ($this->query_vehicles(array('posts_per_page' => -1, 'fields' => 'ids'))->posts as $id) {
				$mode = (string) MP_Global_Function::get_post_info($id, 'mptbm_price_based');
				if ('' !== $mode) {
					$modes[$mode] = true;
				}
			}
			return array_keys($modes);
		}

		/**
		 * The trip types the app can offer, i.e. the search modes the published vehicles are sold
		 * under, with what each one needs from the customer.
		 */
		private function search_modes(): array {
			$modes = array();
			foreach ($this->price_modes() as $model) {
				$mode = MPTBM_App_Quote::search_mode_for_model($model);
				$modes[$mode] = array(
					'id' => $mode,
					'needs_dropoff' => in_array($mode, MPTBM_App_Quote::COORDINATE_MODES, true),
					'needs_duration' => in_array($mode, array('fixed_hourly', 'fixed_daily'), true),
					'duration_unit' => 'fixed_daily' === $mode ? 'day' : ('fixed_hourly' === $mode ? 'hour' : ''),
					'uses_location_list' => in_array($mode, array('manual', 'fixed_route'), true),
				);
			}
			return array_values($modes);
		}

		/* -------------------------------------------------------------------- vehicles */

		private function query_vehicles(array $args = array()): WP_Query {
			return new WP_Query(array_merge(array(
				'post_type' => MPTBM_Function::get_cpt(),
				'post_status' => 'publish',
				'orderby' => array('menu_order' => 'ASC', 'date' => 'DESC'),
				'no_found_rows' => false,
			), $args));
		}

		private function prepare_vehicle(int $id): array {
			$features = array();
			$feature_rows = MP_Global_Function::get_post_info($id, 'mptbm_features', array());
			if (is_array($feature_rows) && 'off' !== MP_Global_Function::get_post_info($id, 'display_mptbm_features', 'on')) {
				foreach ($feature_rows as $row) {
					$text = isset($row['text']) ? trim((string) $row['text']) : '';
					if ('' !== $text) {
						$features[] = array(
							'label' => isset($row['label']) ? wp_strip_all_tags((string) $row['label']) : '',
							'icon' => isset($row['icon']) ? (string) $row['icon'] : '',
							'text' => wp_strip_all_tags($text),
						);
					}
				}
			}

			$thumb_id = get_post_thumbnail_id($id);
			$terms = get_the_terms($id, MPTBM_Function::get_category_slug());
			return array(
				'id' => $id,
				'title' => wp_strip_all_tags(get_the_title($id)),
				'excerpt' => wp_strip_all_tags(get_the_excerpt($id)),
				'description' => wp_kses_post(apply_filters('the_content', get_post_field('post_content', $id))),
				'image' => $thumb_id ? wp_get_attachment_image_url($thumb_id, 'medium_large') : '',
				'image_full' => $thumb_id ? wp_get_attachment_image_url($thumb_id, 'large') : '',
				'max_passengers' => (int) MPTBM_Function::get_feature_passenger($id),
				'max_bags' => (int) MPTBM_Function::get_feature_bag($id),
				'features' => $features,
				'price_based' => (string) MP_Global_Function::get_post_info($id, 'mptbm_price_based'),
				'categories' => ($terms && !is_wp_error($terms)) ? array_map(static function ($term) {
					return array('id' => $term->term_id, 'name' => $term->name, 'slug' => $term->slug);
				}, $terms) : array(),
			);
		}

		public function get_vehicles($request) {
			$args = array(
				'posts_per_page' => (int) $request->get_param('per_page'),
				'paged' => (int) $request->get_param('page'),
				'fields' => 'ids',
			);
			$passengers = (int) $request->get_param('passengers');
			if ($passengers > 0) {
				$args['meta_query'] = array(array('key' => 'mptbm_maximum_passenger', 'value' => $passengers, 'type' => 'NUMERIC', 'compare' => '>='));
			}
			$query = $this->query_vehicles($args);
			$response = new WP_REST_Response(array_map(array($this, 'prepare_vehicle'), $query->posts), 200);
			$response->header('X-WP-Total', (string) $query->found_posts);
			$response->header('X-WP-TotalPages', (string) $query->max_num_pages);
			return $response;
		}

		public function get_vehicle($request) {
			$id = (int) $request['id'];
			if (MPTBM_Function::get_cpt() !== get_post_type($id) || 'publish' !== get_post_status($id)) {
				return new WP_Error('mptbm_app_not_found', esc_html__('Vehicle not found.', 'ecab-taxi-booking-manager'), array('status' => 404));
			}
			return new WP_REST_Response($this->prepare_vehicle($id), 200);
		}

		/* ------------------------------------------------------------------- locations */

		private function location_label(string $value): string {
			if (0 === strpos($value, 'term_')) {
				$term = get_term(absint(substr($value, 5)));
				return ($term && !is_wp_error($term)) ? $term->name : $value;
			}
			return $value;
		}

		public function get_locations($request) {
			$price_based = (string) $request->get_param('price_based');
			$start = sanitize_text_field((string) $request->get_param('start'));
			$values = '' === $start
				? MPTBM_Function::get_all_start_location('', $price_based)
				: MPTBM_Function::get_end_location($start, '', $price_based);
			$items = array();
			foreach (array_values((array) $values) as $value) {
				$items[] = array('value' => (string) $value, 'label' => $this->location_label((string) $value));
			}
			return new WP_REST_Response($items, 200);
		}
	}
	new MPTBM_App_Api();
}
