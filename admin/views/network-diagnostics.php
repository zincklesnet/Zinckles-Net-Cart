<?php
defined( 'ABSPATH' ) || exit;
$host     = new ZNC_Checkout_Host();
$host_id  = $host->get_host_id();
$enrolled = $host->get_enrolled_ids();
$host_info = $host->get_host_info();

global $wpdb;
$host_prefix = $wpdb->get_blog_prefix( $host_id );
$table = $host_prefix . 'znc_global_cart';
$table_exists = (bool) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
    DB_NAME, $table
) );
$cart_count = $table_exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) : 0;
$cart_users = $table_exists ? (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$table}" ) : 0;
?>
<div class="wrap znc-wrap">
    <h1><?php esc_html_e( 'Net Cart — Diagnostics', 'zinckles-net-cart' ); ?></h1>

    <div class="znc-diag-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:20px;">
        <div class="znc-diag-card card">
            <h3>System Info</h3>
            <table class="widefat">
                <tr><td>Plugin Version</td><td><strong><?php echo ZNC_VERSION; ?></strong></td></tr>
                <tr><td>DB Version</td><td><?php echo get_site_option( 'znc_db_version', 'N/A' ); ?></td></tr>
                <tr><td>WordPress</td><td><?php echo get_bloginfo( 'version' ); ?></td></tr>
                <tr><td>PHP</td><td><?php echo phpversion(); ?></td></tr>
                <tr><td>Memory Limit</td><td><?php echo ini_get( 'memory_limit' ); ?></td></tr>
                <tr><td>Memory Usage</td><td><?php echo round( memory_get_usage( true ) / 1048576, 1 ); ?> MB</td></tr>
                <tr><td>Peak Memory</td><td><?php echo round( memory_get_peak_usage( true ) / 1048576, 1 ); ?> MB</td></tr>
            </table>
        </div>
        <div class="znc-diag-card card">
            <h3>Network Status</h3>
            <table class="widefat">
                <tr><td>Checkout Host</td><td><strong><?php echo esc_html( $host_info['name'] ); ?></strong> (ID: <?php echo $host_id; ?>)</td></tr>
                <tr><td>Enrolled Sites</td><td><?php echo count( $enrolled ); ?></td></tr>
                <tr><td>Cart Table</td><td><?php echo $table_exists ? '<span class="znc-badge znc-badge-green">Exists</span>' : '<span class="znc-badge znc-badge-red">Missing</span>'; ?></td></tr>
                <tr><td>Active Carts</td><td><?php echo $cart_users; ?> users</td></tr>
                <tr><td>Total Cart Items</td><td><?php echo $cart_count; ?></td></tr>
                <tr><td>REST Secret</td><td><?php echo get_site_option( 'znc_rest_secret' ) ? '<span class="znc-badge znc-badge-green">Set</span>' : '<span class="znc-badge znc-badge-red">Missing</span>'; ?></td></tr>
            </table>
        </div>
    </div>

    <?php if ( ! $table_exists ) : ?>
        <div class="notice notice-error" style="margin-top:20px;">
            <p><strong>Global cart table is missing.</strong> Try deactivating and reactivating Zinckles Net Cart from Network Admin → Plugins.</p>
        </div>
    <?php endif; ?>
</div>
