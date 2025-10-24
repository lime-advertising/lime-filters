<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( class_exists('LF_Elementor_Filters_Widget') ) {
    return;
}

if ( ! class_exists('\Elementor\Widget_Base') ) {
    return;
}

class LF_Elementor_Filters_Widget extends \Elementor\Widget_Base {
    public function get_name() {
        return 'lime-filters';
    }

    public function get_title() {
        return __('Lime Filters','lime-filters');
    }

    public function get_icon() {
        return 'eicon-filter';
    }

    public function get_categories() {
        return ['general'];
    }

    protected function _register_controls() {
        $this->start_controls_section('section_settings', ['label'=>__('Settings','lime-filters')]);

        $this->add_control('layout', [
            'label' => __('Layout','lime-filters'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'sidebar',
            'options' => [
                'sidebar' => __('Sidebar','lime-filters'),
                'horizontal' => __('Horizontal','lime-filters'),
            ]
        ]);

        $this->add_control('show_counts', [
            'label' => __('Show Counts','lime-filters'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'label_on' => __('Yes','lime-filters'),
            'label_off'=> __('No','lime-filters'),
            'return_value' => 'yes',
            'default' => '',
        ]);

        $this->add_control('default', [
            'label' => __('Default State','lime-filters'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'collapsed',
            'options' => [
                'collapsed' => __('Collapsed','lime-filters'),
                'expanded'  => __('Expanded','lime-filters'),
            ]
        ]);

        $this->add_control('pagination', [
            'label' => __('Enable Pagination','lime-filters'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'label_on' => __('Yes','lime-filters'),
            'label_off'=> __('No','lime-filters'),
            'return_value' => 'yes',
            'default' => '',
        ]);

        $this->add_control('per_page', [
            'label' => __('Products Per Page','lime-filters'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'min' => 1,
            'max' => 48,
            'step' => 1,
            'default' => '',
            'description' => __('Leave empty to use WooCommerce defaults.','lime-filters'),
        ]);

        $column_options = [
            ''  => __('Inherit','lime-filters'),
            '1' => '1',
            '2' => '2',
            '3' => '3',
            '4' => '4',
            '5' => '5',
            '6' => '6',
        ];

        $this->add_responsive_control('columns', [
            'label' => __('Products Columns','lime-filters'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => $column_options,
            'default' => '',
            'tablet_default' => '',
            'mobile_default' => '',
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $atts = [
            'layout'     => $settings['layout'],
            'show_counts'=> $settings['show_counts'] ? 'yes' : 'no',
            'default'    => $settings['default'],
            'pagination' => $settings['pagination'] ? 'yes' : 'no',
        ];

        if (!empty($settings['per_page'])) {
            $atts['per_page'] = (int) $settings['per_page'];
        }

        $columns_desktop = isset($settings['columns']) ? $settings['columns'] : '';
        $columns_tablet  = isset($settings['columns_tablet']) ? $settings['columns_tablet'] : '';
        $columns_mobile  = isset($settings['columns_mobile']) ? $settings['columns_mobile'] : '';

        if ($columns_desktop !== '' && $columns_desktop !== 'inherit') {
            $atts['columns_desktop'] = $columns_desktop;
        }
        if ($columns_tablet !== '' && $columns_tablet !== 'inherit') {
            $atts['columns_tablet'] = $columns_tablet;
        }
        if ($columns_mobile !== '' && $columns_mobile !== 'inherit') {
            $atts['columns_mobile'] = $columns_mobile;
        }

        $parts = [];
        foreach ($atts as $key => $value) {
            $parts[] = $key . '="' . esc_attr($value) . '"';
        }

        echo do_shortcode('[lime_filters ' . implode(' ', $parts) . ']');
    }
}
