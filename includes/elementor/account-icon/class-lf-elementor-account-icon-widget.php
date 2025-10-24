<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Icons_Manager;

if (class_exists('LF_Elementor_Account_Icon_Widget')) {
    return;
}

if (!class_exists('\\Elementor\\Widget_Base')) {
    return;
}

class LF_Elementor_Account_Icon_Widget extends \Elementor\Widget_Base
{
    protected static $assets_registered = false;

    public function get_name()
    {
        return 'lf-account-icon';
    }

    public function get_title()
    {
        return __('LF Account Icon', 'lime-filters');
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

        $this->add_control('icon', [
            'label'   => __('Icon', 'lime-filters'),
            'type'    => Controls_Manager::ICONS,
            'default' => [
                'value'   => 'fas fa-user-circle',
                'library' => 'fa-solid',
            ],
        ]);

        $this->add_control('show_orders_link', [
            'label' => __('Show Orders Link', 'lime-filters'),
            'type'  => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_wishlist_link', [
            'label' => __('Show Wishlist Link (if enabled)', 'lime-filters'),
            'type'  => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_addresses_link', [
            'label' => __('Show Addresses Link', 'lime-filters'),
            'type'  => Controls_Manager::SWITCHER,
            'default' => '',
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_style', [
            'label' => __('Style', 'lime-filters'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('icon_color', [
            'label' => __('Icon Color', 'lime-filters'),
            'type'  => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .lf-account-icon__trigger' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('background_color', [
            'label' => __('Trigger Background', 'lime-filters'),
            'type'  => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .lf-account-icon__trigger' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('dropdown_background', [
            'label' => __('Dropdown Background', 'lime-filters'),
            'type'  => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .lf-account-icon__dropdown' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_mobile', [
            'label' => __('Mobile', 'lime-filters'),
        ]);

        $this->add_control('mobile_append', [
            'label' => __('Append Links to Navigation on Mobile', 'lime-filters'),
            'type'  => Controls_Manager::SWITCHER,
            'default' => '',
        ]);

        $this->add_control('mobile_append_selector', [
            'label' => __('Target Selector', 'lime-filters'),
            'type'  => Controls_Manager::TEXT,
            'placeholder' => '.mobile-nav',
            'description' => __('CSS selector where the mobile account block should be appended (e.g., .header-mobile-nav).', 'lime-filters'),
            'condition' => [
                'mobile_append' => 'yes',
            ],
        ]);

        $this->add_control('mobile_append_title', [
            'label' => __('Mobile Section Title', 'lime-filters'),
            'type'  => Controls_Manager::TEXT,
            'default' => '',
            'condition' => [
                'mobile_append' => 'yes',
            ],
        ]);

        $this->add_control('mobile_breakpoint', [
            'label' => __('Breakpoint (px)', 'lime-filters'),
            'type'  => Controls_Manager::NUMBER,
            'min'   => 320,
            'max'   => 1440,
            'step'  => 10,
            'default' => 768,
            'condition' => [
                'mobile_append' => 'yes',
            ],
        ]);

        $this->end_controls_section();
    }

    public function render()
    {
        self::ensure_assets();
        wp_enqueue_style('lf-account-icon');
        wp_enqueue_script('lf-account-icon-script');

        $settings = $this->get_settings_for_display();

        $user = wp_get_current_user();
        $is_logged_in = $user instanceof WP_User && $user->ID > 0;
        $account_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/');
        $current_url = home_url(add_query_arg([], $GLOBALS['wp']->request ?? ''));
        $logout_url = function_exists('wc_logout_url') ? wc_logout_url($current_url) : wp_logout_url($current_url);
        $orders_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('orders') : $account_url;
        $addresses_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('edit-address') : $account_url;
        $wishlist_url = (class_exists('LF_Wishlist') && method_exists('LF_Wishlist', 'get_page_url')) ? LF_Wishlist::get_page_url() : '';
        $wishlist_enabled = $wishlist_url !== '' && class_exists('LF_Wishlist') && LF_Wishlist::is_enabled();

        $display_name = $is_logged_in ? ($user->display_name ?: $user->user_login) : __('Guest', 'lime-filters');

        $trigger_icon = '';
        if (!empty($settings['icon'])) {
            ob_start();
            Icons_Manager::render_icon($settings['icon'], ['aria-hidden' => 'true']);
            $trigger_icon = ob_get_clean();
        }
        if ($trigger_icon === '') {
            $trigger_icon = $this->render_icon_svg('user-circle');
        }

        $state_class = $is_logged_in ? 'lf-account-icon--logged-in' : 'lf-account-icon--guest';

        $mobile_enabled = isset($settings['mobile_append']) && $settings['mobile_append'] === 'yes';
        $mobile_selector = '';
        if ($mobile_enabled && !empty($settings['mobile_append_selector'])) {
            $mobile_selector = trim($settings['mobile_append_selector']);
        } else {
            $mobile_enabled = false;
        }

        $mobile_breakpoint = isset($settings['mobile_breakpoint']) && (int) $settings['mobile_breakpoint'] > 0
            ? (int) $settings['mobile_breakpoint']
            : 768;
        $mobile_title = isset($settings['mobile_append_title']) ? $settings['mobile_append_title'] : '';
        $widget_uid = wp_unique_id('lf-account-icon-');

        $links = [];
        if ($is_logged_in) {
            $links[] = [
                'label' => __('Dashboard', 'lime-filters'),
                'url'   => $account_url,
                'icon'  => $this->render_icon_svg('dashboard'),
            ];
            if ($settings['show_orders_link'] === 'yes') {
                $links[] = [
                    'label' => __('Orders', 'lime-filters'),
                    'url'   => $orders_url,
                    'icon'  => $this->render_icon_svg('orders'),
                ];
            }
            if ($settings['show_addresses_link'] === 'yes') {
                $links[] = [
                    'label' => __('Addresses', 'lime-filters'),
                    'url'   => $addresses_url,
                    'icon'  => $this->render_icon_svg('location'),
                ];
            }
            if ($settings['show_wishlist_link'] === 'yes' && $wishlist_enabled) {
                $links[] = [
                    'label' => __('Wishlist', 'lime-filters'),
                    'url'   => $wishlist_url,
                    'icon'  => $this->render_icon_svg('wishlist'),
                ];
            }
            $links[] = [
                'label' => __('Log out', 'lime-filters'),
                'url'   => $logout_url,
                'icon'  => $this->render_icon_svg('logout'),
            ];
        } else {
            $login_url = $account_url;
            $register_url = add_query_arg('register', 'true', $account_url);
            $links[] = [
                'label' => __('Sign In', 'lime-filters'),
                'url'   => $login_url,
                'icon'  => $this->render_icon_svg('login'),
            ];
            if (function_exists('wc_registration_enabled') && wc_registration_enabled()) {
                $links[] = [
                    'label' => __('Create Account', 'lime-filters'),
                    'url'   => $register_url,
                    'icon'  => $this->render_icon_svg('register'),
                ];
            }
        }

        $greeting = $is_logged_in ? sprintf(__('Hello, %s', 'lime-filters'), $display_name) : __('Welcome back', 'lime-filters');
        $description = $is_logged_in
            ? __('Manage your account', 'lime-filters')
            : __('Access your account tools', 'lime-filters');

        $wrapper_attrs = 'class="lf-account-icon ' . esc_attr($state_class) . '" tabindex="0" data-widget-id="' . esc_attr($widget_uid) . '"';
        if ($mobile_enabled && $mobile_selector !== '') {
            $wrapper_attrs .= ' data-mobile-append="' . esc_attr($mobile_selector) . '"';
            $wrapper_attrs .= ' data-mobile-breakpoint="' . esc_attr($mobile_breakpoint) . '"';
            $wrapper_attrs .= ' data-mobile-id="' . esc_attr($widget_uid) . '"';
            if ($mobile_title !== '') {
                $wrapper_attrs .= ' data-mobile-title="' . esc_attr($mobile_title) . '"';
            }
        }

        echo '<div ' . $wrapper_attrs . '>';
        echo '<button type="button" class="lf-account-icon__trigger" aria-haspopup="true" aria-expanded="false">';
        echo '<span class="lf-account-icon__trigger-inner">' . $trigger_icon . '</span>';
        if ($is_logged_in) {
            echo '<span class="lf-account-icon__badge" aria-hidden="true">' . esc_html(mb_strtoupper(mb_substr($display_name, 0, 1))) . '</span>';
        }
        echo '</button>';

        echo '<div class="lf-account-icon__dropdown" role="menu">';
        echo '<div class="lf-account-icon__header">';
        echo '<span class="lf-account-icon__greeting">' . esc_html($greeting) . '</span>';
        echo '<span class="lf-account-icon__subtext">' . esc_html($description) . '</span>';
        echo '</div>';

        if (!empty($links)) {
            echo '<ul class="lf-account-icon__list">';
            foreach ($links as $link) {
                $icon = isset($link['icon']) ? $link['icon'] : '';
                $label = isset($link['label']) ? $link['label'] : '';
                $url = isset($link['url']) ? $link['url'] : '#';
                echo '<li class="lf-account-icon__item">';
                echo '<a class="lf-account-icon__link" href="' . esc_url($url) . '">';
                if ($icon !== '') {
                    echo '<span class="lf-account-icon__link-icon" aria-hidden="true">' . $icon . '</span>';
                }
                echo '<span class="lf-account-icon__link-label">' . esc_html($label) . '</span>';
                echo '</a>';
                echo '</li>';
            }
            echo '</ul>';
        }

        echo '</div>'; // dropdown
        echo '</div>'; // wrapper
    }

    protected static function ensure_assets()
    {
        if (self::$assets_registered) {
            return;
        }

        wp_register_style(
            'lf-account-icon',
            LF_PLUGIN_URL . 'includes/elementor/account-icon/account-icon.css',
            ['lime-filters'],
            LF_VERSION
        );

        wp_register_script(
            'lf-account-icon-script',
            LF_PLUGIN_URL . 'includes/elementor/account-icon/account-icon.js',
            [],
            LF_VERSION,
            true
        );

        self::$assets_registered = true;
    }

    protected function render_icon_svg($name)
    {
        $icons = [
            'user-circle' => '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 12c2.7 0 4.9-2.2 4.9-4.9S14.7 2.3 12 2.3 7.1 4.5 7.1 7.1 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8V21h19.2v-1.8c0-3.2-6.4-4.8-9.6-4.8z"/></svg>',
            'dashboard'   => '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8v-10h-8v10zm0-18v6h8V3h-8z"/></svg>',
            'orders'      => '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6 2h12a2 2 0 0 1 2 2v16l-8-3-8 3V4a2 2 0 0 1 2-2zm0 2v11.17l6-2.25 6 2.25V4H6z"/></svg>',
            'location'    => '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg>',
            'wishlist'    => '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41 1.01 4.5 2.57C13.09 4.01 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>',
            'logout'      => '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M16 13v-2H8V8l-4 4 4 4v-3h8zm3-10H11a2 2 0 0 0-2 2v3h2V5h8v14h-8v-3H9v3a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2z"/></svg>',
            'login'       => '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M10 17l-1.41-1.41L13.17 11H3v-2h10.17l-4.58-4.59L10 3l7 7-7 7zm9 4H11v-2h8V5h-8V3h8a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2z"/></svg>',
            'register'    => '<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M15 12c2.7 0 4.9-2.2 4.9-4.9S17.7 2.3 15 2.3 10.1 4.5 10.1 7.1 12.3 12 15 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8V21h9v-2h-7.07c.83-1.74 4.06-2.6 7.67-2.6.59 0 1.17.03 1.73.08A3.5 3.5 0 0 1 19.5 16H21v-2h-1.5a3.5 3.5 0 1 1-3.5-3.5V9h-2v1.5A3.5 3.5 0 0 1 15 14.4z"/></svg>',
        ];

        return isset($icons[$name]) ? $icons[$name] : '';
    }
}
