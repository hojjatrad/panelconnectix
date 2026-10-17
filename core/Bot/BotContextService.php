<?php
/**
 * v4.0.53 BotContextService - Extracted from TelegramBotController
 * Single Responsibility: Context resolution, client sublink, force join, plans
 */
class BotContextService {
    public static function resolveContext(PDO $pdo, ?string $token = null, ?int $resellerId = null): array {
        $token = $token ?: ($_GET['bot_token'] ?? $_GET['token'] ?? null);
        $resellerId = $resellerId ?: (isset($_GET['reseller_id']) ? (int)$_GET['reseller_id'] : null);

        $reseller = null;
        if (!empty($token)) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_bot_token = ? LIMIT 1");
            $stmt->execute([$token]);
            $reseller = $stmt->fetch();
        } elseif (!empty($resellerId)) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$resellerId]);
            $reseller = $stmt->fetch();
        }

        if (!$reseller) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
            $stmt->execute();
            $reseller = $stmt->fetch();
            $token = $token ?: TelegramBot::getToken();
        }

        $brand = !empty($reseller['brand_name']) ? $reseller['brand_name'] : Setting::get('brand_name', 'کانکتیکس');
        $card = [
            'number' => !empty($reseller['card_number']) ? $reseller['card_number'] : Setting::get('bank_card', Setting::get('card_number', '۶۰۳۷-۹۹۷۵-xxxx-xxxx')),
            'holder' => !empty($reseller['card_holder']) ? $reseller['card_holder'] : Setting::get('bank_card_owner', Setting::get('card_holder', 'مدیریت')),
            'shaba' => !empty($reseller['card_shaba']) ? $reseller['card_shaba'] : Setting::get('card_sheba', ''),
        ];
        $adminChatId = !empty($reseller['telegram_admin_chat_id']) ? $reseller['telegram_admin_chat_id'] : TelegramBot::getAdminChatId();

        $ctx = [
            'reseller_id' => (int)($reseller['id'] ?? 1),
            'reseller' => $reseller,
            'bot_token' => $token,
            'brand_name' => $brand,
            'card' => $card,
            'admin_chat_id' => $adminChatId,
            'support_username' => !empty($reseller['support_username']) ? $reseller['support_username'] : Setting::get('telegram_support', ''),
            'channel' => !empty($reseller['telegram_channel']) ? $reseller['telegram_channel'] : Setting::get('bot_force_join_channel', '')
        ];

        // Store in static for compatibility
        TelegramBotController::$currentContext = $ctx;
        return $ctx;
    }

    public static function getContext(?PDO $pdo = null): array {
        if (TelegramBotController::$currentContext !== null) {
            return TelegramBotController::$currentContext;
        }
        $pdo = $pdo ?: Database::getConnection();
        return self::resolveContext($pdo);
    }

    public static function getClientPrimarySublink(array $client, ?PDO $pdo = null): string {
        $pdo = $pdo ?: Database::getConnection();
        $subUrlLocal = Helpers::subUrl($client['sub_token'] ?? '');

        if (!empty($client['node_sublink']) && 
            $client['node_sublink'] !== $subUrlLocal && 
            !Helpers::isPanelSubUrl($client['node_sublink'])) {
            $link = $client['node_sublink'];
            $fixed = Helpers::fixSublinkDomain($link);
            if ($fixed !== $link) {
                $stmt = $pdo->prepare("UPDATE clients SET node_sublink = ? WHERE id = ?");
                $stmt->execute([$link, $client['id']]);
            }
            return $link;
        }

        if (!empty($client['username'])) {
            try {
                $node = null;
                if (!empty($client['server_id'])) {
                    $stmtNode = $pdo->prepare("SELECT * FROM server_nodes WHERE id = ?");
                    $stmtNode->execute([(int)$client['server_id']]);
                    $node = $stmtNode->fetch(PDO::FETCH_ASSOC);
                }
                if (!$node) {
                    $node = Provisioner::findBestServer('default', $pdo);
                }

                if ($node && $node['driver'] !== 'mock') {
                    $driver = DriverFactory::create($node);
                    $live = $driver->getUser($client['username']);

                    if (!$live) {
                        $provRes = $driver->createUser([
                            'username' => $client['username'],
                            'password' => $client['password'] ?: '123456',
                            'uuid' => $client['uuid'] ?: Helpers::generateUUID(),
                            'sub_token' => $client['sub_token'],
                            'traffic_limit_bytes' => (int)($client['traffic_limit_bytes'] ?? 0),
                            'expire_timestamp' => !empty($client['expire_at']) ? strtotime($client['expire_at']) : (time() + 30 * 86400)
                        ]);
                        if ($provRes['success']) {
                            $live = $driver->getUser($client['username']);
                            if (!empty($provRes['sublink'])) {
                                $pdo->prepare("UPDATE clients SET server_id = ?, node_sublink = ? WHERE id = ?")
                                    ->execute([$node['id'], $provRes['sublink'], $client['id']]);
                                return $provRes['sublink'];
                            }
                        }
                    }

                    if ($live && !empty($live['subscription_url'])) {
                        $liveSub = $live['subscription_url'];
                        $pdo->prepare("UPDATE clients SET server_id = ?, node_sublink = ? WHERE id = ?")
                            ->execute([$node['id'], $liveSub, $client['id']]);
                        return $liveSub;
                    }
                    if ($live && !empty($live['links'])) {
                        $firstLink = $live['links'][0];
                        $pdo->prepare("UPDATE clients SET server_id = ?, node_sublink = ? WHERE id = ?")
                            ->execute([$node['id'], $firstLink, $client['id']]);
                        return $firstLink;
                    }
                }
            } catch (Throwable $e) {}
        }

        return $subUrlLocal;
    }

    public static function checkForceJoin(PDO $pdo, string $chatId, string $fromId, ?string $botToken = null): bool {
        $ctx = self::getContext($pdo);
        $channel = trim(!empty($ctx['channel']) ? $ctx['channel'] : Setting::get('bot_force_join_channel', ''));
        if (empty($channel)) {
            return true;
        }

        $botToken = $botToken ?: $ctx['bot_token'];
        $res = TelegramBot::getChatMember($channel, (int)$fromId, $botToken);
        if ($res && isset($res['status'])) {
            $status = $res['status'];
            if (in_array($status, ['creator', 'administrator', 'member', 'restricted'])) {
                return true;
            }
        }

        $channelClean = ltrim($channel, '@');
        $channelUrl = str_starts_with($channel, '-') ? '#' : "https://t.me/{$channelClean}";

        $joinMsg = "⚠️ <b>عضویت در کانال الزامی است</b>\n\n"
                 . "کاربر گرامی، جهت استفاده از کلیه خدمات ربات، عضویت در کانال رسمی اطلاع‌رسانی الزامی است:\n\n"
                 . "📢 <b>کانال رسمی:</b> {$channel}\n\n"
                 . "لطفاً ابتدا در کانال عضو شده و سپس دکمه «تایید عضویت ✅» را لمس فرمایید.";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📢 عضویت در کانال رسمی', 'url' => $channelUrl]
                ],
                [
                    ['text' => '✅ تایید عضویت', 'callback_data' => 'check_join']
                ]
            ]
        ];

        TelegramBot::sendMessage($joinMsg, $chatId, $keyboard, $botToken);
        return false;
    }

    public static function getPlansForReseller(PDO $pdo, int $resellerId, bool $includeFree = false): array {
        // v4.0.53 Use ApcuCache for plans (60s TTL)
        $cacheKey = "bot_plans_{$resellerId}_" . ($includeFree ? '1' : '0');
        if (class_exists('ApcuCache')) {
            $cached = ApcuCache::get($cacheKey, null);
            if ($cached !== null) return $cached;
        }

        Database::ensureExtendedTablesExist($pdo);
        $sql = "SELECT p.*, 
                       COALESCE(rp.custom_title, p.title) as display_title,
                       COALESCE(rp.custom_category, p.category, '۱ ماهه') as display_category,
                       COALESCE(rp.retail_price, p.base_price) as display_price,
                       COALESCE(rp.is_active, 1) as display_active,
                       0 as is_custom_plan,
                       0 as reseller_plan_id,
                       s.name as server_name,
                       s.driver as server_driver,
                       s.server_group as server_node_group,
                       s.is_vip as server_is_vip,
                       p.server_group as server_group,
                       p.vip_group_name as vip_group_name,
                       p.category_id as category_id,
                       c.name as category_real_name,
                       c.slug as category_slug
                FROM plans p
                LEFT JOIN reseller_plans rp ON p.id = rp.plan_id AND rp.reseller_id = ? AND rp.is_custom = 0
                LEFT JOIN server_nodes s ON p.server_id = s.id
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.is_active = 1 AND COALESCE(rp.is_active, 1) = 1 AND COALESCE(p.show_in_bot, 1) = 1";
        if (!$includeFree) {
            $sql .= " AND p.is_free = 0";
        }
        $sql .= " ORDER BY s.id ASC, display_category ASC, display_price ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$resellerId]);
        $basePlans = $stmt->fetchAll();

        try {
            $sqlCustom = "SELECT 
                rp.id as custom_id,
                rp.custom_title as title,
                rp.custom_title as display_title,
                rp.custom_category as category,
                rp.custom_category as display_category,
                rp.retail_price as base_price,
                rp.retail_price as display_price,
                rp.traffic_gb,
                rp.duration_days,
                rp.ip_limit,
                rp.server_id,
                rp.base_cost as reseller_price,
                rp.description,
                1 as is_custom_plan,
                rp.id as reseller_plan_id,
                rp.is_active as display_active,
                0 as is_free,
                1 as is_active,
                rp.id as id,
                'custom' as server_group,
                '' as vip_group_name,
                s.name as server_name,
                s.driver as server_driver,
                s.server_group as server_node_group,
                s.is_vip as server_is_vip,
                1 as show_in_bot,
                NULL as category_id,
                rp.custom_category as category_real_name,
                '' as category_slug
                FROM reseller_plans rp
                LEFT JOIN server_nodes s ON rp.server_id = s.id
                WHERE rp.reseller_id = ? AND rp.is_custom = 1 AND rp.is_active = 1
                ORDER BY rp.custom_category ASC, rp.retail_price ASC";
            $stmt2 = $pdo->prepare($sqlCustom);
            $stmt2->execute([$resellerId]);
            $customPlans = $stmt2->fetchAll();
            $result = array_merge($basePlans, $customPlans);
        } catch (Throwable $e) {
            $result = $basePlans;
        }

        if (class_exists('ApcuCache')) {
            ApcuCache::set($cacheKey, $result, 60);
        }
        return $result;
    }

    public static function recordBotUser(PDO $pdo, array $from, int $resellerId = 1): void {
        try {
            $tgId = (string)($from['id'] ?? '');
            if (empty($tgId)) return;
            $stmt = $pdo->prepare("SELECT id FROM bot_users WHERE telegram_id = ? AND reseller_id = ? LIMIT 1");
            $stmt->execute([$tgId, $resellerId]);
            $exists = $stmt->fetchColumn();
            $username = $from['username'] ?? '';
            $firstName = $from['first_name'] ?? '';
            $lastName = $from['last_name'] ?? '';
            $fullName = trim("$firstName $lastName");
            if ($exists) {
                $pdo->prepare("UPDATE bot_users SET username = ?, first_name = ?, last_name = ?, full_name = ?, last_seen_at = NOW() WHERE id = ?")
                    ->execute([$username, $firstName, $lastName, $fullName, $exists]);
            } else {
                $pdo->prepare("INSERT INTO bot_users (telegram_id, reseller_id, username, first_name, last_name, full_name, created_at, last_seen_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())")
                    ->execute([$tgId, $resellerId, $username, $firstName, $lastName, $fullName]);
            }
        } catch (Throwable $e) {}
    }
}
