import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math' as math;
import 'package:flutter_v2ray/flutter_v2ray.dart';

/// Windows tunnel backend — runs the bundled open-source Xray core
/// (data/xray/Xray.exe) with a config generated from the panel's
/// vless/vmess/trojan/ss URLs (reusing flutter_v2ray's pure-Dart URL
/// parser), and routes Windows traffic through the system proxy
/// (HKCU Internet Settings via reg.exe — no admin rights, no drivers).
///
/// Phase 1 = proxy mode (browsers + WinINET apps). TUN mode (all traffic
/// incl. UDP/games) is a planned phase 2 via WinTUN.
class XrayService {
  XrayService._();
  static final XrayService instance = XrayService._();

  static const int socksPort = 10808;
  static const int httpPort = 10809;
  static const String proxyServer = '127.0.0.1:$httpPort';

  static const String _regKey =
      r'Software\Microsoft\Windows\CurrentVersion\Internet Settings';

  Process? _proc;
  File? _configFile;
  Timer? _ticker;
  DateTime? _startedAt;
  int _pid = 0;

  void Function(V2RayStatus)? onStatusChanged;

  // Saved original proxy settings (restored on stop/dispose).
  int? _origProxyEnable;
  String? _origProxyServer;
  bool _proxySaved = false;

  bool get isRunning => _proc != null;

  String get _exeDir => File(Platform.resolvedExecutable).parent.path;
  String get _xrayExe => '$_exeDir\\data\\xray\\Xray.exe';

  /// Core present? (CI bundles it into data/xray/)
  Future<bool> initialize() async {
    return File(_xrayExe).existsSync();
  }

  Future<String> coreStatus() async {
    return File(_xrayExe).existsSync() ? 'ok' : 'missing';
  }

  /// Whether the app runs elevated (admin). TUN mode needs admin on the
  /// FIRST run (creates the Wintun TAP adapter + installs the driver);
  /// afterwards the adapter persists and no elevation is needed.
  Future<bool> isElevated() async {
    try {
      final r = await Process.run('net', ['session']);
      return r.exitCode == 0;
    } catch (_) {
      return false;
    }
  }

  bool _lastTunMode = false;

  /// Start Xray with the full V2Ray/Xray JSON config (as produced by
  /// flutter_v2ray's getFullConfiguration).
  ///
  /// Inbound mode:
  ///  - [tunMode] false → SOCKS5+HTTP localhost listeners + Windows system
  ///    proxy (Phase 1, no admin needed).
  ///  - [tunMode] true  → Xray TUN inbound (Wintun TAP adapter): every app
  ///    on the machine routes through the tunnel, incl. UDP/games (Phase 2).
  Future<bool> start(String configJson, {bool tunMode = false}) async {
    if (!File(_xrayExe).existsSync()) {
      throw Exception('هسته Xray پیدا نشد (data/xray/Xray.exe)');
    }
    await stop();

    Map<String, dynamic> cfg;
    try {
      cfg = jsonDecode(configJson) as Map<String, dynamic>;
    } catch (_) {
      throw Exception('کانفیگ نامعتبر است');
    }

    if (tunMode) {
      // Full-tunnel mode: Xray owns a Wintun TAP adapter; all packets
      // (IPv4/IPv6, TCP/UDP, DNS) enter the tunnel. The adapter is created
      // on first run (needs admin once) and torn down when the core exits.
      cfg['inbounds'] = [
        {
          'tag': 'tun-in',
          'listen': '127.0.0.1',
          'port': 0,
          'protocol': 'dokodemo-door',
          'settings': {'address': '127.0.0.1', 'followRedirect': true},
          'sniffing': {
            'enabled': true,
            'destOverride': ['http', 'tls', 'fqdn'],
          },
          'tun': {
            'mtu': 1500,
            'gso': true,
            'domainStrategy': 'AsIs',
            'stack': 'system',
            'dns': false,
          },
        }
      ];
    } else {
      // Windows listeners: SOCKS5 + HTTP on localhost (system proxy mode).
      cfg['inbounds'] = [
        {
          'tag': 'socks-in',
          'listen': '127.0.0.1',
          'port': socksPort,
          'protocol': 'socks',
          'settings': {'auth': 'noauth', 'udp': true},
        },
        {
          'tag': 'http-in',
          'listen': '127.0.0.1',
          'port': httpPort,
          'protocol': 'http',
        },
      ];
    }
    cfg['log'] = {'loglevel': 'warning'};

    _pid = math.Random().nextInt(100000);
    _configFile = File('${Directory.systemTemp.path}\\connectix_xray_$_pid.json');
    await _configFile!.writeAsString(jsonEncode(cfg));

    try {
      _proc = await Process.start(_xrayExe, ['run', '-c', _configFile!.path],
          mode: ProcessStartMode.normal);
    } catch (e) {
      _configFile?.deleteSync();
      _configFile = null;
      throw Exception('شروع Xray ناموفق بود: $e');
    }

    final proc = _proc;
    proc?.exitCode.then((_) {
      // Core died on its own (config error / server down) — report it.
      if (identical(_proc, proc)) {
        _proc = null;
        _ticker?.cancel();
        _restoreProxy();
        onStatusChanged?.call(V2RayStatus(state: 'DISCONNECTED'));
      }
    });

    _lastTunMode = tunMode;
    if (!tunMode) {
      await _applyProxy();
    }
    _startedAt = DateTime.now();
    _ticker = Timer.periodic(const Duration(seconds: 1), (_) {
      final alive = _proc != null;
      if (!alive) return;
      onStatusChanged?.call(V2RayStatus(
        state: 'CONNECTED',
        duration: _formatDuration(),
      ));
    });
    onStatusChanged?.call(V2RayStatus(state: 'CONNECTED'));
    return true;
  }

  Future<void> stop() async {
    _ticker?.cancel();
    _ticker = null;
    final proc = _proc;
    _proc = null;
    if (proc != null) {
      try {
        // Kill the whole process tree (xray may spawn children).
        await Process.run('taskkill', ['/F', '/T', '/PID', '${proc.pid}']);
      } catch (_) {
        try {
          proc.kill();
        } catch (_) {}
      }
    }
    try {
      _configFile?.deleteSync();
    } catch (_) {}
    _configFile = null;
    _restoreProxy();
  }

  /// Restore proxy on app exit (called from the session lifecycle hook).
  Future<void> disposeOnExit() async {
    try {
      await stop();
    } catch (_) {}
  }

  String _formatDuration() {
    final d = DateTime.now().difference(_startedAt ?? DateTime.now());
    String two(int v) => v.toString().padLeft(2, '0');
    final h = d.inHours, m = d.inMinutes % 60, s = d.inSeconds % 60;
    return '${two(h)}:${two(m)}:${two(s)}';
  }

  // ---------------- Windows system proxy (reg.exe, no admin) ----------------

  Future<void> _applyProxy() async {
    await _saveOriginalProxy();
    await Process.run('reg', [
      'add', _regKey, '/v', 'ProxyEnable', '/t', 'REG_DWORD', '/d', '1', '/f'
    ]);
    await Process.run('reg', [
      'add', _regKey, '/v', 'ProxyServer', '/t', 'REG_SZ', '/d', proxyServer, '/f'
    ]);
    // LAN bypass so local network keeps working directly.
    await Process.run('reg', [
      'add',
      _regKey,
      '/v',
      'ProxyOverride',
      '/t',
      'REG_SZ',
      '/d',
      '<local>;localhost;127.*;10.*;172.16.*;172.17.*;172.18.*;172.19.*;172.2*'
          ';172.30.*;172.31.*;192.168.*',
      '/f'
    ]);
  }

  Future<void> _saveOriginalProxy() async {
    if (_proxySaved) return;
    try {
      final r1 = await Process.run('reg', ['query', _regKey, '/v', 'ProxyEnable']);
      if (r1.exitCode == 0) {
        final m =
            RegExp(r'ProxyEnable\s+REG_DWORD\s+0x([0-9a-fA-F]+)').firstMatch(r1.stdout.toString());
        if (m != null) _origProxyEnable = int.parse(m.group(1)!, radix: 16);
      }
      final r2 = await Process.run('reg', ['query', _regKey, '/v', 'ProxyServer']);
      if (r2.exitCode == 0) {
        final m =
            RegExp(r'ProxyServer\s+REG_SZ\s+(.+)').firstMatch(r2.stdout.toString());
        if (m != null) _origProxyServer = m.group(1)!.trim();
      }
      _proxySaved = true;
    } catch (_) {}
  }

  void _restoreProxy() {
    _proxySaved = false;
    final enable = _origProxyEnable ?? 0;
    unawaited(Process.run('reg', [
      'add', _regKey, '/v', 'ProxyEnable', '/t', 'REG_DWORD', '/d', '$enable', '/f'
    ]));
    if (_origProxyServer != null && _origProxyServer!.isNotEmpty) {
      unawaited(Process.run('reg', [
        'add', _regKey, '/v', 'ProxyServer', '/t', 'REG_SZ', '/d', _origProxyServer!, '/f'
      ]));
    }
    _origProxyServer = null;
  }

  // ---------------- TCP RTT (server delay ranking) ----------------

  /// Measures a plain TCP connect RTT to the server address:port parsed
  /// from the full config JSON (outbound vnext/servers). A good, cheap
  /// ranking signal without needing to boot a full tunnel per server.
  static Future<int?> measureTcpDelay(String configJson) async {
    try {
      final cfg = jsonDecode(configJson) as Map<String, dynamic>;
      final outbounds = (cfg['outbounds'] as List?) ?? const [];
      if (outbounds.isEmpty) return null;
      String host = '';
      int port = 443;
      final first = outbounds.first as Map<String, dynamic>;
      final settings = (first['settings'] as Map<String, dynamic>?) ?? const {};
      final vnext = (settings['vnext'] as List?)?.first;
      if (vnext is Map<String, dynamic>) {
        host = (vnext['address'] ?? '') as String;
        port = (vnext['port'] as int?) ?? 443;
      } else {
        final servers = (settings['servers'] as List?)?.first;
        if (servers is Map<String, dynamic>) {
          host = (servers['address'] ?? '') as String;
          port = (servers['port'] as int?) ?? 443;
        }
      }
      if (host.isEmpty) return null;

      final sw = Stopwatch()..start();
      final socket =
          await Socket.connect(host, port, timeout: const Duration(seconds: 5));
      sw.stop();
      socket.destroy();
      return sw.elapsedMilliseconds;
    } catch (_) {
      return null;
    }
  }
}
