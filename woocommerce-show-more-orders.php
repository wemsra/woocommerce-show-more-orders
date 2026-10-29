<?php
/**
 * Plugin Name:          WooCommerce Show More Orders
 * Description:          Adds a "per page" selector to the WooCommerce Orders list so you can display a larger number of orders at once.
 * Version:              1.0.0
 * Author:               Dan Bruce
 * License:              MIT
 * License URI:          https://opensource.org/licenses/MIT
 * Text Domain:          woocommerce-show-more-orders
 * Requires at least:    6.5
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * WC requires at least: 8.0
 *
 * @package WooCommerce_Show_More_Orders
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds a per-page dropdown to the order list (both legacy post storage and HPOS).
 *
 * The chosen value is stored in the same user option WordPress' "Screen Options"
 * panel uses (`edit_shop_order_per_page`), so the two stay in sync. Saving it
 * directly also bypasses core's 999 cap on Screen Options values.
 */
final class WC_Show_More_Orders {

	const QUERY_ARG    = 'wcsmo_per_page';
	const NONCE_ARG    = 'wcsmo_nonce';
	const NONCE_ACTION = 'wcsmo_set_per_page';
	const USER_OPTION  = 'edit_shop_order_per_page';
	const DEFAULT_SIZE = 20;

	/**
	 * Screen IDs of the order list: legacy (posts) and HPOS.
	 */
	const SCREEN_IDS = array( 'edit-shop_order', 'woocommerce_page_wc-orders' );

	public static function init() {
		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_hpos_compatibility' ) );
		add_action( 'current_screen', array( __CLASS__, 'maybe_save_per_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_script' ) );

		// Legacy (post-based) order list.
		add_action( 'restrict_manage_posts', array( __CLASS__, 'render_legacy_selector' ), 20, 2 );
		// HPOS order list.
		add_action( 'woocommerce_order_list_table_restrict_manage_orders', array( __CLASS__, 'render_hpos_selector' ), 20, 2 );
	}

	public static function declare_hpos_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}

	/**
	 * Page sizes offered in the dropdown.
	 *
	 * @return int[]
	 */
	public static function get_choices() {
		$choices = (array) apply_filters( 'wcsmo_per_page_choices', array( 20, 50, 100, 250, 500, 1000, 2500 ) );
		$choices = array_filter( array_map( 'absint', $choices ) );
		$choices = array_unique( $choices );
		sort( $choices );

		return $choices;
	}

	public static function get_current_per_page() {
		$per_page = (int) get_user_option( self::USER_OPTION );

		return $per_page > 0 ? $per_page : self::DEFAULT_SIZE;
	}

	private static function is_orders_screen( $screen = null ) {
		$screen = $screen ? $screen : get_current_screen();

		return $screen && in_array( $screen->id, self::SCREEN_IDS, true );
	}

	/**
	 * Persists the selected page size, then redirects to a clean URL.
	 *
	 * @param WP_Screen $screen Current screen.
	 */
	public static function maybe_save_per_page( $screen ) {
		if ( ! self::is_orders_screen( $screen ) || ! isset( $_GET[ self::QUERY_ARG ] ) ) {
			return;
		}

		$nonce = isset( $_GET[ self::NONCE_ARG ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::NONCE_ARG ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) || ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change this setting.', 'woocommerce-show-more-orders' ), 403 );
		}

		$per_page = absint( $_GET[ self::QUERY_ARG ] );
		if ( in_array( $per_page, self::get_choices(), true ) ) {
			update_user_meta( get_current_user_id(), self::USER_OPTION, $per_page );
		}

		wp_safe_redirect( remove_query_arg( array( self::QUERY_ARG, self::NONCE_ARG, 'paged' ) ) );
		exit;
	}

	/**
	 * @param string $post_type Post type being listed.
	 * @param string $which     Table nav position.
	 */
	public static function render_legacy_selector( $post_type, $which = 'top' ) {
		if ( 'shop_order' === $post_type && 'top' === $which ) {
			self::render_selector();
		}
	}

	/**
	 * @param string $order_type Order type being listed.
	 * @param string $which      Table nav position.
	 */
	public static function render_hpos_selector( $order_type, $which = 'top' ) {
		if ( 'shop_order' === $order_type && 'top' === $which ) {
			self::render_selector();
		}
	}

	private static function render_selector() {
		$current = self::get_current_per_page();
		$choices = self::get_choices();

		// Keep a custom value set via Screen Options visible in the dropdown.
		if ( ! in_array( $current, $choices, true ) ) {
			$choices[] = $current;
			sort( $choices );
		}

		// No `name` attribute: the Filter button shouldn't submit it, the script handles changes.
		?>
		<label for="wcsmo-per-page" class="screen-reader-text"><?php esc_html_e( 'Orders per page', 'woocommerce-show-more-orders' ); ?></label>
		<select id="wcsmo-per-page">
			<?php foreach ( $choices as $choice ) : ?>
				<option value="<?php echo esc_attr( $choice ); ?>" <?php selected( $current, $choice ); ?>>
					<?php
					/* translators: %s: number of orders */
					echo esc_html( sprintf( __( '%s per page', 'woocommerce-show-more-orders' ), number_format_i18n( $choice ) ) );
					?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public static function enqueue_script() {
		if ( ! self::is_orders_screen() ) {
			return;
		}

		$config = array(
			'arg'      => self::QUERY_ARG,
			'nonceArg' => self::NONCE_ARG,
			'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
		);

		wp_register_script( 'wcsmo', false, array(), '1.0.0', true );
		wp_enqueue_script( 'wcsmo' );
		wp_add_inline_script(
			'wcsmo',
			'(function (cfg) {
				document.addEventListener("change", function (e) {
					if (!e.target || e.target.id !== "wcsmo-per-page") {
						return;
					}
					var url = new URL(window.location.href);
					url.searchParams.set(cfg.arg, e.target.value);
					url.searchParams.set(cfg.nonceArg, cfg.nonce);
					url.searchParams.delete("paged");
					window.location.href = url.toString();
				});
			})(' . wp_json_encode( $config ) . ');'
		);
	}
}

WC_Show_More_Orders::init();
