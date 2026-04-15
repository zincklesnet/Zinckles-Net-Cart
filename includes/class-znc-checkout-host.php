<?php
/**
 * Checkout Host Resolver — v1.4.1
 * Cached URL resolution, enrollment management, admin helpers.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Checkout_Host {

    private $host_id = null;
    private $urls = null;
    private $enrolled_cache = null;

    public function get_host_id() {
        if ( null !== $this->host_id ) return $this->host_id;
        $settings = get_site_option( 'znc_network_settings', array() );
        $configured = isset( $settings['checkout_host_id'] ) ? absint( $settings['checkout_host_id'] ) : 0;
        $this->host_id = ( $configured > 0 && get_blog_details( $configured ) ) ? $configured : get_main_site_id();
        return $this->host_id;
    }

    public function is_current_site_host() {
        return (int) get_current_blog_id() === (int) $this->get_host_id();
    }

    public function is_host( $blog_id ) {
        return absint( $blog_id ) === $this->get_host_id();
    }

    public function get_host_url() {
        return get_home_url( $this->get_host_id() );
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

    /* ── ENROLLMENT ───────────────────────────────────────────── */

    public function is_enrolled( $blog_id ) {
        $enrolled = $this->get_enrolled_ids();
        return in_array( (int) $blog_id, $enrolled, true );
    }

    public function get_enrolled_ids() {
        if ( null !== $this->enrolled_cache ) return $this->enrolled_cache;
        $settings = get_site_option( 'znc_network_settings', array() );
        $enrolled = isset( $settings['enrolled_sites'] ) ? (array) $settings['enrolled_sites'] : array();
        $blocked  = isset( $settings['blocked_sites'] ) ? (array) $settings['blocked_sites'] : array();
        $this->enrolled_cache = array_values( array_filter(
            array_map( 'intval', $enrolled ),
            function( $id ) use ( $blocked ) {
                return $id > 0 && ! in_array( $id, array_map( 'intval', $blocked ), true );
            }
        ) );
        return $this->enrolled_cache;
    }

    public function get_enrolled_shop_ids() {
        $host_id = $this->get_host_id();
        return array_values( array_filter( $this->get_enrolled_ids(), function( $id ) use ( $host_id ) {
            return $id !== $host_id;
        } ) );
    }

    public static function enroll( $blog_id ) {
        $blog_id  = (int) $blog_id;
        $settings = get_site_option( 'znc_network_settings', array() );
        if ( ! isset( $settings['enrolled_sites'] ) ) $settings['enrolled_sites'] = array();
        $enrolled = array_map( 'intval', (array) $settings['enrolled_sites'] );
        if ( ! in_array( $blog_id, $enrolled, true ) ) {
            $enrolled[] = $blog_id;
            $settings['enrolled_sites'] = $enrolled;
            update_site_option( 'znc_network_settings', $settings );
        }
        return true;
    }

    public static function remove( $blog_id ) {
        $blog_id  = (int) $blog_id;
        $settings = get_site_option( 'znc_network_settings', array() );
        if ( ! isset( $settings['enrolled_sites'] ) ) return true;
        $enrolled = array_map( 'intval', (array) $settings['enrolled_sites'] );
        $enrolled = array_values( array_diff( $enrolled, array( $blog_id ) ) );
        $settings['enrolled_sites'] = $enrolled;
        update_site_option( 'znc_network_settings', $settings );
        return true;
    }

    /* ── CACHED URL METHODS ───────────────────────────────────── */

    private function resolve_urls() {
        if ( null !== $this->urls ) return $this->urls;
        $cache_key = 'znc_host_urls_' . $this->get_host_id();
        $cached    = get_site_transient( $cache_key );
        if ( is_array( $cached ) && ! empty( $cached['cart'] ) ) {
            $this->urls = $cached;
            return $this->urls;
        }

        $host_id = $this->get_host_id();
        $current = get_current_blog_id();
        $sw      = ( (int) $current !== (int) $host_id );
        if ( $sw ) switch_to_blog( $host_id );

        $urls = array();

        // Cart page
        $cart_page_id = get_option( 'znc_cart_page_id', 0 );
        if ( $cart_page_id && get_post_status( $cart_page_id ) === 'publish' ) {
            $urls['cart'] = get_permalink( $cart_page_id );
        } else {
            $pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 's' => '[znc_global_cart]', 'numberposts' => 1, 'fields' => 'ids' ) );
            if ( ! empty( $pages ) ) {
                $urls['cart'] = get_permalink( $pages[0] );
                update_option( 'znc_cart_page_id', $pages[0] );
            } elseif ( function_exists( 'wc_get_cart_url' ) ) {
                $urls['cart'] = wc_get_cart_url();
            } else {
                $urls['cart'] = home_url( '/cart/' );
            }
        }

        // Checkout page
        $co_page_id = get_option( 'znc_checkout_page_id', 0 );
        if ( $co_page_id && get_post_status( $co_page_id ) === 'publish' ) {
            $urls['checkout'] = get_permalink( $co_page_id );
        } else {
            $pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 's' => '[znc_checkout]', 'numberposts' => 1, 'fields' => 'ids' ) );
            if ( ! empty( $pages ) ) {
                $urls['checkout'] = get_permalink( $pages[0] );
                update_option( 'znc_checkout_page_id', $pages[0] );
            } elseif ( function_exists( 'wc_get_checkout_url' ) ) {
                $urls['checkout'] = wc_get_checkout_url();
            } else {
                $urls['checkout'] = home_url( '/checkout/' );
            }
        }

        // My Account
        $urls['account']         = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
        $urls['orders']          = trailingslashit( $urls['account'] ) . 'orders/';
        $urls['net_cart_orders'] = trailingslashit( $urls['account'] ) . 'net-cart-orders/';

        if ( $sw ) restore_current_blog();
        $this->urls = $urls;
        set_site_transient( $cache_key, $urls, HOUR_IN_SECONDS );
        return $this->urls;
    }

    public function get_cart_url()           { return $this->resolve_urls()['cart']; }
    public function get_checkout_url()       { return $this->resolve_urls()['checkout']; }
    public function get_account_url()        { return $this->resolve_urls()['account']; }
    public function get_orders_url()         { return $this->resolve_urls()['orders']; }
    public function get_net_cart_orders_url() { return $this->resolve_urls()['net_cart_orders']; }

    public function flush_url_cache() {
        delete_site_transient( 'znc_host_urls_' . $this->get_host_id() );
        $this->urls = null;
    }

    /* ── ADMIN HELPERS ────────────────────────────────────────── */

    public static function get_all_sites_for_admin() {
        global $wpdb;
        $sites    = get_sites( array( 'number' => 200, 'fields' => 'ids' ) );
        $settings = get_site_option( 'znc_network_settings', array() );
        $enrolled = isset( $settings['enrolled_sites'] ) ? array_map( 'intval', (array) $settings['enrolled_sites'] ) : array();
        $host_id  = ( new self() )->get_host_id();
        $results  = array();

        foreach ( $sites as $sid ) {
            $sid     = (int) $sid;
            $details = get_blog_details( $sid );
            if ( ! $details ) continue;
            $prefix = $wpdb->get_blog_prefix( $sid );
            $has_wc = (bool) $wpdb->get_var( "SELECT option_value FROM {$prefix}options WHERE option_name = 'woocommerce_version' LIMIT 1" );
            $has_mycred = (bool) $wpdb->get_var( "SELECT option_value FROM {$prefix}options WHERE option_name = 'mycred_pref_core' LIMIT 1" );
            $has_gp = (bool) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}posts WHERE post_type = 'point-type' AND post_status = 'publish'" );
            $product_count = $has_wc ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}posts WHERE post_type = 'product' AND post_status = 'publish'" ) : 0;
            $results[] = array(
                'blog_id'       => $sid,
                'blogname'      => $details->blogname,
                'siteurl'       => $details->siteurl,
                'is_enrolled'   => in_array( $sid, $enrolled, true ),
                'is_host'       => ( $sid === $host_id ),
                'has_wc'        => $has_wc,
                'has_mycred'    => $has_mycred,
                'has_gamipress' => $has_gp,
                'product_count' => $product_count,
            );
        }
        return $results;
    }
}
