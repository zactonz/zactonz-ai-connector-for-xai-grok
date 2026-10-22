<?php

declare( strict_types=1 );

namespace Zactonz\AiConnectorForXAi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zactonz\AiConnectorForXAi\Provider\XAiProfile;
use Zactonz\AiConnectorForXAi\Provider\XAiProvider;
use Zactonz\AiConnectorForXAi\Settings\XAiSettings;

class ProviderTest extends TestCase {

	protected function setUp(): void {
		zctz_test_reset_credentials();
		$GLOBALS['zctz_test_options'] = array();
	}

	public function test_the_provider_reports_its_identity(): void {
		$metadata = XAiProvider::metadata();

		$this->assertSame( XAiProfile::id(), $metadata->getId() );
		$this->assertSame( XAiProfile::name(), $metadata->getName() );
		$this->assertNotSame( '', XAiProfile::api_key_url() );
	}

	public function test_the_bundled_logo_exists(): void {
		$this->assertFileExists( dirname( __DIR__, 2 ) . '/includes/Provider/logo.svg' );
	}

	public function test_urls_are_built_from_the_configured_base_url(): void {
		$this->assertSame( XAiSettings::get_base_url(), XAiProvider::url() );
		$this->assertSame( XAiSettings::get_base_url() . '/models', XAiProvider::url( '/models' ) );
		$this->assertSame( XAiSettings::get_base_url() . '/models', XAiProvider::url( 'models' ) );
	}

	public function test_availability_follows_the_stored_credentials(): void {
		$this->assertFalse( XAiProvider::availability()->isConfigured() );

		zctz_test_seed_settings();

		$this->assertTrue( XAiProvider::availability()->isConfigured() );
	}

	public function test_the_advertised_capabilities_match_the_profile(): void {
		$this->assertTrue( XAiProfile::supports( 'text' ) );

		foreach ( array( 'vision', 'tools', 'structured', 'embedding', 'image', 'reasoning' ) as $capability ) {
			$this->assertIsBool( XAiProfile::supports( $capability ) );
		}
	}
}
