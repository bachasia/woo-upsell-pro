# System Architecture

_Last updated: 2026-03-16_

## Overview

Woo Upsell Pro is a modular WordPress/WooCommerce plugin organized around:
- a guarded bootstrap layer,
- a feature bootstrap singleton,
- feature-specific classes in `includes/features/`,
- template-driven rendering,
- settings-driven behavior and dynamic styling.

## High-Level Component Diagram

```text
woo-upsell-pro.php
  -> WUP_Loader
      -> WC guard + HPOS declare
      -> require core/admin/public classes
      -> WUP_Plugin::init()
          -> feature class boot
          -> WUP_Admin (admin only)
          -> WUP_Public
          -> WUP_Assets
```

## Boot Sequence

1. Constants and base paths defined in `woo-upsell-pro.php`.
2. `WUP_Loader` initializes and hooks:
   - `plugins_loaded` -> plugin bootstrap routine
   - `before_woocommerce_init` -> HPOS compatibility declaration
3. If WooCommerce missing, loader shows admin notice and exits.
4. If WooCommerce available, loader requires classes and boots `WUP_Plugin`.
5. `WUP_Plugin::init()` loads feature modules in phase order and triggers `do_action( 'wup_loaded' )`.

## Architecture Layers

### 1) Bootstrap Layer
- `woo-upsell-pro.php`
- `includes/class-wup-loader.php`
- `includes/class-wup-plugin.php`

Responsibilities:
- dependency checks,
- compatibility declarations,
- deterministic class loading.

### 2) Feature Layer
- `includes/features/class-wup-*.php`

Responsibilities:
- register hooks,
- execute business logic,
- provide AJAX handlers/shortcodes/template context.

### 3) Admin Layer
- `admin/class-wup-admin.php`
- `admin/class-wup-settings-page.php`
- `admin/class-wup-settings-schema.php`

Responsibilities:
- settings UI/menu,
- settings registration/sanitization,
- tabbed schema-driven field rendering.

### 4) Presentation Layer
- `templates/**`

Responsibilities:
- all frontend/admin-adjacent HTML rendering from structured data prepared by feature classes.

### 5) Asset Layer
- `includes/helpers/class-wup-assets.php`
- `public/js/build/*`, `public/css/*`

Responsibilities:
- handle registration,
- conditional enqueue,
- dynamic CSS generation from schema mapping.

## Settings + Dynamic CSS Pipeline

1. `WUP_Settings_Schema::get_schema()` returns field metadata (`id`, `type`, `tab`, optional `css`).
2. `WUP_Settings_Page::register_settings()` registers each option with sanitize callback.
3. `WUP_Settings_Page::push_css_schema()` sends `css`-mapped fields into `WUP_Assets`.
4. `WUP_Assets::output_dynamic_css()` renders inline CSS rules and appends to `wup-public-styles`.

## Security Architecture

- AJAX endpoints use nonce checks (`check_ajax_referer`).
- Admin-only operations enforce capability checks:
  - `wup_flush_cache` -> `manage_options`
  - `wup_clear_transients` -> `manage_woocommerce`
- Settings values are sanitized by field type via `register_setting` callbacks and form processing.
- Templates use WP escaping APIs.

## Performance Architecture

- Product source lookups use transient caching (`wup_src_*` style keys via helper wrappers).
- Cache invalidation hooks clear source cache on relevant option changes.
- BMSM asset enqueue is scoped to product/cart pages.
- Fragment-based refresh is used for popup/side-cart cart count synchronization.

## Feature Topology (Phases 00–09)

- Foundation/Bootstrap
- Product source + variation resolver
- FBT bundle
- Add-to-cart popup + side cart
- Cart upsell + thank-you upsell + related products
- Buy More Save More (with internal coupon logic)
- Announcements + sales popup
- Email coupon + FOMO stock
- Full tabbed settings schema/page + dynamic CSS
- QA/security/compatibility hardening

## Known Architecture Notes

- Admin currently includes references to React build assets, but settings rendering is native PHP tabbed UI.
- Sales popup setting key usage differs in some places between schema/runtime naming; treat this area as needing a dedicated consistency review.
