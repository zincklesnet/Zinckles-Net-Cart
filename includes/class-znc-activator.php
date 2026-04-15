<?php
/**
 * Activator — v1.4.1
 * Creates DB tables and sets default options on network activation.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Activator {

    public static function activate( $network_wide ) {
        if ( $network_wide && is_multisite() ) {
            $host_id = get_main_site_id();
            switch_to_blog( $host_id );
            self::create_tables();
            restore_current_blog();
        } else {
            self::create_tables();
        }
        self::set_defaults();
    }

    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        // Global cart table
        $table = $wpdb->prefix . 'znc_global_cart';
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            blog_id BIGINT(20) UNSIGNED NOT NULL,
            product_id BIGINT(20) UNSIGNED NOT NULL,
            variation_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            quantity INT(11) NOT NULL DEFAULT 1,
            product_name VARCHAR(255) NOT NULL DEFAULT '',
            price DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
            currency VARCHAR(10) NOT NULL DEFAULT 'USD',
            image_url TEXT,
            sku VARCHAR(100) DEFAULT '',
            permalink TEXT,
            shop_name VARCHAR(255) NOT NULL DEFAULT '',
            shop_url TEXT,
            variation_data LONGTEXT,
            in_stock TINYINT(1) NOT NULL DEFAULT 1,
            stock_qty INT(11) DEFAULT NULL,
            meta_data LONGTEXT,
            line_total DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_product (user_id, blog_id, product_id, variation_id),
            KEY user_id (user_id),
            KEY blog_id (blog_id)
        ) {$charset};";

        // Order map table
        $map_table = $wpdb->prefix . 'znc_order_map';
        $sql2 = "CREATE TABLE IF NOT EXISTS {$map_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            parent_order_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            child_order_id BIGINT(20) UNSIGNED NOT NULL,
            child_blog_id BIGINT(20) UNSIGNED NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY parent_order_id (parent_order_id),
            KEY child_order_id (child_order_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        dbDelta( $sql2 );
    }

    private static function set_defaults() {
        $existing = get_site_option( 'znc_network_settings' );
        if ( $existing ) return;

        $defaults = array(
            'checkout_host_id'       => get_main_site_id(),
            'enrollment_mode'        => 'manual',
            'base_currency'          => 'USD',
            'mixed_currency'         => 0,
            'cart_expiry_days'       => 7,
            'max_items'              => 100,
            'max_shops'              => 10,
            'debug_mode'             => 0,
            'clear_local_cart'       => 0,
            'enrolled_sites'         => array(),
            'blocked_sites'          => array(),
            'hmac_secret'            => ZNC_REST_Auth::generate_secret(),
            'clock_skew'             => 300,
            'rate_limit'             => 60,
            'ip_whitelist'           => '',
            'mycred_types_config'    => array(),
            'gamipress_types_config' => array(),
            'cart_page_id'           => 0,
            'checkout_page_id'       => 0,
        );
        update_site_option( 'znc_network_settings', $defaults );
    }
}
