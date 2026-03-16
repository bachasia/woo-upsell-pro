<?php
/**
 * Announcement topbar template.
 * Expected: $options array (text, bgcolor, text_color, text_size, bgpattern, bgimage)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $options['text'] ) ) {
	return;
}
?>
<div class="wup-announcement-top">
	<?php echo do_shortcode( wp_kses_post( $options['text'] ) ); ?>
</div>
