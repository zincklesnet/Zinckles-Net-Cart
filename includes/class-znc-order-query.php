<?php
/**
 * Order Query — v1.4.1
 * Queries WooCommerce orders across the multisite network for a user.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Order_Query {

    public function init() {}

    /**
     * Get all Net Cart orders for a user across all enrolled subsites.
     */
    public static function get_network_orders( $user_id, $args = array() ) {
        $defaults = array(
            'status'   => array( 'completed', 'processing', 'on-hold' ),
            'limit'    => 50,
            'offset'   => 0,
            'orderby'  => 'date',
            'order'    => 'DESC',
        );
        $args = wp_parse_args( $args, $defaults );

        $settings = get_site_option( 'znc_network_settings', array() );
        $enrolled = isset( $settings['enrolled_sites'] ) ? (array) $settings['enrolled_sites'] : array();

        // Always include checkout host
        $host_id = isset( $settings['checkout_host_id'] ) ? (int) $settings['checkout_host_id'] : get_main_site_id();
        if ( ! in_array( $host_id, $enrolled ) ) {
            $enrolled[] = $host_id;
        }

        $all_orders = array();

        foreach ( $enrolled as $blog_id ) {
            $blog_id = (int) $blog_id;
            switch_to_blog( $blog_id );

            if ( ! function_exists( 'wc_get_orders' ) ) {
                restore_current_blog();
                continue;
            }

            $orders = wc_get_orders( array(
                'customer_id' => $user_id,
                'status'      => $args['status'],
                'limit'       => $args['limit'],
                'orderby'     => $args['orderby'],
                'order'       => $args['order'],
                'meta_key'    => '_znc_global_order',
                'meta_value'  => '1',
            ) );

            $blog_name = get_bloginfo( 'name' );
            $blog_url  = get_bloginfo( 'url' );

            foreach ( $orders as $order ) {
                $all_orders[] = self::format_order( $order, $blog_id, $blog_name, $blog_url );
            }

            restore_current_blog();
        }

        // Also get non-ZNC orders (regular WC orders on each subsite)
        foreach ( $enrolled as $blog_id ) {
            $blog_id = (int) $blog_id;
            switch_to_blog( $blog_id );

            if ( ! function_exists( 'wc_get_orders' ) ) {
                restore_current_blog();
                continue;
            }

            $orders = wc_get_orders( array(
                'customer_id' => $user_id,
                'status'      => $args['status'],
                'limit'       => $args['limit'],
                'orderby'     => $args['orderby'],
                'order'       => $args['order'],
                'meta_query'  => array(
                    array(
                        'key'     => '_znc_global_order',
                        'compare' => 'NOT EXISTS',
                    ),
                ),
            ) );

            $blog_name = get_bloginfo( 'name' );
            $blog_url  = get_bloginfo( 'url' );

            foreach ( $orders as $order ) {
                $all_orders[] = self::format_order( $order, $blog_id, $blog_name, $blog_url );
            }

            restore_current_blog();
        }

        // Sort by date descending
        usort( $all_orders, function( $a, $b ) {
            return strtotime( $b['date'] ) - strtotime( $a['date'] );
        } );

        // Apply limit/offset
        return array_slice( $all_orders, $args['offset'], $args['limit'] );
    }

    private static function format_order( $order, $blog_id, $blog_name, $blog_url ) {
        $items = array();
        foreach ( $order->get_items() as $item ) {
            $items[] = array(
                'name'     => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total'    => $item->get_total(),
            );
        }

        $payment_type = $order->get_meta( '_znc_payment_type' ) ?: 'currency';
        $point_type   = $order->get_meta( '_znc_point_type' ) ?: '';
        $currency     = $order->get_meta( '_znc_currency_type' ) ?: $order->get_currency();

        return array(
            'order_id'     => $order->get_id(),
            'blog_id'      => $blog_id,
            'shop_name'    => $blog_name,
            'shop_url'     => $blog_url,
            'status'       => $order->get_status(),
            'date'         => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i:s' ) : '',
            'total'        => $order->get_total(),
            'currency'     => $currency,
            'payment_type' => $payment_type,
            'point_type'   => $point_type,
            'items'        => $items,
            'is_global'    => (bool) $order->get_meta( '_znc_global_order' ),
        );
    }
}
