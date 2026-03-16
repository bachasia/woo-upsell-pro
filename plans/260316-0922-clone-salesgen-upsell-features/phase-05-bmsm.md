# Phase 05 — Buy More Save More (BMSM) Pricing Engine

**Status:** Todo | **Priority:** P0 | **Effort:** L
**Depends on:** Phase 00

## Overview

BMSM shows a tiered discount widget on product pages/cart. Discounts apply automatically via virtual WC coupon (`wupbmsm`) calculated on `woocommerce_after_calculate_totals`. Two rule types: by **item count** or by **cart subtotal**. Two layout styles: `default` and `style4`.

## Settings

| WUP option | Default | Description |
|-----------|---------|-------------|
| `wup_bmsm_enable` | `no` | Enable BMSM |
| `wup_bmsm_position` | `woocommerce_after_add_to_cart_form` | Hook name |
| `wup_bmsm_priority` | `50` | Hook priority |
| `wup_bmsm_conditional` | `items` | `items` or `amounts` (subtotal) |
| `wup_buy_more_by_items` | `` | JSON tiers for item count mode |
| `wup_buy_more_by_amounts` | `` | JSON tiers for subtotal mode |
| `wup_bmsm_heading_enable` | `yes` | Show heading block |
| `wup_bmsm_heading` | `Buy More Save More!` | Main heading |
| `wup_bmsm_subtitle` | `It's time to give thanks...` | Subtitle |
| `wup_bmsm_heading_icon` | `thankyou` | Icon key |
| `wup_bmsm_style` | `style1` | `style1` (default) or `style4` |
| `wup_bmsm_combie` | `no` | Allow combining with other coupons |
| `wup_bmsm_hide_congrats` | `no` | Hide congratulation message |
| `wup_bmsm_hide_remain` | `no` | Hide "X more to unlock" message |
| `wup_bmsm_categories` | `[]` | Restrict to these categories |
| `wup_bmsm_add_cart_button` | `no` | Show "Buy {quantity}" helper CTA |
| `wup_bmsm_add_action_label` | `Buy {quantity}` | CTA label template |
| `wup_bmsm_congrats_items` | `Hooray! You got BIG discount <strong>[discount_amount]% OFF</strong> for [items_count] items in your cart!` | |
| `wup_bmsm_congrats_subtotal` | `Hooray! You got BIG discount <strong>[discount_amount]% OFF</strong> on each products in your cart!` | |
| `wup_bmsm_remain_items` | `Just buy more [remain] & GET discount <strong>[discount_amount]% OFF</strong> on each products!` | |
| `wup_bmsm_remain_subtotal` | `Spend [remain] more and GET discount <strong>[discount_amount]% OFF</strong> on your order today!` | |

## Tier JSON Format

**By items** (`wup_buy_more_by_items`):
```json
[
  {"min": 2, "discount": 5},
  {"min": 3, "discount": 10},
  {"min": 5, "discount": 15}
]
```

**By amounts** (`wup_buy_more_by_amounts`):
```json
[
  {"min": 50,  "discount": 5},
  {"min": 100, "discount": 10},
  {"min": 200, "discount": 15}
]
```

`discount` is always a **percentage**.

## Files to Create

- `includes/features/class-wup-buy-more-save-more.php`
- `templates/bmsm/default.php`
- `templates/bmsm/style4.php`
- `public/js/src/tier-table.js`
- `public/css/src/tier-table.scss`

## class-wup-buy-more-save-more.php

```php
class WUP_BuyMoreSaveMore {

  private string $bmsm_coupon = 'wupbmsm';

  public function __construct( array $options ) { ... }

  // Widget render (product page hook + popup cart hook)
  public function render_bmsm( array $atts = [] ): void

  // Shortcode [wup_bmsm]
  public function shortcode( array $atts ): string

  // Pricing: apply discount via virtual coupon on cart recalc
  // Hook: woocommerce_after_calculate_totals (priority 1)
  public function apply_discount( WC_Cart $cart ): void

  // Virtual coupon data provider
  // Filter: woocommerce_get_shop_coupon_data
  public function get_virtual_coupon_data( $data, $code, $coupon ): mixed

  // Coupon label override (hide internal coupon slug from customer)
  // Filter: woocommerce_cart_totals_coupon_label
  public function coupon_label( $label, $coupon ): string

  // Admin order coupon label
  // Filter: woocommerce_order_item_get_code
  public function admin_coupon_label( $code, $item, $order ): string

  // When item removed from cart, recalc discount
  // Hook: woocommerce_cart_item_removed
  public function on_cart_item_removed( $cart_item_key, $cart ): void

  // --- Private helpers ---
  private function get_active_tier(): ?array   // best matching tier given cart state
  private function get_cart_item_count(): int  // count only BMSM-eligible items
  private function get_cart_subtotal(): float  // subtotal of BMSM-eligible items
  private function is_eligible_product( int $product_id ): bool  // category filter
  private function parse_tiers( string $json ): array
  private function best_tier( array $tiers, float $current_value ): ?array
  private function next_tier( array $tiers, float $current_value ): ?array
}
```

## Discount Application Logic

```
1. On woocommerce_after_calculate_totals:
   a. Determine eligible cart items (by category filter if set)
   b. Calculate current value (item count OR subtotal)
   c. Find best tier: highest tier where min <= current_value
   d. If tier found:
      - Build virtual coupon 'wupbmsm' with percent discount = tier.discount
      - Apply via WC()->cart->apply_coupon() if not already applied
   e. If no tier matches: remove 'wupbmsm' if present
   f. Prevent loop: use global $bmsm_calc flag (0/1)

2. Virtual coupon via woocommerce_get_shop_coupon_data filter:
   - Return WC_Coupon-compatible data array when $code == 'wupbmsm'
   - discount_type: 'percent'
   - amount: active tier discount %
   - individual_use: depends on wup_bmsm_combie setting
```

## Widget Display Logic

The BMSM widget shows:
- Heading + subtitle (if `wup_bmsm_heading_enable == yes`)
- Tier table: each row = one tier (min qty/amount, discount %)
- Active tier highlighted
- "Congrats" message when a tier is active (if `wup_bmsm_hide_congrats != yes`)
- "Remain" message showing gap to next tier (if `wup_bmsm_hide_remain != yes`)
- Optional "Buy {quantity}" helper CTA buttons per tier (if `wup_bmsm_add_cart_button == yes`)

Token replacements:
- `[discount_amount]` → tier discount %
- `[items_count]` → current cart item count
- `[remain]` → formatted qty/amount gap to next tier

Also renders inside post-ATC popup cart via filter `wup_popup_cart_before_items`.

## Layout Templates

**templates/bmsm/default.php** — standard table layout with colored tier rows
**templates/bmsm/style4.php** — card-style layout with large discount badges

Both receive `$bmsm_data`:
```php
$bmsm_data = [
  'tiers'          => array,    // all tiers
  'active_tier'    => ?array,   // current matching tier
  'next_tier'      => ?array,   // next tier to reach
  'current_value'  => float,    // item count or subtotal
  'conditional'    => string,   // 'items' | 'amounts'
  'options'        => array,    // all BMSM options
]
```

## tier-table.js behavior

1. On page load: highlight active tier row
2. On cart update (`updated_cart_totals` WC event): re-fetch active tier via AJAX or reparse from localized data, update highlight + messages
3. "Buy {qty}" CTA → AJAX add product to cart → WC fragment refresh

## Implementation Steps

1. Create `class-wup-buy-more-save-more.php` with all methods above
2. Register hooks only when `wup_bmsm_enable == yes`
3. Implement `get_active_tier()` with global `$bmsm_calc` guard against recursion
4. Implement virtual coupon filter — return correct coupon data for `wupbmsm`
5. Create `templates/bmsm/default.php` and `style4.php`
6. Register shortcode `[wup_bmsm]` with `style` attribute
7. Create `tier-table.js`: highlight active tier, handle CTA add-to-cart
8. Create `tier-table.scss`: tier table styles, active row highlight, style4 card variant
9. Hook into popup cart: `wup_popup_cart_before_items` filter renders BMSM widget in lightbox

## Todo

- [ ] `includes/features/class-wup-buy-more-save-more.php`
- [ ] `templates/bmsm/default.php`
- [ ] `templates/bmsm/style4.php`
- [ ] `public/js/src/tier-table.js`
- [ ] `public/css/src/tier-table.scss`
- [ ] Virtual coupon `wupbmsm` via filter
- [ ] Coupon label override (human-readable)
- [ ] Anti-loop guard with `$bmsm_calc` global
- [ ] Category filter for eligible items
- [ ] Congrats + remain message token replacement
- [ ] `[wup_bmsm]` shortcode with `style` attr
- [ ] BMSM block in post-ATC popup via filter
