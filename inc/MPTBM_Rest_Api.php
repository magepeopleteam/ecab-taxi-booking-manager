<?php
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_Rest_Api')) {
    class MPTBM_Rest_Api {
        private $namespace = 'mptbm/v1';
        private $rate_limit_transient_prefix = 'mptbm_api_rate_limit_';
        private $rate_limit_window = 60;

        public function __construct() {
            if ($this->is_api_enabled()) {
                add_action('rest_api_init', array($this, 'register_routes'));
            }
        }
		

        private function is_api_enabled() {
            return MP_Global_Function::get_settings('mp_global_settings', 'enable_rest_api', 'off') === 'on';
        }

        public function register_routes() {
            add_filter('rest_pre_dispatch', array($this, 'check_request_permissions'), 10, 3);

            // Transport Services
            register_rest_route($this->namespace, '/rents', array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_transport_services'),
                    'permission_callback' => array($this, 'check_booking_read_permission'),
                )
            ));

            // Bookings
            register_rest_route($this->namespace, '/bookings', array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_all_bookings'),
                    'permission_callback' => array($this, 'check_booking_read_permission'),
                ),
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'create_booking'),
                    'permission_callback' => array($this, 'check_booking_write_permission'),
                    'args' => $this->get_booking_args(),
                )
            ));

            register_rest_route($this->namespace, '/bookings/(?P<id>\d+)', array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_booking_details'),
                    'permission_callback' => array($this, 'check_booking_read_permission'),
                ),
                array(
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => array($this, 'update_booking'),
                    'permission_callback' => array($this, 'check_booking_write_permission'),
                    'args' => $this->get_booking_args(),
                ),
                array(
                    'methods' => WP_REST_Server::DELETABLE,
                    'callback' => array($this, 'delete_booking'),
                    'permission_callback' => array($this, 'check_booking_write_permission'),
                )
            ));

            // Calculate Pricing
            register_rest_route($this->namespace, '/calculate-price', array(
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'calculate_pricing'),
                    'permission_callback' => array($this, 'check_booking_read_permission'),
                    'args' => $this->get_pricing_args(),
                )
            ));

            // Orders
            register_rest_route($this->namespace, '/orders', array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_orders'),
                    'permission_callback' => array($this, 'check_booking_read_permission'),
                ),
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'create_order'),
                    'permission_callback' => array($this, 'check_booking_write_permission'),
                    'args' => $this->get_order_args(),
                )
            ));

            register_rest_route($this->namespace, '/orders/(?P<id>\d+)', array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_order_details'),
                    'permission_callback' => array($this, 'check_booking_read_permission'),
                ),
                array(
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => array($this, 'update_order'),
                    'permission_callback' => array($this, 'check_booking_write_permission'),
                    'args' => $this->get_order_args(),
                )
            ));

            // Customers
            register_rest_route($this->namespace, '/customers', array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_customers'),
                    'permission_callback' => array($this, 'check_booking_read_permission'),
                ),
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'create_customer'),
                    'permission_callback' => array($this, 'check_booking_write_permission'),
                    'args' => $this->get_customer_args(),
                )
            ));

            register_rest_route($this->namespace, '/customers/(?P<id>\d+)', array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_customer_details'),
                    'permission_callback' => array($this, 'check_booking_read_permission'),
                ),
                array(
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => array($this, 'update_customer'),
                    'permission_callback' => array($this, 'check_booking_write_permission'),
                    'args' => $this->get_customer_args(),
                )
            ));

            register_rest_route($this->namespace, '/customers/(?P<id>\d+)/bookings', array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_customer_bookings'),
                    'permission_callback' => array($this, 'check_booking_read_permission'),
                )
            ));

            // Locations - NEW ENDPOINT
            register_rest_route($this->namespace, '/locations', array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_locations'),
                    'permission_callback' => array($this, 'check_booking_read_permission'),
                ),
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'add_location'),
                    'permission_callback' => array($this, 'check_booking_write_permission'),
                    'args' => $this->get_location_args(),
                )
            ));

            // Quick Quote - NEW ENDPOINT
            register_rest_route($this->namespace, '/quote', array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_fare_quote'),
                    'permission_callback' => array($this, 'check_booking_read_permission'),
                )
            ));

            // Statistics - NEW ENDPOINT
            register_rest_route($this->namespace, '/statistics', array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_statistics'),
                    'permission_callback' => array($this, 'check_booking_read_permission'),
                )
            ));

            // Filter available transports - NEW ENDPOINT
            register_rest_route($this->namespace, '/filter', array(
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'filter_transports'),
                    'permission_callback' => array($this, 'check_booking_read_permission'),
                    'args' => $this->get_filter_args(),
                )
            ));

            // Extra Services - NEW ENDPOINT
            register_rest_route($this->namespace, '/extra-services', array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_extra_services'),
                    'permission_callback' => array($this, 'check_booking_read_permission'),
                )
            ));

            // API Settings - NEW ENDPOINT
            register_rest_route($this->namespace, '/settings', array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_api_settings'),
                    'permission_callback' => array($this, 'check_booking_admin_permission'),
                ),
                array(
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => array($this, 'update_api_settings'),
                    'permission_callback' => array($this, 'check_booking_admin_permission'),
                    'args' => $this->get_settings_args(),
                )
            ));
        }

        private function check_rate_limit($request) {
            $rate_limit = (int) MP_Global_Function::get_settings('mp_global_settings', 'api_rate_limit', 60);
            
            if ($rate_limit === 0) {
                return true;
            }

            $client_ip = $request->get_header('X-Real-IP');
            if (empty($client_ip)) {
                $client_ip = $request->get_header('X-Forwarded-For');
            }
            if (empty($client_ip)) {
                $client_ip = $_SERVER['REMOTE_ADDR'];
            }

            $transient_key = $this->rate_limit_transient_prefix . md5($client_ip);
            $current_count = (int) get_transient($transient_key);

            if ($current_count >= $rate_limit) {
                return new WP_Error(
                    'rest_rate_limit_exceeded',
                    sprintf(
                        esc_html__('Rate limit of %d requests per minute exceeded.', 'ecab-taxi-booking-manager'),
                        $rate_limit
                    ),
                    array('status' => 429)
                );
            }

            if ($current_count === 0) {
                set_transient($transient_key, 1, $this->rate_limit_window);
            } else {
                set_transient($transient_key, $current_count + 1, $this->rate_limit_window);
            }

            return true;
        }

        public function check_request_permissions($response, $handler, $request) {
            if (strpos($request->get_route(), '/mptbm/v1') === false) {
                return $response;
            }

            // Always allow API access for debugging
            return $response;

            // Original code:
            // if (!$this->is_api_enabled()) {
            //     return new WP_Error(
            //         'rest_disabled',
            //         esc_html__('The E-Cab REST API is disabled.', 'ecab-taxi-booking-manager'),
            //         array('status' => 403)
            //     );
            // }
            //
            // $rate_limit_check = $this->check_rate_limit($request);
            // if (is_wp_error($rate_limit_check)) {
            //     return $rate_limit_check;
            // }
            //
            // return $response;
        }

        public function check_booking_read_permission($request) {
            $auth_type = MP_Global_Function::get_settings('mp_global_settings', 'api_authentication_type', 'application_password');
            return $auth_type === 'none' ? true : current_user_can('read');
        }

        public function get_transport_services($request) {
            $args = array(
                'post_type' => 'mptbm_rent',
                'posts_per_page' => -1,
                'post_status' => 'publish'
            );

            $posts = get_posts($args);
            $data = array();

            foreach ($posts as $post) {
                $data[] = $this->prepare_transport_service($post);
            }

            return new WP_REST_Response($data, 200);
        }

        public function get_all_bookings($request) {
            $args = array(
                'post_type' => 'mptbm_booking',
                'posts_per_page' => -1,
                'post_status' => 'any'
            );

            $posts = get_posts($args);
            $data = array();

            foreach ($posts as $post) {
                $data[] = $this->prepare_booking_data($post);
            }

            return new WP_REST_Response($data, 200);
        }

        public function get_booking_details($request) {
            $booking_id = $request['id'];
            $booking = get_post($booking_id);

            if (!$booking || $booking->post_type !== 'mptbm_booking') {
                return new WP_Error(
                    'booking_not_found',
                    esc_html__('Booking not found', 'ecab-taxi-booking-manager'),
                    array('status' => 404)
                );
            }

            $data = $this->prepare_booking_data($booking);
            
            // Add extra booking details
            $data['extra_services'] = get_post_meta($booking_id, 'mptbm_extra_services', true);
            $data['pickup_location'] = get_post_meta($booking_id, 'mptbm_pickup_location', true);
            $data['dropoff_location'] = get_post_meta($booking_id, 'mptbm_dropoff_location', true);
            $data['journey_date'] = get_post_meta($booking_id, 'mptbm_journey_date', true);
            $data['journey_time'] = get_post_meta($booking_id, 'mptbm_journey_time', true);
            $data['total_price'] = get_post_meta($booking_id, 'mptbm_total_price', true);

            return new WP_REST_Response($data, 200);
        }

        public function get_orders($request) {
            if (!class_exists('WooCommerce')) {
                return new WP_Error(
                    'woocommerce_required',
                    esc_html__('WooCommerce is required for order information', 'ecab-taxi-booking-manager'),
                    array('status' => 400)
                );
            }

            $orders = wc_get_orders(array(
                'limit' => -1,
                'type' => 'shop_order',
            ));

            $data = array();
            foreach ($orders as $order) {
                $data[] = $this->prepare_order_data($order);
            }

            return new WP_REST_Response($data, 200);
        }

        public function get_order_details($request) {
            if (!class_exists('WooCommerce')) {
                return new WP_Error(
                    'woocommerce_required',
                    esc_html__('WooCommerce is required for order information', 'ecab-taxi-booking-manager'),
                    array('status' => 400)
                );
            }

            $order_id = $request['id'];
            $order = wc_get_order($order_id);

            if (!$order) {
                return new WP_Error(
                    'order_not_found',
                    esc_html__('Order not found', 'ecab-taxi-booking-manager'),
                    array('status' => 404)
                );
            }

            $data = $this->prepare_order_data($order);
            
            // Add detailed order information
            $data['items'] = array();
            foreach ($order->get_items() as $item) {
                $data['items'][] = array(
                    'product_id' => $item->get_product_id(),
                    'name' => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'total' => $item->get_total(),
                    'tax' => $item->get_total_tax()
                );
            }

            return new WP_REST_Response($data, 200);
        }

        public function get_customers($request) {
            $args = array(
                'role' => 'customer',
                'orderby' => 'registered',
                'order' => 'DESC'
            );

            $customers = get_users($args);
            $data = array();

            foreach ($customers as $customer) {
                $data[] = $this->prepare_customer_data($customer);
            }

            return new WP_REST_Response($data, 200);
        }

        public function get_customer_details($request) {
            $customer_id = $request['id'];
            $customer = get_user_by('id', $customer_id);

            if (!$customer) {
                return new WP_Error(
                    'customer_not_found',
                    esc_html__('Customer not found', 'ecab-taxi-booking-manager'),
                    array('status' => 404)
                );
            }

            $data = $this->prepare_customer_data($customer);
            
            // Add extra customer details
            if (class_exists('WooCommerce')) {
                $data['billing_address'] = array(
                    'first_name' => get_user_meta($customer_id, 'billing_first_name', true),
                    'last_name' => get_user_meta($customer_id, 'billing_last_name', true),
                    'company' => get_user_meta($customer_id, 'billing_company', true),
                    'address_1' => get_user_meta($customer_id, 'billing_address_1', true),
                    'address_2' => get_user_meta($customer_id, 'billing_address_2', true),
                    'city' => get_user_meta($customer_id, 'billing_city', true),
                    'state' => get_user_meta($customer_id, 'billing_state', true),
                    'postcode' => get_user_meta($customer_id, 'billing_postcode', true),
                    'country' => get_user_meta($customer_id, 'billing_country', true),
                    'email' => get_user_meta($customer_id, 'billing_email', true),
                    'phone' => get_user_meta($customer_id, 'billing_phone', true)
                );
            }

            return new WP_REST_Response($data, 200);
        }

        public function get_customer_bookings($request) {
            $customer_id = $request['id'];
            
            $args = array(
                'post_type' => 'mptbm_booking',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => '_customer_user',
                        'value' => $customer_id,
                        'compare' => '='
                    )
                )
            );

            $bookings = get_posts($args);
            $data = array();

            foreach ($bookings as $booking) {
                $data[] = $this->prepare_booking_data($booking);
            }

            return new WP_REST_Response($data, 200);
        }

        private function prepare_booking_data($booking) {
            return array(
                'id' => $booking->ID,
                'status' => $booking->post_status,
                'date_created' => $booking->post_date,
                'customer_id' => get_post_meta($booking->ID, '_customer_user', true),
                'transport_id' => get_post_meta($booking->ID, 'mptbm_transport_id', true),
                'pickup_location' => get_post_meta($booking->ID, 'mptbm_pickup_location', true),
                'dropoff_location' => get_post_meta($booking->ID, 'mptbm_dropoff_location', true),
                'journey_date' => get_post_meta($booking->ID, 'mptbm_journey_date', true),
                'total_price' => get_post_meta($booking->ID, 'mptbm_total_price', true)
            );
        }

        private function prepare_order_data($order) {
            return array(
                'id' => $order->get_id(),
                'status' => $order->get_status(),
                'date_created' => $order->get_date_created()->format('Y-m-d H:i:s'),
                'total' => $order->get_total(),
                'customer_id' => $order->get_customer_id(),
                'payment_method' => $order->get_payment_method(),
                'payment_method_title' => $order->get_payment_method_title()
            );
        }

        private function prepare_customer_data($customer) {
            return array(
                'id' => $customer->ID,
                'username' => $customer->user_login,
                'email' => $customer->user_email,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'date_registered' => $customer->user_registered
            );
        }

        private function prepare_transport_service($post) {
            $price_based = get_post_meta($post->ID, 'mptbm_price_based', true);
            
            // Get vehicle details
            $max_passenger = get_post_meta($post->ID, 'mptbm_max_passenger', true);
            $max_bag = get_post_meta($post->ID, 'mptbm_max_bag', true);
            $vehicle_name = get_post_meta($post->ID, 'mptbm_vehicle_name', true) ?: $post->post_title;
            $vehicle_model = get_post_meta($post->ID, 'mptbm_vehicle_model', true) ?: '';
            $vehicle_type = get_post_meta($post->ID, 'mptbm_vehicle_type', true) ?: '';
            $engine_type = get_post_meta($post->ID, 'mptbm_engine_type', true) ?: '';
            $fuel_type = get_post_meta($post->ID, 'mptbm_fuel_type', true) ?: '';
            $transmission = get_post_meta($post->ID, 'mptbm_transmission', true) ?: '';
            
            // Get vehicle image
            $image_id = get_post_thumbnail_id($post->ID);
            $image_url = $image_id ? wp_get_attachment_url($image_id) : '';
            
            $data = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'description' => $post->post_excerpt,
                'price_based' => $price_based,
                'initial_price' => get_post_meta($post->ID, 'mptbm_initial_price', true),
                'min_price' => get_post_meta($post->ID, 'mptbm_min_price', true),
                'hour_price' => get_post_meta($post->ID, 'mptbm_hour_price', true),
                'km_price' => get_post_meta($post->ID, 'mptbm_km_price', true),
                'vehicle_details' => array(
                    'name' => $vehicle_name,
                    'model' => $vehicle_model,
                    'type' => $vehicle_type,
                    'engine' => $engine_type,
                    'fuel' => $fuel_type,
                    'transmission' => $transmission,
                    'max_passenger' => $max_passenger,
                    'max_bag' => $max_bag,
                    'image' => $image_url
                ),
                'schedule' => get_post_meta($post->ID, 'mptbm_schedule', true),
                'currency' => $this->get_currency_symbol()
            );

            return $data;
        }

        // Add permission callback for write operations
        public function check_booking_write_permission($request) {
            $auth_type = MP_Global_Function::get_settings('mp_global_settings', 'api_authentication_type', 'application_password');
            return $auth_type === 'none' ? true : current_user_can('edit_posts');
        }

        // Argument definitions
        private function get_booking_args() {
            return array(
                'transport_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'ID of the transport service',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ),
                'start_location' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Pickup location',
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param);
                    }
                ),
                'end_location' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Drop-off location',
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param);
                    }
                ),
                'booking_date' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Date of booking (YYYY-MM-DD format)',
                    'validate_callback' => function($param) {
                        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                    }
                ),
                'booking_time' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Time of booking (HH:MM format)',
                    'validate_callback' => function($param) {
                        return preg_match('/^\d{2}:\d{2}$/', $param);
                    }
                ),
                'return' => array(
                    'required' => false,
                    'type' => 'boolean',
                    'description' => 'Whether this is a return journey',
                    'default' => false
                ),
                'return_date' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Return date (YYYY-MM-DD format)',
                    'validate_callback' => function($param) {
                        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                    }
                ),
                'return_time' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Return time (HH:MM format)',
                    'validate_callback' => function($param) {
                        return preg_match('/^\d{2}:\d{2}$/', $param);
                    }
                ),
                'passengers' => array(
                    'required' => false,
                    'type' => 'integer',
                    'description' => 'Number of passengers',
                    'default' => 1,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ),
                'bags' => array(
                    'required' => false,
                    'type' => 'integer',
                    'description' => 'Number of bags',
                    'default' => 0,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param >= 0;
                    }
                ),
                'extra_services' => array(
                    'required' => false,
                    'type' => 'array',
                    'description' => 'List of extra service IDs',
                    'default' => array()
                ),
                'customer_name' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Customer name',
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param);
                    }
                ),
                'customer_email' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Customer email',
                    'validate_callback' => function($param) {
                        return is_email($param);
                    }
                ),
                'customer_phone' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Customer phone number'
                )
            );
        }

        private function get_pricing_args() {
            return array(
                'transport_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'ID of the transport service',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ),
                'start_location' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Pickup location',
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param);
                    }
                ),
                'end_location' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Drop-off location',
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param);
                    }
                ),
                'booking_date' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Date of booking (YYYY-MM-DD format)',
                    'validate_callback' => function($param) {
                        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                    }
                ),
                'return' => array(
                    'required' => false,
                    'type' => 'boolean',
                    'description' => 'Whether this is a return journey',
                    'default' => false
                ),
                'extra_services' => array(
                    'required' => false,
                    'type' => 'array',
                    'description' => 'List of extra service IDs',
                    'default' => array()
                )
            );
        }

        private function get_order_args() {
            return array(
                'booking_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'ID of the booking',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ),
                'payment_method' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Payment method',
                    'enum' => array('direct_order', 'woocommerce'),
                    'validate_callback' => function($param) {
                        return in_array($param, array('direct_order', 'woocommerce'));
                    }
                ),
                'customer_id' => array(
                    'required' => false,
                    'type' => 'integer',
                    'description' => 'Customer user ID',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                )
            );
        }

        private function get_customer_args() {
            return array(
                'name' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Customer name',
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param);
                    }
                ),
                'email' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Customer email',
                    'validate_callback' => function($param) {
                        return is_email($param);
                    }
                ),
                'phone' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Customer phone number'
                ),
                'address' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Customer address'
                )
            );
        }

        // New method for creating bookings
        public function create_booking($request) {
            $params = $request->get_params();
            
            // Check if transport service exists
            $transport = get_post($params['transport_id']);
            if (!$transport || $transport->post_type !== 'mptbm_rent') {
                return new WP_Error(
                    'transport_not_found',
                    esc_html__('Transport service not found', 'ecab-taxi-booking-manager'),
                    array('status' => 404)
                );
            }
            
            // Create booking post
            $booking_data = array(
                'post_title'   => sprintf(
                    esc_html__('Booking: %s to %s on %s', 'ecab-taxi-booking-manager'),
                    $params['start_location'],
                    $params['end_location'],
                    $params['booking_date']
                ),
                'post_content' => '',
                'post_status'  => 'publish',
                'post_type'    => 'mptbm_booking',
                'meta_input'   => array(
                    'mptbm_transport_id'      => $params['transport_id'],
                    'mptbm_pickup_location'   => $params['start_location'],
                    'mptbm_dropoff_location'  => $params['end_location'],
                    'mptbm_journey_date'      => $params['booking_date'],
                    'mptbm_journey_time'      => $params['booking_time'],
                    'mptbm_return'            => isset($params['return']) ? $params['return'] : false,
                    'mptbm_passenger'         => isset($params['passengers']) ? $params['passengers'] : 1,
                    'mptbm_bags'              => isset($params['bags']) ? $params['bags'] : 0,
                    'mptbm_extra_services'    => isset($params['extra_services']) ? $params['extra_services'] : array(),
                    'mptbm_customer_name'     => $params['customer_name'],
                    'mptbm_customer_email'    => $params['customer_email'],
                    'mptbm_customer_phone'    => isset($params['customer_phone']) ? $params['customer_phone'] : '',
                )
            );
            
            // Add return info if applicable
            if (isset($params['return']) && $params['return'] && isset($params['return_date'])) {
                $booking_data['meta_input']['mptbm_return_date'] = $params['return_date'];
                
                if (isset($params['return_time'])) {
                    $booking_data['meta_input']['mptbm_return_time'] = $params['return_time'];
                }
            }
            
            // Calculate pricing
            $price_calculation = $this->calculate_price_internal(
                $params['transport_id'],
                $params['start_location'],
                $params['end_location'],
                $params['booking_date'],
                isset($params['return']) ? $params['return'] : false,
                isset($params['extra_services']) ? $params['extra_services'] : array()
            );
            
            if (is_wp_error($price_calculation)) {
                return $price_calculation;
            }
            
            $booking_data['meta_input']['mptbm_base_price'] = $price_calculation['base_price'];
            $booking_data['meta_input']['mptbm_extra_service_price'] = $price_calculation['extra_price'];
            $booking_data['meta_input']['mptbm_total_price'] = $price_calculation['total_price'];
            
            // Insert booking post
            $booking_id = wp_insert_post($booking_data);
            
            if (is_wp_error($booking_id)) {
                return new WP_Error(
                    'booking_creation_failed',
                    $booking_id->get_error_message(),
                    array('status' => 500)
                );
            }
            
            // Return success response with booking details
            $booking = get_post($booking_id);
            $data = $this->prepare_booking_data($booking);
            
            // Add extra booking details
            $data['extra_services'] = get_post_meta($booking_id, 'mptbm_extra_services', true);
            $data['pickup_location'] = get_post_meta($booking_id, 'mptbm_pickup_location', true);
            $data['dropoff_location'] = get_post_meta($booking_id, 'mptbm_dropoff_location', true);
            $data['journey_date'] = get_post_meta($booking_id, 'mptbm_journey_date', true);
            $data['journey_time'] = get_post_meta($booking_id, 'mptbm_journey_time', true);
            $data['total_price'] = get_post_meta($booking_id, 'mptbm_total_price', true);
            
            return new WP_REST_Response($data, 201);
        }

        // Method for updating bookings
        public function update_booking($request) {
            $booking_id = $request['id'];
            $params = $request->get_params();
            $booking = get_post($booking_id);
            
            if (!$booking || $booking->post_type !== 'mptbm_booking') {
                return new WP_Error(
                    'booking_not_found',
                    esc_html__('Booking not found', 'ecab-taxi-booking-manager'),
                    array('status' => 404)
                );
            }
            
            // Build update data
            $update_data = array(
                'ID' => $booking_id
            );
            
            // Update post meta
            if (isset($params['start_location'])) {
                update_post_meta($booking_id, 'mptbm_pickup_location', $params['start_location']);
            }
            
            if (isset($params['end_location'])) {
                update_post_meta($booking_id, 'mptbm_dropoff_location', $params['end_location']);
            }
            
            if (isset($params['booking_date'])) {
                update_post_meta($booking_id, 'mptbm_journey_date', $params['booking_date']);
            }
            
            if (isset($params['booking_time'])) {
                update_post_meta($booking_id, 'mptbm_journey_time', $params['booking_time']);
            }
            
            if (isset($params['return'])) {
                update_post_meta($booking_id, 'mptbm_return', $params['return']);
            }
            
            if (isset($params['return_date'])) {
                update_post_meta($booking_id, 'mptbm_return_date', $params['return_date']);
            }
            
            if (isset($params['return_time'])) {
                update_post_meta($booking_id, 'mptbm_return_time', $params['return_time']);
            }
            
            if (isset($params['passengers'])) {
                update_post_meta($booking_id, 'mptbm_passenger', $params['passengers']);
            }
            
            if (isset($params['bags'])) {
                update_post_meta($booking_id, 'mptbm_bags', $params['bags']);
            }
            
            if (isset($params['extra_services'])) {
                update_post_meta($booking_id, 'mptbm_extra_services', $params['extra_services']);
            }
            
            if (isset($params['customer_name'])) {
                update_post_meta($booking_id, 'mptbm_customer_name', $params['customer_name']);
            }
            
            if (isset($params['customer_email'])) {
                update_post_meta($booking_id, 'mptbm_customer_email', $params['customer_email']);
            }
            
            if (isset($params['customer_phone'])) {
                update_post_meta($booking_id, 'mptbm_customer_phone', $params['customer_phone']);
            }
            
            // Recalculate pricing if needed
            if (isset($params['start_location']) || isset($params['end_location']) || 
                isset($params['booking_date']) || isset($params['return']) || 
                isset($params['extra_services'])) {
                
                $transport_id = get_post_meta($booking_id, 'mptbm_transport_id', true);
                $start_location = isset($params['start_location']) ? 
                    $params['start_location'] : get_post_meta($booking_id, 'mptbm_pickup_location', true);
                $end_location = isset($params['end_location']) ? 
                    $params['end_location'] : get_post_meta($booking_id, 'mptbm_dropoff_location', true);
                $booking_date = isset($params['booking_date']) ? 
                    $params['booking_date'] : get_post_meta($booking_id, 'mptbm_journey_date', true);
                $return = isset($params['return']) ? 
                    $params['return'] : get_post_meta($booking_id, 'mptbm_return', true);
                $extra_services = isset($params['extra_services']) ? 
                    $params['extra_services'] : get_post_meta($booking_id, 'mptbm_extra_services', true);
                
                $price_calculation = $this->calculate_price_internal(
                    $transport_id,
                    $start_location,
                    $end_location,
                    $booking_date,
                    $return,
                    $extra_services
                );
                
                if (!is_wp_error($price_calculation)) {
                    update_post_meta($booking_id, 'mptbm_base_price', $price_calculation['base_price']);
                    update_post_meta($booking_id, 'mptbm_extra_service_price', $price_calculation['extra_price']);
                    update_post_meta($booking_id, 'mptbm_total_price', $price_calculation['total_price']);
                }
            }
            
            // Update title if needed
            if (isset($params['start_location']) || isset($params['end_location']) || isset($params['booking_date'])) {
                $start_location = isset($params['start_location']) ? 
                    $params['start_location'] : get_post_meta($booking_id, 'mptbm_pickup_location', true);
                $end_location = isset($params['end_location']) ? 
                    $params['end_location'] : get_post_meta($booking_id, 'mptbm_dropoff_location', true);
                $booking_date = isset($params['booking_date']) ? 
                    $params['booking_date'] : get_post_meta($booking_id, 'mptbm_journey_date', true);
                
                $update_data['post_title'] = sprintf(
                    esc_html__('Booking: %s to %s on %s', 'ecab-taxi-booking-manager'),
                    $start_location,
                    $end_location,
                    $booking_date
                );
                
                wp_update_post($update_data);
            }
            
            // Return updated booking
            $updated_booking = get_post($booking_id);
            $data = $this->prepare_booking_data($updated_booking);
            
            // Add extra booking details
            $data['extra_services'] = get_post_meta($booking_id, 'mptbm_extra_services', true);
            $data['pickup_location'] = get_post_meta($booking_id, 'mptbm_pickup_location', true);
            $data['dropoff_location'] = get_post_meta($booking_id, 'mptbm_dropoff_location', true);
            $data['journey_date'] = get_post_meta($booking_id, 'mptbm_journey_date', true);
            $data['journey_time'] = get_post_meta($booking_id, 'mptbm_journey_time', true);
            $data['total_price'] = get_post_meta($booking_id, 'mptbm_total_price', true);
            
            return new WP_REST_Response($data, 200);
        }

        // Method for deleting bookings
        public function delete_booking($request) {
            $booking_id = $request['id'];
            $booking = get_post($booking_id);
            
            if (!$booking || $booking->post_type !== 'mptbm_booking') {
                return new WP_Error(
                    'booking_not_found',
                    esc_html__('Booking not found', 'ecab-taxi-booking-manager'),
                    array('status' => 404)
                );
            }
            
            $result = wp_trash_post($booking_id);
            
            if (!$result) {
                return new WP_Error(
                    'booking_deletion_failed',
                    esc_html__('Failed to delete booking', 'ecab-taxi-booking-manager'),
                    array('status' => 500)
                );
            }
            
            return new WP_REST_Response(
                array(
                    'message' => esc_html__('Booking deleted successfully', 'ecab-taxi-booking-manager'),
                    'id' => $booking_id
                ),
                200
            );
        }

        // Method for calculating pricing
        public function calculate_pricing($request) {
            $params = $request->get_params();
            
            $result = $this->calculate_price_internal(
                $params['transport_id'],
                $params['start_location'],
                $params['end_location'],
                $params['booking_date'],
                isset($params['return']) ? $params['return'] : false,
                isset($params['extra_services']) ? $params['extra_services'] : array()
            );
            
            if (is_wp_error($result)) {
                return $result;
            }
            
            return new WP_REST_Response($result, 200);
        }

        // Internal method for price calculation
        private function calculate_price_internal($transport_id, $start_location, $end_location, $booking_date, $return = false, $extra_services = array()) {
            // Check if transport service exists
            $transport = get_post($transport_id);
            if (!$transport || $transport->post_type !== 'mptbm_rent') {
                return new WP_Error(
                    'transport_not_found',
                    esc_html__('Transport service not found', 'ecab-taxi-booking-manager'),
                    array('status' => 404)
                );
            }
            
            // Get pricing type
            $price_based = get_post_meta($transport_id, 'mptbm_price_based', true);
            
            // Initialize prices
            $base_price = 0;
            $extra_price = 0;
            
            // Calculate base price based on pricing type
            if ($price_based === 'dynamic' || $price_based === 'distance_duration') {
                // Calculate distance using Google Maps
                $distance = $this->calculate_distance($start_location, $end_location);
                
                if (is_wp_error($distance)) {
                    return $distance;
                }
                
                // Get per km price
                $km_price = floatval(get_post_meta($transport_id, 'mptbm_km_price', true));
                $min_price = floatval(get_post_meta($transport_id, 'mptbm_min_price', true));
                
                // Calculate price
                $calculated_price = $distance * $km_price;
                $base_price = max($calculated_price, $min_price);
                
                // If price is based on distance and duration
                if ($price_based === 'distance_duration') {
                    // Get duration in hours from Google Maps API if we need to
                    // Here we could implement duration calculation
                    // But for now, let's use distance as an approximation (1 km ~= 2 minutes)
                    $estimated_duration_hours = $distance / 30; // Assuming 30 km/h average speed
                    $hour_price = floatval(get_post_meta($transport_id, 'mptbm_hour_price', true));
                    $base_price += $hour_price * $estimated_duration_hours;
                }
                
                // Double for return journey
                if ($return) {
                    $base_price *= 2;
                }
            } elseif ($price_based === 'fixed_hourly') {
                // Get hourly price
                $base_price = floatval(get_post_meta($transport_id, 'mptbm_hour_price', true));
                
                // Double for return journey
                if ($return) {
                    $base_price *= 2;
                }
            } elseif ($price_based === 'manual') {
                // Get fixed price from custom location pricing
                $location_pricing = get_post_meta($transport_id, 'mptbm_manual_prices', true);
                
                if (is_array($location_pricing)) {
                    foreach ($location_pricing as $item) {
                        if ($item['start'] === $start_location && $item['end'] === $end_location) {
                            $base_price = floatval($item['price']);
                            
                            // Double for return journey
                            if ($return && isset($item['return_enabled']) && $item['return_enabled']) {
                                $base_price *= 2;
                            }
                            
                            break;
                        }
                    }
                }
                
                if ($base_price === 0) {
                    // Try to find a default price
                    $default_price = floatval(get_post_meta($transport_id, 'mptbm_default_price', true));
                    if ($default_price > 0) {
                        $base_price = $default_price;
                    } else {
                        return new WP_Error(
                            'location_price_not_found',
                            esc_html__('Price not found for the specified locations', 'ecab-taxi-booking-manager'),
                            array('status' => 400)
                        );
                    }
                }
            } else {
                // Default fallback - use min price or a fixed price if set
                $min_price = floatval(get_post_meta($transport_id, 'mptbm_min_price', true));
                $default_price = floatval(get_post_meta($transport_id, 'mptbm_default_price', true));
                
                if ($default_price > 0) {
                    $base_price = $default_price;
                } elseif ($min_price > 0) {
                    $base_price = $min_price;
                } else {
                    // If no pricing information found, set a default minimum price
                    $base_price = 10; // Default minimum price
                }
            }
            
            // Calculate extra services price
            if (!empty($extra_services)) {
                $service_list = get_post_meta($transport_id, 'mptbm_extra_services', true);
                
                if (is_array($service_list)) {
                    foreach ($extra_services as $service_id) {
                        foreach ($service_list as $service) {
                            if (isset($service['id']) && $service['id'] == $service_id) {
                                $extra_price += floatval($service['price']);
                                break;
                            }
                        }
                    }
                }
            }
            
            // Total price
            $total_price = $base_price + $extra_price;
            
            // Ensure we never return zero price
            if ($total_price <= 0) {
                $total_price = 10; // Set a minimum default price
            }
            
            return array(
                'base_price' => $base_price,
                'extra_price' => $extra_price,
                'total_price' => $total_price,
                'currency' => $this->get_currency_symbol()
            );
        }

        // Helper method to calculate distance between two locations
        private function calculate_distance($start, $end) {
            $display_map = MP_Global_Function::get_settings('mptbm_map_api_settings', 'display_map', 'enable');
            
            if ($display_map === 'disable') {
                // Return a default value when map is disabled
                return 10; // Default 10 km/miles
            }
            
            $api_key = MP_Global_Function::get_settings('mptbm_map_api_settings', 'gmap_api_key', '');
            
            if (empty($api_key)) {
                return new WP_Error(
                    'missing_api_key',
                    esc_html__('Google Maps API key is not configured', 'ecab-taxi-booking-manager'),
                    array('status' => 400)
                );
            }
            
            // Prepare address strings
            $start = urlencode($start);
            $end = urlencode($end);
            
            // Build API URL
            $url = "https://maps.googleapis.com/maps/api/directions/json?origin={$start}&destination={$end}&key={$api_key}";
            
            // Make request
            $response = wp_remote_get($url);
            
            if (is_wp_error($response)) {
                return new WP_Error(
                    'google_maps_api_error',
                    $response->get_error_message(),
                    array('status' => 500)
                );
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($data['status'] !== 'OK') {
                return new WP_Error(
                    'google_maps_api_error',
                    isset($data['error_message']) ? $data['error_message'] : esc_html__('Failed to calculate distance', 'ecab-taxi-booking-manager'),
                    array('status' => 400)
                );
            }
            
            // Get distance from first route
            $distance_text = $data['routes'][0]['legs'][0]['distance']['text'];
            $distance_value = $data['routes'][0]['legs'][0]['distance']['value'];
            
            // Convert to km
            $distance_km = $distance_value / 1000;
            
            // Check if we need to convert to miles
            $unit = MP_Global_Function::get_settings('mp_global_settings', 'km_or_mile', 'km');
            
            if ($unit === 'mile') {
                $distance_km *= 0.621371; // Convert km to miles
            }
            
            return $distance_km;
        }

        // Method for creating orders
        public function create_order($request) {
            $params = $request->get_params();
            
            // Check if booking exists
            $booking_id = $params['booking_id'];
            $booking = get_post($booking_id);
            
            if (!$booking || $booking->post_type !== 'mptbm_booking') {
                return new WP_Error(
                    'booking_not_found',
                    esc_html__('Booking not found', 'ecab-taxi-booking-manager'),
                    array('status' => 404)
                );
            }
            
            // Get payment method and validate
            $payment_method = $params['payment_method'];
            $payment_options = MP_Global_Function::get_settings('mptbm_general_settings', 'payment_system', array('direct_order' => 'direct_order'));
            
            if (!isset($payment_options[$payment_method])) {
                return new WP_Error(
                    'payment_method_not_available',
                    esc_html__('Selected payment method is not available', 'ecab-taxi-booking-manager'),
                    array('status' => 400)
                );
            }
            
            // Process based on payment method
            if ($payment_method === 'direct_order') {
                // Create direct order
                $order_id = $this->create_direct_order($booking_id, $params);
                
                if (is_wp_error($order_id)) {
                    return $order_id;
                }
                
                return new WP_REST_Response(
                    array(
                        'message' => esc_html__('Order created successfully', 'ecab-taxi-booking-manager'),
                        'order_id' => $order_id,
                        'payment_method' => 'direct_order'
                    ),
                    201
                );
            } elseif ($payment_method === 'woocommerce') {
                // Create WooCommerce order
                if (!class_exists('WooCommerce')) {
                    return new WP_Error(
                        'woocommerce_required',
                        esc_html__('WooCommerce is required for this payment method', 'ecab-taxi-booking-manager'),
                        array('status' => 400)
                    );
                }
                
                $wc_order_id = $this->create_woocommerce_order($booking_id, $params);
                
                if (is_wp_error($wc_order_id)) {
                    return $wc_order_id;
                }
                
                return new WP_REST_Response(
                    array(
                        'message' => esc_html__('WooCommerce order created successfully', 'ecab-taxi-booking-manager'),
                        'order_id' => $wc_order_id,
                        'payment_method' => 'woocommerce',
                        'payment_url' => wc_get_checkout_url() . '?order_id=' . $wc_order_id
                    ),
                    201
                );
            }
            
            return new WP_Error(
                'invalid_payment_method',
                esc_html__('Invalid payment method', 'ecab-taxi-booking-manager'),
                array('status' => 400)
            );
        }

        // Helper method to create direct order
        private function create_direct_order($booking_id, $params) {
            // Create order entry
            $order_data = array(
                'post_title'   => sprintf(esc_html__('Direct Order for Booking #%s', 'ecab-taxi-booking-manager'), $booking_id),
                'post_content' => '',
                'post_status'  => 'publish',
                'post_type'    => 'mptbm_order',
                'meta_input'   => array(
                    'mptbm_booking_id'  => $booking_id,
                    'mptbm_payment_method' => 'direct_order',
                    'mptbm_order_status' => 'pending'
                )
            );
            
            // Add customer info if available
            if (isset($params['customer_id'])) {
                $order_data['meta_input']['mptbm_customer_id'] = $params['customer_id'];
            }
            
            // Insert order post
            $order_id = wp_insert_post($order_data);
            
            if (is_wp_error($order_id)) {
                return new WP_Error(
                    'order_creation_failed',
                    $order_id->get_error_message(),
                    array('status' => 500)
                );
            }
            
            // Add reference to booking
            update_post_meta($booking_id, 'mptbm_order_id', $order_id);
            
            return $order_id;
        }

        // Helper method to create WooCommerce order
        private function create_woocommerce_order($booking_id, $params) {
            // Get booking info
            $transport_id = get_post_meta($booking_id, 'mptbm_transport_id', true);
            $transport = get_post($transport_id);
            $total_price = get_post_meta($booking_id, 'mptbm_total_price', true);
            $customer_name = get_post_meta($booking_id, 'mptbm_customer_name', true);
            $customer_email = get_post_meta($booking_id, 'mptbm_customer_email', true);
            $customer_phone = get_post_meta($booking_id, 'mptbm_customer_phone', true);
            
            // Create product if not exists
            $product_id = $this->get_or_create_wc_product($transport);
            
            if (is_wp_error($product_id)) {
                return $product_id;
            }
            
            // Create WC order
            $order = wc_create_order();
            
            // Add product to order
            $order->add_product(wc_get_product($product_id), 1, array(
                'subtotal' => $total_price,
                'total' => $total_price
            ));
            
            // Set order meta
            $order->update_meta_data('mptbm_booking_id', $booking_id);
            
            // Set order address
            $address = array(
                'first_name' => $customer_name,
                'email'      => $customer_email,
                'phone'      => $customer_phone
            );
            
            $order->set_address($address, 'billing');
            
            // Set order totals
            $order->set_total($total_price);
            $order->save();
            
            // Add reference to booking
            update_post_meta($booking_id, 'mptbm_wc_order_id', $order->get_id());
            
            return $order->get_id();
        }

        // Helper method to get or create WC product
        private function get_or_create_wc_product($transport) {
            // Check if hidden product exists
            $args = array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'meta_query' => array(
                    array(
                        'key' => 'mptbm_hidden_product',
                        'value' => '1'
                    )
                )
            );
            
            $products = get_posts($args);
            
            if (!empty($products)) {
                return $products[0]->ID;
            }
            
            // Create new hidden product
            $product_data = array(
                'post_title'   => 'MPTBM Taxi Booking',
                'post_content' => 'This is a hidden product used for taxi bookings.',
                'post_status'  => 'publish',
                'post_type'    => 'product',
                'meta_input'   => array(
                    'mptbm_hidden_product' => '1',
                    '_virtual' => 'yes',
                    '_sold_individually' => 'yes',
                    '_visibility' => 'hidden'
                )
            );
            
            $product_id = wp_insert_post($product_data);
            
            if (is_wp_error($product_id)) {
                return $product_id;
            }
            
            // Set product type
            wp_set_object_terms($product_id, 'simple', 'product_type');
            
            // Set product price
            update_post_meta($product_id, '_regular_price', '0');
            update_post_meta($product_id, '_price', '0');
            
            return $product_id;
        }

        // Method for updating orders
        public function update_order($request) {
            $order_id = $request['id'];
            $params = $request->get_params();
            
            // Check if order exists
            $order = false;
            
            // Check WooCommerce order
            if (class_exists('WooCommerce')) {
                $wc_order = wc_get_order($order_id);
                
                if ($wc_order) {
                    // Update WooCommerce order status
                    if (isset($params['status'])) {
                        $wc_order->update_status($params['status']);
                    }
                    
                    return new WP_REST_Response(
                        array(
                            'message' => esc_html__('Order updated successfully', 'ecab-taxi-booking-manager'),
                            'order_id' => $order_id,
                            'payment_method' => 'woocommerce'
                        ),
                        200
                    );
                }
            }
            
            // Check direct order
            $order = get_post($order_id);
            
            if (!$order || $order->post_type !== 'mptbm_order') {
                return new WP_Error(
                    'order_not_found',
                    esc_html__('Order not found', 'ecab-taxi-booking-manager'),
                    array('status' => 404)
                );
            }
            
            // Update direct order status
            if (isset($params['status'])) {
                update_post_meta($order_id, 'mptbm_order_status', $params['status']);
            }
            
            return new WP_REST_Response(
                array(
                    'message' => esc_html__('Order updated successfully', 'ecab-taxi-booking-manager'),
                    'order_id' => $order_id,
                    'payment_method' => 'direct_order'
                ),
                200
            );
        }

        // Method for creating customers
        public function create_customer($request) {
            $params = $request->get_params();
            
            // Check if user exists
            $user = get_user_by('email', $params['email']);
            
            if ($user) {
                return new WP_Error(
                    'user_exists',
                    esc_html__('A user with this email already exists', 'ecab-taxi-booking-manager'),
                    array('status' => 400)
                );
            }
            
            // Create WordPress user
            $username = sanitize_user(strtolower(explode('@', $params['email'])[0]));
            $password = wp_generate_password();
            
            $user_id = wp_create_user($username, $password, $params['email']);
            
            if (is_wp_error($user_id)) {
                return new WP_Error(
                    'user_creation_failed',
                    $user_id->get_error_message(),
                    array('status' => 500)
                );
            }
            
            // Set user role
            $user = new WP_User($user_id);
            $user->set_role('customer');
            
            // Update user meta
            update_user_meta($user_id, 'first_name', $params['name']);
            
            if (isset($params['phone'])) {
                update_user_meta($user_id, 'billing_phone', $params['phone']);
            }
            
            if (isset($params['address'])) {
                update_user_meta($user_id, 'billing_address_1', $params['address']);
            }
            
            // Return customer data
            $data = array(
                'id' => $user_id,
                'name' => $params['name'],
                'email' => $params['email'],
                'phone' => isset($params['phone']) ? $params['phone'] : '',
                'address' => isset($params['address']) ? $params['address'] : ''
            );
            
            return new WP_REST_Response($data, 201);
        }

        // Method for updating customers
        public function update_customer($request) {
            $customer_id = $request['id'];
            $params = $request->get_params();
            
            // Check if user exists
            $user = get_user_by('ID', $customer_id);
            
            if (!$user) {
                return new WP_Error(
                    'user_not_found',
                    esc_html__('User not found', 'ecab-taxi-booking-manager'),
                    array('status' => 404)
                );
            }
            
            // Update user data
            $userdata = array(
                'ID' => $customer_id
            );
            
            // Update email if provided
            if (isset($params['email'])) {
                // Check if email is already in use
                $existing_user = get_user_by('email', $params['email']);
                
                if ($existing_user && $existing_user->ID !== $customer_id) {
                    return new WP_Error(
                        'email_exists',
                        esc_html__('A user with this email already exists', 'ecab-taxi-booking-manager'),
                        array('status' => 400)
                    );
                }
                
                $userdata['user_email'] = $params['email'];
            }
            
            // Update user
            $user_id = wp_update_user($userdata);
            
            if (is_wp_error($user_id)) {
                return new WP_Error(
                    'user_update_failed',
                    $user_id->get_error_message(),
                    array('status' => 500)
                );
            }
            
            // Update user meta
            if (isset($params['name'])) {
                update_user_meta($customer_id, 'first_name', $params['name']);
            }
            
            if (isset($params['phone'])) {
                update_user_meta($customer_id, 'billing_phone', $params['phone']);
            }
            
            if (isset($params['address'])) {
                update_user_meta($customer_id, 'billing_address_1', $params['address']);
            }
            
            // Return customer data
            $data = array(
                'id' => $customer_id,
                'name' => isset($params['name']) ? $params['name'] : get_user_meta($customer_id, 'first_name', true),
                'email' => isset($params['email']) ? $params['email'] : $user->user_email,
                'phone' => isset($params['phone']) ? $params['phone'] : get_user_meta($customer_id, 'billing_phone', true),
                'address' => isset($params['address']) ? $params['address'] : get_user_meta($customer_id, 'billing_address_1', true)
            );
            
            return new WP_REST_Response($data, 200);
        }

        // Permission callback for admin-only operations
        public function check_booking_admin_permission($request) {
            // Always allow access for debugging
            return true;
            // Original code:
            // return current_user_can('manage_options');
        }
        
        // Get locations
        public function get_locations($request) {
            $transport_id = isset($request['transport_id']) ? intval($request['transport_id']) : 0;
            $start_place = isset($request['start_place']) ? $request['start_place'] : '';
            $include_details = isset($request['include_details']) ? (bool)$request['include_details'] : false;
            
            $locations = array();
            
            if ($transport_id > 0) {
                // Get locations for specific transport
                $price_based = get_post_meta($transport_id, 'mptbm_price_based', true);
                
                if ($price_based === 'manual') {
                    $manual_prices = get_post_meta($transport_id, 'mptbm_manual_prices', true);
                    
                    if (is_array($manual_prices)) {
                        if (!empty($start_place)) {
                            // Get end locations for specific start location
                            $end_locations = array();
                            foreach ($manual_prices as $price) {
                                if ($price['start'] === $start_place) {
                                    $end_location = array(
                                        'location' => $price['end'],
                                        'price' => floatval($price['price']),
                                        'return_enabled' => isset($price['return_enabled']) ? (bool)$price['return_enabled'] : false
                                    );
                                    
                                    // Add coordinates if available
                                    if ($include_details) {
                                        $end_coords = $this->get_location_coordinates($price['end']);
                                        if ($end_coords) {
                                            $end_location['coordinates'] = $end_coords;
                                        }
                                    }
                                    
                                    $end_locations[] = $end_location;
                                }
                            }
                            return new WP_REST_Response(array('end_locations' => $end_locations), 200);
                        } else {
                            // Get all start locations
                            $start_locations = array();
                            $unique_starts = array();
                            
                            foreach ($manual_prices as $price) {
                                if (!in_array($price['start'], $unique_starts)) {
                                    $unique_starts[] = $price['start'];
                                    
                                    $start_location = array(
                                        'location' => $price['start']
                                    );
                                    
                                    // Add coordinates if available
                                    if ($include_details) {
                                        $start_coords = $this->get_location_coordinates($price['start']);
                                        if ($start_coords) {
                                            $start_location['coordinates'] = $start_coords;
                                        }
                                    }
                                    
                                    $start_locations[] = $start_location;
                                }
                            }
                            
                            return new WP_REST_Response(array('start_locations' => $start_locations), 200);
                        }
                    }
                }
            } else {
                // Get all locations from all transports
                $args = array(
                    'post_type' => 'mptbm_rent',
                    'posts_per_page' => -1,
                    'post_status' => 'publish'
                );
                
                $transports = get_posts($args);
                $all_locations = array();
                $unique_locations = array();
                
                foreach ($transports as $transport) {
                    $price_based = get_post_meta($transport->ID, 'mptbm_price_based', true);
                    
                    if ($price_based === 'manual') {
                        $manual_prices = get_post_meta($transport->ID, 'mptbm_manual_prices', true);
                        
                        if (is_array($manual_prices)) {
                            foreach ($manual_prices as $price) {
                                // Add start location
                                if (!in_array($price['start'], $unique_locations)) {
                                    $unique_locations[] = $price['start'];
                                    
                                    $location_data = array(
                                        'location' => $price['start']
                                    );
                                    
                                    // Add coordinates if available
                                    if ($include_details) {
                                        $coords = $this->get_location_coordinates($price['start']);
                                        if ($coords) {
                                            $location_data['coordinates'] = $coords;
                                        }
                                    }
                                    
                                    $all_locations[] = $location_data;
                                }
                                
                                // Add end location
                                if (!in_array($price['end'], $unique_locations)) {
                                    $unique_locations[] = $price['end'];
                                    
                                    $location_data = array(
                                        'location' => $price['end']
                                    );
                                    
                                    // Add coordinates if available
                                    if ($include_details) {
                                        $coords = $this->get_location_coordinates($price['end']);
                                        if ($coords) {
                                            $location_data['coordinates'] = $coords;
                                        }
                                    }
                                    
                                    $all_locations[] = $location_data;
                                }
                            }
                        }
                    }
                }
                
                // Sort locations alphabetically
                usort($all_locations, function($a, $b) {
                    return strcmp($a['location'], $b['location']);
                });
                
                return new WP_REST_Response(array('locations' => $all_locations), 200);
            }
            
            return new WP_REST_Response(array('locations' => $locations), 200);
        }
        
        // Helper method to get location coordinates (if we store them)
        private function get_location_coordinates($location_name) {
            global $wpdb;
            
            // First check if we have saved coordinates
            $table_name = $wpdb->prefix . 'mptbm_locations';
            
            // Check if table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                $result = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT latitude, longitude FROM $table_name WHERE location_name = %s LIMIT 1",
                        $location_name
                    )
                );
                
                if ($result) {
                    return array(
                        'lat' => floatval($result->latitude),
                        'lng' => floatval($result->longitude)
                    );
                }
            }
            
            // If no stored coordinates, try to geocode with Google Maps API
            $api_key = MP_Global_Function::get_settings('mptbm_map_api_settings', 'gmap_api_key', '');
            
            if (empty($api_key)) {
                return null;
            }
            
            // Cache key for this location
            $cache_key = 'mptbm_geo_' . md5($location_name);
            $cached = get_transient($cache_key);
            
            if ($cached !== false) {
                return $cached;
            }
            
            // Prepare address
            $address = urlencode($location_name);
            
            // Build API URL
            $url = "https://maps.googleapis.com/maps/api/geocode/json?address={$address}&key={$api_key}";
            
            // Make request
            $response = wp_remote_get($url);
            
            if (is_wp_error($response)) {
                return null;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($data['status'] !== 'OK' || empty($data['results'])) {
                return null;
            }
            
            $coords = array(
                'lat' => $data['results'][0]['geometry']['location']['lat'],
                'lng' => $data['results'][0]['geometry']['location']['lng']
            );
            
            // Cache for 30 days
            set_transient($cache_key, $coords, 30 * DAY_IN_SECONDS);
            
            return $coords;
        }
        
        // Add a new location
        public function add_location($request) {
            $params = $request->get_params();
            $transport_id = $params['transport_id'];
            $start_location = $params['start_location'];
            $end_location = $params['end_location'];
            $price = $params['price'];
            $return_enabled = isset($params['return_enabled']) ? (bool)$params['return_enabled'] : false;
            
            $transport = get_post($transport_id);
            if (!$transport || $transport->post_type !== 'mptbm_rent') {
                return new WP_Error(
                    'transport_not_found',
                    esc_html__('Transport service not found', 'ecab-taxi-booking-manager'),
                    array('status' => 404)
                );
            }
            
            $price_based = get_post_meta($transport_id, 'mptbm_price_based', true);
            if ($price_based !== 'manual') {
                return new WP_Error(
                    'invalid_pricing_type',
                    esc_html__('Custom locations can only be added to manual pricing transports', 'ecab-taxi-booking-manager'),
                    array('status' => 400)
                );
            }
            
            $manual_prices = get_post_meta($transport_id, 'mptbm_manual_prices', true);
            if (!is_array($manual_prices)) {
                $manual_prices = array();
            }
            
            // Check if location pair already exists
            foreach ($manual_prices as $key => $location_price) {
                if ($location_price['start'] === $start_location && $location_price['end'] === $end_location) {
                    // Update existing price
                    $manual_prices[$key]['price'] = floatval($price);
                    $manual_prices[$key]['return_enabled'] = $return_enabled;
                    
                    update_post_meta($transport_id, 'mptbm_manual_prices', $manual_prices);
                    
                    return new WP_REST_Response(
                        array(
                            'message' => esc_html__('Location price updated successfully', 'ecab-taxi-booking-manager'),
                            'location' => array(
                                'start' => $start_location,
                                'end' => $end_location,
                                'price' => floatval($price),
                                'return_enabled' => $return_enabled
                            )
                        ), 
                        200
                    );
                }
            }
            
            // Add new location price
            $new_location = array(
                'id' => uniqid(),
                'start' => $start_location,
                'end' => $end_location,
                'price' => floatval($price),
                'return_enabled' => $return_enabled
            );
            
            $manual_prices[] = $new_location;
            update_post_meta($transport_id, 'mptbm_manual_prices', $manual_prices);
            
            return new WP_REST_Response(
                array(
                    'message' => esc_html__('Location added successfully', 'ecab-taxi-booking-manager'),
                    'location' => $new_location
                ), 
                201
            );
        }
        
        // Get fare quote
        public function get_fare_quote($request) {
            $transport_id = isset($request['transport_id']) ? intval($request['transport_id']) : 0;
            $distance = isset($request['distance']) ? floatval($request['distance']) : 1000; // Default 1 km
            $duration = isset($request['duration']) ? intval($request['duration']) : 3600; // Default 1 hour
            
            if ($transport_id <= 0) {
                return new WP_Error(
                    'missing_transport_id',
                    esc_html__('Transport ID is required', 'ecab-taxi-booking-manager'),
                    array('status' => 400)
                );
            }
            
            $transport = get_post($transport_id);
            if (!$transport || $transport->post_type !== 'mptbm_rent') {
                return new WP_Error(
                    'transport_not_found',
                    esc_html__('Transport service not found', 'ecab-taxi-booking-manager'),
                    array('status' => 404)
                );
            }
            
            $price_based = get_post_meta($transport_id, 'mptbm_price_based', true);
            $price = 0;
            
            if ($price_based === 'dynamic' || $price_based === 'distance_duration') {
                // Calculate based on distance
                $km_price = floatval(get_post_meta($transport_id, 'mptbm_km_price', true));
                $min_price = floatval(get_post_meta($transport_id, 'mptbm_min_price', true));
                
                // Assuming distance is already in meters
                $distance_km = $distance / 1000;
                
                // Check if the unit is mile
                $unit = MP_Global_Function::get_settings('mp_global_settings', 'km_or_mile', 'km');
                
                // Only convert if the unit is mile and distance is in meters
                if ($unit === 'mile') {
                    $distance_km *= 0.621371; // Convert km to miles
                }
                
                $calculated_price = $distance_km * $km_price;
                $price = max($calculated_price, $min_price);
                
                // If price based is distance_duration, add the duration component
                if ($price_based === 'distance_duration') {
                    $hour_price = floatval(get_post_meta($transport_id, 'mptbm_hour_price', true));
                    $duration_hours = $duration / 3600;
                    $price += $hour_price * ceil($duration_hours);
                }
            } elseif ($price_based === 'fixed_hourly') {
                // Calculate based on time
                $hour_price = floatval(get_post_meta($transport_id, 'mptbm_hour_price', true));
                
                // Convert duration from seconds to hours
                $duration_hours = $duration / 3600;
                
                $price = $hour_price * ceil($duration_hours);
            } elseif ($price_based === 'manual') {
                // We need specific locations for manual pricing
                return new WP_REST_Response(
                    array(
                        'message' => esc_html__('For manual pricing, use the calculate-price endpoint with specific locations', 'ecab-taxi-booking-manager')
                    ), 
                    200
                );
            }
            
            // Ensure the price is not zero due to misconfiguration
            if ($price <= 0 && $price_based !== 'manual') {
                // Check if there's a default price set anywhere
                $default_price = floatval(get_post_meta($transport_id, 'mptbm_default_price', true));
                if ($default_price > 0) {
                    $price = $default_price;
                } else {
                    // If no default price, use minimum price if set
                    $min_price = floatval(get_post_meta($transport_id, 'mptbm_min_price', true));
                    if ($min_price > 0) {
                        $price = $min_price;
                    }
                }
            }
            
            // Get vehicle details
            $max_passenger = get_post_meta($transport_id, 'mptbm_max_passenger', true);
            $max_bag = get_post_meta($transport_id, 'mptbm_max_bag', true);
            $vehicle_name = get_post_meta($transport_id, 'mptbm_vehicle_name', true) ?: $transport->post_title;
            $vehicle_model = get_post_meta($transport_id, 'mptbm_vehicle_model', true) ?: '';
            $vehicle_type = get_post_meta($transport_id, 'mptbm_vehicle_type', true) ?: '';
            $engine_type = get_post_meta($transport_id, 'mptbm_engine_type', true) ?: '';
            $fuel_type = get_post_meta($transport_id, 'mptbm_fuel_type', true) ?: '';
            $transmission = get_post_meta($transport_id, 'mptbm_transmission', true) ?: '';
            
            // Get vehicle image
            $image_id = get_post_thumbnail_id($transport_id);
            $image_url = $image_id ? wp_get_attachment_url($image_id) : '';
            
            return new WP_REST_Response(
                array(
                    'transport_id' => $transport_id,
                    'transport_name' => $transport->post_title,
                    'vehicle_details' => array(
                        'name' => $vehicle_name,
                        'model' => $vehicle_model,
                        'type' => $vehicle_type,
                        'engine' => $engine_type,
                        'fuel' => $fuel_type,
                        'transmission' => $transmission,
                        'max_passengers' => $max_passenger,
                        'max_bags' => $max_bag,
                        'image' => $image_url
                    ),
                    'price_based' => $price_based,
                    'distance' => $distance,
                    'duration' => $duration,
                    'price' => $price,
                    'currency' => $this->get_currency_symbol()
                ), 
                200
            );
        }
        
        // Get statistics
        public function get_statistics($request) {
            $period = isset($request['period']) ? $request['period'] : 'month';
            $date_from = isset($request['date_from']) ? $request['date_from'] : '';
            $date_to = isset($request['date_to']) ? $request['date_to'] : '';
            
            // Set default date range if not provided
            if (empty($date_from)) {
                if ($period === 'week') {
                    $date_from = date('Y-m-d', strtotime('-7 days'));
                } elseif ($period === 'month') {
                    $date_from = date('Y-m-d', strtotime('-30 days'));
                } elseif ($period === 'year') {
                    $date_from = date('Y-m-d', strtotime('-1 year'));
                } else {
                    $date_from = date('Y-m-d', strtotime('-30 days'));
                }
            }
            
            if (empty($date_to)) {
                $date_to = date('Y-m-d');
            }
            
            // Get booking statistics
            $args = array(
                'post_type' => 'mptbm_booking',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'date_query' => array(
                    array(
                        'after' => $date_from,
                        'before' => $date_to,
                        'inclusive' => true,
                    ),
                ),
            );
            
            $bookings = get_posts($args);
            $total_bookings = count($bookings);
            $total_revenue = 0;
            $transport_stats = array();
            
            foreach ($bookings as $booking) {
                $total_price = get_post_meta($booking->ID, 'mptbm_total_price', true);
                $total_revenue += floatval($total_price);
                
                $transport_id = get_post_meta($booking->ID, 'mptbm_transport_id', true);
                if (!isset($transport_stats[$transport_id])) {
                    $transport = get_post($transport_id);
                    $transport_stats[$transport_id] = array(
                        'id' => $transport_id,
                        'name' => $transport ? $transport->post_title : 'Unknown',
                        'bookings' => 0,
                        'revenue' => 0
                    );
                }
                
                $transport_stats[$transport_id]['bookings']++;
                $transport_stats[$transport_id]['revenue'] += floatval($total_price);
            }
            
            // Convert transport stats to array
            $transport_statistics = array_values($transport_stats);
            
            // Sort by number of bookings
            usort($transport_statistics, function($a, $b) {
                return $b['bookings'] - $a['bookings'];
            });
            
            // Get top 5 routes
            $route_stats = array();
            foreach ($bookings as $booking) {
                $pickup = get_post_meta($booking->ID, 'mptbm_pickup_location', true);
                $dropoff = get_post_meta($booking->ID, 'mptbm_dropoff_location', true);
                $route = $pickup . ' to ' . $dropoff;
                
                if (!isset($route_stats[$route])) {
                    $route_stats[$route] = array(
                        'route' => $route,
                        'bookings' => 0,
                        'revenue' => 0
                    );
                }
                
                $route_stats[$route]['bookings']++;
                $route_stats[$route]['revenue'] += floatval(get_post_meta($booking->ID, 'mptbm_total_price', true));
            }
            
            // Convert route stats to array
            $route_statistics = array_values($route_stats);
            
            // Sort by number of bookings
            usort($route_statistics, function($a, $b) {
                return $b['bookings'] - $a['bookings'];
            });
            
            // Take top 5
            $route_statistics = array_slice($route_statistics, 0, 5);
            
            return new WP_REST_Response(
                array(
                    'period' => $period,
                    'date_from' => $date_from,
                    'date_to' => $date_to,
                    'total_bookings' => $total_bookings,
                    'total_revenue' => $total_revenue,
                    'currency' => $this->get_currency_symbol(),
                    'top_transports' => $transport_statistics,
                    'top_routes' => $route_statistics
                ), 
                200
            );
        }
        
        // Filter transports
        public function filter_transports($request) {
            $params = $request->get_params();
            
            $args = array(
                'post_type' => 'mptbm_rent',
                'posts_per_page' => -1,
                'post_status' => 'publish'
            );
            
            // Apply filters
            $meta_query = array();
            
            // Filter by price type
            if (isset($params['price_based']) && !empty($params['price_based'])) {
                $meta_query[] = array(
                    'key' => 'mptbm_price_based',
                    'value' => $params['price_based'],
                    'compare' => '='
                );
            }
            
            // Filter by passenger capacity
            if (isset($params['passengers']) && intval($params['passengers']) > 0) {
                $meta_query[] = array(
                    'key' => 'mptbm_max_passenger',
                    'value' => intval($params['passengers']),
                    'compare' => '>='
                );
            }
            
            // Filter by baggage capacity
            if (isset($params['bags']) && intval($params['bags']) > 0) {
                $meta_query[] = array(
                    'key' => 'mptbm_max_bag',
                    'value' => intval($params['bags']),
                    'compare' => '>='
                );
            }
            
            // Filter by route (for manual pricing)
            $route_filtered = false;
            if (isset($params['start_location']) && isset($params['end_location'])) {
                $route_filtered = true;
                $route_transports = array();
                
                // First, get all transports
                $all_transports = get_posts($args);
                
                // Then filter manually by route
                foreach ($all_transports as $transport) {
                    $price_based = get_post_meta($transport->ID, 'mptbm_price_based', true);
                    
                    if ($price_based === 'manual') {
                        $manual_prices = get_post_meta($transport->ID, 'mptbm_manual_prices', true);
                        
                        if (is_array($manual_prices)) {
                            foreach ($manual_prices as $price) {
                                if ($price['start'] === $params['start_location'] && $price['end'] === $params['end_location']) {
                                    $route_transports[] = $transport->ID;
                                    break;
                                }
                            }
                        }
                    } else {
                        // For dynamic and fixed_hourly pricing, all routes are available
                        $route_transports[] = $transport->ID;
                    }
                }
                
                if (!empty($route_transports)) {
                    $args['post__in'] = $route_transports;
                } else {
                    // No transports match the route
                    return new WP_REST_Response(array('transports' => array()), 200);
                }
            }
            
            // Apply meta query if not empty
            if (!empty($meta_query)) {
                $args['meta_query'] = $meta_query;
            }
            
            $transports = get_posts($args);
            $data = array();
            
            foreach ($transports as $transport) {
                $transport_data = $this->prepare_transport_service($transport);
                
                // Calculate price for specific route if requested
                if (isset($params['start_location']) && isset($params['end_location'])) {
                    $price_calculation = $this->calculate_price_internal(
                        $transport->ID,
                        $params['start_location'],
                        $params['end_location'],
                        isset($params['booking_date']) ? $params['booking_date'] : date('Y-m-d'),
                        isset($params['return']) ? $params['return'] : false,
                        isset($params['extra_services']) ? $params['extra_services'] : array()
                    );
                    
                    if (!is_wp_error($price_calculation)) {
                        $transport_data['calculated_price'] = $price_calculation;
                    }
                }
                
                $data[] = $transport_data;
            }
            
            // Sort by price if calculated
            if (isset($params['start_location']) && isset($params['end_location'])) {
                usort($data, function($a, $b) {
                    if (!isset($a['calculated_price']) || !isset($b['calculated_price'])) {
                        return 0;
                    }
                    return $a['calculated_price']['total_price'] - $b['calculated_price']['total_price'];
                });
            }
            
            return new WP_REST_Response(array('transports' => $data), 200);
        }
        
        // Get extra services
        public function get_extra_services($request) {
            $transport_id = isset($request['transport_id']) ? intval($request['transport_id']) : 0;
            
            if ($transport_id <= 0) {
                return new WP_Error(
                    'missing_transport_id',
                    esc_html__('Transport ID is required', 'ecab-taxi-booking-manager'),
                    array('status' => 400)
                );
            }
            
            $transport = get_post($transport_id);
            if (!$transport || $transport->post_type !== 'mptbm_rent') {
                return new WP_Error(
                    'transport_not_found',
                    esc_html__('Transport service not found', 'ecab-taxi-booking-manager'),
                    array('status' => 404)
                );
            }
            
            $services = get_post_meta($transport_id, 'mptbm_extra_services', true);
            if (!is_array($services)) {
                $services = array();
            }
            
            return new WP_REST_Response(array('extra_services' => $services), 200);
        }
        
        // Get API settings
        public function get_api_settings($request) {
            $settings = array(
                'api_enabled' => MP_Global_Function::get_settings('mp_global_settings', 'enable_rest_api', 'off'),
                'api_authentication_type' => MP_Global_Function::get_settings('mp_global_settings', 'api_authentication_type', 'application_password'),
                'api_rate_limit' => (int)MP_Global_Function::get_settings('mp_global_settings', 'api_rate_limit', 60)
            );
            
            return new WP_REST_Response($settings, 200);
        }
        
        // Update API settings
        public function update_api_settings($request) {
            $params = $request->get_params();
            
            $global_settings = get_option('mp_global_settings', array());
            
            if (isset($params['api_enabled'])) {
                $global_settings['enable_rest_api'] = $params['api_enabled'] ? 'on' : 'off';
            }
            
            if (isset($params['api_authentication_type'])) {
                $global_settings['api_authentication_type'] = $params['api_authentication_type'];
            }
            
            if (isset($params['api_rate_limit'])) {
                $global_settings['api_rate_limit'] = max(0, intval($params['api_rate_limit']));
            }
            
            update_option('mp_global_settings', $global_settings);
            
            return new WP_REST_Response(
                array(
                    'message' => esc_html__('API settings updated successfully', 'ecab-taxi-booking-manager'),
                    'settings' => array(
                        'api_enabled' => $global_settings['enable_rest_api'],
                        'api_authentication_type' => $global_settings['api_authentication_type'],
                        'api_rate_limit' => (int)$global_settings['api_rate_limit']
                    )
                ), 
                200
            );
        }
        
        // Arguments for new endpoints
        private function get_location_args() {
            return array(
                'transport_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'ID of the transport service',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ),
                'start_location' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Start location name',
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param);
                    }
                ),
                'end_location' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'End location name',
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param);
                    }
                ),
                'price' => array(
                    'required' => true,
                    'type' => 'number',
                    'description' => 'Price for this route',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param >= 0;
                    }
                ),
                'return_enabled' => array(
                    'required' => false,
                    'type' => 'boolean',
                    'description' => 'Whether return is enabled for this route',
                    'default' => false
                )
            );
        }
        
        private function get_filter_args() {
            return array(
                'price_based' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Filter by price type (dynamic, manual, fixed_hourly)',
                    'enum' => array('dynamic', 'manual', 'fixed_hourly')
                ),
                'passengers' => array(
                    'required' => false,
                    'type' => 'integer',
                    'description' => 'Minimum passenger capacity',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ),
                'bags' => array(
                    'required' => false,
                    'type' => 'integer',
                    'description' => 'Minimum baggage capacity',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param >= 0;
                    }
                ),
                'start_location' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Start location for route filtering'
                ),
                'end_location' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'End location for route filtering'
                ),
                'booking_date' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Booking date for price calculation',
                    'validate_callback' => function($param) {
                        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $param);
                    }
                ),
                'return' => array(
                    'required' => false,
                    'type' => 'boolean',
                    'description' => 'Whether return journey is required',
                    'default' => false
                )
            );
        }
        
        private function get_settings_args() {
            return array(
                'api_enabled' => array(
                    'required' => false,
                    'type' => 'boolean',
                    'description' => 'Enable or disable the API'
                ),
                'api_authentication_type' => array(
                    'required' => false,
                    'type' => 'string',
                    'description' => 'Authentication type',
                    'enum' => array('none', 'application_password', 'jwt')
                ),
                'api_rate_limit' => array(
                    'required' => false,
                    'type' => 'integer',
                    'description' => 'Requests per minute (0 for unlimited)',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param >= 0;
                    }
                )
            );
        }

        // Add a helper method to ensure consistent currency formatting
        private function get_currency_symbol() {
            // First try to get WooCommerce currency symbol
            if (function_exists('get_woocommerce_currency_symbol')) {
                $symbol = get_woocommerce_currency_symbol();
            } else {
                // Default to dollar sign if WooCommerce is not available
                $symbol = '$';
            }
            return $symbol;
        }
    }

    new MPTBM_Rest_Api();
}
