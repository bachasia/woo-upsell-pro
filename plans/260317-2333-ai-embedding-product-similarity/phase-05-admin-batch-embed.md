# Phase 05 — Admin UI: Batch Embed Tool

**Status:** pending
**Priority:** Medium (needed for initial setup)
**Depends on:** Phase 02

## Overview

Admin UI to batch-generate embeddings for all existing products.
Uses chunked AJAX processing (50 products per batch) to avoid PHP timeout.
Shows progress bar + success/error count.

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

[ Generate Embeddings for All Products ]

████████████░░░░░░░░░  58%  (593 / 1,024)
✓ 590 embedded   ✗ 3 errors
```

## Implementation Steps

1. **Add AJAX endpoint** `wp_ajax_wup_batch_embed` in `class-wup-plugin.php`:
   - Accepts: `offset` (int), `batch_size` (int, default 50)
   - Queries: all published product IDs without `_wup_embedding` meta
   - Calls `WUP_Product_Embedder::embed_product()` for each
   - Returns JSON: `{ processed: N, total: N, errors: N, done: bool }`

2. **Add status endpoint** `wp_ajax_wup_embedding_status`:
   - Returns: count of products with `_wup_embedding` meta vs total published products

3. **Admin section** in settings page (under AI tab):
   - Show current status counts (PHP-rendered on page load)
   - "Generate Embeddings" button triggers JS loop
   - JS calls `wup_batch_embed` repeatedly, incrementing `offset` until `done: true`

4. **Rate limiting**: Add 500ms delay between batches in JS to avoid hitting
   OpenAI rate limits (tier 1: 3,000 RPM for text-embedding-3-small = plenty).

5. **Error display**: Log failed product IDs in a collapsible error list.

## Success Criteria

- [ ] "Generate Embeddings" button visible in admin when API key is configured
- [ ] Progress bar updates correctly as batches process
- [ ] All existing products get `_wup_embedding` meta after full run
- [ ] Partial run can be resumed (skips already-embedded products)
- [ ] API errors shown per-product without stopping the whole batch
