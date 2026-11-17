<?php
/**
 * No lost requests: count of new requests in the menu, warning when the shop e-mail fails, test e-mail.
 *
 * @package Nextial_Quotes
 */

defined( 'ABSPATH' ) || exit;

/**
 * A quote request is saved as an order before any e-mail is sent, so it is never lost.
 * This class makes sure the shop also finds out when the e-mail did not go out.
 */
class Nextial_Q_Mail_Alerts {

	const OPTION = 'nextial_q_mail_alerts';
	const MAX    = 20;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_post_nxq_test_mail', array( __CLASS__, 'test_mail' ) );
		add_action( 'admin_post_nxq_dismiss_mail_alert', array( __CLASS__, 'dismiss' ) );
	}

	/**
	 * The new-request e-mail to the shop failed: note on the order and a warning in the dashboard.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $error Error message.
	 */
	public static function request_mail_failed( WC_Order $order, $error ) {
		/* translators: 1: e-mail address, 2: error message */
		$order->add_order_note( sprintf( __( 'The "new quote request" e-mail to %1$s could not be sent: %2$s. The request is saved here, nothing is lost.', 'nextial-quotes-for-woocommerce' ), Nextial_Q_Emails::admin_recipient(), $error ) );
		$alerts                       = self::alerts();
		$alerts[ $order->get_id() ] = array(
			'time'  => time(),
			'error' => substr( (string) $error, 0, 300 ),
		);
		if ( count( $alerts ) > self::MAX ) {
			$alerts = array_slice( $alerts, -self::MAX, null, true );
		}
		update_option( self::OPTION, $alerts, false );
	}

	/**
	 * Stored warnings, order ID => array( time, error ).
	 *
	 * @return array
	 */
	public static function alerts() {
		$a = get_option( self::OPTION, array() );
		return is_array( $a ) ? $a : array();
	}

	/**
	 * Admin list of quote requests (HPOS or classic).
	 *
	 * @return string Relative admin URL.
	 */
	public static function list_url() {
		$hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		return $hpos ? 'admin.php?page=wc-orders&status=wc-' . Nextial_Q_Statuses::REQUEST : 'edit.php?post_status=wc-' . Nextial_Q_Statuses::REQUEST . '&post_type=shop_order';
	}

	/**
	 * How many requests wait for a quote.
	 *
	 * @return int
	 */
	public static function open_count() {
		return function_exists( 'wc_orders_count' ) ? (int) wc_orders_count( Nextial_Q_Statuses::REQUEST ) : 0;
	}

	/**
	 * WooCommerce → Quote requests (N).
	 */
	public static function menu() {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		$n     = self::open_count();
		$label = __( 'Quote requests', 'nextial-quotes-for-woocommerce' );
		if ( $n > 0 ) {
			$label .= ' <span class="awaiting-mod count-' . absint( $n ) . '"><span class="pending-count">' . esc_html( number_format_i18n( $n ) ) . '</span></span>';
		}
		add_submenu_page( 'woocommerce', __( 'Quote requests', 'nextial-quotes-for-woocommerce' ), $label, 'edit_shop_orders', self::list_url() );
	}

	/**
	 * Warning on every admin screen while some request e-mails failed.
	 */
	public static function notice() {
		if ( current_user_can( 'manage_woocommerce' ) ) {
			$t = get_transient( 'nxq_test_mail_' . get_current_user_id() );
			if ( is_array( $t ) ) {
				delete_transient( 'nxq_test_mail_' . get_current_user_id() );
				echo '<div class="notice notice-' . esc_attr( 'sent' === $t[0] ? 'success' : 'error' ) . ' is-dismissible nxq-test-mail"><p>' . esc_html( $t[1] ) . '</p></div>';
			}
		}
		$alerts = self::alerts();
		if ( ! $alerts || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		$links = array();
		foreach ( array_keys( $alerts ) as $id ) {
			$order = wc_get_order( $id );
			if ( $order ) {
				$links[] = '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a>';
			}
		}
		$last    = end( $alerts );
		$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=nxq_dismiss_mail_alert' ), 'nxq_dismiss_mail_alert' );
		$test    = wp_nonce_url( admin_url( 'admin-post.php?action=nxq_test_mail' ), 'nxq_test_mail' );
		echo '<div class="notice notice-warning nxq-mail-alert"><p><strong>' . esc_html__( 'Nextial Quotes: the e-mail for some new quote requests could not be sent.', 'nextial-quotes-for-woocommerce' ) . '</strong> ';
		echo esc_html__( 'The requests are safe – they are orders with the "Quote request" status:', 'nextial-quotes-for-woocommerce' ) . ' ' . wp_kses_post( implode( ', ', $links ) ) . '.</p>';
		/* translators: %s: error message */
		echo '<p>' . esc_html( sprintf( __( 'Last error: %s', 'nextial-quotes-for-woocommerce' ), $last['error'] ) ) . ' ' . esc_html__( 'Your site may not be able to send e-mail: an SMTP plugin usually fixes it.', 'nextial-quotes-for-woocommerce' ) . '</p>';
		echo '<p><a class="button" href="' . esc_url( admin_url( self::list_url() ) ) . '">' . esc_html__( 'Open quote requests', 'nextial-quotes-for-woocommerce' ) . '</a> ';
		if ( current_user_can( 'manage_woocommerce' ) ) {
			echo '<a class="button" href="' . esc_url( $test ) . '">' . esc_html__( 'Send a test e-mail', 'nextial-quotes-for-woocommerce' ) . '</a> ';
		}
		echo '<a href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'nextial-quotes-for-woocommerce' ) . '</a></p></div>';
	}

	/**
	 * Send a test e-mail to the address that receives new requests.
	 */
	public static function test_mail() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'nextial-quotes-for-woocommerce' ), 403 );
		}
		check_admin_referer( 'nxq_test_mail' );
		$to    = Nextial_Q_Emails::admin_recipient();
		$error = '';
		$catch = function ( $e ) use ( &$error ) {
			if ( is_wp_error( $e ) ) {
				$error = $e->get_error_message();
			}
		};
		add_action( 'wp_mail_failed', $catch );
		$mailer = WC()->mailer();
		$body   = $mailer->wrap_message(
			__( 'Test e-mail', 'nextial-quotes-for-woocommerce' ),
			'<p>' . esc_html__( 'This is a test from Nextial Quotes. If you can read it, new quote requests will reach this address.', 'nextial-quotes-for-woocommerce' ) . '</p>'
		);
		$sent   = (bool) $mailer->send( $to, __( 'Test e-mail from Nextial Quotes', 'nextial-quotes-for-woocommerce' ), $body );
		remove_action( 'wp_mail_failed', $catch );
		if ( $sent ) {
			/* translators: %s: e-mail address */
			$result = array( 'sent', sprintf( __( 'Test e-mail sent to %s. If it does not arrive within a few minutes (check spam too), your site cannot deliver e-mail: install an SMTP plugin.', 'nextial-quotes-for-woocommerce' ), $to ) );
		} else {
			/* translators: 1: e-mail address, 2: error message */
			$result = array( 'failed', sprintf( __( 'The test e-mail to %1$s could not be sent: %2$s', 'nextial-quotes-for-woocommerce' ), $to, '' !== $error ? wp_strip_all_tags( $error ) : __( 'no error message', 'nextial-quotes-for-woocommerce' ) ) );
		}
		set_transient( 'nxq_test_mail_' . get_current_user_id(), $result, 120 );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=wc-settings&tab=nextial_quotes' ) );
		exit;
	}

	/**
	 * Remove the warning.
	 */
	public static function dismiss() {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'nextial-quotes-for-woocommerce' ), 403 );
		}
		check_admin_referer( 'nxq_dismiss_mail_alert' );
		delete_option( self::OPTION );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}
}
