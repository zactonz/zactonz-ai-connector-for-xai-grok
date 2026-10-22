<?php

declare( strict_types=1 );

namespace Zactonz\AiConnectorForXAi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zactonz\AiConnectorForXAi\Diagnostics\XAiDiagnostics;
use Zactonz\AiConnectorForXAi\Metadata\XAiModelMetadataDirectory;
use Zactonz\AiConnectorForXAi\Tests\Support\FakeHttpTransporter;
use Zactonz\AiConnectorForXAi\Tests\Support\PassthroughAuthentication;
use Zactonz\AiConnectorForXAi\Diagnostics\XAiSiteHealth;
use Zactonz\AiConnectorForXAi\Settings\XAiSettings;

class DiagnosticsTest extends TestCase {

	protected function setUp(): void {
		zctz_test_reset_credentials();
		$GLOBALS['zctz_test_options']        = array();
		$GLOBALS['zctz_test_http_responses'] = array();
		$GLOBALS['zctz_test_http_requests']  = array();
	}

	public function test_missing_credentials_are_reported_without_a_request(): void {
		$report = ( new XAiDiagnostics() )->run();

		$this->assertFalse( $report['connected'] );
		$this->assertNotSame( '', $report['error'] );
		$this->assertEmpty( $GLOBALS['zctz_test_http_requests'] );
	}

	public function test_a_successful_listing_is_summarized(): void {
		zctz_test_seed_settings();
		zctz_test_queue_http_response( json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/models.json' ), true ), 200 );

		$report = ( new XAiDiagnostics() )->run();

		$this->assertTrue( $report['connected'] );
		$this->assertGreaterThan( 0, $report['modelCount'] );
		$this->assertSame( 200, $report['httpStatus'] );
		$this->assertSame( '', $report['error'] );
	}

	public function test_the_report_never_contains_the_credential(): void {
		zctz_test_seed_settings();
		zctz_test_queue_http_response( json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/models.json' ), true ), 200 );

		$report = ( new XAiDiagnostics() )->run();

		$this->assertStringNotContainsString( 'zctz-test-key', (string) wp_json_encode( $report ) );
	}

	public function test_the_report_counts_the_models_the_connector_offers(): void {
		zctz_test_seed_settings();
		$listing = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/models.json' ), true );

		$directory = new XAiModelMetadataDirectory();
		$directory->setHttpTransporter( new FakeHttpTransporter( array( FakeHttpTransporter::json( $listing ) ) ) );
		$directory->setRequestAuthentication( new PassthroughAuthentication() );
		$offered = count( $directory->listModelMetadata() );

		zctz_test_queue_http_response( $listing, 200 );
		$report = ( new XAiDiagnostics() )->run();

		// A provider lists models this connector cannot serve. Reporting the raw
		// total would contradict the model picker the admin is looking at.
		$this->assertSame( $offered, $report['modelCount'] );
	}

	public function test_a_default_missing_from_the_listing_is_flagged(): void {
		zctz_test_seed_settings();
		$settings                                                           = XAiSettings::get_settings();
		$settings['model_text']                                             = 'model-that-was-removed';
		$GLOBALS['zctz_test_options']['zctz_xai_settings']               = $settings;
		zctz_test_queue_http_response( json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/models.json' ), true ), 200 );

		$report = ( new XAiDiagnostics() )->run();

		$this->assertArrayHasKey( 'text', $report['missingDefaults'] );
		$this->assertSame( 'model-that-was-removed', $report['missingDefaults']['text'] );
	}

	public function test_an_http_failure_is_reported(): void {
		zctz_test_seed_settings();
		zctz_test_queue_http_response( array( 'error' => 'server error' ), 500 );

		$report = ( new XAiDiagnostics() )->run();

		$this->assertFalse( $report['connected'] );
		$this->assertSame( 500, $report['httpStatus'] );
	}

	public function test_site_health_only_registers_when_credentials_exist(): void {
		$health = new XAiSiteHealth();

		$this->assertArrayNotHasKey( 'zctz_xai_connection', $health->register_tests( array() )['async'] ?? array() );

		zctz_test_seed_settings();

		$this->assertArrayHasKey( 'zctz_xai_connection', $health->register_tests( array() )['async'] );
	}

	public function test_site_health_connection_test_is_asynchronous(): void {
		zctz_test_seed_settings();

		$tests = ( new XAiSiteHealth() )->register_tests( array() );

		$this->assertArrayNotHasKey( 'zctz_xai_connection', $tests['direct'] ?? array() );

		$test = $tests['async']['zctz_xai_connection'];

		$this->assertSame( 'zctz-xai-connection', $test['test'] );
		$this->assertFalse( $test['has_rest'] );
		$this->assertIsCallable( $test['async_direct_test'] );
		$this->assertStringNotContainsString( '_', $test['test'] );
	}

	public function test_site_health_debug_information_hides_the_credential(): void {
		zctz_test_seed_settings();

		$info = ( new XAiSiteHealth() )->add_debug_information( array() );

		$this->assertArrayHasKey( 'zctz_xai', $info );
		$this->assertStringNotContainsString( 'zctz-test-key', (string) wp_json_encode( $info ) );
	}
}
