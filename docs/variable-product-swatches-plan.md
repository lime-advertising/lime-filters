# Variable Product Swatches & Gallery Sync Plan

## 1. Current Flow Assessment
- Review `LF_Product_Background::wrap_image_html()` usage in single-product templates and ensure Swiper gallery markup is centrally managed (CSS/JS assets already registered in `product-background` module).
- Confirm WooCommerce variation script usage (`add-to-cart-variation.js`) and identify hooks (`woocommerce_available_variation`, `found_variation`, `show_variation`, `reset_data`) we can extend without breaking native behaviour.
- Inventory existing template overrides (theme or plugin) to understand where swatch UI and gallery updates should be injected (likely `woocommerce_single_product_summary` and gallery filters).

## 2. Data Layer & Swatch UI
- Extend variation payload via `woocommerce_available_variation`:
  - Include swatch metadata (color hex, image thumbnail, label) pulled from attribute term meta (fallback to text).
  - Provide array of gallery image URLs/IDs per variation (use featured image first, then fallback to product gallery).
  - Flag variations without dedicated imagery to handle fallback gracefully.
- Render swatch UI:
  - Replace/augment attribute dropdowns with accessible swatch buttons (respect WooCommerce form structure so selections sync to hidden selects).
  - Apply `lf-bg-wrap` where applicable to keep background styling consistent.
  - Add active/disabled states mirroring WooCommerce stock status.

## 3. Gallery Synchronisation Logic
- JS enhancements (new module e.g. `variable-swatches.js`):
  - Listen for swatch clicks and update the associated attribute select → trigger WooCommerce variation change.
  - On `found_variation` event, swap the gallery’s main slide (and optional thumbs) using preloaded variation images, maintaining `lf-bg-wrap` wrappers.
  - Update price/sku blocks only when WooCommerce signals changes (avoid duplicating native behaviour).
  - Gracefully handle variations lacking custom images by reverting to parent product gallery.
- Swiper integration:
  - Rebuild slides or update the active slide when variation images change.
  - Call `swiper.update()` / `swiper.slideTo(0)` to ensure layout & navigation remain correct.

## 4. Styling & Accessibility
- Create swatch styles within `includes/assets/css/lime-filters.css` (or a new scoped stylesheet) supporting color chips, image thumbnails, and text fallbacks.
- Ensure swatches are keyboard navigable (buttons with `aria-pressed`, focus outlines) and announce variation changes (ARIA live region or rely on WooCommerce messages).
- Maintain responsive layout so swatches don’t break mobile product summary sections.

## 5. Testing & Rollout
- Test matrix:
  - Variable products with per-variation images, shared images, and missing images.
  - Products with multiple attributes (color + size) to ensure interdependent swatches behave.
  - Out-of-stock variations to verify swatch disabling works.
  - Interaction across themes/templates, Elementor single-product layouts, and cached front ends.
- Performance:
  - Lazy load heavy variation galleries where possible; ensure variation data payload stays small (prefer IDs + URLs instead of base64/image blobs).
- Gradual rollout:
  - Feature flag or filter to disable if conflicts arise.
  - Documentation for admins describing swatch term meta requirements and fallback behaviour.
