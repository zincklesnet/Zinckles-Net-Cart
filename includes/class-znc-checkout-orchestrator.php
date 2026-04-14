<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Checkout_Orchestrator {

    public static function init() {}

    /**
     * Process checkout — 10-step sequence with compensating rollback.
     */
    public static function process( $user_id, $data = array() ) {
        $log = array();

        /* Step 1: Refresh cart */
        $refresh = ZNC_Global_Cart_Merger::refresh_cart( $user_id );
        $log[] = 'Refreshed cart: ' . $refresh['removed'] . ' removed, ' . $refresh['updated'] . ' updated.';

        /* Step 2: Get cart items */
        $items = ZNC_Global_Cart_Store::get_cart( $user_id );
        if ( empty( $items ) ) {
            return array( 'success' => false, 'error' => 'Cart is empty after validation.', 'log' => $log );
        }

        /* Step 3: Build parallel totals */
        $totals = ZNC_Currency_Handler::parallel_totals( $user_id );
        $log[] = 'Totals: ' . wp_json_encode( $totals['per_currency'] ) . ' → ' . $totals['converted_total'] . ' ' . $totals['base_currency'];

        /* Step 4: Validate MyCred if applicable */
        $mycred_deductions = array();
        if ( ! empty( $data['mycred_apply'] ) && is_array( $data['mycred_apply'] ) ) {
            foreach ( $data['mycred_apply'] as $type_slug => $amount ) {
                $amount = abs( (float) $amount );
                if ( $amount <= 0 ) continue;

                if ( ! ZNC_MyCred_Engine::validate_deduction( $user_id, $amount, $type_slug ) ) {
                    return array(
                        'success' => false,
                        'error'   => 'Insufficient ' . $type_slug . ' balance.',
                        'log'     => $log,
                    );
                }
                $mycred_deductions[ $type_slug ] = $amount;
            }
        }
        $log[] = 'MyCred validated: ' . count( $mycred_deductions ) . ' type(s).';

        /* Step 5: Per-subsite price/stock validation */
        $grouped = ZNC_Global_Cart_Store::get_cart_grouped( $user_id );
        foreach ( $grouped as $blog_id => $group ) {
            $current = get_current_blog_id();
            $need_switch = ( (int) $blog_id !== $current );
            if ( $need_switch ) switch_to_blog( $blog_id );

            foreach ( $group['items'] as $item ) {
                $product = wc_get_product( $item['variation_id'] ?: $item['product_id'] );
                if ( ! $product || ! $product->is_in_stock() ) {
                    if ( $need_switch ) restore_current_blog();
                    return array( 'success' => false, 'error' => $item['product_name'] . ' is no longer available.', 'log' => $log );
                }
                if ( $product->managing_stock() && $product->get_stock_quantity() < $item['quantity'] ) {
                    if ( $need_switch ) restore_current_blog();
                    return array( 'success' => false, 'error' => $item['product_name'] . ' — insufficient stock.', 'log' => $log );
                }
            }

            if ( $need_switch ) restore_current_blog();
        }
        $log[] = 'All items validated against origin shops.';

        /* Step 6: Deduct MyCred */
        foreach ( $mycred_deductions as $type_slug => $amount ) {
            ZNC_MyCred_Engine::deduct( $user_id, $amount, $type_slug );
        }
        $log[] = 'MyCred deducted.';

        /* Step 7: Create parent order */
        $parent_order = ZNC_Order_Factory::create_parent_order( $user_id, $items, $totals, $mycred_deductions );
        if ( is_wp_error( $parent_order ) ) {
            /* Rollback MyCred */
            foreach ( $mycred_deductions as $type_slug => $amount ) {
                ZNC_MyCred_Engine::refund( $user_id, $amount, $type_slug );
            }
            return array( 'success' => false, 'error' => 'Failed to create order.', 'log' => $log );
        }
        $log[] = 'Parent order #' . $parent_order . ' created.';

        /* Step 8: Create child orders */
        $child_orders = ZNC_Order_Factory::create_child_orders( $parent_order, $user_id, $grouped );
        $log[] = 'Child orders created: ' . count( $child_orders );

        /* Step 9: Sync inventory */
        foreach ( $grouped as $blog_id => $group ) {
            ZNC_Inventory_Sync::deduct_stock( $blog_id, $group['items'] );
        }
        $log[] = 'Inventory synced.';

        /* Step 10: Clear cart */
        ZNC_Global_Cart_Store::clear_cart( $user_id );
        $log[] = 'Cart cleared.';

        do_action( 'znc_checkout_completed', $parent_order, $child_orders, $user_id );

        return array(
            'success'        => true,
            'parent_order'   => $parent_order,
            'child_orders'   => $child_orders,
            'totals'         => $totals,
            'mycred_deducted'=> $mycred_deductions,
            'log'            => $log,
        );
    }
}
