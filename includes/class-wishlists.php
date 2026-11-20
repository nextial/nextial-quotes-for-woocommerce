<?php
/**
 * "Request a quote for this wishlist": one button on the wishlist page of
 * YITH WooCommerce Wishlist and TI WooCommerce Wishlist (a paid feature in both premium versions).
 *
 * @package Nextial_Quotes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wishlist integrations.
 */
class Nextial_Q_Wishlists {

	/**
	 * Hooks (only when a supported wishlist plugin is active).
	 */
	public static function init() {
		add_action( 'yith_wcwl_after_wishlist_form', array( __CLASS__, 'yith_button' ), 20 );
		add_action( 'tinvwl_after_wishlist', array( __CLASS__, 'ti_button' ), 20 );
		add_action( 'wp_loaded', array( __CLASS__, 'handle' ), 6 );
	}

	/**
	 * Button on/off (filter for shops that do not want it).
	 *
	 * @return bool
	 */
	private static function enabled() {
		/**
		 * Show the "Request a quote for this wishlist" button.
		 *
		 * @param bool $show Default true.
		 */
		return (bool) apply_filters( 'nextial_quotes_wishlist_button', true );
	}

	/**
	 * YITH wishlist page.
	 *
	 * @param mixed $wishlist YITH_WCWL_Wishlist.
	 */
	public static function yith_button( $wishlist ) {
		if ( ! is_object( $wishlist ) || ! method_exists( $wishlist, 'get_token' ) ) {
			return;
		}
		$lines = self::yith_lines( (string) $wishlist->get_token() );
		if ( is_array( $lines ) && $lines ) {
			self::button( 'yith', (string) $wishlist->get_token() );
		}
	}

	/**
	 * TI wishlist page.
	 *
	 * @param mixed $wishlist Wishlist array.
	 */
	public static function ti_button( $wishlist ) {
		if ( ! is_array( $wishlist ) || empty( $wishlist['share_key'] ) ) {
			return; // Guest lists kept only in the browser have no server copy: nothing to read.
		}
		$lines = self::ti_lines( (string) $wishlist['share_key'] );
		if ( is_array( $lines ) && $lines ) {
			self::button( 'ti', (string) $wishlist['share_key'] );
		}
	}

	/**
	 * The form. No nonce, like "Add to quote": cache-safe and it only changes the visitor's own quote list.
	 *
	 * @param string $source yith|ti.
	 * @param string $token  Wishlist token / share key.
	 */
	private static function button( $source, $token ) {
		if ( ! self::enabled() ) {
			return;
		}
		echo '<form method="post" action="' . esc_url( Nextial_Q_Settings::page_url() ) . '" class="nxq-wishlist-quote">';
		echo '<input type="hidden" name="nxq_wishlist" value="' . esc_attr( $source ) . '">';
		echo '<input type="hidden" name="nxq_wishlist_token" value="' . esc_attr( $token ) . '">';
		echo '<button type="submit" class="button wp-element-button nxq-wishlist-button">' . esc_html__( 'Request a quote for this list', 'nextial-quotes-for-woocommerce' ) . '</button>';
		echo '</form>';
	}

	/**
	 * Products of a YITH wishlist the visitor may view.
	 *
	 * @param string $token Token.
	 * @return array|null Lines (product, quantity) or null when not readable.
	 */
	public static function yith_lines( $token ) {
		if ( ! class_exists( 'YITH_WCWL_Wishlist_Factory' ) || ! preg_match( '/\A[A-Za-z0-9]{1,64}\z/', $token ) ) {
			return null;
		}
		$wishlist = YITH_WCWL_Wishlist_Factory::get_wishlist( $token );
		if ( ! $wishlist || ! is_object( $wishlist ) || ! $wishlist->current_user_can( 'view' ) ) {
			return null;
		}
		$lines = array();
		foreach ( (array) $wishlist->get_items( Nextial_Q_List::MAX ) as $item ) {
			if ( is_object( $item ) && method_exists( $item, 'get_product_id' ) ) {
				$lines[] = array(
					'id'       => (int) $item->get_product_id(),
					'quantity' => method_exists( $item, 'get_quantity' ) ? (int) $item->get_quantity() : 1,
				);
			}
		}
		return $lines;
	}

	/**
	 * Products of a TI wishlist the visitor may view (privacy checked by TI).
	 *
	 * @param string $share_key Share key.
	 * @return array|null
	 */
	public static function ti_lines( $share_key ) {
		if ( ! class_exists( 'TInvWL_Wishlist' ) || ! class_exists( 'TInvWL_Product' ) || ! preg_match( '/\A[a-f0-9]{6,32}\z/i', $share_key ) ) {
			return null;
		}
		$wl       = new TInvWL_Wishlist();
		$wishlist = $wl->get_by_share_key( $share_key );
		if ( empty( $wishlist['ID'] ) ) {
			return null;
		}
		$wlp   = new TInvWL_Product( $wishlist, defined( 'TINVWL_PREFIX' ) ? TINVWL_PREFIX : 'tinvwl', true );
		$rows  = $wlp->get_wishlist(
			array(
				'count'    => Nextial_Q_List::MAX,
				'external' => false,
			)
		);
		$lines = array();
		foreach ( (array) $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['product_id'] ) ) {
				continue;
			}
			$lines[] = array(
				'id'       => ! empty( $row['variation_id'] ) ? (int) $row['variation_id'] : (int) $row['product_id'],
				'quantity' => isset( $row['quantity'] ) ? (int) $row['quantity'] : 1,
			);
		}
		return $lines;
	}

	/**
	 * Add the wishlist to the quote list, then show the quote page.
	 * The products are read again from the wishlist plugin: posted IDs are never trusted.
	 */
	public static function handle() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- see button(): visitor's own list only.
		if ( empty( $_POST['nxq_wishlist'] ) || empty( $_POST['nxq_wishlist_token'] ) || is_admin() || ! self::enabled() ) {
			return;
		}
		$source = sanitize_key( wp_unslash( $_POST['nxq_wishlist'] ) );
		$token  = sanitize_text_field( wp_unslash( $_POST['nxq_wishlist_token'] ) );
		// phpcs:enable
		$lines = null;
		if ( 'yith' === $source ) {
			$lines = self::yith_lines( $token );
		} elseif ( 'ti' === $source ) {
			$lines = self::ti_lines( $token );
		}
		$added   = 0;
		$skipped = array();
		foreach ( (array) $lines as $line ) {
			$result = self::add_line( $line['id'], $line['quantity'] );
			if ( true === $result ) {
				++$added;
			} elseif ( $result ) {
				$skipped[] = $result;
			}
		}
		if ( null === $lines ) {
			// A visitor with no session yet (nothing added) needs one, or the message is lost on the redirect.
			if ( WC()->session && method_exists( WC()->session, 'has_session' ) && ! WC()->session->has_session() && method_exists( WC()->session, 'set_customer_session_cookie' ) ) {
				WC()->session->set_customer_session_cookie( true );
			}
			wc_add_notice( __( 'This wishlist cannot be read.', 'nextial-quotes-for-woocommerce' ), 'error' );
		} elseif ( $added ) {
			wc_add_notice(
				/* translators: %d: number of products */
				sprintf( _n( '%d product from your wishlist was added to your quote.', '%d products from your wishlist were added to your quote.', $added, 'nextial-quotes-for-woocommerce' ), $added ),
				'success'
			);
		}
		if ( $skipped ) {
			wc_add_notice(
				/* translators: %s: product names */
				sprintf( __( 'Not added (not available for quotes, or options to choose on the product page): %s', 'nextial-quotes-for-woocommerce' ), esc_html( implode( ', ', array_slice( $skipped, 0, 10 ) ) ) ),
				'notice'
			);
		} elseif ( is_array( $lines ) && ! $added ) {
			wc_add_notice( __( 'This wishlist is empty.', 'nextial-quotes-for-woocommerce' ), 'notice' );
		}
		wp_safe_redirect( Nextial_Q_Settings::page_url() );
		exit;
	}

	/**
	 * One product or variation.
	 *
	 * @param int $id       Product or variation ID.
	 * @param int $quantity Quantity.
	 * @return true|string|false True, the product name when skipped, false when the product is gone.
	 */
	private static function add_line( $id, $quantity ) {
		$product = wc_get_product( absint( $id ) );
		if ( ! $product || 'publish' !== get_post_status( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() ) ) {
			return false;
		}
		$quantity = max( 1, (int) $quantity );
		if ( $product->is_type( 'variation' ) ) {
			$attributes = array();
			foreach ( $product->get_variation_attributes() as $name => $value ) {
				$attributes[ (string) $name ] = (string) $value;
			}
			$result = Nextial_Q_List::add( $product->get_parent_id(), $quantity, $product->get_id(), $attributes );
		} else {
			$result = Nextial_Q_List::add( $product->get_id(), $quantity );
		}
		return is_wp_error( $result ) ? $product->get_name() : true;
	}
}
