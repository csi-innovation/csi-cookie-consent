<?php
/**
 * Front-end banner, preferences dialog and script gating.
 *
 * Consent is decided entirely in the browser, so the banner works with full
 * page caching: the same HTML is served to everyone (per language) and JS
 * decides what to show and which gated scripts to activate.
 *
 * @package CSI_Cookie_Consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Front-end output.
 */
class GDPRCN_Frontend {

	/**
	 * Google Consent Mode v2 signals controlled by each category.
	 */
	const CONSENT_MODE_MAP = array(
		'preferences' => array( 'functionality_storage', 'personalization_storage' ),
		'analytics'   => array( 'analytics_storage' ),
		'marketing'   => array( 'ad_storage', 'ad_user_data', 'ad_personalization' ),
	);

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'consent_mode_defaults' ), 1 );
		add_action( 'wp_head', array( __CLASS__, 'gated_scripts' ), 99 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render' ), 5 );
		add_shortcode( 'gdprcn_settings_link', array( __CLASS__, 'settings_link_shortcode' ) );
		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'menu_item' ), 10, 2 );
	}

	/**
	 * Append a "Cookie settings" item to the chosen navigation menus.
	 *
	 * Added as a real menu item object (not raw HTML) so themes and page
	 * builders such as Elementor style it like the other items.
	 *
	 * @param array    $items Sorted menu item objects.
	 * @param stdClass $args  wp_nav_menu() arguments.
	 * @return array
	 */
	public static function menu_item( $items, $args ) {
		$s = gdprcn_get_settings();
		if ( 'menu' !== $s['revisit_mode'] || empty( $s['revisit_menus'] ) || ! self::active() ) {
			return $items;
		}

		// wp_nav_menu() may be given a menu (ID, slug, name or term) or only a theme location.
		$menu = ! empty( $args->menu ) ? wp_get_nav_menu_object( $args->menu ) : false;
		if ( ! $menu && ! empty( $args->theme_location ) ) {
			$locations = get_nav_menu_locations();
			$menu      = isset( $locations[ $args->theme_location ] ) ? wp_get_nav_menu_object( $locations[ $args->theme_location ] ) : false;
		}
		if ( ! $menu || ! in_array( (int) $menu->term_id, array_map( 'intval', $s['revisit_menus'] ), true ) ) {
			return $items;
		}

		$order = 0;
		foreach ( $items as $item ) {
			$order = max( $order, (int) $item->menu_order );
		}

		$items[] = (object) array(
			'ID'                    => 0,
			'db_id'                 => 0,
			'menu_item_parent'      => '0',
			'object_id'             => '0',
			'object'                => 'custom',
			'type'                  => 'custom',
			'type_label'            => '',
			'title'                 => gdprcn_text( 'revisit_label' ),
			'url'                   => '#cookie-settings',
			'target'                => '',
			'attr_title'            => '',
			'description'           => '',
			'classes'               => array( 'menu-item', 'menu-item-type-custom', 'gdprcn-menu-item' ),
			'xfn'                   => '',
			'current'               => false,
			'current_item_ancestor' => false,
			'current_item_parent'   => false,
			'menu_order'            => $order + 1,
			'post_parent'           => 0,
			'post_type'             => 'nav_menu_item',
		);

		return $items;
	}

	/**
	 * Whether the notice should run on this request.
	 *
	 * @return bool
	 */
	private static function active() {
		$settings = gdprcn_get_settings();
		$active   = ! empty( $settings['enabled'] ) && ! is_admin() && ! is_feed() && ! wp_doing_ajax() && ! is_embed();

		/**
		 * Filter whether the cookie notice runs on the current request.
		 *
		 * @param bool $active Whether to run.
		 */
		return (bool) apply_filters( 'gdprcn_active', $active );
	}

	/**
	 * Google Consent Mode v2 defaults. Must run before any Google tag.
	 */
	public static function consent_mode_defaults() {
		$settings = gdprcn_get_settings();
		if ( ! self::active() || empty( $settings['consent_mode'] ) ) {
			return;
		}

		$defaults = array( 'security_storage' => 'granted' );
		foreach ( self::CONSENT_MODE_MAP as $signals ) {
			foreach ( $signals as $signal ) {
				$defaults[ $signal ] = 'denied';
			}
		}
		$defaults['wait_for_update'] = 500;

		// Apply a returning visitor's stored choice immediately so tags fire with the right state on first hit.
		$js = sprintf(
			'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}'
			. 'gtag("consent","default",%1$s);'
			. '(function(){try{var m=document.cookie.match(/(?:^|;\s*)%2$s=([^;]+)/);if(!m)return;'
			. 'var c=JSON.parse(decodeURIComponent(m[1]));if(!c||c.r!==%3$d||!c.c)return;var map=%4$s,u={};'
			. 'Object.keys(map).forEach(function(k){map[k].forEach(function(s){u[s]=c.c[k]?"granted":"denied";});});'
			. 'gtag("consent","update",u);}catch(e){}})();',
			wp_json_encode( $defaults ),
			preg_quote( GDPRCN_COOKIE, '/' ),
			(int) $settings['revision'],
			wp_json_encode( self::CONSENT_MODE_MAP )
		);

		wp_print_inline_script_tag( $js, array( 'id' => 'gdprcn-consent-mode' ) );
	}

	/**
	 * Print each category's scripts inside an inert <template>; JS activates them on consent.
	 */
	public static function gated_scripts() {
		if ( ! self::active() ) {
			return;
		}
		$settings = gdprcn_get_settings();

		foreach ( gdprcn_optional_categories() as $cat ) {
			$scripts = trim( $settings['categories'][ $cat ]['scripts'] );
			if ( '' === $scripts ) {
				continue;
			}
			// Raw output is intended: only users with unfiltered_html can save these.
			printf( "<template data-gdprcn-category=\"%s\">\n%s\n</template>\n", esc_attr( $cat ), $scripts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Enqueue CSS and JS, and pass config.
	 */
	public static function enqueue() {
		if ( ! self::active() ) {
			return;
		}
		$settings = gdprcn_get_settings();

		wp_enqueue_style( 'gdprcn', GDPRCN_URL . 'assets/css/gdprcn.css', array(), GDPRCN_VERSION );
		if ( '' !== trim( $settings['custom_css'] ) ) {
			wp_add_inline_style( 'gdprcn', $settings['custom_css'] );
		}

		wp_enqueue_script( 'gdprcn', GDPRCN_URL . 'assets/js/gdprcn.js', array(), GDPRCN_VERSION, true );

		$cookies = array();
		foreach ( gdprcn_optional_categories() as $cat ) {
			$cookies[ $cat ] = self::cookie_list( $settings['categories'][ $cat ]['cookies'] );
		}

		$config = array(
			'cookieName'       => GDPRCN_COOKIE,
			'cookiePath'       => COOKIEPATH ? COOKIEPATH : '/',
			'secure'           => is_ssl(),
			'revision'         => (int) $settings['revision'],
			'expiryDays'       => (int) $settings['expiry_days'],
			'categories'       => gdprcn_optional_categories(),
			'cookies'          => $cookies,
			'consentMode'      => ! empty( $settings['consent_mode'] ),
			'consentModeMap'   => self::CONSENT_MODE_MAP,
			'reloadOnWithdraw' => ! empty( $settings['reload_on_withdraw'] ),
			'logUrl'           => ! empty( $settings['log_enabled'] ) ? esc_url_raw( rest_url( 'gdprcn/v1/consent' ) ) : '',
		);

		wp_add_inline_script( 'gdprcn', 'window.gdprcnConfig=' . wp_json_encode( $config ) . ';', 'before' );
	}

	/**
	 * Split a newline-separated cookie list.
	 *
	 * @param string $text Raw list.
	 * @return string[]
	 */
	public static function cookie_list( $text ) {
		return array_values( array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', (string) $text ) ) ) );
	}

	/**
	 * [gdprcn_settings_link text="Cookie settings"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function settings_link_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'text' => gdprcn_text( 'revisit_label' ) ), $atts, 'gdprcn_settings_link' );

		return sprintf( '<a href="#cookie-settings" class="gdprcn-open-settings">%s</a>', esc_html( $atts['text'] ) );
	}

	/**
	 * Print the markup in the footer.
	 */
	public static function render() {
		if ( ! self::active() ) {
			return;
		}
		echo self::markup( gdprcn_get_settings(), gdprcn_current_language() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in markup().
	}

	/**
	 * Inline CSS custom properties for the style settings.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function style_vars( $s ) {
		$fonts = array(
			'inherit' => 'inherit',
			'system'  => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
			'serif'   => 'Georgia, "Times New Roman", serif',
		);

		return sprintf(
			'--gdprcn-bg:%s;--gdprcn-text:%s;--gdprcn-accent:%s;--gdprcn-accent-text:%s;--gdprcn-font:%s;--gdprcn-font-size:%dpx;--gdprcn-radius:%dpx;--gdprcn-btn-radius:%dpx;--gdprcn-max-width:%dpx',
			$s['bg_color'],
			$s['text_color'],
			$s['accent_color'],
			$s['accent_text_color'],
			isset( $fonts[ $s['font_family'] ] ) ? $fonts[ $s['font_family'] ] : 'inherit',
			(int) $s['font_size'],
			(int) $s['box_radius'],
			(int) $s['button_radius'],
			(int) $s['max_width']
		);
	}

	/**
	 * Wrapper classes for the style and location settings.
	 *
	 * @param array $s Settings.
	 * @return string
	 */
	public static function root_classes( $s ) {
		$classes = array(
			'gdprcn-root',
			'gdprcn-pos-' . $s['position'],
			'gdprcn-customize-' . $s['customize_style'],
			'gdprcn-order-' . $s['button_order'],
			'gdprcn-revisit-' . $s['revisit_position'],
			'gdprcn-reject-' . $s['reject_button'],
		);
		foreach ( array( 'mobile_bar', 'overlay', 'shadow', 'animation' ) as $flag ) {
			$classes[] = 'gdprcn-' . ( empty( $s[ $flag ] ) ? 'no-' : '' ) . str_replace( '_', '-', $flag );
		}

		return implode( ' ', $classes );
	}

	/**
	 * Banner, preferences dialog and floating button markup.
	 *
	 * @param array       $s       Settings.
	 * @param string|null $lang    Language code, null for the default language.
	 * @param bool        $preview Admin preview: banner and button visible, no IDs that clash with the page.
	 * @return string
	 */
	public static function markup( $s, $lang = null, $preview = false ) {
		$t = function ( $key ) use ( $s, $lang ) {
			return gdprcn_text( $key, $lang, $s );
		};

		$inline     = gdprcn_allowed_inline_html();
		$policy_url = gdprcn_policy_url( $s, $lang );
		// The preview renders every category so the settings form can toggle them live.
		$cats       = $preview ? gdprcn_categories() : array_merge( array( 'necessary' ), gdprcn_optional_categories() );
		$hidden     = $preview ? '' : ' hidden';

		ob_start();
		?>
		<div id="<?php echo $preview ? 'gdprcn-preview-root' : 'gdprcn'; ?>" class="<?php echo esc_attr( self::root_classes( $s ) . ( $preview ? ' gdprcn-preview' : '' ) ); ?>" style="<?php echo esc_attr( self::style_vars( $s ) ); ?>">
			<div id="gdprcn-banner" class="gdprcn-banner" role="region" aria-labelledby="gdprcn-banner-title"<?php echo $hidden; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<div class="gdprcn-banner-inner">
					<div class="gdprcn-banner-text">
						<p class="gdprcn-title" id="gdprcn-banner-title" data-gdprcn-text="title"><?php echo esc_html( $t( 'title' ) ); ?></p>
						<p>
							<span data-gdprcn-text="message"><?php echo wp_kses( $t( 'message' ), $inline ); ?></span>
							<?php if ( $policy_url || $preview ) : ?>
								<a href="<?php echo esc_url( $policy_url ? $policy_url : '#' ); ?>" data-gdprcn-text="policy_label"><?php echo esc_html( $t( 'policy_label' ) ); ?></a>
							<?php endif; ?>
						</p>
					</div>
					<div class="gdprcn-actions">
						<button type="button" class="gdprcn-btn gdprcn-btn-customize" data-gdprcn-action="customize" data-gdprcn-text="customize_label"><?php echo esc_html( $t( 'customize_label' ) ); ?></button>
						<button type="button" class="gdprcn-btn gdprcn-btn-reject" data-gdprcn-action="reject" data-gdprcn-text="reject_label"><?php echo esc_html( $t( 'reject_label' ) ); ?></button>
						<button type="button" class="gdprcn-btn gdprcn-btn-accept" data-gdprcn-action="accept" data-gdprcn-text="accept_label"><?php echo esc_html( $t( 'accept_label' ) ); ?></button>
					</div>
				</div>
			</div>

			<div id="gdprcn-modal" class="gdprcn-modal" hidden>
				<div class="gdprcn-backdrop" data-gdprcn-action="close"></div>
				<div class="gdprcn-dialog" role="dialog" aria-modal="true" aria-labelledby="gdprcn-prefs-title" tabindex="-1">
					<button type="button" class="gdprcn-close" data-gdprcn-action="close" aria-label="<?php echo esc_attr( $t( 'close_label' ) ); ?>">&times;</button>
					<h2 class="gdprcn-title" id="gdprcn-prefs-title" data-gdprcn-text="prefs_title"><?php echo esc_html( $t( 'prefs_title' ) ); ?></h2>
					<p data-gdprcn-text="prefs_intro"><?php echo wp_kses( $t( 'prefs_intro' ), $inline ); ?></p>

					<div class="gdprcn-cats">
						<?php foreach ( $cats as $cat ) : ?>
							<?php
							$cookies = self::cookie_list( $s['categories'][ $cat ]['cookies'] );
							if ( 'necessary' === $cat ) {
								array_unshift( $cookies, GDPRCN_COOKIE );
							}
							$id = 'gdprcn-cat-' . $cat . ( $preview ? '-preview' : '' );
							?>
							<div class="gdprcn-cat" data-gdprcn-cat="<?php echo esc_attr( $cat ); ?>"<?php echo empty( $s['categories'][ $cat ]['enabled'] ) ? ' hidden' : ''; ?>>
								<div class="gdprcn-cat-head">
									<label class="gdprcn-cat-label" for="<?php echo esc_attr( $id ); ?>" data-gdprcn-text="cat_<?php echo esc_attr( $cat ); ?>_label"><?php echo esc_html( $t( "cat_{$cat}_label" ) ); ?></label>
									<span class="gdprcn-switch">
										<?php if ( 'necessary' === $cat ) : ?>
											<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" checked disabled>
										<?php else : ?>
											<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" data-gdprcn-category="<?php echo esc_attr( $cat ); ?>">
										<?php endif; ?>
										<span class="gdprcn-slider" aria-hidden="true"></span>
									</span>
								</div>
								<p class="gdprcn-cat-desc" data-gdprcn-text="cat_<?php echo esc_attr( $cat ); ?>_description"><?php echo wp_kses( $t( "cat_{$cat}_description" ), $inline ); ?></p>
								<?php if ( $cookies ) : ?>
									<details class="gdprcn-cookies">
										<summary data-gdprcn-text="cookies_used_label"><?php echo esc_html( $t( 'cookies_used_label' ) ); ?></summary>
										<p><code><?php echo implode( '</code>, <code>', array_map( 'esc_html', $cookies ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by array_map. ?></code></p>
									</details>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>

					<p class="gdprcn-consent-id" hidden><span data-gdprcn-text="consent_id_label"><?php echo esc_html( $t( 'consent_id_label' ) ); ?></span> <code></code></p>

					<div class="gdprcn-actions">
						<button type="button" class="gdprcn-btn gdprcn-btn-reject" data-gdprcn-action="reject" data-gdprcn-text="reject_label"><?php echo esc_html( $t( 'reject_label' ) ); ?></button>
						<button type="button" class="gdprcn-btn gdprcn-btn-save" data-gdprcn-action="save" data-gdprcn-text="save_label"><?php echo esc_html( $t( 'save_label' ) ); ?></button>
						<button type="button" class="gdprcn-btn gdprcn-btn-accept" data-gdprcn-action="accept" data-gdprcn-text="accept_label"><?php echo esc_html( $t( 'accept_label' ) ); ?></button>
					</div>
				</div>
			</div>

			<?php if ( 'button' === $s['revisit_mode'] || $preview ) : ?>
				<button type="button" id="gdprcn-revisit" class="gdprcn-revisit gdprcn-revisit-style-<?php echo esc_attr( $s['revisit_style'] ); ?> gdprcn-open-settings" aria-label="<?php echo esc_attr( $t( 'revisit_label' ) ); ?>" title="<?php echo esc_attr( $t( 'revisit_label' ) ); ?>"<?php echo $preview && 'button' !== $s['revisit_mode'] ? ' hidden' : $hidden; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5Zm-3.5 7a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm6 5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm-7 2a1 1 0 1 1 0 2 1 1 0 0 1 0-2Z"/></svg>
					<span class="gdprcn-revisit-label" data-gdprcn-text="revisit_label"><?php echo esc_html( $t( 'revisit_label' ) ); ?></span>
				</button>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
