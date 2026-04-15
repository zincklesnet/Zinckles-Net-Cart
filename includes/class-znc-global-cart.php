<?php
/**
 * Global Cart — stores cart data in wp_usermeta across a multisite network.
 *
 * v1.7.1 EMERGENCY FIX:
 *   - Added singleton instance() method
 *   - Made make_key() public
 *   - add_item() now accepts both array and 6-param signatures
 *   - purge_expired() works without $user_id for cron usage
 *
 * @package ZincklesNetCart
 * @since   1.6.0
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Global_Cart {

    /* ────────────────────────────────────────────
     *  Singleton
     * ──────────────────────────────────────────── */
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /* ────────────────────────────────────────────
     *  Constants and properties
     * ──────────────────────────────────────────── */
    const META_KEY        = 'znc_global_cart';
    const MAX_ITEMS       = 100;
    const MAX_QTY         = 999;
    const DEFAULT_EXPIRY  = 7;

    private $cache = array();

    public function __construct() {
        // intentionally lightweight
    }

    /* ────────────────────────────────────────────
     *  Cart key — PUBLIC so Interceptor and Renderer
     *  can call it externally.
     * ──────────────────────────────────────────── */
    public function make_key( $blog_id, $product_id, $variation_id = 0, $variation_data = array() ) {
        $parts = array(
            'b' => absint( $blog_id ),
            'p' => absint( $product_id ),
            'v' => absint( $variation_id ),
            'd' => ! empty( $variation_data ) ? md5( wp_json_encode( $variation_data ) ) : '',
        );
        return md5( wp_json_encode( $parts ) );
    }

    /* ────────────────────────────────────────────
     *  Get cart
     * ──────────────────────────────────────────── */
    public function get_cart( $user_id ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return array();
        }

        if ( isset( $this->cache[ $user_id ] ) ) {
            return $this->cache[ $user_id ];
        }

        $raw = get_user_meta( $user_id, self::META_KEY, true );
        $cart = is_array( $raw ) ? $raw : array();

        $this->cache[ $user_id ] = $cart;
        return $cart;
    }

    /* ────────────────────────────────────────────
     *  Save cart
     * ──────────────────────────────────────────── */
    public function save_cart( $user_id, $cart ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return false;
        }

        $this->cache[ $user_id ] = $cart;
        return update_user_meta( $user_id, self::META_KEY, $cart );
    }

    /* ────────────────────────────────────────────
     *  Add item — accepts BOTH signatures:
     *
     *  New (v1.7.x): add_item( $user_id, array( 'blog_id'=>..., 'product_id'=>..., ... ) )
     *  Old (v1.6.x): add_item( $user_id, $blog_id, $product_id, $qty, $variation_id, $variation )
     * ──────────────────────────────────────────── */
    public function add_item( $user_id, $data_or_blog_id = array(), $product_id = 0, $qty = 1, $variation_id = 0, $variation = array() ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return new WP_Error( 'znc_no_user', 'User ID is required.' );
        }

        // Detect which call signature is being used
        if ( is_array( $data_or_blog_id ) ) {
            // v1.7.x array signature
            $blog_id        = absint( $data_or_blog_id['blog_id'] ?? 0 );
            $product_id     = absint( $data_or_blog_id['product_id'] ?? 0 );
            $qty            = max( 1, absint( $data_or_blog_id['quantity'] ?? ( $data_or_blog_id['qty'] ?? 1 ) ) );
            $variation_id   = absint( $data_or_blog_id['variation_id'] ?? 0 );
            $variation_data = (array) ( $data_or_blog_id['variation'] ?? array() );
        } else {
            // v1.6.x positional signature
            $blog_id        = absint( $data_or_blog_id );
            $product_id     = absint( $product_id );
            $qty            = max( 1, absint( $qty ) );
            $variation_id   = absint( $variation_id );
            $variation_data = (array) $variation;
        }

        if ( ! $blog_id || ! $product_id ) {
            return new WP_Error( 'znc_invalid_item', 'Blog ID and Product ID are required.' );
        }

        if ( $qty > self::MAX_QTY ) {
            $qty = self::MAX_QTY;
        }

        // Validate product exists on the target site
        $current_blog = get_current_blog_id();
        $switched     = ( $current_blog !== $blog_id );
        if ( $switched ) {
            switch_to_blog( $blog_id );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product || ! $product->is_purchasable() ) {
            if ( $switched ) {
                restore_current_blog();
            }
            return new WP_Error( 'znc_invalid_product', 'Product does not exist or is not purchasable.' );
        }

        $price = (float) $product->get_price();

        if ( $switched ) {
            restore_current_blog();
        }

        // Get current cart
        $cart = $this->get_cart( $user_id );

        // Enforce max items
        if ( count( $cart ) >= self::MAX_ITEMS ) {
            return new WP_Error( 'znc_cart_full', 'Cart is full (max ' . self::MAX_ITEMS . ' items).' );
        }

        // Build key
        $key = $this->make_key( $blog_id, $product_id, $variation_id, $variation_data );

        // Add or update
        if ( isset( $cart[ $key ] ) ) {
            $new_qty = $cart[ $key ]['quantity'] + $qty;
            $cart[ $key ]['quantity']  = min( $new_qty, self::MAX_QTY );
            $cart[ $key ]['updated']   = time();
        } else {
            $cart[ $key ] = array(
                'blog_id'       => $blog_id,
                'product_id'    => $product_id,
                'variation_id'  => $variation_id,
                'variation'     => $variation_data,
                'quantity'      => $qty,
                'price'         => $price,
                'added'         => time(),
                'updated'       => time(),
            );
        }

        $this->save_cart( $user_id, $cart );
        return true;
    }

    /* ────────────────────────────────────────────
     *  Update quantity
     * ──────────────────────────────────────────── */
    public function update_quantity( $user_id, $key, $qty ) {
        $user_id = absint( $user_id );
        $qty     = absint( $qty );
        $cart    = $this->get_cart( $user_id );

        if ( ! isset( $cart[ $key ] ) ) {
            return false;
        }

        if ( $qty < 1 ) {
            return $this->remove_item( $user_id, $key );
        }

        $cart[ $key ]['quantity'] = min( $qty, self::MAX_QTY );
        $cart[ $key ]['updated']  = time();

        return $this->save_cart( $user_id, $cart );
    }

    /* ────────────────────────────────────────────
     *  Remove item
     * ──────────────────────────────────────────── */
    public function remove_item( $user_id, $key ) {
        $user_id = absint( $user_id );
        $cart    = $this->get_cart( $user_id );

        if ( ! isset( $cart[ $key ] ) ) {
            return false;
        }

        unset( $cart[ $key ] );
        return $this->save_cart( $user_id, $cart );
    }

    /* ────────────────────────────────────────────
     *  Clear cart
     * ──────────────────────────────────────────── */
    public function clear_cart( $user_id ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) {
            return false;
        }

        $this->cache[ $user_id ] = array();
        return delete_user_meta( $user_id, self::META_KEY );
    }

    /* ────────────────────────────────────────────
     *  Get count
     * ──────────────────────────────────────────── */
    public function get_count( $user_id ) {
        $cart  = $this->get_cart( $user_id );
        $count = 0;
        foreach ( $cart as $item ) {
            $count += absint( $item['quantity'] ?? 1 );
        }
        return $count;
    }

    /* ────────────────────────────────────────────
     *  Get items by shop
     * ──────────────────────────────────────────── */
    public function get_items_by_shop( $user_id ) {
        $cart   = $this->get_cart( $user_id );
        $groups = array();

        foreach ( $cart as $key => $item ) {
            $bid = absint( $item['blog_id'] ?? 0 );
            if ( ! $bid ) {
                continue;
            }
            if ( ! isset( $groups[ $bid ] ) ) {
                $groups[ $bid ] = array();
            }
            $groups[ $bid ][ $key ] = $item;
        }

        return $groups;
    }

    /* ────────────────────────────────────────────
     *  Get cart totals grouped by currency
     * ──────────────────────────────────────────── */
    public function get_totals_by_currency( $user_id ) {
        $cart   = $this->get_cart( $user_id );
        $totals = array();

        foreach ( $cart as $item ) {
            $bid  = absint( $item['blog_id'] ?? 0 );
            $qty  = absint( $item['quantity'] ?? 1 );

            $current = get_current_blog_id();
            $sw      = ( $current !== $bid );
            if ( $sw ) {
                switch_to_blog( $bid );
            }

            $currency = function_exists( 'get_woocommerce_currency' )
                ? get_woocommerce_currency()
                : 'USD';

            $price = (float) ( $item['price'] ?? 0 );
            $product = wc_get_product( absint( $item['product_id'] ?? 0 ) );
            if ( $product ) {
                $price = (float) $product->get_price();
            }

            if ( $sw ) {
                restore_current_blog();
            }

            if ( ! isset( $totals[ $currency ] ) ) {
                $totals[ $currency ] = 0.0;
            }
            $totals[ $currency ] += $price * $qty;
        }

        return $totals;
    }

    /* ────────────────────────────────────────────
     *  Purge expired carts
     *
     *  Works in TWO modes:
     *    1. purge_expired()           — cron mode, iterates ALL users
     *    2. purge_expired( $user_id ) — single-user mode (v1.6 compat)
     * ──────────────────────────────────────────── */
    public function purge_expired( $user_id = null, $max_age = 0 ) {
        if ( $max_age < 1 ) {
            $settings = get_site_option( 'znc_network_settings', array() );
            $max_age  = absint( $settings['cart_expiry_days'] ?? self::DEFAULT_EXPIRY );
            if ( $max_age < 1 ) {
                $max_age = self::DEFAULT_EXPIRY;
            }
        }

        $cutoff = time() - ( $max_age * DAY_IN_SECONDS );
        $purged = 0;

        if ( $user_id ) {
            $purged += $this->purge_user_cart( absint( $user_id ), $cutoff );
        } else {
            global $wpdb;
            $user_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s",
                    self::META_KEY
                )
            );

            foreach ( $user_ids as $uid ) {
                $purged += $this->purge_user_cart( absint( $uid ), $cutoff );
            }
        }

        return $purged;
    }

    private function purge_user_cart( $user_id, $cutoff ) {
        $cart    = $this->get_cart( $user_id );
        $purged  = 0;
        $changed = false;

        foreach ( $cart as $key => $item ) {
            $updated = absint( $item['updated'] ?? ( $item['added'] ?? 0 ) );
            if ( $updated > 0 && $updated < $cutoff ) {
                unset( $cart[ $key ] );
                $purged++;
                $changed = true;
            }
        }

        if ( $changed ) {
            if ( empty( $cart ) ) {
                delete_user_meta( $user_id, self::META_KEY );
            } else {
                $this->save_cart( $user_id, $cart );
            }
            unset( $this->cache[ $user_id ] );
        }

        return $purged;
    }

    /* ────────────────────────────────────────────
     *  Utility — check if cart is empty
     * ──────────────────────────────────────────── */
    public function is_empty( $user_id ) {
        $cart = $this->get_cart( $user_id );
        return empty( $cart );
    }

    /* ────────────────────────────────────────────
     *  Utility — get a specific item
     * ──────────────────────────────────────────── */
    public function get_item( $user_id, $key ) {
        $cart = $this->get_cart( $user_id );
        return $cart[ $key ] ?? null;
    }
}
