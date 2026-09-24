import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../models/client_model.dart';
import '../models/server_model.dart';

class ApiService {
  // Permanently preset and concealed Connectix Panel URL
  static String baseUrl = "https://vpbotn.ir/contax";

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

        List<ServerModel> initialServers = [];
        if (data['data']['servers'] != null && data['data']['servers'] is List) {
          final List sList = data['data']['servers'];
          initialServers = sList.map((e) => ServerModel.fromJson(e)).toList();
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

      // Dual authorization delivery: query parameter + header to bypass cPanel FastCGI stripping
      final url = Uri.parse("$baseUrl/api/v1/app/configs?auth_token=${Uri.encodeComponent(token)}");
      final response = await http.get(
        url,
        headers: {
          'Authorization': 'Bearer $token',
          'X-Auth-Token': token,
          'Accept': 'application/json'
        },
      );

      final data = jsonDecode(utf8.decode(response.bodyBytes));
      if (data['success'] == true && data['data']['servers'] != null) {
        final List list = data['data']['servers'];
        return list.map((e) => ServerModel.fromJson(e)).toList();
      }
      return [];
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
          'latest_version': tag.isNotEmpty ? tag : '1.0.4',
          'title': ghData['name'] ?? 'نگارش جدید Connectix',
          'changelog': ghData['body'] ?? 'بهینه‌سازی کانکشن‌ها و امکان بروزرسانی خودکار درون‌برنامه‌ای',
          'download_url': arm64Url.isNotEmpty ? arm64Url : "https://github.com/hojjatrad/panelconnectix/releases/download/v3.0.0/Connectix-ARM64-v8a.apk",
          'universal_url': universalUrl
        };
      }
    } catch (_) {}

    return null;
  }

  static Future<void> logout() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('auth_token');
    await prefs.remove('saved_password');
  }
}
