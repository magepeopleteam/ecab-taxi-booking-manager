<?php
/*
 * @Author: MagePeople Team
 * Copyright: mage-people.com
 */
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_REST_API')) {
    class MPTBM_REST_API {
        
        private $namespace = 'ecab-taxi/v1';
        private $api_keys_table;
        private $api_logs_table;
        
        public function __construct() {
            // Initialize table names first
            $this->init_table_names();
            
            add_action('rest_api_init', array($this, 'register_routes'));
            add_action('init', array($this, 'ensure_database_tables'));
            add_action('wp_ajax_mptbm_generate_api_key', array($this, 'generate_api_key'));
            add_action('wp_ajax_mptbm_revoke_api_key', array($this, 'revoke_api_key'));
            add_filter('rest_pre_dispatch', array($this, 'check_api_permissions'), 10, 3);
            
            // Add CORS support
            add_action('rest_api_init', array($this, 'add_cors_support'));
            
            // Cleanup old logs daily
            add_action('wp_daily_cron', array($this, 'cleanup_old_api_logs'));
            if (!wp_next_scheduled('wp_daily_cron')) {
                wp_schedule_event(time(), 'daily', 'wp_daily_cron');
            }
        }
        
        private function init_table_names() {
            global $wpdb;
            
            $this->api_keys_table = $wpdb->prefix . 'mptbm_api_keys';
            $this->api_logs_table = $wpdb->prefix . 'mptbm_api_logs';
        }
        
        public function ensure_database_tables() {
            global $wpdb;
            
            // Ensure table names are set
            if (empty($this->api_keys_table) || empty($this->api_logs_table)) {
                $this->init_table_names();
            }
            
            // Check if tables exist, create them if they don't
            $keys_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->api_keys_table}'");
            $logs_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->api_logs_table}'");
            
            if (!$keys_table_exists || !$logs_table_exists) {
                // Use main plugin method if available, otherwise create locally
                if (method_exists('MPTBM_Plugin', 'create_api_tables')) {
                    MPTBM_Plugin::create_api_tables();
                } else {
                    $this->create_api_tables();
                }
            }
        }
        
        private function create_api_tables() {
            global $wpdb;
            
            $charset_collate = $wpdb->get_charset_collate();
            
            // API Keys table
            $api_keys_sql = "CREATE TABLE {$this->api_keys_table} (
                id int(11) NOT NULL AUTO_INCREMENT,
                user_id int(11) NOT NULL,
                api_key varchar(64) NOT NULL,
                api_secret varchar(64) NOT NULL,
                name varchar(200) NOT NULL,
                permissions text,
                last_used datetime DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                expires_at datetime DEFAULT NULL,
                status enum('active','revoked') DEFAULT 'active',
                PRIMARY KEY (id),
                UNIQUE KEY api_key (api_key),
                KEY user_id (user_id),
                KEY status (status)
            ) $charset_collate;";
            
            // API Logs table
            $api_logs_sql = "CREATE TABLE {$this->api_logs_table} (
                id int(11) NOT NULL AUTO_INCREMENT,
                api_key_id int(11) DEFAULT NULL,
                endpoint varchar(255) NOT NULL,
                method varchar(10) NOT NULL,
                request_data text,
                response_code int(3) NOT NULL,
                response_data text,
                ip_address varchar(45) NOT NULL,
                user_agent text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY api_key_id (api_key_id),
                KEY endpoint (endpoint),
                KEY created_at (created_at)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($api_keys_sql);
            dbDelta($api_logs_sql);
        }
        
        public function register_routes() {
            $settings = MP_Global_Function::get_settings('mptbm_rest_api_settings', 'enable_rest_api', 'no');
            
            if ($settings !== 'yes') {
                return;
            }
            
            // Authentication routes
            register_rest_route($this->namespace, '/auth/generate-key', array(
                'methods' => 'POST',
                'callback' => array($this, 'generate_api_key_endpoint'),
                'permission_callback' => array($this, 'check_admin_permissions'),
                'args' => array(
                    'name' => array(
                        'required' => true,
                        'type' => 'string',
                        'description' => 'Name for the API key'
                    ),
                    'permissions' => array(
                        'required' => false,
                        'type' => 'array',
                        'description' => 'Array of permissions'
                    )
                )
            ));
            
            register_rest_route($this->namespace, '/auth/revoke-key', array(
                'methods' => 'POST',
                'callback' => array($this, 'revoke_api_key_endpoint'),
                'permission_callback' => array($this, 'check_admin_permissions'),
                'args' => array(
                    'api_key' => array(
                        'required' => true,
                        'type' => 'string',
                        'description' => 'API key to revoke'
                    )
                )
            ));
            
            register_rest_route($this->namespace, '/auth/validate-key', array(
                'methods' => array('GET', 'POST'),
                'callback' => array($this, 'validate_api_key_endpoint'),
                'permission_callback' => '__return_true',
                'args' => array(
                    'api_key' => array(
                        'required' => true,
                        'type' => 'string'
                    )
                )
            ));
            
            // Taxi management routes
            register_rest_route($this->namespace, '/taxis', array(
                array(
                    'methods' => 'GET',
                    'callback' => array($this, 'get_taxis'),
                    'permission_callback' => array($this, 'check_read_permissions')
                ),
                array(
                    'methods' => 'POST',
                    'callback' => array($this, 'create_taxi'),
                    'permission_callback' => array($this, 'check_write_permissions')
                )
            ));
            
            register_rest_route($this->namespace, '/taxis/(?P<id>\d+)', array(
                array(
                    'methods' => 'GET',
                    'callback' => array($this, 'get_taxi'),
                    'permission_callback' => array($this, 'check_read_permissions')
                ),
                array(
                    'methods' => 'PUT',
                    'callback' => array($this, 'update_taxi'),
                    'permission_callback' => array($this, 'check_write_permissions')
                ),
                array(
                    'methods' => 'DELETE',
                    'callback' => array($this, 'delete_taxi'),
                    'permission_callback' => array($this, 'check_write_permissions')
                )
            ));
            
            register_rest_route($this->namespace, '/taxis/(?P<id>\d+)/availability', array(
                'methods' => 'GET',
                'callback' => array($this, 'check_taxi_availability'),
                'permission_callback' => array($this, 'check_read_permissions')
            ));
            
            register_rest_route($this->namespace, '/taxis/search', array(
                'methods' => array('GET', 'POST'),
                'callback' => array($this, 'search_taxis'),
                'permission_callback' => array($this, 'check_read_permissions')
            ));
            
            // Booking management routes
            register_rest_route($this->namespace, '/bookings', array(
                array(
                    'methods' => 'GET',
                    'callback' => array($this, 'get_bookings'),
                    'permission_callback' => array($this, 'check_read_permissions')
                ),
                array(
                    'methods' => 'POST',
                    'callback' => array($this, 'create_booking'),
                    'permission_callback' => array($this, 'check_write_permissions')
                )
            ));
            
            register_rest_route($this->namespace, '/bookings/(?P<id>\d+)', array(
                array(
                    'methods' => 'GET',
                    'callback' => array($this, 'get_booking'),
                    'permission_callback' => array($this, 'check_read_permissions')
                ),
                array(
                    'methods' => 'PUT',
                    'callback' => array($this, 'update_booking'),
                    'permission_callback' => array($this, 'check_write_permissions')
                ),
                array(
                    'methods' => 'DELETE',
                    'callback' => array($this, 'cancel_booking'),
                    'permission_callback' => array($this, 'check_write_permissions')
                )
            ));
            
            register_rest_route($this->namespace, '/bookings/(?P<id>\d+)/status', array(
                'methods' => 'PUT',
                'callback' => array($this, 'update_booking_status'),
                'permission_callback' => array($this, 'check_write_permissions')
            ));
            
            register_rest_route($this->namespace, '/bookings/calculate-price', array(
                'methods' => 'POST',
                'callback' => array($this, 'calculate_booking_price'),
                'permission_callback' => array($this, 'check_read_permissions')
            ));
            
            // Location and mapping routes
            register_rest_route($this->namespace, '/locations/autocomplete', array(
                'methods' => 'GET',
                'callback' => array($this, 'location_autocomplete'),
                'permission_callback' => array($this, 'check_read_permissions')
            ));
            
            register_rest_route($this->namespace, '/locations/distance', array(
                'methods' => 'POST',
                'callback' => array($this, 'calculate_distance'),
                'permission_callback' => array($this, 'check_read_permissions')
            ));
            
            register_rest_route($this->namespace, '/locations/routes', array(
                'methods' => 'POST',
                'callback' => array($this, 'get_route_information'),
                'permission_callback' => array($this, 'check_read_permissions')
            ));
            
            // Settings routes
            register_rest_route($this->namespace, '/settings/pricing', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_pricing_settings'),
                'permission_callback' => array($this, 'check_read_permissions')
            ));
            
            register_rest_route($this->namespace, '/settings/operational', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_operational_settings'),
                'permission_callback' => array($this, 'check_read_permissions')
            ));
            
            register_rest_route($this->namespace, '/settings/zones', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_operation_zones'),
                'permission_callback' => array($this, 'check_read_permissions')
            ));
        }
        
        // Authentication methods
        public function generate_api_key_endpoint($request) {
            // Additional security check
            if (!current_user_can('manage_options')) {
                return new WP_Error('insufficient_permissions', 'Insufficient permissions to generate API keys', array('status' => 403));
            }
            
            $name = sanitize_text_field($request->get_param('name'));
            
            // Validate name
            if (empty($name) || strlen($name) > 200) {
                return new WP_Error('invalid_name', 'API key name must be between 1 and 200 characters', array('status' => 400));
            }
            
            // Validate and sanitize permissions
            $allowed_permissions = array('read', 'write');
            $permissions = $request->get_param('permissions');
            
            if (!is_array($permissions)) {
                $permissions = array('read');
            }
            
            $permissions = array_intersect($permissions, $allowed_permissions);
            if (empty($permissions)) {
                $permissions = array('read');
            }
            
            // Check if user has permission to create API keys with these capabilities
            $allowed_roles = MP_Global_Function::get_settings('mptbm_rest_api_settings', 'allowed_user_roles', array('administrator'));
            if (!is_array($allowed_roles)) {
                $allowed_roles = array('administrator');
            }
            
            $user = wp_get_current_user();
            $user_roles = $user->roles;
            $has_permission = false;
            
            foreach ($user_roles as $role) {
                if (in_array($role, $allowed_roles)) {
                    $has_permission = true;
                    break;
                }
            }
            
            if (!$has_permission) {
                return new WP_Error('insufficient_role', 'Your user role is not permitted to generate API keys', array('status' => 403));
            }
            
            // Check rate limiting for API key creation (max 5 keys per hour per user)
            if ($this->is_api_key_creation_rate_limited(get_current_user_id())) {
                return new WP_Error('rate_limited', 'Too many API keys created recently. Please wait before creating more.', array('status' => 429));
            }
            
            $api_key = $this->create_api_key(get_current_user_id(), $name, $permissions);
            
            if ($api_key) {
                return new WP_REST_Response(array(
                    'success' => true,
                    'data' => $api_key
                ), 201);
            }
            
            return new WP_Error('api_key_creation_failed', 'Failed to create API key', array('status' => 500));
        }
        
        public function revoke_api_key_endpoint($request) {
            // Security check
            if (!current_user_can('manage_options')) {
                return new WP_Error('insufficient_permissions', 'Insufficient permissions to revoke API keys', array('status' => 403));
            }
            
            $api_key = sanitize_text_field($request->get_param('api_key'));
            
            // Validate API key format
            if (empty($api_key) || !preg_match('/^etbm_[a-zA-Z0-9]{32}$/', $api_key)) {
                return new WP_Error('invalid_api_key', 'Invalid API key format', array('status' => 400));
            }
            
            // Check if user owns this API key or is admin
            if (!$this->user_can_manage_api_key($api_key, get_current_user_id())) {
                return new WP_Error('unauthorized', 'You can only revoke your own API keys', array('status' => 403));
            }
            
            $result = $this->revoke_api_key($api_key);
            
            if ($result) {
                return new WP_REST_Response(array(
                    'success' => true,
                    'message' => 'API key revoked successfully'
                ), 200);
            }
            
            return new WP_Error('api_key_revoke_failed', 'Failed to revoke API key', array('status' => 500));
        }
        
        public function validate_api_key_endpoint($request) {
            $api_key = sanitize_text_field($request->get_param('api_key'));
            
            // Validate API key format
            if (empty($api_key) || !preg_match('/^etbm_[a-zA-Z0-9]{32}$/', $api_key)) {
                return new WP_REST_Response(array(
                    'success' => true,
                    'valid' => false,
                    'message' => 'Invalid API key format'
                ), 200);
            }
            
            $key_data = $this->validate_api_key($api_key);
            
            if ($key_data) {
                return new WP_REST_Response(array(
                    'success' => true,
                    'valid' => true,
                    'data' => array(
                        'user_id' => $key_data['user_id'],
                        'permissions' => json_decode($key_data['permissions'], true),
                        'last_used' => $key_data['last_used'],
                        'expires_at' => $key_data['expires_at']
                    )
                ), 200);
            }
            
            return new WP_REST_Response(array(
                'success' => true,
                'valid' => false
            ), 200);
        }
        
        private function is_api_key_creation_rate_limited($user_id) {
            global $wpdb;
            
            // Ensure table names are initialized
            if (empty($this->api_keys_table)) {
                $this->init_table_names();
            }
            
            $user_id = absint($user_id);
            if ($user_id <= 0) {
                return true;
            }
            
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->api_keys_table} 
                 WHERE user_id = %d 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
                $user_id
            ));
            
            return ($count >= 5); // Max 5 API keys per hour
        }
        
        private function user_can_manage_api_key($api_key, $user_id) {
            global $wpdb;
            
            // Ensure table names are initialized
            if (empty($this->api_keys_table)) {
                $this->init_table_names();
            }
            
            // Admins can manage all keys
            if (current_user_can('manage_options')) {
                return true;
            }
            
            // Check if user owns the key
            $key_owner = $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM {$this->api_keys_table} WHERE api_key = %s",
                $api_key
            ));
            
            return ($key_owner == $user_id);
        }
        
        private function create_api_key($user_id, $name, $permissions) {
            global $wpdb;
            
            // Ensure table names are initialized
            if (empty($this->api_keys_table)) {
                $this->init_table_names();
            }
            
            // Ensure tables exist
            $this->ensure_database_tables();
            
            // Validate and sanitize inputs
            $user_id = absint($user_id);
            if ($user_id <= 0) {
                return false;
            }
            
            $name = sanitize_text_field($name);
            if (strlen($name) > 200) {
                $name = substr($name, 0, 200);
            }
            
            // Validate permissions array
            $allowed_permissions = array('read', 'write');
            if (!is_array($permissions)) {
                $permissions = array('read');
            }
            $permissions = array_intersect($permissions, $allowed_permissions);
            if (empty($permissions)) {
                $permissions = array('read');
            }
            
            $api_key = 'etbm_' . wp_generate_password(32, false);
            $api_secret = wp_generate_password(32, false);
            $expiry_days = absint(MP_Global_Function::get_settings('mptbm_rest_api_settings', 'api_key_expiry', 365));
            $expires_at = $expiry_days > 0 ? gmdate('Y-m-d H:i:s', strtotime("+{$expiry_days} days")) : null;
            
            $result = $wpdb->insert(
                $this->api_keys_table,
                array(
                    'user_id' => $user_id,
                    'api_key' => $api_key,
                    'api_secret' => $api_secret,
                    'name' => $name,
                    'permissions' => json_encode($permissions),
                    'expires_at' => $expires_at,
                    'status' => 'active'
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
            );
            
            if ($result) {
                return array(
                    'api_key' => $api_key,
                    'api_secret' => $api_secret,
                    'name' => $name,
                    'permissions' => $permissions,
                    'expires_at' => $expires_at
                );
            }
            
            return false;
        }
        
        private function validate_api_key($api_key) {
            global $wpdb;
            
            // Ensure table names are initialized
            if (empty($this->api_keys_table)) {
                $this->init_table_names();
            }
            
            $key_data = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->api_keys_table} 
                 WHERE api_key = %s 
                 AND status = 'active' 
                 AND (expires_at IS NULL OR expires_at > NOW())",
                $api_key
            ), ARRAY_A);
            
            if ($key_data) {
                // Update last used timestamp
                $wpdb->update(
                    $this->api_keys_table,
                    array('last_used' => current_time('mysql')),
                    array('id' => $key_data['id'])
                );
                
                return $key_data;
            }
            
            return false;
        }
        
        private function revoke_api_key($api_key) {
            global $wpdb;
            
            // Ensure table names are initialized
            if (empty($this->api_keys_table)) {
                $this->init_table_names();
            }
            
            return $wpdb->update(
                $this->api_keys_table,
                array('status' => 'revoked'),
                array('api_key' => $api_key)
            );
        }
        
        // Permission checking methods
        public function check_api_permissions($result, $server, $request) {
            // Only check for our API routes
            if (strpos($request->get_route(), '/' . $this->namespace . '/') !== 0) {
                return $result;
            }
            
            // Skip auth routes
            if (strpos($request->get_route(), '/' . $this->namespace . '/auth/') === 0) {
                return $result;
            }
            
            $api_key = $this->get_api_key_from_request($request);
            
            if (!$api_key) {
                return new WP_Error('missing_api_key', 'API key is required', array('status' => 401));
            }
            
            $key_data = $this->validate_api_key($api_key);
            
            if (!$key_data) {
                return new WP_Error('invalid_api_key', 'Invalid or expired API key', array('status' => 401));
            }
            
            // Check rate limiting
            if ($this->is_rate_limited($key_data['id'])) {
                return new WP_Error('rate_limited', 'Rate limit exceeded', array('status' => 429));
            }
            
            // Log the request
            $this->log_api_request($key_data['id'], $request);
            
            return $result;
        }
        
        private function get_api_key_from_request($request) {
            $api_key = null;
            
            // Check for API key in header
            $header_key = $request->get_header('X-API-Key');
            if (!empty($header_key)) {
                $api_key = sanitize_text_field($header_key);
            }
            
            // Fallback to query parameter if header not present
            if (empty($api_key)) {
                $param_key = $request->get_param('api_key');
                if (!empty($param_key)) {
                    $api_key = sanitize_text_field($param_key);
                }
            }
            
            // Validate API key format
            if (!empty($api_key) && !preg_match('/^etbm_[a-zA-Z0-9]{32}$/', $api_key)) {
                return null;
            }
            
            return $api_key;
        }
        
        private function is_rate_limited($api_key_id) {
            $rate_limit_enabled = MP_Global_Function::get_settings('mptbm_rest_api_settings', 'rate_limit_enabled', 'yes');
            
            if ($rate_limit_enabled !== 'yes') {
                return false;
            }
            
            $limit = MP_Global_Function::get_settings('mptbm_rest_api_settings', 'rate_limit_requests', 100);
            
            global $wpdb;
            
            // Ensure table names are initialized
            if (empty($this->api_logs_table)) {
                $this->init_table_names();
            }
            
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->api_logs_table} 
                 WHERE api_key_id = %d 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
                $api_key_id
            ));
            
            return $count >= $limit;
        }
        
        private function log_api_request($api_key_id, $request) {
            $logging_enabled = MP_Global_Function::get_settings('mptbm_rest_api_settings', 'api_logging', 'yes');
            
            if ($logging_enabled !== 'yes') {
                return;
            }
            
            global $wpdb;
            
            // Ensure table names are initialized
            if (empty($this->api_logs_table)) {
                $this->init_table_names();
            }
            
            // Sanitize and limit data to prevent DoS attacks
            $api_key_id = absint($api_key_id);
            $endpoint = sanitize_text_field($request->get_route());
            $method = sanitize_text_field($request->get_method());
            $ip_address = $this->get_client_ip();
            $user_agent = sanitize_text_field(substr($request->get_header('User-Agent') ?: '', 0, 500));
            
            // Sanitize request data and limit size
            $request_params = $request->get_params();
            $sanitized_params = array();
            
            foreach ($request_params as $key => $value) {
                $sanitized_key = sanitize_key($key);
                if (is_array($value)) {
                    $sanitized_params[$sanitized_key] = array_map('sanitize_text_field', $value);
                } else {
                    $sanitized_params[$sanitized_key] = sanitize_text_field($value);
                }
            }
            
            $request_data = wp_json_encode($sanitized_params);
            
            // Limit request data size to prevent DoS
            if (strlen($request_data) > 10000) {
                $request_data = substr($request_data, 0, 10000) . '...[truncated]';
            }
            
            $wpdb->insert(
                $this->api_logs_table,
                array(
                    'api_key_id' => $api_key_id,
                    'endpoint' => $endpoint,
                    'method' => $method,
                    'request_data' => $request_data,
                    'response_code' => 200,
                    'ip_address' => $ip_address,
                    'user_agent' => $user_agent
                ),
                array('%d', '%s', '%s', '%s', '%d', '%s', '%s')
            );
        }
        
        private function get_client_ip() {
            // Start with REMOTE_ADDR as it's most reliable
            $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
            
            // Check proxy headers only if behind trusted proxy
            $proxy_headers = array(
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_REAL_IP',
                'HTTP_CLIENT_IP'
            );
            
            foreach ($proxy_headers as $header) {
                if (!empty($_SERVER[$header])) {
                    $proxy_ip = $_SERVER[$header];
                    
                    // Handle comma-separated IPs
                    if (strpos($proxy_ip, ',') !== false) {
                        $ip_list = explode(',', $proxy_ip);
                        $proxy_ip = trim($ip_list[0]);
                    }
                    
                    // Validate IP format
                    if (filter_var($proxy_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        $ip = $proxy_ip;
                        break;
                    }
                }
            }
            
            // Final validation and sanitization
            $ip = filter_var($ip, FILTER_VALIDATE_IP);
            return $ip ? $ip : '0.0.0.0';
        }
        
        public function check_admin_permissions($request) {
            return current_user_can('manage_options');
        }
        
        public function check_read_permissions($request) {
            return true; // Will be handled by API key validation
        }
        
        public function check_write_permissions($request) {
            return true; // Will be handled by API key validation
        }
        
        public function add_cors_support() {
            $cors_enabled = MP_Global_Function::get_settings('mptbm_rest_api_settings', 'cors_enabled', 'yes');
            
            if ($cors_enabled !== 'yes') {
                return;
            }
            
            $allowed_origins = MP_Global_Function::get_settings('mptbm_rest_api_settings', 'cors_allowed_origins', '*');
            
            remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
            add_filter('rest_pre_serve_request', function ($value) use ($allowed_origins) {
                header('Access-Control-Allow-Origin: ' . $allowed_origins);
                header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
                header('Access-Control-Allow-Headers: Authorization, Content-Type, X-API-Key');
                header('Access-Control-Allow-Credentials: true');
                header('Content-Type: application/json; charset=utf-8');
                
                return $value;
            });
        }
        
        // Taxi operations
        public function get_taxis($request) {
            // Get request parameters
            $page = $request->get_param('page') ? absint($request->get_param('page')) : 1;
            $per_page = $request->get_param('per_page') ? absint($request->get_param('per_page')) : 10;
            $status = $request->get_param('status') ? sanitize_text_field($request->get_param('status')) : 'publish';
            
            // Ensure per_page is reasonable
            $per_page = min($per_page, 100); // Max 100 per page
            
            // Query for transportation posts
            $args = array(
                'post_type' => MPTBM_Function::get_cpt(), // 'mptbm_rent'
                'post_status' => $status,
                'posts_per_page' => $per_page,
                'paged' => $page,
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => 'mptbm_rent_type',
                        'value' => 'taxi',
                        'compare' => 'LIKE'
                    ),
                    array(
                        'key' => 'mptbm_rent_type',
                        'compare' => 'NOT EXISTS'
                    )
                )
            );
            
            $query = new WP_Query($args);
            $taxis = array();
            
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    
                    // Get taxi details
                    $taxi_data = array(
                        'id' => $post_id,
                        'title' => get_the_title(),
                        'description' => get_the_excerpt(),
                        'status' => get_post_status(),
                        'featured_image' => get_the_post_thumbnail_url($post_id, 'full'),
                        'price' => MP_Global_Function::get_post_info($post_id, 'mptbm_rent_price', 0),
                        'max_passengers' => MPTBM_Function::get_feature_passenger($post_id),
                        'max_bags' => MPTBM_Function::get_feature_bag($post_id),
                        'rent_type' => MP_Global_Function::get_post_info($post_id, 'mptbm_rent_type', 'taxi'),
                        'created_date' => get_the_date('Y-m-d H:i:s'),
                        'permalink' => get_permalink($post_id)
                    );
                    
                    // Get categories
                    $categories = get_the_terms($post_id, MPTBM_Function::get_category_slug());
                    if ($categories && !is_wp_error($categories)) {
                        $taxi_data['categories'] = array_map(function($cat) {
                            return array(
                                'id' => $cat->term_id,
                                'name' => $cat->name,
                                'slug' => $cat->slug
                            );
                        }, $categories);
                    } else {
                        $taxi_data['categories'] = array();
                    }
                    
                    $taxis[] = $taxi_data;
                }
                wp_reset_postdata();
            }
            
            // Prepare response with pagination info
            $response_data = array(
                'success' => true,
                'data' => $taxis,
                'pagination' => array(
                    'page' => $page,
                    'per_page' => $per_page,
                    'total_items' => $query->found_posts,
                    'total_pages' => $query->max_num_pages
                ),
                'message' => sprintf(
                    'Found %d taxis (page %d of %d)',
                    count($taxis),
                    $page,
                    $query->max_num_pages
                )
            );
            
            return new WP_REST_Response($response_data, 200);
        }
        
        public function create_taxi($request) {
            // Validate required parameters
            $required_params = array('title', 'price');
            
            foreach ($required_params as $param) {
                if (!$request->get_param($param)) {
                    return new WP_Error('missing_parameter', "Missing required parameter: {$param}", array('status' => 400));
                }
            }
            
            // Sanitize input data
            $title = sanitize_text_field($request->get_param('title'));
            $description = sanitize_textarea_field($request->get_param('description'));
            $price = floatval($request->get_param('price'));
            $max_passengers = absint($request->get_param('max_passengers')) ?: 4;
            $max_bags = absint($request->get_param('max_bags')) ?: 2;
            $featured_image_id = absint($request->get_param('featured_image_id'));
            $categories = $request->get_param('categories') ? array_map('absint', (array) $request->get_param('categories')) : array();
            
            // Create the taxi post
            $taxi_id = wp_insert_post(array(
                'post_type' => MPTBM_Function::get_cpt(),
                'post_title' => $title,
                'post_content' => $description,
                'post_status' => 'publish',
                'post_author' => get_current_user_id()
            ));
            
            if (is_wp_error($taxi_id)) {
                return new WP_Error('taxi_creation_failed', 'Failed to create taxi', array('status' => 500));
            }
            
            // Set taxi metadata
            update_post_meta($taxi_id, 'mptbm_rent_price', $price);
            update_post_meta($taxi_id, 'mptbm_maximum_passenger', $max_passengers);
            update_post_meta($taxi_id, 'mptbm_maximum_bag', $max_bags);
            update_post_meta($taxi_id, 'mptbm_rent_type', 'taxi');
            
            // Set featured image if provided
            if ($featured_image_id) {
                set_post_thumbnail($taxi_id, $featured_image_id);
            }
            
            // Set categories if provided
            if (!empty($categories)) {
                wp_set_object_terms($taxi_id, $categories, MPTBM_Function::get_category_slug());
            }
            
            // Prepare response
            $response_data = array(
                'success' => true,
                'data' => array(
                    'id' => $taxi_id,
                    'title' => $title,
                    'price' => $price,
                    'max_passengers' => $max_passengers,
                    'max_bags' => $max_bags,
                    'permalink' => get_permalink($taxi_id)
                ),
                'message' => 'Taxi created successfully'
            );
            
            return new WP_REST_Response($response_data, 201);
        }
        
        public function get_taxi($request) {
            $taxi_id = $request->get_param('id');
            
            if (!$taxi_id) {
                return new WP_Error('missing_id', 'Taxi ID is required', array('status' => 400));
            }
            
            $taxi_post = get_post(absint($taxi_id));
            
            if (!$taxi_post || $taxi_post->post_type !== MPTBM_Function::get_cpt()) {
                return new WP_Error('taxi_not_found', 'Taxi not found', array('status' => 404));
            }
            
            // Get taxi details
            $taxi_data = array(
                'id' => $taxi_post->ID,
                'title' => $taxi_post->post_title,
                'description' => $taxi_post->post_content,
                'excerpt' => $taxi_post->post_excerpt,
                'status' => $taxi_post->post_status,
                'featured_image' => get_the_post_thumbnail_url($taxi_post->ID, 'full'),
                'price' => MP_Global_Function::get_post_info($taxi_post->ID, 'mptbm_rent_price', 0),
                'max_passengers' => MPTBM_Function::get_feature_passenger($taxi_post->ID),
                'max_bags' => MPTBM_Function::get_feature_bag($taxi_post->ID),
                'rent_type' => MP_Global_Function::get_post_info($taxi_post->ID, 'mptbm_rent_type', 'taxi'),
                'created_date' => $taxi_post->post_date,
                'modified_date' => $taxi_post->post_modified,
                'permalink' => get_permalink($taxi_post->ID),
                'schedule' => MPTBM_Function::get_schedule($taxi_post->ID)
            );
            
            // Get categories
            $categories = get_the_terms($taxi_post->ID, MPTBM_Function::get_category_slug());
            if ($categories && !is_wp_error($categories)) {
                $taxi_data['categories'] = array_map(function($cat) {
                    return array(
                        'id' => $cat->term_id,
                        'name' => $cat->name,
                        'slug' => $cat->slug
                    );
                }, $categories);
            } else {
                $taxi_data['categories'] = array();
            }
            
            $response_data = array(
                'success' => true,
                'data' => $taxi_data
            );
            
            return new WP_REST_Response($response_data, 200);
        }
        
        public function update_taxi($request) {
            $taxi_id = $request->get_param('id');
            
            if (!$taxi_id) {
                return new WP_Error('missing_id', 'Taxi ID is required', array('status' => 400));
            }
            
            $taxi_post = get_post(absint($taxi_id));
            
            if (!$taxi_post || $taxi_post->post_type !== MPTBM_Function::get_cpt()) {
                return new WP_Error('taxi_not_found', 'Taxi not found', array('status' => 404));
            }
            
            // Prepare update data
            $update_data = array(
                'ID' => $taxi_id
            );
            
            // Update fields if provided
            if ($request->get_param('title')) {
                $update_data['post_title'] = sanitize_text_field($request->get_param('title'));
            }
            
            if ($request->get_param('description')) {
                $update_data['post_content'] = sanitize_textarea_field($request->get_param('description'));
            }
            
            if ($request->get_param('status')) {
                $status = sanitize_text_field($request->get_param('status'));
                if (in_array($status, array('publish', 'draft', 'private'))) {
                    $update_data['post_status'] = $status;
                }
            }
            
            // Update the post
            $result = wp_update_post($update_data);
            
            if (is_wp_error($result)) {
                return new WP_Error('update_failed', 'Failed to update taxi', array('status' => 500));
            }
            
            // Update metadata if provided
            if ($request->get_param('price') !== null) {
                update_post_meta($taxi_id, 'mptbm_rent_price', floatval($request->get_param('price')));
            }
            
            if ($request->get_param('max_passengers') !== null) {
                update_post_meta($taxi_id, 'mptbm_maximum_passenger', absint($request->get_param('max_passengers')));
            }
            
            if ($request->get_param('max_bags') !== null) {
                update_post_meta($taxi_id, 'mptbm_maximum_bag', absint($request->get_param('max_bags')));
            }
            
            // Update featured image if provided
            if ($request->get_param('featured_image_id') !== null) {
                $image_id = absint($request->get_param('featured_image_id'));
                if ($image_id > 0) {
                    set_post_thumbnail($taxi_id, $image_id);
                } else {
                    delete_post_thumbnail($taxi_id);
                }
            }
            
            // Update categories if provided
            if ($request->get_param('categories') !== null) {
                $categories = array_map('absint', (array) $request->get_param('categories'));
                wp_set_object_terms($taxi_id, $categories, MPTBM_Function::get_category_slug());
            }
            
            $response_data = array(
                'success' => true,
                'message' => 'Taxi updated successfully',
                'data' => array(
                    'id' => $taxi_id,
                    'permalink' => get_permalink($taxi_id)
                )
            );
            
            return new WP_REST_Response($response_data, 200);
        }
        
        public function delete_taxi($request) {
            $taxi_id = $request->get_param('id');
            
            if (!$taxi_id) {
                return new WP_Error('missing_id', 'Taxi ID is required', array('status' => 400));
            }
            
            $taxi_post = get_post(absint($taxi_id));
            
            if (!$taxi_post || $taxi_post->post_type !== MPTBM_Function::get_cpt()) {
                return new WP_Error('taxi_not_found', 'Taxi not found', array('status' => 404));
            }
            
            // Check if taxi has active bookings
            global $wpdb;
            $active_bookings = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} 
                 WHERE meta_key = 'mptbm_taxi_id' 
                 AND meta_value = %d 
                 AND post_id IN (
                     SELECT ID FROM {$wpdb->posts} 
                     WHERE post_status IN ('wc-pending', 'wc-processing', 'wc-on-hold', 'pending', 'processing')
                 )",
                $taxi_id
            ));
            
            if ($active_bookings > 0) {
                return new WP_Error('taxi_has_bookings', 'Cannot delete taxi with active bookings', array('status' => 409));
            }
            
            // Delete the taxi
            $result = wp_delete_post($taxi_id, true);
            
            if (!$result) {
                return new WP_Error('delete_failed', 'Failed to delete taxi', array('status' => 500));
            }
            
            $response_data = array(
                'success' => true,
                'message' => 'Taxi deleted successfully',
                'data' => array(
                    'id' => $taxi_id
                )
            );
            
            return new WP_REST_Response($response_data, 200);
        }
        
        public function check_taxi_availability($request) {
            $taxi_id = $request->get_param('id');
            $pickup_date = sanitize_text_field($request->get_param('pickup_date'));
            $pickup_time = sanitize_text_field($request->get_param('pickup_time'));
            $return_date = sanitize_text_field($request->get_param('return_date'));
            $return_time = sanitize_text_field($request->get_param('return_time'));
            
            if (!$taxi_id) {
                return new WP_Error('missing_id', 'Taxi ID is required', array('status' => 400));
            }
            
            if (!$pickup_date) {
                return new WP_Error('missing_date', 'Pickup date is required', array('status' => 400));
            }
            
            $taxi_post = get_post(absint($taxi_id));
            
            if (!$taxi_post || $taxi_post->post_type !== MPTBM_Function::get_cpt()) {
                return new WP_Error('taxi_not_found', 'Taxi not found', array('status' => 404));
            }
            
            // Check if taxi is published
            if ($taxi_post->post_status !== 'publish') {
                return new WP_REST_Response(array(
                    'success' => true,
                    'available' => false,
                    'reason' => 'Taxi is not available'
                ), 200);
            }
            
            // Check for conflicting bookings
            global $wpdb;
            $pickup_datetime = $pickup_date . ' ' . ($pickup_time ?: '00:00:00');
            $return_datetime = $return_date ? ($return_date . ' ' . ($return_time ?: '23:59:59')) : $pickup_datetime;
            
            $conflicting_bookings = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} pm1
                 INNER JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
                 INNER JOIN {$wpdb->postmeta} pm3 ON pm1.post_id = pm3.post_id
                 INNER JOIN {$wpdb->posts} p ON pm1.post_id = p.ID
                 WHERE pm1.meta_key = 'mptbm_taxi_id' AND pm1.meta_value = %d
                 AND pm2.meta_key = 'mptbm_pickup_date'
                 AND pm3.meta_key = 'mptbm_pickup_time'
                 AND p.post_status IN ('wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed', 'pending', 'processing', 'completed')
                 AND CONCAT(pm2.meta_value, ' ', IFNULL(pm3.meta_value, '00:00:00')) BETWEEN %s AND %s",
                $taxi_id,
                $pickup_datetime,
                $return_datetime
            ));
            
            $is_available = ($conflicting_bookings == 0);
            
            $response_data = array(
                'success' => true,
                'available' => $is_available,
                'taxi_id' => $taxi_id,
                'pickup_date' => $pickup_date,
                'pickup_time' => $pickup_time,
                'conflicting_bookings' => (int) $conflicting_bookings
            );
            
            if (!$is_available) {
                $response_data['reason'] = 'Taxi is already booked for this time period';
            }
            
            return new WP_REST_Response($response_data, 200);
        }
        
        public function search_taxis($request) {
            // Get search parameters
            $search = sanitize_text_field($request->get_param('search'));
            $min_price = floatval($request->get_param('min_price'));
            $max_price = floatval($request->get_param('max_price'));
            $min_passengers = absint($request->get_param('min_passengers'));
            $category = absint($request->get_param('category'));
            $page = absint($request->get_param('page')) ?: 1;
            $per_page = min(absint($request->get_param('per_page')) ?: 10, 100);
            
            // Build query args
            $args = array(
                'post_type' => MPTBM_Function::get_cpt(),
                'post_status' => 'publish',
                'posts_per_page' => $per_page,
                'paged' => $page,
                'meta_query' => array('relation' => 'AND')
            );
            
            // Add search term
            if ($search) {
                $args['s'] = $search;
            }
            
            // Add price range filter
            if ($min_price > 0 || $max_price > 0) {
                $price_query = array(
                    'key' => 'mptbm_rent_price',
                    'type' => 'NUMERIC'
                );
                
                if ($min_price > 0 && $max_price > 0) {
                    $price_query['value'] = array($min_price, $max_price);
                    $price_query['compare'] = 'BETWEEN';
                } elseif ($min_price > 0) {
                    $price_query['value'] = $min_price;
                    $price_query['compare'] = '>=';
                } elseif ($max_price > 0) {
                    $price_query['value'] = $max_price;
                    $price_query['compare'] = '<=';
                }
                
                $args['meta_query'][] = $price_query;
            }
            
            // Add passenger filter
            if ($min_passengers > 0) {
                $args['meta_query'][] = array(
                    'key' => 'mptbm_maximum_passenger',
                    'value' => $min_passengers,
                    'type' => 'NUMERIC',
                    'compare' => '>='
                );
            }
            
            // Add category filter
            if ($category > 0) {
                $args['tax_query'] = array(
                    array(
                        'taxonomy' => MPTBM_Function::get_category_slug(),
                        'field' => 'term_id',
                        'terms' => $category
                    )
                );
            }
            
            $query = new WP_Query($args);
            $taxis = array();
            
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    
                    // Get taxi details
                    $taxi_data = array(
                        'id' => $post_id,
                        'title' => get_the_title(),
                        'description' => get_the_excerpt(),
                        'featured_image' => get_the_post_thumbnail_url($post_id, 'medium'),
                        'price' => MP_Global_Function::get_post_info($post_id, 'mptbm_rent_price', 0),
                        'max_passengers' => MPTBM_Function::get_feature_passenger($post_id),
                        'max_bags' => MPTBM_Function::get_feature_bag($post_id),
                        'permalink' => get_permalink($post_id)
                    );
                    
                    // Get categories
                    $categories = get_the_terms($post_id, MPTBM_Function::get_category_slug());
                    if ($categories && !is_wp_error($categories)) {
                        $taxi_data['categories'] = array_map(function($cat) {
                            return array(
                                'id' => $cat->term_id,
                                'name' => $cat->name,
                                'slug' => $cat->slug
                            );
                        }, $categories);
                    } else {
                        $taxi_data['categories'] = array();
                    }
                    
                    $taxis[] = $taxi_data;
                }
                wp_reset_postdata();
            }
            
            $response_data = array(
                'success' => true,
                'data' => $taxis,
                'pagination' => array(
                    'page' => $page,
                    'per_page' => $per_page,
                    'total_items' => $query->found_posts,
                    'total_pages' => $query->max_num_pages
                ),
                'search_params' => array(
                    'search' => $search,
                    'min_price' => $min_price,
                    'max_price' => $max_price,
                    'min_passengers' => $min_passengers,
                    'category' => $category
                ),
                'message' => sprintf('Found %d taxis matching search criteria', count($taxis))
            );
            
            return new WP_REST_Response($response_data, 200);
        }
        
        // Booking operations
        public function get_bookings($request) {
            // Get request parameters
            $page = $request->get_param('page') ? absint($request->get_param('page')) : 1;
            $per_page = $request->get_param('per_page') ? absint($request->get_param('per_page')) : 10;
            $status = $request->get_param('status') ? sanitize_text_field($request->get_param('status')) : '';
            $user_id = $request->get_param('user_id') ? absint($request->get_param('user_id')) : 0;
            
            // Ensure per_page is reasonable
            $per_page = min($per_page, 100);
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'posts';
            $meta_table = $wpdb->prefix . 'postmeta';
            
            // Build WHERE conditions
            $where_conditions = array("p.post_type = 'shop_order'");
            
            if ($status) {
                $where_conditions[] = $wpdb->prepare("p.post_status = %s", 'wc-' . $status);
            }
            
            if ($user_id) {
                $where_conditions[] = $wpdb->prepare(
                    "EXISTS (SELECT 1 FROM {$meta_table} WHERE post_id = p.ID AND meta_key = '_customer_user' AND meta_value = %d)",
                    $user_id
                );
            }
            
            // Add filter for transportation bookings
            $where_conditions[] = "EXISTS (SELECT 1 FROM {$meta_table} WHERE post_id = p.ID AND meta_key LIKE 'mptbm_%')";
            
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
            
            // Count total
            $count_query = "SELECT COUNT(*) FROM {$table_name} p {$where_clause}";
            $total_items = $wpdb->get_var($count_query);
            
            // Get bookings with pagination
            $offset = ($page - 1) * $per_page;
            $bookings_query = "SELECT p.* FROM {$table_name} p {$where_clause} ORDER BY p.post_date DESC LIMIT %d OFFSET %d";
            $bookings_query = $wpdb->prepare($bookings_query, $per_page, $offset);
            $booking_posts = $wpdb->get_results($bookings_query);
            
            $bookings = array();
            
            foreach ($booking_posts as $post) {
                $order_id = $post->ID;
                
                // Get WooCommerce order if available
                $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
                
                $booking_data = array(
                    'id' => $order_id,
                    'status' => str_replace('wc-', '', $post->post_status),
                    'date_created' => $post->post_date,
                    'date_modified' => $post->post_modified,
                    'total' => $order ? $order->get_total() : get_post_meta($order_id, '_order_total', true),
                    'currency' => $order ? $order->get_currency() : get_option('woocommerce_currency', 'USD'),
                    'customer_id' => $order ? $order->get_customer_id() : get_post_meta($order_id, '_customer_user', true),
                    'customer_email' => $order ? $order->get_billing_email() : get_post_meta($order_id, '_billing_email', true),
                    'customer_name' => $order ? ($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) : 
                                       (get_post_meta($order_id, '_billing_first_name', true) . ' ' . get_post_meta($order_id, '_billing_last_name', true)),
                    'booking_details' => array(
                        'pickup_location' => get_post_meta($order_id, 'mptbm_pickup_location', true),
                        'dropoff_location' => get_post_meta($order_id, 'mptbm_dropoff_location', true),
                        'pickup_date' => get_post_meta($order_id, 'mptbm_pickup_date', true),
                        'pickup_time' => get_post_meta($order_id, 'mptbm_pickup_time', true),
                        'return_date' => get_post_meta($order_id, 'mptbm_return_date', true),
                        'return_time' => get_post_meta($order_id, 'mptbm_return_time', true),
                        'passenger_count' => get_post_meta($order_id, 'mptbm_passenger_count', true),
                        'taxi_id' => get_post_meta($order_id, 'mptbm_taxi_id', true),
                        'distance' => get_post_meta($order_id, 'mptbm_distance', true),
                        'duration' => get_post_meta($order_id, 'mptbm_duration', true)
                    )
                );
                
                // Get taxi details if taxi_id exists
                if ($booking_data['booking_details']['taxi_id']) {
                    $taxi_post = get_post($booking_data['booking_details']['taxi_id']);
                    if ($taxi_post) {
                        $booking_data['taxi_details'] = array(
                            'id' => $taxi_post->ID,
                            'title' => $taxi_post->post_title,
                            'featured_image' => get_the_post_thumbnail_url($taxi_post->ID, 'medium')
                        );
                    }
                }
                
                $bookings[] = $booking_data;
            }
            
            $response_data = array(
                'success' => true,
                'data' => $bookings,
                'pagination' => array(
                    'page' => $page,
                    'per_page' => $per_page,
                    'total_items' => (int) $total_items,
                    'total_pages' => ceil($total_items / $per_page)
                ),
                'message' => sprintf('Found %d bookings', count($bookings))
            );
            
            return new WP_REST_Response($response_data, 200);
        }
        
        public function create_booking($request) {
            // Validate required parameters
            $required_params = array('taxi_id', 'pickup_location', 'dropoff_location', 'pickup_date', 'pickup_time', 'customer_email');
            
            foreach ($required_params as $param) {
                if (!$request->get_param($param)) {
                    return new WP_Error('missing_parameter', "Missing required parameter: {$param}", array('status' => 400));
                }
            }
            
            // Sanitize input data
            $taxi_id = absint($request->get_param('taxi_id'));
            $pickup_location = sanitize_text_field($request->get_param('pickup_location'));
            $dropoff_location = sanitize_text_field($request->get_param('dropoff_location'));
            $pickup_date = sanitize_text_field($request->get_param('pickup_date'));
            $pickup_time = sanitize_text_field($request->get_param('pickup_time'));
            $return_date = sanitize_text_field($request->get_param('return_date'));
            $return_time = sanitize_text_field($request->get_param('return_time'));
            $passenger_count = absint($request->get_param('passenger_count')) ?: 1;
            $customer_email = sanitize_email($request->get_param('customer_email'));
            $customer_name = sanitize_text_field($request->get_param('customer_name'));
            $customer_phone = sanitize_text_field($request->get_param('customer_phone'));
            $distance = floatval($request->get_param('distance'));
            $duration = sanitize_text_field($request->get_param('duration'));
            
            // Validate taxi exists
            $taxi_post = get_post($taxi_id);
            if (!$taxi_post || $taxi_post->post_type !== MPTBM_Function::get_cpt()) {
                return new WP_Error('invalid_taxi', 'Invalid taxi ID', array('status' => 400));
            }
            
            // Validate email
            if (!is_email($customer_email)) {
                return new WP_Error('invalid_email', 'Invalid email address', array('status' => 400));
            }
            
            // Validate date format
            if (!strtotime($pickup_date)) {
                return new WP_Error('invalid_date', 'Invalid pickup date format', array('status' => 400));
            }
            
            // Calculate price
            $base_price = floatval(MP_Global_Function::get_post_info($taxi_id, 'mptbm_rent_price', 0));
            $distance_price = $distance > 0 ? ($distance * floatval(MP_Global_Function::get_settings('mptbm_general_settings', 'price_per_km', 1))) : 0;
            $total_price = max($base_price + $distance_price, $base_price);
            
            // Apply passenger multiplier if configured
            $passenger_multiplier = floatval(MP_Global_Function::get_settings('mptbm_general_settings', 'passenger_price_multiplier', 1));
            if ($passenger_count > 1 && $passenger_multiplier > 1) {
                $total_price *= (1 + (($passenger_count - 1) * ($passenger_multiplier - 1)));
            }
            
            // Create WooCommerce order if WooCommerce is active
            if (function_exists('wc_create_order')) {
                try {
                    $order = wc_create_order();
                    
                    // Add taxi as a product to the order
                    $product_data = array(
                        'name' => sprintf('Taxi Booking: %s', $taxi_post->post_title),
                        'price' => $total_price,
                        'quantity' => 1
                    );
                    
                    $order->add_product(null, 1, $product_data);
                    
                    // Set customer details
                    $order->set_billing_email($customer_email);
                    if ($customer_name) {
                        $name_parts = explode(' ', $customer_name, 2);
                        $order->set_billing_first_name($name_parts[0]);
                        if (isset($name_parts[1])) {
                            $order->set_billing_last_name($name_parts[1]);
                        }
                    }
                    if ($customer_phone) {
                        $order->set_billing_phone($customer_phone);
                    }
                    
                    // Set order status
                    $order->set_status('pending');
                    
                    // Calculate totals
                    $order->calculate_totals();
                    $order->save();
                    
                    $order_id = $order->get_id();
                } catch (Exception $e) {
                    return new WP_Error('order_creation_failed', 'Failed to create order: ' . $e->getMessage(), array('status' => 500));
                }
            } else {
                // Create a custom post for booking if WooCommerce is not available
                $order_id = wp_insert_post(array(
                    'post_type' => 'mptbm_booking',
                    'post_title' => sprintf('Booking - %s to %s', $pickup_location, $dropoff_location),
                    'post_status' => 'pending',
                    'post_date' => current_time('mysql')
                ));
                
                if (is_wp_error($order_id)) {
                    return new WP_Error('booking_creation_failed', 'Failed to create booking', array('status' => 500));
                }
                
                // Store total price
                update_post_meta($order_id, '_order_total', $total_price);
                update_post_meta($order_id, '_billing_email', $customer_email);
                if ($customer_name) {
                    update_post_meta($order_id, '_billing_first_name', $customer_name);
                }
            }
            
            // Store booking metadata
            $booking_meta = array(
                'mptbm_taxi_id' => $taxi_id,
                'mptbm_pickup_location' => $pickup_location,
                'mptbm_dropoff_location' => $dropoff_location,
                'mptbm_pickup_date' => $pickup_date,
                'mptbm_pickup_time' => $pickup_time,
                'mptbm_return_date' => $return_date,
                'mptbm_return_time' => $return_time,
                'mptbm_passenger_count' => $passenger_count,
                'mptbm_distance' => $distance,
                'mptbm_duration' => $duration,
                'mptbm_booking_created_via' => 'api'
            );
            
            foreach ($booking_meta as $key => $value) {
                update_post_meta($order_id, $key, $value);
            }
            
            // Prepare response data
            $response_data = array(
                'success' => true,
                'data' => array(
                    'booking_id' => $order_id,
                    'status' => 'pending',
                    'taxi_id' => $taxi_id,
                    'taxi_title' => $taxi_post->post_title,
                    'pickup_location' => $pickup_location,
                    'dropoff_location' => $dropoff_location,
                    'pickup_date' => $pickup_date,
                    'pickup_time' => $pickup_time,
                    'passenger_count' => $passenger_count,
                    'total_price' => $total_price,
                    'currency' => get_option('woocommerce_currency', 'USD'),
                    'customer_email' => $customer_email
                ),
                'message' => 'Booking created successfully'
            );
            
            return new WP_REST_Response($response_data, 201);
        }
        
        // Location operations
        public function location_autocomplete($request) {
            $query = sanitize_text_field($request->get_param('query'));
            $limit = min(absint($request->get_param('limit')) ?: 5, 20);
            
            if (!$query) {
                return new WP_Error('missing_query', 'Search query is required', array('status' => 400));
            }
            
            $api_key = MP_Global_Function::get_settings('mptbm_map_api_settings', 'gmap_api_key');
            if (!$api_key) {
                return new WP_Error('missing_api_key', 'Google Maps API key not configured', array('status' => 500));
            }
            
            // Call Google Places API
            $url = 'https://maps.googleapis.com/maps/api/place/autocomplete/json';
            $params = array(
                'input' => $query,
                'key' => $api_key,
                'types' => 'address',
                'components' => 'country:' . MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_country', 'BD')
            );
            
            $response = wp_remote_get($url . '?' . http_build_query($params));
            
            if (is_wp_error($response)) {
                return new WP_Error('api_error', 'Failed to fetch autocomplete suggestions', array('status' => 500));
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($data['status'] !== 'OK' && $data['status'] !== 'ZERO_RESULTS') {
                return new WP_Error('google_api_error', 'Google API error: ' . $data['status'], array('status' => 500));
            }
            
            $suggestions = array();
            if (isset($data['predictions'])) {
                $predictions = array_slice($data['predictions'], 0, $limit);
                
                foreach ($predictions as $prediction) {
                    $suggestions[] = array(
                        'place_id' => $prediction['place_id'],
                        'description' => $prediction['description'],
                        'main_text' => $prediction['structured_formatting']['main_text'] ?? '',
                        'secondary_text' => $prediction['structured_formatting']['secondary_text'] ?? ''
                    );
                }
            }
            
            return new WP_REST_Response(array(
                'success' => true,
                'data' => $suggestions,
                'query' => $query,
                'message' => sprintf('Found %d suggestions', count($suggestions))
            ), 200);
        }
        
        public function calculate_distance($request) {
            $origin = sanitize_text_field($request->get_param('origin'));
            $destination = sanitize_text_field($request->get_param('destination'));
            
            if (!$origin || !$destination) {
                return new WP_Error('missing_locations', 'Origin and destination are required', array('status' => 400));
            }
            
            $api_key = MP_Global_Function::get_settings('mptbm_map_api_settings', 'gmap_api_key');
            if (!$api_key) {
                return new WP_Error('missing_api_key', 'Google Maps API key not configured', array('status' => 500));
            }
            
            // Call Google Distance Matrix API
            $url = 'https://maps.googleapis.com/maps/api/distancematrix/json';
            $params = array(
                'origins' => $origin,
                'destinations' => $destination,
                'key' => $api_key,
                'units' => 'metric',
                'mode' => 'driving'
            );
            
            $response = wp_remote_get($url . '?' . http_build_query($params));
            
            if (is_wp_error($response)) {
                return new WP_Error('api_error', 'Failed to calculate distance', array('status' => 500));
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($data['status'] !== 'OK') {
                return new WP_Error('google_api_error', 'Google API error: ' . $data['status'], array('status' => 500));
            }
            
            $element = $data['rows'][0]['elements'][0] ?? null;
            
            if (!$element || $element['status'] !== 'OK') {
                return new WP_Error('route_not_found', 'Route not found', array('status' => 404));
            }
            
            // Calculate price based on distance
            $distance_km = $element['distance']['value'] / 1000;
            $base_price = floatval(MP_Global_Function::get_settings('mptbm_general_settings', 'base_price', 50));
            $price_per_km = floatval(MP_Global_Function::get_settings('mptbm_general_settings', 'price_per_km', 2));
            $estimated_price = $base_price + ($distance_km * $price_per_km);
            
            return new WP_REST_Response(array(
                'success' => true,
                'data' => array(
                    'distance' => array(
                        'text' => $element['distance']['text'],
                        'value' => $element['distance']['value'],
                        'kilometers' => round($distance_km, 2)
                    ),
                    'duration' => array(
                        'text' => $element['duration']['text'],
                        'value' => $element['duration']['value'],
                        'minutes' => round($element['duration']['value'] / 60, 1)
                    ),
                    'price_estimate' => array(
                        'amount' => round($estimated_price, 2),
                        'currency' => get_option('woocommerce_currency', 'USD'),
                        'base_price' => $base_price,
                        'distance_price' => round($distance_km * $price_per_km, 2)
                    )
                ),
                'origin' => $origin,
                'destination' => $destination
            ), 200);
        }
        
        public function get_route_information($request) {
            $origin = sanitize_text_field($request->get_param('origin'));
            $destination = sanitize_text_field($request->get_param('destination'));
            $waypoints = $request->get_param('waypoints') ? sanitize_text_field($request->get_param('waypoints')) : '';
            
            if (!$origin || !$destination) {
                return new WP_Error('missing_locations', 'Origin and destination are required', array('status' => 400));
            }
            
            $api_key = MP_Global_Function::get_settings('mptbm_map_api_settings', 'gmap_api_key');
            if (!$api_key) {
                return new WP_Error('missing_api_key', 'Google Maps API key not configured', array('status' => 500));
            }
            
            // Call Google Directions API
            $url = 'https://maps.googleapis.com/maps/api/directions/json';
            $params = array(
                'origin' => $origin,
                'destination' => $destination,
                'key' => $api_key,
                'mode' => 'driving',
                'alternatives' => 'true'
            );
            
            if ($waypoints) {
                $params['waypoints'] = $waypoints;
            }
            
            $response = wp_remote_get($url . '?' . http_build_query($params));
            
            if (is_wp_error($response)) {
                return new WP_Error('api_error', 'Failed to get route information', array('status' => 500));
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($data['status'] !== 'OK') {
                return new WP_Error('google_api_error', 'Google API error: ' . $data['status'], array('status' => 500));
            }
            
            $routes = array();
            foreach ($data['routes'] as $route) {
                $leg = $route['legs'][0];
                $routes[] = array(
                    'summary' => $route['summary'],
                    'distance' => $leg['distance'],
                    'duration' => $leg['duration'],
                    'start_address' => $leg['start_address'],
                    'end_address' => $leg['end_address'],
                    'polyline' => $route['overview_polyline']['points']
                );
            }
            
            return new WP_REST_Response(array(
                'success' => true,
                'data' => $routes,
                'origin' => $origin,
                'destination' => $destination
            ), 200);
        }
        
        // Settings operations
        public function get_pricing_settings($request) {
            $pricing_settings = array(
                'base_price' => floatval(MP_Global_Function::get_settings('mptbm_general_settings', 'base_price', 50)),
                'price_per_km' => floatval(MP_Global_Function::get_settings('mptbm_general_settings', 'price_per_km', 2)),
                'passenger_price_multiplier' => floatval(MP_Global_Function::get_settings('mptbm_general_settings', 'passenger_price_multiplier', 1)),
                'night_surcharge' => floatval(MP_Global_Function::get_settings('mptbm_general_settings', 'night_surcharge', 0)),
                'weekend_surcharge' => floatval(MP_Global_Function::get_settings('mptbm_general_settings', 'weekend_surcharge', 0)),
                'currency' => get_option('woocommerce_currency', 'USD'),
                'currency_symbol' => get_woocommerce_currency_symbol(),
                'tax_rate' => floatval(MP_Global_Function::get_settings('mptbm_general_settings', 'tax_rate', 0))
            );
            
            return new WP_REST_Response(array(
                'success' => true,
                'data' => $pricing_settings
            ), 200);
        }
        
        public function get_operational_settings($request) {
            $operational_settings = array(
                'booking_buffer_time' => absint(MP_Global_Function::get_settings('mptbm_general_settings', 'enable_buffer_time', 60)),
                'max_advance_booking_days' => absint(MP_Global_Function::get_settings('mptbm_general_settings', 'max_advance_days', 30)),
                'operating_hours' => array(
                    'start' => MP_Global_Function::get_settings('mptbm_general_settings', 'operating_start_time', '06:00'),
                    'end' => MP_Global_Function::get_settings('mptbm_general_settings', 'operating_end_time', '22:00')
                ),
                'timezone' => get_option('timezone_string', 'UTC'),
                'date_format' => get_option('date_format', 'Y-m-d'),
                'time_format' => get_option('time_format', 'H:i'),
                'minimum_trip_distance' => floatval(MP_Global_Function::get_settings('mptbm_general_settings', 'min_distance', 1)),
                'maximum_trip_distance' => floatval(MP_Global_Function::get_settings('mptbm_general_settings', 'max_distance', 100)),
                'auto_confirm_bookings' => MP_Global_Function::get_settings('mptbm_general_settings', 'auto_confirm', 'no') === 'yes'
            );
            
            return new WP_REST_Response(array(
                'success' => true,
                'data' => $operational_settings
            ), 200);
        }
        
        public function get_operation_zones($request) {
            // Get configured operation zones
            $zones_setting = MP_Global_Function::get_settings('mptbm_general_settings', 'operation_zones', '');
            $zones = array();
            
            if ($zones_setting) {
                // Parse zones if stored as JSON or serialized data
                $parsed_zones = maybe_unserialize($zones_setting);
                if (is_array($parsed_zones)) {
                    $zones = $parsed_zones;
                } else {
                    // Fallback: treat as comma-separated list
                    $zone_names = array_map('trim', explode(',', $zones_setting));
                    foreach ($zone_names as $name) {
                        if (!empty($name)) {
                            $zones[] = array(
                                'name' => $name,
                                'type' => 'area',
                                'coordinates' => array()
                            );
                        }
                    }
                }
            }
            
            // Add default zone based on map settings if no zones configured
            if (empty($zones)) {
                $zones[] = array(
                    'name' => 'Default Service Area',
                    'type' => 'circle',
                    'center' => array(
                        'lat' => floatval(MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_latitude', 23.81234828905659)),
                        'lng' => floatval(MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_longitude', 90.41069652669002))
                    ),
                    'radius' => floatval(MP_Global_Function::get_settings('mptbm_general_settings', 'service_radius', 50)), // km
                    'country' => MP_Global_Function::get_settings('mptbm_map_api_settings', 'mp_country', 'BD')
                );
            }
            
            return new WP_REST_Response(array(
                'success' => true,
                'data' => $zones,
                'message' => sprintf('Found %d operation zones', count($zones))
            ), 200);
        }
        
        // Cleanup method for old API logs
        public function cleanup_old_api_logs() {
            $logging_enabled = MP_Global_Function::get_settings('mptbm_rest_api_settings', 'api_logging', 'yes');
            
            if ($logging_enabled !== 'yes') {
                return;
            }
            
            global $wpdb;
            
            // Ensure table names are initialized
            if (empty($this->api_logs_table)) {
                $this->init_table_names();
            }
            
            // Delete logs older than 30 days to prevent database bloat
            $deleted = $wpdb->query(
                "DELETE FROM {$this->api_logs_table} 
                 WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) 
                 LIMIT 1000"
            );
            
            // Log the cleanup activity
            if ($deleted) {
                error_log("MPTBM API: Cleaned up {$deleted} old API log entries");
            }
        }
    }
}
