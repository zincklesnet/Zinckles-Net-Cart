<?php
/**
 * Template: Global Cart — [znc_global_cart] shortcode.
 * Displays all items from all enrolled subsites grouped by shop.
 */
defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
    echo '<div class="woocommerce"><div class="woocommerce-info">Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to view your Net Cart.</div></div>';
    return;
}

$user_id = get_current_user_id();
$grouped = ZNC_Global_Cart_Store::get_cart_grouped( $user_id );
$totals  = ZNC_Currency_Handler::parallel_totals( $user_id );
$host    = new ZNC_Checkout_Host();
?>
<div class="znc-global-cart woocommerce">
    <h2>🛒 Your Global Net Cart</h2>

    <?php if ( empty( $grouped ) ) : ?>
        <div class="woocommerce-info">Your Net Cart is empty. Browse our shops and add products!</div>
    <?php else : ?>
        <?php foreach ( $grouped as $blog_id => $group ) : ?>
            <div class="znc-shop-group" data-blog-id="<?php echo esc_attr( $blog_id ); ?>">
                <div class="znc-shop-header">
                    <span class="znc-shop-badge" style="background:#7c3aed;color:#fff;padding:4px 12px;border-radius:16px;font-size:12px;font-weight:700;">
                        <?php echo esc_html( $group['shop_name'] ); ?>
                    </span>
                    <span class="znc-shop-currency"><?php echo esc_html( $group['currency'] ); ?></span>
                    <span class="znc-shop-items"><?php echo count( $group['items'] ); ?> item(s)</span>
                </div>
                <table class="shop_table shop_table_responsive">
                    <thead>
                        <tr><th class="product-thumbnail">&nbsp;</th><th class="product-name">Product</th><th class="product-price">Price</th><th class="product-quantity">Qty</th><th class="product-subtotal">Subtotal</th><th class="product-remove">&nbsp;</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $group['items'] as $item ) : ?>
                        <tr data-item-id="<?php echo esc_attr( $item['id'] ); ?>">
                            <td class="product-thumbnail">
                                <?php if ( $item['product_image'] ) : ?>
                                    <img src="<?php echo esc_url( $item['product_image'] ); ?>" alt="" style="width:60px;height:60px;object-fit:cover;border-radius:6px;">
                                <?php endif; ?>
                            </td>
                            <td class="product-name">
                                <a href="<?php echo esc_url( $item['product_url'] ); ?>" target="_blank"><?php echo esc_html( $item['product_name'] ); ?></a>
                                <?php if ( $item['product_sku'] ) : ?>
                                    <br><small>SKU: <?php echo esc_html( $item['product_sku'] ); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="product-price"><?php echo wc_price( $item['product_price'], array( 'currency' => $item['currency'] ) ); ?></td>
                            <td class="product-quantity"><?php echo (int) $item['quantity']; ?></td>
                            <td class="product-subtotal"><?php echo wc_price( $item['product_price'] * $item['quantity'], array( 'currency' => $item['currency'] ) ); ?></td>
                            <td class="product-remove">
                                <a href="#" class="znc-remove-item remove" data-item-id="<?php echo esc_attr( $item['id'] ); ?>" title="Remove">&times;</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>

        <div class="znc-cart-totals">
            <h3>Cart Totals</h3>
            <?php if ( count( $totals['per_currency'] ) > 1 ) : ?>
                <div class="znc-mixed-currency-notice" style="background:#fff3cd;padding:10px;border-radius:6px;margin-bottom:12px;">
                    ⚠️ Mixed currencies detected — totals converted to <?php echo esc_html( $totals['base_currency'] ); ?>
                </div>
            <?php endif; ?>

            <table class="shop_table">
                <?php foreach ( $totals['per_currency'] as $currency => $amount ) : ?>
                    <tr>
                        <th>Subtotal (<?php echo esc_html( $currency ); ?>)</th>
                        <td><?php echo wc_price( $amount, array( 'currency' => $currency ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="order-total">
                    <th><strong>Total (<?php echo esc_html( $totals['base_currency'] ); ?>)</strong></th>
                    <td><strong><?php echo wc_price( $totals['converted_total'], array( 'currency' => $totals['base_currency'] ) ); ?></strong></td>
                </tr>
            </table>

            <div class="znc-cart-actions" style="margin-top:16px;">
                <a href="<?php echo esc_url( $host->get_checkout_url() ); ?>" class="checkout-button button alt wc-forward" style="background:#7c3aed;border-color:#7c3aed;">
                    Proceed to Checkout →
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>
