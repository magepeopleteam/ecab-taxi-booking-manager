<?php
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.
if (!class_exists('MPTBM_Dependencies')) {
    class MPTBM_Dependencies {
        public function __construct() {
            add_action('init', array($this, 'language_load'));
            $this->load_file();
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue'), 90);
            add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue'), 90);
            add_action('wp_enqueue_scripts', array($this, 'global_enqueue'), 90);
            add_action('admin_head', array($this, 'js_constant'), 5);
            add_action('wp_head', array($this, 'js_constant'), 5);
        }

        public function language_load(): void {
            $plugin_dir = basename(dirname(__DIR__)) . "/languages/";
            load_plugin_textdomain('mptbm_plugin', false, $plugin_dir);
        }

        private function load_file(): void {
            require_once MPTBM_PLUGIN_DIR . '/inc/MPTBM_Function.php';
            require_once MPTBM_PLUGIN_DIR . '/inc/MPTBM_Query.php';
            require_once MPTBM_PLUGIN_DIR . '/inc/MPTBM_Layout.php';
            require_once MPTBM_PLUGIN_DIR . '/inc/MPTBM_Style.php';
            require_once MPTBM_PLUGIN_DIR . '/inc/MPTBM_Super_Slider.php';
            require_once MPTBM_PLUGIN_DIR . '/Admin/MPTBM_Admin.php';
            require_once MPTBM_PLUGIN_DIR . '/Frontend/MPTBM_Frontend.php';
        }

        public function admin_enqueue() {
            $this->global_enqueue();
            wp_enqueue_editor();
            //admin script
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
            wp_enqueue_style('wp-codemirror');
            wp_enqueue_script('wp-codemirror');
            //********//
            // custom
            wp_enqueue_script('mp_admin_settings', MPTBM_PLUGIN_URL . '/assets/admin/mp_admin_settings.js', array('jquery'), time(), true);
            wp_enqueue_style('mp_admin_settings', MPTBM_PLUGIN_URL . '/assets/admin/mp_admin_settings.css', array(), time());
            // custom
            wp_enqueue_script('mptbm_admin', MPTBM_PLUGIN_URL . '/assets/admin/mptbm_admin.js', array('jquery'), time(), true);
            wp_enqueue_style('mptbm_admin', MPTBM_PLUGIN_URL . '/assets/admin/mptbm_admin.css', array(), time());
            do_action('mptbm_admin_script');
        }

        public function frontend_enqueue() {
            $this->global_enqueue();
            wp_enqueue_style('mptbm_filter_pagination', MPTBM_PLUGIN_URL . '/assets/frontend/filter_pagination.css', array(), time());
            wp_enqueue_script('mptbm_filter_pagination', MPTBM_PLUGIN_URL . '/assets/frontend/filter_pagination.js', array('jquery'), time(), true);
            wp_enqueue_style('mptbm_style', MPTBM_PLUGIN_URL . '/assets/frontend/mptbm_style.css', array(), time());
            wp_enqueue_script('mptbm_script', MPTBM_PLUGIN_URL . '/assets/frontend/mptbm_script.js', array('jquery'), time(), true);
            wp_enqueue_script('mptbm_price_calculation', MPTBM_PLUGIN_URL . '/assets/frontend/mptbm_price_calculation.js', array('jquery'), time(), true);
            do_action('mptbm_registration_enqueue');
            do_action('mptbm_frontend_enqueue');
            wp_enqueue_style('quantity-box', MPTBM_PLUGIN_URL . '/assets/helper/custom-designs/styles/quantity-box.css', array(), time());
            wp_enqueue_script('quantity-box', MPTBM_PLUGIN_URL . '/assets/helper/custom-designs/scripts/quantity-box.js', array('jquery'), time(), true);
            wp_enqueue_style('custom-css', MPTBM_PLUGIN_URL . '/assets/helper/custom-designs/styles/custom-css.css', array(), time());
            wp_enqueue_script('custom-js', MPTBM_PLUGIN_URL . '/assets/helper/custom-designs/scripts/custom-js.js', array('jquery'), time(), true);

        }

        public function global_enqueue() {
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-core');
            wp_enqueue_script('jquery-ui-datepicker');
            wp_localize_script('jquery', 'mpwpb_ajax', array('mpwpb_ajax' => admin_url('Admin-ajax.php')));
            wp_enqueue_style('mp_jquery_ui', 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css', array(), '1.12.1');
            wp_enqueue_style('mp_font_awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css', array(), '5.15.4');
            wp_enqueue_style('mp_plugin_global', MPTBM_PLUGIN_URL . '/assets/helper/mp_style/mp_style.css', array(), time());
            wp_enqueue_script('mp_plugin_global', MPTBM_PLUGIN_URL . '/assets/helper/mp_style/mp_script.js', array('jquery'), time(), true);

            wp_enqueue_style('mp_select_2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css', array(), '4.0.13');
            wp_enqueue_script('mp_select_2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'));
            wp_enqueue_script('mptbm_map', 'https://maps.googleapis.com/maps/api/js?libraries=places&amp;language=en&amp;key=AIzaSyD61CGRsenVDXkRMrBzxQnVTtL7EZz0k_c', array('jquery'), time(), true);
            do_action('mptbm_global_enqueue');
        }

        public function js_constant() {
            ?>
            <script type="text/javascript">
                let mp_ajax_url = "<?php echo admin_url('admin-ajax.php'); ?>";
                let mp_currency_symbol = "<?php echo get_woocommerce_currency_symbol(); ?>";
                let mp_currency_position = "<?php echo get_option('woocommerce_currency_pos'); ?>";
                let mp_currency_decimal = "<?php echo wc_get_price_decimal_separator(); ?>";
                let mp_currency_thousands_separator = "<?php echo wc_get_price_thousand_separator(); ?>";
                let mp_num_of_decimal = "<?php echo get_option('woocommerce_price_num_decimals', 2); ?>";
                let mp_empty_image_url = "<?php echo esc_attr(MPTBM_PLUGIN_URL . '/assets/helper/images/no_image.png'); ?>";
                let mp_date_format = "<?php echo esc_attr(MPTBM_Function::get_general_settings('date_format', 'D d M , yy')); ?>";
                let mp_lat_lng = {lat: 23.81234828905659, lng: 90.41069652669002};
                const mp_map_options = {
                    componentRestrictions: {country: "BD"},
                    fields: ["address_components", "geometry"],
                    types: ["address"],
                }
            </script>
            <?php
        }
    }

    new MPTBM_Dependencies();
}