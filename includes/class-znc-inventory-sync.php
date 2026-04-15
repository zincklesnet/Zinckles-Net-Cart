<?php
/**
 * Inventory Sync — Cross-site stock validation before checkout.
 *
 * Checks stock on each subsite, reports out-of-stock/insufficient items,
 * and optionally reduces stock on order creation.
 *
 * @package ZincklesNetCart
 * @since   1.7.0
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Inventory_Sync {

    public function init() {
        // Hook into child order completion to reduce stock
        add_action( 'woocommerce_order_status_processing', array( $this, 'maybe_reduce_stock' ), 10, 1 );
    }

    /**
     * Validate stock for all items in the global cart.
     *
     * @param array $enriched  Enriched cart from ZNC_Cart_Renderer.
     * @return array  [ 'valid' => bool, 'errors' => [ [ 'blog_id', 'product_id', 'name', 'requested', 'available' ] ] ]
     */
    public static function validate_stock( $enriched ) {
        $errors = array();

        foreach ( $enriched as $blog_id => $shop ) {
            switch_to_blog( $blog_id );

            foreach ( $shop['items'] as $item ) {
                $pid     = $item['variation_id'] ?: $item['product_id'];
                $product = function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;

                if ( ! $product ) {
                    $errors[] = array(
                        'blog_id'    => $blog_id,
                        'product_id' => $pid,
                        'name'       => $item['name'] ?? "Product #{$pid}",
                        'requested'  => $item['quantity'],
                        'available'  => 0,
                        'reason'     => 'not_found',
                        'message'    => sprintf( __( '"%s" is no longer available.', 'zinckles-net-cart' ), $item['name'] ?? "Product #{$pid}" ),
                    );
                    continue;
                }

                // Check if product is purchasable
                if ( ! $product->is_purchasable() ) {
                    $errors[] = array(
                        'blog_id'    => $blog_id,
                        'product_id' => $pid,
                        'name'       => $product->get_name(),
                        'requested'  => $item['quantity'],
                        'available'  => 0,
                        'reason'     => 'not_purchasable',
                        'message'    => sprintf( __( '"%s" cannot be purchased.', 'zinckles-net-cart' ), $product->get_name() ),
                    );
                    continue;
                }

                // Check stock management
                if ( $product->managing_stock() ) {
                    $stock_qty = $product->get_stock_quantity();

                    if ( $stock_qty < $item['quantity'] ) {
                        $errors[] = array(
                            'blog_id'    => $blog_id,
                            'product_id' => $pid,
                            'name'       => $product->get_name(),
                            'requested'  => $item['quantity'],
                            'available'  => $stock_qty,
                            'reason'     => 'insufficient_stock',
                            'message'    => sprintf(
                                __( '"%s" — only %d available (you have %d in cart).', 'zinckles-net-cart' ),
                                $product->get_name(), $stock_qty, $item['quantity']
                            ),
                        );
                    }
                } elseif ( ! $product->is_in_stock() ) {
                    $errors[] = array(
                        'blog_id'    => $blog_id,
                        'product_id' => $pid,
                        'name'       => $product->get_name(),
                        'requested'  => $item['quantity'],
                        'available'  => 0,
                        'reason'     => 'out_of_stock',
                        'message'    => sprintf( __( '"%s" is out of stock.', 'zinckles-net-cart' ), $product->get_name() ),
                    );
                }
            }

            restore_current_blog();
        }

        return array(
            'valid'  => empty( $errors ),
            'errors' => $errors,
        );
    }

    /**
     * Render stock validation errors as HTML.
     *
     * @param array $errors
     * @return string
     */
    public static function render_stock_errors( $errors ) {
        if ( empty( $errors ) ) return '';

        $html = '<div class="znc-stock-errors woocommerce-error">';
        $html .= '<strong>' . esc_html__( 'Some items in your cart have stock issues:', 'zinckles-net-cart' ) . '</strong>';
        $html .= '<ul>';
        foreach ( $errors as $err ) {
            $html .= '<li>' . esc_html( $err['message'] ) . '</li>';
        }
        $html .= '</ul></div>';

        return $html;
    }

    /**
     * Reduce stock after WC marks order as processing (only for child orders).
     *
     * @param int $order_id
     */
    public function maybe_reduce_stock( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        // Only act on ZNC child orders
        if ( $order->get_meta( '_znc_is_child_order' ) !== 'yes' ) return;
        if ( $order->get_meta( '_znc_stock_reduced' ) === 'yes' ) return;

        wc_reduce_stock_levels( $order_id );
        $order->update_meta_data( '_znc_stock_reduced', 'yes' );
        $order->save();
    }
}
