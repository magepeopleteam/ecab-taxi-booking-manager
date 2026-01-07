<?php
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

class MPTBM_Block {
    public function __construct() {
        add_action('init', array($this, 'register_booking_block'));
    }

    public function register_booking_block() {
        if (!function_exists('register_block_type')) {
            return;
        }

        wp_register_script(
            'mptbm-block-editor',
            MPTBM_PLUGIN_URL . '/assets/js/block.js',
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'),
            MPTBM_PLUGIN_VERSION
        );

        register_block_type('mptbm/booking', array(
            'editor_script' => 'mptbm-block-editor',
            'attributes' => array(
                'cat' => array(
                    'type' => 'string',
                    'default' => '0'
                ),
                'org' => array(
                    'type' => 'string',
                    'default' => '0'
                ),
                'style' => array(
                    'type' => 'string',
                    'default' => 'list'
                ),
                'show' => array(
                    'type' => 'string',
                    'default' => '9'
                ),
                'pagination' => array(
                    'type' => 'string',
                    'default' => 'yes'
                ),
                'city' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'country' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'sort' => array(
                    'type' => 'string',
                    'default' => 'ASC'
                ),
                'status' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'paginationStyle' => array(
                    'type' => 'string',
                    'default' => 'load_more'
                ),
                'column' => array(
                    'type' => 'string',
                    'default' => '3'
                ),
                'priceBased' => array(
                    'type' => 'string',
                    'default' => 'dynamic'
                ),
                'progressbar' => array(
                    'type' => 'string',
                    'default' => 'yes'
                ),
                'map' => array(
                    'type' => 'string',
                    'default' => 'yes'
                ),
                'form' => array(
                    'type' => 'string',
                    'default' => 'horizontal'
                ),
                'tab' => array(
                    'type' => 'string',
                    'default' => 'no'
                ),
                'tabs' => array(
                    'type' => 'string',
                    'default' => 'distance,hourly,manual'
                )
            ),
            'render_callback' => array($this, 'render_booking_block')
        ));
    }

    public function render_booking_block($attributes) {
        $shortcode_atts = array(
            'cat' => $attributes['cat'],
            'org' => $attributes['org'],
            'style' => $attributes['style'],
            'show' => $attributes['show'],
            'pagination' => $attributes['pagination'],
            'city' => $attributes['city'],
            'country' => $attributes['country'],
            'sort' => $attributes['sort'],
            'status' => $attributes['status'],
            'pagination-style' => $attributes['paginationStyle'],
            'column' => $attributes['column'],
            'price_based' => $attributes['priceBased'],
            'progressbar' => $attributes['progressbar'],
            'map' => $attributes['map'],
            'form' => $attributes['form'],
            'tab' => $attributes['tab'],
            'tabs' => $attributes['tabs']
        );

        $shortcode_attributes = array_map(function($key, $value) {
            return sprintf('%s="%s"', $key, esc_attr($value));
        }, array_keys($shortcode_atts), $shortcode_atts);

        return do_shortcode(sprintf(
            '[mptbm_booking %s]',
            implode(' ', $shortcode_attributes)
        ));
    }
}

new MPTBM_Block();
