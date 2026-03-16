<?php
/**
 * WUP_Cart_Upsell — cross-sell products displayed in the WooCommerce cart.
 *
 * Hooks into woocommerce_cart_collaterals to inject a cross-sell grid below
 * the cart totals. Also registers an AJAX endpoint for adding items and
 * supports a [wup_cart_upsell] shortcode.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WUP_Cart_Upsell' ) ) {

	class WUP_Cart_Upsell {

		/** @var WUP_Cart_Upsell|null */
		private static ?WUP_Cart_Upsell $instance = null;

		public static function get_instance(): self {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			if ( 'yes' === wup_get_option( 'wup_cart_upsell_enable', 'no' ) ) {
				// Place block after cart table; remove WC default to avoid duplicate cross-sell sections.
				add_action( 'woocommerce_after_cart', [ $this, 'render_cart_upsell' ], 1 );
				remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' );
				remove_action( 'woocommerce_after_cart_table', 'woocommerce_cross_sell_display' );
			}

			// AJAX always registered.
			add_action( 'wp_ajax_wup_cart_upsell_add',        [ $this, 'ajax_add_item' ] );
			add_action( 'wp_ajax_nopriv_wup_cart_upsell_add', [ $this, 'ajax_add_item' ] );
			add_action( 'wp_enqueue_scripts',                  [ $this, 'enqueue_assets' ] );

			add_shortcode( 'wup_cart_upsell', [ $this, 'shortcode' ] );
		}

		// ------------------------------------------------------------------ //
		// Rendering
		// ------------------------------------------------------------------ //

		/** Render cross-sell grid inside cart collaterals. */
		public function render_cart_upsell(): void {
			$products = $this->get_products();
			if ( empty( $products ) ) {
				return;
			}

			$product_cards = WUP_Variation_Resolver::build_product_cards( $products );
			$product_ids   = array_column( $product_cards, 'id' );
			$variants      = WUP_Variation_Resolver::build_variants_map( $product_ids );
			$heading       = wup_get_option( 'wup_cart_upsell_heading', __( 'You may also like', 'woo-upsell-pro' ) );
			$hide_options  = 'yes' === wup_get_option( 'wup_cart_upsell_hide_options', 'no' );

			include WUP_TEMPLATES_DIR . 'cart-upsell.php';
		}

		/** [wup_cart_upsell] shortcode handler. */
		public function shortcode( $atts ): string {
			if ( ! WC()->cart ) {
				return '';
			}

			$products = $this->get_products();
			if ( empty( $products ) ) {
				return '';
			}

			$product_cards = WUP_Variation_Resolver::build_product_cards( $products );
			$product_ids   = array_column( $product_cards, 'id' );
			$variants      = WUP_Variation_Resolver::build_variants_map( $product_ids );
			$heading       = wup_get_option( 'wup_cart_upsell_heading', __( 'You may also like', 'woo-upsell-pro' ) );
			$hide_options  = 'yes' === wup_get_option( 'wup_cart_upsell_hide_options', 'no' );

			ob_start();
			include WUP_TEMPLATES_DIR . 'cart-upsell.php';
			return ob_get_clean();
		}

		// ------------------------------------------------------------------ //
		// AJAX
		// ------------------------------------------------------------------ //

		/** Add a single item to the WC cart via AJAX. */
		public function ajax_add_item(): void {
			check_ajax_referer( 'wup-cart-upsell', 'nonce' );

			$product_id   = absint( $_POST['product_id'] ?? 0 );
			$variation_id = absint( $_POST['variation_id'] ?? 0 );
			$quantity     = max( 1, absint( $_POST['quantity'] ?? 1 ) );

			if ( ! $product_id ) {
				wp_send_json_error( [ 'message' => __( 'Invalid product.', 'woo-upsell-pro' ) ] );
			}

			$added = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id );

			if ( ! $added ) {
				wp_send_json_error( [ 'message' => __( 'Could not add to cart.', 'woo-upsell-pro' ) ] );
			}

			WC()->cart->calculate_totals();

			ob_start();
			woocommerce_mini_cart();
			$mini_cart_html = ob_get_clean();

			wp_send_json_success( [
				'cart_count' => WC()->cart->get_cart_contents_count(),
				'fragments'  => [
					'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart_html . '</div>',
				],
			] );
		}

		// ------------------------------------------------------------------ //
		// Assets
		// ------------------------------------------------------------------ //

		/** Enqueue cart-upsell JS only on cart page. */
		public function enqueue_assets(): void {
			if ( is_admin() || ! is_cart() ) {
				return;
			}

			$js_path = WUP_PUBLIC_DIR . 'js/build/cart-upsell.js';
			$js_ver  = file_exists( $js_path ) ? filemtime( $js_path ) : WUP_VERSION;

			wp_enqueue_script(
				'wup-cart-upsell',
				WUP_URL . 'public/js/build/cart-upsell.js',
				[ 'jquery' ],
				$js_ver,
				true
			);

			wp_localize_script( 'wup-cart-upsell', 'wupCartUpsell', [
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wup-cart-upsell' ),
				'i18n'    => [
					'adding' => __( 'Adding…', 'woo-upsell-pro' ),
					'added'  => __( 'Added!', 'woo-upsell-pro' ),
				],
			] );
		}

		// ------------------------------------------------------------------ //
		// Private helpers
		// ------------------------------------------------------------------ //

		/**
		 * Collect cross-sell products based on cart contents + settings.
		 *
		 * @return WC_Product[]
		 */
		private function get_products(): array {
			if ( ! WC()->cart || WC()->cart->is_empty() ) {
				return [];
			}

			$source      = wup_get_option( 'wup_cart_upsell_source', 'related' );
			$categories  = maybe_unserialize( wup_get_option( 'wup_cart_upsell_categories', [] ) );
			$limit       = intval( wup_get_option( 'wup_cart_upsell_limit', 4 ) );
			$excludes    = wup_get_option( 'wup_cart_upsell_excludes_conditions', [] );

			$cart_ids = [];
			foreach ( WC()->cart->get_cart() as $item ) {
				$cart_ids[] = (int) ( $item['product_id'] ?? 0 );
			}
			$cart_ids = array_filter( array_unique( $cart_ids ) );

			$merged = [];
			foreach ( $cart_ids as $pid ) {
				$resolved = WUP_Product_Source::resolve( $pid, [
					'source'       => $source,
					'categories'   => $categories,
					'limit'        => $limit,
					'excludes'     => is_array( $excludes ) ? $excludes : [],
					'cache_suffix' => 'cart_upsell',
				] );
				foreach ( $resolved as $product ) {
					$merged[ $product->get_id() ] = $product;
				}
			}

			// Exclude products already in cart.
			foreach ( $cart_ids as $cid ) {
				unset( $merged[ $cid ] );
			}

			return array_values( array_slice( $merged, 0, $limit ) );
		}
	}
}
