<?php
/*
 * Admin-written push notifications ("broadcasts") for the mobile app.
 *
 * An admin writes a title, a message and an optional image; every device that has registered an Expo push token
 * (see MPTBM_App_Push) receives it. The first batch is sent while the admin waits so the screen can report the result;
 * anything beyond that is sent in the background by WP-Cron, 500 devices per run, so a big audience never times a page out.
 * The last broadcasts are kept as a short history. Devices Expo reports as gone are dropped, as for booking messages.
 */
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_App_Push_Broadcast')) {
	class MPTBM_App_Push_Broadcast {
		const HISTORY_OPTION = 'mptbm_app_push_broadcasts';
		const PENDING_PREFIX = 'mptbm_app_push_pending_';
		const CRON_HOOK = 'mptbm_app_push_broadcast_continue';
		const BATCH = 100;           // Expo accepts at most 100 messages per request.
		const BATCHES_PER_RUN = 5;   // per cron run
		const HISTORY_LIMIT = 30;
		const TITLE_MAX = 65;
		const BODY_MAX = 240;

		public static function init(): void {
			add_action(self::CRON_HOOK, array(__CLASS__, 'continue_sending'), 10, 1);
		}

		/* ------------------------------------------------------------ audience */

		/** @return array<int, array{user:int, token:string}> every registered device, one entry per token */
		public static function audience(int $only_user = 0): array {
			$args = array('meta_key' => MPTBM_App_Push::META, 'meta_compare' => 'EXISTS', 'fields' => 'ID', 'number' => -1);
			if ($only_user) {
				$args['include'] = array($only_user);
			}
			$devices = array();
			foreach (get_users($args) as $user_id) {
				foreach (array_keys(MPTBM_App_Push::tokens((int) $user_id)) as $token) {
					$devices[$token] = array('user' => (int) $user_id, 'token' => (string) $token);
				}
			}
			return array_values($devices);
		}

		/* ------------------------------------------------------------ sending */

		/**
		 * @return array|WP_Error the history entry for the broadcast that was started
		 */
		public static function send(string $title, string $body, string $image = '', int $only_user = 0) {
			$title = trim(wp_strip_all_tags($title));
			$body  = trim(wp_strip_all_tags($body));
			$image = trim($image);
			if ('' === $title || '' === $body) {
				return new WP_Error('mptbm_broadcast_empty', esc_html__('Please enter both a title and a message.', 'ecab-taxi-booking-manager'));
			}
			if (mb_strlen($title) > self::TITLE_MAX || mb_strlen($body) > self::BODY_MAX) {
				return new WP_Error('mptbm_broadcast_long', esc_html__('The title or the message is too long.', 'ecab-taxi-booking-manager'));
			}
			if ('' !== $image) {
				$image = esc_url_raw($image, array('https'));
				if ('' === $image) {
					return new WP_Error('mptbm_broadcast_image', esc_html__('The image must be a secure (https) address. Choose it from the media library.', 'ecab-taxi-booking-manager'));
				}
			}
			$devices = self::audience($only_user);
			if (!$devices) {
				return new WP_Error('mptbm_broadcast_nobody', $only_user
					? esc_html__('Your own account has no registered device yet. Sign in to the app on a phone with this admin account first, or send to everyone.', 'ecab-taxi-booking-manager')
					: esc_html__('No devices have registered for notifications yet. Customers appear here after they sign in to the app with notifications allowed.', 'ecab-taxi-booking-manager'));
			}

			$id    = time() . wp_rand(100, 999);
			$entry = array(
				'id'       => $id,
				'time'     => time(),
				'title'    => $title,
				'body'     => $body,
				'image'    => $image,
				'audience' => $only_user ? 'test' : 'all',
				'total'    => count($devices),
				'sent'     => 0,
				'failed'   => 0,
				'status'   => 'sending',
			);
			self::save_entry($entry);
			update_option(self::PENDING_PREFIX . $id, $devices, false);

			$entry = self::run($id, 1); // the first batch while the admin waits
			if ('sending' === $entry['status']) {
				wp_schedule_single_event(time() + 5, self::CRON_HOOK, array($id));
			}
			return $entry;
		}

		/** WP-Cron callback: send the next few batches of an unfinished broadcast. */
		public static function continue_sending($id): void {
			self::run((int) $id, self::BATCHES_PER_RUN);
			$entry = self::entry((int) $id);
			if ($entry && 'sending' === $entry['status']) {
				wp_schedule_single_event(time() + 30, self::CRON_HOOK, array((int) $id));
			}
		}

		/** Send up to $batches batches of what is left and update the history. @return array the history entry */
		private static function run(int $id, int $batches): array {
			$entry   = self::entry($id) ?: array();
			$pending = get_option(self::PENDING_PREFIX . $id, array());
			if (!$entry || !is_array($pending)) {
				return $entry;
			}
			for ($i = 0; $i < $batches && $pending; $i++) {
				$chunk   = array_splice($pending, 0, self::BATCH);
				$result  = self::send_chunk($chunk, $entry);
				$entry['sent']   += $result['sent'];
				$entry['failed'] += $result['failed'];
			}
			if ($pending) {
				update_option(self::PENDING_PREFIX . $id, $pending, false);
			} else {
				delete_option(self::PENDING_PREFIX . $id);
				$entry['status'] = ($entry['sent'] > 0 || 0 === $entry['failed']) ? 'done' : 'failed';
			}
			self::save_entry($entry);
			return $entry;
		}

		/** @return array{sent:int, failed:int} */
		private static function send_chunk(array $devices, array $entry): array {
			$messages = array();
			foreach ($devices as $device) {
				$message = array(
					'to'        => $device['token'],
					'title'     => $entry['title'],
					'body'      => $entry['body'],
					'sound'     => 'default',
					'channelId' => 'default',
					'ttl'       => 3 * DAY_IN_SECONDS,
					'priority'  => 'high',
					'data'      => array('type' => 'broadcast', 'broadcast_id' => (int) $entry['id']),
				);
				if ('' !== $entry['image']) {
					$message['richContent'] = array('image' => $entry['image']);
				}
				$messages[] = $message;
			}
			$response = wp_remote_post(MPTBM_App_Push::ENDPOINT, array(
				'timeout' => 20,
				'headers' => array('Accept' => 'application/json', 'Content-Type' => 'application/json'),
				'body'    => wp_json_encode($messages),
			));
			if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
				return array('sent' => 0, 'failed' => count($devices));
			}
			$tickets = (array) (json_decode(wp_remote_retrieve_body($response), true)['data'] ?? array());
			$sent    = 0;
			$failed  = 0;
			foreach ($devices as $index => $device) {
				$ticket = $tickets[$index] ?? array();
				if ('ok' === ($ticket['status'] ?? '')) {
					++$sent;
					continue;
				}
				++$failed;
				if ('DeviceNotRegistered' === ($ticket['details']['error'] ?? '')) {
					MPTBM_App_Push::remove($device['user'], $device['token']);
				}
			}
			return array('sent' => $sent, 'failed' => $failed);
		}

		/* ------------------------------------------------------------- history */

		public static function history(): array {
			$history = get_option(self::HISTORY_OPTION, array());
			return is_array($history) ? $history : array();
		}

		public static function entry(int $id): ?array {
			foreach (self::history() as $entry) {
				if ((int) ($entry['id'] ?? 0) === $id) {
					return $entry;
				}
			}
			return null;
		}

		private static function save_entry(array $entry): void {
			$history = array_filter(self::history(), static function ($old) use ($entry) {
				return (int) ($old['id'] ?? 0) !== (int) $entry['id'];
			});
			array_unshift($history, $entry);
			update_option(self::HISTORY_OPTION, array_slice(array_values($history), 0, self::HISTORY_LIMIT), false);
		}
	}
	MPTBM_App_Push_Broadcast::init();
}
