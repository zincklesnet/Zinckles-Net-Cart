<?php
defined( 'ABSPATH' ) || exit;
class ZNC_Main_Admin {
    private $store;
    public function __construct( ZNC_Global_Cart_Store $store ) { $this->store = $store; }
    public function init() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
    }
    public function add_menu() {
        add_menu_page( 'Net Cart', 'Net Cart', 'manage_woocommerce', 'znc-settings', array( $this, 'render' ), 'dashicons-cart', 56 );
        add_submenu_page( 'znc-settings', 'Settings', 'Settings', 'manage_woocommerce', 'znc-settings', array( $this, 'render' ) );
        add_submenu_page( 'znc-settings', 'Cart Browser', 'Cart Browser', 'manage_woocommerce', 'znc-cart-browser', array( $this, 'render_cart_browser' ) );
    }
    public function render() {
        $host = new ZNC_Checkout_Host();
        ?>
        <div class="wrap">
            <h1>Zinckles Net Cart — Checkout Host Settings</h1>
            <div class="card" style="max-width:600px;padding:20px;">
                <h2>This is the Checkout Host</h2>
                <p>This site hosts the global cart, checkout, and My Account pages for the entire network.</p>
                <table class="widefat">
                    <tr><td>Site</td><td><strong><?php echo esc_html( get_bloginfo('name') ); ?></strong></td></tr>
                    <tr><td>Enrolled Shops</td><td><?php echo count( $host->get_enrolled_shop_ids() ); ?></td></tr>
                    <tr><td>Cart Page</td><td><?php echo get_option('znc_cart_page_id') ? '<span style="color:green;">Set (ID: ' . get_option('znc_cart_page_id') . ')</span>' : '<span style="color:red;">Not set — create a page with [znc_global_cart]</span>'; ?></td></tr>
                    <tr><td>Checkout Page</td><td><?php echo get_option('znc_checkout_page_id') ? '<span style="color:green;">Set (ID: ' . get_option('znc_checkout_page_id') . ')</span>' : '<span style="color:red;">Not set — create a page with [znc_checkout]</span>'; ?></td></tr>
                </table>
            </div>
        </div>
        <?php
    }
    public function render_cart_browser() {
        global $wpdb;
        $table = $wpdb->prefix . 'znc_global_cart';
        $carts = $wpdb->get_results( "SELECT user_id, COUNT(*) as items, COUNT(DISTINCT blog_id) as shops, SUM(price * quantity) as total FROM {$table} GROUP BY user_id ORDER BY total DESC LIMIT 50" );
        ?>
        <div class="wrap">
            <h1>Net Cart — Cart Browser</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>User</th><th>Items</th><th>Shops</th><th>Total</th></tr></thead>
                <tbody>
                <?php foreach ( $carts as $cart ) :
                    $user = get_userdata( $cart->user_id );
                ?>
                    <tr>
                        <td><?php echo $user ? esc_html( $user->display_name . ' (' . $user->user_email . ')' ) : 'User #' . $cart->user_id; ?></td>
                        <td><?php echo $cart->items; ?></td>
                        <td><?php echo $cart->shops; ?></td>
                        <td><?php echo number_format( $cart->total, 2 ); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ( empty( $carts ) ) : ?>
                    <tr><td colspan="4">No active carts.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
