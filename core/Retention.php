<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Setting.php';
require_once __DIR__ . '/TelegramBot.php';
require_once __DIR__ . '/Helpers.php';

class Retention {
    /**
     * Check clients for expiry and low traffic, and send Telegram alerts
     */
    public static function processAlerts(): array {
        $pdo = Database::getConnection();
        $botToken = Setting::get('telegram_bot_token');
        $lowThresholdPercent = (int)Setting::get('low_traffic_alert_threshold', '10');
        $expiryAlertDays = (int)Setting::get('expiry_alert_days', '3');

        $notifiedCount = 0;
        $failoverCount = 0;

        // 1. Process Low Traffic Alerts (< 10% or < 1.5GB remaining) & Expiry Reminders
        $clients = $pdo->query("SELECT c.*, p.title as plan_title 
                                FROM clients c 
                                LEFT JOIN plans p ON c.plan_id = p.id 
                                WHERE c.status = 'active'")->fetchAll(PDO::FETCH_ASSOC);

        $now = time();
        foreach ($clients as $c) {
            $totalBytes = (float)($c['traffic_limit_bytes'] ?? 0);
            $usedBytes = (float)($c['traffic_used_bytes'] ?? 0);
            $remainingBytes = max(0, $totalBytes - $usedBytes);
            $remainingGb = round($remainingBytes / (1024 * 1024 * 1024), 2);
            $percentRemaining = $totalBytes > 0 ? ($remainingBytes / $totalBytes) * 100 : 100;

            $chatId = $c['telegram_chat_id'] ?? null;
            if (empty($chatId)) {
                // If client doesn't have a direct telegram chat, check if bot user exists with same username
                $stmtBotUser = $pdo->prepare("SELECT tg_id FROM bot_users WHERE username = ? LIMIT 1");
                $stmtBotUser->execute([$c['username']]);
                $chatId = $stmtBotUser->fetchColumn();
            }

            if (empty($chatId) || empty($botToken)) {
                continue;
            }

            $subUrl = Helpers::subUrl($c['sub_token']);

            // A. Low Traffic Alert (< 10% remaining or < 1.5 GB)
            if ($totalBytes > 0 && ($percentRemaining <= $lowThresholdPercent || $remainingGb <= 1.5)) {
                // Check if alerted in last 48 hours
                $logCheck = $pdo->prepare("SELECT id FROM activity_logs WHERE entity_id = ? AND action = 'alert_traffic_sent' AND created_at >= ?");
                $logCheck->execute([$c['id'], date('Y-m-d H:i:s', $now - 172800)]);
                if (!$logCheck->fetch()) {
                    $usedStr = Helpers::formatBytes($usedBytes);
                    $totalStr = Helpers::formatBytes($totalBytes);

                    $msg = "⚠️ <b>هشدار رو به اتمام بودن حجم اشتراک</b>\n\n"
                         . "👤 کاربر گرامی اشتراک: <code>{$c['username']}</code>\n"
                         . "📦 پلن: <b>{$c['plan_title']}</b>\n"
                         . "📊 مصرف کل: {$usedStr} از {$totalStr}\n"
                         . "💾 حجم باقیمانده: <b>{$remainingGb} گیگابایت</b> (" . round($percentRemaining) . "٪)\n\n"
                         . "💡 جهت جلوگیری از قطع ناگهانی ارتباط اینترنت، لطفاً همین حالا نسبت به تمدید اقدام فرمایید:";

                    $kb = [
                        'inline_keyboard' => [
                            [['text' => '🔄 تمدید آنی با ۱ کلیک', 'callback_data' => 'renew_acc_' . $c['id']]],
                            [['text' => '🌐 مشاهده وضعیت ساب‌لینک', 'url' => $subUrl]]
                        ]
                    ];

                    $sent = TelegramBot::sendMessage($msg, (string)$chatId, $kb, $botToken);
                    if ($sent) {
                        Helpers::logActivity('alert_traffic_sent', "ارسال هشدار اتمام حجم به کلاینت {$c['username']}", 'system', (string)$c['id']);
                        $notifiedCount++;
                    }
                }
            }

            // B. Expiry Alert (<= 3 days remaining)
            if (!empty($c['expire_at'])) {
                $expTime = strtotime($c['expire_at']);
                $daysLeft = ($expTime - $now) / 86400;

                if ($daysLeft > 0 && $daysLeft <= $expiryAlertDays) {
                    $logCheck = $pdo->prepare("SELECT id FROM activity_logs WHERE entity_id = ? AND action = 'alert_exp_sent' AND created_at >= ?");
                    $logCheck->execute([$c['id'], date('Y-m-d H:i:s', $now - 172800)]);
                    if (!$logCheck->fetch()) {
                        $daysLeftRounded = max(1, ceil($daysLeft));
                        $msg = "⏳ <b>یادآوری رو به اتمام بودن زمان اشتراک</b>\n\n"
                             . "👤 کاربر گرامی اشتراک: <code>{$c['username']}</code>\n"
                             . "📦 پلن: <b>{$c['plan_title']}</b>\n"
                             . "📅 مهلت باقیمانده: <b>{$daysLeftRounded} روز</b>\n"
                             . "🗓 تاریخ انقضا: {$c['expire_at']}\n\n"
                             . "💡 جهت حفظ پیوستگی سرویس، می‌توانید از هم‌اکنون اشتراک خود را تمدید فرمایید:";

                        $kb = [
                            'inline_keyboard' => [
                                [['text' => '🔄 تمدید آنی با ۱ کلیک', 'callback_data' => 'renew_acc_' . $c['id']]],
                                [['text' => '🌐 مشاهده وضعیت ساب‌لینک', 'url' => $subUrl]]
                            ]
                        ];

                        $sent = TelegramBot::sendMessage($msg, (string)$chatId, $kb, $botToken);
                        if ($sent) {
                            Helpers::logActivity('alert_exp_sent', "ارسال یادآوری انقضای زمان به کلاینت {$c['username']}", 'system', (string)$c['id']);
                            $notifiedCount++;
                        }
                    }
                }
            }
        }

        // 2. Auto-Failover Logic: check if servers are marked offline and re-route clients if needed
        $autoFailoverEnabled = (int)Setting::get('auto_failover_enabled', '1');
        if ($autoFailoverEnabled) {
            $offlineServers = $pdo->query("SELECT * FROM server_nodes WHERE is_active = 1 AND (health_status = 'offline' OR latency_ms < 0)")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($offlineServers as $offNode) {
                // Find a healthy online server in the same group or default
                $stmtAlt = $pdo->prepare("SELECT id, name FROM server_nodes WHERE id != ? AND is_active = 1 AND health_status = 'online' ORDER BY latency_ms ASC LIMIT 1");
                $stmtAlt->execute([$offNode['id']]);
                $altNode = $stmtAlt->fetch(PDO::FETCH_ASSOC);

                if ($altNode) {
                    $upd = $pdo->prepare("UPDATE clients SET server_id = ? WHERE server_id = ? AND status = 'active'");
                    $upd->execute([$altNode['id'], $offNode['id']]);
                    $switched = $upd->rowCount();
                    if ($switched > 0) {
                        $failoverCount += $switched;
                        $msgLog = "سوییچ خودکار (Failover): تعداد {$switched} کاربر از سرور آفلاین '{$offNode['name']}' به سرور جایگزین '{$altNode['name']}' منتقل شدند.";
                        Helpers::logActivity('auto_failover', $msgLog, 'system', (string)$offNode['id']);
                        TelegramBot::sendTopicLog('errors', "⚠️ <b>گزارش فیل‌اور خودکار سرور</b>\n\n{$msgLog}\n⏱ زمان: " . Helpers::formatDate(time()));
                    }
                }
            }
        }

        return [
            'alerts_sent' => $notifiedCount,
            'failovers' => $failoverCount,
            'checked_at' => date('Y-m-d H:i:s')
        ];
    }
}
