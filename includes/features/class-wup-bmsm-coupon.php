<?php
/**
 * WUP_BMSM_Coupon — trait for BMSM discount calculation and virtual coupon handling.
 *
 * Provides: apply_discount(), get_virtual_coupon_data(), coupon_label(),
 * admin_coupon_label(), on_cart_item_removed(), and private tier helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WUP_BMSM_Coupon {

	// ------------------------------------------------------------------ //
	// Discount application
	// ------------------------------------------------------------------ //

	/**
	 * Apply or remove the BMSM virtual coupon on cart recalc.
	 * Uses $bmsm_calc global to prevent infinite recursion.
	 *
	 * @param WC_Cart $cart
	 */
	public function apply_discount( WC_Cart $cart ): void {
		global $bmsm_calc;

		$bmsm_calc++;
		if ( $bmsm_calc > 1 ) {
			return;
		}

		// Skip during coupon application to avoid loops.
		if ( isset( $_GET['wc-ajax'] ) && $_GET['wc-ajax'] === 'apply_coupon' ) {
			return;
		}

		$conditional = wup_get_option( 'wup_bmsm_conditional', 'items' );
		$tiers       = $this->parse_tiers( $conditional );

		if ( empty( $tiers ) ) {
			$this->remove_bmsm_coupon( $cart );
			return;
		}

		$current_value = $conditional === 'items'
			? (float) $this->get_cart_item_count()
			: $this->get_cart_subtotal();

		$active_tier = $this->best_tier( $tiers, $current_value );
		$coupons     = $cart->get_applied_coupons();

		if ( $active_tier ) {
			// Persist discount data in session for virtual coupon filter.
			WC()->session->set( 'wup_bmsm', [
				'amount' => $active_tier['discount'],
				'type'   => 'percent',
			] );

			if ( ! in_array( $this->bmsm_coupon, $coupons, true ) ) {
				$cart->add_discount( $this->bmsm_coupon );
			}
		} else {
			WC()->session->set( 'wup_bmsm', null );
			$this->remove_bmsm_coupon( $cart );
		}
	}

	/** Provide virtual coupon data for wupbmsm. */
	public function get_virtual_coupon_data( $data, $code, WC_Coupon $coupon ): mixed {
		if ( $code !== $this->bmsm_coupon || ! WC()->session ) {
			return $data;
		}

		$session_data = WC()->session->get( 'wup_bmsm' );
		if ( empty( $session_data ) ) {
			return $data;
		}

		$combie = wup_get_option( 'wup_bmsm_combie', 'no' );

		return [
			'code'                   => $this->bmsm_coupon,
			'amount'                 => $session_data['amount'],
			'discount_type'          => $session_data['type'],
			'description'            => 'WUP Buy More Save More',
			'usage_count'            => 0,
			'individual_use'         => $combie !== 'yes',
			'usage_limit'            => 0,
			'usage_limit_per_user'   => 0,
			'limit_usage_to_x_items' => null,
			'free_shipping'          => false,
			'virtual'                => true,
		];
	}

	/** Human-readable coupon label on cart totals. */
	public function coupon_label( string $label, WC_Coupon $coupon ): string {
		try {
			if ( WC()->session && $coupon->get_code() === $this->bmsm_coupon ) {
				$data = WC()->session->get( 'wup_bmsm' );
				if ( ! empty( $data ) ) {
					$saved = $data['type'] === 'percent'
						? $data['amount'] . '%'
						: wc_price( $data['amount'] );
					return sprintf( __( 'You saved <strong>%s</strong> (Buy More Save More)', 'wup-upsell-pro' ), $saved );
				}
			}
		} catch ( \Exception $e ) {
			return $label;
		}

		return $label;
	}

	/** Admin order coupon label. */
	public function admin_coupon_label( $code, $item, $order ): string {
		if ( $code === $this->bmsm_coupon ) {
			return __( 'Buy More Save More Discount', 'wup-upsell-pro' );
		}
		return $code;
	}

	/** When item removed, recalculate discount. */
	public function on_cart_item_removed( $cart_item_key, WC_Cart $cart ): void {
		if ( WC()->cart->get_cart_contents_count() < 1 ) {
			$this->remove_bmsm_coupon( $cart );
		}
	}

	// ------------------------------------------------------------------ //
	// Private helpers
	// ------------------------------------------------------------------ //

	private function remove_bmsm_coupon( WC_Cart $cart ): void {
		if ( in_array( $this->bmsm_coupon, $cart->get_applied_coupons(), true ) ) {
			$cart->remove_coupon( $this->bmsm_coupon );
		}
	}

	/** Parse tiers from option. Returns [['min'=>int,'discount'=>float]] sorted asc. */
	private function parse_tiers( string $conditional ): array {
		$key  = $conditional === 'items' ? 'wup_buy_more_by_items' : 'wup_buy_more_by_amounts';
		$json = wup_get_option( $key, '' );

		if ( empty( $json ) ) {
			return [];
		}

		$raw = json_decode( urldecode( $json ), true );
		if ( ! is_array( $raw ) ) {
			return [];
		}

		// Support both plan spec [{min,discount}] and any valid array.
		$tiers = [];
		foreach ( $raw as $tier ) {
			if ( isset( $tier['min'], $tier['discount'] ) ) {
				$tiers[] = [ 'min' => (float) $tier['min'], 'discount' => (float) $tier['discount'] ];
			}
		}

		usort( $tiers, fn( $a, $b ) => $a['min'] <=> $b['min'] );

		return $tiers;
	}

	/** Best (highest) matching tier where min <= current_value. */
	private function best_tier( array $tiers, float $current_value ): ?array {
		$match = null;
		foreach ( $tiers as $tier ) {
			if ( $current_value >= $tier['min'] ) {
				$match = $tier;
			}
		}
		return $match;
	}

	/** Next tier above current_value. */
	private function next_tier( array $tiers, float $current_value ): ?array {
		foreach ( $tiers as $tier ) {
			if ( $tier['min'] > $current_value ) {
				return $tier;
			}
		}
		return null;
	}

	/** Count eligible cart items (filtered by category if set). */
	private function get_cart_item_count(): int {
		$categories = maybe_unserialize( wup_get_option( 'wup_bmsm_categories', [] ) );
		$count      = 0;

		foreach ( WC()->cart->get_cart() as $item ) {
			$product = $item['data'] ?? null;
			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			if ( ! empty( $categories ) && ! $this->is_eligible_product( (int) $product->get_id() ) ) {
				continue;
			}

			$count += (int) $item['quantity'];
		}

		return $count;
	}

	/** Subtotal of eligible cart items. */
	private function get_cart_subtotal(): float {
		$categories = maybe_unserialize( wup_get_option( 'wup_bmsm_categories', [] ) );
		$total      = 0.0;

		foreach ( WC()->cart->get_cart() as $item ) {
			$product = $item['data'] ?? null;
			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			if ( ! empty( $categories ) && ! $this->is_eligible_product( (int) $product->get_id() ) ) {
				continue;
			}

			$total += (float) $item['line_subtotal'];
		}

		return $total;
	}

	/** Check if product belongs to any configured BMSM category. */
	private function is_eligible_product( int $product_id ): bool {
		$categories = maybe_unserialize( wup_get_option( 'wup_bmsm_categories', [] ) );
		if ( empty( $categories ) ) {
			return true;
		}

		$product_cats = wc_get_product_term_ids( $product_id, 'product_cat' );
		return ! empty( array_intersect( $categories, $product_cats ) );
	}
}
