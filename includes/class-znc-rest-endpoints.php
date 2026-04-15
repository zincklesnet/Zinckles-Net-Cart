<?php
/**
 * REST Endpoints — Expanded API with HMAC wiring.
 *
 * Expanded from v1.6.1 (which had only 2 routes):
 *  • GET  /cart             — full enriched cart
 *  • POST /cart/add         — add item
 *  • POST /cart/remove      — remove item
 *  • POST /cart/update-qty  — update quantity
 *  • POST /cart/clear       — clear cart
 *  • GET  /cart/count       — item count
 *  • GET  /points/balance   — MyCred + GamiPress balances
 *  • GET  /orders           — order history
 *
 * All routes require authentication (logged-in user or HMAC).
 *
 * @package ZincklesNetCart
 * @since   1.7.0
 */
defined( 'ABSPATH' ) || exit;

class ZNC_REST_Endpoints {

    const NAMESPACE = 'znc/v1';

    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        /* ── Cart routes ── */
        register_rest_route( self::NAMESPACE, '/cart', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_cart' ),
            'permission_callback' => array( $this, 'check_auth' ),
        ) );

        register_rest_route( self::NAMESPACE, '/cart/add', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'add_item' ),
            'permission_callback' => array( $this, 'check_auth' ),
            'args'                => array(
                'blog_id'      => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ),
                'product_id'   => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ),
                'variation_id' => array( 'required' => false, 'type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint' ),
                'quantity'     => array( 'required' => false, 'type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint' ),
            ),
        ) );

        register_rest_route( self::NAMESPACE, '/cart/remove', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'remove_item' ),
            'permission_callback' => array( $this, 'check_auth' ),
            'args'                => array(
                'item_key' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
            ),
        ) );

        register_rest_route( self::NAMESPACE, '/cart/update-qty', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'update_qty' ),
            'permission_callback' => array( $this, 'check_auth' ),
            'args'                => array(
                'item_key' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
                'quantity' => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ),
            ),
        ) );

        register_rest_route( self::NAMESPACE, '/cart/clear', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'clear_cart' ),
            'permission_callback' => array( $this, 'check_auth' ),
        ) );

        register_rest_route( self::NAMESPACE, '/cart/count', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_count' ),
            'permission_callback' => array( $this, 'check_auth' ),
        ) );

        /* ── Points routes ── */
        register_rest_route( self::NAMESPACE, '/points/balance', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_points_balance' ),
            'permission_callback' => array( $this, 'check_auth' ),
        ) );

        /* ── Order routes ── */
        register_rest_route( self::NAMESPACE, '/orders', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_orders' ),
            'permission_callback' => array( $this, 'check_auth' ),
            'args'                => array(
                'limit' => array( 'required' => false, 'type' => 'integer', 'default' => 20, 'sanitize_callback' => 'absint' ),
            ),
        ) );
    }

    /* ──────────────────────────────────────────────────────────────
     *  AUTH
     * ──────────────────────────────────────────────────────────── */

    /**
     * Permission callback: logged-in user OR valid HMAC signature.
     */
    public function check_auth( $request ) {
        // Standard WP cookie auth
        if ( is_user_logged_in() ) {
            return true;
        }

        // HMAC auth (wires the previously unused ZNC_REST_Auth)
        if ( class_exists( 'ZNC_REST_Auth' ) ) {
            $auth = new ZNC_REST_Auth();
            if ( method_exists( $auth, 'verify_hmac' ) ) {
                return $auth->verify_hmac( $request );
            }
        }

        return new WP_Error(
            'znc_rest_unauthorized',
            __( 'Authentication required.', 'zinckles-net-cart' ),
            array( 'status' => 401 )
        );
    }

    /* ──────────────────────────────────────────────────────────────
     *  CART CALLBACKS
     * ──────────────────────────────────────────────────────────── */

    public function get_cart( $request ) {
        $user_id  = get_current_user_id();
        $renderer = new ZNC_Cart_Renderer();
        $enriched = $renderer->get_enriched_cart( $user_id );

        $currency_totals = ZNC_Currency_Handler::get_totals_by_currency( $enriched );

        return rest_ensure_response( array(
            'shops'           => $enriched,
            'currency_totals' => $currency_totals,
            'item_count'      => ZNC_Global_Cart::instance()->get_item_count( $user_id ),
        ) );
    }

    public function add_item( $request ) {
        $user_id = get_current_user_id();
        $cart    = ZNC_Global_Cart::instance();

        $cart->add_item( $user_id, array(
            'blog_id'      => $request->get_param( 'blog_id' ),
            'product_id'   => $request->get_param( 'product_id' ),
            'variation_id' => $request->get_param( 'variation_id' ),
            'quantity'     => max( 1, $request->get_param( 'quantity' ) ),
            'variation'    => array(),
        ) );

        return rest_ensure_response( array(
            'success' => true,
            'count'   => $cart->get_item_count( $user_id ),
        ) );
    }

    public function remove_item( $request ) {
        $user_id = get_current_user_id();
        $cart    = ZNC_Global_Cart::instance();

        $cart->remove_item( $user_id, $request->get_param( 'item_key' ) );

        return rest_ensure_response( array(
            'success' => true,
            'count'   => $cart->get_item_count( $user_id ),
        ) );
    }

    public function update_qty( $request ) {
        $user_id  = get_current_user_id();
        $cart     = ZNC_Global_Cart::instance();
        $quantity = max( 1, $request->get_param( 'quantity' ) );

        $cart->update_quantity( $user_id, $request->get_param( 'item_key' ), $quantity );

        return rest_ensure_response( array(
            'success'  => true,
            'count'    => $cart->get_item_count( $user_id ),
            'quantity' => $quantity,
        ) );
    }

    public function clear_cart( $request ) {
        $user_id = get_current_user_id();
        ZNC_Global_Cart::instance()->clear_cart( $user_id );

        return rest_ensure_response( array(
            'success' => true,
            'count'   => 0,
        ) );
    }

    public function get_count( $request ) {
        $user_id = get_current_user_id();
        $count   = ZNC_Global_Cart::instance()->get_item_count( $user_id );

        return rest_ensure_response( array( 'count' => $count ) );
    }

    /* ──────────────────────────────────────────────────────────────
     *  POINTS CALLBACKS
     * ──────────────────────────────────────────────────────────── */

    public function get_points_balance( $request ) {
        $user_id  = get_current_user_id();
        $balances = array();

        // MyCred
        if ( class_exists( 'ZNC_MyCred_Engine' ) && function_exists( 'mycred_get_types' ) ) {
            $balances['mycred'] = ZNC_MyCred_Engine::get_all_balances( $user_id );
        }

        // GamiPress
        if ( function_exists( 'gamipress_get_user_points' ) && post_type_exists( 'points-type' ) ) {
            $types = get_posts( array( 'post_type' => 'points-type', 'numberposts' => 50, 'post_status' => 'publish' ) );
            $gami  = array();
            foreach ( $types as $type ) {
                $gami[ $type->post_name ] = array(
                    'label'   => $type->post_title,
                    'balance' => (int) gamipress_get_user_points( $user_id, $type->post_name ),
                );
            }
            if ( ! empty( $gami ) ) {
                $balances['gamipress'] = $gami;
            }
        }

        return rest_ensure_response( $balances );
    }

    /* ──────────────────────────────────────────────────────────────
     *  ORDER CALLBACKS
     * ──────────────────────────────────────────────────────────── */

    public function get_orders( $request ) {
        $user_id = get_current_user_id();
        $limit   = min( 50, $request->get_param( 'limit' ) );

        $orders = ZNC_Order_Query::get_user_parent_orders( $user_id, $limit );
        $result = array();

        foreach ( $orders as $order ) {
            $children = ZNC_Order_Query::get_child_orders( $order->get_id() );
            $child_shops = array();
            foreach ( $children as $bid => $oid ) {
                $details = get_blog_details( $bid );
                $child_shops[] = array(
                    'blog_id'  => $bid,
                    'name'     => $details ? $details->blogname : "Shop #{$bid}",
                    'order_id' => $oid,
                );
            }

            $result[] = array(
                'id'       => $order->get_id(),
                'status'   => $order->get_status(),
                'total'    => (float) $order->get_total(),
                'currency' => $order->get_currency(),
                'date'     => $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : null,
                'shops'    => $child_shops,
            );
        }

        return rest_ensure_response( $result );
    }
}
