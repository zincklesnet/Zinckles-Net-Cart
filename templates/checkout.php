<?php
/**
 * Template: Checkout — [znc_checkout] shortcode.
 * Validates, processes payment, deducts MyCred, creates orders.
 */
defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
    echo '<div class="woocommerce"><div class="woocommerce-info">Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to checkout.</div></div>';
    return;
}

$user_id = get_current_user_id();
$grouped = ZNC_Global_Cart_Store::get_cart_grouped( $user_id );
$totals  = ZNC_Currency_Handler::parallel_totals( $user_id );
$host    = new ZNC_Checkout_Host();

$mycred_types = ZNC_MyCred_Engine::get_types_config();
$has_mycred   = ! empty( array_filter( $mycred_types, function($c){ return !empty($c['enabled']); } ) );

if ( empty( $grouped ) ) {
    echo '<div class="woocommerce"><div class="woocommerce-info">Your Net Cart is empty. <a href="' . esc_url( $host->get_cart_url() ) . '">View Cart</a></div></div>';
    return;
}
?>
<div class="znc-checkout woocommerce">
    <h2>🛒 Net Cart Checkout</h2>

    <div class="znc-checkout-summary">
        <h3>Order Summary</h3>
        <?php foreach ( $grouped as $blog_id => $group ) : ?>
            <div class="znc-checkout-shop">
                <h4 style="display:flex;align-items:center;gap:8px;">
                    <span style="background:#7c3aed;color:#fff;padding:3px 10px;border-radius:12px;font-size:11px;"><?php echo esc_html( $group['shop_name'] ); ?></span>
                    <span style="color:#666;font-size:12px;"><?php echo esc_html( $group['currency'] ); ?></span>
                </h4>
                <?php foreach ( $group['items'] as $item ) : ?>
                    <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid #f0f0f0;">
                        <span><?php echo esc_html( $item['product_name'] ); ?> &times; <?php echo (int) $item['quantity']; ?></span>
                        <span><?php echo wc_price( $item['product_price'] * $item['quantity'], array( 'currency' => $item['currency'] ) ); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <div class="znc-checkout-totals" style="margin-top:20px;padding:16px;background:#f9f9f9;border-radius:8px;">
            <?php foreach ( $totals['per_currency'] as $currency => $amount ) : ?>
                <div style="display:flex;justify-content:space-between;padding:4px 0;">
                    <span>Subtotal (<?php echo esc_html( $currency ); ?>)</span>
                    <span><?php echo wc_price( $amount, array( 'currency' => $currency ) ); ?></span>
                </div>
            <?php endforeach; ?>

            <?php if ( $has_mycred ) : ?>
                <div style="margin-top:12px;padding-top:12px;border-top:1px solid #ddd;">
                    <h4>💰 Pay with Points</h4>
                    <?php foreach ( $mycred_types as $slug => $cfg ) :
                        if ( empty( $cfg['enabled'] ) ) continue;
                        $info    = $cfg['info'] ?? array();
                        $balance = ZNC_MyCred_Engine::get_balance( $user_id, $slug );
                        $max_pct = $cfg['max_percent'] ?? 100;
                        $rate    = $cfg['exchange_rate'] ?? 0;
                    ?>
                        <div style="display:flex;align-items:center;gap:12px;padding:6px 0;">
                            <label style="min-width:120px;">
                                <strong><?php echo esc_html( $info['label'] ?? $slug ); ?></strong>
                                <br><small>Balance: <?php echo number_format( $balance ); ?> | Rate: 1 pt = $<?php echo $rate; ?> | Max: <?php echo $max_pct; ?>%</small>
                            </label>
                            <input type="number" name="znc_mycred[<?php echo esc_attr( $slug ); ?>]" value="0" min="0" max="<?php echo esc_attr( $balance ); ?>" style="width:100px;" class="znc-mycred-input">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div style="display:flex;justify-content:space-between;padding:12px 0;font-size:18px;font-weight:700;border-top:2px solid #7c3aed;margin-top:12px;">
                <span>Total (<?php echo esc_html( $totals['base_currency'] ); ?>)</span>
                <span><?php echo wc_price( $totals['converted_total'], array( 'currency' => $totals['base_currency'] ) ); ?></span>
            </div>
        </div>

        <div style="margin-top:24px;">
            <form method="post" id="znc-checkout-form">
                <?php wp_nonce_field( 'znc_checkout', 'znc_checkout_nonce' ); ?>
                <button type="submit" class="button alt" style="background:#7c3aed;border-color:#7c3aed;color:#fff;width:100%;padding:14px;font-size:16px;font-weight:700;border-radius:8px;">
                    Place Order →
                </button>
            </form>
        </div>
    </div>
</div>
