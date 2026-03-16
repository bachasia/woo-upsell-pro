<?php
/**
 * Side cart header template — title with item count + close button.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wup-sc-header">
	<h3>
		<?php
		printf(
			/* translators: %d: cart item count */
			esc_html__( 'Your Cart (%d)', 'woo-upsell-pro' ),
			WC()->cart->get_cart_contents_count()
		);
		?>
	</h3>
	<button class="wup-sc-close" aria-label="<?php esc_attr_e( 'Close', 'woo-upsell-pro' ); ?>">&times;</button>
</div>
