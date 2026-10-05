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
  // v8.0 PRO MAX: Intelligent Panel Location Resolver
  // Layer 1: Saved working URL (fastest)
  // Layer 2: Well-Known discovery (/.well-known/connectix.json)
  // Layer 3: Panel-Location API (/api/v1/app/panel-location)
  // Layer 4: Old path redirector (/contax -> new)
  // Layer 5: Brute-force common paths
  // Layer 6: Remote config from GitHub
  
  // Default fallback list (if nothing else works)
  static List<String> baseUrls = [
    "https://ir.vpbotn.ir/contax",
    "https://cf.vpbotn.ir/contax",
    "https://vpbotn.ir/contax",
    "https://api.vpbotn.ir/contax",
    "https://ir.vpbotn.ir",
    "https://vpbotn.ir",
    "https://cf.vpbotn.ir",
  ];
  
  static String baseUrl = "https://ir.vpbotn.ir/contax";
  
  // Common panel paths to brute-force
  static const List<String> commonPanelPaths = [
    "/contax",
    "",
    "/panel",
    "/admin",
    "/app",
    "/connectix",
    "/myadmin",
    "/cpanel",
    "/manage",
  ];
  
  // Known domains to try
  static const List<String> knownDomains = [
    "vpbotn.ir",
    "ir.vpbotn.ir",
    "cf.vpbotn.ir",
    "api.vpbotn.ir",
    "direct.vpbotn.ir",
  ];
  
  static const MethodChannel _updaterChannel = MethodChannel('com.connectix.vpn/updater');

  // ---------------- Diagnostic Log Ring Buffer ----------------
  static final List<String> _logLines = <String>[];

  static void log(String msg) {
    try {
      _logLines.add('${DateTime.now().toIso8601String().substring(11, 19)} $msg');
      if (_logLines.length > 100) {
        _logLines.removeAt(0);
      }
    } catch (_) {}
  }

  static String get logDump => _logLines.join('\n');

  static final Connectivity _connectivity = Connectivity();

  // ----------------------------------------------------------
  // v8.0: Smart init with intelligent resolver
  static Future<void> initBaseUrl() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final savedWorking = prefs.getString('api_base_url_working') ?? '';
      final legacy = prefs.getString('api_base_url') ?? '';
      final savedDomain = prefs.getString('panel_domain') ?? '';
      
      log('v8.0 initBaseUrl start: savedWorking=$savedWorking savedDomain=$savedDomain');
      
      // 1. Try saved working URL first (fastest path)
      if (savedWorking.isNotEmpty) {
        if (await _testUrlWorks(savedWorking)) {
          baseUrl = savedWorking;
          log('initBaseUrl: saved working URL works: $savedWorking');
          // Reorder list to prioritize it
          baseUrls = [savedWorking, ...baseUrls.where((u) => u != savedWorking)];
          await prefs.setString('api_base_url', baseUrl);
          await prefs.setString('api_base_url_working', baseUrl);
          return;
        } else {
          log('initBaseUrl: saved working URL FAILED, trying resolver...');
        }
      } else if (legacy.isNotEmpty) {
        if (await _testUrlWorks(legacy)) {
          baseUrl = legacy;
          log('initBaseUrl: legacy URL works: $legacy');
          baseUrls = [legacy, ...baseUrls.where((u) => u != legacy)];
          await prefs.setString('api_base_url_working', baseUrl);
          return;
        }
      }
      
      // 2. Try intelligent resolver (well-known + panel-location + brute-force)
      final resolved = await resolvePanelLocation();
      if (resolved != null && resolved.isNotEmpty) {
        baseUrl = resolved;
        baseUrls = [resolved, ...baseUrls.where((u) => u != resolved)];
        await prefs.setString('api_base_url', baseUrl);
        await prefs.setString('api_base_url_working', baseUrl);
        log('initBaseUrl: resolver found working URL: $resolved');
        return;
      }
      
      // 3. Fallback to first default
      baseUrl = baseUrls[0];
      log('initBaseUrl: fallback to default: $baseUrl');
      await prefs.setString('api_base_url', baseUrl);
      await prefs.setString('api_base_url_working', baseUrl);
    } catch (e) {
      log('initBaseUrl error: $e');
      baseUrl = baseUrls[0];
    }
  }

  // v8.0: Test if a URL works (quick ping)
  static Future<bool> _testUrlWorks(String url) async {
    try {
      final testUrl = Uri.parse("$url/api/v1/app/panel-location");
      final resp = await http.get(testUrl, headers: {'Accept': 'application/json'}).timeout(const Duration(seconds: 4));
      if (resp.statusCode == 200) {
        final data = jsonDecode(utf8.decode(resp.bodyBytes));
        if (data['success'] == true || data['panel_url'] != null) {
          // Check for canonical header
          final canonical = resp.headers['x-panel-canonical'] ?? resp.headers['x-panel-location'];
          if (canonical != null && canonical.isNotEmpty) {
            log('_testUrlWorks: $url works, canonical: $canonical');
            if (canonical != url) {
              await _saveWorkingUrl(canonical);
              return true;
            }
          }
          return true;
        }
      }
      // Also try check-update as fallback test
      final testUrl2 = Uri.parse("$url/api/v1/app/check-update?platform=android");
      final resp2 = await http.get(testUrl2, headers: {'Accept': 'application/json'}).timeout(const Duration(seconds: 3));
      return resp2.statusCode == 200;
    } catch (_) {
      return false;
    }
  }

  // v8.0 PRO MAX: Intelligent Panel Location Resolver - 6 Layers
  static Future<String?> resolvePanelLocation() async {
    log('v8.0 resolver: starting intelligent discovery...');
    
    // Layer 1: Try saved domain with well-known
    try {
      final prefs = await SharedPreferences.getInstance();
      final savedDomain = prefs.getString('panel_domain') ?? 'vpbotn.ir';
      
      // Layer 2: Well-Known discovery
      final wellKnownUrl = await _tryWellKnownDiscovery(savedDomain);
      if (wellKnownUrl != null) {
        log('resolver L2: well-known found: $wellKnownUrl');
        if (await _testUrlWorks(wellKnownUrl)) {
          return wellKnownUrl;
        }
      }
      
      // Try well-known for all known domains
      for (final domain in knownDomains) {
        if (domain == savedDomain) continue;
        final wk = await _tryWellKnownDiscovery(domain);
        if (wk != null && await _testUrlWorks(wk)) {
          log('resolver L2b: well-known found via $domain: $wk');
          return wk;
        }
      }
    } catch (e) {
      log('resolver L2 error: $e');
    }
    
    // Layer 3: Try panel-location API on known URLs
    try {
      for (final base in baseUrls) {
        final loc = await _tryPanelLocationApi(base);
        if (loc != null && await _testUrlWorks(loc)) {
          log('resolver L3: panel-location API found: $loc via $base');
          return loc;
        }
      }
    } catch (e) {
      log('resolver L3 error: $e');
    }
    
    // Layer 4: Try old path redirector (contax -> new)
    try {
      for (final domain in knownDomains) {
        final oldPathUrl = "https://$domain/contax";
        final loc = await _tryOldPathRedirector(oldPathUrl);
        if (loc != null && await _testUrlWorks(loc)) {
          log('resolver L4: old path redirector found: $loc via $oldPathUrl');
          return loc;
        }
      }
    } catch (e) {
      log('resolver L4 error: $e');
    }
    
    // Layer 5: Brute-force common paths (parallel)
    try {
      final bruteResult = await _bruteForceCommonPaths();
      if (bruteResult != null) {
        log('resolver L5: brute-force found: $bruteResult');
        return bruteResult;
      }
    } catch (e) {
      log('resolver L5 error: $e');
    }
    
    // Layer 6: Remote config from GitHub
    try {
      final remote = await _tryRemoteConfig();
      if (remote != null && await _testUrlWorks(remote)) {
        log('resolver L6: remote config found: $remote');
        return remote;
      }
    } catch (e) {
      log('resolver L6 error: $e');
    }
    
    log('resolver: all layers failed');
    return null;
  }

  // Layer 2: Well-Known discovery
  static Future<String?> _tryWellKnownDiscovery(String domain) async {
    try {
      final urls = [
        "https://$domain/.well-known/connectix.json",
        "https://$domain/well-known/connectix.json",
      ];
      
      for (final url in urls) {
        try {
          final resp = await http.get(Uri.parse(url), headers: {'Accept': 'application/json'}).timeout(const Duration(seconds: 4));
          if (resp.statusCode == 200) {
            final data = jsonDecode(utf8.decode(resp.bodyBytes));
            final panelUrl = (data['panel_url'] ?? data['api_url'] ?? '').toString();
            if (panelUrl.isNotEmpty) {
              // Clean api_url to base url
              String base = panelUrl;
              if (base.contains('/api/')) {
                base = base.split('/api/')[0];
              }
              log('_tryWellKnownDiscovery: $url -> $base');
              return base;
            }
          }
        } catch (_) {
          continue;
        }
      }
    } catch (_) {}
    return null;
  }

  // Layer 3: Panel-Location API
  static Future<String?> _tryPanelLocationApi(String base) async {
    try {
      final url = Uri.parse("$base/api/v1/app/panel-location");
      final resp = await http.get(url, headers: {'Accept': 'application/json'}).timeout(const Duration(seconds: 4));
      if (resp.statusCode == 200) {
        final data = jsonDecode(utf8.decode(resp.bodyBytes));
        if (data['success'] == true || data['panel_url'] != null) {
          String panelUrl = (data['panel_url'] ?? data['api_url'] ?? '').toString();
          if (panelUrl.isNotEmpty) {
            if (panelUrl.contains('/api/')) {
              panelUrl = panelUrl.split('/api/')[0];
            }
            return panelUrl;
          }
        }
      }
      
      // Check for migration headers
      final canonical = resp.headers['x-panel-canonical'] ?? resp.headers['x-panel-location'] ?? resp.headers['x-panel-base-url'];
      if (canonical != null && canonical.isNotEmpty) {
        String base2 = canonical;
        if (base2.contains('/api/')) {
          base2 = base2.split('/api/')[0];
        }
        log('_tryPanelLocationApi: canonical header $canonical from $base');
        return base2;
      }
      
      // Check for PANEL_MOVED error
      try {
        final data = jsonDecode(utf8.decode(resp.bodyBytes));
        if (data['migrated'] == true || data['code'] == 'PANEL_MOVED') {
          String newUrl = (data['new_url'] ?? data['panel_url'] ?? data['api_url'] ?? '').toString();
          if (newUrl.isNotEmpty) {
            if (newUrl.contains('/api/')) {
              newUrl = newUrl.split('/api/')[0];
            }
            log('_tryPanelLocationApi: PANEL_MOVED detected, new: $newUrl');
            return newUrl;
          }
        }
      } catch (_) {}
    } catch (_) {}
    return null;
  }

  // Layer 4: Old path redirector
  static Future<String?> _tryOldPathRedirector(String oldBase) async {
    try {
      final urls = [
        "$oldBase/api/panel-location",
        "$oldBase/api/v1/app/panel-location",
        "$oldBase/panel_redirector.php?panel-location=1",
      ];
      
      for (final url in urls) {
        try {
          final resp = await http.get(Uri.parse(url), headers: {'Accept': 'application/json'}).timeout(const Duration(seconds: 4));
          if (resp.statusCode == 200) {
            final data = jsonDecode(utf8.decode(resp.bodyBytes));
            String newUrl = (data['new_url'] ?? data['panel_url'] ?? data['api_url'] ?? '').toString();
            if (newUrl.isNotEmpty) {
              if (newUrl.contains('/api/')) {
                newUrl = newUrl.split('/api/')[0];
              }
              return newUrl;
            }
          }
          // Check headers
          final canonical = resp.headers['x-panel-canonical'] ?? resp.headers['x-panel-location'] ?? resp.headers['location'];
          if (canonical != null && canonical.isNotEmpty && canonical.contains('http')) {
            String base = canonical;
            if (base.contains('/api/')) {
              base = base.split('/api/')[0];
            }
            return base;
          }
        } catch (_) {
          continue;
        }
      }
    } catch (_) {}
    return null;
  }

  // Layer 5: Brute-force common paths
  static Future<String?> _bruteForceCommonPaths() async {
    try {
      final List<Future<String?>> futures = [];
      
      for (final domain in knownDomains) {
        for (final path in commonPanelPaths) {
          final url = "https://$domain$path";
          futures.add(_testAndReturnUrl(url));
        }
      }
      
      // Run in parallel with timeout
      final results = await Future.wait(futures).timeout(const Duration(seconds: 10), onTimeout: () => <String?>[]);
      
      for (final result in results) {
        if (result != null && result.isNotEmpty) {
          return result;
        }
      }
    } catch (_) {}
    return null;
  }

  static Future<String?> _testAndReturnUrl(String url) async {
    try {
      if (await _testUrlWorks(url)) {
        return url;
      }
    } catch (_) {}
    return null;
  }

  // Layer 6: Remote config from GitHub
  static Future<String?> _tryRemoteConfig() async {
    try {
      final urls = [
        "https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/panel_location.json",
        "https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/.well-known/connectix.json",
      ];
      
      for (final url in urls) {
        try {
          final resp = await http.get(Uri.parse(url), headers: {'Accept': 'application/json'}).timeout(const Duration(seconds: 5));
          if (resp.statusCode == 200) {
            final data = jsonDecode(utf8.decode(resp.bodyBytes));
            String panelUrl = (data['panel_url'] ?? data['primary'] ?? '').toString();
            if (panelUrl.isNotEmpty) {
              if (panelUrl.contains('/api/')) {
                panelUrl = panelUrl.split('/api/')[0];
              }
              return panelUrl;
            }
          }
        } catch (_) {
          continue;
        }
      }
    } catch (_) {}
    return null;
  }

  static List<String> getOrderedBaseUrls() {
    if (baseUrls.isEmpty) return [baseUrl];
    if (baseUrls.first == baseUrl) return baseUrls;
    return [baseUrl, ...baseUrls.where((u) => u != baseUrl)];
  }

  static Future<void> _saveWorkingUrl(String workingUrl) async {
    try {
      // Clean URL
      String cleanUrl = workingUrl.trim();
      if (cleanUrl.endsWith('/')) {
        cleanUrl = cleanUrl.substring(0, cleanUrl.length - 1);
      }
      if (cleanUrl.contains('/api/')) {
        cleanUrl = cleanUrl.split('/api/')[0];
      }
      
      baseUrl = cleanUrl;
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('api_base_url_working', cleanUrl);
      await prefs.setString('api_base_url', cleanUrl);
      
      // Extract domain for future well-known discovery
      try {
        final uri = Uri.parse(cleanUrl);
        if (uri.host.isNotEmpty) {
          await prefs.setString('panel_domain', uri.host);
        }
      } catch (_) {}
      
      baseUrls = [cleanUrl, ...baseUrls.where((u) => u != cleanUrl)];
      log('Saved working URL: $cleanUrl');
    } catch (_) {}
  }

  // Check for canonical header in any response and auto-update
  static Future<void> _checkAndUpdateFromHeaders(http.Response response) async {
    try {
      final canonical = response.headers['x-panel-canonical'] ?? 
                       response.headers['x-panel-location'] ?? 
                       response.headers['x-panel-base-url'] ??
                       response.headers['x-panel-api-url'];
      
      if (canonical != null && canonical.isNotEmpty && canonical != baseUrl) {
        String newBase = canonical;
        if (newBase.contains('/api/')) {
          newBase = newBase.split('/api/')[0];
        }
        if (newBase != baseUrl) {
          log('Auto-update from header: $baseUrl -> $newBase');
          await _saveWorkingUrl(newBase);
        }
      }
    } catch (_) {}
  }

  // Check for migration in response body
  static Future<String?> _checkForMigration(http.Response response) async {
    try {
      final data = jsonDecode(utf8.decode(response.bodyBytes));
      if (data['migrated'] == true || data['code'] == 'PANEL_MOVED') {
        String newUrl = (data['new_url'] ?? data['panel_url'] ?? data['api_url'] ?? data['redirect'] ?? '').toString();
        if (newUrl.isNotEmpty) {
          if (newUrl.contains('/api/')) {
            newUrl = newUrl.split('/api/')[0];
          }
          log('Migration detected in body: $baseUrl -> $newUrl');
          await _saveWorkingUrl(newUrl);
          return newUrl;
        }
      }
    } catch (_) {}
    return null;
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

  // v8.0: Login with intelligent resolver + migration handling
  static Future<Map<String, dynamic>> login(String username, String password) async {
    final orderedUrls = getOrderedBaseUrls();
    log('v8.0 login start: trying ${orderedUrls.length} endpoints for user $username');
    
    // First, try to resolve panel location if saved URL fails
    bool triedResolver = false;
    
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

        // Check for migration headers/body before parsing
        await _checkAndUpdateFromHeaders(response);
        final migratedUrl = await _checkForMigration(response);
        if (migratedUrl != null && migratedUrl != currentBase) {
          log('login: panel moved, retrying with new URL: $migratedUrl');
          // Retry with new URL immediately
          try {
            final retryUrl = Uri.parse("$migratedUrl/api/v1/app/login");
            final retryResp = await http.post(
              retryUrl,
              headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
              body: jsonEncode({'username': username, 'password': password}),
            ).timeout(const Duration(seconds: 12));
            final retryData = jsonDecode(utf8.decode(retryResp.bodyBytes));
            if (retryData['success'] == true) {
              return await _handleLoginSuccess(retryData, migratedUrl, username, password);
            }
          } catch (_) {}
        }

        final data = jsonDecode(utf8.decode(response.bodyBytes));
        if (data['success'] == true) {
          return await _handleLoginSuccess(data, currentBase, username, password);
        } else {
          log('login FAILED (auth) via $currentBase: ${data['error']}');
          return {
            'success': false,
            'error': data['error'] ?? 'نام کاربری یا رمز عبور اشتباه است.',
          };
        }
      } catch (e) {
        log('login error via $currentBase: $e');
        if (i == orderedUrls.length - 1 && !triedResolver) {
          // Last endpoint failed, try resolver once
          triedResolver = true;
          log('login: all endpoints failed, trying intelligent resolver...');
          final resolved = await resolvePanelLocation();
          if (resolved != null && !orderedUrls.contains(resolved)) {
            log('login: resolver found new URL: $resolved, retrying...');
            try {
              final url = Uri.parse("$resolved/api/v1/app/login");
              final response = await http.post(
                url,
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                body: jsonEncode({'username': username, 'password': password}),
              ).timeout(const Duration(seconds: 12));
              final data = jsonDecode(utf8.decode(response.bodyBytes));
              if (data['success'] == true) {
                return await _handleLoginSuccess(data, resolved, username, password);
              }
            } catch (e2) {
              log('login retry via resolver failed: $e2');
            }
          }
        }
        if (i == orderedUrls.length - 1) {
          if (e is TimeoutException) {
            return {'success': false, 'error': 'اتصال به سرور طول کشید. تمام سرورها تست شد. اینترنت خود را بررسی کنید.'};
          }
          return {'success': false, 'error': 'خطا در برقراری ارتباط با سرور: $e\nتمام ${orderedUrls.length} آدرس تست شد.\n\n💡 اگر پنل جابجا شده، از بخش تنظیمات "اسکن QR پنل" را امتحان کنید.'};
        }
        await Future.delayed(Duration(milliseconds: 300));
        continue;
      }
    }
    return {'success': false, 'error': 'خطا در برقراری ارتباط با سرور'};
  }

  static Future<Map<String, dynamic>> _handleLoginSuccess(Map<String, dynamic> data, String currentBase, String username, String password) async {
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
  }

  /**
   * Universal Inbounds Delivery Engine with failover + migration handling
   */
  static Future<List<ServerModel>> getServers() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token') ?? '';
      final subUrl = prefs.getString('sub_url') ?? '';

      List<ServerModel> servers = [];
      final orderedUrls = getOrderedBaseUrls();

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
          
          await _checkAndUpdateFromHeaders(response);
          final migrated = await _checkForMigration(response);
          if (migrated != null) {
            // Retry with new URL
            try {
              final retryUrl = Uri.parse("$migrated/api/v1/app/configs?auth_token=${Uri.encodeComponent(token)}");
              final retryResp = await http.get(
                retryUrl,
                headers: {
                  'Authorization': 'Bearer $token',
                  'X-Auth-Token': token,
                  'Accept': 'application/json'
                },
              ).timeout(const Duration(seconds: 12));
              if (retryResp.statusCode == 200) {
                final retryData = jsonDecode(utf8.decode(retryResp.bodyBytes));
                if (retryData['success'] == true && retryData['data'] != null && retryData['data']['servers'] != null) {
                  final List list = retryData['data']['servers'];
                  final parsed = list
                      .map((e) => ServerModel.fromJson(e))
                      .where((s) => !s.isInfoBanner && !s.configUri.contains('mock_pbk') && s.id != 'mci_reality_de')
                      .toList();
                  if (parsed.isNotEmpty) {
                    servers = parsed;
                    await _saveWorkingUrl(migrated);
                    break;
                  }
                }
              }
            } catch (_) {}
          }
          
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
              break;
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

      if (servers.isEmpty && subUrl.isNotEmpty) {
        try {
          log('sublink fallback: $subUrl');
          final subResp = await http.get(
            Uri.parse(subUrl),
            headers: {'User-Agent': 'v2rayNG/1.8.5'},
          ).timeout(const Duration(seconds: 15));

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
          }
        } catch (e) {
          debugPrint("Sublink direct resolver error: $e");
        }
      }

      if (servers.isNotEmpty) {
        await saveCachedServers(servers);
      } else {
        servers = await getCachedServers();
      }

      return servers;
    } catch (e) {
      log('getServers Top-level error: $e');
      return await getCachedServers();
    }
  }

  static Future<int?> pingServerUri(String uriStr) async {
    try {
      String host = '';
      int port = 443;
      final lower = uriStr.toLowerCase().trim();
      
      Uri? _tryParse(String s) {
        try { return Uri.tryParse(s); } catch (_) { return null; }
      }

      if (lower.startsWith('vless://') || lower.startsWith('trojan://') || lower.startsWith('ss://') || lower.startsWith('socks://') || lower.startsWith('http://') || lower.startsWith('https://') || lower.startsWith('hysteria2://') || lower.startsWith('hy2://')) {
        String toParse = uriStr;
        if (lower.startsWith('ss://') && !uriStr.contains('@')) {
          try {
            final b64part = uriStr.substring(5).split('#')[0].split('?')[0];
            final decoded = utf8.decode(base64Decode(b64part));
            if (decoded.contains('@')) {
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

        await _checkAndUpdateFromHeaders(response);

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

        await _checkAndUpdateFromHeaders(response);

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

        await _checkAndUpdateFromHeaders(response);

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

  // v8.0: Manual panel URL update via QR
  static Future<bool> updatePanelUrlFromQr(String qrData) async {
    try {
      // QR can be: https://domain/path or connectix://panel?url=https://domain/path
      String url = qrData.trim();
      
      if (url.startsWith('connectix://')) {
        final uri = Uri.parse(url);
        url = uri.queryParameters['url'] ?? uri.queryParameters['panel'] ?? '';
      }
      
      if (url.isEmpty || !url.contains('http')) {
        return false;
      }
      
      // Clean
      if (url.contains('/api/')) {
        url = url.split('/api/')[0];
      }
      
      if (await _testUrlWorks(url)) {
        await _saveWorkingUrl(url);
        log('QR update: panel URL updated to $url');
        return true;
      }
      
      // Try well-known discovery for domain from QR
      try {
        final uri = Uri.parse(url);
        final domain = uri.host;
        final wk = await _tryWellKnownDiscovery(domain);
        if (wk != null && await _testUrlWorks(wk)) {
          await _saveWorkingUrl(wk);
          return true;
        }
      } catch (_) {}
      
      // Save anyway if looks valid
      if (url.startsWith('http')) {
        await _saveWorkingUrl(url);
        return true;
      }
    } catch (e) {
      log('QR update error: $e');
    }
    return false;
  }

  // v8.0: Get current panel location info
  static Future<Map<String, dynamic>?> getPanelLocationInfo() async {
    try {
      final orderedUrls = getOrderedBaseUrls();
      for (final base in orderedUrls) {
        try {
          final url = Uri.parse("$base/api/v1/app/panel-location");
          final resp = await http.get(url, headers: {'Accept': 'application/json'}).timeout(const Duration(seconds: 5));
          if (resp.statusCode == 200) {
            final data = jsonDecode(utf8.decode(resp.bodyBytes));
            if (data['success'] == true || data['panel_url'] != null) {
              return Map<String, dynamic>.from(data);
            }
          }
        } catch (_) {
          continue;
        }
      }
    } catch (_) {}
    return null;
  }

  // v4.0.11 PRO MAX FIX: Robust download with multiple fallbacks for Iran
  static Future<void> downloadAndInstallApk({
    required String downloadUrl,
    required Function(double progress, int receivedBytes, int totalBytes) onProgress,
    required Function(String error) onError,
    required Function() onSuccess,
  }) async {
    
    // Generate all possible download URLs to try
    List<String> generateAllUrls(String primary, String fallback) {
      final urls = <String>[];
      final seen = <String>{};
      
      void addUrl(String u) {
        if (u.isEmpty || !u.startsWith('http')) return;
        if (seen.contains(u)) return;
        seen.add(u);
        urls.add(u);
      }
      
      // Primary first
      addUrl(primary);
      addUrl(fallback);
      
      // Try panel host mirrored URLs (fastest inside Iran, not filtered)
      try {
        final orderedBases = getOrderedBaseUrls();
        for (final base in orderedBases) {
          // Extract version from primary URL if possible
          String ver = '';
          final verMatch = RegExp(r'v?(\d+\.\d+\.\d+)').firstMatch(primary);
          if (verMatch != null) ver = verMatch.group(1) ?? '';
          
          // Panel host URLs (these are mirrored by AppApkMirror)
          addUrl("$base/Connectix-ARM64-v8a.apk");
          addUrl("$base/Connectix-Universal.apk");
          addUrl("$base/Connectix-Android-ARM64.apk");
        }
      } catch (_) {}
      
      // GitHub direct URLs as last resort
      try {
        String ver = '';
        final verMatch = RegExp(r'v?(\d+\.\d+\.\d+)').firstMatch(primary);
        if (verMatch != null) ver = verMatch.group(1) ?? '4.0.10';
        if (ver.isNotEmpty) {
          addUrl("https://github.com/hojjatrad/panelconnectix/releases/download/v$ver/Connectix-Android-ARM64.apk");
          addUrl("https://github.com/hojjatrad/panelconnectix/releases/download/v$ver/Connectix-Android-Universal.apk");
          addUrl("https://github.com/hojjatrad/panelconnectix/releases/download/v$ver/Connectix-ARM64-v8a.apk");
        }
      } catch (_) {}
      
      // Also try ir.vpbotn.ir direct
      addUrl("https://ir.vpbotn.ir/Connectix-ARM64-v8a.apk");
      addUrl("https://vpbotn.ir/Connectix-ARM64-v8a.apk");
      addUrl("https://cf.vpbotn.ir/Connectix-ARM64-v8a.apk");
      
      return urls;
    }

    Future<bool> attemptDownloadWithHttp(String url) async {
      // Method 1: Try with http package (more reliable for redirects)
      try {
        log('download attempt (http pkg): $url');
        String? cacheDirPath;
        try {
          cacheDirPath = await _updaterChannel.invokeMethod<String>('getCacheDir');
        } catch (_) {}
        if (cacheDirPath == null || cacheDirPath.isEmpty) {
          cacheDirPath = "/data/user/0/com.connectix.vpn/cache";
        }
        final dir = Directory(cacheDirPath);
        if (!await dir.exists()) await dir.create(recursive: true);
        final file = File('$cacheDirPath/Connectix-Update.apk');
        if (await file.exists()) {
          try { await file.delete(); } catch (_) {}
        }

        final response = await http.get(
          Uri.parse(url),
          headers: {
            'User-Agent': 'Mozilla/5.0 (Linux; Android 10; Mobile) Connectix',
            'Accept': '*/*',
            'Cache-Control': 'no-cache',
          },
        ).timeout(const Duration(minutes: 3));

        if (response.statusCode >= 400) {
          log('http pkg download HTTP ${response.statusCode} for $url');
          // Check if it's HTML error page
          final bodyStr = utf8.decode(response.bodyBytes, allowMalformed: true);
          if (bodyStr.length < 5000 && (bodyStr.contains('<html') || bodyStr.contains('404') || bodyStr.contains('403'))) {
            log('http pkg got HTML error page (${bodyStr.length} bytes) for $url');
            throw Exception('خطای سرور: ${response.statusCode} - صفحه خطا دریافت شد');
          }
          throw Exception('کد خطا: ${response.statusCode}');
        }

        if (response.bodyBytes.length < 1000000) {
          final bodyStr = utf8.decode(response.bodyBytes, allowMalformed: true);
          if (bodyStr.contains('<html') || bodyStr.contains('<!DOCTYPE')) {
            log('http pkg got HTML (${response.bodyBytes.length} bytes) instead of APK for $url: ${bodyStr.substring(0, 200)}');
            throw Exception('فایل HTML دریافت شد به جای APK (احتمالا فیلترینگ یا خطای سرور) - حجم: ${response.bodyBytes.length}');
          }
          throw Exception('فایل ناقص است (حجم ${response.bodyBytes.length} بایت) از $url');
        }

        await file.writeAsBytes(response.bodyBytes);
        final len = await file.length();
        
        // Validate APK header PK
        try {
          final raf = await file.open();
          final header = await raf.read(4);
          await raf.close();
          if (header.length < 2 || header[0] != 0x50 || header[1] != 0x4B) {
            final firstBytes = String.fromCharCodes(header);
            log('http pkg invalid APK header: $firstBytes for $url len=$len');
            throw Exception('فایل APK معتبر نیست (هدر: $firstBytes) - ممکن است صفحه خطا باشد');
          }
        } catch (e) {
          if (e.toString().contains('APK معتبر نیست') || e.toString().contains('صفحه خطا')) rethrow;
        }

        log('http pkg download success: $url len=$len');
        onProgress(1.0, len, len);
        
        // Try install
        try {
          final installResult = await _updaterChannel.invokeMethod('installApk', {'filePath': file.path, 'allowSameVersion': true});
          if (installResult == true || installResult == 'true' || installResult == null) {
            onSuccess();
            return true;
          } else {
            throw Exception('نصب شروع نشد: $installResult');
          }
        } catch (nativeErr) {
          final errStr = nativeErr.toString();
          if (errStr.contains('FILE_NOT_FOUND')) {
            onError('❌ فایل یافت نشد: ${file.path} - $url\nحجم: ${(len/1024/1024).toStringAsFixed(1)} MB\nاز مرورگر دانلود کنید');
          } else if (errStr.contains('INSTALL_ERROR')) {
            final detail = errStr.length > 400 ? errStr.substring(0, 400) : errStr;
            onError('❌ خطای نصب: $detail\nاز مرورگر: $url');
          } else {
            onError('❌ نصب نشد: $errStr\nحجم: ${(len/1024/1024).toStringAsFixed(1)} MB\nمرورگر: $url');
          }
          return false;
        }
      } catch (e) {
        log('http pkg download failed for $url: $e');
        return false;
      }
    }

    Future<bool> attemptDownloadWithHttpClient(String url) async {
      // Method 2: HttpClient with streaming (original method, for large files)
      String? cacheDirPath;
      try {
        cacheDirPath = await _updaterChannel.invokeMethod<String>('getCacheDir');
      } catch (_) {}
      if (cacheDirPath == null || cacheDirPath.isEmpty) {
        cacheDirPath = "/data/user/0/com.connectix.vpn/cache";
      }
      final dir = Directory(cacheDirPath);
      if (!await dir.exists()) await dir.create(recursive: true);
      final file = File('$cacheDirPath/Connectix-Update.apk');
      if (await file.exists()) {
        try { await file.delete(); } catch (_) {}
      }

      final httpClient = HttpClient();
      httpClient.connectionTimeout = const Duration(seconds: 25);
      httpClient.idleTimeout = const Duration(seconds: 25);
      httpClient.autoUncompress = false;
      try {
        log('download attempt (HttpClient): $url');
        final uri = Uri.parse(url);
        final request = await httpClient.getUrl(uri);
        request.headers.set(HttpHeaders.userAgentHeader, 'Mozilla/5.0 (Linux; Android 10; Mobile) Connectix v4.0.11');
        request.headers.set(HttpHeaders.acceptHeader, '*/*');
        request.headers.set(HttpHeaders.cacheControlHeader, 'no-cache');
        request.followRedirects = true;
        request.maxRedirects = 5;
        final response = await request.close().timeout(const Duration(minutes: 6));

        if (response.statusCode >= 400) {
          log('HttpClient download HTTP ${response.statusCode} for $url');
          throw Exception('کد خطا: ${response.statusCode} از $url');
        }

        final total = response.contentLength > 0 ? response.contentLength : 0;
        log('HttpClient start: $url total=$total status=${response.statusCode}');

        final sink = file.openWrite();
        int received = 0;
        await for (final chunk in response) {
          sink.add(chunk);
          received += chunk.length;
          if (total > 0) {
            onProgress((received / total).clamp(0.0, 1.0), received, total);
          } else {
            final fakeProgress = (received / (40 * 1024 * 1024)).clamp(0.0, 0.95);
            onProgress(fakeProgress, received, 0);
          }
        }
        await sink.flush();
        await sink.close();

        final len = await file.length();
        log('HttpClient finished: $url len=$len total=$total');

        if (!await file.exists()) throw Exception('فایل ایجاد نشد: $url');
        if (len < 1000000) {
          // Check if HTML
          try {
            final firstKb = await file.openRead(0, 1024).transform(utf8.decoder).join();
            if (firstKb.contains('<html') || firstKb.contains('<!DOCTYPE')) {
              throw Exception('فایل HTML دریافت شد (${len} بایت) از $url - احتمال فیلترینگ:\n${firstKb.substring(0, 200)}');
            }
          } catch (_) {}
          throw Exception('فایل ناقص است (حجم ${len} بایت) از $url - اینترنت ناپایدار');
        }
        try {
          final raf = await file.open();
          final header = await raf.read(4);
          await raf.close();
          if (header.length < 2 || header[0] != 0x50 || header[1] != 0x4B) {
            throw Exception('APK معتبر نیست از $url - هدر اشتباه');
          }
        } catch (e) {
          if (e.toString().contains('معتبر نیست') || e.toString().contains('HTML') || e.toString().contains('ناقص')) rethrow;
        }

        try {
          final installResult = await _updaterChannel.invokeMethod('installApk', {'filePath': file.path, 'allowSameVersion': true});
          if (installResult == true || installResult == 'true' || installResult == null) {
            onSuccess();
            return true;
          } else {
            throw Exception('نصب شروع نشد: $installResult');
          }
        } catch (nativeErr) {
          final errStr = nativeErr.toString();
          onError('❌ نصب نشد: $errStr\nفایل: $url\nحجم: ${(len/1024/1024).toStringAsFixed(1)} MB\nراهنما: تنظیمات → نصب ناشناخته → فعال');
          return false;
        }
      } catch (e) {
        log('HttpClient failed for $url: $e');
        return false;
      } finally {
        try { httpClient.close(force: true); } catch (_) {}
      }
    }

    try {
      try {
        final canInstall = await _updaterChannel.invokeMethod<bool>('canInstallPackages') ?? true;
        if (!canInstall) {
          await _updaterChannel.invokeMethod('openInstallPermissionSettings');
          onError('دسترسی «نصب برنامه‌های ناشناخته» را فعال کنید:\nتنظیمات → حریم خصوصی → نصب ناشناخته → Connectix را فعال کنید\n\nسپس دوباره تلاش کنید.');
          return;
        }
      } catch (_) {}

      String fallbackUrl = '';
      try {
        final updateData = await checkAppUpdate();
        fallbackUrl = (updateData?['fallback_url'] ?? '').toString();
      } catch (_) {}

      final allUrls = generateAllUrls(downloadUrl, fallbackUrl);
      log('v4.0.11 download: trying ${allUrls.length} URLs: $allUrls');

      // Try each URL with http package first (fast), then HttpClient (streaming)
      for (int i = 0; i < allUrls.length; i++) {
        final url = allUrls[i];
        log('v4.0.11 trying ${i+1}/${allUrls.length}: $url');
        
        // Try http package first (better for small/medium files, handles redirects)
        bool ok = await attemptDownloadWithHttp(url);
        if (ok) return;
        
        // If http package failed, try HttpClient streaming (better for large files)
        ok = await attemptDownloadWithHttpClient(url);
        if (ok) return;
        
        // Small delay before next URL
        await Future.delayed(Duration(milliseconds: 500));
      }

      // All URLs failed
      onError('❌ تمام ${allUrls.length} لینک دانلود شکست خورد.\n\n'
          '🔍 دلایل احتمالی:\n'
          '• فیلترینگ گیت‌هاب در ایران (همراه اول/ایرانسل)\n'
          '• اینترنت ناپایدار\n'
          '• فضای ذخیره‌سازی پر\n\n'
          '✅ راه حل:\n'
          '1. با WiFi امتحان کنید (نه دیتا)\n'
          '2. فیلترشکن را خاموش کنید و دوباره امتحان کنید\n'
          '3. روی \"دانلود با مرورگر\" بزنید و از پوشه دانلود نصب کنید\n\n'
          'لینک مستقیم:\n$downloadUrl');
    } catch (e) {
      log('downloadAndInstallApk top-level error: $e');
      onError('❌ خطای کلی: $e\n\nاز مرورگر دانلود کنید:\n$downloadUrl');
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

        await _checkAndUpdateFromHeaders(resp);

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
