# Phase 04 — WUP_Product_Source Integration

**Status:** pending
**Priority:** High
**Depends on:** Phase 03

## Overview

Add `source = 'semantic'` to `WUP_Product_Source`. All upsell features that use
`WUP_Product_Source::resolve()` get AI similarity for free by changing one setting value.
No changes needed in Bundle, Popup, Cart Upsell, Side Cart templates.

## Related Code Files

- `includes/features/class-wup-product-source.php` — add `semantic` branch in `fetch_ids()`
- `admin/class-wup-settings-schema.php` — add `'semantic'` option to all product source selects

## Implementation Steps

1. **Update `fetch_ids()`** in `class-wup-product-source.php`:
   ```php
   if ( 'semantic' === $source ) {
       return WUP_Similarity_Search::find_similar(
           $product_id,
           (int) $args['limit'] + 10,  // fetch extra, slice after exclude filter
           [ $product_id ]             // always exclude self
       );
   }
   ```
   Place this check BEFORE the `term_data` + SQL path — no changes to existing query logic.

2. **Add `require_once`** for similarity search class in `class-wup-product-source.php`.

3. **Update settings schema** — add `'semantic' => 'AI Semantic (Recommended)'` to:
   - `wup_upsell_bundle_source`
   - `wup_upsell_popup_source` (if exists)
   - `wup_upsell_cart_source` (if exists)
   - Any other source selects across the plugin

4. **Graceful degradation**: If `WUP_Similarity_Search::find_similar()` returns empty
   (no embeddings generated yet), fall back to `related` source automatically:
   ```php
   if ( 'semantic' === $source ) {
       $ids = WUP_Similarity_Search::find_similar( ... );
       if ( ! empty( $ids ) ) return $ids;
       // fallback: re-run as 'related'
       $source = 'related';
   }
   ```

## Success Criteria

- [ ] Selecting "AI Semantic" in bundle settings shows semantically related products
- [ ] Selecting "AI Semantic" in any other upsell feature also works
- [ ] Falls back to `related` cleanly when no embeddings exist
- [ ] Existing sources (`related`, `tags`, `cross_sell`, etc.) unaffected
