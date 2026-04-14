<?php
/**
 * Checkout Host Resolver.
 *
 * Determines which site in the multisite network serves as the global
 * cart + checkout + My Account host. Defaults to main site; configurable
 * via Network Admin -> Net Cart -> Settings -> Checkout Host.
 *
 * @package ZincklesNetCart
 * @since   1.3.0
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Checkout_Host {

    private $host_id = null;

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

    /** Check if the current site IS the checkout host. */
    public function is_current_site_host() {
        return get_current_blog_id() === $this->get_host_id();
    }

    /** Check if a given blog_id is the host. */
    public function is_host( $blog_id ) {
        return absint( $blog_id ) === $this->get_host_id();
    }

    /** Get the home URL of the checkout host. */
    public function get_host_url() {
        return get_home_url( $this->get_host_id() );
    }

    /** Get the global cart page URL. */
    public function get_cart_url() {
        $host_id = $this->get_host_id();
        switch_to_blog( $host_id );
        $page_id = get_option( 'znc_cart_page_id', 0 );
        if ( $page_id ) {
            $url = get_permalink( $page_id );
        } else {
            $pages = get_posts( array(
                'post_type' => 'page', 'post_status' => 'publish',
                's' => '[znc_global_cart]', 'numberposts' => 1,
            ) );
            if ( ! empty( $pages ) ) {
                $url = get_permalink( $pages[0]->ID );
                update_option( 'znc_cart_page_id', $pages[0]->ID );
            } else {
                $url = home_url( '/net-cart/' );
            }
        }
        restore_current_blog();
        return $url;
    }

    /** Get the checkout page URL. */
    public function get_checkout_url() {
        $host_id = $this->get_host_id();
        switch_to_blog( $host_id );
        $page_id = get_option( 'znc_checkout_page_id', 0 );
        if ( $page_id ) {
            $url = get_permalink( $page_id );
        } else {
            $pages = get_posts( array(
                'post_type' => 'page', 'post_status' => 'publish',
                's' => '[znc_checkout]', 'numberposts' => 1,
            ) );
            if ( ! empty( $pages ) ) {
                $url = get_permalink( $pages[0]->ID );
                update_option( 'znc_checkout_page_id', $pages[0]->ID );
            } else {
                $url = home_url( '/net-checkout/' );
            }
        }
        restore_current_blog();
        return $url;
    }

    /** Get the My Account page URL on the checkout host. */
    public function get_account_url() {
        $host_id = $this->get_host_id();
        switch_to_blog( $host_id );
        if ( function_exists( 'wc_get_page_permalink' ) ) {
            $url = wc_get_page_permalink( 'myaccount' );
        } else {
            $url = home_url( '/my-account/' );
        }
        restore_current_blog();
        return $url;
    }

    /** Get the orders endpoint URL. */
    public function get_orders_url() {
        return trailingslashit( $this->get_account_url() ) . 'orders/';
    }

    /** Get Net Cart orders tab URL. */
    public function get_net_cart_orders_url() {
        return trailingslashit( $this->get_account_url() ) . 'net-cart-orders/';
    }

    /** Get all enrolled shop IDs (excluding host). */
    public function get_enrolled_shop_ids() {
        $settings = get_site_option( 'znc_network_settings', array() );
        $enrolled = (array) ( $settings['enrolled_sites'] ?? array() );
        $blocked  = (array) ( $settings['blocked_sites'] ?? array() );
        $host_id  = $this->get_host_id();
        return array_values( array_filter( $enrolled, function( $id ) use ( $blocked, $host_id ) {
            return absint( $id ) !== $host_id && ! in_array( absint( $id ), $blocked, true );
        } ) );
    }

    /** Get display info for the checkout host. */
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
}
