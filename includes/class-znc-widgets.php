<?php
/**
 * Widgets — v1.4.1 NEW
 *
 * Registers WordPress widgets and Elementor-compatible widgets for Net Cart.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Widgets {

    /** @var ZNC_Checkout_Host */
    private static $host;

    public static function init( ZNC_Checkout_Host $host ) {
        self::$host = $host;
        add_action( 'widgets_init', array( __CLASS__, 'register_widgets' ) );
    }

    public static function register_widgets() {
        register_widget( 'ZNC_Widget_Cart_Badge' );
        register_widget( 'ZNC_Widget_Cart_Summary' );
        register_widget( 'ZNC_Widget_Shop_List' );
        register_widget( 'ZNC_Widget_Points_Balance' );
    }

    public static function get_host() {
        return self::$host;
    }
}

/* ──────────────────────────────────────────────────────────────────
 * Widget 1: Cart Badge — shows global cart count with link
 * ────────────────────────────────────────────────────────────────── */
class ZNC_Widget_Cart_Badge extends WP_Widget {
    public function __construct() {
        parent::__construct( 'znc_cart_badge', '🛒 Net Cart Badge', array(
            'description' => 'Shows global Net Cart item count with link to cart.',
        ) );
    }

    public function widget( $args, $instance ) {
        if ( ! is_user_logged_in() ) return;
        $host  = ZNC_Widgets::get_host();
        $url   = $host->get_cart_url();
        $title = ! empty( $instance['title'] ) ? $instance['title'] : 'Global Cart';
        $count = self::get_count( $host );

        echo $args['before_widget'];
        if ( $title ) echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
        echo '<div class="znc-widget-cart-badge">';
        echo '<a href="' . esc_url( $url ) . '" class="znc-widget-cart-link">';
        echo '<span class="znc-widget-cart-icon">🛒</span>';
        echo '<span class="znc-global-cart-count znc-cart-badge">' . $count . '</span>';
        echo '</a></div>';
        echo $args['after_widget'];
    }

    public function form( $instance ) {
        $title = isset( $instance['title'] ) ? $instance['title'] : 'Global Cart';
        echo '<p><label>Title: <input class="widefat" type="text" name="' . $this->get_field_name('title') . '" value="' . esc_attr($title) . '"></label></p>';
    }

    public function update( $new, $old ) {
        return array( 'title' => sanitize_text_field( $new['title'] ) );
    }

    private static function get_count( $host ) {
        global $wpdb;
        $hid = $host->get_host_id();
        $cur = get_current_blog_id();
        $sw  = ( (int) $cur !== (int) $hid );
        if ( $sw ) switch_to_blog( $hid );
        $t = $wpdb->prefix . 'znc_global_cart';
        $c = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(quantity),0) FROM {$t} WHERE user_id=%d", get_current_user_id()
        ) );
        if ( $sw ) restore_current_blog();
        return $c;
    }
}

/* ──────────────────────────────────────────────────────────────────
 * Widget 2: Cart Summary — shows items grouped by shop
 * ────────────────────────────────────────────────────────────────── */
class ZNC_Widget_Cart_Summary extends WP_Widget {
    public function __construct() {
        parent::__construct( 'znc_cart_summary', '🛍 Net Cart Summary', array(
            'description' => 'Shows global cart summary with shop breakdown.',
        ) );
    }

    public function widget( $args, $instance ) {
        if ( ! is_user_logged_in() ) return;
        $host  = ZNC_Widgets::get_host();
        $items = self::get_items( $host );
        $title = ! empty( $instance['title'] ) ? $instance['title'] : 'Your Cart';
        $max   = ! empty( $instance['max_items'] ) ? (int) $instance['max_items'] : 5;

        echo $args['before_widget'];
        if ( $title ) echo $args['before_title'] . esc_html( $title ) . $args['after_title'];

        if ( empty( $items ) ) {
            echo '<p class="znc-widget-empty">Your global cart is empty.</p>';
        } else {
            $grouped = array();
            foreach ( $items as $item ) {
                $grouped[ $item->shop_name ][] = $item;
            }
            echo '<div class="znc-widget-cart-summary">';
            $shown = 0;
            foreach ( $grouped as $shop => $shop_items ) {
                echo '<div class="znc-widget-shop"><strong>' . esc_html( $shop ) . '</strong>';
                echo '<ul>';
                foreach ( $shop_items as $si ) {
                    if ( $shown >= $max ) break;
                    echo '<li>' . esc_html( $si->product_name ) . ' × ' . (int) $si->quantity;
                    echo ' <span class="znc-widget-price">' . esc_html( $si->currency ) . ' ' . number_format( $si->price * $si->quantity, 2 ) . '</span>';
                    echo '</li>';
                    $shown++;
                }
                echo '</ul></div>';
                if ( $shown >= $max ) break;
            }
            $total = count( $items );
            if ( $total > $max ) {
                echo '<p class="znc-widget-more">+ ' . ( $total - $max ) . ' more items</p>';
            }
            echo '<a href="' . esc_url( $host->get_cart_url() ) . '" class="button znc-widget-view-cart">View Full Cart</a>';
            echo '</div>';
        }
        echo $args['after_widget'];
    }

    public function form( $instance ) {
        $title = isset( $instance['title'] ) ? $instance['title'] : 'Your Cart';
        $max   = isset( $instance['max_items'] ) ? $instance['max_items'] : 5;
        echo '<p><label>Title: <input class="widefat" type="text" name="' . $this->get_field_name('title') . '" value="' . esc_attr($title) . '"></label></p>';
        echo '<p><label>Max items: <input class="tiny-text" type="number" name="' . $this->get_field_name('max_items') . '" value="' . esc_attr($max) . '" min="1" max="20"></label></p>';
    }

    public function update( $new, $old ) {
        return array( 'title' => sanitize_text_field( $new['title'] ), 'max_items' => absint( $new['max_items'] ) );
    }

    private static function get_items( $host ) {
        global $wpdb;
        $hid = $host->get_host_id();
        $cur = get_current_blog_id();
        $sw  = ( (int) $cur !== (int) $hid );
        if ( $sw ) switch_to_blog( $hid );
        $t = $wpdb->prefix . 'znc_global_cart';
        $items = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$t} WHERE user_id = %d ORDER BY shop_name, created_at DESC",
            get_current_user_id()
        ) );
        if ( $sw ) restore_current_blog();
        return $items ?: array();
    }
}

/* ──────────────────────────────────────────────────────────────────
 * Widget 3: Shop List — enrolled shops with product counts
 * ────────────────────────────────────────────────────────────────── */
class ZNC_Widget_Shop_List extends WP_Widget {
    public function __construct() {
        parent::__construct( 'znc_shop_list', '🏪 Net Cart Shops', array(
            'description' => 'Lists all enrolled shops in the network.',
        ) );
    }

    public function widget( $args, $instance ) {
        $host    = ZNC_Widgets::get_host();
        $enrolled = $host->get_enrolled_ids();
        $title   = ! empty( $instance['title'] ) ? $instance['title'] : 'Our Shops';

        echo $args['before_widget'];
        if ( $title ) echo $args['before_title'] . esc_html( $title ) . $args['after_title'];

        if ( empty( $enrolled ) ) {
            echo '<p>No shops available yet.</p>';
        } else {
            echo '<ul class="znc-widget-shop-list">';
            foreach ( $enrolled as $bid ) {
                $details = get_blog_details( $bid );
                if ( ! $details ) continue;
                $shop_url = $details->siteurl . '/shop/';
                echo '<li><a href="' . esc_url( $shop_url ) . '">' . esc_html( $details->blogname ) . '</a></li>';
            }
            echo '</ul>';
        }
        echo $args['after_widget'];
    }

    public function form( $instance ) {
        $title = isset( $instance['title'] ) ? $instance['title'] : 'Our Shops';
        echo '<p><label>Title: <input class="widefat" type="text" name="' . $this->get_field_name('title') . '" value="' . esc_attr($title) . '"></label></p>';
    }

    public function update( $new, $old ) {
        return array( 'title' => sanitize_text_field( $new['title'] ) );
    }
}

/* ──────────────────────────────────────────────────────────────────
 * Widget 4: Points Balance — MyCred + GamiPress balances
 * ────────────────────────────────────────────────────────────────── */
class ZNC_Widget_Points_Balance extends WP_Widget {
    public function __construct() {
        parent::__construct( 'znc_points_balance', '⚡ Points Balance', array(
            'description' => 'Shows user MyCred and GamiPress point balances.',
        ) );
    }

    public function widget( $args, $instance ) {
        if ( ! is_user_logged_in() ) return;
        $title   = ! empty( $instance['title'] ) ? $instance['title'] : 'Your Points';
        $user_id = get_current_user_id();

        echo $args['before_widget'];
        if ( $title ) echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
        echo '<div class="znc-widget-points">';

        // MyCred balances
        if ( function_exists( 'mycred_get_types' ) ) {
            $types = mycred_get_types();
            foreach ( $types as $slug => $label ) {
                $balance = (float) mycred_get_users_balance( $user_id, $slug );
                echo '<div class="znc-point-row">';
                echo '<span class="znc-point-label">' . esc_html( $label ) . '</span>';
                echo '<span class="znc-point-value">' . number_format( $balance, 0 ) . '</span>';
                echo '</div>';
            }
        }

        // GamiPress balances
        if ( function_exists( 'gamipress_get_user_points' ) ) {
            $gp_types = ZNC_GamiPress_Engine::detect_all_types();
            foreach ( $gp_types as $slug => $info ) {
                $balance = gamipress_get_user_points( $user_id, $slug );
                echo '<div class="znc-point-row">';
                echo '<span class="znc-point-label">' . esc_html( $info['label'] ) . '</span>';
                echo '<span class="znc-point-value">' . number_format( $balance, 0 ) . '</span>';
                echo '</div>';
            }
        }

        if ( ! function_exists( 'mycred_get_types' ) && ! function_exists( 'gamipress_get_user_points' ) ) {
            echo '<p class="znc-widget-empty">No points system detected.</p>';
        }

        echo '</div>';
        echo $args['after_widget'];
    }

    public function form( $instance ) {
        $title = isset( $instance['title'] ) ? $instance['title'] : 'Your Points';
        echo '<p><label>Title: <input class="widefat" type="text" name="' . $this->get_field_name('title') . '" value="' . esc_attr($title) . '"></label></p>';
    }

    public function update( $new, $old ) {
        return array( 'title' => sanitize_text_field( $new['title'] ) );
    }
}
