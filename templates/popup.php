<?php

if (! defined('ABSPATH')) {
    exit;
}
?>
<div id="wup-popup" class="wup-popup" role="dialog" aria-hidden="true" aria-label="<?php echo esc_attr__('Product added to cart', 'woo-upsell-pro'); ?>" aria-modal="true" tabindex="-1">
    <div class="wup-popup__overlay" aria-hidden="true"></div>

    <div class="wup-popup__container" role="document">
        <button type="button" class="wup-popup__close" aria-label="<?php echo esc_attr__('Close', 'woo-upsell-pro'); ?>">&times;</button>

        <div class="wup-popup__progress" aria-hidden="true">
            <div class="wup-popup__progress-bar"></div>
        </div>

        <div class="wup-popup__added">
            <span class="wup-popup__checkmark" aria-hidden="true">&#10003;</span>
            <img class="wup-popup__image" src="" alt="" loading="lazy" />
            <div class="wup-popup__info">
                <p class="wup-popup__product-name"></p>
                <p class="wup-popup__product-price"></p>
            </div>
        </div>

        <div class="wup-popup__upsell" style="display:none">
            <p class="wup-popup__upsell-heading"><?php esc_html_e('Customers also bought', 'woo-upsell-pro'); ?></p>
            <div class="wup-popup__upsell-product">
                <img class="wup-popup__upsell-image" src="" alt="" loading="lazy" />
                <div class="wup-popup__upsell-info">
                    <p class="wup-popup__upsell-name"></p>
                    <p class="wup-popup__upsell-price"></p>
                </div>
                <button type="button" class="wup-popup__upsell-add button" data-product-id="">
                    <?php esc_html_e('+ Add', 'woo-upsell-pro'); ?>
                </button>
            </div>
        </div>

        <div class="wup-popup__actions">
            <a class="wup-popup__view-cart button alt" href="<?php echo esc_url(wc_get_cart_url()); ?>">
                <?php esc_html_e('View Cart', 'woo-upsell-pro'); ?>
            </a>
            <button type="button" class="wup-popup__continue">
                <?php esc_html_e('Continue Shopping', 'woo-upsell-pro'); ?>
            </button>
        </div>
    </div>
</div>
