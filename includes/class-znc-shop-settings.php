<?php
/**
 * Shop Settings Provider (runs on each subsite).
 *
 * @package ZincklesNetCart
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class ZNC_Shop_Settings {

    public function init() {
        // Settings are served via REST, no hooks needed here.
    }

    /**
     * Get full shop settings for this subsite.
     */
    public function get_settings() {
        $subsite = get_option( 'znc_subsite_settings', array() );

        return array(
            'blog_id'     => get_current_blog_id(),
            'name'        => $subsite['display_name'] ?? get_bloginfo( 'name' ),
            'url'         => home_url(),
            'currency'    => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD',
            'wc_active'   => class_exists( 'WooCommerce' ),
            'mycred'      => function_exists( 'mycred' ),
            'mycred_types' => function_exists( 'mycred_get_types' ) ? mycred_get_types() : array(),
            'tax_enabled' => function_exists( 'wc_tax_enabled' ) ? wc_tax_enabled() : false,
            'shipping'    => $this->get_shipping_config( $subsite ),
            'tax'         => $this->get_tax_config( $subsite ),
            'zcred'       => $this->get_zcred_config( $subsite ),
            'branding'    => array(
                'display_name' => $subsite['display_name'] ?? get_bloginfo( 'name' ),
                'tagline'      => $subsite['tagline'] ?? get_bloginfo( 'description' ),
                'badge_color'  => $subsite['badge_color'] ?? '#7c3aed',
                'badge_icon'   => $subsite['badge_icon'] ?? '',
            ),
        );
    }

    private function get_shipping_config( $subsite ) {
        return array(
            'mode'       => $subsite['shipping_mode'] ?? 'inherit',
            'flat_rate'  => floatval( $subsite['shipping_flat_rate'] ?? 0 ),
            'free_above' => floatval( $subsite['shipping_free_threshold'] ?? 0 ),
        );
    }

    private function get_tax_config( $subsite ) {
        return array(
            'mode'     => $subsite['tax_mode'] ?? 'inherit',
            'rate'     => floatval( $subsite['tax_rate'] ?? 0 ),
            'label'    => $subsite['tax_label'] ?? 'Tax',
        );
    }

    private function get_zcred_config( $subsite ) {
        return array(
            'accept'          => ! empty( $subsite['zcred_accept'] ),
            'max_percent'     => absint( $subsite['zcred_max_percent'] ?? 50 ),
            'earn_multiplier' => floatval( $subsite['zcred_earn_multiplier'] ?? 1.0 ),
        );
    }
}
