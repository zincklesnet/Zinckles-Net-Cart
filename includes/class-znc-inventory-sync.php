<?php
/**
 * Inventory Sync — v1.4.1
 * Checks stock availability on source subsites before checkout.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Inventory_Sync {

    public function init() {}

    public static function check_stock( $blog_id, $product_id, $variation_id = 0, $qty = 1 ) {
        switch_to_blog( $blog_id );

        if ( ! function_exists( 'wc_get_product' ) ) {
            restore_current_blog();
            return array( 'in_stock' => false, 'reason' => 'WooCommerce not active' );
        }

        $id      = $variation_id > 0 ? $variation_id : $product_id;
        $product = wc_get_product( $id );

        if ( ! $product ) {
            restore_current_blog();
            return array( 'in_stock' => false, 'reason' => 'Product not found' );
        }

        $result = array(
            'in_stock'     => $product->is_in_stock(),
            'manage_stock' => $product->managing_stock(),
            'stock_qty'    => $product->get_stock_quantity(),
            'status'       => $product->get_stock_status(),
            'enough'       => true,
            'reason'       => '',
        );

        if ( ! $result['in_stock'] ) {
            $result['enough'] = false;
            $result['reason'] = 'Out of stock';
        } elseif ( $result['manage_stock'] && $result['stock_qty'] < $qty ) {
            $result['enough'] = false;
            $result['reason'] = sprintf( 'Only %d in stock', $result['stock_qty'] );
        }

        restore_current_blog();
        return $result;
    }

    public static function validate_cart_stock( $items ) {
        $errors = array();
        foreach ( $items as $item ) {
            $check = self::check_stock(
                $item['blog_id'],
                $item['product_id'],
                $item['variation_id'] ?? 0,
                $item['quantity']
            );
            if ( ! $check['enough'] ) {
                $errors[] = array(
                    'product_name' => $item['product_name'],
                    'shop_name'    => $item['shop_name'],
                    'reason'       => $check['reason'],
                    'available'    => $check['stock_qty'],
                    'requested'    => $item['quantity'],
                );
            }
        }
        return $errors;
    }

    public static function refresh_item_data( $item ) {
        $blog_id = $item['blog_id'];
        $pid     = ! empty( $item['variation_id'] ) ? $item['variation_id'] : $item['product_id'];

        switch_to_blog( $blog_id );
        if ( ! function_exists( 'wc_get_product' ) ) {
            restore_current_blog();
            return $item;
        }

        $product = wc_get_product( $pid );
        if ( ! $product ) {
            restore_current_blog();
            $item['in_stock'] = 0;
            return $item;
        }

        $item['price']     = (float) $product->get_price();
        $item['in_stock']  = $product->is_in_stock() ? 1 : 0;
        $item['stock_qty'] = $product->get_stock_quantity();
        $item['sku']       = $product->get_sku();
        $item['image_url'] = wp_get_attachment_url( $product->get_image_id() ) ?: '';
        $item['line_total'] = $item['price'] * $item['quantity'];

        restore_current_blog();
        return $item;
    }
}
