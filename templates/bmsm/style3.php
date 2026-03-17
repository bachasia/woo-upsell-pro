<?php
/**
 * BMSM style3 template — Flash Card layout.
 *
 * Each tier = rounded card with "Flash Sale" ribbon.
 * Left column: Discount X% + Total (discounted vs original).
 * Right column: Buy N to get + price per item.
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
<div class="wup-bmsm wup-bmsm-style3">
	<?php if ( $heading_en ) : ?>
	<div class="wup-bmsm-header">
		<h3 class="wup-bmsm-heading"><?php echo esc_html( $opts['heading'] ?? '' ); ?></h3>
		<?php if ( ! empty( $opts['subtitle'] ) ) : ?>
		<p class="wup-bmsm-subtitle"><?php echo esc_html( $opts['subtitle'] ); ?></p>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<div class="wup-bmsm-s3-list">
		<span class="wup-bmsm-s3-flash"><?php esc_html_e( 'Flash Sale', 'wup-upsell-pro' ); ?></span>
		<?php foreach ( $tiers as $tier ) :
			$is_active   = $active_tier && $tier['min'] === $active_tier['min'];
			$card_class  = $is_active ? ' wup-bmsm-tier-active' : '';
			$disc_pct    = (float) $tier['discount'];
			$disc_price  = $show_price ? round( $product_price * ( 1 - $disc_pct / 100 ), 2 ) : 0;
			$orig_total  = $show_price ? $product_price * (int) $tier['min'] : 0;
			$disc_total  = $show_price ? $disc_price * (int) $tier['min'] : 0;
		?>
		<div class="wup-bmsm-s3-card<?php echo esc_attr( $card_class ); ?>"
		     data-min="<?php echo esc_attr( $tier['min'] ); ?>"
		     data-discount="<?php echo esc_attr( $tier['discount'] ); ?>">
			<div class="wup-bmsm-s3-left">
				<div class="wup-bmsm-s3-disc-label">
					<?php esc_html_e( 'Discount', 'wup-upsell-pro' ); ?>
					<strong class="wup-bmsm-s3-pct"><?php echo esc_html( $tier['discount'] . '%' ); ?></strong>
				</div>
				<?php if ( $show_price ) : ?>
				<div class="wup-bmsm-s3-totals">
					<?php esc_html_e( 'Total', 'wup-upsell-pro' ); ?>
					<strong><?php echo wp_kses_post( wc_price( $disc_total ) ); ?></strong>
					<del><?php echo wp_kses_post( wc_price( $orig_total ) ); ?></del>
				</div>
				<?php endif; ?>
			</div>
			<div class="wup-bmsm-s3-right">
				<?php if ( $is_items ) : ?>
				<div class="wup-bmsm-s3-buy-label">
					<?php printf(
						/* translators: %d = quantity */
						esc_html__( 'Buy %d to get', 'wup-upsell-pro' ),
						(int) $tier['min']
					); ?>
				</div>
				<?php if ( $show_price ) : ?>
				<div class="wup-bmsm-s3-price-each">
					<del><?php echo wp_kses_post( wc_price( $product_price ) ); ?></del>
					<strong><?php echo wp_kses_post( wc_price( $disc_price ) ); ?></strong>
					<span><?php esc_html_e( '/each', 'wup-upsell-pro' ); ?></span>
				</div>
				<?php else : ?>
				<div class="wup-bmsm-s3-save"><?php echo esc_html( $tier['discount'] . '% OFF' ); ?></div>
				<?php endif; ?>
				<?php else : ?>
				<div class="wup-bmsm-s3-buy-label">
					<?php printf(
						/* translators: %s = price */
						esc_html__( 'Spend %s+', 'wup-upsell-pro' ),
						wp_kses_post( wc_price( $tier['min'] ) )
					); ?>
				</div>
				<div class="wup-bmsm-s3-save"><?php echo esc_html( $tier['discount'] . '% OFF' ); ?></div>
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
			[ $active_tier['discount'], $active_tier['discount'], (int) $current_value ],
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
			[ $remain, $next_tier['discount'], $next_tier['discount'] ],
			! empty( $opts[ $key_tpl ] ) ? $opts[ $key_tpl ] : $defaults
		);
	?>
	<div class="wup-bmsm-notice wup-bmsm-remain"><?php echo wp_kses_post( $msg ); ?></div>
	<?php endif; ?>
</div>
