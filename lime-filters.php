<?php
/**
 * Plugin Name: Lime Filters
 * Description: AJAX product filters for WooCommerce with Elementor widget and shortcode. Includes mobile modal with Sort & Filter.
 * Version: 1.0.0
 * Author: Lime Advertising
 * Text Domain: lime-filters
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'LF_VERSION', '1.0.0' );
define( 'LF_PLUGIN_FILE', __FILE__ );
define( 'LF_PLUGIN_DIR', plugin_dir_path(__FILE__) );
define( 'LF_PLUGIN_URL', plugin_dir_url(__FILE__) );

// Requirements check
add_action('plugins_loaded', function() {
    if ( ! class_exists('WooCommerce') ) {
        add_action('admin_notices', function(){
            echo '<div class="notice notice-error"><p><strong>Lime Filters</strong> requires WooCommerce. Please activate WooCommerce.</p></div>';
        });
        return;
    }

    // Includes
    require_once LF_PLUGIN_DIR . 'includes/helpers.php';
    require_once LF_PLUGIN_DIR . 'includes/class-lf-admin.php';
    require_once LF_PLUGIN_DIR . 'includes/class-lf-frontend.php';
    require_once LF_PLUGIN_DIR . 'includes/class-lf-ajax.php';
    require_once LF_PLUGIN_DIR . 'includes/class-lf-elementor-widget.php';
    require_once LF_PLUGIN_DIR . 'includes/class-lf-product-variants.php';
    require_once LF_PLUGIN_DIR . 'includes/compare/class-lf-product-compare.php';
    require_once LF_PLUGIN_DIR . 'includes/product-background/class-lf-product-background.php';
    require_once LF_PLUGIN_DIR . 'includes/related-products/class-lf-related-products.php';
    require_once LF_PLUGIN_DIR . 'includes/class-lf-wishlist.php';
    require_once LF_PLUGIN_DIR . 'includes/class-lf-affiliate-archive.php';

    // Init
    LF_Admin::init();
    LF_Frontend::init();
    LF_AJAX::init();
    LF_Elementor_Widget::maybe_register();
    LF_Product_Variants::init();
    LF_Product_Compare::init();
    LF_Product_Background::init();
    LF_Related_Products::init();
    LF_Wishlist::init();
    LF_Affiliate_Archive::init();

    add_filter('woocommerce_placeholder_img_src', function($src){
        return LF_Helpers::placeholder_image_url();
    });
});

// Activation defaults
register_activation_hook(__FILE__, function(){
    if ( false === get_option('lime_filters_brand_colors') ) {
        update_option('lime_filters_brand_colors', [
            'accent'     => '#DD7210',
            'border'     => '#A9A9A9',
            'background' => '#000000',
            'text'       => '#ffffff',
        ]);
    }
    if ( false === get_option('lime_filters_map') ) {
        // Minimal starter mapping; edit in WooCommerce → Lime Filters
        update_option('lime_filters_map', [
            'range-hoods'     => ['pa_installation-type','pa_size'],
            'gas-ranges'      => ['pa_size'],
            'dual-fuel-range' => ['pa_size'],
            'refrigeration'   => ['pa_installation-type','pa_size'],
            'range-tops'      => ['pa_size'],
        ]);
    }
});
