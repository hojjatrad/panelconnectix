<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Helpers.php';
require_once __DIR__ . '/../core/Setting.php';

class WebappController {
    public function index(): void {
        $pdo = Database::getConnection();
        $tgId = trim($_GET['tg_id'] ?? '');

        $clientAccounts = [];
        $botUser = null;

        if (!empty($tgId)) {
            $stmt = $pdo->prepare("SELECT c.*, p.title as plan_title, s.name as server_name 
                                   FROM clients c 
                                   LEFT JOIN plans p ON c.plan_id = p.id 
                                   LEFT JOIN server_nodes s ON c.server_id = s.id 
                                   WHERE c.telegram_chat_id = ? ORDER BY c.id DESC");
            $stmt->execute([$tgId]);
            $clientAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmtUser = $pdo->prepare("SELECT * FROM bot_users WHERE tg_id = ?");
            $stmtUser->execute([$tgId]);
            $botUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
        }

        $plans = $pdo->query("SELECT * FROM plans WHERE is_active = 1 AND is_free = 0 ORDER BY base_price ASC")->fetchAll(PDO::FETCH_ASSOC);
        $branding = $pdo->query("SELECT * FROM branding_metadata WHERE user_id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $brandName = $branding['brand_name'] ?? 'Connectix VPN';
        $logoUrl = $branding['logo_url'] ?? '';

        require __DIR__ . '/../views/webapp/index.php';
    }
}
