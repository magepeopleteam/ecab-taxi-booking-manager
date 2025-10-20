<?php
/*
 * @Author: MagePeople Team
 * Copyright: mage-people.com
 */
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_API_Documentation')) {
    class MPTBM_API_Documentation {
        
        public function __construct() {
            add_action('admin_menu', array($this, 'add_documentation_menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_documentation_assets'));
            add_action('wp_ajax_mptbm_generate_api_key', array($this, 'ajax_generate_api_key'));
            add_action('wp_ajax_mptbm_revoke_api_key', array($this, 'ajax_revoke_api_key'));
            add_action('wp_ajax_mptbm_get_api_keys', array($this, 'ajax_get_api_keys'));
        }
        
        public function add_documentation_menu() {
            $cpt = MPTBM_Function::get_cpt();
            add_submenu_page(
                'edit.php?post_type=' . $cpt,
                esc_html__('API Documentation', 'ecab-taxi-booking-manager'),
                esc_html__('API Documentation', 'ecab-taxi-booking-manager'),
                'manage_options',
                'mptbm_api_docs',
                array($this, 'documentation_page')
            );
        }
        
        public function enqueue_documentation_assets($hook) {
            if ($hook !== 'mptbm_rent_page_mptbm_api_docs') {
                return;
            }
            
            wp_enqueue_style('mptbm-api-docs', MPTBM_PLUGIN_URL . '/assets/admin/css/api-documentation.css', array(), MPTBM_PLUGIN_VERSION);
            wp_enqueue_script('mptbm-api-docs', MPTBM_PLUGIN_URL . '/assets/admin/js/api-documentation.js', array('jquery'), MPTBM_PLUGIN_VERSION, true);
            
            wp_localize_script('mptbm-api-docs', 'mptbm_api', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mptbm_api_nonce'),
                'api_base_url' => site_url('wp-json/ecab-taxi/v1/')
            ));
        }
        
        public function documentation_page() {
            // Ensure API tables exist when accessing the documentation page
            $this->ensure_api_tables_exist();
            
            $api_enabled = MP_Global_Function::get_settings('mptbm_rest_api_settings', 'enable_rest_api', 'no');
            $base_url = site_url('wp-json/ecab-taxi/v1/');
            ?>
            <div class="wrap mptbm-api-documentation">
                <h1><?php esc_html_e('E-Cab Taxi Booking REST API Documentation', 'ecab-taxi-booking-manager'); ?></h1>
                
                <?php if ($api_enabled !== 'yes'): ?>
                    <div class="notice notice-warning">
                        <p>
                            <?php esc_html_e('REST API is currently disabled.', 'ecab-taxi-booking-manager'); ?>
                            <a href="<?php echo admin_url('edit.php?post_type=mptbm_rent&page=mptbm_settings_page'); ?>">
                                <?php esc_html_e('Enable it in settings', 'ecab-taxi-booking-manager'); ?>
                            </a>
                        </p>
                    </div>
                <?php endif; ?>
                
                <div class="api-docs-container">
                    <!-- API Keys Management -->
                    <div class="api-section">
                        <h2><span class="dashicons dashicons-admin-network"></span> <?php esc_html_e('API Key Management', 'ecab-taxi-booking-manager'); ?></h2>
                        <div class="api-keys-manager">
                            <div class="generate-key-form">
                                <h3><?php esc_html_e('Generate New API Key', 'ecab-taxi-booking-manager'); ?></h3>
                                <form id="generate-api-key-form">
                                    <table class="form-table">
                                        <tr>
                                            <th><label for="api-key-name"><?php esc_html_e('Key Name', 'ecab-taxi-booking-manager'); ?></label></th>
                                            <td><input type="text" id="api-key-name" name="name" class="regular-text" placeholder="<?php esc_attr_e('My Mobile App', 'ecab-taxi-booking-manager'); ?>" required></td>
                                        </tr>
                                        <tr>
                                            <th><label><?php esc_html_e('Permissions', 'ecab-taxi-booking-manager'); ?></label></th>
                                            <td>
                                                <label><input type="checkbox" name="permissions[]" value="read" checked> <?php esc_html_e('Read', 'ecab-taxi-booking-manager'); ?></label><br>
                                                <label><input type="checkbox" name="permissions[]" value="write" checked> <?php esc_html_e('Write', 'ecab-taxi-booking-manager'); ?></label>
                                            </td>
                                        </tr>
                                    </table>
                                    <p class="submit">
                                        <button type="submit" class="button button-primary"><?php esc_html_e('Generate API Key', 'ecab-taxi-booking-manager'); ?></button>
                                    </p>
                                </form>
                            </div>
                            
                            <div class="api-keys-list">
                                <h3><?php esc_html_e('Existing API Keys', 'ecab-taxi-booking-manager'); ?></h3>
                                <div id="api-keys-container">
                                    <!-- API keys will be loaded here via AJAX -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    
                    <!-- Taxi Management Endpoints -->
                    <?php $this->render_endpoint_section('Taxi Management', $this->get_taxi_endpoints()); ?>
                    
                    <!-- Booking Management Endpoints -->
                    <?php $this->render_endpoint_section('Booking Management', $this->get_booking_endpoints()); ?>
                    
                    <!-- Location Services Endpoints -->
                    <?php $this->render_endpoint_section('Location Services', $this->get_location_endpoints()); ?>
                    
                    <!-- Settings Endpoints -->
                    <?php $this->render_endpoint_section('Settings', $this->get_settings_endpoints()); ?>
                </div>
            </div>
            <?php
        }
        
        private function render_endpoint_section($title, $endpoints) {
            ?>
            <div class="api-section">
                <h2><span class="dashicons dashicons-admin-generic"></span> <?php echo esc_html($title); ?></h2>
                <div class="endpoints-container">
                    <?php foreach ($endpoints as $endpoint): ?>
                        <div class="endpoint-card">
                            <div class="endpoint-header">
                                <span class="method method-<?php echo esc_attr(strtolower($endpoint['method'])); ?>">
                                    <?php echo esc_html($endpoint['method']); ?>
                                </span>
                                <code class="endpoint-url"><?php echo esc_html($endpoint['url']); ?></code>
                            </div>
                            
                            <div class="endpoint-description">
                                <p><?php echo esc_html($endpoint['description']); ?></p>
                            </div>
                            
                            <?php if (!empty($endpoint['parameters'])): ?>
                                <div class="endpoint-parameters">
                                    <h4><?php esc_html_e('Parameters', 'ecab-taxi-booking-manager'); ?></h4>
                                    <table class="parameters-table">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('Parameter', 'ecab-taxi-booking-manager'); ?></th>
                                                <th><?php esc_html_e('Type', 'ecab-taxi-booking-manager'); ?></th>
                                                <th><?php esc_html_e('Required', 'ecab-taxi-booking-manager'); ?></th>
                                                <th><?php esc_html_e('Description', 'ecab-taxi-booking-manager'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($endpoint['parameters'] as $param): ?>
                                                <tr>
                                                    <td><code><?php echo esc_html($param['name']); ?></code></td>
                                                    <td><?php echo esc_html($param['type']); ?></td>
                                                    <td><?php echo $param['required'] ? '<span class="required">Yes</span>' : 'No'; ?></td>
                                                    <td><?php echo esc_html($param['description']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($endpoint['example_request'])): ?>
                                <div class="endpoint-example">
                                    <h4><?php esc_html_e('Example Request', 'ecab-taxi-booking-manager'); ?></h4>
                                    <pre class="code-example"><?php echo esc_html($endpoint['example_request']); ?></pre>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($endpoint['example_response'])): ?>
                                <div class="endpoint-example">
                                    <h4><?php esc_html_e('Example Response', 'ecab-taxi-booking-manager'); ?></h4>
                                    <pre class="code-example"><?php echo esc_html($endpoint['example_response']); ?></pre>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php
        }
        
        private function get_auth_endpoints() {
            return array(
                array(
                    'method' => 'POST',
                    'url' => '/auth/generate-key',
                    'description' => 'Generate a new API key for authentication',
                    'parameters' => array(
                        array('name' => 'name', 'type' => 'string', 'required' => true, 'description' => 'Name for the API key'),
                        array('name' => 'permissions', 'type' => 'array', 'required' => false, 'description' => 'Array of permissions (read, write)')
                    ),
                    'example_request' => 'POST /wp-json/ecab-taxi/v1/auth/generate-key
Content-Type: application/json

{
    "name": "Mobile App",
    "permissions": ["read", "write"]
}',
                    'example_response' => '{
    "success": true,
    "data": {
        "api_key": "etbm_abc123...",
        "api_secret": "secret456...",
        "name": "Mobile App",
        "permissions": ["read", "write"],
        "expires_at": "2025-01-01 00:00:00"
    }
}'
                ),
                array(
                    'method' => 'POST',
                    'url' => '/auth/validate-key',
                    'description' => 'Validate an API key',
                    'parameters' => array(
                        array('name' => 'api_key', 'type' => 'string', 'required' => true, 'description' => 'API key to validate')
                    ),
                    'example_request' => 'POST /wp-json/ecab-taxi/v1/auth/validate-key
Content-Type: application/json

{
    "api_key": "etbm_abc123..."
}',
                    'example_response' => '{
    "success": true,
    "valid": true,
    "data": {
        "user_id": 1,
        "permissions": ["read", "write"],
        "last_used": "2024-01-01 12:00:00",
        "expires_at": "2025-01-01 00:00:00"
    }
}'
                )
            );
        }
        
        private function get_taxi_endpoints() {
            return array(
                array(
                    'method' => 'GET',
                    'url' => '/taxis',
                    'description' => 'Get list of all taxis/vehicles',
                    'parameters' => array(
                        array('name' => 'page', 'type' => 'integer', 'required' => false, 'description' => 'Page number for pagination'),
                        array('name' => 'per_page', 'type' => 'integer', 'required' => false, 'description' => 'Number of items per page (max 100)'),
                        array('name' => 'status', 'type' => 'string', 'required' => false, 'description' => 'Filter by status (active, inactive)')
                    ),
                    'example_request' => 'GET /wp-json/ecab-taxi/v1/taxis?page=1&per_page=10
X-API-Key: your-api-key',
                    'example_response' => '{
    "success": true,
    "data": [
        {
            "id": 123,
            "title": "Sedan Car",
            "description": "Comfortable sedan for 4 passengers",
            "price_per_km": 2.50,
            "max_passengers": 4,
            "max_bags": 3,
            "features": ["AC", "GPS"],
            "status": "active"
        }
    ],
    "pagination": {
        "page": 1,
        "per_page": 10,
        "total": 25,
        "total_pages": 3
    }
}'
                ),
                array(
                    'method' => 'POST',
                    'url' => '/taxis',
                    'description' => 'Create a new taxi/vehicle',
                    'parameters' => array(
                        array('name' => 'title', 'type' => 'string', 'required' => true, 'description' => 'Taxi title'),
                        array('name' => 'description', 'type' => 'string', 'required' => false, 'description' => 'Taxi description'),
                        array('name' => 'price_per_km', 'type' => 'number', 'required' => true, 'description' => 'Price per kilometer'),
                        array('name' => 'max_passengers', 'type' => 'integer', 'required' => true, 'description' => 'Maximum number of passengers'),
                        array('name' => 'max_bags', 'type' => 'integer', 'required' => false, 'description' => 'Maximum number of bags')
                    ),
                    'example_request' => 'POST /wp-json/ecab-taxi/v1/taxis
X-API-Key: your-api-key
Content-Type: application/json

{
    "title": "New SUV",
    "description": "Spacious SUV for families",
    "price_per_km": 3.50,
    "max_passengers": 7,
    "max_bags": 5
}',
                    'example_response' => '{
    "success": true,
    "data": {
        "id": 124,
        "title": "New SUV",
        "status": "active"
    }
}'
                ),
                array(
                    'method' => 'POST',
                    'url' => '/taxis/search',
                    'description' => 'Search for available taxis based on criteria',
                    'parameters' => array(
                        array('name' => 'pickup_location', 'type' => 'string', 'required' => true, 'description' => 'Pickup location'),
                        array('name' => 'dropoff_location', 'type' => 'string', 'required' => false, 'description' => 'Drop-off location'),
                        array('name' => 'pickup_date', 'type' => 'string', 'required' => true, 'description' => 'Pickup date (Y-m-d)'),
                        array('name' => 'pickup_time', 'type' => 'string', 'required' => true, 'description' => 'Pickup time (H:i)'),
                        array('name' => 'passengers', 'type' => 'integer', 'required' => false, 'description' => 'Number of passengers'),
                        array('name' => 'bags', 'type' => 'integer', 'required' => false, 'description' => 'Number of bags')
                    ),
                    'example_request' => 'POST /wp-json/ecab-taxi/v1/taxis/search
X-API-Key: your-api-key
Content-Type: application/json

{
    "pickup_location": "New York Airport",
    "dropoff_location": "Manhattan Hotel",
    "pickup_date": "2024-12-15",
    "pickup_time": "10:30",
    "passengers": 4
}',
                    'example_response' => '{
    "success": true,
    "data": {
        "available_taxis": [...],
        "distance": "25.5 km",
        "estimated_duration": "35 minutes",
        "pricing": {...}
    }
}'
                )
            );
        }
        
        private function get_booking_endpoints() {
            return array(
                array(
                    'method' => 'GET',
                    'url' => '/bookings',
                    'description' => 'Get list of bookings with pagination and filtering',
                    'parameters' => array(
                        array('name' => 'page', 'type' => 'integer', 'required' => false, 'description' => 'Page number for pagination (default: 1)'),
                        array('name' => 'per_page', 'type' => 'integer', 'required' => false, 'description' => 'Items per page (max 100, default: 10)'),
                        array('name' => 'status', 'type' => 'string', 'required' => false, 'description' => 'Filter by booking status (pending, processing, completed, cancelled)'),
                        array('name' => 'user_id', 'type' => 'integer', 'required' => false, 'description' => 'Filter by customer ID')
                    ),
                    'example_response' => '{
    "success": true,
    "data": [
        {
            "id": 123,
            "status": "pending",
            "total": "45.50",
            "customer_name": "John Doe",
            "pickup_location": "Airport",
            "dropoff_location": "Hotel",
            "pickup_date": "2024-12-15",
            "taxi_details": {...}
        }
    ],
    "pagination": {...}
}'
                ),
                array(
                    'method' => 'GET',
                    'url' => '/bookings/{id}',
                    'description' => 'Get detailed information about a specific booking',
                    'parameters' => array(
                        array('name' => 'id', 'type' => 'integer', 'required' => true, 'description' => 'Booking ID')
                    ),
                    'example_response' => '{
    "success": true,
    "data": {
        "id": 123,
        "status": "pending",
        "customer_details": {...},
        "booking_details": {...},
        "taxi_details": {...},
        "payment_details": {...}
    }
}'
                ),
                array(
                    'method' => 'POST',
                    'url' => '/bookings',
                    'description' => 'Create a new booking with full WooCommerce integration',
                    'parameters' => array(
                        array('name' => 'taxi_id', 'type' => 'integer', 'required' => true, 'description' => 'ID of the selected taxi'),
                        array('name' => 'pickup_location', 'type' => 'string', 'required' => true, 'description' => 'Pickup location (min 3 chars)'),
                        array('name' => 'dropoff_location', 'type' => 'string', 'required' => true, 'description' => 'Drop-off location (min 3 chars)'),
                        array('name' => 'pickup_date', 'type' => 'string', 'required' => true, 'description' => 'Pickup date (Y-m-d format)'),
                        array('name' => 'pickup_time', 'type' => 'string', 'required' => true, 'description' => 'Pickup time (H:i format)'),
                        array('name' => 'customer_email', 'type' => 'string', 'required' => true, 'description' => 'Customer email address'),
                        array('name' => 'customer_name', 'type' => 'string', 'required' => false, 'description' => 'Customer full name'),
                        array('name' => 'customer_phone', 'type' => 'string', 'required' => false, 'description' => 'Customer phone number'),
                        array('name' => 'passenger_count', 'type' => 'integer', 'required' => false, 'description' => 'Number of passengers (default: 1)'),
                        array('name' => 'return_date', 'type' => 'string', 'required' => false, 'description' => 'Return date for round trips'),
                        array('name' => 'return_time', 'type' => 'string', 'required' => false, 'description' => 'Return time for round trips'),
                        array('name' => 'distance', 'type' => 'number', 'required' => false, 'description' => 'Distance in kilometers'),
                        array('name' => 'duration', 'type' => 'string', 'required' => false, 'description' => 'Estimated duration')
                    )
                ),
                array(
                    'method' => 'PUT',
                    'url' => '/bookings/{id}',
                    'description' => 'Update an existing booking (only pending/processing bookings)',
                    'parameters' => array(
                        array('name' => 'id', 'type' => 'integer', 'required' => true, 'description' => 'Booking ID'),
                        array('name' => 'customer_email', 'type' => 'string', 'required' => false, 'description' => 'Updated customer email'),
                        array('name' => 'customer_name', 'type' => 'string', 'required' => false, 'description' => 'Updated customer name'),
                        array('name' => 'customer_phone', 'type' => 'string', 'required' => false, 'description' => 'Updated customer phone'),
                        array('name' => 'pickup_location', 'type' => 'string', 'required' => false, 'description' => 'Updated pickup location'),
                        array('name' => 'dropoff_location', 'type' => 'string', 'required' => false, 'description' => 'Updated dropoff location'),
                        array('name' => 'pickup_date', 'type' => 'string', 'required' => false, 'description' => 'Updated pickup date'),
                        array('name' => 'pickup_time', 'type' => 'string', 'required' => false, 'description' => 'Updated pickup time'),
                        array('name' => 'passenger_count', 'type' => 'integer', 'required' => false, 'description' => 'Updated passenger count'),
                        array('name' => 'special_instructions', 'type' => 'string', 'required' => false, 'description' => 'Special instructions')
                    )
                ),
                array(
                    'method' => 'DELETE',
                    'url' => '/bookings/{id}',
                    'description' => 'Cancel a booking with optional refund',
                    'parameters' => array(
                        array('name' => 'id', 'type' => 'integer', 'required' => true, 'description' => 'Booking ID'),
                        array('name' => 'reason', 'type' => 'string', 'required' => false, 'description' => 'Cancellation reason'),
                        array('name' => 'refund_amount', 'type' => 'number', 'required' => false, 'description' => 'Refund amount (if applicable)')
                    )
                ),
                array(
                    'method' => 'PUT',
                    'url' => '/bookings/{id}/status',
                    'description' => 'Update booking status with history tracking',
                    'parameters' => array(
                        array('name' => 'id', 'type' => 'integer', 'required' => true, 'description' => 'Booking ID'),
                        array('name' => 'status', 'type' => 'string', 'required' => true, 'description' => 'New status (pending, processing, on-hold, completed, cancelled, refunded, failed)'),
                        array('name' => 'note', 'type' => 'string', 'required' => false, 'description' => 'Status change note')
                    )
                ),
                array(
                    'method' => 'POST',
                    'url' => '/bookings/calculate-price',
                    'description' => 'Calculate detailed pricing for a potential booking',
                    'parameters' => array(
                        array('name' => 'taxi_id', 'type' => 'integer', 'required' => true, 'description' => 'ID of the taxi'),
                        array('name' => 'pickup_location', 'type' => 'string', 'required' => true, 'description' => 'Pickup location'),
                        array('name' => 'dropoff_location', 'type' => 'string', 'required' => true, 'description' => 'Drop-off location'),
                        array('name' => 'pricing_type', 'type' => 'string', 'required' => false, 'description' => 'Pricing type (distance, hourly, dynamic - default: distance)'),
                        array('name' => 'passenger_count', 'type' => 'integer', 'required' => false, 'description' => 'Number of passengers (default: 1)'),
                        array('name' => 'pickup_date', 'type' => 'string', 'required' => false, 'description' => 'Pickup date for surcharge calculation'),
                        array('name' => 'pickup_time', 'type' => 'string', 'required' => false, 'description' => 'Pickup time for surcharge calculation'),
                        array('name' => 'return_date', 'type' => 'string', 'required' => false, 'description' => 'Return date for round trip pricing'),
                        array('name' => 'hours', 'type' => 'number', 'required' => false, 'description' => 'Hours for hourly pricing')
                    ),
                    'example_response' => '{
    "success": true,
    "data": {
        "pricing_breakdown": {
            "base_price": 25.00,
            "distance_price": 18.50,
            "surcharges": {
                "night_surcharge": 5.00
            },
            "tax": {
                "rate": 10,
                "amount": 4.85
            },
            "total": 53.35
        },
        "currency": "USD"
    }
}'
                )
            );
        }
        
        private function get_location_endpoints() {
            return array(
                array(
                    'method' => 'GET',
                    'url' => '/locations/autocomplete',
                    'description' => 'Get location suggestions for autocomplete',
                    'parameters' => array(
                        array('name' => 'query', 'type' => 'string', 'required' => true, 'description' => 'Search query')
                    )
                ),
                array(
                    'method' => 'POST',
                    'url' => '/locations/distance',
                    'description' => 'Calculate distance between two locations',
                    'parameters' => array(
                        array('name' => 'origin', 'type' => 'string', 'required' => true, 'description' => 'Origin location'),
                        array('name' => 'destination', 'type' => 'string', 'required' => true, 'description' => 'Destination location')
                    )
                )
            );
        }
        
        private function get_settings_endpoints() {
            return array(
                array(
                    'method' => 'GET',
                    'url' => '/settings/pricing',
                    'description' => 'Get pricing settings and rules',
                    'parameters' => array()
                ),
                array(
                    'method' => 'GET',
                    'url' => '/settings/operational',
                    'description' => 'Get operational settings (working hours, buffer time, etc.)',
                    'parameters' => array()
                )
            );
        }
        
        // Helper method to ensure API tables exist
        private function ensure_api_tables_exist() {
            global $wpdb;
            
            $api_keys_table = $wpdb->prefix . 'mptbm_api_keys';
            $api_logs_table = $wpdb->prefix . 'mptbm_api_logs';
            
            $keys_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$api_keys_table}'");
            $logs_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$api_logs_table}'");
            
            if (!$keys_table_exists || !$logs_table_exists) {
                if (method_exists('MPTBM_Plugin', 'create_api_tables')) {
                    MPTBM_Plugin::create_api_tables();
                }
            }
        }
        
        // AJAX Handlers
        public function ajax_generate_api_key() {
            // Verify nonce first
            if (!wp_verify_nonce($_POST['nonce'], 'mptbm_api_nonce')) {
                wp_send_json_error('Security check failed');
            }
            
            // Check user capabilities
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
            }
            
            // Validate and sanitize input
            if (empty($_POST['name'])) {
                wp_send_json_error('API key name is required');
            }
            
            $name = sanitize_text_field($_POST['name']);
            if (strlen($name) > 200) {
                wp_send_json_error('API key name is too long (max 200 characters)');
            }
            
            // Validate permissions
            $permissions = isset($_POST['permissions']) ? (array) $_POST['permissions'] : array('read');
            $allowed_permissions = array('read', 'write');
            $permissions = array_intersect($permissions, $allowed_permissions);
            
            if (empty($permissions)) {
                $permissions = array('read');
            }
            
            // Create API key via REST API class
            if (class_exists('MPTBM_REST_API')) {
                $api_class = new MPTBM_REST_API();
                $request = new WP_REST_Request('POST', '/auth/generate-key');
                $request->set_param('name', $name);
                $request->set_param('permissions', $permissions);
                
                $response = $api_class->generate_api_key_endpoint($request);
                
                if (is_wp_error($response)) {
                    wp_send_json_error($response->get_error_message());
                } else {
                    wp_send_json_success($response->get_data());
                }
            } else {
                wp_send_json_error('REST API class not found');
            }
        }
        
        public function ajax_revoke_api_key() {
            // Verify nonce first
            if (!wp_verify_nonce($_POST['nonce'], 'mptbm_api_nonce')) {
                wp_send_json_error('Security check failed');
            }
            
            // Check user capabilities
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
            }
            
            // Validate input
            if (empty($_POST['api_key'])) {
                wp_send_json_error('API key is required');
            }
            
            $api_key = sanitize_text_field($_POST['api_key']);
            
            // Validate API key format
            if (!preg_match('/^etbm_[a-zA-Z0-9]{32}$/', $api_key)) {
                wp_send_json_error('Invalid API key format');
            }
            
            // Revoke API key via REST API class
            if (class_exists('MPTBM_REST_API')) {
                $api_class = new MPTBM_REST_API();
                $request = new WP_REST_Request('POST', '/auth/revoke-key');
                $request->set_param('api_key', $api_key);
                
                $response = $api_class->revoke_api_key_endpoint($request);
                
                if (is_wp_error($response)) {
                    wp_send_json_error($response->get_error_message());
                } else {
                    wp_send_json_success($response->get_data());
                }
            } else {
                wp_send_json_error('REST API class not found');
            }
        }
        
        public function ajax_get_api_keys() {
            // Verify nonce first
            if (!wp_verify_nonce($_POST['nonce'], 'mptbm_api_nonce')) {
                wp_send_json_error('Security check failed');
            }
            
            // Check user capabilities
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
            }
            
            global $wpdb;
            $api_keys_table = $wpdb->prefix . 'mptbm_api_keys';
            
            // Check if tables exist, create them if they don't
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$api_keys_table}'");
            if (!$table_exists) {
                // Try to create tables
                if (method_exists('MPTBM_Plugin', 'create_api_tables')) {
                    MPTBM_Plugin::create_api_tables();
                } else {
                    wp_send_json_error('API tables not found and could not be created');
                }
            }
            
            // Validate table name to prevent injection
            if (empty($api_keys_table) || !preg_match('/^[a-zA-Z0-9_]+$/', str_replace($wpdb->prefix, '', 'mptbm_api_keys'))) {
                wp_send_json_error('Invalid table name');
            }
            
            $user_id = get_current_user_id();
            if (!$user_id) {
                wp_send_json_error('User not logged in');
            }
            
            $keys = $wpdb->get_results($wpdb->prepare(
                "SELECT id, name, api_key, permissions, status, created_at, last_used, expires_at 
                 FROM {$api_keys_table} 
                 WHERE user_id = %d 
                 ORDER BY created_at DESC 
                 LIMIT 100",
                $user_id
            ));
            
            // Sanitize output
            if (is_array($keys)) {
                foreach ($keys as $key) {
                    $key->name = esc_html($key->name);
                    $key->api_key = esc_html($key->api_key);
                    $key->status = esc_html($key->status);
                }
            }
            
            wp_send_json_success($keys ? $keys : array());
        }
    }
}
