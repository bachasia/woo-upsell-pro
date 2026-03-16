# Phase 01 — Product Source Resolver + Variation Resolver

**Status:** Todo | **Priority:** P0 | **Effort:** M
**Depends on:** Phase 00

## Overview

Single reusable API for resolving product lists used by ALL features (bundle, popup, cart upsell, thank-you, side-cart FBT, related). Includes variation resolver and product card DTO. Transient-cached per product+source+limit.

## Source Modes (from salesgen)

| Mode key | Description |
|----------|-------------|
| `related` | WC related products |
| `cross_sell` | WC cross-sells of product |
| `upsell` | WC upsells of product |
| `categories` | Products from selected categories |
| `tags` | Products sharing upsell tags (`_wup_tags` meta) |

## Exclusion Conditions (salesgen_upsell_bundle_excludes_conditions)

Each block: product IDs, categories, or tags to exclude. Match mode: `any` or `all`.

## Files to Create

- `includes/features/class-wup-product-source.php` — ProductSourceResolver
- `includes/features/class-wup-variation-resolver.php` — VariationResolver

## Product Card DTO (array)

```php
[
  'id'                 => int,       // variation ID or simple product ID
  'parent_id'          => int,       // parent product ID (same as id for simple)
  'product_type'       => string,    // 'simple' | 'variable'
  'default_name'       => string,    // product title
  'url'                => string,    // product permalink
  'thumbnail'          => string,    // HTML img tag
  'price'              => float,     // display price
  'price_html'         => string,    // WC price HTML
  'default_attributes' => array,     // default variation attributes
  'attributes_empty'   => bool,      // whether variation has unset required attributes
]
```

## class-wup-product-source.php

```php
class WUP_Product_Source {

  // Main entry: resolve products for a given context
  public static function resolve( $product_id, $args = [] ): array
  // $args: source, categories, limit, excludes_conditions, excludes_match, cache_key_suffix

  private static function get_related( $product_id, $limit ): array
  private static function get_cross_sells( $product_id, $limit ): array
  private static function get_upsells( $product_id, $limit ): array
  private static function get_by_categories( $category_ids, $limit, $exclude_id ): array
  private static function get_by_tags( $product_id, $limit ): array  // uses _wup_tags meta
  private static function apply_exclusions( $product_ids, $conditions, $match ): array
  private static function cache_key( $product_id, $args ): string
}
```

## class-wup-variation-resolver.php

```php
class WUP_Variation_Resolver {

  // Build variants map for a list of product IDs
  public static function build_variants_map( $product_ids ): array
  // Returns: [ product_id => [ variation_id => [ attribute => value, ... ], ... ] ]

  // Build product card DTOs for a list of product IDs
  public static function build_product_cards( $product_ids, $args = [] ): array
  // Returns array of product card DTOs (see DTO above)
}
```

## Transient Cache Strategy

- Key format: `wup_src_{md5(product_id + source + categories + limit)}`
- Expiry: 12 hours
- Invalidate on option update hooks:
  - `update_option_wup_upsell_bundle_source` → delete prefix `wup_src_`
  - Same for popup, cart upsell, thankyou, related source options
- Admin AJAX: `wp_ajax_wup_clear_transients` for manual flush button

## Implementation Steps

1. Create `class-wup-product-source.php` with all source methods
2. Implement exclusion filter: check product ID list, categories, tags against conditions
3. Implement transient cache wrap around `resolve()`
4. Register `update_option_*` hooks in constructor for cache invalidation
5. Create `class-wup-variation-resolver.php`:
   - Loop product IDs, get `WC_Product`, build DTOs
   - For variable: gather variations, build attribute map
   - Handle out-of-stock: skip variations with `stock_status != instock`
6. Register admin AJAX `wup_clear_transients`
7. Unit-test resolver with known product fixture

## Todo

- [x] `includes/features/class-wup-product-source.php`
- [x] `includes/features/class-wup-product-source-query.php` (split helper — SQL + term builders)
- [x] `includes/features/class-wup-variation-resolver.php`
- [x] Transient cache with prefix invalidation (`wup_delete_transients_by_prefix('src_')`)
- [x] Option update hooks (`on_option_updated` — matches any `wup_*source*` option)
- [x] Admin AJAX clear transients (`wp_ajax_wup_clear_transients`)
- [x] `includes/class-wup-plugin.php` updated to require files + call `init_hooks()`
- [ ] Verify DTO shape matches what bundle/popup renderers expect (deferred to Phase 02+)
