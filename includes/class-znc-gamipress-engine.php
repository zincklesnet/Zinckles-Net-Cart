<?php
/**
 * GamiPress Engine — v1.4.2
 *
 * Detects all GamiPress point types across the multisite network,
 * provides balance checks, deductions, and refunds.
 *
 * v1.4.2 FIX: Changed post_type from 'point-type' to 'points-type'
 *             (GamiPress registers 'points-type' — plural with 's').
 *             Added detect_network_types() for cross-site scanning.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_GamiPress_Engine {

    public function init() {
        // Lazy-load — nothing needed on init
    }

    /**
     * Detect all GamiPress point types across the network.
     * Uses direct DB queries — no switch_to_blog().
     */
    public static function detect_all_types() {
        global $wpdb;
        $types = array();

        // Check main site first (GamiPress stores types as custom post types)
        if ( function_exists( 'gamipress_get_point_types' ) ) {
            $gp_types = gamipress_get_point_types();
            foreach ( $gp_types as $slug => $data ) {
                $types[ $slug ] = array(
                    'slug'     => $slug,
                    'label'    => isset( $data['plural_name'] ) ? $data['plural_name'] : $slug,
                    'singular' => isset( $data['singular_name'] ) ? $data['singular_name'] : $slug,
                    'plural'   => isset( $data['plural_name'] ) ? $data['plural_name'] : $slug,
                    'source'   => 'main_site',
                );
            }
        }

        // Scan enrolled subsites for additional types
        $settings = get_site_option( 'znc_network_settings', array() );
        $enrolled = isset( $settings['enrolled_sites'] ) ? (array) $settings['enrolled_sites'] : array();

        foreach ( $enrolled as $blog_id ) {
            $blog_id = (int) $blog_id;
            $prefix  = $wpdb->get_blog_prefix( $blog_id );

            // v1.4.2 FIX: GamiPress uses 'points-type' (plural with 's'), NOT 'point-type'
            $rows = $wpdb->get_results(
                "SELECT post_name, post_title FROM {$prefix}posts 
                 WHERE post_type = 'points-type' AND post_status = 'publish'"
            );

            if ( $rows ) {
                foreach ( $rows as $row ) {
                    $slug = $row->post_name;
                    if ( ! isset( $types[ $slug ] ) ) {
                        // Get singular name from postmeta
                        $singular = $wpdb->get_var( $wpdb->prepare(
                            "SELECT meta_value FROM {$prefix}postmeta 
                             WHERE post_id = (SELECT ID FROM {$prefix}posts WHERE post_name = %s AND post_type = 'points-type' LIMIT 1)
                             AND meta_key = '_gamipress_singular_name' LIMIT 1",
                            $slug
                        ) );

                        $types[ $slug ] = array(
                            'slug'     => $slug,
                            'label'    => $row->post_title,
                            'singular' => $singular ?: $row->post_title,
                            'plural'   => $row->post_title,
                            'source'   => 'subsite_' . $blog_id,
                        );
                    }
                }
            }
        }

        return $types;
    }

    /**
     * Detect all GamiPress point types across the entire network.
     * Scans ALL enrolled sites + main site via direct DB queries.
     * Mirror of ZNC_MyCred_Engine::detect_network_types().
     *
     * @since 1.4.2
     * @return array Array of [ slug => [ slug, label, singular, plural, blog_id ] ]
     */
    public static function detect_network_types() {
        global $wpdb;
        $types = array();

        $settings = get_site_option( 'znc_network_settings', array() );
        $enrolled = isset( $settings['enrolled_sites'] ) ? (array) $settings['enrolled_sites'] : array();

        // Always include the main site
        $main_id = get_main_site_id();
        $all_ids = array_unique( array_merge( array( $main_id ), array_map( 'intval', $enrolled ) ) );

        foreach ( $all_ids as $blog_id ) {
            $blog_id = (int) $blog_id;
            $prefix  = $wpdb->get_blog_prefix( $blog_id );

            // v1.4.2 FIX: 'points-type' not 'point-type'
            $rows = $wpdb->get_results(
                "SELECT ID, post_name, post_title FROM {$prefix}posts 
                 WHERE post_type = 'points-type' AND post_status = 'publish'"
            );

            if ( $rows ) {
                foreach ( $rows as $row ) {
                    $slug = $row->post_name;
                    if ( isset( $types[ $slug ] ) ) continue; // first-found wins

                    $singular = $wpdb->get_var( $wpdb->prepare(
                        "SELECT meta_value FROM {$prefix}postmeta 
                         WHERE post_id = %d AND meta_key = '_gamipress_singular_name' LIMIT 1",
                        $row->ID
                    ) );

                    $types[ $slug ] = array(
                        'slug'     => $slug,
                        'label'    => $row->post_title,
                        'singular' => $singular ?: $row->post_title,
                        'plural'   => $row->post_title,
                        'blog_id'  => $blog_id,
                    );
                }
            }
        }

        return $types;
    }

    /**
     * Get admin-configured settings per GamiPress point type.
     */
    public static function get_types_config() {
        $settings = get_site_option( 'znc_network_settings', array() );
        $config   = isset( $settings['gamipress_types_config'] ) ? $settings['gamipress_types_config'] : array();
        $types    = self::detect_all_types();

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

    /**
     * Get a user's GamiPress point balance.
     */
    public static function get_balance( $user_id, $point_type ) {
        if ( ! function_exists( 'gamipress_get_user_points' ) ) return 0;
        return (int) gamipress_get_user_points( $user_id, $point_type );
    }

    /**
     * Deduct GamiPress points from a user.
     */
    public static function deduct( $user_id, $amount, $point_type, $reason = '' ) {
        if ( ! function_exists( 'gamipress_deduct_points_to_user' ) ) return false;
        gamipress_deduct_points_to_user( $user_id, abs( $amount ), $point_type, array(
            'reason' => $reason ?: 'Net Cart checkout deduction',
        ) );
        return true;
    }

    /**
     * Award GamiPress points to a user (refund or earn).
     */
    public static function award( $user_id, $amount, $point_type, $reason = '' ) {
        if ( ! function_exists( 'gamipress_award_points_to_user' ) ) return false;
        gamipress_award_points_to_user( $user_id, abs( $amount ), $point_type, array(
            'reason' => $reason ?: 'Net Cart refund/earn',
        ) );
        return true;
    }

    /**
     * Validate that a user can cover a deduction.
     */
    public static function validate_deduction( $user_id, $amount, $point_type ) {
        return self::get_balance( $user_id, $point_type ) >= abs( $amount );
    }

    /**
     * Convert points to currency value.
     */
    public static function points_to_currency( $points, $point_type ) {
        $config = self::get_types_config();
        $rate   = isset( $config[ $point_type ]['exchange_rate'] ) ? $config[ $point_type ]['exchange_rate'] : 0;
        return $points * $rate;
    }
}
