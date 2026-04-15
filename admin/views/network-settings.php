<?php
/**
 * Network Settings View — v1.4.1
 * Full admin settings page with all options.
 */
defined( 'ABSPATH' ) || exit;

$settings = get_site_option( 'znc_network_settings', array() );
$defaults = array(
    'checkout_host_id'     => get_main_site_id(),
    'enrollment_mode'      => 'manual',
    'base_currency'        => 'USD',
    'mixed_currency'       => 0,
    'cart_expiry_days'     => 7,
    'max_items'            => 100,
    'max_shops'            => 10,
    'debug_mode'           => 0,
    'clear_local_cart'     => 0,
    'cart_page_id'         => 0,
    'checkout_page_id'     => 0,
    'mycred_types_config'  => array(),
    'gamipress_types_config' => array(),
);
$s = wp_parse_args( $settings, $defaults );

// Get all sites for checkout host dropdown
$sites = get_sites( array( 'number' => 100 ) );
?>
<div class="wrap znc-admin-wrap">
    <h1><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'Zinckles Net Cart — Network Settings', 'zinckles-net-cart' ); ?></h1>

    <form id="znc-network-settings-form" method="post">
        <?php wp_nonce_field( 'znc_network_admin', 'nonce' ); ?>

        <!-- General Settings -->
        <div class="znc-settings-section">
            <h2><?php esc_html_e( 'General Settings', 'zinckles-net-cart' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="checkout_host_id"><?php esc_html_e( 'Checkout Host Site', 'zinckles-net-cart' ); ?></label></th>
                    <td>
                        <select name="checkout_host_id" id="checkout_host_id">
                            <?php foreach ( $sites as $site ) : ?>
                                <option value="<?php echo esc_attr( $site->blog_id ); ?>" <?php selected( $s['checkout_host_id'], $site->blog_id ); ?>>
                                    <?php echo esc_html( $site->blogname ?: $site->domain . $site->path ); ?> (ID: <?php echo esc_html( $site->blog_id ); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'The site that handles global checkout. Usually your main site.', 'zinckles-net-cart' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="enrollment_mode"><?php esc_html_e( 'Enrollment Mode', 'zinckles-net-cart' ); ?></label></th>
                    <td>
                        <select name="enrollment_mode" id="enrollment_mode">
                            <option value="manual" <?php selected( $s['enrollment_mode'], 'manual' ); ?>><?php esc_html_e( 'Manual — Admin enrolls each site', 'zinckles-net-cart' ); ?></option>
                            <option value="auto" <?php selected( $s['enrollment_mode'], 'auto' ); ?>><?php esc_html_e( 'Auto — All WooCommerce sites enrolled', 'zinckles-net-cart' ); ?></option>
                            <option value="whitelist" <?php selected( $s['enrollment_mode'], 'whitelist' ); ?>><?php esc_html_e( 'Whitelist — Only approved sites', 'zinckles-net-cart' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="base_currency"><?php esc_html_e( 'Base Currency', 'zinckles-net-cart' ); ?></label></th>
                    <td>
                        <select name="base_currency" id="base_currency">
                            <?php
                            $currencies = array( 'USD','EUR','GBP','CAD','AUD','JPY','CNY','INR','BRL','MXN','MYR','SGD','HKD','NZD','KRW','SEK','NOK','DKK','CHF','ZAR' );
                            foreach ( $currencies as $c ) :
                            ?>
                                <option value="<?php echo esc_attr( $c ); ?>" <?php selected( $s['base_currency'], $c ); ?>><?php echo esc_html( $c ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Mixed Currency', 'zinckles-net-cart' ); ?></th>
                    <td>
                        <label><input type="checkbox" name="mixed_currency" value="1" <?php checked( $s['mixed_currency'] ); ?> /> <?php esc_html_e( 'Allow items with different currencies in the same cart', 'zinckles-net-cart' ); ?></label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Clear Local Cart', 'zinckles-net-cart' ); ?></th>
                    <td>
                        <label><input type="checkbox" name="clear_local_cart" value="1" <?php checked( $s['clear_local_cart'] ); ?> /> <?php esc_html_e( 'Remove items from subsite WooCommerce cart after pushing to global cart', 'zinckles-net-cart' ); ?></label>
                        <p class="description"><?php esc_html_e( 'Prevents duplicate items showing in local and global carts.', 'zinckles-net-cart' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Cart Settings -->
        <div class="znc-settings-section">
            <h2><?php esc_html_e( 'Cart Settings', 'zinckles-net-cart' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="cart_expiry_days"><?php esc_html_e( 'Cart Expiry (days)', 'zinckles-net-cart' ); ?></label></th>
                    <td><input type="number" name="cart_expiry_days" id="cart_expiry_days" value="<?php echo esc_attr( $s['cart_expiry_days'] ); ?>" min="1" max="365" class="small-text" /></td>
                </tr>
                <tr>
                    <th><label for="max_items"><?php esc_html_e( 'Max Items per Cart', 'zinckles-net-cart' ); ?></label></th>
                    <td><input type="number" name="max_items" id="max_items" value="<?php echo esc_attr( $s['max_items'] ); ?>" min="1" max="1000" class="small-text" /></td>
                </tr>
                <tr>
                    <th><label for="max_shops"><?php esc_html_e( 'Max Shops per Cart', 'zinckles-net-cart' ); ?></label></th>
                    <td><input type="number" name="max_shops" id="max_shops" value="<?php echo esc_attr( $s['max_shops'] ); ?>" min="1" max="50" class="small-text" /></td>
                </tr>
                <tr>
                    <th><label for="cart_page_id"><?php esc_html_e( 'Global Cart Page ID', 'zinckles-net-cart' ); ?></label></th>
                    <td><input type="number" name="cart_page_id" id="cart_page_id" value="<?php echo esc_attr( $s['cart_page_id'] ); ?>" min="0" class="small-text" />
                    <p class="description"><?php esc_html_e( 'Page ID on checkout host containing [znc_global_cart] shortcode.', 'zinckles-net-cart' ); ?></p></td>
                </tr>
                <tr>
                    <th><label for="checkout_page_id"><?php esc_html_e( 'Checkout Page ID', 'zinckles-net-cart' ); ?></label></th>
                    <td><input type="number" name="checkout_page_id" id="checkout_page_id" value="<?php echo esc_attr( $s['checkout_page_id'] ); ?>" min="0" class="small-text" />
                    <p class="description"><?php esc_html_e( 'Page ID on checkout host containing [znc_checkout] shortcode.', 'zinckles-net-cart' ); ?></p></td>
                </tr>
            </table>
        </div>

        <!-- MyCred Point Types -->
        <div class="znc-settings-section">
            <h2><?php esc_html_e( 'MyCred Point Types', 'zinckles-net-cart' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Detected MyCred point types and their exchange rates to base currency.', 'zinckles-net-cart' ); ?></p>
            <table class="widefat striped" id="znc-mycred-types-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Slug', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Label', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Exchange Rate', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Enabled', 'zinckles-net-cart' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $mycred_config = $s['mycred_types_config'];
                    if ( ! empty( $mycred_config ) ) :
                        foreach ( $mycred_config as $slug => $cfg ) :
                            $label = isset( $cfg['label'] ) ? $cfg['label'] : $slug;
                            $rate  = isset( $cfg['exchange_rate'] ) ? $cfg['exchange_rate'] : 1;
                            $enabled = isset( $cfg['enabled'] ) ? $cfg['enabled'] : 1;
                    ?>
                    <tr>
                        <td><code><?php echo esc_html( $slug ); ?></code></td>
                        <td><input type="text" name="mycred_types[<?php echo esc_attr( $slug ); ?>][label]" value="<?php echo esc_attr( $label ); ?>" class="regular-text" /></td>
                        <td><input type="number" step="0.0001" name="mycred_types[<?php echo esc_attr( $slug ); ?>][exchange_rate]" value="<?php echo esc_attr( $rate ); ?>" class="small-text" /> <span class="description">1 point = <?php echo esc_html( $s['base_currency'] ); ?></span></td>
                        <td><input type="checkbox" name="mycred_types[<?php echo esc_attr( $slug ); ?>][enabled]" value="1" <?php checked( $enabled ); ?> /></td>
                    </tr>
                    <?php endforeach; else : ?>
                    <tr><td colspan="4"><?php esc_html_e( 'No MyCred point types detected. Click "Auto-Detect Point Types" below.', 'zinckles-net-cart' ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- GamiPress Point Types -->
        <div class="znc-settings-section">
            <h2><?php esc_html_e( 'GamiPress Point Types', 'zinckles-net-cart' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Detected GamiPress point types and their exchange rates to base currency.', 'zinckles-net-cart' ); ?></p>
            <table class="widefat striped" id="znc-gamipress-types-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Slug', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Label', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Exchange Rate', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Blog ID', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Enabled', 'zinckles-net-cart' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $gami_config = $s['gamipress_types_config'];
                    if ( ! empty( $gami_config ) ) :
                        foreach ( $gami_config as $slug => $cfg ) :
                            $label   = isset( $cfg['label'] ) ? $cfg['label'] : $slug;
                            $rate    = isset( $cfg['exchange_rate'] ) ? $cfg['exchange_rate'] : 1;
                            $bid     = isset( $cfg['blog_id'] ) ? $cfg['blog_id'] : '';
                            $enabled = isset( $cfg['enabled'] ) ? $cfg['enabled'] : 1;
                    ?>
                    <tr>
                        <td><code><?php echo esc_html( $slug ); ?></code></td>
                        <td><input type="text" name="gamipress_types[<?php echo esc_attr( $slug ); ?>][label]" value="<?php echo esc_attr( $label ); ?>" class="regular-text" /></td>
                        <td><input type="number" step="0.0001" name="gamipress_types[<?php echo esc_attr( $slug ); ?>][exchange_rate]" value="<?php echo esc_attr( $rate ); ?>" class="small-text" /></td>
                        <td><?php echo esc_html( $bid ); ?></td>
                        <td><input type="checkbox" name="gamipress_types[<?php echo esc_attr( $slug ); ?>][enabled]" value="1" <?php checked( $enabled ); ?> /></td>
                    </tr>
                    <?php endforeach; else : ?>
                    <tr><td colspan="5"><?php esc_html_e( 'No GamiPress point types detected. Click "Auto-Detect Point Types" below.', 'zinckles-net-cart' ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Auto-Detect Button -->
        <div class="znc-settings-section">
            <h2><?php esc_html_e( 'Point Type Detection', 'zinckles-net-cart' ); ?></h2>
            <p>
                <button type="button" id="znc-detect-point-types" class="button button-secondary">
                    <span class="dashicons dashicons-search" style="vertical-align:middle;"></span>
                    <?php esc_html_e( 'Auto-Detect Point Types', 'zinckles-net-cart' ); ?>
                </button>
                <span id="znc-detect-status" class="znc-inline-status"></span>
            </p>
            <p class="description"><?php esc_html_e( 'Scans all enrolled subsites for MyCred and GamiPress point types. Detected types will appear in the tables above.', 'zinckles-net-cart' ); ?></p>
        </div>

        <!-- Debug -->
        <div class="znc-settings-section">
            <h2><?php esc_html_e( 'Debug', 'zinckles-net-cart' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Debug Mode', 'zinckles-net-cart' ); ?></th>
                    <td>
                        <label><input type="checkbox" name="debug_mode" value="1" <?php checked( $s['debug_mode'] ); ?> /> <?php esc_html_e( 'Enable verbose logging to debug.log', 'zinckles-net-cart' ); ?></label>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <button type="submit" id="znc-save-settings" class="button button-primary button-hero">
                <?php esc_html_e( 'Save Network Settings', 'zinckles-net-cart' ); ?>
            </button>
            <span id="znc-save-status" class="znc-inline-status"></span>
        </p>
    </form>
</div>
