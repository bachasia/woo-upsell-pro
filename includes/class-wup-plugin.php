<?php
/**
 * WUP_Plugin — Core plugin singleton.
 *
 * Coordinates admin, public, and feature subsystems.
 * Called from WUP_Loader::load_plugin() after WC guard passes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WUP_Plugin' ) ) {

	final class WUP_Plugin {

		/** Feature whitelist — only these tabs have their feature classes instantiated. */
		public const ACTIVE_FEATURES = [
			'wup-bundle'       => true,
			'wup-bmsm'         => true,
			'wup-popup'        => false,
			'wup-sidecart'     => false,
			'wup-cart'         => false,
			'wup-coupon'       => false,
			'wup-announcement' => false,
			'wup-sales-popup'  => false,
		];

		/** Check if a feature tab is active. Tabs not in the list (e.g. advanced, ai) default to true. */
		public static function is_feature_active( string $tab ): bool {
			return self::ACTIVE_FEATURES[ $tab ] ?? true;
		}

		/** @var WUP_Plugin|null */
		private static ?WUP_Plugin $instance = null;

		public static function get_instance(): self {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {}

		/**
		 * Bootstrap all subsystems.
		 * Called once by WUP_Loader after all files are required.
		 */
		public function init(): void {
			add_action( 'init', [ $this, 'load_textdomain' ] );

			// Register activation / deactivation hooks (must be called before output).
			WUP_Activator::register_hooks();

			// Run DB table upgrade if version changed (safe / idempotent via dbDelta).
			if ( get_option( 'wup_db_version' ) !== WUP_DB_VERSION ) {
				WUP_Activator::create_tables();
				update_option( 'wup_db_version', WUP_DB_VERSION, false );
			}

			// AI embedding layer — loaded before product source so WUP_Similarity_Search is available.
			require_once WUP_INCLUDES_DIR . 'ai/class-wup-embedding-client.php';
			require_once WUP_INCLUDES_DIR . 'ai/class-wup-openai-provider.php';
			require_once WUP_INCLUDES_DIR . 'ai/class-wup-product-embedder.php';
			require_once WUP_INCLUDES_DIR . 'ai/class-wup-similarity-search.php';
			require_once WUP_INCLUDES_DIR . 'ai/class-wup-similarity-index.php';
			require_once WUP_INCLUDES_DIR . 'ai/class-wup-similarity-batch.php';
			$this->init_ai_hooks();

			// Boot feature subsystems.
			require_once WUP_INCLUDES_DIR . 'features/class-wup-product-source.php';
			require_once WUP_INCLUDES_DIR . 'features/class-wup-variation-resolver.php';
			require_once WUP_INCLUDES_DIR . 'features/class-wup-bundle.php';
			require_once WUP_ADMIN_DIR . 'class-wup-product-fields.php';
			WUP_Product_Source::init_hooks();
			WUP_Bundle::get_instance();
			WUP_Product_Fields::get_instance();

			// Phase 03 — Popup + Side Cart (gated).
			require_once WUP_INCLUDES_DIR . 'features/class-wup-popup.php';
			require_once WUP_INCLUDES_DIR . 'features/class-wup-side-cart.php';
			if ( self::is_feature_active( 'wup-popup' ) ) {
				WUP_Popup::get_instance();
			}
			if ( self::is_feature_active( 'wup-sidecart' ) ) {
				WUP_Side_Cart::get_instance();
			}

			// Phase 04 — Cart Upsell + Thank-you Upsell + Related Products (gated).
			require_once WUP_INCLUDES_DIR . 'features/class-wup-renderer.php';
			require_once WUP_INCLUDES_DIR . 'features/class-wup-cart-upsell.php';
			require_once WUP_INCLUDES_DIR . 'features/class-wup-thankyou-upsell.php';
			require_once WUP_INCLUDES_DIR . 'features/class-wup-related.php';
			if ( self::is_feature_active( 'wup-cart' ) ) {
				WUP_Cart_Upsell::get_instance();
				WUP_Thankyou_Upsell::get_instance();
				WUP_Related::get_instance();
			}

			// Phase 05 — Buy More Save More.
			require_once WUP_INCLUDES_DIR . 'features/class-wup-buy-more-save-more.php';
			if ( self::is_feature_active( 'wup-bmsm' ) ) {
				WUP_BuyMoreSaveMore::get_instance();
			}

			// Phase 06 — Announcement Bars + Sales Popups (gated).
			require_once WUP_INCLUDES_DIR . 'features/class-wup-announcement.php';
			require_once WUP_INCLUDES_DIR . 'features/class-wup-sales-popup.php';
			if ( self::is_feature_active( 'wup-announcement' ) ) {
				WUP_Announcement::get_instance();
			}
			if ( self::is_feature_active( 'wup-sales-popup' ) ) {
				WUP_Sales_Popup::get_instance();
			}

			// Phase 07 — Email Coupon + FOMO Stock Counter (gated).
			require_once WUP_INCLUDES_DIR . 'features/class-wup-email-coupon.php';
			require_once WUP_INCLUDES_DIR . 'features/class-wup-fomo-stock.php';
			if ( self::is_feature_active( 'wup-coupon' ) ) {
				WUP_Email_Coupon::get_instance();
				WUP_Fomo_Stock::get_instance();
			}

			// Boot admin subsystem.
			if ( is_admin() ) {
				WUP_Admin::get_instance();
			}

			// Boot public subsystem.
			WUP_Public::get_instance();

			// Boot asset manager.
			WUP_Assets::get_instance();

			do_action( 'wup_loaded' );
		}

		/**
		 * Register hooks for the AI embedding layer.
		 *
		 * - On product save: schedule async embed via Action Scheduler (ships with WC).
		 * - Cache invalidation: delete similarity transients for the saved product.
		 * - Similarity index: rebuild single row after embed; full rebuild nightly.
		 * - AJAX: batch embed + status + similarity index endpoints for the admin UI.
		 */
		private function init_ai_hooks(): void {
			// Enqueue an async embed job when a product is saved (non-blocking).
			add_action( 'woocommerce_after_product_object_save', function ( WC_Product $product ): void {
				if ( ! get_option( 'wup_ai_api_key' ) ) {
					return;
				}
				$product_id = $product->get_id();
				$group      = 'wup-ai';
				$args       = [ 'product_id' => $product_id ];

				// Deduplicate: skip if an embed job is already pending for this product.
				if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( 'wup_embed_product', $args, $group ) ) {
					return;
				}

				if ( function_exists( 'as_schedule_single_action' ) ) {
					as_schedule_single_action( time(), 'wup_embed_product', $args, $group );
				} else {
					// Fallback: wp-cron (fires on next page load, not truly async).
					wp_schedule_single_event( time(), 'wup_embed_product', [ $product_id ] );
				}
			} );

			// Process the scheduled embed action: embed → invalidate cache → queue similarity rebuild.
			add_action( 'wup_embed_product', function ( int $product_id ): void {
				WUP_Product_Embedder::embed_product( $product_id );
				WUP_Similarity_Search::invalidate_cache( $product_id );
				// Rebuild this product's similarity index row in the background.
				WUP_Similarity_Batch::schedule_single( $product_id );
			} );

			// Action Scheduler handlers for the similarity index batch jobs.
			add_action( WUP_Similarity_Batch::ACTION_CHUNK, [ 'WUP_Similarity_Batch', 'handle_chunk' ] );
			add_action( WUP_Similarity_Batch::ACTION_SINGLE, [ 'WUP_Similarity_Batch', 'handle_single' ] );

			// Nightly full rebuild (wp-cron fallback — AS recurring is preferred but requires plugin).
			if ( ! wp_next_scheduled( 'wup_similarity_nightly_rebuild' ) ) {
				wp_schedule_event( strtotime( 'tomorrow 02:00:00' ), 'daily', 'wup_similarity_nightly_rebuild' );
			}
			add_action( 'wup_similarity_nightly_rebuild', [ 'WUP_Similarity_Batch', 'schedule_full_rebuild' ] );

			// Invalidate entire index when embedding model changes.
			add_action( 'update_option_wup_ai_embedding_model', function (): void {
				WUP_Similarity_Index::invalidate_all();
				WUP_Similarity_Batch::schedule_full_rebuild();
			} );

			// AJAX: batch embed — processes one chunk and returns progress.
			add_action( 'wp_ajax_wup_batch_embed', [ $this, 'ajax_batch_embed' ] );

			// AJAX: status — returns embedded count vs total.
			add_action( 'wp_ajax_wup_embedding_status', [ $this, 'ajax_embedding_status' ] );

			// AJAX: trigger a full similarity index rebuild.
			add_action( 'wp_ajax_wup_rebuild_similarity', [ $this, 'ajax_rebuild_similarity' ] );

			// AJAX: return similarity index status.
			add_action( 'wp_ajax_wup_similarity_status', [ $this, 'ajax_similarity_status' ] );
		}

		/** AJAX handler: process one batch of products for embedding. */
		public function ajax_batch_embed(): void {
			check_ajax_referer( 'wup_batch_embed', 'nonce' );
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_send_json_error( 'Unauthorized' );
			}

			$offset     = max( 0, (int) ( $_POST['offset'] ?? 0 ) );
			$batch_size = min( 100, max( 1, (int) ( $_POST['batch_size'] ?? 50 ) ) );
			$model      = get_option( 'wup_ai_embedding_model', 'text-embedding-3-small' );

			// Fetch all published products without an embedding (or with mismatched model).
			$all_ids = $this->get_unembedded_product_ids( $model );
			$total   = count( $all_ids );
			$batch   = array_slice( $all_ids, $offset, $batch_size );

			$errors = 0;
			if ( ! empty( $batch ) ) {
				try {
					$client = WUP_Embedding_Client::make();
					$id_map = [];
					$texts  = [];
					foreach ( $batch as $pos => $product_id ) {
						$text = WUP_Product_Embedder::build_embed_text( $product_id );
						if ( $text ) {
							$id_map[ $pos ] = $product_id;
							$texts[ $pos ]  = $text;
						}
					}

					if ( ! empty( $texts ) ) {
						$vectors = $client->embed_batch( array_values( $texts ) );
						$keys    = array_keys( $id_map );
						foreach ( $keys as $i => $pos ) {
							$product_id = $id_map[ $pos ];
							if ( isset( $vectors[ $i ] ) ) {
								WUP_Product_Embedder::store_embedding( $product_id, $vectors[ $i ], $model );
								WUP_Similarity_Search::invalidate_cache( $product_id );
							} else {
								$errors++;
							}
						}
					}
				} catch ( RuntimeException $e ) {
					wp_send_json_error( $e->getMessage() );
				}
			}

			$embedded_total = WUP_Similarity_Batch::count_embedded_products();

			wp_send_json_success( [
				'processed'      => count( $batch ),
				'total'          => $total,
				'errors'         => $errors,
				'done'           => ( $offset + count( $batch ) ) >= $total,
				'embedded_total' => $embedded_total,
			] );
		}

		/** AJAX handler: return embedding status counts. */
		public function ajax_embedding_status(): void {
			check_ajax_referer( 'wup_batch_embed', 'nonce' );
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_send_json_error( 'Unauthorized' );
			}

			$model     = get_option( 'wup_ai_embedding_model', 'text-embedding-3-small' );
			$total     = (int) wp_count_posts( 'product' )->publish;
			$embedded  = WUP_Similarity_Batch::count_embedded_products();
			$remaining = max( 0, $total - $embedded );
			$cost      = number_format( $remaining * 300 * 0.00000002, 4 );

			wp_send_json_success( [
				'total'     => $total,
				'embedded'  => $embedded,
				'remaining' => $remaining,
				'model'     => $model,
				'cost_est'  => $cost,
			] );
		}

		/** AJAX handler: schedule a full similarity index rebuild via Action Scheduler. */
		public function ajax_rebuild_similarity(): void {
			check_ajax_referer( 'wup_batch_embed', 'nonce' );
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_send_json_error( 'Unauthorized' );
			}

			WUP_Similarity_Batch::schedule_full_rebuild();

			wp_send_json_success( [ 'scheduled' => true ] );
		}

		/** AJAX handler: return similarity index status (indexed count, last build). */
		public function ajax_similarity_status(): void {
			check_ajax_referer( 'wup_batch_embed', 'nonce' );
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_send_json_error( 'Unauthorized' );
			}

			wp_send_json_success( [
				'indexed'      => WUP_Similarity_Index::count_indexed(),
				'embedded'     => WUP_Similarity_Batch::count_embedded_products(),
				'last_built'   => get_option( 'wup_similarity_last_full_build', 0 ),
				'last_computed'=> WUP_Similarity_Index::get_last_computed(),
			] );
		}

		/** Return product IDs that do not yet have an embedding for the active model. */
		private function get_unembedded_product_ids( string $model ): array {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} pm  ON pm.post_id  = p.ID AND pm.meta_key  = %s
				 LEFT JOIN {$wpdb->postmeta} pm2 ON pm2.post_id = p.ID AND pm2.meta_key = %s
				 WHERE p.post_type = 'product' AND p.post_status = 'publish'
				   AND ( pm.meta_value IS NULL OR pm2.meta_value != %s )",
				WUP_Product_Embedder::META_VECTOR,
				WUP_Product_Embedder::META_MODEL,
				$model
			) ) );
		}

		/** Load plugin text domain for translations. */
		public function load_textdomain(): void {
			load_plugin_textdomain(
				WUP_TEXT_DOMAIN,
				false,
				dirname( plugin_basename( WUP_FILE ) ) . '/languages'
			);
		}
	}
}
