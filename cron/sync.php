<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Setting.php';
require_once __DIR__ . '/../core/TelegramBot.php';
require_once __DIR__ . '/../drivers/DriverFactory.php';

// Allow running via CLI or Web with secret token
if (php_sapi_name() !== 'cli') {
    $key = $_GET['key'] ?? '';
    if ($key !== APP_SECRET) {
        http_response_code(403);
        die("دسترسی غیرمجاز. کلید امنیتی اشتباه است.");
    }
}

$isCli = (php_sapi_name() === 'cli');
$eol = $isCli ? "\n" : "<br>\n";

echo "[" . date('Y-m-d H:i:s') . "] Starting Sync & Reserved Subscriptions Engine..." . $eol;

$pdo = Database::getConnection();

// Fetch active clients and their servers
$stmt = $pdo->query("SELECT c.*, s.name as server_name, s.driver as server_driver, s.api_url, s.api_username, s.api_password, s.api_token,
                            rp.id as reserved_id, rp.traffic_gb as reserved_gb, rp.duration_days as reserved_days
                     FROM clients c 
                     JOIN server_nodes s ON c.server_id = s.id 
                     LEFT JOIN reserved_plans rp ON rp.client_id = c.id AND rp.status = 'queued'
                     WHERE c.status != 'disabled'");
$clients = $stmt->fetchAll();

$syncedCount = 0;
$reservedActivated = 0;
$expiredCount = 0;

foreach ($clients as $c) {
    try {
        $driver = DriverFactory::create($c);
        $remoteData = $driver->getUser($c['username']);

        if ($remoteData && isset($remoteData['traffic_used_bytes'])) {
            $usedBytes = $remoteData['traffic_used_bytes'];
            $pdo->prepare("UPDATE clients SET traffic_used_bytes = ? WHERE id = ?")->execute([$usedBytes, $c['id']]);
            $c['traffic_used_bytes'] = $usedBytes;
        }

        // Check if exhausted
        $isTrafficDone = ($c['traffic_limit_bytes'] > 0 && $c['traffic_used_bytes'] >= $c['traffic_limit_bytes']);
        $isTimeDone = (!empty($c['expire_at']) && strtotime($c['expire_at']) <= time());

        // Check if traffic is nearly exhausted (>85%) but not yet expired
        if (!$isTrafficDone && !$isTimeDone && $c['traffic_limit_bytes'] > 0) {
            $usageRatio = $c['traffic_used_bytes'] / $c['traffic_limit_bytes'];
            if ($usageRatio >= 0.85) {
                // Send near-exhaustion notice if ordered via bot
                $stmtUserTg = $pdo->prepare("SELECT user_tg_id FROM bot_orders WHERE client_id = ? ORDER BY id DESC LIMIT 1");
                $stmtUserTg->execute([$c['id']]);
                $userTgId = $stmtUserTg->fetchColumn();
                if (!empty($userTgId)) {
                    $usedGb = round($c['traffic_used_bytes'] / (1024*1024*1024), 1);
                    $totalGb = round($c['traffic_limit_bytes'] / (1024*1024*1024), 1);
                    $warnNotice = "⚠️ <b>هشدار اتمام حجم اشتراک</b>\n\n"
                                . "کاربر گرامی <code>{$c['username']}</code>، بیش از ۸۵٪ از ترافیک سرویس شما مصرف شده است:\n"
                                . "📊 مصرف: <b>{$usedGb}GB</b> از <b>{$totalGb}GB</b>\n\n"
                                . "برای جلوگیری از قطع سرویس، می‌توانید همین حالا پلن تمدیدی رزرو کنید تا پس از اتمام خودکار فعال شود.";
                    $warnKeyboard = [
                        'inline_keyboard' => [
                            [['text' => '🔄 رزرو تمدید خودکار', 'callback_data' => 'menu_renew']]
                        ]
                    ];
                    TelegramBot::sendMessage($warnNotice, (string)$userTgId, $warnKeyboard);
                }
            }
        }

        if ($isTrafficDone || $isTimeDone) {
            if (!empty($c['reserved_id'])) {
                // Activate reserved plan
                $addBytes = (int)$c['reserved_gb'] * 1024 * 1024 * 1024;
                $newExpire = date('Y-m-d H:i:s', time() + ($c['reserved_days'] * 86400));

                $pdo->beginTransaction();
                $pdo->prepare("UPDATE clients SET traffic_limit_bytes = traffic_limit_bytes + ?, expire_at = ?, status = 'active' WHERE id = ?")
                    ->execute([$addBytes, $newExpire, $c['id']]);
                
                $appliedAt = date('Y-m-d H:i:s');
                $pdo->prepare("UPDATE reserved_plans SET status = 'applied', applied_at = ? WHERE id = ?")
                    ->execute([$appliedAt, $c['reserved_id']]);
                $pdo->commit();

                // Extend on remote node
                $driver->extendUser($c['username'], $addBytes, $c['reserved_days'] * 86400);

                // Notify Admin
                $botNotice = "⚡️ <b>پلن رزرو هوشمند فعال شد</b>\n"
                           . "👤 کاربر: <code>{$c['username']}</code>\n"
                           . "➕ حجم اضافه شده: <b>{$c['reserved_gb']}GB</b>\n"
                           . "📅 انقضای جدید: {$newExpire}";
                TelegramBot::sendMessage($botNotice);

                // Notify User on Telegram if they ordered via bot
                $stmtUserTg = $pdo->prepare("SELECT user_tg_id FROM bot_orders WHERE client_id = ? ORDER BY id DESC LIMIT 1");
                $stmtUserTg->execute([$c['id']]);
                $userTgId = $stmtUserTg->fetchColumn();
                if (!empty($userTgId)) {
                    $userNotice = "⚡️ <b>پلن رزرو شده شما به صورت خودکار فعال شد!</b>\n\n"
                                . "👤 نام کاربری: <code>{$c['username']}</code>\n"
                                . "📦 حجم افزوده شده: <b>{$c['reserved_gb']} گیگابایت</b>\n"
                                . "⏳ تاریخ انقضای جدید: {$newExpire}\n\n"
                                . "اتصال شما بدون قطعی و با بالاترین سرعت برقرار است.";
                    TelegramBot::sendMessage($userNotice, (string)$userTgId);
                }

                echo "Activated reserved plan for user: {$c['username']} (+{$c['reserved_gb']}GB)" . $eol;
                $reservedActivated++;
            } else {
                // Mark expired
                $pdo->prepare("UPDATE clients SET status = 'expired' WHERE id = ?")->execute([$c['id']]);
                $expiredCount++;

                // Notify User of expiration
                $stmtUserTg = $pdo->prepare("SELECT user_tg_id FROM bot_orders WHERE client_id = ? ORDER BY id DESC LIMIT 1");
                $stmtUserTg->execute([$c['id']]);
                $userTgId = $stmtUserTg->fetchColumn();
                if (!empty($userTgId)) {
                    $expNotice = "🔴 <b>اشتراک شما منقضی گردید</b>\n\n"
                               . "👤 نام کاربری: <code>{$c['username']}</code>\n"
                               . "جهت فعال‌سازی مجدد و تمدید اشتراک، لطفاً از دکمه زیر استفاده نمایید:";
                    $expKeyboard = [
                        'inline_keyboard' => [
                            [['text' => '🔄 تمدید اشتراک', 'callback_data' => 'menu_renew']]
                        ]
                    ];
                    TelegramBot::sendMessage($expNotice, (string)$userTgId, $expKeyboard);
                }
            }
        }

        $syncedCount++;
    } catch (Throwable $e) {
        echo "Error syncing user {$c['username']}: " . $e->getMessage() . $eol;
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Sync Completed. Synced: $syncedCount | Reserved activated: $reservedActivated | Expired: $expiredCount" . $eol;

// Automated Daily Database Backup to Telegram Bot
$lastBackup = (int)Setting::get('last_cron_backup_time', '0');
if (time() - $lastBackup >= 86400) {
    echo "[" . date('Y-m-d H:i:s') . "] Running automated daily database backup..." . $eol;
    try {
        $filename = 'cron_backup_' . date('Y-m-d_H-i') . '.sql';
        $tempPath = sys_get_temp_dir() . '/' . $filename;
        $handle = fopen($tempPath, 'w');

        fwrite($handle, "-- Connectix Automatic Daily Database Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n\n");
        $tables = ['users', 'server_nodes', 'plans', 'clients', 'reserved_plans', 'transactions', 'branding_metadata', 'notifications', 'system_settings', 'bot_orders', 'bot_sessions', 'activity_logs'];

        foreach ($tables as $t) {
            $rows = $pdo->query("SELECT * FROM {$t}")->fetchAll();
            if (!empty($rows)) {
                fwrite($handle, "-- Table: {$t}\n");
                foreach ($rows as $row) {
                    $cols = '`' . implode('`, `', array_keys($row)) . '`';
                    $vals = array_map(function($v) use ($pdo) {
                        return $v === null ? 'NULL' : $pdo->quote((string)$v);
                    }, $row);
                    fwrite($handle, "INSERT INTO `{$t}` ({$cols}) VALUES (" . implode(', ', $vals) . ");\n");
                }
                fwrite($handle, "\n");
            }
        }
        fclose($handle);

        $caption = "📦 <b>پشتیبان‌گیری خودکار ۲۴ ساعته سیستم</b>\n📅 تاریخ: " . date('Y-m-d H:i:s') . "\n🛡 ارسال خودکار توسط کرون جاب";
        TelegramBot::sendDocument($tempPath, $caption);
        @unlink($tempPath);

        Setting::set('last_cron_backup_time', (string)time());
        echo "[" . date('Y-m-d H:i:s') . "] Daily backup sent to Telegram." . $eol;
    } catch (Throwable $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Backup error: " . $e->getMessage() . $eol;
    }
}

// GitHub Auto-Update & Notification Check (Every 6 Hours)
$lastUpdateCheck = (int)Setting::get('last_cron_update_check', '0');
if (time() - $lastUpdateCheck >= 21600) {
    Setting::set('last_cron_update_check', (string)time());
    echo "[" . date('Y-m-d H:i:s') . "] Checking GitHub for panel updates..." . $eol;
    try {
        require_once __DIR__ . '/../core/Updater.php';
        $updateInfo = Updater::checkForUpdates(true);
        if (!empty($updateInfo['has_update'])) {
            $isAutoApply = (bool)Setting::get('auto_apply_github_updates', false);
            if ($isAutoApply) {
                $applyRes = Updater::applyUpdate();
                if ($applyRes['success']) {
                    TelegramBot::sendMessage("🚀 <b>به‌روزرسانی خودکار انجام شد!</b>\n\nسیستم به آخرین نسخه در گیت‌هاب (<code>{$updateInfo['latest_version']}</code>) به‌روزرسانی شد.\n\n📝 تغییرات: " . strip_tags($updateInfo['changelog'] ?? ''));
                    echo "[" . date('Y-m-d H:i:s') . "] Auto-update applied successfully." . $eol;
                }
            } else {
                // Notify admin via Telegram
                TelegramBot::sendMessage("🔔 <b>نسخه جدیدی از پنل در گیت‌هاب منتشر شده است!</b>\n\n🏷 نسخه جدید: <code>{$updateInfo['latest_version']}</code>\n📝 تغییرات:\n" . strip_tags($updateInfo['changelog'] ?? '') . "\n\nبرای اعمال با ۱ کلیک به منوی تنظیمات > آپدیت مراجعه نمایید.");
                echo "[" . date('Y-m-d H:i:s') . "] Update available notification sent to admin." . $eol;
            }
        }
    } catch (Throwable $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Update check error: " . $e->getMessage() . $eol;
    }
}

