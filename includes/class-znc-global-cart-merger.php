<?php
/**
 * Global Cart Merger — v1.4.1
 * Merges items from multiple subsites, handles currency grouping.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Global_Cart_Merger {

    private $store;

    public function __construct( ZNC_Global_Cart_Store $store ) {
        $this->store = $store;
    }

    public function init() {}

    public function get_merged_cart( $user_id ) {
        $items    = $this->store->get_items( $user_id );
        $settings = get_site_option( 'znc_network_settings', array() );
        $base     = isset( $settings['base_currency'] ) ? $settings['base_currency'] : 'USD';
        $mixed    = ! empty( $settings['mixed_currency'] );

        $result = array(
            'items'          => $items,
            'base_currency'  => $base,
            'mixed_currency' => $mixed,
            'totals'         => $this->calculate_totals( $items, $base, $mixed ),
            'by_shop'        => $this->group_by_shop( $items ),
            'by_currency'    => $this->group_by_currency( $items ),
            'item_count'     => array_sum( wp_list_pluck( $items, 'quantity' ) ),
        );

        return $result;
    }

    private function calculate_totals( $items, $base, $mixed ) {
        $totals = array();
        foreach ( $items as $item ) {
            $cur = $item['currency'] ?: $base;
            if ( ! isset( $totals[ $cur ] ) ) {
                $totals[ $cur ] = array( 'subtotal' => 0, 'items' => 0 );
            }
            $totals[ $cur ]['subtotal'] += (float) $item['line_total'];
            $totals[ $cur ]['items']    += (int) $item['quantity'];
        }
        return $totals;
    }

    private function group_by_shop( $items ) {
        $groups = array();
        foreach ( $items as $item ) {
            $key = $item['blog_id'];
            if ( ! isset( $groups[ $key ] ) ) {
                $groups[ $key ] = array(
                    'blog_id'   => $key,
                    'shop_name' => $item['shop_name'],
                    'shop_url'  => $item['shop_url'],
                    'items'     => array(),
                    'subtotal'  => 0,
                );
            }
            $groups[ $key ]['items'][]   = $item;
            $groups[ $key ]['subtotal'] += (float) $item['line_total'];
        }
        return $groups;
    }

    private function group_by_currency( $items ) {
        $groups = array();
        foreach ( $items as $item ) {
            $cur = $item['currency'] ?: 'USD';
            if ( ! isset( $groups[ $cur ] ) ) {
                $groups[ $cur ] = array( 'currency' => $cur, 'items' => array(), 'subtotal' => 0 );
            }
            $groups[ $cur ]['items'][]   = $item;
            $groups[ $cur ]['subtotal'] += (float) $item['line_total'];
        }
        return $groups;
    }
}
