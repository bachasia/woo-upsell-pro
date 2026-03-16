<?php
/**
 * FBT Bundle — Layout 2: Grid cards with larger images (default).
 *
 * @var array $bundle_data See layout-1.php for full shape.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

extract( $bundle_data ); // phpcs:ignore WordPress.PHP.DontExtract -- template convention
?>
<div class="wup-fbt-bundle wup-bundle-layout-2">
	<h2><?php echo esc_html( $heading ); ?></h2>

	<div class="wup-bundle-items"
		data-nonce="<?php echo esc_attr( $nonce ); ?>"
		data-variants="<?php echo esc_attr( wp_json_encode( $variants ) ); ?>">

		<?php foreach ( $products as $index => $card ) : ?>

			<?php if ( $index > 0 ) : ?>
				<div class="wup-bundle-separator">+</div>
			<?php endif; ?>

			<div class="wup-bundle-item wup-bundle-card"
				data-id="<?php echo esc_attr( $card['id'] ); ?>"
				data-parent="<?php echo esc_attr( $card['parent_id'] ); ?>"
				data-price="<?php echo esc_attr( $card['price'] ); ?>"
				data-type="<?php echo esc_attr( $card['product_type'] ); ?>">

				<div class="wup-item-image">
					<a href="<?php echo esc_url( $card['url'] ); ?>">
						<?php echo wp_kses_post( $card['thumbnail'] ); ?>
					</a>
				</div>

				<div class="wup-item-details">
					<label class="wup-item-label">
						<input type="checkbox" class="wup-item-checkbox"
							name="upsells[]"
							value="<?php echo esc_attr( $card['id'] ); ?>"
							<?php checked( true ); ?>>
						<a href="<?php echo esc_url( $card['url'] ); ?>" class="wup-item-title">
							<?php echo esc_html( $card['default_name'] ); ?>
						</a>
					</label>

					<span class="wup-item-price"><?php echo wp_kses_post( $card['price_html'] ); ?></span>

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

			</div>

		<?php endforeach; ?>
	</div>

	<div class="wup-bundle-footer">
		<span class="wup-bundle-total-label"><?php esc_html_e( 'Total:', 'woo-upsell-pro' ); ?></span>
		<span class="wup-bundle-total"></span>
		<?php if ( $discount_amount ) : ?>
			<span class="wup-bundle-discount">
				<?php
				/* translators: %s: discount percentage */
				printf( esc_html__( '%s%% off with bundle', 'woo-upsell-pro' ), esc_html( $discount_amount ) );
				?>
			</span>
		<?php endif; ?>
		<button class="wup-add-bundle button alt" type="button">
			<?php echo esc_html( $add_label ); ?>
		</button>
	</div>
</div>
