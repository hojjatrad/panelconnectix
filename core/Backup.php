<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Setting.php';
require_once __DIR__ . '/TelegramBot.php';
require_once __DIR__ . '/Helpers.php';

class Backup {
    public static function createBackupFile(): array {
        $backupDir = sys_get_temp_dir() . '/connectix_backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }

        $dateStr = date('Y-m-d_H-i-s');
        $pdo = Database::getConnection();
        $dbType = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $dumpFileName = "connectix_db_{$dateStr}.sql";
        $dumpFilePath = "{$backupDir}/{$dumpFileName}";
        $zipFileName = "connectix_backup_{$dateStr}.zip";
        $zipFilePath = "{$backupDir}/{$zipFileName}";

        if ($dbType === 'sqlite') {
            $handle = fopen($dumpFilePath, 'w');
            fwrite($handle, "-- Connectix Panel SQLite Dump\n-- Generated: " . date('Y-m-d H:i:s') . "\n\nPRAGMA foreign_keys=OFF;\nBEGIN TRANSACTION;\n\n");
            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $tbl) {
                $createSql = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='{$tbl}'")->fetchColumn();
                fwrite($handle, "DROP TABLE IF EXISTS `{$tbl}`;\n{$createSql};\n\n");
                $rows = $pdo->query("SELECT * FROM `{$tbl}`")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $keys = array_map(fn($k) => "`{$k}`", array_keys($row));
                    $vals = array_map(function($v) use ($pdo) {
                        return $v === null ? 'NULL' : $pdo->quote((string)$v);
                    }, array_values($row));
                    fwrite($handle, "INSERT INTO `{$tbl}` (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $vals) . ");\n");
                }
                fwrite($handle, "\n");
            }
            fwrite($handle, "COMMIT;\n");
            fclose($handle);
        } else {
            $handle = fopen($dumpFilePath, 'w');
            fwrite($handle, "-- Connectix Panel MySQL Dump\n-- Generated: " . date('Y-m-d H:i:s') . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n");
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $tbl) {
                $createRow = $pdo->query("SHOW CREATE TABLE `{$tbl}`")->fetch(PDO::FETCH_NUM);
                $createSql = $createRow[1] ?? '';
                fwrite($handle, "DROP TABLE IF EXISTS `{$tbl}`;\n{$createSql};\n\n");
                $rows = $pdo->query("SELECT * FROM `{$tbl}`")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $keys = array_map(fn($k) => "`{$k}`", array_keys($row));
                    $vals = array_map(function($v) use ($pdo) {
                        return $v === null ? 'NULL' : $pdo->quote((string)$v);
                    }, array_values($row));
                    fwrite($handle, "INSERT INTO `{$tbl}` (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $vals) . ");\n");
                }
                fwrite($handle, "\n");
            }
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($handle);
        }

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $zip->addFile($dumpFilePath, $dumpFileName);
                $zip->close();
                @unlink($dumpFilePath);
                return ['success' => true, 'path' => $zipFilePath, 'filename' => $zipFileName, 'size' => filesize($zipFilePath)];
            }
        } elseif (function_exists('gzencode')) {
            $gzPath = $dumpFilePath . '.gz';
            $gzName = $dumpFileName . '.gz';
            $data = file_get_contents($dumpFilePath);
            file_put_contents($gzPath, gzencode($data, 9));
            @unlink($dumpFilePath);
            return ['success' => true, 'path' => $gzPath, 'filename' => $gzName, 'size' => filesize($gzPath)];
        }

        return ['success' => true, 'path' => $dumpFilePath, 'filename' => $dumpFileName, 'size' => filesize($dumpFilePath)];
    }

    public static function createEncryptedBackup(string $password = ''): array {
        $backup = self::createBackupFile();
        if (!$backup['success']) return $backup;
        if (empty($password)) {
            $password = Setting::get('backup_encryption_key', '');
            if (empty($password)) {
                $password = bin2hex(random_bytes(16));
                Setting::set('backup_encryption_key', $password);
            }
        }
        $data = file_get_contents($backup['path']);
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', hash('sha256', $password, true), OPENSSL_RAW_DATA, $iv);
        $result = base64_encode($iv . $encrypted);
        $encPath = $backup['path'] . '.enc';
        file_put_contents($encPath, $result);
        @unlink($backup['path']);
        return ['success' => true, 'path' => $encPath, 'filename' => $backup['filename'] . '.enc', 'size' => filesize($encPath), 'original_size' => $backup['size']];
    }

    public static function sendBackupToTelegram(bool $encrypted = true): array {
        $backup = $encrypted ? self::createEncryptedBackup() : self::createBackupFile();
        if (empty($backup['path']) || !file_exists($backup['path'])) {
            return ['success' => false, 'message' => 'خطا در ایجاد فایل پشتیبان.'];
        }
        $botToken = Setting::get('telegram_bot_token');
        $adminChatId = Setting::get('telegram_admin_chat_id');
        $channel = Setting::get('telegram_channel');
        $targetChat = !empty($channel) ? $channel : $adminChatId;
        if (empty($botToken) || empty($targetChat)) {
            return ['success' => false, 'message' => 'توکن ربات یا شناسه چت ادمین تنظیم نشده.', 'path' => $backup['path'], 'filename' => $backup['filename']];
        }
        $caption = "📦 پشتیبان خودکار کانتیکس\n📅 تاریخ: " . date('Y-m-d H:i:s') . "\n💾 حجم: " . round($backup['size'] / 1024, 2) . " KB\n" . ($encrypted ? "🔒 رمزنگاری AES-256\n" : "") . "#Backup";
        $res = TelegramBot::sendDocument($targetChat, $backup['path'], $caption, $botToken);
        if ($res) {
            Setting::set('last_telegram_backup_at', date('Y-m-d H:i:s'));
            self::cleanupOldBackups();
            if (file_exists(__DIR__ . '/SecurityLogger.php')) {
                require_once __DIR__ . '/SecurityLogger.php';
                SecurityLogger::log('backup_created', "Backup sent: {$backup['filename']}");
            }
            @unlink($backup['path']);
            return ['success' => true, 'message' => 'بکاپ به تلگرام ارسال شد.', 'filename' => $backup['filename']];
        }
        return ['success' => false, 'message' => 'ارسال به تلگرام ناموفق.'];
    }
    
    public static function cleanupOldBackups(): int {
        $backupDir = sys_get_temp_dir() . '/connectix_backups';
        if (!is_dir($backupDir)) return 0;
        $files = glob($backupDir . '/*');
        $deleted = 0;
        $now = time();
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file) > 7*24*3600)) {
                @unlink($file);
                $deleted++;
            }
        }
        return $deleted;
    }
    
    public static function checkDailyBackup(): array {
        $lastBackup = Setting::get('last_telegram_backup_at', '');
        $now = time();
        $lastTime = $lastBackup ? strtotime($lastBackup) : 0;
        if (($now - $lastTime) > 24*3600) {
            return self::sendBackupToTelegram(true);
        }
        return ['success' => false, 'message' => 'Backup not needed yet, last: ' . $lastBackup];
    }
}
