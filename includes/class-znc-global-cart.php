<?php
/**
 * Global Cart — Central storage using wp_usermeta.
 *
 * wp_usermeta is network-wide in WordPress multisite.
 * get_user_meta() / update_user_meta() work from ANY subsite
 * without switch_to_blog(). This is the single source of truth.
 *
 * @package ZincklesNetCart
 * @since   1.5.1
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Global_Cart {

    const META_KEY = '_znc_global_cart';

    public function get_cart( $user_id = null ) {
        $user_id = $user_id ?: get_current_user_id();
        if ( ! $user_id ) return array();
        $cart = get_user_meta( $user_id, self::META_KEY, true );
        return is_array( $cart ) ? $cart : array();
    }

    public function add_item( $user_id, $blog_id, $product_id, $quantity = 1, $variation_id = 0, $variation = array() ) {
        if ( ! $user_id || ! $blog_id || ! $product_id ) return false;
        $cart = $this->get_cart( $user_id );
        $key  = $this->make_key( $blog_id, $product_id, $variation_id );

        if ( isset( $cart[ $key ] ) ) {
            $cart[ $key ]['quantity'] += (int) $quantity;
        } else {
            $cart[ $key ] = array(
                'blog_id'      => (int) $blog_id,
                'product_id'   => (int) $product_id,
                'variation_id' => (int) $variation_id,
                'variation'    => (array) $variation,
                'quantity'     => (int) $quantity,
                'added_at'     => time(),
            );
        }
        return update_user_meta( $user_id, self::META_KEY, $cart );
    }

    public function remove_item( $user_id, $item_key ) {
        $cart = $this->get_cart( $user_id );
        if ( ! isset( $cart[ $item_key ] ) ) return false;
        unset( $cart[ $item_key ] );
        return update_user_meta( $user_id, self::META_KEY, $cart );
    }

    public function update_quantity( $user_id, $item_key, $quantity ) {
        if ( $quantity <= 0 ) {
            return $this->remove_item( $user_id, $item_key );
        }
        $cart = $this->get_cart( $user_id );
        if ( ! isset( $cart[ $item_key ] ) ) return false;
        $cart[ $item_key ]['quantity'] = (int) $quantity;
        return update_user_meta( $user_id, self::META_KEY, $cart );
    }

    public function clear_cart( $user_id = null ) {
        $user_id = $user_id ?: get_current_user_id();
        if ( ! $user_id ) return false;
        return delete_user_meta( $user_id, self::META_KEY );
    }

    public function get_item_count( $user_id = null ) {
        $cart  = $this->get_cart( $user_id );
        $count = 0;
        foreach ( $cart as $item ) {
            $count += (int) $item['quantity'];
        }
        return $count;
    }

    public function get_items_by_blog( $user_id = null ) {
        $cart    = $this->get_cart( $user_id );
        $grouped = array();
        foreach ( $cart as $key => $item ) {
            $bid = (int) $item['blog_id'];
            if ( ! isset( $grouped[ $bid ] ) ) {
                $grouped[ $bid ] = array();
            }
            $grouped[ $bid ][ $key ] = $item;
        }
        return $grouped;
    }

    public function get_blog_ids( $user_id = null ) {
        $cart = $this->get_cart( $user_id );
        $ids  = array();
        foreach ( $cart as $item ) {
            $ids[] = (int) $item['blog_id'];
        }
        return array_unique( $ids );
    }

    public function is_empty( $user_id = null ) {
        $cart = $this->get_cart( $user_id );
        return empty( $cart );
    }

    public function purge_expired( $user_id, $max_age_days = 30 ) {
        $cart    = $this->get_cart( $user_id );
        $cutoff  = time() - ( $max_age_days * DAY_IN_SECONDS );
        $changed = false;
        foreach ( $cart as $key => $item ) {
            if ( isset( $item['added_at'] ) && $item['added_at'] < $cutoff ) {
                unset( $cart[ $key ] );
                $changed = true;
            }
        }
        if ( $changed ) {
            update_user_meta( $user_id, self::META_KEY, $cart );
        }
        return $changed;
    }

    private function make_key( $blog_id, $product_id, $variation_id = 0 ) {
        return (int) $blog_id . '_' . (int) $product_id . '_' . (int) $variation_id;
    }
}
