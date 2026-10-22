# Zactonz AI Connector for xAI Grok

[![Documentation](https://img.shields.io/badge/docs-developers.zactonz.com-ff6700)](https://developers.zactonz.com/wordpress/plugins/zactonz-ai-connector-for-xai-grok/)
[![Latest release](https://img.shields.io/github/v/release/zactonz/zactonz-ai-connector-for-xai-grok?include_prereleases&label=release)](https://github.com/zactonz/zactonz-ai-connector-for-xai-grok/releases)
[![License: GPL v2 or later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php)](composer.json)

Adds an xAI Grok connector to Settings > Connectors for the WordPress AI Client, including image generation.

xAI publishes the Grok family for text, vision, and image generation. This connector reads the richer xAI model endpoint, so each Grok model arrives in WordPress with the input and output modalities xAI reports, and image models are wired to the image generation endpoint rather than chat.

WordPress 7.0 introduced the AI Client and a **Settings > Connectors** screen where
every provider's credentials live in one place. Core ships OpenAI, Anthropic and
Google. This plugin adds xAI Grok to that same screen, so anything built on
`wp_ai_client_prompt()` can use it without knowing anything about this plugin.

Version 1.0.0 · Developer: [Zactonz Technologies](https://zactonz.com/)

**Documentation:** [developers.zactonz.com](https://developers.zactonz.com/wordpress/plugins/zactonz-ai-connector-for-xai-grok/) ·
[Releases](https://github.com/zactonz/zactonz-ai-connector-for-xai-grok/releases) ·
[Report an issue](https://github.com/zactonz/zactonz-ai-connector-for-xai-grok/issues)

> xAI Grok is a third-party service. This plugin is developed by Zactonz
> Technologies and is not affiliated with, endorsed by or sponsored by its owner.
> xAI and Grok are trademarks of X.AI Corp.

## Requirements

- WordPress 7.0 or higher
- PHP 7.4 or higher
- An xAI Grok account and API key

## Installation

1. Copy this directory to `wp-content/plugins/zactonz-ai-connector-for-xai-grok/`.
2. Activate **Zactonz AI Connector for xAI Grok** from the Plugins screen.
3. Add the credentials on **Settings > Connectors**, or on **Settings > xAI Grok**.

## Credentials

You can supply the API key in three ways. They are checked in this order, and the
first one found wins:

1. A **PHP constant**, defined before WordPress loads — normally in `wp-config.php`:

   ```php
   define( 'XAI_API_KEY', 'your-api-key' );
   ```

2. An **environment variable** of the same name:

   ```bash
   export XAI_API_KEY="your-api-key"
   ```

3. The **admin field** on **Settings > Connectors**, stored in its own option and
   never rendered back into the page.

When a constant or environment variable is set, the admin field is disabled and
labelled as overridden, so nobody edits a value that cannot take effect. This is
the recommended setup for version-controlled or multi-environment sites: the key
stays out of the database and out of any export of it.

Get a key at [console.x.ai](https://console.x.ai/).

## Usage

Once the connector is configured, use it through the WordPress AI Client by
provider ID. Nothing in your code needs to reference this plugin:

```php
$text = wp_ai_client_prompt( 'Write a short WordPress release note.' )
	->using_provider( 'xai' )
	->generate_text();
```

To pin a specific model rather than the configured default:

```php
use Zactonz\AiConnectorForXAi\Provider\XAiProvider;

$model  = XAiProvider::model( 'your-model-id' );
$result = $model->generateTextResult(
	array( new UserMessage( array( new MessagePart( 'Write a release note.' ) ) ) )
);
```

### Streaming

The AI Client has no provider-neutral streaming contract yet, so the text model
exposes `generateStreamResult()`. Each event has a `type` of `content_delta`,
`thinking_delta`, `tool_call_delta` or `done`. Return `false` from the callback to
stop and receive the partial result.

```php
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\UserMessage;
use Zactonz\AiConnectorForXAi\Provider\XAiProvider;

$model  = XAiProvider::model( 'your-model-id' );
$result = $model->generateStreamResult(
	array( new UserMessage( array( new MessagePart( 'Write a release note.' ) ) ) ),
	static function ( array $event ) {
		if ( 'content_delta' === $event['type'] ) {
			echo esc_html( $event['delta'] );
			flush();
		}
	}
);
```

Streaming is an ordinary `wp_remote_post()`. The body arrives through the
`requests-request.progress` action that WordPress has bridged from the Requests
library since 4.7, so there is no cURL call anywhere in this plugin. Where a host
has no transport that delivers incrementally, the whole response is replayed
through the same parser: every event still arrives, just at once.

## Configuration

| Setting | Purpose |
| --- | --- |
| API key | The credential sent with every request. Stored in its own option and never rendered back. |
| Default models | One default per capability: text, and whichever of vision, images, embeddings and tool calling this provider supports. |
| Reasoning effort | Applied to the default text model when the model reports reasoning support. |
| API base URL | Overrides the default endpoint for a proxy or private gateway. |
| Timeouts | Separate request windows for text, embeddings and images. |

## Model discovery

Models are listed from the xAI API.

Capabilities are read from the provider's own model listing rather than guessed. A
model is advertised as vision, tool-calling, structured-output or embedding capable
only when the provider reports it, which keeps the WordPress model picker honest.

## Diagnostics

**Settings > xAI Grok** has a diagnostics button reporting the endpoint,
HTTP status, latency, number of discovered models, any configured defaults missing
from the current model list, and the AI Client version. Credentials never appear in
the report. The same check is registered as a WordPress Site Health test.

## Development

```bash
composer install
composer lint
```

`composer lint` runs PHP_CodeSniffer against the WordPress standards, PHPStan at
level 6, and the PHPUnit suite.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Security

See [SECURITY.md](SECURITY.md) for how to report a vulnerability.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
