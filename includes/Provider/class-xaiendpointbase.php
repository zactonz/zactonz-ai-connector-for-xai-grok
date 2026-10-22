<?php
/**
 * Shared endpoint construction for xAI Grok.
 *
 * Connectors whose API is addressed differently from a plain OpenAI-compatible
 * base URL extend this class and override only what differs, so a change here
 * reaches every connector in the family.
 *
 * @package Zactonz\AiConnectorForXAi\Provider
 */

declare( strict_types=1 );

namespace Zactonz\AiConnectorForXAi\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds request URLs and headers for the provider.
 *
 * @since 1.0.0
 */
abstract class XAiEndpointBase {

	/**
	 * Returns the additional connection fields this provider needs.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>> Field definitions.
	 */
	public static function connection_fields(): array {
		return array();
	}

	/**
	 * Returns the API base URL for the stored settings.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $settings Stored settings.
	 * @return string Base URL without a trailing slash.
	 */
	public static function base_url( array $settings ): string {
		$base_url = isset( $settings['base_url'] ) ? trim( (string) $settings['base_url'] ) : '';

		if ( '' === $base_url ) {
			$base_url = XAiProfile::default_base_url();
		}

		return rtrim( $base_url, '/' );
	}

	/**
	 * Adjusts an endpoint path for the provider's URL scheme.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $path Endpoint path relative to the base URL.
	 * @param string               $model_id Model the request targets, when known.
	 * @param array<string, mixed> $settings Stored settings.
	 * @return string Adjusted path.
	 */
	public static function decorate_path( string $path, string $model_id, array $settings ): string {
		unset( $model_id, $settings );

		return $path;
	}

	/**
	 * Returns the absolute URL that lists the available models.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $settings Stored settings.
	 * @return string Absolute URL.
	 */
	public static function models_url( array $settings ): string {
		$base = self::base_url( $settings );
		$path = self::decorate_path( XAiProfile::models_path(), '', $settings );

		return $base . '/' . ltrim( $path, '/' );
	}

	/**
	 * Returns the headers for a direct WordPress HTTP request.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $api_key Stored credential.
	 * @param array<string, mixed> $settings Stored settings.
	 * @param string               $method HTTP method.
	 * @param string               $url Absolute request URL.
	 * @param string               $body Request body.
	 * @return array<string, string> Request headers.
	 */
	public static function request_headers( string $api_key, array $settings, string $method = 'GET', string $url = '', string $body = '' ): array {
		unset( $settings, $method, $url, $body );

		$headers = array_merge( array( 'Accept' => 'application/json' ), XAiProfile::extra_headers() );

		if ( '' === $api_key ) {
			return $headers;
		}

		$headers[ XAiProfile::auth_header() ] = 'header' === XAiProfile::auth_scheme()
			? $api_key
			: 'Bearer ' . $api_key;

		return $headers;
	}

	/**
	 * Checks whether the stored settings are complete enough to connect.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $api_key Stored credential.
	 * @param array<string, mixed> $settings Stored settings.
	 * @return bool True when the connector holds everything it needs.
	 */
	public static function has_credentials( string $api_key, array $settings ): bool {
		return '' !== $api_key && '' !== self::base_url( $settings );
	}
}
