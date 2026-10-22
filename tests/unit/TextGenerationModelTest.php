<?php

declare( strict_types=1 );

namespace Zactonz\AiConnectorForXAi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zactonz\AiConnectorForXAi\Http\XAiRequestAuthentication;
use Zactonz\AiConnectorForXAi\Models\XAiTextGenerationModel;
use Zactonz\AiConnectorForXAi\Provider\XAiProfile;
use Zactonz\AiConnectorForXAi\Provider\XAiProvider;
use Zactonz\AiConnectorForXAi\Settings\XAiSettings;
use Zactonz\AiConnectorForXAi\Tests\Support\FakeHttpTransporter;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

class TextGenerationModelTest extends TestCase {

	private const MODEL_ID = 'zctz-test-model';

	protected function setUp(): void {
		zctz_test_reset_credentials();
		$GLOBALS['zctz_test_options'] = array();
		zctz_test_seed_settings();
	}

	private function completion(): array {
		return array(
			'id'      => 'chatcmpl-1',
			'choices' => array(
				array(
					'message'       => array(
						'role'    => 'assistant',
						'content' => 'Hello from the connector.',
					),
					'finish_reason' => 'stop',
				),
			),
			'usage'   => array(
				'prompt_tokens'     => 11,
				'completion_tokens' => 7,
				'total_tokens'      => 18,
			),
		);
	}

	private function model( FakeHttpTransporter $transporter ): XAiTextGenerationModel {
		$metadata = new ModelMetadata(
			self::MODEL_ID,
			self::MODEL_ID,
			array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ),
			array(
				new SupportedOption( OptionEnum::systemInstruction() ),
				new SupportedOption( OptionEnum::maxTokens() ),
				new SupportedOption( OptionEnum::temperature() ),
				new SupportedOption( OptionEnum::customOptions() ),
				new SupportedOption( OptionEnum::outputMimeType(), array( 'text/plain', 'application/json' ) ),
				new SupportedOption( OptionEnum::outputSchema() ),
			)
		);

		$model = new XAiTextGenerationModel( $metadata, XAiProvider::metadata() );
		$model->setHttpTransporter( $transporter );
		$model->setRequestAuthentication( new XAiRequestAuthentication( 'zctz-test-key' ) );

		return $model;
	}

	private function prompt(): array {
		return array( new Message( MessageRoleEnum::user(), array( new MessagePart( 'Say hello.' ) ) ) );
	}

	public function test_a_completion_is_returned_as_text(): void {
		$transporter = new FakeHttpTransporter( array( FakeHttpTransporter::json( $this->completion() ) ) );
		$result      = $this->model( $transporter )->generateTextResult( $this->prompt() );

		$this->assertSame( 'Hello from the connector.', $result->toText() );
		$this->assertSame( 18, $result->getTokenUsage()->getTotalTokens() );
	}

	public function test_the_request_targets_the_chat_completions_endpoint_with_credentials(): void {
		$transporter = new FakeHttpTransporter( array( FakeHttpTransporter::json( $this->completion() ) ) );
		$this->model( $transporter )->generateTextResult( $this->prompt() );

		$request = $transporter->lastRequest();
		$this->assertNotNull( $request );

		$expected_path = XAiSettings::decorate_path( 'chat/completions', self::MODEL_ID );
		$this->assertStringContainsString( ltrim( explode( '?', $expected_path )[0], '/' ), $request->getUri() );
		$this->assertStringStartsWith( XAiSettings::get_base_url(), $request->getUri() );

		$credential = $request->getHeaderAsString( XAiProfile::auth_header() );
		$this->assertNotNull( $credential );
		$this->assertStringContainsString( 'zctz-test-key', $credential );

		$body = json_decode( (string) $request->getBody(), true );
		$this->assertSame( self::MODEL_ID, $body['model'] );
		$this->assertSame( 'Say hello.', $body['messages'][0]['content'][0]['text'] );
	}

	public function test_transport_only_custom_options_do_not_reach_the_provider(): void {
		$transporter = new FakeHttpTransporter( array( FakeHttpTransporter::json( $this->completion() ) ) );
		$model       = $this->model( $transporter );

		$config = new ModelConfig();
		$config->setCustomOptions( array( XAiProfile::id() . '.request_timeout' => 45 ) );
		$model->setConfig( $config );
		$model->generateTextResult( $this->prompt() );

		$body = json_decode( (string) $transporter->lastRequest()->getBody(), true );
		$this->assertArrayNotHasKey( XAiProfile::id() . '.request_timeout', $body );
	}

	public function test_a_json_schema_becomes_a_structured_response_format(): void {
		$transporter = new FakeHttpTransporter( array( FakeHttpTransporter::json( $this->completion() ) ) );
		$model       = $this->model( $transporter );

		$config = new ModelConfig();
		$config->setOutputMimeType( 'application/json' );
		$config->setOutputSchema(
			array(
				'type'       => 'object',
				'properties' => array( 'title' => array( 'type' => 'string' ) ),
			)
		);
		$model->setConfig( $config );
		$model->generateTextResult( $this->prompt() );

		$body = json_decode( (string) $transporter->lastRequest()->getBody(), true );

		if ( XAiProfile::supports( 'structured_schema' ) ) {
			$this->assertSame( 'json_schema', $body['response_format']['type'] );
			$this->assertSame( 'object', $body['response_format']['json_schema']['schema']['type'] );

			return;
		}

		/*
		 * A provider without json_schema must fall back to JSON mode rather than send a
		 * response_format it rejects. DeepSeek documents json_object only.
		 */
		$this->assertSame( array( 'type' => 'json_object' ), $body['response_format'] );
	}

	public function test_the_reasoning_preference_is_only_applied_to_the_default_text_model(): void {
		if ( ! XAiProfile::supports( 'reasoning' ) ) {
			$this->markTestSkipped( 'This provider does not expose a reasoning control.' );
		}

		zctz_test_seed_settings(
			array(
				'model_text' => self::MODEL_ID,
				'reasoning'  => 'high',
			)
		);

		$transporter = new FakeHttpTransporter( array( FakeHttpTransporter::json( $this->completion() ) ) );
		$this->model( $transporter )->generateTextResult( $this->prompt() );

		$body = json_decode( (string) $transporter->lastRequest()->getBody(), true );
		$this->assertSame( 'high', $body['reasoning_effort'] );

		zctz_test_seed_settings(
			array(
				'model_text' => 'another-model',
				'reasoning'  => 'high',
			)
		);

		$transporter = new FakeHttpTransporter( array( FakeHttpTransporter::json( $this->completion() ) ) );
		$this->model( $transporter )->generateTextResult( $this->prompt() );

		$body = json_decode( (string) $transporter->lastRequest()->getBody(), true );
		$this->assertArrayNotHasKey( 'reasoning_effort', $body );
	}

	public function test_an_error_response_is_surfaced(): void {
		$this->expectException( \WordPress\AiClient\Providers\Http\Exception\ClientException::class );
		$this->expectExceptionMessageMatches( '/Invalid API key/' );

		$transporter = new FakeHttpTransporter(
			array( FakeHttpTransporter::json( array( 'error' => array( 'message' => 'Invalid API key' ) ), 401 ) )
		);
		$this->model( $transporter )->generateTextResult( $this->prompt() );
	}

}
