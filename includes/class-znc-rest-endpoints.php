<?php
defined( 'ABSPATH' ) || exit;

class ZNC_REST_Endpoints {

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes() {
        $ns = 'znc/v1';

        /* Subsite endpoints */
        register_rest_route( $ns, '/cart-snapshot', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_cart_snapshot' ),
            'permission_callback' => array( __CLASS__, 'check_auth' ),
        ) );

        register_rest_route( $ns, '/shop-settings', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_shop_settings' ),
            'permission_callback' => array( __CLASS__, 'check_auth' ),
        ) );

        register_rest_route( $ns, '/pricing/validate', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'validate_pricing' ),
            'permission_callback' => array( __CLASS__, 'check_auth' ),
        ) );

        register_rest_route( $ns, '/inventory/deduct', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'deduct_inventory' ),
            'permission_callback' => array( __CLASS__, 'check_auth' ),
        ) );

        register_rest_route( $ns, '/inventory/restore', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'restore_inventory' ),
            'permission_callback' => array( __CLASS__, 'check_auth' ),
        ) );

        register_rest_route( $ns, '/orders/create-child', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'create_child_order' ),
            'permission_callback' => array( __CLASS__, 'check_auth' ),
        ) );

        /* Main site endpoints */
        register_rest_route( $ns, '/global-cart', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_global_cart' ),
            'permission_callback' => 'is_user_logged_in',
        ) );

        register_rest_route( $ns, '/global-cart/add', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'add_to_global_cart' ),
            'permission_callback' => 'is_user_logged_in',
        ) );

        register_rest_route( $ns, '/global-cart/remove', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'remove_from_global_cart' ),
            'permission_callback' => 'is_user_logged_in',
        ) );

        register_rest_route( $ns, '/checkout', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'process_checkout' ),
            'permission_callback' => 'is_user_logged_in',
        ) );
    }

    public static function check_auth( $request ) {
        return ZNC_REST_Auth::verify( $request );
    }

    /* ── Subsite Endpoints ────────────────────────────────────── */

    public static function get_cart_snapshot( $request ) {
        if ( ! function_exists( 'WC' ) ) {
            return new WP_Error( 'no_wc', 'WooCommerce not active.', array( 'status' => 500 ) );
        }
        $snapshot = new ZNC_Cart_Snapshot( new ZNC_Checkout_Host() );
        return rest_ensure_response( array( 'blog_id' => get_current_blog_id(), 'status' => 'ok' ) );
    }

    public static function get_shop_settings( $request ) {
        $settings = ZNC_Shop_Settings::get_settings();
        return rest_ensure_response( $settings );
    }

    public static function validate_pricing( $request ) {
        $items   = $request->get_param( 'items' );
        $results = array();

        if ( ! is_array( $items ) ) {
            return new WP_Error( 'invalid', 'Items array required.', array( 'status' => 400 ) );
        }

        foreach ( $items as $item ) {
            $product = wc_get_product( $item['variation_id'] ?: $item['product_id'] );
            if ( ! $product ) {
                $results[] = array( 'product_id' => $item['product_id'], 'valid' => false, 'reason' => 'not_found' );
                continue;
            }
            $current_price = (float) $product->get_price();
            $price_match   = abs( $current_price - (float) $item['expected_price'] ) < 0.01;
            $in_stock      = $product->is_in_stock() && ( ! $product->managing_stock() || $product->get_stock_quantity() >= $item['quantity'] );

            $results[] = array(
                'product_id'    => $item['product_id'],
                'variation_id'  => $item['variation_id'] ?? 0,
                'valid'         => $price_match && $in_stock,
                'current_price' => $current_price,
                'price_match'   => $price_match,
                'in_stock'      => $in_stock,
                'stock_qty'     => $product->get_stock_quantity(),
            );
        }

        return rest_ensure_response( array( 'results' => $results ) );
    }

    public static function deduct_inventory( $request ) {
        $items = $request->get_param( 'items' );
        if ( ! is_array( $items ) ) {
            return new WP_Error( 'invalid', 'Items array required.', array( 'status' => 400 ) );
        }

        $results = array();
        foreach ( $items as $item ) {
            $product = wc_get_product( $item['variation_id'] ?: $item['product_id'] );
            if ( ! $product || ! $product->managing_stock() ) {
                $results[] = array( 'product_id' => $item['product_id'], 'deducted' => false );
                continue;
            }
            $new_qty = wc_update_product_stock( $product, $item['quantity'], 'decrease' );
            $results[] = array( 'product_id' => $item['product_id'], 'deducted' => true, 'new_qty' => $new_qty );
        }

        return rest_ensure_response( array( 'results' => $results ) );
    }

    public static function restore_inventory( $request ) {
        $items = $request->get_param( 'items' );
        if ( ! is_array( $items ) ) {
            return new WP_Error( 'invalid', 'Items array required.', array( 'status' => 400 ) );
        }

        $results = array();
        foreach ( $items as $item ) {
            $product = wc_get_product( $item['variation_id'] ?: $item['product_id'] );
            if ( ! $product || ! $product->managing_stock() ) {
                $results[] = array( 'product_id' => $item['product_id'], 'restored' => false );
                continue;
            }
            $new_qty = wc_update_product_stock( $product, $item['quantity'], 'increase' );
            $results[] = array( 'product_id' => $item['product_id'], 'restored' => true, 'new_qty' => $new_qty );
        }

        return rest_ensure_response( array( 'results' => $results ) );
    }

    public static function create_child_order( $request ) {
        $data = $request->get_params();
        if ( empty( $data['items'] ) || empty( $data['user_id'] ) ) {
            return new WP_Error( 'invalid', 'Items and user_id required.', array( 'status' => 400 ) );
        }

        $order = wc_create_order( array( 'customer_id' => absint( $data['user_id'] ) ) );
        if ( is_wp_error( $order ) ) return $order;

        foreach ( $data['items'] as $item ) {
            $product = wc_get_product( $item['variation_id'] ?: $item['product_id'] );
            if ( $product ) {
                $order->add_product( $product, $item['quantity'] );
            }
        }

        $order->set_currency( get_woocommerce_currency() );
        $order->calculate_totals();
        $order->update_meta_data( '_znc_parent_order_id', absint( $data['parent_order_id'] ?? 0 ) );
        $order->update_meta_data( '_znc_parent_blog_id', absint( $data['parent_blog_id'] ?? 0 ) );
        $order->update_status( 'processing', 'Created by Zinckles Net Cart.' );
        $order->save();

        return rest_ensure_response( array(
            'child_order_id' => $order->get_id(),
            'blog_id'        => get_current_blog_id(),
            'total'          => $order->get_total(),
        ) );
    }

    /* ── Main Site Endpoints ──────────────────────────────────── */

    public static function get_global_cart( $request ) {
        $items = ZNC_Global_Cart_Store::get_cart( get_current_user_id() );
        return rest_ensure_response( array( 'items' => $items ) );
    }

    public static function add_to_global_cart( $request ) {
        $item = $request->get_params();
        $result = ZNC_Global_Cart_Store::add_item( get_current_user_id(), $item );
        return rest_ensure_response( $result );
    }

    public static function remove_from_global_cart( $request ) {
        $item_id = absint( $request->get_param( 'item_id' ) );
        $result  = ZNC_Global_Cart_Store::remove_item( get_current_user_id(), $item_id );
        return rest_ensure_response( $result );
    }

    public static function process_checkout( $request ) {
        $data   = $request->get_params();
        $result = ZNC_Checkout_Orchestrator::process( get_current_user_id(), $data );
        return rest_ensure_response( $result );
    }
}
