<?php
/**
 * Network Admin — v1.3.1 FIX.
 *
 * FIXED: init() only runs in network admin context (not all sites).
 * FIXED: AJAX enrollment uses correct action name and updates UI.
 * FIXED: Saves to znc_network_settings consistently.
 *
 * @package ZincklesNetCart
 * @since   1.3.0
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Network_Admin {

    public static function init() {
        add_action( 'network_admin_menu', array( __CLASS__, 'add_menu' ) );

        /* AJAX handlers — only register in admin context */
        add_action( 'wp_ajax_znc_toggle_enrollment',     array( __CLASS__, 'ajax_toggle_enrollment' ) );
        add_action( 'wp_ajax_znc_save_network_settings', array( __CLASS__, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_znc_save_security',         array( __CLASS__, 'ajax_save_security' ) );
        add_action( 'wp_ajax_znc_regenerate_secret',     array( __CLASS__, 'ajax_regenerate_secret' ) );
        add_action( 'wp_ajax_znc_test_connection',       array( __CLASS__, 'ajax_test_connection' ) );
    }

    public static function add_menu() {
        add_menu_page(
            'Zinckles Net Cart',
            'Net Cart',
            'manage_network_options',
            'znc-network',
            array( __CLASS__, 'render_settings' ),
            'dashicons-cart',
            30
        );
        add_submenu_page( 'znc-network', 'Network Settings', 'Settings',    'manage_network_options', 'znc-network',      array( __CLASS__, 'render_settings' ) );
        add_submenu_page( 'znc-network', 'Enrolled Subsites', 'Subsites',   'manage_network_options', 'znc-subsites',     array( __CLASS__, 'render_subsites' ) );
        add_submenu_page( 'znc-network', 'Security',          'Security',   'manage_network_options', 'znc-security',     array( __CLASS__, 'render_security' ) );
        add_submenu_page( 'znc-network', 'Diagnostics',       'Diagnostics','manage_network_options', 'znc-diagnostics',  array( __CLASS__, 'render_diagnostics' ) );
    }

    /* ── Renderers ────────────────────────────────────────────── */

    private static function enqueue_admin_js() {
        wp_enqueue_script( 'znc-network-admin', ZNC_PLUGIN_URL . 'assets/js/znc-network-admin.js', array( 'jquery' ), ZNC_VERSION, true );
        wp_localize_script( 'znc-network-admin', 'zncAdmin', array(
            'ajaxUrl' => network_admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'znc_network_admin' ),
        ) );
        wp_enqueue_style( 'znc-network-admin', ZNC_PLUGIN_URL . 'assets/css/znc-admin.css', array(), ZNC_VERSION );
    }

    public static function render_settings() {
        self::enqueue_admin_js();
        include ZNC_PLUGIN_DIR . 'admin/views/network-settings.php';
    }

    public static function render_subsites() {
        self::enqueue_admin_js();
        include ZNC_PLUGIN_DIR . 'admin/views/network-subsites.php';
    }

    public static function render_security() {
        self::enqueue_admin_js();
        include ZNC_PLUGIN_DIR . 'admin/views/network-security.php';
    }

    public static function render_diagnostics() {
        self::enqueue_admin_js();
        include ZNC_PLUGIN_DIR . 'admin/views/network-diagnostics.php';
    }

    /* ── AJAX: Toggle Enrollment ──────────────────────────────── */

    public static function ajax_toggle_enrollment() {
        check_ajax_referer( 'znc_network_admin', 'nonce' );

        if ( ! current_user_can( 'manage_network_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $blog_id = isset( $_POST['blog_id'] ) ? absint( $_POST['blog_id'] ) : 0;
        $action  = isset( $_POST['enrollment_action'] ) ? sanitize_text_field( $_POST['enrollment_action'] ) : '';

        if ( ! $blog_id || ! in_array( $action, array( 'enroll', 'remove' ), true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid parameters. blog_id=' . $blog_id . ' action=' . $action ) );
        }

        $details = get_blog_details( $blog_id );
        if ( ! $details ) {
            wp_send_json_error( array( 'message' => 'Site not found.' ) );
        }

        $settings = get_site_option( 'znc_network_settings', array() );
        if ( ! is_array( $settings ) ) $settings = array();
        if ( ! isset( $settings['enrolled_sites'] ) || ! is_array( $settings['enrolled_sites'] ) ) {
            $settings['enrolled_sites'] = array();
        }

        if ( $action === 'enroll' ) {
            if ( ! in_array( $blog_id, $settings['enrolled_sites'], true ) ) {
                $settings['enrolled_sites'][] = $blog_id;
            }
            update_site_option( 'znc_network_settings', $settings );

            /* Flush caches */
            delete_site_transient( 'znc_enrolled_sites' );
            $host = new ZNC_Checkout_Host();
            $host->flush_url_cache();

            wp_send_json_success( array(
                'message'     => esc_html( $details->blogname ) . ' enrolled successfully.',
                'blog_id'     => $blog_id,
                'is_enrolled' => true,
            ) );
        } else {
            $settings['enrolled_sites'] = array_values( array_diff( $settings['enrolled_sites'], array( $blog_id ) ) );
            update_site_option( 'znc_network_settings', $settings );

            delete_site_transient( 'znc_enrolled_sites' );
            $host = new ZNC_Checkout_Host();
            $host->flush_url_cache();

            wp_send_json_success( array(
                'message'     => esc_html( $details->blogname ) . ' removed from Net Cart.',
                'blog_id'     => $blog_id,
                'is_enrolled' => false,
            ) );
        }
    }

    /* ── AJAX: Save Settings ──────────────────────────────────── */

    public static function ajax_save_settings() {
        check_ajax_referer( 'znc_network_admin', 'nonce' );
        if ( ! current_user_can( 'manage_network_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $settings = get_site_option( 'znc_network_settings', array() );
        if ( ! is_array( $settings ) ) $settings = array();

        $settings['checkout_host_id']  = absint( $_POST['checkout_host_id'] ?? get_main_site_id() );
        $settings['enrollment_mode']   = sanitize_text_field( $_POST['enrollment_mode'] ?? 'manual' );
        $settings['base_currency']     = sanitize_text_field( $_POST['base_currency'] ?? 'USD' );
        $settings['mixed_currency']    = ! empty( $_POST['mixed_currency'] ) ? 1 : 0;
        $settings['cart_expiry_days']  = absint( $_POST['cart_expiry_days'] ?? 7 );
        $settings['max_items']         = absint( $_POST['max_items'] ?? 100 );
        $settings['max_shops']         = absint( $_POST['max_shops'] ?? 20 );
        $settings['debug_mode']        = ! empty( $_POST['debug_mode'] ) ? 1 : 0;

        /* MyCred point types config */
        if ( isset( $_POST['znc_mycred_types'] ) && is_array( $_POST['znc_mycred_types'] ) ) {
            $types_config = array();
            foreach ( $_POST['znc_mycred_types'] as $slug => $config ) {
                $types_config[ sanitize_key( $slug ) ] = array(
                    'enabled'       => ! empty( $config['enabled'] ) ? 1 : 0,
                    'exchange_rate' => (float) ( $config['exchange_rate'] ?? 0 ),
                    'max_percent'   => absint( $config['max_percent'] ?? 100 ),
                );
            }
            $settings['mycred_types_config'] = $types_config;
        }

        update_site_option( 'znc_network_settings', $settings );

        /* Flush URL cache when checkout host changes */
        $host = new ZNC_Checkout_Host();
        $host->flush_url_cache();

        wp_send_json_success( array( 'message' => 'Settings saved successfully.' ) );
    }

    /* ── AJAX: Save Security ──────────────────────────────────── */

    public static function ajax_save_security() {
        check_ajax_referer( 'znc_network_admin', 'nonce' );
        if ( ! current_user_can( 'manage_network_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $settings = get_site_option( 'znc_network_settings', array() );
        if ( ! is_array( $settings ) ) $settings = array();

        $settings['clock_skew']   = absint( $_POST['clock_skew'] ?? 300 );
        $settings['rate_limit']   = absint( $_POST['rate_limit'] ?? 60 );
        $settings['ip_whitelist'] = sanitize_textarea_field( $_POST['ip_whitelist'] ?? '' );

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
        update_site_option( 'znc_rest_secret', $new_secret );

        wp_send_json_success( array( 'message' => 'Secret regenerated and saved.' ) );
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
}
