<?php
/**
 * Cart Sync — v1.4.1 NEW
 *
 * Replaces the WooCommerce cart count in header/nav menus with
 * the global Net Cart count. Works on both checkout host and enrolled subsites.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Cart_Sync {

    /** @var ZNC_Checkout_Host */
    private $host;

    public function __construct( ZNC_Checkout_Host $host ) {
        $this->host = $host;
    }

    public function init() {
        // Replace WC cart fragments with global count
        add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'global_cart_fragments' ), 99 );

        // Override WC cart count function
        add_filter( 'woocommerce_cart_contents_count', array( $this, 'override_cart_count' ), 99 );

        // Inject global cart count CSS + JS on front-end
        add_action( 'wp_footer', array( $this, 'inject_cart_sync_script' ) );

        // Add global cart count to menu items
        add_filter( 'wp_nav_menu_items', array( $this, 'add_global_cart_indicator' ), 20, 2 );
    }

    /**
     * Get global cart count for current user from the host DB.
     * Uses a per-request static cache + short transient.
     */
    public function get_global_count() {
        static $count = null;
        if ( null !== $count ) return $count;

        if ( ! is_user_logged_in() ) {
            $count = 0;
            return $count;
        }

        $user_id   = get_current_user_id();
        $cache_key = 'znc_cart_count_' . $user_id;
        $cached    = get_site_transient( $cache_key );

        if ( false !== $cached ) {
            $count = (int) $cached;
            return $count;
        }

        global $wpdb;
        $host_id     = $this->host->get_host_id();
        $current     = get_current_blog_id();
        $need_switch = ( (int) $current !== (int) $host_id );

        if ( $need_switch ) switch_to_blog( $host_id );

        $table = $wpdb->prefix . 'znc_global_cart';
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(quantity), 0) FROM {$table} WHERE user_id = %d",
            $user_id
        ) );

        if ( $need_switch ) restore_current_blog();

        set_site_transient( $cache_key, $count, 60 ); // 1 min cache
        return $count;
    }

    /**
     * Override WC cart count with global count.
     */
    public function override_cart_count( $count ) {
        $global = $this->get_global_count();
        return $global > 0 ? $global : $count;
    }

    /**
     * Add global cart count to WC AJAX fragments.
     */
    public function global_cart_fragments( $fragments ) {
        $count = $this->get_global_count();
        $fragments['.znc-global-cart-count'] = '<span class="znc-global-cart-count">' . $count . '</span>';
        $fragments['.znc-cart-badge']        = '<span class="znc-cart-badge" data-count="' . $count . '">' . $count . '</span>';

        // Also update WC's default cart count span
        $fragments['span.cart-items-count'] = '<span class="cart-items-count">' . $count . '</span>';
        $fragments['.cart-contents .count'] = '<span class="count">' . $count . '</span>';

        return $fragments;
    }

    /**
     * Inject JS that syncs the cart badge across themes.
     */
    public function inject_cart_sync_script() {
        if ( ! is_user_logged_in() ) return;
        $count    = $this->get_global_count();
        $cart_url = esc_url( $this->host->get_cart_url() );
        ?>
        <style>
            .znc-cart-badge{background:#7c3aed;color:#fff;border-radius:50%;font-size:11px;
                font-weight:700;min-width:18px;height:18px;display:inline-flex;
                align-items:center;justify-content:center;padding:0 4px;margin-left:4px}
            .znc-global-cart-link{text-decoration:none;display:inline-flex;align-items:center;gap:4px}
        </style>
        <script>
        (function(){
            var count = <?php echo (int) $count; ?>;
            var cartUrl = '<?php echo $cart_url; ?>';

            // Update any existing cart count badges
            document.querySelectorAll('.cart-items-count, .cart-count, .count, .wc-cart-count, .header-cart-count').forEach(function(el){
                if(count > 0) el.textContent = count;
            });

            // Update cart links to point to global cart
            document.querySelectorAll('a.cart-contents, a[href*="/cart/"], .cart-icon a, .header-cart a').forEach(function(el){
                el.setAttribute('href', cartUrl);
                el.setAttribute('title', 'View Global Net Cart (' + count + ' items)');
            });
        })();
        </script>
        <?php
    }

    /**
     * Optionally add a global cart indicator to nav menus.
     */
    public function add_global_cart_indicator( $items, $args ) {
        // Only add to primary/main menu
        if ( ! in_array( $args->theme_location, array( 'primary', 'main', 'header', 'primary-menu' ), true ) ) {
            return $items;
        }

        if ( ! is_user_logged_in() ) return $items;

        $count    = $this->get_global_count();
        $cart_url = esc_url( $this->host->get_cart_url() );

        if ( $count > 0 ) {
            $items .= '<li class="menu-item znc-menu-cart-item">';
            $items .= '<a href="' . $cart_url . '" class="znc-global-cart-link">';
            $items .= '🛒 <span class="znc-cart-badge">' . $count . '</span>';
            $items .= '</a></li>';
        }

        return $items;
    }

    /**
     * Invalidate the cart count cache for a user.
     */
    public static function invalidate( $user_id ) {
        delete_site_transient( 'znc_cart_count_' . $user_id );
    }
}
