<?php
defined( 'ABSPATH' ) || exit;
class ZNC_Shop_Settings {
    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }
    public function register_routes() {
        register_rest_route( 'znc/v1', '/shop-settings', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_settings' ),
            'permission_callback' => '__return_true',
        ) );
    }
    public function get_settings() {
        $data = array(
            'blog_id'    => get_current_blog_id(),
            'name'       => get_bloginfo( 'name' ),
            'url'        => home_url(),
            'currency'   => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD',
            'symbol'     => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$',
            'has_wc'     => class_exists( 'WooCommerce' ),
            'has_mycred' => function_exists( 'mycred' ),
            'mycred_types' => function_exists( 'mycred_get_types' ) ? mycred_get_types() : array(),
        );
        return rest_ensure_response( $data );
    }
}
