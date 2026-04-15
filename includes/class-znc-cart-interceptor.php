<?php
/**
 * Cart Interceptor — Hooks into WC add-to-cart and syncs to global cart.
 *
 * Fixes from v1.6.1:
 *  • Added nonce verification to ajax_get_count()
 *  • Deep sanitization of variation data
 *  • Removed duplicate script localization (zncCart removed; uses zncFront only)
 *
 * @package ZincklesNetCart
 * @since   1.7.0
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Cart_Interceptor {

    /** @var ZNC_Global_Cart */
    private $cart;

    public function __construct() {
        $this->cart = ZNC_Global_Cart::instance();
    }

    public function init() {
        /* ── WooCommerce hooks ── */
        add_action( 'woocommerce_add_to_cart',                     array( $this, 'on_add_to_cart' ), 10, 6 );
        add_action( 'woocommerce_cart_item_removed',               array( $this, 'on_item_removed' ), 10, 2 );
        add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'on_qty_updated' ), 10, 4 );

        /* ── AJAX handlers ── */
        add_action( 'wp_ajax_znc_add_to_global_cart', array( $this, 'ajax_add_item' ) );
        add_action( 'wp_ajax_znc_remove_cart_item',   array( $this, 'ajax_remove_item' ) );
        add_action( 'wp_ajax_znc_update_cart_qty',    array( $this, 'ajax_update_qty' ) );
        add_action( 'wp_ajax_znc_clear_global_cart',  array( $this, 'ajax_clear_cart' ) );
        add_action( 'wp_ajax_znc_get_cart_count',     array( $this, 'ajax_get_count' ) );

        // NOTE: Script localization is handled ONLY in the bootstrap (zncFront).
        // The old duplicate zncCart localization on wp_enqueue_scripts:99 is removed.
    }

    /* ──────────────────────────────────────────────────────────────
     *  WC HOOKS
     * ──────────────────────────────────────────────────────────── */

    /**
     * When WC adds to local cart, also add to global cart.
     */
    public function on_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
        if ( ! is_user_logged_in() ) return;
        if ( ! $this->is_site_participating() ) return;

        $user_id = get_current_user_id();

        // Deep sanitize variation data
        $clean_variation = $this->sanitize_variation( $variation );

        $this->cart->add_item( $user_id, array(
            'blog_id'      => get_current_blog_id(),
            'product_id'   => absint( $product_id ),
            'variation_id' => absint( $variation_id ),
            'quantity'     => max( 1, absint( $quantity ) ),
            'variation'    => $clean_variation,
        ) );
    }

    /**
     * When WC removes from local cart, also remove from global cart.
     */
    public function on_item_removed( $cart_item_key, $wc_cart ) {
        if ( ! is_user_logged_in() ) return;
        if ( ! $this->is_site_participating() ) return;

        $user_id = get_current_user_id();
        $removed = $wc_cart->removed_cart_contents[ $cart_item_key ] ?? null;
        if ( ! $removed ) return;

        $key = $this->cart->make_key(
            get_current_blog_id(),
            $removed['product_id'],
            $removed['variation_id'] ?? 0
        );

        $this->cart->remove_item( $user_id, $key );
    }

    /**
     * When WC updates quantity, sync to global cart.
     */
    public function on_qty_updated( $cart_item_key, $quantity, $old_quantity, $wc_cart ) {
        if ( ! is_user_logged_in() ) return;
        if ( ! $this->is_site_participating() ) return;

        $user_id  = get_current_user_id();
        $wc_item  = $wc_cart->get_cart_item( $cart_item_key );
        if ( ! $wc_item ) return;

        $key = $this->cart->make_key(
            get_current_blog_id(),
            $wc_item['product_id'],
            $wc_item['variation_id'] ?? 0
        );

        $this->cart->update_quantity( $user_id, $key, max( 1, absint( $quantity ) ) );
    }

    /* ──────────────────────────────────────────────────────────────
     *  AJAX HANDLERS
     * ──────────────────────────────────────────────────────────── */

    /**
     * AJAX: Add item to global cart.
     */
    public function ajax_add_item() {
        check_ajax_referer( 'znc_cart_action', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Not logged in.', 'zinckles-net-cart' ) ) );
        }

        $user_id      = get_current_user_id();
        $blog_id      = absint( $_POST['blog_id'] ?? get_current_blog_id() );
        $product_id   = absint( $_POST['product_id'] ?? 0 );
        $variation_id = absint( $_POST['variation_id'] ?? 0 );
        $quantity     = max( 1, absint( $_POST['quantity'] ?? 1 ) );
        $variation    = $this->sanitize_variation( $_POST['variation'] ?? array() );

        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid product.', 'zinckles-net-cart' ) ) );
        }

        $this->cart->add_item( $user_id, array(
            'blog_id'      => $blog_id,
            'product_id'   => $product_id,
            'variation_id' => $variation_id,
            'quantity'     => $quantity,
            'variation'    => $variation,
        ) );

        wp_send_json_success( array(
            'count'   => $this->cart->get_item_count( $user_id ),
            'message' => __( 'Item added to cart.', 'zinckles-net-cart' ),
        ) );
    }

    /**
     * AJAX: Remove item from global cart.
     */
    public function ajax_remove_item() {
        check_ajax_referer( 'znc_cart_action', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Not logged in.', 'zinckles-net-cart' ) ) );
        }

        $user_id = get_current_user_id();
        $key     = sanitize_text_field( wp_unslash( $_POST['item_key'] ?? '' ) );

        if ( ! $key ) {
            wp_send_json_error( array( 'message' => __( 'Invalid item key.', 'zinckles-net-cart' ) ) );
        }

        $this->cart->remove_item( $user_id, $key );

        wp_send_json_success( array(
            'count'   => $this->cart->get_item_count( $user_id ),
            'message' => __( 'Item removed.', 'zinckles-net-cart' ),
        ) );
    }

    /**
     * AJAX: Update item quantity.
     */
    public function ajax_update_qty() {
        check_ajax_referer( 'znc_cart_action', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Not logged in.', 'zinckles-net-cart' ) ) );
        }

        $user_id  = get_current_user_id();
        $key      = sanitize_text_field( wp_unslash( $_POST['item_key'] ?? '' ) );
        $quantity = max( 1, absint( $_POST['quantity'] ?? 1 ) );

        if ( ! $key ) {
            wp_send_json_error( array( 'message' => __( 'Invalid item key.', 'zinckles-net-cart' ) ) );
        }

        $this->cart->update_quantity( $user_id, $key, $quantity );

        wp_send_json_success( array(
            'count'    => $this->cart->get_item_count( $user_id ),
            'quantity' => $quantity,
            'message'  => __( 'Quantity updated.', 'zinckles-net-cart' ),
        ) );
    }

    /**
     * AJAX: Clear entire global cart.
     */
    public function ajax_clear_cart() {
        check_ajax_referer( 'znc_cart_action', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Not logged in.', 'zinckles-net-cart' ) ) );
        }

        $this->cart->clear_cart( get_current_user_id() );

        wp_send_json_success( array(
            'count'   => 0,
            'message' => __( 'Cart cleared.', 'zinckles-net-cart' ),
        ) );
    }

    /**
     * AJAX: Get cart count.
     *
     * FIXED: Now has nonce verification (was missing in v1.6.1).
     */
    public function ajax_get_count() {
        // FIX: Added nonce check (was completely missing in v1.6.1)
        check_ajax_referer( 'znc_cart_action', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_success( array( 'count' => 0 ) );
        }

        $count = $this->cart->get_item_count( get_current_user_id() );
        wp_send_json_success( array( 'count' => $count ) );
    }

    /* ──────────────────────────────────────────────────────────────
     *  HELPERS
     * ──────────────────────────────────────────────────────────── */

    /**
     * Check if the current site participates in Net Cart.
     *
     * @return bool
     */
    public function is_site_participating() {
        $settings   = get_site_option( 'znc_network_settings', array() );
        $blog_id    = get_current_blog_id();
        $mode       = $settings['enrollment_mode'] ?? 'opt-in';
        $enrolled   = (array) ( $settings['enrolled_sites'] ?? array() );
        $blocked    = (array) ( $settings['blocked_sites'] ?? array() );

        if ( in_array( $blog_id, $blocked, true ) ) {
            return false;
        }

        if ( $mode === 'opt-out' ) {
            return true; // All sites participate unless blocked
        }

        // opt-in mode
        return in_array( $blog_id, $enrolled, true );
    }

    /**
     * Deep sanitize variation data.
     *
     * FIX: v1.6.1 passed variation arrays without sanitization.
     *
     * @param mixed $variation
     * @return array
     */
    private function sanitize_variation( $variation ) {
        if ( ! is_array( $variation ) ) {
            return array();
        }

        $clean = array();

        foreach ( $variation as $key => $value ) {
            // Only allow attribute_* keys
            $key = sanitize_key( $key );
            if ( strpos( $key, 'attribute' ) !== 0 ) {
                continue;
            }

            // Sanitize the value
            $value = sanitize_text_field( wp_unslash( $value ) );

            // Reject suspiciously long values
            if ( strlen( $value ) > 200 ) {
                continue;
            }

            $clean[ $key ] = $value;
        }

        return $clean;
    }
}
