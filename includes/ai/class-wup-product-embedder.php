<?php
/**
 * WUP_Product_Embedder — generates and stores product embedding vectors.
 *
 * - build_embed_text()  : compose text from product fields per settings toggles
 * - embed_product()     : call API + store result (used by save hook + batch)
 * - store_embedding()   : write _wup_embedding + _wup_embedding_model meta
 * - get_embedding()     : read meta, returns null if missing or model mismatch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WUP_Product_Embedder' ) ) {

	class WUP_Product_Embedder {

		// post_meta keys.
		public const META_VECTOR = '_wup_embedding';
		public const META_MODEL  = '_wup_embedding_model';

		// Max chars sent to OpenAI (~512 tokens at ~4 chars/token).
		private const MAX_CHARS = 2000;

		// ── Public API ────────────────────────────────────────────────────────────

		/**
		 * Build the text string used as embedding input for a product.
		 *
		 * Format: "{title}. Tags: {csv}. Attributes: {csv}. Category: {leaf}. {short_desc}"
		 * Empty or disabled segments are omitted entirely (no dangling labels).
		 *
		 * @param int $product_id
		 * @return string Plain text, max 2000 chars.
		 */
		public static function build_embed_text( int $product_id ): string {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				return '';
			}

			$parts = [];

			// Title — always included.
			$parts[] = wp_strip_all_tags( $product->get_name() );

			// Tags — included when wup_ai_embed_use_tags = 'yes' (default).
			if ( 'yes' === get_option( 'wup_ai_embed_use_tags', 'yes' ) ) {
				$tags = wc_get_product_terms( $product_id, 'product_tag', [ 'fields' => 'names' ] );
				if ( ! empty( $tags ) ) {
					$parts[] = 'Tags: ' . implode( ', ', $tags );
				}
			}

			// Attributes — included when wup_ai_embed_use_attributes = 'yes' (default).
			if ( 'yes' === get_option( 'wup_ai_embed_use_attributes', 'yes' ) ) {
				$attr_text = self::build_attribute_text( $product );
				if ( $attr_text ) {
					$parts[] = 'Attributes: ' . $attr_text;
				}
			}

			// Category — leaf-most only, included when wup_ai_embed_use_category = 'yes' (default).
			if ( 'yes' === get_option( 'wup_ai_embed_use_category', 'yes' ) ) {
				$cats = wc_get_product_terms( $product_id, 'product_cat', [ 'fields' => 'all' ] );
				if ( ! empty( $cats ) ) {
					// Deepest category = largest parent chain (sort by whether it has a parent).
					usort( $cats, fn( $a, $b ) => (int) ( $b->parent > 0 ) - (int) ( $a->parent > 0 ) );
					$parts[] = 'Category: ' . $cats[0]->name;
				}
			}

			// Short description — included when wup_ai_embed_use_description = 'yes' (default).
			if ( 'yes' === get_option( 'wup_ai_embed_use_description', 'yes' ) ) {
				$desc = wp_strip_all_tags( $product->get_short_description() );
				if ( $desc ) {
					$parts[] = $desc;
				}
			}

			$text = implode( '. ', array_filter( $parts ) );

			return substr( $text, 0, self::MAX_CHARS );
		}

		/**
		 * Generate embedding via API and store in post_meta.
		 *
		 * @param int $product_id
		 * @return bool True on success, false on API error or missing text.
		 */
		public static function embed_product( int $product_id ): bool {
			$text = self::build_embed_text( $product_id );
			if ( ! $text ) {
				return false;
			}

			$model = get_option( 'wup_ai_embedding_model', 'text-embedding-3-small' );

			try {
				$vector = WUP_Embedding_Client::make()->embed( $text );
				self::store_embedding( $product_id, $vector, $model );
				return true;
			} catch ( RuntimeException $e ) {
				// Log via WC logger — non-fatal, site continues working.
				if ( function_exists( 'wc_get_logger' ) ) {
					wc_get_logger()->warning( $e->getMessage(), [ 'source' => 'wup-ai' ] );
				}
				return false;
			}
		}

		/**
		 * Write both meta keys atomically.
		 * Single entry point for meta writes — called by embed_product() and batch AJAX handler.
		 *
		 * @param int     $product_id
		 * @param float[] $vector
		 * @param string  $model  e.g. 'text-embedding-3-small'
		 */
		public static function store_embedding( int $product_id, array $vector, string $model ): void {
			update_post_meta( $product_id, self::META_VECTOR, wp_json_encode( $vector ) );
			update_post_meta( $product_id, self::META_MODEL, sanitize_text_field( $model ) );
		}

		/**
		 * Read stored embedding vector for a product.
		 *
		 * Returns null when:
		 * - No embedding stored yet.
		 * - Stored model != active model (forces re-embed after model switch).
		 *
		 * @param int $product_id
		 * @return float[]|null
		 */
		public static function get_embedding( int $product_id ): ?array {
			$active_model  = get_option( 'wup_ai_embedding_model', 'text-embedding-3-small' );
			$stored_model  = get_post_meta( $product_id, self::META_MODEL, true );
			$stored_vector = get_post_meta( $product_id, self::META_VECTOR, true );

			if ( ! $stored_vector ) {
				return null;
			}

			// Stored model present and different = embedding space incompatible.
			if ( $stored_model && $stored_model !== $active_model ) {
				return null;
			}

			$vector = json_decode( $stored_vector, true );

			return is_array( $vector ) ? $vector : null;
		}

		// ── Private helpers ───────────────────────────────────────────────────────

		/**
		 * Build a flat attribute string from a product.
		 * For variable products, merges all variation attribute values into one list.
		 *
		 * @param WC_Product $product
		 * @return string e.g. "Size: S/M/L, Color: Red/Blue"
		 */
		private static function build_attribute_text( WC_Product $product ): string {
			$attrs = $product->get_attributes();
			if ( empty( $attrs ) ) {
				return '';
			}

			$parts = [];
			foreach ( $attrs as $attr ) {
				$name = wc_attribute_label( $attr->get_name() );

				if ( $attr->is_taxonomy() ) {
					$terms  = $attr->get_terms();
					$values = $terms ? wp_list_pluck( $terms, 'name' ) : [];
				} else {
					$values = $attr->get_options();
				}

				if ( ! empty( $values ) ) {
					$parts[] = $name . ': ' . implode( '/', array_map( 'wp_strip_all_tags', $values ) );
				}
			}

			return implode( ', ', $parts );
		}
	}
}
