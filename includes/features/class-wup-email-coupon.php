<?php
/**
 * Email Coupon Handler
 *
 * Listens to order processing status, generates a unique WC coupon,
 * and triggers branded coupon email to customer. One coupon per order.
 *
 * @package WooUpsellPro\Features
 */

namespace WooUpsellPro\Features;

use WooUpsellPro\Helpers\WUP_Utils;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * WUP_Email_Coupon
 *
 * Handles post-purchase coupon generation and email dispatch.
 */
class WUP_Email_Coupon {

    /**
     * Register hooks via loader.
     *
     * @param object $loader WUP_Loader instance.
     */
    public function register_hooks(object $loader): void {
        $loader->add_action('woocommerce_order_status_processing', $this, 'handle_order_processing', 10, 1);
        $loader->add_filter('woocommerce_email_classes', $this, 'register_email_class', 10, 1);
    }

    /**
     * Main handler for order processing status change.
     *
     * @param int $order_id WC Order ID.
     */
    public function handle_order_processing(int $order_id): void {
        if (! $this->is_feature_enabled()) {
            return;
        }

        $order = wc_get_order($order_id);
        if (! $order || $this->has_coupon_been_sent($order_id)) {
            return;
        }

        $settings = $this->get_settings();
        $coupon_code = $this->generate_coupon_code();

        $coupon_id = $this->create_coupon($coupon_code, $settings);
        if (is_wp_error($coupon_id)) {
            wc_get_logger()->error(
                'WUP Email Coupon: failed to create coupon for order ' . $order_id,
                ['source' => 'wup-email-coupon']
            );
            return;
        }

        // Trigger WC email action — picked up by WUP_Email_Coupon_Notification.
        do_action('wup_send_coupon_email', $order_id, $coupon_code);

        $order->update_meta_data('_wup_coupon_sent', 'yes');
        $order->update_meta_data('_wup_coupon_code', $coupon_code);
        $order->save();
    }

    /**
     * Generate a unique coupon code.
     *
     * @return string Coupon code like 'wup-a1b2c3d4'.
     */
    public function generate_coupon_code(): string {
        return 'wup-' . wp_generate_password(8, false, false);
    }

    /**
     * Create a WooCommerce coupon programmatically.
     *
     * @param string $code     Coupon code.
     * @param array  $settings Email coupon settings.
     * @return int|\WP_Error Coupon ID on success.
     */
    public function create_coupon(string $code, array $settings): int|\WP_Error {
        try {
            $coupon = new \WC_Coupon();
            $coupon->set_code($code);
            $coupon->set_discount_type($settings['discount_type'] ?? 'percent');
            $coupon->set_amount((float) ($settings['discount_amount'] ?? 10));

            $expiry_days = (int) ($settings['expiry_days'] ?? 30);
            if ($expiry_days > 0) {
                $coupon->set_date_expires(strtotime("+{$expiry_days} days"));
            }

            $min_order = (float) ($settings['min_order_amount'] ?? 0);
            if ($min_order > 0) {
                $coupon->set_minimum_amount((string) $min_order);
            }

            $coupon->set_usage_limit(1);
            $coupon->set_usage_limit_per_user(1);
            $coupon->set_individual_use(true);
            $coupon->update_meta_data('_wup_source', 'email_coupon');

            return $coupon->save();
        } catch (\Exception $e) {
            return new \WP_Error('wup_coupon_create_failed', $e->getMessage());
        }
    }

    /**
     * Register the email notification class with WooCommerce.
     *
     * @param array $emails Existing WC email classes.
     * @return array Modified email classes.
     */
    public function register_email_class(array $emails): array {
        if (! isset($emails['WUP_Email_Coupon_Notification'])) {
            $emails['WUP_Email_Coupon_Notification'] = new \WooUpsellPro\Features\WUP_Email_Coupon_Notification();
        }
        return $emails;
    }

    /**
     * Check if coupon has already been sent for this order.
     *
     * @param int $order_id Order ID.
     * @return bool True if already sent.
     */
    public function has_coupon_been_sent(int $order_id): bool {
        $order = wc_get_order($order_id);
        if (! $order) {
            return false;
        }
        return 'yes' === $order->get_meta('_wup_coupon_sent', true);
    }

    /**
     * Check if the email coupon feature is enabled.
     *
     * @return bool
     */
    private function is_feature_enabled(): bool {
        $global_enabled = WUP_Utils::is_feature_enabled('email_coupon');

        if (! $global_enabled) {
            return false;
        }

        $settings = $this->get_settings();
        return ! empty($settings['enabled']);
    }

    /**
     * Get email coupon settings from wup_settings option.
     *
     * @return array Settings array with defaults.
     */
    private function get_settings(): array {
        $all_settings = get_option('wup_settings', []);
        $defaults = [
            'enabled'         => false,
            'discount_type'   => 'percent',
            'discount_amount' => 10,
            'expiry_days'     => 30,
            'min_order_amount' => 0,
            'email_heading'   => __("Thank you! Here's a gift.", 'woo-upsell-pro'),
        ];
        $email_settings = $all_settings['email_coupon'] ?? [];
        return array_merge($defaults, $email_settings);
    }
}
