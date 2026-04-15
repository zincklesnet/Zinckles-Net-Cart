(function($){
    var nonce = (typeof zncAdmin !== 'undefined') ? zncAdmin.nonce : '';
    var url = (typeof zncAdmin !== 'undefined') ? zncAdmin.ajaxurl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');

    /* ── Save indicator ─────────────────────────────────── */
    function showNotice(msg, type) {
        var $n = $('#znc-save-notice');
        if (!$n.length) {
            $n = $('<div id="znc-save-notice" style="display:none;" class="notice is-dismissible"><p></p></div>');
            $('.znc-admin-wrap h1').after($n);
        }
        $n.removeClass('notice-success notice-error notice-warning')
          .addClass(type === 'error' ? 'notice-error' : 'notice-success')
          .find('p').html(msg);
        $n.slideDown(200);
        setTimeout(function(){ $n.slideUp(300); }, 5000);
    }

    /* ── Save Settings ──────────────────────────────────── */
    $(document).on('submit', '#znc-settings-form', function(e){
        e.preventDefault();
        var form = $(this), btn = form.find('#znc-save-settings');
        btn.prop('disabled', true).text('Saving...');
        var data = form.serialize();
        data += '&action=znc_save_settings&nonce=' + nonce;
        $.post(url, data, function(r){
            if (r.success) {
                showNotice('✅ ' + (r.data.message || 'Settings saved successfully!'), 'success');
                $('#znc-save-status').html('<span style="color:#46b450;font-weight:600;">✓ Saved</span>').show();
                setTimeout(function(){ $('#znc-save-status').fadeOut(); }, 4000);
            } else {
                showNotice('❌ Error: ' + (r.data || 'Unknown error'), 'error');
            }
            btn.prop('disabled', false).text('Save Network Settings');
        }).fail(function(x){
            showNotice('❌ AJAX Error: ' + x.status + ' ' + x.statusText, 'error');
            btn.prop('disabled', false).text('Save Network Settings');
        });
    });

    /* ── Enroll Site ────────────────────────────────────── */
    $(document).on('click', '.znc-enroll-btn', function(){
        var btn = $(this), bid = btn.data('blog-id');
        btn.prop('disabled', true).text('Enrolling...');
        $.post(url, {action:'znc_enroll_site', blog_id:bid, nonce:nonce}, function(r){
            if (r.success) {
                btn.closest('tr').find('.znc-status-badge')
                   .text('Enrolled').removeClass('znc-not-enrolled').addClass('znc-enrolled');
                btn.parent().html(
                    '<button class="button znc-remove-btn" data-blog-id="'+bid+'">Remove</button> ' +
                    '<button class="button znc-test-btn" data-blog-id="'+bid+'">Test</button>'
                );
                showNotice('✅ Site enrolled successfully!', 'success');
            } else {
                showNotice('❌ Enrollment failed: ' + (r.data || 'Unknown'), 'error');
                btn.prop('disabled', false).text('Enroll');
            }
        }).fail(function(x){
            showNotice('❌ AJAX Error: ' + x.status + ' — ' + x.statusText, 'error');
            btn.prop('disabled', false).text('Enroll');
        });
    });

    /* ── Remove Site ────────────────────────────────────── */
    $(document).on('click', '.znc-remove-btn', function(){
        var btn = $(this), bid = btn.data('blog-id');
        if (!confirm('Remove this site from Net Cart?')) return;
        btn.prop('disabled', true).text('Removing...');
        $.post(url, {action:'znc_remove_site', blog_id:bid, nonce:nonce}, function(r){
            if (r.success) {
                btn.closest('tr').find('.znc-status-badge')
                   .text('Not Enrolled').removeClass('znc-enrolled').addClass('znc-not-enrolled');
                btn.parent().html('<button class="button button-primary znc-enroll-btn" data-blog-id="'+bid+'">Enroll</button>');
                showNotice('✅ Site removed from Net Cart.', 'success');
            }
        }).fail(function(x){
            showNotice('❌ AJAX Error: ' + x.status, 'error');
            btn.prop('disabled', false).text('Remove');
        });
    });

    /* ── Test Connection ────────────────────────────────── */
    $(document).on('click', '.znc-test-btn', function(){
        var btn = $(this), bid = btn.data('blog-id');
        btn.prop('disabled', true).text('Testing...');
        $.post(url, {action:'znc_test_connection', blog_id:bid, nonce:nonce}, function(r){
            if (r.success) {
                var d = r.data;
                var msg = '✅ Connection OK!\n\n';
                msg += 'Site: ' + d.name + '\n';
                msg += 'WooCommerce: ' + (d.has_wc ? 'Yes ✓' : 'No ✗') + '\n';
                msg += 'Tutor LMS: ' + (d.has_tutor ? 'Yes ✓' : 'No ✗') + '\n';
                msg += 'MyCred: ' + (d.has_mycred ? 'Yes ✓' : 'No ✗') + '\n';
                msg += 'GamiPress: ' + (d.has_gamipress ? 'Yes ✓' : 'No ✗');
                alert(msg);
            } else {
                alert('Test failed: ' + (r.data || 'Unknown'));
            }
            btn.prop('disabled', false).text('Test');
        }).fail(function(x){
            alert('AJAX Error: ' + x.status);
            btn.prop('disabled', false).text('Test');
        });
    });

    /* ── Save Security ──────────────────────────────────── */
    $(document).on('submit', '#znc-security-form', function(e){
        e.preventDefault();
        var form = $(this), btn = form.find('[type=submit]');
        btn.prop('disabled', true).text('Saving...');
        var data = form.serialize();
        data += '&action=znc_save_security&nonce=' + nonce;
        $.post(url, data, function(r){
            if (r.success) {
                showNotice('✅ ' + (r.data.message || 'Security settings saved!'), 'success');
            } else {
                showNotice('❌ Error: ' + (r.data || 'Unknown'), 'error');
            }
            btn.prop('disabled', false).text('Save Security Settings');
        }).fail(function(x){
            showNotice('❌ AJAX Error: ' + x.status + ' ' + x.statusText, 'error');
            btn.prop('disabled', false).text('Save Security Settings');
        });
    });

    /* ── Regenerate Secret ──────────────────────────────── */
    $(document).on('click', '#znc-regenerate-secret', function(){
        if (!confirm('Regenerate HMAC secret? All existing tokens will be invalidated.')) return;
        var btn = $(this);
        btn.prop('disabled', true).text('Generating...');
        $.post(url, {action:'znc_regenerate_secret', nonce:nonce}, function(r){
            if (r.success) {
                $('#znc-hmac-display').text(r.data.secret.substring(0,24) + '...');
                showNotice('✅ ' + (r.data.message || 'HMAC secret regenerated!') + ' Generated at: ' + r.data.generated_at, 'success');
            } else {
                showNotice('❌ Error: ' + (r.data || 'Unknown'), 'error');
            }
            btn.prop('disabled', false).text('Regenerate Secret');
        }).fail(function(x){
            showNotice('❌ AJAX Error: ' + x.status, 'error');
            btn.prop('disabled', false).text('Regenerate Secret');
        });
    });

    /* ── Detect Point Types — handles STRUCTURED config ── */
    $(document).on('click', '#znc-detect-points', function(){
        var btn = $(this);
        btn.prop('disabled', true).text('Scanning all sites...');
        $('#znc-detect-status').html('<span style="color:#b45309;">Scanning...</span>');

        $.post(url, {action:'znc_detect_point_types', nonce:nonce}, function(r){
            if (r.success) {
                var mc = r.data.mycred || {},
                    gp = r.data.gamipress || {},
                    tu = r.data.tutor || {};

                var mcCount = Object.keys(mc).length,
                    gpCount = Object.keys(gp).length,
                    tuCount = Object.keys(tu).length;

                // Status notice
                var html = '<div class="notice notice-success" style="margin:10px 0;padding:12px">';
                html += '<strong>✅ Detection Complete!</strong><br>';
                html += 'MyCred: ' + mcCount + ' type(s) found<br>';
                html += 'GamiPress: ' + gpCount + ' type(s) found<br>';
                html += 'Tutor LMS: ' + tuCount + ' site(s) found';
                html += '</div>';
                $('#znc-detected-points').html(html);
                $('#znc-detect-status').html('<span style="color:#46b450;font-weight:600;">✓ Done</span>');
                setTimeout(function(){ $('#znc-detect-status').fadeOut(300, function(){ $(this).show().empty(); }); }, 4000);

                // ── Rebuild MyCred table ──
                var $mcBody = $('#znc-mycred-types-table tbody');
                $mcBody.empty();
                if (mcCount > 0) {
                    var baseCurrency = $('select[name="base_currency"]').val() || 'USD';
                    for (var slug in mc) {
                        var cfg = mc[slug];
                        var row = '<tr>';
                        row += '<td><code>' + slug + '</code><input type="hidden" name="mycred_types[' + slug + '][slug]" value="' + slug + '"></td>';
                        row += '<td><input type="text" name="mycred_types[' + slug + '][label]" value="' + (cfg.label || slug) + '" class="regular-text"></td>';
                        row += '<td><input type="number" step="0.0001" min="0" name="mycred_types[' + slug + '][exchange_rate]" value="' + (cfg.exchange_rate || 1) + '" class="small-text"> <span class="description">1 point = X ' + baseCurrency + '</span></td>';
                        row += '<td><input type="number" step="1" min="0" max="100" name="mycred_types[' + slug + '][max_percent]" value="' + (cfg.max_percent || 100) + '" class="small-text">%</td>';
                        row += '<td><input type="checkbox" name="mycred_types[' + slug + '][enabled]" value="1"' + (cfg.enabled ? ' checked' : '') + '></td>';
                        row += '</tr>';
                        $mcBody.append(row);
                    }
                } else {
                    $mcBody.append('<tr class="znc-no-types"><td colspan="5">No MyCred point types found on enrolled sites.</td></tr>');
                }

                // ── Rebuild GamiPress table ──
                var $gpBody = $('#znc-gamipress-types-table tbody');
                $gpBody.empty();
                if (gpCount > 0) {
                    for (var slug in gp) {
                        var cfg = gp[slug];
                        var row = '<tr>';
                        row += '<td><code>' + slug + '</code><input type="hidden" name="gamipress_types[' + slug + '][slug]" value="' + slug + '"></td>';
                        row += '<td><input type="text" name="gamipress_types[' + slug + '][label]" value="' + (cfg.label || slug) + '" class="regular-text"></td>';
                        row += '<td><input type="number" step="0.0001" min="0" name="gamipress_types[' + slug + '][exchange_rate]" value="' + (cfg.exchange_rate || 1) + '" class="small-text"></td>';
                        row += '<td>' + (cfg.blog_id || '') + '<input type="hidden" name="gamipress_types[' + slug + '][blog_id]" value="' + (cfg.blog_id || '') + '"></td>';
                        row += '<td><input type="number" step="1" min="0" max="100" name="gamipress_types[' + slug + '][max_percent]" value="' + (cfg.max_percent || 100) + '" class="small-text">%</td>';
                        row += '<td><input type="checkbox" name="gamipress_types[' + slug + '][enabled]" value="1"' + (cfg.enabled ? ' checked' : '') + '></td>';
                        row += '</tr>';
                        $gpBody.append(row);
                    }
                } else {
                    $gpBody.append('<tr class="znc-no-types"><td colspan="6">No GamiPress point types found on enrolled sites.</td></tr>');
                }

                // ── Rebuild Tutor sites ──
                var $ts = $('#znc-tutor-sites');
                if (tuCount > 0) {
                    var th = '';
                    for (var bid in tu) {
                        th += '<span class="znc-tag">' + tu[bid].name + ' (' + tu[bid].courses + ' courses)</span> ';
                    }
                    $ts.html(th);
                } else {
                    $ts.html('<em>No Tutor LMS sites found</em>');
                }

                showNotice('✅ ' + (r.data.message || 'Point types detected!') + ' Click "Save Network Settings" to preserve exchange rates.', 'success');

            } else {
                showNotice('❌ Detection failed: ' + (r.data || 'Unknown'), 'error');
                $('#znc-detect-status').html('<span style="color:#dc3232;">Failed</span>');
            }
            btn.prop('disabled', false).html('<span class="dashicons dashicons-search" style="vertical-align:middle;"></span> Auto-Detect Point Types');
        }).fail(function(x){
            showNotice('❌ AJAX Error: ' + x.status + ' — ' + x.statusText, 'error');
            $('#znc-detect-status').html('<span style="color:#dc3232;">Failed</span>');
            btn.prop('disabled', false).html('<span class="dashicons dashicons-search" style="vertical-align:middle;"></span> Auto-Detect Point Types');
        });
    });

})(jQuery);
