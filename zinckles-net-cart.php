<?php
/**
 * Plugin Name: Zinckles Net Cart
 * Plugin URI:  https://zinckles.com/net-cart
 * Description: Unified multisite cart — aggregate WooCommerce products from multiple subsites
 *              into a single checkout with configurable checkout host, mixed currency support,
 *              MyCred (ZCreds) integration, parent/child orders, and inventory sync.
 * Version:     1.3.1
 * Author:      Zinckles
 * Author URI:  https://zinckles.com
 * License:     GPL-2.0-or-later
 * Network:     true
 * Text Domain: zinckles-net-cart
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */
defined( 'ABSPATH' ) || exit;

/* ── Memory guard — skip boot if < 32 MB free ─────────────────── */
$znc_mem_limit = @ini_get( 'memory_limit' );
if ( $znc_mem_limit && $znc_mem_limit !== '-1' ) {
    $znc_bytes = wp_convert_hr_to_bytes( $znc_mem_limit );
    $znc_used  = memory_get_usage( true );
    if ( $znc_bytes > 0 && ( $znc_bytes - $znc_used ) < 33554432 ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[ZNC] Skipped boot — only ' . round( ( $znc_bytes - $znc_used ) / 1048576, 1 ) . ' MB free.' );
        }
        return;
    }
}

define( 'ZNC_VERSION',     '1.3.1' );
define( 'ZNC_PLUGIN_FILE', __FILE__ );
define( 'ZNC_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'ZNC_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'ZNC_DB_VERSION',  '1.3.0' );

require_once ZNC_PLUGIN_DIR . 'includes/class-znc-autoloader.php';
ZNC_Autoloader::register();

register_activation_hook( __FILE__, array( 'ZNC_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ZNC_Deactivator', 'deactivate' ) );

add_action( 'plugins_loaded', 'znc_bootstrap', 20 );

function znc_bootstrap() {
    static $booted = false;
    if ( $booted ) return;
    $booted = true;

    /* Checkout Host resolver — always available, zero switch_to_blog */
    $checkout_host = new ZNC_Checkout_Host();

    /* ── Network Admin ONLY ──────────────────────────────────── */
    if ( is_network_admin() ) {
        ZNC_Network_Admin::init();
        return;
    }

    /* WooCommerce required for everything below */
    if ( ! class_exists( 'WooCommerce' ) ) return;

    /* ── Enrolled Subsite (NOT the checkout host) ────────────── */
    if ( ! $checkout_host->is_current_site_host() ) {
        $redirect = new ZNC_My_Account_Redirect( $checkout_host );
        $redirect->init();

        $snapshot = new ZNC_Cart_Snapshot( $checkout_host );
        $snapshot->init();

        $shop = new ZNC_Shop_Settings();
        $shop->init();

        if ( is_admin() ) {
            ZNC_Subsite_Admin::init();
        }
    }

    /* ── Checkout Host ───────────────────────────────────────── */
    if ( $checkout_host->is_current_site_host() ) {
        $snapshot = new ZNC_Cart_Snapshot( $checkout_host );
        $snapshot->init();

        ZNC_Global_Cart_Store::init();
        ZNC_Global_Cart_Merger::init();
        ZNC_Currency_Handler::init();
        ZNC_MyCred_Engine::init();
        ZNC_Checkout_Orchestrator::init();
        ZNC_Order_Factory::init();
        ZNC_Inventory_Sync::init();
        ZNC_My_Account::init();

        if ( is_admin() ) {
            ZNC_Main_Admin::init();
        }
    }

    /* REST + Auth — all enrolled sites */
    ZNC_REST_Endpoints::init();
    ZNC_REST_Auth::init();
    ZNC_Admin_Loader::init();

    /* Admin Bar — uses CACHED URLs, zero switch_to_blog */
    add_action( 'admin_bar_menu', function( $wp_admin_bar ) use ( $checkout_host ) {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $wp_admin_bar->add_node( array(
            'id'    => 'znc-net-cart',
            'title' => '🛒 Net Cart',
            'href'  => $checkout_host->is_current_site_host()
                ? admin_url( 'admin.php?page=znc-settings' )
                : admin_url( 'admin.php?page=znc-subsite' ),
        ) );
        if ( is_user_logged_in() ) {
            $wp_admin_bar->add_node( array(
                'id'     => 'znc-view-cart',
                'parent' => 'znc-net-cart',
                'title'  => '🛍 View Global Cart',
                'href'   => $checkout_host->get_cart_url(),
            ) );
        }
    }, 100 );
}
