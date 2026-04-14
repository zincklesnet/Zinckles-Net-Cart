<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Order_Factory {

    public static function init() {}

    public static function create_parent_order( $user_id, $items, $totals, $mycred_deductions = array() ) {
        if ( ! function_exists( 'wc_create_order' ) ) {
            return new WP_Error( 'no_wc', 'WooCommerce not available on checkout host.' );
        }

        $order = wc_create_order( array( 'customer_id' => $user_id ) );
        if ( is_wp_error( $order ) ) return $order;

        foreach ( $items as $item ) {
            $fee = new WC_Order_Item_Fee();
            $fee->set_name( sprintf( '[%s] %s (x%d)', $item['shop_name'], $item['product_name'], $item['quantity'] ) );
            $fee->set_total( (float) $item['product_price'] * (int) $item['quantity'] );
            $fee->add_meta_data( '_znc_source_blog_id', $item['source_blog_id'] );
            $fee->add_meta_data( '_znc_product_id', $item['product_id'] );
            $fee->add_meta_data( '_znc_variation_id', $item['variation_id'] );
            $fee->add_meta_data( '_znc_quantity', $item['quantity'] );
            $fee->add_meta_data( '_znc_unit_price', $item['product_price'] );
            $fee->add_meta_data( '_znc_currency', $item['currency'] );
            $fee->add_meta_data( '_znc_shop_name', $item['shop_name'] );
            $fee->add_meta_data( '_znc_product_sku', $item['product_sku'] );
            $order->add_item( $fee );
        }

        $order->set_currency( $totals['base_currency'] );
        $order->set_total( $totals['converted_total'] );

        $order->update_meta_data( '_znc_is_parent', 'yes' );
        $order->update_meta_data( '_znc_totals', $totals );
        $order->update_meta_data( '_znc_mycred_deductions', $mycred_deductions );

        $order->update_status( 'processing', 'Zinckles Net Cart parent order.' );
        $order->save();

        return $order->get_id();
    }

    public static function create_child_orders( $parent_order_id, $user_id, $grouped ) {
        global $wpdb;
        $host    = new ZNC_Checkout_Host();
        $host_id = $host->get_host_id();
        $results = array();

        foreach ( $grouped as $blog_id => $group ) {
            $blog_id = (int) $blog_id;
            if ( $blog_id === $host_id ) continue;

            $current     = get_current_blog_id();
            $need_switch = ( $current !== $blog_id );

            if ( $need_switch ) switch_to_blog( $blog_id );

            if ( function_exists( 'wc_create_order' ) ) {
                $child = wc_create_order( array( 'customer_id' => $user_id ) );

                if ( ! is_wp_error( $child ) ) {
                    foreach ( $group['items'] as $item ) {
                        $product = wc_get_product( $item['variation_id'] ?: $item['product_id'] );
                        if ( $product ) {
                            $child->add_product( $product, $item['quantity'] );
                        }
                    }

                    $child->set_currency( $group['currency'] );
                    $child->calculate_totals();
                    $child->update_meta_data( '_znc_parent_order_id', $parent_order_id );
                    $child->update_meta_data( '_znc_parent_blog_id', $host_id );
                    $child->update_status( 'processing', 'Zinckles Net Cart child order.' );
                    $child->save();

                    $child_id = $child->get_id();
                    $results[ $blog_id ] = $child_id;
                }
            }

            if ( $need_switch ) restore_current_blog();

            /* Record in order map */
            if ( isset( $child_id ) ) {
                $map_current = get_current_blog_id();
                $map_switch  = ( $map_current !== $host_id );
                if ( $map_switch ) switch_to_blog( $host_id );

                $wpdb->insert( $wpdb->prefix . 'znc_order_map', array(
                    'parent_order_id' => $parent_order_id,
                    'child_order_id'  => $child_id,
                    'child_blog_id'   => $blog_id,
                    'status'          => 'processing',
                    'created_at'      => current_time( 'mysql', true ),
                ) );

                if ( $map_switch ) restore_current_blog();
            }
        }

        return $results;
    }
}
