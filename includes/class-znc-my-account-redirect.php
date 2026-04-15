<?php
/**
 * My Account Redirect — v1.4.1
 * Redirects My Account pages on enrolled subsites to the main checkout host site.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_My_Account_Redirect {

    private $host_url = '';
    private $host_id  = 0;

    public function init() {
        if ( is_main_site() ) return;

        $settings     = get_site_option( 'znc_network_settings', array() );
        $this->host_id = isset( $settings['checkout_host_id'] ) ? (int) $settings['checkout_host_id'] : get_main_site_id();
        $enrolled     = isset( $settings['enrolled_sites'] ) ? (array) $settings['enrolled_sites'] : array();

        if ( ! in_array( get_current_blog_id(), $enrolled ) ) return;

        // Cache host URL
        $this->host_url = get_blog_option( $this->host_id, 'siteurl' );
        if ( empty( $this->host_url ) ) return;

        // Override WC page URLs
        add_filter( 'woocommerce_get_myaccount_page_permalink', array( $this, 'redirect_myaccount' ) );
        add_filter( 'woocommerce_get_cart_url', array( $this, 'redirect_cart' ) );
        add_filter( 'woocommerce_get_checkout_url', array( $this, 'redirect_checkout' ) );

        // Override nav menu items
        add_filter( 'wp_nav_menu_objects', array( $this, 'rewrite_nav_items' ), 20, 2 );

        // Template redirect for direct access
        add_action( 'template_redirect', array( $this, 'maybe_redirect' ) );
    }

    public function redirect_myaccount( $url ) {
        $page_id = get_blog_option( $this->host_id, 'woocommerce_myaccount_page_id' );
        if ( $page_id ) {
            switch_to_blog( $this->host_id );
            $url = get_permalink( $page_id );
            restore_current_blog();
        } else {
            $url = trailingslashit( $this->host_url ) . 'my-account/';
        }
        return $url;
    }

    public function redirect_cart( $url ) {
        $settings = get_site_option( 'znc_network_settings', array() );
        $page_id  = ! empty( $settings['cart_page_id'] ) ? (int) $settings['cart_page_id'] : 0;

        if ( $page_id ) {
            switch_to_blog( $this->host_id );
            $url = get_permalink( $page_id );
            restore_current_blog();
        } else {
            $page_id = get_blog_option( $this->host_id, 'woocommerce_cart_page_id' );
            if ( $page_id ) {
                switch_to_blog( $this->host_id );
                $url = get_permalink( $page_id );
                restore_current_blog();
            }
        }
        return $url;
    }

    public function redirect_checkout( $url ) {
        $settings = get_site_option( 'znc_network_settings', array() );
        $page_id  = ! empty( $settings['checkout_page_id'] ) ? (int) $settings['checkout_page_id'] : 0;

        if ( $page_id ) {
            switch_to_blog( $this->host_id );
            $url = get_permalink( $page_id );
            restore_current_blog();
        } else {
            $page_id = get_blog_option( $this->host_id, 'woocommerce_checkout_page_id' );
            if ( $page_id ) {
                switch_to_blog( $this->host_id );
                $url = get_permalink( $page_id );
                restore_current_blog();
            }
        }
        return $url;
    }

    public function rewrite_nav_items( $items, $args ) {
        $myaccount_id = (int) get_option( 'woocommerce_myaccount_page_id' );
        $cart_id      = (int) get_option( 'woocommerce_cart_page_id' );
        $checkout_id  = (int) get_option( 'woocommerce_checkout_page_id' );

        foreach ( $items as &$item ) {
            $obj_id = (int) $item->object_id;
            if ( $obj_id === $myaccount_id && $myaccount_id > 0 ) {
                $item->url = $this->redirect_myaccount( $item->url );
            } elseif ( $obj_id === $cart_id && $cart_id > 0 ) {
                $item->url = $this->redirect_cart( $item->url );
            } elseif ( $obj_id === $checkout_id && $checkout_id > 0 ) {
                $item->url = $this->redirect_checkout( $item->url );
            }
        }
        return $items;
    }

    public function maybe_redirect() {
        if ( ! function_exists( 'is_account_page' ) ) return;

        // Redirect My Account page visits on subsites
        if ( is_account_page() ) {
            $url = $this->redirect_myaccount( '' );
            if ( $url ) {
                wp_redirect( $url, 302 );
                exit;
            }
        }
    }
}
