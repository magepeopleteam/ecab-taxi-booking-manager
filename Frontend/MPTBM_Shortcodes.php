<?php
/*
* @Author        magePeople
* @Copyright     mage-people.com
*/
if (!defined('ABSPATH')) {
    die;
}

if (!class_exists('MPTBM_Shortcodes')) {

    class MPTBM_Shortcodes {

        public function __construct() {
            add_shortcode('mptbm_booking', array($this, 'mptbm_booking'));
        }

        public function mptbm_booking($attributes) {
            $defaults = $this->default_attribute();
            $params   = shortcode_atts($defaults, $attributes, 'mptbm_booking');

            // === Secure Sanitization ===
            $whitelists = [
                'tabs' => ['distance', 'hourly', 'manual'],
                'sort' => ['ASC', 'DESC'],
                'price_based' => ['dynamic', 'fixed'],
                'form' => ['horizontal', 'vertical'],
                'style' => ['list', 'grid'],
                'pagination-style' => ['load_more', 'numbered']
            ];

            foreach ($params as $key => $value) {
                $value = wp_strip_all_tags($value); // removes HTML and JS
                $value = trim($value);

                switch ($key) {
                    case 'cat':
                    case 'org':
                    case 'show':
                    case 'column':
                        $params[$key] = intval($value);
                        break;

                    case 'pagination':
                    case 'progressbar':
                    case 'map':
                    case 'tab':
                        // normalize true/false or yes/no
                        $bool = strtolower($value);
                        $params[$key] = in_array($bool, ['1', 'true', 'yes', 'on'], true) ? 'yes' : 'no';
                        break;

                    case 'tabs':
                        // Comma-separated whitelist validation
                        $items = array_filter(array_map('trim', explode(',', $value)));
                        $clean = [];
                        foreach ($items as $item) {
                            $item = sanitize_text_field($item);
                            if (in_array($item, $whitelists['tabs'], true)) {
                                $clean[] = $item;
                            }
                        }
                        $params[$key] = !empty($clean) ? implode(',', $clean) : $defaults['tabs'];
                        break;

                    case 'style':
                    case 'sort':
                    case 'form':
                    case 'price_based':
                    case 'pagination-style':
                        $params[$key] = (isset($whitelists[$key]) && in_array($value, $whitelists[$key], true))
                            ? $value
                            : $defaults[$key];
                        break;

                    case 'city':
                    case 'country':
                    case 'status':
                        $params[$key] = sanitize_text_field($value);
                        break;

                    default:
                        $params[$key] = sanitize_text_field($value);
                        break;
                }
            }
            // === End Sanitization ===

            // ✅ Respect tab control
            if ($params['tab'] !== 'yes') {
                $params['tabs'] = ''; // hide tabs if tab=no
            }

            ob_start();
            do_action('mptbm_transport_search', $params);
            return ob_get_clean();
        }

        public function default_attribute() {
            return [
                "cat" => "0",
                "org" => "0",
                "style" => 'list',
                "show" => '9',
                "pagination" => "yes",
                "city" => "",
                "country" => "",
                "sort" => 'ASC',
                "status" => '',
                "pagination-style" => "load_more",
                "column" => 3,
                "price_based" => 'dynamic',
                "progressbar" => 'yes',
                "map" => 'yes',
                "form" => 'horizontal',
                "tab" => 'no',
                "tabs" => 'distance,hourly,manual'
            ];
        }
    }

    new MPTBM_Shortcodes();
}
