<?php
/**
 * Global Cart Store — custom DB table CRUD for the unified cross-site cart.
 *
 * v1.2.0 FIX: Table schema expanded to store full product data from all subsites.
 * get_cart() returns items grouped by shop with full metadata.
 * Items from ALL enrolled subsites aggregate into one cart per user.
 *
 * @package ZincklesNetCart
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class ZNC_Global_Cart_Store {

    /** @var string */
    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'znc_global_cart';
    }

    public function init() {
        // Expire stale carts daily.
        if ( ! wp_next_scheduled( 'znc_expire_carts' ) ) {
            wp_schedule_event( time(), 'daily', 'znc_expire_carts' );
        }
        add_action( 'znc_expire_carts', array( $this, 'expire_old_carts' ) );
    }

    /**
     * Create the global cart table.
     * Called from ZNC_Activator::activate().
     */
    public static function create_table() {
        global $wpdb;
        $table   = $wpdb->prefix . 'znc_global_cart';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            blog_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            variation_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            quantity INT UNSIGNED NOT NULL DEFAULT 1,
            product_name VARCHAR(255) NOT NULL DEFAULT '',
            price DECIMAL(12,4) NOT NULL DEFAULT 0,
            line_total DECIMAL(12,4) NOT NULL DEFAULT 0,
            currency VARCHAR(3) NOT NULL DEFAULT 'USD',
            sku VARCHAR(100) DEFAULT '',
            image_url VARCHAR(500) DEFAULT '',
            permalink VARCHAR(500) DEFAULT '',
            in_stock TINYINT(1) NOT NULL DEFAULT 1,
            stock_qty INT DEFAULT NULL,
            variation_data LONGTEXT DEFAULT '',
            meta_data LONGTEXT DEFAULT '',
            shop_name VARCHAR(255) NOT NULL DEFAULT '',
            shop_url VARCHAR(500) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_blog (user_id, blog_id),
            KEY user_id (user_id),
            KEY blog_id (blog_id),
            KEY product_lookup (user_id, blog_id, product_id, variation_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Get the entire global cart for a user — items from ALL subsites.
     *
     * @param int    $user_id
     * @param string $group_by  'flat' returns a simple array, 'shop' groups by blog_id.
     * @return array
     */
    public function get_cart( $user_id, $group_by = 'flat' ) {
        global $wpdb;

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE user_id = %d ORDER BY blog_id ASC, created_at ASC",
            $user_id
        ), ARRAY_A );

        if ( empty( $rows ) ) {
            return array();
        }

        // Unserialize stored data.
        foreach ( $rows as &$row ) {
            $row['variation_data'] = maybe_unserialize( $row['variation_data'] );
            $row['meta_data']      = maybe_unserialize( $row['meta_data'] );
            $row['price']          = (float) $row['price'];
            $row['line_total']     = (float) $row['line_total'];
            $row['quantity']       = (int) $row['quantity'];
            $row['blog_id']        = (int) $row['blog_id'];
            $row['product_id']     = (int) $row['product_id'];
            $row['variation_id']   = (int) $row['variation_id'];
            $row['in_stock']       = (bool) $row['in_stock'];
        }
        unset( $row );

        if ( $group_by === 'shop' ) {
            $grouped = array();
            foreach ( $rows as $item ) {
                $bid = $item['blog_id'];
                if ( ! isset( $grouped[ $bid ] ) ) {
                    $grouped[ $bid ] = array(
                        'blog_id'   => $bid,
                        'shop_name' => $item['shop_name'],
                        'shop_url'  => $item['shop_url'],
                        'currency'  => $item['currency'],
                        'items'     => array(),
                        'subtotal'  => 0.0,
                    );
                }
                $grouped[ $bid ]['items'][]  = $item;
                $grouped[ $bid ]['subtotal'] += $item['line_total'];
            }
            return array_values( $grouped );
        }

        return $rows;
    }

    /**
     * Get cart item count for a user (across all shops).
     */
    public function get_item_count( $user_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(quantity), 0) FROM {$this->table} WHERE user_id = %d",
            $user_id
        ) );
    }

    /**
     * Get the number of distinct shops in a user's cart.
     */
    public function get_shop_count( $user_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT blog_id) FROM {$this->table} WHERE user_id = %d",
            $user_id
        ) );
    }

    /**
     * Get distinct currencies in a user's cart.
     */
    public function get_currencies( $user_id ) {
        global $wpdb;
        return $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT currency FROM {$this->table} WHERE user_id = %d",
            $user_id
        ) );
    }

    /**
     * Add or update an item in the global cart.
     * Upserts: if product+variation+blog already exists for user, updates quantity.
     */
    public function upsert_item( $user_id, $data ) {
        global $wpdb;

        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, quantity FROM {$this->table}
             WHERE user_id = %d AND blog_id = %d AND product_id = %d AND variation_id = %d",
            $user_id,
            $data['blog_id'],
            $data['product_id'],
            $data['variation_id'] ?? 0
        ) );

        if ( $existing ) {
            $new_qty = (int) $data['quantity'];
            $wpdb->update(
                $this->table,
                array(
                    'quantity'   => $new_qty,
                    'price'      => $data['price'],
                    'line_total' => $data['price'] * $new_qty,
                    'in_stock'   => $data['in_stock'] ?? 1,
                    'stock_qty'  => $data['stock_qty'] ?? null,
                    'updated_at' => current_time( 'mysql' ),
                ),
                array( 'id' => $existing->id )
            );
            return $existing->id;
        }

        $wpdb->insert( $this->table, array(
            'user_id'        => $user_id,
            'blog_id'        => $data['blog_id'],
            'product_id'     => $data['product_id'],
            'variation_id'   => $data['variation_id'] ?? 0,
            'quantity'        => $data['quantity'],
            'product_name'   => $data['product_name'] ?? '',
            'price'          => $data['price'],
            'line_total'     => $data['price'] * $data['quantity'],
            'currency'       => $data['currency'] ?? 'USD',
            'sku'            => $data['sku'] ?? '',
            'image_url'      => $data['image_url'] ?? '',
            'permalink'      => $data['permalink'] ?? '',
            'in_stock'       => $data['in_stock'] ?? 1,
            'stock_qty'      => $data['stock_qty'] ?? null,
            'variation_data' => maybe_serialize( $data['variation'] ?? array() ),
            'meta_data'      => maybe_serialize( $data['meta'] ?? array() ),
            'shop_name'      => $data['shop_name'] ?? '',
            'shop_url'       => $data['shop_url'] ?? '',
            'created_at'     => current_time( 'mysql' ),
            'updated_at'     => current_time( 'mysql' ),
        ) );

        return $wpdb->insert_id;
    }

    /**
     * Remove a single item by its row ID.
     */
    public function remove_item( $user_id, $line_id ) {
        global $wpdb;
        return $wpdb->delete( $this->table, array(
            'id'      => $line_id,
            'user_id' => $user_id,
        ) );
    }

    /**
     * Remove all items from a specific subsite for a user.
     */
    public function remove_site_items( $user_id, $blog_id ) {
        global $wpdb;
        return $wpdb->delete( $this->table, array(
            'user_id' => $user_id,
            'blog_id' => $blog_id,
        ) );
    }

    /**
     * Clear the entire cart for a user.
     */
    public function clear_cart( $user_id ) {
        global $wpdb;
        return $wpdb->delete( $this->table, array( 'user_id' => $user_id ) );
    }

    /**
     * Expire carts older than configured hours.
     */
    public function expire_old_carts() {
        global $wpdb;

        $settings = get_site_option( 'znc_network_settings', array() );
        $hours    = absint( $settings['cart_expiry_hours'] ?? 168 );

        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$this->table} WHERE updated_at < DATE_SUB(NOW(), INTERVAL %d HOUR)",
            $hours
        ) );
    }

    /**
     * Get cart summary stats for a user.
     */
    public function get_cart_summary( $user_id ) {
        global $wpdb;

        $stats = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                COUNT(*) as total_lines,
                COALESCE(SUM(quantity), 0) as total_items,
                COUNT(DISTINCT blog_id) as total_shops,
                COUNT(DISTINCT currency) as total_currencies
             FROM {$this->table}
             WHERE user_id = %d",
            $user_id
        ), ARRAY_A );

        // Per-currency totals.
        $currency_totals = $wpdb->get_results( $wpdb->prepare(
            "SELECT currency, SUM(line_total) as subtotal
             FROM {$this->table}
             WHERE user_id = %d
             GROUP BY currency",
            $user_id
        ), ARRAY_A );

        $stats['currency_totals'] = $currency_totals;

        return $stats;
    }
}
