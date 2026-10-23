<?php
/**
 * Shared helpers and the public PHP API.
 *
 * @package CSI_Cookie_Consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Consent categories, in display order. "necessary" is always granted.
 *
 * @return string[]
 */
function gdprcn_categories() {
	return array( 'necessary', 'preferences', 'analytics', 'marketing' );
}

/**
 * Default settings.
 *
 * @return array
 */
function gdprcn_defaults() {
	return array(
		'enabled'            => 1,
		'title'              => __( 'We value your privacy', 'csi-cookie-consent' ),
		'message'            => __( 'We use cookies to keep this site working, and – with your permission – to understand how it is used and to personalise content and ads. You can accept all, reject all non-essential cookies, or choose which ones to allow. You can change your mind at any time.', 'csi-cookie-consent' ),
		'policy_page'        => (int) get_option( 'wp_page_for_privacy_policy' ),
		'policy_label'       => __( 'Privacy & Cookie Policy', 'csi-cookie-consent' ),
		'accept_label'       => __( 'Accept all', 'csi-cookie-consent' ),
		'reject_label'       => __( 'Reject all', 'csi-cookie-consent' ),
		'customize_label'    => __( 'Customise', 'csi-cookie-consent' ),
		'save_label'         => __( 'Save my choices', 'csi-cookie-consent' ),
		'prefs_title'        => __( 'Cookie preferences', 'csi-cookie-consent' ),
		'prefs_intro'        => __( 'Choose which categories of cookies you allow. Strictly necessary cookies are always on because the site cannot work without them.', 'csi-cookie-consent' ),
		'revisit_label'      => __( 'Cookie settings', 'csi-cookie-consent' ),
		'close_label'        => __( 'Close', 'csi-cookie-consent' ),
		'cookies_used_label' => __( 'Cookies used', 'csi-cookie-consent' ),
		'consent_id_label'   => __( 'Your consent ID:', 'csi-cookie-consent' ),

		// Location.
		'position'           => 'bottom',
		'mobile_bar'         => 1,
		'overlay'            => 1,
		'reject_button'      => 'both',
		'revisit_mode'       => 'button',
		'revisit_menus'      => array(),
		'revisit_position'   => 'left',
		'revisit_style'      => 'icon',

		// Styling.
		'bg_color'           => '#ffffff',
		'text_color'         => '#1d2327',
		'accent_color'       => '#2271b1',
		'accent_text_color'  => '#ffffff',
		'font_family'        => 'inherit',
		'font_size'          => 15,
		'box_radius'         => 8,
		'button_radius'      => 6,
		'max_width'          => 440,
		'shadow'             => 1,
		'animation'          => 1,
		'customize_style'    => 'outline',
		'button_order'       => 'accept-last',
		'custom_css'         => '',

		// Behaviour.
		'reload_on_withdraw' => 1,
		'expiry_days'        => 180,
		'revision'           => 1,
		'consent_mode'       => 0,
		'log_enabled'        => 1,
		'log_retention_days' => 365,

		// Translations: [ language code => [ text key => text ] ].
		'extra_languages'    => '',
		'translations'       => array(),

		'categories'         => array(
			'necessary'   => array(
				'enabled'     => 1,
				'label'       => __( 'Strictly necessary', 'csi-cookie-consent' ),
				'description' => __( 'Required for the site to function, e.g. security, load balancing, remembering your cookie choices and keeping you logged in. These cannot be switched off.', 'csi-cookie-consent' ),
				'scripts'     => '',
				'cookies'     => '',
			),
			'preferences' => array(
				'enabled'     => 0,
				'label'       => __( 'Preferences', 'csi-cookie-consent' ),
				'description' => __( 'Remember choices you make, such as language or region, to give you a more personal experience.', 'csi-cookie-consent' ),
				'scripts'     => '',
				'cookies'     => '',
			),
			'analytics'   => array(
				'enabled'     => 1,
				'label'       => __( 'Analytics', 'csi-cookie-consent' ),
				'description' => __( 'Help us understand how visitors use the site so we can improve it. Information is aggregated.', 'csi-cookie-consent' ),
				'scripts'     => '',
				'cookies'     => "_ga\n_ga_*\n_gid\n_gat*\n_hj*\n_clck\n_clsk",
			),
			'marketing'   => array(
				'enabled'     => 1,
				'label'       => __( 'Marketing', 'csi-cookie-consent' ),
				'description' => __( 'Used by us and our advertising partners to show you relevant ads and measure their effectiveness.', 'csi-cookie-consent' ),
				'scripts'     => '',
				'cookies'     => "_fbp\n_fbc\n_gcl_*\n_uetsid\n_uetvid\nli_*",
			),
		),
	);
}

/**
 * Saved settings merged over defaults.
 *
 * @return array
 */
function gdprcn_get_settings() {
	$saved    = get_option( GDPRCN_OPTION, array() );
	$defaults = gdprcn_defaults();
	$settings = wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );

	foreach ( gdprcn_categories() as $cat ) {
		$settings['categories'][ $cat ] = wp_parse_args(
			isset( $settings['categories'][ $cat ] ) ? (array) $settings['categories'][ $cat ] : array(),
			$defaults['categories'][ $cat ]
		);
	}
	$settings['categories']['necessary']['enabled'] = 1;
	$settings['translations']                       = is_array( $settings['translations'] ) ? $settings['translations'] : array();

	return $settings;
}

/**
 * Categories that are switched on in settings, excluding "necessary".
 *
 * @return string[]
 */
function gdprcn_optional_categories() {
	$settings = gdprcn_get_settings();

	return array_values(
		array_filter(
			gdprcn_categories(),
			function ( $cat ) use ( $settings ) {
				return 'necessary' !== $cat && ! empty( $settings['categories'][ $cat ]['enabled'] );
			}
		)
	);
}

/* Translations ------------------------------------------------------------ */

/**
 * Every visitor-facing text, as key => [ label, field type ].
 *
 * Category texts use the keys "cat_{category}_label" / "cat_{category}_description".
 *
 * @return array
 */
function gdprcn_text_fields() {
	$fields = array(
		'title'              => array( __( 'Banner title', 'csi-cookie-consent' ), 'text' ),
		'message'            => array( __( 'Banner message', 'csi-cookie-consent' ), 'textarea' ),
		'policy_label'       => array( __( 'Policy link text', 'csi-cookie-consent' ), 'text' ),
		'accept_label'       => array( __( '"Accept all" button', 'csi-cookie-consent' ), 'text' ),
		'reject_label'       => array( __( '"Reject all" button', 'csi-cookie-consent' ), 'text' ),
		'customize_label'    => array( __( '"Customise" button', 'csi-cookie-consent' ), 'text' ),
		'save_label'         => array( __( '"Save" button', 'csi-cookie-consent' ), 'text' ),
		'prefs_title'        => array( __( 'Preferences title', 'csi-cookie-consent' ), 'text' ),
		'prefs_intro'        => array( __( 'Preferences intro', 'csi-cookie-consent' ), 'textarea' ),
		'revisit_label'      => array( __( '"Cookie settings" label', 'csi-cookie-consent' ), 'text' ),
		'close_label'        => array( __( '"Close" label (screen readers)', 'csi-cookie-consent' ), 'text' ),
		'cookies_used_label' => array( __( '"Cookies used" label', 'csi-cookie-consent' ), 'text' ),
		'consent_id_label'   => array( __( '"Your consent ID" label', 'csi-cookie-consent' ), 'text' ),
	);

	foreach ( gdprcn_categories() as $cat ) {
		$fields[ "cat_{$cat}_label" ] = array(
			/* translators: %s: category key. */
			sprintf( __( 'Category "%s" – name', 'csi-cookie-consent' ), $cat ),
			'text',
		);
		$fields[ "cat_{$cat}_description" ] = array(
			/* translators: %s: category key. */
			sprintf( __( 'Category "%s" – description', 'csi-cookie-consent' ), $cat ),
			'textarea',
		);
	}

	return $fields;
}

/**
 * Inline HTML allowed in visitor-facing text.
 *
 * @return array
 */
function gdprcn_allowed_inline_html() {
	return array(
		'a'      => array(
			'href'   => true,
			'target' => true,
			'rel'    => true,
		),
		'strong' => array(),
		'em'     => array(),
		'br'     => array(),
	);
}

/**
 * The default-language value of a text key.
 *
 * @param array  $settings Settings.
 * @param string $key      Text key.
 * @return string
 */
function gdprcn_base_text( $settings, $key ) {
	if ( preg_match( '/^cat_([a-z]+)_(label|description)$/', $key, $m ) ) {
		return isset( $settings['categories'][ $m[1] ][ $m[2] ] ) ? (string) $settings['categories'][ $m[1] ][ $m[2] ] : '';
	}

	return isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
}

/**
 * A visitor-facing text in the given (or current) language, falling back to the default language.
 *
 * @param string      $key      Text key from gdprcn_text_fields().
 * @param string|null $lang     Language code, or null for the current language.
 * @param array|null  $settings Settings, to avoid re-reading.
 * @return string
 */
function gdprcn_text( $key, $lang = null, $settings = null ) {
	$settings = $settings ? $settings : gdprcn_get_settings();
	$lang     = null === $lang ? gdprcn_current_language() : $lang;

	if ( $lang && ! empty( $settings['translations'][ $lang ][ $key ] ) ) {
		$text = $settings['translations'][ $lang ][ $key ];
	} else {
		$text = gdprcn_base_text( $settings, $key );
	}

	/**
	 * Filter a visitor-facing text.
	 *
	 * @param string      $text Text.
	 * @param string      $key  Text key.
	 * @param string|null $lang Language code, null for the default language.
	 */
	return (string) apply_filters( 'gdprcn_text', $text, $key, $lang );
}

/**
 * Which multilingual plugin, if any, provides the language list.
 *
 * @return string "polylang", "wpml" or "" (manual list).
 */
function gdprcn_language_source() {
	if ( function_exists( 'pll_languages_list' ) ) {
		return 'polylang';
	}
	if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
		return 'wpml';
	}

	return '';
}

/**
 * Translatable languages (excluding the default language), as code => [ name, locale ].
 *
 * @return array
 */
function gdprcn_languages() {
	$languages = array();

	switch ( gdprcn_language_source() ) {
		case 'polylang':
			$default = pll_default_language();
			foreach ( pll_languages_list( array( 'fields' => '' ) ) as $lang ) {
				if ( $lang->slug !== $default ) {
					$languages[ $lang->slug ] = array( $lang->name, $lang->locale );
				}
			}
			break;

		case 'wpml':
			$default = apply_filters( 'wpml_default_language', null );
			foreach ( (array) apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) ) as $code => $lang ) {
				if ( $code !== $default ) {
					$languages[ $code ] = array( $lang['native_name'], isset( $lang['default_locale'] ) ? $lang['default_locale'] : $code );
				}
			}
			break;

		default:
			$settings = gdprcn_get_settings();
			foreach ( preg_split( '/[\s,]+/', $settings['extra_languages'], -1, PREG_SPLIT_NO_EMPTY ) as $code ) {
				$languages[ $code ] = array( $code, $code );
			}
	}

	return $languages;
}

/**
 * The visitor's language code if it is a translated (non-default) language, else null.
 *
 * @return string|null
 */
function gdprcn_current_language() {
	$languages = gdprcn_languages();
	if ( ! $languages ) {
		return null;
	}

	switch ( gdprcn_language_source() ) {
		case 'polylang':
			$current = pll_current_language();
			break;
		case 'wpml':
			$current = apply_filters( 'wpml_current_language', null );
			break;
		default:
			// Manual list: match the site locale exactly (fr_CA) or by language (fr).
			$locale  = determine_locale();
			$current = isset( $languages[ $locale ] ) ? $locale : strtok( $locale, '_' );
	}

	return $current && isset( $languages[ $current ] ) ? $current : null;
}

/**
 * Privacy policy URL, using the translated page where the multilingual plugin has one.
 *
 * @param array       $settings Settings.
 * @param string|null $lang     Language code.
 * @return string
 */
function gdprcn_policy_url( $settings, $lang = null ) {
	$page_id = (int) $settings['policy_page'];
	if ( ! $page_id ) {
		return '';
	}

	if ( $lang && 'polylang' === gdprcn_language_source() && function_exists( 'pll_get_post' ) ) {
		$translated = pll_get_post( $page_id, $lang );
		$page_id    = $translated ? $translated : $page_id;
	} elseif ( $lang && 'wpml' === gdprcn_language_source() ) {
		$page_id = (int) apply_filters( 'wpml_object_id', $page_id, 'page', true, $lang );
	}

	return 'publish' === get_post_status( $page_id ) ? (string) get_permalink( $page_id ) : '';
}

/* Consent ------------------------------------------------------------------ */

/**
 * The visitor's current consent, read from the consent cookie.
 *
 * Note: on fully page-cached sites, prefer the JS API (window.GDPRCN) or the
 * "gdprcn:consent" event, since cached HTML is shared between visitors.
 *
 * @return array|null Map of category => bool, or null if no valid consent.
 */
function gdprcn_get_consent() {
	if ( empty( $_COOKIE[ GDPRCN_COOKIE ] ) ) {
		return null;
	}

	$data = json_decode( wp_unslash( $_COOKIE[ GDPRCN_COOKIE ] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded and validated below.
	if ( ! is_array( $data ) || empty( $data['c'] ) || ! is_array( $data['c'] ) ) {
		return null;
	}

	$settings = gdprcn_get_settings();
	if ( (int) ( $data['r'] ?? 0 ) !== (int) $settings['revision'] ) {
		return null;
	}

	$consent = array( 'necessary' => true );
	foreach ( gdprcn_optional_categories() as $cat ) {
		$consent[ $cat ] = ! empty( $data['c'][ $cat ] );
	}

	return $consent;
}

/**
 * Whether the visitor has consented to a category.
 *
 * @param string $category One of gdprcn_categories().
 * @return bool
 */
function gdprcn_has_consent( $category ) {
	if ( 'necessary' === $category ) {
		return true;
	}
	$consent = gdprcn_get_consent();

	return ! empty( $consent[ $category ] );
}
