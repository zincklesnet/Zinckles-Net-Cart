<?php
defined( 'ABSPATH' ) || exit;
class ZNC_Order_Factory {
    public function init() {}
    public function create_parent( $user_id, $items, $totals ) {
        if ( ! function_exists( 'wc_create_order' ) ) return false;
        $order = wc_create_order( array( 'customer_id' => $user_id ) );
        if ( is_wp_error( $order ) ) return false;
        foreach ( $items as $item ) {
            $fee = new WC_Order_Item_Fee();
            $fee->set_name( ( $item->shop_name ?? 'Shop' ) . ': ' . ( $item->product_name ?? 'Product' ) . ' x' . ( $item->quantity ?? 1 ) );
            $fee->set_total( (float)( $item->price ?? 0 ) * (int)( $item->quantity ?? 1 ) );
            $order->add_item( $fee );
        }
        $order->update_meta_data( '_znc_is_parent', 'yes' );
        $order->update_meta_data( '_znc_currencies', wp_json_encode( $totals ) );
        $order->set_status( 'processing' );
        $order->calculate_totals();
        $order->save();
        return $order->get_id();
    }
    public function create_child( $parent_order_id, $blog_id, $items ) {
        $host = new ZNC_Checkout_Host();
        switch_to_blog( $blog_id );
        if ( ! function_exists( 'wc_create_order' ) ) { restore_current_blog(); return false; }
        $user_id = get_current_user_id();
        $order = wc_create_order( array( 'customer_id' => $user_id ) );
        if ( is_wp_error( $order ) ) { restore_current_blog(); return false; }
        foreach ( $items as $item ) {
            $product = wc_get_product( $item->variation_id ?: $item->product_id );
            if ( $product ) $order->add_product( $product, (int)( $item->quantity ?? 1 ) );
        }
        $order->update_meta_data( '_znc_parent_order_id', $parent_order_id );
        $order->update_meta_data( '_znc_parent_blog_id', $host->get_host_id() );
        $order->set_status( 'processing' );
        $order->calculate_totals();
        $order->save();
        $child_id = $order->get_id();
        restore_current_blog();

        // Record in order map
        global $wpdb;
        switch_to_blog( $host->get_host_id() );
        $wpdb->insert( $wpdb->prefix . 'znc_order_map', array(
            'parent_order_id' => $parent_order_id,
            'child_order_id'  => $child_id,
            'child_blog_id'   => $blog_id,
            'status'          => 'processing',
            'created_at'      => current_time( 'mysql', true ),
        ) );
        restore_current_blog();
        return $child_id;
    }
}
