# پیشنهادهای فنی برای ساب‌لینک دقیق سرور اصلی - Connectix Panel

## درخواست شما (خلاصه):
> توی پنل میخوام دقیقا ساب لینک همون سرور اصلی بیاد بدون اینکه به لینکش چیزی اضافه شده باشه یعنی وقتی هر سرور جدیدی اضافه میکنم دقیقا همون ساب لینک سرور اصلی استخراج بشه و در صورتی که یوزر و پسورد هم همراه با ساب ارائه میکنه که همونو استخراج کنه در غیر اینصورت پنل خودم براش یوزر و پسورد بسازه که بشه به آپ خودم هم بدون وارد کردن ساب لینک اتصال داده بشه

---

## وضعیت فعلی پنل (بررسی عمیق):

### چگونه کار میکند الان:
1. **افزودن سرور:** در `ServerController::store()` سرور جدید با `api_url` اضافه میشود
2. **همگام‌سازی:** `NodeSync::syncServer()` تمام یوزرهای سرور اصلی را از API میگیرد
3. **استخراج ساب:** هر یوزر دارای `subscription_url` از سرور اصلی است که در `clients.node_sublink` ذخیره میشود **دقیقا همان لینک اصلی بدون تغییر**
4. **پسورد:** اگر سرور اصلی پسورد اصلی را بدهد (`$u['password']`) همان ذخیره میشود در `original_password` و به عنوان `password` پنل استفاده میشود، در غیر اینصورت پسورد رندوم ساخته میشود
5. **ساب پنل:** پنل یک `sub_token` رندوم 24 کاراکتری میسازد و لینک `https://vpbotn.ir/sub/{sub_token}` را به عنوان ساب پروکسی ارائه میدهد که محتوای `node_sublink` را پروکسی میکند

### مشکل فعلی از دید شما:
- ساب لینک پنل (`/sub/{token}`) یک لایه اضافه است، شما میخواهید **مستقیما همان ساب سرور اصلی** بدون هیچ پیشوند/پسوند
- اگر ساب سرور اصلی یوزر/پسورد نداشته باشد، پنل باید خودش بسازد تا اپ شما بدون وارد کردن ساب لینک با یوزر/پسورد وصل شود

---

## 3 پیشنهاد فنی (با مزایا/معایب):

### پیشنهاد 1: حالت Direct Passthrough (دقیقا همان ساب اصلی - ساده‌ترین)

**ایده:** وقتی سرور جدید اضافه میشود، ساب‌لینک هر یوزر **عینا** همان لینک سرور اصلی ذخیره و ارائه شود، بدون هیچ `/sub/` یا توکن پنل.

**پیاده‌سازی:**
```php
// در NodeSync::syncServer - خط 104-115
$nodeSub = (string)($u['subscription_url'] ?? ''); // دقیقا همان لینک اصلی
// ذخیره بدون تغییر
// در ApiController::getSubscription - اگر node_sublink موجود است، مستقیما محتوای آن را برگردان یا ریدایرکت کن

// در ClientController - نمایش ساب:
if (!empty($client['node_sublink'])) {
    $displaySub = $client['node_sublink']; // عینا همان
} else {
    $displaySub = Helpers::subUrl($client['sub_token']); // fallback پنل
}
```

**در پنل - تنظیم جدید:**
- در `settings` یک گزینه: `sublink_mode = 'direct' | 'panel_proxy' | 'dual'`
- اگر `direct`: API `/sub/{token}` به جای پروکسی کردن، 302 ریدایرکت به `node_sublink` اصلی میکند یا مستقیما محتوای آن را برمیگرداند بدون تغییر هدر

**استخراج یوزر/پسورد:**
- اگر `subscription_url` شامل `?username=xxx&password=yyy` یا در JSON داخل ساب یوزر/پسورد باشد، استخراج:
```php
// مثال: https://main.com/sub/abc123?user=ali&pass=123
parse_str(parse_url($sub, PHP_URL_QUERY), $q);
$extractedUser = $q['username'] ?? $q['user'] ?? null;
$extractedPass = $q['password'] ?? $q['pass'] ?? null;

// اگر ساب خودش یک JSON با لیست کانفیگ‌هاست، داخل کانفیگ‌ها یوزر را از VLESS uuid استخراج کن
```

**اگر یوزر/پسورد نداشت:**
- پنل خودش میسازد: `username = original username`, `password = generatePassword()` (همین الان هم این کار را میکند)
- این یوزر/پسورد در پنل ذخیره میشود و اپ شما با همین یوزر/پسورد لاگین میکند و به سرورها وصل میشود (بدون نیاز به وارد کردن ساب لینک)

**مزایا:**
- ✅ دقیقا همان ساب اصلی، بدون اضافه
- ✅ ساده، کمترین تغییر کد
- ✅ سازگار با اپ‌های دیگر (V2RayNG, etc.) که ساب مستقیم میخواهند

**معایب:**
- ❌ اگر سرور اصلی فیلتر شود، ساب مستقیم هم فیلتر میشود (پنل پروکسی این را حل میکرد)
- ❌ آمار مصرف دقیق از طریق پنل سخت‌تر (چون ترافیک مستقیم از سرور اصلی می‌آید نه از طریق پنل)

---

### پیشنهاد 2: حالت Dual Mode (دو ساب - هم مستقیم هم پنلی - پیشنهادی من)

**ایده:** هر کلاینت **دو ساب** داشته باشد:
1. `direct_sublink` = عینا ساب سرور اصلی (بدون تغییر)
2. `panel_sublink` = ساب پنل شما (`/sub/{token}`) که یوزر/پسورد پنل را دارد و آمار و مدیریت کامل

**پیاده‌سازی دیتابیس:**
```sql
ALTER TABLE clients ADD COLUMN direct_sublink TEXT NULL; -- ساب دقیق اصلی
ALTER TABLE clients ADD COLUMN panel_sublink TEXT NULL; -- ساب پنل (فعلی sub_token)
-- node_sublink فعلی = direct_sublink
```

**در NodeSync:**
```php
$directSub = $u['subscription_url']; // عینا اصلی
$panelSub = Helpers::subUrl($newSubToken); // ساب پنل

// ذخیره هر دو
INSERT INTO clients (direct_sublink, sub_token, node_sublink, ...) VALUES ($directSub, $token, $directSub, ...)
```

**در پنل UI:**
- در صفحه جزئیات کلاینت دو دکمه:
  - 📋 کپی ساب مستقیم (سرور اصلی)
  - 📋 کپی ساب پنل (با یوزر/پسورد پنل - برای اپ خودت)

**در اپ شما:**
- اگر یوزر با یوزر/پسورد لاگین کند (بدون ساب لینک)، پنل `panel_sublink` را در پس‌زمینه میگیرد و سرورها را لود میکند
- اگر یوزر ساب مستقیم را وارد کند، مستقیما همان را استفاده میکند

**استخراج یوزر/پسورد از ساب:**
```php
function extractCredentialsFromSub($subUrl) {
    // 1. از خود URL query param
    $q = [];
    parse_str(parse_url($subUrl, PHP_URL_QUERY) ?? '', $q);
    if (!empty($q['username']) && !empty($q['password'])) {
        return [$q['username'], $q['password']];
    }
    // 2. از محتوای ساب (اگر base64 یا JSON است)
    $content = @file_get_contents($subUrl);
    if ($content) {
        // اگر ساب شامل vmess:// یا vless:// باشد، از اولین کانفیگ uuid را به عنوان پسورد استخراج کن
        if (preg_match('/vless:\/\/([^@]+)@/', $content, $m)) {
            return [null, $m[1]]; // uuid به عنوان پسورد
        }
    }
    return [null, null];
}

// اگر استخراج نشد، پنل میسازد:
if (empty($user) || empty($pass)) {
    $user = $username; // همان یوزر سرور اصلی
    $pass = Helpers::generatePassword(12); // رندوم امن
}
```

**مزایا:**
- ✅ هم ساب دقیق اصلی را داری (برای فروش به بیرون یا اپ‌های دیگر)
- ✅ هم ساب پنلی با یوزر/پسورد برای اپ خودت (بدون وارد کردن ساب لینک)
- ✅ بهترین هر دو دنیا - آمار پنل + ساب مستقیم
- ✅ اگر سرور اصلی فیلتر شد، ساب پنلی از طریق دامنه vpbotn.ir همچنان کار میکند

**معایب:**
- ❌ کمی پیچیده‌تر (یک ستون جدید + دو دکمه در UI)

**این پیشنهاد من است - چون هم خواسته شما (ساب دقیق) را میدهد و هم نیاز اپ خودت (یوزر/پسورد بدون ساب) را حل میکند.**

---

### پیشنهاد 3: حالت Smart Auto-Create (هوشمند - یوزر/پسورد خودکار)

**ایده:** پنل هنگام افزودن سرور جدید:
1. ساب لینک اصلی را **دقیقا** استخراج میکند
2. سعی میکند از داخل ساب، یوزر/پسورد را استخراج کند (از URL یا از محتوای کانفیگ)
3. اگر موفق شد، همان را ذخیره میکند
4. اگر نشد، **خودش یوزر/پسورد میسازد و روی سرور اصلی هم همان یوزر را میسازد** (via API)، سپس ساب جدید را میگیرد

**پیاده‌سازی - مرحله به مرحله:**

**مرحله 1: استخراج دقیق ساب:**
```php
// در Driver - مثلا MarzbanDriver::listUsers()
foreach ($apiUsers as $u) {
    $subUrl = $u['subscription_url']; // https://main.com:8000/sub/xxxx
    // این دقیقا همان ساب اصلی است - بدون تغییر ذخیره کن
}
```

**مرحله 2: استخراج یوزر/پسورد از ساب:**
```php
// تابع جدید در Helpers:
public static function extractUserPassFromSub($subUrl) {
    // حالت A: ساب URL شامل credential است
    // مثال: https://sub.example.com/sub/abc?username=ali&password=123456
    $parsed = parse_url($subUrl);
    if (!empty($parsed['query'])) {
        parse_str($parsed['query'], $q);
        if (!empty($q['username']) && !empty($q['password'])) {
            return ['user' => $q['username'], 'pass' => $q['password'], 'source' => 'url_query'];
        }
        if (!empty($q['user']) && !empty($q['pass'])) {
            return ['user' => $q['user'], 'pass' => $q['pass'], 'source' => 'url_query'];
        }
    }
    
    // حالت B: محتوای ساب را بگیر و از داخلش استخراج کن
    try {
        $content = @file_get_contents($subUrl, false, stream_context_create(['http'=>['timeout'=>5]]));
        if ($content) {
            // اگر base64 است decode کن
            $decoded = base64_decode($content, true);
            if ($decoded !== false) $content = $decoded;
            
            // از اولین کانفیگ VLESS/VMess یوزر را استخراج کن
            // vless://uuid@host:port?type=...
            if (preg_match('/vless:\/\/([a-f0-9\-]{36})@/i', $content, $m)) {
                // uuid پیدا شد - میتواند به عنوان پسورد استفاده شود
                // یوزر را از نام فایل یا از خود ساب URL استخراج کن
                $username = basename(parse_url($subUrl, PHP_URL_PATH)); // abc123 از /sub/abc123
                return ['user' => $username, 'pass' => $m[1], 'source' => 'vless_uuid'];
            }
            // vmess:// base64 json
            if (preg_match('/vmess:\/\/([A-Za-z0-9+\/=]+)/', $content, $m)) {
                $vmessJson = json_decode(base64_decode($m[1]), true);
                if (!empty($vmessJson['id'])) {
                    return ['user' => $vmessJson['ps'] ?? 'user', 'pass' => $vmessJson['id'], 'source' => 'vmess_id'];
                }
            }
        }
    } catch (Exception $e) {}
    
    return null; // استخراج نشد
}
```

**مرحله 3: اگر استخراج نشد، پنل میسازد:**
```php
$creds = Helpers::extractUserPassFromSub($nodeSub);
if ($creds) {
    $panelUser = $creds['user'];
    $panelPass = $creds['pass'];
    $source = $creds['source'];
} else {
    // پنل خودش میسازد
    $panelUser = $username; // همان یوزر سرور اصلی
    $panelPass = bin2hex(random_bytes(8)); // 16 کاراکتر رندوم امن
    $source = 'panel_generated';
    
    // اختیاری: اگر API سرور اصلی اجازه ساخت یوزر با پسورد دلخواه را میدهد، همان را روی سرور اصلی هم بساز
    // تا ساب جدید شامل همان پسورد باشد
    try {
        $driver->createUser($panelUser, $panelPass, $traffic, $expire);
        // سپس ساب جدید را بگیر
        $newSub = $driver->getUserSubUrl($panelUser);
        if ($newSub) $nodeSub = $newSub;
    } catch (Exception $e) {
        // اگر نشد، فقط در پنل ذخیره کن - اپ با یوزر/پسورد پنل لاگین میکند و ساب پنل را میگیرد
    }
}

// ذخیره در پنل:
INSERT INTO clients (username, password, original_password, direct_sublink, sub_token, node_sublink, credential_source) 
VALUES ($panelUser, $panelPass, $originalPass, $nodeSub, $token, $nodeSub, $source)
```

**در اپ شما (بدون وارد کردن ساب لینک):**
- کاربر فقط `username` و `password` (همان که پنل ساخته) را وارد میکند
- اپ به `https://vpbotn.ir/api/v1/app/login` با یوزر/پسورد درخواست میدهد
- پنل چک میکند: اگر `credential_source = panel_generated` باشد، مستقیما `direct_sublink` (ساب دقیق اصلی) را برمیگرداند
- اپ بدون نیاز به وارد کردن ساب لینک، مستقیما به سرور اصلی وصل میشود

**مزایا:**
- ✅ دقیقا ساب اصلی بدون اضافه
- ✅ اگر ساب اصلی یوزر/پسورد داشت، همان استخراج میشود
- ✅ اگر نداشت، پنل میسازد و اپ بدون ساب لینک کار میکند
- ✅ هوشمند و خودکار

**معایب:**
- ❌ پیچیده‌ترین - نیاز به تغییر Driver و NodeSync و Helpers
- ❌ ساخت یوزر روی سرور اصلی ممکن است با محدودیت API مواجه شود

---

## مقایسه سریع:

| معیار | پیشنهاد 1 Direct | پیشنهاد 2 Dual (پیشنهادی) | پیشنهاد 3 Smart Auto |
|-------|------------------|---------------------------|----------------------|
| ساب دقیق اصلی | ✅ عینا | ✅ عینا + ساب پنلی هم | ✅ عینا |
| یوزر/پسورد از ساب | ✅ اگر باشد | ✅ اگر باشد + پنل میسازد | ✅ هوشمند استخراج + ساخت |
| اپ بدون ساب لینک | ⚠️ فقط اگر پنل یوزر/پسورد داشته باشد | ✅ بله - با یوزر/پسورد پنل | ✅ بله - خودکار |
| پیچیدگی | کم | متوسط | زیاد |
| آمار پنل | ❌ از دست میرود | ✅ کامل (از طریق ساب پنلی) | ✅ کامل |
| مقاومت در برابر فیلتر | ❌ ساب اصلی فیلتر = قطع | ✅ ساب پنلی از دامنه vpbotn.ir کار میکند | ✅ |
| زمان پیاده‌سازی | 1-2 ساعت | 3-4 ساعت | 6-8 ساعت |

---

## پیشنهاد نهایی من (ترکیب 2 + 3):

**برای شما پیشنهاد میکنم پیشنهاد 2 (Dual Mode) را با هوشمندی پیشنهاد 3 ترکیب کنیم:**

1. **دیتابیس:** یک ستون `direct_sublink` اضافه کنیم (ساب دقیق اصلی بدون تغییر)
2. **NodeSync:** ساب اصلی را **عینا** ذخیره کند در `direct_sublink` و `node_sublink`
3. **استخراج یوزر/پسورد:** تابع `extractUserPassFromSub()` اضافه شود که از URL و محتوای ساب یوزر/پسورد را استخراج کند
4. **اگر استخراج نشد:** پنل خودش `username` و `password` رندوم امن میسازد و ذخیره میکند
5. **پنل UI:** در صفحه کلاینت دو دکمه:
   - `کپی ساب مستقیم (اصلی)` → `direct_sublink`
   - `کپی ساب پنل (برای اپ خودت)` → `https://vpbotn.ir/sub/{token}` که با یوزر/پسورد پنل کار میکند
6. **اپ:** وقتی کاربر با یوزر/پسورد لاگین میکند (بدون وارد کردن ساب لینک)، پنل `direct_sublink` را در پس‌زمینه برمیگرداند و اپ مستقیما به سرور اصلی وصل میشود

**این way:**
- ✅ شما ساب دقیق اصلی را بدون اضافه داری (برای فروش یا استفاده در V2RayNG)
- ✅ اپ خودت بدون وارد کردن ساب لینک با یوزر/پسورد کار میکند
- ✅ اگر ساب اصلی یوزر/پسورد داشت همان استخراج میشود، اگر نه پنل میسازد
- ✅ آمار کامل در پنل حفظ میشود (از طریق لاگین یوزر/پسورد)

---

## نمونه کد آماده برای پیاده‌سازی (اگر تایید کردی):

```php
// فایل جدید: core/SublinkExtractor.php
class SublinkExtractor {
    public static function extractExactSub($apiUser) {
        // دقیقا همان ساب سرور اصلی بدون هیچ تغییر
        return trim($apiUser['subscription_url'] ?? '');
    }
    
    public static function extractCredentials($subUrl, $fallbackUsername) {
        // سعی کن از ساب یوزر/پسورد استخراج کنی
        // 1. از query param
        $parsed = parse_url($subUrl);
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $q);
            if (!empty($q['username']) && !empty($q['password'])) {
                return [$q['username'], $q['password'], 'url'];
            }
        }
        // 2. از محتوای ساب
        // ... (کد کامل بالا)
        
        // 3. اگر نشد، پنل بسازد
        return [$fallbackUsername, bin2hex(random_bytes(8)), 'panel_generated'];
    }
}

// در NodeSync::syncServer
$exactSub = SublinkExtractor::extractExactSub($u); // عینا اصلی
list($panelUser, $panelPass, $source) = SublinkExtractor::extractCredentials($exactSub, $username);

// ذخیره
INSERT INTO clients (username, password, direct_sublink, node_sublink, sub_token, credential_source)
VALUES ($panelUser, $panelPass, $exactSub, $exactSub, $token, $source)
```

---

## سوال از شما:

1. **کدام پیشنهاد را میپسندی؟** (من پیشنهاد 2 ترکیبی را توصیه میکنم)
2. **آیا میخواهی ساب مستقیم به صورت ریدایرکت 302 به ساب اصلی باشد یا محتوای ساب اصلی مستقیما از طریق دامنه پنل سرو شود؟** (ریدایرکت ساده‌تر، پروکسی مقاوم‌تر در برابر فیلتر)
3. **آیا میخواهی وقتی پنل خودش یوزر/پسورد میسازد، همان را روی سرور اصلی هم بسازد (via API) تا ساب جدید شامل همان پسورد باشد، یا فقط در پنل بماند؟**

بگو کدام را تایید میکنی تا همین الان پیاده‌سازی کنم و روی پنل تست کنم.
