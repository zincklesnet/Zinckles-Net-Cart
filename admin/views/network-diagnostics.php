<?php
defined( 'ABSPATH' ) || exit;

$host       = new ZNC_Checkout_Host();
$host_id    = $host->get_host_id();
$host_info  = $host->get_host_info();
$settings   = get_site_option( 'znc_network_settings', array() );
$enrolled   = (array) ( $settings['enrolled_sites'] ?? array() );
$secret_set = (bool) get_site_option( 'znc_rest_secret', '' );
$db_version = get_site_option( 'znc_db_version', 'unknown' );
$retry_queue = get_site_option( 'znc_inventory_retry_queue', array() );

global $wpdb;
$host_prefix = $wpdb->get_blog_prefix( $host_id );
$cart_table  = $host_prefix . 'znc_global_cart';
$map_table   = $host_prefix . 'znc_order_map';

$cart_exists = (bool) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
    DB_NAME, $cart_table
) );
$map_exists = (bool) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
    DB_NAME, $map_table
) );

$active_carts = $cart_exists ? (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$cart_table} WHERE expires_at > NOW()" ) : 0;
$total_items  = $cart_exists ? (int) $wpdb->get_var( "SELECT COALESCE(SUM(quantity),0) FROM {$cart_table} WHERE expires_at > NOW()" ) : 0;
$total_orders = $map_exists ? (int) $wpdb->get_var( "SELECT COUNT(DISTINCT parent_order_id) FROM {$map_table}" ) : 0;
?>
<div class="wrap">
    <h1>Zinckles Net Cart — Diagnostics</h1>

    <h2>System Status</h2>
    <table class="widefat striped">
        <tbody>
            <tr><th>Plugin Version</th><td><?php echo ZNC_VERSION; ?></td></tr>
            <tr><th>DB Version</th><td><?php echo esc_html( $db_version ); ?></td></tr>
            <tr><th>Checkout Host</th><td><?php echo esc_html( $host_info['name'] ) . ' (ID: ' . $host_id . ')'; ?></td></tr>
            <tr><th>Enrolled Sites</th><td><?php echo count( $enrolled ); ?></td></tr>
            <tr><th>REST Secret</th><td><?php echo $secret_set ? '✅ Set' : '❌ Not set'; ?></td></tr>
            <tr><th>Global Cart Table</th><td><?php echo $cart_exists ? '✅ Exists' : '❌ Missing'; ?></td></tr>
            <tr><th>Order Map Table</th><td><?php echo $map_exists ? '✅ Exists' : '❌ Missing'; ?></td></tr>
            <tr><th>PHP Memory Limit</th><td><?php echo ini_get( 'memory_limit' ); ?></td></tr>
            <tr><th>Current Memory Usage</th><td><?php echo round( memory_get_usage( true ) / 1048576, 1 ); ?> MB</td></tr>
            <tr><th>Peak Memory Usage</th><td><?php echo round( memory_get_peak_usage( true ) / 1048576, 1 ); ?> MB</td></tr>
        </tbody>
    </table>

    <h2>Live Stats</h2>
    <table class="widefat striped">
        <tbody>
            <tr><th>Active Carts</th><td><?php echo $active_carts; ?></td></tr>
            <tr><th>Total Items in Carts</th><td><?php echo $total_items; ?></td></tr>
            <tr><th>Net Cart Orders</th><td><?php echo $total_orders; ?></td></tr>
            <tr><th>Inventory Retry Queue</th><td><?php echo count( $retry_queue ); ?> pending</td></tr>
        </tbody>
    </table>

    <h2>Enrolled Sites Detail</h2>
    <table class="widefat striped">
        <thead><tr><th>Blog ID</th><th>Name</th><th>WooCommerce</th><th>Products</th></tr></thead>
        <tbody>
        <?php
        foreach ( $enrolled as $sid ) {
            $d = get_blog_details( $sid );
            $prefix = $wpdb->get_blog_prefix( $sid );
            $has_wc = (bool) $wpdb->get_var( "SELECT option_value FROM {$prefix}options WHERE option_name = 'woocommerce_version' LIMIT 1" );
            $products = $has_wc ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}posts WHERE post_type = 'product' AND post_status = 'publish'" ) : 0;
            echo '<tr><td>' . $sid . '</td><td>' . esc_html( $d ? $d->blogname : 'Unknown' ) . '</td>';
            echo '<td>' . ( $has_wc ? '✅' : '❌' ) . '</td><td>' . $products . '</td></tr>';
        }
        if ( empty( $enrolled ) ) echo '<tr><td colspan="4">No sites enrolled yet.</td></tr>';
        ?>
        </tbody>
    </table>
</div>
