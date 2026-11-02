<?php
/*
 * Place suggestions for the mobile app: search by text, and reverse-geocode a point.
 *
 * Uses the same Photon (OpenStreetMap) service as the website's address box, because the quote
 * verifies every pick-up / drop-off name against its coordinates with that service
 * (MPTBM_Function::verify_place_coordinates). A name taken from these suggestions therefore
 * passes that check; a name typed freely may not.
 */
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_App_Places')) {
	class MPTBM_App_Places {
		const SEARCH_URL = 'https://photon.komoot.io/api/';
		const REVERSE_URL = 'https://photon.komoot.io/reverse';
		const CACHE_TTL = DAY_IN_SECONDS;

		/** @return array|WP_Error list of ['name', 'lat', 'lng', 'detail'] */
		public static function search(string $query, ?float $bias_lat = null, ?float $bias_lng = null) {
			$query = trim($query);
			if (strlen($query) < 2 || strlen($query) > 120) {
				return new WP_Error('mptbm_app_bad_query', esc_html__('Type at least 2 characters.', 'ecab-taxi-booking-manager'), array('status' => 400));
			}
			$params = array('q' => $query, 'limit' => 6, 'lang' => 'en');
			if (null !== $bias_lat && null !== $bias_lng) {
				$params['lat'] = round($bias_lat, 3); // a soft ranking hint, never a filter
				$params['lon'] = round($bias_lng, 3);
			}
			return self::fetch(self::SEARCH_URL, $params);
		}

		/** @return array|WP_Error the place at a point, as a one-item list (empty when nothing is near) */
		public static function reverse(float $lat, float $lng) {
			return self::fetch(self::REVERSE_URL, array('lat' => round($lat, 5), 'lon' => round($lng, 5), 'lang' => 'en', 'limit' => 1));
		}

		private static function fetch(string $url, array $params) {
			$restrict = 'yes' === MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_country_restriction', 'no');
			$country = strtoupper((string) MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_country', ''));
			$cache_key = 'mptbm_app_place_' . md5($url . wp_json_encode($params) . ($restrict ? $country : ''));
			$cached = get_transient($cache_key);
			if (is_array($cached)) {
				return $cached;
			}

			$response = wp_remote_get($url . '?' . http_build_query($params), array('timeout' => 10, 'headers' => array('Accept' => 'application/json')));
			if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
				return new WP_Error('mptbm_app_places_unavailable', esc_html__('Place search is temporarily unavailable. Please try again.', 'ecab-taxi-booking-manager'), array('status' => 503));
			}
			$data = json_decode(wp_remote_retrieve_body($response), true);
			if (!is_array($data) || !isset($data['features']) || !is_array($data['features'])) {
				return new WP_Error('mptbm_app_places_unavailable', esc_html__('Place search is temporarily unavailable. Please try again.', 'ecab-taxi-booking-manager'), array('status' => 503));
			}

			$places = array();
			$seen = array();
			foreach ($data['features'] as $feature) {
				$properties = isset($feature['properties']) && is_array($feature['properties']) ? $feature['properties'] : array();
				$coordinates = $feature['geometry']['coordinates'] ?? null; // GeoJSON order: lng, lat
				if (!is_array($coordinates) || count($coordinates) < 2 || !is_numeric($coordinates[0]) || !is_numeric($coordinates[1])) {
					continue;
				}
				if ($restrict && $country && strtoupper((string) ($properties['countrycode'] ?? '')) !== $country) {
					continue;
				}
				$name = self::label($properties);
				$key = strtolower($name) . '|' . round((float) $coordinates[1], 3) . '|' . round((float) $coordinates[0], 3);
				if ('' === $name || isset($seen[$key])) {
					continue;
				}
				$seen[$key] = true;
				$places[] = array(
					'name' => $name,
					'lat' => round((float) $coordinates[1], 6),
					'lng' => round((float) $coordinates[0], 6),
					'detail' => self::detail($properties),
				);
			}
			set_transient($cache_key, $places, self::CACHE_TTL);
			return $places;
		}

		/** "Place name, street, city, state, country" without repeated parts. */
		private static function label(array $properties): string {
			$street = trim(((string) ($properties['street'] ?? '')) . ' ' . ((string) ($properties['housenumber'] ?? '')));
			$parts = array(
				$properties['name'] ?? '',
				$street,
				$properties['district'] ?? '',
				$properties['city'] ?? '',
				$properties['state'] ?? '',
				$properties['country'] ?? '',
			);
			$unique = array();
			foreach ($parts as $part) {
				$part = trim(wp_strip_all_tags((string) $part));
				if ('' !== $part && !in_array(strtolower($part), array_map('strtolower', $unique), true)) {
					$unique[] = $part;
				}
			}
			return implode(', ', $unique);
		}

		/** The secondary line shown under a suggestion: everything after the place's own name. */
		private static function detail(array $properties): string {
			$parts = array_filter(array_map(static function ($key) use ($properties) {
				return trim(wp_strip_all_tags((string) ($properties[$key] ?? '')));
			}, array('city', 'state', 'country')));
			return implode(', ', array_unique($parts));
		}
	}
}
