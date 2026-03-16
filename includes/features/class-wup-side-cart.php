<?php
/**
 * WUP_Side_Cart — Slide-in cart panel feature.
 *
 * Injects a full-featured side cart panel. All cart mutations go through AJAX;
 * WooCommerce fragment refresh keeps the panel and badge in sync.
 *
 * AJAX handlers are split into WUP_Side_Cart_Ajax trait to stay under 200 lines.
 * Sections rendered (in order): header, items, shipping-bar, fbt, coupon, footer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wup-side-cart-ajax.php';

if ( ! class_exists( 'WUP_Side_Cart' ) ) {

	class WUP_Side_Cart {

		use WUP_Side_Cart_Ajax;

		/** @var WUP_Side_Cart|null */
		private static ?WUP_Side_Cart $instance = null;

		public static function get_instance(): self {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			$this->register_ajax_endpoints();
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
			add_filter( 'woocommerce_add_to_cart_fragments', [ $this, 'cart_fragments' ] );

			if ( wup_get_option( 'wup_upsell_sidecart_enable', 'no' ) === 'yes' ) {
				add_action( 'wp_footer', [ $this, 'render_cart_shell' ] );

				if ( wup_get_option( 'wup_upsell_sidecart_icon_enable', 'no' ) === 'yes' ) {
					add_action( 'wp_footer', [ $this, 'render_floating_icon' ] );
				}
			}
		}

		// ------------------------------------------------------------------ //
		// AJAX registration
		// ------------------------------------------------------------------ //

		/** Map WP action names to handler method names and register both auth/nopriv. */
		private function register_ajax_endpoints(): void {
			$map = [
				'wup_get_side_cart'    => 'ajax_get_side_cart',
				'wup_sc_update_qty'    => 'ajax_update_qty',
				'wup_sc_remove_item'   => 'ajax_remove_item',
				'wup_sc_apply_coupon'  => 'ajax_apply_coupon',
				'wup_sc_remove_coupon' => 'ajax_remove_coupon',
				'wup_sc_add_item'      => 'ajax_add_item',
			];

			foreach ( $map as $action => $method ) {
				add_action( "wp_ajax_{$action}",        [ $this, $method ] );
				add_action( "wp_ajax_nopriv_{$action}", [ $this, $method ] );
			}
		}

		// ------------------------------------------------------------------ //
		// Frontend shell output
		// ------------------------------------------------------------------ //

		/** Inject hidden side cart panel shell into page footer. */
		public function render_cart_shell(): void {
			echo '<div id="wup-side-cart" class="wup-side-cart-panel">';
			echo '<div class="wup-sc-overlay"></div>';
			echo '<div class="wup-sc-content" style="display:none;"></div>';
			echo '</div>';
		}

		/** Inject floating cart icon with badge. */
		public function render_floating_icon(): void {
			$pos   = wup_get_option( 'wup_upsell_sidecart_icon_position', 'bottom_right' );
			$size  = wup_get_option( 'wup_upsell_sidecart_icon_size', 'md' );
			$count = intval( WC()->cart->get_cart_contents_count() );

			$classes = 'wup-cart-floating-icon wup-icon--' . esc_attr( $pos ) . ' wup-icon--' . esc_attr( $size );
			echo '<div class="' . esc_attr( $classes ) . '">';
			echo '<span class="wup-sc-badge">' . esc_html( $count ) . '</span>';
			// Inline SVG cart icon (no external request needed).
			echo '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"'
				. ' fill="none" stroke="currentColor" stroke-width="2"'
				. ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
				. '<circle cx="9" cy="21" r="1"></circle>'
				. '<circle cx="20" cy="21" r="1"></circle>'
				. '<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>'
				. '</svg>';
			echo '</div>';
		}

		// ------------------------------------------------------------------ //
		// Section renderer
		// ------------------------------------------------------------------ //

		/** Render all cart panel sections and return the combined HTML string. */
		public function render_all_sections(): string {
			ob_start();
			include WUP_TEMPLATES_DIR . 'side-cart/header.php';
			include WUP_TEMPLATES_DIR . 'side-cart/items.php';
			include WUP_TEMPLATES_DIR . 'side-cart/shipping-bar.php';
			include WUP_TEMPLATES_DIR . 'side-cart/fbt.php';
			include WUP_TEMPLATES_DIR . 'side-cart/coupon.php';
			include WUP_TEMPLATES_DIR . 'side-cart/footer.php';
			return (string) ob_get_clean();
		}

		// ------------------------------------------------------------------ //
		// WC Fragments
		// ------------------------------------------------------------------ //

		/**
		 * Keep side cart content and badge in sync on WC fragment refresh.
		 *
		 * @param array $fragments
		 * @return array
		 */
		public function cart_fragments( array $fragments ): array {
			$fragments['#wup-side-cart .wup-sc-content'] =
				'<div class="wup-sc-content">' . $this->render_all_sections() . '</div>';

			$fragments['.wup-sc-badge'] =
				'<span class="wup-sc-badge">' . WC()->cart->get_cart_contents_count() . '</span>';

			return $fragments;
		}

		// ------------------------------------------------------------------ //
		// Assets
		// ------------------------------------------------------------------ //

		/** Enqueue side cart JS and pass localised config. Only loads when side cart is enabled. */
		public function enqueue_assets(): void {
			if ( is_admin() ) {
				return;
			}

			if ( wup_get_option( 'wup_upsell_sidecart_enable', 'no' ) !== 'yes' ) {
				return;
			}

			wp_enqueue_script( 'wup-sidecart' );

			wp_localize_script( 'wup-sidecart', 'wupSideCart', [
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'wup-side-cart' ),
				// Default matches settings schema default '.cart-contents'.
				'open_selector' => wup_get_option( 'wup_upsell_sidecart_open_selector', '.cart-contents' ),
				'auto_open'     => true,
			] );
		}
	}
}
