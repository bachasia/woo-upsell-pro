---
phase: 3
title: "Buy More Save More (Tier Table)"
status: pending
effort: 4h
depends_on: [1]
blocks: [8]
---

# Phase 03: Buy More Save More Feature

## Context Links
- [Design Guidelines](../../docs/design-guidelines.md) -- Tier table specs
- [WC Architecture Research](../reports/researcher-woocommerce-architecture-260315-2151.md) -- Cart discount hooks

## Overview
**Priority:** P2
Quantity-based discount tiers displayed as table on product + cart pages. Discounts applied via WC hooks.

## Requirements
- Tier configuration from campaign meta or global settings
- Display tier table on product page (below qty input) and cart page (above cart table)
- Active tier highlighted dynamically when quantity changes
- Discount applied via `woocommerce_cart_calculate_fees`
- Product-level price display via `woocommerce_product_get_price` (optional, for display only)
- Mobile-responsive table (collapses to cards)

## Architecture
```
Product Page:                       Cart Page:
woocommerce_before_add_to_cart_qty  woocommerce_before_cart_table
  --> render tier-table.php           --> render tier-table.php (cart context)

Cart Calculation:
woocommerce_cart_calculate_fees --> calculate discount based on qty in cart
```

## Related Code Files
**Create:**
- `includes/features/class-wup-buy-more-save-more.php`
- `templates/tier-table.php`
- `public/js/src/tier-table.js`
- `public/css/src/tier-table.scss`

**Modify:**
- `public/class-wup-public.php` -- enqueue scripts/styles conditionally
- `includes/class-wup-loader.php` -- register hooks via Loader

## Implementation Steps

### 1. `class-wup-buy-more-save-more.php`
- Namespace: `WooUpsellPro\Features`
- Constructor: inject Loader reference (or accept config)
- Methods:
  - `register_hooks(WUP_Loader $loader)`: add all hooks
  - `display_tier_table_product()`: render on product page
  - `display_tier_table_cart()`: render on cart page
  - `apply_cart_discount(WC_Cart $cart)`: calculate and apply fee
  - `get_tiers_for_product(int $product_id): array`: get applicable tiers
  - `get_active_tier(array $tiers, int $qty): ?array`: find matching tier
  - `calculate_discount(float $price, array $tier): float`: compute discount amount

### 2. Tier Data Structure
```php
$tiers = [
    ['qty' => 2, 'discount' => 5, 'type' => 'percent'],
    ['qty' => 5, 'discount' => 10, 'type' => 'percent'],
    ['qty' => 10, 'discount' => 20, 'type' => 'percent'],
];
```
Source priority: Campaign-specific tiers > Global default tiers from settings.

### 3. Product Page Display
- Hook: `woocommerce_before_add_to_cart_quantity` (priority 10)
- Check: feature enabled + product has applicable tiers
- Load `templates/tier-table.php` with data:
  - `$tiers` array, `$current_qty` (default 1), `$product_price`
- Localize tier data to JS for dynamic highlighting

### 4. Cart Page Display
- Hook: `woocommerce_before_cart_table` (priority 10)
- Group cart items by product, show tier table per product with tiers
- Or show combined tier info if global tiers apply

### 5. Discount Calculation Hook
- Hook: `woocommerce_cart_calculate_fees` (priority 20)
- For each cart item with applicable tiers:
  1. Get product qty in cart
  2. Find active tier (`qty >= tier.qty`, highest matching)
  3. Calculate discount: `price * qty * (tier.discount / 100)` for percent
  4. Add negative fee: `$cart->add_fee('Buy More Save More', -$discount, false)`
- Label format: `"Bulk discount: {product_name} ({tier.discount}% off)"`

### 6. `templates/tier-table.php`
```html
<div class="wup-tier-table" data-product-id="<?= $product_id ?>">
  <h4 class="wup-tier-table__heading"><?= __('Buy more, save more!', 'woo-upsell-pro') ?></h4>
  <table class="wup-tier-table__table">
    <thead><tr><th>Quantity</th><th>Discount</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($tiers as $tier): ?>
      <tr class="wup-tier-table__row <?= $tier['active'] ? 'wup-tier-table__row--active' : '' ?>">
        <td><?= $tier['qty'] ?>+</td>
        <td><?= $tier['discount'] ?>% OFF</td>
        <td><?= $tier['active'] ? __('current', 'woo-upsell-pro') : __('locked', 'woo-upsell-pro') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
```

### 7. `public/js/src/tier-table.js`
- Listen to qty input `change` event on product page
- Read tier data from `data-tiers` attribute or localized var
- Update active class on matching tier row
- Recalculate and display savings preview text
- No framework -- vanilla JS, ES6+

### 8. `public/css/src/tier-table.scss`
- BEM: `.wup-tier-table`, `.wup-tier-table__row`, `.wup-tier-table__row--active`
- Active row: bold text, accent color left border, subtle bg highlight
- Mobile: `@media (max-width: 480px)` collapse to stacked cards
- Use CSS custom properties from design guidelines

### 9. Conditional Asset Loading
- Only enqueue `tier-table.js` + `tier-table.css` on:
  - Product pages (`is_product()`) with applicable tiers
  - Cart page (`is_cart()`)
- Check via `wp_enqueue_scripts` hook with conditionals

## Todo
- [ ] Implement BuyMoreSaveMore class with all methods
- [ ] Create tier-table.php template
- [ ] Implement discount calculation in cart fees hook
- [ ] Create tier-table.js for dynamic highlighting
- [ ] Create tier-table.scss with responsive styles
- [ ] Conditional asset loading
- [ ] Test with variable products
- [ ] Test discount stacking behavior

## Success Criteria
- Tier table renders on product page with correct tiers
- Active tier highlights when qty changes (JS)
- Cart discount applies correctly as negative fee
- Multiple products with different tiers each get correct discount
- Table is responsive on mobile
- No CLS on page load

## Risk Assessment
| Risk | Impact | Mitigation |
|------|--------|------------|
| Discount stacking with coupons | Over-discount | Document behavior, add max discount cap |
| Variable product tiers | Complexity | Apply tiers to parent product initially |
| Cache invalidation | Stale tiers | Use transient with product save hook flush |
