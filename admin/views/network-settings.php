<?php
/**
 * Network Settings View — v1.6.1
 * Full admin settings with structured MyCred/GamiPress config, save indicators.
 */
defined( 'ABSPATH' ) || exit;

$settings = get_site_option( 'znc_network_settings', array() );
$defaults = array(
    'checkout_host_id'        => get_main_site_id(),
    'enrollment_mode'         => 'manual',
    'base_currency'           => 'USD',
    'mixed_currency'          => 0,
    'cart_expiry_days'        => 7,
    'max_items'               => 100,
    'max_shops'               => 10,
    'debug_mode'              => 0,
    'clear_local_cart'        => 0,
    'enable_cart_sync'        => 1,
    'enable_admin_bar_cart'   => 1,
    'cart_page_id'            => 0,
    'checkout_page_id'        => 0,
    'tutor_lms_support'       => 0,
    'mycred_types_config'     => array(),
    'gamipress_types_config'  => array(),
    'tutor_sites'             => array(),
    'mycred_hooks'            => array(),
    'gamipress_hooks'         => array(),
);
$s = wp_parse_args( $settings, $defaults );
$sites = get_sites( array( 'number' => 100 ) );

$currencies = array( 'USD','EUR','GBP','CAD','AUD','JPY','CNY','INR','BRL','MXN','MYR','SGD','HKD','NZD','KRW','SEK','NOK','DKK','CHF','ZAR' );
?>
<div class="wrap znc-admin-wrap">
    <h1><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'Zinckles Net Cart — Network Settings', 'zinckles-net-cart' ); ?></h1>

    <div id="znc-save-notice" style="display:none;" class="notice is-dismissible"><p></p></div>

    <form id="znc-settings-form" method="post">
        <?php wp_nonce_field( 'znc_network_admin', 'nonce' ); ?>

        <!-- General Settings -->
        <div class="znc-settings-section">
            <h2><?php esc_html_e( 'General Settings', 'zinckles-net-cart' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="checkout_host_id"><?php esc_html_e( 'Checkout Host Site', 'zinckles-net-cart' ); ?></label></th>
                    <td>
                        <select name="checkout_host_id" id="checkout_host_id">
                            <?php foreach ( $sites as $site ) :
                                $d = get_blog_details( $site->blog_id ); ?>
                                <option value="<?php echo esc_attr( $site->blog_id ); ?>" <?php selected( $s['checkout_host_id'], $site->blog_id ); ?>>
                                    <?php echo esc_html( $d ? $d->blogname : $site->domain . $site->path ); ?> (ID: <?php echo esc_html( $site->blog_id ); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'The site that hosts global cart and checkout pages.', 'zinckles-net-cart' ); ?></p>
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
                            <?php foreach ( $currencies as $c ) : ?>
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
                    <th><?php esc_html_e( 'Clear Local Cart', 'zinckles-net-cart' ); ?></th>
                    <td>
                        <label><input type="checkbox" name="clear_local_cart" value="1" <?php checked( $s['clear_local_cart'] ); ?> /> <?php esc_html_e( 'Remove items from subsite WooCommerce cart after adding to global cart', 'zinckles-net-cart' ); ?></label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Cart Sync', 'zinckles-net-cart' ); ?></th>
                    <td>
                        <label><input type="checkbox" name="enable_cart_sync" value="1" <?php checked( $s['enable_cart_sync'] ); ?> /> <?php esc_html_e( 'Override WooCommerce cart count/fragments with global cart data', 'zinckles-net-cart' ); ?></label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Admin Bar Cart', 'zinckles-net-cart' ); ?></th>
                    <td>
                        <label><input type="checkbox" name="enable_admin_bar_cart" value="1" <?php checked( $s['enable_admin_bar_cart'] ); ?> /> <?php esc_html_e( 'Show Net Cart link in WordPress admin bar', 'zinckles-net-cart' ); ?></label>
                    </td>
                </tr>
                <tr>
                    <th><label for="cart_page_id"><?php esc_html_e( 'Global Cart Page ID', 'zinckles-net-cart' ); ?></label></th>
                    <td>
                        <input type="number" name="cart_page_id" id="cart_page_id" value="<?php echo esc_attr( $s['cart_page_id'] ); ?>" min="0" class="small-text" />
                        <p class="description"><?php esc_html_e( 'Page ID on checkout host containing [znc_global_cart]. Leave 0 for auto-detect.', 'zinckles-net-cart' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="checkout_page_id"><?php esc_html_e( 'Checkout Page ID', 'zinckles-net-cart' ); ?></label></th>
                    <td>
                        <input type="number" name="checkout_page_id" id="checkout_page_id" value="<?php echo esc_attr( $s['checkout_page_id'] ); ?>" min="0" class="small-text" />
                        <p class="description"><?php esc_html_e( 'Page ID on checkout host containing [znc_checkout]. Leave 0 for auto-detect.', 'zinckles-net-cart' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Tutor LMS -->
        <div class="znc-settings-section">
            <h2><?php esc_html_e( 'Tutor LMS Integration', 'zinckles-net-cart' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Tutor LMS Support', 'zinckles-net-cart' ); ?></th>
                    <td>
                        <label><input type="checkbox" name="tutor_lms_support" value="1" <?php checked( $s['tutor_lms_support'] ); ?> /> <?php esc_html_e( 'Enable Tutor LMS course integration (auto-enrollment after purchase)', 'zinckles-net-cart' ); ?></label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Tutor Sites', 'zinckles-net-cart' ); ?></th>
                    <td>
                        <div id="znc-tutor-sites">
                        <?php if ( empty( $s['tutor_sites'] ) ) : ?>
                            <em><?php esc_html_e( 'None detected — click Auto-Detect below', 'zinckles-net-cart' ); ?></em>
                        <?php else : ?>
                            <?php foreach ( (array) $s['tutor_sites'] as $bid ) :
                                $d = get_blog_details( absint( $bid ) );
                                if ( $d ) echo '<span class="znc-tag">' . esc_html( $d->blogname ) . ' (ID: ' . absint( $bid ) . ')</span> ';
                            endforeach; ?>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- MyCred Point Types -->
        <div class="znc-settings-section">
            <h2><?php esc_html_e( 'MyCred Point Types', 'zinckles-net-cart' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Configure exchange rates and enable/disable detected MyCred point types.', 'zinckles-net-cart' ); ?></p>
            <table class="widefat striped" id="znc-mycred-types-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Slug', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Label', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Exchange Rate', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Max % at Checkout', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Enabled', 'zinckles-net-cart' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $mycred_config = (array) $s['mycred_types_config'];
                    if ( ! empty( $mycred_config ) ) :
                        foreach ( $mycred_config as $slug => $cfg ) :
                            $label   = isset( $cfg['label'] ) ? $cfg['label'] : $slug;
                            $rate    = isset( $cfg['exchange_rate'] ) ? $cfg['exchange_rate'] : 1;
                            $max_pct = isset( $cfg['max_percent'] ) ? $cfg['max_percent'] : 100;
                            $enabled = isset( $cfg['enabled'] ) ? $cfg['enabled'] : 1;
                    ?>
                    <tr>
                        <td><code><?php echo esc_html( $slug ); ?></code><input type="hidden" name="mycred_types[<?php echo esc_attr( $slug ); ?>][slug]" value="<?php echo esc_attr( $slug ); ?>"></td>
                        <td><input type="text" name="mycred_types[<?php echo esc_attr( $slug ); ?>][label]" value="<?php echo esc_attr( $label ); ?>" class="regular-text" /></td>
                        <td><input type="number" step="0.0001" min="0" name="mycred_types[<?php echo esc_attr( $slug ); ?>][exchange_rate]" value="<?php echo esc_attr( $rate ); ?>" class="small-text" /> <span class="description">1 point = X <?php echo esc_html( $s['base_currency'] ); ?></span></td>
                        <td><input type="number" step="1" min="0" max="100" name="mycred_types[<?php echo esc_attr( $slug ); ?>][max_percent]" value="<?php echo esc_attr( $max_pct ); ?>" class="small-text" />%</td>
                        <td><input type="checkbox" name="mycred_types[<?php echo esc_attr( $slug ); ?>][enabled]" value="1" <?php checked( $enabled ); ?> /></td>
                    </tr>
                    <?php endforeach; else : ?>
                    <tr class="znc-no-types"><td colspan="5"><?php esc_html_e( 'No MyCred point types detected. Click "Auto-Detect Point Types" below.', 'zinckles-net-cart' ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- MyCred Hooks -->
        <div class="znc-settings-section">
            <h2><?php esc_html_e( 'MyCred Hooks', 'zinckles-net-cart' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Award or deduct MyCred points on Net Cart events.', 'zinckles-net-cart' ); ?></p>
            <?php
            $mc_hooks = (array) $s['mycred_hooks'];
            $mc_hook_defs = array(
                'add_to_cart'      => array( 'label' => 'Add to Global Cart',       'default_amount' => 0 ),
                'complete_order'   => array( 'label' => 'Complete Net Cart Order',   'default_amount' => 0 ),
                'leave_review'     => array( 'label' => 'Leave Product Review',      'default_amount' => 0 ),
                'refer_purchase'   => array( 'label' => 'Referral Purchase',         'default_amount' => 0 ),
            );
            ?>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e( 'Event', 'zinckles-net-cart' ); ?></th><th><?php esc_html_e( 'Points (+/-)', 'zinckles-net-cart' ); ?></th><th><?php esc_html_e( 'Point Type', 'zinckles-net-cart' ); ?></th><th><?php esc_html_e( 'Enabled', 'zinckles-net-cart' ); ?></th></tr></thead>
                <tbody>
                <?php foreach ( $mc_hook_defs as $hook_key => $def ) :
                    $hk = isset( $mc_hooks[ $hook_key ] ) ? $mc_hooks[ $hook_key ] : array();
                    $amt = isset( $hk['amount'] ) ? $hk['amount'] : $def['default_amount'];
                    $pt  = isset( $hk['point_type'] ) ? $hk['point_type'] : 'mycred_default';
                    $en  = isset( $hk['enabled'] ) ? $hk['enabled'] : 0;
                ?>
                <tr>
                    <td><?php echo esc_html( $def['label'] ); ?></td>
                    <td><input type="number" name="mycred_hooks[<?php echo esc_attr( $hook_key ); ?>][amount]" value="<?php echo esc_attr( $amt ); ?>" class="small-text" step="1"></td>
                    <td>
                        <select name="mycred_hooks[<?php echo esc_attr( $hook_key ); ?>][point_type]">
                            <?php if ( ! empty( $mycred_config ) ) :
                                foreach ( $mycred_config as $slug => $cfg ) : ?>
                                    <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $pt, $slug ); ?>><?php echo esc_html( $cfg['label'] ?? $slug ); ?></option>
                                <?php endforeach;
                            else : ?>
                                <option value="mycred_default">Points</option>
                            <?php endif; ?>
                        </select>
                    </td>
                    <td><input type="checkbox" name="mycred_hooks[<?php echo esc_attr( $hook_key ); ?>][enabled]" value="1" <?php checked( $en ); ?>></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- GamiPress Point Types -->
        <div class="znc-settings-section">
            <h2><?php esc_html_e( 'GamiPress Point Types', 'zinckles-net-cart' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Configure exchange rates and enable/disable detected GamiPress point types.', 'zinckles-net-cart' ); ?></p>
            <table class="widefat striped" id="znc-gamipress-types-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Slug', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Label', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Exchange Rate', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Blog ID', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Max % at Checkout', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Enabled', 'zinckles-net-cart' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $gami_config = (array) $s['gamipress_types_config'];
                    if ( ! empty( $gami_config ) ) :
                        foreach ( $gami_config as $slug => $cfg ) :
                            $label   = isset( $cfg['label'] ) ? $cfg['label'] : $slug;
                            $rate    = isset( $cfg['exchange_rate'] ) ? $cfg['exchange_rate'] : 1;
                            $bid     = isset( $cfg['blog_id'] ) ? $cfg['blog_id'] : '';
                            $max_pct = isset( $cfg['max_percent'] ) ? $cfg['max_percent'] : 100;
                            $enabled = isset( $cfg['enabled'] ) ? $cfg['enabled'] : 1;
                    ?>
                    <tr>
                        <td><code><?php echo esc_html( $slug ); ?></code><input type="hidden" name="gamipress_types[<?php echo esc_attr( $slug ); ?>][slug]" value="<?php echo esc_attr( $slug ); ?>"></td>
                        <td><input type="text" name="gamipress_types[<?php echo esc_attr( $slug ); ?>][label]" value="<?php echo esc_attr( $label ); ?>" class="regular-text" /></td>
                        <td><input type="number" step="0.0001" min="0" name="gamipress_types[<?php echo esc_attr( $slug ); ?>][exchange_rate]" value="<?php echo esc_attr( $rate ); ?>" class="small-text" /></td>
                        <td><?php echo esc_html( $bid ); ?><input type="hidden" name="gamipress_types[<?php echo esc_attr( $slug ); ?>][blog_id]" value="<?php echo esc_attr( $bid ); ?>"></td>
                        <td><input type="number" step="1" min="0" max="100" name="gamipress_types[<?php echo esc_attr( $slug ); ?>][max_percent]" value="<?php echo esc_attr( $max_pct ); ?>" class="small-text" />%</td>
                        <td><input type="checkbox" name="gamipress_types[<?php echo esc_attr( $slug ); ?>][enabled]" value="1" <?php checked( $enabled ); ?> /></td>
                    </tr>
                    <?php endforeach; else : ?>
                    <tr class="znc-no-types"><td colspan="6"><?php esc_html_e( 'No GamiPress point types detected. Click "Auto-Detect Point Types" below.', 'zinckles-net-cart' ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- GamiPress Hooks -->
        <div class="znc-settings-section">
            <h2><?php esc_html_e( 'GamiPress Hooks', 'zinckles-net-cart' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Award or deduct GamiPress points on Net Cart events.', 'zinckles-net-cart' ); ?></p>
            <?php
            $gp_hooks = (array) $s['gamipress_hooks'];
            $gp_hook_defs = array(
                'add_to_cart'      => array( 'label' => 'Add to Global Cart',       'default_amount' => 0 ),
                'complete_order'   => array( 'label' => 'Complete Net Cart Order',   'default_amount' => 0 ),
                'enroll_course'    => array( 'label' => 'Enroll in Course',          'default_amount' => 0 ),
                'refer_purchase'   => array( 'label' => 'Referral Purchase',         'default_amount' => 0 ),
            );
            ?>
            <table class="widefat striped">
                <thead><tr><th><?php esc_html_e( 'Event', 'zinckles-net-cart' ); ?></th><th><?php esc_html_e( 'Points (+/-)', 'zinckles-net-cart' ); ?></th><th><?php esc_html_e( 'Point Type', 'zinckles-net-cart' ); ?></th><th><?php esc_html_e( 'Enabled', 'zinckles-net-cart' ); ?></th></tr></thead>
                <tbody>
                <?php foreach ( $gp_hook_defs as $hook_key => $def ) :
                    $hk = isset( $gp_hooks[ $hook_key ] ) ? $gp_hooks[ $hook_key ] : array();
                    $amt = isset( $hk['amount'] ) ? $hk['amount'] : $def['default_amount'];
                    $pt  = isset( $hk['point_type'] ) ? $hk['point_type'] : '';
                    $en  = isset( $hk['enabled'] ) ? $hk['enabled'] : 0;
                ?>
                <tr>
                    <td><?php echo esc_html( $def['label'] ); ?></td>
                    <td><input type="number" name="gamipress_hooks[<?php echo esc_attr( $hook_key ); ?>][amount]" value="<?php echo esc_attr( $amt ); ?>" class="small-text" step="1"></td>
                    <td>
                        <select name="gamipress_hooks[<?php echo esc_attr( $hook_key ); ?>][point_type]">
                            <option value="">— <?php esc_html_e( 'Select', 'zinckles-net-cart' ); ?> —</option>
                            <?php if ( ! empty( $gami_config ) ) :
                                foreach ( $gami_config as $slug => $cfg ) : ?>
                                    <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $pt, $slug ); ?>><?php echo esc_html( $cfg['label'] ?? $slug ); ?></option>
                                <?php endforeach;
                            endif; ?>
                        </select>
                    </td>
                    <td><input type="checkbox" name="gamipress_hooks[<?php echo esc_attr( $hook_key ); ?>][enabled]" value="1" <?php checked( $en ); ?>></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Point Type Detection -->
        <div class="znc-settings-section">
            <h2><?php esc_html_e( 'Point Type Detection', 'zinckles-net-cart' ); ?></h2>
            <p>
                <button type="button" id="znc-detect-points" class="button button-secondary">
                    <span class="dashicons dashicons-search" style="vertical-align:middle;"></span>
                    <?php esc_html_e( 'Auto-Detect Point Types', 'zinckles-net-cart' ); ?>
                </button>
                <span id="znc-detect-status" class="znc-inline-status"></span>
            </p>
            <div id="znc-detected-points"></div>
            <p class="description"><?php esc_html_e( 'Scans all enrolled subsites for MyCred and GamiPress point types. Detected types appear in the tables above. Save settings after detection to preserve exchange rates.', 'zinckles-net-cart' ); ?></p>
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
