<?php
/**
 * Side cart "Frequently Bought Together" strip template.
 *
 * Shows upsell products based on the first item in the current cart.
 * Hidden when wup_sidecart_fbt_enable != 'yes' or cart is empty.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( wup_get_option( 'wup_sidecart_fbt_enable', 'no' ) !== 'yes' ) {
	return;
}

$cart_items = WC()->cart->get_cart();
if ( empty( $cart_items ) ) {
	return;
}

$first      = reset( $cart_items );
$product_id = $first['product_id'];

$products = WUP_Product_Source::resolve( $product_id, [
	'source'       => wup_get_option( 'wup_upsell_bundle_source', 'related' ),
	'limit'        => 4,
	'cache_suffix' => 'sidecart_fbt',
] );

$cards = WUP_Variation_Resolver::build_product_cards( $products );

if ( empty( $cards ) ) {
	return;
}
?>
<div class="wup-sc-fbt">
	<h4><?php esc_html_e( 'You might also like', 'woo-upsell-pro' ); ?></h4>
	<div class="wup-sc-fbt-items">
		<?php foreach ( $cards as $card ) : ?>
		<div class="wup-sc-fbt-item">
			<a href="<?php echo esc_url( $card['url'] ); ?>">
				<?php echo wp_kses_post( $card['thumbnail'] ); ?>
			</a>
			<span class="wup-sc-fbt-name"><?php echo esc_html( $card['default_name'] ); ?></span>
			<span class="wup-sc-fbt-price"><?php echo wp_kses_post( $card['price_html'] ); ?></span>
			<button class="wup-sc-fbt-add button"
			        data-product-id="<?php echo esc_attr( $card['parent_id'] ); ?>">
				+
			</button>
		</div>
		<?php endforeach; ?>
	</div>
</div>
