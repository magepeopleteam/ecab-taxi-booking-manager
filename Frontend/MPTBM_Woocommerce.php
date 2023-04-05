<?php
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	} // Cannot access pages directly.
	if ( ! class_exists( 'MPTBM_Woocommerce' ) ) {
		class MPTBM_Woocommerce {
			public function __construct() {
				add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 90, 3 );
				add_action( 'woocommerce_before_calculate_totals', array( $this, 'before_calculate_totals' ), 90, 1 );
				add_filter( 'woocommerce_cart_item_thumbnail', array( $this, 'cart_item_thumbnail' ), 90, 3 );
				add_filter( 'woocommerce_get_item_data', array( $this, 'get_item_data' ), 90, 2 );
				//************//
				add_action( 'woocommerce_after_checkout_validation', array( $this, 'after_checkout_validation' ) );
				add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'checkout_create_order_line_item' ), 90, 4 );
				add_action( 'woocommerce_checkout_order_processed', array( $this, 'checkout_order_processed' ), 10 );
				add_filter( 'woocommerce_order_status_changed', array( $this, 'order_status_changed' ), 10, 4 );
			}
			public function add_cart_item_data( $cart_item_data, $product_id ) {
				$linked_id  = MPTBM_Function::get_post_info( $product_id, 'link_mptbm_id', $product_id );
				$product_id = is_string( get_post_status( $linked_id ) ) ? $linked_id : $linked_id;
				if ( get_post_type( $product_id ) == MPTBM_Function::get_cpt_name() ) {
					$total_price                                = $this->get_cart_total_price( $product_id );
					$cart_item_data['mptbm_date']               = MPTBM_Function::get_submit_info( 'mptbm_date' );
					$cart_item_data['mptbm_start_place']        = MPTBM_Function::get_submit_info( 'mptbm_start_place' );
					$cart_item_data['mptbm_end_place']          = MPTBM_Function::get_submit_info( 'mptbm_end_place' );
					$cart_item_data['mptbm_distance']           = $_COOKIE['mptbm_distance_text'] ?? '';
					$cart_item_data['mptbm_duration']           = $_COOKIE['mptbm_duration_text'] ?? '';
                    $cart_item_data['mptbm_currency']           = get_woocommerce_currency();
                    $cart_item_data['mptbm_currency_pos']       = get_option( 'woocommerce_currency_pos' );
                    $cart_item_data['mptbm_base_price']         = $this->get_base_price( $product_id );
                    $cart_item_data['mptbm_user_info']          = apply_filters( 'add_mptbm_user_info_data', array(), $product_id );
					$cart_item_data['mptbm_extra_service_info'] = self::cart_extra_service_info( $product_id );
                    $cart_item_data['mptbm_booking_info']       = self::get_booking_info();
					$cart_item_data['mptbm_tp']                 = $total_price;
					$cart_item_data['line_total']               = $total_price;
					$cart_item_data['line_subtotal']            = $total_price;
					$cart_item_data                             = apply_filters( 'mptbm_add_cart_item', $cart_item_data, $product_id );
				}
				$cart_item_data['mptbm_id'] = $product_id;

				return $cart_item_data;
			}
			public function before_calculate_totals( $cart_object ) {
				foreach ( $cart_object->cart_contents as $value ) {
					$mptbm_id = array_key_exists( 'mptbm_id', $value ) ? $value['mptbm_id'] : 0;
					if ( get_post_type( $mptbm_id ) == MPTBM_Function::get_cpt_name() ) {
						$total_price = $value['mptbm_tp'];
						$value['data']->set_price( $total_price );
						$value['data']->set_regular_price( $total_price );
						$value['data']->set_sale_price( $total_price );
						$value['data']->set_sold_individually( 'yes' );
						$value['data']->get_price();
					}
				}
			}
			public function cart_item_thumbnail( $thumbnail, $cart_item ) {
				$mptbm_id = array_key_exists( 'mptbm_id', $cart_item ) ? $cart_item['mptbm_id'] : 0;
				if ( get_post_type( $mptbm_id ) == MPTBM_Function::get_cpt_name() ) {
					$thumbnail = '<div class="bg_image_area" data-href="' . get_the_permalink( $mptbm_id ) . '"><div data-bg-image="' . MPTBM_Function::get_image_url( $mptbm_id ) . '"></div></div>';
				}
				return $thumbnail;
			}
			public function get_item_data( $item_data, $cart_item ) {
				ob_start();
				$post_id = array_key_exists( 'mptbm_id', $cart_item ) ? $cart_item['mptbm_id'] : 0;
				if ( get_post_type( $post_id ) == MPTBM_Function::get_cpt_name() ) {
					$this->show_cart_item( $cart_item, $post_id );
					do_action( 'mptbm_show_cart_item', $cart_item, $post_id );
				}
				$item_data[] = array( 'key' => ob_get_clean() );
				return $item_data;
			}
			//**************//
			public function after_checkout_validation() {
				global $woocommerce;
				$items = $woocommerce->cart->get_cart();

				foreach ( $items as $values ) {
					$post_id = array_key_exists( 'mptbm_id', $values ) ? $values['mptbm_id'] : 0;
					if ( get_post_type( $post_id ) == MPTBM_Function::get_cpt_name() ) {
						do_action( 'mptbm_validate_cart_item', $values, $post_id );
					}
				}
			}
			public function checkout_create_order_line_item( $item, $cart_item_key, $values ) {
				$post_id = array_key_exists( 'mptbm_id', $values ) ? $values['mptbm_id'] : 0;
				if ( get_post_type( $post_id ) == MPTBM_Function::get_cpt_name() ) {
					$data_format    = MPTBM_Function::date_format();
					$date           = $values['mptbm_date'] ?? '';
					$start_location = $values['mptbm_start_place'] ?? '';
					$end_location   = $values['mptbm_end_place'] ?? '';
					$distance       = $values['mptbm_distance'] ?? '';
					$duration       = $values['mptbm_duration'] ?? '';
                    $currency       = $values['mptbm_currency'] ?? '';
                    $currency_pos   = $values['mptbm_currency_pos'] ?? '';
                    $base_price     = $values['mptbm_base_price'] ?? '';
					$extra_service  = $values['mptbm_extra_service_info'] ?? [];
                    $booking_info  = $values['mptbm_booking_info'] ?? [];
					$user_info      = $values['mptbm_user_info'] ?? [];
					$item->add_meta_data( esc_html__( 'Start Location : ', 'mptbm_plugin' ), $start_location );
					$item->add_meta_data( esc_html__( 'Destination Location : ', 'mptbm_plugin' ), $end_location );
					$item->add_meta_data( esc_html__( 'Distance : ', 'mptbm_plugin' ), $distance );
					$item->add_meta_data( esc_html__( 'Approximate Time : ', 'mptbm_plugin' ), $duration );
					$item->add_meta_data( esc_html__( 'Date : ', 'mptbm_plugin' ), date_i18n( $data_format, strtotime( $date ) ) );
					$item->add_meta_data( esc_html__( 'Time : ', 'mptbm_plugin' ), MPTBM_Function::datetime_format( $date, 'time' ) );
					if ( sizeof( $extra_service ) > 0 ) {
						foreach ( $extra_service as $service ) {
							$item->add_meta_data( esc_html__( 'Services Name : ', 'mptbm_plugin' ), $service['service_name'] );
                            $item->add_meta_data( esc_html__( 'Services Quantity : ', 'mptbm_plugin' ), $service['service_quantity'] );
                            $item->add_meta_data( esc_html__( 'Price : ', 'mptbm_plugin' ), ' ( ' . MPTBM_Function::wc_price( $post_id, $service['service_price'] ) . ' x '.$service['service_quantity'].' ) = ' . MPTBM_Function::wc_price( $post_id, ( $service['service_price'] * $service['service_quantity'] ) ) );
						}
					}
					$item->add_meta_data( '_mptbm_id', $post_id );
					$item->add_meta_data( '_mptbm_date', $date );
					$item->add_meta_data( '_mptbm_start_place', $start_location );
					$item->add_meta_data( '_mptbm_end_place', $end_location );
					$item->add_meta_data( '_mptbm_distance', $distance );
					$item->add_meta_data( '_mptbm_duration', $duration );
                    $item->add_meta_data( '_mptbm_currency', $currency );
                    $item->add_meta_data( '_mptbm_currency_pos', $currency_pos );
                    $item->add_meta_data( '_mptbm_base_price', $base_price );
					$item->add_meta_data( '_mptbm_user_info', $user_info );
					$item->add_meta_data( '_mptbm_service_info', $extra_service );
                    $item->add_meta_data( '_mptbm_booking_info', $booking_info );

					do_action( 'mptbm_checkout_create_order_line_item', $item, $values );
				}
			}
			public function checkout_order_processed( $order_id ) {
				if ( $order_id ) {
					$order          = wc_get_order( $order_id );
					$order_status   = $order->get_status();
					$order_meta     = get_post_meta( $order_id );
					$payment_method = $order_meta['_payment_method_title'][0] ?? '';
					$user_id        = $order_meta['_customer_user'][0] ?? '';
					if ( $order_status != 'failed' ) {
						//$item_id = current( array_keys( $order->get_items() ) );
						foreach ( $order->get_items() as $item_id => $item ) {
							$post_id = MPTBM_Query::get_order_meta( $item_id, '_mptbm_id' );
							if ( get_post_type( $post_id ) == MPTBM_Function::get_cpt_name() ) {
								$date        = self::get_order_item_meta( $item_id, '_mptbm_date' );
								$date        = $date ? MPTBM_Function::data_sanitize( $date ) : '';
								$start_place = self::get_order_item_meta( $item_id, '_mptbm_start_place' );
								$start_place = $start_place ? MPTBM_Function::data_sanitize( $start_place ) : '';
								$end_place   = self::get_order_item_meta( $item_id, '_mptbm_end_place' );
								$end_place   = $end_place ? MPTBM_Function::data_sanitize( $end_place ) : '';
								$distance    = self::get_order_item_meta( $item_id, '_mptbm_distance' );
								$distance    = $distance ? MPTBM_Function::data_sanitize( $distance ) : '';
								$duration    = self::get_order_item_meta( $item_id, '_mptbm_duration' );
								$duration    = $duration ? MPTBM_Function::data_sanitize( $duration ) : '';
                                $currency    = self::get_order_item_meta( $item_id, '_mptbm_currency' );
                                $currency    = $currency ? MPTBM_Function::data_sanitize( $currency ) : '';
                                $currency_pos    = self::get_order_item_meta( $item_id, '_mptbm_currency_pos' );
                                $currency_pos    = $currency_pos ? MPTBM_Function::data_sanitize( $currency_pos ) : '';
                                $base_price    = self::get_order_item_meta( $item_id, '_mptbm_base_price' );
                                $base_price    = $base_price ? MPTBM_Function::data_sanitize( $base_price ) : '';
                                $user_info = self::get_order_item_meta( $item_id, '_mptbm_user_info' );
								$user_info = $user_info ? MPTBM_Function::data_sanitize( $user_info ) : [];
                                $booking_info = self::get_order_item_meta( $item_id, '_mptbm_booking_info' );
                                $booking_info = $booking_info ? MPTBM_Function::data_sanitize( $booking_info ) : [];
                                $data['mptbm_id']          = $post_id;
								$data['mptbm_date']        = $date;
								$data['mptbm_start_place'] = $start_place;
								$data['mptbm_end_place']   = $end_place;
								$data['mptbm_distance']    = $distance;
								$data['mptbm_duration']    = $duration;
                                $data['mptbm_currency_pos']    = $currency_pos;
                                $data['mptbm_currency']    = $currency;
                                $data['mptbm_base_price']    = $base_price;
								$data['mptbm_order_id']        = $order_id;
								$data['mptbm_order_status']    = $order_status;
								$data['mptbm_payment_method']  = $payment_method;
								$data['mptbm_user_id']         = $user_id;
								$data['mptbm_billing_name']    = $order_meta['_billing_first_name'][0] . ' ' . $order_meta['_billing_last_name'][0];
								$data['mptbm_billing_email']   = $order_meta['_billing_email'][0];
								$data['mptbm_billing_phone']   = $order_meta['_billing_phone'][0];
								$data['mptbm_billing_address'] = $order_meta['_billing_address_1'][0] . ' ' . $order_meta['_billing_address_2'][0];
								$user_data = apply_filters( 'mptbm_user_booking_data', $data, $post_id, $user_info );
								self::add_cpt_data( 'mptbm_booking', $user_data['mptbm_billing_name'], $user_data );
								$service      = self::get_order_item_meta( $item_id, '_mptbm_service_info' );
								$service_info = $service ? MPTBM_Function::data_sanitize( $service ) : [];
								if ( sizeof( $service_info ) > 0 ) {
									foreach ( $service_info as $service ) {
										$ex_data['mptbm_id']             = $post_id;
										$ex_data['mptbm_date']           = $date;
										$ex_data['mptbm_order_id']       = $order_id;
										$ex_data['mptbm_order_status']   = $order_status;
										$ex_data['mptbm_service_name']   = $service['service_name'];
                                        $ex_data['mptbm_service_quantity']   = $service['service_quantity'];
										$ex_data['mptbm_service_price']  = $service['service_price'];
										$ex_data['mptbm_payment_method'] = $payment_method;
										$ex_data['mptbm_user_id']        = $user_id;
										self::add_cpt_data( 'mptbm_service_booking', '#' . $order_id . $ex_data['mptbm_service_name'], $ex_data );
									}
								}


                                $booking_info      = self::get_order_item_meta( $item_id, '_mptbm_booking_info' );
                                $booking_infos = $booking_info ? MPTBM_Function::data_sanitize( $booking_info ) : [];
                                self::add_cpt_data( 'mptbm_booking', $booking_infos['mptbm_booking_info'], $booking_infos );

                            }
						}
					}
				}
			}
			public function order_status_changed( $order_id ) {
				$order        = wc_get_order( $order_id );
				$order_status = $order->get_status();
				foreach ( $order->get_items() as $item_id => $item_values ) {
					$post_id = MPTBM_Query::get_order_meta( $item_id, '_mptbm_id' );
					if ( get_post_type( $post_id ) == MPTBM_Function::get_cpt_name() ) {
						if ( 
							$order->has_status( 'processing' ) ||
						     $order->has_status( 'pending' ) ||
							$order->has_status( 'on-hold' ) ||
							$order->has_status( 'completed' ) ||
							$order->has_status( 'cancelled' ) ||
							$order->has_status( 'refunded' ) ||
							$order->has_status( 'failed' ) ||
							$order->has_status( 'requested' )
						) {
							$this->wc_order_status_change($order_status, $post_id, $order_id );
						}
					}
				}
			}
			//**************************//
			public function show_cart_item( $cart_item, $post_id ) {
				$data_format    = MPTBM_Function::date_format();
				$date           = $cart_item['mptbm_date'];
				$start_location = $cart_item['mptbm_start_place'];
				$end_location   = $cart_item['mptbm_end_place'];
                $base_price   = $cart_item['mptbm_base_price'];
				$extra_service  = $cart_item['mptbm_extra_service_info'] ?? [];
                $booking_infos  = $cart_item['mptbm_booking_info'] ?? [];
				?>
				<div class="mpStyle">
					<?php do_action( 'mptbm_before_cart_item_display', $cart_item, $post_id ); ?>
					<div class="dLayout_xs">
						<ul class="cart_list">
							<li>
								<span class="fas fa-map-marker-alt"></span>
								<h6><?php esc_html_e( 'Start Location : ', 'mptbm_plugin' ); ?></h6>&nbsp;
								<span><?php echo esc_html( $start_location ); ?></span>
							</li>
							<li>
								<span class="fas fa-map-marker-alt"></span>
								<h6><?php esc_html_e( 'Destination Location : ', 'mptbm_plugin' ); ?></h6>&nbsp;
								<span><?php echo esc_html( $end_location ); ?></span>
							</li>
							<li>
								<span class="fas fa-map-marker-alt"></span>
								<h6><?php esc_html_e( 'Distance : ', 'mptbm_plugin' ); ?></h6>&nbsp;
								<span><?php echo esc_html( $cart_item['mptbm_distance'] ); ?></span>
							</li>
							<li>
								<span class="far fa-clock"></span>
								<h6><?php esc_html_e( 'Approximate Time : ', 'mptbm_plugin' ); ?></h6>&nbsp;
								<span><?php echo esc_html( $cart_item['mptbm_duration'] ); ?></span>
							</li>
							<li>
								<span class="far fa-calendar-alt"></span>
								<h6><?php esc_html_e( 'Date : ', 'mptbm_plugin' ); ?></h6>&nbsp;
								<span><?php echo esc_html( date_i18n( $data_format, strtotime( $date ) ) ); ?></span>
							</li>
							<li>
								<span class="far fa-clock"></span>
								<h6><?php esc_html_e( 'Time : ', 'mptbm_plugin' ); ?></h6>&nbsp;
								<span><?php echo esc_html( MPTBM_Function::datetime_format( $date, 'time' ) ); ?></span>
							</li>
                            <li>
                                <span class="fa fa-tag"></span>
                                <h6><?php esc_html_e( 'Base Price : ', 'mptbm_plugin' ); ?></h6>&nbsp;
                                <span><?php echo $base_price;//$this->get_base_price($post_id); ?></span>
                            </li>
						</ul>
					</div>
					<?php if ( sizeof( $extra_service ) > 0 ) { ?>
						<h5 class="mb_xs"><?php esc_html_e( 'Extra Services', 'mptbm_plugin' ); ?></h5>
						<?php foreach ( $extra_service as $service ) { ?>
							<div class="dLayout_xs">
								<ul class="cart_list">
									<li>
										<h6><?php esc_html_e( 'Name : ', 'mptbm_plugin' ); ?></h6>&nbsp;
										<span><?php echo esc_html( $service['service_name'] ); ?></span>
									</li>
                                    <li>
                                        <h6><?php esc_html_e( 'Quantity : ', 'mptbm_plugin' ); ?></h6>&nbsp;
                                        <span><?php echo esc_html( $service['service_quantity'] ); ?></span>
                                    </li>
									<li>
										<h6><?php esc_html_e( 'Price : ', 'mptbm_plugin' ); ?></h6>&nbsp;
										<span><?php echo ' ( ' . MPTBM_Function::wc_price( $post_id, $service['service_price'] ) . ' x '.$service['service_quantity'].' ) = ' . MPTBM_Function::wc_price( $post_id, ( $service['service_price'] * $service['service_quantity'] ) ); ?></span>
									</li>
								</ul>
							</div>
						<?php } ?>
					<?php } ?>
                    <?php if ( sizeof( $booking_infos ) > 0 ) { ?>
                        <h5 class="mb_xs"><?php esc_html_e( 'Booking Info', 'mptbm_plugin' ); ?></h5>

                            <div class="dLayout_xs">
                                <ul class="cart_list">
                                    <li>
                                        <h6><?php esc_html_e( 'Full Name : ', 'mptbm_plugin' ); ?></h6>&nbsp;
                                        <span><?php echo esc_html( $booking_infos['booking_name']??'' ); ?></span>
                                    </li>
                                    <li>
                                        <h6><?php esc_html_e( 'Email : ', 'mptbm_plugin' ); ?></h6>&nbsp;
                                        <span><?php echo esc_html( $booking_infos['booking_email']??'' ); ?></span>
                                    </li>
                                    <li>
                                        <h6><?php esc_html_e( 'Phone : ', 'mptbm_plugin' ); ?></h6>&nbsp;
                                        <span><?php echo esc_html( $booking_infos['booking_phone']??'' ); ?></span>
                                    </li>
                                    <li>
                                        <h6><?php esc_html_e( 'Address : ', 'mptbm_plugin' ); ?></h6>&nbsp;
                                        <span><?php echo esc_html( $booking_infos['booking_address']??'' ); ?></span>
                                    </li>
                                    <li>
                                        <h6><?php esc_html_e( 'City : ', 'mptbm_plugin' ); ?></h6>&nbsp;
                                        <span><?php echo esc_html( $booking_infos['booking_city']??'' ); ?></span>
                                    </li>
                                    <li>
                                        <h6><?php esc_html_e( 'Zip : ', 'mptbm_plugin' ); ?></h6>&nbsp;
                                        <span><?php echo esc_html( $booking_infos['booking_zip']??'' ); ?></span>
                                    </li>
                                    <li>
                                        <h6><?php esc_html_e( 'Country : ', 'mptbm_plugin' ); ?></h6>&nbsp;
                                        <span><?php echo esc_html( $booking_infos['booking_country']??'' ); ?></span>
                                    </li>
                                    <li>
                                        <h6><?php esc_html_e( 'Passport : ', 'mptbm_plugin' ); ?></h6>&nbsp;
                                        <span><?php echo esc_html( $booking_infos['booking_passport']??'' ); ?></span>
                                    </li>
                                    <li>
                                        <h6><?php esc_html_e( 'NID : ', 'mptbm_plugin' ); ?></h6>&nbsp;
                                        <span><?php echo esc_html( $booking_infos['booking_nid']??'' ); ?></span>
                                    </li>
                                </ul>
                            </div>
                    <?php } ?>
					<?php do_action( 'mptbm_after_cart_item_display', $cart_item, $post_id ); ?>
				</div>
				<?php
			}
			public function wc_order_status_change( $order_status, $post_id, $order_id ) {
				$args = array(
					'post_type'      => 'mptbm_booking',
					'posts_per_page' => - 1,
					'meta_query'     => array(
						'relation' => 'AND',
						array(
							array(
								'key'     => 'mptbm_id',
								'value'   => $post_id,
								'compare' => '='
							),
							array(
								'key'     => 'mptbm_order_id',
								'value'   => $order_id,
								'compare' => '='
							)
						)
					)
				);
				$loop = new WP_Query( $args );
				foreach ( $loop->posts as $user ) {
					$user_id = $user->ID;
					update_post_meta( $user_id, 'mptbm_order_status', $order_status );
				}
				$args = array(
					'post_type'      => 'mptbm_service_booking',
					'posts_per_page' => - 1,
					'meta_query'     => array(
						'relation' => 'AND',
						array(
							array(
								'key'     => 'mptbm_id',
								'value'   => $post_id,
								'compare' => '='
							),
							array(
								'key'     => 'mptbm_order_id',
								'value'   => $order_id,
								'compare' => '='
							)
						)
					)
				);
				$loop = new WP_Query( $args );
				foreach ( $loop->posts as $user ) {
					$user_id = $user->ID;
					update_post_meta( $user_id, 'mptbm_order_status', $order_status );
				}
			}
			//**********************//
			public static function cart_extra_service_info( $post_id ): array {
				$start_date    = MPTBM_Function::get_submit_info( 'mptbm_date' );
				$service_name  = MPTBM_Function::get_submit_info( 'mptbm_extra_service', array() );
                $service_quantity  = MPTBM_Function::get_submit_info( 'mptbm_extra_service_quantity', array() );
				$extra_service = array();
				if ( sizeof( $service_name ) > 0 ) {
					for ( $i = 0; $i < count( $service_name ); $i ++ ) {
						if ( $service_name[ $i ] ) {
							$extra_service[ $i ]['service_name']  = $service_name[ $i ];
                            $extra_service[ $i ]['service_quantity'] = $service_quantity[ $i ];
							$extra_service[ $i ]['service_price'] = MPTBM_Function::get_extra_service_price_by_name( $post_id, $service_name[ $i ] );
							$extra_service[ $i ]['mptbm_date']    = $start_date ?? '';
						}
					}
				}

				return $extra_service;
			}

            public static function get_booking_info(): array
            {
                return array(
                    'booking_name' => MPTBM_Function::get_submit_info( 'mptbm_user_name' ),
                    'booking_email' => MPTBM_Function::get_submit_info( 'mptbm_user_email' ),
                    'booking_phone' => MPTBM_Function::get_submit_info( 'mptbm_user_phone' ),
                    'booking_street' => MPTBM_Function::get_submit_info( 'mptbm_user_address' ),
                    'booking_city' => MPTBM_Function::get_submit_info( 'mptbm_user_city' ),
                    'booking_zip' => MPTBM_Function::get_submit_info( 'mptbm_user_zip' ),
                    'booking_country' => MPTBM_Function::get_submit_info( 'mptbm_user_country' ),
                    'booking_passport' => MPTBM_Function::get_submit_info( 'mptbm_user_passport' ),
                    'booking_nid' => MPTBM_Function::get_submit_info( 'mptbm_user_nid' ),
                );

            }

            public function get_base_price($post_id)
            {
                $distance     = $_COOKIE['mptbm_distance'] ?? '';
                $duration     = $_COOKIE['mptbm_duration'] ?? '';
                $start_place=MPTBM_Function::get_submit_info( 'mptbm_start_place' );
                $end_place=MPTBM_Function::get_submit_info( 'mptbm_end_place' );
                $price        = MPTBM_Function::get_price( $post_id, $distance, $duration,$start_place,$end_place );
                $wc_price     = MPTBM_Function::wc_price( $post_id, $price );

                return MPTBM_Function::get_custom_woocommerce_price(array('price'=>$price,'decimals'=>2));
            }

			public function get_cart_total_price( $post_id ) {
				$distance     = $_COOKIE['mptbm_distance'] ?? '';
				$duration     = $_COOKIE['mptbm_duration'] ?? '';
				$start_place=MPTBM_Function::get_submit_info( 'mptbm_start_place' );
				$end_place=MPTBM_Function::get_submit_info( 'mptbm_end_place' );
				$price        = MPTBM_Function::get_price( $post_id, $distance, $duration,$start_place,$end_place );
				$wc_price     = MPTBM_Function::wc_price( $post_id, $price );
				$raw_price    = MPTBM_Function::price_convert_raw( $wc_price );
				$service_name = MPTBM_Function::get_submit_info( 'mptbm_extra_service', array() );
                $service_quantity = MPTBM_Function::get_submit_info( 'mptbm_extra_service_quantity', array() );

				if ( sizeof( $service_name ) > 0 ) {
					for ( $i = 0; $i < count( $service_name ); $i ++ ) {
						if ( $service_name[ $i ] ) {
                            if(array_key_exists($i, $service_quantity) && isset($service_quantity[$i]))
                            {
                                $raw_price = $raw_price + MPTBM_Function::get_extra_service_price_by_name( $post_id, $service_name[ $i ] ) * $service_quantity[$i];
                            }
                            else
                            {
                                $raw_price = $raw_price + MPTBM_Function::get_extra_service_price_by_name( $post_id, $service_name[ $i ] );
                            }

						}
					}
				}

				return $raw_price;
			}
			public static function add_cpt_data( $cpt_name, $title, $meta_data = array(), $status = 'publish', $cat = array() ) {
				$new_post = array(
					'post_title'    => $title,
					'post_content'  => '',
					'post_category' => $cat,
					'tags_input'    => array(),
					'post_status'   => $status,
					'post_type'     => $cpt_name
				);
				$post_id  = wp_insert_post( $new_post );
				if ( sizeof( $meta_data ) > 0 ) {
					foreach ( $meta_data as $key => $value ) {
						update_post_meta( $post_id, $key, $value );
					}
				}
				if ( $cpt_name == 'mptbm_booking' ) {
					$mptbm_pin = $meta_data['mptbm_user_id'] . $meta_data['mptbm_order_id'] . $meta_data['mptbm_id'] . $post_id;
					update_post_meta( $post_id, 'mptbm_pin', $mptbm_pin );
				}
			}
			public static function get_order_item_meta( $item_id, $key ): string {
				global $wpdb;
				$table_name = $wpdb->prefix . "woocommerce_order_itemmeta";
				$results    = $wpdb->get_results( $wpdb->prepare( "SELECT meta_value FROM $table_name WHERE order_item_id = %d AND meta_key = %s", $item_id, $key ) );
				foreach ( $results as $result ) {
					$value = $result->meta_value;
				}
				return $value ?? '';
			}


		}
		new MPTBM_Woocommerce();
	}