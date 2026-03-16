# Phase 06 — Announcement Bars + Sales Popups (Social Proof)

**Status:** Done | **Priority:** P1 | **Effort:** M
**Depends on:** Phase 00

---

## Part A — Announcement Bars

### Overview

Two announcement surfaces:
1. **Topbar** — site-wide fixed bar (injected via `wp_footer`)
2. **Product bar** — per-product-page message inside single product summary

Both support: custom text (with shortcodes), bg color, text color, font size, background pattern (4 presets), custom bg image upload.

### Settings

| WUP option | Default | Description |
|-----------|---------|-------------|
| `wup_upsell_announcement_topbar` | `no` | Enable topbar |
| `wup_upsell_announcement_topbar_text` | `` | Bar text (shortcodes allowed) |
| `wup_upsell_announcement_topbar_bgcolor` | `#FFFFFF` | CSS bg color |
| `wup_upsell_announcement_topbar_text_color` | `#FFFFFF` | CSS text color |
| `wup_upsell_announcement_topbar_text_size` | `default` | CSS font-size |
| `wup_upsell_announcement_topbar_bgpattern` | `default` | Pattern key: default/pattern01–04 |
| `wup_upsell_announcement_topbar_bgimage` | `` | Custom uploaded image URL |
| `wup_upsell_announcement_product` | `no` | Enable product bar |
| `wup_upsell_announcement_product_text` | `` | Bar text |
| `wup_upsell_announcement_product_bgcolor` | `#FFFFFF` | CSS bg color |
| `wup_upsell_announcement_product_text_color` | `#FFFFFF` | CSS text color |
| `wup_upsell_announcement_product_text_size` | `default` | CSS font-size |
| `wup_upsell_announcement_product_text_align` | `` | CSS text-align |
| `wup_upsell_announcement_product_bgpattern` | `default` | Pattern key |
| `wup_upsell_announcement_product_bgimage` | `` | Custom uploaded image URL |
| `wup_upsell_announcement_product_priority` | `20` | Hook priority on `woocommerce_single_product_summary` |

### Background Pattern Logic (from salesgen)

The dynamic CSS engine handles `background-pattern` as a special CSS property:
- `default` → no background image override
- `pattern01`–`pattern04` → `background-image: url({assets_url}/images/announcement/patternNN.png)`
- Custom upload (`background-upload`) → `background-image: url({uploaded_url})`

The dynamic CSS engine in `class-wup-assets.php` (Phase 00) must handle these two special property types when iterating settings.

### Files

- `includes/features/class-wup-announcement.php`
- `templates/announcement/topbar.php`
- `templates/announcement/product.php`
- `assets/images/announcement/pattern01.png` (copy from salesgen)
- `assets/images/announcement/pattern02.png`
- `assets/images/announcement/pattern03.png`
- `assets/images/announcement/pattern04.png`

### class-wup-announcement.php

```php
class WUP_Announcement {
  public function __construct( array $options ) { ... }

  // Hook: wp_footer — topbar div
  public function render_topbar(): void

  // Hook: woocommerce_single_product_summary (configurable priority)
  public function render_product_bar(): void
}
```

Templates receive `$options` array and render `<div class="wup-announcement-top">` / `<div class="wup-announcement-product">` with `do_shortcode()` on text content.

---

## Part B — Sales Popups (Social Proof)

### Overview

Timed social-proof notifications: "John D. in New York purchased {product} 3 minutes ago". Virtual customer names + cities from admin-configurable lists. Product source: smart random (store best sellers) or selected products. 3 templates. Configurable position, timing, page targeting.

### Settings

| WUP option | Default | Description |
|-----------|---------|-------------|
| `wup_popup_enable` | `no` | Enable sales popups |
| `wup_popup_template` | `modern` | `modern` / `minimal` / `dark` |
| `wup_popup_pages` | `all` | `all` / `product_cart` / `home_only` |
| `wup_popup_desktop_position` | `bottom_left` | `bottom_left/right` / `top_left/right` |
| `wup_popup_mobile_position` | `bottom_center` | `top_center` / `bottom_center` / `hidden` |
| `wup_popup_product_source` | `smart_random` | `smart_random` / `smart_selected` |
| `wup_popup_selected_products` | `` | Comma-separated product IDs |
| `wup_popup_virtual_names` | (20 names, one per line) | Newline-separated virtual names |
| `wup_popup_virtual_cities` | (20 city+country, one per line) | Newline-separated virtual cities |
| `wup_popup_loop_time` | `5` | Seconds between popups |
| `wup_popup_display_time` | `4` | Seconds popup stays visible |
| `wup_popup_msg_template` | `{{name}} in {{city}} purchased {{product}} {{time}} ago` | Message template |

Message tokens: `{{name}}`, `{{city}}`, `{{product}}`, `{{time}}`

### Files

- `includes/features/class-wup-sales-popup.php`
- `templates/sales-popup/popup.php`
- JS: inline or small script in `public/js/src/popup.js` (extend existing file)

### class-wup-sales-popup.php

```php
class WUP_Sales_Popup {
  public function __construct( array $options ) { ... }

  // Inject popup shell + localized data via wp_footer
  public function render_shell(): void

  // Resolve product list to display (best sellers or selected)
  private function get_products(): array
  // For smart_random: wc_get_products with orderby=popularity, limit=20
  // For smart_selected: parse wup_popup_selected_products IDs

  // Build localized JS data (product list, names, cities, settings)
  private function get_js_data(): array
}
```

### templates/sales-popup/popup.php

Hidden shell div with position/template classes. JS fills it dynamically.

```html
<div id="wup-sales-popup"
     class="wup-sp wup-sp--{template} wup-sp--{desktop_position} wup-sp--mobile-{mobile_position}"
     style="display:none;">
  <div class="wup-sp__image"></div>
  <div class="wup-sp__content">
    <p class="wup-sp__message"></p>
  </div>
  <button class="wup-sp__close">&times;</button>
</div>
```

### Sales Popup JS logic (extend popup.js)

Localized via `wp_localize_script` as `wupSalesPopup`:
```js
{
  products: [ { name, image, url } ],
  names: ['John D.', ...],
  cities: ['New York, USA', ...],
  template: 'modern',
  loop_time: 5,      // seconds
  display_time: 4,   // seconds
  msg_template: '{{name}} in {{city}} purchased {{product}} {{time}} ago',
  pages: 'all'
}
```

JS behavior:
1. On DOM ready: check page targeting (`pages` setting)
2. Start loop: every `loop_time` seconds, pick random product + name + city
3. Format message with tokens, inject into `.wup-sp__message`
4. Show popup for `display_time` seconds then hide
5. `{{time}}` = random "X minutes/hours ago" string (2–48 range)
6. Close on `×` click
7. Mobile: hide popup if `mobile_position == hidden` on small screens

### Page Targeting Logic (PHP side)

```php
// Check before rendering shell
private function should_show(): bool {
  $pages = $this->options['wup_popup_pages'];
  if ( $pages === 'all' )          return true;
  if ( $pages === 'home_only' )    return is_front_page();
  if ( $pages === 'product_cart' ) return is_product() || is_cart();
  return false;
}
```

## Implementation Steps

**Announcements:**
1. Create `class-wup-announcement.php` with two render methods
2. Create `templates/announcement/topbar.php` and `product.php`
3. Copy 4 pattern PNGs from salesgen `assets/images/announcement/`
4. Extend `class-wup-assets.php` dynamic CSS engine to handle `background-pattern` and `background-upload` special properties
5. Register hooks in constructor only when respective enable option is `yes` and text is non-empty

**Sales Popups:**
1. Create `class-wup-sales-popup.php`
2. `get_products()`: smart_random uses `wc_get_products(['orderby'=>'popularity','limit'=>20])`; smart_selected parses stored IDs
3. `render_shell()`: include template, `wp_localize_script` JS data
4. Create `templates/sales-popup/popup.php`
5. Extend `popup.js` with sales popup loop logic
6. CSS for 3 templates (modern/minimal/dark) + position classes in `popup.scss`

## Todo

### Announcements
- [ ] `includes/features/class-wup-announcement.php`
- [ ] `templates/announcement/topbar.php`
- [ ] `templates/announcement/product.php`
- [ ] Copy pattern images to `assets/images/announcement/`
- [ ] Dynamic CSS: handle `background-pattern` and `background-upload` in `class-wup-assets.php`

### Sales Popups
- [ ] `includes/features/class-wup-sales-popup.php`
- [ ] `templates/sales-popup/popup.php`
- [ ] JS loop logic in `popup.js`
- [ ] CSS 3 templates + position classes in `popup.scss`
- [ ] Page targeting `should_show()` check
- [ ] `wc_get_products` best sellers query for smart_random
