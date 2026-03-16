<?php
/**
 * WUP_Popup — Post-Add-to-Cart lightbox popup feature.
 *
 * Shows upsell products in a modal after any item is added to the WC cart.
 * Shell is injected via wp_footer; content is loaded on-demand via AJAX.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WUP_Popup' ) ) {

	class WUP_Popup {

		/** @var WUP_Popup|null */
		private static ?WUP_Popup $instance = null;

		public static function get_instance(): self {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			// Conditional hooks — only when popup is enabled.
			if ( wup_get_option( 'wup_upsell_popup_enable', 'no' ) === 'yes' ) {
				add_action( 'wp_footer', [ $this, 'render_popup_shell' ] );
				add_filter( 'woocommerce_add_to_cart_fragments', [ $this, 'add_cart_fragment' ] );
			}

			// AJAX endpoints always registered (JS needs them).
			add_action( 'wp_ajax_wup_get_popup',        [ $this, 'ajax_get_popup' ] );
			add_action( 'wp_ajax_nopriv_wup_get_popup', [ $this, 'ajax_get_popup' ] );
			add_action( 'wp_enqueue_scripts',            [ $this, 'enqueue_assets' ] );
		}

		// ------------------------------------------------------------------ //
		// Frontend output
		// ------------------------------------------------------------------ //

		/** Inject hidden modal shell into page footer. */
		public function render_popup_shell(): void {
			echo '<div id="wup-popup-modal" style="display:none;">';
			echo '<div class="wup-popup-overlay"></div>';
			echo '<div class="wup-popup-inner"></div>';
			echo '</div>';
		}

		// ------------------------------------------------------------------ //
		// AJAX
		// ------------------------------------------------------------------ //

		/** Return rendered popup HTML for the just-added product. */
		public function ajax_get_popup(): void {
			check_ajax_referer( 'wup-popup', 'nonce' );

			$product_id = absint( $_POST['product_id'] ?? 0 );
			if ( ! $product_id ) {
				wp_send_json_error( 'Invalid product.' );
			}

			$source   = wup_get_option( 'wup_upsell_popup_source', 'related' );
			$limit    = intval( wup_get_option( 'wup_upsell_popup_limit', 10 ) );
			$products = WUP_Product_Source::resolve( $product_id, [
				'source'       => $source,
				'limit'        => $limit,
				'cache_suffix' => 'popup',
			] );

			$product_cards = WUP_Variation_Resolver::build_product_cards( $products );
			$product_ids   = array_column( $product_cards, 'id' );
			$variants_map  = WUP_Variation_Resolver::build_variants_map( $product_ids );

			$wc_product   = wc_get_product( $product_id );
			$product_name = $wc_product ? $wc_product->get_name() : '';
			$heading_tpl  = wup_get_option( 'wup_upsell_popup_heading_text', 'Frequently bought with [product_name]' );

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$popup_data = [
				'product_id'   => $product_id,
				'product_name' => $product_name,
				'products'     => $product_cards,
				'variants'     => $variants_map,
				'heading'      => str_replace( '[product_name]', $product_name, $heading_tpl ),
				'add_label'    => wup_get_option( 'wup_upsell_popup_add_action_label', 'Add To Cart' ),
				'hide_items'   => wup_get_option( 'wup_upsell_popup_hide_items', 'no' ),
				'hide_options' => wup_get_option( 'wup_upsell_popup_hide_options', 'no' ),
				'layout'       => wup_get_option( 'wup_upsell_popup_products_layout', 'default' ),
				'nonce'        => wp_create_nonce( 'wup-popup' ),
			];

			ob_start();
			include WUP_TEMPLATES_DIR . 'popup/lightbox.php';
			$html = ob_get_clean();

			wp_send_json_success( [ 'html' => $html ] );
		}

		// ------------------------------------------------------------------ //
		// Fragments
		// ------------------------------------------------------------------ //

		/**
		 * Keep cart count badge in sync after fragment refresh.
		 *
		 * @param array $fragments
		 * @return array
		 */
		public function add_cart_fragment( array $fragments ): array {
			$fragments['.wup-cart-count'] = '<span class="wup-cart-count">'
				. WC()->cart->get_cart_contents_count()
				. '</span>';
			return $fragments;
		}

		// ------------------------------------------------------------------ //
		// Assets
		// ------------------------------------------------------------------ //

		/** Enqueue popup JS and pass localised config. */
		public function enqueue_assets(): void {
			if ( is_admin() ) {
				return;
			}

			wp_enqueue_script( 'wup-popup' );

			wp_localize_script( 'wup-popup', 'wupPopup', [
				'ajax_url'     => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'wup-popup' ),
				'cart_url'     => wc_get_cart_url(),
				'checkout_url' => wc_get_checkout_url(),
			] );
		}
	}
}
