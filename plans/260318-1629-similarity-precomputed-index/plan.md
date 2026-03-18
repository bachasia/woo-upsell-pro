---
title: "Pre-computed Similarity Index (Option A -> Option C)"
description: "Replace O(n) real-time vector scan with O(1) lookup from pre-computed wp_wup_similar table"
status: completed
priority: P1
effort: 6h
branch: main
tags: [ai, performance, database, action-scheduler]
created: 2026-03-18
---

# Pre-computed Similarity Index

## Problem

Current `WUP_Similarity_Search::find_similar()` loads ALL product embedding vectors from `wp_postmeta` on every cache miss. At 5K products with 1536-dim vectors, that is ~15MB per query. Does not scale past ~1000 products.

## Solution

Add `wp_wup_similar` table storing pre-computed top-10 similar product IDs per product. Background batch job (Action Scheduler) recomputes nightly + on product save. `find_similar()` becomes O(1) table lookup with graceful fallback to current real-time method when table is empty.

## Architecture Change

```
Before:  find_similar() -> load ALL vectors -> dot product -> top-N -> cache
After:   find_similar() -> SELECT from wp_wup_similar -> done (fallback to before if no row)
```

## Files Created

| File | Purpose |
|------|---------|
| `includes/ai/class-wup-similarity-index.php` | Core class: build, get, invalidate similarity index |
| `includes/ai/class-wup-similarity-batch.php` | Chunked batch computation via Action Scheduler |

## Files Modified

| File | Change |
|------|--------|
| `includes/class-wup-activator.php` | Add `create_tables()` with dbDelta for `wp_wup_similar` |
| `includes/class-wup-plugin.php` | Require new files, register hooks for index rebuild |
| `includes/ai/class-wup-similarity-search.php` | Add O(1) path via index lookup + fallback |
| `admin/class-wup-settings-page.php` | Add "Similarity Index" status card + rebuild button |
| `admin/class-wup-settings-schema.php` | No change needed (no new settings fields) |

## Phases

| # | Phase | Status | Effort |
|---|-------|--------|--------|
| 1 | [DB table creation](./phase-01-db-table-creation.md) | completed | 30m |
| 2 | [Similarity Index class](./phase-02-similarity-index-class.md) | completed | 1.5h |
| 3 | [Batch computation class](./phase-03-batch-computation.md) | completed | 1h |
| 4 | [Similarity Search modification](./phase-04-search-modification.md) | completed | 30m |
| 5 | [Hook integration](./phase-05-hook-integration.md) | completed | 1h |
| 6 | [Admin UI](./phase-06-admin-ui.md) | completed | 1.5h |

## Key Dependencies

- Action Scheduler (ships with WooCommerce 3.6+) for background jobs
- Existing `WUP_Product_Embedder` API unchanged
- `dbDelta()` for safe table creation/upgrade

## Risk Summary

- **Memory**: batch job must load all vectors once per run. Mitigated by chunked processing (500 products/chunk)
- **Race condition**: product save during full rebuild. Mitigated by per-product invalidation + immediate single-product reindex
- **Empty table**: first-time users or fresh installs see fallback to real-time method until first batch completes
