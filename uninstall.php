<?php
/**
 * Uninstall handler — runs when the plugin is deleted via WP Admin.
 * Removes all wup_* options from the options table.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete all options with wup_ prefix.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		'wup\_%'
	)
);

// Clean up any autoloaded transients with wup_ prefix.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		'\_transient\_wup\_%',
		'\_transient\_timeout\_wup\_%'
	)
);
