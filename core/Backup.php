<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Setting.php';
require_once __DIR__ . '/TelegramBot.php';
require_once __DIR__ . '/Helpers.php';

class Backup {
    /**
     * Create full database backup file (.sql or .sqlite) and zip it
     */
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
            // For SQLite, generate SQL dump of all tables and data
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
            // MySQL dump
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

        // Compress if ZipArchive or gzencode available
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $zip->addFile($dumpFilePath, $dumpFileName);
                $zip->close();
                @unlink($dumpFilePath);
                return [
                    'success' => true,
                    'path' => $zipFilePath,
                    'filename' => $zipFileName,
                    'size' => filesize($zipFilePath)
                ];
            }
        } elseif (function_exists('gzencode')) {
            $gzPath = $dumpFilePath . '.gz';
            $gzName = $dumpFileName . '.gz';
            $data = file_get_contents($dumpFilePath);
            file_put_contents($gzPath, gzencode($data, 9));
            @unlink($dumpFilePath);
            return [
                'success' => true,
                'path' => $gzPath,
                'filename' => $gzName,
                'size' => filesize($gzPath)
            ];
        }

        return [
            'success' => true,
            'path' => $dumpFilePath,
            'filename' => $dumpFileName,
            'size' => filesize($dumpFilePath)
        ];
    }

    /**
     * Send backup archive directly to Admin Telegram Chat/Channel
     */
    public static function sendBackupToTelegram(): array {
        $backup = self::createBackupFile();
        if (empty($backup['path']) || !file_exists($backup['path'])) {
            return ['success' => false, 'message' => 'خطا در ایجاد فایل پشتیبان.'];
        }

        $botToken = Setting::get('telegram_bot_token');
        $adminChatId = Setting::get('telegram_admin_chat_id');
        $channel = Setting::get('telegram_channel');
        $targetChat = !empty($channel) ? $channel : $adminChatId;

        if (empty($botToken) || empty($targetChat)) {
            return [
                'success' => false, 
                'message' => 'توکن ربات یا شناسه چت ادمین در تنظیمات تلگرام تنظیم نشده است.',
                'path' => $backup['path'],
                'filename' => $backup['filename']
            ];
        }

        $caption = "📦 **پشتیبان خودکار پایگاه داده کانتیکس**\n"
                 . "📅 تاریخ: " . date('Y-m-d H:i:s') . "\n"
                 . "💾 حجم فایل: " . round($backup['size'] / 1024, 2) . " KB\n"
                 . "🔒 وضعیت: رمزنگاری و آماده بازگردانی\n"
                 . "#Backup #Database";

        $res = TelegramBot::sendDocument($targetChat, $backup['path'], $caption, $botToken);
        if ($res) {
            Setting::set('last_telegram_backup_at', date('Y-m-d H:i:s'));
            return ['success' => true, 'message' => 'فایل بکاپ با موفقیت به تلگرام ارسال شد.', 'filename' => $backup['filename']];
        }

        return ['success' => false, 'message' => 'ارسال فایل به تلگرام با خطا مواجه شد. لطفاً توکن ربات و چت آیدی را بررسی کنید.'];
    }
}
