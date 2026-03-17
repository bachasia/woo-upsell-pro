# Phase 02 — Embedding Generator + Storage

**Status:** pending
**Priority:** High (blocker for phase 03, 04)
**Depends on:** Phase 01

## Overview

Generate embeddings for products and store as `_wup_embedding` post_meta.
Trigger on product save (incremental) and via admin batch (initial setup).
Text input = product title + short description (optional, configurable).

## Related Code Files

- **New:** `includes/ai/class-wup-product-embedder.php` — core embed + store logic
- `includes/class-wup-plugin.php` — register save hook

## Storage Schema

```
post_meta key: _wup_embedding
value: JSON-encoded float[] — e.g. [-0.021, 0.034, ..., 0.018] (1536 elements)
size: ~6 KB per product
```

## Text Input Strategy

Build embed text from:
```
"{product_title}. {short_description_stripped}"
```
- `short_description` is optional (toggle in settings: `wup_ai_embed_use_description`)
- Strip HTML tags, trim to max 512 tokens (~2000 chars) to stay within model limits

## Implementation Steps

1. **Create `WUP_Product_Embedder`** (`class-wup-product-embedder.php`):
   - `static embed_product( int $product_id ): bool`
     - Build text input from title + optional description
     - Call `WUP_Embedding_Client::make()->embed( $text )`
     - Store result: `update_post_meta( $product_id, '_wup_embedding', wp_json_encode( $vector ) )`
     - Return true on success, false on API error
   - `static get_embedding( int $product_id ): ?array`
     - Read + JSON-decode `_wup_embedding` meta
     - Return null if not yet embedded

2. **Hook on product save** in `class-wup-plugin.php`:
   ```php
   add_action( 'woocommerce_update_product', [ WUP_Product_Embedder::class, 'embed_product' ] );
   ```
   - Only embed if API key is configured
   - Run async if possible (via WC Action Scheduler or wp_schedule_single_event to avoid blocking admin save)

3. **Async fallback** (simple approach without Action Scheduler):
   - Use `wp_schedule_single_event( time(), 'wup_embed_product', [ $product_id ] )`
   - Register `add_action( 'wup_embed_product', ... )`

## Success Criteria

- [ ] Saving a product triggers embedding generation
- [ ] `_wup_embedding` meta exists after save with valid 1536-element JSON array
- [ ] `WUP_Product_Embedder::get_embedding( $id )` returns float[] for embedded products
- [ ] No blocking delay on product save (async)
- [ ] Graceful no-op when API key not configured
