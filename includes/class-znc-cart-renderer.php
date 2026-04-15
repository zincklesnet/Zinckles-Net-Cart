<?php
/**
 * Cart Renderer — Enriched cart data and HTML rendering.
 *
 * Fixes from v1.6.1:
 *  • Removed hardcoded '$' from grand total — uses ZNC_Currency_Handler
 *  • Grand total now separates per-currency totals (no naive summing)
 *  • Added point-type column for ZCred items
 *
 * @package ZincklesNetCart
 * @since   1.7.0
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Cart_Renderer {

    /**
     * Build enriched cart data for rendering.
     *
     * @param int $user_id
     * @return array  [ blog_id => [ 'name', 'items', 'subtotal', 'currency', 'currency_symbol' ] ]
     */
    public function get_enriched_cart( $user_id ) {
        $cart  = ZNC_Global_Cart::instance();
        $blogs = $cart->get_items_by_blog( $user_id );

        if ( empty( $blogs ) ) {
            return array();
        }

        $enriched = array();

        foreach ( $blogs as $blog_id => $items ) {
            switch_to_blog( $blog_id );

            if ( ! function_exists( 'wc_get_product' ) ) {
                restore_current_blog();
                continue;
            }

            $blog_details = get_blog_details( $blog_id );
            $currency     = get_woocommerce_currency();
            $symbol       = get_woocommerce_currency_symbol();
            $shop_items   = array();
            $subtotal     = 0;

            foreach ( $items as $item ) {
                $pid     = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];
                $product = wc_get_product( $pid );

                if ( ! $product ) {
                    continue;
                }

                $price     = (float) $product->get_price();
                $line_total = $price * (int) $item['quantity'];
                $subtotal  += $line_total;

                $image_id  = $product->get_image_id();
                $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';

                $variation_data = array();
                if ( $item['variation_id'] && $product instanceof WC_Product_Variation ) {
                    $variation_data = $product->get_variation_attributes();
                }

                $shop_items[] = array(
                    'product_id'   => $item['product_id'],
                    'variation_id' => $item['variation_id'],
                    'quantity'     => $item['quantity'],
                    'name'         => $product->get_name(),
                    'price'        => $price,
                    'line_total'   => $line_total,
                    'image'        => $image_url,
                    'permalink'    => $product->get_permalink(),
                    'sku'          => $product->get_sku(),
                    'stock_status' => $product->get_stock_status(),
                    'stock_qty'    => $product->managing_stock() ? $product->get_stock_quantity() : null,
                    'variation'    => $variation_data,
                    'cart_key'     => $cart->make_key( $blog_id, $item['product_id'], $item['variation_id'] ),
                );
            }

            if ( ! empty( $shop_items ) ) {
                $enriched[ $blog_id ] = array(
                    'name'            => $blog_details ? $blog_details->blogname : "Shop #{$blog_id}",
                    'url'             => home_url( '/' ),
                    'items'           => $shop_items,
                    'subtotal'        => $subtotal,
                    'currency'        => $currency,
                    'currency_symbol' => $symbol,
                    'is_points'       => ZNC_Currency_Handler::is_points_currency( $blog_id ),
                );
            }

            restore_current_blog();
        }

        return $enriched;
    }

    /**
     * Get per-currency totals (replaces the old naive get_cart_total).
     *
     * @param int $user_id
     * @return array  [ 'USD' => [ 'total' => 252.00, 'symbol' => '$', 'label' => 'US Dollar' ], ... ]
     */
    public function get_cart_totals( $user_id ) {
        $enriched = $this->get_enriched_cart( $user_id );
        return ZNC_Currency_Handler::get_totals_by_currency( $enriched );
    }

    /**
     * Legacy: get_cart_total — backward compat, returns naive sum.
     * Use get_cart_totals() for currency-safe totals.
     *
     * @deprecated Use get_cart_totals() instead.
     * @param int $user_id
     * @return float
     */
    public function get_cart_total( $user_id ) {
        $enriched = $this->get_enriched_cart( $user_id );
        $total    = 0;
        foreach ( $enriched as $shop ) {
            $total += $shop['subtotal'];
        }
        return $total;
    }

    /**
     * Render the full cart page HTML.
     *
     * @param int $user_id
     * @return string HTML
     */
    public function render_cart( $user_id ) {
        $enriched = $this->get_enriched_cart( $user_id );

        if ( empty( $enriched ) ) {
            return '<div class="woocommerce-info">'
                . esc_html__( 'Your global cart is empty.', 'zinckles-net-cart' )
                . '</div>';
        }

        $nonce           = wp_create_nonce( 'znc_cart_action' );
        $currency_totals = ZNC_Currency_Handler::get_totals_by_currency( $enriched );

        ob_start();
        ?>
        <div class="znc-global-cart woocommerce" data-nonce="<?php echo esc_attr( $nonce ); ?>">
        <?php foreach ( $enriched as $blog_id => $shop ) :
            $curr      = ZNC_Currency_Handler::get_blog_currency( $blog_id );
            $is_points = ! empty( $shop['is_points'] );
        ?>
            <div class="znc-shop-section" data-blog="<?php echo esc_attr( $blog_id ); ?>">
                <h3 class="znc-shop-title">
                    <a href="<?php echo esc_url( $shop['url'] ); ?>"><?php echo esc_html( $shop['name'] ); ?></a>
                    <?php if ( $is_points ) : ?>
                        <span class="znc-badge znc-badge-points"><?php echo esc_html( $curr['label'] ); ?></span>
                    <?php endif; ?>
                </h3>

                <table class="znc-cart-table shop_table cart woocommerce-cart-form__contents">
                    <thead>
                        <tr>
                            <th class="product-remove">&nbsp;</th>
                            <th class="product-thumbnail">&nbsp;</th>
                            <th class="product-name"><?php esc_html_e( 'Product', 'zinckles-net-cart' ); ?></th>
                            <th class="product-price"><?php esc_html_e( 'Price', 'zinckles-net-cart' ); ?></th>
                            <th class="product-quantity"><?php esc_html_e( 'Quantity', 'zinckles-net-cart' ); ?></th>
                            <th class="product-subtotal"><?php esc_html_e( 'Subtotal', 'zinckles-net-cart' ); ?></th>
                            <?php if ( $is_points ) : ?>
                                <th class="product-points"><?php esc_html_e( 'Point Type', 'zinckles-net-cart' ); ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $shop['items'] as $item ) :
                        $line_total = (float) $item['price'] * (int) $item['quantity'];
                        $point_label = '';
                        if ( $is_points && class_exists( 'ZNC_MyCred_Engine' ) ) {
                            $point_label = ZNC_MyCred_Engine::get_product_point_label( $blog_id, $item['product_id'] );
                        }
                    ?>
                        <tr class="znc-cart-item"
                            data-key="<?php echo esc_attr( $item['cart_key'] ); ?>"
                            data-price="<?php echo esc_attr( $item['price'] ); ?>"
                            data-blog="<?php echo esc_attr( $blog_id ); ?>"
                            data-symbol="<?php echo esc_attr( $curr['symbol'] ); ?>">
                            <td class="product-remove">
                                <button type="button" class="znc-remove-item remove"
                                        data-key="<?php echo esc_attr( $item['cart_key'] ); ?>"
                                        aria-label="<?php esc_attr_e( 'Remove item', 'zinckles-net-cart' ); ?>">&times;</button>
                            </td>
                            <td class="product-thumbnail">
                                <?php if ( $item['image'] ) : ?>
                                    <img src="<?php echo esc_url( $item['image'] ); ?>"
                                         alt="<?php echo esc_attr( $item['name'] ); ?>"
                                         width="60" height="60">
                                <?php endif; ?>
                            </td>
                            <td class="product-name">
                                <a href="<?php echo esc_url( $item['permalink'] ); ?>">
                                    <?php echo esc_html( $item['name'] ); ?>
                                </a>
                                <?php if ( ! empty( $item['variation'] ) ) : ?>
                                    <dl class="variation">
                                    <?php foreach ( (array) $item['variation'] as $attr => $val ) :
                                        $attr_label = ucfirst( str_replace( 'attribute_pa_', '', $attr ) );
                                    ?>
                                        <dt><?php echo esc_html( $attr_label ); ?>:</dt>
                                        <dd><?php echo esc_html( $val ); ?></dd>
                                    <?php endforeach; ?>
                                    </dl>
                                <?php endif; ?>
                                <?php if ( $item['stock_status'] === 'outofstock' ) : ?>
                                    <span class="znc-stock-warning">⚠ <?php esc_html_e( 'Out of stock', 'zinckles-net-cart' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="product-price" data-title="<?php esc_attr_e( 'Price', 'zinckles-net-cart' ); ?>">
                                <?php echo esc_html( ZNC_Currency_Handler::format_price( $item['price'], $curr['symbol'] ) ); ?>
                            </td>
                            <td class="product-quantity" data-title="<?php esc_attr_e( 'Quantity', 'zinckles-net-cart' ); ?>">
                                <div class="znc-qty-controls">
                                    <button type="button" class="znc-qty-minus" data-key="<?php echo esc_attr( $item['cart_key'] ); ?>">−</button>
                                    <input type="number" class="znc-qty-input"
                                           value="<?php echo intval( $item['quantity'] ); ?>"
                                           min="1" max="<?php echo esc_attr( $item['stock_qty'] ?? 999 ); ?>"
                                           data-key="<?php echo esc_attr( $item['cart_key'] ); ?>">
                                    <button type="button" class="znc-qty-plus" data-key="<?php echo esc_attr( $item['cart_key'] ); ?>">+</button>
                                </div>
                            </td>
                            <td class="product-subtotal znc-line-total" data-title="<?php esc_attr_e( 'Subtotal', 'zinckles-net-cart' ); ?>">
                                <?php echo esc_html( ZNC_Currency_Handler::format_price( $line_total, $curr['symbol'] ) ); ?>
                            </td>
                            <?php if ( $is_points ) : ?>
                                <td class="product-points">
                                    <span class="znc-point-type"><?php echo esc_html( $point_label ?: $curr['label'] ); ?></span>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" class="znc-text-right">
                                <strong><?php echo esc_html( $shop['name'] ); ?> <?php esc_html_e( 'Subtotal', 'zinckles-net-cart' ); ?>:</strong>
                            </td>
                            <td colspan="<?php echo $is_points ? 2 : 1; ?>" class="znc-shop-subtotal"
                                data-blog="<?php echo esc_attr( $blog_id ); ?>">
                                <strong>
                                    <?php echo esc_html( ZNC_Currency_Handler::format_price( $shop['subtotal'], $curr['symbol'] ) ); ?>
                                    <?php if ( $is_points ) : ?>
                                        <small>(<?php echo esc_html( $curr['label'] ); ?>)</small>
                                    <?php endif; ?>
                                </strong>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endforeach; ?>

            <!-- Grand Totals (per currency) -->
            <div class="znc-grand-total-section">
                <h3><?php esc_html_e( 'Grand Total', 'zinckles-net-cart' ); ?></h3>
                <div class="znc-grand-total">
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo ZNC_Currency_Handler::render_grand_total_html( $enriched );
                    ?>
                </div>
            </div>

            <!-- Cart Actions -->
            <div class="znc-cart-actions">
                <button type="button" class="znc-clear-cart button"><?php esc_html_e( 'Clear Cart', 'zinckles-net-cart' ); ?></button>
                <?php
                $checkout_url = ZNC_Checkout_Host::get_checkout_url();
                if ( $checkout_url ) :
                ?>
                    <a href="<?php echo esc_url( $checkout_url ); ?>" class="button alt znc-checkout-button">
                        <?php esc_html_e( 'Proceed to Checkout', 'zinckles-net-cart' ); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
