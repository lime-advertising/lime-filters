<?php
if (!defined('ABSPATH')) {
    exit;
}

class LF_Affiliate_Vendors {
    const OPTION_KEY   = 'lime_filters_affiliate_vendors';
    const OPTION_GROUP = 'lime_filters_affiliate_vendors_group';
    const MENU_SLUG    = 'lime-filters-affiliate-vendors';

    public static function init() {
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function register_settings() {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [__CLASS__, 'sanitize'],
                'default'           => self::defaults(),
            ]
        );
    }

    public static function register_menu() {
        add_submenu_page(
            'woocommerce',
            __('Affiliate Vendors', 'lime-filters'),
            __('Affiliate Vendors', 'lime-filters'),
            'manage_woocommerce',
            self::MENU_SLUG,
            [__CLASS__, 'render_page']
        );
    }

    public static function enqueue_assets($hook) {
        if ($hook !== 'woocommerce_page_' . self::MENU_SLUG) {
            return;
        }

        wp_enqueue_style(
            'lf-affiliate-vendors-admin',
            LF_PLUGIN_URL . 'includes/assets/admin/lime-filters-vendors.css',
            [],
            LF_VERSION
        );

        wp_enqueue_script(
            'lf-affiliate-vendors-admin',
            LF_PLUGIN_URL . 'includes/assets/admin/lime-filters-vendors.js',
            ['jquery'],
            LF_VERSION,
            true
        );
    }

    public static function render_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $vendors = self::vendors_for_display();
        ?>
        <div class="wrap" id="lf-affiliate-vendors">
            <h1><?php esc_html_e('Affiliate Vendors', 'lime-filters'); ?></h1>
            <p class="description">
                <?php esc_html_e('Manage the retailers and meta keys used across Lime Filters (affiliate widgets, archive pages, Elementor blocks, etc.).', 'lime-filters'); ?>
            </p>

            <form method="post" action="options.php">
                <?php settings_fields(self::OPTION_GROUP); ?>
                <table class="widefat lf-affiliate-vendors__table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Slug', 'lime-filters'); ?></th>
                            <th><?php esc_html_e('Meta Key', 'lime-filters'); ?></th>
                            <th><?php esc_html_e('Label', 'lime-filters'); ?></th>
                            <th><?php esc_html_e('Logo URL (optional)', 'lime-filters'); ?></th>
                            <th>&nbsp;</th>
                        </tr>
                    </thead>
                    <tbody id="lf-affiliate-vendors-rows">
                        <?php foreach ($vendors as $index => $vendor) : ?>
                            <?php self::render_row($index, $vendor); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p>
                    <button type="button" class="button" id="lf-add-vendor"><?php esc_html_e('Add Vendor', 'lime-filters'); ?></button>
                </p>

                <?php submit_button(); ?>
            </form>

            <script type="text/html" id="tmpl-lf-affiliate-vendor-row">
                <?php self::render_row('__index__', [
                    'slug'  => '',
                    'meta'  => '',
                    'label' => '',
                    'logo'  => '',
                ]); ?>
            </script>
        </div>
        <?php
    }

    protected static function render_row($index, $vendor) {
        $name = esc_attr(self::OPTION_KEY);
        $slug  = isset($vendor['slug']) ? $vendor['slug'] : '';
        $meta  = isset($vendor['meta']) ? $vendor['meta'] : '';
        $label = isset($vendor['label']) ? $vendor['label'] : '';
        $logo  = isset($vendor['logo']) ? $vendor['logo'] : '';
        ?>
        <tr class="lf-affiliate-vendors__row">
            <td>
                <label class="screen-reader-text" for="lf-vendor-slug-<?php echo esc_attr($index); ?>"><?php esc_html_e('Slug', 'lime-filters'); ?></label>
                <input type="text"
                    id="lf-vendor-slug-<?php echo esc_attr($index); ?>"
                    name="<?php echo $name; ?>[<?php echo esc_attr($index); ?>][slug]"
                    value="<?php echo esc_attr($slug); ?>"
                    placeholder="<?php esc_attr_e('e.g. amazon', 'lime-filters'); ?>" />
                <p class="description"><?php esc_html_e('Used in URLs & identifiers (lowercase, no spaces).', 'lime-filters'); ?></p>
            </td>
            <td>
                <label class="screen-reader-text" for="lf-vendor-meta-<?php echo esc_attr($index); ?>"><?php esc_html_e('Meta Key', 'lime-filters'); ?></label>
                <input type="text"
                    id="lf-vendor-meta-<?php echo esc_attr($index); ?>"
                    name="<?php echo $name; ?>[<?php echo esc_attr($index); ?>][meta]"
                    value="<?php echo esc_attr($meta); ?>"
                    placeholder="<?php esc_attr_e('e.g. amazon', 'lime-filters'); ?>" />
                <p class="description"><?php esc_html_e('Custom field storing the affiliate URL (ACF/meta key).', 'lime-filters'); ?></p>
            </td>
            <td>
                <label class="screen-reader-text" for="lf-vendor-label-<?php echo esc_attr($index); ?>"><?php esc_html_e('Label', 'lime-filters'); ?></label>
                <input type="text"
                    id="lf-vendor-label-<?php echo esc_attr($index); ?>"
                    name="<?php echo $name; ?>[<?php echo esc_attr($index); ?>][label]"
                    value="<?php echo esc_attr($label); ?>"
                    placeholder="<?php esc_attr_e('Vendor name', 'lime-filters'); ?>" />
            </td>
            <td>
                <label class="screen-reader-text" for="lf-vendor-logo-<?php echo esc_attr($index); ?>"><?php esc_html_e('Logo URL', 'lime-filters'); ?></label>
                <input type="url"
                    id="lf-vendor-logo-<?php echo esc_attr($index); ?>"
                    name="<?php echo $name; ?>[<?php echo esc_attr($index); ?>][logo]"
                    value="<?php echo esc_url($logo); ?>"
                    placeholder="<?php esc_attr_e('https://example.com/logo.svg', 'lime-filters'); ?>" />
            </td>
            <td class="lf-affiliate-vendors__actions">
                <button type="button" class="button-link-delete" data-remove-row><?php esc_html_e('Remove', 'lime-filters'); ?></button>
            </td>
        </tr>
        <?php
    }

    protected static function vendors_for_display() {
        $list = get_option(self::OPTION_KEY, []);
        if (!is_array($list) || empty($list)) {
            return self::defaults_list();
        }

        return self::normalize_list($list);
    }

    public static function sanitize($input) {
        if (!is_array($input)) {
            return self::defaults_list();
        }

        $sanitized = [];
        $seen = [];
        foreach ($input as $row) {
            if (!is_array($row)) {
                continue;
            }
            $slug  = isset($row['slug']) ? sanitize_title_with_dashes($row['slug']) : '';
            $meta  = isset($row['meta']) ? sanitize_key($row['meta']) : '';
            $label = isset($row['label']) ? sanitize_text_field($row['label']) : '';
            $logo  = isset($row['logo']) ? esc_url_raw($row['logo']) : '';

            if ($slug === '' || $meta === '' || $label === '' || isset($seen[$slug])) {
                continue;
            }

            $seen[$slug] = true;
            $sanitized[] = [
                'slug'  => $slug,
                'meta'  => $meta,
                'label' => $label,
                'logo'  => $logo,
            ];
        }

        if (empty($sanitized)) {
            return self::defaults_list();
        }

        return $sanitized;
    }

    public static function vendors() {
        $list = get_option(self::OPTION_KEY, []);
        if (!is_array($list) || empty($list)) {
            $list = self::defaults_list();
        } else {
            $list = self::normalize_list($list);
        }

        $vendors = [];
        foreach ($list as $vendor) {
            if (!is_array($vendor)) {
                continue;
            }
            $slug = isset($vendor['slug']) ? sanitize_title_with_dashes($vendor['slug']) : '';
            if ($slug === '' || isset($vendors[$slug])) {
                continue;
            }
            $vendors[$slug] = [
                'label' => isset($vendor['label']) ? $vendor['label'] : $slug,
                'meta'  => isset($vendor['meta']) ? sanitize_key($vendor['meta']) : $slug,
                'logo'  => isset($vendor['logo']) ? esc_url($vendor['logo']) : '',
            ];
        }

        if (empty($vendors)) {
            $vendors = self::defaults();
        }

        return apply_filters('lime_filters_affiliate_vendors', $vendors);
    }

    public static function defaults() {
        $base = LF_PLUGIN_URL . 'includes/compare/icons/compare-icons/';

        return [
            'amazon' => [
                'label' => __('Amazon', 'lime-filters'),
                'meta'  => 'amazon',
                'logo'  => $base . 'amazon-ico.svg',
            ],
            'best-buy' => [
                'label' => __('Best Buy', 'lime-filters'),
                'meta'  => 'best_buy',
                'logo'  => $base . 'best_buy-ico.svg',
            ],
            'home-depot' => [
                'label' => __('The Home Depot', 'lime-filters'),
                'meta'  => 'the_home_depot',
                'logo'  => $base . 'the_home_depot-ico.svg',
            ],
            'rona' => [
                'label' => __('RONA', 'lime-filters'),
                'meta'  => 'rona',
                'logo'  => $base . 'rona-ico.svg',
            ],
            'wayfair' => [
                'label' => __('Wayfair', 'lime-filters'),
                'meta'  => 'wayfair',
                'logo'  => $base . 'wayfair-ico.svg',
            ],
            'walmart' => [
                'label' => __('Walmart', 'lime-filters'),
                'meta'  => 'walmart',
                'logo'  => $base . 'walmart-ico.svg',
            ],
        ];
    }

    protected static function defaults_list() {
        $list = [];
        foreach (self::defaults() as $slug => $data) {
            $list[] = [
                'slug'  => $slug,
                'meta'  => $data['meta'],
                'label' => $data['label'],
                'logo'  => isset($data['logo']) ? $data['logo'] : '',
            ];
        }
        return $list;
    }

    protected static function normalize_list(array $list) {
        $normalized = [];
        foreach ($list as $key => $row) {
            if (!is_array($row)) {
                continue;
            }

            $slug = '';
            if (isset($row['slug'])) {
                $slug = sanitize_title_with_dashes($row['slug']);
            } elseif (is_string($key)) {
                $slug = sanitize_title_with_dashes($key);
            }

            $meta  = isset($row['meta']) ? sanitize_key($row['meta']) : '';
            $label = isset($row['label']) ? sanitize_text_field($row['label']) : '';
            $logo  = isset($row['logo']) ? esc_url_raw($row['logo']) : '';

            if ($slug === '' || $meta === '' || $label === '') {
                continue;
            }

            $normalized[] = [
                'slug'  => $slug,
                'meta'  => $meta,
                'label' => $label,
                'logo'  => $logo,
            ];
        }

        if (empty($normalized)) {
            return self::defaults_list();
        }

        return $normalized;
    }
}
