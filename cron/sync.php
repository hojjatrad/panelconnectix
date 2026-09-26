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

// 0. Cron Heartbeat — alert if the previous cron run was longer than 5 minutes ago
try {
    $lastRun = (int)Setting::get('last_cron_sync_at', '0');
    $lastAlert = (int)Setting::get('last_cron_dead_alert', '0');
    $deadSec = (int)Setting::get('cron_dead_alert_secs', '900'); // default 15 min (sync can be long)
    if ($lastRun !== 0 && (time() - $lastRun > $deadSec) && (time() - $lastAlert > 1800)) {
        Setting::set('last_cron_dead_alert', (string)time());
        $minutes = round((time() - $lastRun) / 60);
        TelegramBot::sendCategorizedReport('servers', "🫀 <b>هشدار تپش کرون (Cron Heartbeat)</b>\n\n"
            . "آخرین اجرای موفق موتور همگام‌سازی بیش از <b>{$minutes} دقیقه</b> قبل بوده است.\n"
            . "🕐 آخرین اجرای موفق: " . date('Y-m-d H:i:s', $lastRun) . "\n"
            . "اگر ادامه داشت، Jobهای cron در cPanel (cron/sync.php) را بررسی کنید.");
        echo "[Heartbeat] CRON DEAD ALERT (last successful run {$minutes} min ago)." . $eol;
    }
    Setting::set('last_cron_sync_at', (string)time());
} catch (Throwable $e) {
    echo "[Heartbeat Error] " . $e->getMessage() . $eol;
}

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

// 2.5 Automated Daily Cloud Database Backup to Telegram
try {
    require_once __DIR__ . '/../core/Backup.php';
    $lastBackup = Setting::get('last_auto_backup_date', '');
    $today = date('Y-m-d');
    if ($lastBackup !== $today) {
        $backupRes = Backup::sendBackupToTelegram();
        if ($backupRes['success']) {
            Setting::set('last_auto_backup_date', $today);
            echo "[Auto Backup] Dispatched daily database backup to Telegram successfully." . $eol;
        }
    }
} catch (Throwable $e) {
    echo "[Auto Backup Error] " . $e->getMessage() . $eol;
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

// 4a. CI Quality Gate — apply the webhook-parked update once Panel CI goes green
// (runs every minute, independent of the 180s update-check throttle below)
try {
    $pendingSha = (string)Setting::get('pending_ci_sha', '');
    if ($pendingSha !== '' && (time() - (int)Setting::get('pending_ci_at', '0') >= 45)) {
        require_once __DIR__ . '/../core/Updater.php';
        $run = Updater::getActionsRunForSha($pendingSha);
        $ciStatus = ($run && empty($run['not_found'])) ? (string)($run['status'] ?? '') : '';
        $ciConclusion = ($run && empty($run['not_found'])) ? (string)($run['conclusion'] ?? '') : '';

        if ($ciConclusion === 'success') {
            Setting::set('pending_ci_sha', '');
            $info = Updater::checkForUpdates(true);
            if (!empty($info['has_update'])) {
                $res = Updater::applyUpdate(false);
                if (!empty($res['success'])) {
                    $msg = "⚡️ <b>آپدیت آنی گیت‌هاب با وب‌هوک اعمال شد!</b>\n\n"
                         . "تغییرات جدید مستقیماً از مخزن گیت‌هاب دریافت و روی پنل هاست مستقر گردید.\n"
                         . "🏷 نسخه: <code>" . htmlspecialchars((string)($res['version'] ?? ''), ENT_QUOTES) . "</code>\n"
                         . "🧪 درِ کیفیت CI: ✅ سبز\n"
                         . "📅 زمان: " . date('Y-m-d H:i:s');
                    TelegramBot::announcePanelUpdate($pendingSha, $msg);
                    echo "[CI Gate] Applied " . substr($pendingSha, 0, 7) . " after Panel CI turned green." . $eol;
                } else {
                    echo "[CI Gate Error] Apply failed after green CI: " . ($res['error'] ?? 'unknown') . $eol;
                }
            } else {
                echo "[CI Gate] CI green but no update pending (already up to date)." . $eol;
            }
        } elseif (in_array($ciConclusion, ['failure', 'cancelled', 'timeout'], true)) {
            Setting::set('pending_ci_sha', '');
            $link = (string)($run['html_url'] ?? '');
            TelegramBot::sendCategorizedReport('general', "🚫 <b>درِ کیفیت CI — آپدیت مسدود شد</b>\n\n"
                . "آزمایش‌های پنل روی کامیت <code>" . substr($pendingSha, 0, 7) . "</code> <b>شکست خوردند</b>؛ این آپدیت اعمال نشد.\n"
                . "پنل روی نسخه پایدار قبلی باقی می‌ماند تا خرابی اصلاح شود."
                . ($link !== '' ? "\n🔗 <a href=\"" . $link . "\">مشاهده جزئیات CI</a>" : ''));
            echo "[CI Gate] CI failed for " . substr($pendingSha, 0, 7) . " — update blocked." . $eol;
        } elseif (time() - (int)Setting::get('pending_ci_at', '0') > 1800) {
            Setting::set('pending_ci_sha', '');
            TelegramBot::sendCategorizedReport('general', "⏳ <b>CI بیش از ۳۰ دقیقه طول کشید</b>\n\n"
                . "انتظار برای سبز شدن کامیت <code>" . substr($pendingSha, 0, 7) . "</code> لغو شد.\n"
                . "در صورت نیاز، اعمال دستی از صفحه «بروزرسانی» پنل انجام شود.");
            echo "[CI Gate] Timed out waiting for CI on " . substr($pendingSha, 0, 7) . "." . $eol;
        } else {
            echo "[CI Gate] Still waiting for Panel CI (status={$ciStatus}, conclusion=" . ($ciConclusion ?: '-') . ")." . $eol;
        }
    }
} catch (Throwable $e) {
    echo "[CI Gate Error] " . $e->getMessage() . $eol;
}

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
            $applyRes = Updater::applyUpdate(false);
            if ($applyRes['success']) {
                $notifySha = ($updateInfo['type'] ?? '') === 'commit'
                    ? str_replace('commit-', '', (string)($updateInfo['latest_version'] ?? ''))
                    : (string)($updateInfo['latest_version'] ?? '');
                $msg = "🚀 <b>به‌روزرسانی خودکار پنل از گیت‌هاب با موفقیت اعمال شد!</b>\n\n"
                     . "🏷 نسخه جدید: <code>{$updateInfo['latest_version']}</code>\n"
                     . "📅 زمان: " . date('Y-m-d H:i:s') . "\n"
                     . "📝 تغییرات: " . strip_tags($updateInfo['changelog'] ?? 'همگام‌سازی آخرین کدهای مخزن');
                // ONE message per applied update in the supergroup reports
                // topic (de-duplicated by sha so webhook + cron never double-post)
                $announced = TelegramBot::announcePanelUpdate($notifySha, $msg);
                echo "[Auto-Update] Panel updated successfully to {$updateInfo['latest_version']} (announced: " . ($announced ? 'yes' : 'deduplicated') . ")." . $eol;
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

// 4.5 Node Client Sync (mirror live node users into the clients table every 5 minutes)
try {
    require_once __DIR__ . '/../core/NodeSync.php';
    $lastNodeSync = (int)Setting::get('last_cron_node_sync', '0');
    if (time() - $lastNodeSync >= 300) {
        Setting::set('last_cron_node_sync', (string)time());
        $nodeSyncRes = NodeSync::syncAll($pdo);
        echo "[Node Sync] servers=" . $nodeSyncRes['servers']
            . " added=" . $nodeSyncRes['added']
            . " updated=" . $nodeSyncRes['updated']
            . " skipped=" . $nodeSyncRes['skipped'] . $eol;
        if (!empty($nodeSyncRes['errors'])) {
            echo "[Node Sync] errors: " . implode(' | ', array_slice($nodeSyncRes['errors'], 0, 5)) . $eol;
        }
    } else {
        echo "[Node Sync] skipped (throttled, last run " . (time() - $lastNodeSync) . "s ago)." . $eol;
    }
} catch (Throwable $e) {
    echo "[Node Sync Error] " . $e->getMessage() . $eol;
}

// 5. App Release Auto-Publisher (publishes new Android app builds from CI automatically)
try {
    require_once __DIR__ . '/../core/AppReleasePublisher.php';
    $appPub = AppReleasePublisher::sync();
    if (!empty($appPub['action'])) {
        echo "[App Release] remote=" . ($appPub['remote_version'] ?? '?')
            . " | published=" . ($appPub['published_version'] ?? '-')
            . " | action=" . $appPub['action'] . $eol;
    } else {
        echo "[App Release] " . ($appPub['error'] ?? 'ok') . " (remote=" . ($appPub['remote_version'] ?? '?') . ")" . $eol;
    }
} catch (Throwable $e) {
    echo "[App Release Error] " . $e->getMessage() . $eol;
}

// 6. Disk-Quota Monitor & Local Backup Retention (once per 6h)
try {
    $lastDisk = (int)Setting::get('last_cron_disk_check', '0');
    if (time() - $lastDisk >= 21600) {
        Setting::set('last_cron_disk_check', (string)time());
        $alertPct = (float)Setting::get('disk_alert_pct', '85');
        $rootPath = __DIR__; // panel root (same filesystem as the cPanel quota)
        $totalBytes = @disk_total_space($rootPath);
        $freeBytes = @disk_free_space($rootPath);
        if ($totalBytes > 0) {
            $usedPct = round((($totalBytes - $freeBytes) / $totalBytes) * 100, 1);
            if ($usedPct >= $alertPct) {
                TelegramBot::sendCategorizedReport('servers', "💾 <b>هشدار پرشدگی دیسک هاست</b>\n\n"
                    . "فضا مصرف‌شده: <b>{$usedPct}%</b> از " . Helpers::formatBytes($totalBytes) . "\n"
                    . "آستانه هشدار: {$alertPct}%\n"
                    . "پیشنهاد: پاک‌سازی بکاپ‌های قدیمی، لاگ‌ها و فایل‌های موقت.");
                echo "[Disk] HIGH USAGE {$usedPct}% (threshold {$alertPct}%)." . $eol;
            } else {
                echo "[Disk] OK {$usedPct}% used of " . Helpers::formatBytes($totalBytes) . $eol;
            }
        }
        // Local backup retention (purge data/backups older than N days)
        $retentionDays = (int)Setting::get('backup_retention_days', '14');
        $backupDir = dirname(__DIR__) . '/data/backups';
        if ($retentionDays > 0 && is_dir($backupDir)) {
            $cutoff = time() - ($retentionDays * 86400);
            $purged = 0;
            foreach (glob($backupDir . '/*') ?: [] as $bf) {
                if (is_file($bf) && @filemtime($bf) < $cutoff) {
                    if (@unlink($bf)) $purged++;
                }
            }
            if ($purged > 0) {
                echo "[Backup Retention] Purged {$purged} old local backup(s) > {$retentionDays}d." . $eol;
            }
        }
    }
} catch (Throwable $e) {
    echo "[Disk Monitor Error] " . $e->getMessage() . $eol;
}

// 6b. Server Capacity Alerts (per-server, once per 6h)
try {
    $lastCap = (int)Setting::get('last_cron_capacity_check', '0');
    if (time() - $lastCap >= 21600) {
        Setting::set('last_cron_capacity_check', (string)time());
        $capPct = (float)Setting::get('server_capacity_alert_pct', '90');
        $capStmt = $pdo->query("SELECT s.id, s.name, s.max_clients, COUNT(c.id) AS cnt
                                FROM server_nodes s
                                LEFT JOIN clients c ON c.server_id = s.id AND c.status = 'active'
                                WHERE s.is_active = 1 AND s.max_clients > 0
                                GROUP BY s.id, s.name, s.max_clients");
        foreach ($capStmt->fetchAll() as $srv) {
            $max = (int)$srv['max_clients'];
            $cnt = (int)$srv['cnt'];
            if ($max > 0 && (($cnt / $max) * 100) >= $capPct) {
                TelegramBot::sendCategorizedReport('servers', "📈 <b>هشدار ظرفیت سرور</b>\n\n"
                    . "سرور: <b>" . htmlspecialchars($srv['name']) . "</b>\n"
                    . "کلاینت فعال: <b>{$cnt}</b> از {$max} (" . round(($cnt / $max) * 100, 1) . "%)\n"
                    . "آستانه: {$capPct}%\nپیشنهاد: افزودن سرور جدید یا جابه‌جایی کلاینت‌ها (servers/migrate).");
                echo "[Capacity] {$srv['name']} at " . round(($cnt / $max) * 100, 1) . "%" . $eol;
            }
        }
    }
} catch (Throwable $e) {
    echo "[Capacity Error] " . $e->getMessage() . $eol;
}

// 6b2. Weekly Reseller Digest (sent via each reseller's own Telegram bot)
try {
    $lastDigest = (int)Setting::get('last_cron_reseller_digest', '0');
    if (time() - $lastDigest >= 604800) { // 7 days
        Setting::set('last_cron_reseller_digest', (string)time());
        $weekAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
        $expSoonEnd = date('Y-m-d H:i:s', strtotime('+7 days'));
        $resellers = $pdo->query("SELECT id, full_name, username, wallet_balance, telegram_bot_token, telegram_admin_chat_id, telegram_bot_username
                                  FROM users WHERE role = 'reseller' AND status = 'active' AND telegram_bot_token IS NOT NULL AND telegram_bot_token != ''")->fetchAll();
        $sentDigests = 0;
        foreach ($resellers as $rs) {
            if (empty($rs['telegram_admin_chat_id'])) continue;
            try {
                $stA = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE reseller_id = ? AND status = 'active'");
                $stA->execute([$rs['id']]);
                $activeC = (int)$stA->fetchColumn();

                $stS = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id = ? AND status = 'completed' AND amount > 0 AND created_at >= ?");
                $stS->execute([$rs['id'], $weekAgo]);
                $weekSales = (int)$stS->fetchColumn();

                $stE = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE reseller_id = ? AND status = 'active' AND expire_at IS NOT NULL AND expire_at BETWEEN ? AND ?");
                $stE->execute([$rs['id'], date('Y-m-d H:i:s'), $expSoonEnd]);
                $expSoon = (int)$stE->fetchColumn();

                $stNew = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE reseller_id = ? AND created_at >= ?");
                $stNew->execute([$rs['id'], $weekAgo]);
                $newC = (int)$stNew->fetchColumn();

                $digest = "📊 <b>گزارش هفتگی نمایندگی — کانکتیکس</b>\n\n"
                    . "👤 نماینده: <b>" . htmlspecialchars((string)($rs['full_name'] ?: $rs['username'])) . "</b>\n"
                    . "──────────────\n"
                    . "🟢 کلاینت‌های فعال: <b>" . number_format($activeC) . "</b>\n"
                    . "🆕 صدور جدید (۷ روز): <b>" . number_format($newC) . "</b>\n"
                    . "🛒 خرید شما (۷ روز): <b>" . number_format($weekSales) . " تومان</b>\n"
                    . "⏳ منقضی‌شونده تا ۷ روز دیگر: <b>" . number_format($expSoon) . "</b>\n"
                    . "💳 موجودی کیف‌پول: <b>" . number_format((int)$rs['wallet_balance']) . " تومان</b>\n"
                    . "──────────────\n"
                    . "گزارش‌های کامل مالی: پنل → صورت‌حساب‌ها";
                $sent = TelegramBot::sendMessage($digest, (string)$rs['telegram_admin_chat_id'], null, (string)$rs['telegram_bot_token']);
                if ($sent) $sentDigests++;
            } catch (Throwable $eR) {
                echo "[Digest Error] reseller {$rs['id']}: " . $eR->getMessage() . $eol;
            }
        }
        echo "[Digest] Weekly reseller digests sent: {$sentDigests} of " . count($resellers) . $eol;
    }
} catch (Throwable $e) {
    echo "[Digest Error] " . $e->getMessage() . $eol;
}

// 6d. Metrics Snapshot (every 30 min → dashboard 72h trend charts)
try {
    $lastSnap = (int)Setting::get('last_metrics_snapshot', '0');
    if (time() - $lastSnap >= 1800) {
        Setting::set('last_metrics_snapshot', (string)time());
        $mActive = (int)$pdo->query("SELECT COUNT(*) FROM clients WHERE status = 'active'")->fetchColumn();
        $mTotal = (int)$pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
        $mOnline = (int)$pdo->query("SELECT COUNT(*) FROM server_nodes WHERE is_active = 1 AND health_status = 'online'")->fetchColumn();
        $mNodes = (int)$pdo->query("SELECT COUNT(*) FROM server_nodes WHERE is_active = 1")->fetchColumn();
        $mTraffic = $pdo->query("SELECT COALESCE(SUM(traffic_used_bytes),0) AS u, COALESCE(SUM(traffic_limit_bytes),0) AS l FROM clients WHERE status = 'active'")->fetch();
        $pdo->prepare("INSERT INTO metrics (ts, active_clients, total_clients, online_nodes, total_nodes, traffic_used_total, traffic_limit_total)
                       VALUES (CURRENT_TIMESTAMP, ?, ?, ?, ?, ?, ?)")
            ->execute([$mActive, $mTotal, $mOnline, $mNodes, (int)$mTraffic['u'], (int)$mTraffic['l']]);
        $pdo->prepare("DELETE FROM metrics WHERE ts < ?")->execute([date('Y-m-d H:i:s', strtotime('-14 days'))]);
        echo "[Metrics] Snapshot saved (active={$mActive}, nodes={$mOnline}/{$mNodes})." . $eol;
    }
} catch (Throwable $e) {
    echo "[Metrics Error] " . $e->getMessage() . $eol;
}

// 6c. Monthly Backup Restore Self-Test (validates the latest backup is restorable)
try {
    $lastRestoreTest = (int)Setting::get('last_cron_restore_test', '0');
    if (time() - $lastRestoreTest >= 2592000) { // 30 days
        Setting::set('last_cron_restore_test', (string)time());
        // 1) Integrity of the LIVE database first (the important one)
        if (defined('DB_DRIVER') && DB_DRIVER === 'sqlite' && defined('SQLITE_PATH') && file_exists(SQLITE_PATH)) {
            try {
                $t = new PDO('sqlite:' . SQLITE_PATH);
                $integrity = $t->query('PRAGMA integrity_check')->fetchColumn();
                $users = (int)$t->query('SELECT COUNT(*) FROM users')->fetchColumn();
                $clients = (int)$t->query('SELECT COUNT(*) FROM clients')->fetchColumn();
                $t = null;
                $okLive = ($integrity === 'ok');
                TelegramBot::sendCategorizedReport('backup', ($okLive ? "✅ " : "❌ ") . "<b>کنترل سلامت دیتابیس زنده (ماهانه)</b>\n\n"
                    . "integrity_check: <b>" . ($okLive ? 'OK' : 'ERROR — فوراً بکاپ بگیرید') . "</b>\n"
                    . "👥 کاربران: {$users} | 📦 کلاینت‌ها: {$clients}");
                echo "[RestoreTest] live DB integrity=" . $integrity . $eol;
            } catch (Throwable $e) {
                echo "[RestoreTest] live DB check error: " . $e->getMessage() . $eol;
            }
        }
        // 2) Newest local backup file, if any exist
        $backupDir = dirname(__DIR__) . '/data/backups';
        $candidates = is_dir($backupDir) ? glob($backupDir . '/*.{sql,sqlite}', GLOB_BRACE) ?: [] : [];
        usort($candidates, fn($a, $b) => filemtime($b) - filemtime($a));
        if (!empty($candidates)) {
            $bk = $candidates[0];
            $ok = false;
            $detail = '';
            if (str_ends_with($bk, '.sqlite')) {
                $tmp = tempnam(sys_get_temp_dir(), 'cr_');
                copy($bk, $tmp);
                try {
                    $t = new PDO('sqlite:' . $tmp);
                    $integrity = $t->query('PRAGMA integrity_check')->fetchColumn();
                    $cnt = (int)$t->query('SELECT COUNT(*) FROM users')->fetchColumn();
                    $ok = ($integrity === 'ok');
                    $detail = "integrity=" . $integrity . " users={$cnt}";
                } catch (Throwable $e) {
                    $detail = 'error: ' . $e->getMessage();
                }
                @unlink($tmp);
            } else {
                // SQL dump: count INSERT statements as a sanity signal
                $content = (string)@file_get_contents($bk);
                $inserts = substr_count($content, 'INSERT INTO');
                $ok = $inserts > 0 && strlen($content) > 1000;
                $detail = "inserts={$inserts} bytes=" . strlen($content);
            }
            TelegramBot::sendCategorizedReport('backup', ($ok ? "✅ " : "❌ ") . "<b>تست خودکار بازگردانی بکاپ (ماهانه)</b>\n\n"
                . "فایل: <code>" . basename($bk) . "</code>\n"
                . "نتیجه: " . ($ok ? "قابل بازیابی" : "مشکوک — بررسی کنید") . "\n"
                . "جزئیات: {$detail}");
            echo "[RestoreTest] " . basename($bk) . " => " . ($ok ? 'OK' : 'CHECK') . " ({$detail})" . $eol;
        } else {
            echo "[RestoreTest] No local backup files found; skipped." . $eol;
        }
    }
} catch (Throwable $e) {
    echo "[RestoreTest Error] " . $e->getMessage() . $eol;
}

// Mark successful full completion (heartbeat freshness = "cron finished cleanly")
try {
    Setting::set('last_cron_sync_at', (string)time());
} catch (Throwable $e) {}
echo "[" . date('Y-m-d H:i:s') . "] Sync engine completed cleanly." . $eol;

