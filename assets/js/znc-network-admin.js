/**
 * Zinckles Net Cart — Network Admin JS — v1.4.1
 * Handles all AJAX operations for the network admin pages.
 */
(function($) {
    'use strict';

    if (typeof zncAdmin === 'undefined') return;

    var ajaxUrl = zncAdmin.ajaxUrl;
    var nonce   = zncAdmin.nonce;
    var strings = zncAdmin.strings || {};

    /* ── Enrollment Toggle ───────────────────────── */
    $(document).on('click', '.znc-enrollment-btn', function(e) {
        e.preventDefault();
        var $btn    = $(this);
        var blogId  = $btn.data('blog-id');
        var action  = $btn.data('action'); // 'enroll' or 'remove'
        var $status = $('#znc-status-' + blogId);
        var $actionStatus = $('#znc-action-' + blogId);

        $btn.prop('disabled', true);
        $actionStatus.text(strings.enrolling || 'Processing…').css('color', '#666');

        $.post(ajaxUrl, {
            action: 'znc_toggle_enrollment',
            blog_id: blogId,
            enrollment_action: action,
            nonce: nonce
        })
        .done(function(response) {
            if (response.success) {
                if (action === 'enroll') {
                    $status.html('<span class="znc-badge znc-badge-success">Enrolled</span>');
                    $btn.text('Remove').data('action', 'remove')
                        .removeClass('button-primary znc-btn-enroll')
                        .addClass('znc-btn-remove');
                    $btn.closest('tr').addClass('znc-enrolled');
                } else {
                    $status.html('<span class="znc-badge znc-badge-muted">Not Enrolled</span>');
                    $btn.text('Enroll').data('action', 'enroll')
                        .removeClass('znc-btn-remove')
                        .addClass('button-primary znc-btn-enroll');
                    $btn.closest('tr').removeClass('znc-enrolled');
                }
                $actionStatus.text('✓').css('color', '#46b450');
            } else {
                $actionStatus.text(response.data || strings.error || 'Error').css('color', '#dc3232');
            }
        })
        .fail(function(xhr) {
            $actionStatus.text(strings.error || 'AJAX Error: ' + xhr.status).css('color', '#dc3232');
        })
        .always(function() {
            $btn.prop('disabled', false);
            setTimeout(function() { $actionStatus.text(''); }, 4000);
        });
    });

    /* ── Save Network Settings ───────────────────── */
    $(document).on('submit', '#znc-network-settings-form', function(e) {
        e.preventDefault();
        var $form   = $(this);
        var $btn    = $('#znc-save-settings');
        var $status = $('#znc-save-status');

        $btn.prop('disabled', true);
        $status.text(strings.saving || 'Saving…').css('color', '#666');

        var formData = $form.serialize();
        formData += '&action=znc_save_network_settings';

        $.post(ajaxUrl, formData)
        .done(function(response) {
            if (response.success) {
                $status.text(strings.saved || '✓ Saved!').css('color', '#46b450');
            } else {
                $status.text(response.data || strings.error || 'Error saving.').css('color', '#dc3232');
            }
        })
        .fail(function(xhr) {
            $status.text(strings.error || 'AJAX Error: ' + xhr.status).css('color', '#dc3232');
        })
        .always(function() {
            $btn.prop('disabled', false);
            setTimeout(function() { $status.text(''); }, 5000);
        });
    });

    /* ── Save Security Settings ──────────────────── */
    $(document).on('submit', '#znc-security-form', function(e) {
        e.preventDefault();
        var $form   = $(this);
        var $btn    = $('#znc-save-security');
        var $status = $('#znc-security-save-status');

        $btn.prop('disabled', true);
        $status.text(strings.saving || 'Saving…').css('color', '#666');

        $.post(ajaxUrl, {
            action: 'znc_save_security',
            clock_skew: $('#clock_skew').val(),
            rate_limit: $('#rate_limit').val(),
            ip_whitelist: $('#ip_whitelist').val(),
            nonce: nonce
        })
        .done(function(response) {
            if (response.success) {
                $status.text(strings.saved || '✓ Saved!').css('color', '#46b450');
            } else {
                $status.text(response.data || strings.error || 'Error saving.').css('color', '#dc3232');
            }
        })
        .fail(function(xhr) {
            $status.text(strings.error || 'AJAX Error: ' + xhr.status).css('color', '#dc3232');
        })
        .always(function() {
            $btn.prop('disabled', false);
            setTimeout(function() { $status.text(''); }, 5000);
        });
    });

    /* ── Regenerate HMAC Secret ──────────────────── */
    $(document).on('click', '#znc-regenerate-secret', function(e) {
        e.preventDefault();
        if (!confirm(strings.confirm_regen || 'Regenerate HMAC secret? All subsites will need to re-authenticate.')) return;

        var $btn    = $(this);
        var $status = $('#znc-regen-status');

        $btn.prop('disabled', true);
        $status.text(strings.regenerating || 'Regenerating…').css('color', '#666');

        $.post(ajaxUrl, {
            action: 'znc_regenerate_secret',
            nonce: nonce
        })
        .done(function(response) {
            if (response.success) {
                var preview = response.data.secret_preview || '••••••••••••';
                $('#znc-hmac-secret').text(preview);
                $status.text('✓ Regenerated!').css('color', '#46b450');
            } else {
                $status.text(response.data || strings.error || 'Error').css('color', '#dc3232');
            }
        })
        .fail(function(xhr) {
            $status.text(strings.error || 'AJAX Error: ' + xhr.status).css('color', '#dc3232');
        })
        .always(function() {
            $btn.prop('disabled', false);
            setTimeout(function() { $status.text(''); }, 5000);
        });
    });

    /* ── Test Connection ─────────────────────────── */
    $(document).on('click', '.znc-test-btn', function(e) {
        e.preventDefault();
        var $btn    = $(this);
        var blogId  = $btn.data('blog-id');
        var $status = $('#znc-action-' + blogId);

        $btn.prop('disabled', true);
        $status.text(strings.testing || 'Testing…').css('color', '#666');

        $.post(ajaxUrl, {
            action: 'znc_test_connection',
            blog_id: blogId,
            nonce: nonce
        })
        .done(function(response) {
            if (response.success) {
                $status.text('✓ Connected').css('color', '#46b450');
            } else {
                $status.text('✗ ' + (response.data || 'Failed')).css('color', '#dc3232');
            }
        })
        .fail(function(xhr) {
            $status.text('✗ Error ' + xhr.status).css('color', '#dc3232');
        })
        .always(function() {
            $btn.prop('disabled', false);
            setTimeout(function() { $status.text(''); }, 6000);
        });
    });

    /* ── Auto-Detect Point Types ─────────────────── */
    $(document).on('click', '#znc-detect-point-types', function(e) {
        e.preventDefault();
        var $btn    = $(this);
        var $status = $('#znc-detect-status');

        $btn.prop('disabled', true);
        $status.text(strings.detecting || 'Detecting…').css('color', '#666');

        $.post(ajaxUrl, {
            action: 'znc_detect_point_types',
            nonce: nonce
        })
        .done(function(response) {
            if (response.success) {
                var data = response.data;
                var mycredCount = data.mycred_types ? Object.keys(data.mycred_types).length : 0;
                var gamiCount   = data.gamipress_types ? Object.keys(data.gamipress_types).length : 0;

                // Rebuild MyCred table
                var $mycredBody = $('#znc-mycred-types-table tbody');
                $mycredBody.empty();
                if (mycredCount > 0) {
                    $.each(data.mycred_types, function(slug, cfg) {
                        var label = cfg.label || slug;
                        var rate  = cfg.exchange_rate || 1;
                        $mycredBody.append(
                            '<tr>' +
                            '<td><code>' + escHtml(slug) + '</code></td>' +
                            '<td><input type="text" name="mycred_types[' + escHtml(slug) + '][label]" value="' + escAttr(label) + '" class="regular-text" /></td>' +
                            '<td><input type="number" step="0.0001" name="mycred_types[' + escHtml(slug) + '][exchange_rate]" value="' + escAttr(rate) + '" class="small-text" /></td>' +
                            '<td><input type="checkbox" name="mycred_types[' + escHtml(slug) + '][enabled]" value="1" checked /></td>' +
                            '</tr>'
                        );
                    });
                } else {
                    $mycredBody.append('<tr><td colspan="4">No MyCred point types detected.</td></tr>');
                }

                // Rebuild GamiPress table
                var $gamiBody = $('#znc-gamipress-types-table tbody');
                $gamiBody.empty();
                if (gamiCount > 0) {
                    $.each(data.gamipress_types, function(slug, cfg) {
                        var label  = cfg.label || slug;
                        var rate   = cfg.exchange_rate || 1;
                        var blogId = cfg.blog_id || '';
                        $gamiBody.append(
                            '<tr>' +
                            '<td><code>' + escHtml(slug) + '</code></td>' +
                            '<td><input type="text" name="gamipress_types[' + escHtml(slug) + '][label]" value="' + escAttr(label) + '" class="regular-text" /></td>' +
                            '<td><input type="number" step="0.0001" name="gamipress_types[' + escHtml(slug) + '][exchange_rate]" value="' + escAttr(rate) + '" class="small-text" /></td>' +
                            '<td>' + escHtml(blogId) + '</td>' +
                            '<td><input type="checkbox" name="gamipress_types[' + escHtml(slug) + '][enabled]" value="1" checked /></td>' +
                            '</tr>'
                        );
                    });
                } else {
                    $gamiBody.append('<tr><td colspan="5">No GamiPress point types detected.</td></tr>');
                }

                $status.text('✓ Found ' + mycredCount + ' MyCred + ' + gamiCount + ' GamiPress types').css('color', '#46b450');
            } else {
                $status.text(response.data || strings.error || 'Detection failed.').css('color', '#dc3232');
            }
        })
        .fail(function(xhr) {
            $status.text(strings.error || 'AJAX Error: ' + xhr.status).css('color', '#dc3232');
        })
        .always(function() {
            $btn.prop('disabled', false);
            setTimeout(function() { $status.text(''); }, 8000);
        });
    });

    /* ── Helpers ──────────────────────────────────── */
    function escHtml(str) {
        return $('<span>').text(str).html();
    }
    function escAttr(str) {
        return $('<span>').text(str).html().replace(/"/g, '&quot;');
    }

})(jQuery);
