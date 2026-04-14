<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Admin_Loader {

    public static function init() {
        add_filter( 'znc_cart_snapshot_enabled', array( __CLASS__, 'check_enrollment' ) );
        add_filter( 'znc_checkout_config', array( __CLASS__, 'get_checkout_config' ) );
    }

    public static function check_enrollment( $enabled ) {
        $settings = get_site_option( 'znc_network_settings', array() );
        $enrolled = (array) ( $settings['enrolled_sites'] ?? array() );
        $blog_id  = get_current_blog_id();
        return in_array( $blog_id, $enrolled, true );
    }

    public static function get_checkout_config( $config ) {
        $settings = get_site_option( 'znc_network_settings', array() );
        return array_merge( $config, array(
            'base_currency'  => $settings['base_currency'] ?? 'USD',
            'mixed_currency' => ! empty( $settings['mixed_currency'] ),
            'cart_expiry'    => absint( $settings['cart_expiry_days'] ?? 7 ),
            'max_items'      => absint( $settings['max_items'] ?? 100 ),
            'max_shops'      => absint( $settings['max_shops'] ?? 20 ),
        ) );
    }
}
