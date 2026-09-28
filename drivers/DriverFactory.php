<?php
require_once __DIR__ . '/PanelDriverInterface.php';
require_once __DIR__ . '/MarzbanDriver.php';
require_once __DIR__ . '/PasargadDriver.php';
require_once __DIR__ . '/XUiDriver.php';
require_once __DIR__ . '/MockDriver.php';

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
        $pass = $server['api_password'] ?? '';
        $token = $server['api_token'] ?? '';
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
                return new XUiDriver($url, $user, $pass, $subDomain);
            case 'mock':
            default:
                return new MockDriver($url, $user, $pass, $token);
        }
    }
}
