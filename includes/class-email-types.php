<?php
/**
 * The four e-mail types. Loaded only when WooCommerce builds its mailer (WC_Email exists).
 *
 * @package Nextial_Quotes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shared rendering.
 */
abstract class Nextial_Q_Email_Base extends WC_Email {

	/**
	 * Intro paragraph (plain text, may contain {placeholders}).
	 *
	 * @return string
	 */
	abstract protected function intro();

	/**
	 * Show prices in the item list.
	 *
	 * @return bool
	 */
	protected function with_prices() {
		return true;
	}

	/**
	 * Extra HTML after the intro (buttons).
	 *
	 * @return string
	 */
	protected function extra_html() {
		return '';
	}

	/**
	 * Extra plain text after the intro.
	 *
	 * @return string
	 */
	protected function extra_plain() {
		return '';
	}

	/**
	 * Send.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public function trigger( $order ) {
		$this->setup_locale();
		$sent = false;
		if ( $order instanceof WC_Order ) {
			$this->object                         = $order;
			$this->placeholders['{order_number}'] = $order->get_order_number();
			$this->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );
			$this->placeholders['{customer}']     = trim( $order->get_formatted_billing_full_name() );
			if ( ! $this->is_customer_email() ) {
				$this->recipient = Nextial_Q_Emails::admin_recipient();
			} else {
				$this->recipient = $order->get_billing_email();
			}
			if ( $this->is_enabled() && $this->get_recipient() ) {
				$sent = (bool) $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}
		}
		$this->restore_locale();
		return $sent;
	}

	/**
	 * Admin e-mails: reply goes to the customer.
	 *
	 * @return string
	 */
	public function get_headers() {
		$headers = parent::get_headers();
		if ( ! $this->is_customer_email() && $this->object instanceof WC_Order && is_email( $this->object->get_billing_email() ) ) {
			$name    = str_replace( array( "\r", "\n", '<', '>', '"' ), '', $this->object->get_formatted_billing_full_name() );
			$headers = preg_replace( '/^Reply-to:.*\r?\n?/mi', '', $headers );
			$headers .= 'Reply-to: ' . $name . ' <' . $this->object->get_billing_email() . ">\r\n";
		}
		return $headers;
	}

	/**
	 * HTML body.
	 *
	 * @return string
	 */
	public function get_content_html() {
		$order = $this->object;
		ob_start();
		wc_get_template( 'emails/email-header.php', array( 'email_heading' => $this->get_heading(), 'email' => $this ) );
		echo '<p>' . wp_kses_post( nl2br( $this->format_string( $this->intro() ) ) ) . '</p>';
		echo $this->extra_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_* in extra_html().
		if ( $order instanceof WC_Order ) {
			if ( $this->with_prices() ) {
				do_action( 'woocommerce_email_order_details', $order, ! $this->is_customer_email(), false, $this );
			} else {
				echo '<ul>';
				foreach ( $order->get_items() as $item ) {
					echo '<li>' . esc_html( $item->get_name() ) . ' &times; ' . esc_html( $item->get_quantity() ) . '</li>';
				}
				echo '</ul>';
			}
			if ( ! $this->is_customer_email() ) {
				do_action( 'woocommerce_email_customer_details', $order, true, false, $this );
			} elseif ( $order->get_customer_note() && ! $this->with_prices() ) {
				// With prices, the WooCommerce order table already shows the note.
				echo '<p><strong>' . esc_html__( 'Your message:', 'nextial-quotes-for-woocommerce' ) . '</strong><br>' . wp_kses_post( nl2br( esc_html( $order->get_customer_note() ) ) ) . '</p>';
			}
		}
		if ( $this->get_additional_content() ) {
			echo wp_kses_post( wpautop( wptexturize( $this->get_additional_content() ) ) );
		}
		wc_get_template( 'emails/email-footer.php', array( 'email' => $this ) );
		return (string) ob_get_clean();
	}

	/**
	 * Plain body.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		$order = $this->object;
		$out   = wp_strip_all_tags( $this->get_heading() ) . "\n\n" . wp_strip_all_tags( $this->format_string( $this->intro() ) ) . "\n\n";
		$out  .= $this->extra_plain();
		if ( $order instanceof WC_Order ) {
			foreach ( $order->get_items() as $item ) {
				$out .= '- ' . $item->get_name() . ' x ' . $item->get_quantity();
				if ( $this->with_prices() ) {
					$out .= ': ' . wp_strip_all_tags( $order->get_formatted_line_subtotal( $item ) );
				}
				$out .= "\n";
			}
			if ( $this->with_prices() ) {
				$out .= "\n" . __( 'Total:', 'nextial-quotes-for-woocommerce' ) . ' ' . wp_strip_all_tags( $order->get_formatted_order_total() ) . "\n";
			}
			if ( ! $this->is_customer_email() ) {
				$out .= "\n" . $order->get_formatted_billing_full_name() . ' <' . $order->get_billing_email() . '> ' . $order->get_billing_phone() . "\n" . $order->get_billing_company() . "\n";
			}
			if ( $order->get_customer_note() ) {
				$out .= "\n" . $order->get_customer_note() . "\n";
			}
		}
		if ( $this->get_additional_content() ) {
			$out .= "\n" . wp_strip_all_tags( wptexturize( $this->get_additional_content() ) ) . "\n";
		}
		return $out;
	}
}

/**
 * Admin: new quote request.
 */
class Nextial_Q_Email_New_Request extends Nextial_Q_Email_Base {
	/** Constructor. */
	public function __construct() {
		$this->id             = 'nextial_q_new_request';
		$this->title          = __( 'Quote request (to you)', 'nextial-quotes-for-woocommerce' );
		$this->description    = __( 'Sent to you when a customer asks for a quote.', 'nextial-quotes-for-woocommerce' );
		$this->customer_email = false;
		$this->placeholders   = array(
			'{order_number}' => '',
			'{order_date}'   => '',
			'{customer}'     => '',
		);
		parent::__construct();
		$this->recipient = Nextial_Q_Emails::admin_recipient();
	}
	/** @return string */
	public function get_default_subject() {
		return __( '[{site_title}]: New quote request #{order_number} from {customer}', 'nextial-quotes-for-woocommerce' );
	}
	/** @return string */
	public function get_default_heading() {
		return __( 'New quote request #{order_number}', 'nextial-quotes-for-woocommerce' );
	}
	/** @return string */
	protected function intro() {
		return __( '{customer} asked for a quote. Open the request, adjust the prices if needed, then use Order actions → "Send quote to customer".', 'nextial-quotes-for-woocommerce' );
	}
	/** @return string */
	protected function extra_html() {
		$o = $this->object;
		return $o instanceof WC_Order ? '<p><a href="' . esc_url( $o->get_edit_order_url() ) . '">' . esc_html__( 'Open the request', 'nextial-quotes-for-woocommerce' ) . '</a></p>' : '';
	}
	/** @return string */
	protected function extra_plain() {
		$o = $this->object;
		return $o instanceof WC_Order ? $o->get_edit_order_url() . "\n\n" : '';
	}
}

/**
 * Customer: request received.
 */
class Nextial_Q_Email_Request_Received extends Nextial_Q_Email_Base {
	/** Constructor. */
	public function __construct() {
		$this->id             = 'nextial_q_request_received';
		$this->title          = __( 'Quote request received (to the customer)', 'nextial-quotes-for-woocommerce' );
		$this->description    = __( 'Confirmation sent to the customer after a quote request.', 'nextial-quotes-for-woocommerce' );
		$this->customer_email = true;
		$this->placeholders   = array(
			'{order_number}' => '',
			'{order_date}'   => '',
			'{customer}'     => '',
		);
		parent::__construct();
	}
	/** @return string */
	public function get_default_subject() {
		return __( 'We received your quote request #{order_number}', 'nextial-quotes-for-woocommerce' );
	}
	/** @return string */
	public function get_default_heading() {
		return __( 'Thank you for your request', 'nextial-quotes-for-woocommerce' );
	}
	/** @return string */
	protected function intro() {
		return __( 'We received your quote request #{order_number} and will reply by e-mail with our prices. Here is what you asked for:', 'nextial-quotes-for-woocommerce' );
	}
	/** @return bool */
	protected function with_prices() {
		return false;
	}
}

/**
 * Customer: the quote, with Accept and pay / Decline.
 */
class Nextial_Q_Email_Quote extends Nextial_Q_Email_Base {
	/** Constructor. */
	public function __construct() {
		$this->id             = 'nextial_q_quote';
		$this->title          = __( 'Your quote (to the customer)', 'nextial-quotes-for-woocommerce' );
		$this->description    = __( 'The quote with prices, an "Accept and pay" button and a "Decline" link. Sent when you use Order actions → "Send quote to customer".', 'nextial-quotes-for-woocommerce' );
		$this->customer_email = true;
		$this->placeholders   = array(
			'{order_number}' => '',
			'{order_date}'   => '',
			'{customer}'     => '',
		);
		parent::__construct();
	}
	/** @return string */
	public function get_default_subject() {
		return __( 'Your quote #{order_number} from {site_title}', 'nextial-quotes-for-woocommerce' );
	}
	/** @return string */
	public function get_default_heading() {
		return __( 'Your quote is ready', 'nextial-quotes-for-woocommerce' );
	}
	/** @return string */
	protected function intro() {
		return __( 'Here is our quote #{order_number}. To accept it, press "Accept and pay" and complete the payment.', 'nextial-quotes-for-woocommerce' );
	}
	/** @return string */
	protected function extra_html() {
		$o = $this->object;
		if ( ! $o instanceof WC_Order ) {
			return '';
		}
		return '<p style="margin:24px 0"><a href="' . esc_url( $o->get_checkout_payment_url() ) . '" style="display:inline-block;padding:12px 22px;background:#1a1a1a;color:#ffffff;text-decoration:none;font-weight:bold;border-radius:4px">' . esc_html__( 'Accept and pay', 'nextial-quotes-for-woocommerce' ) . '</a>'
			. ' &nbsp; <a href="' . esc_url( Nextial_Q_Emails::decline_url( $o ) ) . '">' . esc_html__( 'Decline', 'nextial-quotes-for-woocommerce' ) . '</a></p>';
	}
	/** @return string */
	protected function extra_plain() {
		$o = $this->object;
		if ( ! $o instanceof WC_Order ) {
			return '';
		}
		return __( 'Accept and pay:', 'nextial-quotes-for-woocommerce' ) . ' ' . $o->get_checkout_payment_url() . "\n" . __( 'Decline:', 'nextial-quotes-for-woocommerce' ) . ' ' . Nextial_Q_Emails::decline_url( $o ) . "\n\n";
	}
}

/**
 * Admin: customer declined.
 */
class Nextial_Q_Email_Declined extends Nextial_Q_Email_Base {
	/**
	 * Reason given by the customer.
	 *
	 * @var string
	 */
	public $reason = '';
	/** Constructor. */
	public function __construct() {
		$this->id             = 'nextial_q_declined';
		$this->title          = __( 'Quote declined (to you)', 'nextial-quotes-for-woocommerce' );
		$this->description    = __( 'Sent to you when a customer declines a quote.', 'nextial-quotes-for-woocommerce' );
		$this->customer_email = false;
		$this->placeholders   = array(
			'{order_number}' => '',
			'{order_date}'   => '',
			'{customer}'     => '',
		);
		parent::__construct();
		$this->recipient = Nextial_Q_Emails::admin_recipient();
	}
	/** @return string */
	public function get_default_subject() {
		return __( '[{site_title}]: Quote #{order_number} declined by {customer}', 'nextial-quotes-for-woocommerce' );
	}
	/** @return string */
	public function get_default_heading() {
		return __( 'Quote #{order_number} declined', 'nextial-quotes-for-woocommerce' );
	}
	/** @return string */
	protected function intro() {
		$text = __( '{customer} declined quote #{order_number}.', 'nextial-quotes-for-woocommerce' );
		if ( '' !== $this->reason ) {
			$text .= "\n" . __( 'Reason:', 'nextial-quotes-for-woocommerce' ) . ' ' . $this->reason;
		}
		return $text;
	}
}
