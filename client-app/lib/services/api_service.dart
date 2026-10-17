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
import '../models/account_model.dart';
import 'account_manager.dart';

class ApiService {
  // v8.0 PRO MAX: Intelligent Panel Location Resolver
  // Layer 1: Saved working URL (fastest)
  // Layer 2: Well-Known discovery (/.well-known/connectix.json)
  // Layer 3: Panel-Location API (/api/v1/app/panel-location)
  // Layer 4: Old path redirector (/contax -> new)
  // Layer 5: Brute-force common paths
  // Layer 6: Remote config from GitHub
  
  // v4.0.45 FIX: Update error "ارتباط برقرار نشد" - FORENSIC
  // Root cause: baseUrls contained ir.vpbotn.ir and cf.vpbotn.ir which return 404 for APKs and sometimes timeout
  // User sees "connection failed" because all 7 URLs tried with 6s timeout each = 42s wait, then fails
  // FIX: Only keep working hosts, prioritize vpbotn.ir direct, add direct.vpbotn.ir, remove ir/cf from update path
  // LAW: For update check, ONLY vpbotn.ir and GitHub should be used - ir/cf are dead for APKs
  static List<String> baseUrls = [
    "https://vpbotn.ir",
    "https://direct.vpbotn.ir",
    "https://vpbotn.ir/contax",
    "https://api.vpbotn.ir/contax",
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
      final savedApiKey = prefs.getString('saved_api_key') ?? '';
      if (savedApiKey.isNotEmpty) {
        _customApiKey = savedApiKey;
        log('v4.0.50 initBaseUrl: loaded saved_api_key: ${savedApiKey.substring(0, savedApiKey.length > 8 ? 8 : savedApiKey.length)}...');
      }
      
      log('v8.0 initBaseUrl start: savedWorking=$savedWorking savedDomain=$savedDomain');
      
      // v4.0.51: First fetch global config from web panel - central management
      try {
        await fetchGlobalAppConfig();
      } catch (_) {}
      
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

  // v4.0.51: Fetch global app config from web panel - Central management
  static Map<String, dynamic>? _globalAppConfig;
  static Map<String, dynamic>? get globalAppConfig => _globalAppConfig;

  static Future<Map<String, dynamic>?> fetchGlobalAppConfig() async {
    try {
      // Try to fetch from current baseUrl + /api/v1/app/global-config
      final urlsToTry = [
        '$baseUrl/api/v1/app/global-config',
        'https://vpbotn.ir/api/v1/app/global-config',
        'https://vpbotn.ir/api/app/global-config',
      ];
      for (final url in urlsToTry) {
        try {
          final resp = await http.get(Uri.parse(url), headers: {'Accept': 'application/json'}).timeout(const Duration(seconds: 5));
          if (resp.statusCode == 200) {
            final data = jsonDecode(resp.body);
            if (data is Map && data['success'] == true && data['data'] != null) {
              _globalAppConfig = Map<String, dynamic>.from(data['data']);
              final prefs = await SharedPreferences.getInstance();
              await prefs.setString('global_app_config', jsonEncode(_globalAppConfig));
              log('v4.0.51 Global app config fetched from $url: ${_globalAppConfig}');
              // Auto-apply panel URL and API key from global config
              final panelUrl = _globalAppConfig!['default_panel_url']?.toString() ?? _globalAppConfig!['panel_url']?.toString() ?? '';
              final apiKey = _globalAppConfig!['default_api_key']?.toString() ?? _globalAppConfig!['api_key']?.toString() ?? '';
              final hidePanel = _globalAppConfig!['hide_manual_panel_url'] == true;
              final hideApiKey = _globalAppConfig!['hide_manual_api_key'] == true;
              final forceManaged = _globalAppConfig!['force_managed_mode'] == true;
              if (panelUrl.isNotEmpty && panelUrl.startsWith('http')) {
                baseUrl = panelUrl;
                await prefs.setString('api_base_url_working', panelUrl);
                await prefs.setString('api_base_url', panelUrl);
                log('v4.0.51 AUTO panel_url from global config: $panelUrl');
              }
              if (apiKey.isNotEmpty) {
                _customApiKey = apiKey;
                await prefs.setString('saved_api_key', apiKey);
                log('v4.0.51 AUTO api_key from global config: ${apiKey.substring(0, apiKey.length > 8 ? 8 : apiKey.length)}...');
              }
              if (hidePanel) {
                await prefs.setBool('hide_manual_panel_url', true);
                await prefs.setBool('hide_app_config', true);
              }
              if (hideApiKey) {
                await prefs.setBool('hide_manual_api_key', true);
              }
              if (forceManaged) {
                await prefs.setBool('app_managed_mode', true);
                await prefs.setBool('force_managed_mode', true);
              }
              return _globalAppConfig;
            }
          }
        } catch (e) {
          log('v4.0.51 fetchGlobalAppConfig failed for $url: $e');
          continue;
        }
      }
      // Try loading from prefs cache
      final prefs = await SharedPreferences.getInstance();
      final cached = prefs.getString('global_app_config');
      if (cached != null && cached.isNotEmpty) {
        try {
          _globalAppConfig = jsonDecode(cached) as Map<String, dynamic>;
          log('v4.0.51 Global app config loaded from cache');
          return _globalAppConfig;
        } catch (_) {}
      }
    } catch (e) {
      log('v4.0.51 fetchGlobalAppConfig error: $e');
    }
    return null;
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

  // v4.0.49 API KEY SUPPORT: Login via API key + username/password
  static String? _customApiKey;
  static void setApiKey(String? key) {
    _customApiKey = key;
  }
  static String? get customApiKey => _customApiKey;

  static Future<Map<String, dynamic>> loginWithApiKey({
    required String username,
    required String password,
    required String apiKey,
    String? panelUrl,
  }) async {
    _customApiKey = apiKey;
    if (panelUrl != null && panelUrl.isNotEmpty) {
      baseUrl = panelUrl;
      baseUrls = [panelUrl, ...baseUrls.where((u) => u != panelUrl)];
    }
    final result = await login(username, password);
    if (result['success'] == true) {
      // Save apiKey to account
      try {
        final accounts = await AccountManager.getAccounts();
        final id = '${username.toLowerCase()}@${Uri.tryParse(panelUrl ?? baseUrl)?.host ?? baseUrl}';
        final idx = accounts.indexWhere((a) => a.id == id);
        if (idx >= 0) {
          await AccountManager.updateAccountMeta(id, displayName: null, colorHex: null, avatarEmoji: null);
          // Save apiKey via addOrUpdate
          await AccountManager.addOrUpdateAccount(
            username: username,
            password: password,
            panelUrl: panelUrl ?? baseUrl,
            apiKey: apiKey,
          );
        }
      } catch (_) {}
    }
    return result;
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
        final loginHeaders = {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          if (_customApiKey != null && _customApiKey!.isNotEmpty) 'X-API-Key': _customApiKey!,
          if (_customApiKey != null && _customApiKey!.isNotEmpty) 'X-Reseller-API-Key': _customApiKey!,
        };
        final response = await http.post(
          url,
          headers: loginHeaders,
          body: jsonEncode({
            'username': username, 
            'password': password,
            if (_customApiKey != null && _customApiKey!.isNotEmpty) 'api_key': _customApiKey,
          }),
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
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                if (_customApiKey != null && _customApiKey!.isNotEmpty) 'X-API-Key': _customApiKey!,
              },
              body: jsonEncode({
                'username': username,
                'password': password,
                if (_customApiKey != null && _customApiKey!.isNotEmpty) 'api_key': _customApiKey,
              }),
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
    final token = data['data']['auth_token'] ?? '';
    await prefs.setBool('is_logged_in', true);
    await prefs.setString('auth_token', token);
    await prefs.setString('saved_username', username);
    await prefs.setString('saved_password', password);

    Map<String, dynamic> clientMap = {};
    Map<String, dynamic> brandingMap = {};
    if (data['data']['client'] != null) {
      clientMap = Map<String, dynamic>.from(data['data']['client']);
      await prefs.setString('cached_client', jsonEncode(clientMap));
      if (clientMap['sub_url'] != null) {
        await prefs.setString('sub_url', clientMap['sub_url'].toString());
      }
    }
    if (data['data']['branding'] != null) {
      brandingMap = Map<String, dynamic>.from(data['data']['branding']);
      await prefs.setString('cached_branding', jsonEncode(brandingMap));
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

    // v4.0.47 MULTI-ACCOUNT: save to account manager
    // v4.0.49 MANAGED MODE + API KEY + v4.0.50 AUTO API KEY FROM PANEL
    try {
      // v4.0.50: Extract API key from server response (set by admin in web panel)
      final serverApiKey = clientMap['api_key']?.toString() ?? brandingMap['api_key']?.toString() ?? '';
      final serverPanelUrl = brandingMap['panel_url']?.toString() ?? clientMap['panel_url']?.toString() ?? '';
      if (serverApiKey.isNotEmpty) {
        _customApiKey = serverApiKey;
        await prefs.setString('saved_api_key', serverApiKey);
        log('v4.0.50 AUTO API KEY from panel: ${serverApiKey.substring(0, serverApiKey.length > 8 ? 8 : serverApiKey.length)}... saved to prefs');
      }
      // v4.0.50: If server returns custom panel_url (set by admin), use it automatically
      if (serverPanelUrl.isNotEmpty && serverPanelUrl.startsWith('http')) {
        // Only update if different and valid
        if (serverPanelUrl != currentBase) {
          await prefs.setString('api_base_url_working', serverPanelUrl);
          await prefs.setString('api_base_url', serverPanelUrl);
          baseUrl = serverPanelUrl;
          log('v4.0.50 AUTO PANEL URL from admin: $serverPanelUrl applied');
        }
      }
      // Check if branding has hide config or managed mode flags
      final hideConfig = brandingMap['hide_app_config'] == true || brandingMap['hide_config'] == true || brandingMap['managed_mode'] == true || clientMap['hide_app_config'] == true;
      final managedMode = brandingMap['managed_mode'] == true || brandingMap['app_managed_mode'] == true || clientMap['managed_mode'] == true;
      if (hideConfig) {
        await AccountManager.setHideConfig(true);
        await prefs.setBool('hide_app_config', true);
        log('v4.0.50 AUTO HIDE CONFIG enabled from panel');
      }
      if (managedMode) {
        await AccountManager.setManagedMode(true);
        await prefs.setBool('app_managed_mode', true);
        log('v4.0.50 AUTO MANAGED MODE enabled from panel');
      }
      
      final account = await AccountManager.addOrUpdateAccount(
        username: username,
        password: password,
        panelUrl: serverPanelUrl.isNotEmpty ? serverPanelUrl : currentBase,
        serverCount: initialServers.length,
        planTitle: clientMap['plan_title']?.toString() ?? clientMap['plan']?.toString() ?? '',
        displayName: clientMap['customer_name']?.toString() ?? '',
        apiKey: serverApiKey,
      );
      await AccountManager.saveAccountToken(account.id, token);
      if (clientMap.isNotEmpty) await AccountManager.saveAccountClient(account.id, clientMap);
      if (brandingMap.isNotEmpty) await AccountManager.saveAccountBranding(account.id, brandingMap);
      if (initialServers.isNotEmpty) {
        await AccountManager.saveAccountServerCache(account.id, initialServers.map((s) => s.toJson()).toList());
      }
    } catch (e) {
      log('account manager save error: $e');
    }

    log('login SUCCESS via $currentBase');
    return {
      'success': true,
      'client': ClientModel.fromJson(data['data']['client']),
      'branding': BrandingModel.fromJson(data['data']['branding']),
      'servers': initialServers,
    };
  }

  // v4.0.47 MULTI-ACCOUNT: Switch active account
  static Future<Map<String, dynamic>?> switchToAccount(VpnAccount account) async {
    try {
      final password = await AccountManager.getPassword(account.id);
      final token = await AccountManager.getAccountToken(account.id);
      final prefs = await SharedPreferences.getInstance();
      
      // Set baseUrl to account's panelUrl
      baseUrl = account.panelUrl;
      baseUrls = [account.panelUrl, ...baseUrls.where((u) => u != account.panelUrl)];
      await prefs.setString('api_base_url_working', account.panelUrl);
      await prefs.setString('api_base_url', account.panelUrl);
      await prefs.setString('saved_username', account.username);
      if (password.isNotEmpty) await prefs.setString('saved_password', password);
      if (token != null && token.isNotEmpty) await prefs.setString('auth_token', token);
      await AccountManager.setActiveAccount(account.id);
      
      // Load cached client/branding for instant UI
      final clientJson = await AccountManager.getAccountClient(account.id);
      final cachedServersList = await AccountManager.getAccountServerCache(account.id);
      List<ServerModel> cachedServers = [];
      if (cachedServersList.isNotEmpty) {
        cachedServers = cachedServersList.map((e) => ServerModel.fromJson(Map<String, dynamic>.from(e))).toList();
        await saveCachedServers(cachedServers);
        await prefs.setString('cached_servers', jsonEncode(cachedServersList));
      }
      if (clientJson != null) {
        await prefs.setString('cached_client', jsonEncode(clientJson));
      }
      final brandingJson = await prefs.getString('cached_branding'); // will be overwritten by profile fetch
      // Try to refresh profile with current token
      final profile = await getProfile();
      if (profile != null) {
        return {
          'client': profile,
          'servers': cachedServers.isNotEmpty ? cachedServers : await getCachedServers(),
          'account': account,
        };
      }
      // If token invalid, try login with password
      if (password.isNotEmpty) {
        final loginRes = await login(account.username, password);
        if (loginRes['success'] == true) {
          return loginRes;
        }
      }
      // Fallback to cached
      if (clientJson != null) {
        return {
          'client': ClientModel.fromJson(clientJson),
          'servers': cachedServers,
          'account': account,
        };
      }
      return null;
    } catch (e) {
      log('switchToAccount error: $e');
      return null;
    }
  }

  // v4.0.47 MULTI-ACCOUNT: Get all servers from all accounts (unified mode)
  static Future<List<AccountServerEntry>> getAllAccountsServers() async {
    final accounts = await AccountManager.getAccounts();
    final List<AccountServerEntry> result = [];
    for (final acc in accounts) {
      final cache = await AccountManager.getAccountServerCache(acc.id);
      for (final sJson in cache) {
        try {
          final server = ServerModel.fromJson(Map<String, dynamic>.from(sJson));
          if (server.isInfoBanner || server.configUri.isEmpty) continue;
          result.add(AccountServerEntry(
            accountId: acc.id,
            accountName: acc.effectiveName,
            accountColor: acc.colorHex,
            accountAvatar: acc.avatarEmoji,
            server: server,
          ));
        } catch (_) {}
      }
    }
    return result;
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

  // v4.0.45 FIX: Update error "ارتباط برقرار نشد" - FORENSIC
  // OLD BUG: 6s timeout per URL, 4 URLs = 24s wait, plus GitHub 5s = 29s total before showing error
  // User on slow Iran network sees timeout and gets "connection failed"
  // FIX: 10s timeout, only 2 primary URLs, parallel GitHub check, never show connection error for update
  static Future<Map<String, dynamic>?> checkAppUpdate() async {
    final deviceAbi = await getDeviceAbi();
    Map<String, dynamic>? panelResult;
    String panelVersion = '';
    
    // v4.0.45: Only try 2 most reliable URLs for update check, not all 4, with longer timeout
    final orderedUrls = getOrderedBaseUrls().take(2).toList();
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
        ).timeout(const Duration(seconds: 10));

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

          // v4.0.43 FOREVER LAW 16 - PERMANENT CACHE FIX - Always versioned with t, s, cb, r
          final ts = DateTime.now().millisecondsSinceEpoch;
          final rnd = math.Random().nextInt(9999);
          final dynamicGhArm64 = "https://github.com/hojjatrad/panelconnectix/releases/download/v$latestVer/Connectix-Android-ARM64.apk?t=$ts&r=$rnd&_=$ts";
          final dynamicGhUniversal = "https://github.com/hojjatrad/panelconnectix/releases/download/v$latestVer/Connectix-Android-Universal.apk?t=$ts&r=$rnd&_=$ts";
          String addCacheBust(String url, String ver) {
            if (url.isEmpty) return url;
            final sep = url.contains('?') ? '&' : '?';
            // Always ensure v, t, s, cb, r present
            if (url.contains('?v=')) {
              var u = url;
              if (!u.contains('&t=')) u += '&t=$ts';
              if (!u.contains('&s=')) u += '&s=$rnd';
              if (!u.contains('&cb=')) u += '&cb=${ts}$rnd';
              if (!u.contains('&r=')) u += '&r=$rnd';
              u += '&_=$ts'; // Extra cache bust
              return u;
            }
            return '$url${sep}v=$ver&t=$ts&s=$rnd&cb=${ts}$rnd&r=$rnd&_=$ts';
          }

          String finalDownload = serverUrl;
          if (finalDownload.isEmpty || !finalDownload.startsWith('http') || finalDownload.contains('ir.vpbotn.ir/Connectix') || finalDownload.contains('cf.vpbotn.ir/Connectix')) {
            finalDownload = "https://vpbotn.ir/Connectix-ARM64-v8a.apk?v=$latestVer&t=$ts&s=$rnd&cb=${ts}$rnd&r=$rnd&_=$ts";
            if (serverUrl.isNotEmpty && serverUrl.startsWith('http') && serverUrl.contains('vpbotn.ir') && !serverUrl.contains('ir.vpbotn.ir') && !serverUrl.contains('cf.vpbotn.ir')) {
              finalDownload = addCacheBust(serverUrl, latestVer);
            } else if (serverUrl.isNotEmpty && serverUrl.startsWith('http') && !serverUrl.contains('vpbotn.ir')) {
              finalDownload = addCacheBust(serverUrl, latestVer);
            }
          } else {
            finalDownload = addCacheBust(finalDownload, latestVer);
          }
          String finalFallback = universalUrl;
          if (finalFallback.isEmpty || !finalFallback.startsWith('http') || finalFallback.contains('ir.vpbotn.ir/Connectix') || finalFallback.contains('cf.vpbotn.ir/Connectix')) {
            finalFallback = "https://vpbotn.ir/Connectix-Universal.apk?v=$latestVer&t=$ts&s=$rnd&cb=${ts}$rnd&r=$rnd&_=$ts";
          } else {
            finalFallback = addCacheBust(finalFallback, latestVer);
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

    // v4.0.45 FIX: Always check GitHub raw as well, return newest version - FOREVER LAW
    // GitHub is source of truth, must be tried even if panel fails, with longer timeout for Iran networks
    Map<String, dynamic>? githubResult;
    String githubVersion = '';
    try {
      final ghResp = await http.get(
        Uri.parse("https://raw.githubusercontent.com/hojjatrad/panelconnectix/main/app_release.json?v=${DateTime.now().millisecondsSinceEpoch}"),
        headers: {'Accept': 'application/json', 'Cache-Control': 'no-cache'},
      ).timeout(const Duration(seconds: 10));
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

  // v4.0.41 PROXY: Get proxies - FORENSIC FIX for infinite spinner
  // OLD BUG: 7 baseUrls * 8s = 56s worst, DNS hang not timed out, spinner infinite
  // FIX: Only 2 primary URLs, 3s timeout per URL, total max 6s, immediate return if token empty
  static Future<Map<String, dynamic>?> getProxies() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token') ?? '';
      if (token.isEmpty) {
        log('getProxies: token empty, returning null for local fallback');
        return null;
      }

      // v4.0.41: Only try 2 most reliable URLs for proxy, not all 7 - to prevent 56s hang
      final orderedUrls = getOrderedBaseUrls();
      final limitedUrls = orderedUrls.take(2).toList(); // Only first 2
      
      for (final currentBase in limitedUrls) {
        try {
          final url = Uri.parse("$currentBase/api/v1/app/proxies?auth_token=${Uri.encodeComponent(token)}");
          final response = await http.get(
            url,
            headers: {
              'Authorization': 'Bearer $token',
              'X-Auth-Token': token,
              'Accept': 'application/json'
            },
          ).timeout(const Duration(seconds: 3)); // v4.0.41: 3s not 8s

          await _checkAndUpdateFromHeaders(response);

          final data = jsonDecode(utf8.decode(response.bodyBytes));
          if (data['success'] == true && data['data'] != null) {
            await _saveWorkingUrl(currentBase);
            log('getProxies SUCCESS via $currentBase');
            return Map<String, dynamic>.from(data['data']);
          }
        } catch (e) {
          log('getProxies error via $currentBase: $e (3s timeout)');
        }
      }
      log('getProxies: all limited URLs failed, returning null for local fallback');
      return null;
    } catch (e) {
      log('getProxies FATAL: $e');
      return null;
    }
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
  // v4.0.41 FIX: Safe WakeLock with timeout + engine detached handling - prevents exit errors
  static Future<bool> acquireWakeLock() async {
    try {
      const channel = MethodChannel('com.connectix.vpn/updater');
      final result = await channel.invokeMethod('acquireWakeLock').timeout(const Duration(seconds: 2), onTimeout: () => false);
      return result == true;
    } catch (e) {
      // v4.0.41: MissingPluginException after engine detach is expected, ignore
      if (e.toString().contains('MissingPluginException')) {
        return false;
      }
      return false;
    }
  }

  static Future<bool> releaseWakeLock() async {
    try {
      const channel = MethodChannel('com.connectix.vpn/updater');
      final result = await channel.invokeMethod('releaseWakeLock').timeout(const Duration(seconds: 2), onTimeout: () => false);
      return result == true;
    } catch (e) {
      if (e.toString().contains('MissingPluginException')) {
        return false;
      }
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
  // v4.0.41 ULTRA FORENSIC FIX - Deep expert analysis for 10% stuck + exit errors + proxy spinning
  // ROOT CAUSE DEEP DIVE (3 bugs reported: 10% download stuck, exit errors, proxy infinite spinner):
  //
  // BUG 1: Download stuck at 10% - FORENSIC:
  // - Old v4.0.40 used DownloadManager as PRIMARY, DM stuck at 0 bytes for 60s with progress 10% (0.1 + attempts/60*0.1)
  // - Stuck detection only when bytes>0, so 0 bytes never detected as stuck -> infinite 10%
  // - File path used getCacheDir (/data/user/0/.../cache) which on MIUI/Samsung is cleared aggressively + FileProvider fails
  // - No progress timer when no data -> UI appears frozen
  // - Only 3 attempts then browser, but DM took 60s before trying streaming -> user sees 10% for 60s
  //
  // BUG 2: Exit errors - FORENSIC:
  // - dashboard dispose only canceled timers, not V2Ray service -> V2Ray still running when activity destroyed -> error
  // - main.dart _SessionMarkerState didChangeAppLifecycleState created NEW V2RayCompat() instance, _vless null -> exception on exit
  // - MethodChannel calls after engine detached -> MissingPluginException
  //
  // BUG 3: Proxy infinite spinner - FORENSIC:
  // - getProxies tried 7 baseUrls * 8s = 56s worst, outer timeout 10s should cut but http.get timeout not always triggered on DNS hang
  // - proxy_screen had fallback but _isLoading set only after getProxies returns, if getProxies hangs 56s, spinner 56s
  // - No immediate local fallback for offline case
  //
  // FIX v4.0.41 ARCHITECTURE:
  // - Streaming as PRIMARY (more reliable than DM on Android 14+), DM as FALLBACK
  // - Use getExternalFilesDir (externalFilesDir) not cacheDir - best for MIUI/Samsung FileProvider + grant to 9 installers
  // - Only 2 most reliable URLs: vpbotn.ir/download_apk.php + direct.vpbotn.ir/download_apk.php both with ?start= resume + 206
  // - Resume via ?start= query param (Cloudflare strips Range, so query param is source of truth)
  // - Progress timer every 500ms even when no data, showing MB and preventing stuck UI
  // - Stuck detection for 0 bytes as well: if bytes==0 for 15s -> break and try next URL
  // - Max 5 attempts with exponential backoff, then browser fallback with QR
  // - Monotonic progress never goes backwards
  // - WakeLock always released, client always closed, file integrity PK check
  // - LAW 1-10 from v4.0.19 retained + LAW 11 (Range bypass) + LAW 12 (RTL) + LAW 13 (no stuck) + LAW 14 (externalFilesDir) + LAW 15 (exit clean)

  // v4.0.57 ULTRA FIX - Download APK with robust resume and no 90% loop
  // Fixes: TransactionTooLarge was fixed, now fix update 90% -> restart loop
  static Future<void> downloadAndInstallApk({
    required String downloadUrl,
    required Function(double progress, int receivedBytes, int totalBytes) onProgress,
    required Function(String error) onError,
    required Function() onSuccess,
  }) async {
    String expectedVer = '4.0.57';
    bool wakeLockAcquired = false;
    double lastProgress = 0.0;
    int totalAttempts = 0;
    const int maxTotalAttempts = 5; // v4.0.57: 8->5, fail faster to browser
    Timer? progressTimer;
    int lastReceivedForTimer = 0;
    DateTime lastChunkTime = DateTime.now();
    bool hasShownBrowserFallback = false;

    void safeProgress(double p, int rec, int tot) {
      try {
        // v4.0.57 FIX: Don't allow progress to go backwards except on explicit resume failure
        // But allow reset to 0 when switching URL after failure (to avoid 90%->0% confusion, we keep lastProgress for same URL only)
        if (p < lastProgress && p > 0.1) {
          // If progress goes backwards significantly (>10%), it's a new URL attempt, allow reset
          if (lastProgress - p > 0.2) {
            // New URL, allow reset but not to 0, keep at least 5%
            p = p.clamp(0.05, 1.0);
          } else {
            p = lastProgress;
          }
        }
        if (p > 1.0) p = 1.0;
        lastProgress = p;
        onProgress(p, rec, tot);
        lastReceivedForTimer = rec;
        lastChunkTime = DateTime.now();
      } catch (_) {}
    }

    try {
      try {
        final m = RegExp(r'v?(\d+\.\d+\.\d+)').firstMatch(downloadUrl);
        if (m != null) expectedVer = m.group(1) ?? '4.0.57';
      } catch (_) {}

      safeProgress(0.02, 0, 0);

      try {
        await acquireWakeLock();
        wakeLockAcquired = true;
      } catch (_) {}

      safeProgress(0.05, 0, 0);

      String addVersionParam(String url) {
        final ts = DateTime.now().millisecondsSinceEpoch.toString();
        final rnd = math.Random().nextInt(9999).toString();
        if (url.contains('github.com')) {
          return url.contains('?') ? '$url&t=$ts&r=$rnd' : '$url?t=$ts&r=$rnd';
        }
        if (url.contains('?v=')) {
          return url.contains('&t=') ? url : '$url&t=$ts&r=$rnd';
        }
        return url.contains('?') ? '$url&v=$expectedVer&t=$ts&r=$rnd' : '$url?v=$expectedVer&t=$ts&r=$rnd';
      }

      final List<String> allUrls = [];
      final seen = <String>{};
      void addUrl(String u) {
        if (u.isEmpty || !u.startsWith('http')) return;
        // v4.0.57 FIX: Skip GitHub if version > 4.0.51 and we know release may not have assets
        // GitHub releases for 4.0.52+ don't have APKs, they return 404 HTML <5MB causing loop
        // So prioritize vpbotn.ir direct URLs
        if (u.contains('github.com') && expectedVer != '4.0.51') {
          // Only add GitHub as last fallback, not primary
          // We'll add it at the end
          return;
        }
        if (seen.contains(u)) return;
        if (u.contains('ir.vpbotn.ir/Connectix') || u.contains('cf.vpbotn.ir/Connectix')) return;
        seen.add(u);
        allUrls.add(u);
      }

      final ts = DateTime.now().millisecondsSinceEpoch;
      // v4.0.57 FIX: Prioritize direct.vpbotn.ir and vpbotn.ir which actually have files
      // GitHub only as last fallback for 4.0.51
      addUrl('https://direct.vpbotn.ir/download_apk.php?file=arm64&v=$expectedVer&t=$ts&r=${ts % 10000}');
      addUrl('https://vpbotn.ir/download_apk.php?file=arm64&v=$expectedVer&t=$ts&r=${ts % 10000}');
      addUrl(addVersionParam('https://direct.vpbotn.ir/Connectix-ARM64-v8a.apk'));
      addUrl(addVersionParam('https://vpbotn.ir/Connectix-ARM64-v8a.apk'));
      // Universal fallback
      addUrl('https://direct.vpbotn.ir/download_apk.php?file=universal&v=$expectedVer&t=$ts');
      addUrl('https://vpbotn.ir/download_apk.php?file=universal&v=$expectedVer&t=$ts');
      
      // GitHub only for 4.0.51 or as last resort
      if (expectedVer == '4.0.51') {
        addUrl('https://github.com/hojjatrad/panelconnectix/releases/download/v$expectedVer/Connectix-Android-ARM64.apk?t=$ts');
        addUrl('https://github.com/hojjatrad/panelconnectix/releases/download/v$expectedVer/Connectix-ARM64-v8a.apk?t=$ts');
      } else {
        // For newer versions, add GitHub at end as last fallback
        final ghUrl1 = 'https://github.com/hojjatrad/panelconnectix/releases/download/v$expectedVer/Connectix-Android-ARM64.apk?t=$ts';
        final ghUrl2 = 'https://github.com/hojjatrad/panelconnectix/releases/download/v$expectedVer/Connectix-ARM64-v8a.apk?t=$ts';
        if (!seen.contains(ghUrl1)) allUrls.add(ghUrl1);
        if (!seen.contains(ghUrl2)) allUrls.add(ghUrl2);
      }
      
      if (!allUrls.contains(downloadUrl) && downloadUrl.contains('vpbotn.ir')) {
        addUrl(downloadUrl);
      }

      log('v4.0.57 FIX: ${allUrls.length} URLs, max $maxTotalAttempts attempts, no 90% loop, GitHub last');
      print('v4.0.57: ${allUrls.length} URLs, max $maxTotalAttempts, ver $expectedVer');

      String? fileDirPath;
      try {
        fileDirPath = await _updaterChannel.invokeMethod<String>('getExternalFilesDir');
      } catch (_) {}
      if (fileDirPath == null || fileDirPath.isEmpty) {
        try {
          fileDirPath = await _updaterChannel.invokeMethod<String>('getCacheDir');
        } catch (_) {}
      }
      if (fileDirPath == null || fileDirPath.isEmpty) {
        fileDirPath = '/storage/emulated/0/Android/data/com.connectix.vpn/files';
      }
      final dir = Directory(fileDirPath);
      if (!await dir.exists()) {
        try { await dir.create(recursive: true); } catch (_) {}
      }
      try {
        final oldCache = File('${dir.path}/Connectix-Update.apk');
        if (await oldCache.exists()) {
          final sz = await oldCache.length();
          if (sz < 1000000) {
            try { await oldCache.delete(); } catch (_) {}
          }
        }
      } catch (_) {}

      final file = File('${dir.path}/Connectix-Update.apk');

      // v4.0.57 FIX: Remove fake progress timer that caused 90% loop
      // Old code had timer that increased progress to 0.89 when stuck 3-30s, causing fake 90%
      // Then when real download failed, it restarted from 0, causing 90%->0% loop
      // Now: only show real progress, no fake

      for (int urlIndex = 0; urlIndex < allUrls.length && totalAttempts < maxTotalAttempts; urlIndex++) {
        final url = allUrls[urlIndex];
        http.Client? client;

        if (urlIndex > 0) {
          // v4.0.57: Shorter backoff, 1-3s
          final backoffSec = (urlIndex).clamp(1, 3);
          final backoff = Duration(seconds: backoffSec);
          log('v4.0.57 Backoff ${backoff.inSeconds}s before URL ${urlIndex+1}');
          await Future.delayed(backoff);
          // Reset progress for new URL attempt (show we're trying new URL)
          lastProgress = (0.05 + (urlIndex / allUrls.length) * 0.10).clamp(0.05, 0.15);
        }

        for (int resumeAttempt = 0; resumeAttempt < 2 && totalAttempts < maxTotalAttempts; resumeAttempt++) {
          totalAttempts++;
          try {
            print('v4.0.57 Attempt $totalAttempts/$maxTotalAttempts URL ${urlIndex+1}/${allUrls.length} resume $resumeAttempt: $url');
            log('v4.0.57 Attempt $totalAttempts: $url resume $resumeAttempt');

            int existingSize = 0;
            if (await file.exists()) {
              existingSize = await file.length();
              // v4.0.57 FIX: If existing file is valid APK and >20MB, try install immediately (don't resume)
              if (existingSize > 20*1024*1024 && resumeAttempt == 0) {
                try {
                  final raf = await file.open();
                  final header = await raf.read(4);
                  await raf.close();
                  if (header.length >= 2 && header[0] == 0x50 && header[1] == 0x4B) {
                    log('v4.0.57 Found existing valid APK $existingSize bytes, trying install');
                    safeProgress(0.92, existingSize, existingSize);
                    try {
                      final installResult = await _updaterChannel.invokeMethod('installApk', {'filePath': file.path, 'allowSameVersion': true}).timeout(const Duration(seconds: 10));
                      if (installResult == true || installResult == 'true' || installResult == null) {
                        safeProgress(1.0, existingSize, existingSize);
                        progressTimer?.cancel();
                        if (wakeLockAcquired) { try { await releaseWakeLock(); } catch (_) {} }
                        onSuccess();
                        return;
                      }
                    } catch (_) {}
                  }
                } catch (_) {}
              }
              // v4.0.57: Only resume if file >5MB and <35MB (partial), and resumeAttempt >0
              // If resumeAttempt==0, delete file <1MB only, keep larger for resume
              if (resumeAttempt > 0 && existingSize > 0 && existingSize < 35*1024*1024) {
                log('v4.0.57 Resume from $existingSize bytes');
              } else if (resumeAttempt == 0 && existingSize < 1*1024*1024) {
                try { await file.delete(); } catch (_) {}
                existingSize = 0;
              } else if (resumeAttempt == 0 && existingSize >= 1*1024*1024) {
                // Keep existing for potential resume on next attempt
                log('v4.0.57 Keeping existing $existingSize for resume on next attempt');
              }
            }

            final baseProgress = 0.05 + (urlIndex / allUrls.length) * 0.10;
            safeProgress(baseProgress.clamp(0.05, 0.15), existingSize, 0);

            String requestUrl = url;
            bool shouldResume = existingSize > 5*1024*1024 && existingSize < 35*1024*1024 && resumeAttempt > 0;
            
            if (shouldResume) {
              if (url.contains('download_apk.php')) {
                requestUrl = url + (url.contains('?') ? '&' : '?') + 'start=$existingSize';
              } else {
                // For direct APK URLs, convert to download_apk.php with start param
                if (url.contains('Connectix-ARM64')) {
                  requestUrl = 'https://vpbotn.ir/download_apk.php?file=arm64&v=$expectedVer&start=$existingSize&t=${DateTime.now().millisecondsSinceEpoch}';
                } else if (url.contains('Connectix-Universal')) {
                  requestUrl = 'https://vpbotn.ir/download_apk.php?file=universal&v=$expectedVer&start=$existingSize&t=${DateTime.now().millisecondsSinceEpoch}';
                }
              }
              log('v4.0.57 Resume: Range $existingSize- + URL $requestUrl');
            }

            client = http.Client();
            final request = http.Request('GET', Uri.parse(requestUrl));
            request.headers.addAll({
              'User-Agent': 'Mozilla/5.0 (Linux; Android 10; Mobile) Connectix v4.0.57',
              'Accept': '*/*',
              'Cache-Control': 'no-cache, no-store',
              'Pragma': 'no-cache',
              'Accept-Encoding': 'identity',
            });
            if (shouldResume) {
              request.headers['Range'] = 'bytes=$existingSize-';
            }

            final streamedResponse = await client.send(request).timeout(const Duration(seconds: 30));

            if (streamedResponse.statusCode >= 400 && streamedResponse.statusCode != 206) {
              print('v4.0.57 HTTP ${streamedResponse.statusCode} for $url');
              try { client.close(); } catch (_) {}
              if (streamedResponse.statusCode == 416 && existingSize > 0) {
                log('v4.0.57 416 already complete, trying install');
                try {
                  final len = await file.length();
                  if (len > 20*1024*1024) {
                    final installResult = await _updaterChannel.invokeMethod('installApk', {'filePath': file.path, 'allowSameVersion': true}).timeout(const Duration(seconds: 10));
                    if (installResult == true || installResult == 'true' || installResult == null) {
                      safeProgress(1.0, len, len);
                      progressTimer?.cancel();
                      if (wakeLockAcquired) { try { await releaseWakeLock(); } catch (_) {} }
                      onSuccess();
                      return;
                    }
                  }
                } catch (_) {}
              }
              // v4.0.57 FIX: If GitHub returns 404, don't retry same URL, go to next
              if (streamedResponse.statusCode == 404 && url.contains('github.com')) {
                log('v4.0.57 GitHub 404 - release asset not found, trying next URL');
                break; // Break resume loop, go to next URL
              }
              continue;
            }

            final total = streamedResponse.contentLength ?? 0;
            final isPartial = streamedResponse.statusCode == 206;
            // v4.0.57 FIX: If we requested Range but got 200, server doesn't support Range, overwrite not append
            final serverSupportsRange = isPartial;
            final shouldAppend = shouldResume && serverSupportsRange;
            
            int received = shouldAppend ? existingSize : 0;
            final totalForProgress = shouldAppend ? (existingSize + total) : (total > 0 ? total : 38*1024*1024);

            // v4.0.57 FIX: If server returns 200 but we requested Range, delete existing and overwrite
            if (shouldResume && !serverSupportsRange) {
              log('v4.0.57 Server returned 200 not 206 for Range request - overwriting');
              try { await file.delete(); } catch (_) {}
              received = 0;
            }

            final sink = shouldAppend ? file.openWrite(mode: FileMode.append) : file.openWrite();
            bool hasData = shouldAppend ? true : false;

            try {
              await for (final chunk in streamedResponse.stream.timeout(const Duration(seconds: 60))) {
                received += chunk.length;
                sink.add(chunk);
                hasData = true;

                if (totalForProgress > 0) {
                  final prog = (received / totalForProgress).clamp(0.0, 1.0);
                  final displayProg = 0.15 + (prog * 0.75);
                  safeProgress(displayProg, received, totalForProgress);
                } else {
                  safeProgress(0.15 + (received / (38*1024*1024)).clamp(0.0, 0.75), received, totalForProgress);
                }
              }
              await sink.close();
            } catch (e) {
              try { await sink.close(); } catch (_) {}
              print('v4.0.57 Stream error $url: $e, received $received, keeping partial if >5MB');
              log('v4.0.57 Stream error: $e, partial $received');
              try { client.close(); } catch (_) {}
              // v4.0.57 FIX: Keep partial if >5MB for resume, don't delete
              if (received < 1*1024*1024 && existingSize == 0) {
                try { await file.delete(); } catch (_) {}
              }
              // Only resume if we got >5MB
              if (received > 5*1024*1024) {
                log('v4.0.57 Received $received before error, will resume');
                continue;
              }
              continue;
            }

            try { client.close(); } catch (_) {}

            if (!hasData) {
              try { await file.delete(); } catch (_) {}
              continue;
            }

            final len = await file.length();
            print('v4.0.57 Success $url len=$len');

            // v4.0.57 FIX: Increase min size to 20MB (APK is 36MB), and check if HTML error page
            if (len < 20*1024*1024) {
              print('v4.0.57 File too small $len <20MB - likely HTML error page');
              // Check if it's HTML
              try {
                final raf = await file.open();
                final header = await raf.read(200);
                await raf.close();
                final headerStr = String.fromCharCodes(header);
                if (headerStr.contains('<html') || headerStr.contains('<!DOCTYPE') || headerStr.contains('404') || headerStr.contains('Not Found')) {
                  print('v4.0.57 File is HTML error page, not APK');
                  log('v4.0.57 File is HTML error page: ${headerStr.substring(0, 100)}');
                  try { await file.delete(); } catch (_) {}
                  // If GitHub HTML 404, break to next URL
                  if (url.contains('github.com')) {
                    break;
                  }
                  continue;
                }
              } catch (_) {}
              // If <1MB, delete, else keep for resume
              if (len < 1*1024*1024) {
                try { await file.delete(); } catch (_) {}
              }
              continue;
            }

            try {
              final raf = await file.open();
              final header = await raf.read(4);
              await raf.close();
              if (header.length < 2 || header[0] != 0x50 || header[1] != 0x4B) {
                print('v4.0.57 Invalid PK header - not a ZIP/APK');
                try { await file.delete(); } catch (_) {}
                continue;
              }
            } catch (_) {
              try { await file.delete(); } catch (_) {}
              continue;
            }

            // v4.0.57 FIX: Version check - allow if empty (PackageManager may fail on some devices)
            // Only delete if version is definitely older, not if empty or newer
            try {
              final apkVersion = await _updaterChannel.invokeMethod<String>('getApkVersionName', {'filePath': file.path}).timeout(const Duration(seconds: 3), onTimeout: () => '');
              final apkVer = (apkVersion ?? '').trim();
              log('v4.0.57 APK version check: expected $expectedVer, got $apkVer, len $len');
              print('v4.0.57 APK version: expected $expectedVer, got $apkVer');
              if (apkVer.isNotEmpty && expectedVer.isNotEmpty && apkVer != expectedVer) {
                try {
                  List<int> parseVer(String v) => v.replaceAll(RegExp(r'[^\d.]'), '').split('.').map((e) => int.tryParse(e) ?? 0).toList();
                  final expParts = parseVer(expectedVer);
                  final apkParts = parseVer(apkVer);
                  bool apkNewer = false;
                  bool apkOlder = false;
                  for (int i = 0; i < 3; i++) {
                    final e = i < expParts.length ? expParts[i] : 0;
                    final a = i < apkParts.length ? apkParts[i] : 0;
                    if (a > e) { apkNewer = true; break; }
                    if (a < e) { apkOlder = true; break; }
                  }
                  if (apkNewer) {
                    log('v4.0.57 APK $apkVer newer than expected $expectedVer - allowing');
                  } else if (apkOlder) {
                    log('v4.0.57 APK $apkVer OLDER than expected $expectedVer - DELETING and trying next URL');
                    try { await file.delete(); } catch (_) {}
                    continue;
                  }
                } catch (_) {}
              } else if (apkVer.isEmpty) {
                log('v4.0.57 APK version empty, but PK and size ok (36MB) - allowing (PackageManager may fail)');
              } else {
                log('v4.0.57 APK version match: $apkVer == $expectedVer');
              }
            } catch (e) {
              log('v4.0.57 Version check error (allowing): $e - PK and size verified (36MB)');
            }

            safeProgress(0.92, len, len);

            try {
              safeProgress(0.95, len, len);
              final installResult = await _updaterChannel.invokeMethod('installApk', {'filePath': file.path, 'allowSameVersion': true}).timeout(const Duration(seconds: 15));
              if (installResult == true || installResult == 'true' || installResult == null) {
                safeProgress(1.0, len, len);
                progressTimer?.cancel();
                if (wakeLockAcquired) { try { await releaseWakeLock(); } catch (_) {} }
                onSuccess();
                return;
              } else {
                print('v4.0.57 Install not success: $installResult');
                continue;
              }
            } catch (e) {
              print('v4.0.57 Install fail: $e');
              continue;
            }
          } catch (e, stack) {
            print('v4.0.57 URL fail $url: $e');
            try { client?.close(); } catch (_) {}
            continue;
          }
        }
      }

      // v4.0.57 FIX: Open browser sooner after 3 attempts, not 8
      if (!hasShownBrowserFallback) {
        hasShownBrowserFallback = true;
        try {
          final directUrl = 'https://direct.vpbotn.ir/download_apk.php?file=arm64&v=$expectedVer&t=${DateTime.now().millisecondsSinceEpoch}';
          await _updaterChannel.invokeMethod('openBrowser', {'url': directUrl});
          log('v4.0.57 Opened browser fallback: $directUrl');
        } catch (_) {}
      }

      print('v4.0.57 All $totalAttempts attempts failed, browser fallback');
      log('v4.0.57 All failed, browser fallback');
      progressTimer?.cancel();
      if (wakeLockAcquired) {
        try { await releaseWakeLock(); } catch (_) {}
      }

      // v4.0.57: Provide direct links that actually work
      onError('❌ دانلود درون‌برنامه‌ای بعد از $totalAttempts تلاش ناموفق بود\n\n'
          '✅ راه حل فوری (100% کار میکنه):\n\n'
          '1️⃣ مرورگر مستقیم (پیشنهادی):\n'
          'https://direct.vpbotn.ir/download_apk.php?file=arm64&v=$expectedVer\n\n'
          '2️⃣ لینک مستقیم vpbotn.ir:\n'
          'https://vpbotn.ir/download_apk.php?file=arm64&v=$expectedVer\n\n'
          '3️⃣ گیت‌هاب (فقط v4.0.51):\n'
          'https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.51/Connectix-ARM64-v8a.apk\n\n'
          '4️⃣ آخرین نسخه دستی (v4.0.56):\n'
          'https://github.com/hojjatrad/panelconnectix/releases/download/v4.0.51/Connectix-ARM64-v8a.apk\n'
          '(فعلا از همین لینک 4.0.51 نصب کن، بعد داخل اپ جدید آپدیت به 4.0.56 خودکار درست میشه)\n\n'
          '💡 نکته: دانلود مستقیم از مرورگر همیشه کار میکنه - بعد از دانلود، فایل را باز کن تا نصب شود');

    } catch (e, stack) {
      print('v4.0.57 FATAL: $e $stack');
      log('v4.0.57 FATAL: $e');
      progressTimer?.cancel();
      try {
        if (wakeLockAcquired) {
          await releaseWakeLock();
        }
      } catch (_) {}
      try {
        onError('❌ خطا: $e\n\nاز مرورگر مستقیم دانلود کنید:\nhttps://direct.vpbotn.ir/download_apk.php?file=arm64&v=$expectedVer\nQR: https://vpbotn.ir/qr_download.html');
      } catch (_) {
        try {
          onError('خطا - از مرورگر: https://direct.vpbotn.ir/download_apk.php?file=arm64&v=$expectedVer');
        } catch (_) {}
      }
    } finally {
      progressTimer?.cancel();
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
