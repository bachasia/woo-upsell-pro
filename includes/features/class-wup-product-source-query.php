<?php
/**
 * WUP_Product_Source_Query — SQL query builder and term-resolution helpers.
 *
 * Internal helper used exclusively by WUP_Product_Source.
 * Separated to keep each file under 200 lines.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WUP_Product_Source_Query' ) ) {

	class WUP_Product_Source_Query {

		// ------------------------------------------------------------------ //
		// Term-data builder
		// ------------------------------------------------------------------ //

		/**
		 * Build include/exclude term ID sets for a given source mode.
		 *
		 * @param int    $product_id
		 * @param string $source  'related'|'cross_sell'|'specific'|'tags'
		 * @param array  $args    Resolver args (categories, etc.).
		 * @return array{ include: int[], exclude: int[], exclude_ids: int[], exclude_keywords: string[] }
		 */
		public static function term_data( int $product_id, string $source, array $args ): array {
			$include          = [];
			$exclude          = [];
			$exclude_ids      = [ 0, $product_id ];
			$exclude_keywords = [];

			// Always exclude out-of-stock and catalog-hidden products.
			$vis = wc_get_product_visibility_term_ids();
			if ( ! empty( $vis['exclude-from-catalog'] ) ) {
				$exclude[] = (int) $vis['exclude-from-catalog'];
			}
			if ( ! empty( $vis['outofstock'] ) ) {
				$exclude[] = (int) $vis['outofstock'];
			}

			switch ( $source ) {
				case 'related':
					$cats    = wc_get_product_terms( $product_id, 'product_cat' );
					$deepest = $cats[0] ?? null;
					foreach ( $cats as $cat ) {
						if ( $deepest && $cat->parent === $deepest->term_id ) {
							$deepest = $cat;
						}
					}
					if ( $deepest && $deepest->term_id > 0 ) {
						$include[] = (int) $deepest->term_id;
					}
					break;

				case 'cross_sell':
					foreach ( wc_get_product_terms( $product_id, 'product_cat' ) as $cat ) {
						$exclude[] = (int) $cat->term_id;
					}
					break;

				case 'specific':
					foreach ( (array) $args['categories'] as $id ) {
						$include[] = (int) $id;
					}
					break;

				case 'tags':
					$raw = get_post_meta( $product_id, '_wup_tags', true );
					if ( ! empty( $raw ) ) {
						foreach ( explode( ',', $raw ) as $name ) {
							$term = get_term_by( 'name', trim( $name ), 'product_tag' );
							if ( $term ) {
								$include[] = (int) $term->term_id;
							}
						}
					} else {
						$tags = wc_get_product_terms( $product_id, 'product_tag' );
						if ( ! empty( $tags ) ) {
							$include[] = (int) $tags[0]->term_id;
						}
					}
					break;
			}

			return compact( 'include', 'exclude', 'exclude_ids', 'exclude_keywords' );
		}

		/**
		 * Convert exclusion conditions (product_cat/product_tag) into product IDs.
		 *
		 * @param array $conditions { cond: string[], valwith: mixed[] }
		 * @return int[]
		 */
		public static function exclusion_ids( array $conditions ): array {
			$cond_list = $conditions['cond']    ?? [];
			$val_list  = $conditions['valwith'] ?? [];
			$tids      = [];

			foreach ( $cond_list as $pos => $cond ) {
				$val = $val_list[ $pos ] ?? '';
				if ( 'product_cat' === $cond ) {
					$tids[] = (int) $val;
				} elseif ( 'product_tag' === $cond ) {
					$term = get_term_by( 'name', trim( (string) $val ), 'product_tag' );
					if ( $term ) {
						$tids[] = (int) $term->term_id;
					}
				}
			}

			return self::ids_for_terms( $tids );
		}

		// ------------------------------------------------------------------ //
		// Raw SQL
		// ------------------------------------------------------------------ //

		/**
		 * Run a raw SQL query returning product IDs that match term criteria.
		 *
		 * int[] arrays are sanitised with absint before interpolation.
		 * Keywords use $wpdb->esc_like() for safe LIKE clauses.
		 *
		 * @param int[]    $includes         Term IDs to INNER JOIN (include).
		 * @param int[]    $excludes         Term IDs to LEFT JOIN + IS NULL (exclude).
		 * @param int[]    $exclude_ids      Product IDs to exclude via NOT IN.
		 * @param string[] $exclude_keywords Post title substrings to reject.
		 * @param int      $limit            Maximum rows to return.
		 * @return string[]
		 */
		public static function run(
			array $includes,
			array $excludes,
			array $exclude_ids,
			array $exclude_keywords,
			int $limit
		): array {
			global $wpdb;

			$join  = '';
			$where = "WHERE 1=1 AND p.post_status = 'publish' AND p.post_type = 'product'";

			if ( ! empty( $excludes ) ) {
				$in    = implode( ',', array_map( 'absint', $excludes ) );
				$join .= " LEFT JOIN (
					SELECT object_id FROM {$wpdb->term_relationships}
					WHERE term_taxonomy_id IN ({$in})
				) AS exc_j ON exc_j.object_id = p.ID";
				$where .= ' AND exc_j.object_id IS NULL';
			}

			if ( ! empty( $includes ) ) {
				$in    = implode( ',', array_map( 'absint', $includes ) );
				$join .= " INNER JOIN (
					SELECT object_id FROM {$wpdb->term_relationships}
					INNER JOIN {$wpdb->term_taxonomy} USING (term_taxonomy_id)
					WHERE term_id IN ({$in})
				) AS inc_j ON inc_j.object_id = p.ID";
			}

			if ( ! empty( $exclude_ids ) ) {
				$in     = implode( ',', array_map( 'absint', $exclude_ids ) );
				$where .= " AND p.ID NOT IN ({$in})";
			}

			foreach ( $exclude_keywords as $kw ) {
				$like   = '%' . $wpdb->esc_like( $kw ) . '%';
				$where .= $wpdb->prepare( ' AND p.post_title NOT LIKE %s', $like );
			}

			$lim = absint( $limit );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (array) $wpdb->get_col(
				"SELECT DISTINCT p.ID FROM {$wpdb->posts} p {$join} {$where} LIMIT {$lim}"
			);
		}

		/**
		 * Return product IDs that belong to any of the given term IDs.
		 *
		 * @param int[] $term_ids
		 * @return int[]
		 */
		public static function ids_for_terms( array $term_ids ): array {
			if ( empty( $term_ids ) ) {
				return [];
			}
			global $wpdb;
			$in = implode( ',', array_map( 'absint', $term_ids ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return array_map( 'intval', (array) $wpdb->get_col(
				"SELECT DISTINCT object_id FROM {$wpdb->term_relationships}
				 INNER JOIN {$wpdb->term_taxonomy} USING (term_taxonomy_id)
				 WHERE term_id IN ({$in})"
			) );
		}
	}
}
