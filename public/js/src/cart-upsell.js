/* Woo Upsell Pro — Cart Upsell Widget */
(function ($) {
    'use strict';

    if (typeof wupCartUpsell === 'undefined') {
        return;
    }

    var cfg = wupCartUpsell;

    // Update ATC button variation_id when variant select changes.
    $(document).on('change', '.wup-cart-upsell-block .wup-cs-variant', function () {
        var $select = $(this);
        var $item   = $select.closest('.wup-cs-item');
        var $btn    = $item.find('.wup-cs-atc');
        var varId   = $select.val();
        $btn.data('variation_id', varId || 0);
        $btn.attr('data-variation_id', varId || 0);
    });

    // Add single item to cart.
    $(document).on('click', '.wup-cart-upsell-block .wup-cs-atc', function (e) {
        e.preventDefault();

        var $btn        = $(this);
        var $item       = $btn.closest('.wup-cs-item');
        var productId   = parseInt($item.data('id'), 10) || parseInt($item.data('parent'), 10);
        var variationId = parseInt($btn.data('variation_id'), 10) || 0;

        if (!productId) {
            return;
        }

        var originalText = $btn.text();
        $btn.text(cfg.i18n.adding).prop('disabled', true);

        $.post(cfg.ajaxUrl, {
            action:       'wup_cart_upsell_add',
            nonce:        cfg.nonce,
            product_id:   productId,
            variation_id: variationId,
            quantity:     1
        })
        .done(function (response) {
            if (response.success) {
                $btn.text(cfg.i18n.added);

                // Update cart count badges.
                var count = response.data.cart_count;
                $('.wup-cart-count, .cart-contents-count').text(count);

                // Trigger WC fragment refresh so mini cart + totals update.
                $(document.body).trigger('wc_fragment_refresh');

                // Reset button after short delay.
                setTimeout(function () {
                    $btn.text(originalText).prop('disabled', false);
                }, 2000);
            } else {
                $btn.text(originalText).prop('disabled', false);
            }
        })
        .fail(function () {
            $btn.text(originalText).prop('disabled', false);
        });
    });

})(jQuery);
