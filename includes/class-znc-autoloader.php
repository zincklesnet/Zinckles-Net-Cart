<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Autoloader {

    private static $map = [
        'ZNC_Activator'              => 'includes/class-znc-activator.php',
        'ZNC_Deactivator'            => 'includes/class-znc-deactivator.php',
        'ZNC_Checkout_Host'          => 'includes/class-znc-checkout-host.php',
        'ZNC_My_Account_Redirect'    => 'includes/class-znc-my-account-redirect.php',
        'ZNC_REST_Auth'              => 'includes/class-znc-rest-auth.php',
        'ZNC_REST_Endpoints'         => 'includes/class-znc-rest-endpoints.php',
        'ZNC_Cart_Snapshot'          => 'includes/class-znc-cart-snapshot.php',
        'ZNC_Shop_Settings'          => 'includes/class-znc-shop-settings.php',
        'ZNC_Global_Cart_Store'      => 'includes/class-znc-global-cart-store.php',
        'ZNC_Global_Cart_Merger'     => 'includes/class-znc-global-cart-merger.php',
        'ZNC_Currency_Handler'       => 'includes/class-znc-currency-handler.php',
        'ZNC_Checkout_Orchestrator'  => 'includes/class-znc-checkout-orchestrator.php',
        'ZNC_MyCred_Engine'          => 'includes/class-znc-mycred-engine.php',
        'ZNC_Order_Factory'          => 'includes/class-znc-order-factory.php',
        'ZNC_Inventory_Sync'         => 'includes/class-znc-inventory-sync.php',
        'ZNC_Network_Admin'          => 'includes/class-znc-network-admin.php',
        'ZNC_Main_Admin'             => 'includes/class-znc-main-admin.php',
        'ZNC_Subsite_Admin'          => 'includes/class-znc-subsite-admin.php',
        'ZNC_Admin_Loader'           => 'includes/class-znc-admin-loader.php',
        'ZNC_My_Account'             => 'includes/class-znc-my-account.php',
        'ZNC_Order_Query'            => 'includes/class-znc-order-query.php',
    ];

    public static function register() {
        spl_autoload_register( [ __CLASS__, 'load' ] );
    }

    public static function load( $class ) {
        if ( isset( self::$map[ $class ] ) ) {
            $file = ZNC_PLUGIN_DIR . self::$map[ $class ];
            if ( file_exists( $file ) ) {
                require_once $file;
            }
        }
    }
}
