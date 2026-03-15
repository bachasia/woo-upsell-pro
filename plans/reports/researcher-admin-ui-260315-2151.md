# WordPress Admin UI Research Report
**Date:** 2026-03-15 | **Focus:** WooCommerce Plugin Admin Development

---

## Executive Summary
Comprehensive research on 10 critical areas for WooCommerce plugin admin UI development. Settings API is standard for plugin pages, WooCommerce uses hooks (tabs/settings), modern approach favors REST API over AJAX, React suits complex interfaces, wp_options for settings, order meta for conversions tracking.

---

## 1. Settings API vs Custom Admin Pages
**RECOMMENDATION: Use Settings API as default**

- **Settings API strengths**: Handles nonces/sanitization automatically, familiar UI consistent with WP core, auto-updated with WP versions, no manual form handling risk
- **Custom pages**: Only when Settings API restrictions prevent required functionality
- **Security**: Settings API auto-validates capabilities (requires manage_options)
- **Trade-off**: Settings API less flexible; custom pages more work but unlimited control

---

## 2. WooCommerce Admin Tab Integration Patterns
**Standard Hooks:**
- Product tabs: `woocommerce_product_data_tabs` + `woocommerce_product_data_panels` (product edit page)
- Settings tabs: `woocommerce_get_settings_pages` (Settings > [Your Tab]) via WC_Settings_Page class
- Integration class: Extend `WC_Integration` for auto-created settings under Settings > Integrations
- Admin page connection: `wc_admin_connect_page()` registers existing PHP pages

**Pattern**: Create class extending WC_Settings_Page, handle save/load via hooks, use WooCommerce helper functions (woocommerce_wp_checkbox, woocommerce_wp_select)

---

## 3. JavaScript Framework Choice
**Modern Admin UI Hierarchy:**
1. **React** (complex interfaces): Block editor built on React, best for interactive dashboards, smooth UX without page reloads
2. **Vanilla JS** (simple interactions): ES6+ now mature, better performance than jQuery, understands underlying APIs
3. **jQuery** (legacy): Still practical for plugins loading jQuery anyway, good for DOM manipulation

**WP Components**: WordPress provides @wordpress/components library (React-based) for plugin pages—use for consistency

---

## 4. REST API vs AJAX Best Practices
**Use REST API (modern standard):**
- Smaller response overhead (doesn't load full WP admin)
- Predictable schema-based responses
- Better HTTP verb support (GET/POST/PUT/DELETE)
- Faster performance (verified by Delicious Brains)
- Proper routing/validation built-in

**Legacy AJAX (admin-ajax.php):** Only maintain existing code. Loads entire admin environment every request, string-based actions, minimal structure.

**Security both**: Require nonce validation, check user capabilities (current_user_can), never allow unauthenticated actions without permission checks

---

## 5. WooCommerce Product Meta Boxes
**Implementation Hooks:**
- Add fields: `woocommerce_product_options_general_product_data` (standard WC functions: woocommerce_wp_checkbox, woocommerce_wp_select)
- Save metadata: `woocommerce_process_product_meta` (don't use save_post; WC handles nonces/validation)
- Visibility: Use classes (show_if_simple, show_if_variable, show_if_virtual) to target product types

**Field tools:**
- Native WooCommerce functions for standard fields
- Meta Box plugin (50+ field types) or JetEngine for advanced needs
- Always sanitize/validate on save and display

---

## 6. Plugin Localization (i18n)
**Core Pattern:**
1. Use __('text', 'your-textdomain') for translatable strings
2. Use _n() for plurals, _x() for context-dependent terms
3. Register domain: load_plugin_textdomain('your-textdomain', false, '/languages')
4. JavaScript: wp_localize_script() for server→JS data, or @wordpress/i18n package (mirrors PHP functions)
5. Extract: .pot file, translators create .po files per language, compiled to .mo at runtime

**Best practice**: Use entire sentences (word order varies), sprintf() for variables (not string concat)

---

## 7. Asset Enqueuing & Conditional Loading
**Hooks:**
- wp_enqueue_scripts (frontend), admin_enqueue_scripts (admin with $hook parameter for targeting)

**Conditional strategies:**
- Use WordPress Conditional Tags: is_front_page(), is_admin(), is_user_logged_in()
- has_block() for Gutenberg blocks
- Custom post types via is_singular('custom-type')
- Admin pages via $hook parameter in admin_enqueue_scripts callback

**Pattern**: Register on init, enqueue conditionally only when needed—reduces HTTP requests, improves Core Web Vitals

---

## 8. Plugin Settings Storage
**wp_options (default, recommended for most):**
- Pros: Built-in Options API, simple key-value, WordPress-standard
- Cons: Not indexed on autoload by default, scales poorly with excessive autoloaded options, wp_options table bloats quickly
- Optimization: Set autoload=no for settings not needed on every page load

**Custom tables (avoid unless necessary):**
- Pros: Better for large datasets, custom indexing
- Cons: Database proliferation, maintenance overhead
- Alternative: Use custom post types with post meta (hybrid approach)

**Recommendation**: wp_options for typical plugin settings; custom tables only if storing thousands of rows regularly

---

## 9. WooCommerce Order Meta for Upsell Tracking
**Implementation:**
- Store upsell data via: update_post_meta($order_id, '_upsell_product_id', $value)
- Retrieve: get_post_meta($order_id, '_upsell_product_id', true)
- Server-side tracking sends order meta to Meta/Google/TikTok pixels
- Data layer: JavaScript object on checkout containing order total, transaction ID, products, quantities

**Tracking approach:**
- Capture at order completion hook: woocommerce_order_status_completed
- Track secondary conversions: upsell accepted, order bump converted
- Use conversion tracking plugins (Pixel Manager for WooCommerce, FunnelKit) or custom implementation via woocommerce_order_data_store hooks

---

## 10. Modern Admin UI Libraries Summary
**CMB2**: Developer toolkit for meta boxes/custom fields. Clean API, 50+ field types, active community. CMB2 Admin Extension allows UI-based field creation (non-code).

**WP Components**: @wordpress/components—React library for admin. Consistent with block editor, recommended for React-based admin pages.

**Meta Box**: Alternative to CMB2, 50+ fields, supports Bricks builder integration.

**Recommendation**: CMB2 for straightforward meta, WP Components for React dashboards, Meta Box if needing builder integration

---

## Key Decision Matrix
| Use Case | Recommendation | Why |
|----------|---|---|
| Plugin settings page | Settings API | Security, consistency, WP-managed |
| WP admin REST endpoints | REST API | Performance, structure, modern |
| Complex interactive dashboard | React + WP Components | Smooth UX, no page reloads |
| Product custom fields | woocommerce_wp_* functions | WC-native, proper save handling |
| Upsell tracking | Order meta + server-side pixel | Accurate conversion attribution |
| Settings storage | wp_options (autoload=no) | Standard, unless >1000 rows |

---

## Unresolved Questions
- Specific FunnelKit vs Pixel Manager performance comparison for order meta tracking?
- Recommended autoload threshold before considering custom tables?
- Does @wordpress/i18n package fully replace wp_localize_script or complement it?

---

## Sources
- [WordPress Settings API - Developer.WordPress.org](https://developer.wordpress.org/plugins/settings/settings-api/)
- [WooCommerce Admin Integration Docs](https://developer.woocommerce.com/docs/integrating-admin-pages-into-woocommerce-extensions)
- [How to Use WordPress React Components - Developer Blog](https://developer.wordpress.org/news/2024/03/how-to-use-wordpress-react-components-for-plugin-pages/)
- [REST API vs admin-ajax.php - Roots](https://roots.io/wordpress-rest-api-vs-admin-ajax-php-the-modern-choice/)
- [WooCommerce Custom Fields Guide - Meta Box](https://docs.metabox.io/tutorials/add-custom-fields-woocommerce/)
- [WordPress i18n Handbook](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/)
- [Conditional Asset Loading - PixelNet](https://www.pixelnet.in/blog/conditionally-enqueue-scripts-in-wordpress/)
- [WP Options vs Custom Tables - Pantheon](https://docs.pantheon.io/optimize-wp-options-table-autoloaded-data)
- [WooCommerce Conversion Tracking Guide - FunnelKit](https://funnelkit.com/woocommerce-conversion-tracking/)
- [CMB2 GitHub](https://github.com/CMB2/CMB2)
