<?php
/**
 * WUP transient/cache helper functions (procedural).
 *
 * Thin wrappers around WP transient API with a consistent wup_ key prefix.
 * All keys are prefixed with 'wup_' automatically to avoid collisions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Store data in a transient.
 *
 * @param string $key    Cache key (will be prefixed with 'wup_').
 * @param mixed  $data   Data to cache.
 * @param int    $expiry Expiry in seconds. Default 1 hour.
 */
function wup_set_transient( string $key, mixed $data, int $expiry = 3600 ): void {
	set_transient( 'wup_' . $key, $data, $expiry );
}

/**
 * Retrieve data from a transient. Returns false when expired or not found.
 *
 * @param string $key Cache key (will be prefixed with 'wup_').
 * @return mixed Cached data or false.
 */
function wup_get_transient( string $key ): mixed {
	return get_transient( 'wup_' . $key );
}

/**
 * Delete all transients whose key starts with the given prefix.
 *
 * Uses a LIKE query against the options table since WP has no native
 * bulk-delete-by-prefix API for transients.
 *
 * @param string $prefix Key prefix to match (will be prepended with '_transient_wup_').
 */
function wup_delete_transients_by_prefix( string $prefix ): void {
	global $wpdb;

	$like = $wpdb->esc_like( '_transient_wup_' . $prefix ) . '%';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$keys = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
			$like
		)
	);

	if ( empty( $keys ) ) {
		return;
	}

	foreach ( $keys as $option_name ) {
		// Strip the _transient_ prefix to get the raw transient name.
		$transient_name = str_replace( '_transient_', '', $option_name );
		delete_transient( $transient_name );
	}
}
