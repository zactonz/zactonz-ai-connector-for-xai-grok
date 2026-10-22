<?php
/**
 * Availability check for the xAI Grok connector.
 *
 * @package Zactonz\AiConnectorForXAi\Provider
 */

declare( strict_types=1 );

namespace Zactonz\AiConnectorForXAi\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zactonz\AiConnectorForXAi\Settings\XAiSettings;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;

/**
 * Reports whether the connector holds usable credentials.
 *
 * @since 1.0.0
 */
class XAiProviderAvailability implements ProviderAvailabilityInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function isConfigured(): bool {
		return XAiSettings::has_credentials();
	}
}
