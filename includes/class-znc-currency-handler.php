<?php
/**
 * Currency Handler — mixed-currency detection, conversion, and parallel totals.
 *
 * @package ZincklesNetCart
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class ZNC_Currency_Handler {

    /** Fallback exchange rates (to USD). */
    private static $fallback_rates = array(
        'USD' => 1.0,
        'CAD' => 0.74,
        'EUR' => 1.08,
        'GBP' => 1.27,
        'AUD' => 0.65,
        'JPY' => 0.0067,
    );

    public function init() {
        add_filter( 'znc_currency_rates', array( $this, 'get_rates' ) );
    }

    /**
     * Check if a cart contains multiple currencies.
     */
    public function is_mixed( $items ) {
        $currencies = array_unique( array_column( $items, 'currency' ) );
        return count( $currencies ) > 1;
    }

    /**
     * Calculate parallel totals — per-currency subtotals + unified converted total.
     */
    public function parallel_totals( $items, $base_currency = null ) {
        if ( ! $base_currency ) {
            $settings      = get_site_option( 'znc_network_settings', array() );
            $base_currency = $settings['base_currency'] ?? 'CAD';
        }

        $per_currency   = array();
        $converted_total = 0.0;

        foreach ( $items as $item ) {
            $currency   = $item['currency'] ?? $base_currency;
            $line_total = (float) ( $item['line_total'] ?? 0 );

            if ( ! isset( $per_currency[ $currency ] ) ) {
                $per_currency[ $currency ] = 0.0;
            }
            $per_currency[ $currency ] += $line_total;

            $converted_total += $this->convert( $line_total, $currency, $base_currency );
        }

        return array(
            'base_currency'    => $base_currency,
            'is_mixed'         => count( $per_currency ) > 1,
            'per_currency'     => $per_currency,
            'converted_total'  => round( $converted_total, 2 ),
        );
    }

    /**
     * Convert an amount from one currency to another.
     */
    public function convert( $amount, $from, $to ) {
        if ( $from === $to ) {
            return $amount;
        }

        $rates   = $this->get_rates();
        $from_rate = $rates[ $from ] ?? 1.0;
        $to_rate   = $rates[ $to ] ?? 1.0;

        // Convert to USD base, then to target.
        $usd_amount = $amount * $from_rate;
        return $usd_amount / $to_rate;
    }

    /**
     * Get exchange rates. Uses saved rates, falls back to hardcoded.
     */
    public function get_rates() {
        $settings = get_site_option( 'znc_network_settings', array() );
        $saved    = get_site_option( 'znc_exchange_rates', array() );

        if ( ! empty( $saved ) ) {
            return wp_parse_args( $saved, self::$fallback_rates );
        }

        return self::$fallback_rates;
    }

    /**
     * Format a price with currency symbol.
     */
    public function format( $amount, $currency ) {
        $symbols = array(
            'USD' => '$', 'CAD' => 'C$', 'EUR' => '€',
            'GBP' => '£', 'AUD' => 'A$', 'JPY' => '¥',
        );
        $symbol = $symbols[ $currency ] ?? $currency . ' ';
        return $symbol . number_format( $amount, 2 );
    }
}
