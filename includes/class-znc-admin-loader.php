<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Admin_Loader {

    public function init() {
        add_action( 'admin_enqueue_scripts',         array( $this, 'enqueue' ) );
        add_action( 'network_admin_enqueue_scripts', array( $this, 'enqueue' ) );
    }

    public function enqueue( $hook ) {
        if ( strpos( $hook, 'znc-' ) === false &&
             strpos( $hook, 'znc_' ) === false &&
             strpos( $hook, 'net-cart' ) === false ) {
            return;
        }

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
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'znc_network_admin' ),
        ) );
    }
}
