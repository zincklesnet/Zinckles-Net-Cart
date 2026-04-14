<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Global_Cart_Store {

    public static function init() {
        /* CRUD methods called on demand */
    }

    public static function get_cart( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'znc_global_cart';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND expires_at > NOW() ORDER BY source_blog_id, added_at DESC",
            $user_id
        ), ARRAY_A );
    }

    public static function get_cart_grouped( $user_id ) {
        $items   = self::get_cart( $user_id );
        $grouped = array();
        foreach ( $items as $item ) {
            $blog_id = $item['source_blog_id'];
            if ( ! isset( $grouped[ $blog_id ] ) ) {
                $grouped[ $blog_id ] = array(
                    'blog_id'   => $blog_id,
                    'shop_name' => $item['shop_name'],
                    'shop_url'  => $item['shop_url'],
                    'currency'  => $item['currency'],
                    'items'     => array(),
                );
            }
            $grouped[ $blog_id ]['items'][] = $item;
        }
        return $grouped;
    }

    public static function add_item( $user_id, $item_data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'znc_global_cart';

        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE user_id = %d AND source_blog_id = %d AND product_id = %d AND variation_id = %d LIMIT 1",
            $user_id, $item_data['source_blog_id'], $item_data['product_id'], $item_data['variation_id'] ?? 0
        ) );

        if ( $existing ) {
            $wpdb->update( $table,
                array( 'quantity' => $item_data['quantity'], 'product_price' => $item_data['product_price'] ),
                array( 'id' => $existing )
            );
            return array( 'action' => 'updated', 'id' => $existing );
        }

        $item_data['user_id'] = $user_id;
        if ( empty( $item_data['expires_at'] ) ) {
            $days = absint( get_site_option( 'znc_network_settings', array() )['cart_expiry_days'] ?? 7 );
            $item_data['expires_at'] = gmdate( 'Y-m-d H:i:s', time() + ( $days * DAY_IN_SECONDS ) );
        }
        $wpdb->insert( $table, $item_data );
        return array( 'action' => 'added', 'id' => $wpdb->insert_id );
    }

    public static function remove_item( $user_id, $item_id ) {
        global $wpdb;
        $table   = $wpdb->prefix . 'znc_global_cart';
        $deleted = $wpdb->delete( $table, array( 'id' => $item_id, 'user_id' => $user_id ) );
        return array( 'deleted' => (bool) $deleted );
    }

    public static function clear_cart( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'znc_global_cart';
        $wpdb->delete( $table, array( 'user_id' => $user_id ) );
    }

    public static function get_cart_count( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'znc_global_cart';
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(quantity),0) FROM {$table} WHERE user_id = %d AND expires_at > NOW()",
            $user_id
        ) );
    }

    public static function cleanup_expired() {
        global $wpdb;
        $table = $wpdb->prefix . 'znc_global_cart';
        $wpdb->query( "DELETE FROM {$table} WHERE expires_at < NOW()" );
    }
}
