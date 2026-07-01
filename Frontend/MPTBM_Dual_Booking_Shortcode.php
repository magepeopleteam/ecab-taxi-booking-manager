<?php
/*
 * New shortcode: [mptbm_dual_booking]
 * Renders two independent search forms stacked on one page - a flat rate
 * (manual) form on top and a distance based form below - each with its own
 * working Search button, reusing the existing mptbm_transport_search action
 * and AJAX search endpoints untouched.
 *
 * get_details.php renders both date/time picker fields and the map area with
 * the same hardcoded element IDs every time it's included, so simply printing
 * the existing form twice breaks the second form's date picker and the map.
 * This class works around that without editing any existing plugin file:
 *  - the flat rate form never needs the map, so its inert (display:none) map
 *    block is stripped after render, leaving only one #mptbm_map_area on the
 *    page for the distance form's map/autocomplete to initialize into.
 *  - the distance form's date-picker init script is retargeted (by rewriting
 *    the captured output, not the template) to a selector scoped to its own
 *    wrapper, so it gets its own jQuery UI datepicker instance instead of
 *    silently re-binding the flat rate form's date field.
 *  - mptbm_dual_booking.js takes care of the date/time-list change handlers,
 *    which are scoped by JS event rebinding rather than markup rewriting.
 */
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_Dual_Booking_Shortcode')) {
    class MPTBM_Dual_Booking_Shortcode {
        public function __construct() {
            add_shortcode('mptbm_dual_booking', array($this, 'render'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'), 90);
        }

        public function enqueue_assets() {
            wp_enqueue_style(
                'mptbm_dual_booking',
                MPTBM_PLUGIN_URL . '/assets/frontend/mptbm_dual_booking.css',
                array(),
                MPTBM_PLUGIN_VERSION
            );
            wp_enqueue_script(
                'mptbm_dual_booking',
                MPTBM_PLUGIN_URL . '/assets/frontend/mptbm_dual_booking.js',
                array('jquery', 'jquery-ui-datepicker', 'mptbm_registration'),
                MPTBM_PLUGIN_VERSION,
                true
            );
        }

        public function render($attributes) {
            $atts = shortcode_atts(array(
                'manual_label'   => __('Flat Rate Search', 'ecab-taxi-booking-manager'),
                'distance_label' => __('Distance Based Search', 'ecab-taxi-booking-manager'),
                'show_labels'    => 'yes',
            ), $attributes, 'mptbm_dual_booking');

            $manual_params = array(
                'price_based' => 'manual',
                'progressbar' => 'no',
                'form'        => 'inline',
                'map'         => 'no',
                'tab'         => 'no',
                'tabs'        => '',
            );

            $distance_params = array(
                'price_based' => 'dynamic',
                'progressbar' => 'no',
                'form'        => 'horizontal',
                'map'         => 'yes',
                'tab'         => 'no',
                'tabs'        => '',
            );

            ob_start();
            do_action('mptbm_transport_search', $manual_params);
            $manual_html = ob_get_clean();
            $manual_html = $this->strip_inert_map_block($manual_html);

            ob_start();
            do_action('mptbm_transport_search', $distance_params);
            $distance_html = ob_get_clean();
            $distance_html = $this->scope_datepicker_init($distance_html, '.mptbm_dual_booking_distance');

            ob_start();
            ?>
            <div class="mptbm_dual_booking_wrap">
                <div class="mptbm_dual_booking_form mptbm_dual_booking_manual">
                    <?php if ($atts['show_labels'] === 'yes') : ?>
                        <span class="mptbm_dual_booking_label"><?php echo esc_html($atts['manual_label']); ?></span>
                    <?php endif; ?>
                    <?php echo $manual_html; ?>
                </div>
                <div class="mptbm_dual_booking_form mptbm_dual_booking_distance">
                    <?php if ($atts['show_labels'] === 'yes') : ?>
                        <span class="mptbm_dual_booking_label"><?php echo esc_html($atts['distance_label']); ?></span>
                    <?php endif; ?>
                    <?php echo $distance_html; ?>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * The manual/flat-rate form never displays its map (get_details.php hides
         * it for price_based == 'manual'), but the markup - including the
         * id="mptbm_map_area" node - is still printed. Because the plugin's map
         * init only ever looks at the first .mptbm_map_area/#mptbm_map_area in
         * the document, that inert, hidden copy has to be removed so the
         * distance form's real, visible map below it is the only one on the
         * page and initializes correctly.
         */
        private function strip_inert_map_block($html) {
            $needle = '<div class="mptbm_map_area fdColumn" style="display: none;">';
            $start = strpos($html, $needle);
            if ($start === false) {
                return $html;
            }

            $pos = $start + strlen($needle);
            $depth = 1;
            $len = strlen($html);

            while ($depth > 0 && $pos < $len) {
                $next_open = strpos($html, '<div', $pos);
                $next_close = strpos($html, '</div>', $pos);

                if ($next_close === false) {
                    return $html; // Unexpected structure: bail out without touching anything.
                }

                if ($next_open !== false && $next_open < $next_close) {
                    $depth++;
                    $pos = $next_open + 4;
                } else {
                    $depth--;
                    $pos = $next_close + 6;
                }
            }

            return substr($html, 0, $start) . substr($html, $pos);
        }

        /**
         * get_details.php always initializes the pickup/return date pickers with
         * the bare selectors #mptbm_start_date / #mptbm_return_date. When the same
         * markup is printed twice on one page, a bare jQuery("#id") selector only
         * ever resolves to the first matching element, so the second form's date
         * inputs never get their own calendar widget. Retargeting just these two
         * generated snippets to a selector scoped under this form's own wrapper
         * class fixes that while keeping the actual id="" attributes (and
         * therefore the Search button's own #id lookups) untouched.
         */
        private function scope_datepicker_init($html, $scope_class) {
            $replacements = array(
                'jQuery("#mptbm_start_date").datepicker({'  => 'jQuery("' . $scope_class . ' #mptbm_start_date").datepicker({',
                'jQuery("#mptbm_return_date").datepicker({' => 'jQuery("' . $scope_class . ' #mptbm_return_date").datepicker({',
            );

            return str_replace(array_keys($replacements), array_values($replacements), $html);
        }
    }
    new MPTBM_Dual_Booking_Shortcode();
}
