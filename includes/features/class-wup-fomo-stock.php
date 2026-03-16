<?php
/**
 * WUP_Fomo_Stock — FOMO stock urgency notice on single product pages.
 *
 * Shows a configurable message when product stock is within the min–max range.
 * Hook: woocommerce_single_product_summary (priority 25)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WUP_Fomo_Stock' ) ) {

	class WUP_Fomo_Stock {

		/** @var WUP_Fomo_Stock|null */
		private static ?WUP_Fomo_Stock $instance = null;

		public static function get_instance(): self {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			if ( 'yes' === wup_get_option( 'wup_fomo_stock_enable', 'no' ) ) {
				add_action( 'woocommerce_single_product_summary', [ $this, 'render_stock_notice' ], 25 );
			}
		}

		/** Render the stock urgency notice on single product pages. */
		public function render_stock_notice(): void {
			if ( ! is_product() ) {
				return;
			}

			$qty = $this->get_stock_qty();
			if ( null === $qty || ! $this->should_show( $qty ) ) {
				return;
			}

			$msg   = wup_get_option( 'wup_fomo_stock_msg', 'Only [stock] stock left!' );
			$color = wup_get_option( 'wup_fomo_stock_color', '#ff9900' );
			$msg   = str_replace( '[stock]', (string) $qty, $msg );

			printf(
				'<div class="wup-fomo-stock" style="color:%s;">%s</div>',
				esc_attr( $color ),
				esc_html( $msg )
			);
		}

		/**
		 * Get the stock quantity for the current product.
		 * Returns null if the product is not managing stock.
		 */
		private function get_stock_qty(): ?int {
			global $product;

			if ( ! $product instanceof WC_Product ) {
				return null;
			}

			if ( ! $product->managing_stock() ) {
				return null;
			}

			$qty = $product->get_stock_quantity();
			return is_numeric( $qty ) ? (int) $qty : null;
		}

		/** Returns true when stock is within the configured display range. */
		private function should_show( int $qty ): bool {
			$min = (int) wup_get_option( 'wup_fomo_stock_min', 5 );
			$max = (int) wup_get_option( 'wup_fomo_stock_max', 10 );

			return $qty >= $min && $qty <= $max;
		}
	}
}
