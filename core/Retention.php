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
        $adminChatId = Setting::get('telegram_admin_chat_id');
        $lowThresholdPercent = (int)Setting::get('low_traffic_alert_threshold', '10');
        $expiryAlertDays = (int)Setting::get('expiry_alert_days', '3');

        $notifiedCount = 0;
        $failoverCount = 0;

        // 1. Process Low Traffic Alerts (< 10% or < 2GB remaining)
        $clients = $pdo->query("SELECT c.*, p.title as plan_title, u.brand_name 
                                FROM clients c 
                                LEFT JOIN plans p ON c.plan_id = p.id 
                                LEFT JOIN users u ON c.reseller_id = u.id 
                                WHERE c.status = 'active'")->fetchAll(PDO::FETCH_ASSOC);

        $now = time();
        foreach ($clients as $c) {
            $totalBytes = (float)($c['total_traffic_bytes'] ?? 0);
            $usedBytes = (float)($c['used_traffic_bytes'] ?? 0);
            $remainingBytes = max(0, $totalBytes - $usedBytes);
            $remainingGb = round($remainingBytes / (1024 * 1024 * 1024), 2);
            $percentRemaining = $totalBytes > 0 ? ($remainingBytes / $totalBytes) * 100 : 100;

            $chatId = $c['telegram_chat_id'] ?? null;
            if (empty($chatId)) {
                // If client doesn't have a direct telegram chat, check if bot user exists with same username or notes
                $botUser = $pdo->query("SELECT telegram_id FROM bot_users WHERE username = " . $pdo->quote($c['username']) . " LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if ($botUser) {
                    $chatId = $botUser['telegram_id'];
                }
            }

            // A. Low Traffic Alert (< 10% remaining and not sent yet)
            if ($totalBytes > 0 && $percentRemaining <= $lowThresholdPercent && empty($c['alert_95_sent'])) {
                if ($chatId && !empty($botToken)) {
                    $msg = "⚠️ **هشدار رو به اتمام بودن ترافیک اشتراک**\n\n"
                         . "👤 کاربر گرامی: `{$c['username']}`\n"
                         . "📦 پلن: {$c['plan_title']}\n"
                         . "📊 ترافیک باقیمانده: **{$remainingGb} گیگابایت** (" . round($percentRemaining) . "%)\n\n"
                         . "جهت جلوگیری از قطع شدن اتصال اینترنت، لطفاً اشتراک خود را تمدید فرمایید.\n"
                         . "🔗 لینک مدیریت اشتراک:\n" . Helpers::url("sub/status?token={$c['sub_rev_token']}");
                    
                    TelegramBot::sendMessage($chatId, $msg, null, $botToken);
                }
                $pdo->exec("UPDATE clients SET alert_95_sent = 1 WHERE id = {$c['id']}");
                $notifiedCount++;
            }

            // B. Expiry Alert (<= 3 days remaining and not sent yet)
            if (!empty($c['expires_at'])) {
                $expTime = strtotime($c['expires_at']);
                $daysLeft = ($expTime - $now) / 86400;

                if ($daysLeft > 0 && $daysLeft <= $expiryAlertDays && empty($c['alert_exp_sent'])) {
                    if ($chatId && !empty($botToken)) {
                        $daysLeftRounded = max(1, round($daysLeft));
                        $msg = "⏳ **یادآوری رو به اتمام بودن زمان اشتراک**\n\n"
                             . "👤 کاربر گرامی: `{$c['username']}`\n"
                             . "📦 پلن: {$c['plan_title']}\n"
                             . "📅 مهلت باقیمانده: **{$daysLeftRounded} روز**\n"
                             . "🗓 تاریخ انقضا: {$c['expires_at']}\n\n"
                             . "جهت استمرار سرویس بدون اختلال، می‌توانید از هم‌اکنون نسبت به تمدید اقدام نمایید.\n"
                             . "🔗 لینک تمدید:\n" . Helpers::url("sub/status?token={$c['sub_rev_token']}");

                        TelegramBot::sendMessage($chatId, $msg, null, $botToken);
                    }
                    $pdo->exec("UPDATE clients SET alert_exp_sent = 1 WHERE id = {$c['id']}");
                    $notifiedCount++;
                }
            }
        }

        // 2. Auto-Failover Logic: check if servers are marked offline and re-route clients if needed
        $autoFailoverEnabled = (int)Setting::get('auto_failover_enabled', '1');
        if ($autoFailoverEnabled) {
            $offlineServers = $pdo->query("SELECT * FROM server_nodes WHERE health_status = 'offline' OR (last_checked_at IS NOT NULL AND latency_ms < 0)")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($offlineServers as $offNode) {
                // Find a healthy online server in the same group
                $stmtAlt = $pdo->prepare("SELECT id FROM server_nodes WHERE id != ? AND server_group = ? AND is_active = 1 AND health_status = 'online' ORDER BY latency_ms ASC LIMIT 1");
                $stmtAlt->execute([$offNode['id'], $offNode['server_group']]);
                $altNodeId = $stmtAlt->fetchColumn();

                if ($altNodeId) {
                    $upd = $pdo->prepare("UPDATE clients SET server_id = ? WHERE server_id = ? AND status = 'active'");
                    $upd->execute([$altNodeId, $offNode['id']]);
                    $switched = $upd->rowCount();
                    if ($switched > 0) {
                        $failoverCount += $switched;
                        // Log event
                        $msgLog = "سوییچ خودکار (Failover): تعداد {$switched} کاربر از سرور آفلاین '{$offNode['name']}' به سرور جایگزین منتقل شدند.";
                        try {
                            $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (1, 'auto_failover', ?)")->execute([$msgLog]);
                        } catch (Throwable $e) {}
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
