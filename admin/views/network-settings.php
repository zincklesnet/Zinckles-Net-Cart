<?php
defined( 'ABSPATH' ) || exit;

$settings = get_site_option( 'znc_network_settings', array() );
$host     = new ZNC_Checkout_Host();
$host_id  = $host->get_host_id();
$sites    = get_sites( array( 'number' => 200, 'fields' => 'ids' ) );

$mycred_cfg = ZNC_MyCred_Engine::get_types_config();

$currencies = array('USD','EUR','GBP','CAD','AUD','JPY','CHF','CNY','INR','BRL','MXN','KRW','SEK','NOK','DKK','NZD','SGD','HKD','ZAR','TRY');
?>
<div class="wrap">
    <h1>Zinckles Net Cart — Network Settings</h1>
    <form id="znc-network-settings-form">
        <?php wp_nonce_field( 'znc_network_admin', 'znc_nonce' ); ?>

        <h2>Checkout Host</h2>
        <p class="description">Choose which site hosts the global cart, checkout, and My Account pages.</p>
        <table class="form-table">
            <tr>
                <th>Checkout Host Site</th>
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
                    <p class="description">Enrolled subsites redirect cart/checkout/account pages here.</p>
                </td>
            </tr>
        </table>

        <h2>General</h2>
        <table class="form-table">
            <tr>
                <th>Enrollment Mode</th>
                <td>
                    <select name="enrollment_mode">
                        <option value="manual" <?php selected( $settings['enrollment_mode'] ?? 'manual', 'manual' ); ?>>Manual — Super Admin only</option>
                        <option value="opt-in" <?php selected( $settings['enrollment_mode'] ?? '', 'opt-in' ); ?>>Opt-In — shops request enrollment</option>
                        <option value="opt-out" <?php selected( $settings['enrollment_mode'] ?? '', 'opt-out' ); ?>>Opt-Out — all sites enrolled by default</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th>Base Currency</th>
                <td>
                    <select name="base_currency">
                        <?php foreach ( $currencies as $c ) : ?>
                            <option value="<?php echo $c; ?>" <?php selected( $settings['base_currency'] ?? 'USD', $c ); ?>><?php echo $c; ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th>Mixed Currencies</th>
                <td><label><input type="checkbox" name="mixed_currency" value="1" <?php checked( ! empty( $settings['mixed_currency'] ) ); ?>> Allow products in different currencies in one cart</label></td>
            </tr>
            <tr>
                <th>Cart Expiry (days)</th>
                <td><input type="number" name="cart_expiry_days" value="<?php echo esc_attr( $settings['cart_expiry_days'] ?? 7 ); ?>" min="1" max="90"></td>
            </tr>
            <tr>
                <th>Max Items per Cart</th>
                <td><input type="number" name="max_items" value="<?php echo esc_attr( $settings['max_items'] ?? 100 ); ?>" min="1" max="500"></td>
            </tr>
            <tr>
                <th>Max Shops per Cart</th>
                <td><input type="number" name="max_shops" value="<?php echo esc_attr( $settings['max_shops'] ?? 20 ); ?>" min="1" max="100"></td>
            </tr>
            <tr>
                <th>Debug Mode</th>
                <td><label><input type="checkbox" name="debug_mode" value="1" <?php checked( ! empty( $settings['debug_mode'] ) ); ?>> Log detailed events to error_log</label></td>
            </tr>
        </table>

        <h2>MyCred / ZCreds Point Types</h2>
        <p class="description">All detected MyCred point types across your network. Configure exchange rates and limits per type.</p>
        <?php if ( empty( $mycred_cfg ) ) : ?>
            <p><em>No MyCred point types detected. Install and activate MyCred on at least one enrolled site.</em></p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr><th>Enabled</th><th>Point Type</th><th>Slug</th><th>Exchange Rate (1 pt = $?)</th><th>Max % of Order</th><th>Source</th></tr>
                </thead>
                <tbody>
                <?php foreach ( $mycred_cfg as $slug => $cfg ) :
                    $info = $cfg['info'] ?? array();
                ?>
                    <tr>
                        <td><input type="checkbox" name="znc_mycred_types[<?php echo esc_attr( $slug ); ?>][enabled]" value="1" <?php checked( ! empty( $cfg['enabled'] ) ); ?>></td>
                        <td><strong><?php echo esc_html( $info['label'] ?? $slug ); ?></strong><br><small><?php echo esc_html( ( $info['singular'] ?? '' ) . ' / ' . ( $info['plural'] ?? '' ) ); ?></small></td>
                        <td><code><?php echo esc_html( $slug ); ?></code></td>
                        <td><input type="number" step="0.0001" min="0" name="znc_mycred_types[<?php echo esc_attr( $slug ); ?>][exchange_rate]" value="<?php echo esc_attr( $cfg['exchange_rate'] ?? 0 ); ?>" style="width:120px"></td>
                        <td><input type="number" min="0" max="100" name="znc_mycred_types[<?php echo esc_attr( $slug ); ?>][max_percent]" value="<?php echo esc_attr( $cfg['max_percent'] ?? 100 ); ?>" style="width:80px">%</td>
                        <td><small><?php echo esc_html( $info['source'] ?? 'host' ); ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <p class="submit">
            <button type="submit" class="button button-primary" id="znc-save-settings">Save Settings</button>
            <span id="znc-settings-status" style="margin-left:12px;"></span>
        </p>
    </form>
</div>
