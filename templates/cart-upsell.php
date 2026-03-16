<?php

if (! defined('ABSPATH')) {
    exit;
}

if (empty($products) || ! is_array($products)) {
    return;
}
?>
<div class="wup-cart-upsell">
    <h3 class="wup-cart-upsell__heading">
        <?php echo esc_html($heading ?? __('You might also like', 'woo-upsell-pro')); ?>
    </h3>

    <div class="wup-cart-upsell__grid">
        <?php foreach ($products as $product) : ?>
            <div class="wup-cart-upsell__card" data-product-id="<?php echo esc_attr((string) ($product['id'] ?? 0)); ?>">
                <a href="<?php echo esc_url($product['permalink'] ?? '#'); ?>" class="wup-cart-upsell__image-link">
                    <img
                        src="<?php echo esc_url($product['image_url'] ?? ''); ?>"
                        alt="<?php echo esc_attr($product['name'] ?? ''); ?>"
                        class="wup-cart-upsell__image"
                        loading="lazy"
                    />
                </a>

                <div class="wup-cart-upsell__info">
                    <p class="wup-cart-upsell__name"><?php echo esc_html($product['name'] ?? ''); ?></p>
                    <p class="wup-cart-upsell__price"><?php echo wp_kses_post($product['price_html'] ?? ''); ?></p>
                </div>

                <button
                    type="button"
                    class="wup-cart-upsell__add button"
                    data-product-id="<?php echo esc_attr((string) ($product['id'] ?? 0)); ?>"
                >
                    <?php esc_html_e('+ Add', 'woo-upsell-pro'); ?>
                </button>
            </div>
        <?php endforeach; ?>
    </div>
</div>
