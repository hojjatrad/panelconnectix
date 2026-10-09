import 'dart:io';
import 'dart:convert';
import 'package:flutter_vless/flutter_vless.dart';
import 'xray_service.dart';

/// ULTRA v3.7.0 - Dual-Core Xray + sing-box compat
/// Supports: VLESS (Reality, Vision), VMess, Trojan, Shadowsocks, SOCKS, HTTP, WireGuard, Raw JSON, Clash YAML, sing-box JSON
/// - Android: FlutterVless (Xray AAR + sing-box via Maven) with VpnService TUN
/// - Windows: XrayService (Xray.exe) + fallback to FlutterVless Windows
/// - iOS/macOS: FlutterVless NetworkExtension

// For backward compatibility, keep V2RayStatus alias
typedef V2RayStatus = VlessStatus;

class V2RayCompat {
  static V2RayCompat? _instance;
  
  static V2RayCompat? getInstanceOrNull() {
    return _instance;
  }

  V2RayCompat({void Function(VlessStatus)? onStatusChanged}) {
    _onStatusChanged = onStatusChanged ?? _noop;
    _xray.onStatusChanged = (s) => _onStatusChanged(s);
    _instance = this;
  }

  late final void Function(VlessStatus) _onStatusChanged;
  static void _noop(VlessStatus status) {}

  final XrayService _xray = XrayService.instance;
  FlutterVless? _vless;

  bool get isWindows => Platform.isWindows;
  bool get isAndroid => Platform.isAndroid;

  Future<bool> isElevated() async => _xray.isElevated();

  Future<void> initializeV2Ray() async {
    if (isWindows) {
      // Try FlutterVless Windows first, fallback to XrayService
      try {
        _vless ??= FlutterVless(onStatusChanged: (s) => _onStatusChanged(s));
        await _vless!.initializeVless();
        return;
      } catch (_) {
        await _xray.initialize();
        return;
      }
    }
    _vless ??= FlutterVless(onStatusChanged: (s) => _onStatusChanged(s));
    await _vless!.initializeVless();
  }

  Future<int?> getServerDelay({required String config}) async {
    if (isWindows) {
      try {
        if (_vless != null) {
          return await _vless!.getServerDelay(config: config);
        }
      } catch (_) {}
      return XrayService.measureTcpDelay(config);
    }
    try {
      return await _vless!.getServerDelay(config: config);
    } catch (_) {
      return null;
    }
  }

  Future<bool> requestPermission() async {
    if (isWindows) return true;
    try {
      return await _vless!.requestPermission();
    } catch (_) {
      return false;
    }
  }

  Future<void> startV2Ray({
    required String remark,
    required String config,
    List<String>? blockedApps,
    List<String>? bypassSubnets,
    bool proxyOnly = false,
    bool tunMode = false,
    String notificationDisconnectButtonName = "DISCONNECT",
  }) async {
    if (isWindows) {
      // Try FlutterVless Windows, fallback to XrayService
      try {
        if (_vless != null) {
          await _vless!.startVless(
            remark: remark,
            config: config,
            blockedApps: blockedApps,
            bypassSubnets: bypassSubnets,
            proxyOnly: proxyOnly,
            notificationDisconnectButtonName: notificationDisconnectButtonName,
          );
          return;
        }
      } catch (_) {}
      await _xray.start(config, tunMode: tunMode);
      return;
    }
    await _vless!.startVless(
      remark: remark,
      config: config,
      blockedApps: blockedApps,
      bypassSubnets: bypassSubnets,
      proxyOnly: proxyOnly,
      notificationDisconnectButtonName: notificationDisconnectButtonName,
    );
  }

  Future<void> stopV2Ray() async {
    if (isWindows) {
      try {
        await _vless?.stopVless();
      } catch (_) {}
      try {
        await _xray.stop();
      } catch (_) {}
      _onStatusChanged(VlessStatus(state: 'DISCONNECTED'));
      return;
    }
    await _vless?.stopVless();
  }

  Future<void> shutdown() async {
    if (isWindows) {
      try { await _xray.disposeOnExit(); } catch (_) {}
      try { await _vless?.stopVless(); } catch (_) {}
    }
  }

  // v4.0.41 FORENSIC FIX: Safe shutdown that never throws, checks instance exists
  Future<void> shutdownSafe() async {
    try {
      if (isWindows) {
        try { 
          await _xray.disposeOnExit().timeout(const Duration(seconds: 2), onTimeout: () {}); 
        } catch (_) {}
        try { 
          await _vless?.stopVless().timeout(const Duration(seconds: 2), onTimeout: () {}); 
        } catch (_) {}
      } else {
        try {
          await _vless?.stopVless().timeout(const Duration(seconds: 2), onTimeout: () {});
        } catch (_) {}
      }
    } catch (_) {}
  }

  // ============ ULTRA: Universal parser supporting all requested protocols ============
  // This replaces FlutterV2ray.parseFromURL with FlutterVless.parse + WireGuard + HTTP + Raw JSON

  static FlutterVlessURL _parseWithWireGuardSupport(String input) {
    final trimmed = input.trim();
    
    // Raw JSON detection
    if (trimmed.startsWith('{') && trimmed.endsWith('}')) {
      try {
        jsonDecode(trimmed);
        return FlutterVless.parse(trimmed);
      } catch (_) {}
    }

    // Clash YAML detection (contains proxies: or mixed-port:)
    if (trimmed.contains('proxies:') || trimmed.contains('mixed-port:') || trimmed.contains('proxy-groups:')) {
      return FlutterVless.parse(trimmed);
    }

    // sing-box JSON detection (contains outbounds)
    if (trimmed.contains('"outbounds"') && trimmed.contains('"type"')) {
      return FlutterVless.parse(trimmed);
    }

    // WireGuard URL: wg://privateKey@host:port?publicKey=xxx&address=10.0.0.2/32&...
    // Convert to Clash YAML then parse via flutter_vless
    if (trimmed.toLowerCase().startsWith('wg://') || trimmed.toLowerCase().startsWith('wireguard://')) {
      try {
        final clashYaml = _convertWireGuardToClashYaml(trimmed);
        return FlutterVless.parse(clashYaml);
      } catch (e) {
        // Fallback: try to create Xray WireGuard JSON manually
        final xrayJson = _convertWireGuardToXrayJson(trimmed);
        return FlutterVless.parse(xrayJson);
      }
    }

    // HTTP proxy URL: http://user:pass@host:port or https://
    if (trimmed.toLowerCase().startsWith('http://') || trimmed.toLowerCase().startsWith('https://')) {
      // Check if it's actually a sub link (https://sub.domain) vs http proxy
      // If it contains @ and is not a sub link, treat as HTTP proxy
      if (trimmed.contains('@') && !trimmed.contains('/sub/')) {
        try {
          final xrayJson = _convertHttpProxyToXrayJson(trimmed);
          return FlutterVless.parse(xrayJson);
        } catch (_) {}
      }
      // Otherwise it's a subscription link, let subscription parser handle
    }

    // Standard parsing via flutter_vless (supports vless://, vmess://, trojan://, ss://, socks://, hy2://, hysteria2://)
    try {
      return FlutterVless.parse(trimmed);
    } catch (_) {
      // Try parseFromURL as fallback for socks:// which parse() might miss if single
      try {
        return FlutterVless.parseFromURL(trimmed);
      } catch (_) {
        rethrow;
      }
    }
  }

  static String _convertWireGuardToClashYaml(String wgUrl) {
    // Parse wg://privateKey@host:port?publicKey=xxx&address=10.0.0.2/32&allowedIPs=0.0.0.0/0&reserved=...#name
    // Example: wg://privateKey@1.2.3.4:51820?publicKey=peerPubKey&address=10.0.0.2/32#MyWG
    try {
      String url = wgUrl.trim();
      // Remove wg:// or wireguard:// prefix
      url = url.replaceFirst(RegExp(r'^wg://', caseSensitive: false), '');
      url = url.replaceFirst(RegExp(r'^wireguard://', caseSensitive: false), '');
      
      String name = 'WireGuard';
      if (url.contains('#')) {
        final parts = url.split('#');
        url = parts[0];
        name = Uri.decodeComponent(parts.sublist(1).join('#'));
      }

      String privateKey = '';
      String hostPort = '';
      Map<String, String> params = {};

      if (url.contains('@')) {
        final atParts = url.split('@');
        privateKey = atParts[0];
        final rest = atParts.sublist(1).join('@');
        if (rest.contains('?')) {
          final qParts = rest.split('?');
          hostPort = qParts[0];
          final query = qParts.sublist(1).join('?');
          params = Uri.splitQueryString(query);
        } else {
          hostPort = rest;
        }
      } else {
        // No @, might be just host:port with params
        if (url.contains('?')) {
          final qParts = url.split('?');
          hostPort = qParts[0];
          params = Uri.splitQueryString(qParts.sublist(1).join('?'));
          privateKey = params['privateKey'] ?? params['private-key'] ?? '';
        } else {
          hostPort = url;
        }
      }

      String host = '';
      String port = '51820';
      if (hostPort.contains(':')) {
        final hp = hostPort.split(':');
        port = hp.last;
        host = hp.sublist(0, hp.length - 1).join(':');
      } else {
        host = hostPort;
      }

      final publicKey = params['publicKey'] ?? params['public-key'] ?? params['peerPublicKey'] ?? '';
      final address = params['address'] ?? params['ip'] ?? '10.0.0.2/32';
      final allowedIPs = params['allowedIPs'] ?? params['allowed-ips'] ?? '0.0.0.0/0, ::/0';
      final reserved = params['reserved'] ?? '';
      final mtu = params['mtu'] ?? '1280';

      // Build Clash YAML for WireGuard
      final yaml = '''
mixed-port: 7890
proxies:
  - name: "$name"
    type: wireguard
    server: $host
    port: $port
    ip: ${address.split('/')[0]}
    ipv6: ""
    private-key: $privateKey
    public-key: $publicKey
    allowed-ips: ["${allowedIPs.split(',').map((e) => e.trim()).join('", "')}"]
    ${reserved.isNotEmpty ? 'reserved: [$reserved]' : ''}
    mtu: $mtu
    udp: true
proxy-groups:
  - name: PROXY
    type: select
    proxies: ["$name"]
rules:
  - MATCH,PROXY
''';
      return yaml;
    } catch (e) {
      throw Exception('Failed to convert WireGuard URL: $e');
    }
  }

  static String _convertWireGuardToXrayJson(String wgUrl) {
    // Fallback: Generate Xray JSON for WireGuard outbound
    try {
      String url = wgUrl.trim();
      url = url.replaceFirst(RegExp(r'^wg://', caseSensitive: false), '');
      url = url.replaceFirst(RegExp(r'^wireguard://', caseSensitive: false), '');
      
      String name = 'WireGuard';
      if (url.contains('#')) {
        final parts = url.split('#');
        url = parts[0];
        name = Uri.decodeComponent(parts.sublist(1).join('#'));
      }

      String privateKey = '';
      String hostPort = '';
      Map<String, String> params = {};

      if (url.contains('@')) {
        final atParts = url.split('@');
        privateKey = atParts[0];
        final rest = atParts.sublist(1).join('@');
        if (rest.contains('?')) {
          final qParts = rest.split('?');
          hostPort = qParts[0];
          params = Uri.splitQueryString(qParts.sublist(1).join('?'));
        } else {
          hostPort = rest;
        }
      }

      String host = '';
      String port = '51820';
      if (hostPort.contains(':')) {
        final hp = hostPort.split(':');
        port = hp.last;
        host = hp.sublist(0, hp.length - 1).join(':');
      } else {
        host = hostPort;
      }

      final publicKey = params['publicKey'] ?? params['public-key'] ?? '';
      final address = params['address'] ?? '10.0.0.2/32';

      final xrayConfig = {
        "log": {"loglevel": "warning"},
        "inbounds": [
          {
            "port": 10808,
            "protocol": "socks",
            "settings": {"auth": "noauth", "udp": true},
            "sniffing": {"enabled": true, "destOverride": ["http", "tls"]}
          }
        ],
        "outbounds": [
          {
            "protocol": "wireguard",
            "settings": {
              "secretKey": privateKey,
              "address": [address],
              "peers": [
                {
                  "publicKey": publicKey,
                  "endpoint": "$host:$port",
                  "allowedIPs": ["0.0.0.0/0", "::/0"]
                }
              ]
            }
          }
        ]
      };
      return jsonEncode(xrayConfig);
    } catch (e) {
      throw Exception('Failed to convert WireGuard to Xray JSON: $e');
    }
  }

  static String _convertHttpProxyToXrayJson(String httpUrl) {
    try {
      final uri = Uri.parse(httpUrl);
      final host = uri.host;
      final port = uri.port != 0 ? uri.port : (uri.scheme == 'https' ? 443 : 80);
      final username = uri.userInfo.contains(':') ? uri.userInfo.split(':')[0] : uri.userInfo;
      final password = uri.userInfo.contains(':') ? uri.userInfo.split(':').sublist(1).join(':') : '';

      final config = {
        "log": {"loglevel": "warning"},
        "inbounds": [
          {
            "port": 10808,
            "protocol": "socks",
            "settings": {"auth": "noauth", "udp": true}
          }
        ],
        "outbounds": [
          {
            "protocol": "http",
            "settings": {
              "servers": [
                {
                  "address": host,
                  "port": port,
                  "users": username.isNotEmpty ? [{"user": username, "pass": password}] : []
                }
              ]
            }
          }
        ]
      };
      return jsonEncode(config);
    } catch (e) {
      throw Exception('Failed to convert HTTP proxy: $e');
    }
  }

  // Public method for dashboard to parse any protocol
  static FlutterVlessURL parseUniversal(String input) {
    return _parseWithWireGuardSupport(input);
  }

  // For subscription with multiple protocols including WireGuard
  static List<FlutterVlessURL> parseManyUniversal(String input) {
    final trimmed = input.trim();
    
    // If it's a single WG link, convert to Clash YAML first
    if (trimmed.toLowerCase().startsWith('wg://') || trimmed.toLowerCase().startsWith('wireguard://')) {
      try {
        final yaml = _convertWireGuardToClashYaml(trimmed);
        return FlutterVless.parseMany(yaml);
      } catch (_) {
        final json = _convertWireGuardToXrayJson(trimmed);
        return FlutterVless.parseMany(json);
      }
    }

    try {
      return FlutterVless.parseMany(trimmed);
    } catch (_) {
      // Fallback to single parse
      try {
        return [FlutterVless.parse(trimmed)];
      } catch (_) {
        return [];
      }
    }
  }
}

// Compatibility wrapper for old FlutterV2ray API
class FlutterV2ray {
  static FlutterVlessURL parseFromURL(String url) {
    return V2RayCompat.parseUniversal(url);
  }
}
