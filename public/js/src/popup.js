/* Woo Upsell Pro - Bundle & Popup JS */
(function($) {
    'use strict';

    // Bundle: checkbox toggle (show/hide item visually)
    $(document).on('change', '.wup-item-checkbox', function() {
        const $item = $(this).closest('[data-id]');
        $item.toggleClass('wup-item-deselected', !this.checked);
        wupUpdateBundleTotal();
    });

    // Bundle: update total price display
    function wupUpdateBundleTotal() {
        let total = 0;
        $('.wup-bundle-items [data-price]').each(function() {
            const $item = $(this);
            const $checkbox = $item.find('.wup-item-checkbox');
            if ($checkbox.length && !$checkbox.is(':checked')) {
                return; // skip deselected items
            }
            total += parseFloat($item.data('price')) || 0;
        });
        $('.wup-bundle-total').text(wupFormatPrice(total));
    }

    function wupFormatPrice(price) {
        return parseFloat(price).toFixed(2);
    }

    // Bundle: add all selected items to cart
    $(document).on('click', '.wup-add-bundle', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const $container = $btn.closest('.wup-fbt-bundle');
        const nonce = $container.find('.wup-bundle-items').data('nonce') || (typeof wupData !== 'undefined' ? wupData.nonce : '');
        const items = [];

        $container.find('.wup-bundle-items [data-id]').each(function() {
            const $item = $(this);
            const $checkbox = $item.find('.wup-item-checkbox');
            if ($checkbox.length && !$checkbox.is(':checked')) {
                return; // skip unchecked
            }

            const item = {
                product_id: parseInt($item.data('parent'), 10) || parseInt($item.data('id'), 10),
                variation_id: parseInt($item.data('variation-id'), 10) || 0,
                quantity: 1,
                variation: {}
            };

            $item.find('.wup-variant-select').each(function() {
                const attrKey = 'attribute_' + $(this).attr('name').replace('variation_', '');
                item.variation[attrKey] = $(this).val();
                // Store selected variation ID on item element for next add
                if ($(this).val()) {
                    $item.attr('data-variation-id', $(this).find(':selected').val());
                }
            });

            items.push(item);
        });

        if (!items.length) {
            return;
        }

        $btn.prop('disabled', true).addClass('wup-loading');

        $.post(wupData.ajax_url, {
            action: 'wup_add_bundle',
            nonce: nonce,
            items: JSON.stringify(items)
        }, function(response) {
            $btn.prop('disabled', false).removeClass('wup-loading');
            if (response.success) {
                $(document.body).trigger('wc_fragment_refresh');
                // Show success feedback, restore original label after 2s
                const originalLabel = $btn.data('original-label') || $btn.text();
                $btn.data('original-label', originalLabel);
                $btn.text('\u2713 ' + (wupData.added_text || 'Added!'));
                setTimeout(function() {
                    $btn.text(originalLabel);
                }, 2000);
            }
        }).fail(function() {
            $btn.prop('disabled', false).removeClass('wup-loading');
        });
    });

    // Store original button label and init total on DOM ready
    $(document).ready(function() {
        $('.wup-add-bundle').each(function() {
            $(this).data('original-label', $(this).text().trim());
        });
        wupUpdateBundleTotal();
    });

    // Re-init when WC refreshes fragments (e.g. after mini-cart update)
    $(document.body).on('wc_fragments_refreshed', function() {
        wupUpdateBundleTotal();
    });

})(jQuery);

// === WUP Popup ===
(function($) {
    'use strict';

    // Open popup after add-to-cart
    $(document.body).on('added_to_cart', function(e, fragments, cart_hash, $button) {
        const productId = $button
            ? ($button.data('product_id') || $button.closest('[data-product_id]').data('product_id'))
            : 0;
        if (!productId || !window.wupPopup) return;

        $.post(wupPopup.ajax_url, {
            action: 'wup_get_popup',
            nonce: wupPopup.nonce,
            product_id: productId
        }, function(res) {
            if (res.success) {
                $('#wup-popup-modal .wup-popup-inner').html(res.data.html);
                $('#wup-popup-modal').fadeIn(200);
                $('body').addClass('wup-popup-open');
            }
        });
    });

    // Close popup on overlay or close button click
    $(document).on('click', '#wup-popup-modal .wup-popup-close, #wup-popup-modal .wup-popup-overlay', function() {
        $('#wup-popup-modal').fadeOut(200);
        $('body').removeClass('wup-popup-open');
    });

    // Add individual item from popup to cart
    $(document).on('click', '.wup-popup-add-btn', function() {
        const $btn = $(this);
        const $item = $btn.closest('.wup-popup-item');
        const productId = $btn.data('product-id');
        const variationId = $item.data('variation-id') || 0;
        const addLabel = (window.wupPopup && wupPopup.add_label) ? wupPopup.add_label : 'Add To Cart';

        $btn.prop('disabled', true);

        $.post(wupPopup.ajax_url, {
            action: 'wup_add_bundle',
            nonce: wupPopup.nonce,
            items: JSON.stringify([{ product_id: productId, variation_id: variationId, quantity: 1 }])
        }, function(res) {
            $btn.prop('disabled', false);
            if (res.success) {
                $(document.body).trigger('wc_fragment_refresh');
                $btn.text('\u2713').css('background', '#4caf50');
                setTimeout(function() {
                    $btn.text(addLabel).css('background', '');
                }, 1500);
            }
        }).fail(function() {
            $btn.prop('disabled', false);
        });
    });

})(jQuery);
