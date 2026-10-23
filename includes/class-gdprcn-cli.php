<?php
/**
 * WP-CLI commands: wp cookie-consent <command>.
 *
 * @package CSI_Cookie_Consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manage CSI Cookie Consent settings from the command line.
 */
class GDPRCN_CLI {

	/**
	 * Export settings as JSON.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<file>]
	 * : Write to this file instead of standard output.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cookie-consent export --file=settings.json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function export( $args, $assoc_args ) {
		$json = wp_json_encode( GDPRCN_Transfer::export_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		if ( empty( $assoc_args['file'] ) ) {
			WP_CLI::line( $json );
			return;
		}
		if ( false === file_put_contents( $assoc_args['file'], $json ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			WP_CLI::error( "Could not write {$assoc_args['file']}." );
		}
		WP_CLI::success( "Exported settings to {$assoc_args['file']}." );
	}

	/**
	 * Import settings from an export file.
	 *
	 * Keeps this site's privacy policy page and consent revision. Run with
	 * --user=<admin> so scripts and custom CSS are allowed.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Export file to import.
	 *
	 * [--include-scripts]
	 * : Also import tracking scripts (by default this site's scripts are kept).
	 *
	 * ## EXAMPLES
	 *
	 *     wp cookie-consent import settings.json --user=admin
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function import( $args, $assoc_args ) {
		$file = $args[0];
		if ( ! is_readable( $file ) ) {
			WP_CLI::error( "Cannot read {$file}." );
		}

		$include_scripts = ! empty( $assoc_args['include-scripts'] );
		if ( $include_scripts && ! current_user_can( 'unfiltered_html' ) ) {
			WP_CLI::warning( 'Scripts can only be imported as an administrator; run with --user=<admin>. Keeping this site\'s scripts.' );
		}

		$result = GDPRCN_Transfer::import( json_decode( (string) file_get_contents( $file ), true ), $include_scripts ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
		WP_CLI::success( 'Settings imported.' );
	}

	/**
	 * Ask every visitor for consent again (bumps the consent revision).
	 *
	 * @subcommand reset-consents
	 */
	public function reset_consents() {
		$settings             = get_option( GDPRCN_OPTION, array() );
		$settings             = is_array( $settings ) ? $settings : array();
		$settings['revision'] = ( isset( $settings['revision'] ) ? (int) $settings['revision'] : 1 ) + 1;
		update_option( GDPRCN_OPTION, $settings );

		WP_CLI::success( "Consent revision is now {$settings['revision']}; visitors will be asked again." );
	}

	/**
	 * Show the installed version, the latest release and whether the notice is enabled.
	 */
	public function status() {
		$settings = gdprcn_get_settings();
		$manifest = GDPRCN_Updater::manifest( true );

		WP_CLI\Utils\format_items(
			'table',
			array(
				array(
					'installed'  => GDPRCN_VERSION,
					'latest'     => $manifest ? $manifest['version'] : 'unknown',
					'active'     => is_plugin_active( plugin_basename( GDPRCN_FILE ) ) ? 'yes' : 'no',
					'enabled'    => $settings['enabled'] ? 'yes' : 'no',
					'revision'   => $settings['revision'],
					'languages'  => implode( ',', array_keys( $settings['translations'] ) ),
				),
			),
			array( 'installed', 'latest', 'active', 'enabled', 'revision', 'languages' )
		);
	}
}
