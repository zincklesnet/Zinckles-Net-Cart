<?php
/**
 * Shortcodes — registers [znc_global_cart] and [znc_checkout].
 * v1.3.2 — THIS WAS THE MISSING FILE. Shortcodes were never registered.
 *
 * @package ZincklesNetCart
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Shortcodes {

    /** @var ZNC_Checkout_Host */
    private static $host;

    public static function init( ZNC_Checkout_Host $host ) {
        self::$host = $host;
        add_shortcode( 'znc_global_cart', array( __CLASS__, 'render_global_cart' ) );
        add_shortcode( 'znc_checkout',    array( __CLASS__, 'render_checkout' ) );
    }

    /**
     * [znc_global_cart] — renders the global cart with items from all enrolled subsites.
     */
    public static function render_global_cart( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<div class="znc-login-required"><p>' .
                sprintf(
                    __( 'Please <a href="%s">log in</a> to view your global cart.', 'zinckles-net-cart' ),
                    esc_url( wp_login_url( get_permalink() ) )
                ) . '</p></div>';
        }

        $user_id = get_current_user_id();
        $store   = new ZNC_Global_Cart_Store();
        $items   = $store->get_cart( $user_id );

        // Enqueue front-end assets
        wp_enqueue_style( 'znc-front', ZNC_PLUGIN_URL . 'assets/css/znc-front.css', array(), ZNC_VERSION );
        wp_enqueue_script( 'znc-front', ZNC_PLUGIN_URL . 'assets/js/znc-front.js', array( 'jquery' ), ZNC_VERSION, true );
        wp_localize_script( 'znc-front', 'zncFront', array(
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'znc_front' ),
            'checkoutUrl' => self::$host->get_checkout_url(),
            'currency'    => get_woocommerce_currency(),
            'symbol'      => get_woocommerce_currency_symbol(),
        ) );

        ob_start();

        if ( empty( $items ) ) {
            echo '<div class="znc-cart znc-cart-empty">';
            echo '<div class="znc-empty-icon">🛒</div>';
            echo '<h2>' . esc_html__( 'Your Global Cart is Empty', 'zinckles-net-cart' ) . '</h2>';
            echo '<p>' . esc_html__( 'Browse products on any shop in our network and add them to your cart.', 'zinckles-net-cart' ) . '</p>';

            // List enrolled shops
            $enrolled = self::$host->get_enrolled_shop_ids();
            if ( ! empty( $enrolled ) ) {
                echo '<div class="znc-shop-links"><h3>' . esc_html__( 'Browse Our Shops', 'zinckles-net-cart' ) . '</h3><ul>';
                foreach ( $enrolled as $blog_id ) {
                    $details = get_blog_details( $blog_id );
                    if ( $details ) {
                        $shop_url = $details->siteurl . '/shop/';
                        echo '<li><a href="' . esc_url( $shop_url ) . '">' . esc_html( $details->blogname ) . '</a></li>';
                    }
                }
                echo '</ul></div>';
            }
            echo '</div>';
            return ob_get_clean();
        }

        // Group items by source blog
        $shops = array();
        foreach ( $items as $item ) {
            $blog_id = $item->blog_id ?? $item->source_blog_id ?? 0;
            if ( ! isset( $shops[ $blog_id ] ) ) {
                $shops[ $blog_id ] = array(
                    'name'     => $item->shop_name ?? 'Shop #' . $blog_id,
                    'url'      => $item->shop_url ?? '',
                    'currency' => $item->currency ?? 'USD',
                    'items'    => array(),
                );
            }
            $shops[ $blog_id ]['items'][] = $item;
        }

        // Compute totals
        $currencies_used = array();
        $grand_total     = 0;
        foreach ( $shops as $blog_id => $shop ) {
            $shop_total = 0;
            foreach ( $shop['items'] as $item ) {
                $price     = floatval( $item->price ?? $item->product_price ?? 0 );
                $qty       = intval( $item->quantity ?? 1 );
                $line      = $price * $qty;
                $shop_total += $line;
            }
            $cur = $shop['currency'];
            if ( ! isset( $currencies_used[ $cur ] ) ) $currencies_used[ $cur ] = 0;
            $currencies_used[ $cur ] += $shop_total;
            $grand_total += $shop_total;
        }

        $is_mixed = count( $currencies_used ) > 1;

        echo '<div class="znc-cart">';
        echo '<div class="znc-cart-header">';
        echo '<h2>🛒 ' . esc_html__( 'Your Global Cart', 'zinckles-net-cart' ) . '</h2>';
        echo '<span class="znc-item-count">' . count( $items ) . ' ' . esc_html__( 'items from', 'zinckles-net-cart' ) . ' ' . count( $shops ) . ' ' . esc_html__( 'shops', 'zinckles-net-cart' ) . '</span>';
        if ( $is_mixed ) {
            echo '<span class="znc-badge znc-badge-mixed">Mixed Currency</span>';
        }
        echo '</div>';

        // Per-shop sections
        foreach ( $shops as $blog_id => $shop ) {
            $shop_total = 0;
            echo '<div class="znc-cart-shop" data-blog-id="' . esc_attr( $blog_id ) . '">';
            echo '<div class="znc-shop-header">';
            echo '<span class="znc-shop-badge" style="background:' . esc_attr( self::shop_color( $blog_id ) ) . ';">' . esc_html( mb_substr( $shop['name'], 0, 1 ) ) . '</span>';
            echo '<strong>' . esc_html( $shop['name'] ) . '</strong>';
            echo '<span class="znc-currency-tag">' . esc_html( $shop['currency'] ) . '</span>';
            echo '</div>';

            echo '<table class="znc-cart-items"><thead><tr>';
            echo '<th>' . esc_html__( 'Product', 'zinckles-net-cart' ) . '</th>';
            echo '<th>' . esc_html__( 'Price', 'zinckles-net-cart' ) . '</th>';
            echo '<th>' . esc_html__( 'Qty', 'zinckles-net-cart' ) . '</th>';
            echo '<th>' . esc_html__( 'Total', 'zinckles-net-cart' ) . '</th>';
            echo '<th></th>';
            echo '</tr></thead><tbody>';

            foreach ( $shop['items'] as $item ) {
                $price     = floatval( $item->price ?? $item->product_price ?? 0 );
                $qty       = intval( $item->quantity ?? 1 );
                $line      = $price * $qty;
                $shop_total += $line;
                $name      = $item->product_name ?? 'Product';
                $image     = $item->image_url ?? $item->product_image ?? '';
                $pid       = $item->product_id ?? 0;
                $item_id   = $item->id ?? 0;

                echo '<tr class="znc-cart-item" data-item-id="' . esc_attr( $item_id ) . '">';
                echo '<td class="znc-product-cell">';
                if ( $image ) echo '<img src="' . esc_url( $image ) . '" class="znc-thumb" alt="" />';
                echo '<span class="znc-product-name">' . esc_html( $name ) . '</span>';
                echo '</td>';
                echo '<td>' . esc_html( $shop['currency'] ) . ' ' . number_format( $price, 2 ) . '</td>';
                echo '<td><input type="number" class="znc-qty" value="' . esc_attr( $qty ) . '" min="1" max="99" data-item-id="' . esc_attr( $item_id ) . '" data-blog-id="' . esc_attr( $blog_id ) . '" data-product-id="' . esc_attr( $pid ) . '" /></td>';
                echo '<td class="znc-line-total">' . esc_html( $shop['currency'] ) . ' ' . number_format( $line, 2 ) . '</td>';
                echo '<td><button class="znc-remove-item" data-item-id="' . esc_attr( $item_id ) . '">✕</button></td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '<div class="znc-shop-total">' . esc_html__( 'Shop Total:', 'zinckles-net-cart' ) . ' <strong>' . esc_html( $shop['currency'] ) . ' ' . number_format( $shop_total, 2 ) . '</strong></div>';
            echo '</div>';
        }

        // Cart summary
        echo '<div class="znc-cart-summary">';
        if ( $is_mixed ) {
            echo '<div class="znc-currency-breakdown"><h3>' . esc_html__( 'Currency Breakdown', 'zinckles-net-cart' ) . '</h3>';
            foreach ( $currencies_used as $cur => $total ) {
                echo '<div class="znc-cur-line"><span>' . esc_html( $cur ) . '</span><strong>' . number_format( $total, 2 ) . '</strong></div>';
            }
            echo '</div>';
        }

        // MyCred balances
        if ( function_exists( 'mycred_get_types' ) ) {
            $types = mycred_get_types();
            $has_balance = false;
            $balance_html = '<div class="znc-zcred-balances"><h3>⚡ ' . esc_html__( 'Your Point Balances', 'zinckles-net-cart' ) . '</h3>';
            foreach ( $types as $slug => $label ) {
                $balance = (float) mycred_get_users_balance( $user_id, $slug );
                if ( $balance > 0 ) {
                    $has_balance = true;
                    $balance_html .= '<div class="znc-balance-line"><span>' . esc_html( $label ) . '</span><strong>' . number_format( $balance, 0 ) . '</strong></div>';
                }
            }
            $balance_html .= '</div>';
            if ( $has_balance ) echo $balance_html;
        }

        echo '<div class="znc-grand-total">';
        echo '<span>' . esc_html__( 'Grand Total', 'zinckles-net-cart' ) . '</span>';
        if ( ! $is_mixed ) {
            $cur = array_key_first( $currencies_used );
            echo '<strong>' . esc_html( $cur ) . ' ' . number_format( $grand_total, 2 ) . '</strong>';
        } else {
            echo '<strong>' . esc_html__( 'See currency breakdown above', 'zinckles-net-cart' ) . '</strong>';
        }
        echo '</div>';

        echo '<a href="' . esc_url( self::$host->get_checkout_url() ) . '" class="znc-checkout-btn">' . esc_html__( 'Proceed to Checkout', 'zinckles-net-cart' ) . '</a>';
        echo '</div>'; // .znc-cart-summary
        echo '</div>'; // .znc-cart

        return ob_get_clean();
    }

    /**
     * [znc_checkout] — renders the checkout form.
     */
    public static function render_checkout( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<div class="znc-login-required"><p>' .
                sprintf(
                    __( 'Please <a href="%s">log in</a> to checkout.', 'zinckles-net-cart' ),
                    esc_url( wp_login_url( get_permalink() ) )
                ) . '</p></div>';
        }

        $user_id = get_current_user_id();
        $store   = new ZNC_Global_Cart_Store();
        $items   = $store->get_cart( $user_id );

        if ( empty( $items ) ) {
            return '<div class="znc-checkout znc-cart-empty"><h2>' . esc_html__( 'Your cart is empty', 'zinckles-net-cart' ) .
                '</h2><p><a href="' . esc_url( self::$host->get_cart_url() ) . '">' .
                esc_html__( 'Return to cart', 'zinckles-net-cart' ) . '</a></p></div>';
        }

        wp_enqueue_style( 'znc-front', ZNC_PLUGIN_URL . 'assets/css/znc-front.css', array(), ZNC_VERSION );
        wp_enqueue_script( 'znc-front', ZNC_PLUGIN_URL . 'assets/js/znc-front.js', array( 'jquery' ), ZNC_VERSION, true );

        ob_start();
        echo '<div class="znc-checkout">';
        echo '<h2>🛍 ' . esc_html__( 'Net Cart Checkout', 'zinckles-net-cart' ) . '</h2>';
        echo '<p>' . esc_html__( 'Review your items and complete your purchase.', 'zinckles-net-cart' ) . '</p>';

        // Use WooCommerce checkout if available
        if ( function_exists( 'woocommerce_checkout' ) ) {
            echo '<div class="znc-wc-checkout-wrapper">';
            echo do_shortcode( '[woocommerce_checkout]' );
            echo '</div>';
        } else {
            echo '<p>' . esc_html__( 'WooCommerce checkout is not available.', 'zinckles-net-cart' ) . '</p>';
        }

        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Generate a consistent color for a shop badge based on blog_id.
     */
    private static function shop_color( $blog_id ) {
        $colors = array( '#7c3aed', '#4f46e5', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#84cc16', '#f97316' );
        return $colors[ $blog_id % count( $colors ) ];
    }
}
