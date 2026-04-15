<?php
defined( 'ABSPATH' ) || exit;
class ZNC_Order_Query {
    public function get_user_orders( $user_id, $limit = 20 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'znc_order_map';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT DISTINCT parent_order_id, MIN(created_at) as created_at
             FROM {$table}
             WHERE parent_order_id IN (
                SELECT ID FROM {$wpdb->posts}
                WHERE post_type = 'shop_order' AND post_author = %d
                   OR ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_customer_user' AND meta_value = %d)
             )
             GROUP BY parent_order_id
             ORDER BY created_at DESC
             LIMIT %d",
            $user_id, $user_id, $limit
        ) );
    }
    public function get_child_orders( $parent_order_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'znc_order_map';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE parent_order_id = %d ORDER BY child_blog_id",
            $parent_order_id
        ) );
    }
    public function get_shop_count( $parent_order_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'znc_order_map';
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT child_blog_id) FROM {$table} WHERE parent_order_id = %d",
            $parent_order_id
        ) );
    }
}
