<?php
/**
 * WUP_Bundle — FBT (Frequently Bought Together) bundle feature.
 *
 * Renders the bundle block on single product pages and registers hooks.
 * AJAX handlers and coupon logic live in the WUP_Bundle_Ajax trait.
 *
 * Supported position keys (wup_upsell_bundle_position):
 *   below_add_to_cart  — after add-to-cart form (default)
 *   below_images       — between product images and summary
 *   below_summary      — after the entire product summary section
 *   inside_summary     — inside the product summary (after sharing links)
 *   shortcode_only     — no automatic hook; use [wup_fbt_bundle] shortcode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wup-bundle-ajax.php';

if ( ! class_exists( 'WUP_Bundle' ) ) {

	class WUP_Bundle {

		use WUP_Bundle_Ajax;

		/** Maps position option values → WooCommerce action hooks. */
		private const POSITION_MAP = [
			'below_add_to_cart' => 'woocommerce_after_add_to_cart_form',
			'below_images'      => 'woocommerce_before_single_product_summary',
			'below_summary'     => 'woocommerce_after_single_product_summary',
			'inside_summary'    => 'woocommerce_single_product_summary',
			'shortcode_only'    => '', // no auto-hook
		];

		/** @var WUP_Bundle|null */
		private static ?WUP_Bundle $instance = null;

		public static function get_instance(): self {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			// Conditional hooks — only when feature is enabled.
			if ( 'yes' === wup_get_option( 'wup_upsell_bundle_enable', 'no' ) ) {
				$position_key = wup_get_option( 'wup_upsell_bundle_position', 'below_add_to_cart' );
				$priority     = intval( wup_get_option( 'wup_upsell_bundle_priority', 50 ) );

				// Resolve hook: known key → map; unknown value treated as raw hook name (backward compat).
				$hook = array_key_exists( $position_key, self::POSITION_MAP )
					? self::POSITION_MAP[ $position_key ]
					: $position_key;

				if ( $hook ) {
					add_action( $hook, [ $this, 'render_bundle' ], $priority );
				}

				// Always register shortcode so [wup_fbt_bundle] works everywhere.
				add_shortcode( 'wup_fbt_bundle', [ $this, 'render_bundle_shortcode' ] );

				add_filter( 'woocommerce_get_shop_coupon_data', [ $this, 'virtual_bundle_coupon' ], 10, 2 );
				add_action( 'woocommerce_before_calculate_totals', [ $this, 'apply_bundle_discount' ], 10, 1 );
			}

			// Always register AJAX + asset hooks.
			add_action( 'wp_ajax_wup_add_bundle',        [ $this, 'ajax_add_bundle' ] );
			add_action( 'wp_ajax_nopriv_wup_add_bundle', [ $this, 'ajax_add_bundle' ] );
			add_action( 'wp_ajax_wup_quickview',         [ $this, 'ajax_quickview' ] );
			add_action( 'wp_ajax_nopriv_wup_quickview',  [ $this, 'ajax_quickview' ] );
			add_action( 'wp_enqueue_scripts',            [ $this, 'enqueue_assets' ] );
		}

		// ------------------------------------------------------------------ //
		// Rendering
		// ------------------------------------------------------------------ //

		/**
		 * Render the FBT bundle block.
		 *
		 * @param int $product_id Optional. Falls back to get_the_ID().
		 */
		public function render_bundle( int $product_id = 0 ): void {
			$product = wc_get_product( $product_id ?: get_the_ID() );
			if ( ! $product instanceof WC_Product ) {
				return;
			}

			$products = WUP_Product_Source::resolve( $product->get_id(), [
				'source' => wup_get_option( 'wup_upsell_bundle_source', 'related' ),
				'limit'  => intval( wup_get_option( 'wup_upsell_bundle_limit', 4 ) ),
			] );

			if ( empty( $products ) ) {
				return;
			}

			$bundle_data = $this->build_bundle_data( $product, $products );
			$template    = WUP_TEMPLATES_DIR . 'bundle/layout-' . $bundle_data['layout'] . '.php';

			if ( file_exists( $template ) ) {
				include $template;
			}
		}

		/**
		 * Shortcode handler: [wup_fbt_bundle id="123"]
		 * When `id` is omitted, falls back to current post ID.
		 *
		 * @param array|string $atts Shortcode attributes.
		 * @return string Rendered HTML.
		 */
		public function render_bundle_shortcode( $atts ): string {
			$atts = shortcode_atts( [ 'id' => 0 ], $atts, 'wup_fbt_bundle' );
			ob_start();
			$this->render_bundle( intval( $atts['id'] ) );
			return ob_get_clean();
		}

		// ------------------------------------------------------------------ //
		// Assets
		// ------------------------------------------------------------------ //

		/** Enqueue bundle JS on product pages and pages containing the shortcode. */
		public function enqueue_assets(): void {
			if ( ! is_product() && ! $this->page_has_bundle_shortcode() ) {
				return;
			}

			$js_path = WUP_PUBLIC_DIR . 'js/build/popup.js';
			$js_ver  = file_exists( $js_path ) ? filemtime( $js_path ) : WUP_VERSION;

			wp_enqueue_script(
				'wup-bundle-js',
				WUP_URL . 'public/js/build/popup.js',
				[ 'jquery' ],
				$js_ver,
				true
			);

			wp_localize_script( 'wup-bundle-js', 'wupData', [
				'ajax_url'        => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'wup-add-bundle' ),
				'quickview_nonce' => wp_create_nonce( 'wup-quickview' ),
			] );
		}

		// ------------------------------------------------------------------ //
		// Private helpers
		// ------------------------------------------------------------------ //

		/**
		 * Assemble the $bundle_data array passed to layout templates.
		 *
		 * @param WC_Product   $product  Main product.
		 * @param WC_Product[] $products Bundle items (without main product).
		 * @return array
		 */
		private function build_bundle_data( WC_Product $product, array $products ): array {
			$all_items     = array_merge( [ $product ], $products );
			$product_cards = WUP_Variation_Resolver::build_product_cards( $all_items );
			$all_ids       = array_map( static fn( WC_Product $p ) => $p->get_id(), $all_items );
			$variants_map  = WUP_Variation_Resolver::build_variants_map( $all_ids );
			$layout        = max( 1, min( 4, intval( wup_get_option( 'wup_upsell_bundle_layout', 2 ) ) ) );

			return [
				'products'         => $product_cards,
				'variants'         => $variants_map,
				'main_product'     => $product,
				'heading'          => wup_get_option( 'wup_upsell_bundle_heading', 'Frequently Bought Together:' ),
				'add_label'        => wup_get_option( 'wup_upsell_bundle_add_action_label', 'Add All To Cart' ),
				'discount_amount'  => wup_get_option( 'wup_upsell_bundle_discount_amount', '' ),
				'layout'           => $layout,
				'hide_all_options' => wup_get_option( 'wup_upsell_bundle_hide_all_options', 'no' ),
				'hide_when'        => intval( wup_get_option( 'wup_upsell_bundle_hide_options_when', 2 ) ),
				'nonce'            => wp_create_nonce( 'wup-add-bundle' ),
			];
		}

		/** Check if the current post contains the [wup_fbt_bundle] shortcode. */
		private function page_has_bundle_shortcode(): bool {
			global $post;
			return $post instanceof WP_Post && has_shortcode( $post->post_content, 'wup_fbt_bundle' );
		}
	}
}
