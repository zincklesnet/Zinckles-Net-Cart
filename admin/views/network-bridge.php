<?php
/**
 * Points Bridge — Network Admin page for GamiPress↔MyCred transfers.
 *
 * Renders the full admin UI for:
 *  • Auto-detecting GamiPress and MyCred point types
 *  • Configuring exchange rates
 *  • Single-user and bulk transfers
 *  • Transfer audit log
 *
 * @package ZincklesNetCart
 * @since   1.7.0
 */
defined( 'ABSPATH' ) || exit;

$bridge_nonce = wp_create_nonce( 'znc_bridge_nonce' );
?>
<div class="wrap znc-bridge-wrap">
    <h1><?php esc_html_e( 'Points Bridge — GamiPress ↔ MyCred', 'zinckles-net-cart' ); ?></h1>
    <p class="description">
        <?php esc_html_e( 'Detect, transfer, and sync point balances between GamiPress and MyCred across your multisite network.', 'zinckles-net-cart' ); ?>
    </p>

    <input type="hidden" id="znc-bridge-nonce" value="<?php echo esc_attr( $bridge_nonce ); ?>">

    <!-- ─── Detection Panel ─── -->
    <div class="znc-bridge-section">
        <h2><?php esc_html_e( 'Point Type Detection', 'zinckles-net-cart' ); ?></h2>
        <p>
            <button type="button" class="button button-primary" id="znc-detect-types">
                <?php esc_html_e( 'Scan Network for Point Types', 'zinckles-net-cart' ); ?>
            </button>
            <span class="spinner" id="znc-detect-spinner" style="float:none;"></span>
        </p>

        <div id="znc-detection-results" style="display:none;">
            <div class="znc-detection-cols" style="display:flex; gap:20px; flex-wrap:wrap;">
                <!-- GamiPress -->
                <div class="znc-detection-col" style="flex:1; min-width:300px;">
                    <h3><?php esc_html_e( 'GamiPress Point Types', 'zinckles-net-cart' ); ?></h3>
                    <table class="widefat striped" id="znc-gami-types-table">
                        <thead><tr>
                            <th><?php esc_html_e( 'Site', 'zinckles-net-cart' ); ?></th>
                            <th><?php esc_html_e( 'Point Type', 'zinckles-net-cart' ); ?></th>
                            <th><?php esc_html_e( 'Slug', 'zinckles-net-cart' ); ?></th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                    <p class="znc-no-gami" style="display:none;">
                        <?php esc_html_e( 'No GamiPress point types found on any site.', 'zinckles-net-cart' ); ?>
                    </p>
                </div>

                <!-- MyCred -->
                <div class="znc-detection-col" style="flex:1; min-width:300px;">
                    <h3><?php esc_html_e( 'MyCred Point Types', 'zinckles-net-cart' ); ?></h3>
                    <table class="widefat striped" id="znc-mycred-types-table">
                        <thead><tr>
                            <th><?php esc_html_e( 'Point Type', 'zinckles-net-cart' ); ?></th>
                            <th><?php esc_html_e( 'Key', 'zinckles-net-cart' ); ?></th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                    <p class="znc-no-mycred" style="display:none;">
                        <?php esc_html_e( 'No MyCred point types found. Is MyCred active?', 'zinckles-net-cart' ); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <hr>

    <!-- ─── Exchange Rates ─── -->
    <div class="znc-bridge-section">
        <h2><?php esc_html_e( 'Exchange Rates', 'zinckles-net-cart' ); ?></h2>
        <p class="description">
            <?php esc_html_e( 'Set how many MyCred points equal 1 GamiPress point. Example: rate 1.5 means 100 GamiPress → 150 MyCred.', 'zinckles-net-cart' ); ?>
        </p>

        <div id="znc-rates-panel" style="display:none;">
            <table class="widefat" id="znc-rates-table">
                <thead><tr>
                    <th><?php esc_html_e( 'GamiPress Source', 'zinckles-net-cart' ); ?></th>
                    <th><?php esc_html_e( 'MyCred Destination', 'zinckles-net-cart' ); ?></th>
                    <th><?php esc_html_e( 'Rate (1 GP = ? MC)', 'zinckles-net-cart' ); ?></th>
                </tr></thead>
                <tbody></tbody>
            </table>
            <p style="margin-top:10px;">
                <button type="button" class="button button-primary" id="znc-save-rates">
                    <?php esc_html_e( 'Save Rates', 'zinckles-net-cart' ); ?>
                </button>
                <span class="znc-rate-status" style="margin-left:10px;"></span>
            </p>
        </div>
        <p class="znc-rates-placeholder">
            <?php esc_html_e( 'Scan for point types first to configure exchange rates.', 'zinckles-net-cart' ); ?>
        </p>
    </div>

    <hr>

    <!-- ─── Single User Transfer ─── -->
    <div class="znc-bridge-section">
        <h2><?php esc_html_e( 'Single User Transfer', 'zinckles-net-cart' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><label for="znc-transfer-user"><?php esc_html_e( 'User ID', 'zinckles-net-cart' ); ?></label></th>
                <td>
                    <input type="number" id="znc-transfer-user" min="1" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. 42', 'zinckles-net-cart' ); ?>">
                    <button type="button" class="button" id="znc-check-user-balance"><?php esc_html_e( 'Check Balance', 'zinckles-net-cart' ); ?></button>
                    <span id="znc-user-balance-info" style="margin-left:10px;"></span>
                </td>
            </tr>
            <tr>
                <th><label for="znc-transfer-gami-slug"><?php esc_html_e( 'GamiPress Source', 'zinckles-net-cart' ); ?></label></th>
                <td>
                    <select id="znc-transfer-gami-slug" class="regular-text"><option value="">—</option></select>
                    <label style="margin-left:10px;">
                        <?php esc_html_e( 'on Site:', 'zinckles-net-cart' ); ?>
                        <select id="znc-transfer-gami-blog" class="regular-text"><option value="">—</option></select>
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="znc-transfer-mycred-key"><?php esc_html_e( 'MyCred Destination', 'zinckles-net-cart' ); ?></label></th>
                <td><select id="znc-transfer-mycred-key" class="regular-text"><option value="">—</option></select></td>
            </tr>
            <tr>
                <th><label for="znc-transfer-amount"><?php esc_html_e( 'Amount (0 = all)', 'zinckles-net-cart' ); ?></label></th>
                <td><input type="number" id="znc-transfer-amount" min="0" step="1" value="0" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="znc-transfer-rate"><?php esc_html_e( 'Exchange Rate', 'zinckles-net-cart' ); ?></label></th>
                <td><input type="number" id="znc-transfer-rate" min="0.001" step="0.01" value="1.00" class="regular-text"></td>
            </tr>
        </table>
        <p>
            <button type="button" class="button button-primary" id="znc-do-transfer">
                <?php esc_html_e( 'Transfer Points', 'zinckles-net-cart' ); ?>
            </button>
            <span class="spinner" id="znc-transfer-spinner" style="float:none;"></span>
            <span id="znc-transfer-result" style="margin-left:10px;"></span>
        </p>
    </div>

    <hr>

    <!-- ─── Bulk Transfer ─── -->
    <div class="znc-bridge-section">
        <h2><?php esc_html_e( 'Bulk Transfer (All Users)', 'zinckles-net-cart' ); ?></h2>
        <p class="description">
            <?php esc_html_e( 'Transfer GamiPress points to MyCred for ALL subscribers/customers/contributors/authors on the selected site. This cannot be undone.', 'zinckles-net-cart' ); ?>
        </p>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'GamiPress Source', 'zinckles-net-cart' ); ?></th>
                <td>
                    <select id="znc-bulk-gami-slug" class="regular-text"><option value="">—</option></select>
                    <label style="margin-left:10px;">
                        <?php esc_html_e( 'on Site:', 'zinckles-net-cart' ); ?>
                        <select id="znc-bulk-gami-blog" class="regular-text"><option value="">—</option></select>
                    </label>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'MyCred Destination', 'zinckles-net-cart' ); ?></th>
                <td><select id="znc-bulk-mycred-key" class="regular-text"><option value="">—</option></select></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Exchange Rate', 'zinckles-net-cart' ); ?></th>
                <td><input type="number" id="znc-bulk-rate" min="0.001" step="0.01" value="1.00" class="regular-text"></td>
            </tr>
        </table>
        <p>
            <button type="button" class="button button-secondary" id="znc-do-bulk-transfer" style="color:#d63638;">
                <?php esc_html_e( 'Bulk Transfer All Users', 'zinckles-net-cart' ); ?>
            </button>
            <span class="spinner" id="znc-bulk-spinner" style="float:none;"></span>
            <span id="znc-bulk-result" style="margin-left:10px;"></span>
        </p>
    </div>

    <hr>

    <!-- ─── Audit Log ─── -->
    <div class="znc-bridge-section">
        <h2><?php esc_html_e( 'Transfer Log', 'zinckles-net-cart' ); ?></h2>
        <p>
            <button type="button" class="button" id="znc-load-log">
                <?php esc_html_e( 'Load Recent Transfers', 'zinckles-net-cart' ); ?>
            </button>
        </p>
        <table class="widefat striped" id="znc-log-table" style="display:none;">
            <thead><tr>
                <th><?php esc_html_e( 'Date', 'zinckles-net-cart' ); ?></th>
                <th><?php esc_html_e( 'User', 'zinckles-net-cart' ); ?></th>
                <th><?php esc_html_e( 'GamiPress', 'zinckles-net-cart' ); ?></th>
                <th><?php esc_html_e( 'MyCred', 'zinckles-net-cart' ); ?></th>
                <th><?php esc_html_e( 'Rate', 'zinckles-net-cart' ); ?></th>
                <th><?php esc_html_e( 'Admin', 'zinckles-net-cart' ); ?></th>
            </tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<script>
jQuery(function($) {
    var nonce   = $('#znc-bridge-nonce').val();
    var ajaxUrl = ajaxurl;
    var gamiData = {};
    var mycredData = {};

    /* ── Detection ── */
    $('#znc-detect-types').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true);
        $('#znc-detect-spinner').addClass('is-active');

        $.post(ajaxUrl, { action: 'znc_bridge_detect_types', nonce: nonce }, function(res) {
            $btn.prop('disabled', false);
            $('#znc-detect-spinner').removeClass('is-active');

            if (!res.success) { alert('Detection failed.'); return; }

            gamiData  = res.data.gamipress || {};
            mycredData = res.data.mycred || {};

            // Populate GamiPress table
            var $gBody = $('#znc-gami-types-table tbody').empty();
            var gamiCount = 0;
            for (var blogId in gamiData) {
                var blog = gamiData[blogId];
                blog.types.forEach(function(t) {
                    gamiCount++;
                    $gBody.append('<tr><td>' + blog.blog_name + '</td><td>' + t.label + '</td><td><code>' + t.slug + '</code></td></tr>');
                });
            }
            $('.znc-no-gami').toggle(gamiCount === 0);

            // Populate MyCred table
            var $mBody = $('#znc-mycred-types-table tbody').empty();
            var mcCount = 0;
            for (var key in mycredData) {
                mcCount++;
                $mBody.append('<tr><td>' + mycredData[key] + '</td><td><code>' + key + '</code></td></tr>');
            }
            $('.znc-no-mycred').toggle(mcCount === 0);

            $('#znc-detection-results').show();

            // Populate dropdowns
            populateDropdowns();

            // Build rates table
            buildRatesTable();
        });
    });

    function populateDropdowns() {
        var gamiSlugs = [];
        var gamiBlogOptions = '<option value="">—</option>';
        var gamiSlugOptions = '<option value="">—</option>';
        var mycredOptions   = '<option value="">—</option>';

        for (var blogId in gamiData) {
            gamiBlogOptions += '<option value="' + blogId + '">' + gamiData[blogId].blog_name + '</option>';
            gamiData[blogId].types.forEach(function(t) {
                if (gamiSlugs.indexOf(t.slug) === -1) {
                    gamiSlugs.push(t.slug);
                    gamiSlugOptions += '<option value="' + t.slug + '">' + t.label + '</option>';
                }
            });
        }

        for (var key in mycredData) {
            mycredOptions += '<option value="' + key + '">' + mycredData[key] + '</option>';
        }

        $('#znc-transfer-gami-slug, #znc-bulk-gami-slug').html(gamiSlugOptions);
        $('#znc-transfer-gami-blog, #znc-bulk-gami-blog').html(gamiBlogOptions);
        $('#znc-transfer-mycred-key, #znc-bulk-mycred-key').html(mycredOptions);
    }

    function buildRatesTable() {
        var gamiSlugs = [];
        for (var blogId in gamiData) {
            gamiData[blogId].types.forEach(function(t) {
                if (gamiSlugs.indexOf(t.slug) === -1) gamiSlugs.push(t.slug);
            });
        }

        if (gamiSlugs.length === 0 || Object.keys(mycredData).length === 0) {
            $('#znc-rates-panel').hide();
            return;
        }

        var $tbody = $('#znc-rates-table tbody').empty();
        gamiSlugs.forEach(function(gs) {
            for (var mk in mycredData) {
                var rateKey = gs + '|' + mk;
                $tbody.append(
                    '<tr>' +
                    '<td>' + gs + '</td>' +
                    '<td>' + mycredData[mk] + ' (' + mk + ')</td>' +
                    '<td><input type="number" class="znc-rate-input" data-key="' + rateKey + '" min="0.001" step="0.01" value="1.00" style="width:100px;"></td>' +
                    '</tr>'
                );
            }
        });

        $('#znc-rates-panel').show();
        $('.znc-rates-placeholder').hide();
    }

    /* ── Save Rates ── */
    $('#znc-save-rates').on('click', function() {
        var rates = {};
        $('.znc-rate-input').each(function() {
            rates[$(this).data('key')] = parseFloat($(this).val()) || 1.0;
        });

        $.post(ajaxUrl, { action: 'znc_bridge_save_rates', nonce: nonce, rates: rates }, function(res) {
            if (res.success) {
                $('.znc-rate-status').text('Saved ' + res.data.saved + ' rates.').css('color', 'green');
            } else {
                $('.znc-rate-status').text('Save failed.').css('color', 'red');
            }
        });
    });

    /* ── Single Transfer ── */
    $('#znc-do-transfer').on('click', function() {
        var userId   = parseInt($('#znc-transfer-user').val(), 10);
        var gamiSlug = $('#znc-transfer-gami-slug').val();
        var gamiBlog = parseInt($('#znc-transfer-gami-blog').val(), 10);
        var mycredKey = $('#znc-transfer-mycred-key').val();
        var amount   = parseFloat($('#znc-transfer-amount').val()) || 0;
        var rate     = parseFloat($('#znc-transfer-rate').val()) || 1.0;

        if (!userId || !gamiSlug || !gamiBlog || !mycredKey) {
            alert('Please fill all fields.');
            return;
        }

        if (!confirm('Transfer ' + (amount || 'ALL') + ' ' + gamiSlug + ' points for user #' + userId + '?')) return;

        $('#znc-transfer-spinner').addClass('is-active');

        $.post(ajaxUrl, {
            action: 'znc_bridge_transfer', nonce: nonce,
            user_id: userId, gami_slug: gamiSlug, gami_blog_id: gamiBlog,
            mycred_key: mycredKey, amount: amount, rate: rate
        }, function(res) {
            $('#znc-transfer-spinner').removeClass('is-active');
            if (res.success) {
                $('#znc-transfer-result').text('Deducted ' + res.data.deducted + ' GP, credited ' + res.data.credited + ' MC.').css('color', 'green');
            } else {
                $('#znc-transfer-result').text(res.data.message).css('color', 'red');
            }
        });
    });

    /* ── Bulk Transfer ── */
    $('#znc-do-bulk-transfer').on('click', function() {
        var gamiSlug = $('#znc-bulk-gami-slug').val();
        var gamiBlog = parseInt($('#znc-bulk-gami-blog').val(), 10);
        var mycredKey = $('#znc-bulk-mycred-key').val();
        var rate     = parseFloat($('#znc-bulk-rate').val()) || 1.0;

        if (!gamiSlug || !gamiBlog || !mycredKey) {
            alert('Please fill all fields.');
            return;
        }

        if (!confirm('This will transfer ALL ' + gamiSlug + ' points for ALL users. This cannot be undone. Proceed?')) return;

        $('#znc-bulk-spinner').addClass('is-active');

        $.post(ajaxUrl, {
            action: 'znc_bridge_bulk_transfer', nonce: nonce,
            gami_slug: gamiSlug, gami_blog_id: gamiBlog,
            mycred_key: mycredKey, rate: rate
        }, function(res) {
            $('#znc-bulk-spinner').removeClass('is-active');
            if (res.success) {
                $('#znc-bulk-result').text(
                    'Done: ' + res.data.success + ' transferred, ' +
                    res.data.failed + ' failed, ' + res.data.skipped + ' skipped.'
                ).css('color', 'green');
            } else {
                $('#znc-bulk-result').text(res.data.message).css('color', 'red');
            }
        });
    });

    /* ── Audit Log ── */
    $('#znc-load-log').on('click', function() {
        $.post(ajaxUrl, { action: 'znc_bridge_get_log', nonce: nonce, limit: 50 }, function(res) {
            if (!res.success) return;

            var $tbody = $('#znc-log-table tbody').empty();
            var log = res.data.log || [];

            if (log.length === 0) {
                $tbody.append('<tr><td colspan="6">No transfers recorded yet.</td></tr>');
            } else {
                log.forEach(function(entry) {
                    $tbody.append(
                        '<tr>' +
                        '<td>' + (entry.timestamp || '—') + '</td>' +
                        '<td>#' + entry.user_id + '</td>' +
                        '<td>' + entry.gami_amount + ' ' + entry.gami_slug + ' (Blog ' + entry.gami_blog_id + ')</td>' +
                        '<td>' + entry.mycred_amount + ' ' + entry.mycred_key + '</td>' +
                        '<td>' + entry.rate + '</td>' +
                        '<td>#' + entry.admin_id + '</td>' +
                        '</tr>'
                    );
                });
            }

            $('#znc-log-table').show();
        });
    });
});
</script>

<style>
.znc-bridge-wrap h2 { margin-top: 25px; }
.znc-bridge-section { margin-bottom: 15px; }
.znc-balance-ok td { background: #f0fff0 !important; }
.znc-balance-short td { background: #fff5f5 !important; }
#znc-log-table td, #znc-log-table th { padding: 6px 10px; }
</style>
