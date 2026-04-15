<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Deactivator {
    public static function deactivate() {
        wp_clear_scheduled_hook( 'znc_inventory_retry' );
        wp_clear_scheduled_hook( 'znc_cart_cleanup' );
        delete_site_transient( 'znc_mycred_point_types' );
        // Don't delete tables or settings on deactivation — only on uninstall
    }
}
