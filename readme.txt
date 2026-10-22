=== Zactonz AI Connector for xAI Grok ===
Contributors:      zactonz
Tags:              connector, grok, xai, ai-client, image-generation
Requires at least: 7.0
Tested up to:      7.1
Stable tag:        1.0.0
Requires PHP:      7.4
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Adds an xAI Grok connector to Settings > Connectors for the WordPress AI Client, including image generation.

== Description ==

This plugin by [Zactonz Technologies](https://zactonz.com/) adds [xAI Grok](https://x.ai/) as an AI provider for the WordPress AI Client. It is built for the WordPress **Settings > Connectors** screen and for the plugin directory connector search, so a site owner who needs xAI Grok can find it, install it, paste a key, and have every AI feature in WordPress start using it.

xAI publishes the Grok family for text, vision, and image generation. This connector reads the richer xAI model endpoint, so each Grok model arrives in WordPress with the input and output modalities xAI reports, and image models are wired to the image generation endpoint rather than chat.

**Disclaimer:** this connector is developed by Zactonz Technologies and is not affiliated with, endorsed by, or sponsored by xAI Grok. xAI and Grok are trademarks of X.AI Corp.

**Features:**

* Text generation with every xAI Grok chat model your credentials can reach
* Automatic model discovery, taking each model's capabilities from the provider's own catalogue wherever it publishes them
* Separate default model per capability, or automatic selection by the WordPress AI Client
* Vision input on models that accept images
* Tool calling on models that report function support
* Structured output with a JSON schema on models that support it
* Image generation on models that produce images
* Reasoning effort control for the default text model
* Cancellable server-sent event streaming for content, reasoning, and tool-call chunks
* Configurable request timeouts for text and images
* API key supplied by a PHP constant or an environment variable instead of the database
* An API base URL override for a proxy or gateway, within the hosts and ports WordPress permits outbound requests to
* Redacted diagnostics and a WordPress Site Health connectivity test

**Requirements:**

* PHP 7.4 or higher
* WordPress 7.0 or higher
* An xAI Grok account and API key

== External services ==

This plugin sends requests to xAI Grok, a third-party service, and does nothing without it. Requests go to the xAI Grok API at `https://api.x.ai/v1`, or to the API base URL you set in the connector settings.

**What is sent and when:** the API key you configure, and the prompts, images, tool definitions and generation settings that a WordPress AI feature passes to the AI Client, each time such a feature runs a request through this connector. The connector also requests the model catalogue when its settings screen loads, when a WordPress AI feature asks which models are available, and when you run diagnostics or the Site Health test. No other data is sent.

**Service provider:** xAI Grok ([terms of service](https://x.ai/legal/terms-of-service), [privacy policy](https://x.ai/legal/privacy-policy)).

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/zactonz-ai-connector-for-xai-grok/`, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings > Connectors** and add your xAI Grok API key.
4. Go to **Settings > xAI Grok** to review the discovered models and choose a default model per capability.

== Frequently Asked Questions ==

= Where do I get an API key? =

Create one on the xAI Grok dashboard: https://console.x.ai/

= Can I keep the key out of the database? =

Yes. Define `XAI_API_KEY` in `wp-config.php`, or set an environment variable of the same name. When either is present the connector uses it, the settings field is disabled, and nothing is written to the database.

= Which models will I see? =

Models are listed from the xAI API. Each model is offered as vision, tool-calling, structured-output or image-generation capable according to what xAI Grok publishes about it, and the connector falls back to the model identifier only where xAI Grok publishes nothing.

= Does this replace the connectors in WordPress core? =

No. WordPress core ships its own connectors. This plugin adds xAI Grok alongside them, and you can keep several connectors active at once and pick a default model per capability.

= Is my key ever displayed or logged? =

No. The key is stored in its own option, is never rendered back into the settings screen, and is excluded from the diagnostics report and from Site Health debug information.

== Changelog ==

= 1.0.0 =

* Initial release.
* Registers xAI Grok with the WordPress AI Client and the Settings > Connectors screen.
* Discovers models from the provider and advertises only the capabilities the provider reports.
* Adds per-capability default model selection, reasoning effort, a configurable timeout, and an API base URL override.
* Streams text responses token by token through the WordPress HTTP API, aggregating content, reasoning, tool calls, and usage.
* Adds a redacted diagnostics report and a WordPress Site Health connectivity test.
* Supports an API key supplied by a PHP constant or an environment variable.

== Upgrade Notice ==

= 1.0.0 =

Initial release.
