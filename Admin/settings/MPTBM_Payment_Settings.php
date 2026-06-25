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
	 * the free version shows a PRO badge. Offline payment is fully functional in free.
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
						'name'     => 'mptbm_payment_tabs_html',
						'label'    => '',
						'callback' => array( $this, 'render_sub_tabs' ),
					),
					array(
						'name'    => 'mptbm_enable_wc_payment',
						'label'   => __( 'Enable WooCommerce Payment', 'ecab-taxi-booking-manager' ),
						'desc'    => __( 'If enabled, the WooCommerce cart/checkout flow is used for bookings.', 'ecab-taxi-booking-manager' ),
						'type'    => 'checkbox',
						'default' => 'on',
						'class'   => 'woocommerce-field woocommerce-main-toggle',
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
						'class'   => 'woocommerce-field wc-additional-field',
					),
					array(
						'name'    => 'mptbm_wc_show_billing_info',
						'label'   => __( 'Show Billing Info', 'ecab-taxi-booking-manager' ),
						'desc'    => __( 'Show billing info on the WooCommerce checkout page.', 'ecab-taxi-booking-manager' ),
						'type'    => 'checkbox',
						'default' => '',
						'class'   => 'woocommerce-field wc-additional-field',
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

			/** Sub-tab bar (WooCommerce / Custom Payment) + WC-inactive warning. */
			public function render_sub_tabs() {
				$wc_active    = $this->has_woo();
				$is_installed = file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' );
				$btn_text     = $is_installed
					? __( 'Activate WooCommerce Now', 'ecab-taxi-booking-manager' )
					: __( 'Install &amp; Activate Now', 'ecab-taxi-booking-manager' );
				?>
				<div class="payment-sub-tabs-wrapper">
					<h2 class="nav-tab-wrapper payment-sub-tabs">
						<a href="#woocommerce-field" class="nav-tab nav-tab-active"><?php esc_html_e( 'WooCommerce', 'ecab-taxi-booking-manager' ); ?></a>
						<a href="#no-woocommerce-field" class="nav-tab"><?php esc_html_e( 'Custom Payment', 'ecab-taxi-booking-manager' ); ?></a>
					</h2>
					<?php if ( ! $wc_active ) : ?>
						<div class="woocommerce-field">
							<div class="mptbm-woo-warning-notice" style="background:#fff3cd;color:#856404;padding:15px;border-left:4px solid #ffeeba;border-radius:6px;margin:15px 0 10px;">
								<div style="display:flex;flex-direction:column;align-items:flex-start;gap:15px;">
									<div style="width:100%;">
										<strong style="display:block;font-size:14px;margin-bottom:5px;"><i class="fas fa-exclamation-triangle" style="margin-right:5px;"></i><?php esc_html_e( 'Notice: WooCommerce is Not Activated', 'ecab-taxi-booking-manager' ); ?></strong>
										<span style="font-size:13px;display:block;"><?php esc_html_e( 'To process bookings through the WooCommerce cart/checkout flow, you must install and activate WooCommerce. Otherwise, use the Custom Payment tab.', 'ecab-taxi-booking-manager' ); ?></span>
									</div>
									<div>
										<button type="button" class="button button-primary mptbm-install-wc-trigger" style="white-space:nowrap;"><?php echo wp_kses_post( $btn_text ); ?></button>
									</div>
								</div>
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
				$off_enabled = $this->opt( 'mptbm_offline_enable' ) === 'on';
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
						<?php if ( $is_pro ) : ?>
							<span class="gateway-status <?php echo $off_enabled ? 'active' : ''; ?>"><?php echo esc_html( $off_enabled ? $enabled_txt : $disabled_txt ); ?></span>
						<?php endif; ?>
						<div class="gateway-actions">
							<?php if ( $is_pro ) : ?>
								<button type="button" class="gateway-configure-btn" id="mptbm-offline-configure-btn"><?php esc_html_e( 'Configure', 'ecab-taxi-booking-manager' ); ?></button>
							<?php else : ?>
								<?php echo wp_kses_post( $pro_badge ); ?>
							<?php endif; ?>
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
				$off_enabled = $this->opt( 'mptbm_offline_enable' ) === 'on';
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
				<!-- Offline Payment Config Modal (Pro-only) -->
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
				<?php endif; ?>

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
				div.tabsItem[data-tabs="#mptbm_payment_settings"]{padding:18px 22px 26px;}
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
				div.tabsItem[data-tabs="#mptbm_payment_settings"] > form > .form-table > tbody > tr > th{display:block;width:100%;padding:16px 22px 4px;font-size:13px;font-weight:600;color:#1d2327;}
				div.tabsItem[data-tabs="#mptbm_payment_settings"] > form > .form-table > tbody > tr > td{display:block;width:100%;padding:0 22px 16px !important;}
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
				div.tabsItem[data-tabs="#mptbm_payment_settings"] > form > .form-table > tbody > tr.wc-additional-first{border-top:1px solid #e7e8ec;border-radius:12px 12px 0 0;margin-top:6px;}
				div.tabsItem[data-tabs="#mptbm_payment_settings"] > form > .form-table > tbody > tr.wc-additional-last{border-bottom:1px solid #e7e8ec;border-radius:0 0 12px 12px;}
				div.tabsItem[data-tabs="#mptbm_payment_settings"] > form > .form-table .formControl{max-width:480px;}
				div.tabsItem[data-tabs="#mptbm_payment_settings"] .submit{margin-top:8px;padding-top:18px;border-top:1px solid #e7e8ec;}
				/* Sub-tab bar */
				.payment-sub-tabs-wrapper{margin:0 0 24px;background:#fff;padding:6px;border-radius:12px;border:1px solid #e7e8ec;box-shadow:0 1px 2px rgba(16,24,40,0.04);display:inline-block;}
				.payment-sub-tabs.nav-tab-wrapper{border-bottom:none !important;padding:0 !important;margin:0 !important;display:flex;gap:6px;}
				.payment-sub-tabs .nav-tab{background:transparent;border:1px solid transparent;border-radius:8px;padding:9px 20px;font-size:14px;font-weight:600;color:#50575e !important;text-decoration:none;margin:0;transition:all 0.18s ease;}
				.payment-sub-tabs .nav-tab:hover{background:#fbeaf1;color:var(--mptbm-pay-accent) !important;}
				.payment-sub-tabs .nav-tab-active,.payment-sub-tabs .nav-tab-active:hover{background:var(--mptbm-pay-accent);color:#fff !important;box-shadow:0 4px 12px rgba(241,41,113,0.28);}

				/* Custom Payment intro */
				.mptbm-gw-intro{margin:4px 0 20px;}
				.mptbm-gw-intro h3{margin:0 0 6px;font-size:16px;font-weight:700;color:#1d2327;}
				.mptbm-gw-intro p{margin:0;font-size:13px;color:#6b7280;max-width:680px;line-height:1.6;}

				/* Gateway cards (Custom Payment) */
				.payment-gateways-container th{display:none;}
				.payment-gateways-container td{padding:0 !important;}
				.gateway-card{border:none;border-radius:14px;margin-bottom:16px;box-shadow:0 6px 18px rgba(16,24,40,0.10);width:100%;box-sizing:border-box;color:#fff;overflow:hidden;transition:transform 0.18s ease,box-shadow 0.18s ease;}
				.gateway-card:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(16,24,40,0.16);}
				.gateway-card .gateway-header{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:18px 22px;}
				.gateway-card .gateway-id{display:flex;align-items:center;gap:14px;min-width:0;flex:1 1 0;}
				.gateway-card .gateway-icon{flex:0 0 auto;width:46px;height:46px;border-radius:12px;background:rgba(255,255,255,0.16);display:flex;align-items:center;justify-content:center;}
				.gateway-card .gateway-meta{display:flex;flex-direction:column;min-width:0;}
				.gateway-card .gateway-name{font-size:16px;font-weight:700;color:#fff;line-height:1.3;}
				.gateway-card .gateway-sub{font-size:12px;color:rgba(255,255,255,0.82);line-height:1.4;}
				.gateway-card .gateway-actions{display:flex;align-items:center;justify-content:flex-end;gap:12px;flex:1 1 0;}
				.gateway-card .gateway-status{display:inline-block;min-width:78px;text-align:center;font-size:11px;text-transform:uppercase;letter-spacing:0.4px;padding:4px 11px;border-radius:20px;background:rgba(255,255,255,0.2);color:#fff;font-weight:700;}
				.gateway-card .gateway-status.active{background:#fff;}
				.gateway-card.paypal-card{background:linear-gradient(135deg,#003087 0%,#0079C1 100%);}
				.gateway-card.paypal-card .gateway-status.active{color:#003087;}
				.gateway-card.stripe-card{background:linear-gradient(135deg,#635bff 0%,#3f36c5 100%);}
				.gateway-card.stripe-card .gateway-status.active{color:#635bff;}
				.gateway-card.offline-card{background:linear-gradient(135deg,#0f766e 0%,#115e59 100%);}
				.gateway-card.offline-card .gateway-status.active{color:#0f766e;}
				.gateway-card .gateway-configure-btn{cursor:pointer;color:#1d2327 !important;background:#fff !important;border:none !important;font-weight:600 !important;font-size:13px !important;border-radius:8px !important;padding:7px 16px !important;line-height:1.4 !important;box-shadow:0 2px 6px rgba(0,0,0,0.18) !important;transition:opacity 0.15s ease;}
				.gateway-card .gateway-configure-btn:hover{opacity:0.9;}
				.mptbm-gw-pro-badge{background:linear-gradient(135deg,#f6d365 0%,#fda085 100%);color:#fff;padding:5px 12px;border-radius:20px;font-weight:bold;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;box-shadow:0 2px 6px rgba(253,160,133,0.4);}

				/* Booking confirmation page */
				.mptbm-conf-page{margin-top:22px;padding:22px 24px;display:flex;align-items:center;gap:24px;flex-wrap:wrap;background:#fafafb;border:1px solid #ececf0;border-radius:14px;}
				.mptbm-conf-page-label{flex:1 1 260px;}
				.mptbm-conf-page-label label{display:block;font-weight:700;font-size:14px;color:#1d2327;margin:0 0 4px;}
				.mptbm-conf-page-label span{display:block;font-size:12px;color:#6b7280;line-height:1.6;}
				.mptbm-conf-page-field{flex:0 0 auto;}
				.mptbm-conf-page-field select{width:100%;max-width:320px;border:1px solid #d1d5db;border-radius:8px;padding:7px 12px;font-size:13px;background:#fff;}

				/* WooCommerce sub-tab accordions */
				tr.mptbm-acc-header > td.mptbm-acc-header-cell{padding:0 !important;}
				tr.mptbm-acc-header .mptbm-acc-bar{display:flex;align-items:center;justify-content:space-between;gap:10px;cursor:pointer;user-select:none;background:#fff;border:1px solid #e7e8ec;border-radius:10px;padding:13px 16px;margin:14px 0 4px;transition:background 0.2s ease,border-color 0.2s ease,box-shadow 0.2s ease;}
				tr.mptbm-acc-header .mptbm-acc-bar:hover{border-color:#d4b3c3;box-shadow:0 2px 8px rgba(16,24,40,0.06);}
				tr.mptbm-acc-header.open .mptbm-acc-bar{background:#fdf2f7;border-color:var(--mptbm-pay-accent);}
				tr.mptbm-acc-header .mptbm-acc-title{display:flex;align-items:center;gap:8px;font-size:14px;font-weight:700;color:#1d2327;margin:0;}
				tr.mptbm-acc-header.open .mptbm-acc-title{color:var(--mptbm-pay-accent);}
				tr.mptbm-acc-header .mptbm-acc-arrow{transition:transform 0.2s ease;color:#50575e;line-height:1;}
				tr.mptbm-acc-header.open .mptbm-acc-arrow{transform:rotate(180deg);color:var(--mptbm-pay-accent);}
				/* The accordion header already shows the title; hide the manager's own duplicate heading but keep its bar (it holds the "Open in WooCommerce" link). */
				tr.wc-payment-methods-field .mptbm-wc-pm-heading{display:none;}
				tr.wc-payment-methods-field .mptbm-wc-payment-manager{margin-top:4px;padding:6px 2px;}
				/* WooCommerce enable toggle row + additional fields: lighter rows */
				tr.woocommerce-field td, tr.no-woocommerce-field td{vertical-align:middle;}
				</style>
				<script>
				jQuery(function($){
					var wcActive = <?php echo $wc_active; ?>;
					if ($('.payment-sub-tabs').length === 0) { return; }

					var toggleSel = 'input.checkbox[name="<?php echo esc_js( self::OPTION ); ?>[mptbm_enable_wc_payment]"]';
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
						var $toggleRow = $('tr.woocommerce-main-toggle');
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

					function toggleWcSettings(){
						var isChecked = $(toggleSel).is(':checked');
						var $wcFields = $('tr.woocommerce-field').not('tr.woocommerce-main-toggle');
						if (isChecked) { $wcFields.stop(true,true).show(); refreshAccordions(); } else { $wcFields.hide(); }
					}
					$(toggleSel).on('change', toggleWcSettings);

					function updateTabs(){
						var activeTabId = $('.payment-sub-tabs .nav-tab-active').attr('href').replace('#','');
						$('tr.woocommerce-field, div.woocommerce-field, tr.no-woocommerce-field').hide();
						$paymentSubmit.show();
						if (activeTabId === 'woocommerce-field') {
							$('div.woocommerce-field').show();
							if (wcActive) { $('tr.woocommerce-field').show(); toggleWcSettings(); }
						} else {
							$('tr.' + activeTabId).show();
						}
					}
					$('.payment-sub-tabs .nav-tab').on('click', function(e){
						e.preventDefault();
						$('.payment-sub-tabs .nav-tab').removeClass('nav-tab-active');
						$(this).addClass('nav-tab-active');
						updateTabs();
					});

					// Move the tab bar above the settings table so it spans full width.
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

				// PayPal, Stripe & Offline are Pro-only; never persist them from the free build.
				if ( ! $this->is_pro() ) {
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
			 * Keep gateway credentials when the Settings API saves the rest of the form.
			 * Only restores a key when it is ABSENT from the incoming value, so a gateway
			 * modal's own AJAX save (which carries new values) is never clobbered.
			 */
			public function preserve_gateway_keys( $new_value, $old_value ) {
				$protected = array(
					'mptbm_paypal_enable', 'mptbm_paypal_sandbox', 'mptbm_paypal_client_id', 'mptbm_paypal_secret',
					'mptbm_stripe_enable', 'mptbm_stripe_sandbox', 'mptbm_stripe_test_pub', 'mptbm_stripe_test_sec',
					'mptbm_stripe_live_pub', 'mptbm_stripe_live_sec',
					'mptbm_offline_enable', 'mptbm_offline_label',
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
