<?php
/**
 * Vehicle Custom Form Frontend
 *
 * Renders vehicle-specific custom form fields on checkout,
 * saves them as order item meta, and exposes them in:
 *   - Admin order details
 *   - Order received / thank-you page
 *   - WooCommerce emails
 *   - PDF (via filter mptbm_vehicle_form_data_for_pdf)
 *   - CSV export (via filter mptbm_vehicle_form_data_for_csv)
 *   - Order list / booking details panel
 *
 * @package ecab-taxi-booking-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

// Ensure the Form Builder class is available on frontend (admin loads it only in is_admin())
if ( ! class_exists( 'MPTBM_Vehicle_Form_Builder' ) ) {
	require_once plugin_dir_path( __FILE__ ) . '../Admin/settings/MPTBM_Vehicle_Form_Builder.php';
}

if ( ! class_exists( 'MPTBM_Vehicle_Form_Frontend' ) ) {
	class MPTBM_Vehicle_Form_Frontend {

		const ORDER_ITEM_META_KEY = '_mptbm_vfb_data';  // stored on WC order item
		const ORDER_META_KEY      = '_mptbm_vfb_data';  // also stored on booking post_meta
		private $block_fields_registered = false;
		private static $rendered_frontend_orders = array();

	public function __construct() {
		// Render fields on checkout after order notes
		add_action( 'woocommerce_after_order_notes', array( $this, 'render_checkout_fields' ), 20 );
		add_action( 'wp_loaded', array( $this, 'register_block_checkout_fields' ), 20 );
		add_action( 'woocommerce_blocks_checkout_enqueue_data', array( $this, 'register_block_checkout_fields' ), 0 );

		// Validate required fields
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_checkout_fields' ) );

		// Save to order item meta when order is created
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_to_order_item' ), 20, 4 );

		// Also save to WC order post meta (for admin display)
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_to_order_meta' ), 20 );

		// Show in cart item data
		add_filter( 'woocommerce_get_item_data', array( $this, 'show_in_cart' ), 20, 2 );

		// Show in admin order page
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_in_admin_order' ), 20, 1 );
		add_filter( 'woocommerce_admin_billing_fields', array( $this, 'filter_admin_address_fields' ), 20, 3 );
		add_filter( 'woocommerce_admin_shipping_fields', array( $this, 'filter_admin_address_fields' ), 20, 3 );

		// Include in WooCommerce emails
		add_filter( 'woocommerce_email_order_meta_fields', array( $this, 'add_to_email_fields' ), 20, 3 );

		// Show on order received / account order page
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_on_order_received' ), 20 );
		add_action( 'woocommerce_thankyou', array( $this, 'display_on_thankyou' ), 20 );

		// Also save to mptbm_booking post_meta after booking is created
		add_action( 'mptbm_booking_created', array( $this, 'save_to_booking_post' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'sync_block_checkout_order_data' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'sync_block_checkout_order_data' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'capture_block_checkout_order_data_from_request' ), 20, 2 );

		// Hook into MPTBM_Layout_Pro::order_info output via action
		add_action( 'mptbm_after_order_info', array( $this, 'render_in_order_info' ), 10, 1 );
		add_action( 'mptbm_cart_item_display', array( $this, 'render_in_cart_booking_details' ), 10, 2 );

		// AJAX: return vehicle form HTML for vehicle-select step
		add_action( 'wp_ajax_mptbm_vfb_get_vehicle_form',        array( $this, 'ajax_get_vehicle_form' ) );
		add_action( 'wp_ajax_nopriv_mptbm_vfb_get_vehicle_form', array( $this, 'ajax_get_vehicle_form' ) );

		// Vehicle-select UI is handled in the main booking script to avoid race
		// conditions with the core select-car flow.
	}

		/* ------------------------------------------------------------------ */
		/* Helper: get vehicle ID from cart item                               */
		/* ------------------------------------------------------------------ */

		private function get_vehicle_id_from_cart_item( $cart_item ) {
			$post_id = null;

			if ( isset( $cart_item['mptbm_id'] ) ) {
				$post_id = absint( $cart_item['mptbm_id'] );
			} elseif ( isset( $cart_item['product_id'] ) ) {
				$product_id = absint( $cart_item['product_id'] );
				$linked     = MP_Global_Function::get_post_info( $product_id, 'link_mptbm_id', $product_id );
				if ( get_post_type( $linked ) === MPTBM_Function::get_cpt() ) {
					$post_id = $linked;
				}
			}

			return $post_id;
		}

		private function persist_active_vehicle_id( $vehicle_id ) {
			$vehicle_id = absint( $vehicle_id );

			if ( ! $vehicle_id ) {
				return;
			}

			if ( function_exists( 'WC' ) && WC()->session ) {
				WC()->session->set( 'mptbm_vfb_active_vehicle_id', $vehicle_id );
			}

			if ( session_status() === PHP_SESSION_NONE && ! headers_sent() ) {
				session_start();
			}

			if ( session_status() === PHP_SESSION_ACTIVE ) {
				$_SESSION['mptbm_vfb_active_vehicle_id'] = $vehicle_id;
			}

			if ( ! headers_sent() ) {
				setcookie( 'mptbm_vfb_active_vehicle_id', (string) $vehicle_id, time() + DAY_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
			}
		}

		private function get_vehicle_fields_from_native_session_or_cookie() {
			$vehicle_id = 0;

			if ( session_status() === PHP_SESSION_NONE && ! headers_sent() ) {
				session_start();
			}

			if ( session_status() === PHP_SESSION_ACTIVE && ! empty( $_SESSION['mptbm_vfb_active_vehicle_id'] ) ) {
				$vehicle_id = absint( $_SESSION['mptbm_vfb_active_vehicle_id'] );
			}

			if ( ! $vehicle_id && ! empty( $_COOKIE['mptbm_vfb_active_vehicle_id'] ) ) {
				$vehicle_id = absint( wp_unslash( $_COOKIE['mptbm_vfb_active_vehicle_id'] ) );
			}

			if ( ! $vehicle_id ) {
				return array( 'vehicle_id' => null, 'fields' => array() );
			}

			$fields = MPTBM_Vehicle_Form_Builder::get_fields( $vehicle_id );
			if ( empty( $fields ) ) {
				return array( 'vehicle_id' => null, 'fields' => array() );
			}

			return array(
				'vehicle_id' => $vehicle_id,
				'fields'     => $fields,
			);
		}

		private function get_vehicle_fields_from_session() {
			if ( ! function_exists( 'WC' ) || ! WC()->session ) {
				return $this->get_vehicle_fields_from_native_session_or_cookie();
			}

			$vehicle_id = absint( WC()->session->get( 'mptbm_vfb_active_vehicle_id' ) );
			if ( $vehicle_id ) {
				$fields = MPTBM_Vehicle_Form_Builder::get_fields( $vehicle_id );
				if ( ! empty( $fields ) ) {
					return array(
						'vehicle_id' => $vehicle_id,
						'fields'     => $fields,
					);
				}
			}

			$session_cart = WC()->session->get( 'cart', array() );
			if ( ! is_array( $session_cart ) ) {
				return array( 'vehicle_id' => null, 'fields' => array() );
			}

			foreach ( $session_cart as $cart_item ) {
				$vehicle_id = $this->get_vehicle_id_from_cart_item( (array) $cart_item );
				if ( ! $vehicle_id ) {
					continue;
				}

				$fields = MPTBM_Vehicle_Form_Builder::get_fields( $vehicle_id );
				if ( empty( $fields ) ) {
					continue;
				}

				$this->persist_active_vehicle_id( $vehicle_id );

				return array(
					'vehicle_id' => $vehicle_id,
					'fields'     => $fields,
				);
			}

			$native_session_data = $this->get_vehicle_fields_from_native_session_or_cookie();
			if ( ! empty( $native_session_data['vehicle_id'] ) ) {
				return $native_session_data;
			}

			return array( 'vehicle_id' => null, 'fields' => array() );
		}

		private function get_vehicle_fields_from_order( $order ) {
			if ( ! $order instanceof WC_Order ) {
				return array( 'vehicle_id' => null, 'fields' => array() );
			}

			foreach ( $order->get_items() as $item ) {
				$vehicle_id = absint( $item->get_meta( '_mptbm_vfb_vehicle_id', true ) );

				if ( ! $vehicle_id ) {
					$vehicle_id = absint( $item->get_meta( '_mptbm_id', true ) );
				}

				if ( ! $vehicle_id && method_exists( $item, 'get_product_id' ) ) {
					$product_id = absint( $item->get_product_id() );
					if ( $product_id ) {
						$linked_id = MP_Global_Function::get_post_info( $product_id, 'link_mptbm_id', $product_id );
						if ( get_post_type( $linked_id ) === MPTBM_Function::get_cpt() ) {
							$vehicle_id = absint( $linked_id );
						}
					}
				}

				if ( ! $vehicle_id ) {
					continue;
				}

				$fields = MPTBM_Vehicle_Form_Builder::get_fields( $vehicle_id );
				if ( empty( $fields ) ) {
					continue;
				}

				$this->persist_active_vehicle_id( $vehicle_id );

				return array(
					'vehicle_id' => $vehicle_id,
					'fields'     => $fields,
				);
			}

			return array( 'vehicle_id' => null, 'fields' => array() );
		}

		private function save_form_data_to_order( $order, $form_data ) {
			if ( ! $order instanceof WC_Order ) {
				return;
			}

			$order->update_meta_data( self::ORDER_META_KEY, $form_data );
			$order->save();
		}

		private static function get_form_data_from_order_object( $order ) {
			if ( ! $order instanceof WC_Order ) {
				return array();
			}

			$form_data = $order->get_meta( self::ORDER_META_KEY, true );

			if ( ! empty( $form_data ) && is_array( $form_data ) ) {
				return $form_data;
			}

			foreach ( $order->get_items() as $item ) {
				$meta = $item->get_meta( self::ORDER_ITEM_META_KEY, true );
				if ( ! empty( $meta ) && is_array( $meta ) ) {
					return $meta;
				}
			}

			return array();
		}

		/* ------------------------------------------------------------------ */
		/* Helper: get all fields for the vehicle currently in cart            */
		/* ------------------------------------------------------------------ */

		private function get_active_vehicle_fields() {
			if ( ! did_action( 'wp_loaded' ) || ! function_exists( 'WC' ) ) {
				return array( 'vehicle_id' => null, 'fields' => array() );
			}

			if ( WC()->cart ) {
				foreach ( WC()->cart->get_cart() as $cart_item ) {
					$vid = $this->get_vehicle_id_from_cart_item( $cart_item );
					if ( $vid ) {
						$fields = MPTBM_Vehicle_Form_Builder::get_fields( $vid );
						if ( ! empty( $fields ) ) {
							$this->persist_active_vehicle_id( $vid );

							return array( 'vehicle_id' => $vid, 'fields' => $fields );
						}
					}
				}
			}

			return $this->get_vehicle_fields_from_session();
		}

		/* ------------------------------------------------------------------ */
		/* Helper: build field key used in POST / meta                         */
		/* ------------------------------------------------------------------ */

		public static function field_post_key( $uid ) {
			return 'mptbm_vf_' . sanitize_key( $uid );
		}

		private function should_store_field_value( $field ) {
			$show_on_checkout = isset( $field['show_on_checkout'] ) ? (bool) $field['show_on_checkout'] : false;
			$show_on_select   = isset( $field['show_on_select'] ) ? (bool) $field['show_on_select'] : false;
			$legacy_show      = isset( $field['show'] ) ? (bool) $field['show'] : false;

			return $show_on_checkout || $show_on_select || $legacy_show;
		}

		private function get_cart_item_form_data( $cart_item, $fields ) {
			$data = array();

			foreach ( $fields as $field ) {
				if ( ! $this->should_store_field_value( $field ) ) {
					continue;
				}

				$uid = $field['uid'] ?? '';
				if ( ! $uid ) {
					continue;
				}

				$value = $cart_item[ self::field_post_key( $uid ) ] ?? '';
				if ( $value === '' || $value === array() ) {
					continue;
				}

				$data[] = array(
					'label' => $field['label'] ?? '',
					'type'  => $field['type'] ?? 'text',
					'value' => $value,
				);
			}

			return $data;
		}

		private function get_checkout_only_fields( $fields ) {
			return array_values(
				array_filter(
					(array) $fields,
					static function ( $field ) {
						$show_on_checkout = isset( $field['show_on_checkout'] ) ? (bool) $field['show_on_checkout'] : ( isset( $field['show'] ) ? (bool) $field['show'] : false );

						return ! empty( $field['uid'] ) && ! empty( $field['label'] ) && $show_on_checkout;
					}
				)
			);
		}

		private function get_block_checkout_field_id( $uid ) {
			return 'mptbm-vfb/' . sanitize_key( $uid );
		}

		private function get_block_checkout_default_value( $uid ) {
			if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
				return '';
			}

			$key = self::field_post_key( $uid );

			foreach ( WC()->cart->get_cart() as $cart_item ) {
				if ( isset( $cart_item[ $key ] ) ) {
					$value = $cart_item[ $key ];

					return is_array( $value ) ? implode( ', ', $value ) : (string) $value;
				}
			}

			return '';
		}

		private function map_field_for_block_checkout( $field ) {
			$type        = $field['type'] ?? 'text';
			$label       = $field['label'] ?? '';
			$required    = ! empty( $field['required'] );
			$options     = isset( $field['options'] ) ? array_values( array_filter( (array) $field['options'] ) ) : array();
			$field_id    = $this->get_block_checkout_field_id( $field['uid'] ?? '' );

			if ( empty( $label ) || empty( $field_id ) ) {
				return array();
			}

			$config = array(
				'id'                         => $field_id,
				'label'                      => $label,
				'location'                   => 'order',
				'required'                   => $required,
				'optionalLabel'              => '',
				'show_in_order_confirmation' => false,
			);

			if ( in_array( $type, array( 'select', 'radio' ), true ) && ! empty( $options ) ) {
				$config['type']    = 'select';
				$config['options'] = array_map(
					static function ( $option ) {
						return array(
							'value' => (string) $option,
							'label' => (string) $option,
						);
					},
					$options
				);
			} elseif ( 'checkbox' === $type ) {
				$config['type'] = 'checkbox';
			} elseif ( 'file' === $type ) {
				return array();
			} else {
				$config['type'] = 'text';
			}

			if ( in_array( $type, array( 'number', 'tel', 'date' ), true ) ) {
				$config['attributes'] = array(
					'autocomplete' => 'off',
				);
			}

			return $config;
		}

		private function validate_block_checkout_field( $field, $value ) {
			$type  = $field['type'] ?? 'text';
			$label = $field['label'] ?? __( 'This field', 'ecab-taxi-booking-manager' );
			$value = is_string( $value ) ? trim( $value ) : $value;

			if ( ! empty( $field['required'] ) && '' === $value ) {
				return new WP_Error( 'mptbm_vfb_required', sprintf( __( '%s is required.', 'ecab-taxi-booking-manager' ), $label ) );
			}

			if ( '' === $value ) {
				return true;
			}

			if ( 'email' === $type && ! is_email( $value ) ) {
				return new WP_Error( 'mptbm_vfb_email', sprintf( __( '%s must be a valid email address.', 'ecab-taxi-booking-manager' ), $label ) );
			}

			if ( 'number' === $type && ! is_numeric( $value ) ) {
				return new WP_Error( 'mptbm_vfb_number', sprintf( __( '%s must be a valid number.', 'ecab-taxi-booking-manager' ), $label ) );
			}

			return true;
		}

		private function get_block_checkout_field_value( $order, $field_id, $group = 'other' ) {
			if (
				! class_exists( '\Automattic\WooCommerce\Blocks\Package' ) ||
				! class_exists( '\Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields' )
			) {
				return '';
			}

			try {
				$container = \Automattic\WooCommerce\Blocks\Package::container();
				$service   = $container->get( \Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class );

				return $service->get_field_from_object( $field_id, $order, $group );
			} catch ( Exception $exception ) {
				return '';
			}
		}

		private function get_block_checkout_field_value_from_groups( $order, $field_id, $groups = array( 'billing', 'shipping', 'other' ) ) {
			foreach ( (array) $groups as $group ) {
				$value = $this->get_block_checkout_field_value( $order, $field_id, $group );

				if ( '' !== $value && null !== $value && array() !== $value ) {
					return $value;
				}
			}

			return '';
		}

		private function get_order_for_sync( $order_source ) {
			if ( $order_source instanceof WC_Order ) {
				return $order_source;
			}

			if ( is_numeric( $order_source ) ) {
				return wc_get_order( absint( $order_source ) );
			}

			if ( is_object( $order_source ) && isset( $order_source->id ) ) {
				return wc_get_order( absint( $order_source->id ) );
			}

			if ( is_string( $order_source ) ) {
				$decoded = json_decode( $order_source );
				if ( ! empty( $decoded->id ) ) {
					return wc_get_order( absint( $decoded->id ) );
				}
			}

			return false;
		}

		private function sanitize_form_field_value( $field, $value ) {
			$type = $field['type'] ?? 'text';

			if ( is_array( $value ) ) {
				return array_values( array_filter( array_map( 'sanitize_text_field', $value ), 'strlen' ) );
			}

			if ( 'textarea' === $type ) {
				return sanitize_textarea_field( (string) $value );
			}

			return sanitize_text_field( (string) $value );
		}

		private function build_form_data_from_request_sources( $fields, $sources, $default_group = 'other' ) {
			$form_data = array();

			foreach ( (array) $fields as $field ) {
				if ( empty( $field['uid'] ) || empty( $field['label'] ) ) {
					continue;
				}

				$field_id = $this->get_block_checkout_field_id( $field['uid'] );
				$group    = $default_group;
				$value    = null;

				foreach ( (array) $sources as $source_group => $source_values ) {
					if ( ! is_array( $source_values ) || ! array_key_exists( $field_id, $source_values ) ) {
						continue;
					}

					$value = $source_values[ $field_id ];
					$group = $source_group;
					break;
				}

				if ( null === $value ) {
					continue;
				}

				$value = $this->sanitize_form_field_value( $field, $value );
				if ( '' === $value || array() === $value ) {
					continue;
				}

				$form_data[ $field['uid'] ] = array(
					'label' => $field['label'],
					'type'  => $field['type'] ?? 'text',
					'value' => $value,
					'group' => $group,
				);
			}

			return $form_data;
		}

		private function build_form_data_from_wc_data_object( $fields, $wc_object, $groups = array( 'billing', 'shipping', 'other' ) ) {
			$form_data = array();

			if ( ! $wc_object ) {
				return $form_data;
			}

			foreach ( (array) $fields as $field ) {
				if ( empty( $field['uid'] ) || empty( $field['label'] ) ) {
					continue;
				}

				$field_id = $this->get_block_checkout_field_id( $field['uid'] );
				$value    = null;
				$group    = 'other';

				foreach ( (array) $groups as $candidate_group ) {
					$value = $this->get_block_checkout_field_value( $wc_object, $field_id, $candidate_group );

					if ( '' !== $value && null !== $value && array() !== $value ) {
						$group = $candidate_group;
						break;
					}
				}

				if ( '' === $value || null === $value || array() === $value ) {
					continue;
				}

				$form_data[ $field['uid'] ] = array(
					'label' => $field['label'],
					'type'  => $field['type'] ?? 'text',
					'value' => $this->sanitize_form_field_value( $field, $value ),
					'group' => $group,
				);
			}

			return $form_data;
		}

		public function capture_block_checkout_order_data_from_request( $order, $request ) {
			if ( ! $order instanceof WC_Order || ! $request || ! method_exists( $request, 'get_param' ) ) {
				return;
			}

			$data   = $this->get_vehicle_fields_from_order( $order );
			if ( empty( $data['fields'] ) ) {
				$data = $this->get_active_vehicle_fields();
			}
			$fields = $this->get_checkout_only_fields( $data['fields'] ?? array() );
			if ( empty( $fields ) ) {
				return;
			}

			$sources = array(
				'billing'  => (array) $request->get_param( 'billing_address' ),
				'shipping' => (array) $request->get_param( 'shipping_address' ),
				'other'    => (array) $request->get_param( 'additional_fields' ),
			);

			$form_data = $this->build_form_data_from_request_sources( $fields, $sources, 'other' );
			if ( empty( $form_data ) && function_exists( 'WC' ) && WC()->customer ) {
				$form_data = $this->build_form_data_from_wc_data_object( $fields, WC()->customer, array( 'billing', 'shipping', 'other' ) );
			}
			if ( empty( $form_data ) ) {
				return;
			}

			if (
				class_exists( '\Automattic\WooCommerce\Blocks\Package' ) &&
				class_exists( '\Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields' )
			) {
				try {
					$container = \Automattic\WooCommerce\Blocks\Package::container();
					$service   = $container->get( \Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class );

					foreach ( $form_data as $uid => $entry ) {
						$service->persist_field_for_order(
							$this->get_block_checkout_field_id( $uid ),
							$entry['value'],
							$order,
							$entry['group'] ?? 'other',
							false
						);
					}
				} catch ( Exception $exception ) {
					// Keep our own order meta fallback below even if WooCommerce Blocks persistence fails.
				}
			}

			$stored_form_data = array();
			foreach ( $form_data as $uid => $entry ) {
				unset( $entry['group'] );
				$stored_form_data[ $uid ] = $entry;
			}

			$this->save_form_data_to_order( $order, $stored_form_data );
		}

		/* ------------------------------------------------------------------ */
		/* Render on checkout                                                  */
		/* ------------------------------------------------------------------ */

		public function register_block_checkout_fields() {
			if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
				return;
			}

			if ( ! did_action( 'wp_loaded' ) ) {
				return;
			}

			if ( $this->block_fields_registered ) {
				return;
			}

			$data   = $this->get_active_vehicle_fields();
			$fields = $this->get_checkout_only_fields( $data['fields'] ?? array() );

			if ( empty( $fields ) ) {
				return;
			}

			foreach ( $fields as $field ) {
				$config = $this->map_field_for_block_checkout( $field );

				if ( empty( $config ) || empty( $config['id'] ) ) {
					continue;
				}

				$config['sanitize_callback'] = function ( $value ) use ( $field ) {
					$type = $field['type'] ?? 'text';

					if ( 'textarea' === $type ) {
						return sanitize_textarea_field( (string) $value );
					}

					return sanitize_text_field( (string) $value );
				};

				$config['validate_callback'] = function ( $value ) use ( $field ) {
					return $this->validate_block_checkout_field( $field, $value );
				};

				woocommerce_register_additional_checkout_field( $config );

				add_filter(
					'woocommerce_get_default_value_for_' . $config['id'],
					function ( $default ) use ( $field ) {
						$value = $this->get_block_checkout_default_value( $field['uid'] );

						return '' !== $value ? $value : $default;
					}
				);
			}

			$this->block_fields_registered = true;
		}

		public function render_checkout_fields( $checkout ) {
			$data   = $this->get_active_vehicle_fields();
			$fields = $data['fields'];
			$vid    = $data['vehicle_id'];

			if ( empty( $fields ) || ! $vid ) {
				return;
			}

			$types = MPTBM_Vehicle_Form_Builder::get_field_types();

		echo '<div class="mptbm-vfb-checkout-section" id="mptbm_vfb_checkout_section">';
		echo '<h3 class="mptbm-vfb-checkout-title">' . esc_html__( 'Additional Information', 'ecab-taxi-booking-manager' ) . '</h3>';

		foreach ( $fields as $field ) {
			// Legacy compat: fall back to 'show' if show_on_checkout not set
			$on_checkout = isset( $field['show_on_checkout'] ) ? (bool) $field['show_on_checkout'] : ( isset( $field['show'] ) ? (bool) $field['show'] : true );
			if ( ! $on_checkout ) {
				continue;
			}

				$uid         = $field['uid'];
				$key         = self::field_post_key( $uid );
				$label       = $field['label'];
				$placeholder = $field['placeholder'] ?? '';
				$desc        = $field['description'] ?? '';
				$type        = $field['type'] ?? 'text';
				$required    = ! empty( $field['required'] );
				$options     = isset( $field['options'] ) ? (array) $field['options'] : array();
				$value       = $checkout->get_value( $key );
				$field_id    = 'mptbm_vfb_' . esc_attr( $key );

				echo '<p class="form-row form-row-wide mptbm-vfb-checkout-field" id="row_' . esc_attr( $key ) . '" data-field="' . esc_attr( $key ) . '">';
				echo '<label for="' . esc_attr( $field_id ) . '">' . esc_html( $label );
				if ( $required ) {
					echo ' <span class="required">*</span>';
				}
				echo '</label>';

				if ( in_array( $type, array( 'text', 'number', 'email', 'tel', 'date' ), true ) ) {
					echo '<input type="' . esc_attr( $type ) . '" class="input-text" name="' . esc_attr( $key ) . '" id="' . esc_attr( $field_id ) . '" placeholder="' . esc_attr( $placeholder ) . '" value="' . esc_attr( (string) $value ) . '" ' . ( $required ? 'required' : '' ) . ' />';
				} elseif ( $type === 'textarea' ) {
					echo '<textarea name="' . esc_attr( $key ) . '" id="' . esc_attr( $field_id ) . '" class="input-text" placeholder="' . esc_attr( $placeholder ) . '" rows="3" ' . ( $required ? 'required' : '' ) . '>' . esc_textarea( (string) $value ) . '</textarea>';
				} elseif ( $type === 'select' ) {
					echo '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $field_id ) . '" class="input-select" ' . ( $required ? 'required' : '' ) . '>';
					echo '<option value="">' . esc_html__( '— Select —', 'ecab-taxi-booking-manager' ) . '</option>';
					foreach ( $options as $opt ) {
						echo '<option value="' . esc_attr( $opt ) . '"' . selected( $value, $opt, false ) . '>' . esc_html( $opt ) . '</option>';
					}
					echo '</select>';
				} elseif ( $type === 'radio' ) {
					foreach ( $options as $opt ) {
						$opt_id = $field_id . '_' . sanitize_key( $opt );
						echo '<label class="mptbm-vfb-radio-label"><input type="radio" name="' . esc_attr( $key ) . '" id="' . esc_attr( $opt_id ) . '" value="' . esc_attr( $opt ) . '"' . ( $value === $opt ? ' checked' : '' ) . '> ' . esc_html( $opt ) . '</label> ';
					}
				} elseif ( $type === 'checkbox' ) {
					$saved_vals = is_array( $value ) ? $value : array();
					foreach ( $options as $opt ) {
						$opt_id = $field_id . '_' . sanitize_key( $opt );
						echo '<label class="mptbm-vfb-checkbox-label"><input type="checkbox" name="' . esc_attr( $key ) . '[]" id="' . esc_attr( $opt_id ) . '" value="' . esc_attr( $opt ) . '"' . ( in_array( $opt, $saved_vals, true ) ? ' checked' : '' ) . '> ' . esc_html( $opt ) . '</label> ';
					}
				} elseif ( $type === 'file' ) {
					$upload_nonce = wp_create_nonce( 'mptbm_file_upload' );
					echo '<input type="file" class="input-file mptbm-file-upload mptbm-vfb-file" data-field="' . esc_attr( $key ) . '" data-nonce="' . esc_attr( $upload_nonce ) . '" accept="image/*,application/pdf,.doc,.docx">';
					echo '<input type="hidden" name="' . esc_attr( $key ) . '" id="' . esc_attr( $field_id ) . '_hidden" value="' . esc_attr( (string) $value ) . '">';
					echo '<span class="mptbm-vfb-file-status"></span>';
				}

				if ( ! empty( $desc ) ) {
					echo '<span class="description">' . esc_html( $desc ) . '</span>';
				}

				echo '</p>';
			}

			echo '</div>';

			// Inline style
			echo '<style>
				.mptbm-vfb-checkout-section { margin: 20px 0; padding: 18px 20px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; }
				.mptbm-vfb-checkout-title { margin: 0 0 14px 0 !important; font-size: 16px !important; }
				.mptbm-vfb-radio-label, .mptbm-vfb-checkbox-label { display: inline-flex; align-items: center; gap: 6px; margin-right: 12px; cursor: pointer; }
				.mptbm-vfb-file-status { display: block; font-size: 12px; color: #666; margin-top: 4px; }
			</style>';
		}

		public function sync_block_checkout_order_data( $order_source ) {
			$order = $this->get_order_for_sync( $order_source );

			if ( ! $order ) {
				return;
			}

			$vehicle_id = 0;
			$order_item = false;

			foreach ( $order->get_items() as $item ) {
				$item_vehicle_id = (int) $item->get_meta( '_mptbm_vfb_vehicle_id', true );

				if ( ! $item_vehicle_id ) {
					$item_vehicle_id = (int) $item->get_meta( '_mptbm_id', true );
				}

				if ( $item_vehicle_id ) {
					$vehicle_id = $item_vehicle_id;
					$order_item = $item;
					break;
				}
			}

			if ( ! $vehicle_id ) {
				return;
			}

			$fields = $this->get_checkout_only_fields( MPTBM_Vehicle_Form_Builder::get_fields( $vehicle_id ) );
			if ( empty( $fields ) ) {
				return;
			}

			$form_data = array();

			foreach ( $fields as $field ) {
				$field_id = $this->get_block_checkout_field_id( $field['uid'] );
				$value    = $this->get_block_checkout_field_value_from_groups( $order, $field_id, array( 'billing', 'shipping', 'other' ) );

				if ( '' === $value || array() === $value || null === $value ) {
					continue;
				}

				$form_data[ $field['uid'] ] = array(
					'label' => $field['label'],
					'type'  => $field['type'] ?? 'text',
					'value' => $value,
				);
			}

			if ( empty( $form_data ) ) {
				if ( function_exists( 'WC' ) && WC()->customer ) {
					$customer_form_data = $this->build_form_data_from_wc_data_object( $fields, WC()->customer, array( 'billing', 'shipping', 'other' ) );
					foreach ( $customer_form_data as $uid => $entry ) {
						$field_id = $this->get_block_checkout_field_id( $uid );

						if (
							class_exists( '\Automattic\WooCommerce\Blocks\Package' ) &&
							class_exists( '\Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields' )
						) {
							try {
								$container = \Automattic\WooCommerce\Blocks\Package::container();
								$service   = $container->get( \Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class );
								$service->persist_field_for_order( $field_id, $entry['value'], $order, $entry['group'] ?? 'other', false );
							} catch ( Exception $exception ) {
								// Keep local fallback below even if this fails.
							}
						}

						unset( $entry['group'] );
						$form_data[ $uid ] = $entry;
					}
				}
			}

			if ( empty( $form_data ) ) {
				$form_data = self::get_form_data_for_order( $order->get_id() );
			}

			if ( empty( $form_data ) ) {
				return;
			}

			$this->save_form_data_to_order( $order, $form_data );

			if ( $order_item ) {
				$order_item->delete_meta_data( self::ORDER_ITEM_META_KEY );
				$order_item->delete_meta_data( '_mptbm_vfb_vehicle_id' );
				$order_item->add_meta_data( self::ORDER_ITEM_META_KEY, $form_data, true );
				$order_item->add_meta_data( '_mptbm_vfb_vehicle_id', $vehicle_id, true );

				foreach ( $form_data as $entry ) {
					if ( empty( $entry['label'] ) || ! array_key_exists( 'value', $entry ) ) {
						continue;
					}

					$order_item->delete_meta_data( $entry['label'] );
					$display_value = is_array( $entry['value'] ) ? implode( ', ', $entry['value'] ) : $entry['value'];
					$order_item->add_meta_data( $entry['label'], $display_value, true );
				}

				$order_item->save();
			}
		}

		/* ------------------------------------------------------------------ */
		/* Validate required fields                                            */
		/* ------------------------------------------------------------------ */

		public function validate_checkout_fields() {
			$data   = $this->get_active_vehicle_fields();
			$fields = $data['fields'];

		foreach ( $fields as $field ) {
			$on_checkout = isset( $field['show_on_checkout'] ) ? (bool) $field['show_on_checkout'] : ( isset( $field['show'] ) ? (bool) $field['show'] : true );
			if ( ! $on_checkout || empty( $field['required'] ) ) {
				continue;
			}
				$key   = self::field_post_key( $field['uid'] );
				$label = $field['label'];
				$type  = $field['type'] ?? 'text';

				if ( $type === 'checkbox' ) {
					if ( empty( $_POST[ $key ] ) || ! is_array( $_POST[ $key ] ) ) {
						wc_add_notice( sprintf( __( '"%s" is a required field.', 'ecab-taxi-booking-manager' ), $label ), 'error' );
					}
				} else {
					if ( empty( $_POST[ $key ] ) ) {
						wc_add_notice( sprintf( __( '"%s" is a required field.', 'ecab-taxi-booking-manager' ), $label ), 'error' );
					}
				}
			}
		}

		/* ------------------------------------------------------------------ */
		/* Save to WC order item meta                                          */
		/* ------------------------------------------------------------------ */

		public function save_to_order_item( $item, $cart_item_key, $values, $order ) {
			$vid = $this->get_vehicle_id_from_cart_item( $values );
			if ( ! $vid ) {
				return;
			}

			$fields = MPTBM_Vehicle_Form_Builder::get_fields( $vid );
			if ( empty( $fields ) ) {
				return;
			}

			$form_data = $this->collect_form_data( $fields );
			if ( ! empty( $form_data ) ) {
				$item->add_meta_data( self::ORDER_ITEM_META_KEY, $form_data, true );
				// Store vehicle id reference
				$item->add_meta_data( '_mptbm_vfb_vehicle_id', $vid, true );

				// Also add visible line-item meta so custom fields appear with the
				// rest of the booking details in WooCommerce order/cart flows.
				foreach ( $form_data as $entry ) {
					if ( empty( $entry['label'] ) || ! array_key_exists( 'value', $entry ) ) {
						continue;
					}
					$display_value = is_array( $entry['value'] ) ? implode( ', ', $entry['value'] ) : $entry['value'];
					$item->add_meta_data( $entry['label'], $display_value, true );
				}
			}
		}

		/* ------------------------------------------------------------------ */
		/* Save to WC order / booking post meta                                */
		/* ------------------------------------------------------------------ */

		public function save_to_order_meta( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return;
			}

			$vid    = null;
			$fields = array();

			foreach ( $order->get_items() as $item_id => $item ) {
				$vid_meta = $item->get_meta( '_mptbm_vfb_vehicle_id', true );
				if ( $vid_meta ) {
					$vid    = $vid_meta;
					$fields = MPTBM_Vehicle_Form_Builder::get_fields( $vid );
					break;
				}
			}

			if ( empty( $fields ) ) {
				return;
			}

			$form_data = $this->collect_form_data( $fields );
			if ( ! empty( $form_data ) ) {
				$order->update_meta_data( self::ORDER_META_KEY, $form_data );
				$order->save();
			}
		}

		/* ------------------------------------------------------------------ */
		/* Also store on mptbm_booking post (called via hook after creation)  */
		/* ------------------------------------------------------------------ */

		public function save_to_booking_post( $attendee_id, $order_id ) {
			$form_data = self::get_form_data_for_order( $order_id );
			if ( $form_data ) {
				update_post_meta( $attendee_id, self::ORDER_META_KEY, $form_data );
			}
		}

		/* ------------------------------------------------------------------ */
		/* Collect form data from $_POST                                       */
		/* ------------------------------------------------------------------ */

		private function collect_form_data( $fields ) {
			$data = array();
			foreach ( $fields as $field ) {
				if ( ! $this->should_store_field_value( $field ) ) {
					continue;
				}
				$key  = self::field_post_key( $field['uid'] );
				$type = $field['type'] ?? 'text';

				if ( $type === 'checkbox' ) {
					$val = isset( $_POST[ $key ] ) ? array_map( 'sanitize_text_field', (array) $_POST[ $key ] ) : array();
				} elseif ( $type === 'textarea' ) {
					$val = isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : '';
				} else {
					$val = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
				}

				if ( $val !== '' && $val !== array() ) {
					$data[ $field['uid'] ] = array(
						'label' => $field['label'],
						'type'  => $type,
						'value' => $val,
					);
				}
			}
			return $data;
		}

		/* ------------------------------------------------------------------ */
		/* Show in cart (display custom data in cart line items)               */
		/* ------------------------------------------------------------------ */

		public function show_in_cart( $item_data, $cart_item ) {
			if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
				return $item_data;
			}

			$vid = $this->get_vehicle_id_from_cart_item( $cart_item );
			if ( ! $vid ) {
				return $item_data;
			}

			$fields = MPTBM_Vehicle_Form_Builder::get_fields( $vid );
			$form_data = $this->get_cart_item_form_data( $cart_item, $fields );
			foreach ( $form_data as $entry ) {
				$display = is_array( $entry['value'] ) ? implode( ', ', $entry['value'] ) : $entry['value'];
				$item_data[] = array(
					'name'  => $entry['label'],
					'value' => $display,
				);
			}

			return $item_data;
		}

		public function render_in_cart_booking_details( $cart_item, $post_id ) {
			$vid = $this->get_vehicle_id_from_cart_item( $cart_item );
			if ( ! $vid ) {
				return;
			}

			$fields    = MPTBM_Vehicle_Form_Builder::get_fields( $vid );
			$form_data = $this->get_cart_item_form_data( $cart_item, $fields );

			if ( empty( $form_data ) ) {
				return;
			}
			?>
			<li class="mptbm-vfb-cart-heading">
				<span class="fas fa-clipboard-list"></span>
				<h6 class="_mR_xs"><?php esc_html_e( 'Additional Information', 'ecab-taxi-booking-manager' ); ?> :</h6>
			</li>
			<?php foreach ( $form_data as $entry ) : ?>
				<li class="mptbm-vfb-cart-field">
					<span class="fas fa-angle-right"></span>
					<h6 class="_mR_xs"><?php echo esc_html( $entry['label'] ); ?> :</h6>
					<span>
						<?php
						if ( 'file' === $entry['type'] && ! empty( $entry['value'] ) ) {
							$url = esc_url( (string) $entry['value'] );
							echo '<a href="' . $url . '" target="_blank">' . esc_html( basename( (string) $entry['value'] ) ) . '</a>';
						} else {
							echo esc_html( is_array( $entry['value'] ) ? implode( ', ', $entry['value'] ) : (string) $entry['value'] );
						}
						?>
					</span>
				</li>
			<?php endforeach; ?>
			<?php
		}

		/* ------------------------------------------------------------------ */
		/* Display in admin WC order page                                      */
		/* ------------------------------------------------------------------ */

		public function display_in_admin_order( $order ) {
			$form_data = self::get_form_data_from_order_object( $order );

			// Also try reading from order items
			if ( empty( $form_data ) ) {
				foreach ( $order->get_items() as $item ) {
					$meta = $item->get_meta( self::ORDER_ITEM_META_KEY, true );
					if ( $meta ) {
						$form_data = $meta;
						break;
					}
				}
			}

			if ( empty( $form_data ) || ! is_array( $form_data ) ) {
				return;
			}

			echo '<div class="mptbm-vfb-admin-order-data">';
			echo '<h4 style="border-top:1px solid #eee;padding-top:12px;margin-top:14px;">' . esc_html__( 'Custom Booking Details', 'ecab-taxi-booking-manager' ) . '</h4>';
			echo '<table class="mptbm-vfb-data-table" style="width:100%;border-collapse:collapse;">';
			foreach ( $form_data as $uid => $entry ) {
				if ( empty( $entry['label'] ) ) {
					continue;
				}
				$display = is_array( $entry['value'] ) ? implode( ', ', array_map( 'esc_html', $entry['value'] ) ) : esc_html( (string) $entry['value'] );
				if ( $entry['type'] === 'file' && ! empty( $entry['value'] ) ) {
					$url     = esc_url( (string) $entry['value'] );
					$display = '<a href="' . $url . '" target="_blank">' . esc_html( basename( (string) $entry['value'] ) ) . '</a>';
				}
				echo '<tr><th style="text-align:left;padding:4px 8px;background:#f9f9f9;border:1px solid #eee;width:35%;">' . esc_html( $entry['label'] ) . '</th>';
				echo '<td style="padding:4px 8px;border:1px solid #eee;">' . $display . '</td></tr>';
			}
			echo '</table></div>';
		}

		/* ------------------------------------------------------------------ */
		/* Show on order received page (frontend)                              */
		/* ------------------------------------------------------------------ */

		public function display_on_order_received( $order ) {
			if ( ! $order instanceof WC_Order ) {
				return;
			}

			$order_id = $order->get_id();
			if ( $this->has_rendered_frontend_order_section( $order_id ) ) {
				return;
			}

			$form_data = self::get_form_data_for_order( $order_id );
			if ( empty( $form_data ) ) {
				return;
			}

			if ( $this->is_form_data_already_visible_in_order_items( $order, $form_data ) ) {
				return;
			}

			$this->mark_frontend_order_section_rendered( $order_id );
			$this->render_frontend_order_section( $form_data );
		}

		public function display_on_thankyou( $order_id ) {
			$order_id = absint( $order_id );
			if ( ! $order_id || $this->has_rendered_frontend_order_section( $order_id ) ) {
				return;
			}

			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return;
			}

			$form_data = self::get_form_data_for_order( $order_id );
			if ( empty( $form_data ) ) {
				return;
			}

			if ( $this->is_form_data_already_visible_in_order_items( $order, $form_data ) ) {
				return;
			}

			$this->mark_frontend_order_section_rendered( $order_id );
			$this->render_frontend_order_section( $form_data );
		}

		/* ------------------------------------------------------------------ */
		/* Add to email fields                                                  */
		/* ------------------------------------------------------------------ */

		public function add_to_email_fields( $fields, $sent_to_admin, $order ) {
			$form_data = array();

			foreach ( $order->get_items() as $item ) {
				$meta = $item->get_meta( self::ORDER_ITEM_META_KEY, true );
				if ( $meta ) {
					$form_data = $meta;
					break;
				}
			}
			if ( empty( $form_data ) ) {
				$form_data = self::get_form_data_from_order_object( $order );
			}

			if ( empty( $form_data ) || ! is_array( $form_data ) ) {
				return $fields;
			}

			foreach ( $form_data as $uid => $entry ) {
				if ( empty( $entry['label'] ) ) {
					continue;
				}
				$val = $entry['value'];
				if ( is_array( $val ) ) {
					$val = implode( ', ', $val );
				}
				if ( $entry['type'] === 'file' && ! empty( $val ) ) {
					$val = '<a href="' . esc_url( $val ) . '">' . esc_html( basename( $val ) ) . '</a>';
				} else {
					$val = esc_html( (string) $val );
				}

				$fields[ 'mptbm_vf_email_' . $uid ] = array(
					'label' => $entry['label'],
					'value' => $val,
				);
			}

			return $fields;
		}

		/* ------------------------------------------------------------------ */
		/* Render in order info panel (MPTBM_Layout_Pro::order_info)           */
		/* ------------------------------------------------------------------ */

		public function render_in_order_info( $attendee_id ) {
			if ( ! empty( $GLOBALS['mptbm_suppress_inline_order_info_custom_fields'] ) ) {
				return;
			}

			$form_data = self::get_form_data_for_booking( $attendee_id );

			if ( empty( $form_data ) || ! is_array( $form_data ) ) {
				return;
			}

			echo '<div class="mptbm-vfb-order-info-section">';
			echo '<h4>' . esc_html__( 'Additional Booking Details', 'ecab-taxi-booking-manager' ) . '</h4>';
			echo '<table style="width:100%;border-collapse:collapse;">';
			foreach ( $form_data as $uid => $entry ) {
				if ( empty( $entry['label'] ) ) {
					continue;
				}
				$display = is_array( $entry['value'] ) ? implode( ', ', array_map( 'esc_html', (array) $entry['value'] ) ) : esc_html( (string) $entry['value'] );
				if ( 'file' === ( $entry['type'] ?? '' ) && ! empty( $entry['value'] ) ) {
					$url     = esc_url( (string) $entry['value'] );
					$display = '<a href="' . $url . '" target="_blank">' . esc_html( basename( (string) $entry['value'] ) ) . '</a>';
				}
				echo '<tr>';
				echo '<td style="padding:4px 8px;font-weight:600;background:#f9f9f9;border:1px solid #eee;width:40%;">' . esc_html( $entry['label'] ) . '</td>';
				echo '<td style="padding:4px 8px;border:1px solid #eee;">' . $display . '</td>';
				echo '</tr>';
			}
			echo '</table></div>';
		}

		private function has_rendered_frontend_order_section( $order_id ) {
			return isset( self::$rendered_frontend_orders[ absint( $order_id ) ] );
		}

		private function mark_frontend_order_section_rendered( $order_id ) {
			self::$rendered_frontend_orders[ absint( $order_id ) ] = true;
		}

		private function is_form_data_already_visible_in_order_items( $order, $form_data ) {
			if ( ! $order instanceof WC_Order || empty( $form_data ) || ! is_array( $form_data ) ) {
				return false;
			}

			foreach ( $form_data as $entry ) {
				$label = $entry['label'] ?? '';
				if ( '' === $label ) {
					continue;
				}

				$found = false;

				foreach ( $order->get_items() as $item ) {
					$value = $item->get_meta( $label, true );
					if ( '' !== $value && null !== $value ) {
						$found = true;
						break;
					}
				}

				if ( ! $found ) {
					return false;
				}
			}

			return true;
		}

		private function render_frontend_order_section( $form_data ) {
			echo '<section class="mptbm-vfb-order-received">';
			echo '<h2 class="woocommerce-order-details__title">' . esc_html__( 'Additional Booking Details', 'ecab-taxi-booking-manager' ) . '</h2>';
			echo '<table class="woocommerce-table mptbm-vfb-data-table">';
			foreach ( $form_data as $uid => $entry ) {
				if ( empty( $entry['label'] ) ) {
					continue;
				}

				$display = is_array( $entry['value'] ) ? implode( ', ', array_map( 'esc_html', (array) $entry['value'] ) ) : esc_html( (string) $entry['value'] );
				if ( 'file' === ( $entry['type'] ?? '' ) && ! empty( $entry['value'] ) ) {
					$url     = esc_url( (string) $entry['value'] );
					$display = '<a href="' . $url . '" target="_blank">' . esc_html( basename( (string) $entry['value'] ) ) . '</a>';
				}

				echo '<tr><th>' . esc_html( $entry['label'] ) . '</th><td>' . $display . '</td></tr>';
			}
			echo '</table></section>';
		}

		public function filter_admin_address_fields( $fields, $order = false, $context = 'edit' ) {
			if ( empty( $fields ) || ! is_array( $fields ) ) {
				return $fields;
			}

			foreach ( array_keys( $fields ) as $field_key ) {
				if ( 0 === strpos( (string) $field_key, 'mptbm-vfb/' ) ) {
					unset( $fields[ $field_key ] );
				}
			}

			return $fields;
		}

		/* ------------------------------------------------------------------ */
		/* Static helper: get form data for a booking post / order             */
		/* (used by PDF, CSV, email templates)                                 */
		/* ------------------------------------------------------------------ */

		public static function get_form_data_for_order( $order_id ) {
			$form_data = array();

			if ( function_exists( 'wc_get_order' ) ) {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					$form_data = self::get_form_data_from_order_object( $order );
				}
			}

			if ( empty( $form_data ) ) {
				$form_data = get_post_meta( $order_id, self::ORDER_META_KEY, true );
			}

			return is_array( $form_data ) ? $form_data : array();
		}

		public static function get_form_data_for_booking( $attendee_id ) {
			$form_data = get_post_meta( $attendee_id, self::ORDER_META_KEY, true );
			if ( ! empty( $form_data ) && is_array( $form_data ) ) {
				return $form_data;
			}
			// Try from linked order
			$order_id = get_post_meta( $attendee_id, 'mptbm_order_id', true );
			if ( $order_id ) {
				return self::get_form_data_for_order( $order_id );
			}
			return array();
		}

	/* ------------------------------------------------------------------ */
	/* AJAX: return vehicle-select form HTML for a given vehicle ID       */
	/* ------------------------------------------------------------------ */

	public function ajax_get_vehicle_form() {
		$vid = isset( $_POST['vehicle_id'] ) ? absint( $_POST['vehicle_id'] ) : 0;
		if ( ! $vid ) {
			wp_send_json_success( array( 'html' => '' ) );
		}

		$fields = MPTBM_Vehicle_Form_Builder::get_fields( $vid );
		// Filter: only fields with show_on_select = true (default true for legacy fields)
		$select_fields = array_filter( $fields, function( $f ) {
			// If show_on_select is not explicitly set, default to true (legacy compat)
			return isset( $f['show_on_select'] ) ? (bool) $f['show_on_select'] : true;
		} );

		if ( empty( $select_fields ) ) {
			wp_send_json_success( array( 'html' => '' ) );
		}

		ob_start();
		$this->render_vehicle_select_form( $vid, array_values( $select_fields ) );
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/* ------------------------------------------------------------------ */
	/* Render vehicle-select inline form (shown after "Select Car" click) */
	/* ------------------------------------------------------------------ */

	public function render_vehicle_select_form( $vid, $fields ) {
		$types = MPTBM_Vehicle_Form_Builder::get_field_types();
		?>
		<div class="mptbm-vfb-select-section" data-vehicle-id="<?php echo esc_attr( $vid ); ?>">
			<h3 class="mptbm-vfb-select-title">
				<span class="fas fa-clipboard-list"></span>
				<?php esc_html_e( 'Additional Information', 'ecab-taxi-booking-manager' ); ?>
			</h3>
			<div class="mptbm-vfb-select-fields">
			<?php foreach ( $fields as $field ) :
				$uid         = $field['uid'];
				$key         = self::field_post_key( $uid );
				$label       = $field['label'];
				$placeholder = $field['placeholder'] ?? '';
				$desc        = $field['description'] ?? '';
				$type        = $field['type'] ?? 'text';
				$required    = ! empty( $field['required'] );
				$options     = isset( $field['options'] ) ? (array) $field['options'] : array();
				$field_id    = 'mptbm_vfb_sel_' . esc_attr( $key );
			?>
				<div class="mptbm-vfb-sel-row" data-field="<?php echo esc_attr( $key ); ?>">
					<label for="<?php echo esc_attr( $field_id ); ?>">
						<?php echo esc_html( $label ); ?>
						<?php if ( $required ) : ?><span class="required" style="color:#d63638;">*</span><?php endif; ?>
					</label>

					<?php if ( in_array( $type, array( 'text', 'number', 'email', 'tel', 'date' ), true ) ) : ?>
						<input type="<?php echo esc_attr( $type ); ?>"
							name="mptbm_vfb_sel[<?php echo esc_attr( $uid ); ?>]"
							id="<?php echo esc_attr( $field_id ); ?>"
							class="mptbm-vfb-sel-input"
							placeholder="<?php echo esc_attr( $placeholder ); ?>"
							data-uid="<?php echo esc_attr( $uid ); ?>"
							data-key="<?php echo esc_attr( $key ); ?>"
							<?php echo $required ? 'required' : ''; ?> />

					<?php elseif ( $type === 'textarea' ) : ?>
						<textarea name="mptbm_vfb_sel[<?php echo esc_attr( $uid ); ?>]"
							id="<?php echo esc_attr( $field_id ); ?>"
							class="mptbm-vfb-sel-input"
							placeholder="<?php echo esc_attr( $placeholder ); ?>"
							data-uid="<?php echo esc_attr( $uid ); ?>"
							data-key="<?php echo esc_attr( $key ); ?>"
							rows="3"
							<?php echo $required ? 'required' : ''; ?>></textarea>

					<?php elseif ( $type === 'select' ) : ?>
						<select name="mptbm_vfb_sel[<?php echo esc_attr( $uid ); ?>]"
							id="<?php echo esc_attr( $field_id ); ?>"
							class="mptbm-vfb-sel-input"
							data-uid="<?php echo esc_attr( $uid ); ?>"
							data-key="<?php echo esc_attr( $key ); ?>"
							<?php echo $required ? 'required' : ''; ?>>
							<option value=""><?php esc_html_e( '— Select —', 'ecab-taxi-booking-manager' ); ?></option>
							<?php foreach ( $options as $opt ) : ?>
								<option value="<?php echo esc_attr( $opt ); ?>"><?php echo esc_html( $opt ); ?></option>
							<?php endforeach; ?>
						</select>

					<?php elseif ( $type === 'radio' ) : ?>
						<div class="mptbm-vfb-sel-options">
						<?php foreach ( $options as $opt ) :
							$opt_id = $field_id . '_' . sanitize_key( $opt );
						?>
							<label class="mptbm-vfb-sel-option-label">
								<input type="radio"
									name="mptbm_vfb_sel[<?php echo esc_attr( $uid ); ?>]"
									id="<?php echo esc_attr( $opt_id ); ?>"
									value="<?php echo esc_attr( $opt ); ?>"
									data-uid="<?php echo esc_attr( $uid ); ?>"
									data-key="<?php echo esc_attr( $key ); ?>"
									<?php echo $required ? 'required' : ''; ?> />
								<?php echo esc_html( $opt ); ?>
							</label>
						<?php endforeach; ?>
						</div>

					<?php elseif ( $type === 'checkbox' ) : ?>
						<div class="mptbm-vfb-sel-options">
						<?php foreach ( $options as $opt ) :
							$opt_id = $field_id . '_' . sanitize_key( $opt );
						?>
							<label class="mptbm-vfb-sel-option-label">
								<input type="checkbox"
									name="mptbm_vfb_sel[<?php echo esc_attr( $uid ); ?>][]"
									id="<?php echo esc_attr( $opt_id ); ?>"
									value="<?php echo esc_attr( $opt ); ?>"
									data-uid="<?php echo esc_attr( $uid ); ?>"
									data-key="<?php echo esc_attr( $key ); ?>" />
								<?php echo esc_html( $opt ); ?>
							</label>
						<?php endforeach; ?>
						</div>

					<?php endif; ?>

					<?php if ( $desc ) : ?>
						<span class="description"><?php echo esc_html( $desc ); ?></span>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
			</div>
		</div>
		<style>
		.mptbm-vfb-select-section {
			margin: 12px 0 8px;
			padding: 16px 18px;
			background: #f9fafb;
			border: 1px solid #e2e8f0;
			border-left: 4px solid var(--color_theme, #e91e8c);
			border-radius: 6px;
			animation: mptbmVfbFadeIn .25s ease;
		}
		@keyframes mptbmVfbFadeIn {
			from { opacity: 0; transform: translateY(-6px); }
			to   { opacity: 1; transform: translateY(0); }
		}
		.mptbm-vfb-select-title {
			margin: 0 0 12px !important;
			font-size: 14px !important;
			font-weight: 600;
			display: flex;
			align-items: center;
			gap: 7px;
			color: var(--color_theme, #e91e8c);
		}
		.mptbm-vfb-sel-row {
			display: flex;
			flex-direction: column;
			gap: 8px;
			margin-bottom: 14px;
		}
		.mptbm-vfb-sel-row > label {
			display: block;
			font-weight: 600;
			font-size: 12px;
			line-height: 1.4;
			margin-bottom: 0;
			color: #374151;
		}
		.mptbm-vfb-sel-input {
			width: 100%;
			padding: 7px 10px;
			border: 1px solid #d1d5db;
			border-radius: 4px;
			font-size: 13px;
			background: #fff;
			box-sizing: border-box;
			transition: border-color .15s;
		}
		.mptbm-vfb-sel-input:focus {
			outline: none;
			border-color: var(--color_theme, #e91e8c);
			box-shadow: 0 0 0 2px rgba(233,30,140,.12);
		}
		.mptbm-vfb-sel-input[type="date"] { cursor: pointer; }
		.mptbm-vfb-sel-row select.mptbm-vfb-sel-input { height: 36px; }
		.mptbm-vfb-sel-row textarea.mptbm-vfb-sel-input { resize: vertical; }
		.mptbm-vfb-sel-options {
			display: flex;
			flex-wrap: wrap;
			gap: 10px;
			margin-top: 2px;
		}
		.mptbm-vfb-sel-option-label {
			display: inline-flex;
			align-items: center;
			gap: 5px;
			font-size: 13px;
			cursor: pointer;
			font-weight: 400;
			color: #374151;
		}
		.mptbm-vfb-sel-row .description {
			display: block;
			font-size: 11px;
			color: #9ca3af;
			line-height: 1.45;
			margin-top: 0;
		}
		.mptbm-vfb-sel-required-error {
			border-color: #ef4444 !important;
		}
		.mptbm-vfb-sel-error-msg {
			color: #ef4444;
			font-size: 11px;
			margin-top: 2px;
			display: block;
		}
		</style>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Enqueue JS to hook into vehicle-select button click                 */
	/* ------------------------------------------------------------------ */

	public function enqueue_vehicle_select_js() {
		// Only on pages that have the MPTBM shortcode (check for the search area)
		if ( ! is_page() && ! is_front_page() && ! is_singular() ) {
			return;
		}
		?>
		<script type="text/javascript">
		(function($){
			'use strict';

			var VFB_AJAX = '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>';

			// Container class for the inline form (rendered after extra_service area)
			var SEL_CONTAINER = 'mptbm-vfb-select-container';

		/**
		 * Load the vehicle-select form via AJAX and inject it
		 */
		function loadVehicleForm(postId, $searchArea, $vehicleWrapper) {
			// Remove any previously injected form
			$searchArea.find('.' + SEL_CONTAINER).remove();

			$.ajax({
				url: VFB_AJAX,
				type: 'POST',
				data: { action: 'mptbm_vfb_get_vehicle_form', vehicle_id: postId },
				success: function(res) {
					if (res && res.success && res.data.html) {
						var $container = $('<div class="' + SEL_CONTAINER + '"></div>').html(res.data.html);

						// Inject right after the vehicle wrapper card
						if ($vehicleWrapper && $vehicleWrapper.length) {
							$vehicleWrapper.after($container);
						} else {
							// Fallback 1: after extra_service area
							var $extraService = $searchArea.find('.mptbm_extra_service');
							if ($extraService.length) {
								$extraService.after($container);
							} else {
								// Fallback 2: before book-now button
								var $bookNow = $searchArea.find('.mptbm_book_now');
								if ($bookNow.length) {
									$bookNow.closest('.justifyBetween').before($container);
								} else {
									$searchArea.find('.tabsContentNext, .dLayout').last().after($container);
								}
							}
						}

						// Scroll to the form
						if ($container.offset()) {
							$('html,body').animate({ scrollTop: $container.offset().top - 80 }, 300);
						}
					}
				}
			});
		}

		/**
		 * Collect vehicle-select form data and store in hidden inputs
		 * so they are available during add-to-cart POST
		 */
		function collectSelectFormData($searchArea) {
			var data = {};
			$searchArea.find('.' + SEL_CONTAINER + ' .mptbm-vfb-sel-input').each(function() {
				var $el  = $(this);
				var uid  = $el.data('uid');
				var type = this.type;
				if (!uid) return;

				if (type === 'checkbox') {
					if (!data[uid]) data[uid] = [];
					if (this.checked) data[uid].push($el.val());
				} else if (type === 'radio') {
					if (this.checked) data[uid] = $el.val();
				} else {
					data[uid] = $el.val();
				}
			});
			return data;
		}

		/**
		 * Validate required fields in vehicle-select form
		 * Returns true if valid, false + highlights errors if not
		 */
		function validateSelectForm($searchArea) {
			var valid = true;
			$searchArea.find('.' + SEL_CONTAINER + ' .mptbm-vfb-sel-row').each(function() {
				var $row    = $(this);
				var $inputs = $row.find('.mptbm-vfb-sel-input');
				var isRequired = $inputs.first().prop('required') ||
					$row.find('[required]').length > 0;

				if (!isRequired) return;

				$row.find('.mptbm-vfb-sel-error-msg').remove();
				$inputs.removeClass('mptbm-vfb-sel-required-error');

				var type = $inputs.first().attr('type');
				var hasValue = false;

				if (type === 'checkbox') {
					hasValue = $row.find('input:checked').length > 0;
				} else if (type === 'radio') {
					hasValue = $row.find('input:checked').length > 0;
				} else {
					hasValue = $.trim($inputs.first().val()) !== '';
				}

				if (!hasValue) {
					valid = false;
					$inputs.first().addClass('mptbm-vfb-sel-required-error');
					var label = $row.find('label').first().text().replace('*','').trim();
					$row.append('<span class="mptbm-vfb-sel-error-msg">' +
						label + ' <?php echo esc_js( __('is required.', 'ecab-taxi-booking-manager') ); ?>' +
						'</span>');
				}
			});
			return valid;
		}

		// ── Hook into "Select Car" button click ──────────────────────────
		$(document).on('click', '.mptbm_transport_search_area .mptbm_transport_select', function() {
			var $this   = $(this);
			var postId  = $this.data('post-id');
			var $searchArea = $this.closest('.mptbm_transport_search_area');

			// Remove any previously injected form (on any vehicle)
			$searchArea.find('.' + SEL_CONTAINER).remove();

			// The vehicle wrapper for this specific car
			var $vehicleWrapper = $this.closest('.mptbm-vehicle-wrapper');
			// Fallback: closest booking item
			if (!$vehicleWrapper.length) {
				$vehicleWrapper = $this.closest('.mptbm_booking_item');
			}

			// Short delay to let the original JS update the DOM first
			// (original handler adds active_select class inside a promise/async chain)
			setTimeout(function() {
				// After delay, check if button is now active (selected) or de-selected
				if (!$this.hasClass('active_select')) {
					// De-selected: don't show form
					return;
				}
				loadVehicleForm(postId, $searchArea, $vehicleWrapper);
			}, 500);
		});

		// ── Hook into "Book Now" button click to validate & collect data ─
		$(document).on('click', '.mptbm_transport_search_area .mptbm_book_now', function(e) {
			var $searchArea = $(this).closest('.mptbm_transport_search_area');
			var $form       = $searchArea.find('.' + SEL_CONTAINER);

			if (!$form.length) return; // no form, skip

			if (!validateSelectForm($searchArea)) {
				e.stopImmediatePropagation();
				// Scroll to first error
				var $err = $searchArea.find('.mptbm-vfb-sel-required-error').first();
				if ($err.offset()) {
					$('html,body').animate({ scrollTop: $err.offset().top - 80 }, 250 );
				}
				return false;
			}

			// Collect and store form data on the search area element
			var formData = collectSelectFormData($searchArea);
			$searchArea.data('mptbm_vfb_sel_data', formData);
		}, 5); // priority 5 = runs before original handler

		// ── Intercept mptbm_add_to_cart AJAX to inject vehicle-select form data ─
		// Uses jQuery prefilter so ALL calls to mptbm_add_to_cart automatically
		// include the collected vehicle form data.
		$.ajaxPrefilter(function(options, originalOptions, jqXHR) {
			// Only intercept mptbm_add_to_cart POSTs
			var dataStr = (typeof options.data === 'string') ? options.data : '';
			if (dataStr.indexOf('action=mptbm_add_to_cart') === -1) return;

			// Find the search area that has form data stored
			var formData = null;
			$('.mptbm_transport_search_area').each(function() {
				var d = $(this).data('mptbm_vfb_sel_data');
				if (d && Object.keys(d).length) {
					formData = d;
					return false; // break
				}
			});

			if (!formData) return;

			// Append mptbm_vf_* fields to the POST data string
			var extraParams = '';
			$.each(formData, function(uid, val) {
				if ($.isArray(val)) {
					$.each(val, function(i, v) {
						extraParams += '&' + encodeURIComponent('mptbm_vf_' + uid + '[]') + '=' + encodeURIComponent(v);
					});
				} else {
					extraParams += '&' + encodeURIComponent('mptbm_vf_' + uid) + '=' + encodeURIComponent(val);
				}
			});

			if (extraParams && typeof options.data === 'string') {
				options.data += extraParams;
			}
		});

		})(jQuery);
		</script>
		<?php
	}

	

		public static function render_as_html_table( $form_data, $heading = true ) {
			if ( empty( $form_data ) || ! is_array( $form_data ) ) {
				return '';
			}
			$html = '';
			if ( $heading ) {
				$html .= '<h4 style="margin:10px 0 6px;">' . esc_html__( 'Custom Booking Fields', 'ecab-taxi-booking-manager' ) . '</h4>';
			}
			$html .= '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
			foreach ( $form_data as $uid => $entry ) {
				if ( empty( $entry['label'] ) ) {
					continue;
				}
				$val = is_array( $entry['value'] ) ? implode( ', ', $entry['value'] ) : (string) $entry['value'];
				if ( 'file' === ( $entry['type'] ?? '' ) && ! empty( $entry['value'] ) ) {
					$url = esc_url( (string) $entry['value'] );
					$val = '<a href="' . $url . '" target="_blank">' . esc_html( basename( (string) $entry['value'] ) ) . '</a>';
				} else {
					$val = esc_html( $val );
				}
				$html .= '<tr>';
				$html .= '<td style="padding:4px 8px;border:1px solid #ddd;background:#f5f5f5;font-weight:600;width:40%;">' . esc_html( $entry['label'] ) . '</td>';
				$html .= '<td style="padding:4px 8px;border:1px solid #ddd;">' . $val . '</td>';
				$html .= '</tr>';
			}
			$html .= '</table>';
			return $html;
		}

		/* ------------------------------------------------------------------ */
		/* Static: render as plain text rows (for CSV, plain emails)          */
		/* ------------------------------------------------------------------ */

		public static function render_as_text( $form_data ) {
			if ( empty( $form_data ) || ! is_array( $form_data ) ) {
				return '';
			}
			$lines = array();
			foreach ( $form_data as $uid => $entry ) {
				if ( empty( $entry['label'] ) ) {
					continue;
				}
				$val    = is_array( $entry['value'] ) ? implode( ', ', $entry['value'] ) : (string) $entry['value'];
				$lines[] = $entry['label'] . ': ' . $val;
			}
			return implode( "\n", $lines );
		}
	}

	new MPTBM_Vehicle_Form_Frontend();
}
