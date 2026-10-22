<?php

declare( strict_types=1 );

namespace Zactonz\AiConnectorForXAi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zactonz\AiConnectorForXAi\Http\XAiRequestAuthentication;
use Zactonz\AiConnectorForXAi\Models\XAiTextGenerationModel;
use Zactonz\AiConnectorForXAi\Provider\XAiProvider;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;

class StreamingTest extends TestCase {

	/** @var resource|null */
	private $process;

	/** @var int */
	private $port = 0;

	protected function setUp(): void {
		zctz_test_reset_credentials();
		if ( ! function_exists( 'curl_init' ) || ! function_exists( 'proc_open' ) ) {
			$this->markTestSkipped( 'cURL and proc_open are required for the streaming integration test.' );
		}

		$socket = stream_socket_server( 'tcp://127.0.0.1:0', $error_code, $error_message );
		$this->assertIsResource( $socket, (string) $error_message );
		$address    = (string) stream_socket_get_name( $socket, false );
		$this->port = (int) substr( $address, strrpos( $address, ':' ) + 1 );
		fclose( $socket );

		$null          = '/dev/null';
		$this->process = proc_open(
			array( PHP_BINARY, '-S', '127.0.0.1:' . $this->port, dirname( __DIR__ ) . '/fixtures/stream-server.php' ),
			array(
				0 => array( 'file', $null, 'r' ),
				1 => array( 'file', $null, 'a' ),
				2 => array( 'file', $null, 'a' ),
			),
			$pipes
		);
		$this->assertIsResource( $this->process );

		$ready = false;
		for ( $attempt = 0; $attempt < 60; $attempt++ ) {
			$connection = @fsockopen( '127.0.0.1', $this->port );
			if ( is_resource( $connection ) ) {
				fclose( $connection );
				$ready = true;
				break;
			}
			usleep( 25000 );
		}
		$this->assertTrue( $ready, 'The streaming fixture server did not start.' );

		$GLOBALS['zctz_test_options'] = array();
		zctz_test_seed_settings(
			array(
				'base_url'        => 'http://127.0.0.1:' . $this->port,
				'request_timeout' => '20',
			)
		);
	}

	protected function tearDown(): void {
		if ( is_resource( $this->process ) ) {
			proc_terminate( $this->process );
			proc_close( $this->process );
			$this->process = null;
		}
	}

	private function model( string $model_id ): XAiTextGenerationModel {
		$metadata = new ModelMetadata(
			$model_id,
			$model_id,
			array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ),
			array()
		);

		$model = new XAiTextGenerationModel( $metadata, XAiProvider::metadata() );
		$model->setRequestAuthentication( new XAiRequestAuthentication( 'zctz-test-key' ) );

		return $model;
	}

	private function prompt(): array {
		return array( new Message( MessageRoleEnum::user(), array( new MessagePart( 'Say hello.' ) ) ) );
	}

	public function test_content_and_reasoning_chunks_are_aggregated(): void {
		$events = array();
		$result = $this->model( 'stream-model' )->generateStreamResult(
			$this->prompt(),
			static function ( array $event ) use ( &$events ): void {
				$events[] = $event;
			}
		);

		$this->assertSame( 'Hello WordPress.', $result->toText() );
		$this->assertSame( 7, $result->getTokenUsage()->getTotalTokens() );

		$types = array_column( $events, 'type' );
		$this->assertContains( 'content_delta', $types );
		$this->assertContains( 'thinking_delta', $types );
		$this->assertSame( 'done', end( $types ) );
	}

	public function test_returning_false_from_the_callback_cancels_the_transfer(): void {
		$deltas = 0;
		$result = $this->model( 'stream-model' )->generateStreamResult(
			$this->prompt(),
			static function ( array $event ) use ( &$deltas ) {
				if ( 'content_delta' !== $event['type'] ) {
					return true;
				}

				$deltas++;

				return false;
			}
		);

		$this->assertSame( 1, $deltas );
		$this->assertSame( 'Hello ', $result->toText() );
	}

	public function test_streamed_tool_calls_are_reassembled(): void {
		$result = $this->model( 'tool-model' )->generateStreamResult( $this->prompt(), static function (): void {} );

		$calls = $result->getCandidates()[0]->getMessage()->getParts();
		$found = null;
		foreach ( $calls as $part ) {
			if ( null !== $part->getFunctionCall() ) {
				$found = $part->getFunctionCall();
				break;
			}
		}

		$this->assertNotNull( $found );
		$this->assertSame( 'lookup', $found->getName() );
		$this->assertSame( array( 'query' => 'WordPress' ), $found->getArgs() );
	}

	public function test_an_error_status_is_surfaced(): void {
		$this->expectException( ResponseException::class );

		$this->model( 'error-model' )->generateStreamResult( $this->prompt(), static function (): void {} );
	}

	public function test_the_trailing_block_is_parsed(): void {
		$result = $this->model( 'trailing-model' )->generateStreamResult(
			$this->prompt(),
			static function (): bool {
				return true;
			}
		);

		$this->assertSame( 'Partial tail.', $result->toText() );
	}

	public function test_a_throwing_callback_in_the_trailing_block_is_reported(): void {
		$this->expectException( RuntimeException::class );

		$this->model( 'trailing-model' )->generateStreamResult(
			$this->prompt(),
			static function ( array $event ): bool {
				if ( 'content_delta' === $event['type'] && 'tail.' === $event['delta'] ) {
					throw new \LogicException( 'callback failed on the last block' );
				}

				return true;
			}
		);
	}

	public function test_a_throwing_callback_is_reported(): void {
		$this->expectException( RuntimeException::class );

		$this->model( 'stream-model' )->generateStreamResult(
			$this->prompt(),
			static function (): void {
				throw new \LogicException( 'callback failed' );
			}
		);
	}

	public function test_the_stream_asks_the_provider_to_include_usage(): void {
		$result = $this->model( 'echo-options-model' )->generateStreamResult(
			$this->prompt(),
			static function ( array $event ): void {
				unset( $event );
			}
		);

		$this->assertSame( '{"include_usage":true}', $result->toText() );
	}

	public function test_a_callback_failure_on_the_done_event_is_reported(): void {
		$this->expectException( \WordPress\AiClient\Common\Exception\RuntimeException::class );
		$this->expectExceptionMessage( 'The stream callback failed' );

		$this->model( 'stream-model' )->generateStreamResult(
			$this->prompt(),
			static function ( array $event ): void {
				if ( 'done' === $event['type'] ) {
					throw new \LogicException( 'boom' );
				}
			}
		);
	}
}
