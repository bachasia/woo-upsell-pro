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
1. Load query product's embedding vector (via `WUP_Product_Embedder::get_embedding()`)
2. Load all other published product embeddings — **only those with matching `_wup_embedding_model`**
3. Compute dot product vs query vector for each
4. Sort descending, take top N
5. Exclude OOS + catalog-hidden (same visibility filters as existing SQL)
6. Cache result: `wup_emb_{product_id}` transient, 12h

> **Cache staleness:** Cache is invalidated only when the same product is saved. If another
> product updates its embedding, existing cached results may be stale until next 12h expiry.
> This is intentional — acceptable for a recommendation system.

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

   **Early return when query product has no embedding:**
   ```php
   $query_vector = WUP_Product_Embedder::get_embedding( $product_id );
   if ( null === $query_vector ) return []; // not yet embedded or model mismatch
   ```
   This must be the first check — prevents crash and lets Phase 04 fallback to `related`.

2. **Batch-load all embeddings** via single custom SQL (avoids `get_posts()` double-JOIN anti-pattern):
   ```php
   global $wpdb;
   $active_model = get_option( 'wup_ai_embedding_model', 'text-embedding-3-small' );

   // LEFT JOIN on model meta — include legacy embeddings without _wup_embedding_model
   // (INNER JOIN would silently exclude products embedded before versioning was added)
   $rows = $wpdb->get_results( $wpdb->prepare(
       "SELECT pm.post_id, pm.meta_value AS vector
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        LEFT  JOIN {$wpdb->postmeta} pm2 ON pm2.post_id = pm.post_id
                                        AND pm2.meta_key = '_wup_embedding_model'
        WHERE pm.meta_key   = '_wup_embedding'
          AND p.post_type   = 'product'
          AND p.post_status = 'publish'
          AND ( pm2.meta_value = %s OR pm2.meta_value IS NULL )",
       $active_model
   ) );
   // Result: array of {post_id, vector} — decode vector inline
   $vectors = [];
   foreach ( $rows as $row ) {
       $vectors[ (int) $row->post_id ] = json_decode( $row->vector, true );
   }
   ```
   **Why LEFT JOIN (not INNER JOIN) on `_wup_embedding_model`:**
   - INNER JOIN silently excludes legacy products embedded before versioning was introduced
   - LEFT JOIN + NULL fallback includes them — acceptable since same default model was always used

   **Why custom SQL instead of `get_posts()` + meta loop:**
   - `get_posts()` with dual `meta_query` = 2 JOINs on `wp_postmeta` (slow)
   - Separate `get_post_meta()` loop after = N+1 queries
   - Single SQL above fetches IDs + vectors in **one round-trip**

   **For catalogs > 10k:** Add a pre-filter by category via `wp_term_relationships` JOIN
   before the similarity scan. Reduces vectors loaded from 60 MB to ~10-15 MB.

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

4. **Visibility filter** — hybrid approach (SQL model-filter only, WC API for top-N):
   Avoid adding visibility JOINs to the SQL — coupling to WC internal schema + complex query.
   Instead, post-filter only the top candidates:
   ```php
   // After dot product ranking, take top 20 candidates
   arsort( $scores );
   $candidates = array_slice( array_keys( $scores ), 0, 20, true );

   // WC API check on 20 products max — fast, cached, correct for all WC settings
   $hide_oos = 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' );
   $valid = [];
   foreach ( $candidates as $id ) {
       $product = wc_get_product( $id );
       if ( ! $product ) continue;
       if ( ! $product->is_visible() ) continue;                        // respects catalog_visibility
       if ( $hide_oos && ! $product->is_in_stock() ) continue;          // respects store OOS setting
       $valid[] = $id;
       if ( count( $valid ) >= $limit ) break;
   }
   // Note: filter OOS conditionally — if store shows OOS in catalog, similarity should too
   ```
   **Why not pre-filter in SQL:**
   - Adding visibility JOINs = 4-5 JOINs + correlated subquery → query planner may do worse
   - WC visibility is more than one term (`catalog_visibility`, OOS global setting, etc.)
   - WC has changed visibility internals once before → SQL coupling = maintenance risk
   - 20x `wc_get_product()` calls are fast (WC object cache) and always correct

5. **Cache**: Store ranked IDs in transient `wup_emb_{product_id}`, 12h.
   Invalidate on `woocommerce_update_product` (same product only).

## Success Criteria

- [ ] `WUP_Similarity_Search::find_similar(123, 5)` returns 5 product IDs
- [ ] Results are sorted by semantic similarity (Naruto product returns other Naruto products first)
- [ ] OOS products excluded from results
- [ ] Second call hits transient cache (no meta queries)
- [ ] Works on catalog of 1000+ products within 100ms
