<?php
/**
 * Order Factory — v1.4.1
 * Creates WC orders on subsites from global cart items.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Order_Factory {

    public function init() {}

    public static function create_order_on_subsite( $blog_id, $user_id, $items ) {
        switch_to_blog( $blog_id );

        if ( ! function_exists( 'wc_create_order' ) ) {
            restore_current_blog();
            return new WP_Error( 'no_wc', 'WooCommerce not active on blog ' . $blog_id );
        }

        $order = wc_create_order( array( 'customer_id' => $user_id ) );
        if ( is_wp_error( $order ) ) {
            restore_current_blog();
            return $order;
        }

        $currency = 'USD';
        foreach ( $items as $item ) {
            $product = wc_get_product( $item['product_id'] );
            if ( $product ) {
                $order->add_product( $product, (int) $item['quantity'] );
            } else {
                // Product may have been deleted — add as fee line
                $fee = new WC_Order_Item_Fee();
                $fee->set_name( $item['product_name'] ?: 'Product #' . $item['product_id'] );
                $fee->set_amount( $item['line_total'] );
                $fee->set_total( $item['line_total'] );
                $order->add_item( $fee );
            }
            $currency = $item['currency'] ?: 'USD';
        }

        $order->set_currency( $currency );
        $order->calculate_totals();
        $order->update_status( 'processing', __( 'Created by Zinckles Net Cart global checkout.', 'zinckles-net-cart' ) );
        $order->update_meta_data( '_znc_global_order', 1 );
        $order->update_meta_data( '_znc_checkout_blog', get_main_site_id() );
        $order->update_meta_data( '_znc_currency_type', $currency );

        // Detect if points purchase
        $point_currencies = array( 'MYC' );
        $settings = get_site_option( 'znc_network_settings', array() );
        if ( ! empty( $settings['mycred_types_config'] ) ) {
            foreach ( $settings['mycred_types_config'] as $slug => $cfg ) {
                $point_currencies[] = strtoupper( $slug );
            }
        }
        if ( in_array( strtoupper( $currency ), $point_currencies, true ) ) {
            $order->update_meta_data( '_znc_payment_type', 'points' );
            $order->update_meta_data( '_znc_point_type', $currency );
        } else {
            $order->update_meta_data( '_znc_payment_type', 'currency' );
        }

        $order->save();
        $order_id = $order->get_id();

        restore_current_blog();
        return $order_id;
    }

    public static function get_order_meta_summary( $order ) {
        return array(
            'is_global'    => (bool) $order->get_meta( '_znc_global_order' ),
            'payment_type' => $order->get_meta( '_znc_payment_type' ) ?: 'currency',
            'point_type'   => $order->get_meta( '_znc_point_type' ) ?: '',
            'currency'     => $order->get_meta( '_znc_currency_type' ) ?: $order->get_currency(),
            'source_blog'  => $order->get_meta( '_znc_checkout_blog' ) ?: '',
        );
    }
}
