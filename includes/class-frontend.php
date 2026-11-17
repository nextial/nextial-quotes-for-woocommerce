<?php
/**
 * Front end: "Add to quote" buttons, hidden prices, the quote page.
 *
 * Plain HTML forms, no JavaScript needed: works with any theme and page builder.
 *
 * @package Nextial_Quotes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Front end.
 */
class Nextial_Q_Frontend {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_after_add_to_cart_button', array( __CLASS__, 'single_button' ) );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'single_fallback' ), 31 );
		add_filter( 'woocommerce_loop_add_to_cart_link', array( __CLASS__, 'loop_link' ), 20, 2 );
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'price_html' ), 100, 2 );
		add_filter( 'woocommerce_available_variation', array( __CLASS__, 'variation_data' ), 100, 3 );
		add_filter( 'woocommerce_structured_data_product', array( __CLASS__, 'structured_data' ), 100, 2 );
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'store_api_prices' ), 100, 3 );
		add_filter( 'woocommerce_hydration_request_after_callbacks', array( __CLASS__, 'store_api_prices' ), 100, 3 );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'block_cart' ), 100, 3 );
		add_action( 'woocommerce_store_api_validate_add_to_cart', array( __CLASS__, 'block_store_api' ), 10, 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'styles' ) );
		add_action( 'wp_loaded', array( __CLASS__, 'handle_add' ), 5 );
		add_shortcode( 'nextial_quote', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Button inside the add-to-cart form (simple and variable products).
	 */
	public static function single_button() {
		global $product;
		if ( ! $product instanceof WC_Product || ! Nextial_Q_Rules::is_quotable( $product ) ) {
			return;
		}
		if ( ! $product->is_type( array( 'simple', 'variable' ) ) && ! apply_filters( 'nextial_q_button_for_type', false, $product ) ) {
			return;
		}
		echo '<button type="submit" name="nxq_add" value="' . esc_attr( $product->get_id() ) . '" class="button alt wp-element-button nxq-add-button">' . esc_html( Nextial_Q_Settings::text( 'button_label' ) ) . '</button>';
	}

	/**
	 * Products WooCommerce shows without an add-to-cart form (out of stock, no price):
	 * still offer a small quote form.
	 */
	public static function single_fallback() {
		global $product;
		if ( ! $product instanceof WC_Product || ! $product->is_type( 'simple' ) || ! Nextial_Q_Rules::is_quotable( $product ) ) {
			return;
		}
		if ( $product->is_purchasable() && $product->is_in_stock() ) {
			return; // The normal form already shows our button.
		}
		echo '<form class="cart nxq-fallback" method="post" action="' . esc_url( get_permalink( $product->get_id() ) ) . '">';
		echo '<input type="number" name="quantity" value="1" min="1" step="1" class="input-text qty text" aria-label="' . esc_attr__( 'Quantity', 'nextial-quotes-for-woocommerce' ) . '"> ';
		echo '<button type="submit" name="nxq_add" value="' . esc_attr( $product->get_id() ) . '" class="button alt wp-element-button nxq-add-button">' . esc_html( Nextial_Q_Settings::text( 'button_label' ) ) . '</button>';
		echo '</form>';
	}

	/**
	 * Shop and category pages.
	 *
	 * @param string     $html    Link.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function loop_link( $html, $product ) {
		if ( ! $product instanceof WC_Product || ! Nextial_Q_Rules::is_quotable( $product ) ) {
			return $html;
		}
		$quote_only = Nextial_Q_Rules::is_quote_only( $product );
		if ( 'yes' !== Nextial_Q_Settings::get( 'show_in_loop' ) ) {
			if ( $quote_only ) {
				return '<a href="' . esc_url( $product->get_permalink() ) . '" class="button">' . esc_html__( 'View product', 'nextial-quotes-for-woocommerce' ) . '</a>';
			}
			return $html;
		}
		if ( $product->is_type( 'simple' ) ) {
			$url  = add_query_arg( 'nxq_add', $product->get_id(), $product->get_permalink() );
			$link = '<a href="' . esc_url( $url ) . '" rel="nofollow" class="button wp-element-button nxq-add-button">' . esc_html( Nextial_Q_Settings::text( 'button_label' ) ) . '</a>';
		} else {
			$link = '<a href="' . esc_url( $product->get_permalink() ) . '" class="button wp-element-button nxq-add-button">' . esc_html( Nextial_Q_Settings::text( 'button_label' ) ) . '</a>';
		}
		return $quote_only ? $link : $html . ' ' . $link;
	}

	/**
	 * Hide the price when set.
	 *
	 * @param string     $html    Price HTML.
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function price_html( $html, $product ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $html;
		}
		if ( $product instanceof WC_Product && Nextial_Q_Rules::hides_price( $product ) ) {
			return '';
		}
		return $html;
	}

	/**
	 * Hidden price: keep it out of the product's structured data (JSON-LD) too.
	 *
	 * @param array      $markup  Markup.
	 * @param WC_Product $product Product.
	 * @return array
	 */
	public static function structured_data( $markup, $product ) {
		if ( is_array( $markup ) && $product instanceof WC_Product && Nextial_Q_Rules::hides_price( $product ) ) {
			unset( $markup['offers'] );
		}
		return $markup;
	}

	/**
	 * Hidden price: also blank it in public Store API product responses (used by block themes,
	 * also preloaded inside the page).
	 *
	 * @param mixed           $response Response.
	 * @param array           $handler  Handler.
	 * @param WP_REST_Request $request  Request.
	 * @return mixed
	 */
	public static function store_api_prices( $response, $handler, $request ) {
		if ( 'yes' !== Nextial_Q_Settings::get( 'hide_price' ) || ! $response instanceof WP_REST_Response || ! $request instanceof WP_REST_Request ) {
			return $response;
		}
		if ( 0 !== strpos( (string) $request->get_route(), '/wc/store/' ) || false === strpos( (string) $request->get_route(), '/products' ) ) {
			return $response;
		}
		$data = $response->get_data();
		if ( ! is_array( $data ) ) {
			return $response;
		}
		if ( isset( $data['id'], $data['prices'] ) ) {
			$data = self::blank_prices( $data );
		} else {
			foreach ( $data as $i => $row ) {
				if ( is_array( $row ) && isset( $row['id'], $row['prices'] ) ) {
					$data[ $i ] = self::blank_prices( $row );
				}
			}
		}
		$response->set_data( $data );
		return $response;
	}

	/**
	 * Blank the price fields of one Store API product.
	 *
	 * @param array $row Product data.
	 * @return array
	 */
	private static function blank_prices( array $row ) {
		$product = wc_get_product( (int) $row['id'] );
		if ( ! $product || ! Nextial_Q_Rules::hides_price( $product ) ) {
			return $row;
		}
		$is_object = is_object( $row['prices'] );
		$prices    = (array) $row['prices'];
		foreach ( array( 'price', 'regular_price', 'sale_price' ) as $k ) {
			if ( array_key_exists( $k, $prices ) ) {
				$prices[ $k ] = '';
			}
		}
		if ( array_key_exists( 'price_range', $prices ) ) {
			$prices['price_range'] = null;
		}
		$row['prices'] = $is_object ? (object) $prices : $prices;
		if ( isset( $row['price_html'] ) ) {
			$row['price_html'] = '';
		}
		$row['on_sale'] = false;
		return $row;
	}

	/**
	 * Variation data sent to the variation form.
	 *
	 * @param array                $data      Data.
	 * @param WC_Product           $parent    Parent.
	 * @param WC_Product_Variation $variation Variation.
	 * @return array
	 */
	public static function variation_data( $data, $parent, $variation ) {
		if ( Nextial_Q_Rules::hides_price( $variation ) ) {
			$data['price_html'] = '';
		}
		return $data;
	}

	/**
	 * Server-side block of add to cart for quote-only products.
	 *
	 * @param bool $passed     Passed.
	 * @param int  $product_id Product.
	 * @param int  $quantity   Quantity.
	 * @return bool
	 */
	public static function block_cart( $passed, $product_id, $quantity = 1 ) {
		$product = wc_get_product( $product_id );
		if ( $passed && $product && Nextial_Q_Rules::is_quote_only( $product ) ) {
			wc_add_notice( __( 'This product is available on request: please ask for a quote.', 'nextial-quotes-for-woocommerce' ), 'error' );
			return false;
		}
		return $passed;
	}

	/**
	 * Same block for the Cart/Checkout blocks (Store API).
	 *
	 * @param WC_Product $product Product.
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException When quote-only.
	 */
	public static function block_store_api( $product ) {
		$check = $product instanceof WC_Product && $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
		if ( $check instanceof WC_Product && Nextial_Q_Rules::is_quote_only( $check ) && class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'nextial_q_quote_only', esc_html__( 'This product is available on request: please ask for a quote.', 'nextial-quotes-for-woocommerce' ), 400 );
		}
	}

	/**
	 * Small CSS: hide the native button on quote-only product pages.
	 */
	public static function styles() {
		wp_register_style( 'nextial-quotes', false, array(), NEXTIAL_Q_VERSION );
		wp_enqueue_style( 'nextial-quotes' );
		$css = '.nxq-add-button{margin-left:.5em}.nxq-table input.qty{width:5em}.nxq-form p{margin:0 0 1em}.nxq-form label{display:block;font-weight:600}.nxq-form input[type=text],.nxq-form input[type=email],.nxq-form input[type=tel],.nxq-form textarea{width:100%;max-width:560px;padding:.5em;font:inherit;box-sizing:border-box}.nxq-table input.qty{padding:.3em;font:inherit}.nxq-hp{position:absolute!important;left:-9999px!important}form.cart:has(.nxq-add-button){display:flex;flex-wrap:wrap;align-items:center;gap:.5em}form.cart:has(.nxq-add-button)>*{float:none;margin-top:0;margin-bottom:0}form.cart>.nxq-add-button{width:auto;flex:0 0 auto}';
		if ( is_product() ) {
			$product = wc_get_product( get_queried_object_id() );
			if ( $product && Nextial_Q_Rules::is_quote_only( $product ) ) {
				$css .= '.single_add_to_cart_button{display:none!important}.nxq-add-button{margin-left:0}';
			}
		}
		wp_add_inline_style( 'nextial-quotes', $css );
	}

	/**
	 * "Add to quote" from the product form (POST) or the shop link (GET).
	 * Like WooCommerce's own add-to-cart, this works without a nonce so it survives page caching;
	 * it only changes the visitor's own list.
	 */
	public static function handle_add() {
		// phpcs:disable WordPress.Security.NonceVerification -- same model as WooCommerce add-to-cart (cache-safe, visitor's own list only).
		if ( empty( $_REQUEST['nxq_add'] ) || is_admin() ) {
			return;
		}
		$product_id   = absint( wp_unslash( $_REQUEST['nxq_add'] ) );
		$quantity     = isset( $_REQUEST['quantity'] ) ? (int) wc_stock_amount( sanitize_text_field( wp_unslash( $_REQUEST['quantity'] ) ) ) : 1;
		$variation_id = isset( $_REQUEST['variation_id'] ) ? absint( wp_unslash( $_REQUEST['variation_id'] ) ) : 0;
		$attributes   = array();
		foreach ( $_REQUEST as $k => $v ) {
			if ( is_string( $k ) && 0 === strpos( $k, 'attribute_' ) && is_string( $v ) ) {
				$attributes[ sanitize_title( $k ) ] = wc_clean( wp_unslash( $v ) );
			}
		}
		// phpcs:enable
		$result = Nextial_Q_List::add( $product_id, $quantity ? $quantity : 1, $variation_id, $attributes );
		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );
			$back = get_permalink( $product_id );
		} else {
			$name = get_the_title( $product_id );
			wc_add_notice(
				sprintf(
					/* translators: 1: product name, 2: link to the quote page */
					__( '"%1$s" was added to your quote. %2$s', 'nextial-quotes-for-woocommerce' ),
					esc_html( $name ),
					'<a href="' . esc_url( Nextial_Q_Settings::page_url() ) . '" class="button wc-forward">' . esc_html__( 'View quote', 'nextial-quotes-for-woocommerce' ) . '</a>'
				),
				'success'
			);
			$back = wp_get_referer() ? wp_get_referer() : get_permalink( $product_id );
		}
		$back = remove_query_arg( array( 'nxq_add', 'quantity' ), $back ? $back : home_url( '/' ) );
		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * [nextial_quote]: the list, the request form, or the decline page.
	 *
	 * @return string
	 */
	public static function shortcode() {
		if ( ! function_exists( 'WC' ) ) {
			return '';
		}
		ob_start();
		echo '<div class="woocommerce nxq-quote">';
		if ( function_exists( 'wc_print_notices' ) && WC()->session ) {
			wc_print_notices();
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only views; the order key is checked.
		if ( isset( $_GET['nxq_decline'], $_GET['key'] ) ) {
			Nextial_Q_Request::render_decline( absint( wp_unslash( $_GET['nxq_decline'] ) ), sanitize_text_field( wp_unslash( $_GET['key'] ) ) );
		} elseif ( isset( $_GET['nxq_done'], $_GET['key'] ) ) {
			Nextial_Q_Request::render_done( absint( wp_unslash( $_GET['nxq_done'] ) ), sanitize_text_field( wp_unslash( $_GET['key'] ) ) );
		} else {
			self::render_list();
		}
		// phpcs:enable
		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * The list and the form.
	 */
	public static function render_list() {
		$items = Nextial_Q_List::items();
		if ( ! $items ) {
			echo '<p class="nxq-empty">' . esc_html__( 'Your quote list is empty.', 'nextial-quotes-for-woocommerce' ) . '</p>';
			echo '<p><a class="button" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">' . esc_html__( 'Browse products', 'nextial-quotes-for-woocommerce' ) . '</a></p>';
			return;
		}
		$action = Nextial_Q_Settings::page_url();
		echo '<form method="post" action="' . esc_url( $action ) . '" class="nxq-list-form">';
		wp_nonce_field( 'nxq_update', 'nxq_update_nonce' );
		echo '<table class="shop_table shop_table_responsive nxq-table"><thead><tr><th>' . esc_html__( 'Product', 'nextial-quotes-for-woocommerce' ) . '</th><th>' . esc_html__( 'Quantity', 'nextial-quotes-for-woocommerce' ) . '</th><th><span class="screen-reader-text">' . esc_html__( 'Remove', 'nextial-quotes-for-woocommerce' ) . '</span></th></tr></thead><tbody>';
		foreach ( $items as $key => $item ) {
			$p = Nextial_Q_List::product( $item );
			if ( ! $p ) {
				continue;
			}
			echo '<tr><td data-title="' . esc_attr__( 'Product', 'nextial-quotes-for-woocommerce' ) . '"><a href="' . esc_url( $p->get_permalink() ) . '">' . esc_html( $p->get_name() ) . '</a>';
			if ( ! empty( $item['variation'] ) ) {
				echo '<br><small>' . wp_kses_post( wc_get_formatted_variation( $item['variation'], true ) ) . '</small>';
			}
			echo '</td><td data-title="' . esc_attr__( 'Quantity', 'nextial-quotes-for-woocommerce' ) . '"><input type="number" class="input-text qty text" min="0" step="1" name="nxq_qty[' . esc_attr( $key ) . ']" value="' . esc_attr( $item['quantity'] ) . '" aria-label="' . esc_attr__( 'Quantity', 'nextial-quotes-for-woocommerce' ) . '"></td>';
			echo '<td><button type="submit" class="button nxq-remove" name="nxq_remove" value="' . esc_attr( $key ) . '" aria-label="' . esc_attr__( 'Remove', 'nextial-quotes-for-woocommerce' ) . '">&times;</button></td></tr>';
		}
		echo '</tbody></table>';
		echo '<p><button type="submit" class="button wp-element-button" name="nxq_update" value="1">' . esc_html__( 'Update list', 'nextial-quotes-for-woocommerce' ) . '</button></p>';
		echo '</form>';
		Nextial_Q_Request::render_form();
	}
}
