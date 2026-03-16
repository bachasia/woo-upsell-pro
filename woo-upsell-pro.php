<?php
/**
 * Plugin Name: Woo Upsell Pro
 * Plugin URI: https://example.com/woo-upsell-pro
 * Description: Smart upsell campaigns for WooCommerce.
 * Version: 1.0.0
 * Requires PHP: 8.0
 * Requires at least: 6.4
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 * Text Domain: woo-upsell-pro
 * Domain Path: /languages
 * License: GPL-2.0+
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('WUP_VERSION')) {
    define('WUP_VERSION', '1.0.0');
}

if (! defined('WUP_PLUGIN_FILE')) {
    define('WUP_PLUGIN_FILE', __FILE__);
}

if (! defined('WUP_PLUGIN_DIR')) {
    define('WUP_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (! defined('WUP_PLUGIN_URL')) {
    define('WUP_PLUGIN_URL', plugin_dir_url(__FILE__));
}

add_action('before_woocommerce_init', static function () {
    if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', WUP_PLUGIN_FILE, true);
    }
});

function wup_autoload(string $class): void
{
    $prefix = 'WooUpsellPro\\';

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));

    $base = WUP_PLUGIN_DIR . 'includes/';
    if (strpos($relative, 'Admin\\') === 0) {
        $base = WUP_PLUGIN_DIR . 'admin/';
        $relative = substr($relative, strlen('Admin\\'));
    } elseif (strpos($relative, 'PublicSite\\') === 0) {
        $base = WUP_PLUGIN_DIR . 'public/';
        $relative = substr($relative, strlen('PublicSite\\'));
    }

    $parts = explode('\\', $relative);
    $class_name = array_pop($parts);
    $folders = array_map(
        static fn (string $part): string => strtolower(str_replace('_', '-', $part)),
        $parts
    );

    $class_file = 'class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';
    $path = $base . (empty($folders) ? '' : implode('/', $folders) . '/') . $class_file;

    if (file_exists($path)) {
        require_once $path;
    }
}

function wup_load_autoloader(): void
{
    $vendor_autoload = WUP_PLUGIN_DIR . 'vendor/autoload.php';

    if (file_exists($vendor_autoload)) {
        require_once $vendor_autoload;
    }

    spl_autoload_register('wup_autoload');
}

wup_load_autoloader();

register_activation_hook(WUP_PLUGIN_FILE, ['WooUpsellPro\\WUP_Activator', 'activate']);

function wup_is_woocommerce_active(): bool
{
    return class_exists('WooCommerce');
}

function wup_missing_wc_notice(): void
{
    if (! current_user_can('activate_plugins')) {
        return;
    }

    echo '<div class="notice notice-error"><p>';
    echo esc_html__('Woo Upsell Pro requires WooCommerce to be active.', 'woo-upsell-pro');
    echo '</p></div>';
}

function wup_init_plugin(): void
{
    if (! wup_is_woocommerce_active()) {
        add_action('admin_notices', 'wup_missing_wc_notice');
        return;
    }

    if (! class_exists('WooUpsellPro\\WUP_Plugin')) {
        add_action('admin_notices', static function () {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('Woo Upsell Pro failed to load plugin classes.', 'woo-upsell-pro');
            echo '</p></div>';
        });
        return;
    }

    $plugin = new WooUpsellPro\WUP_Plugin();
    $plugin->run();
}

add_action('plugins_loaded', 'wup_init_plugin');
