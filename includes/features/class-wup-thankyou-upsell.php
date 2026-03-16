<?php
/**
 * WUP_Thankyou_Upsell — cross-sell products on the WooCommerce order thank-you page.
 *
 * Hooks into woocommerce_thankyou (priority 20) and renders a cross-sell grid
 * below the order details. Products already in the order are excluded.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WUP_Thankyou_Upsell' ) ) {

	class WUP_Thankyou_Upsell {

		/** @var WUP_Thankyou_Upsell|null */
		private static ?WUP_Thankyou_Upsell $instance = null;

		public static function get_instance(): self {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			if ( 'yes' === wup_get_option( 'wup_thankyou_upsell_enable', 'no' ) ) {
				add_action( 'woocommerce_thankyou', [ $this, 'render_thankyou_upsell' ], 20 );
			}
		}

		// ------------------------------------------------------------------ //
		// Rendering
		// ------------------------------------------------------------------ //

		/**
		 * Render cross-sell grid on the thank-you page.
		 *
		 * @param int $order_id WooCommerce order ID.
		 */
		public function render_thankyou_upsell( $order_id ): void {
			$order_product_ids = $this->get_order_product_ids( $order_id );
			if ( empty( $order_product_ids ) ) {
				return;
			}

			$source     = wup_get_option( 'wup_thankyou_upsell_source', 'related' );
			$categories = maybe_unserialize( wup_get_option( 'wup_thankyou_upsell_categories', [] ) );
			$limit      = intval( wup_get_option( 'wup_thankyou_upsell_limit', 4 ) );
			$excludes   = wup_get_option( 'wup_thankyou_upsell_excludes_conditions', [] );

			$merged = [];
			foreach ( $order_product_ids as $pid ) {
				$resolved = WUP_Product_Source::resolve( $pid, [
					'source'       => $source,
					'categories'   => $categories,
					'limit'        => $limit,
					'excludes'     => is_array( $excludes ) ? $excludes : [],
					'cache_suffix' => 'thankyou_upsell',
				] );
				foreach ( $resolved as $product ) {
					$merged[ $product->get_id() ] = $product;
				}
			}

			// Exclude products already purchased in this order.
			foreach ( $order_product_ids as $pid ) {
				unset( $merged[ $pid ] );
			}

			$products = array_values( array_slice( $merged, 0, $limit ) );
			if ( empty( $products ) ) {
				return;
			}

			$product_cards = WUP_Variation_Resolver::build_product_cards( $products );
			$product_ids   = array_column( $product_cards, 'id' );
			$variants      = WUP_Variation_Resolver::build_variants_map( $product_ids );
			$heading       = wup_get_option( 'wup_thankyou_upsell_heading', __( 'You may also like', 'woo-upsell-pro' ) );
			$hide_options  = 'yes' === wup_get_option( 'wup_thankyou_upsell_hide_options', 'no' );

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo WUP_Renderer::cross_sell_display( $product_cards, $variants, [
				'heading'      => $heading,
				'class_wrp'    => 'wup-thankyou-upsell-block',
				'hide_options' => $hide_options,
				'add_label'    => __( 'Add to Cart', 'woo-upsell-pro' ),
			] );
		}

		// ------------------------------------------------------------------ //
		// Private helpers
		// ------------------------------------------------------------------ //

		/**
		 * Extract product IDs from an order.
		 *
		 * @param int $order_id
		 * @return int[]
		 */
		private function get_order_product_ids( $order_id ): array {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return [];
			}

			$ids = [];
			foreach ( $order->get_items() as $item ) {
				/** @var WC_Order_Item_Product $item */
				$pid = (int) $item->get_product_id();
				if ( $pid > 0 ) {
					$ids[] = $pid;
				}
			}

			return array_values( array_unique( $ids ) );
		}
	}
}
