<?php
/**
 * WUP_Settings_Page — Settings schema skeleton and React root renderer.
 *
 * Schema is intentionally empty for Phase 00; feature phases will
 * populate get_schema() with their own field definitions.
 *
 * The render() method outputs only a React mount point — all UI is
 * handled by the React admin build (admin/src/index.js).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WUP_Settings_Page' ) ) {

	class WUP_Settings_Page {

		/** @var WUP_Settings_Page|null */
		private static ?WUP_Settings_Page $instance = null;

		public static function get_instance(): self {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {}

		/**
		 * Returns the settings field schema.
		 * Each entry is an array with at minimum: id, label, type, default.
		 * Feature phases add their fields here in Phase 08.
		 *
		 * @return array<int,array>
		 */
		public function get_schema(): array {
			return [];
		}

		/**
		 * Persist settings from a form POST.
		 * Loops the schema, sanitizes each value, and calls update_option().
		 */
		public function save(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			check_admin_referer( 'wup_save_settings', 'wup_nonce' );

			foreach ( $this->get_schema() as $field ) {
				if ( empty( $field['id'] ) ) {
					continue;
				}

				$key   = sanitize_key( $field['id'] );
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above
				$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
				update_option( $key, $value );
			}
		}

		/**
		 * Render the React admin app mount point.
		 * The React build targets #wup-admin-root.
		 */
		public function render(): void {
			?>
			<div class="wrap">
				<h1><?php echo esc_html__( 'Woo Upsell Pro', 'woo-upsell-pro' ); ?></h1>
				<div id="wup-admin-root"></div>
			</div>
			<?php
		}
	}
}
