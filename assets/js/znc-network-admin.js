/**
 * Zinckles Net Cart — Network Admin JS v1.3.1
 *
 * FIXED: Enrollment AJAX sends correct parameters and updates UI on success.
 */
(function($) {
    'use strict';

    /* ── Enrollment Toggle ──────────────────────────────────────── */
    $(document).on('click', '.znc-enroll-btn', function(e) {
        e.preventDefault();
        var $btn    = $(this);
        var blogId  = $btn.data('blog-id');
        var action  = $btn.data('action'); // 'enroll' or 'remove'

        if (!blogId || !action) {
            alert('Missing parameters.');
            return;
        }

        $btn.prop('disabled', true).text(action === 'enroll' ? 'Enrolling...' : 'Removing...');

        $.ajax({
            url:  zncAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action:            'znc_toggle_enrollment',
                nonce:             zncAdmin.nonce,
                blog_id:           blogId,
                enrollment_action: action
            },
            success: function(response) {
                if (response.success) {
                    var isEnrolled = response.data.is_enrolled;
                    var $row    = $('#znc-site-row-' + blogId);
                    var $status = $('#znc-status-' + blogId);

                    // Update button
                    $btn.data('action', isEnrolled ? 'remove' : 'enroll');
                    $btn.text(isEnrolled ? 'Remove' : 'Enroll');
                    $btn.removeClass('button-primary button-secondary');
                    $btn.addClass(isEnrolled ? 'button-secondary' : 'button-primary');

                    // Update status badge
                    if (isEnrolled) {
                        $status.html('<span class="znc-badge znc-badge-success">Enrolled</span>');
                    } else {
                        $status.html('<span class="znc-badge znc-badge-neutral">Not Enrolled</span>');
                    }

                    // Flash row
                    $row.css('background-color', isEnrolled ? '#d4edda' : '#fff3cd');
                    setTimeout(function() {
                        $row.css('background-color', '');
                    }, 1500);
                } else {
                    alert('Error: ' + (response.data.message || 'Unknown error'));
                }
                $btn.prop('disabled', false);
            },
            error: function(xhr, status, error) {
                alert('AJAX error: ' + error);
                $btn.prop('disabled', false).text(action === 'enroll' ? 'Enroll' : 'Remove');
            }
        });
    });

    /* ── Test Connection ──────────────────────────────────────── */
    $(document).on('click', '.znc-test-btn', function(e) {
        e.preventDefault();
        var $btn   = $(this);
        var blogId = $btn.data('blog-id');

        $btn.prop('disabled', true).text('Testing...');

        $.ajax({
            url:  zncAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action:  'znc_test_connection',
                nonce:   zncAdmin.nonce,
                blog_id: blogId
            },
            success: function(response) {
                if (response.success) {
                    var d = response.data;
                    var msg = d.blogname + ': ' + (d.has_wc ? '✅ WooCommerce active' : '❌ WooCommerce not found');
                    alert(msg);
                } else {
                    alert('Error: ' + (response.data.message || 'Unknown'));
                }
                $btn.prop('disabled', false).text('Test');
            },
            error: function() {
                alert('Connection test failed.');
                $btn.prop('disabled', false).text('Test');
            }
        });
    });

    /* ── Save Network Settings ────────────────────────────────── */
    $('#znc-network-settings-form').on('submit', function(e) {
        e.preventDefault();
        var $form   = $(this);
        var $status = $('#znc-settings-status');
        var $btn    = $('#znc-save-settings');

        $btn.prop('disabled', true);
        $status.text('Saving...');

        $.ajax({
            url:  zncAdmin.ajaxUrl,
            type: 'POST',
            data: $form.serialize() + '&action=znc_save_network_settings&nonce=' + zncAdmin.nonce,
            success: function(response) {
                if (response.success) {
                    $status.text('✅ ' + response.data.message).css('color', '#00a32a');
                } else {
                    $status.text('❌ ' + (response.data.message || 'Error')).css('color', '#d63638');
                }
                $btn.prop('disabled', false);
                setTimeout(function() { $status.text(''); }, 4000);
            },
            error: function() {
                $status.text('❌ Connection error').css('color', '#d63638');
                $btn.prop('disabled', false);
            }
        });
    });

    /* ── Save Security Settings ───────────────────────────────── */
    $('#znc-security-form').on('submit', function(e) {
        e.preventDefault();
        var $form   = $(this);
        var $status = $('#znc-security-status');
        var $btn    = $('#znc-save-security');

        $btn.prop('disabled', true);
        $status.text('Saving...');

        $.ajax({
            url:  zncAdmin.ajaxUrl,
            type: 'POST',
            data: $form.serialize() + '&action=znc_save_security&nonce=' + zncAdmin.nonce,
            success: function(response) {
                if (response.success) {
                    $status.text('✅ ' + response.data.message).css('color', '#00a32a');
                } else {
                    $status.text('❌ ' + (response.data.message || 'Error')).css('color', '#d63638');
                }
                $btn.prop('disabled', false);
                setTimeout(function() { $status.text(''); }, 4000);
            },
            error: function() {
                $status.text('❌ Connection error').css('color', '#d63638');
                $btn.prop('disabled', false);
            }
        });
    });

    /* ── Regenerate Secret ────────────────────────────────────── */
    $('#znc-regenerate-secret').on('click', function(e) {
        e.preventDefault();
        if (!confirm('Are you sure? This will invalidate all existing signed requests.')) return;

        var $btn    = $(this);
        var $status = $('#znc-secret-status');

        $btn.prop('disabled', true);
        $status.text('Regenerating...');

        $.ajax({
            url:  zncAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'znc_regenerate_secret',
                nonce:  zncAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.text('✅ ' + response.data.message).css('color', '#00a32a');
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    $status.text('❌ ' + (response.data.message || 'Error')).css('color', '#d63638');
                }
                $btn.prop('disabled', false);
            },
            error: function() {
                $status.text('❌ Connection error').css('color', '#d63638');
                $btn.prop('disabled', false);
            }
        });
    });

})(jQuery);
