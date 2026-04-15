<?php
/**
 * Network Settings View — v1.3.2
 * Adds checkout host selector and multi MyCred point type config.
 */
defined( 'ABSPATH' ) || exit;

$settings = get_site_option( 'znc_network_settings', array() );
$host     = new ZNC_Checkout_Host();
$host_id  = $host->get_host_id();
$sites    = get_sites( array( 'number' => 200, 'fields' => 'ids' ) );
$mycred_cfg = ZNC_MyCred_Engine::get_types_config();

$currencies = array('USD','EUR','GBP','CAD','AUD','JPY','CHF','CNY','INR','BRL','MXN','KRW','SEK','NOK','DKK','NZD','SGD','HKD','ZAR','TRY');
?>
<div class="wrap znc-wrap">
    <h1><?php esc_html_e( 'Zinckles Net Cart — Network Settings', 'zinckles-net-cart' ); ?></h1>

    <form id="znc-network-settings-form">

        <h2><?php esc_html_e( 'Checkout Host', 'zinckles-net-cart' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Choose which site hosts the global cart, checkout, and My Account. All enrolled subsites redirect customers here.', 'zinckles-net-cart' ); ?></p>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Checkout Host Site', 'zinckles-net-cart' ); ?></th>
                <td>
                    <select name="checkout_host_id">
                        <?php foreach ( $sites as $sid ) :
                            $d = get_blog_details( $sid );
                            if ( ! $d ) continue;
                            $label = $d->blogname . ' (' . $d->siteurl . ')';
                            if ( (int) $sid === (int) get_main_site_id() ) $label .= ' — Main Site';
                        ?>
                            <option value="<?php echo esc_attr( $sid ); ?>" <?php selected( $host_id, $sid ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'General', 'zinckles-net-cart' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Enrollment Mode', 'zinckles-net-cart' ); ?></th>
                <td>
                    <select name="enrollment_mode">
                        <option value="manual" <?php selected( $settings['enrollment_mode'] ?? 'manual', 'manual' ); ?>>Manual — Super Admin only</option>
                        <option value="opt-in" <?php selected( $settings['enrollment_mode'] ?? '', 'opt-in' ); ?>>Opt-In — shops request enrollment</option>
                        <option value="opt-out" <?php selected( $settings['enrollment_mode'] ?? '', 'opt-out' ); ?>>Opt-Out — all sites enrolled by default</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Base Currency', 'zinckles-net-cart' ); ?></th>
                <td>
                    <select name="base_currency">
                        <?php foreach ( $currencies as $c ) : ?>
                            <option value="<?php echo $c; ?>" <?php selected( $settings['base_currency'] ?? 'USD', $c ); ?>><?php echo $c; ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Allow Mixed Currencies', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="mixed_currency" value="1" <?php checked( $settings['mixed_currency'] ?? 0 ); ?>> Allow products in different currencies in one cart</label></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Cart Expiry (days)', 'zinckles-net-cart' ); ?></th>
                <td><input type="number" name="cart_expiry_days" value="<?php echo esc_attr( $settings['cart_expiry_days'] ?? 7 ); ?>" min="1" max="90"></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Max Items per Cart', 'zinckles-net-cart' ); ?></th>
                <td><input type="number" name="max_items" value="<?php echo esc_attr( $settings['max_items'] ?? 100 ); ?>" min="1" max="500"></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Max Shops per Cart', 'zinckles-net-cart' ); ?></th>
                <td><input type="number" name="max_shops" value="<?php echo esc_attr( $settings['max_shops'] ?? 20 ); ?>" min="1" max="100"></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Debug Mode', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="debug_mode" value="1" <?php checked( $settings['debug_mode'] ?? 0 ); ?>> Log detailed Net Cart events to error_log</label></td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'MyCred / ZCreds Point Types', 'zinckles-net-cart' ); ?></h2>
        <p class="description"><?php esc_html_e( 'All detected MyCred point types across your network. Configure exchange rates and limits per type.', 'zinckles-net-cart' ); ?></p>

        <?php if ( empty( $mycred_cfg ) ) : ?>
            <p><em><?php esc_html_e( 'No MyCred point types detected. Install and activate MyCred on at least one enrolled site.', 'zinckles-net-cart' ); ?></em></p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Enabled', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Point Type', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Slug', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Exchange Rate (1 point = $ ?)', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Max % of Cart', 'zinckles-net-cart' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $mycred_cfg as $slug => $cfg ) : $info = $cfg['info'] ?? array(); ?>
                        <tr>
                            <td><input type="checkbox" name="znc_mycred_types[<?php echo esc_attr( $slug ); ?>][enabled]" value="1" <?php checked( $cfg['enabled'] ?? 0 ); ?>></td>
                            <td><strong><?php echo esc_html( $info['label'] ?? $slug ); ?></strong></td>
                            <td><code><?php echo esc_html( $slug ); ?></code></td>
                            <td><input type="number" step="0.001" min="0" name="znc_mycred_types[<?php echo esc_attr( $slug ); ?>][exchange_rate]" value="<?php echo esc_attr( $cfg['exchange_rate'] ?? 0 ); ?>" style="width:120px;"></td>
                            <td><input type="number" min="0" max="100" name="znc_mycred_types[<?php echo esc_attr( $slug ); ?>][max_percent]" value="<?php echo esc_attr( $cfg['max_percent'] ?? 100 ); ?>" style="width:80px;">%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <p class="submit">
            <button type="submit" id="znc-save-settings" class="button button-primary"><?php esc_html_e( 'Save Settings', 'zinckles-net-cart' ); ?></button>
            <span id="znc-settings-status" style="margin-left:12px;"></span>
        </p>
    </form>
</div>
