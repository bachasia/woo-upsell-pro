/* Woo Upsell Pro — BMSM Tier Table */
(function ($) {
    'use strict';

    if (typeof wupBmsm === 'undefined') {
        return;
    }

    var cfg = wupBmsm;

    /** Highlight the active tier row/card based on current cart value. */
    function updateActiveTier(currentValue) {
        var $tiers = $('.wup-bmsm-tier, .wup-bmsm-card, .wup-bmsm-s2-row, .wup-bmsm-s3-card, .wup-bmsm-s5-row');
        var bestMin = -1;

        $tiers.removeClass('wup-bmsm-tier-active');

        $tiers.each(function () {
            var min = parseFloat($(this).data('min')) || 0;
            if (currentValue >= min && min > bestMin) {
                bestMin = min;
            }
        });

        if (bestMin >= 0) {
            $tiers.filter('[data-min="' + bestMin + '"]').addClass('wup-bmsm-tier-active');
        }

        syncStyle5CtaQuantity();
    }

    /** Keep style5 CTA quantity synced with active row. */
    function syncStyle5CtaQuantity() {
        var $activeRow = $('.wup-bmsm-style5 .wup-bmsm-s5-row.wup-bmsm-tier-active').first();
        var qty = parseInt($activeRow.data('min'), 10) || 1;
        var $btn = $('.wup-bmsm-style5 .wup-bmsm-s5-cta').first();

        if (!$btn.length) {
            return;
        }

        $btn.attr('data-quantity', qty);

        var labelTpl = $btn.data('label-template');
        if (typeof labelTpl === 'string' && labelTpl.indexOf('{quantity}') !== -1) {
            $btn.text(labelTpl.replace('{quantity}', qty));
        }
    }

    // Init highlight on page load.
    updateActiveTier(cfg.currentValue || 0);

    // Re-highlight after WooCommerce updates cart fragments.
    $(document.body).on('updated_cart_totals wc_fragments_refreshed', function () {
        // Re-read current value from localized data (updated via fragment).
        var currentValue = cfg.conditional === 'items'
            ? parseInt($('.wup-cart-count').first().text(), 10) || 0
            : cfg.currentValue;
        updateActiveTier(currentValue);
    });

    // Style5: click row to select tier + sync CTA quantity.
    $(document).on('click', '.wup-bmsm-style5 .wup-bmsm-s5-row', function () {
        var $row = $(this);
        $row
            .closest('.wup-bmsm-style5')
            .find('.wup-bmsm-s5-row')
            .removeClass('wup-bmsm-tier-active');

        $row.addClass('wup-bmsm-tier-active');
        syncStyle5CtaQuantity();
    });

    // "Buy {quantity}" helper CTA — add that many of the current product to cart.
    $(document).on('click', '.wup-bmsm-add-btn', function (e) {
        e.preventDefault();

        var $btn      = $(this);
        var quantity  = parseInt($btn.data('quantity'), 10) || 1;
        var productId = parseInt($('input[name="product_id"], input[name="add-to-cart"]').first().val(), 10) || 0;

        if (!productId) {
            return;
        }

        $btn.prop('disabled', true);

        $.post(cfg.ajaxUrl, {
            action:     'woocommerce_add_to_cart',
            product_id: productId,
            quantity:   quantity
        })
        .done(function () {
            $(document.body).trigger('wc_fragment_refresh');
        })
        .always(function () {
            $btn.prop('disabled', false);
        });
    });

})(jQuery);
