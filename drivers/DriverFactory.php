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

        switch ($driver) {
            case 'marzban':
                return new MarzbanDriver($url, $user, $pass, $token);
            case 'pasargad':
                return new PasargadDriver($url, $user, $pass, $token);
            case '3xui':
            case 'xui':
                return new XUiDriver($url, $user, $pass);
            case 'mock':
            default:
                return new MockDriver($url, $user, $pass, $token);
        }
    }
}
