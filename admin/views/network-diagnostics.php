<?php
/**
 * Network Admin — Diagnostics Page
 * v1.2.0
 */
defined( 'ABSPATH' ) || exit;

$enrolled_count = count( $enrolled );
$secret_set     = ! empty( $settings['rest_shared_secret'] );
?>
<div class="wrap">
    <h1><?php _e( 'Net Cart — Diagnostics', 'znc' ); ?></h1>

    <!-- Stats Grid -->
    <div class="znc-diag-grid">
        <div class="znc-diag-card">
            <div class="znc-diag-value"><?php echo $enrolled_count; ?></div>
            <div class="znc-diag-label"><?php _e( 'Enrolled Sites', 'znc' ); ?></div>
        </div>
        <div class="znc-diag-card">
            <div class="znc-diag-value"><?php echo $secret_set ? '✅' : '❌'; ?></div>
            <div class="znc-diag-label"><?php _e( 'REST Secret', 'znc' ); ?></div>
        </div>
        <div class="znc-diag-card">
            <div class="znc-diag-value"><?php echo esc_html( $settings['enrollment_mode'] ); ?></div>
            <div class="znc-diag-label"><?php _e( 'Enrollment Mode', 'znc' ); ?></div>
        </div>
        <div class="znc-diag-card">
            <div class="znc-diag-value"><?php echo esc_html( $settings['base_currency'] ); ?></div>
            <div class="znc-diag-label"><?php _e( 'Base Currency', 'znc' ); ?></div>
        </div>
    </div>

    <?php if ( $enrolled_count > 0 ) : ?>
    <div class="card" style="max-width:780px;margin-bottom:24px;">
        <h2>
            <?php _e( 'Connection Tests', 'znc' ); ?>
            <button type="button" class="button button-small" id="znc-test-all" style="margin-left:12px;">
                <?php _e( 'Test All Connections', 'znc' ); ?>
            </button>
        </h2>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php _e( 'Site', 'znc' ); ?></th>
                    <th><?php _e( 'WooCommerce', 'znc' ); ?></th>
                    <th><?php _e( 'MyCred', 'znc' ); ?></th>
                    <th><?php _e( 'Products', 'znc' ); ?></th>
                    <th><?php _e( 'Status', 'znc' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $enrolled as $site ) : ?>
                <tr>
                    <td><strong><?php echo esc_html( $site['name'] ); ?></strong></td>
                    <td><?php echo $site['wc_active'] ? '✅' : '❌'; ?></td>
                    <td><?php echo $site['mycred'] ? '✅' : '—'; ?></td>
                    <td><?php echo (int) $site['products']; ?></td>
                    <td>
                        <button type="button" class="button button-small znc-test-connection"
                                data-blog-id="<?php echo $site['blog_id']; ?>">
                            <?php _e( 'Test', 'znc' ); ?>
                        </button>
                        <span class="znc-connection-result"></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else : ?>
        <div class="notice notice-warning">
            <p><?php _e( 'No subsites are enrolled yet. Go to Subsites to enroll shops.', 'znc' ); ?></p>
        </div>
    <?php endif; ?>

    <div class="card" style="max-width:780px;">
        <h2><?php _e( 'System Info', 'znc' ); ?></h2>
        <table class="form-table">
            <tr><th><?php _e( 'Plugin Version', 'znc' ); ?></th><td><code><?php echo ZNC_VERSION; ?></code></td></tr>
            <tr><th><?php _e( 'DB Version', 'znc' ); ?></th><td><code><?php echo get_site_option( 'znc_db_version', '—' ); ?></code></td></tr>
            <tr><th><?php _e( 'PHP Version', 'znc' ); ?></th><td><code><?php echo PHP_VERSION; ?></code></td></tr>
            <tr><th><?php _e( 'WordPress', 'znc' ); ?></th><td><code><?php echo get_bloginfo( 'version' ); ?></code></td></tr>
            <tr><th><?php _e( 'Multisite', 'znc' ); ?></th><td><?php echo is_multisite() ? '✅ Yes' : '❌ No'; ?></td></tr>
        </table>
    </div>
</div>
