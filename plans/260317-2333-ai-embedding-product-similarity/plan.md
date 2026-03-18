---
title: AI Embedding — Product Similarity Engine
status: pending
created: 2026-03-17
slug: 260317-2333-ai-embedding-product-similarity
---

# AI Embedding — Product Similarity Engine

Shared semantic similarity layer for all WUP upsell features.
Products are embedded once (on save or via batch), stored as post_meta vectors.
At query time: cosine similarity lookup in PHP — zero API cost per customer visit.

## Phases

| # | Phase | Status |
|---|-------|--------|
| 01 | [Settings & API Client](phase-01-settings-api-client.md) | pending |
| 02 | [Embedding Generator + Storage](phase-02-embedding-generator.md) | pending |
| 03 | [Similarity Search Engine](phase-03-similarity-search.md) | pending |
| 04 | [WUP_Product_Source Integration](phase-04-product-source-integration.md) | pending |
| 05 | [Admin UI — Batch Embed Tool](phase-05-admin-batch-embed.md) | pending |

## Key Dependencies

- Phase 01 → all others (API key must exist before embedding)
- Phase 02 → 03, 04 (vectors must exist before search)
- Phase 03 → 04 (search engine before source integration)
- Phase 05 is standalone (admin-only, can be done last)

## Design Principle

`source = 'semantic'` is **one optional choice** alongside existing WooCommerce-native sources.
Users who don't want AI simply keep using `related`, `tags`, `upsell`, `cross_sell`, or `specific` — no behavior changes for them.
The AI feature is purely additive: it adds a new option to every source selector dropdown, nothing more.

## Architecture Decision

- **Provider**: OpenAI `text-embedding-3-small` (1536 dims, cheapest, best quality/cost)
- **Fallback**: Allow pluggable provider (interface-based) so user can swap to Cohere, Google, etc.
- **Storage**: `_wup_embedding` post_meta — JSON-encoded float array
- **Query strategy**: PHP cosine similarity loop (fast enough for < 20k products with transient cache)
- **Scope**: New `source = 'semantic'` option in WUP_Product_Source — reused by ALL upsell features
- **Non-breaking**: All existing sources (`related`, `tags`, `cross_sell`, `upsell`, `specific`) remain unchanged
