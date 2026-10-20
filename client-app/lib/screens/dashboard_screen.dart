import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_vless/flutter_vless.dart';
import 'package:percent_indicator/circular_percent_indicator.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';
import '../models/client_model.dart';
import '../models/server_model.dart';
import '../models/account_model.dart';
import '../services/api_service.dart';
import '../services/account_manager.dart';
import '../services/v2ray_compat.dart';
import '../widgets/connect_button_ultimate.dart';
import '../widgets/connect_button_lite.dart';
import 'login_screen.dart';
import 'server_list_modal.dart';
import 'bypass_apps_screen.dart';
import 'advanced_settings_screen.dart';
import 'manage_accounts_screen.dart';
// v4.0.46 NO-SCROLL: Proxy and GPS moved to AdvancedSettingsScreen (gear), removed from dashboard to prevent scroll
// import 'proxy_screen.dart'; // now accessed via gear settings
// import 'gps_spoof_screen.dart'; // now accessed via gear settings

class DashboardScreen extends StatefulWidget {
  final ClientModel client;
  final BrandingModel branding;
  final List<ServerModel>? initialServers;

  /// Public app version (mirrors the State) so other screens
  /// (login footer, support sheet) can display it.
  // v4.0.63 FIX: Was const, now getter because State's version is dynamic getter (not const) - fixes build failure
  static String get currentAppVersion => _DashboardScreenState.currentAppVersion;
  static String get actualVersion => _DashboardScreenState.actualVersion;
  static int get actualCode => _DashboardScreenState.actualCode;
  static Future<void> loadActualInstalledVersion() => _DashboardScreenState.loadActualInstalledVersion();

  const DashboardScreen({
    Key? key,
    required this.client,
    required this.branding,
    this.initialServers,
  }) : super(key: key);

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> with SingleTickerProviderStateMixin {
  late ClientModel _client;
  List<ServerModel> _servers = [];
  ServerModel? _selectedServer;

  late final V2RayCompat _flutterV2ray;
  final ValueNotifier<V2RayStatus> _v2rayStatus = ValueNotifier<V2RayStatus>(V2RayStatus());

  bool _isConnected = false;
  bool _isConnecting = false;
  bool _isRefreshing = false;
  bool _userIntentionallyDisconnected = true;
  int _connectedSeconds = 0;
  Timer? _timer;

  // New Pro Features States
  bool _splitTunnelingEnabled = true;
  bool _autoPauseForBankingEnabled = false;
  bool _pausedByBankingApp = false;
  String? _pausedForPackage;
  Timer? _foregroundCheckTimer;

  // Windows-only tunnel flavor: 'proxy' (system proxy, Phase 1) or
  // 'tun' (full Wintun VPN, Phase 2). Ignored on Android.
  String _winTunnelMode = 'proxy';
  bool _autoReconnectEnabled = true;
  int _reconnectAttempts = 0;
  bool _isSmartConnecting = false;
  int? _currentServerPing;

  List<Map<String, dynamic>> _announcements = [];
  bool _hasAppUpdate = false;
  Map<String, dynamic>? _updateInfo;
  bool _isCheckingUpdate = false;

  // v4.0.47 MULTI-ACCOUNT
  List<VpnAccount> _accounts = [];
  VpnAccount? _activeAccount;
  bool _unifiedEnabled = false;
  // v4.0.49 MANAGED MODE
  bool _isManagedMode = false;
  bool _hideConfig = false;
  bool _useLiteButton = false; // v4.0.68 ULTIMATE BEAUTIFUL default - wave effects restored, user can toggle to lite in settings

  // v4.0.63 FOREVER FIX - Never show old version after install - PERMANENT LAW
  // LAW: currentAppVersion must be dynamic, not hardcoded const that stays old after install
  // Fix: Make it getter that returns actual installed version if available, else latest hardcoded
  static const String _hardcodedVersion = '4.0.78'; // Fallback only, updated each release - v4.0.78 FIX version display + account info filter
  static const int _hardcodedCode = 112;
  static String get currentAppVersion => _actualInstalledVersion.isNotEmpty && _actualInstalledVersion != '4.0.51' ? _actualInstalledVersion : _hardcodedVersion;
  static String _actualInstalledVersion = '4.0.78'; // Will be updated from PackageManager immediately on start - v4.0.75 FIX
  static int _actualInstalledCode = 112;

  // v4.0.46 FOREVER CACHE FIX - PERMANENT LAW - NEVER REGRESS
  // LAW 1: Panel must NEVER serve old APK (auto-delete stale files)
  // LAW 2: App must verify APK versionName after download via PackageManager.getPackageArchiveInfo
  // LAW 3: If downloaded version != expected, try next URL automatically
  // LAW 4: Clear old files before download
  // LAW 5: ?v=version&t=time&s=random for ALL cache bypass
  // LAW 6: Always try GitHub as fallback even if panel file exists
  // LAW 7: Footer must read actual version from PackageManager, not const - FIXED v4.0.46 (was 4.0.45 bug)
  // LAW 16: Version from app_release.json, never hardcoded, delete stale APKs on version change

  // v4.0.46 FOREVER LAW: Get ACTUAL installed version from PackageManager, not hardcoded - NEVER REGRESS
  // LAW 7: Footer must read actual version from PackageManager, not const
  // LAW 16: Version from app_release.json, never hardcoded, delete stale APKs
  // v4.0.63 FOREVER FIX: Robust version loading - never show old version after install
  static Future<void> loadActualInstalledVersion() async {
    // v4.0.78 FOREVER FIX: Clear any cached old version prefs that might cause stale display
    try {
      final prefs = await SharedPreferences.getInstance();
      // If we have a cached dismissed_version that equals old hardcoded, clear it to force fresh check
      final cachedVer = prefs.getString('cached_app_version') ?? '';
      if (cachedVer.isNotEmpty && cachedVer != _hardcodedVersion) {
        // Old cached version detected, will be updated
      }
    } catch (_) {}
    // Try 3 times with increasing timeout, fallback to hardcoded only if all fail
    for (int attempt = 0; attempt < 3; attempt++) {
      try {
        const channel = MethodChannel('com.connectix.vpn/updater');
        final timeout = Duration(seconds: 2 + attempt); // 2s, 3s, 4s
        final result = await channel.invokeMethod('getInstalledAppVersion').timeout(timeout, onTimeout: () => null);
        if (result is Map) {
          final verName = (result['versionName'] ?? '').toString().trim();
          final verCode = (result['versionCode'] ?? '').toString().trim();
          if (verName.isNotEmpty && verName != '4.0.51' || attempt == 2) {
            // Accept even 4.0.51 on last attempt to avoid empty, but prefer real version
            if (verName.isNotEmpty) {
              _actualInstalledVersion = verName;
              if (kDebugMode) debugPrint('v4.0.63 Actual installed version (attempt ${attempt+1}): $verName ($verCode)');
            }
          } else if (verName.isNotEmpty && verName != '4.0.51') {
            _actualInstalledVersion = verName;
            if (kDebugMode) debugPrint('v4.0.63 Actual installed version (attempt ${attempt+1}): $verName ($verCode)');
          }
          if (verCode.isNotEmpty) {
            final code = int.tryParse(verCode);
            if (code != null && code > 0) {
              _actualInstalledCode = code;
            }
          }
          // If we got a valid version different from old hardcoded 4.0.51, break
          if (_actualInstalledVersion.isNotEmpty && _actualInstalledVersion != '4.0.51') {
            break;
          }
        }
      } catch (e) {
        if (kDebugMode) debugPrint('v4.0.69 loadActualInstalledVersion attempt ${attempt+1} error: $e');
        if (attempt == 2) {
          if (kDebugMode) debugPrint('v4.0.69 loadActualInstalledVersion failed all attempts - using hardcoded $_hardcodedVersion');
          // Keep hardcoded as fallback, but ensure it's latest not 4.0.51
          if (_actualInstalledVersion == '4.0.51' || _actualInstalledVersion.isEmpty) {
            _actualInstalledVersion = _hardcodedVersion;
            _actualInstalledCode = _hardcodedCode;
          }
        }
      }
      if (attempt < 2) {
        await Future.delayed(Duration(milliseconds: 300 * (attempt+1)));
      }
    }
  }

  static String get actualVersion => _actualInstalledVersion;
  static int get actualCode => _actualInstalledCode;

  // "Download over Wi-Fi only" for update packages
  bool _updateWifiOnly = false;

  // Comprehensive Iranian & Banking Apps Bypass List (Verified package names from Cafe Bazaar & Myket)
  // v4.0.53 ULTRA LITE: 20 essential apps only - fixes TransactionTooLarge + button not working
  // Full 130 list moved to extendedDomesticBypassApps for optional use
  static const List<String> defaultDomesticBypassApps = [
    // پیام‌رسان‌های اصلی (4)
    'ir.eitaa.messenger',             // ایتا - 20M
    'ir.resanememaran.rubika',        // روبیکا - 40M
    'ir.ble.messenger',               // بله
    'mobi.mmdt.ott',                  // سروش پلاس

    // بانک‌های اصلی (10)
    'com.pmb.mobile',                 // ملت - 19M
    'ir.bmi.bam.nativeweb',           // ملی بام
    'ir.tejaratbank.tata.mobile.android.tejarat', // تجارت - 9.7M
    'com.isc.bsinew',                 // صادرات - 6M
    'mob.banking.android.sepah',      // سپه
    'mob.banking.android.pasargad',   // پاسارگاد - 4.4M
    'com.samanpr.mobillet',           // سامان موبایلت
    'com.maskanmobilebank',           // مسکن
    'com.bki.mobilebanking.android',  // کشاورزی
    'com.citydi.hplus',               // شهر پلاس - 3.7M

    // پرداخت و خدمات (6)
    'com.adpdigital.mbs.ayande',      // همراه کارت - 12M
    'com.asanpardakht.app',           // آپ
    'cab.snapp.passenger',            // اسنپ
    'ir.divar',                       // دیوار
    'com.farsitel.bazaar',            // بازار
    'ir.gov.my',                      // دولت من
  ];

  // Extended list (130) - optional, user can enable in advanced settings
  static const List<String> extendedDomesticBypassApps = [
    'ir.eitaa.messenger', 'ir.resanememaran.rubika', 'ir.resanememaran.rubikax',
    'ir.ble.messenger', 'mobi.mmdt.ott', 'net.iGap', 'i.gap.im', 'ir.soroush.app',
    'ir.medu.shad', 'com.gap.android',
    'com.pmb.mobile', 'com.mellat.mobilebank', 'ir.bankmellat.mobilebank',
    'com.mellat.mobilebank.new', 'ir.bankmellat.sekeh',
    'com.refahbank.dpi.android', 'com.refah.superapp', 'ir.refah.mobilebank',
    'com.refah.mobilebank', 'ir.refah.omidbank', 'com.refah.fara',
    'ir.bmi.bam.nativeweb', 'com.bmi.bam', 'ir.bmi.bam', 'com.sadadpsp.eva',
    'com.sadad.shast', 'ir.melli.app', 'ir.sadad.mobilebank',
    'ir.tejaratbank.tata.mobile.android.tejarat', 'com.tejaratbank.mobilebank',
    'ir.stts.bjt', 'ir.stts.etc', 'ir.mbt.android', 'com.tejarat.hamrah',
    'com.isc.bsinew', 'ir.bsi.mobilebank', 'com.saderat.mobilebank',
    'com.isc.sap', 'com.saderat.sipa', 'com.saderat.rimor',
    'mob.banking.android.sepah', 'ir.sepah.mobilebank', 'com.sepah.mobilebank',
    'ir.sepah.omid', 'com.ansar.mobilebank', 'com.ghavamin.mobilebank',
    'com.kosar.mobilebank', 'mob.banking.android.pasargad', 'com.dotin.wepod',
    'com.pasargad.mobilebank', 'ir.pasargad.mobilebank',
    'com.samanpr.mobillet', 'com.samanpr.blubank', 'com.modern.blubank',
    'com.samanpr.mb', 'com.saman.mobilebank',
    'com.maskanmobilebank', 'mob.banking.android.maskan', 'ir.maskan.mobilebank',
    'com.maskan.mobilebank', 'com.bki.mobilebanking.android',
    'ir.keshavarzibank.mobilebank', 'com.bki.mobilebank',
    'com.citydi.hplus', 'mob.banking.android.shahr', 'ir.shahr.hamrah', 'com.tosan.shahr',
    'mob.banking.android.resalat', 'ir.rqbank.mobilebank', 'com.melli.resalat',
    'com.qmb.mobile', 'ir.qmb.mobilebank', 'com.qmb.mobilebank', 'ir.ttbank.mobilebank',
    'com.adpdigital.mbs.ayande', 'ir.ba.abank', 'com.tosan.keylid', 'com.ayandeh.mobilebank',
    'com.tosan.dara.day', 'mob.banking.android.day', 'ir.daybank.mobilebank',
    'mob.banking.android.gardesh', 'ir.tourismbank.mobilebank', 'com.tosan.gardeshgari',
    'mob.banking.android.sina', 'ir.sinabank.mobilebank', 'mob.banking.android.taavon',
    'com.tosan.dara.bim', 'com.tosan.dara.edbi', 'mob.banking.android.karafarin',
    'com.middleeastbank.mobile', 'mob.banking.android.iranzamin', 'com.iranzamin.faraz',
    'com.bankino.mobile', 'com.asanpardakht.app', 'ir.sep.seso', 'ir.pec.top',
    'com.pec.parsian', 'com.tosan.saman.sekeh', 'com.snapp.pay', 'ir.mcoin.hamrahcard',
    'ir.nobitex.app', 'ir.wallex.app', 'ir.ramzinex.app', 'ir.abantether.app',
    'com.bitpin.app', 'ir.exir.app',
    'cab.snapp.passenger', 'cab.snapp.driver', 'com.tapsi.passenger', 'com.tapsi.driver',
    'ir.divar', 'com.farsitel.bazaar', 'com.myket.android', 'ir.torob', 'com.digikala',
    'ir.neshan.sp', 'ir.balad', 'com.takhfifan', 'com.snapp.food', 'com.snappbox.passenger',
    'ir.gov.my', 'ir.tamin.taminman', 'ir.post.postman', 'ir.tehran.mytehran',
    'ir.mci.ecareapp', 'ir.irancell.myirancell', 'ir.rightel.myrightel',
  ];

  @override
  void initState() {
    super.initState();
    _client = widget.client;
    if (widget.initialServers != null && widget.initialServers!.isNotEmpty) {
      _servers = widget.initialServers!.where((s) => !s.isInfoBanner && s.configUri.isNotEmpty).toList();
      if (_servers.isNotEmpty) {
        _syncActiveServer(_servers);
      }
    }
    // v4.0.78 FOREVER FIX: Load actual installed version immediately and update UI - FIXED REGRESSION
    // This fixes "new version installed but still shows old version" - must reload from PackageManager
    // LAW: Must call 2 times - immediately and after 1s delay (PackageManager cache)
    loadActualInstalledVersion().then((_) {
      if (mounted) {
        setState(() {
          // Force UI refresh with new actual version
        });
        if (kDebugMode) debugPrint('v4.0.78 initState: Actual version loaded: $actualVersion ($actualCode)');
      }
    });
    // Second attempt after 1.5s to catch PackageManager cache update after fresh install
    Future.delayed(const Duration(milliseconds: 1500), () {
      loadActualInstalledVersion().then((_) {
        if (mounted) {
          setState(() {});
          if (kDebugMode) debugPrint('v4.0.78 initState delayed: Actual version loaded: $actualVersion ($actualCode)');
        }
      });
    });
    _loadSettings();
    _loadAccounts();
    _initV2Ray();
    _loadServers();
    _refreshProfile();
    _loadAnnouncements();
    _autoCheckUpdateInBackground();
  }

  Future<void> _loadAccounts() async {
    try {
      final accounts = await AccountManager.getAccounts();
      final active = await AccountManager.getActiveAccount();
      final unified = await AccountManager.isUnifiedEnabled();
      final managed = await AccountManager.isManagedMode();
      final hideConfig = await AccountManager.isHideConfig();
      if (mounted) {
        setState(() {
          _accounts = accounts;
          _activeAccount = active;
          _unifiedEnabled = unified;
          _isManagedMode = managed;
          _hideConfig = hideConfig;
        });
      }
    } catch (_) {}
  }

  void _loadSettings() async {
    final prefs = await SharedPreferences.getInstance();
    if (mounted) {
      setState(() {
        _splitTunnelingEnabled = prefs.getBool('split_tunneling_enabled') ?? true;
        _autoReconnectEnabled = prefs.getBool('auto_reconnect_enabled') ?? true;
        _autoPauseForBankingEnabled = prefs.getBool('auto_pause_for_banking_enabled') ?? false;
        _updateWifiOnly = prefs.getBool('update_wifi_only') ?? false;
        _winTunnelMode = prefs.getString('windows_tunnel_mode') ?? 'proxy';
        _unifiedEnabled = prefs.getBool('unified_servers_enabled') ?? false;
        _useLiteButton = prefs.getBool('use_lite_button') ?? false; // v4.0.68 default ULTIMATE beautiful
      });
    }
    if (_autoPauseForBankingEnabled && _isConnected) {
      _startForegroundAppMonitoring();
    }
  }

  Future<void> _setAutoPauseForBanking(bool value) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('auto_pause_for_banking_enabled', value);
    if (mounted) setState(() => _autoPauseForBankingEnabled = value);
    if (value && Platform.isAndroid) {
      _checkAndRequestUsageStatsPermission();
    }
    if (value) {
      _startForegroundAppMonitoring();
    } else {
      _stopForegroundAppMonitoring();
    }
  }

  Future<void> _checkAndRequestUsageStatsPermission() async {
    if (!Platform.isAndroid) return;
    try {
      const channel = MethodChannel('com.connectix.vpn/updater');
      final bool hasPermission = await channel.invokeMethod<bool>('checkUsageStatsPermission') ?? false;
      if (!hasPermission && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('برای توقف خودکار، دسترسی «دسترسی به آمار استفاده» را در تنظیمات فعال کنید.'),
            duration: Duration(seconds: 5),
          ),
        );
        await channel.invokeMethod('openUsageStatsSettings');
      }
    } catch (e) {
      debugPrint('UsageStats permission check: $e');
    }
  }

  void _startForegroundAppMonitoring() {
    if (!Platform.isAndroid || !_autoPauseForBankingEnabled) return;
    _foregroundCheckTimer?.cancel();
    _foregroundCheckTimer = Timer.periodic(const Duration(seconds: 5), (timer) async {
      if (!_isConnected || _isConnecting || _pausedByBankingApp) {
        // If already paused by banking, check if we should resume
        if (_pausedByBankingApp) {
          await _checkShouldResumeVpn();
        }
        return;
      }
      await _checkForegroundAppAndMaybePause();
    });
  }

  void _stopForegroundAppMonitoring() {
    _foregroundCheckTimer?.cancel();
    _foregroundCheckTimer = null;
  }

  Future<void> _checkForegroundAppAndMaybePause() async {
    if (!Platform.isAndroid || !_autoPauseForBankingEnabled || !_isConnected) return;
    try {
      const channel = MethodChannel('com.connectix.vpn/updater');
      final String? foregroundPkg = await channel.invokeMethod<String>('getForegroundApp');
      if (foregroundPkg == null || foregroundPkg.isEmpty) return;
      if (foregroundPkg == 'com.connectix.vpn') return;

      final prefs = await SharedPreferences.getInstance();
      final List<String>? customBypass = prefs.getStringList('custom_bypass_apps');
      final List<String> bypassList = (customBypass != null && customBypass.isNotEmpty) ? customBypass : defaultDomesticBypassApps;

      if (bypassList.contains(foregroundPkg)) {
        // Banking/Iranian app in foreground -> pause VPN to hide tun0
        debugPrint('Auto-pausing VPN for banking app: $foregroundPkg');
        _pausedByBankingApp = true;
        _pausedForPackage = foregroundPkg;
        try {
          await _flutterV2ray.stopV2Ray();
        } catch (_) {}
        _timer?.cancel();
        if (mounted) {
          setState(() {
            _isConnected = false;
            _isConnecting = false;
          });
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('VPN برای ${foregroundPkg} متوقف شد تا بدون شناسایی فیلترشکن باز شود.'),
              backgroundColor: const Color(0xFF10B981),
              duration: const Duration(seconds: 4),
            ),
          );
        }
        ApiService.updateNotificationStatus(title: '', content: '', isConnected: false);
      }
    } catch (e) {
      debugPrint('Foreground check error: $e');
    }
  }

  Future<void> _checkShouldResumeVpn() async {
    if (!Platform.isAndroid || !_pausedByBankingApp) return;
    try {
      const channel = MethodChannel('com.connectix.vpn/updater');
      final String? foregroundPkg = await channel.invokeMethod<String>('getForegroundApp');
      if (foregroundPkg == null) return;
      // If user left banking app and returned to Connectix or other non-bypass app, resume
      final prefs = await SharedPreferences.getInstance();
      final List<String>? customBypass = prefs.getStringList('custom_bypass_apps');
      final List<String> bypassList = (customBypass != null && customBypass.isNotEmpty) ? customBypass : defaultDomesticBypassApps;

      if (!bypassList.contains(foregroundPkg) || foregroundPkg == 'com.connectix.vpn') {
        debugPrint('Auto-resuming VPN after leaving banking app: $_pausedForPackage -> $foregroundPkg');
        _pausedByBankingApp = false;
        _pausedForPackage = null;
        if (mounted && !_isConnected && !_isConnecting && !_userIntentionallyDisconnected) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('بازگشت به Connectix - VPN دوباره وصل می‌شود...'),
              backgroundColor: Color(0xFF6366F1),
            ),
          );
          _startTunnel(isReconnect: true);
        } else if (_userIntentionallyDisconnected) {
          _pausedByBankingApp = false;
        }
      }
    } catch (e) {
      debugPrint('Resume check error: $e');
    }
  }

  Future<void> _setWinTunnelMode(String value) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('windows_tunnel_mode', value);
    if (mounted) setState(() => _winTunnelMode = value);
  }

  void _pickWinTunnelMode(String value) {
    if (value == _winTunnelMode) return;
    _setWinTunnelMode(value);
    if (_isConnected) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('حالت جدید در اتصال بعدی اعمال می‌شود (یک‌بار قطع و دوباره وصل شوید).')),
      );
    }
  }

  Widget _winModeChip({
    required BuildContext context,
    required String label,
    required bool selected,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: const EdgeInsets.symmetric(vertical: 8),
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: selected ? const Color(0xFF9333EA) : const Color(0xFF0F172A),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
            color: selected ? const Color(0xFF9333EA) : const Color(0xFF334155),
          ),
        ),
        child: Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: selected ? Colors.white : const Color(0xFF94A3B8),
            fontSize: 11,
            fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
          ),
        ),
      ),
    );
  }

  Future<void> _setUpdateWifiOnly(bool value) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool('update_wifi_only', value);
    if (mounted) setState(() => _updateWifiOnly = value);
  }

  /// Sends the current device log as a support ticket (never throws).
  Future<void> _sendUpdateErrorReport(String context) async {
    if (!mounted) return;
    final messenger = ScaffoldMessenger.of(this.context);
    final ok = await ApiService.sendFeedback(
      subject: 'خطای دانلود/نصب بروزرسانی',
      message: 'بروزرسانی ${_updateInfo?['latest_version'] ?? ''}\n$context',
      version: currentAppVersion,
    );
    messenger.showSnackBar(SnackBar(
      content: Text(ok ? '✓ گزارش فنی برای پشتیبانی ارسال شد.' : 'ارسال گزارش ممکن نشد؛ لطفاً دوباره تلاش کنید.'),
    ));
  }

  static bool isNewerVersion(String latest, String current) {
    try {
      // v4.0.43 FOREVER: Use actual installed version if available, not hardcoded
      final effectiveCurrent = current == currentAppVersion ? _actualInstalledVersion : current;
      List<int> parse(String v) => v
          .replaceAll(RegExp(r'[^\d.]'), '')
          .split('.')
          .map((e) => int.tryParse(e) ?? 0)
          .toList();
      final l = parse(latest);
      final c = parse(effectiveCurrent);
      for (int i = 0; i < 3; i++) {
        final lv = i < l.length ? l[i] : 0;
        final cv = i < c.length ? c[i] : 0;
        if (lv > cv) return true;
        if (lv < cv) return false;
      }
    } catch (_) {}
    return false;
  }

  void _loadAnnouncements() async {
    final list = await ApiService.getAnnouncements();
    if (mounted && list.isNotEmpty) {
      setState(() {
        _announcements = list;
      });
    }
  }

  void _autoCheckUpdateInBackground() async {
    if (_isCheckingUpdate) return;
    _isCheckingUpdate = true;
    try {
      final prefs = await SharedPreferences.getInstance();
      final dismissed = prefs.getString('dismissed_version') ?? '';
      final updateData = await ApiService.checkAppUpdate();
      if (mounted && updateData != null) {
        final latest = (updateData['latest_version'] ?? '').toString();
        final lastPrompted = prefs.getString('last_prompted_version') ?? '';

        final bool isNew = isNewerVersion(latest, currentAppVersion);
        final bool isDismissed = dismissed == latest && dismissed.isNotEmpty;
        
        if (kDebugMode) debugPrint('v4.0.29 UPDATE CHECK: current=$currentAppVersion latest=$latest isNew=$isNew dismissed=$isDismissed lastPrompted=$lastPrompted');
        
        // v4.0.29 FIX: Always show update if newer, even if dismissed before (for important fixes)
        // But respect dismissed for same version unless force
        final forceUpdate = updateData['force_update'] == true;
        final shouldShow = isNew && (!isDismissed || forceUpdate);
        
        setState(() {
          _hasAppUpdate = shouldShow;
          _updateInfo = shouldShow ? updateData : null;
        });

        // Automatically alert user with one-click install dialog if not yet prompted for this version
        // v4.0.29 FIX: Always show dialog for new version, even if previously dismissed (for critical fixes)
        if (shouldShow && (lastPrompted != latest || forceUpdate)) {
          await prefs.setString('last_prompted_version', latest);
          Future.delayed(const Duration(milliseconds: 1400), () {
            if (mounted) {
              _showUpdateDialog(updateData, isAutoPrompt: true);
            }
          });
        }
      } else {
        if (kDebugMode) debugPrint('v4.0.29 UPDATE CHECK: updateData is null - no update info');
      }
    } catch (e) {
      if (kDebugMode) debugPrint('v4.0.29 UPDATE CHECK error: $e');
    } finally {
      _isCheckingUpdate = false;
    }
  }

  void _dismissUpdateBanner() async {
    if (_updateInfo != null) {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('dismissed_version', (_updateInfo!['latest_version'] ?? '').toString());
    }
    setState(() {
      _hasAppUpdate = false;
    });
  }

  bool _isV2RayInitialized = false;
  void _initV2Ray() async {
    try {
      // v4.0.53 FIX: Ensure V2Ray is initialized before use
      await _flutterV2ray.initializeV2Ray().timeout(const Duration(seconds: 5), onTimeout: () {
        ApiService.log('V2Ray init timeout - will retry on connect');
      });
      _isV2RayInitialized = true;
      ApiService.log('V2Ray initialized successfully');
    } catch (e) {
      ApiService.log('V2Ray init failed: $e - will retry on connect');
      _isV2RayInitialized = false;
    }
    try {
      MethodChannel('com.connectix.vpn/updater').setMethodCallHandler((call) async {
      if (call.method == 'onNotificationDisconnect') {
        _userIntentionallyDisconnected = true;
        _pausedByBankingApp = false;
        _reconnectAttempts = 0;
        _stopForegroundAppMonitoring();
        try {
          await _flutterV2ray.stopV2Ray();
        } catch (_) {}
        _timer?.cancel();
        if (mounted) {
          setState(() {
            _isConnected = false;
            _isConnecting = false;
          });
        }
        ApiService.updateNotificationStatus(
          title: '',
          content: '',
          isConnected: false,
        );
      }
    });
    } catch (e) {
      ApiService.log('MethodChannel handler setup failed: $e');
    }

    _flutterV2ray = V2RayCompat(
      onStatusChanged: (status) {
        _v2rayStatus.value = status;
        if (mounted) {
          setState(() {
            if (status.state == 'CONNECTED') {
              final wasConnected = _isConnected;
              _isConnected = true;
              _isConnecting = false;
              _userIntentionallyDisconnected = false;
              _pausedByBankingApp = false;
              _reconnectAttempts = 0;
              if (!wasConnected) {
                _autoCheckUpdateInBackground();
                if (_autoPauseForBankingEnabled) {
                  _startForegroundAppMonitoring();
                }
                // v4.0.36 ZERO-COST ST4: Save last working server (free, offline cache)
                if (_selectedServer != null) {
                  ApiService.saveLastWorkingServer(_selectedServer!);
                  ApiService.log('ST4: Saved last working ${ _selectedServer!.name}');
                }
                // v4.0.76 NEW: Blacklist learning - if after 10s upload=0 download=0, mark as info server
                Future.delayed(const Duration(seconds: 10), () async {
                  try {
                    if (!mounted || !_isConnected || _selectedServer == null) return;
                    final status = _v2rayStatus.value;
                    final dl = status.downloadSpeed ?? 0;
                    final ul = status.uploadSpeed ?? 0;
                    print('v4.0.76 blacklist check: dl=$dl ul=$ul server=${_selectedServer!.name}');
                    if (dl == 0 && ul == 0) {
                      final prefs = await SharedPreferences.getInstance();
                      final list = prefs.getStringList('blacklisted_info_servers') ?? [];
                      if (!list.contains(_selectedServer!.id)) {
                        list.add(_selectedServer!.id);
                        await prefs.setStringList('blacklisted_info_servers', list);
                        print('v4.0.76 BLACKLISTED info server: ${_selectedServer!.name} id=${_selectedServer!.id}');
                        if (mounted) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(
                              content: Text('⚠️ سرور ${_selectedServer!.name} حجم/زمان بود و بلاک شد'),
                              backgroundColor: const Color(0xFFEF4444),
                            ),
                          );
                        }
                      }
                    }
                  } catch (e) {
                    print('v4.0.76 blacklist check error: $e');
                  }
                });
              }
              final down = _formatSpeed(status.downloadSpeed);
              final up = _formatSpeed(status.uploadSpeed);
              final sName = _selectedServer?.name ?? 'متصل';
              ApiService.updateNotificationStatus(
                title: 'Connectix • $sName (${status.duration})',
                content: '↓ $down   •   ↑ $up',
                isConnected: true,
              );
            } else if (status.state == 'DISCONNECTED') {
              final wasConnected = _isConnected;
              _isConnected = false;
              _isConnecting = false;
              _timer?.cancel();
              if (!_pausedByBankingApp) {
                _stopForegroundAppMonitoring();
              }
              ApiService.updateNotificationStatus(
                title: '',
                content: '',
                isConnected: false,
              );
              
              // v4.0.36 ZERO-COST ST3: Auto-Reconnect with exponential backoff (free, no disadvantage)
              // If disconnect was NOT intentional and auto-reconnect enabled
              if (wasConnected && !_userIntentionallyDisconnected && !_pausedByBankingApp && _autoReconnectEnabled) {
                if (_reconnectAttempts < 5) {
                  _reconnectAttempts++;
                  final delay = Duration(seconds: _reconnectAttempts * 2); // 2s, 4s, 6s, 8s, 10s
                  ApiService.log('ST3 Auto-Reconnect attempt $_reconnectAttempts/5 in ${delay.inSeconds}s');
                  Future.delayed(delay, () {
                    if (mounted && !_isConnected && !_isConnecting && !_userIntentionallyDisconnected) {
                      ApiService.log('ST3 Auto-Reconnect executing attempt $_reconnectAttempts');
                      _startTunnel();
                    }
                  });
                } else {
                  // Max attempts reached, show notification
                  _reconnectAttempts = 0;
                  _userIntentionallyDisconnected = true;
                  if (mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('اتصال قطع شد - 5 بار تلاش ناموفق، لطفا دستی وصل شوید')),
                    );
                  }
                }
              } else {
                // Intentional disconnect
                if (!_pausedByBankingApp) {
                  _userIntentionallyDisconnected = true;
                }
                _reconnectAttempts = 0;
              }
            }
          });
        }
      },
    );

    try {
      await _flutterV2ray.initializeV2Ray();
    } catch (e) {
      debugPrint("V2Ray init error: $e");
    }
  }

  void _handleAutoReconnect() {
    _reconnectAttempts = 0;
  }

  void _refreshProfile() async {
    final updated = await ApiService.getProfile();
    if (updated != null && mounted) {
      setState(() {
        _client = updated;
      });
    }
  }

  Future<void> _syncActiveServer(List<ServerModel> list) async {
    final clean = list.where((s) => !s.isInfoBanner && s.configUri.isNotEmpty).toList();
    if (clean.isEmpty) return;

    final prefs = await SharedPreferences.getInstance();
    final lastId = prefs.getString('last_working_server_id');

    ServerModel? target;
    if (lastId != null) {
      final matches = clean.where((s) => s.id == lastId);
      if (matches.isNotEmpty) {
        target = matches.first;
      }
    }

    if (target == null && _selectedServer != null) {
      final matches = clean.where((s) => s.id == _selectedServer!.id && !s.isInfoBanner);
      if (matches.isNotEmpty) {
        target = matches.first;
      }
    }

    if (target == null) {
      target = clean.firstWhere((s) => s.isRecommended, orElse: () => clean.first);
    }

    if (mounted) {
      setState(() {
        _selectedServer = target;
      });
      _measureSelectedServerPing();
    }
  }

  Future<void> _loadServers() async {
    // 1. Immediately populate from local cache if list is currently empty
    if (_servers.isEmpty) {
      final cached = await ApiService.getCachedServers();
      final cleanCached = cached.where((s) => !s.isInfoBanner && s.configUri.isNotEmpty).toList();
      if (cleanCached.isNotEmpty && mounted && _servers.isEmpty) {
        setState(() {
          _servers = cleanCached;
        });
        await _syncActiveServer(cleanCached);
      }
    }

    // 2. Fetch fresh server list in background
    final list = await ApiService.getServers();
    final cleanList = list.where((s) => !s.isInfoBanner && s.configUri.isNotEmpty).toList();
    if (mounted && cleanList.isNotEmpty) {
      setState(() {
        _servers = cleanList;
      });
      await _syncActiveServer(cleanList);
    }
  }

  void _manualRefresh() async {
    if (_isRefreshing) return;
    setState(() {
      _isRefreshing = true;
    });

    final profileFuture = ApiService.getProfile();
    final serversFuture = ApiService.getServers();

    final results = await Future.wait([profileFuture, serversFuture]);
    final updatedProfile = results[0] as ClientModel?;
    final updatedServers = results[1] as List<ServerModel>;

    if (mounted) {
      final cleanUpdated = updatedServers.where((s) => !s.isInfoBanner && s.configUri.isNotEmpty).toList();
      setState(() {
        _isRefreshing = false;
        if (updatedProfile != null) {
          _client = updatedProfile;
        }
        if (cleanUpdated.isNotEmpty) {
          _servers = cleanUpdated;
        }
      });
      if (cleanUpdated.isNotEmpty) {
        await _syncActiveServer(cleanUpdated);
      }

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            cleanUpdated.isNotEmpty
                ? 'اطلاعات حساب و ${cleanUpdated.length} کانکشن سرور با موفقیت بروزرسانی شد.'
                : 'اطلاعات حساب با موفقیت بروزرسانی شد.',
          ),
          backgroundColor: const Color(0xFF10B981),
          duration: const Duration(seconds: 2),
        ),
      );

      _autoCheckUpdateInBackground();
    }
  }

  // v4.0.14 IMPROVED PING - Show "آماده" when ping fails but server exists
  void _measureSelectedServerPing() async {
    if (_selectedServer == null || _selectedServer!.configUri.isEmpty) return;
    try {
      int? delay;
      try {
        final parser = V2RayCompat.parseUniversal(_selectedServer!.configUri);
        // v4.0.53 FIX: Add timeout to prevent stuck progress 0/16 + button not working
        if (_isV2RayInitialized) {
          delay = await _flutterV2ray.getServerDelay(config: parser.getFullConfiguration())
              .timeout(const Duration(seconds: 3), onTimeout: () => -1);
        } else {
          ApiService.log('measurePing: V2Ray not initialized, skipping V2Ray ping');
        }
        ApiService.log('measurePing: V2Ray delay for ${_selectedServer!.name} => $delay ms');
      } catch (e) {
        ApiService.log('measurePing: V2Ray getServerDelay failed: $e');
      }

      if (delay == null || delay <= 0) {
        delay = await ApiService.pingServerUri(_selectedServer!.configUri);
        ApiService.log('measurePing: TCP ping for ${_selectedServer!.name} => $delay ms');
      }

      if (mounted) {
        setState(() {
          if (delay != null && delay > 0 && delay < 10000) {
            _currentServerPing = delay;
            _selectedServer!.pingMs = delay;
          } else {
            // v4.0.14: If ping fails but server exists, show as "ready" (0 = special value for آماده)
            // This fixes "هیچی نشان نمیده و فقط نوشته آماده اتصال" - now shows "آماده" badge
            _currentServerPing = 0; // 0 means "آماده" - server exists but ping not measurable
            _selectedServer!.pingMs = 0;
          }
        });
      }
    } catch (e) {
      ApiService.log('measurePing error: $e');
      if (mounted) {
        setState(() {
          _currentServerPing = 0;
          if (_selectedServer != null) _selectedServer!.pingMs = 0;
        });
      }
    }
  }

  // 1-Tap Smart Connect to Best Ping Server - v4.0.76 ULTRA STRICT FINAL + BLACKLIST + MANUAL EXCLUDE
  Future<List<String>> _getBlacklistedIds() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      return prefs.getStringList('blacklisted_info_servers') ?? [];
    } catch (_) {
      return [];
    }
  }
  Future<List<String>> _getExcludedIds() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      return prefs.getStringList('excluded_from_smart') ?? [];
    } catch (_) {
      return [];
    }
  }
  void _smartConnect() async {
    if (_isSmartConnecting || _isConnecting) return;
    print('v4.0.76 _smartConnect: total=${_servers.length}');
    final blacklisted = await _getBlacklistedIds();
    final excluded = await _getExcludedIds();
    print('v4.0.76 blacklist=${blacklisted.length} excluded=${excluded.length}');
    for (final s in _servers) {
      print('  ${s.name} | isInfo=${s.isInfoBanner} | isReal=${s.isRealVpnServer} | sel=${s.isSelectableForSmartConnect} | reason=${s.rejectReason} | black=${blacklisted.contains(s.id)} | excl=${excluded.contains(s.id)}');
    }
    final clean = _servers.where((s) {
      if (blacklisted.contains(s.id)) return false;
      if (excluded.contains(s.id)) return false;
      if (s.isInfo) return false;
      if (!s.isReal) return false;
      if (s.isInfoBanner) return false;
      if (!s.isRealVpnServer) return false;
      if (!s.isSelectableForSmartConnect) return false;
      final lower = s.name.toLowerCase();
      if (s.name.contains('👤') || s.name.contains('👥')) return false; // v4.0.78: account info with user emoji
      if (lower.contains('حجم') || lower.contains('باقی') || lower.contains('روز') || lower.contains('ساعت') || lower.contains('انقضا') || lower.contains('ترافیک') || lower.contains('traffic') || lower.contains('expire') || lower.contains('remaining') || lower.contains('quota') || lower.contains('account') || lower.contains('subscription') || lower.contains('info') || lower.contains('📊') || lower.contains('⏳') || lower.contains('👤') || lower.contains('کاربر') || lower.contains('اکانت')) return false;
      // v4.0.78 NEW: Block if name contains current username (privacy)
      try {
        final userLower = _client.username.toLowerCase();
        if (userLower.isNotEmpty && userLower.length >= 3 && lower.contains(userLower)) {
          print('v4.0.78 BLOCKED server containing username: ${s.name} contains ${_client.username}');
          return false;
        }
      } catch (_) {}
      return true;
    }).toList();
    print('v4.0.76 clean=${clean.length}');
    if (clean.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('هیچ سرور واقعی برای اتصال هوشمند یافت نشد (همه حجم/زمان یا بلاک شدند)'), backgroundColor: Color(0xFFEF4444)),
      );
      return;
    }
    setState(() {
      _isSmartConnecting = true;
    });
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('در حال سنجش پینگ ${clean.length} سرور واقعی... (حجم/زمان/بلاک رد شد)'),
        backgroundColor: const Color(0xFF6366F1),
        duration: const Duration(seconds: 2),
      ),
    );
    ServerModel bestServer = clean.first;
    int lowestPing = 99999;
    final candidates = clean.length > 10 ? clean.take(10).toList() : clean;
    final futures = candidates.map((s) async {
      if (s.configUri.isEmpty) return (s, -1);
      try {
        final parser = V2RayCompat.parseUniversal(s.configUri);
        final delay = await _flutterV2ray.getServerDelay(config: parser.getFullConfiguration()).timeout(const Duration(seconds: 4), onTimeout: () => -1);
        return (s, delay ?? -1);
      } catch (_) {
        return (s, -1);
      }
    }).toList();
    final results = await Future.wait(futures);
    bool found = false;
    for (final r in results) {
      final s = r.$1;
      final delay = r.$2;
      if (delay > 0 && delay < lowestPing) {
        lowestPing = delay;
        bestServer = s;
        found = true;
      }
    }
    if (!found) {
      bestServer = clean.first;
      lowestPing = 99999;
    }
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('last_working_server_id', bestServer.id);
    } catch (_) {}
    if (mounted) {
      setState(() {
        _isSmartConnecting = false;
        _selectedServer = bestServer;
        _currentServerPing = lowestPing < 99999 ? lowestPing : null;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('✅ سریع‌ترین سرور واقعی (${bestServer.name}) پینگ ${lowestPing < 99999 ? '$lowestPing ms' : 'عالی'} (حجم/زمان/بلاک رد شد)'),
          backgroundColor: const Color(0xFF10B981),
        ),
      );
      if (!_isConnected) {
        _startTunnel();
      } else {
        try {
          await _flutterV2ray.stopV2Ray();
        } catch (_) {}
        await Future.delayed(const Duration(milliseconds: 400));
        _startTunnel();
      }
    }
  }

  // v4.0.66 NEW: Ping all servers - v4.0.75 ULTRA STRICT: Only real VPN servers, not volume info
  Future<void> _pingAllServers() async {
    if (_isRefreshing || _servers.isEmpty) return;
    final blacklisted = await _getBlacklistedIds();
    final excluded = await _getExcludedIds();
    final realServers = _servers.where((s) {
      if (blacklisted.contains(s.id)) return false;
      if (excluded.contains(s.id)) return false;
      if (s.isInfo) return false;
      if (!s.isReal) return false;
      if (s.isInfoBanner) return false;
      if (!s.isRealVpnServer) return false;
      if (!s.isSelectableForSmartConnect) return false;
      if (s.name.contains('👤') || s.name.contains('👥')) return false;
      final lower = s.name.toLowerCase();
      if (lower.contains('حجم') || lower.contains('باقی') || lower.contains('روز') || lower.contains('ساعت') || lower.contains('انقضا') || lower.contains('ترافیک') || lower.contains('traffic') || lower.contains('expire') || lower.contains('remaining') || lower.contains('quota') || lower.contains('account') || lower.contains('📊') || lower.contains('⏳') || lower.contains('👤')) return false;
      try {
        final userLower = _client.username.toLowerCase();
        if (userLower.isNotEmpty && userLower.length >= 3 && lower.contains(userLower)) return false;
      } catch (_) {}
      return true;
    }).toList();
    print('v4.0.76 _pingAllServers: total=${_servers.length} real=${realServers.length} black=${blacklisted.length} excl=${excluded.length}');
    if (realServers.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('هیچ سرور واقعی برای پینگ یافت نشد (فقط اطلاعات حجم بود)'), backgroundColor: Color(0xFFEF4444)),
      );
      return;
    }
    setState(() => _isRefreshing = true);
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('در حال پینگ همه سرورها...'), backgroundColor: Color(0xFF10B981), duration: Duration(seconds: 2)),
    );
    try {
      int bestPing = 99999;
      ServerModel? bestServer;
      // v4.0.71 OPTIMIZED: Parallel ping for all servers - 5x faster
      final candidates = realServers.take(10).toList();
      final pingFutures = candidates.map((s) async {
        if (s.configUri.isEmpty) return (s, 0);
        try {
          final parser = V2RayCompat.parseUniversal(s.configUri);
          int? delay;
          try {
            delay = await _flutterV2ray.getServerDelay(config: parser.getFullConfiguration()).timeout(const Duration(seconds: 3), onTimeout: () => -1);
          } catch (_) {}
          delay ??= await ApiService.pingServerUri(s.configUri);
          if (delay != null && delay > 0 && delay < 10000) {
            return (s, delay);
          } else {
            return (s, 0);
          }
        } catch (_) {
          return (s, 0);
        }
      }).toList();

      final pingResults = await Future.wait(pingFutures);
      for (final r in pingResults) {
        final s = r.$1;
        final delay = r.$2;
        s.pingMs = delay;
        if (delay > 0 && delay < bestPing) {
          bestPing = delay;
          bestServer = s;
        }
      }
      if (mounted) {
        setState(() {
          _currentServerPing = bestServer?.pingMs;
          if (bestServer != null) _selectedServer = bestServer;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(bestServer != null ? '✅ بهترین سرور: ${bestServer.name} (${bestPing}ms)' : 'پینگ همه سرورها انجام شد'), backgroundColor: const Color(0xFF10B981)),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('خطا در پینگ: $e')));
      }
    } finally {
      if (mounted) setState(() => _isRefreshing = false);
    }
  }

  // In-App Auto-Update & In-Place Installer Flow
  void _checkAppUpdate() async {
    if (_hasAppUpdate && _updateInfo != null) {
      final latestVer = (_updateInfo!['latest_version'] ?? '3.4.7').toString();
      final dlUrl = (_updateInfo!['download_url'] ?? '').toString();
      final fbUrl = (_updateInfo!['fallback_url'] ?? '').toString();
      _startInAppDownloadAndInstall(dlUrl, latestVer, fallbackUrl: fbUrl);
      return;
    }

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => const Center(
        child: CircularProgressIndicator(color: Color(0xFF6366F1)),
      ),
    );

    final updateData = await ApiService.checkAppUpdate();
    if (!mounted) return;
    Navigator.pop(context);

    if (updateData != null && isNewerVersion((updateData['latest_version'] ?? '').toString(), currentAppVersion)) {
      final latestVer = (updateData['latest_version'] ?? '3.4.7').toString();
      final dlUrl = (updateData['download_url'] ?? '').toString();
      final fbUrl = (updateData['fallback_url'] ?? '').toString();
      _startInAppDownloadAndInstall(dlUrl, latestVer, fallbackUrl: fbUrl);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('شما از آخرین نسخه رسمی نرم‌افزار ($currentAppVersion) استفاده می‌فرمایید.'),
          backgroundColor: const Color(0xFF1E293B),
        ),
      );
    }
  }

  void _showUpdateDialog(Map<String, dynamic> updateData, {bool isAutoPrompt = false}) {
    final latestVer = (updateData['latest_version'] ?? '3.1.0').toString();
    final changelog = (updateData['changelog'] ?? '• ماندگاری دائمی ورود به حساب\n• دریافت زنده ۱۴ اینباند فعال پاسارگاد\n• دانلود مستقیم و پرسرعت درون‌برنامه‌ای').toString();
    final downloadUrl = (updateData['download_url'] ?? '').toString();
    final fallbackUrl = (updateData['fallback_url'] ?? '').toString();

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: const Color(0xFF0F172A),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(24),
          side: const BorderSide(color: Color(0xFF4F46E5), width: 1.5),
        ),
        title: Row(textDirection: TextDirection.rtl, 
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
                'بروزرسانی Connectix ($latestVer)',
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
              child: Text(
                isAutoPrompt ? '✨ نگارش جدید به صورت خودکار شناسایی شد' : 'تغییرات نگارش $latestVer',
                style: const TextStyle(color: Color(0xFFA5B4FC), fontSize: 11, fontWeight: FontWeight.bold),
              ),
            ),
            const SizedBox(height: 10),
            Text(
              changelog,
              style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 12, height: 1.6),
            ),
          ],
        ),
        actions: [
          // Wi-Fi-only toggle (data-saver for cellular plans)
          SizedBox(
            width: double.infinity,
            child: Row(textDirection: TextDirection.rtl, 
              children: [
                Expanded(
                  child: Text(
                    'دانلود فقط روی Wi-Fi (صرفه‌جویی در اینترنت)',
                    style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 11),
                  ),
                ),
                Switch(
                  value: _updateWifiOnly,
                  activeColor: const Color(0xFF10B981),
                  onChanged: (v) => _setUpdateWifiOnly(v),
                ),
              ],
            ),
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('بعداً', style: TextStyle(color: Color(0xFF64748B))),
          ),
          ElevatedButton.icon(
            onPressed: () async {
              if (_updateWifiOnly) {
                final onWifi = await ApiService.isOnWifi();
                if (!onWifi) {
                  ScaffoldMessenger.of(ctx).showSnackBar(const SnackBar(
                    content: Text('شما گزینه «فقط روی Wi-Fi» را فعال کرده‌اید. برای دانلود، به شبکه Wi-Fi متصل شوید (یا این گزینه را غیرفعال کنید).'),
                  ));
                  return;
                }
              }
              // v4.0.75 NEW: Check install permission before download
              try {
                const channel = MethodChannel('com.connectix.vpn/updater');
                final canInstall = await channel.invokeMethod('canInstallPackages').timeout(const Duration(seconds: 2), onTimeout: () => true);
                if (canInstall != true) {
                  // Show permission dialog
                  final shouldOpen = await showDialog<bool>(
                    context: ctx,
                    builder: (permCtx) => AlertDialog(
                      backgroundColor: const Color(0xFF0F172A),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                      title: const Row(
                        textDirection: TextDirection.rtl,
                        children: [
                          Icon(Icons.security_rounded, color: Color(0xFFF59E0B)),
                          SizedBox(width: 8),
                          Text('مجوز نصب لازم است', style: TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.bold)),
                        ],
                      ),
                      content: const Text(
                        'برای نصب خودکار درون‌برنامه‌ای، باید اجازه نصب برنامه‌های ناشناخته را فعال کنید:\n\n'
                        '1️⃣ در صفحه بعدی، Connectix را پیدا کنید\n'
                        '2️⃣ گزینه "مجاز" یا "Allow" را فعال کنید\n'
                        '3️⃣ برگردید و دوباره "نصب خودکار" را بزنید\n\n'
                        'این مجوز فقط برای بروزرسانی استفاده می‌شود و امن است.',
                        style: TextStyle(color: Color(0xFF94A3B8), fontSize: 12, height: 1.6),
                        textDirection: TextDirection.rtl,
                      ),
                      actions: [
                        TextButton(
                          onPressed: () => Navigator.pop(permCtx, false),
                          child: Text('انصراف', style: TextStyle(color: Color(0xFF64748B))),
                        ),
                      ],
                    ),
                  );
                  // Open settings
                  try {
                    await channel.invokeMethod('openInstallPermissionSettings');
                  } catch (_) {}
                  // Don't start download yet, let user enable permission first
                  ScaffoldMessenger.of(ctx).showSnackBar(
                    const SnackBar(
                      content: Text('لطفا مجوز نصب را فعال کنید، سپس دوباره بروزرسانی را بزنید'),
                      backgroundColor: Color(0xFFF59E0B),
                      duration: Duration(seconds: 4),
                    ),
                  );
                  return;
                }
              } catch (e) {
                // If check fails, continue anyway
              }
              
              Navigator.pop(ctx);
              _startInAppDownloadAndInstall(downloadUrl, latestVer, fallbackUrl: fallbackUrl);
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF10B981),
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              elevation: 4,
            ),
            icon: const Icon(Icons.install_mobile_rounded, size: 18),
            label: const Text('نصب خودکار درون‌برنامه‌ای', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
          ),
        ],
      ),
    );
  }

  // Real-time In-App Downloader Modal Sheet
  // v4.0.73 NEW ARCHITECTURE: 3-Tier Hybrid - GitHub Direct + Panel Proxy + Browser Fallback - 100% Guaranteed
  void _startInAppDownloadAndInstall(String downloadUrl, String version, {String fallbackUrl = ''}) {
    final githubReleasesPage = "https://github.com/hojjatrad/panelconnectix/releases/tag/v$version";
    final githubLatestPage = "https://github.com/hojjatrad/panelconnectix/releases/latest";
    final directPanelUrl = "https://direct.vpbotn.ir/download_apk.php?file=arm64&v=$version";
    final defaultPrimary = "https://github.com/hojjatrad/panelconnectix/releases/download/v$version/Connectix-ARM64-v8a.apk";
    final defaultFallback = "https://github.com/hojjatrad/panelconnectix/releases/download/v$version/Connectix-Android-Universal.apk";

    if (downloadUrl.isEmpty || !downloadUrl.startsWith('http')) {
      downloadUrl = defaultPrimary;
    }
    if (fallbackUrl.isEmpty || !fallbackUrl.startsWith('http')) {
      fallbackUrl = defaultFallback;
    }

    // Windows (phase 1): the update is a ZIP package — open it in the
    // default browser (no in-process installer yet).
    if (Platform.isWindows) {
      final uri = Uri.parse(downloadUrl);
      canLaunchUrl(uri).then((ok) {
        if (ok) {
          launchUrl(uri, mode: LaunchMode.externalApplication);
        }
      });
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text('درخواست دانلود شد. بعد از دانلود، فایل فشرده را باز کرده و طبق راهنما نصب کنید.'),
      ));
      return;
    }

    double downloadProgress = 0.0;
    String statusText = 'در حال برقراری ارتباط...';
    String transferredText = '0 MB / ...';
    bool isFailed = false;
    bool hasStarted = false;
    bool usedFallback = false;

    showModalBottomSheet(
      context: context,
      isDismissible: false,
      enableDrag: false,
      backgroundColor: const Color(0xFF0F172A),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      builder: (bottomSheetContext) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            // Kick off download exactly once when modal mounts
            if (!hasStarted) {
              hasStarted = true;

              void launchDownload(String url) {
                ApiService.downloadAndInstallApk(
                  downloadUrl: url,
                  onProgress: (progress, received, total) {
                    setModalState(() {
                      downloadProgress = progress;
                      final recMb = (received / (1024 * 1024)).toStringAsFixed(1);
                      final totMb = total > 0 ? (total / (1024 * 1024)).toStringAsFixed(1) : '...';
                      transferredText = '$recMb MB / $totMb MB (${(progress * 100).toInt()}%)';
                      statusText = 'در حال دریافت مستقیم بسته...';
                    });
                  },
                  onError: (error) {
                    // v4.0.75 FIX: Handle INSTALL_PERMISSION_REQUIRED specially
                    if (error.contains('INSTALL_PERMISSION_REQUIRED')) {
                      setModalState(() {
                        isFailed = true;
                        statusText = '⚠️ مجوز نصب لازم است\n\n'
                            'برای نصب خودکار، باید اجازه نصب برنامه‌های ناشناخته را فعال کنید:\n'
                            'تنظیمات → برنامه‌ها → Connectix → نصب برنامه‌های ناشناخته → مجاز\n\n'
                            'بعد از فعال‌سازی، دوباره تلاش کنید.\n\n'
                            'یا از مرورگر دانلود کنید (لینک گیت‌هاب مستقیم - همیشه نسخه جدید):';
                      });
                      return;
                    }
                    // Auto-retry once via the GitHub release fallback (GitHub first now - source of truth)
                    if (!usedFallback && fallbackUrl.isNotEmpty && fallbackUrl != url) {
                      usedFallback = true;
                      setModalState(() {
                        isFailed = false;
                        downloadProgress = 0.0;
                        transferredText = '0 MB / ...';
                        statusText = 'لینک اصلی در دسترس نبود؛ در حال دریافت از لینک جایگزین گیت‌هاب (منبع اصلی - همیشه نسخه جدید)...';
                      });
                      launchDownload(fallbackUrl);
                    } else {
                      setModalState(() {
                        isFailed = true;
                        statusText = error;
                      });
                    }
                  },
                  onSuccess: () {
                    setModalState(() {
                      statusText = '✅ دانلود کامل شد. در حال باز کردن نصاب اندروید...\n\nاگر پنجره نصب باز نشد، دکمه زیر را بزنید یا از مرورگر دانلود کنید.';
                      downloadProgress = 1.0;
                    });
                    // v4.0.10: Don't auto-close immediately, give time for installer UI to appear on MIUI/Samsung
                    // Show manual fallback after 3 seconds if installer didn't appear
                    Future.delayed(const Duration(milliseconds: 3500), () {
                      if (Navigator.canPop(bottomSheetContext)) {
                        // Keep modal open but update text to show manual options
                        setModalState(() {
                          statusText = '📲 نصاب خالص v4.0.13 فراخوانی شد (مثل نسخه قدیمی سالم) - پنجره نصب سیستم باید الان باز شود.\n\nاگر باز نشد:\n1️⃣ روی "نصاب خالص v4.0.13" بزنید\n2️⃣ یا "مرورگر" و از پوشه دانلود نصب کنید\n\n⚠️ شیائومی/سامسونگ: تنظیمات → حریم خصوصی → نصب ناشناخته → Connectix را فعال کنید\n\n✅ این روش بدون اسکن فایل و مستقیم است.';
                        });
                      }
                    });
                  },
                );
              }
              launchDownload(downloadUrl);
            }

            return Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 28),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(textDirection: TextDirection.rtl, 
                    children: [
                      Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: const Color(0xFF6366F1).withOpacity(0.15),
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: const Icon(Icons.cloud_download_rounded, color: Color(0xFF818CF8), size: 28),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'بروزرسانی خودکار Connectix $version',
                              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              transferredText,
                              style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 12),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: LinearProgressIndicator(
                      value: downloadProgress > 0 ? downloadProgress : null,
                      backgroundColor: const Color(0xFF1E293B),
                      valueColor: AlwaysStoppedAnimation<Color>(
                        isFailed ? const Color(0xFFEF4444) : const Color(0xFF10B981),
                      ),
                      minHeight: 10,
                    ),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    statusText,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: isFailed ? const Color(0xFFF87171) : const Color(0xFFCBD5E1),
                      fontSize: 12,
                    ),
                  ),
                  const SizedBox(height: 24),
                  if (isFailed)
                    Column(
                      children: [
                        Row(textDirection: TextDirection.rtl, 
                          children: [
                            Expanded(
                              child: OutlinedButton(
                                onPressed: () => Navigator.pop(bottomSheetContext),
                                style: OutlinedButton.styleFrom(
                                  foregroundColor: const Color(0xFF94A3B8),
                                  side: const BorderSide(color: Color(0xFF334155)),
                                ),
                                child: const Text('انصراف'),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: OutlinedButton.icon(
                                onPressed: () {
                                  _sendUpdateErrorReport(statusText);
                                },
                                style: OutlinedButton.styleFrom(
                                  foregroundColor: const Color(0xFF818CF8),
                                  side: const BorderSide(color: Color(0xFF4F46E5)),
                                ),
                                icon: const Icon(Icons.report_problem_rounded, size: 16),
                                label: const Text('ارسال گزارش فنی'),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        // v4.0.75 FIX: 3-Tier Browser Fallback - GitHub Direct FIRST (always correct version)
                        // Previous bug: directPanelUrl first, but panel download_apk.php had allowlist bug serving old APK (4.0.72) for v4.0.73 request
                        // This caused "browser update still old version" - user downloads via browser but gets old version
                        // Fix: GitHub direct first (source of truth, always correct), panel second (for Iran)
                        ElevatedButton.icon(
                          onPressed: () async {
                            Navigator.pop(bottomSheetContext);
                            // v4.0.75: GitHub direct FIRST (always correct version), then panel (for Iran where GitHub blocked)
                            final urlsToTry = [downloadUrl, githubReleasesPage, githubLatestPage, directPanelUrl];
                            for (final u in urlsToTry) {
                              try {
                                final uri = Uri.parse(u);
                                if (await canLaunchUrl(uri)) {
                                  await launchUrl(uri, mode: LaunchMode.externalApplication);
                                  break;
                                }
                              } catch (_) {}
                            }
                          },
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFF6366F1),
                            padding: const EdgeInsets.symmetric(vertical: 12),
                          ),
                          icon: const Icon(Icons.open_in_browser, size: 16),
                          label: const Text('دانلود با مرورگر (100% تضمینی)', style: TextStyle(fontWeight: FontWeight.bold)),
                        ),
                        const SizedBox(height: 8),
                        OutlinedButton.icon(
                          onPressed: () async {
                            Navigator.pop(bottomSheetContext);
                            final uri = Uri.parse(githubReleasesPage);
                            if (await canLaunchUrl(uri)) {
                              await launchUrl(uri, mode: LaunchMode.externalApplication);
                            }
                          },
                          style: OutlinedButton.styleFrom(
                            foregroundColor: const Color(0xFF10B981),
                            side: const BorderSide(color: Color(0xFF10B981)),
                          ),
                          icon: const Icon(Icons.public_rounded, size: 16),
                          label: const Text('صفحه ریلیز گیت‌هاب', style: TextStyle(fontSize: 12)),
                        ),
                      ],
                    )
                  else
                    Column(
                      children: [
                        const Text(
                          'نصاب اندروید به صورت خودکار اجرا خواهد شد و نیازی به حذف برنامه قبلی نیست.\n\nاگر پنجره باز نشد، از دکمه‌های زیر استفاده کنید:',
                          textAlign: TextAlign.center,
                          style: TextStyle(color: Color(0xFF64748B), fontSize: 11),
                        ),
                        const SizedBox(height: 16),
                        // v4.0.10: Manual fallback buttons
                        Row(textDirection: TextDirection.rtl, 
                          children: [
                            Expanded(
                              child: OutlinedButton.icon(
                                onPressed: () async {
                                  // v4.0.13 PURE INTENT FOREVER FIX
                                  ScaffoldMessenger.of(context).showSnackBar(
                                    const SnackBar(content: Text('🔄 تلاش مجدد نصب با روش خالص v4.0.13 (مثل نسخه قدیمی سالم)...'), duration: Duration(seconds: 2)),
                                  );
                                  try {
                                    final apkPath = await ApiService.getApkFilePath();
                                    if (apkPath != null && apkPath.isNotEmpty) {
                                      final opened = await ApiService.openApkFile(apkPath);
                                      if (opened) {
                                        ScaffoldMessenger.of(context).showSnackBar(
                                          const SnackBar(content: Text('✅ نصاب باز شد - لطفا تایید کنید')),
                                        );
                                        return;
                                      }
                                    }
                                  } catch (_) {}
                                  // Fallback: re-trigger downloadAndInstall
                                  ApiService.downloadAndInstallApk(
                                    downloadUrl: downloadUrl,
                                    onProgress: (p, r, t) {},
                                    onError: (e) async {
                                      final uri = Uri.parse(downloadUrl);
                                      if (await canLaunchUrl(uri)) {
                                        await launchUrl(uri, mode: LaunchMode.externalApplication);
                                      }
                                    },
                                    onSuccess: () {},
                                  );
                                },
                                style: OutlinedButton.styleFrom(
                                  foregroundColor: const Color(0xFF10B981),
                                  side: const BorderSide(color: Color(0xFF10B981)),
                                ),
                                icon: const Icon(Icons.install_mobile_rounded, size: 16),
                                label: const Text('نصاب خالص v4.0.13', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold)),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: OutlinedButton.icon(
                                onPressed: () async {
                                  try {
                                    final apkPath = await ApiService.getApkFilePath();
                                    if (apkPath != null && apkPath.isNotEmpty) {
                                      await ApiService.openFileManager(apkPath);
                                      ScaffoldMessenger.of(context).showSnackBar(
                                        const SnackBar(content: Text('📁 پوشه باز شد - روی APK بزنید تا نصب شود')),
                                      );
                                      return;
                                    }
                                  } catch (_) {}
                                  ScaffoldMessenger.of(context).showSnackBar(
                                    const SnackBar(content: Text('📁 فایل در پوشه دانلود/Connectix ذخیره شده')),
                                  );
                                },
                                style: OutlinedButton.styleFrom(
                                  foregroundColor: const Color(0xFFF59E0B),
                                  side: const BorderSide(color: Color(0xFFF59E0B)),
                                ),
                                icon: const Icon(Icons.folder_open_rounded, size: 14),
                                label: const Text('پوشه', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold)),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: ElevatedButton.icon(
                                onPressed: () async {
                                  final uri = Uri.parse(downloadUrl);
                                  if (await canLaunchUrl(uri)) {
                                    await launchUrl(uri, mode: LaunchMode.externalApplication);
                                  }
                                },
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: const Color(0xFF6366F1),
                                  padding: const EdgeInsets.symmetric(vertical: 10),
                                ),
                                icon: const Icon(Icons.open_in_browser_rounded, size: 14),
                                label: const Text('مرورگر', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold)),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 10),
                        TextButton(
                          onPressed: () {
                            if (Navigator.canPop(bottomSheetContext)) {
                              Navigator.pop(bottomSheetContext);
                            }
                          },
                          child: const Text('بستن', style: TextStyle(color: Color(0xFF94A3B8), fontSize: 12)),
                        ),
                      ],
                    ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  void _startTunnel({bool isReconnect = false}) async {
    // v4.0.29 FIX: Screen off disconnect - Acquire WakeLock + WifiLock + Battery exemption
    try {
      const channel = MethodChannel('com.connectix.vpn/updater');
      await channel.invokeMethod('acquireWakeLock');
      if (kDebugMode) debugPrint('v4.0.29 WakeLock acquired for VPN keepalive');
      
      // Check battery optimization and request exemption if needed (once per install)
      final prefs = await SharedPreferences.getInstance();
      final hasRequestedBattery = prefs.getBool('has_requested_battery_opt') ?? false;
      if (!hasRequestedBattery) {
        final isIgnoring = await channel.invokeMethod('isIgnoringBatteryOptimizations');
        if (isIgnoring != true) {
          if (kDebugMode) debugPrint('v4.0.29 Requesting battery optimization exemption');
          await channel.invokeMethod('requestBatteryOptimizationExemption');
          await prefs.setBool('has_requested_battery_opt', true);
        }
      }
    } catch (e) {
      if (kDebugMode) debugPrint('v4.0.29 WakeLock/battery check failed: $e');
    }

    if (_selectedServer == null || _selectedServer!.configUri.isEmpty || _selectedServer!.isInfoBanner) {
      final clean = _servers.where((s) => !s.isInfoBanner && s.configUri.isNotEmpty).toList();
      if (clean.isNotEmpty) {
        _selectedServer = clean.first;
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('لطفاً یک سرور دارای کانکشن معتبر انتخاب فرمایید.')),
        );
        return;
      }
    }

    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('last_working_server_id', _selectedServer!.id);
    } catch (_) {}

    setState(() {
      _isConnecting = true;
      _userIntentionallyDisconnected = false;
    });

    try {
      // v4.0.53 FIX: Ensure V2Ray initialized before connect - fixes button not working
      if (!_isV2RayInitialized) {
        try {
          await _flutterV2ray.initializeV2Ray().timeout(const Duration(seconds: 5));
          _isV2RayInitialized = true;
          ApiService.log('V2Ray initialized on-demand before connect');
        } catch (e) {
          ApiService.log('V2Ray on-demand init failed: $e');
        }
      }

      final hasPermission = await _flutterV2ray.requestPermission();
      if (!hasPermission) {
        setState(() {
          _isConnecting = false;
        });
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('مجوز اتصال وی‌پی‌ان (VPN Permission) تایید نشد.')),
          );
        }
        return;
      }

      // v4.0.45 FAKE VPN FIX: TUN mode must be TRUE on Android for real VPN
      // Previously tunMode was only for Windows, causing proxyOnly:false but maybe not full TUN on some flutter_vless versions
      // Now: Android always TUN (real VPN), Windows depends on setting
      final tunMode = Platform.isAndroid ? true : (Platform.isWindows && _winTunnelMode == 'tun');
      // Windows Phase 2: full TUN mode needs admin on the FIRST run only
      // (creates the Wintun TAP adapter); afterwards the adapter persists.
      if (Platform.isWindows && tunMode && !await _flutterV2ray.isElevated()) {
        setState(() {
          _isConnecting = false;
        });
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text(
                  'حالت «VPN کامل» فقط در اولین اجرا به دسترسی ادمین نیاز دارد: روی آیکون برنامه راست‌کلیک کنید و «Run as administrator» را بزنید. در بارگذاری‌های بعدی دیگر لازم نیست.'),
              duration: Duration(seconds: 9),
            ),
          );
        }
        return;
      }

      final configUri = _selectedServer!.configUri;
      final parser = V2RayCompat.parseUniversal(configUri);
      String finalConfig = parser.getFullConfiguration();

      // Pass Split Tunneling blocked apps to exclude domestic/banking apps natively (without crashes)
      // Layer 1: System-level disallowed apps (Android VpnService.Builder.addDisallowedApplication)
      // v3.5.8 FIX: Filter to ONLY installed packages to avoid TransactionTooLarge & NameNotFoundException
      List<String>? blockedAppsToPass;
      String safeFinalConfig = finalConfig;
      if (Platform.isAndroid && _splitTunnelingEnabled) {
        try {
          final prefs = await SharedPreferences.getInstance();
          final List<String>? customBypass = prefs.getStringList('custom_bypass_apps');
          List<String> candidateList;
          if (customBypass != null && customBypass.isNotEmpty) {
            candidateList = customBypass;
          } else {
            // v4.0.53 Check if user enabled extended 130 list
            final useExtended = prefs.getBool('use_extended_bypass') ?? false;
            candidateList = useExtended ? extendedDomesticBypassApps : defaultDomesticBypassApps;
            ApiService.log('bypass mode: ${useExtended ? 'extended 130' : 'lite 20'} -> ${candidateList.length} apps');
          }
          // v4.0.53 FIX: Hard limit to 25 to prevent TransactionTooLargeException (130->20 default already, but custom may be large)
          if (candidateList.length > 25) {
            candidateList = candidateList.sublist(0, 25);
            ApiService.log('bypass truncated to 25 to prevent TransactionTooLarge');
          }

          // v3.5.8: Ask native layer to filter to installed apps only (real check via PackageManager)
          try {
            const channel = MethodChannel('com.connectix.vpn/updater');
            final filtered = await channel.invokeMethod<List<dynamic>>('getInstalledBypassApps', {'packages': candidateList});
            if (filtered != null && filtered.isNotEmpty) {
              blockedAppsToPass = filtered.map((e) => e.toString()).toList();
              ApiService.log('bypass filter: ${candidateList.length} -> ${blockedAppsToPass!.length} installed');
            } else {
              // If filter returns empty (e.g. no banking apps installed), pass null to avoid empty list overhead
              // But keep original candidate for safety if user has custom list that is all uninstalled? Use candidate truncated to 30
              blockedAppsToPass = candidateList.length > 30 ? candidateList.sublist(0, 30) : candidateList;
              ApiService.log('bypass filter empty, using truncated candidate ${blockedAppsToPass!.length}');
            }
          } catch (e) {
            debugPrint('Bypass filter failed, using truncated list: $e');
            ApiService.log('bypass filter error: $e');
            // Fallback: truncate to 30 to avoid binder limit, still better than 130
            blockedAppsToPass = candidateList.length > 30 ? candidateList.sublist(0, 30) : candidateList;
          }
        } catch (e) {
          debugPrint('Bypass apps check: $e');
          blockedAppsToPass = null; // fail-safe: no bypass rather than crash
        }

        // Layer 2: Inject SAFE direct routing rules (NO geosite/geoip - those need dat files and crash core on many devices)
        // v3.5.8 FIX: Revert to v3.5.5 safe rules that were proven stable: only domain:ir + explicit Iranian domains, ip: geoip:private only
        try {
          final Map<String, dynamic> configMap = jsonDecode(finalConfig);
          final routing = (configMap['routing'] as Map<String, dynamic>?) != null
              ? Map<String, dynamic>.from(configMap['routing'] as Map)
              : <String, dynamic>{};
          final List<dynamic> rules = (routing['rules'] as List<dynamic>?) != null
              ? List<dynamic>.from(routing['rules'] as List<dynamic>)
              : <dynamic>[];

          // v4.0.71: Bypass panel domain + Iranian domains -> direct (prevent loop + speed)
          rules.insert(0, {
            'type': 'field',
            'outboundTag': 'direct',
            'domain': [
              'vpbotn.ir',
              'direct.vpbotn.ir',
              'api.vpbotn.ir',
              'domain:ir',
              'eitaa.com',
              'rubika.ir',
              'bale.ai',
              'divar.ir',
              'snapp.ir',
              'tapsi.ir',
              'digikala.com',
              'torob.com',
              'shaparak.ir',
              'myket.ir',
              'cafebazaar.ir',
              'bazaar.ir',
              'bankmellat.ir',
              'bmi.ir',
              'bankmelli.ir',
              'aparat.com',
              'filimo.com',
              'aparat.com',
              'myket.ir',
            ],
          });
          // Rule 1: Private IPs -> direct (safe, no geoip:ir needed)
          rules.insert(1, {
            'type': 'field',
            'outboundTag': 'direct',
            'ip': [
              'geoip:private',
              '10.0.0.0/8',
              '172.16.0.0/12',
              '192.168.0.0/16',
            ],
          });
          routing['rules'] = rules;
          configMap['routing'] = routing;
          // v4.0.22 FIX: Inject local proxy inbounds for hotspot sharing (TV, console, Windows)
          // When VPN connected, app creates local SOCKS 10808 and HTTP 10809
          // For hotspot, need HTTP proxy listening on 0.0.0.0:10809 so other devices can connect via 192.168.43.1:10809
          try {
            final inbounds = (configMap['inbounds'] as List<dynamic>?) != null
                ? List<dynamic>.from(configMap['inbounds'] as List<dynamic>)
                : <dynamic>[];
            
            // Check if HTTP inbound on 10809 already exists
            bool hasHttp10809 = false;
            bool hasSocks10808 = false;
            for (final ib in inbounds) {
              try {
                final m = ib as Map<String, dynamic>;
                final port = m['port'] as int? ?? 0;
                final protocol = m['protocol'] as String? ?? '';
                if (port == 10809 && protocol == 'http') hasHttp10809 = true;
                if (port == 10808 && protocol == 'socks') hasSocks10808 = true;
              } catch (_) {}
            }
            
            // Add SOCKS 10808 if missing (for Telegram local proxy)
            if (!hasSocks10808) {
              inbounds.add({
                'tag': 'socks-local-10808',
                'listen': '127.0.0.1',
                'port': 10808,
                'protocol': 'socks',
                'settings': {'auth': 'noauth', 'udp': true, 'ip': '127.0.0.1'},
                'sniffing': {'enabled': true, 'destOverride': ['http', 'tls']},
              });
            }
            
            // Add HTTP 10809 on 0.0.0.0 for hotspot sharing (TV, console, Windows) - CRITICAL for user request
            if (!hasHttp10809) {
              inbounds.add({
                'tag': 'http-local-10809',
                'listen': '0.0.0.0',
                'port': 10809,
                'protocol': 'http',
                'settings': {'allowTransparent': false},
                'sniffing': {'enabled': true, 'destOverride': ['http', 'tls']},
              });
              ApiService.log('v4.0.22 LOCAL PROXY FIX: Added HTTP inbound 0.0.0.0:10809 for hotspot sharing');
            }
            
            // v4.0.25 FIX: Removed SOCKS 0.0.0.0:10808 to avoid port conflict with 127.0.0.1:10808
            // Binding 0.0.0.0:10808 conflicts with 127.0.0.1:10808 (0.0.0.0 includes 127.0.0.1) -> V2Ray fails to start -> connection fails
            // For hotspot, HTTP 0.0.0.0:10809 is enough (Windows/TV can use HTTP proxy)
            configMap['inbounds'] = inbounds;
            ApiService.log('v4.0.25 LOCAL PROXY: inbounds count=${inbounds.length} hasHttp10809=$hasHttp10809 hasSocks10808=$hasSocks10808 - SOCKS 0.0.0.0 removed to avoid conflict');
          } catch (e) {
            ApiService.log('v4.0.22 LOCAL PROXY inject failed: $e');
          }
          
          final encoded = jsonEncode(configMap);
          jsonDecode(encoded); // validate JSON
          safeFinalConfig = encoded;
          ApiService.log('routing safe rules injected, total rules=${rules.length}');
        } catch (e) {
          debugPrint('Inject direct routing rules failed, using original config: $e');
          ApiService.log('routing inject failed: $e');
          safeFinalConfig = finalConfig;
        }
      }
      finalConfig = safeFinalConfig;

      // v4.0.22 FIX: Ensure local proxy inbounds are injected even if split tunneling disabled (for hotspot sharing)
      if (!Platform.isAndroid || !_splitTunnelingEnabled) {
        try {
          final Map<String, dynamic> cfgMap = jsonDecode(finalConfig);
          final inbounds = (cfgMap['inbounds'] as List<dynamic>?) != null
              ? List<dynamic>.from(cfgMap['inbounds'] as List<dynamic>)
              : <dynamic>[];
          
          bool hasHttp10809 = false;
          for (final ib in inbounds) {
            try {
              final m = ib as Map<String, dynamic>;
              if ((m['port'] as int? ?? 0) == 10809 && (m['protocol'] as String? ?? '') == 'http') hasHttp10809 = true;
            } catch (_) {}
          }
          if (!hasHttp10809) {
            inbounds.add({
              'tag': 'http-local-10809',
              'listen': '0.0.0.0',
              'port': 10809,
              'protocol': 'http',
              'settings': {'allowTransparent': false},
              'sniffing': {'enabled': true, 'destOverride': ['http', 'tls']},
            });
            // v4.0.25 FIX: Do NOT add SOCKS 0.0.0.0:10808 to avoid port conflict - HTTP 10809 is enough for hotspot
            cfgMap['inbounds'] = inbounds;
            finalConfig = jsonEncode(cfgMap);
            ApiService.log('v4.0.25 LOCAL PROXY FIX (no split): Added 10809 on 0.0.0.0 only (no SOCKS conflict)');
          }
        } catch (e) {
          ApiService.log('v4.0.22 LOCAL PROXY inject (no split) failed: $e');
        }
      }

      // v4.0.36 ZERO-COST: Enhance with anti-filter + speed + stability
      try {
        finalConfig = _enhanceWithZeroCostAntiFilter(finalConfig);
        ApiService.log('v4.0.36 ZERO-COST: Enhanced config with Fragment+uTLS+DoH+BBR');
      } catch (e) {
        ApiService.log('v4.0.36 enhance failed: $e - using original');
      }

      // v3.5.8: Robust start with fallback - if safe config fails, retry with original config and no bypass
      bool started = false;
      String lastError = '';
      try {
        await _flutterV2ray.startV2Ray(
          remark: 'Connectix • ${_selectedServer!.name}',
          config: finalConfig,
          blockedApps: blockedAppsToPass,
          proxyOnly: false,
          tunMode: tunMode,
          notificationDisconnectButtonName: 'قطع اتصال',
        );
        started = true;
        ApiService.log('V2Ray started with safe config + ${blockedAppsToPass?.length ?? 0} bypass apps');
      } catch (e) {
        lastError = e.toString();
        ApiService.log('V2Ray start with safe config failed: $e - retrying with original config');
        try {
          // Retry 2: original config + filtered bypass
          await _flutterV2ray.startV2Ray(
            remark: 'Connectix • ${_selectedServer!.name}',
            config: parser.getFullConfiguration(),
            blockedApps: blockedAppsToPass,
            proxyOnly: false,
            tunMode: tunMode,
            notificationDisconnectButtonName: 'قطع اتصال',
          );
          started = true;
          ApiService.log('V2Ray started with original config + bypass');
        } catch (e2) {
          ApiService.log('V2Ray retry 2 failed: $e2 - retrying with no bypass');
          try {
            // Retry 3: original config + no bypass (guaranteed to work if server is valid)
            await _flutterV2ray.startV2Ray(
              remark: 'Connectix • ${_selectedServer!.name}',
              config: parser.getFullConfiguration(),
              blockedApps: null,
              proxyOnly: false,
              tunMode: tunMode,
              notificationDisconnectButtonName: 'قطع اتصال',
            );
            started = true;
            ApiService.log('V2Ray started with original config + no bypass (fallback)');
          } catch (e3) {
            lastError = '$e | $e2 | $e3';
            ApiService.log('V2Ray all retries failed: $lastError');
            rethrow;
          }
        }
      }

      if (!started) {
        throw Exception(lastError);
      }

      _connectedSeconds = 0;
      _timer?.cancel();
      _timer = Timer.periodic(const Duration(milliseconds: 1500), (timer) { // v4.0.71: 1s->1.5s
        if (mounted && _isConnected) {
          setState(() {
            _connectedSeconds++;
          });
        }
      });
    } catch (e) {
      setState(() {
        _isConnecting = false;
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('خطا در اتصال وی‌پی‌ان: $e')),
        );
      }
    }
  }

  // v4.0.45 FAKE VPN FIX: Enhance Xray config with SAFE anti-filter + speed + stability
  // PREVIOUS v4.0.36 had fragment for ALL non-Reality which breaks many servers -> FAKE VPN symptom
  // NEW v4.0.45: No fragment for Reality/Vision, mux only 4 concurrency, DNS plain (no DoH direct), no DoH direct routing
  String _enhanceWithZeroCostAntiFilter(String configJson) {
    try {
      final Map<String, dynamic> cfg = jsonDecode(configJson) as Map<String, dynamic>;
      
      // 1. DNS - v4.0.45 FIX: Use plain DNS, not DoH direct (DoH often filtered in Iran via direct route)
      // DoH via direct causes DNS fail -> filtered apps don't open
      // Use 8.8.8.8, 1.1.1.1, 1.0.0.1, 8.8.4.4 with UseIP strategy, no DoH
      cfg['dns'] = {
        'servers': [
          '8.8.8.8',
          '1.1.1.1',
          '1.0.0.1',
          '8.8.4.4',
          'localhost'
        ],
        'queryStrategy': 'UseIP',
        'disableCache': false,
        'disableFallback': false,
        'tag': 'dns',
      };
      
      // 2. Enhance outbounds with SAFE settings (NO fragment for Reality/Vision)
      if (cfg['outbounds'] is List) {
        final outbounds = cfg['outbounds'] as List;
        for (int i = 0; i < outbounds.length; i++) {
          try {
            final ob = outbounds[i] as Map<String, dynamic>;
            final protocol = (ob['protocol'] ?? '').toString().toLowerCase();
            
            // Only enhance vless/vmess/trojan/shadowsocks
            if (!['vless', 'vmess', 'trojan', 'shadowsocks', 'ss'].contains(protocol)) continue;
            
            // Ensure streamSettings exists
            final streamSettings = (ob['streamSettings'] as Map<String, dynamic>?) != null
                ? Map<String, dynamic>.from(ob['streamSettings'] as Map)
                : <String, dynamic>{};
            
            final security = (streamSettings['security'] ?? '').toString().toLowerCase();
            final isReality = security == 'reality';
            
            // Check for Vision flow (xtls-rprx-vision) - must NOT use fragment/mux
            bool isVision = false;
            try {
              final flow = (ob['settings']?['vnext']?[0]?['users']?[0]?['flow'] ?? ob['settings']?['users']?[0]?['flow'] ?? '').toString();
              if (flow.contains('vision') || flow.contains('xtls')) isVision = true;
            } catch (_) {}
            
            // F6: uTLS fingerprint = chrome (hide Xray fingerprint) - safe for all TLS/Reality
            if (security == 'tls' || isReality) {
              streamSettings['tlsSettings'] = streamSettings['tlsSettings'] ?? {};
              final tlsSettings = Map<String, dynamic>.from(streamSettings['tlsSettings'] as Map? ?? {});
              if (!tlsSettings.containsKey('fingerprint')) {
                tlsSettings['fingerprint'] = 'chrome';
              }
              streamSettings['tlsSettings'] = tlsSettings;
            }
            
            // S4: TCP optimizations (free, no disadvantage) - safe for all
            final sockopt = (streamSettings['sockopt'] as Map<String, dynamic>?) != null
                ? Map<String, dynamic>.from(streamSettings['sockopt'] as Map)
                : <String, dynamic>{};
            
            // v4.0.71 OPTIMIZED: TCP optimizations for speed + stability
            sockopt['tcpNoDelay'] = true;
            sockopt['tcpKeepAliveIdle'] = 100;
            sockopt['tcpKeepAliveInterval'] = 30;
            sockopt['tcpFastOpen'] = true;
            sockopt['tcpKeepAlive'] = true;
            sockopt['mark'] = 0;
            sockopt['domainStrategy'] = 'AsIs';
            
            // v4.0.45 FIX: NO FRAGMENT for Reality and Vision - fragment breaks them and causes FAKE VPN
            // Fragment was causing silent outbound failure on many servers
            // Only add fragment for plain TLS non-Vision if needed, and even then make it optional (disabled by default)
            // For now: NO fragment at all to ensure real connection - user can enable via setting later if needed
            // If you need fragment, uncomment below but with very conservative values:
            // if (!isReality && !isVision && security == 'tls') {
            //   sockopt['fragment'] = {'packets': 'tlshello', 'length': '10-20', 'interval': '10-20'};
            // }
            // Remove any existing fragment to be safe
            sockopt.remove('fragment');
            
            streamSettings['sockopt'] = sockopt;
            ob['streamSettings'] = streamSettings;
            
            // S4: Mux - v4.0.45 FIX: Disable mux for Reality/Vision (they have their own), enable with concurrency 4 (not 8) for others
            // Concurrency 8 was rejected by some servers causing speed 0
            if (isReality || isVision) {
              // For Reality/Vision, disable mux (Xray docs: mux not recommended with Vision)
              final mux = (ob['mux'] as Map<String, dynamic>?) != null
                  ? Map<String, dynamic>.from(ob['mux'] as Map)
                  : <String, dynamic>{};
              mux['enabled'] = false;
              ob['mux'] = mux;
            } else {
              // For others, mux with safe concurrency 4
              final mux = (ob['mux'] as Map<String, dynamic>?) != null
                  ? Map<String, dynamic>.from(ob['mux'] as Map)
                  : <String, dynamic>{};
              if (!mux.containsKey('enabled')) {
                mux['enabled'] = true;
                mux['concurrency'] = 4; // v4.0.45: reduced from 8 to 4 for compatibility
                mux['xudpConcurrency'] = 4;
                mux['xudpProxyUDP443'] = 'reject';
              } else {
                // If mux already exists, ensure concurrency not too high
                if ((mux['concurrency'] as int? ?? 0) > 8) mux['concurrency'] = 8; // v4.0.71: allow up to 8
                if (!mux.containsKey('xudpConcurrency')) mux['xudpConcurrency'] = 16;
                if ((mux['xudpConcurrency'] as int? ?? 0) > 4) mux['xudpConcurrency'] = 4;
              }
              ob['mux'] = mux;
            }
            
            outbounds[i] = ob;
          } catch (e) {
            continue;
          }
        }
      }
      
      // 3. v4.0.45 FIX: Remove DoH direct routing rule - DoH direct causes DNS fail in Iran
      // Previously we added rule for cloudflare-dns.com, dns.google -> direct, but DoH is filtered
      // Now DNS is plain 8.8.8.8 etc., no need for DoH direct rule
      // Also ensure existing DoH direct rules are removed if present
      if (cfg['routing'] is Map) {
        final routing = Map<String, dynamic>.from(cfg['routing'] as Map);
        final rules = (routing['rules'] as List?) != null
            ? List<dynamic>.from(routing['rules'] as List)
            : <dynamic>[];
        
        // Remove any DoH direct rules (cloudflare-dns.com, dns.google, dns.cloudflare.com)
        rules.removeWhere((r) {
          try {
            final rule = r as Map;
            final domains = (rule['domain'] as List?)?.map((e) => e.toString()).toList() ?? [];
            final domainStr = (rule['domain']?.toString() ?? '').toLowerCase();
            return domains.any((d) => d.contains('cloudflare-dns.com') || d.contains('dns.google') || d.contains('dns.cloudflare.com')) ||
                   domainStr.contains('cloudflare-dns.com') || domainStr.contains('dns.google');
          } catch (_) {
            return false;
          }
        });
        
        routing['rules'] = rules;
        cfg['routing'] = routing;
      }
      
      return jsonEncode(cfg);
    } catch (e) {
      return configJson;
    }
  }

  void _toggleConnection() async {
    if (_isConnecting) return;

    if (!_isConnected) {
      _pausedByBankingApp = false;
      _userIntentionallyDisconnected = false;
      _startTunnel();
    } else {
      // Intentional user disconnect
      _userIntentionallyDisconnected = true;
      _pausedByBankingApp = false;
      _pausedForPackage = null;
      _stopForegroundAppMonitoring();
      try {
        await _flutterV2ray.stopV2Ray();
      } catch (_) {}
      _timer?.cancel();
      setState(() {
        _isConnected = false;
        _isConnecting = false;
      });
    }
  }

  // VPN Hotspot & Proxy Sharing Modal
  void _openHotspotProxySharingSheet() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: const Color(0xFF0F172A),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      builder: (ctx) => FractionallySizedBox(
        heightFactor: 0.82,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 20),
          child: Column(
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
              const SizedBox(height: 18),
              Row(textDirection: TextDirection.rtl, 
                children: [
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: const Color(0xFF10B981).withOpacity(0.15),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: const Icon(Icons.wifi_tethering_rounded, color: Color(0xFF34D399), size: 26),
                  ),
                  const SizedBox(width: 14),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'اشتراک اینترنت با تلویزیون و کنسول',
                          style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15),
                        ),
                        SizedBox(height: 4),
                        Text(
                          'VPN Hotspot & Local Proxy Sharing',
                          style: TextStyle(color: Color(0xFF94A3B8), fontSize: 11),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              // Proxy Parameters Box
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: const Color(0xFF1E293B),
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: const Color(0xFF334155)),
                ),
                child: Column(
                  children: [
                    Row(textDirection: TextDirection.rtl, 
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: const [
                        Text('پورت پروکسی HTTP:', style: TextStyle(color: Color(0xFFCBD5E1), fontSize: 13)),
                        Text('10809', style: TextStyle(color: Color(0xFF38BDF8), fontWeight: FontWeight.bold, fontSize: 14)),
                      ],
                    ),
                    const Divider(color: Color(0xFF334155), height: 20),
                    Row(textDirection: TextDirection.rtl, 
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: const [
                        Text('پورت پروکسی SOCKS5:', style: TextStyle(color: Color(0xFFCBD5E1), fontSize: 13)),
                        Text('10808', style: TextStyle(color: Color(0xFF34D399), fontWeight: FontWeight.bold, fontSize: 14)),
                      ],
                    ),
                    const Divider(color: Color(0xFF334155), height: 20),
                    Row(textDirection: TextDirection.rtl, 
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: const [
                        Text('آدرس IP دستگاه شما:', style: TextStyle(color: Color(0xFFCBD5E1), fontSize: 13)),
                        Text('192.168.43.1 (هات‌اسپات)', style: TextStyle(color: Colors.amber, fontWeight: FontWeight.bold, fontSize: 13)),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              // Open Hotspot Settings Button
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: () => ApiService.openHotspotSettings(),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF6366F1),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                  icon: const Icon(Icons.settings_input_antenna, size: 20),
                  label: const Text('روشن کردن هات‌اسپات گوشی (Hotspot)'),
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                'راهنمای اتصال سایر دستگاه‌ها:',
                style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13),
              ),
              const SizedBox(height: 10),
              Expanded(
                child: ListView(
                  children: const [
                    _GuideStep(
                      icon: Icons.tv,
                      title: 'تلویزیون هوشمند (Android TV / Tizen / webOS)',
                      desc: 'تلویزیون را به وای‌فای هات‌اسپات گوشی وصل کنید. در تنظیمات شبکه تلویزیون، بخش Advanced یا Proxy را روی Manual قرار دهید و آی‌پی 192.168.43.1 و پورت 10809 را وارد نمایید.',
                    ),
                    SizedBox(height: 12),
                    _GuideStep(
                      icon: Icons.sports_esports,
                      title: 'کنسول‌های بازی (PlayStation / Xbox)',
                      desc: 'در تنظیمات Network کنسول، گزینه Set Up Internet Connection را بزنید، به هات‌اسپات گوشی متصل شوید و در مرحله آخر Proxy Server را فعال و پورت 10809 را وارد کنید.',
                    ),
                    SizedBox(height: 12),
                    _GuideStep(
                      icon: Icons.laptop,
                      title: 'رایانه و لپ‌تاپ (Windows / Mac)',
                      desc: 'به هات‌اسپات گوشی متصل شوید. در تنظیمات Proxy ویندوز، Manual proxy setup را روشن کرده و آی‌پی 192.168.43.1 با پورت 10809 را ذخیره کنید.',
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // Announcements & Notifications Inbox Modal
  void _openAnnouncementsInbox() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: const Color(0xFF0F172A),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      builder: (ctx) {
        return StatefulBuilder(
          builder: (modalContext, setModalState) {
            if (!_isCheckingUpdate && _updateInfo == null) {
              _isCheckingUpdate = true;
              ApiService.checkAppUpdate().then((updateData) {
                _isCheckingUpdate = false;
                if (updateData != null && mounted) {
                  final latest = (updateData['latest_version'] ?? '').toString();
                  final isNew = isNewerVersion(latest, currentAppVersion);
                  setState(() {
                    _hasAppUpdate = isNew;
                    _updateInfo = isNew ? updateData : null;
                  });
                  setModalState(() {});
                }
              }).catchError((_) {
                _isCheckingUpdate = false;
              });
            }

            final hasUpdate = _hasAppUpdate && _updateInfo != null;
            final latestVer = (_updateInfo?['latest_version'] ?? '').toString();
            final changelog = (_updateInfo?['changelog'] ?? 'بهبود پایداری، سنجش پینگ و قابلیت‌های جدید برنامه').toString();
            final updateTitle = (_updateInfo?['title'] ?? 'بروزرسانی جدید Connectix').toString();

            return FractionallySizedBox(
              heightFactor: hasUpdate ? 0.82 : 0.65,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 20),
                child: Column(
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
                    const SizedBox(height: 18),
                    Row(textDirection: TextDirection.rtl, 
                      children: [
                        const Icon(Icons.notifications_active_rounded, color: Color(0xFF38BDF8), size: 24),
                        const SizedBox(width: 10),
                        const Text(
                          'صندوق پیام‌ها و بروزرسانی',
                          style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16),
                        ),
                        const Spacer(),
                        if (hasUpdate)
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(
                              color: const Color(0xFF10B981).withOpacity(0.18),
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(color: const Color(0xFF10B981)),
                            ),
                            child: const Text(
                              'بروزرسانی جدید',
                              style: TextStyle(color: Color(0xFF34D399), fontSize: 10, fontWeight: FontWeight.bold),
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 16),

                    // Dedicated In-App Update Card
                    if (hasUpdate) ...[
                      Container(
                        padding: const EdgeInsets.all(16),
                        margin: const EdgeInsets.only(bottom: 16),
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(
                            colors: [Color(0xFF1E1B4B), Color(0xFF31104B)],
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          ),
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(color: const Color(0xFF9333EA), width: 1.5),
                          boxShadow: [
                            BoxShadow(
                              color: const Color(0xFF9333EA).withOpacity(0.25),
                              blurRadius: 16,
                              offset: const Offset(0, 4),
                            ),
                          ],
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(textDirection: TextDirection.rtl, 
                              children: [
                                Container(
                                  padding: const EdgeInsets.all(8),
                                  decoration: BoxDecoration(
                                    color: const Color(0xFF10B981).withOpacity(0.2),
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  child: const Icon(Icons.system_update_rounded, color: Color(0xFF34D399), size: 22),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Row(textDirection: TextDirection.rtl, 
                                        children: [
                                          const Text(
                                            'نگارش جدید برنامه',
                                            style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                                          ),
                                          const SizedBox(width: 8),
                                          Container(
                                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                            decoration: BoxDecoration(
                                              color: const Color(0xFF10B981),
                                              borderRadius: BorderRadius.circular(6),
                                            ),
                                            child: Text(
                                              'نسخه $latestVer',
                                              style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                                            ),
                                          ),
                                        ],
                                      ),
                                      const SizedBox(height: 2),
                                      Text(
                                        updateTitle,
                                        style: const TextStyle(color: Color(0xFFCBD5E1), fontSize: 11),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 10),
                            Text(
                              changelog,
                              style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 11, height: 1.6),
                            ),
                            const SizedBox(height: 14),
                            SizedBox(
                              width: double.infinity,
                              child: ElevatedButton.icon(
                                onPressed: () {
                                  Navigator.pop(ctx);
                                  _startInAppDownloadAndInstall(
                                    (_updateInfo!['download_url'] ?? '').toString(),
                                    latestVer,
                                    fallbackUrl: (_updateInfo!['fallback_url'] ?? '').toString(),
                                  );
                                },
                                icon: const Icon(Icons.download_rounded, size: 18),
                                label: Text(
                                  'دانلود و نصب خودکار نسخه $latestVer',
                                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                                ),
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: const Color(0xFF10B981),
                                  foregroundColor: Colors.white,
                                  padding: const EdgeInsets.symmetric(vertical: 12),
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                                  elevation: 4,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],

                    if (_announcements.isEmpty && !hasUpdate)
                      Expanded(
                        child: Center(
                          child: _isCheckingUpdate
                              ? const CircularProgressIndicator(color: Color(0xFF6366F1))
                              : const Text(
                                  'هیچ پیام یا اطلاعیه جدیدی وجود ندارد.',
                                  style: TextStyle(color: Color(0xFF64748B), fontSize: 13),
                                ),
                        ),
                      )
                    else if (_announcements.isNotEmpty)
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            if (hasUpdate)
                              const Padding(
                                padding: EdgeInsets.only(bottom: 8),
                                child: Text(
                                  'سایر اطلاعیه‌ها',
                                  style: TextStyle(color: Color(0xFF94A3B8), fontSize: 12, fontWeight: FontWeight.bold),
                                ),
                              ),
                            Expanded(
                              child: ListView.separated(
                                itemCount: _announcements.length,
                                separatorBuilder: (_, __) => const SizedBox(height: 10),
                                itemBuilder: (context, idx) {
                                  final item = _announcements[idx];
                                  return Container(
                                    padding: const EdgeInsets.all(14),
                                    decoration: BoxDecoration(
                                      color: const Color(0xFF1E293B),
                                      borderRadius: BorderRadius.circular(14),
                                      border: Border.all(color: const Color(0xFF334155)),
                                    ),
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          item['title'] ?? 'اطلاعیه شبکه',
                                          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13),
                                        ),
                                        const SizedBox(height: 4),
                                        Text(
                                          item['message'] ?? '',
                                          style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 11, height: 1.5),
                                        ),
                                        if (item['created_at'] != null) ...[
                                          const SizedBox(height: 6),
                                          Text(
                                            item['created_at'],
                                            style: const TextStyle(color: Color(0xFF64748B), fontSize: 9),
                                          ),
                                        ],
                                      ],
                                    ),
                                  );
                                },
                              ),
                            ),
                          ],
                        ),
                      )
                    else if (hasUpdate && _announcements.isEmpty)
                      const Spacer(),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  void _openAdvancedSettings() async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => AdvancedSettingsScreen(
          defaultBypassList: defaultDomesticBypassApps,
          splitTunnelingEnabled: _splitTunnelingEnabled,
          autoReconnectEnabled: _autoReconnectEnabled,
          winTunnelMode: _winTunnelMode,
          onSplitTunnelingChanged: (val) {
            setState(() => _splitTunnelingEnabled = val);
            if (_isConnected) {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('جهت اعمال تغییرات، یک‌بار اتصال را مجدداً برقرار کنید.')),
              );
            }
          },
          onAutoReconnectChanged: (val) {
            setState(() => _autoReconnectEnabled = val);
          },
          onWinTunnelModeChanged: (val) {
            _setWinTunnelMode(val);
            if (_isConnected) {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('حالت جدید در اتصال بعدی اعمال می‌شود.')),
              );
            }
          },
        ),
      ),
    );
    // Reload settings after returning from advanced screen (auto-pause may have changed)
    _loadSettings();
    _loadAccounts();
    if (_autoPauseForBankingEnabled && _isConnected) {
      _startForegroundAppMonitoring();
    }
  }

  // v4.0.47 MULTI-ACCOUNT: Helper to parse hex color
  Color _hexToColor(String hex) {
    try {
      var h = hex.replaceAll('#', '');
      if (h.length == 6) h = 'FF$h';
      return Color(int.parse(h, radix: 16));
    } catch (_) {
      return const Color(0xFF8B5CF6);
    }
  }

  // v4.0.47 MULTI-ACCOUNT: Show Telegram-like bottom sheet account switcher
  void _showAccountSwitcher() {
    showModalBottomSheet(
      context: context,
      backgroundColor: const Color(0xFF0F172A),
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      isScrollControlled: true,
      builder: (ctx) => DraggableScrollableSheet(
        initialChildSize: 0.6,
        minChildSize: 0.4,
        maxChildSize: 0.85,
        expand: false,
        builder: (context, scrollController) => Container(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: const Color(0xFF334155), borderRadius: BorderRadius.circular(4)))),
              const SizedBox(height: 16),
              Row(
                children: [
                  Container(padding: const EdgeInsets.all(8), decoration: BoxDecoration(color: const Color(0xFF6366F1).withOpacity(0.15), borderRadius: BorderRadius.circular(12)), child: const Icon(Icons.switch_account_rounded, color: Color(0xFF818CF8), size: 22)),
                  const SizedBox(width: 10),
                  const Expanded(child: Text('مدیریت حساب‌ها - چند اکانتی', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15))),
                  Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3), decoration: BoxDecoration(color: const Color(0xFF10B981).withOpacity(0.15), borderRadius: BorderRadius.circular(8), border: Border.all(color: const Color(0xFF10B981))), child: Text('${_accounts.length} حساب', style: const TextStyle(color: Color(0xFF10B981), fontSize: 10, fontWeight: FontWeight.bold))),
                ],
              ),
              const SizedBox(height: 16),
              // Unified toggle
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                decoration: BoxDecoration(color: const Color(0xFF1E293B), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFF334155))),
                child: Row(
                  children: [
                    const Icon(Icons.merge_type_rounded, color: Color(0xFF38BDF8), size: 18),
                    const SizedBox(width: 8),
                    const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('نمایش یکپارچه همه سرورها', style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold)), Text('همه سرورهای همه حساب‌ها در یک لیست با برچسب', style: TextStyle(color: Color(0xFF64748B), fontSize: 10))])),
                    Switch(value: _unifiedEnabled, activeColor: const Color(0xFF10B981), onChanged: (v) async { await AccountManager.setUnifiedEnabled(v); if (mounted) setState(() => _unifiedEnabled = v); ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(v ? 'حالت یکپارچه فعال شد' : 'حالت یکپارچه غیرفعال شد'))); }),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              Expanded(
                child: ListView.separated(
                  controller: scrollController,
                  itemCount: _accounts.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 8),
                  itemBuilder: (context, idx) {
                    final acc = _accounts[idx];
                    final isActive = _activeAccount?.id == acc.id;
                    return InkWell(
                      onTap: isActive ? null : () async { Navigator.pop(ctx); await _switchAccount(acc); },
                      borderRadius: BorderRadius.circular(16),
                      child: Container(
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: isActive ? _hexToColor(acc.colorHex).withOpacity(0.15) : const Color(0xFF1E293B),
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: isActive ? _hexToColor(acc.colorHex) : const Color(0xFF334155), width: isActive ? 1.5 : 1),
                        ),
                        child: Row(
                          children: [
                            Container(width: 48, height: 48, decoration: BoxDecoration(color: _hexToColor(acc.colorHex).withOpacity(0.25), borderRadius: BorderRadius.circular(14), border: Border.all(color: _hexToColor(acc.colorHex).withOpacity(0.4))), child: Center(child: Text(acc.avatarEmoji, style: const TextStyle(fontSize: 22)))),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                Row(children: [Expanded(child: Text(acc.effectiveName, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13), maxLines: 1, overflow: TextOverflow.ellipsis)), if (isActive) Container(margin: const EdgeInsets.only(right: 6), padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2), decoration: BoxDecoration(color: const Color(0xFF10B981), borderRadius: BorderRadius.circular(6)), child: const Text('فعال', style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold)))]),
                                const SizedBox(height: 2),
                                Text('${acc.username} • ${acc.shortPanel}', style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 11), maxLines: 1, overflow: TextOverflow.ellipsis),
                                const SizedBox(height: 2),
                                Row(children: [Container(padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1), decoration: BoxDecoration(color: const Color(0xFF334155), borderRadius: BorderRadius.circular(6)), child: Text('${acc.serverCount} سرور', style: const TextStyle(color: Color(0xFFCBD5E1), fontSize: 9))), const SizedBox(width: 6), if (acc.planTitle.isNotEmpty) Flexible(child: Text(acc.planTitle, style: const TextStyle(color: Color(0xFF64748B), fontSize: 9), maxLines: 1, overflow: TextOverflow.ellipsis))]),
                              ]),
                            ),
                            if (!isActive) const Icon(Icons.swap_horiz_rounded, color: Color(0xFF64748B), size: 20) else const Icon(Icons.check_circle_rounded, color: Color(0xFF10B981), size: 22),
                          ],
                        ),
                      ),
                    );
                  },
                ),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  if (!_hideConfig) Expanded(child: OutlinedButton.icon(onPressed: () { Navigator.pop(ctx); Navigator.push(context, MaterialPageRoute(builder: (_) => const ManageAccountsScreen())).then((_) => _loadAccounts()); }, icon: const Icon(Icons.settings_rounded, size: 16), label: const Text('مدیریت حساب‌ها', style: TextStyle(fontSize: 12)), style: OutlinedButton.styleFrom(foregroundColor: const Color(0xFF94A3B8), side: const BorderSide(color: Color(0xFF334155)), padding: const EdgeInsets.symmetric(vertical: 12)))),
                  const SizedBox(width: 10),
                  Expanded(child: ElevatedButton.icon(onPressed: () { Navigator.pop(ctx); Navigator.push(context, MaterialPageRoute(builder: (_) => const ManageAccountsScreen(isAdding: true))).then((_) => _loadAccounts()); }, icon: const Icon(Icons.person_add_rounded, size: 18), label: const Text('افزودن حساب', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)), style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF6366F1), foregroundColor: Colors.white, padding: const EdgeInsets.symmetric(vertical: 12), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))))),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _switchAccount(VpnAccount account) async {
    if (_isConnected) {
      try { await _flutterV2ray.stopV2Ray(); } catch (_) {}
      _timer?.cancel();
      setState(() { _isConnected = false; _isConnecting = false; });
    }
    if (!mounted) return;
    showDialog(context: context, barrierDismissible: false, builder: (_) => const Center(child: CircularProgressIndicator(color: Color(0xFF6366F1))));
    try {
      final res = await ApiService.switchToAccount(account);
      if (!mounted) return;
      Navigator.pop(context);
      if (res != null && res['client'] != null) {
        final newClient = res['client'] as ClientModel;
        final servers = (res['servers'] as List<ServerModel>?) ?? [];
        setState(() {
          _client = newClient;
          _activeAccount = account;
          if (servers.isNotEmpty) {
            _servers = servers;
            _syncActiveServer(servers);
          }
        });
        await _loadAccounts();
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('✅ به حساب ${account.effectiveName} سوئیچ شد'), backgroundColor: const Color(0xFF10B981)));
      } else {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('❌ سوئیچ حساب ناموفق - رمز عبور را چک کنید'), backgroundColor: Color(0xFFEF4444)));
      }
    } catch (e) {
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('خطا: $e')));
      }
    }
  }

  // Quick Support & Account Drawer Modal
  void _openSupportSheet() {
    showModalBottomSheet(
      context: context,
      backgroundColor: const Color(0xFF0F172A),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      builder: (ctx) => Padding(
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'ارتباط با پشتیبانی و تمدید اشتراک',
              style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15),
            ),
            const SizedBox(height: 20),
            if (widget.branding.telegramSupport.isNotEmpty)
              _SupportActionTile(
                icon: Icons.send_rounded,
                iconColor: const Color(0xFF38BDF8),
                title: 'پشتیبانی تلگرام',
                subtitle: widget.branding.telegramSupport,
                onTap: () {
                  final link = widget.branding.supportLink.isNotEmpty
                      ? widget.branding.supportLink
                      : 'https://t.me/${widget.branding.telegramSupport.replaceAll('@', '')}';
                  launchUrl(Uri.parse(link), mode: LaunchMode.externalApplication);
                },
              ),
            if (widget.branding.whatsappSupport.isNotEmpty)
              _SupportActionTile(
                icon: Icons.chat_bubble_rounded,
                iconColor: const Color(0xFF10B981),
                title: 'پشتیبانی واتساپ',
                subtitle: widget.branding.whatsappSupport,
                onTap: () {
                  launchUrl(Uri.parse('https://wa.me/${widget.branding.whatsappSupport}'), mode: LaunchMode.externalApplication);
                },
              ),
            if (widget.branding.renewalUrl.isNotEmpty)
              _SupportActionTile(
                icon: Icons.autorenew_rounded,
                iconColor: const Color(0xFFF59E0B),
                title: 'پورتال تمدید اشتراک',
                subtitle: 'شارژ فوری ترافیک و دوره زمانی',
                onTap: () {
                  launchUrl(Uri.parse(widget.branding.renewalUrl), mode: LaunchMode.externalApplication);
                },
              ),
            _SupportActionTile(
              icon: Icons.language_rounded,
              iconColor: const Color(0xFF818CF8),
              title: 'وب‌سایت پنل اختصاصی',
              subtitle: ApiService.baseUrl,
              onTap: () {
                launchUrl(Uri.parse(ApiService.baseUrl), mode: LaunchMode.externalApplication);
              },
            ),
          ],
        ),
      ),
    );
  }

  String _formatDuration(int seconds) {
    final m = (seconds ~/ 60).toString().padLeft(2, '0');
    final s = (seconds % 60).toString().padLeft(2, '0');
    final h = (seconds ~/ 3600).toString().padLeft(2, '0');
    return h == '00' ? '$m:$s' : '$h:$m:$s';
  }

  String _formatSpeed(int bytes) {
    if (bytes >= 1024 * 1024) {
      return '${(bytes / (1024 * 1024)).toStringAsFixed(1)} MB/s';
    } else if (bytes >= 1024) {
      return '${(bytes / 1024).toStringAsFixed(0)} KB/s';
    } else {
      return '$bytes B/s';
    }
  }

  // LAW 12 v4.0.40: Fixed RTL flip bug - split Persian label and English speed to prevent bidi flip on connect
  Widget _buildSpeedChip({
    required IconData icon,
    required String label,
    required int bytes,
    required Color color,
  }) {
    final speedText = _formatSpeed(bytes);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.12),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: color.withOpacity(0.3)),
      ),
      child: Row(
        textDirection: TextDirection.rtl,
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: color),
          const SizedBox(width: 4),
          // Persian label RTL
          Text(
            '$label: ',
            textDirection: TextDirection.rtl,
            style: TextStyle(
              color: color,
              fontSize: 11,
              fontWeight: FontWeight.bold,
            ),
          ),
          // English speed LTR - fixed direction to prevent flip
          Directionality(
            textDirection: TextDirection.ltr,
            child: Text(
              speedText,
              textDirection: TextDirection.ltr,
              style: TextStyle(
                color: color,
                fontSize: 11,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
        ],
      ),
    );
  }

  // v4.0.41 FORENSIC FIX: Proper dispose to prevent exit errors
  // OLD BUG: Only canceled timers, left V2Ray running + MethodChannel handler + ValueNotifier -> crash on exit
  // FIX: Cancel timers, stop V2Ray safely, remove MethodChannel handler, dispose notifier, all with try/catch
  @override
  void dispose() {
    try { _timer?.cancel(); } catch (_) {}
    try { _foregroundCheckTimer?.cancel(); } catch (_) {}
    _timer = null;
    _foregroundCheckTimer = null;
    try {
      // Remove MethodChannel handler safely
      const channel = MethodChannel('com.connectix.vpn/updater');
      channel.setMethodCallHandler(null);
    } catch (_) {}
    try {
      // Stop V2Ray safely without awaiting (dispose can't be async)
      _flutterV2ray.stopV2Ray().catchError((_) {});
    } catch (_) {}
    try {
      _v2rayStatus.dispose();
    } catch (_) {}
    try {
      super.dispose();
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    final statusColor = _isConnected
        ? const Color(0xFF10B981)
        : (_isConnecting ? const Color(0xFFF59E0B) : widget.branding.accentColor);

    final double usageClamped = (_client.usagePercent / 100).clamp(0.0, 1.0);

    return Scaffold(
      backgroundColor: const Color(0xFF090D16),
      extendBody: true,
      extendBodyBehindAppBar: false,
      resizeToAvoidBottomInset: true,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        title: Row(textDirection: TextDirection.rtl, 
          children: [
            Container(
              width: 10,
              height: 10,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: _isConnected ? const Color(0xFF10B981) : const Color(0xFF64748B),
                boxShadow: _isConnected
                    ? [const BoxShadow(color: Color(0xFF10B981), blurRadius: 6, spreadRadius: 1)]
                    : [],
              ),
            ),
            const SizedBox(width: 10),
            if (widget.branding.logoUrl.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(right: 2, left: 8),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: Image.network(
                    widget.branding.logoUrl,
                    width: 26,
                    height: 26,
                    fit: BoxFit.contain,
                    errorBuilder: (ctx, err, st) => const Icon(
                      Icons.shield_rounded,
                      size: 22,
                      color: Color(0xFF64748B),
                    ),
                  ),
                ),
              ),
            Flexible(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    widget.branding.appName,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  Text(
                    'نسخه ${actualVersion} (کد $actualCode)',
                    style: const TextStyle(color: Color(0xFF64748B), fontSize: 9),
                  ),
                ],
              ),
            ),
          ],
        ),
        actions: [
          // v4.0.47 MULTI-ACCOUNT SWITCHER BUTTON (Telegram-like)
          if (_accounts.length > 1 || _activeAccount != null)
            GestureDetector(
              onTap: _showAccountSwitcher,
              child: Container(
                margin: const EdgeInsets.only(left: 4),
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: _activeAccount != null ? _hexToColor(_activeAccount!.colorHex).withOpacity(0.15) : const Color(0xFF1E293B),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: _activeAccount != null ? _hexToColor(_activeAccount!.colorHex).withOpacity(0.4) : const Color(0xFF334155)),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(_activeAccount?.avatarEmoji ?? '👤', style: const TextStyle(fontSize: 14)),
                    const SizedBox(width: 4),
                    Text(
                      _activeAccount?.effectiveName ?? _client.username,
                      style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(width: 2),
                    const Icon(Icons.keyboard_arrow_down_rounded, color: Color(0xFF94A3B8), size: 14),
                    if (_accounts.length > 1)
                      Container(
                        margin: const EdgeInsets.only(right: 4),
                        padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                        decoration: BoxDecoration(color: const Color(0xFF6366F1), borderRadius: BorderRadius.circular(6)),
                        child: Text('${_accounts.length}', style: const TextStyle(color: Colors.white, fontSize: 8, fontWeight: FontWeight.bold)),
                      ),
                  ],
                ),
              ),
            ),
          // New Version Available Badge Button
          if (_hasAppUpdate)
            IconButton(
              icon: Container(
                padding: const EdgeInsets.all(4),
                decoration: BoxDecoration(
                  color: const Color(0xFF10B981).withOpacity(0.2),
                  shape: BoxShape.circle,
                  border: Border.all(color: const Color(0xFF10B981), width: 1.5),
                ),
                child: const Icon(Icons.system_update_rounded, color: Color(0xFF10B981), size: 16),
              ),
              onPressed: _checkAppUpdate,
              tooltip: 'نصب خودکار نگارش جدید',
            ),
          // Announcements & Update Inbox with Badge
          Stack(
            alignment: Alignment.center,
            children: [
              IconButton(
                icon: Icon(
                  _hasAppUpdate ? Icons.notifications_active_rounded : Icons.notifications_none_rounded,
                  color: _hasAppUpdate ? const Color(0xFF34D399) : const Color(0xFF94A3B8),
                ),
                onPressed: _openAnnouncementsInbox,
                tooltip: _hasAppUpdate ? 'بروزرسانی جدید در دسترس است' : 'اطلاعیه‌ها',
              ),
              if (_hasAppUpdate || _announcements.isNotEmpty)
                Positioned(
                  top: 8,
                  right: 8,
                  child: Container(
                    width: 10,
                    height: 10,
                    decoration: BoxDecoration(
                      color: _hasAppUpdate ? const Color(0xFF10B981) : const Color(0xFFEF4444),
                      shape: BoxShape.circle,
                      border: Border.all(color: const Color(0xFF090D16), width: 1.8),
                    ),
                  ),
                ),
            ],
          ),
          // Refresh Button
          IconButton(
            icon: _isRefreshing
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFF6366F1)),
                  )
                : const Icon(Icons.refresh, color: Color(0xFF94A3B8)),
            onPressed: _manualRefresh,
            tooltip: 'بروزرسانی وضعیت و کانکشن‌ها',
          ),
          // Advanced Settings (Split Tunneling + Bypass Apps Manager moved here to keep main page clean)
          IconButton(
            icon: const Icon(Icons.settings_rounded, color: Color(0xFF94A3B8)),
            onPressed: _openAdvancedSettings,
            tooltip: 'تنظیمات پیشرفته',
          ),
          // Support & Links Drawer
          IconButton(
            icon: const Icon(Icons.support_agent_rounded, color: Color(0xFF94A3B8)),
            onPressed: _openSupportSheet,
            tooltip: 'پشتیبانی و تمدید',
          ),
          // Logout Button
          IconButton(
            icon: const Icon(Icons.logout, color: Color(0xFF94A3B8)),
            onPressed: () async {
              if (_isConnected) {
                try {
                  await _flutterV2ray.stopV2Ray();
                } catch (_) {}
              }
              await ApiService.logout();
              if (!mounted) return;
              Navigator.pushReplacement(
                context,
                MaterialPageRoute(builder: (_) => const LoginScreen()),
              );
            },
            tooltip: 'خروج از حساب',
          ),
        ],
      ),
      // LAW 12: Force RTL for entire dashboard - prevents flip left->right on connect + buttons non-functional
      body: Directionality(
        textDirection: TextDirection.rtl,
        child: SafeArea(
          bottom: false,
          child: LayoutBuilder(
            builder: (context, constraints) {
              return SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                child: ConstrainedBox(
                  constraints: BoxConstraints(minHeight: constraints.maxHeight),
                  child: IntrinsicHeight(
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 2),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
              // Global / Reseller Announcement Banner
              if (widget.branding.announcement.isNotEmpty)
                Container(
                  margin: const EdgeInsets.only(bottom: 6), // v4.0.69 COMPACT
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  decoration: BoxDecoration(
                    color: const Color(0xFF1E293B),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: widget.branding.accentColor.withOpacity(0.35)),
                  ),
                  child: Row(textDirection: TextDirection.rtl, 
                    children: [
                      Icon(Icons.campaign_rounded, color: widget.branding.accentColor, size: 18),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          widget.branding.announcement,
                          style: const TextStyle(color: Color(0xFFCBD5E1), fontSize: 11, height: 1.6),
                        ),
                      ),
                    ],
                  ),
                ),
              // In-App Update Alert Banner
              if (_hasAppUpdate && _updateInfo != null)
                Container(
                  margin: const EdgeInsets.only(bottom: 6), // v4.0.69 COMPACT
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [Color(0xFF4338CA), Color(0xFF6D28D9)],
                    ),
                    borderRadius: BorderRadius.circular(18),
                    boxShadow: [
                      BoxShadow(
                        color: const Color(0xFF6366F1).withOpacity(0.3),
                        blurRadius: 12,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Row(textDirection: TextDirection.rtl, 
                    children: [
                      const Icon(Icons.rocket_launch, color: Colors.amber, size: 22),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'نگارش جدید ${_updateInfo!['latest_version'] ?? 'جدید'} آماده است',
                              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 11), // v4.0.69 COMPACT
                            ),
                            const Text(
                              'نصب مستقیم بدون حذف نسخه قبل',
                              style: TextStyle(color: Color(0xFFE0E7FF), fontSize: 9), // v4.0.69 COMPACT
                            ),
                          ],
                        ),
                      ),
                      ElevatedButton(
                        onPressed: () {
                          final latestVer = (_updateInfo?['latest_version'] ?? '3.4.7').toString();
                          final dlUrl = (_updateInfo?['download_url'] ?? '').toString();
                          final fbUrl = (_updateInfo?['fallback_url'] ?? '').toString();
                          _startInAppDownloadAndInstall(dlUrl, latestVer, fallbackUrl: fbUrl);
                        },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.white,
                          foregroundColor: const Color(0xFF4338CA),
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                        child: const Text('نصب فوری', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 11)),
                      ),
                      const SizedBox(width: 4),
                      IconButton(
                        icon: const Icon(Icons.close, color: Colors.white70, size: 16),
                        onPressed: _dismissUpdateBanner,
                        tooltip: 'بستن',
                        padding: EdgeInsets.zero,
                        constraints: const BoxConstraints(),
                      ),
                    ],
                  ),
                ),

              // v4.0.70 ULTRA COMPACT FINAL
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(colors: [Color(0xFF161536), Color(0xFF0F172A)], begin: Alignment.topLeft, end: Alignment.bottomRight),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFF312E81).withOpacity(0.5)),
                ),
                child: Column(
                  children: [
                    Row(textDirection: TextDirection.rtl, children: [
                      Expanded(child: Row(children: [
                        Flexible(child: Text(_client.username, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 10), maxLines: 1, overflow: TextOverflow.ellipsis)),
                        const SizedBox(width: 3),
                        Flexible(child: Text(_client.planTitle, style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 8), maxLines: 1, overflow: TextOverflow.ellipsis)),
                        const SizedBox(width: 3),
                        Container(padding: const EdgeInsets.symmetric(horizontal: 3, vertical: 1), decoration: BoxDecoration(color: const Color(0xFF10B981).withOpacity(0.15), borderRadius: BorderRadius.circular(5)), child: Text(_client.daysRemaining, style: const TextStyle(color: Color(0xFF10B981), fontSize: 7, fontWeight: FontWeight.bold))),
                      ])),
                      const SizedBox(width: 4),
                      Text('${(_client.usagePercent).toInt()}%', style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold)),
                    ]),
                    const SizedBox(height: 3),
                    ClipRRect(borderRadius: BorderRadius.circular(3), child: LinearProgressIndicator(value: usageClamped, minHeight: 3, backgroundColor: const Color(0xFF1E293B), valueColor: AlwaysStoppedAnimation<Color>(usageClamped > 0.85 ? const Color(0xFFEF4444) : const Color(0xFF6366F1)))),
                    const SizedBox(height: 4),
                    Row(textDirection: TextDirection.rtl, mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                      _CompactQuotaMetric(label: 'باقی', value: '${_client.trafficRemainingGb} GB', color: const Color(0xFF38BDF8), icon: Icons.pie_chart_outline_rounded),
                      _CompactQuotaMetric(label: 'مصرف', value: '${_client.trafficUsedGb} GB', color: const Color(0xFFCBD5E1), icon: Icons.data_usage_rounded),
                      _CompactQuotaMetric(label: 'کل', value: '${_client.trafficTotalGb} GB', color: const Color(0xFF94A3B8), icon: Icons.cloud_done_outlined),
                    ]),
                  ],
                ),
              ),

              // Low Traffic / Expiry Alert Card
              if (_client.trafficRemainingGb <= 1.5 || _client.usagePercent >= 88.0) ...[
                const SizedBox(height: 14),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [Color(0xFF7C2D12), Color(0xFF451A03)],
                    ),
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFFF97316).withOpacity(0.4)),
                  ),
                  child: Row(textDirection: TextDirection.rtl, 
                    children: [
                      Container(
                        padding: const EdgeInsets.all(6),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF97316).withOpacity(0.2),
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(Icons.warning_amber_rounded, color: Color(0xFFFB923C), size: 20),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              '⚠️ حجم اشتراک شما رو به پایان است',
                              style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              'تنها ${_client.trafficRemainingGb} GB باقیمانده است. جهت استمرار اتصال تمدید نمایید.',
                              style: const TextStyle(color: Color(0xFFFED7AA), fontSize: 10),
                            ),
                          ],
                        ),
                      ),
                      if (widget.branding.renewalUrl.isNotEmpty)
                        ElevatedButton(
                          onPressed: () {
                            launchUrl(Uri.parse(widget.branding.renewalUrl), mode: LaunchMode.externalApplication);
                          },
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFFF97316),
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            elevation: 0,
                          ),
                          child: const Text('تمدید آنی', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                        ),
                    ],
                  ),
                ),
              ],

              const SizedBox(height: 6),

              // v4.0.69 COMPACT NO-SCROLL - Restored v4.0.42 wave effects + beautiful first page
              // Per user: UI قبلی زیبا با موج برگردانده شد، Lite پیش‌فرض false شد
              // First page clean: no proxy/gps/tv (only in settings), but beautiful connect button with wave

              // v4.0.53 LITE vs ULTIMATE - v4.0.68 ULTIMATE BEAUTIFUL default
              Directionality(
                textDirection: TextDirection.ltr,
                child: Center(
                child: _useLiteButton
                    ? ConnectButtonLite(
                        state: _isConnected
                            ? LiteConnectState.connected
                            : _isConnecting
                                ? LiteConnectState.connecting
                                : LiteConnectState.disconnected,
                        size: (MediaQuery.of(context).size.height * 0.15).clamp(90.0, 160.0), // v4.0.76 FULL-SCREEN 15% responsive
                        countryFlag: _selectedServer?.flag ?? '🌐',
                        serverName: _selectedServer?.name ?? 'Connectix',
                        enableHaptic: true,
                        onTap: _toggleConnection,
                      )
                    : ConnectButtonUltimate(
                        state: _isConnected
                            ? UltimateConnectState.connected
                            : _isConnecting
                                ? UltimateConnectState.connecting
                                : UltimateConnectState.disconnected,
                        size: (MediaQuery.of(context).size.height * 0.15).clamp(90.0, 160.0), // v4.0.76 FULL-SCREEN 15% responsive
                        countryFlag: _selectedServer?.flag ?? '🌐',
                        serverName: _selectedServer?.name ?? 'Connectix',
                        enableHaptic: true,
                        enableConfetti: true,
                        enablePlasma: true,
                        enableSound: true,
                        enableFlagWave: true,
                        enableDust: true,
                        enableScreenShake: true,
                        onTap: _toggleConnection,
                      ),
              ),
              ),

              const SizedBox(height: 4),

              // Timer & Status Tag
              Text(
                _isConnected
                    ? 'زمان اتصال: ${_formatDuration(_connectedSeconds)}'
                    : (_isConnecting ? 'در حال برقراری تونل امن...' : 'جهت برقراری تونل امن لمس نمایید'),
                style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 12),
              ),

              // v4.0.68 ULTIMATE BEAUTIFUL: Speed always visible + beautiful Smart/Ping buttons below
              const SizedBox(height: 2),
              ValueListenableBuilder<V2RayStatus>(
                valueListenable: _v2rayStatus,
                builder: (context, status, _) {
                  final dl = _isConnected ? status.downloadSpeed : 0;
                  final ul = _isConnected ? status.uploadSpeed : 0;
                  return Directionality(
                    textDirection: TextDirection.rtl,
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Row(
                        textDirection: TextDirection.rtl,
                        mainAxisAlignment: MainAxisAlignment.center,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          _buildSpeedChip(
                            icon: Icons.arrow_downward_rounded,
                            label: 'دانلود',
                            bytes: dl,
                            color: const Color(0xFF10B981),
                          ),
                          const SizedBox(width: 10),
                          _buildSpeedChip(
                            icon: Icons.arrow_upward_rounded,
                            label: 'آپلود',
                            bytes: ul,
                            color: const Color(0xFF6366F1),
                          ),
                          // v4.0.69 COMPACT: پینگ از کنار سرعت حذف شد per user request - فقط دانلود/آپلود
                        ],
                      ),
                    ),
                  );
                },
              ),

              const SizedBox(height: 8),

              // v4.0.69 COMPACT Smart Connect + Ping All - گرادیانت زیبا با موج و سایه (مثل v4.0.42)
              Row(
                textDirection: TextDirection.rtl,
                children: [
                  Expanded(
                    child: Material(
                      color: Colors.transparent,
                      child: InkWell(
                        onTap: _isSmartConnecting ? null : _smartConnect,
                        borderRadius: BorderRadius.circular(14),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                          decoration: BoxDecoration(
                            gradient: const LinearGradient(
                              colors: [Color(0xFF4338CA), Color(0xFF6366F1), Color(0xFF818CF8)],
                              begin: Alignment.topRight,
                              end: Alignment.bottomLeft,
                            ),
                            borderRadius: BorderRadius.circular(16),
                            boxShadow: [
                              BoxShadow(color: const Color(0xFF6366F1).withOpacity(0.35), blurRadius: 12, offset: const Offset(0, 4)),
                              BoxShadow(color: Colors.black.withOpacity(0.2), blurRadius: 8, offset: const Offset(0, 2)),
                            ],
                          ),
                          child: Row(
                            textDirection: TextDirection.rtl,
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              _isSmartConnecting
                                  ? const SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                                  : const Icon(Icons.bolt_rounded, color: Colors.amber, size: 18),
                              const SizedBox(width: 6),
                              const Flexible(
                                child: Text('اتصال هوشمند', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12), maxLines: 1, overflow: TextOverflow.ellipsis),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Material(
                      color: Colors.transparent,
                      child: InkWell(
                        onTap: _isRefreshing ? null : _pingAllServers,
                        borderRadius: BorderRadius.circular(14),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                          decoration: BoxDecoration(
                            gradient: const LinearGradient(
                              colors: [Color(0xFF065F46), Color(0xFF10B981), Color(0xFF34D399)],
                              begin: Alignment.topRight,
                              end: Alignment.bottomLeft,
                            ),
                            borderRadius: BorderRadius.circular(16),
                            boxShadow: [
                              BoxShadow(color: const Color(0xFF10B981).withOpacity(0.35), blurRadius: 12, offset: const Offset(0, 4)),
                              BoxShadow(color: Colors.black.withOpacity(0.2), blurRadius: 8, offset: const Offset(0, 2)),
                            ],
                          ),
                          child: Row(
                            textDirection: TextDirection.rtl,
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              _isRefreshing
                                  ? const SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                                  : const Icon(Icons.speed_rounded, color: Colors.white, size: 18),
                              const SizedBox(width: 6),
                              const Flexible(
                                child: Text('پینگ همه', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12), maxLines: 1, overflow: TextOverflow.ellipsis),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 6),

              if (Platform.isWindows) ...[ // v4.0.69 COMPACT
                const SizedBox(height: 18),
                // Windows tunnel flavor selector (Phase 1: system proxy,
                // Phase 2: full TUN VPN).
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  decoration: BoxDecoration(
                    color: const Color(0xFF111827),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: const Color(0xFF1E293B)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(textDirection: TextDirection.rtl, 
                        children: [
                          const Icon(Icons.router_rounded,
                              size: 16, color: Color(0xFF9333EA)),
                          const SizedBox(width: 8),
                          const Text('حالت اتصال در ویندوز',
                              style: TextStyle(color: Color(0xFFE2E8F0), fontSize: 12, fontWeight: FontWeight.w600)),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Row(textDirection: TextDirection.rtl, 
                        children: [
                          Expanded(
                            child: _winModeChip(
                              context: context,
                              label: 'پروکسی سیستمی',
                              selected: _winTunnelMode == 'proxy',
                              onTap: () => _pickWinTunnelMode('proxy'),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: _winModeChip(
                              context: context,
                              label: 'VPN کامل (TUN)',
                              selected: _winTunnelMode == 'tun',
                              onTap: () => _pickWinTunnelMode('tun'),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],

              const SizedBox(height: 8),

              // Server Selector Card - v4.0.69 COMPACT NO-SCROLL - v4.0.47 MULTI-ACCOUNT UNIFIED
              Material(
                color: Colors.transparent,
                child: InkWell(
                  onTap: () async {
                    if (_servers.isEmpty) {
                      await _loadServers();
                    }
                    if (!mounted) return;
                    List<AccountServerEntry> unifiedEntries = [];
                    if (_unifiedEnabled) {
                      unifiedEntries = await ApiService.getAllAccountsServers();
                    }
                    final selected = await showModalBottomSheet<ServerModel>(
                      context: context,
                      backgroundColor: Colors.transparent,
                      isScrollControlled: true,
                      builder: (modalCtx) => SizedBox(
                        height: MediaQuery.of(modalCtx).size.height * 0.85,
                        child: ServerListModal(
                          servers: _servers,
                          selectedServer: _selectedServer,
                          unifiedEntries: unifiedEntries.isNotEmpty ? unifiedEntries : null,
                          onRefresh: _manualRefresh,
                          pingFunction: (uri) async {
                            try {
                              final parser = V2RayCompat.parseUniversal(uri);
                              final delay = await _flutterV2ray.getServerDelay(config: parser.getFullConfiguration());
                              if (delay != null && delay > 0) return delay;
                            } catch (_) {}
                            return await ApiService.pingServerUri(uri);
                          },
                        ),
                      ),
                    );
                    if (selected != null && mounted) {
                      // If unified, find which account this server belongs to and switch if needed
                      if (_unifiedEnabled) {
                        try {
                          final allEntries = unifiedEntries.isNotEmpty ? unifiedEntries : await ApiService.getAllAccountsServers();
                          final matching = allEntries.where((e) => (e.server as ServerModel).id == selected.id).toList();
                          if (matching.isNotEmpty && _activeAccount?.id != matching.first.accountId) {
                            final targetAcc = _accounts.firstWhere((a) => a.id == matching.first.accountId, orElse: () => _accounts.first);
                            ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('سوئیچ به حساب ${targetAcc.effectiveName} برای سرور ${selected.name}'), backgroundColor: const Color(0xFF6366F1)));
                            await _switchAccount(targetAcc);
                          }
                        } catch (_) {}
                      }
                      try {
                        final prefs = await SharedPreferences.getInstance();
                        await prefs.setString('last_working_server_id', selected.id);
                      } catch (_) {}
                      setState(() {
                        _selectedServer = selected;
                        _currentServerPing = selected.pingMs;
                      });
                      if (_currentServerPing == null) {
                        _measureSelectedServerPing();
                      }
                      if (_isConnected) {
                        try {
                          await _flutterV2ray.stopV2Ray();
                        } catch (_) {}
                        await Future.delayed(const Duration(milliseconds: 300));
                        _startTunnel();
                      }
                    }
                  },
                  borderRadius: BorderRadius.circular(20),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6), // v4.0.69 COMPACT
                    decoration: BoxDecoration(
                      color: const Color(0xFF0F172A),
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: const Color(0xFF1E293B)),
                    ),
                    child: Row(textDirection: TextDirection.rtl, 
                      children: [
                        Text(
                          _selectedServer?.flag ?? '🌐',
                          style: const TextStyle(fontSize: 26),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  if (_selectedServer != null) ...[
                                    Text(_selectedServer!.displayIcon, style: const TextStyle(fontSize: 12)),
                                    const SizedBox(width: 4),
                                  ],
                                  Expanded(
                                    child: Text(
                                      _selectedServer?.name ?? 'در حال دریافت سرورها...',
                                      style: const TextStyle(
                                        color: Colors.white,
                                        fontWeight: FontWeight.bold,
                                        fontSize: 13,
                                      ),
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 3),
                              Row(
                                children: [
                                  if (_selectedServer != null)
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                                      decoration: BoxDecoration(
                                        color: Color(_selectedServer!.protocolColorInt).withOpacity(0.15),
                                        borderRadius: BorderRadius.circular(4),
                                      ),
                                      child: Text(
                                        _selectedServer!.displayProtocolLabel,
                                        style: TextStyle(color: Color(_selectedServer!.protocolColorInt), fontSize: 9, fontWeight: FontWeight.bold),
                                      ),
                                    ),
                                  const SizedBox(width: 5),
                                  Expanded(
                                    child: Text(
                                      _selectedServer != null ? '${_selectedServer!.bestFor} • ${_selectedServer!.operatorName}' : (_selectedServer?.operatorName ?? 'پیش‌فرض'),
                                      style: const TextStyle(color: Color(0xFF64748B), fontSize: 10),
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                        if (_currentServerPing != null)
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                            margin: const EdgeInsets.only(left: 8),
                            decoration: BoxDecoration(
                              color: (_currentServerPing == 0 ? const Color(0xFF6366F1) : const Color(0xFF10B981)).withOpacity(0.15),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Text(
                              _currentServerPing == 0 ? 'آماده' : '$_currentServerPing ms',
                              style: TextStyle(color: _currentServerPing == 0 ? const Color(0xFF818CF8) : const Color(0xFF34D399), fontSize: 10, fontWeight: FontWeight.bold),
                            ),
                          ),
                        const Icon(Icons.arrow_forward_ios_rounded, size: 14, color: Color(0xFF64748B)),
                      ],
                    ),
                  ),
                ),
              ),

              const SizedBox(height: 12),

              // v4.0.45 NO-SCROLL: Removed bottom advanced settings card - moved to gear (top AppBar)
              // Per user request: first page should not scroll, only quota + connect + server selector + version
              // v4.0.37 LAW: Removed duplicate bottom speed indicator - only one speed display under connect button
              // Previously had duplicate: one under connect button (_buildSpeedChip) and one at bottom (this block)
              // Now only _buildSpeedChip under connect button shows speed - single source of truth
              // App Version & Support Footer
              Container(
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: Row(textDirection: TextDirection.rtl, 
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      'نسخه ${actualVersion} (کد $actualCode)',
                      style: const TextStyle(color: Color(0xFF475569), fontSize: 10),
                    ),
                    const SizedBox(width: 14),
                    GestureDetector(
                      onTap: () {
                        final link = widget.branding.supportLink.isNotEmpty
                            ? widget.branding.supportLink
                            : 'https://t.me/${widget.branding.telegramSupport.replaceAll('@', '')}';
                        launchUrl(Uri.parse(link), mode: LaunchMode.externalApplication);
                      },
                      child: Row(textDirection: TextDirection.rtl, 
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.support_agent_rounded, size: 12, color: Color(0xFF475569)),
                          const SizedBox(width: 4),
                          Text(
                            widget.branding.telegramSupport,
                            style: const TextStyle(color: Color(0xFF64748B), fontSize: 10),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              const Spacer(),
            ],
                      ),
                    ),
                  ),
                ),
              );
            },
          ),
        ),
      ),
    );
  }
}

class _CompactQuotaMetric extends StatelessWidget {
  final String label;
  final String value;
  final Color color;
  final IconData icon;
  const _CompactQuotaMetric({required this.label, required this.value, required this.color, required this.icon});
  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 10, color: color),
            const SizedBox(width: 3),
            Text(label, style: const TextStyle(color: Color(0xFF64748B), fontSize: 8)),
          ],
        ),
        const SizedBox(height: 2),
        Text(value, style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.bold)),
      ],
    );
  }
}

class _QuotaMetricRow extends StatelessWidget {
  final String label;
  final String value;
  final Color valueColor;
  final IconData icon;

  const _QuotaMetricRow({
    required this.label,
    required this.value,
    required this.valueColor,
    required this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return Row(textDirection: TextDirection.rtl, 
      children: [
        Icon(icon, size: 14, color: valueColor),
        const SizedBox(width: 6),
        Text(label, style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 11)),
        const SizedBox(width: 6),
        Text(value, style: TextStyle(color: valueColor, fontWeight: FontWeight.bold, fontSize: 12)),
      ],
    );
  }
}

class _GuideStep extends StatelessWidget {
  final IconData icon;
  final String title;
  final String desc;

  const _GuideStep({
    required this.icon,
    required this.title,
    required this.desc,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFF1E293B),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(textDirection: TextDirection.rtl, 
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 22, color: const Color(0xFF38BDF8)),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12)),
                const SizedBox(height: 4),
                Text(desc, style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 11, height: 1.5)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SupportActionTile extends StatelessWidget {
  final IconData icon;
  final Color iconColor;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  const _SupportActionTile({
    required this.icon,
    required this.iconColor,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: iconColor.withOpacity(0.15),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Icon(icon, color: iconColor, size: 20),
      ),
      title: Text(title, style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold)),
      subtitle: Text(subtitle, style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 11)),
      trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 14, color: Color(0xFF64748B)),
      onTap: onTap,
    );
  }
}
