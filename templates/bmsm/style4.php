<?php
/**
 * BMSM style4 template — professional card layout with progress bar.
 *
 * Expected: $bmsm_data array with keys:
 *   tiers, active_tier, next_tier, current_value, conditional, options
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

if ( empty( $tiers ) ) {
	return;
}

$is_items      = $conditional === 'items';
$heading_en    = ( $opts['heading_enable'] ?? 'yes' ) === 'yes';
$hide_congrats = ( $opts['hide_congrats'] ?? 'no' ) === 'yes';
$hide_remain   = ( $opts['hide_remain'] ?? 'no' ) === 'yes';

// Progress bar calculation — percent toward next tier (or 100% if max tier active).
$highest_min = (float) end( $tiers )['min'];
if ( $active_tier && ! $next_tier ) {
	$progress_pct = 100;
} elseif ( $next_tier && $next_tier['min'] > 0 ) {
	$progress_pct = (int) min( 100, round( ( $current_value / $next_tier['min'] ) * 100 ) );
} else {
	$progress_pct = 0;
}
?>
<div class="wup-bmsm wup-bmsm-style4">
	<?php if ( $heading_en ) : ?>
	<div class="wup-bmsm-header">
		<h3 class="wup-bmsm-heading"><?php echo esc_html( $opts['heading'] ?? '' ); ?></h3>
		<?php if ( ! empty( $opts['subtitle'] ) ) : ?>
		<p class="wup-bmsm-subtitle"><?php echo esc_html( $opts['subtitle'] ); ?></p>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<div class="wup-bmsm-cards">
		<?php
		$last_index = count( $tiers ) - 1;
		foreach ( $tiers as $i => $tier ) :
			$is_active  = $active_tier && $tier['min'] === $active_tier['min'];
			$is_best    = ( $i === $last_index );
			$card_class = $is_active ? ' wup-bmsm-tier-active' : '';
			$card_class .= $is_best ? ' wup-bmsm-card-best' : '';
		?>
		<div class="wup-bmsm-card<?php echo esc_attr( $card_class ); ?>"
		     data-min="<?php echo esc_attr( $tier['min'] ); ?>"
		     data-discount="<?php echo esc_attr( $tier['discount'] ); ?>">
			<?php if ( $is_best && ! $is_active ) : ?>
			<span class="wup-bmsm-best-ribbon"><?php esc_html_e( 'Best', 'wup-upsell-pro' ); ?></span>
			<?php endif; ?>
			<div class="wup-bmsm-badge"><?php echo esc_html( $tier['discount'] . '%' ); ?></div>
			<div class="wup-bmsm-card-off"><?php esc_html_e( 'OFF', 'wup-upsell-pro' ); ?></div>
			<div class="wup-bmsm-card-label">
				<?php if ( $is_items ) : ?>
					<?php printf( esc_html__( 'Buy %d+ items', 'wup-upsell-pro' ), $tier['min'] ); ?>
				<?php else : ?>
					<?php printf( esc_html__( 'Spend %s+', 'wup-upsell-pro' ), wp_kses_post( wc_price( $tier['min'] ) ) ); ?>
				<?php endif; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<div class="wup-bmsm-progress">
		<div class="wup-bmsm-progress-track">
			<div class="wup-bmsm-progress-fill" style="width:<?php echo esc_attr( $progress_pct ); ?>%"></div>
		</div>
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
