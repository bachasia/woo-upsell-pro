<?php
/**
 * WUP_Activator — Handles plugin activation and deactivation.
 *
 * Activation: records install timestamp and plugin version.
 * Deactivation: no-op (data preserved for re-activation).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WUP_Activator' ) ) {

	class WUP_Activator {

		/** Register WP activation/deactivation hooks. Must be called before any output. */
		public static function register_hooks(): void {
			register_activation_hook( WUP_FILE, [ __CLASS__, 'activate' ] );
			register_deactivation_hook( WUP_FILE, [ __CLASS__, 'deactivate' ] );
		}

		/** Runs on plugin activation. */
		public static function activate(): void {
			if ( ! get_option( 'wup_activated_time' ) ) {
				update_option( 'wup_activated_time', time(), false );
			}
			update_option( 'wup_version', WUP_VERSION, false );
			self::create_tables();
			update_option( 'wup_db_version', WUP_DB_VERSION, false );
		}

		/**
		 * Create or upgrade custom DB tables using dbDelta (idempotent).
		 * Called on activation and on version upgrade.
		 */
		public static function create_tables(): void {
			global $wpdb;

			$table   = $wpdb->prefix . 'wup_similar';
			$charset = $wpdb->get_charset_collate();

			// Note: dbDelta requires exactly two spaces before PRIMARY KEY.
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

		/** Runs on plugin deactivation — intentionally empty. */
		public static function deactivate(): void {
			// Nothing to do on deactivation; data is preserved.
		}
	}
}
