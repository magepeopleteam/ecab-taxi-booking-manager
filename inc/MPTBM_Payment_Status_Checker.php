<?php
	/**
	 * Aggregates every payment provider the plugin knows about (WooCommerce
	 * gateways, Pro plugin custom payment methods, and any future provider) into
	 * a single "is a booking payable right now?" answer.
	 *
	 * Pure/static and hook-free by design so it stays trivial to unit test and
	 * safe to call without side effects.
	 *
	 * WooCommerce and the Pro plugin are both optional. This class never assumes
	 * either is present - each provider count degrades to 0 when its dependency
	 * is missing, so has_available_payment_method() is safe to call unconditionally.
	 * Note: the WooCommerce count relies on MPTBM_WC_Payment_Manager, which is
	 * only loaded on admin requests (is_admin()) - callers on the frontend would
	 * need that class loaded first for an accurate count.
	 */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('MPTBM_Payment_Status_Checker')) {
		class MPTBM_Payment_Status_Checker {

			/**
			 * Filter any third-party payment integration can hook into to report how
			 * many additional custom payment methods it currently has enabled, on
			 * top of the paypal/stripe/offline toggles below. Lets future providers
			 * (a newer Pro version, an addon) contribute without this class needing
			 * to know about them in advance.
			 *
			 * Callback contract: return an int >= 0 (number of enabled methods).
			 */
			const PRO_PAYMENT_METHODS_FILTER = 'mptbm_enabled_pro_payment_methods_count';

			/**
			 * Option (shared with MPTBM_Payment_Settings::OPTION) that stores the
			 * PayPal/Stripe/Offline "Custom Payment" toggles, which are what the
			 * standalone (non-WooCommerce) checkout consumes - so today this option,
			 * not a Pro-side API, is the actual source of truth for "custom payment
			 * methods enabled". Read directly rather than via
			 * MPTBM_Payment_Settings::OPTION so this class doesn't depend on that
			 * file having loaded first.
			 *
			 * Note: PayPal/Stripe configuration is Pro-only, while Offline is
			 * configurable in the free plugin (see
			 * MPTBM_Function::offline_payment_enabled()). The counts below are still
			 * gated on the Pro plugin because only Pro ships the standalone checkout
			 * that can actually turn an enabled toggle into a completed booking - an
			 * Offline toggle with nothing to consume it must not silence the
			 * "no payment method" notice.
			 */
			const CUSTOM_PAYMENT_OPTION = 'mptbm_payment_settings';

			/**
			 * Keys inside CUSTOM_PAYMENT_OPTION whose value 'on' means that custom
			 * payment method is enabled.
			 */
			const CUSTOM_PAYMENT_ENABLE_KEYS = array(
				'mptbm_paypal_enable',
				'mptbm_stripe_enable',
				'mptbm_offline_enable',
			);

			/**
			 * Whether at least one payment method is available to complete a booking.
			 */
			public static function has_available_payment_method(): bool {
				return self::get_total_available_payment_methods() > 0;
			}

			/**
			 * Total count of enabled payment methods across every known provider.
			 */
			public static function get_total_available_payment_methods(): int {
				$counts = self::get_provider_counts();

				return array_sum($counts);
			}

			/**
			 * Per-provider breakdown, keyed by provider id. Filterable so new
			 * providers can register their own counts later without this class
			 * needing to know about them in advance.
			 *
			 * @return array<string,int>
			 */
			public static function get_provider_counts(): array {
				$counts = array(
					'woocommerce' => self::get_enabled_woocommerce_gateway_count(),
					'pro'         => self::get_enabled_pro_payment_method_count(),
				);

				/**
				 * Filter: mptbm_payment_provider_counts
				 * Lets any code (addons, must-use plugins, the Pro plugin itself)
				 * add or adjust provider counts used in the total.
				 */
				$counts = apply_filters('mptbm_payment_provider_counts', $counts);

				return array_map('absint', (array) $counts);
			}

			/**
			 * Number of currently enabled WooCommerce payment gateways.
			 * 0 when WooCommerce is not installed/active, or when it is active but
			 * every gateway is disabled.
			 */
			public static function get_enabled_woocommerce_gateway_count(): int {
				if (!MPTBM_Function::is_wc_active()) {
					return 0;
				}
				if (!function_exists('WC') || !class_exists('MPTBM_WC_Payment_Manager')) {
					return 0;
				}

				return MPTBM_WC_Payment_Manager::instance()->count_enabled_gateways();
			}

			/**
			 * Number of currently enabled custom (non-WooCommerce) payment methods that
			 * can really complete a booking right now.
			 *
			 * Offline counts in the free plugin - MPTBM_Offline_Checkout handles it without
			 * WooCommerce or Pro. PayPal/Stripe only count when Pro is active, since their
			 * gateways (and the hosted-checkout return handling) ship with Pro; counting a
			 * toggle nothing can consume would wrongly silence the payment-setup notice.
			 *
			 * Name kept for backward compatibility with existing callers/integrations.
			 */
			public static function get_enabled_pro_payment_method_count(): int {
				$count = 0;

				if (class_exists('MPTBM_Function') && MPTBM_Function::offline_payment_enabled()) {
					$count++;
				}

				if (class_exists('MPTBM_Plugin_Pro')) {
					$count += self::count_enabled_custom_payment_toggles(array('mptbm_paypal_enable', 'mptbm_stripe_enable'));
				}

				$filtered = apply_filters(self::PRO_PAYMENT_METHODS_FILTER, 0);
				$count += is_numeric($filtered) ? absint($filtered) : 0;

				return $count;
			}

			/**
			 * How many of the given enable-toggles in CUSTOM_PAYMENT_OPTION are set to 'on'.
			 *
			 * @param array $keys Subset of CUSTOM_PAYMENT_ENABLE_KEYS to count; all of them when empty.
			 */
			private static function count_enabled_custom_payment_toggles(array $keys = array()): int {
				$options = get_option(self::CUSTOM_PAYMENT_OPTION, array());
				if (!is_array($options)) {
					return 0;
				}

				$keys = $keys ?: self::CUSTOM_PAYMENT_ENABLE_KEYS;

				$count = 0;
				foreach ($keys as $key) {
					if (isset($options[$key]) && $options[$key] === 'on') {
						$count++;
					}
				}

				return $count;
			}
		}
	}
