<?php
/**
 * Connectix Panel & Telegram Bot Configuration
 * 
 * راهنما:
 * ۱. اگر از نصب‌کننده هوشمند استفاده می‌کنید، کافیست آدرس پنل را با /install.php در مرورگر باز کنید.
 * ۲. در صورت نصب دستی، این فایل را به config.php تغییر نام داده و مشخصات دیتابیس MySQL خود را وارد فرمایید.
 */

// نام برند سامانه و آدرس اصلی
define('APP_NAME', 'کانکتیکس پنل');
define('APP_ENV', 'production');
define('APP_URL', 'https://your-domain.com'); // آدرس کامل دامنه هاست (بدون اسلش انتهایی)

// تنظیمات پایگاه داده (MySQL / MariaDB)
define('DB_DRIVER', 'mysql'); // در هاست cPanel روی mysql باشد
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'cpaneluser_connectix'); // نام دیتابیس ساخته‌شده در cPanel
define('DB_USER', 'cpaneluser_dbuser');    // نام کاربری دیتابیس
define('DB_PASS', 'YourStrongPassword123!'); // رمز عبور دیتابیس
define('SQLITE_PATH', __DIR__ . '/data/panel.sqlite'); // در صورت عدم اتصال MySQL به عنوان پشتیبان استفاده می‌شود

// کلید رمزنگاری امنیتی سامانه (۳۲ الی ۶۴ کاراکتر تصادفی)
define('APP_SECRET', 'c7f91a8e2b4d603f95e1b7c8a3d5e2f104b6c8e9f2a1b3c5d7e9f1a2b4c6d8e0');

// ربات تلگرام (می‌توانید بعداً از داخل منوی تنظیمات پنل نیز وارد کنید)
define('TELEGRAM_BOT_TOKEN', '');
define('TELEGRAM_ADMIN_CHAT_ID', '');

// منطقه زمانی و دیباگ
date_default_timezone_set('Asia/Tehran');
define('APP_DEBUG', false);

if (APP_DEBUG) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
