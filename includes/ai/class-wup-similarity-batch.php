<?php
/**
 * WUP_Similarity_Batch — chunked background job to build the similarity index.
 *
 * Uses Action Scheduler (ships with WC 3.6+) for reliable async execution.
 *
 * Full rebuild flow:
 *   schedule_full_rebuild() → cancels stale chunks → schedules handle_chunk(offset=0)
 *   handle_chunk(offset)    → loads ALL vectors once → computes 500 rows → self-schedules next chunk
 *
 * Single-product rebuild:
 *   schedule_single(id) → schedules handle_single(id) → loads ALL vectors → writes one row
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WUP_Similarity_Batch' ) ) {

	class WUP_Similarity_Batch {

		// Products processed per Action Scheduler tick (~0.4s at 5K products).
		public const CHUNK_SIZE = 500;

		// Action Scheduler action names.
		public const ACTION_CHUNK  = 'wup_similarity_build_chunk';
		public const ACTION_SINGLE = 'wup_similarity_build_single';

		// AS group — used for deduplication and cancellation.
		public const GROUP = 'wup-similarity';

		// ── Schedule API ──────────────────────────────────────────────────────────

		/**
		 * Schedule a full index rebuild from offset 0.
		 * Cancels any pending chunk actions first to avoid duplication.
		 */
		public static function schedule_full_rebuild(): void {
			self::cancel_pending_chunks();
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( time(), self::ACTION_CHUNK, [ 'offset' => 0 ], self::GROUP );
			}
		}

		/**
		 * Schedule a single-product similarity rebuild (deduplicates).
		 *
		 * @param int $product_id
		 */
		public static function schedule_single( int $product_id ): void {
			$args = [ 'product_id' => $product_id ];

			if ( function_exists( 'as_has_scheduled_action' )
				&& as_has_scheduled_action( self::ACTION_SINGLE, $args, self::GROUP ) ) {
				return; // Already queued.
			}

			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( time(), self::ACTION_SINGLE, $args, self::GROUP );
			}
		}

		// ── Action Scheduler handlers ─────────────────────────────────────────────

		/**
		 * Process one chunk of the full rebuild.
		 * Self-schedules the next chunk via Action Scheduler until all products are done.
		 *
		 * @param int $offset Index into the sorted product ID array.
		 */
		public static function handle_chunk( int $offset = 0 ): void {
			// Allow higher memory limit for background jobs (wp_raise_memory_limit is safe to call multiple times).
			wp_raise_memory_limit( 'admin' );

			$all_vectors = self::load_all_vectors();
			if ( empty( $all_vectors ) ) {
				return;
			}

			$product_ids = array_keys( $all_vectors );
			$chunk       = array_slice( $product_ids, $offset, self::CHUNK_SIZE );

			if ( empty( $chunk ) ) {
				update_option( 'wup_similarity_last_full_build', time(), false );
				return;
			}

			foreach ( $chunk as $product_id ) {
				WUP_Similarity_Index::build_for_product( $product_id, $all_vectors );
			}

			$next_offset = $offset + self::CHUNK_SIZE;

			if ( $next_offset < count( $product_ids ) ) {
				// Schedule next chunk with 5s delay to reduce DB pressure.
				if ( function_exists( 'as_schedule_single_action' ) ) {
					as_schedule_single_action(
						time() + 5,
						self::ACTION_CHUNK,
						[ 'offset' => $next_offset ],
						self::GROUP
					);
				}
			} else {
				// All chunks done.
				update_option( 'wup_similarity_last_full_build', time(), false );
			}
		}

		/**
		 * Rebuild the similarity index row for a single product.
		 * Called after a product is re-embedded (its vector changed).
		 *
		 * @param int $product_id
		 */
		public static function handle_single( int $product_id ): void {
			wp_raise_memory_limit( 'admin' );

			$all_vectors = self::load_all_vectors();
			if ( empty( $all_vectors ) || ! isset( $all_vectors[ $product_id ] ) ) {
				return;
			}

			WUP_Similarity_Index::build_for_product( $product_id, $all_vectors );
		}

		// ── Helpers ───────────────────────────────────────────────────────────────

		/**
		 * Count published products that have a stored embedding (used by admin UI).
		 *
		 * @return int
		 */
		public static function count_embedded_products(): int {
			global $wpdb;
			return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_wup_embedding'"
			);
		}

		// ── Private ───────────────────────────────────────────────────────────────

		/**
		 * Load all published product embedding vectors in a single SQL round-trip.
		 * Filters by active model (LEFT JOIN allows legacy vectors without model meta).
		 *
		 * Memory note: 5K products x 256 dims x ~16 bytes/float ≈ 20MB. Safe in background context.
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
				            ON pm2.post_id = pm.post_id AND pm2.meta_key = %s
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

			// Free raw SQL result from memory before computation phase.
			unset( $rows );

			return $vectors;
		}

		/** Cancel all pending chunk actions (used before scheduling a fresh full rebuild). */
		private static function cancel_pending_chunks(): void {
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( self::ACTION_CHUNK, null, self::GROUP );
			}
		}
	}
}
