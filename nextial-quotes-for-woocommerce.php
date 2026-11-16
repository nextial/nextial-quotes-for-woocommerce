<?php
/**
 * Plugin Name:       Nextial Quotes – Request a Quote for WooCommerce
 * Description:       Let customers request a quote for one or many products. Requests become WooCommerce orders with a "Quote request" status; set your prices and send the quote in one click – the customer accepts and pays online, or declines.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Nextial
 * Author URI:        https://nextial.it
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nextial-quotes-for-woocommerce
 * WC requires at least: 8.2
 * WC tested up to:   11.3
 *
 * @package Nextial_Quotes
 */

defined( 'ABSPATH' ) || exit;

define( 'NEXTIAL_Q_VERSION', '0.1.0' );
define( 'NEXTIAL_Q_FILE', __FILE__ );
define( 'NEXTIAL_Q_DIR', plugin_dir_path( __FILE__ ) );

require_once NEXTIAL_Q_DIR . 'includes/class-settings.php';
require_once NEXTIAL_Q_DIR . 'includes/class-statuses.php';
require_once NEXTIAL_Q_DIR . 'includes/class-rules.php';
require_once NEXTIAL_Q_DIR . 'includes/class-quote-list.php';
require_once NEXTIAL_Q_DIR . 'includes/class-frontend.php';
require_once NEXTIAL_Q_DIR . 'includes/class-request.php';
require_once NEXTIAL_Q_DIR . 'includes/class-emails.php';
require_once NEXTIAL_Q_DIR . 'includes/class-admin.php';

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		Nextial_Q_Statuses::init();
		Nextial_Q_Settings::init();
		Nextial_Q_Frontend::init();
		Nextial_Q_Request::init();
		Nextial_Q_Emails::init();
		Nextial_Q_Admin::init();
	}
);

register_activation_hook( __FILE__, array( 'Nextial_Q_Settings', 'activate' ) );
