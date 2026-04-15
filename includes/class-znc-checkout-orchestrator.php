<?php
defined( 'ABSPATH' ) || exit;
class ZNC_Checkout_Orchestrator {
    private $store, $merger, $currency, $mycred, $orders, $inventory;
    public function __construct( $store, $merger, $currency, $mycred, $orders, $inventory ) {
        $this->store     = $store;
        $this->merger    = $merger;
        $this->currency  = $currency;
        $this->mycred    = $mycred;
        $this->orders    = $orders;
        $this->inventory = $inventory;
    }
    public function init() {
        add_action( 'wp_ajax_znc_process_checkout', array( $this, 'process' ) );
    }
    public function process() {
        check_ajax_referer( 'znc_front', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'Not logged in.' ) );
        $user_id = get_current_user_id();
        $items   = $this->store->get_cart( $user_id );
        if ( empty( $items ) ) wp_send_json_error( array( 'message' => 'Cart is empty.' ) );

        // Step 1: Refresh & validate all items
        $valid_items = $this->merger->refresh_cart( $user_id );
        if ( empty( $valid_items ) ) wp_send_json_error( array( 'message' => 'All items are unavailable.' ) );

        // Step 2: Calculate totals
        $totals = $this->currency->parallel_totals( $valid_items );

        // Step 3: Create parent order
        $parent_order = $this->orders->create_parent( $user_id, $valid_items, $totals );
        if ( ! $parent_order ) wp_send_json_error( array( 'message' => 'Failed to create order.' ) );

        // Step 4: Create child orders per subsite
        $grouped = $this->store->get_cart_grouped( $user_id );
        foreach ( $grouped as $blog_id => $shop ) {
            $this->orders->create_child( $parent_order, $blog_id, $shop['items'] );
            $this->inventory->deduct_for_items( $blog_id, $shop['items'] );
        }

        // Step 5: Clear cart
        $this->store->clear_cart( $user_id );

        wp_send_json_success( array( 'message' => 'Order placed.', 'order_id' => $parent_order ) );
    }
}
