<?php
/**
 * Plugin Activator — creates DB tables and initial config on network activation.
 *
 * v1.2.0: Updated table schema for full product data storage across subsites.
 *
 * @package ZincklesNetCart
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class ZNC_Activator {

    public static function activate( $network_wide ) {
        if ( $network_wide && is_multisite() ) {
            // Create tables on main site.
            switch_to_blog( get_main_site_id() );
            self::create_tables();
            self::set_defaults();
            restore_current_blog();
        } else {
            self::create_tables();
            self::set_defaults();
        }
    }

    private static function create_tables() {
        ZNC_Global_Cart_Store::create_table();
        self::create_order_map_table();
    }

    private static function create_order_map_table() {
        global $wpdb;
        $table   = $wpdb->prefix . 'znc_order_map';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            parent_order_id BIGINT UNSIGNED NOT NULL,
            child_order_id BIGINT UNSIGNED NOT NULL,
            child_blog_id BIGINT UNSIGNED NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT 'USD',
            subtotal DECIMAL(12,4) NOT NULL DEFAULT 0,
            status VARCHAR(50) NOT NULL DEFAULT 'processing',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY parent_order (parent_order_id),
            KEY child_order (child_order_id, child_blog_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    private static function set_defaults() {
        // Generate initial REST secret if none exists.
        $settings = get_site_option( 'znc_network_settings', array() );
        if ( empty( $settings['rest_shared_secret'] ) ) {
            $settings['rest_shared_secret'] = wp_generate_password( 64, true, true );
            update_site_option( 'znc_network_settings', $settings );
        }

        // Store DB version.
        update_site_option( 'znc_db_version', ZNC_DB_VERSION );
    }
}
