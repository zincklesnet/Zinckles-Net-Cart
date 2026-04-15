<?php
/**
 * GamiPress↔MyCred Bridge — Admin controls for point transfer & sync.
 *
 * Features:
 *  • Auto-detect all GamiPress point types across all subsites
 *  • Auto-detect all MyCred point types
 *  • Admin interface: pick user, source type, destination type
 *  • Configurable exchange rates
 *  • Import / export / transfer operations
 *  • Bulk transfer for all subscribers
 *  • Full audit logging
 *
 * @package ZincklesNetCart
 * @since   1.7.0
 */
defined( 'ABSPATH' ) || exit;

class ZNC_GamiPress_Engine {

    /** Option key for bridge settings */
    const OPTION_KEY = 'znc_points_bridge_settings';

    /** Log option key */
    const LOG_KEY    = 'znc_points_bridge_log';

    public function init() {
        // Network admin menu is handled by ZNC_Network_Admin.
        // We only register AJAX handlers here.
        add_action( 'wp_ajax_znc_bridge_detect_types', array( $this, 'ajax_detect_types' ) );
        add_action( 'wp_ajax_znc_bridge_transfer',     array( $this, 'ajax_transfer' ) );
        add_action( 'wp_ajax_znc_bridge_bulk_transfer', array( $this, 'ajax_bulk_transfer' ) );
        add_action( 'wp_ajax_znc_bridge_get_log',      array( $this, 'ajax_get_log' ) );
        add_action( 'wp_ajax_znc_bridge_save_rates',    array( $this, 'ajax_save_rates' ) );
    }

    /* ──────────────────────────────────────────────────────────────
     *  DETECTION
     * ──────────────────────────────────────────────────────────── */

    /**
     * Detect all GamiPress point types across the multisite.
     *
     * @return array [ blog_id => [ [ 'slug' => '...', 'label' => '...' ], ... ] ]
     */
    public static function detect_gamipress_types() {
        $result = array();
        $sites  = get_sites( array( 'number' => 200 ) );

        foreach ( $sites as $site ) {
            switch_to_blog( $site->blog_id );

            // GamiPress stores point types as a custom post type
            if ( post_type_exists( 'points-type' ) ) {
                $types = get_posts( array(
                    'post_type'   => 'points-type',
                    'numberposts' => 50,
                    'post_status' => 'publish',
                ) );

                if ( ! empty( $types ) ) {
                    $blog_types = array();
                    foreach ( $types as $type ) {
                        $blog_types[] = array(
                            'slug'  => $type->post_name,
                            'label' => $type->post_title,
                            'id'    => $type->ID,
                        );
                    }
                    $details = get_blog_details( $site->blog_id );
                    $result[ $site->blog_id ] = array(
                        'blog_name' => $details ? $details->blogname : "Blog #{$site->blog_id}",
                        'types'     => $blog_types,
                    );
                }
            }

            restore_current_blog();
        }

        return $result;
    }

    /**
     * Detect all MyCred point types (network-wide on main site).
     *
     * @return array [ type_key => label ]
     */
    public static function detect_mycred_types() {
        if ( ! function_exists( 'mycred_get_types' ) ) {
            return array();
        }
        return mycred_get_types();
    }

    /**
     * Get a user's GamiPress balance for a specific point type.
     *
     * @param int    $user_id
     * @param string $point_type_slug
     * @param int    $blog_id   (switches to that blog to read)
     * @return int
     */
    public static function get_gamipress_balance( $user_id, $point_type_slug, $blog_id = 0 ) {
        if ( $blog_id ) switch_to_blog( $blog_id );

        $balance = 0;
        if ( function_exists( 'gamipress_get_user_points' ) ) {
            $balance = (int) gamipress_get_user_points( $user_id, $point_type_slug );
        }

        if ( $blog_id ) restore_current_blog();
        return $balance;
    }

    /* ──────────────────────────────────────────────────────────────
     *  EXCHANGE RATES
     * ──────────────────────────────────────────────────────────── */

    /**
     * Get saved exchange rates.
     *
     * @return array [ 'source_slug|dest_key' => rate ]
     */
    public static function get_rates() {
        $settings = get_site_option( self::OPTION_KEY, array() );
        return (array) ( $settings['rates'] ?? array() );
    }

    /**
     * Get specific exchange rate.
     *
     * @param string $gamipress_slug
     * @param string $mycred_key
     * @return float
     */
    public static function get_rate( $gamipress_slug, $mycred_key ) {
        $rates = self::get_rates();
        $key   = $gamipress_slug . '|' . $mycred_key;
        return (float) ( $rates[ $key ] ?? 1.0 );
    }

    /* ──────────────────────────────────────────────────────────────
     *  TRANSFER OPERATIONS
     * ──────────────────────────────────────────────────────────── */

    /**
     * Transfer GamiPress points to MyCred for a single user.
     *
     * @param int    $user_id
     * @param string $gami_slug     GamiPress point type slug.
     * @param int    $gami_blog_id  Blog where GamiPress points live.
     * @param string $mycred_key    MyCred point type key.
     * @param float  $amount        GamiPress amount to transfer (0 = all).
     * @param float  $rate          Exchange rate (GamiPress → MyCred).
     * @return array|WP_Error       [ 'deducted' => int, 'credited' => float ]
     */
    public static function transfer_points( $user_id, $gami_slug, $gami_blog_id, $mycred_key, $amount = 0, $rate = 1.0 ) {
        // Validate GamiPress
        $gami_balance = self::get_gamipress_balance( $user_id, $gami_slug, $gami_blog_id );

        if ( $amount <= 0 ) {
            $amount = $gami_balance; // Transfer all
        }

        if ( $amount <= 0 ) {
            return new WP_Error( 'znc_no_balance', __( 'No GamiPress points to transfer.', 'zinckles-net-cart' ) );
        }

        if ( $gami_balance < $amount ) {
            return new WP_Error( 'znc_insufficient', sprintf(
                __( 'User only has %d %s points (tried to transfer %d).', 'zinckles-net-cart' ),
                $gami_balance, $gami_slug, $amount
            ) );
        }

        // Validate MyCred
        if ( ! function_exists( 'mycred_add' ) ) {
            return new WP_Error( 'znc_no_mycred', __( 'MyCred is not available.', 'zinckles-net-cart' ) );
        }

        $mycred_amount = round( $amount * $rate, 2 );

        // Deduct from GamiPress
        switch_to_blog( $gami_blog_id );
        if ( function_exists( 'gamipress_deduct_points_to_user' ) ) {
            gamipress_deduct_points_to_user( $user_id, (int) $amount, $gami_slug );
        } else {
            // Fallback: direct meta update
            $current = (int) get_user_meta( $user_id, '_gamipress_' . $gami_slug . '_points', true );
            update_user_meta( $user_id, '_gamipress_' . $gami_slug . '_points', max( 0, $current - (int) $amount ) );
        }
        restore_current_blog();

        // Credit to MyCred
        $log_entry = sprintf(
            __( 'Bridge transfer: %d %s (GamiPress) → %s %s (MyCred) at rate %s', 'zinckles-net-cart' ),
            $amount, $gami_slug, number_format( $mycred_amount, 2 ), $mycred_key, $rate
        );

        $result = mycred_add(
            'znc_bridge_transfer',
            $user_id,
            $mycred_amount,
            $log_entry,
            0,
            $mycred_key
        );

        if ( $result === false ) {
            // Rollback GamiPress deduction
            switch_to_blog( $gami_blog_id );
            if ( function_exists( 'gamipress_award_points_to_user' ) ) {
                gamipress_award_points_to_user( $user_id, (int) $amount, $gami_slug );
            }
            restore_current_blog();

            return new WP_Error( 'znc_credit_failed', __( 'MyCred credit failed. GamiPress deduction reversed.', 'zinckles-net-cart' ) );
        }

        // Log
        self::log_transfer( array(
            'user_id'       => $user_id,
            'gami_slug'     => $gami_slug,
            'gami_blog_id'  => $gami_blog_id,
            'gami_amount'   => $amount,
            'mycred_key'    => $mycred_key,
            'mycred_amount' => $mycred_amount,
            'rate'          => $rate,
            'timestamp'     => current_time( 'mysql' ),
            'admin_id'      => get_current_user_id(),
        ) );

        return array(
            'deducted' => (int) $amount,
            'credited' => $mycred_amount,
        );
    }

    /* ──────────────────────────────────────────────────────────────
     *  LOGGING
     * ──────────────────────────────────────────────────────────── */

    /**
     * Log a transfer event.
     */
    private static function log_transfer( $entry ) {
        $log   = get_site_option( self::LOG_KEY, array() );
        $log[] = $entry;

        // Keep last 500 entries
        if ( count( $log ) > 500 ) {
            $log = array_slice( $log, -500 );
        }

        update_site_option( self::LOG_KEY, $log );
    }

    /**
     * Get the transfer log.
     *
     * @param int $limit
     * @return array
     */
    public static function get_log( $limit = 50 ) {
        $log = get_site_option( self::LOG_KEY, array() );
        $log = array_reverse( $log ); // Newest first
        return array_slice( $log, 0, $limit );
    }

    /* ──────────────────────────────────────────────────────────────
     *  AJAX HANDLERS
     * ──────────────────────────────────────────────────────────── */

    /**
     * AJAX: Detect all point types.
     */
    public function ajax_detect_types() {
        check_ajax_referer( 'znc_bridge_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_network' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }

        wp_send_json_success( array(
            'gamipress' => self::detect_gamipress_types(),
            'mycred'    => self::detect_mycred_types(),
        ) );
    }

    /**
     * AJAX: Transfer points for a single user.
     */
    public function ajax_transfer() {
        check_ajax_referer( 'znc_bridge_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_network' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }

        $user_id      = absint( $_POST['user_id'] ?? 0 );
        $gami_slug    = sanitize_text_field( wp_unslash( $_POST['gami_slug'] ?? '' ) );
        $gami_blog_id = absint( $_POST['gami_blog_id'] ?? get_main_site_id() );
        $mycred_key   = sanitize_text_field( wp_unslash( $_POST['mycred_key'] ?? 'mycred_default' ) );
        $amount       = (float) ( $_POST['amount'] ?? 0 );
        $rate         = (float) ( $_POST['rate'] ?? 1.0 );

        if ( ! $user_id || ! $gami_slug ) {
            wp_send_json_error( array( 'message' => 'User ID and GamiPress type are required.' ) );
        }

        $result = self::transfer_points( $user_id, $gami_slug, $gami_blog_id, $mycred_key, $amount, $rate );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }

    /**
     * AJAX: Bulk transfer for all subscribers.
     */
    public function ajax_bulk_transfer() {
        check_ajax_referer( 'znc_bridge_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_network' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }

        $gami_slug    = sanitize_text_field( wp_unslash( $_POST['gami_slug'] ?? '' ) );
        $gami_blog_id = absint( $_POST['gami_blog_id'] ?? get_main_site_id() );
        $mycred_key   = sanitize_text_field( wp_unslash( $_POST['mycred_key'] ?? 'mycred_default' ) );
        $rate         = (float) ( $_POST['rate'] ?? 1.0 );

        if ( ! $gami_slug ) {
            wp_send_json_error( array( 'message' => 'GamiPress type is required.' ) );
        }

        // Get all subscribers on the GamiPress blog
        switch_to_blog( $gami_blog_id );
        $users = get_users( array(
            'role__in' => array( 'subscriber', 'customer', 'contributor', 'author' ),
            'number'   => 500,
        ) );
        restore_current_blog();

        $results  = array( 'success' => 0, 'failed' => 0, 'skipped' => 0 );

        foreach ( $users as $user ) {
            $balance = self::get_gamipress_balance( $user->ID, $gami_slug, $gami_blog_id );
            if ( $balance <= 0 ) {
                $results['skipped']++;
                continue;
            }

            $transfer = self::transfer_points( $user->ID, $gami_slug, $gami_blog_id, $mycred_key, $balance, $rate );

            if ( is_wp_error( $transfer ) ) {
                $results['failed']++;
            } else {
                $results['success']++;
            }
        }

        wp_send_json_success( $results );
    }

    /**
     * AJAX: Get transfer log.
     */
    public function ajax_get_log() {
        check_ajax_referer( 'znc_bridge_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_network' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }

        $limit = absint( $_POST['limit'] ?? 50 );
        wp_send_json_success( array( 'log' => self::get_log( $limit ) ) );
    }

    /**
     * AJAX: Save exchange rates.
     */
    public function ajax_save_rates() {
        check_ajax_referer( 'znc_bridge_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_network' ) ) {
            wp_send_json_error( array( 'message' => 'Insufficient permissions.' ) );
        }

        $raw_rates = $_POST['rates'] ?? array();
        $rates     = array();

        if ( is_array( $raw_rates ) ) {
            foreach ( $raw_rates as $key => $val ) {
                $key = sanitize_text_field( $key );
                $val = max( 0.001, (float) $val );
                $rates[ $key ] = $val;
            }
        }

        $settings          = get_site_option( self::OPTION_KEY, array() );
        $settings['rates'] = $rates;
        update_site_option( self::OPTION_KEY, $settings );

        wp_send_json_success( array( 'saved' => count( $rates ) ) );
    }
}
