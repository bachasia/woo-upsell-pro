# Phase 03: Batch Computation Class

## Context Links
- [plan.md](./plan.md)
- [Phase 02](./phase-02-similarity-index-class.md)
- [class-wup-plugin.php](/Users/bachasia/Data/VibeCoding/woo-upsell-pro/includes/class-wup-plugin.php) -- existing Action Scheduler usage pattern

## Overview
- **Priority**: P1
- **Status**: completed
- **Description**: Chunked background job that builds similarity index for all products without exceeding PHP memory limits

## Key Insights
- Existing codebase already uses Action Scheduler (`as_schedule_single_action`) for embed jobs
- The main memory bottleneck: loading ALL vectors at once. For 5K products x 1536 dims x 8 bytes = ~60MB. Acceptable for background job within 256MB limit.
- Cannot chunk vector loading (need full matrix for dot product), but CAN chunk the products we compute FOR
- Single-product reindex needs to load all vectors too, but only computes one row. Fast enough for sync use.

## Requirements

### Functional
- Full rebuild: process all embedded products in chunks of 500
- Single-product rebuild: recompute one product's similarity row immediately
- Self-scheduling: each chunk schedules the next via Action Scheduler
- Idempotent: safe to run multiple times (REPLACE INTO)
- Cancellable: admin can trigger fresh full rebuild which cancels pending chunks

### Non-functional
- Each chunk completes within 30 seconds
- Peak memory under 200MB for 5K products
- No API calls (vectors already in postmeta)

## Architecture

### Class: `WUP_Similarity_Batch`
Location: `includes/ai/class-wup-similarity-batch.php`

```
WUP_Similarity_Batch
  + CHUNK_SIZE = 500
  + ACTION_CHUNK = 'wup_similarity_build_chunk'
  + ACTION_FULL  = 'wup_similarity_build_all'
  + ACTION_SINGLE = 'wup_similarity_build_single'
  + GROUP = 'wup-similarity'
  + schedule_full_rebuild(): void
  + schedule_single(int $product_id): void
  + handle_chunk(int $offset): void
  + handle_single(int $product_id): void
  - load_all_vectors(): array
  - get_embedded_product_ids(): int[]
  - cancel_pending(): void
```

### Job Flow (Full Rebuild)
```
Admin clicks "Rebuild" (or nightly cron fires)
  -> schedule_full_rebuild()
     -> cancel_pending() (clear stale chunks)
     -> as_schedule_single_action(ACTION_CHUNK, offset=0)

handle_chunk(offset=0):
  -> load_all_vectors() once
  -> get product IDs slice [0..499]
  -> for each: WUP_Similarity_Index::build_for_product()
  -> if more products remain:
     -> as_schedule_single_action(ACTION_CHUNK, offset=500)
  -> else: update_option('wup_similarity_last_full_build', time())
```

### Job Flow (Single Product)
```
Product saved -> schedule_single($product_id)
  -> as_schedule_single_action(ACTION_SINGLE, product_id)

handle_single($product_id):
  -> load_all_vectors()
  -> WUP_Similarity_Index::build_for_product($product_id, $all_vectors)
  -> Also rebuild any product that previously had $product_id in its top-10
     (because the updated product's vector changed, their rankings may shift)
```

**Note on reverse invalidation**: When product X is saved, products that had X in their top-10 might have stale rankings. Two options:
- **Simple (recommended)**: Only rebuild product X. Other products' stale top-10 self-correct on next nightly rebuild. The staleness is cosmetic (X might rank slightly differently for them), not broken.
- **Full**: Also rebuild all products referencing X. Adds complexity + N more writes. YAGNI.

Go with the simple approach.

## Related Code Files

### Files to Create
- `includes/ai/class-wup-similarity-batch.php`

### Files to Modify
- None in this phase (wiring in Phase 05)

## Implementation Steps

1. Create `includes/ai/class-wup-similarity-batch.php`

2. Implement `load_all_vectors()`:
   Reuse the same SQL from `WUP_Similarity_Search::load_all_vectors()`. Since that method is private, duplicate the SQL here. (Alternative: make it protected/public on Search class. But batch class shouldn't depend on Search -- they are peers.)
   ```php
   private static function load_all_vectors(): array {
       global $wpdb;
       $active_model = get_option( 'wup_ai_embedding_model', 'text-embedding-3-small' );

       $rows = $wpdb->get_results( $wpdb->prepare(
           "SELECT pm.post_id, pm.meta_value AS vector
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            LEFT JOIN {$wpdb->postmeta} pm2
                ON pm2.post_id = pm.post_id AND pm2.meta_key = %s
            WHERE pm.meta_key = %s
              AND p.post_type = 'product'
              AND p.post_status = 'publish'
              AND ( pm2.meta_value = %s OR pm2.meta_value IS NULL )",
           WUP_Product_Embedder::META_MODEL,
           WUP_Product_Embedder::META_VECTOR,
           $active_model
       ) );

       $vectors = [];
       foreach ( $rows as $row ) {
           $decoded = json_decode( $row->vector, true );
           if ( is_array( $decoded ) ) {
               $vectors[ (int) $row->post_id ] = $decoded;
           }
       }
       return $vectors;
   }
   ```

3. Implement `schedule_full_rebuild()`:
   ```php
   public static function schedule_full_rebuild(): void {
       self::cancel_pending();
       if ( function_exists( 'as_schedule_single_action' ) ) {
           as_schedule_single_action( time(), self::ACTION_CHUNK, [ 'offset' => 0 ], self::GROUP );
       }
   }
   ```

4. Implement `cancel_pending()`:
   ```php
   private static function cancel_pending(): void {
       if ( function_exists( 'as_unschedule_all_actions' ) ) {
           as_unschedule_all_actions( self::ACTION_CHUNK, null, self::GROUP );
       }
   }
   ```

5. Implement `handle_chunk()`:
   ```php
   public static function handle_chunk( int $offset = 0 ): void {
       $all_vectors = self::load_all_vectors();
       if ( empty( $all_vectors ) ) {
           return;
       }

       $product_ids = array_keys( $all_vectors );
       $chunk       = array_slice( $product_ids, $offset, self::CHUNK_SIZE );

       if ( empty( $chunk ) ) {
           // Done -- record completion time
           update_option( 'wup_similarity_last_full_build', time(), false );
           return;
       }

       foreach ( $chunk as $product_id ) {
           WUP_Similarity_Index::build_for_product( $product_id, $all_vectors );
       }

       // Schedule next chunk if more products remain
       $next_offset = $offset + self::CHUNK_SIZE;
       if ( $next_offset < count( $product_ids ) ) {
           if ( function_exists( 'as_schedule_single_action' ) ) {
               as_schedule_single_action(
                   time() + 5, // 5s delay between chunks to reduce DB pressure
                   self::ACTION_CHUNK,
                   [ 'offset' => $next_offset ],
                   self::GROUP
               );
           }
       } else {
           update_option( 'wup_similarity_last_full_build', time(), false );
       }
   }
   ```

6. Implement `schedule_single()` and `handle_single()`:
   ```php
   public static function schedule_single( int $product_id ): void {
       $args  = [ 'product_id' => $product_id ];
       $group = self::GROUP;

       if ( function_exists( 'as_has_scheduled_action' )
            && as_has_scheduled_action( self::ACTION_SINGLE, $args, $group ) ) {
           return; // already queued
       }

       if ( function_exists( 'as_schedule_single_action' ) ) {
           as_schedule_single_action( time(), self::ACTION_SINGLE, $args, $group );
       }
   }

   public static function handle_single( int $product_id ): void {
       $all_vectors = self::load_all_vectors();
       if ( empty( $all_vectors ) || ! isset( $all_vectors[ $product_id ] ) ) {
           return;
       }
       WUP_Similarity_Index::build_for_product( $product_id, $all_vectors );
   }
   ```

7. Implement `get_embedded_product_ids()` (helper for count display):
   ```php
   public static function count_embedded_products(): int {
       global $wpdb;
       return (int) $wpdb->get_var(
           "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_wup_embedding'"
       );
   }
   ```

## Memory Analysis

For 5K products x 1536 dims:
- PHP array overhead per float: ~72 bytes (zval + hash bucket)
- Per vector: 1536 x 72 = ~110KB
- 5K vectors: ~550MB -- **TOO HIGH**

**CRITICAL FIX**: Store vectors as packed binary or use array of floats without hash keys.

Actually re-checking: `json_decode` produces a numerically-indexed array. PHP 8.1 packed arrays for sequential int keys use ~16 bytes per element (value zval only, no hash bucket).
- Per vector: 1536 x 16 = ~24KB
- 5K vectors: ~120MB
- Plus JSON strings in memory during decode: another ~60MB transiently

**This fits within 256MB but is tight.** Mitigation strategies:
1. `unset($rows)` after decoding to free raw SQL result memory
2. For very large stores (>5K), the chunk approach means we load vectors once per chunk. Each chunk does 500 dot products against all vectors -- that's the computation, not extra memory.
3. If stores exceed 10K products, consider loading vectors in binary format (future optimization, YAGNI for now).

## Todo List
- [x] Create `includes/ai/class-wup-similarity-batch.php`
- [x] Implement `load_all_vectors()` (duplicated SQL)
- [x] Implement `schedule_full_rebuild()` + `cancel_pending()`
- [x] Implement `handle_chunk()` with self-scheduling
- [x] Implement `schedule_single()` + `handle_single()`
- [x] Add memory cleanup (`unset`) after SQL result decode
- [x] Verify file stays under 200 lines
- [x] Test: full rebuild produces rows for all embedded products
- [x] Test: single rebuild updates one row
- [x] Test: chunk scheduling correctly chains

## Success Criteria
- Full rebuild processes all embedded products in chunks
- Each chunk schedules the next automatically
- Peak memory stays under 200MB for 5K products
- Single-product rebuild completes within 5 seconds
- `wup_similarity_last_full_build` option updated on completion

## Risk Assessment
- **Memory**: See analysis above. 5K products is borderline. Add `wp_raise_memory_limit('admin')` call at start of `handle_chunk()` as safety net.
- **Timeout**: 500 products x 5K dot products = 2.5M dot products per chunk. Each dot product is 1536 multiplications. PHP does ~100M float ops/sec. Total: ~38M ops = ~0.4s. Well within 30s limit.
- **Duplicate SQL**: `load_all_vectors()` duplicated from Search class. Acceptable -- extracting to shared utility would couple unrelated classes.

## Security Considerations
- Background jobs run as WP system, no user context needed
- No external API calls
- All DB queries parameterized

## Next Steps
- Phase 04 wires `WUP_Similarity_Index::get_similar()` into `WUP_Similarity_Search::find_similar()`
