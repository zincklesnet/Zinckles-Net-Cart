<?php
/**
 * Checkout Orchestrator — 10-step checkout with compensating rollback.
 *
 * v1.2.0: Multi-point-type MyCred support + cross-site validation.
 *
 * @package ZincklesNetCart
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class ZNC_Checkout_Orchestrator {

    private $store;
    private $merger;
    private $currency;
    private $mycred;
    private $orders;
    private $inventory;

    public function __construct(
        ZNC_Global_Cart_Store $store,
        ZNC_Global_Cart_Merger $merger,
        ZNC_Currency_Handler $currency,
        ZNC_MyCred_Engine $mycred,
        ZNC_Order_Factory $orders,
        ZNC_Inventory_Sync $inventory
    ) {
        $this->store     = $store;
        $this->merger    = $merger;
        $this->currency  = $currency;
        $this->mycred    = $mycred;
        $this->orders    = $orders;
        $this->inventory = $inventory;
    }

    public function init() {
        // No hooks — invoked directly from REST endpoint.
    }

    /**
     * Process the full checkout.
     *
     * @param int   $user_id
     * @param array $params  Checkout params (payment_method, zcred_deductions, billing, etc.)
     * @return array|WP_Error
     */
    public function process( $user_id, $params = array() ) {
        $steps_completed = array();

        try {
            // Step 1: Refresh & re-validate all lines.
            $this->merger->refresh_all( $user_id );
            $issues = $this->merger->validate_all( $user_id );
            $steps_completed[] = 'refresh';

            // Step 2: Check for blocking issues.
            $settings   = get_site_option( 'znc_network_settings', array() );
            $validation = $settings['checkout_validation'] ?? 'strict';

            $blocking = array_filter( $issues, function ( $i ) {
                return in_array( $i['issue'], array( 'not_found', 'out_of_stock' ), true );
            } );

            if ( ! empty( $blocking ) ) {
                return new WP_Error( 'validation_failed', 'Some items are no longer available.', array(
                    'status' => 400,
                    'issues' => $blocking,
                ) );
            }

            if ( $validation === 'strict' && ! empty( $issues ) ) {
                return new WP_Error( 'validation_failed', 'Price or stock changes detected.', array(
                    'status' => 400,
                    'issues' => $issues,
                ) );
            }
            $steps_completed[] = 'validate';

            // Step 3: Build parallel totals.
            $items  = $this->store->get_cart( $user_id );
            $totals = $this->currency->parallel_totals( $items );
            $steps_completed[] = 'totals';

            // Step 4: Validate MyCred deductions (multi-type).
            $zcred_deductions = $params['zcred_deductions'] ?? array();
            $total_zcred_value = 0.0;

            foreach ( $zcred_deductions as $point_type => $amount ) {
                $amount = (float) $amount;
                if ( $amount <= 0 ) {
                    continue;
                }

                if ( ! $this->mycred->validate_deduction( $user_id, $point_type, $amount ) ) {
                    return new WP_Error( 'insufficient_points', sprintf(
                        'Insufficient %s balance.',
                        $point_type
                    ), array( 'status' => 400 ) );
                }

                // Get exchange rate for this type.
                $types = $this->mycred->get_enabled_types();
                $rate  = isset( $types[ $point_type ] ) ? (float) $types[ $point_type ]['exchange_rate'] : 1.0;
                $total_zcred_value += $amount * $rate;
            }

            // Verify ZCred value doesn't exceed order total.
            if ( $total_zcred_value > $totals['converted_total'] ) {
                return new WP_Error( 'zcred_exceeds_total', 'ZCred value exceeds order total.', array( 'status' => 400 ) );
            }

            $monetary_total = $totals['converted_total'] - $total_zcred_value;
            $steps_completed[] = 'mycred_validate';

            // Step 5: Deduct MyCred points.
            $mycred_results = array();
            foreach ( $zcred_deductions as $point_type => $amount ) {
                $amount = (float) $amount;
                if ( $amount <= 0 ) {
                    continue;
                }

                $result = $this->mycred->deduct( $user_id, $point_type, $amount, 'znc_checkout', array(
                    'order_id' => 'pending',
                ) );

                if ( is_wp_error( $result ) ) {
                    // Rollback previous deductions.
                    foreach ( $mycred_results as $prev ) {
                        $this->mycred->refund( $user_id, $prev['point_type'], $prev['deducted'] );
                    }
                    return $result;
                }

                $mycred_results[] = $result;
            }
            $steps_completed[] = 'mycred_deduct';

            // Step 6: Create parent order on main site.
            $shops       = $this->store->get_cart( $user_id, 'shop' );
            $parent_order = $this->orders->create_parent_order( $user_id, $shops, array(
                'totals'           => $totals,
                'monetary_total'   => $monetary_total,
                'zcred_deductions' => $zcred_deductions,
                'mycred_results'   => $mycred_results,
                'billing'          => $params['billing'] ?? array(),
                'shipping'         => $params['shipping'] ?? array(),
                'payment_method'   => $params['payment_method'] ?? '',
            ) );

            if ( is_wp_error( $parent_order ) ) {
                // Rollback MyCred.
                foreach ( $mycred_results as $prev ) {
                    $this->mycred->refund( $user_id, $prev['point_type'], $prev['deducted'] );
                }
                return $parent_order;
            }
            $steps_completed[] = 'parent_order';

            // Step 7: Create child orders on each subsite.
            $child_orders = $this->orders->create_child_orders(
                $parent_order['order_id'],
                $user_id,
                $shops
            );
            $steps_completed[] = 'child_orders';

            // Step 8: Sync inventory on each subsite.
            $inventory_results = $this->inventory->deduct_all( $shops );
            $steps_completed[] = 'inventory';

            // Step 9: Clear global cart.
            $this->store->clear_cart( $user_id );
            $steps_completed[] = 'clear_cart';

            // Step 10: Fire completion action.
            do_action( 'znc_checkout_completed', array(
                'user_id'        => $user_id,
                'parent_order'   => $parent_order,
                'child_orders'   => $child_orders,
                'totals'         => $totals,
                'mycred_results' => $mycred_results,
            ) );
            $steps_completed[] = 'complete';

            return array(
                'success'          => true,
                'parent_order_id'  => $parent_order['order_id'],
                'child_orders'     => $child_orders,
                'totals'           => $totals,
                'monetary_charged' => $monetary_total,
                'zcred_deducted'   => $mycred_results,
                'steps_completed'  => $steps_completed,
            );

        } catch ( \Exception $e ) {
            return new WP_Error( 'checkout_error', $e->getMessage(), array(
                'status'          => 500,
                'steps_completed' => $steps_completed,
            ) );
        }
    }
}
