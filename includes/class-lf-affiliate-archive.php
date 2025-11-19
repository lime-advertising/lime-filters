<?php
if (!defined('ABSPATH')) {
    exit;
}

class LF_Affiliate_Archive {
    const OPTION_KEY   = 'lime_filters_affiliate_archive';
    const OPTION_GROUP = 'lime_filters_affiliate_archive_group';
    const MENU_SLUG    = 'lime-filters-affiliate-archive';
    const QV           = 'lf_affiliate_store';
    const SHORTCODE    = 'lf_affiliate_products';
    const QV_CATEGORY  = 'lf_affiliate_cat';

    protected static $shortcode_used = false;

    public static function init() {
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('update_option_' . self::OPTION_KEY, [__CLASS__, 'maybe_flush_rewrite'], 10, 2);

        register_activation_hook(LF_PLUGIN_FILE, [__CLASS__, 'activate']);

        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_shortcode('affiliate_products', [__CLASS__, 'shortcode']); // legacy alias

        if (!self::is_enabled()) {
            return;
        }

        add_action('init', [__CLASS__, 'add_rewrite']);
        add_filter('query_vars', [__CLASS__, 'add_query_var']);
        add_action('parse_query', [__CLASS__, 'mark_main_query']);
        add_action('template_redirect', [__CLASS__, 'render_if_affiliate']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_styles']);
    }

    public static function register_settings() {
        register_setting(
            self::OPTION_GROUP,
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
            __('Affiliate Archive', 'lime-filters'),
            __('Affiliate Archive', 'lime-filters'),
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
            <h1><?php esc_html_e('Affiliate Archive', 'lime-filters'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields(self::OPTION_GROUP); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable archive', 'lime-filters'); ?></th>
                            <td>
                                <label>
                                    <input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enabled]" value="no" />
                                    <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enabled]" value="yes" <?php checked($settings['enabled'], 'yes'); ?> />
                                    <?php esc_html_e('Allow visitors to browse products per affiliate store.', 'lime-filters'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="lf-affiliate-slug"><?php esc_html_e('Archive slug', 'lime-filters'); ?></label>
                            </th>
                            <td>
                                <input type="text" class="regular-text" id="lf-affiliate-slug" name="<?php echo esc_attr(self::OPTION_KEY); ?>[slug]" value="<?php echo esc_attr($settings['slug']); ?>" placeholder="affiliate" />
                                <p class="description">
                                    <?php esc_html_e('Used for URLs such as /affiliate/amazon/.', 'lime-filters'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="lf-affiliate-per-page"><?php esc_html_e('Products per page', 'lime-filters'); ?></label>
                            </th>
                            <td>
                                <input type="number" min="1" class="small-text" id="lf-affiliate-per-page" name="<?php echo esc_attr(self::OPTION_KEY); ?>[per_page]" value="<?php echo esc_attr($settings['per_page']); ?>" />
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public static function sanitize_options($input) {
        $defaults = self::defaults();
        $output   = $defaults;

        if (isset($input['enabled']) && $input['enabled'] === 'yes') {
            $output['enabled'] = 'yes';
        }

        if (!empty($input['slug'])) {
            $slug = sanitize_title_with_dashes($input['slug']);
            if ($slug !== '') {
                $output['slug'] = $slug;
            }
        }

        if (isset($input['per_page'])) {
            $per_page = absint($input['per_page']);
            if ($per_page > 0) {
                $output['per_page'] = $per_page;
            }
        }

        return $output;
    }

    public static function activate() {
        if (!self::is_enabled()) {
            return;
        }
        self::add_rewrite();
        flush_rewrite_rules();
    }

    public static function maybe_flush_rewrite($old_value, $value) {
        $old = wp_parse_args(is_array($old_value) ? $old_value : [], self::defaults());
        $new = wp_parse_args(is_array($value) ? $value : [], self::defaults());

        if ($old['slug'] !== $new['slug'] || $old['enabled'] !== $new['enabled']) {
            if ($new['enabled'] === 'yes') {
                self::add_rewrite();
            }
            flush_rewrite_rules();
        }
    }

    public static function add_rewrite() {
        if (!self::is_enabled()) {
            return;
        }

        $base = self::base_slug();
        if ($base === '') {
            return;
        }

        add_rewrite_tag('%' . self::QV . '%', '([^&]+)');
        add_rewrite_tag('%' . self::QV_CATEGORY . '%', '([^&]+)');

        $base_regex = preg_quote($base, '/');
        add_rewrite_rule('^' . $base_regex . '/([^/]+)/?$', 'index.php?' . self::QV . '=$matches[1]', 'top');
        add_rewrite_rule('^' . $base_regex . '/([^/]+)/page/([0-9]+)/?$', 'index.php?' . self::QV . '=$matches[1]&paged=$matches[2]', 'top');
    }

    public static function add_query_var($vars) {
        $vars[] = self::QV;
        $vars[] = self::QV_CATEGORY;
        return $vars;
    }

    public static function mark_main_query($query) {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }
        if ($query->get(self::QV)) {
            $query->is_home    = false;
            $query->is_archive = true;
            $query->is_404     = false;
        }
    }

    public static function render_if_affiliate() {
        $slug = get_query_var(self::QV);
        if (!$slug) {
            return;
        }

        $slug = sanitize_title($slug);
        global $wp_query;
        if ($wp_query instanceof WP_Query) {
            $wp_query->is_home    = false;
            $wp_query->is_archive = true;
            $wp_query->is_404     = false;
        }
        status_header(200);

        $output = self::render_archive($slug);

        get_header();
        echo '<main class="site-main">';
        echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</main>';
        get_footer();
        exit;
    }

    public static function shortcode($atts) {
        $atts = shortcode_atts([
            'store' => '',
            'category' => '',
        ], $atts, self::SHORTCODE);

        if (!self::is_enabled()) {
            return '';
        }

        $slug = sanitize_title($atts['store']);
        if ($slug === '') {
            return '';
        }

        $category = isset($atts['category']) ? sanitize_title($atts['category']) : '';

        self::$shortcode_used = true;
        return self::render_archive($slug, [
            'category' => $category,
        ]);
    }

    public static function enqueue_styles() {
        if (!self::$shortcode_used && !self::is_affiliate_request()) {
            return;
        }

        $colors = LF_Helpers::colors();
        $accent = $colors['accent'];
        $border = $colors['border'];
        $text   = $colors['text'];
        $css = "
        .lf-affiliate-archive {max-width:1720px;margin:0 auto;padding:0 40px;}
        .lf-affiliate-archive h1 {margin:0 0 24px;font-size:28px;line-height:1.2;color:#fff;}
        .lf-affiliate-archive .lf-affiliate-filter {display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin:0 0 24px;padding:16px;border:1px solid {$border};border-radius:12px;box-shadow:0 10px 30px rgba(15,28,50,0.08);background-color:#2B2B2B;background-image:url('https://kucht.ca/wp-content/uploads/2024/05/body-bg.png');background-size:cover;background-position:center;color:#fff;}
        .lf-affiliate-archive .lf-affiliate-filter label {display:block;font-weight:600;margin-bottom:4px;color:#fff;}
        .lf-affiliate-archive .lf-affiliate-filter .lf-affiliate-filter__field {flex:1;min-width:220px;}
        .lf-affiliate-archive .lf-affiliate-filter .lf-affiliate-filter__control {position:relative;}
        .lf-affiliate-archive .lf-affiliate-filter select {appearance:none;-webkit-appearance:none;min-width:220px;border-radius:999px;padding:10px 48px 10px 16px;border:1px solid {$border};box-shadow:none;background:#fff;color:#111;width:100%;}
        .lf-affiliate-archive .lf-affiliate-filter .lf-affiliate-filter__control #select2-lf-affiliate-cat-select-container {padding-top:0;padding-bottom:0;}
        .lf-affiliate-archive .lf-affiliate-filter .lf-affiliate-filter__arrow {position:absolute;right:16px;top:50%;transform:translateY(-50%);pointer-events:none;color:#666;}
        .lf-affiliate-archive .lf-affiliate-filter .button.button-secondary {background:{$accent};border-color:{$accent};color:#fff;border-radius:999px;padding:10px 28px;font-weight:600;}
        .lf-affiliate-archive .lf-affiliate-filter .button.button-secondary:hover,
        .lf-affiliate-archive .lf-affiliate-filter .button.button-secondary:focus {background:#fff;color:{$accent};}
        .lf-affiliate-archive .lf-affiliate-filter .button-link {padding:0;border:0;background:none;color:{$accent};cursor:pointer;font-weight:600;text-decoration:none;}
        .lf-affiliate-archive .lf-affiliate-products {margin-top:24px;}
        .lf-affiliate-archive .lf-affiliate-pagination {margin:24px 0;display:flex;gap:8px;flex-wrap:wrap;}
        .lf-affiliate-archive .lf-affiliate-pagination a,
        .lf-affiliate-archive .lf-affiliate-pagination span {padding:8px 12px;border:1px solid #ddd;border-radius:8px;text-decoration:none}
        .lf-affiliate-archive .lf-affiliate-empty {padding:24px;border:1px dashed #ddd;border-radius:12px;background:#fcfcfc;text-align:center;}
        ";

        wp_register_style('lf-affiliate-archive', false);
        wp_enqueue_style('lf-affiliate-archive');
        wp_add_inline_style('lf-affiliate-archive', $css);
    }

    protected static function render_archive($slug, $args = []) {
        $args = wp_parse_args($args, [
            'category' => '',
        ]);

        $store = self::get_store($slug);
        if (!$store) {
            return self::render_unknown_store();
        }

        $meta_key = $store['meta'];
        $label    = $store['label'];
        $per_page = self::per_page();
        $paged    = max(1, get_query_var('paged') ?: 1);
        $selected_category = $args['category'] !== '' ? $args['category'] : self::current_category_slug();

        $query_args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => $meta_key, 'compare' => 'EXISTS'],
                ['key' => $meta_key, 'value' => '', 'compare' => '!='],
            ],
        ];

        if ($selected_category !== '') {
            $query_args['tax_query'] = [
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => $selected_category,
                ],
            ];
        }

        $query = new WP_Query($query_args);

        if ($selected_category !== '') {
            $query->set('tax_query', [
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => $selected_category,
                ],
            ]);
        }

        wp_enqueue_style('lime-filters');

        ob_start();
        ?>
        <div class="lf-affiliate-archive">
            <h1><?php echo esc_html(sprintf(__('Products available on %s', 'lime-filters'), $label)); ?></h1>
            <?php
            $categories = self::category_terms();
            if (!empty($categories)) :
                $action_url = self::archive_url($slug);
                ?>
                <form class="lf-affiliate-filter" method="get" action="<?php echo esc_url($action_url); ?>">
                    <div class="lf-affiliate-filter__field">
                        <label for="lf-affiliate-cat-select"><?php esc_html_e('Category', 'lime-filters'); ?></label>
                        <div class="lf-affiliate-filter__control">
                            <select id="lf-affiliate-cat-select" name="lf_affiliate_cat">
                                <option value=""><?php esc_html_e('All categories', 'lime-filters'); ?></option>
                                <?php foreach ($categories as $term) : ?>
                                    <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($selected_category, $term->slug); ?>>
                                        <?php echo esc_html($term->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="lf-affiliate-filter__arrow" aria-hidden="true">⌄</span>
                        </div>
                    </div>
                    <button type="submit" class="button button-secondary"><?php esc_html_e('Apply', 'lime-filters'); ?></button>
                    <?php if ($selected_category !== '') : ?>
                        <a class="button-link" href="<?php echo esc_url($action_url); ?>"><?php esc_html_e('Reset', 'lime-filters'); ?></a>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
            <?php if ($query->have_posts()) : ?>
                <?php
                $columns = [
                    'desktop' => 4,
                    'tablet'  => 2,
                    'mobile'  => 1,
                ];
                ?>
                <div class="lf-products lf-affiliate-products" data-columns="<?php echo esc_attr($columns['desktop']); ?>"
                    style="--lf-col-desktop:<?php echo esc_attr($columns['desktop']); ?>;
                           --lf-col-tablet:<?php echo esc_attr($columns['tablet']); ?>;
                           --lf-col-mobile:<?php echo esc_attr($columns['mobile']); ?>;">
                    <?php
                    while ($query->have_posts()) :
                        $query->the_post();
                        $product = function_exists('wc_get_product') ? wc_get_product(get_the_ID()) : null;
                        $card_html = '';
                        if ($product instanceof WC_Product && class_exists('LF_AJAX') && method_exists('LF_AJAX', 'render_product_card')) {
                            $card_html = LF_AJAX::render_product_card($product);
                        } else {
                            $placeholder = method_exists('LF_Helpers', 'placeholder_image_url') ? LF_Helpers::placeholder_image_url() : includes_url('images/media/default.png');
                            $card_html   = sprintf(
                                '<article class="lf-product"><a class="lf-product__thumb" href="%1$s"><img src="%2$s" alt="" /></a><div class="lf-product__body"><h3 class="lf-product__title"><a href="%1$s">%3$s</a></h3></div></article>',
                                esc_url(get_permalink()),
                                esc_url($placeholder),
                                esc_html(get_the_title())
                            );
                        }
                        echo $card_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        ?>
                    <?php endwhile; ?>
                </div>
                <?php
                $links = paginate_links([
                    'base'      => user_trailingslashit(home_url(trailingslashit(self::base_slug()) . trailingslashit($slug) . 'page/%#%')),
                    'format'    => '',
                    'current'   => $paged,
                    'total'     => max(1, (int) $query->max_num_pages),
                    'type'      => 'array',
                    'prev_text' => __('« Prev', 'lime-filters'),
                    'next_text' => __('Next »', 'lime-filters'),
                    'add_args'  => $selected_category !== '' ? ['lf_affiliate_cat' => $selected_category] : [],
                ]);
                if (!empty($links)) : ?>
                    <nav class="lf-affiliate-pagination" aria-label="<?php esc_attr_e('Affiliate archive pagination', 'lime-filters'); ?>">
                        <?php foreach ($links as $link) : ?>
                            <?php echo wp_kses_post($link); ?>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>
            <?php else : ?>
                <div class="lf-affiliate-empty">
                    <strong><?php esc_html_e('No products found.', 'lime-filters'); ?></strong>
                    <p><?php esc_html_e('Try a different retailer.', 'lime-filters'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }

    protected static function archive_url($store_slug) {
        return home_url(trailingslashit(self::base_slug()) . trailingslashit($store_slug));
    }

    protected static function render_unknown_store() {
        status_header(404);
        $stores = array_map(function ($meta) {
            return isset($meta['label']) ? $meta['label'] : '';
        }, self::stores());

        return sprintf(
            '<div class="lf-affiliate-archive"><h1>%s</h1><p>%s</p></div>',
            esc_html__('Retailer not found', 'lime-filters'),
            esc_html(sprintf(__('Try one of: %s.', 'lime-filters'), implode(', ', $stores)))
        );
    }

    protected static function stores() {
        $defaults = [
            'amazon'     => ['meta' => 'amazon', 'label' => __('Amazon', 'lime-filters')],
            'best-buy'   => ['meta' => 'best_buy', 'label' => __('Best Buy', 'lime-filters')],
            'home-depot' => ['meta' => 'the_home_depot', 'label' => __('The Home Depot', 'lime-filters')],
            'rona'       => ['meta' => 'rona', 'label' => __('RONA', 'lime-filters')],
            'wayfair'    => ['meta' => 'wayfair', 'label' => __('Wayfair', 'lime-filters')],
            'walmart'    => ['meta' => 'walmart', 'label' => __('Walmart', 'lime-filters')],
        ];

        return apply_filters('lime_filters_affiliate_archive_stores', $defaults);
    }

    protected static function get_store($slug) {
        $stores = self::stores();
        return $stores[$slug] ?? null;
    }

    protected static function category_terms() {
        $terms = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC',
            'parent'     => 0,
        ]);
        if (is_wp_error($terms)) {
            return [];
        }
        return $terms;
    }

    protected static function settings() {
        $option = get_option(self::OPTION_KEY, []);
        if (!is_array($option)) {
            $option = [];
        }
        return wp_parse_args($option, self::defaults());
    }

    protected static function defaults() {
        return [
            'enabled'  => 'no',
            'slug'     => 'affiliate',
            'per_page' => 24,
        ];
    }

    protected static function is_enabled() {
        $settings = self::settings();
        return isset($settings['enabled']) && $settings['enabled'] === 'yes';
    }

    protected static function base_slug() {
        $settings = self::settings();
        $slug = isset($settings['slug']) ? sanitize_title_with_dashes($settings['slug']) : 'affiliate';
        return $slug !== '' ? $slug : 'affiliate';
    }

    protected static function per_page() {
        $settings = self::settings();
        $per_page = isset($settings['per_page']) ? absint($settings['per_page']) : 24;
        return max(1, $per_page);
    }

    protected static function is_affiliate_request() {
        return (bool) get_query_var(self::QV);
    }

    protected static function current_category_slug() {
        $slug = get_query_var(self::QV_CATEGORY);
        if (!$slug && isset($_GET['lf_affiliate_cat'])) {
            $slug = wp_unslash($_GET['lf_affiliate_cat']);
        }
        if (!$slug && isset($_GET['category'])) {
            $slug = wp_unslash($_GET['category']);
        }
        return sanitize_title($slug);
    }
}
