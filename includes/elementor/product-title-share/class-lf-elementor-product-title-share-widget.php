<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

if (class_exists('LF_Elementor_Product_Title_Share_Widget')) {
    return;
}

if (!class_exists('\\Elementor\\Widget_Base')) {
    return;
}

class LF_Elementor_Product_Title_Share_Widget extends Widget_Base
{
    protected static $assets_enqueued = false;

    public function get_name()
    {
        return 'lf-product-title-share';
    }

    public function get_title()
    {
        return __('LF Product Title & Share', 'lime-filters');
    }

    public function get_icon()
    {
        return 'eicon-post-title';
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

        $this->add_control('heading_tag', [
            'label' => __('HTML Tag', 'lime-filters'),
            'type' => Controls_Manager::SELECT,
            'options' => [
                'h1' => 'H1',
                'h2' => 'H2',
                'h3' => 'H3',
                'h4' => 'H4',
                'h5' => 'H5',
                'h6' => 'H6',
                'p'  => 'p',
                'div' => 'div',
            ],
            'default' => 'h1',
        ]);

        $this->add_control('custom_title', [
            'label' => __('Override Title', 'lime-filters'),
            'type' => Controls_Manager::TEXT,
            'description' => __('Leave empty to use the product title.', 'lime-filters'),
        ]);

        $this->add_control('show_share', [
            'label' => __('Show Share Actions', 'lime-filters'),
            'type'  => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('share_label', [
            'label' => __('Share Heading', 'lime-filters'),
            'type'  => Controls_Manager::TEXT,
            'default' => __('Share this product', 'lime-filters'),
            'condition' => [
                'show_share' => 'yes',
            ],
        ]);

        $this->add_control('share_layout', [
            'label' => __('Share Layout', 'lime-filters'),
            'type'  => Controls_Manager::SELECT,
            'options' => [
                'inline' => __('Inline', 'lime-filters'),
                'stacked' => __('Stacked', 'lime-filters'),
            ],
            'default' => 'inline',
            'condition' => [
                'show_share' => 'yes',
            ],
        ]);

        $this->add_control('show_copy', [
            'label' => __('Show Copy Link', 'lime-filters'),
            'type'  => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'condition' => [
                'show_share' => 'yes',
            ],
        ]);

        $this->add_control('copy_label', [
            'label' => __('Copy Button Label', 'lime-filters'),
            'type'  => Controls_Manager::TEXT,
            'default' => __('Copy link', 'lime-filters'),
            'condition' => [
                'show_share' => 'yes',
                'show_copy' => 'yes',
            ],
        ]);

        $this->add_control('copy_success', [
            'label' => __('Copy Success Message', 'lime-filters'),
            'type'  => Controls_Manager::TEXT,
            'default' => __('Link copied!', 'lime-filters'),
            'condition' => [
                'show_share' => 'yes',
                'show_copy' => 'yes',
            ],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('section_style_title', [
            'label' => __('Title', 'lime-filters'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('title_color', [
            'label' => __('Color', 'lime-filters'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .lf-product-title-share__title' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .lf-product-title-share__title',
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section('section_style_share', [
            'label' => __('Share', 'lime-filters'),
            'tab'   => Controls_Manager::TAB_STYLE,
            'condition' => [
                'show_share' => 'yes',
            ],
        ]);

        $this->add_control('share_label_color', [
            'label' => __('Label Color', 'lime-filters'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .lf-product-title-share__share-label' => 'color: {{VALUE}};',
                '{{WRAPPER}} .lf-product-title-share__modal-title' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('share_icon_color', [
            'label' => __('Icon Color', 'lime-filters'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .lf-product-title-share__button svg' => 'fill: {{VALUE}};',
            ],
        ]);

        $this->add_control('share_icon_background', [
            'label' => __('Icon Background', 'lime-filters'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .lf-product-title-share__button' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    public function render()
    {
        $product = $this->get_current_product();
        if (!$product instanceof WC_Product) {
            echo '<div class="lf-product-title-share lf-product-title-share--empty">' . esc_html__('Product context not found.', 'lime-filters') . '</div>';
            return;
        }

        self::ensure_assets();
        wp_enqueue_style('lf-product-title-share');
        wp_enqueue_script('lf-product-title-share');

        $settings = $this->get_settings_for_display();

        $tag = isset($settings['heading_tag']) ? $settings['heading_tag'] : 'h1';
        $allowed_tags = ['h1','h2','h3','h4','h5','h6','p','div'];
        if (!in_array($tag, $allowed_tags, true)) {
            $tag = 'h1';
        }

        $title = isset($settings['custom_title']) && $settings['custom_title'] !== ''
            ? $settings['custom_title']
            : $product->get_name();

        $show_share = isset($settings['show_share']) && $settings['show_share'] === 'yes';

        $wrapper_classes = ['lf-product-title-share'];
        if ($show_share) {
            $wrapper_classes[] = 'lf-product-title-share--with-share';
        }

        $permalink = get_permalink($product->get_id());

        echo '<div class="' . esc_attr(implode(' ', $wrapper_classes)) . '">';
        printf('<%1$s class="lf-product-title-share__title">%2$s</%1$s>', esc_attr($tag), esc_html($title));

        if ($show_share) {
            $share_items = $this->get_share_items($product, $permalink, $title);

            $layout = isset($settings['share_layout']) ? $settings['share_layout'] : 'inline';
            $share_label = isset($settings['share_label']) && $settings['share_label'] !== ''
                ? $settings['share_label']
                : __('Share this product', 'lime-filters');
            $show_copy = isset($settings['show_copy']) && $settings['show_copy'] === 'yes';
            $copy_label = isset($settings['copy_label']) && $settings['copy_label'] !== ''
                ? $settings['copy_label']
                : __('Copy link', 'lime-filters');
            $copy_success = isset($settings['copy_success']) && $settings['copy_success'] !== ''
                ? $settings['copy_success']
                : __('Link copied!', 'lime-filters');

            echo '<div class="lf-product-title-share__share lf-product-title-share__share--' . esc_attr($layout) . '" data-copy-success="' . esc_attr($copy_success) . '">';
            if ($share_label !== '') {
                echo '<div class="lf-product-title-share__share-label">' . esc_html($share_label) . '</div>';
            }
            echo '<div class="lf-product-title-share__buttons">';
            foreach ($share_items as $item) {
                echo '<a class="lf-product-title-share__button" href="' . esc_url($item['url']) . '" target="_blank" rel="nofollow noopener noreferrer" aria-label="' . esc_attr($item['label']) . '">';
                echo $item['icon'];
                echo '<span class="lf-product-title-share__button-label sr-only">' . esc_html($item['label']) . '</span>';
                echo '</a>';
            }
            if ($show_copy) {
                echo '<button type="button" class="lf-product-title-share__button lf-product-title-share__button--copy" data-share-copy>';
                echo $this->render_icon_svg('link');
                echo '<span class="lf-product-title-share__button-label">' . esc_html($copy_label) . '</span>';
                echo '</button>';
            }
            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
    }


    protected function get_share_items(WC_Product $product, $permalink, $title)
    {
        $encoded_url = rawurlencode($permalink);
        $encoded_title = rawurlencode($title);

        return [
            [
                'label' => __('Share on Facebook', 'lime-filters'),
                'url'   => 'https://www.facebook.com/sharer/sharer.php?u=' . $encoded_url,
                'icon'  => $this->render_icon_svg('facebook'),
            ],
            [
                'label' => __('Share on X', 'lime-filters'),
                'url'   => 'https://twitter.com/intent/tweet?text=' . $encoded_title . '&url=' . $encoded_url,
                'icon'  => $this->render_icon_svg('twitter'),
            ],
            [
                'label' => __('Share on Pinterest', 'lime-filters'),
                'url'   => 'https://pinterest.com/pin/create/button/?url=' . $encoded_url,
                'icon'  => $this->render_icon_svg('pinterest'),
            ],
            [
                'label' => __('Share on LinkedIn', 'lime-filters'),
                'url'   => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $encoded_url,
                'icon'  => $this->render_icon_svg('linkedin'),
            ],
            [
                'label' => __('Share via Email', 'lime-filters'),
                'url'   => 'mailto:?subject=' . rawurlencode(sprintf(__('Check out %s', 'lime-filters'), $title)) . '&body=' . $encoded_url,
                'icon'  => $this->render_icon_svg('email'),
            ],
        ];
    }

    protected static function ensure_assets()
    {
        if (self::$assets_enqueued) {
            return;
        }

        wp_register_style(
            'lf-product-title-share',
            LF_PLUGIN_URL . 'includes/elementor/product-title-share/product-title-share.css',
            ['lime-filters'],
            LF_VERSION
        );

        wp_register_script(
            'lf-product-title-share',
            LF_PLUGIN_URL . 'includes/elementor/product-title-share/product-title-share.js',
            [],
            LF_VERSION,
            true
        );

        self::$assets_enqueued = true;
    }

    protected function render_icon_svg($name)
    {
        $icons = [
            'facebook' => '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M22 12a10 10 0 1 0-11.5 9.9v-7h-2v-3h2v-2.3c0-2 1.2-3.2 3-3.2 .9 0 1.8.2 1.8.2v2h-1c-1 0-1.3.6-1.3 1.2V12h2.3l-.4 3h-1.9v7A10 10 0 0 0 22 12"/></svg>',
            'twitter'  => '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M21 5.9c-.7.3-1.4.5-2.2.6a3.8 3.8 0 0 0 1.6-2 7.6 7.6 0 0 1-2.4.9 3.7 3.7 0 0 0-6.3 3.4 10.5 10.5 0 0 1-7.6-3.8 3.6 3.6 0 0 0-.5 1.9 3.7 3.7 0 0 0 1.6 3.1 3.7 3.7 0 0 1-1.7-.5v.1a3.7 3.7 0 0 0 3 3.6c-.4.1-.8.2-1.2.2-.3 0-.6 0-.8-.1a3.7 3.7 0 0 0 3.4 2.5 7.5 7.5 0 0 1-4.6 1.6h-.9a10.6 10.6 0 0 0 16.3-9c0-.2 0-.3-.1-.5A7.4 7.4 0 0 0 21 5.9"/></svg>',
            'pinterest' => '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a10 10 0 0 0-3.5 19.4c-.1-.8-.3-2 .1-2.8l2-6.8s-.5-1-.5-2c0-1.9 1.1-3.3 2.5-3.3 1.2 0 1.8.9 1.8 2s-.8 3.2-1.2 5c-.3 1.3.6 2.3 1.9 2.3 2.3 0 3.8-2.9 3.8-6.4 0-2.6-1.8-4.5-5.1-4.5A5.9 5.9 0 0 0 6 11.4a4.5 4.5 0 0 0 1.1 3l.3-.1c.3-.1.3-.2.4-.4l.3-1.1c0-.2 0-.2-.1-.3a3.4 3.4 0 0 1-.5-1.6c0-3.2 2.4-6.1 6.4-6.1 3.4 0 5.8 2.3 5.8 5.4 0 3.6-1.9 6.7-4.9 6.7-1 0-1.9-.5-2.2-1.2l-.6 2.3c-.2.9-.8 2-1.2 2.7a10 10 0 1 1 9.1-9.9"/></svg>',
            'linkedin' => '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6.5 19h-3v-9h3v9zM5 8.5A1.75 1.75 0 1 1 5 5a1.75 1.75 0 0 1 0 3.5zM20.5 19h-3v-4.7c0-1.1-.4-1.9-1.4-1.9-.8 0-1.3.5-1.5 1-.1.2-.1.5-.1.8V19h-3s.1-8.1 0-9h3v1.3c.4-.7 1.1-1.6 2.8-1.6 2 0 3.5 1.3 3.5 4V19z"/></svg>',
            'email' => '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zm0 2v.5l8 5 8-5V6H4zm16 12V9l-8 5-8-5v9h16z"/></svg>',
            'link' => '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M10.6 13.4a1 1 0 0 1 0-1.4l3-3a1 1 0 1 1 1.4 1.4l-3 3a1 1 0 0 1-1.4 0z"/><path fill="currentColor" d="M7.8 17.2a3 3 0 0 1 0-4.2l2-2a1 1 0 1 0-1.4-1.4l-2 2a5 5 0 1 0 7.1 7.1l2-2a1 1 0 0 0-1.4-1.4l-2 2a3 3 0 0 1-4.3-.1z"/><path fill="currentColor" d="M16.2 6.8a3 3 0 0 1 0 4.2l-2 2a1 1 0 1 0 1.4 1.4l2-2a5 5 0 0 0-7.1-7.1l-2 2a1 1 0 1 0 1.4 1.4l2-2a3 3 0 0 1 4.3.1z"/></svg>',
            'share' => '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7a3 3 0 0 0 0-1.39l7.05-4.11A2.99 2.99 0 1 0 15 5a3 3 0 0 0 .04.49L8 9.6a3 3 0 1 0 0 4.79l7.04 4.11c-.02.17-.04.34-.04.5a3 3 0 1 0 3-3z"/></svg>',
        ];

        return isset($icons[$name]) ? $icons[$name] : '';
    }

    protected function get_current_product()
    {
        global $product;
        if ($product instanceof WC_Product) {
            return $product;
        }

        $post = get_post();
        if ($post instanceof WP_Post && $post->post_type === 'product') {
            return wc_get_product($post->ID);
        }

        return null;
    }
}
