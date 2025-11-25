# Lime Filters – Working Notes

## Current Features
- WooCommerce filter sidebar works across desktop, tablet, and mobile.
- Mobile view uses an off-canvas drawer triggered by “Sort & Filter.”
- Active filter “chips” show selected options and support quick removal.
- Responsive product columns configurable (desktop/tablet/mobile) via shortcode/Elementor controls.
- Shared product background layer for transparent images with admin-managed asset.
- Gallery shortcode now outputs Swiper-powered slider with thumbnails on mobile/desktop.
- Elementor widget exposes pagination, per‑page, and responsive column settings.

## Recent Changes
- Replaced earlier modal with off-canvas drawer for mobile filters.
- Added dynamic chips bar with new styling.
- Introduced `lf-mobile-bar` trigger and JS handling for off-canvas toggling.
- AJAX responses now include column metadata; front-end grid uses CSS custom props.
- Added Product Background module (separate admin page + front-end wrapper CSS).
- Admin mapping allows dedicated “Shop (All Products)” configuration and optional category filter.
- Attribute Variants panel now lets admins pick participating attributes and build multi-attribute combinations (e.g., Color × Fuel Type) instead of configuring values one attribute at a time.
- Merged the affiliate archive plugin into Lime Filters with its own WooCommerce submenu (enable toggle, customizable slug/per-page) and rewrite/pagination handling identical to Product Compare’s settings workflow.
- Affiliate archives now include a category dropdown filter so shoppers can narrow each retailer view down to a specific product_cat without leaving the archive.
- Variant combos gained a dedicated UPC field (stored alongside SKU) so merchandisers can manage per-attribute UPC overrides for feeds/affiliate tagging.
- Added an “Affiliate Vendors” WooCommerce submenu to manage retailer slugs, labels, meta keys, and logos; helpers/archives now read from this option instead of hardcoded arrays (still filterable for extensions).
- Added a global toggle (WooCommerce → Lime Filters) to enable/disable the affiliate upsell modal, defaulting to off until we revisit the flow.

## Next Ideas / Follow-ups
- Hook up additional behavior for the mobile “Sort & Filter” button (if needed).
- Decide on analytics or logging for filter interactions.
- Consider extending chips to show counts or category tags.
- Review accessibility (focus trapping & aria attributes) once the off-canvas behavior is finalized.
