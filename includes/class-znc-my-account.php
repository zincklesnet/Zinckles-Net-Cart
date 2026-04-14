<?php
/**
 * My Account Integration (checkout host only).
 *
 * Adds "Net Cart Orders" tab to WooCommerce My Account showing
 * cross-site orders with per-shop breakdowns, currency & ZCred details.
 *
 * @package ZincklesNetCart
 * @since   1.3.0
 */
defined( 'ABSPATH' ) || exit;

class ZNC_My_Account {

    private $host;

    public function __construct( ZNC_Checkout_Host $host ) {
        $this->host = $host;
    }

    public function init() {
        if ( ! $this->host->is_current_site_host() ) return;

        add_action( 'init', array( $this, 'register_endpoints' ) );
        add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ), 20 );
        add_action( 'woocommerce_account_net-cart-orders_endpoint', array( $this, 'render_orders_tab' ) );
        add_action( 'woocommerce_account_dashboard', array( $this, 'render_dashboard_widget' ), 20 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
    }

    public function register_endpoints() {
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

    public function render_orders_tab() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            echo '<p>' . esc_html__( 'Please log in.', 'zinckles-net-cart' ) . '</p>';
            return;
        }

        $orders = $this->get_net_cart_orders( $user_id );

        echo '<div class="znc-my-orders">';
        echo '<h3>' . esc_html__( 'Net Cart Orders', 'zinckles-net-cart' ) . '</h3>';
        echo '<p class="znc-orders-desc">' . esc_html__( 'Orders placed through the unified Net Cart across all shops.', 'zinckles-net-cart' ) . '</p>';

        if ( empty( $orders ) ) {
            echo '<div class="znc-no-orders"><span>&#x1F4E6;</span>';
            echo '<p>' . esc_html__( 'No Net Cart orders yet.', 'zinckles-net-cart' ) . '</p></div></div>';
            return;
        }

        // Stats
        $total_spent = $total_zcreds = 0;
        $shops = array();
        foreach ( $orders as $o ) {
            $total_spent  += floatval( $o->get_total() );
            $total_zcreds += floatval( $o->get_meta( '_znc_zcreds_deducted', true ) );
            $cm = $o->get_meta( '_znc_child_orders', true );
            if ( is_array( $cm ) ) foreach ( $cm as $c ) $shops[ $c['blog_id'] ?? 0 ] = true;
        }

        echo '<div class="znc-order-stats">';
        echo '<div class="znc-stat"><span class="znc-stat-val">' . count($orders) . '</span><span class="znc-stat-lbl">Orders</span></div>';
        echo '<div class="znc-stat"><span class="znc-stat-val">' . wc_price($total_spent) . '</span><span class="znc-stat-lbl">Total Spent</span></div>';
        echo '<div class="znc-stat"><span class="znc-stat-val">' . count($shops) . '</span><span class="znc-stat-lbl">Shops</span></div>';
        if ( $total_zcreds > 0 ) {
            echo '<div class="znc-stat"><span class="znc-stat-val">&#x26A1; ' . number_format($total_zcreds) . '</span><span class="znc-stat-lbl">ZCreds Used</span></div>';
        }
        echo '</div>';

        // Orders list
        echo '<div class="znc-orders-list">';
        foreach ( $orders as $order ) {
            $oid       = $order->get_id();
            $date      = $order->get_date_created()->date_i18n( get_option('date_format') );
            $status    = $order->get_status();
            $total     = $order->get_total();
            $curr      = $order->get_currency();
            $child_map = $order->get_meta( '_znc_child_orders', true ) ?: array();
            $zc_used   = floatval( $order->get_meta( '_znc_zcreds_deducted', true ) );
            $zc_earned = floatval( $order->get_meta( '_znc_zcreds_earned', true ) );
            $is_mixed  = $order->get_meta( '_znc_mixed_currency', true );
            $breakdown = $order->get_meta( '_znc_currency_breakdown', true ) ?: array();
            $payment   = $order->get_payment_method_title();

            echo '<div class="znc-order-card">';

            // Header
            echo '<div class="znc-order-header">';
            echo '<div><strong>#' . esc_html($oid) . '</strong> <span class="znc-date">' . esc_html($date) . '</span></div>';
            echo '<div class="znc-badges">';
            echo '<span class="znc-status znc-status-' . esc_attr($status) . '">' . esc_html( wc_get_order_status_name($status) ) . '</span>';
            if ( $is_mixed ) echo '<span class="znc-badge-mixed">&#x1F310; Mixed Currency</span>';
            echo '</div></div>';

            // Shop badges
            if ( ! empty( $child_map ) ) {
                echo '<div class="znc-shop-badges">';
                foreach ( $child_map as $child ) {
                    $sn = $child['shop_name'] ?? 'Shop #' . ($child['blog_id'] ?? '?');
                    $sc = $child['currency'] ?? $curr;
                    $ss = $child['status'] ?? $status;
                    echo '<div class="znc-shop-badge">';
                    echo '<span class="znc-shop-init">' . esc_html(mb_substr($sn,0,1)) . '</span>';
                    echo '<span>' . esc_html($sn) . '</span>';
                    echo '<span class="znc-shop-curr">' . esc_html($sc) . '</span>';
                    echo '<span class="znc-status znc-status-' . esc_attr($ss) . '">' . esc_html(ucfirst($ss)) . '</span>';
                    echo '</div>';
                }
                echo '</div>';
            }

            // Payment line
            echo '<div class="znc-order-payment">';
            echo '<span class="znc-total">' . wc_price($total, array('currency'=>$curr)) . '</span>';
            if ( $zc_used > 0 ) echo '<span class="znc-zc-chip znc-zc-used">&#x26A1; -' . number_format($zc_used) . ' ZCreds</span>';
            if ( $zc_earned > 0 ) echo '<span class="znc-zc-chip znc-zc-earned">&#x26A1; +' . number_format($zc_earned) . ' earned</span>';
            if ( $payment ) echo '<span class="znc-pm">' . esc_html($payment) . '</span>';
            echo '</div>';

            // Currency breakdown
            if ( count($breakdown) > 1 ) {
                echo '<div class="znc-curr-break">';
                foreach ( $breakdown as $cc => $cd ) {
                    $amt = is_array($cd) ? ($cd['total'] ?? 0) : $cd;
                    echo '<span class="znc-curr-chip">' . wc_price($amt,array('currency'=>$cc)) . ' ' . esc_html($cc) . '</span>';
                }
                echo '</div>';
            }

            echo '<div class="znc-order-actions"><a href="' . esc_url(wc_get_endpoint_url('view-order',$oid,wc_get_page_permalink('myaccount'))) . '">' . esc_html__('View Details','zinckles-net-cart') . ' &rarr;</a></div>';
            echo '</div>';
        }
        echo '</div></div>';
    }

    public function render_dashboard_widget() {
        $orders = $this->get_net_cart_orders( get_current_user_id(), 3 );
        if ( empty( $orders ) ) return;

        echo '<div class="znc-dashboard-widget"><h3>&#x1F6D2; Recent Net Cart Orders</h3><ul>';
        foreach ( $orders as $o ) {
            $url = wc_get_endpoint_url('view-order',$o->get_id(),wc_get_page_permalink('myaccount'));
            echo '<li><a href="' . esc_url($url) . '"><strong>#' . $o->get_id() . '</strong> ';
            echo '<span>' . $o->get_date_created()->date_i18n('M j') . '</span> ';
            echo wc_price($o->get_total(),array('currency'=>$o->get_currency()));
            echo '</a></li>';
        }
        echo '</ul>';
        echo '<a href="' . esc_url(wc_get_endpoint_url('net-cart-orders','',wc_get_page_permalink('myaccount'))) . '">View All &rarr;</a>';
        echo '</div>';
    }

    private function get_net_cart_orders( $user_id, $limit = 20 ) {
        if ( ! function_exists( 'wc_get_orders' ) ) return array();
        return wc_get_orders( array(
            'customer_id' => $user_id,
            'meta_key'    => '_znc_is_parent_order',
            'meta_value'  => '1',
            'limit'       => $limit,
            'orderby'     => 'date',
            'order'       => 'DESC',
            'status'      => array('wc-completed','wc-processing','wc-on-hold','wc-refunded','wc-pending'),
        ) );
    }

    public function enqueue_styles() {
        if ( ! function_exists('is_account_page') || ! is_account_page() ) return;
        wp_enqueue_style( 'znc-my-account', ZNC_PLUGIN_URL . 'assets/css/znc-my-account.css', array(), ZNC_VERSION );
    }
}
