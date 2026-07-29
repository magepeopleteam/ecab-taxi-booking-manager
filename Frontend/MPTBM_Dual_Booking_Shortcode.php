<?php
/*
 * New shortcode: [mptbm_dual_booking]
 * Renders two independent search forms stacked on one page - by default a
 * flat rate (manual) form on top and a distance based form below - each with
 * its own working Search button, reusing the existing mptbm_transport_search
 * action and AJAX search endpoints untouched.
 *
 * Attributes:
 *   top_price_based    dynamic|manual|fixed_hourly|fixed_distance|fixed_zone|
 *                       fixed_zone_dropoff|fixed_map   (default: manual)
 *   bottom_price_based  same allowed values             (default: dynamic)
 *   top_form / bottom_form   horizontal|inline|vertical (default: inline / horizontal)
 *   top_map / bottom_map     yes|no                     (default: no / yes)
 *   top_label / bottom_label text shown above each form
 *   show_labels          yes|no  (default: yes)
 *
 * Example: [mptbm_dual_booking top_price_based="manual" bottom_price_based="dynamic"]
 *
 * get_details.php renders both date/time picker fields and the map area with
 * the same hardcoded element IDs every time it's included, so simply printing
 * the existing form twice breaks the second form's date picker and the map.
 * This class works around that without editing any existing plugin file:
 *  - whichever form's map ends up hidden by the template (price_based ==
 *    'manual' always hides it) has its inert map block stripped after render,
 *    so at most one #mptbm_map_area is left on the page for a visible map to
 *    initialize into. This is a no-op for a form whose map is actually shown.
 *  - the bottom form's date-picker init script is retargeted (by rewriting
 *    the captured output, not the template) to a selector scoped to its own
 *    wrapper, so it gets its own jQuery UI datepicker instance instead of
 *    silently re-binding the top form's date field.
 *  - mptbm_dual_booking.js takes care of the date/time-list change handlers
 *    and the custom location-search dropdown, scoped by JS event rebinding
 *    rather than markup rewriting.
 *
 * Known limitation: if both slots are given a price_based that shows a real
 * map (e.g. both "dynamic"), only the first form's map will initialize - two
 * live maps on one page isn't handled by this shortcode.
 */
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

if (!class_exists('MPTBM_Dual_Booking_Shortcode')) {
    class MPTBM_Dual_Booking_Shortcode {
        const VALID_PRICE_BASED = array(
            'dynamic', 'manual', 'fixed_hourly', 'fixed_distance', 'fixed_zone', 'fixed_zone_dropoff', 'fixed_map',
        );
        const VALID_FORM_STYLE = array('horizontal', 'inline', 'vertical');

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
                array('jquery', 'flatpickr', 'mptbm_registration'),
                MPTBM_PLUGIN_VERSION,
                true
            );
        }

        public function render($attributes) {
            $atts = shortcode_atts(array(
                'top_price_based'    => 'manual',
                'bottom_price_based' => 'dynamic',
                'top_form'           => 'inline',
                'bottom_form'        => 'horizontal',
                'top_map'            => 'no',
                'bottom_map'         => 'yes',
                'top_label'          => __('Flat Rate Search', 'ecab-taxi-booking-manager'),
                'bottom_label'       => __('Distance Based Search', 'ecab-taxi-booking-manager'),
                'show_labels'        => 'yes',
            ), $attributes, 'mptbm_dual_booking');

            $top_params = array(
                'price_based' => $this->sanitize_choice($atts['top_price_based'], self::VALID_PRICE_BASED, 'manual'),
                'progressbar' => 'no',
                'form'        => $this->sanitize_choice($atts['top_form'], self::VALID_FORM_STYLE, 'inline'),
                'map'         => ($atts['top_map'] === 'yes') ? 'yes' : 'no',
                'tab'         => 'no',
                'tabs'        => '',
            );

            $bottom_params = array(
                'price_based' => $this->sanitize_choice($atts['bottom_price_based'], self::VALID_PRICE_BASED, 'dynamic'),
                'progressbar' => 'no',
                'form'        => $this->sanitize_choice($atts['bottom_form'], self::VALID_FORM_STYLE, 'horizontal'),
                'map'         => ($atts['bottom_map'] === 'no') ? 'no' : 'yes',
                'tab'         => 'no',
                'tabs'        => '',
            );

            $show_labels = ($atts['show_labels'] === 'no') ? 'no' : 'yes';

            ob_start();
            do_action('mptbm_transport_search', $top_params);
            $top_html = ob_get_clean();
            $top_html = $this->strip_inert_map_block($top_html);

            ob_start();
            do_action('mptbm_transport_search', $bottom_params);
            $bottom_html = ob_get_clean();
            $bottom_html = $this->strip_inert_map_block($bottom_html);
            $bottom_html = $this->scope_datepicker_init($bottom_html, '.mptbm_dual_booking_bottom');

            ob_start();
            ?>
            <div class="mptbm_dual_booking_wrap">
                <div class="mptbm_dual_booking_form mptbm_dual_booking_top">
                    <?php if ($show_labels === 'yes') : ?>
                        <span class="mptbm_dual_booking_label"><?php echo esc_html($atts['top_label']); ?></span>
                    <?php endif; ?>
                    <?php echo $top_html; ?>
                </div>
                <div class="mptbm_dual_booking_form mptbm_dual_booking_bottom">
                    <?php if ($show_labels === 'yes') : ?>
                        <span class="mptbm_dual_booking_label"><?php echo esc_html($atts['bottom_label']); ?></span>
                    <?php endif; ?>
                    <?php echo $bottom_html; ?>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        private function sanitize_choice($value, $allowed, $default) {
            return in_array($value, $allowed, true) ? $value : $default;
        }

        /**
         * A price_based of 'manual' always renders its map hidden
         * (display:none), but the markup - including the id="mptbm_map_area"
         * node - is still printed. Because the plugin's map init only ever
         * looks at the first .mptbm_map_area/#mptbm_map_area in the document,
         * a hidden copy ahead of a real, visible one stops the visible one
         * from ever initializing. Stripping the hidden copy fixes that.
         * No-op (returns $html unchanged) when the form's map is visible.
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
                'jQuery("#mptbm_start_date").each('  => 'jQuery("' . $scope_class . ' #mptbm_start_date").each(',
                'jQuery("#mptbm_return_date").each(' => 'jQuery("' . $scope_class . ' #mptbm_return_date").each(',
            );

            return str_replace(array_keys($replacements), array_values($replacements), $html);
        }
    }
    new MPTBM_Dual_Booking_Shortcode();
}
