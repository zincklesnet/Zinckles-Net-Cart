<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Deactivator {
    public static function deactivate() {
        wp_clear_scheduled_hook( 'znc_inventory_retry_cron' );
        wp_clear_scheduled_hook( 'znc_cart_cleanup_cron' );
        delete_site_transient( 'znc_enrolled_sites' );
        delete_site_transient( 'znc_mycred_point_types' );
    }
}
