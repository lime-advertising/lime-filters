# Product Background Feature – Specification Draft

## Goal
Allow WooCommerce product and gallery images (assumed to be transparent PNGs) to be rendered on a common background image managed by the Lime Filters plugin.

## Requirements
1. **Background asset management**
   - Default background image (current path):  
     `includes/assets/images/Kucht Products BG.png`
   - Provide an admin UI (similar to “Lime Filters” submenu) to upload/replace the background image:
     - New settings page or tab under WooCommerce → Lime Filters (e.g. “Product Background”).
     - Store selected image in an option for easy retrieval (e.g. `lime_filters_product_bg_id` for attachment ID).
   - Provide preview + “Reset to default” control.

2. **Front-end integration**
   - Override WooCommerce single product gallery and archive thumbnails:
     - Wrap transparent product image with a generated background (CSS layering or GD/Imagick preprocessing).
     - Ensure responsiveness (background should scale for various thumbnail sizes).
   - Compatibility with lazy-loading and existing WooCommerce image hooks.

3. **Performance considerations**
   - Avoid regenerating images on every request.
   - Potential approaches:
     - Use CSS background layering (no image processing required).
     - Generate composited images on upload and store in custom size(s).
   - Cache per-product results if doing server-side compositing.

4. **Fallbacks & edge cases**
   - If no background image is configured, show the transparent images as-is.
   - Respect retina (2x) and different size requests by using background-size or multiple generated assets.
   - Ensure gallery lightbox still shows composited result (or provide transparent version toggle).

5. **Developer hooks**
   - Filters for overriding background image URL/ID.
   - Action to inject additional CSS if themes need custom adjustments.

6. **Documentation tasks**
   - Update `docs/lime-filters-notes.md` once feature is implemented.
   - Produce user-facing instructions for the new admin settings page.
   - Document shortcodes for embedding wrapped images.

## Shortcode Reference (Implemented)
- `[lf_product_image product="123" size="woocommerce_single" class=""]`
  - `product` (optional) falls back to current global product.
  - `attachment` (optional) to force a specific attachment ID.
  - `size` defaults to `woocommerce_single`.
  - Additional CSS classes via `class`.

- `[lf_product_gallery product="123" size="woocommerce_single" limit="" columns="4" columns_tablet="3" columns_mobile="2"]`
  - Renders a Swiper-powered slider (main image + thumbnail rail) with the shared background applied.
  - `limit` optional integer to cap number of gallery images.
  - `columns` attributes control thumbnail counts per breakpoint (desktop/tablet/mobile).
  - Automatically enqueues Swiper from CDN if a compatible handle isn’t already registered by the theme.

## Implementation Outline (Future)
1. Create new settings tab/page under WooCommerce → Lime Filters for background image upload.
2. Register option to store attachment ID of background image.
3. Extend front-end hooks:
   - Archives: `woocommerce_get_product_thumbnail`, custom wrapper.
   - Single product gallery: filter WooCommerce image HTML or template overrides.
4. Add frontend CSS to layer product image atop background.
5. Provide helper functions:
   - `LF_Product_Background::get_image_url()` with fallbacks.
   - `LF_Product_Background::render_thumbnail( $html, $product )`.
6. Test across device breakpoints, high-DPI displays, and lazy-loading scenarios.
