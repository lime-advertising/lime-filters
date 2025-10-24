<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;

if (class_exists('LF_Elementor_Recently_Viewed_Widget')) {
    return;
}

if (!class_exists('\\Elementor\\Widget_Base')) {
    return;
}

class LF_Elementor_Recently_Viewed_Widget extends \Elementor\Widget_Base
{
    protected static $assets_registered = false;

    public function get_name()
    {
        return 'lf-recently-viewed';
    }

    public function get_title()
    {
        return __('LF Recently Viewed', 'lime-filters');
    }

    public function get_icon()
    {
        return 'eicon-products';
    }

    public function get_categories()
    {
        return ['general'];
    }

    protected function register_controls()
    {
        $this->start_controls_section('section_content', [
            'label' => __('Content', 'lime-filters'),
        ]);

        $this->add_control('heading', [
            'label' => __('Heading', 'lime-filters'),
            'type'  => Controls_Manager::TEXT,
            'default' => __('Recently Viewed', 'lime-filters'),
        ]);

        $this->add_control('max_items', [
            'label'   => __('Max Products', 'lime-filters'),
            'type'    => Controls_Manager::NUMBER,
            'default' => 4,
            'min'     => 1,
            'max'     => 12,
        ]);

        $this->add_control('columns', [
            'label'   => __('Columns (Desktop)', 'lime-filters'),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                '2' => '2',
                '3' => '3',
                '4' => '4',
            ],
            'default' => '4',
        ]);

        $this->add_control('empty_message', [
            'label' => __('Empty Message', 'lime-filters'),
            'type'  => Controls_Manager::TEXT,
            'default' => __('Browse products to see them appear here.', 'lime-filters'),
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_style', [
            'label' => __('Style', 'lime-filters'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('heading_color', [
            'label' => __('Heading Color', 'lime-filters'),
            'type'  => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .lf-recently-viewed__heading' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    public function render()
    {
        self::ensure_assets();
        wp_enqueue_style('lf-recently-viewed');

        $settings = $this->get_settings_for_display();
        $max_items = isset($settings['max_items']) ? max(1, (int) $settings['max_items']) : 4;
        $columns = isset($settings['columns']) ? max(1, (int) $settings['columns']) : 4;

        $products = [];
        if (class_exists('LF_Helpers') && method_exists('LF_Helpers', 'recently_viewed_products')) {
            $products = LF_Helpers::recently_viewed_products($max_items);
        }

        if (empty($products) && $this->is_edit_mode()) {
            $products = $this->get_placeholder_products($max_items);
        }

        $has_products = !empty($products);
        $heading = isset($settings['heading']) && $settings['heading'] !== '' ? $settings['heading'] : '';
        $empty_message = isset($settings['empty_message']) ? $settings['empty_message'] : '';

        $wrapper_classes = ['lf-recently-viewed', 'lf-recently-viewed--columns-' . $columns];

        echo '<div class="' . esc_attr(implode(' ', $wrapper_classes)) . '" data-columns="' . esc_attr($columns) . '">';

        if ($heading !== '') {
            echo '<div class="lf-recently-viewed__header">';
            echo '<h3 class="lf-recently-viewed__heading">' . esc_html($heading) . '</h3>';
            echo '</div>';
        }

        if ($has_products) {
            echo '<div class="lf-recently-viewed__grid">';
            foreach ($products as $product) {
                $card = $this->render_product_card($product);
                if ($card !== '') {
                    echo '<div class="lf-recently-viewed__item">' . $card . '</div>';
                }
            }
            echo '</div>';
        } else {
            if ($empty_message !== '') {
                echo '<div class="lf-recently-viewed__empty">' . esc_html($empty_message) . '</div>';
            }
        }

        echo '</div>';
    }

    protected static function ensure_assets()
    {
        if (self::$assets_registered) {
            return;
        }

        wp_register_style(
            'lf-recently-viewed',
            LF_PLUGIN_URL . 'includes/elementor/recently-viewed/recently-viewed.css',
            ['lime-filters'],
            LF_VERSION
        );

        self::$assets_registered = true;
    }

    protected function render_product_card($product)
    {
        if ($product instanceof WC_Product) {
            if (class_exists('LF_AJAX') && method_exists('LF_AJAX', 'render_product_card')) {
                return LF_AJAX::render_product_card($product);
            }
        }

        $id = $product instanceof WC_Product ? $product->get_id() : 0;
        $title = $product instanceof WC_Product ? $product->get_name() : __('Sample Product', 'lime-filters');
        $permalink = $product instanceof WC_Product ? $product->get_permalink() : '#';
        return '<article class="lf-product lf-product--placeholder"><div class="lf-product__body"><h3 class="lf-product__title"><a href="' . esc_url($permalink) . '">' . esc_html($title) . '</a></h3></div></article>';
    }

    protected function get_placeholder_products($limit)
    {
        $items = [];
        $limit = max(1, (int) $limit);
        for ($i = 1; $i <= $limit; $i++) {
            $items[] = (object) [
                'ID' => 0,
                'post_title' => sprintf(__('Sample Product %d', 'lime-filters'), $i),
            ];
        }
        return $items;
    }

    protected function is_edit_mode()
    {
        if (!did_action('elementor/loaded')) {
            return false;
        }

        $plugin = \Elementor\Plugin::$instance;
        if (isset($plugin->editor) && method_exists($plugin->editor, 'is_edit_mode') && $plugin->editor->is_edit_mode()) {
            return true;
        }
        if (isset($plugin->preview) && method_exists($plugin->preview, 'is_preview_mode') && $plugin->preview->is_preview_mode()) {
            return true;
        }
        return false;
    }
}
