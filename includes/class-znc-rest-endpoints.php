<?php
/**
 * REST Endpoints — v1.4.1
 * Internal REST API for cross-subsite communication.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_REST_Endpoints {

    private $namespace = 'znc/v1';
    private $auth;

    public function __construct( ZNC_REST_Auth $auth ) {
        $this->auth = $auth;
    }

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route( $this->namespace, '/cart', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_cart' ),
            'permission_callback' => array( $this, 'check_auth' ),
        ) );

        register_rest_route( $this->namespace, '/cart/add', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'add_to_cart' ),
            'permission_callback' => array( $this, 'check_auth' ),
        ) );

        register_rest_route( $this->namespace, '/cart/remove', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'remove_from_cart' ),
            'permission_callback' => array( $this, 'check_auth' ),
        ) );

        register_rest_route( $this->namespace, '/cart/count', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_cart_count' ),
            'permission_callback' => array( $this, 'check_auth' ),
        ) );

        register_rest_route( $this->namespace, '/stock/check', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'check_stock' ),
            'permission_callback' => array( $this, 'check_auth' ),
        ) );

        register_rest_route( $this->namespace, '/ping', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'ping' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public function check_auth( $request ) {
        // Internal multisite calls — check user is logged in
        if ( is_user_logged_in() ) return true;

        // External HMAC auth
        $sig       = $request->get_header( 'X-ZNC-Signature' );
        $timestamp = $request->get_header( 'X-ZNC-Timestamp' );
        if ( ! $sig || ! $timestamp ) return false;

        $body   = $request->get_json_params() ?: array();
        $result = $this->auth->verify_request( $sig, $body, $timestamp );
        if ( is_wp_error( $result ) ) return $result;
        return (bool) $result;
    }

    public function get_cart( $request ) {
        $user_id = $request->get_param( 'user_id' ) ?: get_current_user_id();
        if ( ! $user_id ) return new WP_Error( 'no_user', 'User ID required.', array( 'status' => 400 ) );

        $store = new ZNC_Global_Cart_Store( new ZNC_Checkout_Host() );
        return rest_ensure_response( array(
            'items' => $store->get_items( $user_id ),
            'count' => $store->count( $user_id ),
            'total' => $store->get_total( $user_id ),
        ) );
    }

    public function add_to_cart( $request ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) return new WP_Error( 'no_user', 'Must be logged in.', array( 'status' => 401 ) );

        $data = $request->get_json_params();
        $data['user_id'] = $user_id;

        $store = new ZNC_Global_Cart_Store( new ZNC_Checkout_Host() );
        $ok    = $store->upsert( $data );
        if ( ! $ok ) return new WP_Error( 'insert_fail', 'Failed to add item.', array( 'status' => 500 ) );

        return rest_ensure_response( array( 'success' => true, 'count' => $store->count( $user_id ) ) );
    }

    public function remove_from_cart( $request ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) return new WP_Error( 'no_user', 'Must be logged in.', array( 'status' => 401 ) );

        $data = $request->get_json_params();
        $store = new ZNC_Global_Cart_Store( new ZNC_Checkout_Host() );
        $ok    = $store->remove( $user_id, $data['blog_id'], $data['product_id'], $data['variation_id'] ?? 0 );

        return rest_ensure_response( array( 'success' => (bool) $ok, 'count' => $store->count( $user_id ) ) );
    }

    public function get_cart_count( $request ) {
        $user_id = $request->get_param( 'user_id' ) ?: get_current_user_id();
        $store   = new ZNC_Global_Cart_Store( new ZNC_Checkout_Host() );
        return rest_ensure_response( array( 'count' => $store->count( $user_id ) ) );
    }

    public function check_stock( $request ) {
        $data = $request->get_json_params();
        $result = ZNC_Inventory_Sync::check_stock(
            $data['blog_id'], $data['product_id'], $data['variation_id'] ?? 0, $data['quantity'] ?? 1
        );
        return rest_ensure_response( $result );
    }

    public function ping() {
        return rest_ensure_response( array(
            'status'  => 'ok',
            'version' => ZNC_VERSION,
            'time'    => current_time( 'mysql' ),
        ) );
    }
}
