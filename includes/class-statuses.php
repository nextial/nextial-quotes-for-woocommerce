<?php
/**
 * Order statuses "Quote request" and "Quote sent".
 *
 * @package Nextial_Quotes
 */

defined( 'ABSPATH' ) || exit;

/**
 * A quote is a normal WooCommerce order with one of these statuses, so it works with HPOS,
 * the classic storage, the order editor, My Account and the payment page.
 */
class Nextial_Q_Statuses {

	const REQUEST = 'nxq-request';
	const SENT    = 'nxq-sent';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'wc_order_statuses', array( __CLASS__, 'add_to_list' ) );
		add_filter( 'woocommerce_valid_order_statuses_for_payment', array( __CLASS__, 'payable' ) );
		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array( __CLASS__, 'payable' ) );
		add_filter( 'woocommerce_analytics_excluded_statuses', array( __CLASS__, 'exclude_from_analytics' ) );
		add_filter( 'woocommerce_reports_order_statuses', array( __CLASS__, 'exclude_from_legacy_reports' ) );
		add_filter( 'woocommerce_order_is_paid_statuses', array( __CLASS__, 'not_paid' ) );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'after_payment' ), 10, 4 );
	}

	/**
	 * Labels.
	 *
	 * @return array
	 */
	public static function labels() {
		return array(
			'wc-' . self::REQUEST => _x( 'Quote request', 'Order status', 'nextial-quotes-for-woocommerce' ),
			'wc-' . self::SENT    => _x( 'Quote sent', 'Order status', 'nextial-quotes-for-woocommerce' ),
		);
	}

	/**
	 * Register the post statuses (used by both storages for the status list).
	 */
	public static function register() {
		register_post_status(
			'wc-' . self::REQUEST,
			array(
				'label'                     => _x( 'Quote request', 'Order status', 'nextial-quotes-for-woocommerce' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of orders */
				'label_count'               => _n_noop( 'Quote requests <span class="count">(%s)</span>', 'Quote requests <span class="count">(%s)</span>', 'nextial-quotes-for-woocommerce' ),
			)
		);
		register_post_status(
			'wc-' . self::SENT,
			array(
				'label'                     => _x( 'Quote sent', 'Order status', 'nextial-quotes-for-woocommerce' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of orders */
				'label_count'               => _n_noop( 'Quotes sent <span class="count">(%s)</span>', 'Quotes sent <span class="count">(%s)</span>', 'nextial-quotes-for-woocommerce' ),
			)
		);
	}

	/**
	 * Add to WooCommerce's list, right after "Pending payment".
	 *
	 * @param array $statuses Statuses.
	 * @return array
	 */
	public static function add_to_list( $statuses ) {
		$out = array();
		foreach ( $statuses as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'wc-pending' === $k ) {
				$out = array_merge( $out, self::labels() );
			}
		}
		if ( ! isset( $out[ 'wc-' . self::REQUEST ] ) ) {
			$out = array_merge( $out, self::labels() );
		}
		return $out;
	}

	/**
	 * A sent quote can be paid on the WooCommerce payment page.
	 *
	 * @param array $statuses Statuses without prefix.
	 * @return array
	 */
	public static function payable( $statuses ) {
		$statuses[] = self::SENT;
		return array_values( array_unique( $statuses ) );
	}

	/**
	 * Quotes are not sales: keep them out of Analytics.
	 *
	 * @param array $statuses Excluded statuses.
	 * @return array
	 */
	public static function exclude_from_analytics( $statuses ) {
		$statuses   = (array) $statuses;
		$statuses[] = self::REQUEST;
		$statuses[] = self::SENT;
		return array_values( array_unique( $statuses ) );
	}

	/**
	 * Legacy reports: remove quote statuses if present.
	 *
	 * @param array|false $statuses Statuses.
	 * @return array|false
	 */
	public static function exclude_from_legacy_reports( $statuses ) {
		if ( ! is_array( $statuses ) ) {
			return $statuses;
		}
		return array_values( array_diff( $statuses, array( self::REQUEST, self::SENT ) ) );
	}

	/**
	 * Never treat a quote as paid.
	 *
	 * @param array $statuses Paid statuses.
	 * @return array
	 */
	public static function not_paid( $statuses ) {
		return array_values( array_diff( (array) $statuses, array( self::REQUEST, self::SENT ) ) );
	}

	/**
	 * Is this order a quote (request or sent)?
	 *
	 * @param WC_Order|int $order Order.
	 * @return bool
	 */
	public static function is_quote( $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		return $order instanceof WC_Order && in_array( $order->get_status(), array( self::REQUEST, self::SENT ), true );
	}

	/**
	 * When a sent quote is paid, WooCommerce does not send its usual e-mails
	 * (they listen to pending→processing etc.). Send them here.
	 *
	 * @param int      $order_id Order ID.
	 * @param string   $from     Old status.
	 * @param string   $to       New status.
	 * @param WC_Order $order    Order.
	 */
	public static function after_payment( $order_id, $from, $to, $order = null ) {
		if ( self::SENT !== $from || ! in_array( $to, array( 'processing', 'on-hold', 'completed' ), true ) ) {
			return;
		}
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$order->add_order_note( __( 'Quote accepted by the customer.', 'nextial-quotes-for-woocommerce' ) );
		$mailer = WC()->mailer();
		$emails = $mailer->get_emails();
		if ( isset( $emails['WC_Email_New_Order'] ) ) {
			$emails['WC_Email_New_Order']->trigger( $order_id, $order );
		}
		$map = array(
			'processing' => 'WC_Email_Customer_Processing_Order',
			'on-hold'    => 'WC_Email_Customer_On_Hold_Order',
			'completed'  => 'WC_Email_Customer_Completed_Order',
		);
		if ( isset( $emails[ $map[ $to ] ] ) && 'completed' !== $to ) {
			$emails[ $map[ $to ] ]->trigger( $order_id, $order );
		}
		do_action( 'nextial_q_quote_accepted', $order_id, $order );
	}
}
