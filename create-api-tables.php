<?php
/**
 * Manual script to create API tables for existing installations
 * 
 * This script should be run once to create the necessary database tables
 * for the REST API functionality if they weren't created during activation.
 * 
 * To run: Access this file directly in your browser once, then delete it.
 * Example: https://yoursite.com/wp-content/plugins/ecab-taxi-booking-manager/create-api-tables.php
 */

// Security check - make sure this is being run in WordPress context
if (!defined('ABSPATH')) {
    // Load WordPress
    $wp_load = dirname(__FILE__) . '/../../../wp-load.php';
    if (file_exists($wp_load)) {
        require_once($wp_load);
    } else {
        die('WordPress not found. Please run this script from your WordPress installation.');
    }
}

// Check if user is logged in and has admin capabilities
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    die('Access denied. You must be logged in as an administrator.');
}

echo '<h1>E-Cab API Tables Creation Script</h1>';

global $wpdb;

$api_keys_table = $wpdb->prefix . 'mptbm_api_keys';
$api_logs_table = $wpdb->prefix . 'mptbm_api_logs';

// Check if tables already exist
$keys_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$api_keys_table}'");
$logs_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$api_logs_table}'");

if ($keys_table_exists && $logs_table_exists) {
    echo '<p style="color: green;">✅ API tables already exist!</p>';
    echo '<ul>';
    echo '<li>API Keys table: ' . $api_keys_table . '</li>';
    echo '<li>API Logs table: ' . $api_logs_table . '</li>';
    echo '</ul>';
} else {
    echo '<p>Creating API tables...</p>';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // API Keys table
    $api_keys_sql = "CREATE TABLE {$api_keys_table} (
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
    ) {$charset_collate};";
    
    // API Logs table
    $api_logs_sql = "CREATE TABLE {$api_logs_table} (
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
    ) {$charset_collate};";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $result1 = dbDelta($api_keys_sql);
    $result2 = dbDelta($api_logs_sql);
    
    // Check if tables were created successfully
    $keys_table_created = $wpdb->get_var("SHOW TABLES LIKE '{$api_keys_table}'");
    $logs_table_created = $wpdb->get_var("SHOW TABLES LIKE '{$api_logs_table}'");
    
    if ($keys_table_created && $logs_table_created) {
        echo '<p style="color: green;">✅ API tables created successfully!</p>';
        echo '<ul>';
        echo '<li>API Keys table: ' . $api_keys_table . '</li>';
        echo '<li>API Logs table: ' . $api_logs_table . '</li>';
        echo '</ul>';
    } else {
        echo '<p style="color: red;">❌ Error creating API tables.</p>';
        echo '<p>Please check your database permissions and try again.</p>';
        
        if (!$keys_table_created) {
            echo '<p>Failed to create: ' . $api_keys_table . '</p>';
        }
        if (!$logs_table_created) {
            echo '<p>Failed to create: ' . $api_logs_table . '</p>';
        }
    }
}

echo '<hr>';
echo '<p><strong>Next steps:</strong></p>';
echo '<ol>';
echo '<li>Go to <strong>Transportation > Settings > REST API Settings</strong> to enable the REST API</li>';
echo '<li>Visit <strong>Transportation > API Documentation</strong> to generate API keys</li>';
echo '<li><strong>Delete this script file</strong> for security reasons</li>';
echo '</ol>';

echo '<hr>';
echo '<p style="color: #666; font-size: 12px;">Script location: ' . __FILE__ . '</p>';
echo '<p style="color: red; font-size: 14px;"><strong>⚠️ Remember to delete this script file after running it!</strong></p>';
?>
