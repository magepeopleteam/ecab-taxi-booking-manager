<?php
/*
 * Add Stoppage - optional priced/free sightseeing stops a customer can pick
 * for the currently selected vehicle. Included from extra_service.php, which
 * already guarantees $post_id and $mptbm_stoppages (non-empty) are set.
 */
if (!defined('ABSPATH')) {
	die;
} // Cannot access pages directly

$mptbm_stoppage_badge_labels = [
	'most_popular' => esc_html__('Most popular', 'ecab-taxi-booking-manager'),
	'recommended'  => esc_html__('Recommended', 'ecab-taxi-booking-manager'),
];
?>
<div class="mptbm_stoppage_panel">
	<div class="mptbm_stoppage_banner">
		<span class="mptbm_stoppage_banner_icon"><span class="fas fa-map-marker-alt" aria-hidden="true"></span></span>
		<div class="mptbm_stoppage_banner_text">
			<strong><?php esc_html_e('Enhance Your Journey', 'ecab-taxi-booking-manager'); ?></strong>
			<span><?php esc_html_e('Would you like to add a stop to see attractions during this trip? Customize your route to make the most of your travel time.', 'ecab-taxi-booking-manager'); ?></span>
		</div>
		<button type="button" class="mptbm_stoppage_trigger" data-stoppage-trigger>
			<span class="fas fa-plus" aria-hidden="true"></span>
			<span><?php esc_html_e('Add Stoppage', 'ecab-taxi-booking-manager'); ?></span>
			<span class="mptbm_stoppage_trigger_count" data-stoppage-count></span>
		</button>
	</div>

	<div class="mptbm_stoppage_popup" data-stoppage-popup aria-hidden="true">
		<div class="mptbm_stoppage_popup_backdrop" data-stoppage-close></div>
		<div class="mptbm_stoppage_dialog" role="dialog" aria-modal="true" aria-labelledby="mptbm_stoppage_dialog_title_<?php echo esc_attr($post_id); ?>">
			<button type="button" class="mptbm_stoppage_dialog_close" data-stoppage-close aria-label="<?php esc_attr_e('Close', 'ecab-taxi-booking-manager'); ?>">
				<span class="fas fa-times" aria-hidden="true"></span>
			</button>
			<h3 id="mptbm_stoppage_dialog_title_<?php echo esc_attr($post_id); ?>"><?php esc_html_e('Customize your trip with stops', 'ecab-taxi-booking-manager'); ?></h3>
			<p><?php esc_html_e('Add optional sightseeing or rest stops along the way - pick as many as you like.', 'ecab-taxi-booking-manager'); ?></p>

			<div class="mptbm_stoppage_row">
				<?php foreach ($mptbm_stoppages as $mptbm_stop) : ?>
					<div class="mptbm_stoppage_item" data-stoppage-item
						data-id="<?php echo esc_attr($mptbm_stop['id']); ?>"
						data-name="<?php echo esc_attr($mptbm_stop['name']); ?>"
						data-description="<?php echo esc_attr($mptbm_stop['description']); ?>"
						data-duration="<?php echo esc_attr($mptbm_stop['duration']); ?>"
						data-price="<?php echo esc_attr($mptbm_stop['price']); ?>"
						data-image="<?php echo esc_url($mptbm_stop['image_url']); ?>"
						data-gallery="<?php echo esc_attr(wp_json_encode($mptbm_stop['gallery'])); ?>"
						data-badge="<?php echo esc_attr($mptbm_stop['badge']); ?>">
						<button type="button" class="mptbm_stoppage_card_media" data-stoppage-details-trigger
							<?php echo $mptbm_stop['image_url'] ? 'style="background-image:url(' . esc_url($mptbm_stop['image_url']) . ')"' : ''; ?>>
							<?php if (!empty($mptbm_stop['badge']) && isset($mptbm_stoppage_badge_labels[$mptbm_stop['badge']])) : ?>
								<span class="mptbm_stoppage_badge is-<?php echo esc_attr($mptbm_stop['badge']); ?>">
									<span class="fas fa-star" aria-hidden="true"></span>
									<?php echo esc_html($mptbm_stoppage_badge_labels[$mptbm_stop['badge']]); ?>
								</span>
							<?php endif; ?>
							<span class="mptbm_stoppage_card_name"><?php echo esc_html($mptbm_stop['name']); ?></span>
						</button>
						<div class="mptbm_stoppage_card_footer">
							<span class="mptbm_stoppage_card_meta">
								<?php if ($mptbm_stop['duration']) : ?>
									<span class="mptbm_stoppage_card_duration"><?php echo esc_html($mptbm_stop['duration']); ?></span>
									<span aria-hidden="true"> &middot; </span>
								<?php endif; ?>
								<span class="mptbm_stoppage_card_price">
									<?php echo $mptbm_stop['price'] > 0 ? wp_kses_post(MP_Global_Function::format_price($mptbm_stop['price'])) : esc_html__('Free', 'ecab-taxi-booking-manager'); ?>
								</span>
							</span>
							<button type="button" class="mptbm_stoppage_add_btn" data-stoppage-toggle title="<?php esc_attr_e('Add this stop', 'ecab-taxi-booking-manager'); ?>">
								<input type="hidden" name="mptbm_stoppage_id[]" value="" data-price="<?php echo esc_attr($mptbm_stop['price']); ?>" data-id="<?php echo esc_attr($mptbm_stop['id']); ?>" />
								<span class="fas fa-plus" aria-hidden="true" data-stoppage-icon></span>
							</button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="mptbm_stoppage_dialog_footer">
				<button type="button" class="button mptbm_stoppage_done" data-stoppage-close><?php esc_html_e('Done', 'ecab-taxi-booking-manager'); ?></button>
			</div>
		</div>
	</div>

	<!-- One shared detail view, populated from the clicked card's data-* attributes -->
	<div class="mptbm_stoppage_detail_popup" data-stoppage-detail-popup aria-hidden="true">
		<div class="mptbm_stoppage_popup_backdrop" data-stoppage-detail-close></div>
		<div class="mptbm_stoppage_detail_dialog" role="dialog" aria-modal="true">
			<button type="button" class="mptbm_stoppage_dialog_close" data-stoppage-detail-close aria-label="<?php esc_attr_e('Close', 'ecab-taxi-booking-manager'); ?>">
				<span class="fas fa-times" aria-hidden="true"></span>
			</button>
			<div class="mptbm_stoppage_detail_content">
				<div class="mptbm_stoppage_detail_media" data-stoppage-detail-media>
					<span class="mptbm_stoppage_badge" data-stoppage-detail-badge hidden><span class="fas fa-star" aria-hidden="true"></span><span data-stoppage-detail-badge-text></span></span>
				</div>
				<div class="mptbm_stoppage_detail_thumbs" data-stoppage-detail-thumbs hidden></div>
				<div class="mptbm_stoppage_detail_body">
					<h4 data-stoppage-detail-name></h4>
					<div class="mptbm_stoppage_detail_pills">
						<span class="mptbm_stoppage_pill is-duration" data-stoppage-detail-duration-pill hidden>
							<span class="far fa-clock" aria-hidden="true"></span>
							<span data-stoppage-detail-duration></span>
						</span>
						<span class="mptbm_stoppage_pill is-price" data-stoppage-detail-price-pill>
							<span data-stoppage-detail-price></span>
						</span>
					</div>
					<p class="mptbm_stoppage_detail_desc" data-stoppage-detail-desc></p>
					<button type="button" class="button button-primary mptbm_stoppage_detail_add" data-stoppage-detail-add>
						<span class="fas fa-plus" data-stoppage-detail-add-icon aria-hidden="true"></span>
						<span data-stoppage-detail-add-text></span>
					</button>
				</div>
			</div>
		</div>
	</div>
</div>
