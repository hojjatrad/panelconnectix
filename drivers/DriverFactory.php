<?php
require_once __DIR__ . '/PanelDriverInterface.php';
require_once __DIR__ . '/../core/Encryption.php';
require_once __DIR__ . '/MarzbanDriver.php';
require_once __DIR__ . '/PasargadDriver.php';
require_once __DIR__ . '/XUiDriver.php';
require_once __DIR__ . '/MockDriver.php';
require_once __DIR__ . '/ConnectixSellerDriver.php';

class DriverFactory {
    public static function create(array $server): PanelDriverInterface {
        $driver = strtolower($server['driver'] ?? $server['server_driver'] ?? '');

        if (empty($driver) && !empty($server['server_id'])) {
            try {
                $pdo = Database::getConnection();
                $sRow = $pdo->query("SELECT * FROM server_nodes WHERE id = " . (int)$server['server_id'])->fetch();
                if ($sRow) {
                    $server = array_merge($sRow, $server);
                    $driver = strtolower($server['driver'] ?? '');
                }
            } catch (Throwable $ignore) {}
        }

        if (empty($driver)) {
            $driver = 'mock';
        }

        $url = $server['api_url'] ?? '';
        $user = $server['api_username'] ?? '';
        $pass = Encryption::decrypt($server['api_password'] ?? '');
        $token = Encryption::decrypt($server['api_token'] ?? '');
        $subDomain = $server['sub_domain'] ?? $server['server_sub_domain'] ?? null;

        switch ($driver) {
            case 'pasargad':
            case 'pasarguard':
            case 'pasar_guard':
                return new PasargadDriver($url, $user, $pass, $token, $subDomain);
            case 'marzban':
                return new MarzbanDriver($url, $user, $pass, $token, $subDomain);
            case '3xui':
            case 'xui':
                return new XUiDriver($url, $user, $pass, $token, $subDomain);
            case 'connectix':
            case 'connectix_seller':
            case 'seller':
            case 'seller_api':
                return new ConnectixSellerDriver($url, $user, $pass, $token, $subDomain);
            case 'mock':
            default:
                // Auto-detect Connectix Seller API by URL pattern
                $lowUrl = strtolower($url);
                $lowToken = strtolower($token);
                if (str_contains($lowUrl, 'api.connectix.vip') || str_contains($lowUrl, 'seller-api.connectix.vip') || str_contains($lowUrl, 'seller.connectix.vip')) {
                    return new ConnectixSellerDriver($url, $user, $pass, $token, $subDomain);
                }
                // Auto-detect PasarGuard API Key format pg_key_
                if (str_starts_with($lowToken, 'pg_key_') || str_contains($lowUrl, 'speedur.org')) {
                    return new PasargadDriver($url, $user, $pass, $token, $subDomain);
                }
                // Auto-detect Connectix token format (40 chars alphanumeric)
                if (preg_match('/^[a-z0-9]{35,45}$/i', $token) && strlen($token) >= 35) {
                    return new ConnectixSellerDriver($url, $user, $pass, $token, $subDomain);
                }
                return new MockDriver($url, $user, $pass, $token);
        }
    }
}
