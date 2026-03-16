<?php
/**
 * WUP_Announcement — site-wide topbar and per-product announcement bars.
 *
 * Topbar: fixed bar injected via wp_footer.
 * Product bar: injected inside single product summary via configurable hook priority.
 * Both support do_shortcode() on text content.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WUP_Announcement' ) ) {

	class WUP_Announcement {

		/** @var WUP_Announcement|null */
		private static ?WUP_Announcement $instance = null;

		public static function get_instance(): self {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			if (
				'yes' === wup_get_option( 'wup_upsell_announcement_topbar', 'no' )
				&& '' !== wup_get_option( 'wup_upsell_announcement_topbar_text', '' )
			) {
				add_action( 'wp_footer', [ $this, 'render_topbar' ] );
			}

			if (
				'yes' === wup_get_option( 'wup_upsell_announcement_product', 'no' )
				&& '' !== wup_get_option( 'wup_upsell_announcement_product_text', '' )
			) {
				$priority = intval( wup_get_option( 'wup_upsell_announcement_product_priority', 20 ) );
				add_action( 'woocommerce_single_product_summary', [ $this, 'render_product_bar' ], $priority );
			}

			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_inline_css' ] );
		}

		// ------------------------------------------------------------------ //
		// Rendering
		// ------------------------------------------------------------------ //

		/** Render site-wide topbar announcement. */
		public function render_topbar(): void {
			$options = $this->get_bar_options( 'topbar' );
			include WUP_TEMPLATES_DIR . 'announcement/topbar.php';
		}

		/** Render per-product announcement bar. */
		public function render_product_bar(): void {
			$options = $this->get_bar_options( 'product' );
			include WUP_TEMPLATES_DIR . 'announcement/product.php';
		}

		// ------------------------------------------------------------------ //
		// Inline CSS
		// ------------------------------------------------------------------ //

		/**
		 * Output dynamic CSS for announcement bars (bg-color, text-color,
		 * font-size, bg-pattern, bg-image) via inline style.
		 */
		public function enqueue_inline_css(): void {
			$css = '';

			// Topbar.
			if ( 'yes' === wup_get_option( 'wup_upsell_announcement_topbar', 'no' ) ) {
				$css .= $this->build_bar_css( '.wup-announcement-top', 'topbar' );
			}

			// Product bar.
			if ( 'yes' === wup_get_option( 'wup_upsell_announcement_product', 'no' ) ) {
				$css .= $this->build_bar_css( '.wup-announcement-product', 'product' );
			}

			if ( $css ) {
				wp_add_inline_style( 'wup-public-styles', $css );
			}
		}

		// ------------------------------------------------------------------ //
		// Private helpers
		// ------------------------------------------------------------------ //

		/** Collect display options for a bar type. */
		private function get_bar_options( string $type ): array {
			$prefix = "wup_upsell_announcement_{$type}";
			return [
				'text'       => wup_get_option( "{$prefix}_text", '' ),
				'bgcolor'    => wup_get_option( "{$prefix}_bgcolor", '#FFFFFF' ),
				'text_color' => wup_get_option( "{$prefix}_text_color", '#FFFFFF' ),
				'text_size'  => wup_get_option( "{$prefix}_text_size", 'default' ),
				'text_align' => wup_get_option( "{$prefix}_text_align", '' ),
				'bgpattern'  => wup_get_option( "{$prefix}_bgpattern", 'default' ),
				'bgimage'    => wup_get_option( "{$prefix}_bgimage", '' ),
			];
		}

		/** Build inline CSS for a bar based on its options. */
		private function build_bar_css( string $selector, string $type ): string {
			$opts  = $this->get_bar_options( $type );
			$rules = [];

			if ( $opts['bgcolor'] ) {
				$rules[] = 'background-color:' . esc_attr( $opts['bgcolor'] );
			}
			if ( $opts['text_color'] ) {
				$rules[] = 'color:' . esc_attr( $opts['text_color'] );
			}
			if ( $opts['text_size'] && $opts['text_size'] !== 'default' ) {
				$rules[] = 'font-size:' . esc_attr( $opts['text_size'] );
			}
			if ( ! empty( $opts['text_align'] ) ) {
				$rules[] = 'text-align:' . esc_attr( $opts['text_align'] );
			}

			// Background pattern or custom image.
			$bg_img = '';
			if ( ! empty( $opts['bgimage'] ) ) {
				$bg_img = esc_url( $opts['bgimage'] );
			} elseif ( $opts['bgpattern'] && $opts['bgpattern'] !== 'default' ) {
				$bg_img = WUP_URL . 'assets/images/announcement/' . sanitize_file_name( $opts['bgpattern'] ) . '.png';
			}

			if ( $bg_img ) {
				$rules[] = 'background-image:url(' . $bg_img . ')';
				$rules[] = 'background-repeat:repeat';
			}

			if ( empty( $rules ) ) {
				return '';
			}

			return $selector . '{' . implode( ';', $rules ) . '}';
		}
	}
}
