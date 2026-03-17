<?php
/**
 * WUP_Admin — Admin menu registration and React app entrypoint.
 *
 * Adds a top-level menu page under WP Admin (not under WooCommerce).
 * Enqueues the React admin build on the plugin's settings page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WUP_Admin' ) ) {

	class WUP_Admin {

		/** @var WUP_Admin|null */
		private static ?WUP_Admin $instance = null;

		public static function get_instance(): self {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			// Boot settings page early (during plugins_loaded) so its admin_init hooks
			// (handle_save, register_settings) are registered before admin_init fires.
			WUP_Settings_Page::get_instance();

			add_action( 'admin_menu',            [ $this, 'register_menu' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
		}

		/** Register the top-level WP Admin menu page. */
		public function register_menu(): void {
			add_menu_page(
				__( 'Woo Upsell Pro', 'woo-upsell-pro' ),
				__( 'Upsell Pro', 'woo-upsell-pro' ),
				'manage_options',
				'wup-settings',
				[ $this, 'render_settings_page' ],
				'dashicons-cart',
				56
			);
		}

		/** Render the settings page — delegates to WUP_Settings_Page. */
		public function render_settings_page(): void {
			WUP_Settings_Page::get_instance()->render();
		}

		/**
		 * Enqueue the React admin app only on the plugin's own settings page.
		 *
		 * @param string $hook Current admin page hook suffix.
		 */
		public function enqueue_admin_scripts( string $hook ): void {
			if ( 'toplevel_page_wup-settings' !== $hook ) {
				return;
			}

			$asset_file = WUP_ADMIN_DIR . 'build/index.asset.php';
			$asset      = file_exists( $asset_file )
				? require $asset_file
				: [ 'dependencies' => [ 'wp-element', 'wp-components', 'wp-api-fetch' ], 'version' => WUP_VERSION ];

			wp_enqueue_script(
				'wup-admin',
				WUP_URL . 'admin/build/index.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);

			wp_localize_script(
				'wup-admin',
				'wupAdmin',
				[
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'wup_admin_nonce' ),
					'version' => WUP_VERSION,
				]
			);
		}
	}
}
