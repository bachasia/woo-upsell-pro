# Phase 02 — Frequently Bought Together (FBT Bundle)

**Status:** Done | **Priority:** P0 | **Effort:** L
**Depends on:** Phase 01

## Overview

FBT bundle block on product page: 4 layout variants, checkbox item selection, variant dropdowns for variable products, AJAX add-all-to-cart, optional bundle discount coupon. Also: product data tab for per-product upsell tags.

## Settings (mapped from salesgen)

| WUP option | Default | Description |
|-----------|---------|-------------|
| `wup_upsell_bundle_enable` | `no` | Enable FBT block |
| `wup_upsell_bundle_position` | `woocommerce_after_add_to_cart_form` | Hook name |
| `wup_upsell_bundle_priority` | `50` | Hook priority |
| `wup_upsell_bundle_heading` | `Frequently Bought Together:` | Section heading |
| `wup_upsell_bundle_layout` | `2` | 1–4 |
| `wup_upsell_bundle_source` | `related` | Source mode |
| `wup_upsell_bundle_categories` | `[]` | Category IDs (when source=categories) |
| `wup_upsell_bundle_prefix` | `[FBT]` | Tag prefix for tag-based source |
| `wup_upsell_bundle_hide_all_options` | `no` | Hide variant selects globally |
| `wup_upsell_bundle_hide_options_when` | `2` | Hide if ≤ N inputs in form |
| `wup_upsell_bundle_excludes_conditions_match` | `` | any/all |
| `wup_upsell_bundle_excludes_conditions` | `` | JSON exclusion rules |
| `wup_upsell_bundle_add_action_label` | `Add All To Cart` | CTA button text |
| `wup_upsell_bundle_discount_amount` | `` | % discount (empty = no discount) |
| `wup_fbt_heading_text` | `Frequently Bought Together` | Alt heading |
| `wup_fbt_layout` | `layout-1` | Layout key |
| `wup_fbt_badges_enable` | `no` | Show badge on items |
| `wup_fbt_badges_text` | `Top Pick` | Badge label |
| `wup_fbt_badges_bgcolor` | `#ff9900` | Badge bg color (CSS var) |

## Files to Create

- `includes/features/class-wup-bundle.php` — FBT bundle renderer + AJAX handlers
- `admin/class-wup-product-fields.php` — Product data tab (upsell tags)
- `templates/bundle/layout-1.php`
- `templates/bundle/layout-2.php`
- `templates/bundle/layout-3.php`
- `templates/bundle/layout-4.php`
- `public/js/src/popup.js` — already handles bundle ATC via shared script
- `public/css/src/tier-table.scss` — bundle styles included here

## class-wup-bundle.php responsibilities

```php
class WUP_Bundle {
  // Hook: render bundle on product page
  public function render_bundle(): void

  // AJAX: add selected bundle items to cart
  // action: wp_ajax_wup_add_bundle / wp_ajax_nopriv_wup_add_bundle
  public function ajax_add_bundle(): void

  // AJAX: get quick-view product form HTML
  // action: wp_ajax_wup_quickview / wp_ajax_nopriv_wup_quickview
  public function ajax_quickview(): void

  private function get_bundle_products( $product_id ): array  // uses ProductSource
  private function apply_bundle_discount( $cart ): void        // via WUP_COUPON_BUNDLE coupon
}
```

## Bundle Discount Logic

- Discount stored as `wup_upsell_bundle_discount_amount` (percentage)
- Applied via virtual WC coupon `wupbundle` on `woocommerce_after_calculate_totals`
- Only applies when bundle items are in cart (tracked via cart item meta `_wup_bundle`)
- Prevent double application: check existing applied coupons

## Layout Templates

Each layout receives `$bundle_data`:
```php
$bundle_data = [
  'products'       => [],   // product card DTOs
  'variants'       => [],   // variants map
  'main_product'   => $product,
  'options'        => $this->options,
  'heading'        => string,
  'add_label'      => string,
  'discount_amount'=> float|null,
]
```

Layout differences (from salesgen source):
- **layout-1**: Horizontal row, checkboxes, price total below
- **layout-2**: Grid cards with checkboxes, larger images (default)
- **layout-3**: Compact list, no images
- **layout-4**: Style4 — prominent deal display

## Product Data Tab (class-wup-product-fields.php)

Adds "Upsell Tags" tab to WooCommerce product edit:
- Meta key: `_wup_tags` (text field, comma-separated tag slugs)
- Used by `ProductSourceResolver` when `source=tags` and prefix `[FBT]` filtering
- Save hook: `woocommerce_process_product_meta`

## AJAX Endpoints

| Action | Handler | Auth |
|--------|---------|------|
| `wup_add_bundle` | `ajax_add_bundle()` | nopriv + priv |
| `wup_quickview` | `ajax_quickview()` | nopriv + priv |
| `wup_clear_transients` | (in Phase 01) | priv only |

**add_bundle payload:**
```json
{ "items": [{"product_id": 1, "variation_id": 2, "quantity": 1}], "nonce": "..." }
```
**Response:** `{ "success": true, "fragments": {...}, "cart_count": N }`

## Implementation Steps

1. Create `class-wup-bundle.php` registering hooks only when `wup_upsell_bundle_enable == yes`
2. `render_bundle()`: call `WUP_Product_Source::resolve()`, build `$bundle_data`, `include` layout template
3. Create 4 layout templates mirroring salesgen `layouts/bundle_*.php`
4. `ajax_add_bundle()`: verify nonce, loop items, call `WC()->cart->add_to_cart()`, return fragments
5. Bundle discount: register virtual coupon via `woocommerce_get_shop_coupon_data` filter
6. `ajax_quickview()`: load product form HTML for inline variant selection in popup
7. Create `class-wup-product-fields.php`: tab registration + `_wup_tags` save/load
8. Enqueue `popup.js` + styles only on single product pages

## Todo

- [x] `includes/features/class-wup-bundle.php`
- [x] `includes/features/class-wup-bundle-ajax.php` (trait — split for 200-line rule)
- [x] `admin/class-wup-product-fields.php`
- [x] `templates/bundle/layout-1.php`
- [x] `templates/bundle/layout-2.php`
- [x] `templates/bundle/layout-3.php`
- [x] `templates/bundle/layout-4.php`
- [x] AJAX `wup_add_bundle` with nonce + cart fragments
- [x] AJAX `wup_quickview`
- [x] Bundle discount via virtual `wupbundle` coupon
- [x] Product data tab `_wup_tags` meta
- [x] Frontend JS handles checkbox selection + total price recalc
