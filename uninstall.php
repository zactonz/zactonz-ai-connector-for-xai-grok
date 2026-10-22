<?php
/**
 * Removes the connector's stored options when the plugin is deleted.
 *
 * WordPress registers one API key option per connector and this connector writes
 * that option rather than keeping a second copy, so deleting the
 * plugin takes the credential with it instead of leaving a usable key behind.
 *
 * @package Zactonz\AiConnectorForXAi
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$zctz_xai_options = array(
	'zctz_xai_settings',
	'connectors_ai_' . str_replace( '-', '_', 'xai' ) . '_api_key',
);

foreach ( $zctz_xai_options as $zctz_xai_option ) {
	delete_option( $zctz_xai_option );

	if ( is_multisite() ) {
		delete_site_option( $zctz_xai_option );
	}
}
