# Phase 00 — Foundation

**Status:** Todo | **Priority:** P0 | **Effort:** S

## Overview

Bootstrap the plugin skeleton: entry file, autoloader, WC dependency guard, activation/deactivation hooks, asset registration, settings page shell, dynamic CSS output.

## Requirements

- Plugin activates/deactivates without fatal errors
- Admin notice if WooCommerce not active
- Empty settings page visible under WP Admin menu
- All constants defined
- JS/CSS assets registered (not yet enqueued with content)

## Files to Create/Modify

**Create:**
- `woo-upsell-pro.php` — plugin header, constants, require loader
- `uninstall.php` — cleanup options on uninstall
- `includes/class-wup-loader.php` — WC guard, activation/deactivation, load_plugin()
- `includes/class-wup-plugin.php` — core singleton, hook registration
- `includes/class-wup-activator.php` — activation logic
- `includes/helpers/class-wup-utils.php` — global helper functions
- `includes/helpers/class-wup-cache.php` — transient helpers
- `includes/helpers/class-wup-assets.php` — enqueue + dynamic CSS engine
- `admin/class-wup-admin.php` — admin menu + settings page shell
- `admin/class-wup-settings-page.php` — settings schema skeleton
- `public/class-wup-public.php` — frontend hook registration
- `webpack.config.js`, `package.json` — build tooling
- `composer.json` — PHP autoload config

**Modify:** none (fresh start)

## Constants (woo-upsell-pro.php)

```php
define('WUP_FILE', __FILE__);
define('WUP_VERSION', '1.0.0');
define('WUP_DIR', plugin_dir_path(__FILE__));
define('WUP_URL', plugin_dir_url(__FILE__));
define('WUP_SLUG', 'woo-upsell-pro/woo-upsell-pro.php');
define('WUP_TEXT_DOMAIN', 'woo-upsell-pro');
define('WUP_COUPON_BMSM', 'wupbmsm');
define('WUP_COUPON_BUNDLE', 'wupbundle');
```

## Dynamic CSS Engine (class-wup-assets.php)

The original plugin drives frontend styles via settings with a `css` key in the schema:
```php
// Schema entry example:
array(
  'id'      => 'wup_upsell_sidecart_primary_color',
  'default' => '#2c7a7b',
  'css'     => ':root|--sg-side-cart-color-primary',   // selector|property
)
// Array form (multiple rules):
'css' => array(
  'body .salesgen-add-bundle.button|background-color|1',
  'body .button.salesgen-select-options|border-color|1',
)
```

`class-wup-assets.php` must:
1. Iterate all settings fields with a `css` key
2. Build inline CSS string from current option values
3. Output via `wp_add_inline_style()` on `wp_enqueue_scripts`

## Implementation Steps

1. Create `woo-upsell-pro.php` with plugin header + constants + `require_once` loader
2. Create `class-wup-loader.php` with WC guard → `load_plugin()` on `plugins_loaded` priority 99
3. Create `class-wup-plugin.php` singleton with `init()` method
4. Create `class-wup-activator.php` for activation timestamp + modules option
5. Create `class-wup-utils.php` with helper functions:
   - `wup_get_product_category_ids( $product_id )`
   - `wup_get_product_category_slugs( $product_id )`
   - `wup_get_product_tag_slugs( $product_id )`
   - `wup_count_form_inputs( $html )`
6. Create `class-wup-cache.php`:
   - `wup_set_transient( $key, $data, $expiry )`
   - `wup_get_transient( $key )`
   - `wup_delete_transients_by_prefix( $prefix )`
7. Create `class-wup-assets.php` with dynamic CSS engine
8. Create `admin/class-wup-admin.php`: admin menu under WP Admin (not WooCommerce menu)
9. Create `admin/class-wup-settings-page.php`: skeleton with tabs (to be filled in Phase 08)
10. Create `public/class-wup-public.php`: skeleton with `wp_enqueue_scripts` hook
11. Setup webpack config for SCSS + JS bundles
12. Create `uninstall.php`: delete all `wup_*` options on uninstall

## Todo

- [x] `woo-upsell-pro.php`
- [x] `uninstall.php`
- [x] `includes/class-wup-loader.php`
- [x] `includes/class-wup-plugin.php`
- [x] `includes/class-wup-activator.php`
- [x] `includes/helpers/class-wup-utils.php`
- [x] `includes/helpers/class-wup-cache.php`
- [x] `includes/helpers/class-wup-assets.php` (dynamic CSS engine)
- [x] `admin/class-wup-admin.php`
- [x] `admin/class-wup-settings-page.php` (schema skeleton)
- [x] `public/class-wup-public.php`
- [x] `webpack.config.js` + `package.json`
- [ ] Plugin activates cleanly, admin page visible (needs live WP environment)
