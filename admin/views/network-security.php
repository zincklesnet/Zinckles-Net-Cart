<?php
/**
 * Network Admin — Security Settings
 *
 * v1.2.0 FIX: Added full form with Save button + regenerate secret button.
 */
defined( 'ABSPATH' ) || exit;

$secret = $settings['rest_shared_secret'] ?? '';
$secret_preview = $secret
    ? substr( $secret, 0, 8 ) . '…' . substr( $secret, -8 )
    : __( 'Not generated', 'znc' );
?>
<div class="wrap">
    <h1><?php _e( 'Net Cart — Security', 'znc' ); ?></h1>

    <?php if ( isset( $_GET['updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e( 'Security settings saved.', 'znc' ); ?></p>
        </div>
    <?php endif; ?>

    <!-- ── HMAC Secret ────────────────────────────────────── -->
    <div class="card" style="max-width:720px;margin-bottom:24px;">
        <h2><?php _e( 'HMAC-SHA256 Shared Secret', 'znc' ); ?></h2>
        <p class="description">
            <?php _e( 'This secret is used to sign all cross-site REST requests between the main site and enrolled subsites.', 'znc' ); ?>
        </p>

        <table class="form-table">
            <tr>
                <th><?php _e( 'Current Secret', 'znc' ); ?></th>
                <td>
                    <code id="znc-secret-preview"><?php echo esc_html( $secret_preview ); ?></code>
                    <?php if ( $secret ) : ?>
                        <span class="dashicons dashicons-yes-alt" style="color:#4caf50;vertical-align:middle;" title="Active"></span>
                    <?php else : ?>
                        <span class="dashicons dashicons-warning" style="color:#f44336;vertical-align:middle;" title="Not set"></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th></th>
                <td>
                    <button type="button" class="button button-secondary" id="znc-regenerate-secret">
                        <?php _e( 'Regenerate & Propagate', 'znc' ); ?>
                    </button>
                    <p class="description">
                        <?php _e( 'Generates a new 64-character secret and pushes it to all enrolled subsites automatically.', 'znc' ); ?>
                    </p>
                    <div id="znc-secret-status" style="margin-top:8px;"></div>
                </td>
            </tr>
        </table>
    </div>

    <!-- ── Security Settings Form ─────────────────────────── -->
    <form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=znc_save_security' ) ); ?>">
        <?php wp_nonce_field( 'znc_save_security_settings' ); ?>

        <div class="card" style="max-width:720px;margin-bottom:24px;">
            <h2><?php _e( 'Request Validation', 'znc' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th><label for="znc-clock-skew"><?php _e( 'Clock Skew Tolerance', 'znc' ); ?></label></th>
                    <td>
                        <input type="number" id="znc-clock-skew"
                               name="znc[rest_clock_skew]"
                               value="<?php echo esc_attr( $settings['rest_clock_skew'] ); ?>"
                               min="30" max="3600" step="30" class="small-text">
                        <?php _e( 'seconds', 'znc' ); ?>
                        <p class="description">
                            <?php _e( 'Maximum time difference allowed between request timestamp and server time. Default: 300 (5 minutes).', 'znc' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th><label for="znc-rate-limit"><?php _e( 'Rate Limit', 'znc' ); ?></label></th>
                    <td>
                        <input type="number" id="znc-rate-limit"
                               name="znc[rest_rate_limit]"
                               value="<?php echo esc_attr( $settings['rest_rate_limit'] ); ?>"
                               min="10" max="1000" class="small-text">
                        <?php _e( 'requests per minute per site', 'znc' ); ?>
                        <p class="description">
                            <?php _e( 'Maximum REST API requests allowed per minute from each enrolled subsite.', 'znc' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th><label for="znc-ip-whitelist"><?php _e( 'IP Whitelist', 'znc' ); ?></label></th>
                    <td>
                        <textarea id="znc-ip-whitelist"
                                  name="znc[rest_ip_whitelist]"
                                  rows="3" class="large-text code"
                                  placeholder="e.g. 192.168.1.100, 10.0.0.0/24"><?php echo esc_textarea( $settings['rest_ip_whitelist'] ); ?></textarea>
                        <p class="description">
                            <?php _e( 'Comma-separated list of allowed IP addresses or CIDR ranges. Leave empty to allow all IPs (HMAC verification still applies).', 'znc' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button( __( 'Save Security Settings', 'znc' ), 'primary', 'submit', true ); ?>
    </form>
</div>
