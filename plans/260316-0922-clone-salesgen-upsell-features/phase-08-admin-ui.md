# Phase 08 — Admin UI (Full Settings Page + Dynamic CSS)

**Status:** Done | **Priority:** P1 | **Effort:** L
**Depends on:** Phase 00 (settings skeleton)

## Overview

Complete the admin settings page with all tab sections, full settings schema (mirroring all `salesgen_*` options as `wup_*`), dynamic CSS generation engine, and all shortcode registrations. Also covers the React-based admin UI build for the campaign editor.

---

## Settings Page Architecture

Settings are registered via the standard WordPress Settings API (same pattern as salesgen). The `SalesGen_Settings` class pattern is replicated as `WUP_Settings_Page`.

### Tab Sections

| Tab slug | Label | Settings group |
|----------|-------|----------------|
| `wup-bundle` | FBT Bundle | bundle + FBT badges |
| `wup-popup` | Add-to-Cart Popup | popup lightbox |
| `wup-sidecart` | Side Cart | side cart + shipping bar + icon |
| `wup-bmsm` | Buy More Save More | BMSM tiers + display |
| `wup-cart` | Cart & Thank-you | cart upsell + thankyou upsell + related |
| `wup-coupon` | Coupons | email coupon + advanced coupon |
| `wup-announcement` | Announcements | topbar + product bar |
| `wup-sales-popup` | Sales Popups | social proof popups |
| `wup-advanced` | Advanced | FOMO stock + image variants + cache flush |

---

## Full Settings Schema

The schema lives in `admin/class-wup-settings-page.php` as a method `get_schema()` returning the full field array. Each field:

```php
[
  'id'      => 'wup_option_name',       // option key
  'name'    => 'Human Label',            // display label
  'type'    => 'checkbox|text|select|textarea|number|color|image|product_search',
  'default' => '...',
  'desc'    => 'Helper text',
  'options' => [],                       // for select fields
  'css'     => 'selector|property',     // drives dynamic CSS engine
]
```

### Complete field list by section

**FBT Bundle** (mapped from salesgen_upsell_bundle_* + salesgen_fbt_*):
- `wup_upsell_bundle_enable` checkbox
- `wup_upsell_bundle_position` select (all WC hooks)
- `wup_upsell_bundle_priority` number
- `wup_upsell_bundle_heading` text
- `wup_upsell_bundle_layout` select (1/2/3/4)
- `wup_upsell_bundle_source` select (related/cross_sell/upsell/categories/tags)
- `wup_upsell_bundle_categories` (multi-select or product_search style)
- `wup_upsell_bundle_prefix` text (`[FBT]`)
- `wup_upsell_bundle_hide_all_options` checkbox
- `wup_upsell_bundle_hide_options_when` number
- `wup_upsell_bundle_excludes_conditions_match` select (any/all)
- `wup_upsell_bundle_excludes_conditions` textarea (JSON)
- `wup_upsell_bundle_add_action_label` text
- `wup_upsell_bundle_discount_amount` number
- `wup_fbt_heading_text` text
- `wup_fbt_badges_enable` checkbox
- `wup_fbt_badges_text` text
- `wup_fbt_badges_bgcolor` color → CSS `:root|--wup-fbt-badge-bg-color`

**Post-ATC Popup** (mapped from salesgen_upsell_popup_*):
- `wup_upsell_popup_enable` checkbox
- `wup_upsell_popup_source` select
- `wup_upsell_popup_categories` category select
- `wup_upsell_popup_limit` number
- `wup_upsell_popup_heading_text` text
- `wup_upsell_popup_products_layout` select
- `wup_upsell_popup_hide_items` checkbox
- `wup_upsell_popup_hide_options` checkbox
- `wup_upsell_popup_add_action_label` text
- `wup_upsell_image_variants` checkbox
- `wup_upsell_product_title_color` color → CSS
- `wup_upsell_product_regular_price_color` color → CSS
- `wup_upsell_product_sale_price_color` color → CSS
- `wup_upsell_add_action_color` color → CSS (multiple targets)
- `wup_upsell_add_action_label_color` color → CSS
- `wup_upsell_checkout_button_color` color → CSS
- `wup_upsell_checkout_button_text_color` color → CSS
- `wup_upsell_viewcart_button_color` color → CSS
- `wup_upsell_viewcart_button_text_color` color → CSS
- `wup_upsell_popup_product_title_size` select → CSS

**Side Cart** (mapped from salesgen_upsell_sidecart_* + salesgen_sidecart_*):
- `wup_upsell_sidecart_enable` checkbox
- `wup_upsell_sidecart_open_selector` text
- `wup_upsell_sidecart_font` select
- `wup_upsell_sidecart_checkout_label` text
- `wup_upsell_sidecart_primary_color` color → CSS `:root|--wup-sc-color-primary`
- `wup_upsell_sidecart_icon_enable` checkbox
- `wup_upsell_sidecart_icon_bgcolor` color
- `wup_upsell_sidecart_icon_color` color
- `wup_upsell_sidecart_icon_position` select
- `wup_upsell_sidecart_icon_size` select
- `wup_sidecart_fsg_enable` checkbox
- `wup_sidecart_fsg_type` select (amount/count)
- `wup_sidecart_fsg_amount` number
- `wup_sidecart_fsg_msg_progress` text
- `wup_sidecart_fsg_msg_success` text
- `wup_sidecart_fsg_color` color → CSS `.wup-fsg-progress>span|background-color`
- `wup_sidecart_fsg_bg_color` color → CSS `.wup-fsg-progress|background`

**BMSM** (mapped from salesgen_bmsm_* + salesgen_buy_more_*):
- `wup_bmsm_enable` checkbox
- `wup_bmsm_position` select
- `wup_bmsm_priority` number
- `wup_bmsm_conditional` select (items/amounts)
- `wup_buy_more_by_items` textarea (JSON)
- `wup_buy_more_by_amounts` textarea (JSON)
- `wup_bmsm_heading_enable` checkbox
- `wup_bmsm_heading` text
- `wup_bmsm_subtitle` text
- `wup_bmsm_heading_icon` select
- `wup_bmsm_style` select (style1/style4)
- `wup_bmsm_combie` checkbox
- `wup_bmsm_hide_congrats` checkbox
- `wup_bmsm_hide_remain` checkbox
- `wup_bmsm_categories` multi-category
- `wup_bmsm_add_cart_button` checkbox
- `wup_bmsm_add_action_label` text
- `wup_bmsm_congrats_items` textarea
- `wup_bmsm_congrats_subtotal` textarea
- `wup_bmsm_remain_items` textarea
- `wup_bmsm_remain_subtotal` textarea

**Cart & Thank-you** (mapped from salesgen_cart_upsell_* + salesgen_thankyou_* + salesgen_related_*):
- All enable/source/categories/limit/hide_options/excludes fields for cart, thankyou, related
- `wup_related_position` select + `wup_related_priority` number

**Coupons** (salesgen_coupon_* + salesgen_advanced_coupons_*):
- `wup_coupon_enable` checkbox
- `wup_coupon_amount` number
- `wup_coupon_code` text
- `wup_coupon_email_subject` text
- `wup_coupon_email_content` textarea
- `wup_advanced_coupons_one` checkbox

**Announcements** (salesgen_upsell_announcement_*):
- All topbar and product bar fields (colors, text, pattern, image upload)

**Sales Popups** (salesgen_popup_*):
- All sales popup fields

**Advanced**:
- `wup_fomo_stock_enable` checkbox
- `wup_fomo_stock_msg` text
- `wup_fomo_stock_min` number
- `wup_fomo_stock_max` number
- `wup_fomo_stock_color` color → CSS `.wup-fomo-stock|color`
- Cache flush button (admin AJAX)

---

## Dynamic CSS Engine (class-wup-assets.php)

Called on `wp_enqueue_scripts`, iterates all schema fields with `css` key:

```php
private function build_inline_css(): string {
  $css = '';
  foreach ( $this->get_all_css_fields() as $field ) {
    $value = get_option( $field['id'], $field['default'] ?? '' );
    if ( empty($value) || $value === 'default' ) continue;

    $rules = is_array($field['css']) ? $field['css'] : [ $field['css'] ];

    foreach ( $rules as $rule ) {
      $parts    = explode('|', $rule);
      $selector = $parts[0];
      $property = $parts[1];
      // $parts[2] optional priority flag

      if ( $property === 'background-pattern' ) {
        $css .= "$selector { background-image: url({$field['path']}{$value}.png); }";
      } elseif ( $property === 'background-upload' ) {
        $css .= "$selector { background-image: url($value); }";
      } else {
        $css .= "$selector { $property: $value; }";
      }
    }
  }
  return $css;
}
```

Output via `wp_add_inline_style( 'wup-frontend', $css )`.

---

## Shortcode Registrations (all phases consolidated)

| Shortcode | Class | Registered in |
|-----------|-------|--------------|
| `[wup_upsell]` | `WUP_Bundle` | Phase 02 |
| `[wup_related]` | `WUP_Related` | Phase 04 |
| `[wup_cart_upsell]` | `WUP_Cart_Upsell` | Phase 04 |
| `[wup_bmsm]` | `WUP_BuyMoreSaveMore` | Phase 05 |

---

## Admin JS Build (React)

Minimal React app for the campaign editor (from existing scaffold):
- `admin/src/index.js` — entry, renders `<SettingsPage />` or `<CampaignEditor />`
- `admin/src/components/SettingsPage.js` — wraps native WP settings form
- `admin/src/components/CampaignEditor.js` — campaign create/edit form
- `admin/src/components/CampaignList.js` — list campaigns
- `admin/src/api/api-client.js` — REST API calls to `wup/v1/*`

Build output: `admin/build/index.js`, `admin/build/index.asset.php`

---

## Implementation Steps

1. Complete `admin/class-wup-settings-page.php` with full `get_schema()` method
2. Register all settings fields via WordPress Settings API in `admin_init`
3. Render tabbed settings page using `do_settings_sections()` per tab
4. Finalize `class-wup-assets.php` dynamic CSS engine with all CSS-mapped fields
5. Add admin AJAX handler for cache flush button
6. Register all shortcodes in respective feature classes (already planned in each phase)
7. Build React admin JS: `npm run build` via webpack
8. Enqueue `admin/build/index.js` only on plugin settings pages
9. Pass settings nonce + REST URL to admin JS via `wp_localize_script`

## Todo

- [x] Full `get_schema()` in `admin/class-wup-settings-schema.php` (~70 fields, 9 tabs)
- [x] WordPress Settings API registration for all fields
- [x] Tabbed admin page rendering (native PHP, WP Settings API)
- [x] Dynamic CSS engine handles all CSS-mapped fields (pushed to WUP_Assets)
- [x] Admin AJAX cache flush button (`wup_flush_cache`)
- [ ] Webpack build for admin React JS (requires npm — deferred)
- [x] All shortcodes registered in respective feature classes
- [x] Settings save/load verified with `wup_` prefix
