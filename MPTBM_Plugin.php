<?php
/**
 * Plugin Name: E-cab Taxi Booking Manager for Woocommerce
 * Plugin URI: https://wordpress.org/plugins/ecab-taxi-booking-manager/
 * Description: A Complete Transportation Solution for WordPress by MagePeople.
 * Version: 1.2.9
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
                require_once MPTBM_PLUGIN_DIR . '/inc/MPTBM_Rest_Api.php';
                require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_API_Documentation.php';
				

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
            } else {
                add_action('wp_loaded', array(__CLASS__, 'create_pages'));
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
            // Check if the page 'transport-result' exists
            $page = get_page_by_path('transport-result');
            if ($page) {
                // Update the page meta to assign the template
                update_post_meta($page->ID, '_wp_page_template', 'transport_result.php');
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

    new MPTBM_Plugin();
}
