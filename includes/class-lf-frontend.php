<?php
if (! defined('ABSPATH')) {
  exit;
}

class LF_Frontend
{
  public static function init()
  {
    add_shortcode('lime_filters', [__CLASS__, 'render_shortcode']);
    add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
    add_filter('woocommerce_available_variation', [__CLASS__, 'ensure_variation_price_html'], 10, 3);
    add_action('template_redirect', [__CLASS__, 'track_product_view'], 20);
  }

  public static function assets()
  {
    // CSS
    wp_register_style('lime-filters', LF_PLUGIN_URL . 'includes/assets/css/lime-filters.css', [], LF_VERSION);
    // Colors as CSS variables
    $c = LF_Helpers::colors();
    $vars = ':root{--lf-accent:%1$s;--lf-border:%2$s;--lf-bg:%3$s;--lf-text:%4$s;}';
    $vars = sprintf($vars, esc_attr($c['accent']), esc_attr($c['border']), esc_attr($c['background']), esc_attr($c['text']));
    wp_add_inline_style('lime-filters', $vars);
    // JS
    wp_register_script('lime-filters', LF_PLUGIN_URL . 'includes/assets/js/lime-filters.js', ['jquery'], LF_VERSION, true);
    wp_localize_script('lime-filters', 'LimeFilters', [
      'ajax'      => admin_url('admin-ajax.php'),
      'nonce'     => wp_create_nonce('lf_nonce'),
    ]);
  }

  public static function render_shortcode($atts = [])
  {
    wp_enqueue_style('lime-filters');
    wp_enqueue_script('lime-filters');

    $atts = shortcode_atts([
      'layout'      => 'sidebar', // sidebar|horizontal
      'show_counts' => 'no',
      'default'     => 'collapsed', // collapsed|expanded
      'category'    => '', // override
      'pagination'  => 'no',
      'per_page'    => '',
    ], $atts, 'lime_filters');

    $category_slug    = $atts['category'] ?: LF_Helpers::current_category_slug();
    $layout           = $atts['layout'] === 'horizontal' ? 'horizontal' : 'sidebar';
    $default_state    = ($atts['default'] === 'expanded') ? 'expanded' : 'collapsed';
    $show_counts_flag = ($atts['show_counts'] === 'yes') ? 'yes' : 'no';
    $show_counts_bool = ($show_counts_flag === 'yes');

    $allowed_orderby = ['menu_order', 'popularity', 'rating', 'date', 'price', 'price-desc'];
    $current_orderby = self::query_orderby_from_request($allowed_orderby);

    $pagination_enabled = ($atts['pagination'] === 'yes');
    $current_page = self::query_page_from_request();
    $requested_per_page = isset($atts['per_page']) ? (int) $atts['per_page'] : 0;
    if ($requested_per_page < 1) {
      $requested_per_page = 0;
    } else {
      $max_pp = (int) apply_filters('lime_filters_max_per_page', 48);
      if ($max_pp > 0) {
        $requested_per_page = min($requested_per_page, $max_pp);
      }
    }

    $columns_config = [
      'desktop' => self::sanitize_column_attr($atts['columns_desktop']),
      'tablet'  => self::sanitize_column_attr($atts['columns_tablet']),
      'mobile'  => self::sanitize_column_attr($atts['columns_mobile']),
    ];

    $initial_filters = self::query_filters_from_request();

    $initial_html    = '';
    $pagination_html = '';
    $filters_payload = [];
    $resolved_columns = [];

    if (class_exists('LF_AJAX') && method_exists('LF_AJAX', 'get_products_html')) {
      $response = LF_AJAX::get_products_html($category_slug, $initial_filters, $current_orderby, $current_page, $pagination_enabled, $requested_per_page, $show_counts_flag, $columns_config);
      if (is_array($response)) {
        $initial_html     = isset($response['html']) ? $response['html'] : '';
        $pagination_html  = isset($response['pagination']) ? $response['pagination'] : '';
        $current_page     = isset($response['page']) ? (int) $response['page'] : $current_page;
        if (isset($response['per_page'])) {
          $requested_per_page = (int) $response['per_page'];
        }
        if (isset($response['filters']) && is_array($response['filters'])) {
          $filters_payload = $response['filters'];
        }
        if (isset($response['columns']) && is_array($response['columns'])) {
          $resolved_columns = array_map('intval', $response['columns']);
        }
      } else {
        $initial_html = $response;
      }
    }

    if (empty($resolved_columns)) {
      $desktop_default = function_exists('wc_get_default_products_per_row') ? wc_get_default_products_per_row() : 4;
      $resolved_columns = [
        'desktop' => max(1, (int) apply_filters('lime_filters_grid_columns', $desktop_default)),
        'tablet'  => max(1, (int) apply_filters('lime_filters_default_columns_tablet', 2, $category_slug)),
        'mobile'  => max(1, (int) apply_filters('lime_filters_default_columns_mobile', 1, $category_slug)),
      ];
    }

    $panel_id      = wp_unique_id('lf-panel-');
    $has_filters   = !empty($filters_payload);
    $per_page_attr = $requested_per_page > 0 ? $requested_per_page : '';
    $layout_class  = $layout === 'horizontal' ? 'lf-layout-horizontal' : 'lf-layout-sidebar';

    ob_start();
?>
    <div class="lime-filters <?php echo esc_attr($layout_class); ?>"
      data-layout="<?php echo esc_attr($layout); ?>"
      data-show-counts="<?php echo esc_attr($show_counts_flag); ?>"
      data-default="<?php echo esc_attr($default_state); ?>"
      data-category="<?php echo esc_attr($category_slug); ?>"
      data-pagination="<?php echo esc_attr($pagination_enabled ? 'yes' : 'no'); ?>"
      data-page="<?php echo esc_attr($current_page); ?>"
      data-per-page="<?php echo esc_attr($per_page_attr); ?>"
      data-columns-desktop="<?php echo esc_attr($resolved_columns['desktop']); ?>"
      data-columns-tablet="<?php echo esc_attr($resolved_columns['tablet']); ?>"
      data-columns-mobile="<?php echo esc_attr($resolved_columns['mobile']); ?>"
      data-filters-initial='<?php echo esc_attr(wp_json_encode($filters_payload)); ?>'>
      <?php if (!$has_filters): ?>
        <div class="lf-empty"><?php esc_html_e('No filters configured for this view. Configure in WooCommerce → Lime Filters.', 'lime-filters'); ?></div>
      <?php endif; ?>

      <div class="lf-chips" aria-live="polite" aria-label="<?php esc_attr_e('Active filters', 'lime-filters'); ?>"></div>

      <?php if ($layout === 'horizontal'): ?>
        <div class="lf-row">
          <?php self::render_sort_dropdown($current_orderby); ?>
          <div class="lf-filters lf-filters--horizontal" data-context="main">
            <?php self::render_filters($filters_payload, 'horizontal', $default_state, $show_counts_bool, 'main'); ?>
          </div>
        </div>
        <div class="lf-results"><?php echo $initial_html; ?></div>
      <?php else: ?>
        <?php if ($has_filters): ?>
          <div class="lf-mobile-bar" aria-hidden="false">
            <button type="button" class="lf-mobile-bar__button" data-role="toggle-mobile-filters" aria-expanded="false" aria-controls="<?php echo esc_attr($panel_id); ?>"><?php esc_html_e('Sort & Filter', 'lime-filters'); ?></button>
          </div>
        <?php endif; ?>
        <div class="lf-wrapper">
          <div class="lf-offcanvas" id="<?php echo esc_attr($panel_id); ?>" data-role="mobile-filters" aria-hidden="true" tabindex="-1">
            <div class="lf-offcanvas__inner">
              <div class="lf-offcanvas__header">
                <h2 class="lf-offcanvas__title"><?php esc_html_e('Filters', 'lime-filters'); ?></h2>
                <button type="button" class="lf-offcanvas__close" data-role="close-mobile-filters" aria-label="<?php esc_attr_e('Close filters', 'lime-filters'); ?>">&times;</button>
              </div>
              <aside class="lf-sidebar">
                <?php self::render_sort_dropdown($current_orderby); ?>
                <div class="lf-filters lf-filters--sidebar" data-context="main">
                  <?php self::render_filters($filters_payload, 'sidebar', $default_state, $show_counts_bool, 'main'); ?>
                </div>
                <?php if ($has_filters): ?>
                  <div class="lf-actions"><button class="lf-clear"><?php esc_html_e('Clear All', 'lime-filters'); ?></button></div>
                <?php endif; ?>
              </aside>
            </div>
          </div>
          <div class="lf-results"><?php echo $initial_html; ?></div>
        </div>
        <?php if ($has_filters): ?>
          <div class="lf-offcanvas-backdrop" data-role="close-mobile-filters" aria-hidden="true"></div>
        <?php endif; ?>
      <?php endif; ?>

      <div class="lf-pagination"><?php echo $pagination_html; ?></div>
    </div>
  <?php
    return ob_get_clean();
  }

  protected static function render_sort_dropdown($current = 'menu_order')
  {
  ?>
    <div class="lf-sort">
      <label for="lf-sortby" class="lf-sort-label"><?php esc_html_e('Sort by', 'lime-filters'); ?></label>
      <select id="lf-sortby" class="lf-sort-select">
        <option value="menu_order" <?php selected($current, 'menu_order'); ?>><?php esc_html_e('Default', 'lime-filters'); ?></option>
        <option value="popularity" <?php selected($current, 'popularity'); ?>><?php esc_html_e('Popularity', 'lime-filters'); ?></option>
        <option value="rating" <?php selected($current, 'rating'); ?>><?php esc_html_e('Average rating', 'lime-filters'); ?></option>
        <option value="date" <?php selected($current, 'date'); ?>><?php esc_html_e('Newest', 'lime-filters'); ?></option>
        <option value="price" <?php selected($current, 'price'); ?>><?php esc_html_e('Price: low to high', 'lime-filters'); ?></option>
        <option value="price-desc" <?php selected($current, 'price-desc'); ?>><?php esc_html_e('Price: high to low', 'lime-filters'); ?></option>
      </select>
    </div>
    <?php
  }

  protected static function render_filters(array $filters, $layout, $default_state, $show_counts, $context = 'main')
  {
    foreach ($filters as $filter) {
      $terms_html = self::render_filter_terms_html($filter, $show_counts);
      if ($terms_html === '') {
        continue;
      }
      $has_checked = false;
      if (!empty($filter['terms']) && is_array($filter['terms'])) {
        foreach ($filter['terms'] as $term) {
          if (!empty($term['checked'])) {
            $has_checked = true;
            break;
          }
        }
      }
      if ($layout === 'horizontal') {
        self::render_filter_dropdown_markup($filter, $terms_html, $default_state, $has_checked);
      } else {
        $force_open = ($context === 'modal');
        self::render_filter_accordion_markup($filter, $terms_html, $default_state, $force_open, $has_checked);
      }
    }
  }

  protected static function render_filter_terms_html(array $filter, $show_counts)
  {
    if (empty($filter['terms']) || !is_array($filter['terms'])) {
      return '';
    }
    ob_start();
    foreach ($filter['terms'] as $term) {
      $slug = isset($term['slug']) ? $term['slug'] : '';
      $name = isset($term['name']) ? $term['name'] : '';
      if ($slug === '' || $name === '') {
        continue;
      }
      $count   = isset($term['count']) ? (int) $term['count'] : 0;
      $checked = !empty($term['checked']);
    ?>
      <label class="lf-check" data-slug="<?php echo esc_attr($slug); ?>" data-count="<?php echo esc_attr($count); ?>">
        <input type="checkbox" class="lf-checkbox" value="<?php echo esc_attr($slug); ?>" <?php checked($checked, true); ?> />
        <span class="lf-term-label">
          <span class="lf-term-name"><?php echo esc_html($name); ?></span>
          <?php if ($show_counts): ?>
            <span class="lf-term-count"><?php echo esc_html(sprintf('(%d)', $count)); ?></span>
          <?php endif; ?>
        </span>
      </label>
    <?php
    }
    return ob_get_clean();
  }

  protected static function render_filter_dropdown_markup(array $filter, $terms_html, $default_state, $has_checked)
  {
    $taxonomy = isset($filter['taxonomy']) ? $filter['taxonomy'] : '';
    $label    = isset($filter['label']) ? $filter['label'] : '';
    $open     = ($default_state === 'expanded' || $has_checked) ? ' open' : '';
    ?>
    <details class="lf-group lf-dropdown" data-tax="<?php echo esc_attr($taxonomy); ?>" data-type="<?php echo esc_attr($filter['type'] ?? 'attribute'); ?>" <?php echo $open; ?>>
      <summary class="lf-toggle"><?php echo esc_html($label); ?></summary>
      <div class="lf-body">
        <?php echo $terms_html; ?>
      </div>
    </details>
  <?php
  }

  protected static function render_filter_accordion_markup(array $filter, $terms_html, $default_state, $force_open = false, $has_checked = false)
  {
    $taxonomy = isset($filter['taxonomy']) ? $filter['taxonomy'] : '';
    $label    = isset($filter['label']) ? $filter['label'] : '';
    $expanded = $force_open || $default_state === 'expanded' || $has_checked;
    $open_class = $expanded ? ' lf-open' : '';
    $aria = $expanded ? 'true' : 'false';
  ?>
    <div class="lf-group lf-accordion<?php echo $open_class; ?>" data-tax="<?php echo esc_attr($taxonomy); ?>" data-type="<?php echo esc_attr($filter['type'] ?? 'attribute'); ?>">
      <button class="lf-toggle" aria-expanded="<?php echo esc_attr($aria); ?>">
        <span><?php echo esc_html($label); ?></span>
        <svg class="lf-chevron" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
          <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </button>
      <div class="lf-body">
        <?php echo $terms_html; ?>
      </div>
    </div>
  <?php
  }

  protected static function sanitize_column_attr($value)
  {
    if (is_string($value)) {
      $value = trim(strtolower($value));
      if ($value === '' || $value === 'inherit') {
        return 0;
      }
    }

    $value = (int) $value;
    if ($value < 1) {
      return 0;
    }
    $value = min($value, 6);
    return $value;
  }

  public static function ensure_variation_price_html($variation_data, $product, $variation)
  {
    if (!empty($variation_data['price_html'])) {
      return $variation_data;
    }

    if (! $variation instanceof WC_Product_Variation) {
      return $variation_data;
    }

    $sale_price     = $variation->get_sale_price();
    $regular_price  = $variation->get_regular_price();
    $active_price   = $variation->get_price();

    if ($active_price === '' || $active_price === null) {
      return $variation_data;
    }

    $display_price        = wc_get_price_to_display($variation);
    $display_regular_price = $regular_price !== ''
      ? wc_get_price_to_display($variation, ['price' => $regular_price])
      : $display_price;

    if ($display_price === '' || $display_price === null) {
      return $variation_data;
    }

    if ($sale_price !== '' && $sale_price !== null && $display_price < $display_regular_price) {
      $price_html = wc_format_sale_price(wc_price($display_regular_price), wc_price($display_price));
    } else {
      $price_html = wc_price($display_price);
    }

    if ($price_html !== '') {
      $variation_data['price_html'] = sprintf('<span class="price">%s</span>', $price_html);
    }

    return $variation_data;
  }

  public static function track_product_view()
  {
    if (!function_exists('is_product') || !is_product()) {
      return;
    }

    $product_id = get_the_ID();
    if (!$product_id) {
      return;
    }

    $cookie_name = 'lf_recently_viewed';
    $raw = isset($_COOKIE[$cookie_name]) ? wp_unslash($_COOKIE[$cookie_name]) : '';
    $ids = $raw !== '' ? array_filter(array_map('absint', explode('|', $raw))) : [];
    $ids = array_values(array_diff($ids, [$product_id]));
    array_unshift($ids, $product_id);
    $ids = array_slice($ids, 0, 20);
    $value = implode('|', $ids);

    $expire = time() + DAY_IN_SECONDS * 30;
    $path   = defined('COOKIEPATH') ? COOKIEPATH : '/';
    $domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
    setcookie($cookie_name, $value, $expire, $path, $domain, is_ssl(), true);
    $_COOKIE[$cookie_name] = $value;

    if (function_exists('wc_track_product_view')) {
      wc_track_product_view();
    } elseif (function_exists('woocommerce_track_product_view')) {
      woocommerce_track_product_view();
    }
  }

  protected static function query_filters_from_request()
  {
    if (!isset($_GET['lf_filters'])) {
      return [];
    }

    $raw = wp_unslash($_GET['lf_filters']);
    if (!is_string($raw) || $raw === '') {
      return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
      return [];
    }

    $filters = [];
    foreach ($decoded as $taxonomy => $values) {
      $taxonomy = sanitize_key($taxonomy);
      if ($taxonomy === '') {
        continue;
      }

      if (is_string($values)) {
        $values = array_map('trim', explode(',', $values));
      }

      if (!is_array($values)) {
        continue;
      }

      $slugs = [];
      foreach ($values as $value) {
        if (!is_string($value) || $value === '') {
          continue;
        }
        $slug = sanitize_title($value);
        if ($slug !== '') {
          $slugs[] = $slug;
        }
      }

      if (!empty($slugs)) {
        $filters[$taxonomy] = array_values(array_unique($slugs));
      }
    }

    return $filters;
  }

  protected static function query_orderby_from_request(array $allowed)
  {
    $value = '';
    if (isset($_GET['lf_orderby'])) {
      $value = sanitize_text_field(wp_unslash($_GET['lf_orderby']));
    } elseif (isset($_GET['orderby'])) {
      $value = sanitize_text_field(wp_unslash($_GET['orderby']));
    }

    if ($value === '') {
      return 'menu_order';
    }

    if (!in_array($value, $allowed, true)) {
      return 'menu_order';
    }

    return $value;
  }

  protected static function query_page_from_request()
  {
    if (isset($_GET['lf_page'])) {
      $page = (int) wp_unslash($_GET['lf_page']);
      if ($page > 0) {
        return $page;
      }
    }

    $paged = get_query_var('paged');
    if ($paged) {
      $paged = (int) $paged;
      if ($paged > 0) {
        return $paged;
      }
    }

    return 1;
  }

}
