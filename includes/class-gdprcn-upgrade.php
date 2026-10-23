<?php
/**
 * Settings migrations between plugin versions.
 *
 * When a release changes how settings are stored, add a migration to
 * migrations() keyed by that release's version. It runs once on every site
 * that updates from an older version, and on imports of older exports.
 *
 * @package CSI_Cookie_Consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Runs settings migrations.
 */
class GDPRCN_Upgrade {

	/**
	 * Option holding the plugin version the stored settings are in.
	 */
	const OPTION = 'gdprcn_settings_version';

	/**
	 * Last version released before settings were versioned.
	 */
	const UNVERSIONED = '1.1.0';

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 5 );
	}

	/**
	 * Migrations as version => callable( array $settings ): array.
	 *
	 * Example for a future release that renames a setting:
	 *
	 *     '1.3.0' => function ( $s ) {
	 *         $s['new_key'] = $s['old_key'] ?? 'default';
	 *         unset( $s['old_key'] );
	 *         return $s;
	 *     },
	 *
	 * @return array
	 */
	public static function migrations() {
		return array(
			// The on/off floating button became a choice between button, menu item or neither.
			'1.3.0' => function ( $s ) {
				if ( ! isset( $s['revisit_mode'] ) ) {
					$s['revisit_mode'] = isset( $s['revisit_button'] ) && empty( $s['revisit_button'] ) ? 'none' : 'button';
				}
				unset( $s['revisit_button'] );
				return $s;
			},
		);
	}

	/**
	 * Apply every migration newer than $from and not newer than this version.
	 *
	 * @param array  $settings Stored settings.
	 * @param string $from     Version the settings were saved by.
	 * @return array
	 */
	public static function migrate( array $settings, $from ) {
		foreach ( self::migrations() as $version => $callback ) {
			if ( version_compare( $from, $version, '<' ) && version_compare( $version, GDPRCN_VERSION, '<=' ) ) {
				$settings = (array) call_user_func( $callback, $settings );
			}
		}

		return $settings;
	}

	/**
	 * Migrate stored settings once after an update.
	 */
	public static function maybe_upgrade() {
		$from = (string) get_option( self::OPTION, '' );
		if ( GDPRCN_VERSION === $from ) {
			return;
		}

		$settings = get_option( GDPRCN_OPTION, null );
		if ( is_array( $settings ) ) {
			$migrated = self::migrate( $settings, '' === $from ? self::UNVERSIONED : $from );
			if ( $migrated !== $settings ) {
				update_option( GDPRCN_OPTION, $migrated );
			}
		}

		update_option( self::OPTION, GDPRCN_VERSION );
	}
}
