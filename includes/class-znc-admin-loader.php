<?php
/**
 * Admin Loader — v1.4.1
 * Conditionally loads admin pages based on context (main site, subsite, network).
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Admin_Loader {

    public function init() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    public function enqueue_admin_assets( $hook ) {
        // Network admin pages
        if ( strpos( $hook, 'znc-network' ) !== false || strpos( $hook, 'zinckles-net-cart' ) !== false ) {
            wp_enqueue_style(
                'znc-admin',
                ZNC_PLUGIN_URL . 'assets/css/znc-admin.css',
                array(),
                ZNC_VERSION
            );
            wp_enqueue_script(
                'znc-network-admin',
                ZNC_PLUGIN_URL . 'assets/js/znc-network-admin.js',
                array( 'jquery' ),
                ZNC_VERSION,
                true
            );
            wp_localize_script( 'znc-network-admin', 'zncAdmin', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'znc_network_admin' ),
                'strings' => array(
                    'saving'       => __( 'Saving…', 'zinckles-net-cart' ),
                    'saved'        => __( 'Saved!', 'zinckles-net-cart' ),
                    'error'        => __( 'Error occurred.', 'zinckles-net-cart' ),
                    'enrolling'    => __( 'Processing…', 'zinckles-net-cart' ),
                    'enrolled'     => __( 'Enrolled', 'zinckles-net-cart' ),
                    'notEnrolled'  => __( 'Not Enrolled', 'zinckles-net-cart' ),
                    'detecting'    => __( 'Detecting…', 'zinckles-net-cart' ),
                    'regenerating' => __( 'Regenerating…', 'zinckles-net-cart' ),
                    'testing'      => __( 'Testing…', 'zinckles-net-cart' ),
                    'confirm_regen' => __( 'Regenerate HMAC secret? All subsites will need to re-authenticate.', 'zinckles-net-cart' ),
                ),
            ) );
        }

        // Subsite admin pages
        if ( strpos( $hook, 'znc-shop-settings' ) !== false ) {
            wp_enqueue_style(
                'znc-admin',
                ZNC_PLUGIN_URL . 'assets/css/znc-admin.css',
                array(),
                ZNC_VERSION
            );
        }

        // Main site admin pages
        if ( strpos( $hook, 'znc-main-admin' ) !== false ) {
            wp_enqueue_style(
                'znc-admin',
                ZNC_PLUGIN_URL . 'assets/css/znc-admin.css',
                array(),
                ZNC_VERSION
            );
        }
    }
}
