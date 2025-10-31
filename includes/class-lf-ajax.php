<?php
if (! defined('ABSPATH')) {
    exit;
}

class LF_AJAX
{
    public static function init()
    {
        add_action('wp_ajax_lime_filter_products', [__CLASS__, 'handle']);
        add_action('wp_ajax_nopriv_lime_filter_products', [__CLASS__, 'handle']);
    }

    public static function handle()
    {
        check_ajax_referer('lf_nonce', 'nonce');

        $category   = sanitize_text_field($_POST['category'] ?? '');
        $orderby    = sanitize_text_field($_POST['orderby'] ?? 'menu_order');
        $filters    = isset($_POST['filters']) && is_array($_POST['filters']) ? $_POST['filters'] : [];
        $page       = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
        $pagination = isset($_POST['pagination']) && $_POST['pagination'] === 'yes';
        $per_page   = isset($_POST['per_page']) ? max(0, (int) $_POST['per_page']) : 0;
        $show_counts = isset($_POST['show_counts']) && $_POST['show_counts'] === 'yes' ? 'yes' : 'no';
        $columns     = isset($_POST['columns']) ? self::sanitize_columns_request($_POST['columns']) : ['desktop' => 0, 'tablet' => 0, 'mobile' => 0];

        $response = self::get_products_html($category, $filters, $orderby, $page, $pagination, $per_page, $show_counts, $columns);
        if (!is_array($response)) {
            $response = [
                'html'        => (string) $response,
                'pagination'  => '',
                'page'        => max(1, (int) $page),
                'total_pages' => 1,
                'per_page'    => self::products_per_page($per_page),
                'columns'     => self::resolve_columns_config($columns, $category),
                'filters'     => [],
            ];
        }

        wp_send_json_success($response);
    }

    public static function get_products_html($category = '', $filters = [], $orderby = 'menu_order', $page = 1, $pagination_enabled = false, $per_page = 0, $show_counts = 'no', $columns_config = [])
    {
        $filters = self::sanitize_filters($filters);
        $per_page_value = self::products_per_page($per_page);
        $columns_config = self::sanitize_columns_request($columns_config);
        $resolved_columns = self::resolve_columns_config($columns_config, $category);

        $args = self::build_query_args($category, $filters, $orderby, $page, $per_page_value);
        $args = apply_filters('lime_filters_query_args', $args, $category, $filters, $orderby);

        $attributes = LF_Helpers::attributes_for_context($category);
        $include_categories = ($category === '' && LF_Helpers::shop_show_categories());
        $show_counts_enabled = ($show_counts === 'yes');

        $query = new WP_Query($args);
        $products_html   = '';
        $pagination_html = '';
        $total_pages     = (int) $query->max_num_pages;
        $current_page    = max(1, (int) $page);
        if ($total_pages > 0) {
            $current_page = min($current_page, $total_pages);
        }

        if ($query->have_posts()) {
            ob_start();
            $style = sprintf(
                '--lf-col-desktop:%d;--lf-col-tablet:%d;--lf-col-mobile:%d;',
                (int) $resolved_columns['desktop'],
                (int) $resolved_columns['tablet'],
                (int) $resolved_columns['mobile']
            );
            printf(
                '<div class="lf-products" data-columns="%d" data-columns-desktop="%d" data-columns-tablet="%d" data-columns-mobile="%d" style="%s">',
                (int) $resolved_columns['desktop'],
                (int) $resolved_columns['desktop'],
                (int) $resolved_columns['tablet'],
                (int) $resolved_columns['mobile'],
                esc_attr($style)
            );

            while ($query->have_posts()) {
                $query->the_post();
                global $product;
                $product = wc_get_product(get_the_ID());
                if (!$product) {
                    continue;
                }

                echo self::render_product_card($product);
            }

            echo '</div>';
            $products_html = ob_get_clean();
            wp_reset_postdata();
        } else {
            $products_html = '<p class="lf-no-results">' . esc_html__('No products match your filters.', 'lime-filters') . '</p>';
        }

        if ($pagination_enabled) {
            $pagination_html = self::render_pagination($query, $current_page);
            $pagination_html = apply_filters('lime_filters_pagination_html', $pagination_html, $query, $current_page);
        }

        $filters_payload = self::build_filters_payload($args, $filters, $attributes, $include_categories, $show_counts_enabled);

        return [
            'html'        => $products_html,
            'pagination'  => $pagination_html,
            'page'        => $current_page,
            'total_pages' => $total_pages,
            'per_page'    => $per_page_value,
            'filters'     => $filters_payload,
            'columns'     => $resolved_columns,
            'wishlist'    => (class_exists('LF_Wishlist') && LF_Wishlist::is_enabled()) ? LF_Wishlist::current_ids() : [],
        ];
    }

    public static function render_product_card($product)
    {
        if (!$product instanceof WC_Product) {
            return '';
        }

        $product_id = $product->get_id();
        $permalink  = get_permalink($product_id);
        $title      = $product->get_name();
        $thumbnail  = $product->get_image('woocommerce_thumbnail');

        if (!$thumbnail || strpos($thumbnail, 'woocommerce-placeholder') !== false) {
            $thumbnail = '<img src="' . esc_url(LF_Helpers::placeholder_image_url()) . '" alt="" class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail lf-placeholder" />';
        }

        if ($thumbnail && class_exists('LF_Product_Background') && method_exists('LF_Product_Background', 'apply_background_wrapper')) {
            $thumbnail = LF_Product_Background::apply_background_wrapper($thumbnail);
        }

        $price      = LF_Helpers::product_price_columns($product);
        $sku        = $product->get_sku();
        $categories = self::category_links($product_id);

        $previous_product = isset($GLOBALS['product']) ? $GLOBALS['product'] : null;
        $GLOBALS['product'] = $product;
        $actions    = self::render_product_actions($product);
        $GLOBALS['product'] = $previous_product;

        $wishlist_button = '';
        if (class_exists('LF_Wishlist') && method_exists('LF_Wishlist', 'render_button')) {
            $wishlist_button = LF_Wishlist::render_button($product);
        }

        ob_start();
        ?>
        <article class="lf-product">
            <div class="lf-product__media">
                <a class="lf-product__thumb" href="<?php echo esc_url($permalink); ?>">
                    <?php echo $thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php if ($sku) : ?>
                        <div class="lf-product__sku"><?php echo esc_html($sku); ?></div>
                    <?php endif; ?>
                </a>
                <?php if ($wishlist_button) : ?>
                    <?php echo $wishlist_button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php endif; ?>
            </div>
            <div class="lf-product__body">
                <?php if ($categories) : ?>
                    <div class="lf-product__cats"><?php echo $categories; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <?php endif; ?>
                <h3 class="lf-product__title">
                    <a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a>
                </h3>
                <?php if ($price) : ?>
                    <div class="lf-product__price lf-product__price--columns"><?php echo $price; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <?php endif; ?>
            </div>
            <?php if (!empty(trim($actions))) : ?>
                <div class="lf-product__actions">
                    <?php echo $actions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            <?php endif; ?>
        </article>
        <?php
        return ob_get_clean();
    }

    protected static function build_query_args($category, $filters, $orderby, $page = 1, $per_page = 0)
    {
        $tax_query = self::build_tax_query($category, $filters);

        $args = [
            'post_type'      => 'product',
            'posts_per_page' => $per_page,
            'tax_query'      => $tax_query,
            'orderby'        => $orderby,
            'paged'          => max(1, (int) $page),
        ];

        switch ($orderby) {
            case 'price':
                $args['meta_key'] = '_price';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'ASC';
                break;
            case 'price-desc':
                $args['meta_key'] = '_price';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'DESC';
                break;
            case 'date':
                $args['orderby']  = 'date';
                $args['order']    = 'DESC';
                break;
            case 'rating':
                $args['orderby']  = 'rating';
                break;
            case 'popularity':
                $args['orderby']  = 'popularity';
                break;
            default:
                $args['orderby'] = 'menu_order title';
                $args['order']   = 'ASC';
        }

        return $args;
    }

    protected static function sanitize_filters($filters)
    {
        if (!is_array($filters)) {
            return [];
        }

        $sanitized = [];
        foreach ($filters as $tax => $slugs) {
            $tax = sanitize_text_field($tax);
            if (!$tax || !taxonomy_exists($tax)) {
                continue;
            }
            $slugs = array_filter(array_map('sanitize_text_field', (array) $slugs));
            $slugs = array_values(array_unique($slugs));
            if ($slugs) {
                $sanitized[$tax] = array_values($slugs);
            }
        }
        return $sanitized;
    }

    protected static function build_tax_query($category, $filters)
    {
        $tax_query = ['relation' => 'AND'];

        if ($category) {
            $tax_query[] = [
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => [$category],
            ];
        }

        foreach ($filters as $tax => $slugs) {
            $tax_query[] = [
                'taxonomy' => $tax,
                'field'    => 'slug',
                'terms'    => $slugs,
                'operator' => 'IN',
            ];
        }

        if (count($tax_query) === 1) {
            return [];
        }

        return $tax_query;
    }

    protected static function products_per_page($requested = 0)
    {
        $requested = (int) $requested;
        $max = (int) apply_filters('lime_filters_max_per_page', 48);
        if ($requested > 0) {
            if ($max > 0) {
                $requested = min($requested, $max);
            }
            $requested = (int) apply_filters('lime_filters_products_per_page', $requested);
            return max(1, $requested);
        }

        $per_row = function_exists('wc_get_default_products_per_row') ? wc_get_default_products_per_row() : 4;
        $rows = function_exists('wc_get_default_product_rows_per_page') ? wc_get_default_product_rows_per_page() : 3;
        $per_page = $per_row * $rows;
        $per_page = (int) apply_filters('lime_filters_products_per_page', $per_page);
        return max(1, $per_page);
    }

    protected static function grid_columns()
    {
        $default = function_exists('wc_get_default_products_per_row') ? wc_get_default_products_per_row() : 3;
        $value = (int) apply_filters('lime_filters_grid_columns', $default);
        if ($value < 1) {
            $value = 1;
        }
        if ($value > 6) {
            $value = 6;
        }
        return $value;
    }

    protected static function category_links($product_id)
    {
        $terms = get_the_terms($product_id, 'product_cat');
        if (!$terms || is_wp_error($terms)) {
            return '';
        }

        $links = [];
        foreach ($terms as $term) {
            $url = get_term_link($term);
            if (is_wp_error($url)) {
                continue;
            }
            $links[] = sprintf('<a href="%s">%s</a>', esc_url($url), esc_html($term->name));
        }

        return implode(', ', $links);
    }

    protected static function render_product_actions($product)
    {
        if (! $product instanceof WC_Product) {
            return '';
        }

        $actions = [];

        if (self::supports_add_to_cart($product)) {
            $add_to_cart = self::get_add_to_cart_button($product);
            if ($add_to_cart) {
                $actions[] = $add_to_cart;
            }
        } else {
            $compare = self::get_compare_button($product);
            if ($compare) {
                $actions[] = $compare;
            }
        }

        $actions[] = sprintf(
            '<a class="lf-button lf-button--secondary" href="%s">%s</a>',
            esc_url(get_permalink($product->get_id())),
            esc_html__('View Product', 'lime-filters')
        );

        return implode('', $actions);
    }

    protected static function supports_add_to_cart($product)
    {
        $allowed = apply_filters('lime_filters_add_to_cart_categories', ['accessories', 'parts']);
        if (!$allowed) {
            return false;
        }
        $terms = get_the_terms($product->get_id(), 'product_cat');
        if (!$terms || is_wp_error($terms)) {
            return false;
        }
        $slugs = wp_list_pluck($terms, 'slug');
        return (bool) array_intersect($allowed, $slugs);
    }

    protected static function get_add_to_cart_button($product)
    {
        if (!function_exists('woocommerce_template_loop_add_to_cart')) {
            return '';
        }

        ob_start();
        woocommerce_template_loop_add_to_cart();
        $button = trim(ob_get_clean());
        if (!$button) {
            return '';
        }

        // Ensure button has our class
        $button = preg_replace(
            '/class="([^"]*?)"/',
            'class="$1 lf-button lf-button--primary"',
            $button,
            1,
            $count
        );
        if ($count === 0) {
            // No class attribute found; add one
            $button = str_replace('<a ', '<a class="lf-button lf-button--primary" ', $button);
            $button = str_replace('<button ', '<button class="lf-button lf-button--primary" ', $button);
        }

        return $button;
    }

    protected static function build_filters_payload($args, $selected_filters, $attributes, $include_categories, $show_counts)
    {
        if (!$include_categories && empty($attributes)) {
            return [];
        }

        $product_ids = self::collect_product_ids($args);
        $payload = [];

        if ($include_categories) {
            $category_group = self::build_taxonomy_filter_group(
                'product_cat',
                __('Categories', 'lime-filters'),
                $product_ids,
                $selected_filters,
                $show_counts
            );
            if (!empty($category_group)) {
                $payload[] = $category_group;
            }
        }

        foreach ($attributes as $taxonomy) {
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }
            $label = wc_attribute_label($taxonomy);
            if ($label === '') {
                $label = ucwords(str_replace(['pa_', '_', '-'], ['', ' ', ' '], $taxonomy));
            }
            $group = self::build_taxonomy_filter_group(
                $taxonomy,
                $label,
                $product_ids,
                $selected_filters,
                $show_counts
            );
            if (!empty($group)) {
                $payload[] = $group;
            }
        }

        return $payload;
    }

    protected static function collect_product_ids($args)
    {
        $ids_args = $args;
        $ids_args['posts_per_page'] = -1;
        $ids_args['paged'] = 1;
        $ids_args['fields'] = 'ids';
        $ids_args['no_found_rows'] = true;
        $ids_args['update_post_meta_cache'] = false;
        $ids_args['update_post_term_cache'] = false;

        $ids_query = new WP_Query($ids_args);
        $ids = $ids_query->posts;
        wp_reset_postdata();

        return $ids;
    }

    protected static function build_taxonomy_filter_group($taxonomy, $label, $product_ids, $selected_filters, $show_counts)
    {
        $selected_slugs = isset($selected_filters[$taxonomy]) ? (array) $selected_filters[$taxonomy] : [];
        $selected_slugs = array_values(array_unique(array_map('sanitize_text_field', $selected_slugs)));

        $counts = self::term_counts_for_products($taxonomy, $product_ids);

        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);
        if (is_wp_error($terms)) {
            $terms = [];
        }

        $terms_by_slug = [];
        foreach ($terms as $term) {
            $terms_by_slug[$term->slug] = $term;
        }

        foreach ($selected_slugs as $slug) {
            if (!isset($terms_by_slug[$slug])) {
                $term = get_term_by('slug', $slug, $taxonomy);
                if ($term && !is_wp_error($term)) {
                    $terms_by_slug[$term->slug] = $term;
                }
            }
        }

        if (empty($terms_by_slug)) {
            return [];
        }

        uasort($terms_by_slug, function ($a, $b) {
            return strcasecmp($a->name, $b->name);
        });

        $terms_payload = [];
        foreach ($terms_by_slug as $slug => $term) {
            $count = isset($counts[$term->term_id]) ? (int) $counts[$term->term_id] : 0;
            $checked = in_array($slug, $selected_slugs, true);

            if ($count === 0 && !$checked) {
                continue;
            }

            $terms_payload[] = [
                'slug'    => $term->slug,
                'name'    => $term->name,
                'count'   => $count,
                'checked' => $checked,
            ];
        }

        if (empty($terms_payload)) {
            return [];
        }

        return [
            'taxonomy' => $taxonomy,
            'label'    => $label,
            'type'     => $taxonomy === 'product_cat' ? 'category' : 'attribute',
            'terms'    => $terms_payload,
            'show_counts' => $show_counts,
        ];
    }

    protected static function term_counts_for_products($taxonomy, $product_ids)
    {
        $counts = [];
        if (empty($product_ids)) {
            return $counts;
        }

        $terms = wp_get_object_terms($product_ids, $taxonomy, [
            'fields' => 'all_with_object_id',
        ]);

        if (is_wp_error($terms)) {
            return $counts;
        }

        foreach ($terms as $term) {
            if (!isset($counts[$term->term_id])) {
                $counts[$term->term_id] = 0;
            }
            $counts[$term->term_id]++;
        }

        return $counts;
    }

    protected static function sanitize_columns_request($columns)
    {
        $defaults = ['desktop' => 0, 'tablet' => 0, 'mobile' => 0];
        if (!is_array($columns)) {
            return $defaults;
        }

        foreach ($defaults as $key => $value) {
            if (isset($columns[$key])) {
                $int = (int) $columns[$key];
                $defaults[$key] = $int > 0 ? min($int, 6) : 0;
            }
        }

        return $defaults;
    }

    protected static function resolve_columns_config($columns, $category = '')
    {
        $desktop_default = self::grid_columns();
        $tablet_default  = (int) apply_filters('lime_filters_default_columns_tablet', max(1, min(3, $desktop_default)), $category);
        $mobile_default  = (int) apply_filters('lime_filters_default_columns_mobile', 1, $category);

        $resolved = [
            'desktop' => $desktop_default,
            'tablet'  => $tablet_default,
            'mobile'  => $mobile_default,
        ];

        foreach ($resolved as $key => $default) {
            if (isset($columns[$key]) && (int)$columns[$key] > 0) {
                $resolved[$key] = max(1, min(6, (int) $columns[$key]));
            }
        }

        return $resolved;
    }

    protected static function render_pagination($query, $current_page)
    {
        $total_pages = (int) $query->max_num_pages;
        if ($total_pages <= 1) {
            return '';
        }

        $current_page = max(1, (int) $current_page);
        $buttons = [];

        for ($i = 1; $i <= $total_pages; $i++) {
            $classes = 'lf-page';
            if ($i === $current_page) {
                $classes .= ' is-active';
            }
            $buttons[] = sprintf(
                '<button type="button" class="%s" data-page="%d">%s</button>',
                esc_attr($classes),
                (int) $i,
                esc_html($i)
            );
        }

        return sprintf(
            '<nav class="lf-pagination__nav" aria-label="%s">%s</nav>',
            esc_attr__('Pagination', 'lime-filters'),
            implode('', $buttons)
        );
    }

    protected static function get_compare_button($product)
    {
        if (!class_exists('LF_Product_Compare') || !LF_Product_Compare::is_enabled()) {
            return '';
        }

        if (!$product instanceof WC_Product) {
            return '';
        }

        $button = LF_Product_Compare::button_markup($product);
        if ($button === '') {
            return '';
        }

        $button = preg_replace(
            '/class="([^"]*)"/',
            'class="$1 lf-button lf-button--ghost"',
            $button,
            1,
            $count
        );

        if ($count === 0) {
            $button = str_replace('<button ', '<button class="lf-button lf-button--ghost" ', $button);
        }

        return $button;
    }
}
