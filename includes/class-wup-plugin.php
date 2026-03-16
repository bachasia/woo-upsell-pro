<?php
/**
 * WUP_Plugin — Core plugin singleton.
 *
 * Coordinates admin, public, and feature subsystems.
 * Called from WUP_Loader::load_plugin() after WC guard passes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WUP_Plugin' ) ) {

	final class WUP_Plugin {

		/** @var WUP_Plugin|null */
		private static ?WUP_Plugin $instance = null;

		public static function get_instance(): self {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {}

		/**
		 * Bootstrap all subsystems.
		 * Called once by WUP_Loader after all files are required.
		 */
		public function init(): void {
			add_action( 'init', [ $this, 'load_textdomain' ] );

			// Register activation / deactivation hooks (must be called before output).
			WUP_Activator::register_hooks();

			// Boot feature subsystems.
			require_once WUP_INCLUDES_DIR . 'features/class-wup-product-source.php';
			require_once WUP_INCLUDES_DIR . 'features/class-wup-variation-resolver.php';
			require_once WUP_INCLUDES_DIR . 'features/class-wup-bundle.php';
			require_once WUP_ADMIN_DIR . 'class-wup-product-fields.php';
			WUP_Product_Source::init_hooks();
			WUP_Bundle::get_instance();
			WUP_Product_Fields::get_instance();

			// Boot admin subsystem.
			if ( is_admin() ) {
				WUP_Admin::get_instance();
			}

			// Boot public subsystem.
			WUP_Public::get_instance();

			// Boot asset manager.
			WUP_Assets::get_instance();

			do_action( 'wup_loaded' );
		}

		/** Load plugin text domain for translations. */
		public function load_textdomain(): void {
			load_plugin_textdomain(
				WUP_TEXT_DOMAIN,
				false,
				dirname( plugin_basename( WUP_FILE ) ) . '/languages'
			);
		}
	}
}
