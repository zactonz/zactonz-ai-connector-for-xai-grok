<?php
/**
 * WordPress Site Health integration.
 *
 * @package Zactonz\AiConnectorForXAi\Diagnostics
 */

declare( strict_types=1 );

namespace Zactonz\AiConnectorForXAi\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zactonz\AiConnectorForXAi\Provider\XAiProfile;
use Zactonz\AiConnectorForXAi\Settings\XAiSettings;

/**
 * Registers a status test and redacted debug information.
 *
 * @since 1.0.0
 */
class XAiSiteHealth {

	private const TEST_SLUG = 'zctz-xai-connection';

	/**
	 * Registers hooks.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		add_filter( 'site_status_tests', array( $this, 'register_tests' ) );
		add_filter( 'debug_information', array( $this, 'add_debug_information' ) );
		add_action( 'wp_ajax_health-check-' . self::TEST_SLUG, array( $this, 'ajax_test_connection' ) );
	}

	/**
	 * Adds the connection test.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $tests Existing tests.
	 * @return array<string, mixed> Tests including this connector.
	 */
	public function register_tests( array $tests ): array {
		if ( ! XAiSettings::has_credentials() ) {
			return $tests;
		}

		$tests['async']['zctz_xai_connection'] = array(
			'label'             => sprintf(
				/* translators: %s: Provider name. */
				__( '%s connection', 'zactonz-ai-connector-for-xai-grok' ),
				XAiProfile::name()
			),
			'test'              => self::TEST_SLUG,
			'has_rest'          => false,
			'async_direct_test' => array( $this, 'test_connection' ),
		);

		return $tests;
	}

	/**
	 * Runs the connection test for the Site Health screen.
	 *
	 * The test reaches the provider over the network, so it is registered as
	 * asynchronous and answered here instead of during the page render. A direct
	 * test would hold the Site Health screen open for the length of the request,
	 * up to the request timeout when the provider cannot be reached.
	 *
	 * @since 1.0.0
	 */
	public function ajax_test_connection(): void {
		check_ajax_referer( 'health-check-site-status' );

		if ( ! current_user_can( 'view_site_health_checks' ) ) {
			wp_send_json_error();
		}

		wp_send_json_success( $this->test_connection() );
	}

	/**
	 * Runs the connection test.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Site Health test result.
	 */
	public function test_connection(): array {
		$report    = ( new XAiDiagnostics() )->run();
		$connected = ! empty( $report['connected'] );

		return array(
			'label'       => $connected
				? sprintf(
					/* translators: %s: Provider name. */
					__( '%s is connected', 'zactonz-ai-connector-for-xai-grok' ),
					XAiProfile::name()
				)
				: sprintf(
					/* translators: %s: Provider name. */
					__( '%s is not reachable', 'zactonz-ai-connector-for-xai-grok' ),
					XAiProfile::name()
				),
			'status'      => $connected ? 'good' : 'recommended',
			'badge'       => array(
				'label' => __( 'AI', 'zactonz-ai-connector-for-xai-grok' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html(
				$connected
					? sprintf(
						/* translators: 1: Number of models, 2: Request latency in milliseconds. */
						__( 'The connector discovered %1$d models in %2$d ms.', 'zactonz-ai-connector-for-xai-grok' ),
						(int) $report['modelCount'],
						(int) $report['latencyMs']
					)
					: (string) $report['error']
			) . '</p>',
			'actions'     => sprintf(
				'<p><a href="%1$s">%2$s</a></p>',
				esc_url( admin_url( 'options-general.php?page=zactonz-ai-connector-for-xai-grok' ) ),
				esc_html(
					sprintf(
						/* translators: %s: Provider name. */
						__( 'Review %s settings', 'zactonz-ai-connector-for-xai-grok' ),
						XAiProfile::name()
					)
				)
			),
			'test'        => 'zctz_xai_connection',
		);
	}

	/**
	 * Adds non-secret details to Site Health information.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $info Existing debug information.
	 * @return array<string, mixed> Debug information including this connector.
	 */
	public function add_debug_information( array $info ): array {
		$fields = array(
			'endpoint'    => array(
				'label' => __( 'Endpoint', 'zactonz-ai-connector-for-xai-grok' ),
				'value' => XAiSettings::get_base_url(),
			),
			'credentials' => array(
				'label' => __( 'Credentials stored', 'zactonz-ai-connector-for-xai-grok' ),
				'value' => XAiSettings::has_credentials()
					? __( 'Yes', 'zactonz-ai-connector-for-xai-grok' )
					: __( 'No', 'zactonz-ai-connector-for-xai-grok' ),
			),
			'timeout'     => array(
				'label' => __( 'Text timeout', 'zactonz-ai-connector-for-xai-grok' ),
				'value' => XAiSettings::get_text_request_timeout() . 's',
			),
		);

		foreach ( XAiSettings::get_preferred_models() as $capability => $model_id ) {
			$fields[ 'default_' . $capability ] = array(
				'label' => sprintf(
					/* translators: %s: Capability name. */
					__( 'Default %s model', 'zactonz-ai-connector-for-xai-grok' ),
					$capability
				),
				'value' => '' !== $model_id ? $model_id : __( 'Automatic', 'zactonz-ai-connector-for-xai-grok' ),
			);
		}

		$info['zctz_xai'] = array(
			'label'  => 'Zactonz AI Connector for xAI Grok',
			'fields' => $fields,
		);

		return $info;
	}
}
