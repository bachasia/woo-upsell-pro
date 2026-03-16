<?php
/**
 * WUP_Renderer — shared cross-sell card renderer for cart, thankyou, and related features.
 *
 * Produces consistent HTML for cross-sell product grids.
 * All methods are static and output-buffered — callers receive a string.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WUP_Renderer' ) ) {

	class WUP_Renderer {

		// ------------------------------------------------------------------ //
		// Public API
		// ------------------------------------------------------------------ //

		/**
		 * Render a full cross-sell section with heading and product grid.
		 *
		 * @param WC_Product[]|array[] $products  WC_Product objects or product card DTOs.
		 * @param array                $variants  Variants map from WUP_Variation_Resolver.
		 * @param array                $args {
		 *   @type string $heading      Section heading text.
		 *   @type string $class_wrp    Extra CSS class on the wrapper div.
		 *   @type string $cols_classes Extra CSS class on the <ul> for column layout.
		 *   @type bool   $hide_options Whether to suppress variation selects. Default false.
		 *   @type string $add_label    ATC button label. Default 'Add to Cart'.
		 * }
		 * @return string  HTML string.
		 */
		public static function cross_sell_display( array $products, array $variants, array $args = [] ): string {
			if ( empty( $products ) ) {
				return '';
			}

			$args = wp_parse_args( $args, [
				'heading'      => __( 'You may also like', 'woo-upsell-pro' ),
				'class_wrp'    => '',
				'cols_classes' => '',
				'hide_options' => false,
				'add_label'    => __( 'Add to Cart', 'woo-upsell-pro' ),
			] );

			$cards = self::normalise_products( $products );

			ob_start();
			?>
			<div class="wup-cross-sell <?php echo esc_attr( $args['class_wrp'] ); ?>">
				<h3><?php echo esc_html( $args['heading'] ); ?></h3>
				<ul class="wup-cs-items <?php echo esc_attr( $args['cols_classes'] ); ?>">
					<?php foreach ( $cards as $card ) : ?>
						<?php echo self::product_card( $card, $variants, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php
			return ob_get_clean();
		}

		/**
		 * Render a single product card <li>.
		 *
		 * @param array $product     Product card DTO (from WUP_Variation_Resolver or normalised WC_Product).
		 * @param array $variant_map Variants map for this product's parent.
		 * @param array $args        Same args shape as cross_sell_display().
		 * @return string  HTML string.
		 */
		public static function product_card( array $product, array $variant_map, array $args ): string {
			$hide_options     = ! empty( $args['hide_options'] );
			$add_label        = $args['add_label'] ?? __( 'Add to Cart', 'woo-upsell-pro' );
			$parent_id        = (int) $product['id'];
			$product_id       = (int) $product['id'];
			$is_variable      = ( $product['product_type'] ?? 'simple' ) === 'variable';
			$variants_for_pid = $variant_map[ $parent_id ] ?? [];
			$default_attrs    = $product['default_attributes'] ?? [];
			$attrs_empty      = $product['attributes_empty'] ?? true;

			// Determine default variation ID from default attributes when possible.
			$default_variation_id = 0;
			if ( $is_variable && ! empty( $default_attrs ) && ! $attrs_empty ) {
				foreach ( $variants_for_pid as $var_id => $attrs ) {
					if ( empty( array_diff_assoc( $default_attrs, $attrs ) ) ) {
						$default_variation_id = (int) $var_id;
						break;
					}
				}
			}

			$show_select = $is_variable && ! $hide_options;

			// If variable without a resolvable default, button should link to product page.
			$button_links_to_page = $is_variable && ( $hide_options || $attrs_empty );

			ob_start();
			?>
			<li class="wup-cs-item"
				data-parent="<?php echo esc_attr( $parent_id ); ?>"
				data-id="<?php echo esc_attr( $product_id ); ?>"
				data-price="<?php echo esc_attr( $product['price'] ?? '' ); ?>"
				data-variants="<?php echo esc_attr( wp_json_encode( $variants_for_pid, JSON_FORCE_OBJECT ) ); ?>"
				data-default_attributes="<?php echo esc_attr( wp_json_encode( $default_attrs, JSON_FORCE_OBJECT ) ); ?>">

				<a href="<?php echo esc_url( $product['url'] ); ?>">
					<?php echo wp_kses_post( $product['thumbnail'] ); ?>
				</a>

				<div class="wup-cs-info">
					<a href="<?php echo esc_url( $product['url'] ); ?>" class="wup-cs-title">
						<?php echo esc_html( $product['default_name'] ); ?>
					</a>
					<?php echo wp_kses_post( $product['price_html'] ); ?>

					<?php if ( $show_select && ! empty( $variants_for_pid ) ) : ?>
					<select class="wup-cs-variant">
						<option value=""><?php esc_html_e( 'Select options', 'woo-upsell-pro' ); ?></option>
						<?php foreach ( $variants_for_pid as $var_id => $attrs ) : ?>
							<?php $label = implode( ' / ', array_filter( array_values( $attrs ) ) ) ?: '#' . $var_id; ?>
							<option value="<?php echo esc_attr( $var_id ); ?>"
								<?php selected( $default_variation_id, $var_id ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php endif; ?>

					<?php if ( $button_links_to_page ) : ?>
					<a href="<?php echo esc_url( $product['url'] ); ?>" class="button wup-cs-atc">
						<?php esc_html_e( 'Select options', 'woo-upsell-pro' ); ?>
					</a>
					<?php else : ?>
					<button class="wup-cs-atc button"
					        data-product_id="<?php echo esc_attr( $product_id ); ?>"
					        data-variation_id="<?php echo esc_attr( $default_variation_id ); ?>">
						<?php echo esc_html( $add_label ); ?>
					</button>
					<?php endif; ?>
				</div>
			</li>
			<?php
			return ob_get_clean();
		}

		// ------------------------------------------------------------------ //
		// Private helpers
		// ------------------------------------------------------------------ //

		/**
		 * Accept either WC_Product objects or pre-built card DTOs and return DTOs.
		 *
		 * @param WC_Product[]|array[] $products
		 * @return array[]
		 */
		private static function normalise_products( array $products ): array {
			$cards = [];
			foreach ( $products as $item ) {
				if ( $item instanceof WC_Product ) {
					$cards[] = WUP_Variation_Resolver::build_product_cards( [ $item ] )[0] ?? null;
				} elseif ( is_array( $item ) && isset( $item['id'] ) ) {
					$cards[] = $item;
				}
			}
			return array_filter( $cards );
		}
	}
}
