<?php
/**
 * Widgets — All 5 widgets with bug fixes.
 *
 * Fixes from v1.6.1:
 *  • Cart Summary widget: uses switch_to_blog() to get real product names
 *  • Recent Items widget: uses switch_to_blog() to get real product names
 *  • All price displays use ZNC_Currency_Handler (no hardcoded '$')
 *
 * @package ZincklesNetCart
 * @since   1.7.0
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Widgets {

    /**
     * Register all widgets.
     */
    public static function register() {
        register_widget( 'ZNC_Widget_Cart_Badge' );
        register_widget( 'ZNC_Widget_Cart_Summary' );
        register_widget( 'ZNC_Widget_Shop_List' );
        register_widget( 'ZNC_Widget_Points_Balance' );
        register_widget( 'ZNC_Widget_Recent_Items' );
    }
}

/* ═══════════════════════════════════════════════════════════════
 *  1. Cart Badge — shows item count
 * ═══════════════════════════════════════════════════════════════ */
class ZNC_Widget_Cart_Badge extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'znc_cart_badge',
            __( 'Net Cart Badge', 'zinckles-net-cart' ),
            array( 'description' => __( 'Shows global cart item count.', 'zinckles-net-cart' ) )
        );
    }

    public function widget( $args, $instance ) {
        echo $args['before_widget']; // phpcs:ignore

        $title = apply_filters( 'widget_title', $instance['title'] ?? '' );
        if ( $title ) echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore

        $count = 0;
        if ( is_user_logged_in() ) {
            $cart  = ZNC_Global_Cart::instance();
            $count = $cart->get_item_count( get_current_user_id() );
        }

        $cart_url = ZNC_Checkout_Host::get_cart_url();
        echo '<a href="' . esc_url( $cart_url ) . '" class="znc-widget-badge">';
        echo '🛒 <span class="znc-cart-count">' . intval( $count ) . '</span>';
        echo '</a>';

        echo $args['after_widget']; // phpcs:ignore
    }

    public function form( $instance ) {
        $title = $instance['title'] ?? __( 'Cart', 'zinckles-net-cart' );
        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
                <?php esc_html_e( 'Title:', 'zinckles-net-cart' ); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
                   type="text" value="<?php echo esc_attr( $title ); ?>">
        </p>
        <?php
    }

    public function update( $new_instance, $old_instance ) {
        return array( 'title' => sanitize_text_field( $new_instance['title'] ?? '' ) );
    }
}

/* ═══════════════════════════════════════════════════════════════
 *  2. Cart Summary — FIXED: uses switch_to_blog() for product names
 * ═══════════════════════════════════════════════════════════════ */
class ZNC_Widget_Cart_Summary extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'znc_cart_summary',
            __( 'Net Cart Summary', 'zinckles-net-cart' ),
            array( 'description' => __( 'Shows cart items with names and prices.', 'zinckles-net-cart' ) )
        );
    }

    public function widget( $args, $instance ) {
        if ( ! is_user_logged_in() ) return;

        echo $args['before_widget']; // phpcs:ignore

        $title = apply_filters( 'widget_title', $instance['title'] ?? '' );
        if ( $title ) echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore

        $user_id  = get_current_user_id();
        $cart     = ZNC_Global_Cart::instance();
        $items    = $cart->get_cart( $user_id );

        if ( empty( $items ) ) {
            echo '<p class="znc-widget-empty">' . esc_html__( 'Your cart is empty.', 'zinckles-net-cart' ) . '</p>';
            echo $args['after_widget']; // phpcs:ignore
            return;
        }

        $max_items = absint( $instance['max_items'] ?? 5 );

        echo '<ul class="znc-widget-cart-list">';

        $shown = 0;
        $blogs = $cart->get_items_by_blog( $user_id );

        foreach ( $blogs as $blog_id => $blog_items ) {
            switch_to_blog( $blog_id );

            $curr = ZNC_Currency_Handler::get_blog_currency( $blog_id );

            foreach ( $blog_items as $item ) {
                if ( $shown >= $max_items ) break 2;
                $shown++;

                // FIX: Get actual product name via switch_to_blog
                $pid     = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];
                $product = function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;
                $name    = $product ? $product->get_name() : sprintf( __( 'Product #%d', 'zinckles-net-cart' ), $pid );
                $price   = $product ? (float) $product->get_price() : 0;

                echo '<li class="znc-widget-cart-item">';
                echo '<span class="znc-widget-item-name">' . esc_html( $name ) . '</span>';
                echo ' <span class="znc-widget-item-qty">&times;' . intval( $item['quantity'] ) . '</span>';
                if ( $price > 0 ) {
                    echo ' <span class="znc-widget-item-price">'
                        . esc_html( ZNC_Currency_Handler::format_price( $price * $item['quantity'], $curr['symbol'] ) )
                        . '</span>';
                }
                echo '</li>';
            }

            restore_current_blog();
        }

        echo '</ul>';

        // Total
        $renderer = new ZNC_Cart_Renderer();
        $enriched = $renderer->get_enriched_cart( $user_id );
        if ( ! empty( $enriched ) ) {
            echo '<div class="znc-widget-total">';
            echo '<strong>' . esc_html__( 'Total:', 'zinckles-net-cart' ) . '</strong> ';
            echo ZNC_Currency_Handler::render_grand_total_html( $enriched ); // phpcs:ignore
            echo '</div>';
        }

        $cart_url = ZNC_Checkout_Host::get_cart_url();
        echo '<a href="' . esc_url( $cart_url ) . '" class="button znc-widget-view-cart">'
            . esc_html__( 'View Cart', 'zinckles-net-cart' ) . '</a>';

        echo $args['after_widget']; // phpcs:ignore
    }

    public function form( $instance ) {
        $title     = $instance['title'] ?? __( 'Cart Summary', 'zinckles-net-cart' );
        $max_items = $instance['max_items'] ?? 5;
        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
                <?php esc_html_e( 'Title:', 'zinckles-net-cart' ); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
                   type="text" value="<?php echo esc_attr( $title ); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'max_items' ) ); ?>">
                <?php esc_html_e( 'Max items to show:', 'zinckles-net-cart' ); ?>
            </label>
            <input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'max_items' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'max_items' ) ); ?>"
                   type="number" min="1" max="20" value="<?php echo intval( $max_items ); ?>">
        </p>
        <?php
    }

    public function update( $new_instance, $old_instance ) {
        return array(
            'title'     => sanitize_text_field( $new_instance['title'] ?? '' ),
            'max_items' => absint( $new_instance['max_items'] ?? 5 ),
        );
    }
}

/* ═══════════════════════════════════════════════════════════════
 *  3. Shop List — shows enrolled shops
 * ═══════════════════════════════════════════════════════════════ */
class ZNC_Widget_Shop_List extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'znc_shop_list',
            __( 'Net Cart Shop List', 'zinckles-net-cart' ),
            array( 'description' => __( 'Lists all enrolled shops.', 'zinckles-net-cart' ) )
        );
    }

    public function widget( $args, $instance ) {
        echo $args['before_widget']; // phpcs:ignore

        $title = apply_filters( 'widget_title', $instance['title'] ?? '' );
        if ( $title ) echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore

        $settings      = get_site_option( 'znc_network_settings', array() );
        $enrolled_sites = (array) ( $settings['enrolled_sites'] ?? array() );

        if ( empty( $enrolled_sites ) ) {
            echo '<p>' . esc_html__( 'No shops available.', 'zinckles-net-cart' ) . '</p>';
        } else {
            echo '<ul class="znc-widget-shop-list">';
            foreach ( $enrolled_sites as $blog_id ) {
                $details = get_blog_details( $blog_id );
                if ( ! $details ) continue;
                $curr = ZNC_Currency_Handler::get_blog_currency( $blog_id );
                echo '<li>';
                echo '<a href="' . esc_url( $details->siteurl ) . '">' . esc_html( $details->blogname ) . '</a>';
                echo ' <small>(' . esc_html( $curr['label'] ) . ')</small>';
                echo '</li>';
            }
            echo '</ul>';
        }

        echo $args['after_widget']; // phpcs:ignore
    }

    public function form( $instance ) {
        $title = $instance['title'] ?? __( 'Our Shops', 'zinckles-net-cart' );
        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
                <?php esc_html_e( 'Title:', 'zinckles-net-cart' ); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
                   type="text" value="<?php echo esc_attr( $title ); ?>">
        </p>
        <?php
    }

    public function update( $new_instance, $old_instance ) {
        return array( 'title' => sanitize_text_field( $new_instance['title'] ?? '' ) );
    }
}

/* ═══════════════════════════════════════════════════════════════
 *  4. Points Balance — shows MyCred + GamiPress
 * ═══════════════════════════════════════════════════════════════ */
class ZNC_Widget_Points_Balance extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'znc_points_balance',
            __( 'Net Cart Points Balance', 'zinckles-net-cart' ),
            array( 'description' => __( 'Shows MyCred and GamiPress point balances.', 'zinckles-net-cart' ) )
        );
    }

    public function widget( $args, $instance ) {
        if ( ! is_user_logged_in() ) return;

        echo $args['before_widget']; // phpcs:ignore

        $title = apply_filters( 'widget_title', $instance['title'] ?? '' );
        if ( $title ) echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore

        $user_id = get_current_user_id();

        // MyCred
        if ( function_exists( 'mycred_get_types' ) ) {
            $types = mycred_get_types();
            if ( ! empty( $types ) ) {
                echo '<div class="znc-widget-mycred">';
                echo '<h5>' . esc_html__( 'MyCred', 'zinckles-net-cart' ) . '</h5>';
                echo '<ul>';
                foreach ( $types as $key => $label ) {
                    $balance = ZNC_MyCred_Engine::get_user_balance( $user_id, $key );
                    echo '<li>' . esc_html( $label ) . ': <strong>' . esc_html( number_format( $balance, 2 ) ) . '</strong></li>';
                }
                echo '</ul></div>';
            }
        }

        // GamiPress
        if ( function_exists( 'gamipress_get_user_points' ) && post_type_exists( 'points-type' ) ) {
            $gami_types = get_posts( array(
                'post_type'   => 'points-type',
                'numberposts' => 50,
                'post_status' => 'publish',
            ) );
            if ( ! empty( $gami_types ) ) {
                echo '<div class="znc-widget-gamipress">';
                echo '<h5>' . esc_html__( 'GamiPress', 'zinckles-net-cart' ) . '</h5>';
                echo '<ul>';
                foreach ( $gami_types as $type ) {
                    $balance = gamipress_get_user_points( $user_id, $type->post_name );
                    echo '<li>' . esc_html( $type->post_title ) . ': <strong>' . esc_html( number_format( $balance ) ) . '</strong></li>';
                }
                echo '</ul></div>';
            }
        }

        echo $args['after_widget']; // phpcs:ignore
    }

    public function form( $instance ) {
        $title = $instance['title'] ?? __( 'Points Balance', 'zinckles-net-cart' );
        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
                <?php esc_html_e( 'Title:', 'zinckles-net-cart' ); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
                   type="text" value="<?php echo esc_attr( $title ); ?>">
        </p>
        <?php
    }

    public function update( $new_instance, $old_instance ) {
        return array( 'title' => sanitize_text_field( $new_instance['title'] ?? '' ) );
    }
}

/* ═══════════════════════════════════════════════════════════════
 *  5. Recent Items — FIXED: uses switch_to_blog() for product names
 * ═══════════════════════════════════════════════════════════════ */
class ZNC_Widget_Recent_Items extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'znc_recent_items',
            __( 'Net Cart Recent Items', 'zinckles-net-cart' ),
            array( 'description' => __( 'Shows recently added cart items.', 'zinckles-net-cart' ) )
        );
    }

    public function widget( $args, $instance ) {
        if ( ! is_user_logged_in() ) return;

        echo $args['before_widget']; // phpcs:ignore

        $title = apply_filters( 'widget_title', $instance['title'] ?? '' );
        if ( $title ) echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore

        $user_id   = get_current_user_id();
        $cart      = ZNC_Global_Cart::instance();
        $all_items = $cart->get_cart( $user_id );

        if ( empty( $all_items ) ) {
            echo '<p class="znc-widget-empty">' . esc_html__( 'No items yet.', 'zinckles-net-cart' ) . '</p>';
            echo $args['after_widget']; // phpcs:ignore
            return;
        }

        // Sort by added_at descending
        usort( $all_items, function( $a, $b ) {
            return ( $b['added_at'] ?? 0 ) - ( $a['added_at'] ?? 0 );
        } );

        $max_items = absint( $instance['max_items'] ?? 3 );
        $shown     = 0;

        echo '<ul class="znc-widget-recent-list">';

        foreach ( $all_items as $item ) {
            if ( $shown >= $max_items ) break;
            $shown++;

            $blog_id = $item['blog_id'];
            switch_to_blog( $blog_id );

            $curr    = ZNC_Currency_Handler::get_blog_currency( $blog_id );
            $pid     = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];
            $product = function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;

            // FIX: Get actual product name instead of "Product #123"
            $name    = $product ? $product->get_name() : sprintf( __( 'Product #%d', 'zinckles-net-cart' ), $pid );
            $price   = $product ? (float) $product->get_price() : 0;
            $image   = '';
            if ( $product ) {
                $img_id = $product->get_image_id();
                $image  = $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '';
            }

            restore_current_blog();

            echo '<li class="znc-widget-recent-item">';
            if ( $image ) {
                echo '<img src="' . esc_url( $image ) . '" alt="" width="40" height="40" class="znc-widget-thumb">';
            }
            echo '<span class="znc-widget-item-name">' . esc_html( $name ) . '</span>';
            if ( $price > 0 ) {
                echo ' <span class="znc-widget-item-price">'
                    . esc_html( ZNC_Currency_Handler::format_price( $price, $curr['symbol'] ) )
                    . '</span>';
            }
            echo '</li>';
        }

        echo '</ul>';
        echo $args['after_widget']; // phpcs:ignore
    }

    public function form( $instance ) {
        $title     = $instance['title'] ?? __( 'Recently Added', 'zinckles-net-cart' );
        $max_items = $instance['max_items'] ?? 3;
        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
                <?php esc_html_e( 'Title:', 'zinckles-net-cart' ); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
                   type="text" value="<?php echo esc_attr( $title ); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'max_items' ) ); ?>">
                <?php esc_html_e( 'Max items:', 'zinckles-net-cart' ); ?>
            </label>
            <input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'max_items' ) ); ?>"
                   name="<?php echo esc_attr( $this->get_field_name( 'max_items' ) ); ?>"
                   type="number" min="1" max="10" value="<?php echo intval( $max_items ); ?>">
        </p>
        <?php
    }

    public function update( $new_instance, $old_instance ) {
        return array(
            'title'     => sanitize_text_field( $new_instance['title'] ?? '' ),
            'max_items' => absint( $new_instance['max_items'] ?? 3 ),
        );
    }
}
