<?php
	/*
	 * @Author 		engr.sumonazma@gmail.com
	 * Copyright: 	mage-people.com
	 */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('MPTBM_Reviews')) {
		class MPTBM_Reviews {
			const COMMENT_TYPE = 'mptbm_review';

			public function __construct() {
				add_action('mptbm_after_details_page', [$this, 'render_reviews_section']);
				add_action('wp_ajax_mptbm_submit_review', [$this, 'submit_review']);
				add_action('wp_ajax_nopriv_mptbm_submit_review', [$this, 'submit_review_guest']);
				add_action('wp_ajax_mptbm_admin_delete_review', [$this, 'admin_delete_review']);
				add_action('wp_ajax_mptbm_admin_load_reviews', [$this, 'admin_load_reviews']);
				add_action('woocommerce_order_details_after_order_table', [$this, 'maybe_show_review_prompt']);
			}

			// Per-vehicle "Customer Reviews" toggle (Admin > Edit Rent > General), default off.
			public static function reviews_enabled($post_id) {
				return MP_Global_Function::get_post_info($post_id, 'mptbm_show_reviews', 'no') === 'yes';
			}

			public static function get_vehicle_reviews($post_id) {
				return get_comments([
					'post_id' => $post_id,
					'type'    => self::COMMENT_TYPE,
					'status'  => 'approve',
					'order'   => 'DESC',
				]);
			}

			public static function get_average_rating($post_id) {
				$reviews = self::get_vehicle_reviews($post_id);
				$count = count($reviews);
				if (!$count) {
					return ['average' => 0, 'count' => 0];
				}
				$total = 0;
				foreach ($reviews as $review) {
					$total += (int) get_comment_meta($review->comment_ID, 'rating', true);
				}
				return ['average' => round($total / $count, 1), 'count' => $count];
			}

			// Small stars + count badge, safe to echo anywhere a vehicle is listed (e.g. search results card).
			public static function get_rating_html($post_id) {
				if (!self::reviews_enabled($post_id)) {
					return '';
				}
				$data = self::get_average_rating($post_id);
				if ($data['count'] === 0) {
					return '';
				}
				ob_start();
				?>
				<div class="mptbm_rating_summary" style="display:flex;align-items:center;gap:4px;margin:2px 0;">
					<span style="color:#f5a623;font-size:13px;letter-spacing:1px;">
						<?php for ($i = 1; $i <= 5; $i++) { echo ($i <= round($data['average'])) ? '&#9733;' : '&#9734;'; } ?>
					</span>
					<span style="font-size:12px;color:#666;"><?php echo esc_html($data['average']); ?> (<?php echo esc_html($data['count']); ?>)</span>
				</div>
				<?php
				return ob_get_clean();
			}

			// Customer's most recent COMPLETED booking for this vehicle - required to be allowed to review.
			// Only the order status matters; the trip date is not checked (an admin marking the order
			// Completed is treated as confirmation the trip happened).
			public static function find_eligible_booking($user_id, $vehicle_id) {
				if (!$user_id) {
					return null;
				}
				$bookings = get_posts([
					'post_type'      => 'mptbm_booking',
					'posts_per_page' => -1,
					'meta_query'     => [
						['key' => 'mptbm_id', 'value' => $vehicle_id, 'compare' => '='],
						['key' => 'mptbm_order_status', 'value' => 'completed', 'compare' => '='],
					],
				]);
				foreach ($bookings as $booking) {
					$order_id = get_post_meta($booking->ID, 'mptbm_order_id', true);
					$order = $order_id ? wc_get_order($order_id) : false;
					if (!$order || (int) $order->get_customer_id() !== (int) $user_id) {
						continue;
					}
					return $booking;
				}
				return null;
			}

			public static function has_already_reviewed($user_id, $vehicle_id) {
				$existing = get_comments([
					'post_id' => $vehicle_id,
					'type'    => self::COMMENT_TYPE,
					'user_id' => $user_id,
					'count'   => true,
				]);
				return $existing > 0;
			}

			public function render_reviews_section($post_id) {
				if (get_post_type($post_id) !== MPTBM_Function::get_cpt()) {
					return;
				}
				if (!self::reviews_enabled($post_id)) {
					return;
				}
				$reviews = self::get_vehicle_reviews($post_id);
				$rating = self::get_average_rating($post_id);
				$user_id = get_current_user_id();
				$eligible_booking = $user_id ? self::find_eligible_booking($user_id, $post_id) : null;
				$already_reviewed = $user_id ? self::has_already_reviewed($user_id, $post_id) : false;
				?>
				<div id="mptbm-reviews" class="mptbm_reviews_section" style="margin-top:30px;padding-top:20px;border-top:1px solid #e1e5e9;">
					<h3>
						<?php esc_html_e('Customer Reviews', 'ecab-taxi-booking-manager'); ?>
						<?php if ($rating['count'] > 0) { ?>
							<span style="font-size:14px;color:#666;font-weight:normal;">
								(<?php echo esc_html($rating['average']); ?>/5 - <?php echo esc_html($rating['count']); ?> <?php esc_html_e('reviews', 'ecab-taxi-booking-manager'); ?>)
							</span>
						<?php } ?>
					</h3>

					<?php if (empty($reviews)) { ?>
						<p><?php esc_html_e('No reviews yet.', 'ecab-taxi-booking-manager'); ?></p>
					<?php } else {
						foreach ($reviews as $review) {
							$stars = (int) get_comment_meta($review->comment_ID, 'rating', true);
							?>
							<div class="mptbm_review_item" style="margin-bottom:15px;padding:12px;background:#f9f9f9;border-radius:6px;">
								<div style="color:#f5a623;">
									<?php for ($i = 1; $i <= 5; $i++) { echo ($i <= $stars) ? '&#9733;' : '&#9734;'; } ?>
								</div>
								<strong><?php echo esc_html($review->comment_author); ?></strong>
								<span style="color:#999;font-size:12px;"> &mdash; <?php echo esc_html(mysql2date(get_option('date_format'), $review->comment_date)); ?></span>
								<p style="margin:5px 0 0;"><?php echo esc_html($review->comment_content); ?></p>
							</div>
							<?php
						}
					} ?>

					<?php if (!is_user_logged_in()) { ?>
						<p>
							<?php
							printf(
								/* translators: %s: login URL */
								wp_kses_post(__('Please <a href="%s">log in</a> to leave a review after your completed trip.', 'ecab-taxi-booking-manager')),
								esc_url(wp_login_url(get_permalink($post_id)))
							);
							?>
						</p>
					<?php } elseif ($already_reviewed) { ?>
						<p><?php esc_html_e('You have already reviewed this vehicle. Thank you!', 'ecab-taxi-booking-manager'); ?></p>
					<?php } elseif (!$eligible_booking) { ?>
						<p><?php esc_html_e('You can leave a review after completing a trip with this vehicle.', 'ecab-taxi-booking-manager'); ?></p>
					<?php } else { ?>
						<form id="mptbm_review_form" data-post-id="<?php echo esc_attr($post_id); ?>">
							<?php wp_nonce_field('mptbm_submit_review_' . $post_id, 'mptbm_review_nonce'); ?>
							<div>
								<label><?php esc_html_e('Your Rating', 'ecab-taxi-booking-manager'); ?></label><br>
								<div class="mptbm_star_input" style="font-size:22px;cursor:pointer;color:#ccc;">
									<?php for ($i = 1; $i <= 5; $i++) { ?>
										<span data-value="<?php echo esc_attr($i); ?>" class="mptbm_star_choice">&#9733;</span>
									<?php } ?>
								</div>
								<input type="hidden" name="rating" id="mptbm_review_rating" value="0">
							</div>
							<div style="margin-top:10px;">
								<textarea name="review_text" rows="3" style="width:100%;" placeholder="<?php esc_attr_e('Share your experience...', 'ecab-taxi-booking-manager'); ?>"></textarea>
							</div>
							<button type="submit" class="_themeButton" style="margin-top:10px;"><?php esc_html_e('Submit Review', 'ecab-taxi-booking-manager'); ?></button>
							<p class="mptbm_review_message" style="display:none;margin-top:8px;"></p>
						</form>
						<script>
						jQuery(function($){
							$('.mptbm_star_choice').on('click', function(){
								var val = $(this).data('value');
								$('#mptbm_review_rating').val(val);
								$('.mptbm_star_choice').each(function(){
									$(this).css('color', ($(this).data('value') <= val) ? '#f5a623' : '#ccc');
								});
							});
							$('#mptbm_review_form').on('submit', function(e){
								e.preventDefault();
								var $form = $(this);
								var rating = $('#mptbm_review_rating').val();
								if (!rating || rating == 0) {
									alert(<?php echo wp_json_encode(__('Please select a star rating.', 'ecab-taxi-booking-manager')); ?>);
									return;
								}
								$.post(ajaxurl || <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, {
									action: 'mptbm_submit_review',
									post_id: $form.data('post-id'),
									rating: rating,
									review_text: $form.find('[name="review_text"]').val(),
									nonce: $form.find('#mptbm_review_nonce').val()
								}, function(response){
									var $msg = $form.find('.mptbm_review_message').show();
									if (response.success) {
										$msg.css('color','green').text(response.data.message);
										$form.find('button[type=submit]').prop('disabled', true);
									} else {
										$msg.css('color','red').text(response.data.message || 'Error');
									}
								});
							});
						});
						</script>
					<?php } ?>
				</div>
				<?php
			}

			public function submit_review() {
				$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

				if (!check_ajax_referer('mptbm_submit_review_' . $post_id, 'nonce', false)) {
					wp_send_json_error(['message' => __('Security check failed, please refresh and try again.', 'ecab-taxi-booking-manager')]);
				}
				if (!is_user_logged_in()) {
					wp_send_json_error(['message' => __('Please log in to submit a review.', 'ecab-taxi-booking-manager')]);
				}
				if (!$post_id || get_post_type($post_id) !== MPTBM_Function::get_cpt()) {
					wp_send_json_error(['message' => __('Invalid vehicle.', 'ecab-taxi-booking-manager')]);
				}
				if (!self::reviews_enabled($post_id)) {
					wp_send_json_error(['message' => __('Reviews are not enabled for this vehicle.', 'ecab-taxi-booking-manager')]);
				}

				$rating = isset($_POST['rating']) ? absint($_POST['rating']) : 0;
				$review_text = isset($_POST['review_text']) ? sanitize_textarea_field($_POST['review_text']) : '';
				$user_id = get_current_user_id();

				if ($rating < 1 || $rating > 5) {
					wp_send_json_error(['message' => __('Please select a rating between 1 and 5 stars.', 'ecab-taxi-booking-manager')]);
				}
				if (self::has_already_reviewed($user_id, $post_id)) {
					wp_send_json_error(['message' => __('You have already reviewed this vehicle.', 'ecab-taxi-booking-manager')]);
				}

				$booking = self::find_eligible_booking($user_id, $post_id);
				if (!$booking) {
					wp_send_json_error(['message' => __('You can only review a vehicle after a completed trip.', 'ecab-taxi-booking-manager')]);
				}

				$user = wp_get_current_user();
				$comment_id = wp_insert_comment([
					'comment_post_ID'      => $post_id,
					'comment_author'       => $user->display_name,
					'comment_author_email' => $user->user_email,
					'user_id'              => $user_id,
					'comment_content'      => $review_text,
					'comment_type'         => self::COMMENT_TYPE,
					'comment_approved'     => 1,
				]);

				if (!$comment_id) {
					wp_send_json_error(['message' => __('Could not save your review, please try again.', 'ecab-taxi-booking-manager')]);
				}

				update_comment_meta($comment_id, 'rating', $rating);
				update_comment_meta($comment_id, 'mptbm_booking_id', $booking->ID);
				$driver_id = get_post_meta($post_id, 'mptbm_selected_driver', true);
				if ($driver_id) {
					update_comment_meta($comment_id, 'mptbm_review_driver_id', $driver_id);
				}

				wp_send_json_success(['message' => __('Thank you for your review!', 'ecab-taxi-booking-manager')]);
			}

			public function submit_review_guest() {
				wp_send_json_error(['message' => __('Please log in to submit a review.', 'ecab-taxi-booking-manager')]);
			}

			// Admin-only: remove a review from the vehicle's "Manage Reviews" list in the edit screen.
			public function admin_delete_review() {
				$comment_id = isset($_POST['comment_id']) ? absint($_POST['comment_id']) : 0;
				$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

				if (!$comment_id || !check_ajax_referer('mptbm_delete_review_' . $comment_id, 'nonce', false)) {
					wp_send_json_error(['message' => __('Security check failed, please refresh and try again.', 'ecab-taxi-booking-manager')]);
				}
				if (!is_user_logged_in() || !current_user_can('edit_post', $post_id)) {
					wp_send_json_error(['message' => __('You do not have permission to do this.', 'ecab-taxi-booking-manager')]);
				}
				if (!$post_id || get_post_type($post_id) !== MPTBM_Function::get_cpt()) {
					wp_send_json_error(['message' => __('Invalid vehicle.', 'ecab-taxi-booking-manager')]);
				}

				$comment = get_comment($comment_id);
				if (!$comment || $comment->comment_type !== self::COMMENT_TYPE || (int) $comment->comment_post_ID !== $post_id) {
					// Don't let this endpoint touch any comment other than a review that actually belongs to this vehicle.
					wp_send_json_error(['message' => __('Review not found.', 'ecab-taxi-booking-manager')]);
				}

				$deleted = wp_delete_comment($comment_id, true);
				if (!$deleted) {
					wp_send_json_error(['message' => __('Could not delete the review, please try again.', 'ecab-taxi-booking-manager')]);
				}

				wp_send_json_success(['message' => __('Review deleted.', 'ecab-taxi-booking-manager')]);
			}

			// Admin-only: fetch reviews for the "Manage Reviews" list in the edit screen, 20 at a
			// time, so vehicles with a lot of reviews don't have to load them all at once.
			public function admin_load_reviews() {
				$per_page = 20;
				$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
				$offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;

				if (!$post_id || !check_ajax_referer('mptbm_load_reviews_' . $post_id, 'nonce', false)) {
					wp_send_json_error(['message' => __('Security check failed, please refresh and try again.', 'ecab-taxi-booking-manager')]);
				}
				if (!is_user_logged_in() || !current_user_can('edit_post', $post_id)) {
					wp_send_json_error(['message' => __('You do not have permission to do this.', 'ecab-taxi-booking-manager')]);
				}
				if (get_post_type($post_id) !== MPTBM_Function::get_cpt()) {
					wp_send_json_error(['message' => __('Invalid vehicle.', 'ecab-taxi-booking-manager')]);
				}

				$total = count(self::get_vehicle_reviews($post_id));
				$comments = get_comments([
					'post_id' => $post_id,
					'type'    => self::COMMENT_TYPE,
					'status'  => 'approve',
					'order'   => 'DESC',
					'number'  => $per_page,
					'offset'  => $offset,
				]);

				$data = [];
				foreach ($comments as $comment) {
					$data[] = [
						'id'           => $comment->comment_ID,
						'author'       => esc_html($comment->comment_author),
						'date'         => esc_html(mysql2date(get_option('date_format'), $comment->comment_date)),
						'content'      => esc_html($comment->comment_content),
						'rating'       => (int) get_comment_meta($comment->comment_ID, 'rating', true),
						'delete_nonce' => wp_create_nonce('mptbm_delete_review_' . $comment->comment_ID),
					];
				}

				wp_send_json_success([
					'reviews'  => $data,
					'has_more' => ($offset + count($comments)) < $total,
				]);
			}

			// Nudge customers to review right from their completed order page.
			public function maybe_show_review_prompt($order) {
				if (!$order->has_status('completed')) {
					return;
				}
				$vehicle_id = 0;
				foreach ($order->get_items() as $item) {
					$pid = MP_Global_Function::get_order_item_meta($item->get_id(), '_mptbm_id');
					if ($pid && get_post_type($pid) === MPTBM_Function::get_cpt()) {
						$vehicle_id = $pid;
						break;
					}
				}
				if (!$vehicle_id) {
					return;
				}
				printf(
					'<p class="mptbm_review_prompt"><a href="%s">%s</a></p>',
					esc_url(get_permalink($vehicle_id) . '#mptbm-reviews'),
					esc_html__('How was your trip? Rate your ride now', 'ecab-taxi-booking-manager') . ' &rarr;'
				);
			}
		}
		new MPTBM_Reviews();
	}
