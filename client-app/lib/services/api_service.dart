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
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getString('api_base_url');
    if (saved != null && saved.isNotEmpty) {
      baseUrl = saved.replaceAll(RegExp(r'/+$'), '');
    } else {
      baseUrl = "https://vpbotn.ir/contax";
      await prefs.setString('api_base_url', baseUrl);
    }
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

        return {
          'client': ClientModel.fromJson(clientMap),
          'branding': BrandingModel.fromJson(brandingMap),
        };
      }
      return null;
    } catch (e) {
      debugPrint("checkSavedSession Error: $e");
      return null;
    }
  }

  static Future<Map<String, dynamic>> login(String username, String password) async {
    try {
      final url = Uri.parse("$baseUrl/api/v1/app/login");
      final response = await http.post(
        url,
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
        body: jsonEncode({'username': username, 'password': password}),
      ).timeout(const Duration(seconds: 12));

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
          final parsed = sList.map((e) => ServerModel.fromJson(e)).toList();
          final bool hasMock = parsed.any((s) => s.configUri.contains('mock_pbk') || s.id == 'mci_reality_de');
          if (!hasMock && parsed.length > 5) {
            initialServers = parsed;
          }
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
          final parsed = list.map((e) => ServerModel.fromJson(e)).toList();
          final bool hasMock = parsed.any((s) => s.configUri.contains('mock_pbk') || s.id == 'mci_reality_de');
          log('configs API: ${parsed.length} servers, hasMock=$hasMock');
          
          // Accept only if real inbounds (more than 5 servers without mock items)
          if (!hasMock && parsed.length > 5) {
            servers = parsed;
          } else {
            log('configs list rejected (hasMock=$hasMock count=${parsed.length}) — falling back to sublink');
          }
        } else {
          log('configs API: success=${data['success']} error=${data['error']}');
        }
      } catch (e) {
        log('API configs fetch error: $e');
        debugPrint("API configs fetch error: $e");
      }

      // 2. Direct Node / Panel Sublink Auto-Resolver (Delivers all 14 live PasarGuard inbounds)
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
                servers.add(ServerModel.fromUri(line, idx++));
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

      return servers;
    } catch (e) {
      log('getServers Top-level error: $e');
      debugPrint("getServers Top-level error: $e");
      return [];
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
    final directApkUrl = "$baseUrl/Connectix-ARM64-v8a.apk";
    final ghApkUrl = "https://github.com/hojjatrad/panelconnectix/releases/download/v3.0.0/Connectix-ARM64-v8a.apk";

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
        // Respect the admin-configured APK URL from the panel settings;
        // otherwise use the fast panel-hosted direct APK.
        final serverUrl = (map['download_url'] ?? '').toString();
        map['download_url'] = (serverUrl.isNotEmpty && serverUrl.startsWith('http'))
            ? serverUrl
            : directApkUrl;
        map['fallback_url'] = ghApkUrl;
        return map;
      }
    } catch (_) {}

    // 2. Fallback: Default Release v3.1.0 metadata
    return {
      'has_update': true,
      'latest_version': '3.1.0',
      'title': 'Connectix v3.1.0 (نگارش پایدار)',
      'changelog': "• ماندگاری دائمی ورود به حساب و عدم بازگشت به صفحه لاگین\n• رفع کامل کانکشن‌های پیش‌فرض و نمایش هر ۱۴ سرور فعال پاسارگاد\n• دانلود مستقیم و فوق‌سریع درون‌برنامه‌ای بدون نیاز به مرورگر\n• اعطای خودکار دسترسی‌های نصاب اندروید بدون خطا",
      'download_url': directApkUrl,
      'fallback_url': ghApkUrl
    };
  }

  /**
   * High-Speed In-App Download and Native Package Installation
   * Downloads APK directly into cache with progress callback, then triggers Android PackageInstaller
   */
  /// Robust APK downloader:
  /// - Streams the file in 1 MB Range chunks (each request is short, so
  ///   network/edge timeouts cannot kill a long transfer)
  /// - On any interruption, retries the SAME chunk (server-side resume via
  ///   HTTP Range — no data lost, no restart from zero)
  /// - Falls back to a plain single-stream GET when the server does not
  ///   support Range (206) responses.
  static Future<void> downloadAndInstallApk({
    required String downloadUrl,
    required Function(double progress, int receivedBytes, int totalBytes) onProgress,
    required Function(String error) onError,
    required Function() onSuccess,
  }) async {
    try {
      // 1. Query Cache Directory
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
        try {
          await file.delete();
        } catch (_) {}
      }

      var client = http.Client();
      const String ua = 'Mozilla/5.0 (Linux; Android 10; Mobile)';

      // 2. Discover total size (HEAD, then Range: bytes=0-0 fallback)
      int totalBytes = 0;
      bool rangeSupported = false;
      try {
        final headReq = http.Request('HEAD', Uri.parse(downloadUrl));
        headReq.headers['User-Agent'] = ua;
        final headResp = await client.send(headReq).timeout(const Duration(seconds: 15));
        totalBytes = int.tryParse(headResp.headers['content-length'] ?? '') ?? 0;
        rangeSupported = (headResp.headers['accept-ranges'] ?? '').toLowerCase().contains('bytes');
        await headResp.stream.drain<void>();
      } catch (_) {}
      if (totalBytes <= 0 || !rangeSupported) {
        try {
          final req0 = http.Request('GET', Uri.parse(downloadUrl))..headers['User-Agent'] = ua;
          if (rangeSupported) req0.headers['Range'] = 'bytes=0-0';
          final r0 = await client.send(req0).timeout(const Duration(seconds: 15));
          if (r0.statusCode == 206) {
            rangeSupported = true;
            final m = RegExp(r'/(\d+)\s*$').firstMatch(r0.headers['content-range'] ?? '');
            if (m != null) totalBytes = int.tryParse(m.group(1)!) ?? 0;
          } else if (r0.statusCode == 200) {
            totalBytes = int.tryParse(r0.headers['content-length'] ?? '') ?? 0;
            rangeSupported = false;
          }
          await r0.stream.drain<void>();
        } catch (_) {}
      }

      const int chunkSize = 1024 * 1024; // 1 MB per HTTP request
      int offset = 0;
      final sink = file.openWrite();

      if (rangeSupported && totalBytes > 0) {
        // 3a. Chunked Range download with resume + retry
        while (offset < totalBytes) {
          final end = math.min(offset + chunkSize - 1, totalBytes - 1);
          bool got = false;
          for (int attempt = 0; attempt < 6 && !got; attempt++) {
            try {
              final req = http.Request('GET', Uri.parse(downloadUrl))
                ..headers['User-Agent'] = ua
                ..headers['Range'] = 'bytes=$offset-$end';
              final resp = await client.send(req).timeout(const Duration(seconds: 45));
              if (resp.statusCode != 206) {
                await resp.stream.drain<void>();
                ApiService.log('chunk ${offset}-$end HTTP ${resp.statusCode} (unexpected)');
                throw Exception('HTTP ${resp.statusCode}');
              }
              await resp.stream.listen((c) {
                sink.add(c);
                offset += c.length;
                onProgress(offset / totalBytes, offset, totalBytes);
              }).asFuture<void>();
              ApiService.log('chunk ..$end ok (now $offset/$totalBytes)');
              got = true;
            } catch (err) {
              // Interrupted — recycle the client (a timed-out stream may leave
              // the pooled socket broken), back off, resume from same offset
              ApiService.log('chunk retry $attempt from offset $offset: $err');
              try { client.close(); } catch (_) {}
              client = http.Client();
              await Future<void>.delayed(Duration(milliseconds: 1500 * (attempt + 1)));
            }
          }
          if (!got) {
            await sink.close();
            onError('اتصال مکرراً قطع شد؛ لطفاً دوباره تلاش کنید.');
            return;
          }
        }
      } else {
        // 3b. Plain single-stream fallback (server without Range support)
        final req = http.Request('GET', Uri.parse(downloadUrl));
        req.headers['User-Agent'] = ua;
        final resp = await client.send(req).timeout(const Duration(minutes: 10));
        if (resp.statusCode >= 400) {
          await sink.close();
          onError('خطا در دریافت بسته (کد خطا: ${resp.statusCode})');
          return;
        }
        await resp.stream.listen((chunk) {
          sink.add(chunk);
          offset += chunk.length;
          onProgress(totalBytes > 0 ? offset / totalBytes : 0, offset, totalBytes);
        }, cancelOnError: true).asFuture<void>();
      }

      await sink.flush();
      await sink.close();

      // Verify file integrity
      final len = await file.length();
      if (!await file.exists() || len < 1000000 ||
          (totalBytes > 0 && len != totalBytes)) {
        onError('فایل دانلود شده ناقص است.');
        return;
      }

      // 3. Trigger Native Android Package Installer Dialog
      try {
        final installResult = await _updaterChannel.invokeMethod('installApk', {
          'filePath': file.path,
        });

        if (installResult == true) {
          onSuccess();
          return;
        }
      } catch (nativeErr) {
        debugPrint("Native install failed: $nativeErr");
      }

      // 4. If native installer permission was not granted, request permission
      try {
        final canInstall = await _updaterChannel.invokeMethod<bool>('canInstallPackages') ?? true;
        if (!canInstall) {
          await _updaterChannel.invokeMethod('openInstallPermissionSettings');
          onError('دسترسی نصب در تنظیمات فعال نیست. لطفاً دسترسی را فعال فرمایید.');
          return;
        }
      } catch (_) {}

      onSuccess();
    } catch (e) {
      onError('خطا در دانلود یا نصب: $e');
    }
  }

  static Future<void> openHotspotSettings() async {
    try {
      await _updaterChannel.invokeMethod('openHotspotSettings');
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
