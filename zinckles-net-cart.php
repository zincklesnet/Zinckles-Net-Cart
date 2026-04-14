<?php
/**
 * Plugin Name:  Zinckles Net Cart
 * Plugin URI:   https://zinckles.com/net-cart
 * Description:  Unified multisite cart — aggregate WooCommerce products from multiple subsites
 *               into a single checkout with configurable checkout host, mixed currency support,
 *               MyCred (ZCreds) integration, parent/child orders, and inventory sync.
 * Version:      1.3.0
 * Author:       Zinckles
 * Author URI:   https://zinckles.com
 * License:      GPL-2.0-or-later
 * Network:      true
 * Text Domain:  zinckles-net-cart
 * Domain Path:  /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

define( 'ZNC_VERSION',     '1.3.0' );
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

    /* Checkout Host resolver — always available */
    $checkout_host = new ZNC_Checkout_Host();

    /* Network Admin — always */
    ZNC_Network_Admin::init();

    /* My Account + Cart redirect on non-host enrolled subsites */
    $redirect = new ZNC_My_Account_Redirect( $checkout_host );
    $redirect->init();

    /* Add-to-Cart interceptor on non-host enrolled subsites */
    if ( ! $checkout_host->is_current_site_host() ) {
        $interceptor = new ZNC_Add_To_Cart_Interceptor( $checkout_host );
        $interceptor->init();

        $snapshot = new ZNC_Cart_Snapshot();
        $snapshot->init();

        $shop = new ZNC_Shop_Settings();
        $shop->init();

        $subsite_admin = new ZNC_Subsite_Admin();
        $subsite_admin->init();
    }

    /* Checkout Host modules */
    if ( $checkout_host->is_current_site_host() ) {
        $store    = new ZNC_Global_Cart_Store();
        $store->init();

        $currency = new ZNC_Currency_Handler();
        $currency->init();

        $mycred   = new ZNC_MyCred_Engine();
        $mycred->init();

        $merger   = new ZNC_Global_Cart_Merger( $store, $currency );
        $merger->init();

        $inventory = new ZNC_Inventory_Sync();
        $inventory->init();

        $orders   = new ZNC_Order_Factory();
        $orders->init();

        $checkout = new ZNC_Checkout_Orchestrator(
            $store, $merger, $currency, $mycred, $orders, $inventory
        );
        $checkout->init();

        $main_admin = new ZNC_Main_Admin( $store );
        $main_admin->init();

        $my_account = new ZNC_My_Account( $checkout_host );
        $my_account->init();
    }

    /* REST + Auth — all sites */
    $rest = new ZNC_REST_Endpoints();
    $rest->init();

    $auth = new ZNC_REST_Auth();
    $auth->init();

    /* Admin Loader */
    $loader = new ZNC_Admin_Loader();
    $loader->init();

    /* Admin Bar */
    add_action( 'admin_bar_menu', function( $wp_admin_bar ) use ( $checkout_host ) {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $wp_admin_bar->add_node( array(
            'id'    => 'znc-net-cart',
            'title' => "\xF0\x9F\x9B\x92 Net Cart",
            'href'  => $checkout_host->is_current_site_host()
                ? admin_url( 'admin.php?page=znc-settings' )
                : admin_url( 'admin.php?page=znc-subsite' ),
        ) );
        if ( is_user_logged_in() ) {
            $wp_admin_bar->add_node( array(
                'id'     => 'znc-view-cart',
                'parent' => 'znc-net-cart',
                'title'  => "\xF0\x9F\x9B\x8D View Global Cart",
                'href'   => $checkout_host->get_cart_url(),
            ) );
        }
    }, 100 );
}
