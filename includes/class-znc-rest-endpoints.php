<?php
defined( 'ABSPATH' ) || exit;
class ZNC_REST_Endpoints {
    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }
    public function register_routes() {
        register_rest_route( 'znc/v1', '/cart-snapshot', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'receive_snapshot' ),
            'permission_callback' => array( $this, 'check_auth' ),
        ));
        register_rest_route( 'znc/v1', '/pricing/validate', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'validate_pricing' ),
            'permission_callback' => array( $this, 'check_auth' ),
        ));
        register_rest_route( 'znc/v1', '/inventory/deduct', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'deduct_inventory' ),
            'permission_callback' => array( $this, 'check_auth' ),
        ));
        register_rest_route( 'znc/v1', '/inventory/restore', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'restore_inventory' ),
            'permission_callback' => array( $this, 'check_auth' ),
        ));
        register_rest_route( 'znc/v1', '/orders/create-child', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'create_child_order' ),
            'permission_callback' => array( $this, 'check_auth' ),
        ));
    }
    public function check_auth( $request ) {
        $sig = $request->get_header( 'X-ZNC-Signature' );
        $ts  = $request->get_header( 'X-ZNC-Timestamp' );
        if ( ! $sig || ! $ts ) return false;
        return ZNC_REST_Auth::verify( $sig, (int) $ts, $request->get_json_params() );
    }
    public function receive_snapshot( $request ) {
        $data = $request->get_json_params();
        $store = new ZNC_Global_Cart_Store();
        $id = $store->upsert_item( $data );
        return rest_ensure_response( array( 'success' => true, 'id' => $id ) );
    }
    public function validate_pricing( $request ) {
        $params = $request->get_json_params();
        $pid    = absint( $params['product_id'] ?? 0 );
        $product = wc_get_product( $pid );
        if ( ! $product ) return rest_ensure_response( array( 'valid' => false, 'reason' => 'Product not found' ) );
        return rest_ensure_response( array(
            'valid'    => true,
            'price'    => (float) $product->get_price(),
            'in_stock' => $product->is_in_stock(),
            'stock'    => $product->get_stock_quantity(),
        ));
    }
    public function deduct_inventory( $request ) {
        $params = $request->get_json_params();
        $product = wc_get_product( absint( $params['product_id'] ?? 0 ) );
        if ( ! $product ) return rest_ensure_response( array( 'success' => false ) );
        $qty = absint( $params['quantity'] ?? 1 );
        if ( $product->managing_stock() ) {
            $new_stock = wc_update_product_stock( $product, $qty, 'decrease' );
            return rest_ensure_response( array( 'success' => true, 'new_stock' => $new_stock ) );
        }
        return rest_ensure_response( array( 'success' => true, 'managed' => false ) );
    }
    public function restore_inventory( $request ) {
        $params = $request->get_json_params();
        $product = wc_get_product( absint( $params['product_id'] ?? 0 ) );
        if ( ! $product ) return rest_ensure_response( array( 'success' => false ) );
        $qty = absint( $params['quantity'] ?? 1 );
        if ( $product->managing_stock() ) {
            $new_stock = wc_update_product_stock( $product, $qty, 'increase' );
            return rest_ensure_response( array( 'success' => true, 'new_stock' => $new_stock ) );
        }
        return rest_ensure_response( array( 'success' => true, 'managed' => false ) );
    }
    public function create_child_order( $request ) {
        $params = $request->get_json_params();
        $order = wc_create_order( array( 'customer_id' => absint( $params['customer_id'] ?? 0 ) ) );
        if ( is_wp_error( $order ) ) return rest_ensure_response( array( 'success' => false, 'error' => $order->get_error_message() ) );
        foreach ( (array) ( $params['items'] ?? array() ) as $item ) {
            $product = wc_get_product( absint( $item['product_id'] ) );
            if ( $product ) $order->add_product( $product, absint( $item['quantity'] ?? 1 ) );
        }
        $order->set_status( 'processing' );
        $order->update_meta_data( '_znc_parent_order_id', absint( $params['parent_order_id'] ?? 0 ) );
        $order->update_meta_data( '_znc_parent_blog_id', absint( $params['parent_blog_id'] ?? 0 ) );
        $order->save();
        return rest_ensure_response( array( 'success' => true, 'order_id' => $order->get_id() ) );
    }
}
