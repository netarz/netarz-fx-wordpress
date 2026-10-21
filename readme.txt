=== NetArz Currency & Gold Rates ===
Contributors: gisooai
Tags: exchange rate, currency, gold price, toman, woocommerce
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Rates in Iranian Toman, the gold and silver ounce, and a labelled computed value of 18k gold and coins, from NetArz. Shortcodes, blocks, charts.

== Description ==

NetArz Currency & Gold Rates shows numbers from [NetArz](https://netarz.ir/rates) on your site: exchange rates in Toman, the global gold and silver ounce in dollars, and a computed value of 18k gold, the mesghal and Iranian gold coins.

**Nothing is fetched until you allow it.** After activation the plugin sends no request anywhere. Turn on "Allow this site to fetch rates from netarz.ir" in Settings > NetArz FX when you are ready. Until then the shortcodes, blocks and widgets print nothing for visitors.

**Computed values are labelled, always.** 18k gold, the mesghal and the coins are worked out from the global gold ounce and the NetArz dollar rate. They are not the Tehran market price and leave out the coin premium and making charges. Every table and chart that shows one prints a "Computed value" badge and that note; it cannot be switched off.

Shortcodes:

* `[netarz_rate currency="usd"]` prints one rate inline, for example the dollar sell rate. `field="buy|sell|mid"`, `show_name="1"`.
* `[netarz_rates currencies="usd,eur,aed,try" fields="buy,sell" change="1" updated="1"]` prints a table of currencies.
* `[netarz_convert currencies="usd,eur,aed,try" field="sell" amount="1"]` prints a converter between Toman and a currency. Typing makes no request.
* `[netarz_gold]` prints the gold ounce, 18k gold per gram and per mesghal.
* `[netarz_coins]` prints the Emami, Bahar Azadi, half, quarter and gram coins.
* `[netarz_metals]` prints the gold and silver ounce.
* `[netarz_market symbols="XAU,GOLD18,COIN_EMAMI,USD" change="1" updated="1"]` prints any mix of symbols.
* `[netarz_chart symbol="XAU" range="30d" height="220"]` draws a line chart as SVG on your server, without a chart library. Ranges: 7d, 30d, 90d, 6m, 1y, 5y.
* `digits="persian"` or `digits="latin"` on any shortcode overrides the site-wide setting.

Blocks: "NetArz exchange rate", "NetArz rates table", "NetArz currency converter", "NetArz gold and coins" and "NetArz price chart", each with a live preview.

Widgets: "NetArz currency and gold rates" (classic widget with currency, gold and coin tabs) and "NetArz exchange rates" (a table of currencies).

WooCommerce (optional, off by default): in a store priced in a foreign currency such as USD, each product price can get "about ... Toman" after it, from the cached rates.

What it does for you:

* **No key needed to start.** Without a key the plugin reads the keyless public board: a fixed set of currencies, gold and coins, a few minutes behind. A free NetArz FX API key lets you choose any currency or symbol of your plan and draw charts.
* **Few requests.** Every answer is cached (1 to 60 minutes for rates, your choice; 12 hours for chart history), and every shortcode on the site shares the same copy.
* **Never an empty box.** If NetArz cannot be reached, visitors see the last numbers that were fetched, with "Last updated" and their time.
* **Sources named.** A plain-text line under each table says where the numbers come from (for example gold-api.com for the ounce).
* **Persian or Latin digits**, following the site language by default. Persian (fa_IR) translation included.
* Every value printed is escaped. All scripts and styles ship with the plugin; nothing is loaded from another site.

= Credit link =

A small "Rates by NetArz" link to netarz.ir/rates can be shown once per page. It is **off by default**; turn it on in Settings > NetArz FX if you want it. The filter `netarz_fx_show_attribution` overrides the setting.

== External services ==

This plugin uses one external service, **NetArz** (netarz.ir), and only after an administrator turns on "Allow this site to fetch rates from netarz.ir" in Settings > NetArz FX. It is off when the plugin is activated.

What the service is: NetArz publishes exchange rates in Iranian Toman, the global gold and silver ounce, and its own computed value of 18k gold and Iranian gold coins, through the NetArz FX API (`https://netarz.ir/api/fx/v1`) and the NetArz markets API (`https://netarz.ir/api/markets/v1`).

When requests are made, once the connection is on:

* When a page with a shortcode, block or widget is shown and the cached answer has expired (rates: after the cache time you set, 1 to 60 minutes; chart history: after 12 hours).
* When an administrator opens the block editor with a key saved, to list the currencies for the picker (from the same cache).
* When an administrator clicks "Test connection".
* At most once a day, with a key, the currency list (`/currencies`, no prices) when a page asks for a currency the rates did not include.

What is sent:

* Without a key: a request for the public board (`/api/markets/v1/public/board`). It carries no personal data; NetArz sees your server's IP address and the plugin's user agent.
* With a key: your NetArz FX API key in the `Authorization` header, and the symbols or currency codes and date range you asked for. NetArz sees your server's IP address.
* In browser mode (only with a key): each visitor's browser calls netarz.ir directly for currency rates, so NetArz sees the visitor's IP address and the page's origin.

No other data about your site, its users or its visitors is sent.

Where the numbers come from: currencies are NetArz's own rates; the gold and silver ounce come from [gold-api.com](https://gold-api.com) through NetArz; 18k gold, the mesghal and the coins are computed by NetArz from those two and are not market prices. The plugin itself contacts only netarz.ir.

* NetArz terms of use and privacy: https://netarz.ir/terms
* NetArz FX API plans and limits: https://netarz.ir/docs/fx/plans
* gold-api.com terms: https://gold-api.com/terms

== Installation ==

1. Install the plugin from Plugins > Add New (search for "NetArz"), or upload the ZIP, and activate it.
2. Open Settings > NetArz FX and turn on "Allow this site to fetch rates from netarz.ir". Save.
3. Add `[netarz_gold]`, `[netarz_rate currency="usd"]` or any block whose name starts with "NetArz".
4. Optional, for any currency or symbol of your plan and for charts: sign in at https://netarz.ir/fx, create a free app for your site's domain, verify the domain, copy the key (`fx-ntz-v1-...`) and paste it in the settings. In server mode, add your server's outgoing IP to the app; "Test connection" tells you which IP NetArz sees.

== Frequently Asked Questions ==

= Why does nothing show after I activate the plugin? =

The plugin does not contact NetArz until you allow it. Turn on the connection in Settings > NetArz FX. Administrators see a short note in place of the rates until then; visitors see nothing.

= Is the 18k gold or coin price the market price? =

No. It is a computed value from the global gold ounce and the NetArz dollar rate. It leaves out the coin premium and making charges, which can be large, so the Tehran market price is usually different. The plugin labels it on every table and chart.

= Do I need an API key? =

No. Without a key you get the keyless public board (a fixed set, a few minutes behind). A free key lets you pick any symbol of your plan and draw charts; the free plan's chart history covers the last 30 days.

= I get ip_not_allowed or origin_required =

With a key in server mode, your server's outgoing IP must be on the app's allow-list. Click "Test connection": the IP NetArz saw is printed; add exactly that address at netarz.ir/fx. Keep "Connect over IPv4" on unless you allow-listed an IPv6 address.

= A symbol shows "Rates unavailable" =

Check the symbol. Without a key, only the symbols on the public board are available. Administrators see a note saying which symbols NetArz did not return; visitors never see it.

= Can I remove the source line? =

No. Naming the source of the numbers is a condition of the data licence. It is plain text, not a link. The optional "Rates by NetArz" credit link is a separate setting and is off by default.

== Screenshots ==

1. The rates table and the converter on a Persian site.
2. The rates table block and its settings in the block editor.

== Changelog ==

= 2.0.1 =
* Matched to the live NetArz markets API: history points, `unit_amount`, bid and ask.
* Keyless mode also reads a board made of tabs (the box payload).
* Without a key, currencies show the average rate, and tables say so in their one column, because the keyless board has no separate buy and sell rates.
* The chart block describes what it charts: the gold ounce, the silver ounce and 18k gold.

= 2.0.0 =
* New name: NetArz Currency & Gold Rates (the slug stays `netarz-fx`).
* New: gold and coins. `[netarz_gold]`, `[netarz_coins]`, `[netarz_metals]` and `[netarz_market symbols="..."]`, plus the "NetArz gold and coins" block: the global gold and silver ounce, and a computed value of 18k gold, the mesghal and five Iranian gold coins, always labelled as computed and not the Tehran market price.
* New: `[netarz_chart]` and the "NetArz price chart" block: a server-side SVG line chart, no chart library.
* New: classic widget "NetArz currency and gold rates" with currency, gold and coin tabs.
* New: works without a key, from the keyless public board.
* New: the connection to NetArz is off until an administrator turns it on; nothing is requested before that. Sites updating from 1.x with a saved key stay connected.
* New: a plain-text source line under every table and chart.
* New: when NetArz cannot be reached, the last numbers show with "Last updated".
* Kept: all 1.x shortcodes, blocks, the widget, the converter and the WooCommerce line.

= 1.1.0 =
* New: blocks for the block editor: exchange rate, rates table and currency converter.
* New: `[netarz_convert]`, `change="1"`, `updated="1"` and `digits="persian|latin"`.
* New: optional WooCommerce line with the Toman equivalent of foreign-currency prices.
* Changed: the credit link is off by default.

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 2.0.1 =
Matches the live NetArz markets API. Update if you use gold, coins or charts.

= 2.0.0 =
Gold, coins, silver and charts. The plugin now asks before it contacts NetArz; sites with a saved key stay connected.
