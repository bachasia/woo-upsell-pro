<?php
/**
 * WUP_Settings_Page — Tabbed PHP settings page + WP Settings API registration.
 *
 * Schema defined in WUP_Settings_Schema trait (class-wup-settings-schema.php).
 * Dynamic CSS fields are pushed to WUP_Assets::register_schema() on init.
 * Cache flush exposed via admin-ajax action wup_flush_cache.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once WUP_ADMIN_DIR . 'class-wup-settings-schema.php';

if ( ! class_exists( 'WUP_Settings_Page' ) ) {

	class WUP_Settings_Page {

		use WUP_Settings_Schema;

		/** @var WUP_Settings_Page|null */
		private static ?WUP_Settings_Page $instance = null;

		/** Tab definitions: slug => label */
		private array $tabs = [
			'wup-bundle'       => 'FBT Bundle',
			'wup-popup'        => 'Add-to-Cart Popup',
			'wup-sidecart'     => 'Side Cart',
			'wup-bmsm'         => 'Buy More Save More',
			'wup-cart'         => 'Cart & Thank-you',
			'wup-coupon'       => 'Coupons',
			'wup-announcement' => 'Announcements',
			'wup-sales-popup'  => 'Sales Popups',
			'wup-advanced'     => 'Advanced',
			'wup-ai'           => 'AI Settings',
		];

		public static function get_instance(): self {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			add_action( 'admin_init', [ $this, 'register_settings' ] );
			add_action( 'admin_init', [ $this, 'handle_save' ] );
			add_action( 'admin_init', [ $this, 'push_css_schema' ] );
			add_action( 'wp_ajax_wup_flush_cache', [ $this, 'ajax_flush_cache' ] );
		}

		/** Register each setting with WordPress so it can be saved/read. */
		public function register_settings(): void {
			foreach ( $this->get_schema() as $field ) {
				if ( empty( $field['id'] ) || $field['type'] === 'locked_checkbox' ) {
					continue;
				}

				$sanitize = match ( $field['type'] ) {
					'checkbox'  => fn( $v ) => ( $v === 'yes' ) ? 'yes' : 'no',
					'number'    => fn( $v ) => absint( $v ),
					'color'     => fn( $v ) => sanitize_hex_color( $v ) ?? '',
					'textarea'  => fn( $v ) => sanitize_textarea_field( $v ),
					'select'    => fn( $v ) => sanitize_key( $v ),
					default     => fn( $v ) => sanitize_text_field( $v ),
				};

				register_setting( 'wup_settings', sanitize_key( $field['id'] ), [ 'sanitize_callback' => $sanitize ] );
			}
		}

		/** Push CSS-mapped fields to WUP_Assets for dynamic CSS generation. */
		public function push_css_schema(): void {
			$css_fields = array_filter( $this->get_schema(), fn( $f ) => ! empty( $f['css'] ) );
			if ( $css_fields ) {
				WUP_Assets::get_instance()->register_schema( array_values( $css_fields ) );
			}
		}

		/** Handle settings form POST. */
		public function handle_save(): void {
			if ( ! isset( $_POST['wup_save_settings'] ) ) {
				return;
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			check_admin_referer( 'wup_save_settings', 'wup_nonce' );

			// Only save fields for the active tab — other tabs' fields are not in $_POST,
			// so iterating all schema would reset their checkbox values to 'no'.
			$active_tab = sanitize_key( $_GET['tab'] ?? array_key_first( $this->tabs ) );
			$schema     = array_filter( $this->get_schema(), fn( $f ) => ( $f['tab'] ?? '' ) === $active_tab );

			foreach ( $schema as $field ) {
				$key = sanitize_key( $field['id'] ?? '' );
				if ( ! $key ) {
					continue;
				}

				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above
				$raw = $_POST[ $key ] ?? null;

				switch ( $field['type'] ) {
					case 'checkbox':
						update_option( $key, ( $raw === 'yes' ) ? 'yes' : 'no' );
						break;
					case 'textarea':
						update_option( $key, isset( $raw ) ? sanitize_textarea_field( wp_unslash( $raw ) ) : '' );
						break;
					case 'number':
						update_option( $key, isset( $raw ) ? (int) $raw : ( $field['default'] ?? 0 ) );
						break;
					case 'color':
						$val = isset( $raw ) ? sanitize_hex_color( wp_unslash( $raw ) ) : '';
						update_option( $key, $val ?? '' );
						break;
					default:
						update_option( $key, isset( $raw ) ? sanitize_text_field( wp_unslash( $raw ) ) : '' );
				}
			}

			add_settings_error( 'wup_messages', 'wup_saved', __( 'Settings saved.', 'woo-upsell-pro' ), 'updated' );
			set_transient( 'settings_errors', get_settings_errors(), 30 );

			wp_safe_redirect( add_query_arg( [ 'page' => 'wup-settings', 'tab' => sanitize_key( $_GET['tab'] ?? array_key_first( $this->tabs ) ), 'settings-updated' => 'true' ], admin_url( 'admin.php' ) ) );
			exit;
		}

		/** AJAX: flush all wup_ transient caches. */
		public function ajax_flush_cache(): void {
			check_ajax_referer( 'wup_admin_nonce', 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Unauthorized' );
			}

			wup_delete_transients_by_prefix( '' );
			wp_send_json_success( __( 'Cache cleared.', 'woo-upsell-pro' ) );
		}

		/** Tab icons (Dashicons classes). */
		private array $tab_icons = [
			'wup-bundle'       => 'dashicons-products',
			'wup-popup'        => 'dashicons-welcome-widgets-menus',
			'wup-sidecart'     => 'dashicons-cart',
			'wup-bmsm'         => 'dashicons-tag',
			'wup-cart'         => 'dashicons-yes-alt',
			'wup-coupon'       => 'dashicons-tickets-alt',
			'wup-announcement' => 'dashicons-megaphone',
			'wup-sales-popup'  => 'dashicons-bell',
			'wup-advanced'     => 'dashicons-admin-settings',
		];

		/** Render the full settings page with sidebar layout. */
		public function render(): void {
			$active_tab = sanitize_key( $_GET['tab'] ?? array_key_first( $this->tabs ) );
			if ( ! array_key_exists( $active_tab, $this->tabs ) ) {
				$active_tab = array_key_first( $this->tabs );
			}

			$fields = array_filter( $this->get_schema(), fn( $f ) => ( $f['tab'] ?? '' ) === $active_tab );
			$saved  = isset( $_GET['settings-updated'] );
			?>
			<div class="wrap wup-settings-wrap">

				<!-- Sidebar navigation -->
				<aside class="wup-sidebar">
					<div class="wup-sidebar-brand">
						<strong>Woo Upsell Pro</strong>
						<span>Settings</span>
					</div>
					<nav>
						<?php foreach ( $this->tabs as $slug => $label ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wup-settings&tab=' . $slug ) ); ?>"
							   class="<?php echo $slug === $active_tab ? 'active' : ''; ?>">
								<span class="wup-nav-icon dashicons <?php echo esc_attr( $this->tab_icons[ $slug ] ?? 'dashicons-admin-generic' ); ?>"></span>
								<?php echo esc_html( $label ); ?>
							</a>
						<?php endforeach; ?>
					</nav>
				</aside>

				<!-- Content area -->
				<div class="wup-content">

					<?php if ( $saved ) : ?>
						<div class="notice notice-success is-dismissible" style="border-radius:7px;">
							<p><?php esc_html_e( 'Settings saved.', 'woo-upsell-pro' ); ?></p>
						</div>
					<?php endif; ?>

					<div class="wup-content-header">
						<h2><?php echo esc_html( $this->tabs[ $active_tab ] ); ?></h2>
					</div>

					<form method="post" action="">
						<?php wp_nonce_field( 'wup_save_settings', 'wup_nonce' ); ?>
						<input type="hidden" name="wup_save_settings" value="1">

						<!-- Fields card -->
						<div class="wup-card">
							<div class="wup-card-title"><?php echo esc_html( $this->tabs[ $active_tab ] ); ?> Options</div>
							<?php foreach ( $fields as $field ) : ?>
								<div class="wup-field">
									<div class="wup-field-label">
										<label for="<?php echo esc_attr( $field['id'] ); ?>"><?php echo esc_html( $field['name'] ); ?></label>
										<?php if ( ! empty( $field['desc'] ) ) : ?>
											<div class="wup-field-desc"><?php echo esc_html( $field['desc'] ); ?></div>
										<?php endif; ?>
									</div>
									<div class="wup-field-input">
										<?php $this->render_field( $field ); ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>

						<?php if ( $active_tab === 'wup-ai' ) : ?>
							<?php $this->render_batch_embed_card(); ?>
							<?php $this->render_similarity_index_card(); ?>
						<?php endif; ?>

						<?php if ( $active_tab === 'wup-advanced' ) : ?>
							<div class="wup-card">
								<div class="wup-card-title">Cache</div>
								<div class="wup-field">
									<div class="wup-field-label">
										<?php esc_html_e( 'Flush Transient Cache', 'woo-upsell-pro' ); ?>
										<div class="wup-field-desc"><?php esc_html_e( 'Clear all cached product queries.', 'woo-upsell-pro' ); ?></div>
									</div>
									<div class="wup-field-input">
										<button type="button" id="wup-flush-cache" class="wup-btn-secondary">
											<?php esc_html_e( 'Flush Cache', 'woo-upsell-pro' ); ?>
										</button>
										<span id="wup-flush-result" style="font-size:12px;color:#10b981;margin-top:4px;"></span>
									</div>
								</div>
							</div>
							<script>
							document.getElementById('wup-flush-cache').addEventListener('click',function(){
								var el=document.getElementById('wup-flush-result');
								el.textContent='...';
								fetch(ajaxurl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
								body:new URLSearchParams({action:'wup_flush_cache',nonce:'<?php echo esc_js( wp_create_nonce( 'wup_admin_nonce' ) ); ?>'})
								}).then(r=>r.json()).then(d=>{el.textContent=d.success?d.data:'Error';});
							});
							</script>
						<?php endif; ?>

						<!-- Actions bar -->
						<div class="wup-actions">
							<button type="submit" class="wup-btn-save"><?php esc_html_e( 'Save Settings', 'woo-upsell-pro' ); ?></button>
						</div>
					</form>
				</div>
			</div>
			<?php
		}

		/** Render the batch embedding card (shown only on the AI Settings tab). */
		private function render_batch_embed_card(): void {
			// Count embedded vs total published products.
			$total_products = (int) wp_count_posts( 'product' )->publish;
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$embedded_count = (int) $wpdb->get_var(
				"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_wup_embedding'"
			);
			$remaining      = max( 0, $total_products - $embedded_count );

			// Cost estimate: ~300 tokens × $0.02/1M tokens per product.
			$cost_estimate = number_format( $remaining * 300 * 0.00000002, 4 );
			$api_key       = get_option( 'wup_ai_api_key', '' );
			$nonce         = wp_create_nonce( 'wup_batch_embed' );
			?>
			<div class="wup-card">
				<div class="wup-card-title">Product Embeddings</div>
				<div class="wup-field">
					<div class="wup-field-label">
						Status
						<div class="wup-field-desc">Products with AI embeddings generated</div>
					</div>
					<div class="wup-field-input">
						<span id="wup-embed-count"><?php echo esc_html( $embedded_count ); ?></span> / <?php echo esc_html( $total_products ); ?>
						<?php if ( $remaining > 0 ) : ?>
							&nbsp;&mdash;&nbsp;<small>Estimated cost for remaining <?php echo esc_html( $remaining ); ?>: ~$<?php echo esc_html( $cost_estimate ); ?></small>
						<?php else : ?>
							&nbsp;<span style="color:#10b981;">&#10003; All products embedded</span>
						<?php endif; ?>
					</div>
				</div>
				<?php if ( $api_key ) : ?>
				<div class="wup-field">
					<div class="wup-field-label">
						Generate Embeddings
						<div class="wup-field-desc">Process all products without an embedding. Resumes where it left off.</div>
					</div>
					<div class="wup-field-input">
						<button type="button" id="wup-batch-embed-btn" class="wup-btn-secondary">Generate Embeddings for All Products</button>
						<div id="wup-batch-embed-progress" style="display:none;margin-top:10px;">
							<div style="background:#e5e7eb;border-radius:4px;height:8px;width:360px;overflow:hidden;">
								<div id="wup-batch-embed-bar" style="background:#10b981;height:100%;width:0;transition:width .3s;"></div>
							</div>
							<div style="margin-top:6px;font-size:12px;">
								<span id="wup-batch-embed-status">Starting…</span>
							</div>
						</div>
					</div>
				</div>
				<script>
				(function(){
					var btn      = document.getElementById('wup-batch-embed-btn');
					var progress = document.getElementById('wup-batch-embed-progress');
					var bar      = document.getElementById('wup-batch-embed-bar');
					var status   = document.getElementById('wup-batch-embed-status');
					var count    = document.getElementById('wup-embed-count');

					if (!btn) return;

					btn.addEventListener('click', function(){
						btn.disabled = true;
						progress.style.display = 'block';
						runBatch(0, 0);
					});

					function runBatch(offset, totalDone) {
						fetch(ajaxurl, {
							method: 'POST',
							headers: {'Content-Type': 'application/x-www-form-urlencoded'},
							body: new URLSearchParams({
								action: 'wup_batch_embed',
								nonce: '<?php echo esc_js( $nonce ); ?>',
								offset: offset,
								batch_size: 50
							})
						})
						.then(function(r){ return r.json(); })
						.then(function(d){
							if (!d.success) {
								status.textContent = 'Error: ' + (d.data || 'unknown');
								btn.disabled = false;
								return;
							}
							var data     = d.data;
							var done     = totalDone + data.processed;
							var pct      = data.total > 0 ? Math.round(done / data.total * 100) : 100;
							bar.style.width = pct + '%';
							status.textContent = done + ' / ' + data.total + ' embedded' + (data.errors > 0 ? ' (' + data.errors + ' errors)' : '');
							if (count) count.textContent = data.embedded_total;

							if (data.done) {
								if (data.embedded_total > 0) {
									status.textContent = data.embedded_total + ' products embedded — Done!';
								} else {
									status.textContent = done + ' processed, 0 saved — check WooCommerce logs';
								}
								btn.disabled = false;
								return;
							}
							// Rate-limit: ~900ms between batches to respect OpenAI TPM limits.
							setTimeout(function(){ runBatch(offset + data.processed, done); }, 900);
						})
						.catch(function(e){
							status.textContent = 'Network error: ' + e.message;
							btn.disabled = false;
						});
					}
				})();
				</script>
				<?php else : ?>
				<div class="wup-field">
					<div class="wup-field-label" colspan="2">
						<div class="wup-field-desc" style="color:#f59e0b;">&#9888; Enter your API key above and save settings to enable batch generation.</div>
					</div>
				</div>
				<?php endif; ?>
			</div>
			<?php
		}

		/** Render the similarity index status + rebuild card (shown on AI Settings tab). */
		private function render_similarity_index_card(): void {
			$indexed        = WUP_Similarity_Index::count_indexed();
			$embedded       = WUP_Similarity_Batch::count_embedded_products();
			$last_built_ts  = (int) get_option( 'wup_similarity_last_full_build', 0 );
			$last_computed  = WUP_Similarity_Index::get_last_computed();
			$nonce          = wp_create_nonce( 'wup_batch_embed' );

			$last_built_str = $last_built_ts
				? esc_html( human_time_diff( $last_built_ts, time() ) . ' ago' )
				: 'Never';
			?>
			<div class="wup-card">
				<div class="wup-card-title">Similarity Index</div>
				<div class="wup-field">
					<div class="wup-field-label">
						Index Status
						<div class="wup-field-desc">Pre-computed product similarity rows — enables O(1) lookups</div>
					</div>
					<div class="wup-field-input">
						<span id="wup-sim-indexed"><?php echo esc_html( $indexed ); ?></span> / <?php echo esc_html( $embedded ); ?> products indexed
						<?php if ( $indexed > 0 && $indexed >= $embedded ) : ?>
							&nbsp;<span style="color:#10b981;">&#10003; Index complete</span>
						<?php elseif ( $indexed > 0 ) : ?>
							&nbsp;<span style="color:#f59e0b;">&#9888; Partial index (<?php echo esc_html( $embedded - $indexed ); ?> missing)</span>
						<?php else : ?>
							&nbsp;<span style="color:#6b7280;">Not built yet</span>
						<?php endif; ?>
						<div style="font-size:11px;color:#6b7280;margin-top:3px;">Last full build: <span id="wup-sim-last-built"><?php echo esc_html( $last_built_str ); ?></span></div>
					</div>
				</div>
				<div class="wup-field">
					<div class="wup-field-label">
						Rebuild Index
						<div class="wup-field-desc">Schedule a full background rebuild via Action Scheduler</div>
					</div>
					<div class="wup-field-input">
						<button type="button" id="wup-rebuild-sim-btn" class="wup-btn-secondary"<?php echo $embedded === 0 ? ' disabled' : ''; ?>>
							Rebuild Similarity Index
						</button>
						<span id="wup-rebuild-sim-status" style="font-size:12px;margin-left:10px;"></span>
					</div>
				</div>
			</div>
			<script>
			(function(){
				var btn    = document.getElementById('wup-rebuild-sim-btn');
				var status = document.getElementById('wup-rebuild-sim-status');
				if (!btn) return;
				btn.addEventListener('click', function(){
					btn.disabled = true;
					status.textContent = 'Scheduling…';
					fetch(ajaxurl, {
						method: 'POST',
						headers: {'Content-Type': 'application/x-www-form-urlencoded'},
						body: new URLSearchParams({action: 'wup_rebuild_similarity', nonce: '<?php echo esc_js( $nonce ); ?>'})
					})
					.then(function(r){ return r.json(); })
					.then(function(d){
						if (d.success) {
							status.style.color = '#10b981';
							status.textContent = 'Rebuild scheduled — runs in the background.';
						} else {
							status.style.color = '#ef4444';
							status.textContent = 'Error: ' + (d.data || 'unknown');
						}
						btn.disabled = false;
					})
					.catch(function(e){
						status.style.color = '#ef4444';
						status.textContent = 'Network error: ' + e.message;
						btn.disabled = false;
					});
				});
			})();
			</script>
			<?php
		}

		/** Output a single settings field input. */
		private function render_field( array $field ): void {
			$id    = esc_attr( $field['id'] );
			$value = get_option( $field['id'], $field['default'] ?? '' );

			switch ( $field['type'] ) {
				case 'checkbox':
					echo '<label class="wup-toggle">';
					echo '<input type="checkbox" id="' . $id . '" name="' . $id . '" value="yes"' . checked( $value, 'yes', false ) . '>';
					echo '<span class="wup-toggle-track"></span>';
					echo '<span class="wup-toggle-label">' . esc_html__( 'Enabled', 'woo-upsell-pro' ) . '</span>';
					echo '</label>';
					break;

				case 'textarea':
					echo '<textarea id="' . $id . '" name="' . $id . '" rows="4">' . esc_textarea( (string) $value ) . '</textarea>';
					break;

				case 'number':
					echo '<input type="number" id="' . $id . '" name="' . $id . '" value="' . esc_attr( (string) $value ) . '">';
					break;

				case 'color':
					echo '<input type="color" id="' . $id . '" name="' . $id . '" value="' . esc_attr( (string) $value ) . '">';
					break;

				case 'locked_checkbox':
					// Read-only toggle — always on, cannot be changed.
					echo '<label class="wup-toggle" style="pointer-events:none;opacity:.7;">';
					echo '<input type="checkbox" checked disabled>';
					echo '<span class="wup-toggle-track"></span>';
					echo '<span class="wup-toggle-label">' . esc_html__( 'Always enabled', 'woo-upsell-pro' ) . '</span>';
					echo '</label>';
					break;

				case 'password':
					echo '<input type="password" id="' . $id . '" name="' . $id . '" value="' . esc_attr( (string) $value ) . '" autocomplete="new-password" style="width:360px;">';
					break;

				case 'select':
					echo '<select id="' . $id . '" name="' . $id . '">';
					foreach ( $field['options'] ?? [] as $opt_val => $opt_label ) {
						echo '<option value="' . esc_attr( $opt_val ) . '"' . selected( $value, $opt_val, false ) . '>' . esc_html( $opt_label ) . '</option>';
					}
					echo '</select>';
					break;

				default:
					echo '<input type="text" id="' . $id . '" name="' . $id . '" value="' . esc_attr( (string) $value ) . '">';
			}
		}
	}
}
