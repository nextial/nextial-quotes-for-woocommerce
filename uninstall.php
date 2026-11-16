<?php
/**
 * Uninstall: remove settings only. Quote orders are WooCommerce orders and stay.
 *
 * @package Nextial_Quotes
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'nextial_q_settings' );
foreach ( array( 'woocommerce_nextial_q_new_request_settings', 'woocommerce_nextial_q_request_received_settings', 'woocommerce_nextial_q_quote_settings', 'woocommerce_nextial_q_declined_settings' ) as $option ) {
	delete_option( $option );
}
