<?php
/**
 * Email Coupon Template
 *
 * Rendered by WUP_Email_Coupon_Notification::get_content_html().
 * Variables injected: $order, $email_heading, $coupon_code,
 * $discount_text, $min_order, $expiry_date, $email.
 *
 * @package WooUpsellPro
 */

if (! defined('ABSPATH')) {
    exit;
}

/** @var WC_Order $order */
/** @var string   $email_heading */
/** @var string   $coupon_code */
/** @var string   $discount_text */
/** @var float    $min_order */
/** @var string   $expiry_date */
/** @var WC_Email $email */

do_action('woocommerce_email_header', $email_heading, $email);
?>

<p>
    <?php
    printf(
        /* translators: %s: customer first name */
        esc_html__('Hi %s,', 'woo-upsell-pro'),
        esc_html($order->get_billing_first_name())
    );
    ?>
</p>

<p>
    <?php esc_html_e('Thank you for your recent order! As a token of our appreciation, here\'s an exclusive discount code for your next purchase:', 'woo-upsell-pro'); ?>
</p>

<!-- Coupon code highlight block -->
<div style="text-align:center; margin:24px 0; padding:20px 16px; background:#f7f7f7; border-radius:8px; border:1px solid #e0e0e0;">
    <p style="font-size:12px; color:#888; margin:0 0 8px; text-transform:uppercase; letter-spacing:1px;">
        <?php esc_html_e('Your Coupon Code', 'woo-upsell-pro'); ?>
    </p>
    <p style="font-size:28px; font-weight:bold; letter-spacing:4px; margin:0 0 12px; color:#7f54b3; font-family:monospace;">
        <?php echo esc_html($coupon_code); ?>
    </p>
    <p style="font-size:13px; color:#555; margin:0; line-height:1.6;">
        <?php
        printf(
            /* translators: %s: discount text e.g. "10%" or "$5.00" */
            esc_html__('%s off your next order', 'woo-upsell-pro'),
            esc_html($discount_text)
        );
        ?>
        <?php if ($min_order > 0) : ?>
            &nbsp;&bull;&nbsp;
            <?php
            printf(
                /* translators: %s: formatted minimum order amount */
                esc_html__('Min. order: %s', 'woo-upsell-pro'),
                wp_kses_post(wc_price($min_order))
            );
            ?>
        <?php endif; ?>
        &nbsp;&bull;&nbsp;
        <?php
        printf(
            /* translators: %s: expiry date string */
            esc_html__('Expires: %s', 'woo-upsell-pro'),
            esc_html($expiry_date)
        );
        ?>
    </p>
</div>

<p><?php esc_html_e('Simply enter the code above at checkout to redeem your discount.', 'woo-upsell-pro'); ?></p>

<!-- CTA button -->
<p style="text-align:center; margin:28px 0;">
    <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>"
       style="display:inline-block; padding:13px 28px; background:#7f54b3; color:#ffffff; text-decoration:none; border-radius:4px; font-size:15px; font-weight:600;">
        <?php esc_html_e('Shop Now', 'woo-upsell-pro'); ?>
    </a>
</p>

<p style="font-size:12px; color:#999; text-align:center;">
    <?php esc_html_e('This code is valid for one use only.', 'woo-upsell-pro'); ?>
</p>

<?php do_action('woocommerce_email_footer', $email); ?>
