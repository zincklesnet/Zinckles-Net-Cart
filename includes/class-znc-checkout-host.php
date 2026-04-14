<?php
/**
 * Checkout Host Resolver — v1.3.1 FIX.
 *
 * FIXED: All URL methods now cache results in a site transient.
 * switch_to_blog() runs ONCE per hour, not 6-12x per page load.
 * This eliminates the memory exhaustion on enrolled subsites.
 *
 * @package ZincklesNetCart
 * @since   1.3.0
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Checkout_Host {

    private $host_id = null;
    private $urls    = null;

    /**
     * Get the blog ID of the checkout host site.
     */
    public function get_host_id() {
        if ( null !== $this->host_id ) return $this->host_id;

        $settings   = get_site_option( 'znc_network_settings', array() );
        $configured = isset( $settings['checkout_host_id'] ) ? absint( $settings['checkout_host_id'] ) : 0;

        if ( $configured > 0 && get_blog_details( $configured ) ) {
            $this->host_id = $configured;
        } else {
            $this->host_id = get_main_site_id();
        }

        return $this->host_id;
    }

    public function is_current_site_host() {
        return get_current_blog_id() === $this->get_host_id();
    }

    public function is_host( $blog_id ) {
        return absint( $blog_id ) === $this->get_host_id();
    }

    public function get_host_url() {
        return get_home_url( $this->get_host_id() );
    }

    /* ─────────────────────────────────────────────────────────────
     * CACHED URL METHODS — single switch_to_blog() per hour
     * ───────────────────────────────────────────────────────────── */

    private function resolve_urls() {
        if ( null !== $this->urls ) return $this->urls;

        $cache_key = 'znc_host_urls_' . $this->get_host_id();
        $cached    = get_site_transient( $cache_key );

        if ( is_array( $cached ) && ! empty( $cached['cart'] ) ) {
            $this->urls = $cached;
            return $this->urls;
        }

        $host_id     = $this->get_host_id();
        $current     = get_current_blog_id();
        $need_switch = ( $current !== $host_id );

        if ( $need_switch ) {
            switch_to_blog( $host_id );
        }

        $urls = array();

        // Cart page
        $cart_page_id = get_option( 'znc_cart_page_id', 0 );
        if ( $cart_page_id ) {
            $urls['cart'] = get_permalink( $cart_page_id );
        } else {
            $pages = get_posts( array(
                'post_type'   => 'page',
                'post_status' => 'publish',
                's'           => '[znc_global_cart]',
                'numberposts' => 1,
                'fields'      => 'ids',
            ) );
            if ( ! empty( $pages ) ) {
                $urls['cart'] = get_permalink( $pages[0] );
                update_option( 'znc_cart_page_id', $pages[0] );
            } else {
                $urls['cart'] = home_url( '/net-cart/' );
            }
        }

        // Checkout page
        $checkout_page_id = get_option( 'znc_checkout_page_id', 0 );
        if ( $checkout_page_id ) {
            $urls['checkout'] = get_permalink( $checkout_page_id );
        } else {
            $pages = get_posts( array(
                'post_type'   => 'page',
                'post_status' => 'publish',
                's'           => '[znc_checkout]',
                'numberposts' => 1,
                'fields'      => 'ids',
            ) );
            if ( ! empty( $pages ) ) {
                $urls['checkout'] = get_permalink( $pages[0] );
                update_option( 'znc_checkout_page_id', $pages[0] );
            } else {
                $urls['checkout'] = home_url( '/net-checkout/' );
            }
        }

        // My Account
        if ( function_exists( 'wc_get_page_permalink' ) ) {
            $urls['account'] = wc_get_page_permalink( 'myaccount' );
        } else {
            $urls['account'] = home_url( '/my-account/' );
        }

        $urls['orders']          = trailingslashit( $urls['account'] ) . 'orders/';
        $urls['net_cart_orders'] = trailingslashit( $urls['account'] ) . 'net-cart-orders/';

        if ( $need_switch ) {
            restore_current_blog();
        }

        $this->urls = $urls;
        set_site_transient( $cache_key, $urls, HOUR_IN_SECONDS );

        return $this->urls;
    }

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

    public function get_net_cart_orders_url() {
        $urls = $this->resolve_urls();
        return $urls['net_cart_orders'];
    }

    public function flush_url_cache() {
        $host_id = $this->get_host_id();
        delete_site_transient( 'znc_host_urls_' . $host_id );
        $this->urls = null;
    }

    public function get_enrolled_shop_ids() {
        $settings = get_site_option( 'znc_network_settings', array() );
        $enrolled = (array) ( $settings['enrolled_sites'] ?? array() );
        $blocked  = (array) ( $settings['blocked_sites']  ?? array() );
        $host_id  = $this->get_host_id();

        return array_values( array_filter( $enrolled, function( $id ) use ( $blocked, $host_id ) {
            return absint( $id ) !== $host_id && ! in_array( absint( $id ), $blocked, true );
        } ) );
    }

    public function get_host_info() {
        $host_id = $this->get_host_id();
        $details = get_blog_details( $host_id );
        return array(
            'blog_id' => $host_id,
            'name'    => $details ? $details->blogname : 'Main Site',
            'url'     => $details ? $details->siteurl : network_home_url(),
            'is_main' => $host_id === get_main_site_id(),
        );
    }

    /**
     * Get all sites in the network with metadata for the admin UI.
     * Uses direct DB query — never switch_to_blog() in a loop.
     */
    public static function get_all_sites_for_admin() {
        global $wpdb;

        $sites    = get_sites( array( 'number' => 200, 'fields' => 'ids' ) );
        $settings = get_site_option( 'znc_network_settings', array() );
        $enrolled = (array) ( $settings['enrolled_sites'] ?? array() );
        $host_obj = new self();
        $host_id  = $host_obj->get_host_id();
        $results  = array();

        foreach ( $sites as $sid ) {
            $sid     = (int) $sid;
            $details = get_blog_details( $sid );
            if ( ! $details ) continue;

            $prefix    = $wpdb->get_blog_prefix( $sid );
            $has_wc    = (bool) $wpdb->get_var(
                "SELECT option_value FROM {$prefix}options WHERE option_name = 'woocommerce_version' LIMIT 1"
            );
            $product_count = $has_wc ? (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$prefix}posts WHERE post_type = 'product' AND post_status = 'publish'"
            ) : 0;

            $results[] = array(
                'blog_id'       => $sid,
                'blogname'      => $details->blogname,
                'siteurl'       => $details->siteurl,
                'is_enrolled'   => in_array( $sid, $enrolled, true ),
                'is_host'       => ( $sid === $host_id ),
                'has_wc'        => $has_wc,
                'product_count' => $product_count,
            );
        }

        return $results;
    }
}
