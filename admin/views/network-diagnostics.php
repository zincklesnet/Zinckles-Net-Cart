<?php
/**
 * Network Diagnostics View — v1.4.1
 * System info, global cart stats, health checks.
 */
defined( 'ABSPATH' ) || exit;

global $wpdb;
$settings = get_site_option( 'znc_network_settings', array() );
$host_id  = isset( $settings['checkout_host_id'] ) ? (int) $settings['checkout_host_id'] : get_main_site_id();
$prefix   = $wpdb->get_blog_prefix( $host_id );
$table    = $prefix . 'znc_global_cart';
$table_exists = (bool) $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );

$cart_items  = 0;
$cart_users  = 0;
$cart_value  = 0;
$oldest_item = '—';
if ( $table_exists ) {
    $cart_items  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    $cart_users  = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$table}" );
    $cart_value  = (float) $wpdb->get_var( "SELECT COALESCE(SUM(line_total),0) FROM {$table}" );
    $oldest_item = $wpdb->get_var( "SELECT MIN(created_at) FROM {$table}" ) ?: '—';
}

$enrolled = isset( $settings['enrolled_sites'] ) ? (array) $settings['enrolled_sites'] : array();
?>
<div class="wrap znc-admin-wrap">
    <h1><span class="dashicons dashicons-heart"></span> <?php esc_html_e( 'Net Cart — Diagnostics', 'zinckles-net-cart' ); ?></h1>

    <!-- Health Checks -->
    <div class="znc-settings-section">
        <h2><?php esc_html_e( 'Health Checks', 'zinckles-net-cart' ); ?></h2>
        <table class="widefat striped">
            <tr>
                <td width="300"><?php esc_html_e( 'Global Cart Table', 'zinckles-net-cart' ); ?></td>
                <td><?php echo $table_exists ? '<span style="color:#46b450;">✓ ' . esc_html( $table ) . '</span>' : '<span style="color:#dc3232;">✗ Table missing: ' . esc_html( $table ) . '</span>'; ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e( 'Checkout Host', 'zinckles-net-cart' ); ?></td>
                <td>Blog ID <?php echo esc_html( $host_id ); ?> — <?php echo esc_html( get_blog_option( $host_id, 'blogname' ) ); ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e( 'Enrolled Sites', 'zinckles-net-cart' ); ?></td>
                <td><?php echo count( $enrolled ); ?> site(s): <?php echo esc_html( implode( ', ', $enrolled ) ); ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e( 'HMAC Secret', 'zinckles-net-cart' ); ?></td>
                <td><?php echo ! empty( $settings['hmac_secret'] ) ? '<span style="color:#46b450;">✓ Configured</span>' : '<span style="color:#f0ad4e;">⚠ Not set</span>'; ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e( 'MyCred Types', 'zinckles-net-cart' ); ?></td>
                <td><?php echo ! empty( $settings['mycred_types_config'] ) ? count( $settings['mycred_types_config'] ) . ' type(s) configured' : '<span style="color:#999;">None detected</span>'; ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e( 'GamiPress Types', 'zinckles-net-cart' ); ?></td>
                <td><?php echo ! empty( $settings['gamipress_types_config'] ) ? count( $settings['gamipress_types_config'] ) . ' type(s) configured' : '<span style="color:#999;">None detected</span>'; ?></td>
            </tr>
        </table>
    </div>

    <!-- Global Cart Stats -->
    <div class="znc-settings-section">
        <h2><?php esc_html_e( 'Global Cart Statistics', 'zinckles-net-cart' ); ?></h2>
        <div class="znc-stats-grid">
            <div class="znc-stat-card">
                <span class="znc-stat-value"><?php echo esc_html( $cart_items ); ?></span>
                <span class="znc-stat-label"><?php esc_html_e( 'Total Items', 'zinckles-net-cart' ); ?></span>
            </div>
            <div class="znc-stat-card">
                <span class="znc-stat-value"><?php echo esc_html( $cart_users ); ?></span>
                <span class="znc-stat-label"><?php esc_html_e( 'Active Carts', 'zinckles-net-cart' ); ?></span>
            </div>
            <div class="znc-stat-card">
                <span class="znc-stat-value"><?php echo esc_html( ZNC_Currency_Handler::format( $cart_value, $settings['base_currency'] ?? 'USD' ) ); ?></span>
                <span class="znc-stat-label"><?php esc_html_e( 'Total Value', 'zinckles-net-cart' ); ?></span>
            </div>
        </div>
        <table class="widefat striped" style="margin-top:16px;">
            <tr>
                <td><?php esc_html_e( 'Oldest Cart Item', 'zinckles-net-cart' ); ?></td>
                <td><?php echo esc_html( $oldest_item ); ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e( 'Cart Expiry Setting', 'zinckles-net-cart' ); ?></td>
                <td><?php echo esc_html( $settings['cart_expiry_days'] ?? 7 ); ?> days</td>
            </tr>
        </table>
    </div>

    <!-- System Info -->
    <div class="znc-settings-section">
        <h2><?php esc_html_e( 'System Information', 'zinckles-net-cart' ); ?></h2>
        <table class="widefat striped">
            <tr><td><?php esc_html_e( 'Plugin Version', 'zinckles-net-cart' ); ?></td><td><?php echo esc_html( ZNC_VERSION ); ?></td></tr>
            <tr><td><?php esc_html_e( 'WordPress', 'zinckles-net-cart' ); ?></td><td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
            <tr><td><?php esc_html_e( 'PHP', 'zinckles-net-cart' ); ?></td><td><?php echo esc_html( phpversion() ); ?></td></tr>
            <tr><td><?php esc_html_e( 'MySQL / MariaDB', 'zinckles-net-cart' ); ?></td><td><?php echo esc_html( $wpdb->db_version() ); ?></td></tr>
            <tr><td><?php esc_html_e( 'Multisite', 'zinckles-net-cart' ); ?></td><td><?php echo is_multisite() ? '✓ Yes' : '✗ No'; ?></td></tr>
            <tr><td><?php esc_html_e( 'Network Sites', 'zinckles-net-cart' ); ?></td><td><?php echo esc_html( get_blog_count() ); ?></td></tr>
            <tr><td><?php esc_html_e( 'PHP Memory Limit', 'zinckles-net-cart' ); ?></td><td><?php echo esc_html( ini_get( 'memory_limit' ) ); ?></td></tr>
            <tr><td><?php esc_html_e( 'Max Execution Time', 'zinckles-net-cart' ); ?></td><td><?php echo esc_html( ini_get( 'max_execution_time' ) ); ?>s</td></tr>
            <tr><td><?php esc_html_e( 'WP Debug', 'zinckles-net-cart' ); ?></td><td><?php echo defined( 'WP_DEBUG' ) && WP_DEBUG ? '✓ On' : '✗ Off'; ?></td></tr>
        </table>
    </div>

    <!-- Debug Log -->
    <div class="znc-settings-section">
        <h2><?php esc_html_e( 'Debug Settings', 'zinckles-net-cart' ); ?></h2>
        <p>
            <?php esc_html_e( 'Net Cart Debug Mode:', 'zinckles-net-cart' ); ?>
            <strong><?php echo ! empty( $settings['debug_mode'] ) ? '✓ Enabled' : '✗ Disabled'; ?></strong>
        </p>
        <p class="description"><?php esc_html_e( 'When enabled, Net Cart writes detailed logs to the WordPress debug.log file. Enable this in Network Settings.', 'zinckles-net-cart' ); ?></p>
    </div>
</div>
