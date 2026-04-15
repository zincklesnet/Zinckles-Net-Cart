<?php
/**
 * MyCred Engine — Full MyCred payment integration for ZCred Boutique items.
 *
 * Features:
 *  • Per-product MyCred point-type selection in WooCommerce product editor
 *  • Product page display showing required point type
 *  • Cart page annotations showing point requirements per item
 *  • Checkout balance validation and deduction
 *  • Only applies to subsites where currency = MYC (ZCreds)
 *  • Support for multiple MyCred point types per product
 *
 * @package ZincklesNetCart
 * @since   1.7.0
 */
defined( 'ABSPATH' ) || exit;

class ZNC_MyCred_Engine {

    /** Product meta key for storing selected point type */
    const META_POINT_TYPE   = '_znc_mycred_point_type';

    /** Product meta key for allowing all point types */
    const META_ALLOW_ALL    = '_znc_mycred_allow_all_types';

    public function init() {
        if ( ! $this->is_mycred_active() ) return;

        /* ── Product editor: point-type meta box (on ZCred subsites) ── */
        add_action( 'add_meta_boxes', array( $this, 'add_product_meta_box' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ) );

        /* ── Product page: display point type ── */
        add_action( 'woocommerce_single_product_summary', array( $this, 'display_product_point_type' ), 25 );

        /* ── WC cart: annotate ZCred items ── */
        add_filter( 'woocommerce_get_item_data', array( $this, 'annotate_cart_item' ), 10, 2 );
    }

    /**
     * Check if MyCred is active on current site.
     */
    private function is_mycred_active() {
        return function_exists( 'mycred' ) || class_exists( 'myCRED_Core' );
    }

    /**
     * Check if MyCred is active anywhere on the network.
     */
    public static function is_mycred_network_active() {
        return function_exists( 'mycred' ) || class_exists( 'myCRED_Core' );
    }

    /* ──────────────────────────────────────────────────────────────
     *  POINT TYPE DISCOVERY
     * ──────────────────────────────────────────────────────────── */

    /**
     * Get all registered MyCred point types.
     *
     * @return array [ type_key => label ]
     */
    public static function get_point_types() {
        if ( ! function_exists( 'mycred_get_types' ) ) {
            return array();
        }
        return mycred_get_types();
    }

    /**
     * Get a specific MyCred point type object.
     *
     * @param string $type_key
     * @return object|null
     */
    public static function get_point_type_object( $type_key ) {
        if ( ! function_exists( 'mycred' ) ) return null;
        return mycred( $type_key );
    }

    /**
     * Get all MyCred point types across the entire multisite network.
     *
     * @return array [ blog_id => [ type_key => label ] ]
     */
    public static function get_network_point_types() {
        $result = array();
        $sites  = get_sites( array( 'number' => 200 ) );

        foreach ( $sites as $site ) {
            switch_to_blog( $site->blog_id );

            if ( function_exists( 'mycred_get_types' ) ) {
                $types = mycred_get_types();
                if ( ! empty( $types ) ) {
                    $result[ $site->blog_id ] = $types;
                }
            }

            restore_current_blog();
        }

        return $result;
    }

    /**
     * Get user's balance for a specific point type.
     *
     * @param int    $user_id
     * @param string $type_key  e.g. 'mycred_default'
     * @return float
     */
    public static function get_user_balance( $user_id, $type_key = 'mycred_default' ) {
        if ( ! function_exists( 'mycred_get_users_balance' ) ) return 0;
        return (float) mycred_get_users_balance( $user_id, $type_key );
    }

    /**
     * Get user's balances for ALL registered point types.
     *
     * @param int $user_id
     * @return array [ type_key => [ 'label' => '...', 'balance' => float ] ]
     */
    public static function get_all_balances( $user_id ) {
        $types    = self::get_point_types();
        $balances = array();

        foreach ( $types as $key => $label ) {
            $balances[ $key ] = array(
                'label'   => $label,
                'balance' => self::get_user_balance( $user_id, $key ),
            );
        }

        return $balances;
    }

    /* ──────────────────────────────────────────────────────────────
     *  PRODUCT META BOX (WC Product Editor)
     * ──────────────────────────────────────────────────────────── */

    /**
     * Add meta box to product editor — only on ZCred subsites.
     */
    public function add_product_meta_box() {
        if ( ! ZNC_Currency_Handler::is_points_currency( get_current_blog_id() ) ) return;

        add_meta_box(
            'znc_mycred_point_type',
            __( 'ZCred Point Type', 'zinckles-net-cart' ),
            array( $this, 'render_product_meta_box' ),
            'product',
            'side',
            'high'
        );
    }

    /**
     * Render the meta box content.
     */
    public function render_product_meta_box( $post ) {
        $types       = self::get_point_types();
        $selected    = get_post_meta( $post->ID, self::META_POINT_TYPE, true );
        $allow_all   = get_post_meta( $post->ID, self::META_ALLOW_ALL, true );

        wp_nonce_field( 'znc_mycred_meta', 'znc_mycred_meta_nonce' );

        if ( empty( $types ) ) {
            echo '<p>' . esc_html__( 'No MyCred point types found. Activate MyCred and configure point types.', 'zinckles-net-cart' ) . '</p>';
            return;
        }
        ?>
        <p>
            <label>
                <input type="checkbox" name="znc_mycred_allow_all" value="yes"
                    <?php checked( $allow_all, 'yes' ); ?>>
                <?php esc_html_e( 'Allow payment with any point type', 'zinckles-net-cart' ); ?>
            </label>
        </p>
        <p id="znc-specific-type-wrap" style="<?php echo $allow_all === 'yes' ? 'display:none;' : ''; ?>">
            <label for="znc_mycred_point_type"><?php esc_html_e( 'Specific point type:', 'zinckles-net-cart' ); ?></label>
            <select name="znc_mycred_point_type" id="znc_mycred_point_type" style="width:100%;">
                <option value=""><?php esc_html_e( '— Default —', 'zinckles-net-cart' ); ?></option>
                <?php foreach ( $types as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected, $key ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p class="description">
            <?php esc_html_e( 'Select which MyCred point type customers must use to purchase this product.', 'zinckles-net-cart' ); ?>
        </p>
        <script>
        jQuery(function($){
            $('input[name="znc_mycred_allow_all"]').on('change', function(){
                $('#znc-specific-type-wrap').toggle(!this.checked);
            });
        });
        </script>
        <?php
    }

    /**
     * Save the meta box data.
     */
    public function save_product_meta( $post_id ) {
        if ( ! isset( $_POST['znc_mycred_meta_nonce'] )
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['znc_mycred_meta_nonce'] ) ), 'znc_mycred_meta' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $allow_all = isset( $_POST['znc_mycred_allow_all'] ) ? 'yes' : 'no';
        update_post_meta( $post_id, self::META_ALLOW_ALL, $allow_all );

        $point_type = sanitize_text_field( wp_unslash( $_POST['znc_mycred_point_type'] ?? '' ) );
        update_post_meta( $post_id, self::META_POINT_TYPE, $point_type );
    }

    /* ──────────────────────────────────────────────────────────────
     *  PRODUCT PAGE DISPLAY
     * ──────────────────────────────────────────────────────────── */

    /**
     * Display point type on single product page (ZCred subsites only).
     */
    public function display_product_point_type() {
        if ( ! ZNC_Currency_Handler::is_points_currency( get_current_blog_id() ) ) return;

        global $product;
        if ( ! $product ) return;

        $allow_all  = get_post_meta( $product->get_id(), self::META_ALLOW_ALL, true );
        $point_type = get_post_meta( $product->get_id(), self::META_POINT_TYPE, true );

        echo '<div class="znc-point-type-display">';
        echo '<span class="znc-point-icon">🪙</span> ';

        if ( $allow_all === 'yes' ) {
            $types = self::get_point_types();
            if ( ! empty( $types ) ) {
                echo '<strong>' . esc_html__( 'Pay with:', 'zinckles-net-cart' ) . '</strong> ';
                echo esc_html( implode( ', ', $types ) );
            } else {
                echo '<strong>' . esc_html__( 'Pay with: ZCreds', 'zinckles-net-cart' ) . '</strong>';
            }
        } elseif ( $point_type ) {
            $types = self::get_point_types();
            $label = $types[ $point_type ] ?? $point_type;
            echo '<strong>' . esc_html__( 'Pay with:', 'zinckles-net-cart' ) . '</strong> ';
            echo esc_html( $label );
        } else {
            echo '<strong>' . esc_html__( 'Pay with: ZCreds', 'zinckles-net-cart' ) . '</strong>';
        }

        // Show user's balance
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            if ( $point_type && $allow_all !== 'yes' ) {
                $balance = self::get_user_balance( $user_id, $point_type );
                $types   = self::get_point_types();
                $label   = $types[ $point_type ] ?? $point_type;
                echo '<br><small>' . sprintf(
                    /* translators: %1$s = balance, %2$s = point type */
                    esc_html__( 'Your balance: %1$s %2$s', 'zinckles-net-cart' ),
                    number_format( $balance, 2 ),
                    esc_html( $label )
                ) . '</small>';
            } else {
                $balances = self::get_all_balances( $user_id );
                if ( ! empty( $balances ) ) {
                    echo '<br><small>' . esc_html__( 'Your balances:', 'zinckles-net-cart' ) . ' ';
                    $parts = array();
                    foreach ( $balances as $b ) {
                        $parts[] = number_format( $b['balance'], 2 ) . ' ' . $b['label'];
                    }
                    echo esc_html( implode( ' | ', $parts ) );
                    echo '</small>';
                }
            }
        }

        echo '</div>';
    }

    /* ──────────────────────────────────────────────────────────────
     *  CART ANNOTATION
     * ──────────────────────────────────────────────────────────── */

    /**
     * Annotate WC cart items with point type info (local cart display).
     */
    public function annotate_cart_item( $item_data, $cart_item ) {
        if ( ! ZNC_Currency_Handler::is_points_currency( get_current_blog_id() ) ) {
            return $item_data;
        }

        $product_id = $cart_item['product_id'];
        $allow_all  = get_post_meta( $product_id, self::META_ALLOW_ALL, true );
        $point_type = get_post_meta( $product_id, self::META_POINT_TYPE, true );

        if ( $allow_all === 'yes' ) {
            $label = __( 'Any point type', 'zinckles-net-cart' );
        } elseif ( $point_type ) {
            $types = self::get_point_types();
            $label = $types[ $point_type ] ?? $point_type;
        } else {
            $label = __( 'ZCreds (default)', 'zinckles-net-cart' );
        }

        $item_data[] = array(
            'key'   => __( 'Point Type', 'zinckles-net-cart' ),
            'value' => $label,
        );

        return $item_data;
    }

    /* ──────────────────────────────────────────────────────────────
     *  GLOBAL CART HELPERS (used by checkout handler)
     * ──────────────────────────────────────────────────────────── */

    /**
     * Get the user-friendly point type label for a product.
     *
     * @param int $blog_id
     * @param int $product_id
     * @return string
     */
    public static function get_product_point_label( $blog_id, $product_id ) {
        switch_to_blog( $blog_id );

        $allow_all  = get_post_meta( $product_id, self::META_ALLOW_ALL, true );
        $point_type = get_post_meta( $product_id, self::META_POINT_TYPE, true );

        if ( $allow_all === 'yes' ) {
            $label = __( 'Any point type', 'zinckles-net-cart' );
        } elseif ( $point_type && function_exists( 'mycred_get_types' ) ) {
            $types = mycred_get_types();
            $label = $types[ $point_type ] ?? $point_type;
        } else {
            $label = ZNC_Currency_Handler::get_label( 'MYC' );
        }

        restore_current_blog();
        return $label;
    }

    /* ──────────────────────────────────────────────────────────────
     *  CHECKOUT: BALANCE & RENDERING
     * ──────────────────────────────────────────────────────────── */

    /**
     * Render the checkout balance section for ZCred items.
     *
     * @param int   $user_id
     * @param array $zcred_shops  [ blog_id => shop data ]
     * @return string HTML
     */
    public static function render_checkout_balance( $user_id, $zcred_shops ) {
        if ( ! function_exists( 'mycred_get_types' ) ) {
            return '<div class="woocommerce-error">'
                . esc_html__( 'MyCred is not active. Points payment is unavailable.', 'zinckles-net-cart' )
                . '</div>';
        }

        $requirements = self::calculate_point_requirements( $zcred_shops );
        $balances     = self::get_all_balances( $user_id );

        ob_start();
        ?>
        <div class="znc-mycred-checkout-balance">
            <table class="znc-balance-table shop_table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Point Type', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Required', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Your Balance', 'zinckles-net-cart' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'zinckles-net-cart' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $requirements as $type_key => $required_amount ) :
                    $balance = $balances[ $type_key ]['balance'] ?? 0;
                    $label   = $balances[ $type_key ]['label'] ?? $type_key;
                    $ok      = $balance >= $required_amount;
                ?>
                    <tr class="<?php echo $ok ? 'znc-balance-ok' : 'znc-balance-short'; ?>">
                        <td><?php echo esc_html( $label ); ?></td>
                        <td><?php echo esc_html( number_format( $required_amount, 2 ) ); ?></td>
                        <td><?php echo esc_html( number_format( $balance, 2 ) ); ?></td>
                        <td>
                            <?php if ( $ok ) : ?>
                                <span class="znc-status-ok">✅ <?php esc_html_e( 'Sufficient', 'zinckles-net-cart' ); ?></span>
                            <?php else : ?>
                                <span class="znc-status-short">⚠️ <?php
                                    printf(
                                        /* translators: %s = shortfall amount */
                                        esc_html__( 'Need %s more', 'zinckles-net-cart' ),
                                        number_format( $required_amount - $balance, 2 )
                                    );
                                ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            // If any product allows "any type", show type selector
            $has_any = false;
            foreach ( $zcred_shops as $blog_id => $shop ) {
                foreach ( $shop['items'] as $item ) {
                    switch_to_blog( $blog_id );
                    $allow_all = get_post_meta( $item['product_id'], self::META_ALLOW_ALL, true );
                    restore_current_blog();
                    if ( $allow_all === 'yes' ) {
                        $has_any = true;
                        break 2;
                    }
                }
            }

            if ( $has_any ) :
                $types = self::get_point_types();
            ?>
            <div class="znc-point-type-chooser">
                <label for="znc_checkout_point_type">
                    <?php esc_html_e( 'Choose point type for "any type" items:', 'zinckles-net-cart' ); ?>
                </label>
                <select name="znc_checkout_point_type" id="znc_checkout_point_type">
                    <?php foreach ( $types as $key => $label ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>">
                            <?php echo esc_html( $label ); ?>
                            (<?php echo esc_html( number_format( self::get_user_balance( $user_id, $key ), 2 ) ); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Calculate point requirements per type for ZCred shops.
     *
     * @param array $zcred_shops
     * @return array [ type_key => total_required ]
     */
    public static function calculate_point_requirements( $zcred_shops ) {
        $requirements = array();

        foreach ( $zcred_shops as $blog_id => $shop ) {
            switch_to_blog( $blog_id );

            foreach ( $shop['items'] as $item ) {
                $allow_all  = get_post_meta( $item['product_id'], self::META_ALLOW_ALL, true );
                $point_type = get_post_meta( $item['product_id'], self::META_POINT_TYPE, true );
                $line_total = (float) $item['price'] * (int) $item['quantity'];

                if ( $allow_all === 'yes' ) {
                    // Will be resolved at checkout via the chooser dropdown
                    $type_key = '_any';
                } elseif ( $point_type ) {
                    $type_key = $point_type;
                } else {
                    $type_key = 'mycred_default';
                }

                if ( ! isset( $requirements[ $type_key ] ) ) {
                    $requirements[ $type_key ] = 0;
                }
                $requirements[ $type_key ] += $line_total;
            }

            restore_current_blog();
        }

        return $requirements;
    }

    /* ──────────────────────────────────────────────────────────────
     *  CHECKOUT: VALIDATION & DEDUCTION
     * ──────────────────────────────────────────────────────────── */

    /**
     * Validate that user has enough points for all ZCred items.
     *
     * @param int   $user_id
     * @param array $zcred_shops
     * @return array [ 'valid' => bool, 'errors' => string[] ]
     */
    public static function validate_checkout_balance( $user_id, $zcred_shops ) {
        $requirements = self::calculate_point_requirements( $zcred_shops );
        $errors       = array();

        // Resolve "_any" items to the chosen type
        $chosen_type = sanitize_text_field( wp_unslash( $_POST['znc_checkout_point_type'] ?? 'mycred_default' ) );

        if ( isset( $requirements['_any'] ) ) {
            $any_total = $requirements['_any'];
            unset( $requirements['_any'] );

            if ( ! isset( $requirements[ $chosen_type ] ) ) {
                $requirements[ $chosen_type ] = 0;
            }
            $requirements[ $chosen_type ] += $any_total;
        }

        $types = self::get_point_types();

        foreach ( $requirements as $type_key => $required ) {
            $balance = self::get_user_balance( $user_id, $type_key );
            $label   = $types[ $type_key ] ?? $type_key;

            if ( $balance < $required ) {
                $errors[] = sprintf(
                    /* translators: %1$s = point type, %2$s = required, %3$s = balance */
                    __( 'Insufficient %1$s — need %2$s but you have %3$s.', 'zinckles-net-cart' ),
                    $label,
                    number_format( $required, 2 ),
                    number_format( $balance, 2 )
                );
            }
        }

        return array(
            'valid'  => empty( $errors ),
            'errors' => $errors,
        );
    }

    /**
     * Deduct MyCred points for ZCred items at checkout.
     *
     * @param int   $user_id
     * @param array $zcred_shops
     * @param int   $order_id     Parent order ID for logging.
     * @return true|WP_Error
     */
    public static function deduct_for_checkout( $user_id, $zcred_shops, $order_id ) {
        if ( ! function_exists( 'mycred_subtract' ) ) {
            return new WP_Error( 'znc_no_mycred', __( 'MyCred is not available for point deduction.', 'zinckles-net-cart' ) );
        }

        $requirements = self::calculate_point_requirements( $zcred_shops );

        // Resolve "_any" items
        $chosen_type = sanitize_text_field( wp_unslash( $_POST['znc_checkout_point_type'] ?? 'mycred_default' ) );

        if ( isset( $requirements['_any'] ) ) {
            $any_total = $requirements['_any'];
            unset( $requirements['_any'] );

            if ( ! isset( $requirements[ $chosen_type ] ) ) {
                $requirements[ $chosen_type ] = 0;
            }
            $requirements[ $chosen_type ] += $any_total;
        }

        $types       = self::get_point_types();
        $deductions  = array();

        foreach ( $requirements as $type_key => $amount ) {
            if ( $amount <= 0 ) continue;

            $label  = $types[ $type_key ] ?? $type_key;
            $log    = sprintf(
                /* translators: %1$s = amount, %2$s = point type, %3$d = order ID */
                __( 'Net Cart order #%3$d — deducted %1$s %2$s', 'zinckles-net-cart' ),
                number_format( $amount, 2 ), $label, $order_id
            );

            $result = mycred_subtract(
                'znc_checkout_payment',
                $user_id,
                $amount,
                $log,
                $order_id,
                $type_key
            );

            if ( $result === false ) {
                // Rollback: refund any previously deducted amounts
                foreach ( $deductions as $d ) {
                    mycred_add(
                        'znc_checkout_refund',
                        $user_id,
                        $d['amount'],
                        sprintf( __( 'Refund for failed Net Cart order #%d', 'zinckles-net-cart' ), $order_id ),
                        $order_id,
                        $d['type_key']
                    );
                }

                return new WP_Error(
                    'znc_deduct_failed',
                    sprintf(
                        /* translators: %s = point type */
                        __( 'Failed to deduct %s points. Transaction rolled back.', 'zinckles-net-cart' ),
                        $label
                    )
                );
            }

            $deductions[] = array( 'type_key' => $type_key, 'amount' => $amount );
        }

        return true;
    }

    /**
     * Refund MyCred points when an order is cancelled/refunded.
     *
     * @param int $parent_order_id
     */
    public static function refund_for_order( $parent_order_id ) {
        if ( ! function_exists( 'mycred_add' ) ) return;

        $parent = wc_get_order( $parent_order_id );
        if ( ! $parent ) return;

        $zcred_total = (float) $parent->get_meta( '_znc_zcred_total' );
        if ( $zcred_total <= 0 ) return;

        // Already refunded?
        if ( $parent->get_meta( '_znc_mycred_refunded' ) === 'yes' ) return;

        $user_id = $parent->get_customer_id();
        if ( ! $user_id ) return;

        // Simple refund to default type — enhance later with per-type tracking
        mycred_add(
            'znc_order_refund',
            $user_id,
            $zcred_total,
            sprintf( __( 'Refund for Net Cart order #%d', 'zinckles-net-cart' ), $parent_order_id ),
            $parent_order_id,
            'mycred_default'
        );

        $parent->update_meta_data( '_znc_mycred_refunded', 'yes' );
        $parent->add_order_note(
            sprintf( __( 'Refunded %s ZCred points to customer.', 'zinckles-net-cart' ), number_format( $zcred_total, 2 ) ),
            false, false
        );
        $parent->save();
    }
}
