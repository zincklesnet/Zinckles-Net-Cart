<?php
/**
 * Plugin Name: Zinckles Net Cart
 * Plugin URI:  https://zinckles.com/net-cart
 * Description: Unified multisite global cart — aggregate WooCommerce + Tutor LMS
 *              products from all enrolled subsites into a single checkout.
 *              wp_usermeta storage, MyCred + GamiPress + Tutor LMS integration,
 *              parent/child orders, 10 shortcodes, 5 widgets.
 * Version:     1.6.1
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

/* ── Memory guard ─────────────────────────────────────────── */
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

define( 'ZNC_VERSION',     '1.6.1' );
define( 'ZNC_PLUGIN_FILE', __FILE__ );
define( 'ZNC_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'ZNC_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

/* ── Autoloader ───────────────────────────────────────────── */
require_once ZNC_PLUGIN_DIR . 'includes/class-znc-autoloader.php';
ZNC_Autoloader::register();

/* ── Activation / Deactivation ────────────────────────────── */
register_activation_hook( __FILE__, array( 'ZNC_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ZNC_Deactivator', 'deactivate' ) );

/* ── Bootstrap ────────────────────────────────────────────── */
add_action( 'plugins_loaded', 'znc_bootstrap', 20 );

function znc_bootstrap() {
    static $booted = false;
    if ( $booted ) return;
    $booted = true;

    /* ── Zero-dependency singletons ─────────────────────── */
    $checkout_host = new ZNC_Checkout_Host();
    $global_cart   = new ZNC_Global_Cart();

    /* ──────────────────────────────────────────────────────
     * AJAX handlers — MUST register on ALL admin contexts
     * including network admin, site admin, and AJAX requests.
     * This is the #1 fix: previously these only ran outside
     * network admin, so all buttons returned 400/500.
     * ────────────────────────────────────────────────────── */
    ZNC_Network_Admin::register_ajax_handlers();

    /* ──────────────────────────────────────────────────────
     * ADMIN ASSET LOADER — MUST run in ALL admin contexts
     * including network admin. Previously this was placed
     * AFTER the is_network_admin() return, so JS/CSS never
     * loaded on network admin pages = all buttons broken.
     * ────────────────────────────────────────────────────── */
    if ( is_admin() || is_network_admin() ) {
        $admin_loader = new ZNC_Admin_Loader();
        $admin_loader->init();
    }

    /* ── Network Admin menus ───────────────────────────── */
    if ( is_network_admin() ) {
        ZNC_Network_Admin::init();
        return; // network admin doesn't need WC front-end modules
    }

    /* ── WooCommerce required for everything below ──────── */
    if ( ! class_exists( 'WooCommerce' ) ) return;

    /* ── Cart Interceptor — ALL participating sites ─────── */
    $interceptor = new ZNC_Cart_Interceptor( $global_cart );
    $interceptor->init();

    /* ── Tutor LMS Interceptor — catches course add-to-cart */
    $tutor = new ZNC_Tutor_Engine( $global_cart );
    $tutor->init();

    /* ── Cart Sync — global count in WC fragments ────────── */
    $cart_sync = new ZNC_Cart_Sync( $global_cart, $checkout_host );
    $cart_sync->init();

    /* ── Non-host enrolled subsite modules ────────────────── */
    if ( ! $checkout_host->is_current_site_host() ) {
        $redirect = new ZNC_My_Account_Redirect( $checkout_host );
        $redirect->init();

        if ( is_admin() ) {
            $subsite_admin = new ZNC_Subsite_Admin();
            $subsite_admin->init();
        }
    }

    /* ── Checkout host modules ────────────────────────────── */
    if ( $checkout_host->is_current_site_host() ) {
        $renderer  = new ZNC_Cart_Renderer( $global_cart );
        $checkout  = new ZNC_Checkout_Handler( $global_cart, $checkout_host );
        $checkout->init();

        $shortcodes = new ZNC_Shortcodes( $global_cart, $renderer, $checkout_host, $checkout );
        $shortcodes->init();

        $mycred = new ZNC_MyCred_Engine();
        $mycred->init();

        $gamipress = new ZNC_GamiPress_Engine();
        $gamipress->init();

        $currency = new ZNC_Currency_Handler();
        $currency->init();

        $inventory = new ZNC_Inventory_Sync();
        $inventory->init();

        if ( is_admin() ) {
            $main_admin = new ZNC_Main_Admin( $global_cart );
            $main_admin->init();
        }

        $my_account = new ZNC_My_Account( $checkout_host );
        $my_account->init();
    }

    /* ── REST API ─────────────────────────────────────────── */
    $rest_auth = new ZNC_REST_Auth();
    $rest_auth->init();

    $rest = new ZNC_REST_Endpoints( $rest_auth );
    $rest->init();

    /* ── Widgets ──────────────────────────────────────────── */
    ZNC_Widgets::register();

    /* ── Front-end assets ─────────────────────────────────── */
    add_action( 'wp_enqueue_scripts', function() use ( $checkout_host, $global_cart ) {
        wp_enqueue_style(
            'znc-front',
            ZNC_PLUGIN_URL . 'assets/css/znc-front.css',
            array(),
            ZNC_VERSION
        );
        wp_enqueue_script(
            'znc-front',
            ZNC_PLUGIN_URL . 'assets/js/znc-front.js',
            array( 'jquery' ),
            ZNC_VERSION,
            true
        );
        wp_localize_script( 'znc-front', 'zncFront', array(
            'ajaxurl'     => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'znc_cart_nonce' ),
            'blogId'      => get_current_blog_id(),
            'cartUrl'     => $checkout_host->get_cart_url(),
            'checkoutUrl' => $checkout_host->get_checkout_url(),
            'cartCount'   => is_user_logged_in() ? $global_cart->get_item_count() : 0,
        ) );
    } );
}
