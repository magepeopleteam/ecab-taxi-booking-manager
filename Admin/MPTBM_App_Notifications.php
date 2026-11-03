<?php
/*
 * Admin screen: write a push notification (title, message, optional image) and send it to the mobile app's users.
 */
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_App_Notifications')) {
	class MPTBM_App_Notifications {
		const PAGE = 'mptbm_app_notifications';
		const ACTION = 'mptbm_app_push_send';

		public function __construct() {
			add_action('admin_menu', array($this, 'menu'));
			add_action('admin_post_' . self::ACTION, array($this, 'handle_send'));
		}

		public function menu(): void {
			$cpt = MPTBM_Function::get_cpt();
			add_submenu_page('edit.php?post_type=' . $cpt, esc_html__('App Notifications', 'ecab-taxi-booking-manager'), esc_html__('App Notifications', 'ecab-taxi-booking-manager'), 'manage_options', self::PAGE, array($this, 'page'));
		}

		private function page_url(array $args = array()): string {
			return add_query_arg(array_merge(array('post_type' => MPTBM_Function::get_cpt(), 'page' => self::PAGE), $args), admin_url('edit.php'));
		}

		public function handle_send(): void {
			if (!current_user_can('manage_options')) {
				wp_die(esc_html__('You are not allowed to do this.', 'ecab-taxi-booking-manager'), 403);
			}
			check_admin_referer(self::ACTION);
			$title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
			$body  = isset($_POST['body']) ? sanitize_textarea_field(wp_unslash($_POST['body'])) : '';
			$image = isset($_POST['image']) ? esc_url_raw(wp_unslash($_POST['image'])) : '';
			$test  = !empty($_POST['test_only']);
			$result = MPTBM_App_Push_Broadcast::send($title, $body, $image, $test ? get_current_user_id() : 0);
			if (is_wp_error($result)) {
				set_transient('mptbm_app_push_notice_' . get_current_user_id(), array('error', $result->get_error_message()), 60);
			} else {
				$text = sprintf(
					/* translators: 1: devices reached, 2: devices in total */
					esc_html__('Notification sent to %1$d of %2$d devices.', 'ecab-taxi-booking-manager'),
					$result['sent'],
					$result['total']
				);
				if ('sending' === $result['status']) {
					$text = sprintf(esc_html__('Sending started: %1$d of %2$d devices so far. The rest are sent in the background.', 'ecab-taxi-booking-manager'), $result['sent'], $result['total']);
				}
				set_transient('mptbm_app_push_notice_' . get_current_user_id(), array('success', $text), 60);
			}
			wp_safe_redirect($this->page_url());
			exit;
		}

		public function page(): void {
			if (!current_user_can('manage_options')) {
				return;
			}
			wp_enqueue_media();
			$notice_key = 'mptbm_app_push_notice_' . get_current_user_id();
			$notice     = get_transient($notice_key);
			delete_transient($notice_key);
			$devices = count(MPTBM_App_Push_Broadcast::audience());
			$mine    = count(MPTBM_App_Push_Broadcast::audience(get_current_user_id()));
			$history = MPTBM_App_Push_Broadcast::history();
			?>
			<div class="wrap">
				<h1><?php esc_html_e('App Notifications', 'ecab-taxi-booking-manager'); ?></h1>
				<p><?php
				printf(
					/* translators: %d: number of devices */
					esc_html(_n('%d device is registered to receive notifications.', '%d devices are registered to receive notifications.', $devices, 'ecab-taxi-booking-manager')),
					(int) $devices
				);
				?> <span class="description"><?php esc_html_e('A device registers when a customer signs in to the app and allows notifications.', 'ecab-taxi-booking-manager'); ?></span></p>

				<?php if (is_array($notice) && 2 === count($notice)) : ?>
					<div class="notice notice-<?php echo 'success' === $notice[0] ? 'success' : 'error'; ?> is-dismissible"><p><?php echo esc_html($notice[1]); ?></p></div>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:720px">
					<input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
					<?php wp_nonce_field(self::ACTION); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="mptbm-push-title"><?php esc_html_e('Title', 'ecab-taxi-booking-manager'); ?></label></th>
							<td><input type="text" id="mptbm-push-title" name="title" class="regular-text" maxlength="<?php echo (int) MPTBM_App_Push_Broadcast::TITLE_MAX; ?>" required>
								<p class="description"><?php printf(esc_html__('Up to %d characters.', 'ecab-taxi-booking-manager'), (int) MPTBM_App_Push_Broadcast::TITLE_MAX); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="mptbm-push-body"><?php esc_html_e('Message', 'ecab-taxi-booking-manager'); ?></label></th>
							<td><textarea id="mptbm-push-body" name="body" rows="4" class="large-text" maxlength="<?php echo (int) MPTBM_App_Push_Broadcast::BODY_MAX; ?>" required></textarea>
								<p class="description"><?php printf(esc_html__('Up to %d characters.', 'ecab-taxi-booking-manager'), (int) MPTBM_App_Push_Broadcast::BODY_MAX); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e('Image (optional)', 'ecab-taxi-booking-manager'); ?></th>
							<td>
								<input type="hidden" id="mptbm-push-image" name="image" value="">
								<button type="button" class="button" id="mptbm-push-pick"><?php esc_html_e('Choose image', 'ecab-taxi-booking-manager'); ?></button>
								<button type="button" class="button-link" id="mptbm-push-clear" style="display:none;margin-left:8px"><?php esc_html_e('Remove', 'ecab-taxi-booking-manager'); ?></button>
								<p><img id="mptbm-push-preview" src="" alt="" style="display:none;max-width:320px;height:auto;border:1px solid #ccd0d4;border-radius:4px"></p>
								<p class="description"><?php esc_html_e('Shown in the notification on Android. A wide image (about 2:1, at least 1024 px) works best. The address must be https.', 'ecab-taxi-booking-manager'); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e('Send to', 'ecab-taxi-booking-manager'); ?></th>
							<td>
								<label><input type="checkbox" name="test_only" value="1"> <?php
								printf(esc_html__('Test: send only to my own account (%d device)', 'ecab-taxi-booking-manager'), (int) $mine);
								?></label>
								<p class="description"><?php esc_html_e('Leave unticked to send to every registered device.', 'ecab-taxi-booking-manager'); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button(esc_html__('Send notification', 'ecab-taxi-booking-manager'), 'primary', 'submit', true, array('onclick' => "return confirm('" . esc_js(__('Send this notification now? It cannot be recalled.', 'ecab-taxi-booking-manager')) . "');")); ?>
				</form>

				<h2><?php esc_html_e('Recent notifications', 'ecab-taxi-booking-manager'); ?></h2>
				<table class="widefat striped" style="max-width:960px">
					<thead><tr>
						<th><?php esc_html_e('Sent', 'ecab-taxi-booking-manager'); ?></th>
						<th><?php esc_html_e('Title', 'ecab-taxi-booking-manager'); ?></th>
						<th><?php esc_html_e('Message', 'ecab-taxi-booking-manager'); ?></th>
						<th><?php esc_html_e('Image', 'ecab-taxi-booking-manager'); ?></th>
						<th><?php esc_html_e('Reached', 'ecab-taxi-booking-manager'); ?></th>
						<th><?php esc_html_e('Status', 'ecab-taxi-booking-manager'); ?></th>
					</tr></thead>
					<tbody>
					<?php if (!$history) : ?>
						<tr><td colspan="6"><?php esc_html_e('Nothing sent yet.', 'ecab-taxi-booking-manager'); ?></td></tr>
					<?php endif; ?>
					<?php foreach ($history as $row) : ?>
						<tr>
							<td><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $row['time'])); ?></td>
							<td><?php echo esc_html($row['title']); ?><?php echo 'test' === $row['audience'] ? ' <em>(' . esc_html__('test', 'ecab-taxi-booking-manager') . ')</em>' : ''; ?></td>
							<td><?php echo esc_html($row['body']); ?></td>
							<td><?php echo $row['image'] ? '<img src="' . esc_url($row['image']) . '" alt="" style="width:60px;height:auto;border-radius:3px">' : '&mdash;'; ?></td>
							<td><?php echo (int) $row['sent'] . ' / ' . (int) $row['total']; ?></td>
							<td><?php echo esc_html('done' === $row['status'] ? __('Sent', 'ecab-taxi-booking-manager') : ('sending' === $row['status'] ? __('Sending…', 'ecab-taxi-booking-manager') : __('Failed', 'ecab-taxi-booking-manager'))); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<script>
			jQuery(function ($) {
				var frame;
				$('#mptbm-push-pick').on('click', function (e) {
					e.preventDefault();
					if (!frame) {
						frame = wp.media({ title: <?php echo wp_json_encode(__('Choose notification image', 'ecab-taxi-booking-manager')); ?>, library: { type: 'image' }, multiple: false });
						frame.on('select', function () {
							var a = frame.state().get('selection').first().toJSON();
							var url = (a.sizes && a.sizes.large ? a.sizes.large.url : a.url) || '';
							$('#mptbm-push-image').val(url);
							$('#mptbm-push-preview').attr('src', url).show();
							$('#mptbm-push-clear').show();
						});
					}
					frame.open();
				});
				$('#mptbm-push-clear').on('click', function () {
					$('#mptbm-push-image').val('');
					$('#mptbm-push-preview').hide().attr('src', '');
					$(this).hide();
				});
			});
			</script>
			<?php
		}
	}
	new MPTBM_App_Notifications();
}
