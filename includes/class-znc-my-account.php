<?php
/**
 * My Account — v1.4.1
 * Adds a "Net Cart Orders" tab to WooCommerce My Account on the main site.
 * Shows completed orders from all enrolled subsites with shop name, currency/point type.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_My_Account {

    public function init() {
        // Only add the custom tab on the checkout host
        $settings = get_site_option( 'znc_network_settings', array() );
        $host_id  = isset( $settings['checkout_host_id'] ) ? (int) $settings['checkout_host_id'] : get_main_site_id();
        if ( get_current_blog_id() !== $host_id ) return;

        add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ), 10, 1 );
        add_action( 'init', array( $this, 'add_endpoint' ) );
        add_action( 'woocommerce_account_net-cart-orders_endpoint', array( $this, 'render_page' ) );
    }

    public function add_endpoint() {
        add_rewrite_endpoint( 'net-cart-orders', EP_ROOT | EP_PAGES );
    }

    public function add_menu_item( $items ) {
        $new_items = array();
        foreach ( $items as $key => $label ) {
            $new_items[ $key ] = $label;
            if ( 'orders' === $key ) {
                $new_items['net-cart-orders'] = __( 'Net Cart Orders', 'zinckles-net-cart' );
            }
        }
        return $new_items;
    }

    public function render_page() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            echo '<p>' . esc_html__( 'Please log in to view your orders.', 'zinckles-net-cart' ) . '</p>';
            return;
        }

        $orders = ZNC_Order_Query::get_network_orders( $user_id, array(
            'status' => array( 'completed', 'processing', 'on-hold', 'refunded' ),
            'limit'  => 50,
        ) );

        if ( empty( $orders ) ) {
            echo '<div class="znc-no-orders">';
            echo '<p>' . esc_html__( 'No Net Cart orders found across the network.', 'zinckles-net-cart' ) . '</p>';
            echo '</div>';
            return;
        }

        echo '<div class="znc-my-account-orders">';
        echo '<h3>' . esc_html__( 'Orders Across All Shops', 'zinckles-net-cart' ) . '</h3>';
        echo '<table class="woocommerce-orders-table shop_table shop_table_responsive znc-orders-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Order', 'zinckles-net-cart' ) . '</th>';
        echo '<th>' . esc_html__( 'Shop', 'zinckles-net-cart' ) . '</th>';
        echo '<th>' . esc_html__( 'Date', 'zinckles-net-cart' ) . '</th>';
        echo '<th>' . esc_html__( 'Products', 'zinckles-net-cart' ) . '</th>';
        echo '<th>' . esc_html__( 'Total', 'zinckles-net-cart' ) . '</th>';
        echo '<th>' . esc_html__( 'Payment', 'zinckles-net-cart' ) . '</th>';
        echo '<th>' . esc_html__( 'Status', 'zinckles-net-cart' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $orders as $o ) {
            $status_class = 'znc-status-' . esc_attr( $o['status'] );

            // Build payment label
            if ( $o['payment_type'] === 'points' && ! empty( $o['point_type'] ) ) {
                $payment_label = $this->get_point_type_label( $o['point_type'] );
            } else {
                $payment_label = $o['currency'] ?: 'USD';
            }

            // Products list
            $product_names = array();
            foreach ( $o['items'] as $item ) {
                $product_names[] = esc_html( $item['name'] ) . ' × ' . (int) $item['quantity'];
            }

            echo '<tr>';
            echo '<td data-title="' . esc_attr__( 'Order', 'zinckles-net-cart' ) . '">#' . esc_html( $o['order_id'] ) . '</td>';
            echo '<td data-title="' . esc_attr__( 'Shop', 'zinckles-net-cart' ) . '">';
            echo '<a href="' . esc_url( $o['shop_url'] ) . '" target="_blank">' . esc_html( $o['shop_name'] ) . '</a>';
            if ( $o['is_global'] ) echo ' <span class="znc-badge znc-badge-info" title="Global Cart Order">🌐</span>';
            echo '</td>';
            echo '<td data-title="' . esc_attr__( 'Date', 'zinckles-net-cart' ) . '">' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $o['date'] ) ) ) . '</td>';
            echo '<td data-title="' . esc_attr__( 'Products', 'zinckles-net-cart' ) . '" class="znc-products-cell">' . implode( '<br>', $product_names ) . '</td>';
            echo '<td data-title="' . esc_attr__( 'Total', 'zinckles-net-cart' ) . '">' . esc_html( ZNC_Currency_Handler::format( $o['total'], $o['currency'] ) ) . '</td>';
            echo '<td data-title="' . esc_attr__( 'Payment', 'zinckles-net-cart' ) . '"><span class="znc-payment-badge">' . esc_html( $payment_label ) . '</span></td>';
            echo '<td data-title="' . esc_attr__( 'Status', 'zinckles-net-cart' ) . '"><span class="znc-badge ' . $status_class . '">' . esc_html( ucfirst( $o['status'] ) ) . '</span></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    private function get_point_type_label( $slug ) {
        $labels = array(
            'mycred_default' => 'ZCreds',
            'MYC'            => 'ZCreds',
            'plzcreds'       => 'Platinum ZCreds',
            'gzcreds'        => 'Gold ZCreds',
            'spzcreds'       => 'Special ZCreds',
            'grzcreds'       => 'Group ZCreds',
        );
        return isset( $labels[ $slug ] ) ? $labels[ $slug ] : ucfirst( str_replace( '_', ' ', $slug ) );
    }
}
