<?php
/**
 * Cart Snapshot — captures WooCommerce add-to-cart on enrolled subsites
 * and writes directly to the global cart table on the checkout host.
 *
 * v1.3.2 FIX: Uses configurable checkout host (not hardcoded get_main_site_id).
 * Only switches to host blog for the single INSERT — no WC reinitialization.
 *
 * @package ZincklesNetCart
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Cart_Snapshot {

    /** @var ZNC_Checkout_Host */
    private $host;

    /** @var bool recursion guard */
    private static $pushing = false;

    public function __construct( ZNC_Checkout_Host $host ) {
        $this->host = $host;
    }

    public function init() {
        add_action( 'woocommerce_add_to_cart',                    array( $this, 'on_add_to_cart' ), 20, 6 );
        add_action( 'woocommerce_cart_item_removed',              array( $this, 'on_remove_from_cart' ), 20, 2 );
        add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'on_qty_update' ), 20, 4 );
        add_filter( 'woocommerce_add_to_cart_fragments',          array( $this, 'inject_cart_count_fragment' ) );
    }

    /**
     * Fires when a product is added to cart on any enrolled subsite.
     */
    public function on_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
        if ( self::$pushing ) return;
        if ( ! is_user_logged_in() ) return;

        $product = wc_get_product( $variation_id ? $variation_id : $product_id );
        if ( ! $product ) return;

        $source_blog_id = get_current_blog_id();
        $user_id        = get_current_user_id();

        $item_data = array(
            'user_id'        => $user_id,
            'blog_id'        => $source_blog_id,
            'product_id'     => $product_id,
            'variation_id'   => $variation_id ? $variation_id : 0,
            'quantity'       => $quantity,
            'product_name'   => $product->get_name(),
            'price'          => (float) $product->get_price(),
            'currency'       => get_woocommerce_currency(),
            'image_url'      => wp_get_attachment_url( $product->get_image_id() ) ?: '',
            'sku'            => $product->get_sku(),
            'permalink'      => get_permalink( $product_id ),
            'shop_name'      => get_bloginfo( 'name' ),
            'shop_url'       => home_url(),
            'variation_data' => $variation ? wp_json_encode( $variation ) : '{}',
            'in_stock'       => $product->is_in_stock() ? 1 : 0,
            'stock_qty'      => $product->get_stock_quantity(),
            'meta_data'      => wp_json_encode( $cart_item_data ),
            'created_at'     => current_time( 'mysql', true ),
            'updated_at'     => current_time( 'mysql', true ),
        );

        $this->push_to_global_cart( $item_data );
    }

    /**
     * Write item to the global cart table on the checkout host.
     * Uses direct DB write — no HTTP, no WC reinitialization.
     */
    private function push_to_global_cart( $item_data ) {
        global $wpdb;

        self::$pushing = true;
        $host_id      = $this->host->get_host_id();
        $current_blog = get_current_blog_id();
        $need_switch  = ( (int) $current_blog !== (int) $host_id );

        if ( $need_switch ) {
            switch_to_blog( $host_id );
        }

        $table = $wpdb->prefix . 'znc_global_cart';

        // Check if table exists
        $table_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            DB_NAME, $table
        ) );

        if ( ! $table_exists ) {
            ZNC_Activator::create_tables();
        }

        // Upsert: update quantity if same product from same shop, else insert
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table}
             WHERE user_id = %d AND blog_id = %d AND product_id = %d AND variation_id = %d
             LIMIT 1",
            $item_data['user_id'],
            $item_data['blog_id'],
            $item_data['product_id'],
            $item_data['variation_id']
        ) );

        if ( $existing ) {
            $wpdb->update(
                $table,
                array(
                    'quantity'   => $item_data['quantity'],
                    'price'      => $item_data['price'],
                    'in_stock'   => $item_data['in_stock'],
                    'stock_qty'  => $item_data['stock_qty'],
                    'updated_at' => $item_data['updated_at'],
                ),
                array( 'id' => $existing ),
                array( '%d', '%f', '%d', '%d', '%s' ),
                array( '%d' )
            );
        } else {
            $wpdb->insert( $table, $item_data );
        }

        if ( $need_switch ) {
            restore_current_blog();
        }

        self::$pushing = false;

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf(
                '[ZNC] Pushed product %d from blog %d to global cart on host %d (user %d)',
                $item_data['product_id'],
                $item_data['blog_id'],
                $host_id,
                $item_data['user_id']
            ) );
        }
    }

    /**
     * Remove item from global cart when removed on subsite.
     */
    public function on_remove_from_cart( $cart_item_key, $cart ) {
        if ( ! is_user_logged_in() ) return;
        $item = isset( $cart->removed_cart_contents[ $cart_item_key ] ) ? $cart->removed_cart_contents[ $cart_item_key ] : null;
        if ( ! $item ) return;

        global $wpdb;
        $host_id      = $this->host->get_host_id();
        $current_blog = get_current_blog_id();
        $need_switch  = ( (int) $current_blog !== (int) $host_id );

        if ( $need_switch ) switch_to_blog( $host_id );

        $table = $wpdb->prefix . 'znc_global_cart';
        $wpdb->delete( $table, array(
            'user_id'      => get_current_user_id(),
            'blog_id'      => $current_blog,
            'product_id'   => $item['product_id'],
            'variation_id' => isset( $item['variation_id'] ) ? $item['variation_id'] : 0,
        ) );

        if ( $need_switch ) restore_current_blog();
    }

    /**
     * Update quantity in global cart when changed on subsite.
     */
    public function on_qty_update( $cart_item_key, $quantity, $old_quantity, $cart ) {
        if ( ! is_user_logged_in() ) return;
        $item = isset( $cart->cart_contents[ $cart_item_key ] ) ? $cart->cart_contents[ $cart_item_key ] : null;
        if ( ! $item ) return;

        global $wpdb;
        $host_id      = $this->host->get_host_id();
        $current_blog = get_current_blog_id();
        $need_switch  = ( (int) $current_blog !== (int) $host_id );

        if ( $need_switch ) switch_to_blog( $host_id );

        $table = $wpdb->prefix . 'znc_global_cart';
        $wpdb->update(
            $table,
            array( 'quantity' => $quantity, 'updated_at' => current_time( 'mysql', true ) ),
            array(
                'user_id'      => get_current_user_id(),
                'blog_id'      => $current_blog,
                'product_id'   => $item['product_id'],
                'variation_id' => isset( $item['variation_id'] ) ? $item['variation_id'] : 0,
            ),
            array( '%d', '%s' )
        );

        if ( $need_switch ) restore_current_blog();
    }

    /**
     * Add global cart count to WC AJAX fragments.
     */
    public function inject_cart_count_fragment( $fragments ) {
        if ( ! is_user_logged_in() ) return $fragments;

        global $wpdb;
        $host_id      = $this->host->get_host_id();
        $current_blog = get_current_blog_id();
        $need_switch  = ( (int) $current_blog !== (int) $host_id );

        if ( $need_switch ) switch_to_blog( $host_id );

        $table = $wpdb->prefix . 'znc_global_cart';
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(quantity), 0) FROM {$table} WHERE user_id = %d",
            get_current_user_id()
        ) );

        if ( $need_switch ) restore_current_blog();

        $fragments['.znc-global-cart-count'] = '<span class="znc-global-cart-count">' . $count . '</span>';
        return $fragments;
    }
}
