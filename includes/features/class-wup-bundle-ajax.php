<?php
/**
 * WUP_Bundle_Ajax — AJAX handlers and coupon logic for the FBT bundle feature.
 *
 * Used as a trait by WUP_Bundle. Separated to keep WUP_Bundle under 200 lines.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WUP_Bundle_Ajax {

	// ------------------------------------------------------------------ //
	// Virtual coupon
	// ------------------------------------------------------------------ //

	/**
	 * Provide virtual coupon data for the WUP_COUPON_BUNDLE coupon code.
	 *
	 * @param array|false $data WC coupon data or false.
	 * @param string      $code Coupon code being looked up.
	 * @return array|false
	 */
	public function virtual_bundle_coupon( $data, string $code ) {
		if ( strtolower( $code ) !== WUP_COUPON_BUNDLE ) {
			return $data;
		}

		$amount = wup_get_option( 'wup_upsell_bundle_discount_amount', 0 );
		if ( ! $amount ) {
			return $data;
		}

		return [
			'id'                         => 9999999,
			'discount_type'              => 'percent',
			'amount'                     => floatval( $amount ),
			'individual_use'             => 'no',
			'product_ids'                => [],
			'excluded_product_ids'       => [],
			'usage_limit'                => '',
			'usage_limit_per_user'       => '',
			'limit_usage_to_x_items'     => '',
			'usage_count'                => '',
			'expiry_date'                => '',
			'free_shipping'              => false,
			'product_categories'         => [],
			'excluded_product_categories'=> [],
			'exclude_sale_items'         => false,
			'minimum_amount'             => '',
			'maximum_amount'             => '',
			'customer_email'             => [],
		];
	}

	/**
	 * Auto-apply bundle coupon when bundle items are present in cart.
	 *
	 * @param WC_Cart $cart
	 */
	public function apply_bundle_discount( WC_Cart $cart ): void {
		if ( ! wup_get_option( 'wup_upsell_bundle_discount_amount', '' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $item ) {
			if ( ! empty( $item['_wup_bundle'] ) ) {
				if ( ! $cart->has_discount( WUP_COUPON_BUNDLE ) ) {
					$cart->apply_coupon( WUP_COUPON_BUNDLE );
				}
				return;
			}
		}
	}

	// ------------------------------------------------------------------ //
	// AJAX handlers
	// ------------------------------------------------------------------ //

	/** AJAX: add selected bundle items to cart and optionally apply discount. */
	public function ajax_add_bundle(): void {
		check_ajax_referer( 'wup-add-bundle', 'nonce' );

		$raw   = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : '[]'; // phpcs:ignore WordPress.Security.NonceVerification
		$items = json_decode( $raw, true );

		if ( ! is_array( $items ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid items', 'woo-upsell-pro' ) ] );
			return;
		}

		foreach ( $items as $item ) {
			$product_id   = absint( $item['product_id'] ?? 0 );
			$variation_id = absint( $item['variation_id'] ?? 0 );
			$quantity     = max( 1, intval( $item['quantity'] ?? 1 ) );
			$variation    = isset( $item['variation'] ) && is_array( $item['variation'] )
				? array_map( 'sanitize_text_field', $item['variation'] )
				: [];

			if ( ! $product_id ) {
				continue;
			}

			WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation, [ '_wup_bundle' => 1 ] );
		}

		if ( wup_get_option( 'wup_upsell_bundle_discount_amount', '' ) && ! WC()->cart->has_discount( WUP_COUPON_BUNDLE ) ) {
			WC()->cart->apply_coupon( WUP_COUPON_BUNDLE );
		}

		wp_send_json_success( [
			'cart_count' => WC()->cart->get_cart_contents_count(),
			'message'    => __( 'Added to cart', 'woo-upsell-pro' ),
		] );
	}

	/** AJAX: return WC add-to-cart form HTML for quick-view. */
	public function ajax_quickview(): void {
		check_ajax_referer( 'wup-quickview', 'nonce' );

		$product_id = absint( $_POST['product_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			wp_send_json_error( [ 'message' => __( 'Product not found', 'woo-upsell-pro' ) ] );
			return;
		}

		global $post;
		$backup_post = $post;
		$post        = get_post( $product_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		setup_postdata( $post );
		ob_start();
		woocommerce_template_single_add_to_cart();
		$html = ob_get_clean();
		$post = $backup_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		wp_reset_postdata();

		wp_send_json_success( [ 'html' => $html ] );
	}
}
