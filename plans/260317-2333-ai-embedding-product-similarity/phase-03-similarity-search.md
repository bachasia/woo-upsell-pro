# Phase 03 — Similarity Search Engine

**Status:** pending
**Priority:** High (blocker for phase 04)
**Depends on:** Phase 02

## Overview

PHP cosine similarity engine. Loads all embedded product vectors, ranks by similarity
to the query product, returns top-N IDs. Results cached via transient (12h, same as
existing source cache pattern).

## Related Code Files

- **New:** `includes/ai/class-wup-similarity-search.php` — core search logic
- `includes/features/class-wup-product-source.php` — will call this in phase 04

## Algorithm

**Cosine similarity:**
```
similarity(A, B) = dot(A, B) / (|A| * |B|)
```
Returns value in [-1, 1]. Higher = more similar.

Since all OpenAI embeddings are L2-normalized, `|A| = |B| = 1`, so:
```
similarity(A, B) = dot(A, B)   // just a dot product — O(1536) multiplications
```

**Query flow:**
1. Load query product's embedding vector
2. Load all other published product embeddings from post_meta (batch fetch)
3. Compute dot product vs query vector for each
4. Sort descending, take top N
5. Exclude OOS + catalog-hidden (same visibility filters as existing SQL)
6. Cache result: `src_emb_{product_id}` transient, 12h

## Performance Analysis

| Catalog size | Vectors loaded | PHP dot product time | Memory |
|---|---|---|---|
| 1,000 products | 6 MB | ~5ms | ~8 MB |
| 5,000 products | 30 MB | ~25ms | ~40 MB |
| 10,000 products | 60 MB | ~50ms | ~80 MB |

Transient cache means this cost is paid once per 12h per product, not per customer visit.
For > 10k products, add pre-filtering by category before loading all vectors.

## Implementation Steps

1. **Create `WUP_Similarity_Search`** (`class-wup-similarity-search.php`):

   ```php
   public static function find_similar(
       int $product_id,
       int $limit = 5,
       array $exclude_ids = []
   ): array // returns int[] product IDs sorted by similarity desc
   ```

2. **Batch-load all embeddings** in single `get_posts` + `get_post_meta` call:
   ```php
   // Fetch all published product IDs that have _wup_embedding meta
   $all_ids = get_posts([
       'post_type'   => 'product',
       'post_status' => 'publish',
       'numberposts' => -1,
       'fields'      => 'ids',
       'meta_key'    => '_wup_embedding',
   ]);
   ```
   Then batch-load meta with a single SQL via `get_post_meta` loop or custom query.

3. **Dot product function** (pure PHP, no extensions required):
   ```php
   private static function dot( array $a, array $b ): float {
       $sum = 0.0;
       foreach ( $a as $i => $v ) {
           $sum += $v * $b[ $i ];
       }
       return $sum;
   }
   ```

4. **Visibility filter**: After ranking, remove OOS + catalog-hidden using
   `wc_get_product_visibility_term_ids()` + `wc_get_product()` status check
   (or pre-filter via term relationship query before loading vectors).

5. **Cache**: Store ranked IDs in transient `wup_emb_{product_id}`, 12h.
   Invalidate on `woocommerce_update_product` (same product only).

## Success Criteria

- [ ] `WUP_Similarity_Search::find_similar(123, 5)` returns 5 product IDs
- [ ] Results are sorted by semantic similarity (Naruto product returns other Naruto products first)
- [ ] OOS products excluded from results
- [ ] Second call hits transient cache (no meta queries)
- [ ] Works on catalog of 1000+ products within 100ms
