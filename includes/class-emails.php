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
	 * Result of the last quote e-mail: array( 'sent'|'off'|'failed', error message ).
	 *
	 * @var array|null
	 */
	public static $last_quote = null;

	/**
	 * Error caught from wp_mail_failed while one of our e-mails is being sent.
	 *
	 * @var string|null
	 */
	private static $mail_error = null;

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
			$r = self::send_watched( $a, $order );
			if ( 'failed' === $r[0] ) {
				Nextial_Q_Mail_Alerts::request_mail_failed( $order, $r[1] );
			}
		}
		$c = self::email( 'Nextial_Q_Email_Request_Received' );
		if ( $c ) {
			$r = self::send_watched( $c, $order );
			if ( 'failed' === $r[0] ) {
				/* translators: %s: error message */
				$order->add_order_note( sprintf( __( 'The confirmation e-mail to the customer could not be sent: %s', 'nextial-quotes-for-woocommerce' ), $r[1] ) );
			}
		}
	}

	/**
	 * Send one of our e-mails and tell whether WordPress reported a failure.
	 *
	 * @param WC_Email $email E-mail.
	 * @param WC_Order $order Order.
	 * @return array array( 'sent'|'off'|'failed', error message ).
	 */
	public static function send_watched( $email, $order ) {
		if ( ! $email->is_enabled() ) {
			return array( 'off', '' );
		}
		self::$mail_error = null;
		add_action( 'wp_mail_failed', array( __CLASS__, 'catch_mail_error' ) );
		$sent = (bool) $email->trigger( $order );
		remove_action( 'wp_mail_failed', array( __CLASS__, 'catch_mail_error' ) );
		if ( $sent ) {
			return array( 'sent', '' );
		}
		$msg = null !== self::$mail_error && '' !== self::$mail_error ? self::$mail_error : __( 'WordPress could not send the e-mail (no error message).', 'nextial-quotes-for-woocommerce' );
		return array( 'failed', wp_strip_all_tags( $msg ) );
	}

	/**
	 * Remember the error WordPress gives when wp_mail() fails.
	 *
	 * @param WP_Error $error Error.
	 */
	public static function catch_mail_error( $error ) {
		if ( is_wp_error( $error ) ) {
			self::$mail_error = $error->get_error_message();
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
		$e                = self::email( 'Nextial_Q_Email_Quote' );
		self::$last_quote = $e ? self::send_watched( $e, $order ) : array( 'off', '' );
		if ( 'failed' === self::$last_quote[0] ) {
			/* translators: %s: error message */
			$order->add_order_note( sprintf( __( 'The quote e-mail to the customer could not be sent: %s. Copy the payment link from this order and send it yourself.', 'nextial-quotes-for-woocommerce' ), self::$last_quote[1] ) );
		}
		return 'sent' === self::$last_quote[0];
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
			$r         = self::send_watched( $e, $order );
			if ( 'failed' === $r[0] ) {
				/* translators: %s: error message */
				$order->add_order_note( sprintf( __( 'The "quote declined" e-mail to the shop could not be sent: %s', 'nextial-quotes-for-woocommerce' ), $r[1] ) );
			}
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
