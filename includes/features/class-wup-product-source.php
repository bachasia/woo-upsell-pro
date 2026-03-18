<?php
/**
 * WUP_Product_Source — resolves product lists for upsell/cross-sell features.
 *
 * All methods are static. Results are transient-cached per product+args (12 h).
 * Term resolution and raw SQL are delegated to WUP_Product_Source_Query.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wup-product-source-query.php';

if ( ! class_exists( 'WUP_Product_Source' ) ) {

	class WUP_Product_Source {

		// ------------------------------------------------------------------ //
		// Bootstrap
		// ------------------------------------------------------------------ //

		/** Register WP hooks. Call once at plugin init. */
		public static function init_hooks(): void {
			add_action( 'update_option', [ self::class, 'on_option_updated' ], 10, 1 );
			add_action( 'wp_ajax_wup_clear_transients', [ self::class, 'ajax_clear_transients' ] );
		}

		// ------------------------------------------------------------------ //
		// Public API
		// ------------------------------------------------------------------ //

		/**
		 * Resolve a list of WC_Product objects for the given product + args.
		 *
		 * @param int   $product_id Source product ID.
		 * @param array $args {
		 *   @type string $source        'related'|'cross_sell'|'upsell'|'specific'|'tags'|'default'
		 *   @type array  $categories    Term IDs for 'specific' mode.
		 *   @type int    $limit         Max products to return. Default 5.
		 *   @type array  $excludes      ['conditions' => ['cond' => [], 'valwith' => []]].
		 *   @type bool   $include_self  Prepend source product to result. Default false.
		 *   @type string $cache_suffix  Extra string appended to cache key.
		 * }
		 * @return WC_Product[]
		 */
		public static function resolve( int $product_id, array $args = [] ): array {
			$args = wp_parse_args( $args, [
				'source'       => 'related',
				'categories'   => [],
				'limit'        => 5,
				'excludes'     => [],
				'include_self' => false,
				'cache_suffix' => '',
			] );

			$cache_key = self::cache_key( $product_id, $args );
			$cached    = wup_get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}

			$ids = self::fetch_ids( $product_id, $args );
			// Preserve similarity ranking for semantic source; shuffle only for term-based sources.
			if ( 'semantic' !== $args['source'] ) {
				shuffle( $ids );
			}
			$ids = array_slice( $ids, 0, (int) $args['limit'] );

			if ( $args['include_self'] ) {
				array_unshift( $ids, $product_id );
			}

			$products = array_values( array_filter( array_map( 'wc_get_product', array_unique( $ids ) ) ) );
			wup_set_transient( $cache_key, $products, 12 * HOUR_IN_SECONDS );

			return $products;
		}

		// ------------------------------------------------------------------ //
		// Hooks
		// ------------------------------------------------------------------ //

		/**
		 * Flush source cache when any wup_*source* option is updated.
		 *
		 * @param string $option
		 */
		public static function on_option_updated( string $option ): void {
			if ( str_starts_with( $option, 'wup_' ) && str_contains( $option, 'source' ) ) {
				wup_delete_transients_by_prefix( 'src_' );
			}
		}

		/** AJAX: manually flush all source transients. Requires nonce 'wup-admin'. */
		public static function ajax_clear_transients(): void {
			check_ajax_referer( 'wup-admin' );
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_send_json_error( 'Unauthorized' );
			}
			wup_delete_transients_by_prefix( 'src_' );
			wp_send_json_success();
		}

		// ------------------------------------------------------------------ //
		// Private — ID resolution
		// ------------------------------------------------------------------ //

		/**
		 * Fetch raw product IDs (no caching) for the given source + args.
		 *
		 * @return int[]
		 */
		private static function fetch_ids( int $product_id, array $args ): array {
			$source = $args['source'];

			// AI semantic similarity — uses embedding vectors, no term queries needed.
			// Falls back to 'related' when the product has no embedding yet.
			if ( 'semantic' === $source ) {
				$ids = WUP_Similarity_Search::find_similar(
					$product_id,
					(int) $args['limit'],  // find_similar handles internal over-fetch + filtering
					[ $product_id ]
				);
				if ( ! empty( $ids ) ) {
					return $ids;
				}
				// Graceful fallback: product not yet embedded — serve 'related' source instead.
				$source = 'related';
			}

			// Direct WC meta modes — no SQL needed.
			if ( 'upsell' === $source ) {
				$p = wc_get_product( $product_id );
				return $p ? self::strip_self( array_map( 'intval', $p->get_upsell_ids() ), $product_id ) : [];
			}
			if ( 'default' === $source ) {
				$meta = get_post_meta( $product_id, '_upsell_ids', true );
				return self::strip_self( is_array( $meta ) ? array_map( 'intval', $meta ) : [], $product_id );
			}

			// Term-based modes — delegate term building and SQL to query helper.
			$td = WUP_Product_Source_Query::term_data( $product_id, $source, $args );

			$raw = WUP_Product_Source_Query::run(
				$td['include'],
				$td['exclude'],
				$td['exclude_ids'],
				$td['exclude_keywords'],
				(int) $args['limit'] + 10
			);

			// Apply optional exclusion conditions from $args['excludes'].
			if ( ! empty( $args['excludes']['conditions'] ) ) {
				$extra = WUP_Product_Source_Query::exclusion_ids( $args['excludes']['conditions'] );
				$raw   = array_diff( $raw, $extra );
			}

			return array_values( array_map( 'intval', $raw ) );
		}

		/** Deterministic transient cache key (without the wup_ prefix). */
		private static function cache_key( int $product_id, array $args ): string {
			return 'src_' . md5( serialize( [
				$product_id,
				$args['source'],
				$args['categories'],
				$args['limit'],
				$args['cache_suffix'],
			] ) );
		}

		/** Remove 0 and $product_id from an int array. */
		private static function strip_self( array $ids, int $product_id ): array {
			return array_values( array_filter( $ids, static fn( $id ) => $id > 0 && $id !== $product_id ) );
		}
	}
}
