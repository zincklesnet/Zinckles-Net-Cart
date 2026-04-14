<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Autoloader {
    public static function register() {
        spl_autoload_register( array( __CLASS__, 'autoload' ) );
    }
    public static function autoload( $class ) {
        if ( 0 !== strpos( $class, 'ZNC_' ) ) return;
        $file = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
        $paths = array(
            ZNC_PLUGIN_DIR . 'includes/' . $file,
            ZNC_PLUGIN_DIR . 'admin/'    . $file,
        );
        foreach ( $paths as $path ) {
            if ( file_exists( $path ) ) { require_once $path; return; }
        }
    }
}
