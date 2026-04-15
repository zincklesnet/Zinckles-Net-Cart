/**
 * Zinckles Net Cart — Front-End JS — v1.4.1
 * Handles cart interactions: remove item, quantity update, AJAX refresh.
 */
(function($) {
    'use strict';

    if (typeof zncFront === 'undefined') return;

    var ajaxUrl = zncFront.ajaxUrl;
    var nonce   = zncFront.nonce;

    /* ── Remove Item ─────────────────────────────── */
    $(document).on('click', '.znc-remove-item', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $row = $btn.closest('.znc-cart-item, tr');

        $btn.prop('disabled', true).text('…');

        $.post(ajaxUrl, {
            action: 'znc_remove_cart_item',
            blog_id: $btn.data('blog-id'),
            product_id: $btn.data('product-id'),
            variation_id: $btn.data('variation-id') || 0,
            nonce: nonce
        })
        .done(function(response) {
            if (response.success) {
                $row.fadeOut(300, function() {
                    $(this).remove();
                    updateCartCount(response.data.count);
                    updateCartTotal(response.data.total);
                    if ($('.znc-cart-item, .znc-cart-table tbody tr').length === 0) {
                        showEmptyCart();
                    }
                });
            } else {
                alert(response.data || 'Error removing item.');
                $btn.prop('disabled', false).text('×');
            }
        })
        .fail(function() {
            alert('Network error. Please try again.');
            $btn.prop('disabled', false).text('×');
        });
    });

    /* ── Quantity Update ─────────────────────────── */
    var qtyTimer = null;
    $(document).on('change', '.znc-qty-input', function() {
        var $input = $(this);
        var itemId = $input.data('item-id');
        var qty    = parseInt($input.val(), 10);

        if (qty < 1) { $input.val(1); qty = 1; }

        clearTimeout(qtyTimer);
        qtyTimer = setTimeout(function() {
            $input.prop('disabled', true);
            $.post(ajaxUrl, {
                action: 'znc_update_cart_quantity',
                item_id: itemId,
                quantity: qty,
                nonce: nonce
            })
            .done(function(response) {
                if (response.success) {
                    updateCartCount(response.data.count);
                    updateCartTotal(response.data.total);
                    if (response.data.line_total) {
                        $input.closest('.znc-cart-item, tr').find('.znc-line-total').text(response.data.line_total);
                    }
                }
            })
            .always(function() {
                $input.prop('disabled', false);
            });
        }, 500);
    });

    /* ── Cart Badge Sync ─────────────────────────── */
    function updateCartCount(count) {
        count = parseInt(count, 10) || 0;
        // Update all known cart count elements
        $('.znc-cart-count, .znc-cart-badge-count').text(count);
        // WC fragments
        $('.cart-contents .count, .cart-count, .wc-cart-count').text(count);
        // REIGN theme specific
        $('.wb-cart-count, .cart-items-count, .header-cart-count').text(count);
        // Trigger custom event
        $(document.body).trigger('znc_cart_count_updated', [count]);
    }

    function updateCartTotal(total) {
        if (total !== undefined) {
            $('.znc-cart-total-amount').text(total);
        }
    }

    function showEmptyCart() {
        var $container = $('.znc-global-cart');
        if ($container.length) {
            $container.html(
                '<div class="znc-empty-cart">' +
                '<span class="znc-empty-icon">🛒</span>' +
                '<p>Your global cart is empty.</p>' +
                '<a href="/" class="button">Continue Shopping</a>' +
                '</div>'
            );
        }
    }

    /* ── Initial Cart Count Load ─────────────────── */
    function loadGlobalCartCount() {
        if (!zncFront.loadCount) return;
        $.get(ajaxUrl, {
            action: 'znc_get_cart_count',
            nonce: nonce
        })
        .done(function(response) {
            if (response.success) {
                updateCartCount(response.data.count);
            }
        });
    }

    $(document).ready(function() {
        loadGlobalCartCount();
    });

})(jQuery);
