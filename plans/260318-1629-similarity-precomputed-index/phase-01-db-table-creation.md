# Phase 01: DB Table Creation

## Context Links
- [plan.md](./plan.md)
- [class-wup-activator.php](/Users/bachasia/Data/VibeCoding/woo-upsell-pro/includes/class-wup-activator.php)
- [WP dbDelta docs](https://developer.wordpress.org/reference/functions/dbdelta/)

## Overview
- **Priority**: P1 (foundation for all other phases)
- **Status**: completed
- **Description**: Create `wp_wup_similar` table via `dbDelta()` on activation + version upgrade

## Key Insights
- Current `WUP_Activator` is minimal (just sets options). Need to add `create_tables()` method
- Must use `dbDelta()` for safe upgrades (WP standard pattern)
- Table should use InnoDB for row-level locking during batch writes
- Bump `WUP_DB_VERSION` option to trigger re-run on plugin update

## Requirements

### Functional
- Table created on plugin activation
- Table updated via `dbDelta()` on version upgrade
- Table dropped on plugin uninstall (not deactivation)

### Non-functional
- Must work on MySQL 5.7+ and MariaDB 10.3+
- No data loss on plugin update

## Architecture

### Table Schema
```sql
CREATE TABLE {prefix}wup_similar (
    product_id   bigint(20) UNSIGNED NOT NULL,
    similar_ids  text NOT NULL,
    model        varchar(64) NOT NULL,
    dims         smallint(6) UNSIGNED NOT NULL DEFAULT 0,
    computed_at  datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    PRIMARY KEY  (product_id)
) {charset_collate};
```

**Column rationale:**
- `product_id`: FK to `wp_posts.ID` (no actual FK constraint per WP convention)
- `similar_ids`: JSON array `[id1, id2, ..., id10]` with scores: `[[id1, 0.95], [id2, 0.91], ...]`
- `model`: embedding model name, so we know when to invalidate (model change = full rebuild)
- `dims`: vector dimensions, secondary invalidation signal
- `computed_at`: timestamp for "last rebuilt X ago" UI display

## Related Code Files

### Files to Modify
- `includes/class-wup-activator.php` — add `create_tables()` static method, call from `activate()`

### Files to Create
- None (all in activator)

## Implementation Steps

1. Add `WUP_DB_VERSION` constant to `woo-upsell-pro.php` (value: `'1.1.0'`)

2. In `WUP_Activator::activate()`:
   ```php
   self::create_tables();
   update_option( 'wup_db_version', WUP_DB_VERSION, false );
   ```

3. Add `WUP_Activator::create_tables()`:
   ```php
   public static function create_tables(): void {
       global $wpdb;
       $table   = $wpdb->prefix . 'wup_similar';
       $charset = $wpdb->get_charset_collate();

       $sql = "CREATE TABLE {$table} (
           product_id bigint(20) UNSIGNED NOT NULL,
           similar_ids text NOT NULL,
           model varchar(64) NOT NULL,
           dims smallint(6) UNSIGNED NOT NULL DEFAULT 0,
           computed_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
           PRIMARY KEY  (product_id)
       ) {$charset};";

       require_once ABSPATH . 'wp-admin/includes/upgrade.php';
       dbDelta( $sql );
   }
   ```

4. Add version-check hook in `WUP_Plugin::init()` to run `create_tables()` on upgrade:
   ```php
   if ( get_option( 'wup_db_version' ) !== WUP_DB_VERSION ) {
       WUP_Activator::create_tables();
       update_option( 'wup_db_version', WUP_DB_VERSION, false );
   }
   ```

5. Add `uninstall.php` drop table logic (or add to existing uninstall handler):
   ```php
   $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wup_similar" );
   delete_option( 'wup_db_version' );
   ```

## Todo List
- [x] Add `WUP_DB_VERSION` constant to main plugin file
- [x] Implement `WUP_Activator::create_tables()`
- [x] Call `create_tables()` from `activate()`
- [x] Add upgrade check in `WUP_Plugin::init()`
- [x] Add table drop to uninstall handler
- [x] Test: fresh activation creates table
- [x] Test: re-activation does not lose data

## Success Criteria
- `wp_wup_similar` table exists after plugin activation
- `dbDelta()` is idempotent (running twice = no error, no data loss)
- Table has correct charset matching site config

## Risk Assessment
- **dbDelta quirks**: requires exactly two spaces before `PRIMARY KEY`. Template above is correct.
- **Multisite**: `dbDelta()` runs per-site. Network activation would need `switch_to_blog()` loop. YAGNI for now.

## Security Considerations
- Table stores only product IDs and model name. No user data. No PII concern.

## Next Steps
- Phase 02 depends on this table existing
