<?php
if (!defined('ABSPATH')) {
    exit;
}

class LF_Wishlist {
    const OPTION_ENABLED = 'lime_filters_wishlist_enabled';
    const OPTION_PAGE    = 'lime_filters_wishlist_page';
    const USER_META_KEY  = 'lf_wishlist_items';
    const COOKIE_KEY     = 'lf_wishlist';
    const NONCE_ACTION   = 'lf_wishlist_toggle';
    const COOKIE_LIFETIME = 2592000; // 30 days

    public static function init() {
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_menu', [__CLASS__, 'register_menu']);

        if (!self::is_enabled()) {
            return;
        }

        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_ajax_lf_toggle_wishlist', [__CLASS__, 'ajax_toggle']);
        add_action('wp_ajax_nopriv_lf_toggle_wishlist', [__CLASS__, 'ajax_toggle']);
        add_action('wp_login', [__CLASS__, 'merge_cookie_on_login'], 10, 2);

        add_shortcode('lf_wishlist', [__CLASS__, 'shortcode_wishlist']);
    }

    public static function register_settings() {
        register_setting('lime_filters_wishlist_group', self::OPTION_ENABLED, [
            'type'              => 'boolean',
            'sanitize_callback' => function($value) {
                return $value === 'yes' ? 'yes' : 'no';
            },
            'default'           => 'no',
        ]);

        register_setting('lime_filters_wishlist_group', self::OPTION_PAGE, [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 0,
        ]);
    }

    public static function register_menu() {
        add_submenu_page(
            'woocommerce',
            __('Wishlist', 'lime-filters'),
            __('Wishlist', 'lime-filters'),
            'manage_woocommerce',
            'lime-filters-wishlist',
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function render_settings_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $enabled = get_option(self::OPTION_ENABLED, 'no');
        $page_id = get_option(self::OPTION_PAGE, 0);

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Wishlist Settings', 'lime-filters'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('lime_filters_wishlist_group'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Wishlist', 'lime-filters'); ?></th>
                        <td>
                            <label>
                                <input type="hidden" name="<?php echo esc_attr(self::OPTION_ENABLED); ?>" value="no" />
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_ENABLED); ?>" value="yes" <?php checked($enabled, 'yes'); ?> />
                                <?php esc_html_e('Allow customers to save products to a wishlist.', 'lime-filters'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Wishlist Page', 'lime-filters'); ?></th>
                        <td>
                            <?php
                            wp_dropdown_pages([
                                'name'              => self::OPTION_PAGE,
                                'selected'          => $page_id,
                                'show_option_none'  => __('Select a page', 'lime-filters'),
                                'option_none_value' => 0,
                            ]);
                            ?>
                            <p class="description"><?php esc_html_e('Select the page where the [lf_wishlist] shortcode is placed.', 'lime-filters'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public static function is_enabled() {
        return get_option(self::OPTION_ENABLED, 'no') === 'yes';
    }

    public static function get_page_url() {
        $page_id = absint(get_option(self::OPTION_PAGE, 0));
        return $page_id ? get_permalink($page_id) : '';
    }

    public static function enqueue_assets() {
        wp_enqueue_style('lime-filters');

        wp_enqueue_style(
            'lf-wishlist',
            LF_PLUGIN_URL . 'includes/assets/css/lf-wishlist.css',
            ['lime-filters'],
            LF_VERSION
        );

        wp_enqueue_script(
            'lf-wishlist',
            LF_PLUGIN_URL . 'includes/assets/js/lf-wishlist.js',
            ['jquery'],
            LF_VERSION,
            true
        );

        wp_localize_script('lf-wishlist', 'LimeFiltersWishlist', [
            'ajax'        => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce(self::NONCE_ACTION),
            'items'       => self::current_ids(),
            'wishlistUrl' => self::get_page_url(),
            'strings'     => [
                'added'   => __('Added to wishlist', 'lime-filters'),
                'removed' => __('Removed from wishlist', 'lime-filters'),
                'view'    => __('View Wishlist', 'lime-filters'),
            ],
            'labels'      => [
                'add'    => __('Add to Wishlist', 'lime-filters'),
                'remove' => __('Remove from Wishlist', 'lime-filters'),
            ],
        ]);
    }

    public static function ajax_toggle() {
        if (!self::is_enabled()) {
            wp_send_json_error(['message' => __('Wishlist is disabled.', 'lime-filters')]);
        }

        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        if (!$product_id || 'product' !== get_post_type($product_id)) {
            wp_send_json_error(['message' => __('Invalid product.', 'lime-filters')]);
        }

        $wishlist = self::current_ids();
        $action   = 'added';

        if (in_array($product_id, $wishlist, true)) {
            $wishlist = array_values(array_diff($wishlist, [$product_id]));
            $action   = 'removed';
        } else {
            $wishlist[] = $product_id;
        }

        self::save_ids($wishlist);

        wp_send_json_success([
            'action'  => $action,
            'items'   => $wishlist,
            'count'   => count($wishlist),
            'toast'   => self::toast_payload($action),
        ]);
    }

    protected static function toast_payload($action) {
        $url = self::get_page_url();
        $message = $action === 'added' ? __('Added to wishlist', 'lime-filters') : __('Removed from wishlist', 'lime-filters');

        return [
            'message' => $message,
            'url'     => $url,
        ];
    }

    public static function current_ids() {
        if (is_user_logged_in()) {
            return self::sanitize_ids((array) get_user_meta(get_current_user_id(), self::USER_META_KEY, true));
        }

        if (!empty($_COOKIE[self::COOKIE_KEY])) {
            $decoded = json_decode(stripslashes($_COOKIE[self::COOKIE_KEY]), true);
            if (is_array($decoded)) {
                return self::sanitize_ids($decoded);
            }
        }

        return [];
    }

    protected static function save_ids(array $ids) {
        $ids = self::sanitize_ids($ids);

        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), self::USER_META_KEY, $ids);
        } else {
            $encoded = wp_json_encode($ids);
            if (!headers_sent()) {
                setcookie(self::COOKIE_KEY, $encoded, time() + self::COOKIE_LIFETIME, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
            }
            $_COOKIE[self::COOKIE_KEY] = $encoded;
        }

        if (is_user_logged_in()) {
            if (!headers_sent()) {
                setcookie(self::COOKIE_KEY, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
            }
            unset($_COOKIE[self::COOKIE_KEY]);
        }
    }

    protected static function sanitize_ids($ids) {
        $clean = [];
        foreach ($ids as $id) {
            $id = absint($id);
            if ($id > 0) {
                $clean[] = $id;
            }
        }
        return array_values(array_unique($clean));
    }

    public static function merge_cookie_on_login($user_login, $user) {
        if (empty($_COOKIE[self::COOKIE_KEY])) {
            return;
        }

        $cookie_ids = self::sanitize_ids(json_decode(stripslashes($_COOKIE[self::COOKIE_KEY]), true));
        if (empty($cookie_ids)) {
            return;
        }

        $user_ids = self::sanitize_ids((array) get_user_meta($user->ID, self::USER_META_KEY, true));
        $merged = array_values(array_unique(array_merge($user_ids, $cookie_ids)));
        update_user_meta($user->ID, self::USER_META_KEY, $merged);

        if (!headers_sent()) {
            setcookie(self::COOKIE_KEY, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
        }
        unset($_COOKIE[self::COOKIE_KEY]);
    }

    public static function render_button($product) {
        if (!self::is_enabled()) {
            return '';
        }

        if (!$product instanceof WC_Product) {
            return '';
        }

        $product_id = $product->get_id();
        $items = self::current_ids();
        $is_active = in_array($product_id, $items, true);

        $classes = 'lf-wishlist-toggle lf-wishlist-toggle--icon' . ($is_active ? ' is-active' : '');
        $label_add = __('Add to Wishlist', 'lime-filters');
        $label_remove = __('Remove from Wishlist', 'lime-filters');
        $label = $is_active ? $label_remove : $label_add;

        $icon = '<span class="lf-wishlist-toggle__icon" aria-hidden="true"></span>';
        $sr   = sprintf('<span class="sr-only">%s</span>', esc_html($label));

        return sprintf(
            '<button type="button" class="%1$s" data-product-id="%2$d" aria-pressed="%3$s" aria-label="%4$s">%5$s%6$s</button>',
            esc_attr($classes),
            esc_attr($product_id),
            $is_active ? 'true' : 'false',
            esc_attr($label),
            $icon,
            $sr
        );
    }

    public static function shortcode_wishlist($atts = []) {
        if (!self::is_enabled()) {
            return '<div class="lf-wishlist lf-wishlist--disabled">' . esc_html__('Wishlist is currently disabled.', 'lime-filters') . '</div>';
        }

        $ids = self::current_ids();
        if (empty($ids)) {
            return '<div class="lf-wishlist lf-wishlist--empty">' . esc_html__('Your wishlist is empty.', 'lime-filters') . '</div>';
        }

        $ids = array_slice($ids, 0, 60);

        $query = new WP_Query([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 60,
            'post__in'       => $ids,
            'orderby'        => 'post__in',
        ]);

        if (!$query->have_posts()) {
            return '<div class="lf-wishlist lf-wishlist--empty">' . esc_html__('Your wishlist is empty.', 'lime-filters') . '</div>';
        }

        ob_start();

        echo '<div class="lf-wishlist lf-wishlist--list">';
        echo '<div class="lf-wishlist__grid">';

        while ($query->have_posts()) {
            $query->the_post();
            global $product;
            $product = wc_get_product(get_the_ID());
            if (!$product) {
                continue;
            }

            $card = LF_AJAX::render_product_card($product);
            if ($card) {
                echo $card;
            }
        }

        wp_reset_postdata();

        echo '</div>';
        echo '</div>';

        return ob_get_clean();
    }
}
