<?php
/**
 * Plugin Deactivator — cleans up cron events on deactivation.
 *
 * @package ZincklesNetCart
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class ZNC_Deactivator {

    public static function deactivate() {
        wp_clear_scheduled_hook( 'znc_expire_carts' );
        wp_clear_scheduled_hook( 'znc_inventory_retry' );
    }
}
