<?php
/**
 * WUP_Embedding_Provider — interface for embedding providers.
 * WUP_Embedding_Client   — factory that returns the active provider instance.
 *
 * Pluggable: add new providers by implementing WUP_Embedding_Provider and
 * registering via the wup_embedding_providers filter.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Provider interface ────────────────────────────────────────────────────────

interface WUP_Embedding_Provider {

	/**
	 * Embed a single text string.
	 *
	 * @param string $text Plain text to embed (HTML stripped, max ~2000 chars).
	 * @return float[]     Embedding vector.
	 * @throws RuntimeException On API error.
	 */
	public function embed( string $text ): array;

	/**
	 * Embed multiple texts in one API call (order preserved).
	 *
	 * @param string[] $texts
	 * @return float[][] Indexed same as $texts.
	 * @throws RuntimeException On API error.
	 */
	public function embed_batch( array $texts ): array;
}

// ── Factory ───────────────────────────────────────────────────────────────────

if ( ! class_exists( 'WUP_Embedding_Client' ) ) {

	class WUP_Embedding_Client {

		/**
		 * Return the active embedding provider instance.
		 *
		 * Reads wup_ai_provider setting (default: 'openai').
		 * Throws RuntimeException if provider class not registered.
		 */
		public static function make(): WUP_Embedding_Provider {
			$provider = get_option( 'wup_ai_provider', 'openai' );
			$api_key  = get_option( 'wup_ai_api_key', '' );
			$model    = get_option( 'wup_ai_embedding_model', 'text-embedding-3-small' );

			/**
			 * Filter to register additional providers.
			 * Map of slug => callable that returns WUP_Embedding_Provider instance.
			 *
			 * @param array{string: callable} $providers
			 */
			$providers = apply_filters( 'wup_embedding_providers', [
				'openai' => fn() => new WUP_OpenAI_Provider( $api_key, $model ),
			] );

			if ( ! isset( $providers[ $provider ] ) ) {
				throw new RuntimeException( "WUP: unknown embedding provider '{$provider}'" );
			}

			return ( $providers[ $provider ] )();
		}
	}
}
