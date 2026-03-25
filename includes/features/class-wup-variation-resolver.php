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

					$attrs = [
						'_price' => (float) $variation->get_price(),
					];
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

			// For variable products, use get_variation_price('min') — more reliable than get_price()
			// which depends on the cached _price meta that can be stale or unset.
			$price      = $is_variable
				? (float) $product->get_variation_price( 'min' )
				: (float) $product->get_price();
			$price_html = $product->get_price_html();
			// Fallback: if WC returns empty price_html but we have a min price, show it.
			if ( $is_variable && empty( $price_html ) && $price > 0 ) {
				$price_html = wc_price( $price );
			}

			// Total published variation count — NOT filtered by stock status.
			// Used for the "show select when >= N options" threshold check, so that a product
			// with e.g. 4 variants (3 OOS) still passes the threshold and shows its select.
			$variation_count = $is_variable ? count( $product->get_children() ) : 0;

			return [
				'id'                 => (int) $product->get_id(),
				'parent_id'          => (int) $product->get_id(),
				'product_type'       => $is_variable ? 'variable' : 'simple',
				'default_name'       => wp_strip_all_tags( $product->get_name() ),
				'url'                => esc_url( get_permalink( $product->get_id() ) ),
				'thumbnail'          => get_the_post_thumbnail( $product->get_id(), $thumbnail_size ),
				'price'              => $price,
				'price_html'         => $price_html,
				'variation_count'    => $variation_count,
				'default_attributes' => $default_attributes,
				'attributes_empty'   => $attributes_empty,
			];
		}
	}
}
