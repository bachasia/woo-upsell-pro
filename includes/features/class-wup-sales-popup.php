<?php
/**
 * WUP_Sales_Popup — social proof notification popups.
 *
 * Shows timed "Name in City purchased Product X minutes ago" notifications.
 * Product list sourced from best sellers (smart_random) or configured IDs.
 * All rotation logic runs client-side via wupSalesPopup JS config.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WUP_Sales_Popup' ) ) {

	class WUP_Sales_Popup {

		/** @var WUP_Sales_Popup|null */
		private static ?WUP_Sales_Popup $instance = null;

		public static function get_instance(): self {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			if ( 'yes' !== wup_get_option( 'wup_popup_enable', 'no' ) ) {
				return;
			}

			add_action( 'wp_footer',          [ $this, 'render_shell' ] );
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		}

		// ------------------------------------------------------------------ //
		// Rendering
		// ------------------------------------------------------------------ //

		/** Inject hidden popup shell + JS config into page footer. */
		public function render_shell(): void {
			if ( ! $this->should_show() ) {
				return;
			}

			$template         = wup_get_option( 'wup_popup_template', 'modern' );
			$desktop_position = wup_get_option( 'wup_popup_position', 'bottom_left' );
			$mobile_position  = wup_get_option( 'wup_popup_mobile', 'mobile-bottom_center' );

			include WUP_TEMPLATES_DIR . 'sales-popup/popup.php';
		}

		// ------------------------------------------------------------------ //
		// Assets
		// ------------------------------------------------------------------ //

		public function enqueue_assets(): void {
			if ( is_admin() || ! $this->should_show() ) {
				return;
			}

			// Piggyback on existing wup-popup handle (already registered by WUP_Assets).
			wp_enqueue_script( 'wup-popup' );

			wp_localize_script( 'wup-popup', 'wupSalesPopup', $this->get_js_data() );
		}

		// ------------------------------------------------------------------ //
		// Private helpers
		// ------------------------------------------------------------------ //

		/** Check whether popup should render on current page. */
		private function should_show(): bool {
			$pages = wup_get_option( 'wup_popup_pages', 'all' );

			switch ( $pages ) {
				case 'all':
					return true;
				case 'home_only':
					return is_front_page();
				case 'product_cart':
					return is_product() || is_cart();
				default:
					return false;
			}
		}

		/** Build JS localisation data. */
		private function get_js_data(): array {
			$names_raw  = wup_get_option( 'wup_popup_names', '' );
			$cities_raw = wup_get_option( 'wup_popup_cities', '' );

			$names  = array_filter( array_map( 'trim', explode( "\n", $names_raw ) ) );
			$cities = array_filter( array_map( 'trim', explode( "\n", $cities_raw ) ) );

			return [
				'products'     => $this->get_products(),
				'names'        => array_values( $names ),
				'cities'       => array_values( $cities ),
				'template'     => wup_get_option( 'wup_popup_template', 'modern' ),
				'loop_time'    => intval( wup_get_option( 'wup_popup_loop_time', 5 ) ),
				'display_time' => intval( wup_get_option( 'wup_popup_display_time', 4 ) ),
				'msg_template' => wup_get_option( 'wup_popup_msg_template', '{{name}} in {{city}} purchased {{product}} {{time}} ago' ),
				'pages'        => wup_get_option( 'wup_popup_pages', 'all' ),
			];
		}

		/** Resolve product list based on source setting. */
		private function get_products(): array {
			$source_type = wup_get_option( 'wup_popup_source', 'smart_random' );
			$args        = [ 'status' => 'publish', 'limit' => 20 ];

			if ( $source_type === 'smart_selected' ) {
				$ids_raw = wup_get_option( 'wup_popup_products', '' );
				if ( ! empty( $ids_raw ) ) {
					$args['include'] = array_map( 'intval', explode( ',', $ids_raw ) );
					$args['limit']   = -1;
				}
			} else {
				// smart_random: recent products as proxy for popularity.
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
			}

			$products = [];
			foreach ( wc_get_products( $args ) as $product ) {
				$img_src = wp_get_attachment_image_src( $product->get_image_id(), 'shop_thumbnail' );
				$products[] = [
					'id'    => $product->get_id(),
					'name'  => $product->get_name(),
					'url'   => get_permalink( $product->get_id() ),
					'image' => $img_src ? $img_src[0] : wc_placeholder_img_src(),
				];
			}

			return $products;
		}
	}
}
