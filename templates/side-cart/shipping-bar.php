<?php
/**
 * Side cart free-shipping progress bar template.
 *
 * Hidden when wup_sidecart_fsg_enable != 'yes'.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( wup_get_option( 'wup_sidecart_fsg_enable', 'no' ) !== 'yes' ) {
	return;
}

$threshold = floatval( wup_get_option( 'wup_sidecart_fsg_amount', 100 ) );
$subtotal  = (float) WC()->cart->get_subtotal();
$pct       = $threshold > 0 ? min( 100, ( $subtotal / $threshold ) * 100 ) : 100;
$achieved  = $subtotal >= $threshold;
$remain    = wc_price( $threshold - $subtotal );

$msg = $achieved
	? wup_get_option( 'wup_sidecart_fsg_msg_success', 'You have free shipping!' )
	: str_replace(
		'[remain]',
		wp_kses_post( $remain ),
		wup_get_option( 'wup_sidecart_fsg_msg_progress', 'Only [remain] to free shipping' )
	);
?>
<div class="wup-sc-shipping-bar<?php echo $achieved ? ' wup-sc-shipping-achieved' : ''; ?>">
	<p class="wup-sc-shipping-msg"><?php echo wp_kses_post( $msg ); ?></p>
	<div class="wup-sc-shipping-track">
		<div class="wup-sc-shipping-fill" style="width:<?php echo esc_attr( round( $pct, 2 ) ); ?>%"></div>
	</div>
</div>
