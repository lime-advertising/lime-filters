<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;

if (class_exists('LF_Elementor_Account_Dashboard_Widget')) {
    return;
}

if (!class_exists('\\Elementor\\Widget_Base')) {
    return;
}

class LF_Elementor_Account_Dashboard_Widget extends \Elementor\Widget_Base
{
    protected static $assets_registered = false;

    public function get_name()
    {
        return 'lf-account-dashboard';
    }

    public function get_title()
    {
        return __('LF Account Dashboard', 'lime-filters');
    }

    public function get_icon()
    {
        return 'eicon-user-circle-o';
    }

    public function get_categories()
    {
        return ['general'];
    }

    protected function register_controls()
    {
        $this->start_controls_section('section_content', [
            'label' => __('Content', 'lime-filters'),
        ]);

        $this->add_control('recent_orders_limit', [
            'label' => __('Recent Orders Limit', 'lime-filters'),
            'type' => Controls_Manager::NUMBER,
            'min' => 1,
            'max' => 10,
            'step' => 1,
            'default' => 3,
        ]);

        $this->add_control('show_reward_placeholder', [
            'label' => __('Show Reward Points Placeholder', 'lime-filters'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('logged_out_message', [
            'label' => __('Logged Out Message', 'lime-filters'),
            'type' => Controls_Manager::TEXTAREA,
            'default' => __('Please log in to view your account dashboard.', 'lime-filters'),
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_support', [
            'label' => __('Support Block', 'lime-filters'),
        ]);

        $this->add_control('show_support_block', [
            'label' => __('Show Support Block', 'lime-filters'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('support_title', [
            'label' => __('Support Title', 'lime-filters'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Need Help?', 'lime-filters'),
            'condition' => [
                'show_support_block' => 'yes',
            ],
        ]);

        $this->add_control('support_description', [
            'label' => __('Support Description', 'lime-filters'),
            'type' => Controls_Manager::TEXTAREA,
            'default' => __('Our team is ready to assist you with orders, products, or account questions.', 'lime-filters'),
            'condition' => [
                'show_support_block' => 'yes',
            ],
        ]);

        $this->add_control('support_cta_label', [
            'label' => __('Support Button Label', 'lime-filters'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Contact Support', 'lime-filters'),
            'condition' => [
                'show_support_block' => 'yes',
            ],
        ]);

        $this->add_control('support_cta_link', [
            'label' => __('Support Button URL', 'lime-filters'),
            'type' => Controls_Manager::URL,
            'placeholder' => 'https://example.com/support',
            'condition' => [
                'show_support_block' => 'yes',
            ],
        ]);

        $this->end_controls_section();
    }

    public function render()
    {
        $settings = $this->get_settings_for_display();

        wp_enqueue_style('lime-filters');
        self::ensure_assets();
        wp_enqueue_style('lf-account-dashboard');

        $user = wp_get_current_user();
        $is_logged_in = $user instanceof WP_User && $user->ID > 0;
        $is_editor = $this->is_elementor_editor();

        if (!$is_logged_in && !$is_editor) {
            $message = isset($settings['logged_out_message']) && $settings['logged_out_message'] !== ''
                ? $settings['logged_out_message']
                : __('Please log in to view your account dashboard.', 'lime-filters');
            $login_url = function_exists('wc_get_page_permalink')
                ? wc_get_page_permalink('myaccount')
                : wp_login_url(home_url('/'));
            $registration_allowed = function_exists('wc_registration_enabled')
                ? wc_registration_enabled()
                : true;
            if (!$registration_allowed) {
                $registration_allowed = get_option('woocommerce_enable_myaccount_registration') === 'yes'
                    || get_option('woocommerce_enable_checkout_registration') === 'yes';
            }
            $register_url = '';
            if ($registration_allowed) {
                $register_url = add_query_arg('register', 'true', $login_url);
            } elseif (function_exists('wp_registration_url')) {
                $register_url = wp_registration_url();
            }
            $this->render_logged_out_view($message, $login_url, $register_url, $registration_allowed);
            return;
        }

        $data = $is_logged_in
            ? $this->build_dashboard_data($user, $settings)
            : $this->build_placeholder_data($settings);

        $show_support = $settings['show_support_block'] === 'yes';
        $show_rewards = $settings['show_reward_placeholder'] === 'yes';

        echo '<div class="lf-account-dashboard">';
        if (function_exists('wc_print_notices')) {
            wc_print_notices();
        }
        echo '<div class="lf-account-dashboard__cols">';

        $this->render_navigation($data['navigation'], $data['active_endpoint']);

        echo '<div class="lf-account-dashboard__main">';
        $this->render_header($data['user']);

        $is_dashboard = $this->is_dashboard_endpoint($data['active_endpoint']);

        if ($is_dashboard) {
            $this->render_stats($data['stats'], $show_rewards);
            $this->render_recent_orders($data['orders'], $data['orders_link']);
            $this->render_addresses($data['addresses']);

            if ($show_support) {
                $this->render_support_block($settings);
            }
        } else {
            $this->render_endpoint_content($data['active_endpoint'], $data['navigation']);
            if ($show_support) {
                $this->render_support_block($settings);
            }
        }

        echo '</div>'; // main
        echo '</div>'; // cols
        echo '</div>'; // dashboard
    }

    protected static function ensure_assets()
    {
        if (self::$assets_registered) {
            return;
        }

        wp_register_style(
            'lf-account-dashboard',
            LF_PLUGIN_URL . 'includes/elementor/account-dashboard/account-dashboard.css',
            ['lime-filters'],
            LF_VERSION
        );

        self::$assets_registered = true;
    }

    protected function build_dashboard_data(WP_User $user, array $settings)
    {
        $wishlist_enabled = class_exists('LF_Wishlist') && LF_Wishlist::is_enabled();
        $wishlist_ids = $wishlist_enabled ? LF_Wishlist::current_ids() : [];
        $wishlist_url = $wishlist_enabled ? LF_Wishlist::get_page_url() : '';
        $wishlist_count = is_array($wishlist_ids) ? count($wishlist_ids) : 0;

        $navigation = $this->build_navigation_items($wishlist_enabled, $wishlist_count, $wishlist_url);
        $active_endpoint = $this->detect_active_endpoint();

        $stats = $this->build_stats($user->ID, $wishlist_count);
        $orders_limit = isset($settings['recent_orders_limit']) ? max(1, (int) $settings['recent_orders_limit']) : 3;
        $orders = $this->get_recent_orders($user->ID, $orders_limit);
        $orders_link = wc_get_account_endpoint_url('orders');

        $addresses = $this->get_addresses();

        $wishlist = [
            'enabled' => $wishlist_enabled,
            'count'   => $wishlist_count,
            'url'     => $wishlist_url,
            'items'   => $this->get_wishlist_preview($wishlist_ids, 3),
        ];

        return [
            'user'            => [
                'display_name' => $user->display_name ?: $user->user_login,
            ],
            'navigation'      => $navigation,
            'active_endpoint' => $active_endpoint,
            'stats'           => $stats,
            'orders'          => $orders,
            'orders_link'     => $orders_link,
            'addresses'       => $addresses,
            'wishlist'        => $wishlist,
        ];
    }

    protected function build_placeholder_data(array $settings)
    {
        $wishlist_enabled = true;

        $navigation = $this->build_navigation_items(
            $wishlist_enabled,
            2,
            '#'
        );

        $orders_limit = isset($settings['recent_orders_limit']) ? max(1, (int) $settings['recent_orders_limit']) : 3;

        $placeholder_orders = [];
        for ($i = 1; $i <= $orders_limit; $i++) {
            $placeholder_orders[] = [
                'number' => '#100' . $i,
                'date'   => date_i18n(get_option('date_format'), strtotime("-{$i} weeks")),
                'status' => __('Processing', 'lime-filters'),
                'status_class' => 'processing',
                'total'  => wc_price(199.99 + ($i * 40)),
                'link'   => '#',
            ];
        }

        $wishlist_preview = $wishlist_enabled ? $this->get_placeholder_wishlist_cards() : [];

        return [
            'user' => [
                'display_name' => __('Demo Customer', 'lime-filters'),
            ],
            'navigation' => $navigation,
            'active_endpoint' => 'dashboard',
            'stats' => [
                'recent_orders' => [
                    'label' => __('Orders (30 days)', 'lime-filters'),
                    'value' => 4,
                ],
                'total_spent' => [
                    'label' => __('Total Spent', 'lime-filters'),
                    'value' => wc_price(1299.00),
                ],
                'wishlist' => [
                    'label' => __('Wishlist Items', 'lime-filters'),
                    'value' => $wishlist_enabled ? 2 : 0,
                ],
                'rewards' => [
                    'label' => __('Reward Points', 'lime-filters'),
                    'value' => 320,
                ],
            ],
            'orders' => $placeholder_orders,
            'orders_link' => '#',
            'addresses' => [
                [
                    'type' => __('Billing Address', 'lime-filters'),
                    'content' => "123 Demo Street<br>Suite 100<br>Toronto, ON",
                    'link' => '#',
                ],
                [
                    'type' => __('Shipping Address', 'lime-filters'),
                    'content' => __('Same as billing.', 'lime-filters'),
                    'link' => '#',
                ],
            ],
            'wishlist' => [
                'enabled' => $wishlist_enabled,
                'count' => $wishlist_enabled ? 2 : 0,
                'url' => '#',
                'items' => $wishlist_preview,
            ],
        ];
    }

    protected function build_navigation_items($include_wishlist, $wishlist_count, $wishlist_url)
    {
        $items = [];
        $menu_items = function_exists('wc_get_account_menu_items') ? wc_get_account_menu_items() : [];

        foreach ($menu_items as $endpoint => $label) {
            $url = '';
            if ($endpoint === 'customer-logout') {
                $url = function_exists('wc_logout_url') ? wc_logout_url() : wp_logout_url();
            } elseif ($endpoint === 'dashboard') {
                $url = wc_get_account_endpoint_url('dashboard');
            } else {
                $url = wc_get_account_endpoint_url($endpoint);
            }

            $items[] = [
                'endpoint'     => $endpoint,
                'label'        => $label,
                'description'  => $this->get_endpoint_description($endpoint),
                'icon'         => $this->get_endpoint_icon($endpoint),
                'url'          => $url,
            ];
        }

        if ($include_wishlist && $wishlist_url) {
            $items[] = [
                'endpoint'     => 'lf-wishlist',
                'label'        => __('Wishlist', 'lime-filters'),
                'description'  => __('View saved products you love.', 'lime-filters'),
                'icon'         => $this->get_endpoint_icon('lf-wishlist'),
                'url'          => $wishlist_url,
                'badge'        => max(0, (int) $wishlist_count),
            ];
        }

        return $items;
    }

    protected function detect_active_endpoint()
    {
        if (!function_exists('is_account_page') || !is_account_page()) {
            return '';
        }

        if (!function_exists('WC') || !WC()->query) {
            return '';
        }

        $endpoint = WC()->query->get_current_endpoint();
        if (empty($endpoint)) {
            return 'dashboard';
        }

        if (is_string($endpoint)) {
            return $endpoint;
        }

        return '';
    }

    protected function build_stats($user_id, $wishlist_count)
    {
        $thirty_days_ago = function_exists('wc_string_to_datetime')
            ? wc_string_to_datetime('30 days ago')
            : new DateTime('-30 days', wp_timezone());

        $orders_last_30 = wc_get_orders([
            'customer_id' => $user_id,
            'date_created' => '>=' . $thirty_days_ago->format('Y-m-d H:i:s'),
            'return' => 'ids',
            'status' => array_keys(wc_get_order_statuses()),
        ]);
        $recent_count = is_array($orders_last_30) ? count($orders_last_30) : 0;

        $total_spent = function_exists('wc_get_customer_total_spent')
            ? wc_get_customer_total_spent($user_id)
            : 0;

        return [
            'recent_orders' => [
                'label' => __('Orders (30 days)', 'lime-filters'),
                'value' => $recent_count,
            ],
            'total_spent' => [
                'label' => __('Total Spent', 'lime-filters'),
                'value' => wc_price($total_spent),
            ],
            'wishlist' => [
                'label' => __('Wishlist Items', 'lime-filters'),
                'value' => max(0, (int) $wishlist_count),
            ],
            'rewards' => [
                'label' => __('Reward Points', 'lime-filters'),
                'value' => apply_filters('lf_account_dashboard_reward_points', 0, $user_id),
            ],
        ];
    }

    protected function get_recent_orders($user_id, $limit)
    {
        $orders = [];
        $query = wc_get_orders([
            'customer_id' => $user_id,
            'limit'       => $limit,
            'orderby'     => 'date',
            'order'       => 'DESC',
            'status'      => array_keys(wc_get_order_statuses()),
        ]);

        if (empty($query)) {
            return $orders;
        }

        foreach ($query as $order) {
            if (!$order instanceof WC_Order) {
                continue;
            }

            $orders[] = [
                'number' => $order->get_order_number(),
                'date'   => wc_format_datetime($order->get_date_created()),
                'status' => wc_get_order_status_name($order->get_status()),
                'status_class' => sanitize_html_class($order->get_status()),
                'total'  => $order->get_formatted_order_total(),
                'link'   => $order->get_view_order_url(),
            ];
        }

        return $orders;
    }

    protected function get_addresses()
    {
        $addresses = [];

        if (function_exists('wc_get_account_formatted_address')) {
            $billing = wc_get_account_formatted_address('billing');
            $shipping = wc_get_account_formatted_address('shipping');

            $addresses[] = [
                'type'    => __('Billing Address', 'lime-filters'),
                'content' => $billing ? wpautop($billing) : __('You have not set up a billing address.', 'lime-filters'),
                'link'    => wc_get_endpoint_url('edit-address', 'billing'),
            ];

            $addresses[] = [
                'type'    => __('Shipping Address', 'lime-filters'),
                'content' => $shipping ? wpautop($shipping) : __('You have not set up a shipping address.', 'lime-filters'),
                'link'    => wc_get_endpoint_url('edit-address', 'shipping'),
            ];
        }

        return $addresses;
    }

    protected function get_wishlist_preview(array $ids, $limit = 3)
    {
        $cards = [];

        if (empty($ids) || !class_exists('LF_AJAX')) {
            return $cards;
        }

        $slice = array_slice($ids, 0, max(1, (int) $limit));
        $filter = function () {
            return [];
        };

        add_filter('lime_filters_add_to_cart_categories', $filter, 100);

        foreach ($slice as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product instanceof WC_Product) {
                continue;
            }
            $card = LF_AJAX::render_product_card($product);
            if ($card) {
                $cards[] = $card;
            }
        }

        remove_filter('lime_filters_add_to_cart_categories', $filter, 100);

        return $cards;
    }

    protected function get_placeholder_wishlist_cards()
    {
        $cards = [];
        $placeholder = '<article class="lf-product lf-product--placeholder"><div class="lf-product__media"><div class="lf-product__thumb lf-product__thumb--placeholder"></div></div><div class="lf-product__body"><div class="lf-product__cats">Demo Category</div><h3 class="lf-product__title"><span>' . esc_html__('Sample Product', 'lime-filters') . '</span></h3><div class="lf-product__price lf-product__price--columns"><div class="lf-price-block lf-price-block--single"><div class="lf-price-col lf-price-col--sale"><span class="lf-price-label">' . esc_html__('Starting at', 'lime-filters') . '</span><span class="lf-price-value">' . wc_price(499.00) . '</span></div></div></div></div></article>';
        $cards[] = $placeholder;
        $cards[] = str_replace('Sample Product', __('Premium Range', 'lime-filters'), $placeholder);
        return $cards;
    }

    protected function render_navigation(array $items, $active_endpoint)
    {
        echo '<aside class="lf-account-dashboard__nav">';
        echo '<div class="lf-account-dashboard__nav-inner">';

        foreach ($items as $item) {
            $endpoint = isset($item['endpoint']) ? (string) $item['endpoint'] : '';
            $is_active = $endpoint !== '' && $endpoint === $active_endpoint;
            $classes = ['lf-account-dashboard__nav-item'];
            if ($is_active) {
                $classes[] = 'is-active';
            }
            echo '<a class="' . esc_attr(implode(' ', $classes)) . '" href="' . esc_url($item['url']) . '">';
            echo '<span class="lf-account-dashboard__nav-icon">' . $item['icon'] . '</span>';
            echo '<span class="lf-account-dashboard__nav-content">';
            echo '<span class="lf-account-dashboard__nav-title">' . esc_html($item['label']) . '</span>';
            if (!empty($item['description'])) {
                echo '<span class="lf-account-dashboard__nav-desc">' . esc_html($item['description']) . '</span>';
            }
            echo '</span>';
            if (isset($item['badge']) && (int) $item['badge'] > 0) {
                echo '<span class="lf-account-dashboard__badge">' . esc_html((int) $item['badge']) . '</span>';
            }
            echo '</a>';
        }

        echo '</div>';
        echo '</aside>';
    }

    protected function render_header(array $user)
    {
        $name = isset($user['display_name']) ? $user['display_name'] : '';
        echo '<header class="lf-account-dashboard__header">';
        echo '<h2>' . esc_html(sprintf(__('Hello, %s', 'lime-filters'), $name)) . '</h2>';
        echo '<p>' . esc_html__('Here’s a quick snapshot of your account.', 'lime-filters') . '</p>';
        echo '</header>';
    }

    protected function render_stats(array $stats, $show_rewards)
    {
        echo '<div class="lf-account-dashboard__stats">';

        if (isset($stats['recent_orders'])) {
            $this->render_stat_card($stats['recent_orders']);
        }
        if (isset($stats['total_spent'])) {
            $this->render_stat_card($stats['total_spent']);
        }
        if (isset($stats['wishlist'])) {
            $this->render_stat_card($stats['wishlist']);
        }

        if ($show_rewards && isset($stats['rewards'])) {
            if (!isset($stats['rewards']['value']) || $stats['rewards']['value'] === 0) {
                $stats['rewards']['value'] = 0;
            }
            $this->render_stat_card($stats['rewards'], 'lf-account-dashboard__stat--rewards');
        }

        echo '</div>';
    }

    protected function render_stat_card(array $stat, $additional_class = '')
    {
        $classes = ['lf-account-dashboard__stat'];
        if ($additional_class !== '') {
            $classes[] = $additional_class;
        }
        $value = isset($stat['value']) ? $stat['value'] : '';
        if (is_numeric($value)) {
            $value = number_format_i18n($value);
        }

        echo '<div class="' . esc_attr(implode(' ', $classes)) . '">';
        echo '<span class="lf-account-dashboard__stat-value">' . wp_kses_post($value) . '</span>';
        echo '<span class="lf-account-dashboard__stat-label">' . esc_html($stat['label']) . '</span>';
        echo '</div>';
    }

    protected function render_recent_orders(array $orders, $orders_link)
    {
        echo '<section class="lf-account-dashboard__section lf-account-dashboard__section--orders">';
        echo '<div class="lf-account-dashboard__section-header">';
        echo '<h3>' . esc_html__('Recent Orders', 'lime-filters') . '</h3>';
        if (!empty($orders_link)) {
            echo '<a class="lf-account-dashboard__link" href="' . esc_url($orders_link) . '">' . esc_html__('All orders', 'lime-filters') . '</a>';
        }
        echo '</div>';

        if (empty($orders)) {
            echo '<p class="lf-account-dashboard__empty">' . esc_html__('You have not placed any orders yet.', 'lime-filters') . '</p>';
            echo '</section>';
            return;
        }

        echo '<ul class="lf-account-dashboard__orders">';
        foreach ($orders as $order) {
            echo '<li class="lf-account-dashboard__order">';
            echo '<div class="lf-account-dashboard__order-main">';
            echo '<span class="lf-account-dashboard__order-number">' . esc_html(sprintf(__('Order %s', 'lime-filters'), $order['number'])) . '</span>';
            if (!empty($order['date'])) {
                echo '<span class="lf-account-dashboard__order-date">' . esc_html($order['date']) . '</span>';
            }
            echo '</div>';
            echo '<div class="lf-account-dashboard__order-meta">';
            echo '<span class="lf-account-dashboard__order-status lf-account-dashboard__order-status--' . esc_attr($order['status_class']) . '">' . esc_html($order['status']) . '</span>';
            echo '<span class="lf-account-dashboard__order-total">' . wp_kses_post($order['total']) . '</span>';
            if (!empty($order['link'])) {
                echo '<a class="lf-account-dashboard__button" href="' . esc_url($order['link']) . '">' . esc_html__('View', 'lime-filters') . '</a>';
            }
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';
        echo '</section>';
    }

    protected function render_addresses(array $addresses)
    {
        if (empty($addresses)) {
            return;
        }

        echo '<section class="lf-account-dashboard__section lf-account-dashboard__section--addresses">';
        echo '<div class="lf-account-dashboard__section-header">';
        echo '<h3>' . esc_html__('Saved Addresses', 'lime-filters') . '</h3>';
        echo '</div>';
        echo '<div class="lf-account-dashboard__address-grid">';

        foreach ($addresses as $address) {
            echo '<div class="lf-account-dashboard__address-card">';
            echo '<h4>' . esc_html($address['type']) . '</h4>';
            echo '<div class="lf-account-dashboard__address-content">' . wp_kses_post($address['content']) . '</div>';
            if (!empty($address['link'])) {
                echo '<a class="lf-account-dashboard__link" href="' . esc_url($address['link']) . '">' . esc_html__('Edit', 'lime-filters') . '</a>';
            }
            echo '</div>';
        }

        echo '</div>';
        echo '</section>';
    }
    protected function render_endpoint_content($endpoint, array $navigation)
    {
        $title = $this->get_endpoint_title($endpoint, $navigation);
        $content = $this->get_endpoint_content_html($endpoint);

        echo '<section class="lf-account-dashboard__section lf-account-dashboard__section--endpoint">';
        if ($title !== '') {
            echo '<div class="lf-account-dashboard__section-header">';
            echo '<h3>' . esc_html($title) . '</h3>';
            echo '</div>';
        }

        echo '<div class="lf-account-dashboard__endpoint">';
        if ($content === '') {
            echo '<p class="lf-account-dashboard__empty">' . esc_html__('Content unavailable for this section.', 'lime-filters') . '</p>';
        } else {
            echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        echo '</div>';
        echo '</section>';
    }

    protected function render_support_block(array $settings)
    {
        $title = isset($settings['support_title']) && $settings['support_title'] !== '' ? $settings['support_title'] : __('Need Help?', 'lime-filters');
        $description = isset($settings['support_description']) && $settings['support_description'] !== '' ? $settings['support_description'] : __('Our team is ready to assist you with orders, products, or account questions.', 'lime-filters');
        $button_label = isset($settings['support_cta_label']) && $settings['support_cta_label'] !== '' ? $settings['support_cta_label'] : __('Contact Support', 'lime-filters');
        $link = isset($settings['support_cta_link']['url']) ? $settings['support_cta_link']['url'] : '';

        echo '<section class="lf-account-dashboard__section lf-account-dashboard__section--support">';
        echo '<div class="lf-account-dashboard__support-card">';
        echo '<div class="lf-account-dashboard__support-content">';
        echo '<h3>' . esc_html($title) . '</h3>';
        echo '<p>' . esc_html($description) . '</p>';
        echo '</div>';
        if ($link !== '') {
            $target = !empty($settings['support_cta_link']['is_external']) ? ' target="_blank" rel="noopener"' : '';
            echo '<a class="lf-account-dashboard__button lf-account-dashboard__button--primary" href="' . esc_url($link) . '"' . $target . '>' . esc_html($button_label) . '</a>';
        }
        echo '</div>';
        echo '</section>';
    }

    protected function get_endpoint_description($endpoint)
    {
        $map = [
            'dashboard'        => __('Overview and recommendations.', 'lime-filters'),
            'orders'           => __('View and manage your orders.', 'lime-filters'),
            'downloads'        => __('Access digital downloads.', 'lime-filters'),
            'edit-address'     => __('Manage your addresses.', 'lime-filters'),
            'payment-methods'  => __('Update saved payment methods.', 'lime-filters'),
            'edit-account'     => __('Update your profile and password.', 'lime-filters'),
            'customer-logout'  => __('Sign out of your account.', 'lime-filters'),
            'lf-wishlist'      => __('View saved products you love.', 'lime-filters'),
        ];

        return isset($map[$endpoint]) ? $map[$endpoint] : '';
    }

    protected function get_endpoint_icon($endpoint)
    {
        $map = [
            'dashboard'       => 'home',
            'orders'          => 'box',
            'downloads'       => 'download',
            'edit-address'    => 'location',
            'payment-methods' => 'card',
            'edit-account'    => 'user',
            'customer-logout' => 'logout',
            'lf-wishlist'     => 'heart',
        ];

        $name = isset($map[$endpoint]) ? $map[$endpoint] : 'dots';

        return $this->get_icon_svg($name);
    }

    protected function get_icon_svg($name)
    {
        $icons = [
            'home' => '<svg class="lf-account-dashboard__icon-svg" viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 3l9 8h-3v9h-5v-6h-2v6H6v-9H3z"/></svg>',
            'box' => '<svg class="lf-account-dashboard__icon-svg" viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false"><path fill="currentColor" d="M4 7l8-4 8 4v10l-8 4-8-4V7zm14 0l-6-3-6 3v8l6 3 6-3V7z"/></svg>',
            'download' => '<svg class="lf-account-dashboard__icon-svg" viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false"><path fill="currentColor" d="M11 3h2v9l3-3 1.4 1.4L12 17.8 6.6 10.4 8 9l3 3V3zm-6 15h14v2H5z"/></svg>',
            'location' => '<svg class="lf-account-dashboard__icon-svg" viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg>',
            'card' => '<svg class="lf-account-dashboard__icon-svg" viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false"><path fill="currentColor" d="M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zm1 4v8h16V9H4zm0-2v1h16V7H4zm3 6h4v2H7v-2z"/></svg>',
            'user' => '<svg class="lf-account-dashboard__icon-svg" viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5z"/></svg>',
            'logout' => '<svg class="lf-account-dashboard__icon-svg" viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false"><path fill="currentColor" d="M9 5h2v4h8v6h-8v4H9V5zm7.59 6-2.3-2.29L15 7l5 5-5 5-1.71-1.71L16.59 13H4v-2z"/></svg>',
            'heart' => '<svg class="lf-account-dashboard__icon-svg" viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 20l-1.45-1.32C5.4 13.36 2 10.28 2 6.5 2 4 4 2 6.5 2c1.74 0 3.41 1.01 4.5 2.57C12.09 3.01 13.76 2 15.5 2 18 2 20 4 20 6.5c0 3.78-3.4 6.86-8.55 12.18L12 20z"/></svg>',
            'dots' => '<svg class="lf-account-dashboard__icon-svg" viewBox="0 0 24 24" role="img" aria-hidden="true" focusable="false"><circle fill="currentColor" cx="5" cy="12" r="2"/><circle fill="currentColor" cx="12" cy="12" r="2"/><circle fill="currentColor" cx="19" cy="12" r="2"/></svg>',
        ];

        return isset($icons[$name]) ? $icons[$name] : $icons['dots'];
    }

    protected function get_endpoint_content_html($endpoint)
    {
        if ($this->is_dashboard_endpoint($endpoint)) {
            return '';
        }

        if (!function_exists('do_action')) {
            return '';
        }

        $slug = sanitize_title($endpoint);

        if ($slug === '') {
            return '';
        }

        global $wp;
        $value = '';
        if (isset($wp->query_vars[$slug])) {
            $value = $wp->query_vars[$slug];
        }

        if (!has_action("woocommerce_account_{$slug}_endpoint")) {
            return '';
        }

        ob_start();
        /**
         * Mimic WooCommerce default endpoint rendering.
         *
         * @see woocommerce_account_content()
         */
        do_action("woocommerce_account_{$slug}_endpoint", $value);
        $content = trim(ob_get_clean());

        return $content;
    }

    protected function get_endpoint_title($endpoint, array $navigation)
    {
        foreach ($navigation as $item) {
            if (isset($item['endpoint']) && $item['endpoint'] === $endpoint && !empty($item['label'])) {
                return $item['label'];
            }
        }

        if ($endpoint === '' || $endpoint === null) {
            return '';
        }

        return ucwords(str_replace(['-', '_'], ' ', $endpoint));
    }

    protected function is_dashboard_endpoint($endpoint)
    {
        return $endpoint === '' || $endpoint === null || $endpoint === 'dashboard';
    }

    protected function render_logged_out_view($message, $login_url, $register_url, $registration_allowed)
    {
        $login_redirect = function_exists('wc_get_page_permalink')
            ? wc_get_page_permalink('myaccount')
            : $login_url;

        echo '<div class="lf-account-dashboard lf-account-dashboard--logged-out">';

        if (function_exists('wc_print_notices')) {
            wc_print_notices();
        }

        echo '<div class="lf-account-dashboard__auth">';
        echo '<div class="lf-account-dashboard__auth-header">';
        echo '<h2>' . esc_html__('Welcome back', 'lime-filters') . '</h2>';
        if ($message !== '') {
            echo '<p>' . esc_html($message) . '</p>';
        }
        echo '</div>';

        echo '<div class="lf-account-dashboard__auth--split">';

        // Login column.
        echo '<div class="lf-account-dashboard__auth-col lf-account-dashboard__auth-col--login">';
        echo '<div class="lf-account-dashboard__auth-icon">';
        echo '<svg viewBox="0 0 48 48" aria-hidden="true" focusable="false"><path fill="currentColor" d="M24 4a10 10 0 1 1 0 20 10 10 0 0 1 0-20zm0 24c8.84 0 16 5.16 16 11.5V44H8v-4.5C8 33.16 15.16 28 24 28z"/></svg>';
        echo '</div>';
        echo '<h3>' . esc_html__('Sign in to your account', 'lime-filters') . '</h3>';
        echo '<p>' . esc_html__('Access your orders, saved addresses, wishlists, and personalized dashboard.', 'lime-filters') . '</p>';

        if (function_exists('woocommerce_login_form')) {
            ob_start();
            woocommerce_login_form([
                'redirect' => esc_url($login_redirect),
            ]);
            $login_form = ob_get_clean();
            echo '<div class="lf-account-dashboard__form-wrapper">' . $login_form . '</div>';
        } else {
            echo '<a class="lf-account-dashboard__button lf-account-dashboard__button--primary" href="' . esc_url($login_url) . '">' . esc_html__('Log in', 'lime-filters') . '</a>';
        }

        echo '</div>';

        // Registration column.
        echo '<div class="lf-account-dashboard__auth-col lf-account-dashboard__auth-col--register">';
        echo '<div class="lf-account-dashboard__auth-icon">';
        echo '<svg viewBox="0 0 48 48" aria-hidden="true" focusable="false"><path fill="currentColor" d="M24 4a10 10 0 1 1 0 20 10 10 0 0 1 0-20zm0 24c8.84 0 16 5.16 16 11.5V44H8v-4.5C8 33.16 15.16 28 24 28z"/></svg>';
        echo '</div>';
        echo '<h3>' . esc_html__('Create a new account', 'lime-filters') . '</h3>';
        echo '<p>' . esc_html__('Register to track orders, save favorites, and receive tailored product updates.', 'lime-filters') . '</p>';

        if ($registration_allowed) {
            $generate_username = 'yes' !== get_option('woocommerce_registration_generate_username');
            $generate_password = 'yes' !== get_option('woocommerce_registration_generate_password');

            echo '<form method="post" class="woocommerce-form woocommerce-form-register register lf-account-dashboard__form">';

            if (function_exists('do_action')) {
                do_action('woocommerce_register_form_start');
            }

            if ($generate_username) {
                $username_value = !empty($_POST['username']) ? sanitize_user(wp_unslash($_POST['username'])) : '';
                echo '<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">';
                echo '<label for="reg_username">' . esc_html__('Username', 'woocommerce') . ' <span class="required">*</span></label>';
                echo '<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="reg_username" autocomplete="username" value="' . esc_attr($username_value) . '" />';
                echo '</p>';
            }

            $email_value = !empty($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
            echo '<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">';
            echo '<label for="reg_email">' . esc_html__('Email address', 'woocommerce') . ' <span class="required">*</span></label>';
            echo '<input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" autocomplete="email" value="' . esc_attr($email_value) . '" />';
            echo '</p>';

            if ($generate_password) {
                $password_value = !empty($_POST['password']) ? wp_unslash($_POST['password']) : '';
                echo '<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">';
                echo '<label for="reg_password">' . esc_html__('Password', 'woocommerce') . ' <span class="required">*</span></label>';
                echo '<input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" autocomplete="new-password" value="' . esc_attr($password_value) . '" />';
                echo '</p>';
            } else {
                echo '<p class="lf-account-dashboard__auth-note">' . esc_html__('A secure password will be generated and emailed to you.', 'lime-filters') . '</p>';
            }

            if (function_exists('do_action')) {
                do_action('woocommerce_register_form');
            }

            echo '<div class="lf-account-dashboard__auth-actions">';
            if (function_exists('do_action')) {
                do_action('woocommerce_register_form_end');
            }
            if (function_exists('wp_nonce_field')) {
                wp_nonce_field('woocommerce-register', 'woocommerce-register-nonce');
            }
            echo '<button type="submit" class="woocommerce-Button button lf-account-dashboard__button lf-account-dashboard__button--primary" name="register" value="' . esc_attr__('Register', 'woocommerce') . '">' . esc_html__('Create account', 'lime-filters') . '</button>';
            echo '</div>';

            echo '</form>';
        } else {
            echo '<p class="lf-account-dashboard__auth-note">' . esc_html__('Online registration is currently disabled. Please contact our team for assistance.', 'lime-filters') . '</p>';
            if (!empty($register_url)) {
                echo '<a class="lf-account-dashboard__button lf-account-dashboard__button--ghost" href="' . esc_url($register_url) . '">' . esc_html__('Contact support', 'lime-filters') . '</a>';
            }
        }

        echo '</div>';

        echo '</div>'; // auth split
        echo '</div>'; // auth container
        echo '</div>'; // dashboard
    }

    protected function is_elementor_editor()
    {
        if (!did_action('elementor/loaded')) {
            return false;
        }

        $plugin = \Elementor\Plugin::$instance;
        if (isset($plugin->editor) && method_exists($plugin->editor, 'is_edit_mode') && $plugin->editor->is_edit_mode()) {
            return true;
        }
        if (isset($plugin->preview) && method_exists($plugin->preview, 'is_preview_mode') && $plugin->preview->is_preview_mode()) {
            return true;
        }
        return false;
    }
}
