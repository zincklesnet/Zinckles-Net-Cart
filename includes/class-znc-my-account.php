<?php
defined( 'ABSPATH' ) || exit;
class ZNC_My_Account {
    private $host;
    public function __construct( ZNC_Checkout_Host $host ) { $this->host = $host; }
    public function init() {
        add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ), 20 );
        add_action( 'init', array( $this, 'add_endpoint' ) );
        add_action( 'woocommerce_account_net-cart-orders_endpoint', array( $this, 'render_orders' ) );
    }
    public function add_endpoint() {
        add_rewrite_endpoint( 'net-cart-orders', EP_ROOT | EP_PAGES );
    }
    public function add_menu_item( $items ) {
        $new = array();
        foreach ( $items as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'orders' ) {
                $new['net-cart-orders'] = __( 'Net Cart Orders', 'zinckles-net-cart' );
            }
        }
        return $new;
    }
    public function render_orders() {
        $user_id = get_current_user_id();
        $query = new ZNC_Order_Query();
        $orders = $query->get_user_orders( $user_id );
        if ( empty( $orders ) ) {
            echo '<div class="woocommerce-info">' . esc_html__( 'No Net Cart orders yet.', 'zinckles-net-cart' ) . '</div>';
            return;
        }
        echo '<h2>' . esc_html__( 'Net Cart Orders', 'zinckles-net-cart' ) . '</h2>';
        echo '<table class="woocommerce-orders-table shop_table"><thead><tr>';
        echo '<th>Order</th><th>Date</th><th>Status</th><th>Total</th><th>Shops</th></tr></thead><tbody>';
        foreach ( $orders as $order ) {
            $wc_order = wc_get_order( $order->parent_order_id );
            if ( ! $wc_order ) continue;
            echo '<tr>';
            echo '<td><a href="' . esc_url( $wc_order->get_view_order_url() ) . '">#' . $wc_order->get_order_number() . '</a></td>';
            echo '<td>' . esc_html( $wc_order->get_date_created()->date_i18n( get_option( 'date_format' ) ) ) . '</td>';
            echo '<td>' . esc_html( wc_get_order_status_name( $wc_order->get_status() ) ) . '</td>';
            echo '<td>' . wp_kses_post( $wc_order->get_formatted_order_total() ) . '</td>';
            echo '<td>' . esc_html( $query->get_shop_count( $order->parent_order_id ) ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
}
