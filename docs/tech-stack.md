# Tech Stack — Woo Upsell Pro

**Target:** WordPress 6.4+ / WooCommerce 8.0+
**PHP:** 8.0+ (namespaces, typed properties, match expressions)
**License Model:** Annual (per-site)

## Backend (PHP)
| Decision | Choice | Rationale |
|----------|--------|-----------|
| Architecture | OOP + namespaces + PSR-4 autoloader | Maintainable, no conflicts |
| Storage (settings) | `wp_options` (autoload=no) | Standard WP, no table bloat |
| Storage (campaigns) | Custom Post Type `wup_campaign` | WP-native CRUD, REST-ready |
| Storage (tracking) | Order meta (`_wup_*`) | Per-order upsell attribution |
| API (admin) | WP REST API (`/wp/v2/`) + custom routes | Modern, structured, no admin-ajax |
| Cart operations | WC Store API (`/wc/store/v1/`) | HPOS-compatible, modern |
| Email | WC email hooks + `wp_mail()` | Native, no 3rd-party dependency |
| Discount engine | `woocommerce_cart_calculate_fees` + `woocommerce_product_get_price` | WC-native hooks |

## Frontend (JavaScript)
| Area | Choice | Rationale |
|------|--------|-----------|
| Admin dashboard | React + `@wordpress/components` | Consistent with block editor |
| Frontend widgets | Vanilla JS (ES6+) | Lightweight, no jQuery dep |
| Build tool | `@wordpress/scripts` (webpack) | WP-standard, zero config |
| CSS | SCSS → compiled | Admin: WP design tokens; Frontend: scoped |
| AJAX (cart) | WC Store API fetch | Modern, HPOS-compatible |

## WooCommerce Integration
| Feature | Hook/API |
|---------|----------|
| Add-to-cart popup | `woocommerce_add_to_cart` (JS event) |
| Cart upsell widget | `woocommerce_cart_collaterals` |
| Buy More Save More | `woocommerce_cart_calculate_fees`, `woocommerce_before_cart_table` |
| Post-purchase email | `woocommerce_order_status_processing` |
| Campaign targeting | `woocommerce_get_cart_item_from_session` |

## File Structure
```
woo-upsell-pro/
├── woo-upsell-pro.php          # Plugin header + bootstrap
├── uninstall.php
├── includes/
│   ├── class-wup-loader.php    # Hook registration
│   ├── class-wup-activator.php
│   ├── campaigns/
│   │   ├── class-wup-campaign-cpt.php
│   │   └── class-wup-campaign-manager.php
│   ├── features/
│   │   ├── class-wup-cart-upsell.php
│   │   ├── class-wup-popup.php
│   │   ├── class-wup-buy-more-save-more.php
│   │   └── class-wup-email-coupon.php
│   ├── api/
│   │   └── class-wup-rest-controller.php
│   └── helpers/
│       └── class-wup-utils.php
├── admin/
│   ├── class-wup-admin.php
│   ├── src/                    # React source
│   └── build/                  # Compiled assets
├── public/
│   ├── class-wup-public.php
│   ├── js/                     # Compiled frontend JS
│   └── css/
├── languages/
└── tests/
```

## Plugin Metadata
- **Slug:** `woo-upsell-pro`
- **Prefix:** `wup_`
- **Text domain:** `woo-upsell-pro`
- **Requires WooCommerce HPOS:** Compatible
- **Min PHP:** 8.0
- **Min WP:** 6.4
- **Min WC:** 8.0
