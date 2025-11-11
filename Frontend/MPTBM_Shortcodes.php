<?php
/*
* @Author        magePeople
* @Copyright     mage-people.com
*/
if ( ! defined( 'ABSPATH' ) ) {
    die;
}

if ( ! class_exists( 'MPTBM_Shortcodes' ) ) {

    class MPTBM_Shortcodes {

        public function __construct() {
            add_shortcode( 'mptbm_booking', array( $this, 'mptbm_booking' ) );
        }

        public function mptbm_booking( $attribute ) {
            $defaults = $this->default_attribute();
            $params   = shortcode_atts( $defaults, $attribute, 'mptbm_booking' );

            // === Secure sanitization section ===
            $whitelists = [
                'tabs' => ['distance', 'hourly', 'manual'],
                'sort' => ['ASC', 'DESC'],
                'price_based' => ['dynamic', 'fixed'],
                'form' => ['horizontal', 'vertical'],
                'style' => ['list', 'grid'],
                'pagination-style' => ['load_more', 'numbered']
            ];

            $types = [
                'cat' => 'int',
                'org' => 'int',
                'style' => 'listtype',
                'show' => 'int',
                'pagination' => 'bool',
                'city' => 'text',
                'country' => 'text',
                'sort' => 'listtype',
                'status' => 'text',
                'pagination-style' => 'listtype',
                'column' => 'int',
                'price_based' => 'listtype',
                'progressbar' => 'bool',
                'map' => 'slug',
                'form' => 'listtype',
                'tab' => 'bool',
                'tabs' => 'list',
            ];

            foreach ( $params as $key => $value ) {

                if ( ! isset( $types[ $key ] ) ) {
                    $params[ $key ] = sanitize_text_field( $value );
                    continue;
                }

                switch ( $types[ $key ] ) {

                    case 'int':
                        $params[ $key ] = intval( $value );
                        break;

                    case 'bool':
                        // Normalize to 'yes' or 'no' so old code that checks for 'yes' continues to work.
                        if ( function_exists( 'wp_validate_boolean' ) ) {
                            $is_true = wp_validate_boolean( $value );
                        } else {
                            $is_true = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
                        }
                        $params[ $key ] = $is_true ? 'yes' : 'no';
                        break;

                    case 'slug':
                        // Allow only safe alphanumeric, dash and underscore
                        $value = sanitize_text_field( $value );
                        $params[ $key ] = preg_replace( '/[^A-Za-z0-9_-]/', '', $value );
                        break;

                    case 'list':
                        // e.g. tabs="distance,hourly,manual"
                        // preserve user's order, but validate items against whitelist (if present)
                        $items = array_filter( array_map( 'trim', explode( ',', $value ) ) );
                        $clean = [];
                        foreach ( $items as $item ) {
                            $item = sanitize_text_field( $item );
                            if ( isset( $whitelists[ $key ] ) ) {
                                if ( in_array( $item, $whitelists[ $key ], true ) ) {
                                    $clean[] = $item;
                                }
                            } else {
                                // if no whitelist provided, accept sanitized item
                                $clean[] = $item;
                            }
                        }
                        // If no valid items left, fall back to default
                        if ( empty( $clean ) && isset( $defaults[ $key ] ) ) {
                            $params[ $key ] = $defaults[ $key ];
                        } else {
                            $params[ $key ] = implode( ',', $clean );
                        }
                        break;

                    case 'listtype':
                        // single value but must match whitelist; fallback to original default if invalid
                        $value = sanitize_text_field( $value );
                        if ( isset( $whitelists[ $key ] ) && in_array( $value, $whitelists[ $key ], true ) ) {
                            $params[ $key ] = $value;
                        } else {
                            // keep the original default for this key (safer than guessing)
                            $params[ $key ] = isset( $defaults[ $key ] ) ? $defaults[ $key ] : ( $whitelists[ $key ][0] ?? '' );
                        }
                        break;

                    case 'text':
                    default:
                        $params[ $key ] = sanitize_text_field( $value );
                        break;
                }
            }
            // === End sanitization ===

            ob_start();
            do_action( 'mptbm_transport_search', $params );
            return ob_get_clean();
        }

        public function default_attribute() {
            return array(
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
                'progressbar' => 'yes',
                'map' => 'yes',
                'form' => 'horizontal',
                'tab' => 'no',
                'tabs' => 'distance,hourly,manual'
            );
        }
    }

    new MPTBM_Shortcodes();
}
