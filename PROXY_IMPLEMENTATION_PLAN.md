# پلن پیاده‌سازی پروکسی - رایگان + فروشی با سرورهای فعلی

## نیازمندی کاربر
- ✅ رایگان برای مشتری‌های VPN فعلی
- ✅ قابل فروش جدا به عنوان پلن پروکسی
- ✅ استفاده از سرورهای فعلی (Xray/Marzban)
- ✅ برای تلگرام (MTProto + SOCKS5) و سایر برنامه‌ها (HTTP, SOCKS)

---

## معماری پیشنهادی - 2 فاز

### فاز 1: v4.0.18 - پروکسی رایگان برای مشتری‌های VPN (همین هفته)

#### Backend (پنل):

**1. دیتابیس - جدول `proxy_configs`:**
```sql
CREATE TABLE proxy_configs (
  id INTEGER PRIMARY KEY,
  client_id INTEGER,
  server_id INTEGER,
  type TEXT, -- 'socks', 'http', 'mtproto'
  host TEXT,
  port INTEGER,
  username TEXT,
  password TEXT,
  secret TEXT, -- برای MTProto
  is_active INTEGER DEFAULT 1,
  traffic_used_bytes INTEGER DEFAULT 0,
  created_at DATETIME
)
```

**2. `ApiController.php` - متد جدید `appProxies`:**
- برای هر کلاینت فعال، پروکسی اختصاصی بساز:
  - SOCKS5: `socks5://username:password@server_host:1080#name`
  - HTTP: `http://username:password@server_host:8080#name`
  - MTProto: `https://t.me/proxy?server=host&port=443&secret=secret`
- اگر کلاینت VPN داره → پروکسی رایگان
- یوزر/پسورد = یوزرنیم کلاینت + پسورد رندوم یا همان پسورد کلاینت

**3. `server_nodes` - inbound پروکسی:**
- در کانفیگ Xray هر نود، inbound جدید:
```json
{
  "port": 1080,
  "protocol": "socks",
  "settings": {
    "auth": "password",
    "accounts": [{"user": "client_user", "pass": "client_pass"}],
    "udp": true
  }
},
{
  "port": 8080,
  "protocol": "http",
  "settings": {
    "accounts": [{"user": "client_user", "pass": "client_pass"}]
  }
}
```
- این را درایور Xray باید ساپورت کنه - یا از طریق API نود اضافه بشه

**4. کرون `sync_proxy`:**
- هر 10 دقیقه: کلاینت‌های فعال → اکانت SOCKS/HTTP روی نود اصلی‌شون بساز

#### Frontend (اپ):

**1. تب جدید "پروکسی" در داشبورد:**
- کارت 1: **پروکسی محلی (رایگان - وقتی VPN وصله)**
  - SOCKS5: 127.0.0.1:10808 [کپی]
  - HTTP: 127.0.0.1:10809 [کپی]
  - توضیح: فقط وقتی VPN وصله، برای همین گوشی یا TV از طریق هات‌اسپات

- کارت 2: **SOCKS5 اختصاصی (رایگان - بدون VPN)**
  - `socks5://user:pass@1.2.3.4:1080` [کپی] [QR]
  - آموزش تلگرام، مرورگر

- کارت 3: **HTTP اختصاصی (رایگان)**
  - `http://user:pass@1.2.3.4:8080` [کپی]

- کارت 4: **MTProto تلگرام (رایگان)**
  - `https://t.me/proxy?server=...` [باز کردن در تلگرام]
  - توضیح: تلگرام بدون فیلترشکن

**2. آموزش‌ها:**
- تلگرام SOCKS5: Settings → Data and Storage → Proxy → Add Proxy
- تلگرام MTProto: کلیک روی لینک
- کروم: Settings → Proxy
- ویندوز: Settings → Proxy → Manual

---

### فاز 2: v4.0.19 - پلن پروکسی فروشی (هفته بعد)

#### Backend:

**1. پلن جدید:**
- در `plans` یک کتگوری جدید: `category = 'پروکسی'` یا ستون `is_proxy_only`
- `is_proxy_only = 1` → فقط پروکسی، نه VPN
- قیمت ارزان‌تر: مثلا 30% قیمت VPN
- ترافیک کمتر: مثلا 50GB به جای 100GB

**2. کلاینت پروکسی:**
- وقتی کاربر پلن پروکسی میخره، `clients` با `plan_id` پروکسی ساخته میشه
- فقط `proxy_configs` براش ساخته میشه، نه کانفیگ VLESS کامل

**3. API:**
- `GET /api/v1/app/proxies` → برای همه (VPN و پروکسی)
- اگر `is_proxy_only` → فقط پروکسی برمیگردونه
- اگر VPN → هم VPN هم پروکسی رایگان

**4. ربات تلگرام:**
- دکمه جدید "خرید پروکسی"
- نمایش پلن‌های پروکسی

#### Frontend:

**1. در اپ:**
- اگر کاربر پلن پروکسی داره، داشبورد متفاوت:
  - به جای دکمه اتصال VPN، نمایش پروکسی‌ها
  - دکمه "اتصال VPN" غیرفعال یا مخفی
  - پیام: "شما پلن پروکسی دارید - از تنظیمات پروکسی استفاده کنید"

**2. در وب پنل (ادمین):**
- صفحه `proxies` برای مدیریت
- نمایش ترافیک پروکسی هر کاربر
- فعال/غیرفعال کردن

---

## پیاده‌سازی فنی دقیق - با سرورهای فعلی

### گزینه A: استفاده از Xray inbound (توصیه میشه)

**مزایا:**
- نیاز به سرور جدید نیست
- از همان نودهای فعلی استفاده میشه
- Xray خودش SOCKS و HTTP ساپورت میکنه
- ترافیک با Xray حساب میشه

**مراحل:**

1. **در هر نود Xray، کانفیگ را آپدیت کنید:**
```json
{
  "inbounds": [
    {
      "port": 1080,
      "protocol": "socks",
      "settings": {
        "auth": "password",
        "accounts": [
          {"user": "user1", "pass": "pass1"},
          {"user": "user2", "pass": "pass2"}
        ],
        "udp": true,
        "ip": "0.0.0.0"
      }
    },
    {
      "port": 8080,
      "protocol": "http",
      "settings": {
        "accounts": [
          {"user": "user1", "pass": "pass1"}
        ]
      }
    },
    // ... سایر inbound های VLESS
  ]
}
```

2. **پنل - `DriverFactory` و `XrayDriver`:**
- متد جدید `createProxyAccount($client, $type)` که اکانت SOCKS/HTTP روی نود میسازه
- از API Marzban/Xray برای اضافه کردن یوزر

3. **پنل - `ProxyController`:**
```php
class ProxyController {
  public function appProxies() {
    $client = authenticate...
    $proxies = [];
    // SOCKS5
    $proxies['socks'] = [
      'url' => "socks5://{$client['username']}:{$client['password']}@{$node['host']}:1080",
      'host' => $node['host'],
      'port' => 1080,
      'username' => $client['username'],
      'password' => $client['password']
    ];
    // HTTP
    $proxies['http'] = [...];
    // MTProto - نیاز به سرور جدا یا همین سرور با پورت 443
    $proxies['mtproto'] = [
      'url' => "https://t.me/proxy?server={$node['host']}&port=443&secret=...",
      'secret' => $secret
    ];
    // Local
    $proxies['local'] = [
      'socks' => '127.0.0.1:10808',
      'http' => '127.0.0.1:10809'
    ];
    jsonSuccess($proxies);
  }
}
```

### گزینه B: 3proxy مستقل (اگر Xray inbound نشد)

**اگر نودهای Marzban اجازه inbound جدید نمیدن:**

1. روی هر سرور، 3proxy نصب:
```bash
docker run -d --name 3proxy -p 1080:1080 -p 8080:8080 \
  -v /etc/3proxy:/etc/3proxy \
  3proxy/3proxy
```

2. کانفیگ `/etc/3proxy/3proxy.cfg`:
```
auth strong
users user1:CL:pass1
users user2:CL:pass2
allow user1,user2
proxy -p1080
proxy -p8080 -h
```

3. پنل کرون هر 5 دقیقه این فایل را از `clients` میسازه

**مزایا:**
- مستقل از Xray
- حتی اگر Xray down باشه کار میکنه

**معایب:**
- نیاز به نصب نرم‌افزار جدا
- منابع بیشتر

---

## پیشنهاد نهایی من برای شما

**با توجه به اینکه گفتید:**
- رایگان برای VPN + فروشی جدا
- سرورهای فعلی
- تلگرام + سایر برنامه‌ها

**من پیشنهاد میکنم گزینه A + MTProto:**

### v4.0.18 (همین هفته - رایگان):
1. **اپ:** نمایش پروکسی محلی 127.0.0.1:10808/10809 + آموزش تلگرام
2. **پنل:** API `/api/v1/app/proxies` که SOCKS5/HTTP از نود اصلی کاربر میسازه
3. **اپ:** تب پروکسی با 3 کارت: محلی، SOCKS5 اختصاصی، HTTP اختصاصی

### v4.0.19 (هفته بعد - فروشی):
1. **پنل:** پلن جدید `is_proxy_only` + جدول `proxy_configs`
2. **پنل:** کرون sync_proxy + MTProto
3. **اپ:** تشخیص پلن پروکسی + UI متفاوت
4. **ربات:** دکمه خرید پروکسی

### v4.0.20 (ماه بعد - کامل):
1. ترافیک پروکسی جدا
2. آمار پروکسی در پنل ادمین
3. فروش پروکسی با قیمت دلخواه

---

## کد نمونه برای شروع v4.0.18

### پنل - `controllers/ProxyController.php`:
```php
class ProxyController {
  public function appProxies() {
    $client = self::authenticateClientApp();
    $pdo = Database::getConnection();
    $node = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ?")->execute([$client['server_id']])->fetch();
    
    $proxies = [
      'local' => [
        'socks' => '127.0.0.1:10808',
        'http' => '127.0.0.1:10809',
        'note' => 'فقط وقتی VPN وصله'
      ],
      'socks' => [
        'url' => "socks5://{$client['username']}:{$client['password']}@{$node['host']}:1080",
        'host' => $node['host'],
        'port' => 1080,
        'username' => $client['username'],
        'password' => $client['password']
      ],
      'http' => [
        'url' => "http://{$client['username']}:{$client['password']}@{$node['host']}:8080",
        'host' => $node['host'],
        'port' => 8080
      ],
      'mtproto' => [
        'url' => "https://t.me/proxy?server={$node['host']}&port=443&secret=ee0000000000000000000000000000000000000000",
        'tg_url' => "tg://proxy?server={$node['host']}&port=443&secret=ee..."
      ]
    ];
    
    self::jsonSuccess($proxies);
  }
}
```

### اپ - `lib/screens/proxy_screen.dart`:
```dart
class ProxyScreen extends StatelessWidget {
  // نمایش 4 کارت پروکسی + آموزش
}
```

---

## جمع‌بندی

**بله، میشه و خیلی هم خوب میشه:**
- ✅ از سرورهای فعلی استفاده میشه (Xray inbound)
- ✅ رایگان برای VPN + پلن جدا فروشی
- ✅ تلگرام (MTProto + SOCKS5) + سایر برنامه‌ها (HTTP, SOCKS)
- ✅ ترافیک قابل محاسبه جدا
- ✅ قابل فروش با قیمت ارزان‌تر

**اگر موافقید، v4.0.18 را با پروکسی رایگان (محلی + اختصاصی) شروع کنم؟**
```

