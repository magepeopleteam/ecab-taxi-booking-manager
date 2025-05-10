<?php
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_AI_Chatbot')) {
    class MPTBM_AI_Chatbot {
        private $settings;
        private $api_key;
        private $ai_provider;
        private $system_prompt;
        private $plugin_data;

        public function __construct() {
            // Load settings
            $this->settings = get_option('mptbm_ai_chatbot_settings', array());
            $this->api_key = isset($this->settings['api_key']) ? $this->settings['api_key'] : '';
            $this->ai_provider = isset($this->settings['ai_provider']) ? $this->settings['ai_provider'] : 'openai';
            $this->system_prompt = isset($this->settings['system_prompt']) ? $this->settings['system_prompt'] : $this->get_default_system_prompt();
            
            // Register settings page
            add_action('admin_menu', array($this, 'add_settings_page'));
            add_action('admin_init', array($this, 'register_settings'));
            
            // Register frontend scripts and styles
            add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
            
            // Add chat widget to footer
            add_action('wp_footer', array($this, 'render_chat_widget'));
            
            // Register AJAX handlers
            add_action('wp_ajax_mptbm_ai_chat_message', array($this, 'handle_chat_message'));
            add_action('wp_ajax_nopriv_mptbm_ai_chat_message', array($this, 'handle_chat_message'));
            add_action('wp_ajax_mptbm_test_ai_api_key', array($this, 'test_ai_api_key'));
            add_action('wp_ajax_mptbm_clear_chat_history', array($this, 'clear_chat_history'));
            add_action('wp_ajax_nopriv_mptbm_clear_chat_history', array($this, 'clear_chat_history'));
            
            // Initialize plugin data for context
            add_action('init', array($this, 'initialize_plugin_data'), 20);
        }
        
        /**
         * Get default system prompt for the AI
         */
        private function get_default_system_prompt() {
            return 'You are a helpful assistant for the E-Cab Taxi Booking Manager WordPress plugin. ' .
                   'Your purpose is to help users with taxi bookings, answer questions about services, ' .
                   'pricing, and provide assistance with the booking process. ' .
                   'Provide clear, concise, and accurate information. ' .
                   'If you don\'t know the answer, suggest contacting support. ' .
                   'Keep responses friendly and professional.';
        }
        
        /**
         * Initialize plugin data for context
         */
        public function initialize_plugin_data() {
            // Get basic plugin settings
            $general_settings = get_option('mptbm_general_settings', array());
            $price_settings = get_option('mptbm_price_settings', array());
            
            // Get all services
            $services = array();
            $args = array(
                'post_type' => 'mptbm_rent',
                'posts_per_page' => -1,
                'post_status' => 'publish'
            );
            
            $transports = get_posts($args);
            foreach ($transports as $transport) {
                $id = $transport->ID;
                $price_based = get_post_meta($id, 'mptbm_price_based', true);
                $price_info = '';
                $pricing_details = array();
                
                if ($price_based === 'dynamic') {
                    $km_price = get_post_meta($id, 'mptbm_km_price', true);
                    $price_info = "Price per km/mile: " . $km_price;
                    $pricing_details['type'] = 'per_km';
                    $pricing_details['price'] = $km_price;
                    
                    // Add minimum charge if available
                    $min_price = get_post_meta($id, 'mptbm_min_price', true);
                    if (!empty($min_price)) {
                        $pricing_details['min_charge'] = $min_price;
                        $price_info .= ", Minimum charge: " . $min_price;
                    }
                } elseif ($price_based === 'fixed_hourly') {
                    $hour_price = get_post_meta($id, 'mptbm_hour_price', true);
                    $price_info = "Price per hour: " . $hour_price;
                    $pricing_details['type'] = 'per_hour';
                    $pricing_details['price'] = $hour_price;
                    
                    // Add minimum hours if available
                    $min_hours = get_post_meta($id, 'mptbm_min_hour', true);
                    if (!empty($min_hours)) {
                        $pricing_details['min_hours'] = $min_hours;
                        $price_info .= ", Minimum " . $min_hours . " hours";
                    }
                } else {
                    $manual_prices = get_post_meta($id, 'mptbm_manual_prices', true);
                    $price_info = "Fixed pricing based on locations";
                    $pricing_details['type'] = 'fixed';
                    
                    // Include specific location prices if available
                    if (is_array($manual_prices)) {
                        $pricing_details['routes'] = array();
                        foreach ($manual_prices as $index => $route) {
                            if (isset($route['mptbm_start_location']) && isset($route['mptbm_end_location']) && isset($route['mptbm_price'])) {
                                $pricing_details['routes'][] = array(
                                    'from' => $route['mptbm_start_location'],
                                    'to' => $route['mptbm_end_location'],
                                    'price' => $route['mptbm_price']
                                );
                            }
                        }
                    }
                }
                
                // Get available locations 
                $locations = array();
                $start_locations = get_post_meta($id, 'mptbm_start_location', true);
                $end_locations = get_post_meta($id, 'mptbm_end_location', true);
                
                if (is_array($start_locations)) {
                    foreach ($start_locations as $location) {
                        if (!empty($location) && !in_array($location, $locations)) {
                            $locations[] = $location;
                        }
                    }
                }
                
                if (is_array($end_locations)) {
                    foreach ($end_locations as $location) {
                        if (!empty($location) && !in_array($location, $locations)) {
                            $locations[] = $location;
                        }
                    }
                }
                
                $services[] = array(
                    'id' => $id,
                    'name' => $transport->post_title,
                    'price_based' => $price_based,
                    'price_info' => $price_info,
                    'pricing_details' => $pricing_details,
                    'max_passenger' => get_post_meta($id, 'mptbm_max_passenger', true),
                    'max_bag' => get_post_meta($id, 'mptbm_max_bag', true),
                    'locations' => $locations,
                    'description' => get_post_meta($id, 'mptbm_description', true)
                );
            }
            
            $faq_data = array();
            foreach ($transports as $transport) {
                $faq = get_post_meta($transport->ID, 'mptbm_faq', true);
                if (is_array($faq)) {
                    foreach ($faq as $item) {
                        if (isset($item['title']) && isset($item['content'])) {
                            $faq_data[] = array(
                                'question' => $item['title'],
                                'answer' => $item['content']
                            );
                        }
                    }
                }
            }
            
            // Store data for API context
            $this->plugin_data = array(
                'general_settings' => $general_settings,
                'price_settings' => $price_settings,
                'services' => $services,
                'faqs' => $faq_data,
                'booking_url' => home_url('/transport_booking/'),
                'plugin_version' => MPTBM_PLUGIN_VERSION
            );
        }
        
        /**
         * Add settings page to admin menu
         */
        public function add_settings_page() {
            add_submenu_page(
                'edit.php?post_type=mptbm_rent',
                __('AI Chatbot Settings', 'ecab-taxi-booking-manager'),
                __('AI Chatbot', 'ecab-taxi-booking-manager'),
                'manage_options',
                'mptbm_ai_chatbot',
                array($this, 'render_settings_page')
            );
        }
        
        /**
         * Register settings
         */
        public function register_settings() {
            register_setting('mptbm_ai_chatbot_group', 'mptbm_ai_chatbot_settings');
            
            add_settings_section(
                'mptbm_ai_chatbot_main',
                __('AI Chatbot Configuration', 'ecab-taxi-booking-manager'),
                array($this, 'settings_section_callback'),
                'mptbm_ai_chatbot'
            );
            
            add_settings_field(
                'mptbm_ai_chatbot_enable',
                __('Enable Chatbot', 'ecab-taxi-booking-manager'),
                array($this, 'enable_field_callback'),
                'mptbm_ai_chatbot',
                'mptbm_ai_chatbot_main'
            );
            
            add_settings_field(
                'mptbm_ai_chatbot_provider',
                __('AI Provider', 'ecab-taxi-booking-manager'),
                array($this, 'provider_field_callback'),
                'mptbm_ai_chatbot',
                'mptbm_ai_chatbot_main'
            );
            
            add_settings_field(
                'mptbm_ai_chatbot_api_key',
                __('API Key', 'ecab-taxi-booking-manager'),
                array($this, 'api_key_field_callback'),
                'mptbm_ai_chatbot',
                'mptbm_ai_chatbot_main'
            );
            
            add_settings_field(
                'mptbm_ai_chatbot_system_prompt',
                __('System Prompt', 'ecab-taxi-booking-manager'),
                array($this, 'system_prompt_field_callback'),
                'mptbm_ai_chatbot',
                'mptbm_ai_chatbot_main'
            );
            
            add_settings_field(
                'mptbm_ai_chatbot_appearance',
                __('Appearance', 'ecab-taxi-booking-manager'),
                array($this, 'appearance_field_callback'),
                'mptbm_ai_chatbot',
                'mptbm_ai_chatbot_main'
            );
            
            add_settings_field(
                'mptbm_ai_chatbot_history',
                __('Message History', 'ecab-taxi-booking-manager'),
                array($this, 'history_field_callback'),
                'mptbm_ai_chatbot',
                'mptbm_ai_chatbot_main'
            );
        }
        
        /**
         * Settings section description
         */
        public function settings_section_callback() {
            echo '<p>' . __('Configure the AI chatbot to help your customers with bookings and questions.', 'ecab-taxi-booking-manager') . '</p>';
        }
        
        /**
         * Enable chatbot field
         */
        public function enable_field_callback() {
            $enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : false;
            ?>
            <label>
                <input type="checkbox" name="mptbm_ai_chatbot_settings[enabled]" value="1" <?php checked(1, $enabled); ?> />
                <?php _e('Enable AI chatbot on the website', 'ecab-taxi-booking-manager'); ?>
            </label>
            <?php
        }
        
        /**
         * AI provider field
         */
        public function provider_field_callback() {
            $provider = isset($this->settings['ai_provider']) ? $this->settings['ai_provider'] : 'openai';
            ?>
            <select name="mptbm_ai_chatbot_settings[ai_provider]">
                <option value="openai" <?php selected('openai', $provider); ?>><?php _e('OpenAI', 'ecab-taxi-booking-manager'); ?></option>
                <option value="claude" <?php selected('claude', $provider); ?>><?php _e('Claude (Anthropic)', 'ecab-taxi-booking-manager'); ?></option>
                <option value="gemini" <?php selected('gemini', $provider); ?>><?php _e('Gemini (Google)', 'ecab-taxi-booking-manager'); ?></option>
            </select>
            <p class="description"><?php _e('Select the AI provider to use for the chatbot.', 'ecab-taxi-booking-manager'); ?></p>
            
            <div id="openai-model-selection" style="margin-top: 10px; <?php echo $provider !== 'openai' ? 'display: none;' : ''; ?>">
                <label><?php _e('OpenAI Model:', 'ecab-taxi-booking-manager'); ?></label>
                <?php 
                $openai_model = isset($this->settings['openai_model']) ? $this->settings['openai_model'] : 'gpt-3.5-turbo';
                ?>
                <select name="mptbm_ai_chatbot_settings[openai_model]">
                    <option value="gpt-4o" <?php selected('gpt-4o', $openai_model); ?>><?php _e('GPT-4o (Most Capable)', 'ecab-taxi-booking-manager'); ?></option>
                    <option value="gpt-4-turbo" <?php selected('gpt-4-turbo', $openai_model); ?>><?php _e('GPT-4 Turbo', 'ecab-taxi-booking-manager'); ?></option>
                    <option value="gpt-3.5-turbo" <?php selected('gpt-3.5-turbo', $openai_model); ?>><?php _e('GPT-3.5 Turbo (Economical)', 'ecab-taxi-booking-manager'); ?></option>
                </select>
            </div>
            
            <div id="claude-model-selection" style="margin-top: 10px; <?php echo $provider !== 'claude' ? 'display: none;' : ''; ?>">
                <label><?php _e('Claude Model:', 'ecab-taxi-booking-manager'); ?></label>
                <?php 
                $claude_model = isset($this->settings['claude_model']) ? $this->settings['claude_model'] : 'claude-3-sonnet-20240229';
                ?>
                <select name="mptbm_ai_chatbot_settings[claude_model]">
                    <option value="claude-3-opus-20240229" <?php selected('claude-3-opus-20240229', $claude_model); ?>><?php _e('Claude 3 Opus (Most Capable)', 'ecab-taxi-booking-manager'); ?></option>
                    <option value="claude-3-sonnet-20240229" <?php selected('claude-3-sonnet-20240229', $claude_model); ?>><?php _e('Claude 3 Sonnet (Balanced)', 'ecab-taxi-booking-manager'); ?></option>
                    <option value="claude-3-haiku-20240307" <?php selected('claude-3-haiku-20240307', $claude_model); ?>><?php _e('Claude 3 Haiku (Fastest)', 'ecab-taxi-booking-manager'); ?></option>
                    <option value="claude-2.0" <?php selected('claude-2.0', $claude_model); ?>><?php _e('Claude 2 (Legacy)', 'ecab-taxi-booking-manager'); ?></option>
                </select>
            </div>
            
            <div id="gemini-model-selection" style="margin-top: 10px; <?php echo $provider !== 'gemini' ? 'display: none;' : ''; ?>">
                <label><?php _e('Gemini Model:', 'ecab-taxi-booking-manager'); ?></label>
                <?php 
                $gemini_model = isset($this->settings['gemini_model']) ? $this->settings['gemini_model'] : 'gemini-pro';
                ?>
                <select name="mptbm_ai_chatbot_settings[gemini_model]">
                    <option value="gemini-1.5-pro" <?php selected('gemini-1.5-pro', $gemini_model); ?>><?php _e('Gemini 1.5 Pro (Most Capable)', 'ecab-taxi-booking-manager'); ?></option>
                    <option value="gemini-pro" <?php selected('gemini-pro', $gemini_model); ?>><?php _e('Gemini Pro (Standard)', 'ecab-taxi-booking-manager'); ?></option>
                    <option value="gemini-flash" <?php selected('gemini-flash', $gemini_model); ?>><?php _e('Gemini Flash (Fastest)', 'ecab-taxi-booking-manager'); ?></option>
                </select>
            </div>
            
            <script>
                jQuery(document).ready(function($) {
                    $('select[name="mptbm_ai_chatbot_settings[ai_provider]"]').on('change', function() {
                        // Hide all model selections first
                        $('#openai-model-selection, #claude-model-selection, #gemini-model-selection').hide();
                        
                        // Show the appropriate model selection based on the selected provider
                        if ($(this).val() === 'openai') {
                            $('#openai-model-selection').show();
                        } else if ($(this).val() === 'claude') {
                            $('#claude-model-selection').show();
                        } else if ($(this).val() === 'gemini') {
                            $('#gemini-model-selection').show();
                        }
                    });
                });
            </script>
            <?php
        }
        
        /**
         * API key field
         */
        public function api_key_field_callback() {
            $api_key = isset($this->settings['api_key']) ? $this->settings['api_key'] : '';
            ?>
            <input type="password" name="mptbm_ai_chatbot_settings[api_key]" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
            <p class="description"><?php _e('Enter your API key for the selected AI provider.', 'ecab-taxi-booking-manager'); ?></p>
            <button type="button" class="button" id="mptbm-test-api-key"><?php _e('Test API Key', 'ecab-taxi-booking-manager'); ?></button>
            <span id="mptbm-test-api-result" style="margin-left: 10px; display: inline-block;"></span>
            
            <script>
                jQuery(document).ready(function($) {
                    $('#mptbm-test-api-key').on('click', function() {
                        const apiKey = $('input[name="mptbm_ai_chatbot_settings[api_key]"]').val();
                        const provider = $('select[name="mptbm_ai_chatbot_settings[ai_provider]"]:checked').val();
                        
                        if (!apiKey) {
                            $('#mptbm-test-api-result').html('<span style="color: #dc3232;">Please enter an API key first</span>');
                            return;
                        }
                        
                        $('#mptbm-test-api-result').html('<span style="color: #666;">Testing...</span>');
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'mptbm_test_ai_api_key',
                                nonce: '<?php echo wp_create_nonce('mptbm_test_ai_api_key'); ?>',
                                api_key: apiKey,
                                provider: provider
                            },
                            success: function(response) {
                                if (response.success) {
                                    $('#mptbm-test-api-result').html('<span style="color: #46b450;">' + response.data + '</span>');
                                } else {
                                    $('#mptbm-test-api-result').html('<span style="color: #dc3232;">' + response.data + '</span>');
                                }
                            },
                            error: function() {
                                $('#mptbm-test-api-result').html('<span style="color: #dc3232;">Connection error. Please try again.</span>');
                            }
                        });
                    });
                });
            </script>
            <?php
        }
        
        /**
         * System prompt field
         */
        public function system_prompt_field_callback() {
            $system_prompt = isset($this->settings['system_prompt']) ? $this->settings['system_prompt'] : $this->get_default_system_prompt();
            ?>
            <textarea name="mptbm_ai_chatbot_settings[system_prompt]" rows="6" class="large-text"><?php echo esc_textarea($system_prompt); ?></textarea>
            <p class="description"><?php _e('The system prompt tells the AI how to behave and what context to use.', 'ecab-taxi-booking-manager'); ?></p>
            <button type="button" class="button" onclick="document.querySelector('textarea[name=\'mptbm_ai_chatbot_settings[system_prompt]\']').value = '<?php echo esc_js($this->get_default_system_prompt()); ?>';">
                <?php _e('Reset to Default', 'ecab-taxi-booking-manager'); ?>
            </button>
            <?php
        }
        
        /**
         * Appearance field
         */
        public function appearance_field_callback() {
            $primary_color = isset($this->settings['primary_color']) ? $this->settings['primary_color'] : '#0073aa';
            $chat_title = isset($this->settings['chat_title']) ? $this->settings['chat_title'] : __('Taxi Booking Assistant', 'ecab-taxi-booking-manager');
            $welcome_message = isset($this->settings['welcome_message']) ? $this->settings['welcome_message'] : __('Hi there! How can I help you with your taxi booking today?', 'ecab-taxi-booking-manager');
            ?>
            <p>
                <label><?php _e('Primary Color:', 'ecab-taxi-booking-manager'); ?></label>
                <input type="color" name="mptbm_ai_chatbot_settings[primary_color]" value="<?php echo esc_attr($primary_color); ?>" />
            </p>
            <p>
                <label><?php _e('Chat Title:', 'ecab-taxi-booking-manager'); ?></label>
                <input type="text" name="mptbm_ai_chatbot_settings[chat_title]" value="<?php echo esc_attr($chat_title); ?>" class="regular-text" />
            </p>
            <p>
                <label><?php _e('Welcome Message:', 'ecab-taxi-booking-manager'); ?></label>
                <input type="text" name="mptbm_ai_chatbot_settings[welcome_message]" value="<?php echo esc_attr($welcome_message); ?>" class="large-text" />
            </p>
            <?php
        }
        
        /**
         * Message history field
         */
        public function history_field_callback() {
            $save_history = isset($this->settings['save_history']) ? $this->settings['save_history'] : false;
            $history_limit = isset($this->settings['history_limit']) ? $this->settings['history_limit'] : 10;
            ?>
            <p>
                <label class="mptbm-toggle-switch">
                    <input type="checkbox" name="mptbm_ai_chatbot_settings[save_history]" value="1" <?php checked(1, $save_history); ?> />
                    <span class="mptbm-toggle-slider"></span>
                </label>
                <span class="description"><?php _e('Save chat history between sessions', 'ecab-taxi-booking-manager'); ?></span>
            </p>
            <p>
                <label><?php _e('Number of messages to remember:', 'ecab-taxi-booking-manager'); ?></label>
                <select name="mptbm_ai_chatbot_settings[history_limit]">
                    <option value="5" <?php selected(5, $history_limit); ?>>5</option>
                    <option value="10" <?php selected(10, $history_limit); ?>>10</option>
                    <option value="15" <?php selected(15, $history_limit); ?>>15</option>
                    <option value="20" <?php selected(20, $history_limit); ?>>20</option>
                    <option value="0" <?php selected(0, $history_limit); ?>><?php _e('Unlimited', 'ecab-taxi-booking-manager'); ?></option>
                </select>
                <p class="description"><?php _e('Number of previous messages to save. Note that more messages will use more API tokens.', 'ecab-taxi-booking-manager'); ?></p>
            </p>
            <?php
        }
        
        /**
         * Render settings page
         */
        public function render_settings_page() {
            if (!current_user_can('manage_options')) {
                return;
            }
            
            // Get plugin colors
            $primary_color = isset($this->settings['primary_color']) ? $this->settings['primary_color'] : '#0073aa';
            ?>
            <div class="wrap mptbm-chatbot-settings-wrap">
                <h1 class="mptbm-settings-title">
                    <span class="dashicons dashicons-format-chat"></span>
                    <?php echo esc_html(get_admin_page_title()); ?>
                </h1>
                
                <div class="mptbm-settings-header">
                    <div class="mptbm-header-info">
                        <h2><?php _e('Enhance Your Taxi Booking with AI Assistance', 'ecab-taxi-booking-manager'); ?></h2>
                        <p><?php _e('Configure your AI chatbot to provide instant answers to customer questions about your taxi services, pricing, and booking process.', 'ecab-taxi-booking-manager'); ?></p>
                    </div>
                    <div class="mptbm-header-actions">
                        <a href="<?php echo admin_url('edit.php?post_type=mptbm_rent&page=mptbm_documentation&tab=ai-chatbot'); ?>" class="button button-secondary">
                            <span class="dashicons dashicons-book"></span>
                            <?php _e('Documentation', 'ecab-taxi-booking-manager'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="mptbm-settings-container">
                    <form action="options.php" method="post" class="mptbm-settings-form">
                        <?php
                        settings_fields('mptbm_ai_chatbot_group');
                        ?>
                        
                        <div class="mptbm-settings-sections">
                            <!-- Basic Settings Section -->
                            <div class="mptbm-settings-section">
                                <div class="mptbm-section-header">
                                    <h3><span class="dashicons dashicons-admin-generic"></span> <?php _e('Basic Settings', 'ecab-taxi-booking-manager'); ?></h3>
                                </div>
                                <div class="mptbm-section-content">
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row"><?php _e('Enable Chatbot', 'ecab-taxi-booking-manager'); ?></th>
                                            <td>
                                                <?php 
                                                $enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : false;
                                                ?>
                                                <label class="mptbm-toggle-switch">
                                                    <input type="checkbox" name="mptbm_ai_chatbot_settings[enabled]" value="1" <?php checked(1, $enabled); ?> />
                                                    <span class="mptbm-toggle-slider"></span>
                                                </label>
                                                <span class="description"><?php _e('Enable AI chatbot on the website', 'ecab-taxi-booking-manager'); ?></span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- AI Provider Section -->
                            <div class="mptbm-settings-section">
                                <div class="mptbm-section-header">
                                    <h3><span class="dashicons dashicons-rest-api"></span> <?php _e('AI Provider Configuration', 'ecab-taxi-booking-manager'); ?></h3>
                                </div>
                                <div class="mptbm-section-content">
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row"><?php _e('AI Provider', 'ecab-taxi-booking-manager'); ?></th>
                                            <td>
                                                <?php 
                                                $provider = isset($this->settings['ai_provider']) ? $this->settings['ai_provider'] : 'openai';
                                                ?>
                                                <div class="mptbm-provider-selector">
                                                    <div class="mptbm-provider-options">
                                                        <label class="mptbm-provider-option <?php echo $provider === 'openai' ? 'active' : ''; ?>" data-provider="openai">
                                                            <input type="radio" name="mptbm_ai_chatbot_settings[ai_provider]" value="openai" <?php checked('openai', $provider); ?> />
                                                            <span class="mptbm-provider-logo openai-logo">OpenAI</span>
                                                        </label>
                                                        <label class="mptbm-provider-option <?php echo $provider === 'claude' ? 'active' : ''; ?>" data-provider="claude">
                                                            <input type="radio" name="mptbm_ai_chatbot_settings[ai_provider]" value="claude" <?php checked('claude', $provider); ?> />
                                                            <span class="mptbm-provider-logo claude-logo">Claude</span>
                                                        </label>
                                                        <label class="mptbm-provider-option <?php echo $provider === 'gemini' ? 'active' : ''; ?>" data-provider="gemini">
                                                            <input type="radio" name="mptbm_ai_chatbot_settings[ai_provider]" value="gemini" <?php checked('gemini', $provider); ?> />
                                                            <span class="mptbm-provider-logo gemini-logo">Gemini</span>
                                                        </label>
                                                    </div>
                                                    
                                                    <!-- OpenAI Model Selection -->
                                                    <div id="openai-model-selection" class="mptbm-model-selection" <?php echo $provider !== 'openai' ? 'style="display: none;"' : ''; ?>>
                                                        <label><?php _e('Model:', 'ecab-taxi-booking-manager'); ?></label>
                                                        <?php 
                                                        $openai_model = isset($this->settings['openai_model']) ? $this->settings['openai_model'] : 'gpt-3.5-turbo';
                                                        ?>
                                                        <select name="mptbm_ai_chatbot_settings[openai_model]">
                                                            <option value="gpt-4o" <?php selected('gpt-4o', $openai_model); ?>><?php _e('GPT-4o (Most Capable)', 'ecab-taxi-booking-manager'); ?></option>
                                                            <option value="gpt-4-turbo" <?php selected('gpt-4-turbo', $openai_model); ?>><?php _e('GPT-4 Turbo', 'ecab-taxi-booking-manager'); ?></option>
                                                            <option value="gpt-3.5-turbo" <?php selected('gpt-3.5-turbo', $openai_model); ?>><?php _e('GPT-3.5 Turbo (Economical)', 'ecab-taxi-booking-manager'); ?></option>
                                                        </select>
                                                    </div>
                                                    
                                                    <!-- Claude Model Selection -->
                                                    <div id="claude-model-selection" class="mptbm-model-selection" <?php echo $provider !== 'claude' ? 'style="display: none;"' : ''; ?>>
                                                        <label><?php _e('Model:', 'ecab-taxi-booking-manager'); ?></label>
                                                        <?php 
                                                        $claude_model = isset($this->settings['claude_model']) ? $this->settings['claude_model'] : 'claude-3-sonnet-20240229';
                                                        ?>
                                                        <select name="mptbm_ai_chatbot_settings[claude_model]">
                                                            <option value="claude-3-opus-20240229" <?php selected('claude-3-opus-20240229', $claude_model); ?>><?php _e('Claude 3 Opus (Most Capable)', 'ecab-taxi-booking-manager'); ?></option>
                                                            <option value="claude-3-sonnet-20240229" <?php selected('claude-3-sonnet-20240229', $claude_model); ?>><?php _e('Claude 3 Sonnet (Balanced)', 'ecab-taxi-booking-manager'); ?></option>
                                                            <option value="claude-3-haiku-20240307" <?php selected('claude-3-haiku-20240307', $claude_model); ?>><?php _e('Claude 3 Haiku (Fastest)', 'ecab-taxi-booking-manager'); ?></option>
                                                            <option value="claude-2.0" <?php selected('claude-2.0', $claude_model); ?>><?php _e('Claude 2 (Legacy)', 'ecab-taxi-booking-manager'); ?></option>
                                                        </select>
                                                    </div>
                                                    
                                                    <!-- Gemini Model Selection -->
                                                    <div id="gemini-model-selection" class="mptbm-model-selection" <?php echo $provider !== 'gemini' ? 'style="display: none;"' : ''; ?>>
                                                        <label><?php _e('Model:', 'ecab-taxi-booking-manager'); ?></label>
                                                        <?php 
                                                        $gemini_model = isset($this->settings['gemini_model']) ? $this->settings['gemini_model'] : 'gemini-pro';
                                                        ?>
                                                        <select name="mptbm_ai_chatbot_settings[gemini_model]">
                                                            <option value="gemini-1.5-pro" <?php selected('gemini-1.5-pro', $gemini_model); ?>><?php _e('Gemini 1.5 Pro (Most Capable)', 'ecab-taxi-booking-manager'); ?></option>
                                                            <option value="gemini-pro" <?php selected('gemini-pro', $gemini_model); ?>><?php _e('Gemini Pro (Standard)', 'ecab-taxi-booking-manager'); ?></option>
                                                            <option value="gemini-flash" <?php selected('gemini-flash', $gemini_model); ?>><?php _e('Gemini Flash (Fastest)', 'ecab-taxi-booking-manager'); ?></option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php _e('API Key', 'ecab-taxi-booking-manager'); ?></th>
                                            <td>
                                                <div class="mptbm-api-key-container">
                                                    <input type="password" name="mptbm_ai_chatbot_settings[api_key]" value="<?php echo esc_attr(isset($this->settings['api_key']) ? $this->settings['api_key'] : ''); ?>" class="regular-text mptbm-api-key-input" />
                                                    <div class="mptbm-api-key-actions">
                                                        <button type="button" class="button" id="mptbm-test-api-key"><?php _e('Test API Key', 'ecab-taxi-booking-manager'); ?></button>
                                                        <button type="button" class="button mptbm-toggle-password"><?php _e('Show', 'ecab-taxi-booking-manager'); ?></button>
                                                    </div>
                                                    <span id="mptbm-test-api-result" class="mptbm-api-test-result"></span>
                                                </div>
                                                <p class="description"><?php _e('Enter your API key for the selected AI provider. Your API key is stored securely and used only for communicating with the AI service.', 'ecab-taxi-booking-manager'); ?></p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Chatbot Content Section -->
                            <div class="mptbm-settings-section">
                                <div class="mptbm-section-header">
                                    <h3><span class="dashicons dashicons-editor-paste-text"></span> <?php _e('Chatbot Content', 'ecab-taxi-booking-manager'); ?></h3>
                                </div>
                                <div class="mptbm-section-content">
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row"><?php _e('System Prompt', 'ecab-taxi-booking-manager'); ?></th>
                                            <td>
                                                <?php 
                                                $system_prompt = isset($this->settings['system_prompt']) ? $this->settings['system_prompt'] : $this->get_default_system_prompt();
                                                ?>
                                                <textarea name="mptbm_ai_chatbot_settings[system_prompt]" rows="6" class="large-text mptbm-textarea"><?php echo esc_textarea($system_prompt); ?></textarea>
                                                <p class="description"><?php _e('The system prompt tells the AI how to behave and what context to use.', 'ecab-taxi-booking-manager'); ?></p>
                                                <button type="button" class="button" id="mptbm-reset-prompt"><?php _e('Reset to Default', 'ecab-taxi-booking-manager'); ?></button>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Appearance Section -->
                            <div class="mptbm-settings-section">
                                <div class="mptbm-section-header">
                                    <h3><span class="dashicons dashicons-admin-appearance"></span> <?php _e('Appearance', 'ecab-taxi-booking-manager'); ?></h3>
                                </div>
                                <div class="mptbm-section-content">
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row"><?php _e('Primary Color', 'ecab-taxi-booking-manager'); ?></th>
                                            <td>
                                                <div class="mptbm-color-picker-container">
                                                    <input type="color" name="mptbm_ai_chatbot_settings[primary_color]" value="<?php echo esc_attr($primary_color); ?>" class="mptbm-color-picker" />
                                                    <div class="mptbm-color-preview" style="background-color: <?php echo esc_attr($primary_color); ?>;"></div>
                                                </div>
                                                <p class="description"><?php _e('Choose the primary color for the chatbot interface.', 'ecab-taxi-booking-manager'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php _e('Chat Title', 'ecab-taxi-booking-manager'); ?></th>
                                            <td>
                                                <?php 
                                                $chat_title = isset($this->settings['chat_title']) ? $this->settings['chat_title'] : __('Taxi Booking Assistant', 'ecab-taxi-booking-manager');
                                                ?>
                                                <input type="text" name="mptbm_ai_chatbot_settings[chat_title]" value="<?php echo esc_attr($chat_title); ?>" class="regular-text" />
                                                <p class="description"><?php _e('The title displayed in the chat header.', 'ecab-taxi-booking-manager'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php _e('Welcome Message', 'ecab-taxi-booking-manager'); ?></th>
                                            <td>
                                                <?php 
                                                $welcome_message = isset($this->settings['welcome_message']) ? $this->settings['welcome_message'] : __('Hi there! How can I help you with your taxi booking today?', 'ecab-taxi-booking-manager');
                                                ?>
                                                <input type="text" name="mptbm_ai_chatbot_settings[welcome_message]" value="<?php echo esc_attr($welcome_message); ?>" class="large-text" />
                                                <p class="description"><?php _e('The initial message displayed when a user opens the chat.', 'ecab-taxi-booking-manager'); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php _e('Preview', 'ecab-taxi-booking-manager'); ?></th>
                                            <td>
                                                <div class="mptbm-chatbot-preview" style="--preview-primary-color: <?php echo esc_attr($primary_color); ?>;">
                                                    <div class="mptbm-preview-header">
                                                        <h3 id="preview-title"><?php echo esc_html($chat_title); ?></h3>
                                                        <button class="mptbm-preview-close">&times;</button>
                                                    </div>
                                                    <div class="mptbm-preview-messages">
                                                        <div class="mptbm-preview-message bot">
                                                            <div class="mptbm-preview-bubble" id="preview-welcome"><?php echo esc_html($welcome_message); ?></div>
                                                        </div>
                                                        <div class="mptbm-preview-message user">
                                                            <div class="mptbm-preview-bubble"><?php _e('How much does it cost to book a taxi?', 'ecab-taxi-booking-manager'); ?></div>
                                                        </div>
                                                        <div class="mptbm-preview-message bot">
                                                            <div class="mptbm-preview-bubble"><?php _e('Our taxi fares depend on the distance and type of vehicle. We offer fixed rates for airport transfers and hourly options for city tours. Would you like me to calculate a fare for a specific journey?', 'ecab-taxi-booking-manager'); ?></div>
                                                        </div>
                                                    </div>
                                                    <div class="mptbm-preview-input">
                                                        <div class="mptbm-preview-textarea"><?php _e('Type your message...', 'ecab-taxi-booking-manager'); ?></div>
                                                        <button class="mptbm-preview-send"></button>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mptbm-settings-footer">
                            <?php submit_button(__('Save Settings', 'ecab-taxi-booking-manager'), 'primary', 'submit', false); ?>
                        </div>
                    </form>
                </div>
                
                <style>
                    /* Settings Page Styles */
                    .mptbm-chatbot-settings-wrap {
                        max-width: 1200px;
                        margin: 20px 0;
                    }
                    
                    .mptbm-settings-title {
                        display: flex;
                        align-items: center;
                        margin-bottom: 20px;
                    }
                    
                    .mptbm-settings-title .dashicons {
                        font-size: 30px;
                        width: 30px;
                        height: 30px;
                        margin-right: 10px;
                    }
                    
                    .mptbm-settings-header {
                        background: white;
                        border-radius: 4px;
                        padding: 20px;
                        margin-bottom: 20px;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                    }
                    
                    .mptbm-header-info h2 {
                        margin-top: 0;
                        margin-bottom: 10px;
                    }
                    
                    .mptbm-header-info p {
                        margin: 0;
                        font-size: 14px;
                        color: #666;
                    }
                    
                    .mptbm-header-actions .button {
                        display: flex;
                        align-items: center;
                    }
                    
                    .mptbm-header-actions .dashicons {
                        margin-right: 5px;
                    }
                    
                    .mptbm-settings-container {
                        background: white;
                        border-radius: 4px;
                        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                    }
                    
                    .mptbm-settings-section {
                        border-bottom: 1px solid #eee;
                        margin-bottom: 20px;
                    }
                    
                    .mptbm-settings-section:last-child {
                        border-bottom: none;
                    }
                    
                    .mptbm-section-header {
                        padding: 15px 20px;
                        border-bottom: 1px solid #f0f0f0;
                    }
                    
                    .mptbm-section-header h3 {
                        margin: 0;
                        display: flex;
                        align-items: center;
                        font-size: 16px;
                    }
                    
                    .mptbm-section-header .dashicons {
                        margin-right: 8px;
                        color: <?php echo esc_attr($primary_color); ?>;
                    }
                    
                    .mptbm-section-content {
                        padding: 20px;
                    }
                    
                    /* Toggle Switch */
                    .mptbm-toggle-switch {
                        position: relative;
                        display: inline-block;
                        width: 50px;
                        height: 24px;
                        margin-right: 10px;
                    }
                    
                    .mptbm-toggle-switch input {
                        opacity: 0;
                        width: 0;
                        height: 0;
                    }
                    
                    .mptbm-toggle-slider {
                        position: absolute;
                        cursor: pointer;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background-color: #ccc;
                        transition: .4s;
                        border-radius: 24px;
                    }
                    
                    .mptbm-toggle-slider:before {
                        position: absolute;
                        content: "";
                        height: 16px;
                        width: 16px;
                        left: 4px;
                        bottom: 4px;
                        background-color: white;
                        transition: .4s;
                        border-radius: 50%;
                    }
                    
                    input:checked + .mptbm-toggle-slider {
                        background-color: <?php echo esc_attr($primary_color); ?>;
                    }
                    
                    input:checked + .mptbm-toggle-slider:before {
                        transform: translateX(26px);
                    }
                    
                    /* Provider Selector */
                    .mptbm-provider-selector {
                        margin-bottom: 15px;
                    }
                    
                    .mptbm-provider-options {
                        display: flex;
                        gap: 15px;
                        margin-bottom: 15px;
                    }
                    
                    .mptbm-provider-option {
                        position: relative;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        width: 120px;
                        height: 60px;
                        border: 2px solid #ddd;
                        border-radius: 6px;
                        cursor: pointer;
                        transition: all 0.3s;
                        background: #f9f9f9;
                    }
                    
                    .mptbm-provider-option:hover {
                        border-color: #bbb;
                    }
                    
                    .mptbm-provider-option.active {
                        border-color: <?php echo esc_attr($primary_color); ?>;
                        background: #f0f7fb;
                    }
                    
                    .mptbm-provider-option input {
                        position: absolute;
                        opacity: 0;
                    }
                    
                    .mptbm-provider-logo {
                        font-weight: bold;
                        font-size: 16px;
                    }
                    
                    .openai-logo {
                        color: #10a37f;
                    }
                    
                    .claude-logo {
                        color: #7f64fa;
                    }
                    
                    .gemini-logo {
                        color: #1a73e8;
                    }
                    
                    /* Model Selection */
                    .mptbm-model-selection {
                        background: #f9f9f9;
                        padding: 10px 15px;
                        border-radius: 4px;
                        display: flex;
                        align-items: center;
                    }
                    
                    .mptbm-model-selection label {
                        margin-right: 10px;
                        font-weight: 500;
                    }
                    
                    .mptbm-model-selection select {
                        min-width: 250px;
                    }
                    
                    /* API Key Styles */
                    .mptbm-api-key-container {
                        display: flex;
                        flex-wrap: wrap;
                        align-items: flex-start;
                        gap: 10px;
                    }
                    
                    .mptbm-api-key-input {
                        flex: 1;
                        min-width: 300px;
                    }
                    
                    .mptbm-api-key-actions {
                        display: flex;
                        gap: 10px;
                    }
                    
                    .mptbm-api-test-result {
                        width: 100%;
                        margin-top: 5px;
                        font-style: italic;
                    }
                    
                    /* Textarea */
                    .mptbm-textarea {
                        font-family: monospace;
                        resize: vertical;
                    }
                    
                    /* Color Picker */
                    .mptbm-color-picker-container {
                        display: flex;
                        align-items: center;
                        gap: 10px;
                    }
                    
                    .mptbm-color-preview {
                        width: 30px;
                        height: 30px;
                        border-radius: 4px;
                        border: 1px solid #ddd;
                    }
                    
                    /* Preview */
                    .mptbm-chatbot-preview {
                        --preview-primary-color: <?php echo esc_attr($primary_color); ?>;
                        width: 320px;
                        height: 400px;
                        border: 1px solid #ddd;
                        border-radius: 10px;
                        overflow: hidden;
                        display: flex;
                        flex-direction: column;
                        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                    }
                    
                    .mptbm-preview-header {
                        background-color: var(--preview-primary-color);
                        color: white;
                        padding: 12px 15px;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    }
                    
                    .mptbm-preview-header h3 {
                        margin: 0;
                        font-size: 16px;
                        font-weight: 500;
                    }
                    
                    .mptbm-preview-close {
                        background: none;
                        border: none;
                        color: white;
                        font-size: 20px;
                        cursor: pointer;
                        padding: 0;
                        margin: 0;
                        width: 24px;
                        height: 24px;
                        line-height: 24px;
                        text-align: center;
                    }
                    
                    .mptbm-preview-messages {
                        flex: 1;
                        padding: 15px;
                        overflow-y: auto;
                        background-color: #f9f9f9;
                        display: flex;
                        flex-direction: column;
                        gap: 10px;
                    }
                    
                    .mptbm-preview-message {
                        display: flex;
                    }
                    
                    .mptbm-preview-message.user {
                        justify-content: flex-end;
                    }
                    
                    .mptbm-preview-bubble {
                        max-width: 80%;
                        padding: 10px 12px;
                        border-radius: 18px;
                        line-height: 1.4;
                        font-size: 14px;
                    }
                    
                    .mptbm-preview-message.user .mptbm-preview-bubble {
                        background-color: var(--preview-primary-color);
                        color: white;
                        border-bottom-right-radius: 4px;
                    }
                    
                    .mptbm-preview-message.bot .mptbm-preview-bubble {
                        background-color: white;
                        border: 1px solid #eee;
                        border-bottom-left-radius: 4px;
                    }
                    
                    .mptbm-preview-input {
                        display: flex;
                        padding: 10px;
                        border-top: 1px solid #eee;
                        background-color: white;
                    }
                    
                    .mptbm-preview-textarea {
                        flex: 1;
                        border: 1px solid #ddd;
                        border-radius: 18px;
                        padding: 8px 12px;
                        font-size: 14px;
                        color: #777;
                    }
                    
                    .mptbm-preview-send {
                        width: 32px;
                        height: 32px;
                        border-radius: 50%;
                        background-color: var(--preview-primary-color);
                        color: white;
                        border: none;
                        margin-left: 10px;
                        cursor: pointer;
                        position: relative;
                    }
                    
                    .mptbm-preview-send:before {
                        content: '';
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        width: 12px;
                        height: 12px;
                        border-top: 2px solid white;
                        border-right: 2px solid white;
                        transform: translate(-75%, -50%) rotate(45deg);
                    }
                    
                    /* Settings Footer */
                    .mptbm-settings-footer {
                        padding: 20px;
                        border-top: 1px solid #eee;
                        text-align: right;
                    }
                </style>
                
                <script>
                    jQuery(document).ready(function($) {
                        // Toggle API Key visibility
                        $('.mptbm-toggle-password').on('click', function() {
                            var input = $('.mptbm-api-key-input');
                            var type = input.attr('type') === 'password' ? 'text' : 'password';
                            input.attr('type', type);
                            $(this).text(type === 'password' ? '<?php _e('Show', 'ecab-taxi-booking-manager'); ?>' : '<?php _e('Hide', 'ecab-taxi-booking-manager'); ?>');
                        });
                        
                        // Reset prompt to default
                        $('#mptbm-reset-prompt').on('click', function() {
                            $('textarea[name="mptbm_ai_chatbot_settings[system_prompt]"]').val('<?php echo esc_js($this->get_default_system_prompt()); ?>');
                        });
                        
                        // Provider selection
                        $('.mptbm-provider-option').on('click', function() {
                            $('.mptbm-provider-option').removeClass('active');
                            $(this).addClass('active');
                            
                            var provider = $(this).data('provider');
                            $('input[name="mptbm_ai_chatbot_settings[ai_provider]"][value="' + provider + '"]').prop('checked', true);
                            
                            // Hide all model selections
                            $('#openai-model-selection, #claude-model-selection, #gemini-model-selection').hide();
                            
                            // Show the correct model selection
                            $('#' + provider + '-model-selection').show();
                        });
                        
                        // Live preview updates
                        $('input[name="mptbm_ai_chatbot_settings[chat_title]"]').on('input', function() {
                            $('#preview-title').text($(this).val());
                        });
                        
                        $('input[name="mptbm_ai_chatbot_settings[welcome_message]"]').on('input', function() {
                            $('#preview-welcome').text($(this).val());
                        });
                        
                        $('input[name="mptbm_ai_chatbot_settings[primary_color]"]').on('input', function() {
                            var color = $(this).val();
                            $('.mptbm-color-preview').css('background-color', color);
                            $('.mptbm-chatbot-preview').css('--preview-primary-color', color);
                        });
                        
                        // Existing AJAX test API key functionality
                        $('#mptbm-test-api-key').on('click', function() {
                            const apiKey = $('input[name="mptbm_ai_chatbot_settings[api_key]"]').val();
                            const provider = $('input[name="mptbm_ai_chatbot_settings[ai_provider]"]:checked').val();
                            
                            if (!apiKey) {
                                $('#mptbm-test-api-result').html('<span style="color: #dc3232;"><?php _e('Please enter an API key first', 'ecab-taxi-booking-manager'); ?></span>');
                                return;
                            }
                            
                            $('#mptbm-test-api-result').html('<span style="color: #666;"><?php _e('Testing...', 'ecab-taxi-booking-manager'); ?></span>');
                            
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'mptbm_test_ai_api_key',
                                    nonce: '<?php echo wp_create_nonce('mptbm_test_ai_api_key'); ?>',
                                    api_key: apiKey,
                                    provider: provider
                                },
                                success: function(response) {
                                    if (response.success) {
                                        $('#mptbm-test-api-result').html('<span style="color: #46b450;">' + response.data + '</span>');
                                    } else {
                                        $('#mptbm-test-api-result').html('<span style="color: #dc3232;">' + response.data + '</span>');
                                    }
                                },
                                error: function() {
                                    $('#mptbm-test-api-result').html('<span style="color: #dc3232;"><?php _e('Connection error. Please try again.', 'ecab-taxi-booking-manager'); ?></span>');
                                }
                            });
                        });
                    });
                </script>
            </div>
            <?php
        }
        
        /**
         * Enqueue frontend assets
         */
        public function enqueue_assets() {
            if (!isset($this->settings['enabled']) || !$this->settings['enabled']) {
                return;
            }
            
            wp_enqueue_style(
                'mptbm-ai-chatbot',
                MPTBM_PLUGIN_URL . '/assets/css/ai-chatbot.css',
                array(),
                MPTBM_PLUGIN_VERSION
            );
            
            wp_enqueue_script(
                'mptbm-ai-chatbot',
                MPTBM_PLUGIN_URL . '/assets/js/ai-chatbot.js',
                array('jquery'),
                MPTBM_PLUGIN_VERSION,
                true
            );
            
            wp_localize_script(
                'mptbm-ai-chatbot',
                'mptbmAIChatbot',
                array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('mptbm_ai_chatbot_nonce'),
                    'chatTitle' => isset($this->settings['chat_title']) ? $this->settings['chat_title'] : __('Taxi Booking Assistant', 'ecab-taxi-booking-manager'),
                    'welcomeMessage' => isset($this->settings['welcome_message']) ? $this->settings['welcome_message'] : __('Hi there! How can I help you with your taxi booking today?', 'ecab-taxi-booking-manager'),
                    'saveHistory' => isset($this->settings['save_history']) ? (bool)$this->settings['save_history'] : false,
                    'historyLimit' => isset($this->settings['history_limit']) ? (int)$this->settings['history_limit'] : 10,
                    'i18n' => array(
                        'send' => __('Send', 'ecab-taxi-booking-manager'),
                        'typing' => __('Typing...', 'ecab-taxi-booking-manager'),
                        'error' => __('Error connecting to assistant. Please try again.', 'ecab-taxi-booking-manager'),
                        'placeholder' => __('Type your message here...', 'ecab-taxi-booking-manager'),
                        'confirmClear' => __('Are you sure you want to clear the chat history?', 'ecab-taxi-booking-manager'),
                        'previousConversation' => __('Previous conversation', 'ecab-taxi-booking-manager'),
                        'clearChat' => __('Clear Chat', 'ecab-taxi-booking-manager')
                    )
                )
            );
        }
        
        /**
         * Render chat widget
         */
        public function render_chat_widget() {
            if (!isset($this->settings['enabled']) || !$this->settings['enabled']) {
                return;
            }
            
            $primary_color = isset($this->settings['primary_color']) ? $this->settings['primary_color'] : '#0073aa';
            $chat_title = isset($this->settings['chat_title']) ? $this->settings['chat_title'] : __('Taxi Booking Assistant', 'ecab-taxi-booking-manager');
            
            // Get saved history if enabled
            $history_data = '';
            if (isset($this->settings['save_history']) && $this->settings['save_history']) {
                $session_id = $this->get_session_id();
                $history = get_transient('mptbm_chat_history_' . $session_id);
                if ($history) {
                    $history_data = 'data-history="' . esc_attr(json_encode($history)) . '"';
                }
            }
            ?>
            <div id="mptbm-ai-chatbot" style="--primary-color: <?php echo esc_attr($primary_color); ?>" <?php echo $history_data; ?>>
                <div class="mptbm-chatbot-button">
                    <span class="mptbm-chatbot-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="none" d="M0 0h24v24H0z"/><path d="M12 2c5.523 0 10 4.477 10 10s-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2zm0 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16zm0 10a1 1 0 1 1 0 2 1 1 0 0 1 0-2zm0-8a4 4 0 0 1 3.464 6.01L15 12.5a.5.5 0 0 1-.5.5h-2A.5.5 0 0 1 12 12.5V10a.5.5 0 0 1 .5-.5 1.5 1.5 0 1 0-1.5-1.5.5.5 0 1 1-1 0 2.5 2.5 0 0 1 2-2.45V4.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v.55z" fill="currentColor"/></svg>
                    </span>
                </div>
                <div class="mptbm-chatbot-container">
                    <div class="mptbm-chatbot-header">
                        <h3><?php echo esc_html($chat_title); ?></h3>
                        <div class="mptbm-chatbot-actions">
                            <button class="mptbm-chatbot-clear" title="<?php esc_attr_e('Clear Chat', 'ecab-taxi-booking-manager'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                            </button>
                            <button class="mptbm-chatbot-close" title="<?php esc_attr_e('Close Chat', 'ecab-taxi-booking-manager'); ?>">&times;</button>
                        </div>
                    </div>
                    <div class="mptbm-chatbot-messages"></div>
                    <div class="mptbm-chatbot-input">
                        <textarea placeholder="<?php esc_attr_e('Type your message here...', 'ecab-taxi-booking-manager'); ?>"></textarea>
                        <button class="mptbm-chatbot-send">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="none" d="M0 0h24v24H0z"/><path d="M1.946 9.315c-.522-.174-.527-.455.01-.634l19.087-6.362c.529-.176.832.12.684.638l-5.454 19.086c-.15.529-.455.547-.679.045L12 14l6-8-8 6-8.054-2.685z" fill="currentColor"/></svg>
                        </button>
                    </div>
                </div>
            </div>
            <?php
        }
        
        /**
         * Handle chat message AJAX request
         */
        public function handle_chat_message() {
            // Check nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mptbm_ai_chatbot_nonce')) {
                wp_send_json_error('Invalid nonce');
            }
            
            // Get message
            $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
            if (empty($message)) {
                wp_send_json_error('Empty message');
            }
            
            // Get conversation history
            $history = isset($_POST['history']) ? json_decode(stripslashes($_POST['history']), true) : array();
            if (!is_array($history)) {
                $history = array();
            }
            
            // Add user message to history
            $history[] = array(
                'role' => 'user',
                'content' => $message
            );
            
            // Get AI response
            $response = $this->get_ai_response($history);
            
            if (is_wp_error($response)) {
                wp_send_json_error($response->get_error_message());
            }
            
            // Add AI response to history
            $history[] = array(
                'role' => 'assistant',
                'content' => $response
            );
            
            // Save history to user session if enabled
            if (isset($this->settings['save_history']) && $this->settings['save_history']) {
                $session_id = $this->get_session_id();
                $history_limit = isset($this->settings['history_limit']) ? (int)$this->settings['history_limit'] : 10;
                
                // Limit history length if needed
                if ($history_limit > 0 && count($history) > $history_limit * 2) { // *2 because each exchange has 2 messages
                    $history = array_slice($history, -($history_limit * 2));
                }
                
                // Store in transient with 24-hour expiry
                set_transient('mptbm_chat_history_' . $session_id, $history, DAY_IN_SECONDS);
            }
            
            wp_send_json_success(array(
                'response' => $response,
                'history' => $history
            ));
        }
        
        /**
         * Clear chat history AJAX handler
         */
        public function clear_chat_history() {
            // Check nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mptbm_ai_chatbot_nonce')) {
                wp_send_json_error('Invalid nonce');
            }
            
            $session_id = $this->get_session_id();
            delete_transient('mptbm_chat_history_' . $session_id);
            
            wp_send_json_success('Chat history cleared');
        }
        
        /**
         * Get a unique session ID for the current user
         */
        private function get_session_id() {
            if (is_user_logged_in()) {
                return 'user_' . get_current_user_id();
            } else {
                if (!isset($_COOKIE['mptbm_chat_session'])) {
                    $session_id = uniqid('visitor_');
                    setcookie('mptbm_chat_session', $session_id, time() + 30 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl());
                    return $session_id;
                }
                return sanitize_text_field($_COOKIE['mptbm_chat_session']);
            }
        }
        
        /**
         * Get AI response from provider API
         */
        private function get_ai_response($conversation_history) {
            if (empty($this->api_key)) {
                return new WP_Error('missing_api_key', __('API key is not configured.', 'ecab-taxi-booking-manager'));
            }
            
            // Prepare system prompt with plugin data
            $enhanced_system_prompt = $this->system_prompt . "\n\n" . $this->get_plugin_context();
            
            // Format the conversation based on provider
            switch ($this->ai_provider) {
                case 'openai':
                    return $this->get_openai_response($enhanced_system_prompt, $conversation_history);
                case 'claude':
                    return $this->get_claude_response($enhanced_system_prompt, $conversation_history);
                case 'gemini':
                    return $this->get_gemini_response($enhanced_system_prompt, $conversation_history);
                default:
                    return new WP_Error('invalid_provider', __('Invalid AI provider.', 'ecab-taxi-booking-manager'));
            }
        }
        
        /**
         * Get response from OpenAI API
         */
        private function get_openai_response($system_prompt, $conversation_history) {
            $messages = array(
                array(
                    'role' => 'system',
                    'content' => $system_prompt
                )
            );
            
            // Add conversation history
            foreach ($conversation_history as $message) {
                $messages[] = array(
                    'role' => $message['role'],
                    'content' => $message['content']
                );
            }
            
            // Get selected OpenAI model
            $openai_model = isset($this->settings['openai_model']) ? $this->settings['openai_model'] : 'gpt-3.5-turbo';
            
            $args = array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array(
                    'model' => $openai_model,
                    'messages' => $messages,
                    'max_tokens' => 500,
                    'temperature' => 0.7
                )),
                'timeout' => 30
            );
            
            $response = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($body['error'])) {
                return new WP_Error('openai_error', $body['error']['message']);
            }
            
            if (!isset($body['choices'][0]['message']['content'])) {
                return new WP_Error('openai_error', __('Invalid response from OpenAI.', 'ecab-taxi-booking-manager'));
            }
            
            return $body['choices'][0]['message']['content'];
        }
        
        /**
         * Get response from Claude API
         */
        private function get_claude_response($system_prompt, $conversation_history) {
            // Claude API expects a different format than OpenAI
            // System prompt is not included in messages with "role":"system"
            
            $messages = array();
            
            // Add conversation history (excluding system message)
            foreach ($conversation_history as $message) {
                $messages[] = array(
                    'role' => $message['role'],
                    'content' => $message['content']
                );
            }
            
            // Get selected Claude model
            $claude_model = isset($this->settings['claude_model']) ? $this->settings['claude_model'] : 'claude-3-sonnet-20240229';
            
            $args = array(
                'headers' => array(
                    'x-api-key' => $this->api_key,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array(
                    'model' => $claude_model,
                    'messages' => $messages,
                    'system' => $system_prompt, // System prompt as separate parameter
                    'max_tokens' => 500,
                    'temperature' => 0.7
                )),
                'timeout' => 30
            );
            
            $response = wp_remote_post('https://api.anthropic.com/v1/messages', $args);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($body['error'])) {
                return new WP_Error('claude_error', $body['error']['message']);
            }
            
            if (!isset($body['content'][0]['text'])) {
                return new WP_Error('claude_error', __('Invalid response from Claude.', 'ecab-taxi-booking-manager'));
            }
            
            return $body['content'][0]['text'];
        }
        
        /**
         * Get response from Gemini API
         */
        private function get_gemini_response($system_prompt, $conversation_history) {
            $contents = array(
                array(
                    'role' => 'system',
                    'parts' => array(
                        array('text' => $system_prompt)
                    )
                )
            );
            
            // Add conversation history
            foreach ($conversation_history as $message) {
                $contents[] = array(
                    'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                    'parts' => array(
                        array('text' => $message['content'])
                    )
                );
            }
            
            // Get selected Gemini model
            $gemini_model = isset($this->settings['gemini_model']) ? $this->settings['gemini_model'] : 'gemini-pro';
            
            $args = array(
                'headers' => array(
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array(
                    'contents' => $contents,
                    'generationConfig' => array(
                        'maxOutputTokens' => 500,
                        'temperature' => 0.7
                    )
                )),
                'timeout' => 30
            );
            
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $gemini_model . ':generateContent?key=' . $this->api_key;
            $response = wp_remote_post($url, $args);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($body['error'])) {
                return new WP_Error('gemini_error', $body['error']['message']);
            }
            
            if (!isset($body['candidates'][0]['content']['parts'][0]['text'])) {
                return new WP_Error('gemini_error', __('Invalid response from Gemini.', 'ecab-taxi-booking-manager'));
            }
            
            return $body['candidates'][0]['content']['parts'][0]['text'];
        }
        
        /**
         * Get plugin context for AI
         */
        private function get_plugin_context() {
            $context = "Here's information about the E-Cab Taxi Booking Manager plugin:\n\n";
            
            // Add services information
            $context .= "Available Transport Services:\n";
            foreach ($this->plugin_data['services'] as $service) {
                $context .= "- " . $service['name'] . ": " . 
                            $service['price_info'] . 
                            ", Max Passengers: " . $service['max_passenger'] . 
                            ", Max Bags: " . $service['max_bag'] . "\n";
                
                // Add description if available
                if (!empty($service['description'])) {
                    $context .= "  Description: " . substr(strip_tags($service['description']), 0, 150) . "...\n";
                }
                
                // Add available locations if any
                if (!empty($service['locations']) && is_array($service['locations'])) {
                    $context .= "  Available Locations: " . implode(", ", array_slice($service['locations'], 0, 5));
                    if (count($service['locations']) > 5) {
                        $context .= " and " . (count($service['locations']) - 5) . " more";
                    }
                    $context .= "\n";
                }
                
                // Add pricing details for fixed routes
                if ($service['price_based'] === 'fixed' && 
                    isset($service['pricing_details']['routes']) && 
                    !empty($service['pricing_details']['routes'])) {
                    
                    $context .= "  Sample Routes:\n";
                    $routes = array_slice($service['pricing_details']['routes'], 0, 3);
                    foreach ($routes as $route) {
                        $context .= "    - From: " . $route['from'] . " To: " . $route['to'] . " - Price: " . $route['price'] . "\n";
                    }
                    if (count($service['pricing_details']['routes']) > 3) {
                        $context .= "    - And " . (count($service['pricing_details']['routes']) - 3) . " more routes\n";
                    }
                }
            }
            
            // Add FAQs
            $context .= "\nFrequently Asked Questions:\n";
            foreach ($this->plugin_data['faqs'] as $index => $faq) {
                if ($index >= 10) break; // Limit to 10 FAQs
                $context .= "Q: " . $faq['question'] . "\n";
                $context .= "A: " . $faq['answer'] . "\n\n";
            }
            
            // Add booking info
            $context .= "\nBooking Information:\n";
            $context .= "- Booking URL: " . $this->plugin_data['booking_url'] . "\n";
            $context .= "- Plugin Version: " . $this->plugin_data['plugin_version'] . "\n";
            
            return $context;
        }
        
        /**
         * Test AI API key AJAX handler
         */
        public function test_ai_api_key() {
            // Check nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mptbm_test_ai_api_key')) {
                wp_send_json_error('Security check failed');
            }
            
            // Get API key and provider
            $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
            $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : 'openai';
            
            if (empty($api_key)) {
                wp_send_json_error('API key is empty');
            }
            
            // Test API based on provider
            switch ($provider) {
                case 'openai':
                    $result = $this->test_openai_api($api_key);
                    break;
                case 'claude':
                    $result = $this->test_claude_api($api_key);
                    break;
                case 'gemini':
                    $result = $this->test_gemini_api($api_key);
                    break;
                default:
                    $result = new WP_Error('invalid_provider', 'Invalid AI provider');
            }
            
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            } else {
                wp_send_json_success('API key is valid and working properly!');
            }
        }
        
        /**
         * Test OpenAI API key
         */
        private function test_openai_api($api_key) {
            // Get selected OpenAI model
            $openai_model = isset($this->settings['openai_model']) ? $this->settings['openai_model'] : 'gpt-3.5-turbo';
            
            $args = array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array(
                    'model' => $openai_model,
                    'messages' => array(
                        array(
                            'role' => 'user',
                            'content' => 'Say "API test successful"'
                        )
                    ),
                    'max_tokens' => 20
                )),
                'timeout' => 15
            );
            
            $response = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($body['error'])) {
                return new WP_Error('openai_error', $body['error']['message']);
            }
            
            return true;
        }
        
        /**
         * Test Claude API key
         */
        private function test_claude_api($api_key) {
            // Get selected Claude model
            $claude_model = isset($this->settings['claude_model']) ? $this->settings['claude_model'] : 'claude-3-sonnet-20240229';
            
            $args = array(
                'headers' => array(
                    'x-api-key' => $api_key,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array(
                    'model' => $claude_model,
                    'messages' => array(
                        array(
                            'role' => 'user',
                            'content' => 'Say "API test successful"'
                        )
                    ),
                    'system' => 'You are a helpful assistant for testing API connectivity.',
                    'max_tokens' => 20
                )),
                'timeout' => 15
            );
            
            $response = wp_remote_post('https://api.anthropic.com/v1/messages', $args);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($body['error'])) {
                return new WP_Error('claude_error', $body['error']['message']);
            }
            
            return true;
        }
        
        /**
         * Test Gemini API key
         */
        private function test_gemini_api($api_key) {
            // Get selected Gemini model
            $gemini_model = isset($this->settings['gemini_model']) ? $this->settings['gemini_model'] : 'gemini-pro';
            
            $contents = array(
                array(
                    'role' => 'user',
                    'parts' => array(
                        array('text' => 'Say "API test successful"')
                    )
                )
            );
            
            $args = array(
                'headers' => array(
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array(
                    'contents' => $contents,
                    'generationConfig' => array(
                        'maxOutputTokens' => 20,
                        'temperature' => 0.7
                    )
                )),
                'timeout' => 15
            );
            
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $gemini_model . ':generateContent?key=' . $api_key;
            $response = wp_remote_post($url, $args);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (isset($body['error'])) {
                return new WP_Error('gemini_error', $body['error']['message']);
            }
            
            return true;
        }
    }
    
    new MPTBM_AI_Chatbot();
} 