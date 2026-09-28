=== NetArz FX Rates ===
Contributors: netarz
Tags: exchange rate, currency, toman, dollar, converter
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Exchange rates in Iranian Toman (USD, EUR, AED, TRY, ...) from the NetArz FX API: shortcodes, blocks, a converter and a widget.

== Description ==

NetArz FX Rates prints buy, sell or average rates in Toman from the [NetArz FX API](https://netarz.ir/fx-api), the same numbers as the [NetArz rate board](https://netarz.ir/rates).

* `[netarz_rate currency="usd"]` prints one rate inline, for example the dollar sell rate.
* `[netarz_rate currency="eur" field="buy" show_name="1"]` adds the currency name.
* `[netarz_rates currencies="usd,eur,aed,try" fields="buy,sell" change="1" updated="1"]` prints a small table, optionally with the change since yesterday and the time the rates were taken.
* `[netarz_convert currencies="usd,eur,aed,try" field="sell" amount="1"]` prints a converter: type an amount of currency or of Toman and the other field follows.
* Three blocks for the block editor: "NetArz exchange rate", "NetArz rates table" and "NetArz currency converter", with a live preview.
* A classic widget, "NetArz exchange rates", shows the same table in a sidebar.
* `digits="persian"` or `digits="latin"` on any shortcode (or the Digits control on a block) overrides the site-wide setting.

What it does for you:

* **One request per cache period.** The plugin fetches the whole rate board once and every shortcode and widget reads that copy. The cache time is yours to set (1 to 60 minutes, default 5).
* **Keeps the last good rates.** If the API cannot be reached, visitors see the last rates that were fetched instead of an empty box.
* **Two ways to fetch.** From your server (recommended, needs your server's IP on the app's allow-list) or from the visitor's browser (for hosts without a fixed IP; the key only works on your verified domain).
* **A "Test connection" button** that reports the exact IP NetArz saw when it refuses your server, so you can add that one address.
* **Persian or Latin digits**, following the site language by default.
* **Pro-only currencies are explained, not hidden.** A currency the free plan does not include (for example UZS) shows "Rate unavailable" to visitors and a short note about the Pro plan to administrators only.
* **WooCommerce (optional, off by default).** For a store priced in a foreign currency such as USD, each product price can get "about ... Toman" after it, from the cached rates.
* Translation-ready (text domain `netarz-fx`), Persian (fa_IR) translation included.
* Every value printed is escaped.

= Credit link =

A small "Rates by NetArz" link («نرخ از نِت اَرز») to netarz.ir/rates can be shown under the rates, once per page. It is off by default: turn it on in Settings > NetArz FX if you want readers to see where the numbers come from. The filter `netarz_fx_show_attribution` overrides the setting, for example `add_filter( 'netarz_fx_show_attribution', '__return_false' );`.

= External service =

This plugin connects to the NetArz FX API (`https://netarz.ir/api/fx/v1`) to read exchange rates. It is required for the plugin to work.

* **When:** when a page with a shortcode, a block or the widget is rendered and the cached rates have expired; when an administrator clicks "Test connection"; and, at most once a day, the currency list (`/currencies`, no prices) when a page asks for a currency the rates did not include, to tell administrators whether it is a Pro-only currency.
* **What is sent:** your NetArz FX API key in the `Authorization` header and the list of currency codes. In server mode the request comes from your server, so NetArz sees your server's IP. In browser mode each visitor's browser calls netarz.ir directly, so NetArz sees the visitor's IP address and the page's origin.
* **Account:** a NetArz account and a free FX app are needed to get a key. Plans and limits: https://netarz.ir/docs/fx/plans
* **Terms and privacy:** https://netarz.ir/terms

== Installation ==

1. Download `netarz-fx-1.1.0.zip` from https://github.com/netarz/netarz-fx-wordpress/releases and install it from Plugins > Add New > Upload Plugin (or clone the repository into `/wp-content/plugins/netarz-fx/`), then activate it.
2. Sign in at https://netarz.ir/fx and create an app with your site's domain. Copy the key (`fx-ntz-v1-...`); it is shown once.
3. Verify the domain in the same panel (a DNS TXT record, or the ready-made file under `/.well-known/`).
4. In server mode, add your server's outgoing IP under the app's allowed IPs. The "Test connection" button tells you which IP NetArz sees.
5. Paste the key in Settings > NetArz FX, save, then add `[netarz_rate currency="usd"]` to any post or page, or search for "NetArz" in the block inserter.

== Frequently Asked Questions ==

= I get ip_not_allowed or origin_required =

Your server's outgoing IP is not on the app's allow-list. Click "Test connection": the IP NetArz saw is printed; add exactly that address at netarz.ir/fx. The domain's IP (for example a Cloudflare IP) is not the one that matters. If your host changes its outgoing IP, switch to browser mode.

= I added the IPv4 address and it still fails =

Your server may be reaching netarz.ir over IPv6. Keep "Connect over IPv4" switched on (default), or add the server's exact IPv6 address to the app.

= Does the free plan work? =

Yes. The free plan serves rates with a short delay and has a daily request limit; with the plugin's cache one site uses only a small part of it in server mode. Current limits are on https://netarz.ir/docs/fx/plans

= A currency shows "Rate unavailable" =

Check the code (three letters, for example USD). If you are signed in as an administrator and the currency is one NetArz offers on the Pro plan only, a note says so under it; visitors never see that note.

= Where do the numbers come from? =

They are the same rates as on the NetArz rate board (https://netarz.ir/rates); pegged currencies use a fixed parity and the rest are fetched daily. Rates are informational.

= The key is in my page source in browser mode. Is that safe? =

The key only works from domains registered and verified on your app; browsers do not let a page forge its Origin. Anyone copying it can only make requests from your own site. If you see unusual usage, rotate the key in the panel.

== Screenshots ==

1. The rates table and the converter on a Persian site.
2. The rates table block and its settings in the block editor.

== Changelog ==

= 1.1.0 =
* New: blocks for the block editor: exchange rate, rates table and currency converter (no build step, live preview).
* New: `[netarz_convert]`, a Toman and currency converter that works both ways as you type, with Latin or Persian digits. No request while typing.
* New: `change="1"` on `[netarz_rates]` adds the change since yesterday; `updated="1"` adds when the rates were taken, and the delay on the free plan.
* New: `digits="persian|latin"` on every shortcode.
* New: administrators see a short note when a currency is Pro-only on the NetArz FX API.
* New: optional WooCommerce line with the Toman equivalent of foreign-currency prices.
* New: `NETARZ_FX_API` can be set in wp-config.php for staging and tests.
* Changed: the credit link is now off by default; turn it on in the settings if you want it.
* Changed: in tables, "(per 100 units)" is printed once beside the currency name.
* Changed: links to netarz.ir from the plugin carry UTM parameters.
* Fixed: browser mode printed Persian digits and names on every site.

= 1.0.0 =
* First release: `[netarz_rate]`, `[netarz_rates]`, widget, settings page, server and browser modes, test connection, Persian translation.

== Upgrade Notice ==

= 1.1.0 =
Blocks, a currency converter, a change column and an updated line. The credit link is now off unless you turn it on.

= 1.0.0 =
First release.
