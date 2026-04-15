<?php
/**
 * Subsite Admin — v1.4.1
 * Admin notice and cart info page on enrolled subsites.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Subsite_Admin {

    public function init() {
        if ( is_main_site() ) return;
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_notices', array( $this, 'enrollment_notice' ) );
    }

    public function add_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Net Cart Status', 'zinckles-net-cart' ),
            __( 'Net Cart Status', 'zinckles-net-cart' ),
            'manage_woocommerce',
            'znc-subsite-status',
            array( $this, 'render_page' )
        );
    }

    public function enrollment_notice() {
        $settings = get_site_option( 'znc_network_settings', array() );
        $enrolled = isset( $settings['enrolled_sites'] ) ? (array) $settings['enrolled_sites'] : array();
        $blog_id  = get_current_blog_id();

        if ( ! in_array( $blog_id, $enrolled ) ) {
            echo '<div class="notice notice-warning"><p>';
            printf(
                esc_html__( 'This site is not enrolled in Zinckles Net Cart. Products will not sync to the global cart. %sEnroll in Network Admin%s', 'zinckles-net-cart' ),
                '<a href="' . esc_url( network_admin_url( 'admin.php?page=znc-network-subsites' ) ) . '">',
                '</a>'
            );
            echo '</p></div>';
        }
    }

    public function render_page() {
        $settings   = get_site_option( 'znc_network_settings', array() );
        $enrolled   = isset( $settings['enrolled_sites'] ) ? (array) $settings['enrolled_sites'] : array();
        $blog_id    = get_current_blog_id();
        $is_enrolled = in_array( $blog_id, $enrolled );
        $host_id    = isset( $settings['checkout_host_id'] ) ? (int) $settings['checkout_host_id'] : get_main_site_id();
        $host_url   = get_blog_option( $host_id, 'siteurl' );
        ?>
        <div class="wrap znc-admin-wrap">
            <h1><span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'Net Cart — Subsite Status', 'zinckles-net-cart' ); ?></h1>

            <div class="znc-info-card">
                <table class="widefat striped">
                    <tr>
                        <td><?php esc_html_e( 'Enrollment Status', 'zinckles-net-cart' ); ?></td>
                        <td>
                            <?php if ( $is_enrolled ) : ?>
                                <span class="znc-badge znc-badge-success"><?php esc_html_e( 'Enrolled', 'zinckles-net-cart' ); ?></span>
                            <?php else : ?>
                                <span class="znc-badge znc-badge-warning"><?php esc_html_e( 'Not Enrolled', 'zinckles-net-cart' ); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'Blog ID', 'zinckles-net-cart' ); ?></td>
                        <td><?php echo esc_html( $blog_id ); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'Checkout Host', 'zinckles-net-cart' ); ?></td>
                        <td><a href="<?php echo esc_url( $host_url ); ?>" target="_blank"><?php echo esc_html( $host_url ); ?></a> (Blog ID <?php echo esc_html( $host_id ); ?>)</td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'WooCommerce', 'zinckles-net-cart' ); ?></td>
                        <td>
                            <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                                <span style="color:#46b450;">✓ Active (<?php echo esc_html( WC()->version ); ?>)</span>
                            <?php else : ?>
                                <span style="color:#dc3232;">✗ Not Active</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'MyCred', 'zinckles-net-cart' ); ?></td>
                        <td>
                            <?php if ( function_exists( 'mycred' ) ) : ?>
                                <span style="color:#46b450;">✓ Active</span>
                            <?php else : ?>
                                <span style="color:#999;">— Not detected</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'GamiPress', 'zinckles-net-cart' ); ?></td>
                        <td>
                            <?php if ( function_exists( 'gamipress' ) || defined( 'GAMIPRESS_VER' ) ) : ?>
                                <span style="color:#46b450;">✓ Active</span>
                            <?php else : ?>
                                <span style="color:#999;">— Not detected</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'Clear Local Cart', 'zinckles-net-cart' ); ?></td>
                        <td><?php echo ! empty( $settings['clear_local_cart'] ) ? '✓ Enabled' : '✗ Disabled'; ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e( 'Plugin Version', 'zinckles-net-cart' ); ?></td>
                        <td><?php echo esc_html( ZNC_VERSION ); ?></td>
                    </tr>
                </table>
            </div>

            <?php if ( $is_enrolled ) : ?>
            <div class="znc-info-card">
                <h2><?php esc_html_e( 'Shortcodes Available', 'zinckles-net-cart' ); ?></h2>
                <p><?php esc_html_e( 'Use these shortcodes on this subsite:', 'zinckles-net-cart' ); ?></p>
                <code>[znc_global_cart]</code> · <code>[znc_cart_count]</code> · <code>[znc_cart_total]</code> · <code>[znc_mini_cart]</code> · <code>[znc_cart_button]</code> · <code>[znc_points_balance]</code> · <code>[znc_shop_list]</code>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
