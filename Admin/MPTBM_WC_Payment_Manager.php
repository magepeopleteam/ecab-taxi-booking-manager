<?php
	/**
	 * WooCommerce Payment Methods Manager for the E-cab Taxi Booking plugin.
	 *
	 * Renders every WooCommerce payment gateway's OWN native settings form inline,
	 * inside the Payments → WooCommerce tab. Each gateway's fields are produced by
	 * WooCommerce itself (generate_settings_html / get_form_fields) and saved through
	 * the gateway's own process_admin_options(). Nothing is re-implemented — this is
	 * WooCommerce's real configuration, embedded inline.
	 *
	 * Ported from the Rental plugin's RBFW_WC_Payment_Manager.
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		die;
	}

	if ( ! class_exists( 'MPTBM_WC_Payment_Manager' ) ) :

		class MPTBM_WC_Payment_Manager {

			private static $instance = null;

			public static function instance() {
				if ( null === self::$instance ) {
					self::$instance = new self();
				}

				return self::$instance;
			}

			private function __construct() {
				add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
				add_action( 'wp_ajax_mptbm_wc_save_gateway', array( $this, 'ajax_save_gateway' ) );
				add_action( 'wp_ajax_mptbm_wc_toggle_gateway', array( $this, 'ajax_toggle_gateway' ) );
			}

			// ---------------------------------------------------------------
			// Assets
			// ---------------------------------------------------------------

			public function enqueue_assets( $hook ) {
				unset( $hook );

				$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
				if ( ! $screen || strpos( $screen->id, 'mptbm_settings_page' ) === false ) {
					return;
				}

				// WooCommerce admin styling + the scripts its native fields rely on.
				if ( function_exists( 'WC' ) && class_exists( 'WooCommerce' ) ) {
					wp_enqueue_style( 'woocommerce_admin_styles' );
					wp_enqueue_script( 'wc-enhanced-select' );
					wp_enqueue_script( 'wc-jquery-tiptip' );
				}

				$js_path = MPTBM_PLUGIN_DIR . '/assets/admin/mptbm-wc-payment-manager.js';
				$js_ver  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : ( defined( 'MPTBM_PLUGIN_VERSION' ) ? MPTBM_PLUGIN_VERSION : '1.0.0' );

				wp_enqueue_script(
					'mptbm-wc-payment-manager',
					MPTBM_PLUGIN_URL . '/assets/admin/mptbm-wc-payment-manager.js',
					array( 'jquery' ),
					$js_ver,
					true
				);
				wp_localize_script(
					'mptbm-wc-payment-manager',
					'mptbmWcPaymentManager',
					array(
						'ajaxUrl' => admin_url( 'admin-ajax.php' ),
						'nonce'   => wp_create_nonce( 'mptbm_wc_payment_manager' ),
						'i18n'    => array(
							'saving'    => __( 'Saving…', 'ecab-taxi-booking-manager' ),
							'saved'     => __( 'Saved!', 'ecab-taxi-booking-manager' ),
							'error'     => __( 'An error occurred. Please try again.', 'ecab-taxi-booking-manager' ),
							'enabled'   => __( 'Enabled', 'ecab-taxi-booking-manager' ),
							'disabled'  => __( 'Disabled', 'ecab-taxi-booking-manager' ),
							'configure' => __( 'Configure', 'ecab-taxi-booking-manager' ),
							'close'     => __( 'Close', 'ecab-taxi-booking-manager' ),
						),
					)
				);
			}

			// ---------------------------------------------------------------
			// Gateway collection (includes suppressed ones, e.g. PayPal Standard)
			// ---------------------------------------------------------------

			private function get_all_gateways() {
				$wc_defaults     = array( 'WC_Gateway_BACS', 'WC_Gateway_Cheque', 'WC_Gateway_COD', 'WC_Gateway_Paypal' );
				$gateway_classes = apply_filters( 'woocommerce_payment_gateways', $wc_defaults );

				$loaded   = WC()->payment_gateways()->payment_gateways();
				$gateways = array();
				foreach ( $loaded as $g ) {
					if ( $g instanceof WC_Payment_Gateway ) {
						$gateways[ $g->id ] = $g;
					}
				}
				foreach ( $gateway_classes as $class ) {
					if ( ! is_string( $class ) || ! class_exists( $class ) ) {
						continue;
					}
					$already = false;
					foreach ( $gateways as $g ) {
						if ( $g instanceof $class ) {
							$already = true;
							break;
						}
					}
					if ( ! $already ) {
						$instance = new $class();
						if ( $instance instanceof WC_Payment_Gateway && ! isset( $gateways[ $instance->id ] ) ) {
							$gateways[ $instance->id ] = $instance;
						}
					}
				}

				// Respect WooCommerce's saved gateway order.
				$order = (array) get_option( 'woocommerce_gateway_order', array() );
				if ( ! empty( $order ) ) {
					uasort(
						$gateways,
						static function ( $a, $b ) use ( $order ) {
							$pa = isset( $order[ $a->id ] ) ? (int) $order[ $a->id ] : 999;
							$pb = isset( $order[ $b->id ] ) ? (int) $order[ $b->id ] : 999;
							return $pa <=> $pb;
						}
					);
				}

				return $gateways;
			}

			private function get_gateway( $gateway_id ) {
				$gateways = $this->get_all_gateways();
				return isset( $gateways[ $gateway_id ] ) ? $gateways[ $gateway_id ] : null;
			}

			/**
			 * How many registered WooCommerce gateways are currently enabled.
			 * Used by MPTBM_Payment_Status_Checker to decide whether WooCommerce
			 * contributes any usable payment method.
			 */
			public function count_enabled_gateways() {
				if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) ) {
					return 0;
				}

				$count = 0;
				foreach ( $this->get_all_gateways() as $gateway ) {
					if ( 'yes' === $gateway->enabled ) {
						$count++;
					}
				}

				return $count;
			}

			private function verify_request() {
				check_ajax_referer( 'mptbm_wc_payment_manager', 'nonce' );
				if ( ! current_user_can( 'manage_woocommerce' ) ) {
					wp_send_json_error( __( 'Permission denied.', 'ecab-taxi-booking-manager' ), 403 );
				}
				if ( ! class_exists( 'WooCommerce' ) ) {
					wp_send_json_error( __( 'WooCommerce is not active.', 'ecab-taxi-booking-manager' ) );
				}
			}

			// ---------------------------------------------------------------
			// AJAX: save one gateway's native form (process_admin_options)
			// ---------------------------------------------------------------

			public function ajax_save_gateway() {
				$this->verify_request();

				$gateway_id = isset( $_POST['gateway_id'] ) ? sanitize_key( wp_unslash( $_POST['gateway_id'] ) ) : '';
				$gateway    = $this->get_gateway( $gateway_id );
				if ( ! $gateway ) {
					wp_send_json_error( __( 'Gateway not found.', 'ecab-taxi-booking-manager' ) );
				}

				// process_admin_options() reads $_POST keyed as woocommerce_{id}_{field};
				// our JS submits the native form fields under exactly those names.
				$gateway->process_admin_options();

				$errors = $gateway->get_errors();
				if ( ! empty( $errors ) ) {
					wp_send_json_error( implode( ' ', array_map( 'wp_strip_all_tags', $errors ) ) );
				}

				do_action( 'woocommerce_update_options_payment_gateways_' . $gateway->id );
				if ( WC()->payment_gateways() ) {
					WC()->payment_gateways()->init();
				}

				$refreshed = $this->get_gateway( $gateway_id );
				wp_send_json_success(
					array(
						'message' => __( 'Settings saved successfully!', 'ecab-taxi-booking-manager' ),
						'enabled' => ( $refreshed && 'yes' === $refreshed->enabled ) ? 'yes' : 'no',
					)
				);
			}

			// ---------------------------------------------------------------
			// AJAX: quick enable/disable from the card header
			// ---------------------------------------------------------------

			public function ajax_toggle_gateway() {
				$this->verify_request();

				$gateway_id = isset( $_POST['gateway_id'] ) ? sanitize_key( wp_unslash( $_POST['gateway_id'] ) ) : '';
				$enabled    = ( isset( $_POST['enabled'] ) && 'yes' === $_POST['enabled'] ) ? 'yes' : 'no';
				if ( empty( $gateway_id ) ) {
					wp_send_json_error( __( 'Invalid gateway.', 'ecab-taxi-booking-manager' ) );
				}

				$option_key = 'woocommerce_' . $gateway_id . '_settings';
				$opts       = get_option( $option_key, array() );
				if ( ! is_array( $opts ) ) {
					$opts = array();
				}
				$opts['enabled'] = $enabled;
				if ( 'yes' === $enabled ) {
					$opts['_should_load'] = 'yes';
				}
				update_option( $option_key, $opts );

				if ( WC()->payment_gateways() ) {
					WC()->payment_gateways()->init();
				}

				wp_send_json_success( array( 'enabled' => $enabled ) );
			}

			// ---------------------------------------------------------------
			// Render — called from the WooCommerce tab
			// ---------------------------------------------------------------

			public function render() {
				if ( ! class_exists( 'WooCommerce' ) ) {
					return;
				}

				$gateways = $this->get_all_gateways();
				if ( empty( $gateways ) ) {
					echo '<p>' . esc_html__( 'No payment gateways are registered.', 'ecab-taxi-booking-manager' ) . '</p>';
					return;
				}
				?>
				<div class="mptbm-wc-payment-manager">
					<div class="mptbm-wc-pm-bar">
						<h3 class="mptbm-wc-pm-heading"><?php esc_html_e( 'WooCommerce Payment Methods', 'ecab-taxi-booking-manager' ); ?></h3>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ); ?>" class="button button-small mptbm-wc-pm-wc-link" target="_blank">
							<?php esc_html_e( 'Open in WooCommerce', 'ecab-taxi-booking-manager' ); ?>
							<span class="dashicons dashicons-external" style="font-size:14px;line-height:1.4;vertical-align:middle;"></span>
						</a>
					</div>

					<?php
					foreach ( $gateways as $gateway ) :
						$is_enabled = ( 'yes' === $gateway->enabled );
						$title      = $gateway->get_method_title() ? $gateway->get_method_title() : $gateway->get_title();
						$desc       = $gateway->get_method_description() ? $gateway->get_method_description() : $gateway->get_description();
						?>
						<div class="mptbm-gw-card <?php echo $is_enabled ? 'is-enabled' : 'is-disabled'; ?>" data-gateway-id="<?php echo esc_attr( $gateway->id ); ?>">
							<div class="mptbm-gw-head">
								<div class="mptbm-gw-head-main">
									<label class="mptbm-gw-toggle" title="<?php esc_attr_e( 'Enable / disable', 'ecab-taxi-booking-manager' ); ?>">
										<input type="checkbox" class="mptbm-gw-toggle-input" data-gateway-id="<?php echo esc_attr( $gateway->id ); ?>" <?php checked( $is_enabled ); ?>>
										<span class="mptbm-gw-toggle-slider"></span>
									</label>
									<span class="mptbm-gw-title"><?php echo esc_html( $title ); ?></span>
									<span class="mptbm-gw-badge"><?php echo $is_enabled ? esc_html__( 'Enabled', 'ecab-taxi-booking-manager' ) : esc_html__( 'Disabled', 'ecab-taxi-booking-manager' ); ?></span>
								</div>
								<button type="button" class="button mptbm-gw-configure-btn"><?php esc_html_e( 'Configure', 'ecab-taxi-booking-manager' ); ?></button>
							</div>

							<?php if ( $desc ) : ?>
								<div class="mptbm-gw-desc"><?php echo wp_kses_post( wpautop( $desc ) ); ?></div>
							<?php endif; ?>

							<div class="mptbm-gw-body" style="display:none;">
								<?php // Not a <form> on purpose: this sits inside the settings <form>, and nested forms are invalid HTML (the inner one is dropped by the browser). We serialize this container's inputs and save over AJAX instead. ?>
								<div class="mptbm-gw-form" data-gateway-id="<?php echo esc_attr( $gateway->id ); ?>">
									<table class="form-table mptbm-gw-form-table">
										<?php
										// WooCommerce's OWN field rendering for this gateway.
										echo $gateway->generate_settings_html( $gateway->get_form_fields(), false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										?>
									</table>
									<div class="mptbm-gw-form-footer">
										<button type="button" class="button button-primary mptbm-gw-save-btn"><?php esc_html_e( 'Save changes', 'ecab-taxi-booking-manager' ); ?></button>
										<span class="mptbm-gw-status"></span>
									</div>
								</div>
							</div>
						</div>
					<?php endforeach; ?>

					<?php $this->render_styles(); ?>
				</div>
				<?php
			}

			private function render_styles() {
				?>
				<style>
					.mptbm-wc-payment-manager { --mptbm-pay-accent:#F12971; display:block; width:100%; box-sizing:border-box; margin-top:8px; }
					.mptbm-wc-pm-bar { display:flex; align-items:center; gap:12px; margin-bottom:14px; }
					.mptbm-wc-pm-heading { margin:0; font-size:15px; }
					.mptbm-wc-pm-wc-link { font-size:12px; font-weight:normal; }

					.mptbm-gw-card { border:1px solid #e7e8ec; border-radius:12px; background:#fff; margin-bottom:14px; overflow:hidden; box-shadow:0 1px 2px rgba(16,24,40,0.04); transition:box-shadow 0.18s ease; }
					.mptbm-gw-card:hover { box-shadow:0 4px 14px rgba(16,24,40,0.08); }
					.mptbm-gw-card.is-enabled { border-left:3px solid var(--mptbm-pay-accent); }
					.mptbm-gw-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 16px; }
					.mptbm-gw-head-main { display:flex; align-items:center; gap:12px; }
					.mptbm-gw-title { font-size:14px; font-weight:600; color:#1d2327; }
					.mptbm-gw-badge { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.3px; padding:2px 8px; border-radius:9px; background:#f0f0f1; color:#646970; }
					.mptbm-gw-card.is-enabled .mptbm-gw-badge { background:#e6f4ea; color:#0a7c2f; }
					.mptbm-gw-desc { padding:0 16px 12px; color:#50575e; font-size:13px; }
					.mptbm-gw-desc p { margin:0 0 6px; }

					.mptbm-gw-body { padding:6px 16px 16px; border-top:1px solid #f0f0f1; background:#fbfbfc; }
					.mptbm-gw-form-table { width:100%; background:transparent; }
					.mptbm-gw-form-table th { width:200px; padding:14px 10px 14px 0; background:transparent; font-weight:600; vertical-align:top; }
					.mptbm-gw-form-table td { padding:12px 0; background:transparent; }
					.mptbm-gw-form-table input[type=text], .mptbm-gw-form-table input[type=password],
					.mptbm-gw-form-table input[type=email], .mptbm-gw-form-table input[type=number],
					.mptbm-gw-form-table textarea, .mptbm-gw-form-table select { min-width:320px; max-width:100%; }
					.mptbm-gw-form-footer { display:flex; align-items:center; gap:12px; margin-top:8px; padding-top:12px; border-top:1px solid #f0f0f1; }
					.mptbm-gw-status { font-size:13px; }
					.mptbm-gw-status.is-success { color:#0a7c2f; }
					.mptbm-gw-status.is-error { color:#d63638; }

					/* Toggle switch */
					.mptbm-gw-toggle { position:relative; display:inline-block; width:42px; height:24px; cursor:pointer; flex:0 0 auto; }
					.mptbm-gw-toggle-input {
						position:absolute; inset:0; margin:0; padding:0;
						width:100%; height:100%; min-width:0 !important; min-height:0 !important;
						opacity:0 !important; cursor:pointer; z-index:1;
						-webkit-appearance:none !important; -moz-appearance:none !important; appearance:none !important;
						background:none !important; border:none !important; box-shadow:none !important;
					}
					.mptbm-gw-toggle-input::before,
					.mptbm-gw-toggle-input::after { content:none !important; display:none !important; }
					.mptbm-gw-toggle-slider { position:absolute; inset:0; background:#b5b5ba; border-radius:24px; transition:background .2s; }
					.mptbm-gw-toggle-slider::before { content:''; position:absolute; height:18px; width:18px; left:3px; top:3px; background:#fff; border-radius:50%; transition:transform .2s; box-shadow:0 1px 3px rgba(0,0,0,.3); }
					.mptbm-gw-toggle-input:checked + .mptbm-gw-toggle-slider { background:var(--mptbm-pay-accent); }
					.mptbm-gw-toggle-input:checked + .mptbm-gw-toggle-slider::before { transform:translateX(18px); }
					.mptbm-gw-toggle-input:disabled + .mptbm-gw-toggle-slider { opacity:.5; cursor:not-allowed; }
				</style>
				<?php
			}
		}

		// Always instantiate so the admin_enqueue_scripts + AJAX hooks register.
		// (Required during plugin include, before WooCommerce has loaded — gating on
		// class_exists('WooCommerce') here would silently skip hook registration.
		// Each method guards WC availability internally.)
		MPTBM_WC_Payment_Manager::instance();

	endif;
