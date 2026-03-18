<?php
/**
 * WUP_OpenAI_Provider — OpenAI embedding provider implementation.
 *
 * Implements WUP_Embedding_Provider using wp_remote_post() (no curl dependency).
 * Supports both single embed and batch embed (one API call for multiple texts).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WUP_OpenAI_Provider' ) ) {

	class WUP_OpenAI_Provider implements WUP_Embedding_Provider {

		private string $api_key;
		private string $model;

		// OpenAI embeddings endpoint.
		private const ENDPOINT = 'https://api.openai.com/v1/embeddings';

		public function __construct( string $api_key, string $model = 'text-embedding-3-small' ) {
			$this->api_key = $api_key;
			$this->model   = $model;
		}

		/** {@inheritdoc} */
		public function embed( string $text ): array {
			$result = $this->embed_batch( [ $text ] );
			return $result[0] ?? [];
		}

		/** {@inheritdoc} */
		public function embed_batch( array $texts ): array {
			if ( empty( $texts ) ) {
				return [];
			}

			if ( empty( $this->api_key ) ) {
				throw new RuntimeException( 'WUP: OpenAI API key not configured.' );
			}

			// Re-index to ensure 0-based sequential keys (required for OpenAI order guarantee).
			$texts = array_values( $texts );

			$body = [ 'model' => $this->model, 'input' => $texts ];

			// Dimensionality reduction — text-embedding-3-* supports native truncation.
			// 256 dims = ~3KB/product vs 18KB full; quality loss is minimal for product matching.
			$dims = (int) get_option( 'wup_ai_embedding_dimensions', 256 );
			if ( $dims > 0 && $dims < 1536 ) {
				$body['dimensions'] = $dims;
			}

			$response = wp_remote_post( self::ENDPOINT, [
				'timeout' => 30,
				'headers' => [
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				],
				'body' => wp_json_encode( $body ),
			] );

			if ( is_wp_error( $response ) ) {
				throw new RuntimeException( 'WUP: OpenAI request failed: ' . $response->get_error_message() );
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( 200 !== (int) $code ) {
				$msg = $body['error']['message'] ?? 'unknown error';
				throw new RuntimeException( "WUP: OpenAI API error {$code}: {$msg}" );
			}

			// OpenAI returns data[] sorted by 'index' — rebuild indexed by position.
			$vectors = [];
			foreach ( $body['data'] ?? [] as $item ) {
				$vectors[ (int) $item['index'] ] = $item['embedding'];
			}

			ksort( $vectors );

			return array_values( $vectors );
		}
	}
}
