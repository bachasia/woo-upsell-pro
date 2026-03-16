<?php
/**
 * WUP_BuyMoreSaveMore — tiered discount widget and pricing engine.
 *
 * Renders a tier table on product pages/cart and applies a virtual percent
 * coupon 'wupbmsm' via WooCommerce coupon hooks. Coupon logic is in
 * WUP_BMSM_Coupon trait to keep each file under 200 lines.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wup-bmsm-coupon.php';

if ( ! class_exists( 'WUP_BuyMoreSaveMore' ) ) {

	class WUP_BuyMoreSaveMore {

		use WUP_BMSM_Coupon;

		/** Virtual coupon slug — internal only, never displayed to customer. */
		private string $bmsm_coupon = 'wupbmsm';

		/** @var WUP_BuyMoreSaveMore|null */
		private static ?WUP_BuyMoreSaveMore $instance = null;

		public static function get_instance(): self {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			global $bmsm_calc;
			$bmsm_calc = 0;

			// Coupon hooks — always registered so the virtual coupon always resolves.
			add_action( 'woocommerce_after_calculate_totals', [ $this, 'apply_discount' ], 1 );
			add_filter( 'woocommerce_get_shop_coupon_data',   [ $this, 'get_virtual_coupon_data' ], 1, 3 );
			add_filter( 'woocommerce_cart_totals_coupon_label', [ $this, 'coupon_label' ], 1, 2 );
			add_filter( 'woocommerce_order_item_get_code',    [ $this, 'admin_coupon_label' ], 1, 3 );
			add_action( 'woocommerce_cart_item_removed',      [ $this, 'on_cart_item_removed' ], 10, 2 );

			if ( 'yes' === wup_get_option( 'wup_bmsm_enable', 'no' ) ) {
				$position = wup_get_option( 'wup_bmsm_position', 'woocommerce_after_add_to_cart_form' );
				$priority = intval( wup_get_option( 'wup_bmsm_priority', 50 ) );

				// 'shortcode_only' means no automatic hook — use [wup_bmsm] shortcode.
				if ( $position && 'shortcode_only' !== $position ) {
					add_action( $position, [ $this, 'render_bmsm' ], $priority );
				}

				add_shortcode( 'wup_bmsm', [ $this, 'shortcode' ] );
				add_filter( 'wup_popup_cart_before_items', [ $this, 'popup_cart_before_items' ] );
				add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
			}
		}

		// ------------------------------------------------------------------ //
		// Rendering
		// ------------------------------------------------------------------ //

		/** Render BMSM widget. Called from WC action hook or shortcode. */
		public function render_bmsm( $atts = [] ): void {
			// WC action hooks pass a string; normalize to array for shortcode compatibility.
			if ( ! is_array( $atts ) ) {
				$atts = [];
			}
			if ( 'yes' !== wup_get_option( 'wup_bmsm_enable', 'no' ) ) {
				return;
			}

			global $product;

			// Category filter on product pages.
			$categories = maybe_unserialize( wup_get_option( 'wup_bmsm_categories', [] ) );
			if ( ! empty( $categories ) && $product instanceof WC_Product ) {
				$product_cats = wc_get_product_term_ids( $product->get_id(), 'product_cat' );
				if ( empty( array_intersect( $categories, $product_cats ) ) ) {
					return;
				}
			}

			$style = $atts['style'] ?? wup_get_option( 'wup_bmsm_style', 'style1' );

			// Map style key → template filename.
			$layout_map = [
				'style1' => 'default',
				'style2' => 'style2',
				'style3' => 'style3',
				'style4' => 'style4',
				'style5' => 'style5',
			];
			$layout = $layout_map[ $style ] ?? 'default';

			$conditional   = wup_get_option( 'wup_bmsm_conditional', 'items' );
			$tiers         = $this->parse_tiers( $conditional );
			$current_value = $conditional === 'items'
				? (float) $this->get_cart_item_count()
				: $this->get_cart_subtotal();

			// Pass product price so price-aware templates can compute discounted amounts.
			$product_price = ( $product instanceof WC_Product ) ? (float) $product->get_price() : 0;

			$bmsm_data = [
				'tiers'         => $tiers,
				'active_tier'   => $this->best_tier( $tiers, $current_value ),
				'next_tier'     => $this->next_tier( $tiers, $current_value ),
				'current_value' => $current_value,
				'conditional'   => $conditional,
				'options'       => $this->collect_options(),
				'product_price' => $product_price,
			];

			include WUP_TEMPLATES_DIR . 'bmsm/' . $layout . '.php';
		}

		/** [wup_bmsm] shortcode. */
		public function shortcode( $atts ): string {
			$atts = shortcode_atts( [ 'style' => 'default' ], $atts, 'wup_bmsm' );
			ob_start();
			$this->render_bmsm( (array) $atts );
			return ob_get_clean();
		}

		/** Inject congrats/remain message into popup cart (via filter). */
		public function popup_cart_before_items( string $html ): string {
			if ( 'yes' === wup_get_option( 'wup_bmsm_hide_congrats', 'no' ) ) {
				return '';
			}

			$conditional   = wup_get_option( 'wup_bmsm_conditional', 'items' );
			$tiers         = $this->parse_tiers( $conditional );
			$current_value = $conditional === 'items'
				? (float) $this->get_cart_item_count()
				: $this->get_cart_subtotal();
			$active_tier   = $this->best_tier( $tiers, $current_value );

			if ( ! $active_tier ) {
				return $html;
			}

			return $html . $this->build_congrats_html( $active_tier, $conditional, $current_value );
		}

		// ------------------------------------------------------------------ //
		// Assets
		// ------------------------------------------------------------------ //

		public function enqueue_assets(): void {
			if ( is_admin() || ( ! is_product() && ! is_cart() ) ) {
				return;
			}

			$js_path = WUP_PUBLIC_DIR . 'js/build/tier-table.js';
			$js_ver  = file_exists( $js_path ) ? filemtime( $js_path ) : WUP_VERSION;

			wp_enqueue_script(
				'wup-tier-table',
				WUP_URL . 'public/js/build/tier-table.js',
				[ 'jquery' ],
				$js_ver,
				true
			);

			$conditional   = wup_get_option( 'wup_bmsm_conditional', 'items' );
			$tiers         = $this->parse_tiers( $conditional );
			$current_value = $conditional === 'items'
				? (float) $this->get_cart_item_count()
				: $this->get_cart_subtotal();

			wp_localize_script( 'wup-tier-table', 'wupBmsm', [
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'tiers'        => $tiers,
				'currentValue' => $current_value,
				'conditional'  => $conditional,
			] );
		}

		// ------------------------------------------------------------------ //
		// Private helpers
		// ------------------------------------------------------------------ //

		/** Build congrats HTML for a matched tier. */
		private function build_congrats_html( array $tier, string $conditional, float $current_value ): string {
			$key_tpl  = $conditional === 'items' ? 'wup_bmsm_congrats_items' : 'wup_bmsm_congrats_subtotal';
			$defaults = $conditional === 'items'
				? 'Hooray! You got BIG discount <strong>[discount_amount]% OFF</strong> for [items_count] items in your cart!'
				: 'Hooray! You got BIG discount <strong>[discount_amount]% OFF</strong> on each product in your cart!';
			$tpl = wup_get_option( $key_tpl, $defaults );

			return '<div class="wup-bmsm-notice">' . str_replace(
				[ '[discount_amount]', '[discount]', '[items_count]' ],
				[ $tier['discount'], (int) $current_value ],
				$tpl
			) . '</div>';
		}

		/** Gather all BMSM display options into one array for templates. */
		private function collect_options(): array {
			return [
				'heading_enable'    => wup_get_option( 'wup_bmsm_heading_enable', 'yes' ),
				'heading'           => wup_get_option( 'wup_bmsm_heading', __( 'Buy More Save More!', 'wup-upsell-pro' ) ),
				'subtitle'          => wup_get_option( 'wup_bmsm_subtitle', '' ),
				'heading_icon'      => wup_get_option( 'wup_bmsm_heading_icon', 'thankyou' ),
				'hide_congrats'     => wup_get_option( 'wup_bmsm_hide_congrats', 'no' ),
				'hide_remain'       => wup_get_option( 'wup_bmsm_hide_remain', 'no' ),
				'add_cart_button'   => wup_get_option( 'wup_bmsm_add_cart_button', 'no' ),
				'add_action_label'  => wup_get_option( 'wup_bmsm_add_action_label', 'Buy {quantity}' ),
				'congrats_items'    => wup_get_option( 'wup_bmsm_congrats_items', '' ),
				'congrats_subtotal' => wup_get_option( 'wup_bmsm_congrats_subtotal', '' ),
				'remain_items'      => wup_get_option( 'wup_bmsm_remain_items', '' ),
				'remain_subtotal'   => wup_get_option( 'wup_bmsm_remain_subtotal', '' ),
			];
		}
	}
}
