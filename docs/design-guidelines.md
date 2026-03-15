# Design Guidelines — Woo Upsell Pro

## Design Philosophy
- **Non-intrusive**: Upsells should feel helpful, not aggressive
- **Store-native**: Match the active WooCommerce theme's colors/fonts automatically
- **Mobile-first**: 60%+ of WooCommerce traffic is mobile
- **Fast**: No layout shift (CLS), animations <300ms

---

## Color System
Plugin inherits WooCommerce theme colors by default. Override variables available.

```css
:root {
  --wup-primary: var(--wc-highlight-color, #7f54b3); /* WC purple fallback */
  --wup-accent: var(--wc-button-background-color, #7f54b3);
  --wup-text: var(--wc-body-text-color, #3d3d3d);
  --wup-bg: #fff;
  --wup-border-radius: 8px;
  --wup-shadow: 0 4px 20px rgba(0,0,0,0.12);
}
```

---

## Frontend UI Components

### 1. Add-to-Cart Popup
**Style:** Slide-in from bottom-right (mobile: full-width bottom sheet)
- Product thumbnail (60×60px) + name + price
- CTA: "View Cart" (primary) + "Continue Shopping" (ghost)
- Auto-dismiss: 5 seconds with progress bar
- Upsell suggestion: 1 product shown below ("Customers also bought…")
- **Max width:** 380px desktop | 100% mobile

### 2. Cart Upsell Widget
**Style:** Bordered card below cart totals, above checkout button
- Heading: "You might also like" or custom text
- Grid: 2-3 product cards (image + name + price + "+ Add" button)
- "+ Add" triggers AJAX, no page reload
- Background: `--wup-bg` with subtle border

### 3. Buy More Save More — Tier Table
**Style:** Table/card showing discount tiers by quantity
- Positioned: Below product quantity input (product page) + below cart table (cart page)
- Columns: Quantity threshold | Discount | Status (active/locked)
- Active tier: highlighted row (bold + accent color)
- Responsive: collapses to compact cards on mobile

```
+----------+----------+---------+
| Quantity | Discount | Status  |
+----------+----------+---------+
| 1        | -        | current |
| 2+       | 5% OFF   | locked  |
| 5+       | 10% OFF  | locked  |
| 10+      | 20% OFF  | locked  |
+----------+----------+---------+
```
- Above table: short heading "Buy more, save more!"
- No progress bar — clean table-only display

---

## Admin UI (React + WP Components)

### Campaign Builder
- **Layout:** WooCommerce-style settings page (extends WC_Settings_Page)
- **Panels:** Sidebar list (campaigns) + main editor (React)
- **Inputs:** @wordpress/components (TextControl, SelectControl, ToggleControl, RangeControl)
- **Typography:** System font stack (WP admin default)
- **Colors:** WP admin color scheme (#2271b1 blue, #d63638 red)

### Settings Page
- Tab: "Upsell Pro" under WooCommerce > Settings
- Sections: General | Cart Popup | Cart Upsell | Buy More Save More | Email
- Clean two-column layout (label left, control right)

---

## Typography
- **Admin:** System font stack (WP default: `-apple-system, BlinkMacSystemFont, "Segoe UI"`)
- **Frontend:** Inherit from theme (`font-family: inherit`)
- **Heading in popup/widget:** 14-16px, font-weight: 600
- **Body/price:** 13-14px, regular weight

---

## Animations
| Trigger | Animation | Duration |
|---------|-----------|----------|
| Popup appear | slide-up + fade-in | 250ms |
| Popup dismiss | fade-out | 200ms |
| Add to cart (widget) | Button spin → checkmark | 300ms |
| Progress bar fill | width ease | 400ms |

---

## Accessibility
- All interactive elements: keyboard navigable
- Popup: focus trap when open, ESC closes
- Color contrast: WCAG AA minimum (4.5:1)
- ARIA labels on all CTA buttons
- Reduced motion: `@media (prefers-reduced-motion)` fallbacks
