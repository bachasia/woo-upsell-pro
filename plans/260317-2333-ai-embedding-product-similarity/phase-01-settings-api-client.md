# Phase 01 — Settings & API Client

**Status:** pending
**Priority:** High (blocker for all other phases)

## Overview

Add OpenAI API key setting + a lightweight HTTP client class for embedding API calls.
Designed as a thin, pluggable interface so future providers (Cohere, Google) can be swapped in.

## Related Code Files

- `admin/class-wup-settings-schema.php` — add AI settings tab or group
- **New:** `includes/ai/class-wup-embedding-client.php` — HTTP client abstraction
- **New:** `includes/ai/class-wup-openai-provider.php` — OpenAI implementation

## Implementation Steps

1. **Add settings** to `class-wup-settings-schema.php`:
   - `wup_ai_provider` — select: `openai` (default), extensible
   - `wup_ai_api_key` — text (password input), stored encrypted or plaintext
   - `wup_ai_embedding_model` — select: `text-embedding-3-small` (default), `text-embedding-3-large`
   - Tab: existing `wup-general` or new `wup-ai`

2. **Create interface** `WUP_Embedding_Provider` (in `class-wup-embedding-client.php`):
   ```php
   interface WUP_Embedding_Provider {
       public function embed( string $text ): array; // returns float[]
       public function embed_batch( array $texts ): array; // returns float[][]
   }
   ```

3. **Create** `WUP_OpenAI_Provider implements WUP_Embedding_Provider`:
   - Uses `wp_remote_post()` (no curl dependency)
   - Endpoint: `https://api.openai.com/v1/embeddings`
   - Auth: `Authorization: Bearer {api_key}`
   - Returns `float[]` or throws on error

4. **Create factory** `WUP_Embedding_Client::make(): WUP_Embedding_Provider`:
   - Reads `wup_ai_provider` setting
   - Returns correct provider instance

## Success Criteria

- [ ] API key field visible in admin settings
- [ ] `WUP_Embedding_Client::make()->embed('test text')` returns 1536-element float array
- [ ] WP_Error propagated cleanly when API key missing or invalid
