<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Global_Cart_Merger {

    public static function init() {}

    /**
     * Refresh all cart items — validate prices and stock against origin subsites.
     * Uses direct DB reads via switch_to_blog (single switch per subsite group).
     */
    public static function refresh_cart( $user_id ) {
        $grouped = ZNC_Global_Cart_Store::get_cart_grouped( $user_id );
        $removed = array();
        $updated = array();

        foreach ( $grouped as $blog_id => $group ) {
            $current = get_current_blog_id();
            $need_switch = ( (int) $blog_id !== $current );

            if ( $need_switch ) switch_to_blog( $blog_id );

            foreach ( $group['items'] as $item ) {
                $product = wc_get_product( $item['variation_id'] ?: $item['product_id'] );

                if ( ! $product || $product->get_status() !== 'publish' ) {
                    $removed[] = $item;
                    continue;
                }

                $changes = array();
                $current_price = (float) $product->get_price();
                if ( abs( $current_price - (float) $item['product_price'] ) > 0.01 ) {
                    $changes['product_price'] = $current_price;
                }

                $stock_status = $product->get_stock_status();
                if ( $stock_status !== $item['stock_status'] ) {
                    $changes['stock_status'] = $stock_status;
                }

                if ( $product->managing_stock() ) {
                    $stock_qty = $product->get_stock_quantity();
                    if ( $stock_qty !== (int) $item['stock_qty'] ) {
                        $changes['stock_qty'] = $stock_qty;
                    }
                    if ( $stock_qty < $item['quantity'] ) {
                        if ( $stock_qty <= 0 ) {
                            $removed[] = $item;
                            continue;
                        }
                        $changes['quantity'] = $stock_qty;
                    }
                }

                if ( ! empty( $changes ) ) {
                    $changes['item_id'] = $item['id'];
                    $updated[] = $changes;
                }
            }

            if ( $need_switch ) restore_current_blog();
        }

        /* Apply removals */
        foreach ( $removed as $item ) {
            ZNC_Global_Cart_Store::remove_item( $user_id, $item['id'] );
        }

        /* Apply updates */
        if ( ! empty( $updated ) ) {
            global $wpdb;
            $host = new ZNC_Checkout_Host();
            $host_id = $host->get_host_id();
            $current = get_current_blog_id();
            $need_switch = ( $current !== $host_id );
            if ( $need_switch ) switch_to_blog( $host_id );

            $table = $wpdb->prefix . 'znc_global_cart';
            foreach ( $updated as $change ) {
                $id = $change['item_id'];
                unset( $change['item_id'] );
                $wpdb->update( $table, $change, array( 'id' => $id ) );
            }

            if ( $need_switch ) restore_current_blog();
        }

        return array(
            'removed' => count( $removed ),
            'updated' => count( $updated ),
        );
    }
}
