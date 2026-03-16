<?php
/**
 * Plugin Name: Woo Upsell Pro
 * Plugin URI:  https://github.com/woo-upsell-pro
 * Description: Upsell & cross-sell features for WooCommerce: buy-more-save-more, popup, sidecart, cart upsell, email coupons.
 * Version:     1.0.0
 * Author:      Woo Upsell Pro
 * Text Domain: woo-upsell-pro
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Core constants.
define( 'WUP_FILE',          __FILE__ );
define( 'WUP_VERSION',       '1.0.0' );
define( 'WUP_DIR',           plugin_dir_path( __FILE__ ) );
define( 'WUP_URL',           plugin_dir_url( __FILE__ ) );
define( 'WUP_SLUG',          'woo-upsell-pro/woo-upsell-pro.php' );
define( 'WUP_TEXT_DOMAIN',   'woo-upsell-pro' );

// Coupon type constants.
define( 'WUP_COUPON_BMSM',   'wupbmsm' );
define( 'WUP_COUPON_BUNDLE', 'wupbundle' );

// Directory constants.
define( 'WUP_INCLUDES_DIR',  WUP_DIR . 'includes/' );
define( 'WUP_ADMIN_DIR',     WUP_DIR . 'admin/' );
define( 'WUP_PUBLIC_DIR',    WUP_DIR . 'public/' );
define( 'WUP_TEMPLATES_DIR', WUP_DIR . 'templates/' );

// Boot the loader.
require_once WUP_INCLUDES_DIR . 'class-wup-loader.php';
