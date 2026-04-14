<?php
/**
 * MyCred Engine — v1.3.1 FIX.
 *
 * FIXED: Detects ALL MyCred point types across the network via
 * mycred_get_types() + direct DB scan of enrolled subsites.
 * Per-type exchange rates and enable/disable from network settings.
 *
 * @package ZincklesNetCart
 * @since   1.3.0
 */
defined( 'ABSPATH' ) || exit;

class ZNC_MyCred_Engine {

    private static $types = null;

    public static function init() {
        /* Lazy-load everything */
    }

    /**
     * Detect all MyCred point types across the network.
     */
    public static function get_all_point_types() {
        if ( self::$types !== null ) return self::$types;

        $cached = get_site_transient( 'znc_mycred_point_types' );
        if ( is_array( $cached ) && ! empty( $cached ) ) {
            self::$types = $cached;
            return self::$types;
        }

        self::$types = array();

        /* Check current site (checkout host) */
        if ( function_exists( 'mycred_get_types' ) ) {
            $types = mycred_get_types();
            foreach ( $types as $slug => $label ) {
                $mycred = mycred( $slug );
                self::$types[ $slug ] = array(
                    'slug'     => $slug,
                    'label'    => $label,
                    'singular' => $mycred ? $mycred->singular() : $label,
                    'plural'   => $mycred ? $mycred->plural() : $label,
                    'prefix'   => $mycred ? $mycred->before : '',
                    'suffix'   => $mycred ? $mycred->after : '',
                    'source'   => 'host',
                );
            }
        }

        /* Scan enrolled subsites for additional types via direct DB */
        $settings = get_site_option( 'znc_network_settings', array() );
        $enrolled = (array) ( $settings['enrolled_sites'] ?? array() );
        $host     = new ZNC_Checkout_Host();
        $host_id  = $host->get_host_id();

        foreach ( $enrolled as $blog_id ) {
            if ( absint( $blog_id ) === $host_id ) continue;

            global $wpdb;
            $prefix = $wpdb->get_blog_prefix( absint( $blog_id ) );
            $raw    = $wpdb->get_var(
                "SELECT option_value FROM {$prefix}options WHERE option_name = 'mycred_types' LIMIT 1"
            );
            if ( $raw ) {
                $subsite_types = maybe_unserialize( $raw );
                if ( is_array( $subsite_types ) ) {
                    foreach ( $subsite_types as $slug => $label ) {
                        if ( ! isset( self::$types[ $slug ] ) ) {
                            self::$types[ $slug ] = array(
                                'slug'     => $slug,
                                'label'    => $label,
                                'singular' => $label,
                                'plural'   => $label,
                                'prefix'   => '',
                                'suffix'   => '',
                                'source'   => 'subsite_' . $blog_id,
                            );
                        }
                    }
                }
            }
        }

        if ( empty( self::$types ) ) {
            self::$types['mycred_default'] = array(
                'slug'     => 'mycred_default',
                'label'    => 'ZCreds',
                'singular' => 'ZCred',
                'plural'   => 'ZCreds',
                'prefix'   => '',
                'suffix'   => '',
                'source'   => 'default',
            );
        }

        set_site_transient( 'znc_mycred_point_types', self::$types, 3600 );
        return self::$types;
    }

    /**
     * Get admin-configured settings per point type.
     */
    public static function get_types_config() {
        $settings = get_site_option( 'znc_network_settings', array() );
        $config   = (array) ( $settings['mycred_types_config'] ?? array() );
        $types    = self::get_all_point_types();

        foreach ( $types as $slug => $info ) {
            if ( ! isset( $config[ $slug ] ) ) {
                $config[ $slug ] = array(
                    'enabled'       => 0,
                    'exchange_rate' => 0,
                    'max_percent'   => 100,
                );
            }
            $config[ $slug ]['info'] = $info;
        }

        return $config;
    }

    public static function get_balance( $user_id, $type_slug = 'mycred_default' ) {
        if ( ! function_exists( 'mycred_get_users_balance' ) ) return 0;
        return (float) mycred_get_users_balance( $user_id, $type_slug );
    }

    public static function deduct( $user_id, $amount, $type_slug = 'mycred_default', $ref = 'znc_checkout', $entry = '' ) {
        if ( ! function_exists( 'mycred' ) ) return false;
        $mycred = mycred( $type_slug );
        if ( ! $mycred ) return false;
        $mycred->update_users_balance( $user_id, -abs( $amount ), $type_slug );
        $mycred->add_to_log( $ref, $user_id, -abs( $amount ), $entry ?: 'Net Cart checkout deduction', '', '', $type_slug );
        return true;
    }

    public static function refund( $user_id, $amount, $type_slug = 'mycred_default', $ref = 'znc_refund', $entry = '' ) {
        if ( ! function_exists( 'mycred' ) ) return false;
        $mycred = mycred( $type_slug );
        if ( ! $mycred ) return false;
        $mycred->update_users_balance( $user_id, abs( $amount ), $type_slug );
        $mycred->add_to_log( $ref, $user_id, abs( $amount ), $entry ?: 'Net Cart refund', '', '', $type_slug );
        return true;
    }

    public static function validate_deduction( $user_id, $amount, $type_slug = 'mycred_default' ) {
        $balance = self::get_balance( $user_id, $type_slug );
        return $balance >= abs( $amount );
    }

    public static function points_to_currency( $points, $type_slug = 'mycred_default' ) {
        $config = self::get_types_config();
        $rate   = $config[ $type_slug ]['exchange_rate'] ?? 0;
        return $points * $rate;
    }
}
