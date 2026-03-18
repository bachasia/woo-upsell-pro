# Phase 04: Similarity Search Modification

## Context Links
- [plan.md](./plan.md)
- [Phase 02](./phase-02-similarity-index-class.md)
- [class-wup-similarity-search.php](/Users/bachasia/Data/VibeCoding/woo-upsell-pro/includes/ai/class-wup-similarity-search.php)

## Overview
- **Priority**: P1
- **Status**: completed
- **Description**: Modify `find_similar()` to try O(1) index lookup first, fall back to existing real-time method

## Key Insights
- Existing `find_similar()` has transient caching. Keep that layer -- it still prevents repeated DB hits for index lookups
- The index stores top-10 IDs but `find_similar()` may be called with different `$limit` values. Index always returns 10, caller slices to `$limit`
- Must still apply visibility filtering on index results (products may have become hidden/OOS since index was built)
- Existing `compute_similar()` method stays intact as fallback

## Requirements

### Functional
- Try `WUP_Similarity_Index::get_similar()` first
- If returns non-null, apply visibility filter and return
- If returns null (no row, model mismatch, empty table), fall back to existing `compute_similar()`
- Existing API contract unchanged: `find_similar(int, int, array): int[]`

### Non-functional
- Zero behavioral change for users when table is empty
- O(1) DB query for the happy path

## Architecture

### Modified Flow
```
find_similar($product_id, $limit, $exclude_ids)
  -> check transient cache (existing)
  -> if miss:
     -> TRY: WUP_Similarity_Index::get_similar($product_id)
        -> if non-null:
           -> remove $exclude_ids
           -> filter_visible()
           -> if enough results: return
           -> else: fall through to real-time (index may have stale/hidden products)
     -> FALLBACK: compute_similar() (existing real-time method)
  -> set transient cache
  -> return
```

## Related Code Files

### Files to Modify
- `includes/ai/class-wup-similarity-search.php`

## Implementation Steps

1. Add index lookup to `find_similar()`, between cache check and `compute_similar()`:

   Replace the current block:
   ```php
   $result = self::compute_similar( $product_id, $limit, $exclude_ids );
   ```

   With:
   ```php
   $result = self::try_index_lookup( $product_id, $limit, $exclude_ids );
   if ( null === $result ) {
       $result = self::compute_similar( $product_id, $limit, $exclude_ids );
   }
   ```

2. Add `try_index_lookup()` private method:
   ```php
   /**
    * Attempt O(1) lookup from pre-computed similarity index.
    *
    * Returns null if index has no data for this product (triggers fallback).
    * Returns array (possibly empty) if index had data.
    */
   private static function try_index_lookup( int $product_id, int $limit, array $exclude_ids ): ?array {
       if ( ! class_exists( 'WUP_Similarity_Index' ) ) {
           return null;
       }

       $similar_ids = WUP_Similarity_Index::get_similar( $product_id );
       if ( null === $similar_ids ) {
           return null;
       }

       // Remove excluded IDs
       $similar_ids = array_diff( $similar_ids, $exclude_ids );

       if ( empty( $similar_ids ) ) {
           return [];
       }

       // Apply WC visibility filter (same as real-time path)
       $visible = self::filter_visible( $similar_ids, $limit );

       // If index didn't produce enough visible results, don't fall back --
       // the index represents the best matches available.
       return $visible;
   }
   ```

3. **Decision: Fall back when index has too few visible results?**
   No. If the index has a row but visibility filtering removes most results, returning fewer results is correct behavior. Falling back to real-time would be wasteful and the results would be the same (index contains the same top-10).

## Todo List
- [x] Add `try_index_lookup()` method to `WUP_Similarity_Search`
- [x] Modify `find_similar()` to call it before `compute_similar()`
- [x] Verify `class_exists` guard prevents fatal if index class not loaded
- [x] Test: when index has data, real-time path is NOT called
- [x] Test: when index returns null, real-time path IS called
- [x] Test: exclude_ids correctly removed from index results
- [x] Test: visibility filter applied to index results

## Success Criteria
- `find_similar()` returns same-quality results whether using index or fallback
- No behavioral change when `wp_wup_similar` table is empty
- Existing tests (if any) continue to pass

## Risk Assessment
- **Low risk**: fallback path is the current production code, untouched
- **Edge case**: index row exists but all 10 similar products now hidden. Returns empty array. This is correct -- there genuinely are no visible similar products.

## Security Considerations
- No new user input processing
- `class_exists` check is a safety guard, not security

## Next Steps
- Phase 05 wires hooks (product save, cron, model change)
