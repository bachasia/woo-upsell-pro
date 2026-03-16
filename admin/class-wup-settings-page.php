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
				if ( empty( $field['id'] ) ) {
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

			foreach ( $this->get_schema() as $field ) {
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

			wp_safe_redirect( add_query_arg( [ 'page' => 'wup-settings', 'tab' => sanitize_key( $_GET['tab'] ?? '' ), 'settings-updated' => 'true' ], admin_url( 'admin.php' ) ) );
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

		/** Render the full tabbed settings page. */
		public function render(): void {
			$active_tab = sanitize_key( $_GET['tab'] ?? array_key_first( $this->tabs ) );
			if ( ! array_key_exists( $active_tab, $this->tabs ) ) {
				$active_tab = array_key_first( $this->tabs );
			}

			$schema = $this->get_schema();
			$fields = array_filter( $schema, fn( $f ) => ( $f['tab'] ?? '' ) === $active_tab );

			settings_errors( 'wup_messages' );
			?>
			<div class="wrap wup-settings-wrap">
				<h1><?php esc_html_e( 'Woo Upsell Pro', 'woo-upsell-pro' ); ?></h1>

				<nav class="nav-tab-wrapper woo-nav-tab-wrapper">
					<?php foreach ( $this->tabs as $slug => $label ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wup-settings&tab=' . $slug ) ); ?>"
						   class="nav-tab<?php echo $slug === $active_tab ? ' nav-tab-active' : ''; ?>">
							<?php echo esc_html( $label ); ?>
						</a>
					<?php endforeach; ?>
				</nav>

				<form method="post" action="">
					<?php wp_nonce_field( 'wup_save_settings', 'wup_nonce' ); ?>
					<input type="hidden" name="wup_save_settings" value="1">

					<table class="form-table" role="presentation">
						<?php foreach ( $fields as $field ) : ?>
							<tr>
								<th scope="row">
									<label for="<?php echo esc_attr( $field['id'] ); ?>">
										<?php echo esc_html( $field['name'] ); ?>
									</label>
								</th>
								<td>
									<?php $this->render_field( $field ); ?>
									<?php if ( ! empty( $field['desc'] ) ) : ?>
										<p class="description"><?php echo esc_html( $field['desc'] ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>

					<?php if ( $active_tab === 'wup-advanced' ) : ?>
						<hr>
						<p>
							<button type="button" id="wup-flush-cache" class="button">
								<?php esc_html_e( 'Flush Cache', 'woo-upsell-pro' ); ?>
							</button>
							<span id="wup-flush-result" style="margin-left:8px;"></span>
						</p>
						<script>
						document.getElementById('wup-flush-cache').addEventListener('click', function() {
							var el = document.getElementById('wup-flush-result');
							el.textContent = '...';
							fetch(ajaxurl, {
								method: 'POST',
								headers: {'Content-Type': 'application/x-www-form-urlencoded'},
								body: new URLSearchParams({
									action: 'wup_flush_cache',
									nonce: '<?php echo esc_js( wp_create_nonce( 'wup_admin_nonce' ) ); ?>'
								})
							}).then(r => r.json()).then(d => {
								el.textContent = d.success ? d.data : d.data;
							});
						});
						</script>
					<?php endif; ?>

					<?php submit_button( __( 'Save Settings', 'woo-upsell-pro' ) ); ?>
				</form>
			</div>
			<?php
		}

		/** Output a single settings field input. */
		private function render_field( array $field ): void {
			$id    = esc_attr( $field['id'] );
			$value = get_option( $field['id'], $field['default'] ?? '' );

			switch ( $field['type'] ) {
				case 'checkbox':
					echo '<input type="checkbox" id="' . $id . '" name="' . $id . '" value="yes"' . checked( $value, 'yes', false ) . '>';
					break;

				case 'textarea':
					echo '<textarea id="' . $id . '" name="' . $id . '" rows="4" cols="60">' . esc_textarea( (string) $value ) . '</textarea>';
					break;

				case 'number':
					echo '<input type="number" id="' . $id . '" name="' . $id . '" value="' . esc_attr( (string) $value ) . '" class="small-text">';
					break;

				case 'color':
					echo '<input type="color" id="' . $id . '" name="' . $id . '" value="' . esc_attr( (string) $value ) . '">';
					break;

				case 'select':
					echo '<select id="' . $id . '" name="' . $id . '">';
					foreach ( $field['options'] ?? [] as $opt_val => $opt_label ) {
						echo '<option value="' . esc_attr( $opt_val ) . '"' . selected( $value, $opt_val, false ) . '>' . esc_html( $opt_label ) . '</option>';
					}
					echo '</select>';
					break;

				default:
					echo '<input type="text" id="' . $id . '" name="' . $id . '" value="' . esc_attr( (string) $value ) . '" class="regular-text">';
			}
		}
	}
}
