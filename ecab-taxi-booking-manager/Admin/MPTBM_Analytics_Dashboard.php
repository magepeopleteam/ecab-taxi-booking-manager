<?php
/*
 * @Author 		engr.sumonazma@gmail.com
 * Copyright: 	mage-people.com
 */
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_Analytics_Dashboard')) {
	
    class MPTBM_Analytics_Dashboard {
        public function __construct() {
            add_action('admin_menu', array($this, 'analytics_menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_analytics_scripts'));
            add_action('wp_ajax_mptbm_get_analytics_data', array($this, 'get_analytics_data'));
        }

        /**
         * Add Analytics Dashboard menu
         */
        public function analytics_menu() {
            $cpt = MPTBM_Function::get_cpt();
            add_submenu_page(
                'edit.php?post_type=' . $cpt,
                esc_html__('Analytics Dashboard', 'ecab-taxi-booking-manager'),
                '<span style="color:#00c853">' . esc_html__('Analytics Dashboard', 'ecab-taxi-booking-manager') . '</span>',
                'manage_options',
                'mptbm_analytics_dashboard',
                array($this, 'analytics_dashboard_page')
            );
        }

        /**
         * Enqueue necessary scripts and styles for the analytics dashboard
         */
        public function enqueue_analytics_scripts($hook) {
            // The hook name should be {post_type}_page_{menu_slug}
            if (strpos($hook, 'mptbm_analytics_dashboard') === false) {
                return;
            }

            // Enqueue Chart.js
            wp_enqueue_script('mptbm-chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', array(), '3.9.1', true);

            // Enqueue Date Range Picker dependencies
            wp_enqueue_script('mptbm-moment', 'https://cdn.jsdelivr.net/momentjs/latest/moment.min.js', array(), '2.29.4', true);
            wp_enqueue_script('mptbm-daterangepicker', 'https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.min.js', array('jquery', 'mptbm-moment'), '3.1.0', true);
            wp_enqueue_style('mptbm-daterangepicker', 'https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.css', array(), '3.1.0');

            // Enqueue custom analytics scripts and styles
            wp_enqueue_script('mptbm-analytics', MPTBM_PLUGIN_URL . '/assets/admin/js/analytics-dashboard.js', array('jquery', 'mptbm-chartjs', 'mptbm-daterangepicker'), MPTBM_PLUGIN_VERSION, true);
            wp_enqueue_style('mptbm-analytics', MPTBM_PLUGIN_URL . '/assets/admin/css/analytics-dashboard.css', array(), MPTBM_PLUGIN_VERSION);

            // Localize script with ajax url and nonce
            wp_localize_script('mptbm-analytics', 'mptbm_analytics', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mptbm_analytics_nonce'),
                'currency_symbol' => get_woocommerce_currency_symbol(),
                'labels' => array(
                    'bookings' => esc_html__('Bookings', 'ecab-taxi-booking-manager'),
                    'revenue' => esc_html__('Revenue', 'ecab-taxi-booking-manager'),
                    'popular_routes' => esc_html__('Popular Routes', 'ecab-taxi-booking-manager'),
                    'booking_status' => esc_html__('Booking Status', 'ecab-taxi-booking-manager'),
                    'loading' => esc_html__('Loading...', 'ecab-taxi-booking-manager'),
                )
            ));
        }

        /**
         * Render the analytics dashboard page
         */
        public function analytics_dashboard_page() {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('E-Cab Taxi Booking Analytics Dashboard', 'ecab-taxi-booking-manager'); ?></h1>

                <div class="mptbm-analytics-container">
                    <!-- Date Range Filter -->
                    <div class="mptbm-analytics-filters">
                        <div class="mptbm-date-range-filter">
                            <label for="mptbm-date-range"><?php esc_html_e('Date Range:', 'ecab-taxi-booking-manager'); ?></label>
                            <input type="text" id="mptbm-date-range" name="mptbm-date-range" class="mptbm-date-range-input" />
                        </div>

                        <div class="mptbm-filter-buttons">
                            <button type="button" class="button button-primary mptbm-refresh-data">
                                <span class="dashicons dashicons-update"></span> <?php esc_html_e('Refresh Data', 'ecab-taxi-booking-manager'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Summary Cards -->
                    <div class="mptbm-summary-cards">
                        <div class="mptbm-card mptbm-total-bookings">
                            <div class="mptbm-card-icon">
                                <span class="dashicons dashicons-calendar-alt"></span>
                            </div>
                            <div class="mptbm-card-content">
                                <h3><?php esc_html_e('Total Bookings', 'ecab-taxi-booking-manager'); ?></h3>
                                <div class="mptbm-card-value" id="mptbm-total-bookings">0</div>
                                <div class="mptbm-card-trend" id="mptbm-bookings-trend"></div>
                            </div>
                        </div>

                        <div class="mptbm-card mptbm-total-revenue">
                            <div class="mptbm-card-icon">
                                <span class="dashicons dashicons-money-alt"></span>
                            </div>
                            <div class="mptbm-card-content">
                                <h3><?php esc_html_e('Total Revenue', 'ecab-taxi-booking-manager'); ?></h3>
                                <div class="mptbm-card-value" id="mptbm-total-revenue">0</div>
                                <div class="mptbm-card-trend" id="mptbm-revenue-trend"></div>
                            </div>
                        </div>

                        <div class="mptbm-card mptbm-avg-booking-value">
                            <div class="mptbm-card-icon">
                                <span class="dashicons dashicons-chart-line"></span>
                            </div>
                            <div class="mptbm-card-content">
                                <h3><?php esc_html_e('Avg. Booking Value', 'ecab-taxi-booking-manager'); ?></h3>
                                <div class="mptbm-card-value" id="mptbm-avg-booking-value">0</div>
                                <div class="mptbm-card-trend" id="mptbm-avg-trend"></div>
                            </div>
                        </div>

                        <div class="mptbm-card mptbm-completion-rate">
                            <div class="mptbm-card-icon">
                                <span class="dashicons dashicons-yes-alt"></span>
                            </div>
                            <div class="mptbm-card-content">
                                <h3><?php esc_html_e('Completion Rate', 'ecab-taxi-booking-manager'); ?></h3>
                                <div class="mptbm-card-value" id="mptbm-completion-rate">0%</div>
                                <div class="mptbm-card-trend" id="mptbm-completion-trend"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Cancellation & Lost Revenue Summary -->
                    <div class="mptbm-cancellation-summary">
                        <h2><?php esc_html_e('Cancellation & Lost Revenue Summary', 'ecab-taxi-booking-manager'); ?></h2>
                        <div class="mptbm-summary-cards">
                            <div class="mptbm-card mptbm-cancelled-bookings">
                                <div class="mptbm-card-icon">
                                    <span class="dashicons dashicons-dismiss"></span>
                                </div>
                                <div class="mptbm-card-content">
                                    <h3><?php esc_html_e('Cancelled Bookings', 'ecab-taxi-booking-manager'); ?></h3>
                                    <div class="mptbm-card-value" id="mptbm-cancelled-bookings">0</div>
                                </div>
                            </div>

                            <div class="mptbm-card mptbm-failed-bookings">
                                <div class="mptbm-card-icon">
                                    <span class="dashicons dashicons-warning"></span>
                                </div>
                                <div class="mptbm-card-content">
                                    <h3><?php esc_html_e('Failed Bookings', 'ecab-taxi-booking-manager'); ?></h3>
                                    <div class="mptbm-card-value" id="mptbm-failed-bookings">0</div>
                                </div>
                            </div>

                            <div class="mptbm-card mptbm-lost-revenue">
                                <div class="mptbm-card-icon">
                                    <span class="dashicons dashicons-money-alt"></span>
                                </div>
                                <div class="mptbm-card-content">
                                    <h3><?php esc_html_e('Lost Revenue', 'ecab-taxi-booking-manager'); ?></h3>
                                    <div class="mptbm-card-value" id="mptbm-lost-revenue">0</div>
                                </div>
                            </div>

                            <div class="mptbm-card mptbm-cancellation-rate">
                                <div class="mptbm-card-icon">
                                    <span class="dashicons dashicons-chart-pie"></span>
                                </div>
                                <div class="mptbm-card-content">
                                    <h3><?php esc_html_e('Cancellation Rate', 'ecab-taxi-booking-manager'); ?></h3>
                                    <div class="mptbm-card-value" id="mptbm-cancellation-rate">0%</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row 1 -->
                    <div class="mptbm-charts-row">
                        <div class="mptbm-chart-container mptbm-chart-bookings">
                            <h2><?php esc_html_e('Bookings Over Time', 'ecab-taxi-booking-manager'); ?></h2>
                            <div class="mptbm-chart-wrapper">
                                <canvas id="mptbm-bookings-chart"></canvas>
                                <div class="mptbm-chart-loading" id="mptbm-bookings-loading">
                                    <span class="spinner is-active"></span>
                                    <p><?php esc_html_e('Loading chart data...', 'ecab-taxi-booking-manager'); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="mptbm-chart-container mptbm-chart-revenue">
                            <h2><?php esc_html_e('Revenue Over Time', 'ecab-taxi-booking-manager'); ?></h2>
                            <div class="mptbm-chart-wrapper">
                                <canvas id="mptbm-revenue-chart"></canvas>
                                <div class="mptbm-chart-loading" id="mptbm-revenue-loading">
                                    <span class="spinner is-active"></span>
                                    <p><?php esc_html_e('Loading chart data...', 'ecab-taxi-booking-manager'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row 2 -->
                    <div class="mptbm-charts-row">
                        <div class="mptbm-chart-container mptbm-chart-popular-routes">
                            <h2><?php esc_html_e('Popular Routes', 'ecab-taxi-booking-manager'); ?></h2>
                            <div class="mptbm-chart-wrapper">
                                <canvas id="mptbm-popular-routes-chart"></canvas>
                                <div class="mptbm-chart-loading" id="mptbm-popular-routes-loading">
                                    <span class="spinner is-active"></span>
                                    <p><?php esc_html_e('Loading chart data...', 'ecab-taxi-booking-manager'); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="mptbm-chart-container mptbm-chart-booking-status">
                            <h2><?php esc_html_e('Booking Status Distribution', 'ecab-taxi-booking-manager'); ?></h2>
                            <div class="mptbm-chart-wrapper">
                                <canvas id="mptbm-booking-status-chart"></canvas>
                                <div class="mptbm-chart-loading" id="mptbm-booking-status-loading">
                                    <span class="spinner is-active"></span>
                                    <p><?php esc_html_e('Loading chart data...', 'ecab-taxi-booking-manager'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Bookings Table -->
                    <div class="mptbm-recent-bookings">
                        <h2><?php esc_html_e('Recent Bookings', 'ecab-taxi-booking-manager'); ?></h2>
                        <div class="mptbm-table-container">
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                <tr>
                                    <th><?php esc_html_e('Order ID', 'ecab-taxi-booking-manager'); ?></th>
                                    <th><?php esc_html_e('Customer', 'ecab-taxi-booking-manager'); ?></th>
                                    <th><?php esc_html_e('Route', 'ecab-taxi-booking-manager'); ?></th>
                                    <th><?php esc_html_e('Date', 'ecab-taxi-booking-manager'); ?></th>
                                    <th><?php esc_html_e('Amount', 'ecab-taxi-booking-manager'); ?></th>
                                    <th><?php esc_html_e('Status', 'ecab-taxi-booking-manager'); ?></th>
                                    <th><?php esc_html_e('Actions', 'ecab-taxi-booking-manager'); ?></th>
                                </tr>
                                </thead>
                                <tbody id="mptbm-recent-bookings-table">
                                <tr>
                                    <td colspan="7" class="mptbm-loading-row">
                                        <span class="spinner is-active"></span>
                                        <p><?php esc_html_e('Loading recent bookings...', 'ecab-taxi-booking-manager'); ?></p>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

        /**
         * AJAX handler for getting analytics data
         */
        public function get_analytics_data() {
            // Check nonce for security
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mptbm_analytics_nonce')) {
                wp_send_json_error(array('message' => 'Invalid security token'));
                wp_die();
            }

            // Get date range parameters
            $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : date('Y-m-d', strtotime('-30 days'));
            $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : date('Y-m-d', strtotime('+1 day'));

            // Get analytics data
            $data = $this->generate_analytics_data($start_date, $end_date);
            
            // Send response
            wp_send_json_success($data);
            wp_die();
        }

        /**
         * Generate analytics data based on date range
         * COMPLETELY REWRITTEN to fix counting issues
         */
        private function generate_analytics_data($start_date, $end_date) {
            global $wpdb;

            // Convert dates to timestamp for comparison
            $start_timestamp = strtotime($start_date);
            $end_timestamp = strtotime($end_date . ' 23:59:59');

            // Format dates for SQL query
            $start_date_sql = date('Y-m-d H:i:s', $start_timestamp);
            $end_date_sql = date('Y-m-d H:i:s', $end_timestamp);

            // Initialize data arrays
            $bookings_data = array();
            $revenue_data = array();
            $popular_routes = array();
            $booking_status = array(
                'completed' => 0,
                'processing' => 0,
                'on-hold' => 0,
                'pending' => 0,
                'cancelled' => 0,
                'refunded' => 0,
                'failed' => 0
            );

            // COMPLETELY NEW APPROACH: Use direct SQL query to get exact count of ECAB taxi bookings
            // This is the most accurate way to get the data
            $query = $wpdb->prepare(
                "SELECT p.ID, p.post_date, pm1.meta_value as order_id, pm2.meta_value as order_status, 
                pm3.meta_value as booking_date, pm4.meta_value as start_place, pm5.meta_value as end_place, 
                pm6.meta_value as price, pm7.meta_value as customer_name, pm8.meta_value as taxi_id
                FROM {$wpdb->posts} p
                JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'mptbm_order_id'
                JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'mptbm_order_status'
                JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = 'mptbm_date'
                JOIN {$wpdb->postmeta} pm4 ON p.ID = pm4.post_id AND pm4.meta_key = 'mptbm_start_place'
                JOIN {$wpdb->postmeta} pm5 ON p.ID = pm5.post_id AND pm5.meta_key = 'mptbm_end_place'
                JOIN {$wpdb->postmeta} pm6 ON p.ID = pm6.post_id AND pm6.meta_key = 'mptbm_tp'
                JOIN {$wpdb->postmeta} pm7 ON p.ID = pm7.post_id AND pm7.meta_key = 'mptbm_billing_name'
                JOIN {$wpdb->postmeta} pm8 ON p.ID = pm8.post_id AND pm8.meta_key = 'mptbm_id'
                WHERE p.post_type = 'mptbm_booking'
                AND p.post_status = 'publish'
                AND p.post_date BETWEEN %s AND %s
                ORDER BY p.post_date DESC",
                $start_date_sql,
                $end_date_sql
            );

            $bookings = $wpdb->get_results($query);

            // Initialize counters
            $total_bookings = count($bookings);
            $total_revenue = 0;
            $completed_bookings = 0;
            $cancelled_bookings = 0;
            $failed_bookings = 0;
            $lost_revenue = 0; // Track revenue lost due to cancellations and failures
            $recent_bookings = array();

            // Process each booking from the SQL query
            foreach ($bookings as $booking) {
                // Format booking date
                $booking_date_formatted = date('Y-m-d', strtotime($booking->booking_date));

                // Add to revenue data - only count revenue for non-cancelled and non-failed orders
                if (!isset($revenue_data[$booking_date_formatted])) {
                    $revenue_data[$booking_date_formatted] = 0;
                }

                // Only add to revenue if the order is not cancelled or failed
                if (!in_array($booking->order_status, array('cancelled', 'failed', 'refunded'))) {
                    $revenue_data[$booking_date_formatted] += (float)$booking->price;
                    $total_revenue += (float)$booking->price;
                }

                // Add to bookings data
                if (!isset($bookings_data[$booking_date_formatted])) {
                    $bookings_data[$booking_date_formatted] = 0;
                }
                $bookings_data[$booking_date_formatted]++;

                // Add to booking status
                if (isset($booking_status[$booking->order_status])) {
                    $booking_status[$booking->order_status]++;
                }

                if ($booking->order_status === 'completed') {
                    $completed_bookings++;
                } elseif ($booking->order_status === 'cancelled') {
                    $cancelled_bookings++;
                    $lost_revenue += (float)$booking->price; // Add to lost revenue
                } elseif ($booking->order_status === 'failed') {
                    $failed_bookings++;
                    $lost_revenue += (float)$booking->price; // Add to lost revenue
                } elseif ($booking->order_status === 'refunded') {
                    $lost_revenue += (float)$booking->price; // Add to lost revenue
                }

                // Get route information
                if ($booking->start_place && $booking->end_place) {
                    $route = $booking->start_place . ' → ' . $booking->end_place;
                    if (!isset($popular_routes[$route])) {
                        $popular_routes[$route] = 0;
                    }
                    $popular_routes[$route]++;
                }

                // Add to recent bookings (limit to 10)
                if (count($recent_bookings) < 5) {
                    $recent_bookings[] = array(
                        'order_id' => $booking->order_id,
                        'customer' => $booking->customer_name,
                        'route' => isset($route) ? $route : '-',
                        'date' => $booking_date_formatted,
                        'amount' => wc_price($booking->price),
                        'status' => $booking->order_status,
                        'view_url' => admin_url('post.php?post=' . $booking->order_id . '&action=edit')
                    );
                }
            }

            // Sort data by date
            ksort($bookings_data);
            ksort($revenue_data);

            // Calculate completion rate
            $completion_rate = $total_bookings > 0 ? round(($completed_bookings / $total_bookings) * 100) : 0;

            // Calculate average booking value
            $avg_booking_value = $total_bookings > 0 ? $total_revenue / $total_bookings : 0;

            // Sort popular routes by count (descending) and take top 5
            arsort($popular_routes);
            $popular_routes = array_slice($popular_routes, 0, 5, true);

            // Format data for charts
            $formatted_bookings_data = array(
                'labels' => array_keys($bookings_data),
                'values' => array_values($bookings_data)
            );

            $formatted_revenue_data = array(
                'labels' => array_keys($revenue_data),
                'values' => array_values($revenue_data)
            );

            $formatted_popular_routes = array(
                'labels' => array_keys($popular_routes),
                'values' => array_values($popular_routes)
            );

            $formatted_booking_status = array(
                'labels' => array_map('ucfirst', array_keys($booking_status)),
                'values' => array_values($booking_status)
            );

            // Return all data
            $return_data = array(
                'total_bookings' => $total_bookings,
                'total_revenue' => $total_revenue,
                'avg_booking_value' => $avg_booking_value,
                'completion_rate' => $completion_rate,
                'cancelled_bookings' => $cancelled_bookings,
                'failed_bookings' => $failed_bookings,
                'lost_revenue' => $lost_revenue,
                'bookings_data' => $formatted_bookings_data,
                'revenue_data' => $formatted_revenue_data,
                'popular_routes' => $formatted_popular_routes,
                'booking_status' => $formatted_booking_status,
                'recent_bookings' => $recent_bookings
            );

            if (class_exists('MPTBM_Plugin_Pro')){
                return $return_data;
            }else{
                $return_data = array(
                    'total_bookings' => $total_bookings,
                    'total_revenue' => $total_revenue,
                    'avg_booking_value' => $avg_booking_value,
                    'completion_rate' => $completion_rate,
                    'cancelled_bookings' => $cancelled_bookings,
                    'failed_bookings' => $failed_bookings,
                    'lost_revenue' => $lost_revenue,
                    'bookings_data' => $formatted_bookings_data,
                    'revenue_data' => $formatted_revenue_data,
                    'popular_routes' => $formatted_popular_routes,
                    'booking_status' => $formatted_booking_status,
                    'recent_bookings' => 'pro_not_active',
                );
                return $return_data;
            }
            
        }
    }

    // Initialize the fixed analytics dashboard
    new MPTBM_Analytics_Dashboard();
}