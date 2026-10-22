<?php
/**
 * Provider registration for xAI Grok with the WordPress AI Client.
 *
 * @package Zactonz\AiConnectorForXAi\Provider
 */

declare( strict_types=1 );

namespace Zactonz\AiConnectorForXAi\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zactonz\AiConnectorForXAi\Metadata\XAiModelMetadataDirectory;
use Zactonz\AiConnectorForXAi\Models\XAiEmbeddingGenerationModel;
use Zactonz\AiConnectorForXAi\Models\XAiImageGenerationModel;
use Zactonz\AiConnectorForXAi\Models\XAiTextGenerationModel;
use Zactonz\AiConnectorForXAi\Settings\XAiSettings;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * Class for the xAI Grok provider.
 *
 * @since 1.0.0
 */
class XAiProvider extends AbstractApiProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function baseUrl(): string {
		return XAiSettings::get_base_url();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param ModelMetadata    $model_metadata Model metadata.
	 * @param ProviderMetadata $provider_metadata Provider metadata.
	 * @return ModelInterface The concrete model instance.
	 * @throws RuntimeException When the model capabilities are unsupported.
	 */
	protected static function createModel(
		ModelMetadata $model_metadata,
		ProviderMetadata $provider_metadata
	): ModelInterface {
		$capabilities = $model_metadata->toArray()[ ModelMetadata::KEY_SUPPORTED_CAPABILITIES ];

		if ( in_array( 'embedding_generation', $capabilities, true ) ) {
			if ( ! interface_exists( 'WordPress\\AiClient\\Providers\\Models\\EmbeddingGeneration\\Contracts\\EmbeddingGenerationModelInterface' ) ) {
				throw new RuntimeException(
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
					'This WordPress release does not provide the AI Client embedding model contract.'
				);
			}

			return new XAiEmbeddingGenerationModel( $model_metadata, $provider_metadata );
		}

		if ( in_array( 'image_generation', $capabilities, true ) ) {
			return new XAiImageGenerationModel( $model_metadata, $provider_metadata );
		}

		if ( in_array( 'text_generation', $capabilities, true ) ) {
			return new XAiTextGenerationModel( $model_metadata, $provider_metadata );
		}

		throw new RuntimeException(
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
			'Unsupported model capabilities: ' . implode( ', ', $capabilities )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		$provider_meta = array(
			XAiProfile::id(),
			XAiProfile::name(),
			ProviderTypeEnum::cloud(),
			XAiProfile::api_key_url(),
			RequestAuthenticationMethod::apiKey(),
		);

		if ( version_compare( AiClient::VERSION, '1.2.0', '>=' ) ) {
			$provider_meta[] = function_exists( '__' )
				? __( 'Text generation, vision, tool calling, and image generation with xAI Grok models.', 'zactonz-ai-connector-for-xai-grok' )
				: 'Text generation, vision, tool calling, and image generation with xAI Grok models.';
		}

		if ( version_compare( AiClient::VERSION, '1.3.0', '>=' ) ) {
			$provider_meta[] = defined( 'ZCTZ_XAI_PLUGIN_DIR' )
				? ZCTZ_XAI_PLUGIN_DIR . 'includes/Provider/logo.svg'
				: dirname( __DIR__, 2 ) . '/includes/Provider/logo.svg';
		}

		return new ProviderMetadata( ...$provider_meta );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		return new XAiProviderAvailability();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new XAiModelMetadataDirectory();
	}
}
