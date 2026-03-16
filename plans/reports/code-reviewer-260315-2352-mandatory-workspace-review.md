## Code Review Summary

### Scope
- Files: `woo-upsell-pro.php`, `webpack.config.js`, `includes/**`, `public/class-wup-public.php`, `public/js/src/*.js`, `templates/*.php`, `admin/**/*.php`, `admin/src/**`
- Focus: current workspace snapshot (targeted restore/edit areas)
- Scout findings: used targeted dependency scout (no git history available in current workspace)

### Overall Assessment
- Core structure is coherent and plugin bootstrap is mostly safe.
- Main risks are settings-source inconsistency, REST validation gaps, and one public endpoint exposure.

## 1) Critical issues (must fix)

1. **Email coupon disable toggle can fail silently (wrong source of truth).**
   - `admin/src/components/SettingsPage.js` updates `enable_email_coupon` (top-level), but runtime gate in `includes/features/class-wup-email-coupon.php` uses `email_coupon.enabled` only.
   - Impact: merchant disables feature in one UI but coupons may still be generated/sent.
   - Evidence:
     - `/Users/bachasia/Data/VibeCoding/woo-upsell-pro/admin/src/components/SettingsPage.js`
     - `/Users/bachasia/Data/VibeCoding/woo-upsell-pro/includes/features/class-wup-email-coupon.php`

2. **Public suggest endpoint has no permission callback and can leak campaign-driven suggestions.**
   - `includes/api/class-wup-rest-controller.php` sets `permission_callback => '__return_true'` for `/products/suggest`.
   - It can expose recommendation logic and product relationships to anonymous callers.
   - Impact: data exposure / scraping vector.

## 2) High/medium issues

### High

1. **`update_campaign` validation bypass for `status` unless `type` is provided.**
   - In `includes/campaigns/class-wup-campaign-manager.php`, `validate_campaign_data()` runs only when `type` exists on update.
   - Invalid status can be persisted.

2. **Dual settings systems are inconsistent (WC options vs `wup_settings` array).**
   - WC settings page writes per-option keys (`wup_enable_popup`, `wup_email_coupon_discount_type`, etc.).
   - REST/admin React uses aggregate `wup_settings` structure.
   - Runtime readers mix both patterns; nested settings (especially email coupon fields) can diverge.
   - Affected files:
     - `/Users/bachasia/Data/VibeCoding/woo-upsell-pro/admin/class-wup-settings-page.php`
     - `/Users/bachasia/Data/VibeCoding/woo-upsell-pro/includes/helpers/class-wup-utils.php`
     - `/Users/bachasia/Data/VibeCoding/woo-upsell-pro/includes/features/class-wup-email-coupon.php`
     - `/Users/bachasia/Data/VibeCoding/woo-upsell-pro/includes/api/class-wup-rest-controller.php`

### Medium

1. **Cart upsell max-products setting ignored.**
   - `class-wup-cart-upsell.php` hardcodes `MAX_PRODUCTS = 3` and does not use `cart_upsell_max_products`.

2. **Popup product price fallback may display raw minor-unit value.**
   - `public/js/src/popup.js` uses Store API `details.prices.price` directly as display text fallback.

3. **Localized object duplication for tier table (`wupTierTableData` + `wupTierData`).**
   - Not fatal, but increases mismatch risk and debugging complexity.

## 3) Nice-to-have

1. Normalize on one canonical settings schema and add a migration bridge.
2. Add explicit REST arg schemas (`args`) for route params and body fields.
3. Add feature-level integration tests for:
   - disabling email coupon,
   - invalid campaign status updates,
   - anonymous suggest endpoint behavior.

## 4) Quick patch recommendations

1. **Unify email coupon gate**
   - Gate by a single canonical flag (prefer `wup_settings.email_coupon.enabled`), and ensure both UIs write same field.

2. **Harden suggest endpoint**
   - Require nonce or authenticated capability; alternatively keep public but strictly restrict response to published+visible products and add rate limiting/caching.

3. **Fix campaign update validation**
   - Validate `status` and `discount_tiers` independently on update, even when `type` omitted.

4. **Resolve settings split-brain**
   - Either:
     - use only `wup_settings` and map WC settings page saves into it, or
     - use only individual WC options and remove nested aggregate reads.

5. **Read cart upsell max from settings**
   - Replace hardcoded limit with sanitized `cart_upsell_max_products` at runtime.

### Edge Cases Found by Scout
- Feature flag naming (`bmsm` vs `buy_more_save_more`) is currently bridged in `WUP_Utils::FEATURE_MAP` and works.
- Main edge risk is *source mismatch* (two UIs writing different setting shapes) rather than flag-name mismatch.
- Public route `/products/suggest` can be probed without auth.

### Runtime/Activation checks
- No immediate bootstrap fatal found from static read.
- Could not run PHP lint in this environment (`php` CLI unavailable), so syntax/activation not executed in runtime shell.

### Unresolved questions
1. Should `/products/suggest` be intentionally public for storefront JS, or should it require nonce-authenticated requests only?
2. Which settings system is the canonical source: WC settings options, or `wup_settings` aggregate?
