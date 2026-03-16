# Woo Upsell Pro — Clone SalesGen Upsell Blast (Feature Parity)

**Status:** Planning
**Priority:** P0
**Plugin to clone:** `D:\VibeCoding\salesgen-upsell` (v0.9.21)
**Target plugin:** `D:\VibeCoding\woo-upsell-pro`
**Prefix:** `wup_` (options), `WUP_` (constants), `wup-upsell-pro` (text domain)

---

## Objective

Build `woo-upsell-pro` with exact feature parity to `salesgen-upsell`, using clean architecture, no license dependency, and `wup_` prefix throughout.

---

## Feature Inventory (from salesgen analysis)

| # | Feature | salesgen key prefix | Phase |
|---|---------|-------------------|-------|
| 1 | Plugin bootstrap + WC guard + admin notice | core | 00 |
| 2 | Product Source Resolver (related/category/tag/upsell IDs) | — | 01 |
| 3 | Variation Resolver + product card DTO | — | 01 |
| 4 | Helper utilities (category/tag helpers, input counter) | — | 01 |
| 5 | FBT Bundle — 4 layouts, AJAX add-all, bundle discount | salesgen_upsell_bundle_* | 02 |
| 6 | Product Data Tab (upsell tags + WCPA compat) | — | 02 |
| 7 | Transient cache + invalidation on source change | — | 02 |
| 8 | Post add-to-cart lightbox popup | salesgen_upsell_popup_* | 03 |
| 9 | Slide side cart (full: header/items/shipping-bar/coupon/FBT/icon) | salesgen_upsell_sidecart_* | 03 |
| 10 | Cart upsell block | salesgen_cart_upsell_* | 04 |
| 11 | Thank-you upsell block | salesgen_thankyou_upsell_* | 04 |
| 12 | Related products block | salesgen_related_* | 04 |
| 13 | Buy More Save More (items + subtotal tiers, 2 layouts) | salesgen_bmsm_* / salesgen_buy_more_* | 05 |
| 14 | Internal discount coupons (sgbmsm / sgbundle logic) | — | 05 |
| 15 | Announcement bars (topbar + product page) | salesgen_upsell_announcement_* | 06 |
| 16 | Sales popups (social proof) | salesgen_popup_* | 06 |
| 17 | Email coupon on order | salesgen_coupon_* | 07 |
| 18 | FOMO stock counter | salesgen_fomo_stock_* | 07 |
| 19 | Admin settings page (full schema + dynamic CSS) | salesgen_* | 08 |
| 20 | Shortcodes: wup_upsell, wup_related, wup_cart_upsell, wup_bmsm | — | 08 |
| 21 | Color settings + dynamic CSS generation engine | salesgen_upsell_*_color + css mapping | 08 |
| 22 | QA, compat, security pass | — | 09 |

---

## Architecture

```
woo-upsell-pro/
  woo-upsell-pro.php          # Plugin header + bootstrap
  uninstall.php
  includes/
    class-wup-plugin.php      # Core singleton: hooks, load order
    class-wup-loader.php      # WC guard, activation/deactivation
    class-wup-activator.php
    api/
      class-wup-rest-controller.php
    helpers/
      class-wup-utils.php     # sg_get_product_category_ids, sg_count_inputs, etc.
      class-wup-cache.php     # Transient helpers + prefix-based invalidation
      class-wup-assets.php    # Enqueue + dynamic CSS output
    campaigns/
      class-wup-campaign-cpt.php
      class-wup-campaign-manager.php
    features/
      class-wup-product-source.php  # ProductSourceResolver
      class-wup-variation-resolver.php
      class-wup-bundle.php          # FBT bundle (4 layouts)
      class-wup-popup.php           # Post-ATC lightbox
      class-wup-side-cart.php       # Slide side cart
      class-wup-cart-upsell.php     # Cart upsell block
      class-wup-thankyou-upsell.php # Thank-you block + coupon reveal
      class-wup-related.php         # Related products block
      class-wup-buy-more-save-more.php # BMSM engine
      class-wup-announcement.php    # Announcement bars
      class-wup-sales-popup.php     # Social proof popups
      class-wup-email-coupon.php    # Email coupon on order
      class-wup-fomo-stock.php      # FOMO stock counter
  admin/
    class-wup-admin.php
    class-wup-settings-page.php     # Full settings schema + dynamic CSS
    class-wup-product-fields.php    # Product data tab (upsell tags)
    src/
      index.js
      components/
        SettingsPage.js
        CampaignEditor.js
        CampaignList.js
      api/api-client.js
    build/
      index.js
      index.asset.php
  public/
    class-wup-public.php
    css/src/
      popup.scss
      sidecart.scss
      tier-table.scss
      cart-upsell.scss
    js/src/
      popup.js         # Lightbox + ATC
      sidecart.js      # Side cart interactions
      tier-table.js    # BMSM widget
      cart-upsell.js   # Cart upsell AJAX
    js/build/
      popup.js / popup.asset.php
      sidecart.js / sidecart.asset.php
      tier-table.js / tier-table.asset.php
      cart-upsell.js / cart-upsell.asset.php
  templates/
    bundle/
      layout-1.php    # bundle_1.php equivalent
      layout-2.php
      layout-3.php
      layout-4.php
    bmsm/
      default.php     # bmsm_default.php equivalent
      style4.php      # bmsm_style4.php equivalent
    side-cart/
      header.php
      items.php
      shipping-bar.php
      fbt.php
      coupon.php
      footer.php
    popup/
      lightbox.php    # Post-ATC lightbox
    announcement/
      topbar.php
      product.php
    sales-popup/
      popup.php
    email/
      coupon.php
    quickview/
      content.php
  assets/
    images/
      announcement/
        pattern01.png
        pattern02.png
        pattern03.png
        pattern04.png
      thank-you.png
  webpack.config.js
  package.json
  composer.json
```

---

## Settings Schema (option keys → wup_ prefix)

All `salesgen_*` option keys map to `wup_*`:
- `wup_upsell_bundle_*` ← `salesgen_upsell_bundle_*`
- `wup_upsell_popup_*` ← `salesgen_upsell_popup_*`
- `wup_upsell_sidecart_*` ← `salesgen_upsell_sidecart_*`
- `wup_bmsm_*` / `wup_buy_more_*` ← `salesgen_bmsm_*`
- `wup_cart_upsell_*` ← `salesgen_cart_upsell_*`
- `wup_thankyou_upsell_*` ← `salesgen_thankyou_upsell_*`
- `wup_related_*` ← `salesgen_related_*`
- `wup_coupon_*` ← `salesgen_coupon_*`
- `wup_fomo_stock_*` ← `salesgen_fomo_stock_*`
- `wup_popup_*` (sales popup) ← `salesgen_popup_*`
- `wup_upsell_announcement_*` ← `salesgen_upsell_announcement_*`

Internal coupon slugs: `wupbmsm` (BMSM), `wupbundle` (bundle discount)

---

## Phases

| Phase | File | Status | Priority |
|-------|------|--------|----------|
| 00 | [phase-00-foundation.md](phase-00-foundation.md) | Todo | P0 |
| 01 | [phase-01-source-resolver.md](phase-01-source-resolver.md) | Todo | P0 |
| 02 | [phase-02-fbt-bundle.md](phase-02-fbt-bundle.md) | Todo | P0 |
| 03 | [phase-03-popup-sidecart.md](phase-03-popup-sidecart.md) | Todo | P0 |
| 04 | [phase-04-cart-thankyou-related.md](phase-04-cart-thankyou-related.md) | Todo | P0 |
| 05 | [phase-05-bmsm.md](phase-05-bmsm.md) | Todo | P0 |
| 06 | [phase-06-announcement-sales-popup.md](phase-06-announcement-sales-popup.md) | Todo | P1 |
| 07 | [phase-07-email-coupon-fomo.md](phase-07-email-coupon-fomo.md) | Todo | P1 |
| 08 | [phase-08-admin-ui.md](phase-08-admin-ui.md) | Todo | P1 |
| 09 | [phase-09-qa-compat.md](phase-09-qa-compat.md) | Todo | P0 |

---

## Definition of Done

- All P0 features functional
- No license check / remote call dependency
- All AJAX endpoints nonce-validated
- No unsanitized output in templates
- Settings saved/loaded correctly with `wup_` prefix
- Plugin activates/deactivates cleanly
- Works on WooCommerce 8.x+ / WP 6.x+ / PHP 8.1+
