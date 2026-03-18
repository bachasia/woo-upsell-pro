# Phase 05 — Admin UI: Batch Embed Tool

**Status:** pending
**Priority:** Medium (needed for initial setup)
**Depends on:** Phase 02

## Overview

Admin UI to batch-generate embeddings for all existing products.
Uses chunked AJAX processing (50 products per batch) with **one API call per batch**
(not one per product) via `embed_batch()`. Shows cost estimate + progress bar + error count.

## Related Code Files

- `admin/class-wup-settings-page.php` — add batch embed section
- `admin/css/wup-admin.css` — progress bar styles
- **New:** `admin/js/wup-batch-embed.js` (or inline script) — AJAX loop + progress UI
- `includes/ai/class-wup-product-embedder.php` — add `embed_batch_ajax()` handler

## UI Design

```
[ AI Embeddings ]
─────────────────────────────────────────────
Products embedded: 243 / 1,024
Last run: 2026-03-17 23:00
Estimated cost for remaining 781 products: ~$0.005 (text-embedding-3-small)

[ Generate Embeddings for All Products ]

████████████░░░░░░░░░  58%  (593 / 1,024)
✓ 590 embedded   ✗ 3 errors
```

Cost estimate formula: `remaining_products × 300 tokens × $0.02/1M = $X`.
Display before the button so user can decide before incurring API costs.

## Implementation Steps

1. **Add AJAX endpoint** `wp_ajax_wup_batch_embed` in `class-wup-plugin.php`:
   - Verify nonce + capability at entry: `check_ajax_referer('wup_batch_embed','nonce'); check_current_user_can('manage_woocommerce');`
   - Accepts: `offset` (int), `batch_size` (int, default 50)
   - Queries: all published product IDs without `_wup_embedding` (or model mismatch)
   - Build position→ID map explicitly before calling `embed_batch()`:
     ```php
     $id_map = []; $texts = [];
     foreach ( $batch_ids as $pos => $product_id ) {
         $id_map[ $pos ]  = $product_id;
         $texts[ $pos ]   = WUP_Product_Embedder::build_embed_text( $product_id );
     }
     $vectors = $client->embed_batch( $texts ); // OpenAI preserves input order
     foreach ( $id_map as $pos => $product_id ) {
         if ( isset( $vectors[ $pos ] ) ) {
             WUP_Product_Embedder::store_embedding( $product_id, $vectors[ $pos ], $active_model );
         } else {
             $errors[] = $product_id; // partial failure — skip, don't abort batch
         }
     }
     ```
   - Returns JSON: `{ processed: N, total: N, errors: N, done: bool }`

2. **Add status endpoint** `wp_ajax_wup_embedding_status`:
   - Returns: count of products with `_wup_embedding` (matching active model) vs total published
   - Also returns: estimated cost for remaining products (300 tokens × $0.02/1M each)

3. **Admin section** in settings page (under AI tab):
   - Show current status counts (PHP-rendered on page load)
   - "Generate Embeddings" button triggers JS loop
   - JS calls `wup_batch_embed` repeatedly, incrementing `offset` until `done: true`

4. **Rate limiting** — TPM is the real bottleneck, not RPM:
   - `text-embedding-3-small` tier 1: **1M TPM** (tokens per minute)
   - 50 products × 300 tokens/product = 15,000 tokens/batch
   - Max safe rate: 1,000,000 / 15,000 = ~66 batches/min → need **~900ms** minimum delay
   - Use `Math.max(900, calculated_delay)` in JS between each batch call
   - Display estimated completion time in UI based on remaining batches × delay

5. **Error display**: Log failed product IDs in a collapsible error list.

## Success Criteria

- [ ] "Generate Embeddings" button visible in admin when API key is configured
- [ ] Estimated cost shown before run (based on remaining unembedded products)
- [ ] Each batch uses 1 API call via `embed_batch()`, not N calls
- [ ] Progress bar updates correctly as batches process
- [ ] All existing products get `_wup_embedding` + `_wup_embedding_model` meta after full run
- [ ] Partial run can be resumed (skips already-embedded products with current model)
- [ ] Switching model triggers re-embed prompt (existing embeddings become stale)
- [ ] API errors shown per-product without stopping the whole batch
