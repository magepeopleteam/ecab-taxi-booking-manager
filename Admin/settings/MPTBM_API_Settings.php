<?php
/*
* @Author         engr.sumonazma@gmail.com
* Copyright:      mage-people.com
*/
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_API_Settings')) {
    class MPTBM_API_Settings {
        public function __construct() {
            // AJAX handlers
            add_action('wp_ajax_mptbm_get_api_keys', array($this, 'ajax_get_api_keys'));
            add_action('wp_ajax_mptbm_generate_api_key', array($this, 'ajax_generate_api_key'));
            add_action('wp_ajax_mptbm_delete_api_key', array($this, 'ajax_delete_api_key'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_api_scripts'));
        }

        /**
         * AJAX handler for getting API keys
         */
        public function ajax_get_api_keys() {
            // Check permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => esc_html__('You do not have permission to perform this action.', 'ecab-taxi-booking-manager')));
            }
            
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mptbm_api_nonce')) {
                wp_send_json_error(array('message' => esc_html__('Security check failed.', 'ecab-taxi-booking-manager')));
            }
            
            wp_send_json_success(array(
                'html' => $this->get_api_keys_html()
            ));
        }
        
        /**
         * Generate HTML for API keys table
         */
        private function get_api_keys_html() {
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
                $html .= '<th style="width:100px;">' . esc_html__('Actions', 'ecab-taxi-booking-manager') . '</th>';
                $html .= '</tr></thead><tbody>';
                
                foreach ($api_keys as $index => $key) {
                    $html .= '<tr>';
                    $html .= '<td>' . esc_html($key['description']) . '</td>';
                    $html .= '<td><code class="mptbm-api-key">' . esc_html($key['key']) . '</code>';
                    $html .= '</td>';
                    $html .= '<td>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($key['created']))) . '</td>';
                    $html .= '<td>' . (empty($key['last_used']) ? esc_html__('Never', 'ecab-taxi-booking-manager') : esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($key['last_used'])))) . '</td>';
                    $html .= '<td>';
                    $html .= '<button type="button" class="button button-small mptbm-copy-key" data-key="' . esc_attr($key['key']) . '" title="' . esc_attr__('Copy', 'ecab-taxi-booking-manager') . '"><span class="dashicons dashicons-clipboard"></span></button> ';
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
        public function ajax_generate_api_key() {
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
            $key = $this->generate_api_key();
            
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
                'html' => $this->get_api_keys_html()
            ));
        }
        
        /**
         * AJAX handler for deleting API key
         */
        public function ajax_delete_api_key() {
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
                'html' => $this->get_api_keys_html()
            ));
        }
        
        /**
         * Generate a unique API key
         */
        private function generate_api_key() {
            return 'mptbm_' . bin2hex(random_bytes(16));
        }
        
        /**
         * Enqueue scripts for API settings
         */
        public function enqueue_api_scripts($hook) {
            if (strpos($hook, 'mptbm_settings_page') !== false) {
                // Calculate the plugin directory URL
                $plugin_dir_url = plugin_dir_url(dirname(dirname(__FILE__)));
                
                // Enqueue CSS
                wp_enqueue_style(
                    'mptbm-api-css',
                    $plugin_dir_url . 'assets/admin/css/mptbm_api.css',
                    array(),
                    '1.0'
                );
                
                // Enqueue JS
                wp_enqueue_script(
                    'mptbm-api-js',
                    $plugin_dir_url . 'assets/admin/mptbm_api.js',
                    array('jquery'),
                    '1.0',
                    true
                );
                
                // Pass the nonce to our script
                wp_localize_script('mptbm-api-js', 'mptbm_api_data', array(
                    'nonce' => wp_create_nonce('mptbm_api_nonce')
                ));
            }
        }
    }
    
    new MPTBM_API_Settings();
} 