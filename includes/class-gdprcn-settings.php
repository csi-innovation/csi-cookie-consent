<?php
/**
 * Admin settings page (Settings → Cookie Notice).
 *
 * @package CSI_Cookie_Consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers, sanitises and renders the plugin settings.
 */
class GDPRCN_Settings {

	const PAGE = 'csi-cookie-consent';

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_init', array( __CLASS__, 'privacy_policy_content' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( GDPRCN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Add the settings page.
	 */
	public static function menu() {
		add_options_page(
			__( 'Cookie Notice', 'csi-cookie-consent' ),
			__( 'Cookie Notice', 'csi-cookie-consent' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Register the option.
	 */
	public static function register() {
		register_setting(
			'gdprcn',
			GDPRCN_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Settings link on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ),
				esc_html__( 'Settings', 'csi-cookie-consent' )
			)
		);

		return $links;
	}

	/**
	 * Admin assets on our page only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function assets( $hook ) {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'gdprcn', GDPRCN_URL . 'assets/css/gdprcn.css', array(), GDPRCN_VERSION );
		wp_enqueue_style( 'gdprcn-admin', GDPRCN_URL . 'assets/css/admin.css', array( 'gdprcn' ), GDPRCN_VERSION );
		wp_enqueue_script( 'gdprcn-admin', GDPRCN_URL . 'assets/js/admin.js', array( 'jquery', 'wp-color-picker' ), GDPRCN_VERSION, true );
		wp_localize_script( 'gdprcn-admin', 'gdprcnAdmin', self::admin_data() );
	}

	/**
	 * Data for the settings page script.
	 *
	 * @return array
	 */
	public static function admin_data() {
		$starters = array();
		foreach ( gdprcn_languages() as $code => $lang ) {
			$starter = gdprcn_starter_translation( $code );
			if ( ! $starter ) {
				$starter = gdprcn_starter_translation( $lang[1] );
			}
			if ( $starter ) {
				$starters[ $code ] = $starter;
			}
		}

		return array(
			'starters' => $starters,
			'presets'  => self::presets(),
			'i18n'     => array(
				/* translators: %d: number of fields. */
				'filled'       => __( 'Filled %d empty fields. Review them, then save.', 'csi-cookie-consent' ),
				'nothing'      => __( 'All fields already have text.', 'csi-cookie-consent' ),
				'confirmClear' => __( 'Clear every translated text for this language?', 'csi-cookie-consent' ),
			),
		);
	}

	/**
	 * Colour presets offered on the Styling tab.
	 *
	 * @return array
	 */
	public static function presets() {
		return array(
			'light'   => array(
				'label'             => __( 'Light', 'csi-cookie-consent' ),
				'bg_color'          => '#ffffff',
				'text_color'        => '#1d2327',
				'accent_color'      => '#2271b1',
				'accent_text_color' => '#ffffff',
			),
			'dark'    => array(
				'label'             => __( 'Dark', 'csi-cookie-consent' ),
				'bg_color'          => '#1d2327',
				'text_color'        => '#f0f0f1',
				'accent_color'      => '#72aee6',
				'accent_text_color' => '#1d2327',
			),
			'minimal' => array(
				'label'             => __( 'Minimal', 'csi-cookie-consent' ),
				'bg_color'          => '#f6f7f7',
				'text_color'        => '#1d2327',
				'accent_color'      => '#1d2327',
				'accent_text_color' => '#ffffff',
			),
			'eu'      => array(
				'label'             => __( 'EU blue', 'csi-cookie-consent' ),
				'bg_color'          => '#003399',
				'text_color'        => '#ffffff',
				'accent_color'      => '#ffcc00',
				'accent_text_color' => '#003399',
			),
		);
	}

	/**
	 * Suggested text for Settings → Privacy → Policy guide.
	 */
	public static function privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content = '<p>' . __( 'When you first visit this site we ask for your consent to set non-essential cookies. Your choice is stored in a cookie named "gdprcn_consent" for up to the period configured by the site owner, together with a random consent ID, the date and the version of the cookie notice you responded to.', 'csi-cookie-consent' ) . '</p>'
			. '<p>' . __( 'To be able to demonstrate consent, a record of your choice may be kept containing the consent ID, the categories you accepted, the date, the page, your browser user agent and a one-way hash of your truncated IP address. This record does not identify you directly and is deleted automatically after the configured retention period.', 'csi-cookie-consent' ) . '</p>'
			. '<p>' . __( 'You can change or withdraw your consent at any time using the "Cookie settings" link or button on this site.', 'csi-cookie-consent' ) . '</p>';

		wp_add_privacy_policy_content( 'CSI Cookie Consent', wp_kses_post( $content ) );
	}

	/* Choices ------------------------------------------------------------- */

	/**
	 * Banner positions.
	 *
	 * @return array
	 */
	public static function positions() {
		return array(
			'bottom'        => __( 'Full-width bar – bottom', 'csi-cookie-consent' ),
			'top'           => __( 'Full-width bar – top', 'csi-cookie-consent' ),
			'bottom-left'   => __( 'Floating box – bottom left', 'csi-cookie-consent' ),
			'bottom-center' => __( 'Floating box – bottom centre', 'csi-cookie-consent' ),
			'bottom-right'  => __( 'Floating box – bottom right', 'csi-cookie-consent' ),
			'top-left'      => __( 'Floating box – top left', 'csi-cookie-consent' ),
			'top-right'     => __( 'Floating box – top right', 'csi-cookie-consent' ),
			'center'        => __( 'Centred dialog', 'csi-cookie-consent' ),
		);
	}

	/**
	 * Whitelisted values for the select settings.
	 *
	 * @return array
	 */
	private static function choices() {
		return array(
			'position'         => self::positions(),
			'revisit_position' => array(
				'left'  => __( 'Bottom left', 'csi-cookie-consent' ),
				'right' => __( 'Bottom right', 'csi-cookie-consent' ),
			),
			'revisit_style'    => array(
				'icon' => __( 'Icon only', 'csi-cookie-consent' ),
				'text' => __( 'Icon and text', 'csi-cookie-consent' ),
			),
			'font_family'      => array(
				'inherit' => __( 'Same as the theme', 'csi-cookie-consent' ),
				'system'  => __( 'System sans-serif', 'csi-cookie-consent' ),
				'serif'   => __( 'Serif', 'csi-cookie-consent' ),
			),
			'customize_style'  => array(
				'outline' => __( 'Outlined button', 'csi-cookie-consent' ),
				'link'    => __( 'Text link', 'csi-cookie-consent' ),
				'button'  => __( 'Filled button (same as the others)', 'csi-cookie-consent' ),
			),
			'button_order'     => array(
				'accept-last'  => __( 'Customise · Reject all · Accept all', 'csi-cookie-consent' ),
				'accept-first' => __( 'Accept all · Reject all · Customise', 'csi-cookie-consent' ),
			),
		);
	}

	/**
	 * Numeric settings as key => [ min, max ].
	 *
	 * @return array
	 */
	private static function ranges() {
		return array(
			'expiry_days'        => array( 1, 395 ),
			'log_retention_days' => array( 30, 3650 ),
			'font_size'          => array( 12, 22 ),
			'box_radius'         => array( 0, 32 ),
			'button_radius'      => array( 0, 32 ),
			'max_width'          => array( 300, 800 ),
		);
	}

	/* Sanitising ---------------------------------------------------------- */

	/**
	 * Sanitise one visitor-facing text.
	 *
	 * @param string $key   Text key.
	 * @param mixed  $value Raw value.
	 * @return string
	 */
	private static function sanitize_text( $key, $value ) {
		$fields = gdprcn_text_fields();
		$value  = is_scalar( $value ) ? (string) $value : '';

		return isset( $fields[ $key ] ) && 'textarea' === $fields[ $key ][1]
			? trim( wp_kses( $value, gdprcn_allowed_inline_html() ) )
			: sanitize_text_field( $value );
	}

	/**
	 * Sanitise submitted settings.
	 *
	 * Note: WordPress runs this twice when the option is first created, so it
	 * must be idempotent on its own output.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$current  = gdprcn_get_settings();
		$defaults = gdprcn_defaults();
		$out      = array();

		// Default-language texts (category texts are handled below).
		foreach ( array_keys( gdprcn_text_fields() ) as $key ) {
			if ( 0 === strpos( $key, 'cat_' ) ) {
				continue;
			}
			$value       = isset( $input[ $key ] ) ? self::sanitize_text( $key, $input[ $key ] ) : '';
			$out[ $key ] = '' !== $value ? $value : $defaults[ $key ];
		}

		foreach ( array( 'enabled', 'mobile_bar', 'overlay', 'shadow', 'animation', 'revisit_button', 'reload_on_withdraw', 'consent_mode', 'log_enabled' ) as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		foreach ( array( 'bg_color', 'text_color', 'accent_color', 'accent_text_color' ) as $key ) {
			$color       = isset( $input[ $key ] ) ? sanitize_hex_color( $input[ $key ] ) : '';
			$out[ $key ] = $color ? $color : $defaults[ $key ];
		}

		foreach ( self::choices() as $key => $options ) {
			$out[ $key ] = isset( $input[ $key ] ) && array_key_exists( $input[ $key ], $options ) ? $input[ $key ] : $defaults[ $key ];
		}

		foreach ( self::ranges() as $key => $range ) {
			$out[ $key ] = isset( $input[ $key ] ) && '' !== $input[ $key ] ? min( $range[1], max( $range[0], (int) $input[ $key ] ) ) : $defaults[ $key ];
		}

		$out['policy_page'] = isset( $input['policy_page'] ) ? absint( $input['policy_page'] ) : 0;

		// Custom CSS follows the same rule as the Customizer's "Additional CSS".
		$out['custom_css'] = current_user_can( 'edit_css' ) && isset( $input['custom_css'] )
			? trim( str_replace( '</', '', wp_strip_all_tags( $input['custom_css'] ) ) )
			: $current['custom_css'];

		// Bumping the revision invalidates every stored consent and re-prompts all visitors.
		$out['revision'] = max( 1, isset( $input['revision'] ) ? absint( $input['revision'] ) : (int) $current['revision'] );
		if ( ! empty( $input['bump_revision'] ) ) {
			$out['revision'] = (int) $current['revision'] + 1;
		}

		// Categories.
		$raw_cats          = isset( $input['categories'] ) && is_array( $input['categories'] ) ? $input['categories'] : array();
		$out['categories'] = array();
		foreach ( gdprcn_categories() as $cat ) {
			$raw = isset( $raw_cats[ $cat ] ) && is_array( $raw_cats[ $cat ] ) ? $raw_cats[ $cat ] : array();
			$def = $defaults['categories'][ $cat ];

			$label       = isset( $raw['label'] ) ? self::sanitize_text( "cat_{$cat}_label", $raw['label'] ) : '';
			$description = isset( $raw['description'] ) ? self::sanitize_text( "cat_{$cat}_description", $raw['description'] ) : '';
			$scripts     = isset( $raw['scripts'] ) ? trim( $raw['scripts'] ) : '';
			if ( ! current_user_can( 'unfiltered_html' ) ) {
				// Users without unfiltered_html cannot add scripts; keep what is already there.
				$scripts = $current['categories'][ $cat ]['scripts'];
			}

			$out['categories'][ $cat ] = array(
				'enabled'     => 'necessary' === $cat || ! empty( $raw['enabled'] ) ? 1 : 0,
				'label'       => '' !== $label ? $label : $def['label'],
				'description' => '' !== $description ? $description : $def['description'],
				'scripts'     => 'necessary' === $cat ? '' : $scripts,
				'cookies'     => isset( $raw['cookies'] ) ? sanitize_textarea_field( $raw['cookies'] ) : '',
			);
		}

		// Translations: only non-empty values are stored; empty means "use the default language".
		$out['extra_languages'] = isset( $input['extra_languages'] )
			? implode( ', ', array_filter( preg_split( '/[\s,]+/', sanitize_text_field( $input['extra_languages'] ) ), array( __CLASS__, 'is_language_code' ) ) )
			: $current['extra_languages'];

		$raw_tr              = isset( $input['translations'] ) && is_array( $input['translations'] ) ? $input['translations'] : array();
		$out['translations'] = array();
		foreach ( $raw_tr as $lang => $texts ) {
			if ( ! self::is_language_code( $lang ) || ! is_array( $texts ) ) {
				continue;
			}
			foreach ( array_keys( gdprcn_text_fields() ) as $key ) {
				$value = isset( $texts[ $key ] ) ? self::sanitize_text( $key, $texts[ $key ] ) : '';
				if ( '' !== $value ) {
					$out['translations'][ $lang ][ $key ] = $value;
				}
			}
		}

		return $out;
	}

	/**
	 * Whether a string looks like a language code or locale (en, pt-br, sr_RS).
	 *
	 * @param string $code Candidate.
	 * @return bool
	 */
	public static function is_language_code( $code ) {
		return is_string( $code ) && (bool) preg_match( '/^[a-zA-Z]{2,3}([_-][a-zA-Z0-9]{2,8})*$/', $code );
	}

	/* Rendering ----------------------------------------------------------- */

	/**
	 * Field name helper.
	 *
	 * @param string ...$path Key path.
	 * @return string
	 */
	private static function name( ...$path ) {
		return GDPRCN_OPTION . '[' . implode( '][', $path ) . ']';
	}

	/**
	 * Field id helper.
	 *
	 * @param string ...$path Key path.
	 * @return string
	 */
	private static function id( ...$path ) {
		return 'gdprcn-' . implode( '-', $path );
	}

	/**
	 * Print a form-table row.
	 *
	 * @param string $label    Row label.
	 * @param string $field    Field HTML (already escaped).
	 * @param string $help     Optional help text (plain).
	 * @param string $label_for Optional field id for the label.
	 */
	private static function row( $label, $field, $help = '', $label_for = '' ) {
		echo '<tr><th scope="row">';
		echo $label_for ? '<label for="' . esc_attr( $label_for ) . '">' . esc_html( $label ) . '</label>' : esc_html( $label );
		echo '</th><td>' . $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		if ( $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Text or textarea input for a visitor-facing text.
	 *
	 * @param string $name        Field name.
	 * @param string $id          Field id.
	 * @param string $value       Value.
	 * @param string $type        "text" or "textarea".
	 * @param string $key         Text key, for the live preview.
	 * @param string $lang        Language code, "" for default.
	 * @param string $placeholder Placeholder.
	 * @return string
	 */
	private static function text_input( $name, $id, $value, $type, $key, $lang = '', $placeholder = '' ) {
		$attrs = sprintf(
			'name="%s" id="%s" data-gdprcn-key="%s" data-gdprcn-lang="%s" placeholder="%s"',
			esc_attr( $name ),
			esc_attr( $id ),
			esc_attr( $key ),
			esc_attr( $lang ),
			esc_attr( $placeholder )
		);

		return 'textarea' === $type
			? '<textarea class="large-text" rows="3" ' . $attrs . '>' . esc_textarea( $value ) . '</textarea>'
			: '<input type="text" class="regular-text" ' . $attrs . ' value="' . esc_attr( $value ) . '">';
	}

	/**
	 * Checkbox for a top-level setting.
	 *
	 * @param array  $s    Settings.
	 * @param string $key  Setting key.
	 * @param string $text Checkbox text.
	 * @return string
	 */
	private static function checkbox( $s, $key, $text ) {
		return sprintf(
			'<label><input type="checkbox" name="%s" value="1" data-gdprcn-setting="%s" %s> %s</label>',
			esc_attr( self::name( $key ) ),
			esc_attr( $key ),
			checked( ! empty( $s[ $key ] ), true, false ),
			esc_html( $text )
		);
	}

	/**
	 * Select for a top-level setting.
	 *
	 * @param array  $s   Settings.
	 * @param string $key Setting key.
	 * @return string
	 */
	private static function select( $s, $key ) {
		$html = sprintf( '<select name="%s" id="%s" data-gdprcn-setting="%s">', esc_attr( self::name( $key ) ), esc_attr( self::id( $key ) ), esc_attr( $key ) );
		foreach ( self::choices()[ $key ] as $value => $label ) {
			$html .= sprintf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $s[ $key ], $value, false ), esc_html( $label ) );
		}

		return $html . '</select>';
	}

	/**
	 * Number input for a top-level setting.
	 *
	 * @param array  $s    Settings.
	 * @param string $key  Setting key.
	 * @param string $unit Unit shown after the field.
	 * @return string
	 */
	private static function number( $s, $key, $unit ) {
		$range = self::ranges()[ $key ];

		return sprintf(
			'<input type="number" class="small-text" min="%d" max="%d" name="%s" id="%s" value="%d" data-gdprcn-setting="%s"> %s',
			$range[0],
			$range[1],
			esc_attr( self::name( $key ) ),
			esc_attr( self::id( $key ) ),
			(int) $s[ $key ],
			esc_attr( $key ),
			esc_html( $unit )
		);
	}

	/**
	 * Colour picker for a top-level setting.
	 *
	 * @param array  $s   Settings.
	 * @param string $key Setting key.
	 * @return string
	 */
	private static function color( $s, $key ) {
		return sprintf(
			'<input type="text" class="gdprcn-color" name="%s" id="%s" value="%s" data-default-color="%s" data-gdprcn-setting="%s">',
			esc_attr( self::name( $key ) ),
			esc_attr( self::id( $key ) ),
			esc_attr( $s[ $key ] ),
			esc_attr( gdprcn_defaults()[ $key ] ),
			esc_attr( $key )
		);
	}

	/**
	 * Tabs, as slug => label.
	 *
	 * @return array
	 */
	private static function tabs() {
		return array(
			'general'      => __( 'General', 'csi-cookie-consent' ),
			'text'         => __( 'Text', 'csi-cookie-consent' ),
			'translations' => __( 'Translations', 'csi-cookie-consent' ),
			'layout'       => __( 'Layout & position', 'csi-cookie-consent' ),
			'styling'      => __( 'Styling', 'csi-cookie-consent' ),
			'categories'   => __( 'Cookie categories', 'csi-cookie-consent' ),
			'integrations' => __( 'Integrations & records', 'csi-cookie-consent' ),
			'tools'        => __( 'Import / export', 'csi-cookie-consent' ),
		);
	}

	/**
	 * Render the settings page.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = gdprcn_get_settings();
		?>
		<div class="wrap gdprcn-admin">
			<h1><?php esc_html_e( 'Cookie Notice', 'csi-cookie-consent' ); ?></h1>

			<?php self::render_preview( $s ); ?>

			<nav class="nav-tab-wrapper gdprcn-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'csi-cookie-consent' ); ?>">
				<?php foreach ( self::tabs() as $slug => $label ) : ?>
					<a href="#<?php echo esc_attr( $slug ); ?>" class="nav-tab" data-gdprcn-tab="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<form action="options.php" method="post">
				<?php settings_fields( 'gdprcn' ); ?>

				<?php
				foreach ( array_keys( self::tabs() ) as $slug ) {
					if ( 'tools' === $slug ) {
						continue;
					}
					echo '<div class="gdprcn-panel" data-gdprcn-panel="' . esc_attr( $slug ) . '">';
					call_user_func( array( __CLASS__, 'tab_' . $slug ), $s );
					echo '</div>';
				}
				?>

				<div class="gdprcn-submit"><?php submit_button(); ?></div>
			</form>

			<?php // Import / export has its own forms, so it sits outside the settings form. ?>
			<div class="gdprcn-panel" data-gdprcn-panel="tools"><?php GDPRCN_Transfer::render_panel(); ?></div>
		</div>
		<?php
	}

	/**
	 * Live preview of the banner.
	 *
	 * @param array $s Settings.
	 */
	private static function render_preview( $s ) {
		?>
		<div class="gdprcn-preview-wrap">
			<div class="gdprcn-preview-toolbar">
				<strong><?php esc_html_e( 'Live preview', 'csi-cookie-consent' ); ?></strong>
				<span class="gdprcn-preview-views" role="group">
					<button type="button" class="button button-small is-active" data-gdprcn-view="banner"><?php esc_html_e( 'Banner', 'csi-cookie-consent' ); ?></button>
					<button type="button" class="button button-small" data-gdprcn-view="prefs"><?php esc_html_e( 'Preferences', 'csi-cookie-consent' ); ?></button>
				</span>
				<?php $languages = gdprcn_languages(); ?>
				<?php if ( $languages ) : ?>
					<label>
						<?php esc_html_e( 'Language', 'csi-cookie-consent' ); ?>
						<select class="gdprcn-preview-lang">
							<option value=""><?php esc_html_e( 'Default', 'csi-cookie-consent' ); ?></option>
							<?php foreach ( $languages as $code => $lang ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $lang[0] ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				<?php endif; ?>
				<span class="description"><?php esc_html_e( 'Updates as you edit. Save to publish.', 'csi-cookie-consent' ); ?></span>
			</div>
			<div class="gdprcn-preview-stage" aria-hidden="true">
				<div class="gdprcn-preview-page"><span></span><span></span><span></span><span></span><span></span></div>
				<?php echo GDPRCN_Frontend::markup( $s, null, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in markup(). ?>
			</div>
		</div>
		<?php
	}

	/**
	 * General tab.
	 *
	 * @param array $s Settings.
	 */
	private static function tab_general( $s ) {
		echo '<table class="form-table" role="presentation">';

		self::row( __( 'Status', 'csi-cookie-consent' ), self::checkbox( $s, 'enabled', __( 'Show the cookie notice on the front end', 'csi-cookie-consent' ) ) );

		$pages = wp_dropdown_pages(
			array(
				'name'              => esc_attr( self::name( 'policy_page' ) ),
				'id'                => 'gdprcn-policy_page',
				'selected'          => (int) $s['policy_page'],
				'show_option_none'  => esc_html__( '— None —', 'csi-cookie-consent' ),
				'option_none_value' => '0',
				'post_status'       => array( 'publish', 'private', 'draft' ),
				'echo'              => 0,
			)
		);
		$help  = __( 'Linked from the banner. With Polylang or WPML the translated page is used automatically for each language.', 'csi-cookie-consent' );
		if ( $s['policy_page'] && 'publish' !== get_post_status( $s['policy_page'] ) ) {
			$help .= ' ' . __( 'This page is not published, so the link is hidden from visitors until it is.', 'csi-cookie-consent' );
		}
		self::row( __( 'Privacy / cookie policy page', 'csi-cookie-consent' ), $pages, $help, 'gdprcn-policy_page' );

		self::row( __( 'Remember choice for', 'csi-cookie-consent' ), self::number( $s, 'expiry_days', __( 'days', 'csi-cookie-consent' ) ), __( 'Many EU regulators recommend re-asking after 6–13 months.', 'csi-cookie-consent' ), 'gdprcn-expiry_days' );
		self::row( __( 'On withdrawal', 'csi-cookie-consent' ), self::checkbox( $s, 'reload_on_withdraw', __( 'Reload the page when a visitor withdraws consent, so already-loaded tracking scripts stop running', 'csi-cookie-consent' ) ) );

		self::row(
			__( 'Re-ask everyone', 'csi-cookie-consent' ),
			sprintf( '<label><input type="checkbox" name="%s" value="1"> %s</label>', esc_attr( self::name( 'bump_revision' ) ), esc_html__( 'Reset all stored consents on save', 'csi-cookie-consent' ) ),
			/* translators: %d: current consent revision number. */
			sprintf( __( 'Use this after adding new cookies or changing purposes. Current revision: %d.', 'csi-cookie-consent' ), (int) $s['revision'] )
		);

		echo '</table>';
	}

	/**
	 * Text tab (default language).
	 *
	 * @param array $s Settings.
	 */
	private static function tab_text( $s ) {
		echo '<p>' . esc_html__( 'Texts in the site’s default language. Category names and descriptions are on the Cookie categories tab; other languages on the Translations tab.', 'csi-cookie-consent' ) . '</p>';
		echo '<p class="description">' . wp_kses( __( 'Allowed HTML in longer texts: <code>&lt;a&gt;</code>, <code>&lt;strong&gt;</code>, <code>&lt;em&gt;</code>, <code>&lt;br&gt;</code>.', 'csi-cookie-consent' ), array( 'code' => array() ) ) . '</p>';
		echo '<table class="form-table" role="presentation">';
		foreach ( gdprcn_text_fields() as $key => $field ) {
			if ( 0 === strpos( $key, 'cat_' ) ) {
				continue;
			}
			self::row( $field[0], self::text_input( self::name( $key ), self::id( $key ), $s[ $key ], $field[1], $key ), '', self::id( $key ) );
		}
		echo '</table>';
	}

	/**
	 * Translations tab.
	 *
	 * @param array $s Settings.
	 */
	private static function tab_translations( $s ) {
		$source    = gdprcn_language_source();
		$languages = gdprcn_languages();
		$fields    = gdprcn_text_fields();

		if ( 'polylang' === $source ) {
			echo '<p>' . esc_html__( 'Languages come from Polylang. The banner is shown in the language of the page being viewed; any text left empty falls back to the default language.', 'csi-cookie-consent' ) . '</p>';
		} elseif ( 'wpml' === $source ) {
			echo '<p>' . esc_html__( 'Languages come from WPML. The banner is shown in the language of the page being viewed; any text left empty falls back to the default language.', 'csi-cookie-consent' ) . '</p>';
		} else {
			echo '<table class="form-table" role="presentation">';
			self::row(
				__( 'Languages', 'csi-cookie-consent' ),
				sprintf( '<input type="text" class="regular-text" name="%s" id="gdprcn-extra_languages" value="%s" placeholder="fr, de, el">', esc_attr( self::name( 'extra_languages' ) ), esc_attr( $s['extra_languages'] ) ),
				__( 'No multilingual plugin found. List language codes or locales (comma separated); the banner uses the matching translation when the site language (Settings → General) matches. Save to add them below.', 'csi-cookie-consent' ),
				'gdprcn-extra_languages'
			);
			echo '</table>';
		}

		if ( ! $languages ) {
			echo '<p><em>' . esc_html__( 'No additional languages to translate.', 'csi-cookie-consent' ) . '</em></p>';
			// Keep stored translations when there is nothing to show.
			foreach ( $s['translations'] as $lang => $texts ) {
				foreach ( $texts as $key => $value ) {
					printf( '<input type="hidden" name="%s" value="%s">', esc_attr( self::name( 'translations', $lang, $key ) ), esc_attr( $value ) );
				}
			}
			return;
		}

		$total = count( $fields );
		?>
		<p class="gdprcn-lang-picker">
			<label for="gdprcn-lang-select"><strong><?php esc_html_e( 'Language to edit', 'csi-cookie-consent' ); ?></strong></label>
			<select id="gdprcn-lang-select">
				<?php foreach ( $languages as $code => $lang ) : ?>
					<?php $done = isset( $s['translations'][ $code ] ) ? count( array_intersect_key( $s['translations'][ $code ], $fields ) ) : 0; ?>
					<option value="<?php echo esc_attr( $code ); ?>">
						<?php
						/* translators: 1: language name, 2: translated count, 3: total count. */
						echo esc_html( sprintf( __( '%1$s (%2$d / %3$d translated)', 'csi-cookie-consent' ), $lang[0], $done, $total ) );
						?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<?php foreach ( $languages as $code => $lang ) : ?>
			<div class="gdprcn-lang-panel" data-gdprcn-lang-panel="<?php echo esc_attr( $code ); ?>">
				<p>
					<button type="button" class="button gdprcn-fill-starter" data-gdprcn-lang="<?php echo esc_attr( $code ); ?>"><?php esc_html_e( 'Fill empty fields with suggested translation', 'csi-cookie-consent' ); ?></button>
					<button type="button" class="button-link gdprcn-clear-lang" data-gdprcn-lang="<?php echo esc_attr( $code ); ?>"><?php esc_html_e( 'Clear this language', 'csi-cookie-consent' ); ?></button>
					<span class="gdprcn-fill-status" aria-live="polite"></span>
				</p>
				<p class="description gdprcn-no-starter" hidden><?php esc_html_e( 'No suggested translation is bundled for this language.', 'csi-cookie-consent' ); ?></p>
				<p class="description"><?php esc_html_e( 'Suggested translations are a starting point for the default English texts – have a native speaker review them. The grey text in each field is the default-language text used while the field is empty.', 'csi-cookie-consent' ); ?></p>
				<table class="form-table" role="presentation">
					<?php foreach ( $fields as $key => $field ) : ?>
						<?php
						$value = isset( $s['translations'][ $code ][ $key ] ) ? $s['translations'][ $code ][ $key ] : '';
						$id    = self::id( 'tr', $code, $key );
						self::row( $field[0], self::text_input( self::name( 'translations', $code, $key ), $id, $value, $field[1], $key, $code, wp_strip_all_tags( gdprcn_base_text( $s, $key ) ) ), '', $id );
						?>
					<?php endforeach; ?>
				</table>
			</div>
		<?php endforeach; ?>
		<?php

		// Keep translations for languages that are not currently active.
		foreach ( array_diff_key( $s['translations'], $languages ) as $lang => $texts ) {
			foreach ( $texts as $key => $value ) {
				printf( '<input type="hidden" name="%s" value="%s">', esc_attr( self::name( 'translations', $lang, $key ) ), esc_attr( $value ) );
			}
		}
	}

	/**
	 * Layout & position tab.
	 *
	 * @param array $s Settings.
	 */
	private static function tab_layout( $s ) {
		echo '<h2 class="title">' . esc_html__( 'Banner', 'csi-cookie-consent' ) . '</h2>';
		echo '<table class="form-table" role="presentation">';
		self::row( __( 'Position', 'csi-cookie-consent' ), self::select( $s, 'position' ), '', self::id( 'position' ) );
		self::row( __( 'Box width', 'csi-cookie-consent' ), self::number( $s, 'max_width', 'px' ), __( 'Maximum width of floating boxes and the centred dialog.', 'csi-cookie-consent' ), self::id( 'max_width' ) );
		self::row( __( 'Dim the page', 'csi-cookie-consent' ), self::checkbox( $s, 'overlay', __( 'Darken the page behind the centred dialog', 'csi-cookie-consent' ) ) );
		self::row( __( 'Phones', 'csi-cookie-consent' ), self::checkbox( $s, 'mobile_bar', __( 'Show as a full-width bar at the bottom on small screens', 'csi-cookie-consent' ) ) );
		self::row( __( 'Button order', 'csi-cookie-consent' ), self::select( $s, 'button_order' ), '', self::id( 'button_order' ) );
		echo '</table>';

		echo '<h2 class="title">' . esc_html__( 'Floating "cookie settings" button', 'csi-cookie-consent' ) . '</h2>';
		echo '<table class="form-table" role="presentation">';
		self::row( __( 'Show', 'csi-cookie-consent' ), self::checkbox( $s, 'revisit_button', __( 'Show a floating button after a choice is made, so visitors can change it later', 'csi-cookie-consent' ) ) );
		self::row( __( 'Position', 'csi-cookie-consent' ), self::select( $s, 'revisit_position' ), '', self::id( 'revisit_position' ) );
		self::row( __( 'Style', 'csi-cookie-consent' ), self::select( $s, 'revisit_style' ), '', self::id( 'revisit_style' ) );
		echo '</table>';

		echo '<p class="description">' . wp_kses(
			__( 'Visitors must always be able to change their choice. Without the floating button, use the <code>[gdprcn_settings_link]</code> shortcode, a menu link to <code>#cookie-settings</code>, or any element with the class <code>gdprcn-open-settings</code>.', 'csi-cookie-consent' ),
			array( 'code' => array() )
		) . '</p>';
	}

	/**
	 * Styling tab.
	 *
	 * @param array $s Settings.
	 */
	private static function tab_styling( $s ) {
		$presets = '';
		foreach ( self::presets() as $slug => $preset ) {
			$presets .= sprintf(
				'<button type="button" class="button gdprcn-preset" data-gdprcn-preset="%s"><span class="gdprcn-swatch" style="background:%s;border-color:%s"></span>%s</button> ',
				esc_attr( $slug ),
				esc_attr( $preset['bg_color'] ),
				esc_attr( $preset['accent_color'] ),
				esc_html( $preset['label'] )
			);
		}

		echo '<table class="form-table" role="presentation">';
		self::row( __( 'Colour presets', 'csi-cookie-consent' ), $presets, __( 'Sets the four colours below; adjust them afterwards if you like.', 'csi-cookie-consent' ) );
		self::row( __( 'Background', 'csi-cookie-consent' ), self::color( $s, 'bg_color' ), '', self::id( 'bg_color' ) );
		self::row( __( 'Text', 'csi-cookie-consent' ), self::color( $s, 'text_color' ), '', self::id( 'text_color' ) );
		self::row( __( 'Buttons', 'csi-cookie-consent' ), self::color( $s, 'accent_color' ), '', self::id( 'accent_color' ) );
		self::row( __( 'Button text', 'csi-cookie-consent' ), self::color( $s, 'accent_text_color' ), '', self::id( 'accent_text_color' ) );
		self::row( __( 'Font', 'csi-cookie-consent' ), self::select( $s, 'font_family' ), '', self::id( 'font_family' ) );
		self::row( __( 'Font size', 'csi-cookie-consent' ), self::number( $s, 'font_size', 'px' ), '', self::id( 'font_size' ) );
		self::row( __( 'Corner radius', 'csi-cookie-consent' ), self::number( $s, 'box_radius', 'px' ), __( 'Floating boxes and the preferences dialog.', 'csi-cookie-consent' ), self::id( 'box_radius' ) );
		self::row( __( 'Button radius', 'csi-cookie-consent' ), self::number( $s, 'button_radius', 'px' ), '', self::id( 'button_radius' ) );
		self::row( __( '"Customise" button', 'csi-cookie-consent' ), self::select( $s, 'customize_style' ), __( '"Accept all" and "Reject all" always look the same, as EU regulators require rejecting to be as easy as accepting.', 'csi-cookie-consent' ), self::id( 'customize_style' ) );
		self::row( __( 'Shadow', 'csi-cookie-consent' ), self::checkbox( $s, 'shadow', __( 'Drop shadow (otherwise a thin border)', 'csi-cookie-consent' ) ) );
		self::row( __( 'Animation', 'csi-cookie-consent' ), self::checkbox( $s, 'animation', __( 'Fade in (skipped for visitors who prefer reduced motion)', 'csi-cookie-consent' ) ) );

		$css_field = sprintf(
			'<textarea class="large-text code" rows="6" name="%s" id="gdprcn-custom_css" placeholder=".gdprcn-banner { … }" %s>%s</textarea>',
			esc_attr( self::name( 'custom_css' ) ),
			disabled( ! current_user_can( 'edit_css' ), true, false ),
			esc_textarea( $s['custom_css'] )
		);
		self::row( __( 'Custom CSS', 'csi-cookie-consent' ), $css_field, __( 'Loaded after the plugin styles. Main selectors: .gdprcn-banner, .gdprcn-dialog, .gdprcn-btn, .gdprcn-btn-accept, .gdprcn-btn-reject, .gdprcn-btn-customize, .gdprcn-revisit. Not shown in the preview until saved.', 'csi-cookie-consent' ), 'gdprcn-custom_css' );
		echo '</table>';
	}

	/**
	 * Cookie categories tab.
	 *
	 * @param array $s Settings.
	 */
	private static function tab_categories( $s ) {
		echo '<p>' . wp_kses(
			__( 'Scripts pasted here are only loaded after the visitor consents to that category. To block a script added elsewhere (theme, other plugin), change it to <code>&lt;script type="text/plain" data-cookie-category="analytics"&gt;</code>. For iframes, use <code>data-gdprcn-src</code> instead of <code>src</code> plus <code>data-cookie-category</code>.', 'csi-cookie-consent' ),
			array( 'code' => array() )
		) . '</p>';

		foreach ( gdprcn_categories() as $cat ) {
			$c = $s['categories'][ $cat ];
			echo '<h2 class="title">' . esc_html( $c['label'] ) . ' <code>' . esc_html( $cat ) . '</code></h2>';
			echo '<table class="form-table" role="presentation">';

			if ( 'necessary' !== $cat ) {
				self::row(
					__( 'Used on this site', 'csi-cookie-consent' ),
					sprintf(
						'<label><input type="checkbox" name="%s" value="1" data-gdprcn-category-toggle="%s" %s> %s</label>',
						esc_attr( self::name( 'categories', $cat, 'enabled' ) ),
						esc_attr( $cat ),
						checked( ! empty( $c['enabled'] ), true, false ),
						esc_html__( 'Show this category to visitors', 'csi-cookie-consent' )
					)
				);
			}

			$id = self::id( 'cat', $cat, 'label' );
			self::row( __( 'Name', 'csi-cookie-consent' ), self::text_input( self::name( 'categories', $cat, 'label' ), $id, $c['label'], 'text', "cat_{$cat}_label" ), '', $id );
			$id = self::id( 'cat', $cat, 'description' );
			self::row( __( 'Description', 'csi-cookie-consent' ), self::text_input( self::name( 'categories', $cat, 'description' ), $id, $c['description'], 'textarea', "cat_{$cat}_description" ), '', $id );

			if ( 'necessary' !== $cat ) {
				$id = self::id( 'cat', $cat, 'scripts' );
				self::row(
					__( 'Scripts to load on consent', 'csi-cookie-consent' ),
					sprintf(
						'<textarea class="large-text code" rows="5" id="%s" name="%s" %s placeholder="%s">%s</textarea>',
						esc_attr( $id ),
						esc_attr( self::name( 'categories', $cat, 'scripts' ) ),
						disabled( ! current_user_can( 'unfiltered_html' ), true, false ),
						esc_attr( '<script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXX"></script>' ),
						esc_textarea( $c['scripts'] )
					),
					'',
					$id
				);
			}

			$id = self::id( 'cat', $cat, 'cookies' );
			self::row(
				__( 'Cookies set', 'csi-cookie-consent' ),
				sprintf( '<textarea class="large-text code" rows="3" id="%s" name="%s">%s</textarea>', esc_attr( $id ), esc_attr( self::name( 'categories', $cat, 'cookies' ) ), esc_textarea( $c['cookies'] ) ),
				'necessary' === $cat
					? __( 'One cookie name per line. Listed in the preferences dialog for transparency.', 'csi-cookie-consent' )
					: __( 'One cookie name per line; a trailing * matches a prefix. Listed in the preferences dialog and deleted when consent for this category is withdrawn.', 'csi-cookie-consent' ),
				$id
			);

			echo '</table>';
		}
	}

	/**
	 * Integrations & records tab.
	 *
	 * @param array $s Settings.
	 */
	private static function tab_integrations( $s ) {
		echo '<table class="form-table" role="presentation">';
		self::row( __( 'Google Consent Mode v2', 'csi-cookie-consent' ), self::checkbox( $s, 'consent_mode', __( 'Send consent defaults (denied) and updates to Google tags (gtag.js / GTM)', 'csi-cookie-consent' ) ) );
		self::row( __( 'Consent log', 'csi-cookie-consent' ), self::checkbox( $s, 'log_enabled', __( 'Keep an anonymised record of each consent decision (Tools → Cookie Consent Log)', 'csi-cookie-consent' ) ) );
		self::row( __( 'Keep log records for', 'csi-cookie-consent' ), self::number( $s, 'log_retention_days', __( 'days', 'csi-cookie-consent' ) ), '', self::id( 'log_retention_days' ) );
		echo '</table>';
	}
}
