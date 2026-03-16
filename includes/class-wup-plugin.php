<?php

namespace WooUpsellPro;

use WooUpsellPro\Campaigns\WUP_Campaign_CPT;
use WooUpsellPro\Campaigns\WUP_Campaign_Manager;
use WooUpsellPro\Api\WUP_Rest_Controller;
use WooUpsellPro\Features\WUP_Buy_More_Save_More;
use WooUpsellPro\Features\WUP_Popup;
use WooUpsellPro\Features\WUP_Cart_Upsell;
use WooUpsellPro\Features\WUP_Email_Coupon;
use WooUpsellPro\Admin\WUP_Admin;

if (! defined('ABSPATH')) {
    exit;
}

class WUP_Plugin {
    private WUP_Loader $loader;

    public function __construct() {
        $this->loader = new WUP_Loader();
        $this->register_core_hooks();
        $this->register_feature_hooks();
    }

    public function run(): void {
        $this->loader->run();
    }

    private function register_core_hooks(): void {
        $campaign_cpt = new WUP_Campaign_CPT();
        $this->loader->add_action('init', $campaign_cpt, 'register');

        if (class_exists('WooUpsellPro\\Api\\WUP_Rest_Controller')) {
            $campaign_manager = new WUP_Campaign_Manager();
            $rest_controller = new WUP_Rest_Controller($campaign_manager);
            $this->loader->add_action('rest_api_init', $rest_controller, 'register_routes');
        }

        if (class_exists('WooUpsellPro\\PublicSite\\WUP_Public')) {
            $public = new \WooUpsellPro\PublicSite\WUP_Public();
            $public->register_hooks($this->loader);
        }

        if (is_admin() && class_exists('WooUpsellPro\\Admin\\WUP_Admin')) {
            $admin = new WUP_Admin();
            $admin->register_hooks($this->loader);
        }

        if (is_admin() && class_exists('WooUpsellPro\\Admin\\WUP_Settings_Page')) {
            $this->loader->add_filter(
                'woocommerce_get_settings_pages',
                $this,
                'register_settings_page',
                20,
                1
            );
        }
    }

    public function register_settings_page(array $pages): array {
        $pages[] = new \WooUpsellPro\Admin\WUP_Settings_Page();

        return $pages;
    }

    private function register_feature_hooks(): void {
        if (class_exists('WooUpsellPro\\Features\\WUP_Buy_More_Save_More')) {
            $feature = new WUP_Buy_More_Save_More();
            $feature->register_hooks($this->loader);
        }

        if (class_exists('WooUpsellPro\\Features\\WUP_Popup')) {
            $feature = new WUP_Popup();
            $feature->register_hooks($this->loader);
        }

        if (class_exists('WooUpsellPro\\Features\\WUP_Cart_Upsell')) {
            $feature = new WUP_Cart_Upsell();
            $feature->register_hooks($this->loader);
        }

        if (class_exists('WooUpsellPro\\Features\\WUP_Email_Coupon')) {
            $feature = new WUP_Email_Coupon();
            $feature->register_hooks($this->loader);
        }
    }
}
