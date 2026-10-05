import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math' as math;
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../models/client_model.dart';
import '../models/server_model.dart';

class ApiService {
  // 3.6.0-IR-CLOUDFLARE-FIX: Multi-endpoint failover for Iran
  // Priority: ir.vpbotn.ir (Iran-optimized via Cloudflare) -> cf -> main -> api
  static List<String> baseUrls = [
    "https://ir.vpbotn.ir/contax",      // Priority 1: Iran-optimized (Cloudflare proxied, fastest from Iran)
    "https://cf.vpbotn.ir/contax",      // Priority 2: Cloudflare backup
    "https://vpbotn.ir/contax",         // Priority 3: Direct main domain (now also Cloudflare proxied)
    "https://api.vpbotn.ir/contax",     // Priority 4: API subdomain backup
  ];
  
  static String baseUrl = "https://ir.vpbotn.ir/contax";
  static const MethodChannel _updaterChannel = MethodChannel('com.connectix.vpn/updater');

  // ---------------- Diagnostic Log Ring Buffer ----------------
  static final List<String> _logLines = <String>[];

  static void log(String msg) {
    try {
      _logLines.add('${DateTime.now().toIso8601String().substring(11, 19)} $msg');
      if (_logLines.length > 80) {
        _logLines.removeAt(0);
      }
    } catch (_) {}
  }

  static String get logDump => _logLines.join('\n');

  static final Connectivity _connectivity = Connectivity();

  // ----------------------------------------------------------
  // 3.6.0: Smart init - loads last working URL first
  static Future<void> initBaseUrl() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final savedWorking = prefs.getString('api_base_url_working') ?? '';
      final legacy = prefs.getString('api_base_url') ?? '';
      
      if (savedWorking.isNotEmpty && baseUrls.contains(savedWorking)) {
        // Move working URL to front
        baseUrl = savedWorking;
        baseUrls = [savedWorking, ...baseUrls.where((u) => u != savedWorking)];
        log('initBaseUrl: using saved working $savedWorking');
      } else if (legacy.isNotEmpty && baseUrls.contains(legacy)) {
        baseUrl = legacy;
      } else {
        baseUrl = baseUrls[0];
      }
      
      await prefs.setString('api_base_url', baseUrl);
      await prefs.setString('api_base_url_working', baseUrl);
    } catch (_) {
      baseUrl = baseUrls[0];
    }
  }

  static List<String> getOrderedBaseUrls() {
    // Returns baseUrls with current baseUrl first
    if (baseUrls.isEmpty) return [baseUrl];
    if (baseUrls.first == baseUrl) return baseUrls;
    return [baseUrl, ...baseUrls.where((u) => u != baseUrl)];
  }

  static Future<void> _saveWorkingUrl(String workingUrl) async {
    try {
      baseUrl = workingUrl;
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('api_base_url_working', workingUrl);
      await prefs.setString('api_base_url', workingUrl);
      // Reorder list to prioritize working URL next time
      baseUrls = [workingUrl, ...baseUrls.where((u) => u != workingUrl)];
      log('Saved working URL: $workingUrl');
    } catch (_) {}
  }

  /**
   * Persistent Session Verification (Auto-Login)
   */
  static Future<Map<String, dynamic>?> checkSavedSession() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final isLoggedIn = prefs.getBool('is_logged_in') ?? false;
      final token = prefs.getString('auth_token') ?? '';
      final cachedClientStr = prefs.getString('cached_client');
      final cachedBrandingStr = prefs.getString('cached_branding');

      if (isLoggedIn && token.isNotEmpty && cachedClientStr != null) {
        final clientMap = jsonDecode(cachedClientStr);
        final brandingMap = cachedBrandingStr != null ? jsonDecode(cachedBrandingStr) : {};
        final cachedServers = await getCachedServers();

        return {
          'client': ClientModel.fromJson(clientMap),
          'branding': BrandingModel.fromJson(brandingMap),
          'servers': cachedServers,
        };
      }
      return null;
    } catch (e) {
      debugPrint("checkSavedSession Error: $e");
      return null;
    }
  }

  static Future<void> saveCachedServers(List<ServerModel> servers) async {
    try {
      if (servers.isEmpty) return;
      final prefs = await SharedPreferences.getInstance();
      final list = servers.map((s) => s.toJson()).toList();
      await prefs.setString('cached_servers', jsonEncode(list));
    } catch (e) {
      debugPrint("saveCachedServers Error: $e");
    }
  }

  static Future<List<ServerModel>> getCachedServers() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final str = prefs.getString('cached_servers');
      if (str != null && str.isNotEmpty) {
        final List list = jsonDecode(str);
        return list.map((e) => ServerModel.fromJson(e)).toList();
      }
    } catch (e) {
      debugPrint("getCachedServers Error: $e");
    }
    return [];
  }

  // 3.6.0: Login with multi-endpoint failover
  static Future<Map<String, dynamic>> login(String username, String password) async {
    final orderedUrls = getOrderedBaseUrls();
    log('login start: trying ${orderedUrls.length} endpoints for user $username');
    
    for (int i = 0; i < orderedUrls.length; i++) {
      final currentBase = orderedUrls[i];
      try {
        log('login try ${i+1}/${orderedUrls.length}: $currentBase');
        final url = Uri.parse("$currentBase/api/v1/app/login");
        final response = await http.post(
          url,
          headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
          body: jsonEncode({'username': username, 'password': password}),
        ).timeout(const Duration(seconds: 12));

        final data = jsonDecode(utf8.decode(response.bodyBytes));
        if (data['success'] == true) {
          await _saveWorkingUrl(currentBase);
          final prefs = await SharedPreferences.getInstance();
          await prefs.setBool('is_logged_in', true);
          await prefs.setString('auth_token', data['data']['auth_token'] ?? '');
          await prefs.setString('saved_username', username);
          await prefs.setString('saved_password', password);

          if (data['data']['client'] != null) {
            await prefs.setString('cached_client', jsonEncode(data['data']['client']));
            if (data['data']['client']['sub_url'] != null) {
              await prefs.setString('sub_url', data['data']['client']['sub_url'].toString());
            }
          }
          if (data['data']['branding'] != null) {
            await prefs.setString('cached_branding', jsonEncode(data['data']['branding']));
          }

          List<ServerModel> initialServers = [];
          if (data['data']['servers'] != null && data['data']['servers'] is List) {
            final List sList = data['data']['servers'];
            final parsed = sList
                .map((e) => ServerModel.fromJson(e))
                .where((s) => !s.isInfoBanner && !s.configUri.contains('mock_pbk') && s.id != 'mci_reality_de')
                .toList();
            if (parsed.isNotEmpty) {
              initialServers = parsed;
            }
          }

          if (initialServers.isNotEmpty) {
            await saveCachedServers(initialServers);
          }

          log('login SUCCESS via $currentBase');
          return {
            'success': true,
            'client': ClientModel.fromJson(data['data']['client']),
            'branding': BrandingModel.fromJson(data['data']['branding']),
            'servers': initialServers,
          };
        } else {
          // Username/password wrong - no need to try other endpoints
          log('login FAILED (auth) via $currentBase: ${data['error']}');
          return {
            'success': false,
            'error': data['error'] ?? 'نام کاربری یا رمز عبور اشتباه است.',
          };
        }
      } catch (e) {
        log('login error via $currentBase: $e');
        if (i == orderedUrls.length - 1) {
          // Last endpoint failed
          if (e is TimeoutException) {
            return {'success': false, 'error': 'اتصال به سرور طول کشید. تمام سرورها تست شد. اینترنت خود را بررسی کنید.'};
          }
          return {'success': false, 'error': 'خطا در برقراری ارتباط با سرور: $e\nتمام ${orderedUrls.length} آدرس تست شد.'};
        }
        // Try next endpoint
        await Future.delayed(Duration(milliseconds: 300));
        continue;
      }
    }
    return {'success': false, 'error': 'خطا در برقراری ارتباط با سرور'};
  }

  /**
   * Universal Inbounds Delivery Engine with failover
   */
  static Future<List<ServerModel>> getServers() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token') ?? '';
      final subUrl = prefs.getString('sub_url') ?? '';

      List<ServerModel> servers = [];
      final orderedUrls = getOrderedBaseUrls();

      // 1. Primary: Try all baseUrls for configs API
      for (int i = 0; i < orderedUrls.length; i++) {
        final currentBase = orderedUrls[i];
        try {
          final url = Uri.parse("$currentBase/api/v1/app/configs?auth_token=${Uri.encodeComponent(token)}");
          final response = await http.get(
            url,
            headers: {
              'Authorization': 'Bearer $token',
              'X-Auth-Token': token,
              'Accept': 'application/json'
            },
          ).timeout(const Duration(seconds: 12));
          log('configs API try ${i+1}: base=$currentBase HTTP ${response.statusCode}');
          if (response.statusCode != 200) {
            final snippet = response.body.length > 200 ? response.body.substring(0, 200) : response.body;
            log('configs API non-200 body: $snippet');
            continue;
          }
          final data = jsonDecode(utf8.decode(response.bodyBytes));
          if (data['success'] == true && data['data'] != null && data['data']['servers'] != null) {
            final List list = data['data']['servers'];
            final parsed = list
                .map((e) => ServerModel.fromJson(e))
                .where((s) => !s.isInfoBanner && !s.configUri.contains('mock_pbk') && s.id != 'mci_reality_de')
                .toList();
            log('configs API via $currentBase: ${parsed.length} clean servers');
            
            if (parsed.isNotEmpty) {
              servers = parsed;
              await _saveWorkingUrl(currentBase);
              break; // Success, no need to try more
            } else {
              log('configs list empty after filtering — trying next');
            }
          } else {
            log('configs API via $currentBase: success=${data['success']} error=${data['error']}');
          }
        } catch (e) {
          log('API configs fetch error via $currentBase: $e');
          if (i == orderedUrls.length - 1) {
            debugPrint("API configs fetch error (all endpoints failed): $e");
          }
        }
      }

      // 2. Direct Node / Panel Sublink Auto-Resolver
      if (servers.isEmpty && subUrl.isNotEmpty) {
        try {
          log('sublink fallback: $subUrl');
          final subResp = await http.get(
            Uri.parse(subUrl),
            headers: {'User-Agent': 'v2rayNG/1.8.5'},
          ).timeout(const Duration(seconds: 15));
          log('sublink HTTP ${subResp.statusCode} (${subResp.body.length} bytes)');

          if (subResp.statusCode == 200 && subResp.body.isNotEmpty) {
            String decoded = subResp.body.trim();
            try {
              decoded = utf8.decode(base64Decode(decoded));
            } catch (_) {}
            final lines = decoded
                .split(RegExp(r'[\r\n]+'))
                .map((l) => l.trim())
                .where((l) => l.isNotEmpty)
                .toList();

            int idx = 1;
            for (final line in lines) {
              if (line.contains('://')) {
                final model = ServerModel.fromUri(line, idx++);
                if (!model.isInfoBanner) {
                  servers.add(model);
                }
              }
            }
            log('sublink parsed ${servers.length} servers');
          }
        } catch (e) {
          log('Sublink direct resolver error: $e');
          debugPrint("Sublink direct resolver error: $e");
        }
      } else if (servers.isEmpty) {
        log('NO SERVERS: API failed/empty and sub_url is not saved');
      }

      if (servers.isNotEmpty) {
        await saveCachedServers(servers);
      } else {
        servers = await getCachedServers();
      }

      return servers;
    } catch (e) {
      log('getServers Top-level error: $e');
      debugPrint("getServers Top-level error: $e");
      return await getCachedServers();
    }
  }

  static Future<int?> pingServerUri(String uriStr) async {
    try {
      String host = '';
      int port = 443;
      final lower = uriStr.toLowerCase().trim();
      
      // Helper to extract host/port from generic URI
      Uri? _tryParse(String s) {
        try { return Uri.tryParse(s); } catch (_) { return null; }
      }

      if (lower.startsWith('vless://') || lower.startsWith('trojan://') || lower.startsWith('ss://') || lower.startsWith('socks://') || lower.startsWith('http://') || lower.startsWith('https://') || lower.startsWith('hysteria2://') || lower.startsWith('hy2://')) {
        // Handle ss:// which might be base64
        String toParse = uriStr;
        if (lower.startsWith('ss://') && !uriStr.contains('@')) {
          try {
            // ss://base64 (method:pass@host:port)
            final b64part = uriStr.substring(5).split('#')[0].split('?')[0];
            final decoded = utf8.decode(base64Decode(b64part));
            if (decoded.contains('@')) {
              // Extract host:port from decoded
              final atIdx = decoded.lastIndexOf('@');
              final hp = decoded.substring(atIdx+1);
              if (hp.contains(':')) {
                host = hp.split(':')[0];
                port = int.tryParse(hp.split(':')[1]) ?? 443;
              }
            }
          } catch (_) {}
        }
        if (host.isEmpty) {
          final u = _tryParse(toParse);
          if (u != null && u.host.isNotEmpty) {
            host = u.host;
            port = u.hasPort ? u.port : 443;
          }
        }
      } else if (lower.startsWith('vmess://')) {
        try {
          final b64 = uriStr.substring(8).split('#')[0];
          final raw = utf8.decode(base64Decode(b64));
          final j = jsonDecode(raw);
          host = j['add'] ?? '';
          port = int.tryParse(j['port']?.toString() ?? '') ?? 443;
        } catch (_) {}
      } else if (lower.startsWith('wg://') || lower.startsWith('wireguard://')) {
        try {
          String tmp = uriStr;
          tmp = tmp.replaceFirst(RegExp(r'^wg://', caseSensitive: false), '');
          tmp = tmp.replaceFirst(RegExp(r'^wireguard://', caseSensitive: false), '');
          if (tmp.contains('#')) tmp = tmp.split('#')[0];
          if (tmp.contains('?')) tmp = tmp.split('?')[0];
          if (tmp.contains('@')) {
            final afterAt = tmp.split('@').sublist(1).join('@');
            if (afterAt.contains(':')) {
              host = afterAt.split(':')[0];
              port = int.tryParse(afterAt.split(':')[1]) ?? 51820;
            } else {
              host = afterAt;
              port = 51820;
            }
          } else if (tmp.contains(':')) {
            host = tmp.split(':')[0];
            port = int.tryParse(tmp.split(':')[1]) ?? 51820;
          }
        } catch (_) {}
      } else if (uriStr.trim().startsWith('{')) {
        // Raw JSON - try to extract address from first outbound
        try {
          final cfg = jsonDecode(uriStr) as Map<String, dynamic>;
          final out = (cfg['outbounds'] as List?)?.first as Map<String, dynamic>?;
          final settings = out?['settings'] as Map<String, dynamic>?;
          final vnext = (settings?['vnext'] as List?)?.first;
          if (vnext is Map) {
            host = vnext['address'] ?? '';
            port = vnext['port'] ?? 443;
          } else {
            final servers = (settings?['servers'] as List?)?.first;
            if (servers is Map) {
              host = servers['address'] ?? '';
              port = servers['port'] ?? 443;
            }
          }
        } catch (_) {}
      }

      if (host.isEmpty) return null;

      final sw = Stopwatch()..start();
      final socket = await Socket.connect(host, port, timeout: const Duration(seconds: 4));
      sw.stop();
      socket.destroy();
      return sw.elapsedMilliseconds;
    } catch (_) {
      return -1;
    }
  }

  static Future<void> pingAllServers(
    List<ServerModel> servers, {
    Future<int?> Function(String uri)? pingFn,
    void Function(int current, int total)? onProgress,
  }) async {
    int finished = 0;
    const batchSize = 3;
    for (int i = 0; i < servers.length; i += batchSize) {
      final batch = servers.sublist(i, math.min(i + batchSize, servers.length));
      await Future.wait(batch.map((s) async {
        if (s.configUri.isNotEmpty) {
          int? ms;
          if (pingFn != null) {
            try {
              ms = await pingFn(s.configUri);
            } catch (_) {}
          }
          if (ms == null || ms <= 0) {
            ms = await pingServerUri(s.configUri);
          }
          s.pingMs = ms;
        } else {
          s.pingMs = -1;
        }
        finished++;
        onProgress?.call(finished, servers.length);
      }));
    }
  }

  static Future<ClientModel?> getProfile() async {
    final orderedUrls = getOrderedBaseUrls();
    for (int i = 0; i < orderedUrls.length; i++) {
      final currentBase = orderedUrls[i];
      try {
        final prefs = await SharedPreferences.getInstance();
        final token = prefs.getString('auth_token') ?? '';

        final url = Uri.parse("$currentBase/api/v1/app/profile?auth_token=${Uri.encodeComponent(token)}");
        final response = await http.get(
          url,
          headers: {
            'Authorization': 'Bearer $token',
            'X-Auth-Token': token,
            'Accept': 'application/json'
          },
        ).timeout(const Duration(seconds: 8));

        final data = jsonDecode(utf8.decode(response.bodyBytes));
        if (data['success'] == true && data['data'] != null) {
          await _saveWorkingUrl(currentBase);
          await prefs.setString('cached_client', jsonEncode(data['data']));
          if (data['data']['sub_url'] != null) {
            await prefs.setString('sub_url', data['data']['sub_url'].toString());
          }
          return ClientModel.fromJson(data['data']);
        }
      } catch (_) {
        if (i == orderedUrls.length - 1) return null;
      }
    }
    return null;
  }

  static Future<List<Map<String, dynamic>>> getAnnouncements() async {
    final orderedUrls = getOrderedBaseUrls();
    for (final currentBase in orderedUrls) {
      try {
        final prefs = await SharedPreferences.getInstance();
        final token = prefs.getString('auth_token') ?? '';

        final url = Uri.parse("$currentBase/api/v1/app/announcements?auth_token=${Uri.encodeComponent(token)}");
        final response = await http.get(
          url,
          headers: {
            'Authorization': 'Bearer $token',
            'X-Auth-Token': token,
            'Accept': 'application/json'
          },
        ).timeout(const Duration(seconds: 8));

        final data = jsonDecode(utf8.decode(response.bodyBytes));
        if (data['success'] == true && data['data']['announcements'] != null) {
          await _saveWorkingUrl(currentBase);
          final List list = data['data']['announcements'];
          return list.map((e) => Map<String, dynamic>.from(e)).toList();
        }
      } catch (_) {}
    }
    return [];
  }

  static Future<Map<String, dynamic>?> checkAppUpdate() async {
    // 1. Primary: Try all baseUrls for check-update
    final orderedUrls = getOrderedBaseUrls();
    for (final currentBase in orderedUrls) {
      try {
        final prefs = await SharedPreferences.getInstance();
        final token = prefs.getString('auth_token') ?? '';

        final url = Uri.parse("$currentBase/api/v1/app/check-update?auth_token=${Uri.encodeComponent(token)}&platform=${Platform.isWindows ? 'windows' : 'android'}");
        final response = await http.get(
          url,
          headers: {
            'Authorization': 'Bearer $token',
            'X-Auth-Token': token,
            'Accept': 'application/json'
          },
        ).timeout(const Duration(seconds: 6));

        final data = jsonDecode(utf8.decode(response.bodyBytes));
        if (data['success'] == true && data['data'] != null) {
          await _saveWorkingUrl(currentBase);
          final map = Map<String, dynamic>.from(data['data']);
          final latestVer = (map['latest_version'] ?? '').toString();
          final serverUrl = (map['download_url'] ?? '').toString();
          final dynamicGhArm64 = "https://github.com/hojjatrad/panelconnectix/releases/download/v$latestVer/Connectix-Android-ARM64.apk";
          final dynamicGhUniversal = "https://github.com/hojjatrad/panelconnectix/releases/download/v$latestVer/Connectix-Android-Universal.apk";

          map['download_url'] = (serverUrl.isNotEmpty && serverUrl.startsWith('http'))
              ? serverUrl
              : dynamicGhArm64;
          map['fallback_url'] = (map['universal_url'] != null && map['universal_url'].toString().startsWith('http'))
              ? map['universal_url'].toString()
              : dynamicGhUniversal;
          return map;
        }
      } catch (_) {}
    }

    // 2. Direct Fallback: GitHub (always accessible from Iran)
    try {
      final ghResp = await http.get(
        Uri.parse("https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/app_release.json"),
        headers: {'Accept': 'application/json'},
      ).timeout(const Duration(seconds: 5));
      if (ghResp.statusCode == 200) {
        final ghData = jsonDecode(utf8.decode(ghResp.bodyBytes));
        final ver = (ghData['version'] ?? '').toString();
        if (ver.isNotEmpty) {
          final apkArm64 = ghData['apk']?['arm64']?.toString() ??
              "https://github.com/hojjatrad/panelconnectix/releases/download/v$ver/Connectix-Android-ARM64.apk";
          final apkUniversal = ghData['apk']?['universal']?.toString() ??
              "https://github.com/hojjatrad/panelconnectix/releases/download/v$ver/Connectix-Android-Universal.apk";
          return {
            'has_update': true,
            'latest_version': ver,
            'title': 'Connectix v$ver',
            'changelog': (ghData['changelog'] ?? '• نگارش جدید سامانه منتشر شد.').toString(),
            'download_url': apkArm64,
            'fallback_url': apkUniversal,
          };
        }
      }
    } catch (_) {}

    return null;
  }

  static Future<void> downloadAndInstallApk({
    required String downloadUrl,
    required Function(double progress, int receivedBytes, int totalBytes) onProgress,
    required Function(String error) onError,
    required Function() onSuccess,
  }) async {
    Future<bool> attemptDownload(String url, {bool isFallback = false}) async {
      String? cacheDirPath;
      try {
        cacheDirPath = await _updaterChannel.invokeMethod<String>('getCacheDir');
      } catch (_) {}
      if (cacheDirPath == null || cacheDirPath.isEmpty) {
        cacheDirPath = "/data/user/0/com.connectix.vpn/cache";
      }
      final dir = Directory(cacheDirPath);
      if (!await dir.exists()) {
        await dir.create(recursive: true);
      }
      final file = File('$cacheDirPath/Connectix-Update.apk');
      if (await file.exists()) {
        try { await file.delete(); } catch (_) {}
      }

      final httpClient = HttpClient();
      httpClient.connectionTimeout = const Duration(seconds: 20);
      httpClient.idleTimeout = const Duration(seconds: 20);
      httpClient.autoUncompress = false;
      try {
        final uri = Uri.parse(url);
        final request = await httpClient.getUrl(uri);
        request.headers.set(HttpHeaders.userAgentHeader, 'Mozilla/5.0 (Linux; Android 10; Mobile) Connectix');
        request.headers.set(HttpHeaders.acceptHeader, '*/*');
        request.followRedirects = true;
        request.maxRedirects = 5;
        final response = await request.close().timeout(const Duration(minutes: 6));

        if (response.statusCode >= 400) {
          log('download HTTP ${response.statusCode} for $url');
          throw Exception('کد خطا: ${response.statusCode}');
        }

        final total = response.contentLength > 0 ? response.contentLength : 0;
        log('download start: $url total=$total status=${response.statusCode}');

        final sink = file.openWrite();
        int received = 0;
        await for (final chunk in response) {
          sink.add(chunk);
          received += chunk.length;
          if (total > 0) {
            onProgress((received / total).clamp(0.0, 1.0), received, total);
          } else {
            final fakeProgress = (received / (30 * 1024 * 1024)).clamp(0.0, 0.95);
            onProgress(fakeProgress, received, 0);
          }
        }
        await sink.flush();
        await sink.close();

        final len = await file.length();
        log('download finished: len=$len total=$total');

        if (!await file.exists()) {
          throw Exception('فایل ایجاد نشد');
        }
        if (len < 1000000) {
          throw Exception('فایل ناقص است (حجم ${len} بایت)');
        }
        try {
          final raf = await file.open();
          final header = await raf.read(4);
          await raf.close();
          if (header.length < 2 || header[0] != 0x50 || header[1] != 0x4B) {
            throw Exception('فایل دانلود شده APK معتبر نیست');
          }
        } catch (e) {
          if (e.toString().contains('APK معتبر نیست')) rethrow;
        }

        try {
          final installResult = await _updaterChannel.invokeMethod('installApk', {'filePath': file.path});
          log('installApk result: $installResult path=${file.path}');
        } catch (nativeErr) {
          log('Native install invoke failed: $nativeErr');
        }
        onSuccess();
        return true;
      } catch (e) {
        log('attemptDownload error for $url: $e');
        if (!isFallback) {
          return false;
        } else {
          onError('خطا در دانلود: $e');
          return false;
        }
      } finally {
        try { httpClient.close(force: true); } catch (_) {}
      }
    }

    try {
      try {
        final canInstall = await _updaterChannel.invokeMethod<bool>('canInstallPackages') ?? true;
        if (!canInstall) {
          await _updaterChannel.invokeMethod('openInstallPermissionSettings');
          onError('دسترسی «نصب برنامه‌های ناشناخته» را در صفحه تنظیمات فعال کرده و دوباره دکمه را لمس فرمایید.');
          return;
        }
      } catch (_) {}

      final primaryOk = await attemptDownload(downloadUrl, isFallback: false);
      if (primaryOk) return;

      try {
        String fallbackUrl = '';
        try {
          final updateData = await checkAppUpdate();
          fallbackUrl = (updateData?['fallback_url'] ?? '').toString();
        } catch (_) {}
        if (fallbackUrl.isEmpty || fallbackUrl == downloadUrl) {
          fallbackUrl = downloadUrl.replaceAll('ARM64', 'Universal').replaceAll('arm64-v8a', 'Universal');
        }
        if (fallbackUrl.isNotEmpty && fallbackUrl != downloadUrl) {
          log('Retrying download with fallback: $fallbackUrl');
          final fallbackOk = await attemptDownload(fallbackUrl, isFallback: true);
          if (fallbackOk) return;
        }
      } catch (e) {
        log('Fallback retry error: $e');
      }

      onError('فایل دانلود شده ناقص است. لطفا با اینترنت پایدارتر دوباره تلاش کنید یا از مرورگر دانلود کنید.');
    } catch (e) {
      log('downloadAndInstallApk top-level error: $e');
      onError('خطا در دانلود یا نصب: $e');
    }
  }

  static Future<void> openHotspotSettings() async {
    try {
      await _updaterChannel.invokeMethod('openHotspotSettings');
    } catch (_) {}
  }

  static void updateNotificationStatus({
    required String title,
    required String content,
    required bool isConnected,
  }) {
    if (!Platform.isAndroid) return;
    try {
      _updaterChannel.invokeMethod('updateNotification', {
        'title': title,
        'content': content,
        'isConnected': isConnected,
      });
    } catch (_) {}
  }

  static Future<void> logout() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('is_logged_in', false);
    await prefs.remove('is_logged_in');
    await prefs.remove('auth_token');
    await prefs.remove('saved_password');
    await prefs.remove('cached_client');
    await prefs.remove('cached_branding');
    await prefs.remove('sub_url');
  }

  static Future<bool> sendFeedback({
    required String subject,
    required String message,
    String? version,
  }) async {
    final orderedUrls = getOrderedBaseUrls();
    for (final currentBase in orderedUrls) {
      try {
        final prefs = await SharedPreferences.getInstance();
        final token = prefs.getString('auth_token') ?? '';
        if (token.isEmpty) return false;

        final deviceInfo = Platform.operatingSystem.toUpperCase() + ' ' + Platform.version;
        final body = <String, dynamic>{
          'subject': subject,
          'message': message,
          'device_log': [
            'نسخه: ${version ?? 'unknown'}',
            'دستگاه: $deviceInfo',
            'بازه زمانی: ${DateTime.now().toIso8601String()}',
            '',
            '--- لاگ آخرین فعالیت‌ها ---',
            logDump,
          ].join('\n'),
        };

        final resp = await http.post(
          Uri.parse('$currentBase/api/v1/app/feedback'),
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': 'Bearer $token',
          },
          body: jsonEncode(body),
        ).timeout(const Duration(seconds: 20));

        final data = jsonDecode(utf8.decode(resp.bodyBytes));
        if (data['success'] == true) {
          await _saveWorkingUrl(currentBase);
          return true;
        }
      } catch (e) {
        debugPrint('sendFeedback error via $currentBase: $e');
      }
    }
    return false;
  }

  static Future<bool> isOnWifi() async {
    try {
      final conn = await _connectivity.checkConnectivity();
      return conn.toString() == 'ConnectivityResult.wifi';
    } catch (_) {
      return false;
    }
  }
}
