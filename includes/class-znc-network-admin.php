<?php
/**
 * Network Admin — v1.4.2
 *
 * FIXES:
 * 1. HMAC regenerate now saves into znc_network_settings['hmac_secret']
 *    (was saving to separate 'znc_rest_secret' option — mismatch with security view)
 * 2. MyCred save now reads $_POST['mycred_types'] to match JS field names
 *    (was expecting 'znc_mycred_types' — mismatch with auto-detect JS)
 * 3. GamiPress save now reads $_POST['gamipress_types'] to match JS
 * 4. HMAC regenerate returns secret_preview for JS to display
 * 5. Detect uses network-wide scan (detect_network_types) for MyCred
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Network_Admin {

    /**
     * Register AJAX handlers — called on ALL admin/ajax requests.
     */
    public static function register_ajax_handlers() {
        add_action( 'wp_ajax_znc_toggle_enrollment',       array( __CLASS__, 'ajax_toggle_enrollment' ) );
        add_action( 'wp_ajax_znc_save_network_settings',   array( __CLASS__, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_znc_save_security',           array( __CLASS__, 'ajax_save_security' ) );
        add_action( 'wp_ajax_znc_regenerate_secret',       array( __CLASS__, 'ajax_regenerate_secret' ) );
        add_action( 'wp_ajax_znc_test_connection',         array( __CLASS__, 'ajax_test_connection' ) );
        add_action( 'wp_ajax_znc_detect_point_types',      array( __CLASS__, 'ajax_detect_point_types' ) );
    }

    /**
     * Register menu pages — only called in network admin context.
     */
    public static function init_menus() {
        add_action( 'network_admin_menu', array( __CLASS__, 'add_menu' ) );
    }

    public static function add_menu() {
        add_menu_page(
            'Zinckles Net Cart', 'Net Cart', 'manage_network_options',
            'znc-network', array( __CLASS__, 'render_settings' ),
            'dashicons-cart', 30
        );
        add_submenu_page( 'znc-network', 'Network Settings', 'Settings',
            'manage_network_options', 'znc-network', array( __CLASS__, 'render_settings' ) );
        add_submenu_page( 'znc-network', 'Enrolled Subsites', 'Subsites',
            'manage_network_options', 'znc-subsites', array( __CLASS__, 'render_subsites' ) );
        add_submenu_page( 'znc-network', 'Security', 'Security',
            'manage_network_options', 'znc-security', array( __CLASS__, 'render_security' ) );
        add_submenu_page( 'znc-network', 'Diagnostics', 'Diagnostics',
            'manage_network_options', 'znc-diagnostics', array( __CLASS__, 'render_diagnostics' ) );
    }

    /* ── Page Renderers ───────────────────────────────────────── */

    public static function render_settings() {
        self::enqueue_admin_assets();
        include ZNC_PLUGIN_DIR . 'admin/views/network-settings.php';
    }

    public static function render_subsites() {
        self::enqueue_admin_assets();
        include ZNC_PLUGIN_DIR . 'admin/views/network-subsites.php';
    }

    public static function render_security() {
        self::enqueue_admin_assets();
        include ZNC_PLUGIN_DIR . 'admin/views/network-security.php';
    }

    public static function render_diagnostics() {
        self::enqueue_admin_assets();
        include ZNC_PLUGIN_DIR . 'admin/views/network-diagnostics.php';
    }

    private static function enqueue_admin_assets() {
        wp_enqueue_style( 'znc-admin', ZNC_PLUGIN_URL . 'assets/css/znc-admin.css', array(), ZNC_VERSION );
        wp_enqueue_script( 'znc-network-admin', ZNC_PLUGIN_URL . 'assets/js/znc-network-admin.js', array( 'jquery' ), ZNC_VERSION, true );
        wp_localize_script( 'znc-network-admin', 'zncAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'znc_network_admin' ),
        ) );
    }

    /* ── AJAX: Toggle Enrollment ──────────────────────────────── */

    public static function ajax_toggle_enrollment() {
        check_ajax_referer( 'znc_network_admin', 'nonce' );

        if ( ! current_user_can( 'manage_network_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $blog_id = isset( $_POST['blog_id'] ) ? (int) $_POST['blog_id'] : 0;
        $action  = isset( $_POST['enrollment_action'] ) ? sanitize_text_field( $_POST['enrollment_action'] ) : '';

        if ( ! $blog_id || ! in_array( $action, array( 'enroll', 'remove' ), true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid parameters.' ) );
        }

        $details = get_blog_details( $blog_id );
        if ( ! $details ) {
            wp_send_json_error( array( 'message' => 'Site not found.' ) );
        }

        if ( $action === 'enroll' ) {
            ZNC_Checkout_Host::enroll( $blog_id );

            /* Ensure global cart table exists on checkout host */
            $host = new ZNC_Checkout_Host();
            $host_id = $host->get_host_id();
            if ( (int) get_current_blog_id() !== (int) $host_id ) {
                switch_to_blog( $host_id );
                ZNC_Activator::create_tables();
                restore_current_blog();
            } else {
                ZNC_Activator::create_tables();
            }

            wp_send_json_success( array(
                'message'     => $details->blogname . ' enrolled successfully.',
                'blog_id'     => $blog_id,
                'is_enrolled' => true,
            ) );
        } else {
            ZNC_Checkout_Host::remove( $blog_id );
            wp_send_json_success( array(
                'message'     => $details->blogname . ' removed from Net Cart.',
                'blog_id'     => $blog_id,
                'is_enrolled' => false,
            ) );
        }
    }

    /* ── AJAX: Save Network Settings ──────────────────────────── */

    public static function ajax_save_settings() {
        check_ajax_referer( 'znc_network_admin', 'nonce' );

        if ( ! current_user_can( 'manage_network_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $settings = get_site_option( 'znc_network_settings', array() );

        /* Checkout Host */
        if ( isset( $_POST['checkout_host_id'] ) ) {
            $settings['checkout_host_id'] = absint( $_POST['checkout_host_id'] );
            $host = new ZNC_Checkout_Host();
            $host->flush_url_cache();
        }

        /* General Settings */
        $settings['enrollment_mode']  = sanitize_text_field( $_POST['enrollment_mode'] ?? 'manual' );
        $settings['base_currency']    = sanitize_text_field( $_POST['base_currency'] ?? 'USD' );
        $settings['mixed_currency']   = ! empty( $_POST['mixed_currency'] ) ? 1 : 0;
        $settings['cart_expiry_days'] = absint( $_POST['cart_expiry_days'] ?? 7 );
        $settings['max_items']        = absint( $_POST['max_items'] ?? 100 );
        $settings['max_shops']        = absint( $_POST['max_shops'] ?? 20 );
        $settings['debug_mode']       = ! empty( $_POST['debug_mode'] ) ? 1 : 0;
        $settings['clear_local_cart'] = ! empty( $_POST['clear_local_cart'] ) ? 1 : 0;

        /* Cart page IDs */
        if ( isset( $_POST['cart_page_id'] ) ) {
            $settings['cart_page_id'] = absint( $_POST['cart_page_id'] );
        }
        if ( isset( $_POST['checkout_page_id'] ) ) {
            $settings['checkout_page_id'] = absint( $_POST['checkout_page_id'] );
        }

        /*
         * FIX v1.4.2: MyCred types — JS auto-detect uses name="mycred_types[slug][...]"
         * Previous versions expected 'znc_mycred_types' — field name mismatch!
         * Now accepts BOTH for backward compatibility.
         */
        $mycred_post = null;
        if ( isset( $_POST['znc_mycred_types'] ) && is_array( $_POST['znc_mycred_types'] ) ) {
            $mycred_post = $_POST['znc_mycred_types'];
        } elseif ( isset( $_POST['mycred_types'] ) && is_array( $_POST['mycred_types'] ) ) {
            $mycred_post = $_POST['mycred_types'];
        }

        if ( $mycred_post ) {
            $types_config = array();
            foreach ( $mycred_post as $slug => $config ) {
                $types_config[ sanitize_key( $slug ) ] = array(
                    'enabled'       => ! empty( $config['enabled'] ) ? 1 : 0,
                    'exchange_rate' => floatval( $config['exchange_rate'] ?? 0 ),
                    'max_percent'   => absint( $config['max_percent'] ?? 100 ),
                    'label'         => sanitize_text_field( $config['label'] ?? $slug ),
                );
            }
            $settings['mycred_types_config'] = $types_config;
        }

        /*
         * FIX v1.4.2: GamiPress types — same field name fix as MyCred
         */
        $gp_post = null;
        if ( isset( $_POST['znc_gamipress_types'] ) && is_array( $_POST['znc_gamipress_types'] ) ) {
            $gp_post = $_POST['znc_gamipress_types'];
        } elseif ( isset( $_POST['gamipress_types'] ) && is_array( $_POST['gamipress_types'] ) ) {
            $gp_post = $_POST['gamipress_types'];
        }

        if ( $gp_post ) {
            $gp_config = array();
            foreach ( $gp_post as $slug => $config ) {
                $gp_config[ sanitize_key( $slug ) ] = array(
                    'enabled'       => ! empty( $config['enabled'] ) ? 1 : 0,
                    'exchange_rate' => floatval( $config['exchange_rate'] ?? 0 ),
                    'max_percent'   => absint( $config['max_percent'] ?? 100 ),
                    'label'         => sanitize_text_field( $config['label'] ?? $slug ),
                );
            }
            $settings['gamipress_types_config'] = $gp_config;
        }

        update_site_option( 'znc_network_settings', $settings );
        wp_send_json_success( array( 'message' => 'Settings saved successfully.' ) );
    }

    /* ── AJAX: Save Security ──────────────────────────────────── */

    public static function ajax_save_security() {
        check_ajax_referer( 'znc_network_admin', 'nonce' );

        if ( ! current_user_can( 'manage_network_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $settings = get_site_option( 'znc_network_settings', array() );
        $settings['clock_skew']    = absint( $_POST['clock_skew'] ?? 300 );
        $settings['rate_limit']    = absint( $_POST['rate_limit'] ?? 60 );
        $settings['ip_whitelist']  = sanitize_textarea_field( $_POST['ip_whitelist'] ?? '' );

        update_site_option( 'znc_network_settings', $settings );
        wp_send_json_success( array( 'message' => 'Security settings saved.' ) );
    }

    /* ── AJAX: Regenerate Secret ──────────────────────────────── */

    public static function ajax_regenerate_secret() {
        check_ajax_referer( 'znc_network_admin', 'nonce' );

        if ( ! current_user_can( 'manage_network_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $new_secret = wp_generate_password( 64, true, true );

        /*
         * FIX v1.4.2: Save into the SAME location the security view reads from.
         * Was: update_site_option('znc_rest_secret', ...) — wrong key!
         * Now: $settings['hmac_secret'] inside znc_network_settings
         */
        $settings = get_site_option( 'znc_network_settings', array() );
        $settings['hmac_secret'] = $new_secret;
        update_site_option( 'znc_network_settings', $settings );

        /* Also update the legacy separate option for backward compat */
        update_site_option( 'znc_rest_secret', $new_secret );

        wp_send_json_success( array(
            'message'        => 'Secret regenerated and saved.',
            'secret_preview' => substr( $new_secret, 0, 12 ) . '••••••••••••',
        ) );
    }

    /* ── AJAX: Test Connection ────────────────────────────────── */

    public static function ajax_test_connection() {
        check_ajax_referer( 'znc_network_admin', 'nonce' );

        if ( ! current_user_can( 'manage_network_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $blog_id = absint( $_POST['blog_id'] ?? 0 );
        if ( ! $blog_id ) {
            wp_send_json_error( array( 'message' => 'Invalid blog ID.' ) );
        }

        $details = get_blog_details( $blog_id );
        if ( ! $details ) {
            wp_send_json_error( array( 'message' => 'Site not found.' ) );
        }

        global $wpdb;
        $prefix = $wpdb->get_blog_prefix( $blog_id );
        $has_wc = (bool) $wpdb->get_var(
            "SELECT option_value FROM {$prefix}options WHERE option_name = 'woocommerce_version' LIMIT 1"
        );

        wp_send_json_success( array(
            'blog_id'  => $blog_id,
            'blogname' => $details->blogname,
            'has_wc'   => $has_wc,
            'status'   => $has_wc ? 'connected' : 'no_woocommerce',
        ) );
    }

    /* ── AJAX: Detect Point Types ─────────────────────────────── */

    public static function ajax_detect_point_types() {
        check_ajax_referer( 'znc_network_admin', 'nonce' );

        if ( ! current_user_can( 'manage_network_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        /*
         * FIX v1.4.2: Use network-wide detection, not just current site.
         * MyCred/GamiPress may be active only on subsites, not the network admin site.
         */
        $mycred_types    = ZNC_MyCred_Engine::detect_network_types();
        $gamipress_types = ZNC_GamiPress_Engine::detect_network_types();

        /* Also save detected types into settings for diagnostics display */
        $settings = get_site_option( 'znc_network_settings', array() );
        $settings['detected_mycred_types']    = $mycred_types;
        $settings['detected_gamipress_types'] = $gamipress_types;
        update_site_option( 'znc_network_settings', $settings );

        wp_send_json_success( array(
            'message'          => 'Detection complete.',
            'mycred_types'     => $mycred_types,
            'gamipress_types'  => $gamipress_types,
            'mycred_count'     => count( $mycred_types ),
            'gamipress_count'  => count( $gamipress_types ),
        ) );
    }
}
