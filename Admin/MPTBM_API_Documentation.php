<?php
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_API_Documentation')) {
    class MPTBM_API_Documentation {
        public function __construct() {
            add_action('admin_menu', array($this, 'add_api_documentation_menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_documentation_assets'));
        }
		

        public function add_api_documentation_menu() {
            add_submenu_page(
                'edit.php?post_type=mptbm_rent',
                'API Documentation',
                'API Documentation',
                'manage_options',
                'mptbm-api-documentation',
                array($this, 'render_documentation_page')
            );
        }

        public function enqueue_documentation_assets($hook) {
            if ($hook !== 'mptbm_rent_page_mptbm-api-documentation') {
                return;
            }

            wp_enqueue_style(
                'mptbm-api-documentation',
                MPTBM_PLUGIN_URL . '/assets/admin/css/api-documentation.css',
                array(),
                MPTBM_PLUGIN_VERSION
            );

            wp_enqueue_script(
                'mptbm-api-documentation',
                MPTBM_PLUGIN_URL . '/assets/admin/js/api-documentation.js',
                array('jquery'),
                MPTBM_PLUGIN_VERSION,
                true
            );
        }

        public function render_documentation_page() {
            $rest_url = rest_url('mptbm/v1/');
            ?>
            <div class="wrap mptbm-api-docs">
                <h1>E-Cab Taxi Booking Manager REST API Documentation</h1>
                
                <div class="mptbm-api-intro">
                    <p>
                        Welcome to the E-Cab Taxi Booking Manager REST API documentation. This API allows you to integrate
                        our taxi booking system with your applications. All endpoints are accessible via HTTPS at:
                        <code><?php echo esc_html($rest_url); ?></code>
                    </p>
                </div>

                <div class="mptbm-api-settings">
                    <h2>API Settings</h2>
                    <p>
                        The REST API can be configured in the <a href="<?php echo esc_url(admin_url('edit.php?post_type=mptbm_rent&page=mptbm_settings_page')); ?>">Global Settings</a> page. 
                        Current settings:
                    </p>
                    <?php 
                    $api_enabled = MP_Global_Function::get_settings('mp_global_settings', 'enable_rest_api', 'on');
                    $auth_type = MP_Global_Function::get_settings('mp_global_settings', 'api_authentication_type', 'application_password');
                    $rate_limit = (int) MP_Global_Function::get_settings('mp_global_settings', 'api_rate_limit', 60);
                    ?>
                    <ul class="mptbm-api-settings-list">
                        <li>
                            <strong>API Status:</strong> 
                            <span class="<?php echo $api_enabled === 'on' ? 'status-enabled' : 'status-disabled'; ?>">
                                <?php echo $api_enabled === 'on' ? 'Enabled' : 'Disabled'; ?>
                            </span>
                            <?php if ($api_enabled !== 'on'): ?>
                                <p class="api-status-note">
                                    <em>Note: Enable the API in E-Cab Settings to access the endpoints.</em>
                                </p>
                            <?php endif; ?>
                        </li>
                        <li>
                            <strong>Authentication Type:</strong> 
                            <?php 
                            switch($auth_type) {
                                case 'none':
                                    echo 'No Authentication Required';
                                    break;
                                case 'application_password':
                                    echo 'Application Passwords';
                                    break;
                                case 'jwt':
                                    echo 'JWT Authentication';
                                    break;
                            }
                            ?>
                        </li>
                        <li>
                            <strong>Rate Limit:</strong> 
                            <?php echo $rate_limit === 0 ? 'Unlimited' : $rate_limit . ' requests per minute'; ?>
                        </li>
                    </ul>
                </div>

                <div class="mptbm-api-authentication">
                    <h2>Authentication</h2>
                    <?php if ($auth_type === 'none'): ?>
                        <p>Authentication is currently disabled. All endpoints are publicly accessible.</p>
                    <?php elseif ($auth_type === 'application_password'): ?>
                        <p>
                            This API uses WordPress Application Passwords for authentication. To use the API:
                        </p>
                        <ol>
                            <li>Go to your WordPress profile page</li>
                            <li>Scroll down to the "Application Passwords" section</li>
                            <li>Create a new application password for this API</li>
                            <li>Use the generated password in the Authorization header:</li>
                        </ol>
                        <pre><code>Authorization: Basic base64_encode(username:application_password)</code></pre>
                        <p>
                            For more details, see the <a href="https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/#application-passwords" target="_blank">WordPress REST API Authentication Documentation</a>.
                        </p>
                    <?php elseif ($auth_type === 'jwt'): ?>
                        <p>JWT Authentication is currently not implemented.</p>
                    <?php endif; ?>
                </div>

                <?php if ($rate_limit > 0): ?>
                <div class="mptbm-api-rate-limit">
                    <h2>Rate Limiting</h2>
                    <p>
                        This API is rate-limited to <?php echo esc_html($rate_limit); ?> requests per minute per IP address. 
                        If you exceed this limit, you'll receive a 429 (Too Many Requests) response.
                    </p>
                    <p>
                        The following headers are included in API responses:
                    </p>
                    <ul>
                        <li><code>X-RateLimit-Limit</code>: The number of requests allowed per minute</li>
                        <li><code>X-RateLimit-Remaining</code>: The number of requests remaining in the current window</li>
                        <li><code>X-RateLimit-Reset</code>: The time when the rate limit will reset (Unix timestamp)</li>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="mptbm-api-endpoints">
                    <h2>Available Endpoints</h2>

                    <!-- Transport Services Endpoint -->
                    <div class="endpoint-section">
                        <h3>List Transport Services</h3>
                        <div class="endpoint-details">
                            <p><strong>Endpoint:</strong> <code>GET /wp-json/mptbm/v1/rents</code></p>
                            <p><strong>Description:</strong> Retrieves a list of available transport services.</p>
                            
                            <div class="parameters">
                                <h4>Parameters</h4>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th>Parameter</th>
                                            <th>Type</th>
                                            <th>Required</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>price_based</td>
                                            <td>string</td>
                                            <td>No</td>
                                            <td>Filter services by pricing type (dynamic, manual, fixed_hourly)</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="example-response">
                                <h4>Example Response</h4>
                                <pre>
{
    "id": 123,
    "title": "Luxury Sedan",
    "price_based": "dynamic",
    "initial_price": 10.00,
    "min_price": 25.00,
    "hour_price": 30.00,
    "km_price": 2.50,
    "max_passenger": 4,
    "max_bag": 3,
    "schedule": {
        "monday": ["09:00", "17:00"],
        "tuesday": ["09:00", "17:00"]
    }
}
                                </pre>
                            </div>
                        </div>
                    </div>

                    <!-- Booking Endpoints -->
                    <div class="endpoint-section">
                        <h3>Manage Bookings</h3>
                        
                        <!-- Create Booking -->
                        <div class="endpoint-details">
                            <h4>Create Booking</h4>
                            <p><strong>Endpoint:</strong> <code>POST /wp-json/mptbm/v1/bookings</code></p>
                            <p><strong>Authentication Required:</strong> Yes</p>
                            
                            <div class="parameters">
                                <h4>Parameters</h4>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th>Parameter</th>
                                            <th>Type</th>
                                            <th>Required</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>transport_id</td>
                                            <td>integer</td>
                                            <td>Yes</td>
                                            <td>ID of the transport service</td>
                                        </tr>
                                        <tr>
                                            <td>start_location</td>
                                            <td>string</td>
                                            <td>Yes</td>
                                            <td>Pickup location</td>
                                        </tr>
                                        <tr>
                                            <td>end_location</td>
                                            <td>string</td>
                                            <td>Yes</td>
                                            <td>Drop-off location</td>
                                        </tr>
                                        <tr>
                                            <td>booking_date</td>
                                            <td>string</td>
                                            <td>Yes</td>
                                            <td>Date of booking (YYYY-MM-DD format)</td>
                                        </tr>
                                        <tr>
                                            <td>booking_time</td>
                                            <td>string</td>
                                            <td>Yes</td>
                                            <td>Time of booking (HH:MM format)</td>
                                        </tr>
                                        <tr>
                                            <td>customer_name</td>
                                            <td>string</td>
                                            <td>Yes</td>
                                            <td>Customer's name</td>
                                        </tr>
                                        <tr>
                                            <td>customer_email</td>
                                            <td>string</td>
                                            <td>Yes</td>
                                            <td>Customer's email address</td>
                                        </tr>
                                        <tr>
                                            <td>customer_phone</td>
                                            <td>string</td>
                                            <td>No</td>
                                            <td>Customer's phone number</td>
                                        </tr>
                                        <tr>
                                            <td>return</td>
                                            <td>boolean</td>
                                            <td>No</td>
                                            <td>Whether return journey is required</td>
                                        </tr>
                                        <tr>
                                            <td>return_date</td>
                                            <td>string</td>
                                            <td>No</td>
                                            <td>Return date (YYYY-MM-DD format)</td>
                                        </tr>
                                        <tr>
                                            <td>return_time</td>
                                            <td>string</td>
                                            <td>No</td>
                                            <td>Return time (HH:MM format)</td>
                                        </tr>
                                        <tr>
                                            <td>passengers</td>
                                            <td>integer</td>
                                            <td>No</td>
                                            <td>Number of passengers</td>
                                        </tr>
                                        <tr>
                                            <td>bags</td>
                                            <td>integer</td>
                                            <td>No</td>
                                            <td>Number of bags</td>
                                        </tr>
                                        <tr>
                                            <td>extra_services</td>
                                            <td>array</td>
                                            <td>No</td>
                                            <td>IDs of extra services</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="example-response">
                                <h4>Example Response</h4>
                                <pre>
{
    "id": 123,
    "title": "Booking: Downtown to Airport on 2023-05-15",
    "status": "publish",
    "date_created": "2023-05-10T14:30:45",
    "pickup_location": "Downtown",
    "dropoff_location": "Airport",
    "journey_date": "2023-05-15",
    "journey_time": "14:00",
    "return": false,
    "passengers": 2,
    "bags": 1,
    "total_price": "75.00",
    "currency": "$"
}
                                </pre>
                            </div>
                        </div>

                        <!-- Get Booking -->
                        <div class="endpoint-details">
                            <h4>Get Booking Details</h4>
                            <p><strong>Endpoint:</strong> <code>GET /wp-json/mptbm/v1/booking/{id}</code></p>
                            <p><strong>Authentication Required:</strong> Yes</p>
                        </div>

                        <!-- Update Booking -->
                        <div class="endpoint-details">
                            <h4>Update Booking</h4>
                            <p><strong>Endpoint:</strong> <code>PUT /wp-json/mptbm/v1/bookings/{id}</code></p>
                            <p><strong>Authentication Required:</strong> Yes</p>
                            
                            <div class="parameters">
                                <h4>Parameters</h4>
                                <p>Same parameters as Create Booking. All parameters are optional for update.</p>
                            </div>

                            <div class="example-response">
                                <h4>Example Response</h4>
                                <p>Same as Create Booking response</p>
                            </div>
                        </div>

                        <!-- Delete Booking -->
                        <div class="endpoint-details">
                            <h4>Delete Booking</h4>
                            <p><strong>Endpoint:</strong> <code>DELETE /wp-json/mptbm/v1/bookings/{id}</code></p>
                            <p><strong>Authentication Required:</strong> Yes</p>
                            
                            <div class="example-response">
                                <h4>Example Response</h4>
                                <pre>
{
    "message": "Booking deleted successfully",
    "id": 123
}
                                </pre>
                            </div>
                        </div>

                        <!-- Calculate Price -->
                        <div class="endpoint-details">
                            <h4>Calculate Price</h4>
                            <p><strong>Endpoint:</strong> <code>POST /wp-json/mptbm/v1/calculate-price</code></p>
                            <p><strong>Authentication Required:</strong> Yes</p>
                            
                            <div class="parameters">
                                <h4>Parameters</h4>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th>Parameter</th>
                                            <th>Type</th>
                                            <th>Required</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>transport_id</td>
                                            <td>integer</td>
                                            <td>Yes</td>
                                            <td>ID of the transport service</td>
                                        </tr>
                                        <tr>
                                            <td>start_location</td>
                                            <td>string</td>
                                            <td>Yes</td>
                                            <td>Pickup location</td>
                                        </tr>
                                        <tr>
                                            <td>end_location</td>
                                            <td>string</td>
                                            <td>Yes</td>
                                            <td>Drop-off location</td>
                                        </tr>
                                        <tr>
                                            <td>booking_date</td>
                                            <td>string</td>
                                            <td>Yes</td>
                                            <td>Date of booking (YYYY-MM-DD format)</td>
                                        </tr>
                                        <tr>
                                            <td>return</td>
                                            <td>boolean</td>
                                            <td>No</td>
                                            <td>Whether return journey is required</td>
                                        </tr>
                                        <tr>
                                            <td>extra_services</td>
                                            <td>array</td>
                                            <td>No</td>
                                            <td>IDs of extra services</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="example-response">
                                <h4>Example Response</h4>
                                <pre>
{
    "base_price": 65.00,
    "extra_price": 10.00,
    "total_price": 75.00,
    "currency": "$"
}
                                </pre>
                            </div>
                        </div>

                        <!-- Create Order -->
                        <div class="endpoint-details">
                            <h4>Create Order</h4>
                            <p><strong>Endpoint:</strong> <code>POST /wp-json/mptbm/v1/orders</code></p>
                            <p><strong>Authentication Required:</strong> Yes</p>
                            
                            <div class="parameters">
                                <h4>Parameters</h4>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th>Parameter</th>
                                            <th>Type</th>
                                            <th>Required</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>booking_id</td>
                                            <td>integer</td>
                                            <td>Yes</td>
                                            <td>ID of the booking to create an order for</td>
                                        </tr>
                                        <tr>
                                            <td>payment_method</td>
                                            <td>string</td>
                                            <td>Yes</td>
                                            <td>Payment method (direct_order or woocommerce)</td>
                                        </tr>
                                        <tr>
                                            <td>customer_id</td>
                                            <td>integer</td>
                                            <td>No</td>
                                            <td>Customer user ID (if available)</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="example-response">
                                <h4>Example Response (Direct Order)</h4>
                                <pre>
{
    "message": "Order created successfully",
    "order_id": 456,
    "payment_method": "direct_order"
}
                                </pre>
                                <h4>Example Response (WooCommerce)</h4>
                                <pre>
{
    "message": "WooCommerce order created successfully",
    "order_id": 789,
    "payment_method": "woocommerce",
    "payment_url": "https://yoursite.com/checkout/?order_id=789"
}
                                </pre>
                            </div>
                        </div>

                        <!-- Update Order -->
                        <div class="endpoint-details">
                            <h4>Update Order</h4>
                            <p><strong>Endpoint:</strong> <code>PUT /wp-json/mptbm/v1/orders/{id}</code></p>
                            <p><strong>Authentication Required:</strong> Yes</p>
                            
                            <div class="parameters">
                                <h4>Parameters</h4>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th>Parameter</th>
                                            <th>Type</th>
                                            <th>Required</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>status</td>
                                            <td>string</td>
                                            <td>No</td>
                                            <td>Order status (pending, processing, completed, etc.)</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="example-response">
                                <h4>Example Response</h4>
                                <pre>
{
    "message": "Order updated successfully",
    "order_id": 456,
    "payment_method": "direct_order"
}
                                </pre>
                            </div>
                        </div>

                        <!-- Create Customer -->
                        <div class="endpoint-details">
                            <h4>Create Customer</h4>
                            <p><strong>Endpoint:</strong> <code>POST /wp-json/mptbm/v1/customers</code></p>
                            <p><strong>Authentication Required:</strong> Yes</p>
                            
                            <div class="parameters">
                                <h4>Parameters</h4>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th>Parameter</th>
                                            <th>Type</th>
                                            <th>Required</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>name</td>
                                            <td>string</td>
                                            <td>Yes</td>
                                            <td>Customer's name</td>
                                        </tr>
                                        <tr>
                                            <td>email</td>
                                            <td>string</td>
                                            <td>Yes</td>
                                            <td>Customer's email address</td>
                                        </tr>
                                        <tr>
                                            <td>phone</td>
                                            <td>string</td>
                                            <td>No</td>
                                            <td>Customer's phone number</td>
                                        </tr>
                                        <tr>
                                            <td>address</td>
                                            <td>string</td>
                                            <td>No</td>
                                            <td>Customer's address</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="example-response">
                                <h4>Example Response</h4>
                                <pre>
{
    "id": 42,
    "name": "John Doe",
    "email": "john.doe@example.com",
    "phone": "123-456-7890",
    "address": "123 Main St, Anytown, USA"
}
                                </pre>
                            </div>
                        </div>

                        <!-- Update Customer -->
                        <div class="endpoint-details">
                            <h4>Update Customer</h4>
                            <p><strong>Endpoint:</strong> <code>PUT /wp-json/mptbm/v1/customers/{id}</code></p>
                            <p><strong>Authentication Required:</strong> Yes</p>
                            
                            <div class="parameters">
                                <h4>Parameters</h4>
                                <p>Same parameters as Create Customer. All parameters are optional for update.</p>
                            </div>

                            <div class="example-response">
                                <h4>Example Response</h4>
                                <p>Same as Create Customer response</p>
                            </div>
                        </div>
                    </div>

                    <!-- Quote Endpoint -->
                    <div class="endpoint-section">
                        <h3>Get Fare Quote</h3>
                        <div class="endpoint-details">
                            <p><strong>Endpoint:</strong> <code>GET /wp-json/mptbm/v1/quote</code></p>
                            
                            <div class="parameters">
                                <h4>Parameters</h4>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th>Parameter</th>
                                            <th>Type</th>
                                            <th>Required</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>transport_id</td>
                                            <td>integer</td>
                                            <td>Yes</td>
                                            <td>ID of the transport service</td>
                                        </tr>
                                        <tr>
                                            <td>distance</td>
                                            <td>number</td>
                                            <td>No</td>
                                            <td>Distance in meters (default: 1000)</td>
                                        </tr>
                                        <tr>
                                            <td>duration</td>
                                            <td>number</td>
                                            <td>No</td>
                                            <td>Duration in seconds (default: 3600)</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Locations Endpoint -->
                    <div class="endpoint-section">
                        <h3>Get Locations</h3>
                        <div class="endpoint-details">
                            <p><strong>Endpoint:</strong> <code>GET /wp-json/mptbm/v1/locations</code></p>
                            
                            <div class="parameters">
                                <h4>Parameters</h4>
                                <table class="wp-list-table widefat fixed striped">
                                    <thead>
                                        <tr>
                                            <th>Parameter</th>
                                            <th>Type</th>
                                            <th>Required</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>transport_id</td>
                                            <td>integer</td>
                                            <td>No</td>
                                            <td>Filter locations by transport service</td>
                                        </tr>
                                        <tr>
                                            <td>start_place</td>
                                            <td>string</td>
                                            <td>No</td>
                                            <td>Get available end locations for a start location</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
    }

    new MPTBM_API_Documentation();
}
