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
            
            // New feature: Voice input
            add_action('wp_ajax_mptbm_process_voice_input', array($this, 'process_voice_input'));
            add_action('wp_ajax_nopriv_mptbm_process_voice_input', array($this, 'process_voice_input'));
            
            // New feature: Route suggestions
            add_action('wp_ajax_mptbm_get_route_suggestions', array($this, 'get_route_suggestions'));
            add_action('wp_ajax_nopriv_mptbm_get_route_suggestions', array($this, 'get_route_suggestions'));
            
            // New feature: Multilingual support
            add_action('wp_ajax_mptbm_translate_chat', array($this, 'translate_chat_content'));
            add_action('wp_ajax_nopriv_mptbm_translate_chat', array($this, 'translate_chat_content'));
            
            // New feature: Analytics
            add_action('wp_ajax_mptbm_chatbot_analytics', array($this, 'get_chatbot_analytics'));
            
            // New feature: User training data submission
            add_action('wp_ajax_mptbm_submit_training_data', array($this, 'submit_training_data'));
            add_action('wp_ajax_nopriv_mptbm_submit_training_data', array($this, 'submit_training_data'));
            
            // New feature: Chatbot scheduled messages
            add_action('mptbm_scheduled_chatbot_message', array($this, 'process_scheduled_message'), 10, 2);
            
            // Initialize plugin data for context
            add_action('init', array($this, 'initialize_plugin_data'), 20);
            
            // Register chat analytics tracking
            add_action('mptbm_after_chat_message', array($this, 'track_chat_analytics'));
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
            try {
                // Check nonce
                if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mptbm_ai_chatbot_nonce')) {
                    wp_send_json_error('Security verification failed. Please refresh the page and try again.');
                    return;
                }
                
                // Get message
                $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
                if (empty($message)) {
                    wp_send_json_error('Please enter a message.');
                    return;
                }
                
                // Get conversation history
                $history = isset($_POST['history']) ? json_decode(stripslashes($_POST['history']), true) : array();
                if (!is_array($history)) {
                    $history = array();
                }
                
                // Record the start time for response time tracking
                $start_time = microtime(true);
                
                // Check if chatbot is enabled
                if (!isset($this->settings['enabled']) || !$this->settings['enabled']) {
                    wp_send_json_error('AI Chatbot is currently disabled. Please contact the administrator.');
                    return;
                }
                
                // Check if API key is configured
                if (empty($this->api_key)) {
                    wp_send_json_error('AI Chatbot is not properly configured. Please contact the administrator.');
                    return;
                }
                
                // Get AI response - we'll pass the history directly to maintain consistent state
                $response = $this->get_ai_response($history);
                
                // Calculate response time
                $response_time = microtime(true) - $start_time;
                
                // Handle error in response
                if (is_wp_error($response)) {
                    // Log the error for diagnostic purposes
                    error_log('AI Chatbot Error: ' . $response->get_error_message());
                    
                    // Provide a friendly error message to the user
                    $error_code = $response->get_error_code();
                    $error_message = 'An error occurred while processing your request. ';
                    
                    if (strpos($error_code, 'openai') !== false || 
                        strpos($error_code, 'claude') !== false || 
                        strpos($error_code, 'gemini') !== false) {
                        $error_message .= 'The AI service is temporarily unavailable. Please try again later.';
                    } else if (strpos($error_code, 'timeout') !== false) {
                        $error_message .= 'The request timed out. Please try a shorter question.';
                    } else if (strpos($error_code, 'invalid') !== false) {
                        $error_message .= 'Invalid request parameters. Please try rephrasing your question.';
                    } else {
                        $error_message .= 'Please try again or contact support if the issue persists.';
                    }
                    
                    wp_send_json_error($error_message);
                    return;
                }
                
                // Track analytics for this conversation if enabled
                if (isset($this->settings['analytics_enabled']) && $this->settings['analytics_enabled'] === 'yes') {
                    $this->track_chat_analytics(array(
                        'user_message' => $message,
                        'ai_response' => $response,
                        'response_time' => $response_time,
                        'timestamp' => current_time('mysql'),
                        'session_id' => $this->get_session_id(),
                        'ip_address' => $this->get_user_ip()
                    ));
                }
                
                // Return just the response text directly - simplified for better client handling
                wp_send_json_success($response);
                
            } catch (Exception $e) {
                // Log the exception
                error_log('AI Chatbot Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
                
                // Return a user-friendly error message
                wp_send_json_error('An unexpected error occurred. Please try again later.');
            }
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
            $primary_provider = $this->ai_provider;
            $response = null;
            $error = null;
            
            // Try the primary provider first
            switch ($primary_provider) {
                case 'openai':
                    $response = $this->get_openai_response($enhanced_system_prompt, $conversation_history);
                    break;
                case 'claude':
                    $response = $this->get_claude_response($enhanced_system_prompt, $conversation_history);
                    break;
                case 'gemini':
                    $response = $this->get_gemini_response($enhanced_system_prompt, $conversation_history);
                    break;
                default:
                    return new WP_Error('invalid_provider', __('Invalid AI provider.', 'ecab-taxi-booking-manager'));
            }
            
            // If the primary provider returned an error, try a fallback
            if (is_wp_error($response)) {
                $error = $response;
                error_log('Primary AI provider (' . $primary_provider . ') failed: ' . $error->get_error_message());
                
                // We'll try another provider as fallback
                // Only attempt fallback if the API key is configured
                if (!empty($this->api_key)) {
                    $fallback_provider = '';
                    
                    // Choose a fallback provider
                    if ($primary_provider === 'claude') {
                        $fallback_provider = 'openai';
                    } else if ($primary_provider === 'openai') {
                        $fallback_provider = 'gemini';
                    } else {
                        $fallback_provider = 'openai';
                    }
                    
                    error_log('Attempting fallback to ' . $fallback_provider);
                    
                    // Try the fallback provider
                    switch ($fallback_provider) {
                        case 'openai':
                            $response = $this->get_openai_response($enhanced_system_prompt, $conversation_history);
                            break;
                        case 'gemini':
                            $response = $this->get_gemini_response($enhanced_system_prompt, $conversation_history);
                            break;
                        case 'claude':
                            $response = $this->get_claude_response($enhanced_system_prompt, $conversation_history);
                            break;
                    }
                    
                    // If the fallback also failed, return the original error
                    if (is_wp_error($response)) {
                        error_log('Fallback provider (' . $fallback_provider . ') also failed: ' . $response->get_error_message());
                        return $error; // Return the original error
                    }
                    
                    // Add a note that we used a fallback
                    $response = sprintf(__('(Note: Used fallback %s provider) ', 'ecab-taxi-booking-manager'), $fallback_provider) . $response;
                } else {
                    // No fallback possible, return the original error
                    return $error;
                }
            }
            
            return $response;
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
            try {
                // Claude API expects a different format than OpenAI
                
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
                
                // Log the request for debugging
                error_log('Claude API Request - Model: ' . $claude_model);
                
                // Check if messages and system prompt are not empty
                if (empty($messages)) {
                    $error_msg = 'No messages provided for Claude API.';
                    $this->log_api_error('claude', $error_msg, json_encode($conversation_history));
                    return new WP_Error('claude_error', $error_msg);
                }
                
                // Prepare the API request body
                $request_body = array(
                    'model' => $claude_model,
                    'messages' => $messages,
                    'max_tokens' => 500,
                    'temperature' => 0.7
                );
                
                // Add system prompt if not empty
                if (!empty($system_prompt)) {
                    $request_body['system'] = $system_prompt;
                }
                
                // Prepare the request arguments
                $args = array(
                    'headers' => array(
                        'x-api-key' => $this->api_key,
                        'anthropic-version' => '2023-06-01',
                        'Content-Type' => 'application/json'
                    ),
                    'body' => json_encode($request_body),
                    'timeout' => 60 // Increased timeout for better reliability
                );
                
                // Store request data for logging purposes
                $request_data = json_encode(array(
                    'model' => $claude_model,
                    'message_count' => count($messages),
                    'system_prompt_length' => strlen($system_prompt)
                ));
                
                // Make API request
                $response = wp_remote_post('https://api.anthropic.com/v1/messages', $args);
                
                // Error handling for transport-level errors
                if (is_wp_error($response)) {
                    $error_msg = 'Claude API Transport Error: ' . $response->get_error_message();
                    $this->log_api_error('claude', $error_msg, $request_data);
                    return $response;
                }
                
                // Get response body for logging
                $response_body = wp_remote_retrieve_body($response);
                
                // Check for HTTP error codes
                $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code !== 200) {
                    $error_msg = 'Claude API HTTP Error: ' . $response_code;
                    if (!empty($response_body)) {
                        $error_data = json_decode($response_body, true);
                        if (isset($error_data['error']) && isset($error_data['error']['message'])) {
                            $error_msg .= ' - ' . $error_data['error']['message'];
                        } elseif (isset($error_data['error'])) {
                            $error_msg .= ' - ' . json_encode($error_data['error']);
                        } else {
                            $error_msg .= ' - ' . $response_body;
                        }
                    }
                    $this->log_api_error('claude', $error_msg, $request_data, $response_body);
                    return new WP_Error('claude_error', $error_msg);
                }
                
                // Parse the response
                $body = json_decode($response_body, true);
                
                // Error handling for API-level errors
                if (isset($body['error'])) {
                    $error_msg = isset($body['error']['message']) ? $body['error']['message'] : json_encode($body['error']);
                    $this->log_api_error('claude', $error_msg, $request_data, $response_body);
                    return new WP_Error('claude_error', $error_msg);
                }
                
                // Extract the response content
                // The structure is { content: [ { type: "text", text: "..." } ] }
                if (!isset($body['content']) || !is_array($body['content']) || empty($body['content'])) {
                    $error_msg = 'Invalid response format from Claude.';
                    $this->log_api_error('claude', $error_msg, $request_data, $response_body);
                    return new WP_Error('claude_error', __($error_msg, 'ecab-taxi-booking-manager'));
                }
                
                // Find the first text content
                $text_content = '';
                foreach ($body['content'] as $content_item) {
                    if (isset($content_item['type']) && $content_item['type'] === 'text' && isset($content_item['text'])) {
                        $text_content = $content_item['text'];
                        break;
                    } else if (isset($content_item['text'])) {
                        // Sometimes the 'type' field might be missing
                        $text_content = $content_item['text'];
                        break;
                    }
                }
                
                if (empty($text_content)) {
                    $error_msg = 'No text content found in Claude response.';
                    $this->log_api_error('claude', $error_msg, $request_data, $response_body);
                    return new WP_Error('claude_error', __($error_msg, 'ecab-taxi-booking-manager'));
                }
                
                return $text_content;
            } catch (Exception $e) {
                // Catch any unexpected exceptions and log them
                $error_msg = 'Exception in Claude API: ' . $e->getMessage();
                $this->log_api_error('claude', $error_msg);
                return new WP_Error('claude_error', sprintf(__('Exception in Claude API: %s', 'ecab-taxi-booking-manager'), $e->getMessage()));
            }
        }
        
        /**
         * Get response from Gemini API
         */
        private function get_gemini_response($system_prompt, $conversation_history) {
            try {
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
                
                // Log the request for debugging
                error_log('Gemini API Request - Model: ' . $gemini_model);
                
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
                    'timeout' => 60 // Increased timeout
                );
                
                $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $gemini_model . ':generateContent?key=' . $this->api_key;
                $response = wp_remote_post($url, $args);
                
                if (is_wp_error($response)) {
                    error_log('Gemini API Transport Error: ' . $response->get_error_message());
                    return $response;
                }
                
                // Check response code
                $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code !== 200) {
                    $error_message = 'Gemini API HTTP Error: ' . $response_code;
                    $body = wp_remote_retrieve_body($response);
                    error_log($error_message . ' - ' . $body);
                    return new WP_Error('gemini_error', $error_message);
                }
                
                $body = json_decode(wp_remote_retrieve_body($response), true);
                
                if (isset($body['error'])) {
                    $error_message = isset($body['error']['message']) ? $body['error']['message'] : json_encode($body['error']);
                    error_log('Gemini API Error: ' . $error_message);
                    return new WP_Error('gemini_error', $error_message);
                }
                
                if (!isset($body['candidates'][0]['content']['parts'][0]['text'])) {
                    error_log('Gemini API Invalid Response Format: ' . json_encode($body));
                    return new WP_Error('gemini_error', __('Invalid response from Gemini.', 'ecab-taxi-booking-manager'));
                }
                
                return $body['candidates'][0]['content']['parts'][0]['text'];
            } catch (Exception $e) {
                // Catch any unexpected exceptions
                error_log('Gemini API Exception: ' . $e->getMessage());
                return new WP_Error('gemini_error', sprintf(__('Exception in Gemini API: %s', 'ecab-taxi-booking-manager'), $e->getMessage()));
            }
        }
        
        /**
         * Get plugin context for AI
         */
        private function get_plugin_context() {
            $context = "Here's information about the E-Cab Taxi Booking Manager plugin:\n\n";
            
            // Check if plugin_data is properly initialized
            if (!isset($this->plugin_data) || !is_array($this->plugin_data)) {
                $this->initialize_plugin_data();
            }
            
            // Add services information
            $context .= "Available Transport Services:\n";
            
            if (isset($this->plugin_data['services']) && is_array($this->plugin_data['services'])) {
                foreach ($this->plugin_data['services'] as $service) {
                    if (!is_array($service)) continue;
                    
                    $name = isset($service['name']) ? $service['name'] : 'Unnamed service';
                    $price_info = isset($service['price_info']) ? $service['price_info'] : 'Price info not available';
                    $max_passenger = isset($service['max_passenger']) ? $service['max_passenger'] : 'N/A';
                    $max_bag = isset($service['max_bag']) ? $service['max_bag'] : 'N/A';
                    
                    $context .= "- {$name}: {$price_info}, Max Passengers: {$max_passenger}, Max Bags: {$max_bag}\n";
                    
                    // Add description if available
                    if (!empty($service['description'])) {
                        $desc = strip_tags($service['description']);
                        $context .= "  Description: " . substr($desc, 0, 150) . "...\n";
                    }
                    
                    // Add available locations if any
                    if (!empty($service['locations']) && is_array($service['locations'])) {
                        $locations = array_slice($service['locations'], 0, 5);
                        $context .= "  Available Locations: " . implode(", ", $locations);
                        if (count($service['locations']) > 5) {
                            $context .= " and " . (count($service['locations']) - 5) . " more";
                        }
                        $context .= "\n";
                    }
                    
                    // Add pricing details for fixed routes
                    if (isset($service['price_based']) && $service['price_based'] === 'fixed' && 
                        isset($service['pricing_details']['routes']) && 
                        is_array($service['pricing_details']['routes']) &&
                        !empty($service['pricing_details']['routes'])) {
                        
                        $context .= "  Sample Routes:\n";
                        $routes = array_slice($service['pricing_details']['routes'], 0, 3);
                        foreach ($routes as $route) {
                            if (isset($route['from']) && isset($route['to']) && isset($route['price'])) {
                                $context .= "    - From: " . $route['from'] . " To: " . $route['to'] . " - Price: " . $route['price'] . "\n";
                            }
                        }
                        if (count($service['pricing_details']['routes']) > 3) {
                            $context .= "    - And " . (count($service['pricing_details']['routes']) - 3) . " more routes\n";
                        }
                    }
                }
            } else {
                $context .= "  No services found. Please check back later.\n";
            }
            
            // Add FAQs
            $context .= "\nFrequently Asked Questions:\n";
            if (isset($this->plugin_data['faqs']) && is_array($this->plugin_data['faqs']) && !empty($this->plugin_data['faqs'])) {
                $faq_count = 0;
                foreach ($this->plugin_data['faqs'] as $faq) {
                    if ($faq_count >= 10) break; // Limit to 10 FAQs
                    if (isset($faq['question']) && isset($faq['answer'])) {
                        $context .= "Q: " . $faq['question'] . "\n";
                        $context .= "A: " . $faq['answer'] . "\n\n";
                        $faq_count++;
                    }
                }
            } else {
                $context .= "No FAQs available at this time.\n\n";
            }
            
            // Add booking info
            $context .= "\nBooking Information:\n";
            $booking_url = isset($this->plugin_data['booking_url']) ? $this->plugin_data['booking_url'] : home_url('/transport_booking/');
            $plugin_version = isset($this->plugin_data['plugin_version']) ? $this->plugin_data['plugin_version'] : MPTBM_PLUGIN_VERSION;
            
            $context .= "- Booking URL: " . $booking_url . "\n";
            $context .= "- Plugin Version: " . $plugin_version . "\n";
            
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
        
        /**
         * Handle translation of chat content
         */
        public function translate_chat_content() {
            // Check nonce
            check_ajax_referer('mptbm_chatbot_nonce', 'nonce');
            
            // Get parameters
            $text = isset($_POST['text']) ? sanitize_textarea_field($_POST['text']) : '';
            $target_language = isset($_POST['target_language']) ? sanitize_text_field($_POST['target_language']) : 'en';
            
            if (empty($text)) {
                wp_send_json_error(array('message' => 'No text provided for translation'));
            }
            
            // Use AI model for translation
            $translation = $this->translate_text($text, $target_language);
            
            if ($translation) {
                wp_send_json_success(array(
                    'original' => $text,
                    'translated' => $translation,
                    'language' => $target_language
                ));
            } else {
                wp_send_json_error(array('message' => 'Translation failed'));
            }
            
            wp_die();
        }
        
        /**
         * Translate text using the selected AI provider
         */
        private function translate_text($text, $target_language) {
            // Skip translation if target is English and text is already in English
            if ($target_language === 'en' && $this->detect_language($text) === 'en') {
                return $text;
            }
            
            $language_names = array(
                'en' => 'English',
                'es' => 'Spanish',
                'fr' => 'French',
                'de' => 'German',
                'it' => 'Italian',
                'pt' => 'Portuguese',
                'ru' => 'Russian',
                'ja' => 'Japanese',
                'zh' => 'Chinese',
                'ar' => 'Arabic',
                'hi' => 'Hindi'
            );
            
            $language_name = isset($language_names[$target_language]) ? $language_names[$target_language] : $target_language;
            
            $system_prompt = "You are a professional translator. Translate the following text to {$language_name}. Preserve formatting and keep the meaning intact. Only return the translated text without any additional explanation.";
            
            $conversation = array(
                array(
                    'role' => 'system',
                    'content' => $system_prompt
                ),
                array(
                    'role' => 'user',
                    'content' => $text
                )
            );
            
            if ($this->ai_provider === 'openai') {
                return $this->get_openai_translation($conversation);
            } elseif ($this->ai_provider === 'claude') {
                return $this->get_claude_translation($conversation);
            } elseif ($this->ai_provider === 'gemini') {
                return $this->get_gemini_translation($conversation);
            }
            
            return false;
        }
        
        /**
         * Get OpenAI translation
         */
        private function get_openai_translation($conversation) {
            $api_key = $this->api_key;
            
            $headers = array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            );
            
            $body = array(
                'model' => 'gpt-3.5-turbo',
                'messages' => $conversation,
                'temperature' => 0.3,
                'max_tokens' => 1000
            );
            
            $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
                'headers' => $headers,
                'body' => json_encode($body),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return false;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['choices'][0]['message']['content'])) {
                return trim($data['choices'][0]['message']['content']);
            }
            
            return false;
        }
        
        /**
         * Get Claude translation
         */
        private function get_claude_translation($conversation) {
            $api_key = $this->api_key;
            
            $headers = array(
                'x-api-key' => $api_key,
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01'
            );
            
            // Convert conversation format
            $messages = array();
            foreach ($conversation as $message) {
                $messages[] = array(
                    'role' => $message['role'],
                    'content' => $message['content']
                );
            }
            
            $body = array(
                'model' => 'claude-instant-1',
                'messages' => $messages,
                'max_tokens' => 1000,
                'temperature' => 0.3
            );
            
            $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
                'headers' => $headers,
                'body' => json_encode($body),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return false;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['content'][0]['text'])) {
                return trim($data['content'][0]['text']);
            }
            
            return false;
        }
        
        /**
         * Get Gemini translation
         */
        private function get_gemini_translation($conversation) {
            $api_key = $this->api_key;
            
            $headers = array(
                'Content-Type' => 'application/json'
            );
            
            // Convert conversation format for Gemini
            $contents = array();
            foreach ($conversation as $message) {
                if ($message['role'] === 'system') {
                    // Add system message as a user message since Gemini doesn't have system role
                    $contents[] = array(
                        'role' => 'user',
                        'parts' => array(
                            array('text' => 'System instruction: ' . $message['content'])
                        )
                    );
                    // Add model response to acknowledge system instruction
                    $contents[] = array(
                        'role' => 'model',
                        'parts' => array(
                            array('text' => 'I understand and will act as a translator.')
                        )
                    );
                } else {
                    $contents[] = array(
                        'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                        'parts' => array(
                            array('text' => $message['content'])
                        )
                    );
                }
            }
            
            $body = array(
                'contents' => $contents,
                'generationConfig' => array(
                    'temperature' => 0.3,
                    'maxOutputTokens' => 1000
                )
            );
            
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $api_key;
            
            $response = wp_remote_post($url, array(
                'headers' => $headers,
                'body' => json_encode($body),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return false;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return trim($data['candidates'][0]['content']['parts'][0]['text']);
            }
            
            return false;
        }
        
        /**
         * Simple language detection
         */
        private function detect_language($text) {
            // This is a very basic detection - for production, consider using a proper detection service
            $text = strtolower(substr($text, 0, 100));
            
            // English common words and characters
            if (preg_match('/\b(the|and|of|to|a|in|is|you|that|it|for)\b/i', $text)) {
                return 'en';
            }
            
            // Spanish common words
            if (preg_match('/\b(el|la|de|que|y|en|un|ser|se|no|haber|por|con|su)\b/i', $text)) {
                return 'es';
            }
            
            // French common words
            if (preg_match('/\b(le|la|de|et|un|être|que|avoir|ne|pas|dans|ce|qui|sur)\b/i', $text)) {
                return 'fr';
            }
            
            // German common words
            if (preg_match('/\b(der|die|das|und|in|zu|den|ein|nicht|mit|dem|sich|auf)\b/i', $text)) {
                return 'de';
            }
            
            // If no match, default to English
            return 'en';
        }
        
        /**
         * Process voice input from the user
         */
        public function process_voice_input() {
            // Check nonce
            check_ajax_referer('mptbm_chatbot_nonce', 'nonce');
            
            // Check if audio data is provided
            if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
                wp_send_json_error(array('message' => 'No audio file provided or upload error'));
            }
            
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/mptbm_temp_audio';
            
            // Create temp directory if it doesn't exist
            if (!file_exists($temp_dir)) {
                wp_mkdir_p($temp_dir);
                
                // Create index.php to prevent directory listing
                $index_file = $temp_dir . '/index.php';
                if (!file_exists($index_file)) {
                    file_put_contents($index_file, '<?php // Silence is golden');
                }
                
                // Create .htaccess to prevent direct access
                $htaccess_file = $temp_dir . '/.htaccess';
                if (!file_exists($htaccess_file)) {
                    file_put_contents($htaccess_file, 'deny from all');
                }
            }
            
            // Generate unique filename
            $filename = 'voice_' . uniqid() . '.webm';
            $file_path = $temp_dir . '/' . $filename;
            
            // Move the uploaded file to our temp directory
            if (!move_uploaded_file($_FILES['audio']['tmp_name'], $file_path)) {
                wp_send_json_error(array('message' => 'Failed to save audio file'));
            }
            
            // Get the transcription based on AI provider
            $transcription = $this->transcribe_audio($file_path);
            
            // Delete the temporary file
            @unlink($file_path);
            
            if ($transcription) {
                wp_send_json_success(array('transcription' => $transcription));
            } else {
                wp_send_json_error(array('message' => 'Failed to transcribe audio'));
            }
            
            wp_die();
        }
        
        /**
         * Transcribe audio using selected AI provider
         */
        private function transcribe_audio($file_path) {
            if ($this->ai_provider === 'openai') {
                return $this->openai_transcribe($file_path);
            } elseif ($this->ai_provider === 'whisper_local') {
                return $this->whisper_local_transcribe($file_path);
            }
            
            // Default to OpenAI for other providers that might not have direct transcription API
            return $this->openai_transcribe($file_path);
        }
        
        /**
         * Transcribe audio using OpenAI's Whisper API
         */
        private function openai_transcribe($file_path) {
            $api_key = $this->api_key;
            
            // Check if file exists
            if (!file_exists($file_path)) {
                return false;
            }
            
            // Convert WebM to MP3 using FFmpeg if available
            $mp3_path = $this->convert_audio_to_mp3($file_path);
            $file_to_send = $mp3_path ? $mp3_path : $file_path;
            
            $headers = array(
                'Authorization' => 'Bearer ' . $api_key
            );
            
            $boundary = wp_generate_password(24, false);
            $headers['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;
            
            // Read the file
            $file_content = file_get_contents($file_to_send);
            $filename = basename($file_to_send);
            
            // Prepare request body
            $body = '';
            
            // Add model parameter
            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="model"' . "\r\n\r\n";
            $body .= 'whisper-1' . "\r\n";
            
            // Add file parameter
            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="file"; filename="' . $filename . '"' . "\r\n";
            $body .= 'Content-Type: audio/mpeg' . "\r\n\r\n";
            $body .= $file_content . "\r\n";
            
            // Close the body
            $body .= '--' . $boundary . '--';
            
            // Make the API request
            $response = wp_remote_post('https://api.openai.com/v1/audio/transcriptions', array(
                'headers' => $headers,
                'body' => $body,
                'timeout' => 60 // Longer timeout for audio processing
            ));
            
            // Clean up temporary MP3 file if created
            if ($mp3_path) {
                @unlink($mp3_path);
            }
            
            if (is_wp_error($response)) {
                return false;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['text'])) {
                return trim($data['text']);
            }
            
            return false;
        }
        
        /**
         * Convert WebM audio to MP3 using FFmpeg if available
         */
        private function convert_audio_to_mp3($webm_path) {
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/mptbm_temp_audio';
            $mp3_path = $temp_dir . '/' . pathinfo($webm_path, PATHINFO_FILENAME) . '.mp3';
            
            // Check if FFmpeg is available
            $ffmpeg_path = $this->get_ffmpeg_path();
            if (!$ffmpeg_path) {
                return false;
            }
            
            // Execute FFmpeg conversion
            $command = escapeshellcmd($ffmpeg_path) . ' -i ' . escapeshellarg($webm_path) . ' -c:a libmp3lame -b:a 128k -f mp3 ' . escapeshellarg($mp3_path) . ' 2>&1';
            
            exec($command, $output, $return_code);
            
            if ($return_code !== 0 || !file_exists($mp3_path)) {
                return false;
            }
            
            return $mp3_path;
        }
        
        /**
         * Get the path to FFmpeg executable
         */
        private function get_ffmpeg_path() {
            // Check if path is stored in settings
            $ffmpeg_path = isset($this->settings['ffmpeg_path']) ? $this->settings['ffmpeg_path'] : '';
            
            if (!empty($ffmpeg_path) && file_exists($ffmpeg_path)) {
                return $ffmpeg_path;
            }
            
            // Common locations for FFmpeg
            $possible_paths = array(
                '/usr/bin/ffmpeg',
                '/usr/local/bin/ffmpeg',
                'C:\\ffmpeg\\bin\\ffmpeg.exe',
                'ffmpeg' // In case it's in the PATH
            );
            
            foreach ($possible_paths as $path) {
                $test_command = escapeshellcmd($path) . ' -version 2>&1';
                exec($test_command, $output, $return_code);
                
                if ($return_code === 0) {
                    return $path;
                }
            }
            
            return false;
        }
        
        /**
         * Transcribe audio using locally hosted Whisper model (if available)
         * This requires the server to have the whisper library installed and configured
         */
        private function whisper_local_transcribe($file_path) {
            $whisper_endpoint = isset($this->settings['whisper_endpoint']) ? $this->settings['whisper_endpoint'] : '';
            
            if (empty($whisper_endpoint)) {
                return false;
            }
            
            // Check if file exists
            if (!file_exists($file_path)) {
                return false;
            }
            
            // Create a cURL file object
            $cfile = new \CURLFile($file_path, 'audio/webm', basename($file_path));
            
            // Prepare the request
            $post_data = array(
                'file' => $cfile,
                'model' => 'small' // Choose model size: tiny, base, small, medium, large
            );
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $whisper_endpoint);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code !== 200) {
                return false;
            }
            
            $data = json_decode($response, true);
            
            if (isset($data['text'])) {
                return trim($data['text']);
            }
            
            return false;
        }
        
        /**
         * Get AI-powered route suggestions
         */
        public function get_route_suggestions() {
            // Check nonce
            check_ajax_referer('mptbm_chatbot_nonce', 'nonce');
            
            // Get parameters
            $origin = isset($_POST['origin']) ? sanitize_text_field($_POST['origin']) : '';
            $destination = isset($_POST['destination']) ? sanitize_text_field($_POST['destination']) : '';
            $preferences = isset($_POST['preferences']) ? sanitize_text_field($_POST['preferences']) : '';
            
            if (empty($origin) || empty($destination)) {
                wp_send_json_error(array('message' => 'Origin and destination are required'));
            }
            
            // Get route data from the database or cached data
            $known_routes = $this->get_cached_routes();
            
            // Initialize results
            $direct_routes = array();
            $alternative_routes = array();
            
            // Look for direct routes
            foreach ($known_routes as $route) {
                if ($this->match_locations($route['from'], $origin) && $this->match_locations($route['to'], $destination)) {
                    $direct_routes[] = $route;
                }
            }
            
            // If no direct routes found, use AI to suggest alternative options
            if (empty($direct_routes)) {
                $alternative_routes = $this->generate_ai_route_suggestions($origin, $destination, $preferences);
            }
            
            wp_send_json_success(array(
                'direct_routes' => $direct_routes,
                'alternative_routes' => $alternative_routes,
                'origin' => $origin,
                'destination' => $destination
            ));
            
            wp_die();
        }
        
        /**
         * Generate AI-powered route suggestions
         */
        private function generate_ai_route_suggestions($origin, $destination, $preferences) {
            // Get all location data to inform AI
            $all_locations = $this->get_all_locations();
            $services = $this->plugin_data['services'];
            
            // Create system prompt for route suggestions
            $system_prompt = "You are a helpful transport route assistant. Your task is to suggest alternative routes or transportation options based on the user's requested origin and destination. Provide practical, realistic suggestions.";
            
            // Prepare the user message with context
            $user_message = "I need to travel from {$origin} to {$destination}";
            
            if (!empty($preferences)) {
                $user_message .= ". My preferences: {$preferences}";
            }
            
            // Add context about available services and locations
            $context = "Here are the available transportation services and locations in our system:\n\n";
            
            // Add services information
            $context .= "Services:\n";
            foreach ($services as $service) {
                $context .= "- {$service['name']} (Max passengers: {$service['max_passenger']}, Max bags: {$service['max_bag']})\n";
                $context .= "  Pricing: {$service['price_info']}\n";
            }
            
            // Add locations information
            $context .= "\nAvailable Locations:\n";
            foreach ($all_locations as $location) {
                $context .= "- {$location}\n";
            }
            
            // Add instructions for the AI
            $instructions = "Based on this information, suggest up to 3 alternative routes or transportation options for traveling from {$origin} to {$destination}. For each option, include:
1. A brief description of the route
2. Estimated travel time and distance (if possible)
3. Recommended vehicle type
4. Approximate pricing
5. Any benefits or considerations for this option

Format the response as structured data in this exact JSON format:
```json
[
  {
    \"option\": \"Option 1 name\",
    \"description\": \"Brief description\",
    \"estimated_time\": \"Estimated travel time\",
    \"vehicle\": \"Recommended vehicle\",
    \"price_range\": \"Approximate price range\",
    \"benefits\": \"Key benefits\"
  },
  {...}
]
```";
            
            // Create conversation
            $conversation = array(
                array(
                    'role' => 'system',
                    'content' => $system_prompt
                ),
                array(
                    'role' => 'user',
                    'content' => $user_message . "\n\n" . $context . "\n\n" . $instructions
                )
            );
            
            // Get AI response
            $json_response = false;
            
            if ($this->ai_provider === 'openai') {
                $json_response = $this->get_openai_structured_response($conversation);
            } elseif ($this->ai_provider === 'claude') {
                $json_response = $this->get_claude_structured_response($conversation);
            } elseif ($this->ai_provider === 'gemini') {
                $json_response = $this->get_gemini_structured_response($conversation);
            }
            
            // Parse JSON response
            if ($json_response) {
                // Extract JSON from the response (it might be wrapped in ```json blocks)
                preg_match('/```(?:json)?(.*?)```/s', $json_response, $matches);
                $json_str = isset($matches[1]) ? trim($matches[1]) : $json_response;
                
                // Clean the string to ensure it's valid JSON
                $json_str = preg_replace('/[^[:print:]]/', '', $json_str);
                
                // Decode JSON
                $route_options = json_decode($json_str, true);
                
                if (is_array($route_options)) {
                    return $route_options;
                }
            }
            
            // Default fallback suggestions if AI fails
            return array(
                array(
                    'option' => 'Direct Taxi',
                    'description' => "Standard taxi service from {$origin} to {$destination}",
                    'estimated_time' => 'Varies based on traffic',
                    'vehicle' => 'Standard Taxi',
                    'price_range' => 'Standard rate',
                    'benefits' => 'Convenient door-to-door service'
                ),
                array(
                    'option' => 'Premium Car Service',
                    'description' => "Luxury vehicle from {$origin} to {$destination}",
                    'estimated_time' => 'Varies based on traffic',
                    'vehicle' => 'Premium Sedan or SUV',
                    'price_range' => 'Premium rate',
                    'benefits' => 'Comfort and luxury'
                )
            );
        }
        
        /**
         * Get OpenAI structured response for route suggestions
         */
        private function get_openai_structured_response($conversation) {
            $api_key = $this->api_key;
            
            $headers = array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            );
            
            $body = array(
                'model' => 'gpt-3.5-turbo',
                'messages' => $conversation,
                'temperature' => 0.7,
                'max_tokens' => 1000
            );
            
            $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
                'headers' => $headers,
                'body' => json_encode($body),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return false;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['choices'][0]['message']['content'])) {
                return trim($data['choices'][0]['message']['content']);
            }
            
            return false;
        }
        
        /**
         * Get Claude structured response
         */
        private function get_claude_structured_response($conversation) {
            $api_key = $this->api_key;
            
            $headers = array(
                'x-api-key' => $api_key,
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01'
            );
            
            // Convert conversation format
            $messages = array();
            foreach ($conversation as $message) {
                $messages[] = array(
                    'role' => $message['role'],
                    'content' => $message['content']
                );
            }
            
            $body = array(
                'model' => 'claude-instant-1',
                'messages' => $messages,
                'max_tokens' => 1000,
                'temperature' => 0.7
            );
            
            $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
                'headers' => $headers,
                'body' => json_encode($body),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return false;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['content'][0]['text'])) {
                return trim($data['content'][0]['text']);
            }
            
            return false;
        }
        
        /**
         * Get Gemini structured response
         */
        private function get_gemini_structured_response($conversation) {
            $api_key = $this->api_key;
            
            $headers = array(
                'Content-Type' => 'application/json'
            );
            
            // Convert conversation format for Gemini
            $contents = array();
            foreach ($conversation as $message) {
                if ($message['role'] === 'system') {
                    // Add system message as a user message since Gemini doesn't have system role
                    $contents[] = array(
                        'role' => 'user',
                        'parts' => array(
                            array('text' => 'System instruction: ' . $message['content'])
                        )
                    );
                    // Add model response to acknowledge system instruction
                    $contents[] = array(
                        'role' => 'model',
                        'parts' => array(
                            array('text' => 'I understand and will act as a transport route assistant.')
                        )
                    );
                } else {
                    $contents[] = array(
                        'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                        'parts' => array(
                            array('text' => $message['content'])
                        )
                    );
                }
            }
            
            $body = array(
                'contents' => $contents,
                'generationConfig' => array(
                    'temperature' => 0.7,
                    'maxOutputTokens' => 1000
                )
            );
            
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $api_key;
            
            $response = wp_remote_post($url, array(
                'headers' => $headers,
                'body' => json_encode($body),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return false;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return trim($data['candidates'][0]['content']['parts'][0]['text']);
            }
            
            return false;
        }
        
        /**
         * Get all locations from the plugin data
         */
        private function get_all_locations() {
            $all_locations = array();
            
            if (isset($this->plugin_data['services']) && is_array($this->plugin_data['services'])) {
                foreach ($this->plugin_data['services'] as $service) {
                    if (isset($service['locations']) && is_array($service['locations'])) {
                        foreach ($service['locations'] as $location) {
                            if (!in_array($location, $all_locations) && !empty($location)) {
                                $all_locations[] = $location;
                            }
                        }
                    }
                }
            }
            
            // Sort locations alphabetically
            sort($all_locations);
            
            return $all_locations;
        }
        
        /**
         * Match locations with fuzzy matching
         */
        private function match_locations($route_location, $user_location) {
            // Exact match
            if (strcasecmp($route_location, $user_location) === 0) {
                return true;
            }
            
            // Partial match (user location contains route location or vice versa)
            if (stripos($route_location, $user_location) !== false || stripos($user_location, $route_location) !== false) {
                return true;
            }
            
            // Calculate similarity
            $similarity = similar_text($route_location, $user_location, $percent);
            
            // If similarity is above 75%, consider it a match
            if ($percent > 75) {
                return true;
            }
            
            return false;
        }
        
        /**
         * Get cached routes from database or generate them
         */
        private function get_cached_routes() {
            // Check for cached routes
            $cached_routes = get_transient('mptbm_ai_cached_routes');
            
            if ($cached_routes !== false) {
                return $cached_routes;
            }
            
            // No cache, generate routes from plugin data
            $routes = array();
            
            if (isset($this->plugin_data['services']) && is_array($this->plugin_data['services'])) {
                foreach ($this->plugin_data['services'] as $service) {
                    if ($service['price_based'] === 'manual' && isset($service['pricing_details']['routes'])) {
                        foreach ($service['pricing_details']['routes'] as $route) {
                            $routes[] = array(
                                'from' => $route['from'],
                                'to' => $route['to'],
                                'price' => $route['price'],
                                'service_id' => $service['id'],
                                'service_name' => $service['name']
                            );
                        }
                    }
                }
            }
            
            // Cache the routes for 12 hours
            set_transient('mptbm_ai_cached_routes', $routes, 12 * HOUR_IN_SECONDS);
            
            return $routes;
        }
        
        /**
         * Track chat analytics after each message
         */
        public function track_chat_analytics($message_data) {
            if (!isset($message_data['user_message']) || !isset($message_data['ai_response'])) {
                return;
            }
            
            $user_message = $message_data['user_message'];
            $ai_response = $message_data['ai_response'];
            $session_id = $this->get_session_id();
            $user_id = get_current_user_id();
            $timestamp = current_time('mysql');
            
            // Analyze intent and sentiment
            $intent = $this->analyze_message_intent($user_message);
            $sentiment = $this->analyze_message_sentiment($user_message);
            
            // Check if response contains booking reference
            $has_booking_ref = (stripos($ai_response, 'booking') !== false || 
                               stripos($ai_response, 'reservation') !== false);
            
            // Store the analytics data
            $analytics_data = array(
                'timestamp' => $timestamp,
                'session_id' => $session_id,
                'user_id' => $user_id,
                'user_message' => $user_message,
                'ai_response' => $ai_response,
                'intent' => $intent,
                'sentiment' => $sentiment,
                'has_booking_ref' => $has_booking_ref ? 1 : 0,
                'ip_address' => $this->get_user_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                'page_url' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
                'response_tokens' => $this->estimate_token_count($ai_response),
                'response_time' => isset($message_data['response_time']) ? $message_data['response_time'] : 0,
            );
            
            // Get existing analytics or create new array
            $all_analytics = get_option('mptbm_ai_chatbot_analytics', array());
            
            // If not an array, initialize it
            if (!is_array($all_analytics)) {
                $all_analytics = array();
            }
            
            // Keep only the most recent 1000 interactions to avoid database bloat
            if (count($all_analytics) >= 1000) {
                array_shift($all_analytics);
            }
            
            // Add new interaction
            $all_analytics[] = $analytics_data;
            
            // Update the option
            update_option('mptbm_ai_chatbot_analytics', $all_analytics);
            
            // Update session count
            $this->update_session_stats($session_id);
            
            // Trigger action for other integrations
            do_action('mptbm_after_chat_message', $message_data);
        }
        
        /**
         * Get chatbot analytics for admin dashboard
         */
        public function get_chatbot_analytics() {
            // Check permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Unauthorized access'));
            }
            
            // Check nonce
            check_ajax_referer('mptbm_chatbot_nonce', 'nonce');
            
            // Get date range parameters
            $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : date('Y-m-d', strtotime('-30 days'));
            $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : date('Y-m-d');
            
            // Get analytics data
            $all_analytics = get_option('mptbm_ai_chatbot_analytics', array());
            
            // If not an array, initialize it
            if (!is_array($all_analytics)) {
                $all_analytics = array();
            }
            
            // Filter data by date range
            $filtered_data = array_filter($all_analytics, function($item) use ($start_date, $end_date) {
                if (!isset($item['timestamp'])) {
                    return false;
                }
                $item_date = date('Y-m-d', strtotime($item['timestamp']));
                return $item_date >= $start_date && $item_date <= $end_date;
            });
            
            // Process data for charts and statistics
            $processed_data = $this->process_analytics_data($filtered_data);
            
            wp_send_json_success($processed_data);
            wp_die();
        }
        
        /**
         * Process analytics data for charts and statistics
         */
        private function process_analytics_data($data) {
            // Initialize statistics
            $total_conversations = 0;
            $total_messages = count($data);
            $unique_sessions = array();
            $unique_users = array();
            $intents = array();
            $sentiments = array();
            $conversations_by_date = array();
            $message_lengths = array();
            $response_lengths = array();
            $has_booking_refs = 0;
            
            // Calculate average response time
            $total_response_time = 0;
            $response_time_count = 0;
            
            // Calculate daily message counts
            foreach ($data as $item) {
                // Track unique sessions
                if (isset($item['session_id']) && !in_array($item['session_id'], $unique_sessions)) {
                    $unique_sessions[] = $item['session_id'];
                }
                
                // Track unique users
                if (isset($item['user_id']) && $item['user_id'] > 0 && !in_array($item['user_id'], $unique_users)) {
                    $unique_users[] = $item['user_id'];
                }
                
                // Track intents
                if (isset($item['intent'])) {
                    if (!isset($intents[$item['intent']])) {
                        $intents[$item['intent']] = 0;
                    }
                    $intents[$item['intent']]++;
                }
                
                // Track sentiments
                if (isset($item['sentiment'])) {
                    if (!isset($sentiments[$item['sentiment']])) {
                        $sentiments[$item['sentiment']] = 0;
                    }
                    $sentiments[$item['sentiment']]++;
                }
                
                // Track messages by date
                if (isset($item['timestamp'])) {
                    $date = date('Y-m-d', strtotime($item['timestamp']));
                    if (!isset($conversations_by_date[$date])) {
                        $conversations_by_date[$date] = 0;
                    }
                    $conversations_by_date[$date]++;
                }
                
                // Track message lengths
                if (isset($item['user_message'])) {
                    $message_lengths[] = mb_strlen($item['user_message']);
                }
                
                if (isset($item['ai_response'])) {
                    $response_lengths[] = mb_strlen($item['ai_response']);
                }
                
                // Track booking references
                if (isset($item['has_booking_ref']) && $item['has_booking_ref']) {
                    $has_booking_refs++;
                }
                
                // Track response time
                if (isset($item['response_time']) && $item['response_time'] > 0) {
                    $total_response_time += $item['response_time'];
                    $response_time_count++;
                }
            }
            
            // Calculate averages
            $avg_message_length = count($message_lengths) > 0 ? array_sum($message_lengths) / count($message_lengths) : 0;
            $avg_response_length = count($response_lengths) > 0 ? array_sum($response_lengths) / count($response_lengths) : 0;
            $avg_response_time = $response_time_count > 0 ? $total_response_time / $response_time_count : 0;
            
            // Extract common phrases
            $common_phrases = $this->extract_common_phrases($data);
            
            // Prepare data for time series chart
            $time_series_data = array();
            $all_dates = $this->get_date_range(array_keys($conversations_by_date));
            foreach ($all_dates as $date) {
                $time_series_data[] = array(
                    'date' => $date,
                    'count' => isset($conversations_by_date[$date]) ? $conversations_by_date[$date] : 0
                );
            }
            
            // Prepare intent and sentiment data for charts
            $intent_chart_data = array();
            foreach ($intents as $intent => $count) {
                $intent_chart_data[] = array(
                    'intent' => $intent,
                    'count' => $count
                );
            }
            
            $sentiment_chart_data = array();
            foreach ($sentiments as $sentiment => $count) {
                $sentiment_chart_data[] = array(
                    'sentiment' => $sentiment,
                    'count' => $count
                );
            }
            
            // Ensure we have default values for any potentially missing data
            if (empty($intent_chart_data)) {
                $intent_chart_data = array(
                    array('intent' => 'other', 'count' => 0)
                );
            }
            
            if (empty($sentiment_chart_data)) {
                $sentiment_chart_data = array(
                    array('sentiment' => 'neutral', 'count' => 0)
                );
            }
            
            if (empty($time_series_data)) {
                $time_series_data = array(
                    array('date' => date('Y-m-d'), 'count' => 0)
                );
            }
            
            // Return processed data
            return array(
                'total_conversations' => count($unique_sessions),
                'total_messages' => $total_messages,
                'unique_users' => count($unique_users),
                'avg_message_length' => round($avg_message_length, 1),
                'avg_response_length' => round($avg_response_length, 1),
                'avg_response_time' => round($avg_response_time, 2),
                'booking_reference_rate' => $total_messages > 0 ? round(($has_booking_refs / $total_messages) * 100, 1) : 0,
                'time_series_data' => $time_series_data,
                'intent_data' => $intent_chart_data,
                'sentiment_data' => $sentiment_chart_data,
                'common_phrases' => $common_phrases
            );
        }
        
        /**
         * Update session statistics
         */
        private function update_session_stats($session_id) {
            $session_stats = get_option('mptbm_ai_chatbot_session_stats', array());
            
            if (!isset($session_stats[$session_id])) {
                $session_stats[$session_id] = array(
                    'start_time' => current_time('mysql'),
                    'last_activity' => current_time('mysql'),
                    'message_count' => 1,
                    'user_id' => get_current_user_id(),
                    'ip_address' => $this->get_user_ip(),
                    'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                    'referrer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''
                );
            } else {
                $session_stats[$session_id]['last_activity'] = current_time('mysql');
                $session_stats[$session_id]['message_count']++;
            }
            
            // Keep only the most recent 1000 sessions
            if (count($session_stats) > 1000) {
                // Sort by last activity
                uasort($session_stats, function($a, $b) {
                    return strtotime($b['last_activity']) - strtotime($a['last_activity']);
                });
                
                // Keep only the most recent 1000
                $session_stats = array_slice($session_stats, 0, 1000, true);
            }
            
            update_option('mptbm_ai_chatbot_session_stats', $session_stats);
        }
        
        /**
         * Analyze message intent
         */
        private function analyze_message_intent($message) {
            $message = strtolower($message);
            
            // Define intent patterns
            $intent_patterns = array(
                'booking' => array('book', 'reserve', 'schedule', 'appointment', 'reservation'),
                'pricing' => array('price', 'cost', 'rate', 'fare', 'much is', 'how much', 'pricing'),
                'availability' => array('available', 'free', 'when can', 'schedule', 'timing', 'hours'),
                'information' => array('info', 'tell me about', 'what is', 'how does', 'explain'),
                'support' => array('help', 'support', 'issue', 'problem', 'trouble', 'not working'),
                'cancellation' => array('cancel', 'refund', 'change', 'reschedule'),
                'greeting' => array('hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening')
            );
            
            // Check for each intent
            $matches = array();
            foreach ($intent_patterns as $intent => $patterns) {
                $matches[$intent] = 0;
                foreach ($patterns as $pattern) {
                    if (stripos($message, $pattern) !== false) {
                        $matches[$intent]++;
                    }
                }
            }
            
            // Find the intent with the most matches
            $max_matches = 0;
            $detected_intent = 'other';
            
            foreach ($matches as $intent => $count) {
                if ($count > $max_matches) {
                    $max_matches = $count;
                    $detected_intent = $intent;
                }
            }
            
            return $detected_intent;
        }
        
        /**
         * Analyze message sentiment
         */
        private function analyze_message_sentiment($message) {
            $message = strtolower($message);
            
            // Define sentiment patterns
            $sentiment_patterns = array(
                'positive' => array('great', 'good', 'excellent', 'thanks', 'thank you', 'awesome', 'love', 'happy', 'pleased', 'best'),
                'negative' => array('bad', 'terrible', 'awful', 'horrible', 'poor', 'worst', 'hate', 'disappointed', 'frustrating', 'upset', 'not working'),
                'neutral' => array('ok', 'okay', 'fine', 'alright', 'average', 'when', 'what', 'how', 'where', 'who')
            );
            
            // Check for each sentiment
            $matches = array();
            foreach ($sentiment_patterns as $sentiment => $patterns) {
                $matches[$sentiment] = 0;
                foreach ($patterns as $pattern) {
                    if (stripos($message, $pattern) !== false) {
                        $matches[$sentiment]++;
                    }
                }
            }
            
            // Find the sentiment with the most matches
            $max_matches = 0;
            $detected_sentiment = 'neutral'; // Default sentiment
            
            foreach ($matches as $sentiment => $count) {
                if ($count > $max_matches) {
                    $max_matches = $count;
                    $detected_sentiment = $sentiment;
                }
            }
            
            return $detected_sentiment;
        }
        
        /**
         * Extract common phrases from messages
         */
        private function extract_common_phrases($data) {
            $all_messages = array();
            foreach ($data as $item) {
                if (isset($item['user_message'])) {
                    $all_messages[] = strtolower($item['user_message']);
                }
            }
            
            $word_count = array();
            $phrase_count = array();
            
            // Count words and simple phrases
            foreach ($all_messages as $message) {
                // Clean message
                $message = preg_replace('/[^\p{L}\p{N}\s]/u', '', $message);
                $words = explode(' ', $message);
                
                // Count single words
                foreach ($words as $word) {
                    $word = trim($word);
                    if (strlen($word) > 3) { // Ignore short words
                        if (!isset($word_count[$word])) {
                            $word_count[$word] = 0;
                        }
                        $word_count[$word]++;
                    }
                }
                
                // Count phrases (2-3 words)
                for ($i = 0; $i < count($words) - 1; $i++) {
                    if (strlen($words[$i]) > 2 && strlen($words[$i+1]) > 2) {
                        $phrase = $words[$i] . ' ' . $words[$i+1];
                        if (!isset($phrase_count[$phrase])) {
                            $phrase_count[$phrase] = 0;
                        }
                        $phrase_count[$phrase]++;
                    }
                    
                    if (isset($words[$i+2]) && strlen($words[$i]) > 2 && strlen($words[$i+1]) > 2 && strlen($words[$i+2]) > 2) {
                        $phrase = $words[$i] . ' ' . $words[$i+1] . ' ' . $words[$i+2];
                        if (!isset($phrase_count[$phrase])) {
                            $phrase_count[$phrase] = 0;
                        }
                        $phrase_count[$phrase]++;
                    }
                }
            }
            
            // Sort by frequency
            arsort($word_count);
            arsort($phrase_count);
            
            // Take top results
            $top_words = array_slice($word_count, 0, 10, true);
            $top_phrases = array_slice($phrase_count, 0, 10, true);
            
            // Format for output
            $formatted_words = array();
            foreach ($top_words as $word => $count) {
                if ($count > 1) { // Only include words that appear more than once
                    $formatted_words[] = array(
                        'text' => $word,
                        'count' => $count
                    );
                }
            }
            
            $formatted_phrases = array();
            foreach ($top_phrases as $phrase => $count) {
                if ($count > 1) { // Only include phrases that appear more than once
                    $formatted_phrases[] = array(
                        'text' => $phrase,
                        'count' => $count
                    );
                }
            }
            
            // Ensure we have default values
            if (empty($formatted_words)) {
                $formatted_words = array(array('text' => 'No common words yet', 'count' => 0));
            }
            
            if (empty($formatted_phrases)) {
                $formatted_phrases = array(array('text' => 'No common phrases yet', 'count' => 0));
            }
            
            return array(
                'words' => $formatted_words,
                'phrases' => $formatted_phrases
            );
        }
        
        /**
         * Get all dates in a range
         */
        private function get_date_range($dates) {
            if (empty($dates)) {
                return array();
            }
            
            sort($dates);
            $start = reset($dates);
            $end = end($dates);
            
            $range = array();
            $current = $start;
            
            while ($current <= $end) {
                $range[] = $current;
                $current = date('Y-m-d', strtotime($current . ' +1 day'));
            }
            
            return $range;
        }
        
        /**
         * Get user IP address
         */
        private function get_user_ip() {
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } else {
                $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
            }
            
            return sanitize_text_field($ip);
        }
        
        /**
         * Estimate token count in text
         */
        private function estimate_token_count($text) {
            // Very rough estimation based on average English word length
            $words = explode(' ', $text);
            return count($words) * 1.3; // Multiply by 1.3 as a rough estimate for tokens per word
        }
        
        /**
         * Submit training data to improve AI model
         */
        public function submit_training_data() {
            // Check permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Unauthorized access'));
            }
            
            // Check nonce
            check_ajax_referer('mptbm_chatbot_nonce', 'nonce');
            
            // Get training data
            $pairs = isset($_POST['training_pairs']) ? $_POST['training_pairs'] : array();
            $system_prompt = isset($_POST['system_prompt']) ? sanitize_textarea_field($_POST['system_prompt']) : '';
            
            if (empty($pairs) || !is_array($pairs)) {
                wp_send_json_error(array('message' => 'No training data provided'));
            }
            
            // Sanitize training pairs
            $sanitized_pairs = array();
            foreach ($pairs as $pair) {
                if (isset($pair['question']) && isset($pair['answer'])) {
                    $sanitized_pairs[] = array(
                        'question' => sanitize_textarea_field($pair['question']),
                        'answer' => sanitize_textarea_field($pair['answer'])
                    );
                }
            }
            
            // Get existing training data
            $training_data = get_option('mptbm_ai_chatbot_training_data', array());
            
            // Add new training data
            foreach ($sanitized_pairs as $pair) {
                $training_data[] = array(
                    'question' => $pair['question'],
                    'answer' => $pair['answer'],
                    'added_by' => get_current_user_id(),
                    'added_on' => current_time('mysql')
                );
            }
            
            // Update system prompt if provided
            if (!empty($system_prompt)) {
                $this->settings['system_prompt'] = $system_prompt;
                update_option('mptbm_ai_chatbot_settings', $this->settings);
            }
            
            // Save training data
            update_option('mptbm_ai_chatbot_training_data', $training_data);
            
            // Generate AI fine-tuning file
            $this->generate_fine_tuning_file();
            
            wp_send_json_success(array('message' => count($sanitized_pairs) . ' training pairs added successfully'));
            wp_die();
        }
        
        /**
         * Generate a file for AI model fine-tuning
         */
        private function generate_fine_tuning_file() {
            $training_data = get_option('mptbm_ai_chatbot_training_data', array());
            
            if (empty($training_data)) {
                return false;
            }
            
            // Prepare data for fine-tuning
            $fine_tuning_data = array();
            
            foreach ($training_data as $pair) {
                // Format for OpenAI fine-tuning
                $fine_tuning_data[] = array(
                    'messages' => array(
                        array(
                            'role' => 'system',
                            'content' => $this->system_prompt
                        ),
                        array(
                            'role' => 'user',
                            'content' => $pair['question']
                        ),
                        array(
                            'role' => 'assistant',
                            'content' => $pair['answer']
                        )
                    )
                );
            }
            
            // Create JSON file
            $upload_dir = wp_upload_dir();
            $fine_tuning_dir = $upload_dir['basedir'] . '/mptbm_fine_tuning';
            
            // Create directory if it doesn't exist
            if (!file_exists($fine_tuning_dir)) {
                wp_mkdir_p($fine_tuning_dir);
                
                // Create index.php to prevent directory listing
                $index_file = $fine_tuning_dir . '/index.php';
                if (!file_exists($index_file)) {
                    file_put_contents($index_file, '<?php // Silence is golden');
                }
                
                // Create .htaccess to prevent direct access
                $htaccess_file = $fine_tuning_dir . '/.htaccess';
                if (!file_exists($htaccess_file)) {
                    file_put_contents($htaccess_file, 'deny from all');
                }
            }
            
            // Generate timestamp for filename
            $timestamp = date('Ymd_His');
            $filename = 'mptbm_fine_tuning_' . $timestamp . '.jsonl';
            $file_path = $fine_tuning_dir . '/' . $filename;
            
            // Write data to JSONL file (one JSON object per line)
            $file = fopen($file_path, 'w');
            foreach ($fine_tuning_data as $data) {
                fwrite($file, json_encode($data) . "\n");
            }
            fclose($file);
            
            // Store the file path
            $this->settings['fine_tuning_file'] = $file_path;
            $this->settings['fine_tuning_timestamp'] = $timestamp;
            update_option('mptbm_ai_chatbot_settings', $this->settings);
            
            return $file_path;
        }
        
        /**
         * Process scheduled messages
         */
        public function process_scheduled_message($user_id, $message_data) {
            if (!is_array($message_data) || !isset($message_data['message']) || empty($message_data['message'])) {
                return;
            }
            
            $message = $message_data['message'];
            $session_id = isset($message_data['session_id']) ? $message_data['session_id'] : '';
            
            // If no session ID provided, try to get from user's sessions
            if (empty($session_id)) {
                $user_sessions = $this->get_user_sessions($user_id);
                if (!empty($user_sessions)) {
                    // Get the most recent session
                    $session_id = reset($user_sessions)['session_id'];
                } else {
                    // Generate a new session ID
                    $session_id = 'scheduled_' . md5(uniqid() . $user_id);
                }
            }
            
            // Get conversation history
            $conversation_history = $this->get_conversation_history($session_id);
            
            // Add system message if this is a new conversation
            if (empty($conversation_history)) {
                $conversation_history[] = array(
                    'role' => 'system',
                    'content' => $this->system_prompt
                );
            }
            
            // Add scheduled message as if from the AI
            $conversation_history[] = array(
                'role' => 'assistant',
                'content' => $message
            );
            
            // Save conversation history
            $this->save_conversation_history($session_id, $conversation_history);
            
            // Send notification to user if enabled
            if (isset($message_data['send_notification']) && $message_data['send_notification']) {
                $this->send_scheduled_message_notification($user_id, $message);
            }
            
            // Track this interaction in analytics
            do_action('mptbm_after_chat_message', array(
                'user_message' => '[SCHEDULED]',
                'ai_response' => $message,
                'session_id' => $session_id,
                'user_id' => $user_id,
                'response_time' => 0
            ));
            
            return true;
        }
        
        /**
         * Schedule a chatbot message for a user
         */
        public function schedule_message($user_id, $message, $schedule_time = null, $send_notification = true) {
            if (empty($user_id) || empty($message)) {
                return false;
            }
            
            // If no schedule time provided, default to now
            if (empty($schedule_time)) {
                $schedule_time = time();
            }
            
            // Get user sessions
            $user_sessions = $this->get_user_sessions($user_id);
            $session_id = !empty($user_sessions) ? reset($user_sessions)['session_id'] : '';
            
            // Prepare message data
            $message_data = array(
                'message' => $message,
                'session_id' => $session_id,
                'send_notification' => $send_notification
            );
            
            // Check if we need to schedule this for the future
            if ($schedule_time > time()) {
                wp_schedule_single_event($schedule_time, 'mptbm_scheduled_chatbot_message', array($user_id, $message_data));
                return true;
            }
            
            // Otherwise, process immediately
            return $this->process_scheduled_message($user_id, $message_data);
        }
        
        /**
         * Get all sessions for a user
         */
        private function get_user_sessions($user_id) {
            if (empty($user_id)) {
                return array();
            }
            
            $session_stats = get_option('mptbm_ai_chatbot_session_stats', array());
            $user_sessions = array();
            
            foreach ($session_stats as $session_id => $stats) {
                if (isset($stats['user_id']) && $stats['user_id'] == $user_id) {
                    $user_sessions[$session_id] = array(
                        'session_id' => $session_id,
                        'last_activity' => $stats['last_activity'],
                        'message_count' => $stats['message_count']
                    );
                }
            }
            
            // Sort by last activity (newest first)
            usort($user_sessions, function($a, $b) {
                return strtotime($b['last_activity']) - strtotime($a['last_activity']);
            });
            
            return $user_sessions;
        }
        
        /**
         * Send notification to user about scheduled message
         */
        private function send_scheduled_message_notification($user_id, $message) {
            if (empty($user_id)) {
                return false;
            }
            
            $user = get_user_by('id', $user_id);
            if (!$user) {
                return false;
            }
            
            $to = $user->user_email;
            $subject = __('New message from E-Cab AI Assistant', 'ecab-taxi-booking-manager');
            
            $message_content = sprintf(
                __('Hello %s,', 'ecab-taxi-booking-manager'), 
                $user->display_name
            ) . "\n\n";
            
            $message_content .= __('You have a new message from our AI Assistant:', 'ecab-taxi-booking-manager') . "\n\n";
            $message_content .= '"' . $message . '"' . "\n\n";
            $message_content .= __('Login to continue the conversation.', 'ecab-taxi-booking-manager') . "\n\n";
            $message_content .= site_url();
            
            $headers = array('Content-Type: text/plain; charset=UTF-8');
            
            return wp_mail($to, $subject, $message_content, $headers);
        }
        
        /**
         * Get conversation history
         */
        private function get_conversation_history($session_id) {
            $history_option_name = 'mptbm_conversation_' . $session_id;
            return get_option($history_option_name, array());
        }
        
        /**
         * Save conversation history
         */
        private function save_conversation_history($session_id, $conversation_history) {
            $history_option_name = 'mptbm_conversation_' . $session_id;
            update_option($history_option_name, $conversation_history);
        }
        
        /**
         * Log AI API errors to database
         * 
         * @param string $provider The AI provider (openai, claude, gemini)
         * @param string $error_message The error message
         * @param string $request_data The request data for debugging
         * @param string $response_data The response data if any
         */
        private function log_api_error($provider, $error_message, $request_data = '', $response_data = '') {
            global $wpdb;
            
            // Create table if it doesn't exist
            $this->maybe_create_error_log_table();
            
            // Get table name
            $table_name = $wpdb->prefix . 'mptbm_ai_error_logs';
            
            // Insert the error log
            $wpdb->insert(
                $table_name,
                array(
                    'timestamp' => current_time('mysql'),
                    'provider' => $provider,
                    'error_message' => $error_message,
                    'request_data' => $request_data,
                    'response_data' => $response_data,
                    'user_id' => get_current_user_id()
                ),
                array('%s', '%s', '%s', '%s', '%s', '%d')
            );
            
            // Also log to error_log for server logs
            error_log("MPTBM AI Error - Provider: $provider - Error: $error_message");
            
            return $wpdb->insert_id;
        }
        
        /**
         * Create error log table if it doesn't exist
         */
        private function maybe_create_error_log_table() {
            global $wpdb;
            
            $table_name = $wpdb->prefix . 'mptbm_ai_error_logs';
            
            // Check if table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            
            if (!$table_exists) {
                $charset_collate = $wpdb->get_charset_collate();
                
                $sql = "CREATE TABLE $table_name (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                    provider varchar(20) NOT NULL,
                    error_message text NOT NULL,
                    request_data longtext,
                    response_data longtext,
                    user_id bigint(20) unsigned,
                    PRIMARY KEY  (id)
                ) $charset_collate;";
                
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                dbDelta($sql);
            }
        }
    }
    
    new MPTBM_AI_Chatbot();
} 