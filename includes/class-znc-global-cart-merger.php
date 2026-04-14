<?php
/**
 * Global Cart Merger — adds items + full cart refresh from all enrolled subsites.
 *
 * v1.2.0: Added refresh_all() to pull cart snapshots from every enrolled subsite
 * so the global cart always reflects the aggregate of ALL shops.
 *
 * @package ZincklesNetCart
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class ZNC_Global_Cart_Merger {

    /** @var ZNC_Global_Cart_Store */
    private $store;

    /** @var ZNC_Currency_Handler */
    private $currency;

    public function __construct( ZNC_Global_Cart_Store $store, ZNC_Currency_Handler $currency ) {
        $this->store    = $store;
        $this->currency = $currency;
    }

    public function init() {
        // No hooks needed — called directly by REST endpoints and checkout.
    }

    /**
     * Add a single item to the global cart (from a subsite push or REST call).
     */
    public function add_item( $params ) {
        $user_id = $params['user_id'] ?? get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error( 'no_user', 'User ID is required.', array( 'status' => 400 ) );
        }

        $blog_id = absint( $params['blog_id'] ?? 0 );
        if ( ! $blog_id ) {
            return new WP_Error( 'no_blog', 'Blog ID is required.', array( 'status' => 400 ) );
        }

        // Check enrollment.
        if ( ! ZNC_Network_Admin::is_site_enrolled( $blog_id ) ) {
            return new WP_Error( 'not_enrolled', 'This site is not enrolled in Net Cart.', array( 'status' => 403 ) );
        }

        // Check cart limits.
        $settings = get_site_option( 'znc_network_settings', array() );
        $max_items = absint( $settings['max_items_per_cart'] ?? 100 );
        $max_shops = absint( $settings['max_shops_per_cart'] ?? 10 );

        $current_count = $this->store->get_item_count( $user_id );
        $current_shops = $this->store->get_shop_count( $user_id );

        if ( $current_count >= $max_items ) {
            return new WP_Error( 'cart_full', 'Cart item limit reached.', array( 'status' => 400 ) );
        }

        // Check shop limit only if this is a new shop.
        $existing_items = $this->store->get_cart( $user_id );
        $existing_blogs = array_unique( array_column( $existing_items, 'blog_id' ) );
        if ( ! in_array( $blog_id, $existing_blogs ) && $current_shops >= $max_shops ) {
            return new WP_Error( 'shop_limit', 'Maximum shops per cart reached.', array( 'status' => 400 ) );
        }

        // Upsert the item.
        $line_id = $this->store->upsert_item( $user_id, $params );

        return $this->store->get_cart( $user_id, 'shop' );
    }

    /**
     * Refresh the entire global cart by re-pulling snapshots from all enrolled subsites.
     * This ensures the cart always shows the latest prices, stock, and items.
     *
     * v1.2.0 addition — the core of the cross-site aggregation fix.
     */
    public function refresh_all( $user_id ) {
        $enrolled = ZNC_Network_Admin::get_enrolled_sites();

        foreach ( $enrolled as $site ) {
            $blog_id = $site['blog_id'];

            switch_to_blog( $blog_id );

            if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) ) {
                restore_current_blog();
                continue;
            }

            // Build the snapshot for this user on this subsite.
            $snapshot = new ZNC_Cart_Snapshot();
            $snap_data = $snapshot->build( $user_id );

            restore_current_blog();

            // Now on main site — sync the snapshot into the global cart table.
            global $wpdb;
            $table = $wpdb->prefix . 'znc_global_cart';

            // Clear old items from this subsite for this user.
            $wpdb->delete( $table, array(
                'user_id' => $user_id,
                'blog_id' => $blog_id,
            ) );

            // Insert fresh items.
            foreach ( $snap_data['items'] as $item ) {
                $wpdb->insert( $table, array(
                    'user_id'        => $user_id,
                    'blog_id'        => $blog_id,
                    'product_id'     => $item['product_id'],
                    'variation_id'   => $item['variation_id'] ?? 0,
                    'quantity'       => $item['quantity'],
                    'product_name'   => $item['product_name'],
                    'price'          => $item['price'],
                    'line_total'     => $item['line_total'],
                    'currency'       => $snap_data['currency'],
                    'sku'            => $item['sku'] ?? '',
                    'image_url'      => $item['image_url'] ?? '',
                    'permalink'      => $item['permalink'] ?? '',
                    'in_stock'       => $item['in_stock'] ? 1 : 0,
                    'stock_qty'      => $item['stock_qty'],
                    'variation_data' => maybe_serialize( $item['variation'] ?? array() ),
                    'meta_data'      => maybe_serialize( $item['meta'] ?? array() ),
                    'shop_name'      => $snap_data['shop']['name'] ?? '',
                    'shop_url'       => $snap_data['shop']['url'] ?? '',
                    'created_at'     => current_time( 'mysql' ),
                    'updated_at'     => current_time( 'mysql' ),
                ) );
            }
        }

        do_action( 'znc_global_cart_refreshed', $user_id );

        return true;
    }

    /**
     * Validate all items in the global cart against their origin subsites.
     * Used by the checkout orchestrator before processing.
     */
    public function validate_all( $user_id ) {
        $shops   = $this->store->get_cart( $user_id, 'shop' );
        $issues  = array();

        foreach ( $shops as $shop ) {
            $blog_id = $shop['blog_id'];

            switch_to_blog( $blog_id );

            foreach ( $shop['items'] as $item ) {
                $product = function_exists( 'wc_get_product' )
                    ? wc_get_product( $item['variation_id'] ?: $item['product_id'] )
                    : null;

                if ( ! $product ) {
                    $issues[] = array(
                        'blog_id'    => $blog_id,
                        'product_id' => $item['product_id'],
                        'issue'      => 'not_found',
                        'message'    => $item['product_name'] . ' is no longer available.',
                    );
                    continue;
                }

                // Price check.
                $current_price = (float) $product->get_price();
                if ( abs( $current_price - $item['price'] ) > 0.01 ) {
                    $issues[] = array(
                        'blog_id'       => $blog_id,
                        'product_id'    => $item['product_id'],
                        'issue'         => 'price_changed',
                        'old_price'     => $item['price'],
                        'current_price' => $current_price,
                        'message'       => sprintf(
                            '%s price changed from %s to %s.',
                            $item['product_name'],
                            $item['price'],
                            $current_price
                        ),
                    );
                }

                // Stock check.
                if ( ! $product->is_in_stock() ) {
                    $issues[] = array(
                        'blog_id'    => $blog_id,
                        'product_id' => $item['product_id'],
                        'issue'      => 'out_of_stock',
                        'message'    => $item['product_name'] . ' is out of stock.',
                    );
                } elseif ( $product->managing_stock() && $product->get_stock_quantity() < $item['quantity'] ) {
                    $issues[] = array(
                        'blog_id'    => $blog_id,
                        'product_id' => $item['product_id'],
                        'issue'      => 'insufficient_stock',
                        'available'  => $product->get_stock_quantity(),
                        'requested'  => $item['quantity'],
                        'message'    => sprintf(
                            '%s: only %d available (requested %d).',
                            $item['product_name'],
                            $product->get_stock_quantity(),
                            $item['quantity']
                        ),
                    );
                }
            }

            restore_current_blog();
        }

        return $issues;
    }
}
