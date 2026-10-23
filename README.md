# CSI Cookie Consent

WordPress plugin for GDPR / ePrivacy cookie consent: a banner with equal "Accept all" / "Reject all" buttons, per-category preferences, script blocking until consent, Google Consent Mode v2, an anonymised consent log, per-language texts (Polylang / WPML) and a settings page with a live preview.

`readme.txt` is the user-facing documentation and changelog. This file covers development and releases.

## Install on a site

Download `csi-cookie-consent.zip` from the [latest release](https://github.com/csi-innovation/csi-cookie-consent/releases/latest) and upload it under **Plugins → Add New → Upload**, or over SSH:

```bash
wp plugin install https://github.com/csi-innovation/csi-cookie-consent/releases/latest/download/csi-cookie-consent.zip
```

After that, the site finds new versions on its own.

## How updates reach sites

- The plugin header sets `Update URI: https://github.com/csi-innovation/csi-cookie-consent`. Because of that, WordPress never matches the plugin against wordpress.org (a plugin there once overwrote an earlier version that shared its folder name).
- Instead WordPress asks the `update_plugins_github.com` filter. `includes/class-gdprcn-updater.php` answers it from `update.json`, which every release carries, at `releases/latest/download/update.json`. The result is cached for 12 hours; **Dashboard → Updates → Check again** forces a fresh check.
- Only packages under this repository's `releases/download/` are accepted.
- Sites see the update in WP Admin like any other plugin, with "View details" showing the changelog, and can switch on auto-updates for it.
- **Inactive copies don't check for updates** (WordPress doesn't run inactive plugins' code). Update those with `wp plugin install <zip url> --force`.

## Releasing a new version

1. Make the changes on a branch and open a pull request. CI lints PHP on 7.4 and 8.3, checks the JS, checks the version numbers and builds the zip.
2. Bump the version in **three places**: the `Version:` header and `GDPRCN_VERSION` in `csi-cookie-consent.php`, and `Stable tag:` in `readme.txt`. Add a `= x.y.z =` entry under `== Changelog ==`. `bin/check-version.sh` fails the build if these disagree.
3. If the release changes how settings are stored, add a migration to `GDPRCN_Upgrade::migrations()` in `includes/class-gdprcn-upgrade.php`.
4. Merge, then tag and push:
   ```bash
   git tag v1.2.1
   git push origin v1.2.1
   ```
   The **Release** workflow builds `csi-cookie-consent.zip` and `update.json` and publishes the GitHub release. Within 12 hours sites show the update.
5. Roll out: update a staging site or one low-traffic site first, then the rest.

Use version numbers as x.y.z: patch (1.2.1) for fixes, minor (1.3.0) for features, major (2.0.0) for changes that break existing setups.

## Settings between sites

- **Settings → Cookie Notice → Import / export** downloads or uploads all settings as JSON.
- Imports always keep the target site's privacy policy page and consent revision, and keep its tracking scripts unless you tick "Also import tracking scripts".
- WP-CLI equivalents (run with `--user=<admin>` so scripts and custom CSS are allowed):

```bash
wp cookie-consent export --file=settings.json
wp cookie-consent import settings.json --user=admin
wp cookie-consent status
wp cookie-consent reset-consents
```

`reset-consents` asks every visitor for consent again. Use it after adding cookies or changing purposes, not as part of a code release.

## Building locally

```bash
bin/build.sh          # dist/csi-cookie-consent.zip and dist/update.json
bin/check-version.sh  # version consistency
```

Needs bash, PHP, rsync and zip.

## Code map

| Path | What it does |
| --- | --- |
| `csi-cookie-consent.php` | Header, constants, bootstrap |
| `includes/functions.php` | Defaults, settings, languages, `gdprcn_text()`, consent helpers |
| `includes/class-gdprcn-settings.php` | Settings page, sanitising |
| `includes/class-gdprcn-frontend.php` | Banner markup, script gating, Consent Mode |
| `includes/class-gdprcn-consent-log.php` | Consent log table, REST endpoint, CSV export |
| `includes/class-gdprcn-transfer.php` | Settings export / import |
| `includes/class-gdprcn-upgrade.php` | Settings migrations |
| `includes/class-gdprcn-updater.php` | Updates from GitHub releases |
| `includes/class-gdprcn-cli.php` | `wp cookie-consent` commands |
| `includes/starter-translations.php` | Suggested translations |
| `assets/` | Front-end and admin CSS / JS |
| `bin/` | Build and release scripts (not shipped) |
