<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Activator {

    public static function activate( $network_wide = false ) {
        if ( is_multisite() && $network_wide ) {
            $host = new ZNC_Checkout_Host();
            $host_id = $host->get_host_id();
            switch_to_blog( $host_id );
            self::create_tables();
            restore_current_blog();
            self::generate_rest_secret();
        } else {
            self::create_tables();
            self::generate_rest_secret();
        }
    }

    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $table_cart = $wpdb->prefix . 'znc_global_cart';
        $table_map  = $wpdb->prefix . 'znc_order_map';

        $sql_cart = "CREATE TABLE IF NOT EXISTS {$table_cart} (
            id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id         BIGINT(20) UNSIGNED NOT NULL,
            source_blog_id  BIGINT(20) UNSIGNED NOT NULL,
            product_id      BIGINT(20) UNSIGNED NOT NULL,
            variation_id    BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            quantity        INT(11) UNSIGNED NOT NULL DEFAULT 1,
            product_name    VARCHAR(255) NOT NULL DEFAULT '',
            product_price   DECIMAL(13,4) NOT NULL DEFAULT 0,
            currency        VARCHAR(3) NOT NULL DEFAULT 'USD',
            product_image   VARCHAR(500) NOT NULL DEFAULT '',
            product_sku     VARCHAR(100) NOT NULL DEFAULT '',
            product_url     VARCHAR(500) NOT NULL DEFAULT '',
            shop_name       VARCHAR(255) NOT NULL DEFAULT '',
            shop_url        VARCHAR(500) NOT NULL DEFAULT '',
            variation_data  TEXT,
            stock_status    VARCHAR(20) NOT NULL DEFAULT 'instock',
            stock_qty       INT(11) DEFAULT NULL,
            meta            TEXT,
            added_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at      DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY source_blog_id (source_blog_id),
            KEY expires_at (expires_at),
            UNIQUE KEY user_product (user_id, source_blog_id, product_id, variation_id)
        ) {$charset};";

        $sql_map = "CREATE TABLE IF NOT EXISTS {$table_map} (
            id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            parent_order_id BIGINT(20) UNSIGNED NOT NULL,
            child_order_id  BIGINT(20) UNSIGNED NOT NULL,
            child_blog_id   BIGINT(20) UNSIGNED NOT NULL,
            status          VARCHAR(30) NOT NULL DEFAULT 'pending',
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY parent_order_id (parent_order_id),
            KEY child_blog_id (child_blog_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_cart );
        dbDelta( $sql_map );

        update_site_option( 'znc_db_version', ZNC_DB_VERSION );
    }

    public static function generate_rest_secret() {
        if ( ! get_site_option( 'znc_rest_secret' ) ) {
            update_site_option( 'znc_rest_secret', wp_generate_password( 64, true, true ) );
        }
    }
}
