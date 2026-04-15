<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Activator {

    public static function activate() {
        $settings = get_site_option( 'znc_network_settings', array() );
        if ( empty( $settings ) ) {
            update_site_option( 'znc_network_settings', array(
                'checkout_host_id' => get_main_site_id(),
                'enrollment_mode'  => 'opt-in',
                'base_currency'    => 'USD',
                'cart_expiry_days'  => 30,
                'clear_local_cart'  => 0,
                'debug_mode'        => 0,
                'enrolled_sites'    => array(),
            ) );
        }

        $security = get_site_option( 'znc_security_settings', array() );
        if ( empty( $security['hmac_secret'] ) ) {
            $security['hmac_secret']       = wp_generate_password( 64, true, true );
            $security['hmac_generated_at'] = current_time( 'mysql' );
            $security['clock_skew']        = 300;
            $security['rate_limit']        = 60;
            $security['ip_whitelist']      = '';
            update_site_option( 'znc_security_settings', $security );
        }

        // Schedule cart expiry purge cron
        if ( ! wp_next_scheduled( 'znc_purge_expired_carts' ) ) {
            wp_schedule_event( time(), 'daily', 'znc_purge_expired_carts' );
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'znc_purge_expired_carts' );
    }
}
