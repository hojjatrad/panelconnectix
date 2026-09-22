<?php
interface PanelDriverInterface {
    /**
     * Authenticate or test connection to the remote panel API
     */
    public function authenticate(): bool;

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
}
