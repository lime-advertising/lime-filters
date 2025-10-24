<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class LF_Elementor_Widget {
    public static function maybe_register() {
        // Elementor 3.5+
        if ( did_action('elementor/loaded') ) {
            add_action('elementor/widgets/register', [__CLASS__, 'register']);
        }
    }

    public static function register($widgets_manager) {
        if (!class_exists('\Elementor\Widget_Base')) return;

        require_once __DIR__ . '/class-lf-elementor-filters-widget.php';
        if (class_exists('LF_Elementor_Filters_Widget')) {
            $widgets_manager->register( new \LF_Elementor_Filters_Widget() );
        }

        $tabs_widget_path = LF_PLUGIN_DIR . 'includes/elementor/category-tabs/class-lf-elementor-category-tabs-widget.php';
        if (file_exists($tabs_widget_path)) {
            require_once $tabs_widget_path;
            if (class_exists('LF_Elementor_Category_Tabs_Widget')) {
                $widgets_manager->register( new \LF_Elementor_Category_Tabs_Widget() );
            }
        }

        $product_attributes_path = LF_PLUGIN_DIR . 'includes/elementor/product-attributes/class-lf-elementor-product-attributes-widget.php';
        if (file_exists($product_attributes_path)) {
            require_once $product_attributes_path;
            if (class_exists('LF_Elementor_Product_Attributes_Widget')) {
                $widgets_manager->register( new \LF_Elementor_Product_Attributes_Widget() );
            }
        }

        $product_info_tabs_path = LF_PLUGIN_DIR . 'includes/elementor/product-info/class-lf-elementor-product-info-tabs-widget.php';
        if (file_exists($product_info_tabs_path)) {
            require_once $product_info_tabs_path;
            if (class_exists('LF_Elementor_Product_Info_Tabs_Widget')) {
                $widgets_manager->register( new \LF_Elementor_Product_Info_Tabs_Widget() );
            }
        }

        $product_affiliates_path = LF_PLUGIN_DIR . 'includes/elementor/product-affiliates/class-lf-elementor-product-affiliates-widget.php';
        if (file_exists($product_affiliates_path)) {
            require_once $product_affiliates_path;
            if (class_exists('LF_Elementor_Product_Affiliates_Widget')) {
                $widgets_manager->register( new \LF_Elementor_Product_Affiliates_Widget() );
            }
        }

        $product_pricing_path = LF_PLUGIN_DIR . 'includes/elementor/product-pricing/class-lf-elementor-product-pricing-widget.php';
        if (file_exists($product_pricing_path)) {
            require_once $product_pricing_path;
            if (class_exists('LF_Elementor_Product_Pricing_Widget')) {
                $widgets_manager->register( new \LF_Elementor_Product_Pricing_Widget() );
            }
        }

        $account_dashboard_path = LF_PLUGIN_DIR . 'includes/elementor/account-dashboard/class-lf-elementor-account-dashboard-widget.php';
        if (file_exists($account_dashboard_path)) {
            require_once $account_dashboard_path;
            if (class_exists('LF_Elementor_Account_Dashboard_Widget')) {
                $widgets_manager->register( new \LF_Elementor_Account_Dashboard_Widget() );
            }
        }

        $account_icon_path = LF_PLUGIN_DIR . 'includes/elementor/account-icon/class-lf-elementor-account-icon-widget.php';
        if (file_exists($account_icon_path)) {
            require_once $account_icon_path;
            if (class_exists('LF_Elementor_Account_Icon_Widget')) {
                $widgets_manager->register( new \LF_Elementor_Account_Icon_Widget() );
            }
        }

        $recently_viewed_path = LF_PLUGIN_DIR . 'includes/elementor/recently-viewed/class-lf-elementor-recently-viewed-widget.php';
        if (file_exists($recently_viewed_path)) {
            require_once $recently_viewed_path;
            if (class_exists('LF_Elementor_Recently_Viewed_Widget')) {
                $widgets_manager->register( new \LF_Elementor_Recently_Viewed_Widget() );
            }
        }

        $title_share_path = LF_PLUGIN_DIR . 'includes/elementor/product-title-share/class-lf-elementor-product-title-share-widget.php';
        if (file_exists($title_share_path)) {
            require_once $title_share_path;
            if (class_exists('LF_Elementor_Product_Title_Share_Widget')) {
                $widgets_manager->register( new \LF_Elementor_Product_Title_Share_Widget() );
            }
        }

    }
}
