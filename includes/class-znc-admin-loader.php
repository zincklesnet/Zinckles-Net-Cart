<?php
defined( 'ABSPATH' ) || exit;
class ZNC_Admin_Loader {
    public function init() {
        add_filter( 'znc_cart_snapshot_enabled', array( $this, 'check_enrollment' ) );
    }
    public function check_enrollment( $enabled ) {
        $host = new ZNC_Checkout_Host();
        return $host->is_enrolled( get_current_blog_id() );
    }
}
