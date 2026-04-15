<?php
/**
 * MyCred Engine — v1.4.1
 * Detects and manages all MyCred point types across the network.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_MyCred_Engine {

    public function init() {}

    /**
     * Detect all MyCred point types from the current site.
     * Called by AJAX auto-detect handler.
     */
    public static function detect_all_types() {
        $types = array();

        if ( ! function_exists( 'mycred' ) ) {
            // Try loading from options directly
            $raw = get_option( 'mycred_types', array() );
            if ( ! empty( $raw ) && is_array( $raw ) ) {
                foreach ( $raw as $slug => $label ) {
                    $types[ $slug ] = array(
                        'label'         => $label,
                        'slug'          => $slug,
                        'singular'      => $label,
                        'plural'        => $label,
                        'prefix'        => '',
                        'suffix'        => '',
                        'exchange_rate' => 1,
                        'enabled'       => 1,
                    );
                }
            }
            return $types;
        }

        // MyCred is active — use API
        $registered = mycred_get_types();
        if ( empty( $registered ) ) return $types;

        foreach ( $registered as $slug => $label ) {
            $core = mycred( $slug );
            $types[ $slug ] = array(
                'label'         => $label,
                'slug'          => $slug,
                'singular'      => $core->singular(),
                'plural'        => $core->plural(),
                'prefix'        => $core->before,
                'suffix'        => $core->after,
                'exchange_rate' => 1,
                'enabled'       => 1,
            );
        }

        return $types;
    }

    /**
     * Detect MyCred types across all enrolled subsites.
     */
    public static function detect_network_types() {
        $settings = get_site_option( 'znc_network_settings', array() );
        $enrolled = isset( $settings['enrolled_sites'] ) ? (array) $settings['enrolled_sites'] : array();
        if ( ! in_array( get_main_site_id(), $enrolled ) ) {
            $enrolled[] = get_main_site_id();
        }

        $all_types = array();

        foreach ( $enrolled as $blog_id ) {
            switch_to_blog( $blog_id );
            $site_types = self::detect_all_types();
            foreach ( $site_types as $slug => $type ) {
                if ( ! isset( $all_types[ $slug ] ) ) {
                    $type['blog_ids'] = array( (int) $blog_id );
                    $all_types[ $slug ] = $type;
                } else {
                    $all_types[ $slug ]['blog_ids'][] = (int) $blog_id;
                }
            }
            restore_current_blog();
        }

        return $all_types;
    }

    /**
     * Get user balance for a specific point type.
     */
    public static function get_balance( $user_id, $type = 'mycred_default' ) {
        if ( ! function_exists( 'mycred_get_users_balance' ) ) return 0;
        return (float) mycred_get_users_balance( $user_id, $type );
    }

    /**
     * Deduct points from user.
     */
    public static function deduct( $user_id, $amount, $type = 'mycred_default', $ref = 'znc_purchase', $data = array() ) {
        if ( ! function_exists( 'mycred' ) ) return false;
        $core = mycred( $type );
        if ( ! $core ) return false;
        return $core->update_users_balance( $user_id, 0 - abs( $amount ), $ref, $data );
    }

    /**
     * Award points to user.
     */
    public static function award( $user_id, $amount, $type = 'mycred_default', $ref = 'znc_refund', $data = array() ) {
        if ( ! function_exists( 'mycred' ) ) return false;
        $core = mycred( $type );
        if ( ! $core ) return false;
        return $core->update_users_balance( $user_id, abs( $amount ), $ref, $data );
    }

    /**
     * Validate user has enough points for a deduction.
     */
    public static function validate_deduction( $user_id, $amount, $type = 'mycred_default' ) {
        $balance = self::get_balance( $user_id, $type );
        return $balance >= abs( $amount );
    }

    /**
     * Convert points to currency value.
     */
    public static function points_to_currency( $amount, $type = 'mycred_default' ) {
        $settings = get_site_option( 'znc_network_settings', array() );
        $config   = isset( $settings['mycred_types_config'][ $type ] ) ? $settings['mycred_types_config'][ $type ] : array();
        $rate     = isset( $config['exchange_rate'] ) ? (float) $config['exchange_rate'] : 1;
        return $amount * $rate;
    }

    /**
     * Get formatted balance string for display.
     */
    public static function get_formatted_balance( $user_id, $type = 'mycred_default' ) {
        $balance = self::get_balance( $user_id, $type );
        if ( function_exists( 'mycred' ) ) {
            $core = mycred( $type );
            if ( $core ) {
                return $core->format_creds( $balance );
            }
        }
        return number_format( $balance, 0 ) . ' points';
    }
}
