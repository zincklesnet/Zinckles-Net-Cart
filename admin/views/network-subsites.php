<?php
defined( 'ABSPATH' ) || exit;

$sites = ZNC_Checkout_Host::get_all_sites_for_admin();
$host  = new ZNC_Checkout_Host();
$host_id = $host->get_host_id();
?>
<div class="wrap">
    <h1>Zinckles Net Cart — Enrolled Subsites</h1>
    <p class="description">Manage which sites participate in the Net Cart network. The checkout host is marked with a star.</p>

    <table class="widefat striped" id="znc-subsites-table">
        <thead>
            <tr>
                <th>Site</th>
                <th>URL</th>
                <th>WooCommerce</th>
                <th>Products</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $sites as $site ) : ?>
            <tr id="znc-site-row-<?php echo esc_attr( $site['blog_id'] ); ?>"
                data-blog-id="<?php echo esc_attr( $site['blog_id'] ); ?>">
                <td>
                    <strong><?php echo esc_html( $site['blogname'] ); ?></strong>
                    <?php if ( $site['is_host'] ) : ?>
                        <span class="znc-badge znc-badge-host" title="Checkout Host">⭐ Host</span>
                    <?php endif; ?>
                </td>
                <td><a href="<?php echo esc_url( $site['siteurl'] ); ?>" target="_blank"><?php echo esc_html( $site['siteurl'] ); ?></a></td>
                <td>
                    <?php if ( $site['has_wc'] ) : ?>
                        <span class="znc-badge znc-badge-success">✅ Active</span>
                    <?php else : ?>
                        <span class="znc-badge znc-badge-warning">❌ Not Found</span>
                    <?php endif; ?>
                </td>
                <td><?php echo (int) $site['product_count']; ?></td>
                <td>
                    <span class="znc-enrollment-status" id="znc-status-<?php echo esc_attr( $site['blog_id'] ); ?>">
                        <?php if ( $site['is_enrolled'] ) : ?>
                            <span class="znc-badge znc-badge-success">Enrolled</span>
                        <?php else : ?>
                            <span class="znc-badge znc-badge-neutral">Not Enrolled</span>
                        <?php endif; ?>
                    </span>
                </td>
                <td>
                    <?php if ( ! $site['is_host'] ) : ?>
                        <button type="button"
                                class="button znc-enroll-btn"
                                id="znc-btn-<?php echo esc_attr( $site['blog_id'] ); ?>"
                                data-blog-id="<?php echo esc_attr( $site['blog_id'] ); ?>"
                                data-action="<?php echo $site['is_enrolled'] ? 'remove' : 'enroll'; ?>"
                                <?php if ( ! $site['has_wc'] && ! $site['is_enrolled'] ) echo 'disabled title="WooCommerce required"'; ?>>
                            <?php echo $site['is_enrolled'] ? 'Remove' : 'Enroll'; ?>
                        </button>
                        <button type="button" class="button znc-test-btn" data-blog-id="<?php echo esc_attr( $site['blog_id'] ); ?>">
                            Test
                        </button>
                    <?php else : ?>
                        <em>Checkout Host</em>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
