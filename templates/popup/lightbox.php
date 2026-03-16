<?php
/**
 * Popup lightbox template.
 *
 * Expected variables (via extract($popup_data)):
 *   string   $heading       Localised heading text.
 *   array    $products      Product card DTOs from WUP_Variation_Resolver.
 *   array    $variants      Variants map [ parent_id => [ variation_id => attrs ] ].
 *   string   $layout        Layout class suffix.
 *   string   $hide_items    'yes'|'no' — hide upsell list entirely.
 *   string   $hide_options  'yes'|'no' — hide variant selects.
 *   string   $add_label     Per-item CTA label.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
extract( $popup_data ); // $heading, $products, $variants, $layout, $hide_items, $hide_options, $add_label
?>
<div class="wup-popup-content">

	<button class="wup-popup-close" aria-label="<?php esc_attr_e( 'Close', 'woo-upsell-pro' ); ?>">&times;</button>

	<div class="wup-popup-header">
		<h3><?php echo esc_html( $heading ); ?></h3>
	</div>

	<?php if ( $hide_items !== 'yes' && ! empty( $products ) ) : ?>
	<div class="wup-popup-items wup-popup-layout-<?php echo esc_attr( $layout ); ?>"
	     data-variants="<?php echo esc_attr( rawurlencode( json_encode( $variants, JSON_FORCE_OBJECT ) ) ); ?>">

		<?php foreach ( $products as $prod ) : ?>
		<div class="wup-popup-item"
		     data-id="<?php echo esc_attr( $prod['id'] ); ?>"
		     data-parent="<?php echo esc_attr( $prod['parent_id'] ); ?>"
		     data-price="<?php echo esc_attr( $prod['price'] ); ?>"
		     data-type="<?php echo esc_attr( $prod['product_type'] ); ?>">

			<a href="<?php echo esc_url( $prod['url'] ); ?>">
				<?php echo wp_kses_post( $prod['thumbnail'] ); ?>
			</a>

			<div class="wup-popup-item-title">
				<?php echo esc_html( $prod['default_name'] ); ?>
			</div>

			<div class="wup-popup-item-price">
				<?php echo wp_kses_post( $prod['price_html'] ); ?>
			</div>

			<?php if ( $hide_options !== 'yes' && $prod['product_type'] === 'variable' ) : ?>
			<select class="wup-variant-select" data-id="<?php echo esc_attr( $prod['id'] ); ?>">
				<option value=""><?php esc_html_e( 'Select options', 'woo-upsell-pro' ); ?></option>
			</select>
			<?php endif; ?>

			<button class="wup-popup-add-btn button"
			        data-product-id="<?php echo esc_attr( $prod['parent_id'] ); ?>">
				<?php echo esc_html( $add_label ); ?>
			</button>

		</div>
		<?php endforeach; ?>

	</div>
	<?php endif; ?>

	<div class="wup-popup-footer">
		<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="button wup-popup-viewcart">
			<?php esc_html_e( 'View Cart', 'woo-upsell-pro' ); ?>
		</a>
		<a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="button alt wup-popup-checkout">
			<?php esc_html_e( 'Checkout', 'woo-upsell-pro' ); ?>
		</a>
	</div>

</div>
