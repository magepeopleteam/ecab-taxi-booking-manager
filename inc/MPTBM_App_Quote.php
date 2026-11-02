<?php
/*
 * Route quote for the mobile app: which vehicles can take this trip, and at what fare.
 *
 * Nothing here prices a trip. The quote runs the website's own search pipeline
 * (MPTBM_Transport_Search::run_search_for_app() -> templates/registration/choose_vehicles.php)
 * so availability, schedules, buffer time, address verification, server-side distance,
 * tier / peak-hour addons and every pricing mode produce exactly the figures the booking
 * form shows. Only the one decision the website leaves to browser JavaScript - whether a
 * vehicle's operation area / geo-fence covers the trip - is evaluated here, in PHP.
 */
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_App_Quote')) {
	class MPTBM_App_Quote {
		const TTL = 30 * MINUTE_IN_SECONDS;
		const MODES = array('dynamic', 'manual', 'fixed_hourly', 'fixed_daily', 'fixed_distance', 'fixed_zone', 'fixed_zone_dropoff', 'fixed_map', 'fixed_route');
		/** Modes priced from the pick-up / drop-off coordinates, so both points are required. */
		const COORDINATE_MODES = array('dynamic', 'fixed_distance', 'fixed_map', 'fixed_zone', 'fixed_zone_dropoff');

		/** Search mode a vehicle's own pricing model is offered under (same mapping the website uses). */
		public static function search_mode_for_model(string $model): string {
			switch ($model) {
				case 'manual':
				case 'fixed_hourly':
				case 'fixed_daily':
				case 'fixed_route':
				case 'fixed_zone':
					return $model;
				case 'fixed_distance':
				case 'fixed_map':
					return 'fixed_map';
				case 'fixed_zone_dropoff':
					return 'fixed_zone_dropoff';
				default:
					return 'dynamic';
			}
		}

		/* ------------------------------------------------------------------- input */

		/** @return array|WP_Error normalised trip */
		private static function normalize(array $in) {
			$bad = static function (string $message) {
				return new WP_Error('mptbm_app_bad_quote', $message, array('status' => 400));
			};
			$mode = isset($in['price_based']) ? sanitize_key((string) $in['price_based']) : 'dynamic';
			if (!in_array($mode, self::MODES, true)) {
				return $bad(esc_html__('Unknown trip type.', 'ecab-taxi-booking-manager'));
			}

			$point = static function ($value): array {
				$value = is_array($value) ? $value : array();
				$lat = isset($value['lat']) ? $value['lat'] : null;
				$lng = isset($value['lng']) ? $value['lng'] : null;
				if (!is_numeric($lat) || !is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
					return array();
				}
				return array(
					'name' => sanitize_text_field((string) ($value['name'] ?? '')),
					'lat' => (float) $lat,
					'lng' => (float) $lng,
				);
			};

			$trip = array('price_based' => $mode, 'start' => $point($in['start'] ?? null), 'end' => $point($in['end'] ?? null), 'stops' => array());
			$needs_end = in_array($mode, self::COORDINATE_MODES, true);
			if (!$trip['start'] || '' === $trip['start']['name'] || ($needs_end && (!$trip['end'] || '' === $trip['end']['name']))) {
				return $bad(esc_html__('Choose a pick-up and drop-off location.', 'ecab-taxi-booking-manager'));
			}
			foreach (array_slice((array) ($in['stops'] ?? array()), 0, 10) as $stop) {
				$stop = $point($stop);
				if ($stop && '' !== $stop['name']) {
					$trip['stops'][] = $stop;
				}
			}

			$date = (string) ($in['date'] ?? '');
			$time = (string) ($in['time'] ?? '');
			if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !wp_checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4), $date)) {
				return $bad(esc_html__('Choose a valid pick-up date.', 'ecab-taxi-booking-manager'));
			}
			if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time)) {
				return $bad(esc_html__('Choose a valid pick-up time.', 'ecab-taxi-booking-manager'));
			}
			$trip['date'] = $date;
			$trip['time'] = $time;

			$trip['return'] = !empty($in['return']);
			$trip['return_date'] = '';
			$trip['return_time'] = '';
			if ($trip['return'] && 'yes' === MP_Global_Function::get_settings('mptbm_general_settings', 'enable_return_in_different_date', 'no')) {
				$return_date = (string) ($in['return_date'] ?? '');
				$return_time = (string) ($in['return_time'] ?? '');
				if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $return_date) || !preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $return_time)) {
					return $bad(esc_html__('Choose a valid return date and time.', 'ecab-taxi-booking-manager'));
				}
				if (strtotime($return_date . ' ' . $return_time) < strtotime($date . ' ' . $time)) {
					return $bad(esc_html__('The return must be after the pick-up.', 'ecab-taxi-booking-manager'));
				}
				$trip['return_date'] = $return_date;
				$trip['return_time'] = $return_time;
			}

			$trip['waiting_time'] = max(0, min(24, (float) ($in['waiting_time'] ?? 0)));
			$trip['fixed_time'] = max(0, min(24 * 30, (float) ($in['fixed_time'] ?? 0)));
			$trip['passengers'] = max(1, min(100, (int) ($in['passengers'] ?? 1)));
			$trip['bags'] = max(0, min(100, (int) ($in['bags'] ?? 0)));
			if (in_array($mode, array('fixed_hourly', 'fixed_daily'), true) && $trip['fixed_time'] <= 0) {
				return $bad('fixed_daily' === $mode ? esc_html__('Choose how many days.', 'ecab-taxi-booking-manager') : esc_html__('Choose how many hours.', 'ecab-taxi-booking-manager'));
			}
			return $trip;
		}

		/** The form time value: hour + minutes/100, e.g. 10:30 -> "10.30". */
		private static function form_time(string $time): string {
			list($hours, $minutes) = array_map('intval', explode(':', $time));
			return sprintf('%.2f', $hours + $minutes / 100);
		}

		/** Fill $_POST the way the booking form does, so the website pipeline reads the same keys. */
		private static function post_fields(array $trip): array {
			$post = array(
				'price_based' => $trip['price_based'],
				'start_place' => $trip['start']['name'],
				'start_place_coordinates' => array('latitude' => $trip['start']['lat'], 'longitude' => $trip['start']['lng']),
				'start_date' => $trip['date'],
				'start_time' => self::form_time($trip['time']),
				'two_way' => $trip['return'] ? 2 : 1,
				'waiting_time' => $trip['waiting_time'],
				'fixed_time' => $trip['fixed_time'],
				'mptbm_passengers' => $trip['passengers'],
			);
			if ($trip['end']) {
				$post['end_place'] = $trip['end']['name'];
				$post['end_place_coordinates'] = array('latitude' => $trip['end']['lat'], 'longitude' => $trip['end']['lng']);
			}
			if ($trip['stops']) {
				$post['mptbm_extra_stop_place'] = array_column($trip['stops'], 'name');
				$post['mptbm_extra_stop_place_coordinates'] = array_map(static function ($stop) {
					return $stop['lat'] . ',' . $stop['lng'];
				}, $trip['stops']);
			}
			if ($trip['return_date']) {
				$post['return_date'] = $trip['return_date'];
				$post['return_time'] = self::form_time($trip['return_time']);
			}
			return $post;
		}

		/* ------------------------------------------------------------------- quote */

		/**
		 * @param array $in Raw request fields (see normalize()).
		 * @return array|WP_Error
		 */
		public static function quote(array $in) {
			$trip = self::normalize($in);
			if (is_wp_error($trip)) {
				return $trip;
			}
			$search = MPTBM_Transport_Search::instance();
			if (!$search) {
				return new WP_Error('mptbm_app_unavailable', esc_html__('Booking is not available right now.', 'ecab-taxi-booking-manager'), array('status' => 503));
			}

			$items = array();
			$collector = static function ($list) use (&$items) {
				$items = is_array($list) ? $list : array();
				return $list;
			};
			$saved_post = $_POST;
			$_POST = self::post_fields($trip);
			$wp_error = null;
			add_filter('mptbm_search_result_items', $collector, PHP_INT_MAX);
			ob_start();
			try {
				$search->run_search_for_app();
			} catch (MPTBM_Search_Exception $e) {
				$wp_error = new WP_Error('mptbm_app_search_failed', $e->getMessage(), array('status' => $e->getCode() ?: 400));
			} finally {
				ob_end_clean(); // the markup is for browsers; the structured items were captured above
				remove_filter('mptbm_search_result_items', $collector, PHP_INT_MAX);
				$_POST = $saved_post;
			}
			// The pipeline starts a PHP session for its geo-fence bookkeeping; the API is stateless.
			if (!headers_sent()) {
				header_remove('Set-Cookie');
			}
			if ($wp_error) {
				return $wp_error;
			}

			$context = MPTBM_Function::get_search_context();
			$vehicles = array();
			foreach ($items as $item) {
				$vehicle = self::prepare_vehicle($item, $trip);
				if ($vehicle) {
					$vehicles[] = $vehicle;
				}
			}

			$quote = array(
				'trip' => array(
					'price_based' => $trip['price_based'],
					'start' => $trip['start'],
					'end' => $trip['end'],
					'stops' => $trip['stops'],
					'date' => $trip['date'],
					'time' => $trip['time'],
					'return' => $trip['return'],
					'return_date' => $trip['return_date'],
					'return_time' => $trip['return_time'],
					'waiting_time' => $trip['waiting_time'],
					'fixed_time' => $trip['fixed_time'],
					'passengers' => $trip['passengers'],
					'distance' => (int) ($context['distance'] ?? 0),
					'duration' => (int) ($context['duration'] ?? 0),
					'distance_text' => !empty($context['distance']) ? MPTBM_Function::format_distance_text($context['distance']) : '',
					'duration_text' => !empty($context['duration']) ? MPTBM_Function::format_duration_text($context['duration']) : '',
					'distance_verified' => !empty($context['distance_verified']),
				),
				'vehicles' => $vehicles,
				'no_vehicle_message' => $vehicles ? '' : wp_strip_all_tags((string) MP_Global_Function::get_settings('mptbm_general_settings', 'no_transport_message', esc_html__('No Transport Available !', 'ecab-taxi-booking-manager'))),
			);

			$quote_id = wp_generate_password(32, false, false);
			set_transient('mptbm_app_quote_' . $quote_id, array('trip' => $trip, 'vehicles' => array_column($vehicles, 'price', 'id'), 'created' => time()), self::TTL);
			$quote['quote_id'] = $quote_id;
			$quote['expires_in'] = self::TTL;
			return $quote;
		}

		/** @return array|null null when the vehicle cannot take this trip */
		private static function prepare_vehicle(array $item, array $trip) {
			$id = (int) $item['post_id'];
			if (!self::area_covers_trip($id, $trip)) {
				return null;
			}
			$max_passengers = (int) get_post_meta($id, 'mptbm_maximum_passenger', true);
			$max_bags = (int) get_post_meta($id, 'mptbm_maximum_bag', true);
			if (($max_passengers && $trip['passengers'] > $max_passengers) || ($max_bags && $trip['bags'] > $max_bags)) {
				return null;
			}

			$fare = isset($item['fare']) && is_array($item['fare']) ? $item['fare'] : array();
			$display_type = $fare['display_type'] ?? 'normal';
			$transport = (float) ($fare['transport'] ?? 0);
			$base = (float) ($fare['base_location'] ?? 0);
			$stops = (float) ($fare['stops'] ?? 0);
			// The geo-fence surcharge is already inside the transport figure: get_price() adds it.
			$total = 'normal' === $display_type ? $transport + $base + $stops : 0.0;

			$thumb = get_post_thumbnail_id($id);
			return array(
				'id' => $id,
				'title' => wp_strip_all_tags(get_the_title($id)),
				'image' => $thumb ? wp_get_attachment_image_url($thumb, 'medium_large') : '',
				'max_passengers' => $max_passengers,
				'max_bags' => $max_bags,
				'rating' => round((float) ($item['rating'] ?? 0), 1),
				'available' => empty($fare['unavailable']),
				'unavailable_reason' => wp_strip_all_tags((string) ($fare['unavailable_reason'] ?? '')),
				'price' => array(
					'display_type' => $display_type,
					'custom_message' => wp_strip_all_tags((string) ($fare['custom_message'] ?? '')),
					'transport' => round($transport, 4),
					'base_location' => round($base, 4),
					'stops' => round($stops, 4),
					'total' => round($total, 4),
				),
			);
		}

		/* -------------------------------------------------- operation area / geo-fence */

		/** @param array $polygon list of ['latitude' => , 'longitude' => ] */
		private static function in_polygon(array $point, array $polygon): bool {
			$x = $point['lat'];
			$y = $point['lng'];
			$inside = false;
			$n = count($polygon);
			for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
				$xi = $polygon[$i]['latitude'];
				$yi = $polygon[$i]['longitude'];
				$xj = $polygon[$j]['latitude'];
				$yj = $polygon[$j]['longitude'];
				if ((($yi > $y) !== ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi + 0.0000001) + $xi)) {
					$inside = !$inside;
				}
			}
			return $inside;
		}

		/** An area polygon stored as a flat lat,lng,lat,lng... list in post meta. */
		private static function polygon(int $area_id, string $key): array {
			$flat = get_post_meta($area_id, $key, true);
			$polygon = array();
			if (is_array($flat)) {
				for ($i = 0; $i + 1 < count($flat); $i += 2) {
					$polygon[] = array('latitude' => (float) $flat[$i], 'longitude' => (float) $flat[$i + 1]);
				}
			}
			return $polygon;
		}

		private static function coordinate_key(string $area_type): string {
			if ('geo-matched-operation-area-type' === $area_type) {
				return 'mptbm-coordinates-four';
			}
			return 'geo-fence-operation-area-type' === $area_type ? 'mptbm-coordinates-one' : 'mptbm-coordinates-three';
		}

		/** @return string 'full' | 'partial' | '' */
		private static function fixed_distance_match(int $area_id, array $trip): string {
			$polygon = self::polygon($area_id, self::coordinate_key((string) get_post_meta($area_id, 'mptbm-operation-type', true)));
			if (!$polygon || !$trip['end']) {
				return '';
			}
			$start_in = self::in_polygon($trip['start'], $polygon);
			$end_in = self::in_polygon($trip['end'], $polygon);
			return $start_in && $end_in ? 'full' : ($start_in || $end_in ? 'partial' : '');
		}

		/** Intercity geo-fence: two polygons, either one-direction or both. */
		private static function fence_covers(int $area_id, array $trip): bool {
			$one = self::polygon($area_id, 'mptbm-coordinates-one');
			$two = self::polygon($area_id, 'mptbm-coordinates-two');
			if (!$one || !$two || !$trip['end']) {
				return false;
			}
			$s1 = self::in_polygon($trip['start'], $one);
			$s2 = self::in_polygon($trip['start'], $two);
			$e1 = self::in_polygon($trip['end'], $one);
			$e2 = self::in_polygon($trip['end'], $two);
			if ('geo-fence-one-direction' === get_post_meta($area_id, 'mptbm-geo-fence-direction', true)) {
				return ($s1 && $e2) || ($s1 && $e1);
			}
			return ($s1 && $e2) || ($s2 && $e1) || ($s1 && $e1) || ($s2 && $e2);
		}

		/** Operation area of an area-type that is not an intercity fence: both points inside. */
		private static function both_inside(int $area_id, array $trip): bool {
			$polygon = self::polygon($area_id, 'mptbm-coordinates-three');
			return $polygon && $trip['end'] && self::in_polygon($trip['start'], $polygon) && self::in_polygon($trip['end'], $polygon);
		}

		/**
		 * Whether the vehicle's operation area(s) cover this trip. Mirrors wptbm_get_schedule() and
		 * mptbm_check_transport_area_geo_fence() in choose_vehicles.php, whose "show this vehicle"
		 * step is browser JavaScript on the website.
		 */
		public static function area_covers_trip(int $vehicle_id, array $trip): bool {
			$mode = $trip['price_based'];
			$areas = get_post_meta($vehicle_id, 'mptbm_selected_operation_areas', true);
			$distance_mode = in_array($mode, array('fixed_distance', 'fixed_map'), true);
			if (!$areas || in_array($mode, array('manual', 'fixed_zone', 'fixed_zone_dropoff'), true)) {
				return !$distance_mode; // distance-matched vehicles need an area; the rest are not area-bound
			}

			$vehicle_type = (string) get_post_meta($vehicle_id, 'mptbm_operation_area_type', true);
			$inclusive_dynamic = ('dynamic' === $mode && 'inclusive' === get_post_meta($vehicle_id, 'mptbm_price_based', true));

			if (!is_array($areas)) { // a single selected area
				if ($distance_mode) {
					$match = self::fixed_distance_match((int) $areas, $trip);
					return '' !== $match && !('fixed_map' === $mode && 'fixed-operation-area-type' === $vehicle_type && 'full' !== $match);
				}
				return 'fixed-operation-area-type' === get_post_meta((int) $areas, 'mptbm-operation-type', true)
					? self::both_inside((int) $areas, $trip)
					: self::fence_covers((int) $areas, $trip);
			}

			foreach ($areas as $area_id) {
				$area_id = (int) $area_id;
				$area_type = (string) get_post_meta($area_id, 'mptbm-operation-type', true);
				if ('fixed-map-operation-area-type' === $vehicle_type && ('fixed_map' === $mode || $inclusive_dynamic)) {
					$polygon = self::polygon($area_id, self::coordinate_key($area_type));
					if ($polygon && self::in_polygon($trip['start'], $polygon)) {
						return true;
					}
					continue;
				}
				if ($distance_mode) {
					$match = self::fixed_distance_match($area_id, $trip);
					if ('' !== $match && !('fixed_map' === $mode && 'fixed-operation-area-type' === $vehicle_type && 'full' !== $match)) {
						return true;
					}
					continue;
				}
				if ('geo-matched-operation-area-type' === $vehicle_type) {
					$polygon = self::polygon($area_id, 'mptbm-coordinates-three');
					if ($polygon && (self::in_polygon($trip['start'], $polygon) || ($trip['end'] && self::in_polygon($trip['end'], $polygon)))) {
						return true;
					}
				} elseif ('geo-fence-operation-area-type' === $area_type) {
					if (self::fence_covers($area_id, $trip)) {
						return true;
					}
				} elseif (self::both_inside($area_id, $trip)) {
					return true;
				}
			}
			return false;
		}
	}
}
