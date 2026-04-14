<?php
/**
 * Add-to-Cart Interceptor.
 *
 * Runs on enrolled subsites. Hooks into WooCommerce add-to-cart events and
 * immediately pushes items to the global cart on the checkout host via
 * switch_to_blog() + direct DB writes (reliable for same-server multisite).
 *
 * @package ZincklesNetCart
 * @since   1.3.0
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Add_To_Cart_Interceptor {

    private $host;
    private $processing = false;

    public function __construct( ZNC_Checkout_Host $host ) {
        $this->host = $host;
    }

    public function init() {
        if ( $this->host->is_current_site_host() ) return;

        add_action( 'woocommerce_add_to_cart',                        array( $this, 'on_add_to_cart' ), 10, 6 );
        add_action( 'woocommerce_cart_item_removed',                  array( $this, 'on_cart_changed' ), 10 );
        add_action( 'woocommerce_after_cart_item_quantity_update',     array( $this, 'on_cart_changed' ), 10 );
        add_action( 'woocommerce_cart_emptied',                       array( $this, 'on_cart_emptied' ), 10 );
    }

    /** Fires when a product is added to the WC cart on a subsite. */
    public function on_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
        if ( $this->processing || ! is_user_logged_in() ) return;
        if ( ! $this->is_enrolled() ) return;

        $this->processing = true;

        $product = wc_get_product( $variation_id ? $variation_id : $product_id );
        if ( ! $product ) { $this->processing = false; return; }

        $item = array(
            'product_id'    => $product_id,
            'variation_id'  => $variation_id ?: 0,
            'quantity'      => $quantity,
            'product_name'  => $product->get_name(),
            'price'         => (float) $product->get_price(),
            'line_total'    => (float) $product->get_price() * $quantity,
            'sku'           => $product->get_sku(),
            'image_url'     => wp_get_attachment_url( $product->get_image_id() ),
            'permalink'     => $product->get_permalink(),
            'in_stock'      => $product->is_in_stock() ? 1 : 0,
            'stock_qty'     => $product->managing_stock() ? $product->get_stock_quantity() : null,
            'variation_data' => maybe_serialize( $variation ?: array() ),
            'meta_data'     => maybe_serialize( array() ),
        );

        $this->push_item( $item );
        $this->processing = false;
    }

    /** Fires on removal or qty change — full resync on shutdown. */
    public function on_cart_changed() {
        if ( $this->processing || ! is_user_logged_in() ) return;
        if ( ! $this->is_enrolled() ) return;
        if ( ! has_action( 'shutdown', array( $this, 'full_sync' ) ) ) {
            add_action( 'shutdown', array( $this, 'full_sync' ) );
        }
    }

    /** Fires when cart is emptied — clear this subsite from global cart. */
    public function on_cart_emptied() {
        if ( ! is_user_logged_in() ) return;
        $user_id = get_current_user_id();
        $blog_id = get_current_blog_id();
        $host_id = $this->host->get_host_id();

        switch_to_blog( $host_id );
        global $wpdb;
        $table = $wpdb->prefix . 'znc_global_cart';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table ) {
            $wpdb->delete( $table, array( 'user_id' => $user_id, 'blog_id' => $blog_id ) );
        }
        restore_current_blog();
    }

    /** Push a single item via switch_to_blog + upsert. */
    private function push_item( $item ) {
        $user_id  = get_current_user_id();
        $blog_id  = get_current_blog_id();
        $host_id  = $this->host->get_host_id();
        $currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
        $shop     = $this->get_shop_info();

        switch_to_blog( $host_id );
        global $wpdb;
        $table = $wpdb->prefix . 'znc_global_cart';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            restore_current_blog(); return;
        }

        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, quantity FROM {$table} WHERE user_id=%d AND blog_id=%d AND product_id=%d AND variation_id=%d",
            $user_id, $blog_id, $item['product_id'], $item['variation_id']
        ) );

        $now = current_time( 'mysql' );

        if ( $existing ) {
            $wpdb->update( $table, array(
                'quantity'   => $item['quantity'],
                'price'      => $item['price'],
                'line_total' => $item['price'] * $item['quantity'],
                'in_stock'   => $item['in_stock'],
                'stock_qty'  => $item['stock_qty'],
                'updated_at' => $now,
            ), array( 'id' => $existing->id ) );
        } else {
            $wpdb->insert( $table, array(
                'user_id' => $user_id, 'blog_id' => $blog_id,
                'product_id' => $item['product_id'], 'variation_id' => $item['variation_id'],
                'quantity' => $item['quantity'], 'product_name' => $item['product_name'],
                'price' => $item['price'], 'line_total' => $item['line_total'],
                'currency' => $currency, 'sku' => $item['sku'],
                'image_url' => $item['image_url'], 'permalink' => $item['permalink'],
                'in_stock' => $item['in_stock'], 'stock_qty' => $item['stock_qty'],
                'variation_data' => $item['variation_data'], 'meta_data' => $item['meta_data'],
                'shop_name' => $shop['name'], 'shop_url' => $shop['url'],
                'created_at' => $now, 'updated_at' => $now,
            ) );
        }
        restore_current_blog();

        do_action( 'znc_item_pushed_to_global_cart', $user_id, $blog_id, $item );
    }

    /** Full cart resync (called on shutdown after removals/qty changes). */
    public function full_sync() {
        if ( ! is_user_logged_in() || ! function_exists( 'WC' ) ) return;
        $this->processing = true;

        $user_id  = get_current_user_id();
        $blog_id  = get_current_blog_id();
        $host_id  = $this->host->get_host_id();
        $currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
        $shop     = $this->get_shop_info();
        $wc_cart  = WC()->cart;

        if ( ! $wc_cart ) { $this->processing = false; return; }

        $contents = $wc_cart->get_cart();

        switch_to_blog( $host_id );
        global $wpdb;
        $table = $wpdb->prefix . 'znc_global_cart';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            restore_current_blog(); $this->processing = false; return;
        }

        // Clear all from this subsite.
        $wpdb->delete( $table, array( 'user_id' => $user_id, 'blog_id' => $blog_id ) );

        // Re-insert current cart.
        $now = current_time( 'mysql' );
        foreach ( $contents as $item ) {
            $product = $item['data'];
            if ( ! $product ) continue;
            $wpdb->insert( $table, array(
                'user_id' => $user_id, 'blog_id' => $blog_id,
                'product_id' => $item['product_id'],
                'variation_id' => $item['variation_id'] ?? 0,
                'quantity' => $item['quantity'],
                'product_name' => $product->get_name(),
                'price' => (float) $product->get_price(),
                'line_total' => (float) $product->get_price() * $item['quantity'],
                'currency' => $currency,
                'sku' => $product->get_sku(),
                'image_url' => wp_get_attachment_url( $product->get_image_id() ),
                'permalink' => $product->get_permalink(),
                'in_stock' => $product->is_in_stock() ? 1 : 0,
                'stock_qty' => $product->managing_stock() ? $product->get_stock_quantity() : null,
                'variation_data' => maybe_serialize( $item['variation'] ?? array() ),
                'meta_data' => maybe_serialize( array() ),
                'shop_name' => $shop['name'], 'shop_url' => $shop['url'],
                'created_at' => $now, 'updated_at' => $now,
            ) );
        }
        restore_current_blog();
        $this->processing = false;
        do_action( 'znc_cart_full_sync_completed', $user_id, $blog_id );
    }

    private function get_shop_info() {
        $s = get_option( 'znc_subsite_settings', array() );
        return array(
            'name' => $s['display_name'] ?? get_bloginfo( 'name' ),
            'url'  => home_url(),
        );
    }

    private function is_enrolled() {
        $settings = get_site_option( 'znc_network_settings', array() );
        $blog_id  = get_current_blog_id();
        $blocked  = (array) ( $settings['blocked_sites'] ?? array() );
        if ( in_array( $blog_id, $blocked, true ) ) return false;
        $mode = $settings['enrollment_mode'] ?? 'opt-in';
        if ( $mode === 'opt-out' ) return true;
        $enrolled = (array) ( $settings['enrolled_sites'] ?? array() );
        return in_array( $blog_id, $enrolled, true );
    }
}
