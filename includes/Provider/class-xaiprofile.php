<?php
/**
 * Static description of the xAI Grok API.
 *
 * Generated from providers/providers.php. Every value that differs between the
 * connectors in this family is held here, so the rest of the plugin is shared.
 *
 * @package Zactonz\AiConnectorForXAi\Provider
 */

declare( strict_types=1 );

namespace Zactonz\AiConnectorForXAi\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class holding the provider profile.
 *
 * @since 1.0.0
 */
class XAiProfile {

	/**
	 * Returns the raw profile data.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Profile data.
	 */
	public static function data(): array {
		// phpcs:disable PluginCheck.CodeAnalysis.AIProvider.DirectIntegration -- Provider addresses registered with the WordPress AI Client, not a direct integration.
		return array(
			'releases'          => array(
				array(
					'version' => '1.0.0',
					'date'    => '2026-09-17',
					'notice'  => 'Initial release.',
					'changes' => array(
						'initial',
					),
				),
			),
			'name'              => 'xAI Grok',
			'article'           => 'An',
			'class'             => 'XAi',
			'provider_id'       => 'xai',
			'menu_title'        => 'xAI Grok',
			'const_prefix'      => 'ZCTZ_XAI',
			'option_prefix'     => 'zctz_xai',
			'api_key_constant'  => 'XAI_API_KEY',
			'homepage'          => 'https://x.ai/',
			'api_key_url'       => 'https://console.x.ai/',
			'docs_url'          => 'https://docs.x.ai/',
			'default_base_url'  => 'https://api.x.ai/v1',
			'auth'              => 'bearer',
			'auth_header'       => 'Authorization',
			'models_path'       => 'language-models',
			'models_fallback'   => 'models',
			'short_description' => 'Adds an xAI Grok connector to Settings > Connectors for the WordPress AI Client, including image generation.',
			'description'       => 'Text generation, vision, tool calling, and image generation with xAI Grok models.',
			'summary'           => 'xAI publishes the Grok family for text, vision, and image generation. This connector reads the richer xAI model endpoint, so each Grok model arrives in WordPress with the input and output modalities xAI reports, and image models are wired to the image generation endpoint rather than chat.',
			'tags'              => 'connector, grok, xai, ai-client, image-generation',
			'reasoning_pattern' => 'grok-4\\.[5-7]|(?<!non-)reasoning|multi-agent',
			'reasoning_efforts' => array(
				'low',
				'medium',
				'high',
				'xhigh',
			),
			'capabilities'      => array(
				'text'              => true,
				'vision'            => true,
				'tools'             => true,
				'structured'        => true,
				'structured_schema' => true,
				'embedding'         => false,
				'image'             => true,
				'reasoning'         => true,
			),
			'model_notes'       => 'Models are listed from the xAI API.',
			'trademark'         => 'xAI and Grok are trademarks of X.AI Corp.',
			'terms_url'         => 'https://x.ai/legal/terms-of-service',
			'privacy_url'       => 'https://x.ai/legal/privacy-policy',
			'service_note'      => '',
			'logo_color'        => '#111111',
			'logo_text'         => '#FFFFFF',
			'tagline'           => 'Grok text, vision and image generation',
			'asset_color'       => '#EDEFF2',
			'asset_text'        => '#14161A',
		);
		// phpcs:enable PluginCheck.CodeAnalysis.AIProvider.DirectIntegration
	}

	/**
	 * Returns one profile value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Profile key.
	 * @param mixed  $default_value Value returned when the key is absent.
	 * @return mixed Profile value.
	 */
	public static function get( string $key, $default_value = '' ) {
		$data = self::data();

		return array_key_exists( $key, $data ) ? $data[ $key ] : $default_value;
	}

	/**
	 * Returns the AI Client provider ID.
	 *
	 * @since 1.0.0
	 *
	 * @return string Provider ID.
	 */
	public static function id(): string {
		return (string) self::get( 'provider_id' );
	}

	/**
	 * Returns the provider display name.
	 *
	 * @since 1.0.0
	 *
	 * @return string Provider name.
	 */
	public static function name(): string {
		return (string) self::get( 'name' );
	}

	/**
	 * Returns the provider home page.
	 *
	 * @since 1.0.0
	 *
	 * @return string Home page URL.
	 */
	public static function homepage(): string {
		return (string) self::get( 'homepage' );
	}

	/**
	 * Returns the page where an API key is issued.
	 *
	 * @since 1.0.0
	 *
	 * @return string API key URL.
	 */
	public static function api_key_url(): string {
		return (string) self::get( 'api_key_url' );
	}

	/**
	 * Returns the provider documentation URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string Documentation URL.
	 */
	public static function docs_url(): string {
		return (string) self::get( 'docs_url' );
	}

	/**
	 * Returns the default API base URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string Base URL without a trailing slash.
	 */
	public static function default_base_url(): string {
		return rtrim( (string) self::get( 'default_base_url' ), '/' );
	}

	/**
	 * Returns the authentication scheme.
	 *
	 * @since 1.0.0
	 *
	 * @return string One of bearer, header, or sigv4.
	 */
	public static function auth_scheme(): string {
		return (string) self::get( 'auth', 'bearer' );
	}

	/**
	 * Returns the header carrying the credential.
	 *
	 * @since 1.0.0
	 *
	 * @return string Header name.
	 */
	public static function auth_header(): string {
		return (string) self::get( 'auth_header', 'Authorization' );
	}

	/**
	 * Returns the constant and environment variable used to override the API key.
	 *
	 * @since 1.0.0
	 *
	 * @return string Constant name.
	 */
	public static function api_key_constant(): string {
		return (string) self::get( 'api_key_constant' );
	}

	/**
	 * Returns the path of the model listing endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return string Relative path.
	 */
	public static function models_path(): string {
		return (string) self::get( 'models_path', 'models' );
	}

	/**
	 * Returns the fallback model listing path, if the provider has one.
	 *
	 * @since 1.0.0
	 *
	 * @return string Relative path, or an empty string.
	 */
	public static function models_fallback_path(): string {
		return (string) self::get( 'models_fallback', '' );
	}

	/**
	 * Returns the default request timeout in seconds.
	 *
	 * @since 1.0.0
	 *
	 * @return float Timeout in seconds.
	 */
	public static function default_timeout(): float {
		$timeout = self::get( 'default_timeout', 180 );

		return is_numeric( $timeout ) ? (float) $timeout : 180.0;
	}

	/**
	 * Checks whether the provider can offer one capability at all.
	 *
	 * A true value only allows the capability. Whether an individual model
	 * advertises it is decided from the provider's own model listing.
	 *
	 * @since 1.0.0
	 *
	 * @param string $capability Capability key.
	 * @return bool True when the capability is possible for this provider.
	 */
	public static function supports( string $capability ): bool {
		$capabilities = self::get( 'capabilities', array() );

		return is_array( $capabilities ) && ! empty( $capabilities[ $capability ] );
	}

	/**
	 * Returns provider-specific headers sent with every request.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Header map.
	 */
	public static function extra_headers(): array {
		$headers = self::get( 'extra_headers', array() );
		if ( ! is_array( $headers ) || empty( $headers ) ) {
			return array();
		}

		$replacements = array(
			'{site_url}'  => function_exists( 'home_url' ) ? (string) home_url() : '',
			'{site_name}' => function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '',
		);

		$resolved = array();
		foreach ( $headers as $name => $value ) {
			$value = strtr( (string) $value, $replacements );
			if ( '' === $value ) {
				continue;
			}
			$resolved[ (string) $name ] = $value;
		}

		return $resolved;
	}

	/**
	 * Returns the admin note describing where models come from.
	 *
	 * @since 1.0.0
	 *
	 * @return string Admin note.
	 */
	public static function model_notes(): string {
		return (string) self::get( 'model_notes' );
	}
}
