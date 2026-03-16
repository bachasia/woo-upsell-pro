<?php
/**
 * FBT Bundle — Layout 4: Deal display with prominent CTA.
 *
 * Shows total price and discounted price when a discount is configured.
 *
 * @var array $bundle_data See layout-1.php for full shape.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

extract( $bundle_data ); // phpcs:ignore WordPress.PHP.DontExtract -- template convention

// Pre-calculate total for server-side display.
$total = 0.0;
foreach ( $products as $card ) {
	$total += (float) $card['price'];
}
$discounted = $discount_amount ? $total * ( 1 - ( (float) $discount_amount / 100 ) ) : 0.0;
?>
<div class="wup-fbt-bundle wup-bundle-layout-4">
	<div class="wup-deal-header">
		<h2><?php echo esc_html( $heading ); ?></h2>
		<?php if ( $discount_amount ) : ?>
			<span class="wup-deal-badge">
				<?php
				/* translators: %s: discount percentage */
				printf( esc_html__( 'Save %s%%', 'woo-upsell-pro' ), esc_html( $discount_amount ) );
				?>
			</span>
		<?php endif; ?>
	</div>

	<div class="wup-bundle-items"
		data-nonce="<?php echo esc_attr( $nonce ); ?>"
		data-variants="<?php echo esc_attr( wp_json_encode( $variants ) ); ?>">

		<?php foreach ( $products as $index => $card ) : ?>

			<?php if ( $index > 0 ) : ?>
				<div class="wup-bundle-separator">+</div>
			<?php endif; ?>

			<div class="wup-bundle-item"
				data-id="<?php echo esc_attr( $card['id'] ); ?>"
				data-parent="<?php echo esc_attr( $card['parent_id'] ); ?>"
				data-price="<?php echo esc_attr( $card['price'] ); ?>"
				data-type="<?php echo esc_attr( $card['product_type'] ); ?>">

				<div class="wup-item-image">
					<a href="<?php echo esc_url( $card['url'] ); ?>">
						<?php echo wp_kses_post( $card['thumbnail'] ); ?>
					</a>
				</div>

				<label class="wup-item-label">
					<input type="checkbox" class="wup-item-checkbox"
						name="upsells[]"
						value="<?php echo esc_attr( $card['id'] ); ?>"
						<?php checked( true ); ?>>
					<span class="wup-item-title"><?php echo esc_html( $card['default_name'] ); ?></span>
				</label>

				<?php if ( 'variable' === $card['product_type'] && 'yes' !== $hide_all_options && ! empty( $variants[ $card['id'] ] ) ) : ?>
					<select class="wup-variant-select"
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

			</div>

		<?php endforeach; ?>
	</div>

	<div class="wup-deal-pricing">
		<?php if ( $discount_amount && $discounted > 0 ) : ?>
			<span class="wup-deal-original-price">
				<?php echo wp_kses_post( wup_format_price( $total ) ); ?>
			</span>
			<span class="wup-deal-arrow">&rarr;</span>
			<span class="wup-deal-discounted-price">
				<?php echo wp_kses_post( wup_format_price( $discounted ) ); ?>
			</span>
		<?php else : ?>
			<span class="wup-bundle-total-label"><?php esc_html_e( 'Total:', 'woo-upsell-pro' ); ?></span>
			<span class="wup-bundle-total"><?php echo wp_kses_post( wup_format_price( $total ) ); ?></span>
		<?php endif; ?>
	</div>

	<div class="wup-bundle-footer">
		<button class="wup-add-bundle button alt wup-deal-cta" type="button">
			<?php echo esc_html( $add_label ); ?>
		</button>
	</div>
</div>
