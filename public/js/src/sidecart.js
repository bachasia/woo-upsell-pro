/* Woo Upsell Pro - Side Cart JS */
(function($) {
    'use strict';

    const cfg = window.wupSideCart || {};

    // ------------------------------------------------------------------ //
    // Panel open / close
    // ------------------------------------------------------------------ //

    function openCart() {
        $.post(cfg.ajax_url, { action: 'wup_get_side_cart', nonce: cfg.nonce }, function(res) {
            if (res.success) {
                $('#wup-side-cart .wup-sc-content').html(res.data.html).show();
                $('#wup-side-cart').addClass('wup-sc-open');
                $('body').addClass('wup-sc-body-open');
            }
        });
    }

    function closeCart() {
        $('#wup-side-cart').removeClass('wup-sc-open');
        $('body').removeClass('wup-sc-body-open');
    }

    // ------------------------------------------------------------------ //
    // Generic cart action helper — posts data, re-renders panel content
    // ------------------------------------------------------------------ //

    function cartAction(action, data, callback) {
        $.post(cfg.ajax_url, Object.assign({ action: action, nonce: cfg.nonce }, data), function(res) {
            if (res.success) {
                $('#wup-side-cart .wup-sc-content').html(res.data.html);
                $('.wup-sc-badge').text(res.data.cart_count);
                $(document.body).trigger('wc_fragment_refresh');
                if (typeof callback === 'function') callback(res.data);
            }
        });
    }

    // ------------------------------------------------------------------ //
    // Event bindings
    // ------------------------------------------------------------------ //

    // Open cart via configured selector or floating icon
    $(document).on('click', cfg.open_selector || '.wup-cart-floating-icon', openCart);

    // Close via close button or overlay
    $(document).on('click', '.wup-sc-close, .wup-sc-overlay', closeCart);

    // Qty stepper
    $(document).on('click', '.wup-qty-plus, .wup-qty-minus', function() {
        const $item = $(this).closest('[data-cart-key]');
        const key   = $item.data('cart-key');
        const $qty  = $item.find('.wup-qty');
        let qty     = parseInt($qty.text(), 10) + ($(this).hasClass('wup-qty-plus') ? 1 : -1);
        if (qty < 0) qty = 0;
        cartAction('wup_sc_update_qty', { cart_item_key: key, quantity: qty });
    });

    // Remove item
    $(document).on('click', '.wup-remove-item', function() {
        cartAction('wup_sc_remove_item', { cart_item_key: $(this).data('key') });
    });

    // Apply coupon
    $(document).on('click', '.wup-apply-coupon', function() {
        const code = $(this).siblings('.wup-coupon-code').val();
        if (!code) return;
        cartAction('wup_sc_apply_coupon', { coupon_code: code });
    });

    // Remove coupon
    $(document).on('click', '.wup-remove-coupon', function() {
        cartAction('wup_sc_remove_coupon', { coupon_code: $(this).data('coupon') });
    });

    // FBT strip — add item
    $(document).on('click', '.wup-sc-fbt-add', function() {
        cartAction('wup_sc_add_item', { product_id: $(this).data('product-id') });
    });

    // After add-to-cart: open side cart if popup is not already showing
    $(document.body).on('added_to_cart', function() {
        if ($('body').hasClass('wup-popup-open')) return;
        if (cfg.auto_open) openCart();
    });

    // Fragment refresh: re-render content if cart panel is currently open
    $(document.body).on('wc_fragments_refreshed', function() {
        if ($('#wup-side-cart').hasClass('wup-sc-open')) {
            // Content is already updated via cartAction; nothing extra needed.
        }
    });

})(jQuery);
