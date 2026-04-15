/**
 * Zinckles Net Cart — Network Admin JS v1.7.0
 */
(function($){
    'use strict';

    var $doc = $(document);

    /* ── Helpers ── */
    function showNotice(msg, type) {
        var $n = $('#znc-subsite-notice, .znc-admin-wrap .notice').first();
        if (!$n.length) {
            $n = $('<div class="notice is-dismissible"><p></p></div>').prependTo('.znc-admin-wrap');
        }
        $n.removeClass('notice-success notice-error notice-warning notice-info')
          .addClass('notice-' + (type || 'success'))
          .find('p').html(msg);
        $n.slideDown(200);
        setTimeout(function(){ $n.slideUp(300); }, 6000);
    }

    function ajaxPost(action, data, btn) {
        var $btn = $(btn);
        var origText = $btn.text();
        $btn.prop('disabled', true).append(' <span class="znc-spinner"></span>');
        data.action = action;
        data._wpnonce = typeof zncAdmin !== 'undefined' ? zncAdmin.nonce : '';
        return $.post(ajaxurl, data).always(function(){
            $btn.prop('disabled', false).find('.znc-spinner').remove();
            $btn.text(origText);
        });
    }

    /* ── Save Settings ── */
    $doc.on('submit', '#znc-settings-form', function(e){
        e.preventDefault();
        var formData = $(this).serializeArray();
        var data = {};
        $.each(formData, function(i, field){ data[field.name] = field.value; });
        ajaxPost('znc_save_settings', data, $(this).find('[type=submit]'))
            .done(function(r){
                showNotice(r.success ? 'Settings saved.' : (r.data || 'Error saving.'), r.success ? 'success' : 'error');
            });
    });

    /* ── Save Security ── */
    $doc.on('submit', '#znc-security-form', function(e){
        e.preventDefault();
        var formData = $(this).serializeArray();
        var data = {};
        $.each(formData, function(i, field){ data[field.name] = field.value; });
        ajaxPost('znc_save_security', data, $(this).find('[type=submit]'))
            .done(function(r){
                showNotice(r.success ? 'Security settings saved.' : (r.data || 'Error.'), r.success ? 'success' : 'error');
            });
    });

    /* ── Regenerate HMAC Secret ── */
    $doc.on('click', '#znc-regenerate-secret', function(){
        if (!confirm('Regenerate HMAC secret? Existing API consumers will need the new key.')) return;
        ajaxPost('znc_regenerate_secret', {}, this)
            .done(function(r){
                if (r.success && r.data && r.data.preview) {
                    $('#znc-hmac-display').text(r.data.preview + '...');
                    showNotice('HMAC secret regenerated.');
                } else {
                    showNotice('Failed to regenerate secret.', 'error');
                }
            });
    });

    /* ── Enroll / Remove Site ── */
    $doc.on('click', '.znc-enroll-site', function(){
        var bid = $(this).data('blog-id');
        ajaxPost('znc_enroll_site', { blog_id: bid }, this)
            .done(function(r){
                if (r.success) { location.reload(); }
                else { showNotice(r.data || 'Enrollment failed.', 'error'); }
            });
    });

    $doc.on('click', '.znc-remove-site', function(){
        var bid = $(this).data('blog-id');
        if (!confirm('Remove site #' + bid + ' from Net Cart?')) return;
        ajaxPost('znc_remove_site', { blog_id: bid }, this)
            .done(function(r){
                if (r.success) { location.reload(); }
                else { showNotice(r.data || 'Removal failed.', 'error'); }
            });
    });

    /* ── Test Connection ── */
    $doc.on('click', '.znc-test-site', function(){
        var bid = $(this).data('blog-id');
        var $result = $('.znc-test-result[data-bid="' + bid + '"]');
        $result.html('<span class="znc-spinner"></span>');
        ajaxPost('znc_test_connection', { blog_id: bid }, this)
            .done(function(r){
                if (r.success) {
                    $result.html('<span class="ok">✓ WooCommerce active</span>');
                } else {
                    $result.html('<span class="fail">✗ ' + (r.data || 'No WooCommerce') + '</span>');
                }
            });
    });

    /* ── Detect Points (Bridge) ── */
    $doc.on('click', '#znc-detect-points', function(){
        ajaxPost('znc_detect_points', {}, this)
            .done(function(r){
                if (r.success && r.data) {
                    var html = '';
                    if (r.data.mycred && r.data.mycred.length) {
                        html += '<h4>MyCred Point Types</h4><ul class="znc-point-type-list">';
                        $.each(r.data.mycred, function(i, pt){
                            html += '<li><span>' + pt.label + '</span><code>' + pt.key + '</code></li>';
                        });
                        html += '</ul>';
                    }
                    if (r.data.gamipress && r.data.gamipress.length) {
                        html += '<h4>GamiPress Point Types</h4><ul class="znc-point-type-list">';
                        $.each(r.data.gamipress, function(i, pt){
                            html += '<li><span>' + pt.label + '</span><code>' + pt.slug + '</code></li>';
                        });
                        html += '</ul>';
                    }
                    if (!html) html = '<p>No point types detected.</p>';
                    $('#znc-detected-points').html(html);
                    showNotice('Point types detected.');
                } else {
                    showNotice('Detection failed.', 'error');
                }
            });
    });

    /* ── Transfer Points (Bridge) ── */
    $doc.on('submit', '#znc-transfer-form', function(e){
        e.preventDefault();
        var data = {
            user_id:    $(this).find('[name=user_id]').val(),
            gami_type:  $(this).find('[name=gami_type]').val(),
            mycred_type:$(this).find('[name=mycred_type]').val(),
            amount:     $(this).find('[name=amount]').val()
        };
        if (!data.user_id || !data.amount) {
            showNotice('Please fill in User ID and Amount.', 'warning');
            return;
        }
        ajaxPost('znc_transfer_points', data, $(this).find('[type=submit]'))
            .done(function(r){
                showNotice(r.success ? 'Points transferred successfully.' : (r.data || 'Transfer failed.'), r.success ? 'success' : 'error');
            });
    });

})(jQuery);
