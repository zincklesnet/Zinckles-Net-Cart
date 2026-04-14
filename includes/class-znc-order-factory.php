<?php
/**
 * Order Factory — creates parent + child orders with mapping table.
 *
 * @package ZincklesNetCart
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class ZNC_Order_Factory {

    public function init() {
        // No hooks — invoked by checkout orchestrator.
    }

    /**
     * Create the parent order on the main site.
     */
    public function create_parent_order( $user_id, $shops, $checkout_data ) {
        if ( ! function_exists( 'wc_create_order' ) ) {
            return new WP_Error( 'wc_missing', 'WooCommerce is required on the main site.' );
        }

        $order = wc_create_order( array(
            'customer_id' => $user_id,
            'status'      => 'processing',
        ) );

        if ( is_wp_error( $order ) ) {
            return $order;
        }

        // Add line items from all shops.
        foreach ( $shops as $shop ) {
            foreach ( $shop['items'] as $item ) {
                $order->add_item( new WC_Order_Item_Product( array(
                    'name'     => sprintf( '[%s] %s', $shop['shop_name'], $item['product_name'] ),
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['line_total'],
                    'total'    => $item['line_total'],
                ) ) );
            }
        }

        // Set order meta.
        $order->update_meta_data( '_znc_order_type', 'parent' );
        $order->update_meta_data( '_znc_totals', $checkout_data['totals'] ?? array() );
        $order->update_meta_data( '_znc_zcred_deductions', $checkout_data['zcred_deductions'] ?? array() );
        $order->update_meta_data( '_znc_mycred_results', $checkout_data['mycred_results'] ?? array() );
        $order->update_meta_data( '_znc_monetary_total', $checkout_data['monetary_total'] ?? 0 );
        $order->update_meta_data( '_znc_shop_count', count( $shops ) );

        if ( ! empty( $checkout_data['payment_method'] ) ) {
            $order->set_payment_method( $checkout_data['payment_method'] );
        }

        $order->set_currency( $checkout_data['totals']['base_currency'] ?? 'CAD' );
        $order->set_total( $checkout_data['monetary_total'] ?? 0 );

        // Billing/shipping.
        if ( ! empty( $checkout_data['billing'] ) ) {
            foreach ( $checkout_data['billing'] as $key => $val ) {
                $setter = 'set_billing_' . $key;
                if ( method_exists( $order, $setter ) ) {
                    $order->$setter( $val );
                }
            }
        }

        $order->add_order_note( sprintf(
            'Net Cart parent order — %d shops, %s currency mode.',
            count( $shops ),
            ( $checkout_data['totals']['is_mixed'] ?? false ) ? 'mixed' : 'single'
        ) );

        $order->save();

        return array(
            'order_id' => $order->get_id(),
            'total'    => $order->get_total(),
            'status'   => $order->get_status(),
        );
    }

    /**
     * Create child orders on each subsite via switch_to_blog.
     */
    public function create_child_orders( $parent_order_id, $user_id, $shops ) {
        $results = array();

        global $wpdb;
        $map_table = $wpdb->prefix . 'znc_order_map';

        foreach ( $shops as $shop ) {
            $blog_id = $shop['blog_id'];

            switch_to_blog( $blog_id );

            if ( ! function_exists( 'wc_create_order' ) ) {
                restore_current_blog();
                $results[] = array(
                    'blog_id' => $blog_id,
                    'success' => false,
                    'error'   => 'WooCommerce not active.',
                );
                continue;
            }

            $child_order = wc_create_order( array(
                'customer_id' => $user_id,
                'status'      => 'processing',
            ) );

            if ( is_wp_error( $child_order ) ) {
                restore_current_blog();
                $results[] = array(
                    'blog_id' => $blog_id,
                    'success' => false,
                    'error'   => $child_order->get_error_message(),
                );
                continue;
            }

            $subtotal = 0;
            foreach ( $shop['items'] as $item ) {
                $product = wc_get_product( $item['variation_id'] ?: $item['product_id'] );
                if ( $product ) {
                    $child_order->add_product( $product, $item['quantity'], array(
                        'subtotal' => $item['line_total'],
                        'total'    => $item['line_total'],
                    ) );
                }
                $subtotal += $item['line_total'];
            }

            $child_order->set_currency( $shop['currency'] );
            $child_order->calculate_totals();
            $child_order->update_meta_data( '_znc_order_type', 'child' );
            $child_order->update_meta_data( '_znc_parent_order_id', $parent_order_id );
            $child_order->update_meta_data( '_znc_parent_site_id', get_main_site_id() );
            $child_order->add_order_note( sprintf(
                'Net Cart child order — parent #%d on main site.',
                $parent_order_id
            ) );
            $child_order->save();

            $child_id = $child_order->get_id();

            restore_current_blog();

            // Record in order map on main site.
            $wpdb->insert( $map_table, array(
                'parent_order_id' => $parent_order_id,
                'child_order_id'  => $child_id,
                'child_blog_id'   => $blog_id,
                'currency'        => $shop['currency'],
                'subtotal'        => $subtotal,
                'status'          => 'processing',
                'created_at'      => current_time( 'mysql' ),
            ) );

            $results[] = array(
                'blog_id'        => $blog_id,
                'shop_name'      => $shop['shop_name'],
                'child_order_id' => $child_id,
                'currency'       => $shop['currency'],
                'subtotal'       => $subtotal,
                'success'        => true,
            );
        }

        return $results;
    }
}
