<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;

if (class_exists('LF_Elementor_Product_Attributes_Widget')) {
    return;
}

if (!class_exists('\Elementor\Widget_Base')) {
    return;
}

class LF_Elementor_Product_Attributes_Widget extends \Elementor\Widget_Base {
    public function get_name() {
        return 'lf-product-attributes';
    }

    public function get_title() {
        return __('LF Product Attributes', 'lime-filters');
    }

    public function get_icon() {
        return 'eicon-product-info';
    }

    public function get_categories() {
        return ['general'];
    }

    protected function register_controls() {
        $this->start_controls_section('section_content', [
            'label' => __('Content', 'lime-filters'),
        ]);

        $this->add_control('show_heading', [
            'label' => __('Show Heading', 'lime-filters'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'description' => __('Toggle to display or hide the block heading.', 'lime-filters'),
        ]);

        $this->add_control('heading_text', [
            'label' => __('Heading Text', 'lime-filters'),
            'type' => Controls_Manager::TEXT,
            'placeholder' => __('e.g. Product Details', 'lime-filters'),
            'default' => __('Product Details', 'lime-filters'),
            'label_block' => true,
            'description' => __('Enter the title shown above the attribute pills.', 'lime-filters'),
            'condition' => [
                'show_heading' => 'yes',
            ],
        ]);

        $this->add_control('attribute_layout', [
            'label' => __('Attribute Layout', 'lime-filters'),
            'type' => Controls_Manager::SELECT,
            'default' => 'list',
            'options' => [
                'list' => __('List', 'lime-filters'),
                'grid' => __('Grid', 'lime-filters'),
            ],
            'description' => __('Choose how the attribute pills should be arranged.', 'lime-filters'),
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_style_pills', [
            'label' => __('Pills', 'lime-filters'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('pill_bg_color', [
            'label' => __('Background Color', 'lime-filters'),
            'type' => Controls_Manager::COLOR,
            'description' => __('Customize the pill background to match your theme.', 'lime-filters'),
            'selectors' => [
                '{{WRAPPER}} .lf-pill' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('pill_text_color', [
            'label' => __('Text Color', 'lime-filters'),
            'type' => Controls_Manager::COLOR,
            'description' => __('Color used for the pill label text.', 'lime-filters'),
            'selectors' => [
                '{{WRAPPER}} .lf-pill' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'pill_border',
                'selector' => '{{WRAPPER}} .lf-pill',
                'description' => __('Optional border around each pill.', 'lime-filters'),
            ]
        );

        $this->add_responsive_control('pill_border_radius', [
            'label' => __('Border Radius', 'lime-filters'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'description' => __('Adjust the pill corner rounding per device.', 'lime-filters'),
            'selectors' => [
                '{{WRAPPER}} .lf-pill' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('pill_padding', [
            'label' => __('Padding', 'lime-filters'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'description' => __('Fine-tune internal spacing for each pill.', 'lime-filters'),
            'selectors' => [
                '{{WRAPPER}} .lf-pill' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('pill_gap', [
            'label' => __('Gap', 'lime-filters'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range' => [
                'px' => [
                    'min' => 0,
                    'max' => 60,
                ],
            ],
            'description' => __('Control the spacing between individual pills.', 'lime-filters'),
            'selectors' => [
                '{{WRAPPER}} .lf-attribute-group__pills' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_style_labels', [
            'label' => __('Labels', 'lime-filters'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('attribute_label_color', [
            'label' => __('Attribute Label Color', 'lime-filters'),
            'type' => Controls_Manager::COLOR,
            'description' => __('Set the color for the attribute group titles.', 'lime-filters'),
            'selectors' => [
                '{{WRAPPER}} .lf-attribute-group__label' => 'color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    public function render() {
        $product = $this->get_current_product();
        if (!$product instanceof WC_Product) {
            echo '<div class="lf-product-attributes lf-product-attributes--empty">' . esc_html__('Product context not found.', 'lime-filters') . '</div>';
            return;
        }

        wp_enqueue_style(
            'lf-product-attributes-widget',
            LF_PLUGIN_URL . 'includes/elementor/product-attributes/product-attributes.css',
            [],
            LF_VERSION
        );

        $settings = $this->get_settings_for_display();

        $is_variable = $product instanceof WC_Product_Variable;
        $attribute_layout = isset($settings['attribute_layout']) ? $settings['attribute_layout'] : 'list';
        $swatch_layout = isset($settings['swatch_layout']) ? $settings['swatch_layout'] : 'row';

        if ($product instanceof WC_Product_Variable) {
            return;
        }

        $content = $this->render_simple_attributes($product, $settings);

        if ($content === '') {
            echo '<div class="lf-product-attributes lf-product-attributes--empty">' . esc_html__('No attributes available for this product.', 'lime-filters') . '</div>';
            return;
        }

        $wrapper_classes = [
            'lf-product-attributes',
            'lf-product-attributes--simple',
            'lf-attributes-layout-' . sanitize_html_class($attribute_layout),
        ];

        ?>
        <div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>">
            <?php if ($settings['show_heading'] === 'yes' && !empty($settings['heading_text'])) : ?>
                <h3 class="lf-product-attributes__title"><?php echo esc_html($settings['heading_text']); ?></h3>
            <?php endif; ?>
            <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <?php
    }

    protected function render_simple_attributes(WC_Product $product, array $settings) {
        $attributes = $product->get_attributes();
        if (empty($attributes)) {
            return '';
        }

        $layout = isset($settings['attribute_layout']) ? $settings['attribute_layout'] : 'list';
        $wrapper_classes = ['lf-attribute-groups', 'lf-attribute-groups--pills'];
        $wrapper_classes[] = $layout === 'grid' ? 'lf-attribute-groups--grid' : 'lf-attribute-groups--list';

        LF_Product_Variants::record_frontend_product($product);

        $product_id  = $product->get_id();
        $variant_map = LF_Product_Variants::get_variants_for_product($product_id, $product);
        if (!is_array($variant_map)) {
            $variant_map = [];
        }
        $initial_selection = $this->initial_variant_selection($product, $variant_map);
        $term_variants = [];
        if (!empty($variant_map['variants']) && is_array($variant_map['variants'])) {
            foreach ($variant_map['variants'] as $variant) {
                if (empty($variant['attributes']) || !is_array($variant['attributes'])) {
                    continue;
                }
                foreach ($variant['attributes'] as $attr_slug => $term_slug) {
                    if (!isset($term_variants[$attr_slug])) {
                        $term_variants[$attr_slug] = [];
                    }
                    $term_variants[$attr_slug][$term_slug] = true;
                }
            }
        }

        ob_start();
        echo '<div class="' . esc_attr(implode(' ', $wrapper_classes)) . '" data-product-id="' . esc_attr($product_id) . '">';
        foreach ($attributes as $attribute) {
            if ($attribute->is_taxonomy()) {
                $orderby = wc_attribute_orderby($attribute->get_name());
                $term_args = [
                    'fields'     => 'all',
                    'hide_empty' => false,
                    'orderby'    => $orderby === 'menu_order' ? 'menu_order' : 'name',
                    'order'      => 'ASC',
                ];
                $terms = wc_get_product_terms($product->get_id(), $attribute->get_name(), $term_args);
                if (empty($terms)) {
                    continue;
                }
                $attr_slug = $attribute->get_name();
                $prepared_terms = [];
                $initial_slug = isset($initial_selection[$attr_slug]) ? $initial_selection[$attr_slug] : null;
                $first_active_slug = null;
                foreach ($terms as $term) {
                    $term_slug   = $term->slug;
                    $has_variant = isset($term_variants[$attr_slug][$term_slug]);
                    if ($initial_slug !== null && $term_slug === $initial_slug) {
                        $first_active_slug = $term_slug;
                    } elseif ($first_active_slug === null && $has_variant) {
                        $first_active_slug = $term_slug;
                    }
                    $prepared_terms[] = [
                        'slug'        => $term_slug,
                        'name'        => $term->name,
                        'has_variant' => $has_variant,
                    ];
                }
                if ($first_active_slug === null && $initial_slug !== null) {
                    $first_active_slug = $initial_slug;
                }
                echo '<div class="lf-attribute-group">';
                echo '<span class="lf-attribute-group__label">' . esc_html(wc_attribute_label($attr_slug)) . '</span>';
                echo '<div class="lf-attribute-group__pills" data-attribute="' . esc_attr($attr_slug) . '">';
                foreach ($prepared_terms as $term) {
                    $classes     = ['lf-pill'];
                    if ($term['has_variant']) {
                        $classes[] = 'has-variant';
                    }
                    if ($term['slug'] === $first_active_slug) {
                        $classes[] = 'is-active';
                    }
                    echo '<button type="button" class="' . esc_attr(implode(' ', $classes)) . '" title="' . esc_attr($term['name']) . '" data-product-id="' . esc_attr($product_id) . '" data-attribute="' . esc_attr($attr_slug) . '" data-term="' . esc_attr($term['slug']) . '" data-has-variant="' . ($term['has_variant'] ? '1' : '0') . '">' . esc_html($term['name']) . '</button>';
                }
                echo '</div></div>';
            } else {
                $options = $attribute->get_options();
                if (empty($options)) {
                    continue;
                }
                $attr_slug = $attribute->get_name();
                $prepared_options = [];
                $initial_slug = isset($initial_selection[$attr_slug]) ? $initial_selection[$attr_slug] : null;
                $first_active_slug = null;
                foreach ($options as $option) {
                    $term_slug   = sanitize_title($option);
                    $has_variant = isset($term_variants[$attr_slug][$term_slug]);
                    if ($initial_slug !== null && $term_slug === $initial_slug) {
                        $first_active_slug = $term_slug;
                    } elseif ($first_active_slug === null && $has_variant) {
                        $first_active_slug = $term_slug;
                    }
                    $prepared_options[] = [
                        'slug'        => $term_slug,
                        'name'        => $option,
                        'has_variant' => $has_variant,
                    ];
                }
                if ($first_active_slug === null && $initial_slug !== null) {
                    $first_active_slug = $initial_slug;
                }
                echo '<div class="lf-attribute-group">';
                echo '<span class="lf-attribute-group__label">' . esc_html($attribute->get_name()) . '</span>';
                echo '<div class="lf-attribute-group__pills" data-attribute="' . esc_attr($attr_slug) . '">';
                foreach ($prepared_options as $option) {
                    $classes     = ['lf-pill'];
                    if ($option['has_variant']) {
                        $classes[] = 'has-variant';
                    }
                    if ($option['slug'] === $first_active_slug) {
                        $classes[] = 'is-active';
                    }
                    echo '<button type="button" class="' . esc_attr(implode(' ', $classes)) . '" title="' . esc_attr($option['name']) . '" data-product-id="' . esc_attr($product_id) . '" data-attribute="' . esc_attr($attr_slug) . '" data-term="' . esc_attr($option['slug']) . '" data-has-variant="' . ($option['has_variant'] ? '1' : '0') . '">' . esc_html($option['name']) . '</button>';
                }
                echo '</div></div>';
            }
        }
        echo '</div>';
        return ob_get_clean();
    }

    protected function get_current_product() {
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

    protected function initial_variant_selection(WC_Product $product, array $variant_map)
    {
        $defaults = [];
        if (method_exists($product, 'get_default_attributes')) {
            $defaults = $product->get_default_attributes();
        }
        if (!is_array($defaults)) {
            $defaults = [];
        }

        if (!empty($defaults)) {
            return $defaults;
        }

        return [];
    }
}
