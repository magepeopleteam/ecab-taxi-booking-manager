<?php
/**
 * Test script to verify table name initialization in REST API class
 * Run this once to test the fix, then delete it.
 */

// Load WordPress
$wp_load = dirname(__FILE__) . '/../../../wp-load.php';
if (file_exists($wp_load)) {
    require_once($wp_load);
} else {
    die('WordPress not found.');
}

// Check if user is logged in and has admin capabilities
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    die('Access denied. You must be logged in as an administrator.');
}

echo '<h1>Table Name Initialization Test</h1>';

// Test the REST API class
if (class_exists('MPTBM_REST_API')) {
    echo '<p>✅ MPTBM_REST_API class exists</p>';
    
    $api_instance = new MPTBM_REST_API();
    
    // Use reflection to access private properties
    $reflection = new ReflectionClass($api_instance);
    
    $keys_table_prop = $reflection->getProperty('api_keys_table');
    $keys_table_prop->setAccessible(true);
    $keys_table_value = $keys_table_prop->getValue($api_instance);
    
    $logs_table_prop = $reflection->getProperty('api_logs_table');
    $logs_table_prop->setAccessible(true);
    $logs_table_value = $logs_table_prop->getValue($api_instance);
    
    echo '<h3>Table Names Status:</h3>';
    echo '<ul>';
    echo '<li><strong>API Keys Table:</strong> ' . ($keys_table_value ? $keys_table_value : '<span style="color:red;">EMPTY!</span>') . '</li>';
    echo '<li><strong>API Logs Table:</strong> ' . ($logs_table_value ? $logs_table_value : '<span style="color:red;">EMPTY!</span>') . '</li>';
    echo '</ul>';
    
    if ($keys_table_value && $logs_table_value) {
        echo '<p style="color:green;">✅ <strong>SUCCESS:</strong> Table names are properly initialized!</p>';
        
        // Check if tables actually exist
        global $wpdb;
        $keys_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$keys_table_value}'");
        $logs_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$logs_table_value}'");
        
        echo '<h3>Database Tables Status:</h3>';
        echo '<ul>';
        echo '<li><strong>' . $keys_table_value . ':</strong> ' . ($keys_table_exists ? '<span style="color:green;">EXISTS</span>' : '<span style="color:orange;">DOES NOT EXIST</span>') . '</li>';
        echo '<li><strong>' . $logs_table_value . ':</strong> ' . ($logs_table_exists ? '<span style="color:green;">EXISTS</span>' : '<span style="color:orange;">DOES NOT EXIST</span>') . '</li>';
        echo '</ul>';
        
        if (!$keys_table_exists || !$logs_table_exists) {
            echo '<p style="color:orange;">⚠️ Tables don\'t exist yet. Run the create-api-tables.php script or try generating an API key (which should auto-create them).</p>';
        }
    } else {
        echo '<p style="color:red;">❌ <strong>ERROR:</strong> Table names are not initialized properly!</p>';
    }
} else {
    echo '<p style="color:red;">❌ MPTBM_REST_API class not found</p>';
}

echo '<hr>';
echo '<p><strong>How to proceed:</strong></p>';
echo '<ol>';
echo '<li>If table names are initialized but tables don\'t exist, run <a href="create-api-tables.php">create-api-tables.php</a></li>';
echo '<li>If everything looks good, try generating an API key from the admin panel</li>';
echo '<li>Delete this test script when done</li>';
echo '</ol>';

echo '<hr>';
echo '<p style="color: red; font-size: 14px;"><strong>⚠️ Remember to delete this test script file after using it!</strong></p>';
?>
