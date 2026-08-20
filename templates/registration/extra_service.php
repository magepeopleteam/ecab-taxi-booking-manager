<?php
	/*
 * @Author 		engr.sumonazma@gmail.com
 * Copyright: 	mage-people.com
 */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly
	$post_id = absint($_POST['post_id']);
	if ($post_id && $post_id > 0) {
		// Whether the WooCommerce cart actually owns this submit is decided by Booking
		// Mode, not merely whether WooCommerce happens to be installed - a site can run
		// WooCommerce alongside the Pro custom checkout, or start Pro-only and have WC
		// added later. In custom/standalone mode the "Book Now" button must target the
		// transportation post directly (MPTBM_Native_Checkout resolves it back to the
		// vehicle); in WooCommerce mode it needs a real, existing mirror WC product id -
		// self-heal it here rather than relying on save_post having already created one
		// (vehicles imported before WooCommerce was installed never went through that).
		$wc_owns_booking = class_exists('MPTBM_Booking_Mode')
			? MPTBM_Booking_Mode::is_woocommerce()
			: (class_exists('MPTBM_Function') && MPTBM_Function::is_wc_active());
		if ($wc_owns_booking) {
			$link_wc_product = class_exists('MPTBM_Hidden_Product')
				? MPTBM_Hidden_Product::get_or_create_wc_product($post_id)
				: MP_Global_Function::get_post_info($post_id, 'link_wc_product');
		} else {
			$link_wc_product = $post_id;
		}
		$display_extra_services = MP_Global_Function::get_post_info($post_id, 'display_mptbm_extra_services', 'on');
		$service_id = MP_Global_Function::get_post_info($post_id, 'mptbm_extra_services_id', $post_id);
		$extra_services = MP_Global_Function::get_post_info($service_id, 'mptbm_extra_service_infos', []);
		if ($display_extra_services == 'on' && is_array($extra_services) && sizeof($extra_services) > 0) {
			?>
		<div class="dLayout mptbm_exsvc_panel">
			<div class="mptbm_exsvc_header">
				<h3><?php esc_html_e('Choose Extra Features (Optional)', 'ecab-taxi-booking-manager'); ?></h3>
			</div>
			<?php foreach ($extra_services as $service) { ?>
				<?php
				$service_icon = array_key_exists('service_icon', $service) ? $service['service_icon'] : '';
				$service_image = array_key_exists('service_image', $service) ? $service['service_image'] : '';
				$service_name = array_key_exists('service_name', $service) ? $service['service_name'] : '';
				$service_price = array_key_exists('service_price', $service) ? $service['service_price'] : 0;
				$wc_price = MP_Global_Function::wc_price($post_id, $service_price);
				$service_price = MP_Global_Function::price_convert_raw($wc_price);
				$description = array_key_exists('extra_service_description', $service) ? $service['extra_service_description'] : '';
				$ex_unique_id = '#ex_service_' . uniqid();
				?>
				<?php if ($service_name) { ?>
					<div class="dFlex mptbm_extra_service_item">
						<?php if ($service_image) { ?>
							<div class="mptbm_exsvc_thumb">
								<?php 
								$image_url = is_numeric($service_image) ? wp_get_attachment_image_url($service_image, 'medium') : MP_Global_Function::get_image_url('', $service_image, 'medium');
								?>
								<img src="<?php echo esc_attr($image_url); ?>" alt="<?php echo esc_attr($service_name); ?>">
							</div>
						<?php } else if ($service_icon) { ?>
							<div class="mptbm_exsvc_icon">
								<span class="<?php echo esc_attr($service_icon); ?>"></span>
							</div>
						<?php } ?>
						<div class="fdColumn _fullWidth">
							<div class="mptbm_exsvc_top">
								<div class="mptbm_exsvc_info">
									<h4>
										<?php if (!$service_image && !$service_icon) { ?>
											<span class="mi mi-star"></span>
										<?php } ?>
										<?php echo esc_html($service_name); ?>
									</h4>
									<div class="mptbm_exsvc_desc">
										<?php MP_Custom_Layout::load_more_text($description, 100); ?>
									</div>
								</div>
								<div class="mptbm_exsvc_meta">
									<span class="mptbm_exsvc_price"><?php echo wp_kses_post(MP_Global_Function::format_price($service_price)); ?></span>
									<div class="mptbm_exsvc_actions">
										<div class="_mR_min_100" data-collapse="<?php echo esc_attr($ex_unique_id); ?>">
											<?php MP_Custom_Layout::qty_input('mptbm_extra_service_qty[]', $service_price, 100, 1, 0); ?>
										</div>
										<button type="button" class="mptbm_price_calculation mptbm_exsvc_select" data-extra-item data-collapse-target="<?php echo esc_attr($ex_unique_id); ?>" data-open-icon="far fa-check-circle" data-close-icon="" data-open-text="<?php esc_attr_e('Select', 'ecab-taxi-booking-manager'); ?>" data-close-text="<?php esc_attr_e('Selected', 'ecab-taxi-booking-manager'); ?>" data-add-class="mActive">
											<input type="hidden" name="mptbm_extra_service[]" data-value="<?php echo esc_attr($service_name); ?>" value=""/>
											<span data-text><?php esc_html_e('Select', 'ecab-taxi-booking-manager'); ?></span>
											<span data-icon class="mL_xs"></span>
										</button>
									</div>
								</div>
							</div>
						</div>
					</div>
				<?php } ?>
			<?php } ?>
		</div>
		<?php } ?>
		<?php
			// Add Stoppage - independent of Extra Services (own CPT/meta, own
			// JS/CSS - see Admin/MPTBM_Stoppages_Manager.php). Only rendered when
			// this vehicle actually has stoppages assigned and switched on.
			$mptbm_stoppages = class_exists('MPTBM_Function') ? MPTBM_Function::get_available_stoppages($post_id) : [];
			if (!empty($mptbm_stoppages)) {
				include(MPTBM_Function::template_path('registration/stoppage.php'));
			}
		?>
		<?php
			// Pro custom booking flow injects customer + payment-method fields here when
			// WooCommerce is inactive. No-op otherwise (hook has no callbacks).
			do_action('mptbm_custom_checkout_form', $post_id);
		?>
		<?php $booking_available = class_exists('MPTBM_Function') && MPTBM_Function::is_booking_available(); ?>

		<?php
			// Mirrors the .mptbm_transport_summary block in summary.php (the sticky
			// left sidebar) so mobile/scrolled users see the running total again right
			// before the Book Now button. Same classes on purpose: mptbm_registration.js
			// updates every ".mptbm_transport_summary" it finds inside this
			// .mptbm_transport_search_area, so this copy is kept in sync for free -
			// no JS changes needed. Vehicle name is pre-filled server-side since this
			// markup only reaches the DOM after the vehicle is already selected (the
			// JS event that first sets .mptbm_product_name already fired by then).
		?>
		<div class="mptbm_transport_summary mptbm_exsvc_summary_footer">
			<div class="divider"></div>
			<h6 class="_mB_xs"><?php echo esc_html(MPTBM_Function::get_name()) . ' ' . esc_html__(' Details', 'ecab-taxi-booking-manager'); ?></h6>
			<div class="_textColor_4 justifyBetween">
				<div class="_dFlex_alignCenter">
					<span class="fas fa-check-square _textTheme_mR_xs"></span>
					<span class="mptbm_product_name"><?php echo esc_html(get_the_title($post_id)); ?></span>
				</div>
				<span class="mptbm_product_price _textTheme"></span>
			</div>
			<div class="mptbm_base_price_detail"></div>
			<div class="mptbm_stop_price_detail"></div>
			<div class="mptbm_stoppage_price_detail"></div>
			<div class="mptbm_extra_service_summary"></div>
			<div class="divider"></div>
			<div class="justifyBetween">
				<h4><?php esc_html_e('Total : ', 'ecab-taxi-booking-manager'); ?></h4>
				<h6 class="mptbm_product_total_price"></h6>
			</div>
		</div>

		<div class="mptbm_exsvc_footer">
			<?php if ($booking_available) { ?>
				<button class="mptbm_exsvc_booknow mptbm_book_now" style="display:none;" type="button" data-wc_link_id="<?php echo esc_attr($link_wc_product); ?>">
					<span class="fas fa-cart-plus"></span>
					<span><?php esc_html_e('Book Now', 'ecab-taxi-booking-manager'); ?></span>
				</button>
			<?php } else { ?>
				<p class="mptbm_booking_unavailable_msg"><?php esc_html_e('Booking currently not possible. Please contact us directly to complete your booking.', 'ecab-taxi-booking-manager'); ?></p>
				<button class="mptbm_exsvc_booknow mptbm_book_now" type="button" disabled title="<?php esc_attr_e('Booking is currently not possible. Please contact us directly.', 'ecab-taxi-booking-manager'); ?>">
					<span class="fas fa-cart-plus"></span>
					<span><?php esc_html_e('Book Now', 'ecab-taxi-booking-manager'); ?></span>
				</button>
			<?php } ?>
		</div>
		<?php
	}