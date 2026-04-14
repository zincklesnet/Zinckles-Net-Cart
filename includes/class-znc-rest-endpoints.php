<?php
/**
 * REST Endpoints — all cross-site API routes.
 *
 * v1.2.0: Added /global-cart/refresh endpoint for full cross-site aggregation.
 *
 * @package ZincklesNetCart
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class ZNC_REST_Endpoints {

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        $ns = 'znc/v1';

        /* ── Subsite endpoints ────────────────────────────── */
        register_rest_route( $ns, '/cart-snapshot', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_cart_snapshot' ),
            'permission_callback' => array( $this, 'verify_hmac' ),
        ) );

        register_rest_route( $ns, '/shop-settings', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_shop_settings' ),
            'permission_callback' => array( $this, 'verify_hmac' ),
        ) );

        register_rest_route( $ns, '/pricing/validate', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'validate_pricing' ),
            'permission_callback' => array( $this, 'verify_hmac' ),
        ) );

        register_rest_route( $ns, '/inventory/deduct', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'deduct_inventory' ),
            'permission_callback' => array( $this, 'verify_hmac' ),
        ) );

        register_rest_route( $ns, '/inventory/restore', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'restore_inventory' ),
            'permission_callback' => array( $this, 'verify_hmac' ),
        ) );

        register_rest_route( $ns, '/orders/create-child', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'create_child_order' ),
            'permission_callback' => array( $this, 'verify_hmac' ),
        ) );

        /* ── Main-site endpoints ──────────────────────────── */
        register_rest_route( $ns, '/global-cart', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_global_cart' ),
            'permission_callback' => array( $this, 'verify_logged_in' ),
        ) );

        register_rest_route( $ns, '/global-cart/add', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'add_to_global_cart' ),
            'permission_callback' => array( $this, 'verify_hmac_or_user' ),
        ) );

        register_rest_route( $ns, '/global-cart/remove', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'remove_from_global_cart' ),
            'permission_callback' => array( $this, 'verify_logged_in' ),
        ) );

        register_rest_route( $ns, '/global-cart/refresh', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'refresh_global_cart' ),
            'permission_callback' => array( $this, 'verify_logged_in' ),
        ) );

        register_rest_route( $ns, '/checkout', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'process_checkout' ),
            'permission_callback' => array( $this, 'verify_logged_in' ),
        ) );
    }

    /* ── Permission callbacks ─────────────────────────────── */

    public function verify_hmac( WP_REST_Request $request ) {
        return apply_filters( 'znc_rest_verify_request', $request );
    }

    public function verify_logged_in() {
        return is_user_logged_in();
    }

    public function verify_hmac_or_user( WP_REST_Request $request ) {
        if ( is_user_logged_in() ) {
            return true;
        }
        return $this->verify_hmac( $request );
    }

    /* ── Subsite: Cart Snapshot ────────────────────────────── */

    public function get_cart_snapshot( WP_REST_Request $request ) {
        $user_id = absint( $request->get_param( 'user_id' ) );
        if ( ! $user_id ) {
            return new WP_Error( 'missing_user', 'user_id is required.', array( 'status' => 400 ) );
        }
        $snapshot = new ZNC_Cart_Snapshot();
        return rest_ensure_response( $snapshot->build( $user_id ) );
    }

    /* ── Subsite: Shop Settings ───────────────────────────── */

    public function get_shop_settings() {
        $shop = new ZNC_Shop_Settings();
        return rest_ensure_response( $shop->get_settings() );
    }

    /* ── Subsite: Pricing Validation ──────────────────────── */

    public function validate_pricing( WP_REST_Request $request ) {
        $items = $request->get_json_params();
        if ( empty( $items['products'] ) ) {
            return new WP_Error( 'no_products', 'Products array is required.', array( 'status' => 400 ) );
        }

        $results = array();
        foreach ( $items['products'] as $item ) {
            $product_id     = absint( $item['product_id'] ?? 0 );
            $variation_id   = absint( $item['variation_id'] ?? 0 );
            $quantity       = absint( $item['quantity'] ?? 1 );
            $expected_price = floatval( $item['expected_price'] ?? 0 );

            $product = wc_get_product( $variation_id ?: $product_id );
            if ( ! $product ) {
                $results[] = array(
                    'product_id' => $product_id,
                    'valid'      => false,
                    'reason'     => 'not_found',
                );
                continue;
            }

            $current_price = floatval( $product->get_price() );
            $in_stock      = $product->is_in_stock()
                && ( ! $product->managing_stock() || $product->get_stock_quantity() >= $quantity );
            $price_match   = abs( $current_price - $expected_price ) < 0.01;

            $results[] = array(
                'product_id'    => $product_id,
                'variation_id'  => $variation_id,
                'valid'         => $price_match && $in_stock,
                'current_price' => $current_price,
                'in_stock'      => $in_stock,
                'stock_qty'     => $product->managing_stock() ? $product->get_stock_quantity() : null,
                'price_changed' => ! $price_match,
            );
        }

        return rest_ensure_response( array( 'results' => $results ) );
    }

    /* ── Subsite: Inventory ───────────────────────────────── */

    public function deduct_inventory( WP_REST_Request $request ) {
        $params  = $request->get_json_params();
        $product = wc_get_product( $params['variation_id'] ?? $params['product_id'] ?? 0 );

        if ( ! $product ) {
            return new WP_Error( 'not_found', 'Product not found.', array( 'status' => 404 ) );
        }

        if ( $product->managing_stock() ) {
            $new_stock = wc_update_product_stock( $product, $params['quantity'], 'decrease' );
            if ( is_wp_error( $new_stock ) ) {
                return $new_stock;
            }
            return rest_ensure_response( array( 'success' => true, 'new_stock' => $new_stock ) );
        }

        return rest_ensure_response( array( 'success' => true, 'message' => 'Stock not managed.' ) );
    }

    public function restore_inventory( WP_REST_Request $request ) {
        $params  = $request->get_json_params();
        $product = wc_get_product( $params['variation_id'] ?? $params['product_id'] ?? 0 );

        if ( ! $product ) {
            return new WP_Error( 'not_found', 'Product not found.', array( 'status' => 404 ) );
        }

        if ( $product->managing_stock() ) {
            wc_update_product_stock( $product, $params['quantity'], 'increase' );
        }

        return rest_ensure_response( array( 'success' => true ) );
    }

    /* ── Subsite: Child Order ─────────────────────────────── */

    public function create_child_order( WP_REST_Request $request ) {
        $params = $request->get_json_params();

        $order = wc_create_order( array(
            'customer_id' => $params['customer_id'] ?? 0,
            'status'      => 'processing',
        ) );

        if ( is_wp_error( $order ) ) {
            return $order;
        }

        foreach ( $params['items'] ?? array() as $item ) {
            $product = wc_get_product( $item['variation_id'] ?? $item['product_id'] );
            if ( $product ) {
                $order->add_product( $product, $item['quantity'], array(
                    'subtotal' => $item['line_total'],
                    'total'    => $item['line_total'],
                ) );
            }
        }

        $order->set_currency( $params['currency'] ?? get_woocommerce_currency() );
        $order->calculate_totals();
        $order->add_order_note( sprintf(
            'Net Cart child order — parent #%d',
            $params['parent_order_id'] ?? 0
        ) );
        $order->update_meta_data( '_znc_parent_order_id', $params['parent_order_id'] ?? 0 );
        $order->update_meta_data( '_znc_parent_site_id', $params['parent_site_id'] ?? get_main_site_id() );
        $order->save();

        return rest_ensure_response( array(
            'success'        => true,
            'child_order_id' => $order->get_id(),
        ) );
    }

    /* ── Main site: Global Cart ───────────────────────────── */

    public function get_global_cart( WP_REST_Request $request ) {
        $store    = new ZNC_Global_Cart_Store();
        $group_by = $request->get_param( 'group_by' ) ?: 'flat';
        $items    = $store->get_cart( get_current_user_id(), $group_by );
        $summary  = $store->get_cart_summary( get_current_user_id() );

        return rest_ensure_response( array(
            'items'   => $items,
            'summary' => $summary,
        ) );
    }

    public function add_to_global_cart( WP_REST_Request $request ) {
        $params   = $request->get_json_params();
        $store    = new ZNC_Global_Cart_Store();
        $currency = new ZNC_Currency_Handler();
        $merger   = new ZNC_Global_Cart_Merger( $store, $currency );

        $result = $merger->add_item( $params );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( array( 'success' => true, 'cart' => $result ) );
    }

    public function remove_from_global_cart( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $store  = new ZNC_Global_Cart_Store();
        $store->remove_item( get_current_user_id(), $params['line_id'] ?? 0 );

        return rest_ensure_response( array( 'success' => true ) );
    }

    /**
     * Refresh the global cart — re-fetches snapshots from all enrolled subsites.
     * v1.2.0 addition.
     */
    public function refresh_global_cart( WP_REST_Request $request ) {
        $user_id  = get_current_user_id();
        $store    = new ZNC_Global_Cart_Store();
        $currency = new ZNC_Currency_Handler();
        $merger   = new ZNC_Global_Cart_Merger( $store, $currency );

        $result = $merger->refresh_all( $user_id );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( array(
            'success' => true,
            'cart'    => $store->get_cart( $user_id, 'shop' ),
            'summary' => $store->get_cart_summary( $user_id ),
        ) );
    }

    /* ── Main site: Checkout ──────────────────────────────── */

    public function process_checkout( WP_REST_Request $request ) {
        $params      = $request->get_json_params();
        $store       = new ZNC_Global_Cart_Store();
        $currency    = new ZNC_Currency_Handler();
        $merger      = new ZNC_Global_Cart_Merger( $store, $currency );
        $mycred      = new ZNC_MyCred_Engine();
        $orders      = new ZNC_Order_Factory();
        $inventory   = new ZNC_Inventory_Sync();
        $orchestrator = new ZNC_Checkout_Orchestrator(
            $store, $merger, $currency, $mycred, $orders, $inventory
        );

        $result = $orchestrator->process( get_current_user_id(), $params );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( $result );
    }
}
