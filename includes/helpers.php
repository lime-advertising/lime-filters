<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class LF_Helpers {
    public static function colors() {
        $colors = get_option('lime_filters_brand_colors', []);
        $defaults = [
            'accent'     => '#009688',
            'border'     => '#E0E0E0',
            'background' => '#FFFFFF',
            'text'       => '#222222',
        ];
        return wp_parse_args($colors, $defaults);
    }

    public static function mapping() {
        $map = get_option('lime_filters_map', []);
        if (!is_array($map)) {
            $map = [];
        }

        $normalized = [];
        foreach ($map as $key => $value) {
            $slug = is_string($key) ? $key : '';
            if ($slug === '') {
                continue;
            }
            if (is_string($value)) {
                $value = array_filter(array_map('trim', explode(',', $value)));
            } elseif (is_array($value)) {
                $value = array_filter(array_map('trim', $value));
            } else {
                $value = [];
            }

            $value = array_map([__CLASS__, 'sanitize_attr_tax'], $value);

            $value = array_values(array_unique($value));
            $normalized[$slug] = $value;
        }

        return $normalized;
    }

    public static function current_category_slug() {
        if (is_product_category()) {
            $term = get_queried_object();
            return $term ? $term->slug : '';
        }
        // Allow override via shortcode attr `category`
        return '';
    }

    public static function get_attr_terms($taxonomy) {
        $args = ['hide_empty' => false];
        $terms = get_terms($taxonomy, $args);
        if (is_wp_error($terms)) return [];
        return $terms;
    }

    public static function sanitize_attr_tax($attr) {
        $attr = wc_sanitize_taxonomy_name($attr);
        if (strpos($attr, 'pa_') !== 0) $attr = 'pa_' . $attr;
        return $attr;
    }

    public static function placeholder_image_url() {
        $path = LF_PLUGIN_DIR . 'includes/assets/images/Kucht Placeholder.png';
        if (!file_exists($path)) {
            return wc_placeholder_img_src();
        }
        return LF_PLUGIN_URL . 'includes/assets/images/Kucht Placeholder.png';
    }

    public static function attributes_for_context($context_slug) {
        $map = self::mapping();
        $attrs = [];
        $explicit = false;

        if ($context_slug !== '') {
            $slug = $context_slug;
            $visited = [];

            while ($slug !== '' && !isset($visited[$slug])) {
                if (array_key_exists($slug, $map)) {
                    $attrs = (array) $map[$slug];
                    $explicit = true;
                    break;
                }

                $visited[$slug] = true;

                $term = get_term_by('slug', $slug, 'product_cat');
                if ($term && !is_wp_error($term) && $term->parent > 0) {
                    $parent = get_term($term->parent, 'product_cat');
                    if ($parent && !is_wp_error($parent)) {
                        $slug = $parent->slug;
                        continue;
                    }
                }

                break;
            }
        } else {
            if (array_key_exists('__shop__', $map)) {
                $attrs = (array) $map['__shop__'];
                $explicit = true;
            }
        }

        if (!$explicit && empty($attrs) && array_key_exists('__shop__', $map)) {
            $attrs = (array) $map['__shop__'];
        }

        if (empty($attrs) && !$explicit) {
            $all = [];
            foreach ($map as $set) {
                $all = array_merge($all, (array) $set);
            }
            $attrs = array_values(array_unique($all));
        }

        return $attrs;
    }

    public static function shop_show_categories() {
        $value = get_option('lime_filters_shop_show_categories', 'yes');
        return $value === 'yes';
    }

    public static function product_price_columns($product) {
        if (!($product instanceof WC_Product)) {
            return '';
        }

        $regular_price = '';
        $sale_price    = '';
        $current_price = '';

        if ($product->is_type('variable')) {
            $regular_price = $product->get_variation_regular_price('min', true);
            $sale_price    = $product->get_variation_sale_price('min', true);
            $current_price = $product->get_variation_price('min', true);
        } else {
            $regular_price = $product->get_regular_price();
            $sale_price    = $product->get_sale_price();
            $current_price = $product->get_price();
        }

        $regular_display = '';
        if ($regular_price !== '' && $regular_price !== null) {
            $regular_display = wc_price( wc_get_price_to_display( $product, ['price' => $regular_price] ) );
        }

        $current_display = '';
        if ($current_price !== '' && $current_price !== null) {
            $current_display = wc_price( wc_get_price_to_display( $product, ['price' => $current_price] ) );
        }

        $sale_display = '';
        if ($sale_price !== '' && $sale_price !== null) {
            $sale_display = wc_price( wc_get_price_to_display( $product, ['price' => $sale_price] ) );
        }

        $has_sale = $sale_display !== '' && $sale_display !== $regular_display;

        if (!$has_sale) {
            $display = $current_display !== '' ? $current_display : $regular_display;
            if ($display === '') {
                return '';
            }
            $html  = '<div class="lf-price-block lf-price-block--single">';
            $html .= '<div class="lf-price-col lf-price-col--sale">';
            $html .= '<span class="lf-price-label">' . esc_html__('Starting at', 'lime-filters') . '</span>';
            $html .= '<span class="lf-price-value">' . wp_kses_post($display) . '</span>';
            $html .= '</div>';
            $html .= '</div>';
            return $html;
        }

        if ($regular_display === '') {
            $regular_display = $current_display !== '' ? $current_display : $sale_display;
        }

        $html  = '<div class="lf-price-block">';
        $html .= '<div class="lf-price-col lf-price-col--regular">';
        $html .= '<span class="lf-price-label">' . esc_html__('Suggested price', 'lime-filters') . '</span>';
        $html .= '<span class="lf-price-value">' . wp_kses_post($regular_display) . '</span>';
        $html .= '</div>';
        $html .= '<div class="lf-price-col lf-price-col--sale">';
        $html .= '<span class="lf-price-label">' . esc_html__('Starting at', 'lime-filters') . '</span>';
        $html .= '<span class="lf-price-value">' . wp_kses_post($sale_display) . '</span>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    public static function product_price_summary($product) {
        if (!($product instanceof WC_Product)) {
            return [
                'regular' => '',
                'sale'    => '',
                'current' => '',
            ];
        }

        if ($product->is_type('variable')) {
            $regular = $product->get_variation_regular_price('min', true);
            $sale    = $product->get_variation_sale_price('min', true);
            $current = $product->get_variation_price('min', true);
        } else {
            $regular = $product->get_regular_price();
            $sale    = $product->get_sale_price();
            $current = $product->get_price();
        }

        $regular_display = '';
        if ($regular !== '' && $regular !== null) {
            $regular_display = wc_price(wc_get_price_to_display($product, ['price' => $regular]));
        }

        $current_display = '';
        if ($current !== '' && $current !== null) {
            $current_display = wc_price(wc_get_price_to_display($product, ['price' => $current]));
        }

        $sale_display = '';
        if ($sale !== '' && $sale !== null) {
            $sale_display = wc_price(wc_get_price_to_display($product, ['price' => $sale]));
        }

        return [
            'regular' => $regular_display,
            'sale'    => $sale_display,
            'current' => $current_display,
        ];
    }

    public static function affiliate_stores() {
        $base = LF_PLUGIN_URL . 'includes/compare/icons/compare-icons/';
        $stores = [
            'amazon'         => [
                'label' => __('Amazon', 'lime-filters'),
                'logo'  => $base . 'amazon-ico.svg',
            ],
            'best_buy'       => [
                'label' => __('Best Buy', 'lime-filters'),
                'logo'  => $base . 'best_buy-ico.svg',
            ],
            'rona'           => [
                'label' => __('Rona', 'lime-filters'),
                'logo'  => $base . 'rona-ico.svg',
            ],
            'the_home_depot' => [
                'label' => __('Home Depot', 'lime-filters'),
                'logo'  => $base . 'the_home_depot-ico.svg',
            ],
            'wayfair'        => [
                'label' => __('Wayfair', 'lime-filters'),
                'logo'  => $base . 'wayfair-ico.svg',
            ],
            'walmart'        => [
                'label' => __('Walmart', 'lime-filters'),
                'logo'  => $base . 'walmart-ico.svg',
            ],
        ];

        return apply_filters('lime_filters_affiliate_stores', $stores);
    }

    public static function affiliate_link($product_id, $store_key) {
        $url = '';
        if (function_exists('get_field')) {
            $url = get_field($store_key, $product_id);
        }

        if (!$url) {
            $meta = get_post_meta($product_id, $store_key, true);
            if (is_string($meta) && $meta !== '') {
                $url = $meta;
            }
        }

        return $url ? esc_url_raw($url) : '';
    }

    public static function recently_viewed_product_ids($limit = 10) {
        $cookie_sources = ['lf_recently_viewed', 'woocommerce_recently_viewed'];
        $cookie = '';
        foreach ($cookie_sources as $name) {
            if (isset($_COOKIE[$name]) && $_COOKIE[$name] !== '') {
                $cookie = wp_unslash($_COOKIE[$name]);
                if ($cookie !== '') {
                    break;
                }
            }
        }

        if ($cookie === '') {
            return [];
        }

        $ids = array_filter(array_map('absint', explode('|', $cookie)));
        $ids = array_values(array_unique($ids));

        if ($limit > 0) {
            $ids = array_slice($ids, 0, $limit);
        }

        /**
         * Filter the list of recently viewed product IDs.
         *
         * @param array $ids
         * @param int   $limit
         */
        return apply_filters('lime_filters_recently_viewed_product_ids', $ids, $limit);
    }

    public static function recently_viewed_products($limit = 6) {
        $ids = self::recently_viewed_product_ids($limit);
        if (empty($ids)) {
            return [];
        }

        $products = [];
        foreach ($ids as $id) {
            $product = wc_get_product($id);
            if ($product instanceof WC_Product) {
                $products[] = $product;
            }
        }

        /**
         * Filter the collection of recently viewed product objects.
         *
         * @param array $products
         * @param int   $limit
         */
        return apply_filters('lime_filters_recently_viewed_products', $products, $limit);
    }

    public static function upsell_products_for($product, $limit = 4) {
        if (!($product instanceof WC_Product)) {
            return [];
        }

        $upsell_ids = $product->get_upsell_ids();
        if (empty($upsell_ids)) {
            return [];
        }

        $upsell_ids = array_slice(array_filter(array_map('absint', $upsell_ids)), 0, max(1, (int) $limit));
        if (empty($upsell_ids)) {
            return [];
        }

        $products = [];
        foreach ($upsell_ids as $upsell_id) {
            $upsell = wc_get_product($upsell_id);
            if (!$upsell instanceof WC_Product) {
                continue;
            }

            $is_in_stock = $upsell->is_in_stock();
            $is_purchasable = $upsell->is_purchasable();
            $supports_simple_add = $upsell->get_type() === 'simple';

            $status = '';
            $status_label = '';
            if (!$is_in_stock) {
                $status = 'out_of_stock';
                $status_label = __('View product (currently out of stock)', 'lime-filters');
            } elseif (!$supports_simple_add) {
                $status = 'requires_options';
                $status_label = __('Choose options (opens product page)', 'lime-filters');
            }

            $products[] = [
                'id'         => $upsell_id,
                'title'      => $upsell->get_name(),
                'price'      => $upsell->get_price_html(),
                'image'      => $upsell->get_image('woocommerce_thumbnail'),
                'url'        => get_permalink($upsell_id),
                'type'       => $upsell->get_type(),
                'can_add'    => ($supports_simple_add && $is_purchasable && $is_in_stock),
                'status'     => $status,
                'status_label' => $status_label,
            ];
        }

        /**
         * Filter the upsell products payload used for affiliate accessory prompts.
         *
         * @param array      $products Structured data for upsell products.
         * @param WC_Product $product  The source product.
         * @param int        $limit    Requested limit.
         */
        return apply_filters('lf_upsell_products_for_affiliate_prompt', $products, $product, $limit);
    }
}
