<?php
/**
 * Order Query — Cross-site order querying.
 *
 * Provides helpers for querying Net Cart orders across the multisite,
 * resolving parent/child relationships, and building order history.
 *
 * @package ZincklesNetCart
 * @since   1.7.0
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Order_Query {

    public function init() {
        // Nothing to hook at this stage; purely utility.
    }

    /**
     * Get all Net Cart parent orders for a user on the checkout host.
     *
     * @param int $user_id
     * @param int $limit
     * @param int $offset
     * @return array  WC_Order[]
     */
    public static function get_user_parent_orders( $user_id, $limit = 20, $offset = 0 ) {
        if ( ! function_exists( 'wc_get_orders' ) ) return array();

        return wc_get_orders( array(
            'customer_id' => $user_id,
            'meta_key'    => '_znc_is_parent_order',
            'meta_value'  => 'yes',
            'limit'       => $limit,
            'offset'      => $offset,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ) );
    }

    /**
     * Get child order IDs linked to a parent order.
     *
     * @param int $parent_order_id
     * @return array  [ blog_id => child_order_id ]
     */
    public static function get_child_orders( $parent_order_id ) {
        $parent = wc_get_order( $parent_order_id );
        if ( ! $parent ) return array();

        $children = $parent->get_meta( '_znc_child_orders' );
        return is_array( $children ) ? $children : array();
    }

    /**
     * Get enriched child order details (switches to each subsite).
     *
     * @param int $parent_order_id
     * @return array  [ blog_id => [ 'order_id', 'status', 'total', 'currency', 'items' => [...] ] ]
     */
    public static function get_enriched_child_orders( $parent_order_id ) {
        $children = self::get_child_orders( $parent_order_id );
        $result   = array();

        foreach ( $children as $blog_id => $child_order_id ) {
            switch_to_blog( $blog_id );

            $order = wc_get_order( $child_order_id );
            if ( ! $order ) {
                restore_current_blog();
                continue;
            }

            $items = array();
            foreach ( $order->get_items() as $item ) {
                $product = $item->get_product();
                $items[] = array(
                    'name'     => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'total'    => (float) $item->get_total(),
                    'image'    => $product ? wp_get_attachment_url( $product->get_image_id() ) : '',
                );
            }

            $blog_details = get_blog_details( $blog_id );

            $result[ $blog_id ] = array(
                'order_id'  => $child_order_id,
                'shop_name' => $blog_details ? $blog_details->blogname : "Shop #{$blog_id}",
                'status'    => $order->get_status(),
                'total'     => (float) $order->get_total(),
                'currency'  => $order->get_currency(),
                'symbol'    => get_woocommerce_currency_symbol( $order->get_currency() ),
                'items'     => $items,
                'date'      => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
            );

            restore_current_blog();
        }

        return $result;
    }

    /**
     * Get a user's cross-site order history (all subsites).
     *
     * @param int $user_id
     * @param int $limit
     * @return array
     */
    public static function get_cross_site_orders( $user_id, $limit = 50 ) {
        $settings      = get_site_option( 'znc_network_settings', array() );
        $enrolled_sites = (array) ( $settings['enrolled_sites'] ?? array() );
        $orders         = array();

        foreach ( $enrolled_sites as $blog_id ) {
            switch_to_blog( $blog_id );

            if ( ! function_exists( 'wc_get_orders' ) ) {
                restore_current_blog();
                continue;
            }

            $site_orders = wc_get_orders( array(
                'customer_id' => $user_id,
                'limit'       => $limit,
                'orderby'     => 'date',
                'order'       => 'DESC',
            ) );

            $blog_details = get_blog_details( $blog_id );

            foreach ( $site_orders as $order ) {
                $orders[] = array(
                    'blog_id'   => $blog_id,
                    'shop_name' => $blog_details ? $blog_details->blogname : "Shop #{$blog_id}",
                    'order_id'  => $order->get_id(),
                    'status'    => $order->get_status(),
                    'total'     => (float) $order->get_total(),
                    'currency'  => $order->get_currency(),
                    'symbol'    => get_woocommerce_currency_symbol( $order->get_currency() ),
                    'date'      => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
                    'is_znc'    => $order->get_meta( '_znc_is_child_order' ) === 'yes',
                );
            }

            restore_current_blog();
        }

        // Sort by date descending
        usort( $orders, function( $a, $b ) {
            return strcmp( $b['date'], $a['date'] );
        } );

        return array_slice( $orders, 0, $limit );
    }
}
