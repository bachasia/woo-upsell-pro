# Woo Upsell Pro Codebase Summary

_Last updated: 2026-03-16_

## Snapshot

This summary is generated from direct code inspection and the repository compaction file at:
- `/Users/bachasia/Data/VibeCoding/woo-upsell-pro/repomix-output.xml`

The plugin is a WooCommerce extension with feature parity goals against `salesgen-upsell`. Current code shows Phases 00–09 implemented, including Phase 07 (email coupon + FOMO stock), Phase 08 (tabbed settings schema/page), and Phase 09 (QA/security hardening).

## Repository Structure

- `woo-upsell-pro.php`: plugin header, constants, bootstrap loader.
- `includes/`: core bootstrap, helpers, and feature classes.
- `admin/`: WP admin menu, settings page, settings schema, product fields.
- `public/`: public hooks + frontend assets.
- `templates/`: feature templates (bundle, popup, side cart, bmsm, announcements, sales popup, email).
- `plans/`: implementation plans and phase reports.

## Runtime Bootstrap Flow

1. `WUP_Loader` runs on `plugins_loaded` (priority 99).
2. WooCommerce dependency guard checks `function_exists( 'WC' )`.
3. HPOS compatibility is declared on `before_woocommerce_init` via `FeaturesUtil::declare_compatibility( 'custom_order_tables', WUP_FILE, true )`.
4. Core files are loaded, then `WUP_Plugin::init()` boots feature classes.

## Core Modules

### Core and Helpers
- `WUP_Loader`: dependency guard + loader + HPOS declaration.
- `WUP_Plugin`: feature registration and boot order.
- `WUP_Assets`: public/admin assets + dynamic CSS engine from settings schema `css` mappings.
- `WUP_Product_Source`: cached product source resolver + transient invalidation hooks.
- `wup_*` cache helpers in `includes/helpers/class-wup-cache.php`.

### Feature Classes (includes/features)
- `WUP_Bundle`
- `WUP_Popup`
- `WUP_Side_Cart`
- `WUP_Cart_Upsell`
- `WUP_Thankyou_Upsell`
- `WUP_Related`
- `WUP_BuyMoreSaveMore`
- `WUP_Announcement`
- `WUP_Sales_Popup`
- `WUP_Email_Coupon`
- `WUP_Fomo_Stock`

## Phase 07–09 Implementation Highlights

### Phase 07
- `WUP_Email_Coupon` hooks `woocommerce_thankyou` and generates/sends a coupon email once per order.
- Deduplication uses order meta `_wup_coupon_sent` and stores `_wup_coupon_code` on success.
- Email template resolved from `templates/email/coupon.php` with token replacement.
- `WUP_Fomo_Stock` renders stock urgency on product pages when stock is within configured min/max.

### Phase 08
- `WUP_Settings_Page` now renders native PHP tabbed settings UI (`9` tabs).
- Settings are registered via WP Settings API with sanitize callbacks per field type.
- Dynamic CSS schema is pushed into `WUP_Assets::register_schema()` from field definitions carrying `css`.
- Advanced tab includes AJAX cache flush action (`wup_flush_cache`) with nonce and capability checks.
- `WUP_Settings_Schema` currently defines 100+ field entries across bundle, popup, side cart, BMSM, cart, coupons, announcements, sales popup, and advanced tabs.

### Phase 09
- HPOS compatibility declaration added in bootstrap loader.
- Settings registration includes sanitize callbacks.
- Cache flush action includes capability enforcement (`manage_options`).
- Product-source transient clear AJAX includes capability enforcement (`manage_woocommerce`).
- BMSM enqueue is scoped to product and cart storefront contexts.

## Public AJAX Endpoints (verified)

- `wup_get_popup`
- `wup_add_bundle`
- `wup_quickview`
- `wup_get_side_cart`
- `wup_sc_update_qty`
- `wup_sc_remove_item`
- `wup_sc_apply_coupon`
- `wup_sc_remove_coupon`
- `wup_sc_add_item`
- `wup_cart_upsell_add`
- `wup_clear_transients` (admin capability check present)

## Shortcodes (verified)

- `[wup_bmsm]`
- `[wup_cart_upsell]`
- `[wup_related]`

## Current Documentation Gaps Detected

- There was no `docs/` directory in this repository state before this update.
- No maintained architecture/standards/roadmap/changelog docs existed in `docs/`.
- Some option key naming appears inconsistent between schema and runtime usage in sales popup paths; implementation may vary and should be normalized in a dedicated cleanup pass.

## Maintenance Notes

- Keep docs synchronized with option-key/schema changes, especially sales popup settings.
- Keep feature toggle and hook docs aligned with `WUP_Plugin::init()` load order.
- Re-run codebase summary generation when any feature class, settings schema, or bootstrap file changes.
