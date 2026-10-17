<?php
/**
 * v4.0.53 BotSessionService - Session management
 */
class BotSessionService {
    public static function getSession(PDO $pdo, string $tgId): ?array {
        try {
            $stmt = $pdo->prepare("SELECT * FROM bot_sessions WHERE telegram_id = ? LIMIT 1");
            $stmt->execute([$tgId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null;
            return [
                'step' => $row['step'] ?? '',
                'data' => json_decode($row['data'] ?? '{}', true) ?: [],
                'raw' => $row
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function setSession(PDO $pdo, string $tgId, string $step, array $data = []): void {
        try {
            $json = json_encode($data, JSON_UNESCAPED_UNICODE);
            $stmt = $pdo->prepare("SELECT id FROM bot_sessions WHERE telegram_id = ? LIMIT 1");
            $stmt->execute([$tgId]);
            $exists = $stmt->fetchColumn();
            if ($exists) {
                $pdo->prepare("UPDATE bot_sessions SET step = ?, data = ?, updated_at = NOW() WHERE telegram_id = ?")
                    ->execute([$step, $json, $tgId]);
            } else {
                $pdo->prepare("INSERT INTO bot_sessions (telegram_id, step, data, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())")
                    ->execute([$tgId, $step, $json]);
            }
        } catch (Throwable $e) {}
    }

    public static function clearSession(PDO $pdo, string $tgId): void {
        try {
            $pdo->prepare("DELETE FROM bot_sessions WHERE telegram_id = ?")->execute([$tgId]);
        } catch (Throwable $e) {}
    }
}
