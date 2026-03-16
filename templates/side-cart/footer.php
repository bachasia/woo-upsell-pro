<?php
/**
 * Side cart footer template — subtotal + checkout button.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wup-sc-footer">
	<div class="wup-sc-subtotal">
		<span><?php esc_html_e( 'Subtotal:', 'woo-upsell-pro' ); ?></span>
		<span class="wup-sc-subtotal-amount">
			<?php echo wp_kses_post( WC()->cart->get_cart_subtotal() ); ?>
		</span>
	</div>
	<a href="<?php echo esc_url( wc_get_checkout_url() ); ?>"
	   class="button alt wup-sc-checkout-btn">
		<?php echo esc_html( wup_get_option( 'wup_upsell_sidecart_checkout_label', 'Checkout' ) ); ?>
	</a>
</div>
