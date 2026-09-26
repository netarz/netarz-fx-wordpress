# افزونهٔ وردپرس نرخ ارز نِت اَرز (NetArz FX Rates)

افزونه‌ای کوچک برای نمایش **نرخ دلار، یورو، درهم، لیر و ارزهای دیگر به تومان** در سایت وردپرسی، با کد کوتاه
(شورت‌کد) یا ابزارک. نرخ‌ها از [API نرخ ارز نِت اَرز](https://netarz.ir/fx-api) می‌آیند و همان اعداد
[تابلوی نرخ ارز](https://netarz.ir/rates) هستند.

```text
[netarz_rate currency="usd"]                               نرخ فروش دلار، داخل متن
[netarz_rate currency="eur" field="buy" show_name="1"]     نرخ خرید یورو با نام ارز
[netarz_rates currencies="usd,eur,aed,try"]                جدول خرید و فروش چند ارز
```

ابزارک «NetArz exchange rates» (در نسخهٔ فارسی: «نرخ ارز نِت اَرز») همان جدول را در ستون کناری نشان می‌دهد.
در قالب‌های بلوکی، بلوک «کد کوتاه» را با `[netarz_rates]` به کار ببرید.

## نصب

1. فایل ZIP همین مخزن را دانلود کنید و از «افزونه‌ها ← افزودن ← بارگذاری افزونه» نصب و فعال کنید.
2. در پنل [netarz.ir/fx](https://netarz.ir/fx) یک اپ با دامنهٔ سایتتان بسازید و کلید `fx-ntz-v1-…` را کپی کنید.
   کلید فقط همان یک بار نمایش داده می‌شود.
3. مالکیت دامنه را در همان پنل تأیید کنید. فایل آماده را دانلود کنید و بدون ویرایش در پوشهٔ `.well-known` بگذارید، یا رکورد TXT بسازید.
4. در وردپرس به «تنظیمات ← NetArz FX» بروید، کلید را بگذارید و ذخیره کنید، بعد «آزمایش اتصال» را بزنید.

## از سرور یا از مرورگر؟

| حالت | کی مناسب است | چه لازم دارد |
|---|---|---|
| **از همین سرور** (پیش‌فرض، پیشنهادی) | هاست یا سروری که IP خروجی ثابت دارد | IP خروجی سرور در «IPهای مجاز» اپ |
| **از مرورگر بازدیدکننده** | هاستی که IP ثابت ندارد | فقط دامنهٔ تأییدشده. کلید در سورس صفحه دیده می‌شود ولی روی دامنهٔ دیگری کار نمی‌کند؛ هر بازدیدکننده بخشی از سهمیهٔ روزانهٔ اپ را مصرف می‌کند |

اگر نمی‌دانید IP خروجی سرورتان چیست، «آزمایش اتصال» را بزنید. اگر نِت اَرز درخواست را نپذیرد، افزونه **همان IPی را که نِت اَرز دیده**
نشان می‌دهد؛ دقیقاً همان را در پنل اضافه کنید. IP دامنه (مثلاً IP کلادفلر) ملاک نیست.
گزینهٔ «اتصال با IPv4» به‌طور پیش‌فرض روشن است، چون `netarz.ir` آدرس IPv6 هم دارد و سرور دوپشته‌ای (IPv4 و IPv6)
ممکن است با IPv6 وصل شود. توضیح کامل: [راهنمای قفل دامنه و IP](https://github.com/netarz/fx-api-examples/blob/main/guides/domain-and-ip-allow-list.md)

## افزونه چه می‌کند

- **یک درخواست در هر دورهٔ کش.** کل تابلوی نرخ یک بار گرفته می‌شود و همهٔ شورت‌کدها و ابزارک‌ها از همان نسخه می‌خوانند.
  زمان کش را خودتان تعیین می‌کنید (۱ تا ۶۰ دقیقه، پیش‌فرض ۵).
- **آخرین نرخ سالم را نگه می‌دارد.** اگر API در دسترس نباشد، بازدیدکننده آخرین نرخ گرفته‌شده را می‌بیند، نه جای خالی.
- **ارقام فارسی یا لاتین**، به‌طور پیش‌فرض مطابق زبان سایت.
- ترجمهٔ فارسی همراه افزونه است (text domain: `netarz-fx`) و هر مقداری که چاپ می‌شود escape شده است.

## لینک منبع

به‌طور پیش‌فرض یک لینک کوچک «نرخ از نِت اَرز» به صفحهٔ [نرخ ارز](https://netarz.ir/rates) زیر نرخ‌ها نشان داده می‌شود،
یک بار در هر صفحه. این لینک پنهان نیست و هر وقت خواستید از «تنظیمات ← NetArz FX» خاموشش کنید، یا با کد:

```php
add_filter( 'netarz_fx_show_attribution', '__return_false' );
```

## طرح رایگان کافی است؟

برای بیشتر سایت‌ها بله. طرح رایگان فقط عضویت می‌خواهد و نرخ را با کمی تأخیر می‌دهد؛ با کش افزونه، هر سایت در حالت
سرور بخش کوچکی از سهمیهٔ روزانه را مصرف می‌کند. نرخ زنده و تاریخچه در طرح پرو است. اعداد دقیق:
[netarz.ir/docs/fx/plans](https://netarz.ir/docs/fx/plans)

## خطاهای رایج

| پیام | راه‌حل |
|---|---|
| `domain_not_verified` | تأیید دامنه را در پنل /fx کامل کنید |
| `origin_required` یا `ip_not_allowed` | IPی را که «آزمایش اتصال» نشان می‌دهد در «IPهای مجاز» اپ بگذارید، یا حالت مرورگر را انتخاب کنید |
| `origin_not_allowed` (در حالت مرورگر) | دامنهٔ سایت را به دامنه‌های اپ اضافه کنید (مثلاً اگر سایت روی www است) |
| «نرخ در دسترس نیست» | کلید وارد نشده، یا هنوز هیچ نرخی گرفته نشده؛ «آزمایش اتصال» علت را می‌گوید |

## پشتیبانی

اشکال در افزونه: بخش Issues همین مخزن. حساب، اپ و کلید: تیکت از پنل نِت اَرز یا `support@netarz.ir`.

مجوز: GPL-2.0-or-later ([LICENSE](LICENSE))

---

## English

**NetArz FX Rates** is a small WordPress plugin that shows exchange rates in Iranian Toman (USD, EUR, AED, TRY and more)
from the [NetArz FX API](https://netarz.ir/fx-api).

- Shortcodes: `[netarz_rate currency="usd" field="sell" show_name="0"]` and
  `[netarz_rates currencies="usd,eur,aed" fields="buy,sell"]`; a classic widget with the same table.
- Settings > NetArz FX: API key, server or browser mode, cache minutes (default 5), Persian/Latin digits,
  a visible "Rates by NetArz" credit link (on by default, can be turned off, or filtered with `netarz_fx_show_attribution`),
  and "Connect over IPv4".
- One API request per cache period for the whole site; the last good board is kept as a fallback.
- "Test connection" calls `/me` and, when the API refuses the server, prints the exact IP NetArz saw.
- Server mode sends no Origin/Referer: calls are matched by the server's IP. Browser mode relies on the domain lock.
- Translation-ready (`netarz-fx`), Persian translation included; all output escaped; `uninstall.php` removes its options.

Install: download the ZIP, upload it under Plugins > Add New, create an app and key at <https://netarz.ir/fx>,
verify the domain, paste the key in Settings > NetArz FX.

License: GPL-2.0-or-later.
