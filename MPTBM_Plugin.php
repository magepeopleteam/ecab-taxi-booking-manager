<?php
/**
 * Plugin Name: E-cab Taxi Booking Manager for Woocommerce
 * Plugin URI: https://wordpress.org/plugins/ecab-taxi-booking-manager/
 * Description: A Complete Transportation Solution for WordPress by MagePeople.
 * Version: 1.3.2
 * Author: MagePeople Team
 * Author URI: http://www.mage-people.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ecab-taxi-booking-manager
 * Domain Path: /languages/
 */
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.
if (!class_exists('MPTBM_Plugin')) {
    class MPTBM_Plugin
    {
        public function __construct()
        {
            $this->load_plugin();

            add_filter('theme_page_templates', array($this, 'mptbm_on_activation_template_create'), 10, 3);
            add_filter('template_include', array($this, 'mptbm_change_page_template'), 99);
            add_action('admin_init', array($this, 'wptbm_assign_template_to_page'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
            
            // Hook to automatically assign template when settings are saved
            add_action('update_option_mp_global_settings', array($this, 'auto_assign_template_on_settings_save'), 10, 3);
            
            // Hook to automatically assign template when pages are created/updated
            add_action('save_post_page', array($this, 'auto_assign_template_on_page_save'), 10, 3);
            
            // Add admin notice about template assignment
            add_action('admin_notices', array($this, 'show_template_assignment_notice'));
        }

        private function load_plugin(): void
        {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
            if (!defined('MPTBM_PLUGIN_DIR')) {
                define('MPTBM_PLUGIN_DIR', dirname(__FILE__));
            }
            if (!defined('MPTBM_PLUGIN_URL')) {
                define('MPTBM_PLUGIN_URL', plugins_url() . '/' . plugin_basename(dirname(__FILE__)));
            }
            if (!defined('MPTBM_PLUGIN_DATA')) {
                // define('MPTBM_PLUGIN_DATA', get_plugin_data(__FILE__));
            }
            if (!defined('MPTBM_PLUGIN_VERSION')) {
                define('MPTBM_PLUGIN_VERSION', '1.2.1');
            }

            // Create required directories if they don't exist
            $dirs = array(
                MPTBM_PLUGIN_DIR . '/assets/admin/css',
                MPTBM_PLUGIN_DIR . '/assets/admin/js'
            );
            
            foreach ($dirs as $dir) {
                if (!file_exists($dir)) {
                    wp_mkdir_p($dir);
                }
            }

            require_once MPTBM_PLUGIN_DIR . '/mp_global/MP_Global_File_Load.php';
            if (MP_Global_Function::check_woocommerce() == 1) {
                add_action('activated_plugin', array($this, 'activation_redirect'), 90, 1);
                self::on_activation_page_create();
                require_once MPTBM_PLUGIN_DIR . '/inc/MPTBM_Dependencies.php';
                require_once MPTBM_PLUGIN_DIR . '/inc/MPTBM_Geo_Lib.php';
				

                // Load Block Editor Integration
                if (function_exists('register_block_type')) {
                    require_once MPTBM_PLUGIN_DIR . '/Frontend/MPTBM_Block.php';
                    add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
                }

                // Load Elementor Integration
                add_action('elementor/widgets/register', array($this, 'register_elementor_widget'));
                add_action('elementor/elements/categories_registered', array($this, 'add_elementor_widget_category'));

                // Always load the checkout fields helper on frontend
                require_once MPTBM_PLUGIN_DIR . '/Frontend/MPTBM_Wc_Checkout_Fields_Helper.php';
            } else {
                require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Quick_Setup.php';
                add_action('activated_plugin', array($this, 'activation_redirect_setup'), 90, 1);
            }
        }

        public function activation_redirect($plugin)
        {
            $mptbm_quick_setup_done = get_option('mptbm_quick_setup_done');
            if ($plugin == plugin_basename(__FILE__) && $mptbm_quick_setup_done != 'yes') {
                exit(wp_redirect(admin_url('edit.php?post_type=mptbm_rent&page=mptbm_quick_setup')));
            }
        }

        public function activation_redirect_setup($plugin)
        {
            $mptbm_quick_setup_done = get_option('mptbm_quick_setup_done');
            if ($plugin == plugin_basename(__FILE__) && $mptbm_quick_setup_done != 'yes') {
                exit(wp_redirect(admin_url('admin.php?post_type=mptbm_rent&page=mptbm_quick_setup')));
            }
        }

        public static function on_activation_page_create(): void
        {
            if (did_action('wp_loaded')) {
                self::create_pages();
                self::create_api_tables();
            } else {
                add_action('wp_loaded', array(__CLASS__, 'create_pages'));
                add_action('wp_loaded', array(__CLASS__, 'create_api_tables'));
            }
        }

        public static function create_pages(): void
        {
            $forbidden_slugs = array(
                'transport_booking',
                'transport_booking_manual',
                'transport_booking_fixed_hourly',
                'transport-result',
                'transport-tabs' 
            );

            foreach ($forbidden_slugs as $slug) {
                $existing_page = get_page_by_path($slug, OBJECT, 'page');

                if (!$existing_page) {
                    $post_content = ''; 

                    switch ($slug) {
                        case 'transport_booking':
                            $post_title = 'Transport Booking';
                            $post_content = '[mptbm_booking]';
                            break;

                        case 'transport_booking_manual':
                            $post_title = 'Transport Booking Manual';
                            $post_content = '[mptbm_booking price_based="manual" form="inline"]';
                            break;

                        case 'transport_booking_fixed_hourly':
                            $post_title = 'Transport Booking Fixed Hourly';
                            $post_content = '[mptbm_booking price_based="fixed_hourly"]';
                            break;

                        case 'transport-result':
                            $post_title = 'Transport Result';
                            break;

                        case 'transport-tabs':
                            $post_title = 'Transport Tabs';
                            $post_content = '[mptbm_booking tab="yes" tabs="hourly,distance,manual"]';
                            break;
                    }

                    $page_data = array(
                        'post_type'    => 'page',
                        'post_name'    => $slug,
                        'post_title'   => $post_title,
                        'post_content' => $post_content,
                        'post_status'  => 'publish',
                    );
                    wp_insert_post($page_data);
                }
            }

            flush_rewrite_rules();
        }
        
        public static function create_api_tables(): void
        {
            global $wpdb;
            
            $api_keys_table = $wpdb->prefix . 'mptbm_api_keys';
            $api_logs_table = $wpdb->prefix . 'mptbm_api_logs';
            
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
            dbDelta($api_keys_sql);
            dbDelta($api_logs_sql);
        }
        
        public static function on_plugin_activation()
        {
            // Create pages
            self::on_activation_page_create();
            
            // Create API tables
            self::create_api_tables();
            
            // Flush rewrite rules
            flush_rewrite_rules();
        }

        public function mptbm_on_activation_template_create($templates)
        {
            $template_path = 'transport_result.php';
            $page_templates[$template_path] = 'Transport Result';
            foreach ($page_templates as $tk => $tv) {
                $templates[$tk] = $tv;
            }
            flush_rewrite_rules();
            return $templates;
        }

        public function mptbm_change_page_template($template)
        {
            global $wp_query, $wpdb;
            $page_temp_slug = get_page_template_slug(get_the_ID());
            $template_path = 'transport_result.php';
            $page_templates[$template_path] = 'Transport Result';
            if (isset($page_templates[$page_temp_slug])) {
                $template = plugin_dir_path(__FILE__) . '/' . $page_temp_slug;
            }

            return $template;
        }

        public function wptbm_assign_template_to_page()
        {
            flush_rewrite_rules();
            
            // Get the search result page slug from settings
            $search_result_slug = MP_Global_Function::get_settings('mptbm_general_settings', 'enable_view_search_result_page');
            
            // If no custom slug is set, use the default 'transport-result'
            if (empty($search_result_slug)) {
                $search_result_slug = 'transport-result';
            }
            
            // Check if the page exists
            $page = get_page_by_path($search_result_slug);
            if ($page) {
                // Update the page meta to assign the template
                update_post_meta($page->ID, '_wp_page_template', 'transport_result.php');
            }
        }
        
        /**
         * Automatically assign the Transport Result template when settings are saved
         */
        public function auto_assign_template_on_settings_save($old_value, $value, $option)
        {
            // Check if the mptbm_general_settings were updated
            if (isset($value['mptbm_general_settings']['enable_view_search_result_page'])) {
                $new_search_result_slug = $value['mptbm_general_settings']['enable_view_search_result_page'];
                $old_search_result_slug = isset($old_value['mptbm_general_settings']['enable_view_search_result_page']) ? $old_value['mptbm_general_settings']['enable_view_search_result_page'] : '';
                
                // If the slug changed, remove template from old page
                if (!empty($old_search_result_slug) && $old_search_result_slug !== $new_search_result_slug) {
                    $old_page = get_page_by_path($old_search_result_slug);
                    if ($old_page) {
                        delete_post_meta($old_page->ID, '_wp_page_template');
                    }
                }
                
                // If a new slug is provided, assign the template to that page
                if (!empty($new_search_result_slug)) {
                    $page = get_page_by_path($new_search_result_slug);
                    if ($page) {
                        update_post_meta($page->ID, '_wp_page_template', 'transport_result.php');
                    }
                }
            }
        }
        
        /**
         * Automatically assign the Transport Result template when a page is created/updated
         */
        public function auto_assign_template_on_page_save($post_id, $post, $update)
        {
            // Only proceed if this is a page and it's being published
            if ($post->post_type !== 'page' || $post->post_status !== 'publish') {
                return;
            }
            
            // Get the search result page slug from settings
            $search_result_slug = MP_Global_Function::get_settings('mptbm_general_settings', 'enable_view_search_result_page');
            
            // If no custom slug is set, use the default 'transport-result'
            if (empty($search_result_slug)) {
                $search_result_slug = 'transport-result';
            }
            
            // Check if this page's slug matches the search result slug
            if ($post->post_name === $search_result_slug) {
                update_post_meta($post_id, '_wp_page_template', 'transport_result.php');
            }
        }
        
        /**
         * Show admin notice about automatic template assignment
         */
        public function show_template_assignment_notice()
        {
            // Only show on plugin settings page
            if (!isset($_GET['page']) || $_GET['page'] !== 'mptbm_settings_page') {
                return;
            }
            
            $search_result_slug = MP_Global_Function::get_settings('mptbm_general_settings', 'enable_view_search_result_page');
            
            if (!empty($search_result_slug)) {
                $page = get_page_by_path($search_result_slug);
                if ($page) {
                    $template = get_page_template_slug($page->ID);
                    if ($template === 'transport_result.php') {
                        echo '<div class="notice notice-success is-dismissible">';
                        echo '<p><strong>' . esc_html__('E-Cab Taxi Booking Manager:', 'ecab-taxi-booking-manager') . '</strong> ';
                        echo sprintf(
                            esc_html__('The "Transport Result" template has been automatically assigned to the page "%s" (slug: %s).', 'ecab-taxi-booking-manager'),
                            esc_html($page->post_title),
                            esc_html($search_result_slug)
                        );
                        echo '</p>';
                        echo '</div>';
                    }
                }
            }
        }

        /**
         * Enqueue Block Editor assets
         */
        public function enqueue_block_editor_assets() {
            // Enqueue block editor script
            wp_enqueue_script(
                'mptbm-block-editor',
                MPTBM_PLUGIN_URL . '/assets/js/block.js',
                array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'),
                MPTBM_PLUGIN_VERSION
            );

            // Enqueue block editor styles
            wp_enqueue_style(
                'mptbm-block-editor',
                MPTBM_PLUGIN_URL . '/assets/css/block-editor.css',
                array(),
                MPTBM_PLUGIN_VERSION
            );
        }

        /**
         * Register Elementor widget
         */
        public function register_elementor_widget($widgets_manager) {
            if (class_exists('\\Elementor\\Widget_Base')) {
                require_once MPTBM_PLUGIN_DIR . '/Frontend/MPTBM_Elementor_Widget.php';
                $widgets_manager->register(new MPTBM_Elementor_Widget());
            }
        }

        /**
         * Add Elementor widget category
         */
        public function add_elementor_widget_category($elements_manager) {
            $elements_manager->add_category(
                'mptbm',
                [
                    'title' => esc_html__('E-Cab Taxi Booking', 'ecab-taxi-booking-manager'),
                    'icon' => 'fa fa-car',
                ]
            );
        }

        /**
         * Enqueue frontend assets
         */
        public function enqueue_frontend_assets() {
            // Check if WooCommerce is active and the is_checkout function exists
            if (function_exists('is_checkout') && is_checkout()) {
                wp_enqueue_style(
                    'mptbm-file-upload',
                    MPTBM_PLUGIN_URL . '/assets/css/file-upload.css',
                    array(),
                    MPTBM_PLUGIN_VERSION
                );
            }

            // Dequeue conflicting datepicker CSS from other plugins (e.g., WP Travel Engine)
            // on pages where our booking shortcode is present.
            if (is_singular()) {
                global $post;
                if ($post && has_shortcode($post->post_content, 'mptbm_booking')) {
                    // Run very late to ensure conflicting styles were enqueued first.
                    add_action('wp_print_styles', array($this, 'dequeue_conflicting_styles'), 999);
                }
            }
        }

        /**
         * Dequeue CSS that overrides our jQuery UI datepicker styling.
         */
        public function dequeue_conflicting_styles() {
            // Handle used by WP Travel Engine for jQuery UI Datepicker theme.
            wp_dequeue_style('datepicker-style');

            // WTE bundles many generic styles (including .ui-datepicker) into this handle.
            wp_dequeue_style('wp-travel-engine');

            // Ensure our jQuery UI stylesheet prints after others for higher cascade priority.
            if (wp_style_is('mp_jquery_ui', 'enqueued')) {
                wp_dequeue_style('mp_jquery_ui');
                wp_enqueue_style('mp_jquery_ui', MPTBM_PLUGIN_URL . '/mp_global/assets/jquery-ui.min.css', array(), '1.13.2');
            }

            // If any theme or plugin enqueues their own jQuery UI base with this handle,
            // leave it as-is. Our plugin already enqueues its own scoped UI CSS as 'mp_jquery_ui'.
        }
    }

    // Register activation hook
    register_activation_hook(__FILE__, array('MPTBM_Plugin', 'on_plugin_activation'));
    
    new MPTBM_Plugin();
}
