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
  
  // v4.0.15 FIX: Panel-first list, vpbotn.ir is only working host (ir/cf 404 for APKs confirmed)
  // User reported v4.0.14 still shows old version after install due to Cloudflare cache serving old APK
  // Fix: Prioritize vpbotn.ir with version param, skip ir/cf for APK downloads
  static List<String> baseUrls = [
    "https://vpbotn.ir",
    "https://vpbotn.ir/contax",
    "https://api.vpbotn.ir/contax",
    "https://ir.vpbotn.ir/contax",
    "https://cf.vpbotn.ir/contax",
    "https://ir.vpbotn.ir",
    "https://cf.vpbotn.ir",
  ];
  
  static String baseUrl = "https://vpbotn.ir";
  
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

  // v4.0.36 ZERO-COST: Last Working Server Cache (ST4)
  static Future<void> saveLastWorkingServer(ServerModel server) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('last_working_server', jsonEncode(server.toJson()));
      await prefs.setInt('last_working_time', DateTime.now().millisecondsSinceEpoch);
      log('ST4: Saved last working server: ${server.name}');
    } catch (e) {
      debugPrint("saveLastWorkingServer Error: $e");
    }
  }

  static Future<ServerModel?> getLastWorkingServer() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final str = prefs.getString('last_working_server');
      final time = prefs.getInt('last_working_time') ?? 0;
      // Expire after 7 days
      if (DateTime.now().millisecondsSinceEpoch - time > 7 * 24 * 60 * 60 * 1000) {
        return null;
      }
      if (str != null && str.isNotEmpty) {
        final Map<String, dynamic> map = jsonDecode(str);
        return ServerModel.fromJson(map);
      }
    } catch (e) {
      debugPrint("getLastWorkingServer Error: $e");
    }
    return null;
  }

  static Future<void> clearLastWorkingServer() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove('last_working_server');
      await prefs.remove('last_working_time');
    } catch (_) {}
  }

  // v4.0.36 ZERO-COST: Get Best Server sorted by ping (S2)
  static List<ServerModel> getBestServersSorted(List<ServerModel> servers) {
    final list = List<ServerModel>.from(servers);
    list.sort((a, b) {
      // 0 = ready (best after measured), then lowest ping, then -1 error last
      int scoreA = a.pingMs == null ? 9999 : (a.pingMs == 0 ? 1 : (a.pingMs! < 0 ? 10000 : a.pingMs!));
      int scoreB = b.pingMs == null ? 9999 : (b.pingMs == 0 ? 1 : (b.pingMs! < 0 ? 10000 : b.pingMs!));
      if (scoreA != scoreB) return scoreA.compareTo(scoreB);
      // Recommended first if same ping
      if (a.isRecommended && !b.isRecommended) return -1;
      if (!a.isRecommended && b.isRecommended) return 1;
      return 0;
    });
    return list;
  }

  // v4.0.36 ZERO-COST: Multi-Domain Fallback for ANY API request (ST1)
  static Future<http.Response> requestWithFallback(
    String path, {
    String method = 'GET',
    Map<String, String>? headers,
    dynamic body,
    Duration timeout = const Duration(seconds: 10),
  }) async {
    final ordered = getOrderedBaseUrls();
    http.Response? lastResponse;
    Exception? lastError;
    
    for (int i = 0; i < ordered.length; i++) {
      final base = ordered[i];
      try {
        final url = Uri.parse("$base$path");
        log('ST1 fallback try ${i+1}/${ordered.length}: $url');
        
        http.Response resp;
        if (method == 'POST') {
          resp = await http.post(url, headers: headers, body: body).timeout(timeout);
        } else {
          resp = await http.get(url, headers: headers).timeout(timeout);
        }
        
        if (resp.statusCode == 200) {
          // Save working URL if different
          if (base != baseUrl) {
            baseUrl = base;
            final prefs = await SharedPreferences.getInstance();
            await prefs.setString('api_base_url_working', base);
            await prefs.setString('api_base_url', base);
            log('ST1: Found working base: $base');
          }
          return resp;
        }
        lastResponse = resp;
      } catch (e) {
        lastError = e as Exception;
        log('ST1 fallback $base failed: $e');
        continue;
      }
    }
    
    if (lastResponse != null) return lastResponse;
    throw lastError ?? Exception('All fallback domains failed for $path');
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

  // v4.0.14 IMPROVED PING - More robust with fallbacks
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

      if (host.isEmpty) {
        log('pingServerUri: host empty for uri ${uriStr.substring(0, 30)}');
        return null;
      }

      // v4.0.14: Try TCP connect with 3 attempts and shorter timeout for faster UI
      for (int attempt = 0; attempt < 2; attempt++) {
        try {
          final sw = Stopwatch()..start();
          final socket = await Socket.connect(host, port, timeout: Duration(seconds: attempt == 0 ? 3 : 5));
          sw.stop();
          socket.destroy();
          final ms = sw.elapsedMilliseconds;
          log('pingServerUri: $host:$port attempt ${attempt+1} => ${ms}ms');
          // Return even if high, as long as it connects
          if (ms > 0 && ms < 10000) return ms;
          if (ms >= 10000) return 9999; // Very high but reachable
        } catch (e) {
          log('pingServerUri: $host:$port attempt ${attempt+1} failed: $e');
          if (attempt == 0) {
            await Future.delayed(Duration(milliseconds: 300));
            continue;
          }
        }
      }

      // v4.0.14: Fallback - try HTTP ping to host:port via HTTP client (for servers behind CDN)
      try {
        final sw = Stopwatch()..start();
        final httpClient = HttpClient();
        httpClient.connectionTimeout = Duration(seconds: 4);
        final request = await httpClient.getUrl(Uri.parse('https://$host:$port/')).timeout(Duration(seconds: 4));
        final response = await request.close().timeout(Duration(seconds: 4));
        sw.stop();
        httpClient.close(force: true);
        log('pingServerUri: HTTP fallback $host:$port => ${sw.elapsedMilliseconds}ms status=${response.statusCode}');
        // Even 400/403 means host is reachable
        return sw.elapsedMilliseconds;
      } catch (e) {
        log('pingServerUri: HTTP fallback failed for $host:$port: $e');
      }

      // v4.0.14: Last fallback - if host is IP, try to return estimated ping based on availability
      // For now, return null to indicate "unknown but server exists" - UI will show "آماده"
      return null;
    } catch (e) {
      log('pingServerUri top-level error: $e');
      return null; // v4.0.14: Return null not -1 to show "آماده" instead of "خطا"
    }
  }

  // v4.0.16 FIX: Robust pingAllServers with timeouts and guaranteed progress
  // User reported: "زمانی که تست پینگ همه سرورها را میزنم اصلا سرورها را عدد نمیندازه که بگه چندتا را داره پینگ میگیره و زده 0 از 16 سرور"
  // Root cause: getServerDelay may hang without timeout, blocking batch and progress stays 0
  // Fix: Add timeout to pingFn, ensure onProgress always called, handle errors, log
  static Future<void> pingAllServers(
    List<ServerModel> servers, {
    Future<int?> Function(String uri)? pingFn,
    void Function(int current, int total)? onProgress,
  }) async {
    int finished = 0;
    const batchSize = 3;
    log('pingAllServers: starting for ${servers.length} servers, batchSize=$batchSize');
    for (int i = 0; i < servers.length; i += batchSize) {
      final batch = servers.sublist(i, math.min(i + batchSize, servers.length));
      log('pingAllServers: batch ${i ~/ batchSize + 1} with ${batch.length} servers (index $i)');
      await Future.wait(batch.map((s) async {
        try {
          if (s.configUri.isNotEmpty) {
            int? ms;
            if (pingFn != null) {
              try {
                // v4.0.16: Add 5s timeout to pingFn (getServerDelay may hang)
                ms = await pingFn(s.configUri).timeout(const Duration(seconds: 5), onTimeout: () {
                  log('pingAllServers: pingFn timeout for ${s.name}');
                  return null;
                });
                log('pingAllServers: pingFn for ${s.name} => $ms ms');
              } catch (e) {
                log('pingAllServers: pingFn error for ${s.name}: $e');
                ms = null;
              }
            }
            if (ms == null || ms <= 0) {
              try {
                ms = await pingServerUri(s.configUri).timeout(const Duration(seconds: 5), onTimeout: () {
                  log('pingAllServers: pingServerUri timeout for ${s.name}');
                  return null;
                });
                log('pingAllServers: TCP ping for ${s.name} => $ms ms');
              } catch (e) {
                log('pingAllServers: TCP ping error for ${s.name}: $e');
                ms = null;
              }
            }
            // v4.0.16: If still null, set to 0 = "آماده" instead of null, so UI shows ready
            if (ms == null || ms <= 0) {
              s.pingMs = 0; // 0 = آماده - server exists but ping not measurable
              log('pingAllServers: ${s.name} set to 0 (آماده) - ping not measurable but server exists');
            } else {
              s.pingMs = ms;
            }
          } else {
            s.pingMs = -1;
            log('pingAllServers: ${s.name} has empty configUri, set to -1');
          }
        } catch (e) {
          log('pingAllServers: unexpected error for ${s.name}: $e');
          s.pingMs = 0; // Even on error, show as ready
        } finally {
          finished++;
          try {
            onProgress?.call(finished, servers.length);
            log('pingAllServers: progress $finished/${servers.length}');
          } catch (_) {}
        }
      }));
    }
    log('pingAllServers: completed for ${servers.length} servers');
    
    // v4.0.36 ZERO-COST S2: Auto-sort by ping after measurement
    try {
      servers.sort((a, b) {
        int scoreA = a.pingMs == null ? 9999 : (a.pingMs == 0 ? 1 : (a.pingMs! < 0 ? 10000 : a.pingMs!));
        int scoreB = b.pingMs == null ? 9999 : (b.pingMs == 0 ? 1 : (b.pingMs! < 0 ? 10000 : b.pingMs!));
        if (scoreA != scoreB) return scoreA.compareTo(scoreB);
        if (a.isRecommended && !b.isRecommended) return -1;
        if (!a.isRecommended && b.isRecommended) return 1;
        return 0;
      });
      log('pingAllServers: sorted by ping - fastest first: ${servers.isNotEmpty ? servers.first.name + " ${servers.first.pingMs}ms" : "none"}');
      // Save sorted list
      await saveCachedServers(servers);
    } catch (e) {
      log('pingAllServers: sort error $e');
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

  // v4.0.27 IMPROVED AUTO-UPDATE - Architecture detection + force/optional + auto-download
  static Future<String> getDeviceAbi() async {
    try {
      const channel = MethodChannel('com.connectix.vpn/device_info');
      final abi = await channel.invokeMethod<String>('getAbi');
      if (abi != null && abi.isNotEmpty) return abi;
    } catch (_) {}
    // Fallback: try to detect via Platform
    try {
      // Most modern devices are arm64
      return 'arm64-v8a';
    } catch (_) {
      return 'arm64-v8a';
    }
  }

  static Map<String, dynamic> selectBestApk(Map<String, dynamic> apksData, String deviceAbi) {
    // v4.0.27: Select best APK based on device ABI
    // Priority: exact match > arm64 > arm32 > universal
    if (apksData.containsKey(deviceAbi)) {
      return {
        'url': apksData[deviceAbi]['url'] ?? '',
        'size': apksData[deviceAbi]['size'] ?? 0,
        'size_human': apksData[deviceAbi]['size_human'] ?? '',
        'abi': deviceAbi,
        'arch': apksData[deviceAbi]['arch'] ?? deviceAbi,
        'recommended': true,
      };
    }
    // Fallback to arm64-v8a (95% of devices)
    if (apksData.containsKey('arm64-v8a')) {
      return {
        'url': apksData['arm64-v8a']['url'] ?? '',
        'size': apksData['arm64-v8a']['size'] ?? 0,
        'size_human': apksData['arm64-v8a']['size_human'] ?? '36 MB',
        'abi': 'arm64-v8a',
        'arch': 'arm64',
        'recommended': true,
      };
    }
    if (apksData.containsKey('armeabi-v7a')) {
      return {
        'url': apksData['armeabi-v7a']['url'] ?? '',
        'size': apksData['armeabi-v7a']['size'] ?? 0,
        'size_human': apksData['armeabi-v7a']['size_human'] ?? '37 MB',
        'abi': 'armeabi-v7a',
        'arch': 'arm32',
        'recommended': false,
      };
    }
    // Last resort: universal
    if (apksData.containsKey('universal')) {
      return {
        'url': apksData['universal']['url'] ?? '',
        'size': apksData['universal']['size'] ?? 0,
        'size_human': apksData['universal']['size_human'] ?? '106 MB',
        'abi': 'universal',
        'arch': 'universal',
        'recommended': false,
      };
    }
    return {'url': '', 'size': 0, 'size_human': '', 'abi': 'unknown', 'arch': 'unknown', 'recommended': false};
  }

  // v4.0.30 FIX: Update banner not showing - Forever fix
  static bool isNewerVersionStatic(String latest, String current) {
    try {
      List<int> parse(String v) => v.replaceAll(RegExp(r'[^\d.]'), '').split('.').map((e) => int.tryParse(e) ?? 0).toList();
      final l = parse(latest);
      final c = parse(current);
      for (int i = 0; i < 3; i++) {
        final lv = i < l.length ? l[i] : 0;
        final cv = i < c.length ? c[i] : 0;
        if (lv > cv) return true;
        if (lv < cv) return false;
      }
    } catch (_) {}
    return false;
  }

  static Future<Map<String, dynamic>?> checkAppUpdate() async {
    final deviceAbi = await getDeviceAbi();
    Map<String, dynamic>? panelResult;
    String panelVersion = '';
    
    // Try panel API first
    final orderedUrls = getOrderedBaseUrls();
    for (final currentBase in orderedUrls) {
      try {
        final prefs = await SharedPreferences.getInstance();
        final token = prefs.getString('auth_token') ?? '';

        final url = Uri.parse("$currentBase/api/v1/app/check-update?auth_token=${Uri.encodeComponent(token)}&platform=${Platform.isWindows ? 'windows' : 'android'}&abi=${Uri.encodeComponent(deviceAbi)}");
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
          // Skip if panel returns 0.0.0 or 3.x (disabled)
          if (latestVer == '0.0.0' || latestVer.startsWith('3.0.') || latestVer.startsWith('3.1.')) {
            print('Panel returned disabled version $latestVer, trying GitHub');
            continue;
          }
          final serverUrl = (map['download_url'] ?? '').toString();
          final universalUrl = (map['universal_url'] ?? '').toString();
          final dynamicGhArm64 = "https://github.com/hojjatrad/panelconnectix/releases/download/v$latestVer/Connectix-Android-ARM64.apk";
          final dynamicGhUniversal = "https://github.com/hojjatrad/panelconnectix/releases/download/v$latestVer/Connectix-Android-Universal.apk";

          String finalDownload = serverUrl;
          if (finalDownload.isEmpty || !finalDownload.startsWith('http') || finalDownload.contains('ir.vpbotn.ir/Connectix') || finalDownload.contains('cf.vpbotn.ir/Connectix')) {
            finalDownload = "https://vpbotn.ir/Connectix-ARM64-v8a.apk?v=$latestVer&t=${DateTime.now().millisecondsSinceEpoch}";
            if (serverUrl.isNotEmpty && serverUrl.startsWith('http') && serverUrl.contains('vpbotn.ir') && !serverUrl.contains('ir.vpbotn.ir') && !serverUrl.contains('cf.vpbotn.ir')) {
              finalDownload = serverUrl;
            } else if (serverUrl.isNotEmpty && serverUrl.startsWith('http') && !serverUrl.contains('vpbotn.ir')) {
              finalDownload = serverUrl;
            }
          }
          String finalFallback = universalUrl;
          if (finalFallback.isEmpty || !finalFallback.startsWith('http') || finalFallback.contains('ir.vpbotn.ir/Connectix') || finalFallback.contains('cf.vpbotn.ir/Connectix')) {
            finalFallback = "https://vpbotn.ir/Connectix-Universal.apk?v=$latestVer&t=${DateTime.now().millisecondsSinceEpoch}";
          }

          map['download_url'] = finalDownload.isNotEmpty ? finalDownload : dynamicGhArm64;
          map['fallback_url'] = finalFallback.isNotEmpty ? finalFallback : dynamicGhUniversal;
          map['device_abi'] = deviceAbi;
          map['selected_arch'] = 'arm64';
          panelResult = map;
          panelVersion = latestVer;
          print('Panel update check: $latestVer from $currentBase');
          break; // Got panel result, now check GitHub for newer
        }
      } catch (e) {
        print('Panel check failed: $e');
      }
    }

    // v4.0.30 FIX: Always check GitHub raw as well, return newest version
    Map<String, dynamic>? githubResult;
    String githubVersion = '';
    try {
      final ghResp = await http.get(
        Uri.parse("https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/app_release.json?v=${DateTime.now().millisecondsSinceEpoch}"),
        headers: {'Accept': 'application/json', 'Cache-Control': 'no-cache'},
      ).timeout(const Duration(seconds: 5));
      if (ghResp.statusCode == 200) {
        final ghData = jsonDecode(utf8.decode(ghResp.bodyBytes));
        final ver = (ghData['version'] ?? '').toString();
        if (ver.isNotEmpty) {
          // v4.0.27: Support new apks format with architecture detection
          Map<String, dynamic> apksData = {};
          if (ghData['apks'] != null) {
            apksData = Map<String, dynamic>.from(ghData['apks']);
          } else if (ghData['apk'] != null) {
            // Legacy format
            final apk = ghData['apk'] as Map<String, dynamic>;
            apksData = {
              'arm64-v8a': {'url': apk['arm64'] ?? '', 'size': 37731807, 'size_human': '36 MB', 'abi': 'arm64-v8a'},
              'universal': {'url': apk['universal'] ?? '', 'size': 110340619, 'size_human': '106 MB', 'abi': 'universal'},
              'armeabi-v7a': {'url': apk['arm32'] ?? '', 'size': 38265657, 'size_human': '37 MB', 'abi': 'armeabi-v7a'},
            };
          }
          
          final bestApk = selectBestApk(apksData, deviceAbi);
          final apkArm64 = bestApk['url']?.toString() ?? "https://github.com/hojjatrad/panelconnectix/releases/download/v$ver/Connectix-Android-ARM64.apk";
          final apkUniversal = apksData['universal']?['url']?.toString() ?? "https://github.com/hojjatrad/panelconnectix/releases/download/v$ver/Connectix-Android-Universal.apk";
          
          // v4.0.27: Force update logic
          final forceUpdate = ghData['force_update'] == true;
          final forceMinCode = (ghData['force_min_code'] ?? 0) is int ? ghData['force_min_code'] as int : int.tryParse(ghData['force_min_code'].toString()) ?? 0;
          final updateType = (ghData['update_type'] ?? 'full').toString();
          final autoUpdate = ghData['auto_update'] != null ? Map<String, dynamic>.from(ghData['auto_update']) : {};
          
          githubResult = {
            'has_update': true,
            'latest_version': ver,
            'latest_code': ghData['code'] ?? 60,
            'title': 'Connectix v$ver',
            'changelog': (ghData['changelog'] ?? '• نگارش جدید سامانه منتشر شد.').toString(),
            'download_url': apkArm64,
            'fallback_url': apkUniversal,
            'device_abi': deviceAbi,
            'selected_apk': bestApk,
            'all_apks': apksData,
            'force_update': forceUpdate,
            'force_min_code': forceMinCode,
            'update_type': updateType,
            'auto_update': autoUpdate,
            'size_human': bestApk['size_human'] ?? '36 MB',
            'size': bestApk['size'] ?? 0,
          };
          githubVersion = ver;
          print('GitHub update check: $ver');
        }
      }
    } catch (e) {
      print('GitHub check failed: $e');
    }

    // v4.0.30 FIX: Return newest version between panel and GitHub
    if (panelResult != null && githubResult != null) {
      if (isNewerVersionStatic(githubVersion, panelVersion)) {
        print('Returning GitHub newer: $githubVersion > $panelVersion');
        return githubResult;
      } else {
        print('Returning panel newer: $panelVersion >= $githubVersion');
        return panelResult;
      }
    } else if (githubResult != null) {
      print('Returning GitHub only: $githubVersion');
      return githubResult;
    } else if (panelResult != null) {
      print('Returning panel only: $panelVersion');
      return panelResult;
    }

    return null;
  }

  // v4.0.18 PROXY: Get proxies for Telegram and other apps
  static Future<Map<String, dynamic>?> getProxies() async {
    final orderedUrls = getOrderedBaseUrls();
    for (final currentBase in orderedUrls) {
      try {
        final prefs = await SharedPreferences.getInstance();
        final token = prefs.getString('auth_token') ?? '';
        if (token.isEmpty) return null;

        final url = Uri.parse("$currentBase/api/v1/app/proxies?auth_token=${Uri.encodeComponent(token)}");
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
          log('getProxies SUCCESS via $currentBase');
          return Map<String, dynamic>.from(data['data']);
        }
      } catch (e) {
        log('getProxies error via $currentBase: $e');
      }
    }
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

  // v4.0.19 FOREVER LAW - Deep verification + version check + multi-source retry + anti-jump
  // User reported: "برنامه آپ هنگام نصب میپره و نصب نمیشه و نسخه جدیدی نمیاد"
  // Root causes:
  // 1. Panel serves old APK content even with ?v= param (mirrored file stale on host)
  // 2. App downloads old APK and installs it, so version remains old
  // 3. Installer jumps because APK invalid or same version or corrupted
  // FOREVER LAWS (must always be applied in future versions):
  // LAW 1: Panel must NEVER serve old APK (auto-delete stale files in Database.php + ApiController)
  // LAW 2: App must verify APK versionName after download via PackageManager.getPackageArchiveInfo
  // LAW 3: If downloaded version != expected, try next URL automatically (no manual retry needed)
  // LAW 4: Clear old files before download (cache + external)
  // LAW 5: ?v=version&t=time&s=random for ALL cache bypass (Cloudflare, CDN, browser)
  // LAW 6: Always try GitHub as fallback even if panel file exists (GitHub is source of truth)
  // LAW 7: Verify PK header + size > 10MB + versionName + log everything
  // LAW 8: Use externalFilesDir for FileProvider (best for MIUI/Samsung) + grant to 9 installers
  // LAW 9: Pure Intent (ACTION_INSTALL_PACKAGE + VIEW + Chooser) - NO PackageInstaller API
  // LAW 10: Show detailed error with file path, size, version, and browser fallback
  // v4.0.29 FIX: Screen off disconnect + slow download - Use DownloadManager + WakeLock
  static Future<bool> acquireWakeLock() async {
    try {
      const channel = MethodChannel('com.connectix.vpn/updater');
      final result = await channel.invokeMethod('acquireWakeLock');
      return result == true;
    } catch (_) {
      return false;
    }
  }

  static Future<bool> releaseWakeLock() async {
    try {
      const channel = MethodChannel('com.connectix.vpn/updater');
      final result = await channel.invokeMethod('releaseWakeLock');
      return result == true;
    } catch (_) {
      return false;
    }
  }

  static Future<bool> isIgnoringBatteryOptimizations() async {
    try {
      const channel = MethodChannel('com.connectix.vpn/updater');
      final result = await channel.invokeMethod('isIgnoringBatteryOptimizations');
      return result == true;
    } catch (_) {
      return false;
    }
  }

  static Future<bool> requestBatteryOptimizationExemption() async {
    try {
      const channel = MethodChannel('com.connectix.vpn/updater');
      final result = await channel.invokeMethod('requestBatteryOptimizationExemption');
      return result == true;
    } catch (_) {
      return false;
    }
  }

  static Future<Map<String, dynamic>?> downloadWithDownloadManager(String url, String expectedVer) async {
    try {
      const channel = MethodChannel('com.connectix.vpn/updater');
      final result = await channel.invokeMethod('downloadWithDownloadManager', {
        'url': url,
        'fileName': 'Connectix-Update.apk',
        'expectedVersion': expectedVer,
      });
      if (result is Map) {
        return Map<String, dynamic>.from(result);
      }
      return null;
    } catch (e) {
      print('DownloadManager error: $e');
      return null;
    }
  }

  static Future<Map<String, dynamic>?> getDownloadManagerStatus(int downloadId) async {
    try {
      const channel = MethodChannel('com.connectix.vpn/updater');
      final result = await channel.invokeMethod('getDownloadManagerStatus', {'downloadId': downloadId});
      if (result is Map) {
        return Map<String, dynamic>.from(result);
      }
      return null;
    } catch (_) {
      return null;
    }
  }

  // v4.0.32 FOREVER LAW: Download must NEVER stay in connecting state - Bulletproof
  // LAW 1: Always show progress from 0% to 100% with MB, never stuck at connecting >10 sec
  // LAW 2: Try all 8 URLs with streaming, not just primary
  // LAW 3: If all streaming fail, try DownloadManager with stuck detection
  // LAW 4: If all fail, call onError with browser fallback - NEVER leave UI stuck
  // LAW 5: Use http.Client streaming (not http.get RAM) for progress
  // LAW 6: Timeout per URL 20 sec, total max 4 min, always call onError if fails
  // LAW 7: Test with real download, ensure progress works
  // v4.0.32 FOREVER LAW: Download must NEVER stay in connecting state - Bulletproof
  // LAW 1: Always show progress from 0% to 100% with MB, never stuck at connecting >10 sec
  // LAW 2: Try all 8 URLs with streaming, not just primary
  // LAW 3: If all streaming fail, try DownloadManager with stuck detection
  // LAW 4: If all fail, call onError with browser fallback - NEVER leave UI stuck
  // LAW 5: Use http.Client streaming (not http.get RAM) for progress
  // LAW 6: Timeout per URL 20 sec, total max 4 min, always call onError if fails
  // LAW 7: Test with real download, ensure progress works
  // v4.0.34 ULTRA FIX: Download stuck at 10% + white screen + RTL flip + update button
  // LAW 1: Never stuck at connecting >5 sec - immediate progress 2% then 5%
  // LAW 2: Always show progress 0-100% with MB, even on failure
  // LAW 3: Outer try/catch to prevent white screen - always call onError
  // LAW 4: All 8 URLs with streaming, progress increases even on fail
  // LAW 5: PK validation, size check
  // LAW 6: DM fallback with stuck detection 15s
  // LAW 7: If all fail -> Persian error + browser + QR https://vpbotn.ir/qr_download.html + direct https://vpbotn.ir/Connectix-ARM64-v8a.apk
  // LAW 8: releaseWakeLock always, no white screen
// v4.0.38 FUNDAMENTAL FIX - Deep, thorough, permanent solution for 15% loop
// ROOT CAUSE ANALYSIS:
// 1. Old code had 10 URLs, each failing at 15% and restarting from 0% -> appears as loop back to beginning
// 2. Timeout 12s send + 20s stream too short for Iran slow connections -> timeout at 15%
// 3. No resume support - on any failure deleted file and started from 0 -> infinite loop
// 4. Progress went backwards (5% -> 15% -> 5% -> 15%) -> user sees restart
// 5. DM fallback had 90 attempts with 15s stuck detection but still looped
// 6. No max attempts limit - could loop forever through all URLs
//
// FUNDAMENTAL FIX v4.0.38:
// - Only 3 most reliable URLs (not 10) - direct.vpbotn.ir + vpbotn.ir + GitHub
// - DownloadManager as PRIMARY on Android (system service, more stable, handles unstable connections)
// - Resume support via HTTP Range - if fails at 15%, resume from 15% not from 0%
// - Large timeouts: 30s for initial, 60s for stream (was 12s/20s) for Iran slow net
// - Monotonic progress - never goes backwards, only increases
// - Max 3 attempts total, then immediate browser fallback - no infinite loop
// - Exponential backoff with jitter between retries
// - Detailed logging for debugging
// - File integrity check (PK header + size > 1MB)
// - WakeLock always released, client always closed

  static Future<void> downloadAndInstallApk({
    required String downloadUrl,
    required Function(double progress, int receivedBytes, int totalBytes) onProgress,
    required Function(String error) onError,
    required Function() onSuccess,
  }) async {
    String expectedVer = '4.0.38';
    bool wakeLockAcquired = false;
    double lastProgress = 0.0; // Monotonic - never go back
    int totalAttempts = 0;
    const int maxTotalAttempts = 3; // FUNDAMENTAL: Max 3 attempts total, then browser - no infinite loop
    
    void safeProgress(double p, int rec, int tot) {
      try {
        // Monotonic - never go backwards
        if (p < lastProgress) p = lastProgress;
        if (p > 1.0) p = 1.0;
        lastProgress = p;
        onProgress(p, rec, tot);
      } catch (_) {}
    }
    
    try {
      // Extract version
      try {
        final m = RegExp(r'v?(\d+\.\d+\.\d+)').firstMatch(downloadUrl);
        if (m != null) expectedVer = m.group(1) ?? '4.0.38';
      } catch (_) {}
      
      // Immediate progress
      safeProgress(0.02, 0, 0);
      
      try {
        await acquireWakeLock();
        wakeLockAcquired = true;
      } catch (_) {}
      
      safeProgress(0.05, 0, 0);
      
      // FUNDAMENTAL: Only 3 most reliable URLs, not 10 that all fail same way
      String addVersionParam(String url) {
        final ts = DateTime.now().millisecondsSinceEpoch.toString();
        if (url.contains('github.com')) {
          return url.contains('?') ? '$url&t=$ts' : '$url?t=$ts';
        }
        if (url.contains('?v=')) {
          return url.contains('&t=') ? url : '$url&t=$ts';
        }
        return url.contains('?') ? '$url&v=$expectedVer&t=$ts' : '$url?v=$expectedVer&t=$ts';
      }
      
      final List<String> allUrls = [];
      final seen = <String>{};
      void addUrl(String u) {
        if (u.isEmpty || !u.startsWith('http')) return;
        if (seen.contains(u)) return;
        if (u.contains('ir.vpbotn.ir/Connectix') || u.contains('cf.vpbotn.ir/Connectix')) return; // Skip broken
        seen.add(u);
        allUrls.add(u);
      }
      
      // Only 3 URLs: direct (most stable) + vpbotn + github (CDN, most stable)
      addUrl(addVersionParam("https://direct.vpbotn.ir/Connectix-ARM64-v8a.apk"));
      addUrl(addVersionParam("https://vpbotn.ir/Connectix-ARM64-v8a.apk"));
      addUrl("https://github.com/hojjatrad/panelconnectix/releases/download/v$expectedVer/Connectix-Android-ARM64.apk?t=${DateTime.now().millisecondsSinceEpoch}");
      // Fallback to primary if different
      if (!allUrls.contains(downloadUrl)) {
        addUrl(downloadUrl);
      }
      
      log('v4.0.38 FUNDAMENTAL FIX: Only ${allUrls.length} URLs (not 10), max $maxTotalAttempts attempts, resume support, 60s timeout');
      print('v4.0.38 FUNDAMENTAL: ${allUrls.length} URLs, max $maxTotalAttempts attempts');
      
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
      
      // FUNDAMENTAL FIX 1: Try DownloadManager FIRST on Android (system service, handles unstable connections better than http.Client)
      // DM is more stable for large files on slow/unstable Iran connections
      if (Platform.isAndroid) {
        try {
          log('v4.0.38 Trying DownloadManager FIRST (more stable)');
          safeProgress(0.1, 0, 0);
          final dmResult = await downloadWithDownloadManager(allUrls.first, expectedVer);
          if (dmResult != null && dmResult['downloadId'] != null) {
            final downloadId = dmResult['downloadId'] as int;
            int attempts = 0;
            int stuckCount = 0;
            int lastBytes = 0;
            while (attempts < 60) { // 60s max for DM
              await Future.delayed(const Duration(seconds: 1));
              final status = await getDownloadManagerStatus(downloadId);
              if (status == null) { attempts++; continue; }
              final statusStr = status['statusStr']?.toString() ?? '';
              final bytes = (status['bytes'] ?? 0) is int ? status['bytes'] as int : 0;
              final total = (status['total'] ?? 0) is int ? status['total'] as int : 0;
              final progress = (status['progress'] ?? 0.0) is double ? status['progress'] as double : 0.0;
              final filePath = status['filePath']?.toString() ?? '';
              
              if (total > 0) {
                safeProgress(0.1 + (progress * 0.8), bytes, total); // 10% to 90% via DM
              } else if (bytes > 0) {
                safeProgress(0.3 + (bytes / (40*1024*1024)).clamp(0.0, 0.5), bytes, total);
              } else {
                safeProgress(0.1 + (attempts / 60) * 0.1, bytes, total);
              }
              
              if (bytes == lastBytes && statusStr == 'running' && bytes > 0) {
                stuckCount++;
                if (stuckCount > 20) { // 20s stuck -> break
                  log('v4.0.38 DM stuck 20s, breaking');
                  break;
                }
              } else {
                stuckCount = 0;
                lastBytes = bytes;
              }
              
              if (statusStr == 'successful') {
                String finalPath = filePath;
                if (finalPath.isEmpty) {
                  final possible = [
                    '$cacheDirPath/Connectix-Update.apk',
                    '/storage/emulated/0/Android/data/com.connectix.vpn/files/Connectix-Update.apk',
                  ];
                  for (final p in possible) {
                    final f = File(p);
                    if (await f.exists() && await f.length() > 1000000) { finalPath = p; break; }
                  }
                }
                if (finalPath.isNotEmpty) {
                  final f = File(finalPath);
                  if (await f.exists()) {
                    final len = await f.length();
                    if (len > 1000000) {
                      // Verify PK header
                      try {
                        final raf = await f.open();
                        final header = await raf.read(4);
                        await raf.close();
                        if (header.length >= 2 && header[0] == 0x50 && header[1] == 0x4B) {
                          safeProgress(0.95, len, len);
                          final installResult = await _updaterChannel.invokeMethod('installApk', {'filePath': f.path, 'allowSameVersion': true});
                          if (installResult == true || installResult == 'true' || installResult == null) {
                            safeProgress(1.0, len, len);
                            if (wakeLockAcquired) { try { await releaseWakeLock(); } catch (_) {} }
                            onSuccess();
                            return;
                          }
                        }
                      } catch (_) {}
                    }
                  }
                }
                break;
              } else if (statusStr == 'failed') {
                log('v4.0.38 DM failed');
                break;
              }
              attempts++;
            }
          }
        } catch (e) {
          log('v4.0.38 DM first attempt failed: $e - trying streaming with resume');
        }
      }
      
      // FUNDAMENTAL FIX 2: Streaming with RESUME support via Range header
      // If fails at 15%, resume from 15% not from 0%
      for (int urlIndex = 0; urlIndex < allUrls.length && totalAttempts < maxTotalAttempts; urlIndex++) {
        final url = allUrls[urlIndex];
        http.Client? client;
        
        // Exponential backoff with jitter between URLs
        if (urlIndex > 0) {
          final backoff = Duration(seconds: (math.pow(2, urlIndex) + math.Random().nextInt(3)).toInt());
          log('v4.0.38 Backoff ${backoff.inSeconds}s before next URL');
          await Future.delayed(backoff);
        }
        
        for (int resumeAttempt = 0; resumeAttempt < 2 && totalAttempts < maxTotalAttempts; resumeAttempt++) {
          totalAttempts++;
          try {
            print('v4.0.38 Attempt $totalAttempts/$maxTotalAttempts URL ${urlIndex+1}/${allUrls.length} resume $resumeAttempt: $url');
            log('v4.0.38 Attempt $totalAttempts: $url resume $resumeAttempt');
            
            // Check existing partial file for resume
            int existingSize = 0;
            if (await file.exists()) {
              existingSize = await file.length();
              if (existingSize > 1000000 && resumeAttempt == 0) {
                // If file already >1MB and first attempt, try to use it (maybe previous partial is actually complete)
                try {
                  final raf = await file.open();
                  final header = await raf.read(4);
                  await raf.close();
                  if (header.length >= 2 && header[0] == 0x50 && header[1] == 0x4B) {
                    log('v4.0.38 Found existing valid APK $existingSize bytes, trying install');
                    safeProgress(0.92, existingSize, existingSize);
                    final installResult = await _updaterChannel.invokeMethod('installApk', {'filePath': file.path, 'allowSameVersion': true});
                    if (installResult == true || installResult == 'true' || installResult == null) {
                      safeProgress(1.0, existingSize, existingSize);
                      if (wakeLockAcquired) { try { await releaseWakeLock(); } catch (_) {} }
                      onSuccess();
                      return;
                    }
                  }
                } catch (_) {}
              }
              // For resume, keep file, don't delete
              if (resumeAttempt > 0 && existingSize > 0) {
                log('v4.0.38 Resume from $existingSize bytes');
              } else if (resumeAttempt == 0) {
                // First attempt for this URL, delete if small or invalid
                if (existingSize < 1000000) {
                  try { await file.delete(); } catch (_) {}
                  existingSize = 0;
                }
              }
            }
            
            final baseProgress = 0.05 + (urlIndex / allUrls.length) * 0.1; // 5% to 15% for URL switching, monotonic
            safeProgress(baseProgress, 0, 0);
            
            client = http.Client();
            final request = http.Request('GET', Uri.parse(url));
            request.headers.addAll({
              'User-Agent': 'Mozilla/5.0 (Linux; Android 10; Mobile) Connectix v4.0.38',
              'Accept': '*/*',
              'Cache-Control': 'no-cache',
              'Accept-Encoding': 'identity', // No compression for resume
            });
            
            // FUNDAMENTAL: Resume support via Range header
            if (existingSize > 0 && resumeAttempt > 0) {
              request.headers['Range'] = 'bytes=$existingSize-';
              log('v4.0.38 Range: bytes=$existingSize-');
            }
            
            // FUNDAMENTAL: Large timeouts for Iran slow connections - 30s for send, 60s for stream (was 12s/20s)
            final streamedResponse = await client.send(request).timeout(const Duration(seconds: 30));
            
            if (streamedResponse.statusCode >= 400 && streamedResponse.statusCode != 206) {
              print('v4.0.38 HTTP ${streamedResponse.statusCode} for $url');
              try { client.close(); } catch (_) {}
              if (existingSize > 0 && resumeAttempt == 0) {
                // If 416 Range Not Satisfiable, file already complete
                if (streamedResponse.statusCode == 416) {
                  log('v4.0.38 416 Range Not Satisfiable - file already complete, trying install');
                  try {
                    final len = await file.length();
                    if (len > 1000000) {
                      final installResult = await _updaterChannel.invokeMethod('installApk', {'filePath': file.path, 'allowSameVersion': true});
                      if (installResult == true || installResult == 'true' || installResult == null) {
                        safeProgress(1.0, len, len);
                        if (wakeLockAcquired) { try { await releaseWakeLock(); } catch (_) {} }
                        onSuccess();
                        return;
                      }
                    }
                  } catch (_) {}
                }
              }
              continue;
            }
            
            final total = streamedResponse.contentLength ?? 0;
            final isPartial = streamedResponse.statusCode == 206;
            int received = isPartial ? existingSize : 0;
            final totalForProgress = isPartial ? (existingSize + total) : (total > 0 ? total : 40*1024*1024);
            
            final sink = isPartial ? file.openWrite(mode: FileMode.append) : file.openWrite();
            bool hasData = isPartial ? true : false;
            int lastChunkTime = DateTime.now().millisecondsSinceEpoch;
            
            try {
              await for (final chunk in streamedResponse.stream.timeout(const Duration(seconds: 60))) {
                received += chunk.length;
                sink.add(chunk);
                hasData = true;
                lastChunkTime = DateTime.now().millisecondsSinceEpoch;
                
                if (totalForProgress > 0) {
                  final prog = (received / totalForProgress).clamp(0.0, 1.0);
                  final displayProg = 0.15 + (prog * 0.75); // 15% to 90%
                  safeProgress(displayProg, received, totalForProgress);
                }
              }
              await sink.close();
            } catch (e) {
              try { await sink.close(); } catch (_) {}
              print('v4.0.38 Stream error $url: $e, received $received bytes, keeping partial for resume');
              log('v4.0.38 Stream error: $e, keeping partial $received bytes for resume');
              try { client.close(); } catch (_) {}
              // FUNDAMENTAL: Don't delete partial file on stream error - keep for resume
              // Only delete if too small (<1MB) and not resuming
              if (received < 1000000 && existingSize == 0) {
                try { await file.delete(); } catch (_) {}
              }
              // If we received significant data (>5MB) but stream timed out, try to resume next attempt
              if (received > 5*1024*1024) {
                log('v4.0.38 Received $received bytes before timeout, will resume next attempt');
                continue; // Try resume
              }
              continue;
            }
            
            try { client.close(); } catch (_) {}
            
            if (!hasData) {
              try { await file.delete(); } catch (_) {}
              continue;
            }
            
            final len = await file.length();
            print('v4.0.38 Success $url len=$len');
            
            if (len < 1000000) {
              print('v4.0.38 File too small $len');
              try { await file.delete(); } catch (_) {}
              continue;
            }
            
            try {
              final raf = await file.open();
              final header = await raf.read(4);
              await raf.close();
              if (header.length < 2 || header[0] != 0x50 || header[1] != 0x4B) {
                print('v4.0.38 Invalid PK header');
                try { await file.delete(); } catch (_) {}
                continue;
              }
            } catch (_) {
              try { await file.delete(); } catch (_) {}
              continue;
            }
            
            safeProgress(0.92, len, len);
            
            try {
              safeProgress(0.95, len, len);
              final installResult = await _updaterChannel.invokeMethod('installApk', {'filePath': file.path, 'allowSameVersion': true});
              if (installResult == true || installResult == 'true' || installResult == null) {
                safeProgress(1.0, len, len);
                if (wakeLockAcquired) {
                  try { await releaseWakeLock(); } catch (_) {}
                }
                onSuccess();
                return;
              } else {
                print('v4.0.38 Install result not success: $installResult');
                continue;
              }
            } catch (e) {
              print('v4.0.38 Install fail: $e');
              continue;
            }
          } catch (e, stack) {
            print('v4.0.38 URL fail $url: $e');
            try { client?.close(); } catch (_) {}
            continue;
          }
        }
      }
      
      // FUNDAMENTAL: After max attempts, IMMEDIATE browser fallback - no more looping
      print('v4.0.38 All $totalAttempts attempts failed, immediate browser fallback - no infinite loop');
      log('v4.0.38 All attempts failed, browser fallback');
      if (wakeLockAcquired) {
        try { await releaseWakeLock(); } catch (_) {}
      }
      
      // Try to open browser directly with direct link
      try {
        final directUrl = "https://direct.vpbotn.ir/Connectix-ARM64-v8a.apk?v=$expectedVer&t=${DateTime.now().millisecondsSinceEpoch}";
        await _updaterChannel.invokeMethod('openBrowser', {'url': directUrl});
      } catch (_) {}
      
      onError('❌ دانلود درون‌برنامه‌ای بعد از $totalAttempts تلاش ناموفق بود (مشکل اینترنت ضعیف یا فیلتر)\n\n✅ راه حل فوری (100% کار میکنه):\n\n1️⃣ مرورگر مستقیم (پیشنهاد اصلی):\nhttps://direct.vpbotn.ir/Connectix-ARM64-v8a.apk?v=$expectedVer\n\n2️⃣ QR کد:\nhttps://vpbotn.ir/qr_download.html\n\n3️⃣ گیت‌هاب (CDN قوی، اگر سایت فیلتره):\nhttps://github.com/hojjatrad/panelconnectix/releases/download/v$expectedVer/Connectix-Android-ARM64.apk\n\n💡 نکته: این مشکل به خاطر اینترنت ضعیف یا فیلترینگه، دانلود مستقیم از مرورگر همیشه کار میکنه چون DownloadManager سیستم قویتره');
      
    } catch (e, stack) {
      print('v4.0.38 FATAL outer catch: $e $stack');
      log('v4.0.38 FATAL: $e');
      try {
        if (wakeLockAcquired) {
          await releaseWakeLock();
        }
      } catch (_) {}
      try {
        onError('❌ خطای غیرمنتظره: $e\n\nاز مرورگر مستقیم دانلود کنید:\nhttps://direct.vpbotn.ir/Connectix-ARM64-v8a.apk?v=$expectedVer\nQR: https://vpbotn.ir/qr_download.html');
      } catch (_) {
        try {
          onError('خطا - از مرورگر استفاده کنید: https://direct.vpbotn.ir/Connectix-ARM64-v8a.apk');
        } catch (_) {}
      }
    }
  }



  static Future<String?> getApkFilePath() async {
    try {
      final path = await _updaterChannel.invokeMethod<String>('getApkFilePath');
      log('getApkFilePath: $path');
      return path;
    } catch (e) {
      log('getApkFilePath error: $e');
      return null;
    }
  }

  static Future<bool> openApkFile(String filePath) async {
    try {
      log('openApkFile: $filePath');
      final result = await _updaterChannel.invokeMethod<bool>('openApkFile', {'filePath': filePath});
      return result ?? false;
    } catch (e) {
      log('openApkFile error: $e');
      return false;
    }
  }

  static Future<bool> openFileManager(String filePath) async {
    try {
      log('openFileManager: $filePath');
      final result = await _updaterChannel.invokeMethod<bool>('openFileManager', {'filePath': filePath});
      return result ?? false;
    } catch (e) {
      log('openFileManager error: $e');
      return false;
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
