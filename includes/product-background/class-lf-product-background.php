<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class LF_Product_Background {
    const OPTION_KEY = 'lime_filters_product_bg_id';
    const MENU_SLUG  = 'lime-filters-product-bg';
    protected static $style_enqueued  = false;
    protected static $slider_enqueued = false;

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'register_front_assets']);
        add_action('init', [__CLASS__, 'register_shortcodes']);

        add_filter('woocommerce_get_product_thumbnail', [__CLASS__, 'filter_loop_thumbnail'], 10, 2);
        add_filter('woocommerce_single_product_image_thumbnail_html', [__CLASS__, 'filter_single_gallery'], 10, 2);
        add_filter('woocommerce_single_product_image_html', [__CLASS__, 'filter_single_main'], 10, 2);
    }

    public static function register_menu() {
        add_submenu_page(
            'woocommerce',
            __('Product Background', 'lime-filters'),
            __('Product Background', 'lime-filters'),
            'manage_woocommerce',
            self::MENU_SLUG,
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function register_settings() {
        register_setting(
            'lime_filters_product_background_group',
            self::OPTION_KEY,
            [
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'default'           => 0,
            ]
        );
    }

    public static function admin_assets($hook) {
        if ($hook !== 'woocommerce_page_' . self::MENU_SLUG) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_style(
            'lf-product-background-admin',
            LF_PLUGIN_URL . 'includes/product-background/product-background-admin.css',
            [],
            LF_VERSION
        );
        wp_enqueue_script(
            'lf-product-background-admin',
            LF_PLUGIN_URL . 'includes/product-background/product-background-admin.js',
            ['jquery'],
            LF_VERSION,
            true
        );
        wp_localize_script('lf-product-background-admin', 'LFProductBackground', [
            'defaultText' => __('No image selected', 'lime-filters'),
            'chooseText'  => __('Select background image', 'lime-filters'),
            'buttonText'  => __('Use this image', 'lime-filters'),
        ]);
    }

    public static function render_settings_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $attachment_id = absint(get_option(self::OPTION_KEY, 0));
        $custom_url    = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'large') : '';
        $default_url   = self::get_default_background_url();
        $preview_url   = $custom_url ?: $default_url;
        $remove_disabled = $attachment_id ? '' : 'disabled';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Product Background', 'lime-filters'); ?></h1>
            <p class="description"><?php esc_html_e('Set a common background image that will appear behind transparent product images across your store.', 'lime-filters'); ?></p>

            <form method="post" action="options.php">
                <?php settings_fields('lime_filters_product_background_group'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Background image', 'lime-filters'); ?>
                            </th>
                            <td>
                                <div id="lf-bg-preview"
                                     class="lf-bg-preview<?php echo $preview_url ? ' has-image' : ''; ?>"
                                     data-current="<?php echo esc_url($custom_url); ?>"
                                     data-default="<?php echo esc_url($default_url); ?>"
                                     style="<?php echo $preview_url ? 'background-image: url(' . esc_url($preview_url) . ');' : ''; ?>">
                                    <?php if (!$preview_url): ?>
                                        <span class="lf-bg-preview__placeholder"><?php esc_html_e('No image selected', 'lime-filters'); ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="lf-bg-actions">
                                    <input type="hidden" id="lime_filters_product_bg_id" name="<?php echo esc_attr(self::OPTION_KEY); ?>" value="<?php echo esc_attr($attachment_id); ?>" />
                                    <button type="button" class="button" id="lf-bg-upload"><?php esc_html_e('Choose image', 'lime-filters'); ?></button>
                                    <button type="button" class="button" id="lf-bg-remove" <?php echo esc_attr($remove_disabled); ?>><?php esc_html_e('Remove', 'lime-filters'); ?></button>
                                </div>
                                <p class="description">
                                    <?php esc_html_e('Upload a high-resolution background image. Transparent product photos will be layered over this image automatically.', 'lime-filters'); ?>
                                </p>
                                <?php if ($default_url): ?>
                                    <p class="description">
                                        <?php esc_html_e('Default image path:', 'lime-filters'); ?>
                                        <code><?php echo esc_html(self::get_default_background_path()); ?></code>
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public static function register_front_assets() {
        wp_register_style(
            'lf-product-background',
            LF_PLUGIN_URL . 'includes/product-background/product-background.css',
            [],
            LF_VERSION
        );

        if (!wp_style_is('lf-swiper', 'registered')) {
            wp_register_style(
                'lf-swiper',
                'https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css',
                [],
                '9.4.1'
            );
        }

        if (!wp_script_is('lf-swiper', 'registered')) {
            wp_register_script(
                'lf-swiper',
                'https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js',
                [],
                '9.4.1',
                true
            );
        }

        wp_register_script(
            'lf-product-background-slider',
            LF_PLUGIN_URL . 'includes/product-background/product-background-slider.js',
            [],
            LF_VERSION,
            true
        );
    }

    public static function register_shortcodes() {
        add_shortcode('lf_product_image', [__CLASS__, 'shortcode_product_image']);
        add_shortcode('lf_product_gallery', [__CLASS__, 'shortcode_product_gallery']);
    }

    public static function shortcode_product_image($atts) {
        $atts = shortcode_atts([
            'product'    => '',
            'attachment' => '',
            'size'       => 'woocommerce_single',
            'class'      => '',
        ], $atts, 'lf_product_image');

        $product = null;
        if ($atts['product'] !== '') {
            $product = wc_get_product(absint($atts['product']));
        } elseif (function_exists('wc_get_product') && isset($GLOBALS['product']) && $GLOBALS['product'] instanceof WC_Product) {
            $product = $GLOBALS['product'];
        }

        $attachment_id = absint($atts['attachment']);
        if (!$attachment_id && $product instanceof WC_Product) {
            $attachment_id = $product->get_image_id();
        }
        if (!$attachment_id) {
            return '';
        }

        $classes = array_filter(array_map('sanitize_html_class', explode(' ', $atts['class'])));
        $attr = [];
        if (!empty($classes)) {
            $attr['class'] = implode(' ', $classes);
        }

        $html = wp_get_attachment_image($attachment_id, $atts['size'], false, $attr);
        if (!$html) {
            $html = self::placeholder_image_html();
        }

        return self::wrap_image_html($html);
    }

    public static function shortcode_product_gallery($atts) {
        $atts = shortcode_atts([
            'product'         => '',
            'size'            => 'woocommerce_single',
            'limit'           => '',
            'class'           => '',
            'columns'         => 4,
            'columns_tablet'  => 3,
            'columns_mobile'  => 2,
        ], $atts, 'lf_product_gallery');

        $product = null;
        if ($atts['product'] !== '') {
            $product = wc_get_product(absint($atts['product']));
        } elseif (function_exists('wc_get_product') && isset($GLOBALS['product']) && $GLOBALS['product'] instanceof WC_Product) {
            $product = $GLOBALS['product'];
        }

        if (!$product instanceof WC_Product) {
            return '';
        }

        $image_ids = $product->get_gallery_image_ids();
        if (empty($image_ids)) {
            $image_ids = [0];
        }

        $limit = absint($atts['limit']);
        if ($limit > 0) {
            $image_ids = array_slice($image_ids, 0, $limit);
        }

        $columns        = max(1, (int) $atts['columns']);
        $columns_tablet = max(1, (int) $atts['columns_tablet']);
        $columns_mobile = max(1, (int) $atts['columns_mobile']);

        $gallery_id      = wp_unique_id('lf-gallery-');
        $wrapper_classes = ['lf-bg-gallery', 'lf-bg-gallery--slider'];
        if ($atts['class']) {
            $wrapper_classes[] = sanitize_html_class($atts['class']);
        }

        self::ensure_front_style();

        $main_slides  = [];
        $thumb_slides = [];

        foreach ($image_ids as $image_id) {
            $main_image  = wp_get_attachment_image($image_id, $atts['size']);
            if (!$main_image) {
                $main_image = self::placeholder_image_html();
            }
            $thumb_image = wp_get_attachment_image($image_id, 'woocommerce_gallery_thumbnail');
            if (!$thumb_image) {
                $thumb_image = $main_image;
            }

            $main_slides[]  = '<div class="swiper-slide" data-image-id="' . esc_attr((int) $image_id) . '">' . self::wrap_image_html($main_image) . '</div>';
            $thumb_slides[] = '<div class="swiper-slide" data-image-id="' . esc_attr((int) $image_id) . '"><div class="lf-bg-gallery__thumb">' . self::wrap_image_html($thumb_image) . '</div></div>';
        }

        if (empty($main_slides)) {
            return '';
        }

        self::enqueue_slider_assets();

        $data_attrs = sprintf(
            ' data-lf-gallery="%1$s" data-columns="%2$d" data-columns-tablet="%3$d" data-columns-mobile="%4$d"',
            esc_attr($gallery_id),
            $columns,
            $columns_tablet,
            $columns_mobile
        );

        $output  = '<div class="' . esc_attr(implode(' ', $wrapper_classes)) . '" data-product-id="' . esc_attr($product->get_id()) . '"' . $data_attrs . '>';
        $output .= '<div class="lf-bg-gallery__main swiper"><div class="swiper-wrapper">' . implode('', $main_slides) . '</div>';
        $output .= '<div class="lf-bg-gallery__nav">';
        $output .= '<button type="button" class="lf-bg-gallery__prev" aria-label="' . esc_attr__('Previous image', 'lime-filters') . '">';
        $output .= '<span class="lf-bg-gallery__icon" aria-hidden="true">' . self::navigation_icon('prev') . '</span>';
        $output .= '<span class="screen-reader-text">' . esc_html__('Previous image', 'lime-filters') . '</span>';
        $output .= '</button>';
        $output .= '<button type="button" class="lf-bg-gallery__next" aria-label="' . esc_attr__('Next image', 'lime-filters') . '">';
        $output .= '<span class="lf-bg-gallery__icon" aria-hidden="true">' . self::navigation_icon('next') . '</span>';
        $output .= '<span class="screen-reader-text">' . esc_html__('Next image', 'lime-filters') . '</span>';
        $output .= '</button>';
        $output .= '</div></div>';
        $output .= '<div class="lf-bg-gallery__thumbs swiper"><div class="swiper-wrapper">' . implode('', $thumb_slides) . '</div></div>';
        $output .= '</div>';

        return $output;
    }

    public static function filter_loop_thumbnail($html, $size) {
        return self::wrap_image_html($html);
    }

    public static function filter_single_gallery($html) {
        return self::wrap_image_html($html);
    }

    public static function filter_single_main($html) {
        return self::wrap_image_html($html);
    }

    protected static function navigation_icon($direction = 'next') {
        $direction = ($direction === 'prev') ? 'prev' : 'next';
        if ($direction === 'prev') {
            return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"><path d="M14.5 5.5L8 12l6.5 6.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        }
        return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"><path d="M9.5 5.5L16 12l-6.5 6.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }

    protected static function wrap_image_html($html) {
        if (strpos($html, 'lf-bg-wrap') !== false) {
            return $html;
        }

        if (empty($html)) {
            $html = self::placeholder_image_html();
            $is_placeholder = true;
        } elseif (strpos($html, 'woocommerce-placeholder') !== false) {
            $html = self::placeholder_image_html($html);
            $is_placeholder = true;
        } else {
            $is_placeholder = strpos($html, 'lf-placeholder') !== false;
        }

        $background_url = self::get_background_url();
        if (!$background_url) {
            return $html;
        }

        self::ensure_front_style();

        $style = '--lf-bg-image:url("' . esc_url($background_url) . '");';

        $class = 'lf-bg-wrap' . ($is_placeholder ? ' lf-bg-wrap--placeholder' : '');

        return '<span class="' . esc_attr($class) . '" style="' . esc_attr($style) . '">' . $html . '</span>';
    }

    public static function apply_background_wrapper($html) {
        return self::wrap_image_html($html);
    }

    protected static function ensure_front_style() {
        if (self::$style_enqueued) {
            return;
        }
        wp_enqueue_style('lf-product-background');
        self::$style_enqueued = true;
    }

    protected static function enqueue_slider_assets() {
        if (self::$slider_enqueued) {
            return;
        }

        self::ensure_front_style();

        $swiper_handle = self::ensure_swiper_assets();
        wp_register_script(
            'lf-product-background-slider',
            LF_PLUGIN_URL . 'includes/product-background/product-background-slider.js',
            [$swiper_handle],
            LF_VERSION,
            true
        );
        wp_enqueue_script('lf-product-background-slider');
        self::$slider_enqueued = true;
    }

    protected static function ensure_swiper_assets() {
        $handle = 'swiper';

        if (wp_script_is('swiper', 'registered') || wp_script_is('swiper', 'enqueued')) {
            wp_enqueue_script('swiper');
        } elseif (wp_script_is('swiper-bundle', 'registered') || wp_script_is('swiper-bundle', 'enqueued')) {
            $handle = 'swiper-bundle';
            wp_enqueue_script('swiper-bundle');
        } else {
            $handle = 'lf-swiper';
            wp_enqueue_style('lf-swiper');
            wp_enqueue_script('lf-swiper');
        }

        if (!wp_style_is('swiper', 'enqueued') && !wp_style_is('swiper-bundle', 'enqueued')) {
            if (!wp_style_is('lf-swiper', 'enqueued')) {
                wp_enqueue_style('lf-swiper');
            }
        }

        return $handle;
    }

    protected static function get_background_url() {
        $attachment_id = absint(get_option(self::OPTION_KEY, 0));
        if ($attachment_id) {
            $image = wp_get_attachment_image_url($attachment_id, 'full');
            if ($image) {
                return $image;
            }
        }
        return self::get_default_background_url();
    }

    protected static function get_default_background_url() {
        $path = self::get_default_background_path();
        if (!file_exists($path)) {
            return '';
        }
        return LF_PLUGIN_URL . 'includes/assets/images/Kucht Products BG.png';
    }

    protected static function get_default_background_path() {
        return LF_PLUGIN_DIR . 'includes/assets/images/Kucht Products BG.png';
    }

    protected static function placeholder_image_html($original = '') {
        $class = 'lf-placeholder';
        if ($original && preg_match('/class="([^"]+)"/', $original, $matches)) {
            $class .= ' ' . $matches[1];
        }

        return sprintf(
            '<img src="%1$s" alt="%2$s" class="%3$s" />',
            esc_url(LF_Helpers::placeholder_image_url()),
            esc_attr__('Placeholder image', 'lime-filters'),
            esc_attr(trim($class))
        );
    }
}
