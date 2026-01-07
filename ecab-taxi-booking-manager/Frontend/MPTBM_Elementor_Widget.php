<?php
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.

class MPTBM_Elementor_Widget extends \Elementor\Widget_Base {
    public function get_name() {
        return 'mptbm_booking';
    }

    public function get_title() {
        return esc_html__('E-Cab Booking Form', 'ecab-taxi-booking-manager');
    }

    public function get_icon() {
        return 'eicon-car-rental';
    }

    public function get_categories() {
        return ['mptbm'];
    }

    protected function register_controls() {
        // Display Settings
        $this->start_controls_section(
            'display_settings',
            [
                'label' => esc_html__('Display Settings', 'ecab-taxi-booking-manager'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'style',
            [
                'label' => esc_html__('Display Style', 'ecab-taxi-booking-manager'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'list',
                'options' => [
                    'list' => esc_html__('List', 'ecab-taxi-booking-manager'),
                    'grid' => esc_html__('Grid', 'ecab-taxi-booking-manager'),
                ],
            ]
        );

        $this->add_control(
            'show',
            [
                'label' => esc_html__('Items to Show', 'ecab-taxi-booking-manager'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 9,
                'min' => 1,
                'max' => 100,
            ]
        );

        $this->add_control(
            'column',
            [
                'label' => esc_html__('Columns', 'ecab-taxi-booking-manager'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => '3',
                'options' => [
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                ],
                'condition' => [
                    'style' => 'grid',
                ],
            ]
        );

        $this->end_controls_section();

        // Form Settings
        $this->start_controls_section(
            'form_settings',
            [
                'label' => esc_html__('Form Settings', 'ecab-taxi-booking-manager'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'price_based',
            [
                'label' => esc_html__('Price Based', 'ecab-taxi-booking-manager'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'dynamic',
                'options' => [
                    'dynamic' => esc_html__('Dynamic', 'ecab-taxi-booking-manager'),
                    'manual' => esc_html__('Manual', 'ecab-taxi-booking-manager'),
                    'fixed_hourly' => esc_html__('Fixed Hourly', 'ecab-taxi-booking-manager'),
                ],
            ]
        );

        $this->add_control(
            'form_layout',
            [
                'label' => esc_html__('Form Layout', 'ecab-taxi-booking-manager'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'horizontal',
                'options' => [
                    'horizontal' => esc_html__('Horizontal', 'ecab-taxi-booking-manager'),
                    'inline' => esc_html__('Inline', 'ecab-taxi-booking-manager'),
                ],
            ]
        );

        $this->end_controls_section();

        // Additional Features
        $this->start_controls_section(
            'additional_features',
            [
                'label' => esc_html__('Additional Features', 'ecab-taxi-booking-manager'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'pagination',
            [
                'label' => esc_html__('Show Pagination', 'ecab-taxi-booking-manager'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Yes', 'ecab-taxi-booking-manager'),
                'label_off' => esc_html__('No', 'ecab-taxi-booking-manager'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'pagination_style',
            [
                'label' => esc_html__('Pagination Style', 'ecab-taxi-booking-manager'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'load_more',
                'options' => [
                    'load_more' => esc_html__('Load More', 'ecab-taxi-booking-manager'),
                    'numeric' => esc_html__('Numeric', 'ecab-taxi-booking-manager'),
                ],
                'condition' => [
                    'pagination' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'progressbar',
            [
                'label' => esc_html__('Show Progress Bar', 'ecab-taxi-booking-manager'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Yes', 'ecab-taxi-booking-manager'),
                'label_off' => esc_html__('No', 'ecab-taxi-booking-manager'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'map',
            [
                'label' => esc_html__('Show Map', 'ecab-taxi-booking-manager'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Yes', 'ecab-taxi-booking-manager'),
                'label_off' => esc_html__('No', 'ecab-taxi-booking-manager'),
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'enable_tabs',
            [
                'label' => esc_html__('Enable Tabs', 'ecab-taxi-booking-manager'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => esc_html__('Yes', 'ecab-taxi-booking-manager'),
                'label_off' => esc_html__('No', 'ecab-taxi-booking-manager'),
                'default' => 'no',
            ]
        );

        $this->add_control(
            'tabs',
            [
                'label' => esc_html__('Tab Options', 'ecab-taxi-booking-manager'),
                'type' => \Elementor\Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => [
                    'distance' => esc_html__('Distance', 'ecab-taxi-booking-manager'),
                    'hourly' => esc_html__('Hourly', 'ecab-taxi-booking-manager'),
                    'manual' => esc_html__('Manual', 'ecab-taxi-booking-manager'),
                ],
                'default' => ['distance', 'hourly', 'manual'],
                'condition' => [
                    'enable_tabs' => 'yes',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        $shortcode_atts = [
            'style' => $settings['style'],
            'show' => $settings['show'],
            'pagination' => $settings['pagination'],
            'pagination-style' => $settings['pagination_style'],
            'column' => $settings['column'],
            'price_based' => $settings['price_based'],
            'progressbar' => $settings['progressbar'],
            'map' => $settings['map'],
            'form' => $settings['form_layout'],
            'tab' => $settings['enable_tabs'],
            'tabs' => is_array($settings['tabs']) ? implode(',', $settings['tabs']) : $settings['tabs']
        ];

        $shortcode_string = '[mptbm_booking';
        foreach ($shortcode_atts as $key => $value) {
            if (!empty($value)) {
                $shortcode_string .= ' ' . esc_attr($key) . '="' . esc_attr($value) . '"';
            }
        }
        $shortcode_string .= ']';

        echo do_shortcode($shortcode_string);
    }
}
