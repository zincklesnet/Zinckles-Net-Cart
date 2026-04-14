<?php
defined( 'ABSPATH' ) || exit;

class ZNC_My_Account {

    public static function init() {
        add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'add_menu_item' ) );
        add_action( 'woocommerce_account_net-cart-orders_endpoint', array( __CLASS__, 'render_orders' ) );
        add_action( 'init', array( __CLASS__, 'add_endpoint' ) );
        add_filter( 'woocommerce_account_menu_item_classes', array( __CLASS__, 'menu_classes' ), 10, 2 );
    }

    public static function add_endpoint() {
        add_rewrite_endpoint( 'net-cart-orders', EP_ROOT | EP_PAGES );
    }

    public static function add_menu_item( $items ) {
        $new = array();
        foreach ( $items as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'orders' ) {
                $new['net-cart-orders'] = '🛒 Net Cart Orders';
            }
        }
        return $new;
    }

    public static function menu_classes( $classes, $endpoint ) {
        if ( $endpoint === 'net-cart-orders' ) {
            $classes[] = 'znc-net-cart-orders-tab';
        }
        return $classes;
    }

    public static function render_orders() {
        $user_id = get_current_user_id();
        $query   = ZNC_Order_Query::get_net_cart_orders( $user_id );

        echo '<h2>Net Cart Orders</h2>';

        if ( empty( $query ) ) {
            echo '<p>No Net Cart orders yet. Start shopping across our network!</p>';
            return;
        }

        echo '<table class="woocommerce-orders-table shop_table shop_table_responsive" style="width:100%">';
        echo '<thead><tr><th>Order</th><th>Date</th><th>Shops</th><th>Total</th><th>Status</th><th></th></tr></thead><tbody>';

        foreach ( $query as $order_data ) {
            $order = wc_get_order( $order_data['order_id'] );
            if ( ! $order ) continue;

            $shops     = $order_data['shops'] ?? array();
            $shop_list = array();
            foreach ( $shops as $s ) {
                $shop_list[] = esc_html( $s['name'] );
            }

            $mycred = $order->get_meta( '_znc_mycred_deductions' );
            $mycred_str = '';
            if ( ! empty( $mycred ) && is_array( $mycred ) ) {
                $parts = array();
                foreach ( $mycred as $slug => $amt ) {
                    $parts[] = $amt . ' ' . $slug;
                }
                $mycred_str = ' + ' . implode( ', ', $parts );
            }

            echo '<tr>';
            echo '<td>#' . $order->get_id() . '</td>';
            echo '<td>' . $order->get_date_created()->date_i18n( 'M j, Y' ) . '</td>';
            echo '<td>' . implode( ', ', $shop_list ) . '</td>';
            echo '<td>' . $order->get_formatted_order_total() . $mycred_str . '</td>';
            echo '<td>' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</td>';
            echo '<td><a href="' . esc_url( $order->get_view_order_url() ) . '">View</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
}
