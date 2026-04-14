<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Shop_Settings {

    public static function init() {
        /* Settings are read on demand */
    }

    public static function get_settings() {
        $settings = array(
            'blog_id'       => get_current_blog_id(),
            'shop_name'     => get_bloginfo( 'name' ),
            'shop_url'      => home_url(),
            'currency'      => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD',
            'has_wc'        => class_exists( 'WooCommerce' ),
            'has_mycred'    => function_exists( 'mycred' ),
            'tax_enabled'   => get_option( 'woocommerce_calc_taxes', 'no' ) === 'yes',
            'prices_inc_tax'=> get_option( 'woocommerce_prices_include_tax', 'no' ) === 'yes',
            'shipping'      => self::get_shipping_zones(),
        );

        if ( $settings['has_mycred'] && function_exists( 'mycred_get_types' ) ) {
            $settings['mycred_types'] = mycred_get_types();
        }

        return $settings;
    }

    private static function get_shipping_zones() {
        if ( ! class_exists( 'WC_Shipping_Zones' ) ) return array();

        $zones  = WC_Shipping_Zones::get_zones();
        $result = array();

        foreach ( $zones as $zone ) {
            $methods = array();
            foreach ( $zone['shipping_methods'] as $method ) {
                $methods[] = array(
                    'id'    => $method->id,
                    'title' => $method->get_title(),
                    'cost'  => $method->get_option( 'cost', 0 ),
                );
            }
            $result[] = array(
                'id'      => $zone['id'],
                'name'    => $zone['zone_name'],
                'methods' => $methods,
            );
        }

        return $result;
    }
}
