<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;

if (class_exists('LF_Elementor_Product_Affiliates_Widget')) {
    return;
}

if (!class_exists('\\Elementor\\Widget_Base')) {
    return;
}

class LF_Elementor_Product_Affiliates_Widget extends \Elementor\Widget_Base
{
    protected static $assets_enqueued = false;

    public function get_name()
    {
        return 'lf-product-affiliates';
    }

    public function get_title()
    {
        return __('LF Product Affiliates', 'lime-filters');
    }

    public function get_icon()
    {
        return 'eicon-cart-solid';
    }

    public function get_categories()
    {
        return ['general'];
    }

    protected function register_controls()
    {
        $stores = LF_Helpers::affiliate_stores();

        $this->start_controls_section('section_content', [
            'label' => __('Content', 'lime-filters'),
        ]);

        $this->add_control('show_title', [
            'label' => __('Show Title', 'lime-filters'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('title_text', [
            'label' => __('Title', 'lime-filters'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Available At:', 'lime-filters'),
            'condition' => [
                'show_title' => 'yes',
            ],
        ]);

        $this->add_control('desktop_columns', [
            'label' => __('Columns (Desktop)', 'lime-filters'),
            'type' => Controls_Manager::NUMBER,
            'min' => 1,
            'max' => 6,
            'step' => 1,
            'default' => 4,
        ]);

        $this->add_control('tablet_columns', [
            'label' => __('Columns (Tablet)', 'lime-filters'),
            'type' => Controls_Manager::NUMBER,
            'min' => 1,
            'max' => 4,
            'step' => 1,
            'default' => 3,
        ]);

        $this->add_control('mobile_columns', [
            'label' => __('Columns (Mobile)', 'lime-filters'),
            'type' => Controls_Manager::NUMBER,
            'min' => 1,
            'max' => 3,
            'step' => 1,
            'default' => 2,
        ]);

        $this->add_control('show_comparison', [
            'label' => __('Show Comparison Table', 'lime-filters'),
            'type' => Controls_Manager::SWITCHER,
            'default' => '',
        ]);

        $this->add_control('hide_categories', [
            'label' => __('Hide In Categories', 'lime-filters'),
            'type' => Controls_Manager::SELECT2,
            'multiple' => true,
            'label_block' => true,
            'options' => $this->get_product_category_options(),
            'description' => __('Select product categories where affiliate links should not appear.', 'lime-filters'),
        ]);

        if (!empty($stores)) {
            $repeater = new \Elementor\Repeater();
            $repeater->add_control('row_label', [
                'label' => __('Row Heading', 'lime-filters'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
            ]);

            foreach ($stores as $key => $meta) {
                $label = isset($meta['label']) ? $meta['label'] : ucfirst(str_replace('_', ' ', $key));
                $repeater->add_control('store_' . $key, [
                    'label' => sprintf(__('%s Value', 'lime-filters'), $label),
                    'type' => Controls_Manager::TEXT,
                    'default' => '',
                ]);
            }

            $this->add_control('comparison_rows', [
                'label' => __('Comparison Rows', 'lime-filters'),
                'type' => Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'title_field' => '{{{ row_label }}}',
                'condition' => [
                    'show_comparison' => 'yes',
                ],
            ]);
        }

        $this->end_controls_section();
    }

    public function render()
    {
        $product = $this->get_current_product();
        if (!$product instanceof WC_Product) {
            echo '<div class="lf-affiliates lf-affiliates--empty">' . esc_html__('Product context not found.', 'lime-filters') . '</div>';
            return;
        }

        $settings = $this->get_settings_for_display();

        if ($this->is_hidden_for_product($product, $settings)) {
            return;
        }

        $stores = LF_Helpers::affiliate_stores();
        if (empty($stores) || !is_array($stores)) {
            return;
        }

        $items = [];
        $product_id = $product->get_id();
        foreach ($stores as $key => $meta) {
            $url = LF_Helpers::affiliate_link($product_id, $key);
            if (!$url) {
                continue;
            }

            $label = isset($meta['label']) ? $meta['label'] : ucfirst(str_replace('_', ' ', $key));
            $logo  = isset($meta['logo']) ? $meta['logo'] : '';

            $items[] = [
                'store' => $key,
                'label' => $label,
                'logo'  => $logo,
                'url'   => $url,
            ];
        }

        if (empty($items)) {
            echo '<div class="lf-affiliates lf-affiliates--empty">' . esc_html__('No affiliate links configured for this product.', 'lime-filters') . '</div>';
            return;
        }

        $this->ensure_assets();

        $desktop = max(1, min(6, (int) $settings['desktop_columns']));
        $tablet  = max(1, min(4, (int) $settings['tablet_columns']));
        $mobile  = max(1, min(3, (int) $settings['mobile_columns']));

        $sku = $product->get_sku();
        $wrapper_classes = ['lf-affiliates'];

        $style = sprintf('--lf-affiliates-desktop:%1$d;--lf-affiliates-tablet:%2$d;--lf-affiliates-mobile:%3$d;', $desktop, $tablet, $mobile);

        echo '<div class="' . esc_attr(implode(' ', $wrapper_classes)) . '" style="' . esc_attr($style) . '">';

        if ($settings['show_title'] === 'yes' && !empty($settings['title_text'])) {
            echo '<h3 class="lf-affiliates__title">' . esc_html($settings['title_text']) . '</h3>';
        }

        $table_html = '';
        $show_table = ($settings['show_comparison'] === 'yes' && !empty($settings['comparison_rows']));
        if ($show_table) {
            $table_html = $this->render_comparison_table($settings['comparison_rows'], $items, $sku, $product_id);
            if ($table_html === '') {
                $show_table = false;
            }
        }

        if (!$show_table) {
            echo '<div class="lf-affiliates__grid">';
            foreach ($items as $item) {
                $label = $item['label'];
                $has_logo = $item['logo'] !== '';
                $button_classes = ['lf-affiliates__button', 'affiliate-button'];
                echo '<a class="' . esc_attr(implode(' ', $button_classes)) . '" href="' . esc_url($item['url']) . '" target="_blank" rel="nofollow noopener noreferrer" data-store="' . esc_attr($item['store']) . '" data-affiliate-link="1" data-product-id="' . esc_attr($product_id) . '"' . ($sku ? ' data-sku="' . esc_attr($sku) . '"' : '') . '>';
                if ($has_logo) {
                    echo '<span class="lf-affiliates__logo">';
                    echo '<img src="' . esc_url($item['logo']) . '" alt="' . esc_attr($label) . '" loading="lazy" />';
                    echo '</span>';
                    echo '<span class="lf-affiliates__label sr-only">' . esc_html($label) . '</span>';
                } else {
                    echo '<span class="lf-affiliates__label">' . esc_html($label) . '</span>';
                }
                echo '</a>';
            }
            echo '</div>';
        }

        if ($table_html !== '') {
            echo $table_html;
        }

        // Affiliate accessory modal container.
        $upsell_products = LF_Helpers::upsell_products_for($product, 4);
        $upsell_payload  = '';
        if (is_array($upsell_products)) {
            $encoded = wp_json_encode($upsell_products, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            if ($encoded !== false) {
                $upsell_payload = $encoded;
            }
        }

        echo '<div class="lf-affiliates-upsell-modal" data-upsell-modal data-product-id="' . esc_attr($product_id) . '" hidden>';
        echo '  <div class="lf-affiliates-upsell-modal__backdrop" data-upsell-close></div>';
        echo '  <div class="lf-affiliates-upsell-modal__dialog" role="dialog" aria-modal="true" tabindex="-1">';
        echo '    <div class="lf-affiliates-upsell-modal__content" data-upsell-content>';
        echo '      <!-- Accessory cards rendered via JS -->';
        echo '    </div>';
        echo '  </div>';
        if ($upsell_payload !== '') {
            echo '  <script type="application/json" data-upsell-json data-product-id="' . esc_attr($product_id) . '">' . $upsell_payload . '</script>';
        }
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
            'lf-product-affiliates',
            LF_PLUGIN_URL . 'includes/elementor/product-affiliates/product-affiliates.css',
            ['lime-filters'],
            LF_VERSION
        );

        wp_register_style(
            'lf-product-affiliates-modal',
            LF_PLUGIN_URL . 'includes/elementor/product-affiliates/product-affiliates-modal.css',
            ['lf-product-affiliates'],
            LF_VERSION
        );

        wp_register_script(
            'lf-product-affiliates-modal',
            LF_PLUGIN_URL . 'includes/elementor/product-affiliates/product-affiliates-modal.js',
            [],
            LF_VERSION,
            true
        );

        wp_enqueue_style('lf-product-affiliates-modal');
        wp_enqueue_script('lf-product-affiliates-modal');

        self::$assets_enqueued = true;
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
         * Filter the category options displayed in the affiliates widget.
         *
         * @param array $options
         */
        return apply_filters('lf_product_affiliates_category_options', $options);
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

    protected function render_comparison_table(array $rows, array $items, $sku, $product_id = null)
    {
        if (empty($rows) || empty($items)) {
            return '';
        }

        $store_keys = array_map(function($item){ return $item['store']; }, $items);

        $body_rows = [];
        foreach ($rows as $row) {
            $label = isset($row['row_label']) ? trim($row['row_label']) : '';
            $cells = [];
            foreach ($store_keys as $store_key) {
                $field = 'store_' . $store_key;
                $cells[] = isset($row[$field]) ? trim($row[$field]) : '';
            }

            if ($label === '' && !array_filter($cells)) {
                continue;
            }

            $body_rows[] = [
                'label' => $label,
                'cells' => $cells,
            ];
        }

        if (empty($body_rows)) {
            return '';
        }

        ob_start();
        echo '<div class="lf-affiliates__comparison">';
        echo '<table class="lf-affiliates__table">';
        echo '<thead><tr>';
        echo '<th scope="col" class="lf-affiliates__table-heading">' . esc_html__('Store', 'lime-filters') . '</th>';
        foreach ($items as $item) {
            $label = $item['label'];
            $logo  = $item['logo'];
            $href  = $item['url'];
            $store_key = $item['store'];
            $product_attr = $product_id ? ' data-product-id="' . esc_attr($product_id) . '"' : '';

            echo '<th scope="col" class="lf-affiliates__table-store">';
            echo '<a class="lf-affiliates__table-link affiliate-button" href="' . esc_url($href) . '" target="_blank" rel="nofollow noopener noreferrer" data-store="' . esc_attr($store_key) . '" data-affiliate-link="1"' . ($sku ? ' data-sku="' . esc_attr($sku) . '"' : '') . $product_attr . '>';
            if ($logo) {
                echo '<span class="lf-affiliates__table-logo"><img src="' . esc_url($logo) . '" alt="' . esc_attr($label) . '" loading="lazy" /></span>';
                echo '<span class="lf-affiliates__table-label sr-only">' . esc_html($label) . '</span>';
            } else {
                echo '<span class="lf-affiliates__table-label">' . esc_html($label) . '</span>';
            }
            echo '</a>';
            echo '</th>';
        }
        echo '</tr></thead>';

        echo '<tbody>';
        foreach ($body_rows as $row) {
            echo '<tr>';
            echo '<th scope="row" class="lf-affiliates__table-label">' . esc_html($row['label']) . '</th>';
            foreach ($row['cells'] as $value) {
                echo '<td class="lf-affiliates__table-cell">' . $this->format_cell_value($value) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody>';

        echo '</table>';
        echo '</div>';
        return ob_get_clean();
    }

    protected function format_cell_value($value)
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        $normalized = strtolower($raw);
        $yes_values = ['yes', 'y', 'true', '1', __('yes', 'lime-filters')];
        $no_values  = ['no', 'n', 'false', '0', __('no', 'lime-filters')];

        if (in_array($normalized, array_map('strtolower', $yes_values), true)) {
            $label = esc_html__('Yes', 'lime-filters');
            return '<span class="lf-affiliates__value lf-affiliates__value--yes"><span class="lf-affiliates__value-icon" aria-hidden="true">✓</span><span class="sr-only">' . $label . '</span></span>';
        }

        if (in_array($normalized, array_map('strtolower', $no_values), true)) {
            $label = esc_html__('No', 'lime-filters');
            return '<span class="lf-affiliates__value lf-affiliates__value--no"><span class="lf-affiliates__value-icon" aria-hidden="true">✕</span><span class="sr-only">' . $label . '</span></span>';
        }

        return '<span class="lf-affiliates__value-text">' . esc_html($raw) . '</span>';
    }
}
