<?php
/**
 * Checkout Host — Resolves URLs for global cart and checkout pages.
 *
 * v1.7.1 EMERGENCY FIX:
 *   - Added singleton instance() method
 *   - Added is_checkout_host() alias for is_current_site_host()
 *   - Added static proxy methods for get_cart_url() and get_checkout_url()
 *     so the bootstrap can call ZNC_Checkout_Host::get_checkout_url() etc.
 *
 * @package ZincklesNetCart
 * @since   1.6.1
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Checkout_Host {

    /* ────────────────────────────────────────────
     *  Singleton
     * ──────────────────────────────────────────── */
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /* ────────────────────────────────────────────
     *  Internal state
     * ──────────────────────────────────────────── */
    private $host_id = null;
    private $urls    = null;

    /* ────────────────────────────────────────────
     *  Host ID resolution
     * ──────────────────────────────────────────── */
    public function get_host_id() {
        if ( null !== $this->host_id ) {
            return $this->host_id;
        }

        $settings = get_site_option( 'znc_network_settings', array() );
        $configured = isset( $settings['checkout_host_id'] ) ? absint( $settings['checkout_host_id'] ) : 0;

        if ( $configured > 0 && get_blog_details( $configured ) ) {
            $this->host_id = $configured;
        } else {
            $this->host_id = get_main_site_id();
        }

        return $this->host_id;
    }

    /* ────────────────────────────────────────────
     *  Host checks
     * ──────────────────────────────────────────── */
    public function is_current_site_host() {
        return get_current_blog_id() === $this->get_host_id();
    }

    /** Alias used by v1.7.x bootstrap */
    public function is_checkout_host() {
        return $this->is_current_site_host();
    }

    public function is_host( $blog_id ) {
        return absint( $blog_id ) === $this->get_host_id();
    }

    public function get_host_url() {
        return get_home_url( $this->get_host_id() );
    }

    /* ────────────────────────────────────────────
     *  URL resolution (private)
     * ──────────────────────────────────────────── */
    private function resolve_urls() {
        if ( null !== $this->urls ) {
            return $this->urls;
        }

        $cache_key = 'znc_host_urls_' . $this->get_host_id();
        $cached    = get_site_transient( $cache_key );
        if ( is_array( $cached ) && ! empty( $cached['cart'] ) && ! empty( $cached['checkout'] ) ) {
            $this->urls = $cached;
            return $this->urls;
        }

        $host_id     = $this->get_host_id();
        $current_id  = get_current_blog_id();
        $switched    = ( $current_id !== $host_id );

        if ( $switched ) {
            switch_to_blog( $host_id );
        }

        $settings = get_site_option( 'znc_network_settings', array() );
        $urls     = array();

        // Cart page
        $cart_page_id = absint( $settings['cart_page_id'] ?? 0 );
        if ( $cart_page_id && get_post_status( $cart_page_id ) === 'publish' ) {
            $urls['cart'] = get_permalink( $cart_page_id );
        } else {
            $page = get_page_by_path( 'cart-g' );
            if ( $page && $page->post_status === 'publish' ) {
                $urls['cart'] = get_permalink( $page->ID );
            } else {
                global $wpdb;
                $found_id = $wpdb->get_var(
                    "SELECT ID FROM {$wpdb->posts}
                     WHERE post_type = 'page' AND post_status = 'publish'
                     AND post_content LIKE '%[znc_global_cart]%' LIMIT 1"
                );
                if ( $found_id ) {
                    $urls['cart'] = get_permalink( $found_id );
                } else {
                    $urls['cart'] = home_url( '/cart-g/' );
                }
            }
        }

        // Checkout page
        $checkout_page_id = absint( $settings['checkout_page_id'] ?? 0 );
        if ( $checkout_page_id && get_post_status( $checkout_page_id ) === 'publish' ) {
            $urls['checkout'] = get_permalink( $checkout_page_id );
        } else {
            $page = get_page_by_path( 'checkout-g' );
            if ( $page && $page->post_status === 'publish' ) {
                $urls['checkout'] = get_permalink( $page->ID );
            } else {
                global $wpdb;
                $found_id = $wpdb->get_var(
                    "SELECT ID FROM {$wpdb->posts}
                     WHERE post_type = 'page' AND post_status = 'publish'
                     AND post_content LIKE '%[znc_checkout]%' LIMIT 1"
                );
                if ( $found_id ) {
                    $urls['checkout'] = get_permalink( $found_id );
                } else {
                    $urls['checkout'] = home_url( '/checkout-g/' );
                }
            }
        }

        // Account and Orders
        if ( function_exists( 'wc_get_page_permalink' ) ) {
            $urls['account'] = wc_get_page_permalink( 'myaccount' );
        } else {
            $urls['account'] = home_url( '/my-account/' );
        }
        $urls['orders'] = trailingslashit( $urls['account'] ) . 'orders/';

        if ( $switched ) {
            restore_current_blog();
        }

        $this->urls = $urls;
        set_site_transient( $cache_key, $urls, HOUR_IN_SECONDS );

        return $this->urls;
    }

    /* ────────────────────────────────────────────
     *  Instance URL getters
     * ──────────────────────────────────────────── */
    public function get_cart_url() {
        $urls = $this->resolve_urls();
        return $urls['cart'];
    }

    public function get_checkout_url() {
        $urls = $this->resolve_urls();
        return $urls['checkout'];
    }

    public function get_account_url() {
        $urls = $this->resolve_urls();
        return $urls['account'];
    }

    public function get_orders_url() {
        $urls = $this->resolve_urls();
        return $urls['orders'];
    }

    /* ────────────────────────────────────────────
     *  Static proxy — so bootstrap can call
     *  ZNC_Checkout_Host::get_checkout_url()
     *  without having an instance variable.
     * ──────────────────────────────────────────── */
    public static function __callStatic( $name, $arguments ) {
        $allowed = array( 'get_cart_url', 'get_checkout_url', 'get_account_url', 'get_orders_url' );

        if ( in_array( $name, $allowed, true ) ) {
            $inst = self::instance();
            return call_user_func_array( array( $inst, $name ), $arguments );
        }

        trigger_error(
            'Call to undefined static method ZNC_Checkout_Host::' . esc_html( $name ) . '()',
            E_USER_ERROR
        );
    }

    /* ────────────────────────────────────────────
     *  Cache flush
     * ──────────────────────────────────────────── */
    public function flush_url_cache() {
        delete_site_transient( 'znc_host_urls_' . $this->get_host_id() );
        $this->urls = null;
    }

    /* ────────────────────────────────────────────
     *  Enrolled shops
     * ──────────────────────────────────────────── */
    public function get_enrolled_shop_ids() {
        $settings = get_site_option( 'znc_network_settings', array() );
        $enrolled = (array) ( $settings['enrolled_sites'] ?? array() );
        $blocked  = (array) ( $settings['blocked_sites']  ?? array() );
        $host_id  = $this->get_host_id();

        return array_values( array_filter( $enrolled, function ( $id ) use ( $blocked, $host_id ) {
            $id = absint( $id );
            return $id !== $host_id && ! in_array( $id, array_map( 'absint', $blocked ), true );
        } ) );
    }

    /* ────────────────────────────────────────────
     *  Host info
     * ──────────────────────────────────────────── */
    public function get_host_info() {
        $host_id = $this->get_host_id();
        $details = get_blog_details( $host_id );

        return array(
            'blog_id' => $host_id,
            'name'    => $details ? $details->blogname : 'Main',
            'url'     => $details ? $details->siteurl  : network_home_url(),
            'is_main' => $host_id === get_main_site_id(),
        );
    }
}
