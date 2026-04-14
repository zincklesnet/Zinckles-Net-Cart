<?php
defined( 'ABSPATH' ) || exit;

$settings = get_site_option( 'znc_network_settings', array() );
$secret   = get_site_option( 'znc_rest_secret', '' );
?>
<div class="wrap">
    <h1>Zinckles Net Cart — Security</h1>

    <form id="znc-security-form">
        <?php wp_nonce_field( 'znc_network_admin', 'znc_nonce' ); ?>

        <h2>HMAC-SHA256 Shared Secret</h2>
        <table class="form-table">
            <tr>
                <th>Current Secret</th>
                <td>
                    <code style="word-break:break-all;display:block;max-width:500px;padding:8px;background:#f0f0f1;">
                        <?php echo $secret ? substr( $secret, 0, 8 ) . str_repeat( '•', 48 ) . substr( $secret, -8 ) : '<em>Not set</em>'; ?>
                    </code>
                    <p class="description">Used to sign cross-site REST requests between enrolled subsites and the checkout host.</p>
                </td>
            </tr>
            <tr>
                <th>Regenerate</th>
                <td>
                    <button type="button" class="button button-secondary" id="znc-regenerate-secret">
                        🔄 Regenerate Secret
                    </button>
                    <span id="znc-secret-status" style="margin-left:12px;"></span>
                    <p class="description">⚠️ This will invalidate all existing signed requests. All enrolled sites share the network-level secret automatically.</p>
                </td>
            </tr>
        </table>

        <h2>Request Validation</h2>
        <table class="form-table">
            <tr>
                <th>Clock Skew Tolerance (seconds)</th>
                <td>
                    <input type="number" name="clock_skew" value="<?php echo esc_attr( $settings['clock_skew'] ?? 300 ); ?>" min="30" max="3600" style="width:120px">
                    <p class="description">Maximum allowed time difference between request timestamp and server time. Default: 300 (5 minutes).</p>
                </td>
            </tr>
            <tr>
                <th>Rate Limit (requests/minute)</th>
                <td>
                    <input type="number" name="rate_limit" value="<?php echo esc_attr( $settings['rate_limit'] ?? 60 ); ?>" min="10" max="600" style="width:120px">
                    <p class="description">Maximum REST API requests per minute per enrolled site. Default: 60.</p>
                </td>
            </tr>
            <tr>
                <th>IP Whitelist</th>
                <td>
                    <textarea name="ip_whitelist" rows="4" cols="50" placeholder="One IP per line (e.g., 192.168.1.100)"><?php echo esc_textarea( $settings['ip_whitelist'] ?? '' ); ?></textarea>
                    <p class="description">Optional. Restrict REST requests to these IP addresses. Leave empty to allow all. One IP per line.</p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary" id="znc-save-security">Save Security Settings</button>
            <span id="znc-security-status" style="margin-left:12px;"></span>
        </p>
    </form>
</div>
