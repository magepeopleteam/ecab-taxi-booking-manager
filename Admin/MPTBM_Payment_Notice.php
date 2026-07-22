<?php
	/**
	 * Admin notice shown when the booking system has no usable payment method
	 * at all - neither an enabled WooCommerce gateway nor an enabled Pro plugin
	 * custom payment method. Without one, customers can never complete a booking.
	 *
	 * All availability logic lives in MPTBM_Payment_Status_Checker; this class is
	 * only responsible for deciding when/where to render the notice and what it
	 * says, per single-responsibility.
	 */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('MPTBM_Payment_Notice')) {
		class MPTBM_Payment_Notice {

			public function __construct() {
				add_action('admin_notices', array($this, 'render'));
			}

			/**
			 * Print the notice, but only for users who can act on it and only on
			 * screens where it's relevant - mirrors the screen-scoping already used
			 * by MPTBM_Woo_Installer so we don't blast every wp-admin page.
			 */
			public function render(): void {
				if (!$this->should_show_notice()) {
					return;
				}
				$this->print_styles_once();

				$payments_url = admin_url('edit.php?post_type=' . MPTBM_Function::get_cpt() . '&page=mptbm_settings_page&mptbm_tab=payments');
				$pro_active   = class_exists('MPTBM_Plugin_Pro');
				?>
				<div class="notice is-dismissible mptbm-pay-notice">
					<div class="mptbm-pn-inner">
						<span class="mptbm-pn-icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2.5"/><line x1="2" y1="10" x2="22" y2="10"/><line x1="6" y1="15" x2="10" y2="15"/></svg>
						</span>
						<div class="mptbm-pn-body">
							<div class="mptbm-pn-title">
								<?php esc_html_e('E-cab Taxi Booking Manager — payment setup needed', 'ecab-taxi-booking-manager'); ?>
							</div>
							<p class="mptbm-pn-text"><?php echo esc_html($this->get_notice_message()); ?></p>

							<div class="mptbm-pn-actions">
								<a class="mptbm-pn-btn mptbm-pn-btn-primary" href="<?php echo esc_url($payments_url); ?>">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
									<?php esc_html_e('Go to Payment Settings', 'ecab-taxi-booking-manager'); ?>
								</a>

								<?php if (MPTBM_Function::is_wc_active()) : ?>
									<span class="mptbm-pn-hint"><?php esc_html_e('Enable a WooCommerce gateway there.', 'ecab-taxi-booking-manager'); ?></span>
								<?php else : ?>
									<span class="mptbm-pn-hint"><?php esc_html_e('Activate WooCommerce or enable Custom Payment there.', 'ecab-taxi-booking-manager'); ?></span>
								<?php endif; ?>
							</div>

							<?php if (!$pro_active) : ?>
								<div class="mptbm-pn-pro-row">
									<span class="mptbm-pn-pro-chip"><?php esc_html_e('PRO', 'ecab-taxi-booking-manager'); ?></span>
									<span class="mptbm-pn-pro-text"><?php esc_html_e('Want more gateways (Stripe, PayPal, offline) without WooCommerce?', 'ecab-taxi-booking-manager'); ?></span>
									<a class="mptbm-pn-pro-link" href="https://mage-people.com/product/wordpress-taxi-cab-booking-plugin-for-woocommerce" target="_blank" rel="noopener noreferrer">
										<?php esc_html_e('Explore Pro', 'ecab-taxi-booking-manager'); ?> &rarr;
									</a>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
				<?php
			}

			/** Scoped CSS for the modern notice, printed at most once per request. */
			private function print_styles_once(): void {
				static $printed = false;
				if ($printed) {
					return;
				}
				$printed = true;
				?>
				<style>
				.mptbm-pay-notice{
					border:1px solid #e9d7bd !important;border-left:4px solid #f59e0b !important;
					border-radius:12px !important;background:#fff !important;padding:0 !important;
					margin:16px 20px 14px 0 !important;box-shadow:0 4px 16px rgba(16,24,40,.07) !important;overflow:hidden;
				}
				.mptbm-pn-inner{display:flex;gap:14px;align-items:flex-start;padding:16px 40px 16px 18px;
					font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,sans-serif;}
				.mptbm-pn-icon{flex:0 0 auto;width:40px;height:40px;border-radius:11px;
					background:linear-gradient(135deg,#fff7ed,#ffedd5);color:#d97706;border:1px solid #fed7aa;
					display:flex;align-items:center;justify-content:center;}
				.mptbm-pn-icon svg{width:20px;height:20px;display:block;}
				.mptbm-pn-body{flex:1;min-width:0;}
				.mptbm-pn-title{font-size:14px;font-weight:700;color:#1e293b;margin:1px 0 4px;line-height:1.3;}
				.mptbm-pn-text{font-size:13px;color:#50575e;margin:0 0 12px;line-height:1.55;max-width:840px;}
				.mptbm-pn-actions{display:flex;flex-wrap:wrap;align-items:center;gap:12px;}
				.mptbm-pn-btn{display:inline-flex;align-items:center;gap:7px;height:34px;padding:0 16px;border-radius:8px;
					font-size:13px;font-weight:600;text-decoration:none;line-height:1;box-sizing:border-box;
					cursor:pointer;transition:all .18s ease;}
				.mptbm-pn-btn svg{width:15px;height:15px;display:block;}
				.mptbm-pn-btn-primary{background:#4f46e5;color:#fff !important;border:1px solid #4f46e5;
					box-shadow:0 1px 3px rgba(79,70,229,.3);}
				.mptbm-pn-btn-primary:hover{background:#4338ca;border-color:#4338ca;transform:translateY(-1px);
					box-shadow:0 4px 10px rgba(79,70,229,.32);color:#fff !important;}
				.mptbm-pn-hint{font-size:12px;color:#64748b;}
				.mptbm-pn-pro-row{display:flex;flex-wrap:wrap;align-items:center;gap:9px;margin-top:12px;padding-top:12px;
					border-top:1px dashed #eceff3;}
				.mptbm-pn-pro-chip{display:inline-flex;align-items:center;background:#f59e0b;color:#fff;font-weight:800;
					font-size:9px;letter-spacing:.07em;padding:3px 8px;border-radius:20px;text-transform:uppercase;}
				.mptbm-pn-pro-text{font-size:12px;color:#64748b;}
				.mptbm-pn-pro-link{font-size:12px;font-weight:700;color:#b45309 !important;text-decoration:none;}
				.mptbm-pn-pro-link:hover{color:#92400e !important;text-decoration:underline;}
				@media (max-width:782px){
					.mptbm-pay-notice{margin-right:10px !important;}
					.mptbm-pn-inner{padding-right:34px;}
				}
				</style>
				<?php
			}

			private function should_show_notice(): bool {
				if (!current_user_can('manage_options')) {
					return false;
				}
				if (!$this->is_relevant_screen()) {
					return false;
				}

				if (class_exists('MPTBM_Booking_Mode') && MPTBM_Booking_Mode::needs_selection()) {
					return true;
				}

				return class_exists('MPTBM_Booking_Mode')
					? !MPTBM_Booking_Mode::has_gateway_for_active_mode()
					: !MPTBM_Payment_Status_Checker::has_available_payment_method();
			}

			/**
			 * Mode-aware copy. The active Booking Mode is what actually processes
			 * bookings, so the notice names it explicitly instead of a generic
			 * "no payment method" line that could be true for the OTHER, unused mode
			 * while the active one has nothing configured.
			 */
			private function get_notice_message(): string {
				if (!class_exists('MPTBM_Booking_Mode')) {
					return __('No payment method is set up yet, so customers can\'t complete a booking. Activate WooCommerce or enable a Custom Payment method in Payment Settings to start taking bookings.', 'ecab-taxi-booking-manager');
				}

				if (MPTBM_Booking_Mode::needs_selection()) {
					return __('A Booking Mode hasn\'t been chosen yet. Pick WooCommerce or Custom Payment in Payment Settings so every booking follows one clear checkout path.', 'ecab-taxi-booking-manager');
				}

				if ('none' === MPTBM_Booking_Mode::availability()) {
					return __('No payment method is set up yet, so customers can\'t complete a booking. Activate WooCommerce or enable a Custom Payment method in Payment Settings to start taking bookings.', 'ecab-taxi-booking-manager');
				}

				if (MPTBM_Booking_Mode::is_woocommerce()) {
					return __('Booking Mode is set to WooCommerce, but no WooCommerce payment gateway is enabled yet. Enable one in Payment Settings so customers can complete a booking.', 'ecab-taxi-booking-manager');
				}

				return __('Booking Mode is set to Custom Payment, but no gateway (PayPal, Stripe, or Offline) is enabled yet. Enable one in Payment Settings so customers can complete a booking.', 'ecab-taxi-booking-manager');
			}

			/**
			 * Restrict the notice to this plugin's own admin screens plus the
			 * dashboard/plugins list, so it doesn't nag admins on unrelated pages.
			 */
			private function is_relevant_screen(): bool {
				$screen = function_exists('get_current_screen') ? get_current_screen() : null;
				if (!$screen) {
					return false;
				}

				return (
					strpos((string) $screen->id, 'mptbm') !== false
					|| $screen->post_type === MPTBM_Function::get_cpt()
					|| $screen->id === 'dashboard'
					|| $screen->id === 'plugins'
				);
			}

		}
		new MPTBM_Payment_Notice();
	}
