<?php
/**
 * Which products can be quoted.
 *
 * @package Nextial_Quotes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rules from the settings.
 */
class Nextial_Q_Rules {

	/**
	 * Can this product (or variation) be added to a quote?
	 *
	 * @param WC_Product|int $product Product.
	 * @return bool
	 */
	public static function is_quotable( $product ) {
		$product = $product instanceof WC_Product ? $product : wc_get_product( $product );
		if ( ! $product || 'publish' !== get_post_status( $product->get_parent_id() ? $product->get_parent_id() : $product->get_id() ) ) {
			$ok = false;
		} else {
			$ok = self::check( $product );
		}
		/**
		 * Filter whether a product can be quoted.
		 *
		 * @param bool            $ok      Result.
		 * @param WC_Product|null $product Product.
		 */
		return (bool) apply_filters( 'nextial_q_is_quotable', $ok, $product ? $product : null );
	}

	/**
	 * Rule check without the filter.
	 *
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	private static function check( WC_Product $product ) {
		$type = $product->get_type();
		if ( in_array( $type, array( 'external', 'grouped' ), true ) ) {
			return false;
		}
		$id      = self::original( $product->get_parent_id() ? $product->get_parent_id() : $product->get_id(), 'product' );
		$exclude = array_map( 'intval', (array) Nextial_Q_Settings::get( 'exclude' ) );
		if ( in_array( (int) $id, $exclude, true ) || in_array( (int) self::original( $product->get_id(), 'product_variation' ), $exclude, true ) ) {
			return false;
		}
		if ( 'categories' !== Nextial_Q_Settings::get( 'scope' ) ) {
			return true;
		}
		$wanted = array_map( 'intval', (array) Nextial_Q_Settings::get( 'categories' ) );
		if ( ! $wanted ) {
			return false;
		}
		$own  = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		$cats = wc_get_product_term_ids( $own, 'product_cat' );
		foreach ( $cats as $cat ) {
			$tree = array_merge( array( (int) $cat ), array_map( 'intval', get_ancestors( $cat, 'product_cat', 'taxonomy' ) ) );
			$tree = array_merge( $tree, array_map( array( __CLASS__, 'original_cat' ), $tree ) );
			if ( array_intersect( $tree, $wanted ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * On multilingual sites (WPML, Polylang) the settings hold IDs in the default language:
	 * map a translated product or category back to it. Without such a plugin the ID is unchanged.
	 *
	 * @param int    $id   Object ID.
	 * @param string $type Element type (product, product_variation, product_cat).
	 * @return int
	 */
	public static function original( $id, $type ) {
		$default = apply_filters( 'wpml_default_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML/Polylang API.
		if ( ! $default ) {
			return (int) $id;
		}
		$orig = apply_filters( 'wpml_object_id', (int) $id, $type, true, $default ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML/Polylang API.
		return $orig ? (int) $orig : (int) $id;
	}

	/**
	 * Category in the default language.
	 *
	 * @param int $id Term ID.
	 * @return int
	 */
	public static function original_cat( $id ) {
		return self::original( $id, 'product_cat' );
	}

	/**
	 * Quote-only product: "Add to cart" hidden and blocked.
	 *
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public static function is_quote_only( $product ) {
		return 'yes' === Nextial_Q_Settings::get( 'hide_add_to_cart' ) && self::is_quotable( $product );
	}

	/**
	 * Price hidden for this product.
	 *
	 * @param WC_Product $product Product.
	 * @return bool
	 */
	public static function hides_price( $product ) {
		return 'yes' === Nextial_Q_Settings::get( 'hide_price' ) && self::is_quotable( $product );
	}
}
