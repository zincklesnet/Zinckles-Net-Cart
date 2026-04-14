<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Currency_Handler {

    private static $rates = null;

    public static function init() {}

    public static function get_base_currency() {
        $settings = get_site_option( 'znc_network_settings', array() );
        return $settings['base_currency'] ?? 'USD';
    }

    public static function is_mixed_cart( $user_id ) {
        $items      = ZNC_Global_Cart_Store::get_cart( $user_id );
        $currencies = array_unique( array_column( $items, 'currency' ) );
        return count( $currencies ) > 1;
    }

    public static function get_exchange_rates() {
        if ( self::$rates !== null ) return self::$rates;

        $settings = get_site_option( 'znc_network_settings', array() );
        $custom   = $settings['exchange_rates'] ?? array();

        self::$rates = array_merge( array(
            'USD' => 1.00, 'EUR' => 1.08, 'GBP' => 1.27, 'CAD' => 0.74,
            'AUD' => 0.65, 'JPY' => 0.0067, 'CHF' => 1.13, 'CNY' => 0.14,
            'INR' => 0.012, 'BRL' => 0.20, 'MXN' => 0.058, 'KRW' => 0.00075,
            'SEK' => 0.096, 'NOK' => 0.094, 'DKK' => 0.145, 'NZD' => 0.61,
            'SGD' => 0.75, 'HKD' => 0.13, 'ZAR' => 0.055, 'TRY' => 0.031,
        ), $custom );

        return self::$rates;
    }

    public static function convert( $amount, $from, $to ) {
        if ( $from === $to ) return $amount;
        $rates    = self::get_exchange_rates();
        $from_usd = ( $rates[ $from ] ?? 1 );
        $to_usd   = ( $rates[ $to ] ?? 1 );
        return round( $amount * $from_usd / $to_usd, 2 );
    }

    public static function parallel_totals( $user_id ) {
        $grouped = ZNC_Global_Cart_Store::get_cart_grouped( $user_id );
        $base    = self::get_base_currency();
        $totals  = array( 'per_currency' => array(), 'converted_total' => 0 );

        foreach ( $grouped as $blog_id => $group ) {
            $currency  = $group['currency'];
            $subtotal  = 0;

            foreach ( $group['items'] as $item ) {
                $subtotal += (float) $item['product_price'] * (int) $item['quantity'];
            }

            if ( ! isset( $totals['per_currency'][ $currency ] ) ) {
                $totals['per_currency'][ $currency ] = 0;
            }
            $totals['per_currency'][ $currency ] += $subtotal;
            $totals['converted_total'] += self::convert( $subtotal, $currency, $base );
        }

        $totals['base_currency'] = $base;
        return $totals;
    }
}
