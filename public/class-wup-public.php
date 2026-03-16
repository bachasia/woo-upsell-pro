<?php

namespace WooUpsellPro\PublicSite;

use WooUpsellPro\Helpers\WUP_Utils;

if (! defined('ABSPATH')) {
    exit;
}

class WUP_Public {
    public function register_hooks(object $loader): void {
        $loader->add_action('wp_enqueue_scripts', $this, 'enqueue_scripts');
        $loader->add_action('wp_enqueue_scripts', $this, 'enqueue_styles');
    }

    public function enqueue_scripts(): void {
        if (! function_exists('is_woocommerce')) {
            return;
        }

        if (! (is_woocommerce() || is_cart())) {
            return;
        }

        $js_base = WUP_PLUGIN_URL . 'public/js/build/';
        $version = WUP_VERSION;

        if (WUP_Utils::is_feature_enabled('popup') && $this->should_load_popup_assets()) {
            wp_enqueue_script('wup-popup', $js_base . 'popup.js', ['jquery'], $version, true);
        }

        if (WUP_Utils::is_feature_enabled('cart_upsell') && is_cart()) {
            wp_enqueue_script('wup-cart-upsell', $js_base . 'cart-upsell.js', [], $version, true);
        }

        if (WUP_Utils::is_feature_enabled('bmsm') && (is_product() || is_cart())) {
            wp_enqueue_script('wup-tier-table', $js_base . 'tier-table.js', [], $version, true);
        }

        $this->localize_scripts();
    }

    public function enqueue_styles(): void {
        if (! function_exists('is_woocommerce')) {
            return;
        }

        if (! (is_woocommerce() || is_cart())) {
            return;
        }

        $css_base = WUP_PLUGIN_URL . 'public/css/build/';
        $version = WUP_VERSION;

        if (WUP_Utils::is_feature_enabled('popup') && $this->should_load_popup_assets()) {
            wp_enqueue_style('wup-popup', $css_base . 'popup.css', [], $version);
        }

        if (WUP_Utils::is_feature_enabled('cart_upsell') && is_cart()) {
            wp_enqueue_style('wup-cart-upsell', $css_base . 'cart-upsell.css', [], $version);
        }

        if (WUP_Utils::is_feature_enabled('bmsm') && (is_product() || is_cart())) {
            wp_enqueue_style('wup-tier-table', $css_base . 'tier-table.css', [], $version);
        }
    }

    private function should_load_popup_assets(): bool {
        return is_shop() || is_product() || is_product_category() || is_product_tag() || is_product_taxonomy();
    }

    private function localize_scripts(): void {
        $data = [
            'rest_url' => esc_url_raw(rest_url('wup/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'store_api_nonce' => wp_create_nonce('wc_store_api'),
            'cart_url' => wc_get_cart_url(),
            'popup_auto_dismiss' => (int) WUP_Utils::get_setting('popup_auto_dismiss', 5),
        ];

        if (wp_script_is('wup-popup', 'enqueued')) {
            wp_localize_script('wup-popup', 'wupPopupData', $data);
        }

        if (wp_script_is('wup-cart-upsell', 'enqueued')) {
            wp_localize_script('wup-cart-upsell', 'wupCartUpsellData', $data);
        }

        if (wp_script_is('wup-tier-table', 'enqueued')) {
            wp_localize_script('wup-tier-table', 'wupTierTableData', $data);
        }
    }
}
