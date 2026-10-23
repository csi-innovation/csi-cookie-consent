<?php
/**
 * Consent log: anonymised proof-of-consent records (GDPR Art. 7(1)).
 *
 * @package CSI_Cookie_Consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stores, purges, lists and exports consent records.
 */
class GDPRCN_Consent_Log {

	const DB_VERSION = '1';
	const CRON       = 'gdprcn_purge_log';
	const PAGE       = 'gdprcn-consent-log';
	const PER_PAGE   = 50;

	/**
	 * Log table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'gdprcn_consent_log';
	}

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
		add_action( self::CRON, array( __CLASS__, 'purge' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_gdprcn_export_log', array( __CLASS__, 'export' ) );
	}

	/**
	 * Activation: create the table and schedule purging.
	 */
	public static function activate() {
		self::install();
		self::schedule();
	}

	/**
	 * Deactivation: stop purging.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
	}

	/**
	 * Install/upgrade lazily too, which covers multisite and file-copy updates.
	 */
	public static function maybe_install() {
		if ( get_option( 'gdprcn_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
		self::schedule();
	}

	/**
	 * Schedule the daily purge.
	 */
	private static function schedule() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/**
	 * Create or update the table.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			consent_id char(36) NOT NULL,
			created_at datetime NOT NULL,
			action varchar(20) NOT NULL,
			categories varchar(255) NOT NULL DEFAULT '',
			revision int(10) unsigned NOT NULL DEFAULT 1,
			ip_hash char(64) NOT NULL DEFAULT '',
			user_agent varchar(255) NOT NULL DEFAULT '',
			page_url varchar(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY consent_id (consent_id),
			KEY created_at (created_at)
			) {$charset};"
		);

		update_option( 'gdprcn_db_version', self::DB_VERSION, false );
	}

	/**
	 * REST endpoint the banner posts to. Public, because visitors are anonymous
	 * and pages may be cached (so a nonce cannot be relied on).
	 */
	public static function register_route() {
		register_rest_route(
			'gdprcn/v1',
			'/consent',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'record' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'consent_id' => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$',
					),
					'action'     => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => array( 'accept', 'reject', 'save' ),
					),
					'categories' => array(
						'type'     => 'object',
						'required' => true,
					),
					'url'        => array(
						'type'   => 'string',
						'format' => 'uri',
					),
				),
			)
		);
	}

	/**
	 * Store one consent decision.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function record( WP_REST_Request $request ) {
		global $wpdb;

		$settings = gdprcn_get_settings();
		if ( empty( $settings['log_enabled'] ) ) {
			return new WP_REST_Response( null, 204 );
		}

		$raw     = (array) $request->get_param( 'categories' );
		$granted = array( 'necessary' );
		foreach ( gdprcn_optional_categories() as $cat ) {
			if ( ! empty( $raw[ $cat ] ) ) {
				$granted[] = $cat;
			}
		}

		$url = (string) $request->get_param( 'url' );
		$url = 0 === strpos( $url, home_url() ) ? esc_url_raw( strtok( $url, '?#' ) ) : '';
		$ua  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::table(),
			array(
				'consent_id' => strtolower( $request->get_param( 'consent_id' ) ),
				'created_at' => current_time( 'mysql', true ),
				'action'     => $request->get_param( 'action' ),
				'categories' => implode( ',', $granted ),
				'revision'   => (int) $settings['revision'],
				'ip_hash'    => self::ip_hash(),
				'user_agent' => substr( $ua, 0, 255 ),
				'page_url'   => substr( $url, 0, 255 ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Keyed hash of the truncated IP (last IPv4 octet / last 80 IPv6 bits zeroed).
	 *
	 * @return string
	 */
	private static function ip_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$ip = preg_replace( '/\.\d+$/', '.0', $ip );
		} elseif ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$ip = inet_ntop( inet_pton( $ip ) & inet_pton( 'ffff:ffff:ffff::' ) );
		} else {
			return '';
		}

		return hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
	}

	/**
	 * Delete records past the retention period.
	 */
	public static function purge() {
		global $wpdb;

		$settings = gdprcn_get_settings();
		$cutoff   = gmdate( 'Y-m-d H:i:s', time() - (int) $settings['log_retention_days'] * DAY_IN_SECONDS );
		$table    = self::table();

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Tools → Cookie Consent Log.
	 */
	public static function menu() {
		add_management_page(
			__( 'Cookie Consent Log', 'csi-cookie-consent' ),
			__( 'Cookie Consent Log', 'csi-cookie-consent' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * WHERE clause for an optional consent-ID search.
	 *
	 * @param string $search Consent ID (or part of it).
	 * @return string
	 */
	private static function where( $search ) {
		global $wpdb;

		return '' === $search ? '' : $wpdb->prepare( 'WHERE consent_id LIKE %s', '%' . $wpdb->esc_like( $search ) . '%' );
	}

	/**
	 * Render the log page.
	 */
	public static function render() {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$table  = self::table();
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$where  = self::where( $search );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d", self::PER_PAGE, ( $paged - 1 ) * self::PER_PAGE ) );
		$stats = $wpdb->get_results( $wpdb->prepare( "SELECT action, COUNT(*) AS n FROM {$table} WHERE created_at >= %s GROUP BY action", gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ), OBJECT_K );
		// phpcs:enable

		$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=gdprcn_export_log' ), 'gdprcn_export_log' );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Cookie Consent Log', 'csi-cookie-consent' ); ?></h1>
			<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'csi-cookie-consent' ); ?></a>
			<hr class="wp-header-end">

			<p>
				<?php
				printf(
					/* translators: 1: accept-all count, 2: reject-all count, 3: custom choice count. */
					esc_html__( 'Last 30 days: %1$d accepted all, %2$d rejected all, %3$d made a custom choice.', 'csi-cookie-consent' ),
					isset( $stats['accept'] ) ? (int) $stats['accept']->n : 0,
					isset( $stats['reject'] ) ? (int) $stats['reject']->n : 0,
					isset( $stats['save'] ) ? (int) $stats['save']->n : 0
				);
				?>
			</p>

			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
				<p class="search-box">
					<label class="screen-reader-text" for="gdprcn-search"><?php esc_html_e( 'Search by consent ID', 'csi-cookie-consent' ); ?></label>
					<input type="search" id="gdprcn-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Consent ID', 'csi-cookie-consent' ); ?>">
					<?php submit_button( __( 'Search', 'csi-cookie-consent' ), '', '', false ); ?>
				</p>
			</form>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date (UTC)', 'csi-cookie-consent' ); ?></th>
						<th><?php esc_html_e( 'Consent ID', 'csi-cookie-consent' ); ?></th>
						<th><?php esc_html_e( 'Action', 'csi-cookie-consent' ); ?></th>
						<th><?php esc_html_e( 'Categories granted', 'csi-cookie-consent' ); ?></th>
						<th><?php esc_html_e( 'Rev.', 'csi-cookie-consent' ); ?></th>
						<th><?php esc_html_e( 'Page', 'csi-cookie-consent' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $rows ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No records.', 'csi-cookie-consent' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row->created_at ); ?></td>
							<td><code><?php echo esc_html( $row->consent_id ); ?></code></td>
							<td><?php echo esc_html( $row->action ); ?></td>
							<td><?php echo esc_html( str_replace( ',', ', ', $row->categories ) ); ?></td>
							<td><?php echo (int) $row->revision; ?></td>
							<td><?php echo esc_html( wp_make_link_relative( $row->page_url ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'    => add_query_arg( 'paged', '%#%' ),
								'format'  => '',
								'current' => $paged,
								'total'   => max( 1, (int) ceil( $total / self::PER_PAGE ) ),
							)
						)
					);
					?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Stream the full log as CSV.
	 */
	public static function export() {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'csi-cookie-consent' ), 403 );
		}
		check_admin_referer( 'gdprcn_export_log' );

		$table = self::table();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=cookie-consent-log-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'id', 'created_at_utc', 'consent_id', 'action', 'categories', 'revision', 'ip_hash', 'user_agent', 'page_url' ) );

		$last = 0;
		do {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, created_at, consent_id, action, categories, revision, ip_hash, user_agent, page_url FROM {$table} WHERE id > %d ORDER BY id ASC LIMIT 1000", $last ), ARRAY_A );
			foreach ( $rows as $row ) {
				// Neutralise spreadsheet formula injection.
				$row = array_map(
					function ( $v ) {
						return preg_match( '/^[=+\-@\t\r]/', (string) $v ) ? "'" . $v : $v;
					},
					$row
				);
				fputcsv( $out, $row );
				$last = (int) $row['id'];
			}
		} while ( count( $rows ) === 1000 );

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
