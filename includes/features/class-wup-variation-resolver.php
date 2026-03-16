<?php
/**
 * WUP_Variation_Resolver — builds product card DTOs and variation attribute maps.
 *
 * All methods are static. Accepts WC_Product objects or product IDs.
 * Product card DTO shape is the canonical data contract for all front-end renderers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WUP_Variation_Resolver' ) ) {

	class WUP_Variation_Resolver {

		// ------------------------------------------------------------------ //
		// Public API
		// ------------------------------------------------------------------ //

		/**
		 * Build a variants map for a list of product IDs.
		 *
		 * Returns only in-stock variations.
		 *
		 * @param int[] $product_ids
		 * @return array<int, array<int, array<string, string>>>
		 *   [ parent_id => [ variation_id => [ attr_name => value ] ] ]
		 */
		public static function build_variants_map( array $product_ids ): array {
			$map = [];

			foreach ( $product_ids as $pid ) {
				$product = wc_get_product( (int) $pid );

				if ( ! $product || ! $product->is_type( 'variable' ) ) {
					continue;
				}

				/** @var WC_Product_Variable $product */
				$map[ $product->get_id() ] = [];

				foreach ( $product->get_available_variations() as $variation_data ) {
					$variation = wc_get_product( (int) $variation_data['variation_id'] );

					if ( ! $variation || 'instock' !== $variation->get_stock_status() ) {
						continue;
					}

					$attrs = [];
					foreach ( $variation->get_attributes() as $attr_name => $attr_value ) {
						$attrs[ sanitize_text_field( $attr_name ) ] = sanitize_text_field( $attr_value );
					}

					$map[ $product->get_id() ][ $variation->get_id() ] = $attrs;
				}
			}

			return $map;
		}

		/**
		 * Build product card DTOs for a list of WC_Product objects or product IDs.
		 *
		 * @param WC_Product[]|int[] $products
		 * @param array              $args {
		 *   @type string $thumbnail_size Image size slug. Default 'woocommerce_thumbnail'.
		 * }
		 * @return array[] Array of product card DTOs.
		 */
		public static function build_product_cards( array $products, array $args = [] ): array {
			$thumbnail_size = $args['thumbnail_size'] ?? 'woocommerce_thumbnail';
			$cards = [];

			foreach ( $products as $item ) {
				$product = is_int( $item ) || is_numeric( $item )
					? wc_get_product( (int) $item )
					: $item;

				if ( ! $product instanceof WC_Product ) {
					continue;
				}

				$cards[] = self::build_card( $product, (string) $thumbnail_size );
			}

			return $cards;
		}

		// ------------------------------------------------------------------ //
		// Private — card builder
		// ------------------------------------------------------------------ //

		/**
		 * Build a single product card DTO from a WC_Product.
		 *
		 * @param WC_Product $product
		 * @param string     $thumbnail_size
		 * @return array
		 */
		private static function build_card( WC_Product $product, string $thumbnail_size ): array {
			$is_variable        = $product->is_type( 'variable' );
			$default_attributes = [];
			$attributes_empty   = false;

			if ( $is_variable ) {
				/** @var WC_Product_Variable $product */
				$default_attributes = $product->get_default_attributes();

				// attributes_empty = true if any attribute has no default set.
				foreach ( $product->get_variation_attributes() as $attr_name => $options ) {
					if ( empty( $default_attributes[ sanitize_title( $attr_name ) ] ) ) {
						$attributes_empty = true;
						break;
					}
				}
			}

			return [
				'id'                 => (int) $product->get_id(),
				'parent_id'          => $is_variable
					? (int) $product->get_id()
					: (int) $product->get_id(),
				'product_type'       => $is_variable ? 'variable' : 'simple',
				'default_name'       => wp_strip_all_tags( $product->get_name() ),
				'url'                => esc_url( get_permalink( $product->get_id() ) ),
				'thumbnail'          => woocommerce_get_product_thumbnail( $thumbnail_size, $product->get_id() ),
				'price'              => (float) $product->get_price(),
				'price_html'         => $product->get_price_html(),
				'default_attributes' => $default_attributes,
				'attributes_empty'   => $attributes_empty,
			];
		}
	}
}
