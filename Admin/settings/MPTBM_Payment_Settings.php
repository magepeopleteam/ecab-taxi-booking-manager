<?php
	/**
	 * Payment settings tab for the E-cab Taxi Booking global settings page.
	 *
	 * Ported from the Rental plugin (booking-and-rental-manager-for-woocommerce)
	 * RBFW_Payment_Settings, adapted to the ecab MPTBM_/mptbm_ naming convention and
	 * the MagePeople MAGE_Setting_API filter pattern used by MPTBM_Settings_Global.
	 *
	 * - Registers a new "Payments" tab via mp_settings_sec_reg.
	 * - Adds the sub-tabbed UI (WooCommerce / Custom Payment), WooCommerce fields,
	 *   and the PayPal / Stripe / Offline gateway cards via mp_settings_sec_fields.
	 * - Injects the gateway Configure modals + the WooCommerce install/activate
	 *   modal + the tab-switching script on admin_footer (raw HTML, so the SVG /
	 *   button / input markup is not stripped by the html field's wp_kses pass).
	 *
	 * Gateway credentials are stored in the mptbm_payment_settings option and are
	 * saved in real time over AJAX from their own modals, so they are protected
	 * from being wiped when the Settings API saves the rest of the form.
	 *
	 * PayPal & Stripe Configure are gated behind the Pro plugin (MPTBM_Plugin_Pro);
	 * the free version shows a PRO badge for those two. Offline Payment is part of the
	 * FREE plugin - it needs no online processor, so its card, Configure modal and AJAX
	 * save all work without Pro (see MPTBM_Function::offline_payment_enabled()). Note the
	 * standalone checkout that consumes an enabled Offline method still ships with Pro.
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	if ( ! class_exists( 'MPTBM_Payment_Settings' ) ) :
		class MPTBM_Payment_Settings {

			const OPTION = 'mptbm_payment_settings';

			public function __construct() {
				add_filter( 'mp_settings_sec_reg', array( $this, 'register_section' ), 15 );
				add_filter( 'mp_settings_sec_fields', array( $this, 'register_fields' ), 15 );

				add_action( 'admin_footer', array( $this, 'render_wc_warning_modal' ) );
				add_action( 'admin_footer', array( $this, 'render_gateway_modals' ) );
				add_action( 'admin_footer', array( $this, 'payment_tabs_script' ) );

				add_action( 'wp_ajax_mptbm_save_gateway_settings', array( $this, 'ajax_save_gateway_settings' ) );
				add_action( 'wp_ajax_mptbm_install_activate_wc', array( $this, 'ajax_install_activate_wc' ) );
				add_action( 'wp_ajax_mptbm_save_booking_mode', array( $this, 'ajax_save_booking_mode' ) );

				// Gateway keys are managed by their own AJAX modals and never travel with
				// the settings form, so preserve them when the Settings API saves the rest.
				add_filter( 'pre_update_option_' . self::OPTION, array( $this, 'preserve_gateway_keys' ), 10, 2 );
			}

			/** Is this the taxi-booking settings screen? */
			private function is_settings_screen() {
				$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
				return $screen && strpos( $screen->id, 'mptbm_settings_page' ) !== false;
			}

			private function has_woo() {
				return MP_Global_Function::check_woocommerce() === 1;
			}

			private function is_pro() {
				return class_exists( 'MPTBM_Plugin_Pro' );
			}

			private function opt( $key, $default = '' ) {
				$o = get_option( self::OPTION, array() );
				return isset( $o[ $key ] ) ? $o[ $key ] : $default;
			}

			/** Add the "Payments" tab to the settings navigation. */
			public function register_section( $sections ) {
				$sections[] = array(
					'id'    => self::OPTION,
					'icon'  => 'fas fa-credit-card',
					'title' => esc_html__( 'Payments', 'ecab-taxi-booking-manager' ),
				);

				return $sections;
			}

			/** Register the fields that make up the Payments tab. */
			public function register_fields( $settings_fields ) {
				$settings_fields[ self::OPTION ] = array(
					array(
						'name'     => 'mptbm_booking_mode_selector',
						'label'    => '',
						'callback' => array( $this, 'render_booking_mode_selector' ),
					),
					array(
						'name'     => 'mptbm_payment_tabs_html',
						'label'    => '',
						'callback' => array( $this, 'render_sub_tabs' ),
					),
					array(
						'name'     => 'mptbm_wc_payment_gateways_manager',
						'label'    => '',
						'class'    => 'woocommerce-field wc-payment-methods-field',
						'callback' => array( $this, 'render_wc_payment_manager' ),
					),
					array(
						'name'    => 'mptbm_wc_add_to_cart_redirect',
						'label'   => __( 'After Adding to Cart, Redirect to', 'ecab-taxi-booking-manager' ),
						'desc'    => __( 'Select where to redirect after adding an item to the cart.', 'ecab-taxi-booking-manager' ),
						'type'    => 'select',
						'default' => 'checkout',
						'options' => array(
							'cart'     => __( 'Cart', 'ecab-taxi-booking-manager' ),
							'checkout' => __( 'Checkout', 'ecab-taxi-booking-manager' ),
						),
						'class'   => 'woocommerce-field wc-additional-field wc-additional-first',
					),
					array(
						'name'    => 'mptbm_wc_require_login',
						'label'   => __( 'Require Account Login', 'ecab-taxi-booking-manager' ),
						'desc'    => __( 'Require login to complete a booking.', 'ecab-taxi-booking-manager' ),
						'type'    => 'checkbox',
						'default' => '',
						'class'   => 'woocommerce-field wc-additional-field mptbm-check-row',
					),
					array(
						'name'    => 'mptbm_wc_show_billing_info',
						'label'   => __( 'Show Billing Info', 'ecab-taxi-booking-manager' ),
						'desc'    => __( 'Show billing info on the WooCommerce checkout page.', 'ecab-taxi-booking-manager' ),
						'type'    => 'checkbox',
						'default' => '',
						'class'   => 'woocommerce-field wc-additional-field mptbm-check-row',
					),
					array(
						'name'    => 'mptbm_wc_confirm_status',
						'label'   => __( 'Confirm Booking Based on Payment Status', 'ecab-taxi-booking-manager' ),
						'desc'    => __( 'Select the order statuses that will confirm a booking.', 'ecab-taxi-booking-manager' ),
						'type'    => 'multicheck',
						'default' => array( 'processing' => 'processing', 'completed' => 'completed' ),
						'options' => array(
							'pending'    => __( 'Pending payment', 'ecab-taxi-booking-manager' ),
							'processing' => __( 'Processing', 'ecab-taxi-booking-manager' ),
							'on-hold'    => __( 'On hold', 'ecab-taxi-booking-manager' ),
							'completed'  => __( 'Completed', 'ecab-taxi-booking-manager' ),
						),
						'class'   => 'woocommerce-field wc-additional-field wc-additional-last',
					),
					array(
						'name'     => 'mptbm_payment_gateways_ui',
						'label'    => '',
						'class'    => 'no-woocommerce-field payment-gateways-container',
						'callback' => array( $this, 'render_gateway_cards' ),
					),
				);

				return $settings_fields;
			}

			/**
			 * The "Booking Mode" card selector - the single, explicit, required switch
			 * that decides whether WooCommerce or the Pro Custom Payment flow processes
			 * bookings. See MPTBM_Booking_Mode for why this replaced the old implicit
			 * "Enable WooCommerce Payment" checkbox.
			 */
			public function render_booking_mode_selector() {
				if ( ! class_exists( 'MPTBM_Booking_Mode' ) ) {
					return;
				}

				$availability = MPTBM_Booking_Mode::availability();

				if ( 'none' === $availability ) {
					?>
					<div class="mptbm-bm-auto-note mptbm-bm-auto-note--warn">
						<span class="dashicons dashicons-warning"></span>
						<p><?php esc_html_e( 'No booking flow is available yet: WooCommerce is not active and no Custom Payment method is enabled. Activate WooCommerce, or enable Offline Payment below, to start taking bookings.', 'ecab-taxi-booking-manager' ); ?></p>
					</div>
					<?php
					$this->booking_mode_styles();
					return;
				}

				if ( 'woocommerce_only' === $availability ) {
					?>
					<div class="mptbm-bm-auto-note">
						<span class="dashicons dashicons-yes-alt"></span>
						<p><?php esc_html_e( 'Bookings are automatically processed through WooCommerce - it\'s the only booking flow available right now. Enable Offline Payment below (or activate the Pro plugin for PayPal & Stripe) to unlock the standalone Custom Payment flow and a mode switch here.', 'ecab-taxi-booking-manager' ); ?></p>
					</div>
					<?php
					$this->booking_mode_styles();
					return;
				}

				if ( 'custom_only' === $availability ) {
					?>
					<div class="mptbm-bm-auto-note">
						<span class="dashicons dashicons-yes-alt"></span>
						<p><?php esc_html_e( 'Bookings are automatically processed through the Custom Payment flow - WooCommerce is not active. Activate WooCommerce to unlock the WooCommerce checkout flow (and a mode switch here).', 'ecab-taxi-booking-manager' ); ?></p>
					</div>
					<?php
					$this->booking_mode_styles();
					return;
				}

				// $availability === 'both': a real, required choice.
				$needs_choice = MPTBM_Booking_Mode::needs_selection();
				$mode         = MPTBM_Booking_Mode::get_mode();
				$is_wc        = ! $needs_choice && 'woocommerce' === $mode;
				$is_custom    = ! $needs_choice && 'custom' === $mode;
				$has_gateway  = MPTBM_Booking_Mode::has_gateway_for_active_mode();
				$nonce        = wp_create_nonce( 'mptbm_save_booking_mode' );
				?>
				<div class="mptbm-bm-wrap" data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<div class="mptbm-bm-head">
						<h3>
							<?php esc_html_e( 'Booking Mode', 'ecab-taxi-booking-manager' ); ?>
							<span class="mptbm-bm-required"><?php esc_html_e( 'Required', 'ecab-taxi-booking-manager' ); ?></span>
						</h3>
						<p><?php esc_html_e( 'Choose exactly one flow to process bookings. This single switch decides everything below, so WooCommerce and Custom Payment never both try to handle the same booking.', 'ecab-taxi-booking-manager' ); ?></p>
					</div>

					<?php if ( $needs_choice ) : ?>
						<div class="mptbm-bm-nudge">
							<span class="dashicons dashicons-flag"></span>
							<?php esc_html_e( 'Please choose a booking mode below to continue.', 'ecab-taxi-booking-manager' ); ?>
						</div>
					<?php endif; ?>

					<div class="mptbm-bm-cards">
						<label class="mptbm-bm-card<?php echo $is_wc ? ' is-selected' : ''; ?>" data-mode="woocommerce">
							<input type="radio" name="mptbm_booking_mode_radio" value="woocommerce" <?php checked( $is_wc ); ?>>
							<span class="mptbm-bm-card-icon dashicons dashicons-cart"></span>
							<span class="mptbm-bm-card-body">
								<span class="mptbm-bm-card-title-row">
									<strong><?php esc_html_e( 'WooCommerce Checkout', 'ecab-taxi-booking-manager' ); ?></strong>
									<?php if ( $is_wc ) : ?>
										<span class="mptbm-bm-card-badge"><?php esc_html_e( 'Active', 'ecab-taxi-booking-manager' ); ?></span>
									<?php endif; ?>
								</span>
								<span class="mptbm-bm-card-desc"><?php esc_html_e( 'Bookings go through the WooCommerce cart, checkout, and orders.', 'ecab-taxi-booking-manager' ); ?></span>
							</span>
						</label>
						<label class="mptbm-bm-card<?php echo $is_custom ? ' is-selected' : ''; ?>" data-mode="custom">
							<input type="radio" name="mptbm_booking_mode_radio" value="custom" <?php checked( $is_custom ); ?>>
							<span class="mptbm-bm-card-icon dashicons dashicons-money-alt"></span>
							<span class="mptbm-bm-card-body">
								<span class="mptbm-bm-card-title-row">
									<strong><?php esc_html_e( 'Custom Payment (Standalone)', 'ecab-taxi-booking-manager' ); ?></strong>
									<?php if ( $is_custom ) : ?>
										<span class="mptbm-bm-card-badge"><?php esc_html_e( 'Active', 'ecab-taxi-booking-manager' ); ?></span>
									<?php endif; ?>
								</span>
								<span class="mptbm-bm-card-desc"><?php esc_html_e( 'Bookings are taken directly via PayPal, Stripe, or Offline payment - no WooCommerce.', 'ecab-taxi-booking-manager' ); ?></span>
							</span>
						</label>
					</div>

					<p class="mptbm-bm-status" role="status" aria-live="polite"></p>

					<div class="mptbm-bm-gateway-warning-slot">
						<?php if ( ! $needs_choice && ! $has_gateway ) : ?>
							<div class="mptbm-bm-gateway-warning">
								<span class="dashicons dashicons-warning"></span>
								<p>
									<?php if ( $is_wc ) : ?>
										<?php esc_html_e( 'WooCommerce mode is selected, but no WooCommerce payment gateway is enabled yet. Customers won\'t be able to complete a booking until you enable one below.', 'ecab-taxi-booking-manager' ); ?>
									<?php else : ?>
										<?php esc_html_e( 'Custom Payment mode is selected, but no gateway (PayPal, Stripe, or Offline) is enabled yet. Customers won\'t be able to complete a booking until you enable one below.', 'ecab-taxi-booking-manager' ); ?>
									<?php endif; ?>
								</p>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<?php $this->booking_mode_styles(); ?>
				<script>
				jQuery( function ( $ ) {
					var $wrap = $( '.mptbm-bm-wrap' );
					if ( ! $wrap.length ) { return; }
					var nonce = $wrap.data( 'nonce' );
					var i18n  = {
						saving: <?php echo wp_json_encode( __( 'Saving…', 'ecab-taxi-booking-manager' ) ); ?>,
						saved:  <?php echo wp_json_encode( __( 'Booking mode saved.', 'ecab-taxi-booking-manager' ) ); ?>,
						error:  <?php echo wp_json_encode( __( 'Could not save. Please try again.', 'ecab-taxi-booking-manager' ) ); ?>,
						wcWarn: <?php echo wp_json_encode( __( 'WooCommerce mode is selected, but no WooCommerce payment gateway is enabled yet. Customers won\'t be able to complete a booking until you enable one below.', 'ecab-taxi-booking-manager' ) ); ?>,
						customWarn: <?php echo wp_json_encode( __( 'Custom Payment mode is selected, but no gateway (PayPal, Stripe, or Offline) is enabled yet. Customers won\'t be able to complete a booking until you enable one below.', 'ecab-taxi-booking-manager' ) ); ?>,
						active: <?php echo wp_json_encode( __( 'Active', 'ecab-taxi-booking-manager' ) ); ?>
					};

					$wrap.on( 'click', '.mptbm-bm-card', function () {
						var $card = $( this ), mode = $card.data( 'mode' );
						if ( $card.hasClass( 'is-selected' ) ) { return; }

						$wrap.find( '.mptbm-bm-card' ).removeClass( 'is-selected' ).find( '.mptbm-bm-card-badge' ).remove();
						$card.addClass( 'is-selected' ).find( '.mptbm-bm-card-title-row' ).append( '<span class="mptbm-bm-card-badge">' + i18n.active + '</span>' );
						$card.find( 'input[type=radio]' ).prop( 'checked', true );
						$wrap.find( '.mptbm-bm-nudge' ).hide();
						var $status = $wrap.find( '.mptbm-bm-status' ).show().text( i18n.saving ).css( 'color', '#6b7280' );

						$.post( ajaxurl, {
							action: 'mptbm_save_booking_mode',
							nonce: nonce,
							mode: mode
						} ).done( function ( res ) {
							if ( res && res.success ) {
								$status.text( i18n.saved ).css( 'color', '#0a7c2f' );
								setTimeout( function () { $status.fadeOut( 400, function () { $( this ).text( '' ).show(); } ); }, 1800 );

								// Reveal the newly active mode's settings so the admin can configure
								// it right away. payment_tabs_script() listens for this.
								$( document ).trigger( 'mptbm:mode-changed', [ mode ] );

								// Refresh the "no gateway enabled" warning for the freshly active mode.
								var $slot = $wrap.find( '.mptbm-bm-gateway-warning-slot' );
								$slot.empty();
								if ( res.data && res.data.has_gateway === false ) {
									var msg = ( mode === 'woocommerce' ) ? i18n.wcWarn : i18n.customWarn;
									$slot.append( '<div class="mptbm-bm-gateway-warning"><span class="dashicons dashicons-warning"></span><p>' + msg + '</p></div>' );
								}
							} else {
								$status.show().text( ( res && res.data ) ? res.data : i18n.error ).css( 'color', '#d63638' );
							}
						} ).fail( function () {
							$status.show().text( i18n.error ).css( 'color', '#d63638' );
						} );
					} );
				} );
				</script>
				<?php
			}

			/** Styles for the Booking Mode selector + its auto-detected notices. Printed once. */
			private function booking_mode_styles() {
				static $printed = false;
				if ( $printed ) {
					return;
				}
				$printed = true;
				?>
				<style>
				.mptbm-bm-wrap,
				.mptbm-bm-wrap *,
				.mptbm-bm-auto-note,
				.mptbm-bm-auto-note *{box-sizing:border-box;}
				.mptbm-bm-wrap{background:#fff;padding:0;margin:10px 0 0px;box-shadow:0 1px 2px rgba(16,24,40,0.04);max-width:100%;overflow:hidden;}
				.mptbm-bm-head h3{margin:5px 0 2px;font-size:14px;font-weight:700;color:#1d2327;display:flex;align-items:center;gap:8px;}
				.mptbm-bm-required{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;background:#fee2e2;color:#991b1b;padding:1px 8px;border-radius:20px;}
				.mptbm-bm-head p{margin:0 0 10px;font-size:12px;color:#6b7280;max-width:640px;line-height:1.5;}
				.mptbm-bm-nudge{display:flex;align-items:center;gap:8px;background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;border-radius:8px;padding:7px 12px;font-size:12px;font-weight:600;margin-bottom:10px;}
				.mptbm-bm-cards{display:grid;grid-template-columns:1fr 1fr;gap:10px;max-width:100%;}
				.mptbm-bm-card{position:relative;display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border:1.5px solid #e5e7eb;border-radius:10px;background:#fafafb;cursor:pointer;transition:border-color .15s,box-shadow .15s,background .15s;min-width:0;}
				.mptbm-bm-card:hover{border-color:#d4b3c3;box-shadow:0 4px 14px rgba(16,24,40,0.06);}
				.mptbm-bm-card.is-selected{border-color:#F12971;background:#fff;box-shadow:0 6px 18px rgba(241,41,113,0.12);}
				.mptbm-bm-card input[type=radio]{position:absolute;opacity:0;width:0;height:0;}
				.mptbm-bm-card-icon{flex:0 0 auto;width:30px;height:30px;border-radius:8px;background:rgba(241,41,113,0.1);color:#F12971;display:flex !important;align-items:center !important;justify-content:center !important;font-size:15px;box-sizing:border-box;padding:7px;}
				/* The shared mp_global framework sets ".mpStyle label > span{white-space:nowrap}"
				   with higher specificity than a single class, which would otherwise force this
				   whole block onto one clipped line - override it explicitly on every level. */
				.mptbm-bm-card-body{display:block !important;flex:1;min-width:0;white-space:normal !important;}
				.mptbm-bm-card-title-row{display:flex !important;align-items:center;justify-content:space-between;gap:8px;margin:0 0 4px;width:100%;white-space:normal !important;}
				.mptbm-bm-card-body strong{display:inline-block !important;font-size:13px;line-height:1.3;color:#1d2327;white-space:normal !important;}
				.mptbm-bm-card-desc{display:block !important;font-size:11.5px;color:#6b7280;line-height:1.45;white-space:normal !important;overflow-wrap:break-word;}
				.mptbm-bm-card-badge{flex:0 0 auto;font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;background:#dcfce7;color:#166534;padding:1px 8px;border-radius:20px;display:none !important;}
				.mptbm-bm-card.is-selected .mptbm-bm-card-badge{display:inline-block !important;}
				.mptbm-bm-status{min-height:16px;margin:6px 2px 0;font-size:12px;font-weight:600;}
				.mptbm-bm-gateway-warning{display:flex;align-items:flex-start;gap:8px;margin-top:10px;padding:9px 12px;border-radius:8px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:12px;}
				.mptbm-bm-gateway-warning p{margin:0;}
				.mptbm-bm-auto-note{display:flex;align-items:center;gap:12px;background:#f0fdf4;border:1px solid #bbf7d0;color:#14532d;border-radius:12px;padding:14px 16px;margin:6px 0 14px;font-size:13px;line-height:1.55;box-shadow:0 1px 2px rgba(16,24,40,0.03);}
				.mptbm-bm-auto-note .dashicons{flex:0 0 auto;width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;font-size:18px;border-radius:9px;background:#dcfce7;color:#16a34a;}
				.mptbm-bm-auto-note p{margin:0;font-weight:500;}
				.mptbm-bm-auto-note--warn{background:#fff5f5;border-color:#fbcfcf;color:#8a1c1c;}
				.mptbm-bm-auto-note--warn .dashicons{background:#fee2e2;color:#dc2626;}
				@media (max-width:680px){.mptbm-bm-cards{grid-template-columns:1fr;}}
				</style>
				<?php
			}

			/**
			 * Anchor row for the settings sections + the WooCommerce-inactive warning.
			 *
			 * There used to be a WooCommerce / Custom Payment sub-tab bar here, but it
			 * duplicated the Booking Mode selector directly below it: two controls for one
			 * decision, which could disagree (you could sit on the WooCommerce tab while
			 * Custom Payment was the mode actually taking bookings). The Booking Mode cards
			 * are now the single switch - they save the mode AND reveal that mode's settings
			 * (see payment_tabs_script()).
			 */
			public function render_sub_tabs() {
				$wc_active    = $this->has_woo();
				$is_installed = file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' );
				$btn_text     = $is_installed
					? __( 'Activate WooCommerce Now', 'ecab-taxi-booking-manager' )
					: __( 'Install &amp; Activate Now', 'ecab-taxi-booking-manager' );
				?>
				<div class="payment-sub-tabs-wrapper">
					<?php if ( ! $wc_active ) : ?>
						<div class="woocommerce-field">
							<div class="mptbm-wc-callout">
								<div class="mptbm-wc-callout-head">
									<span class="mptbm-wc-callout-icon" aria-hidden="true">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
									</span>
									<h4 class="mptbm-wc-callout-title"><?php esc_html_e( 'WooCommerce is not activated', 'ecab-taxi-booking-manager' ); ?></h4>
								</div>
								<p class="mptbm-wc-callout-text"><?php esc_html_e( 'To take bookings through the WooCommerce cart & checkout flow, install and activate WooCommerce. Prefer not to use it? Choose Custom Payment (Standalone) as your Booking Mode above.', 'ecab-taxi-booking-manager' ); ?></p>
								<button type="button" class="mptbm-install-wc-trigger mptbm-wc-callout-btn">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v11"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
									<?php echo wp_kses_post( $btn_text ); ?>
								</button>
							</div>
						</div>
					<?php endif; ?>
				</div>
				<?php
			}

			/** PayPal / Stripe / Offline gateway cards + booking confirmation page. */
			public function render_gateway_cards() {
				$is_pro      = $this->is_pro();
				$pp_enabled  = $this->opt( 'mptbm_paypal_enable' ) === 'on';
				$st_enabled  = $this->opt( 'mptbm_stripe_enable' ) === 'on';
				$off_enabled = MPTBM_Function::offline_payment_enabled();
				$conf_page   = absint( $this->opt( 'mptbm_confirmation_page_id', 0 ) );

				$enabled_txt  = __( 'Enabled', 'ecab-taxi-booking-manager' );
				$disabled_txt = __( 'Disabled', 'ecab-taxi-booking-manager' );
				$pro_badge    = '<span class="mptbm-gw-pro-badge" title="' . esc_attr__( 'Available in Pro version', 'ecab-taxi-booking-manager' ) . '">PRO</span>';
				?>
				<div class="mptbm-gw-intro">
					<h3><?php esc_html_e( 'Custom Payment Gateways', 'ecab-taxi-booking-manager' ); ?></h3>
					<p><?php esc_html_e( 'Accept payments directly without WooCommerce. Configure a gateway below, then enable it for the Standalone / Custom Payment checkout.', 'ecab-taxi-booking-manager' ); ?></p>
				</div>

				<!-- PayPal Card -->
				<div class="gateway-card paypal-card">
					<div class="gateway-header">
						<div class="gateway-id">
							<span class="gateway-icon">
								<svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M7.076 21.337H2.47a.641.641 0 0 1-.633-.74L4.944.901C5.026.382 5.474 0 5.998 0h7.46c2.57 0 4.578.543 5.69 1.81 1.01 1.15 1.304 2.42 1.012 4.287-.023.143-.047.288-.077.437-.983 5.05-4.349 6.797-8.647 6.797h-2.19c-.524 0-.968.382-1.05.9l-1.12 7.106z" fill="#fff"/>
								</svg>
							</span>
							<span class="gateway-meta">
								<span class="gateway-name"><?php esc_html_e( 'PayPal', 'ecab-taxi-booking-manager' ); ?></span>
								<span class="gateway-sub"><?php esc_html_e( 'Cards & PayPal balance', 'ecab-taxi-booking-manager' ); ?></span>
							</span>
						</div>
						<?php if ( $is_pro ) : ?>
							<span class="gateway-status <?php echo $pp_enabled ? 'active' : ''; ?>"><?php echo esc_html( $pp_enabled ? $enabled_txt : $disabled_txt ); ?></span>
						<?php endif; ?>
						<div class="gateway-actions">
							<?php if ( $is_pro ) : ?>
								<button type="button" class="gateway-configure-btn" id="mptbm-paypal-configure-btn"><?php esc_html_e( 'Configure', 'ecab-taxi-booking-manager' ); ?></button>
							<?php else : ?>
								<?php echo wp_kses_post( $pro_badge ); ?>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- Stripe Card -->
				<div class="gateway-card stripe-card">
					<div class="gateway-header">
						<div class="gateway-id">
							<span class="gateway-icon">
								<svg width="26" height="26" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
									<path fill="#fff" d="M14.07 15.11c-1.85-.43-2.61-.79-2.61-1.63 0-.79.75-1.33 1.95-1.33 1.34 0 2.87.41 4.31 1.09V8.65c-1.39-.56-2.93-.84-4.52-.84-3.8 0-6.66 1.96-6.66 5.25 0 3.73 3.32 4.96 6.03 5.61 2.05.49 2.8.92 2.8 1.8 0 .86-.87 1.48-2.3 1.48-1.57 0-3.37-.53-5.06-1.54v4.75c1.67.75 3.59 1.13 5.51 1.13 4.13 0 7-2 7-5.34-.01-3.6-3.6-4.41-6.45-5.84z"/>
								</svg>
							</span>
							<span class="gateway-meta">
								<span class="gateway-name"><?php esc_html_e( 'Stripe', 'ecab-taxi-booking-manager' ); ?></span>
								<span class="gateway-sub"><?php esc_html_e( 'Credit & debit cards', 'ecab-taxi-booking-manager' ); ?></span>
							</span>
						</div>
						<?php if ( $is_pro ) : ?>
							<span class="gateway-status <?php echo $st_enabled ? 'active' : ''; ?>"><?php echo esc_html( $st_enabled ? $enabled_txt : $disabled_txt ); ?></span>
						<?php endif; ?>
						<div class="gateway-actions">
							<?php if ( $is_pro ) : ?>
								<button type="button" class="gateway-configure-btn" id="mptbm-stripe-configure-btn"><?php esc_html_e( 'Configure', 'ecab-taxi-booking-manager' ); ?></button>
							<?php else : ?>
								<?php echo wp_kses_post( $pro_badge ); ?>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- Offline Payment Card -->
				<div class="gateway-card offline-card">
					<div class="gateway-header">
						<div class="gateway-id">
							<span class="gateway-icon">
								<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M3 19h18a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1Z" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/>
									<path d="M2 10h20M6 14h4" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/>
								</svg>
							</span>
							<span class="gateway-meta">
								<span class="gateway-name"><?php esc_html_e( 'Offline Payment', 'ecab-taxi-booking-manager' ); ?></span>
								<span class="gateway-sub"><?php esc_html_e( 'Bank transfer, cash, pay on pickup', 'ecab-taxi-booking-manager' ); ?></span>
							</span>
						</div>
						<!-- Offline needs no online processor, so it is available in the free plugin. -->
						<span class="gateway-status <?php echo $off_enabled ? 'active' : ''; ?>"><?php echo esc_html( $off_enabled ? $enabled_txt : $disabled_txt ); ?></span>
						<div class="gateway-actions">
							<button type="button" class="gateway-configure-btn" id="mptbm-offline-configure-btn"><?php esc_html_e( 'Configure', 'ecab-taxi-booking-manager' ); ?></button>
						</div>
					</div>
				</div>

				<!-- Booking Confirmation Page -->
				<div class="mptbm-conf-page">
					<div class="mptbm-conf-page-label">
						<label><?php esc_html_e( 'Booking Confirmation Page', 'ecab-taxi-booking-manager' ); ?></label>
						<span><?php esc_html_e( 'In Standalone / Custom Payment mode, customers are shown a confirmation after booking. Optionally choose a dedicated page here.', 'ecab-taxi-booking-manager' ); ?></span>
					</div>
					<div class="mptbm-conf-page-field">
						<?php
							wp_dropdown_pages( array(
								'name'              => self::OPTION . '[mptbm_confirmation_page_id]',
								'id'                => 'mptbm_confirmation_page_id',
								'selected'          => $conf_page,
								'show_option_none'  => __( '— Default —', 'ecab-taxi-booking-manager' ),
								'option_none_value' => '0',
							) );
						?>
					</div>
				</div>

				<!-- Require customer login (Pro custom booking flow + portal) -->
				<?php $require_login = $this->opt( 'mptbm_require_login', 'no' ); ?>
				<div class="mptbm-conf-page">
					<div class="mptbm-conf-page-label">
						<label><?php esc_html_e( 'Require Customer Login', 'ecab-taxi-booking-manager' ); ?></label>
						<span><?php esc_html_e( 'When enabled, customers must log in (or register) before they can complete a Custom Payment booking or view the My Bookings portal. When disabled, guests can book and track by email + reference.', 'ecab-taxi-booking-manager' ); ?></span>
					</div>
					<div class="mptbm-conf-page-field">
						<?php if ( $is_pro ) : ?>
							<select name="<?php echo esc_attr( self::OPTION ); ?>[mptbm_require_login]">
								<option value="yes" <?php selected( $require_login, 'yes' ); ?>><?php esc_html_e( 'Yes — require login / registration', 'ecab-taxi-booking-manager' ); ?></option>
								<option value="no" <?php selected( $require_login, 'no' ); ?>><?php esc_html_e( 'No — allow guest checkout', 'ecab-taxi-booking-manager' ); ?></option>
							</select>
						<?php else : ?>
							<?php echo wp_kses_post( $pro_badge ); ?>
						<?php endif; ?>
					</div>
				</div>
				<?php
			}

			/** WooCommerce native payment-methods manager (inside the Payment Methods accordion). */
			public function render_wc_payment_manager() {
				if ( class_exists( 'WooCommerce' ) && class_exists( 'MPTBM_WC_Payment_Manager' ) ) {
					MPTBM_WC_Payment_Manager::instance()->render();
				}
			}

			/** WooCommerce install / activate modal (footer). */
			public function render_wc_warning_modal() {
				if ( ! $this->is_settings_screen() || $this->has_woo() ) {
					return;
				}
				$is_installed = file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' );
				$modal_desc   = $is_installed
					? __( 'WooCommerce is already installed but not active. Click the button below to activate it now.', 'ecab-taxi-booking-manager' )
					: __( 'WooCommerce is required to process payments through the cart/checkout flow. We will securely download, install, and activate it for you now.', 'ecab-taxi-booking-manager' );
				$modal_btn    = $is_installed
					? __( 'Activate WooCommerce Now', 'ecab-taxi-booking-manager' )
					: __( 'Install &amp; Activate Now', 'ecab-taxi-booking-manager' );
				?>
				<div id="mptbm-wc-install-modal" style="display:none;position:fixed;z-index:999999;inset:0;background:rgba(0,0,0,0.6);align-items:center;justify-content:center;">
					<div style="background:#fff;border-radius:12px;width:520px;max-width:92vw;box-shadow:0 10px 40px rgba(0,0,0,0.35);overflow:hidden;">
						<div style="padding:18px 24px;border-bottom:1px solid #e2e4e7;display:flex;justify-content:space-between;align-items:center;background:#f8f9fa;">
							<h3 style="margin:0;font-size:17px;color:#2c3338;display:flex;align-items:center;gap:8px;">
								<span class="dashicons dashicons-plugins-checked" style="font-size:20px;color:#2271b1;"></span>
								<?php esc_html_e( 'Set Up WooCommerce', 'ecab-taxi-booking-manager' ); ?>
							</h3>
							<button type="button" id="mptbm-wc-install-modal-close" style="background:none;border:none;font-size:24px;line-height:1;cursor:pointer;color:#666;padding:0;">&times;</button>
						</div>
						<div style="padding:24px;">
							<div id="mptbm-wc-modal-info">
								<p style="margin:0 0 18px;font-size:14px;color:#3c434a;line-height:1.6;"><?php echo esc_html( $modal_desc ); ?></p>
								<button type="button" id="mptbm-wc-modal-action-btn" class="button button-primary" style="white-space:nowrap;padding:6px 18px;"><?php echo wp_kses_post( $modal_btn ); ?></button>
							</div>
							<div id="mptbm-wc-modal-progress" style="display:none;">
								<div style="width:100%;height:8px;background:#f0f0f1;border-radius:100px;overflow:hidden;margin-bottom:10px;">
									<div id="mptbm-wc-modal-progress-fill" style="height:100%;width:0%;border-radius:100px;background:linear-gradient(90deg,#7b5ea7,#9b72cf);transition:width 0.5s cubic-bezier(0.16,1,0.3,1);"></div>
								</div>
								<p id="mptbm-wc-modal-status-text" style="font-size:13px;color:#50575e;margin:0;text-align:center;min-height:20px;"></p>
							</div>
						</div>
					</div>
				</div>
				<script>
				jQuery(function($){
					var mptbmWcIsInstalled = <?php echo $is_installed ? 'true' : 'false'; ?>;
					var mptbmWcNonce       = '<?php echo esc_js( wp_create_nonce( 'mptbm_install_wc' ) ); ?>';

					$(document).on('click', '.mptbm-install-wc-trigger', function(e){
						e.preventDefault();
						$('#mptbm-wc-install-modal').css('display','flex').hide().fadeIn(200);
					});
					$('#mptbm-wc-install-modal-close').on('click', function(){ $('#mptbm-wc-install-modal').fadeOut(200); });
					$(document).on('click', '#mptbm-wc-install-modal', function(e){
						if ($(e.target).is('#mptbm-wc-install-modal')) { $(this).fadeOut(200); }
					});

					$('#mptbm-wc-modal-action-btn').on('click', function(){
						var $info=$('#mptbm-wc-modal-info'), $progress=$('#mptbm-wc-modal-progress'),
						    $fill=$('#mptbm-wc-modal-progress-fill'), $status=$('#mptbm-wc-modal-status-text');
						$info.hide(); $fill.css('width','0%'); $progress.fadeIn(200);
						var texts = mptbmWcIsInstalled
							? [<?php echo implode( ',', array_map( 'wp_json_encode', array(
								__( 'Activating WooCommerce...', 'ecab-taxi-booking-manager' ),
								__( 'Configuring settings...', 'ecab-taxi-booking-manager' ),
								__( 'Finalizing setup...', 'ecab-taxi-booking-manager' ),
							) ) ); ?>]
							: [<?php echo implode( ',', array_map( 'wp_json_encode', array(
								__( 'Downloading WooCommerce...', 'ecab-taxi-booking-manager' ),
								__( 'Installing WooCommerce...', 'ecab-taxi-booking-manager' ),
								__( 'Activating WooCommerce...', 'ecab-taxi-booking-manager' ),
								__( 'Configuring settings...', 'ecab-taxi-booking-manager' ),
								__( 'Finalizing...', 'ecab-taxi-booking-manager' ),
							) ) ); ?>];
						var duration=mptbmWcIsInstalled?3000:15000, startTime=Date.now(), isDone=false, frameId;
						$status.text(texts[0]);
						function animateBar(){
							if(isDone) return;
							var raw=Math.min((Date.now()-startTime)/duration,1), pct=raw*(2-raw)*95;
							$fill.css('width',pct+'%');
							var idx=Math.min(Math.floor((pct/95)*texts.length),texts.length-1);
							$status.text(texts[idx]+' '+Math.round(pct)+'%');
							if(pct<95) frameId=requestAnimationFrame(animateBar);
						}
						frameId=requestAnimationFrame(animateBar);
						$.ajax({
							url: ajaxurl, type:'POST',
							data:{ action:'mptbm_install_activate_wc', nonce:mptbmWcNonce },
							success: function(response){
								var minWait=mptbmWcIsInstalled?1500:3000, leftover=Math.max(0,minWait-(Date.now()-startTime));
								setTimeout(function(){
									isDone=true; cancelAnimationFrame(frameId); $fill.css('width','100%');
									if(response.success){
										$status.css('color','#039855').text(<?php echo wp_json_encode( __( 'Successfully Activated! 100%', 'ecab-taxi-booking-manager' ) ); ?>);
										setTimeout(function(){ location.reload(); }, 1200);
									} else {
										$status.css('color','#d92d20').text(<?php echo wp_json_encode( __( 'Error: ', 'ecab-taxi-booking-manager' ) ); ?> + (response.data||'Unknown error'));
										setTimeout(function(){ $progress.hide(); $info.show(); }, 5000);
									}
								}, leftover);
							},
							error: function(){
								isDone=true; cancelAnimationFrame(frameId); $fill.css('width','100%');
								$status.css('color','#d92d20').text(<?php echo wp_json_encode( __( 'A network error occurred. Please try again.', 'ecab-taxi-booking-manager' ) ); ?>);
								setTimeout(function(){ $progress.hide(); $info.show(); }, 5000);
							}
						});
					});
				});
				</script>
				<?php
			}

			/** PayPal / Stripe / Offline Configure modals (footer). Pro-only for PayPal/Stripe. */
			public function render_gateway_modals() {
				if ( ! $this->is_settings_screen() ) {
					return;
				}
				$pp_enabled  = $this->opt( 'mptbm_paypal_enable' ) === 'on';
				$pp_sandbox  = $this->opt( 'mptbm_paypal_sandbox' ) === 'on';
				$pp_client   = esc_attr( $this->opt( 'mptbm_paypal_client_id' ) );
				$pp_secret   = esc_attr( $this->opt( 'mptbm_paypal_secret' ) );
				$st_enabled  = $this->opt( 'mptbm_stripe_enable' ) === 'on';
				$st_sandbox  = $this->opt( 'mptbm_stripe_sandbox' ) === 'on';
				$st_test_pub = esc_attr( $this->opt( 'mptbm_stripe_test_pub' ) );
				$st_test_sec = esc_attr( $this->opt( 'mptbm_stripe_test_sec' ) );
				$st_live_pub = esc_attr( $this->opt( 'mptbm_stripe_live_pub' ) );
				$st_live_sec = esc_attr( $this->opt( 'mptbm_stripe_live_sec' ) );
				$off_enabled = MPTBM_Function::offline_payment_enabled();
				$off_label   = esc_attr( $this->opt( 'mptbm_offline_label', __( 'Offline Payment', 'ecab-taxi-booking-manager' ) ) );
				$nonce       = wp_create_nonce( 'mptbm_save_gateway' );
				$is_pro      = $this->is_pro();
				?>
				<style>
				.mptbm-gw-modal{display:none;position:fixed;inset:0;z-index:999999;background:rgba(10,10,30,0.65);align-items:center;justify-content:center;backdrop-filter:blur(3px);}
				.mptbm-gw-modal-box{background:#fff;border-radius:16px;width:540px;max-width:94vw;max-height:92vh;overflow-y:auto;box-shadow:0 24px 64px rgba(0,0,0,0.3);}
				.mptbm-gw-modal-header{padding:22px 26px;display:flex;align-items:center;justify-content:space-between;border-radius:16px 16px 0 0;}
				.mptbm-gw-modal-header h2{margin:0;font-size:19px;font-weight:700;color:#fff;display:flex;align-items:center;gap:12px;}
				.mptbm-gw-modal-close{background:rgba(255,255,255,0.2);border:none;border-radius:50%;width:34px;height:34px;font-size:20px;line-height:1;cursor:pointer;color:#fff;display:flex;align-items:center;justify-content:center;}
				.mptbm-gw-modal-body{padding:26px 26px 10px;}
				.mptbm-gw-field{margin-bottom:20px;}
				.mptbm-gw-field label.mptbm-gw-label{display:block;font-weight:600;font-size:13px;color:#374151;margin-bottom:7px;}
				.mptbm-gw-field input[type="text"],.mptbm-gw-field input[type="password"]{width:100%;padding:10px 14px;border:1.5px solid #d1d5db;border-radius:8px;font-size:14px;color:#111;background:#f9fafb;box-sizing:border-box;}
				.mptbm-gw-field input[type="text"]:focus,.mptbm-gw-field input[type="password"]:focus{border-color:#F12971;box-shadow:0 0 0 3px rgba(241,41,113,0.12);outline:none;background:#fff;}
				.mptbm-gw-toggle-row{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;background:#f9fafb;border-radius:10px;margin-bottom:20px;border:1.5px solid #e5e7eb;}
				.mptbm-gw-toggle-label{font-weight:600;font-size:14px;color:#111827;}
				.mptbm-gw-toggle-sub{font-size:12px;color:#6b7280;margin-top:2px;}
				.mptbm-gw-divider{border:none;border-top:1px solid #e5e7eb;margin:4px 0 20px;}
				.mptbm-gw-section-title{font-size:12px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:14px;}
				.mptbm-gw-modal-footer{padding:16px 26px 22px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;}
				.mptbm-gw-save-btn{padding:11px 28px;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;color:#fff;flex-shrink:0;}
				.mptbm-gw-save-msg{display:none;padding:9px 14px;border-radius:7px;font-size:13px;font-weight:500;flex:1;}
				.mptbm-gw-switch{position:relative;display:inline-block;width:48px;height:26px;flex-shrink:0;}
				.mptbm-gw-switch input{opacity:0;width:0;height:0;}
				.mptbm-gw-slider{position:absolute;cursor:pointer;inset:0;background:#d1d5db;border-radius:26px;transition:0.3s;}
				.mptbm-gw-slider:before{content:"";position:absolute;height:20px;width:20px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:0.3s;box-shadow:0 1px 3px rgba(0,0,0,0.2);}
				.mptbm-gw-switch input:checked + .mptbm-gw-slider{background:#22c55e;}
				.mptbm-gw-switch input:checked + .mptbm-gw-slider:before{transform:translateX(22px);}
				</style>

				<?php if ( $is_pro ) : ?>
				<!-- PayPal Config Modal -->
				<div id="mptbm-paypal-modal" class="mptbm-gw-modal">
					<div class="mptbm-gw-modal-box">
						<div class="mptbm-gw-modal-header" style="background:linear-gradient(135deg,#003087 0%,#0079C1 100%);">
							<h2><?php esc_html_e( 'PayPal Configuration', 'ecab-taxi-booking-manager' ); ?></h2>
							<button type="button" class="mptbm-gw-modal-close">&times;</button>
						</div>
						<div class="mptbm-gw-modal-body">
							<div class="mptbm-gw-toggle-row">
								<div>
									<div class="mptbm-gw-toggle-label"><?php esc_html_e( 'Enable PayPal', 'ecab-taxi-booking-manager' ); ?></div>
									<div class="mptbm-gw-toggle-sub"><?php esc_html_e( 'Accept payments via PayPal', 'ecab-taxi-booking-manager' ); ?></div>
								</div>
								<label class="mptbm-gw-switch"><input type="checkbox" data-field="mptbm_paypal_enable" <?php checked( $pp_enabled ); ?>><span class="mptbm-gw-slider"></span></label>
							</div>
							<div class="mptbm-gw-toggle-row">
								<div>
									<div class="mptbm-gw-toggle-label"><?php esc_html_e( 'Sandbox / Test Mode', 'ecab-taxi-booking-manager' ); ?></div>
									<div class="mptbm-gw-toggle-sub"><?php esc_html_e( 'Use sandbox credentials for testing', 'ecab-taxi-booking-manager' ); ?></div>
								</div>
								<label class="mptbm-gw-switch"><input type="checkbox" data-field="mptbm_paypal_sandbox" <?php checked( $pp_sandbox ); ?>><span class="mptbm-gw-slider"></span></label>
							</div>
							<hr class="mptbm-gw-divider">
							<p class="mptbm-gw-section-title"><?php esc_html_e( 'API Credentials', 'ecab-taxi-booking-manager' ); ?></p>
							<div class="mptbm-gw-field">
								<label class="mptbm-gw-label"><?php esc_html_e( 'PayPal Client ID', 'ecab-taxi-booking-manager' ); ?></label>
								<input type="text" data-field="mptbm_paypal_client_id" value="<?php echo $pp_client; ?>" placeholder="<?php esc_attr_e( 'Enter your PayPal Client ID', 'ecab-taxi-booking-manager' ); ?>">
							</div>
							<div class="mptbm-gw-field">
								<label class="mptbm-gw-label"><?php esc_html_e( 'PayPal Secret Key', 'ecab-taxi-booking-manager' ); ?></label>
								<input type="password" data-field="mptbm_paypal_secret" value="<?php echo $pp_secret; ?>" placeholder="<?php esc_attr_e( 'Enter your PayPal Secret Key', 'ecab-taxi-booking-manager' ); ?>">
							</div>
						</div>
						<div class="mptbm-gw-modal-footer">
							<button type="button" class="mptbm-gw-save-btn" data-gateway="paypal" style="background:linear-gradient(135deg,#003087,#0079C1);"><?php esc_html_e( 'Save PayPal Settings', 'ecab-taxi-booking-manager' ); ?></button>
							<span class="mptbm-gw-save-msg"></span>
						</div>
					</div>
				</div>

				<!-- Stripe Config Modal -->
				<div id="mptbm-stripe-modal" class="mptbm-gw-modal">
					<div class="mptbm-gw-modal-box">
						<div class="mptbm-gw-modal-header" style="background:linear-gradient(135deg,#635bff 0%,#3f36c5 100%);">
							<h2><?php esc_html_e( 'Stripe Configuration', 'ecab-taxi-booking-manager' ); ?></h2>
							<button type="button" class="mptbm-gw-modal-close">&times;</button>
						</div>
						<div class="mptbm-gw-modal-body">
							<div class="mptbm-gw-toggle-row">
								<div>
									<div class="mptbm-gw-toggle-label"><?php esc_html_e( 'Enable Stripe', 'ecab-taxi-booking-manager' ); ?></div>
									<div class="mptbm-gw-toggle-sub"><?php esc_html_e( 'Accept payments via Stripe', 'ecab-taxi-booking-manager' ); ?></div>
								</div>
								<label class="mptbm-gw-switch"><input type="checkbox" data-field="mptbm_stripe_enable" <?php checked( $st_enabled ); ?>><span class="mptbm-gw-slider"></span></label>
							</div>
							<div class="mptbm-gw-toggle-row">
								<div>
									<div class="mptbm-gw-toggle-label"><?php esc_html_e( 'Sandbox / Test Mode', 'ecab-taxi-booking-manager' ); ?></div>
									<div class="mptbm-gw-toggle-sub"><?php esc_html_e( 'Use test keys instead of live keys', 'ecab-taxi-booking-manager' ); ?></div>
								</div>
								<label class="mptbm-gw-switch"><input type="checkbox" data-field="mptbm_stripe_sandbox" <?php checked( $st_sandbox ); ?>><span class="mptbm-gw-slider"></span></label>
							</div>
							<hr class="mptbm-gw-divider">
							<p class="mptbm-gw-section-title"><?php esc_html_e( 'Test / Sandbox Keys', 'ecab-taxi-booking-manager' ); ?></p>
							<div class="mptbm-gw-field">
								<label class="mptbm-gw-label"><?php esc_html_e( 'Test Publishable Key', 'ecab-taxi-booking-manager' ); ?></label>
								<input type="text" data-field="mptbm_stripe_test_pub" value="<?php echo $st_test_pub; ?>" placeholder="pk_test_...">
							</div>
							<div class="mptbm-gw-field">
								<label class="mptbm-gw-label"><?php esc_html_e( 'Test Secret Key', 'ecab-taxi-booking-manager' ); ?></label>
								<input type="password" data-field="mptbm_stripe_test_sec" value="<?php echo $st_test_sec; ?>" placeholder="sk_test_...">
							</div>
							<hr class="mptbm-gw-divider">
							<p class="mptbm-gw-section-title"><?php esc_html_e( 'Live Keys', 'ecab-taxi-booking-manager' ); ?></p>
							<div class="mptbm-gw-field">
								<label class="mptbm-gw-label"><?php esc_html_e( 'Live Publishable Key', 'ecab-taxi-booking-manager' ); ?></label>
								<input type="text" data-field="mptbm_stripe_live_pub" value="<?php echo $st_live_pub; ?>" placeholder="pk_live_...">
							</div>
							<div class="mptbm-gw-field">
								<label class="mptbm-gw-label"><?php esc_html_e( 'Live Secret Key', 'ecab-taxi-booking-manager' ); ?></label>
								<input type="password" data-field="mptbm_stripe_live_sec" value="<?php echo $st_live_sec; ?>" placeholder="sk_live_...">
							</div>
						</div>
						<div class="mptbm-gw-modal-footer">
							<button type="button" class="mptbm-gw-save-btn" data-gateway="stripe" style="background:linear-gradient(135deg,#635bff,#3f36c5);"><?php esc_html_e( 'Save Stripe Settings', 'ecab-taxi-booking-manager' ); ?></button>
							<span class="mptbm-gw-save-msg"></span>
						</div>
					</div>
				</div>
				<?php endif; ?>

				<!-- Offline Payment Config Modal (free - no online processor needed). -->
				<div id="mptbm-offline-modal" class="mptbm-gw-modal">
					<div class="mptbm-gw-modal-box">
						<div class="mptbm-gw-modal-header" style="background:linear-gradient(135deg,#0f766e 0%,#115e59 100%);">
							<h2><?php esc_html_e( 'Offline Payment Configuration', 'ecab-taxi-booking-manager' ); ?></h2>
							<button type="button" class="mptbm-gw-modal-close">&times;</button>
						</div>
						<div class="mptbm-gw-modal-body">
							<div class="mptbm-gw-toggle-row">
								<div>
									<div class="mptbm-gw-toggle-label"><?php esc_html_e( 'Enable Offline Payment', 'ecab-taxi-booking-manager' ); ?></div>
									<div class="mptbm-gw-toggle-sub"><?php esc_html_e( 'Let customers pay offline (bank transfer, cash, pay on pickup).', 'ecab-taxi-booking-manager' ); ?></div>
								</div>
								<label class="mptbm-gw-switch"><input type="checkbox" data-field="mptbm_offline_enable" <?php checked( $off_enabled ); ?>><span class="mptbm-gw-slider"></span></label>
							</div>
							<hr class="mptbm-gw-divider">
							<div class="mptbm-gw-field">
								<label class="mptbm-gw-label"><?php esc_html_e( 'Payment Label', 'ecab-taxi-booking-manager' ); ?></label>
								<input type="text" data-field="mptbm_offline_label" value="<?php echo $off_label; ?>" placeholder="<?php esc_attr_e( 'e.g. Pay on Pickup / Bank Transfer', 'ecab-taxi-booking-manager' ); ?>">
								<p style="margin:8px 0 0;font-size:12px;color:#6b7280;"><?php esc_html_e( 'This label is shown to customers on the frontend payment step.', 'ecab-taxi-booking-manager' ); ?></p>
							</div>
						</div>
						<div class="mptbm-gw-modal-footer">
							<button type="button" class="mptbm-gw-save-btn" data-gateway="offline" style="background:linear-gradient(135deg,#0f766e,#115e59);"><?php esc_html_e( 'Save Offline Settings', 'ecab-taxi-booking-manager' ); ?></button>
							<span class="mptbm-gw-save-msg"></span>
						</div>
					</div>
				</div>

				<script>
				var mptbmGateway = <?php echo wp_json_encode( array(
					'nonce'    => $nonce,
					'enabled'  => __( 'Enabled', 'ecab-taxi-booking-manager' ),
					'disabled' => __( 'Disabled', 'ecab-taxi-booking-manager' ),
				) ); ?>;
				jQuery(function($){
					$(document).on('click', '#mptbm-paypal-configure-btn', function(e){ e.preventDefault(); $('#mptbm-paypal-modal').css('display','flex').hide().fadeIn(220); });
					$(document).on('click', '#mptbm-stripe-configure-btn', function(e){ e.preventDefault(); $('#mptbm-stripe-modal').css('display','flex').hide().fadeIn(220); });
					$(document).on('click', '#mptbm-offline-configure-btn', function(e){ e.preventDefault(); $('#mptbm-offline-modal').css('display','flex').hide().fadeIn(220); });
					$(document).on('click', '.mptbm-gw-modal-close', function(){ $('.mptbm-gw-modal').fadeOut(200); });
					$(document).on('click', '.mptbm-gw-modal', function(e){ if ($(e.target).hasClass('mptbm-gw-modal')) $(this).fadeOut(200); });

					$(document).on('click', '.mptbm-gw-save-btn', function(e){
						e.preventDefault();
						var $btn=$(this), $box=$btn.closest('.mptbm-gw-modal-box'), gateway=$btn.data('gateway'),
						    $msg=$box.find('.mptbm-gw-save-msg'), fields={};
						$box.find('input[data-field]').each(function(){
							var key=$(this).data('field');
							fields[key]=($(this).attr('type')==='checkbox') ? ($(this).is(':checked')?'on':'off') : $(this).val();
						});
						$btn.prop('disabled',true).css('opacity','0.7'); $msg.hide();
						$.ajax({
							url: ajaxurl, type:'POST',
							data:{ action:'mptbm_save_gateway_settings', nonce:mptbmGateway.nonce, gateway:gateway, fields:fields },
							success: function(res){
								if(res.success){
									$msg.css({'color':'#0f5132','background':'#d1e7dd','border':'1px solid #badbcc'}).text(res.data).fadeIn(200);
									setTimeout(function(){ $msg.fadeOut(400); }, 1200);
									var $badge=$('.'+gateway+'-card .gateway-status');
									if($badge.length){
										var isEnabled = fields['mptbm_'+gateway+'_enable']==='on';
										$badge.text(isEnabled?mptbmGateway.enabled:mptbmGateway.disabled).toggleClass('active',isEnabled);
									}
								} else {
									$msg.css({'color':'#842029','background':'#f8d7da','border':'1px solid #f5c2c7'}).text(res.data).fadeIn(200);
									setTimeout(function(){ $msg.fadeOut(400); }, 1500);
								}
							},
							error: function(){
								$msg.css({'color':'#842029','background':'#f8d7da','border':'1px solid #f5c2c7'}).text('A network error occurred.').fadeIn(200);
								setTimeout(function(){ $msg.fadeOut(400); }, 1500);
							},
							complete: function(){ $btn.prop('disabled',false).css('opacity','1'); }
						});
					});
				});
				</script>
				<?php
			}

			/** Sub-tab switching + gateway card styling (footer). */
			public function payment_tabs_script() {
				if ( ! $this->is_settings_screen() ) {
					return;
				}
				$wc_active = $this->has_woo() ? 'true' : 'false';
				?>
				<style>
				:root{--mptbm-pay-accent:#F12971;}
				/* Payments panel: consistent outer spacing + a comfortable content width
				   so cards and fields don't stretch edge-to-edge on wide screens. */
				div.tabsItem[data-tabs="#mptbm_payment_settings"]{padding:20px 24px 28px;}
				div.tabsItem[data-tabs="#mptbm_payment_settings"] form{max-width:100%;width:100%;}
				/* Make the MAIN settings table a full-width stacked layout (label above
				   field) so every WooCommerce / Custom Payment row uses the entire panel
				   width instead of WP's fixed two-column label/field grid. Scoped to the
				   form's direct table so the nested WooCommerce gateway tables
				   (.mptbm-gw-form-table) keep their own two-column layout. */
				div.tabsItem[data-tabs="#mptbm_payment_settings"] > form > .form-table,
				div.tabsItem[data-tabs="#mptbm_payment_settings"] > form > .form-table > tbody{display:block;width:100%;margin-top:0;}
				div.tabsItem[data-tabs="#mptbm_payment_settings"] > form > .form-table > tbody > tr{display:block;width:100%;}
				/* Inset the row content so labels/inputs aren't flush against the edge. */
				div.tabsItem[data-tabs="#mptbm_payment_settings"] > form > .form-table > tbody > tr > th{display:block;width:100%;padding:16px 24px 4px;font-size:13px;font-weight:600;color:#1d2327;}
				div.tabsItem[data-tabs="#mptbm_payment_settings"] > form > .form-table > tbody > tr > td{display:block;width:100%;padding:0 24px 16px !important;}
				/* Muted, spaced description text under labels. */
				div.tabsItem[data-tabs="#mptbm_payment_settings"] .info_text{display:block;margin-top:5px;font-size:12px;font-weight:400;color:#6b7280;line-height:1.55;}
				/* Checkbox rows already show their description beside the control, so hide
				   the duplicated label description for them and space the control. */
				div.tabsItem[data-tabs="#mptbm_payment_settings"] tr.mptbm-check-row .info_text{display:none;}
				div.tabsItem[data-tabs="#mptbm_payment_settings"] tr.mptbm-check-row td .checkbox{margin-right:8px;}
				/* Additional Settings accordion fields: standard ecab two-column row
				   (label + description on the left, control on the right), grouped into a
				   single bordered panel so the content reads as the accordion's body.
				   NOTE: no !important on display so the accordion's jQuery show/hide
				   (inline display:none) can still collapse these rows. */
				div.tabsItem[data-tabs="#mptbm_payment_settings"] > form > .form-table > tbody > tr.wc-additional-field{
					display:flex;align-items:flex-start;gap:0;width:100%;box-sizing:border-box;
					background:#fff;border-left:1px solid #e7e8ec;border-right:1px solid #e7e8ec;border-bottom:1px solid #eef0f3;
				}
				div.tabsItem[data-tabs="#mptbm_payment_settings"] > form > .form-table > tbody > tr.wc-additional-field > th{flex:0 0 240px;width:240px !important;max-width:240px;padding:18px 24px !important;background:transparent;}
				/* Give the value/input column its own left padding so the control is spaced
				   away from the label column instead of sitting at the edge. */
				div.tabsItem[data-tabs="#mptbm_payment_settings"] > form > .form-table > tbody > tr.wc-additional-field > td{flex:1 1 auto;width:auto !important;padding:18px 24px 18px 28px !important;background:transparent;}
				div.tabsItem[data-tabs="#mptbm_payment_settings"] > form > .form-table > tbody > tr.wc-additional-first{border-top:1px solid #e7e8ec;border-radius:12px 12px 0 0;margin-top:8px;}
				div.tabsItem[data-tabs="#mptbm_payment_settings"] > form > .form-table > tbody > tr.wc-additional-last{border-bottom:1px solid #e7e8ec;border-radius:0 0 12px 12px;}
				div.tabsItem[data-tabs="#mptbm_payment_settings"] > form > .form-table .formControl{max-width:340px;}
				div.tabsItem[data-tabs="#mptbm_payment_settings"] .submit{margin:20px 0 0;padding-top:20px;border-top:1px solid #e7e8ec;}
				/* Anchor for the mode-driven sections. It used to hold the WooCommerce /
				   Custom Payment sub-tab bar; that was removed (the Booking Mode cards are
				   the single switch), so it is now an unstyled hook - the accordion script
				   still uses its row as the insertion point. Any spacing comes from the
				   callout inside it, so with WooCommerce active it takes up no room. */

				/* WooCommerce-not-activated callout (modern) */
				.mptbm-wc-callout{background:linear-gradient(180deg,#fffdf6,#fff8e8);border:1px solid #f2e0b0;border-radius:14px;padding:18px;margin:16px 0 6px;box-shadow:0 1px 2px rgba(16,24,40,0.03);}
				.mptbm-wc-callout .mptbm-wc-callout-head{display:flex !important;align-items:center;gap:12px;margin-bottom:9px;}
				.mptbm-wc-callout .mptbm-wc-callout-icon{flex:0 0 auto;width:40px;height:40px;border-radius:11px;background:#fff2cf;color:#c07d16;border:1px solid #f2d484;display:flex !important;align-items:center;justify-content:center;}
				.mptbm-wc-callout .mptbm-wc-callout-icon svg{width:21px;height:21px;display:block;}
				.mptbm-wc-callout .mptbm-wc-callout-title{margin:0;padding:0;font-size:15px;font-weight:700;color:#1d2327;line-height:1.3;text-transform:none;}
				.mptbm-wc-callout-text{margin:0 0 15px;font-size:13px;color:#6b7280;line-height:1.55;max-width:720px;}
				.mptbm-wc-callout-btn{display:inline-flex;align-items:center;gap:8px;height:38px;padding:0 18px;border:none;border-radius:9px;background:#7f54b3;color:#fff !important;font-size:13.5px;font-weight:600;cursor:pointer;line-height:1;box-shadow:0 2px 6px rgba(127,84,179,0.3);transition:all .18s ease;text-decoration:none;}
				.mptbm-wc-callout-btn:hover{background:#6b4599;transform:translateY(-1px);box-shadow:0 5px 14px rgba(127,84,179,0.34);color:#fff !important;}
				.mptbm-wc-callout-btn:active{transform:translateY(0);}
				.mptbm-wc-callout-btn svg{width:16px;height:16px;display:block;}

				/* Custom Payment intro */
				.mptbm-gw-intro{margin:4px 0 20px;}
				.mptbm-gw-intro h3{margin:0 0 6px;font-size:16px;font-weight:700;color:#1d2327;}
				.mptbm-gw-intro p{margin:0;font-size:13px;color:#6b7280;max-width:680px;line-height:1.6;}

				/* Gateway cards (Custom Payment) - light, modern palette */
				.payment-gateways-container th{display:none;}
				.payment-gateways-container td{padding:0 !important;}
				.gateway-card{position:relative;background:#fff;border:1px solid #eceef2;border-left:4px solid #cbd5e1;border-radius:14px;margin-bottom:13px;box-shadow:0 1px 2px rgba(16,24,40,0.04);width:100%;box-sizing:border-box;color:#1d2327;overflow:hidden;transition:transform 0.18s ease,box-shadow 0.18s ease,border-color 0.18s ease;}
				.gateway-card:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(16,24,40,0.10);}
				.gateway-card .gateway-header{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:16px 20px;}
				.gateway-card .gateway-id{display:flex;align-items:center;gap:14px;min-width:0;flex:1 1 0;}
				.gateway-card .gateway-icon{flex:0 0 auto;width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;box-shadow:0 4px 10px rgba(16,24,40,0.13);}
				.gateway-card .gateway-meta{display:flex;flex-direction:column;min-width:0;}
				.gateway-card .gateway-name{font-size:15.5px;font-weight:700;color:#1d2327;line-height:1.3;}
				.gateway-card .gateway-sub{font-size:12px;color:#6b7280;line-height:1.4;}
				.gateway-card .gateway-actions{display:flex;align-items:center;justify-content:flex-end;gap:12px;flex:1 1 0;}
				.gateway-card .gateway-status{display:inline-block;min-width:74px;text-align:center;font-size:10.5px;text-transform:uppercase;letter-spacing:0.4px;padding:4px 11px;border-radius:20px;background:#f1f5f9;color:#64748b;border:1px solid #e5e9f0;font-weight:700;}
				.gateway-card .gateway-status.active{background:#dcfce7;color:#15803d;border-color:#bbf7d0;}
				/* Per-brand: soft tinted card + accent stripe + vibrant icon badge */
				.gateway-card.paypal-card{background:#f4f9fe;border-left-color:#0070ba;}
				.gateway-card.paypal-card .gateway-icon{background:linear-gradient(135deg,#0079C1,#003087);}
				.gateway-card.stripe-card{background:#f6f5ff;border-left-color:#635bff;}
				.gateway-card.stripe-card .gateway-icon{background:linear-gradient(135deg,#7a73ff,#4f46e5);}
				.gateway-card.offline-card{background:#f0faf8;border-left-color:#14b8a6;}
				.gateway-card.offline-card .gateway-icon{background:linear-gradient(135deg,#14b8a6,#0f766e);}
				.gateway-card .gateway-configure-btn{cursor:pointer;color:#fff !important;border:none !important;font-weight:600 !important;font-size:13px !important;border-radius:8px !important;padding:7px 16px !important;line-height:1.4 !important;box-shadow:0 2px 6px rgba(16,24,40,0.14) !important;transition:transform 0.15s ease,opacity 0.15s ease;}
				.gateway-card.paypal-card .gateway-configure-btn{background:#0070ba !important;}
				.gateway-card.stripe-card .gateway-configure-btn{background:#635bff !important;}
				.gateway-card.offline-card .gateway-configure-btn{background:#0f766e !important;}
				.gateway-card .gateway-configure-btn:hover{transform:translateY(-1px);opacity:0.94;}
				.mptbm-gw-pro-badge{background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#fff;padding:5px 12px;border-radius:20px;font-weight:800;font-size:10.5px;text-transform:uppercase;letter-spacing:0.5px;box-shadow:0 2px 6px rgba(245,158,11,0.3);}

				/* Booking confirmation page */
				.mptbm-conf-page{margin-top:10px;padding:20px 22px;display:flex;align-items:center;gap:24px;flex-wrap:wrap;background:#fff;border:1px solid #eceef2;border-radius:14px;box-shadow:0 1px 2px rgba(16,24,40,0.04);transition:border-color 0.18s ease,box-shadow 0.18s ease;}
				.mptbm-conf-page:hover{border-color:#dcdfe6;box-shadow:0 4px 14px rgba(16,24,40,0.06);}
				.mptbm-conf-page-label{flex:1 1 260px;}
				.mptbm-conf-page-label label{display:block;font-weight:700;font-size:14px;color:#1d2327;margin:0 0 4px;}
				.mptbm-conf-page-label span{display:block;font-size:12px;color:#6b7280;line-height:1.6;}
				.mptbm-conf-page-field{flex:0 0 auto;}
				.mptbm-conf-page-field select{width:100%;max-width:320px;min-width:230px;border:1px solid #d1d5db;border-radius:9px;padding:9px 13px;font-size:13px;font-weight:500;color:#334155;background:#fff;transition:border-color 0.18s ease,box-shadow 0.18s ease;}
				.mptbm-conf-page-field select:hover{border-color:#9aa4b2;}
				.mptbm-conf-page-field select:focus{border-color:var(--mptbm-pay-accent);box-shadow:0 0 0 3px rgba(241,41,113,0.16);outline:none;}

				/* WooCommerce sub-tab accordions */
				tr.mptbm-acc-header > td.mptbm-acc-header-cell{padding:0 !important;}
				tr.mptbm-acc-header .mptbm-acc-bar{display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:pointer;user-select:none;background:#fff;border:1px solid #e7e8ec;border-radius:10px;padding:14px 22px;margin:16px 0 0;transition:background 0.2s ease,border-color 0.2s ease,box-shadow 0.2s ease;}
				tr.mptbm-acc-header .mptbm-acc-bar:hover{border-color:#d4b3c3;box-shadow:0 2px 8px rgba(16,24,40,0.06);}
				tr.mptbm-acc-header.open .mptbm-acc-bar{background:#fdf2f7;border-color:var(--mptbm-pay-accent);}
				tr.mptbm-acc-header .mptbm-acc-title{display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:#1d2327;margin:0;}
				tr.mptbm-acc-header.open .mptbm-acc-title{color:var(--mptbm-pay-accent);}
				tr.mptbm-acc-header .mptbm-acc-arrow{transition:transform 0.2s ease;color:#50575e;line-height:1;}
				tr.mptbm-acc-header.open .mptbm-acc-arrow{transform:rotate(180deg);color:var(--mptbm-pay-accent);}
				/* The accordion header already shows the title; hide the manager's own duplicate heading but keep its bar (it holds the "Open in WooCommerce" link). */
				tr.wc-payment-methods-field .mptbm-wc-pm-heading{display:none;}
				/* Align the WooCommerce payment-methods manager with its accordion bar
				   (drop the inherited horizontal cell padding so the cards line up). */
				div.tabsItem[data-tabs="#mptbm_payment_settings"] > form > .form-table > tbody > tr.wc-payment-methods-field > td{padding:0 0 8px !important;}
				tr.wc-payment-methods-field .mptbm-wc-payment-manager{margin-top:8px;padding:0;}
				/* WooCommerce enable toggle row + additional fields: lighter rows */
				tr.woocommerce-field td, tr.no-woocommerce-field td{vertical-align:middle;}
				</style>
				<script>
				jQuery(function($){
					// Deep-link from the "Go to Payment Settings" admin notice: open the
					// Payments tab (and, when WooCommerce is inactive, nudge the WC card
					// into view) instead of landing on the default first tab.
					(function(){
						var params = new URLSearchParams(window.location.search || '');
						if (params.get('mptbm_tab') !== 'payments') { return; }
						var $target = $('[data-tabs-target="#<?php echo esc_js( self::OPTION ); ?>"]');
						if (!$target.length) { return; }
						setTimeout(function(){
							$target.trigger('click');
							var el = $target.get(0);
							if (el && el.scrollIntoView) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
						}, 200);
					})();

					var wcActive = <?php echo $wc_active; ?>;
					if ($('.payment-sub-tabs-wrapper').length === 0) { return; }

					// The mode actually in effect, resolved server-side. Used when the Booking
					// Mode cards aren't rendered (only one flow is available, so there is
					// nothing to choose) - the correct section must still be the visible one.
					var resolvedMode = <?php echo wp_json_encode( class_exists( 'MPTBM_Booking_Mode' ) ? MPTBM_Booking_Mode::get_mode() : 'woocommerce' ); ?>;

					var $paymentSubmit = $('div.tabsItem[data-tabs="#<?php echo esc_js( self::OPTION ); ?>"] .submit');

					// --- WooCommerce sub-tab accordions: Payment Methods (open) + Additional Settings (collapsed) ---
					var $methodsRows      = $('tr.wc-payment-methods-field');
					var $additionalRows   = $('tr.wc-additional-field');
					var $methodsHeader    = $();
					var $additionalHeader = $();

					function buildAccordionHeader(extraClass, title, isOpen){
						return $(
							'<tr class="woocommerce-field mptbm-acc-header '+extraClass+(isOpen?' open':'')+'">'+
								'<td colspan="2" class="mptbm-acc-header-cell">'+
									'<div class="mptbm-acc-bar">'+
										'<span class="mptbm-acc-title">'+title+'</span>'+
										'<span class="mptbm-acc-arrow dashicons dashicons-arrow-down-alt2"></span>'+
									'</div>'+
								'</td>'+
							'</tr>'
						);
					}

					function refreshAccordions(){
						if (!$methodsHeader.length) { return; }
						if ($methodsHeader.hasClass('open')) { $methodsRows.show(); } else { $methodsRows.hide(); }
						if ($additionalHeader.hasClass('open')) { $additionalRows.show(); } else { $additionalRows.hide(); }
					}

					if ($methodsRows.length || $additionalRows.length) {
						var $toggleRow = $('.payment-sub-tabs-wrapper').closest('tr');
						$methodsHeader    = buildAccordionHeader('mptbm-acc-methods', <?php echo wp_json_encode( __( 'WooCommerce Payment Methods', 'ecab-taxi-booking-manager' ) ); ?>, true);
						$additionalHeader = buildAccordionHeader('mptbm-acc-additional', <?php echo wp_json_encode( __( 'Additional Settings', 'ecab-taxi-booking-manager' ) ); ?>, false);

						// Make the payment-methods row span the full table width (drop the empty
						// label cell so the shared column widths don't squeeze sibling rows).
						$methodsRows.each(function(){
							var $r = $(this);
							$r.children('th').remove();
							$r.children('td').attr('colspan', 2);
						});

						// Re-order: toggle -> [Methods header + rows] -> [Additional header + rows].
						$methodsRows.detach();
						$additionalRows.detach();
						$toggleRow.after($methodsHeader);
						$methodsHeader.after($methodsRows);
						$methodsRows.last().after($additionalHeader);
						$additionalHeader.after($additionalRows);

						// Exclusive toggle: opening one closes the other.
						$methodsHeader.find('.mptbm-acc-bar').on('click', function(){
							var willOpen = !$methodsHeader.hasClass('open');
							$methodsHeader.toggleClass('open', willOpen);
							if (willOpen) { $additionalHeader.removeClass('open'); }
							refreshAccordions();
						});
						$additionalHeader.find('.mptbm-acc-bar').on('click', function(){
							var willOpen = !$additionalHeader.hasClass('open');
							$additionalHeader.toggleClass('open', willOpen);
							if (willOpen) { $methodsHeader.removeClass('open'); }
							refreshAccordions();
						});
					}

					// Which settings section is showing follows the Booking Mode - the selected
					// card when the selector is on screen, otherwise the server-resolved mode.
					function activeMode(){
						var $selected = $('.mptbm-bm-card.is-selected');
						return $selected.length ? String($selected.data('mode')) : resolvedMode;
					}

					function updateTabs(){
						$('tr.woocommerce-field, div.woocommerce-field, tr.no-woocommerce-field').hide();
						$paymentSubmit.show();
						if (activeMode() === 'custom') {
							$('tr.no-woocommerce-field').show();
						} else {
							$('div.woocommerce-field').show();
							if (wcActive) { $('tr.woocommerce-field').stop(true,true).show(); refreshAccordions(); }
						}
					}

					// Fired by the Booking Mode selector once a new mode has been saved.
					$(document).on('mptbm:mode-changed', updateTabs);

					// Move the anchor above the settings table so its callout spans full width.
					var $tabContainer = $('.payment-sub-tabs-wrapper');
					var $table = $tabContainer.closest('table.form-table');
					if ($table.length) {
						$tabContainer.insertBefore($table);
						$table.find('tr').each(function(){
							if ($(this).find('.payment-sub-tabs-wrapper').length === 0 && $(this).text().trim() === '') { $(this).hide(); }
						});
					}
					updateTabs();
				});
				</script>
				<?php
			}

			/** AJAX: save a single gateway's settings (real-time from its modal). */
			public function ajax_save_gateway_settings() {
				check_ajax_referer( 'mptbm_save_gateway', 'nonce' );
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error( __( 'Permission denied.', 'ecab-taxi-booking-manager' ) );
				}

				$gateway  = isset( $_POST['gateway'] ) ? sanitize_key( wp_unslash( $_POST['gateway'] ) ) : '';
				$fields   = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : array();
				$existing = get_option( self::OPTION, array() );
				if ( ! is_array( $existing ) ) {
					$existing = array();
				}

				$allowed = array(
					'paypal'  => array( 'mptbm_paypal_enable', 'mptbm_paypal_sandbox', 'mptbm_paypal_client_id', 'mptbm_paypal_secret' ),
					'stripe'  => array( 'mptbm_stripe_enable', 'mptbm_stripe_sandbox', 'mptbm_stripe_test_pub', 'mptbm_stripe_test_sec', 'mptbm_stripe_live_pub', 'mptbm_stripe_live_sec' ),
					'offline' => array( 'mptbm_offline_enable', 'mptbm_offline_label' ),
				);

				if ( ! isset( $allowed[ $gateway ] ) ) {
					wp_send_json_error( __( 'Invalid gateway.', 'ecab-taxi-booking-manager' ) );
				}

				// PayPal & Stripe configuration is Pro-only; never persist them from the free
				// build. Offline needs no online processor, so it stays configurable in free.
				if ( 'offline' !== $gateway && ! $this->is_pro() ) {
					wp_send_json_error( __( 'This gateway is available in the Pro version.', 'ecab-taxi-booking-manager' ) );
				}

				$toggles = array( 'mptbm_paypal_enable', 'mptbm_paypal_sandbox', 'mptbm_stripe_enable', 'mptbm_stripe_sandbox', 'mptbm_offline_enable' );
				foreach ( $allowed[ $gateway ] as $key ) {
					$val = isset( $fields[ $key ] ) ? $fields[ $key ] : 'off';
					if ( in_array( $key, $toggles, true ) ) {
						$existing[ $key ] = ( 'on' === $val ) ? 'on' : 'off';
					} else {
						$existing[ $key ] = sanitize_text_field( $val );
					}
				}

				update_option( self::OPTION, $existing );
				wp_send_json_success( __( 'Settings saved successfully!', 'ecab-taxi-booking-manager' ) );
			}

			/** AJAX: save the Booking Mode selector (real-time, no page reload). */
			public function ajax_save_booking_mode() {
				check_ajax_referer( 'mptbm_save_booking_mode', 'nonce' );
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error( __( 'Permission denied.', 'ecab-taxi-booking-manager' ) );
				}
				if ( ! class_exists( 'MPTBM_Booking_Mode' ) || 'both' !== MPTBM_Booking_Mode::availability() ) {
					wp_send_json_error( __( 'Booking mode cannot be changed right now.', 'ecab-taxi-booking-manager' ) );
				}

				$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
				if ( ! MPTBM_Booking_Mode::set_mode( $mode ) ) {
					wp_send_json_error( __( 'Invalid booking mode.', 'ecab-taxi-booking-manager' ) );
				}

				wp_send_json_success( array(
					'message'     => __( 'Booking mode saved.', 'ecab-taxi-booking-manager' ),
					'mode'        => $mode,
					'has_gateway' => MPTBM_Booking_Mode::has_gateway_for_active_mode(),
				) );
			}

			/** AJAX: install &/or activate WooCommerce. */
			public function ajax_install_activate_wc() {
				check_ajax_referer( 'mptbm_install_wc', 'nonce' );
				if ( ! current_user_can( 'install_plugins' ) ) {
					wp_send_json_error( __( 'Permission denied.', 'ecab-taxi-booking-manager' ) );
				}

				require_once ABSPATH . 'wp-admin/includes/plugin.php';
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
				require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
				require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/misc.php';

				$plugin_file = 'woocommerce/woocommerce.php';

				if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
					$api = plugins_api( 'plugin_information', array(
						'slug'   => 'woocommerce',
						'fields' => array( 'sections' => false ),
					) );
					if ( is_wp_error( $api ) ) {
						wp_send_json_error( $api->get_error_message() );
					}
					$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
					$result   = $upgrader->install( $api->download_link );
					if ( is_wp_error( $result ) ) {
						wp_send_json_error( $result->get_error_message() );
					} elseif ( ! $result ) {
						wp_send_json_error( __( 'Installation failed. Please try manually.', 'ecab-taxi-booking-manager' ) );
					}
				}

				// Activate via the options table to avoid loading woocommerce.php into this
				// process (which would clash with the wc_price()/WC() fallback shims).
				$active = get_option( 'active_plugins', array() );
				if ( ! in_array( $plugin_file, $active, true ) ) {
					$active[] = $plugin_file;
					sort( $active );
					update_option( 'active_plugins', $active );
				}
				do_action( 'activate_' . $plugin_file );
				do_action( 'activated_plugin', $plugin_file, false );

				wp_send_json_success( __( 'WooCommerce activated successfully!', 'ecab-taxi-booking-manager' ) );
			}

			/**
			 * Keep values saved outside this form (gateway credentials + the Booking Mode,
			 * which are written by their own AJAX handlers and never travel with the form)
			 * when the Settings API saves the rest. Only restores a key when it is ABSENT
			 * from the incoming value, so an AJAX save with new values is never clobbered.
			 */
			public function preserve_gateway_keys( $new_value, $old_value ) {
				$protected = array(
					'mptbm_paypal_enable', 'mptbm_paypal_sandbox', 'mptbm_paypal_client_id', 'mptbm_paypal_secret',
					'mptbm_stripe_enable', 'mptbm_stripe_sandbox', 'mptbm_stripe_test_pub', 'mptbm_stripe_test_sec',
					'mptbm_stripe_live_pub', 'mptbm_stripe_live_sec',
					'mptbm_offline_enable', 'mptbm_offline_label',
					'mptbm_booking_mode',
					'mptbm_require_login',
				);
				if ( ! is_array( $new_value ) ) {
					return $new_value;
				}
				if ( is_array( $old_value ) ) {
					foreach ( $protected as $key ) {
						if ( ! isset( $new_value[ $key ] ) && isset( $old_value[ $key ] ) ) {
							$new_value[ $key ] = $old_value[ $key ];
						}
					}
				}
				return $new_value;
			}
		}

		new MPTBM_Payment_Settings();
	endif;
