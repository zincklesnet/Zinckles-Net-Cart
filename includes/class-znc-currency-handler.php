<?php
/**
 * Currency Handler — v1.4.1
 * Mixed-currency support and formatting.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Currency_Handler {

    private static $symbols = array(
        'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'CAD' => 'CA$', 'AUD' => 'A$',
        'JPY' => '¥', 'CNY' => '¥', 'INR' => '₹', 'BRL' => 'R$', 'MXN' => 'MX$',
        'MYR' => 'RM', 'SGD' => 'S$', 'HKD' => 'HK$', 'NZD' => 'NZ$', 'KRW' => '₩',
        'SEK' => 'kr', 'NOK' => 'kr', 'DKK' => 'kr', 'CHF' => 'CHF', 'ZAR' => 'R',
        'RUB' => '₽', 'TRY' => '₺', 'PLN' => 'zł', 'THB' => '฿', 'IDR' => 'Rp',
        'PHP' => '₱', 'CZK' => 'Kč', 'TWD' => 'NT$', 'AED' => 'د.إ', 'SAR' => '﷼',
        'MYC' => 'Cr', // MyCred points
    );

    public function init() {}

    public static function format( $amount, $currency = 'USD' ) {
        $symbol   = isset( self::$symbols[ $currency ] ) ? self::$symbols[ $currency ] : $currency . ' ';
        $decimals = in_array( $currency, array( 'JPY', 'KRW', 'MYC' ), true ) ? 0 : 2;
        return $symbol . number_format( (float) $amount, $decimals );
    }

    public static function get_symbol( $currency ) {
        return isset( self::$symbols[ $currency ] ) ? self::$symbols[ $currency ] : $currency;
    }

    public static function get_base_currency() {
        $settings = get_site_option( 'znc_network_settings', array() );
        return isset( $settings['base_currency'] ) ? $settings['base_currency'] : 'USD';
    }

    public static function is_mixed_enabled() {
        $settings = get_site_option( 'znc_network_settings', array() );
        return ! empty( $settings['mixed_currency'] );
    }

    public static function is_points_currency( $currency ) {
        return in_array( $currency, array( 'MYC', 'POINTS', 'ZCREDS' ), true );
    }
}
