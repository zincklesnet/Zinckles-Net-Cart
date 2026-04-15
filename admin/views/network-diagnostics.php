<?php
/**
 * Network Diagnostics — v1.7.0 FIXED
 *
 * FIX: $cart->get_items() → $cart->get_cart() — get_items() never existed.
 * FIX: Plugin detection uses get_option() via switch_to_blog() instead of fragile strpos() on serialized.
 *
 * @package ZincklesNetCart
 * @since   1.7.0
 */
defined( 'ABSPATH' ) || exit;

$settings  = get_site_option( 'znc_network_settings', array() );
$security  = get_site_option( 'znc_security_settings', array() );
$enrolled  = (array) ( $settings['enrolled_sites'] ?? array() );
$host_id   = absint( $settings['checkout_host_id'] ?? get_main_site_id() );
$mc_config = (array) ( $settings['mycred_types_config'] ?? array() );
$gp_config = (array) ( $settings['gamipress_types_config'] ?? array() );
$mc_hooks  = (array) ( $settings['mycred_hooks'] ?? array() );
$gp_hooks  = (array) ( $settings['gamipress_hooks'] ?? array() );

function znc_check( $ok, $label, $detail = '' ) {
    $icon = $ok
        ? '<span style="color:#46b450;">&#10003;</span>'
        : '<span style="color:#dc3232;">&#10007;</span>';
    echo '<tr><td>' . $icon . '</td><td><strong>' . esc_html( $label ) . '</strong></td><td>' . esc_html( $detail ) . '</td></tr>';
}
?>
<div class="wrap znc-admin-wrap">
<h1><span class="dashicons dashicons-heart"></span> <?php esc_html_e( 'Net Cart — Diagnostics', 'zinckles-net-cart' ); ?></h1>

<!-- ── Health Checks ─────────────────────────────────── -->
<div class="znc-settings-section">
<h2><?php esc_html_e( 'Health Checks', 'zinckles-net-cart' ); ?></h2>
<table class="widefat striped">
<thead><tr><th style="width:30px;"></th><th style="width:250px;"><?php esc_html_e( 'Check', 'zinckles-net-cart' ); ?></th><th><?php esc_html_e( 'Details', 'zinckles-net-cart' ); ?></th></tr></thead>
<tbody>
<?php
    znc_check( ! empty( $enrolled ), 'Enrolled Sites', count( $enrolled ) . ' site(s) enrolled' );
    znc_check( $host_id > 0, 'Checkout Host', 'Blog ID: ' . $host_id );

    $host_details = get_blog_details( $host_id );
    znc_check( (bool) $host_details, 'Checkout Host Exists', $host_details ? $host_details->blogname : 'NOT FOUND' );

    $has_hmac = ! empty( $security['hmac_secret'] );
    znc_check( $has_hmac, 'HMAC Secret', $has_hmac ? 'Generated at: ' . ( $security['hmac_generated_at'] ?? 'Unknown' ) : 'Not generated — go to Security page' );

    $mc_count = count( $mc_config );
    znc_check( $mc_count > 0, 'MyCred Point Types', $mc_count > 0 ? $mc_count . ' type(s): ' . implode( ', ', array_keys( $mc_config ) ) : 'None detected — click Auto-Detect in Settings' );

    $gp_count = count( $gp_config );
    znc_check( $gp_count > 0, 'GamiPress Point Types', $gp_count > 0 ? $gp_count . ' type(s): ' . implode( ', ', array_keys( $gp_config ) ) : 'None detected — click Auto-Detect in Settings' );

    $mc_hooks_on = array_filter( $mc_hooks, function( $h ) { return ! empty( $h['enabled'] ); } );
    znc_check( count( $mc_hooks_on ) > 0, 'MyCred Hooks', count( $mc_hooks_on ) . ' active hook(s)' );

    $gp_hooks_on = array_filter( $gp_hooks, function( $h ) { return ! empty( $h['enabled'] ); } );
    znc_check( count( $gp_hooks_on ) > 0, 'GamiPress Hooks', count( $gp_hooks_on ) . ' active hook(s)' );

    $tutor_sites = (array) ( $settings['tutor_sites'] ?? array() );
    znc_check( count( $tutor_sites ) > 0, 'Tutor LMS Sites', count( $tutor_sites ) > 0 ? count( $tutor_sites ) . ' site(s)' : 'None detected' );

    $host = new ZNC_Checkout_Host();
    $cart_url     = $host->get_cart_url();
    $checkout_url = $host->get_checkout_url();
    znc_check( ! empty( $cart_url ), 'Cart Page URL', $cart_url ?: 'NOT SET' );
    znc_check( ! empty( $checkout_url ), 'Checkout Page URL', $checkout_url ?: 'NOT SET' );

    $debug = ! empty( $settings['debug_mode'] );
    znc_check( true, 'Debug Mode', $debug ? 'Enabled' : 'Disabled' );
    znc_check( true, 'Plugin Version', defined( 'ZNC_VERSION' ) ? ZNC_VERSION : 'Unknown' );
    znc_check( true, 'PHP Version', PHP_VERSION );
    znc_check( true, 'WordPress', get_bloginfo( 'version' ) );
    znc_check( class_exists( 'WooCommerce' ), 'WooCommerce', class_exists( 'WooCommerce' ) ? WC()->version : 'Not active on this site' );
    znc_check( function_exists( 'mycred' ), 'MyCred', function_exists( 'mycred' ) ? 'Active' : 'Inactive' );
    znc_check( function_exists( 'GamiPress' ), 'GamiPress', function_exists( 'GamiPress' ) ? 'Active' : 'Inactive' );

    $mem_limit = @ini_get( 'memory_limit' );
    $mem_used  = round( memory_get_peak_usage( true ) / 1048576, 1 );
    znc_check( true, 'Memory', 'Limit: ' . $mem_limit . ' | Peak: ' . $mem_used . ' MB' );
?>
</tbody>
</table>
</div>

<!-- ── Cart Statistics ── FIXED: was $cart->get_items() which doesn't exist ── -->
<div class="znc-settings-section">
<h2><?php esc_html_e( 'Cart Statistics', 'zinckles-net-cart' ); ?></h2>
<?php if ( is_user_logged_in() ) :
    $cart  = new ZNC_Global_Cart();
    $count = $cart->get_item_count();
    // FIX: get_items() does NOT exist — use get_cart()
    $items = $cart->get_cart();
    $blogs = array();
    foreach ( $items as $item ) {
        $bid = $item['blog_id'] ?? 0;
        if ( ! isset( $blogs[ $bid ] ) ) $blogs[ $bid ] = 0;
        $blogs[ $bid ]++;
    }
?>
<table class="widefat striped">
<tbody>
    <tr><td><strong><?php esc_html_e( 'Your Cart Items', 'zinckles-net-cart' ); ?></strong></td><td><?php echo esc_html( $count ); ?></td></tr>
    <tr><td><strong><?php esc_html_e( 'Shops in Cart', 'zinckles-net-cart' ); ?></strong></td><td><?php echo esc_html( count( $blogs ) ); ?></td></tr>
    <?php foreach ( $blogs as $bid => $cnt ) :
        $d = get_blog_details( $bid );
    ?>
    <tr><td>&nbsp;&nbsp;<?php echo esc_html( $d ? $d->blogname : "Blog $bid" ); ?></td><td><?php echo esc_html( $cnt ); ?> item(s)</td></tr>
    <?php endforeach; ?>
</tbody>
</table>
<?php else : ?>
    <p><?php esc_html_e( 'Log in to see cart statistics.', 'zinckles-net-cart' ); ?></p>
<?php endif; ?>
</div>

<!-- ── Enrolled Sites Detail ── FIXED: use switch_to_blog + get_option instead of fragile strpos ── -->
<div class="znc-settings-section">
<h2><?php esc_html_e( 'Enrolled Sites Detail', 'zinckles-net-cart' ); ?></h2>
<table class="widefat striped">
<thead><tr><th>ID</th><th>Site</th><th>WC</th><th>MyCred</th><th>GamiPress</th><th>Tutor</th><th>Currency</th></tr></thead>
<tbody>
<?php
foreach ( $enrolled as $bid ) :
    $bid = absint( $bid );
    $d   = get_blog_details( $bid );
    if ( ! $d ) continue;

    // Safe plugin detection via switch_to_blog
    switch_to_blog( $bid );
    $active = (array) get_option( 'active_plugins', array() );
    $active_str = implode( '|', $active );
    $wc = (bool) preg_match( '/woocommerce/i', $active_str );
    $mc = (bool) preg_match( '/mycred/i', $active_str );
    $gp = (bool) preg_match( '/gamipress/i', $active_str );
    $tu = (bool) preg_match( '/tutor/i', $active_str );

    // Get currency if WC is active
    $currency = $wc && function_exists( 'get_woocommerce_currency' )
        ? get_woocommerce_currency()
        : '—';
    restore_current_blog();
?>
    <tr>
        <td><?php echo esc_html( $bid ); ?></td>
        <td><?php echo esc_html( $d->blogname ); ?></td>
        <td><?php echo $wc ? '&#10003;' : '&#10007;'; ?></td>
        <td><?php echo $mc ? '&#10003;' : '&#10007;'; ?></td>
        <td><?php echo $gp ? '&#10003;' : '&#10007;'; ?></td>
        <td><?php echo $tu ? '&#10003;' : '&#10007;'; ?></td>
        <td><?php echo esc_html( $currency ); ?></td>
    </tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<!-- ── System Info ── -->
<div class="znc-settings-section">
<h2><?php esc_html_e( 'System Info', 'zinckles-net-cart' ); ?></h2>
<textarea readonly rows="14" style="width:100%;font-family:monospace;font-size:12px;"><?php
echo "Zinckles Net Cart: " . ( defined( 'ZNC_VERSION' ) ? ZNC_VERSION : '?' ) . "\n";
echo "WordPress: " . get_bloginfo( 'version' ) . "\n";
echo "PHP: " . PHP_VERSION . "\n";
echo "WooCommerce: " . ( class_exists( 'WooCommerce' ) ? WC()->version : 'N/A' ) . "\n";
echo "MyCred: " . ( function_exists( 'mycred' ) ? 'Active' : 'Inactive' ) . "\n";
echo "GamiPress: " . ( function_exists( 'GamiPress' ) ? 'Active' : 'Inactive' ) . "\n";
echo "Tutor LMS: " . ( function_exists( 'tutor' ) ? 'Active' : 'Inactive' ) . "\n";
echo "Network Sites: " . get_blog_count() . "\n";
echo "Enrolled: " . count( $enrolled ) . "\n";
echo "Checkout Host: " . $host_id . "\n";
echo "Memory Limit: " . @ini_get( 'memory_limit' ) . "\n";
echo "Peak Memory: " . round( memory_get_peak_usage( true ) / 1048576, 1 ) . " MB\n";
echo "Server: " . ( $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ) . "\n";
echo "OS: " . PHP_OS . "\n";
?></textarea>
</div>
</div>
