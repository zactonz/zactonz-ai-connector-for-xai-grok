<?php
/**
 * WordPress admin settings for the xAI Grok connector.
 *
 * @package Zactonz\AiConnectorForXAi\Settings
 */

declare( strict_types=1 );

namespace Zactonz\AiConnectorForXAi\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Error;
use Zactonz\AiConnectorForXAi\Diagnostics\XAiDiagnostics;
use Zactonz\AiConnectorForXAi\Http\XAiRequestAuthentication;
use Zactonz\AiConnectorForXAi\Provider\XAiEndpoint;
use Zactonz\AiConnectorForXAi\Provider\XAiProfile;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

/**
 * Settings screen and stored configuration for the connector.
 *
 * @since 1.0.0
 */
class XAiSettings {

	private const OPTION_GROUP      = 'zctz_xai_settings';
	private const OPTION_NAME       = 'zctz_xai_settings';
	private const SECRET_PREFIX     = 'zctz_xai_secret_';
	private const PAGE_SLUG         = 'zactonz-ai-connector-for-xai-grok';
	private const SECTION_MAIN      = 'zctz_xai_main';
	private const SECTION_MODELS    = 'zctz_xai_models';
	private const SECTION_ADVANCED  = 'zctz_xai_advanced';
	private const AJAX_MODELS       = 'zctz_xai_list_models';
	private const AJAX_DIAGNOSTICS  = 'zctz_xai_diagnostics';
	private const AJAX_CONNECTION   = 'zctz_xai_save_connection';
	private const NONCE_ACTION      = 'zctz_xai_nonce';
	private const REASONING_EFFORTS = array( 'low', 'medium', 'high', 'xhigh' );
	private const REASONING_MODES   = array( 'default', 'low', 'medium', 'high', 'xhigh' );

	/**
	 * Returns the capability keys that get a default-model selector.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Capability keys mapped to labels.
	 */
	private static function model_capabilities(): array {
		$capabilities = array( 'text' => __( 'Default text model', 'zactonz-ai-connector-for-xai-grok' ) );

		if ( XAiProfile::supports( 'vision' ) ) {
			$capabilities['vision'] = __( 'Default vision model', 'zactonz-ai-connector-for-xai-grok' );
		}
		if ( XAiProfile::supports( 'image' ) ) {
			$capabilities['image'] = __( 'Default image model', 'zactonz-ai-connector-for-xai-grok' );
		}
		if ( XAiProfile::supports( 'embedding' ) ) {
			$capabilities['embedding'] = __( 'Default embedding model', 'zactonz-ai-connector-for-xai-grok' );
		}
		if ( XAiProfile::supports( 'tools' ) ) {
			$capabilities['tools'] = __( 'Default tool model', 'zactonz-ai-connector-for-xai-grok' );
		}

		return $capabilities;
	}

	/**
	 * Registers hooks.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_settings_screen' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_connector_assets' ) );
		add_action( 'wp_ajax_' . self::AJAX_MODELS, array( $this, 'ajax_list_models' ) );
		add_action( 'wp_ajax_' . self::AJAX_DIAGNOSTICS, array( $this, 'ajax_diagnostics' ) );
		add_action( 'wp_ajax_' . self::AJAX_CONNECTION, array( $this, 'ajax_save_connection' ) );
	}

	/**
	 * Registers the setting, its sections, and its fields.
	 *
	 * @since 1.0.0
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			self::SECTION_MAIN,
			__( 'Connection', 'zactonz-ai-connector-for-xai-grok' ),
			'__return_empty_string',
			self::PAGE_SLUG
		);
		add_settings_section(
			self::SECTION_MODELS,
			__( 'Models', 'zactonz-ai-connector-for-xai-grok' ),
			'__return_empty_string',
			self::PAGE_SLUG
		);
		add_settings_section(
			self::SECTION_ADVANCED,
			__( 'Advanced', 'zactonz-ai-connector-for-xai-grok' ),
			'__return_empty_string',
			self::PAGE_SLUG
		);

		add_settings_field(
			self::OPTION_NAME . '_api_key',
			__( 'API key', 'zactonz-ai-connector-for-xai-grok' ),
			array( $this, 'render_api_key_field' ),
			self::PAGE_SLUG,
			self::SECTION_MAIN,
			array( 'label_for' => self::OPTION_NAME . '-api-key' )
		);

		foreach ( XAiEndpoint::connection_fields() as $field ) {
			add_settings_field(
				self::OPTION_NAME . '_' . $field['key'],
				$field['label'],
				array( $this, 'render_connection_field' ),
				self::PAGE_SLUG,
				self::SECTION_MAIN,
				array(
					'field'     => $field,
					'label_for' => self::OPTION_NAME . '-' . str_replace( '_', '-', (string) $field['key'] ),
				)
			);
		}

		add_settings_field(
			self::OPTION_NAME . '_diagnostics',
			__( 'Diagnostics', 'zactonz-ai-connector-for-xai-grok' ),
			array( $this, 'render_diagnostics_field' ),
			self::PAGE_SLUG,
			self::SECTION_MAIN
		);

		foreach ( self::model_capabilities() as $capability => $label ) {
			add_settings_field(
				self::OPTION_NAME . '_model_' . $capability,
				$label,
				array( $this, 'render_model_field' ),
				self::PAGE_SLUG,
				self::SECTION_MODELS,
				array(
					'capability' => $capability,
					'label_for'  => self::OPTION_NAME . '-model-' . $capability,
				)
			);
		}

		if ( XAiProfile::supports( 'reasoning' ) ) {
			add_settings_field(
				self::OPTION_NAME . '_reasoning',
				__( 'Reasoning effort', 'zactonz-ai-connector-for-xai-grok' ),
				array( $this, 'render_reasoning_field' ),
				self::PAGE_SLUG,
				self::SECTION_MODELS,
				array( 'label_for' => self::OPTION_NAME . '-reasoning' )
			);
		}

		add_settings_field(
			self::OPTION_NAME . '_available_models',
			__( 'Available models', 'zactonz-ai-connector-for-xai-grok' ),
			array( $this, 'render_available_models_field' ),
			self::PAGE_SLUG,
			self::SECTION_MODELS
		);

		add_settings_field(
			self::OPTION_NAME . '_base_url',
			__( 'API base URL', 'zactonz-ai-connector-for-xai-grok' ),
			array( $this, 'render_base_url_field' ),
			self::PAGE_SLUG,
			self::SECTION_ADVANCED,
			array( 'label_for' => self::OPTION_NAME . '-base-url' )
		);

		add_settings_field(
			self::OPTION_NAME . '_request_timeout',
			__( 'Text request timeout', 'zactonz-ai-connector-for-xai-grok' ),
			array( $this, 'render_timeout_field' ),
			self::PAGE_SLUG,
			self::SECTION_ADVANCED,
			array(
				'key'       => 'request_timeout',
				'default'   => (string) (int) XAiProfile::default_timeout(),
				'minimum'   => 15,
				'label_for' => self::OPTION_NAME . '-request-timeout',
				'help'      => __( 'Seconds to wait for a text response. Reasoning models may need longer.', 'zactonz-ai-connector-for-xai-grok' ),
			)
		);

		if ( XAiProfile::supports( 'embedding' ) ) {
			add_settings_field(
				self::OPTION_NAME . '_embedding_request_timeout',
				__( 'Embedding request timeout', 'zactonz-ai-connector-for-xai-grok' ),
				array( $this, 'render_timeout_field' ),
				self::PAGE_SLUG,
				self::SECTION_ADVANCED,
				array(
					'key'       => 'embedding_request_timeout',
					'default'   => '60',
					'minimum'   => 5,
					'label_for' => self::OPTION_NAME . '-embedding-request-timeout',
					'help'      => __( 'Used for single and batch embedding requests.', 'zactonz-ai-connector-for-xai-grok' ),
				)
			);
		}

		if ( XAiProfile::supports( 'image' ) ) {
			add_settings_field(
				self::OPTION_NAME . '_image_request_timeout',
				__( 'Image request timeout', 'zactonz-ai-connector-for-xai-grok' ),
				array( $this, 'render_timeout_field' ),
				self::PAGE_SLUG,
				self::SECTION_ADVANCED,
				array(
					'key'       => 'image_request_timeout',
					'default'   => '180',
					'minimum'   => 15,
					'label_for' => self::OPTION_NAME . '-image-request-timeout',
					'help'      => __( 'Seconds to wait for generated images.', 'zactonz-ai-connector-for-xai-grok' ),
				)
			);
		}
	}

	/**
	 * Registers the settings screen.
	 *
	 * @since 1.0.0
	 */
	public function register_settings_screen(): void {
		add_options_page(
			/* translators: %s: Provider name. */
			sprintf( __( '%s Settings', 'zactonz-ai-connector-for-xai-grok' ), XAiProfile::name() ),
			XAiProfile::name(),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_screen' )
		);
	}

	/**
	 * Sanitizes the submitted settings.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Submitted value.
	 * @return array<string, mixed> Sanitized settings.
	 */
	public function sanitize_settings( $value ): array {
		if ( ! is_array( $value ) ) {
			return self::get_settings();
		}

		$existing  = self::get_settings();
		$sanitized = array();

		$base_url = isset( $value['base_url'] ) ? trim( (string) $value['base_url'] ) : '';
		if ( '' !== $base_url ) {
			if ( ! preg_match( '#^https?://#i', $base_url ) ) {
				$base_url = 'https://' . $base_url;
			}
			$base_url = rtrim( esc_url_raw( $base_url ), '/' );
		}
		$sanitized['base_url'] = $base_url;

		foreach ( XAiEndpoint::connection_fields() as $field ) {
			$key = (string) $field['key'];

			if ( ! empty( $field['secret'] ) ) {
				$this->save_secret( $key, $value );
				continue;
			}

			$submitted = isset( $value[ $key ] )
				? sanitize_text_field( (string) $value[ $key ] )
				: ( isset( $existing[ $key ] ) ? sanitize_text_field( (string) $existing[ $key ] ) : '' );

			if ( '' === $submitted && isset( $field['default'] ) ) {
				$submitted = (string) $field['default'];
			}

			if ( ! empty( $field['options'] ) && is_array( $field['options'] ) && ! isset( $field['options'][ $submitted ] ) ) {
				$submitted = isset( $field['default'] ) ? (string) $field['default'] : '';
			}

			$sanitized[ $key ] = $submitted;
		}

		foreach ( array_keys( self::model_capabilities() ) as $capability ) {
			$key               = 'model_' . $capability;
			$sanitized[ $key ] = isset( $value[ $key ] )
				? sanitize_text_field( (string) $value[ $key ] )
				: ( isset( $existing[ $key ] ) ? sanitize_text_field( (string) $existing[ $key ] ) : '' );
		}

		$reasoning              = isset( $value['reasoning'] )
			? sanitize_key( (string) $value['reasoning'] )
			: ( isset( $existing['reasoning'] ) ? sanitize_key( (string) $existing['reasoning'] ) : 'default' );
		$sanitized['reasoning'] = in_array( $reasoning, self::REASONING_MODES, true ) ? $reasoning : 'default';

		$timeouts = array(
			'request_timeout'           => array( 15, 1800 ),
			'embedding_request_timeout' => array( 5, 1800 ),
			'image_request_timeout'     => array( 15, 1800 ),
		);
		foreach ( $timeouts as $key => $bounds ) {
			$submitted = isset( $value[ $key ] )
				? trim( (string) $value[ $key ] )
				: ( isset( $existing[ $key ] ) ? trim( (string) $existing[ $key ] ) : '' );

			if ( '' !== $submitted ) {
				$seconds = absint( $submitted );
				if ( $seconds < $bounds[0] || $seconds > $bounds[1] ) {
					add_settings_error(
						self::OPTION_NAME,
						'zctz_xai_invalid_' . $key,
						sprintf(
							/* translators: 1: Lowest allowed value, 2: Highest allowed value. */
							__( 'Use a timeout between %1$d and %2$d seconds.', 'zactonz-ai-connector-for-xai-grok' ),
							$bounds[0],
							$bounds[1]
						)
					);
					$submitted = '';
				} else {
					$submitted = (string) $seconds;
				}
			}

			$sanitized[ $key ] = $submitted;
		}

		$stored = $this->save_api_key( $value );

		/*
		 * The credential itself is read back from the AI Client, which only has it
		 * once WordPress has handed it over. This records that a key was stored, so
		 * the screen can still say so, and still offer to remove it, on a request
		 * where the credential is not readable.
		 */
		if ( null === $stored ) {
			$sanitized['has_api_key'] = ! empty( $existing['has_api_key'] );
		} else {
			$sanitized['has_api_key'] = $stored;
		}

		$this->set_request_authentication();
		$this->invalidate_model_cache();

		return $sanitized;
	}

	/**
	 * Renders the settings screen.
	 *
	 * @since 1.0.0
	 */
	public function render_screen(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>

		<div class="wrap zctz-xai-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: 1: Opening link tag to the Connectors screen, 2: Closing link tag, 3: Provider name. */
					esc_html__( 'The API key can also be managed on the %1$sSettings > Connectors%2$s screen. This screen adds the %3$s options WordPress does not ask for.', 'zactonz-ai-connector-for-xai-grok' ),
					'<a href="' . esc_url( admin_url( 'options-connectors.php' ) ) . '">',
					'</a>',
					esc_html( XAiProfile::name() )
				);
				?>
			</p>
			<p>
				<?php
				printf(
					/* translators: 1: Opening link tag to the provider API key page, 2: Closing link tag. */
					esc_html__( 'Create an API key on the %1$sprovider dashboard%2$s.', 'zactonz-ai-connector-for-xai-grok' ),
					'<a href="' . esc_url( XAiProfile::api_key_url() ) . '" target="_blank" rel="noopener noreferrer">',
					'</a>'
				);
				?>
			</p>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>

		<?php
	}

	/**
	 * Renders the API key field.
	 *
	 * @since 1.0.0
	 */
	public function render_api_key_field(): void {
		$override = self::get_api_key_override();
		$has_key  = self::has_stored_api_key();
		?>

		<input
			type="password"
			id="<?php echo esc_attr( self::OPTION_NAME . '-api-key' ); ?>"
			name="<?php echo esc_attr( self::OPTION_NAME . '[api_key]' ); ?>"
			value=""
			class="regular-text"
			autocomplete="off"
			spellcheck="false"
			placeholder="<?php echo esc_attr( $has_key ? __( 'A key is saved and hidden', 'zactonz-ai-connector-for-xai-grok' ) : __( 'Enter an API key', 'zactonz-ai-connector-for-xai-grok' ) ); ?>"
			<?php disabled( '' !== $override ); ?>
		/>
		<?php if ( $has_key && '' === $override ) : ?>
			<p>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME . '[clear_api_key]' ); ?>" value="1" />
					<?php echo esc_html__( 'Remove the saved API key', 'zactonz-ai-connector-for-xai-grok' ); ?>
				</label>
			</p>
		<?php endif; ?>
		<p class="description">
			<?php if ( '' !== $override ) : ?>
				<?php
				printf(
					/* translators: 1: Opening code tag, 2: Constant name, 3: Closing code tag. */
					esc_html__( 'The key is supplied by the %1$s%2$s%3$s constant or environment variable and cannot be edited here.', 'zactonz-ai-connector-for-xai-grok' ),
					'<code>',
					esc_html( XAiProfile::api_key_constant() ),
					'</code>'
				);
				?>
			<?php else : ?>
				<?php
				printf(
					/* translators: 1: Opening code tag, 2: Constant name, 3: Closing code tag. */
					esc_html__( 'Leave blank to keep the saved key. Defining %1$s%2$s%3$s in wp-config.php overrides this field.', 'zactonz-ai-connector-for-xai-grok' ),
					'<code>',
					esc_html( XAiProfile::api_key_constant() ),
					'</code>'
				);
				?>
			<?php endif; ?>
		</p>

		<?php
	}

	/**
	 * Renders one provider-specific connection field.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args Field arguments.
	 */
	public function render_connection_field( array $args ): void {
		$field    = isset( $args['field'] ) && is_array( $args['field'] ) ? $args['field'] : array();
		$key      = isset( $field['key'] ) ? (string) $field['key'] : '';
		$settings = self::get_settings();
		$id       = self::OPTION_NAME . '-' . str_replace( '_', '-', $key );
		$name     = self::OPTION_NAME . '[' . $key . ']';

		if ( '' === $key ) {
			return;
		}

		if ( ! empty( $field['secret'] ) ) {
			$has_secret = '' !== self::get_secret( $key );
			?>
			<input
				type="password"
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( $name ); ?>"
				value=""
				class="regular-text"
				autocomplete="off"
				spellcheck="false"
				placeholder="<?php echo esc_attr( $has_secret ? __( 'A value is saved and hidden', 'zactonz-ai-connector-for-xai-grok' ) : (string) ( $field['placeholder'] ?? '' ) ); ?>"
			/>
			<?php if ( $has_secret ) : ?>
				<p>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME . '[clear_' . $key . ']' ); ?>" value="1" />
						<?php echo esc_html__( 'Remove the saved value', 'zactonz-ai-connector-for-xai-grok' ); ?>
					</label>
				</p>
			<?php endif; ?>
			<?php
		} elseif ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
			$value = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : (string) ( $field['default'] ?? '' );
			?>
			<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
				<?php foreach ( $field['options'] as $option_value => $option_label ) : ?>
					<option value="<?php echo esc_attr( (string) $option_value ); ?>" <?php selected( (string) $option_value, $value ); ?>>
						<?php echo esc_html( (string) $option_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php
		} else {
			$value = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : (string) ( $field['default'] ?? '' );
			?>
			<input
				type="text"
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				class="regular-text"
				spellcheck="false"
				placeholder="<?php echo esc_attr( (string) ( $field['placeholder'] ?? '' ) ); ?>"
			/>
			<?php
		}

		if ( ! empty( $field['description'] ) ) {
			echo '<p class="description">' . esc_html( (string) $field['description'] ) . '</p>';
		}
	}

	/**
	 * Renders the API base URL field.
	 *
	 * @since 1.0.0
	 */
	public function render_base_url_field(): void {
		$settings = self::get_settings();
		$value    = isset( $settings['base_url'] ) ? (string) $settings['base_url'] : '';
		?>

		<input
			type="url"
			id="<?php echo esc_attr( self::OPTION_NAME . '-base-url' ); ?>"
			name="<?php echo esc_attr( self::OPTION_NAME . '[base_url]' ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text code"
			spellcheck="false"
			placeholder="<?php echo esc_attr( XAiProfile::default_base_url() ); ?>"
		/>
		<p class="description">
			<?php echo esc_html__( 'Leave blank for the default endpoint. Change this only for a proxy or a private gateway.', 'zactonz-ai-connector-for-xai-grok' ); ?>
		</p>

		<?php
	}

	/**
	 * Renders one default-model selector.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args Field arguments.
	 */
	public function render_model_field( array $args ): void {
		$capability = isset( $args['capability'] ) ? (string) $args['capability'] : 'text';
		$value      = self::get_preferred_model( $capability );
		?>

		<select
			id="<?php echo esc_attr( self::OPTION_NAME . '-model-' . $capability ); ?>"
			name="<?php echo esc_attr( self::OPTION_NAME . '[model_' . $capability . ']' ); ?>"
			class="zctz-xai-model-select"
			data-capability="<?php echo esc_attr( $capability ); ?>"
			data-selected="<?php echo esc_attr( $value ); ?>"
		>
			<option value=""><?php echo esc_html__( 'Automatic', 'zactonz-ai-connector-for-xai-grok' ); ?></option>
			<?php if ( '' !== $value ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" selected><?php echo esc_html( $value ); ?></option>
			<?php endif; ?>
		</select>
		<p class="description">
			<?php echo esc_html__( 'Only models that report this capability are offered. Automatic lets the WordPress AI Client choose.', 'zactonz-ai-connector-for-xai-grok' ); ?>
		</p>

		<?php
	}

	/**
	 * Renders the reasoning effort selector.
	 *
	 * @since 1.0.0
	 */
	public function render_reasoning_field(): void {
		$value  = self::get_reasoning_effort();
		$names  = array(
			'none'    => __( 'No reasoning', 'zactonz-ai-connector-for-xai-grok' ),
			'minimal' => __( 'Minimal', 'zactonz-ai-connector-for-xai-grok' ),
			'low'     => __( 'Low', 'zactonz-ai-connector-for-xai-grok' ),
			'medium'  => __( 'Medium', 'zactonz-ai-connector-for-xai-grok' ),
			'high'    => __( 'High', 'zactonz-ai-connector-for-xai-grok' ),
			'xhigh'   => __( 'Extra high', 'zactonz-ai-connector-for-xai-grok' ),
		);
		$labels = array( 'default' => __( 'Model default', 'zactonz-ai-connector-for-xai-grok' ) );

		foreach ( self::REASONING_EFFORTS as $effort ) {
			if ( isset( $names[ $effort ] ) ) {
				$labels[ $effort ] = $names[ $effort ];
			}
		}
		?>

		<select
			id="<?php echo esc_attr( self::OPTION_NAME . '-reasoning' ); ?>"
			name="<?php echo esc_attr( self::OPTION_NAME . '[reasoning]' ); ?>"
		>
			<?php foreach ( $labels as $mode => $label ) : ?>
				<option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $mode, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php echo esc_html__( 'Sent with requests to the default text model, and left off every other request. Which levels a model accepts is up to the provider, so a model that does not reason, or that does not accept the level chosen here, may ignore or refuse it.', 'zactonz-ai-connector-for-xai-grok' ); ?>
		</p>
		<p class="description" id="<?php echo esc_attr( self::OPTION_NAME . '-reasoning-support' ); ?>"></p>

		<?php
	}

	/**
	 * Renders one timeout field.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args Field arguments.
	 */
	public function render_timeout_field( array $args ): void {
		$key      = isset( $args['key'] ) ? (string) $args['key'] : 'request_timeout';
		$settings = self::get_settings();
		$value    = isset( $settings[ $key ] ) && '' !== $settings[ $key ]
			? (string) $settings[ $key ]
			: (string) ( $args['default'] ?? '180' );
		?>

		<input
			type="number"
			id="<?php echo esc_attr( self::OPTION_NAME . '-' . str_replace( '_', '-', $key ) ); ?>"
			name="<?php echo esc_attr( self::OPTION_NAME . '[' . $key . ']' ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="small-text"
			min="<?php echo esc_attr( (string) ( $args['minimum'] ?? 15 ) ); ?>"
			max="1800"
			step="1"
		/>
		<span><?php echo esc_html__( 'seconds', 'zactonz-ai-connector-for-xai-grok' ); ?></span>
		<?php if ( ! empty( $args['help'] ) ) : ?>
			<p class="description"><?php echo esc_html( (string) $args['help'] ); ?></p>
		<?php endif; ?>

		<?php
	}

	/**
	 * Renders the diagnostics panel.
	 *
	 * @since 1.0.0
	 */
	public function render_diagnostics_field(): void {
		?>

		<button type="button" class="button" id="zctz-xai-run-diagnostics">
			<?php echo esc_html__( 'Run diagnostics', 'zactonz-ai-connector-for-xai-grok' ); ?>
		</button>
		<span class="spinner" id="zctz-xai-diagnostics-spinner"></span>
		<div id="zctz-xai-diagnostics-results" aria-live="polite"></div>
		<p class="description">
			<?php echo esc_html__( 'Checks the endpoint, latency, discovered models, and the chosen defaults. Credentials are never displayed.', 'zactonz-ai-connector-for-xai-grok' ); ?>
		</p>

		<?php
	}

	/**
	 * Renders the available models panel.
	 *
	 * @since 1.0.0
	 */
	public function render_available_models_field(): void {
		?>

		<div id="zctz-xai-models-container">
			<span id="zctz-xai-model-status"></span>
		</div>
		<p class="description"><?php echo esc_html( XAiProfile::model_notes() ); ?></p>

		<?php
	}

	/**
	 * Enqueues the assets for the connector settings screen.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_settings_assets( string $hook_suffix ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		$plugin_dir = ZCTZ_XAI_PLUGIN_DIR;
		$script     = $plugin_dir . 'admin/settings.js';
		$style      = $plugin_dir . 'admin/style-settings.css';

		wp_enqueue_script(
			'zactonz-ai-connector-for-xai-grok-settings',
			plugins_url( 'admin/settings.js', ZCTZ_XAI_PLUGIN_FILE ),
			array(),
			file_exists( $script ) ? (string) filemtime( $script ) : ZCTZ_XAI_VERSION,
			true
		);
		wp_enqueue_style(
			'zactonz-ai-connector-for-xai-grok-settings',
			plugins_url( 'admin/style-settings.css', ZCTZ_XAI_PLUGIN_FILE ),
			array(),
			file_exists( $style ) ? (string) filemtime( $style ) : ZCTZ_XAI_VERSION
		);

		wp_localize_script(
			'zactonz-ai-connector-for-xai-grok-settings',
			'zctzXAiSettings',
			array(
				'modelsUrl'      => esc_url_raw(
					add_query_arg(
						array(
							'action'   => self::AJAX_MODELS,
							'_wpnonce' => wp_create_nonce( self::NONCE_ACTION ),
						),
						admin_url( 'admin-ajax.php' )
					)
				),
				'diagnosticsUrl' => esc_url_raw(
					add_query_arg(
						array(
							'action'   => self::AJAX_DIAGNOSTICS,
							'_wpnonce' => wp_create_nonce( self::NONCE_ACTION ),
						),
						admin_url( 'admin-ajax.php' )
					)
				),
				'providerName'   => XAiProfile::name(),
				'strings'        => array(
					'loadingModels'     => __( 'Loading models...', 'zactonz-ai-connector-for-xai-grok' ),
					'noModels'          => __( 'No models were returned for this API key.', 'zactonz-ai-connector-for-xai-grok' ),
					'modelsFailed'      => __( 'Could not load models.', 'zactonz-ai-connector-for-xai-grok' ),
					'automatic'         => __( 'Automatic', 'zactonz-ai-connector-for-xai-grok' ),
					'running'           => __( 'Running diagnostics...', 'zactonz-ai-connector-for-xai-grok' ),
					'connected'         => __( 'Connected', 'zactonz-ai-connector-for-xai-grok' ),
					'notConnected'      => __( 'Not connected', 'zactonz-ai-connector-for-xai-grok' ),
					'endpoint'          => __( 'Endpoint', 'zactonz-ai-connector-for-xai-grok' ),
					'latency'           => __( 'Latency', 'zactonz-ai-connector-for-xai-grok' ),
					'modelCount'        => __( 'Models discovered', 'zactonz-ai-connector-for-xai-grok' ),
					'missingDefaults'   => __( 'Defaults not found in the model list', 'zactonz-ai-connector-for-xai-grok' ),
					'aiClient'          => __( 'AI Client version', 'zactonz-ai-connector-for-xai-grok' ),
					'reasoningYes'      => __( 'This default model reports reasoning support.', 'zactonz-ai-connector-for-xai-grok' ),
					'reasoningNo'       => __( 'This default model does not report reasoning support.', 'zactonz-ai-connector-for-xai-grok' ),
					'reasoningPick'     => __( 'Choose a default text model to see whether it supports reasoning.', 'zactonz-ai-connector-for-xai-grok' ),
					'context'           => __( 'context', 'zactonz-ai-connector-for-xai-grok' ),
					'featureText'       => __( 'Text', 'zactonz-ai-connector-for-xai-grok' ),
					'featureVision'     => __( 'Vision', 'zactonz-ai-connector-for-xai-grok' ),
					'featureTools'      => __( 'Tools', 'zactonz-ai-connector-for-xai-grok' ),
					'featureStructured' => __( 'JSON', 'zactonz-ai-connector-for-xai-grok' ),
					'featureReasoning'  => __( 'Reasoning', 'zactonz-ai-connector-for-xai-grok' ),
					'featureEmbedding'  => __( 'Embeddings', 'zactonz-ai-connector-for-xai-grok' ),
					'featureImage'      => __( 'Images', 'zactonz-ai-connector-for-xai-grok' ),
				),
			)
		);
	}

	/**
	 * Enqueues the controls layered into the WordPress Connectors screen.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_connector_assets( string $hook_suffix ): void {
		if ( 'options-connectors.php' !== $hook_suffix ) {
			return;
		}

		$plugin_dir = ZCTZ_XAI_PLUGIN_DIR;
		$script     = $plugin_dir . 'admin/connector-settings.js';
		$style      = $plugin_dir . 'admin/style-connector-settings.css';
		$settings   = self::get_settings();
		$fields     = array();

		foreach ( XAiEndpoint::connection_fields() as $field ) {
			$fields[] = array(
				'key'         => (string) $field['key'],
				'label'       => (string) $field['label'],
				'type'        => ! empty( $field['secret'] ) ? 'password' : ( ! empty( $field['options'] ) ? 'select' : 'text' ),
				'options'     => ! empty( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array(),
				'value'       => ! empty( $field['secret'] ) ? '' : (string) ( $settings[ (string) $field['key'] ] ?? ( $field['default'] ?? '' ) ),
				'hasValue'    => ! empty( $field['secret'] ) && '' !== self::get_secret( (string) $field['key'] ),
				'placeholder' => (string) ( $field['placeholder'] ?? '' ),
				'description' => (string) ( $field['description'] ?? '' ),
			);
		}

		wp_enqueue_script(
			'zactonz-ai-connector-for-xai-grok-connector',
			plugins_url( 'admin/connector-settings.js', ZCTZ_XAI_PLUGIN_FILE ),
			array(),
			file_exists( $script ) ? (string) filemtime( $script ) : ZCTZ_XAI_VERSION,
			true
		);
		wp_enqueue_style(
			'zactonz-ai-connector-for-xai-grok-connector',
			plugins_url( 'admin/style-connector-settings.css', ZCTZ_XAI_PLUGIN_FILE ),
			array(),
			file_exists( $style ) ? (string) filemtime( $style ) : ZCTZ_XAI_VERSION
		);

		wp_localize_script(
			'zactonz-ai-connector-for-xai-grok-connector',
			'zctzXAiConnector',
			array(
				'ajaxUrl'      => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
				'action'       => self::AJAX_CONNECTION,
				'nonce'        => wp_create_nonce( self::NONCE_ACTION ),
				'providerId'   => XAiProfile::id(),
				'providerName' => XAiProfile::name(),
				'settingsUrl'  => esc_url_raw( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ),
				'fields'       => $fields,
				'strings'      => array(
					'heading'      => sprintf(
						/* translators: %s: Provider name. */
						__( '%s connection', 'zactonz-ai-connector-for-xai-grok' ),
						XAiProfile::name()
					),
					'save'         => __( 'Save and check connection', 'zactonz-ai-connector-for-xai-grok' ),
					'saving'       => __( 'Checking...', 'zactonz-ai-connector-for-xai-grok' ),
					'saved'        => __( 'Connection settings saved.', 'zactonz-ai-connector-for-xai-grok' ),
					'unexpected'   => __( 'WordPress returned an unexpected response.', 'zactonz-ai-connector-for-xai-grok' ),
					'moreSettings' => __( 'All connector settings', 'zactonz-ai-connector-for-xai-grok' ),
					'savedValue'   => __( 'A value is saved and hidden', 'zactonz-ai-connector-for-xai-grok' ),
					'apiKey'       => __( 'API key', 'zactonz-ai-connector-for-xai-grok' ),
				),
			)
		);
	}

	/**
	 * Lists the discovered models for the settings screen.
	 *
	 * @since 1.0.0
	 */
	public function ajax_list_models(): void {
		check_ajax_referer( self::NONCE_ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'zactonz-ai-connector-for-xai-grok' ), 403 );
		}

		$models = $this->get_models();
		if ( is_wp_error( $models ) ) {
			wp_send_json_error( $models->get_error_message(), 500 );
		}

		$registry  = AiClient::defaultRegistry();
		$provider  = $registry->getProviderClassName( XAiProfile::id() );
		$directory = $provider::modelMetadataDirectory();
		$payload   = array();

		foreach ( $models as $model ) {
			$model_data = method_exists( $model, 'toArray' ) ? $model->toArray() : array();
			if ( ! isset( $model_data['id'] ) || ! is_string( $model_data['id'] ) ) {
				continue;
			}
			if ( method_exists( $directory, 'getModelDescriptor' ) ) {
				$model_data = array_merge( $model_data, $directory->getModelDescriptor( $model_data['id'] ) );
			}
			$payload[] = $model_data;
		}

		wp_send_json_success( $payload );
	}

	/**
	 * Runs redacted diagnostics for an administrator.
	 *
	 * @since 1.0.0
	 */
	public function ajax_diagnostics(): void {
		check_ajax_referer( self::NONCE_ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'zactonz-ai-connector-for-xai-grok' ), 403 );
		}

		wp_send_json_success( ( new XAiDiagnostics() )->run() );
	}

	/**
	 * Saves connection settings submitted from the Connectors screen.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_connection(): void {
		check_ajax_referer( self::NONCE_ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'zactonz-ai-connector-for-xai-grok' ), 403 );
		}

		$submitted = self::get_settings();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified by check_ajax_referer above.
		if ( isset( $_POST['api_key'] ) ) {
			$submitted['api_key'] = sanitize_text_field( wp_unslash( $_POST['api_key'] ) );
		}
		if ( isset( $_POST['clear_api_key'] ) ) {
			$submitted['clear_api_key'] = sanitize_text_field( wp_unslash( $_POST['clear_api_key'] ) );
		}
		foreach ( XAiEndpoint::connection_fields() as $field ) {
			$key = (string) $field['key'];
			if ( isset( $_POST[ $key ] ) ) {
				$submitted[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			}
			if ( isset( $_POST[ 'clear_' . $key ] ) ) {
				$submitted[ 'clear_' . $key ] = sanitize_text_field( wp_unslash( $_POST[ 'clear_' . $key ] ) );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		update_option( self::OPTION_NAME, $this->sanitize_settings( $submitted ), false );

		$verification = self::verify_connection();
		if ( is_wp_error( $verification ) ) {
			wp_send_json_success(
				array(
					'connected' => false,
					'message'   => $verification->get_error_message(),
				)
			);
		}

		wp_send_json_success(
			array(
				'connected' => true,
				'message'   => sprintf(
					/* translators: %s: Provider name. */
					__( '%s accepted these credentials.', 'zactonz-ai-connector-for-xai-grok' ),
					XAiProfile::name()
				),
			)
		);
	}

	/**
	 * Lists the models the AI Client can see for this provider.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_Error|list<\WordPress\AiClient\Providers\Models\DTO\ModelMetadata> Models or an error.
	 */
	public function get_models() {
		if ( ! class_exists( AiClient::class ) ) {
			return new WP_Error( 'ai_client_not_found', __( 'The WordPress AI Client is not available.', 'zactonz-ai-connector-for-xai-grok' ) );
		}

		$registry = AiClient::defaultRegistry();
		if ( ! $registry->hasProvider( XAiProfile::id() ) ) {
			return new WP_Error( 'ai_provider_not_found', __( 'The connector is not registered with the AI Client.', 'zactonz-ai-connector-for-xai-grok' ) );
		}

		if ( ! self::has_credentials() ) {
			return new WP_Error(
				'missing_credentials',
				sprintf(
					/* translators: %s: Provider name. */
					__( 'Add the %s credentials before listing models.', 'zactonz-ai-connector-for-xai-grok' ),
					XAiProfile::name()
				)
			);
		}

		$this->set_request_authentication();
		$provider_classname = $registry->getProviderClassName( XAiProfile::id() );

		try {
			return $provider_classname::modelMetadataDirectory()->listModelMetadata();
		} catch ( \Throwable $e ) {
			return new WP_Error(
				'could_not_list_models',
				sprintf(
					/* translators: 1: Provider name, 2: Error message. */
					__( 'Could not list %1$s models. Error: %2$s', 'zactonz-ai-connector-for-xai-grok' ),
					XAiProfile::name(),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Applies the credential to the AI Client registry.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $api_key Key to apply, or null for the key in use.
	 */
	public function set_request_authentication( ?string $api_key = null ): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$registry = AiClient::defaultRegistry();
		if ( ! $registry->hasProvider( XAiProfile::id() ) ) {
			return;
		}

		$registry->setProviderRequestAuthentication(
			XAiProfile::id(),
			new XAiRequestAuthentication( $api_key ?? self::get_api_key() )
		);
	}

	/**
	 * Clears the cached model list.
	 *
	 * @since 1.0.0
	 */
	public function invalidate_model_cache(): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$registry = AiClient::defaultRegistry();
		if ( ! $registry->hasProvider( XAiProfile::id() ) ) {
			return;
		}

		$directory = $registry->getProviderClassName( XAiProfile::id() )::modelMetadataDirectory();
		if ( method_exists( $directory, 'invalidateCaches' ) ) {
			$directory->invalidateCaches();
		}
	}

	/**
	 * Saves or clears the API key.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $value Submitted settings values.
	 */
	private function save_api_key( array $value ): ?bool {
		if ( ! empty( $value['clear_api_key'] ) ) {
			update_option( self::api_key_option(), '' );
			$this->set_request_authentication( self::get_api_key_override() );
			return false;
		}

		if ( ! isset( $value['api_key'] ) ) {
			return null;
		}

		$api_key = sanitize_text_field( (string) $value['api_key'] );
		if ( '' === $api_key ) {
			return null;
		}

		update_option( self::api_key_option(), $api_key );
		$override = self::get_api_key_override();
		$this->set_request_authentication( '' !== $override ? $override : $api_key );

		return true;
	}

	/**
	 * Saves or clears one secret connection field.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $key Field key.
	 * @param array<string, mixed> $value Submitted settings values.
	 */
	private function save_secret( string $key, array $value ): void {
		if ( ! empty( $value[ 'clear_' . $key ] ) ) {
			update_option( self::SECRET_PREFIX . $key, '', false );
			return;
		}

		if ( ! isset( $value[ $key ] ) ) {
			return;
		}

		$secret = sanitize_text_field( (string) $value[ $key ] );
		if ( '' === $secret ) {
			return;
		}

		update_option( self::SECRET_PREFIX . $key, $secret, false );
	}

	/**
	 * Returns one stored secret connection value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Field key.
	 * @return string Stored secret, or an empty string.
	 */
	public static function get_secret( string $key ): string {
		$constant = strtoupper( str_replace( '-', '_', XAiProfile::id() ) ) . '_' . strtoupper( $key );
		if ( defined( $constant ) ) {
			$value = constant( $constant );
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				return trim( $value );
			}
		}

		$environment = getenv( $constant );
		if ( is_string( $environment ) && '' !== trim( $environment ) ) {
			return trim( $environment );
		}

		$stored = get_option( self::SECRET_PREFIX . $key, '' );

		return is_string( $stored ) ? $stored : '';
	}

	/**
	 * Returns the stored settings.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Stored settings.
	 */
	public static function get_settings(): array {
		$settings = get_option( self::OPTION_NAME, array() );

		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Returns the API key override from a constant or environment variable.
	 *
	 * @since 1.0.0
	 *
	 * @return string Override value, or an empty string.
	 */
	public static function get_api_key_override(): string {
		$constant = XAiProfile::api_key_constant();

		if ( '' !== $constant && defined( $constant ) ) {
			$value = constant( $constant );
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				return trim( $value );
			}
		}

		$environment = '' !== $constant ? getenv( $constant ) : false;

		return is_string( $environment ) && '' !== trim( $environment ) ? trim( $environment ) : '';
	}

	/**
	 * Returns the option WordPress stores this connector's API key in.
	 *
	 * WordPress discovers every registered AI Client provider as a connector and
	 * registers one API key setting per connector, which the Connectors screen
	 * writes and which core hands to the AI Client on init. The connector's own
	 * settings screen writes that same option, so both screens always agree, and
	 * the key is read back through the AI Client rather than from the option.
	 *
	 * @since 1.0.0
	 *
	 * @return string Option name.
	 */
	public static function api_key_option(): string {
		return 'connectors_ai_' . str_replace( '-', '_', XAiProfile::id() ) . '_api_key';
	}

	/**
	 * Returns the API key WordPress has handed to the AI Client for this provider.
	 *
	 * @since 1.0.0
	 *
	 * @return string Stored API key, or an empty string.
	 */
	public static function get_saved_api_key(): string {
		if ( ! class_exists( AiClient::class ) ) {
			return '';
		}

		$registry = AiClient::defaultRegistry();
		if ( ! $registry->hasProvider( XAiProfile::id() ) ) {
			return '';
		}

		$authentication = $registry->getProviderRequestAuthentication( XAiProfile::id() );

		return $authentication instanceof ApiKeyRequestAuthentication ? $authentication->getApiKey() : '';
	}

	/**
	 * Reports whether an API key has been stored for this connector.
	 *
	 * Separate from the key itself: the credential comes back from the AI Client,
	 * which WordPress only populates while it is wiring connectors, so a site with
	 * AI support switched off reads no key even though one is saved. This answers
	 * the question the settings screen actually asks, without reading the
	 * credential out of the option.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True when a key is stored.
	 */
	public static function has_stored_api_key(): bool {
		if ( '' !== self::get_saved_api_key() ) {
			return true;
		}

		$settings = self::get_settings();

		return ! empty( $settings['has_api_key'] );
	}

	/**
	 * Returns the API key in use.
	 *
	 * @since 1.0.0
	 *
	 * @return string Effective API key.
	 */
	public static function get_api_key(): string {
		$override = self::get_api_key_override();

		return '' !== $override ? $override : self::get_saved_api_key();
	}

	/**
	 * Reports whether the connector holds everything it needs to connect.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True when credentials are present.
	 */
	public static function has_credentials(): bool {
		return XAiEndpoint::has_credentials( self::get_api_key(), self::get_settings() );
	}

	/**
	 * Returns the API base URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string Base URL without a trailing slash.
	 */
	public static function get_base_url(): string {
		return XAiEndpoint::base_url( self::get_settings() );
	}

	/**
	 * Adjusts an endpoint path for the provider's URL scheme.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Endpoint path relative to the base URL.
	 * @param string $model_id Model the request targets, when known.
	 * @return string Adjusted path.
	 */
	public static function decorate_path( string $path, string $model_id = '' ): string {
		return XAiEndpoint::decorate_path( $path, $model_id, self::get_settings() );
	}

	/**
	 * Returns the absolute URL that lists the available models.
	 *
	 * @since 1.0.0
	 *
	 * @return string Absolute URL.
	 */
	public static function get_models_url(): string {
		return XAiEndpoint::models_url( self::get_settings() );
	}

	/**
	 * Returns headers for a direct WordPress HTTP request.
	 *
	 * @since 1.0.0
	 *
	 * @param string $method HTTP method.
	 * @param string $url Absolute request URL.
	 * @param string $body Request body.
	 * @return array<string, string> Request headers.
	 */
	public static function get_request_headers( string $method = 'GET', string $url = '', string $body = '' ): array {
		return XAiEndpoint::request_headers( self::get_api_key(), self::get_settings(), $method, $url, $body );
	}

	/**
	 * Returns the configured default model for one capability.
	 *
	 * @since 1.0.0
	 *
	 * @param string $capability Capability key.
	 * @return string Model identifier, or an empty string for automatic.
	 */
	public static function get_preferred_model( string $capability = 'text' ): string {
		$settings = self::get_settings();
		$key      = 'model_' . $capability;

		return isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
	}

	/**
	 * Returns every configured default model.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Defaults keyed by capability.
	 */
	public static function get_preferred_models(): array {
		$models = array();

		foreach ( array_keys( self::model_capabilities() ) as $capability ) {
			$models[ $capability ] = self::get_preferred_model( $capability );
		}

		return $models;
	}

	/**
	 * Returns the configured reasoning effort.
	 *
	 * @since 1.0.0
	 *
	 * @return string Reasoning mode.
	 */
	public static function get_reasoning_effort(): string {
		$settings  = self::get_settings();
		$reasoning = isset( $settings['reasoning'] ) ? (string) $settings['reasoning'] : 'default';

		return in_array( $reasoning, self::REASONING_MODES, true ) ? $reasoning : 'default';
	}

	/**
	 * Returns the reasoning effort that applies to one model.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model_id Model identifier.
	 * @return string Reasoning mode.
	 */
	public static function get_reasoning_effort_for_model( string $model_id ): string {
		$preferred = self::get_preferred_model( 'text' );

		if ( '' === $preferred || $preferred !== $model_id ) {
			return 'default';
		}

		return self::get_reasoning_effort();
	}

	/**
	 * Returns one configured timeout.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Settings key.
	 * @param float  $fallback Value used when nothing valid is stored.
	 * @param float  $minimum Lowest accepted value.
	 * @return float Timeout in seconds.
	 */
	private static function get_timeout( string $key, float $fallback, float $minimum ): float {
		$settings = self::get_settings();

		if ( isset( $settings[ $key ] ) && is_numeric( $settings[ $key ] ) ) {
			$timeout = (float) $settings[ $key ];
			if ( $timeout >= $minimum && $timeout <= 1800.0 ) {
				return $timeout;
			}
		}

		return $fallback;
	}

	/**
	 * Returns the text generation timeout.
	 *
	 * @since 1.0.0
	 *
	 * @return float Timeout in seconds.
	 */
	public static function get_text_request_timeout(): float {
		return self::get_timeout( 'request_timeout', XAiProfile::default_timeout(), 15.0 );
	}

	/**
	 * Returns the embedding request timeout.
	 *
	 * @since 1.0.0
	 *
	 * @return float Timeout in seconds.
	 */
	public static function get_embedding_request_timeout(): float {
		return self::get_timeout( 'embedding_request_timeout', 60.0, 5.0 );
	}

	/**
	 * Returns the image request timeout.
	 *
	 * @since 1.0.0
	 *
	 * @return float Timeout in seconds.
	 */
	public static function get_image_request_timeout(): float {
		return self::get_timeout( 'image_request_timeout', 180.0, 15.0 );
	}

	/**
	 * Checks the stored credentials against the provider.
	 *
	 * @since 1.0.0
	 *
	 * @return true|WP_Error True when the provider accepts the credentials.
	 */
	public static function verify_connection() {
		if ( ! self::has_credentials() ) {
			return new WP_Error(
				'missing_credentials',
				sprintf(
					/* translators: %s: Provider name. */
					__( 'Add the %s credentials before checking the connection.', 'zactonz-ai-connector-for-xai-grok' ),
					XAiProfile::name()
				)
			);
		}

		$url      = self::get_models_url();
		$response = wp_remote_get(
			$url,
			array(
				'headers' => self::get_request_headers( 'GET', $url ),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'request_failed',
				sprintf(
					/* translators: 1: Provider name, 2: Error message. */
					__( 'Could not reach %1$s. Error: %2$s', 'zactonz-ai-connector-for-xai-grok' ),
					XAiProfile::name(),
					$response->get_error_message()
				)
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );

		if ( 401 === $status || 403 === $status ) {
			return new WP_Error(
				'invalid_credentials',
				sprintf(
					/* translators: %s: Provider name. */
					__( '%s rejected these credentials.', 'zactonz-ai-connector-for-xai-grok' ),
					XAiProfile::name()
				)
			);
		}

		if ( $status < 200 || $status >= 300 ) {
			return new WP_Error(
				'unexpected_status',
				sprintf(
					/* translators: 1: Provider name, 2: HTTP status code. */
					__( '%1$s returned HTTP %2$d while listing models.', 'zactonz-ai-connector-for-xai-grok' ),
					XAiProfile::name(),
					$status
				)
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || ( ! isset( $body['data'] ) && ! isset( $body['models'] ) && ! isset( $body['modelSummaries'] ) ) ) {
			return new WP_Error(
				'invalid_response',
				sprintf(
					/* translators: %s: Provider name. */
					__( '%s returned an unexpected model list.', 'zactonz-ai-connector-for-xai-grok' ),
					XAiProfile::name()
				)
			);
		}

		return true;
	}
}
