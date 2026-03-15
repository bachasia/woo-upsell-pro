---
phase: 4
title: "Add-to-Cart Popup"
status: pending
effort: 4h
depends_on: [1]
blocks: [8]
---

# Phase 04: Add-to-Cart Popup

## Context Links
- [Design Guidelines](../../docs/design-guidelines.md) -- Popup specs, animations, dimensions

## Overview
**Priority:** P2
Slide-in popup after add-to-cart showing product added + 1 upsell suggestion. Auto-dismiss 5s. Desktop: bottom-right; Mobile: bottom sheet.

## Requirements
- Trigger on WC `added_to_cart` JS event (AJAX add) + page reload (non-AJAX)
- Show: product name, thumbnail, price of item added
- Show: 1 upsell product suggestion (from campaign or auto)
- CTAs: "View Cart" (primary), "Continue Shopping" (ghost/dismiss)
- Auto-dismiss after 5s with progress bar
- Accessible: focus trap, ESC closes, keyboard nav, ARIA labels
- Responsive: 380px max-width desktop, 100% bottom-sheet mobile

## Architecture
```
User clicks Add to Cart
  --> WC fires added_to_cart JS event (or page reload with ?added-to-cart=ID)
  --> popup.js intercepts
  --> Fetch upsell product via REST (GET /wup/v1/products/suggest?product_id=X)
  --> Inject data into popup template (already in DOM via wp_footer)
  --> Animate slide-in
  --> Auto-dismiss timer (5s)
```

## Related Code Files
**Create:**
- `includes/features/class-wup-popup.php`
- `templates/popup.php`
- `public/js/src/popup.js`
- `public/css/src/popup.scss`

**Modify:**
- `public/class-wup-public.php` -- enqueue popup assets
- `includes/api/class-wup-rest-controller.php` -- add suggest endpoint (public, no auth needed)

## Implementation Steps

### 1. `class-wup-popup.php`
- Namespace: `WooUpsellPro\Features`
- Methods:
  - `register_hooks(WUP_Loader $loader)`: add wp_footer hook
  - `render_popup_container()`: output popup.php template in footer
  - `get_upsell_for_product(int $product_id): ?array`: get 1 upsell product
  - `prepare_popup_data(int $product_id): array`: localize data for JS

### 2. REST Endpoint for Product Suggestion
- Add to `class-wup-rest-controller.php`:
- `GET /wup/v1/products/suggest?product_id=123`
- Permission: public (no auth -- frontend needs it)
- Response: `{ id, name, price, price_html, image_url, permalink, add_to_cart_url }`
- Logic: delegate to CampaignManager `get_suggested_products()`, return first result
- If no suggestion available, return `null`

### 3. `templates/popup.php`
```html
<div id="wup-popup" class="wup-popup" role="dialog" aria-label="<?= __('Product added to cart', 'woo-upsell-pro') ?>" aria-hidden="true">
  <div class="wup-popup__overlay"></div>
  <div class="wup-popup__container">
    <button class="wup-popup__close" aria-label="<?= __('Close', 'woo-upsell-pro') ?>">&times;</button>
    <div class="wup-popup__progress"><div class="wup-popup__progress-bar"></div></div>

    <!-- Added product -->
    <div class="wup-popup__added">
      <span class="wup-popup__checkmark">&#10003;</span>
      <img class="wup-popup__image" src="" alt="" />
      <div class="wup-popup__info">
        <p class="wup-popup__product-name"></p>
        <p class="wup-popup__product-price"></p>
      </div>
    </div>

    <!-- Upsell suggestion -->
    <div class="wup-popup__upsell" style="display:none">
      <p class="wup-popup__upsell-heading"><?= __('Customers also bought', 'woo-upsell-pro') ?></p>
      <div class="wup-popup__upsell-product">
        <img class="wup-popup__upsell-image" src="" alt="" />
        <div class="wup-popup__upsell-info">
          <p class="wup-popup__upsell-name"></p>
          <p class="wup-popup__upsell-price"></p>
        </div>
        <button class="wup-popup__upsell-add button"><?= __('+ Add', 'woo-upsell-pro') ?></button>
      </div>
    </div>

    <!-- CTAs -->
    <div class="wup-popup__actions">
      <a class="wup-popup__view-cart button alt" href="<?= wc_get_cart_url() ?>"><?= __('View Cart', 'woo-upsell-pro') ?></a>
      <button class="wup-popup__continue"><?= __('Continue Shopping', 'woo-upsell-pro') ?></button>
    </div>
  </div>
</div>
```

### 4. `public/js/src/popup.js`
- **Event listeners:**
  - `jQuery(document.body).on('added_to_cart', handler)` -- WC AJAX event
  - On page load, check URL param `?added-to-cart` for non-AJAX
- **Show flow:**
  1. Get added product data from WC event or localized data
  2. Fetch upsell via `fetch('/wp-json/wup/v1/products/suggest?product_id=X')`
  3. Populate popup DOM elements
  4. Show upsell section if suggestion found
  5. Add `wup-popup--visible` class (triggers CSS animation)
  6. Start auto-dismiss timer (5s)
  7. Animate progress bar width 0->100% over 5s
- **Dismiss:** click close/continue/overlay, ESC key, timer
- **Upsell add:** POST to WC Store API `/wc/store/v1/cart/add-item`
  - On success: update button text to checkmark, refresh cart fragments
- **Focus trap:** tab cycling within popup when open
- **Reduced motion:** check `window.matchMedia('(prefers-reduced-motion: reduce)')`

### 5. `public/css/src/popup.scss`
- Position: `fixed`, bottom-right (desktop), bottom 0 full-width (mobile)
- Slide-up animation: `transform: translateY(100%)` -> `translateY(0)`
- `.wup-popup--visible`: trigger animation
- Progress bar: gradient fill, 5s `transition: width 5s linear`
- Breakpoint: `@media (max-width: 480px)` -> bottom sheet style
- Max-width: 380px desktop
- Shadow: `var(--wup-shadow)`
- Use CSS custom properties from design guidelines
- `@media (prefers-reduced-motion: reduce)`: disable animations

### 6. Localize Data to JS
- In `class-wup-public.php`, `wp_localize_script('wup-popup', 'wupPopupData', [...])`
- Data: `rest_url`, `nonce`, `auto_dismiss_seconds`, `store_api_nonce`
- WC Store API nonce: use `wp_create_nonce('wc_store_api')`

### 7. Conditional Loading
- Only enqueue on shop pages: `is_shop()`, `is_product()`, `is_product_category()`, `is_product_tag()`
- Or globally if WC AJAX add-to-cart can trigger anywhere

## Todo
- [ ] Implement WUP_Popup class
- [ ] Add suggest endpoint to REST controller
- [ ] Create popup.php template
- [ ] Implement popup.js with WC event handling
- [ ] Implement popup.scss with animations
- [ ] Add focus trap + accessibility
- [ ] Handle upsell add-to-cart via Store API
- [ ] Test AJAX and non-AJAX add-to-cart triggers
- [ ] Test mobile bottom-sheet layout
- [ ] Test reduced-motion preference

## Success Criteria
- Popup appears after add-to-cart (both AJAX and page reload)
- Upsell product displays when available
- Auto-dismiss works with progress bar
- Upsell add-to-cart updates cart without reload
- Accessible: keyboard nav, ESC close, screen reader labels
- Mobile bottom-sheet layout works

## Risk Assessment
| Risk | Impact | Mitigation |
|------|--------|------------|
| Theme CSS conflicts | Broken layout | Use `.wup-` prefix, high specificity |
| WC event not firing | Popup never shows | Fallback to URL param detection |
| Store API nonce expiry | Add fails | Refresh nonce from WC on 403 |
