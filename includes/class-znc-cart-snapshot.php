<?php
/**
 * Cart Snapshot Builder (runs on each subsite).
 *
 * v1.2.0 FIX: Auto-pushes cart items to the main site's global cart
 * whenever a product is added/updated/removed on ANY enrolled subsite.
 * This ensures the global cart always reflects the aggregate of all shops.
 *
 * @package ZincklesNetCart
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class ZNC_Cart_Snapshot {

    public function init() {
        // Fire on add-to-cart, quantity update, and item removal.
        add_action( 'woocommerce_add_to_cart',            array( $this, 'on_cart_change' ), 20, 6 );
        add_action( 'woocommerce_cart_item_removed',      array( $this, 'on_item_removed' ), 20, 2 );
        add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'on_quantity_update' ), 20, 4 );
        add_action( 'woocommerce_cart_emptied',           array( $this, 'on_cart_emptied' ), 20 );

        // Non-blocking push via shutdown hook.
        add_action( 'znc_push_cart_snapshot', array( $this, 'push_to_main_site' ) );
    }

    /**
     * Triggered on add-to-cart.
     */
    public function on_cart_change( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
        $this->schedule_push();
    }

    /**
     * Triggered on item removal.
     */
    public function on_item_removed( $cart_item_key, $cart ) {
        $this->schedule_push();
    }

    /**
     * Triggered on quantity update.
     */
    public function on_quantity_update( $cart_item_key, $quantity, $old_quantity, $cart ) {
        $this->schedule_push();
    }

    /**
     * Triggered when cart is emptied.
     */
    public function on_cart_emptied() {
        $this->schedule_push();
    }

    /**
     * Schedule the push for the shutdown hook (non-blocking).
     */
    private function schedule_push() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        // Check enrollment.
        if ( ! $this->is_enrolled() ) {
            return;
        }

        // Use shutdown to avoid blocking the add-to-cart response.
        if ( ! has_action( 'shutdown', array( $this, 'push_to_main_site' ) ) ) {
            add_action( 'shutdown', array( $this, 'push_to_main_site' ) );
        }
    }

    /**
     * Check if this subsite is enrolled in Net Cart.
     */
    private function is_enrolled() {
        $settings = get_site_option( 'znc_network_settings', array() );
        $blog_id  = get_current_blog_id();

        // Check blocked.
        if ( in_array( $blog_id, (array) ( $settings['blocked_sites'] ?? array() ), true ) ) {
            return false;
        }

        $mode = $settings['enrollment_mode'] ?? 'opt-in';
        switch ( $mode ) {
            case 'opt-out':
                return true;
            case 'manual':
            case 'opt-in':
            default:
                return in_array( $blog_id, (array) ( $settings['enrolled_sites'] ?? array() ), true );
        }
    }

    /**
     * Build the full cart snapshot for a user on this subsite.
     */
    public function build( $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return array(
                'blog_id'  => get_current_blog_id(),
                'user_id'  => $user_id,
                'items'    => array(),
                'currency' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD',
                'shop'     => $this->get_shop_info(),
            );
        }

        $items = array();
        foreach ( WC()->cart->get_cart() as $key => $item ) {
            $product = $item['data'];
            if ( ! $product ) {
                continue;
            }

            $items[] = array(
                'cart_item_key' => $key,
                'product_id'    => $item['product_id'],
                'variation_id'  => $item['variation_id'] ?? 0,
                'quantity'      => $item['quantity'],
                'product_name'  => $product->get_name(),
                'price'         => (float) $product->get_price(),
                'line_total'    => (float) $product->get_price() * $item['quantity'],
                'sku'           => $product->get_sku(),
                'image_url'     => wp_get_attachment_url( $product->get_image_id() ),
                'permalink'     => $product->get_permalink(),
                'in_stock'      => $product->is_in_stock(),
                'stock_qty'     => $product->managing_stock() ? $product->get_stock_quantity() : null,
                'weight'        => $product->get_weight(),
                'variation'     => $item['variation'] ?? array(),
                'meta'          => $this->get_item_meta( $item ),
            );
        }

        return array(
            'blog_id'    => get_current_blog_id(),
            'user_id'    => $user_id,
            'items'      => $items,
            'currency'   => get_woocommerce_currency(),
            'shop'       => $this->get_shop_info(),
            'timestamp'  => current_time( 'timestamp' ),
            'cart_hash'  => WC()->cart->get_cart_hash(),
        );
    }

    /**
     * Push the current user's cart snapshot to the main site global cart.
     * Called on shutdown (non-blocking).
     */
    public function push_to_main_site() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id  = get_current_user_id();
        $blog_id  = get_current_blog_id();
        $snapshot = $this->build( $user_id );

        // Switch to main site and sync.
        $main_site_id = get_main_site_id();
        switch_to_blog( $main_site_id );

        global $wpdb;
        $table = $wpdb->prefix . 'znc_global_cart';

        // Check if table exists.
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            restore_current_blog();
            return;
        }

        // Clear old items from this subsite for this user.
        $wpdb->delete( $table, array(
            'user_id' => $user_id,
            'blog_id' => $blog_id,
        ) );

        // Insert each item from the snapshot.
        foreach ( $snapshot['items'] as $item ) {
            $wpdb->insert( $table, array(
                'user_id'       => $user_id,
                'blog_id'       => $blog_id,
                'product_id'    => $item['product_id'],
                'variation_id'  => $item['variation_id'],
                'quantity'      => $item['quantity'],
                'product_name'  => $item['product_name'],
                'price'         => $item['price'],
                'line_total'    => $item['line_total'],
                'currency'      => $snapshot['currency'],
                'sku'           => $item['sku'],
                'image_url'     => $item['image_url'],
                'permalink'     => $item['permalink'],
                'in_stock'      => $item['in_stock'] ? 1 : 0,
                'stock_qty'     => $item['stock_qty'],
                'variation_data' => maybe_serialize( $item['variation'] ),
                'meta_data'     => maybe_serialize( $item['meta'] ),
                'shop_name'     => $snapshot['shop']['name'] ?? '',
                'shop_url'      => $snapshot['shop']['url'] ?? '',
                'created_at'    => current_time( 'mysql' ),
                'updated_at'    => current_time( 'mysql' ),
            ) );
        }

        restore_current_blog();

        do_action( 'znc_cart_snapshot_pushed', $user_id, $blog_id, $snapshot );
    }

    /**
     * Get basic shop info for this subsite.
     */
    private function get_shop_info() {
        $subsite_settings = get_option( 'znc_subsite_settings', array() );

        return array(
            'name'     => $subsite_settings['display_name'] ?? get_bloginfo( 'name' ),
            'url'      => home_url(),
            'currency' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD',
            'blog_id'  => get_current_blog_id(),
            'icon'     => $subsite_settings['badge_icon'] ?? '',
            'color'    => $subsite_settings['badge_color'] ?? '#7c3aed',
        );
    }

    /**
     * Extract relevant meta from a cart item.
     */
    private function get_item_meta( $item ) {
        $meta = array();

        // Include custom meta keys if configured.
        $subsite_settings = get_option( 'znc_subsite_settings', array() );
        $custom_keys = array_filter( explode( ',', $subsite_settings['custom_meta_keys'] ?? '' ) );

        foreach ( $custom_keys as $key ) {
            $key = trim( $key );
            if ( isset( $item[ $key ] ) ) {
                $meta[ $key ] = $item[ $key ];
            }
        }

        return $meta;
    }
}
