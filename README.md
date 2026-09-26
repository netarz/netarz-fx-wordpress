<div align="center">

# افزونهٔ وردپرس نرخ ارز نِت اَرز (NetArz FX Rates)

**نرخ دلار و ارزهای دیگر به تومان در سایت وردپرسی، با یک شورت‌کد یا ابزارک.**

WordPress plugin for Iranian Toman exchange rates (USD, EUR, AED, TRY and more) via the NetArz FX API: shortcode, widget, cache, Persian translation.

[![License: GPL v2+](https://img.shields.io/badge/license-GPL--2.0--or--later-ffc700?style=flat-square&labelColor=14161f)](LICENSE)
[![Version](https://img.shields.io/badge/version-1.0.0-ffc700?style=flat-square&labelColor=14161f)](https://github.com/netarz/netarz-fx-wordpress/releases)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-ffc700?style=flat-square&labelColor=14161f&logo=wordpress&logoColor=white)](readme.txt)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-ffc700?style=flat-square&labelColor=14161f&logo=php&logoColor=white)](netarz-fx.php)
[![Docs](https://img.shields.io/badge/docs-netarz.ir%2Fdocs%2Ffx-ffc700?style=flat-square&labelColor=14161f)](https://netarz.ir/docs/fx?utm_source=github&utm_medium=referral&utm_campaign=netarz-fx-wordpress&utm_content=header)

[دانلود آخرین نسخه](https://github.com/netarz/netarz-fx-wordpress/releases/latest) · [ساخت کلید رایگان](https://netarz.ir/fx?utm_source=github&utm_medium=referral&utm_campaign=netarz-fx-wordpress&utm_content=header) · [معرفی API نرخ ارز](https://netarz.ir/fx-api?utm_source=github&utm_medium=referral&utm_campaign=netarz-fx-wordpress&utm_content=header) · [تابلوی نرخ ارز](https://netarz.ir/rates?utm_source=github&utm_medium=referral&utm_campaign=netarz-fx-wordpress&utm_content=header) · [English](#english)

</div>

<a id="intro"></a>

افزونه‌ای کوچک برای نمایش **نرخ دلار، یورو، درهم، لیر و ارزهای دیگر به تومان** در سایت وردپرسی، با کد کوتاه
(شورت‌کد) یا ابزارک. نرخ‌ها از [API نرخ ارز نِت اَرز](https://netarz.ir/fx-api?utm_source=github&utm_medium=referral&utm_campaign=netarz-fx-wordpress&utm_content=intro) می‌آیند و همان اعداد
[تابلوی نرخ ارز](https://netarz.ir/rates?utm_source=github&utm_medium=referral&utm_campaign=netarz-fx-wordpress&utm_content=intro) هستند.

```text
[netarz_rate currency="usd"]                               نرخ فروش دلار، داخل متن
[netarz_rate currency="eur" field="buy" show_name="1"]     نرخ خرید یورو با نام ارز
[netarz_rates currencies="usd,eur,aed,try"]                جدول خرید و فروش چند ارز
```

ابزارک «NetArz exchange rates» (در نسخهٔ فارسی: «نرخ ارز نِت اَرز») همان جدول را در ستون کناری نشان می‌دهد.
در قالب‌های بلوکی، بلوک «کد کوتاه» را با `[netarz_rates]` به کار ببرید.

## فهرست

- [نصب در چهار قدم](#install)
- [از سرور یا از مرورگر؟](#modes)
- [افزونه چه می‌کند](#features)
- [لینک منبع](#credit)
- [طرح رایگان کافی است؟](#plans)
- [خطاهای رایج](#errors)
- [مخزن‌های دیگر نِت اَرز](#related)
- [مشارکت و پشتیبانی](#support)
- [English](#english)

<a id="install"></a>

## نصب در چهار قدم

1. فایل `netarz-fx-1.0.0.zip` را از [صفحهٔ نسخه‌ها (Releases)](https://github.com/netarz/netarz-fx-wordpress/releases/latest) دانلود کنید و از
   «افزونه‌ها ← افزودن ← بارگذاری افزونه» نصب و فعال کنید. دکمهٔ سبز «Code ← Download ZIP» هم کار می‌کند، ولی پوشهٔ افزونه
   نام دیگری می‌گیرد؛ فایل Releases پوشهٔ درست `netarz-fx` را دارد.
2. در [پنل API نرخ ارز](https://netarz.ir/fx?utm_source=github&utm_medium=referral&utm_campaign=netarz-fx-wordpress&utm_content=install) یک اپ با دامنهٔ سایتتان بسازید و کلید `fx-ntz-v1-…` را کپی کنید.
   کلید را فقط همان یک بار می‌بینید.
3. مالکیت دامنه را در همان پنل تأیید کنید. فایل آماده را دانلود کنید و بدون ویرایش در پوشهٔ `.well-known` بگذارید، یا رکورد TXT بسازید.
4. در وردپرس به «تنظیمات ← نرخ ارز نِت اَرز» بروید (در وردپرس انگلیسی: Settings ← NetArz FX)، کلید را بگذارید و ذخیره کنید، بعد «آزمایش اتصال» را بزنید.

حالا در هر نوشته یا برگه بنویسید `[netarz_rate currency="usd"]`.

<a id="modes"></a>

## از سرور یا از مرورگر؟

| حالت | کی مناسب است | چه لازم دارد |
|---|---|---|
| **از همین سرور** (پیش‌فرض، پیشنهادی) | هاست یا سروری که IP خروجی ثابت دارد | IP خروجی سرور در «IPهای مجاز» اپ |
| **از مرورگر بازدیدکننده** | هاستی که IP ثابت ندارد | فقط دامنهٔ تأییدشده. کلید در سورس صفحه دیده می‌شود ولی روی دامنهٔ دیگری کار نمی‌کند؛ هر بازدیدکننده بخشی از سهمیهٔ روزانهٔ اپ را مصرف می‌کند |

اگر نمی‌دانید IP خروجی سرورتان چیست، «آزمایش اتصال» را بزنید. اگر نِت اَرز درخواست را نپذیرد، افزونه **همان IPی را که نِت اَرز دیده**
نشان می‌دهد؛ دقیقاً همان را در پنل اضافه کنید. IP دامنه (مثلاً IP کلادفلر) ملاک نیست.
گزینهٔ «اتصال با IPv4» به‌طور پیش‌فرض روشن است، چون `netarz.ir` آدرس IPv6 هم دارد و سرور دوپشته‌ای (IPv4 و IPv6)
ممکن است با IPv6 وصل شود. توضیح کامل: [راهنمای قفل دامنه و IP](https://github.com/netarz/fx-api-examples/blob/main/guides/domain-and-ip-allow-list.md)

<a id="features"></a>

## افزونه چه می‌کند

- **یک درخواست در هر دورهٔ کش.** کل تابلوی نرخ یک بار گرفته می‌شود و همهٔ شورت‌کدها و ابزارک‌ها از همان نسخه می‌خوانند.
  زمان کش را خودتان تعیین می‌کنید (۱ تا ۶۰ دقیقه، پیش‌فرض ۵).
- **آخرین نرخ سالم را نگه می‌دارد.** اگر API در دسترس نباشد، بازدیدکننده آخرین نرخ گرفته‌شده را می‌بیند، نه جای خالی.
- **ارقام فارسی یا لاتین**، به‌طور پیش‌فرض مطابق زبان سایت.
- ترجمهٔ فارسی همراه افزونه است (text domain: `netarz-fx`) و هر مقداری که چاپ می‌شود escape شده است.

<a id="credit"></a>

## لینک منبع

به‌طور پیش‌فرض یک لینک کوچک «نرخ از نِت اَرز» به صفحهٔ [نرخ ارز](https://netarz.ir/rates?utm_source=github&utm_medium=referral&utm_campaign=netarz-fx-wordpress&utm_content=credit) زیر نرخ‌ها نشان داده می‌شود،
یک بار در هر صفحه. این لینک پنهان نیست و هر وقت خواستید از «تنظیمات ← نرخ ارز نِت اَرز» خاموشش کنید، یا با کد:

```php
add_filter( 'netarz_fx_show_attribution', '__return_false' );
```

<a id="plans"></a>

## طرح رایگان کافی است؟

برای بیشتر سایت‌ها بله. طرح رایگان فقط عضویت می‌خواهد و نرخ را با کمی تأخیر می‌دهد؛ با کش افزونه، هر سایت در حالت
سرور بخش کوچکی از سهمیهٔ روزانه را مصرف می‌کند. نرخ زنده و تاریخچه در طرح پرو است. اعداد دقیق:
[netarz.ir/docs/fx/plans](https://netarz.ir/docs/fx/plans?utm_source=github&utm_medium=referral&utm_campaign=netarz-fx-wordpress&utm_content=plans)

<a id="errors"></a>

## خطاهای رایج

| پیام | راه‌حل |
|---|---|
| `domain_not_verified` | تأیید دامنه را در پنل /fx کامل کنید |
| `origin_required` یا `ip_not_allowed` | IPی را که «آزمایش اتصال» نشان می‌دهد در «IPهای مجاز» اپ بگذارید، یا حالت مرورگر را انتخاب کنید |
| `origin_not_allowed` (در حالت مرورگر) | دامنهٔ سایت را به دامنه‌های اپ اضافه کنید (مثلاً اگر سایت روی www است) |
| «نرخ در دسترس نیست» | کلید وارد نشده، یا هنوز هیچ نرخی گرفته نشده؛ «آزمایش اتصال» علت را می‌گوید |

<a id="related"></a>

## مخزن‌های دیگر نِت اَرز

| مخزن | چیست |
|---|---|
| [fx-api-examples](https://github.com/netarz/fx-api-examples) | نمونه‌کد همین API برای PHP، JavaScript، Python، Laravel، Google Sheets و Excel، و [راهنمای قفل دامنه و IP](https://github.com/netarz/fx-api-examples/blob/main/guides/domain-and-ip-allow-list.md) |
| [ai-api-examples](https://github.com/netarz/ai-api-examples) | نمونه‌کد وب‌سرویس هوش مصنوعی: GPT، Claude، Gemini و DeepSeek با یک کلید سازگار با OpenAI |
| [netarz](https://github.com/netarz/netarz) | معرفی همهٔ وب‌سرویس‌ها و مخزن‌های نِت اَرز |

همهٔ پروژه‌های متن‌باز ما یک‌جا: [netarz.ir/open-source](https://netarz.ir/open-source?utm_source=github&utm_medium=referral&utm_campaign=netarz-fx-wordpress&utm_content=related) · همهٔ مستندات فنی: [netarz.ir/docs](https://netarz.ir/docs?utm_source=github&utm_medium=referral&utm_campaign=netarz-fx-wordpress&utm_content=related)

<a id="support"></a>

## مشارکت و پشتیبانی

- **اشکال در افزونه:** یک [Issue](https://github.com/netarz/netarz-fx-wordpress/issues/new/choose) باز کنید و نتیجهٔ «آزمایش اتصال» را هم بگذارید.
- **رفع اشکال، ترجمه یا امکان تازه:** Pull Request بفرستید. پیش از آن [راهنمای مشارکت](CONTRIBUTING.md) را ببینید.
- **حساب، اپ و کلید:** از پنل نِت اَرز تیکت بزنید یا به `info@netarz.ir` ایمیل بفرستید.
- **مشکل امنیتی:** در Issue عمومی ننویسید؛ طبق [سیاست امنیتی](SECURITY.md) به `dev@netarz.ir` بفرستید.

مجوز: GPL-2.0-or-later ([LICENSE](LICENSE)) · [آیین رفتار](CODE_OF_CONDUCT.md)

---

<a id="english"></a>

## English

**NetArz FX Rates** is a small WordPress plugin that shows exchange rates in Iranian Toman (USD, EUR, AED, TRY and more)
from the [NetArz FX API](https://netarz.ir/fx-api?utm_source=github&utm_medium=referral&utm_campaign=netarz-fx-wordpress&utm_content=english).

```text
[netarz_rate currency="usd"]                            dollar sell rate, inline
[netarz_rate currency="eur" field="buy" show_name="1"]  euro buy rate with its name
[netarz_rates currencies="usd,eur,aed,try"]             buy/sell table
```

- A classic widget with the same table; in block themes use the Shortcode block with `[netarz_rates]`.
- Settings > NetArz FX: API key, server or browser mode, cache minutes (default 5), Persian/Latin digits,
  a visible "Rates by NetArz" credit link (on by default, can be turned off, or filtered with `netarz_fx_show_attribution`),
  and "Connect over IPv4".
- One API request per cache period for the whole site; the last good board is kept as a fallback.
- "Test connection" calls `/me` and, when the API refuses the server, prints the exact IP NetArz saw.
- Server mode sends no Origin/Referer: calls are matched by the server's IP. Browser mode relies on the domain lock.
- Translation-ready (`netarz-fx`), Persian translation included; all output escaped; `uninstall.php` removes its options.

**Install:** download `netarz-fx-1.0.0.zip` from [Releases](https://github.com/netarz/netarz-fx-wordpress/releases/latest),
upload it under Plugins > Add New > Upload Plugin, create an app and key at [netarz.ir/fx](https://netarz.ir/fx?utm_source=github&utm_medium=referral&utm_campaign=netarz-fx-wordpress&utm_content=english), verify the domain,
paste the key in Settings > NetArz FX and click "Test connection".

Links: [FX API docs](https://netarz.ir/docs/fx?utm_source=github&utm_medium=referral&utm_campaign=netarz-fx-wordpress&utm_content=english) · [Plans and limits](https://netarz.ir/docs/fx/plans?utm_source=github&utm_medium=referral&utm_campaign=netarz-fx-wordpress&utm_content=english) ·
[All NetArz open-source projects](https://netarz.ir/open-source?utm_source=github&utm_medium=referral&utm_campaign=netarz-fx-wordpress&utm_content=english) · Related: [fx-api-examples](https://github.com/netarz/fx-api-examples) ·
[ai-api-examples](https://github.com/netarz/ai-api-examples)

Contributions are welcome: see [CONTRIBUTING.md](CONTRIBUTING.md). Report security issues privately to `dev@netarz.ir`
([SECURITY.md](SECURITY.md)). License: GPL-2.0-or-later.
