<?php
/**
 * Side cart items template — line items with image, name, price, qty stepper, remove.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cart_items = WC()->cart->get_cart();
?>
<div class="wup-sc-items">
	<?php if ( empty( $cart_items ) ) : ?>

	<div class="wup-sc-empty">
		<p><?php esc_html_e( 'Your cart is empty.', 'woo-upsell-pro' ); ?></p>
		<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="button">
			<?php esc_html_e( 'Continue Shopping', 'woo-upsell-pro' ); ?>
		</a>
	</div>

	<?php else : ?>

	<ul class="cart-items-list">
		<?php foreach ( $cart_items as $cart_item_key => $cart_item ) :

			// Honour WooCommerce visibility filter.
			if ( ! apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
				continue;
			}

			$product   = $cart_item['data'];
			$product_id = $cart_item['product_id'];
			$qty        = $cart_item['quantity'];
			$line_price = $product->get_price() * $qty;

			// Variation attribute labels.
			$variation_attrs = [];
			if ( ! empty( $cart_item['variation'] ) ) {
				foreach ( $cart_item['variation'] as $attr_key => $attr_value ) {
					if ( empty( $attr_value ) ) {
						continue;
					}
					$attr_name         = wc_attribute_label( str_replace( 'attribute_', '', $attr_key ) );
					$variation_attrs[] = esc_html( $attr_name ) . ': ' . esc_html( $attr_value );
				}
			}
			?>
		<li class="cart-item" data-cart-key="<?php echo esc_attr( $cart_item_key ); ?>">

			<div class="item-image">
				<a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>">
					<?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ); ?>
				</a>
			</div>

			<div class="item-details">
				<div class="item-name">
					<a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>">
						<?php echo esc_html( $product->get_name() ); ?>
					</a>
				</div>

				<?php if ( ! empty( $variation_attrs ) ) : ?>
				<div class="item-meta">
					<?php echo esc_html( implode( ' / ', $variation_attrs ) ); ?>
				</div>
				<?php endif; ?>

				<div class="item-price">
					<?php echo wp_kses_post( wc_price( $line_price ) ); ?>
				</div>

				<div class="item-qty">
					<button class="wup-qty-minus" aria-label="<?php esc_attr_e( 'Decrease quantity', 'woo-upsell-pro' ); ?>">
						&minus;
					</button>
					<span class="wup-qty"><?php echo esc_html( $qty ); ?></span>
					<button class="wup-qty-plus" aria-label="<?php esc_attr_e( 'Increase quantity', 'woo-upsell-pro' ); ?>">
						+
					</button>
				</div>
			</div>

			<button class="wup-remove-item" data-key="<?php echo esc_attr( $cart_item_key ); ?>"
			        aria-label="<?php esc_attr_e( 'Remove item', 'woo-upsell-pro' ); ?>">
				&times;
			</button>

		</li>
		<?php endforeach; ?>
	</ul>

	<?php endif; ?>
</div>
