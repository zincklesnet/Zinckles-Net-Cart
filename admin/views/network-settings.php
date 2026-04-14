<?php
/**
 * Network Admin → Net Cart → Settings view.
 * v1.3.0: Added Checkout Host selector + multi-MyCred point types.
 */
defined( 'ABSPATH' ) || exit;

$settings     = get_site_option( 'znc_network_settings', array() );
$checkout_host = new ZNC_Checkout_Host();
$host_info    = $checkout_host->get_host_info();

// Get all sites for the checkout host dropdown.
$all_sites = get_sites( array( 'number' => 200, 'fields' => 'ids' ) );

// Detect MyCred point types across the network.
$point_types = array();
foreach ( $all_sites as $site_id ) {
    switch_to_blog( $site_id );
    if ( function_exists( 'mycred_get_types' ) ) {
        foreach ( mycred_get_types() as $slug => $label ) {
            if ( ! isset( $point_types[ $slug ] ) ) {
                $point_types[ $slug ] = array(
                    'label'  => $label,
                    'slug'   => $slug,
                    'sites'  => array(),
                );
            }
            $point_types[ $slug ]['sites'][] = $site_id;
        }
    }
    restore_current_blog();
}

$zcred_settings = $settings['zcred_types'] ?? array();
?>

<div class="wrap">
<h1>Zinckles Net Cart &mdash; Network Settings</h1>

<form method="post" action="">
<?php wp_nonce_field( 'znc_network_settings', 'znc_nonce' ); ?>

<!-- CHECKOUT HOST -->
<h2 class="znc-section-title">&#x1F3E0; Checkout Host Site</h2>
<p class="description">Choose which site in your network hosts the global cart, checkout, and My Account pages. All other enrolled subsites will redirect their cart/checkout/account pages here.</p>

<table class="form-table">
<tr>
    <th>Checkout Host</th>
    <td>
        <select name="znc_settings[checkout_host_id]" class="regular-text">
            <option value="0" <?php selected( 0, $settings['checkout_host_id'] ?? 0 ); ?>>
                Main Site (<?php echo esc_html( get_blog_details( get_main_site_id() )->blogname ); ?>)
            </option>
            <?php foreach ( $all_sites as $sid ) :
                if ( $sid == get_main_site_id() ) continue;
                $details = get_blog_details( $sid );
                if ( ! $details ) continue;
            ?>
            <option value="<?php echo esc_attr( $sid ); ?>" <?php selected( $sid, $settings['checkout_host_id'] ?? 0 ); ?>>
                <?php echo esc_html( $details->blogname ); ?> (<?php echo esc_html( $details->siteurl ); ?>)
            </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            Current host: <strong><?php echo esc_html( $host_info['name'] ); ?></strong>
            (<?php echo $host_info['is_main'] ? 'Main Site' : 'Subsite #' . $host_info['blog_id']; ?>)
        </p>
    </td>
</tr>
</table>

<!-- ENROLLMENT -->
<h2 class="znc-section-title">&#x1F310; Enrollment</h2>
<table class="form-table">
<tr>
    <th>Enrollment Mode</th>
    <td>
        <select name="znc_settings[enrollment_mode]">
            <option value="opt-in" <?php selected( 'opt-in', $settings['enrollment_mode'] ?? 'opt-in' ); ?>>Opt-In (manual enrollment)</option>
            <option value="opt-out" <?php selected( 'opt-out', $settings['enrollment_mode'] ?? '' ); ?>>Opt-Out (all sites participate unless blocked)</option>
            <option value="manual" <?php selected( 'manual', $settings['enrollment_mode'] ?? '' ); ?>>Manual (network admin only)</option>
        </select>
    </td>
</tr>
<tr>
    <th>Auto-enroll new sites</th>
    <td><label><input type="checkbox" name="znc_settings[auto_enroll]" value="1" <?php checked( 1, $settings['auto_enroll'] ?? 0 ); ?>> Automatically enroll new subsites when created</label></td>
</tr>
</table>

<!-- CART -->
<h2 class="znc-section-title">&#x1F6D2; Cart Settings</h2>
<table class="form-table">
<tr>
    <th>Base Currency</th>
    <td><input type="text" name="znc_settings[base_currency]" value="<?php echo esc_attr( $settings['base_currency'] ?? 'USD' ); ?>" class="small-text"></td>
</tr>
<tr>
    <th>Allow Mixed Currencies</th>
    <td><label><input type="checkbox" name="znc_settings[mixed_currency]" value="1" <?php checked( 1, $settings['mixed_currency'] ?? 1 ); ?>> Allow items from different currencies in one cart</label></td>
</tr>
<tr>
    <th>Cart Expiry (days)</th>
    <td><input type="number" name="znc_settings[cart_expiry_days]" value="<?php echo esc_attr( $settings['cart_expiry_days'] ?? 7 ); ?>" min="1" max="90" class="small-text"></td>
</tr>
<tr>
    <th>Max Items Per Cart</th>
    <td><input type="number" name="znc_settings[max_items]" value="<?php echo esc_attr( $settings['max_items'] ?? 100 ); ?>" min="1" class="small-text"></td>
</tr>
<tr>
    <th>Max Shops Per Cart</th>
    <td><input type="number" name="znc_settings[max_shops]" value="<?php echo esc_attr( $settings['max_shops'] ?? 20 ); ?>" min="1" class="small-text"></td>
</tr>
</table>

<!-- MYCRED / ZCRED POINT TYPES -->
<h2 class="znc-section-title">&#x26A1; MyCred / ZCred Point Types</h2>

<?php if ( empty( $point_types ) ) : ?>
    <p class="description" style="color:#e74c3c">No MyCred point types detected on any enrolled site. Install and configure MyCred on your subsites first.</p>
<?php else : ?>
    <p class="description">Detected <?php echo count( $point_types ); ?> point type(s) across the network. Configure each independently.</p>

    <table class="widefat striped" style="max-width:800px">
    <thead>
        <tr><th>Point Type</th><th>Slug</th><th>Found On</th><th>Enabled</th><th>Exchange Rate</th><th>Max %</th></tr>
    </thead>
    <tbody>
    <?php foreach ( $point_types as $slug => $pt ) :
        $type_settings = $zcred_settings[ $slug ] ?? array();
        $enabled  = $type_settings['enabled'] ?? 1;
        $rate     = $type_settings['exchange_rate'] ?? 100;
        $max_pct  = $type_settings['max_percent'] ?? 50;
    ?>
    <tr>
        <td><strong><?php echo esc_html( $pt['label'] ); ?></strong></td>
        <td><code><?php echo esc_html( $slug ); ?></code></td>
        <td><?php
            $names = array();
            foreach ( $pt['sites'] as $sid ) {
                $d = get_blog_details( $sid );
                $names[] = $d ? $d->blogname : '#' . $sid;
            }
            echo esc_html( implode( ', ', $names ) );
        ?></td>
        <td><input type="checkbox" name="znc_settings[zcred_types][<?php echo esc_attr($slug); ?>][enabled]" value="1" <?php checked( 1, $enabled ); ?>></td>
        <td>
            <input type="number" name="znc_settings[zcred_types][<?php echo esc_attr($slug); ?>][exchange_rate]"
                   value="<?php echo esc_attr( $rate ); ?>" min="1" step="1" class="small-text">
            <span class="description">points = 1 <?php echo esc_html( $settings['base_currency'] ?? 'USD' ); ?></span>
        </td>
        <td>
            <input type="number" name="znc_settings[zcred_types][<?php echo esc_attr($slug); ?>][max_percent]"
                   value="<?php echo esc_attr( $max_pct ); ?>" min="0" max="100" class="small-text"> %
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
<?php endif; ?>

<!-- LOGGING -->
<h2 class="znc-section-title">&#x1F4CB; Logging</h2>
<table class="form-table">
<tr>
    <th>Log Level</th>
    <td>
        <select name="znc_settings[log_level]">
            <option value="none" <?php selected( 'none', $settings['log_level'] ?? 'errors' ); ?>>None</option>
            <option value="errors" <?php selected( 'errors', $settings['log_level'] ?? 'errors' ); ?>>Errors Only</option>
            <option value="all" <?php selected( 'all', $settings['log_level'] ?? '' ); ?>>All Events</option>
        </select>
    </td>
</tr>
<tr>
    <th>Debug Mode</th>
    <td><label><input type="checkbox" name="znc_settings[debug_mode]" value="1" <?php checked( 1, $settings['debug_mode'] ?? 0 ); ?>> Enable verbose logging</label></td>
</tr>
</table>

<?php submit_button( 'Save Network Settings' ); ?>
</form>
</div>

<style>
.znc-section-title{margin-top:30px;padding-top:20px;border-top:1px solid #e5e7eb;font-size:16px}
.znc-section-title:first-of-type{border-top:none;margin-top:10px;padding-top:0}
</style>
