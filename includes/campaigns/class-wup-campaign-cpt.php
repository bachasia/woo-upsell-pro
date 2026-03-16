<?php

namespace WooUpsellPro\Campaigns;

if (! defined('ABSPATH')) {
    exit;
}

class WUP_Campaign_CPT {
    public const POST_TYPE = 'wup_campaign';
    public const TYPES = ['popup', 'cart_upsell', 'bmsm', 'email_coupon'];
    public const STATUSES = ['active', 'paused', 'draft'];

    public function register(): void {
        $this->register_post_type();
        $this->register_post_meta();
    }

    public function register_post_type(): void {
        register_post_type(
            self::POST_TYPE,
            [
                'labels' => [
                    'name' => __('Upsell Campaigns', 'woo-upsell-pro'),
                    'singular_name' => __('Upsell Campaign', 'woo-upsell-pro'),
                ],
                'public' => false,
                'show_ui' => false,
                'show_in_menu' => false,
                'show_in_rest' => false,
                'supports' => ['title'],
                'capabilities' => [
                    'edit_post' => 'manage_woocommerce',
                    'read_post' => 'manage_woocommerce',
                    'delete_post' => 'manage_woocommerce',
                    'edit_posts' => 'manage_woocommerce',
                    'edit_others_posts' => 'manage_woocommerce',
                    'publish_posts' => 'manage_woocommerce',
                    'read_private_posts' => 'manage_woocommerce',
                    'delete_posts' => 'manage_woocommerce',
                ],
                'map_meta_cap' => false,
                'rewrite' => false,
                'query_var' => false,
                'has_archive' => false,
            ]
        );
    }

    public function register_post_meta(): void {
        $meta_definitions = [
            '_wup_campaign_type' => ['type' => 'string', 'default' => 'popup'],
            '_wup_campaign_status' => ['type' => 'string', 'default' => 'draft'],
            '_wup_campaign_rules' => ['type' => 'object', 'default' => []],
            '_wup_campaign_products' => ['type' => 'array', 'default' => []],
            '_wup_campaign_discount_tiers' => ['type' => 'array', 'default' => []],
            '_wup_campaign_settings' => ['type' => 'object', 'default' => []],
        ];

        foreach ($meta_definitions as $meta_key => $args) {
            register_post_meta(
                self::POST_TYPE,
                $meta_key,
                [
                    'type' => $args['type'],
                    'single' => true,
                    'default' => $args['default'],
                    'show_in_rest' => false,
                ]
            );
        }
    }
}
