---
phase: 1
title: "Project Setup & Bootstrap"
status: pending
effort: 3h
depends_on: []
blocks: [2, 3, 4, 5, 6, 7, 8]
---

# Phase 01: Project Setup & Bootstrap

## Context Links
- [Tech Stack](../../docs/tech-stack.md)
- [Plan Overview](plan.md)

## Overview
**Priority:** P1 (blocks everything)
Bootstrap plugin skeleton: main file, autoloader, loader pattern, activator, CPT registration, build tooling.

## Requirements
- Main plugin file with proper WP/WC header
- PSR-4 autoloading via Composer
- @wordpress/scripts build pipeline
- Loader class for centralized hook registration
- Activator for activation tasks
- Uninstall cleanup
- WC HPOS compatibility declaration

## Architecture
```
woo-upsell-pro.php → requires composer autoload → instantiates Plugin class
Plugin class → creates Loader → registers all hooks → runs
Loader stores actions/filters arrays → fires them on run()
```

## Related Code Files
**Create:**
- `woo-upsell-pro.php` -- main entry
- `composer.json` -- PSR-4 autoload config
- `package.json` -- @wordpress/scripts, build scripts
- `includes/class-wup-loader.php` -- hook registration
- `includes/class-wup-activator.php` -- activation logic
- `includes/helpers/class-wup-utils.php` -- shared utilities
- `includes/campaigns/class-wup-campaign-cpt.php` -- CPT registration
- `public/class-wup-public.php` -- frontend hook coordinator
- `admin/class-wup-admin.php` -- admin hook coordinator (stub)
- `uninstall.php` -- cleanup on uninstall
- `.editorconfig`

## Implementation Steps

### 1. Create `woo-upsell-pro.php`
```php
/**
 * Plugin Name: Woo Upsell Pro
 * Plugin URI: https://example.com/woo-upsell-pro
 * Description: Smart upsell campaigns for WooCommerce
 * Version: 1.0.0
 * Requires PHP: 8.0
 * Requires at least: 6.4
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 * Text Domain: woo-upsell-pro
 * Domain Path: /languages
 * License: GPL-2.0+
 */
```
- Define `WUP_VERSION`, `WUP_PLUGIN_DIR`, `WUP_PLUGIN_URL`, `WUP_PLUGIN_FILE`
- Check WooCommerce active before loading
- Require Composer autoload
- Instantiate main plugin class, call `run()`
- Declare HPOS compatibility via `before_woocommerce_init` hook:
  ```php
  \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__);
  ```

### 2. Create `composer.json`
```json
{
  "name": "woo-upsell-pro/woo-upsell-pro",
  "autoload": {
    "psr-4": {
      "WooUpsellPro\\": "includes/"
    }
  }
}
```
- Run `composer dump-autoload`

### 3. Create `package.json`
- Dependencies: `@wordpress/scripts`, `@wordpress/components`, `@wordpress/element`, `@wordpress/api-fetch`
- Scripts: `build`, `start` (dev watch), `lint:js`, `lint:css`
- Configure webpack entry points:
  - `admin/src/index.js` -> `admin/build/index.js`
  - `public/js/src/popup.js` -> `public/js/build/popup.js`
  - `public/js/src/cart-upsell.js` -> `public/js/build/cart-upsell.js`
  - `public/js/src/tier-table.js` -> `public/js/build/tier-table.js`
- SCSS compilation for `public/css/src/*.scss` -> `public/css/build/`

### 4. Create `class-wup-loader.php`
- Namespace: `WooUpsellPro`
- Properties: `protected array $actions = []`, `protected array $filters = []`
- Methods: `add_action()`, `add_filter()`, `run()` (loops and registers all hooks)
- Typed params: `string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1`

### 5. Create `class-wup-activator.php`
- Namespace: `WooUpsellPro`
- `activate()`: flush rewrite rules (for CPT), set default options
- Default options: `wup_settings` array with feature toggles (all enabled by default)
- Register activation hook in main file

### 6. Create `class-wup-campaign-cpt.php`
- Namespace: `WooUpsellPro\Campaigns`
- Register CPT `wup_campaign` with: `public => false`, `show_ui => false`, `show_in_rest => false` (we use custom REST)
- Labels, capabilities (`manage_woocommerce`), supports: `title`
- Meta keys: `_wup_campaign_type`, `_wup_campaign_rules`, `_wup_campaign_products`, `_wup_campaign_status`, `_wup_campaign_discount_tiers`
- Register meta with `register_post_meta()` for each key

### 7. Create `class-wup-utils.php`
- Namespace: `WooUpsellPro\Helpers`
- Static methods: `get_setting(string $key, mixed $default)`, `is_feature_enabled(string $feature)`, `get_plugin_url()`, `get_plugin_dir()`
- Reads from `wup_settings` option

### 8. Create `class-wup-public.php` and `class-wup-admin.php` (stubs)
- `WooUpsellPro\WUP_Public`: enqueue frontend scripts/styles conditionally
- `WooUpsellPro\WUP_Admin`: enqueue admin scripts, register admin pages (filled in Phase 07)

### 9. Create `uninstall.php`
- Check `WP_UNINSTALL_PLUGIN` defined
- Delete all `wup_campaign` posts
- Delete `wup_settings` option
- Clean up any transients with `wup_` prefix

### 10. Create directory structure
```bash
mkdir -p includes/{campaigns,features,api,helpers}
mkdir -p admin/{src/components,src/api,build}
mkdir -p public/{js/src,js/build,css/src,css/build}
mkdir -p templates languages tests/unit
```

## Todo
- [ ] Create main plugin file with WP header + WC check
- [ ] Set up composer.json with PSR-4
- [ ] Set up package.json with @wordpress/scripts
- [ ] Implement Loader class
- [ ] Implement Activator class
- [ ] Register CPT `wup_campaign`
- [ ] Create Utils helper class
- [ ] Create Public/Admin coordinator stubs
- [ ] Create uninstall.php
- [ ] Create full directory structure
- [ ] Run `composer dump-autoload`
- [ ] Verify plugin activates without errors

## Success Criteria
- Plugin activates without errors in WP 6.4+ / WC 8.0+
- PSR-4 autoloading works for all classes
- CPT `wup_campaign` registered
- `npm run build` compiles without errors
- HPOS compatibility declared
- No PHP notices/warnings on activation

## Risk Assessment
| Risk | Impact | Mitigation |
|------|--------|------------|
| WC not active | Plugin crash | Guard in main file, show admin notice |
| Namespace collision | Fatal error | Use unique `WooUpsellPro` namespace |
| Build tool version conflicts | Build fails | Pin @wordpress/scripts version |
