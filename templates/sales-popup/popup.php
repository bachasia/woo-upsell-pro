<?php
/**
 * Sales popup shell template.
 * Expected: $template (modern|minimal|dark), $desktop_position, $mobile_position
 * Content is filled dynamically by wupSalesPopup JS.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$template         = sanitize_html_class( $template ?? 'modern' );
$desktop_position = sanitize_html_class( $desktop_position ?? 'bottom_left' );
$mobile_position  = sanitize_html_class( $mobile_position ?? 'bottom_center' );
?>
<div id="wup-sales-popup"
     class="wup-sp wup-sp--<?php echo esc_attr( $template ); ?> wup-sp--<?php echo esc_attr( $desktop_position ); ?> wup-sp--mobile-<?php echo esc_attr( $mobile_position ); ?>"
     style="display:none;" aria-live="polite">
	<div class="wup-sp__image">
		<img src="" alt="" loading="lazy">
	</div>
	<div class="wup-sp__content">
		<p class="wup-sp__message"></p>
	</div>
	<button class="wup-sp__close" aria-label="<?php esc_attr_e( 'Close', 'wup-upsell-pro' ); ?>">&times;</button>
</div>
