<?php
/**
 * Global Cart Store — v1.4.1
 * CRUD for znc_global_cart table on the checkout-host blog.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Global_Cart_Store {

    private $host;

    public function __construct( ZNC_Checkout_Host $host ) {
        $this->host = $host;
    }

    public function init() {}

    /* ── Read ─────────────────────────────────────── */

    public function get_items( $user_id ) {
        global $wpdb;
        $table = $this->table();
        if ( ! $table ) return array();
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC", $user_id
        ), ARRAY_A );
    }

    public function get_items_grouped( $user_id ) {
        $items  = $this->get_items( $user_id );
        $groups = array();
        foreach ( $items as $item ) {
            $bid = $item['blog_id'];
            if ( ! isset( $groups[ $bid ] ) ) {
                $groups[ $bid ] = array(
                    'shop_name' => $item['shop_name'],
                    'shop_url'  => $item['shop_url'],
                    'blog_id'   => $bid,
                    'items'     => array(),
                );
            }
            $groups[ $bid ]['items'][] = $item;
        }
        return $groups;
    }

    public function count( $user_id ) {
        global $wpdb;
        $table = $this->table();
        if ( ! $table ) return 0;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(quantity),0) FROM {$table} WHERE user_id = %d", $user_id
        ) );
    }

    public function get_total( $user_id ) {
        global $wpdb;
        $table = $this->table();
        if ( ! $table ) return 0;
        return (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(line_total),0) FROM {$table} WHERE user_id = %d", $user_id
        ) );
    }

    public function get_item( $user_id, $blog_id, $product_id, $variation_id = 0 ) {
        global $wpdb;
        $table = $this->table();
        if ( ! $table ) return null;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id=%d AND blog_id=%d AND product_id=%d AND variation_id=%d",
            $user_id, $blog_id, $product_id, $variation_id
        ), ARRAY_A );
    }

    /* ── Write ────────────────────────────────────── */

    public function upsert( $data ) {
        global $wpdb;
        $table = $this->table( true );
        if ( ! $table ) return false;

        $data['line_total']  = ( $data['price'] ?? 0 ) * ( $data['quantity'] ?? 1 );
        $data['updated_at']  = current_time( 'mysql' );

        $existing = $this->get_item(
            $data['user_id'], $data['blog_id'], $data['product_id'], $data['variation_id'] ?? 0
        );

        if ( $existing ) {
            $ok = $wpdb->update( $table, $data, array( 'id' => $existing['id'] ) );
            if ( false === $ok ) {
                $this->log_error( 'update', $wpdb->last_error, $data );
                return false;
            }
        } else {
            $data['created_at'] = current_time( 'mysql' );
            $ok = $wpdb->insert( $table, $data );
            if ( false === $ok ) {
                $this->log_error( 'insert', $wpdb->last_error, $data );
                return false;
            }
        }

        ZNC_Cart_Sync::invalidate( $data['user_id'] );
        return true;
    }

    public function remove( $user_id, $blog_id, $product_id, $variation_id = 0 ) {
        global $wpdb;
        $table = $this->table();
        if ( ! $table ) return false;
        $ok = $wpdb->delete( $table, array(
            'user_id'      => $user_id,
            'blog_id'      => $blog_id,
            'product_id'   => $product_id,
            'variation_id' => $variation_id,
        ) );
        if ( false !== $ok ) {
            ZNC_Cart_Sync::invalidate( $user_id );
        }
        return $ok;
    }

    public function update_quantity( $user_id, $item_id, $qty ) {
        global $wpdb;
        $table = $this->table();
        if ( ! $table ) return false;
        $item = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id=%d AND user_id=%d", $item_id, $user_id
        ), ARRAY_A );
        if ( ! $item ) return false;
        $line_total = $item['price'] * $qty;
        $ok = $wpdb->update( $table,
            array( 'quantity' => $qty, 'line_total' => $line_total, 'updated_at' => current_time('mysql') ),
            array( 'id' => $item_id )
        );
        if ( false !== $ok ) ZNC_Cart_Sync::invalidate( $user_id );
        return $ok;
    }

    public function clear( $user_id ) {
        global $wpdb;
        $table = $this->table();
        if ( ! $table ) return false;
        $ok = $wpdb->delete( $table, array( 'user_id' => $user_id ) );
        if ( false !== $ok ) ZNC_Cart_Sync::invalidate( $user_id );
        return $ok;
    }

    public function clear_expired( $days = 7 ) {
        global $wpdb;
        $table = $this->table();
        if ( ! $table ) return 0;
        return $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table} WHERE updated_at < DATE_SUB(NOW(), INTERVAL %d DAY)", $days
        ) );
    }

    /* ── Helpers ──────────────────────────────────── */

    private function table( $auto_create = false ) {
        global $wpdb;
        $host_id = $this->host->get_host_id();
        $prefix  = $wpdb->get_blog_prefix( $host_id );
        $table   = $prefix . 'znc_global_cart';

        if ( $auto_create ) {
            $exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
            if ( ! $exists ) {
                switch_to_blog( $host_id );
                ZNC_Activator::create_tables();
                restore_current_blog();
            }
        }
        return $table;
    }

    private function log_error( $op, $error, $data ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf(
                '[ZNC Global Cart Store] %s failed: %s | data: %s',
                $op, $error, wp_json_encode( $data )
            ) );
        }
    }
}
