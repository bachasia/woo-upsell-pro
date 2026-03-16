<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$campaigns = get_posts([
    'post_type' => 'wup_campaign',
    'post_status' => 'any',
    'numberposts' => -1,
    'fields' => 'ids',
]);

foreach ($campaigns as $campaign_id) {
    wp_delete_post((int) $campaign_id, true);
}

delete_option('wup_settings');

global $wpdb;
$like = $wpdb->esc_like('_transient_wup_') . '%';
$timeout_like = $wpdb->esc_like('_transient_timeout_wup_') . '%';

$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $like,
        $timeout_like
    )
);
