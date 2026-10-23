<?php
/**
 * Plugin Name:       CSI Cookie Consent
 * Plugin URI:        https://github.com/csi-innovation/csi-cookie-consent
 * Description:       GDPR / ePrivacy cookie consent banner with granular categories, prior script blocking, Google Consent Mode v2 and a consent log.
 * Version:           1.2.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            CSI
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       csi-cookie-consent
 * Domain Path:       /languages
 * Update URI:        https://github.com/csi-innovation/csi-cookie-consent
 */

defined( 'ABSPATH' ) || exit;

define( 'GDPRCN_VERSION', '1.2.0' );
define( 'GDPRCN_FILE', __FILE__ );
define( 'GDPRCN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GDPRCN_URL', plugin_dir_url( __FILE__ ) );
define( 'GDPRCN_OPTION', 'gdprcn_settings' );
define( 'GDPRCN_COOKIE', 'gdprcn_consent' );

require_once GDPRCN_DIR . 'includes/functions.php';
require_once GDPRCN_DIR . 'includes/starter-translations.php';
require_once GDPRCN_DIR . 'includes/class-gdprcn-upgrade.php';
require_once GDPRCN_DIR . 'includes/class-gdprcn-settings.php';
require_once GDPRCN_DIR . 'includes/class-gdprcn-transfer.php';
require_once GDPRCN_DIR . 'includes/class-gdprcn-frontend.php';
require_once GDPRCN_DIR . 'includes/class-gdprcn-consent-log.php';
require_once GDPRCN_DIR . 'includes/class-gdprcn-updater.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once GDPRCN_DIR . 'includes/class-gdprcn-cli.php';
}

register_activation_hook( __FILE__, array( 'GDPRCN_Consent_Log', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GDPRCN_Consent_Log', 'deactivate' ) );

add_action( 'plugins_loaded', 'gdprcn_bootstrap' );

/**
 * Wire up the plugin components.
 */
function gdprcn_bootstrap() {
	add_action( 'init', 'gdprcn_load_textdomain' );

	GDPRCN_Upgrade::init();
	GDPRCN_Settings::init();
	GDPRCN_Transfer::init();
	GDPRCN_Frontend::init();
	GDPRCN_Consent_Log::init();
	GDPRCN_Updater::init();

	if ( class_exists( 'GDPRCN_CLI' ) ) {
		WP_CLI::add_command( 'cookie-consent', 'GDPRCN_CLI' );
	}
}

/**
 * Load translations.
 */
function gdprcn_load_textdomain() {
	load_plugin_textdomain( 'csi-cookie-consent', false, dirname( plugin_basename( GDPRCN_FILE ) ) . '/languages' );
}
