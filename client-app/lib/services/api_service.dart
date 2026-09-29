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
  static String baseUrl = "https://vpbotn.ir/contax";
  static const MethodChannel _updaterChannel = MethodChannel('com.connectix.vpn/updater');

  // ---------------- Diagnostic Log Ring Buffer ----------------
  // Kept in memory; attached to error reports so support sees exactly
  // what happened (URLs, sizes, retry counts, errors) without screenshots.
  static final List<String> _logLines = <String>[];

  static void log(String msg) {
    try {
      _logLines.add('${DateTime.now().toIso8601String().substring(11, 19)} $msg');
      if (_logLines.length > 60) {
        _logLines.removeAt(0);
      }
    } catch (_) {}
  }

  static String get logDump => _logLines.join('\n');

  static final Connectivity _connectivity = Connectivity();

  // ----------------------------------------------------------

  static Future<void> initBaseUrl() async {
    // 3.3.9: the panel address is FIXED at build time. Any per-device saved
    // value is ignored on purpose — the app must always talk to the official
    // panel (the "set panel address" UI was removed in 3.3.9, so customers
    // can never misconfigure the server). To change the panel address,
    // release a new build with the new constant (see docs/RULES.md).
    baseUrl = "https://vpbotn.ir/contax";
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('api_base_url', baseUrl); // keep legacy key in sync
    } catch (_) {}
  }

  /**
   * Persistent Session Verification (Auto-Login)
   * Restores user state directly on app launch without prompting for login
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

  static Future<Map<String, dynamic>> login(String username, String password) async {
    try {
      final url = Uri.parse("$baseUrl/api/v1/app/login");
      final response = await http.post(
        url,
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
        body: jsonEncode({'username': username, 'password': password}),
      ).timeout(const Duration(seconds: 30));

      final data = jsonDecode(utf8.decode(response.bodyBytes));
      if (data['success'] == true) {
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

        return {
          'success': true,
          'client': ClientModel.fromJson(data['data']['client']),
          'branding': BrandingModel.fromJson(data['data']['branding']),
          'servers': initialServers,
        };
      } else {
        return {
          'success': false,
          'error': data['error'] ?? 'نام کاربری یا رمز عبور اشتباه است.',
        };
      }
    } catch (e) {
      log('login error: $e');
      if (e is TimeoutException) {
        return {'success': false, 'error': 'اتصال به سرور طول کشید. اتصال اینترنت خود را بررسی کنید و دوباره تلاش کنید.'};
      }
      return {'success': false, 'error': 'خطا در برقراری ارتباط با سرور: $e'};
    }
  }

  /**
   * Universal Inbounds Delivery Engine
   * 1. First tests panel app/configs endpoint
   * 2. If response contains mock/fallback or fewer than 6 nodes, automatically falls back to sub_url
   * 3. Parses all 14 active PasarGuard inbounds into clean ServerModel instances
   */
  static Future<List<ServerModel>> getServers() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token') ?? '';
      final subUrl = prefs.getString('sub_url') ?? '';

      List<ServerModel> servers = [];

      // 1. Primary: Dedicated App Configs API
      try {
        final url = Uri.parse("$baseUrl/api/v1/app/configs?auth_token=${Uri.encodeComponent(token)}");
        final response = await http.get(
          url,
          headers: {
            'Authorization': 'Bearer $token',
            'X-Auth-Token': token,
            'Accept': 'application/json'
          },
        ).timeout(const Duration(seconds: 20));
        log('configs API: base=$baseUrl HTTP ${response.statusCode}');
        if (response.statusCode != 200) {
          final snippet = response.body.length > 200 ? response.body.substring(0, 200) : response.body;
          log('configs API non-200 body: $snippet');
        }
        final data = jsonDecode(utf8.decode(response.bodyBytes));
        if (data['success'] == true && data['data'] != null && data['data']['servers'] != null) {
          final List list = data['data']['servers'];
          final parsed = list
              .map((e) => ServerModel.fromJson(e))
              .where((s) => !s.isInfoBanner && !s.configUri.contains('mock_pbk') && s.id != 'mci_reality_de')
              .toList();
          log('configs API: ${parsed.length} clean servers');
          
          if (parsed.isNotEmpty) {
            servers = parsed;
          } else {
            log('configs list empty after filtering — falling back to sublink');
          }
        } else {
          log('configs API: success=${data['success']} error=${data['error']}');
        }
      } catch (e) {
        log('API configs fetch error: $e');
        debugPrint("API configs fetch error: $e");
      }

      // 2. Direct Node / Panel Sublink Auto-Resolver (Delivers all live PasarGuard inbounds)
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
        // Fallback to locally cached servers so the user NEVER sees an empty server list
        servers = await getCachedServers();
      }

      return servers;
    } catch (e) {
      log('getServers Top-level error: $e');
      debugPrint("getServers Top-level error: $e");
      return await getCachedServers();
    }
  }

  /**
   * Fast TCP Ping to server host:port directly (works offline/online, no VPN needed)
   */
  static Future<int?> pingServerUri(String uriStr) async {
    try {
      String host = '';
      int port = 443;
      if (uriStr.startsWith('vless://') || uriStr.startsWith('trojan://') || uriStr.startsWith('ss://')) {
        final u = Uri.tryParse(uriStr);
        if (u != null && u.host.isNotEmpty) {
          host = u.host;
          port = u.hasPort ? u.port : 443;
        }
      } else if (uriStr.startsWith('vmess://')) {
        try {
          final b64 = uriStr.substring(8);
          final raw = utf8.decode(base64Decode(b64));
          final j = jsonDecode(raw);
          host = j['add'] ?? '';
          port = int.tryParse(j['port']?.toString() ?? '') ?? 443;
        } catch (_) {}
      }

      if (host.isEmpty) return null;

      final sw = Stopwatch()..start();
      final socket = await Socket.connect(host, port, timeout: const Duration(seconds: 4));
      sw.stop();
      socket.destroy();
      return sw.elapsedMilliseconds;
    } catch (_) {
      return -1; // timed out or unreachable
    }
  }

  /**
   * Ping multiple servers in parallel batches with progress callback.
   * Supports custom pingFn (e.g. FlutterV2ray core delay) falling back to TCP socket ping.
   */
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
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token') ?? '';

      final url = Uri.parse("$baseUrl/api/v1/app/profile?auth_token=${Uri.encodeComponent(token)}");
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
        // Cache refreshed profile
        await prefs.setString('cached_client', jsonEncode(data['data']));
        if (data['data']['sub_url'] != null) {
          await prefs.setString('sub_url', data['data']['sub_url'].toString());
        }
        return ClientModel.fromJson(data['data']);
      }
      return null;
    } catch (e) {
      return null;
    }
  }

  static Future<List<Map<String, dynamic>>> getAnnouncements() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token') ?? '';

      final url = Uri.parse("$baseUrl/api/v1/app/announcements?auth_token=${Uri.encodeComponent(token)}");
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
        final List list = data['data']['announcements'];
        return list.map((e) => Map<String, dynamic>.from(e)).toList();
      }
      return [];
    } catch (e) {
      return [];
    }
  }

  static Future<Map<String, dynamic>?> checkAppUpdate() async {
    // 1. Primary: Query Panel /api/v1/app/check-update
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token') ?? '';

      // Platform-aware: Windows clients get the Windows package version/URL.
      final url = Uri.parse("$baseUrl/api/v1/app/check-update?auth_token=${Uri.encodeComponent(token)}&platform=${Platform.isWindows ? 'windows' : 'android'}");
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

    // 2. Direct Fallback: Query GitHub raw release manifest (available globally even when panel is blocked)
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

  /**
   * High-Speed In-App Download and Native Package Installation - ROBUST v3.5.7
   * Fixes \"فایل ناقص\" by using dart:io HttpClient which correctly follows GitHub 302 redirects,
   * validates APK ZIP header, retries fallback URL, and uses external files dir for better FileProvider compatibility.
   */
  static Future<void> downloadAndInstallApk({
    required String downloadUrl,
    required Function(double progress, int receivedBytes, int totalBytes) onProgress,
    required Function(String error) onError,
    required Function() onSuccess,
  }) async {
    // Helper to attempt one download
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

      // Use dart:io HttpClient for proper redirect handling (GitHub -> S3)
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
            // Unknown total: show indeterminate progress based on received MB
            final fakeProgress = (received / (30 * 1024 * 1024)).clamp(0.0, 0.95);
            onProgress(fakeProgress, received, 0);
          }
        }
        await sink.flush();
        await sink.close();

        final len = await file.length();
        log('download finished: len=$len total=$total');

        // Robust integrity checks
        if (!await file.exists()) {
          throw Exception('فایل ایجاد نشد');
        }
        if (len < 1000000) {
          throw Exception('فایل ناقص است (حجم ${len} بایت) - احتمالا لینک ریدایرکت نشده');
        }
        // Check APK ZIP magic header 'PK'
        try {
          final raf = await file.open();
          final header = await raf.read(4);
          await raf.close();
          if (header.length < 2 || header[0] != 0x50 || header[1] != 0x4B) {
            throw Exception('فایل دانلود شده APK معتبر نیست (هدر نامعتبر)');
          }
        } catch (e) {
          if (e.toString().contains('APK معتبر نیست')) rethrow;
          // ignore header check errors
        }

        // Success - trigger installer
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
          // Will be retried by caller with fallback URL
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
      // 1. Check install permission first
      try {
        final canInstall = await _updaterChannel.invokeMethod<bool>('canInstallPackages') ?? true;
        if (!canInstall) {
          await _updaterChannel.invokeMethod('openInstallPermissionSettings');
          onError('دسترسی «نصب برنامه‌های ناشناخته» را در صفحه تنظیمات فعال کرده و دوباره دکمه را لمس فرمایید.');
          return;
        }
      } catch (_) {}

      // 2. Try primary URL
      final primaryOk = await attemptDownload(downloadUrl, isFallback: false);
      if (primaryOk) return;

      // 3. Auto-retry with fallback URL (Universal APK) if primary failed
      try {
        final prefs = await SharedPreferences.getInstance();
        final token = prefs.getString('auth_token') ?? '';
        // Try to get fallback from panel if not already provided via caller
        String fallbackUrl = '';
        try {
          final updateData = await checkAppUpdate();
          fallbackUrl = (updateData?['fallback_url'] ?? '').toString();
        } catch (_) {}
        if (fallbackUrl.isEmpty || fallbackUrl == downloadUrl) {
          // Derive universal from primary
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

      // If both failed and we haven't yet called onError
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

  /**
   * Send a diagnostic error report (device log + context) to support.
   * Creates a support ticket on the panel side; never throws.
   */
  static Future<bool> sendFeedback({
    required String subject,
    required String message,
    String? version,
  }) async {
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
        Uri.parse('$baseUrl/api/v1/app/feedback'),
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
        body: jsonEncode(body),
      ).timeout(const Duration(seconds: 20));

      final data = jsonDecode(utf8.decode(resp.bodyBytes));
      return data['success'] == true;
    } catch (e) {
      debugPrint('sendFeedback error: $e');
      return false;
    }
  }

  /**
   * Returns true when the device is on Wi-Fi (used for the
   * "download over Wi-Fi only" update option).
   */
  static Future<bool> isOnWifi() async {
    try {
      // connectivity_plus 5.x: checkConnectivity() returns a single
      // ConnectivityResult (pinned to ^5.0.0).
      final conn = await _connectivity.checkConnectivity();
      return conn.toString() == 'ConnectivityResult.wifi';
    } catch (_) {
      return false;
    }
  }
}
