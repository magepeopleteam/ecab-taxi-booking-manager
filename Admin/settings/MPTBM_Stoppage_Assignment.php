<?php
/*
 * Per-vehicle Stoppage assignment - rendered by MPTBM_Rent_Custom_Editor.php
 * directly inside the "Fees & Extra Service" tab, in its own card right
 * below "Customer Add-ons" (self::render() is called from there - this class
 * only owns the render markup, the AJAX picker, and saving; it doesn't hook
 * its own rendering into the tab like the older settings screens do, because
 * that tab is built inline in the custom editor rather than through the
 * add_mptbm_settings_tab_content action).
 *
 * A vehicle just stores which globally-created mptbm_stoppages posts it
 * offers (mptbm_stoppage_ids) plus an on/off flag (display_mptbm_stoppages) -
 * the stoppages themselves are managed once, globally, in
 * Admin/MPTBM_Stoppages_Manager.php.
 */
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_Stoppage_Assignment')) {
	class MPTBM_Stoppage_Assignment {
		const POST_TYPE = 'mptbm_stoppages';
		const PER_PAGE = 10;

		public function __construct() {
			add_action('save_post', [ $this, 'save_settings' ]);
			add_action('admin_enqueue_scripts', [ $this, 'enqueue_assets' ]);
			add_action('wp_ajax_mptbm_load_stoppage_picker', [ $this, 'ajax_load_picker' ]);
		}

		public function enqueue_assets($hook): void {
			// Only needed on the vehicle add/edit screen, where the Fees & Extra
			// Service tab (and this section within it) actually renders.
			if (!in_array($hook, [ 'post.php', 'post-new.php' ], true)) {
				return;
			}
			$screen = function_exists('get_current_screen') ? get_current_screen() : null;
			if (!$screen || $screen->post_type !== MPTBM_Function::get_cpt()) {
				return;
			}

			$css_path = MPTBM_PLUGIN_DIR . '/assets/admin/mptbm_stoppage_assignment.css';
			$js_path = MPTBM_PLUGIN_DIR . '/assets/admin/mptbm_stoppage_assignment.js';

			wp_enqueue_style(
				'mptbm-stoppage-assignment',
				MPTBM_PLUGIN_URL . '/assets/admin/mptbm_stoppage_assignment.css',
				[ 'mptbm-shell' ],
				file_exists($css_path) ? filemtime($css_path) : MPTBM_PLUGIN_VERSION
			);
			wp_enqueue_script(
				'mptbm-stoppage-assignment',
				MPTBM_PLUGIN_URL . '/assets/admin/mptbm_stoppage_assignment.js',
				[ 'jquery' ],
				file_exists($js_path) ? filemtime($js_path) : MPTBM_PLUGIN_VERSION,
				true
			);
			wp_localize_script(
				'mptbm-stoppage-assignment',
				'mptbmStoppageAssignment',
				[
					'ajaxUrl' => admin_url('admin-ajax.php'),
					'nonce'   => wp_create_nonce('mptbm_stoppage_assignment_nonce'),
					'action'  => 'mptbm_load_stoppage_picker',
					'loadError' => esc_html__('Could not load stoppages. Please try again.', 'ecab-taxi-booking-manager'),
					'loadMoreLabel' => esc_html__('Load more', 'ecab-taxi-booking-manager'),
					'loadingLabel' => esc_html__('Loading…', 'ecab-taxi-booking-manager'),
				]
			);
		}

		/**
		 * Called directly from MPTBM_Rent_Custom_Editor.php - the toggle switch
		 * itself is rendered there (matching the "Customer Add-ons" card right
		 * above it); this only renders the body: the assignable stoppage grid.
		 */
		public static function render($post_id, bool $is_enabled): void {
			$assigned_ids = get_post_meta($post_id, 'mptbm_stoppage_ids', true);
			$assigned_ids = is_array($assigned_ids) ? array_values(array_map('absint', $assigned_ids)) : [];
			$total_stoppages = wp_count_posts(self::POST_TYPE)->publish ?? 0;

			// First page renders straight into the page's own HTML - no AJAX
			// round-trip needed just to show what's already known at render time.
			// AJAX (ajax_load_picker(), same query/ordering) only kicks in for
			// "Load more" onward, starting at offset PER_PAGE.
			$first_page_html = '';
			if ((int) $total_stoppages > 0) {
				$first_page = new WP_Query([
					'post_type'      => self::POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => self::PER_PAGE,
					'offset'         => 0,
					'orderby'        => 'title',
					'order'          => 'ASC',
				]);
				foreach ($first_page->posts as $post) {
					$first_page_html .= self::render_picker_card($post, $assigned_ids);
				}
			}
			?>
			<div class="mptbm_taxi_ex_service_body<?php echo $is_enabled ? '' : ' mptbm_disabled'; ?>" id="mptbm_taxi_stoppage_body" style="display: <?php echo $is_enabled ? 'block' : 'none'; ?>">
				<input type="hidden" name="mptbm_stoppage_ids_json" data-stoppage-assigned-field value="<?php echo esc_attr(wp_json_encode($assigned_ids)); ?>" />

				<?php if ((int) $total_stoppages === 0) : ?>
					<p class="mptbm-stoppage-picker-empty">
						<?php
						printf(
							/* translators: link to the global Stoppages screen */
							esc_html__('No stoppages yet. Create some first in %s.', 'ecab-taxi-booking-manager'),
							'<a href="' . esc_url(admin_url('edit.php?post_type=mptbm_stoppages')) . '" target="_blank" rel="noopener">' . esc_html__('Transportation → Stoppages', 'ecab-taxi-booking-manager') . '</a>'
						);
						?>
					</p>
				<?php else : ?>
					<p class="mptbm-stoppage-picker-hint"><?php esc_html_e('Click a card to assign/unassign it. Only assigned stoppages are offered to customers of this vehicle.', 'ecab-taxi-booking-manager'); ?></p>
					<div class="mptbm-stoppage-picker" data-stoppage-picker data-assigned="<?php echo esc_attr(wp_json_encode($assigned_ids)); ?>" data-offset="<?php echo esc_attr(self::PER_PAGE); ?>" data-per-page="<?php echo esc_attr(self::PER_PAGE); ?>">
						<div class="mptbm-stoppage-picker-grid" data-stoppage-picker-grid><?php echo $first_page_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						<button type="button" class="button mptbm-stoppage-load-more" data-stoppage-load-more <?php echo $total_stoppages > self::PER_PAGE ? '' : 'style="display:none;"'; ?>>
							<?php esc_html_e('Load more', 'ecab-taxi-booking-manager'); ?>
						</button>
					</div>
				<?php endif; ?>
			</div>
			<?php
		}

		/**
		 * Same nonce as the rest of the vehicle edit form
		 * (MPTBM_Extra_Service::save_ex_service() uses the identical check) -
		 * this section is part of the same single form, not a separate one.
		 */
		public function save_settings($post_id) {
			if (get_post_type($post_id) !== MPTBM_Function::get_cpt()
				|| (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
				|| wp_is_post_revision($post_id)
				|| !current_user_can('edit_post', $post_id)
				|| !isset($_POST['mptbm_transportation_type_nonce'])
				|| !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mptbm_transportation_type_nonce'])), 'mptbm_transportation_type_nonce')) {
				return;
			}

			// The hidden field is only posted when this panel actually rendered as
			// part of the submitted form - without this guard, any other save_post
			// on this CPT (without the field) would silently wipe assignments.
			if (!isset($_POST['mptbm_stoppage_ids_json'])) {
				return;
			}

			$display = (isset($_POST['display_mptbm_stoppages']) && sanitize_text_field(wp_unslash($_POST['display_mptbm_stoppages']))) ? 'on' : 'off';
			update_post_meta($post_id, 'display_mptbm_stoppages', $display);

			$decoded = json_decode(wp_unslash($_POST['mptbm_stoppage_ids_json']), true);
			$posted_ids = is_array($decoded) ? array_values(array_unique(array_map('absint', $decoded))) : [];

			// Only IDs that are real, published mptbm_stoppages posts survive -
			// never trust the client for anything beyond "which ids were clicked".
			$valid_ids = array_values(array_filter($posted_ids, function ($id) {
				$post = get_post($id);
				return $post && $post->post_type === self::POST_TYPE && $post->post_status === 'publish';
			}));

			update_post_meta($post_id, 'mptbm_stoppage_ids', $valid_ids);
		}

		private static function render_picker_card(WP_Post $post, array $assigned_ids): string {
			$is_assigned = in_array($post->ID, $assigned_ids, true);
			$image_id = (int) get_post_meta($post->ID, 'mptbm_stoppage_image', true);
			// 'thumbnail' can come back empty for attachments whose intermediate
			// sizes were never regenerated (e.g. uploaded before this feature
			// existed) - wp_get_attachment_image_url() does NOT fall back to the
			// full image the way wp_get_attachment_image_src() does, so chain a
			// couple of sizes manually instead of trusting 'thumbnail' alone.
			$image_url = '';
			if ($image_id) {
				foreach ([ 'thumbnail', 'medium', 'full' ] as $size) {
					$image_url = wp_get_attachment_image_url($image_id, $size);
					if ($image_url) {
						break;
					}
				}
			}
			$duration = MPTBM_Function::format_duration_minutes(get_post_meta($post->ID, 'mptbm_stoppage_duration', true));
			$price = get_post_meta($post->ID, 'mptbm_stoppage_price', true);

			ob_start();
			?>
			<div class="mptbm-stoppage-pick-card<?php echo $is_assigned ? ' is-assigned' : ''; ?>" data-stoppage-pick-card data-id="<?php echo esc_attr($post->ID); ?>" role="button" tabindex="0" style="display:flex;flex-direction:column;text-align:left;padding:0;overflow:hidden;cursor:pointer;">
				<span class="mptbm-stoppage-pick-media" style="position:relative;display:flex;align-items:center;justify-content:center;height:84px;min-height:84px;color:#94a3b8;<?php echo $image_url ? 'background:center/cover no-repeat url(' . esc_url($image_url) . ');' : 'background:#f1f5f9;'; ?>">
					<?php if (!$image_url) : ?><i class="fas fa-image" aria-hidden="true"></i><?php endif; ?>
					<span class="mptbm-stoppage-pick-check" aria-hidden="true"><i class="fas fa-check"></i></span>
				</span>
				<span class="mptbm-stoppage-pick-name"><?php echo esc_html($post->post_title); ?></span>
				<span class="mptbm-stoppage-pick-meta">
					<?php if ($duration) : ?><?php echo esc_html($duration); ?> · <?php endif; ?>
					<?php echo $price !== '' ? wp_kses_post(MP_Global_Function::format_price((float) $price)) : esc_html__('Free', 'ecab-taxi-booking-manager'); ?>
				</span>
			</div>
			<?php
			return (string) ob_get_clean();
		}

		public function ajax_load_picker(): void {
			check_ajax_referer('mptbm_stoppage_assignment_nonce', 'nonce');
			if (!current_user_can('manage_mptbm_transportation')) {
				wp_send_json_error([ 'message' => esc_html__('You do not have permission to do this.', 'ecab-taxi-booking-manager') ], 403);
			}

			$offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
			$assigned = isset($_POST['assigned']) ? array_map('absint', (array) json_decode(wp_unslash($_POST['assigned']), true)) : [];

			$query = new WP_Query([
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => self::PER_PAGE,
				'offset'         => $offset,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => false,
			]);

			$html = '';
			foreach ($query->posts as $post) {
				$html .= self::render_picker_card($post, $assigned);
			}

			wp_send_json_success([
				'html'    => $html,
				'hasMore' => ($offset + self::PER_PAGE) < (int) $query->found_posts,
			]);
		}
	}

	new MPTBM_Stoppage_Assignment();
}
