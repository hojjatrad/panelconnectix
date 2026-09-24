<?php
require_once __DIR__ . '/PanelDriverInterface.php';
require_once __DIR__ . '/MarzbanDriver.php';
require_once __DIR__ . '/PasargadDriver.php';
require_once __DIR__ . '/XUiDriver.php';
require_once __DIR__ . '/MockDriver.php';

class DriverFactory {
    public static function create(array $server): PanelDriverInterface {
        $driver = strtolower($server['driver'] ?? 'mock');
        $url = $server['api_url'] ?? '';
        $user = $server['api_username'] ?? '';
        $pass = $server['api_password'] ?? '';
        $token = $server['api_token'] ?? '';
        $subDomain = $server['sub_domain'] ?? null;

        switch ($driver) {
            case 'marzban':
                return new MarzbanDriver($url, $user, $pass, $token, $subDomain);
            case 'pasargad':
            case 'pasarguard':
            case 'pasar_guard':
                return new PasargadDriver($url, $user, $pass, $token, $subDomain);
            case '3xui':
            case 'xui':
                return new XUiDriver($url, $user, $pass);
            case 'mock':
            default:
                return new MockDriver($url, $user, $pass, $token);
        }
    }
}
