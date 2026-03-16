# Project Changelog

_All dates in YYYY-MM-DD format._

## 2026-03-16

### Added
- Phase 07 implementation:
  - `includes/features/class-wup-email-coupon.php`
  - `includes/features/class-wup-fomo-stock.php`
  - `templates/email/coupon.php`
- Phase 08 implementation:
  - tabbed settings renderer in `admin/class-wup-settings-page.php`
  - expanded settings schema in `admin/class-wup-settings-schema.php`
  - dynamic CSS schema wiring into `WUP_Assets`

### Changed
- `includes/class-wup-plugin.php` boot sequence now includes:
  - `WUP_Email_Coupon`
  - `WUP_Fomo_Stock`
- Admin settings flow updated to register sanitize callbacks and perform schema-driven saves.

### Security / Compatibility
- HPOS compatibility declaration in `includes/class-wup-loader.php` via WooCommerce `FeaturesUtil::declare_compatibility`.
- Capability checks for privileged AJAX cache/transient clearing paths:
  - `wup_flush_cache` (`manage_options`)
  - `wup_clear_transients` (`manage_woocommerce`)
- BMSM asset enqueue restricted to product/cart contexts.

### Status
- Feature parity plan (Phases 00–09): complete.
