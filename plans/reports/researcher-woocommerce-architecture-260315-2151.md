# WooCommerce Upsell Plugin: Architecture & Implementation Research

**Date:** 2026-03-15 | **Duration:** Comprehensive research across 8 technical areas

---

## 1. WordPress Plugin Architecture (OOP/Hooks)

**OOP Structure (CRITICAL):**
- Use namespaces to avoid function name collisions
- Leverage autoloaders to reduce file loading overhead
- Separate admin/public code with `is_admin()` conditionals
- Apply SOLID principles: dependency injection, strategy pattern, observer pattern
- Keep files <200 lines for maintainability

**Hook System:**
- Actions: execute functions at predetermined points (e.g., `add_action('admin_enqueue_scripts')`)
- Filters: modify data before display/save (e.g., `add_filter('woocommerce_product_data')`)
- Use unique plugin prefixes to prevent conflicts (e.g., `wup_` for woo-upsell-pro)

**Best Practices:**
- Document all hooks thoroughly
- Design for extensibility via hooks from day 1
- Use dependency injection instead of global functions

---

## 2. WooCommerce Checkout/Cart Hooks for Upsells

**Order Bump Locations:**
- Before Place Order button
- After Place Order button
- Bottom of checkout page
- Before payment methods

**Key Hooks:**
- `woocommerce_review_order_before_submit` - before order button
- `woocommerce_review_order_after_submit` - after order button
- `woocommerce_checkout_process` - validate additions
- Cart page: before/after cart table, before proceed

**Conditions Support:**
- Cart subtotal min/max
- Cart total min/max
- Include/exclude cart items
- Customer role-based
- Quantity thresholds

---

## 3. Post-Purchase Upsell (Thank You Page)

**Implementation Approach:**
- Redirect to thank you page, present complementary products
- One-click add-to-cart: instant checkout without re-entry
- **Conversion Rate:** 20-25% (vs 5-7% pre-checkout)
- **AOV Increase:** 10-15% monthly average

**Hook:** `woocommerce_thankyou` - fires on order confirmation page

**Product Selection:** Complements (accessories, warranties, upgrades) outperform related/upsell

**Key Plugin Approaches:**
- Display products at top of thank you page
- One-click upsell with no friction
- Redirect to checkout after thank you if needed

---

## 4. AJAX Add-to-Cart Patterns

**Modern Approach (Store API):**
- POST to `/wc/store/cart/add-item` endpoint (requires nonce/cart token)
- Returns full updated cart state
- Session-based (maintains cookies for SPAs)
- Supports variable products with `pa_` attribute prefix

**Legacy Pattern:**
- WC cart fragments API (admin-ajax.php)
- Triggers `woocommerce_add_to_cart` action
- Fires custom JavaScript events for re-binding

**Implementation Details:**
- Variable products require attribute slugs with `pa_` prefix
- All endpoints return full cart state after modification
- Handle session/cookie management for SPAs carefully

---

## 5. Plugin File Structure Conventions

```
woo-upsell-pro/
├── woo-upsell-pro.php (main entry, header)
├── uninstall.php
├── includes/ (core logic)
│   ├── class-wup-loader.php
│   ├── class-wup-upsell-manager.php
│   └── hooks/ (organized by feature)
├── admin/
│   ├── js/ (wp_enqueue_script)
│   ├── css/ (wp_enqueue_style)
│   └── class-wup-admin.php
├── public/
│   ├── js/
│   ├── css/
│   └── class-wup-public.php
├── languages/ (i18n)
├── assets/ (images, templates)
└── tests/
```

**Asset Enqueue Hooks:**
- `admin_enqueue_scripts` - admin only
- `wp_enqueue_scripts` - frontend only
- Use wp_enqueue_script/style for proper dependency management

---

## 6. WooCommerce Product Bundles API

**REST API Extensions:**
- Adds `bundled_by` property (lists bundle IDs containing product)
- Adds `bundled_items` property (bundled item data)
- Endpoints: `/products/{id}` includes bundle metadata

**Store API Support:**
- Cart endpoints: `cart/add-item`, `cart/update-item`, `cart/remove-item`
- Products endpoint includes `bundle_item_id` in extensions
- Supports programmatic cart updates

**Database:** Custom tables for bundled item storage (v5.0+)

**Filtering:** `woocommerce_bundled_item_group_of_quantity` - override group quantities

**Key Difference:** Bundles ≠ product groups; bundles are "wrapper" products containing other products

---

## 7. Cart Discounts API (Tiered/Quantity-Based)

**Implementation Options:**
1. Product-level: Real-time price updates based on quantity
2. Cart-level: Discounts based on total qty/amount
3. Role-based: Different pricing for user roles
4. Bulk tiers: e.g., 5+ = 10%, 11+ = 25%

**Popular Implementations:**
- Dynamic Pricing & Discounts extension
- Cart-Based Tiered Discounts
- Tiered Pricing Table plugin

**Custom Implementation:**
- Hook: `woocommerce_cart_calculate_fees` - add custom fees/discounts
- Hook: `woocommerce_product_get_price` - modify product price dynamically
- REST API: Tiered pricing plugins extend `/products/` endpoint with pricing tiers

**Key Metric:** Cart upsells can encourage qty increases to unlock tier discounts

---

## 8. WooCommerce Email Customization

**Common Email Hooks:**
- `woocommerce_email_header()` - below header
- `woocommerce_email_order_details()` - below header (duplicates)
- `woocommerce_email_before_order_table()` - above order table
- Custom hooks can be added at any position

**Customization Methods:**
1. **Settings:** WC admin settings (colors, styling only)
2. **Hooks:** Add custom content without layout changes
3. **Template Overrides:** Full HTML control via theme/plugin templates

**Post-Purchase Email Integration:**
- Inject upsell offers via `woocommerce_email_*` hooks
- Include product images, discounts, one-click links
- Track email conversions separately from thank you page

---

## Architecture Recommendations

**Upsell Plugin Core Components:**
1. **Upsell Manager** - CRUD for upsell campaigns
2. **Condition Engine** - evaluate cart/customer conditions
3. **Display Manager** - render at correct hooks
4. **Cart Handler** - AJAX add-to-cart with one-click support
5. **Email Integration** - post-purchase email hooks

**Key Implementation Points:**
- All admin logic in `/admin/` folder
- All frontend logic in `/public/` folder
- Hooks organized in `/includes/hooks/` by feature (checkout, thank-you, email)
- Use custom post type `wup_upsell` for campaign storage
- Leverage Store API for modern AJAX instead of admin-ajax

---

## Unresolved Questions

1. Should plugin support WooCommerce Subscriptions integration?
2. Does upsell need A/B testing/analytics built-in?
3. Payment gateway upsell confirmation (after payment, before confirmation)?
4. Multi-language support priority (wpml/polylang)?
5. Custom fee calculation vs dynamic pricing plugin integration?

---

**Sources:**
- [WordPress Plugin Best Practices](https://developer.wordpress.org/plugins/plugin-basics/best-practices/)
- [WordPress Plugin Architecture: OOP and Design Patterns](https://www.voxfor.com/wordpress-plugin-architecture-oop-design-patterns/)
- [WooCommerce Cart API](https://developer.woocommerce.com/docs/apis/store-api/resources-endpoints/cart/)
- [WooCommerce Store API - Cart Fragments Best Practices](https://developer.woocommerce.com/2023/06/16/best-practices-for-the-use-of-the-cart-fragments-api/)
- [UpsellWP Thank You Upsell Documentation](https://docs.upsellwp.com/campaigns/thank-you-page-upsells/)
- [Post Purchase Upsells - PeachPay Guide](https://peachpay.app/guides/2025/07/24/add-post-purchase-upsells-woocommerce/)
- [WooCommerce Project Structure](https://developer.woocommerce.com/docs/getting-started/project-structure/)
- [WooCommerce Product Bundles REST API Reference](https://woocommerce.com/document/bundles-rest-api-reference/)
- [WooCommerce Tiered Pricing Table Documentation](https://woocommerce.com/document/tiered-pricing-table/)
- [How to Customize WooCommerce Emails via Hooks](https://yaycommerce.com/how-to-customize-email-template-with-woocommerce-email-hooks/)
