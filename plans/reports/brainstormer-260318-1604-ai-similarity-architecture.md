# Brainstorm Report: AI Product Similarity Architecture

**Date:** 2026-03-18
**Scope:** WooCommerce plugin `woo-upsell-pro` — scale AI similarity search beyond current postmeta approach

---

## Problem Statement

Current implementation (`class-wup-similarity-search.php`) calls `load_all_vectors()` which loads ALL published product vectors from `wp_postmeta` into PHP memory on cache miss. Memory footprint:

- `text-embedding-3-small` (1536 dims): ~12-18KB per product as JSON string
- 1,000 products: ~12-18MB
- 5,000 products: ~60-90MB → OOM on shared hosting (128-256MB limit)
- 10,000 products: ~120-180MB → OOM on most managed hosting

Real OOM threshold: ~2,000-5,000 products, not 50K. This is within the target customer range.

---

## Evaluated Approaches

### A: Current (postmeta + PHP cosine)

**How it works:** Vectors in `wp_postmeta` as JSON, `load_all_vectors()` fetches all into PHP array, pure PHP dot-product loop, 12h transient cache per product.

**Pros:** Already implemented, zero infra, self-contained.

**Cons:**
- O(N) memory at query time — no upper bound
- JSON decode in PHP loop, no SIMD/native ops
- Cache per-product: a product save invalidates that product's cache, but full reload still happens on next miss
- No path to scale without architecture change

**Verdict:** Works fine < 1,000 products. Becomes liability at 2K+.

---

### B: Intermediary SaaS Service

**How it works:** Dedicated server receives product syncs from all customer stores, computes embeddings, stores in vector DB (Qdrant/Pinecone), returns similar products via REST API.

**Analysis:**

**Privacy (showstopper):**
- All customer product data (titles, descriptions, attributes) flows through your server
- B2B customers will ask "where does my data go?"
- EU customers require DPA under GDPR — product data is business-confidential even if not PII
- A server breach exposes entire catalog of all customers simultaneously

**Reliability (structural problem):**
- If intermediary is down, ALL customers lose AI similarity across all their stores
- Requires SLA, monitoring, auto-failover, incident response
- Customers open support tickets against you when their WordPress is fine but your service is not

**Latency:**
- Adds 50-200ms network round-trip on cache miss
- Transient hit stays ~1ms; cache miss becomes noticeably slower for end-users

**Business model transformation (most disruptive):**
- Plugin becomes SaaS dependency: needs auth, rate limiting, usage quotas, billing
- Incompatible with one-time purchase model
- Customers will reject "buy plugin + pay ongoing SaaS fee" for a feature that competes with free WC related products
- This is not a technical decision — it's a product pivot

**Verdict:** Solves scale but creates a different product. Disproportionate cost vs. benefit for this customer segment. Reject.

---

### C: Pre-computed Similarity Table (Recommended)

**How it works:**
- Custom DB table `{prefix}_wup_product_similarity` stores pre-computed scores: `(product_id_a, product_id_b, score, model, computed_at)`
- On product embed/re-embed: background job loads all existing vectors once, computes similarity with new product, writes top-N scores to table
- `find_similar()` becomes a simple indexed DB lookup — no vectors loaded into PHP memory

**Schema concept:**
```sql
product_id_a BIGINT, product_id_b BIGINT, score FLOAT, model VARCHAR(64)
PRIMARY KEY (product_id_a, product_id_b)
INDEX (product_id_a, score DESC)
```

**Storage:** Top-50 per product = 50N rows. At 5K products = 250K rows, ~15MB table. Manageable.

**Computation:** Re-embedding 1 product = load N vectors + N dot products = background-safe ~1-3s for 10K products.

**Query time:** O(1) index scan, ~1-5ms regardless of catalog size.

**Pros:**
- Eliminates OOM at query time entirely
- Data stays local, no privacy concern
- Self-contained, no external dependency
- Plugin remains one-time purchase compatible
- Scales to 100K+ products without architecture change

**Cons:**
- Requires custom DB table (migration)
- Background job on embed (Action Scheduler or WP Cron)
- Initial full-catalog computation when first activating feature: O(N²) but runs once in background
- Incremental maintenance needed: when product deleted, prune rows

**Verdict:** Correct solution. Addresses root cause, fits WordPress plugin model.

---

### D: Category-scoped Pre-filter (Quick Win / Temporary Patch)

**How it works:** Add WHERE clause to `load_all_vectors()` to restrict to same category + adjacent categories. Reduces candidate pool from 10K to ~200-500 for typical structured catalogs.

**Pros:** Minimal code change, immediate memory relief for well-structured catalogs.

**Cons:** Semantic cross-category similarity lost. Flat catalogs (all products same category) get no benefit. Not a real fix, just a band-aid.

**Verdict:** Useful as an interim patch while C is being built.

---

## Recommendation

**Primary: Implement Phương án C (Pre-computed Similarity Table)**

This is the correct architectural fit for a WooCommerce plugin:
- Eliminates the OOM problem structurally
- No privacy, reliability, or business model concerns
- Proven pattern in WordPress (custom tables used by WooCommerce, WPML, Yoast, etc.)
- Scale headroom: handles 100K+ products comfortably

**Migration path:**
1. Deploy D (category pre-filter) as immediate hotfix for customers hitting memory limits
2. Build C alongside A — both active, C takes over when table is populated
3. Deprecate A's `load_all_vectors()` path after C is validated

**Reject Phương án B** — it solves a different problem than what this plugin needs to solve.

---

## Implementation Considerations for C

- Use `Action Scheduler` (bundled with WooCommerce) for background recompute jobs — more reliable than WP Cron
- Table creation via `dbDelta()` on plugin activation hook
- Add admin notice when table not yet populated ("AI similarity pre-computation in progress")
- Prune stale rows when product deleted or unpublished
- Model change invalidation: add `model` column, prune rows where `model != active_model` on settings save

---

## Success Metrics

- Zero OOM errors on stores with 2K-50K products
- `find_similar()` query time < 10ms at any catalog size
- Background recompute for single product < 30s at 10K products
- No customer-visible downtime when switching from A to C

---

## Unresolved Questions

1. Does current plugin use Action Scheduler already, or only WP Cron? (Affects background job implementation choice)
2. What is the target "top-N" to store per product? (50 is a guess — affects table size)
3. Is there a current mechanism to detect if a store has > threshold products to auto-switch strategies?
4. Are there customers already hitting OOM in production, or is this proactive?
