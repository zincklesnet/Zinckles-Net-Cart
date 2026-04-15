<?php
/**
 * Global Cart Store — CRUD operations for the znc_global_cart table.
 * v1.3.2 — column names match Cart Snapshot exactly.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Global_Cart_Store {

    public function init() {
        add_action( 'wp_ajax_znc_remove_cart_item', array( $this, 'ajax_remove_item' ) );
        add_action( 'wp_ajax_znc_update_cart_qty',  array( $this, 'ajax_update_qty' ) );
        add_action( 'wp_ajax_znc_clear_cart',       array( $this, 'ajax_clear_cart' ) );
    }

    private function table() {
        global $wpdb;
        return $wpdb->prefix . 'znc_global_cart';
    }

    public function get_cart( $user_id ) {
        global $wpdb;
        $table = $this->table();
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY blog_id, created_at DESC",
            $user_id
        ) );
    }

    public function get_cart_grouped( $user_id ) {
        $items = $this->get_cart( $user_id );
        $shops = array();
        foreach ( $items as $item ) {
            $bid = $item->blog_id;
            if ( ! isset( $shops[ $bid ] ) ) {
                $shops[ $bid ] = array(
                    'blog_id'   => $bid,
                    'shop_name' => $item->shop_name,
                    'shop_url'  => $item->shop_url,
                    'currency'  => $item->currency,
                    'items'     => array(),
                );
            }
            $shops[ $bid ]['items'][] = $item;
        }
        return $shops;
    }

    public function upsert_item( $data ) {
        global $wpdb;
        $table = $this->table();

        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE user_id = %d AND blog_id = %d AND product_id = %d AND variation_id = %d LIMIT 1",
            $data['user_id'], $data['blog_id'], $data['product_id'], $data['variation_id'] ?? 0
        ) );

        if ( $existing ) {
            $wpdb->update( $table, $data, array( 'id' => $existing ) );
            return $existing;
        }

        $wpdb->insert( $table, $data );
        return $wpdb->insert_id;
    }

    public function remove_item( $item_id, $user_id ) {
        global $wpdb;
        return $wpdb->delete( $this->table(), array( 'id' => $item_id, 'user_id' => $user_id ) );
    }

    public function update_qty( $item_id, $user_id, $quantity ) {
        global $wpdb;
        return $wpdb->update(
            $this->table(),
            array( 'quantity' => max( 1, (int) $quantity ), 'updated_at' => current_time( 'mysql', true ) ),
            array( 'id' => $item_id, 'user_id' => $user_id )
        );
    }

    public function clear_cart( $user_id ) {
        global $wpdb;
        return $wpdb->delete( $this->table(), array( 'user_id' => $user_id ) );
    }

    public function get_item_count( $user_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(quantity), 0) FROM {$this->table()} WHERE user_id = %d", $user_id
        ) );
    }

    public function get_shop_count( $user_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT blog_id) FROM {$this->table()} WHERE user_id = %d", $user_id
        ) );
    }

    public function get_currencies( $user_id ) {
        global $wpdb;
        return $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT currency FROM {$this->table()} WHERE user_id = %d", $user_id
        ) );
    }

    /* AJAX handlers */

    public function ajax_remove_item() {
        check_ajax_referer( 'znc_front', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'Not logged in.' ) );

        $item_id = absint( $_POST['item_id'] ?? 0 );
        if ( ! $item_id ) wp_send_json_error( array( 'message' => 'Invalid item.' ) );

        $this->remove_item( $item_id, get_current_user_id() );
        wp_send_json_success( array( 'message' => 'Item removed.', 'count' => $this->get_item_count( get_current_user_id() ) ) );
    }

    public function ajax_update_qty() {
        check_ajax_referer( 'znc_front', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'Not logged in.' ) );

        $item_id  = absint( $_POST['item_id'] ?? 0 );
        $quantity = absint( $_POST['quantity'] ?? 1 );
        if ( ! $item_id ) wp_send_json_error( array( 'message' => 'Invalid item.' ) );

        $this->update_qty( $item_id, get_current_user_id(), $quantity );
        wp_send_json_success( array( 'message' => 'Quantity updated.' ) );
    }

    public function ajax_clear_cart() {
        check_ajax_referer( 'znc_front', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'Not logged in.' ) );

        $this->clear_cart( get_current_user_id() );
        wp_send_json_success( array( 'message' => 'Cart cleared.' ) );
    }
}
