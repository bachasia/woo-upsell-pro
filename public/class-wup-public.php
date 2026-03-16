<?php
/**
 * WUP_Public — Frontend hook registration skeleton.
 *
 * Delegates asset enqueueing to WUP_Assets.
 * Feature classes register their own hooks via do_action('wup_loaded').
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WUP_Public' ) ) {

	class WUP_Public {

		/** @var WUP_Public|null */
		private static ?WUP_Public $instance = null;

		public static function get_instance(): self {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		}

		/**
		 * Trigger public asset enqueueing via the assets manager.
		 * WUP_Assets hooks into wp_enqueue_scripts independently;
		 * this call is a convenience proxy for explicit ordering.
		 */
		public function enqueue_assets(): void {
			WUP_Assets::get_instance()->enqueue_public_assets();
		}
	}
}
