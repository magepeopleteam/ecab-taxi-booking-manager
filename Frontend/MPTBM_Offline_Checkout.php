<?php
	/**
	 * Standalone (no-WooCommerce) checkout for the built-in FREE Offline payment method.
	 *
	 * Offline is the one custom payment method that ships with the free plugin: it needs
	 * no online processor, so a booking can be recorded as "pending" and settled on
	 * pickup / by bank transfer. PayPal & Stripe (and the richer Pro checkout - customer
	 * portal, gateway returns, PDF, driver assignment) stay in the Pro plugin.
	 *
	 * Flow:
	 *   1. Renders the customer fields + the Offline payment card into the last booking
	 *      step, via the `mptbm_custom_checkout_form` action the free templates already fire.
	 *   2. Handles the "Book Now" submit through the `mptbm_custom_payment_add_to_cart`
	 *      filter that MPTBM_Woocommerce::mptbm_add_to_cart() applies whenever WooCommerce
	 *      does not own the booking (see MPTBM_Booking_Mode).
	 *   3. Recomputes the fare SERVER-SIDE (never trusts a posted price), stores the
	 *      booking as an `mptbm_booking` post using the SAME meta schema as the
	 *      WooCommerce flow (MPTBM_Woocommerce::add_cpt_data), so it shows up in the
	 *      existing Bookings list, analytics, etc.
	 *   4. Redirects to the Booking Confirmation page (auto-created, shortcode
	 *      [mptbm_booking_confirmation]), keyed by booking id + PIN so a booking can't be
	 *      read by guessing ids.
	 *
	 * Stands down COMPLETELY when the Pro plugin is active - Pro's MPTBM_Native_Checkout
	 * owns the standalone flow there and registers the same shortcode. Pro is detected at
	 * hook/request time (both plugins are loaded by then), never at include time, because
	 * plugin load order is not guaranteed. Same approach as MPTBM_Booking_List_Free.
	 */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.

	if (!class_exists('MPTBM_Offline_Checkout')) {
		class MPTBM_Offline_Checkout {

			const CONFIRMATION_SHORTCODE = 'mptbm_booking_confirmation';
			const SETTINGS_OPTION        = 'mptbm_payment_settings';
			const AUTO_PAGE_OPTION       = 'mptbm_confirmation_page_auto';

			public function __construct() {
				add_action('mptbm_custom_checkout_form', array($this, 'render_checkout_form'));
				add_filter('mptbm_custom_payment_add_to_cart', array($this, 'process_checkout'), 10, 2);
				add_action('init', array($this, 'register_confirmation_shortcode'));
				add_action('admin_init', array($this, 'ensure_confirmation_page'));
			}

			/*
			 * ---------------------------------------------------------------
			 * Availability
			 * ------------------------------------------------------------ */

			/** Pro owns the standalone flow when present, so this class must not run. */
			private function pro_active(): bool {
				return class_exists('MPTBM_Plugin_Pro') || class_exists('MPTBM_Native_Checkout');
			}

			/** Should this free Offline flow handle the booking right now? */
			private function is_active(): bool {
				if ($this->pro_active()) {
					return false;
				}
				if (!MPTBM_Function::offline_payment_enabled()) {
					return false;
				}
				// Only when the booking is NOT owned by the WooCommerce cart.
				return !class_exists('MPTBM_Booking_Mode') || !MPTBM_Booking_Mode::is_woocommerce();
			}

			/** Customer-facing label for the offline method. */
			private function offline_label(): string {
				$label = MP_Global_Function::get_settings(self::SETTINGS_OPTION, 'mptbm_offline_label', '');
				return $label ? $label : esc_html__('Offline Payment', 'ecab-taxi-booking-manager');
			}

			/**
			 * Whether a customer must be signed in to book. Mirrors the Pro reader
			 * (MPTBM_Customer_Portal::login_required()) so both builds agree; defaults to
			 * NO, matching the "Require Customer Login" default on the Payments tab.
			 */
			private function login_required(): bool {
				if (class_exists('MPTBM_Booking_Mode') && MPTBM_Booking_Mode::is_woocommerce()) {
					return false;
				}
				return MP_Global_Function::get_settings(self::SETTINGS_OPTION, 'mptbm_require_login', 'no') === 'yes';
			}

			/*
			 * ---------------------------------------------------------------
			 * Frontend: customer details + payment method (final booking step)
			 * ------------------------------------------------------------ */

			public function render_checkout_form($post_id = 0): void {
				if (!$this->is_active()) {
					return;
				}

				if (!is_user_logged_in() && $this->login_required()) {
					$this->render_login_required_panel();
					return;
				}

				$name = $email = $phone = '';
				if (is_user_logged_in()) {
					$user  = wp_get_current_user();
					$name  = $user->display_name;
					$email = $user->user_email;
					$phone = get_user_meta($user->ID, 'user_phone', true);
				}
				?>
				<div class="dLayout mptbm_custom_checkout">
					<div class="mptbm_cc_head">
						<span class="mptbm_cc_head_icon"><span class="fas fa-user"></span></span>
						<h3><?php esc_html_e('Your Details', 'ecab-taxi-booking-manager'); ?></h3>
					</div>
					<div class="mptbm_cc_fields">
						<div class="mptbm_cc_field">
							<label><?php esc_html_e('Full Name', 'ecab-taxi-booking-manager'); ?> <span class="mptbm_cc_req">*</span></label>
							<input type="text" name="mptbm_billing_name" value="<?php echo esc_attr($name); ?>" placeholder="<?php esc_attr_e('John Doe', 'ecab-taxi-booking-manager'); ?>" required>
						</div>
						<div class="mptbm_cc_field">
							<label><?php esc_html_e('Email', 'ecab-taxi-booking-manager'); ?> <span class="mptbm_cc_req">*</span></label>
							<input type="email" name="mptbm_billing_email" value="<?php echo esc_attr($email); ?>" placeholder="<?php esc_attr_e('you@example.com', 'ecab-taxi-booking-manager'); ?>" required>
						</div>
						<div class="mptbm_cc_field mptbm_cc_field_full">
							<label><?php esc_html_e('Phone', 'ecab-taxi-booking-manager'); ?></label>
							<input type="text" name="mptbm_billing_phone" value="<?php echo esc_attr($phone); ?>" placeholder="<?php esc_attr_e('+1 234 567 890', 'ecab-taxi-booking-manager'); ?>">
						</div>
					</div>

					<div class="mptbm_cc_head mptbm_cc_head_pay">
						<span class="mptbm_cc_head_icon"><span class="fas fa-credit-card"></span></span>
						<h3><?php esc_html_e('Payment Method', 'ecab-taxi-booking-manager'); ?></h3>
					</div>
					<div class="mptbm_payment_methods">
						<label class="mptbm_pm_card is-selected" data-pm="offline">
							<span class="mptbm_pm_icon">
								<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#fff" stroke-width="1.7" xmlns="http://www.w3.org/2000/svg"><rect x="2" y="6" width="20" height="13" rx="2"/><circle cx="12" cy="12.5" r="2.4"/><path d="M5 9.5h.01M19 15.5h.01"/></svg>
							</span>
							<span class="mptbm_pm_text">
								<span class="mptbm_pm_name"><?php echo esc_html($this->offline_label()); ?></span>
								<span class="mptbm_pm_sub"><?php esc_html_e('Pay on service / bank transfer', 'ecab-taxi-booking-manager'); ?></span>
							</span>
							<input type="radio" name="mptbm_payment_method" value="offline" checked>
							<span class="mptbm_pm_radio"></span>
						</label>
					</div>
				</div>
				<?php
				$this->checkout_form_assets();
			}

			/** Guest + "Require Customer Login" is on: point them at wp-login instead of a dead form. */
			private function render_login_required_panel(): void {
				$redirect = home_url(add_query_arg(array()));
				?>
				<div class="dLayout mptbm_custom_checkout">
					<div class="mptbm_cc_head">
						<span class="mptbm_cc_head_icon"><span class="fas fa-lock"></span></span>
						<h3><?php esc_html_e('Please sign in to continue', 'ecab-taxi-booking-manager'); ?></h3>
					</div>
					<p class="mptbm_cc_login_text"><?php esc_html_e('An account is required to complete this booking. Sign in or create an account, then come back to finish.', 'ecab-taxi-booking-manager'); ?></p>
					<p>
						<a class="_themeButton_min_200" href="<?php echo esc_url(wp_login_url($redirect)); ?>"><?php esc_html_e('Sign in', 'ecab-taxi-booking-manager'); ?></a>
						<?php if (get_option('users_can_register')) : ?>
							<a class="_mpBtn_dBR_min_150" href="<?php echo esc_url(wp_registration_url()); ?>"><?php esc_html_e('Create an account', 'ecab-taxi-booking-manager'); ?></a>
						<?php endif; ?>
					</p>
				</div>
				<?php
				$this->checkout_form_assets();
			}

			/** Checkout-form CSS, printed at most once per request. */
			private function checkout_form_assets(): void {
				static $printed = false;
				if ($printed) {
					return;
				}
				$printed = true;
				?>
				<style>
				.mptbm_custom_checkout{--mptbm-cc-accent:#F12971;--mptbm-cc-border:#e5e7eb;padding:22px 22px 4px;}
				.mptbm_cc_head{display:flex;align-items:center;gap:10px;margin:0 0 18px;}
				.mptbm_cc_head_pay{margin-top:28px;}
				.mptbm_cc_head h3{margin:0;font-size:16px;font-weight:700;color:#1f2937;}
				.mptbm_custom_checkout .mptbm_cc_head_icon{width:32px;height:32px;border-radius:9px;display:inline-flex !important;align-items:center !important;justify-content:center !important;line-height:1;background:rgba(241,41,113,.1);color:var(--mptbm-cc-accent);font-size:14px;flex:0 0 auto;}
				.mptbm_custom_checkout .mptbm_cc_head_icon .fas{display:block;line-height:1;margin:0;}
				.mptbm_cc_fields{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
				.mptbm_cc_field{display:flex;flex-direction:column;gap:7px;}
				.mptbm_cc_field_full{grid-column:1 / -1;}
				.mptbm_cc_field label{font-size:13px;font-weight:600;color:#374151;}
				.mptbm_cc_req{color:var(--mptbm-cc-accent);}
				.mptbm_cc_field input{width:100%;box-sizing:border-box;height:46px;padding:0 14px;border:1.5px solid var(--mptbm-cc-border);border-radius:10px;font-size:14px;color:#111827;background:#fff;transition:border-color .15s,box-shadow .15s;}
				.mptbm_cc_field input::placeholder{color:#9ca3af;}
				.mptbm_cc_field input:focus{outline:none;border-color:var(--mptbm-cc-accent);box-shadow:0 0 0 3px rgba(241,41,113,.12);}
				.mptbm_cc_login_text{margin:0 0 16px;font-size:14px;color:#4b5563;line-height:1.6;}
				.mptbm_payment_methods{display:flex;flex-direction:column;gap:12px;}
				.mptbm_pm_card{display:flex;align-items:center;gap:14px;padding:14px 16px;border:1.5px solid var(--mptbm-cc-border);border-radius:14px;background:#fff;cursor:pointer;position:relative;transition:border-color .15s,box-shadow .15s,background .15s;}
				.mptbm_pm_card:hover{border-color:#cbd5e1;box-shadow:0 4px 14px rgba(16,24,40,.06);}
				.mptbm_pm_card.is-selected{border-color:var(--mptbm-cc-accent);background:#fff;box-shadow:0 6px 18px rgba(241,41,113,.12);}
				.mptbm_custom_checkout .mptbm_pm_icon{flex:0 0 auto;width:44px;height:44px;border-radius:11px;display:inline-flex !important;align-items:center !important;justify-content:center !important;line-height:1;background:linear-gradient(135deg,#0f766e,#115e59);box-shadow:0 4px 10px rgba(16,24,40,.18);overflow:hidden;}
				.mptbm_custom_checkout .mptbm_pm_icon svg{display:block;width:20px;height:20px;margin:0;}
				.mptbm_pm_text{display:flex;flex-direction:column;gap:2px;flex:1 1 auto;min-width:0;}
				.mptbm_pm_name{font-size:15px;font-weight:700;color:#1f2937;line-height:1.2;}
				.mptbm_pm_sub{font-size:12.5px;color:#6b7280;line-height:1.3;}
				.mptbm_pm_card input[type=radio]{position:absolute;opacity:0;width:0;height:0;}
				.mptbm_pm_radio{flex:0 0 auto;width:22px;height:22px;border-radius:50%;border:2px solid #d1d5db;position:relative;transition:border-color .15s;}
				.mptbm_pm_card.is-selected .mptbm_pm_radio{border-color:var(--mptbm-cc-accent);}
				.mptbm_pm_card.is-selected .mptbm_pm_radio:after{content:"";position:absolute;inset:4px;border-radius:50%;background:var(--mptbm-cc-accent);}
				@media (max-width:560px){.mptbm_cc_fields{grid-template-columns:1fr;}}
				</style>
				<?php
			}

			/*
			 * ---------------------------------------------------------------
			 * Booking submit
			 * ------------------------------------------------------------ */

			/**
			 * Handle "Book Now" for the standalone Offline flow.
			 *
			 * Filter contract (see MPTBM_Woocommerce::mptbm_add_to_cart): return a plain
			 * URL to redirect the browser, or HTML containing a <div> to show inline.
			 * Returning the untouched $response leaves the request to another handler.
			 *
			 * @param string $response Response so far ('' when unhandled).
			 * @return string
			 */
			public function process_checkout($response, $posted = array()) {
				if ($response !== '' || !$this->is_active()) {
					return $response;
				}

				if (!is_user_logged_in() && $this->login_required()) {
					return $this->error_html(esc_html__('Please sign in to complete your booking.', 'ecab-taxi-booking-manager'));
				}

				$post_id = $this->resolve_vehicle_id(isset($_POST['link_id']) ? $_POST['link_id'] : 0);
				if (!$post_id) {
					return $this->error_html(esc_html__('Invalid vehicle selected. Please start your booking again.', 'ecab-taxi-booking-manager'));
				}

				$quantity = isset($_POST['transport_quantity']) ? max(1, absint($_POST['transport_quantity'])) : 1;

				$name  = isset($_POST['mptbm_billing_name']) ? sanitize_text_field(wp_unslash($_POST['mptbm_billing_name'])) : '';
				$email = isset($_POST['mptbm_billing_email']) ? sanitize_email(wp_unslash($_POST['mptbm_billing_email'])) : '';
				$phone = isset($_POST['mptbm_billing_phone']) ? sanitize_text_field(wp_unslash($_POST['mptbm_billing_phone'])) : '';
				if ((!$name || !$email) && is_user_logged_in()) {
					$user  = wp_get_current_user();
					$name  = $name ?: $user->display_name;
					$email = $email ?: $user->user_email;
				}
				if (!$name || !$email || !is_email($email)) {
					return $this->error_html(esc_html__('Please enter a valid name and email address.', 'ecab-taxi-booking-manager'));
				}

				// Fare is always recomputed here - the posted price is display-only.
				$unit_price = $this->get_total_price($post_id);
				$total      = round((float) $unit_price * $quantity, 2);

				$meta = $this->build_booking_meta($post_id, $quantity, $unit_price, $total, $name, $email, $phone);

				$booking_id = $this->create_booking($name, $meta);
				if (!$booking_id) {
					return $this->error_html(esc_html__('Could not create the booking. Please try again.', 'ecab-taxi-booking-manager'));
				}

				return $this->confirmation_url($booking_id);
			}

			/**
			 * Server-side fare for one vehicle incl. extra services.
			 * Mirrors MPTBM_Woocommerce's cart price calculation.
			 */
			private function get_total_price($post_id): float {
				$distance = isset($_POST['mptbm_distance']) ? absint($_POST['mptbm_distance']) : (isset($_COOKIE['mptbm_distance']) ? absint($_COOKIE['mptbm_distance']) : 1000);
				$duration = isset($_POST['mptbm_duration']) ? absint($_POST['mptbm_duration']) : (isset($_COOKIE['mptbm_duration']) ? absint($_COOKIE['mptbm_duration']) : 3600);
				$start    = isset($_POST['mptbm_start_place']) ? sanitize_text_field(wp_unslash($_POST['mptbm_start_place'])) : '';
				$end      = isset($_POST['mptbm_end_place']) ? sanitize_text_field(wp_unslash($_POST['mptbm_end_place'])) : '';
				$waiting  = isset($_POST['mptbm_waiting_time']) ? sanitize_text_field(wp_unslash($_POST['mptbm_waiting_time'])) : 0;
				$two_way  = isset($_POST['mptbm_taxi_return']) ? sanitize_text_field(wp_unslash($_POST['mptbm_taxi_return'])) : 1;
				$fixed    = isset($_POST['mptbm_fixed_hours']) ? sanitize_text_field(wp_unslash($_POST['mptbm_fixed_hours'])) : 0;

				$price_based = isset($_POST['mptbm_original_price_base']) ? sanitize_text_field(wp_unslash($_POST['mptbm_original_price_base'])) : '';
				$geo_coords  = null;
				if (in_array($price_based, array('fixed_zone', 'fixed_zone_dropoff'), true)) {
					$start_coords = isset($_POST['start_place_coordinates']) ? wp_unslash($_POST['start_place_coordinates']) : '';
					$end_coords   = isset($_POST['end_place_coordinates']) ? wp_unslash($_POST['end_place_coordinates']) : '';
					if ($price_based === 'fixed_zone_dropoff' && !empty($start_coords)) {
						$geo_coords = $this->parse_coords($start_coords);
					} elseif ($price_based === 'fixed_zone' && !empty($end_coords)) {
						$geo_coords = $this->parse_coords($end_coords);
					}
				}

				$price     = MPTBM_Function::get_price($post_id, $distance, $duration, $start, $end, $waiting, $two_way, $fixed, $geo_coords);
				$raw_price = (float) MP_Global_Function::price_convert_raw(MP_Global_Function::wc_price($post_id, $price));

				foreach ($this->posted_extra_services($post_id) as $service) {
					$raw_price += (float) $service['service_price'] * (int) $service['service_quantity'];
				}

				return $raw_price;
			}

			/** Normalised extra-service rows from the posted arrays (name/qty/unit price). */
			private function posted_extra_services($post_id): array {
				$names = isset($_POST['mptbm_extra_service']) ? array_map('sanitize_text_field', (array) wp_unslash($_POST['mptbm_extra_service'])) : array();
				$qtys  = isset($_POST['mptbm_extra_service_qty']) ? array_map('absint', (array) wp_unslash($_POST['mptbm_extra_service_qty'])) : array();
				$names = array_values($names);
				$qtys  = array_values($qtys);

				$rows = array();
				for ($i = 0; $i < count($names); $i++) {
					if (empty($names[$i])) {
						continue;
					}
					$rows[] = array(
						'service_name'     => $names[$i],
						'service_quantity' => (isset($qtys[$i]) && $qtys[$i] > 0) ? $qtys[$i] : 1,
						'service_price'    => (float) MPTBM_Function::get_extra_service_price_by_name($post_id, $names[$i]),
					);
				}
				return $rows;
			}

			/** Booking meta, matching the schema the WooCommerce flow writes. */
			private function build_booking_meta($post_id, $quantity, $unit_price, $total, $name, $email, $phone): array {
				$post_value = function ($key, $default = '') {
					return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : $default;
				};

				$meta = array(
					'mptbm_id'                          => $post_id,
					// The price model the customer searched with. Stored so a fare can be
					// recomputed later (admin booking edit) without it - see the
					// mptbm_original_price_based filter in MPTBM_Function::get_price().
					'mptbm_original_price_base'         => $post_value('mptbm_original_price_base'),
					'mptbm_date'                        => $post_value('mptbm_date'),
					'mptbm_start_place'                 => $post_value('mptbm_start_place'),
					'mptbm_end_place'                   => $post_value('mptbm_end_place'),
					'mptbm_extra_stop_place'            => $post_value('mptbm_extra_stop_place'),
					'mptbm_waiting_time'                => $post_value('mptbm_waiting_time', 0),
					'mptbm_taxi_return'                 => $post_value('mptbm_taxi_return', 1),
					'mptbm_fixed_hours'                 => $post_value('mptbm_fixed_hours', 0),
					'mptbm_distance'                    => isset($_POST['mptbm_distance']) ? absint($_POST['mptbm_distance']) : '',
					'mptbm_duration'                    => isset($_POST['mptbm_duration']) ? absint($_POST['mptbm_duration']) : '',
					'mptbm_base_price'                  => $unit_price,
					'mptbm_threshold_base_price'        => $post_value('mptbm_threshold_base_price', 0),
					'mptbm_order_status'                => 'pending',
					'mptbm_user_id'                     => get_current_user_id(),
					'mptbm_tp'                          => $total,
					'mptbm_service_info'                => $this->posted_extra_services($post_id),
					'mptbm_billing_name'                => $name,
					'mptbm_billing_email'               => $email,
					'mptbm_billing_phone'               => $phone,
					'mptbm_payment_method'              => $this->offline_label(),
					'mptbm_target_pickup_interval_time' => MPTBM_Function::get_general_settings('mptbm_pickup_interval_time', '30'),
					'mptbm_transport_quantity'          => $quantity,
				);

				$return_date = $post_value('mptbm_return_date');
				$return_time = $post_value('mptbm_return_time');
				if ($return_date) {
					$meta['mptbm_return_target_date'] = $return_date;
				}
				if ($return_time) {
					$meta['mptbm_return_target_time'] = $return_time;
				}
				if (isset($_POST['mptbm_passengers'])) {
					$meta['mptbm_passengers'] = absint($_POST['mptbm_passengers']);
				}

				return apply_filters('add_mptbm_booking_data', $meta, $post_id);
			}

			/**
			 * Insert the booking post + meta. Replicates MPTBM_Woocommerce::add_cpt_data()
			 * (unavailable logic-wise here because there is no WooCommerce order): the
			 * booking is its own order, so mptbm_order_id is its own id and mptbm_pin is
			 * derived the same way.
			 *
			 * Offline bookings are always 'pending' - payment happens off-site, so an admin
			 * confirms them manually.
			 */
			private function create_booking($title, $meta) {
				$booking_id = wp_insert_post(array(
					'post_title'  => $title ?: esc_html__('Booking', 'ecab-taxi-booking-manager'),
					'post_type'   => 'mptbm_booking',
					'post_status' => 'pending',
				));

				if (is_wp_error($booking_id) || !$booking_id) {
					return 0;
				}

				$meta['mptbm_order_id']     = $booking_id;
				$meta['mptbm_order_status'] = 'pending';
				$meta['mptbm_payment_gateway'] = 'offline';

				foreach ($meta as $key => $value) {
					update_post_meta($booking_id, $key, $value);
				}

				update_post_meta($booking_id, 'mptbm_pin', $meta['mptbm_user_id'] . $meta['mptbm_order_id'] . $meta['mptbm_id'] . $booking_id);

				do_action('mptbm_custom_booking_created', $booking_id, $meta, 'pending');
				$this->send_notifications($booking_id, $meta);

				return $booking_id;
			}

			/**
			 * Minimal plain-text confirmation to the customer + the shop admin. Without
			 * this an offline booking would be recorded with nobody told about it. Pro
			 * replaces this with its templated native booking mail.
			 */
			private function send_notifications($booking_id, $meta): void {
				if (!apply_filters('mptbm_offline_send_notifications', true, $booking_id, $meta)) {
					return;
				}

				$site      = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
				$reference = '#' . $booking_id;
				$total     = MP_Global_Function::format_price($meta['mptbm_tp']);
				$lines     = array(
					sprintf(esc_html__('Booking reference: %s', 'ecab-taxi-booking-manager'), $reference),
					sprintf(esc_html__('Pickup: %s', 'ecab-taxi-booking-manager'), $meta['mptbm_start_place']),
					sprintf(esc_html__('Drop-off: %s', 'ecab-taxi-booking-manager'), $meta['mptbm_end_place']),
					sprintf(esc_html__('Date: %s', 'ecab-taxi-booking-manager'), $meta['mptbm_date']),
					sprintf(esc_html__('Total: %s', 'ecab-taxi-booking-manager'), wp_strip_all_tags($total)),
					sprintf(esc_html__('Payment: %s (to be paid offline)', 'ecab-taxi-booking-manager'), $meta['mptbm_payment_method']),
				);
				$body = implode("\n", $lines);

				wp_mail(
					$meta['mptbm_billing_email'],
					sprintf(esc_html__('[%1$s] Your booking %2$s is received', 'ecab-taxi-booking-manager'), $site, $reference),
					esc_html__('Thank you for your booking. We have received it and will confirm shortly.', 'ecab-taxi-booking-manager') . "\n\n" . $body
				);

				wp_mail(
					get_option('admin_email'),
					sprintf(esc_html__('[%1$s] New offline booking %2$s', 'ecab-taxi-booking-manager'), $site, $reference),
					sprintf(esc_html__('A new booking was placed by %1$s (%2$s).', 'ecab-taxi-booking-manager'), $meta['mptbm_billing_name'], $meta['mptbm_billing_email']) . "\n\n" . $body
				);
			}

			/*
			 * ---------------------------------------------------------------
			 * Confirmation page
			 * ------------------------------------------------------------ */

			/** Confirmation URL, keyed by booking id + PIN so ids can't simply be guessed. */
			private function confirmation_url($booking_id): string {
				$opts    = get_option(self::SETTINGS_OPTION, array());
				$page_id = (is_array($opts) && !empty($opts['mptbm_confirmation_page_id'])) ? absint($opts['mptbm_confirmation_page_id']) : 0;
				$base    = ($page_id && get_post_status($page_id) === 'publish') ? get_permalink($page_id) : home_url('/');

				return add_query_arg(array(
					'mptbm_booking'    => 'pending',
					'mptbm_booking_id' => $booking_id,
					'mptbm_pin'        => rawurlencode((string) get_post_meta($booking_id, 'mptbm_pin', true)),
				), $base);
			}

			public function register_confirmation_shortcode(): void {
				if ($this->pro_active()) {
					return; // Pro registers the same shortcode with its richer template.
				}
				add_shortcode(self::CONFIRMATION_SHORTCODE, array($this, 'render_confirmation'));
			}

			/**
			 * Auto-create the confirmation page and select it in Payments settings.
			 * Idempotent and cheap; uses the same option keys as Pro so a later upgrade
			 * reuses the very same page instead of creating a second one.
			 */
			public function ensure_confirmation_page(): void {
				if ($this->pro_active() || !MPTBM_Function::offline_payment_enabled() || !current_user_can('manage_options')) {
					return;
				}

				$opts    = get_option(self::SETTINGS_OPTION, array());
				$opts    = is_array($opts) ? $opts : array();
				$page_id = !empty($opts['mptbm_confirmation_page_id']) ? absint($opts['mptbm_confirmation_page_id']) : 0;
				if ($page_id && get_post_status($page_id) === 'publish') {
					return;
				}

				$auto = absint(get_option(self::AUTO_PAGE_OPTION, 0));
				if ($auto && get_post_status($auto) === 'publish') {
					$page_id = $auto;
				} else {
					$page_id = wp_insert_post(array(
						'post_title'     => esc_html__('Booking Confirmation', 'ecab-taxi-booking-manager'),
						'post_name'      => 'booking-confirmation',
						'post_content'   => '[' . self::CONFIRMATION_SHORTCODE . ']',
						'post_status'    => 'publish',
						'post_type'      => 'page',
						'comment_status' => 'closed',
					));
					if (is_wp_error($page_id) || !$page_id) {
						return;
					}
					update_option(self::AUTO_PAGE_OPTION, $page_id);
				}

				$opts['mptbm_confirmation_page_id'] = $page_id;
				update_option(self::SETTINGS_OPTION, $opts);
			}

			/** [mptbm_booking_confirmation] - booking summary after an offline booking. */
			public function render_confirmation() {
				$booking_id = isset($_GET['mptbm_booking_id']) ? absint($_GET['mptbm_booking_id']) : 0;
				$pin        = isset($_GET['mptbm_pin']) ? sanitize_text_field(wp_unslash($_GET['mptbm_pin'])) : '';

				if (!$booking_id || get_post_type($booking_id) !== 'mptbm_booking') {
					return '<p>' . esc_html__('No booking to show.', 'ecab-taxi-booking-manager') . '</p>';
				}

				// A booking is readable with its PIN, or by the account that placed it.
				$stored_pin = (string) get_post_meta($booking_id, 'mptbm_pin', true);
				$owner_id   = absint(get_post_meta($booking_id, 'mptbm_user_id', true));
				$is_owner   = $owner_id && get_current_user_id() === $owner_id;
				if (!hash_equals($stored_pin, $pin) && !$is_owner && !current_user_can('manage_options')) {
					return '<p>' . esc_html__('This booking could not be verified.', 'ecab-taxi-booking-manager') . '</p>';
				}

				$get = function ($key) use ($booking_id) {
					return get_post_meta($booking_id, $key, true);
				};

				$rows = array(
					esc_html__('Booking reference', 'ecab-taxi-booking-manager') => '#' . $booking_id,
					esc_html__('Status', 'ecab-taxi-booking-manager')            => esc_html__('Pending confirmation', 'ecab-taxi-booking-manager'),
					esc_html__('Name', 'ecab-taxi-booking-manager')              => $get('mptbm_billing_name'),
					esc_html__('Email', 'ecab-taxi-booking-manager')             => $get('mptbm_billing_email'),
					esc_html__('Phone', 'ecab-taxi-booking-manager')             => $get('mptbm_billing_phone'),
					esc_html__('Pickup', 'ecab-taxi-booking-manager')            => $get('mptbm_start_place'),
					esc_html__('Drop-off', 'ecab-taxi-booking-manager')          => $get('mptbm_end_place'),
					esc_html__('Date', 'ecab-taxi-booking-manager')              => $get('mptbm_date'),
					esc_html__('Payment method', 'ecab-taxi-booking-manager')    => $get('mptbm_payment_method'),
					esc_html__('Total', 'ecab-taxi-booking-manager')             => wp_strip_all_tags(MP_Global_Function::format_price($get('mptbm_tp'))),
				);

				ob_start();
				?>
				<div class="mptbm_booking_confirmation">
					<div class="mptbm_bc_head">
						<span class="mptbm_bc_tick" aria-hidden="true">&#10003;</span>
						<h2><?php esc_html_e('Thank you! Your booking is received.', 'ecab-taxi-booking-manager'); ?></h2>
						<p><?php esc_html_e('We have emailed you the details. Your booking is pending until we confirm it - payment is taken offline.', 'ecab-taxi-booking-manager'); ?></p>
					</div>
					<table class="mptbm_bc_table">
						<tbody>
						<?php foreach ($rows as $label => $value) : ?>
							<?php if ($value === '' || $value === null) { continue; } ?>
							<tr>
								<th><?php echo esc_html($label); ?></th>
								<td><?php echo esc_html($value); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<style>
				.mptbm_booking_confirmation{max-width:640px;margin:0 auto;padding:26px;border:1px solid #e5e7eb;border-radius:16px;background:#fff;box-shadow:0 6px 24px rgba(16,24,40,.06);}
				.mptbm_bc_head{text-align:center;margin-bottom:22px;}
				.mptbm_bc_tick{display:inline-flex;align-items:center;justify-content:center;width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,#0f766e,#115e59);color:#fff;font-size:24px;margin-bottom:12px;}
				.mptbm_bc_head h2{margin:0 0 8px;font-size:20px;font-weight:700;color:#1f2937;}
				.mptbm_bc_head p{margin:0;font-size:14px;color:#6b7280;line-height:1.6;}
				.mptbm_bc_table{width:100%;border-collapse:collapse;}
				.mptbm_bc_table th,.mptbm_bc_table td{padding:11px 4px;border-bottom:1px solid #f1f2f4;font-size:14px;text-align:left;vertical-align:top;}
				.mptbm_bc_table th{color:#6b7280;font-weight:600;width:42%;}
				.mptbm_bc_table td{color:#1f2937;}
				</style>
				<?php
				return ob_get_clean();
			}

			/*
			 * ---------------------------------------------------------------
			 * Helpers
			 * ------------------------------------------------------------ */

			/**
			 * Resolve a posted link_id to the underlying transportation (mptbm_rent) id.
			 * In standalone mode extra_service.php posts the vehicle id directly; a stale
			 * WooCommerce product id is mapped back via its link_mptbm_id meta.
			 */
			private function resolve_vehicle_id($id): int {
				$id = absint($id);
				if (!$id) {
					return 0;
				}
				$cpt = MPTBM_Function::get_cpt();
				if (get_post_type($id) === $cpt) {
					return $id;
				}
				$linked = absint(get_post_meta($id, 'link_mptbm_id', true));
				return ($linked && get_post_type($linked) === $cpt) ? $linked : 0;
			}

			/** Parse a coordinate string/JSON the same way the WooCommerce flow does. */
			private function parse_coords($raw) {
				if (is_array($raw)) {
					return $raw;
				}
				$decoded = json_decode((string) $raw, true);
				return is_array($decoded) ? $decoded : null;
			}

			/** Error markup - the booking JS shows any response containing a <div> inline. */
			private function error_html($message): string {
				return '<div class="mptbm_booking_error" style="padding:14px 16px;border:1px solid #f5c2c7;background:#f8d7da;color:#842029;border-radius:8px;">' . esc_html($message) . '</div>';
			}
		}

		new MPTBM_Offline_Checkout();
	}
