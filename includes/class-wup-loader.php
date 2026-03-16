<?php
/**
 * WUP_Loader — WooCommerce dependency guard and plugin bootstrapper.
 *
 * Hooked on plugins_loaded priority 99 so all plugins are registered first.
 * Shows an admin notice (with activate/install button) if WooCommerce is absent.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WUP_Loader' ) ) {

	final class WUP_Loader {

		/** @var WUP_Loader|null */
		private static ?WUP_Loader $instance = null;

		public static function get_instance(): self {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			add_action( 'plugins_loaded', [ $this, 'load_plugin' ], 99 );
		}

		/**
		 * Guard: bail with admin notice when WooCommerce is not active.
		 * Otherwise require all classes and boot the plugin.
		 */
		public function load_plugin(): void {
			if ( ! function_exists( 'WC' ) ) {
				add_action( 'admin_notices', [ $this, 'notice_wc_missing' ] );
				return;
			}

			$this->require_files();
			WUP_Plugin::get_instance()->init();
		}

		/** Load all plugin class files in dependency order. */
		private function require_files(): void {
			// Helpers (no dependencies).
			require_once WUP_INCLUDES_DIR . 'helpers/class-wup-utils.php';
			require_once WUP_INCLUDES_DIR . 'helpers/class-wup-cache.php';
			require_once WUP_INCLUDES_DIR . 'helpers/class-wup-assets.php';

			// Core.
			require_once WUP_INCLUDES_DIR . 'class-wup-activator.php';
			require_once WUP_INCLUDES_DIR . 'class-wup-plugin.php';

			// Admin.
			require_once WUP_ADMIN_DIR . 'class-wup-settings-page.php';
			require_once WUP_ADMIN_DIR . 'class-wup-admin.php';

			// Public.
			require_once WUP_PUBLIC_DIR . 'class-wup-public.php';
		}

		/**
		 * Admin notice shown when WooCommerce is not active.
		 * Offers an Activate or Install button depending on install state.
		 */
		public function notice_wc_missing(): void {
			$screen = get_current_screen();
			if (
				isset( $screen->parent_file ) &&
				'plugins.php' === $screen->parent_file &&
				'update' === $screen->id
			) {
				return;
			}

			$plugin  = 'woocommerce/woocommerce.php';
			$message = sprintf(
				/* translators: %1$s/%2$s: bold open/close tags */
				__( 'The %1$sWoo Upsell Pro%2$s plugin requires %1$sWooCommerce%2$s installed & activated.', 'woo-upsell-pro' ),
				'<strong>',
				'</strong>'
			);

			if ( $this->is_wc_installed() ) {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				$action_url   = wp_nonce_url(
					'plugins.php?action=activate&plugin=' . $plugin . '&plugin_status=all&paged=1',
					'activate-plugin_' . $plugin
				);
				$button_label = __( 'Activate WooCommerce', 'woo-upsell-pro' );
			} else {
				if ( ! current_user_can( 'install_plugins' ) ) {
					return;
				}
				$action_url   = wp_nonce_url(
					self_admin_url( 'update.php?action=install-plugin&plugin=woocommerce' ),
					'install-plugin_woocommerce'
				);
				$button_label = __( 'Install WooCommerce', 'woo-upsell-pro' );
			}

			$button = '<p><a href="' . esc_url( $action_url ) . '" class="button-primary">'
				. esc_html( $button_label ) . '</a></p>';

			printf(
				'<div class="notice notice-error"><p>%s</p>%s</div>',
				wp_kses_post( $message ),
				wp_kses_post( $button )
			);
		}

		/** Check if WooCommerce plugin file exists (even if inactive). */
		private function is_wc_installed(): bool {
			return isset( get_plugins()['woocommerce/woocommerce.php'] );
		}
	}

	WUP_Loader::get_instance();
}
