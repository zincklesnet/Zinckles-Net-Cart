/**
 * Zinckles Net Cart — Front-end JS v1.3.2
 * Handles global cart AJAX: remove item, update quantity, refresh totals.
 */
(function($){
    'use strict';

    var ZNCFront = {
        init: function(){
            $(document).on('click', '.znc-remove-item', this.removeItem);
            $(document).on('change', '.znc-qty', this.updateQty);
            $(document).on('click', '.znc-refresh-cart', this.refreshCart);
        },

        removeItem: function(e){
            e.preventDefault();
            var $btn  = $(this),
                $row  = $btn.closest('tr'),
                rowId = $btn.data('row-id');

            if ( ! rowId ) return;

            $btn.prop('disabled', true).text('…');

            $.ajax({
                url:  znc_front.ajax_url,
                type: 'POST',
                data: {
                    action:   'znc_remove_cart_item',
                    row_id:   rowId,
                    _wpnonce: znc_front.nonce
                },
                success: function(res){
                    if ( res.success ) {
                        $row.fadeOut(300, function(){
                            $(this).remove();
                            ZNCFront.updateSummary(res.data);
                        });
                    } else {
                        alert( res.data || 'Could not remove item.' );
                        $btn.prop('disabled', false).text('✕');
                    }
                },
                error: function(){
                    alert('Network error. Please try again.');
                    $btn.prop('disabled', false).text('✕');
                }
            });
        },

        updateQty: function(){
            var $input = $(this),
                rowId  = $input.data('row-id'),
                qty    = parseInt( $input.val(), 10 );

            if ( ! rowId || qty < 1 ) return;

            $input.prop('disabled', true);

            $.ajax({
                url:  znc_front.ajax_url,
                type: 'POST',
                data: {
                    action:   'znc_update_cart_qty',
                    row_id:   rowId,
                    quantity: qty,
                    _wpnonce: znc_front.nonce
                },
                success: function(res){
                    $input.prop('disabled', false);
                    if ( res.success ) {
                        ZNCFront.updateSummary(res.data);
                        // Update line total in same row
                        var $row = $input.closest('tr');
                        if ( res.data.line_total ) {
                            $row.find('.znc-line-total').text( res.data.line_total );
                        }
                    } else {
                        alert( res.data || 'Could not update quantity.' );
                    }
                },
                error: function(){
                    $input.prop('disabled', false);
                    alert('Network error. Please try again.');
                }
            });
        },

        refreshCart: function(e){
            e.preventDefault();
            var $btn = $(this);
            $btn.prop('disabled', true).text('Refreshing…');

            $.ajax({
                url:  znc_front.ajax_url,
                type: 'POST',
                data: {
                    action:   'znc_refresh_cart',
                    _wpnonce: znc_front.nonce
                },
                success: function(res){
                    if ( res.success ) {
                        location.reload();
                    } else {
                        alert( res.data || 'Could not refresh cart.' );
                        $btn.prop('disabled', false).text('↻ Refresh');
                    }
                },
                error: function(){
                    alert('Network error.');
                    $btn.prop('disabled', false).text('↻ Refresh');
                }
            });
        },

        updateSummary: function(data){
            if ( ! data ) return;
            if ( data.item_count !== undefined ) {
                $('.znc-item-count').text( data.item_count + ' item' + (data.item_count !== 1 ? 's' : '') );
            }
            if ( data.currency_totals ) {
                $.each( data.currency_totals, function(cur, total){
                    $('.znc-cur-total-' + cur.toLowerCase()).text( total );
                });
            }
            if ( data.item_count === 0 ) {
                location.reload();
            }
        }
    };

    $(document).ready(function(){ ZNCFront.init(); });

})(jQuery);
