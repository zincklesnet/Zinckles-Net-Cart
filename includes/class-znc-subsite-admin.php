<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Subsite_Admin {
    private $host;
    public function __construct( ZNC_Checkout_Host $host ) { $this->host = $host; }

    public function init() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
    }

    public function add_menu() {
        add_menu_page( 'Net Cart', 'Net Cart', 'manage_woocommerce', 'znc-subsite', array( $this, 'render' ), 'dashicons-cart', 56 );
    }

    public function render() {
        $host_info = $this->host->get_host_info();
        $blog_id   = get_current_blog_id();
        $enrolled  = $this->host->is_enrolled( $blog_id );
        $currency  = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'N/A';
        $products  = function_exists( 'wc_get_products' ) ? count( wc_get_products( array( 'limit' => -1, 'return' => 'ids', 'status' => 'publish' ) ) ) : 0;
        $mycred_types = array();
        if ( function_exists( 'mycred_get_types' ) ) { $mycred_types = mycred_get_types(); }
        ?>
        <div class="wrap">
            <h1>Zinckles Net Cart</h1>
            <div class="card" style="max-width:600px;padding:20px;">
                <h2>Subsite Status</h2>
                <table class="widefat">
                    <tr><td>Enrollment</td><td><?php echo $enrolled ? '<span style="color:green;font-weight:bold;">✅ Enrolled</span>' : '<span style="color:#999;">Not Enrolled</span>'; ?></td></tr>
                    <tr><td>Checkout Host</td><td><a href="<?php echo esc_url( $host_info['url'] ); ?>" target="_blank"><?php echo esc_html( $host_info['name'] ); ?></a></td></tr>
                    <tr><td>Currency</td><td><?php echo esc_html( $currency ); ?></td></tr>
                    <tr><td>Published Products</td><td><?php echo $products; ?></td></tr>
                    <tr><td>WooCommerce</td><td><?php echo class_exists('WooCommerce') ? '<span style="color:green;">Active</span>' : '<span style="color:red;">Missing</span>'; ?></td></tr>
                    <tr><td>MyCred Types</td><td><?php echo ! empty( $mycred_types ) ? esc_html( implode( ', ', $mycred_types ) ) : 'N/A'; ?></td></tr>
                </table>
                <?php if ( $enrolled ) : ?>
                    <p style="margin-top:16px;"><a href="<?php echo esc_url( $this->host->get_cart_url() ); ?>" class="button button-primary">View Global Cart</a></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
