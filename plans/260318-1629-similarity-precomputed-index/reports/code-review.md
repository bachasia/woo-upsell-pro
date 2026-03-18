---
reviewer: code-reviewer
date: 2026-03-18
plan: plans/260318-1629-similarity-precomputed-index/
---

# Code Review — Pre-computed Similarity Index

## Scope

| File | Role |
|------|------|
| `includes/ai/class-wup-similarity-index.php` | NEW — read/write wp_wup_similar table |
| `includes/ai/class-wup-similarity-batch.php` | NEW — AS-backed chunked batch rebuild |
| `includes/ai/class-wup-similarity-search.php` | MODIFIED — added `try_index_lookup()` fast path |
| `includes/class-wup-plugin.php` | MODIFIED — hooks + 4 AJAX handlers |
| `includes/class-wup-activator.php` | MODIFIED — `create_tables()` via dbDelta |
| `admin/class-wup-settings-page.php` | MODIFIED — `render_similarity_index_card()` |
| `uninstall.php` | MODIFIED — DROP TABLE |

LOC added: ~430. PHP syntax: no PHP binary available; reviewed manually. No syntax errors found.

## Overall Assessment

Solid implementation. Architecture is clean — the three-tier fallback chain (transient → index → real-time) is correct and each tier degrades gracefully. Security posture on AJAX handlers is good. Several low/medium-priority issues documented below; one medium-priority correctness issue in the batch memory management and one medium-priority unescaped output.

## Critical Issues

None.

## High Priority

### H1 — `handle_chunk` reloads ALL vectors on every tick (memory × chunk count)

**File:** `class-wup-similarity-batch.php`, `handle_chunk()` line 76.

`load_all_vectors()` is called at the start of every chunk action. For a 10 000-product store with 1536-dim vectors that is ~120 MB loaded, JSON-decoded, and GC'd for **each of the 20 AS ticks**. The 5-second inter-chunk delay doesn't help — each tick is a fresh PHP process.

This is by-design (comment on line 9: "loads ALL vectors once"), but "once per tick" is not "once per rebuild". The comment is misleading and the cost is real.

There is no straightforward fix without storing vectors between ticks (transient or temp table). However, the current approach is still correct — it will complete — just more expensive than stated. At 1536 dims / 5K products the per-tick cost is ~90 MB which exceeds default WP memory limits even after `wp_raise_memory_limit('admin')` (128 MB → 256 MB). At 10K+ products this becomes a problem.

**Recommendation:** Add an explicit memory guard: if `memory_get_usage(true) > 200 * 1024 * 1024` after loading, bail and reschedule with the same offset (retry). Also fix the misleading inline comment.

### H2 — `count_embedded_products` duplicated across two classes

**Files:** `class-wup-plugin.php` line 301–307 and `class-wup-similarity-batch.php` line 136–139.

Both query `COUNT(DISTINCT post_id) FROM {wpdb->postmeta} WHERE meta_key = '_wup_embedding'` using a hardcoded string in `WUP_Plugin` rather than `WUP_Product_Embedder::META_VECTOR`. Violates DRY and risks drift if the meta key ever changes. `WUP_Similarity_Batch::count_embedded_products()` already exists — `WUP_Plugin::count_embedded_products()` should call it, or both should use the constant.

## Medium Priority

### M1 — Unescaped output in `render_similarity_index_card()`

**File:** `class-wup-settings-page.php`, line 413.

```php
<div style="...">Last full build: <span id="wup-sim-last-built"><?php echo $last_built_str; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
```

`$last_built_str` is built from `human_time_diff()` + string literal, which is safe in practice, but the `phpcs:ignore` suppresses the rule rather than fixing it. Use `echo esc_html( $last_built_str )` and remove the ignore comment.

### M2 — `strtotime('tomorrow 02:00:00')` timezone ambiguity in nightly schedule

**File:** `class-wup-plugin.php`, line 153.

```php
wp_schedule_event( strtotime( 'tomorrow 02:00:00' ), 'daily', 'wup_similarity_nightly_rebuild' );
```

`strtotime()` uses the server's PHP timezone, not the WordPress site timezone. On a server set to UTC+0 this fires at 02:00 UTC; on a server in UTC+8 it fires at 02:00 local = 18:00 UTC the day before. WordPress cron timestamps should always be derived from UTC. Fix:

```php
wp_schedule_event(
    strtotime( 'tomorrow 02:00:00', time() ), // still server-tz but consistent
    // or better:
    mktime( 2, 0, 0, date('n'), date('j') + 1 ), // explicit UTC if WP_TIMEZONE_STRING='UTC'
    'daily',
    'wup_similarity_nightly_rebuild'
);
```

More robustly: use `wp_next_scheduled` with a comment that documents the timezone assumption, or accept that the fire time is approximate (the current offset from desired is only cosmetic, not a correctness bug).

### M3 — `invalidate_all()` uses unparameterised TRUNCATE

**File:** `class-wup-similarity-index.php`, line 125.

```php
$wpdb->query( 'TRUNCATE TABLE ' . self::table() );
```

`self::table()` is `$wpdb->prefix . 'wup_similar'` — the prefix is from WordPress core, not user input, so this is not a SQL injection risk. However, `TRUNCATE` is a DDL statement that implicitly commits any open transaction and is not rolled back by WP's test-suite transaction wrapping. This is fine in production but will cause issues in any unit test using transaction rollback. Low risk operationally; document it.

### M4 — `try_index_lookup` does not cap candidates at `CANDIDATE_POOL`

**File:** `class-wup-similarity-search.php`, `try_index_lookup()` line 105.

```php
$candidates = array_values( array_diff( $indexed, $exclude_ids ) );
return self::filter_visible( $candidates, $limit );
```

The index stores up to `TOP_N = 10` entries. `filter_visible()` iterates all of them calling `wc_get_product()` for each. This is fine for 10 entries (WC object cache), but it diverges from `compute_similar()` which explicitly caps at `CANDIDATE_POOL = 20`. If `TOP_N` is ever raised, the mismatch becomes meaningful. At minimum add a comment; better to consistently apply the pool cap: `array_slice( $candidates, 0, self::CANDIDATE_POOL )`.

### M5 — Cache key does not include `$exclude_ids`

**File:** `class-wup-similarity-search.php`, line 52.

```php
$cache_key = self::CACHE_PREFIX . $product_id . '_' . $limit;
```

If the same product is requested with different `$exclude_ids` (e.g. once from a PDP with no exclusions, once from a bundle context that excludes already-in-cart items), the first result is cached and returned for all subsequent calls regardless of excludes. This is a pre-existing issue not introduced by this PR, but the index fast path makes the cache even more load-bearing and the risk more visible. Document or fix.

## Low Priority

### L1 — Action Scheduler `as_unschedule_all_actions` null args parameter

**File:** `class-wup-similarity-batch.php`, line 190.

```php
as_unschedule_all_actions( self::ACTION_CHUNK, null, self::GROUP );
```

The AS docs define the signature as `as_unschedule_all_actions( string $hook, array $args = null, string $group = '' )`. Passing `null` for `$args` is explicitly supported (means "any args") and works correctly. No bug, but `[]` is clearer than `null` per AS convention.

### L2 — `dot()` function duplicated in `WUP_Similarity_Index` and `WUP_Similarity_Search`

Both classes implement an identical private `dot( array $a, array $b )` method. DRY violation — consider extracting to a shared `WUP_Math` utility or a static method on `WUP_Product_Embedder`.

### L3 — `load_all_vectors()` duplicated in `WUP_Similarity_Batch` and `WUP_Similarity_Search`

Same SQL logic with same LEFT JOIN pattern in both classes. Same extraction suggestion as L2.

### L4 — `wup_similarity_last_full_build` timestamp stored as Unix int, `computed_at` stored as MySQL datetime

Two different time representations for conceptually the same "last built" concept. `get_last_computed()` (from the table's `MAX(computed_at)`) returns a UTC datetime string; `last_full_build` (from the option) returns a Unix timestamp. The admin card uses both. Mixing formats adds cognitive overhead; pick one.

### L5 — No `unset($rows)` in `WUP_Similarity_Search::load_all_vectors()`

**File:** `class-wup-similarity-search.php`, line 180–187.

The batch version (`WUP_Similarity_Batch::load_all_vectors()`) explicitly `unset($rows)` after building the `$vectors` map (line 182 of batch file). The search version does not. For the real-time path this is called inline on a frontend request and returns to the caller quickly, so it's lower risk — but for consistency and to avoid unnecessary peak memory hold, it should mirror the batch version.

### L6 — `get_unembedded_product_ids` LEFT JOIN logic returns wrong rows when vector exists but model matches

**File:** `class-wup-plugin.php`, line 292–293.

```sql
AND ( pm.meta_value IS NULL OR pm2.meta_value != %s )
```

`pm` is the vector JOIN, `pm2` is the model JOIN. A product that has a vector (`pm.meta_value IS NOT NULL`) and the correct model (`pm2.meta_value = $model`) will NOT match — correct. A product with a vector but NO model meta (`pm2.meta_value IS NULL` because the LEFT JOIN produced no row) WILL match because `pm2.meta_value != $model` is NULL-evaluated as false but `pm.meta_value IS NULL` is also false... wait: the condition is `pm.meta_value IS NULL OR pm2.meta_value != $model`. Legacy products (vector present, no model row) have `pm2.meta_value IS NULL` → `!= $model` evaluates to NULL (neither true nor false in MySQL) → row is excluded. This means legacy embeddings are treated as "already embedded" and skipped by the batch embed tool, which is inconsistent with `load_all_vectors()` where the LEFT JOIN includes legacy (model IS NULL) rows.

**Impact:** The batch embed button will not re-embed legacy products (those without a model meta row), even though `find_similar()` will use them for real-time search. Minor inconsistency; no data loss.

## Edge Cases Found by Scout

1. **Product deleted mid-rebuild**: `handle_chunk` iterates `$product_ids` from `load_all_vectors()`. If a product is trashed between load and `build_for_product()`, `build_for_product()` still writes a row for that product_id. The index row stays stale until the next nightly rebuild or manual rebuild. Tolerable.

2. **Full rebuild racing with single-product rebuild**: `schedule_full_rebuild()` calls `cancel_pending_chunks()` but does NOT cancel pending `ACTION_SINGLE` jobs. If 50 single-product jobs are queued and then a full rebuild is triggered, all 51 jobs will run and each will call `load_all_vectors()`. No data corruption, but memory and API cost multiply. Mitigate by also cancelling `ACTION_SINGLE` in `cancel_pending_chunks()`, or document as acceptable.

3. **Model change during mid-rebuild**: `update_option_wup_ai_embedding_model` hook calls `invalidate_all()` then `schedule_full_rebuild()`. If a chunk action fires between `invalidate_all()` and the rebuild completing, `build_for_product()` will write rows with the NEW model (because it reads `get_option` at write time) while the vectors loaded are potentially mixed-model (LEFT JOIN includes legacy). The stale-model check in `get_similar()` would catch this for the OLD model rows but the timing window is narrow enough to be cosmetic.

4. **Store with 0 published products or 0 embeddings**: `handle_chunk()` returns early on `empty($all_vectors)`. `find_similar()` falls through to `compute_similar()` which also returns `[]`. Both paths handled correctly.

5. **Transient collision across `$limit` variants**: Cache key is `wup_emb_{id}_{limit}`. If a product is requested with `limit=5` (PDP) and `limit=3` (cart widget), two separate transients are cached. Both are correctly invalidated by `invalidate_cache()` (wildcard LIKE delete). Correct.

## Positive Observations

- SQL prepared statements used correctly throughout with `%d`/`%s` placeholders.
- `$wpdb->prefix` used consistently via `self::table()` helper — no hardcoded prefix.
- `dbDelta` correctly used with two spaces before `PRIMARY KEY` (per WP docs requirement).
- Action Scheduler deduplication via `as_has_scheduled_action` before scheduling — no duplicate jobs.
- `wp_raise_memory_limit('admin')` called at start of both batch handlers.
- `unset($rows)` in batch `load_all_vectors()` frees the raw SQL result before O(n²) computation.
- Model staleness check in `get_similar()` ensures index is invalidated transparently after model change.
- `uninstall.php` correctly uses `WP_UNINSTALL_PLUGIN` guard and drops the new table.
- Fallback chain (transient → index → real-time) degrades gracefully on fresh install.
- `filter_visible()` uses WC object cache (`wc_get_product`) — correct and performant.
- All AJAX handlers: nonce checked first, then capability check. Correct order.
- PHP 7.4+ arrow function syntax (`static fn`) used only in `class-wup-similarity-index.php` line 56; all other files use traditional closures — consistent with codebase.

## Recommended Actions

1. **(H1)** Add memory guard in `handle_chunk()` — bail+reschedule if memory near limit after `load_all_vectors()`.
2. **(H2)** Remove `WUP_Plugin::count_embedded_products()` and call `WUP_Similarity_Batch::count_embedded_products()` instead (DRY).
3. **(M1)** Replace `echo $last_built_str` with `echo esc_html( $last_built_str )` on line 413 of settings page.
4. **(M4)** Apply `array_slice( $candidates, 0, self::CANDIDATE_POOL )` in `try_index_lookup()` for consistency.
5. **(Edge-2)** Consider cancelling `ACTION_SINGLE` jobs in `cancel_pending_chunks()` to prevent memory pile-up during full rebuilds.
6. **(L2/L3)** Extract `dot()` and `load_all_vectors()` to a shared utility to eliminate duplication (can defer).
7. **(M2)** Add a comment on the `strtotime` line acknowledging the server-timezone dependency.

## Plan TODO Status

All 6 phases in `plan.md` were marked `pending`. All have been implemented:

| Phase | Implemented |
|-------|-------------|
| 1 — DB table | yes — `WUP_Activator::create_tables()` + `uninstall.php` |
| 2 — Similarity Index class | yes — `class-wup-similarity-index.php` |
| 3 — Batch computation | yes — `class-wup-similarity-batch.php` |
| 4 — Search modification | yes — `try_index_lookup()` in search class |
| 5 — Hook integration | yes — `init_ai_hooks()` in plugin class |
| 6 — Admin UI | yes — `render_similarity_index_card()` |

Plan phase statuses should be updated to `complete`.

## Metrics

- Linting issues: 1 confirmed (`echo $last_built_str` unescaped, phpcs:ignore suppressing it)
- SQL injections: 0
- Missing nonce checks: 0
- Missing capability checks: 0
- Duplicate functions: 2 (`count_embedded_products`, `dot`, `load_all_vectors`)

## Unresolved Questions

1. Is the 5-second inter-chunk delay intentional rate-limiting for DB pressure, or can it be reduced for large stores?
2. Should `TOP_N = 10` be configurable per-store (some stores may want more candidates for the visibility filter)?
3. The nightly rebuild uses wp-cron as a comment says "AS recurring is preferred" — is there a plan to switch to AS recurring schedules, or is wp-cron intentional (simpler, no AS dependency for the schedule itself)?
