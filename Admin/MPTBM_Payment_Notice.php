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
				?>
				<div class="notice notice-warning is-dismissible">
					<p>
						<strong><?php esc_html_e('E-cab Taxi Booking Manager', 'ecab-taxi-booking-manager'); ?>:</strong>
						<?php echo esc_html($this->get_notice_message()); ?>
					</p>
					<?php $links = $this->get_action_links(); ?>
					<?php if (!empty($links)) : ?>
						<p><?php echo wp_kses_post(implode(' &nbsp;|&nbsp; ', $links)); ?></p>
					<?php endif; ?>
				</div>
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
					return __('No payment method is currently available. Customers will not be able to complete bookings until at least one payment method is enabled.', 'ecab-taxi-booking-manager');
				}

				if (MPTBM_Booking_Mode::needs_selection()) {
					return __('A Booking Mode hasn\'t been chosen yet (WooCommerce or Custom Payment). Choose one in Payments settings so bookings have a single, deterministic checkout path.', 'ecab-taxi-booking-manager');
				}

				if ('none' === MPTBM_Booking_Mode::availability()) {
					return __('No payment method is currently available. Customers will not be able to complete bookings until at least one payment method is enabled.', 'ecab-taxi-booking-manager');
				}

				if (MPTBM_Booking_Mode::is_woocommerce()) {
					return __('Booking Mode is set to WooCommerce, but no WooCommerce payment gateway is enabled. Customers will not be able to complete a booking until one is enabled.', 'ecab-taxi-booking-manager');
				}

				return __('Booking Mode is set to Custom Payment, but no gateway (PayPal, Stripe, or Offline) is enabled. Customers will not be able to complete a booking until one is enabled.', 'ecab-taxi-booking-manager');
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

			/**
			 * Contextual action links: only offer to configure the providers that
			 * are actually present, and offer an upgrade link when Pro is missing.
			 *
			 * @return string[]
			 */
			private function get_action_links(): array {
				$links = array();
				$settings_url = admin_url('edit.php?post_type=' . MPTBM_Function::get_cpt() . '&page=mptbm_settings_page');

				if (MPTBM_Function::is_wc_active()) {
					$links[] = sprintf(
						'<a href="%1$s">%2$s</a>',
						esc_url($settings_url),
						esc_html__('Configure WooCommerce Payments', 'ecab-taxi-booking-manager')
					);
				}

				if (class_exists('MPTBM_Plugin_Pro')) {
					$links[] = sprintf(
						'<a href="%1$s">%2$s</a>',
						esc_url($settings_url),
						esc_html__('Configure Pro Payment Methods', 'ecab-taxi-booking-manager')
					);
				} else {
					$links[] = sprintf(
						'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
						esc_url('https://mage-people.com/'),
						esc_html__('Upgrade to Pro', 'ecab-taxi-booking-manager')
					);
				}

				return $links;
			}
		}
		new MPTBM_Payment_Notice();
	}
