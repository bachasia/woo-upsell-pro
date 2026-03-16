# Phase 09 — QA, Compatibility & Security Pass

**Status:** Todo | **Priority:** P0 | **Effort:** M
**Depends on:** All phases

## Overview

Final hardening pass before production: security audit, WooCommerce compatibility checks, performance validation, and manual test matrix.

---

## Security Checklist

### AJAX Endpoints
All AJAX handlers must:
- [ ] Verify nonce: `check_ajax_referer( 'wup-nonce-name', 'nonce' )`
- [ ] Check capability where appropriate: `current_user_can('manage_woocommerce')` for admin-only
- [ ] Sanitize all `$_POST`/`$_GET` inputs before use
- [ ] Return `wp_send_json_error()` on any validation failure + `wp_die()`

| Endpoint | Nonce | Cap check |
|----------|-------|-----------|
| `wup_add_bundle` | `wup-add-bundle` | none (public) |
| `wup_quickview` | `wup-quickview` | none (public) |
| `wup_get_popup` | `wup-popup` | none (public) |
| `wup_get_side_cart` | `wup-side-cart` | none (public) |
| `wup_sc_update_qty` | `wup-side-cart` | none (public) |
| `wup_sc_remove_item` | `wup-side-cart` | none (public) |
| `wup_sc_apply_coupon` | `wup-side-cart` | none (public) |
| `wup_sc_remove_coupon` | `wup-side-cart` | none (public) |
| `wup_sc_add_item` | `wup-side-cart` | none (public) |
| `wup_cart_upsell_add` | `wup-cart-upsell` | none (public) |
| `wup_clear_transients` | `wup-admin` | `manage_woocommerce` |

### Output Escaping
- [ ] All template output uses `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()` as appropriate
- [ ] No raw `echo $_POST[...]` or `echo get_option(...)` without escaping
- [ ] `do_shortcode()` only on trusted admin-saved content (announcement text, email body)

### Settings Sanitization
- [ ] All `register_setting()` calls include sanitize callback
- [ ] Text fields: `sanitize_text_field()`
- [ ] HTML fields (email content, announcement text): `wp_kses_post()`
- [ ] Numeric fields: `absint()` or `floatval()` with range bounds
- [ ] Select fields: validate against allowed enum values
- [ ] JSON textarea fields: validate `json_decode()` result is array

### SQL / DB
- [ ] No raw SQL. Only WP/WC APIs (`WC_Coupon`, `wc_get_products()`, etc.)
- [ ] If raw SQL unavoidable: `$wpdb->prepare()` mandatory

---

## Compatibility Checklist

### WooCommerce
- [ ] HPOS (High Performance Order Storage) compatibility:
  - Use `wc_get_order()` not `get_post()`
  - Declare compatibility: `\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', WUP_FILE)`
- [ ] Cart fragments: verify `woocommerce_add_to_cart_fragments` works with AJAX cart
- [ ] Variable products: ensure all variation attribute combinations handled
- [ ] Tax display: price HTML uses WC's `wc_price()`, respects tax display settings
- [ ] Test with WooCommerce 8.x and 9.x

### WordPress / PHP
- [ ] PHP 8.1 / 8.2 / 8.3 — no deprecated functions, no implicit nullable params
- [ ] WordPress 6.4+ — no deprecated hooks
- [ ] `FILTER_SANITIZE_STRING` removed in PHP 8.1 → use `FILTER_DEFAULT` or `sanitize_text_field()`

### Theme Compatibility
- [ ] Side cart open selector configurable (admin sets correct selector per theme)
- [ ] Announcement topbar injected via `wp_footer` — works across block/classic themes
- [ ] Bundle position hook configurable — themes may use different hooks

---

## Performance Checklist

- [ ] Product source results cached in transients (12h), invalidated on setting change
- [ ] No `WP_Query` or `wc_get_products()` calls inside loops
- [ ] Side cart fragment payload minimal — renders only changed sections
- [ ] JS assets enqueued conditionally (popup.js only on product pages, sidecart.js everywhere if enabled, etc.)
- [ ] Dynamic CSS output once via `wp_add_inline_style()`, not per request

### Asset Enqueue Conditions

| Asset | Condition |
|-------|-----------|
| `popup.js` | `is_product()` OR popup enabled |
| `sidecart.js` | side cart enabled (sitewide) |
| `cart-upsell.js` | `is_cart()` AND cart upsell enabled |
| `tier-table.js` | `is_product()` OR `is_cart()` AND BMSM enabled |
| Admin JS | `is_admin()` AND on plugin settings page |

---

## Functional Test Matrix

### FBT Bundle
- [ ] Simple product: bundle renders, add-all adds to cart
- [ ] Variable product: variant select updates price, add-all with correct variation
- [ ] Bundle discount: coupon `wupbundle` applied correctly, removed when bundle items removed
- [ ] Layout 1, 2, 3, 4 render without errors
- [ ] Exclusion conditions filter products correctly
- [ ] `[wup_upsell]` shortcode renders outside product page

### Post-ATC Popup
- [ ] Popup shows after add-to-cart event
- [ ] Upsell items rendered with correct source
- [ ] Add upsell item from popup → cart count updates
- [ ] Variable product in popup: variant select → price updates
- [ ] View Cart / Checkout buttons go to correct URLs
- [ ] Close (×) and overlay click close popup

### Side Cart
- [ ] Opens on configured selector click
- [ ] Floating icon shows/hides badge correctly
- [ ] Qty +/− updates cart total and shipping bar
- [ ] Remove item updates cart
- [ ] Coupon apply: success and error states
- [ ] Coupon remove works
- [ ] Shipping bar: progress updates correctly at threshold
- [ ] FBT strip: product added from strip, cart updates
- [ ] Empty cart state handled gracefully
- [ ] Mobile: renders correctly at ≤768px

### BMSM
- [ ] Item count tiers: correct tier activates at each threshold
- [ ] Subtotal tiers: correct tier activates
- [ ] `wupbmsm` coupon applied automatically
- [ ] Coupon removed when cart drops below minimum tier
- [ ] Congrats message shows/hides correctly
- [ ] Remain message correct at each level
- [ ] Category filter: only eligible products counted
- [ ] `wup_bmsm_combie=no`: other coupons removed when BMSM active
- [ ] style4 layout renders without errors
- [ ] `[wup_bmsm]` shortcode works

### Cart Upsell / Thank-you / Related
- [ ] Cart upsell shows products from cart items, excludes already-in-cart
- [ ] Add from cart upsell: cart fragments update
- [ ] Thank-you: products based on order items, excludes purchased
- [ ] Related: respects configured source/position/priority
- [ ] All shortcodes render correctly

### Announcements
- [ ] Topbar renders in footer with correct text/colors
- [ ] Product bar renders in single product summary
- [ ] Background patterns 01–04 applied correctly via dynamic CSS
- [ ] Custom bg image applied
- [ ] Empty text → block not rendered

### Sales Popups
- [ ] Popup shows after `loop_time` seconds
- [ ] Hides after `display_time` seconds
- [ ] Random name/city/product on each cycle
- [ ] `{{time}}` shows reasonable range
- [ ] Page targeting: home_only / product_cart / all
- [ ] Mobile hidden works
- [ ] 3 templates render with correct classes

### Email Coupon
- [ ] Coupon created on order completion
- [ ] Email sent with correct subject/body tokens replaced
- [ ] `_wup_coupon_sent` prevents duplicate send
- [ ] One-use limit enforced on coupon
- [ ] `wup_advanced_coupons_one=yes` sets `usage_limit_per_user=1`

### FOMO Stock
- [ ] Shows when stock within min–max range
- [ ] Hidden when out of range or not managing stock
- [ ] `[stock]` replaced with actual quantity
- [ ] Color applied correctly

---

## Edge Cases

- [ ] Empty cart + side cart: empty state message, no PHP notices
- [ ] Out-of-stock products skipped in all recommendation blocks
- [ ] Nonce expiry: AJAX returns proper error, JS shows user-friendly message
- [ ] BMSM + bundle discount both active: no coupon conflict
- [ ] Multiple BMSM tiers — only best tier applied
- [ ] High product count categories: transient prevents slow queries
- [ ] Plugin deactivate: no fatal errors, options persist
- [ ] Plugin uninstall (`uninstall.php`): all `wup_*` options deleted

---

## Implementation Steps

1. Run through security checklist — patch any missing nonce/cap/escape/sanitize
2. Add HPOS compatibility declaration in `class-wup-loader.php`
3. Replace any `FILTER_SANITIZE_STRING` usage
4. Conditional asset enqueue audit
5. Manual test on: WC 8.x + WP 6.5 + PHP 8.2 + Storefront theme
6. Manual test on: WC 9.x + block theme (Twenty Twenty-Four)
7. Mobile test (Chrome DevTools responsive mode)
8. Fix all issues found

## Todo

- [ ] Security audit: all AJAX nonces + escaping + sanitization
- [ ] HPOS compatibility declaration
- [ ] PHP 8.1+ compatibility (`FILTER_SANITIZE_STRING` → `FILTER_DEFAULT`)
- [ ] Conditional asset enqueue review
- [ ] Full functional test matrix (desktop)
- [ ] Full functional test matrix (mobile)
- [ ] Edge case testing
- [ ] Performance check: no heavy queries outside transient cache
