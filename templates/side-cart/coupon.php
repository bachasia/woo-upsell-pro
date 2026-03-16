<?php
/**
 * Side cart coupon template — code input + list of applied coupons.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wup-sc-coupon">
	<div class="wup-sc-coupon-input">
		<input type="text" class="wup-coupon-code"
		       placeholder="<?php esc_attr_e( 'Coupon code', 'woo-upsell-pro' ); ?>">
		<button class="wup-apply-coupon button">
			<?php esc_html_e( 'Apply', 'woo-upsell-pro' ); ?>
		</button>
	</div>

	<?php foreach ( WC()->cart->get_applied_coupons() as $coupon ) : ?>
	<div class="wup-sc-coupon-applied">
		<span><?php echo esc_html( $coupon ); ?></span>
		<button class="wup-remove-coupon"
		        data-coupon="<?php echo esc_attr( $coupon ); ?>"
		        aria-label="<?php esc_attr_e( 'Remove coupon', 'woo-upsell-pro' ); ?>">
			&times;
		</button>
	</div>
	<?php endforeach; ?>
</div>
