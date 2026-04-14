<?php
/**
 * Inventory Sync — stock deduction + retry queue with cron fallback.
 *
 * @package ZincklesNetCart
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class ZNC_Inventory_Sync {

    public function init() {
        if ( ! wp_next_scheduled( 'znc_inventory_retry' ) ) {
            wp_schedule_event( time(), 'every_five_minutes', 'znc_inventory_retry' );
        }
        add_action( 'znc_inventory_retry', array( $this, 'process_retry_queue' ) );

        // Register custom cron interval.
        add_filter( 'cron_schedules', array( $this, 'add_cron_interval' ) );
    }

    public function add_cron_interval( $schedules ) {
        $schedules['every_five_minutes'] = array(
            'interval' => 300,
            'display'  => __( 'Every 5 Minutes', 'znc' ),
        );
        return $schedules;
    }

    /**
     * Deduct inventory for all shops in a checkout.
     */
    public function deduct_all( $shops ) {
        $results = array();

        foreach ( $shops as $shop ) {
            $blog_id = $shop['blog_id'];

            switch_to_blog( $blog_id );

            foreach ( $shop['items'] as $item ) {
                $product = function_exists( 'wc_get_product' )
                    ? wc_get_product( $item['variation_id'] ?: $item['product_id'] )
                    : null;

                if ( ! $product ) {
                    $this->queue_retry( $blog_id, $item );
                    $results[] = array(
                        'blog_id'    => $blog_id,
                        'product_id' => $item['product_id'],
                        'success'    => false,
                        'queued'     => true,
                    );
                    continue;
                }

                if ( $product->managing_stock() ) {
                    $new_stock = wc_update_product_stock( $product, $item['quantity'], 'decrease' );
                    if ( is_wp_error( $new_stock ) ) {
                        $this->queue_retry( $blog_id, $item );
                        $results[] = array(
                            'blog_id'    => $blog_id,
                            'product_id' => $item['product_id'],
                            'success'    => false,
                            'queued'     => true,
                        );
                    } else {
                        $results[] = array(
                            'blog_id'    => $blog_id,
                            'product_id' => $item['product_id'],
                            'success'    => true,
                            'new_stock'  => $new_stock,
                        );
                    }
                } else {
                    $results[] = array(
                        'blog_id'    => $blog_id,
                        'product_id' => $item['product_id'],
                        'success'    => true,
                        'message'    => 'Stock not managed.',
                    );
                }
            }

            restore_current_blog();
        }

        return $results;
    }

    /**
     * Queue a failed deduction for retry.
     */
    private function queue_retry( $blog_id, $item ) {
        $queue = get_site_option( 'znc_inventory_retry_queue', array() );
        $queue[] = array(
            'blog_id'      => $blog_id,
            'product_id'   => $item['product_id'],
            'variation_id' => $item['variation_id'] ?? 0,
            'quantity'     => $item['quantity'],
            'attempts'     => 0,
            'queued_at'    => current_time( 'timestamp' ),
        );
        update_site_option( 'znc_inventory_retry_queue', $queue );
    }

    /**
     * Process the retry queue (called by cron).
     */
    public function process_retry_queue() {
        $queue    = get_site_option( 'znc_inventory_retry_queue', array() );
        $settings = get_site_option( 'znc_network_settings', array() );
        $max      = absint( $settings['inventory_retry_max'] ?? 5 );
        $remaining = array();

        foreach ( $queue as $entry ) {
            if ( $entry['attempts'] >= $max ) {
                do_action( 'znc_inventory_retry_failed', $entry );
                continue;
            }

            switch_to_blog( $entry['blog_id'] );

            $product = function_exists( 'wc_get_product' )
                ? wc_get_product( $entry['variation_id'] ?: $entry['product_id'] )
                : null;

            if ( $product && $product->managing_stock() ) {
                $result = wc_update_product_stock( $product, $entry['quantity'], 'decrease' );
                restore_current_blog();

                if ( ! is_wp_error( $result ) ) {
                    continue; // Success — don't re-queue.
                }
            } else {
                restore_current_blog();
            }

            $entry['attempts']++;
            $remaining[] = $entry;
        }

        update_site_option( 'znc_inventory_retry_queue', $remaining );
    }
}
