<?php
/**
 * Remove all plugin data on uninstall.
 *
 * @package CSI_Cookie_Consent
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove data for the current site.
 */
function gdprcn_uninstall_site() {
	global $wpdb;

	delete_option( 'gdprcn_settings' );
	delete_option( 'gdprcn_db_version' );
	delete_option( 'gdprcn_settings_version' );
	delete_site_transient( 'gdprcn_update_manifest' );
	wp_clear_scheduled_hook( 'gdprcn_purge_log' );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}gdprcn_consent_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $gdprcn_site_id ) {
		switch_to_blog( $gdprcn_site_id );
		gdprcn_uninstall_site();
		restore_current_blog();
	}
} else {
	gdprcn_uninstall_site();
}
