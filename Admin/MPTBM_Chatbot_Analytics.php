<?php
if (!defined('ABSPATH')) {
    die;
} // Cannot access directly.

/**
 * AI Chatbot Analytics Dashboard
 *
 * Provides an analytics dashboard for the AI chatbot
 */
class MPTBM_Chatbot_Analytics {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_analytics_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_mptbm_reset_analytics', array($this, 'reset_analytics'));
        add_action('wp_ajax_mptbm_generate_sample_data', array($this, 'ajax_generate_sample_data'));
    }
    
    /**
     * Add analytics page to admin menu
     */
    public function add_analytics_page() {
        add_submenu_page(
            'edit.php?post_type=mptbm_rent',
            __('AI Chatbot Analytics', 'ecab-taxi-booking-manager'),
            __('Chatbot Analytics', 'ecab-taxi-booking-manager'),
            'manage_options',
            'mptbm_ai_analytics',
            array($this, 'render_analytics_page')
        );
    }
    
    /**
     * Enqueue assets for the analytics page
     */
    public function enqueue_assets($hook) {
        if ('mptbm_rent_page_mptbm_ai_analytics' !== $hook) {
            return;
        }
        
        // Enqueue Chart.js
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js', array(), '3.7.1', true);
        
        // Enqueue our custom script
        wp_enqueue_script(
            'mptbm-chatbot-analytics',
            plugins_url('assets/admin/js/mptbm-chatbot-analytics.js', dirname(__FILE__)),
            array('jquery', 'chartjs'),
            MPTBM_PLUGIN_VERSION,
            true
        );
        
        // Pass data to script
        wp_localize_script('mptbm-chatbot-analytics', 'mptbm_analytics_data', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mptbm_chatbot_nonce'),
            'date_format' => get_option('date_format'),
            'labels' => array(
                'conversations' => __('Conversations', 'ecab-taxi-booking-manager'),
                'messages' => __('Messages', 'ecab-taxi-booking-manager'),
                'intents' => __('Intent Distribution', 'ecab-taxi-booking-manager'),
                'sentiments' => __('Sentiment Analysis', 'ecab-taxi-booking-manager'),
                'activity' => __('Daily Activity', 'ecab-taxi-booking-manager'),
                'users' => __('Unique Users', 'ecab-taxi-booking-manager'),
                'loading' => __('Loading analytics data...', 'ecab-taxi-booking-manager'),
                'error' => __('Error loading data. Please try again.', 'ecab-taxi-booking-manager'),
                'no_data' => __('No data available for the selected period.', 'ecab-taxi-booking-manager'),
            )
        ));
        
        // Enqueue custom CSS
        wp_enqueue_style(
            'mptbm-chatbot-analytics',
            plugins_url('assets/admin/css/mptbm-chatbot-analytics.css', dirname(__FILE__)),
            array(),
            MPTBM_PLUGIN_VERSION
        );
    }
    
    /**
     * Render the analytics page
     */
    public function render_analytics_page() {
        // Get date range settings
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-30 days'));
        
        // Check if we need to generate sample data
        $analytics_data = get_option('mptbm_ai_chatbot_analytics', array());
        $has_data = is_array($analytics_data) && !empty($analytics_data);
        
        ?>
        <div class="wrap mptbm-analytics-dashboard">
            <h1><?php echo esc_html__('E-Cab AI Chatbot Analytics', 'ecab-taxi-booking-manager'); ?></h1>
            
            <div class="mptbm-date-range">
                <form id="mptbm-analytics-form" method="post">
                    <div class="date-inputs">
                        <label>
                            <?php echo esc_html__('Start Date:', 'ecab-taxi-booking-manager'); ?>
                            <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
                        </label>
                        
                        <label>
                            <?php echo esc_html__('End Date:', 'ecab-taxi-booking-manager'); ?>
                            <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
                        </label>
                        
                        <button type="submit" class="button button-primary"><?php echo esc_html__('Apply', 'ecab-taxi-booking-manager'); ?></button>
                    </div>
                    
                    <div class="quick-ranges">
                        <button type="button" class="button quick-range" data-days="7"><?php echo esc_html__('Last 7 Days', 'ecab-taxi-booking-manager'); ?></button>
                        <button type="button" class="button quick-range" data-days="30"><?php echo esc_html__('Last 30 Days', 'ecab-taxi-booking-manager'); ?></button>
                        <button type="button" class="button quick-range" data-days="90"><?php echo esc_html__('Last 90 Days', 'ecab-taxi-booking-manager'); ?></button>
                    </div>
                </form>
            </div>
            
            <?php if (!$has_data): ?>
                <div class="notice notice-warning">
                    <p>
                        <?php echo esc_html__('No chatbot analytics data found. Would you like to generate sample data for demonstration purposes?', 'ecab-taxi-booking-manager'); ?>
                        <button type="button" id="generate-sample-data" class="button button-primary"><?php echo esc_html__('Generate Sample Data', 'ecab-taxi-booking-manager'); ?></button>
                    </p>
                </div>
                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        $('#generate-sample-data').on('click', function() {
                            $(this).prop('disabled', true).text('<?php echo esc_js(__('Generating...', 'ecab-taxi-booking-manager')); ?>');
                            
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'mptbm_generate_sample_data',
                                    nonce: mptbm_analytics_data.nonce
                                },
                                success: function(response) {
                                    if (response && response.success) {
                                        location.reload();
                                    } else {
                                        alert('<?php echo esc_js(__('Error generating sample data', 'ecab-taxi-booking-manager')); ?>');
                                        $('#generate-sample-data').prop('disabled', false).text('<?php echo esc_js(__('Generate Sample Data', 'ecab-taxi-booking-manager')); ?>');
                                    }
                                },
                                error: function() {
                                    alert('<?php echo esc_js(__('Error connecting to server', 'ecab-taxi-booking-manager')); ?>');
                                    $('#generate-sample-data').prop('disabled', false).text('<?php echo esc_js(__('Generate Sample Data', 'ecab-taxi-booking-manager')); ?>');
                                }
                            });
                        });
                    });
                </script>
            <?php else: ?>
                <div class="mptbm-admin-actions">
                    <button type="button" id="reset-analytics" class="button button-secondary"><?php echo esc_html__('Reset Analytics Data', 'ecab-taxi-booking-manager'); ?></button>
                </div>
                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        $('#reset-analytics').on('click', function() {
                            if (confirm('<?php echo esc_js(__('Are you sure you want to reset all analytics data? This cannot be undone.', 'ecab-taxi-booking-manager')); ?>')) {
                                $(this).prop('disabled', true).text('<?php echo esc_js(__('Resetting...', 'ecab-taxi-booking-manager')); ?>');
                                
                                $.ajax({
                                    url: ajaxurl,
                                    type: 'POST',
                                    data: {
                                        action: 'mptbm_reset_analytics',
                                        nonce: mptbm_analytics_data.nonce
                                    },
                                    success: function(response) {
                                        if (response && response.success) {
                                            location.reload();
                                        } else {
                                            alert('<?php echo esc_js(__('Error resetting analytics', 'ecab-taxi-booking-manager')); ?>');
                                            $('#reset-analytics').prop('disabled', false).text('<?php echo esc_js(__('Reset Analytics Data', 'ecab-taxi-booking-manager')); ?>');
                                        }
                                    },
                                    error: function() {
                                        alert('<?php echo esc_js(__('Error connecting to server', 'ecab-taxi-booking-manager')); ?>');
                                        $('#reset-analytics').prop('disabled', false).text('<?php echo esc_js(__('Reset Analytics Data', 'ecab-taxi-booking-manager')); ?>');
                                    }
                                });
                            }
                        });
                    });
                </script>
            <?php endif; ?>
            
            <div class="mptbm-loading-overlay">
                <div class="spinner is-active"></div>
                <p><?php echo esc_html__('Loading analytics data...', 'ecab-taxi-booking-manager'); ?></p>
            </div>
            
            <div class="mptbm-analytics-content">
                <div class="mptbm-analytics-summary">
                    <div class="summary-card">
                        <div class="card-icon">
                            <span class="dashicons dashicons-format-chat"></span>
                        </div>
                        <div class="card-content">
                            <h3><?php echo esc_html__('Total Conversations', 'ecab-taxi-booking-manager'); ?></h3>
                            <div class="card-value" id="total-conversations">0</div>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="card-icon">
                            <span class="dashicons dashicons-admin-comments"></span>
                        </div>
                        <div class="card-content">
                            <h3><?php echo esc_html__('Total Messages', 'ecab-taxi-booking-manager'); ?></h3>
                            <div class="card-value" id="total-messages">0</div>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="card-icon">
                            <span class="dashicons dashicons-groups"></span>
                        </div>
                        <div class="card-content">
                            <h3><?php echo esc_html__('Unique Users', 'ecab-taxi-booking-manager'); ?></h3>
                            <div class="card-value" id="unique-users">0</div>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="card-icon">
                            <span class="dashicons dashicons-tickets-alt"></span>
                        </div>
                        <div class="card-content">
                            <h3><?php echo esc_html__('Booking Rate', 'ecab-taxi-booking-manager'); ?></h3>
                            <div class="card-value" id="booking-rate">0%</div>
                        </div>
                    </div>
                </div>
                
                <div class="mptbm-analytics-grid">
                    <div class="analytics-card full-width">
                        <h3><?php echo esc_html__('Conversations Over Time', 'ecab-taxi-booking-manager'); ?></h3>
                        <div class="chart-container">
                            <canvas id="time-series-chart"></canvas>
                        </div>
                    </div>
                    
                    <div class="analytics-card">
                        <h3><?php echo esc_html__('Intent Distribution', 'ecab-taxi-booking-manager'); ?></h3>
                        <div class="chart-container">
                            <canvas id="intent-chart"></canvas>
                        </div>
                    </div>
                    
                    <div class="analytics-card">
                        <h3><?php echo esc_html__('Sentiment Analysis', 'ecab-taxi-booking-manager'); ?></h3>
                        <div class="chart-container">
                            <canvas id="sentiment-chart"></canvas>
                        </div>
                    </div>
                    
                    <div class="analytics-card">
                        <h3><?php echo esc_html__('Response Metrics', 'ecab-taxi-booking-manager'); ?></h3>
                        <table class="metrics-table">
                            <tr>
                                <td><?php echo esc_html__('Avg. Message Length', 'ecab-taxi-booking-manager'); ?></td>
                                <td id="avg-message-length">0</td>
                            </tr>
                            <tr>
                                <td><?php echo esc_html__('Avg. Response Length', 'ecab-taxi-booking-manager'); ?></td>
                                <td id="avg-response-length">0</td>
                            </tr>
                            <tr>
                                <td><?php echo esc_html__('Avg. Response Time', 'ecab-taxi-booking-manager'); ?></td>
                                <td id="avg-response-time">0 s</td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="analytics-card">
                        <h3><?php echo esc_html__('Common Questions', 'ecab-taxi-booking-manager'); ?></h3>
                        <div id="common-phrases">
                            <div class="loading-placeholder">
                                <div class="spinner is-active"></div>
                                <p><?php echo esc_html__('Loading common phrases...', 'ecab-taxi-booking-manager'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="analytics-card">
                        <h3><?php echo esc_html__('Training Opportunities', 'ecab-taxi-booking-manager'); ?></h3>
                        <div id="training-opportunities">
                            <p><?php echo esc_html__('Analyzing conversations to identify potential training opportunities...', 'ecab-taxi-booking-manager'); ?></p>
                            <div id="training-list"></div>
                            <div class="actions">
                                <a href="<?php echo admin_url('edit.php?post_type=mptbm_rent&page=mptbm_ai_chatbot'); ?>" class="button button-primary">
                                    <?php echo esc_html__('Manage AI Training', 'ecab-taxi-booking-manager'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Generate sample chatbot analytics data
     */
    public function generate_sample_data($days = 30) {
        // Define sample data
        $intents = array('booking', 'pricing', 'availability', 'services', 'locations', 'payment', 'cancellation', 'support');
        $sentiments = array('positive', 'neutral', 'negative');
        $sample_questions = array(
            'How much does it cost to book a taxi?',
            'Can I book a taxi for tomorrow morning?',
            'What payment methods do you accept?',
            'Do you have any luxury vehicles available?',
            'How do I cancel my booking?',
            'Is there an extra charge for airport pickups?',
            'Do you offer child seats?',
            'Can I book a taxi for a week-long trip?',
            'What areas do you serve?',
            'How early should I book a taxi?',
            'Do you have wheelchair accessible taxis?',
            'Is there a cancellation fee?',
            'Can I get a receipt for my booking?',
            'Do you offer corporate accounts?',
            'How many passengers can fit in your largest vehicle?'
        );
        
        $sample_responses = array(
            'Our taxi rates start at $2.50 per km with a minimum fare of $10. Airport pickups have an additional $5 surcharge.',
            'Yes, you can book a taxi for tomorrow morning. What time would you need the pickup?',
            'We accept credit cards, debit cards, cash, and mobile payments like Apple Pay and Google Pay.',
            'Yes, we have several luxury vehicles in our fleet including Mercedes E-Class and BMW 5 Series.',
            'You can cancel your booking through our website or app. Cancellations made at least 2 hours before pickup have no fee.',
            'There is a $5 surcharge for airport pickups to cover parking and waiting time.',
            'Yes, we offer child seats upon request for an additional $5 per seat.',
            'Yes, we can arrange a taxi for week-long trips. For extended bookings, we offer discounted daily rates.',
            'We currently serve the entire metropolitan area and surrounding suburbs within a 50km radius.',
            'We recommend booking at least 2 hours in advance for regular trips and 24 hours for airport transfers.',
            'Yes, we have wheelchair accessible vehicles in our fleet. Please specify this requirement when booking.',
            'Cancellations made less than 2 hours before pickup incur a 50% fee of the estimated fare.',
            'Yes, you will automatically receive a receipt via email after your journey is completed.',
            'Yes, we offer corporate accounts with monthly billing and detailed reporting. Please contact our business team.',
            'Our largest vehicle can accommodate up to 8 passengers along with luggage.'
        );
        
        $session_ids = array();
        for ($i = 1; $i <= 15; $i++) {
            $session_ids[] = md5('sample-session-' . $i);
        }
        
        $user_ids = array(0, 1, 2, 3);
        
        // Generate sample data
        $analytics_data = array();
        $end_date = current_time('timestamp');
        $start_date = strtotime('-' . $days . ' days', $end_date);
        
        for ($timestamp = $start_date; $timestamp <= $end_date; $timestamp += rand(3600, 86400)) {
            $date = date('Y-m-d H:i:s', $timestamp);
            $session_id = $session_ids[array_rand($session_ids)];
            $user_id = $user_ids[array_rand($user_ids)];
            
            // Generate 1-3 interactions per day
            $interactions = rand(1, 3);
            for ($j = 0; $j < $interactions; $j++) {
                $question_index = array_rand($sample_questions);
                $user_message = $sample_questions[$question_index];
                $ai_response = $sample_responses[$question_index];
                
                $intent = $intents[array_rand($intents)];
                $sentiment = $sentiments[array_rand($sentiments)];
                $has_booking_ref = strpos($ai_response, 'book') !== false ? 1 : 0;
                
                $analytics_data[] = array(
                    'timestamp' => $date,
                    'session_id' => $session_id,
                    'user_id' => $user_id,
                    'user_message' => $user_message,
                    'ai_response' => $ai_response,
                    'intent' => $intent,
                    'sentiment' => $sentiment,
                    'has_booking_ref' => $has_booking_ref,
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'page_url' => site_url('/taxi-booking/'),
                    'response_tokens' => strlen($ai_response) / 4,
                    'response_time' => rand(5, 20) / 10,
                );
                
                // Add a timestamp offset for each interaction in the same session
                $timestamp += rand(60, 300);
            }
        }
        
        // Update the option
        update_option('mptbm_ai_chatbot_analytics', $analytics_data);
        
        return true;
    }
    
    /**
     * AJAX handler for generating sample data
     */
    public function ajax_generate_sample_data() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized access'));
        }
        
        // Check nonce
        check_ajax_referer('mptbm_chatbot_nonce', 'nonce');
        
        // Generate sample data
        $result = $this->generate_sample_data();
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => 'Failed to generate sample data'));
        }
        wp_die();
    }
    
    /**
     * Reset analytics data
     */
    public function reset_analytics() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized access'));
        }
        
        // Check nonce
        check_ajax_referer('mptbm_chatbot_nonce', 'nonce');
        
        // Delete analytics data
        delete_option('mptbm_ai_chatbot_analytics');
        
        wp_send_json_success();
        wp_die();
    }
}

new MPTBM_Chatbot_Analytics(); 