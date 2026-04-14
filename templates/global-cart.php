<?php
/**
 * Global Cart Template — [znc_global_cart] shortcode
 *
 * v1.2.0: Shows ALL products from ALL enrolled subsites in one unified cart,
 * grouped by shop with currency badges and per-shop subtotals.
 *
 * @package ZincklesNetCart
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
    echo '<div class="znc-notice znc-notice-info">';
    echo '<p>' . __( 'Please log in to view your Net Cart.', 'znc' ) . '</p>';
    echo '<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '" class="button">' . __( 'Log In', 'znc' ) . '</a>';
    echo '</div>';
    return;
}

$user_id = get_current_user_id();
$store   = new ZNC_Global_Cart_Store();

// Get cart grouped by shop — this pulls items from ALL subsites.
$shops   = $store->get_cart( $user_id, 'shop' );
$summary = $store->get_cart_summary( $user_id );

$total_items      = (int) ( $summary['total_items'] ?? 0 );
$total_shops      = (int) ( $summary['total_shops'] ?? 0 );
$total_currencies = (int) ( $summary['total_currencies'] ?? 0 );
$currency_totals  = (array) ( $summary['currency_totals'] ?? array() );

// Network settings for checkout URL.
$settings     = get_site_option( 'znc_network_settings', array() );
$checkout_url = get_permalink( get_option( 'znc_checkout_page_id' ) );
if ( ! $checkout_url ) {
    // Fallback: look for page with [znc_checkout] shortcode.
    $pages = get_pages();
    foreach ( $pages as $page ) {
        if ( has_shortcode( $page->post_content, 'znc_checkout' ) ) {
            $checkout_url = get_permalink( $page->ID );
            break;
        }
    }
}
?>

<div class="znc-global-cart" data-user-id="<?php echo $user_id; ?>">

    <!-- ── Cart Header ────────────────────────────────────── -->
    <div class="znc-cart-header">
        <h2 class="znc-cart-title">
            <?php _e( '🛒 Your Net Cart', 'znc' ); ?>
        </h2>

        <?php if ( $total_items > 0 ) : ?>
            <div class="znc-cart-stats">
                <span class="znc-stat">
                    <strong><?php echo $total_items; ?></strong> <?php _e( 'items', 'znc' ); ?>
                </span>
                <span class="znc-stat">
                    <strong><?php echo $total_shops; ?></strong> <?php _e( 'shops', 'znc' ); ?>
                </span>
                <?php if ( $total_currencies > 1 ) : ?>
                    <span class="znc-stat znc-mixed-badge">
                        <?php _e( '🌐 Mixed Currency', 'znc' ); ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ( empty( $shops ) ) : ?>
        <!-- ── Empty Cart ─────────────────────────────────────── -->
        <div class="znc-empty-cart">
            <div class="znc-empty-icon">🛒</div>
            <h3><?php _e( 'Your Net Cart is empty', 'znc' ); ?></h3>
            <p><?php _e( 'Products you add from any shop across the network will appear here.', 'znc' ); ?></p>
        </div>

    <?php else : ?>
        <!-- ── Cart Items Grouped by Shop ─────────────────────── -->
        <?php foreach ( $shops as $shop ) : ?>
            <div class="znc-shop-group" data-blog-id="<?php echo esc_attr( $shop['blog_id'] ); ?>">

                <!-- Shop Header -->
                <div class="znc-shop-header">
                    <div class="znc-shop-badge" style="border-left-color: #7c3aed;">
                        <span class="znc-shop-icon">🏪</span>
                        <div class="znc-shop-info">
                            <strong class="znc-shop-name">
                                <?php echo esc_html( $shop['shop_name'] ?: __( 'Shop', 'znc' ) ); ?>
                            </strong>
                            <a href="<?php echo esc_url( $shop['shop_url'] ); ?>" target="_blank" class="znc-shop-url">
                                <?php echo esc_html( $shop['shop_url'] ); ?>
                            </a>
                        </div>
                    </div>
                    <div class="znc-shop-meta">
                        <span class="znc-currency-chip"><?php echo esc_html( $shop['currency'] ); ?></span>
                        <span class="znc-item-count">
                            <?php echo count( $shop['items'] ); ?> <?php _e( 'items', 'znc' ); ?>
                        </span>
                    </div>
                </div>

                <!-- Items Table -->
                <table class="znc-items-table">
                    <thead>
                        <tr>
                            <th class="znc-col-image"></th>
                            <th class="znc-col-product"><?php _e( 'Product', 'znc' ); ?></th>
                            <th class="znc-col-price"><?php _e( 'Price', 'znc' ); ?></th>
                            <th class="znc-col-qty"><?php _e( 'Qty', 'znc' ); ?></th>
                            <th class="znc-col-total"><?php _e( 'Total', 'znc' ); ?></th>
                            <th class="znc-col-stock"><?php _e( 'Stock', 'znc' ); ?></th>
                            <th class="znc-col-actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $shop['items'] as $item ) :
                            $variation = maybe_unserialize( $item['variation_data'] );
                        ?>
                            <tr class="znc-cart-item <?php echo ! $item['in_stock'] ? 'znc-out-of-stock' : ''; ?>"
                                data-line-id="<?php echo esc_attr( $item['id'] ); ?>">

                                <td class="znc-col-image">
                                    <?php if ( $item['image_url'] ) : ?>
                                        <img src="<?php echo esc_url( $item['image_url'] ); ?>"
                                             alt="<?php echo esc_attr( $item['product_name'] ); ?>"
                                             class="znc-product-thumb" width="60" height="60">
                                    <?php else : ?>
                                        <div class="znc-no-image">📦</div>
                                    <?php endif; ?>
                                </td>

                                <td class="znc-col-product">
                                    <a href="<?php echo esc_url( $item['permalink'] ); ?>" target="_blank">
                                        <?php echo esc_html( $item['product_name'] ); ?>
                                    </a>
                                    <?php if ( $item['sku'] ) : ?>
                                        <br><small class="znc-sku">SKU: <?php echo esc_html( $item['sku'] ); ?></small>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $variation ) && is_array( $variation ) ) : ?>
                                        <div class="znc-variation-info">
                                            <?php foreach ( $variation as $attr => $val ) : ?>
                                                <span class="znc-variation-attr">
                                                    <?php echo esc_html( ucfirst( str_replace( 'attribute_pa_', '', $attr ) ) ); ?>:
                                                    <?php echo esc_html( $val ); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td class="znc-col-price">
                                    <?php echo esc_html( $item['currency'] ); ?> <?php echo number_format( $item['price'], 2 ); ?>
                                </td>

                                <td class="znc-col-qty">
                                    <?php echo (int) $item['quantity']; ?>
                                </td>

                                <td class="znc-col-total">
                                    <strong><?php echo esc_html( $item['currency'] ); ?> <?php echo number_format( $item['line_total'], 2 ); ?></strong>
                                </td>

                                <td class="znc-col-stock">
                                    <?php if ( $item['in_stock'] ) : ?>
                                        <span class="znc-stock-ok" title="In stock">✅</span>
                                        <?php if ( $item['stock_qty'] !== null ) : ?>
                                            <small>(<?php echo (int) $item['stock_qty']; ?>)</small>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="znc-stock-out" title="Out of stock">❌</span>
                                    <?php endif; ?>
                                </td>

                                <td class="znc-col-actions">
                                    <button type="button" class="znc-remove-item"
                                            data-line-id="<?php echo esc_attr( $item['id'] ); ?>"
                                            title="<?php _e( 'Remove', 'znc' ); ?>">
                                        ✕
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="znc-shop-subtotal-label">
                                <?php _e( 'Shop Subtotal', 'znc' ); ?>
                            </td>
                            <td colspan="3" class="znc-shop-subtotal-value">
                                <strong><?php echo esc_html( $shop['currency'] ); ?> <?php echo number_format( $shop['subtotal'], 2 ); ?></strong>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endforeach; ?>

        <!-- ── Cart Totals ────────────────────────────────────── -->
        <div class="znc-cart-totals">
            <h3><?php _e( 'Cart Totals', 'znc' ); ?></h3>

            <?php if ( $total_currencies > 1 ) : ?>
                <div class="znc-currency-breakdown">
                    <p class="znc-breakdown-label"><?php _e( 'Per-Currency Subtotals:', 'znc' ); ?></p>
                    <?php foreach ( $currency_totals as $ct ) : ?>
                        <div class="znc-currency-line">
                            <span class="znc-currency-chip"><?php echo esc_html( $ct['currency'] ); ?></span>
                            <strong><?php echo number_format( (float) $ct['subtotal'], 2 ); ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <?php
                $single_currency = ! empty( $currency_totals[0] ) ? $currency_totals[0] : null;
                if ( $single_currency ) :
                ?>
                    <div class="znc-single-total">
                        <span><?php _e( 'Subtotal:', 'znc' ); ?></span>
                        <strong>
                            <?php echo esc_html( $single_currency['currency'] ); ?>
                            <?php echo number_format( (float) $single_currency['subtotal'], 2 ); ?>
                        </strong>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ( $checkout_url ) : ?>
                <a href="<?php echo esc_url( $checkout_url ); ?>" class="znc-checkout-btn button alt">
                    <?php _e( 'Proceed to Checkout →', 'znc' ); ?>
                </a>
            <?php endif; ?>
        </div>

    <?php endif; ?>
</div>
