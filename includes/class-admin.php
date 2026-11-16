<?php
/**
 * Order screen: "Send quote to customer" in Order actions, quote box.
 *
 * @package Nextial_Quotes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin side (HPOS and classic order screens).
 */
class Nextial_Q_Admin {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_order_actions', array( __CLASS__, 'actions' ), 10, 2 );
		add_action( 'woocommerce_order_action_nxq_send_quote', array( __CLASS__, 'action_send' ) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'box' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	/**
	 * Add the action for quotes.
	 *
	 * @param array         $actions Actions.
	 * @param WC_Order|null $order   Order (WooCommerce 8.x+ passes it).
	 * @return array
	 */
	public static function actions( $actions, $order = null ) {
		if ( ! $order instanceof WC_Order ) {
			global $theorder;
			$order = $theorder instanceof WC_Order ? $theorder : null;
		}
		if ( $order && Nextial_Q_Statuses::is_quote( $order ) ) {
			$actions = array(
				'nxq_send_quote' => Nextial_Q_Statuses::SENT === $order->get_status()
					? __( 'Send the quote again', 'nextial-quotes-for-woocommerce' )
					: __( 'Send quote to customer', 'nextial-quotes-for-woocommerce' ),
			) + $actions;
		}
		return $actions;
	}

	/**
	 * Send the quote.
	 *
	 * @param WC_Order $order Order.
	 * @return true|WP_Error
	 */
	public static function send( WC_Order $order ) {
		if ( ! Nextial_Q_Statuses::is_quote( $order ) ) {
			return new WP_Error( 'nxq_state', __( 'Only quote requests can be sent as quotes.', 'nextial-quotes-for-woocommerce' ) );
		}
		if ( ! is_email( $order->get_billing_email() ) ) {
			return new WP_Error( 'nxq_email', __( 'The customer has no valid e-mail address.', 'nextial-quotes-for-woocommerce' ) );
		}
		if ( count( $order->get_items() ) < 1 || (float) $order->get_total() <= 0 ) {
			return new WP_Error( 'nxq_total', __( 'Set the prices first: the quote total is zero.', 'nextial-quotes-for-woocommerce' ) );
		}
		if ( Nextial_Q_Statuses::SENT !== $order->get_status() ) {
			$order->set_status( Nextial_Q_Statuses::SENT, __( 'Quote sent to the customer.', 'nextial-quotes-for-woocommerce' ) );
		} else {
			$order->add_order_note( __( 'Quote sent to the customer again.', 'nextial-quotes-for-woocommerce' ) );
		}
		$order->update_meta_data( '_nextial_quote_sent', time() );
		$order->save();
		/**
		 * The quote was sent (the e-mail goes out on this action).
		 *
		 * @param int      $order_id Order ID.
		 * @param WC_Order $order    Order.
		 */
		do_action( 'nextial_q_quote_sent', $order->get_id(), $order );
		return true;
	}

	/**
	 * Order action handler.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function action_send( $order ) {
		if ( ! $order instanceof WC_Order || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		$r = self::send( $order );
		set_transient( 'nxq_notice_' . get_current_user_id(), is_wp_error( $r ) ? array( 'error', $r->get_error_message() ) : array( 'success', __( 'Quote sent to the customer.', 'nextial-quotes-for-woocommerce' ) ), 60 );
	}

	/**
	 * Notice after the action.
	 */
	public static function notice() {
		$n = get_transient( 'nxq_notice_' . get_current_user_id() );
		if ( ! is_array( $n ) ) {
			return;
		}
		delete_transient( 'nxq_notice_' . get_current_user_id() );
		echo '<div class="notice notice-' . esc_attr( 'error' === $n[0] ? 'error' : 'success' ) . ' is-dismissible"><p>' . esc_html( $n[1] ) . '</p></div>';
	}

	/**
	 * Small box in the order details.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function box( $order ) {
		if ( ! $order instanceof WC_Order || ! $order->get_meta( '_nextial_quote' ) ) {
			return;
		}
		echo '<div class="form-field form-field-wide nxq-admin-box" style="margin-top:12px;padding:10px 12px;background:#f6f7f7;border-left:4px solid #2271b1">';
		if ( Nextial_Q_Statuses::REQUEST === $order->get_status() ) {
			echo '<strong>' . esc_html__( 'Quote request', 'nextial-quotes-for-woocommerce' ) . '</strong><br>' . esc_html__( 'Edit the item prices (or add shipping and fees), press Recalculate, then choose "Send quote to customer" in Order actions and Update.', 'nextial-quotes-for-woocommerce' );
		} elseif ( Nextial_Q_Statuses::SENT === $order->get_status() ) {
			$sent = (int) $order->get_meta( '_nextial_quote_sent' );
			echo '<strong>' . esc_html__( 'Quote sent', 'nextial-quotes-for-woocommerce' ) . '</strong>';
			if ( $sent ) {
				echo ' ' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $sent ) );
			}
			echo '<br>' . esc_html__( 'Payment link for the customer:', 'nextial-quotes-for-woocommerce' ) . ' <input type="text" readonly class="widefat" onclick="this.select()" value="' . esc_attr( $order->get_checkout_payment_url() ) . '">';
		} elseif ( $order->get_meta( '_nextial_quote_declined' ) ) {
			echo '<strong>' . esc_html__( 'Quote declined by the customer', 'nextial-quotes-for-woocommerce' ) . '</strong>';
		} else {
			echo '<strong>' . esc_html__( 'Created from a quote request', 'nextial-quotes-for-woocommerce' ) . '</strong>';
		}
		echo '</div>';
	}
}
