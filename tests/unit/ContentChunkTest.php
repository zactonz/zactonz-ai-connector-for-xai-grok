<?php

declare( strict_types=1 );

namespace Zactonz\AiConnectorForXAi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zactonz\AiConnectorForXAi\Http\XAiRequestAuthentication;
use Zactonz\AiConnectorForXAi\Models\XAiTextGenerationModel;
use Zactonz\AiConnectorForXAi\Provider\XAiProvider;
use Zactonz\AiConnectorForXAi\Tests\Support\FakeHttpTransporter;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessagePartChannelEnum;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;

/**
 * A provider may answer with a list of content chunks instead of a string once
 * reasoning is involved, and some hosted models never answer any other way.
 * The AI Client's own parser only reads a string, so the chunks used to be
 * dropped and the caller saw an empty response rather than an error.
 */
class ContentChunkTest extends TestCase {

	private const MODEL_ID = 'zctz-test-model';

	protected function setUp(): void {
		zctz_test_reset_credentials();
		$GLOBALS['zctz_test_options'] = array();
		zctz_test_seed_settings();
	}

	private function model( FakeHttpTransporter $transporter ): XAiTextGenerationModel {
		$metadata = new ModelMetadata(
			self::MODEL_ID,
			self::MODEL_ID,
			array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ),
			array()
		);

		$model = new XAiTextGenerationModel( $metadata, XAiProvider::metadata() );
		$model->setHttpTransporter( $transporter );
		$model->setRequestAuthentication( new XAiRequestAuthentication( 'zctz-test-key' ) );

		return $model;
	}

	private function prompt(): array {
		return array( new Message( MessageRoleEnum::user(), array( new MessagePart( 'Say hello.' ) ) ) );
	}

	/**
	 * @param mixed $content Content value as a provider would send it.
	 */
	private function completion( $content ): array {
		return array(
			'id'      => 'chatcmpl-chunks',
			'choices' => array(
				array(
					'message'       => array(
						'role'    => 'assistant',
						'content' => $content,
					),
					'finish_reason' => 'stop',
				),
			),
			'usage'   => array(
				'prompt_tokens'     => 9,
				'completion_tokens' => 5,
				'total_tokens'      => 14,
			),
		);
	}

	public function test_a_text_chunk_list_is_read_as_the_answer(): void {
		$transporter = new FakeHttpTransporter(
			array(
				FakeHttpTransporter::json(
					$this->completion(
						array(
							array(
								'type'     => 'thinking',
								'thinking' => array( array( 'type' => 'text', 'text' => 'Let me think. ' ) ),
							),
							array( 'type' => 'text', 'text' => 'Hello from the connector.' ),
						)
					)
				),
			)
		);

		$result = $this->model( $transporter )->generateTextResult( $this->prompt() );

		$this->assertSame( 'Hello from the connector.', $result->toText() );
		$this->assertSame( 14, $result->getTokenUsage()->getTotalTokens() );
	}

	public function test_a_thinking_chunk_becomes_a_thought_part(): void {
		$transporter = new FakeHttpTransporter(
			array(
				FakeHttpTransporter::json(
					$this->completion(
						array(
							array(
								'type'     => 'thinking',
								'thinking' => array(
									array( 'type' => 'text', 'text' => 'First. ' ),
									array( 'type' => 'text', 'text' => 'Then this.' ),
								),
							),
							array( 'type' => 'text', 'text' => 'Answer.' ),
						)
					)
				),
			)
		);

		$result = $this->model( $transporter )->generateTextResult( $this->prompt() );
		$parts  = $result->getCandidates()[0]->getMessage()->getParts();

		$thoughts = array();
		$answers  = array();
		foreach ( $parts as $part ) {
			if ( MessagePartChannelEnum::thought()->value === $part->getChannel()->value ) {
				$thoughts[] = $part->getText();
				continue;
			}
			$answers[] = $part->getText();
		}

		$this->assertSame( array( 'First. Then this.' ), $thoughts );
		$this->assertSame( array( 'Answer.' ), $answers );
	}

	public function test_several_text_chunks_are_joined_in_order(): void {
		$transporter = new FakeHttpTransporter(
			array(
				FakeHttpTransporter::json(
					$this->completion(
						array(
							array( 'type' => 'text', 'text' => 'One ' ),
							array( 'type' => 'text', 'text' => 'two ' ),
							array( 'type' => 'text', 'text' => 'three.' ),
						)
					)
				),
			)
		);

		$result = $this->model( $transporter )->generateTextResult( $this->prompt() );

		$this->assertSame( 'One two three.', $result->toText() );
	}

	public function test_chunk_kinds_the_ai_client_cannot_represent_are_skipped(): void {
		$transporter = new FakeHttpTransporter(
			array(
				FakeHttpTransporter::json(
					$this->completion(
						array(
							array( 'type' => 'image_url', 'image_url' => 'https://example.com/a.png' ),
							array( 'type' => 'reference', 'reference_ids' => array( 1, 2 ) ),
							array( 'type' => 'text', 'text' => 'Only this survives.' ),
						)
					)
				),
			)
		);

		$result = $this->model( $transporter )->generateTextResult( $this->prompt() );

		$this->assertSame( 'Only this survives.', $result->toText() );
	}

	public function test_a_plain_string_answer_is_untouched(): void {
		$transporter = new FakeHttpTransporter(
			array( FakeHttpTransporter::json( $this->completion( 'Hello from the connector.' ) ) )
		);

		$result = $this->model( $transporter )->generateTextResult( $this->prompt() );

		$this->assertSame( 'Hello from the connector.', $result->toText() );
	}

	public function test_a_reasoning_content_string_still_wins_over_a_thinking_chunk(): void {
		$completion = $this->completion( array( array( 'type' => 'text', 'text' => 'Answer.' ) ) );
		$completion['choices'][0]['message']['reasoning_content'] = 'Reported separately.';

		$transporter = new FakeHttpTransporter( array( FakeHttpTransporter::json( $completion ) ) );

		$result = $this->model( $transporter )->generateTextResult( $this->prompt() );
		$parts  = $result->getCandidates()[0]->getMessage()->getParts();

		$thoughts = array();
		foreach ( $parts as $part ) {
			if ( MessagePartChannelEnum::thought()->value === $part->getChannel()->value ) {
				$thoughts[] = $part->getText();
			}
		}

		$this->assertSame( array( 'Reported separately.' ), $thoughts );
		$this->assertSame( 'Answer.', $result->toText() );
	}
}
