# راهنمای مشارکت

خوشحالیم که می‌خواهید افزونهٔ وردپرس نرخ ارز نِت اَرز را بهتر کنید. این راهنما کوتاه است و کمک می‌کند Pull Request شما زودتر بررسی و ادغام شود.

## چه کمکی به کار می‌آید

- اصلاح کدی که با [مستندات فعلی](https://netarz.ir/docs/fx?utm_source=github&utm_medium=referral&utm_campaign=netarz-fx-wordpress&utm_content=contributing) جور نیست یا اجرا نمی‌شود
- رفع اشکال، سازگاری با قالب‌ها و افزونه‌های رایج، و ترجمهٔ بهتر
- توضیح روشن‌تر در README یا در توضیح بالای فایل‌ها

## چه چیزی جای این مخزن نیست

- **حساب، اعتبار، پرداخت و کلید:** از پنل نِت اَرز [تیکت بزنید](https://netarz.ir/tickets?utm_source=github&utm_medium=referral&utm_campaign=netarz-fx-wordpress&utm_content=contributing) یا به `info@netarz.ir` ایمیل بفرستید.
- **مشکل امنیتی:** در Issue یا Pull Request عمومی ننویسید. طبق [سیاست امنیتی](SECURITY.md) به `dev@netarz.ir` بفرستید.

## پیش از فرستادن Pull Request

1. برای تغییر بزرگ، اول یک Issue باز کنید تا پیش از نوشتن کد دربارهٔ راه‌حل هم‌نظر شویم.
2. کلید واقعی (`fx-ntz-v1-…`)، توکن ربات یا نشانی سرور خصوصی در کد، README یا تاریخچهٔ گیت نگذارید. اگر اشتباهی کلیدی را کامیت کردید، پیش از هر کاری آن را از پنل نِت اَرز باطل کنید؛ پاک کردن کامیت کافی نیست.
3. کد را با [استاندارد کدنویسی وردپرس](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/) بنویسید. افزونه باید روی PHP 7.4 و وردپرس 6.0 به بعد اجرا شود.
4. هر مقداری که چاپ می‌شود escape شود (`esc_html`، `esc_attr`، `esc_url`) و هر ورودی تنظیمات sanitize شود.
5. هر متن تازه با text domain `netarz-fx` ترجمه‌پذیر باشد. فایل `languages/netarz-fx.pot` را به‌روز کنید و اگر می‌توانید ترجمهٔ فارسی (`fa_IR`) را هم.
6. اگر رفتار افزونه عوض شده، `Version` در `netarz-fx.php`، `Stable tag` و بخش Changelog در `readme.txt` را هم عوض کنید.
7. کد و توضیح‌های داخل کد انگلیسی باشد. متن‌هایی که کاربر وردپرس می‌بیند انگلیسی و ترجمه‌پذیر است و ترجمهٔ فارسی جدا می‌آید.
8. اگر شورت‌کد یا تنظیم تازه‌ای اضافه کرده‌اید، README و `readme.txt` را هم به‌روز کنید.
9. کد را یک بار واقعاً اجرا کنید. بررسی سریع نحو: `php -l <file>.php`، و آزمایش دستی روی یک وردپرس محلی با حالت «از همین سرور» و «از مرورگر».

## سبک نوشتن متن فارسی

- خواننده را «شما» خطاب کنید و جمله‌ها را کوتاه بنویسید.
- اصطلاح فنی را بار اول با توضیح بیاورید، مثل «کلید دسترسی (API Key)». نام endpoint، پارامتر و کد لاتین می‌ماند.
- ایموجی و علامت تعجب نگذارید. نام برند همیشه «نِت اَرز» (یا NetArz) است.

## مجوز

با فرستادن Pull Request می‌پذیرید که کد شما با مجوز همین مخزن (GPL-2.0-or-later) منتشر شود.
همه در این مخزن از [آیین رفتار](CODE_OF_CONDUCT.md) پیروی می‌کنند.

---

## Contributing (English)

Thanks for improving the NetArz FX Rates WordPress plugin. Open an issue before a large change. Never commit a real key (`fx-ntz-v1-…`) or token;
if you did, revoke it in the NetArz panel first. Follow the WordPress PHP coding standards, keep PHP 7.4 / WP 6.0 compatibility, escape all output, sanitize all input, keep strings translatable (`netarz-fx`) and bump the version in `netarz-fx.php` and `readme.txt`. Run the code once for real before opening a pull request.
Account, credit and billing questions go to `info@netarz.ir`, security issues to `dev@netarz.ir` (see [SECURITY.md](SECURITY.md)).
By contributing you agree your work is released under GPL-2.0-or-later.
