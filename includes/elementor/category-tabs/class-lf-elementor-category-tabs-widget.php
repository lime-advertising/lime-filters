<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( class_exists('LF_Elementor_Category_Tabs_Widget') ) {
    return;
}

if ( ! class_exists('\Elementor\Widget_Base') ) {
    return;
}

class LF_Elementor_Category_Tabs_Widget extends \Elementor\Widget_Base {
    protected static $assets_registered = false;

    public function get_name() {
        return 'lime-filters-category-tabs';
    }

    public function get_title() {
        return __('Lime Filters Category Tabs', 'lime-filters');
    }

    public function get_icon() {
        return 'eicon-tabs';
    }

    public function get_categories() {
        return ['general'];
    }

    protected function register_controls() {
        $this->start_controls_section('section_content', [
            'label' => __('Content', 'lime-filters'),
        ]);

        $category_options = $this->get_category_options();

        $reorder_repeater = new \Elementor\Repeater();
        $reorder_repeater->add_control('category', [
            'label' => __('Category', 'lime-filters'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => $category_options,
            'label_block' => true,
            'default' => '',
        ]);

        $this->add_control('tabs', [
            'label' => __('Custom Tab Order', 'lime-filters'),
            'type' => \Elementor\Controls_Manager::REPEATER,
            'fields' => $reorder_repeater->get_controls(),
            'title_field' => '{{{ category }}}',
            'prevent_empty' => false,
            'description' => __('Add categories to display and drag to reorder. Leave empty to use automatic ordering.', 'lime-filters'),
        ]);

        $this->add_control('layout', [
            'label' => __('Layout', 'lime-filters'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'grid',
            'options' => [
                'grid'   => __('Grid', 'lime-filters'),
                'slider' => __('Slider', 'lime-filters'),
            ],
        ]);

        $this->add_control('max_tabs', [
            'label' => __('Maximum Tabs', 'lime-filters'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'min' => 1,
            'max' => 8,
            'step' => 1,
            'default' => 4,
        ]);

        $this->add_control('products_per_tab', [
            'label' => __('Products Per Tab', 'lime-filters'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'min' => 1,
            'max' => 12,
            'step' => 1,
            'default' => 4,
        ]);

        $this->add_control('orderby', [
            'label' => __('Order By', 'lime-filters'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'menu_order',
            'options' => [
                'menu_order'  => __('Menu Order', 'lime-filters'),
                'date'        => __('Date', 'lime-filters'),
                'price'       => __('Price: Low to High', 'lime-filters'),
                'price-desc'  => __('Price: High to Low', 'lime-filters'),
                'popularity'  => __('Popularity', 'lime-filters'),
                'rating'      => __('Rating', 'lime-filters'),
            ],
        ]);

        $this->add_control('order', [
            'label' => __('Order', 'lime-filters'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'ASC',
            'options' => [
                'ASC'  => __('Ascending', 'lime-filters'),
                'DESC' => __('Descending', 'lime-filters'),
            ],
        ]);

        $this->add_responsive_control('columns', [
            'label' => __('Columns', 'lime-filters'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => [
                '1' => '1',
                '2' => '2',
                '3' => '3',
                '4' => '4',
                '5' => '5',
                '6' => '6',
            ],
            'default' => '4',
            'tablet_default' => '2',
            'mobile_default' => '1',
        ]);

        $this->end_controls_section();
    }

    public function render() {
        if ( ! function_exists('wc_get_product') ) {
            return;
        }

        $settings = $this->get_settings_for_display();
        $ordered_tabs = isset($settings['tabs']) ? (array) $settings['tabs'] : [];
        $max_tabs = isset($settings['max_tabs']) ? (int) $settings['max_tabs'] : 4;
        if ($max_tabs < 1) {
            $max_tabs = 4;
        }

        $categories = $this->resolve_categories($max_tabs, $ordered_tabs);
        if (empty($categories)) {
            echo '<div class="lf-category-tabs"><div class="lf-category-tabs__empty">' . esc_html__('No categories available for the tabs widget.', 'lime-filters') . '</div></div>';
            return;
        }

        if ($this->is_elementor_editor()) {
            echo '<div class="lf-category-tabs"><div class="lf-category-tabs__empty">' . esc_html__('Interactive preview available on the front end. Save and view the page to see category tabs.', 'lime-filters') . '</div></div>';
            return;
        }

        $layout = isset($settings['layout']) && $settings['layout'] === 'slider' ? 'slider' : 'grid';
        $limit  = isset($settings['products_per_tab']) ? max(1, (int) $settings['products_per_tab']) : 4;
        $orderby = isset($settings['orderby']) ? $settings['orderby'] : 'menu_order';
        $order   = isset($settings['order']) && in_array($settings['order'], ['ASC', 'DESC'], true) ? $settings['order'] : 'ASC';

        $columns = [
            'desktop' => isset($settings['columns']) ? max(1, (int) $settings['columns']) : 4,
            'tablet'  => isset($settings['columns_tablet']) ? max(1, (int) $settings['columns_tablet']) : 2,
            'mobile'  => isset($settings['columns_mobile']) ? max(1, (int) $settings['columns_mobile']) : 1,
        ];

        self::ensure_assets();

        if ($layout === 'slider') {
            self::ensure_swiper_assets();
        }
        wp_enqueue_style('lime-filters');
        wp_enqueue_style('lf-category-tabs');
        wp_enqueue_script('lf-category-tabs');

        $tabs_id = wp_unique_id('lf-tabs-');

        $nav_items = [];
        $panes = [];

        foreach ($categories as $term) {
            $products = $this->query_products_for_term($term->term_id, $limit, $orderby, $order);
            $product_html = $this->render_products($products, $columns, $layout);
            if ($product_html === '') {
                $product_html = '<div class="lf-category-tabs__empty">' . esc_html__('No products found in this category.', 'lime-filters') . '</div>';
            }

            $tab_id = sanitize_title($term->slug);
            $nav_items[] = sprintf(
                '<button type="button" class="lf-category-tabs__tab" role="tab" data-tab-target="%1$s" aria-controls="%1$s-%2$s">%3$s</button>',
                esc_attr($tab_id),
                esc_attr($tabs_id),
                esc_html($term->name)
            );

            $panes[] = sprintf(
                '<div class="lf-category-tabs__pane" id="%1$s-%3$s" role="tabpanel" data-tab-panel="%1$s">%2$s</div>',
                esc_attr($tab_id),
                $product_html,
                esc_attr($tabs_id)
            );
        }

        if (empty($nav_items)) {
            echo '<div class="lf-category-tabs"><div class="lf-category-tabs__empty">' . esc_html__('Unable to display tabs at this time.', 'lime-filters') . '</div></div>';
            return;
        }

        printf(
            '<div class="lf-category-tabs" id="%1$s"><div class="lf-category-tabs__nav" role="tablist">%2$s</div><div class="lf-category-tabs__panes">%3$s</div></div>',
            esc_attr($tabs_id),
            implode('', $nav_items),
            implode('', $panes)
        );
    }

    protected static function ensure_assets() {
        if (self::$assets_registered) {
            return;
        }

        wp_register_style(
            'lf-category-tabs',
            LF_PLUGIN_URL . 'includes/elementor/category-tabs/category-tabs.css',
            ['lime-filters'],
            LF_VERSION
        );

        wp_register_script(
            'lf-category-tabs',
            LF_PLUGIN_URL . 'includes/elementor/category-tabs/category-tabs.js',
            [],
            LF_VERSION,
            true
        );

        self::$assets_registered = true;
    }

    protected function get_category_options() {
        $terms = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'parent'     => 0,
        ]);

        $options = [];
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $options[$term->slug] = $term->name;
            }
        }

        return $options;
    }

    protected function resolve_categories($max_tabs, array $ordered_tabs = []) {
        $slugs = [];

        if (!empty($ordered_tabs)) {
            foreach ($ordered_tabs as $row) {
                if (isset($row['category']) && $row['category'] !== '') {
                    $sanitized = sanitize_title($row['category']);
                    $term = get_term_by('slug', $sanitized, 'product_cat');
                    if ($term && !is_wp_error($term) && (int) $term->parent === 0) {
                        $slugs[] = $term->slug;
                    }
                }
            }
        }

        if (empty($slugs)) {
            if (class_exists('LF_Helpers')) {
                $map = LF_Helpers::mapping();
                foreach ($map as $slug => $attributes) {
                    if ($slug === '__shop__') {
                        continue;
                    }
                    $sanitized = sanitize_title($slug);
                    $term = get_term_by('slug', $sanitized, 'product_cat');
                    if ($term && !is_wp_error($term) && (int) $term->parent === 0) {
                        $slugs[] = $term->slug;
                    }
                }
            }
        }

        if (empty($slugs)) {
            $fallback_terms = get_terms([
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'parent'     => 0,
                'number'     => $max_tabs > 0 ? $max_tabs : 6,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ]);
            if (!is_wp_error($fallback_terms)) {
                foreach ($fallback_terms as $term) {
                    $slugs[] = $term->slug;
                }
            }
        }

        $slugs = array_values(array_unique(array_filter($slugs)));
        if ($max_tabs > 0 && count($slugs) > $max_tabs) {
            $slugs = array_slice($slugs, 0, $max_tabs);
        }

        $terms = [];
        foreach ($slugs as $slug) {
            $term = get_term_by('slug', $slug, 'product_cat');
            if ($term && !is_wp_error($term) && (int) $term->parent === 0) {
                $terms[] = $term;
            }
        }

        return $terms;
    }

    protected function query_products_for_term($term_id, $limit, $orderby, $order) {
        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'tax_query'      => [
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => [$term_id],
                ],
            ],
            'orderby'        => 'menu_order title',
            'order'          => $order,
        ];

        switch ($orderby) {
            case 'price':
                $args['meta_key'] = '_price';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'ASC';
                break;
            case 'price-desc':
                $args['meta_key'] = '_price';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'DESC';
                break;
            case 'date':
                $args['orderby']  = 'date';
                break;
            case 'rating':
                $args['orderby']  = 'rating';
                break;
            case 'popularity':
                $args['orderby']  = 'popularity';
                break;
            default:
                $args['orderby'] = 'menu_order title';
        }

        $query = new WP_Query($args);
        $ids = [];
        if ($query->have_posts()) {
            $ids = wp_list_pluck($query->posts, 'ID');
        }
        wp_reset_postdata();

        return $ids;
    }

    protected function render_products(array $product_ids, array $columns, $layout) {
        if (empty($product_ids)) {
            return '';
        }

        $layout = ($layout === 'slider') ? 'slider' : 'grid';

        $desktop = max(1, (int) $columns['desktop']);
        $tablet  = max(1, (int) $columns['tablet']);
        $mobile  = max(1, (int) $columns['mobile']);

        if ($layout === 'slider') {
            return $this->render_products_slider($product_ids, $desktop, $tablet, $mobile);
        }

        $style = sprintf(
            '--lf-col-desktop:%d;--lf-col-tablet:%d;--lf-col-mobile:%d;',
            $desktop,
            $tablet,
            $mobile
        );

        ob_start();
        printf(
            '<div class="lf-products" data-columns="%1$d" data-columns-desktop="%1$d" data-columns-tablet="%2$d" data-columns-mobile="%3$d" style="%4$s">',
            (int) $desktop,
            (int) $tablet,
            (int) $mobile,
            esc_attr($style)
        );

        foreach ($product_ids as $product_id) {
            $html = $this->render_product_card($product_id);
            if ($html) {
                echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
        }

        echo '</div>';
        return ob_get_clean();
    }

    protected function render_products_slider(array $product_ids, $desktop, $tablet, $mobile) {
        if (empty($product_ids)) {
            return '';
        }

        $desktop = max(1, (int) $desktop);
        $tablet  = max(1, (int) $tablet);
        $mobile  = max(1, (int) $mobile);

        $slider_id = wp_unique_id('lf-cat-slider-');

        ob_start();
?>
        <div class="lf-category-tabs__slider" data-slides-desktop="<?php echo esc_attr($desktop); ?>" data-slides-tablet="<?php echo esc_attr($tablet); ?>" data-slides-mobile="<?php echo esc_attr($mobile); ?>">
            <div class="lf-products lf-products--slider swiper" id="<?php echo esc_attr($slider_id); ?>">
                <div class="swiper-wrapper">
                    <?php foreach ($product_ids as $product_id) : ?>
                        <div class="swiper-slide">
                            <?php
                                $card = $this->render_product_card($product_id);
                                if ($card) {
                                    echo $card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                }
                            ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="lf-category-tabs__controls">
                <button type="button" class="lf-category-tabs__button lf-category-tabs__prev" aria-label="<?php esc_attr_e('Previous', 'lime-filters'); ?>">
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
                        <path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
                <button type="button" class="lf-category-tabs__button lf-category-tabs__next" aria-label="<?php esc_attr_e('Next', 'lime-filters'); ?>">
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
                        <path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
            </div>
        </div>
<?php
        return ob_get_clean();
    }

    protected function render_product_card($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return '';
        }

        return LF_AJAX::render_product_card($product);
    }

    protected static function ensure_swiper_assets() {
        $script_handle = 'swiper';
        $style_handle = 'swiper';

        if (wp_script_is('swiper', 'registered') || wp_script_is('swiper', 'enqueued')) {
            wp_enqueue_script('swiper');
        } elseif (wp_script_is('swiper-bundle', 'registered') || wp_script_is('swiper-bundle', 'enqueued')) {
            $script_handle = 'swiper-bundle';
            $style_handle = 'swiper-bundle';
            wp_enqueue_script('swiper-bundle');
        } else {
            $script_handle = 'lf-swiper';
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
            wp_enqueue_style('lf-swiper');
            wp_enqueue_script('lf-swiper');
        }

        if (!wp_style_is('swiper', 'enqueued') && !wp_style_is('swiper-bundle', 'enqueued')) {
            if (isset($style_handle) && wp_style_is($style_handle, 'registered')) {
                wp_enqueue_style($style_handle);
            } elseif (!wp_style_is('lf-swiper', 'enqueued')) {
                wp_enqueue_style('lf-swiper');
            }
        }
    }

    protected function is_elementor_editor() {
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
