# 🚀 Connectix Dedicated Multiplatform VPN Client (Flutter + LibXray)

اپلیکیشن اختصاصی چندسکویی (Android, Windows, iOS, macOS) با قابلیت ورود مستقیم با **نام‌کاربری و رمز عبور (User/Pass)** بدون نیاز به کپی کردن لینک ساب‌لینک یا اسکن QR کد.

---

## 📱 پیش‌نیازها و کامپایل

### ۱. تنظیم آدرس پنل شما
در فایل `lib/services/api_service.dart` آدرس دامنه پنل خود را قرار دهید:
```dart
static String baseUrl = "https://your-panel-domain.com";
```

### ۲. دریافت پکیج‌ها:
```bash
flutter pub get
```

### ۳. خروجی اندروید (Android APK / AAB):
```bash
# ساخت نسخه APK عمومی
flutter build apk --release

# ساخت نسخه تفکیک شده معماری‌های پردازنده (ARM64, ARMv7):
flutter build apk --split-per-abi --release
```
فایل‌های خروجی در مسیر `build/app/outputs/flutter-apk/` قرار می‌گیرند.

### ۴. خروجی ویندوز (Windows EXE):
```bash
flutter build windows --release
```
فایل اجرایی و DLLهای وابسته در مسیر `build/windows/x64/runner/Release/` آماده تحویل و توزیع است.

---

## ⚡️ وب‌سرویس‌های متصل به پنل (API Contracts):
- `POST /api/v1/app/login`: ورود کلاینت و دریافت توکن
- `GET /api/v1/app/configs`: دریافت لیست سرورهای بهینه‌شده به همراه ساب‌لینک
- `GET /api/v1/app/profile`: استعلام زنده وضعیت حجم و روزهای باقیمانده
- `GET /api/v1/app/announcements`: دریافت اعلانات پنل در داخل اپلیکیشن
- `POST /api/v1/app/feedback`: ارسال گزارش خطا و پیام پشتیبانی
