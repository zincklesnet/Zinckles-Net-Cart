<?php
/**
 * Per-Subsite Shop Settings — v1.4.1
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Shop_Settings {

    public function init() {
        if ( is_main_site() ) return;
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function add_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Net Cart Shop Settings', 'zinckles-net-cart' ),
            __( 'Net Cart', 'zinckles-net-cart' ),
            'manage_woocommerce',
            'znc-shop-settings',
            array( $this, 'render_page' )
        );
    }

    public function register_settings() {
        register_setting( 'znc_shop_settings', 'znc_shop_config' );
    }

    public function render_page() {
        $config = get_option( 'znc_shop_config', array() );
        $defaults = array(
            'shop_display_name' => get_bloginfo( 'name' ),
            'push_on_add'       => 1,
            'show_global_link'  => 1,
            'accepted_currencies' => array( 'USD' ),
        );
        $config = wp_parse_args( $config, $defaults );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Net Cart — Shop Settings', 'zinckles-net-cart' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'znc_shop_settings' ); ?>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Shop Display Name', 'zinckles-net-cart' ); ?></th>
                        <td><input type="text" name="znc_shop_config[shop_display_name]" value="<?php echo esc_attr( $config['shop_display_name'] ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Auto-Push to Global Cart', 'zinckles-net-cart' ); ?></th>
                        <td><label><input type="checkbox" name="znc_shop_config[push_on_add]" value="1" <?php checked( $config['push_on_add'] ); ?> /> <?php esc_html_e( 'Automatically push items to global cart when added locally', 'zinckles-net-cart' ); ?></label></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Show Global Cart Link', 'zinckles-net-cart' ); ?></th>
                        <td><label><input type="checkbox" name="znc_shop_config[show_global_link]" value="1" <?php checked( $config['show_global_link'] ); ?> /> <?php esc_html_e( 'Display "View Global Cart" link after adding items', 'zinckles-net-cart' ); ?></label></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
