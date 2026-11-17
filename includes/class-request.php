<?php
/**
 * Quote request form → WooCommerce order; decline page.
 *
 * @package Nextial_Quotes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Requests.
 */
class Nextial_Q_Request {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'wp_loaded', array( __CLASS__, 'handle_post' ), 6 );
	}

	/**
	 * Form posts: update list, submit request, decline quote.
	 */
	public static function handle_post() {
		if ( is_admin() || 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- compared only.
			return;
		}
		if ( isset( $_POST['nxq_update_nonce'] ) && ( isset( $_POST['nxq_update'] ) || isset( $_POST['nxq_remove'] ) ) ) {
			if ( wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nxq_update_nonce'] ) ), 'nxq_update' ) ) {
				if ( isset( $_POST['nxq_remove'] ) ) {
					Nextial_Q_List::remove( sanitize_key( wp_unslash( $_POST['nxq_remove'] ) ) );
				} elseif ( isset( $_POST['nxq_qty'] ) && is_array( $_POST['nxq_qty'] ) ) {
					$q = array();
					foreach ( wp_unslash( $_POST['nxq_qty'] ) as $k => $v ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- key sanitised, value cast to int.
						$q[ sanitize_key( $k ) ] = (int) $v;
					}
					Nextial_Q_List::update( $q );
				}
				wc_add_notice( __( 'Quote list updated.', 'nextial-quotes-for-woocommerce' ), 'success' );
			} else {
				wc_add_notice( __( 'The page expired. Please try again.', 'nextial-quotes-for-woocommerce' ), 'error' );
			}
			wp_safe_redirect( Nextial_Q_Settings::page_url() );
			exit;
		}
		if ( isset( $_POST['nxq_submit'], $_POST['nxq_nonce'] ) ) {
			$result = self::submit( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- nonce and fields checked in submit().
			if ( is_wp_error( $result ) ) {
				wc_add_notice( $result->get_error_message(), 'error' );
				Nextial_Q_List::form_values( self::sticky( wp_unslash( $_POST ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised in sticky().
				wp_safe_redirect( Nextial_Q_Settings::page_url() );
			} else {
				wp_safe_redirect(
					add_query_arg(
						array(
							'nxq_done' => $result->get_id(),
							'key'      => $result->get_order_key(),
						),
						Nextial_Q_Settings::page_url()
					)
				);
			}
			exit;
		}
		if ( isset( $_POST['nxq_decline_order'], $_POST['nxq_decline_nonce'], $_POST['key'] ) ) {
			$order_id = absint( wp_unslash( $_POST['nxq_decline_order'] ) );
			$key      = sanitize_text_field( wp_unslash( $_POST['key'] ) );
			if ( wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nxq_decline_nonce'] ) ), 'nxq_decline_' . $order_id ) ) {
				$reason = isset( $_POST['nxq_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['nxq_reason'] ) ) : '';
				$r      = self::decline( $order_id, $key, $reason );
				if ( is_wp_error( $r ) ) {
					wc_add_notice( $r->get_error_message(), 'error' );
				} else {
					wc_add_notice( __( 'Thank you. We have recorded that you declined the quote.', 'nextial-quotes-for-woocommerce' ), 'success' );
				}
			} else {
				wc_add_notice( __( 'The page expired. Please try again.', 'nextial-quotes-for-woocommerce' ), 'error' );
			}
			wp_safe_redirect(
				add_query_arg(
					array(
						'nxq_decline' => $order_id,
						'key'         => $key,
					),
					Nextial_Q_Settings::page_url()
				)
			);
			exit;
		}
	}

	/**
	 * Anonymous visitor hash (for rate limit and sticky form). Never stored raw.
	 *
	 * @return string
	 */
	public static function visitor_hash() {
		$ip = class_exists( 'WC_Geolocation' ) ? WC_Geolocation::get_ip_address() : ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
		return substr( wp_hash( 'nxq|' . $ip ), 0, 20 );
	}

	/**
	 * Values to refill the form after an error.
	 *
	 * @param array $in Posted.
	 * @return array
	 */
	private static function sticky( array $in ) {
		$out = array();
		foreach ( array( 'nxq_name', 'nxq_email', 'nxq_phone', 'nxq_company' ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) ? sanitize_text_field( $in[ $k ] ) : '';
		}
		$out['nxq_message'] = isset( $in['nxq_message'] ) ? sanitize_textarea_field( $in['nxq_message'] ) : '';
		return $out;
	}

	/**
	 * Validate and create the quote order.
	 *
	 * @param array $in Unslashed POST.
	 * @return WC_Order|WP_Error
	 */
	public static function submit( array $in ) {
		if ( empty( $in['nxq_nonce'] ) || ! wp_verify_nonce( sanitize_key( $in['nxq_nonce'] ), 'nxq_submit' ) ) {
			return new WP_Error( 'nxq_nonce', __( 'The page expired. Please try again.', 'nextial-quotes-for-woocommerce' ) );
		}
		if ( ! empty( $in['nxq_website'] ) ) {
			return new WP_Error( 'nxq_spam', __( 'Your request could not be sent.', 'nextial-quotes-for-woocommerce' ) );
		}
		$items = Nextial_Q_List::items();
		if ( ! $items ) {
			return new WP_Error( 'nxq_empty', __( 'Your quote list is empty.', 'nextial-quotes-for-woocommerce' ) );
		}
		$name    = isset( $in['nxq_name'] ) ? sanitize_text_field( $in['nxq_name'] ) : '';
		$email   = isset( $in['nxq_email'] ) ? sanitize_email( $in['nxq_email'] ) : '';
		$phone   = isset( $in['nxq_phone'] ) ? wc_sanitize_phone_number( $in['nxq_phone'] ) : '';
		$company = isset( $in['nxq_company'] ) ? sanitize_text_field( $in['nxq_company'] ) : '';
		$message = isset( $in['nxq_message'] ) ? sanitize_textarea_field( $in['nxq_message'] ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'nxq_name', __( 'Please enter your name.', 'nextial-quotes-for-woocommerce' ) );
		}
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'nxq_email', __( 'Please enter a valid e-mail address.', 'nextial-quotes-for-woocommerce' ) );
		}
		if ( 'yes' === Nextial_Q_Settings::get( 'require_phone' ) && '' === $phone ) {
			return new WP_Error( 'nxq_phone', __( 'Please enter your phone number.', 'nextial-quotes-for-woocommerce' ) );
		}
		if ( '' !== (string) Nextial_Q_Settings::text( 'privacy_text' ) && empty( $in['nxq_privacy'] ) ) {
			return new WP_Error( 'nxq_privacy', __( 'Please accept the privacy terms.', 'nextial-quotes-for-woocommerce' ) );
		}
		$rate_key = 'nxq_rate_' . self::visitor_hash();
		$count    = (int) get_transient( $rate_key );
		if ( $count >= (int) Nextial_Q_Settings::get( 'max_per_hour' ) ) {
			return new WP_Error( 'nxq_rate', __( 'Too many requests. Please try again later.', 'nextial-quotes-for-woocommerce' ) );
		}
		if ( Nextial_Q_Settings::turnstile_on() ) {
			$check = self::verify_turnstile( isset( $in['cf-turnstile-response'] ) ? (string) $in['cf-turnstile-response'] : '' );
			if ( is_wp_error( $check ) ) {
				return $check;
			}
		}

		$order = wc_create_order(
			array(
				'status'      => Nextial_Q_Statuses::REQUEST,
				'customer_id' => get_current_user_id(),
				'created_via' => 'nextial-quotes',
			)
		);
		if ( is_wp_error( $order ) ) {
			return new WP_Error( 'nxq_order', __( 'Your request could not be saved. Please try again.', 'nextial-quotes-for-woocommerce' ) );
		}
		$added = 0;
		foreach ( $items as $item ) {
			$p = Nextial_Q_List::product( $item );
			if ( ! $p || ! Nextial_Q_Rules::is_quotable( $p ) ) {
				continue;
			}
			$args = array();
			if ( ! empty( $item['variation'] ) ) {
				$args['variation'] = $item['variation'];
			}
			$order->add_product( $p, max( 1, (int) $item['quantity'] ), $args );
			++$added;
		}
		if ( ! $added ) {
			$order->delete( true );
			Nextial_Q_List::clear();
			return new WP_Error( 'nxq_empty', __( 'The products in your list are no longer available for quotes.', 'nextial-quotes-for-woocommerce' ) );
		}
		$parts = preg_split( '/\s+/', trim( $name ), 2 );
		$order->set_billing_first_name( $parts[0] );
		$order->set_billing_last_name( isset( $parts[1] ) ? $parts[1] : '' );
		$order->set_billing_email( $email );
		$order->set_billing_phone( $phone );
		$order->set_billing_company( $company );
		$order->set_customer_note( $message );
		$order->set_currency( get_woocommerce_currency() );
		$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
		if ( class_exists( 'WC_Geolocation' ) ) {
			$order->set_customer_ip_address( WC_Geolocation::get_ip_address() );
		}
		$order->set_customer_user_agent( wc_get_user_agent() );
		$order->update_meta_data( '_nextial_quote', NEXTIAL_Q_VERSION );
		$lang = apply_filters( 'wpml_current_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML/Polylang API.
		if ( is_string( $lang ) && '' !== $lang ) {
			$order->update_meta_data( '_nextial_quote_lang', sanitize_key( $lang ) );
		}
		$order->calculate_totals();
		$order->save();
		$order->add_order_note( __( 'Quote request received. Adjust the prices if needed, then use Order actions → "Send quote to customer".', 'nextial-quotes-for-woocommerce' ) );

		set_transient( $rate_key, $count + 1, HOUR_IN_SECONDS );
		Nextial_Q_List::form_values( array() );
		Nextial_Q_List::clear();

		/**
		 * A quote request was created.
		 *
		 * @param int      $order_id Order ID.
		 * @param WC_Order $order    Order.
		 */
		do_action( 'nextial_q_request_created', $order->get_id(), $order );
		return $order;
	}

	/**
	 * Check a Cloudflare Turnstile token on the server (siteverify).
	 *
	 * @param string $token Token posted by the widget.
	 * @return true|WP_Error
	 */
	public static function verify_turnstile( $token ) {
		$token = substr( preg_replace( '/[^A-Za-z0-9_.\-:]/', '', $token ), 0, 2048 );
		$fail  = new WP_Error( 'nxq_turnstile', __( 'Please complete the anti-spam check and try again.', 'nextial-quotes-for-woocommerce' ) );
		if ( '' === $token ) {
			return $fail;
		}
		$body = array(
			'secret'   => (string) Nextial_Q_Settings::get( 'turnstile_secret' ),
			'response' => $token,
		);
		if ( class_exists( 'WC_Geolocation' ) ) {
			$body['remoteip'] = WC_Geolocation::get_ip_address();
		}
		$r = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'timeout' => 10,
				'body'    => $body,
			)
		);
		if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
			return new WP_Error( 'nxq_turnstile_down', __( 'The anti-spam check could not be completed. Please try again in a minute.', 'nextial-quotes-for-woocommerce' ) );
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $r ), true );
		return ( is_array( $data ) && ! empty( $data['success'] ) ) ? true : $fail;
	}

	/**
	 * The request form under the list.
	 */
	public static function render_form() {
		$v = Nextial_Q_List::form_values();
		if ( ! $v && is_user_logged_in() ) {
			$c = new WC_Customer( get_current_user_id() );
			$v = array(
				'nxq_name'    => trim( $c->get_billing_first_name() . ' ' . $c->get_billing_last_name() ),
				'nxq_email'   => $c->get_billing_email() ? $c->get_billing_email() : wp_get_current_user()->user_email,
				'nxq_phone'   => $c->get_billing_phone(),
				'nxq_company' => $c->get_billing_company(),
			);
		}
		$val     = function ( $k ) use ( $v ) {
			return isset( $v[ $k ] ) ? $v[ $k ] : '';
		};
		$phone_r = 'yes' === Nextial_Q_Settings::get( 'require_phone' );
		$privacy = (string) Nextial_Q_Settings::text( 'privacy_text' );
		?>
		<form method="post" action="<?php echo esc_url( Nextial_Q_Settings::page_url() ); ?>" class="nxq-form">
			<h3><?php esc_html_e( 'Your details', 'nextial-quotes-for-woocommerce' ); ?></h3>
			<?php wp_nonce_field( 'nxq_submit', 'nxq_nonce' ); ?>
			<p><label for="nxq_name"><?php esc_html_e( 'Name', 'nextial-quotes-for-woocommerce' ); ?> <abbr class="required" title="required">*</abbr></label><input type="text" id="nxq_name" name="nxq_name" required autocomplete="name" value="<?php echo esc_attr( $val( 'nxq_name' ) ); ?>"></p>
			<p><label for="nxq_email"><?php esc_html_e( 'E-mail', 'nextial-quotes-for-woocommerce' ); ?> <abbr class="required" title="required">*</abbr></label><input type="email" id="nxq_email" name="nxq_email" required autocomplete="email" value="<?php echo esc_attr( $val( 'nxq_email' ) ); ?>"></p>
			<p><label for="nxq_phone"><?php esc_html_e( 'Phone', 'nextial-quotes-for-woocommerce' ); ?><?php echo $phone_r ? ' <abbr class="required" title="required">*</abbr>' : ''; ?></label><input type="tel" id="nxq_phone" name="nxq_phone" autocomplete="tel" <?php echo $phone_r ? 'required' : ''; ?> value="<?php echo esc_attr( $val( 'nxq_phone' ) ); ?>"></p>
			<p><label for="nxq_company"><?php esc_html_e( 'Company', 'nextial-quotes-for-woocommerce' ); ?></label><input type="text" id="nxq_company" name="nxq_company" autocomplete="organization" value="<?php echo esc_attr( $val( 'nxq_company' ) ); ?>"></p>
			<p><label for="nxq_message"><?php esc_html_e( 'Message', 'nextial-quotes-for-woocommerce' ); ?></label><textarea id="nxq_message" name="nxq_message" rows="5"><?php echo esc_textarea( $val( 'nxq_message' ) ); ?></textarea></p>
			<p class="nxq-hp" aria-hidden="true"><label for="nxq_website">Website</label><input type="text" id="nxq_website" name="nxq_website" tabindex="-1" autocomplete="off" value=""></p>
			<?php if ( Nextial_Q_Settings::turnstile_on() ) : ?>
				<?php wp_enqueue_script( 'nxq-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, array( 'strategy' => 'defer', 'in_footer' => true ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Cloudflare requires the unversioned URL. ?>
				<div class="cf-turnstile nxq-turnstile" data-sitekey="<?php echo esc_attr( Nextial_Q_Settings::get( 'turnstile_site' ) ); ?>"></div>
			<?php endif; ?>
			<?php if ( '' !== $privacy ) : ?>
				<p><label style="font-weight:normal"><input type="checkbox" name="nxq_privacy" value="1" required> <?php echo esc_html( $privacy ); ?></label></p>
			<?php endif; ?>
			<p><button type="submit" class="button alt wp-element-button" name="nxq_submit" value="1"><?php esc_html_e( 'Send quote request', 'nextial-quotes-for-woocommerce' ); ?></button></p>
		</form>
		<?php
	}

	/**
	 * Order from ID + key (constant-time check).
	 *
	 * @param int    $order_id Order ID.
	 * @param string $key      Order key.
	 * @return WC_Order|null
	 */
	public static function order_with_key( $order_id, $key ) {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order || '' === (string) $key || ! hash_equals( (string) $order->get_order_key(), (string) $key ) ) {
			return null;
		}
		return $order;
	}

	/**
	 * Thank-you message after a request.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $key      Key.
	 */
	public static function render_done( $order_id, $key ) {
		$order = self::order_with_key( $order_id, $key );
		if ( ! $order ) {
			self::render_list();
			return;
		}
		echo '<div class="woocommerce-message" role="status">';
		/* translators: %s: request number */
		echo esc_html( sprintf( __( 'Thank you! We received your quote request #%s and will reply by e-mail.', 'nextial-quotes-for-woocommerce' ), $order->get_order_number() ) );
		echo '</div>';
		echo '<p><a class="button" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">' . esc_html__( 'Continue shopping', 'nextial-quotes-for-woocommerce' ) . '</a></p>';
	}

	/**
	 * Decline page (a POST button, so e-mail link scanners cannot decline by visiting the link).
	 *
	 * @param int    $order_id Order ID.
	 * @param string $key      Key.
	 */
	public static function render_decline( $order_id, $key ) {
		$order = self::order_with_key( $order_id, $key );
		if ( ! $order ) {
			echo '<p>' . esc_html__( 'This quote link is not valid.', 'nextial-quotes-for-woocommerce' ) . '</p>';
			return;
		}
		if ( Nextial_Q_Statuses::SENT !== $order->get_status() ) {
			if ( 'cancelled' === $order->get_status() && $order->get_meta( '_nextial_quote_declined' ) ) {
				echo '<p>' . esc_html__( 'You declined this quote.', 'nextial-quotes-for-woocommerce' ) . '</p>';
			} else {
				echo '<p>' . esc_html__( 'This quote can no longer be declined.', 'nextial-quotes-for-woocommerce' ) . '</p>';
			}
			return;
		}
		/* translators: %s: quote number */
		echo '<h3>' . esc_html( sprintf( __( 'Decline quote #%s', 'nextial-quotes-for-woocommerce' ), $order->get_order_number() ) ) . '</h3>';
		echo '<p>' . wp_kses_post( sprintf( /* translators: %s: link to pay */ __( 'Changed your mind? You can still <a href="%s">accept and pay</a>.', 'nextial-quotes-for-woocommerce' ), esc_url( $order->get_checkout_payment_url() ) ) ) . '</p>';
		echo '<form method="post" action="' . esc_url( Nextial_Q_Settings::page_url() ) . '" class="nxq-form">';
		wp_nonce_field( 'nxq_decline_' . $order->get_id(), 'nxq_decline_nonce' );
		echo '<input type="hidden" name="nxq_decline_order" value="' . esc_attr( $order->get_id() ) . '"><input type="hidden" name="key" value="' . esc_attr( $order->get_order_key() ) . '">';
		echo '<p><label for="nxq_reason">' . esc_html__( 'Reason (optional)', 'nextial-quotes-for-woocommerce' ) . '</label><textarea id="nxq_reason" name="nxq_reason" rows="3"></textarea></p>';
		echo '<p><button type="submit" class="button">' . esc_html__( 'Decline the quote', 'nextial-quotes-for-woocommerce' ) . '</button></p></form>';
	}

	/**
	 * Customer declines.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $key      Key.
	 * @param string $reason   Reason.
	 * @return true|WP_Error
	 */
	public static function decline( $order_id, $key, $reason = '' ) {
		$order = self::order_with_key( $order_id, $key );
		if ( ! $order ) {
			return new WP_Error( 'nxq_key', __( 'This quote link is not valid.', 'nextial-quotes-for-woocommerce' ) );
		}
		if ( Nextial_Q_Statuses::SENT !== $order->get_status() ) {
			return new WP_Error( 'nxq_state', __( 'This quote can no longer be declined.', 'nextial-quotes-for-woocommerce' ) );
		}
		$order->update_meta_data( '_nextial_quote_declined', time() );
		$order->update_status(
			'cancelled',
			'' !== $reason
				/* translators: %s: reason */
				? sprintf( __( 'Customer declined the quote. Reason: %s', 'nextial-quotes-for-woocommerce' ), $reason )
				: __( 'Customer declined the quote.', 'nextial-quotes-for-woocommerce' )
		);
		/**
		 * The customer declined a quote.
		 *
		 * @param int      $order_id Order ID.
		 * @param WC_Order $order    Order.
		 * @param string   $reason   Reason.
		 */
		do_action( 'nextial_q_quote_declined', $order->get_id(), $order, $reason );
		return true;
	}
}
