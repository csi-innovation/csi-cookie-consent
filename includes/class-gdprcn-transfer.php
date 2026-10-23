<?php
/**
 * Settings export and import (Settings → Cookie Notice → Import / export, and WP-CLI).
 *
 * @package CSI_Cookie_Consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Moves settings between sites as JSON.
 */
class GDPRCN_Transfer {

	/**
	 * Settings that belong to one site and are kept on import unless asked otherwise.
	 */
	const SITE_SPECIFIC = array( 'policy_page', 'revision' );

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'admin_post_gdprcn_export_settings', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_gdprcn_import_settings', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	/**
	 * Export payload.
	 *
	 * @return array
	 */
	public static function export_data() {
		return array(
			'plugin'   => 'csi-cookie-consent',
			'version'  => GDPRCN_VERSION,
			'exported' => gmdate( 'c' ),
			'site'     => home_url(),
			'settings' => gdprcn_get_settings(),
		);
	}

	/**
	 * Import an export payload into this site's settings.
	 *
	 * Keeps this site's policy page and consent revision, and – unless
	 * $include_scripts – its tracking scripts, which usually hold site-specific IDs.
	 *
	 * @param mixed $data            Decoded export.
	 * @param bool  $include_scripts Also import the category scripts.
	 * @return array|WP_Error The saved settings.
	 */
	public static function import( $data, $include_scripts = false ) {
		if ( ! is_array( $data ) || ( $data['plugin'] ?? '' ) !== 'csi-cookie-consent' || empty( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			return new WP_Error( 'gdprcn_invalid', __( 'This is not a CSI Cookie Consent settings export.', 'csi-cookie-consent' ) );
		}

		$from = isset( $data['version'] ) && preg_match( '/^\d+\.\d+\.\d+$/', $data['version'] ) ? $data['version'] : GDPRCN_Upgrade::UNVERSIONED;
		if ( version_compare( $from, GDPRCN_VERSION, '>' ) ) {
			return new WP_Error(
				'gdprcn_newer',
				/* translators: %s: version number. */
				sprintf( __( 'This export comes from a newer plugin version (%s). Update the plugin on this site first.', 'csi-cookie-consent' ), $from )
			);
		}

		$current  = gdprcn_get_settings();
		$incoming = GDPRCN_Upgrade::migrate( $data['settings'], $from );

		foreach ( self::SITE_SPECIFIC as $key ) {
			$incoming[ $key ] = $current[ $key ];
		}
		foreach ( gdprcn_categories() as $cat ) {
			if ( ! $include_scripts || ! isset( $incoming['categories'][ $cat ]['scripts'] ) ) {
				$incoming['categories'][ $cat ]['scripts'] = $current['categories'][ $cat ]['scripts'];
			}
		}

		$clean = GDPRCN_Settings::sanitize( $incoming );
		update_option( GDPRCN_OPTION, $clean );

		return $clean;
	}

	/**
	 * Download the export file.
	 */
	public static function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'csi-cookie-consent' ), 403 );
		}
		check_admin_referer( 'gdprcn_export_settings' );

		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=cookie-consent-' . sanitize_file_name( $host ) . '-' . gmdate( 'Y-m-d' ) . '.json' );
		echo wp_json_encode( self::export_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/**
	 * Handle the import upload.
	 */
	public static function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'csi-cookie-consent' ), 403 );
		}
		check_admin_referer( 'gdprcn_import_settings' );

		$result = new WP_Error( 'gdprcn_upload', __( 'Choose a .json export file to import.', 'csi-cookie-consent' ) );
		$file   = isset( $_FILES['gdprcn_import'] ) ? $_FILES['gdprcn_import'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated below.

		if ( $file && UPLOAD_ERR_OK === $file['error'] && is_uploaded_file( $file['tmp_name'] ) && $file['size'] < MB_IN_BYTES ) {
			$data   = json_decode( (string) file_get_contents( $file['tmp_name'] ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$result = self::import( $data, ! empty( $_POST['gdprcn_include_scripts'] ) );
		}

		$args = is_wp_error( $result )
			? array( 'gdprcn_import_error' => rawurlencode( $result->get_error_message() ) )
			: array( 'gdprcn_imported' => 1 );

		wp_safe_redirect( add_query_arg( $args, admin_url( 'options-general.php?page=' . GDPRCN_Settings::PAGE ) ) . '#tools' );
		exit;
	}

	/**
	 * Result notice after an import.
	 */
	public static function notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		if ( ! isset( $_GET['page'] ) || GDPRCN_Settings::PAGE !== $_GET['page'] ) {
			return;
		}
		if ( isset( $_GET['gdprcn_imported'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings imported. This site’s policy page, consent revision and (unless you chose otherwise) tracking scripts were kept.', 'csi-cookie-consent' ) . '</p></div>';
		} elseif ( isset( $_GET['gdprcn_import_error'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['gdprcn_import_error'] ) ) ) . '</p></div>';
		}
		// phpcs:enable
	}

	/**
	 * The Import / export tab (rendered outside the main settings form).
	 */
	public static function render_panel() {
		$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=gdprcn_export_settings' ), 'gdprcn_export_settings' );
		?>
		<h2 class="title"><?php esc_html_e( 'Export', 'csi-cookie-consent' ); ?></h2>
		<p><?php esc_html_e( 'Download all settings – texts, translations, layout, styling, categories and scripts – as a JSON file. Use it as a backup or to set up another site the same way.', 'csi-cookie-consent' ); ?></p>
		<p><a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Download settings', 'csi-cookie-consent' ); ?></a></p>

		<h2 class="title"><?php esc_html_e( 'Import', 'csi-cookie-consent' ); ?></h2>
		<p><?php esc_html_e( 'Replace this site’s settings with an export. This site’s privacy policy page and consent revision are always kept.', 'csi-cookie-consent' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="gdprcn_import_settings">
			<?php wp_nonce_field( 'gdprcn_import_settings' ); ?>
			<p><input type="file" name="gdprcn_import" accept=".json,application/json" required></p>
			<p>
				<label>
					<input type="checkbox" name="gdprcn_include_scripts" value="1" <?php disabled( ! current_user_can( 'unfiltered_html' ) ); ?>>
					<?php esc_html_e( 'Also import tracking scripts (otherwise this site’s scripts are kept – they usually contain site-specific IDs)', 'csi-cookie-consent' ); ?>
				</label>
			</p>
			<?php submit_button( __( 'Import settings', 'csi-cookie-consent' ), 'secondary', 'submit', false ); ?>
		</form>

		<h2 class="title"><?php esc_html_e( 'Command line', 'csi-cookie-consent' ); ?></h2>
		<p><?php esc_html_e( 'With WP-CLI:', 'csi-cookie-consent' ); ?> <code>wp cookie-consent export --file=settings.json</code>, <code>wp cookie-consent import settings.json</code>, <code>wp cookie-consent status</code>.</p>
		<?php
	}
}
