<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Order_Query {

    public static function get_net_cart_orders( $user_id, $limit = 20 ) {
        $orders = wc_get_orders( array(
            'customer_id' => $user_id,
            'meta_key'    => '_znc_is_parent',
            'meta_value'  => 'yes',
            'limit'       => $limit,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ) );

        $results = array();
        foreach ( $orders as $order ) {
            $order_id = $order->get_id();
            $shops    = self::get_child_shops( $order_id );
            $totals   = $order->get_meta( '_znc_totals' );
            $mycred   = $order->get_meta( '_znc_mycred_deductions' );

            $results[] = array(
                'order_id' => $order_id,
                'date'     => $order->get_date_created()->date_i18n( 'Y-m-d H:i' ),
                'status'   => $order->get_status(),
                'total'    => $order->get_total(),
                'currency' => $order->get_currency(),
                'shops'    => $shops,
                'totals'   => $totals,
                'mycred'   => $mycred,
            );
        }

        return $results;
    }

    public static function get_child_shops( $parent_order_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'znc_order_map';
        $rows  = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE parent_order_id = %d",
            $parent_order_id
        ), ARRAY_A );

        $shops = array();
        foreach ( $rows as $row ) {
            $details = get_blog_details( $row['child_blog_id'] );
            $shops[] = array(
                'blog_id'        => $row['child_blog_id'],
                'name'           => $details ? $details->blogname : 'Site ' . $row['child_blog_id'],
                'child_order_id' => $row['child_order_id'],
                'status'         => $row['status'],
            );
        }

        return $shops;
    }
}
