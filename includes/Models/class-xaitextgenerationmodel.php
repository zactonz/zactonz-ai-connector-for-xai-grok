<?php
/**
 * Text-generation model for xAI Grok.
 *
 * @package Zactonz\AiConnectorForXAi\Models
 */

declare( strict_types=1 );

namespace Zactonz\AiConnectorForXAi\Models;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Zactonz\AiConnectorForXAi\Models\Traits\XAiRequestOptionsTrait;
use Zactonz\AiConnectorForXAi\Provider\XAiProfile;
use Zactonz\AiConnectorForXAi\Provider\XAiProvider;
use Zactonz\AiConnectorForXAi\Settings\XAiSettings;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;

/**
 * Generates text with the provider's OpenAI-compatible chat completions API.
 *
 * @since 1.0.0
 */
class XAiTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {
	use XAiRequestOptionsTrait;

	/**
	 * Prepares the structured output parameter.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $output_schema The output schema.
	 * @return array<string, mixed> The response format parameter.
	 */
	protected function prepareResponseFormatParam( ?array $output_schema ): array { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( is_array( $output_schema ) && XAiProfile::supports( 'structured_schema' ) ) {
			return array(
				'type'        => 'json_schema',
				'json_schema' => array(
					'name'   => 'response_schema',
					'strict' => true,
					'schema' => $output_schema,
				),
			);
		}

		return array( 'type' => 'json_object' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * The OpenAI-compatible parser in the AI Client only reads a message whose
	 * `content` is a string. Several providers answer with a list of content
	 * chunks instead once reasoning is involved - xAI Grok documents exactly
	 * that for a raised reasoning effort, and some of the models it hosts never
	 * return anything else - and the chunks were being dropped, which surfaced as
	 * an empty response rather than an error. Flattening the list here keeps the
	 * parent in charge of parts, channels and tool calls.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $message_data The message data from the API response.
	 * @param int                  $index The index of the choice in the choices array.
	 * @return array<int, \WordPress\AiClient\Messages\DTO\MessagePart> The parsed message parts.
	 */
	protected function parseResponseChoiceMessageParts( array $message_data, int $index ): array { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( isset( $message_data['content'] ) && is_array( $message_data['content'] ) ) {
			list( $text, $thinking ) = self::split_content_chunks( $message_data['content'] );

			$message_data['content'] = $text;

			if ( '' !== $thinking && ! isset( $message_data['reasoning_content'] ) ) {
				$message_data['reasoning_content'] = $thinking;
			}
		}

		return parent::parseResponseChoiceMessageParts( $message_data, $index );
	}

	/**
	 * Separates a content value into visible text and reasoning text.
	 *
	 * A string is returned untouched, so a provider that answers the plain
	 * OpenAI shape is unaffected. A list is read as content chunks: a text chunk
	 * contributes to the answer and a thinking chunk to the reasoning trace,
	 * which is where xAI Grok puts a thinking trace rather than in a
	 * `reasoning_content` field. Chunk kinds the AI Client cannot represent, such
	 * as an image or a document reference, are skipped.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $content Content value as the provider sent it.
	 * @return array{0: string, 1: string} Visible text, then reasoning text.
	 */
	protected static function split_content_chunks( $content ): array {
		if ( is_string( $content ) ) {
			return array( $content, '' );
		}

		if ( ! is_array( $content ) ) {
			return array( '', '' );
		}

		$text     = '';
		$thinking = '';

		foreach ( $content as $chunk ) {
			if ( is_string( $chunk ) ) {
				$text .= $chunk;
				continue;
			}

			if ( ! is_array( $chunk ) ) {
				continue;
			}

			$type = isset( $chunk['type'] ) && is_string( $chunk['type'] ) ? $chunk['type'] : '';

			if ( 'thinking' === $type ) {
				$thinking .= self::flatten_thinking_chunk( $chunk );
				continue;
			}

			if ( ( '' === $type || 'text' === $type ) && isset( $chunk['text'] ) && is_string( $chunk['text'] ) ) {
				$text .= $chunk['text'];
			}
		}

		return array( $text, $thinking );
	}

	/**
	 * Reads the text out of one thinking chunk.
	 *
	 * A thinking chunk carries its own list of parts, so the text sits one level
	 * further down than in a text chunk.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $chunk Thinking chunk.
	 * @return string Reasoning text.
	 */
	private static function flatten_thinking_chunk( array $chunk ): string {
		if ( isset( $chunk['thinking'] ) && is_string( $chunk['thinking'] ) ) {
			return $chunk['thinking'];
		}

		if ( ! isset( $chunk['thinking'] ) || ! is_array( $chunk['thinking'] ) ) {
			return '';
		}

		$thinking = '';

		foreach ( $chunk['thinking'] as $part ) {
			if ( is_string( $part ) ) {
				$thinking .= $part;
				continue;
			}

			if ( is_array( $part ) && isset( $part['text'] ) && is_string( $part['text'] ) ) {
				$thinking .= $part['text'];
			}
		}

		return $thinking;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @param HttpMethodEnum                     $method HTTP method.
	 * @param string                             $path Endpoint path.
	 * @param array<string, string|list<string>> $headers Request headers.
	 * @param mixed                              $data Request body data.
	 * @return Request The prepared request.
	 */
	protected function createRequest( // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		HttpMethodEnum $method,
		string $path,
		array $headers = array(),
		$data = null
	): Request {
		$options = $this->prepareRequestOptions( XAiSettings::get_text_request_timeout(), 10.0 );

		if ( is_array( $data ) ) {
			$data = $this->stripTransportOptions( $data );
			$data = $this->applyReasoningPreference( $data );
		}

		return new Request(
			$method,
			XAiProvider::url( XAiSettings::decorate_path( $path, $this->metadata()->getId() ) ),
			$headers,
			$data,
			$options
		);
	}

	/**
	 * Applies the administrator's reasoning preference to a request payload.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data Request payload.
	 * @return array<string, mixed> Payload including the reasoning preference.
	 */
	protected function applyReasoningPreference( array $data ): array { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( ! XAiProfile::supports( 'reasoning' ) ) {
			return $data;
		}

		if ( isset( $data['reasoning_effort'] ) || isset( $data['reasoning'] ) ) {
			return $data;
		}

		$model_id = isset( $data['model'] ) && is_string( $data['model'] ) ? $data['model'] : '';
		$effort   = XAiSettings::get_reasoning_effort_for_model( $model_id );

		if ( in_array( $effort, array( 'low', 'medium', 'high', 'xhigh' ), true ) ) {
			$data['reasoning_effort'] = $effort;
		}

		return $data;
	}

	/**
	 * Streams a text response and returns the aggregated result.
	 *
	 * The callback receives arrays with a type of content_delta, thinking_delta,
	 * tool_call_delta, or done. Returning false stops delivering events and returns
	 * the partial result. The HTTP request itself still runs to completion: nothing
	 * in the HTTP API can abort a response mid-body, and PHP defers an exception
	 * thrown from a transport callback until the transfer has finished anyway.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, Message> $prompt Prompt messages.
	 * @phpstan-param list<Message> $prompt
	 * @param callable            $on_event Receives each normalized stream event.
	 * @return GenerativeAiResult Aggregated result.
	 * @throws RuntimeException When streaming is unavailable or the transfer fails.
	 * @throws ResponseException When the provider returns an unsuccessful response.
	 */
	public function generateStreamResult( array $prompt, callable $on_event ): GenerativeAiResult { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$params           = $this->stripTransportOptions( $this->prepareGenerateTextParams( $prompt ) );
		$params           = $this->applyReasoningPreference( $params );
		$params['stream'] = true;

		/*
		 * An OpenAI-compatible stream omits the usage block unless it is asked for,
		 * so without this the aggregated result reports no tokens at all.
		 */
		if ( ! isset( $params['stream_options'] ) ) {
			$params['stream_options'] = array( 'include_usage' => true );
		}

		$url     = XAiProvider::url( XAiSettings::decorate_path( 'chat/completions', $this->metadata()->getId() ) );
		$headers = array(
			'Content-Type' => 'application/json',
			'Accept'       => 'text/event-stream',
		);
		foreach ( XAiSettings::get_request_headers() as $name => $value ) {
			$headers[ $name ] = $value;
		}

		$state    = array(
			'content'       => '',
			'thinking'      => '',
			'finish_reason' => null,
			'id'            => '',
			'usage'         => array(),
			'tool_calls'    => array(),
			'cancelled'     => false,
			'error'         => null,
		);
		$buffer   = '';
		$raw_body = '';
		$streamed = false;
		$halted   = false;

		$process = function ( string $data ) use ( &$state, $on_event ): bool {
			if ( '[DONE]' === trim( $data ) ) {
				return true;
			}

			$chunk = json_decode( $data, true );
			if ( ! is_array( $chunk ) ) {
				return true;
			}
			if ( isset( $chunk['id'] ) && is_string( $chunk['id'] ) ) {
				$state['id'] = $chunk['id'];
			}
			if ( isset( $chunk['usage'] ) && is_array( $chunk['usage'] ) ) {
				$state['usage'] = $chunk['usage'];
			}

			$choice = isset( $chunk['choices'][0] ) && is_array( $chunk['choices'][0] ) ? $chunk['choices'][0] : array();
			$delta  = isset( $choice['delta'] ) && is_array( $choice['delta'] ) ? $choice['delta'] : array();
			$events = array();

			list( $delta_text, $delta_thinking ) = self::split_content_chunks(
				isset( $delta['content'] ) ? $delta['content'] : null
			);

			if ( '' !== $delta_text ) {
				$state['content'] .= $delta_text;
				$events[]          = array(
					'type'  => 'content_delta',
					'delta' => $delta_text,
					'raw'   => $chunk,
				);
			}

			$thinking = $delta_thinking;
			if ( '' === $thinking ) {
				foreach ( array( 'reasoning_content', 'reasoning' ) as $key ) {
					if ( isset( $delta[ $key ] ) && is_string( $delta[ $key ] ) && '' !== $delta[ $key ] ) {
						$thinking = $delta[ $key ];
						break;
					}
				}
			}
			if ( '' !== $thinking ) {
				$state['thinking'] .= $thinking;
				$events[]           = array(
					'type'  => 'thinking_delta',
					'delta' => $thinking,
					'raw'   => $chunk,
				);
			}

			if ( isset( $delta['tool_calls'] ) && is_array( $delta['tool_calls'] ) ) {
				foreach ( $delta['tool_calls'] as $tool_delta ) {
					if ( ! is_array( $tool_delta ) ) {
						continue;
					}
					$index = isset( $tool_delta['index'] ) ? (int) $tool_delta['index'] : count( $state['tool_calls'] );
					if ( ! isset( $state['tool_calls'][ $index ] ) ) {
						$state['tool_calls'][ $index ] = array(
							'id'       => '',
							'type'     => 'function',
							'function' => array(
								'name'      => '',
								'arguments' => '',
							),
						);
					}
					if ( isset( $tool_delta['id'] ) && is_string( $tool_delta['id'] ) ) {
						$state['tool_calls'][ $index ]['id'] = $tool_delta['id'];
					}
					if ( isset( $tool_delta['function']['name'] ) && is_string( $tool_delta['function']['name'] ) ) {
						$state['tool_calls'][ $index ]['function']['name'] .= $tool_delta['function']['name'];
					}
					if ( isset( $tool_delta['function']['arguments'] ) && is_string( $tool_delta['function']['arguments'] ) ) {
						$state['tool_calls'][ $index ]['function']['arguments'] .= $tool_delta['function']['arguments'];
					}
					$events[] = array(
						'type'  => 'tool_call_delta',
						'index' => $index,
						'delta' => $tool_delta,
						'raw'   => $chunk,
					);
				}
			}

			if ( isset( $choice['finish_reason'] ) && is_string( $choice['finish_reason'] ) ) {
				$state['finish_reason'] = $choice['finish_reason'];
			}

			foreach ( $events as $event ) {
				try {
					if ( false === $on_event( $event ) ) {
						$state['cancelled'] = true;
						return false;
					}
				} catch ( \Throwable $e ) {
					$state['error'] = $e;
					return false;
				}
			}

			return true;
		};

		$consume = function ( string $chunk ) use ( &$buffer, &$raw_body, &$state, $process ): bool {
			$raw_body .= $chunk;
			$buffer   .= str_replace( "\r\n", "\n", $chunk );

			while ( true ) {
				$separator = strpos( $buffer, "\n\n" );
				if ( false === $separator ) {
					break;
				}

				$block  = substr( $buffer, 0, $separator );
				$buffer = substr( $buffer, $separator + 2 );
				$lines  = array();

				foreach ( explode( "\n", $block ) as $line ) {
					if ( 0 === strpos( $line, 'data:' ) ) {
						$lines[] = ltrim( substr( $line, 5 ) );
					}
				}

				if ( ! empty( $lines ) && ! $process( implode( "\n", $lines ) ) ) {
					return false;
				}
			}

			return ! $state['cancelled'] && null === $state['error'];
		};

		/*
		 * WordPress forwards every Requests hook to an action named requests-<hook>,
		 * and both bundled transports dispatch request.progress with each block of
		 * the body as it arrives. So the request stays an ordinary wp_remote_post()
		 * and the chunks come back through a WordPress action, with no transport
		 * code of our own.
		 */
		$progress = function ( $chunk ) use ( $consume, &$streamed, &$halted ): void {
			$streamed = true;

			if ( ! $consume( (string) $chunk ) ) {
				$halted = true;

				throw new XAiStreamCancellation();
			}
		};

		/*
		 * wp_json_encode() returns false on data it cannot encode, malformed UTF-8 in
		 * post content being the realistic case. Passing that to wp_remote_post sends
		 * an empty body and the provider answers with a puzzling error, so say what
		 * actually went wrong instead.
		 */
		$body = wp_json_encode( $params );

		if ( false === $body ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception text is not rendered directly.
			throw new RuntimeException( 'The streaming request could not be encoded: ' . json_last_error_msg() );
		}

		add_action( 'requests-request.progress', $progress, 10, 1 );

		try {
			$response = wp_remote_post(
				$url,
				array(
					'headers'     => $headers,
					'body'        => $body,
					'timeout'     => (int) ceil( XAiSettings::get_text_request_timeout() ),
					'redirection' => 0,
					'httpversion' => '1.1',
				)
			);
		} catch ( XAiStreamCancellation $cancellation ) {
			$response = null;
		} finally {
			remove_action( 'requests-request.progress', $progress, 10 );
		}

		// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception text is not rendered directly.
		if ( null === $response ) {
			$status_code = 200;
		} elseif ( is_wp_error( $response ) ) {
			if ( ! $state['cancelled'] ) {
				throw new RuntimeException( 'The streaming request failed: ' . $response->get_error_message() );
			}

			$status_code = 200;
		} else {
			$status_code = (int) wp_remote_retrieve_response_code( $response );

			if ( ! $streamed ) {
				/*
				 * No cURL transport, so nothing streamed. The response is complete and
				 * correct, it simply arrived in one piece, so replay it through the same
				 * parser and the caller still sees every event.
				 */
				$halted = ! $consume( (string) wp_remote_retrieve_body( $response ) );
			}
		}

		if ( ! $state['cancelled'] && ( $status_code < 200 || $status_code >= 300 ) ) {
			$decoded = json_decode( $raw_body, true );
			$message = is_array( $decoded ) && isset( $decoded['error']['message'] )
				? (string) $decoded['error']['message']
				: trim( $raw_body );

			throw ResponseException::fromInvalidData(
				$this->providerMetadata()->getName(),
				'stream',
				'' !== $message ? $message : 'Unexpected HTTP status ' . $status_code . '.'
			);
		}
		// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped

		/*
		 * A stream can end without the blank line that closes its last block, leaving
		 * one event in the buffer. Parse it, unless the caller has already cancelled
		 * or its callback has already thrown.
		 */
		if ( '' !== trim( $buffer ) && ! $halted ) {
			$trailing = preg_replace( '/^data:\s*/m', '', trim( $buffer ) );
			if ( is_string( $trailing ) ) {
				$process( $trailing );
			}
		}

		/*
		 * Checked once, here, because the caller's callback runs on every path above:
		 * during the stream, during a replayed body, and on the trailing block.
		 */
		if ( $state['error'] instanceof \Throwable ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception text is not rendered directly.
			throw new RuntimeException( 'The stream callback failed: ' . $state['error']->getMessage(), 0, $state['error'] );
		}

		ksort( $state['tool_calls'] );
		$message = array(
			'role'    => 'assistant',
			'content' => $state['content'],
		);
		if ( '' !== $state['thinking'] ) {
			$message['reasoning_content'] = $state['thinking'];
		}
		if ( ! empty( $state['tool_calls'] ) ) {
			$message['tool_calls'] = array_values( $state['tool_calls'] );
		}

		$finish_reason = null !== $state['finish_reason']
			? $state['finish_reason']
			: ( ! empty( $state['tool_calls'] ) ? 'tool_calls' : 'stop' );

		$result = $this->parseResponseToGenerativeAiResult(
			new Response(
				200,
				array( 'Content-Type' => 'application/json' ),
				(string) wp_json_encode(
					array(
						'id'        => $state['id'],
						'choices'   => array(
							array(
								'message'       => $message,
								'finish_reason' => $finish_reason,
							),
						),
						'usage'     => $state['usage'],
						'cancelled' => $state['cancelled'],
					)
				)
			)
		);

		try {
			$on_event(
				array(
					'type'         => 'done',
					'cancelled'    => $state['cancelled'],
					'finishReason' => $finish_reason,
					'usage'        => $state['usage'],
				)
			);
		} catch ( \Throwable $e ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception text is not rendered directly.
			throw new RuntimeException( 'The stream callback failed: ' . $e->getMessage(), 0, $e );
		}

		return $result;
	}
}
