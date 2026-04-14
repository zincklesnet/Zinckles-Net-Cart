<?php
defined( 'ABSPATH' ) || exit;
$sites = get_sites( array( 'number' => 0 ) );
$main  = get_main_site_id();
global $wpdb;
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
                if ( intval( $site->blog_id ) === $main ) continue;
                switch_to_blog( $site->blog_id );
                $has_wc    = class_exists( 'WooCommerce' );
                $has_mycred = function_exists( 'mycred' );
                $products  = $has_wc ? count( wc_get_products( array( 'limit' => -1, 'return' => 'ids', 'status' => 'publish' ) ) ) : 0;
                restore_current_blog();

                $enrolled = $wpdb->get_row( $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}znc_enrolled_sites WHERE site_id = %d", $site->blog_id
                ) );
                $status = $enrolled ? $enrolled->status : 'not_enrolled';
            ?>
            <tr>
                <td><strong><?php echo esc_html( $site->blogname ?: 'Site #' . $site->blog_id ); ?></strong></td>
                <td><a href="<?php echo esc_url( $site->siteurl ); ?>" target="_blank"><?php echo esc_html( $site->siteurl ); ?></a></td>
                <td><?php echo $has_wc ? '<span class="znc-badge znc-badge-green">Active</span>' : '<span class="znc-badge znc-badge-red">Missing</span>'; ?></td>
                <td><?php echo $has_mycred ? '<span class="znc-badge znc-badge-green">Active</span>' : '<span class="znc-badge znc-badge-gray">N/A</span>'; ?></td>
                <td><?php echo esc_html( $products ); ?></td>
                <td>
                    <?php if ( 'active' === $status ) : ?>
                        <span class="znc-badge znc-badge-green">Enrolled</span>
                    <?php elseif ( 'pending' === $status ) : ?>
                        <span class="znc-badge znc-badge-yellow">Pending</span>
                    <?php else : ?>
                        <span class="znc-badge znc-badge-gray">Not Enrolled</span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="post" style="display:inline;">
                        <?php wp_nonce_field( 'znc_enroll_site_' . $site->blog_id ); ?>
                        <input type="hidden" name="site_id" value="<?php echo esc_attr( $site->blog_id ); ?>" />
                        <?php if ( 'active' === $status ) : ?>
                            <button type="submit" name="znc_action" value="remove" class="button button-small"><?php esc_html_e( 'Remove', 'zinckles-net-cart' ); ?></button>
                            <button type="submit" name="znc_action" value="test" class="button button-small"><?php esc_html_e( 'Test Connection', 'zinckles-net-cart' ); ?></button>
                        <?php elseif ( 'pending' === $status ) : ?>
                            <button type="submit" name="znc_action" value="approve" class="button button-primary button-small"><?php esc_html_e( 'Approve', 'zinckles-net-cart' ); ?></button>
                            <button type="submit" name="znc_action" value="reject" class="button button-small"><?php esc_html_e( 'Reject', 'zinckles-net-cart' ); ?></button>
                        <?php else : ?>
                            <button type="submit" name="znc_action" value="enroll" class="button button-primary button-small" <?php echo $has_wc ? '' : 'disabled'; ?>><?php esc_html_e( 'Enroll', 'zinckles-net-cart' ); ?></button>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
