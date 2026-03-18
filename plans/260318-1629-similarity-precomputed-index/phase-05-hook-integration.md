# Phase 05: Hook Integration

## Context Links
- [plan.md](./plan.md)
- [class-wup-plugin.php](/Users/bachasia/Data/VibeCoding/woo-upsell-pro/includes/class-wup-plugin.php) -- `init_ai_hooks()`

## Overview
- **Priority**: P1
- **Status**: completed
- **Description**: Wire up Action Scheduler hooks, product save triggers, nightly cron, and model-change detection

## Key Insights
- Existing `init_ai_hooks()` already has the `wup_embed_product` action that fires after embed completes and invalidates cache. Perfect place to also trigger single-product index rebuild.
- Must require new files BEFORE `init_ai_hooks()` so classes are available
- Nightly cron: use Action Scheduler recurring action (not WP cron) for reliability
- Model change detection: when `wup_ai_embedding_model` option changes, full-invalidate index

## Requirements

### Functional
- On `wup_embed_product` completion: schedule single-product similarity rebuild
- Nightly: schedule full similarity rebuild at 02:00 site time
- Model change: invalidate all index rows + schedule full rebuild
- Manual rebuild: AJAX endpoint for admin "Rebuild" button (Phase 06 UI)
- Plugin init: require new class files in correct order

### Non-functional
- No duplicate scheduled actions (deduplicate)
- Nightly job resilient to missed runs (AS handles retries)

## Architecture

### Hook Map

| Trigger | Action | Handler |
|---------|--------|---------|
| Product embed complete | `wup_embed_product` | Schedule `WUP_Similarity_Batch::schedule_single()` |
| Nightly | `wup_similarity_nightly` recurring | `WUP_Similarity_Batch::schedule_full_rebuild()` |
| Model option changed | `update_option_wup_ai_embedding_model` | `WUP_Similarity_Index::invalidate_all()` + schedule full rebuild |
| Admin AJAX | `wp_ajax_wup_rebuild_similarity` | `WUP_Similarity_Batch::schedule_full_rebuild()` |
| Admin AJAX | `wp_ajax_wup_similarity_status` | Return index count + last build time |

## Related Code Files

### Files to Modify
- `includes/class-wup-plugin.php` -- add requires + hooks in `init_ai_hooks()`
- `includes/class-wup-loader.php` -- no change needed (AI files loaded in Plugin::init)

## Implementation Steps

1. **Add require statements** in `WUP_Plugin::init()`, after existing AI requires:
   ```php
   require_once WUP_INCLUDES_DIR . 'ai/class-wup-similarity-index.php';
   require_once WUP_INCLUDES_DIR . 'ai/class-wup-similarity-batch.php';
   ```

2. **Modify `wup_embed_product` handler** to also trigger similarity rebuild:
   ```php
   // Existing:
   add_action( 'wup_embed_product', function ( int $product_id ): void {
       WUP_Product_Embedder::embed_product( $product_id );
       WUP_Similarity_Search::invalidate_cache( $product_id );
       // NEW: rebuild this product's similarity index row
       WUP_Similarity_Index::invalidate( $product_id );
       WUP_Similarity_Batch::schedule_single( $product_id );
   } );
   ```

3. **Register Action Scheduler action handlers** in `init_ai_hooks()`:
   ```php
   // Similarity batch handlers
   add_action( WUP_Similarity_Batch::ACTION_CHUNK, [ WUP_Similarity_Batch::class, 'handle_chunk' ] );
   add_action( WUP_Similarity_Batch::ACTION_SINGLE, [ WUP_Similarity_Batch::class, 'handle_single' ] );
   ```

4. **Schedule nightly recurring action** (idempotent -- only schedules if not already scheduled):
   ```php
   // In init_ai_hooks(), after action registrations:
   add_action( 'init', function (): void {
       if ( ! get_option( 'wup_ai_api_key' ) ) {
           return;
       }
       if ( function_exists( 'as_has_scheduled_action' )
            && ! as_has_scheduled_action( 'wup_similarity_nightly' ) ) {
           // Schedule nightly at 02:00 UTC
           $next_run = strtotime( 'tomorrow 02:00:00 UTC' );
           as_schedule_recurring_action( $next_run, DAY_IN_SECONDS, 'wup_similarity_nightly', [], 'wup-similarity' );
       }
   } );

   add_action( 'wup_similarity_nightly', [ WUP_Similarity_Batch::class, 'schedule_full_rebuild' ] );
   ```

5. **Model change detection**:
   ```php
   add_action( 'update_option_wup_ai_embedding_model', function ( $old, $new ): void {
       if ( $old !== $new ) {
           WUP_Similarity_Index::invalidate_all();
           // Full rebuild will happen after embeddings are regenerated
       }
   }, 10, 2 );
   ```

6. **AJAX: rebuild similarity index** (admin button):
   ```php
   add_action( 'wp_ajax_wup_rebuild_similarity', function (): void {
       check_ajax_referer( 'wup_batch_embed', 'nonce' );
       if ( ! current_user_can( 'manage_woocommerce' ) ) {
           wp_send_json_error( 'Unauthorized' );
       }
       WUP_Similarity_Batch::schedule_full_rebuild();
       wp_send_json_success( [ 'message' => 'Rebuild scheduled.' ] );
   } );
   ```

7. **AJAX: similarity status** (for admin UI polling):
   ```php
   add_action( 'wp_ajax_wup_similarity_status', function (): void {
       check_ajax_referer( 'wup_batch_embed', 'nonce' );
       if ( ! current_user_can( 'manage_woocommerce' ) ) {
           wp_send_json_error( 'Unauthorized' );
       }
       wp_send_json_success( [
           'indexed'      => WUP_Similarity_Index::count_indexed(),
           'embedded'     => WUP_Similarity_Batch::count_embedded_products(),
           'last_build'   => get_option( 'wup_similarity_last_full_build', 0 ),
           'last_computed' => WUP_Similarity_Index::get_last_computed(),
       ] );
   } );
   ```

## Todo List
- [x] Add `require_once` for new AI classes in `WUP_Plugin::init()`
- [x] Modify `wup_embed_product` handler to trigger index rebuild
- [x] Register AS action handlers for chunk + single
- [x] Schedule nightly recurring action
- [x] Add model change detection hook
- [x] Add AJAX endpoint: `wup_rebuild_similarity`
- [x] Add AJAX endpoint: `wup_similarity_status`
- [x] Verify nonce reuse (`wup_batch_embed`) is acceptable for new endpoints
- [x] Test: product save triggers embed then index rebuild
- [x] Test: nightly action schedules correctly
- [x] Test: model change triggers full invalidation

## Success Criteria
- Product save -> embed -> index rebuild chain works end-to-end
- Nightly rebuild schedules and completes
- AJAX endpoints respond correctly to admin requests
- No duplicate scheduled actions

## Risk Assessment
- **Nonce reuse**: reusing `wup_batch_embed` nonce for similarity endpoints is safe (same page context, same capability check). Avoids adding another nonce to the settings page.
- **Race condition**: product save during full rebuild is fine -- `REPLACE INTO` means last write wins, and both write correct data.
- **Missing AS**: fallback to wp-cron for nightly job not implemented. AS ships with WC 3.6+, plugin requires WC 8.0+. Safe to assume AS is available.

## Security Considerations
- All AJAX endpoints check nonce + `manage_woocommerce` capability
- Background jobs run as system, no capability bypass needed

## Next Steps
- Phase 06 adds admin UI for monitoring and triggering rebuilds
