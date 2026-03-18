<?php
/**
 * WUP_Similarity_Search — cosine similarity search over product embedding vectors.
 *
 * Flow:
 *  1. Load query product's vector via WUP_Product_Embedder::get_embedding()
 *  2. Batch-load all published product vectors (single SQL, model-filtered)
 *  3. Dot-product ranking (OpenAI vectors are L2-normalised → dot = cosine)
 *  4. Post-filter top-20 candidates through WC visibility API
 *  5. Cache result in transient (12 h, invalidated on product save)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WUP_Similarity_Search' ) ) {

	class WUP_Similarity_Search {

		// Number of raw candidates to run WC visibility check on.
		// Over-fetching here is cheap since wc_get_product() hits WC object cache.
		private const CANDIDATE_POOL = 20;

		// Transient prefix — kept short to fit WP's 172-char option name limit.
		private const CACHE_PREFIX = 'wup_emb_';

		// TTL matches existing source transient cache (12 h).
		private const CACHE_TTL = 12 * HOUR_IN_SECONDS;

		// ── Public API ────────────────────────────────────────────────────────────

		/**
		 * Find products most similar to $product_id by embedding cosine similarity.
		 *
		 * Returns an empty array when:
		 * - Query product has no embedding (not yet embedded or model mismatch).
		 * - No other products have embeddings.
		 *
		 * This lets WUP_Product_Source fall back gracefully to 'related' source.
		 *
		 * @param int   $product_id   Query product.
		 * @param int   $limit        Number of results to return.
		 * @param int[] $exclude_ids  Product IDs to exclude from results (always include query product).
		 * @return int[]              Product IDs sorted by similarity desc.
		 */
		public static function find_similar( int $product_id, int $limit = 5, array $exclude_ids = [] ): array {
			// Always exclude the query product itself.
			$exclude_ids[] = $product_id;
			$exclude_ids   = array_unique( array_map( 'intval', $exclude_ids ) );

			$cache_key = self::CACHE_PREFIX . $product_id . '_' . $limit;
			$cached    = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}

			// Fast path: pre-computed index (O(1) SELECT vs O(n) vector scan).
			$result = self::try_index_lookup( $product_id, $limit, $exclude_ids );

			// Slow path: real-time cosine search (index miss or stale model).
			if ( null === $result ) {
				$result = self::compute_similar( $product_id, $limit, $exclude_ids );
			}

			set_transient( $cache_key, $result, self::CACHE_TTL );

			return $result;
		}

		/**
		 * Invalidate similarity cache for a specific product.
		 * Called on product save so next request recomputes fresh results.
		 *
		 * @param int $product_id
		 */
		public static function invalidate_cache( int $product_id ): void {
			// Delete all transients for this product regardless of $limit variant.
			global $wpdb;
			$like = $wpdb->esc_like( '_transient_' . self::CACHE_PREFIX . $product_id . '_' ) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$like
			) );
		}

		// ── Index fast path ───────────────────────────────────────────────────────

		/**
		 * Try to serve results from the pre-computed similarity index (O(1) SELECT).
		 * Returns null on index miss or model mismatch — triggers real-time fallback.
		 *
		 * @param int   $product_id
		 * @param int   $limit
		 * @param int[] $exclude_ids
		 * @return int[]|null  IDs sorted by similarity desc, or null on miss.
		 */
		private static function try_index_lookup( int $product_id, int $limit, array $exclude_ids ): ?array {
			$indexed = WUP_Similarity_Index::get_similar( $product_id );
			if ( null === $indexed ) {
				return null; // Miss or stale — caller falls back to real-time.
			}

			$candidates = array_values( array_diff( $indexed, $exclude_ids ) );

			return self::filter_visible( $candidates, $limit );
		}

	// ── Core computation (no caching) ─────────────────────────────────────────

		/** @param int[] $exclude_ids */
		private static function compute_similar( int $product_id, int $limit, array $exclude_ids ): array {
			// Step 1 — load query vector; bail if not yet embedded.
			$query_vector = WUP_Product_Embedder::get_embedding( $product_id );
			if ( null === $query_vector ) {
				return [];
			}

			// Step 2 — load all published product vectors with matching model (single SQL).
			$all_vectors = self::load_all_vectors();
			if ( empty( $all_vectors ) ) {
				return [];
			}

			// Step 3 — rank by dot product (= cosine for L2-normalised OpenAI vectors).
			$scores = [];
			foreach ( $all_vectors as $pid => $vector ) {
				if ( in_array( $pid, $exclude_ids, true ) ) {
					continue;
				}
				$scores[ $pid ] = self::dot( $query_vector, $vector );
			}

			if ( empty( $scores ) ) {
				return [];
			}

			arsort( $scores );

			// Step 4 — take top candidates, then filter by WC visibility.
			$candidates = array_slice( array_keys( $scores ), 0, self::CANDIDATE_POOL, true );

			return self::filter_visible( $candidates, $limit );
		}

		// ── SQL helpers ───────────────────────────────────────────────────────────

		/**
		 * Load all published product vectors in a single SQL round-trip.
		 *
		 * Uses LEFT JOIN on _wup_embedding_model so legacy vectors (before model versioning
		 * was introduced) are still included — they were generated with the default model.
		 *
		 * @return array<int, float[]>  Map of product_id => vector.
		 */
		private static function load_all_vectors(): array {
			global $wpdb;

			$active_model = get_option( 'wup_ai_embedding_model', 'text-embedding-3-small' );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value AS vector
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 LEFT  JOIN {$wpdb->postmeta} pm2
				            ON pm2.post_id  = pm.post_id
				            AND pm2.meta_key = %s
				 WHERE pm.meta_key   = %s
				   AND p.post_type   = 'product'
				   AND p.post_status = 'publish'
				   AND ( pm2.meta_value = %s OR pm2.meta_value IS NULL )",
				WUP_Product_Embedder::META_MODEL,
				WUP_Product_Embedder::META_VECTOR,
				$active_model
			) );

			$vectors = [];
			foreach ( $rows as $row ) {
				$decoded = json_decode( $row->vector, true );
				if ( is_array( $decoded ) ) {
					$vectors[ (int) $row->post_id ] = $decoded;
				}
			}

			return $vectors;
		}

		// ── Visibility filter ─────────────────────────────────────────────────────

		/**
		 * Filter candidate IDs through WC visibility rules and return up to $limit valid IDs.
		 *
		 * Uses WC API on up to CANDIDATE_POOL products (fast — WC object cache).
		 * This is safer than SQL JOINs on visibility terms, which couple to WC internals.
		 *
		 * @param int[] $candidates  Pre-sorted candidate IDs (best first).
		 * @param int   $limit
		 * @return int[]
		 */
		private static function filter_visible( array $candidates, int $limit ): array {
			$hide_oos = 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' );
			$valid    = [];

			foreach ( $candidates as $id ) {
				$product = wc_get_product( $id );
				if ( ! $product ) {
					continue;
				}
				// is_visible() checks catalog_visibility meta.
				if ( ! $product->is_visible() ) {
					continue;
				}
				// Respect store-level hide-OOS setting.
				if ( $hide_oos && ! $product->is_in_stock() ) {
					continue;
				}

				$valid[] = $id;

				if ( count( $valid ) >= $limit ) {
					break;
				}
			}

			return $valid;
		}

		// ── Math ──────────────────────────────────────────────────────────────────

		/**
		 * Dot product of two equal-length float vectors.
		 * For L2-normalised OpenAI vectors: dot(A,B) == cosine_similarity(A,B).
		 *
		 * @param float[] $a
		 * @param float[] $b
		 * @return float
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
