<?php
/**
 * Settings: WooCommerce → Settings → Quotes.
 *
 * @package Nextial_Quotes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Options are stored in one array, nextial_q_settings.
 */
class Nextial_Q_Settings {

	const OPTION = 'nextial_q_settings';

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'scope'             => 'all',      // all | categories.
			'categories'        => array(),    // term IDs when scope = categories.
			'exclude'           => array(),    // product IDs never quotable.
			'hide_price'        => 'no',
			'hide_add_to_cart'  => 'no',
			'show_in_loop'      => 'yes',
			'button_label'      => __( 'Add to quote', 'nextial-quotes-for-woocommerce' ),
			'page_id'           => 0,
			'require_phone'     => 'no',
			'privacy_text'      => '',
			'admin_email'       => '',
			'max_per_hour'      => 5,
			'turnstile_site'    => '',
			'turnstile_secret'  => '',
		);
	}

	/**
	 * Read one setting.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * All settings.
	 *
	 * @return array
	 */
	public static function all() {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	/**
	 * Save settings (sanitised).
	 *
	 * @param array $in Raw values.
	 */
	public static function save( array $in ) {
		$d   = self::defaults();
		$out = self::all();
		$out['scope']            = ( isset( $in['scope'] ) && 'categories' === $in['scope'] ) ? 'categories' : 'all';
		$out['categories']       = isset( $in['categories'] ) ? array_values( array_filter( array_map( 'absint', (array) $in['categories'] ) ) ) : array();
		$out['exclude']          = isset( $in['exclude'] ) ? self::parse_ids( $in['exclude'] ) : array();
		foreach ( array( 'hide_price', 'hide_add_to_cart', 'show_in_loop', 'require_phone' ) as $k ) {
			$out[ $k ] = ! empty( $in[ $k ] ) && 'no' !== $in[ $k ] ? 'yes' : 'no';
		}
		$label                   = isset( $in['button_label'] ) ? sanitize_text_field( wp_unslash( $in['button_label'] ) ) : '';
		$out['button_label']     = '' !== $label ? $label : $d['button_label'];
		$out['page_id']          = isset( $in['page_id'] ) ? absint( $in['page_id'] ) : $out['page_id'];
		$out['privacy_text']     = isset( $in['privacy_text'] ) ? sanitize_text_field( wp_unslash( $in['privacy_text'] ) ) : '';
		$email                   = isset( $in['admin_email'] ) ? sanitize_email( wp_unslash( $in['admin_email'] ) ) : '';
		$out['admin_email']      = is_email( $email ) ? $email : '';
		$out['max_per_hour']     = isset( $in['max_per_hour'] ) ? max( 1, min( 100, absint( $in['max_per_hour'] ) ) ) : $d['max_per_hour'];
		foreach ( array( 'turnstile_site', 'turnstile_secret' ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) wp_unslash( $in[ $k ] ) ) : $out[ $k ];
		}
		update_option( self::OPTION, $out );
	}

	/**
	 * Cloudflare Turnstile is on when both keys are set.
	 *
	 * @return bool
	 */
	public static function turnstile_on() {
		return '' !== (string) self::get( 'turnstile_site' ) && '' !== (string) self::get( 'turnstile_secret' );
	}

	/**
	 * "12, 15 ,abc" → array(12, 15).
	 *
	 * @param mixed $raw Raw.
	 * @return int[]
	 */
	public static function parse_ids( $raw ) {
		if ( is_array( $raw ) ) {
			$raw = implode( ',', $raw );
		}
		$ids = array_filter( array_map( 'absint', preg_split( '/[\s,]+/', (string) $raw ) ) );
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_settings_tabs_array', array( __CLASS__, 'tab' ), 60 );
		add_action( 'woocommerce_settings_tabs_nextial_quotes', array( __CLASS__, 'render' ) );
		add_action( 'woocommerce_update_options_nextial_quotes', array( __CLASS__, 'handle_save' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( NEXTIAL_Q_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Activation: create the "Request a quote" page once.
	 */
	public static function activate() {
		$page_id = (int) self::get( 'page_id' );
		if ( $page_id && 'page' === get_post_type( $page_id ) && 'trash' !== get_post_status( $page_id ) ) {
			return;
		}
		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Request a quote', 'nextial-quotes-for-woocommerce' ),
				'post_name'    => 'request-a-quote',
				'post_content' => '<!-- wp:shortcode -->[nextial_quote]<!-- /wp:shortcode -->',
			)
		);
		if ( $page_id && ! is_wp_error( $page_id ) ) {
			$all            = self::all();
			$all['page_id'] = (int) $page_id;
			update_option( self::OPTION, $all );
		}
	}

	/**
	 * URL of the quote page.
	 *
	 * @return string
	 */
	public static function page_url() {
		$id = (int) self::get( 'page_id' );
		return $id ? (string) get_permalink( $id ) : home_url( '/' );
	}

	/**
	 * Settings tab.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public static function tab( $tabs ) {
		$tabs['nextial_quotes'] = __( 'Quotes', 'nextial-quotes-for-woocommerce' );
		return $tabs;
	}

	/**
	 * Plugins screen link.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=nextial_quotes' ) ) . '">' . esc_html__( 'Settings', 'nextial-quotes-for-woocommerce' ) . '</a>' );
		return $links;
	}

	/**
	 * Save handler (WooCommerce already checked the nonce and capability).
	 */
	public static function handle_save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		// WooCommerce already checked this nonce; checking it again keeps the handler safe on its own.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'woocommerce-settings' ) ) {
			return;
		}
		$in = isset( $_POST['nextial_q'] ) && is_array( $_POST['nextial_q'] ) ? wp_unslash( $_POST['nextial_q'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised field by field in save().
		self::save( $in );
	}

	/**
	 * Settings form.
	 */
	public static function render() {
		$s     = self::all();
		$cats  = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);
		$pages = get_pages();
		$name  = function ( $k ) {
			return 'nextial_q[' . $k . ']';
		};
		?>
		<h2><?php esc_html_e( 'Quotes', 'nextial-quotes-for-woocommerce' ); ?></h2>
		<p><?php esc_html_e( 'Customers add products to a quote list and send a request. Each request becomes an order with the "Quote request" status: open it, adjust prices, and press "Send quote". The customer gets an e-mail with "Accept and pay" and "Decline".', 'nextial-quotes-for-woocommerce' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Products that can be quoted', 'nextial-quotes-for-woocommerce' ); ?></th>
				<td>
					<label><input type="radio" name="<?php echo esc_attr( $name( 'scope' ) ); ?>" value="all" <?php checked( 'all', $s['scope'] ); ?>> <?php esc_html_e( 'All products', 'nextial-quotes-for-woocommerce' ); ?></label><br>
					<label><input type="radio" name="<?php echo esc_attr( $name( 'scope' ) ); ?>" value="categories" <?php checked( 'categories', $s['scope'] ); ?>> <?php esc_html_e( 'Only these categories:', 'nextial-quotes-for-woocommerce' ); ?></label><br>
					<select multiple size="6" name="<?php echo esc_attr( $name( 'categories' ) ); ?>[]" style="min-width:280px">
						<?php foreach ( is_array( $cats ) ? $cats : array() as $c ) : ?>
							<option value="<?php echo esc_attr( $c->term_id ); ?>" <?php selected( in_array( (int) $c->term_id, array_map( 'intval', $s['categories'] ), true ) ); ?>><?php echo esc_html( $c->name ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Sub-categories are included.', 'nextial-quotes-for-woocommerce' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="nxq-exclude"><?php esc_html_e( 'Never quote these product IDs', 'nextial-quotes-for-woocommerce' ); ?></label></th>
				<td><input id="nxq-exclude" type="text" class="regular-text" name="<?php echo esc_attr( $name( 'exclude' ) ); ?>" value="<?php echo esc_attr( implode( ', ', $s['exclude'] ) ); ?>"><p class="description"><?php esc_html_e( 'Comma-separated, e.g. 12, 57', 'nextial-quotes-for-woocommerce' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'On quotable products', 'nextial-quotes-for-woocommerce' ); ?></th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( $name( 'hide_price' ) ); ?>" value="yes" <?php checked( 'yes', $s['hide_price'] ); ?>> <?php esc_html_e( 'Hide the price', 'nextial-quotes-for-woocommerce' ); ?></label><br>
					<label><input type="checkbox" name="<?php echo esc_attr( $name( 'hide_add_to_cart' ) ); ?>" value="yes" <?php checked( 'yes', $s['hide_add_to_cart'] ); ?>> <?php esc_html_e( 'Hide "Add to cart" (quote only)', 'nextial-quotes-for-woocommerce' ); ?></label><br>
					<label><input type="checkbox" name="<?php echo esc_attr( $name( 'show_in_loop' ) ); ?>" value="yes" <?php checked( 'yes', $s['show_in_loop'] ); ?>> <?php esc_html_e( 'Show the button in the shop and category pages too', 'nextial-quotes-for-woocommerce' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="nxq-label"><?php esc_html_e( 'Button text', 'nextial-quotes-for-woocommerce' ); ?></label></th>
				<td><input id="nxq-label" type="text" class="regular-text" name="<?php echo esc_attr( $name( 'button_label' ) ); ?>" value="<?php echo esc_attr( $s['button_label'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="nxq-page"><?php esc_html_e( 'Quote page', 'nextial-quotes-for-woocommerce' ); ?></label></th>
				<td>
					<select id="nxq-page" name="<?php echo esc_attr( $name( 'page_id' ) ); ?>">
						<option value="0">—</option>
						<?php foreach ( $pages as $p ) : ?>
							<option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( (int) $s['page_id'], (int) $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'The page must contain the [nextial_quote] shortcode (created automatically on activation).', 'nextial-quotes-for-woocommerce' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Request form', 'nextial-quotes-for-woocommerce' ); ?></th>
				<td>
					<label><input type="checkbox" name="<?php echo esc_attr( $name( 'require_phone' ) ); ?>" value="yes" <?php checked( 'yes', $s['require_phone'] ); ?>> <?php esc_html_e( 'Phone number required', 'nextial-quotes-for-woocommerce' ); ?></label><br>
					<label for="nxq-privacy"><?php esc_html_e( 'Privacy checkbox text (empty = no checkbox):', 'nextial-quotes-for-woocommerce' ); ?></label><br>
					<input id="nxq-privacy" type="text" class="large-text" name="<?php echo esc_attr( $name( 'privacy_text' ) ); ?>" value="<?php echo esc_attr( $s['privacy_text'] ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="nxq-admin-email"><?php esc_html_e( 'Send new requests to', 'nextial-quotes-for-woocommerce' ); ?></label></th>
				<td><input id="nxq-admin-email" type="email" class="regular-text" name="<?php echo esc_attr( $name( 'admin_email' ) ); ?>" value="<?php echo esc_attr( $s['admin_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nxq_test_mail' ), 'nxq_test_mail' ) ); ?>"><?php esc_html_e( 'Send a test e-mail', 'nextial-quotes-for-woocommerce' ); ?></a>
					<p class="description"><?php esc_html_e( 'Every request is saved as an order before any e-mail is sent, so nothing is lost. If WordPress cannot send the e-mail, you get a warning in the dashboard and a note on the order. Save your changes before sending the test.', 'nextial-quotes-for-woocommerce' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><label for="nxq-max"><?php esc_html_e( 'Max requests per hour from one visitor', 'nextial-quotes-for-woocommerce' ); ?></label></th>
				<td><input id="nxq-max" type="number" min="1" max="100" name="<?php echo esc_attr( $name( 'max_per_hour' ) ); ?>" value="<?php echo esc_attr( $s['max_per_hour'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Cloudflare Turnstile (optional anti-spam)', 'nextial-quotes-for-woocommerce' ); ?></th>
				<td>
					<label for="nxq-ts-site"><?php esc_html_e( 'Site key', 'nextial-quotes-for-woocommerce' ); ?></label><br>
					<input id="nxq-ts-site" type="text" class="regular-text" autocomplete="off" name="<?php echo esc_attr( $name( 'turnstile_site' ) ); ?>" value="<?php echo esc_attr( $s['turnstile_site'] ); ?>"><br>
					<label for="nxq-ts-secret"><?php esc_html_e( 'Secret key', 'nextial-quotes-for-woocommerce' ); ?></label><br>
					<input id="nxq-ts-secret" type="password" class="regular-text" autocomplete="new-password" name="<?php echo esc_attr( $name( 'turnstile_secret' ) ); ?>" value="<?php echo esc_attr( $s['turnstile_secret'] ); ?>">
					<p class="description"><?php esc_html_e( 'Free keys from the Cloudflare dashboard (Turnstile). With both keys set, the quote form shows the Turnstile check and every request is verified with Cloudflare. Leave empty to use only the built-in honeypot and rate limit.', 'nextial-quotes-for-woocommerce' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}
}
