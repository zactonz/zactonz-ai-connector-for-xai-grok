<?php
/**
 * Deterministic SSE endpoint used by the streaming integration test.
 */

$request = json_decode( (string) file_get_contents( 'php://input' ), true );
$model   = is_array( $request ) && isset( $request['model'] ) ? (string) $request['model'] : '';

if ( 'error-model' === $model ) {
	http_response_code( 503 );
	header( 'Content-Type: application/json' );
	echo json_encode( array( 'error' => array( 'message' => 'Model is unavailable.' ) ) );
	return;
}

header( 'Content-Type: text/event-stream' );
header( 'Cache-Control: no-cache' );

if ( 'echo-options-model' === $model ) {
	$echo = isset( $request['stream_options'] ) ? $request['stream_options'] : null;
	echo 'data: ', json_encode(
		array(
			'id'      => 'echo',
			'choices' => array( array( 'delta' => array( 'content' => json_encode( $echo ) ) ) ),
		)
	), "\n\n";
	echo 'data: ', json_encode(
		array(
			'id'      => 'echo',
			'choices' => array( array( 'delta' => new stdClass(), 'finish_reason' => 'stop' ) ),
		)
	), "\n\n";
	echo "data: [DONE]\n\n";
	return;
}

if ( 'tool-model' === $model ) {
	$chunks = array(
		array(
			'id'      => 'tool-stream',
			'choices' => array(
				array(
					'delta'         => array(
						'tool_calls' => array(
							array(
								'index'    => 0,
								'id'       => 'call-1',
								'function' => array(
									'name'      => 'lookup',
									'arguments' => '{"query":',
								),
							),
						),
					),
					'finish_reason' => null,
				),
			),
		),
		array(
			'id'      => 'tool-stream',
			'choices' => array(
				array(
					'delta'         => array(
						'tool_calls' => array(
							array(
								'index'    => 0,
								'function' => array( 'arguments' => '"WordPress"}' ),
							),
						),
					),
					'finish_reason' => null,
				),
			),
		),
		array(
			'id'      => 'tool-stream',
			'choices' => array( array( 'delta' => array(), 'finish_reason' => 'tool_calls' ) ),
		),
	);
} else {
	$chunks = array(
		array(
			'id'      => 'stream-test',
			'choices' => array(
				array(
					'delta'         => array(
						'role'              => 'assistant',
						'reasoning_content' => 'Thinking. ',
					),
					'finish_reason' => null,
				),
			),
		),
		array(
			'id'      => 'stream-test',
			'choices' => array( array( 'delta' => array( 'content' => 'Hello ' ), 'finish_reason' => null ) ),
		),
		array(
			'id'      => 'stream-test',
			'choices' => array( array( 'delta' => array( 'content' => 'WordPress.' ), 'finish_reason' => null ) ),
		),
		array(
			'id'      => 'stream-test',
			'choices' => array( array( 'delta' => array(), 'finish_reason' => 'stop' ) ),
			'usage'   => array(
				'prompt_tokens'     => 3,
				'completion_tokens' => 4,
				'total_tokens'      => 7,
			),
		),
	);
}

if ( 'trailing-model' === $model ) {
	// Ends without the blank line that closes an SSE block, so the last event is
	// left in the parser's buffer and only the trailing-block path will see it.
	echo 'data: ' . json_encode(
		array(
			'id'      => 'trailing-stream',
			'choices' => array( array( 'delta' => array( 'content' => 'Partial ' ), 'finish_reason' => null ) ),
		)
	) . "\n\n";
	echo 'data: ' . json_encode(
		array(
			'id'      => 'trailing-stream',
			'choices' => array( array( 'delta' => array( 'content' => 'tail.' ), 'finish_reason' => 'stop' ) ),
		)
	);
	return;
}

foreach ( $chunks as $chunk ) {
	echo 'data: ' . json_encode( $chunk ) . "\n\n";
}

echo "data: [DONE]\n\n";
