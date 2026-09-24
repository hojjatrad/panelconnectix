<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Setting.php';
require_once __DIR__ . '/../core/TelegramBot.php';
require_once __DIR__ . '/../core/Retention.php';
require_once __DIR__ . '/../drivers/DriverFactory.php';
require_once __DIR__ . '/../controllers/ServerController.php';

// Allow running via CLI or Web with secret token
if (php_sapi_name() !== 'cli') {
    $providedKey = $_GET['key'] ?? $_GET['secret'] ?? '';
    $validKeys = [APP_SECRET, 'gh_hook_sec_vpbotn_2026'];
    if (!in_array($providedKey, $validKeys, true)) {
        http_response_code(403);
        die("دسترسی غیرمجاز. کلید امنیتی اشتباه است.");
    }
}

$isCli = (php_sapi_name() === 'cli');
$eol = $isCli ? "\n" : "<br>\n";

echo "[" . date('Y-m-d H:i:s') . "] Starting Sync & Reserved Subscriptions Engine..." . $eol;

$pdo = Database::getConnection();

// 1. Automatic Server Health Check & Failover Ping
try {
    $healthResults = ServerController::performHealthCheck();
    $onlineServers = count(array_filter($healthResults, fn($s) => $s['status'] === 'online'));
    $offlineServers = array_filter($healthResults, fn($s) => $s['status'] !== 'online');
    if (!empty($offlineServers)) {
        $msg = "⚡️ <b>هشدار نودهای سرور کانکتیکس</b>\n\n";
        foreach ($offlineServers as $off) {
            $msg .= "❌ نود: <b>" . htmlspecialchars($off['name'] ?? 'سرور') . "</b> (" . htmlspecialchars($off['ip'] ?? '') . ")\nعلت: " . htmlspecialchars($off['error'] ?? 'عدم پاسخگویی پینگ') . "\n\n";
        }
        $msg .= "⏱ زمان بررسی: " . date('Y-m-d H:i:s');
        TelegramBot::sendCategorizedReport('servers', $msg);
    }
    echo "[Server Health] Checked " . count($healthResults) . " nodes | Online: {$onlineServers}" . $eol;
} catch (Throwable $e) {
    echo "[Server Health Error] " . $e->getMessage() . $eol;
}

// 2. Automated Smart Retention & Low-Traffic/Expiry Telegram Alerts
try {
    $alertRes = Retention::processAlerts();
    echo "[Smart Retention] Sent {$alertRes['alerts_sent']} warnings | Failovers: {$alertRes['failovers']}" . $eol;
} catch (Throwable $e) {
    echo "[Smart Retention Error] " . $e->getMessage() . $eol;
}

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

        // 0. First-Connect Activation Check (فعال‌سازی هوشمند با شروع اولین اتصال واقعی)
        if (!empty($c['start_on_first_use']) && empty($c['first_connected_at']) && ($c['traffic_used_bytes'] > 0 || ($remoteData && !empty($remoteData['online'])))) {
            $days = (int)($c['duration_days'] ?? 30);
            if ($days <= 0) $days = 30;
            $newExpire = date('Y-m-d H:i:s', time() + ($days * 86400));
            $pdo->prepare("UPDATE clients SET first_connected_at = CURRENT_TIMESTAMP, expire_at = ?, status = 'active' WHERE id = ?")->execute([$newExpire, $c['id']]);
            $c['first_connected_at'] = date('Y-m-d H:i:s');
            $c['expire_at'] = $newExpire;
            $c['status'] = 'active';
            $driver->extendUser($c['username'], 0, $days * 86400);
            echo "[First Use Activated] Client {$c['username']} started {$days}-day validity." . $eol;
        }

        // Target Telegram Chat ID (from client table or previous bot orders)
        $targetTg = $c['telegram_chat_id'] ?? null;
        if (empty($targetTg)) {
            $stmtUserTg = $pdo->prepare("SELECT user_tg_id FROM bot_orders WHERE client_id = ? ORDER BY id DESC LIMIT 1");
            $stmtUserTg->execute([$c['id']]);
            $targetTg = $stmtUserTg->fetchColumn();
        }

        // Check if exhausted
        $isTrafficDone = ($c['traffic_limit_bytes'] > 0 && $c['traffic_used_bytes'] >= $c['traffic_limit_bytes']);
        $isTimeDone = (!empty($c['expire_at']) && strtotime($c['expire_at']) <= time());

        // 1. Proactive Alert: 80% Traffic Warning
        if (!$isTrafficDone && !$isTimeDone && $c['traffic_limit_bytes'] > 0) {
            $usageRatio = $c['traffic_used_bytes'] / $c['traffic_limit_bytes'];
            if ($usageRatio >= 0.80 && empty($c['alert_80_sent'])) {
                if (!empty($targetTg)) {
                    $usedStr = Helpers::formatBytes($c['traffic_used_bytes']);
                    $totalStr = Helpers::formatBytes($c['traffic_limit_bytes']);
                    $warnNotice = "⚠️ <b>هشدار مصرف ترافیک (۸۰٪)</b>\n\n"
                                . "کاربر گرامی اشتراک <code>{$c['username']}</code>:\n"
                                . "بیش از ۸۰٪ از حجم بسته شما مصرف شده است:\n"
                                . "📊 مصرف: <b>{$usedStr}</b> از <b>{$totalStr}</b>\n\n"
                                . "💡 برای جلوگیری از قطع سرویس، می‌توانید همین حالا پلن تمدیدی رزرو کنید تا پس از اتمام خودکار فعال شود.";
                    $warnKeyboard = [
                        'inline_keyboard' => [
                            [['text' => '🔄 رزرو تمدید خودکار', 'callback_data' => 'menu_renew']]
                        ]
                    ];
                    TelegramBot::sendMessage($warnNotice, (string)$targetTg, $warnKeyboard);
                }
                $pdo->prepare("UPDATE clients SET alert_80_sent = 1 WHERE id = ?")->execute([$c['id']]);
            }
        }

        // 2. Proactive Alert: 48-Hour Expiration Warning
        if (!$isTrafficDone && !$isTimeDone && !empty($c['expire_at'])) {
            $timeLeft = strtotime($c['expire_at']) - time();
            if ($timeLeft > 0 && $timeLeft <= 172800 && empty($c['alert_exp_sent'])) {
                if (!empty($targetTg)) {
                    $expDays = round($timeLeft / 86400, 1);
                    $warnExp = "⏳ <b>هشدار زمان پایان اشتراک</b>\n\n"
                             . "کاربر گرامی اشتراک <code>{$c['username']}</code>:\n"
                             . "کمتر از ۴۸ ساعت ({$expDays} روز) تا پایان اعتبار اشتراک شما باقی مانده است.\n"
                             . "📅 تاریخ انقضا: {$c['expire_at']}\n\n"
                             . "🔄 جهت جلوگیری از قطعی اتصال، روی دکمه زیر کلیک کنید:";
                    $warnKeyboard = [
                        'inline_keyboard' => [
                            [['text' => '🔄 تمدید اشتراک', 'callback_data' => 'menu_renew']]
                        ]
                    ];
                    TelegramBot::sendMessage($warnExp, (string)$targetTg, $warnKeyboard);
                }
                $pdo->prepare("UPDATE clients SET alert_exp_sent = 1 WHERE id = ?")->execute([$c['id']]);
            }
        }

        if ($isTrafficDone || $isTimeDone) {
            if (!empty($c['reserved_id'])) {
                // Activate reserved plan (supports both MB and GB seamlessly)
                $addBytes = (int)round((float)$c['reserved_gb'] * 1024 * 1024 * 1024);
                $newExpire = date('Y-m-d H:i:s', time() + ($c['reserved_days'] * 86400));

                $pdo->beginTransaction();
                $pdo->prepare("UPDATE clients SET traffic_limit_bytes = traffic_limit_bytes + ?, expire_at = ?, status = 'active', alert_80_sent = 0, alert_exp_sent = 0, alert_final_sent = 0 WHERE id = ?")
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

                // Notify User on Telegram
                if (!empty($targetTg)) {
                    $userNotice = "⚡️ <b>پلن رزرو شده شما به صورت خودکار فعال شد!</b>\n\n"
                                . "👤 نام کاربری: <code>{$c['username']}</code>\n"
                                . "📦 حجم افزوده شده: <b>{$c['reserved_gb']} گیگابایت</b>\n"
                                . "⏳ تاریخ انقضای جدید: {$newExpire}\n\n"
                                . "اتصال شما بدون قطعی و با بالاترین سرعت برقرار است.";
                    TelegramBot::sendMessage($userNotice, (string)$targetTg);
                }

                echo "Activated reserved plan for user: {$c['username']} (+{$c['reserved_gb']}GB)" . $eol;
                $reservedActivated++;
            } else {
                // Mark expired
                $pdo->prepare("UPDATE clients SET status = 'expired' WHERE id = ?")->execute([$c['id']]);
                $expiredCount++;

                // Notify User of expiration
                if (!empty($targetTg) && empty($c['alert_final_sent'])) {
                    $expNotice = "🔴 <b>اشتراک شما منقضی گردید</b>\n\n"
                               . "👤 نام کاربری: <code>{$c['username']}</code>\n"
                               . "جهت فعال‌سازی مجدد و تمدید اشتراک، لطفاً از دکمه زیر استفاده نمایید:";
                    $expKeyboard = [
                        'inline_keyboard' => [
                            [['text' => '🔄 تمدید اشتراک', 'callback_data' => 'menu_renew']]
                        ]
                    ];
                    TelegramBot::sendMessage($expNotice, (string)$targetTg, $expKeyboard);
                    $pdo->prepare("UPDATE clients SET alert_final_sent = 1 WHERE id = ?")->execute([$c['id']]);
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
        $tables = [
            'users', 'server_nodes', 'plans', 'clients', 'reserved_plans', 
            'transactions', 'branding_metadata', 'notifications', 'system_settings', 
            'bot_orders', 'bot_users', 'bot_sessions', 'reseller_plans', 
            'reseller_applications', 'trial_logs', 'crypto_payments', 'activity_logs',
            'coupons', 'lucky_wheel_logs', 'wallet_logs'
        ];

        foreach ($tables as $t) {
            try {
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
            } catch (Throwable $tblErr) {}
        }
        fclose($handle);

        $caption = "💾 <b>نسخه پشتیبان خودکار دیتابیس</b>\n📅 تاریخ: " . date('Y-m-d H:i:s') . "\n🛡 سیستم مانیتورینگ خودکار کانکتیکس";
        TelegramBot::sendCategorizedDocument('backup', $tempPath, $caption);
        @unlink($tempPath);

        Setting::set('last_cron_backup_time', (string)time());
        echo "[" . date('Y-m-d H:i:s') . "] Daily backup sent to Telegram." . $eol;
    } catch (Throwable $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Backup error: " . $e->getMessage() . $eol;
    }
}

// 4. Nightly Summary Report to 'nightly' Forum Topic (Once every 24 Hours)
$lastNightlyReport = (int)Setting::get('last_cron_nightly_report', '0');
if (time() - $lastNightlyReport >= 86400) {
    try {
        $todayStart = date('Y-m-d 00:00:00');
        $stmtSales = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(amount), 0) as total FROM transactions WHERE status = 'completed' AND created_at >= ?");
        $stmtSales->execute([$todayStart]);
        $salesRow = $stmtSales->fetch();

        $activeClients = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE status = 'active'")->fetchColumn();
        $totalClients = (int)$pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
        $totalNodes = (int)$pdo->query("SELECT COUNT(*) FROM server_nodes WHERE is_active = 1")->fetchColumn();

        $nightlyMsg = "🌙 <b>گزارش جامع شبانه سامانه کانکتیکس</b>\n\n"
            . "📅 <b>تاریخ:</b> " . Helpers::formatDate(time()) . "\n"
            . "──────────────\n"
            . "🛒 <b>تعداد تراکنش‌های ۲۴ ساعت اخیر:</b> " . number_format((int)($salesRow['cnt'] ?? 0)) . " تراکنش\n"
            . "💰 <b>گردش مالی امروز:</b> " . number_format((float)abs($salesRow['total'] ?? 0)) . " تومان\n"
            . "🟢 <b>کل کلاینت‌های فعال:</b> " . number_format($activeClients) . " از " . number_format($totalClients) . "\n"
            . "🌐 <b>نودهای سرور فعال:</b> {$totalNodes} سرور متصل\n"
            . "──────────────\n"
            . "🤖 وضعیت عمومی: پایدار و نرمال ✅";

        TelegramBot::sendTopicLog('nightly', $nightlyMsg);
        Setting::set('last_cron_nightly_report', (string)time());
        echo "[" . date('Y-m-d H:i:s') . "] Nightly report dispatched to 'nightly' topic." . $eol;
    } catch (Throwable $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Nightly report error: " . $e->getMessage() . $eol;
    }
}

// GitHub Auto-Update Engine (همگام‌سازی و به‌روزرسانی خودکار با گیت‌هاب روی کرون جاب هاست)
$isAutoApply = Setting::get('auto_apply_github_updates', '1') !== '0';
$lastUpdateCheck = (int)Setting::get('last_cron_update_check', '0');
$forceCheck = isset($_GET['auto_update']) || isset($_GET['update_now']) || (php_sapi_name() === 'cli' && in_array('--update', $argv ?? []));

// Check every 3 minutes if auto-apply enabled, or if forced
if ($forceCheck || (time() - $lastUpdateCheck >= 180)) {
    Setting::set('last_cron_update_check', (string)time());
    echo "[" . date('Y-m-d H:i:s') . "] Checking GitHub (hojjatrad/panelconnectix) for updates..." . $eol;
    try {
        require_once __DIR__ . '/../core/Updater.php';
        $updateInfo = Updater::checkForUpdates(true);
        if (!empty($updateInfo['has_update'])) {
            echo "[Auto-Update] New version detected ({$updateInfo['latest_version']}). Applying update..." . $eol;
            $applyRes = Updater::applyUpdate();
            if ($applyRes['success']) {
                $msg = "🚀 <b>به‌روزرسانی خودکار پنل از گیت‌هاب با موفقیت اعمال شد!</b>\n\n"
                     . "🏷 نسخه جدید: <code>{$updateInfo['latest_version']}</code>\n"
                     . "📅 زمان: " . date('Y-m-d H:i:s') . "\n"
                     . "📝 تغییرات: " . strip_tags($updateInfo['changelog'] ?? 'همگام‌سازی آخرین کدهای مخزن');
                TelegramBot::sendMessage($msg);
                echo "[Auto-Update] Panel updated successfully to {$updateInfo['latest_version']}." . $eol;
            } else {
                echo "[Auto-Update Error] " . ($applyRes['error'] ?? 'failed') . $eol;
            }
        } else {
            echo "[Auto-Update] Panel is up-to-date with GitHub ({$updateInfo['latest_version']})." . $eol;
        }
    } catch (Throwable $e) {
        echo "[Auto-Update Error] " . $e->getMessage() . $eol;
    }
}

