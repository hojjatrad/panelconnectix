<?php
/**
 * Multi-Protocol Generator - v4.0.73 TUIC + Hysteria2 + Reality
 * Generates VLESS Reality + Hysteria2 + TUIC v5 links for each user
 * 
 * TUIC: QUIC-based, 0-RTT, lowest latency 110-140ms, best for gaming/voice
 * Hysteria2: QUIC-based with Brutal CC, best for streaming on lossy networks
 * VLESS Reality: TCP-based, most resistant to filtering (9.5/10)
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Setting.php';

class MultiProtocolGenerator {
    
    /**
     * Generate all 3 protocols for a user
     * @param array $client - client data (username, uuid, password, etc)
     * @param array $server - server data (name, sub_domain, ip, etc)
     * @return array - ['vless' => link, 'hysteria2' => link, 'tuic' => link, 'all' => [links]]
     */
    public static function generateForClient(array $client, array $server): array {
        $username = $client['username'] ?? 'user';
        $uuid = $client['uuid'] ?? '';
        $password = $client['password'] ?? $uuid;
        $subDomain = $server['sub_domain'] ?? $server['domain'] ?? '';
        $serverName = $server['name'] ?? 'سرور';
        
        // Extract host from sub_domain or api_url
        $host = self::extractHost($server);
        if (empty($host)) {
            $host = 'example.com';
        }
        
        // Get ports from settings or use defaults
        $realityPort = (int)Setting::get('reality_port', '443');
        $hysteria2Port = (int)Setting::get('hysteria2_port', '4443');
        $tuicPort = (int)Setting::get('tuic_port', '8443');
        $sni = Setting::get('tuic_sni', $host);
        $hysteria2Sni = Setting::get('hysteria2_sni', $host);
        
        // Generate VLESS Reality (if not already exists, create basic one)
        $vlessLink = $client['vless_link'] ?? '';
        if (empty($vlessLink) && !empty($uuid)) {
            // Basic Reality link - will be replaced by actual from remote panel if available
            $vlessLink = "vless://{$uuid}@{$host}:{$realityPort}?encryption=none&flow=xtls-rprx-vision&security=reality&sni=www.google.com&fp=chrome&pbk=your_public_key&sid=your_short_id&type=tcp#{$serverName}-Reality-{$username}";
        }
        
        // Generate Hysteria2 link - QUIC with Brutal CC, best for streaming
        // Format: hysteria2://password@host:port?insecure=0&sni=host&obfs=salamander&obfs-password=xxx#name
        $hysteria2Link = "hysteria2://{$password}@{$host}:{$hysteria2Port}?insecure=0&sni={$hysteria2Sni}&obfs=salamander&obfs-password={$password}#{$serverName}-Hysteria2-{$username}";
        
        // Generate TUIC v5 link - QUIC with BBR, lowest latency, best for gaming
        // Format: tuic://uuid:password@host:port?congestion_control=bbr&udp_relay_mode=native&alpn=h3&sni=host&allow_insecure=0#name
        $tuicLink = "tuic://{$uuid}:{$password}@{$host}:{$tuicPort}?congestion_control=bbr&udp_relay_mode=native&alpn=h3&sni={$sni}&allow_insecure=0#{$serverName}-TUIC-{$username}";
        
        return [
            'vless' => $vlessLink,
            'hysteria2' => $hysteria2Link,
            'tuic' => $tuicLink,
            'all' => array_filter([$vlessLink, $hysteria2Link, $tuicLink]),
            'protocols' => [
                'reality' => [
                    'link' => $vlessLink,
                    'name' => "{$serverName} - Reality (پایدار)",
                    'desc' => 'مقاوم‌ترین در برابر فیلتر - 9.5/10 - مناسب روزمره',
                    'icon' => '🛡️',
                    'color' => '#10B981',
                    'best_for' => 'روزمره + استقامت در فیلترینگ شدید'
                ],
                'hysteria2' => [
                    'link' => $hysteria2Link,
                    'name' => "{$serverName} - Hysteria2 (سریع)",
                    'desc' => 'سریع‌ترین در شبکه ضعیف - مناسب استریم 4K + دانلود',
                    'icon' => '⚡',
                    'color' => '#F59E0B',
                    'best_for' => 'استریم + دانلود در شبکه ضعیف'
                ],
                'tuic' => [
                    'link' => $tuicLink,
                    'name' => "{$serverName} - TUIC v5 (تاخیر کم)",
                    'desc' => 'کمترین تاخیر 110-140ms - مناسب بازی آنلاین + تماس',
                    'icon' => '🚀',
                    'color' => '#8B5CF6',
                    'best_for' => 'بازی آنلاین + تماس صوتی + API'
                ]
            ]
        ];
    }
    
    /**
     * Extract host from server data
     */
    public static function extractHost(array $server): string {
        // Try sub_domain first
        $subDomain = $server['sub_domain'] ?? '';
        if (!empty($subDomain)) {
            $subDomain = trim($subDomain);
            if (str_starts_with($subDomain, 'http')) {
                $parsed = parse_url($subDomain);
                return $parsed['host'] ?? '';
            }
            // Remove protocol if present
            $subDomain = preg_replace('#^https?://#', '', $subDomain);
            $subDomain = explode('/', $subDomain)[0];
            $subDomain = explode(':', $subDomain)[0];
            return $subDomain;
        }
        
        // Try api_url
        $apiUrl = $server['api_url'] ?? '';
        if (!empty($apiUrl)) {
            $parsed = parse_url($apiUrl);
            return $parsed['host'] ?? '';
        }
        
        // Try domain field
        $domain = $server['domain'] ?? '';
        if (!empty($domain)) {
            return $domain;
        }
        
        // Try ip
        $ip = $server['ip'] ?? $server['server_ip'] ?? '';
        if (!empty($ip)) {
            return $ip;
        }
        
        return '';
    }
    
    /**
     * Generate TUIC server config for sing-box
     * @param string $domain - server domain
     * @param int $port - TUIC port
     * @param string $certPath - TLS cert path
     * @param string $keyPath - TLS key path
     * @return array - sing-box config
     */
    public static function generateTuicServerConfig(string $domain, int $port = 8443, string $certPath = '', string $keyPath = ''): array {
        if (empty($certPath)) {
            $certPath = "/etc/letsencrypt/live/{$domain}/fullchain.pem";
        }
        if (empty($keyPath)) {
            $keyPath = "/etc/letsencrypt/live/{$domain}/privkey.pem";
        }
        
        return [
            'inbounds' => [
                [
                    'type' => 'tuic',
                    'tag' => 'tuic-in',
                    'listen' => '::',
                    'listen_port' => $port,
                    'users' => [], // Will be populated dynamically
                    'congestion_control' => 'bbr',
                    'zero_rtt_handshake' => true,
                    'heartbeat' => '10s',
                    'tls' => [
                        'enabled' => true,
                        'server_name' => $domain,
                        'certificate_path' => $certPath,
                        'key_path' => $keyPath,
                        'alpn' => ['h3']
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Generate Hysteria2 server config for sing-box
     */
    public static function generateHysteria2ServerConfig(string $domain, int $port = 4443, string $certPath = '', string $keyPath = ''): array {
        if (empty($certPath)) {
            $certPath = "/etc/letsencrypt/live/{$domain}/fullchain.pem";
        }
        if (empty($keyPath)) {
            $keyPath = "/etc/letsencrypt/live/{$domain}/privkey.pem";
        }
        
        return [
            'inbounds' => [
                [
                    'type' => 'hysteria2',
                    'tag' => 'hysteria2-in',
                    'listen' => '::',
                    'listen_port' => $port,
                    'users' => [],
                    'obfs' => [
                        'type' => 'salamander',
                        'password' => 'your_obfs_password'
                    ],
                    'tls' => [
                        'enabled' => true,
                        'server_name' => $domain,
                        'certificate_path' => $certPath,
                        'key_path' => $keyPath,
                        'alpn' => ['h3']
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Generate combined multi-protocol sing-box config (Reality + Hysteria2 + TUIC)
     */
    public static function generateCombinedConfig(string $domain, array $ports = []): array {
        $realityPort = $ports['reality'] ?? 443;
        $hysteria2Port = $ports['hysteria2'] ?? 4443;
        $tuicPort = $ports['tuic'] ?? 8443;
        $certPath = "/etc/letsencrypt/live/{$domain}/fullchain.pem";
        $keyPath = "/etc/letsencrypt/live/{$domain}/privkey.pem";
        
        return [
            'log' => ['level' => 'info'],
            'inbounds' => [
                // VLESS Reality - TCP 443
                [
                    'type' => 'vless',
                    'tag' => 'vless-reality-in',
                    'listen' => '::',
                    'listen_port' => $realityPort,
                    'users' => [],
                    'tls' => [
                        'enabled' => true,
                        'server_name' => 'www.google.com',
                        'reality' => [
                            'enabled' => true,
                            'handshake' => [
                                'server' => 'www.google.com',
                                'server_port' => 443
                            ],
                            'private_key' => 'your_reality_private_key',
                            'short_id' => ['your_short_id']
                        ]
                    ]
                ],
                // Hysteria2 - UDP 4443
                [
                    'type' => 'hysteria2',
                    'tag' => 'hysteria2-in',
                    'listen' => '::',
                    'listen_port' => $hysteria2Port,
                    'users' => [],
                    'obfs' => [
                        'type' => 'salamander',
                        'password' => 'obfs_password'
                    ],
                    'tls' => [
                        'enabled' => true,
                        'server_name' => $domain,
                        'certificate_path' => $certPath,
                        'key_path' => $keyPath
                    ]
                ],
                // TUIC v5 - UDP 8443
                [
                    'type' => 'tuic',
                    'tag' => 'tuic-in',
                    'listen' => '::',
                    'listen_port' => $tuicPort,
                    'users' => [],
                    'congestion_control' => 'bbr',
                    'zero_rtt_handshake' => true,
                    'heartbeat' => '10s',
                    'tls' => [
                        'enabled' => true,
                        'server_name' => $domain,
                        'certificate_path' => $certPath,
                        'key_path' => $keyPath,
                        'alpn' => ['h3']
                    ]
                ]
            ],
            'outbounds' => [
                ['type' => 'direct', 'tag' => 'direct'],
                ['type' => 'block', 'tag' => 'block']
            ],
            'route' => [
                'rules' => [
                    ['action' => 'sniff'],
                    ['protocol' => 'dns', 'action' => 'hijack-dns']
                ]
            ]
        ];
    }
}
