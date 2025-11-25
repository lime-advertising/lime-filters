<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class LF_Admin {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
    }

    public static function menu() {
        add_submenu_page(
            'woocommerce',
            __('Lime Filters','lime-filters'),
            __('Lime Filters','lime-filters'),
            'manage_woocommerce',
            'lime-filters',
            [__CLASS__, 'render_page']
        );
    }

    public static function register_settings() {
        register_setting('lime_filters_group', 'lime_filters_map', [
            'type'              => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_map'],
            'default'           => [],
        ]);
        register_setting('lime_filters_group', 'lime_filters_brand_colors');
        register_setting('lime_filters_group', 'lime_filters_shop_show_categories');
        register_setting('lime_filters_group', 'lime_filters_affiliate_upsell', [
            'type'              => 'string',
            'sanitize_callback' => function($value) {
                return $value === 'yes' ? 'yes' : 'no';
            },
            'default'           => 'no',
        ]);
    }

    public static function assets($hook) {
        if ($hook !== 'woocommerce_page_lime-filters') {
            return;
        }

        wp_enqueue_style(
            'lime-filters-admin',
            LF_PLUGIN_URL . 'includes/assets/admin/lime-filters-admin.css',
            [],
            LF_VERSION
        );

        wp_enqueue_script(
            'lime-filters-admin',
            LF_PLUGIN_URL . 'includes/assets/admin/lime-filters-admin.js',
            [],
            LF_VERSION,
            true
        );

        wp_localize_script('lime-filters-admin', 'LimeFiltersAdmin', [
            'attributes' => self::attribute_options(),
            'i18n'       => [
                'placeholder' => __('Add attribute...','lime-filters'),
                'noMatches'   => __('No matching attributes','lime-filters'),
                'remove'      => __('Remove attribute','lime-filters'),
            ],
        ]);
    }

    protected static function attribute_options() {
        if ( ! function_exists('wc_get_attribute_taxonomies') ) {
            return [];
        }

        $options = [];
        $taxes = wc_get_attribute_taxonomies();
        if ($taxes) {
            foreach ($taxes as $tax) {
                $slug = wc_attribute_taxonomy_name($tax->attribute_name);
                $label = $tax->attribute_label ?: $tax->attribute_name;
                $options[] = [
                    'slug'  => $slug,
                    'label' => $label,
                ];
            }
        }

        usort($options, function($a, $b){
            return strcasecmp($a['label'], $b['label']);
        });

        return $options;
    }

    public static function sanitize_map($input) {
        if (!is_array($input)) {
            return [];
        }

        $output = [];

        foreach ($input as $key => $value) {
            $raw_key = is_string($key) ? $key : '';
            if ($raw_key === '') {
                continue;
            }

            $normalized_key = ($raw_key === '__shop__') ? '__shop__' : sanitize_title($raw_key);
            if ($normalized_key === '') {
                continue;
            }

            $values = [];

            if (is_string($value)) {
                $values = [$value];
            } elseif (is_array($value)) {
                $values = $value;
            } else {
                $values = [];
            }

            $items = [];
            foreach ($values as $item) {
                if (!is_string($item)) {
                    continue;
                }
                $fragments = array_map('trim', explode(',', $item));
                foreach ($fragments as $fragment) {
                    if ($fragment === '') {
                        continue;
                    }
                    if (class_exists('LF_Helpers') && method_exists('LF_Helpers', 'sanitize_attr_tax')) {
                        $fragment = LF_Helpers::sanitize_attr_tax($fragment);
                    } else {
                        $fragment = sanitize_title($fragment);
                    }
                    if ($fragment !== '') {
                        $items[] = $fragment;
                    }
                }
            }

            $items = array_values(array_unique($items));
            $output[$normalized_key] = $items;
        }

        return $output;
    }

    public static function render_page() {
        if (!current_user_can('manage_woocommerce')) return;
        $map    = get_option('lime_filters_map', []);
        if (!is_array($map)) {
            $map = [];
        }
        $shop_show_categories = get_option('lime_filters_shop_show_categories', 'yes');
        $colors = LF_Helpers::colors();
        ?>
        <div class="wrap">
          <h1><?php echo esc_html__('Lime Filters Settings','lime-filters'); ?></h1>
          <h2 class="nav-tab-wrapper">
            <a href="#lf-tab-mapping" class="nav-tab nav-tab-active"><?php esc_html_e('Category Mapping','lime-filters'); ?></a>
            <a href="#lf-tab-colors" class="nav-tab"><?php esc_html_e('Brand Colors','lime-filters'); ?></a>
          </h2>

          <form method="post" action="options.php">
            <?php settings_fields('lime_filters_group'); ?>
            <div id="lf-tab-mapping" class="lf-tab active">
              <p><?php esc_html_e('Map product categories to the attributes you want to show as filters. Use attribute taxonomy slugs (e.g., pa_size, pa_installation-type).','lime-filters'); ?></p>
              <table class="form-table">
                <tbody>
                  <?php
                  $shop_val = isset($map['__shop__']) ? (array) $map['__shop__'] : [];
                  $shop_initial = esc_attr( wp_json_encode( array_values( array_filter($shop_val) ) ) );
                  $shop_display = implode(', ', $shop_val);
                  ?>
                  <tr>
                    <th scope="row"><label><?php esc_html_e('Shop (All Products)','lime-filters'); ?></label></th>
                    <td>
                      <input type="text" class="regular-text lf-attr-raw" data-initial="<?php echo $shop_initial; ?>" name="lime_filters_map[__shop__]" value="<?php echo esc_attr($shop_display); ?>" placeholder="e.g. pa_size, pa_finish">
                      <p class="description"><?php esc_html_e('Attributes shown on the main shop page when no category is selected.','lime-filters'); ?></p>
                    </td>
                  </tr>
                  <?php
                  $cats = get_terms([
                    'taxonomy'   => 'product_cat',
                    'hide_empty' => false,
                    'parent'     => 0,
                    'orderby'    => 'name',
                  ]);
                  if (!is_wp_error($cats)) {
                    foreach ($cats as $cat) {
                        $slug = $cat->slug;
                        if ($slug === '__shop__') {
                            continue;
                        }
                        $val = isset($map[$slug]) ? (array)$map[$slug] : [];
                        $val_str = implode(', ', $val);
                        $data_initial = esc_attr( wp_json_encode( array_values( array_filter($val) ) ) );
                        echo '<tr>';
                        echo '<th scope="row"><label>' . esc_html($cat->name) . ' (' . esc_html($slug) . ')</label></th>';
                        echo '<td>';
                        echo '<input type="text" class="regular-text lf-attr-raw" data-initial="' . $data_initial . '" name="lime_filters_map['.esc_attr($slug).']" value="'.esc_attr($val_str).'" placeholder="e.g. pa_size, pa_installation-type">';
                        echo '<p class="description">'.esc_html__('Start typing to search existing attributes.','lime-filters').'</p>';
                        echo '</td>';
                        echo '</tr>';
                    }
                  }
                  ?>
                  <tr>
                    <th scope="row"><label><?php esc_html_e('Shop Category Filter','lime-filters'); ?></label></th>
                    <td>
                      <label>
                        <input type="hidden" name="lime_filters_shop_show_categories" value="no">
                        <input type="checkbox" name="lime_filters_shop_show_categories" value="yes" <?php checked($shop_show_categories, 'yes'); ?> />
                        <?php esc_html_e('Show category filter on the main shop page','lime-filters'); ?>
                      </label>
                      <p class="description"><?php esc_html_e('When enabled, a category filter appears above other filters on the shop page.','lime-filters'); ?></p>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div id="lf-tab-colors" class="lf-tab" style="display:none;">
              <table class="form-table">
                <tbody>
                  <?php
                  $fields = [
                    'accent'     => __('Primary Accent','lime-filters'),
                    'border'     => __('Border','lime-filters'),
                    'background' => __('Background','lime-filters'),
                    'text'       => __('Text','lime-filters'),
                  ];
                  foreach ($fields as $key => $label) {
                      $val = isset($colors[$key]) ? $colors[$key] : '';
                      echo '<tr><th scope="row"><label>'.esc_html($label).'</label></th>';
                      echo '<td><input type="text" class="lf-color-field" name="lime_filters_brand_colors['.esc_attr($key).']" value="'.esc_attr($val).'" /> </td></tr>';
                  }
                  ?>
                  <tr>
                    <th scope="row"><label for="lf-affiliate-upsell"><?php esc_html_e('Affiliate Upsell Modal','lime-filters'); ?></label></th>
                    <td>
                      <?php $upsell_enabled = get_option('lime_filters_affiliate_upsell', 'no'); ?>
                      <label>
                        <input type="hidden" name="lime_filters_affiliate_upsell" value="no" />
                        <input type="checkbox" id="lf-affiliate-upsell" name="lime_filters_affiliate_upsell" value="yes" <?php checked($upsell_enabled, 'yes'); ?> />
                        <?php esc_html_e('Enable accessory upsell modal after clicking affiliate buttons','lime-filters'); ?>
                      </label>
                      <p class="description"><?php esc_html_e('When disabled, affiliates open directly without prompting for upsell accessories.','lime-filters'); ?></p>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <?php submit_button(); ?>
          </form>
        </div>

        <script>
        (function(){
          const tabs = document.querySelectorAll('.nav-tab');
          const panes = document.querySelectorAll('.lf-tab');
          tabs.forEach(t => t.addEventListener('click', function(e){
            e.preventDefault();
            tabs.forEach(x=>x.classList.remove('nav-tab-active'));
            this.classList.add('nav-tab-active');
            panes.forEach(p=>p.style.display='none');
            const target = document.querySelector(this.getAttribute('href'));
            if (target) target.style.display = '';
          }));
        })();
        </script>
        <style>
          .lf-color-field { width: 150px; }
        </style>
        <?php
    }
}
