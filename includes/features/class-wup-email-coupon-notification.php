<?php
/**
 * WC Email Coupon Notification
 *
 * Extends WC_Email to send a branded coupon email to the customer
 * after their order reaches processing status.
 *
 * @package WooUpsellPro\Features
 */

namespace WooUpsellPro\Features;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * WUP_Email_Coupon_Notification
 *
 * Registered with WC email system so it appears under WC > Settings > Emails.
 * Triggered via do_action('wup_send_coupon_email', $order_id, $coupon_code).
 */
class WUP_Email_Coupon_Notification extends \WC_Email {

    /** @var string Coupon code to pass to template. */
    public string $coupon_code = '';

    /**
     * Constructor — configure email metadata and template paths.
     */
    public function __construct() {
        $this->id             = 'wup_coupon_notification';
        $this->customer_email = true;
        $this->title          = __('Upsell Pro: Post-Purchase Coupon', 'woo-upsell-pro');
        $this->description    = __('Sent to customers after order processing with a unique discount coupon.', 'woo-upsell-pro');
        $this->heading        = __("Thank you! Here's a gift.", 'woo-upsell-pro');
        $this->subject        = __('Your exclusive discount code is inside', 'woo-upsell-pro');

        // Template paths — WC will look here for overrides.
        $this->template_base  = WUP_PLUGIN_DIR . 'templates/';
        $this->template_html  = 'email-coupon.php';
        $this->template_plain = ''; // HTML-only email for MVP.

        // Hook to custom action fired from WUP_Email_Coupon handler.
        add_action('wup_send_coupon_email', [$this, 'trigger'], 10, 2);

        parent::__construct();
    }

    /**
     * Trigger the email send.
     *
     * @param int    $order_id    WC Order ID.
     * @param string $coupon_code Generated coupon code.
     */
    public function trigger(int $order_id, string $coupon_code): void {
        $this->setup_locale();

        $order = wc_get_order($order_id);
        if (! $order) {
            $this->restore_locale();
            return;
        }

        $this->object      = $order;
        $this->coupon_code = $coupon_code;
        $this->recipient   = $order->get_billing_email();

        // Override heading from settings if configured.
        $all_settings = get_option('wup_settings', []);
        $email_heading = $all_settings['email_coupon']['email_heading'] ?? '';
        if (! empty($email_heading)) {
            $this->heading = $email_heading;
        }

        if (! $this->is_enabled() || ! $this->get_recipient()) {
            $this->restore_locale();
            return;
        }

        $this->send(
            $this->get_recipient(),
            $this->get_subject(),
            $this->get_content(),
            $this->get_headers(),
            $this->get_attachments()
        );

        $this->restore_locale();
    }

    /**
     * Build HTML email content from template.
     *
     * @return string Rendered HTML.
     */
    public function get_content_html(): string {
        return wc_get_template_html(
            $this->template_html,
            $this->get_template_vars(),
            '',
            $this->template_base
        );
    }

    /**
     * Plain text fallback — not implemented for MVP.
     *
     * @return string Empty string.
     */
    public function get_content_plain(): string {
        return '';
    }

    /**
     * Assemble variables passed to the email template.
     *
     * @return array Template variables.
     */
    private function get_template_vars(): array {
        $order    = $this->object;
        $settings = $this->get_email_coupon_settings();

        // Build human-readable discount text.
        $amount        = (float) ($settings['discount_amount'] ?? 10);
        $discount_type = $settings['discount_type'] ?? 'percent';
        $discount_text = 'percent' === $discount_type
            ? $amount . '%'
            : wc_price($amount);

        // Build expiry date string.
        $expiry_days = (int) ($settings['expiry_days'] ?? 30);
        $expiry_date = $expiry_days > 0
            ? date_i18n(get_option('date_format'), strtotime("+{$expiry_days} days"))
            : __('No expiry', 'woo-upsell-pro');

        return [
            'order'         => $order,
            'email_heading' => $this->get_heading(),
            'coupon_code'   => $this->coupon_code,
            'discount_text' => $discount_text,
            'min_order'     => (float) ($settings['min_order_amount'] ?? 0),
            'expiry_date'   => $expiry_date,
            'email'         => $this,
            'sent_to_admin' => false,
            'plain_text'    => false,
        ];
    }

    /**
     * Get email coupon settings from wup_settings option.
     *
     * @return array
     */
    private function get_email_coupon_settings(): array {
        $all = get_option('wup_settings', []);
        return $all['email_coupon'] ?? [];
    }
}
