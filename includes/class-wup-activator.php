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
		}

		/** Runs on plugin deactivation — intentionally empty. */
		public static function deactivate(): void {
			// Nothing to do on deactivation; data is preserved.
		}
	}
}
