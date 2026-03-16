<?php

namespace WooUpsellPro\Helpers;

if (! defined('ABSPATH')) {
    exit;
}

class WUP_Utils {
    private const FEATURE_MAP = [
        'popup' => 'enable_popup',
        'cart_upsell' => 'enable_cart_upsell',
        'bmsm' => 'enable_bmsm',
        'buy_more_save_more' => 'enable_bmsm',
        'email_coupon' => 'enable_email_coupon',
    ];

    public static function get_setting(string $key, mixed $default = null): mixed {
        $settings = get_option('wup_settings', []);

        if (is_array($settings) && array_key_exists($key, $settings)) {
            return $settings[$key];
        }

        $option_key = str_starts_with($key, 'wup_') ? $key : 'wup_' . $key;
        $option_value = get_option($option_key, null);

        if ($option_value !== null) {
            return $option_value;
        }

        return $default;
    }

    public static function get_nested_setting(string $group, string $key, mixed $default = null): mixed {
        $settings = get_option('wup_settings', []);

        if (is_array($settings) && isset($settings[$group]) && is_array($settings[$group]) && array_key_exists($key, $settings[$group])) {
            return $settings[$group][$key];
        }

        return $default;
    }

    public static function is_feature_enabled(string $feature): bool {
        $key = self::FEATURE_MAP[$feature] ?? $feature;
        $value = self::get_setting($key, true);

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes'], true);
        }

        return (bool) $value;
    }

    public static function get_plugin_dir(): string {
        return WUP_PLUGIN_DIR;
    }

    public static function get_plugin_url(): string {
        return WUP_PLUGIN_URL;
    }
}
