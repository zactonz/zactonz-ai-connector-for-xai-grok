<?php

declare( strict_types=1 );

namespace Zactonz\AiConnectorForXAi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zactonz\AiConnectorForXAi\Metadata\XAiModelMetadataDirectory;
use Zactonz\AiConnectorForXAi\Provider\XAiProfile;
use Zactonz\AiConnectorForXAi\Tests\Support\FakeHttpTransporter;
use Zactonz\AiConnectorForXAi\Tests\Support\PassthroughAuthentication;

class MetadataDirectoryTest extends TestCase {

	protected function setUp(): void {
		zctz_test_reset_credentials();
		$GLOBALS['zctz_test_options'] = array();
	}

	private function directory( array $payload ): XAiModelMetadataDirectory {
		$directory = new XAiModelMetadataDirectory();
		$directory->setHttpTransporter( new FakeHttpTransporter( array( FakeHttpTransporter::json( $payload ) ) ) );
		$directory->setRequestAuthentication( new PassthroughAuthentication() );

		return $directory;
	}

	private function fixture( string $name ): array {
		$path = dirname( __DIR__ ) . '/fixtures/' . $name;
		$this->assertFileExists( $path );

		$data = json_decode( (string) file_get_contents( $path ), true );
		$this->assertIsArray( $data );

		return $data;
	}

	public function test_discovers_models_from_the_provider_listing(): void {
		$directory = $this->directory( $this->fixture( 'models.json' ) );
		$models    = $directory->listModelMetadata();

		$this->assertNotEmpty( $models, 'The fixture should yield at least one usable model.' );

		$expected = $this->fixture( 'models.expected.json' );
		$actual   = array();
		foreach ( $models as $model ) {
			$actual[ $model->getId() ] = $model->toArray();
		}

		foreach ( $expected as $model_id => $expectation ) {
			if ( ! empty( $expectation['excluded'] ) || $this->needs_embedding_support( $expectation ) ) {
				continue;
			}

			$this->assertArrayHasKey( $model_id, $actual, sprintf( 'Model %s should be discovered.', $model_id ) );
			$this->assertSame(
				$expectation['capabilities'],
				$actual[ $model_id ]['supportedCapabilities'],
				sprintf( 'Model %s should report the expected capabilities.', $model_id )
			);

			$option_names = array_column( $actual[ $model_id ]['supportedOptions'], 'name' );
			foreach ( $expectation['options'] as $option ) {
				$this->assertContains( $option, $option_names, sprintf( 'Model %s should support %s.', $model_id, $option ) );
			}
			foreach ( $expectation['optionsAbsent'] ?? array() as $option ) {
				$this->assertNotContains( $option, $option_names, sprintf( 'Model %s should not support %s.', $model_id, $option ) );
			}
		}

		foreach ( $expected as $model_id => $expectation ) {
			if ( ! empty( $expectation['excluded'] ) || $this->needs_embedding_support( $expectation ) ) {
				continue;
			}

			$descriptor = $directory->getModelDescriptor( $model_id );
			foreach ( $expectation['features'] as $feature => $value ) {
				$this->assertSame(
					$value,
					$descriptor['features'][ $feature ],
					sprintf( 'Model %s should report %s as %s.', $model_id, $feature, $value ? 'true' : 'false' )
				);
			}
			if ( isset( $expectation['contextLength'] ) ) {
				$this->assertSame( $expectation['contextLength'], $descriptor['contextLength'] );
			}
		}
	}

	public function test_excluded_models_are_not_offered(): void {
		$directory = $this->directory( $this->fixture( 'models.json' ) );
		$ids       = array();

		foreach ( $directory->listModelMetadata() as $model ) {
			$ids[] = $model->getId();
		}

		foreach ( $this->fixture( 'models.expected.json' ) as $model_id => $expectation ) {
			if ( ! empty( $expectation['excluded'] ) ) {
				$this->assertNotContains( $model_id, $ids );
			}
		}
	}

	private function needs_embedding_support( array $expectation ): bool {
		return ! empty( $expectation['features']['embedding'] ) && ! zctz_test_supports_embeddings();
	}

	public function test_a_response_without_a_model_list_is_rejected(): void {
		$this->expectException( \WordPress\AiClient\Providers\Http\Exception\ResponseException::class );

		$this->directory( array( 'unexpected' => true ) )->listModelMetadata();
	}

	public function test_the_listing_request_targets_the_configured_endpoint(): void {
		$transporter = new FakeHttpTransporter( array( FakeHttpTransporter::json( $this->fixture( 'models.json' ) ) ) );
		$directory   = new XAiModelMetadataDirectory();
		$directory->setHttpTransporter( $transporter );
		$directory->setRequestAuthentication( new PassthroughAuthentication() );
		$directory->listModelMetadata();

		$request = $transporter->lastRequest();
		$this->assertNotNull( $request );
		$this->assertStringContainsString( XAiProfile::models_path(), $request->getUri() );
		$this->assertSame( 'application/json', $request->getHeaderAsString( 'Accept' ) );
	}

	public function test_the_configured_default_model_is_listed_first(): void {
		$expected = $this->fixture( 'models.expected.json' );
		$text_models = array_keys(
			array_filter(
				$expected,
				static function ( $expectation ): bool {
					return empty( $expectation['excluded'] ) && ! empty( $expectation['features']['text'] );
				}
			)
		);

		if ( count( $text_models ) < 2 ) {
			$this->markTestSkipped( 'This provider fixture has fewer than two text models.' );
		}

		$preferred = end( $text_models );
		$GLOBALS['zctz_test_options']['zctz_xai_settings'] = array( 'model_text' => $preferred );

		$models = $this->directory( $this->fixture( 'models.json' ) )->listModelMetadata();

		$this->assertSame( $preferred, $models[0]->getId() );
	}



}
