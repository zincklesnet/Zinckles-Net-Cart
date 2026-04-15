<?php
/**
 * REST Auth — v1.4.1
 * HMAC-based authentication for cross-subsite REST API calls.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_REST_Auth {

    private $secret;
    private $clock_skew;
    private $rate_limit;
    private $ip_whitelist;

    public function init() {
        $settings         = get_site_option( 'znc_network_settings', array() );
        $this->secret     = isset( $settings['hmac_secret'] ) ? $settings['hmac_secret'] : '';
        $this->clock_skew = isset( $settings['clock_skew'] ) ? (int) $settings['clock_skew'] : 300;
        $this->rate_limit = isset( $settings['rate_limit'] ) ? (int) $settings['rate_limit'] : 60;
        $this->ip_whitelist = isset( $settings['ip_whitelist'] )
            ? array_filter( array_map( 'trim', explode( "\n", $settings['ip_whitelist'] ) ) )
            : array();
    }

    public function sign_request( $payload, $timestamp = null ) {
        if ( ! $timestamp ) $timestamp = time();
        $data = $timestamp . '|' . wp_json_encode( $payload );
        return hash_hmac( 'sha256', $data, $this->secret );
    }

    public function verify_request( $signature, $payload, $timestamp ) {
        if ( empty( $this->secret ) ) return false;

        // Clock skew check
        $diff = abs( time() - (int) $timestamp );
        if ( $diff > $this->clock_skew ) {
            return new WP_Error( 'clock_skew', 'Request timestamp too far from server time.' );
        }

        // IP whitelist check
        if ( ! empty( $this->ip_whitelist ) ) {
            $ip = $this->get_client_ip();
            if ( ! in_array( $ip, $this->ip_whitelist, true ) ) {
                return new WP_Error( 'ip_blocked', 'IP not in whitelist.' );
            }
        }

        // Rate limit check
        if ( ! $this->check_rate_limit() ) {
            return new WP_Error( 'rate_limit', 'Rate limit exceeded.' );
        }

        // HMAC verify
        $expected = $this->sign_request( $payload, $timestamp );
        return hash_equals( $expected, $signature );
    }

    public static function generate_secret() {
        return wp_generate_password( 64, true, true );
    }

    private function check_rate_limit() {
        $key   = 'znc_rate_' . md5( $this->get_client_ip() );
        $count = get_transient( $key );
        if ( false === $count ) {
            set_transient( $key, 1, 60 );
            return true;
        }
        if ( (int) $count >= $this->rate_limit ) return false;
        set_transient( $key, (int) $count + 1, 60 );
        return true;
    }

    private function get_client_ip() {
        $headers = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );
        foreach ( $headers as $h ) {
            if ( ! empty( $_SERVER[ $h ] ) ) {
                $ip = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $h ] ) ) );
                return trim( $ip[0] );
            }
        }
        return '127.0.0.1';
    }
}
