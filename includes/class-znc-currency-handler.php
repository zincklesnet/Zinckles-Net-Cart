<?php
/**
 * Currency Handler — Proper multi-currency support.
 *
 * Handles mixed currencies in the global cart. Never naively sums different
 * currencies. Shows per-currency subtotals and formatted symbols.
 *
 * @package ZincklesNetCart
 * @since   1.7.0
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Currency_Handler {

    /** @var array Cached currency map: blog_id => [ code, symbol, label ] */
    private static $currency_map = array();

    /** @var array User-friendly labels for known currencies */
    private static $labels = array(
        'USD' => 'US Dollar',
        'CAD' => 'Canadian Dollar',
        'EUR' => 'Euro',
        'GBP' => 'British Pound',
        'AUD' => 'Australian Dollar',
        'MYC' => 'ZCreds',
    );

    public function init() {
        // Allow themes/plugins to register custom labels
        add_filter( 'znc_currency_labels', array( $this, 'apply_custom_labels' ), 10, 1 );
    }

    /**
     * Get currency info for a specific blog.
     *
     * @param int $blog_id
     * @return array [ 'code' => 'USD', 'symbol' => '$', 'label' => 'US Dollar' ]
     */
    public static function get_blog_currency( $blog_id ) {
        if ( isset( self::$currency_map[ $blog_id ] ) ) {
            return self::$currency_map[ $blog_id ];
        }

        switch_to_blog( $blog_id );

        $code   = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
        $symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';

        restore_current_blog();

        $labels = apply_filters( 'znc_currency_labels', self::$labels );
        $label  = $labels[ $code ] ?? $code;

        self::$currency_map[ $blog_id ] = array(
            'code'   => $code,
            'symbol' => $symbol,
            'label'  => $label,
        );

        return self::$currency_map[ $blog_id ];
    }

    /**
     * Check if a blog uses a points-based currency (e.g. MyCred ZCreds).
     *
     * @param int $blog_id
     * @return bool
     */
    public static function is_points_currency( $blog_id ) {
        $info = self::get_blog_currency( $blog_id );
        // MYC = MyCred custom currency code used by ZCred Boutique
        $points_codes = apply_filters( 'znc_points_currency_codes', array( 'MYC' ) );
        return in_array( $info['code'], $points_codes, true );
    }

    /**
     * Get per-currency totals from an enriched cart.
     *
     * @param array $enriched The enriched cart array from ZNC_Cart_Renderer.
     * @return array [ 'USD' => [ 'total' => 252.00, 'symbol' => '$', 'label' => 'US Dollar' ], ... ]
     */
    public static function get_totals_by_currency( $enriched ) {
        $totals = array();

        foreach ( $enriched as $blog_id => $shop ) {
            $code   = $shop['currency'] ?? 'USD';
            $symbol = $shop['currency_symbol'] ?? '$';

            if ( ! isset( $totals[ $code ] ) ) {
                $labels = apply_filters( 'znc_currency_labels', self::$labels );
                $totals[ $code ] = array(
                    'total'    => 0,
                    'symbol'   => $symbol,
                    'label'    => $labels[ $code ] ?? $code,
                    'blog_ids' => array(),
                );
            }

            $totals[ $code ]['total'] += $shop['subtotal'];
            $totals[ $code ]['blog_ids'][] = $blog_id;
        }

        return $totals;
    }

    /**
     * Format a price with the correct currency symbol.
     *
     * @param float  $amount
     * @param string $symbol
     * @param int    $decimals
     * @return string
     */
    public static function format_price( $amount, $symbol, $decimals = 2 ) {
        return $symbol . number_format( (float) $amount, $decimals );
    }

    /**
     * Render a grand total line that correctly separates currencies.
     *
     * @param array $enriched
     * @return string HTML
     */
    public static function render_grand_total_html( $enriched ) {
        $totals = self::get_totals_by_currency( $enriched );

        if ( count( $totals ) <= 1 ) {
            // Single currency — simple display
            $first = reset( $totals );
            if ( ! $first ) return '';
            return '<span class="znc-grand-total-amount">'
                . esc_html( self::format_price( $first['total'], $first['symbol'] ) )
                . '</span>';
        }

        // Multiple currencies — display each separately
        $parts = array();
        foreach ( $totals as $code => $data ) {
            $parts[] = '<span class="znc-currency-total" data-currency="' . esc_attr( $code ) . '">'
                . esc_html( self::format_price( $data['total'], $data['symbol'] ) )
                . ' <small>(' . esc_html( $data['label'] ) . ')</small>'
                . '</span>';
        }

        return implode( ' <span class="znc-currency-separator">+</span> ', $parts );
    }

    /**
     * Get the user-friendly label for a currency code.
     *
     * @param string $code
     * @return string
     */
    public static function get_label( $code ) {
        $labels = apply_filters( 'znc_currency_labels', self::$labels );
        return $labels[ $code ] ?? $code;
    }

    /**
     * Filter callback for custom labels.
     */
    public function apply_custom_labels( $labels ) {
        $settings = get_site_option( 'znc_network_settings', array() );
        $custom   = (array) ( $settings['currency_labels'] ?? array() );
        return array_merge( $labels, $custom );
    }

    /**
     * Invalidate the static cache (for testing or after blog switch).
     */
    public static function flush_cache() {
        self::$currency_map = array();
    }
}
