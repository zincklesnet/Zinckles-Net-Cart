<?php
defined( 'ABSPATH' ) || exit;
class ZNC_REST_Auth {
    public function init() {}
    public static function sign( $payload ) {
        $secret = get_site_option( 'znc_rest_secret', '' );
        $ts     = time();
        $data   = $ts . '|' . wp_json_encode( $payload );
        return array( 'signature' => hash_hmac( 'sha256', $data, $secret ), 'timestamp' => $ts );
    }
    public static function verify( $signature, $timestamp, $payload ) {
        $secret   = get_site_option( 'znc_rest_secret', '' );
        $settings = get_site_option( 'znc_network_settings', array() );
        $skew     = absint( $settings['clock_skew'] ?? 300 );
        if ( abs( time() - $timestamp ) > $skew ) return false;
        $data     = $timestamp . '|' . wp_json_encode( $payload );
        $expected = hash_hmac( 'sha256', $data, $secret );
        return hash_equals( $expected, $signature );
    }
}
