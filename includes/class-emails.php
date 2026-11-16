<?php
/**
 * E-mails (editable in WooCommerce → Settings → Emails).
 *
 * @package Nextial_Quotes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers four WooCommerce e-mails and sends them at the right moment.
 */
class Nextial_Q_Emails {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'register' ) );
		add_action( 'nextial_q_request_created', array( __CLASS__, 'on_request' ), 10, 2 );
		add_action( 'nextial_q_quote_sent', array( __CLASS__, 'on_sent' ), 10, 2 );
		add_action( 'nextial_q_quote_declined', array( __CLASS__, 'on_declined' ), 10, 3 );
	}

	/**
	 * Register the e-mail classes.
	 *
	 * @param array $emails Emails.
	 * @return array
	 */
	public static function register( $emails ) {
		require_once NEXTIAL_Q_DIR . 'includes/class-email-types.php';
		$emails['Nextial_Q_Email_New_Request']      = new Nextial_Q_Email_New_Request();
		$emails['Nextial_Q_Email_Request_Received'] = new Nextial_Q_Email_Request_Received();
		$emails['Nextial_Q_Email_Quote']            = new Nextial_Q_Email_Quote();
		$emails['Nextial_Q_Email_Declined']         = new Nextial_Q_Email_Declined();
		return $emails;
	}

	/**
	 * Get one of our e-mails.
	 *
	 * @param string $class Class.
	 * @return WC_Email|null
	 */
	private static function email( $class ) {
		$emails = WC()->mailer()->get_emails();
		return isset( $emails[ $class ] ) ? $emails[ $class ] : null;
	}

	/**
	 * New request: admin + customer confirmation.
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order.
	 */
	public static function on_request( $order_id, $order ) {
		$a = self::email( 'Nextial_Q_Email_New_Request' );
		if ( $a ) {
			$a->trigger( $order );
		}
		$c = self::email( 'Nextial_Q_Email_Request_Received' );
		if ( $c ) {
			$c->trigger( $order );
		}
	}

	/**
	 * Quote sent to the customer.
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order.
	 * @return bool Sent.
	 */
	public static function on_sent( $order_id, $order ) {
		$e = self::email( 'Nextial_Q_Email_Quote' );
		return $e ? (bool) $e->trigger( $order ) : false;
	}

	/**
	 * Customer declined: tell the admin.
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order.
	 * @param string   $reason   Reason.
	 */
	public static function on_declined( $order_id, $order, $reason = '' ) {
		$e = self::email( 'Nextial_Q_Email_Declined' );
		if ( $e ) {
			$e->reason = (string) $reason;
			$e->trigger( $order );
		}
	}

	/**
	 * Where admin e-mails go.
	 *
	 * @return string
	 */
	public static function admin_recipient() {
		$to = (string) Nextial_Q_Settings::get( 'admin_email' );
		return is_email( $to ) ? $to : (string) get_option( 'admin_email' );
	}

	/**
	 * Decline link for a quote.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function decline_url( WC_Order $order ) {
		return add_query_arg(
			array(
				'nxq_decline' => $order->get_id(),
				'key'         => $order->get_order_key(),
			),
			Nextial_Q_Settings::page_url()
		);
	}
}
