<?php
/**
 * Network Security View — v1.3.2
 * FIXED: Has a proper <form> with Save button.
 */
defined( 'ABSPATH' ) || exit;

$settings = get_site_option( 'znc_network_settings', array() );
$secret   = get_site_option( 'znc_rest_secret', '' );
?>
<div class="wrap znc-wrap">
    <h1><?php esc_html_e( 'Net Cart — Security', 'zinckles-net-cart' ); ?></h1>

    <form id="znc-security-form">
        <h2><?php esc_html_e( 'HMAC Shared Secret', 'zinckles-net-cart' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Current Secret', 'zinckles-net-cart' ); ?></th>
                <td>
                    <code style="word-break:break-all;"><?php echo $secret ? substr( $secret, 0, 12 ) . str_repeat( '•', 40 ) . substr( $secret, -8 ) : 'Not set'; ?></code>
                    <p class="description"><?php esc_html_e( 'Used to sign cross-site REST requests between enrolled subsites and the checkout host.', 'zinckles-net-cart' ); ?></p>
                </td>
            </tr>
        </table>
        <p>
            <button type="button" id="znc-regenerate-secret" class="button button-secondary"><?php esc_html_e( 'Regenerate & Propagate Secret', 'zinckles-net-cart' ); ?></button>
            <span id="znc-secret-status" style="margin-left:12px;"></span>
        </p>

        <h2><?php esc_html_e( 'Request Validation', 'zinckles-net-cart' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Clock Skew Tolerance (seconds)', 'zinckles-net-cart' ); ?></th>
                <td>
                    <input type="number" name="clock_skew" value="<?php echo esc_attr( $settings['clock_skew'] ?? 300 ); ?>" min="30" max="600" />
                    <p class="description"><?php esc_html_e( 'Maximum allowed time difference between request timestamp and server time. Default: 300 seconds.', 'zinckles-net-cart' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Rate Limit (requests/minute)', 'zinckles-net-cart' ); ?></th>
                <td>
                    <input type="number" name="rate_limit" value="<?php echo esc_attr( $settings['rate_limit'] ?? 60 ); ?>" min="10" max="500" />
                    <p class="description"><?php esc_html_e( 'Maximum REST requests per minute from any single subsite. Default: 60.', 'zinckles-net-cart' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'IP Whitelist', 'zinckles-net-cart' ); ?></th>
                <td>
                    <textarea name="ip_whitelist" rows="4" cols="50" placeholder="One IP per line"><?php echo esc_textarea( $settings['ip_whitelist'] ?? '' ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'Optional. One IP address per line. Leave empty to allow all enrolled subsites.', 'zinckles-net-cart' ); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" id="znc-save-security" class="button button-primary"><?php esc_html_e( 'Save Security Settings', 'zinckles-net-cart' ); ?></button>
            <span id="znc-security-status" style="margin-left:12px;"></span>
        </p>
    </form>
</div>
