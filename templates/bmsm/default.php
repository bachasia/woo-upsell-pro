<?php
/**
 * BMSM default template — standard tier table layout.
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

$is_items    = $conditional === 'items';
$heading_en  = ( $opts['heading_enable'] ?? 'yes' ) === 'yes';
$hide_congrats = ( $opts['hide_congrats'] ?? 'no' ) === 'yes';
$hide_remain   = ( $opts['hide_remain'] ?? 'no' ) === 'yes';
$add_btn       = ( $opts['add_cart_button'] ?? 'no' ) === 'yes';
$btn_label_tpl = $opts['add_action_label'] ?? 'Buy {quantity}';
?>
<div class="wup-bmsm wup-bmsm-default">
	<?php if ( $heading_en ) : ?>
	<div class="wup-bmsm-header">
		<h3 class="wup-bmsm-heading"><?php echo esc_html( $opts['heading'] ?? '' ); ?></h3>
		<?php if ( ! empty( $opts['subtitle'] ) ) : ?>
		<p class="wup-bmsm-subtitle"><?php echo esc_html( $opts['subtitle'] ); ?></p>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<table class="wup-bmsm-table">
		<thead>
			<tr>
				<th><?php echo $is_items ? esc_html__( 'Items', 'wup-upsell-pro' ) : esc_html__( 'Subtotal', 'wup-upsell-pro' ); ?></th>
				<th><?php esc_html_e( 'Discount', 'wup-upsell-pro' ); ?></th>
				<?php if ( $add_btn ) : ?>
				<th></th>
				<?php endif; ?>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $tiers as $tier ) :
			$is_active = $active_tier && $tier['min'] === $active_tier['min'];
			$row_class = $is_active ? ' wup-bmsm-tier-active' : '';
		?>
			<tr class="wup-bmsm-tier<?php echo esc_attr( $row_class ); ?>"
			    data-min="<?php echo esc_attr( $tier['min'] ); ?>"
			    data-discount="<?php echo esc_attr( $tier['discount'] ); ?>">
				<td>
					<?php if ( $is_items ) : ?>
						<?php printf( esc_html__( 'Buy %d+', 'wup-upsell-pro' ), $tier['min'] ); ?>
					<?php else : ?>
						<?php echo wp_kses_post( wc_price( $tier['min'] ) ); ?>+
					<?php endif; ?>
				</td>
				<td><strong><?php echo esc_html( $tier['discount'] . '%' ); ?></strong> <?php esc_html_e( 'OFF', 'wup-upsell-pro' ); ?></td>
				<?php if ( $add_btn && $is_items ) : ?>
				<td>
					<button class="wup-bmsm-add-btn button" data-quantity="<?php echo esc_attr( $tier['min'] ); ?>">
						<?php echo esc_html( str_replace( '{quantity}', $tier['min'], $btn_label_tpl ) ); ?>
					</button>
				</td>
				<?php endif; ?>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $active_tier && ! $hide_congrats ) :
		$key_tpl  = $is_items ? 'congrats_items' : 'congrats_subtotal';
		$defaults = $is_items
			? 'Hooray! You got BIG discount <strong>[discount_amount]% OFF</strong> for [items_count] items in your cart!'
			: 'Hooray! You got BIG discount <strong>[discount_amount]% OFF</strong> on each product!';
		$msg = str_replace(
			[ '[discount_amount]', '[items_count]' ],
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
			[ '[remain]', '[discount_amount]' ],
			[ $remain, $next_tier['discount'] ],
			! empty( $opts[ $key_tpl ] ) ? $opts[ $key_tpl ] : $defaults
		);
	?>
	<div class="wup-bmsm-notice wup-bmsm-remain"><?php echo wp_kses_post( $msg ); ?></div>
	<?php endif; ?>
</div>
