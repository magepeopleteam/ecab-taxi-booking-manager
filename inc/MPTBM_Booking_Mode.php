<?php
	/**
	 * Single source of truth for "which flow processes a booking right now: WooCommerce
	 * or the Pro custom/standalone checkout?"
	 *
	 * Before this class existed, that answer came from a single checkbox
	 * ("Enable WooCommerce Payment") buried inside the WooCommerce sub-tab of the
	 * Payments settings screen, while the Custom Payment sub-tab's gateway toggles
	 * (PayPal/Stripe/Offline) were saved completely independently. An admin could
	 * enable Stripe in the Custom Payment tab while the WooCommerce checkbox was still
	 * on (its default) and never realize WooCommerce was still the one actually taking
	 * bookings. This class - and the explicit "Booking Mode" selector in
	 * Admin/settings/MPTBM_Payment_Settings.php that writes to it - replaces that
	 * implicit checkbox with one explicit, required choice that both plugins read.
	 *
	 * Both Frontend/MPTBM_Woocommerce.php (free) and MPTBM_Dependencies_Pro.php (Pro)
	 * gate on get_mode() so they always agree on who owns a given booking.
	 */
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	}

	if ( ! class_exists( 'MPTBM_Booking_Mode' ) ) {
		class MPTBM_Booking_Mode {

			const OPTION = 'mptbm_payment_settings';
			const KEY    = 'mptbm_booking_mode';

			const WOOCOMMERCE = 'woocommerce';
			const CUSTOM      = 'custom';

			/** Legacy key this class migrates away from (see get_stored_mode()). */
			const LEGACY_KEY = 'mptbm_enable_wc_payment';

			public static function has_woo() {
				return class_exists( 'MP_Global_Function' ) && MP_Global_Function::check_woocommerce() === 1;
			}

			/** Only the Pro plugin can actually run the custom/standalone checkout. */
			public static function has_pro() {
				return class_exists( 'MPTBM_Plugin_Pro' );
			}

			/**
			 * Whether a real choice exists at all. When only one side is available there is
			 * nothing to choose - the mode is simply whichever one can run.
			 *
			 * @return string 'both' | 'woocommerce_only' | 'custom_only' | 'none'
			 */
			public static function availability() {
				$woo = self::has_woo();
				$pro = self::has_pro();
				if ( $woo && $pro ) {
					return 'both';
				}
				if ( $woo ) {
					return 'woocommerce_only';
				}
				if ( $pro ) {
					return 'custom_only';
				}
				return 'none';
			}

			/**
			 * The admin's saved choice, or '' if they've never made one. Transparently
			 * migrates the old on/off checkbox the first time it's read, so upgrading
			 * sites keep behaving exactly as before until the admin actively changes it.
			 *
			 * @return string self::WOOCOMMERCE | self::CUSTOM | ''
			 */
			public static function get_stored_mode() {
				$opts = get_option( self::OPTION, array() );
				$opts = is_array( $opts ) ? $opts : array();

				if ( ! empty( $opts[ self::KEY ] ) && in_array( $opts[ self::KEY ], array( self::WOOCOMMERCE, self::CUSTOM ), true ) ) {
					return $opts[ self::KEY ];
				}

				if ( isset( $opts[ self::LEGACY_KEY ] ) ) {
					$migrated         = ( 'off' === $opts[ self::LEGACY_KEY ] ) ? self::CUSTOM : self::WOOCOMMERCE;
					$opts[ self::KEY ] = $migrated;
					update_option( self::OPTION, $opts );
					return $migrated;
				}

				return '';
			}

			/** True only when there's a real choice to make and the admin hasn't made it yet. */
			public static function needs_selection() {
				return 'both' === self::availability() && '' === self::get_stored_mode();
			}

			/**
			 * The mode actually in effect. This is what booking-flow gates must call.
			 *
			 * @return string self::WOOCOMMERCE | self::CUSTOM | '' (nothing can process a booking)
			 */
			public static function get_mode() {
				switch ( self::availability() ) {
					case 'woocommerce_only':
						return self::WOOCOMMERCE;
					case 'custom_only':
						return self::CUSTOM;
					case 'none':
						return '';
					case 'both':
					default:
						// Safe default (matches the old checkbox's default) until an explicit choice is saved.
						return self::get_stored_mode() ?: self::WOOCOMMERCE;
				}
			}

			public static function is_woocommerce() {
				return self::WOOCOMMERCE === self::get_mode();
			}

			public static function is_custom() {
				return self::CUSTOM === self::get_mode();
			}

			/** Persist an explicit choice. Only meaningful when availability() === 'both'. */
			public static function set_mode( $mode ) {
				if ( ! in_array( $mode, array( self::WOOCOMMERCE, self::CUSTOM ), true ) ) {
					return false;
				}
				$opts          = get_option( self::OPTION, array() );
				$opts          = is_array( $opts ) ? $opts : array();
				$opts[ self::KEY ] = $mode;
				return update_option( self::OPTION, $opts );
			}

			/**
			 * Does the currently-active mode actually have a usable payment method?
			 * Reuses the provider counts MPTBM_Payment_Status_Checker already computes -
			 * no new gateway-counting logic.
			 */
			public static function has_gateway_for_active_mode() {
				if ( ! class_exists( 'MPTBM_Payment_Status_Checker' ) ) {
					return true; // fail open - the generic notice will still catch a total absence.
				}
				if ( self::is_woocommerce() ) {
					return MPTBM_Payment_Status_Checker::get_enabled_woocommerce_gateway_count() > 0;
				}
				if ( self::is_custom() ) {
					return MPTBM_Payment_Status_Checker::get_enabled_pro_payment_method_count() > 0;
				}
				return false;
			}
		}
	}
