<?php
/**
 * Updates from the plugin's GitHub releases instead of wordpress.org.
 *
 * The plugin header's "Update URI" points at github.com, so WordPress (5.8+)
 * never matches it against wordpress.org and instead asks the
 * "update_plugins_github.com" filter. We answer from update.json, a manifest
 * the release workflow attaches to every GitHub release.
 *
 * @package CSI_Cookie_Consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Self-hosted update checker.
 */
class GDPRCN_Updater {

	const REPO      = 'csi-innovation/csi-cookie-consent';
	const TRANSIENT = 'gdprcn_update_manifest';

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'check' ), 10, 3 );
		add_filter( 'plugins_api', array( __CLASS__, 'details' ), 20, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'flush' ) );
	}

	/**
	 * Manifest URL. "latest/download" always redirects to the newest release's asset.
	 *
	 * @return string
	 */
	public static function manifest_url() {
		/**
		 * Filter the update manifest URL, e.g. to point a test site at a pre-release.
		 *
		 * @param string $url Manifest URL.
		 */
		return (string) apply_filters( 'gdprcn_update_manifest_url', 'https://github.com/' . self::REPO . '/releases/latest/download/update.json' );
	}

	/**
	 * Fetch (and cache) the release manifest.
	 *
	 * @param bool $force Skip the cache.
	 * @return array|null
	 */
	public static function manifest( $force = false ) {
		$cached = get_site_transient( self::TRANSIENT );
		if ( ! $force && is_array( $cached ) ) {
			return $cached ? $cached : null;
		}

		$response = wp_remote_get(
			self::manifest_url(),
			array(
				'timeout' => 10,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);
		$manifest = 200 === wp_remote_retrieve_response_code( $response )
			? json_decode( wp_remote_retrieve_body( $response ), true )
			: null;

		if ( ! self::valid( $manifest ) ) {
			// Remember the failure briefly so a GitHub outage does not slow every admin page.
			set_site_transient( self::TRANSIENT, array(), HOUR_IN_SECONDS );
			return null;
		}

		set_site_transient( self::TRANSIENT, $manifest, 12 * HOUR_IN_SECONDS );

		return $manifest;
	}

	/**
	 * A manifest must name a version and a package hosted in this repository's releases.
	 *
	 * @param mixed $manifest Decoded JSON.
	 * @return bool
	 */
	private static function valid( $manifest ) {
		return is_array( $manifest )
			&& ! empty( $manifest['version'] ) && preg_match( '/^\d+\.\d+\.\d+$/', $manifest['version'] )
			&& ! empty( $manifest['package'] )
			&& 0 === strpos( $manifest['package'], 'https://github.com/' . self::REPO . '/releases/download/' );
	}

	/**
	 * Answer WordPress's update check for this plugin.
	 *
	 * @param array|false $update      Update data from another filter, or false.
	 * @param array       $plugin_data Plugin header data.
	 * @param string      $plugin_file Plugin basename.
	 * @return array|false
	 */
	public static function check( $update, $plugin_data, $plugin_file ) {
		if ( plugin_basename( GDPRCN_FILE ) !== $plugin_file ) {
			return $update;
		}

		$force    = isset( $_GET['force-check'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only.
		$manifest = self::manifest( $force );
		if ( ! $manifest ) {
			return $update;
		}

		return array(
			'slug'         => dirname( $plugin_file ),
			'version'      => $manifest['version'],
			'url'          => 'https://github.com/' . self::REPO,
			'package'      => $manifest['package'],
			'tested'       => isset( $manifest['tested'] ) ? $manifest['tested'] : '',
			'requires'     => isset( $manifest['requires'] ) ? $manifest['requires'] : '',
			'requires_php' => isset( $manifest['requires_php'] ) ? $manifest['requires_php'] : '',
		);
	}

	/**
	 * "View details" popup on the Plugins and Updates screens.
	 *
	 * @param false|object|array $result Default result.
	 * @param string             $action API action.
	 * @param object             $args   Request arguments.
	 * @return false|object|array
	 */
	public static function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || dirname( plugin_basename( GDPRCN_FILE ) ) !== $args->slug ) {
			return $result;
		}

		$manifest = self::manifest();
		if ( ! $manifest ) {
			return $result;
		}

		$sections = isset( $manifest['sections'] ) && is_array( $manifest['sections'] ) ? $manifest['sections'] : array();

		return (object) array(
			'name'          => isset( $manifest['name'] ) ? $manifest['name'] : 'CSI Cookie Consent',
			'slug'          => $args->slug,
			'version'       => $manifest['version'],
			'author'        => 'CSI',
			'homepage'      => 'https://github.com/' . self::REPO,
			'download_link' => $manifest['package'],
			'requires'      => isset( $manifest['requires'] ) ? $manifest['requires'] : '',
			'tested'        => isset( $manifest['tested'] ) ? $manifest['tested'] : '',
			'requires_php'  => isset( $manifest['requires_php'] ) ? $manifest['requires_php'] : '',
			'last_updated'  => isset( $manifest['last_updated'] ) ? $manifest['last_updated'] : '',
			'sections'      => array_map( 'wp_kses_post', $sections ),
		);
	}

	/**
	 * Drop the cached manifest after any update so the next check is fresh.
	 */
	public static function flush() {
		delete_site_transient( self::TRANSIENT );
	}
}
