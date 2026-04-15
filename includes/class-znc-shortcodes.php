<?php
/**
 * Shortcodes — v1.4.1
 *
 * Registers all Net Cart shortcodes with multiple variations.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Shortcodes {

    private static $host;

    public static function init( ZNC_Checkout_Host $host ) {
        self::$host = $host;

        // Primary shortcodes
        add_shortcode( 'znc_global_cart',    array( __CLASS__, 'render_global_cart' ) );
        add_shortcode( 'znc_checkout',       array( __CLASS__, 'render_checkout' ) );

        // Variation shortcodes
        add_shortcode( 'znc_cart_count',     array( __CLASS__, 'render_cart_count' ) );
        add_shortcode( 'znc_cart_total',     array( __CLASS__, 'render_cart_total' ) );
        add_shortcode( 'znc_shop_list',      array( __CLASS__, 'render_shop_list' ) );
        add_shortcode( 'znc_cart_button',    array( __CLASS__, 'render_cart_button' ) );
        add_shortcode( 'znc_points_balance', array( __CLASS__, 'render_points_balance' ) );
        add_shortcode( 'znc_mini_cart',      array( __CLASS__, 'render_mini_cart' ) );
        add_shortcode( 'znc_order_history',  array( __CLASS__, 'render_order_history' ) );

        // Enqueue front-end assets on pages with our shortcodes
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ) );
    }

    public static function maybe_enqueue() {
        global $post;
        if ( ! $post ) return;
        $shortcodes = array( 'znc_global_cart', 'znc_checkout', 'znc_mini_cart', 'znc_cart_button', 'znc_order_history' );
        foreach ( $shortcodes as $sc ) {
            if ( has_shortcode( $post->post_content, $sc ) ) {
                wp_enqueue_style( 'znc-front', ZNC_PLUGIN_URL . 'assets/css/znc-front.css', array(), ZNC_VERSION );
                wp_enqueue_script( 'znc-front', ZNC_PLUGIN_URL . 'assets/js/znc-front.js', array( 'jquery' ), ZNC_VERSION, true );
                wp_localize_script( 'znc-front', 'zncFront', array(
                    'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'znc_front' ),
                    'cartUrl'  => self::$host->get_cart_url(),
                    'checkoutUrl' => self::$host->get_checkout_url(),
                ) );
                break;
            }
        }
    }

    /* ── Primary: Global Cart ─────────────────────────────────── */

    public static function render_global_cart( $atts ) {
        $atts = shortcode_atts( array(
            'show_shop_badges' => 'yes',
            'show_thumbnails'  => 'yes',
            'layout'           => 'grouped', // grouped|flat|tabbed
        ), $atts );

        if ( ! is_user_logged_in() ) {
            return '<div class="znc-cart znc-cart-login"><p>Please <a href="' . wp_login_url( self::$host->get_cart_url() ) . '">log in</a> to view your cart.</p></div>';
        }

        $items = self::get_cart_items();

        ob_start();
        echo '<div class="znc-cart znc-layout-' . esc_attr( $atts['layout'] ) . '">';
        echo '<div class="znc-cart-header">';
        echo '<h2>🛒 Your Global Cart</h2>';
        echo '<a href="' . esc_url( self::$host->get_checkout_url() ) . '" class="znc-cart-nav">Shopping Cart → Checkout Details</a>';
        echo '</div>';

        if ( empty( $items ) ) {
            echo '<div class="znc-cart-empty">';
            echo '<p class="znc-empty-icon">🛒</p>';
            echo '<p>Your Global Cart is Empty</p>';
            echo '<p class="znc-empty-sub">Browse products on any shop in our network and add them to your cart.</p>';
            echo '<div class="znc-empty-shops"><strong>Browse Our Shops</strong><br>';
            $enrolled = self::$host->get_enrolled_ids();
            foreach ( $enrolled as $bid ) {
                $d = get_blog_details( $bid );
                if ( $d ) echo '<a href="' . esc_url( $d->siteurl . '/shop/' ) . '">' . esc_html( $d->blogname ) . '</a> ';
            }
            echo '</div></div>';
        } else {
            $grouped = array();
            foreach ( $items as $item ) {
                $grouped[ $item->shop_name ][] = $item;
            }

            $total_items = 0;
            $grand_total = 0;

            foreach ( $grouped as $shop => $shop_items ) {
                $first = $shop_items[0];
                echo '<div class="znc-shop-group">';
                echo '<div class="znc-shop-header">';
                echo '<span class="znc-shop-badge" style="background:' . self::shop_color( $shop ) . '">' . esc_html( mb_substr( $shop, 0, 1 ) ) . '</span>';
                echo '<strong>' . esc_html( $shop ) . '</strong>';
                echo '<span class="znc-shop-currency">' . esc_html( $first->currency ) . '</span>';
                echo '</div>';

                echo '<table class="znc-cart-table"><thead><tr>';
                if ( $atts['show_thumbnails'] === 'yes' ) echo '<th></th>';
                echo '<th>Product</th><th>Price</th><th>Qty</th><th>Total</th><th></th>';
                echo '</tr></thead><tbody>';

                foreach ( $shop_items as $item ) {
                    $line_total = $item->price * $item->quantity;
                    $total_items += $item->quantity;
                    $grand_total += $line_total;

                    echo '<tr data-item-id="' . esc_attr( $item->id ) . '">';
                    if ( $atts['show_thumbnails'] === 'yes' ) {
                        echo '<td class="znc-thumb">';
                        if ( $item->image_url ) echo '<img src="' . esc_url( $item->image_url ) . '" alt="" width="50">';
                        echo '</td>';
                    }
                    echo '<td><a href="' . esc_url( $item->permalink ) . '">' . esc_html( $item->product_name ) . '</a>';
                    if ( $item->sku ) echo '<br><small>SKU: ' . esc_html( $item->sku ) . '</small>';
                    echo '</td>';
                    echo '<td>' . esc_html( $item->currency ) . ' ' . number_format( $item->price, 2 ) . '</td>';
                    echo '<td>' . (int) $item->quantity . '</td>';
                    echo '<td>' . esc_html( $item->currency ) . ' ' . number_format( $line_total, 2 ) . '</td>';
                    echo '<td><button class="znc-remove-item" data-id="' . esc_attr( $item->id ) . '">✕</button></td>';
                    echo '</tr>';
                }
                echo '</tbody></table></div>';
            }

            echo '<div class="znc-cart-footer">';
            echo '<span class="znc-total-items">' . $total_items . ' items from ' . count( $grouped ) . ' shop(s)</span>';
            echo '<span class="znc-grand-total">Total: $' . number_format( $grand_total, 2 ) . '</span>';
            echo '<a href="' . esc_url( self::$host->get_checkout_url() ) . '" class="button znc-checkout-btn">Proceed to Checkout</a>';
            echo '</div>';
        }

        echo '</div>';
        return ob_get_clean();
    }

    /* ── Primary: Checkout ────────────────────────────────────── */

    public static function render_checkout( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p>Please <a href="' . wp_login_url() . '">log in</a> to checkout.</p>';
        }
        $items = self::get_cart_items();
        if ( empty( $items ) ) {
            return '<div class="znc-checkout-empty"><p>Your cart is empty. <a href="' . esc_url( self::$host->get_cart_url() ) . '">Go to cart</a></p></div>';
        }

        ob_start();
        echo '<div class="znc-checkout">';
        echo '<h2>🛒 Net Cart Checkout</h2>';
        echo '<p>Review your items from ' . count( array_unique( array_column( $items, 'shop_name' ) ) ) . ' shop(s) before placing your order.</p>';
        // Checkout form would go here — hooks into WC checkout
        echo '<p><em>Checkout integration in progress. Items are in your global cart.</em></p>';
        echo '</div>';
        return ob_get_clean();
    }

    /* ── Variation: Cart Count ────────────────────────────────── */

    public static function render_cart_count( $atts ) {
        $atts = shortcode_atts( array( 'icon' => 'yes', 'link' => 'yes' ), $atts );
        if ( ! is_user_logged_in() ) return '';
        $count = self::get_item_count();
        $out = '';
        if ( $atts['icon'] === 'yes' ) $out .= '🛒 ';
        $out .= '<span class="znc-global-cart-count">' . $count . '</span>';
        if ( $atts['link'] === 'yes' ) {
            $out = '<a href="' . esc_url( self::$host->get_cart_url() ) . '" class="znc-cart-count-link">' . $out . '</a>';
        }
        return $out;
    }

    /* ── Variation: Cart Total ────────────────────────────────── */

    public static function render_cart_total( $atts ) {
        $atts = shortcode_atts( array( 'currency' => '' ), $atts );
        if ( ! is_user_logged_in() ) return '';
        $items = self::get_cart_items();
        $total = 0;
        foreach ( $items as $item ) {
            $total += $item->price * $item->quantity;
        }
        $cur = $atts['currency'] ?: ( ! empty( $items ) ? $items[0]->currency : 'USD' );
        return '<span class="znc-cart-total">' . esc_html( $cur ) . ' ' . number_format( $total, 2 ) . '</span>';
    }

    /* ── Variation: Shop List ─────────────────────────────────── */

    public static function render_shop_list( $atts ) {
        $atts = shortcode_atts( array( 'style' => 'list' ), $atts ); // list|grid|badges
        $enrolled = self::$host->get_enrolled_ids();
        if ( empty( $enrolled ) ) return '<p>No shops available.</p>';

        $class = 'znc-shop-list znc-shop-' . esc_attr( $atts['style'] );
        $out = '<div class="' . $class . '">';
        foreach ( $enrolled as $bid ) {
            $d = get_blog_details( $bid );
            if ( ! $d ) continue;
            $out .= '<div class="znc-shop-item">';
            $out .= '<a href="' . esc_url( $d->siteurl . '/shop/' ) . '">';
            $out .= '<span class="znc-shop-badge" style="background:' . self::shop_color( $d->blogname ) . '">' . esc_html( mb_substr( $d->blogname, 0, 1 ) ) . '</span>';
            $out .= '<span class="znc-shop-name">' . esc_html( $d->blogname ) . '</span>';
            $out .= '</a></div>';
        }
        $out .= '</div>';
        return $out;
    }

    /* ── Variation: Cart Button ────────────────────────────────── */

    public static function render_cart_button( $atts ) {
        $atts = shortcode_atts( array( 'text' => 'View Global Cart', 'style' => 'button' ), $atts );
        $count = is_user_logged_in() ? self::get_item_count() : 0;
        $badge = $count > 0 ? ' <span class="znc-cart-badge">' . $count . '</span>' : '';
        $class = $atts['style'] === 'link' ? 'znc-cart-link' : 'button znc-cart-button';
        return '<a href="' . esc_url( self::$host->get_cart_url() ) . '" class="' . $class . '">🛒 ' . esc_html( $atts['text'] ) . $badge . '</a>';
    }

    /* ── Variation: Points Balance ─────────────────────────────── */

    public static function render_points_balance( $atts ) {
        $atts = shortcode_atts( array( 'type' => 'all' ), $atts ); // all|mycred|gamipress
        if ( ! is_user_logged_in() ) return '';
        $uid = get_current_user_id();
        $out = '<div class="znc-points-balance">';

        if ( in_array( $atts['type'], array( 'all', 'mycred' ), true ) && function_exists( 'mycred_get_types' ) ) {
            foreach ( mycred_get_types() as $slug => $label ) {
                $bal = mycred_get_users_balance( $uid, $slug );
                $out .= '<div class="znc-point-row"><span class="znc-point-label">' . esc_html( $label ) . '</span>';
                $out .= '<span class="znc-point-value">' . number_format( $bal, 0 ) . '</span></div>';
            }
        }

        if ( in_array( $atts['type'], array( 'all', 'gamipress' ), true ) && function_exists( 'gamipress_get_user_points' ) ) {
            $gp = ZNC_GamiPress_Engine::detect_all_types();
            foreach ( $gp as $slug => $info ) {
                $bal = gamipress_get_user_points( $uid, $slug );
                $out .= '<div class="znc-point-row"><span class="znc-point-label">' . esc_html( $info['label'] ) . '</span>';
                $out .= '<span class="znc-point-value">' . number_format( $bal, 0 ) . '</span></div>';
            }
        }

        $out .= '</div>';
        return $out;
    }

    /* ── Variation: Mini Cart ──────────────────────────────────── */

    public static function render_mini_cart( $atts ) {
        $atts = shortcode_atts( array( 'max' => 3 ), $atts );
        if ( ! is_user_logged_in() ) return '';
        $items = self::get_cart_items();
        $count = 0;
        foreach ( $items as $i ) $count += $i->quantity;

        $out = '<div class="znc-mini-cart">';
        $out .= '<div class="znc-mini-header">🛒 Net Cart <span class="znc-cart-badge">' . $count . '</span></div>';
        if ( empty( $items ) ) {
            $out .= '<p class="znc-mini-empty">Empty</p>';
        } else {
            $shown = 0;
            foreach ( $items as $item ) {
                if ( $shown >= (int) $atts['max'] ) break;
                $out .= '<div class="znc-mini-item">';
                if ( $item->image_url ) $out .= '<img src="' . esc_url( $item->image_url ) . '" width="30" height="30">';
                $out .= '<span>' . esc_html( $item->product_name ) . ' ×' . (int) $item->quantity . '</span>';
                $out .= '</div>';
                $shown++;
            }
            if ( count( $items ) > $shown ) {
                $out .= '<p class="znc-mini-more">+ ' . ( count( $items ) - $shown ) . ' more</p>';
            }
        }
        $out .= '<a href="' . esc_url( self::$host->get_cart_url() ) . '" class="znc-mini-link">View Full Cart →</a>';
        $out .= '</div>';
        return $out;
    }

    /* ── Variation: Order History ──────────────────────────────── */

    public static function render_order_history( $atts ) {
        $atts = shortcode_atts( array( 'max' => 10 ), $atts );
        if ( ! is_user_logged_in() ) return '<p>Please log in to view your orders.</p>';
        return '<div class="znc-order-history"><p>Order history coming in a future update.</p><a href="' . esc_url( self::$host->get_account_url() ) . '">Go to My Account →</a></div>';
    }

    /* ── Helpers ───────────────────────────────────────────────── */

    private static function get_cart_items() {
        global $wpdb;
        $host_id = self::$host->get_host_id();
        $current = get_current_blog_id();
        $sw      = ( (int) $current !== (int) $host_id );
        if ( $sw ) switch_to_blog( $host_id );
        $table = $wpdb->prefix . 'znc_global_cart';
        $items = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY shop_name, created_at DESC",
            get_current_user_id()
        ) );
        if ( $sw ) restore_current_blog();
        return $items ?: array();
    }

    private static function get_item_count() {
        $items = self::get_cart_items();
        $count = 0;
        foreach ( $items as $i ) $count += $i->quantity;
        return $count;
    }

    private static function shop_color( $name ) {
        $colors = array( '#7c3aed', '#2563eb', '#059669', '#d97706', '#dc2626', '#7c3aed', '#4f46e5' );
        $i = abs( crc32( $name ) ) % count( $colors );
        return $colors[ $i ];
    }
}
