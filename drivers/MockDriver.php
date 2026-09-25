<?php
require_once __DIR__ . '/PanelDriverInterface.php';

class MockDriver implements PanelDriverInterface {
    private string $baseUrl;
    private string $domain;

    private ?string $lastError = null;

    public function getLastError(): ?string {
        return $this->lastError;
    }

    public function __construct(string $baseUrl, ?string $username = null, ?string $password = null, ?string $token = null) {
        $this->baseUrl = $baseUrl;
        $this->domain = parse_url($baseUrl, PHP_URL_HOST) ?: 'node.connectix.space';
    }

    public function authenticate(): bool {
        return true;
    }

    public function createUser(array $payload): array {
        $uuid = $payload['uuid'];
        $username = $payload['username'];
        
        // Generate simulated VLESS & VMess links
        $vlessLink = "vless://{$uuid}@{$this->domain}:443?encryption=none&security=reality&sni={$this->domain}&fp=chrome&pbk=mock_public_key_xray&sid=123456&type=tcp&headerType=none#Connectix-{$username}";
        
        return [
            'success' => true,
            'uuid' => $uuid,
            'sublink' => Helpers::url('sub/' . ($payload['sub_token'] ?? $uuid)),
            'vless_link' => $vlessLink,
            'error' => null
        ];
    }

    public function getUser(string $username): ?array {
        return [
            'traffic_used_bytes' => 0,
            'traffic_limit_bytes' => 50 * 1024 * 1024 * 1024,
            'expire_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            'status' => 'active',
            'online' => false
        ];
    }

    public function extendUser(string $username, int $addTrafficBytes, int $addSeconds): bool {
        return true;
    }

    public function deleteUser(string $username): bool {
        return true;
    }

    public function toggleUserStatus(string $username, bool $active): bool {
        return true;
    }

    /**
     * Mock nodes never expose user lists (permanent no-fake-data rule).
     */
    public function listUsers(): array {
        return [];
    }

    public function getNodeStats(): array {
        return [
            'status' => 'online',
            'version' => 'Mock Core Node (Online)',
            'users' => rand(40, 180),
            'cpu' => rand(12, 38) . '%',
            'ram' => rand(1, 3) . '.' . rand(1, 9) . ' GB / 8 GB'
        ];
    }
}
