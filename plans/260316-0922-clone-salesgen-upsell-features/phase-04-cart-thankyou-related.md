# Phase 04 — Cart Upsell + Thank-you Upsell + Related Products

**Status:** Done | **Priority:** P0 | **Effort:** M
**Depends on:** Phase 01

## Overview

Three frontend conversion blocks sharing the same product source + cross-sell renderer:
1. **Cart upsell** — product recommendations in cart page, AJAX add-to-cart
2. **Thank-you upsell** — order-based recommendations on order confirmation page
3. **Related products block** — configurable replacement/supplement for WC default related products

---

## Part A — Cart Upsell

### Settings

| WUP option | Default | Description |
|-----------|---------|-------------|
| `wup_cart_upsell_enable` | `no` | Enable |
| `wup_cart_upsell_source` | `related` | Source mode |
| `wup_cart_upsell_categories` | `[]` | Category IDs |
| `wup_cart_upsell_limit` | `4` | Max products |
| `wup_cart_upsell_hide_options` | `no` | Hide variant selects |
| `wup_cart_upsell_excludes_conditions` | `` | Exclusion rules JSON |

### Files

- `includes/features/class-wup-cart-upsell.php`
- `templates/cart-upsell.php`
- `public/js/src/cart-upsell.js`
- `public/css/src/cart-upsell.scss`

### class-wup-cart-upsell.php

```php
class WUP_Cart_Upsell {
  // Hook: woocommerce_cart_collaterals (or woocommerce_after_cart_table)
  public function render_cart_upsell(): void

  // AJAX: add single item from cart upsell widget
  // action: wup_cart_upsell_add / nopriv
  public function ajax_add_item(): void
  // POST: product_id, variation_id, quantity, nonce
  // Response: { success, cart_count, fragments }
}
```

### Product resolution for cart upsell

Source products from **cart items**: loop `WC()->cart->get_cart()`, collect product IDs, call `WUP_Product_Source::resolve()` for each, merge + deduplicate, exclude items already in cart.

### Shortcode

`[wup_cart_upsell]` — renders cart upsell block anywhere

---

## Part B — Thank-you Upsell

### Settings

| WUP option | Default | Description |
|-----------|---------|-------------|
| `wup_thankyou_upsell_enable` | `no` | Enable |
| `wup_thankyou_upsell_source` | `related` | Source mode |
| `wup_thankyou_upsell_categories` | `[]` | Category IDs |
| `wup_thankyou_upsell_limit` | `4` | Max products |
| `wup_thankyou_upsell_hide_options` | `no` | Hide variant selects |
| `wup_thankyou_upsell_excludes_conditions` | `` | Exclusion rules |

### Files

- `includes/features/class-wup-thankyou-upsell.php`
- `templates/email-coupon.php` (coupon reveal — also used by Phase 07)

### class-wup-thankyou-upsell.php

```php
class WUP_Thankyou_Upsell {
  // Hook: woocommerce_thankyou (priority 20)
  public function render_thankyou_upsell( $order_id ): void

  private function get_order_product_ids( $order_id ): array
}
```

Product resolution: get order line item product IDs → `WUP_Product_Source::resolve()` for each → merge, deduplicate, exclude already-purchased products.

Uses `SG_UB_Render::cross_sell_display()` equivalent → `WUP_Renderer::cross_sell_display()` (shared renderer).

---

## Part C — Related Products Block

### Settings

| WUP option | Default | Description |
|-----------|---------|-------------|
| `wup_related_enable` | `no` | Enable |
| `wup_related_source` | `related` | Source mode |
| `wup_related_categories` | `[]` | Category IDs |
| `wup_related_limit` | `4` | Max products |
| `wup_related_hide_options` | `no` | Hide variant selects |
| `wup_related_excludes_conditions` | `` | Exclusion rules |
| `wup_related_position` | `woocommerce_after_single_product_summary` | Hook |
| `wup_related_priority` | `50` | Hook priority |

### Files

- `includes/features/class-wup-related.php`

### class-wup-related.php

```php
class WUP_Related {
  // Registered on configured hook/priority
  public function render_related(): void

  // Shortcode [wup_related]
  public function shortcode( $atts ): string
}
```

---

## Shared Renderer (class-wup-renderer.php)

All three features use same cross-sell card HTML. Extract to shared class:

```
includes/features/class-wup-renderer.php
```

```php
class WUP_Renderer {
  // Render grid of product cards (cross-sell style)
  public static function cross_sell_display(
    array $products,
    array $variants,
    array $args = []
  ): string
  // $args: heading, class_wrp, cols_classes, hide_options, add_label

  // Render single product card
  public static function product_card( array $product, array $variant_map, array $args ): string
}
```

Card HTML structure (from salesgen render.php):
- `<li>` with data attrs: `data-parent`, `data-id`, `data-price`, `data-variants` (JSON), `data-default_attributes` (JSON)
- Product image link, title, price HTML, variation form, add-to-cart or select-options button

## Shortcodes Summary

| Shortcode | Class | Handler |
|-----------|-------|---------|
| `[wup_cart_upsell]` | `WUP_Cart_Upsell` | `shortcode()` |
| `[wup_related]` | `WUP_Related` | `shortcode()` |

## Implementation Steps

1. Create `class-wup-renderer.php` with `cross_sell_display()` and `product_card()`
2. Create `class-wup-cart-upsell.php`:
   - Resolve from cart items (dedupe, exclude in-cart)
   - Render via `WUP_Renderer::cross_sell_display()`
   - AJAX `wup_cart_upsell_add`
   - Register `[wup_cart_upsell]` shortcode
3. Create `templates/cart-upsell.php`
4. Create `public/js/src/cart-upsell.js` (AJAX add, variant select, fragment update)
5. Create `class-wup-thankyou-upsell.php`:
   - Resolve from order items (dedupe, exclude purchased)
   - Render via `WUP_Renderer`
   - Hook `woocommerce_thankyou`
6. Create `class-wup-related.php`:
   - Resolve from current product
   - Render via `WUP_Renderer`
   - Register on configured hook
   - `[wup_related]` shortcode
7. Enqueue `cart-upsell.js` only on cart page

## Todo

- [x] `includes/features/class-wup-renderer.php` (shared cross-sell renderer)
- [x] `includes/features/class-wup-cart-upsell.php` + AJAX + shortcode
- [x] `templates/cart-upsell.php`
- [x] `public/js/src/cart-upsell.js`
- [x] `public/css/src/cart-upsell.scss`
- [x] `includes/features/class-wup-thankyou-upsell.php`
- [x] `includes/features/class-wup-related.php` + shortcode
- [x] Cart items → source resolver dedup + exclude in-cart
- [x] Order items → source resolver dedup + exclude purchased
