<?php
/*
* @Author         engr.sumonazma@gmail.com
* Copyright:      mage-people.com
*/
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

/**
 * Add raw_html callback to the MAGE_Setting_API class through a filter
 */
function mptbm_add_raw_html_callback($args) {
    // Display description if provided
    if (!empty($args['desc'])) {
        ?>
        <i class="info_text">
            <span class="fas fa-info-circle"></span>
            <?php echo esc_html($args['desc']); ?>
        </i>
        <?php
    }
    
    // Display value HTML without escaping
    if (!empty($args['value'])) {
        echo $args['value'];
    }
}

// Register the callback function
add_action('admin_init', function() {
    // Check if the class exists
    if (class_exists('MAGE_Setting_API')) {
        // Get existing instance of the class
        global $mage_setting_api;
        if (!$mage_setting_api) {
            $mage_setting_api = new MAGE_Setting_API();
        }
        
        // Add our callback
        add_filter('mage_callback_raw_html', 'mptbm_add_raw_html_callback', 10, 1);
    }
}, 9); // Priority 9 to run before the settings are registered

// Enqueue scripts and styles
function mptbm_enqueue_api_scripts($hook) {
    if (strpos($hook, 'mptbm_settings_page') !== false) {
        // Add styles
        wp_enqueue_style(
            'mptbm-api-style',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/admin/css/mptbm_api.css',
            array(),
            time()
        );
        
        // Add scripts
        wp_enqueue_script(
            'mptbm-api-script',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/admin/mptbm_api.js',
            array('jquery'),
            time(),
            true
        );
        
        // Pass data to script
        wp_localize_script('mptbm-api-script', 'mptbm_api_data', array(
            'nonce' => wp_create_nonce('mptbm_api_nonce'),
            'ajaxurl' => admin_url('admin-ajax.php')
        ));
    }
}
add_action('admin_enqueue_scripts', 'mptbm_enqueue_api_scripts');

// AJAX handlers
function mptbm_get_api_keys_ajax() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => esc_html__('You do not have permission to perform this action.', 'ecab-taxi-booking-manager')));
    }
    
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mptbm_api_nonce')) {
        wp_send_json_error(array('message' => esc_html__('Security check failed.', 'ecab-taxi-booking-manager')));
    }
    
    wp_send_json_success(array(
        'html' => mptbm_get_api_keys_html()
    ));
}
add_action('wp_ajax_mptbm_get_api_keys', 'mptbm_get_api_keys_ajax');

/**
 * Generate HTML for API keys table
 */
function mptbm_get_api_keys_html() {
    $html = '';
    
    // Get saved API keys
    $api_keys = get_option('mptbm_api_keys', array());
    
    if (!empty($api_keys)) {
        $html .= '<div style="margin-top:15px;">';
        $html .= '<table class="widefat fixed striped mptbm-api-keys-table">';
        $html .= '<thead><tr>';
        $html .= '<th>' . esc_html__('Description', 'ecab-taxi-booking-manager') . '</th>';
        $html .= '<th>' . esc_html__('API Key', 'ecab-taxi-booking-manager') . '</th>';
        $html .= '<th>' . esc_html__('Created', 'ecab-taxi-booking-manager') . '</th>';
        $html .= '<th>' . esc_html__('Last Used', 'ecab-taxi-booking-manager') . '</th>';
        $html .= '<th class="actions">' . esc_html__('Actions', 'ecab-taxi-booking-manager') . '</th>';
        $html .= '</tr></thead><tbody>';
        
        foreach ($api_keys as $index => $key) {
            $html .= '<tr>';
            $html .= '<td>' . esc_html($key['description']) . '</td>';
            $html .= '<td><code class="mptbm-api-key">' . esc_html($key['key']) . '</code></td>';
            $html .= '<td>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($key['created']))) . '</td>';
            $html .= '<td>' . (empty($key['last_used']) ? esc_html__('Never', 'ecab-taxi-booking-manager') : esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($key['last_used'])))) . '</td>';
            $html .= '<td class="actions">';
            $html .= '<button type="button" class="button button-small mptbm-copy-key" data-key="' . esc_attr($key['key']) . '" title="' . esc_attr__('Copy', 'ecab-taxi-booking-manager') . '"><span class="dashicons dashicons-clipboard"></span></button>';
            $html .= '<button type="button" class="button button-small mptbm-delete-key" data-key-id="' . esc_attr($index) . '" title="' . esc_attr__('Delete', 'ecab-taxi-booking-manager') . '"><span class="dashicons dashicons-trash"></span></button>';
            $html .= '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        $html .= '</div>';
    }
    
    return $html;
}

/**
 * AJAX handler for generating API key
 */
function mptbm_generate_api_key_ajax() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => esc_html__('You do not have permission to perform this action.', 'ecab-taxi-booking-manager')));
    }
    
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mptbm_api_nonce')) {
        wp_send_json_error(array('message' => esc_html__('Security check failed.', 'ecab-taxi-booking-manager')));
    }
    
    // Get description
    if (!isset($_POST['description']) || empty($_POST['description'])) {
        wp_send_json_error(array('message' => esc_html__('Description is required.', 'ecab-taxi-booking-manager')));
    }
    
    $description = sanitize_text_field($_POST['description']);
    
    // Generate API key
    $key = 'mptbm_' . bin2hex(random_bytes(16));
    
    // Save API key
    $api_keys = get_option('mptbm_api_keys', array());
    $api_keys[] = array(
        'key' => $key,
        'description' => $description,
        'created' => current_time('mysql'),
        'last_used' => ''
    );
    
    update_option('mptbm_api_keys', $api_keys);
    
    wp_send_json_success(array(
        'message' => esc_html__('API key generated successfully.', 'ecab-taxi-booking-manager'),
        'html' => mptbm_get_api_keys_html()
    ));
}
add_action('wp_ajax_mptbm_generate_api_key', 'mptbm_generate_api_key_ajax');

/**
 * AJAX handler for deleting API key
 */
function mptbm_delete_api_key_ajax() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => esc_html__('You do not have permission to perform this action.', 'ecab-taxi-booking-manager')));
    }
    
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mptbm_api_nonce')) {
        wp_send_json_error(array('message' => esc_html__('Security check failed.', 'ecab-taxi-booking-manager')));
    }
    
    // Get key ID
    if (!isset($_POST['key_id']) || !is_numeric($_POST['key_id'])) {
        wp_send_json_error(array('message' => esc_html__('Invalid API key ID.', 'ecab-taxi-booking-manager')));
    }
    
    $key_id = intval($_POST['key_id']);
    
    // Delete API key
    $api_keys = get_option('mptbm_api_keys', array());
    
    if (!isset($api_keys[$key_id])) {
        wp_send_json_error(array('message' => esc_html__('API key not found.', 'ecab-taxi-booking-manager')));
    }
    
    unset($api_keys[$key_id]);
    $api_keys = array_values($api_keys); // Re-index array
    
    update_option('mptbm_api_keys', $api_keys);
    
    wp_send_json_success(array(
        'message' => esc_html__('API key deleted successfully.', 'ecab-taxi-booking-manager'),
        'html' => mptbm_get_api_keys_html()
    ));
}
add_action('wp_ajax_mptbm_delete_api_key', 'mptbm_delete_api_key_ajax');

/**
 * Register the text_html callback
 */
function mptbm_text_html_callback($args) {
    // Just output the HTML value directly without escaping
    if (!empty($args['value'])) {
        echo $args['value'];
    }
}

// Register our callback for text_html field type
add_action('admin_init', function() {
    // Add callback for our field type
    if (class_exists('MAGE_Setting_API')) {
        // Add the callback for the field
        add_filter('mage_callback_text_html', 'mptbm_text_html_callback', 10, 1);
    }
}, 9); 