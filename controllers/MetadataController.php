<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';

class MetadataController {
    public function index(): void {
        Auth::requireLogin();
        $pdo = Database::getConnection();
        $userId = Auth::id();

        $meta = $pdo->query("SELECT * FROM branding_metadata WHERE user_id = $userId")->fetch();
        if (!$meta) {
            $pdo->prepare("INSERT INTO branding_metadata (user_id, brand_name, theme_color) VALUES (?, 'Connectix VPN', 'violet')")->execute([$userId]);
            $meta = $pdo->query("SELECT * FROM branding_metadata WHERE user_id = $userId")->fetch();
        }

        require __DIR__ . '/../views/settings/metadata.php';
    }

    public function update(): void {
        Auth::requireLogin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('settings/metadata');
        }

        $userId = Auth::id();
        $brandName = trim($_POST['brand_name'] ?? '');
        $themeColor = trim($_POST['theme_color'] ?? 'violet');
        $logoUrl = trim($_POST['logo_url'] ?? '');
        $telegram = trim($_POST['telegram_support'] ?? '');
        $whatsapp = trim($_POST['whatsapp_support'] ?? '');
        $welcome = trim($_POST['welcome_message'] ?? '');
        $renewalUrl = trim($_POST['renewal_url'] ?? '');

        // Handle file upload if provided
        if (!empty($_FILES['logo_file']['name']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../assets/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'svg', 'webp'])) {
                $filename = 'logo_' . $userId . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $uploadDir . $filename)) {
                    $logoUrl = Helpers::url('assets/uploads/' . $filename);
                }
            }
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE branding_metadata SET 
            brand_name = ?, 
            theme_color = ?, 
            logo_url = ?, 
            telegram_support = ?, 
            whatsapp_support = ?, 
            welcome_message = ?, 
            renewal_url = ?,
            updated_at = CURRENT_TIMESTAMP
            WHERE user_id = ?");
        $stmt->execute([$brandName, $themeColor, $logoUrl, $telegram, $whatsapp, $welcome, $renewalUrl, $userId]);

        if (Auth::isAdmin() && isset($_POST['sublink_custom_domain'])) {
            require_once __DIR__ . '/../core/Setting.php';
            Setting::set('sublink_custom_domain', trim($_POST['sublink_custom_domain']));
        }

        Helpers::flash('success', 'تنظیمات برند و شخصی‌سازی ظاهر با موفقیت ذخیره شد.');
        Helpers::redirect('settings/metadata');
    }

    public function backup(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();

        $filename = 'backup_connectix_' . date('Y-m-d_H-i') . '.sql';
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        echo "-- ====================================================\n";
        echo "-- Connectix System Full Database Backup\n";
        echo "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
        echo "-- Version: " . Updater::CURRENT_VERSION . "\n";
        echo "-- ====================================================\n\n";
        echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        $tables = [
            'categories', 'users', 'server_nodes', 'plans', 'clients', 
            'reserved_plans', 'transactions', 'branding_metadata', 'notifications', 
            'system_settings', 'bot_orders', 'bot_sessions', 'bot_users', 
            'reseller_plans', 'reseller_applications', 'trial_logs', 'crypto_payments', 
            'app_guides', 'coupons', 'tickets', 'ticket_messages', 'activity_logs'
        ];

        foreach ($tables as $t) {
            try {
                $rows = $pdo->query("SELECT * FROM `{$t}`")->fetchAll();
                if (!empty($rows)) {
                    echo "-- Table: `{$t}` (" . count($rows) . " rows)\n";
                    foreach ($rows as $row) {
                        $cols = array_keys($row);
                        $colList = '`' . implode('`, `', $cols) . '`';
                        $vals = [];
                        foreach ($row as $v) {
                            $vals[] = ($v === null) ? 'NULL' : $pdo->quote((string)$v);
                        }
                        $valList = implode(', ', $vals);
                        echo "REPLACE INTO `{$t}` ({$colList}) VALUES ({$valList});\n";
                    }
                    echo "\n";
                }
            } catch (Throwable $e) {}
        }

        echo "SET FOREIGN_KEY_CHECKS = 1;\n";
        exit;
    }

    public function backupTelegram(): void {
        Auth::requireAdmin();
        $pdo = Database::getConnection();

        $filename = 'backup_connectix_' . date('Y-m-d_H-i') . '.sql';
        $tempPath = sys_get_temp_dir() . '/' . $filename;
        $handle = fopen($tempPath, 'w');

        fwrite($handle, "-- ====================================================\n");
        fwrite($handle, "-- Connectix System Full Database Backup\n");
        fwrite($handle, "-- Generated on: " . date('Y-m-d H:i:s') . "\n");
        fwrite($handle, "-- Version: " . Updater::CURRENT_VERSION . "\n");
        fwrite($handle, "-- ====================================================\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

        $tables = [
            'categories', 'users', 'server_nodes', 'plans', 'clients', 
            'reserved_plans', 'transactions', 'branding_metadata', 'notifications', 
            'system_settings', 'bot_orders', 'bot_sessions', 'bot_users', 
            'reseller_plans', 'reseller_applications', 'trial_logs', 'crypto_payments', 
            'app_guides', 'coupons', 'tickets', 'ticket_messages', 'activity_logs'
        ];

        foreach ($tables as $t) {
            try {
                $rows = $pdo->query("SELECT * FROM `{$t}`")->fetchAll();
                if (!empty($rows)) {
                    fwrite($handle, "-- Table: `{$t}` (" . count($rows) . " rows)\n");
                    foreach ($rows as $row) {
                        $cols = array_keys($row);
                        $colList = '`' . implode('`, `', $cols) . '`';
                        $vals = [];
                        foreach ($row as $v) {
                            $vals[] = ($v === null) ? 'NULL' : $pdo->quote((string)$v);
                        }
                        fwrite($handle, "REPLACE INTO `{$t}` ({$colList}) VALUES (" . implode(', ', $vals) . ");\n");
                    }
                    fwrite($handle, "\n");
                }
            } catch (Throwable $e) {}
        }
        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($handle);

        require_once __DIR__ . '/../core/TelegramBot.php';
        $caption = "📦 <b>پشتیبان‌گیری کامل پایگاه داده دیتابیس</b>\n📅 تاریخ: " . date('Y-m-d H:i:s') . "\n🛡 سیستم امنیتی Connectix Panel";
        $sent = TelegramBot::sendTopicLog('backup_all', $caption, null, $tempPath);
        if (!$sent) {
            $sent = TelegramBot::sendDocument($tempPath, $caption);
        }
        @unlink($tempPath);

        Helpers::logActivity('backup_telegram', 'تولید و ارسال فایل پشتیبان کامل به تلگرام', 'system');

        if ($sent) {
            Helpers::flash('success', 'فایل پشتیبان کامل ۲۲ جدول دیتابیس با موفقیت به تلگرام ارسال شد!');
        } else {
            Helpers::flash('info', 'فایل پشتیبان تولید شد (در صورت عدم دریافت، توکن ربات و Chat ID ادمین را در تنظیمات ربات بررسی نمایید).');
        }

        Helpers::redirect('settings/metadata');
    }

    public function restore(): void {
        Auth::requireAdmin();
        if (!Helpers::verifyCsrf()) {
            Helpers::flash('error', 'توکن امنیتی نامعتبر است.');
            Helpers::redirect('settings/metadata');
        }

        if (empty($_FILES['backup_file']['tmp_name']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            Helpers::flash('error', 'لطفاً یک فایل معتبر بکاپ با پسوند .sql انتخاب فرمایید.');
            Helpers::redirect('settings/metadata');
        }

        $file = $_FILES['backup_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'sql') {
            Helpers::flash('error', 'فرمت فایل ارسالی نامعتبر است. فقط فایل‌های .sql پشتیبانی می‌شوند.');
            Helpers::redirect('settings/metadata');
        }

        $sqlContent = file_get_contents($file['tmp_name']);
        if (empty($sqlContent)) {
            Helpers::flash('error', 'فایل ارسالی خالی است.');
            Helpers::redirect('settings/metadata');
        }

        $result = Database::restoreFromSql($sqlContent);
        if ($result['success']) {
            Helpers::logActivity('backup_restore', "بازگردانی پایگاه داده از فایل {$file['name']} با موفقیت انجام شد ({$result['executed']} دستور اجرا شد)", 'system');
            Helpers::flash('success', "پایگاه داده با موفقیت بازگردانی شد! ({$result['executed']} دستور با موفقیت اعمال گردید)");
        } else {
            Helpers::flash('error', 'خطا در بازگردانی فایل: ' . ($result['error'] ?? 'خطای ناشناخته'));
        }

        Helpers::redirect('settings/metadata');
    }
}
