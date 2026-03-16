# Phase Implementation Report

### Executed Phase
- Phase: phase-01-source-resolver
- Plan: D:/VibeCoding/woo-upsell-pro/plans/260316-0922-clone-salesgen-upsell-features/
- Status: completed

### Files Modified / Created

| File | Lines | Notes |
|------|-------|-------|
| `includes/features/class-wup-product-source.php` | 157 | New — main resolver, hooks, cache |
| `includes/features/class-wup-product-source-query.php` | 201 | New — SQL query builder + term-data helpers (split for size) |
| `includes/features/class-wup-variation-resolver.php` | 135 | New — product card DTOs + variants map |
| `includes/class-wup-plugin.php` | 67 | Modified — added require + `init_hooks()` call |

### Tasks Completed

- [x] `WUP_Product_Source::resolve()` — all 6 source modes (related, cross_sell, upsell, specific, tags, default)
- [x] Term-based modes use raw SQL via `WUP_Product_Source_Query::run()` with absint-sanitised IDs and esc_like keywords
- [x] Exclusion conditions (product_cat / product_tag) processed via `exclusion_ids()` → term-to-product-ID lookup
- [x] Transient cache: `wup_get_transient` / `wup_set_transient`, 12h expiry, key = `src_` + md5
- [x] Cache invalidation: `on_option_updated` flushes `src_` prefix when any `wup_*source*` option changes
- [x] Admin AJAX `wup_clear_transients` with `check_ajax_referer('wup-admin')`
- [x] `WUP_Variation_Resolver::build_variants_map()` — skips out-of-stock variations
- [x] `WUP_Variation_Resolver::build_product_cards()` — accepts WC_Product objects or IDs
- [x] Product card DTO matches spec (id, parent_id, product_type, default_name, url, thumbnail, price, price_html, default_attributes, attributes_empty)
- [x] Plugin bootstrapped via `class-wup-plugin.php`
- [x] Phase todo list updated

### Tests Status
- Type check: n/a (no PHP type-checker configured in project)
- Unit tests: n/a (Phase 08 owns test suite)
- Brace balance: all 4 files balanced (verified by grep count)

### Design Decisions

- `build_term_ids` / `build_exclusion_ids` extracted to `WUP_Product_Source_Query` to keep main file under 200 lines
- `wup_delete_transients_by_prefix('src_')` — note: cache helper auto-prepends `wup_`, so the actual transient prefix matched is `_transient_wup_src_`
- `str_starts_with` / `str_contains` used — requires PHP 8.0+, consistent with existing codebase
- `parent_id` in card DTO: for simple products, set to product's own ID (no parent); for variable products, also the product's own ID (caller receives the parent WC_Product, not a variation)

### Issues Encountered
- None. No file ownership conflicts.

### Next Steps
- Phase 02+ can call `WUP_Product_Source::resolve($id, ['source'=>'related', 'limit'=>5])` directly
- DTO shape should be confirmed against bundle/popup renderer templates when Phase 02 is implemented
- Consider adding `parent_id` distinction for variation-level cards if renderers need the parent vs variation ID split

### Unresolved Questions
- Should `build_product_cards` also accept variation IDs (not just parent product IDs)? Currently handles only parent products. Clarify in Phase 02 if popup renderer needs per-variation cards.
