<?php
defined( 'ABSPATH' ) || exit;
class ZNC_Inventory_Sync {
    public function init() {
        add_action( 'znc_inventory_retry', array( $this, 'process_retry_queue' ) );
    }
    public function deduct_for_items( $blog_id, $items ) {
        switch_to_blog( $blog_id );
        foreach ( $items as $item ) {
            $product = wc_get_product( $item->variation_id ?: $item->product_id );
            if ( $product && $product->managing_stock() ) {
                wc_update_product_stock( $product, (int)( $item->quantity ?? 1 ), 'decrease' );
            }
        }
        restore_current_blog();
    }
    public function restore_for_items( $blog_id, $items ) {
        switch_to_blog( $blog_id );
        foreach ( $items as $item ) {
            $product = wc_get_product( $item->variation_id ?: $item->product_id );
            if ( $product && $product->managing_stock() ) {
                wc_update_product_stock( $product, (int)( $item->quantity ?? 1 ), 'increase' );
            }
        }
        restore_current_blog();
    }
    public function process_retry_queue() {
        $queue = get_site_option( 'znc_inventory_retry_queue', array() );
        if ( empty( $queue ) ) return;
        $remaining = array();
        foreach ( $queue as $entry ) {
            if ( ( $entry['attempts'] ?? 0 ) >= 3 ) continue;
            $entry['attempts'] = ( $entry['attempts'] ?? 0 ) + 1;
            switch_to_blog( $entry['blog_id'] );
            $product = wc_get_product( $entry['product_id'] );
            if ( $product && $product->managing_stock() ) {
                wc_update_product_stock( $product, $entry['quantity'], $entry['direction'] ?? 'decrease' );
            } else {
                $remaining[] = $entry;
            }
            restore_current_blog();
        }
        update_site_option( 'znc_inventory_retry_queue', $remaining );
    }
}
