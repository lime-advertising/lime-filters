<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;

if (class_exists('LF_Elementor_Product_Info_Tabs_Widget')) {
    return;
}

if (!class_exists('\\Elementor\\Widget_Base')) {
    return;
}

class LF_Elementor_Product_Info_Tabs_Widget extends \Elementor\Widget_Base
{
    protected static $assets_enqueued = false;

    public function get_name()
    {
        return 'lf-product-info-tabs';
    }

    public function get_title()
    {
        return __('LF Product Info Tabs', 'lime-filters');
    }

    public function get_icon()
    {
        return 'eicon-tabs';
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

        $this->add_control('show_description', [
            'label' => __('Show Description Tab', 'lime-filters'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('description_tab_title', [
            'label' => __('Description Tab Title', 'lime-filters'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Overview', 'lime-filters'),
            'condition' => [
                'show_description' => 'yes',
            ],
        ]);

        $this->add_control('details_tab_title', [
            'label' => __('Product Info Tab Title', 'lime-filters'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Specifications', 'lime-filters'),
        ]);

        $this->add_control('notes_tab_title', [
            'label' => __('Installation Notes Title', 'lime-filters'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Installation Notes', 'lime-filters'),
        ]);

        $this->end_controls_section();
    }

    public function render()
    {
        $product = $this->get_current_product();
        if (!$product instanceof WC_Product) {
            echo '<div class="lf-product-info lf-product-info--empty">' . esc_html__('Product context not found.', 'lime-filters') . '</div>';
            return;
        }

        $settings = $this->get_settings_for_display();
        $tabs = [];

        $description_html = $this->get_description_html($product);
        if ($settings['show_description'] === 'yes' && $description_html !== '') {
            $tabs[] = [
                'key'   => 'description',
                'title' => $settings['description_tab_title'] ?: __('Overview', 'lime-filters'),
                'html'  => $description_html,
            ];
        }

        $details_html = $this->get_details_html($product, $settings);
        if ($details_html !== '') {
            $tabs[] = [
                'key'   => 'details',
                'title' => $settings['details_tab_title'] ?: __('Specifications', 'lime-filters'),
                'html'  => $details_html,
            ];
        }

        $notes_html = $this->get_notes_html($product, $settings);
        if ($notes_html !== '') {
            $tabs[] = [
                'key'   => 'installation',
                'title' => $settings['notes_tab_title'] ?: __('Installation Notes', 'lime-filters'),
                'html'  => $notes_html,
            ];
        }

        if (empty($tabs)) {
            echo '<div class="lf-product-info lf-product-info--empty">' . esc_html__('No additional product information available.', 'lime-filters') . '</div>';
            return;
        }

        $this->ensure_assets();

        $widget_id = 'lf-tabs-' . uniqid();

        echo '<div class="lf-product-info" id="' . esc_attr($widget_id) . '">';
        echo '<div class="lf-tabs" data-lf-tabs="1">';

        echo '<div class="lf-tabs__nav" role="tablist">';
        foreach ($tabs as $index => $tab) {
            $tab_id = $widget_id . '-' . $tab['key'];
            $classes = 'lf-tabs__tab' . (($index === 0) ? ' is-active' : '');
            printf(
                '<button type="button" class="%1$s" role="tab" data-tab-target="%2$s" aria-controls="%2$s-pane" aria-selected="%3$s">%4$s</button>',
                esc_attr($classes),
                esc_attr($tab_id),
                $index === 0 ? 'true' : 'false',
                esc_html($tab['title'])
            );
        }
        echo '</div>';

        echo '<div class="lf-tabs__panes">';
        foreach ($tabs as $index => $tab) {
            $tab_id = $widget_id . '-' . $tab['key'];
            $classes = 'lf-tabs__pane' . (($index === 0) ? ' is-active' : '');
            printf(
                '<div class="%1$s" id="%2$s-pane" role="tabpanel" data-tab-panel="%2$s">%3$s</div>',
                esc_attr($classes),
                esc_attr($tab_id),
                wp_kses_post($tab['html'])
            );
        }
        echo '</div>';

        echo '</div>';
        echo '</div>';
    }

    protected function ensure_assets()
    {
        if (self::$assets_enqueued) {
            return;
        }

        wp_enqueue_style('lime-filters');

        wp_enqueue_style(
            'lf-product-info-tabs',
            LF_PLUGIN_URL . 'includes/elementor/product-info/product-info-tabs.css',
            ['lime-filters'],
            LF_VERSION
        );

        wp_enqueue_script(
            'lf-product-info-tabs',
            LF_PLUGIN_URL . 'includes/elementor/product-info/product-info-tabs.js',
            [],
            LF_VERSION,
            true
        );

        self::$assets_enqueued = true;
    }

    protected function get_description_html(WC_Product $product)
    {
        $description = $product->get_description();
        if ($description === '') {
            return '';
        }

        $content = apply_filters('the_content', $description);
        if (trim(wp_strip_all_tags($content)) === '') {
            return '';
        }

        return '<div class="lf-product-info__description">' . $content . '</div>';
    }

    protected function get_details_html(WC_Product $product, array $settings)
    {
        if (!function_exists('get_field')) {
            return '';
        }

        $product_id = $product->get_id();
        $sections = [];

        $map = [
            'performance_group' => __('Performance', 'lime-filters'),
            'design_group'      => __('Design', 'lime-filters'),
            'details_group'     => __('Details', 'lime-filters'),
            'accessories_group' => __('Accessories', 'lime-filters'),
            'dimensions_group'  => __('Dimensions', 'lime-filters'),
            'notes_group'       => __('Notes', 'lime-filters'),
        ];

        foreach ($map as $field_key => $fallback_title) {
            $group = $this->get_acf_group($product_id, $field_key, 'column_heading', 'column_content');
            if (!$group) {
                continue;
            }

            $heading = isset($group['column_heading']) ? trim($group['column_heading']) : '';
            $content = isset($group['column_content']) ? $group['column_content'] : '';
            if ($content === '') {
                continue;
            }

            $rendered = apply_filters('the_content', $content);
            if (trim(wp_strip_all_tags($rendered)) === '') {
                continue;
            }

            $sections[] = [
                'title'   => $heading !== '' ? $heading : $fallback_title,
                'content' => $rendered,
            ];
        }

        if (empty($sections)) {
            return '';
        }

        ob_start();
        echo '<div class="lf-product-info__grid">';
        foreach ($sections as $section) {
            echo '<article class="lf-product-info__section">';
            if (!empty($section['title'])) {
                echo '<h3 class="lf-product-info__heading">' . esc_html($section['title']) . '</h3>';
            }
            echo '<div class="lf-product-info__content">' . $section['content'] . '</div>';
            echo '</article>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    protected function get_notes_html(WC_Product $product, array $settings)
    {
        if (!function_exists('get_field')) {
            return '';
        }

        $product_id = $product->get_id();

        $entries = [];
        $note_map = [
            'gas_connection'                    => __('Gas Connection', 'lime-filters'),
            'electrical_connection'             => __('Electrical Connection', 'lime-filters'),
            'installation_and_positioning_guidelines' => __('Installation & Positioning', 'lime-filters'),
        ];

        foreach ($note_map as $key => $fallback_title) {
            $item = $this->get_acf_group($product_id, 'installation_notes_' . $key, 'heading', 'content');
            if (!$item) {
                $notes_group = $this->get_acf_group($product_id, 'installation_notes');
                if (isset($notes_group[$key]) && is_array($notes_group[$key])) {
                    $item = $notes_group[$key];
                }
            }

            if (!is_array($item)) {
                continue;
            }

            $heading = isset($item['heading']) ? trim($item['heading']) : '';
            $content = isset($item['content']) ? $item['content'] : '';
            if ($content === '') {
                continue;
            }
            $rendered = apply_filters('the_content', $content);
            if (trim(wp_strip_all_tags($rendered)) === '') {
                continue;
            }
            $entries[] = [
                'title'   => $heading !== '' ? $heading : $fallback_title,
                'content' => $rendered,
            ];
        }

        if (empty($entries)) {
            return '';
        }

        ob_start();
        echo '<div class="lf-product-info__notes">';
        foreach ($entries as $entry) {
            echo '<section class="lf-product-info__note">';
            if (!empty($entry['title'])) {
                echo '<h4 class="lf-product-info__note-title">' . esc_html($entry['title']) . '</h4>';
            }
            echo '<div class="lf-product-info__note-content">' . $entry['content'] . '</div>';
            echo '</section>';
        }
        echo '</div>';
        return ob_get_clean();
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

    protected function get_acf_value($product_id, $key)
    {
        $value = null;
        if (function_exists('get_field')) {
            $value = get_field($key, $product_id);
        }

        if ($value === null || $value === false) {
            $meta = get_post_meta($product_id, $key, true);
            if ($meta !== '') {
                $value = $meta;
            }
        }

        return $value;
    }

    protected function get_acf_group($product_id, $group_key, $heading_key = '', $content_key = '')
    {
        $group = null;
        if (function_exists('get_field')) {
            $group = get_field($group_key, $product_id);
        }

        if (!is_array($group)) {
            $group = null;
        }

        if ($group === null) {
            $group = [];
            if ($heading_key !== '') {
                $meta_heading = get_post_meta($product_id, $group_key . '_' . $heading_key, true);
                if ($meta_heading !== '') {
                    $group[$heading_key] = $meta_heading;
                }
            }
            if ($content_key !== '') {
                $meta_content = get_post_meta($product_id, $group_key . '_' . $content_key, true);
                if ($meta_content !== '') {
                    $group[$content_key] = $meta_content;
                }
            }
            if (empty($group)) {
                return null;
            }
        }

        return $group;
    }
}
