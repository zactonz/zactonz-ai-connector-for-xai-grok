<?php
/**
 * Connection diagnostics.
 *
 * @package Zactonz\AiConnectorForXAi\Diagnostics
 */

declare( strict_types=1 );

namespace Zactonz\AiConnectorForXAi\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zactonz\AiConnectorForXAi\Metadata\XAiModelMetadataDirectory;
use Zactonz\AiConnectorForXAi\Provider\XAiProfile;
use Zactonz\AiConnectorForXAi\Settings\XAiSettings;
use WordPress\AiClient\AiClient;

/**
 * Produces a redacted, side-effect-free report for administrators.
 *
 * @since 1.0.0
 */
class XAiDiagnostics {

	/**
	 * Runs the diagnostics.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Diagnostic report.
	 */
	public function run(): array {
		$endpoint = XAiSettings::get_base_url();
		$url      = XAiSettings::get_models_url();

		if ( ! XAiSettings::has_credentials() ) {
			return $this->report(
				false,
				$endpoint,
				0,
				0,
				array(),
				sprintf(
					/* translators: %s: Provider name. */
					__( 'No %s credentials are stored yet.', 'zactonz-ai-connector-for-xai-grok' ),
					XAiProfile::name()
				)
			);
		}

		$started  = microtime( true );
		$response = wp_remote_get(
			$url,
			array(
				'headers'     => XAiSettings::get_request_headers( 'GET', $url ),
				'redirection' => 2,
				'timeout'     => 20,
			)
		);
		$latency  = (int) round( ( microtime( true ) - $started ) * 1000 );

		if ( is_wp_error( $response ) ) {
			return $this->report( false, $endpoint, 0, $latency, array(), $response->get_error_message() );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			return $this->report(
				false,
				$endpoint,
				$status,
				$latency,
				array(),
				sprintf(
					/* translators: 1: Provider name, 2: HTTP status code. */
					__( '%1$s returned HTTP %2$d.', 'zactonz-ai-connector-for-xai-grok' ),
					XAiProfile::name(),
					$status
				)
			);
		}

		$body    = json_decode( wp_remote_retrieve_body( $response ), true );
		$entries = array();
		if ( is_array( $body ) ) {
			foreach ( array( 'data', 'models', 'modelSummaries' ) as $key ) {
				if ( isset( $body[ $key ] ) && is_array( $body[ $key ] ) ) {
					$entries = $body[ $key ];
					break;
				}
			}
		}

		/*
		 * Count what the connector offers, not what the provider listed. A provider
		 * lists models this connector cannot serve - speech, moderation, rerank -
		 * and reporting the raw total tells an admin a number the model picker
		 * contradicts, and lets a default that was filtered out pass as present.
		 */
		$model_ids = ( new XAiModelMetadataDirectory() )->offered_model_ids( $entries );

		return $this->report( true, $endpoint, $status, $latency, $model_ids, '' );
	}

	/**
	 * Assembles the report.
	 *
	 * @since 1.0.0
	 *
	 * @param bool               $connected Whether the provider answered.
	 * @param string             $endpoint Endpoint that was contacted.
	 * @param int                $status HTTP status code.
	 * @param int                $latency Round trip time in milliseconds.
	 * @param array<int, string> $model_ids Discovered model identifiers.
	 * @param string             $error Error message, when there is one.
	 * @return array<string, mixed> Diagnostic report.
	 */
	private function report( bool $connected, string $endpoint, int $status, int $latency, array $model_ids, string $error ): array {
		$missing_defaults = array();
		foreach ( XAiSettings::get_preferred_models() as $capability => $model_id ) {
			if ( '' !== $model_id && ! in_array( $model_id, $model_ids, true ) ) {
				$missing_defaults[ $capability ] = $model_id;
			}
		}

		return array(
			'connected'       => $connected,
			'provider'        => XAiProfile::name(),
			'endpoint'        => $endpoint,
			'error'           => $error,
			'httpStatus'      => $status,
			'latencyMs'       => $latency,
			'modelCount'      => count( $model_ids ),
			'missingDefaults' => $missing_defaults,
			'aiClientVersion' => class_exists( AiClient::class ) ? AiClient::VERSION : '',
			'streamingReady'  => self::supports_incremental_streaming(),
			'timestamp'       => gmdate( 'c' ),
		);
	}

	/**
	 * Reports whether responses can be delivered token by token.
	 *
	 * Streaming rides on the requests-request.progress action, which WordPress has
	 * bridged from the Requests library since 4.7 and which both bundled transports
	 * dispatch. Without that bridge a stream still returns the whole, correct
	 * answer, it just arrives in one piece.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True when incremental delivery is available.
	 */
	private static function supports_incremental_streaming(): bool {
		return class_exists( 'WP_HTTP_Requests_Hooks' );
	}
}
