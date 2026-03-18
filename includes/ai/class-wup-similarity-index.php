<?php
/**
 * WUP_Similarity_Index — reads/writes pre-computed similarity data in wp_wup_similar.
 *
 * All methods static (matches codebase pattern).
 * The table stores top-10 similar product IDs with scores per product, keyed by product_id.
 * O(1) SELECT per lookup — used by WUP_Similarity_Search as fast-path before real-time fallback.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WUP_Similarity_Index' ) ) {

	class WUP_Similarity_Index {

		// Max similar products stored per product.
		private const TOP_N = 10;

		// ── Public API ────────────────────────────────────────────────────────────

		/**
		 * Fetch pre-computed similar product IDs for a product.
		 *
		 * Returns null when: no row exists, or stored model != active model (stale).
		 * Null triggers the caller to fall back to real-time cosine search.
		 *
		 * @param int $product_id
		 * @return int[]|null Sorted by similarity desc, or null on miss/stale.
		 */
		public static function get_similar( int $product_id ): ?array {
			global $wpdb;

			$row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				'SELECT similar_ids, model FROM ' . self::table() . ' WHERE product_id = %d',
				$product_id
			) );

			if ( ! $row ) {
				return null;
			}

			// Model mismatch = embeddings regenerated with different model; index is stale.
			$active_model = get_option( 'wup_ai_embedding_model', 'text-embedding-3-small' );
			if ( $row->model !== $active_model ) {
				return null;
			}

			$pairs = json_decode( $row->similar_ids, true );
			if ( ! is_array( $pairs ) ) {
				return null;
			}

			// Each pair is [product_id, score]; return IDs only.
			return array_map( static fn( $pair ) => (int) $pair[0], $pairs );
		}

		/**
		 * Compute and upsert the top-N similarity row for one product.
		 *
		 * @param int   $product_id  Target product.
		 * @param array $all_vectors Map of product_id => float[] (pre-loaded by caller).
		 * @return bool True if row written, false if product has no vector in the map.
		 */
		public static function build_for_product( int $product_id, array $all_vectors ): bool {
			if ( ! isset( $all_vectors[ $product_id ] ) ) {
				return false;
			}

			$query_vec = $all_vectors[ $product_id ];
			$scores    = [];

			foreach ( $all_vectors as $pid => $vec ) {
				if ( $pid === $product_id ) {
					continue;
				}
				$scores[ $pid ] = self::dot( $query_vec, $vec );
			}

			if ( empty( $scores ) ) {
				return false;
			}

			arsort( $scores );
			$top = array_slice( $scores, 0, self::TOP_N, true );

			// Store as [[id, score], ...] to preserve scores for future use.
			$pairs = [];
			foreach ( $top as $pid => $score ) {
				$pairs[] = [ $pid, round( $score, 4 ) ];
			}

			global $wpdb;
			$model = get_option( 'wup_ai_embedding_model', 'text-embedding-3-small' );

			$wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				self::table(),
				[
					'product_id'  => $product_id,
					'similar_ids' => wp_json_encode( $pairs ),
					'model'       => $model,
					'dims'        => count( $query_vec ),
					'computed_at' => current_time( 'mysql', true ),
				],
				[ '%d', '%s', '%s', '%d', '%s' ]
			);

			return true;
		}

		/**
		 * Delete the index row for one product (triggers recompute on next batch job).
		 *
		 * @param int $product_id
		 */
		public static function invalidate( int $product_id ): void {
			global $wpdb;
			$wpdb->delete( self::table(), [ 'product_id' => $product_id ], [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		/** Truncate the entire index table (e.g. after model change). */
		public static function invalidate_all(): void {
			global $wpdb;
			$wpdb->query( 'TRUNCATE TABLE ' . self::table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		/** Return the number of rows (indexed products) in the table. */
		public static function count_indexed(): int {
			global $wpdb;
			return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		/**
		 * Return the most recent computed_at datetime string, or null if table is empty.
		 *
		 * @return string|null UTC datetime or null.
		 */
		public static function get_last_computed(): ?string {
			global $wpdb;
			$val = $wpdb->get_var( 'SELECT MAX(computed_at) FROM ' . self::table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return ( $val && '0000-00-00 00:00:00' !== $val ) ? $val : null;
		}

		// ── Private helpers ───────────────────────────────────────────────────────

		/** Fully-qualified table name. */
		private static function table(): string {
			global $wpdb;
			return $wpdb->prefix . 'wup_similar';
		}

		/**
		 * Dot product of two equal-length float vectors.
		 * For L2-normalised OpenAI vectors: dot(A,B) == cosine_similarity(A,B).
		 *
		 * @param float[] $a
		 * @param float[] $b
		 */
		private static function dot( array $a, array $b ): float {
			$sum = 0.0;
			foreach ( $a as $i => $v ) {
				$sum += $v * ( $b[ $i ] ?? 0.0 );
			}
			return $sum;
		}
	}
}
