<?php
/**
 * Cart upsell template.
 *
 * Expected variables (set before include):
 *   WC_Product[] $products     Products to display.
 *   array        $variants     Variants map from WUP_Variation_Resolver.
 *   bool         $hide_options Whether to suppress variation selects.
 *   string       $heading      Section heading text.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $products ) ) {
	return;
}

$product_cards = WUP_Variation_Resolver::build_product_cards( $products );

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo WUP_Renderer::cross_sell_display( $product_cards, $variants, [
	'heading'      => $heading,
	'hide_options' => $hide_options,
	'class_wrp'    => 'wup-cart-upsell-block',
	'add_label'    => __( 'Add to Cart', 'woo-upsell-pro' ),
] );
