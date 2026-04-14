<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Subsite_Admin {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
    }

    public static function add_menu() {
        add_menu_page( 'Net Cart', 'Net Cart', 'manage_options', 'znc-subsite', array( __CLASS__, 'render_dashboard' ), 'dashicons-cart', 56 );
    }

    public static function render_dashboard() {
        $host     = new ZNC_Checkout_Host();
        $blog_id  = get_current_blog_id();
        $settings = get_site_option( 'znc_network_settings', array() );
        $enrolled = (array) ( $settings['enrolled_sites'] ?? array() );
        $is_enrolled = in_array( $blog_id, $enrolled, true );

        echo '<div class="wrap">';
        echo '<h1>Zinckles Net Cart — Subsite Dashboard</h1>';

        if ( $is_enrolled ) {
            echo '<div class="notice notice-success"><p><strong>✅ This site is enrolled in Net Cart.</strong></p></div>';
            echo '<table class="form-table">';
            echo '<tr><th>Checkout Host</th><td>' . esc_html( $host->get_host_info()['name'] ) . ' (' . esc_url( $host->get_host_url() ) . ')</td></tr>';
            echo '<tr><th>Global Cart</th><td><a href="' . esc_url( $host->get_cart_url() ) . '" target="_blank">View Global Cart →</a></td></tr>';
            echo '<tr><th>Currency</th><td>' . esc_html( get_woocommerce_currency() ) . '</td></tr>';
            echo '<tr><th>Products</th><td>' . wp_count_posts( 'product' )->publish . ' published</td></tr>';

            if ( function_exists( 'mycred_get_types' ) ) {
                $types = mycred_get_types();
                echo '<tr><th>MyCred Types</th><td>' . esc_html( implode( ', ', $types ) ) . '</td></tr>';
            }

            echo '</table>';

            echo '<h2>How It Works</h2>';
            echo '<ol>';
            echo '<li>Customers add products to cart on this shop — items are pushed to the <strong>Global Net Cart</strong> automatically.</li>';
            echo '<li>Cart, Checkout, and My Account pages redirect to the checkout host site.</li>';
            echo '<li>Orders are split: a parent order on the host + a child order here for fulfillment.</li>';
            echo '</ol>';
        } else {
            echo '<div class="notice notice-warning"><p><strong>⚠️ This site is NOT enrolled in Net Cart.</strong> Contact the network administrator to enroll.</p></div>';
        }

        echo '</div>';
    }
}
