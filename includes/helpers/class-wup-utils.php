<?php
/**
 * WUP utility helper functions (procedural).
 *
 * Thin wrappers around WP/WC core functions used across the plugin.
 * No class needed — functions are namespaced by the wup_ prefix.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return term IDs for all product categories assigned to a product.
 *
 * @param int $product_id WC product post ID.
 * @return int[]
 */
function wup_get_product_category_ids( int $product_id ): array {
	$terms = get_the_terms( $product_id, 'product_cat' );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return [];
	}
	return wp_list_pluck( $terms, 'term_id' );
}

/**
 * Return slugs for all product categories assigned to a product.
 *
 * @param int $product_id WC product post ID.
 * @return string[]
 */
function wup_get_product_category_slugs( int $product_id ): array {
	$terms = get_the_terms( $product_id, 'product_cat' );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return [];
	}
	return wp_list_pluck( $terms, 'slug' );
}

/**
 * Return slugs for all product tags assigned to a product.
 *
 * @param int $product_id WC product post ID.
 * @return string[]
 */
function wup_get_product_tag_slugs( int $product_id ): array {
	$terms = get_the_terms( $product_id, 'product_tag' );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return [];
	}
	return wp_list_pluck( $terms, 'slug' );
}

/**
 * Count the number of form input elements in an HTML string.
 * Counts <input>, <select>, and <textarea> tags.
 *
 * @param string $html Raw HTML string.
 * @return int
 */
function wup_count_form_inputs( string $html ): int {
	if ( empty( $html ) ) {
		return 0;
	}
	$count = preg_match_all( '/<(input|select|textarea)[\s>]/i', $html, $matches );
	return $count === false ? 0 : $count;
}

/**
 * Retrieve a plugin option value with a fallback default.
 *
 * @param string $key     Option key (without wup_ prefix — callers pass full key).
 * @param mixed  $default Fallback value when option is not set.
 * @return mixed
 */
function wup_get_option( string $key, mixed $default = '' ): mixed {
	return get_option( $key, $default );
}

/**
 * Format a price using WooCommerce's wc_price() helper.
 *
 * @param float $price Raw price amount.
 * @return string HTML price string (e.g. "<span class="woocommerce-Price-amount">$9.99</span>").
 */
function wup_format_price( float $price ): string {
	if ( ! function_exists( 'wc_price' ) ) {
		return esc_html( number_format( $price, 2 ) );
	}
	return wc_price( $price );
}
