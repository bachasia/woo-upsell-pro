<?php
/**
 * WUP_Product_Fields — adds "WUP Tags" tab to WooCommerce product edit screen.
 *
 * Stores comma-separated tag slugs in _wup_tags post meta.
 * Used by WUP_Product_Source when source=tags.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WUP_Product_Fields' ) ) {

	class WUP_Product_Fields {

		/** @var WUP_Product_Fields|null */
		private static ?WUP_Product_Fields $instance = null;

		public static function get_instance(): self {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			add_filter( 'woocommerce_product_data_tabs',   [ $this, 'add_tab' ] );
			add_action( 'woocommerce_product_data_panels', [ $this, 'render_panel' ] );
			add_action( 'woocommerce_process_product_meta', [ $this, 'save_fields' ] );
		}

		/**
		 * Register the WUP Tags product data tab.
		 *
		 * @param array $tabs Existing product data tabs.
		 * @return array
		 */
		public function add_tab( array $tabs ): array {
			$tabs['wup_upsell_tags'] = [
				'label'    => __( 'WUP Tags', 'woo-upsell-pro' ),
				'target'   => 'wup_upsell_tags_panel',
				'class'    => [],
				'priority' => 80,
			];
			return $tabs;
		}

		/**
		 * Render the WUP Tags panel content.
		 */
		public function render_panel(): void {
			global $post;
			?>
			<div id="wup_upsell_tags_panel" class="panel woocommerce_options_panel">
				<div class="options_group">
					<?php
					woocommerce_wp_text_input( [
						'id'          => '_wup_tags',
						'label'       => __( 'Upsell Tags', 'woo-upsell-pro' ),
						'desc_tip'    => true,
						'description' => __( 'Comma-separated tag slugs. Example: fbt-bundle,related-item. Used for tag-based product source resolution.', 'woo-upsell-pro' ),
						'value'       => get_post_meta( $post->ID, '_wup_tags', true ),
						'placeholder' => 'fbt-bundle,tag-slug',
					] );
					?>
				</div>
			</div>
			<?php
		}

		/**
		 * Save the _wup_tags meta field on product save.
		 *
		 * @param int $post_id Product post ID.
		 */
		public function save_fields( int $post_id ): void {
			if ( ! isset( $_POST['_wup_tags'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				return;
			}
			$tags = sanitize_text_field( wp_unslash( $_POST['_wup_tags'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			update_post_meta( $post_id, '_wup_tags', $tags );
		}
	}
}
