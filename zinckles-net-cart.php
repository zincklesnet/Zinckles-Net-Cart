<?php
/**
 * Plugin Name: Zinckles Net Cart
 * Plugin URI:  https://zinckles.com/net-cart
 * Description: Unified multisite cart — aggregate WooCommerce products from multiple subsites
 *              into a single checkout with configurable checkout host, mixed currency support,
 *              MyCred/GamiPress integration, parent/child orders, inventory sync, widgets & shortcodes.
 * Version:     1.4.1
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

/* ── Memory guard ─────────────────────────────────────────────── */
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

/* ── Constants ────────────────────────────────────────────────── */
define( 'ZNC_VERSION',     '1.4.1' );
define( 'ZNC_PLUGIN_FILE', __FILE__ );
define( 'ZNC_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'ZNC_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'ZNC_DB_VERSION',  '1.4.1' );

/* ── Autoloader ───────────────────────────────────────────────── */
require_once ZNC_PLUGIN_DIR . 'includes/class-znc-autoloader.php';
ZNC_Autoloader::register();

/* ── Activation / Deactivation ────────────────────────────────── */
register_activation_hook( __FILE__, array( 'ZNC_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ZNC_Deactivator', 'deactivate' ) );

/* ── Boot ─────────────────────────────────────────────────────── */
add_action( 'plugins_loaded', 'znc_bootstrap', 20 );

function znc_bootstrap() {
    static $booted = false;
    if ( $booted ) return;
    $booted = true;

    $checkout_host = new ZNC_Checkout_Host();

    /*
     * ── CRITICAL FIX v1.4.0 ─────────────────────────────────────
     * AJAX handlers MUST be registered on ALL admin requests,
     * because admin-ajax.php runs in a context where
     * is_network_admin() returns FALSE.
     *
     * Menu pages are still only added in network_admin context.
     */
    if ( is_admin() || wp_doing_ajax() ) {
        ZNC_Network_Admin::register_ajax_handlers();
    }

    /*
     * ── FIX v1.4.1 — Admin asset loading ────────────────────────
     * ZNC_Admin_Loader must initialize on ALL admin contexts
     * (network admin, main site admin, subsite admin) so that
     * CSS/JS loads on Net Cart pages and AJAX handlers can fire.
     */
    if ( is_admin() || is_network_admin() ) {
        $admin_loader = new ZNC_Admin_Loader();
        $admin_loader->init();
    }

    /* ── Network Admin menu pages ─────────────────────────────── */
    if ( is_network_admin() ) {
        ZNC_Network_Admin::init_menus();
        return;
    }

    /* WooCommerce required for everything below */
    if ( ! class_exists( 'WooCommerce' ) ) return;

    $current_blog = get_current_blog_id();
    $host_id      = $checkout_host->get_host_id();
    $is_host      = ( (int) $current_blog === (int) $host_id );
    $is_enrolled  = $checkout_host->is_enrolled( $current_blog );

    /* ── Register widgets ─────────────────────────────────────── */
    ZNC_Widgets::init( $checkout_host );

    /* ── Cart menu sync — replaces WC cart count with global ──── */
    if ( $is_host || $is_enrolled ) {
        $cart_sync = new ZNC_Cart_Sync( $checkout_host );
        $cart_sync->init();
    }

    /* ── Enrolled Subsite (NOT the checkout host) ─────────────── */
    if ( ! $is_host && $is_enrolled ) {
        $snapshot = new ZNC_Cart_Snapshot( $checkout_host );
        $snapshot->init();

        $redirect = new ZNC_My_Account_Redirect( $checkout_host );
        $redirect->init();

        $shop = new ZNC_Shop_Settings();
        $shop->init();

        if ( is_admin() ) {
            $subsite_admin = new ZNC_Subsite_Admin( $checkout_host );
            $subsite_admin->init();
        }
        return;
    }

    /* ── Checkout Host Site ───────────────────────────────────── */
    if ( $is_host ) {
        ZNC_Shortcodes::init( $checkout_host );

        /*
         * ── FIX v1.4.1 — Constructor argument mismatches ────────
         *
         * BUG #1: ZNC_Global_Cart_Store requires ZNC_Checkout_Host.
         *         v1.4.0 passed ZERO arguments → fatal TypeError.
         *
         * BUG #2: ZNC_Checkout_Orchestrator expects (Store, Host).
         *         v1.4.0 passed (Store, Merger, Currency, …) → fatal TypeError.
         *
         * BUG #3: ZNC_REST_Endpoints requires ZNC_REST_Auth.
         *         v1.4.0 passed ZERO arguments AND created $auth AFTER $rest.
         */
        $store = new ZNC_Global_Cart_Store( $checkout_host );  // FIX #1: was new ZNC_Global_Cart_Store()
        $store->init();

        $currency = new ZNC_Currency_Handler();
        $currency->init();

        $mycred = new ZNC_MyCred_Engine();
        $mycred->init();

        $gamipress = new ZNC_GamiPress_Engine();
        $gamipress->init();

        $merger = new ZNC_Global_Cart_Merger( $store );  // FIX: removed unused $currency arg
        $merger->init();

        $inventory = new ZNC_Inventory_Sync();
        $inventory->init();

        $orders = new ZNC_Order_Factory();
        $orders->init();

        $checkout = new ZNC_Checkout_Orchestrator( $store, $checkout_host );  // FIX #2: was ($store, $merger, $currency, $mycred, $orders, $inventory)
        $checkout->init();

        $my_account = new ZNC_My_Account( $checkout_host );
        $my_account->init();

        $snapshot = new ZNC_Cart_Snapshot( $checkout_host );
        $snapshot->init();

        if ( is_admin() ) {
            $main_admin = new ZNC_Main_Admin( $store );
            $main_admin->init();
        }
    }

    /* ── REST endpoints — all enrolled sites + host ───────────── */
    if ( $is_host || $is_enrolled ) {
        $auth = new ZNC_REST_Auth();       // FIX #3: moved BEFORE $rest
        $auth->init();

        $rest = new ZNC_REST_Endpoints( $auth );  // FIX #3: was new ZNC_REST_Endpoints()
        $rest->init();
    }

    /* ── Admin Bar — uses CACHED URLs ─────────────────────────── */
    add_action( 'admin_bar_menu', function( $wp_admin_bar ) use ( $checkout_host, $is_host ) {
        if ( ! is_user_logged_in() ) return;
        if ( current_user_can( 'manage_options' ) ) {
            $wp_admin_bar->add_node( array(
                'id'    => 'znc-net-cart',
                'title' => '🛒 Net Cart',
                'href'  => $is_host
                    ? admin_url( 'admin.php?page=znc-settings' )
                    : admin_url( 'admin.php?page=znc-subsite' ),
            ) );
        }
        $wp_admin_bar->add_node( array(
            'id'     => 'znc-view-cart',
            'parent' => current_user_can( 'manage_options' ) ? 'znc-net-cart' : null,
            'title'  => '🛍 View Global Cart',
            'href'   => $checkout_host->get_cart_url(),
        ) );
    }, 100 );
}
