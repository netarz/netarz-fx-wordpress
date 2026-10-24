# Changelog

All notable changes to the NetArz FX Rates WordPress plugin are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the plugin uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [2.1.0] - 2026-10-24

### Added

- Automatic WooCommerce prices (`Netarz_FX_Woo_Pricing`): a product or variation gets a currency and a regular and
  sale price in it (`_netarz_fx_currency`, `_netarz_fx_regular`, `_netarz_fx_sale`, optional `_netarz_fx_fee`).
  Price = amount x rate x (1 + fee / 100) + fixed Toman fee, rounded to a step (1 to 100,000 Toman, up or nearest),
  in the store's unit (IRT, IRR, IRHT, IRHR). Written into the normal WooCommerce regular and sale prices.
- A recurring WP-Cron event every 1, 3, 6, 12 or 24 hours, in batches of 100 products a minute apart; "Update prices
  now" in the settings; the converted price applies at once when an administrator saves the product.
- A safety catch: a product whose rate moved more than `woo_max_jump` percent (default 20) since its last update keeps
  its price and is listed as held until an administrator saves it.
- Declared compatible with WooCommerce HPOS.

## [2.0.3] - 2026-10-21

### Changed

- Readme: NetArz's keyless public board (`/api/markets/v1/public/board`) is now open, so the "coming soon" and
  "a key is needed for now" wording is gone; a key is optional and only adds other symbols and charts.

## [2.0.2] - 2026-10-21

### Changed

- Persian translation reviewed by the NetArz brand team: «طلا و سکه از نِت اَرز», «قیمت / ارزش» column,
  chart titles without «قیمت», a copyable symbol example, «جست‌وجو», «ارقام انگلیسی», «نرخ‌ها الان در دسترس نیستند».
- Readme: the keyless public board is not open yet, so a free key is needed until it is; the source line is named
  for readers, not as a licence condition; gold history starts on 2026-10-21.

## [2.0.1] - 2026-10-21

### Fixed

- Matched to the live markets API (package D): `history/{symbol}` returns `data.points` with OHLC and the instrument
  fields; rows carry `unit_amount`, `bid` and `ask`. The client reads these.
- Keyless mode also accepts a tab-shaped board (`tabs[].rows[]`, the box payload), so the public board can reuse it.
- Without a key the board has one (average) rate per currency: `field` resolves to `mid` and currency tables show one
  "Average" column instead of buy and sell columns holding the same number.
- The chart block no longer says it charts currencies (the markets API sends currency history to `/api/fx/v1/history`).

## [2.0.0] - 2026-10-21

### Added

- Gold, silver and coins from the NetArz markets API (`/api/markets/v1`): `[netarz_gold]`, `[netarz_coins]`,
  `[netarz_metals]` and `[netarz_market symbols="..."]`, and the `netarz-fx/market` block. The ounce comes from
  gold-api.com through NetArz; 18k gold per gram and per mesghal and five Iranian coins are a NetArz computed value.
- Every computed row carries a "Computed value" badge and every table or chart that shows one prints the note that it
  is not the Tehran market price (it leaves out the coin premium and making charges). Not switchable. A row is treated
  as computed when the API says so, when its source is `computed`, or, when the API is silent, for the `gold_ir` and
  `coin_ir` classes.
- `[netarz_chart symbol="XAU" range="30d"]` and the `netarz-fx/chart` block: a server-side SVG line chart from
  `history/{symbol}`, at most 240 points, no chart library.
- Classic widget "NetArz currency and gold rates" with currency, gold and coin tabs (`assets/netarz-fx-tabs.js`,
  vanilla, keyboard-friendly; without JavaScript the panels stack).
- Keyless mode: without a key the plugin reads `/api/markets/v1/public/board`, and the 1.x currency shortcodes, the
  converter and the WooCommerce line read the board's currencies.
- Consent: a "Connection to NetArz" switch, off on a new install. Until it is on, no request is made from the server
  and the browser-mode script is not loaded; visitors see nothing and administrators see a pointer to the settings.
  Sites updating from 1.x with a saved key are switched on once, on update.
- Plain-text source line under every market table and chart (data licence condition; not a link).
- Last good answers for the markets API, shown with "Last updated" when NetArz cannot be reached.
- `NETARZ_FX_MARKETS_API` can be defined in `wp-config.php` for staging and tests.
- `./build.sh wporg [dir]` builds the WordPress.org package (unzipped folder plus `netarz-fx.zip`).

### Changed

- Plugin name: "NetArz Currency & Gold Rates" (slug and text domain stay `netarz-fx`).
- `readme.txt` rewritten in the WordPress.org format with an "External services" section.
- Uninstall also removes the markets cache, its last good copies and the version option.

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

[Unreleased]: https://github.com/netarz/netarz-fx-wordpress/compare/v2.0.2...HEAD
[2.0.2]: https://github.com/netarz/netarz-fx-wordpress/compare/v2.0.1...v2.0.2
[2.0.1]: https://github.com/netarz/netarz-fx-wordpress/compare/v2.0.0...v2.0.1
[2.0.0]: https://github.com/netarz/netarz-fx-wordpress/compare/v1.1.0...v2.0.0
[1.1.0]: https://github.com/netarz/netarz-fx-wordpress/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/netarz/netarz-fx-wordpress/releases/tag/v1.0.0
