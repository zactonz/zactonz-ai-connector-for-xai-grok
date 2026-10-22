<?php
/**
 * Shared request option preparation.
 *
 * @package Zactonz\AiConnectorForXAi\Models\Traits
 */

declare( strict_types=1 );

namespace Zactonz\AiConnectorForXAi\Models\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zactonz\AiConnectorForXAi\Provider\XAiProfile;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;

/**
 * Trait preparing request options with configurable timeouts.
 *
 * @since 1.0.0
 */
trait XAiRequestOptionsTrait {

	/**
	 * Prepares request options, honoring per-request custom overrides.
	 *
	 * @since 1.0.0
	 *
	 * @param float $default_request_timeout Default request timeout in seconds.
	 * @param float $default_connect_timeout Default connect timeout in seconds.
	 * @return RequestOptions Prepared request options.
	 */
	protected function prepareRequestOptions( // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		float $default_request_timeout,
		float $default_connect_timeout
	): RequestOptions {
		$existing        = $this->getRequestOptions();
		$request_options = null !== $existing
			? RequestOptions::fromArray( $existing->toArray() )
			: new RequestOptions();

		$custom  = $this->getConfig()->getCustomOptions();
		$prefix  = XAiProfile::id() . '.';
		$timeout = $default_request_timeout;
		$connect = $default_connect_timeout;

		if ( isset( $custom[ $prefix . 'request_timeout' ] ) && is_numeric( $custom[ $prefix . 'request_timeout' ] ) ) {
			$timeout = (float) $custom[ $prefix . 'request_timeout' ];
		}
		if ( isset( $custom[ $prefix . 'connect_timeout' ] ) && is_numeric( $custom[ $prefix . 'connect_timeout' ] ) ) {
			$connect = (float) $custom[ $prefix . 'connect_timeout' ];
		}

		$request_options->setTimeout( $timeout );

		if ( null === $request_options->getConnectTimeout() ) {
			$request_options->setConnectTimeout( $connect );
		}

		return $request_options;
	}

	/**
	 * Removes transport-only custom options from an API payload.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data Request payload.
	 * @return array<string, mixed> Payload without transport options.
	 */
	protected function stripTransportOptions( array $data ): array { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$prefix = XAiProfile::id() . '.';

		foreach ( array_keys( $data ) as $key ) {
			if ( is_string( $key ) && 0 === strpos( $key, $prefix ) ) {
				unset( $data[ $key ] );
			}
		}

		return $data;
	}
}
