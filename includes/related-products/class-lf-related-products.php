<?php
if (! defined('ABSPATH')) {
    exit;
}

class LF_Related_Products
{
    const SHORTCODE = 'lf_related_products';

    public static function init()
    {
        add_shortcode(self::SHORTCODE, [__CLASS__, 'shortcode']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'register_assets']);
    }

    public static function register_assets()
    {
        wp_register_style(
            'lf-related-products',
            LF_PLUGIN_URL . 'includes/related-products/related-products.css',
            [],
            LF_VERSION
        );
    }

    public static function shortcode($atts = [])
    {
        if (!function_exists('wc_get_product')) {
            return '';
        }

        $atts = shortcode_atts([
            'product'        => '',
            'limit'          => 4,
            'columns'        => 4,
            'columns_tablet' => 2,
            'columns_mobile' => 1,
            'class'          => '',
            'orderby'        => 'rand',
            'order'          => 'desc',
        ], $atts, self::SHORTCODE);

        $limit = max(1, (int) $atts['limit']);
        $product = null;

        if ($atts['product'] !== '') {
            $product = wc_get_product(absint($atts['product']));
        } elseif (isset($GLOBALS['product']) && $GLOBALS['product'] instanceof WC_Product) {
            $product = $GLOBALS['product'];
        }

        if (!$product instanceof WC_Product) {
            return '';
        }

        $related_ids = wc_get_related_products($product->get_id(), $limit);
        if (empty($related_ids)) {
            return '';
        }

        $columns        = max(1, (int) $atts['columns']);
        $columns_tablet = max(1, (int) $atts['columns_tablet']);
        $columns_mobile = max(1, (int) $atts['columns_mobile']);

        $wrapper_classes = ['lf-related-products'];
        if ($atts['class']) {
            $wrapper_classes[] = sanitize_html_class($atts['class']);
        }

        if (wp_style_is('lime-filters', 'registered') || wp_style_is('lime-filters', 'enqueued')) {
            wp_enqueue_style('lime-filters');
        } else {
            wp_enqueue_style(
                'lime-filters',
                LF_PLUGIN_URL . 'includes/assets/css/lime-filters.css',
                [],
                LF_VERSION
            );
        }

        wp_enqueue_style('lf-related-products');

        ob_start();
?>
        <div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>"
            style="--lf-related-columns:<?php echo esc_attr($columns); ?>;
                    --lf-related-columns-tablet:<?php echo esc_attr($columns_tablet); ?>;
                    --lf-related-columns-mobile:<?php echo esc_attr($columns_mobile); ?>;">
            <?php foreach ($related_ids as $related_id): ?>
                <?php
                $related_product = wc_get_product($related_id);
                if (!$related_product) {
                    continue;
                }
                echo LF_AJAX::render_product_card($related_product); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
            <?php endforeach; ?>
        </div>
<?php
        return ob_get_clean();
    }

}
