<?php
/**
 * REST Auth — HMAC-SHA256 request signing & verification.
 *
 * @package ZincklesNetCart
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class ZNC_REST_Auth {

    public function init() {
        add_filter( 'znc_rest_verify_request', array( $this, 'verify' ) );
    }

    /**
     * Verify an incoming signed request.
     */
    public function verify( $request ) {
        $timestamp = $request->get_header( 'X-ZNC-Timestamp' );
        $signature = $request->get_header( 'X-ZNC-Signature' );

        if ( ! $timestamp || ! $signature ) {
            return new WP_Error( 'znc_auth_missing', 'Missing authentication headers.', array( 'status' => 401 ) );
        }

        // Clock skew check.
        $settings  = get_site_option( 'znc_network_settings', array() );
        $max_skew  = absint( $settings['rest_clock_skew'] ?? 300 );
        $now       = time();

        if ( abs( $now - (int) $timestamp ) > $max_skew ) {
            return new WP_Error( 'znc_auth_expired', 'Request timestamp outside tolerance.', array( 'status' => 401 ) );
        }

        // Get secret — check site-level first, then network.
        $secret = get_option( 'znc_rest_shared_secret', '' );
        if ( ! $secret ) {
            $secret = $settings['rest_shared_secret'] ?? '';
        }

        if ( ! $secret ) {
            return new WP_Error( 'znc_auth_no_secret', 'No shared secret configured.', array( 'status' => 500 ) );
        }

        // IP whitelist.
        $whitelist = trim( $settings['rest_ip_whitelist'] ?? '' );
        if ( $whitelist ) {
            $allowed = array_map( 'trim', explode( ',', $whitelist ) );
            $ip      = $_SERVER['REMOTE_ADDR'] ?? '';
            if ( ! in_array( $ip, $allowed, true ) ) {
                return new WP_Error( 'znc_auth_ip', 'IP not whitelisted.', array( 'status' => 403 ) );
            }
        }

        // Verify HMAC.
        $url      = $request->get_route();
        $expected = hash_hmac( 'sha256', $timestamp . ':' . $url, $secret );

        if ( ! hash_equals( $expected, $signature ) ) {
            // Try full URL as well (connection test uses full URL).
            $full_url       = rest_url( $request->get_route() );
            $expected_full  = hash_hmac( 'sha256', $timestamp . ':' . $full_url, $secret );

            if ( ! hash_equals( $expected_full, $signature ) ) {
                return new WP_Error( 'znc_auth_invalid', 'Invalid signature.', array( 'status' => 401 ) );
            }
        }

        return true;
    }

    /**
     * Sign an outgoing request.
     */
    public static function sign_request( $url, $secret = null ) {
        if ( ! $secret ) {
            $settings = get_site_option( 'znc_network_settings', array() );
            $secret   = $settings['rest_shared_secret'] ?? '';
        }

        $timestamp = time();
        $signature = hash_hmac( 'sha256', $timestamp . ':' . $url, $secret );

        return array(
            'X-ZNC-Timestamp' => $timestamp,
            'X-ZNC-Signature' => $signature,
        );
    }
}
