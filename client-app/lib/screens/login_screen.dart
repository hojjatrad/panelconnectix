import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/server_model.dart';
import '../services/api_service.dart';
import 'dashboard_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({Key? key}) : super(key: key);

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _isLoading = false;
  String _errorMessage = '';

  @override
  void initState() {
    super.initState();
    _loadSavedCredentials();
    // 3.3.9: offer the update BEFORE login — the app must be updatable even
    // when the panel is unreachable for login (no chicken-and-egg: you no
    // longer need a working login to install the newest build).
    _checkUpdateBeforeLogin();
  }

  void _loadSavedCredentials() async {
    final prefs = await SharedPreferences.getInstance();
    final u = prefs.getString('saved_username');
    final p = prefs.getString('saved_password');
    if (u != null && p != null) {
      _usernameController.text = u;
      _passwordController.text = p;
    }
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
    final changelog = (updateData['changelog'] ?? '• نگارش جدید سامانه منتشر شد.').toString();
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

  void _doLogin() async {
    final u = _usernameController.text.trim();
    final p = _passwordController.text.trim();

    if (u.isEmpty || p.isEmpty) {
      setState(() {
        _errorMessage = 'لطفاً نام کاربری و رمز عبور را وارد فرمایید.';
      });
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = '';
    });

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
      // 3.3.9: no settings button — the panel address is fixed (build-time
      // constant), so the "set panel address" section is not shown at all.
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
      ),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 28),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                // Logo (3.3.9: no hidden tap gesture — server address is fixed)
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

                // Username input
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

                // Password input
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
                const SizedBox(height: 12),

                // Error message
                if (_errorMessage.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Text(
                      _errorMessage,
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: Color(0xFFF43F5E), fontSize: 12),
                    ),
                  ),

                // Login button
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
                const SizedBox(height: 24),

                // Support footer
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

/// 3.3.9: Update dialog shown on the LOGIN screen (before authentication).
/// Lets customers install the newest build even when the panel is unreachable
/// for login — the check-update endpoint is public, so no token is needed.
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
                    'برای ادامه، نصب را در پنجره‌ای که باز شد تأیید فرمایید.',
                    style: TextStyle(color: Color(0xFF10B981), fontSize: 12),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
      actions: [
        if (!_downloading && !_installing)
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('بعداً', style: TextStyle(color: Color(0xFF94A3B8))),
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
              label: const Text(
                _failed ? 'تلاش مجدد برای دانلود و نصب' : 'دانلود و نصب',
                style: TextStyle(fontWeight: FontWeight.bold),
              ),
            ),
          ),
      ],
    );
  }
}
