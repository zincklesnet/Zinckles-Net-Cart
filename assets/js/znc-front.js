/**
 * ZNC Front-end JS — AJAX cart operations with live recalculation.
 *
 * Fixes from v1.6.1:
 *  • Live line-total / subtotal / grand-total recalculation after qty change
 *  • Uses single localization object (zncFront) — removed duplicate zncCart
 *  • Nonce is now passed with ajax_get_count (was missing)
 *  • Payment gateway toggle on checkout page
 *
 * @package ZincklesNetCart
 * @since   1.7.0
 */
(function ($) {
    'use strict';

    if (typeof zncFront === 'undefined') return;

    var ajaxUrl = zncFront.ajax_url;
    var nonce   = zncFront.nonce;

    /* ──────────────────────────────────────────────────────────────
     *  UTILITY
     * ──────────────────────────────────────────────────────────── */

    function formatPrice(amount, symbol) {
        symbol = symbol || '$';
        return symbol + parseFloat(amount).toFixed(2);
    }

    function updateCount(count) {
        $('.znc-cart-count').text(count);
        // Update badge visibility
        if (parseInt(count, 10) > 0) {
            $('.znc-cart-count').addClass('znc-has-items');
        } else {
            $('.znc-cart-count').removeClass('znc-has-items');
        }
    }

    /**
     * Recalculate all line totals, shop subtotals, and grand totals.
     * Called after any qty change.
     */
    function recalculateTotals() {
        var shopTotals = {};   // blog_id => { total: float, symbol: string }
        var grandTotals = {};  // currency_code => { total: float, symbol: string, label: string }

        $('.znc-cart-item').each(function () {
            var $row     = $(this);
            var price    = parseFloat($row.data('price')) || 0;
            var qty      = parseInt($row.find('.znc-qty-input').val(), 10) || 1;
            var blogId   = $row.data('blog');
            var symbol   = $row.data('symbol') || '$';
            var lineTotal = price * qty;

            // Update line total cell
            $row.find('.znc-line-total').text(formatPrice(lineTotal, symbol));

            // Accumulate shop subtotal
            if (!shopTotals[blogId]) {
                shopTotals[blogId] = { total: 0, symbol: symbol };
            }
            shopTotals[blogId].total += lineTotal;
        });

        // Update shop subtotals
        for (var blogId in shopTotals) {
            if (!shopTotals.hasOwnProperty(blogId)) continue;
            var $sub = $('.znc-shop-subtotal[data-blog="' + blogId + '"]');
            if ($sub.length) {
                $sub.find('strong').contents().first().replaceWith(
                    formatPrice(shopTotals[blogId].total, shopTotals[blogId].symbol)
                );
            }
        }

        // Update grand total
        // If single currency: just sum everything
        var currencyCount = 0;
        var totalsByCurrency = {};

        for (var bid in shopTotals) {
            if (!shopTotals.hasOwnProperty(bid)) continue;
            var sym = shopTotals[bid].symbol;
            if (!totalsByCurrency[sym]) {
                totalsByCurrency[sym] = 0;
                currencyCount++;
            }
            totalsByCurrency[sym] += shopTotals[bid].total;
        }

        var $grandTotal = $('.znc-grand-total');
        if ($grandTotal.length) {
            if (currencyCount <= 1) {
                var firstSym = Object.keys(totalsByCurrency)[0] || '$';
                $grandTotal.find('.znc-grand-total-amount').text(
                    formatPrice(totalsByCurrency[firstSym], firstSym)
                );
            } else {
                // Multiple currencies — update each span
                $grandTotal.find('.znc-currency-total').each(function () {
                    var $span = $(this);
                    var sym   = $span.data('currency');
                    // Try to match by symbol stored in data attribute
                    // For multi-currency, we update based on what we can match
                });
                // Fallback: rebuild the grand total HTML
                var parts = [];
                for (var s in totalsByCurrency) {
                    if (!totalsByCurrency.hasOwnProperty(s)) continue;
                    parts.push(formatPrice(totalsByCurrency[s], s));
                }
                $grandTotal.html(parts.join(' <span class="znc-currency-separator">+</span> '));
            }
        }
    }

    /* ──────────────────────────────────────────────────────────────
     *  REMOVE ITEM
     * ──────────────────────────────────────────────────────────── */

    $(document).on('click', '.znc-remove-item', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var key  = $btn.data('key');
        var $row = $btn.closest('.znc-cart-item, tr');

        $row.css('opacity', '0.5');

        $.post(ajaxUrl, {
            action:   'znc_remove_cart_item',
            nonce:    nonce,
            item_key: key
        }, function (res) {
            if (res.success) {
                $row.fadeOut(300, function () {
                    $row.remove();
                    recalculateTotals();

                    // If shop section is now empty, remove it
                    $('.znc-shop-section').each(function () {
                        if ($(this).find('.znc-cart-item').length === 0) {
                            $(this).fadeOut(300, function () { $(this).remove(); });
                        }
                    });

                    // If cart is completely empty
                    if ($('.znc-cart-item').length === 0) {
                        $('.znc-global-cart').html(
                            '<div class="woocommerce-info">' +
                            (zncFront.i18n_empty || 'Your global cart is empty.') +
                            '</div>'
                        );
                    }
                });
                updateCount(res.data.count);
            } else {
                $row.css('opacity', '1');
                alert(res.data && res.data.message ? res.data.message : 'Error removing item.');
            }
        }).fail(function () {
            $row.css('opacity', '1');
        });
    });

    /* ──────────────────────────────────────────────────────────────
     *  QUANTITY CONTROLS
     * ──────────────────────────────────────────────────────────── */

    function updateQty(key, newQty) {
        if (newQty < 1) newQty = 1;

        $.post(ajaxUrl, {
            action:   'znc_update_cart_qty',
            nonce:    nonce,
            item_key: key,
            quantity: newQty
        }, function (res) {
            if (res.success) {
                updateCount(res.data.count);
                recalculateTotals();
            }
        });
    }

    // Minus button
    $(document).on('click', '.znc-qty-minus', function (e) {
        e.preventDefault();
        var $input = $(this).siblings('.znc-qty-input');
        var val    = parseInt($input.val(), 10) || 1;
        var newVal = Math.max(1, val - 1);
        $input.val(newVal);
        updateQty($(this).data('key'), newVal);
    });

    // Plus button
    $(document).on('click', '.znc-qty-plus', function (e) {
        e.preventDefault();
        var $input = $(this).siblings('.znc-qty-input');
        var val    = parseInt($input.val(), 10) || 1;
        var max    = parseInt($input.attr('max'), 10) || 999;
        var newVal = Math.min(max, val + 1);
        $input.val(newVal);
        updateQty($(this).data('key'), newVal);
    });

    // Direct input change
    var qtyTimer;
    $(document).on('change input', '.znc-qty-input', function () {
        var $input = $(this);
        var key    = $input.data('key');
        var val    = parseInt($input.val(), 10) || 1;
        var max    = parseInt($input.attr('max'), 10) || 999;
        val = Math.max(1, Math.min(max, val));
        $input.val(val);

        clearTimeout(qtyTimer);
        qtyTimer = setTimeout(function () {
            updateQty(key, val);
        }, 400); // Debounce
    });

    /* ──────────────────────────────────────────────────────────────
     *  CLEAR CART
     * ──────────────────────────────────────────────────────────── */

    $(document).on('click', '.znc-clear-cart', function (e) {
        e.preventDefault();

        if (!confirm(zncFront.i18n_clear_confirm || 'Are you sure you want to clear your cart?')) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true);

        $.post(ajaxUrl, {
            action: 'znc_clear_global_cart',
            nonce:  nonce
        }, function (res) {
            if (res.success) {
                updateCount(0);
                $('.znc-global-cart').html(
                    '<div class="woocommerce-info">' +
                    (zncFront.i18n_empty || 'Your global cart is empty.') +
                    '</div>'
                );
            }
            $btn.prop('disabled', false);
        }).fail(function () {
            $btn.prop('disabled', false);
        });
    });

    /* ──────────────────────────────────────────────────────────────
     *  CHECKOUT: Payment gateway toggle
     * ──────────────────────────────────────────────────────────── */

    $(document).on('change', 'input[name="payment_method"]', function () {
        var selected = $(this).val();
        $('.payment_box').hide();
        $('.payment_method_' + selected).find('.payment_box').show();
    });

    /* ──────────────────────────────────────────────────────────────
     *  CHECKOUT: Form submission with loading state
     * ──────────────────────────────────────────────────────────── */

    $(document).on('submit', '#znc-checkout-form', function () {
        var $form = $(this);
        var $btn  = $form.find('button[name="znc_place_order"]');
        $btn.prop('disabled', true).text(zncFront.i18n_processing || 'Processing…');
    });

    /* ──────────────────────────────────────────────────────────────
     *  PERIODIC COUNT REFRESH (every 60s)
     * ──────────────────────────────────────────────────────────── */

    setInterval(function () {
        if (!zncFront.is_logged_in) return;

        $.post(ajaxUrl, {
            action: 'znc_get_cart_count',
            nonce:  nonce
        }, function (res) {
            if (res.success && typeof res.data.count !== 'undefined') {
                updateCount(res.data.count);
            }
        });
    }, 60000);

})(jQuery);
