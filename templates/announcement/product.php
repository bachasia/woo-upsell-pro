<?php
/**
 * Announcement product-page bar template.
 * Expected: $options array (text, bgcolor, text_color, text_size, text_align, bgpattern, bgimage)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $options['text'] ) ) {
	return;
}
?>
<div class="wup-announcement-product">
	<?php echo do_shortcode( wp_kses_post( $options['text'] ) ); ?>
</div>
