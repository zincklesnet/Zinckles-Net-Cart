<?php
defined( 'ABSPATH' ) || exit;

class ZNC_REST_Auth {

    public static function init() {
        /* Auth is checked per-request in REST endpoints */
    }

    public static function sign( $payload ) {
        $secret    = get_site_option( 'znc_rest_secret', '' );
        $timestamp = time();
        $body      = wp_json_encode( $payload );
        $signature = hash_hmac( 'sha256', $timestamp . '|' . $body, $secret );

        return array(
            'X-ZNC-Timestamp' => $timestamp,
            'X-ZNC-Signature' => $signature,
        );
    }

    public static function verify( $request ) {
        $secret    = get_site_option( 'znc_rest_secret', '' );
        $timestamp = $request->get_header( 'X-ZNC-Timestamp' );
        $signature = $request->get_header( 'X-ZNC-Signature' );

        if ( ! $timestamp || ! $signature ) {
            return new WP_Error( 'znc_auth_missing', 'Missing authentication headers.', array( 'status' => 401 ) );
        }

        $settings  = get_site_option( 'znc_network_settings', array() );
        $skew      = absint( $settings['clock_skew'] ?? 300 );

        if ( abs( time() - (int) $timestamp ) > $skew ) {
            return new WP_Error( 'znc_auth_expired', 'Request timestamp expired.', array( 'status' => 401 ) );
        }

        $body     = $request->get_body();
        $expected = hash_hmac( 'sha256', $timestamp . '|' . $body, $secret );

        if ( ! hash_equals( $expected, $signature ) ) {
            return new WP_Error( 'znc_auth_invalid', 'Invalid signature.', array( 'status' => 401 ) );
        }

        return true;
    }
}
