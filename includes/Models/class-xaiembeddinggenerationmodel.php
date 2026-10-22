<?php
/**
 * Embedding-generation model for xAI Grok.
 *
 * @package Zactonz\AiConnectorForXAi\Models
 */

declare( strict_types=1 );

namespace Zactonz\AiConnectorForXAi\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zactonz\AiConnectorForXAi\Models\Traits\XAiRequestOptionsTrait;
use Zactonz\AiConnectorForXAi\Provider\XAiProvider;
use Zactonz\AiConnectorForXAi\Settings\XAiSettings;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\EmbeddingGeneration\Contracts\EmbeddingGenerationModelInterface;
use WordPress\AiClient\Results\DTO\EmbeddingResult;
use WordPress\AiClient\Results\DTO\TokenUsage;

/**
 * Generates single or batched embeddings with the provider's embeddings API.
 *
 * This class is only reachable when the embedding contracts introduced in PHP
 * AI Client 1.4 are present, which keeps the connector load-safe on earlier
 * WordPress releases.
 *
 * @since 1.0.0
 */
class XAiEmbeddingGenerationModel extends AbstractApiBasedModel implements EmbeddingGenerationModelInterface {
	use XAiRequestOptionsTrait;

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, MessagePart> $inputs Text inputs to embed.
	 * @return EmbeddingResult The embedding result.
	 * @throws InvalidArgumentException When the inputs or vectors are invalid.
	 * @throws ResponseException When the provider returns invalid response data.
	 */
	public function generateEmbeddingResult( array $inputs ): EmbeddingResult { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$texts = array_map( array( $this, 'extract_input_text' ), $inputs );
		if ( empty( $texts ) ) {
			throw new InvalidArgumentException( 'At least one text input is required for embedding generation.' );
		}

		$data = array(
			'model' => $this->metadata()->getId(),
			'input' => $texts,
		);

		$dimensions = $this->requested_dimensions();
		if ( null !== $dimensions ) {
			$data['dimensions'] = $dimensions;
		}

		foreach ( $this->getConfig()->getCustomOptions() as $key => $value ) {
			if ( is_string( $key ) && ! isset( $data[ $key ] ) ) {
				$data[ $key ] = $value;
			}
		}
		$data = $this->stripTransportOptions( $data );

		$request  = new Request(
			HttpMethodEnum::POST(),
			XAiProvider::url( XAiSettings::decorate_path( 'embeddings', $this->metadata()->getId() ) ),
			array( 'Content-Type' => 'application/json' ),
			$data,
			$this->prepareRequestOptions( XAiSettings::get_embedding_request_timeout(), 10.0 )
		);
		$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
		$response = $this->getHttpTransporter()->send( $request );
		ResponseUtil::throwIfNotSuccessful( $response );

		$response_data = $response->getData();
		if ( ! is_array( $response_data ) || ! isset( $response_data['data'] ) || ! is_array( $response_data['data'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Provider exception data is not output directly.
			throw ResponseException::fromMissingData( $this->providerMetadata()->getName(), 'data' );
		}

		$rows = array_values( $response_data['data'] );
		usort(
			$rows,
			static function ( $first, $second ): int {
				$first_index  = is_array( $first ) && isset( $first['index'] ) ? (int) $first['index'] : 0;
				$second_index = is_array( $second ) && isset( $second['index'] ) ? (int) $second['index'] : 0;

				return $first_index <=> $second_index;
			}
		);

		if ( count( $rows ) !== count( $texts ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Provider exception data is not output directly.
			throw ResponseException::fromInvalidData(
				$this->providerMetadata()->getName(),
				'data',
				'Expected one embedding vector for each input.'
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$embeddings = array();
		$dimensions = 0;
		foreach ( $rows as $index => $row ) {
			$vector = is_array( $row ) && isset( $row['embedding'] ) && is_array( $row['embedding'] ) ? $row['embedding'] : null;
			if ( null === $vector || empty( $vector ) ) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Provider exception data is not output directly.
				throw ResponseException::fromInvalidData(
					$this->providerMetadata()->getName(),
					'data[' . $index . '].embedding',
					'The value must be a non-empty list of numbers.'
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			$vector = array_map(
				static function ( $value ): float {
					if ( ! is_int( $value ) && ! is_float( $value ) ) {
						throw new InvalidArgumentException( 'Embedding vector values must be numeric.' );
					}

					return (float) $value;
				},
				array_values( $vector )
			);

			if ( 0 === $dimensions ) {
				$dimensions = count( $vector );
			} elseif ( count( $vector ) !== $dimensions ) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Provider exception data is not output directly.
				throw ResponseException::fromInvalidData(
					$this->providerMetadata()->getName(),
					'data[' . $index . '].embedding',
					'All embedding vectors must have the same dimensions.'
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			$embeddings[] = $vector;
		}

		$prompt_tokens = isset( $response_data['usage']['prompt_tokens'] ) && is_numeric( $response_data['usage']['prompt_tokens'] )
			? (int) $response_data['usage']['prompt_tokens']
			: 0;
		$total_tokens  = isset( $response_data['usage']['total_tokens'] ) && is_numeric( $response_data['usage']['total_tokens'] )
			? (int) $response_data['usage']['total_tokens']
			: $prompt_tokens;

		$additional_data = $response_data;
		unset( $additional_data['data'] );

		return new EmbeddingResult(
			isset( $response_data['id'] ) && is_string( $response_data['id'] ) ? $response_data['id'] : '',
			$embeddings,
			$dimensions,
			new TokenUsage( $prompt_tokens, 0, $total_tokens ),
			$this->providerMetadata(),
			$this->metadata(),
			$additional_data
		);
	}

	/**
	 * Returns the requested embedding dimensions, if the AI Client exposes them.
	 *
	 * The dimensions option arrived with the embedding API, so it is read from
	 * the configuration data rather than through a getter that older builds of
	 * the client do not have.
	 *
	 * @since 1.0.0
	 *
	 * @return int|null Requested dimensions.
	 */
	private function requested_dimensions(): ?int {
		$config = $this->getConfig()->toArray();

		return isset( $config['dimensions'] ) && is_numeric( $config['dimensions'] ) ? (int) $config['dimensions'] : null;
	}

	/**
	 * Extracts one text embedding input.
	 *
	 * @since 1.0.0
	 *
	 * @param MessagePart $input Input message part.
	 * @return string Non-empty input text.
	 * @throws InvalidArgumentException When the input is not non-empty text.
	 */
	private function extract_input_text( MessagePart $input ): string {
		$text = $input->getText();
		if ( null === $text || '' === trim( $text ) ) {
			throw new InvalidArgumentException( 'Embeddings support non-empty text inputs only.' );
		}

		return $text;
	}
}
