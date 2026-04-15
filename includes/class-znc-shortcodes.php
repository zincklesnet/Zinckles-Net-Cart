<?php
/**
 * Shortcodes — All 9 shortcodes with currency fixes.
 *
 * Fixes from v1.6.1:
 *  • [znc_cart_total] — removed hardcoded '$', uses ZNC_Currency_Handler
 *  • [znc_mini_cart]  — removed hardcoded '$', uses ZNC_Currency_Handler
 *  • All price displays now use proper currency symbols per shop
 *
 * @package ZincklesNetCart
 * @since   1.7.0
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Shortcodes {

    /** @var ZNC_Cart_Renderer */
    private $renderer;

    public function __construct() {
        $this->renderer = new ZNC_Cart_Renderer();
    }

    public function init() {
        add_shortcode( 'znc_global_cart',   array( $this, 'sc_global_cart' ) );
        add_shortcode( 'znc_checkout',      array( $this, 'sc_checkout' ) );
        add_shortcode( 'znc_cart_count',    array( $this, 'sc_cart_count' ) );
        add_shortcode( 'znc_cart_total',    array( $this, 'sc_cart_total' ) );
        add_shortcode( 'znc_cart_button',   array( $this, 'sc_cart_button' ) );
        add_shortcode( 'znc_mini_cart',     array( $this, 'sc_mini_cart' ) );
        add_shortcode( 'znc_points_balance', array( $this, 'sc_points_balance' ) );
        add_shortcode( 'znc_shop_list',     array( $this, 'sc_shop_list' ) );
        add_shortcode( 'znc_order_history', array( $this, 'sc_order_history' ) );
    }

    /* ── [znc_global_cart] ── */
    public function sc_global_cart( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p class="woocommerce-info">' . esc_html__( 'Please log in to view your cart.', 'zinckles-net-cart' ) . '</p>';
        }
        return $this->renderer->render_cart( get_current_user_id() );
    }

    /* ── [znc_checkout] ── */
    public function sc_checkout( $atts ) {
        $handler = new ZNC_Checkout_Handler();
        return $handler->render_checkout();
    }

    /* ── [znc_cart_count] ── */
    public function sc_cart_count( $atts ) {
        if ( ! is_user_logged_in() ) return '0';
        $cart = ZNC_Global_Cart::instance();
        return '<span class="znc-cart-count">' . intval( $cart->get_item_count( get_current_user_id() ) ) . '</span>';
    }

    /* ── [znc_cart_total] — FIXED: no longer hardcodes '$' ── */
    public function sc_cart_total( $atts ) {
        if ( ! is_user_logged_in() ) return '';

        $user_id  = get_current_user_id();
        $enriched = $this->renderer->get_enriched_cart( $user_id );

        if ( empty( $enriched ) ) {
            return '<span class="znc-cart-total">0.00</span>';
        }

        // Use proper per-currency totals
        return '<span class="znc-cart-total">'
            . ZNC_Currency_Handler::render_grand_total_html( $enriched )
            . '</span>';
    }

    /* ── [znc_cart_button] ── */
    public function sc_cart_button( $atts ) {
        $atts = shortcode_atts( array(
            'text'  => __( 'Cart', 'zinckles-net-cart' ),
            'class' => 'znc-cart-button',
        ), $atts, 'znc_cart_button' );

        $cart_url = ZNC_Checkout_Host::get_cart_url();
        $count    = 0;
        if ( is_user_logged_in() ) {
            $cart  = ZNC_Global_Cart::instance();
            $count = $cart->get_item_count( get_current_user_id() );
        }

        return '<a href="' . esc_url( $cart_url ) . '" class="' . esc_attr( $atts['class'] ) . '">'
            . esc_html( $atts['text'] )
            . ' <span class="znc-cart-count znc-badge">' . intval( $count ) . '</span>'
            . '</a>';
    }

    /* ── [znc_mini_cart] — FIXED: no longer hardcodes '$' ── */
    public function sc_mini_cart( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<div class="znc-mini-cart znc-mini-cart-empty">'
                . esc_html__( 'Cart is empty', 'zinckles-net-cart' )
                . '</div>';
        }

        $user_id  = get_current_user_id();
        $cart     = ZNC_Global_Cart::instance();
        $count    = $cart->get_item_count( $user_id );

        if ( $count === 0 ) {
            return '<div class="znc-mini-cart znc-mini-cart-empty">'
                . esc_html__( 'Cart is empty', 'zinckles-net-cart' )
                . '</div>';
        }

        $enriched = $this->renderer->get_enriched_cart( $user_id );
        $cart_url = ZNC_Checkout_Host::get_cart_url();

        ob_start();
        ?>
        <div class="znc-mini-cart">
            <div class="znc-mini-cart-header">
                <span class="znc-mini-cart-count"><?php echo intval( $count ); ?></span>
                <?php esc_html_e( 'items', 'zinckles-net-cart' ); ?>
            </div>
            <ul class="znc-mini-cart-items">
                <?php
                $shown = 0;
                foreach ( $enriched as $blog_id => $shop ) :
                    $curr = ZNC_Currency_Handler::get_blog_currency( $blog_id );
                    foreach ( $shop['items'] as $item ) :
                        if ( $shown >= 5 ) break 2; // Show max 5 items in mini cart
                        $shown++;
                ?>
                    <li class="znc-mini-cart-item">
                        <?php if ( $item['image'] ) : ?>
                            <img src="<?php echo esc_url( $item['image'] ); ?>" alt="" width="40" height="40">
                        <?php endif; ?>
                        <span class="znc-mini-item-name"><?php echo esc_html( $item['name'] ); ?></span>
                        <span class="znc-mini-item-qty">&times;<?php echo intval( $item['quantity'] ); ?></span>
                        <span class="znc-mini-item-price">
                            <?php echo esc_html( ZNC_Currency_Handler::format_price( $item['price'] * $item['quantity'], $curr['symbol'] ) ); ?>
                        </span>
                    </li>
                <?php
                    endforeach;
                endforeach;
                if ( $shown < $count ) :
                ?>
                    <li class="znc-mini-cart-more">
                        <?php printf( esc_html__( '+ %d more items', 'zinckles-net-cart' ), $count - $shown ); ?>
                    </li>
                <?php endif; ?>
            </ul>
            <div class="znc-mini-cart-footer">
                <span class="znc-mini-cart-total">
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo ZNC_Currency_Handler::render_grand_total_html( $enriched );
                    ?>
                </span>
                <a href="<?php echo esc_url( $cart_url ); ?>" class="button znc-view-cart-btn">
                    <?php esc_html_e( 'View Cart', 'zinckles-net-cart' ); ?>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ── [znc_points_balance] ── */
    public function sc_points_balance( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p class="woocommerce-info">' . esc_html__( 'Please log in to view your balance.', 'zinckles-net-cart' ) . '</p>';
        }

        $user_id = get_current_user_id();
        $output  = '<div class="znc-points-balance">';

        // MyCred balances
        if ( function_exists( 'mycred_get_types' ) ) {
            $types = mycred_get_types();
            if ( ! empty( $types ) ) {
                $output .= '<h4>' . esc_html__( 'MyCred Points', 'zinckles-net-cart' ) . '</h4>';
                $output .= '<table class="znc-balance-table shop_table"><thead><tr>';
                $output .= '<th>' . esc_html__( 'Point Type', 'zinckles-net-cart' ) . '</th>';
                $output .= '<th>' . esc_html__( 'Balance', 'zinckles-net-cart' ) . '</th>';
                $output .= '</tr></thead><tbody>';
                foreach ( $types as $key => $label ) {
                    $balance = ZNC_MyCred_Engine::get_user_balance( $user_id, $key );
                    $output .= '<tr><td>' . esc_html( $label ) . '</td>';
                    $output .= '<td>' . esc_html( number_format( $balance, 2 ) ) . '</td></tr>';
                }
                $output .= '</tbody></table>';
            }
        }

        // GamiPress balances
        if ( function_exists( 'gamipress_get_user_points' ) && post_type_exists( 'points-type' ) ) {
            $gami_types = get_posts( array(
                'post_type'   => 'points-type',
                'numberposts' => 50,
                'post_status' => 'publish',
            ) );
            if ( ! empty( $gami_types ) ) {
                $output .= '<h4>' . esc_html__( 'GamiPress Points', 'zinckles-net-cart' ) . '</h4>';
                $output .= '<table class="znc-balance-table shop_table"><thead><tr>';
                $output .= '<th>' . esc_html__( 'Point Type', 'zinckles-net-cart' ) . '</th>';
                $output .= '<th>' . esc_html__( 'Balance', 'zinckles-net-cart' ) . '</th>';
                $output .= '</tr></thead><tbody>';
                foreach ( $gami_types as $type ) {
                    $balance = gamipress_get_user_points( $user_id, $type->post_name );
                    $output .= '<tr><td>' . esc_html( $type->post_title ) . '</td>';
                    $output .= '<td>' . esc_html( number_format( $balance ) ) . '</td></tr>';
                }
                $output .= '</tbody></table>';
            }
        }

        $output .= '</div>';
        return $output;
    }

    /* ── [znc_shop_list] ── */
    public function sc_shop_list( $atts ) {
        $settings      = get_site_option( 'znc_network_settings', array() );
        $enrolled_sites = (array) ( $settings['enrolled_sites'] ?? array() );

        if ( empty( $enrolled_sites ) ) {
            return '<p class="woocommerce-info">' . esc_html__( 'No shops enrolled yet.', 'zinckles-net-cart' ) . '</p>';
        }

        $output = '<div class="znc-shop-list"><ul>';

        foreach ( $enrolled_sites as $blog_id ) {
            $details = get_blog_details( $blog_id );
            if ( ! $details ) continue;

            $curr = ZNC_Currency_Handler::get_blog_currency( $blog_id );

            $output .= '<li class="znc-shop-item">';
            $output .= '<a href="' . esc_url( $details->siteurl ) . '">' . esc_html( $details->blogname ) . '</a>';
            $output .= ' <small>(' . esc_html( $curr['label'] ) . ')</small>';
            $output .= '</li>';
        }

        $output .= '</ul></div>';
        return $output;
    }

    /* ── [znc_order_history] ── */
    public function sc_order_history( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p class="woocommerce-info">' . esc_html__( 'Please log in to view your orders.', 'zinckles-net-cart' ) . '</p>';
        }

        $atts = shortcode_atts( array(
            'limit' => 20,
        ), $atts, 'znc_order_history' );

        $user_id = get_current_user_id();
        $orders  = ZNC_Order_Query::get_user_parent_orders( $user_id, (int) $atts['limit'] );

        if ( empty( $orders ) ) {
            return '<p class="woocommerce-info">' . esc_html__( 'No orders found.', 'zinckles-net-cart' ) . '</p>';
        }

        ob_start();
        ?>
        <div class="znc-order-history woocommerce">
            <table class="znc-orders-table shop_table shop_table_responsive">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Order', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Total', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Shops', 'zinckles-net-cart' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $orders as $order ) :
                    $children = ZNC_Order_Query::get_child_orders( $order->get_id() );
                    $child_names = array();
                    foreach ( $children as $bid => $oid ) {
                        $details = get_blog_details( $bid );
                        $child_names[] = $details ? $details->blogname : "Shop #{$bid}";
                    }
                    $symbol = get_woocommerce_currency_symbol( $order->get_currency() );
                ?>
                    <tr>
                        <td>#<?php echo intval( $order->get_id() ); ?></td>
                        <td><?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->date( 'M j, Y' ) : '—' ); ?></td>
                        <td><span class="znc-status znc-status-<?php echo esc_attr( $order->get_status() ); ?>">
                            <?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>
                        </span></td>
                        <td><?php echo esc_html( ZNC_Currency_Handler::format_price( $order->get_total(), $symbol ) ); ?></td>
                        <td><?php echo esc_html( implode( ', ', $child_names ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
}
