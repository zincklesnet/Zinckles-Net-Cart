<?php
/**
 * Network Subsites View — v1.3.2
 *
 * FIXES:
 * - Uses AJAX buttons with data-blog-id and data-action (matches JS)
 * - No switch_to_blog() — uses ZNC_Checkout_Host::get_all_sites_for_admin() direct DB
 * - No wp_znc_enrolled_sites table dependency
 * - Enrollment stored in znc_network_settings site option
 */
defined( 'ABSPATH' ) || exit;

$sites   = ZNC_Checkout_Host::get_all_sites_for_admin();
$host    = new ZNC_Checkout_Host();
$host_id = $host->get_host_id();
?>
<div class="wrap znc-wrap">
    <h1><?php esc_html_e( 'Net Cart — Enrolled Subsites', 'zinckles-net-cart' ); ?></h1>
    <p class="description"><?php esc_html_e( 'Manage which subsites participate in Net Cart. Only enrolled sites can push products to the global cart.', 'zinckles-net-cart' ); ?></p>

    <table class="wp-list-table widefat fixed striped znc-sites-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Site', 'zinckles-net-cart' ); ?></th>
                <th><?php esc_html_e( 'URL', 'zinckles-net-cart' ); ?></th>
                <th><?php esc_html_e( 'WooCommerce', 'zinckles-net-cart' ); ?></th>
                <th><?php esc_html_e( 'MyCred', 'zinckles-net-cart' ); ?></th>
                <th><?php esc_html_e( 'Products', 'zinckles-net-cart' ); ?></th>
                <th><?php esc_html_e( 'Status', 'zinckles-net-cart' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'zinckles-net-cart' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $sites as $site ) :
                $is_host = $site['is_host'];
            ?>
            <tr id="znc-site-row-<?php echo esc_attr( $site['blog_id'] ); ?>">
                <td>
                    <strong><?php echo esc_html( $site['blogname'] ); ?></strong>
                    <?php if ( $is_host ) : ?>
                        <span class="znc-badge znc-badge-purple" title="This site hosts the global cart and checkout">Checkout Host</span>
                    <?php endif; ?>
                </td>
                <td><a href="<?php echo esc_url( $site['siteurl'] ); ?>" target="_blank"><?php echo esc_html( $site['siteurl'] ); ?></a></td>
                <td>
                    <?php if ( $site['has_wc'] ) : ?>
                        <span class="znc-badge znc-badge-green">Active</span>
                    <?php else : ?>
                        <span class="znc-badge znc-badge-red">Missing</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ( $site['has_mycred'] ) : ?>
                        <span class="znc-badge znc-badge-green">Active</span>
                    <?php else : ?>
                        <span class="znc-badge znc-badge-gray">N/A</span>
                    <?php endif; ?>
                </td>
                <td><?php echo esc_html( $site['product_count'] ); ?></td>
                <td id="znc-status-<?php echo esc_attr( $site['blog_id'] ); ?>">
                    <?php if ( $is_host ) : ?>
                        <span class="znc-badge znc-badge-purple">Host</span>
                    <?php elseif ( $site['is_enrolled'] ) : ?>
                        <span class="znc-badge znc-badge-green">Enrolled</span>
                    <?php else : ?>
                        <span class="znc-badge znc-badge-gray">Not Enrolled</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ( ! $is_host ) : ?>
                        <?php if ( $site['is_enrolled'] ) : ?>
                            <button type="button"
                                class="button button-small znc-enroll-btn"
                                data-blog-id="<?php echo esc_attr( $site['blog_id'] ); ?>"
                                data-action="remove"
                                <?php echo ! $site['has_wc'] ? 'disabled' : ''; ?>>
                                <?php esc_html_e( 'Remove', 'zinckles-net-cart' ); ?>
                            </button>
                        <?php else : ?>
                            <button type="button"
                                class="button button-primary button-small znc-enroll-btn"
                                data-blog-id="<?php echo esc_attr( $site['blog_id'] ); ?>"
                                data-action="enroll"
                                <?php echo ! $site['has_wc'] ? 'disabled' : ''; ?>>
                                <?php esc_html_e( 'Enroll', 'zinckles-net-cart' ); ?>
                            </button>
                        <?php endif; ?>
                        <button type="button"
                            class="button button-small znc-test-btn"
                            data-blog-id="<?php echo esc_attr( $site['blog_id'] ); ?>">
                            <?php esc_html_e( 'Test', 'zinckles-net-cart' ); ?>
                        </button>
                    <?php else : ?>
                        <em><?php esc_html_e( 'Checkout Host', 'zinckles-net-cart' ); ?></em>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
