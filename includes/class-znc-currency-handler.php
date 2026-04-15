<?php
defined( 'ABSPATH' ) || exit;
class ZNC_Currency_Handler {
    public function init() {}
    public function is_mixed( $items ) {
        $currencies = array();
        foreach ( $items as $item ) {
            $cur = is_object( $item ) ? ( $item->currency ?? 'USD' ) : ( $item['currency'] ?? 'USD' );
            $currencies[ $cur ] = true;
        }
        return count( $currencies ) > 1;
    }
    public function parallel_totals( $items ) {
        $totals = array();
        foreach ( $items as $item ) {
            $cur   = is_object( $item ) ? ( $item->currency ?? 'USD' ) : ( $item['currency'] ?? 'USD' );
            $price = is_object( $item ) ? (float)( $item->price ?? 0 ) : (float)( $item['price'] ?? 0 );
            $qty   = is_object( $item ) ? (int)( $item->quantity ?? 1 ) : (int)( $item['quantity'] ?? 1 );
            if ( ! isset( $totals[ $cur ] ) ) $totals[ $cur ] = 0;
            $totals[ $cur ] += $price * $qty;
        }
        return $totals;
    }
    public function convert( $amount, $from, $to ) {
        if ( $from === $to ) return $amount;
        $rates = $this->get_rates();
        $from_rate = $rates[ $from ] ?? 1;
        $to_rate   = $rates[ $to ] ?? 1;
        return $amount * ( $to_rate / $from_rate );
    }
    private function get_rates() {
        $settings = get_site_option( 'znc_network_settings', array() );
        return $settings['exchange_rates'] ?? array( 'USD' => 1, 'EUR' => 0.92, 'GBP' => 0.79, 'CAD' => 1.36, 'AUD' => 1.53, 'JPY' => 149.5 );
    }
}
