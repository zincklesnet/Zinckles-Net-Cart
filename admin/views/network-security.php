<?php
/**
 * Network Security View — v1.4.1
 * HMAC secret, clock skew, rate limiting, IP whitelist.
 */
defined( 'ABSPATH' ) || exit;

$settings    = get_site_option( 'znc_network_settings', array() );
$secret      = isset( $settings['hmac_secret'] ) ? $settings['hmac_secret'] : '';
$clock_skew  = isset( $settings['clock_skew'] ) ? (int) $settings['clock_skew'] : 300;
$rate_limit  = isset( $settings['rate_limit'] ) ? (int) $settings['rate_limit'] : 60;
$ip_whitelist = isset( $settings['ip_whitelist'] ) ? $settings['ip_whitelist'] : '';
?>
<div class="wrap znc-admin-wrap">
    <h1><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Net Cart — Security', 'zinckles-net-cart' ); ?></h1>

    <form id="znc-security-form" method="post">
        <?php wp_nonce_field( 'znc_network_admin', 'nonce' ); ?>

        <div class="znc-settings-section">
            <h2><?php esc_html_e( 'HMAC Authentication', 'zinckles-net-cart' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'HMAC Secret', 'zinckles-net-cart' ); ?></th>
                    <td>
                        <div class="znc-secret-display">
                            <code id="znc-hmac-secret"><?php echo $secret ? esc_html( substr( $secret, 0, 12 ) . '••••••••••••' ) : esc_html__( 'Not generated', 'zinckles-net-cart' ); ?></code>
                        </div>
                        <p>
                            <button type="button" id="znc-regenerate-secret" class="button button-secondary">
                                <span class="dashicons dashicons-update" style="vertical-align:middle;"></span>
                                <?php esc_html_e( 'Regenerate Secret', 'zinckles-net-cart' ); ?>
                            </button>
                            <span id="znc-regen-status" class="znc-inline-status"></span>
                        </p>
                        <p class="description"><?php esc_html_e( 'Used for cross-subsite REST API authentication. Regenerating will invalidate all existing connections.', 'zinckles-net-cart' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="znc-settings-section">
            <h2><?php esc_html_e( 'Security Settings', 'zinckles-net-cart' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="clock_skew"><?php esc_html_e( 'Clock Skew Tolerance (seconds)', 'zinckles-net-cart' ); ?></label></th>
                    <td>
                        <input type="number" name="clock_skew" id="clock_skew" value="<?php echo esc_attr( $clock_skew ); ?>" min="30" max="3600" class="small-text" />
                        <p class="description"><?php esc_html_e( 'Maximum time difference allowed between servers. Default: 300 seconds (5 minutes).', 'zinckles-net-cart' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="rate_limit"><?php esc_html_e( 'Rate Limit (requests/minute)', 'zinckles-net-cart' ); ?></label></th>
                    <td>
                        <input type="number" name="rate_limit" id="rate_limit" value="<?php echo esc_attr( $rate_limit ); ?>" min="1" max="1000" class="small-text" />
                        <p class="description"><?php esc_html_e( 'Maximum API requests per IP per minute. Default: 60.', 'zinckles-net-cart' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ip_whitelist"><?php esc_html_e( 'IP Whitelist', 'zinckles-net-cart' ); ?></label></th>
                    <td>
                        <textarea name="ip_whitelist" id="ip_whitelist" rows="6" class="large-text code"><?php echo esc_textarea( $ip_whitelist ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'One IP address per line. Leave empty to allow all IPs. Only whitelisted IPs can make authenticated API calls.', 'zinckles-net-cart' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <button type="submit" id="znc-save-security" class="button button-primary button-hero">
                <?php esc_html_e( 'Save Security Settings', 'zinckles-net-cart' ); ?>
            </button>
            <span id="znc-security-save-status" class="znc-inline-status"></span>
        </p>
    </form>
</div>
