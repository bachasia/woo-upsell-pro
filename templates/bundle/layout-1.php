<?php
/**
 * FBT Bundle — Layout 1: Images row → product list → footer (total + CTA).
 *
 * @var array $bundle_data {
 *   @type array[]    $products         Product card DTOs.
 *   @type array      $variants         Variants map [ product_id => [ var_id => attrs ] ].
 *   @type WC_Product $main_product     Current product.
 *   @type string     $heading          Section heading.
 *   @type string     $add_label        CTA button label.
 *   @type string     $discount_amount  Percentage discount or empty.
 *   @type int        $layout           Layout number.
 *   @type string     $hide_all_options 'yes'|'no'.
 *   @type int        $hide_when        Hide selects when input count <= N.
 *   @type string     $nonce            AJAX nonce.
 * }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

extract( $bundle_data ); // phpcs:ignore WordPress.PHP.DontExtract -- template convention
?>
<div class="upsell-bundle wup-fbt-bundle">

	<h3 class="upsell-bundle__heading"><?php echo esc_html( $heading ); ?></h3>

	<!-- Product images row with "+" separators -->
	<div class="upsell-bundle__images">
		<?php foreach ( $products as $index => $card ) : ?>
			<?php if ( $index > 0 ) : ?>
				<span class="upsell-bundle__plus">+</span>
			<?php endif; ?>
			<div class="upsell-bundle__img-wrap">
				<a href="<?php echo esc_url( $card['url'] ); ?>">
					<?php echo wp_kses_post( $card['thumbnail'] ); ?>
				</a>
				<span class="upsell-bundle__check">✓</span>
			</div>
		<?php endforeach; ?>
	</div>

	<!-- Product list rows (JS hooks: wup-bundle-items, wup-bundle-item, wup-item-checkbox, wup-variant-select) -->
	<div class="upsell-bundle__list wup-bundle-items"
		data-nonce="<?php echo esc_attr( $nonce ); ?>"
		data-variants="<?php echo esc_attr( wp_json_encode( $variants ) ); ?>">

		<?php foreach ( $products as $index => $card ) : ?>

			<div class="upsell-bundle__item wup-bundle-item"
				data-id="<?php echo esc_attr( $card['id'] ); ?>"
				data-parent="<?php echo esc_attr( $card['parent_id'] ); ?>"
				data-price="<?php echo esc_attr( $card['price'] ); ?>"
				data-type="<?php echo esc_attr( $card['product_type'] ); ?>">

				<div class="upsell-bundle__item-left">
					<input type="checkbox"
						class="upsell-bundle__checkbox wup-item-checkbox"
						name="upsells[]"
						value="<?php echo esc_attr( $card['id'] ); ?>"
						<?php checked( true ); ?>>
					<div>
						<span class="upsell-bundle__name">
							<?php if ( 0 === $index ) : ?>
								<strong><?php esc_html_e( 'This item:', 'woo-upsell-pro' ); ?></strong>
							<?php endif; ?>
							<a href="<?php echo esc_url( $card['url'] ); ?>">
								<?php echo esc_html( $card['default_name'] ); ?>
							</a>
						</span>
						<span class="upsell-bundle__price"><?php echo wp_kses_post( $card['price_html'] ); ?></span>
					</div>
				</div>

				<!-- Variant selector: show only if product has >= $hide_when options -->
				<?php if ( 'variable' === $card['product_type'] && 'yes' !== $hide_all_options && ! empty( $variants[ $card['id'] ] ) && $card['variation_count'] >= $hide_when ) : ?>
					<select class="upsell-bundle__variant wup-variant-select"
						data-id="<?php echo esc_attr( $card['id'] ); ?>"
						name="variation_<?php echo esc_attr( $card['id'] ); ?>">
						<option value=""><?php esc_html_e( '-- Select --', 'woo-upsell-pro' ); ?></option>
						<?php foreach ( $variants[ $card['id'] ] as $var_id => $attrs ) : ?>
							<option value="<?php echo esc_attr( $var_id ); ?>">
								<?php echo esc_html( implode( ' / ', array_values( $attrs ) ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>

			</div><!-- .upsell-bundle__item -->

		<?php endforeach; ?>

	</div><!-- .upsell-bundle__list -->

	<!-- Footer: total price + savings + CTA -->
	<div class="upsell-bundle__footer">
		<div class="upsell-bundle__total">
			<?php esc_html_e( 'Total Price:', 'woo-upsell-pro' ); ?>
			<span class="upsell-bundle__total-price wup-bundle-total"></span>
			<?php if ( $discount_amount ) : ?>
				<span class="upsell-bundle__saved">
					(<?php esc_html_e( 'Saved', 'woo-upsell-pro' ); ?>
					<span class="upsell-bundle__saved-amount">
						<?php
						/* translators: %s: discount percentage */
						printf( esc_html__( '%s%%+', 'woo-upsell-pro' ), esc_html( $discount_amount ) );
						?>
					</span>)
				</span>
			<?php endif; ?>
		</div>
		<button class="upsell-bundle__submit wup-add-bundle button alt" type="button">
			<?php echo esc_html( $add_label ); ?>
		</button>
	</div>

</div>
