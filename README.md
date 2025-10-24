# Lime Filters

WooCommerce enhancement plugin maintained by Lime Advertising.  
It provides AJAX-driven product filtering, a wishlist, affiliate tooling, media helpers, and a collection of Elementor widgets that power Kucht storefront experiences.

---

## Feature Highlights

- **AJAX product filters**  
  - Works via shortcode or Elementor widget; supports sidebar and horizontal layouts.  
  - Mobile “Sort & Filter” off‑canvas, filter chips, optional result pagination, custom per-page limits.  
  - Category ↔ attribute mapping controlled from WooCommerce → Lime Filters.  
  - Respects brand colour palette (CSS variables) and exposes hooks for query arguments, layout, and grid sizing.

- **Elementor widget suite**  
  - Filters block (`lime-filters`), category tabs, product attribute tables, pricing blocks, product info tabs, affiliates CTA with upsell modal, product title/share row, account dashboard & icon badges, recently viewed carousel, etc.

- **Affiliate store modal & analytics**  
  - Intercepts affiliate buttons, surfaces upsell accessories (with “Add to cart & continue” flow), supports skip/remember behaviour via `sessionStorage`, dispatches `lf:affiliate:click` and `lf:affiliate:upsellAdd` events.

- **Wishlist module** *(opt-in)*  
  - AJAX toggle with cookie support for guests and automatic merge for logged-in users.  
  - Elementor badge integration, toast notifications, `[lf_wishlist]` shortcode renderer.

- **Product media tooling**  
  - Global product background manager with admin UI, shortcodes (`[lf_product_image]`, `[lf_product_gallery]`), Swiper slider assets, and WooCommerce thumbnail overrides.  
  - Recently viewed tracking via `lf_recently_viewed` cookie and helper APIs.

- **Related products shortcode**  
  - `[lf_related_products]` outputs styled cards reusing the shared product tile renderer.

- **Helpers & quality-of-life additions**  
  - Custom placeholder image, upsell data helpers, price summary helpers, analytics hooks, and an extendable affiliate store registry (`lime_filters_affiliate_stores` filter).

---

## Requirements

| Component        | Minimum |
|------------------|---------|
| WordPress        | 5.8     |
| WooCommerce      | 7.0 (or the version that ships with WP 6.6) |
| PHP              | 7.4     |
| Elementor (optional) | 3.5+ if you want the widgets |

The plugin aborts early if WooCommerce is inactive.

---

## Installation

1. Copy the `lime-filters` directory into `wp-content/plugins/`.
2. Activate **Lime Filters** from **Plugins → Installed Plugins**.  
   Activation seeds default brand colours and a starter category → attribute map.
3. Keep WooCommerce active; optionally activate Elementor for the widget suite.

---

## Configuration Overview

### 1. Attribute Mapping & Branding

Navigate to **WooCommerce → Lime Filters**:

- **Category Mapping** tab: map shop and category slugs to WooCommerce attribute taxonomies (e.g. `pa_size`). The UI supports autocomplete and comma-separated entries.  
- **Brand Colours** tab: set accent/border/background/text colours. These values cascade into frontend CSS variables (`--lf-accent`, `--lf-border`, `--lf-bg`, `--lf-text`) and can be overridden per-theme if needed.
- **Shop settings**: toggle whether top-level categories display on the shop view.

### 2. Wishlist (optional)

Visit **WooCommerce → Wishlist**:

- Enable the wishlist toggle, assign a page that contains `[lf_wishlist]`, and publish any Elementor header elements that reference the wishlist icon.
- The wishlist works for guests (cookie `lf_wishlist`) and syncs to user meta on login. AJAX endpoint: `lf_toggle_wishlist`.

### 3. Product Background Manager

Visit **WooCommerce → Product Background**:

- Upload/select a universal background image. Transparent PNG product photos are rendered atop this image across the shop, product gallery, and shortcode output.
- Two shortcodes are available: `[lf_product_image]` for a single attachment and `[lf_product_gallery]` for a slider.
- Swiper assets are registered only when needed.

### 4. Related Products Shortcode

Use `[lf_related_products]` inside templates or Elementor HTML widgets. Attributes include:
- `product` (ID override), `limit`, `columns`, `columns_tablet`, `columns_mobile`, `class`, `orderby`, `order`.

### 5. Affiliate Stores

Affiliate URLs are pulled from post meta/ACF (keys align with store slugs) and rendered by the Elementor Product Affiliates widget. Upsells are delivered via `LF_Helpers::upsell_products_for` which you can filter (`lf_upsell_products_for_affiliate_prompt`).

---

## Shortcodes

| Shortcode | Purpose | Key Attributes |
|-----------|---------|----------------|
| `[lime_filters]` | Render the AJAX filter interface + product grid. | `layout` (`sidebar`/`horizontal`), `show_counts`, `default`, `category`, `pagination`, `per_page`, optional `columns_desktop/tablet/mobile`. |
| `[lf_wishlist]` | Output wishlist grid (requires wishlist enabled). | None. |
| `[lf_product_image]` | Render a product/attachment image with background treatment. | `product`, `attachment`, `size`, `class`. |
| `[lf_product_gallery]` | Render a product gallery slider with background treatment. | `product`, `class`, `show_nav`, `thumbnails`, etc. See `class-lf-product-background.php` for defaults. |
| `[lf_related_products]` | Render related products grid using shared card template. | `product`, `limit`, `columns`, `columns_tablet`, `columns_mobile`, `class`, `orderby`, `order`. |

All shortcodes enqueue shared CSS/JS automatically.

---

## Elementor Widgets

The plugin auto-registers widgets when Elementor is loaded:

- **Lime Filters** – wraps the `[lime_filters]` shortcode with responsive column controls.
- **Category Tabs** – tabbed product collections with query controls.
- **Product Attributes** – prettified attribute/spec tables.
- **Product Info Tabs** – multi-tab product details block.
- **Product Pricing** – “Suggested vs Starting at” pricing columns.
- **Product Affiliates** – affiliate CTA grid/table with upsell modal and analytics events.
- **Product Title & Share** – product title, badges, and share buttons row.
- **Recently Viewed** – carousel/grid fed by the `lf_recently_viewed` cookie.
- **Account Dashboard** – customer overview including order stats & reward points hook (`lf_account_dashboard_reward_points`).
- **Account Icon** – Elementor icon widget decorator with wishlist/cart badges.

Each widget lives under `includes/elementor/<feature>/` with matching CSS/JS.

---

## AJAX Filters: Internal Flow

1. Frontend (`includes/assets/js/lime-filters.js`) renders filter accordions or dropdowns based on layout.  
2. User interactions build a payload and call `wp-admin/admin-ajax.php?action=lime_filter_products`.  
3. `LF_AJAX::handle()` sanitises params, resolves taxonomy filters, builds the WooCommerce product query, and responds with:
   - `html` (product grid/list, each card via `LF_AJAX::render_product_card()`),  
   - `filters` (updated filter state),  
   - `pagination` markup, page metadata, and resolved column counts (desktop/tablet/mobile).  
4. The script updates results, chips, pagination, URL state (`lf_filters` + `lf_page` query vars), and mobile drawers without page reload.

### Developer Hooks

Key filters to extend behaviour:

| Hook | Description |
|------|-------------|
| `lime_filters_max_per_page` | Override allowed per-page cap (default 48). |
| `lime_filters_products_per_page` | Adjust computed per-page totals. |
| `lime_filters_grid_columns` / `lime_filters_default_columns_*` | Override grid columns per breakpoint. |
| `lime_filters_query_args` | Alter WP_Query arguments before execution. |
| `lime_filters_pagination_html` | Replace/augment pagination markup. |
| `lime_filters_affiliate_stores` | Inject/update affiliate store definitions (label, logo). |
| `lf_upsell_products_for_affiliate_prompt` | Filter upsell payload before it reaches the modal. |
| `lf_product_pricing_category_options`, `lf_product_affiliates_category_options` | Filter Elementor control lists. |

### Frontend Events & Storage

- `lf:wishlist:update` – jQuery event emitted after wishlist state changes.  
- `lf:affiliate:click` / `lf:affiliate:upsellAdd` – `CustomEvent`s fired on `document` for analytics.  
- Cookies: `lf_recently_viewed`, `lf_wishlist` (guest fallback).  
- `sessionStorage`: `lfAffiliateModalDismissed` memorises dismissed upsells per product.

---

## Wishlist UX Notes

- Toggle buttons carry `.lf-wishlist-toggle` with `data-product-id`.  
- AJAX handler returns updated counts and toast payload (message + optional wishlist URL).  
- Badges inside Elementor icon wrappers auto-update; accessible labels switch between “Add” / “Remove”.  
- Wishlist page output reuses shared product cards to stay visually aligned with filter grids.

---

## Affiliate Upsell Modal

Implemented in `includes/elementor/product-affiliates/product-affiliates-modal.js`:

- Parses `<script type="application/json" data-upsell-json>` embedded by the widget to build cards.  
- Offers direct “Add to cart & continue” for simple, in-stock upsells (with WooCommerce AJAX add-to-cart fallback to jQuery).  
- For variable/out-of-stock items, presents “Choose options” buttons that open the product page in a new tab.  
- “Skip & go to store” stores the product ID in `sessionStorage` to suppress the modal for the session.  
- Clicking outside, pressing ESC, or activating the close button exits the modal and restores focus.  
- Focus trap and aria attributes ensure keyboard accessibility.

---

## Project Structure (Partial)

```
lime-filters.php                  Plugin bootstrap & activation hooks
includes/
  assets/
    css/, js/, images/            Shared frontend/admin assets
  class-lf-admin.php              WooCommerce submenu & settings
  class-lf-frontend.php           Shortcode rendering, assets, cookies
  class-lf-ajax.php               AJAX controller & product card renderer
  class-lf-elementor-widget.php   Elementor widget registrar
  class-lf-wishlist.php           Wishlist module
  elementor/                      Individual widget implementations
  product-background/             Background manager & shortcodes
  related-products/               Related products shortcode
  helpers.php                     Shared utility methods & filters
docs/                             Internal planning notes
```

Assets are plain ES5/ES6 scripts and CSS—no build step is required. When updating CSS, remember the colour variables injected via `LF_Frontend::assets()`.

---

## Development Tips

- **Localisation**: strings use the `lime-filters` text domain. Run `wp i18n make-pot` if translation updates are needed.  
- **Styling**: base colours come from options; extend via theme overrides or enqueue additional CSS. Sticky offsets use `--lf-sticky-offset` and `--lf-sticky-row-height`.  
- **Testing**: verify both sidebar and horizontal layouts, mobile off-canvas behaviour, wishlist toggles (guest vs logged in), and affiliate modal interactions.  
- **Recently viewed**: `LF_Helpers::recently_viewed_products()` exposes objects for custom templates.  
- **Placeholder images**: `LF_Helpers::placeholder_image_url()` overrides WooCommerce’s placeholder; update the image under `includes/assets/images/`.

---

## License

Released under the GPLv2 (or later). See the plugin header and `readme.txt` for canonical licensing details.

