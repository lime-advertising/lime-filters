<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;

if (class_exists('LF_Elementor_Product_Pricing_Widget')) {
    return;
}

if (!class_exists('\\Elementor\\Widget_Base')) {
    return;
}

class LF_Elementor_Product_Pricing_Widget extends \Elementor\Widget_Base
{
    public function get_name()
    {
        return 'lf-product-pricing';
    }

    public function get_title()
    {
        return __('LF Product Pricing', 'lime-filters');
    }

    public function get_icon()
    {
        return 'eicon-price-list';
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

        $this->add_control('show_regular', [
            'label' => __('Show Suggested Price', 'lime-filters'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('regular_label', [
            'label' => __('Suggested Label', 'lime-filters'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Suggested Price:', 'lime-filters'),
            'condition' => [
                'show_regular' => 'yes',
            ],
        ]);

        $this->add_control('show_starting', [
            'label' => __('Show Starting Price', 'lime-filters'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('starting_label', [
            'label' => __('Starting Label', 'lime-filters'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Starting At:', 'lime-filters'),
            'condition' => [
                'show_starting' => 'yes',
            ],
        ]);

        $this->add_control('hide_categories', [
            'label' => __('Hide In Categories', 'lime-filters'),
            'type' => Controls_Manager::SELECT2,
            'multiple' => true,
            'label_block' => true,
            'options' => $this->get_product_category_options(),
            'description' => __('Select product categories where this widget should not render.', 'lime-filters'),
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_style', [
            'label' => __('Style', 'lime-filters'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('label_color', [
            'label' => __('Label Color', 'lime-filters'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .lf-product-pricing__label' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('regular_value_color', [
            'label' => __('Suggested Price Color', 'lime-filters'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .lf-product-pricing__row--regular .lf-product-pricing__value' => 'color: {{VALUE}};',
            ],
            'condition' => [
                'show_regular' => 'yes',
            ],
        ]);

        $this->add_control('starting_value_color', [
            'label' => __('Starting Price Color', 'lime-filters'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .lf-product-pricing__row--starting .lf-product-pricing__value' => 'color: {{VALUE}};',
            ],
            'condition' => [
                'show_starting' => 'yes',
            ],
        ]);

        $this->end_controls_section();
    }

    public function render()
    {
        $product = $this->get_current_product();
        if (!$product instanceof WC_Product) {
            echo '<div class="lf-product-pricing lf-product-pricing--empty">' . esc_html__('Product context not found.', 'lime-filters') . '</div>';
            return;
        }

        wp_enqueue_style('lime-filters');

        wp_enqueue_style(
            'lf-product-pricing',
            LF_PLUGIN_URL . 'includes/elementor/product-pricing/product-pricing.css',
            ['lime-filters'],
            LF_VERSION
        );

        $settings = $this->get_settings_for_display();

        if ($this->is_hidden_for_product($product, $settings)) {
            return;
        }

        $prices = LF_Helpers::product_price_summary($product);

        $regular_html = $prices['regular'];
        $starting_html = $prices['sale'] !== '' ? $prices['sale'] : $prices['current'];

        $has_output = false;
        $wrapper_classes = ['lf-product-pricing', 'lf-product-pricing--widget'];
        echo '<div class="' . esc_attr(implode(' ', $wrapper_classes)) . '">';

        if ($settings['show_regular'] === 'yes' && $regular_html !== '') {
            $label = $settings['regular_label'] ?: __('Suggested Price:', 'lime-filters');
            echo '<div class="lf-product-pricing__row lf-product-pricing__row--regular">';
            echo '<span class="lf-product-pricing__label">' . esc_html($label) . '</span>';
            echo '<span class="lf-product-pricing__value">' . wp_kses_post($regular_html) . '</span>';
            echo '</div>';
            $has_output = true;
        }

        if ($settings['show_starting'] === 'yes' && $starting_html !== '') {
            $label = $settings['starting_label'] ?: __('Starting At:', 'lime-filters');
            echo '<div class="lf-product-pricing__row lf-product-pricing__row--starting">';
            echo '<span class="lf-product-pricing__label">' . esc_html($label) . '</span>';
            echo '<span class="lf-product-pricing__value">' . wp_kses_post($starting_html) . '</span>';
            echo '</div>';
            $has_output = true;
        }

        if (!$has_output) {
            echo '<div class="lf-product-pricing__row lf-product-pricing__row--empty">' . esc_html__('Pricing unavailable.', 'lime-filters') . '</div>';
        }

        echo '</div>';
    }

    protected function get_current_product()
    {
        global $product;
        if ($product instanceof WC_Product) {
            return $product;
        }

        $post = get_post();
        if ($post instanceof WP_Post && $post->post_type === 'product') {
            return wc_get_product($post->ID);
        }

        return null;
    }

    protected function get_product_category_options()
    {
        $terms = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'parent'     => 0,
        ]);

        $options = [];
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $options[$term->term_id] = $term->name;
            }
        }

        /**
         * Allow filtering the category options displayed in the widget control.
         *
         * @param array $options
         */
        return apply_filters('lf_product_pricing_category_options', $options);
    }

    protected function is_hidden_for_product(WC_Product $product, array $settings)
    {
        if (empty($settings['hide_categories']) || !is_array($settings['hide_categories'])) {
            return false;
        }

        $excluded = array_filter(array_map('intval', $settings['hide_categories']));
        if (empty($excluded)) {
            return false;
        }

        $product_terms = $product->get_category_ids();
        if (empty($product_terms)) {
            return false;
        }

        $all_terms = $product_terms;
        foreach ($product_terms as $term_id) {
            $ancestors = get_ancestors($term_id, 'product_cat');
            if (!empty($ancestors)) {
                $all_terms = array_merge($all_terms, $ancestors);
            }
        }
        $all_terms = array_unique(array_map('intval', $all_terms));

        return (bool) array_intersect($excluded, $all_terms);
    }
}
