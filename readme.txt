=== CSI Cookie Consent ===
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPL-2.0-or-later

Lightweight GDPR / ePrivacy cookie consent banner with granular categories, prior script blocking, Google Consent Mode v2 and an anonymised consent log.

== Description ==

* Banner with equally prominent "Accept all" and "Reject all" buttons, plus "Customise".
* Preferences dialog with per-category toggles (Necessary, Preferences, Analytics, Marketing) and the cookies each one sets.
* Nothing non-essential runs before consent: scripts are held in inert templates and only injected after the visitor opts in.
* Withdrawing consent deletes that category's cookies and (optionally) reloads the page.
* Google Consent Mode v2 (defaults to denied, updates on choice, restores returning visitors' choice before tags fire).
* Consent log under Tools → Cookie Consent Log: random consent ID, choice, date, page, user agent and an HMAC of the truncated IP. CSV export. Automatic purge after the retention period.
* "Reset all stored consents" re-prompts every visitor after you add cookies or change purposes.
* Works with full-page caching: the decision is made in the browser.
* Accessible: keyboard operable, focus-trapped dialog, Esc to close, respects reduced motion.
* Suggested text added to Settings → Privacy → Policy Guide.

= Settings (Settings → Cookie Notice) =

* Live preview at the top of the page, updated as you edit, with a Banner / Preferences view and a language switch.
* Text: every visitor-facing text, including button labels and screen-reader labels.
* Translations: works with Polylang or WPML (or a manual list of language codes). Each language has its own set of texts; empty fields fall back to the default language. The policy link uses the translated policy page automatically. Suggested translations are bundled for bg, de, el, es, fr, it, nl, pl and sr (Cyrillic) – have a native speaker review them.
* Layout & position: full-width bar (top/bottom), floating box (four corners or bottom centre) or centred dialog; box width; dimmed page; full-width bar on phones; button order; "Reject all" on the banner, in the preferences only, or hidden; how visitors reopen the preferences: floating button (position, style), a menu item in chosen menus, or neither.
* Styling: colour presets, four colours, font, font size, corner and button radius, "Customise" button style, shadow, fade-in, and custom CSS.

== Changelog ==

= 1.3.0 =
* "Reject all" can be shown on the banner and in the preferences (default), hidden on the banner only, or hidden everywhere. The settings page warns that EU regulators expect rejecting to be as easy as accepting.
* Visitors can reopen the cookie preferences through a floating button (as before), a "Cookie settings" item added to chosen navigation menus (works with Elementor menus and one menu per Polylang language), or neither.
* Existing sites keep their floating-button setting when they update.

= 1.2.0 =
* Updates now come from the plugin's GitHub releases (csi-innovation/csi-cookie-consent) and appear in WP Admin like any other plugin update, including "View details" and optional auto-updates.
* Settings export / import (Settings → Cookie Notice → Import / export). Imports keep the site's privacy page, consent revision and, by default, its tracking scripts.
* WP-CLI commands: wp cookie-consent export, import, status and reset-consents.
* Stored settings are now versioned, so future releases can migrate them automatically.

= 1.1.0 =
* Renamed to CSI Cookie Consent (folder csi-cookie-consent) with an Update URI, so WordPress never replaces it with a wordpress.org plugin of the same name.
* New tabbed settings page with live preview.
* Per-language texts with Polylang / WPML support and bundled starter translations.
* More positions and styling options; custom CSS.
* The policy link is hidden while the selected page is not published.

= 1.0.0 =
* First release.

== Setup ==

1. Upload the `csi-cookie-consent` folder to `/wp-content/plugins/` (or upload the zip under Plugins → Add New) and activate.
2. Go to Settings → Cookie Notice. Choose your privacy policy page and review the text.
3. Paste tracking snippets (GA4, Meta Pixel, Hotjar…) into the matching category's "Scripts to load on consent" box, and remove them from your theme / other plugins.
4. Make sure visitors can reopen the preferences: keep the floating button on, or add a menu link to `#cookie-settings`, or use `[gdprcn_settings_link]`.

== Blocking scripts added elsewhere ==

    <script type="text/plain" data-cookie-category="analytics" src="https://example.com/tracker.js"></script>

    <script type="text/plain" data-cookie-category="marketing">
      /* inline snippet */
    </script>

    <iframe data-gdprcn-src="https://www.youtube-nocookie.com/embed/ID" data-cookie-category="marketing"></iframe>

For a module script add `data-gdprcn-type="module"`.

== Developer API ==

PHP (not reliable on page-cached pages):

    if ( gdprcn_has_consent( 'analytics' ) ) { ... }
    $consent = gdprcn_get_consent(); // [ 'necessary' => true, 'analytics' => false, ... ] or null

JavaScript:

    GDPRCN.hasConsent('marketing');
    GDPRCN.getConsent();
    GDPRCN.open();
    GDPRCN.reset();
    document.addEventListener('gdprcn:consent', function (e) { console.log(e.detail.categories, e.detail.action); });

Filter `gdprcn_active` (bool) to disable the notice on specific requests.

When Consent Mode is on, a `gdprcn_consent_update` event is pushed to the dataLayer after each choice, for GTM triggers.

== Notes ==

* The consent endpoint (`POST /wp-json/gdprcn/v1/consent`) is public by design, since visitors are anonymous and pages may be cached. Input is strictly validated; if you see abuse, rate-limit it at your firewall/CDN.
* This plugin is a tool, not legal advice. You still need an accurate cookie policy listing your cookies, purposes, providers and durations.
