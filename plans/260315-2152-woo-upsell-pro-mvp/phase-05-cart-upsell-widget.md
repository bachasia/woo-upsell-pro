---
phase: 5
title: "Cart Upsell Widget"
status: pending
effort: 4h
depends_on: [1]
blocks: [8]
---

# Phase 05: Cart Upsell Widget

## Context Links
- [Design Guidelines](../../docs/design-guidelines.md) -- Cart widget specs

## Overview
**Priority:** P2
Product card grid (2-3 items) below cart totals. AJAX add via WC Store API. Products from auto-selection or manual campaign config.

## Requirements
- Display 2-3 upsell product cards below cart collaterals
- Product source: auto (category/cross-sell from cart items) or manual (campaign)
- AJAX add-to-cart via WC Store API (no page reload)
- Button states: default -> loading -> success (checkmark)
- Exclude products already in cart
- Responsive grid: 3 cols desktop, 2 cols tablet, 1 col mobile
- Heading customizable via settings

## Architecture
```
Cart Page Load:
woocommerce_cart_collaterals hook
  --> WUP_Cart_Upsell::render_widget()
  --> Get cart items -> CampaignManager::get_suggested_products()
  --> Render cart-upsell.php template

User clicks "+ Add":
  --> cart-upsell.js -> POST /wc/store/v1/cart/add-item
  --> On success: update button, trigger WC cart fragment refresh
```

## Related Code Files
**Create:**
- `includes/features/class-wup-cart-upsell.php`
- `templates/cart-upsell.php`
- `public/js/src/cart-upsell.js`
- `public/css/src/cart-upsell.scss`

**Modify:**
- `public/class-wup-public.php` -- enqueue on cart page

## Implementation Steps

### 1. `class-wup-cart-upsell.php`
- Namespace: `WooUpsellPro\Features`
- Methods:
  - `register_hooks(WUP_Loader $loader)`: add cart_collaterals hook
  - `render_widget()`: gather products + render template
  - `get_cart_product_ids(): array`: extract product IDs from current cart
  - `get_upsell_products(): array`: delegate to CampaignManager, exclude cart items

### 2. Product Selection Logic
```
get_upsell_products():
  1. Get active cart_upsell campaigns
  2. If campaign has manual products -> use those
  3. Else auto-select:
     a. Get cross-sells of all cart items
     b. Get products from same categories as cart items
     c. Exclude products already in cart
     d. Deduplicate, sort by total_sales desc
     e. Limit to 3
  4. For each product, build data: id, name, price_html, image_url, permalink
  5. Return array
```

### 3. `templates/cart-upsell.php`
```html
<div class="wup-cart-upsell">
  <h3 class="wup-cart-upsell__heading">
    <?= esc_html($heading ?? __('You might also like', 'woo-upsell-pro')) ?>
  </h3>
  <div class="wup-cart-upsell__grid">
    <?php foreach ($products as $product): ?>
    <div class="wup-cart-upsell__card" data-product-id="<?= $product['id'] ?>">
      <a href="<?= esc_url($product['permalink']) ?>" class="wup-cart-upsell__image-link">
        <img src="<?= esc_url($product['image_url']) ?>"
             alt="<?= esc_attr($product['name']) ?>"
             class="wup-cart-upsell__image" loading="lazy" />
      </a>
      <div class="wup-cart-upsell__info">
        <p class="wup-cart-upsell__name"><?= esc_html($product['name']) ?></p>
        <p class="wup-cart-upsell__price"><?= $product['price_html'] ?></p>
      </div>
      <button class="wup-cart-upsell__add button"
              data-product-id="<?= $product['id'] ?>">
        <?= __('+ Add', 'woo-upsell-pro') ?>
      </button>
    </div>
    <?php endforeach; ?>
  </div>
</div>
```

### 4. `public/js/src/cart-upsell.js`
- **Add to cart handler:**
  1. Click `.wup-cart-upsell__add` button
  2. Set button to loading state (spinner class)
  3. POST to `/wc/store/v1/cart/add-item` with `{ id: productId, quantity: 1 }`
  4. Headers: `Content-Type: application/json`, `Nonce: X-WC-Store-API-Nonce`
  5. On success:
     - Button text -> checkmark, add `--success` class
     - Trigger `jQuery(document.body).trigger('wc_fragment_refresh')`
     - After 2s, hide the card (product now in cart)
  6. On error:
     - Button text -> "Error", add `--error` class
     - Reset after 2s
- **Nonce:** localized via `wp_localize_script` from `class-wup-public.php`

### 5. `public/css/src/cart-upsell.scss`
- BEM: `.wup-cart-upsell`, `__grid`, `__card`, `__image`, `__name`, `__price`, `__add`
- Grid: `display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px`
- Card: border, border-radius `var(--wup-border-radius)`, padding
- Image: `aspect-ratio: 1; object-fit: cover`
- Button states: `--loading` (spinner), `--success` (green check), `--error` (red)
- Responsive:
  - `@media (max-width: 768px)`: 2 columns
  - `@media (max-width: 480px)`: 1 column
- Margin-top: space from cart collaterals

### 6. Conditional Loading
- Enqueue only on cart page: `is_cart()`
- Localize: `rest_url`, `store_api_nonce`, `cart_url`

## Todo
- [ ] Implement WUP_Cart_Upsell class
- [ ] Implement product selection logic
- [ ] Create cart-upsell.php template
- [ ] Implement cart-upsell.js with Store API
- [ ] Create cart-upsell.scss responsive grid
- [ ] Handle button loading/success/error states
- [ ] Test cart fragment refresh after add
- [ ] Test with variable products (simple only for MVP)
- [ ] Test empty state (no suggestions available)

## Success Criteria
- Widget shows 2-3 relevant products below cart totals
- Products already in cart excluded
- AJAX add works without page reload
- Cart totals update after adding upsell product
- Button states animate correctly
- Responsive grid adapts to screen size
- No products = widget hidden (no empty state)

## Risk Assessment
| Risk | Impact | Mitigation |
|------|--------|------------|
| Store API nonce mismatch | Add fails silently | Log error, show user-friendly message |
| No cross-sells configured | Empty widget | Fallback to same-category products |
| Cart fragment refresh conflict | Double render | Use standard WC fragment events |
