/**
 * Zinckles Net Cart — Network Admin JS v1.3.2
 * Handles AJAX enrollment toggle for subsites.
 */
(function($){
    'use strict';

    $(document).on('click', '.znc-enroll-btn', function(e){
        e.preventDefault();

        var $btn    = $(this),
            blogId  = $btn.data('blog-id'),
            action  = $btn.data('action'); // 'enroll' or 'remove'

        if ( ! blogId || $btn.prop('disabled') ) return;

        $btn.prop('disabled', true).text( action === 'enroll' ? 'Enrolling…' : 'Removing…' );

        $.ajax({
            url:  ajaxurl,
            type: 'POST',
            data: {
                action:            'znc_toggle_enrollment',
                blog_id:           blogId,
                enrollment_action: action,
                _wpnonce:          $('#znc_enrollment_nonce').val()
            },
            success: function(res){
                if ( res.success ) {
                    var $row = $btn.closest('tr');

                    if ( action === 'enroll' ) {
                        $btn.data('action', 'remove')
                            .text('Remove')
                            .removeClass('button-primary')
                            .addClass('button-link-delete');
                        $row.find('.znc-status-cell')
                            .html('<span class="znc-badge znc-badge-green">Enrolled</span>');
                    } else {
                        $btn.data('action', 'enroll')
                            .text('Enroll')
                            .addClass('button-primary')
                            .removeClass('button-link-delete');
                        $row.find('.znc-status-cell')
                            .html('<span class="znc-badge znc-badge-gray">Not Enrolled</span>');
                    }

                    $btn.prop('disabled', false);
                } else {
                    alert( res.data || 'Enrollment action failed.' );
                    $btn.prop('disabled', false).text( action === 'enroll' ? 'Enroll' : 'Remove' );
                }
            },
            error: function(){
                alert('Network error. Please try again.');
                $btn.prop('disabled', false).text( action === 'enroll' ? 'Enroll' : 'Remove' );
            }
        });
    });

})(jQuery);
