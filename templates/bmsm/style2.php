<?php
/**
 * BMSM style2 template — Badge Row layout.
 *
 * Each tier = teal square badge (X% OFF) + gray pill row with
 * "Buy N and pay just ~~original~~ discounted each".
 * Requires $bmsm_data['product_price'] for price display.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tiers         = $bmsm_data['tiers']         ?? [];
$active_tier   = $bmsm_data['active_tier']   ?? null;
$next_tier     = $bmsm_data['next_tier']      ?? null;
$current_value = $bmsm_data['current_value'] ?? 0;
$conditional   = $bmsm_data['conditional']   ?? 'items';
$opts          = $bmsm_data['options']        ?? [];
$product_price = $bmsm_data['product_price'] ?? 0;

if ( empty( $tiers ) ) {
	return;
}

$is_items      = $conditional === 'items';
$heading_en    = ( $opts['heading_enable'] ?? 'yes' ) === 'yes';
$hide_congrats = ( $opts['hide_congrats'] ?? 'no' ) === 'yes';
$hide_remain   = ( $opts['hide_remain'] ?? 'no' ) === 'yes';
$show_price    = $is_items && $product_price > 0;
?>
<div class="wup-bmsm wup-bmsm-style2">
	<?php if ( $heading_en ) : ?>
	<div class="wup-bmsm-header">
		<h3 class="wup-bmsm-heading"><?php echo esc_html( $opts['heading'] ?? '' ); ?></h3>
		<?php if ( ! empty( $opts['subtitle'] ) ) : ?>
		<p class="wup-bmsm-subtitle"><?php echo esc_html( $opts['subtitle'] ); ?></p>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<div class="wup-bmsm-s2-rows">
		<?php foreach ( $tiers as $tier ) :
			$is_active    = $active_tier && $tier['min'] === $active_tier['min'];
			$row_class    = $is_active ? ' wup-bmsm-tier-active' : '';
			$disc_pct     = (float) $tier['discount'];
			$orig_price   = $product_price;
			$disc_price   = $show_price ? round( $orig_price * ( 1 - $disc_pct / 100 ), 2 ) : 0;
		?>
		<div class="wup-bmsm-s2-row<?php echo esc_attr( $row_class ); ?>"
		     data-min="<?php echo esc_attr( $tier['min'] ); ?>"
		     data-discount="<?php echo esc_attr( $tier['discount'] ); ?>">
			<div class="wup-bmsm-s2-badge">
				<span class="wup-bmsm-s2-pct"><?php echo esc_html( $tier['discount'] . '%' ); ?></span>
				<span class="wup-bmsm-s2-off"><?php esc_html_e( 'OFF', 'wup-upsell-pro' ); ?></span>
			</div>
			<div class="wup-bmsm-s2-pill">
				<?php if ( $is_items ) : ?>
					<span class="wup-bmsm-s2-buy">
						<?php printf(
							/* translators: %d = quantity */
							esc_html__( 'Buy %d and pay just', 'wup-upsell-pro' ),
							(int) $tier['min']
						); ?>
					</span>
					<?php if ( $show_price ) : ?>
					<span class="wup-bmsm-s2-prices">
						<del class="wup-bmsm-s2-orig"><?php echo wp_kses_post( wc_price( $orig_price ) ); ?></del>
						<strong class="wup-bmsm-s2-new"><?php echo wp_kses_post( wc_price( $disc_price ) ); ?></strong>
						<span class="wup-bmsm-s2-each"><?php esc_html_e( 'each', 'wup-upsell-pro' ); ?></span>
					</span>
					<?php else : ?>
					<strong class="wup-bmsm-s2-save"><?php echo esc_html( $tier['discount'] . '% OFF' ); ?></strong>
					<?php endif; ?>
				<?php else : ?>
					<span class="wup-bmsm-s2-buy">
						<?php printf(
							/* translators: %s = formatted price */
							esc_html__( 'Spend %s+ and save', 'wup-upsell-pro' ),
							wp_kses_post( wc_price( $tier['min'] ) )
						); ?>
					</span>
					<strong class="wup-bmsm-s2-save"><?php echo esc_html( $tier['discount'] . '% OFF' ); ?></strong>
				<?php endif; ?>
				<?php if ( $is_active ) : ?>
				<span class="wup-bmsm-s2-active-dot" title="<?php esc_attr_e( 'Active', 'wup-upsell-pro' ); ?>"></span>
				<?php endif; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<?php if ( $active_tier && ! $hide_congrats ) :
		$key_tpl  = $is_items ? 'congrats_items' : 'congrats_subtotal';
		$defaults = $is_items
			? 'Hooray! You got BIG discount <strong>[discount_amount]% OFF</strong> for [items_count] items in your cart!'
			: 'Hooray! You got BIG discount <strong>[discount_amount]% OFF</strong> on each product!';
		$msg = str_replace(
			[ '[discount_amount]', '[discount]', '[items_count]' ],
			[ $active_tier['discount'], (int) $current_value ],
			! empty( $opts[ $key_tpl ] ) ? $opts[ $key_tpl ] : $defaults
		);
	?>
	<div class="wup-bmsm-notice wup-bmsm-congrats"><?php echo wp_kses_post( $msg ); ?></div>
	<?php endif; ?>

	<?php if ( $next_tier && ! $hide_remain ) :
		$key_tpl  = $is_items ? 'remain_items' : 'remain_subtotal';
		$defaults = $is_items
			? 'Just buy more [remain] & GET discount <strong>[discount_amount]% OFF</strong> on each product!'
			: 'Spend [remain] more and GET discount <strong>[discount_amount]% OFF</strong> on your order today!';
		$remain   = $is_items
			? ( $next_tier['min'] - $current_value ) . ' ' . _n( 'item', 'items', (int) ( $next_tier['min'] - $current_value ), 'wup-upsell-pro' )
			: wc_price( $next_tier['min'] - $current_value );
		$msg = str_replace(
			[ '[remain]', '[discount_amount]', '[discount]' ],
			[ $remain, $next_tier['discount'] ],
			! empty( $opts[ $key_tpl ] ) ? $opts[ $key_tpl ] : $defaults
		);
	?>
	<div class="wup-bmsm-notice wup-bmsm-remain"><?php echo wp_kses_post( $msg ); ?></div>
	<?php endif; ?>
</div>
