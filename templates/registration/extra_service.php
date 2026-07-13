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
			<div class="dLayout">
				<h3><?php esc_html_e('Choose Extra Features (Optional)', 'ecab-taxi-booking-manager'); ?></h3>
				<div class="divider"></div>
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
								<div class="service_img_area alignCenter">
									<div class="bg_image_area">
										<?php 
										$image_url = is_numeric($service_image) ? wp_get_attachment_image_url($service_image, 'medium') : MP_Global_Function::get_image_url('', $service_image, 'medium');
										?>
										<img src="<?php echo esc_attr($image_url); ?>" alt="<?php echo esc_attr($service_name); ?>" style="max-width: 125px; max-height: 125px; width: 100%; height: auto; object-fit: cover; border-radius: 4px;">
									</div>
								</div>
							<?php } ?>
							<div class="fdColumn _fullWidth">
								<h4>
									<?php if ($service_icon) { ?>
										<span class="<?php echo esc_attr($service_icon); ?>"></span>
									<?php } ?>
									<?php echo esc_html($service_name); ?>
									<sub class="textTheme"> &nbsp;&nbsp;<?php echo wp_kses_post(MP_Global_Function::format_price($service_price)); ?></sub>
								</h4>
								<div class="_equalChild">
									<div class="_mR_xs">
										<?php MP_Custom_Layout::load_more_text($description, 100); ?>
									</div>
									<div>
										<div class="justifyEnd">
											<div class="_mR_min_100" data-collapse="<?php echo esc_attr($ex_unique_id); ?>">
												<?php MP_Custom_Layout::qty_input('mptbm_extra_service_qty[]', $service_price, 100, 1, 0); ?>
											</div>
											<button type="button" class="_mpBtn_dBR_min_150 mptbm_price_calculation" data-extra-item data-collapse-target="<?php echo esc_attr($ex_unique_id); ?>" data-open-icon="far fa-check-circle" data-close-icon="" data-open-text="<?php esc_attr_e('Select', 'ecab-taxi-booking-manager'); ?>" data-close-text="<?php esc_attr_e('Selected', 'ecab-taxi-booking-manager'); ?>" data-add-class="mActive">
												<input type="hidden" name="mptbm_extra_service[]" data-value="<?php echo esc_attr($service_name); ?>" value=""/>
												<span data-text><?php esc_html_e('Select', 'ecab-taxi-booking-manager'); ?></span>
												<span data-icon class="mL_xs"></span>
											</button>
										</div>
									</div>
									
								</div>
							</div>
						</div>
						<div class="divider"></div>
					<?php } ?>
				<?php } ?>
			</div>
		<?php } ?>
		<div class="divider"></div>
		<?php
			// Pro custom booking flow injects customer + payment-method fields here when
			// WooCommerce is inactive. No-op otherwise (hook has no callbacks).
			do_action('mptbm_custom_checkout_form', $post_id);
		?>
		<?php $booking_available = class_exists('MPTBM_Function') && MPTBM_Function::is_booking_available(); ?>
		<div class="justifyBetween" style="padding: 20px 0;">
			<?php if ($booking_available) { ?>
				<div></div>
				<button class="_themeButton_min_200 mptbm_book_now" style="display:none;" type="button" data-wc_link_id="<?php echo esc_attr($link_wc_product); ?>">
					<span class="fas fa-cart-plus _mR_xs"></span>
					<?php esc_html_e('Book Now', 'ecab-taxi-booking-manager'); ?>
				</button>
			<?php } else { ?>
				<p class="mptbm_booking_unavailable_msg" style="margin:0;color:#b32d2e;font-size:13px;max-width:65%;"><?php esc_html_e('Booking currently not possible. Please contact us directly to complete your booking.', 'ecab-taxi-booking-manager'); ?></p>
				<button class="_themeButton_min_200 mptbm_book_now" type="button" disabled title="<?php esc_attr_e('Booking is currently not possible. Please contact us directly.', 'ecab-taxi-booking-manager'); ?>" style="opacity:.55;cursor:not-allowed;">
					<span class="fas fa-cart-plus _mR_xs"></span>
					<?php esc_html_e('Book Now', 'ecab-taxi-booking-manager'); ?>
				</button>
			<?php } ?>
		</div>
		<?php
	}