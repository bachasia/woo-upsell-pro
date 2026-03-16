<?php
/**
 * WUP_Side_Cart_Ajax — AJAX handler methods for the side cart.
 *
 * Included by WUP_Side_Cart via require_once.
 * All methods verify nonce 'wup-side-cart' before any cart mutation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WUP_Side_Cart_Ajax {

	// ------------------------------------------------------------------ //
	// AJAX handlers
	// ------------------------------------------------------------------ //

	/** Return full side cart HTML. */
	public function ajax_get_side_cart(): void {
		check_ajax_referer( 'wup-side-cart', 'nonce' );
		wp_send_json_success( $this->build_response() );
	}

	/** Update a cart item quantity. */
	public function ajax_update_qty(): void {
		check_ajax_referer( 'wup-side-cart', 'nonce' );
		$key = sanitize_text_field( $_POST['cart_item_key'] ?? '' );
		$qty = absint( $_POST['quantity'] ?? 1 );
		WC()->cart->set_quantity( $key, $qty );
		WC()->cart->calculate_totals();
		wp_send_json_success( $this->build_response() );
	}

	/** Remove a cart item. */
	public function ajax_remove_item(): void {
		check_ajax_referer( 'wup-side-cart', 'nonce' );
		$key = sanitize_text_field( $_POST['cart_item_key'] ?? '' );
		WC()->cart->remove_cart_item( $key );
		WC()->cart->calculate_totals();
		wp_send_json_success( $this->build_response() );
	}

	/** Apply a coupon code. */
	public function ajax_apply_coupon(): void {
		check_ajax_referer( 'wup-side-cart', 'nonce' );
		$code    = sanitize_text_field( $_POST['coupon_code'] ?? '' );
		$applied = WC()->cart->apply_coupon( $code );
		$data    = $this->build_response();
		$data['applied'] = $applied;
		wp_send_json_success( $data );
	}

	/** Remove an applied coupon. */
	public function ajax_remove_coupon(): void {
		check_ajax_referer( 'wup-side-cart', 'nonce' );
		$code = sanitize_text_field( $_POST['coupon_code'] ?? '' );
		WC()->cart->remove_coupon( $code );
		WC()->cart->calculate_totals();
		wp_send_json_success( $this->build_response() );
	}

	/** Add a product (e.g. FBT strip item) to the cart. */
	public function ajax_add_item(): void {
		check_ajax_referer( 'wup-side-cart', 'nonce' );
		$product_id   = absint( $_POST['product_id'] ?? 0 );
		$variation_id = absint( $_POST['variation_id'] ?? 0 );
		WC()->cart->add_to_cart( $product_id, 1, $variation_id );
		WC()->cart->calculate_totals();
		wp_send_json_success( $this->build_response() );
	}

	// ------------------------------------------------------------------ //
	// Private helper (shared with main class)
	// ------------------------------------------------------------------ //

	/** Build the standard AJAX response payload. */
	private function build_response(): array {
		return [
			'html'       => $this->render_all_sections(),
			'cart_count' => WC()->cart->get_cart_contents_count(),
		];
	}
}
