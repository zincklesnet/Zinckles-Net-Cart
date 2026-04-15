<?php
/**
 * Checkout Orchestrator — v1.4.1
 * Splits global cart into per-subsite orders, manages payment flow.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Checkout_Orchestrator {

    private $store;
    private $host;

    public function __construct( ZNC_Global_Cart_Store $store, ZNC_Checkout_Host $host ) {
        $this->store = $store;
        $this->host  = $host;
    }

    public function init() {
        if ( ! $this->host->is_current_site_host() ) return;
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_order_processed' ), 10, 3 );
        add_action( 'wp_ajax_znc_process_checkout', array( $this, 'ajax_process_checkout' ) );
    }

    public function ajax_process_checkout() {
        check_ajax_referer( 'znc_checkout', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( 'Not logged in.' );

        $user_id = get_current_user_id();
        $items   = $this->store->get_items( $user_id );
        if ( empty( $items ) ) wp_send_json_error( 'Cart is empty.' );

        $grouped = $this->store->get_items_grouped( $user_id );
        $results = array();

        foreach ( $grouped as $blog_id => $group ) {
            $result = $this->create_subsite_order( $user_id, $blog_id, $group );
            $results[ $blog_id ] = $result;
        }

        $has_errors = false;
        foreach ( $results as $r ) {
            if ( isset( $r['error'] ) ) { $has_errors = true; break; }
        }

        if ( ! $has_errors ) {
            $this->store->clear( $user_id );
        }

        wp_send_json_success( array(
            'orders'     => $results,
            'has_errors' => $has_errors,
        ) );
    }

    private function create_subsite_order( $user_id, $blog_id, $group ) {
        $blog_id = (int) $blog_id;

        switch_to_blog( $blog_id );

        if ( ! function_exists( 'wc_create_order' ) ) {
            restore_current_blog();
            return array( 'error' => 'WooCommerce not active on blog ' . $blog_id );
        }

        try {
            $order = wc_create_order( array( 'customer_id' => $user_id ) );
            if ( is_wp_error( $order ) ) {
                restore_current_blog();
                return array( 'error' => $order->get_error_message() );
            }

            foreach ( $group['items'] as $item ) {
                $product = wc_get_product( $item['product_id'] );
                if ( $product ) {
                    $order->add_product( $product, $item['quantity'] );
                } else {
                    $order->add_fee( new WC_Order_Item_Fee(), array(
                        'name'      => $item['product_name'],
                        'total'     => $item['line_total'],
                        'tax_class' => '',
                    ) );
                }
            }

            $order->set_currency( $group['items'][0]['currency'] ?? 'USD' );
            $order->calculate_totals();
            $order->update_status( 'processing', 'Order created via Zinckles Net Cart' );
            $order->add_order_note( 'Global Cart checkout — Net Cart v1.4.1' );
            $order->update_meta_data( '_znc_global_order', 1 );
            $order->update_meta_data( '_znc_source_blog', get_main_site_id() );
            $order->save();

            $order_id = $order->get_id();
            restore_current_blog();

            // Map to parent order
            $this->save_order_map( 0, $order_id, $blog_id );

            return array( 'order_id' => $order_id, 'blog_id' => $blog_id, 'status' => 'processing' );

        } catch ( \Exception $e ) {
            restore_current_blog();
            return array( 'error' => $e->getMessage() );
        }
    }

    private function save_order_map( $parent_id, $child_id, $child_blog_id ) {
        global $wpdb;
        $host_id = $this->host->get_host_id();
        $prefix  = $wpdb->get_blog_prefix( $host_id );
        $table   = $prefix . 'znc_order_map';

        $wpdb->insert( $table, array(
            'parent_order_id' => $parent_id,
            'child_order_id'  => $child_id,
            'child_blog_id'   => $child_blog_id,
            'status'          => 'processing',
            'created_at'      => current_time( 'mysql' ),
        ) );
    }

    public function on_order_processed( $order_id, $posted_data, $order ) {
        $order->update_meta_data( '_znc_global_order', 1 );
        $order->save();
    }
}
