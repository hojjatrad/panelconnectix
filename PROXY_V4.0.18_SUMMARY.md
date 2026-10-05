# v4.0.18 PROXY FREE - خلاصه پیاده‌سازی

## سوال کاربر
> میخوام بدونم اگر بتونم از پنل پروکسی درست کنم که برای تلگرام و باقی نرم افزارها استفاده کنم آیا امکانش هست
> میخوام هم بتونم رایگان بدم به مشتری هم بتونم ایجاد کنم و بفروشم و از همین سرورهایی که دارم استفاده کنم و برای تلگرام و سایر برنامه ها بشه استفاده کرد

## پاسخ: بله، امکانش هست و پیاده‌سازی شد ✅

### معماری پروکسی (بدون سرور جدید)

از **سرورهای موجود Xray/Marzban** استفاده می‌کند:

1. **Local Proxy (127.0.0.1:10808/10809)** - از قبل موجود برای اشتراک با TV
   - SOCKS5: `socks5://127.0.0.1:10808`
   - HTTP: `http://127.0.0.1:10809`
   - فقط وقتی VPN وصله کار می‌کند
   - برای همین گوشی و TV/کنسول از طریق هات‌اسپات
   - **رایگان برای همه مشتری‌های VPN**

2. **Dedicated Proxy (بدون نیاز به VPN)**
   - SOCKS5: `socks5://username:password@host:1080`
   - HTTP: `http://username:password@host:8080`
   - از Xray inbound های 1080/8080 استفاده می‌کند
   - username/password از اکانت مشتری
   - **رایگان برای مشتری‌های VPN**
   - **قابل فروش به عنوان پلن جداگانه**

3. **MTProto برای تلگرام**
   - `https://t.me/proxy?server=host&port=443&secret=ee...`
   - `tg://proxy?server=host&port=443&secret=ee...`
   - مخصوص تلگرام - بدون نیاز به فیلترشکن
   - **رایگان**

### فایل‌های جدید/تغییر یافته

#### Backend
- `controllers/ProxyController.php` (جدید):
  - `appProxies()`: GET/POST /api/v1/app/proxies
  - احراز هویت با sub_token/uuid
  - استخراج host از api_url/sub_domain
  - پشتیبانی از پورت‌های قابل تنظیم (socks_port, http_port, mtproto_port)
  - تشخیص پلن پروکسی با is_proxy_only
  - تولید MTProto secret با ee + md5
  - آموزش‌های کامل

- `core/Database.php`:
  - جدول `proxy_configs`: تنظیمات پروکسی هر سرور
  - جدول `proxy_clients`: کلاینت‌های پروکسی
  - ستون‌های جدید: `plans.is_proxy_only`, `plans.proxy_type`, `clients.is_proxy_only`, `server_nodes.socks_port/http_port/mtproto_port/mtproto_secret/proxy_enabled`

- `controllers/PlanController.php`:
  - پشتیبانی از `is_proxy_only` و `proxy_type` در store/update
  - تشخیص خودکار ستون‌های موجود

- `views/plans/index.php`:
  - چک‌باکس "فقط پروکسی" در مودال ایجاد/ویرایش
  - انتخاب نوع پروکسی (all/socks5/http/mtproto)
  - نشان "فقط پروکسی" روی کارت پلن‌ها

- `index.php`:
  - روت‌های `api/v1/app/proxies` و `proxies`

#### Frontend (Flutter)
- `client-app/lib/screens/proxy_screen.dart` (جدید - 800+ خط):
  - 4 کارت: محلی SOCKS/HTTP، اختصاصی SOCKS/HTTP، MTProto
  - دکمه کپی، QR، باز کردن در تلگرام
  - آموزش‌های تلگرام SOCKS/MTProto و مرورگر
  - بنر فروش پروکسی

- `client-app/lib/services/api_service.dart`:
  - متد `getProxies()` با تلاش تمام baseUrl ها

- `client-app/lib/screens/dashboard_screen.dart`:
  - دکمه پروکسی جدید کنار "اتصال هوشمند" و "TV"
  - import proxy_screen

- `client-app/pubspec.yaml`:
  - نسخه 4.0.18+53

### نحوه استفاده

#### برای مشتری VPN (رایگان)
1. اپ را باز کنید و لاگین کنید
2. دکمه "پروکسی" را بزنید
3. یکی از پروکسی‌ها را انتخاب کنید:
   - **محلی**: وقتی VPN وصله، برای همین گوشی
   - **اختصاصی SOCKS5**: برای تلگرام بدون VPN
   - **اختصاصی HTTP**: برای مرورگر
   - **MTProto**: لینک مستقیم تلگرام

#### برای فروش پروکسی جداگانه (v4.0.19 - آماده)
1. پنل مدیریت > پلن‌ها > تعریف پلن جدید
2. تیک "فقط پروکسی" را بزنید
3. نوع پروکسی را انتخاب کنید
4. قیمت ارزان‌تر از VPN بگذارید (مثلاً 30% کمتر)
5. در ربات تلگرام دکمه "خرید پروکسی" اضافه می‌شود (v4.0.19)

### تنظیمات سرور Xray (اختیاری)

اگر سرور شما inbound های SOCKS و HTTP ندارد، این را به config Xray اضافه کنید:

```json
{
  "inbounds": [
    {
      "port": 1080,
      "protocol": "socks",
      "settings": {
        "auth": "password",
        "accounts": [
          {"user": "username", "pass": "password"}
        ],
        "udp": true
      }
    },
    {
      "port": 8080,
      "protocol": "http",
      "settings": {
        "accounts": [
          {"user": "username", "pass": "password"}
        ]
      }
    }
  ]
}
```

اما اگر از Marzban/3x-ui استفاده می‌کنید، معمولاً خودکار حساب‌ها را می‌سازد.

### فیکس دائمی کش (حفظ شد)

- ApiController.php و ApiControllerV2.php همیشه `?v=version&t=time()` اضافه می‌کنند
- .htaccess برای .apk: no-store, BYPASS, no-cache
- اپ: فقط URL های ورژن‌دار + timestamp
- baseUrls: اول vpbotn.ir (نه ir/cf)

### نسخه‌ها
- v4.0.18: پروکسی رایگان برای مشتری‌های VPN (همین نسخه)
- v4.0.19: پلن فروش پروکسی جداگانه + دکمه ربات "خرید پروکسی" + ترافیک جدا

### تست
- [x] ProxyController.php تست شد (host extraction از api_url)
- [x] proxy_screen.dart کامپایل می‌شود
- [x] api_service.getProxies اضافه شد
- [x] dashboard دکمه پروکسی دارد
- [x] Database migration برای proxy
- [x] PlanController is_proxy_only
- [x] Build Android موفقیت (ARM64 38M, Universal 88M, ARM32 38M)
- [x] Build Windows موفقیت (23M)
- [x] Release v4.0.18 در گیت‌هاب با 7 فایل

### لینک‌ها
- Release: https://github.com/hojjatrad/panelconnectix/releases/tag/v4.0.18
- APK ARM64: https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.18/Connectix-Android-ARM64.apk
- APK Universal: https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.18/Connectix-Android-Universal.apk
- Windows: https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.18/Connectix-Windows-x64.zip

### برای آپدیت پنل
1. برو به `https://vpbotn.ir/quick_update.php` (اگر باز نشد، از طریق هاست فایل‌ها را آپدیت کن)
2. یا فایل `update_to_4.0.18.php` را اجرا کن: `https://vpbotn.ir/update_to_4.0.18.php`
3. سپس در تنظیمات > اپ، نسخه را 4.0.18 بگذار

