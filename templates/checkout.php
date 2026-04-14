<?php
/**
 * Checkout Template — [znc_checkout] shortcode
 *
 * v1.2.0: Multi-point-type MyCred support at checkout.
 *
 * @package ZincklesNetCart
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
    echo '<div class="znc-notice znc-notice-info">';
    echo '<p>' . __( 'Please log in to checkout.', 'znc' ) . '</p>';
    echo '<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '" class="button">' . __( 'Log In', 'znc' ) . '</a>';
    echo '</div>';
    return;
}

$user_id  = get_current_user_id();
$store    = new ZNC_Global_Cart_Store();
$currency = new ZNC_Currency_Handler();
$mycred   = new ZNC_MyCred_Engine();

$items   = $store->get_cart( $user_id );
$shops   = $store->get_cart( $user_id, 'shop' );
$summary = $store->get_cart_summary( $user_id );
$totals  = $currency->parallel_totals( $items );

// MyCred balances for all enabled point types.
$balances     = $mycred->get_all_balances( $user_id );
$point_totals = $mycred->get_parallel_totals( $user_id, $totals['converted_total'] );
?>

<div class="znc-checkout" data-user-id="<?php echo $user_id; ?>">

    <?php if ( empty( $items ) ) : ?>
        <div class="znc-notice">
            <h3><?php _e( 'Your cart is empty', 'znc' ); ?></h3>
            <p><?php _e( 'Add products from shops across the network first.', 'znc' ); ?></p>
        </div>
        <?php return; ?>
    <?php endif; ?>

    <h2><?php _e( '🛒 Net Cart Checkout', 'znc' ); ?></h2>

    <!-- ── Order Summary ──────────────────────────────────── -->
    <div class="znc-checkout-section">
        <h3><?php _e( 'Order Summary', 'znc' ); ?></h3>
        <?php foreach ( $shops as $shop ) : ?>
            <div class="znc-checkout-shop">
                <h4>
                    <span class="znc-shop-badge-mini">🏪</span>
                    <?php echo esc_html( $shop['shop_name'] ); ?>
                    <span class="znc-currency-chip"><?php echo esc_html( $shop['currency'] ); ?></span>
                </h4>
                <ul class="znc-checkout-items">
                    <?php foreach ( $shop['items'] as $item ) : ?>
                        <li>
                            <?php echo esc_html( $item['product_name'] ); ?>
                            × <?php echo (int) $item['quantity']; ?>
                            — <?php echo esc_html( $item['currency'] ); ?> <?php echo number_format( $item['line_total'], 2 ); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="znc-shop-subtotal">
                    <?php _e( 'Subtotal:', 'znc' ); ?>
                    <strong><?php echo esc_html( $shop['currency'] ); ?> <?php echo number_format( $shop['subtotal'], 2 ); ?></strong>
                </p>
            </div>
        <?php endforeach; ?>

        <?php if ( $totals['is_mixed'] ) : ?>
            <div class="znc-conversion-note">
                <p><?php _e( 'Currency Conversion:', 'znc' ); ?></p>
                <?php foreach ( $totals['per_currency'] as $cur => $amt ) : ?>
                    <span class="znc-currency-chip"><?php echo esc_html( $cur ); ?> <?php echo number_format( $amt, 2 ); ?></span>
                <?php endforeach; ?>
                <p>→ <strong><?php echo esc_html( $totals['base_currency'] ); ?> <?php echo number_format( $totals['converted_total'], 2 ); ?></strong></p>
            </div>
        <?php endif; ?>

        <div class="znc-order-total">
            <span><?php _e( 'Order Total:', 'znc' ); ?></span>
            <strong><?php echo esc_html( $totals['base_currency'] ); ?> <?php echo number_format( $totals['converted_total'], 2 ); ?></strong>
        </div>
    </div>

    <!-- ── Points Payment ─────────────────────────────────── -->
    <?php if ( ! empty( $balances ) ) : ?>
        <div class="znc-checkout-section">
            <h3><?php _e( '⚡ Pay with Points', 'znc' ); ?></h3>
            <?php foreach ( $point_totals as $slug => $pt ) : ?>
                <div class="znc-point-type-row" data-point-type="<?php echo esc_attr( $slug ); ?>">
                    <label>
                        <strong><?php echo esc_html( $pt['label'] ); ?></strong>
                        <span class="znc-balance-display">
                            <?php _e( 'Balance:', 'znc' ); ?> <?php echo number_format( $pt['balance'], 0 ); ?>
                            (<?php printf( __( 'worth %s %s', 'znc' ), $totals['base_currency'], number_format( $pt['balance_value'], 2 ) ); ?>)
                        </span>
                    </label>
                    <div class="znc-point-slider">
                        <input type="range" min="0" max="<?php echo esc_attr( $pt['points_to_deduct'] ); ?>"
                               value="0" class="znc-zcred-slider"
                               name="zcred_deductions[<?php echo esc_attr( $slug ); ?>]">
                        <span class="znc-zcred-value">0</span> <?php echo esc_html( $pt['label'] ); ?>
                        = <span class="znc-zcred-currency">0.00</span> <?php echo esc_html( $totals['base_currency'] ); ?>
                    </div>
                    <p class="description">
                        <?php printf( __( 'Max %d%% of order (%s %s)', 'znc' ),
                            $pt['max_percent'],
                            $totals['base_currency'],
                            number_format( $pt['max_applicable'], 2 )
                        ); ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ── Place Order ────────────────────────────────────── -->
    <div class="znc-checkout-section">
        <div class="znc-monetary-remaining">
            <span><?php _e( 'Remaining to pay:', 'znc' ); ?></span>
            <strong class="znc-remaining-amount">
                <?php echo esc_html( $totals['base_currency'] ); ?>
                <?php echo number_format( $totals['converted_total'], 2 ); ?>
            </strong>
        </div>
        <button type="button" class="znc-place-order-btn button alt woocommerce-button">
            <?php _e( 'Place Order', 'znc' ); ?>
        </button>
    </div>
</div>
