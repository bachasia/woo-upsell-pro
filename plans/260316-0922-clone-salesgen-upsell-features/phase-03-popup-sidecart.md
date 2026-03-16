# Phase 03 — Post-ATC Lightbox Popup + Slide Side Cart

**Status:** Todo | **Priority:** P0 | **Effort:** XL
**Depends on:** Phase 01, Phase 02 (AJAX fragments contract)

---

## Part A — Post Add-to-Cart Lightbox Popup

### Overview
Lightbox shown after any product is added to cart. Shows: added product confirmation + upsell list with variant handling + "View Cart" / "Checkout" CTAs. Fragment refresh after adding upsell item.

### Settings

| WUP option | Default | Description |
|-----------|---------|-------------|
| `wup_upsell_popup_enable` | `no` | Enable popup |
| `wup_upsell_popup_source` | `related` | Product source |
| `wup_upsell_popup_categories` | `[]` | Category IDs |
| `wup_upsell_popup_limit` | `10` | Max items |
| `wup_upsell_popup_heading_text` | `Frequently bought with [product_name]` | Heading (token: `[product_name]`) |
| `wup_upsell_popup_products_layout` | `default` | Layout variant |
| `wup_upsell_popup_hide_items` | `no` | Hide upsell list entirely |
| `wup_upsell_popup_hide_options` | `no` | Hide variant selects |
| `wup_upsell_popup_add_action_label` | `Add To Cart` | Per-item CTA label |
| `wup_upsell_image_variants` | `no` | Update image on variant change |
| `wup_upsell_checkout_button_color` | `#2196f3` | CSS var |
| `wup_upsell_viewcart_button_color` | `#FFFFFF` | CSS var |

### Files

- `includes/features/class-wup-popup.php`
- `templates/popup/lightbox.php`
- `public/js/src/popup.js` (shared with bundle AJAX)
- `public/css/src/popup.scss`

### class-wup-popup.php

```php
class WUP_Popup {
  // Inject popup HTML into footer (hidden, activated by JS)
  public function render_popup_shell(): void  // hook: wp_footer

  // AJAX: return popup HTML for just-added product
  // action: wup_get_popup / nopriv
  public function ajax_get_popup(): void
  // POST: product_id, nonce
  // Response: { html: '...', fragments: {...} }

  // Filter: add cart fragment key for popup
  public function add_cart_fragment( $fragments ): array
}
```

### Popup JS behavior (popup.js)

1. Intercept `added_to_cart` WC event
2. AJAX call `wup_get_popup` with `product_id`
3. Inject HTML into modal shell, show lightbox
4. Per-item "Add To Cart" → AJAX add → refresh fragments → update badge
5. Variant select change → update price display (optionally swap image)
6. "View Cart" → go to cart URL; "Checkout" → go to checkout URL
7. Close on overlay click or `×` button

---

## Part B — Slide Side Cart

### Overview
Full-featured slide-in cart panel. Replaces default WC mini-cart. Sections: header, line items, free shipping bar, FBT strip, coupon box, footer. Floating cart icon with badge. All cart mutations via AJAX with fragment refresh.

### Settings

| WUP option | Default | Description |
|-----------|---------|-------------|
| `wup_upsell_sidecart_enable` | `no` | Enable side cart |
| `wup_upsell_sidecart_open_selector` | `.header-cart-link` | CSS selector that opens cart |
| `wup_upsell_sidecart_font` | `font-sans` | Font class |
| `wup_upsell_sidecart_checkout_label` | `Checkout` | Checkout button label |
| `wup_upsell_sidecart_primary_color` | `#2c7a7b` | CSS var `--wup-sc-color-primary` |
| `wup_upsell_sidecart_icon_enable` | `no` | Show floating cart icon |
| `wup_upsell_sidecart_icon_bgcolor` | `#FFFFFF` | Icon background |
| `wup_upsell_sidecart_icon_color` | `#000000` | Icon color |
| `wup_upsell_sidecart_icon_position` | `bottom_right` | bottom_right / bottom_left |
| `wup_upsell_sidecart_icon_size` | `md` | sm / md / lg |
| `wup_sidecart_fsg_enable` | `no` | Free shipping bar |
| `wup_sidecart_fsg_type` | `amount` | amount / count |
| `wup_sidecart_fsg_amount` | `100` | Threshold value |
| `wup_sidecart_fsg_msg_progress` | `Only [remain] away from <strong>Free Shipping</strong>` | Progress msg |
| `wup_sidecart_fsg_msg_success` | `Congratulations! You have got <strong>Free Shipping</strong>` | Success msg |
| `wup_sidecart_fsg_color` | `#2196f3` | Progress bar fill color |
| `wup_sidecart_fsg_bg_color` | `#e9e9e9` | Progress bar bg |
| `wup_sidecart_fbt_enable` | (from FBT settings) | FBT strip in side cart |

### Files

- `includes/features/class-wup-side-cart.php`
- `templates/side-cart/header.php`
- `templates/side-cart/items.php`
- `templates/side-cart/shipping-bar.php`
- `templates/side-cart/fbt.php`
- `templates/side-cart/coupon.php`
- `templates/side-cart/footer.php`
- `public/js/src/sidecart.js`
- `public/css/src/sidecart.scss` (rename from cart-upsell.scss for side-cart styles)

### Template sections (matches salesgen layouts/side-cart/*)

**header.php** — cart title with count, close button, free-shipping-bar enabled class
**items.php** — loop `WC()->cart->get_cart()`, each item: image, name, variation attrs, price, qty stepper (+/−), remove button
**shipping-bar.php** — progress bar calc: `(cart_subtotal / threshold) * 100%`, progress/success message
**fbt.php** — strip of upsell product cards (from ProductSource, based on first cart item), add-to-cart per item
**coupon.php** — text input + "Apply" button, list of applied coupons with remove (×)
**footer.php** — subtotal, checkout button

### class-wup-side-cart.php AJAX endpoints

| Action | Handler | Description |
|--------|---------|-------------|
| `wup_get_side_cart` | `ajax_get_side_cart()` | Return full side cart HTML |
| `wup_sc_update_qty` | `ajax_update_qty()` | Update item qty, return fragments |
| `wup_sc_remove_item` | `ajax_remove_item()` | Remove item, return fragments |
| `wup_sc_apply_coupon` | `ajax_apply_coupon()` | Apply coupon code |
| `wup_sc_remove_coupon` | `ajax_remove_coupon()` | Remove applied coupon |
| `wup_sc_add_item` | `ajax_add_item()` | Add FBT item from strip |

All endpoints: verify nonce `wup-side-cart`, return `{ success, html, fragments, cart_count }`.

### Cart Fragments Contract

```php
// Filter: woocommerce_add_to_cart_fragments
$fragments['div.sg-slide-cart-content'] = $this->render_all_sections();
$fragments['.sg-cart-count'] = '<span class="sg-cart-count">'. $count .'</span>';
```

JS listens to `wc_fragments_refreshed` to update badge and re-render if cart is open.

### Floating Cart Icon

- Injected via `wp_footer` when `wup_upsell_sidecart_icon_enable == yes`
- Position class: `wup-cart-icon--bottom-right` etc.
- Badge `<span>` updates via fragment

### sidecart.js behavior

1. Click on `wup_sidecart_open_selector` (or floating icon) → open panel, load via `wup_get_side_cart`
2. Qty +/− click → `wup_sc_update_qty` → re-render items + shipping bar + footer
3. Remove → `wup_sc_remove_item` → same
4. Coupon apply → `wup_sc_apply_coupon` → show success/error
5. Coupon remove → `wup_sc_remove_coupon`
6. FBT add → `wup_sc_add_item` → update badge
7. Close: button click or overlay click
8. `added_to_cart` WC event → if popup disabled, open side cart instead (or in addition)

## Implementation Steps

**Popup:**
1. `class-wup-popup.php`: inject shell `<div id="wup-popup-modal">` via `wp_footer`
2. Register AJAX `wup_get_popup`: resolve upsell products via ProductSource, build `$popup_data`, render `templates/popup/lightbox.php`, return as JSON
3. `popup.js`: intercept `added_to_cart`, call AJAX, inject HTML, bind close/CTA events
4. Popup CSS in `popup.scss`

**Side Cart:**
1. `class-wup-side-cart.php`: inject cart panel shell + overlay via `wp_footer` when enabled
2. Render methods per section (header/items/shipping-bar/fbt/coupon/footer)
3. Add `woocommerce_add_to_cart_fragments` filter for live updates
4. Register all 6 AJAX endpoints with nonce verification
5. `sidecart.js`: all interactions above
6. `sidecart.scss`: panel layout, items, progress bar, coupon, floating icon
7. Floating icon: position CSS classes, badge count

## Todo

### Popup
- [ ] `includes/features/class-wup-popup.php`
- [ ] `templates/popup/lightbox.php`
- [ ] `public/js/src/popup.js` popup interactions
- [ ] `public/css/src/popup.scss`
- [ ] AJAX `wup_get_popup` with nonce

### Side Cart
- [ ] `includes/features/class-wup-side-cart.php`
- [ ] `templates/side-cart/header.php`
- [ ] `templates/side-cart/items.php`
- [ ] `templates/side-cart/shipping-bar.php`
- [ ] `templates/side-cart/fbt.php`
- [ ] `templates/side-cart/coupon.php`
- [ ] `templates/side-cart/footer.php`
- [ ] `public/js/src/sidecart.js`
- [ ] `public/css/src/sidecart.scss`
- [ ] All 6 AJAX endpoints (nonce-protected)
- [ ] `woocommerce_add_to_cart_fragments` filter
- [ ] Floating cart icon with badge
- [ ] Free shipping progress bar calculation
