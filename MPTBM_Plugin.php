<?php
/**
 * Plugin Name: E-cab Taxi Booking Manager for Woocommerce
 * Plugin URI: https://wordpress.org/plugins/ecab-taxi-booking-manager/
 * Description: A Complete Transportation Solution for WordPress by MagePeople.
 * Version: 2.0.9
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
			add_action('init', array(__CLASS__, 'maybe_upgrade_security_capabilities'), 1);
			add_action('init', array(__CLASS__, 'maybe_upgrade_api_schema'), 2);
			add_action('init', array(__CLASS__, 'register_driver_role'), 3);
            add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
            add_filter('body_class', array($this, 'add_body_class'));
            // The booking form's HTML expires: it carries a nonce and a date list.
            add_action('template_redirect', array($this, 'prevent_booking_page_cache'), 5);
            // Saving the map settings invalidates whatever the last lookup reported -
            // the point of saving is usually to fix exactly that - so drop the record and
            // let the next real lookup decide, rather than showing a warning about a
            // configuration that no longer exists.
            add_action('update_option_mptbm_map_api_settings', array(__CLASS__, 'clear_map_api_failure_record'));
            
            // Hook to automatically assign template when settings are saved
            add_action('update_option_mp_global_settings', array($this, 'auto_assign_template_on_settings_save'), 10, 3);
            
            // Hook to automatically assign template when pages are created/updated
            add_action('save_post_page', array($this, 'auto_assign_template_on_page_save'), 10, 3);
            
            // Add admin notice about template assignment
            add_action('admin_notices', array($this, 'show_template_assignment_notice'));
            // Warn when fares are being calculated from the OSRM fallback because
            // Google refused the server-side lookup (see MPTBM_Function::map_server_api_key).
            add_action('admin_notices', array($this, 'show_map_api_failure_notice'));
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
                define('MPTBM_PLUGIN_VERSION', '2.0.9');
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

            // WooCommerce is now OPTIONAL. The core plugin (CPT, settings, booking
            // search & pricing) always loads. WooCommerce-specific integration is
            // gated by MP_Global_Function::check_woocommerce() here and inside the
            // Admin/Frontend/Dependencies loaders, so the plugin runs standalone too.
            add_action('activated_plugin', array($this, 'activation_redirect'), 90, 1);
            require_once MPTBM_PLUGIN_DIR . '/inc/MPTBM_Dependencies.php';
            require_once MPTBM_PLUGIN_DIR . '/inc/MPTBM_Geo_Lib.php';

            // Load Block Editor Integration (does not require WooCommerce)
            if (function_exists('register_block_type')) {
                require_once MPTBM_PLUGIN_DIR . '/Frontend/MPTBM_Block.php';
                add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
            }

            // Load Elementor Integration (does not require WooCommerce)
            add_action('elementor/widgets/register', array($this, 'register_elementor_widget'));
            add_action('elementor/elements/categories_registered', array($this, 'add_elementor_widget_category'));

            if (MP_Global_Function::check_woocommerce() == 1) {
                // WooCommerce active: load the WC checkout-fields helper on frontend.
                require_once MPTBM_PLUGIN_DIR . '/Frontend/MPTBM_Wc_Checkout_Fields_Helper.php';
            } elseif (is_admin()) {
                // WooCommerce missing: still offer the (optional, non-blocking) installer
                // popup so admins can add WooCommerce if they want the WC checkout flow.
                require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Woo_Installer.php';
            }
        }

        public function activation_redirect($plugin)
        {
            // On activation (with WooCommerce active) land on the transportation
            // list, where the demo-import popup is offered. Skip on bulk activations.
            if ($plugin == plugin_basename(__FILE__) && ! isset($_GET['activate-multi'])) {
                exit(wp_redirect(admin_url('edit.php?post_type=mptbm_rent&page=mptbm_transportation_lists')));
            }
        }

        public function add_body_class($classes)
        {
            $classes[] = 'ecab-taxi';
            return $classes;
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

        }

		public static function grant_management_capabilities(): void
		{
			foreach (array('administrator', 'shop_manager') as $role_name) {
				$role = get_role($role_name);
				if ($role) {
					$role->add_cap('manage_mptbm_transportation');
				}
			}
			update_option('mptbm_security_capabilities_version', '2', false);
		}

		public static function maybe_upgrade_security_capabilities(): void
		{
			if ('2' !== get_option('mptbm_security_capabilities_version')) {
				self::grant_management_capabilities();
				global $wpdb;
				// WordPress truncates the former 21-character slug to 20 characters.
				// Migrate those legacy rows to the valid canonical CPT slug.
				$wpdb->update($wpdb->posts, array('post_type' => 'mptbm_service_book'), array('post_type' => 'mptbm_service_bookin'), array('%s'), array('%s'));
			}
		}

		public static function register_driver_role(): void
		{
			if (!get_role('mptbm_driver_role')) {
				add_role(
					'mptbm_driver_role',
					__('Driver', 'ecab-taxi-booking-manager'),
					array(
						'read' => true,
					)
				);
			}
		}

		public static function maybe_upgrade_api_schema(): void
		{
			if ('2' !== get_option('mptbm_api_schema_version')) {
				self::create_api_tables();
				update_option('mptbm_api_schema_version', '2', false);
			}
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
                api_secret varchar(255) NOT NULL,
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

			// Restrict transportation configuration to trusted store managers.
			self::grant_management_capabilities();

			// Drivers can be assigned to transportation units in the free plugin.
			self::register_driver_role();
            
            // Flush rewrite rules
            flush_rewrite_rules();
        }

		public static function on_plugin_deactivation(): void
		{
			wp_clear_scheduled_hook('mptbm_cleanup_api_logs');
			flush_rewrite_rules();
		}

		public static function uninstall(): void
		{
			global $wpdb;
			wp_clear_scheduled_hook('mptbm_cleanup_api_logs');
			foreach (array('administrator', 'shop_manager') as $role_name) {
				$role = get_role($role_name);
				if ($role) {
					$role->remove_cap('manage_mptbm_transportation');
				}
			}
			delete_option('mptbm_security_capabilities_version');
			delete_option('mptbm_api_schema_version');

			$wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}mptbm_api_keys`"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}mptbm_api_logs`"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$auto_pages = array(
				'transport_booking'              => '[mptbm_booking]',
				'transport_booking_manual'       => '[mptbm_booking price_based="manual" form="inline"]',
				'transport_booking_fixed_hourly' => '[mptbm_booking price_based="fixed_hourly"]',
				'transport-tabs'                 => '[mptbm_booking tab="yes" tabs="hourly,distance,manual"]',
			);
			foreach ($auto_pages as $slug => $content) {
				$page = get_page_by_path($slug, OBJECT, 'page');
				if ($page && trim((string) $page->post_content) === $content) {
					wp_delete_post($page->ID, true);
				}
			}
			$result_page = get_page_by_path('transport-result', OBJECT, 'page');
			if ($result_page && trim((string) $result_page->post_content) === '' && get_page_template_slug($result_page->ID) === 'transport_result.php') {
				wp_delete_post($result_page->ID, true);
			}

			$confirmation_page = absint(get_option('mptbm_confirmation_page_auto'));
			if ($confirmation_page && has_shortcode((string) get_post_field('post_content', $confirmation_page), 'mptbm_booking_confirmation')) {
				wp_delete_post($confirmation_page, true);
			}
			$payment_settings = get_option('mptbm_payment_settings', array());
			if (is_array($payment_settings) && absint($payment_settings['mptbm_confirmation_page_id'] ?? 0) === $confirmation_page) {
				unset($payment_settings['mptbm_confirmation_page_id']);
				update_option('mptbm_payment_settings', $payment_settings);
			}
			delete_option('mptbm_confirmation_page_auto');
		}

        public function mptbm_on_activation_template_create($templates)
        {
            $template_path = 'transport_result.php';
            $page_templates[$template_path] = 'Transport Result';
            foreach ($page_templates as $tk => $tv) {
                $templates[$tk] = $tv;
            }
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
            // Get the search result page slug from settings
            $search_result_slug = MP_Global_Function::get_settings('mptbm_general_settings', 'enable_view_search_result_page');
            
            // If no custom slug is set, use the default 'transport-result'
            if (empty($search_result_slug)) {
                $search_result_slug = 'transport-result';
            }
            
            // Check if the page exists
            $page = get_page_by_path($search_result_slug);
            if ($page && get_page_template_slug($page->ID) !== 'transport_result.php') {
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
         * Stop the booking form's HTML being reused on a later day.
         *
         * The rendered form is not static: MPTBM_Function::get_date() bakes the list of
         * selectable dates straight into it (mp_load_date_picker_js writes them out as
         * availableDates/minDate/defaultDate), and wp_nonce_field() bakes in a token that
         * WordPress expires after about a day. Both are computed at render time, so any
         * copy of that HTML reused tomorrow offers yesterday's dates - the customer can
         * no longer pick today, and today's bookings simply cannot be made.
         *
         * WordPress sends no cache directives of its own on a public page, which leaves
         * the decision to whatever sits in front of it - a CDN, a proxy, a caching plugin
         * added later, or the visitor's own browser applying heuristic freshness. None of
         * them can know this page goes stale at midnight unless it says so. The nonce
         * hazard is already documented against the AJAX endpoints in
         * Frontend/MPTBM_Transport_Search.php, which call nocache_headers() for exactly
         * this reason; the page that embeds the same nonce needs the same treatment.
         *
         * Scoped to pages that actually render the form, so the rest of the site stays
         * as cacheable as it was.
         */
        public function prevent_booking_page_cache()
        {
            if (is_admin()) {
                return;
            }

            // get_queried_object(), not is_singular()/get_post(). On this stack the main
            // query's conditional flags are already cleared by the time template_redirect
            // runs - measured on the live site: is_singular(), is_page(), is_404() and
            // is_front_page() all false and post_count 0, while get_queried_object()
            // still correctly returned the requested page. Something resets $wp_query
            // ahead of this hook, so the flags cannot be relied on here; the queried
            // object survives it and is what we actually need.
            $post = get_queried_object();
            if (!$post instanceof WP_Post) {
                return;
            }

            // Deliberately a plain string match rather than has_shortcode(): that helper
            // returns false for any tag not registered *yet*, which silently couples this
            // check to plugin load order for no benefit. The tag appearing in the content
            // is the fact we care about.
            $content = (string) $post->post_content;
            $renders_booking_form = false !== strpos($content, '[mptbm_booking')
                || false !== strpos($content, '[mptbm_dual_booking')
                || has_block('mptbm/booking', $post)
                // The search-results page renders the same form and nonce from a
                // template rather than page content, so match it by its template too.
                || 'transport_result.php' === get_page_template_slug($post->ID);

            // Elementor keeps its layout in post meta, not post_content, so a page built
            // with the booking widget looks empty to every check above.
            if (!$renders_booking_form) {
                $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
                if (is_string($elementor_data) && $elementor_data !== '') {
                    $renders_booking_form = false !== strpos($elementor_data, 'mptbm_booking');
                }
            }

            /**
             * Whether this request should be excluded from caching because it renders
             * the booking form. Themes and page builders can render the form in ways
             * the checks above cannot see (a widget dropped into a template, say).
             *
             * @param bool    $renders_booking_form Result of the built-in detection.
             * @param WP_Post $post                 Post being rendered.
             */
            if (apply_filters('mptbm_prevent_booking_page_cache', $renders_booking_form, $post)) {
                nocache_headers();
            }
        }

        /** Forget the last recorded map-lookup failure (see the hook registration above). */
        public static function clear_map_api_failure_record()
        {
            delete_transient('mptbm_map_api_failure');
        }

        /**
         * Surface a failing server-side Google Maps lookup.
         *
         * Fares are calculated from a distance this site resolves itself. When that
         * call is refused - most often because the configured key carries an HTTP
         * referrer restriction, which Google does not accept on the Distance Matrix
         * and Directions Web Service APIs - MPTBM_Function falls back to OSRM. The
         * form keeps working, so nothing looks broken, but trips are then priced off
         * OpenStreetMap's road network and can quote a materially different distance
         * than the map the customer is looking at. That is a money bug, so it is
         * raised on every admin screen rather than only on the plugin's own pages.
         * MPTBM_Function clears the record as soon as a Google call succeeds again.
         */
        public function show_map_api_failure_notice()
        {
            if (!current_user_can('manage_options') || !class_exists('MPTBM_Function')) {
                return;
            }
            $failure = MPTBM_Function::get_map_api_failure();
            if (empty($failure['reason'])) {
                return;
            }

            // Don't keep nagging once one of the two remedies below is actually in place.
            // Either of them means the failing server-side Google lookup no longer decides
            // what customers pay, so the warning would be describing a problem that has
            // been dealt with. This matters more than it sounds: under "browser" the
            // server-side lookup stops being attempted at all, so nothing would ever
            // succeed to clear the record, and the notice would sit there permanently.
            $server_key = MP_Global_Function::get_settings('mptbm_map_api_settings', 'gmap_server_api_key');
            $fare_source = MP_Global_Function::get_settings('mptbm_map_api_settings', 'fare_distance_source', 'server');
            if ($server_key || 'browser' === $fare_source) {
                return;
            }

            $settings_url = admin_url('edit.php?post_type=mptbm_rent&page=mptbm_settings_page');
            echo '<div class="notice notice-error">';
            echo '<p><strong>' . esc_html__('E-Cab Taxi Booking Manager:', 'ecab-taxi-booking-manager') . '</strong> ';
            echo esc_html__('The server-side Google Maps distance lookup is failing, so trip prices are currently being calculated from the OpenStreetMap fallback. That can quote a different distance than the map shows the customer.', 'ecab-taxi-booking-manager');
            echo '</p>';
            echo '<p><code>' . esc_html($failure['reason']) . '</code></p>';
            echo '<p><strong>' . esc_html__('Two ways to fix this, both in', 'ecab-taxi-booking-manager') . ' ';
            echo '<a href="' . esc_url($settings_url) . '">' . esc_html__('Map API Settings', 'ecab-taxi-booking-manager') . '</a>:</strong></p>';
            echo '<ol>';
            echo '<li>' . esc_html__('Best: paste an IP-restricted (or unrestricted) key with the Distance Matrix API and Directions API enabled into "Google MAP API Key (Server Side)". A key restricted by HTTP referrer works for the map in the browser but is always rejected for these server-side requests.', 'ecab-taxi-booking-manager') . '</li>';
            echo '<li>' . esc_html__('No second key available: set "Fare Distance Source" to "Browser, verified server-side". Trips are then priced on the distance Google already calculated in the customer\'s browser, which the server checks against the straight-line distance before accepting.', 'ecab-taxi-booking-manager') . '</li>';
            echo '</ol>';
            echo '</div>';
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
         * Dequeue CSS that may conflict with our flatpickr calendar styling.
         */
        public function dequeue_conflicting_styles() {
            // Handle used by WP Travel Engine for jQuery UI Datepicker theme.
            wp_dequeue_style('datepicker-style');

            // WTE bundles many generic styles (including .ui-datepicker) into this handle.
            wp_dequeue_style('wp-travel-engine');
        }
    }

    // Register activation hook
    register_activation_hook(__FILE__, array('MPTBM_Plugin', 'on_plugin_activation'));
	register_deactivation_hook(__FILE__, array('MPTBM_Plugin', 'on_plugin_deactivation'));
	register_uninstall_hook(__FILE__, array('MPTBM_Plugin', 'uninstall'));
    
    new MPTBM_Plugin();
}
