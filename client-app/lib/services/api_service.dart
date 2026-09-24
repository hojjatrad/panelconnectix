import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../models/client_model.dart';
import '../models/server_model.dart';

class ApiService {
  // Base URL to Connectix Panel
  static String baseUrl = "https://your-domain.com";

  static Future<void> initBaseUrl() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getString('api_base_url');
    if (saved != null && saved.isNotEmpty) {
      baseUrl = saved;
    }
  }

  static Future<Map<String, dynamic>> login(String username, String password) async {
    try {
      final url = Uri.parse("$baseUrl/api/v1/app/login");
      final response = await http.post(
        url,
        headers: {'Content-Type': 'application/json'},
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
        return {
          'success': true,
          'client': ClientModel.fromJson(data['data']['client']),
          'branding': BrandingModel.fromJson(data['data']['branding']),
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

  static Future<Map<String, dynamic>?> checkAppUpdate() async {
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
      return null;
    } catch (e) {
      return null;
    }
  }

  static Future<void> logout() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('auth_token');
    await prefs.remove('saved_password');
  }
}
