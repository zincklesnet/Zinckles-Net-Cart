<?php
/**
 * Cart Sync — Replaces WooCommerce cart count/fragments with global cart data.
 *
 * Runs on ALL enrolled sites so header cart widgets, menu badges,
 * and WC mini-carts show the global cart count instead of the local one.
 *
 * @package ZincklesNetCart
 * @since   1.5.1
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Cart_Sync {

    /** @var ZNC_Global_Cart */
    private $global_cart;

    /** @var ZNC_Checkout_Host */
    private $checkout_host;

    public function __construct( ZNC_Global_Cart $global_cart, ZNC_Checkout_Host $checkout_host ) {
        $this->global_cart    = $global_cart;
        $this->checkout_host  = $checkout_host;
    }

    public function init() {
        add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'override_fragments' ), 999 );
        add_filter( 'woocommerce_cart_contents_count',   array( $this, 'override_count' ), 999 );
        add_action( 'wp_footer',                         array( $this, 'inject_cart_data' ) );
        add_action( 'admin_bar_menu',                    array( $this, 'admin_bar_cart' ), 100 );
    }

    public function override_fragments( $fragments ) {
        if ( ! is_user_logged_in() ) return $fragments;

        $count    = $this->global_cart->get_item_count();
        $cart_url = $this->checkout_host->get_cart_url();

        $fragments['a.cart-contents'] = sprintf(
            '<a class="cart-contents" href="%s" title="%s"><span class="count">%d</span></a>',
            esc_url( $cart_url ),
            esc_attr__( 'View Global Cart', 'zinckles-net-cart' ),
            $count
        );

        $fragments['.znc-cart-count']      = '<span class="znc-cart-count">' . $count . '</span>';
        $fragments['.cart-contents-count']  = '<span class="cart-contents-count">' . $count . '</span>';
        $fragments['.wc-cart-count']        = '<span class="wc-cart-count">' . $count . '</span>';

        return $fragments;
    }

    public function override_count( $count ) {
        if ( ! is_user_logged_in() ) return $count;
        return $this->global_cart->get_item_count();
    }

    public function inject_cart_data() {
        if ( ! is_user_logged_in() ) return;

        $count    = $this->global_cart->get_item_count();
        $cart_url = $this->checkout_host->get_cart_url();

        echo '<script>var zncCartData=' . wp_json_encode( array(
            'count'   => $count,
            'cartUrl' => $cart_url,
        ) ) . ';</script>';
    }

    public function admin_bar_cart( $wp_admin_bar ) {
        if ( ! is_user_logged_in() ) return;

        $count = $this->global_cart->get_item_count();
        $wp_admin_bar->add_node( array(
            'id'    => 'znc-global-cart',
            'title' => sprintf( '&#x1F6D2; Net Cart (%d)', $count ),
            'href'  => $this->checkout_host->get_cart_url(),
        ) );
    }
}
