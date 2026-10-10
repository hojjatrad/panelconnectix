import 'dart:convert';
import 'dart:math' as math;
import 'package:shared_preferences/shared_preferences.dart';
import '../models/account_model.dart';

class AccountManager {
  static const String _kAccounts = 'multi_accounts';
  static const String _kActiveId = 'active_account_id';
  static const String _kUnified = 'unified_servers_enabled';
  static const String _kPasswords = 'multi_account_passwords'; // fallback if secure storage not available

  // Simple in-memory cache for passwords (since we can't use flutter_secure_storage without adding dep, we use obfuscated prefs)
  // v4.0.47: Use base64 + reverse as light obfuscation, plus optional secure storage channel if available
  static String _obfuscate(String s) {
    try {
      final reversed = s.split('').reversed.join();
      return base64Encode(utf8.encode(reversed));
    } catch (_) { return s; }
  }
  static String _deobfuscate(String s) {
    try {
      final decoded = utf8.decode(base64Decode(s));
      return decoded.split('').reversed.join();
    } catch (_) { return s; }
  }

  static Future<List<VpnAccount>> getAccounts() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final str = prefs.getString(_kAccounts);
      if (str == null || str.isEmpty) return [];
      final List list = jsonDecode(str);
      return list.map((e) => VpnAccount.fromJson(Map<String, dynamic>.from(e))).toList();
    } catch (_) { return []; }
  }

  static Future<void> _saveAccounts(List<VpnAccount> accounts) async {
    final prefs = await SharedPreferences.getInstance();
    final list = accounts.map((e) => e.toJson()).toList();
    await prefs.setString(_kAccounts, jsonEncode(list));
  }

  static Future<Map<String, String>> _getPasswordsMap() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final str = prefs.getString(_kPasswords);
      if (str == null || str.isEmpty) return {};
      final Map<String, dynamic> map = jsonDecode(str);
      return map.map((k, v) => MapEntry(k, v.toString()));
    } catch (_) { return {}; }
  }

  static Future<void> _savePasswordsMap(Map<String, String> map) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kPasswords, jsonEncode(map));
  }

  static Future<String> getPassword(String accountId) async {
    final map = await _getPasswordsMap();
    final obf = map[accountId] ?? '';
    if (obf.isEmpty) return '';
    return _deobfuscate(obf);
  }

  static Future<void> setPassword(String accountId, String password) async {
    final map = await _getPasswordsMap();
    map[accountId] = _obfuscate(password);
    await _savePasswordsMap(map);
  }

  static Future<VpnAccount?> getActiveAccount() async {
    final accounts = await getAccounts();
    if (accounts.isEmpty) return null;
    final prefs = await SharedPreferences.getInstance();
    final activeId = prefs.getString(_kActiveId);
    if (activeId == null || activeId.isEmpty) {
      // return most recently used
      accounts.sort((a, b) => b.lastUsed.compareTo(a.lastUsed));
      return accounts.first;
    }
    final found = accounts.where((a) => a.id == activeId);
    if (found.isNotEmpty) return found.first;
    return accounts.first;
  }

  static Future<void> setActiveAccount(String accountId) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kActiveId, accountId);
    // update lastUsed
    final accounts = await getAccounts();
    final updated = accounts.map((a) => a.id == accountId ? a.copyWith(lastUsed: DateTime.now().millisecondsSinceEpoch) : a).toList();
    await _saveAccounts(updated);
  }

  static Future<VpnAccount> addOrUpdateAccount({
    required String username,
    required String password,
    required String panelUrl,
    String displayName = '',
    String colorHex = '#8B5CF6',
    String avatarEmoji = '👤',
    int serverCount = 0,
    String planTitle = '',
  }) async {
    final accounts = await getAccounts();
    final id = '${username.toLowerCase()}@${Uri.tryParse(panelUrl)?.host ?? panelUrl}';
    final existingIdx = accounts.indexWhere((a) => a.id == id);
    VpnAccount account;
    if (existingIdx >= 0) {
      account = accounts[existingIdx].copyWith(
        lastUsed: DateTime.now().millisecondsSinceEpoch,
        serverCount: serverCount > 0 ? serverCount : accounts[existingIdx].serverCount,
        planTitle: planTitle.isNotEmpty ? planTitle : accounts[existingIdx].planTitle,
        displayName: displayName.isNotEmpty ? displayName : accounts[existingIdx].displayName,
        colorHex: colorHex != '#8B5CF6' ? colorHex : accounts[existingIdx].colorHex,
        avatarEmoji: avatarEmoji != '👤' ? avatarEmoji : accounts[existingIdx].avatarEmoji,
        panelUrl: panelUrl,
      );
      accounts[existingIdx] = account;
    } else {
      account = VpnAccount(
        id: id,
        username: username,
        panelUrl: panelUrl,
        displayName: displayName,
        colorHex: colorHex,
        avatarEmoji: avatarEmoji,
        lastUsed: DateTime.now().millisecondsSinceEpoch,
        serverCount: serverCount,
        planTitle: planTitle,
      );
      accounts.add(account);
    }
    await _saveAccounts(accounts);
    await setPassword(id, password);
    await setActiveAccount(id);

    // Also sync legacy single-account prefs for backward compat
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('saved_username', username);
    await prefs.setString('saved_password', password);
    await prefs.setString('api_base_url_working', panelUrl);
    await prefs.setString('api_base_url', panelUrl);

    return account;
  }

  static Future<void> removeAccount(String accountId) async {
    final accounts = await getAccounts();
    accounts.removeWhere((a) => a.id == accountId);
    await _saveAccounts(accounts);
    final map = await _getPasswordsMap();
    map.remove(accountId);
    await _savePasswordsMap(map);
    final prefs = await SharedPreferences.getInstance();
    final active = prefs.getString(_kActiveId);
    if (active == accountId) {
      if (accounts.isNotEmpty) {
        accounts.sort((a, b) => b.lastUsed.compareTo(a.lastUsed));
        await prefs.setString(_kActiveId, accounts.first.id);
      } else {
        await prefs.remove(_kActiveId);
      }
    }
  }

  static Future<void> updateAccountMeta(String accountId, {String? displayName, String? colorHex, String? avatarEmoji}) async {
    final accounts = await getAccounts();
    final idx = accounts.indexWhere((a) => a.id == accountId);
    if (idx < 0) return;
    accounts[idx] = accounts[idx].copyWith(
      displayName: displayName,
      colorHex: colorHex,
      avatarEmoji: avatarEmoji,
    );
    await _saveAccounts(accounts);
  }

  static Future<bool> isUnifiedEnabled() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool(_kUnified) ?? false;
  }

  static Future<void> setUnifiedEnabled(bool v) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_kUnified, v);
  }

  // Generate random color for new account
  static String randomColor() {
    final colors = ['#8B5CF6','#06B6D4','#10B981','#F59E0B','#EF4444','#EC4899','#6366F1','#14B8A6','#F97316','#8B5CF6'];
    return colors[math.Random().nextInt(colors.length)];
  }

  static String randomAvatar(String username) {
    final avatars = ['🚀','🛡️','⚡','🌐','🔒','💎','🎯','🔥','✨','🌟','🎨','🦊','🐯','🦁','🦄'];
    if (username.isEmpty) return avatars[0];
    final idx = username.codeUnits.fold(0, (a, b) => a + b) % avatars.length;
    return avatars[idx];
  }

  // For server cache per account
  static String _serverCacheKey(String accountId) => 'cached_servers_$accountId';
  static String _clientCacheKey(String accountId) => 'cached_client_$accountId';
  static String _brandingCacheKey(String accountId) => 'cached_branding_$accountId';
  static String _tokenCacheKey(String accountId) => 'auth_token_$accountId';

  static Future<void> saveAccountServerCache(String accountId, List<dynamic> serversJson) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_serverCacheKey(accountId), jsonEncode(serversJson));
  }

  static Future<List<dynamic>> getAccountServerCache(String accountId) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final str = prefs.getString(_serverCacheKey(accountId));
      if (str == null || str.isEmpty) return [];
      return jsonDecode(str) as List<dynamic>;
    } catch (_) { return []; }
  }

  static Future<void> saveAccountClient(String accountId, Map<String, dynamic> clientJson) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_clientCacheKey(accountId), jsonEncode(clientJson));
  }

  static Future<Map<String, dynamic>?> getAccountClient(String accountId) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final str = prefs.getString(_clientCacheKey(accountId));
      if (str == null) return null;
      return jsonDecode(str) as Map<String, dynamic>;
    } catch (_) { return null; }
  }

  static Future<void> saveAccountBranding(String accountId, Map<String, dynamic> brandingJson) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_brandingCacheKey(accountId), jsonEncode(brandingJson));
  }

  static Future<void> saveAccountToken(String accountId, String token) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_tokenCacheKey(accountId), token);
  }

  static Future<String?> getAccountToken(String accountId) async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_tokenCacheKey(accountId));
  }
}
