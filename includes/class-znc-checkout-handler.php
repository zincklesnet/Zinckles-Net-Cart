<?php
/**
 * Checkout Handler — Complete rewrite with payment gateway support.
 *
 * Fixes:
 *  • Redirect loop (suppresses WC template_redirect on ZNC checkout page)
 *  • No-payment bug (integrates WC payment gateways properly)
 *  • Hardcoded '$' (uses ZNC_Currency_Handler throughout)
 *  • Mixed-currency grand total (separate totals per currency)
 *  • MyCred point payment for ZCred Boutique items
 *
 * @package ZincklesNetCart
 * @since   1.7.0
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Checkout_Handler {

    /** @var ZNC_Global_Cart */
    private $cart;

    /** @var ZNC_Cart_Renderer */
    private $renderer;

    public function __construct() {
        $this->cart     = ZNC_Global_Cart::instance();
        $this->renderer = new ZNC_Cart_Renderer();
    }

    public function init() {
        /* ── Suppress WC's empty-cart redirect on the ZNC checkout page ── */
        add_action( 'template_redirect', array( $this, 'suppress_wc_redirect' ), 0 );

        /* ── Form POST handler (priority 20 so WC hooks run first) ── */
        add_action( 'template_redirect', array( $this, 'maybe_process_post' ), 20 );

        /* ── AJAX fallback ── */
        add_action( 'wp_ajax_znc_process_checkout', array( $this, 'ajax_process_checkout' ) );

        /* ── Mark child orders paid when parent completes ── */
        add_action( 'woocommerce_payment_complete', array( 'ZNC_Order_Factory', 'complete_child_orders' ) );
        add_action( 'woocommerce_order_status_processing', array( 'ZNC_Order_Factory', 'complete_child_orders' ) );
        add_action( 'woocommerce_order_status_completed', array( 'ZNC_Order_Factory', 'complete_child_orders' ) );
    }

    /* ──────────────────────────────────────────────────────────────
     *  REDIRECT SUPPRESSION
     * ──────────────────────────────────────────────────────────── */

    /**
     * Prevent WooCommerce from redirecting away from the ZNC checkout page.
     *
     * WC checks is_checkout() + cart-empty and redirects to the shop page.
     * Our page is NOT WC's checkout page, but some themes/plugins trigger
     * similar redirects. We also prevent WC_Form_Handler interference.
     */
    public function suppress_wc_redirect() {
        if ( ! $this->is_znc_checkout_page() ) return;

        // Remove WC's cart-empty redirect (fires on is_checkout() pages)
        remove_action( 'template_redirect', 'wc_template_redirect' );

        // Prevent WC_Form_Handler::pay_action from interfering
        if ( class_exists( 'WC_Form_Handler' ) ) {
            remove_action( 'wp', array( 'WC_Form_Handler', 'pay_action' ) );
        }

        // Tell WC this is NOT its checkout (prevents shortcode-based detection)
        add_filter( 'woocommerce_is_checkout', '__return_false', 999 );
    }

    /**
     * Detect whether the current page contains [znc_checkout].
     */
    private function is_znc_checkout_page() {
        if ( is_admin() || wp_doing_ajax() ) return false;

        global $post;
        if ( ! $post ) return false;

        // Check post_content for shortcode
        if ( has_shortcode( $post->post_content, 'znc_checkout' ) ) {
            return true;
        }

        // Also check the network setting for checkout page ID
        $settings = get_site_option( 'znc_network_settings', array() );
        $page_id  = absint( $settings['checkout_page_id'] ?? 0 );
        if ( $page_id && $post->ID === $page_id ) {
            return true;
        }

        return false;
    }

    /* ──────────────────────────────────────────────────────────────
     *  RENDER CHECKOUT
     * ──────────────────────────────────────────────────────────── */

    /**
     * Render the full checkout form (called from [znc_checkout] shortcode).
     */
    public function render_checkout() {
        if ( ! is_user_logged_in() ) {
            return '<div class="woocommerce-info">'
                . esc_html__( 'You must be logged in to checkout.', 'zinckles-net-cart' )
                . ' <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">'
                . esc_html__( 'Log in', 'zinckles-net-cart' ) . '</a></div>';
        }

        if ( $this->cart->is_empty() ) {
            return '<div class="woocommerce-info">'
                . esc_html__( 'Your global cart is empty.', 'zinckles-net-cart' )
                . '</div>';
        }

        $user_id  = get_current_user_id();
        $enriched = $this->renderer->get_enriched_cart( $user_id );

        if ( empty( $enriched ) ) {
            return '<div class="woocommerce-info">'
                . esc_html__( 'Could not load cart details.', 'zinckles-net-cart' )
                . '</div>';
        }

        /* ── Stock check ── */
        $stock_check = ZNC_Inventory_Sync::validate_stock( $enriched );
        $stock_html  = '';
        if ( ! $stock_check['valid'] ) {
            $stock_html = ZNC_Inventory_Sync::render_stock_errors( $stock_check['errors'] );
        }

        /* ── Currency analysis ── */
        $currency_totals = ZNC_Currency_Handler::get_totals_by_currency( $enriched );
        $zcred_total     = 0;
        $monetary_total  = 0;
        $zcred_shops     = array();
        $regular_shops   = array();

        foreach ( $enriched as $blog_id => $shop ) {
            if ( ZNC_Currency_Handler::is_points_currency( $blog_id ) ) {
                $zcred_total += $shop['subtotal'];
                $zcred_shops[ $blog_id ] = $shop;
            } else {
                $monetary_total += $shop['subtotal'];
                $regular_shops[ $blog_id ] = $shop;
            }
        }

        /* ── MyCred balance (for ZCred items) ── */
        $mycred_balance_html = '';
        if ( ! empty( $zcred_shops ) && class_exists( 'ZNC_MyCred_Engine' ) ) {
            $mycred_balance_html = ZNC_MyCred_Engine::render_checkout_balance( $user_id, $zcred_shops );
        }

        /* ── Payment gateways (for monetary items) ── */
        $gateways_html = '';
        if ( $monetary_total > 0 ) {
            $gateways_html = $this->render_payment_gateways();
        }

        /* ── Build output ── */
        ob_start();
        ?>
        <div class="znc-checkout-wrap woocommerce">
            <?php echo $stock_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <form method="post" id="znc-checkout-form" class="znc-checkout-form">
                <?php wp_nonce_field( 'znc_checkout_action', 'znc_checkout_nonce' ); ?>

                <!-- ─── Order Summary ─── -->
                <h3><?php esc_html_e( 'Order Summary', 'zinckles-net-cart' ); ?></h3>
                <?php foreach ( $enriched as $blog_id => $shop ) :
                    $curr       = ZNC_Currency_Handler::get_blog_currency( $blog_id );
                    $is_points  = ZNC_Currency_Handler::is_points_currency( $blog_id );
                ?>
                <div class="znc-checkout-shop" data-blog="<?php echo esc_attr( $blog_id ); ?>">
                    <h4>
                        <?php echo esc_html( $shop['name'] ); ?>
                        <?php if ( $is_points ) : ?>
                            <span class="znc-badge znc-badge-points"><?php echo esc_html( $curr['label'] ); ?></span>
                        <?php endif; ?>
                    </h4>
                    <table class="znc-checkout-items shop_table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Product', 'zinckles-net-cart' ); ?></th>
                                <th><?php esc_html_e( 'Qty', 'zinckles-net-cart' ); ?></th>
                                <th><?php esc_html_e( 'Price', 'zinckles-net-cart' ); ?></th>
                                <th><?php esc_html_e( 'Subtotal', 'zinckles-net-cart' ); ?></th>
                                <?php if ( $is_points ) : ?>
                                    <th><?php esc_html_e( 'Point Type', 'zinckles-net-cart' ); ?></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $shop['items'] as $item ) :
                            $line_total = (float)$item['price'] * (int)$item['quantity'];
                            $point_type_label = '';
                            if ( $is_points && class_exists( 'ZNC_MyCred_Engine' ) ) {
                                $point_type_label = ZNC_MyCred_Engine::get_product_point_label(
                                    $blog_id,
                                    $item['product_id']
                                );
                            }
                        ?>
                            <tr>
                                <td>
                                    <?php if ( ! empty( $item['image'] ) ) : ?>
                                        <img src="<?php echo esc_url( $item['image'] ); ?>"
                                             alt="" width="40" height="40"
                                             class="znc-checkout-thumb">
                                    <?php endif; ?>
                                    <?php echo esc_html( $item['name'] ); ?>
                                    <?php if ( ! empty( $item['variation'] ) ) : ?>
                                        <small class="znc-variation-info">
                                            <?php
                                            $parts = array();
                                            foreach ( (array) $item['variation'] as $k => $v ) {
                                                $parts[] = esc_html( ucfirst( str_replace( 'attribute_pa_', '', $k ) ) )
                                                    . ': ' . esc_html( $v );
                                            }
                                            echo implode( ', ', $parts );
                                            ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo intval( $item['quantity'] ); ?></td>
                                <td><?php echo esc_html( ZNC_Currency_Handler::format_price( $item['price'], $curr['symbol'] ) ); ?></td>
                                <td><?php echo esc_html( ZNC_Currency_Handler::format_price( $line_total, $curr['symbol'] ) ); ?></td>
                                <?php if ( $is_points ) : ?>
                                    <td><span class="znc-point-type"><?php echo esc_html( $point_type_label ?: $curr['label'] ); ?></span></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="znc-text-right">
                                    <strong><?php echo esc_html( $shop['name'] ); ?> <?php esc_html_e( 'Subtotal', 'zinckles-net-cart' ); ?>:</strong>
                                </td>
                                <td colspan="<?php echo $is_points ? 2 : 1; ?>">
                                    <strong>
                                        <?php echo esc_html( ZNC_Currency_Handler::format_price( $shop['subtotal'], $curr['symbol'] ) ); ?>
                                        <?php if ( $is_points ) : ?>
                                            <small>(<?php echo esc_html( $curr['label'] ); ?>)</small>
                                        <?php endif; ?>
                                    </strong>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endforeach; ?>

                <!-- ─── Grand Totals (per currency) ─── -->
                <div class="znc-checkout-totals">
                    <h3><?php esc_html_e( 'Grand Total', 'zinckles-net-cart' ); ?></h3>
                    <table class="znc-totals-table shop_table">
                        <?php foreach ( $currency_totals as $code => $data ) : ?>
                        <tr>
                            <td><?php echo esc_html( $data['label'] ); ?>:</td>
                            <td><strong><?php echo esc_html( ZNC_Currency_Handler::format_price( $data['total'], $data['symbol'] ) ); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>

                <?php
                /* ─── MyCred Balance Section ─── */
                if ( $mycred_balance_html ) {
                    echo '<div class="znc-checkout-mycred-section">';
                    echo '<h3>' . esc_html__( 'Points Payment', 'zinckles-net-cart' ) . '</h3>';
                    echo $mycred_balance_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo '</div>';
                }
                ?>

                <!-- ─── Billing Details ─── -->
                <div class="znc-checkout-billing">
                    <h3><?php esc_html_e( 'Billing Details', 'zinckles-net-cart' ); ?></h3>
                    <?php
                    $user    = wp_get_current_user();
                    $billing = $this->get_saved_billing( $user );
                    ?>
                    <p class="form-row form-row-wide">
                        <label for="znc_billing_name"><?php esc_html_e( 'Full Name', 'zinckles-net-cart' ); ?> <span class="required">*</span></label>
                        <input type="text" id="znc_billing_name" name="billing_name"
                               value="<?php echo esc_attr( $billing['name'] ); ?>" required class="input-text">
                    </p>
                    <p class="form-row form-row-wide">
                        <label for="znc_billing_email"><?php esc_html_e( 'Email', 'zinckles-net-cart' ); ?> <span class="required">*</span></label>
                        <input type="email" id="znc_billing_email" name="billing_email"
                               value="<?php echo esc_attr( $billing['email'] ); ?>" required class="input-text">
                    </p>
                    <p class="form-row form-row-wide">
                        <label for="znc_billing_address"><?php esc_html_e( 'Address', 'zinckles-net-cart' ); ?></label>
                        <input type="text" id="znc_billing_address" name="billing_address"
                               value="<?php echo esc_attr( $billing['address'] ); ?>" class="input-text">
                    </p>
                    <p class="form-row form-row-first">
                        <label for="znc_billing_city"><?php esc_html_e( 'City', 'zinckles-net-cart' ); ?></label>
                        <input type="text" id="znc_billing_city" name="billing_city"
                               value="<?php echo esc_attr( $billing['city'] ); ?>" class="input-text">
                    </p>
                    <p class="form-row form-row-last">
                        <label for="znc_billing_postcode"><?php esc_html_e( 'Postal Code', 'zinckles-net-cart' ); ?></label>
                        <input type="text" id="znc_billing_postcode" name="billing_postcode"
                               value="<?php echo esc_attr( $billing['postcode'] ); ?>" class="input-text">
                    </p>
                    <p class="form-row form-row-wide">
                        <label for="znc_billing_country"><?php esc_html_e( 'Country', 'zinckles-net-cart' ); ?></label>
                        <select id="znc_billing_country" name="billing_country" class="input-text">
                            <?php
                            $countries = WC()->countries->get_countries();
                            $selected  = $billing['country'] ?: 'CA';
                            foreach ( $countries as $ccode => $cname ) {
                                printf(
                                    '<option value="%s"%s>%s</option>',
                                    esc_attr( $ccode ),
                                    selected( $ccode, $selected, false ),
                                    esc_html( $cname )
                                );
                            }
                            ?>
                        </select>
                    </p>
                    <p class="form-row form-row-wide">
                        <label for="znc_order_notes"><?php esc_html_e( 'Order Notes', 'zinckles-net-cart' ); ?></label>
                        <textarea id="znc_order_notes" name="order_notes" rows="3" class="input-text"></textarea>
                    </p>
                </div>

                <?php
                /* ─── Payment Gateways (only if monetary total > 0) ─── */
                if ( $monetary_total > 0 ) {
                    echo '<div class="znc-checkout-payment">';
                    echo '<h3>' . esc_html__( 'Payment Method', 'zinckles-net-cart' ) . '</h3>';
                    echo $gateways_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo '</div>';
                } else {
                    echo '<input type="hidden" name="payment_method" value="znc_points_only">';
                    echo '<div class="woocommerce-info">';
                    esc_html_e( 'All items will be paid with points — no additional payment required.', 'zinckles-net-cart' );
                    echo '</div>';
                }
                ?>

                <!-- ─── Submit ─── -->
                <div class="znc-checkout-submit">
                    <button type="submit" name="znc_place_order" class="button alt"
                            <?php echo $stock_check['valid'] ? '' : 'disabled'; ?>>
                        <?php
                        if ( $monetary_total > 0 && $zcred_total > 0 ) {
                            printf(
                                /* translators: %s = monetary total */
                                esc_html__( 'Pay %s + Points', 'zinckles-net-cart' ),
                                esc_html( ZNC_Currency_Handler::format_price(
                                    $monetary_total,
                                    $currency_totals[ array_key_first(
                                        array_filter( $currency_totals, function( $d ) {
                                            return ! in_array( 'MYC', [ $d['symbol'] ?? '' ], true );
                                        })
                                    ) ]['symbol'] ?? '$'
                                ))
                            );
                        } elseif ( $zcred_total > 0 ) {
                            esc_html_e( 'Pay with Points', 'zinckles-net-cart' );
                        } else {
                            $sym = reset( $currency_totals )['symbol'] ?? '$';
                            printf(
                                /* translators: %s = total */
                                esc_html__( 'Place Order — %s', 'zinckles-net-cart' ),
                                esc_html( ZNC_Currency_Handler::format_price( $monetary_total, $sym ) )
                            );
                        }
                        ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ──────────────────────────────────────────────────────────────
     *  PAYMENT GATEWAY RENDERING
     * ──────────────────────────────────────────────────────────── */

    private function render_payment_gateways() {
        if ( ! WC()->payment_gateways() ) {
            return '<div class="woocommerce-error">'
                . esc_html__( 'No payment methods available.', 'zinckles-net-cart' )
                . '</div>';
        }

        $gateways = WC()->payment_gateways()->get_available_payment_gateways();
        if ( empty( $gateways ) ) {
            return '<div class="woocommerce-error">'
                . esc_html__( 'No payment methods available. Please contact the administrator.', 'zinckles-net-cart' )
                . '</div>';
        }

        $html = '<ul class="znc-payment-methods wc_payment_methods payment_methods methods">';
        $first = true;

        foreach ( $gateways as $gateway ) {
            $checked = $first ? ' checked' : '';
            $first   = false;

            $html .= '<li class="wc_payment_method payment_method_' . esc_attr( $gateway->id ) . '">';
            $html .= '<input type="radio" name="payment_method" id="payment_method_' . esc_attr( $gateway->id ) . '"'
                   . ' value="' . esc_attr( $gateway->id ) . '"' . $checked . ' class="input-radio">';
            $html .= '<label for="payment_method_' . esc_attr( $gateway->id ) . '">';

            if ( $gateway->get_icon() ) {
                $html .= $gateway->get_icon() . ' ';
            }
            $html .= esc_html( $gateway->get_title() );
            $html .= '</label>';

            if ( $gateway->has_fields() || $gateway->get_description() ) {
                $html .= '<div class="payment_box payment_method_' . esc_attr( $gateway->id ) . '"'
                       . ( $checked ? '' : ' style="display:none;"' ) . '>';
                ob_start();
                $gateway->payment_fields();
                $html .= ob_get_clean();
                $html .= '</div>';
            }

            $html .= '</li>';
        }

        $html .= '</ul>';
        return $html;
    }

    /* ──────────────────────────────────────────────────────────────
     *  PROCESS CHECKOUT (form POST)
     * ──────────────────────────────────────────────────────────── */

    /**
     * Intercept form POST on the checkout page.
     */
    public function maybe_process_post() {
        if ( ! isset( $_POST['znc_place_order'] ) ) return;
        if ( ! $this->is_znc_checkout_page() ) return;

        $this->process_checkout_form();
    }

    /**
     * Main checkout processing pipeline.
     */
    public function process_checkout_form() {
        /* ── 1. Nonce check ── */
        if ( ! isset( $_POST['znc_checkout_nonce'] )
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['znc_checkout_nonce'] ) ), 'znc_checkout_action' ) ) {
            wc_add_notice( __( 'Security check failed. Please try again.', 'zinckles-net-cart' ), 'error' );
            return;
        }

        if ( ! is_user_logged_in() ) {
            wc_add_notice( __( 'You must be logged in.', 'zinckles-net-cart' ), 'error' );
            return;
        }

        $user_id = get_current_user_id();

        /* ── 2. Billing validation ── */
        $billing = array(
            'name'     => sanitize_text_field( wp_unslash( $_POST['billing_name'] ?? '' ) ),
            'email'    => sanitize_email( wp_unslash( $_POST['billing_email'] ?? '' ) ),
            'address'  => sanitize_text_field( wp_unslash( $_POST['billing_address'] ?? '' ) ),
            'city'     => sanitize_text_field( wp_unslash( $_POST['billing_city'] ?? '' ) ),
            'postcode' => sanitize_text_field( wp_unslash( $_POST['billing_postcode'] ?? '' ) ),
            'country'  => sanitize_text_field( wp_unslash( $_POST['billing_country'] ?? 'CA' ) ),
        );
        $order_notes = sanitize_textarea_field( wp_unslash( $_POST['order_notes'] ?? '' ) );

        if ( empty( $billing['name'] ) || empty( $billing['email'] ) ) {
            wc_add_notice( __( 'Name and email are required.', 'zinckles-net-cart' ), 'error' );
            return;
        }

        // Save billing for next time
        update_user_meta( $user_id, '_znc_billing', $billing );

        /* ── 3. Enriched cart + stock check ── */
        $enriched = $this->renderer->get_enriched_cart( $user_id );
        if ( empty( $enriched ) ) {
            wc_add_notice( __( 'Cart is empty or could not be loaded.', 'zinckles-net-cart' ), 'error' );
            return;
        }

        $stock_check = ZNC_Inventory_Sync::validate_stock( $enriched );
        if ( ! $stock_check['valid'] ) {
            foreach ( $stock_check['errors'] as $err ) {
                wc_add_notice( $err['message'], 'error' );
            }
            return;
        }

        /* ── 4. Split cart by currency type ── */
        $zcred_shops   = array();
        $regular_shops = array();
        $zcred_total   = 0;
        $monetary_total = 0;

        foreach ( $enriched as $blog_id => $shop ) {
            if ( ZNC_Currency_Handler::is_points_currency( $blog_id ) ) {
                $zcred_shops[ $blog_id ] = $shop;
                $zcred_total += $shop['subtotal'];
            } else {
                $regular_shops[ $blog_id ] = $shop;
                $monetary_total += $shop['subtotal'];
            }
        }

        /* ── 5. MyCred balance validation ── */
        if ( ! empty( $zcred_shops ) && class_exists( 'ZNC_MyCred_Engine' ) ) {
            $points_validation = ZNC_MyCred_Engine::validate_checkout_balance( $user_id, $zcred_shops );
            if ( ! $points_validation['valid'] ) {
                foreach ( $points_validation['errors'] as $msg ) {
                    wc_add_notice( $msg, 'error' );
                }
                return;
            }
        }

        /* ── 6. Payment method validation ── */
        $payment_method = sanitize_text_field( wp_unslash( $_POST['payment_method'] ?? '' ) );

        if ( $monetary_total > 0 ) {
            if ( empty( $payment_method ) || $payment_method === 'znc_points_only' ) {
                wc_add_notice( __( 'Please select a payment method.', 'zinckles-net-cart' ), 'error' );
                return;
            }

            $gateways = WC()->payment_gateways()->get_available_payment_gateways();
            if ( ! isset( $gateways[ $payment_method ] ) ) {
                wc_add_notice( __( 'Invalid payment method selected.', 'zinckles-net-cart' ), 'error' );
                return;
            }
        }

        /* ── 7. Create orders ── */
        $result = ZNC_Order_Factory::create_orders( $user_id, $enriched, $billing, $order_notes );

        if ( is_wp_error( $result ) ) {
            wc_add_notice( $result->get_error_message(), 'error' );
            return;
        }

        $parent_id  = $result['parent_id'];
        $children   = $result['children'];
        $parent     = wc_get_order( $parent_id );

        /* ── 8. Deduct MyCred points for ZCred items ── */
        if ( ! empty( $zcred_shops ) && class_exists( 'ZNC_MyCred_Engine' ) ) {
            $deduction = ZNC_MyCred_Engine::deduct_for_checkout( $user_id, $zcred_shops, $parent_id );

            if ( is_wp_error( $deduction ) ) {
                // Rollback: cancel all orders
                $this->cancel_orders( $parent_id, $children );
                wc_add_notice( $deduction->get_error_message(), 'error' );
                return;
            }

            // Mark ZCred child orders as processing (points already deducted)
            foreach ( $zcred_shops as $blog_id => $shop ) {
                if ( isset( $children[ $blog_id ] ) ) {
                    switch_to_blog( $blog_id );
                    $child = wc_get_order( $children[ $blog_id ] );
                    if ( $child ) {
                        $child->set_status( 'processing' );
                        $child->set_payment_method_title( __( 'Points Payment', 'zinckles-net-cart' ) );
                        $child->add_order_note( __( 'Paid with MyCred points.', 'zinckles-net-cart' ), false, false );
                        $child->save();
                    }
                    restore_current_blog();
                }
            }
        }

        /* ── 9. Process WC payment for monetary items ── */
        if ( $monetary_total > 0 ) {
            // Adjust parent order total to monetary-only amount
            if ( $zcred_total > 0 ) {
                // Add a discount line for the ZCred portion
                $parent->update_meta_data( '_znc_zcred_total', $zcred_total );

                // Recalculate: remove ZCred fee lines, keep only regular ones
                foreach ( $parent->get_items( 'fee' ) as $item_id => $item ) {
                    $fee_name = $item->get_name();
                    // Check if this fee is for a ZCred shop
                    foreach ( $zcred_shops as $bid => $shop ) {
                        if ( isset( $children[ $bid ] ) && strpos( $fee_name, (string) $children[ $bid ] ) !== false ) {
                            $parent->remove_item( $item_id );
                            break;
                        }
                    }
                }
                $parent->calculate_totals();
                $parent->save();
            }

            $parent->set_payment_method( $payment_method );

            $gateway = $gateways[ $payment_method ];

            // Let the gateway validate its own fields
            if ( method_exists( $gateway, 'validate_fields' ) ) {
                $valid = $gateway->validate_fields();
                if ( $valid === false ) {
                    // Gateway adds its own notices
                    return;
                }
            }

            $parent->save();

            // Process payment
            $pay_result = $gateway->process_payment( $parent_id );

            if ( ! $pay_result || $pay_result['result'] !== 'success' ) {
                wc_add_notice( __( 'Payment could not be processed. Please try again.', 'zinckles-net-cart' ), 'error' );
                return;
            }

            // Clear cart
            $this->cart->clear_cart( $user_id );

            // Redirect to gateway's redirect URL (or order-received)
            if ( ! empty( $pay_result['redirect'] ) ) {
                wp_redirect( $pay_result['redirect'] );
                exit;
            }
        } else {
            // All ZCred — no WC payment needed
            $parent->set_status( 'completed' );
            $parent->set_payment_method_title( __( 'Points Payment', 'zinckles-net-cart' ) );
            $parent->add_order_note( __( 'All items paid with MyCred points.', 'zinckles-net-cart' ), false, false );
            $parent->save();

            // Clear cart
            $this->cart->clear_cart( $user_id );

            // Redirect to a thank-you page
            $redirect = add_query_arg( array(
                'znc_order' => $parent_id,
                'key'       => $parent->get_order_key(),
            ), wc_get_checkout_url() ?: home_url() );

            wp_redirect( $redirect );
            exit;
        }
    }

    /* ──────────────────────────────────────────────────────────────
     *  AJAX FALLBACK
     * ──────────────────────────────────────────────────────────── */

    public function ajax_process_checkout() {
        check_ajax_referer( 'znc_checkout_action', 'znc_checkout_nonce' );
        $this->process_checkout_form();
        // If we get here without redirect, there were errors
        $notices = wc_get_notices( 'error' );
        wc_clear_notices();
        wp_send_json_error( array(
            'messages' => wp_list_pluck( $notices, 'notice' ),
        ) );
    }

    /* ──────────────────────────────────────────────────────────────
     *  HELPERS
     * ──────────────────────────────────────────────────────────── */

    /**
     * Get saved billing details for a user.
     */
    private function get_saved_billing( $user ) {
        $saved = get_user_meta( $user->ID, '_znc_billing', true );
        if ( is_array( $saved ) ) return $saved;

        // Fall back to WC billing meta
        return array(
            'name'     => trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name,
            'email'    => $user->user_email,
            'address'  => get_user_meta( $user->ID, 'billing_address_1', true ),
            'city'     => get_user_meta( $user->ID, 'billing_city', true ),
            'postcode' => get_user_meta( $user->ID, 'billing_postcode', true ),
            'country'  => get_user_meta( $user->ID, 'billing_country', true ) ?: 'CA',
        );
    }

    /**
     * Cancel parent + child orders (rollback on failure).
     */
    private function cancel_orders( $parent_id, $children ) {
        $parent = wc_get_order( $parent_id );
        if ( $parent ) {
            $parent->set_status( 'cancelled' );
            $parent->add_order_note( __( 'Cancelled: payment/points failure.', 'zinckles-net-cart' ), false, false );
            $parent->save();
        }

        foreach ( $children as $blog_id => $child_id ) {
            switch_to_blog( $blog_id );
            $child = wc_get_order( $child_id );
            if ( $child ) {
                $child->set_status( 'cancelled' );
                $child->save();
            }
            restore_current_blog();
        }
    }
}
