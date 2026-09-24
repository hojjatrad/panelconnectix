import 'dart:convert';
import 'dart:io';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../models/client_model.dart';
import '../models/server_model.dart';

class ApiService {
  // Permanently preset and concealed Connectix Panel URL
  static String baseUrl = "https://vpbotn.ir/contax";

  static const MethodChannel _updaterChannel = MethodChannel('com.connectix.vpn/updater');

  static Future<void> initBaseUrl() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getString('api_base_url');
    if (saved != null && saved.isNotEmpty && !saved.contains('your-domain.com')) {
      baseUrl = saved;
    } else {
      baseUrl = "https://vpbotn.ir/contax";
      await prefs.setString('api_base_url', baseUrl);
    }
  }

  static Future<Map<String, dynamic>> login(String username, String password) async {
    try {
      final url = Uri.parse("$baseUrl/api/v1/app/login");
      final response = await http.post(
        url,
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
        body: jsonEncode({
          'username': username,
          'password': password,
        }),
      );

      final data = jsonDecode(utf8.decode(response.bodyBytes));
      if (data['success'] == true) {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('auth_token', data['data']['auth_token'] ?? '');
        await prefs.setString('saved_username', username);
        await prefs.setString('saved_password', password);

        if (data['data']['client'] != null && data['data']['client']['sub_url'] != null) {
          await prefs.setString('sub_url', data['data']['client']['sub_url'].toString());
        }

        List<ServerModel> initialServers = [];
        if (data['data']['servers'] != null && data['data']['servers'] is List) {
          final List sList = data['data']['servers'];
          final parsed = sList.map((e) => ServerModel.fromJson(e)).toList();
          final bool hasMock = parsed.any((s) => s.configUri.contains('mock_pbk') || s.id == 'mci_reality_de');
          if (!hasMock && parsed.isNotEmpty) {
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
          'error': data['error'] ?? 'خطا در ورود به حساب کاربری',
        };
      }
    } catch (e) {
      return {'success': false, 'error': 'خطا در برقراری ارتباط با سرور: $e'};
    }
  }

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
        ).timeout(const Duration(seconds: 7));

        final data = jsonDecode(utf8.decode(response.bodyBytes));
        if (data['success'] == true && data['data']['servers'] != null) {
          final List list = data['data']['servers'];
          final parsed = list.map((e) => ServerModel.fromJson(e)).toList();
          final bool hasMock = parsed.any((s) => s.configUri.contains('mock_pbk') || s.id == 'mci_reality_de');
          if (!hasMock && parsed.isNotEmpty) {
            servers = parsed;
          }
        }
      } catch (_) {}

      // 2. Direct Node / Panel Sublink Auto-Resolver (Delivers all 14 live PasarGuard inbounds)
      if (servers.isEmpty && subUrl.isNotEmpty) {
        try {
          final subResp = await http.get(
            Uri.parse(subUrl),
            headers: {'User-Agent': 'v2rayNG/1.8.5'},
          ).timeout(const Duration(seconds: 8));

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
          }
        } catch (_) {}
      }

      return servers;
    } catch (e) {
      return [];
    }
  }

  static Future<ClientModel?> getProfile() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token') ?? '';

      // Dual authorization delivery: query parameter + header
      final url = Uri.parse("$baseUrl/api/v1/app/profile?auth_token=${Uri.encodeComponent(token)}");
      final response = await http.get(
        url,
        headers: {
          'Authorization': 'Bearer $token',
          'X-Auth-Token': token,
          'Accept': 'application/json'
        },
      );

      final data = jsonDecode(utf8.decode(response.bodyBytes));
      if (data['success'] == true && data['data'] != null) {
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
      );

      final data = jsonDecode(utf8.decode(response.bodyBytes));
      if (data['success'] == true && data['data'] != null && data['data']['announcements'] != null) {
        final List list = data['data']['announcements'];
        return list.cast<Map<String, dynamic>>();
      }
      return [];
    } catch (e) {
      return [];
    }
  }

  /**
   * Dual-Engine In-App Updater: checks Panel update API with GitHub fallback
   */
  static Future<Map<String, dynamic>?> checkAppUpdate() async {
    // 1. Primary: Query Panel /api/v1/app/check-update
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token') ?? '';

      final url = Uri.parse("$baseUrl/api/v1/app/check-update?auth_token=${Uri.encodeComponent(token)}");
      final response = await http.get(
        url,
        headers: {
          'Authorization': 'Bearer $token',
          'X-Auth-Token': token,
          'Accept': 'application/json'
        },
      );

      final data = jsonDecode(utf8.decode(response.bodyBytes));
      if (data['success'] == true && data['data'] != null) {
        return data['data'];
      }
    } catch (_) {}

    // 2. Fallback: Check GitHub API directly
    try {
      final ghUri = Uri.parse("https://api.github.com/repos/hojjatrad/panelconnectix/releases/latest");
      final ghRes = await http.get(ghUri, headers: {'Accept': 'application/vnd.github.v3+json'});
      if (ghRes.statusCode == 200) {
        final ghData = jsonDecode(utf8.decode(ghRes.bodyBytes));
        final tag = (ghData['tag_name'] ?? '').toString().replaceAll('v', '').replaceAll('V', '');
        
        String arm64Url = '';
        String universalUrl = '';
        final assets = ghData['assets'] as List? ?? [];
        for (var a in assets) {
          final name = (a['name'] ?? '').toString();
          if (name.contains('ARM64')) arm64Url = a['browser_download_url'] ?? '';
          if (name.contains('Universal')) universalUrl = a['browser_download_url'] ?? '';
        }
        if (arm64Url.isEmpty && assets.isNotEmpty) {
          arm64Url = assets.first['browser_download_url'] ?? '';
        }

        return {
          'has_update': false, // controlled by semantic version check in UI
          'latest_version': tag.isNotEmpty ? tag : '3.0.0',
          'title': ghData['name'] ?? 'نگارش جدید Connectix',
          'changelog': ghData['body'] ?? 'بهینه‌سازی کانکشن‌ها، بروزرسانی خودکار درون‌برنامه‌ای، اتصال هوشمند و تونل اختصاصی برنامه‌های بانکی',
          'download_url': arm64Url.isNotEmpty ? arm64Url : "https://github.com/hojjatrad/panelconnectix/releases/download/v3.0.0/Connectix-ARM64-v8a.apk",
          'universal_url': universalUrl
        };
      }
    } catch (_) {}

    return null;
  }

  /**
   * High-Speed In-App Download and Native Package Installation
   * Downloads APK directly into cache with progress callback, then summons Android PackageInstaller
   */
  static Future<void> downloadAndInstallApk({
    required String downloadUrl,
    required Function(double progress, int receivedBytes, int totalBytes) onProgress,
    required Function(String error) onError,
    required Function() onSuccess,
  }) async {
    try {
      // 1. Check Android 8.0+ Unknown Sources Permission
      bool canInstall = true;
      try {
        final res = await _updaterChannel.invokeMethod<bool>('canInstallPackages');
        canInstall = res ?? true;
      } catch (_) {}

      if (!canInstall) {
        await _updaterChannel.invokeMethod('openInstallPermissionSettings');
        onError('لطفاً دسترسی نصب برنامه را در صفحه تنظیمات باز شده فعال فرمایید و مجدداً تلاش کنید.');
        return;
      }

      // 2. Query Cache Directory
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

      // 3. Stream download with real-time byte tracking
      final client = http.Client();
      final request = http.Request('GET', Uri.parse(downloadUrl));
      final response = await client.send(request);

      if (response.statusCode >= 400) {
        onError('خطا در دریافت بسته نرم‌افزاری (کد خطا: ${response.statusCode})');
        return;
      }

      final totalBytes = response.contentLength ?? 0;
      int receivedBytes = 0;
      final sink = file.openWrite();

      await response.stream.listen(
        (chunk) {
          receivedBytes += chunk.length;
          sink.add(chunk);
          if (totalBytes > 0) {
            onProgress(receivedBytes / totalBytes, receivedBytes, totalBytes);
          } else {
            onProgress(0.0, receivedBytes, 0);
          }
        },
        cancelOnError: true,
      ).asFuture();

      await sink.flush();
      await sink.close();

      // 4. Trigger Native Android Package Installer Dialog
      final installResult = await _updaterChannel.invokeMethod('installApk', {
        'filePath': file.path,
      });

      if (installResult == true) {
        onSuccess();
      } else {
        onError('نصاب اندروید قادر به باز کردن بسته نبود.');
      }
    } catch (e) {
      onError('خطا در دانلود یا نصب بسته: $e');
    }
  }

  static Future<void> openHotspotSettings() async {
    try {
      await _updaterChannel.invokeMethod('openHotspotSettings');
    } catch (_) {}
  }

  static Future<void> logout() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('auth_token');
    await prefs.remove('saved_password');
  }
}

