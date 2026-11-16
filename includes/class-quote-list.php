<?php
/**
 * The visitor's quote list, kept in the WooCommerce session (works for guests).
 *
 * @package Nextial_Quotes
 */

defined( 'ABSPATH' ) || exit;

/**
 * List of products the visitor wants a quote for.
 */
class Nextial_Q_List {

	const KEY = 'nextial_q_list';
	const MAX = 100;

	/**
	 * Make sure a session exists and has a cookie.
	 *
	 * @return bool
	 */
	private static function session() {
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}
		if ( null === WC()->session && method_exists( WC(), 'initialize_session' ) ) {
			WC()->initialize_session();
		}
		if ( ! WC()->session ) {
			return false;
		}
		if ( method_exists( WC()->session, 'has_session' ) && ! WC()->session->has_session() && method_exists( WC()->session, 'set_customer_session_cookie' ) ) {
			WC()->session->set_customer_session_cookie( true );
		}
		return true;
	}

	/**
	 * Items: key => array( product_id, variation_id, variation, quantity ).
	 *
	 * @return array
	 */
	public static function items() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return array();
		}
		$items = WC()->session->get( self::KEY, array() );
		return is_array( $items ) ? $items : array();
	}

	/**
	 * Number of lines.
	 *
	 * @return int
	 */
	public static function count() {
		return count( self::items() );
	}

	/**
	 * Save items.
	 *
	 * @param array $items Items.
	 */
	private static function store( array $items ) {
		// No session (no cookie, no database row) until a product is really added.
		if ( ! $items && ( ! function_exists( 'WC' ) || ! WC()->session || ( method_exists( WC()->session, 'has_session' ) && ! WC()->session->has_session() ) ) ) {
			return;
		}
		if ( self::session() ) {
			WC()->session->set( self::KEY, $items );
		}
	}

	/**
	 * Add a product or variation.
	 *
	 * @param int   $product_id   Product (parent) ID.
	 * @param int   $quantity     Quantity.
	 * @param int   $variation_id Variation ID or 0.
	 * @param array $variation    attribute_x => value.
	 * @return true|WP_Error
	 */
	public static function add( $product_id, $quantity = 1, $variation_id = 0, array $variation = array() ) {
		$product_id   = absint( $product_id );
		$variation_id = absint( $variation_id );
		$quantity     = max( 1, min( 100000, (int) $quantity ) );
		$product      = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'nxq_missing', __( 'This product does not exist.', 'nextial-quotes-for-woocommerce' ) );
		}
		if ( $product->is_type( 'variable' ) ) {
			$variation_product = $variation_id ? wc_get_product( $variation_id ) : null;
			if ( ! $variation_product || (int) $variation_product->get_parent_id() !== $product_id ) {
				return new WP_Error( 'nxq_choose', __( 'Please choose the product options before adding it to the quote.', 'nextial-quotes-for-woocommerce' ) );
			}
			$variation = self::clean_variation( $variation_product, $variation );
			if ( is_wp_error( $variation ) ) {
				return $variation;
			}
			$target = $variation_product;
		} else {
			$variation_id = 0;
			$variation    = array();
			$target       = $product;
		}
		if ( ! Nextial_Q_Rules::is_quotable( $target ) ) {
			return new WP_Error( 'nxq_not_quotable', __( 'This product cannot be added to a quote.', 'nextial-quotes-for-woocommerce' ) );
		}
		$items = self::items();
		$key   = md5( $product_id . '|' . $variation_id . '|' . wp_json_encode( $variation ) );
		if ( isset( $items[ $key ] ) ) {
			$items[ $key ]['quantity'] = min( 100000, $items[ $key ]['quantity'] + $quantity );
		} else {
			if ( count( $items ) >= self::MAX ) {
				return new WP_Error( 'nxq_full', __( 'Your quote list is full.', 'nextial-quotes-for-woocommerce' ) );
			}
			$items[ $key ] = array(
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'variation'    => $variation,
				'quantity'     => $quantity,
			);
		}
		self::store( $items );
		return true;
	}

	/**
	 * Keep only the variation's real attributes; "any" attributes must be chosen.
	 *
	 * @param WC_Product $variation_product Variation.
	 * @param array      $posted            Posted attributes.
	 * @return array|WP_Error
	 */
	private static function clean_variation( WC_Product $variation_product, array $posted ) {
		$out = array();
		foreach ( $variation_product->get_variation_attributes() as $name => $value ) {
			$name = (string) $name; // attribute_pa_color.
			if ( '' !== (string) $value ) {
				$out[ $name ] = (string) $value;
				continue;
			}
			$chosen = isset( $posted[ $name ] ) ? wc_clean( wp_unslash( $posted[ $name ] ) ) : '';
			if ( '' === $chosen ) {
				return new WP_Error( 'nxq_choose', __( 'Please choose the product options before adding it to the quote.', 'nextial-quotes-for-woocommerce' ) );
			}
			$out[ $name ] = (string) $chosen;
		}
		return $out;
	}

	/**
	 * Change quantities; 0 removes.
	 *
	 * @param array $quantities key => qty.
	 */
	public static function update( array $quantities ) {
		$items = self::items();
		foreach ( $quantities as $key => $qty ) {
			$key = (string) $key;
			if ( ! isset( $items[ $key ] ) ) {
				continue;
			}
			$qty = (int) $qty;
			if ( $qty <= 0 ) {
				unset( $items[ $key ] );
			} else {
				$items[ $key ]['quantity'] = min( 100000, $qty );
			}
		}
		self::store( $items );
	}

	/**
	 * Remove one line.
	 *
	 * @param string $key Key.
	 */
	public static function remove( $key ) {
		$items = self::items();
		unset( $items[ (string) $key ] );
		self::store( $items );
	}

	/**
	 * Empty the list.
	 */
	public static function clear() {
		self::store( array() );
	}

	/**
	 * Form values kept after an error, in this visitor's own WooCommerce session
	 * (never shared between visitors behind the same IP, proxy or CDN).
	 *
	 * @param array|null $values Values to keep; null to read; empty array to forget.
	 * @return array
	 */
	public static function form_values( $values = null ) {
		if ( ! function_exists( 'WC' ) || ! WC()->session || ( method_exists( WC()->session, 'has_session' ) && ! WC()->session->has_session() ) ) {
			return array();
		}
		if ( null === $values ) {
			$v = WC()->session->get( 'nextial_q_form', array() );
			return is_array( $v ) ? $v : array();
		}
		WC()->session->set( 'nextial_q_form', $values ? $values : null );
		return $values;
	}

	/**
	 * Product object for a line (variation if any).
	 *
	 * @param array $item Item.
	 * @return WC_Product|null
	 */
	public static function product( array $item ) {
		$p = wc_get_product( ! empty( $item['variation_id'] ) ? $item['variation_id'] : $item['product_id'] );
		return $p ? $p : null;
	}
}
