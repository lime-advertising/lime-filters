<?php
if (!defined('ABSPATH')) {
    exit;
}

class LF_Product_Compare {
    const OPTION_KEY = 'lime_filters_compare_settings';
    const MENU_SLUG  = 'lime-filters-compare';
    const AJAX_ACTION = 'lf_get_compare_data';

    protected static $settings = null;

    public static function init() {
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_menu', [__CLASS__, 'register_menu']);

        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('woocommerce_after_shop_loop_item', [__CLASS__, 'render_loop_buttons'], 20);
        add_action('wp_footer', [__CLASS__, 'render_modal']);

        add_action('wp_ajax_' . self::AJAX_ACTION, [__CLASS__, 'ajax_get_compare_data']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, [__CLASS__, 'ajax_get_compare_data']);

        add_shortcode('compare_button', [__CLASS__, 'shortcode_compare_button']);
        add_shortcode('compare_icon', [__CLASS__, 'shortcode_compare_icon']);
    }

    public static function register_settings() {
        register_setting(
            'lime_filters_compare_group',
            self::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [__CLASS__, 'sanitize_options'],
                'default'           => self::defaults(),
            ]
        );
    }

    public static function register_menu() {
        add_submenu_page(
            'woocommerce',
            __('Product Compare', 'lime-filters'),
            __('Product Compare', 'lime-filters'),
            'manage_woocommerce',
            self::MENU_SLUG,
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function render_settings_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $settings = self::settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Product Compare', 'lime-filters'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('lime_filters_compare_group');
                ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable Compare Buttons', 'lime-filters'); ?></th>
                            <td>
                                <label>
                                    <input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_compare]" value="no" />
                                    <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_compare]" value="yes" <?php checked($settings['enable_compare'], 'yes'); ?> />
                                    <?php esc_html_e('Show compare buttons on catalog product cards.', 'lime-filters'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Product Card CSS Class', 'lime-filters'); ?></th>
                            <td>
                                <input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[product_card_class]" value="<?php echo esc_attr($settings['product_card_class']); ?>" placeholder="e.g. lf-product" />
                                <p class="description"><?php esc_html_e('Optional. Only show compare buttons on cards containing this CSS class. Leave blank to show on all catalog products.', 'lime-filters'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public static function enqueue_assets() {
        if (!self::is_enabled()) {
            return;
        }

        wp_enqueue_style(
            'lf-product-compare',
            LF_PLUGIN_URL . 'includes/compare/compare.css',
            [],
            LF_VERSION
        );

        wp_enqueue_script(
            'lf-product-compare',
            LF_PLUGIN_URL . 'includes/compare/compare.js',
            ['jquery'],
            LF_VERSION,
            true
        );

        $settings = self::settings();

        wp_localize_script('lf-product-compare', 'LimeFiltersCompare', [
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'action'    => self::AJAX_ACTION,
            'cardClass' => $settings['product_card_class'],
            'maxItems'  => self::max_items(),
            'labels'    => [
                'compare' => __('Compare', 'lime-filters'),
                'view'    => __('View Comparison', 'lime-filters'),
            ],
            'i18n'      => [
                'errorLoading' => __('Error loading compare data.', 'lime-filters'),
            ],
        ]);
    }

    public static function render_loop_buttons() {
        if (!self::is_enabled()) {
            return;
        }

        if (is_product()) {
            return;
        }

        global $product;
        if (!$product instanceof WC_Product) {
            return;
        }

        echo self::button_group_markup($product); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public static function render_modal() {
        if (!self::is_enabled()) {
            return;
        }
        ?>
        <div id="wcp-compare-modal" class="lf-compare-modal" style="display:none;">
            <div class="wcp-overlay lf-compare-modal__overlay"></div>
            <div class="wcp-content lf-compare-modal__content">
                <button class="wcp-close-compare" type="button" aria-label="<?php esc_attr_e('Close compare modal', 'lime-filters'); ?>">&times;</button>
                <div id="wcp-compare-table-container"></div>
            </div>
        </div>
        <?php
    }

    public static function ajax_get_compare_data() {
        if (!self::is_enabled()) {
            wp_send_json_error(__('Compare feature is disabled.', 'lime-filters'));
        }

        $product_ids = isset($_POST['product_ids']) ? array_map('absint', (array) $_POST['product_ids']) : [];
        $product_ids = array_values(array_filter($product_ids));

        if (empty($product_ids)) {
            wp_send_json_error(__('Select at least one product.', 'lime-filters'));
        }

        $limit = self::max_items();
        if ($limit > 0 && count($product_ids) > $limit) {
            $product_ids = array_slice($product_ids, 0, $limit);
        }

        $products = [];
        foreach ($product_ids as $id) {
            $product = wc_get_product($id);
            if ($product instanceof WC_Product) {
                $products[$id] = $product;
            }
        }

        if (empty($products)) {
            wp_send_json_error(__('No valid products found.', 'lime-filters'));
        }

        ob_start();
        self::render_table($products);
        $html = ob_get_clean();

        wp_send_json_success($html);
    }

    public static function shortcode_compare_button() {
        if (!self::is_enabled()) {
            return '';
        }

        global $product;
        if (!$product instanceof WC_Product) {
            return '';
        }

        return self::button_markup($product);
    }

    public static function shortcode_compare_icon() {
        if (!self::is_enabled()) {
            return '';
        }

        return '<span class="wcp-shortcode-icon"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" stroke-width="0.00024000000000000003"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><path d="M1,8A1,1,0,0,1,2,7H9.586L7.293,4.707A1,1,0,1,1,8.707,3.293l4,4a1,1,0,0,1,0,1.414l-4,4a1,1,0,1,1-1.414-1.414L9.586,9H2A1,1,0,0,1,1,8Zm21,7H14.414l2.293-2.293a1,1,0,0,0-1.414-1.414l-4,4a1,1,0,0,0,0,1.414l4,4a1,1,0,0,0,1.414-1.414L14.414,17H22a1,1,0,0,0,0-2Z"></path></g></svg><span class="wcp-count">0</span></span>';
    }

    public static function button_group_markup(WC_Product $product) {
        $view_label = esc_html__('View Product', 'lime-filters');
        $compare_button = self::button_markup($product);

        if ($compare_button === '') {
            return '';
        }

        $view_link = sprintf(
            '<a href="%s" class="wcp-view-product">%s</a>',
            esc_url(get_permalink($product->get_id())),
            esc_html($view_label)
        );

        return sprintf(
            '<div class="wcp-button-group lf-compare-buttons">%s%s</div>',
            $view_link,
            $compare_button
        );
    }

    public static function button_markup(WC_Product $product) {
        $label = self::button_label($product);
        if ($label === '') {
            return '';
        }

        return sprintf(
            '<button class="wcp-compare-button" type="button" data-product-id="%d">%s</button>',
            (int) $product->get_id(),
            $label
        );
    }

    public static function is_enabled() {
        $settings = self::settings();
        return $settings['enable_compare'] === 'yes';
    }

    protected static function settings() {
        if (self::$settings !== null) {
            return self::$settings;
        }

        $options = get_option(self::OPTION_KEY, null);
        if ($options === null || $options === false) {
            $legacy = get_option('wcp_settings', null);
            if (is_array($legacy)) {
                $options = [
                    'enable_compare'     => !empty($legacy['enable_compare']) ? 'yes' : 'no',
                    'product_card_class' => sanitize_text_field($legacy['product_card_class'] ?? ''),
                    'compare_button_label' => isset($legacy['compare_button_label']) ? wp_kses_post($legacy['compare_button_label']) : '',
                ];
                $options = self::sanitize_options($options);
                update_option(self::OPTION_KEY, $options);
            }
        }

        if (!is_array($options)) {
            $options = [];
        }

        $options = self::sanitize_options($options);
        $settings = wp_parse_args($options, self::defaults());

        self::$settings = $settings;
        return $settings;
    }

    public static function sanitize_options($options) {
        if (!is_array($options)) {
            return self::defaults();
        }

        $enabled = isset($options['enable_compare']) && $options['enable_compare'] === 'yes' ? 'yes' : 'no';
        $card_class = isset($options['product_card_class']) ? sanitize_text_field($options['product_card_class']) : '';
        $label = isset($options['compare_button_label']) ? self::sanitize_label($options['compare_button_label']) : '';

        return [
            'enable_compare'       => $enabled,
            'product_card_class'   => $card_class,
            'compare_button_label' => $label,
        ];
    }

    protected static function defaults() {
        return [
            'enable_compare'       => 'yes',
            'product_card_class'   => '',
            'compare_button_label' => '',
        ];
    }

    protected static function button_label(WC_Product $product) {
        $settings = self::settings();
        $label = $settings['compare_button_label'];

        if ($label === '') {
            $label = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" stroke-width="0.00024000000000000003"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><path d="M1,8A1,1,0,0,1,2,7H9.586L7.293,4.707A1,1,0,1,1,8.707,3.293l4,4a1,1,0,0,1,0,1.414l-4,4a1,1,0,1,1-1.414-1.414L9.586,9H2A1,1,0,0,1,1,8Zm21,7H14.414l2.293-2.293a1,1,0,0,0-1.414-1.414l-4,4a1,1,0,0,0,0,1.414l4,4a1,1,0,0,0,1.414-1.414L14.414,17H22a1,1,0,0,0,0-2Z"></path></g></svg>';
        }

        $label = apply_filters('lime_filters_compare_button_label', $label, $product);

        return self::sanitize_label($label);
    }

    protected static function render_table(array $products) {
        $product_ids = array_keys($products);
        ?>
        <div class="wcp-compare-header lf-compare-header">
            <h3><?php esc_html_e('Compare Products', 'lime-filters'); ?></h3>
            <div class="wcp-swipe-hint lf-compare-hint"><span><?php esc_html_e('Swipe right to view more →', 'lime-filters'); ?></span></div>
            <button id="wcp-clear-all" class="wcp-clear-all" type="button"><?php esc_html_e('Clear All', 'lime-filters'); ?></button>
        </div>
        <div class="wcp-table-scroll lf-compare-scroll">
            <table class="wcp-compare-table lf-compare-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Feature', 'lime-filters'); ?></th>
                        <?php foreach ($product_ids as $id) :
                            $product = $products[$id];
                            ?>
                            <th>
                                <a href="<?php echo esc_url(get_permalink($id)); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($product->get_name()); ?></a><br />
                                <button class="wcp-remove-item" type="button" data-remove-id="<?php echo esc_attr($id); ?>"><?php esc_html_e('Remove', 'lime-filters'); ?></button>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php self::render_feature_rows($products); ?>
                    <?php self::render_attribute_rows($products); ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    protected static function render_feature_rows(array $products) {
        $rows = [
            __('Image', 'lime-filters') => function (WC_Product $product) {
                $link = get_permalink($product->get_id());
                return sprintf('<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', esc_url($link), $product->get_image());
            },
            __('Price', 'lime-filters') => function (WC_Product $product) {
                return self::price_row_markup($product);
            },
            __('Category', 'lime-filters') => function (WC_Product $product) {
                return wc_get_product_category_list($product->get_id());
            },
            __('SKU', 'lime-filters') => function (WC_Product $product) {
                return esc_html($product->get_sku());
            },
            __('Available in', 'lime-filters') => function (WC_Product $product) {
                return self::affiliate_buttons($product);
            },
        ];

        foreach ($rows as $label => $callback) {
            echo '<tr>';
            echo '<td class="wcp-heading-cell"><strong>' . esc_html($label) . '</strong></td>';
            foreach ($products as $product) {
                $value = call_user_func($callback, $product);
                if ($value === '' || $value === null) {
                    $value = '-';
                }
                echo '<td>' . wp_kses_post($value) . '</td>';
            }
            echo '</tr>';
        }
    }

    protected static function render_attribute_rows(array $products) {
        $attributes = [];

        foreach ($products as $product) {
            foreach ($product->get_attributes() as $attribute) {
                if (!$attribute instanceof WC_Product_Attribute) {
                    continue;
                }
                $name  = $attribute->get_name();
                $label = wc_attribute_label($name);
                $attributes[$name] = $label;
            }
        }

        foreach ($attributes as $taxonomy => $label) {
            echo '<tr>';
            echo '<td class="wcp-heading-cell"><strong>' . esc_html($label) . '</strong></td>';

            foreach ($products as $product) {
                $value = self::attribute_values($product, $taxonomy);
                echo '<td>' . wp_kses_post($value) . '</td>';
            }

            echo '</tr>';
        }
    }

    protected static function attribute_values(WC_Product $product, $taxonomy) {
        $attributes = $product->get_attributes();
        if (!isset($attributes[$taxonomy])) {
            return '-';
        }

        $attribute = $attributes[$taxonomy];
        if ($attribute instanceof WC_Product_Attribute && $attribute->is_taxonomy()) {
            $terms = wc_get_product_terms($product->get_id(), $taxonomy, ['fields' => 'names']);
            if (!empty($terms)) {
                return esc_html(implode(', ', $terms));
            }
            return '-';
        }

        if ($attribute instanceof WC_Product_Attribute) {
            $options = $attribute->get_options();
            if (!empty($options)) {
                $formatted = array_map(function ($option) {
                    return is_string($option) ? esc_html($option) : '';
                }, $options);
                $formatted = array_filter($formatted);
                if (!empty($formatted)) {
                    return implode(', ', $formatted);
                }
            }
        }

        $raw = wc_get_product_attribute_list($product->get_id(), $taxonomy);
        if (is_string($raw) && $raw !== '') {
            return wp_kses_post($raw);
        }

        return '-';
    }

    protected static function affiliate_buttons(WC_Product $product) {
        if (!class_exists('LF_Helpers')) {
            return '';
        }

        $stores = LF_Helpers::affiliate_stores();
        if (empty($stores)) {
            return '-';
        }

        $buttons = [];
        $sku = $product->get_sku();

        foreach ($stores as $key => $store) {
            $url = LF_Helpers::affiliate_link($product->get_id(), $key);
            if (!$url) {
                continue;
            }

            $buttons[] = self::store_logo_markup($key, $store, $url, $sku);
        }

        if (empty($buttons)) {
            return '-';
        }

        return implode(' ', $buttons);
    }

    protected static function store_logo_markup($key, array $store, $url, $sku) {
        $attrs = sprintf(
            'class="affiliate-button" data-store="%s" data-sku="%s"',
            esc_attr($key),
            esc_attr($sku)
        );

        $logo = isset($store['logo']) ? esc_url($store['logo']) : '';
        $label = isset($store['label']) ? $store['label'] : $key;

        if ($logo !== '') {
            $img = sprintf('<img class="affiliate_img" src="%s" alt="%s" style="max-height: 30px;" />', $logo, esc_attr($label));
            return sprintf('<a href="%s" target="_blank" rel="nofollow" %s>%s</a>', esc_url($url), $attrs, $img);
        }

        return sprintf('<a href="%s" target="_blank" rel="nofollow" %s>%s</a>', esc_url($url), $attrs, esc_html($label));
    }

    protected static function max_items() {
        $limit = (int) apply_filters('lime_filters_compare_max_items', 4);
        return $limit > 0 ? $limit : 4;
    }

    protected static function sanitize_label($label) {
        $allowed = wp_kses_allowed_html('post');
        $allowed['svg'] = [
            'class' => true,
            'xmlns' => true,
            'viewbox' => true,
            'viewBox' => true,
            'width' => true,
            'height' => true,
            'fill' => true,
            'stroke' => true,
            'stroke-width' => true,
            'stroke-linecap' => true,
            'stroke-linejoin' => true,
        ];
        $allowed['g'] = [
            'id' => true,
            'stroke-width' => true,
            'stroke-linecap' => true,
            'stroke-linejoin' => true,
        ];
        $allowed['path'] = [
            'd' => true,
            'fill' => true,
            'stroke' => true,
            'stroke-width' => true,
            'stroke-linecap' => true,
            'stroke-linejoin' => true,
        ];

        return wp_kses($label, $allowed);
    }

    protected static function price_row_markup(WC_Product $product) {
        if (!class_exists('LF_Helpers')) {
            return $product->get_price_html();
        }

        $summary = LF_Helpers::product_price_summary($product);
        if (!is_array($summary)) {
            $summary = [];
        }

        $regular = isset($summary['regular']) ? $summary['regular'] : '';
        $sale    = isset($summary['sale']) ? $summary['sale'] : '';
        $current = isset($summary['current']) ? $summary['current'] : '';

        $primary = '';
        $has_sale = false;

        if ($sale !== '' && $sale !== $regular) {
            $primary = $sale;
            $has_sale = true;
        } elseif ($current !== '') {
            $primary = $current;
        } else {
            $primary = $regular;
        }

        if ($primary === '') {
            return $product->get_price_html();
        }

        $rows = [];

        if ($regular !== '' && $regular !== $primary) {
            $rows[] = sprintf(
                '<div class="lf-compare-price__row"><span class="lf-compare-price__label">%s</span><span class="lf-compare-price__value">%s</span></div>',
                esc_html__('Suggested price', 'lime-filters'),
                $regular
            );
        }

        $rows[] = sprintf(
            '<div class="lf-compare-price__row lf-compare-price__row--highlight"><span class="lf-compare-price__label">%s</span><span class="lf-compare-price__value">%s</span></div>',
            esc_html__('Starting at', 'lime-filters'),
            $primary
        );

        return sprintf('<div class="lf-compare-price">%s</div>', implode('', $rows));
    }
}
