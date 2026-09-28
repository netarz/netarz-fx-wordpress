# Changelog

All notable changes to the NetArz FX Rates WordPress plugin are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the plugin uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.1.0] - 2026-09-28

### Added

- Three dynamic blocks: `netarz-fx/rate` (one rate), `netarz-fx/rates` (rates table) and
  `netarz-fx/convert` (converter). Built without a build step: `block.json` + a plain script on the
  `wp.*` globals with a `ServerSideRender` preview, drawn in PHP by the same renderer as the shortcodes.
- `[netarz_convert currencies="usd,eur,aed,try" field="sell" amount="1"]`: Toman and currency, both
  ways, as the visitor types. Vanilla JavaScript, no jQuery, no request while typing.
- `change="1"` on `[netarz_rates]`: a "Since yesterday" column from the API's `change_24h_percent`.
- `updated="1"` on `[netarz_rates]` and `[netarz_convert]`: "Updated 14:20", with the delay from
  `meta.delayed_minutes` on the free plan.
- `digits="persian|latin"` on every shortcode, and a Digits control on every block.
- A note for administrators (never visitors) when a currency is Pro-only on the FX API (`pro_only`
  in `GET /currencies`); the catalogue is fetched at most once a day, only when a code is missing.
- Optional WooCommerce line: "about ... Toman" after prices in a foreign-currency store, from the
  cached rates. Off by default, server mode only.
- The widget can show the change column and the updated line.
- `NETARZ_FX_API` can be defined in `wp-config.php` to point a staging or test site at another URL.
- CI on GitHub Actions: `php -l` on PHP 7.4 and 8.0 to 8.4, `node --check`, `block.json` and
  translation checks, the plugin ZIP as an artifact, and WordPress Coding Standards (advisory).
- `build.sh`: a reproducible `netarz-fx-<version>.zip` with its `.sha256`; it refuses to build when
  the version numbers disagree.
- `.phpcs.xml.dist` with the WordPress Coding Standards ruleset used by CI.

### Changed

- The "Rates by NetArz" credit link is off by default (WordPress.org guideline 10).
- In tables, "(per 100 units)" is printed once beside the currency name instead of in every cell.
- Links to netarz.ir from inside the plugin carry `utm_source=wp-plugin` parameters.
- Translations: the `.pot` now includes block titles, descriptions and keywords; Persian updated.

### Fixed

- Browser mode printed Persian digits and Persian currency names on every site:
  `wp_localize_script()` turned `0` into `"0"`, which JavaScript treats as true.

## [1.0.0] - 2026-09-26

### Added

- First release: `[netarz_rate]`, `[netarz_rates]`, a classic widget, the settings page, server and
  browser modes, "Test connection" with the IP NetArz saw, cache with a last-good fallback, and the
  Persian translation.

[Unreleased]: https://github.com/netarz/netarz-fx-wordpress/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/netarz/netarz-fx-wordpress/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/netarz/netarz-fx-wordpress/releases/tag/v1.0.0
