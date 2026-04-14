<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Inventory_Sync {

    public static function init() {
        add_action( 'znc_inventory_retry_cron', array( __CLASS__, 'process_retry_queue' ) );
        if ( ! wp_next_scheduled( 'znc_inventory_retry_cron' ) ) {
            wp_schedule_event( time(), 'every_five_minutes', 'znc_inventory_retry_cron' );
        }
        add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_interval' ) );
    }

    public static function add_cron_interval( $schedules ) {
        $schedules['every_five_minutes'] = array(
            'interval' => 300,
            'display'  => 'Every 5 Minutes',
        );
        return $schedules;
    }

    public static function deduct_stock( $blog_id, $items ) {
        $blog_id = (int) $blog_id;
        $current = get_current_blog_id();
        $need_switch = ( $current !== $blog_id );

        if ( $need_switch ) switch_to_blog( $blog_id );

        $failed = array();
        foreach ( $items as $item ) {
            $product = wc_get_product( $item['variation_id'] ?: $item['product_id'] );
            if ( ! $product || ! $product->managing_stock() ) continue;

            $result = wc_update_product_stock( $product, $item['quantity'], 'decrease' );
            if ( $result === false || is_wp_error( $result ) ) {
                $failed[] = $item;
            }
        }

        if ( $need_switch ) restore_current_blog();

        if ( ! empty( $failed ) ) {
            self::add_to_retry_queue( $blog_id, $failed );
        }

        return empty( $failed );
    }

    private static function add_to_retry_queue( $blog_id, $items ) {
        $queue = get_site_option( 'znc_inventory_retry_queue', array() );
        $queue[] = array(
            'blog_id'  => $blog_id,
            'items'    => $items,
            'attempts' => 0,
            'added_at' => time(),
        );
        update_site_option( 'znc_inventory_retry_queue', $queue );
    }

    public static function process_retry_queue() {
        $queue = get_site_option( 'znc_inventory_retry_queue', array() );
        if ( empty( $queue ) ) return;

        $remaining = array();
        foreach ( $queue as $entry ) {
            if ( $entry['attempts'] >= 5 ) {
                error_log( '[ZNC] Inventory sync failed after 5 attempts for blog ' . $entry['blog_id'] );
                continue;
            }

            $success = self::deduct_stock( $entry['blog_id'], $entry['items'] );
            if ( ! $success ) {
                $entry['attempts']++;
                $remaining[] = $entry;
            }
        }

        update_site_option( 'znc_inventory_retry_queue', $remaining );
    }
}
