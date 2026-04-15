<?php
/**
 * Order Factory — Creates parent/child orders with proper payment handling.
 *
 * Replaces the broken inline order creation in the old checkout handler.
 * Creates child orders on subsites and a parent order on the checkout host,
 * with WooCommerce payment gateway integration.
 *
 * @package ZincklesNetCart
 * @since   1.7.0
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Order_Factory {

    /**
     * Create child orders on each subsite and a parent order on the host.
     *
     * @param int   $user_id
     * @param array $enriched  Enriched cart from ZNC_Cart_Renderer.
     * @param array $billing   Billing details.
     * @param string $order_notes
     * @return array|WP_Error  [ 'parent_id' => int, 'children' => [ blog_id => order_id ] ]
     */
    public static function create_orders( $user_id, $enriched, $billing, $order_notes = '' ) {
        if ( empty( $enriched ) ) {
            return new WP_Error( 'znc_empty_cart', __( 'Cart is empty.', 'zinckles-net-cart' ) );
        }

        $child_orders   = array();
        $currency_totals = array(); // code => total

        /* ── Create child orders on each subsite ── */
        foreach ( $enriched as $blog_id => $shop ) {
            switch_to_blog( $blog_id );

            if ( ! function_exists( 'wc_create_order' ) ) {
                restore_current_blog();
                continue;
            }

            try {
                $order = wc_create_order( array(
                    'customer_id' => $user_id,
                    'status'      => 'pending', // Not 'processing' — wait for payment
                ) );

                if ( is_wp_error( $order ) ) {
                    restore_current_blog();
                    continue;
                }

                foreach ( $shop['items'] as $item ) {
                    $pid     = $item['variation_id'] ?: $item['product_id'];
                    $product = wc_get_product( $pid );
                    if ( $product ) {
                        $order->add_product( $product, $item['quantity'] );
                    }
                }

                // Set billing address
                $name_parts = explode( ' ', $billing['name'], 2 );
                $order->set_address( array(
                    'first_name' => $name_parts[0] ?? '',
                    'last_name'  => $name_parts[1] ?? '',
                    'email'      => $billing['email'],
                    'address_1'  => $billing['address'],
                    'city'       => $billing['city'],
                    'postcode'   => $billing['postcode'],
                    'country'    => $billing['country'] ?? 'CA',
                ), 'billing' );

                if ( $order_notes ) {
                    $order->add_order_note( $order_notes, false, false );
                }

                $order->add_order_note(
                    sprintf( 'Net Cart child order — checkout host: %s', network_home_url() ),
                    false, false
                );

                $order->update_meta_data( '_znc_is_child_order', 'yes' );
                $order->calculate_totals();
                $order->save();

                $child_orders[ $blog_id ] = $order->get_id();

                // Track per-currency totals
                $code = $shop['currency'] ?? 'USD';
                if ( ! isset( $currency_totals[ $code ] ) ) {
                    $currency_totals[ $code ] = 0;
                }
                $currency_totals[ $code ] += (float) $order->get_total();

            } catch ( \Exception $e ) {
                if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
                    error_log( '[ZNC-OrderFactory] Error on blog ' . $blog_id . ': ' . $e->getMessage() );
                }
            }

            restore_current_blog();
        }

        if ( empty( $child_orders ) ) {
            return new WP_Error( 'znc_no_children', __( 'No child orders could be created.', 'zinckles-net-cart' ) );
        }

        /* ── Create parent order on checkout host ── */
        if ( ! function_exists( 'wc_create_order' ) ) {
            return new WP_Error( 'znc_no_wc', __( 'WooCommerce not available on host.', 'zinckles-net-cart' ) );
        }

        $parent = wc_create_order( array(
            'customer_id' => $user_id,
            'status'      => 'pending',
        ) );

        if ( is_wp_error( $parent ) ) {
            return $parent;
        }

        $name_parts = explode( ' ', $billing['name'], 2 );
        $parent->set_address( array(
            'first_name' => $name_parts[0] ?? '',
            'last_name'  => $name_parts[1] ?? '',
            'email'      => $billing['email'],
            'address_1'  => $billing['address'],
            'city'       => $billing['city'],
            'postcode'   => $billing['postcode'],
            'country'    => $billing['country'] ?? 'CA',
        ), 'billing' );

        // Add fee lines for each subsite's total (keeps WC accounting clean)
        foreach ( $enriched as $blog_id => $shop ) {
            if ( ! isset( $child_orders[ $blog_id ] ) ) continue;

            $fee = new WC_Order_Item_Fee();
            $fee->set_name( sprintf( '%s — Order #%d', $shop['name'], $child_orders[ $blog_id ] ) );
            $fee->set_total( $shop['subtotal'] );
            $parent->add_item( $fee );
        }

        // Build child order reference note
        $child_refs = array();
        foreach ( $child_orders as $bid => $oid ) {
            $details = get_blog_details( $bid );
            $child_refs[] = ( $details ? $details->blogname : "Blog $bid" ) . " #$oid";
        }
        $parent->add_order_note(
            sprintf( 'Net Cart parent order — children: %s', implode( ', ', $child_refs ) ),
            false, false
        );

        // Store metadata
        $parent->update_meta_data( '_znc_child_orders', $child_orders );
        $parent->update_meta_data( '_znc_is_parent_order', 'yes' );
        $parent->update_meta_data( '_znc_currency_totals', $currency_totals );

        foreach ( $child_orders as $bid => $oid ) {
            $parent->update_meta_data( '_znc_child_order_' . $bid, $oid );
        }

        $parent->calculate_totals();
        $parent->save();

        // Link parent back to children
        foreach ( $child_orders as $bid => $oid ) {
            switch_to_blog( $bid );
            $child = wc_get_order( $oid );
            if ( $child ) {
                $child->update_meta_data( '_znc_parent_order_id', $parent->get_id() );
                $child->update_meta_data( '_znc_parent_blog_id', get_main_site_id() );
                $child->save();
            }
            restore_current_blog();
        }

        return array(
            'parent_id'       => $parent->get_id(),
            'children'        => $child_orders,
            'currency_totals' => $currency_totals,
        );
    }

    /**
     * Mark child orders as paid when the parent order payment completes.
     *
     * @param int $parent_order_id
     */
    public static function complete_child_orders( $parent_order_id ) {
        $parent = wc_get_order( $parent_order_id );
        if ( ! $parent ) return;

        $children = $parent->get_meta( '_znc_child_orders' );
        if ( ! is_array( $children ) ) return;

        foreach ( $children as $blog_id => $child_order_id ) {
            switch_to_blog( $blog_id );
            $child = wc_get_order( $child_order_id );
            if ( $child && $child->get_status() === 'pending' ) {
                $child->set_status( 'processing' );
                $child->add_order_note(
                    sprintf( 'Payment completed via parent order #%d on %s', $parent_order_id, network_home_url() ),
                    false, false
                );
                $child->save();
            }
            restore_current_blog();
        }
    }
}
