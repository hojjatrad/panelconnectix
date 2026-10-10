import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/server_model.dart';
import '../services/api_service.dart';
import 'dashboard_screen.dart';
import 'dart:convert';

class LoginScreen extends StatefulWidget {
  const LoginScreen({Key? key}) : super(key: key);

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();
  final _panelUrlController = TextEditingController(text: 'https://vpbotn.ir');
  bool _isLoading = false;
  String _errorMessage = '';
  List<dynamic> _existingAccounts = [];

  @override
  void initState() {
    super.initState();
    _loadSavedCredentials();
    _checkUpdateBeforeLogin();
  }

  void _loadSavedCredentials() async {
    final prefs = await SharedPreferences.getInstance();
    final u = prefs.getString('saved_username');
    final p = prefs.getString('saved_password');
    final panel = prefs.getString('api_base_url_working') ?? prefs.getString('api_base_url') ?? 'https://vpbotn.ir';
    if (u != null && p != null) {
      _usernameController.text = u;
      _passwordController.text = p;
    }
    _panelUrlController.text = panel;
    // Load existing accounts for quick switch
    try {
      final accStr = prefs.getString('multi_accounts');
      if (accStr != null && accStr.isNotEmpty) {
        final List list = jsonDecode(accStr);
        if (mounted) setState(() => _existingAccounts = list);
      }
    } catch (_) {}
  }

  bool _isVersionNewer(String latest, String current) {
    try {
      List<int> parse(String v) => v
          .replaceAll(RegExp(r'[^\d.]'), '')
          .split('.')
          .map((e) => int.tryParse(e) ?? 0)
          .toList();
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

  void _checkUpdateBeforeLogin() async {
    await Future.delayed(const Duration(milliseconds: 700));
    try {
      final updateData = await ApiService.checkAppUpdate();
      if (!mounted) return;
      if (updateData == null) return;
      final latest = (updateData['latest_version'] ?? '').toString();
      if (updateData['has_update'] == true &&
          _isVersionNewer(latest, DashboardScreen.currentAppVersion)) {
        _showPreLoginUpdateDialog(updateData);
      }
    } catch (_) {}
  }

  void _showPreLoginUpdateDialog(Map<String, dynamic> updateData) {
    final latestVer = (updateData['latest_version'] ?? '').toString();
    final changelog = (updateData['changelog'] ?? 'نگارش جدید سامانه منتشر شد.').toString();
    final primary = (updateData['download_url'] ?? '').toString();
    final fallback = (updateData['fallback_url'] ?? '').toString();
    final url = (primary.isNotEmpty && primary.startsWith('http')) ? primary : fallback;
    if (url.isEmpty) return;

    showDialog(
      context: context,
      builder: (ctx) => _PreLoginUpdateDialog(
        version: latestVer,
        changelog: changelog,
        downloadUrl: url,
      ),
    );
  }

  void _openPanelLocationManager() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: const Color(0xFF0F172A),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      builder: (ctx) => const _PanelLocationManagerSheet(),
    );
  }

  void _doLogin() async {
    final u = _usernameController.text.trim();
    final p = _passwordController.text.trim();
    final panelUrl = _panelUrlController.text.trim().isNotEmpty ? _panelUrlController.text.trim() : 'https://vpbotn.ir';

    if (u.isEmpty || p.isEmpty) {
      setState(() {
        _errorMessage = 'لطفا نام کاربری و رمز عبور را وارد فرمایید.';
      });
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = '';
    });

    // v4.0.47 MULTI-SERVICE: Set baseUrl to user-provided panel before login
    ApiService.baseUrl = panelUrl;
    ApiService.baseUrls = [panelUrl, ...ApiService.baseUrls.where((url) => url != panelUrl)];

    final res = await ApiService.login(u, p);

    setState(() {
      _isLoading = false;
    });

    if (res['success'] == true) {
      if (!mounted) return;
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => DashboardScreen(
            client: res['client'],
            branding: res['branding'],
            initialServers: res['servers'] is List<ServerModel> ? res['servers'] : null,
          ),
        ),
      );
    } else {
      setState(() {
        _errorMessage = res['error'] ?? 'نام کاربری یا رمز عبور اشتباه است.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF090D16),
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        actions: [
          // v4.0.15 FIX: Panel Location Manager accessible BEFORE login
          // User reported: panel manager inside dashboard after login, but if panel moved, user can't login to see it
          IconButton(
            icon: Container(
              padding: const EdgeInsets.all(6),
              decoration: BoxDecoration(
                color: const Color(0xFF1E293B),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFF334155)),
              ),
              child: const Icon(Icons.settings_rounded, color: Color(0xFF94A3B8), size: 18),
            ),
            onPressed: _openPanelLocationManager,
            tooltip: 'مدیریت مسیر پنل',
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 28),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  width: 72,
                  height: 72,
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [Color(0xFF9333EA), Color(0xFF4F46E5)],
                    ),
                    borderRadius: BorderRadius.circular(22),
                    boxShadow: [
                      BoxShadow(
                        color: const Color(0xFF9333EA).withOpacity(0.35),
                        blurRadius: 20,
                        offset: const Offset(0, 10),
                      ),
                    ],
                  ),
                  child: const Icon(Icons.bolt, color: Colors.white, size: 40),
                ),
                const SizedBox(height: 18),
                const Text(
                  'Connectix VPN',
                  style: TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.w900,
                    color: Colors.white,
                    letterSpacing: 0.5,
                  ),
                ),
                const SizedBox(height: 6),
                const Text(
                  'ورود اختصاصی با نام کاربری و کلمه عبور',
                  style: TextStyle(fontSize: 12, color: Color(0xFF94A3B8)),
                ),
                const SizedBox(height: 24),
                TextField(
                  controller: _usernameController,
                  style: const TextStyle(color: Colors.white, fontSize: 14),
                  decoration: InputDecoration(
                    filled: true,
                    fillColor: const Color(0xFF0F172A),
                    hintText: 'نام کاربری (Username)',
                    hintStyle: const TextStyle(color: Color(0xFF64748B), fontSize: 13),
                    prefixIcon: const Icon(Icons.person_outline, color: Color(0xFF94A3B8)),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(16),
                      borderSide: const BorderSide(color: Color(0xFF1E293B)),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(16),
                      borderSide: const BorderSide(color: Color(0xFF9333EA), width: 1.5),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: _passwordController,
                  obscureText: true,
                  style: const TextStyle(color: Colors.white, fontSize: 14),
                  decoration: InputDecoration(
                    filled: true,
                    fillColor: const Color(0xFF0F172A),
                    hintText: 'کلمه عبور (Password)',
                    hintStyle: const TextStyle(color: Color(0xFF64748B), fontSize: 13),
                    prefixIcon: const Icon(Icons.lock_outline, color: Color(0xFF94A3B8)),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(16),
                      borderSide: const BorderSide(color: Color(0xFF1E293B)),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(16),
                      borderSide: const BorderSide(color: Color(0xFF9333EA), width: 1.5),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                // v4.0.47 MULTI-SERVICE: Panel URL field
                TextField(
                  controller: _panelUrlController,
                  style: const TextStyle(color: Colors.white, fontSize: 13, fontFamily: 'monospace'),
                  decoration: InputDecoration(
                    filled: true,
                    fillColor: const Color(0xFF0F172A),
                    hintText: 'آدرس پنل (Panel URL) - مثلا https://vpbotn.ir',
                    hintStyle: const TextStyle(color: Color(0xFF475569), fontSize: 11),
                    prefixIcon: const Icon(Icons.link_rounded, color: Color(0xFF38BDF8)),
                    suffixIcon: _existingAccounts.isNotEmpty
                        ? IconButton(
                            icon: const Icon(Icons.switch_account_rounded, color: Color(0xFF8B5CF6), size: 20),
                            onPressed: () {
                              showModalBottomSheet(
                                context: context,
                                backgroundColor: const Color(0xFF0F172A),
                                shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
                                builder: (ctx) => Padding(
                                  padding: const EdgeInsets.all(16),
                                  child: Column(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      const Text('حساب‌های ذخیره شده', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14)),
                                      const SizedBox(height: 12),
                                      ..._existingAccounts.map((acc) {
                                        final username = acc['username'] ?? '';
                                        final panel = acc['panelUrl'] ?? acc['panel_url'] ?? '';
                                        return ListTile(
                                          leading: Text(acc['avatarEmoji'] ?? '👤', style: const TextStyle(fontSize: 20)),
                                          title: Text(username, style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold)),
                                          subtitle: Text(panel, style: const TextStyle(color: Color(0xFF64748B), fontSize: 10)),
                                          onTap: () {
                                            _usernameController.text = username;
                                            _panelUrlController.text = panel;
                                            Navigator.pop(ctx);
                                          },
                                        );
                                      }).toList(),
                                    ],
                                  ),
                                ),
                              );
                            },
                          )
                        : null,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(16),
                      borderSide: const BorderSide(color: Color(0xFF1E293B)),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(16),
                      borderSide: const BorderSide(color: Color(0xFF38BDF8), width: 1.5),
                    ),
                  ),
                ),
                const SizedBox(height: 6),
                const Text('💡 می‌توانید از پنل‌های مختلف حساب اضافه کنید - نامحدود multi-service', style: TextStyle(color: Color(0xFF475569), fontSize: 10)),
                const SizedBox(height: 12),
                if (_errorMessage.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Text(
                      _errorMessage,
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: Color(0xFFF43F5E), fontSize: 12),
                    ),
                  ),
                SizedBox(
                  width: double.infinity,
                  height: 52,
                  child: ElevatedButton(
                    onPressed: _isLoading ? null : _doLogin,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF9333EA),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      elevation: 8,
                      shadowColor: const Color(0xFF9333EA).withOpacity(0.4),
                    ),
                    child: _isLoading
                        ? const SizedBox(
                            width: 22,
                            height: 22,
                            child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5),
                          )
                        : const Text(
                            'ورود به حساب کاربری',
                            style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: Colors.white),
                          ),
                  ),
                ),
                const SizedBox(height: 16),
                // v4.0.15: Panel location manager button on login screen
                InkWell(
                  onTap: _openPanelLocationManager,
                  borderRadius: BorderRadius.circular(12),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                    decoration: BoxDecoration(
                      color: const Color(0xFF0F172A),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFF1E293B)),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: const [
                        Icon(Icons.travel_explore_rounded, color: Color(0xFF38BDF8), size: 16),
                        SizedBox(width: 6),
                        Text('مدیریت هوشمند مسیر پنل (قبل از ورود)', style: TextStyle(color: Color(0xFF94A3B8), fontSize: 11)),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 24),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Text('نیاز به راهنمایی دارید؟ ', style: TextStyle(color: Color(0xFF64748B), fontSize: 12)),
                    GestureDetector(
                      onTap: () {},
                      child: const Text(
                        'ارتباط با پشتیبانی',
                        style: TextStyle(color: Color(0xFFA855F7), fontSize: 12, fontWeight: FontWeight.bold),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                Text(
                  'نسخه ${DashboardScreen.currentAppVersion}',
                  style: const TextStyle(color: Color(0xFF475569), fontSize: 10),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _PanelLocationManagerSheet extends StatefulWidget {
  const _PanelLocationManagerSheet();

  @override
  State<_PanelLocationManagerSheet> createState() => _PanelLocationManagerSheetState();
}

class _PanelLocationManagerSheetState extends State<_PanelLocationManagerSheet> {
  String _currentBase = '';
  List<String> _allBases = [];
  bool _isResolving = false;
  bool _isTesting = false;
  String _statusText = '';
  Map<String, dynamic>? _panelInfo;
  final _manualUrlController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _loadCurrent();
    _loadPanelInfo();
  }

  void _loadCurrent() {
    setState(() {
      _currentBase = ApiService.baseUrl;
      _allBases = ApiService.getOrderedBaseUrls();
    });
  }

  void _loadPanelInfo() async {
    try {
      final info = await ApiService.getPanelLocationInfo();
      if (mounted && info != null) {
        setState(() {
          _panelInfo = info;
        });
      }
    } catch (_) {}
  }

  void _testCurrent() async {
    setState(() {
      _isTesting = true;
      _statusText = 'در حال تست ${_allBases.length} آدرس...';
    });
    try {
      final resolved = await ApiService.resolvePanelLocation();
      if (mounted) {
        setState(() {
          _isTesting = false;
          if (resolved != null) {
            _statusText = '✅ بهترین مسیر پیدا شد: $resolved';
            _currentBase = resolved;
            _allBases = ApiService.getOrderedBaseUrls();
          } else {
            _statusText = '❌ هیچ مسیر فعالی پیدا نشد. اینترنت را چک کنید یا دستی وارد کنید.';
          }
        });
        _loadPanelInfo();
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isTesting = false;
          _statusText = 'خطا: $e';
        });
      }
    }
  }

  void _resolveIntelligent() async {
    setState(() {
      _isResolving = true;
      _statusText = '🔍 در حال اسکن هوشمند 6 لایه‌ای (well-known, panel-location, brute-force, GitHub)...';
    });
    try {
      final resolved = await ApiService.resolvePanelLocation();
      if (mounted) {
        setState(() {
          _isResolving = false;
          if (resolved != null) {
            _statusText = '✅ مسیر جدید با موفقیت تنظیم شد: $resolved\nحالا میتوانید با یوزر و پسورد وارد شوید.';
            _currentBase = resolved;
            _allBases = ApiService.getOrderedBaseUrls();
          } else {
            _statusText = '❌ هیچ مسیر فعالی پیدا نشد. دستی وارد کنید یا با پشتیبانی تماس بگیرید.';
          }
        });
        _loadPanelInfo();
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isResolving = false;
          _statusText = 'خطا در اسکن: $e';
        });
      }
    }
  }

  void _saveManual() async {
    final url = _manualUrlController.text.trim();
    if (url.isEmpty || !url.startsWith('http')) {
      setState(() {
        _statusText = '❌ آدرس باید با http شروع شود (مثلا https://vpbotn.ir)';
      });
      return;
    }
    setState(() {
      _statusText = 'در حال ذخیره و تست $url...';
    });
    try {
      final ok = await ApiService.updatePanelUrlFromQr(url);
      if (mounted) {
        setState(() {
          if (ok) {
            _statusText = '✅ آدرس دستی ذخیره شد: $url\nحالا لاگین کنید.';
            _currentBase = url;
            _allBases = ApiService.getOrderedBaseUrls();
          } else {
            _statusText = '❌ آدرس تست نشد ولی ذخیره شد: $url';
            _currentBase = url;
          }
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _statusText = 'خطا: $e';
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Center(
            child: Container(
              width: 44,
              height: 4,
              decoration: BoxDecoration(
                color: const Color(0xFF334155),
                borderRadius: BorderRadius.circular(4),
              ),
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: const Color(0xFF38BDF8).withOpacity(0.15),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(Icons.travel_explore_rounded, color: Color(0xFF38BDF8), size: 22),
              ),
              const SizedBox(width: 10),
              const Expanded(
                child: Text(
                  'مدیریت هوشمند مسیر پنل',
                  style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: const Color(0xFF10B981).withOpacity(0.15),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: const Color(0xFF10B981)),
                ),
                child: const Text('قبل از ورود', style: TextStyle(color: Color(0xFF10B981), fontSize: 9, fontWeight: FontWeight.bold)),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: const Color(0xFF1E293B),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: const Color(0xFF334155)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Icon(Icons.link_rounded, color: Color(0xFF94A3B8), size: 14),
                    const SizedBox(width: 6),
                    const Text('مسیر فعلی:', style: TextStyle(color: Color(0xFF94A3B8), fontSize: 11)),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        _currentBase,
                        style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Text('تمام مسیرها (${_allBases.length}): ${_allBases.join(', ')}', style: const TextStyle(color: Color(0xFF64748B), fontSize: 9), maxLines: 2, overflow: TextOverflow.ellipsis),
                if (_panelInfo != null) ...[
                  const SizedBox(height: 8),
                  Text('پنل: ${_panelInfo!['panel_url'] ?? ''} نسخه ${_panelInfo!['version'] ?? ''}', style: const TextStyle(color: Color(0xFF38BDF8), fontSize: 10)),
                ],
              ],
            ),
          ),
          const SizedBox(height: 14),
          if (_statusText.isNotEmpty)
            Container(
              padding: const EdgeInsets.all(10),
              margin: const EdgeInsets.only(bottom: 12),
              decoration: BoxDecoration(
                color: const Color(0xFF1E293B),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Text(_statusText, style: const TextStyle(color: Color(0xFFCBD5E1), fontSize: 11, height: 1.5)),
            ),
          Row(
            children: [
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: _isTesting ? null : _testCurrent,
                  style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF334155), padding: const EdgeInsets.symmetric(vertical: 12), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
                  icon: _isTesting ? const SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.wifi_tethering_rounded, size: 16),
                  label: const Text('تست مسیرها', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: _isResolving ? null : _resolveIntelligent,
                  style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF6366F1), padding: const EdgeInsets.symmetric(vertical: 12), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
                  icon: _isResolving ? const SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.auto_fix_high_rounded, size: 16),
                  label: const Text('اسکن هوشمند', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          const Text('یا آدرس پنل را دستی وارد کنید (از پشتیبانی بگیرید):', style: TextStyle(color: Color(0xFF94A3B8), fontSize: 11)),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _manualUrlController,
                  style: const TextStyle(color: Colors.white, fontSize: 12),
                  decoration: InputDecoration(
                    filled: true,
                    fillColor: const Color(0xFF0F172A),
                    hintText: 'https://vpbotn.ir',
                    hintStyle: const TextStyle(color: Color(0xFF475569), fontSize: 11),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFF1E293B))),
                    focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFF38BDF8))),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              ElevatedButton(
                onPressed: _saveManual,
                style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF10B981), padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
                child: const Text('ذخیره', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: const Color(0xFF1E1B4B).withOpacity(0.5),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFF4338CA).withOpacity(0.3)),
            ),
            child: const Text(
              '💡 اگر پنل جابجا شده و با یوزر/پسورد وارد نمیشوید، اینجا مسیر جدید را تنظیم کنید. این قسمت قبل از ورود در دسترس است تا مشکل chicken-egg حل شود. میتوانید از پشتیبانی QR کد بگیرید یا آدرس جدید را دستی وارد کنید.',
              style: TextStyle(color: Color(0xFFA5B4FC), fontSize: 10, height: 1.6),
            ),
          ),
        ],
      ),
    );
  }
}

class _PreLoginUpdateDialog extends StatefulWidget {
  final String version;
  final String changelog;
  final String downloadUrl;
  const _PreLoginUpdateDialog({
    required this.version,
    required this.changelog,
    required this.downloadUrl,
  });

  @override
  State<_PreLoginUpdateDialog> createState() => _PreLoginUpdateDialogState();
}

class _PreLoginUpdateDialogState extends State<_PreLoginUpdateDialog> {
  bool _downloading = false;
  bool _installing = false;
  bool _done = false;
  bool _failed = false;
  double _progress = 0.0;
  int _receivedBytes = 0;
  int _totalBytes = 0;
  String _errorMsg = '';

  Future<void> _downloadAndInstall() async {
    if (_downloading || _done) return;
    setState(() {
      _downloading = true;
      _failed = false;
      _progress = 0;
    });
    await ApiService.downloadAndInstallApk(
      downloadUrl: widget.downloadUrl,
      onProgress: (p, recv, total) {
        if (mounted) {
          setState(() {
            _progress = p;
            _receivedBytes = recv;
            _totalBytes = total;
          });
        }
      },
      onError: (err) {
        if (mounted) {
          setState(() {
            _downloading = false;
            _failed = true;
            _errorMsg = err;
          });
        }
      },
      onSuccess: () {
        if (mounted) {
          setState(() {
            _downloading = false;
            _installing = true;
          });
        }
      },
    );
  }

  String _fmtBytes(int b) {
    if (b <= 0) return '0 MB';
    final mb = b / (1024 * 1024);
    return mb >= 1 ? '${mb.toStringAsFixed(1)} MB' : '${(b / 1024).toStringAsFixed(0)} KB';
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      backgroundColor: const Color(0xFF0F172A),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(24),
        side: const BorderSide(color: Color(0xFF4F46E5), width: 1.5),
      ),
      title: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: const Color(0xFF10B981).withOpacity(0.15),
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(Icons.system_update_rounded, color: Color(0xFF10B981), size: 24),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              'بروزرسانی Connectix (${widget.version})',
              style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.bold),
            ),
          ),
        ],
      ),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: const Color(0xFF1E293B),
              borderRadius: BorderRadius.circular(8),
            ),
            child: const Text(
              '✨ نگارش جدید برای دریافت آماده است',
              style: TextStyle(color: Color(0xFFA5B4FC), fontSize: 11, fontWeight: FontWeight.bold),
            ),
          ),
          const SizedBox(height: 10),
          Flexible(
            child: SingleChildScrollView(
              child: Text(
                widget.changelog,
                style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 12, height: 1.6),
              ),
            ),
          ),
          if (_downloading) ...[
            const SizedBox(height: 16),
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: LinearProgressIndicator(
                value: _progress,
                minHeight: 8,
                backgroundColor: const Color(0xFF1E293B),
                color: const Color(0xFF9333EA),
              ),
            ),
            const SizedBox(height: 6),
            Text(
              _totalBytes > 0
                  ? 'در حال دانلود: ${_fmtBytes(_receivedBytes)} از ${_fmtBytes(_totalBytes)}'
                  : 'در حال دانلود...',
              style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 11),
            ),
          ],
          if (_failed && _errorMsg.isNotEmpty) ...[
            const SizedBox(height: 12),
            Text(
              _errorMsg,
              style: const TextStyle(color: Color(0xFFF87171), fontSize: 12),
            ),
          ],
          if (_installing) ...[
            const SizedBox(height: 12),
            const Row(
              children: [
                SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFF10B981)),
                ),
                SizedBox(width: 8),
                Expanded(
                  child: Text(
                    '✅ دانلود کامل شد. در حال باز کردن نصاب...\nاگر پنجره باز نشد، دکمه‌های زیر را بزنید.',
                    style: TextStyle(color: Color(0xFF10B981), fontSize: 11, height: 1.4),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            const Text(
              '⚠️ در شیائومی/سامسونگ: اگر پنجره باز نشد، تنظیمات → حریم خصوصی → نصب برنامه‌های ناشناخته → Connectix را فعال کنید',
              style: TextStyle(color: Color(0xFFF59E0B), fontSize: 10, height: 1.4),
            ),
          ],
        ],
      ),
      actions: [
        if (_installing) ...[
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('بستن', style: TextStyle(color: Color(0xFF94A3B8))),
          ),
          ElevatedButton.icon(
            onPressed: () async {
              setState(() {
                _installing = false;
                _downloading = true;
                _progress = 0;
              });
              await ApiService.downloadAndInstallApk(
                downloadUrl: widget.downloadUrl,
                onProgress: (p, r, t) {
                  if (mounted) setState(() {_progress = p; _receivedBytes = r; _totalBytes = t;});
                },
                onError: (e) {
                  if (mounted) setState(() {_downloading = false; _failed = true; _errorMsg = e;});
                },
                onSuccess: () {
                  if (mounted) setState(() {_downloading = false; _installing = true;});
                },
              );
            },
            style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF10B981)),
            icon: const Icon(Icons.install_mobile_rounded, size: 16),
            label: const Text('تلاش مجدد نصب', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
          ),
        ],
        if (!_downloading && !_installing)
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('بعدا', style: TextStyle(color: Color(0xFF94A3B8))),
          ),
        if (!_downloading && !_installing)
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: _downloadAndInstall,
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF9333EA),
                padding: const EdgeInsets.symmetric(vertical: 13),
              ),
              icon: const Icon(Icons.download_rounded, size: 18),
              label: Text(
                _failed ? 'تلاش مجدد برای دانلود و نصب' : 'دانلود و نصب',
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
            ),
          ),
      ],
    );
  }
}
