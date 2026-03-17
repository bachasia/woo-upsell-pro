<?php
/**
 * WUP_Related — related products block for single product pages.
 *
 * Renders a cross-sell grid at a configurable WC hook position.
 * Also registers a [wup_related] shortcode for manual placement.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WUP_Related' ) ) {

	class WUP_Related {

		/** @var WUP_Related|null */
		private static ?WUP_Related $instance = null;

		public static function get_instance(): self {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			if ( 'yes' === wup_get_option( 'wup_related_enable', 'no' ) ) {
				$position = wup_get_option( 'wup_related_position', 'woocommerce_after_single_product_summary' );
				$priority = intval( wup_get_option( 'wup_related_priority', 50 ) );
				add_action( $position, [ $this, 'render_related' ], $priority );
			}

			add_shortcode( 'wup_related', [ $this, 'shortcode' ] );
		}

		// ------------------------------------------------------------------ //
		// Rendering
		// ------------------------------------------------------------------ //

		/** Render related products grid on single product pages. */
		public function render_related(): void {
			$product_id = (int) get_the_ID();
			if ( ! $product_id ) {
				return;
			}

			$output = $this->build_output( $product_id );
			if ( $output ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $output;
			}
		}

		/** [wup_related] shortcode handler. */
		public function shortcode( $atts ): string {
			$product_id = (int) get_the_ID();
			if ( ! $product_id ) {
				return '';
			}

			return $this->build_output( $product_id );
		}

		// ------------------------------------------------------------------ //
		// Private helpers
		// ------------------------------------------------------------------ //

		/**
		 * Build the cross-sell HTML for the given product.
		 *
		 * @param int $product_id Source product ID.
		 * @return string
		 */
		private function build_output( int $product_id ): string {
			$source     = wup_get_option( 'wup_related_source', 'related' );
			$categories = maybe_unserialize( wup_get_option( 'wup_related_categories', [] ) );
			$limit      = intval( wup_get_option( 'wup_related_limit', 4 ) );
			$excludes   = wup_get_option( 'wup_related_excludes_conditions', [] );

			$products = WUP_Product_Source::resolve( $product_id, [
				'source'       => $source,
				'categories'   => $categories,
				'limit'        => $limit,
				'excludes'     => is_array( $excludes ) ? $excludes : [],
				'cache_suffix' => 'related',
			] );

			if ( empty( $products ) ) {
				return '';
			}

			$product_cards = WUP_Variation_Resolver::build_product_cards( $products );
			$product_ids   = array_column( $product_cards, 'id' );
			$variants      = WUP_Variation_Resolver::build_variants_map( $product_ids );
			$heading       = wup_get_option( 'wup_related_heading', __( 'Related Products', 'woo-upsell-pro' ) );
			$hide_options  = 'yes' === wup_get_option( 'wup_related_hide_options', 'no' );

			return WUP_Renderer::cross_sell_display( $product_cards, $variants, [
				'heading'      => $heading,
				'class_wrp'    => 'wup-related-block',
				'hide_options' => $hide_options,
				'add_label'    => __( 'Add to Cart', 'woo-upsell-pro' ),
			] );
		}
	}
}
