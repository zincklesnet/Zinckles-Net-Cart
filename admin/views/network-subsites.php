<?php
/**
 * Network Subsites View — v1.4.1
 * Enrollment management with AJAX enroll/remove.
 */
defined( 'ABSPATH' ) || exit;

$settings = get_site_option( 'znc_network_settings', array() );
$enrolled = isset( $settings['enrolled_sites'] ) ? (array) $settings['enrolled_sites'] : array();
$blocked  = isset( $settings['blocked_sites'] ) ? (array) $settings['blocked_sites'] : array();
$host_id  = isset( $settings['checkout_host_id'] ) ? (int) $settings['checkout_host_id'] : get_main_site_id();

$sites = get_sites( array( 'number' => 100 ) );
?>
<div class="wrap znc-admin-wrap">
    <h1><span class="dashicons dashicons-networking"></span> <?php esc_html_e( 'Net Cart — Enrolled Subsites', 'zinckles-net-cart' ); ?></h1>

    <p class="description"><?php esc_html_e( 'Manage which subsites participate in the global cart. Only sites with WooCommerce active can be enrolled.', 'zinckles-net-cart' ); ?></p>

    <table class="widefat striped znc-sites-table" id="znc-sites-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Blog ID', 'zinckles-net-cart' ); ?></th>
                <th><?php esc_html_e( 'Site Name', 'zinckles-net-cart' ); ?></th>
                <th><?php esc_html_e( 'URL', 'zinckles-net-cart' ); ?></th>
                <th><?php esc_html_e( 'WooCommerce', 'zinckles-net-cart' ); ?></th>
                <th><?php esc_html_e( 'MyCred', 'zinckles-net-cart' ); ?></th>
                <th><?php esc_html_e( 'GamiPress', 'zinckles-net-cart' ); ?></th>
                <th><?php esc_html_e( 'Status', 'zinckles-net-cart' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'zinckles-net-cart' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $sites as $site ) :
                $bid  = (int) $site->blog_id;
                $name = $site->blogname ?: $site->domain . $site->path;
                $url  = $site->domain . $site->path;
                $is_enrolled = in_array( $bid, $enrolled );
                $is_blocked  = in_array( $bid, $blocked );
                $is_host     = ( $bid === $host_id );

                // Check WooCommerce
                switch_to_blog( $bid );
                $has_wc   = class_exists( 'WooCommerce' ) || in_array( 'woocommerce/woocommerce.php', (array) get_option( 'active_plugins', array() ) );
                $has_mycred = function_exists( 'mycred' ) || in_array( 'mycred/mycred.php', (array) get_option( 'active_plugins', array() ) );
                $has_gami  = function_exists( 'gamipress' ) || defined( 'GAMIPRESS_VER' ) || in_array( 'gamipress/gamipress.php', (array) get_option( 'active_plugins', array() ) );
                restore_current_blog();
            ?>
            <tr id="znc-site-row-<?php echo esc_attr( $bid ); ?>" class="<?php echo $is_enrolled ? 'znc-enrolled' : ''; ?>">
                <td><?php echo esc_html( $bid ); ?></td>
                <td>
                    <strong><?php echo esc_html( $name ); ?></strong>
                    <?php if ( $is_host ) : ?>
                        <span class="znc-badge znc-badge-primary"><?php esc_html_e( 'Checkout Host', 'zinckles-net-cart' ); ?></span>
                    <?php endif; ?>
                </td>
                <td><a href="<?php echo esc_url( 'https://' . $url ); ?>" target="_blank"><?php echo esc_html( $url ); ?></a></td>
                <td>
                    <?php if ( $has_wc ) : ?>
                        <span class="znc-badge znc-badge-success">✓</span>
                    <?php else : ?>
                        <span class="znc-badge znc-badge-danger">✗</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ( $has_mycred ) : ?>
                        <span class="znc-badge znc-badge-success">✓</span>
                    <?php else : ?>
                        <span class="znc-badge znc-badge-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ( $has_gami ) : ?>
                        <span class="znc-badge znc-badge-success">✓</span>
                    <?php else : ?>
                        <span class="znc-badge znc-badge-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="znc-enrollment-status" id="znc-status-<?php echo esc_attr( $bid ); ?>">
                        <?php if ( $is_enrolled ) : ?>
                            <span class="znc-badge znc-badge-success"><?php esc_html_e( 'Enrolled', 'zinckles-net-cart' ); ?></span>
                        <?php elseif ( $is_blocked ) : ?>
                            <span class="znc-badge znc-badge-danger"><?php esc_html_e( 'Blocked', 'zinckles-net-cart' ); ?></span>
                        <?php elseif ( ! $has_wc ) : ?>
                            <span class="znc-badge znc-badge-warning"><?php esc_html_e( 'No WooCommerce', 'zinckles-net-cart' ); ?></span>
                        <?php else : ?>
                            <span class="znc-badge znc-badge-muted"><?php esc_html_e( 'Not Enrolled', 'zinckles-net-cart' ); ?></span>
                        <?php endif; ?>
                    </span>
                </td>
                <td>
                    <?php if ( $has_wc ) : ?>
                        <?php if ( $is_enrolled ) : ?>
                            <button type="button" class="button button-small znc-enrollment-btn znc-btn-remove"
                                data-blog-id="<?php echo esc_attr( $bid ); ?>"
                                data-action="remove">
                                <?php esc_html_e( 'Remove', 'zinckles-net-cart' ); ?>
                            </button>
                        <?php else : ?>
                            <button type="button" class="button button-small button-primary znc-enrollment-btn znc-btn-enroll"
                                data-blog-id="<?php echo esc_attr( $bid ); ?>"
                                data-action="enroll">
                                <?php esc_html_e( 'Enroll', 'zinckles-net-cart' ); ?>
                            </button>
                        <?php endif; ?>
                        <button type="button" class="button button-small znc-test-btn"
                            data-blog-id="<?php echo esc_attr( $bid ); ?>">
                            <?php esc_html_e( 'Test', 'zinckles-net-cart' ); ?>
                        </button>
                    <?php else : ?>
                        <span class="description"><?php esc_html_e( 'WooCommerce required', 'zinckles-net-cart' ); ?></span>
                    <?php endif; ?>
                    <span class="znc-action-status" id="znc-action-<?php echo esc_attr( $bid ); ?>"></span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
