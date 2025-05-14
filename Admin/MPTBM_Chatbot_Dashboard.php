<?php
if (!defined('ABSPATH')) {
    die;
} // Cannot access directly.

/**
 * AI Chatbot Dashboard Widget
 *
 * Adds a dashboard widget to highlight the new AI chatbot feature.
 */
class MPTBM_Chatbot_Dashboard {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }
    
    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        if (current_user_can('manage_options')) {
            wp_add_dashboard_widget(
                'mptbm_ai_chatbot_dashboard',
                __('E-Cab AI Chatbot Assistant', 'ecab-taxi-booking-manager'),
                array($this, 'render_dashboard_widget')
            );
        }
    }
    
    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        $settings = get_option('mptbm_ai_chatbot_settings', array());
        $is_enabled = isset($settings['enabled']) && $settings['enabled'];
        $api_key = isset($settings['api_key']) && !empty($settings['api_key']);
        
        echo '<div class="mptbm-dashboard-widget">';
        
        echo '<div class="mptbm-widget-header">';
        echo '<span class="dashicons dashicons-format-chat" style="color: #0073aa; font-size: 48px;"></span>';
        echo '<h3>' . __('Enhance Customer Experience with AI', 'ecab-taxi-booking-manager') . '</h3>';
        echo '</div>';
        
        echo '<div class="mptbm-widget-content">';
        echo '<p>' . __('The new AI Chatbot feature helps your customers get instant answers about your taxi services, pricing, and booking process.', 'ecab-taxi-booking-manager') . '</p>';
        
        echo '<h4>' . __('Current Status', 'ecab-taxi-booking-manager') . '</h4>';
        echo '<ul class="mptbm-status-list">';
        
        echo '<li class="' . ($is_enabled ? 'enabled' : 'disabled') . '">';
        echo '<span class="dashicons ' . ($is_enabled ? 'dashicons-yes-alt' : 'dashicons-no-alt') . '"></span> ';
        echo __('Chatbot is', 'ecab-taxi-booking-manager') . ' <strong>' . ($is_enabled ? __('Enabled', 'ecab-taxi-booking-manager') : __('Disabled', 'ecab-taxi-booking-manager')) . '</strong>';
        echo '</li>';
        
        echo '<li class="' . ($api_key ? 'enabled' : 'disabled') . '">';
        echo '<span class="dashicons ' . ($api_key ? 'dashicons-yes-alt' : 'dashicons-no-alt') . '"></span> ';
        echo __('API Key is', 'ecab-taxi-booking-manager') . ' <strong>' . ($api_key ? __('Configured', 'ecab-taxi-booking-manager') : __('Not Configured', 'ecab-taxi-booking-manager')) . '</strong>';
        echo '</li>';
        
        echo '</ul>';
        
        echo '<div class="mptbm-widget-actions">';
        echo '<a href="' . admin_url('edit.php?post_type=mptbm_rent&page=mptbm_ai_chatbot') . '" class="button button-primary">' . __('Configure Chatbot', 'ecab-taxi-booking-manager') . '</a>';
        echo '<a href="' . admin_url('edit.php?post_type=mptbm_rent&page=mptbm_documentation#ai-chatbot') . '" class="button">' . __('View Documentation', 'ecab-taxi-booking-manager') . '</a>';
        echo '</div>';
        
        echo '</div>'; // widget-content
        
        echo '<style>
            .mptbm-dashboard-widget {
                background: #fff;
                border-radius: 3px;
            }
            .mptbm-widget-header {
                display: flex;
                align-items: center;
                margin-bottom: 15px;
            }
            .mptbm-widget-header h3 {
                margin-left: 10px;
            }
            .mptbm-status-list {
                margin: 15px 0;
            }
            .mptbm-status-list li {
                display: flex;
                align-items: center;
                margin-bottom: 5px;
            }
            .mptbm-status-list li.enabled .dashicons {
                color: #46b450;
            }
            .mptbm-status-list li.disabled .dashicons {
                color: #dc3232;
            }
            .mptbm-widget-actions {
                margin-top: 20px;
                display: flex;
                gap: 10px;
            }
        </style>';
        
        echo '</div>'; // mptbm-dashboard-widget
    }
}

new MPTBM_Chatbot_Dashboard(); 