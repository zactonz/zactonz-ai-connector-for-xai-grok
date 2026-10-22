<?php
/**
 * Request authentication for xAI Grok.
 *
 * @package Zactonz\AiConnectorForXAi\Http
 */

declare( strict_types=1 );

namespace Zactonz\AiConnectorForXAi\Http;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zactonz\AiConnectorForXAi\Provider\XAiProfile;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;

/**
 * Applies the stored credential to every outgoing request.
 *
 * The AI Client registry only accepts the API key authentication class for a
 * provider that declares the API key method, so this extends that class rather
 * than implementing the interface directly, and changes only how the credential
 * is attached.
 *
 * @since 1.0.0
 */
class XAiRequestAuthentication extends ApiKeyRequestAuthentication {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param Request $request The request to authenticate.
	 * @return Request The authenticated request.
	 */
	public function authenticateRequest( Request $request ): Request { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		foreach ( XAiProfile::extra_headers() as $name => $value ) {
			if ( ! $request->hasHeader( $name ) ) {
				$request = $request->withHeader( $name, $value );
			}
		}

		$api_key = $this->getApiKey();

		if ( '' === $api_key ) {
			return $request;
		}

		if ( 'header' === XAiProfile::auth_scheme() ) {
			return $request->withHeader( XAiProfile::auth_header(), $api_key );
		}

		return $request->withHeader( XAiProfile::auth_header(), 'Bearer ' . $api_key );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data Representation.
	 * @return self New instance.
	 */
	public static function fromArray( array $data ): self { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return new self( isset( $data[ self::KEY_API_KEY ] ) ? (string) $data[ self::KEY_API_KEY ] : '' );
	}
}
