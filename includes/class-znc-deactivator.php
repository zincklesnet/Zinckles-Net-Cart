<?php
/**
 * Deactivator — v1.4.1
 * Cleanup on plugin deactivation.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Deactivator {

    public static function deactivate( $network_wide ) {
        // Clear scheduled events
        wp_clear_scheduled_hook( 'znc_cart_cleanup' );
        wp_clear_scheduled_hook( 'znc_inventory_refresh' );

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
