<?php
	/**
	 * Dismissible "E-cab Taxi Booking Manager Pro" upsell notice.
	 *
	 * Replaces the small PRO row that used to live inside the payment-setup notice
	 * (MPTBM_Payment_Notice). That row only appeared while payments were misconfigured,
	 * so once an admin enabled the free Offline method the Pro pitch vanished with it.
	 * This is a standalone notice instead: it shows whenever Pro is inactive, lists the
	 * Pro feature set as a chip grid, and stays gone once dismissed.
	 *
	 * Shown only to admins, only on this plugin's own screens plus the Plugins list, and
	 * never once dismissed (stored in the mptbm_pro_promo_dismissed option). Offline
	 * Payment is deliberately NOT listed - it is a free method
	 * (see MPTBM_Function::offline_payment_enabled()).
	 */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.

	if (!class_exists('MPTBM_Pro_Features_Notice')) {
		class MPTBM_Pro_Features_Notice {

			const DISMISS_OPTION = 'mptbm_pro_promo_dismissed';
			const DISMISS_ACTION = 'mptbm_dismiss_pro_promo';

			public function __construct() {
				add_action('admin_init', array($this, 'handle_dismiss'));
				add_action('admin_notices', array($this, 'render'));
			}

			private function is_pro(): bool {
				return class_exists('MPTBM_Plugin_Pro') || class_exists('MPTBM_Dependencies_Pro');
			}

			/** Filterable upgrade destination, shared with the other free-build PRO locks. */
			private function upgrade_url(): string {
				return apply_filters('mptbm_pro_upgrade_url', 'https://www.magepeople.com/downloads/taxi-booking-manager-for-woocommerce/');
			}

			/** Persist the dismissal (nonce + capability checked) so the notice never returns. */
			public function handle_dismiss(): void {
				if (!isset($_GET[self::DISMISS_ACTION]) || $_GET[self::DISMISS_ACTION] !== '1') {
					return;
				}
				if (isset($_GET['_wpnonce'])
					&& wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), self::DISMISS_ACTION)
					&& current_user_can('manage_options')) {
					update_option(self::DISMISS_OPTION, 'yes');
				}
			}

			/** This plugin's own admin screens + the main Plugins list. */
			private function is_target_screen(): bool {
				if (!function_exists('get_current_screen')) {
					return false;
				}
				$screen = get_current_screen();
				if (!$screen) {
					return false;
				}
				if (strpos((string) $screen->id, 'mptbm') !== false) {
					return true;
				}
				if (class_exists('MPTBM_Function') && $screen->post_type === MPTBM_Function::get_cpt()) {
					return true;
				}
				return $screen->id === 'plugins';
			}

			/** The Pro-only highlights (label + core Dashicon). */
			private function features(): array {
				return array(
					array('icon' => 'cart',              'label' => __('PayPal & Stripe checkout', 'ecab-taxi-booking-manager')),
					array('icon' => 'calendar-alt',      'label' => __('Booking calendar', 'ecab-taxi-booking-manager')),
					array('icon' => 'list-view',         'label' => __('Full booking & order management', 'ecab-taxi-booking-manager')),
					array('icon' => 'businessperson',    'label' => __('Driver panel & trip assignment', 'ecab-taxi-booking-manager')),
					array('icon' => 'id',                'label' => __('Customer portal & My Bookings', 'ecab-taxi-booking-manager')),
					array('icon' => 'media-document',    'label' => __('Branded PDF invoices & tickets', 'ecab-taxi-booking-manager')),
					array('icon' => 'location-alt',      'label' => __('Operation areas & zone geo-fencing', 'ecab-taxi-booking-manager')),
					array('icon' => 'media-spreadsheet', 'label' => __('Google Sheets sync', 'ecab-taxi-booking-manager')),
					array('icon' => 'email-alt',         'label' => __('Notification centre & custom emails', 'ecab-taxi-booking-manager')),
					array('icon' => 'format-gallery',    'label' => __('Vehicle gallery', 'ecab-taxi-booking-manager')),
				);
			}

			public function render(): void {
				if (!current_user_can('manage_options')) {
					return;
				}
				if ($this->is_pro()) {
					return; // Pro is active - nothing to upsell.
				}
				if (get_option(self::DISMISS_OPTION) === 'yes') {
					return; // Dismissed for good.
				}
				if (!$this->is_target_screen()) {
					return;
				}

				$this->print_styles_once();

				$dismiss_url = wp_nonce_url(add_query_arg(self::DISMISS_ACTION, '1'), self::DISMISS_ACTION);
				?>
				<div class="notice mptbm-pro-notice">
					<div class="mptbm-pro-inner">
						<span class="mptbm-pro-icon" aria-hidden="true"><span class="dashicons dashicons-awards"></span></span>
						<div class="mptbm-pro-body">
							<span class="mptbm-pro-eyebrow"><?php esc_html_e('E-cab Taxi Booking Manager Pro', 'ecab-taxi-booking-manager'); ?></span>
							<div class="mptbm-pro-title"><?php esc_html_e('Unlock every premium feature', 'ecab-taxi-booking-manager'); ?></div>
							<p class="mptbm-pro-text"><?php esc_html_e('You\'re on the free plugin - Offline Payment and the WooCommerce checkout are included. Upgrade to Pro for online payments and the complete fleet toolkit:', 'ecab-taxi-booking-manager'); ?></p>
							<ul class="mptbm-pro-chips">
								<?php foreach ($this->features() as $feature) : ?>
									<li class="mptbm-pro-chip">
										<span class="dashicons dashicons-<?php echo esc_attr($feature['icon']); ?>" aria-hidden="true"></span>
										<?php echo esc_html($feature['label']); ?>
									</li>
								<?php endforeach; ?>
							</ul>
							<div class="mptbm-pro-actions">
								<a class="mptbm-pro-btn mptbm-pro-btn-primary" href="<?php echo esc_url($this->upgrade_url()); ?>" target="_blank" rel="noopener noreferrer">
									<span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
									<?php esc_html_e('Get Pro Now', 'ecab-taxi-booking-manager'); ?>
								</a>
								<a class="mptbm-pro-btn mptbm-pro-btn-ghost" href="<?php echo esc_url($dismiss_url); ?>">
									<?php esc_html_e('Maybe later', 'ecab-taxi-booking-manager'); ?>
								</a>
							</div>
						</div>
					</div>
				</div>
				<?php
			}

			/** Scoped CSS for the notice, printed at most once per request. */
			private function print_styles_once(): void {
				static $printed = false;
				if ($printed) {
					return;
				}
				$printed = true;
				?>
				<style id="mptbm-pro-promo-styles">
				.mptbm-pro-notice{
					border:1px solid #ece3fb !important;border-left:4px solid #7b2ff7 !important;
					border-radius:12px !important;background:#fff !important;padding:0 !important;
					margin:16px 20px 14px 0 !important;box-shadow:0 4px 16px rgba(16,24,40,.07) !important;overflow:hidden;
				}
				.mptbm-pro-inner{display:flex;gap:14px;align-items:flex-start;padding:18px 20px;
					font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,sans-serif;}
				.mptbm-pro-icon{flex:0 0 auto;width:40px;height:40px;border-radius:11px;
					background:linear-gradient(135deg,#F12971 0%,#7b2ff7 100%);color:#fff;
					display:flex;align-items:center;justify-content:center;}
				.mptbm-pro-icon .dashicons{font-size:21px;width:21px;height:21px;line-height:1;}
				.mptbm-pro-body{flex:1;min-width:0;}
				.mptbm-pro-eyebrow{display:block;font-size:11px;font-weight:800;letter-spacing:.07em;
					text-transform:uppercase;color:#7b2ff7;margin-bottom:3px;}
				.mptbm-pro-title{font-size:15px;font-weight:700;color:#1e293b;margin:0 0 5px;line-height:1.3;}
				.mptbm-pro-text{font-size:13px;color:#50575e;margin:0;line-height:1.55;max-width:840px;}
				.mptbm-pro-chips{list-style:none;margin:12px 0 4px;padding:0;display:flex;flex-wrap:wrap;gap:8px;}
				.mptbm-pro-chip{display:inline-flex;align-items:center;gap:8px;font-size:12.5px;color:#374151;
					background:#faf5ff;border:1px solid #efe3fb;border-radius:8px;padding:7px 12px;line-height:1.35;white-space:nowrap;}
				.mptbm-pro-chip .dashicons{font-size:16px;width:16px;height:16px;line-height:1;color:#F12971;flex:0 0 auto;}
				.mptbm-pro-actions{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-top:14px;}
				.mptbm-pro-btn{display:inline-flex;align-items:center;gap:7px;height:34px;padding:0 16px;border-radius:8px;
					font-size:13px;font-weight:600;text-decoration:none;line-height:1;box-sizing:border-box;
					cursor:pointer;transition:all .18s ease;}
				.mptbm-pro-btn .dashicons{font-size:15px;width:15px;height:15px;line-height:1;}
				.mptbm-pro-btn-primary{background:linear-gradient(135deg,#F12971 0%,#7b2ff7 100%);color:#fff !important;
					border:1px solid transparent;box-shadow:0 1px 3px rgba(123,47,247,.3);}
				.mptbm-pro-btn-primary:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(123,47,247,.34);color:#fff !important;}
				.mptbm-pro-btn-ghost{background:#fff;color:#64748b !important;border:1px solid #e2e8f0;}
				.mptbm-pro-btn-ghost:hover{background:#f8fafc;color:#475569 !important;}
				@media (max-width:782px){
					.mptbm-pro-notice{margin-right:10px !important;}
					.mptbm-pro-chip{flex:1 1 100%;white-space:normal;}
				}
				</style>
				<?php
			}
		}

		new MPTBM_Pro_Features_Notice();
	}
