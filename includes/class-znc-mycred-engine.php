<?php
/**
 * MyCred Engine — multi-point-type support.
 *
 * v1.2.0: Detects ALL registered MyCred point types, not just 'zcreds'.
 * Each type has its own exchange rate, max %, and enabled state.
 *
 * @package ZincklesNetCart
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class ZNC_MyCred_Engine {

    public function init() {
        add_filter( 'znc_mycred_config', array( $this, 'get_config' ) );
    }

    /**
     * Check if MyCred is available at all.
     */
    public function is_available() {
        return function_exists( 'mycred' ) && function_exists( 'mycred_get_types' );
    }

    /**
     * Get all enabled point types with their settings.
     */
    public function get_enabled_types() {
        if ( ! $this->is_available() ) {
            return array();
        }

        $settings = get_site_option( 'znc_network_settings', array() );

        if ( empty( $settings['mycred_enabled'] ) ) {
            return array();
        }

        $configured_types = (array) ( $settings['mycred_point_types'] ?? array() );

        if ( empty( $configured_types ) ) {
            // Fallback: use registered MyCred types with default settings.
            $registered = mycred_get_types();
            foreach ( $registered as $slug => $label ) {
                $configured_types[ $slug ] = array(
                    'slug'          => $slug,
                    'label'         => $label,
                    'exchange_rate' => (float) ( $settings['mycred_exchange_rate'] ?? 1.0 ),
                    'max_percent'   => (int) ( $settings['mycred_max_percent'] ?? 50 ),
                    'enabled'       => true,
                );
            }
        }

        // Filter to only enabled types.
        return array_filter( $configured_types, function ( $type ) {
            return ! empty( $type['enabled'] );
        } );
    }

    /**
     * Get a user's balance for a specific point type.
     */
    public function get_balance( $user_id, $point_type = 'mycred_default' ) {
        if ( ! $this->is_available() ) {
            return 0;
        }

        $mycred = mycred( $point_type );
        if ( ! $mycred ) {
            return 0;
        }

        return (float) $mycred->get_users_balance( $user_id );
    }

    /**
     * Get balances for ALL enabled point types for a user.
     */
    public function get_all_balances( $user_id ) {
        $types    = $this->get_enabled_types();
        $balances = array();

        foreach ( $types as $slug => $type ) {
            $balances[ $slug ] = array(
                'slug'          => $slug,
                'label'         => $type['label'] ?? $slug,
                'balance'       => $this->get_balance( $user_id, $slug ),
                'exchange_rate' => (float) ( $type['exchange_rate'] ?? 1.0 ),
                'max_percent'   => (int) ( $type['max_percent'] ?? 50 ),
            );
        }

        return $balances;
    }

    /**
     * Calculate parallel totals for all point types against an order total.
     */
    public function get_parallel_totals( $user_id, $order_total, $base_currency = 'CAD' ) {
        $balances = $this->get_all_balances( $user_id );
        $totals   = array();

        foreach ( $balances as $slug => $data ) {
            $exchange_rate     = $data['exchange_rate'];
            $max_percent       = $data['max_percent'];
            $balance           = $data['balance'];
            $max_applicable    = $order_total * ( $max_percent / 100 );
            $balance_in_currency = $balance * $exchange_rate;
            $applicable        = min( $max_applicable, $balance_in_currency );
            $points_to_deduct  = $exchange_rate > 0 ? $applicable / $exchange_rate : 0;

            $totals[ $slug ] = array(
                'slug'              => $slug,
                'label'             => $data['label'],
                'balance'           => $balance,
                'exchange_rate'     => $exchange_rate,
                'max_percent'       => $max_percent,
                'max_applicable'    => round( $max_applicable, 2 ),
                'balance_value'     => round( $balance_in_currency, 2 ),
                'applicable_value'  => round( $applicable, 2 ),
                'points_to_deduct'  => floor( $points_to_deduct ),
                'remaining_monetary' => round( $order_total - $applicable, 2 ),
            );
        }

        return $totals;
    }

    /**
     * Validate that a user has enough points for a deduction.
     */
    public function validate_deduction( $user_id, $point_type, $amount ) {
        $balance = $this->get_balance( $user_id, $point_type );
        return $balance >= $amount;
    }

    /**
     * Deduct points from a user.
     */
    public function deduct( $user_id, $point_type, $amount, $reference = 'znc_checkout', $data = array() ) {
        if ( ! $this->is_available() ) {
            return new WP_Error( 'mycred_unavailable', 'MyCred is not available.' );
        }

        $mycred = mycred( $point_type );
        if ( ! $mycred ) {
            return new WP_Error( 'invalid_type', 'Invalid point type: ' . $point_type );
        }

        if ( ! $this->validate_deduction( $user_id, $point_type, $amount ) ) {
            return new WP_Error( 'insufficient_balance', sprintf(
                'Insufficient %s balance. Required: %s, Available: %s',
                $point_type, $amount, $this->get_balance( $user_id, $point_type )
            ) );
        }

        $entry = sprintf(
            'Net Cart checkout — Order #%s',
            $data['order_id'] ?? 'unknown'
        );

        $mycred->add_creds(
            $reference,
            $user_id,
            0 - abs( $amount ),
            $entry,
            $data['order_id'] ?? 0,
            $data
        );

        return array(
            'deducted'    => $amount,
            'point_type'  => $point_type,
            'new_balance' => $this->get_balance( $user_id, $point_type ),
        );
    }

    /**
     * Refund points to a user (compensating action on checkout failure).
     */
    public function refund( $user_id, $point_type, $amount, $data = array() ) {
        if ( ! $this->is_available() ) {
            return new WP_Error( 'mycred_unavailable', 'MyCred is not available.' );
        }

        $mycred = mycred( $point_type );
        if ( ! $mycred ) {
            return new WP_Error( 'invalid_type', 'Invalid point type: ' . $point_type );
        }

        $entry = sprintf(
            'Net Cart refund — Order #%s',
            $data['order_id'] ?? 'unknown'
        );

        $mycred->add_creds(
            'znc_refund',
            $user_id,
            abs( $amount ),
            $entry,
            $data['order_id'] ?? 0,
            $data
        );

        return array(
            'refunded'    => $amount,
            'point_type'  => $point_type,
            'new_balance' => $this->get_balance( $user_id, $point_type ),
        );
    }

    /**
     * Get config for filters.
     */
    public function get_config() {
        return array(
            'available'    => $this->is_available(),
            'enabled_types' => $this->get_enabled_types(),
        );
    }
}
