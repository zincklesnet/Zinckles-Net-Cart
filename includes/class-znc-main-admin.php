<?php
/**
 * Main Admin — v1.4.1
 * Admin page on the main/checkout-host site showing global cart stats.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Main_Admin {

    public function init() {
        if ( ! is_main_site() ) return;
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
    }

    public function add_menu() {
        add_menu_page(
            __( 'Zinckles Net Cart', 'zinckles-net-cart' ),
            __( 'Net Cart', 'zinckles-net-cart' ),
            'manage_woocommerce',
            'znc-main-admin',
            array( $this, 'render_page' ),
            'dashicons-cart',
            58
        );
    }

    public function render_page() {
        global $wpdb;
        $settings = get_site_option( 'znc_network_settings', array() );
        $host_id  = isset( $settings['checkout_host_id'] ) ? (int) $settings['checkout_host_id'] : get_main_site_id();
        $prefix   = $wpdb->get_blog_prefix( $host_id );
        $table    = $prefix . 'znc_global_cart';

        $total_items = 0;
        $total_users = 0;
        $total_value = 0;
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );

        if ( $table_exists ) {
            $total_items = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
            $total_users = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$table}" );
            $total_value = (float) $wpdb->get_var( "SELECT COALESCE(SUM(line_total),0) FROM {$table}" );
        }

        $enrolled = isset( $settings['enrolled_sites'] ) ? (array) $settings['enrolled_sites'] : array();
        ?>
        <div class="wrap znc-admin-wrap">
            <h1><span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'Zinckles Net Cart — Dashboard', 'zinckles-net-cart' ); ?></h1>
            <div class="znc-stats-grid">
                <div class="znc-stat-card">
                    <span class="znc-stat-value"><?php echo esc_html( $total_items ); ?></span>
                    <span class="znc-stat-label"><?php esc_html_e( 'Cart Items', 'zinckles-net-cart' ); ?></span>
                </div>
                <div class="znc-stat-card">
                    <span class="znc-stat-value"><?php echo esc_html( $total_users ); ?></span>
                    <span class="znc-stat-label"><?php esc_html_e( 'Active Carts', 'zinckles-net-cart' ); ?></span>
                </div>
                <div class="znc-stat-card">
                    <span class="znc-stat-value"><?php echo esc_html( ZNC_Currency_Handler::format( $total_value ) ); ?></span>
                    <span class="znc-stat-label"><?php esc_html_e( 'Total Value', 'zinckles-net-cart' ); ?></span>
                </div>
                <div class="znc-stat-card">
                    <span class="znc-stat-value"><?php echo count( $enrolled ); ?></span>
                    <span class="znc-stat-label"><?php esc_html_e( 'Enrolled Sites', 'zinckles-net-cart' ); ?></span>
                </div>
            </div>

            <div class="znc-info-card">
                <h2><?php esc_html_e( 'Quick Links', 'zinckles-net-cart' ); ?></h2>
                <ul>
                    <li><a href="<?php echo esc_url( network_admin_url( 'admin.php?page=zinckles-net-cart' ) ); ?>"><?php esc_html_e( 'Network Settings', 'zinckles-net-cart' ); ?></a></li>
                    <li><a href="<?php echo esc_url( network_admin_url( 'admin.php?page=znc-network-subsites' ) ); ?>"><?php esc_html_e( 'Enrolled Subsites', 'zinckles-net-cart' ); ?></a></li>
                    <li><a href="<?php echo esc_url( network_admin_url( 'admin.php?page=znc-network-security' ) ); ?>"><?php esc_html_e( 'Security Settings', 'zinckles-net-cart' ); ?></a></li>
                    <li><a href="<?php echo esc_url( network_admin_url( 'admin.php?page=znc-network-diagnostics' ) ); ?>"><?php esc_html_e( 'Diagnostics', 'zinckles-net-cart' ); ?></a></li>
                </ul>
            </div>

            <div class="znc-info-card">
                <h2><?php esc_html_e( 'Version Info', 'zinckles-net-cart' ); ?></h2>
                <table class="widefat striped">
                    <tr><td><?php esc_html_e( 'Plugin Version', 'zinckles-net-cart' ); ?></td><td><strong><?php echo esc_html( ZNC_VERSION ); ?></strong></td></tr>
                    <tr><td><?php esc_html_e( 'Checkout Host', 'zinckles-net-cart' ); ?></td><td>Blog ID <?php echo esc_html( $host_id ); ?></td></tr>
                    <tr><td><?php esc_html_e( 'DB Table', 'zinckles-net-cart' ); ?></td><td><?php echo $table_exists ? '<span style="color:#46b450;">✓ Exists</span>' : '<span style="color:#dc3232;">✗ Missing</span>'; ?></td></tr>
                    <tr><td><?php esc_html_e( 'Base Currency', 'zinckles-net-cart' ); ?></td><td><?php echo esc_html( $settings['base_currency'] ?? 'USD' ); ?></td></tr>
                </table>
            </div>
        </div>
        <?php
    }
}
