<?php
/**
 * Network Subsites View — v1.7.0
 * Manage enrolled subsites with test connection and enrollment controls.
 */
defined( 'ABSPATH' ) || exit;

$settings = get_site_option( 'znc_network_settings', array() );
$enrolled = (array) ( $settings['enrolled_sites'] ?? array() );
$host_id  = absint( $settings['checkout_host_id'] ?? get_main_site_id() );
$sites    = get_sites( array( 'number' => 200 ) );
?>
<div class="wrap znc-admin-wrap">
<h1><span class="dashicons dashicons-networking"></span> <?php esc_html_e( 'Net Cart — Enrolled Subsites', 'zinckles-net-cart' ); ?></h1>
<div id="znc-subsite-notice" style="display:none;" class="notice is-dismissible"><p></p></div>

<table class="widefat striped">
<thead>
<tr>
<th><?php esc_html_e( 'Site', 'zinckles-net-cart' ); ?></th>
<th><?php esc_html_e( 'ID', 'zinckles-net-cart' ); ?></th>
<th><?php esc_html_e( 'URL', 'zinckles-net-cart' ); ?></th>
<th><?php esc_html_e( 'Status', 'zinckles-net-cart' ); ?></th>
<th><?php esc_html_e( 'Actions', 'zinckles-net-cart' ); ?></th>
</tr>
</thead>
<tbody>
<?php foreach ( $sites as $site ) :
    $bid     = (int) $site->blog_id;
    $details = get_blog_details( $bid );
    $name    = $details ? $details->blogname : $site->domain . $site->path;
    $url     = $details ? $details->siteurl : '';
    $is_enrolled = in_array( $bid, $enrolled, true );
    $is_host     = ( $bid === $host_id );
?>
<tr data-blog-id="<?php echo esc_attr( $bid ); ?>">
<td>
    <strong><?php echo esc_html( $name ); ?></strong>
    <?php if ( $is_host ) : ?><span class="znc-tag znc-tag-host"><?php esc_html_e( 'Checkout Host', 'zinckles-net-cart' ); ?></span><?php endif; ?>
</td>
<td><?php echo esc_html( $bid ); ?></td>
<td><a href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( $url ); ?></a></td>
<td>
    <?php if ( $is_enrolled ) : ?>
        <span class="znc-status znc-status-enrolled"><?php esc_html_e( 'Enrolled', 'zinckles-net-cart' ); ?></span>
    <?php elseif ( $is_host ) : ?>
        <span class="znc-status znc-status-host"><?php esc_html_e( 'Host', 'zinckles-net-cart' ); ?></span>
    <?php else : ?>
        <span class="znc-status znc-status-none"><?php esc_html_e( 'Not Enrolled', 'zinckles-net-cart' ); ?></span>
    <?php endif; ?>
    <span class="znc-test-result" data-bid="<?php echo esc_attr( $bid ); ?>"></span>
</td>
<td>
    <?php if ( ! $is_host ) : ?>
        <?php if ( $is_enrolled ) : ?>
            <button type="button" class="button znc-remove-site" data-blog-id="<?php echo esc_attr( $bid ); ?>"><?php esc_html_e( 'Remove', 'zinckles-net-cart' ); ?></button>
        <?php else : ?>
            <button type="button" class="button button-primary znc-enroll-site" data-blog-id="<?php echo esc_attr( $bid ); ?>"><?php esc_html_e( 'Enroll', 'zinckles-net-cart' ); ?></button>
        <?php endif; ?>
    <?php endif; ?>
    <button type="button" class="button znc-test-site" data-blog-id="<?php echo esc_attr( $bid ); ?>"><?php esc_html_e( 'Test', 'zinckles-net-cart' ); ?></button>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
