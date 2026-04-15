<?php
defined( 'ABSPATH' ) || exit;
class ZNC_Global_Cart_Merger {
    private $store;
    private $currency;
    public function __construct( ZNC_Global_Cart_Store $store, ZNC_Currency_Handler $currency ) {
        $this->store    = $store;
        $this->currency = $currency;
    }
    public function init() {}
    public function refresh_cart( $user_id ) {
        $items = $this->store->get_cart( $user_id );
        $refreshed = array();
        foreach ( $items as $item ) {
            $blog_id = $item->blog_id;
            switch_to_blog( $blog_id );
            $product = wc_get_product( $item->variation_id ?: $item->product_id );
            if ( $product && $product->is_in_stock() ) {
                global $wpdb;
                restore_current_blog();
                switch_to_blog( ( new ZNC_Checkout_Host() )->get_host_id() );
                $wpdb->update( $wpdb->prefix . 'znc_global_cart', array(
                    'price'     => (float) $product->get_price(),
                    'in_stock'  => 1,
                    'stock_qty' => $product->get_stock_quantity(),
                    'updated_at'=> current_time( 'mysql', true ),
                ), array( 'id' => $item->id ) );
                restore_current_blog();
                $refreshed[] = $item;
            } else {
                restore_current_blog();
                $this->store->remove_item( $item->id, $user_id );
            }
        }
        return $refreshed;
    }
}
