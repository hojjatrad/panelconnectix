<?php
interface PanelDriverInterface {
    /**
     * Authenticate or test connection to the remote panel API
     */
    public function authenticate(): bool;

    /**
     * Get the last error message encountered during driver operations
     */
    public function getLastError(): ?string;

    /**
     * Create or provision a new VPN client on the remote node
     *
     * @param array $payload ['username', 'uuid', 'traffic_limit_bytes', 'expire_timestamp', 'proxies']
     * @return array ['success' => bool, 'uuid' => string, 'sublink' => string, 'error' => ?string]
     */
    public function createUser(array $payload): array;

    /**
     * Retrieve user live status and usage from the remote node
     *
     * @return ?array ['traffic_used_bytes', 'traffic_limit_bytes', 'expire_at', 'status', 'online']
     */
    public function getUser(string $username): ?array;

    /**
     * Extend user traffic and/or time (used for renewals and reserved plans)
     */
    public function extendUser(string $username, int $addTrafficBytes, int $addSeconds): bool;

    /**
     * Delete or revoke user from the remote node
     */
    public function deleteUser(string $username): bool;

    /**
     * Enable or disable user on the remote node
     */
    public function toggleUserStatus(string $username, bool $active): bool;

    /**
     * Get node system statistics (uptime, users count, status)
     */
    public function getNodeStats(): array;

    /**
     * List ALL clients/users that exist on the remote node (regardless of
     * whether the panel tracks them). Used by the server "node users" admin
     * view for live management + link/credential export.
     *
     * Each row: [
     *   'username' => string,
     *   'status' => 'active'|'disabled'|'expired'|'limited',
     *   'online' => bool,
     *   'traffic_used_bytes' => int,
     *   'traffic_limit_bytes' => int (0 = unlimited),
     *   'expire_at' => ?string 'Y-m-d H:i:s',
     *   'subscription_url' => string (absolute, '' when unknown),
     *   'links' => string[] (raw config lines: vless://, vmess://, trojan://, ss://),
     * ]
     */
    public function listUsers(): array;
}
