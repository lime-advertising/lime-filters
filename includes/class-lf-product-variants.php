<?php
if (!defined('ABSPATH')) {
    exit;
}

class LF_Product_Variants {
    const META_ENABLED = '_lf_variants_enabled';
    const META_PAYLOAD = '_lf_variant_payload';
    const META_APPEND_GALLERY = '_lf_variant_gallery_append';
    const PAYLOAD_VERSION = 2;

    protected static $frontend_products = [];
    protected static $frontend_script_enqueued = false;
    protected static $admin_localized = false;

    public static function init() {
        if (is_admin()) {
            add_filter('woocommerce_product_data_tabs', [__CLASS__, 'add_product_data_tab']);
            add_action('woocommerce_product_data_panels', [__CLASS__, 'output_product_data_panel']);
            add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
            add_action('save_post_product', [__CLASS__, 'save_product_variants'], 20, 2);
        }

        add_action('init', [__CLASS__, 'register_frontend_script']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'maybe_enqueue_frontend_script']);
        add_action('wp_footer', [__CLASS__, 'print_frontend_data'], 5);
        add_filter('woocommerce_single_product_image_html', [__CLASS__, 'flag_main_image'], 5, 2);
        add_filter('woocommerce_single_product_image_thumbnail_html', [__CLASS__, 'flag_main_image'], 5, 2);
    }

    public static function add_product_data_tab($tabs) {
        $tabs['lf_variants'] = [
            'label'    => __('Attribute Variants', 'lime-filters'),
            'target'   => 'lf_variants_panel',
            'priority' => 95,
            'class'    => ['show_if_simple', 'lf-variants-tab'],
        ];
        return $tabs;
    }

    public static function output_product_data_panel() {
        global $post;
        $product = $post instanceof WP_Post ? wc_get_product($post->ID) : null;
        if (!$product instanceof WC_Product) {
            echo '<div id="lf_variants_panel" class="panel woocommerce_options_panel"><p>' . esc_html__('Product context not found.', 'lime-filters') . '</p></div>';
            return;
        }

        $enabled = get_post_meta($product->get_id(), self::META_ENABLED, true);
        $enabled = ($enabled === 'yes') ? 'yes' : 'no';
        $append_gallery = get_post_meta($product->get_id(), self::META_APPEND_GALLERY, true);
        $append_gallery = ($append_gallery === 'yes') ? 'yes' : 'no';

        $payload = get_post_meta($product->get_id(), self::META_PAYLOAD, true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $admin_data = self::prepare_admin_payload($product, $payload, $enabled === 'yes', $append_gallery === 'yes');

        wp_nonce_field('lf_save_variants', '_lf_variants_nonce');

        echo '<div id="lf_variants_panel" class="panel woocommerce_options_panel">';
        echo '<input type="hidden" name="_lf_variants_enabled" value="no" />';
        echo '<p class="form-field lf-variants-toggle">';
        echo '<label for="lf-variants-enabled">' . esc_html__('Enable attribute-based variants', 'lime-filters') . '</label>';
        echo '<input type="checkbox" id="lf-variants-enabled" name="_lf_variants_enabled" value="yes"' . checked($enabled, 'yes', false) . ' />';
        echo '<span class="description">' . esc_html__('When enabled, configure per-attribute overrides for images, SKU, and affiliate links.', 'lime-filters') . '</span>';
        echo '</p>';
        echo '<input type="hidden" name="_lf_variants_append_gallery" value="no" />';
        echo '<p class="form-field lf-variants-toggle">';
        echo '<label for="lf-variants-append-gallery">' . esc_html__('Append variant images to gallery', 'lime-filters') . '</label>';
        echo '<input type="checkbox" id="lf-variants-append-gallery" name="_lf_variants_append_gallery" value="yes"' . checked($append_gallery, 'yes', false) . ' />';
        echo '<span class="description">' . esc_html__('When enabled, Lime Filters galleries automatically include variant images.', 'lime-filters') . '</span>';
        echo '</p>';

        $json_value = wp_json_encode($payload);
        if ($json_value === false || $json_value === '[]') {
            $json_value = '{}';
        }
        echo '<input type="hidden" id="lf-variant-payload" name="_lf_variant_payload" value="' . esc_attr($json_value) . '" />';
        echo '<div id="lf-variants-root" data-product-id="' . esc_attr($product->get_id()) . '"></div>';
        echo '</div>';

        if (!self::$admin_localized) {
            wp_localize_script(
                'lf-product-variants-admin',
                'LFVariantsAdmin',
                $admin_data
            );
            self::$admin_localized = true;
        } else {
            $data_script = 'window.LFVariantsAdmin = ' . wp_json_encode($admin_data) . ';';
            wp_add_inline_script('lf-product-variants-admin', $data_script, 'before');
        }
    }

    public static function enqueue_admin_assets($hook) {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'product') {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_style(
            'lf-product-variants-admin',
            LF_PLUGIN_URL . 'includes/assets/admin/lime-filters-variants.css',
            [],
            LF_VERSION
        );

        wp_enqueue_script(
            'lf-product-variants-admin',
            LF_PLUGIN_URL . 'includes/assets/admin/lime-filters-variants.js',
            ['jquery', 'wp-util'],
            LF_VERSION,
            true
        );
    }

    public static function save_product_variants($post_id, $post) {
        if (!isset($_POST['_lf_variants_nonce']) || !wp_verify_nonce(wp_unslash($_POST['_lf_variants_nonce']), 'lf_save_variants')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if ($post->post_type !== 'product') {
            return;
        }

        $enabled = isset($_POST['_lf_variants_enabled']) && $_POST['_lf_variants_enabled'] === 'yes' ? 'yes' : 'no';
        update_post_meta($post_id, self::META_ENABLED, $enabled);
        $append_gallery = isset($_POST['_lf_variants_append_gallery']) && $_POST['_lf_variants_append_gallery'] === 'yes' ? 'yes' : 'no';
        update_post_meta($post_id, self::META_APPEND_GALLERY, $append_gallery);

        $raw = isset($_POST['_lf_variant_payload']) ? wp_unslash($_POST['_lf_variant_payload']) : '';
        if ($raw === '') {
            delete_post_meta($post_id, self::META_PAYLOAD);
            return;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            delete_post_meta($post_id, self::META_PAYLOAD);
            return;
        }

        $product = wc_get_product($post_id);
        if (!$product instanceof WC_Product) {
            delete_post_meta($post_id, self::META_PAYLOAD);
            return;
        }

        $sanitized = self::sanitize_payload($decoded, $product);
        if (empty($sanitized) || empty($sanitized['variants'])) {
            delete_post_meta($post_id, self::META_PAYLOAD);
            return;
        }

        $sanitized['version'] = self::PAYLOAD_VERSION;
        update_post_meta($post_id, self::META_PAYLOAD, $sanitized);
    }

    protected static function sanitize_payload(array $payload, WC_Product $product) {
        $attributes = self::get_attribute_map($product);
        if (empty($attributes)) {
            return [];
        }

        $stores = LF_Helpers::affiliate_stores();
        $store_keys = array_keys($stores);

        if (self::is_legacy_payload($payload)) {
            $payload = self::convert_legacy_payload($payload);
        }

        $selected_attributes = [];
        if (isset($payload['attributes']) && is_array($payload['attributes'])) {
            foreach ($payload['attributes'] as $attribute_slug) {
                $attribute_slug = sanitize_title($attribute_slug);
                if (isset($attributes[$attribute_slug])) {
                    $selected_attributes[] = $attribute_slug;
                }
            }
        }
        $selected_attributes = array_values(array_unique($selected_attributes));

        $variant_entries = isset($payload['variants']) && is_array($payload['variants']) ? $payload['variants'] : [];
        $sanitized_variants = [];

        foreach ($variant_entries as $key => $variant_entry) {
            $combo = [];
            if (isset($variant_entry['attributes']) && is_array($variant_entry['attributes']) && !empty($variant_entry['attributes'])) {
                $combo = $variant_entry['attributes'];
            } elseif (is_string($key)) {
                $combo = self::parse_variant_key($key);
            }

            if (empty($combo)) {
                continue;
            }

            $normalized_combo = [];
            foreach ($combo as $attr_slug => $term_slug) {
                $attr_slug = sanitize_title($attr_slug);
                $term_slug = sanitize_title($term_slug);
                if (!isset($attributes[$attr_slug]) || !isset($attributes[$attr_slug]['terms'][$term_slug])) {
                    continue 2;
                }
                $normalized_combo[$attr_slug] = $term_slug;
            }

            if (empty($normalized_combo)) {
                continue;
            }

            if (!empty($selected_attributes)) {
                $missing = array_diff($selected_attributes, array_keys($normalized_combo));
                if (!empty($missing)) {
                    continue;
                }
            }

            $sanitized_entry = self::sanitize_variant_entry($variant_entry, $normalized_combo, $store_keys);
            if ($sanitized_entry) {
                $key = self::variant_key($normalized_combo);
                $sanitized_variants[$key] = $sanitized_entry;
            }
        }

        if (empty($sanitized_variants)) {
            return [];
        }

        if (empty($selected_attributes)) {
            $selected_attributes = [];
        }

        return [
            'version'   => self::PAYLOAD_VERSION,
            'attributes' => $selected_attributes,
            'variants'   => $sanitized_variants,
        ];
    }

    protected static function sanitize_variant_entry(array $entry, array $combo, array $store_keys) {
        $sanitized = [
            'attributes' => $combo,
        ];

        $image_id = isset($entry['image_id']) ? absint($entry['image_id']) : 0;
        if ($image_id > 0) {
            $sanitized['image_id'] = $image_id;
        }

        $sku = isset($entry['sku']) ? wc_clean($entry['sku']) : '';
        if ($sku !== '') {
            $sanitized['sku'] = $sku;
        }

        if (isset($entry['affiliates']) && is_array($entry['affiliates'])) {
            $affiliates = [];
            foreach ($entry['affiliates'] as $store => $url) {
                if (!in_array($store, $store_keys, true)) {
                    continue;
                }
                $url = is_string($url) ? trim($url) : '';
                if ($url === '') {
                    continue;
                }
                $affiliates[$store] = esc_url_raw($url);
            }
            if (!empty($affiliates)) {
                $sanitized['affiliates'] = $affiliates;
            }
        }

        if (isset($entry['extras']) && is_array($entry['extras']) && !empty($entry['extras'])) {
            $sanitized['extras'] = array_map('wc_clean', $entry['extras']);
        }

        return $sanitized;
    }

    protected static function variant_key(array $combo) {
        ksort($combo);
        $parts = [];
        foreach ($combo as $attribute_slug => $term_slug) {
            $parts[] = $attribute_slug . '=' . $term_slug;
        }
        return implode('|', $parts);
    }

    protected static function parse_variant_key($key) {
        $combo = [];
        $segments = array_filter(array_map('trim', explode('|', (string) $key)));
        foreach ($segments as $segment) {
            $parts = array_map('trim', explode('=', $segment, 2));
            if (count($parts) !== 2) {
                continue;
            }
            $combo[sanitize_title($parts[0])] = sanitize_title($parts[1]);
        }
        return $combo;
    }

    protected static function is_legacy_payload(array $payload) {
        if (isset($payload['variants']) && is_array($payload['variants'])) {
            return false;
        }

        foreach ($payload as $value) {
            if (is_array($value)) {
                return true;
            }
        }

        return false;
    }

    protected static function convert_legacy_payload(array $payload) {
        $converted = [
            'version'   => self::PAYLOAD_VERSION,
            'attributes' => [],
            'variants'   => [],
        ];

        foreach ($payload as $attribute_slug => $terms) {
            if (!is_array($terms)) {
                continue;
            }
            foreach ($terms as $term_slug => $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $combo = [
                    sanitize_title($attribute_slug) => sanitize_title($term_slug),
                ];
                $key = self::variant_key($combo);
                $converted['variants'][$key] = [
                    'attributes' => $combo,
                    'image_id'   => isset($entry['image_id']) ? $entry['image_id'] : 0,
                    'sku'        => isset($entry['sku']) ? $entry['sku'] : '',
                    'affiliates' => isset($entry['affiliates']) ? $entry['affiliates'] : [],
                    'extras'     => isset($entry['extras']) ? $entry['extras'] : [],
                ];
            }
        }

        return $converted;
    }

    protected static function prepare_admin_payload(WC_Product $product, array $payload, $enabled, $append_gallery) {
        $attributes = self::get_attribute_map($product);
        $stores     = LF_Helpers::affiliate_stores();

        $payload = self::sanitize_payload($payload, $product);
        $selected_attributes = isset($payload['attributes']) ? (array) $payload['attributes'] : [];
        $variant_rows = [];

        if (isset($payload['variants']) && is_array($payload['variants'])) {
            foreach ($payload['variants'] as $key => $data) {
                $entry = [
                    'key'        => $key,
                    'attributes' => isset($data['attributes']) ? (array) $data['attributes'] : [],
                    'image_id'   => isset($data['image_id']) ? absint($data['image_id']) : 0,
                    'image_url'  => '',
                    'sku'        => isset($data['sku']) ? $data['sku'] : '',
                    'affiliates' => [],
                    'extras'     => isset($data['extras']) && is_array($data['extras']) ? $data['extras'] : [],
                ];

                if ($entry['image_id']) {
                    $image_url = wp_get_attachment_image_url($entry['image_id'], 'thumbnail');
                    if ($image_url) {
                        $entry['image_url'] = $image_url;
                    }
                }

                if (isset($data['affiliates']) && is_array($data['affiliates'])) {
                    foreach ($data['affiliates'] as $store => $url) {
                        if (!isset($stores[$store])) {
                            continue;
                        }
                        $entry['affiliates'][$store] = $url;
                    }
                }

                $variant_rows[$key] = $entry;
            }
        }

        $store_payload = [];
        foreach ($stores as $key => $meta) {
            $store_payload[$key] = [
                'label' => isset($meta['label']) ? $meta['label'] : '',
                'logo'  => isset($meta['logo']) ? $meta['logo'] : '',
            ];
        }

        return [
            'productId'          => $product->get_id(),
            'enabled'            => $enabled ? 'yes' : 'no',
            'append_gallery'     => $append_gallery ? 'yes' : 'no',
            'attributes'         => array_values($attributes),
            'selected_attributes'=> $selected_attributes,
            'variants'           => $variant_rows,
            'stores'             => $store_payload,
            'version'            => isset($payload['version']) ? absint($payload['version']) : self::PAYLOAD_VERSION,
            'i18n'               => [
                'noAttributes'   => __('Assign attributes to this product to configure variants.', 'lime-filters'),
                'addVariant'     => __('Add Combination', 'lime-filters'),
                'termPlaceholder'=> __('Select a value', 'lime-filters'),
                'imageSelect'    => __('Select image', 'lime-filters'),
                'imageChange'    => __('Change image', 'lime-filters'),
                'imageRemove'    => __('Remove image', 'lime-filters'),
                'skuLabel'       => __('SKU Override', 'lime-filters'),
                'affiliateLabel' => __('Affiliate URL', 'lime-filters'),
                'removeRow'      => __('Remove variant', 'lime-filters'),
                'enabledLabel'   => __('Variants enabled', 'lime-filters'),
                'attributeToggleLabel' => __('Attributes used for variants', 'lime-filters'),
                'generateAll'    => __('Generate combinations', 'lime-filters'),
                'noAttributeSelection' => __('Select at least one attribute to build combinations.', 'lime-filters'),
                'combinationLabel'=> __('Combination', 'lime-filters'),
                'addCombination' => __('Add Combination', 'lime-filters'),
                'existingCombo'  => __('This combination already exists.', 'lime-filters'),
                'generatedCount' => __('Added %d new combinations.', 'lime-filters'),
                'nothingGenerated' => __('All possible combinations already exist.', 'lime-filters'),
                'attributeBadgeSeparator' => __(' / ', 'lime-filters'),
                'attributeNoTerms' => __('At least one selected attribute has no values assigned.', 'lime-filters'),
                'expandRow'    => __('Show details', 'lime-filters'),
                'collapseRow'  => __('Hide details', 'lime-filters'),
            ],
        ];
    }

    protected static function get_attribute_map(WC_Product $product) {
        $attributes = [];

        foreach ($product->get_attributes() as $attribute) {
            $slug = $attribute->get_name();
            $attr_data = [
                'slug'  => $slug,
                'label' => $attribute->is_taxonomy() ? wc_attribute_label($slug) : $attribute->get_name(),
                'type'  => $attribute->is_taxonomy() ? 'taxonomy' : 'custom',
                'terms' => [],
            ];

            if ($attribute->is_taxonomy()) {
                $terms = $attribute->get_terms();
                if ($terms && !is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        $attr_data['terms'][$term->slug] = [
                            'slug' => $term->slug,
                            'name' => $term->name,
                            'id'   => $term->term_id,
                        ];
                    }
                }
            } else {
                $options = $attribute->get_options();
                if (is_array($options)) {
                    foreach ($options as $option) {
                        $slugged = sanitize_title($option);
                        $attr_data['terms'][$slugged] = [
                            'slug' => $slugged,
                            'name' => $option,
                            'id'   => $option,
                        ];
                    }
                }
            }

            $attributes[$slug] = $attr_data;
        }

        return $attributes;
    }

    public static function get_variants_for_product($product_id, $product = null) {
        $enabled = get_post_meta($product_id, self::META_ENABLED, true);
        if ($enabled !== 'yes') {
            return [];
        }

        $payload = get_post_meta($product_id, self::META_PAYLOAD, true);
        if (!is_array($payload)) {
            return [];
        }

        if (!$product instanceof WC_Product) {
            $product = wc_get_product($product_id);
        }

        if (!$product instanceof WC_Product) {
            return [];
        }

        return self::sanitize_payload($payload, $product);
    }

    public static function record_frontend_product(WC_Product $product) {
        $product_id = $product->get_id();
        if (isset(self::$frontend_products[$product_id])) {
            return;
        }

        $variants = self::get_variants_for_product($product_id, $product);
        if (empty($variants) || empty($variants['variants'])) {
            return;
        }

        $append_gallery = get_post_meta($product_id, self::META_APPEND_GALLERY, true) === 'yes';
        $payload = self::build_frontend_payload($product, $variants, $append_gallery);
        if (empty($payload)) {
            return;
        }

        self::$frontend_products[$product_id] = $payload;
        self::ensure_frontend_script();
    }

    protected static function build_frontend_payload(WC_Product $product, array $payload, $append_gallery) {
        $attributes = self::get_attribute_map($product);
        if (empty($attributes)) {
            return [];
        }

        if (empty($payload['variants']) || !is_array($payload['variants'])) {
            return [];
        }

        $stores = LF_Helpers::affiliate_stores();
        $store_keys = array_keys($stores);

        $data = [
            'product_id' => $product->get_id(),
            'default'    => [
                'sku'        => $product->get_sku(),
                'image'      => self::get_image_data($product->get_image_id()),
                'affiliates' => self::get_product_affiliates($product->get_id(), $store_keys),
            ],
            'attributes' => [],
            'required'   => isset($payload['attributes']) ? array_values($payload['attributes']) : [],
            'variants'   => [],
            'append_gallery' => $append_gallery ? 'yes' : 'no',
        ];

        foreach ($payload['variants'] as $key => $entry) {
            $combo = isset($entry['attributes']) && is_array($entry['attributes']) ? $entry['attributes'] : [];
            if (empty($combo)) {
                continue;
            }

            $normalized_combo = [];
            foreach ($combo as $attribute_slug => $term_slug) {
                $attribute_slug = sanitize_title($attribute_slug);
                $term_slug = sanitize_title($term_slug);
                if (!isset($attributes[$attribute_slug]) || !isset($attributes[$attribute_slug]['terms'][$term_slug])) {
                    continue 2;
                }

                if (!isset($data['attributes'][$attribute_slug])) {
                    $data['attributes'][$attribute_slug] = [
                        'slug'  => $attribute_slug,
                        'label' => $attributes[$attribute_slug]['label'],
                        'terms' => [],
                    ];
                }

                $data['attributes'][$attribute_slug]['terms'][$term_slug] = $attributes[$attribute_slug]['terms'][$term_slug];
                $normalized_combo[$attribute_slug] = $term_slug;
            }

            if (empty($normalized_combo)) {
                continue;
            }

            $variant_entry = [
                'attributes' => $normalized_combo,
                'sku'        => isset($entry['sku']) ? $entry['sku'] : '',
                'image'      => isset($entry['image_id']) ? self::get_image_data($entry['image_id']) : null,
                'affiliates' => [],
                'extras'     => isset($entry['extras']) && is_array($entry['extras']) ? $entry['extras'] : [],
            ];

            if (isset($entry['affiliates']) && is_array($entry['affiliates'])) {
                foreach ($entry['affiliates'] as $store => $url) {
                    if (!in_array($store, $store_keys, true)) {
                        continue;
                    }
                    $variant_entry['affiliates'][$store] = esc_url($url);
                }
            }

            $variant_key = is_string($key) ? $key : self::variant_key($normalized_combo);
            $data['variants'][$variant_key] = $variant_entry;
        }

        if (empty($data['variants'])) {
            return [];
        }

        return $data;
    }

    protected static function get_image_data($attachment_id) {
        $attachment_id = absint($attachment_id);
        if ($attachment_id <= 0) {
            return null;
        }

        $single = wp_get_attachment_image_src($attachment_id, 'woocommerce_single');
        $full   = wp_get_attachment_image_src($attachment_id, 'full');
        $thumb  = wp_get_attachment_image_src($attachment_id, 'woocommerce_gallery_thumbnail');

        $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        if ($alt === '') {
            $alt = get_the_title($attachment_id);
        }

        $image = [
            'id'     => $attachment_id,
            'src'    => $single ? $single[0] : '',
            'width'  => $single ? $single[1] : '',
            'height' => $single ? $single[2] : '',
            'full'   => $full ? $full[0] : '',
            'thumb'  => $thumb ? $thumb[0] : '',
            'alt'    => $alt,
            'srcset' => wp_get_attachment_image_srcset($attachment_id, 'woocommerce_single'),
            'sizes'  => wp_get_attachment_image_sizes($attachment_id, 'woocommerce_single'),
        ];

        return $image;
    }

    protected static function get_product_affiliates($product_id, array $store_keys) {
        $links = [];
        foreach ($store_keys as $store_key) {
            $url = LF_Helpers::affiliate_link($product_id, $store_key);
            if ($url) {
                $links[$store_key] = $url;
            }
        }
        return $links;
    }

    protected static function ensure_frontend_script() {
        if (self::$frontend_script_enqueued) {
            return;
        }
        wp_enqueue_script('lf-product-variants');
        self::$frontend_script_enqueued = true;
    }

    public static function register_frontend_script() {
        wp_register_script(
            'lf-product-variants',
            LF_PLUGIN_URL . 'includes/assets/js/lf-product-variants.js',
            [],
            LF_VERSION,
            true
        );
    }

    public static function maybe_enqueue_frontend_script() {
        if (is_product()) {
            $product = wc_get_product(get_the_ID());
            if ($product instanceof WC_Product) {
                self::record_frontend_product($product);
            }
        }
    }

    public static function print_frontend_data() {
        if (empty(self::$frontend_products) || !self::$frontend_script_enqueued) {
            return;
        }

        $stores = LF_Helpers::affiliate_stores();

        $store_payload = [];
        foreach ($stores as $key => $meta) {
            $store_payload[$key] = [
                'label' => isset($meta['label']) ? $meta['label'] : '',
                'logo'  => isset($meta['logo']) ? $meta['logo'] : '',
            ];
        }

        $payload = [
            'products' => self::$frontend_products,
            'stores'   => $store_payload,
        ];

        $json = wp_json_encode($payload);
        if ($json === false) {
            return;
        }

        wp_add_inline_script('lf-product-variants', 'window.LimeFiltersVariants = ' . $json . ';', 'before');
    }

    public static function flag_main_image($html, $attachment_id) {
        if (strpos($html, 'data-lf-product-image=') !== false) {
            return $html;
        }

        global $product;
        if (!$product instanceof WC_Product) {
            return $html;
        }

        $product_id = $product->get_id();
        $variant_data = self::get_variants_for_product($product_id, $product);
        if (empty($variant_data) || empty($variant_data['variants'])) {
            return $html;
        }

        $replacement = 'data-lf-product-image="' . esc_attr($product_id) . '" ';
        $updated = preg_replace('/<img\s+/i', '<img ' . $replacement, $html, 1);
        if ($updated !== null) {
            return $updated;
        }

        return $html;
    }
}
