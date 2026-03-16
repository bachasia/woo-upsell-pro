<?php
/**
 * WUP_Assets — Asset registration and dynamic CSS engine.
 *
 * Registers public JS/CSS handles on wp_enqueue_scripts.
 * Builds an inline CSS block from settings schema fields that carry a `css` key
 * and appends it to the wup-public-styles handle via wp_add_inline_style().
 *
 * Schema `css` key formats:
 *   String:  'selector|property'
 *   Array:   [ 'selector|property', 'selector|property|priority', ... ]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WUP_Assets' ) ) {

	class WUP_Assets {

		/** @var WUP_Assets|null */
		private static ?WUP_Assets $instance = null;

		/** Settings schema injected by feature classes. @var array<int,array> */
		private array $schema = [];

		public static function get_instance(): self {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			add_action( 'wp_enqueue_scripts',    [ $this, 'enqueue_public_assets' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		}

		/**
		 * Allow feature classes to register their settings fields
		 * so the dynamic CSS engine can pick up `css` keys.
		 *
		 * @param array<int,array> $fields Array of field definition arrays.
		 */
		public function register_schema( array $fields ): void {
			$this->schema = array_merge( $this->schema, $fields );
		}

		// -------------------------------------------------------------------------
		// Public assets
		// -------------------------------------------------------------------------

		/** Register and enqueue all public-facing JS and CSS handles. */
		public function enqueue_public_assets(): void {
			$this->register_public_styles();
			$this->register_public_scripts();
			$this->output_dynamic_css();
		}

		/** Register the primary public stylesheet (empty placeholder until features add CSS). */
		private function register_public_styles(): void {
			wp_register_style(
				'wup-public-styles',
				WUP_URL . 'public/css/wup-public.css',
				[],
				WUP_VERSION
			);
			wp_enqueue_style( 'wup-public-styles' );
		}

		/** Register all public-facing script handles (enqueued conditionally by features). */
		private function register_public_scripts(): void {
			$scripts = [
				'wup-popup'       => 'public/js/build/popup.js',
				'wup-sidecart'    => 'public/js/build/sidecart.js',
				'wup-tier-table'  => 'public/js/build/tier-table.js',
				'wup-cart-upsell' => 'public/js/build/cart-upsell.js',
			];

			foreach ( $scripts as $handle => $path ) {
				$asset_file = WUP_DIR . str_replace( '.js', '.asset.php', $path );
				$asset      = file_exists( $asset_file ) ? require $asset_file : [ 'dependencies' => [], 'version' => WUP_VERSION ];

				wp_register_script(
					$handle,
					WUP_URL . $path,
					$asset['dependencies'],
					$asset['version'],
					true
				);
			}
		}

		// -------------------------------------------------------------------------
		// Dynamic CSS engine
		// -------------------------------------------------------------------------

		/**
		 * Build inline CSS from schema fields that carry a `css` key and append
		 * to the wup-public-styles handle.
		 *
		 * Field `css` formats:
		 *   'selector|property'
		 *   [ 'selector|property', 'selector|property|priority' ]
		 */
		private function output_dynamic_css(): void {
			if ( empty( $this->schema ) ) {
				return;
			}

			$css_rules = []; // keyed by priority (int) → array of rule strings

			foreach ( $this->schema as $field ) {
				if ( empty( $field['css'] ) || empty( $field['id'] ) ) {
					continue;
				}

				$value = get_option( $field['id'], $field['default'] ?? '' );
				if ( $value === '' || $value === null ) {
					continue;
				}

				$definitions = is_array( $field['css'] ) ? $field['css'] : [ $field['css'] ];

				foreach ( $definitions as $definition ) {
					$parts    = explode( '|', $definition );
					$selector = $parts[0] ?? '';
					$property = $parts[1] ?? '';
					$priority = isset( $parts[2] ) ? (int) $parts[2] : 10;

					if ( $selector === '' || $property === '' ) {
						continue;
					}

					$css_rules[ $priority ][] = sprintf(
						'%s { %s: %s; }',
						$selector,
						sanitize_key( $property ),
						esc_attr( $value )
					);
				}
			}

			if ( empty( $css_rules ) ) {
				return;
			}

			ksort( $css_rules );
			$css = '';
			foreach ( $css_rules as $rules ) {
				$css .= implode( "\n", $rules ) . "\n";
			}

			wp_add_inline_style( 'wup-public-styles', $css );
		}

		// -------------------------------------------------------------------------
		// Admin assets
		// -------------------------------------------------------------------------

		/** Enqueue admin stylesheet on all WP admin pages. */
		public function enqueue_admin_assets( string $hook ): void {
			// Scoped to plugin admin pages only.
			if ( strpos( $hook, 'wup-' ) === false ) {
				return;
			}

			wp_enqueue_style(
				'wup-admin-styles',
				WUP_URL . 'admin/css/wup-admin.css',
				[],
				WUP_VERSION
			);
		}
	}
}
