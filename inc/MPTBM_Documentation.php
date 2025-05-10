<?php
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_Documentation')) {
    class MPTBM_Documentation {
        
        public function __construct() {
            // Add documentation page to admin menu
            add_action('admin_menu', array($this, 'add_documentation_page'));
        }
        
        /**
         * Add documentation page to admin menu
         */
        public function add_documentation_page() {
            add_submenu_page(
                'edit.php?post_type=mptbm_rent',
                __('E-Cab Documentation', 'ecab-taxi-booking-manager'),
                __('Documentation', 'ecab-taxi-booking-manager'),
                'edit_posts', // Lower required capability from manage_options to edit_posts
                'mptbm_documentation',
                array($this, 'render_documentation_page')
            );
        }
        
        /**
         * Render the documentation page
         */
        public function render_documentation_page() {
            // Get current tab
            $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
            
            // Define tabs
            $tabs = array(
                'general' => __('General', 'ecab-taxi-booking-manager'),
                'booking' => __('Booking', 'ecab-taxi-booking-manager'),
                'pricing' => __('Pricing', 'ecab-taxi-booking-manager'),
                'ai-chatbot' => __('AI Chatbot', 'ecab-taxi-booking-manager'),
                'settings' => __('Settings', 'ecab-taxi-booking-manager'),
                'api' => __('API Reference', 'ecab-taxi-booking-manager'),
                'troubleshooting' => __('Troubleshooting', 'ecab-taxi-booking-manager'),
            );
            
            // Current tab hash, needed for direct linking
            $hash = !empty($_GET['hash']) ? sanitize_text_field($_GET['hash']) : '';
            ?>
            <div class="wrap mptbm-documentation-wrap">
                <h1><?php _e('E-Cab Taxi Booking Manager Documentation', 'ecab-taxi-booking-manager'); ?></h1>
                
                <div class="mptbm-doc-header">
                    <div class="mptbm-doc-header-info">
                        <p class="mptbm-doc-version">
                            <?php printf(__('Plugin Version: %s', 'ecab-taxi-booking-manager'), MPTBM_PLUGIN_VERSION); ?>
                        </p>
                    </div>
                </div>
                
                <div class="mptbm-documentation-nav">
                    <h2 class="nav-tab-wrapper">
                        <?php
                        foreach ($tabs as $tab => $name) {
                            $class = ($tab === $current_tab) ? ' nav-tab-active' : '';
                            printf(
                                '<a class="nav-tab%s" href="%s">%s</a>',
                                $class,
                                admin_url('edit.php?post_type=mptbm_rent&page=mptbm_documentation&tab=' . $tab),
                                $name
                            );
                        }
                        ?>
                    </h2>
                </div>
                
                <div class="mptbm-documentation-content">
                    <?php
                    switch ($current_tab) {
                        case 'ai-chatbot':
                            $this->render_ai_chatbot_docs($hash);
                            break;
                        case 'booking':
                            $this->render_booking_docs($hash);
                            break;
                        case 'pricing':
                            $this->render_pricing_docs($hash);
                            break;
                        case 'settings':
                            $this->render_settings_docs($hash);
                            break;
                        case 'api':
                            $this->render_api_docs($hash);
                            break;
                        case 'troubleshooting':
                            $this->render_troubleshooting_docs($hash);
                            break;
                        default:
                            $this->render_general_docs($hash);
                            break;
                    }
                    ?>
                </div>
            </div>
            
            <style>
                .mptbm-documentation-wrap {
                    max-width: 1200px;
                    margin: 20px 0;
                }
                
                .mptbm-doc-header {
                    margin-bottom: 20px;
                }
                
                .mptbm-doc-version {
                    color: #666;
                    font-style: italic;
                }
                
                .mptbm-documentation-content {
                    background: white;
                    padding: 20px;
                    border-radius: 4px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                    margin-top: 20px;
                }
                
                .mptbm-doc-section {
                    margin-bottom: 30px;
                    border-bottom: 1px solid #eee;
                    padding-bottom: 20px;
                }
                
                .mptbm-doc-section:last-child {
                    border-bottom: none;
                    margin-bottom: 0;
                    padding-bottom: 0;
                }
                
                .mptbm-doc-section h2 {
                    margin-top: 0;
                    margin-bottom: 20px;
                    padding-bottom: 10px;
                    border-bottom: 1px solid #f0f0f0;
                }
                
                .mptbm-doc-section h3 {
                    margin-top: 25px;
                    margin-bottom: 15px;
                }
                
                .mptbm-doc-image {
                    max-width: 100%;
                    height: auto;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    margin: 15px 0;
                }
                
                .mptbm-doc-note {
                    background-color: #f8f9fa;
                    border-left: 4px solid #0073aa;
                    padding: 15px;
                    margin: 15px 0;
                    border-radius: 0 4px 4px 0;
                }
                
                .mptbm-doc-note.warning {
                    border-left-color: #dc3232;
                }
                
                .mptbm-doc-note.tip {
                    border-left-color: #46b450;
                }
                
                code {
                    background-color: #f6f8fa;
                    padding: 3px 5px;
                    border-radius: 3px;
                }
                
                pre {
                    background-color: #f6f8fa;
                    padding: 15px;
                    border-radius: 4px;
                    overflow-x: auto;
                    border: 1px solid #eee;
                }
                
                .mptbm-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 15px 0;
                }
                
                .mptbm-table th, .mptbm-table td {
                    border: 1px solid #ddd;
                    padding: 8px 12px;
                    text-align: left;
                }
                
                .mptbm-table th {
                    background-color: #f6f8fa;
                }
                
                .mptbm-table tr:nth-child(even) {
                    background-color: #f9f9f9;
                }

                /* API Documentation Styles */
                .api-endpoint {
                    background-color: #f9f9f9;
                    border-left: 4px solid #0073aa;
                    padding: 15px;
                    margin: 20px 0;
                    border-radius: 0 4px 4px 0;
                }
                
                .api-endpoint h4 {
                    margin-top: 0;
                    margin-bottom: 10px;
                    color: #0073aa;
                }
                
                .api-method {
                    font-weight: bold;
                    display: inline-block;
                    padding: 3px 6px;
                    border-radius: 3px;
                    margin-right: 10px;
                    color: white;
                }
                
                .api-method.get {
                    background-color: #61affe;
                }
                
                .api-method.post {
                    background-color: #49cc90;
                }
                
                .api-method.put {
                    background-color: #fca130;
                }
                
                .api-method.delete {
                    background-color: #f93e3e;
                }
                
                .api-url {
                    font-family: monospace;
                    padding: 5px;
                    background: #f0f0f0;
                    border-radius: 3px;
                    display: inline-block;
                    margin-bottom: 10px;
                }
                
                .api-params {
                    margin-top: 15px;
                }
                
                .api-response {
                    margin-top: 15px;
                    background: #f0f0f0;
                    padding: 10px;
                    border-radius: 3px;
                    font-family: monospace;
                    white-space: pre-wrap;
                }
            </style>
            <?php
        }
        
        /**
         * Render AI Chatbot documentation section
         */
        public function render_ai_chatbot_docs($hash = '') {
            ?>
            <div class="mptbm-doc-section" id="ai-chatbot-overview">
                <h2><?php _e('AI Chatbot', 'ecab-taxi-booking-manager'); ?></h2>
                <p><?php _e('The E-Cab AI Chatbot is a powerful addition to your taxi booking website that allows customers to get instant answers to their questions about your services, pricing, booking process, and more.', 'ecab-taxi-booking-manager'); ?></p>
                
                <h3><?php _e('Features', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li><?php _e('<strong>Real-time AI assistance</strong> - Helps customers with booking inquiries', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Multi-provider support</strong> - Works with OpenAI, Claude (Anthropic), and Gemini (Google)', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Customizable appearance</strong> - Match your brand colors and style', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Context-aware</strong> - Understands your specific taxi services, pricing, and FAQs', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Mobile-friendly</strong> - Works perfectly on all devices', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Message history</strong> - Can save conversations between sessions', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
            </div>
            
            <div class="mptbm-doc-section" id="ai-chatbot-setup">
                <h2><?php _e('Setup Instructions', 'ecab-taxi-booking-manager'); ?></h2>
                
                <h3><?php _e('1. Enable the Chatbot', 'ecab-taxi-booking-manager'); ?></h3>
                <ol>
                    <li><?php _e('Navigate to <strong>E-Cab Taxi Booking > AI Chatbot</strong> in your WordPress admin menu', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Check the <strong>Enable AI chatbot on the website</strong> box', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
                
                <h3><?php _e('2. Choose an AI Provider', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('Select your preferred AI provider:', 'ecab-taxi-booking-manager'); ?></p>
                <ul>
                    <li><?php _e('<strong>OpenAI</strong> - Uses GPT models (recommended)', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Claude</strong> - Uses Anthropic\'s Claude models', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Gemini</strong> - Uses Google\'s Gemini models', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <h3><?php _e('3. Enter API Key', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('You\'ll need an API key from your chosen provider:', 'ecab-taxi-booking-manager'); ?></p>
                
                <h4><?php _e('For OpenAI:', 'ecab-taxi-booking-manager'); ?></h4>
                <ol>
                    <li><?php _e('Visit <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI API Keys</a>', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Create a new API key', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Copy and paste it into the API Key field', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
                
                <h4><?php _e('For Claude (Anthropic):', 'ecab-taxi-booking-manager'); ?></h4>
                <ol>
                    <li><?php _e('Visit <a href="https://console.anthropic.com/" target="_blank">Anthropic Console</a>', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Create a new API key', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Copy and paste it into the API Key field', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
                
                <h4><?php _e('For Gemini (Google):', 'ecab-taxi-booking-manager'); ?></h4>
                <ol>
                    <li><?php _e('Visit <a href="https://ai.google.dev/" target="_blank">Google AI Studio</a>', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Create a new API key', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Copy and paste it into the API Key field', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
                
                <h3><?php _e('4. Select AI Model', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('Each provider offers different models with varying capabilities and costs:', 'ecab-taxi-booking-manager'); ?></p>
                
                <h4><?php _e('OpenAI Models:', 'ecab-taxi-booking-manager'); ?></h4>
                <ul>
                    <li><?php _e('<strong>GPT-4o</strong> - Most capable, newest model', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>GPT-4 Turbo</strong> - Powerful with good balance of capability and cost', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>GPT-3.5 Turbo</strong> - Most economical option', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <h4><?php _e('Claude Models:', 'ecab-taxi-booking-manager'); ?></h4>
                <ul>
                    <li><?php _e('<strong>Claude 3 Opus</strong> - Most capable Claude model', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Claude 3 Sonnet</strong> - Balanced performance and cost', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Claude 3 Haiku</strong> - Fastest response times', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Claude 2</strong> - Legacy model', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <h4><?php _e('Gemini Models:', 'ecab-taxi-booking-manager'); ?></h4>
                <ul>
                    <li><?php _e('<strong>Gemini 1.5 Pro</strong> - Most capable Gemini model', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Gemini Pro</strong> - Standard performance', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Gemini Flash</strong> - Optimized for speed', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <div class="mptbm-doc-note">
                    <p><?php _e('<strong>Important:</strong> Each provider has different pricing models. Check their websites for current pricing information.', 'ecab-taxi-booking-manager'); ?></p>
                </div>
                
                <h3><?php _e('5. Configure Message History (Optional)', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('You can choose to save chat history between user sessions:', 'ecab-taxi-booking-manager'); ?></p>
                <ul>
                    <li><?php _e('Enable or disable the "Save chat history between sessions" option', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Select how many messages to remember (5, 10, 15, 20, or unlimited)', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Note that more messages will use more API tokens and may increase costs', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <h3><?php _e('6. Customize the Chatbot (Optional)', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('You can customize various aspects of the chatbot:', 'ecab-taxi-booking-manager'); ?></p>
                <ul>
                    <li><?php _e('<strong>Primary Color</strong> - Choose a color that matches your brand', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Chat Title</strong> - Change the title shown in the chat header', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Welcome Message</strong> - Customize the initial greeting message', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>System Prompt</strong> - Advanced users can modify how the AI responds', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <h3><?php _e('7. Save Settings', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('Click the "Save Settings" button to apply your changes.', 'ecab-taxi-booking-manager'); ?></p>
            </div>
            
            <div class="mptbm-doc-section" id="ai-chatbot-usage">
                <h2><?php _e('How It Works', 'ecab-taxi-booking-manager'); ?></h2>
                
                <p><?php _e('The chatbot automatically collects information about your taxi services, including:', 'ecab-taxi-booking-manager'); ?></p>
                
                <ul>
                    <li><?php _e('Available vehicles and their details', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Pricing models and rates', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Maximum passenger capacity', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Service locations', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('FAQs from your service listings', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <p><?php _e('This allows the AI to provide accurate, contextual responses to customer inquiries about your specific services.', 'ecab-taxi-booking-manager'); ?></p>
                
                <h3><?php _e('User Interaction', 'ecab-taxi-booking-manager'); ?></h3>
                <ol>
                    <li><?php _e('A chat icon appears in the bottom right corner of your website', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Users click the icon to open the chat window', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('The welcome message is displayed', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Users type their questions or requests', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('The AI responds with relevant, helpful information', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Users can continue the conversation or clear the chat history', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
                
                <h3><?php _e('Information Flow', 'ecab-taxi-booking-manager'); ?></h3>
                <ol>
                    <li><?php _e('The user message is sent to the selected AI provider (OpenAI, Claude, or Gemini)', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('The system prompt and context about your services are also sent', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('The AI generates a response based on this information', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('The response is displayed in the chat window', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('If history saving is enabled, the conversation is stored for future sessions', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
            </div>
            
            <div class="mptbm-doc-section" id="ai-chatbot-best-practices">
                <h2><?php _e('Best Practices', 'ecab-taxi-booking-manager'); ?></h2>
                
                <ol>
                    <li>
                        <h3><?php _e('Test thoroughly', 'ecab-taxi-booking-manager'); ?></h3>
                        <p><?php _e('Try various questions to ensure the AI gives accurate responses about your services, pricing, and booking process.', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                    
                    <li>
                        <h3><?php _e('Start with default settings', 'ecab-taxi-booking-manager'); ?></h3>
                        <p><?php _e('The default system prompt is optimized for most use cases. Only modify it if you have specific needs.', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                    
                    <li>
                        <h3><?php _e('Monitor usage', 'ecab-taxi-booking-manager'); ?></h3>
                        <p><?php _e('AI services charge based on usage. Monitor your costs through your AI provider\'s dashboard.', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                    
                    <li>
                        <h3><?php _e('Update service info', 'ecab-taxi-booking-manager'); ?></h3>
                        <p><?php _e('Keep your service details and FAQs up to date for better AI responses.', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                    
                    <li>
                        <h3><?php _e('Balance history settings', 'ecab-taxi-booking-manager'); ?></h3>
                        <p><?php _e('Longer conversation history provides better context but uses more tokens. Find the right balance for your needs.', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                </ol>
            </div>
            
            <div class="mptbm-doc-section" id="ai-chatbot-troubleshooting">
                <h2><?php _e('Troubleshooting', 'ecab-taxi-booking-manager'); ?></h2>
                
                <h3><?php _e('Chatbot doesn\'t appear', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li><?php _e('Ensure the chatbot is enabled in settings', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Check for JavaScript errors in your browser console', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Verify that your API key is valid', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <h3><?php _e('Incorrect or generic responses', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li><?php _e('Make sure your service details are correctly entered', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Consider adding more detailed FAQs to your services', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Adjust the system prompt to be more specific', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <h3><?php _e('API errors', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li><?php _e('Verify your API key is correct and has sufficient permissions', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Check your API usage limits', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Ensure your billing information is up to date with the AI provider', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <h3><?php _e('Testing API Keys', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('Use the "Test API Key" button in the settings to verify your API key works correctly.', 'ecab-taxi-booking-manager'); ?></p>
                <p><?php _e('This sends a small test request to the AI provider and confirms the connection is working.', 'ecab-taxi-booking-manager'); ?></p>
                
                <div class="mptbm-doc-note warning">
                    <p><?php _e('<strong>Note:</strong> Usage of third-party AI services (OpenAI, Claude, Gemini) may incur costs based on their pricing models. E-Cab is not responsible for any charges from these services.', 'ecab-taxi-booking-manager'); ?></p>
                </div>
            </div>
            
            <div class="mptbm-doc-section" id="ai-chatbot-support">
                <h2><?php _e('Support', 'ecab-taxi-booking-manager'); ?></h2>
                <p><?php _e('If you need assistance with the E-Cab AI Chatbot, please contact our support team at support@mage-people.com.', 'ecab-taxi-booking-manager'); ?></p>
            </div>
            <?php
            
            // If a specific hash was passed, add JavaScript to scroll to that section
            if (!empty($hash)) {
                ?>
                <script>
                    jQuery(document).ready(function($) {
                        // Scroll to the section after page load
                        var target = document.getElementById('<?php echo esc_js($hash); ?>');
                        if (target) {
                            $('html, body').animate({
                                scrollTop: $(target).offset().top - 50
                            }, 500);
                        }
                    });
                </script>
                <?php
            }
        }
        
        /**
         * Render General documentation section
         */
        public function render_general_docs($hash = '') {
            ?>
            <div class="mptbm-doc-section" id="general-overview">
                <h2><?php _e('E-Cab Taxi Booking Manager Overview', 'ecab-taxi-booking-manager'); ?></h2>
                <p><?php _e('E-Cab Taxi Booking Manager is a complete solution for managing taxi and transportation booking services through your WordPress website. This documentation provides comprehensive information on how to set up and use the plugin effectively.', 'ecab-taxi-booking-manager'); ?></p>
                
                <h3><?php _e('Key Features', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li><?php _e('<strong>Multiple Pricing Models</strong> - Support for distance-based, hourly, and fixed route pricing', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>WooCommerce Integration</strong> - Process payments through WooCommerce', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Google Maps Integration</strong> - Calculate distances and display routes', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Responsive Design</strong> - Works on desktop, tablet, and mobile devices', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>AI Chatbot</strong> - Provide instant answers to customer questions', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Reporting</strong> - View booking and revenue reports', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
            </div>

            <div class="mptbm-doc-section" id="general-installation">
                <h2><?php _e('Installation', 'ecab-taxi-booking-manager'); ?></h2>
                <h3><?php _e('Requirements', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li><?php _e('WordPress 5.0 or higher', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('PHP 7.2 or higher', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('WooCommerce 4.0 or higher', 'ecab-taxi-booking-manager'); ?></li>
                </ul>

                <h3><?php _e('Installation Steps', 'ecab-taxi-booking-manager'); ?></h3>
                <ol>
                    <li>
                        <strong><?php _e('Download the Plugin', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Download the E-Cab Taxi Booking Manager from WordPress.org or from your MagePeople account.', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                    <li>
                        <strong><?php _e('Install the Plugin', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Navigate to WordPress Dashboard > Plugins > Add New > Upload Plugin. Choose the downloaded zip file and click "Install Now".', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                    <li>
                        <strong><?php _e('Activate the Plugin', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('After installation, click "Activate Plugin". If WooCommerce is not installed, you will be prompted to install it.', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                    <li>
                        <strong><?php _e('Complete the Quick Setup', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Follow the quick setup wizard to configure basic settings for your taxi booking system.', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                </ol>

                <div class="mptbm-doc-note">
                    <p><?php _e('<strong>Note:</strong> If you encounter any issues during installation, ensure that your server meets the requirements and that you have the latest version of WordPress and WooCommerce.', 'ecab-taxi-booking-manager'); ?></p>
                </div>
            </div>

            <div class="mptbm-doc-section" id="general-getting-started">
                <h2><?php _e('Getting Started', 'ecab-taxi-booking-manager'); ?></h2>
                
                <h3><?php _e('1. Create Your First Taxi Service', 'ecab-taxi-booking-manager'); ?></h3>
                <ol>
                    <li><?php _e('Go to <strong>E-Cab Taxi Booking > Add New</strong> in your WordPress admin menu', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Enter a title for your taxi service (e.g., "Airport Transfer")', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Add a description, features, and upload images of the vehicle', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Set pricing details (covered in the Pricing tab)', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Configure availability and other settings', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Click "Publish" to make the service available for booking', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
                
                <h3><?php _e('2. Add the Booking Form to Your Website', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('You can add the booking form to any page using one of these methods:', 'ecab-taxi-booking-manager'); ?></p>
                
                <h4><?php _e('Using Shortcode', 'ecab-taxi-booking-manager'); ?></h4>
                <p><?php _e('Add this shortcode to any page or post:', 'ecab-taxi-booking-manager'); ?></p>
                <pre>[mptbm_booking]</pre>
                
                <h4><?php _e('Using Block Editor', 'ecab-taxi-booking-manager'); ?></h4>
                <p><?php _e('In the block editor, search for "E-Cab" and add the E-Cab Taxi Booking block.', 'ecab-taxi-booking-manager'); ?></p>
                
                <h4><?php _e('Using Elementor', 'ecab-taxi-booking-manager'); ?></h4>
                <p><?php _e('If you use Elementor, search for "E-Cab" in the widgets panel and drag the widget to your page.', 'ecab-taxi-booking-manager'); ?></p>
                
                <h3><?php _e('3. Configure Payment Methods', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('E-Cab uses WooCommerce for payments:', 'ecab-taxi-booking-manager'); ?></p>
                <ol>
                    <li><?php _e('Go to <strong>WooCommerce > Settings > Payments</strong>', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Configure your preferred payment gateways (PayPal, Stripe, etc.)', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
                
                <h3><?php _e('4. Customize Your Settings', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('Go to <strong>E-Cab Taxi Booking > Settings</strong> to configure global settings for your booking system.', 'ecab-taxi-booking-manager'); ?></p>
            </div>

            <div class="mptbm-doc-section" id="general-security">
                <h2><?php _e('Security Best Practices', 'ecab-taxi-booking-manager'); ?></h2>
                
                <p><?php _e('E-Cab Taxi Booking Manager is built with security in mind, following WordPress best practices:', 'ecab-taxi-booking-manager'); ?></p>
                
                <h3><?php _e('Data Sanitization and Validation', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li><?php _e('All form inputs are sanitized using WordPress sanitization functions (e.g., <code>sanitize_text_field()</code>)', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('User capabilities are verified before allowing access to admin features', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Data validation occurs before processing or storing information', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <h3><?php _e('CSRF Protection', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('The plugin includes Cross-Site Request Forgery (CSRF) protection through WordPress nonces:', 'ecab-taxi-booking-manager'); ?></p>
                <ul>
                    <li><?php _e('All admin forms include nonce fields', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('AJAX requests verify nonces before processing', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <h3><?php _e('SQL Injection Prevention', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('The plugin uses WordPress\'s $wpdb prepared statements for all database queries to prevent SQL injection attacks.', 'ecab-taxi-booking-manager'); ?></p>
                
                <h3><?php _e('General Security Recommendations', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li><?php _e('Keep WordPress, WooCommerce, and all plugins (including E-Cab) updated to the latest versions', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Use strong passwords for admin accounts', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Implement a security plugin for additional protection', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Use SSL encryption (HTTPS) on your website', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <div class="mptbm-doc-note warning">
                    <p><?php _e('<strong>Important:</strong> Always keep a backup of your website before updating plugins or making significant changes.', 'ecab-taxi-booking-manager'); ?></p>
                </div>
            </div>
            
            <div class="mptbm-doc-section" id="general-shortcodes">
                <h2><?php _e('Available Shortcodes', 'ecab-taxi-booking-manager'); ?></h2>
                
                <p><?php _e('E-Cab Taxi Booking Manager provides several shortcodes to display booking forms and information:', 'ecab-taxi-booking-manager'); ?></p>
                
                <table class="mptbm-table">
                    <thead>
                        <tr>
                            <th><?php _e('Shortcode', 'ecab-taxi-booking-manager'); ?></th>
                            <th><?php _e('Description', 'ecab-taxi-booking-manager'); ?></th>
                            <th><?php _e('Parameters', 'ecab-taxi-booking-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>[mptbm_booking]</code></td>
                            <td><?php _e('Displays the main booking form', 'ecab-taxi-booking-manager'); ?></td>
                            <td>
                                <ul>
                                    <li><code>price_based</code>: <?php _e('Pricing model (distance, hourly, manual, fixed_hourly)', 'ecab-taxi-booking-manager'); ?></li>
                                    <li><code>form</code>: <?php _e('Form layout (default, inline)', 'ecab-taxi-booking-manager'); ?></li>
                                </ul>
                            </td>
                        </tr>
                        <tr>
                            <td><code>[mptbm_booking tab="yes" tabs="hourly,distance,manual"]</code></td>
                            <td><?php _e('Displays a tabbed booking form with multiple pricing options', 'ecab-taxi-booking-manager'); ?></td>
                            <td>
                                <ul>
                                    <li><code>tab</code>: <?php _e('Enable tabs (yes/no)', 'ecab-taxi-booking-manager'); ?></li>
                                    <li><code>tabs</code>: <?php _e('Comma-separated list of pricing models to include', 'ecab-taxi-booking-manager'); ?></li>
                                </ul>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <h3><?php _e('Examples', 'ecab-taxi-booking-manager'); ?></h3>
                
                <h4><?php _e('Basic Booking Form', 'ecab-taxi-booking-manager'); ?></h4>
                <pre>[mptbm_booking]</pre>
                
                <h4><?php _e('Distance-Based Pricing', 'ecab-taxi-booking-manager'); ?></h4>
                <pre>[mptbm_booking price_based="distance"]</pre>
                
                <h4><?php _e('Hourly Pricing with Inline Form', 'ecab-taxi-booking-manager'); ?></h4>
                <pre>[mptbm_booking price_based="hourly" form="inline"]</pre>
                
                <h4><?php _e('Tabbed Interface with Multiple Pricing Options', 'ecab-taxi-booking-manager'); ?></h4>
                <pre>[mptbm_booking tab="yes" tabs="hourly,distance,manual"]</pre>
            </div>
            <?php
        }
        
        /**
         * Render Booking documentation section
         */
        public function render_booking_docs($hash = '') {
            ?>
            <div class="mptbm-doc-section" id="booking-overview">
                <h2><?php _e('Booking System Overview', 'ecab-taxi-booking-manager'); ?></h2>
                <p><?php _e('E-Cab Taxi Booking Manager provides a comprehensive booking system that allows customers to book taxi services through your website. This section explains how to set up, manage, and customize the booking process.', 'ecab-taxi-booking-manager'); ?></p>
                
                <h3><?php _e('The Booking Process', 'ecab-taxi-booking-manager'); ?></h3>
                <ol>
                    <li><?php _e('<strong>Customer selects service</strong> - Customer chooses a taxi service from your offerings', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Enters journey details</strong> - Provides pickup/dropoff locations or selects a fixed route', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Selects date and time</strong> - Chooses when they need the service', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Selects extras</strong> - Adds any additional services they require', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Reviews price</strong> - Sees the total cost before proceeding', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Completes checkout</strong> - Provides contact details and makes payment', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Receives confirmation</strong> - Gets booking confirmation with details', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
            </div>

            <div class="mptbm-doc-section" id="booking-form">
                <h2><?php _e('Booking Form Configuration', 'ecab-taxi-booking-manager'); ?></h2>
                
                <h3><?php _e('Adding the Booking Form to Your Website', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('You can display the booking form using:', 'ecab-taxi-booking-manager'); ?></p>
                <ul>
                    <li>
                        <strong><?php _e('Shortcode', 'ecab-taxi-booking-manager'); ?></strong>
                        <pre>[mptbm_booking]</pre>
                    </li>
                    <li>
                        <strong><?php _e('Block Editor', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Search for "E-Cab" in the block inserter', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                    <li>
                        <strong><?php _e('Elementor Widget', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Find "E-Cab Taxi Booking" in the Elementor widget panel', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                </ul>
                
                <h3><?php _e('Booking Form Layouts', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('The booking form supports different layouts:', 'ecab-taxi-booking-manager'); ?></p>
                <ul>
                    <li>
                        <strong><?php _e('Default Layout', 'ecab-taxi-booking-manager'); ?></strong>
                        <pre>[mptbm_booking]</pre>
                    </li>
                    <li>
                        <strong><?php _e('Inline Layout', 'ecab-taxi-booking-manager'); ?></strong>
                        <pre>[mptbm_booking form="inline"]</pre>
                    </li>
                    <li>
                        <strong><?php _e('Tabbed Layout', 'ecab-taxi-booking-manager'); ?></strong>
                        <pre>[mptbm_booking tab="yes" tabs="hourly,distance,manual"]</pre>
                    </li>
                </ul>
                
                <h3><?php _e('Customizing the Booking Form', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('You can customize the form fields and appearance:', 'ecab-taxi-booking-manager'); ?></p>
                <ol>
                    <li><?php _e('Go to <strong>E-Cab Taxi Booking > Settings > Booking</strong>', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Configure fields to display (pickup location, dropoff, passenger count, etc.)', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Set required/optional fields', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Customize field labels and placeholder text', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Adjust form styling to match your website', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
                
                <div class="mptbm-doc-note">
                    <p><?php _e('<strong>Note:</strong> For advanced customization, you can override template files by copying them to your theme directory.', 'ecab-taxi-booking-manager'); ?></p>
                </div>
            </div>
            
            <div class="mptbm-doc-section" id="booking-management">
                <h2><?php _e('Managing Bookings', 'ecab-taxi-booking-manager'); ?></h2>
                
                <h3><?php _e('Viewing Bookings', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('All bookings are managed through the WooCommerce orders system:', 'ecab-taxi-booking-manager'); ?></p>
                <ol>
                    <li><?php _e('Go to <strong>WooCommerce > Orders</strong>', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('View the list of all bookings/orders', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Click on an order to view full details', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
                
                <h3><?php _e('Booking Statuses', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('Bookings follow WooCommerce order statuses:', 'ecab-taxi-booking-manager'); ?></p>
                <ul>
                    <li><?php _e('<strong>Pending payment</strong> - Booking created but awaiting payment', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Processing</strong> - Payment received, booking confirmed', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Completed</strong> - Service provided, booking fulfilled', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Cancelled</strong> - Booking cancelled by customer or admin', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Refunded</strong> - Payment returned to customer', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Failed</strong> - Payment failed or booking process incomplete', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <h3><?php _e('Managing Booking Calendar', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('You can view bookings in a calendar format:', 'ecab-taxi-booking-manager'); ?></p>
                <ol>
                    <li><?php _e('Go to <strong>E-Cab Taxi Booking > Calendar</strong>', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('View bookings by day, week, or month', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Filter by service type or status', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Click on a booking to view or edit details', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
                
                <h3><?php _e('Manual Booking Creation', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('Administrators can create bookings manually:', 'ecab-taxi-booking-manager'); ?></p>
                <ol>
                    <li><?php _e('Go to <strong>WooCommerce > Orders > Add Order</strong>', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Add a customer or create a new one', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Add the taxi service product', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Enter booking details in the product meta fields', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Complete the order information', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Set the order status as needed', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
            </div>
            
            <div class="mptbm-doc-section" id="booking-notifications">
                <h2><?php _e('Booking Notifications', 'ecab-taxi-booking-manager'); ?></h2>
                
                <h3><?php _e('Email Notifications', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('E-Cab uses WooCommerce email system for notifications:', 'ecab-taxi-booking-manager'); ?></p>
                <ol>
                    <li><?php _e('Go to <strong>WooCommerce > Settings > Emails</strong>', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Configure the email templates for different booking statuses', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Customize email content, recipient, subject, etc.', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
                
                <p><?php _e('Key email notifications include:', 'ecab-taxi-booking-manager'); ?></p>
                <ul>
                    <li><?php _e('<strong>New Order</strong> - Sent to admin when a new booking is made', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Processing Order</strong> - Sent to customer when payment is received', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Completed Order</strong> - Sent when booking is marked as fulfilled', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Cancelled Order</strong> - Sent when booking is cancelled', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Refunded Order</strong> - Sent when payment is refunded', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <h3><?php _e('SMS Notifications', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('For SMS notifications, you can use the Global SMS Notification plugin:', 'ecab-taxi-booking-manager'); ?></p>
                <ol>
                    <li><?php _e('Install and activate the Global SMS Notification plugin', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Configure your SMS gateway provider', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Set up SMS templates for different booking events', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
                
                <h3><?php _e('Custom Notification Templates', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('You can customize notification templates:', 'ecab-taxi-booking-manager'); ?></p>
                <ol>
                    <li><?php _e('Create an "emails" folder in your theme', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Copy WooCommerce email templates to this folder', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Modify the templates with taxi booking specific information', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
                
                <div class="mptbm-doc-note">
                    <p><?php _e('<strong>Tip:</strong> Include booking details like pickup location, dropoff location, date, time, and vehicle information in your notification templates.', 'ecab-taxi-booking-manager'); ?></p>
                </div>
            </div>
            
            <div class="mptbm-doc-section" id="booking-pdf-tickets">
                <h2><?php _e('PDF Tickets and Invoices', 'ecab-taxi-booking-manager'); ?></h2>
                
                <h3><?php _e('Enabling PDF Tickets', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('E-Cab integrates with MagePeople PDF Support for ticket generation:', 'ecab-taxi-booking-manager'); ?></p>
                <ol>
                    <li><?php _e('Ensure MagePeople PDF Support plugin is activated', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Go to <strong>E-Cab Taxi Booking > Settings > PDF</strong>', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Enable PDF ticket generation', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Configure PDF template settings', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
                
                <h3><?php _e('Customizing PDF Tickets', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('You can customize the appearance and content of PDF tickets:', 'ecab-taxi-booking-manager'); ?></p>
                <ul>
                    <li><?php _e('<strong>Company Information</strong> - Add your business details and logo', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Ticket Layout</strong> - Adjust design and formatting', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>QR/Barcode</strong> - Include for easy verification', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Terms & Conditions</strong> - Add legal information', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <h3><?php _e('Sending PDF Tickets', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('PDF tickets can be delivered in multiple ways:', 'ecab-taxi-booking-manager'); ?></p>
                <ul>
                    <li><?php _e('<strong>Email Attachment</strong> - Automatically attached to order emails', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Customer Account</strong> - Available for download in customer account', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Admin Download</strong> - Generate and download from admin order screen', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
            </div>
            
            <div class="mptbm-doc-section" id="booking-security">
                <h2><?php _e('Booking Security and Data Protection', 'ecab-taxi-booking-manager'); ?></h2>
                
                <h3><?php _e('Security Features', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('E-Cab Taxi Booking Manager implements several security measures:', 'ecab-taxi-booking-manager'); ?></p>
                <ul>
                    <li><?php _e('<strong>Data Validation</strong> - All form inputs are validated and sanitized', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>CSRF Protection</strong> - Uses WordPress nonces to prevent cross-site request forgery', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>SQL Injection Prevention</strong> - Uses prepared statements for database queries', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>XSS Prevention</strong> - Escapes output to prevent cross-site scripting', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>User Capability Checks</strong> - Ensures only authorized users can manage bookings', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <h3><?php _e('GDPR Compliance', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('The plugin is designed with GDPR considerations:', 'ecab-taxi-booking-manager'); ?></p>
                <ul>
                    <li><?php _e('<strong>Data Minimization</strong> - Collects only necessary information', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Consent Management</strong> - Options for privacy policy acceptance', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Data Access</strong> - Customers can view their data in their accounts', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Data Export</strong> - Compatible with WooCommerce data export tools', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Data Deletion</strong> - Supports removal of personal data as required', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <h3><?php _e('Payment Security', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('Payment processing security is handled through WooCommerce:', 'ecab-taxi-booking-manager'); ?></p>
                <ul>
                    <li><?php _e('<strong>Secure Payment Gateways</strong> - Integration with trusted payment providers', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>PCI Compliance</strong> - Payment details handled by payment processors', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>SSL Requirements</strong> - Enforced for secure checkout', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <div class="mptbm-doc-note warning">
                    <p><?php _e('<strong>Important:</strong> Always ensure your website uses SSL encryption (HTTPS) to protect customer data during the booking process.', 'ecab-taxi-booking-manager'); ?></p>
                </div>
            </div>
            <?php
        }
        
        /**
         * Render Pricing documentation section
         */
        public function render_pricing_docs($hash = '') {
            ?>
            <div class="mptbm-doc-section" id="pricing-overview">
                <h2><?php _e('Pricing Models Overview', 'ecab-taxi-booking-manager'); ?></h2>
                <p><?php _e('E-Cab Taxi Booking Manager offers flexible pricing models to suit various transportation business needs. This section explains how to set up and manage different pricing options.', 'ecab-taxi-booking-manager'); ?></p>
                
                <h3><?php _e('Available Pricing Models', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li><?php _e('<strong>Distance-Based Pricing</strong> - Charge based on the distance traveled', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Hourly Pricing</strong> - Charge based on time duration', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Fixed Route Pricing</strong> - Predefined prices for specific routes', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Manual Pricing</strong> - Custom pricing based on specific criteria', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
            </div>

            <div class="mptbm-doc-section" id="pricing-distance">
                <h2><?php _e('Distance-Based Pricing', 'ecab-taxi-booking-manager'); ?></h2>
                
                <p><?php _e('Distance-based pricing calculates the fare based on the distance between pickup and drop-off locations.', 'ecab-taxi-booking-manager'); ?></p>
                
                <h3><?php _e('Setting Up Distance-Based Pricing', 'ecab-taxi-booking-manager'); ?></h3>
                <ol>
                    <li><?php _e('Go to your taxi service edit page (E-Cab Taxi Booking > All Services > Edit)', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Navigate to the "Pricing" tab', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Enable "Distance-Based Pricing" option', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Set the "Base Price" (starting fare)', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Enter "Price Per Kilometer/Mile"', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Optionally, set a "Minimum Distance" threshold', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
                
                <h3><?php _e('Distance Calculation Methods', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('E-Cab offers two methods for calculating distance:', 'ecab-taxi-booking-manager'); ?></p>
                <ul>
                    <li><?php _e('<strong>Google Maps API</strong> - Accurate real-world routing, requires API key', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Haversine Formula</strong> - Direct "as the crow flies" distance, no API requirement', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <h3><?php _e('Setting Up Google Maps API for Distance Calculation', 'ecab-taxi-booking-manager'); ?></h3>
                <ol>
                    <li><?php _e('Go to <strong>E-Cab Taxi Booking > Settings > API</strong>', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Enter your Google Maps API Key', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Ensure the "Distance Matrix API" is enabled in your Google Cloud Console', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
                
                <div class="mptbm-doc-note">
                    <p><?php _e('<strong>Note:</strong> Google Maps API usage may incur costs depending on your usage volume. Check <a href="https://cloud.google.com/maps-platform/pricing" target="_blank">Google Maps Platform Pricing</a> for details.', 'ecab-taxi-booking-manager'); ?></p>
                </div>
                
                <h3><?php _e('Advanced Distance-Based Pricing Options', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li><?php _e('<strong>Price Tiers</strong> - Set different rates for different distance ranges', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Return Trip Discount</strong> - Offer discounts for return journeys', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Extra Distance Fee</strong> - Charge additional fees for distances beyond a threshold', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
            </div>
            
            <div class="mptbm-doc-section" id="pricing-hourly">
                <h2><?php _e('Hourly Pricing', 'ecab-taxi-booking-manager'); ?></h2>
                
                <p><?php _e('Hourly pricing allows you to charge based on the duration of the booking.', 'ecab-taxi-booking-manager'); ?></p>
                
                <h3><?php _e('Setting Up Hourly Pricing', 'ecab-taxi-booking-manager'); ?></h3>
                <ol>
                    <li><?php _e('Go to your taxi service edit page', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Navigate to the "Pricing" tab', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Enable "Hourly Pricing" option', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Set the "Base Price" (minimum booking fee)', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Enter "Price Per Hour"', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Define "Minimum Hours" for booking', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
                
                <h3><?php _e('Time Block Options', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('You can define how time is calculated and billed:', 'ecab-taxi-booking-manager'); ?></p>
                <ul>
                    <li><?php _e('<strong>Hour Blocks</strong> - Charge in full hour increments', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Half-Hour Blocks</strong> - Charge in 30-minute increments', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Custom Blocks</strong> - Define your own time intervals', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <h3><?php _e('Advanced Hourly Pricing Features', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li><?php _e('<strong>Peak Hour Rates</strong> - Set higher rates for busy times', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Extended Duration Discounts</strong> - Offer discounts for longer bookings', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Waiting Time Charges</strong> - Additional fees for driver waiting time', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
            </div>
            
            <div class="mptbm-doc-section" id="pricing-fixed-route">
                <h2><?php _e('Fixed Route Pricing', 'ecab-taxi-booking-manager'); ?></h2>
                
                <p><?php _e('Fixed route pricing allows you to set predefined prices for specific routes or destinations.', 'ecab-taxi-booking-manager'); ?></p>
                
                <h3><?php _e('Setting Up Fixed Route Pricing', 'ecab-taxi-booking-manager'); ?></h3>
                <ol>
                    <li><?php _e('Go to your taxi service edit page', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Navigate to the "Pricing" tab', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Enable "Fixed Route Pricing" option', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Click "Add New Route"', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Define pickup location (e.g., "Airport Terminal 1")', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Define drop-off location (e.g., "City Center")', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Set the fixed price for this route', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Add as many routes as needed', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
                
                <h3><?php _e('Popular Use Cases for Fixed Routes', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li><?php _e('<strong>Airport Transfers</strong> - Fixed rates to/from airports', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Hotel Shuttles</strong> - Preset routes between hotels and attractions', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>City Tours</strong> - Set prices for standard tour routes', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Event Transportation</strong> - Fixed prices to event venues', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
            </div>
            
            <div class="mptbm-doc-section" id="pricing-manual">
                <h2><?php _e('Manual Pricing', 'ecab-taxi-booking-manager'); ?></h2>
                
                <p><?php _e('Manual pricing gives you the flexibility to create custom pricing rules based on various criteria.', 'ecab-taxi-booking-manager'); ?></p>
                
                <h3><?php _e('Setting Up Manual Pricing', 'ecab-taxi-booking-manager'); ?></h3>
                <ol>
                    <li><?php _e('Go to your taxi service edit page', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Navigate to the "Pricing" tab', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Enable "Manual Pricing" option', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Define your pricing rules based on custom fields', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
                
                <h3><?php _e('Custom Pricing Factors', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('You can base manual pricing on factors such as:', 'ecab-taxi-booking-manager'); ?></p>
                <ul>
                    <li><?php _e('<strong>Vehicle Type</strong> - Different rates for different vehicles', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Number of Passengers</strong> - Adjust pricing based on passenger count', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Time of Day</strong> - Different rates for day/night service', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Day of Week</strong> - Weekend vs. weekday pricing', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Season</strong> - Peak season vs. off-season rates', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
            </div>
            
            <div class="mptbm-doc-section" id="pricing-extras">
                <h2><?php _e('Additional Fees and Discounts', 'ecab-taxi-booking-manager'); ?></h2>
                
                <p><?php _e('E-Cab allows you to add extra charges or offer discounts to enhance your pricing strategy.', 'ecab-taxi-booking-manager'); ?></p>
                
                <h3><?php _e('Setting Up Additional Fees', 'ecab-taxi-booking-manager'); ?></h3>
                <ol>
                    <li><?php _e('Go to your taxi service edit page', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Navigate to the "Extra Services" tab', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Click "Add New Extra Service"', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Enter service name (e.g., "Airport Meet & Greet")', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Set the price for this service', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Choose if it\'s an optional or mandatory fee', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
                
                <h3><?php _e('Common Extra Services', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li><?php _e('<strong>Baby Seat</strong> - Child safety seat rental', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Extra Luggage</strong> - Charge for excess baggage', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Wi-Fi Access</strong> - In-vehicle internet service', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Premium Pickup</strong> - Luxury vehicle upgrades', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Additional Stops</strong> - Fees for multiple destinations', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <h3><?php _e('Setting Up Discounts', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('You can create various discount types:', 'ecab-taxi-booking-manager'); ?></p>
                <ul>
                    <li><?php _e('<strong>Fixed Amount</strong> - Deduct a specific amount from the total', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Percentage</strong> - Apply a percentage discount', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Coupon Codes</strong> - Create special offers with codes', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Volume Discounts</strong> - Reduced rates for multiple bookings', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <div class="mptbm-doc-note tip">
                    <p><?php _e('<strong>Tip:</strong> For coupon codes, you can use WooCommerce coupon functionality which integrates seamlessly with E-Cab Taxi Booking.', 'ecab-taxi-booking-manager'); ?></p>
                </div>
            </div>
            
            <div class="mptbm-doc-section" id="pricing-tax">
                <h2><?php _e('Tax Configuration', 'ecab-taxi-booking-manager'); ?></h2>
                
                <p><?php _e('E-Cab Taxi Booking Manager uses WooCommerce\'s tax settings to handle taxes.', 'ecab-taxi-booking-manager'); ?></p>
                
                <h3><?php _e('Setting Up Taxes', 'ecab-taxi-booking-manager'); ?></h3>
                <ol>
                    <li><?php _e('Go to <strong>WooCommerce > Settings > Tax</strong>', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Configure your tax options as needed', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Set up tax classes if you have different tax rates for different services', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
                
                <p><?php _e('For taxi services, you may need to configure:', 'ecab-taxi-booking-manager'); ?></p>
                <ul>
                    <li><?php _e('<strong>Standard Rate</strong> - For regular taxi services', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Reduced Rate</strong> - If applicable for certain types of transport', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Zero Rate</strong> - For tax-exempt services', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <div class="mptbm-doc-note">
                    <p><?php _e('<strong>Important:</strong> Tax regulations vary by country and region. Ensure your tax settings comply with local laws.', 'ecab-taxi-booking-manager'); ?></p>
                </div>
            </div>
            
            <div class="mptbm-doc-section" id="pricing-security">
                <h2><?php _e('Pricing Security Considerations', 'ecab-taxi-booking-manager'); ?></h2>
                
                <p><?php _e('E-Cab implements several security measures to ensure pricing integrity:', 'ecab-taxi-booking-manager'); ?></p>
                
                <h3><?php _e('Price Manipulation Prevention', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li><?php _e('<strong>Server-Side Validation</strong> - All pricing calculations are verified on the server', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Data Sanitization</strong> - All price inputs are sanitized before processing', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Price Verification</strong> - Prices are verified before order completion', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <h3><?php _e('Administrator Settings Security', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li><?php _e('<strong>Capability Checks</strong> - Only authorized users can modify pricing', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Change Logging</strong> - Price changes are logged for auditing', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Data Encryption</strong> - Sensitive pricing data is stored securely', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <div class="mptbm-doc-note warning">
                    <p><?php _e('<strong>Important:</strong> Always restrict admin access to trusted individuals and use strong passwords to prevent unauthorized price modifications.', 'ecab-taxi-booking-manager'); ?></p>
                </div>
            </div>
            <?php
        }
        
        /**
         * Render Settings documentation section
         */
        public function render_settings_docs($hash = '') {
            ?>
            <div class="mptbm-doc-section" id="settings-overview">
                <h2><?php _e('Settings Overview', 'ecab-taxi-booking-manager'); ?></h2>
                <p><?php _e('E-Cab Taxi Booking Manager provides a comprehensive settings panel that allows you to configure all aspects of your taxi booking system. This section explains all available settings and how to configure them for optimal performance.', 'ecab-taxi-booking-manager'); ?></p>
                
                <h3><?php _e('Accessing Settings', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('To access the settings panel:', 'ecab-taxi-booking-manager'); ?></p>
                <ol>
                    <li><?php _e('Go to your WordPress admin dashboard', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Navigate to <strong>E-Cab Taxi Booking > Settings</strong>', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Choose from the various setting tabs (General, Booking, Display, API, etc.)', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
            </div>

            <div class="mptbm-doc-section" id="settings-general">
                <h2><?php _e('General Settings', 'ecab-taxi-booking-manager'); ?></h2>
                
                <h3><?php _e('Basic Configuration', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Currency Settings', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Currency settings are inherited from WooCommerce. To change currency:', 'ecab-taxi-booking-manager'); ?></p>
                        <ol>
                            <li><?php _e('Go to <strong>WooCommerce > Settings > General</strong>', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Select your currency and configure currency position, thousand separator, and decimal separator', 'ecab-taxi-booking-manager'); ?></li>
                        </ol>
                    </li>
                    <li>
                        <strong><?php _e('Date & Time Format', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Configure how dates and times appear throughout the booking system:', 'ecab-taxi-booking-manager'); ?></p>
                        <ul>
                            <li><?php _e('Date Format: Choose between various date formats (MM/DD/YYYY, DD/MM/YYYY, etc.)', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Time Format: Select 12-hour or 24-hour format', 'ecab-taxi-booking-manager'); ?></li>
                        </ul>
                    </li>
                    <li>
                        <strong><?php _e('Distance Unit', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Select between kilometers or miles for distance calculations and display', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                </ul>
                
                <h3><?php _e('Business Information', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Company Details', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Enter your business information for receipts, tickets, and communications:', 'ecab-taxi-booking-manager'); ?></p>
                        <ul>
                            <li><?php _e('Company Name', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Company Address', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Contact Phone', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Contact Email', 'ecab-taxi-booking-manager'); ?></li>
                        </ul>
                    </li>
                    <li>
                        <strong><?php _e('Company Logo', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Upload your logo to display on booking forms, tickets, and receipts', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                </ul>
                
                <h3><?php _e('Default Service Settings', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Default Availability', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Set default operating hours for new services', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                    <li>
                        <strong><?php _e('Advance Booking Period', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Configure how far in advance customers can book (e.g., 1 hour, 2 days, etc.)', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                    <li>
                        <strong><?php _e('Maximum Booking Period', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Set how far in the future bookings can be made (e.g., 30 days, 6 months, etc.)', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                </ul>
            </div>
            
            <div class="mptbm-doc-section" id="settings-booking">
                <h2><?php _e('Booking Settings', 'ecab-taxi-booking-manager'); ?></h2>
                
                <h3><?php _e('Booking Form Configuration', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Form Fields', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Configure which fields appear on the booking form:', 'ecab-taxi-booking-manager'); ?></p>
                        <ul>
                            <li><?php _e('Enable/disable specific fields', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Mark fields as required or optional', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Set default values for fields', 'ecab-taxi-booking-manager'); ?></li>
                        </ul>
                    </li>
                    <li>
                        <strong><?php _e('Form Labels', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Customize the text labels for booking form fields', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                    <li>
                        <strong><?php _e('Form Validation', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Configure validation rules for form fields (phone format, email validation, etc.)', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                </ul>
                
                <h3><?php _e('Checkout Settings', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Checkout Process', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Configure the booking checkout process:', 'ecab-taxi-booking-manager'); ?></p>
                        <ul>
                            <li><?php _e('Direct to WooCommerce Checkout: Enable/disable', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Cart Behavior: Add to cart and redirect or stay on page', 'ecab-taxi-booking-manager'); ?></li>
                        </ul>
                    </li>
                    <li>
                        <strong><?php _e('Guest Checkout', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Allow customers to book without creating an account (uses WooCommerce settings)', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                    <li>
                        <strong><?php _e('Thank You Page', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Configure the content displayed after successful booking', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                </ul>
                
                <h3><?php _e('Booking Rules', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Minimum Notice Period', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Set how much advance notice is required for bookings (e.g., 1 hour, 24 hours)', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                    <li>
                        <strong><?php _e('Maximum Passengers', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Set default passenger limits for vehicles', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                    <li>
                        <strong><?php _e('Booking Buffer Time', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Add buffer time between bookings for the same vehicle', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                </ul>
            </div>
            
            <div class="mptbm-doc-section" id="settings-display">
                <h2><?php _e('Display Settings', 'ecab-taxi-booking-manager'); ?></h2>
                
                <h3><?php _e('Style Customization', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Color Scheme', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Customize colors for various elements:', 'ecab-taxi-booking-manager'); ?></p>
                        <ul>
                            <li><?php _e('Primary Color: Main theme color', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Secondary Color: Accent color', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Button Colors: For call-to-action buttons', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Text Colors: For various text elements', 'ecab-taxi-booking-manager'); ?></li>
                        </ul>
                    </li>
                    <li>
                        <strong><?php _e('Form Style', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Choose between different form styles:', 'ecab-taxi-booking-manager'); ?></p>
                        <ul>
                            <li><?php _e('Classic: Traditional form layout', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Modern: Contemporary, clean design', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Minimal: Simplified, distraction-free design', 'ecab-taxi-booking-manager'); ?></li>
                        </ul>
                    </li>
                    <li>
                        <strong><?php _e('Custom CSS', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Add custom CSS code for additional style customization', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                </ul>
                
                <h3><?php _e('Result Page Settings', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Results Layout', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Configure how search results are displayed:', 'ecab-taxi-booking-manager'); ?></p>
                        <ul>
                            <li><?php _e('Grid View: Display results in a grid', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('List View: Display results in a list', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Items Per Page: How many services to show per page', 'ecab-taxi-booking-manager'); ?></li>
                        </ul>
                    </li>
                    <li>
                        <strong><?php _e('Sort Options', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Configure sorting options for search results (by price, rating, etc.)', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                    <li>
                        <strong><?php _e('Filter Display', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Set which filters are available on the results page', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                </ul>
                
                <h3><?php _e('Map Display', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Map Style', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Choose between different map styles:', 'ecab-taxi-booking-manager'); ?></p>
                        <ul>
                            <li><?php _e('Standard: Default Google Maps style', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Silver: Light, desaturated style', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Retro: Vintage-inspired style', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Dark: Night mode style', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Custom: Upload your own map style JSON', 'ecab-taxi-booking-manager'); ?></li>
                        </ul>
                    </li>
                    <li>
                        <strong><?php _e('Map Controls', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Configure which controls appear on maps:', 'ecab-taxi-booking-manager'); ?></p>
                        <ul>
                            <li><?php _e('Zoom Controls: Enable/disable', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Street View: Enable/disable', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Map Type Control: Enable/disable', 'ecab-taxi-booking-manager'); ?></li>
                        </ul>
                    </li>
                    <li>
                        <strong><?php _e('Default Map Center', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Set the default center point and zoom level for maps', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                </ul>
            </div>
            
            <div class="mptbm-doc-section" id="settings-api">
                <h2><?php _e('API Settings', 'ecab-taxi-booking-manager'); ?></h2>
                
                <h3><?php _e('Google Maps API', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('Configure Google Maps integration for address autocomplete, distance calculation, and route display:', 'ecab-taxi-booking-manager'); ?></p>
                <ol>
                    <li>
                        <strong><?php _e('API Key Setup', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Enter your Google Maps API key:', 'ecab-taxi-booking-manager'); ?></p>
                        <ol>
                            <li><?php _e('Go to <a href="https://console.cloud.google.com/google/maps-apis" target="_blank">Google Cloud Console</a>', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Create a new project or select an existing one', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Enable the required APIs (Maps JavaScript API, Geocoding API, Distance Matrix API, Places API)', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Create an API key and restrict it for security', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Copy the API key to the E-Cab settings', 'ecab-taxi-booking-manager'); ?></li>
                        </ol>
                    </li>
                    <li>
                        <strong><?php _e('API Services', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Enable/disable specific Google Maps API services:', 'ecab-taxi-booking-manager'); ?></p>
                        <ul>
                            <li><?php _e('Places Autocomplete: For address suggestions', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Distance Matrix: For distance and duration calculations', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Directions: For route display', 'ecab-taxi-booking-manager'); ?></li>
                        </ul>
                    </li>
                    <li>
                        <strong><?php _e('API Key Security', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Secure your Google Maps API key:', 'ecab-taxi-booking-manager'); ?></p>
                        <ul>
                            <li><?php _e('HTTP Referrer Restriction: Limit usage to your domain', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('API Usage Quotas: Set limits to prevent unexpected charges', 'ecab-taxi-booking-manager'); ?></li>
                        </ul>
                    </li>
                </ol>
                
                <div class="mptbm-doc-note warning">
                    <p><?php _e('<strong>Important:</strong> Google Maps API usage may incur costs. Always set up billing alerts and quotas in the Google Cloud Console to avoid unexpected charges.', 'ecab-taxi-booking-manager'); ?></p>
                </div>
                
                <h3><?php _e('Other API Integrations', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('REST API', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Configure the E-Cab REST API for external integrations:', 'ecab-taxi-booking-manager'); ?></p>
                        <ul>
                            <li><?php _e('API Access: Enable/disable', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('API Authentication: Configure authentication methods', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('API Keys: Manage API keys for external services', 'ecab-taxi-booking-manager'); ?></li>
                        </ul>
                    </li>
                    <li>
                        <strong><?php _e('AI Chatbot API', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Configure AI Chatbot integration (see AI Chatbot documentation tab for details)', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                </ul>
            </div>
            
            <div class="mptbm-doc-section" id="settings-advanced">
                <h2><?php _e('Advanced Settings', 'ecab-taxi-booking-manager'); ?></h2>
                
                <h3><?php _e('Performance Optimization', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Caching', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Configure caching options for better performance:', 'ecab-taxi-booking-manager'); ?></p>
                        <ul>
                            <li><?php _e('Enable/disable caching of location data', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Cache expiration time', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Clear cache button to refresh data', 'ecab-taxi-booking-manager'); ?></li>
                        </ul>
                    </li>
                    <li>
                        <strong><?php _e('Asset Loading', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Configure how and when plugin assets (CSS/JS) are loaded:', 'ecab-taxi-booking-manager'); ?></p>
                        <ul>
                            <li><?php _e('Load only on taxi booking pages', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Minify CSS and JS files', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Combine files to reduce HTTP requests', 'ecab-taxi-booking-manager'); ?></li>
                        </ul>
                    </li>
                </ul>
                
                <h3><?php _e('Debug Options', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Debug Mode', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Enable/disable debug mode for troubleshooting', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                    <li>
                        <strong><?php _e('Error Logging', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Configure error logging options', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                    <li>
                        <strong><?php _e('API Request Logging', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Log API requests for debugging external integrations', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                </ul>
                
                <h3><?php _e('Data Management', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Database Cleanup', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Options for managing database tables and data:', 'ecab-taxi-booking-manager'); ?></p>
                        <ul>
                            <li><?php _e('Clean up expired temporary data', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Optimize database tables', 'ecab-taxi-booking-manager'); ?></li>
                        </ul>
                    </li>
                    <li>
                        <strong><?php _e('Export/Import', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Tools for exporting and importing settings and data', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                    <li>
                        <strong><?php _e('Data Removal', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Options for plugin data removal on uninstall', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                </ul>
                
                <h3><?php _e('Security Settings', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Access Control', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Configure user roles and capabilities for plugin features', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                    <li>
                        <strong><?php _e('Rate Limiting', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Prevent abuse by limiting the number of booking attempts', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                    <li>
                        <strong><?php _e('API Security', 'ecab-taxi-booking-manager'); ?></strong>
                        <p><?php _e('Configure security measures for API endpoints', 'ecab-taxi-booking-manager'); ?></p>
                    </li>
                </ul>
            </div>
            <?php
        }
        
        /**
         * Render Troubleshooting documentation section
         */
        public function render_troubleshooting_docs($hash = '') {
            ?>
            <div class="mptbm-doc-section" id="troubleshooting-overview">
                <h2><?php _e('Troubleshooting Guide', 'ecab-taxi-booking-manager'); ?></h2>
                <p><?php _e('This section provides solutions to common issues you might encounter while using E-Cab Taxi Booking Manager. If you\'re experiencing problems, follow these troubleshooting steps before contacting support.', 'ecab-taxi-booking-manager'); ?></p>
            </div>

            <div class="mptbm-doc-section" id="troubleshooting-installation">
                <h2><?php _e('Installation Issues', 'ecab-taxi-booking-manager'); ?></h2>
                
                <h3><?php _e('Plugin Won\'t Activate', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Problem:', 'ecab-taxi-booking-manager'); ?></strong> <?php _e('Plugin fails to activate or shows an error message', 'ecab-taxi-booking-manager'); ?>
                    </li>
                    <li>
                        <strong><?php _e('Solutions:', 'ecab-taxi-booking-manager'); ?></strong>
                        <ol>
                            <li><?php _e('Verify that WooCommerce is installed and activated', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Check your PHP version (7.2+ required)', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Ensure WordPress is version 5.0 or higher', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Check server error logs for specific issues', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Try deactivating all other plugins to check for conflicts', 'ecab-taxi-booking-manager'); ?></li>
                        </ol>
                    </li>
                </ul>
                
                <h3><?php _e('Missing Pages or Templates', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Problem:', 'ecab-taxi-booking-manager'); ?></strong> <?php _e('Required pages (like Transport Booking) are missing', 'ecab-taxi-booking-manager'); ?>
                    </li>
                    <li>
                        <strong><?php _e('Solutions:', 'ecab-taxi-booking-manager'); ?></strong>
                        <ol>
                            <li><?php _e('Go to <strong>E-Cab Taxi Booking > Settings > Tools</strong>', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Click "Create Missing Pages" button', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Alternatively, create the pages manually with the required shortcodes', 'ecab-taxi-booking-manager'); ?></li>
                        </ol>
                    </li>
                </ul>
            </div>
            
            <div class="mptbm-doc-section" id="troubleshooting-booking">
                <h2><?php _e('Booking Form Issues', 'ecab-taxi-booking-manager'); ?></h2>
                
                <h3><?php _e('Booking Form Not Displaying', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Problem:', 'ecab-taxi-booking-manager'); ?></strong> <?php _e('The booking form doesn\'t appear on the page', 'ecab-taxi-booking-manager'); ?>
                    </li>
                    <li>
                        <strong><?php _e('Solutions:', 'ecab-taxi-booking-manager'); ?></strong>
                        <ol>
                            <li><?php _e('Verify that the shortcode is correctly inserted: <code>[mptbm_booking]</code>', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Check for JavaScript errors in the browser console', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Try switching to a default WordPress theme to rule out theme conflicts', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Verify that plugin assets are loading (check browser network tab)', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Try disabling caching plugins temporarily', 'ecab-taxi-booking-manager'); ?></li>
                        </ol>
                    </li>
                </ul>
                
                <h3><?php _e('Location Fields Not Working', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Problem:', 'ecab-taxi-booking-manager'); ?></strong> <?php _e('Location autocomplete or map display isn\'t functioning', 'ecab-taxi-booking-manager'); ?>
                    </li>
                    <li>
                        <strong><?php _e('Solutions:', 'ecab-taxi-booking-manager'); ?></strong>
                        <ol>
                            <li><?php _e('Verify your Google Maps API key is correctly entered', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Ensure the necessary Google APIs are enabled (Places, Maps JavaScript, Distance Matrix)', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Check if the API key has proper HTTP referrer restrictions', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Verify billing is enabled on your Google Cloud account', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Check the browser console for specific Google Maps API errors', 'ecab-taxi-booking-manager'); ?></li>
                        </ol>
                    </li>
                </ul>
                
                <h3><?php _e('Price Calculation Issues', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Problem:', 'ecab-taxi-booking-manager'); ?></strong> <?php _e('Incorrect prices or price calculation errors', 'ecab-taxi-booking-manager'); ?>
                    </li>
                    <li>
                        <strong><?php _e('Solutions:', 'ecab-taxi-booking-manager'); ?></strong>
                        <ol>
                            <li><?php _e('Verify pricing settings in your taxi service configuration', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Check distance calculation method is working correctly', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Ensure WooCommerce tax settings align with your pricing model', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Test with a simple pricing model to isolate complex calculation issues', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Enable debug mode to see detailed pricing calculations', 'ecab-taxi-booking-manager'); ?></li>
                        </ol>
                    </li>
                </ul>
            </div>
            
            <div class="mptbm-doc-section" id="troubleshooting-payment">
                <h2><?php _e('Payment and Checkout Issues', 'ecab-taxi-booking-manager'); ?></h2>
                
                <h3><?php _e('Payment Gateway Problems', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Problem:', 'ecab-taxi-booking-manager'); ?></strong> <?php _e('Payment gateways not showing or not working', 'ecab-taxi-booking-manager'); ?>
                    </li>
                    <li>
                        <strong><?php _e('Solutions:', 'ecab-taxi-booking-manager'); ?></strong>
                        <ol>
                            <li><?php _e('Verify WooCommerce payment gateways are correctly configured', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Check currency settings match your payment gateway requirements', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Ensure test mode settings are consistent across WooCommerce', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Verify SSL certificate is valid if using credit card payments', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Check WooCommerce status report for any payment gateway issues', 'ecab-taxi-booking-manager'); ?></li>
                        </ol>
                    </li>
                </ul>
                
                <h3><?php _e('Order Not Created After Booking', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Problem:', 'ecab-taxi-booking-manager'); ?></strong> <?php _e('Customer completes booking but no order is created', 'ecab-taxi-booking-manager'); ?>
                    </li>
                    <li>
                        <strong><?php _e('Solutions:', 'ecab-taxi-booking-manager'); ?></strong>
                        <ol>
                            <li><?php _e('Check for PHP errors in your server logs', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Verify AJAX responses in browser console during checkout', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Ensure WooCommerce hooks are not being disrupted by another plugin', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Test with a default WordPress theme to rule out theme conflicts', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Check if WooCommerce session handling is working correctly', 'ecab-taxi-booking-manager'); ?></li>
                        </ol>
                    </li>
                </ul>
                
                <h3><?php _e('Email Confirmation Not Received', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Problem:', 'ecab-taxi-booking-manager'); ?></strong> <?php _e('Customers not receiving booking confirmation emails', 'ecab-taxi-booking-manager'); ?>
                    </li>
                    <li>
                        <strong><?php _e('Solutions:', 'ecab-taxi-booking-manager'); ?></strong>
                        <ol>
                            <li><?php _e('Verify WooCommerce email settings', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Check spam/junk folders for the emails', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Install WP Mail SMTP or Fluent SMTP plugin to improve email deliverability', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Test email functionality using a tool like Check Email', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Ensure the email template is not corrupted', 'ecab-taxi-booking-manager'); ?></li>
                        </ol>
                    </li>
                </ul>
            </div>
            
            <div class="mptbm-doc-section" id="troubleshooting-api">
                <h2><?php _e('API and Integration Issues', 'ecab-taxi-booking-manager'); ?></h2>
                
                <h3><?php _e('Google Maps API Issues', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Problem:', 'ecab-taxi-booking-manager'); ?></strong> <?php _e('Google Maps not loading or showing errors', 'ecab-taxi-booking-manager'); ?>
                    </li>
                    <li>
                        <strong><?php _e('Solutions:', 'ecab-taxi-booking-manager'); ?></strong>
                        <ol>
                            <li><?php _e('Verify API key is correct and has necessary permissions', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Check Google Cloud Console for any API restrictions or quota issues', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Ensure billing is enabled on your Google Cloud account', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Look for specific error messages in the browser console', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Test the API key with a simple map implementation', 'ecab-taxi-booking-manager'); ?></li>
                        </ol>
                    </li>
                </ul>
                
                <h3><?php _e('AI Chatbot Connection Issues', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Problem:', 'ecab-taxi-booking-manager'); ?></strong> <?php _e('AI Chatbot not responding or showing errors', 'ecab-taxi-booking-manager'); ?>
                    </li>
                    <li>
                        <strong><?php _e('Solutions:', 'ecab-taxi-booking-manager'); ?></strong>
                        <ol>
                            <li><?php _e('Verify API key for the selected provider (OpenAI, Claude, Gemini)', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Check your account balance and limits with the AI provider', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Ensure your server can make outbound HTTPS requests', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Look for specific error responses in the browser console', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Test the API key using the "Test API Key" button in settings', 'ecab-taxi-booking-manager'); ?></li>
                        </ol>
                    </li>
                </ul>
                
                <h3><?php _e('WooCommerce Integration Issues', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Problem:', 'ecab-taxi-booking-manager'); ?></strong> <?php _e('WooCommerce integration not working correctly', 'ecab-taxi-booking-manager'); ?>
                    </li>
                    <li>
                        <strong><?php _e('Solutions:', 'ecab-taxi-booking-manager'); ?></strong>
                        <ol>
                            <li><?php _e('Verify WooCommerce is up to date', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Check if any WooCommerce extensions are causing conflicts', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Test with a default WooCommerce setup to isolate the issue', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Ensure product types are registered correctly', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Check WooCommerce system status for any warnings', 'ecab-taxi-booking-manager'); ?></li>
                        </ol>
                    </li>
                </ul>
            </div>
            
            <div class="mptbm-doc-section" id="troubleshooting-performance">
                <h2><?php _e('Performance Issues', 'ecab-taxi-booking-manager'); ?></h2>
                
                <h3><?php _e('Slow Loading Booking Form', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Problem:', 'ecab-taxi-booking-manager'); ?></strong> <?php _e('Booking form takes a long time to load', 'ecab-taxi-booking-manager'); ?>
                    </li>
                    <li>
                        <strong><?php _e('Solutions:', 'ecab-taxi-booking-manager'); ?></strong>
                        <ol>
                            <li><?php _e('Enable caching for location data in the plugin settings', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Minimize the number of external API calls', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Optimize images and assets used in the booking form', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Use a page caching plugin (excluding dynamic form elements)', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Consider upgrading server resources if necessary', 'ecab-taxi-booking-manager'); ?></li>
                        </ol>
                    </li>
                </ul>
                
                <h3><?php _e('High Resource Usage', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Problem:', 'ecab-taxi-booking-manager'); ?></strong> <?php _e('Plugin using excessive server resources', 'ecab-taxi-booking-manager'); ?>
                    </li>
                    <li>
                        <strong><?php _e('Solutions:', 'ecab-taxi-booking-manager'); ?></strong>
                        <ol>
                            <li><?php _e('Optimize database tables using the maintenance tools', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Clean up old temporary data', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Reduce the frequency of API calls for location data', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Enable asset minification and combination', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Limit the number of pricing options active simultaneously', 'ecab-taxi-booking-manager'); ?></li>
                        </ol>
                    </li>
                </ul>
            </div>
            
            <div class="mptbm-doc-section" id="troubleshooting-security">
                <h2><?php _e('Security and Permissions', 'ecab-taxi-booking-manager'); ?></h2>
                
                <h3><?php _e('Access Denied to Admin Features', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Problem:', 'ecab-taxi-booking-manager'); ?></strong> <?php _e('Unable to access plugin settings or features', 'ecab-taxi-booking-manager'); ?>
                    </li>
                    <li>
                        <strong><?php _e('Solutions:', 'ecab-taxi-booking-manager'); ?></strong>
                        <ol>
                            <li><?php _e('Verify user role has appropriate capabilities', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Check if a role manager plugin is restricting access', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Ensure WordPress file permissions are correct', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Temporarily disable security plugins to check for conflicts', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Check for any capability-related PHP errors in logs', 'ecab-taxi-booking-manager'); ?></li>
                        </ol>
                    </li>
                </ul>
                
                <h3><?php _e('File Upload Security Issues', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Problem:', 'ecab-taxi-booking-manager'); ?></strong> <?php _e('Problems with file uploads or security warnings', 'ecab-taxi-booking-manager'); ?>
                    </li>
                    <li>
                        <strong><?php _e('Solutions:', 'ecab-taxi-booking-manager'); ?></strong>
                        <ol>
                            <li><?php _e('Verify file upload permissions in your server configuration', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Check if security plugins are blocking legitimate uploads', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Ensure file upload directories are properly secured', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Validate that file type restrictions are appropriately set', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Check server logs for specific file permission errors', 'ecab-taxi-booking-manager'); ?></li>
                        </ol>
                    </li>
                </ul>
            </div>
            
            <div class="mptbm-doc-section" id="troubleshooting-updates">
                <h2><?php _e('Update and Compatibility Issues', 'ecab-taxi-booking-manager'); ?></h2>
                
                <h3><?php _e('Problems After Plugin Update', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Problem:', 'ecab-taxi-booking-manager'); ?></strong> <?php _e('Issues appearing after updating the plugin', 'ecab-taxi-booking-manager'); ?>
                    </li>
                    <li>
                        <strong><?php _e('Solutions:', 'ecab-taxi-booking-manager'); ?></strong>
                        <ol>
                            <li><?php _e('Clear all caches (plugin, browser, server)', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Run database update if available in settings', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Check for compatibility issues with WordPress or WooCommerce versions', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Verify if custom code or modifications need updating', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Consider temporarily reverting to the previous version while troubleshooting', 'ecab-taxi-booking-manager'); ?></li>
                        </ol>
                    </li>
                </ul>
                
                <h3><?php _e('Theme Compatibility Issues', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li>
                        <strong><?php _e('Problem:', 'ecab-taxi-booking-manager'); ?></strong> <?php _e('Plugin doesn\'t display correctly with your theme', 'ecab-taxi-booking-manager'); ?>
                    </li>
                    <li>
                        <strong><?php _e('Solutions:', 'ecab-taxi-booking-manager'); ?></strong>
                        <ol>
                            <li><?php _e('Check if the theme is overriding plugin templates', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Test with a default WordPress theme to isolate the issue', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Adjust CSS custom settings in the plugin to match theme styling', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Check for JavaScript conflicts between theme and plugin', 'ecab-taxi-booking-manager'); ?></li>
                            <li><?php _e('Contact theme developer for compatibility assistance', 'ecab-taxi-booking-manager'); ?></li>
                        </ol>
                    </li>
                </ul>
            </div>
            
            <div class="mptbm-doc-section" id="troubleshooting-contacting-support">
                <h2><?php _e('Contacting Support', 'ecab-taxi-booking-manager'); ?></h2>
                
                <p><?php _e('If you\'ve tried the troubleshooting steps and still have issues, contact our support team:', 'ecab-taxi-booking-manager'); ?></p>
                
                <h3><?php _e('Before Contacting Support', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('Prepare the following information to help us assist you faster:', 'ecab-taxi-booking-manager'); ?></p>
                <ul>
                    <li><?php _e('Plugin version', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('WordPress version', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('WooCommerce version', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Theme name and version', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('List of active plugins', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Error messages (if any)', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Screenshots of the issue', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Steps to reproduce the problem', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <h3><?php _e('Generate a System Report', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('For comprehensive system information:', 'ecab-taxi-booking-manager'); ?></p>
                <ol>
                    <li><?php _e('Go to <strong>WooCommerce > Status > System Status</strong>', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Click "Get System Report"', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Copy the report and include it with your support request', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
                
                <h3><?php _e('Support Channels', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li><?php _e('<strong>Email Support:</strong> support@mage-people.com', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Support Ticket:</strong> <a href="https://mage-people.com/submit-ticket/" target="_blank">https://mage-people.com/submit-ticket/</a>', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>WordPress.org Forums:</strong> <a href="https://wordpress.org/support/plugin/ecab-taxi-booking-manager/" target="_blank">Plugin Support Forum</a>', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <div class="mptbm-doc-note">
                    <p><?php _e('<strong>Support Hours:</strong> Monday to Friday, 9 AM to 5 PM GMT. Response time is typically 24-48 business hours.', 'ecab-taxi-booking-manager'); ?></p>
                </div>
            </div>
            <?php
        }

        /**
         * Render API documentation section
         */
        public function render_api_docs($hash = '') {
            ?>
            <div class="mptbm-doc-section" id="api-overview">
                <h2><?php _e('API Reference', 'ecab-taxi-booking-manager'); ?></h2>
                <p><?php _e('E-Cab Taxi Booking Manager provides a comprehensive REST API for integrating with external systems, mobile apps, and other services. This documentation covers the available endpoints, parameters, and response formats.', 'ecab-taxi-booking-manager'); ?></p>
                
                <h3><?php _e('Authentication', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('The API uses WordPress REST API authentication methods. You can authenticate using:', 'ecab-taxi-booking-manager'); ?></p>
                <ul>
                    <li><?php _e('<strong>API Keys</strong> - For server-to-server communication', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>OAuth</strong> - For user-based authentication', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Application Passwords</strong> - For WordPress users', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <h4><?php _e('API Key Authentication', 'ecab-taxi-booking-manager'); ?></h4>
                <p><?php _e('To use API key authentication:', 'ecab-taxi-booking-manager'); ?></p>
                <ol>
                    <li><?php _e('Go to <strong>E-Cab Taxi Booking > Settings > API</strong>', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Generate a new API key', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Include the API key in your requests as a header:', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
                <pre>X-ECAB-API-Key: your_api_key_here</pre>
            </div>

            <div class="mptbm-doc-section" id="api-endpoints">
                <h2><?php _e('API Endpoints', 'ecab-taxi-booking-manager'); ?></h2>
                
                <div class="api-endpoint">
                    <h4><span class="api-method get">GET</span> <?php _e('List All Services', 'ecab-taxi-booking-manager'); ?></h4>
                    <div class="api-url">/wp-json/ecab/v1/services</div>
                    <p><?php _e('Returns a list of all available taxi services.', 'ecab-taxi-booking-manager'); ?></p>
                    
                    <div class="api-params">
                        <strong><?php _e('Parameters:', 'ecab-taxi-booking-manager'); ?></strong>
                        <table class="mptbm-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Parameter', 'ecab-taxi-booking-manager'); ?></th>
                                    <th><?php _e('Type', 'ecab-taxi-booking-manager'); ?></th>
                                    <th><?php _e('Description', 'ecab-taxi-booking-manager'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>per_page</td>
                                    <td>integer</td>
                                    <td><?php _e('Number of services to return per page. Default: 10', 'ecab-taxi-booking-manager'); ?></td>
                                </tr>
                                <tr>
                                    <td>page</td>
                                    <td>integer</td>
                                    <td><?php _e('Current page of results. Default: 1', 'ecab-taxi-booking-manager'); ?></td>
                                </tr>
                                <tr>
                                    <td>price_based</td>
                                    <td>string</td>
                                    <td><?php _e('Filter by pricing model (distance, hourly, fixed, manual)', 'ecab-taxi-booking-manager'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="api-response">
                        <strong><?php _e('Sample Response:', 'ecab-taxi-booking-manager'); ?></strong>
    <pre>{
  "services": [
    {
      "id": 123,
      "title": "Airport Transfer",
      "price_based": "distance",
      "base_price": 25,
      "price_per_km": 2.5,
      "max_passenger": 4,
      "features": ["AC", "WiFi", "Child Seat"]
    },
    {
      "id": 124,
      "title": "City Tour",
      "price_based": "hourly",
      "base_price": 45,
      "price_per_hour": 15,
      "max_passenger": 6,
      "features": ["AC", "WiFi", "Bottled Water"]
    }
  ],
  "total": 2,
  "pages": 1
}</pre>
                    </div>
                </div>
                
                <div class="api-endpoint">
                    <h4><span class="api-method get">GET</span> <?php _e('Get Service Details', 'ecab-taxi-booking-manager'); ?></h4>
                    <div class="api-url">/wp-json/ecab/v1/services/{id}</div>
                    <p><?php _e('Returns detailed information about a specific taxi service.', 'ecab-taxi-booking-manager'); ?></p>
                    
                    <div class="api-params">
                        <strong><?php _e('Parameters:', 'ecab-taxi-booking-manager'); ?></strong>
                        <table class="mptbm-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Parameter', 'ecab-taxi-booking-manager'); ?></th>
                                    <th><?php _e('Type', 'ecab-taxi-booking-manager'); ?></th>
                                    <th><?php _e('Description', 'ecab-taxi-booking-manager'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>id</td>
                                    <td>integer</td>
                                    <td><?php _e('The ID of the service to retrieve', 'ecab-taxi-booking-manager'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="api-response">
                        <strong><?php _e('Sample Response:', 'ecab-taxi-booking-manager'); ?></strong>
    <pre>{
  "id": 123,
  "title": "Airport Transfer",
  "description": "Comfortable airport transfer service with professional drivers",
  "price_based": "distance",
  "base_price": 25,
  "price_per_km": 2.5,
  "min_distance": 5,
  "max_passenger": 4,
  "max_luggage": 3,
  "features": ["AC", "WiFi", "Child Seat"],
  "images": [
    {
      "id": 101,
      "src": "https://example.com/wp-content/uploads/2023/01/taxi1.jpg",
      "alt": "Airport Transfer Vehicle"
    }
  ],
  "availability": {
    "days": ["monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"],
    "hours": {
      "start": "06:00",
      "end": "23:00"
    }
  }
}</pre>
                    </div>
                </div>
                
                <div class="api-endpoint">
                    <h4><span class="api-method post">POST</span> <?php _e('Calculate Price', 'ecab-taxi-booking-manager'); ?></h4>
                    <div class="api-url">/wp-json/ecab/v1/calculate-price</div>
                    <p><?php _e('Calculates the price for a journey based on service ID, locations, and other parameters.', 'ecab-taxi-booking-manager'); ?></p>
                    
                    <div class="api-params">
                        <strong><?php _e('Parameters:', 'ecab-taxi-booking-manager'); ?></strong>
                        <table class="mptbm-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Parameter', 'ecab-taxi-booking-manager'); ?></th>
                                    <th><?php _e('Type', 'ecab-taxi-booking-manager'); ?></th>
                                    <th><?php _e('Description', 'ecab-taxi-booking-manager'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>service_id</td>
                                    <td>integer</td>
                                    <td><?php _e('ID of the taxi service', 'ecab-taxi-booking-manager'); ?></td>
                                </tr>
                                <tr>
                                    <td>pickup_location</td>
                                    <td>string</td>
                                    <td><?php _e('Pickup address or coordinates', 'ecab-taxi-booking-manager'); ?></td>
                                </tr>
                                <tr>
                                    <td>dropoff_location</td>
                                    <td>string</td>
                                    <td><?php _e('Dropoff address or coordinates', 'ecab-taxi-booking-manager'); ?></td>
                                </tr>
                                <tr>
                                    <td>journey_date</td>
                                    <td>string</td>
                                    <td><?php _e('Date of the journey (YYYY-MM-DD format)', 'ecab-taxi-booking-manager'); ?></td>
                                </tr>
                                <tr>
                                    <td>journey_time</td>
                                    <td>string</td>
                                    <td><?php _e('Time of the journey (HH:MM format)', 'ecab-taxi-booking-manager'); ?></td>
                                </tr>
                                <tr>
                                    <td>passengers</td>
                                    <td>integer</td>
                                    <td><?php _e('Number of passengers', 'ecab-taxi-booking-manager'); ?></td>
                                </tr>
                                <tr>
                                    <td>extras</td>
                                    <td>array</td>
                                    <td><?php _e('Array of extra service IDs to include', 'ecab-taxi-booking-manager'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="api-response">
                        <strong><?php _e('Sample Response:', 'ecab-taxi-booking-manager'); ?></strong>
    <pre>{
  "base_price": 25,
  "distance": 15.7,
  "distance_price": 39.25,
  "extras_price": 10,
  "extras": [
    {
      "id": 1,
      "name": "Child Seat",
      "price": 5
    },
    {
      "id": 3,
      "name": "Meet & Greet",
      "price": 5
    }
  ],
  "subtotal": 74.25,
  "tax": 7.43,
  "total": 81.68,
  "currency": "USD",
  "currency_symbol": "$"
}</pre>
                    </div>
                </div>
                
                <div class="api-endpoint">
                    <h4><span class="api-method post">POST</span> <?php _e('Create Booking', 'ecab-taxi-booking-manager'); ?></h4>
                    <div class="api-url">/wp-json/ecab/v1/bookings</div>
                    <p><?php _e('Creates a new booking and returns the booking details.', 'ecab-taxi-booking-manager'); ?></p>
                    
                    <div class="api-params">
                        <strong><?php _e('Parameters:', 'ecab-taxi-booking-manager'); ?></strong>
                        <table class="mptbm-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Parameter', 'ecab-taxi-booking-manager'); ?></th>
                                    <th><?php _e('Type', 'ecab-taxi-booking-manager'); ?></th>
                                    <th><?php _e('Description', 'ecab-taxi-booking-manager'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>service_id</td>
                                    <td>integer</td>
                                    <td><?php _e('ID of the taxi service', 'ecab-taxi-booking-manager'); ?></td>
                                </tr>
                                <tr>
                                    <td>pickup_location</td>
                                    <td>string</td>
                                    <td><?php _e('Pickup address or coordinates', 'ecab-taxi-booking-manager'); ?></td>
                                </tr>
                                <tr>
                                    <td>dropoff_location</td>
                                    <td>string</td>
                                    <td><?php _e('Dropoff address or coordinates', 'ecab-taxi-booking-manager'); ?></td>
                                </tr>
                                <tr>
                                    <td>journey_date</td>
                                    <td>string</td>
                                    <td><?php _e('Date of the journey (YYYY-MM-DD format)', 'ecab-taxi-booking-manager'); ?></td>
                                </tr>
                                <tr>
                                    <td>journey_time</td>
                                    <td>string</td>
                                    <td><?php _e('Time of the journey (HH:MM format)', 'ecab-taxi-booking-manager'); ?></td>
                                </tr>
                                <tr>
                                    <td>passengers</td>
                                    <td>integer</td>
                                    <td><?php _e('Number of passengers', 'ecab-taxi-booking-manager'); ?></td>
                                </tr>
                                <tr>
                                    <td>extras</td>
                                    <td>array</td>
                                    <td><?php _e('Array of extra service IDs to include', 'ecab-taxi-booking-manager'); ?></td>
                                </tr>
                                <tr>
                                    <td>customer_info</td>
                                    <td>object</td>
                                    <td><?php _e('Customer details (name, email, phone, etc.)', 'ecab-taxi-booking-manager'); ?></td>
                                </tr>
                                <tr>
                                    <td>payment_method</td>
                                    <td>string</td>
                                    <td><?php _e('Payment method ID', 'ecab-taxi-booking-manager'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="api-response">
                        <strong><?php _e('Sample Response:', 'ecab-taxi-booking-manager'); ?></strong>
    <pre>{
  "booking_id": 1001,
  "order_id": 2001,
  "status": "processing",
  "service": {
    "id": 123,
    "title": "Airport Transfer"
  },
  "journey": {
    "pickup_location": "123 Main St, City",
    "dropoff_location": "Airport Terminal 1",
    "journey_date": "2023-06-15",
    "journey_time": "14:30",
    "passengers": 2
  },
  "pricing": {
    "subtotal": 74.25,
    "tax": 7.43,
    "total": 81.68,
    "currency": "USD",
    "currency_symbol": "$"
  },
  "customer": {
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+1234567890"
  },
  "payment": {
    "method": "stripe",
    "status": "completed"
  }
}</pre>
                    </div>
                </div>
                
                <div class="api-endpoint">
                    <h4><span class="api-method get">GET</span> <?php _e('Get Booking Details', 'ecab-taxi-booking-manager'); ?></h4>
                    <div class="api-url">/wp-json/ecab/v1/bookings/{id}</div>
                    <p><?php _e('Returns detailed information about a specific booking.', 'ecab-taxi-booking-manager'); ?></p>
                    
                    <div class="api-params">
                        <strong><?php _e('Parameters:', 'ecab-taxi-booking-manager'); ?></strong>
                        <table class="mptbm-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Parameter', 'ecab-taxi-booking-manager'); ?></th>
                                    <th><?php _e('Type', 'ecab-taxi-booking-manager'); ?></th>
                                    <th><?php _e('Description', 'ecab-taxi-booking-manager'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>id</td>
                                    <td>integer</td>
                                    <td><?php _e('The ID of the booking to retrieve', 'ecab-taxi-booking-manager'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="api-response">
                        <strong><?php _e('Sample Response:', 'ecab-taxi-booking-manager'); ?></strong>
    <pre>{
  "booking_id": 1001,
  "order_id": 2001,
  "status": "processing",
  "service": {
    "id": 123,
    "title": "Airport Transfer"
  },
  "journey": {
    "pickup_location": "123 Main St, City",
    "dropoff_location": "Airport Terminal 1",
    "journey_date": "2023-06-15",
    "journey_time": "14:30",
    "passengers": 2
  },
  "pricing": {
    "subtotal": 74.25,
    "tax": 7.43,
    "total": 81.68,
    "currency": "USD",
    "currency_symbol": "$"
  },
  "customer": {
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+1234567890"
  },
  "payment": {
    "method": "stripe",
    "status": "completed"
  }
}</pre>
                    </div>
                </div>
            </div>

            <div class="mptbm-doc-section" id="api-webhooks">
                <h2><?php _e('Webhooks', 'ecab-taxi-booking-manager'); ?></h2>
                <p><?php _e('E-Cab provides webhooks to notify external systems of events that occur within the booking system.', 'ecab-taxi-booking-manager'); ?></p>
                
                <h3><?php _e('Available Webhook Events', 'ecab-taxi-booking-manager'); ?></h3>
                <table class="mptbm-table">
                    <thead>
                        <tr>
                            <th><?php _e('Event', 'ecab-taxi-booking-manager'); ?></th>
                            <th><?php _e('Description', 'ecab-taxi-booking-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>booking.created</td>
                            <td><?php _e('Triggered when a new booking is created', 'ecab-taxi-booking-manager'); ?></td>
                        </tr>
                        <tr>
                            <td>booking.updated</td>
                            <td><?php _e('Triggered when a booking is updated', 'ecab-taxi-booking-manager'); ?></td>
                        </tr>
                        <tr>
                            <td>booking.completed</td>
                            <td><?php _e('Triggered when a booking is marked as completed', 'ecab-taxi-booking-manager'); ?></td>
                        </tr>
                        <tr>
                            <td>booking.cancelled</td>
                            <td><?php _e('Triggered when a booking is cancelled', 'ecab-taxi-booking-manager'); ?></td>
                        </tr>
                        <tr>
                            <td>payment.received</td>
                            <td><?php _e('Triggered when payment is received for a booking', 'ecab-taxi-booking-manager'); ?></td>
                        </tr>
                    </tbody>
                </table>
                
                <h3><?php _e('Setting Up Webhooks', 'ecab-taxi-booking-manager'); ?></h3>
                <ol>
                    <li><?php _e('Go to <strong>E-Cab Taxi Booking > Settings > API > Webhooks</strong>', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Click "Add Webhook"', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Enter the URL where webhook events should be sent', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Select the events you want to receive', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Set an optional secret key for signature verification', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Click "Save Webhook"', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
                
                <h3><?php _e('Webhook Payload', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('Webhook payloads include:', 'ecab-taxi-booking-manager'); ?></p>
                <ul>
                    <li><?php _e('Event type', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Timestamp', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Event data (booking details, payment information, etc.)', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Signature (if a secret key is configured)', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <div class="api-response">
                    <strong><?php _e('Sample Webhook Payload:', 'ecab-taxi-booking-manager'); ?></strong>
    <pre>{
  "event": "booking.created",
  "timestamp": "2023-06-15T14:35:12Z",
  "data": {
    "booking_id": 1001,
    "order_id": 2001,
    "status": "processing",
    "service": {
      "id": 123,
      "title": "Airport Transfer"
    },
    "journey": {
      "pickup_location": "123 Main St, City",
      "dropoff_location": "Airport Terminal 1",
      "journey_date": "2023-06-15",
      "journey_time": "14:30",
      "passengers": 2
    }
  },
  "signature": "sha256=5d3997f0de1e2a5b0d5238a75a4c446e6d893c1a2a5af7bbc05a66e7e4b8d30e"
}</pre>
                </div>
                
                <h3><?php _e('Verifying Webhook Signatures', 'ecab-taxi-booking-manager'); ?></h3>
                <p><?php _e('To verify webhook signatures:', 'ecab-taxi-booking-manager'); ?></p>
                <ol>
                    <li><?php _e('Extract the signature from the <code>X-ECAB-Signature</code> header', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Compute an HMAC with the SHA256 hash function', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Use your webhook secret as the key, and the raw request body as the message', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('Compare the computed signature to the received signature', 'ecab-taxi-booking-manager'); ?></li>
                </ol>
                
                <div class="mptbm-doc-note">
                    <p><?php _e('<strong>Important:</strong> Always verify webhook signatures to ensure that webhook requests are coming from E-Cab and not from a malicious source.', 'ecab-taxi-booking-manager'); ?></p>
                </div>
            </div>

            <div class="mptbm-doc-section" id="api-security">
                <h2><?php _e('API Security Best Practices', 'ecab-taxi-booking-manager'); ?></h2>
                
                <h3><?php _e('Authentication', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li><?php _e('<strong>Use HTTPS</strong> - Always use secure connections for API requests', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Protect API Keys</strong> - Never expose API keys in client-side code', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Rotate Keys</strong> - Periodically rotate API keys to limit exposure', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Restrict Access</strong> - Use IP whitelisting where possible', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <h3><?php _e('Request Validation', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li><?php _e('<strong>Validate All Inputs</strong> - Always validate parameters before processing', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Set Rate Limits</strong> - Protect against abuse by limiting request frequency', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Use Timeouts</strong> - Set reasonable connection and request timeouts', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <h3><?php _e('Error Handling', 'ecab-taxi-booking-manager'); ?></h3>
                <ul>
                    <li><?php _e('<strong>Be Specific in Development</strong> - Use detailed error messages during development', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Be General in Production</strong> - Avoid leaking implementation details in production', 'ecab-taxi-booking-manager'); ?></li>
                    <li><?php _e('<strong>Log Errors</strong> - Maintain detailed logs for troubleshooting', 'ecab-taxi-booking-manager'); ?></li>
                </ul>
                
                <div class="mptbm-doc-note warning">
                    <p><?php _e('<strong>Important:</strong> Never store or transmit sensitive information like credit card details via the API. Use appropriate payment gateways for handling sensitive payment information.', 'ecab-taxi-booking-manager'); ?></p>
                </div>
            </div>
            <?php
        }
    }
    
    // Initialize the documentation class
    new MPTBM_Documentation();
} 