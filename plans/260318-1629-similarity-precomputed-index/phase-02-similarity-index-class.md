# Phase 02: WUP_Similarity_Index Class

## Context Links
- [plan.md](./plan.md)
- [Phase 01](./phase-01-db-table-creation.md)
- [class-wup-similarity-search.php](/Users/bachasia/Data/VibeCoding/woo-upsell-pro/includes/ai/class-wup-similarity-search.php)
- [class-wup-product-embedder.php](/Users/bachasia/Data/VibeCoding/woo-upsell-pro/includes/ai/class-wup-product-embedder.php)

## Overview
- **Priority**: P1
- **Status**: completed
- **Description**: New class responsible for reading/writing pre-computed similarity data to `wp_wup_similar`

## Key Insights
- Must reuse existing `load_all_vectors()` SQL pattern from `WUP_Similarity_Search`
- Dot product math already exists in `WUP_Similarity_Search::dot()` -- extract or duplicate (small function, DRY vs coupling tradeoff -- duplicate is fine for 4 lines)
- Store top-10 similar IDs WITH scores so future UI can show similarity percentage
- Model/dims stored per row to detect stale entries when settings change

## Requirements

### Functional
- `get_similar($product_id)`: return array of similar product IDs from table, or null if no row
- `build_for_product($product_id, $all_vectors)`: compute + upsert one product's similarity row
- `invalidate($product_id)`: delete row for a product
- `invalidate_all()`: truncate table (model change)
- `count_indexed()`: return row count
- `get_last_computed()`: return most recent `computed_at` value

### Non-functional
- `get_similar()` must be single SQL query (O(1))
- No API calls -- this class only works with already-stored vectors

## Architecture

### Class: `WUP_Similarity_Index`
Location: `includes/ai/class-wup-similarity-index.php`

```
WUP_Similarity_Index
  + TABLE_NAME = 'wup_similar'
  + TOP_N = 10
  + get_similar(int $product_id): ?array
  + build_for_product(int $product_id, array $all_vectors): bool
  + invalidate(int $product_id): void
  + invalidate_all(): void
  + count_indexed(): int
  + get_last_computed(): ?string
  - table_name(): string
  - dot(array $a, array $b): float
```

All methods static (matches existing codebase pattern: `WUP_Similarity_Search`, `WUP_Product_Embedder`).

### Data Format in `similar_ids` Column
JSON array of `[product_id, score]` pairs, sorted by score desc:
```json
[[42, 0.9523], [87, 0.9201], [15, 0.8844]]
```

This lets `get_similar()` return just IDs, but the scores are available for future use.

## Related Code Files

### Files to Create
- `includes/ai/class-wup-similarity-index.php`

### Files to Modify
- None in this phase (wiring happens in Phase 05)

## Implementation Steps

1. Create `includes/ai/class-wup-similarity-index.php` with class guard pattern matching existing AI classes

2. Implement `table_name()`:
   ```php
   private static function table_name(): string {
       global $wpdb;
       return $wpdb->prefix . 'wup_similar';
   }
   ```

3. Implement `get_similar()`:
   ```php
   public static function get_similar( int $product_id ): ?array {
       global $wpdb;
       $table = self::table_name();
       $active_model = get_option( 'wup_ai_embedding_model', 'text-embedding-3-small' );

       $row = $wpdb->get_row( $wpdb->prepare(
           "SELECT similar_ids, model FROM {$table} WHERE product_id = %d",
           $product_id
       ) );

       if ( ! $row ) {
           return null;
       }

       // Stale model = treat as missing (fallback to real-time)
       if ( $row->model !== $active_model ) {
           return null;
       }

       $pairs = json_decode( $row->similar_ids, true );
       if ( ! is_array( $pairs ) ) {
           return null;
       }

       // Extract just the IDs from [id, score] pairs
       return array_map( fn( $pair ) => (int) $pair[0], $pairs );
   }
   ```

4. Implement `build_for_product()`:
   ```php
   /**
    * Compute and store top-N similar products for one product.
    *
    * @param int   $product_id   Target product
    * @param array $all_vectors  Map of product_id => float[] (pre-loaded)
    * @return bool True if row written
    */
   public static function build_for_product( int $product_id, array $all_vectors ): bool {
       if ( ! isset( $all_vectors[ $product_id ] ) ) {
           return false;
       }

       $query_vec = $all_vectors[ $product_id ];
       $scores    = [];

       foreach ( $all_vectors as $pid => $vec ) {
           if ( $pid === $product_id ) {
               continue;
           }
           $scores[ $pid ] = self::dot( $query_vec, $vec );
       }

       if ( empty( $scores ) ) {
           return false;
       }

       arsort( $scores );
       $top = array_slice( $scores, 0, self::TOP_N, true );

       // Build [id, score] pairs
       $pairs = [];
       foreach ( $top as $pid => $score ) {
           $pairs[] = [ $pid, round( $score, 4 ) ];
       }

       global $wpdb;
       $table = self::table_name();
       $model = get_option( 'wup_ai_embedding_model', 'text-embedding-3-small' );
       $dims  = count( $query_vec );

       // REPLACE INTO = upsert on PRIMARY KEY
       $wpdb->replace( $table, [
           'product_id'  => $product_id,
           'similar_ids' => wp_json_encode( $pairs ),
           'model'       => $model,
           'dims'        => $dims,
           'computed_at'  => current_time( 'mysql', true ),
       ], [ '%d', '%s', '%s', '%d', '%s' ] );

       return true;
   }
   ```

5. Implement `invalidate()`:
   ```php
   public static function invalidate( int $product_id ): void {
       global $wpdb;
       $wpdb->delete( self::table_name(), [ 'product_id' => $product_id ], [ '%d' ] );
   }
   ```

6. Implement `invalidate_all()`:
   ```php
   public static function invalidate_all(): void {
       global $wpdb;
       $wpdb->query( "TRUNCATE TABLE " . self::table_name() );
   }
   ```

7. Implement `count_indexed()` and `get_last_computed()`:
   ```php
   public static function count_indexed(): int {
       global $wpdb;
       return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table_name() );
   }

   public static function get_last_computed(): ?string {
       global $wpdb;
       $val = $wpdb->get_var(
           "SELECT MAX(computed_at) FROM " . self::table_name()
       );
       return $val && $val !== '0000-00-00 00:00:00' ? $val : null;
   }
   ```

8. Implement `dot()` (same as existing):
   ```php
   private static function dot( array $a, array $b ): float {
       $sum = 0.0;
       foreach ( $a as $i => $v ) {
           $sum += $v * ( $b[ $i ] ?? 0.0 );
       }
       return $sum;
   }
   ```

## Todo List
- [x] Create `includes/ai/class-wup-similarity-index.php`
- [x] Implement all public methods
- [x] Implement private helpers
- [x] Verify file stays under 200 lines
- [x] Test: `get_similar()` returns null when table empty
- [x] Test: `build_for_product()` writes correct data
- [x] Test: `get_similar()` returns null when model mismatches
- [x] Test: `invalidate()` removes row

## Success Criteria
- All methods work with the `wp_wup_similar` table from Phase 01
- `get_similar()` is a single SQL query
- `build_for_product()` correctly stores top-10 pairs with scores
- Model mismatch returns null (triggers fallback)

## Risk Assessment
- **REPLACE INTO**: safe because PK is `product_id` and table has no auto-increment. No race condition.
- **Memory**: `$all_vectors` passed in, not loaded here. Caller (batch class) controls memory.
- **File size**: estimated ~150 lines. Under 200 limit.

## Security Considerations
- All DB queries use `$wpdb->prepare()` or `$wpdb->replace()` (parameterized)
- No user input processed directly

## Next Steps
- Phase 03 uses `build_for_product()` in chunked batch loop
