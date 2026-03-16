<?php

namespace WooUpsellPro;

use WooUpsellPro\Campaigns\WUP_Campaign_CPT;

if (! defined('ABSPATH')) {
    exit;
}

class WUP_Activator {
    private const DEFAULT_SETTINGS = [
        'enable_popup' => true,
        'enable_cart_upsell' => true,
        'enable_bmsm' => true,
        'enable_email_coupon' => true,
        'popup_auto_dismiss' => 5,
        'popup_heading' => 'Customers also bought',
        'cart_upsell_heading' => 'You might also like',
        'cart_upsell_max_products' => 3,
        'bmsm_tiers' => [
            ['qty' => 2, 'discount' => 5, 'type' => 'percent'],
            ['qty' => 5, 'discount' => 10, 'type' => 'percent'],
            ['qty' => 10, 'discount' => 20, 'type' => 'percent'],
        ],
        'email_coupon' => [
            'enabled' => true,
            'discount_type' => 'percent',
            'discount_amount' => 10,
            'expiry_days' => 30,
            'min_order_amount' => 0,
            'email_heading' => 'Thank you! Here\'s a gift.',
        ],
    ];

    public static function activate(): void {
        $cpt = new WUP_Campaign_CPT();
        $cpt->register();

        $existing = get_option('wup_settings', []);

        if (! is_array($existing)) {
            $existing = [];
        }

        update_option('wup_settings', array_replace_recursive(self::DEFAULT_SETTINGS, $existing), false);

        flush_rewrite_rules();
    }
}
