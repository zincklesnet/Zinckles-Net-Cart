<?php
/**
 * Plugin Name: Zinckles Net Cart (BP Net Cart)
 * Plugin URI:  https://zinckles.com
 * Description: Unified global cart across WooCommerce subsites in a WordPress multisite network.
 * Version:     1.7.0
 * Author:      Zinckles
 * Author URI:  https://zinckles.com
 * License:     GPL-2.0-or-later
 * Network:     true
 * Text Domain: zinckles-net-cart
 *
 * Changelog v1.7.0:
 *  • Fixed checkout redirect loop (suppresses WC template_redirect on ZNC page)
 *  • Fixed diagnostics crash (get_items → get_cart)
 *  • Fixed hardcoded '$' in 6 locations → uses ZNC_Currency_Handler
 *  • Fixed grand total mixing currencies → per-currency subtotals
 *  • Fixed widgets showing "Product #123" → uses switch_to_blog() for names
 *  • Fixed double script localization (removed zncCart, kept zncFront)
 *  • Fixed missing nonce on ajax_get_count
 *  • Fixed variation data not sanitized deeply
 *  • Built full MyCred Payment Engine (per-product point-type selection)
 *  • Built GamiPress↔MyCred Bridge (admin transfer controls)
 *  • Built ZNC_Currency_Handler (proper multi-currency support)
 *  • Built ZNC_Order_Factory (structured order creation with payment)
 *  • Built ZNC_Inventory_Sync (cross-site stock validation)
 *  • Built ZNC_Order_Query (cross-site order querying)
 *  • Added live JS recalculation (line totals, subtotals, grand total)
 *  • Added cart expiry cron purge
 *  • Expanded REST API to 8 routes with HMAC wiring
 *  • Added Points Bridge admin page in Network Admin
 *  • Integrated WC payment gateways into checkout flow
 *  • MyCred point deduction with rollback on failure
 *  • MyCred refund on order cancellation
 */
defined( 'ABSPATH' ) || exit;

/* ─── Constants ─── */
define( 'ZNC_VERSION',    '1.7.0' );
define( 'ZNC_PLUGIN_FILE', __FILE__ );
define( 'ZNC_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'ZNC_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

/* ─── Memory guard ─── */
$znc_mem = function_exists( 'wp_convert_hr_to_bytes' )
    ? wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) )
    : (int) ini_get( 'memory_limit' ) * 1024 * 1024;
if ( $znc_mem > 0 && ( $znc_mem - memory_get_usage( true ) ) < 32 * 1024 * 1024 ) {
    return; // Skip boot if <32 MB free
}

/* ─── Autoloader ─── */
require_once ZNC_PLUGIN_DIR . 'includes/class-znc-autoloader.php';
ZNC_Autoloader::register();

/* ─── Activation / Deactivation ─── */
register_activation_hook( __FILE__, array( 'ZNC_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ZNC_Deactivator', 'deactivate' ) );

/* ─── Cron: Cart expiry purge ─── */
register_activation_hook( __FILE__, function () {
    if ( ! wp_next_scheduled( 'znc_daily_cart_purge' ) ) {
        wp_schedule_event( time(), 'daily', 'znc_daily_cart_purge' );
    }
} );
register_deactivation_hook( __FILE__, function () {
    wp_clear_scheduled_hook( 'znc_daily_cart_purge' );
} );
add_action( 'znc_daily_cart_purge', function () {
    if ( class_exists( 'ZNC_Global_Cart' ) ) {
        ZNC_Global_Cart::instance()->purge_expired();
    }
} );

/* ═══════════════════════════════════════════════════════════════
 *  BOOTSTRAP
 * ═══════════════════════════════════════════════════════════════ */
add_action( 'plugins_loaded', 'znc_bootstrap', 20 );

function znc_bootstrap() {

    /* ── Singletons ── */
    $checkout_host = ZNC_Checkout_Host::instance();
    $global_cart   = ZNC_Global_Cart::instance();

    /* ── Network Admin AJAX (always) ── */
    ZNC_Network_Admin::register_ajax_handlers();

    /* ── Admin loaders ── */
    if ( is_admin() || is_network_admin() ) {
        $admin_loader = new ZNC_Admin_Loader();
        $admin_loader->init();
    }

    /* ── Network Admin ── */
    if ( is_network_admin() ) {
        ZNC_Network_Admin::init();

        // Add Points Bridge submenu
        add_action( 'network_admin_menu', function () {
            add_submenu_page(
                'znc-network-settings',
                __( 'Points Bridge', 'zinckles-net-cart' ),
                __( 'Points Bridge', 'zinckles-net-cart' ),
                'manage_network',
                'znc-points-bridge',
                function () {
                    require_once ZNC_PLUGIN_DIR . 'admin/views/network-bridge.php';
                }
            );
        }, 20 );

        // Initialize GamiPress bridge AJAX (network admin context)
        $gami_engine = new ZNC_GamiPress_Engine();
        $gami_engine->init();

        return; // Network admin doesn't need frontend stuff
    }

    /* ── Require WooCommerce for everything below ── */
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    /* ── Currency Handler (all sites) ── */
    $currency_handler = new ZNC_Currency_Handler();
    $currency_handler->init();

    /* ── Cart Interceptor (all participating sites) ── */
    $interceptor = new ZNC_Cart_Interceptor();
    $interceptor->init();

    /* ── Tutor LMS Engine (if Tutor is active) ── */
    if ( function_exists( 'tutor' ) ) {
        $tutor = new ZNC_Tutor_Engine();
        $tutor->init();
    }

    /* ── Cart Sync (all sites) ── */
    $sync = new ZNC_Cart_Sync();
    $sync->init();

    /* ── Non-checkout-host subsites ── */
    if ( ! $checkout_host->is_checkout_host() ) {
        $redirect = new ZNC_My_Account_Redirect();
        $redirect->init();

        $subsite_admin = new ZNC_Subsite_Admin();
        $subsite_admin->init();
    }

    /* ── Checkout host only ── */
    if ( $checkout_host->is_checkout_host() ) {
        $renderer = new ZNC_Cart_Renderer();

        $checkout = new ZNC_Checkout_Handler();
        $checkout->init();

        $shortcodes = new ZNC_Shortcodes();
        $shortcodes->init();

        /* ── MyCred Engine ── */
        $mycred = new ZNC_MyCred_Engine();
        $mycred->init();

        /* ── GamiPress Engine (AJAX handlers) ── */
        $gami = new ZNC_GamiPress_Engine();
        $gami->init();

        /* ── Inventory Sync ── */
        $inventory = new ZNC_Inventory_Sync();
        $inventory->init();

        /* ── Main Admin ── */
        if ( is_admin() ) {
            $main_admin = new ZNC_Main_Admin();
            $main_admin->init();
        }

        /* ── My Account ── */
        $my_account = new ZNC_My_Account();
        $my_account->init();

        /* ── MyCred refund on order cancellation ── */
        add_action( 'woocommerce_order_status_cancelled', array( 'ZNC_MyCred_Engine', 'refund_for_order' ) );
        add_action( 'woocommerce_order_status_refunded', array( 'ZNC_MyCred_Engine', 'refund_for_order' ) );
    }

    /* ── REST API (all sites) ── */
    $rest_auth = new ZNC_REST_Auth();
    $rest_auth->init();

    $rest_endpoints = new ZNC_REST_Endpoints();
    $rest_endpoints->init();

    /* ── Widgets (all sites) ── */
    add_action( 'widgets_init', array( 'ZNC_Widgets', 'register' ) );

    /* ── Front-end assets (single localization — no more duplicate zncCart) ── */
    add_action( 'wp_enqueue_scripts', function () {
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
            'ajax_url'           => admin_url( 'admin-ajax.php' ),
            'nonce'              => wp_create_nonce( 'znc_cart_action' ),
            'checkout_url'       => ZNC_Checkout_Host::get_checkout_url(),
            'cart_url'           => ZNC_Checkout_Host::get_cart_url(),
            'is_logged_in'       => is_user_logged_in(),
            'i18n_empty'         => __( 'Your global cart is empty.', 'zinckles-net-cart' ),
            'i18n_clear_confirm' => __( 'Are you sure you want to clear your entire cart?', 'zinckles-net-cart' ),
            'i18n_processing'    => __( 'Processing…', 'zinckles-net-cart' ),
            'i18n_error'         => __( 'An error occurred. Please try again.', 'zinckles-net-cart' ),
        ) );
    } );
}
