<?php
/**
 * My Account + Cart + Checkout Redirect.
 *
 * On every enrolled subsite that is NOT the checkout host:
 *  - Redirects My Account / Cart / Checkout pages to the checkout host
 *  - Replaces WC menu links for those pages
 *  - Shows a banner on shop pages linking to the global cart
 *  - Rewrites "View cart" button after add-to-cart
 *
 * @package ZincklesNetCart
 * @since   1.3.0
 */
defined( 'ABSPATH' ) || exit;

class ZNC_My_Account_Redirect {

    private $host;

    public function __construct( ZNC_Checkout_Host $host ) {
        $this->host = $host;
    }

    public function init() {
        if ( $this->host->is_current_site_host() ) return;
        if ( ! $this->is_enrolled() ) return;

        add_action( 'template_redirect', array( $this, 'redirect_wc_pages' ), 5 );

        add_filter( 'woocommerce_get_myaccount_page_permalink', array( $this, 'filter_account_url' ) );
        add_filter( 'woocommerce_get_cart_url',                 array( $this, 'filter_cart_url' ) );
        add_filter( 'woocommerce_get_checkout_url',             array( $this, 'filter_checkout_url' ) );

        add_filter( 'wp_nav_menu_objects', array( $this, 'filter_nav_menu_items' ), 20, 2 );

        add_action( 'woocommerce_before_shop_loop',      array( $this, 'cart_notice' ) );
        add_action( 'woocommerce_before_single_product', array( $this, 'cart_notice' ) );

        add_filter( 'wc_add_to_cart_message_html', array( $this, 'filter_add_to_cart_message' ), 20, 2 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /** Redirect WC pages to checkout host. */
    public function redirect_wc_pages() {
        if ( ! function_exists( 'is_account_page' ) ) return;

        $redirect_url = '';

        if ( is_account_page() ) {
            $redirect_url = $this->host->get_account_url();
            global $wp;
            if ( ! empty( $wp->query_vars ) ) {
                foreach ( array( 'orders','view-order','edit-account','edit-address',
                                 'payment-methods','downloads','net-cart-orders' ) as $ep ) {
                    if ( isset( $wp->query_vars[ $ep ] ) ) {
                        $redirect_url = trailingslashit( $redirect_url ) . $ep . '/';
                        $val = $wp->query_vars[ $ep ];
                        if ( $val ) $redirect_url .= $val . '/';
                        break;
                    }
                }
            }
        } elseif ( is_cart() ) {
            $redirect_url = $this->host->get_cart_url();
        } elseif ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
            $redirect_url = $this->host->get_checkout_url();
        }

        if ( $redirect_url ) {
            $redirect_url = add_query_arg( 'znc_from', get_current_blog_id(), $redirect_url );
            wp_redirect( $redirect_url, 302 );
            exit;
        }
    }

    public function filter_account_url( $url )  { return $this->host->get_account_url(); }
    public function filter_cart_url( $url )      { return $this->host->get_cart_url(); }
    public function filter_checkout_url( $url )  { return $this->host->get_checkout_url(); }

    /** Rewrite nav menu items linking to local WC pages. */
    public function filter_nav_menu_items( $items, $args ) {
        if ( ! function_exists( 'wc_get_page_id' ) ) return $items;

        $map = array(
            wc_get_page_id( 'myaccount' ) => $this->host->get_account_url(),
            wc_get_page_id( 'cart' )      => $this->host->get_cart_url(),
            wc_get_page_id( 'checkout' )  => $this->host->get_checkout_url(),
        );

        foreach ( $items as &$item ) {
            if ( $item->object === 'page' && isset( $map[ (int) $item->object_id ] ) ) {
                $item->url = $map[ (int) $item->object_id ];
            }
        }
        return $items;
    }

    /** Banner on shop pages. */
    public function cart_notice() {
        static $shown = false;
        if ( $shown ) return;
        $shown = true;
        $cart_url  = esc_url( $this->host->get_cart_url() );
        $host_info = $this->host->get_host_info();
        $host_name = esc_html( $host_info['name'] );
        echo '<div class="znc-cart-redirect-notice">';
        echo '<span class="znc-notice-icon">&#x1F6D2;</span> ';
        printf(
            __( 'Items you add go to your <a href="%s"><strong>Global Net Cart</strong></a> on %s.', 'zinckles-net-cart' ),
            $cart_url, $host_name
        );
        echo '</div>';
    }

    /** Rewrite "View cart" link in add-to-cart message. */
    public function filter_add_to_cart_message( $message, $products ) {
        $cart_url = esc_url( $this->host->get_cart_url() );
        return preg_replace(
            '#<a[^>]*class="button wc-forward"[^>]*>[^<]*</a>#i',
            '<a href="' . $cart_url . '" class="button wc-forward">' .
                __( 'View Global Cart', 'zinckles-net-cart' ) . '</a>',
            $message
        );
    }

    /** Inline CSS for the notice banner. */
    public function enqueue_assets() {
        if ( ! function_exists( 'is_shop' ) ) return;
        if ( is_shop() || is_product() || is_product_category() || is_product_tag() ) {
            wp_add_inline_style( 'woocommerce-general', '
                .znc-cart-redirect-notice{background:linear-gradient(135deg,#7c3aed15,#4f46e515);
                border:1px solid #7c3aed40;border-radius:8px;padding:12px 16px;margin-bottom:20px;
                font-size:14px;color:#1a1145;display:flex;align-items:center;gap:8px}
                .znc-cart-redirect-notice a{color:#7c3aed;text-decoration:none}
                .znc-cart-redirect-notice a:hover{text-decoration:underline}
                .znc-notice-icon{font-size:18px}
            ' );
        }
    }

    private function is_enrolled() {
        $settings = get_site_option( 'znc_network_settings', array() );
        $blog_id  = get_current_blog_id();
        $blocked  = (array) ( $settings['blocked_sites'] ?? array() );
        if ( in_array( $blog_id, $blocked, true ) ) return false;
        $mode = $settings['enrollment_mode'] ?? 'opt-in';
        if ( $mode === 'opt-out' ) return true;
        $enrolled = (array) ( $settings['enrolled_sites'] ?? array() );
        return in_array( $blog_id, $enrolled, true );
    }
}
