<?php
/**
 * Network Admin — Menus, pages, and AJAX handlers.
 *
 * v1.7.0 FIXES:
 * - Save handler stores structured mycred_types_config / gamipress_types_config
 * - Detect handler saves structured config (not flat arrays).
 * - MyCred/GamiPress hooks saved and loaded.
 * - All AJAX uses consistent 'znc_network_admin' nonce.
 * - Added Points Bridge submenu page.
 *
 * @package ZincklesNetCart
 * @since   1.7.0
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Network_Admin {

    public static function init() {
        add_action( 'network_admin_menu', array( __CLASS__, 'add_menus' ) );
    }

    public static function register_ajax_handlers() {
        $actions = array(
            'znc_save_settings'      => 'ajax_save_settings',
            'znc_enroll_site'        => 'ajax_enroll_site',
            'znc_remove_site'        => 'ajax_remove_site',
            'znc_save_security'      => 'ajax_save_security',
            'znc_regenerate_secret'  => 'ajax_regenerate_secret',
            'znc_test_connection'    => 'ajax_test_connection',
            'znc_detect_point_types' => 'ajax_detect_point_types',
        );
        foreach ( $actions as $action => $method ) {
            if ( ! has_action( 'wp_ajax_' . $action ) ) {
                add_action( 'wp_ajax_' . $action, array( __CLASS__, $method ) );
            }
        }
    }

    public static function add_menus() {
        add_menu_page(
            'Net Cart', 'Net Cart', 'manage_network_options',
            'znc-settings', array( __CLASS__, 'page_settings' ),
            'dashicons-cart', 30
        );
        add_submenu_page(
            'znc-settings', 'Settings', 'Settings',
            'manage_network_options', 'znc-settings',
            array( __CLASS__, 'page_settings' )
        );
        add_submenu_page(
            'znc-settings', 'Subsites', 'Subsites',
            'manage_network_options', 'znc-subsites',
            array( __CLASS__, 'page_subsites' )
        );
        add_submenu_page(
            'znc-settings', 'Security', 'Security',
            'manage_network_options', 'znc-security',
            array( __CLASS__, 'page_security' )
        );
        add_submenu_page(
            'znc-settings', 'Diagnostics', 'Diagnostics',
            'manage_network_options', 'znc-diagnostics',
            array( __CLASS__, 'page_diagnostics' )
        );
        add_submenu_page(
            'znc-settings', 'Points Bridge', 'Points Bridge',
            'manage_network_options', 'znc-bridge',
            array( __CLASS__, 'page_bridge' )
        );
    }

    public static function page_settings()    { include ZNC_PLUGIN_DIR . 'admin/views/network-settings.php'; }
    public static function page_subsites()    { include ZNC_PLUGIN_DIR . 'admin/views/network-subsites.php'; }
    public static function page_security()    { include ZNC_PLUGIN_DIR . 'admin/views/network-security.php'; }
    public static function page_diagnostics() { include ZNC_PLUGIN_DIR . 'admin/views/network-diagnostics.php'; }
    public static function page_bridge()      { include ZNC_PLUGIN_DIR . 'admin/views/network-bridge.php'; }

    /* —— AJAX: Save Settings —— */
    public static function ajax_save_settings() {
        check_ajax_referer( 'znc_network_admin', 'nonce' );
        if ( ! current_user_can( 'manage_network_options' ) )
            wp_send_json_error( 'Unauthorized', 403 );

        $settings = get_site_option( 'znc_network_settings', array() );

        $fields = array(
            'checkout_host_id', 'enrollment_mode', 'base_currency',
            'cart_expiry_days', 'max_items', 'max_shops',
            'cart_page_id', 'checkout_page_id'
        );
        foreach ( $fields as $f ) {
            if ( isset( $_POST[ $f ] ) ) {
                $settings[ $f ] = sanitize_text_field( $_POST[ $f ] );
            }
        }

        $toggles = array(
            'clear_local_cart', 'debug_mode', 'tutor_lms_support',
            'enable_cart_sync', 'enable_admin_bar_cart', 'mixed_currency'
        );
        foreach ( $toggles as $t ) {
            $settings[ $t ] = ! empty( $_POST[ $t ] ) ? 1 : 0;
        }

        // MyCred structured config
        if ( isset( $_POST['mycred_types'] ) && is_array( $_POST['mycred_types'] ) ) {
            $mc_config = array();
            foreach ( $_POST['mycred_types'] as $slug => $cfg ) {
                $slug = sanitize_key( $slug );
                $mc_config[ $slug ] = array(
                    'label'         => sanitize_text_field( $cfg['label'] ?? $slug ),
                    'exchange_rate' => floatval( $cfg['exchange_rate'] ?? 1 ),
                    'max_percent'   => absint( $cfg['max_percent'] ?? 100 ),
                    'enabled'       => ! empty( $cfg['enabled'] ) ? 1 : 0,
                );
            }
            $settings['mycred_types_config'] = $mc_config;
        }

        // GamiPress structured config
        if ( isset( $_POST['gamipress_types'] ) && is_array( $_POST['gamipress_types'] ) ) {
            $gp_config = array();
            foreach ( $_POST['gamipress_types'] as $slug => $cfg ) {
                $slug = sanitize_key( $slug );
                $gp_config[ $slug ] = array(
                    'label'         => sanitize_text_field( $cfg['label'] ?? $slug ),
                    'exchange_rate' => floatval( $cfg['exchange_rate'] ?? 1 ),
                    'blog_id'       => absint( $cfg['blog_id'] ?? 0 ),
                    'max_percent'   => absint( $cfg['max_percent'] ?? 100 ),
                    'enabled'       => ! empty( $cfg['enabled'] ) ? 1 : 0,
                );
            }
            $settings['gamipress_types_config'] = $gp_config;
        }

        // MyCred Hooks
        if ( isset( $_POST['mycred_hooks'] ) && is_array( $_POST['mycred_hooks'] ) ) {
            $mc_hooks = array();
            foreach ( $_POST['mycred_hooks'] as $key => $hk ) {
                $key = sanitize_key( $key );
                $mc_hooks[ $key ] = array(
                    'amount'     => intval( $hk['amount'] ?? 0 ),
                    'point_type' => sanitize_key( $hk['point_type'] ?? 'mycred_default' ),
                    'enabled'    => ! empty( $hk['enabled'] ) ? 1 : 0,
                );
            }
            $settings['mycred_hooks'] = $mc_hooks;
        }

        // GamiPress Hooks
        if ( isset( $_POST['gamipress_hooks'] ) && is_array( $_POST['gamipress_hooks'] ) ) {
            $gp_hooks = array();
            foreach ( $_POST['gamipress_hooks'] as $key => $hk ) {
                $key = sanitize_key( $key );
                $gp_hooks[ $key ] = array(
                    'amount'     => intval( $hk['amount'] ?? 0 ),
                    'point_type' => sanitize_key( $hk['point_type'] ?? '' ),
                    'enabled'    => ! empty( $hk['enabled'] ) ? 1 : 0,
                );
            }
            $settings['gamipress_hooks'] = $gp_hooks;
        }

        update_site_option( 'znc_network_settings', $settings );

        if ( class_exists( 'ZNC_Checkout_Host' ) ) {
            $host = new ZNC_Checkout_Host();
            $host->flush_url_cache();
        }

        wp_send_json_success( array( 'message' => 'Settings saved successfully!' ) );
    }

    /* —— AJAX: Enroll Site —— */
    public static function ajax_enroll_site() {
        check_ajax_referer( 'znc_network_admin', 'nonce' );
        if ( ! current_user_can( 'manage_network_options' ) )
            wp_send_json_error( 'Unauthorized', 403 );

        $blog_id = absint( $_POST['blog_id'] ?? 0 );
        if ( ! $blog_id || ! get_blog_details( $blog_id ) ) {
            wp_send_json_error( 'Invalid site ID' );
        }

        $settings = get_site_option( 'znc_network_settings', array() );
        $enrolled = (array) ( $settings['enrolled_sites'] ?? array() );

        if ( ! in_array( $blog_id, $enrolled, true ) ) {
            $enrolled[] = $blog_id;
            $settings['enrolled_sites'] = $enrolled;
            update_site_option( 'znc_network_settings', $settings );
        }

        wp_send_json_success( array( 'enrolled' => true, 'blog_id' => $blog_id ) );
    }

    /* —— AJAX: Remove Site —— */
    public static function ajax_remove_site() {
        check_ajax_referer( 'znc_network_admin', 'nonce' );
        if ( ! current_user_can( 'manage_network_options' ) )
            wp_send_json_error( 'Unauthorized', 403 );

        $blog_id  = absint( $_POST['blog_id'] ?? 0 );
        $settings = get_site_option( 'znc_network_settings', array() );
        $enrolled = (array) ( $settings['enrolled_sites'] ?? array() );

        $enrolled = array_values( array_filter( $enrolled, function( $id ) use ( $blog_id ) {
            return absint( $id ) !== $blog_id;
        } ) );

        $settings['enrolled_sites'] = $enrolled;
        update_site_option( 'znc_network_settings', $settings );
        wp_send_json_success( array( 'removed' => true, 'blog_id' => $blog_id ) );
    }

    /* —— AJAX: Save Security —— */
    public static function ajax_save_security() {
        check_ajax_referer( 'znc_network_admin', 'nonce' );
        if ( ! current_user_can( 'manage_network_options' ) )
            wp_send_json_error( 'Unauthorized', 403 );

        $security = get_site_option( 'znc_security_settings', array() );
        if ( isset( $_POST['clock_skew'] ) )    $security['clock_skew']    = absint( $_POST['clock_skew'] );
        if ( isset( $_POST['rate_limit'] ) )    $security['rate_limit']    = absint( $_POST['rate_limit'] );
        if ( isset( $_POST['ip_whitelist'] ) )  $security['ip_whitelist']  = sanitize_textarea_field( $_POST['ip_whitelist'] );
        update_site_option( 'znc_security_settings', $security );
        wp_send_json_success( array( 'message' => 'Security settings saved!' ) );
    }

    /* —— AJAX: Regenerate HMAC Secret —— */
    public static function ajax_regenerate_secret() {
        check_ajax_referer( 'znc_network_admin', 'nonce' );
        if ( ! current_user_can( 'manage_network_options' ) )
            wp_send_json_error( 'Unauthorized', 403 );

        $secret   = wp_generate_password( 64, true, true );
        $security = get_site_option( 'znc_security_settings', array() );
        $security['hmac_secret']       = $secret;
        $security['hmac_generated_at'] = current_time( 'mysql' );
        update_site_option( 'znc_security_settings', $security );
        wp_send_json_success( array(
            'secret'       => $secret,
            'generated_at' => $security['hmac_generated_at'],
            'message'      => 'HMAC secret regenerated successfully!',
        ) );
    }

    /* —— AJAX: Test Connection —— */
    public static function ajax_test_connection() {
        check_ajax_referer( 'znc_network_admin', 'nonce' );
        if ( ! current_user_can( 'manage_network_options' ) )
            wp_send_json_error( 'Unauthorized', 403 );

        $blog_id = absint( $_POST['blog_id'] ?? 0 );
        $details = get_blog_details( $blog_id );
        if ( ! $details ) wp_send_json_error( 'Site not found' );

        global $wpdb;
        $prefix  = $wpdb->get_blog_prefix( $blog_id );
        $plugins = $wpdb->get_var( "SELECT option_value FROM {$prefix}options WHERE option_name = 'active_plugins' LIMIT 1" );

        // Safer plugin detection using unserialized array
        $active = $plugins ? maybe_unserialize( $plugins ) : array();
        $has_wc = $has_tutor = $has_mycred = $has_gamipress = false;
        if ( is_array( $active ) ) {
            foreach ( $active as $p ) {
                if ( strpos( $p, 'woocommerce/' ) === 0 )  $has_wc       = true;
                if ( strpos( $p, 'tutor/' ) === 0 )        $has_tutor    = true;
                if ( strpos( $p, 'mycred/' ) === 0 )       $has_mycred   = true;
                if ( strpos( $p, 'gamipress/' ) === 0 )    $has_gamipress = true;
            }
        }

        wp_send_json_success( array(
            'blog_id'        => $blog_id,
            'name'           => $details->blogname,
            'url'            => $details->siteurl,
            'has_wc'         => $has_wc,
            'has_tutor'      => $has_tutor,
            'has_mycred'     => $has_mycred,
            'has_gamipress'  => $has_gamipress,
            'reachable'      => true,
        ) );
    }

    /* —— AJAX: Detect Point Types — saves STRUCTURED config —— */
    public static function ajax_detect_point_types() {
        check_ajax_referer( 'znc_network_admin', 'nonce' );
        if ( ! current_user_can( 'manage_network_options' ) )
            wp_send_json_error( 'Unauthorized', 403 );

        global $wpdb;
        $settings  = get_site_option( 'znc_network_settings', array() );
        $enrolled  = (array) ( $settings['enrolled_sites'] ?? array() );
        $host_id   = absint( $settings['checkout_host_id'] ?? get_main_site_id() );
        $all_sites = array_unique( array_merge( array( $host_id ), $enrolled ) );

        $existing_mc = (array) ( $settings['mycred_types_config'] ?? array() );
        $existing_gp = (array) ( $settings['gamipress_types_config'] ?? array() );

        $mycred_found    = array();
        $gamipress_found = array();
        $tutor_sites     = array();

        foreach ( $all_sites as $blog_id ) {
            $blog_id = absint( $blog_id );
            $details = get_blog_details( $blog_id );
            if ( ! $details ) continue;

            $prefix  = $wpdb->get_blog_prefix( $blog_id );
            $plugins = $wpdb->get_var( "SELECT option_value FROM {$prefix}options WHERE option_name = 'active_plugins' LIMIT 1" );

            // MyCred detection
            if ( $plugins && strpos( $plugins, 'mycred' ) !== false ) {
                $mc_option = $wpdb->get_var( "SELECT option_value FROM {$prefix}options WHERE option_name = 'mycred_types' LIMIT 1" );
                if ( $mc_option ) {
                    $types = maybe_unserialize( $mc_option );
                    if ( is_array( $types ) ) {
                        foreach ( $types as $slug => $label ) {
                            $clean_label = is_array( $label ) ? ( $label['plural'] ?? $slug ) : $label;
                            $mycred_found[ $slug ] = $clean_label;
                        }
                    }
                }
                if ( empty( $mycred_found ) ) {
                    $mycred_found['mycred_default'] = 'Points';
                }
            }

            // GamiPress detection
            if ( $plugins && strpos( $plugins, 'gamipress' ) !== false ) {
                $gp_types = $wpdb->get_results( "SELECT post_name, post_title FROM {$prefix}posts WHERE post_type = 'points-type' AND post_status = 'publish'" );
                if ( $gp_types ) {
                    foreach ( $gp_types as $gpt ) {
                        $gamipress_found[ $gpt->post_name ] = array(
                            'label'   => $gpt->post_title,
                            'blog_id' => $blog_id,
                        );
                    }
                }
            }

            // Tutor LMS detection
            if ( $plugins && strpos( $plugins, 'tutor' ) !== false ) {
                $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}posts WHERE post_type = 'courses' AND post_status = 'publish'" );
                $tutor_sites[ $blog_id ] = array(
                    'name'    => $details->blogname,
                    'courses' => (int) $count,
                );
            }
        }

        // Build structured MyCred config
        $mc_config = array();
        foreach ( $mycred_found as $slug => $label ) {
            $mc_config[ $slug ] = array(
                'label'         => isset( $existing_mc[ $slug ]['label'] ) ? $existing_mc[ $slug ]['label'] : $label,
                'exchange_rate' => isset( $existing_mc[ $slug ]['exchange_rate'] ) ? $existing_mc[ $slug ]['exchange_rate'] : 1,
                'max_percent'   => isset( $existing_mc[ $slug ]['max_percent'] ) ? $existing_mc[ $slug ]['max_percent'] : 100,
                'enabled'       => isset( $existing_mc[ $slug ]['enabled'] ) ? $existing_mc[ $slug ]['enabled'] : 1,
            );
        }

        // Build structured GamiPress config
        $gp_config = array();
        foreach ( $gamipress_found as $slug => $data ) {
            $gp_config[ $slug ] = array(
                'label'         => isset( $existing_gp[ $slug ]['label'] ) ? $existing_gp[ $slug ]['label'] : $data['label'],
                'exchange_rate' => isset( $existing_gp[ $slug ]['exchange_rate'] ) ? $existing_gp[ $slug ]['exchange_rate'] : 1,
                'blog_id'       => $data['blog_id'],
                'max_percent'   => isset( $existing_gp[ $slug ]['max_percent'] ) ? $existing_gp[ $slug ]['max_percent'] : 100,
                'enabled'       => isset( $existing_gp[ $slug ]['enabled'] ) ? $existing_gp[ $slug ]['enabled'] : 1,
            );
        }

        $settings['mycred_types_config']    = $mc_config;
        $settings['gamipress_types_config'] = $gp_config;
        $settings['tutor_sites']            = array_keys( $tutor_sites );
        update_site_option( 'znc_network_settings', $settings );

        wp_send_json_success( array(
            'mycred'    => $mc_config,
            'gamipress' => $gp_config,
            'tutor'     => $tutor_sites,
            'message'   => 'Point types detected and saved!',
        ) );
    }
}
