<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Main_Admin {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
    }

    public static function add_menu() {
        add_menu_page( 'Net Cart', 'Net Cart', 'manage_options', 'znc-settings', array( __CLASS__, 'render_settings' ), 'dashicons-cart', 56 );
        add_submenu_page( 'znc-settings', 'Settings',     'Settings',     'manage_options', 'znc-settings',      array( __CLASS__, 'render_settings' ) );
        add_submenu_page( 'znc-settings', 'Cart Browser',  'Cart Browser', 'manage_options', 'znc-cart-browser',  array( __CLASS__, 'render_cart_browser' ) );
        add_submenu_page( 'znc-settings', 'Order Map',     'Order Map',    'manage_options', 'znc-order-map',     array( __CLASS__, 'render_order_map' ) );
    }

    public static function render_settings() {
        echo '<div class="wrap"><h1>Zinckles Net Cart — Checkout Host Settings</h1>';
        echo '<p>This site is the designated checkout host. Global cart, checkout, and My Account are served from here.</p>';
        $host = new ZNC_Checkout_Host();
        echo '<table class="form-table"><tr><th>Cart Page</th><td>' . esc_url( $host->get_cart_url() ) . '</td></tr>';
        echo '<tr><th>Checkout Page</th><td>' . esc_url( $host->get_checkout_url() ) . '</td></tr>';
        echo '<tr><th>My Account</th><td>' . esc_url( $host->get_account_url() ) . '</td></tr></table>';
        echo '</div>';
    }

    public static function render_cart_browser() {
        global $wpdb;
        $table = $wpdb->prefix . 'znc_global_cart';
        $carts = $wpdb->get_results( "SELECT user_id, COUNT(*) as items, SUM(quantity) as total_qty FROM {$table} WHERE expires_at > NOW() GROUP BY user_id ORDER BY total_qty DESC LIMIT 50" );

        echo '<div class="wrap"><h1>Net Cart — Active Carts</h1>';
        echo '<table class="widefat striped"><thead><tr><th>User</th><th>Items</th><th>Total Qty</th></tr></thead><tbody>';
        foreach ( $carts as $cart ) {
            $user = get_userdata( $cart->user_id );
            echo '<tr><td>' . esc_html( $user ? $user->display_name : '#' . $cart->user_id ) . '</td>';
            echo '<td>' . $cart->items . '</td><td>' . $cart->total_qty . '</td></tr>';
        }
        if ( empty( $carts ) ) echo '<tr><td colspan="3">No active carts.</td></tr>';
        echo '</tbody></table></div>';
    }

    public static function render_order_map() {
        global $wpdb;
        $table = $wpdb->prefix . 'znc_order_map';
        $maps  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 50" );

        echo '<div class="wrap"><h1>Net Cart — Order Map</h1>';
        echo '<table class="widefat striped"><thead><tr><th>Parent Order</th><th>Child Order</th><th>Child Site</th><th>Status</th><th>Created</th></tr></thead><tbody>';
        foreach ( $maps as $m ) {
            $site = get_blog_details( $m->child_blog_id );
            echo '<tr><td>#' . $m->parent_order_id . '</td><td>#' . $m->child_order_id . '</td>';
            echo '<td>' . esc_html( $site ? $site->blogname : 'Site ' . $m->child_blog_id ) . '</td>';
            echo '<td>' . esc_html( $m->status ) . '</td><td>' . esc_html( $m->created_at ) . '</td></tr>';
        }
        if ( empty( $maps ) ) echo '<tr><td colspan="5">No orders yet.</td></tr>';
        echo '</tbody></table></div>';
    }
}
