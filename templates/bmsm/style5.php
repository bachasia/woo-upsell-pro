<?php
/**
 * BMSM style5 template — minimal radio list layout.
 *
 * Reference look: clean rows with left radio, quantity + discount chip,
 * right price stack and optional bottom CTA button.
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
$add_btn       = ( $opts['add_cart_button'] ?? 'no' ) === 'yes';

// Always keep one selected row in this style (fallback to first tier).
$selected_tier = $active_tier ?: ( $tiers[0] ?? null );
$selected_qty  = $selected_tier ? (int) $selected_tier['min'] : 1;

$btn_tpl   = $opts['add_action_label'] ?? 'Add to cart';
$btn_label = ( strpos( $btn_tpl, '{quantity}' ) !== false )
	? str_replace( '{quantity}', (string) $selected_qty, $btn_tpl )
	: $btn_tpl;
?>
<div class="wup-bmsm wup-bmsm-style5">
	<?php if ( $heading_en ) : ?>
	<div class="wup-bmsm-header">
		<h3 class="wup-bmsm-heading"><?php echo esc_html( $opts['heading'] ?? '' ); ?></h3>
		<?php if ( ! empty( $opts['subtitle'] ) ) : ?>
		<p class="wup-bmsm-subtitle"><?php echo esc_html( $opts['subtitle'] ); ?></p>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<div class="wup-bmsm-s5-list">
		<?php foreach ( $tiers as $tier ) :
			$is_active  = $selected_tier && $tier['min'] === $selected_tier['min'];
			$row_class  = $is_active ? ' wup-bmsm-tier-active' : '';
			$disc_pct   = (float) $tier['discount'];
			$disc_price = $show_price ? round( $product_price * ( 1 - $disc_pct / 100 ), 2 ) : 0;
		?>
		<div class="wup-bmsm-s5-row<?php echo esc_attr( $row_class ); ?>"
		     data-min="<?php echo esc_attr( $tier['min'] ); ?>"
		     data-discount="<?php echo esc_attr( $tier['discount'] ); ?>">
			<span class="wup-bmsm-s5-radio" aria-hidden="true"></span>

			<span class="wup-bmsm-s5-copy">
				<?php if ( $is_items ) : ?>
				<span class="wup-bmsm-s5-line1">
					<strong class="wup-bmsm-s5-main"><?php printf( esc_html__( '%d items', 'wup-upsell-pro' ), (int) $tier['min'] ); ?></strong>
					<span class="wup-bmsm-s5-chip"><?php echo esc_html( $tier['discount'] . '% OFF' ); ?></span>
				</span>
				<span class="wup-bmsm-s5-sub"><?php esc_html_e( 'on each product', 'wup-upsell-pro' ); ?></span>
				<?php else : ?>
				<span class="wup-bmsm-s5-line1">
					<strong class="wup-bmsm-s5-main"><?php printf( esc_html__( 'Spend %s+', 'wup-upsell-pro' ), wp_kses_post( wc_price( $tier['min'] ) ) ); ?></strong>
					<span class="wup-bmsm-s5-chip"><?php echo esc_html( $tier['discount'] . '% OFF' ); ?></span>
				</span>
				<span class="wup-bmsm-s5-sub"><?php esc_html_e( 'order discount', 'wup-upsell-pro' ); ?></span>
				<?php endif; ?>
			</span>

			<?php if ( $show_price ) : ?>
			<span class="wup-bmsm-s5-prices">
				<strong><?php echo wp_kses_post( wc_price( $disc_price ) ); ?></strong>
				<del><?php echo wp_kses_post( wc_price( $product_price ) ); ?></del>
			</span>
			<?php endif; ?>
		</div>
		<?php endforeach; ?>
	</div>

	<?php if ( $is_items && $add_btn ) : ?>
	<div class="wup-bmsm-s5-cta-wrap">
		<button class="wup-bmsm-add-btn wup-bmsm-s5-cta"
			data-quantity="<?php echo esc_attr( $selected_qty ); ?>"
			data-label-template="<?php echo esc_attr( $btn_tpl ); ?>">
			<?php echo esc_html( $btn_label ); ?>
		</button>
	</div>
	<?php endif; ?>

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
