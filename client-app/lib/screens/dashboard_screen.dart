import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_v2ray/flutter_v2ray.dart';
import 'package:percent_indicator/circular_percent_indicator.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';
import '../models/client_model.dart';
import '../models/server_model.dart';
import '../services/api_service.dart';
import '../services/v2ray_compat.dart';
import 'login_screen.dart';
import 'server_list_modal.dart';
import 'bypass_apps_screen.dart';
import 'advanced_settings_screen.dart';

class DashboardScreen extends StatefulWidget {
  final ClientModel client;
  final BrandingModel branding;
  final List<ServerModel>? initialServers;

  /// Public app version (mirrors the State constant) so other screens
  /// (login footer, support sheet) can display it.
  static const String currentAppVersion = _DashboardScreenState.currentAppVersion;

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

  static const String currentAppVersion = '3.5.6';

  // "Download over Wi-Fi only" for update packages
  bool _updateWifiOnly = false;

  // Comprehensive Iranian & Banking Apps Bypass List (Verified package names from Cafe Bazaar & Myket)
  static const List<String> defaultDomesticBypassApps = [
    // ---------------------------------------------------------
    // 1. پیام‌رسان‌ها و ارتباطی (Messengers & Social)
    // ---------------------------------------------------------
    'ir.eitaa.messenger',             // ایتا
    'ir.resanememaran.rubika',        // روبیکا
    'ir.resanememaran.rubikax',       // روبیکا ایکس
    'ir.ble.messenger',               // بله
    'mobi.mmdt.ott',                  // سروش پلاس
    'net.iGap',                       // آیگپ
    'i.gap.im',                       // آیگپ
    'ir.soroush.app',                 // سروش
    'ir.medu.shad',                   // شاد
    'com.gap.android',                // گپ

    // ---------------------------------------------------------
    // 2. بانک ملت (Bank Mellat)
    // ---------------------------------------------------------
    'com.pmb.mobile',                 // همراه بانک ملت (اصلی - ۱۹ میلیون نصب)
    'com.mellat.mobilebank',
    'ir.bankmellat.mobilebank',
    'com.mellat.mobilebank.new',
    'ir.bankmellat.sekeh',            // سکه ملت

    // ---------------------------------------------------------
    // 3. بانک رفاه کارگران (Bank Refah)
    // ---------------------------------------------------------
    'com.refahbank.dpi.android',      // همراه بانک رفاه (اصلی - ۷ میلیون نصب)
    'com.refah.superapp',             // فرا رفاه
    'ir.refah.mobilebank',
    'com.refah.mobilebank',
    'ir.refah.omidbank',              // امید بانک رفاه
    'com.refah.fara',

    // ---------------------------------------------------------
    // 4. بانک ملی ایران (Bank Melli)
    // ---------------------------------------------------------
    'ir.bmi.bam.nativeweb',           // همراه بام ملی (نسخه اصلی جدید)
    'com.bmi.bam',                    // همراه بام ملی
    'ir.bmi.bam',
    'com.sadadpsp.eva',               // ایوا (پرداخت سداد ملی)
    'com.sadad.shast',                // شصت (رمزساز بانک ملی)
    'ir.melli.app',
    'ir.sadad.mobilebank',

    // ---------------------------------------------------------
    // 5. بانک تجارت (Bank Tejarat)
    // ---------------------------------------------------------
    'ir.tejaratbank.tata.mobile.android.tejarat', // همراه بانک تجارت (اصلی - ۹.۷ میلیون نصب)
    'com.tejaratbank.mobilebank',
    'ir.stts.bjt',                    // باجت (دیجیتال‌بانک تجارت)
    'ir.stts.etc',                    // ست تجارت
    'ir.mbt.android',
    'com.tejarat.hamrah',

    // ---------------------------------------------------------
    // 6. بانک صادرات ایران (Bank Saderat)
    // ---------------------------------------------------------
    'com.isc.bsinew',                 // همراه بانک صادرات (اصلی - ۶ میلیون نصب)
    'ir.bsi.mobilebank',
    'com.saderat.mobilebank',
    'com.isc.sap',                    // صاپ (پرداخت بانک صادرات)
    'com.saderat.sipa',
    'com.saderat.rimor',

    // ---------------------------------------------------------
    // 7. بانک سپه و بانک‌های ادغامی (Bank Sepah)
    // ---------------------------------------------------------
    'mob.banking.android.sepah',       // همراه بانک سپه (سامانه توسن)
    'ir.sepah.mobilebank',
    'com.sepah.mobilebank',
    'ir.sepah.omid',                  // امید سپه
    'com.ansar.mobilebank',           // انصار سابق
    'com.ghavamin.mobilebank',         // قوامین سابق
    'com.kosar.mobilebank',           // کوثر سابق

    // ---------------------------------------------------------
    // 8. بانک پاسارگاد و ویپاد (Bank Pasargad & Wepod)
    // ---------------------------------------------------------
    'mob.banking.android.pasargad',    // همراه بانک پاسارگاد (اصلی - ۴.۴ میلیون نصب)
    'com.dotin.wepod',                // ویپاد (نئوبانک پاسارگاد)
    'com.pasargad.mobilebank',
    'ir.pasargad.mobilebank',

    // ---------------------------------------------------------
    // 9. بانک سامان و بلو بانک (Bank Saman & BluBank)
    // ---------------------------------------------------------
    'com.samanpr.mobillet',           // موبایلت (همراه بانک سامان)
    'com.samanpr.blubank',            // بلو بانک (BluBank سامان)
    'com.modern.blubank',
    'com.samanpr.mb',
    'com.saman.mobilebank',

    // ---------------------------------------------------------
    // 10. بانک مسکن (Bank Maskan)
    // ---------------------------------------------------------
    'com.maskanmobilebank',           // همراه بانک مسکن (اصلی - ۲ میلیون نصب)
    'mob.banking.android.maskan',
    'ir.maskan.mobilebank',
    'com.maskan.mobilebank',

    // ---------------------------------------------------------
    // 11. بانک کشاورزی (Bank Keshavarzi)
    // ---------------------------------------------------------
    'com.bki.mobilebanking.android',  // همراه بانک کشاورزی (اصلی)
    'ir.keshavarzibank.mobilebank',
    'com.bki.mobilebank',

    // ---------------------------------------------------------
    // 12. بانک شهر (Bank Shahr)
    // ---------------------------------------------------------
    'com.citydi.hplus',               // همراه شهر پلاس (اصلی - ۳.۷ میلیون نصب)
    'mob.banking.android.shahr',
    'ir.shahr.hamrah',
    'com.tosan.shahr',

    // ---------------------------------------------------------
    // 13. بانک رسالت و مهر ایران (Gharzolhasaneh Banks)
    // ---------------------------------------------------------
    'mob.banking.android.resalat',     // همراه بانک رسالت (اصلی)
    'ir.rqbank.mobilebank',
    'com.melli.resalat',
    'com.qmb.mobile',                 // بانک قرض‌الحسنه مهر ایران
    'ir.qmb.mobilebank',
    'com.qmb.mobilebank',
    'ir.ttbank.mobilebank',

    // ---------------------------------------------------------
    // 14. بانک آینده، آبانک و همراه کارت (Bank Ayandeh & Hamrah Card)
    // ---------------------------------------------------------
    'com.adpdigital.mbs.ayande',      // همراه کارت (۱۲ میلیون نصب)
    'ir.ba.abank',                    // آبانک (نئوبانک آینده)
    'com.tosan.keylid',               // کیلید بانک آینده
    'com.ayandeh.mobilebank',

    // ---------------------------------------------------------
    // 15. سایر بانک‌ها (دی، گردشگری، سینا، تعاون، کارآفرین، خاورمیانه)
    // ---------------------------------------------------------
    'com.tosan.dara.day',             // دی جت (بانک دی)
    'mob.banking.android.day',
    'ir.daybank.mobilebank',
    'mob.banking.android.gardesh',    // توبانک / گردشگری
    'ir.tourismbank.mobilebank',
    'com.tosan.gardeshgari',
    'mob.banking.android.sina',       // بانک سینا
    'ir.sinabank.mobilebank',
    'mob.banking.android.taavon',     // توسعه تعاون
    'com.tosan.dara.bim',             // صنعت و معدن
    'com.tosan.dara.edbi',            // توسعه صادرات
    'mob.banking.android.karafarin',  // کارآفرین
    'com.middleeastbank.mobile',      // خاورمیانه
    'mob.banking.android.iranzamin',  // ایران زمین
    'com.iranzamin.faraz',            // فراز ایران زمین
    'com.bankino.mobile',             // بانکینو (خاورمیانه)

    // ---------------------------------------------------------
    // 16. پرداخت، فین‌تک و خدمات مالی (Fintech & Payment)
    // ---------------------------------------------------------
    'com.asanpardakht.app',           // آپ (آسان پرداخت)
    'ir.sep.seso',                    // ۷۲۴ (پرداخت الکترونیک سامان)
    'ir.pec.top',                     // تاپ (تجارت الکترونیک پارسیان)
    'com.pec.parsian',                // پارسیان من
    'com.tosan.saman.sekeh',          // سکه
    'com.snapp.pay',                  // اسنپ پی
    'ir.mcoin.hamrahcard',

    // ---------------------------------------------------------
    // 17. صرافی‌های ایرانی ارز دیجیتال (Crypto Exchanges)
    // ---------------------------------------------------------
    'ir.nobitex.app',                 // نوبیتکس
    'ir.wallex.app',                  // والکس
    'ir.ramzinex.app',                // رمزینکس
    'ir.abantether.app',              // آبان تتر
    'com.bitpin.app',                 // بیت پین
    'ir.exir.app',                    // اکسیر

    // ---------------------------------------------------------
    // 18. خدمات پرکاربرد روزمره، حمل‌ونقل و خرید (Daily Services)
    // ---------------------------------------------------------
    'cab.snapp.passenger',            // اسنپ مسافر
    'cab.snapp.driver',               // اسنپ راننده
    'com.tapsi.passenger',            // تپسی مسافر
    'com.tapsi.driver',               // تپسی راننده
    'ir.divar',                       // دیوار
    'com.farsitel.bazaar',            // کافه بازار
    'com.myket.android',              // مایکت
    'ir.torob',                       // ترب
    'com.digikala',                   // دیجی‌کالا
    'ir.neshan.sp',                   // نشان
    'ir.balad',                       // بلد
    'com.takhfifan',                  // تخفیفان
    'com.snapp.food',                 // اسنپ فود
    'com.snappbox.passenger',         // اسنپ باکس

    // ---------------------------------------------------------
    // 19. خدمات دولتی، بیمه و عمومی (Government & Utilities)
    // ---------------------------------------------------------
    'ir.gov.my',                      // پنجره ملی خدمات دولت هوشمند
    'ir.tamin.taminman',              // تأمین اجتماعی من
    'ir.post.postman',                // پست من
    'ir.tehran.mytehran',             // تهران من
    'ir.mci.ecareapp',                // همراه من (همراه اول)
    'ir.irancell.myirancell',         // ایرانسل من
    'ir.rightel.myrightel',           // رایتل من
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
    _loadSettings();
    _initV2Ray();
    _loadServers();
    _refreshProfile();
    _loadAnnouncements();
    _autoCheckUpdateInBackground();
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
    _foregroundCheckTimer = Timer.periodic(const Duration(seconds: 2), (timer) async {
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
      final updateData = await ApiService.checkAppUpdate();
      if (mounted && updateData != null) {
        final latest = (updateData['latest_version'] ?? '').toString();
        final lastPrompted = prefs.getString('last_prompted_version') ?? '';

        final bool isNew = isNewerVersion(latest, currentAppVersion);
        setState(() {
          _hasAppUpdate = isNew;
          _updateInfo = isNew ? updateData : null;
        });

        // Automatically alert user with one-click install dialog if not yet prompted for this version
        if (isNew && lastPrompted != latest) {
          await prefs.setString('last_prompted_version', latest);
          Future.delayed(const Duration(milliseconds: 1400), () {
            if (mounted) {
              _showUpdateDialog(updateData, isAutoPrompt: true);
            }
          });
        }
      }
    } catch (_) {
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

  void _initV2Ray() async {
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
              _isConnected = false;
              _isConnecting = false;
              if (!_pausedByBankingApp) {
                _userIntentionallyDisconnected = true;
              }
              _reconnectAttempts = 0;
              _timer?.cancel();
              if (!_pausedByBankingApp) {
                _stopForegroundAppMonitoring();
              }
              ApiService.updateNotificationStatus(
                title: '',
                content: '',
                isConnected: false,
              );
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

  void _measureSelectedServerPing() async {
    if (_selectedServer == null || _selectedServer!.configUri.isEmpty) return;
    try {
      int? delay;
      try {
        final parser = FlutterV2ray.parseFromURL(_selectedServer!.configUri);
        delay = await _flutterV2ray.getServerDelay(config: parser.getFullConfiguration());
      } catch (_) {}

      if (delay == null || delay <= 0) {
        delay = await ApiService.pingServerUri(_selectedServer!.configUri);
      }

      if (mounted && delay != null && delay > 0) {
        setState(() {
          _currentServerPing = delay;
          _selectedServer!.pingMs = delay;
        });
      }
    } catch (_) {}
  }

  // 1-Tap Smart Connect to Best Ping Server
  void _smartConnect() async {
    if (_isSmartConnecting || _isConnecting) return;
    final clean = _servers.where((s) => !s.isInfoBanner && s.configUri.isNotEmpty).toList();
    if (clean.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('هیچ سروری برای سنجش پینگ یافت نشد.')),
      );
      return;
    }

    setState(() {
      _isSmartConnecting = true;
    });

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('در حال سنجش پینگ و انتخاب سریع‌ترین سرور...'),
        backgroundColor: Color(0xFF6366F1),
        duration: Duration(seconds: 2),
      ),
    );

    ServerModel bestServer = clean.first;
    int lowestPing = 99999;

    for (int i = 0; i < clean.length && i < 6; i++) {
      final s = clean[i];
      if (s.configUri.isEmpty) continue;
      try {
        final parser = FlutterV2ray.parseFromURL(s.configUri);
        final delay = await _flutterV2ray.getServerDelay(config: parser.getFullConfiguration());
        if (delay != null && delay > 0 && delay < lowestPing) {
          lowestPing = delay;
          bestServer = s;
        }
      } catch (_) {}
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
          content: Text('سریع‌ترین سرور (${bestServer.name}) با پینگ ${lowestPing < 99999 ? '$lowestPing ms' : 'عالی'} انتخاب شد.'),
          backgroundColor: const Color(0xFF10B981),
        ),
      );

      if (!_isConnected) {
        _startTunnel();
      } else {
        // Reconnect to best server
        try {
          await _flutterV2ray.stopV2Ray();
        } catch (_) {}
        await Future.delayed(const Duration(milliseconds: 400));
        _startTunnel();
      }
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
            child: Row(
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
  void _startInAppDownloadAndInstall(String downloadUrl, String version, {String fallbackUrl = ''}) {
    final defaultPrimary = "https://github.com/hojjatrad/panelconnectix/releases/download/v$version/Connectix-Android-ARM64.apk";
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
                    // Auto-retry once via the GitHub release fallback
                    if (!usedFallback && fallbackUrl.isNotEmpty && fallbackUrl != url) {
                      usedFallback = true;
                      setModalState(() {
                        isFailed = false;
                        downloadProgress = 0.0;
                        transferredText = '0 MB / ...';
                        statusText = 'لینک اصلی در دسترس نبود؛ در حال دریافت از لینک جایگزین...';
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
                      statusText = 'دانلود کامل شد. پنجره نصاب اندروید فراخوانی گردید.';
                      downloadProgress = 1.0;
                    });
                    Future.delayed(const Duration(milliseconds: 1400), () {
                      if (Navigator.canPop(bottomSheetContext)) {
                        Navigator.pop(bottomSheetContext);
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
                  Row(
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
                        Row(
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
                        ElevatedButton.icon(
                          onPressed: () async {
                            Navigator.pop(bottomSheetContext);
                            final uri = Uri.parse(downloadUrl);
                            if (await canLaunchUrl(uri)) {
                              await launchUrl(uri, mode: LaunchMode.externalApplication);
                            }
                          },
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFF6366F1),
                            padding: const EdgeInsets.symmetric(vertical: 12),
                          ),
                          icon: const Icon(Icons.open_in_browser, size: 16),
                          label: const Text('دانلود با مرورگر', style: TextStyle(fontWeight: FontWeight.bold)),
                        ),
                      ],
                    )
                  else
                    const Text(
                      'نصاب اندروید به صورت خودکار اجرا خواهد شد و نیازی به حذف برنامه قبلی نیست.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Color(0xFF64748B), fontSize: 11),
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

      // Windows Phase 2: full TUN mode needs admin on the FIRST run only
      // (creates the Wintun TAP adapter); afterwards the adapter persists.
      final tunMode =
          Platform.isWindows && _winTunnelMode == 'tun';
      if (tunMode && !await _flutterV2ray.isElevated()) {
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
      final parser = FlutterV2ray.parseFromURL(configUri);
      String finalConfig = parser.getFullConfiguration();

      // Pass Split Tunneling blocked apps to exclude domestic/banking apps natively (without crashes)
      // Layer 1: System-level disallowed apps (Android VpnService.Builder.addDisallowedApplication)
      List<String>? blockedAppsToPass;
      String safeFinalConfig = finalConfig;
      if (Platform.isAndroid && _splitTunnelingEnabled) {
        try {
          final prefs = await SharedPreferences.getInstance();
          final List<String>? customBypass = prefs.getStringList('custom_bypass_apps');
          blockedAppsToPass = (customBypass != null && customBypass.isNotEmpty)
              ? customBypass
              : defaultDomesticBypassApps;
        } catch (e) {
          debugPrint('Bypass apps check: $e');
          blockedAppsToPass = defaultDomesticBypassApps;
        }

        // Layer 2: Inject SAFE direct routing rules (domain:ir + geosite:ir + geoip:ir - flutter_v2ray bundles dat files, so safe)
        // Layer 2 is fallback for apps not in disallowed list, and for WebView traffic
        try {
          final Map<String, dynamic> configMap = jsonDecode(finalConfig);
          final routing = (configMap['routing'] as Map<String, dynamic>?) != null
              ? Map<String, dynamic>.from(configMap['routing'] as Map)
              : <String, dynamic>{};
          final List<dynamic> rules = (routing['rules'] as List<dynamic>?) != null
              ? List<dynamic>.from(routing['rules'] as List<dynamic>)
              : <dynamic>[];

          // Rule 0: Iranian domains -> direct (safe, uses dat files bundled by flutter_v2ray)
          rules.insert(0, {
            'type': 'field',
            'outboundTag': 'direct',
            'domain': [
              'geosite:ir',
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
              'bankmellat.ir',
              'bmi.ir',
              'bankmelli.ir',
              'aparat.com',
              'filimo.com',
            ],
          });
          // Rule 1: Iranian & private IPs -> direct
          rules.insert(1, {
            'type': 'field',
            'outboundTag': 'direct',
            'ip': [
              'geoip:ir',
              'geoip:private',
            ],
          });
          routing['rules'] = rules;
          configMap['routing'] = routing;
          final encoded = jsonEncode(configMap);
          jsonDecode(encoded); // validate
          safeFinalConfig = encoded;
        } catch (e) {
          debugPrint('Inject direct routing rules failed, using original config: $e');
          safeFinalConfig = finalConfig;
        }
      }
      finalConfig = safeFinalConfig;

      await _flutterV2ray.startV2Ray(
        remark: 'Connectix • ${_selectedServer!.name}',
        config: finalConfig,
        blockedApps: blockedAppsToPass,
        proxyOnly: false, // Full device-wide VPN tunnel
        tunMode: tunMode,
        notificationDisconnectButtonName: 'قطع اتصال',
      );

      _connectedSeconds = 0;
      _timer?.cancel();
      _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
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
              Row(
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
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: const [
                        Text('پورت پروکسی HTTP:', style: TextStyle(color: Color(0xFFCBD5E1), fontSize: 13)),
                        Text('10809', style: TextStyle(color: Color(0xFF38BDF8), fontWeight: FontWeight.bold, fontSize: 14)),
                      ],
                    ),
                    const Divider(color: Color(0xFF334155), height: 20),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: const [
                        Text('پورت پروکسی SOCKS5:', style: TextStyle(color: Color(0xFFCBD5E1), fontSize: 13)),
                        Text('10808', style: TextStyle(color: Color(0xFF34D399), fontWeight: FontWeight.bold, fontSize: 14)),
                      ],
                    ),
                    const Divider(color: Color(0xFF334155), height: 20),
                    Row(
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
                    Row(
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
                            Row(
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
                                      Row(
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
    if (_autoPauseForBankingEnabled && _isConnected) {
      _startForegroundAppMonitoring();
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
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: color),
          const SizedBox(width: 4),
          Text(
            '$label: $speedText',
            style: TextStyle(
              color: color,
              fontSize: 11,
              fontWeight: FontWeight.bold,
            ),
          ),
        ],
      ),
    );
  }

  @override
  void dispose() {
    _timer?.cancel();
    _foregroundCheckTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final statusColor = _isConnected
        ? const Color(0xFF10B981)
        : (_isConnecting ? const Color(0xFFF59E0B) : widget.branding.accentColor);

    final double usageClamped = (_client.usagePercent / 100).clamp(0.0, 1.0);

    return Scaffold(
      backgroundColor: const Color(0xFF090D16),
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        title: Row(
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
                    'نسخه $currentAppVersion',
                    style: const TextStyle(color: Color(0xFF64748B), fontSize: 9),
                  ),
                ],
              ),
            ),
          ],
        ),
        actions: [
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
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
          child: Column(
            children: [
              // Global / Reseller Announcement Banner
              if (widget.branding.announcement.isNotEmpty)
                Container(
                  margin: const EdgeInsets.only(bottom: 14),
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  decoration: BoxDecoration(
                    color: const Color(0xFF1E293B),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: widget.branding.accentColor.withOpacity(0.35)),
                  ),
                  child: Row(
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
                  margin: const EdgeInsets.only(bottom: 14),
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
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
                  child: Row(
                    children: [
                      const Icon(Icons.rocket_launch, color: Colors.amber, size: 22),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'نگارش جدید ${_updateInfo!['latest_version'] ?? 'جدید'} آماده است',
                              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13),
                            ),
                            const Text(
                              'نصب خودکار مستقیم بدون نیاز به حذف نسخه قبل',
                              style: TextStyle(color: Color(0xFFE0E7FF), fontSize: 11),
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

              // Modern Circular Speedometer & Quota Gauge Card
              Container(
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [Color(0xFF161536), Color(0xFF0F172A)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(color: const Color(0xFF312E81)),
                ),
                child: Column(
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _client.username,
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.bold,
                                fontSize: 16,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              _client.planTitle,
                              style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 12),
                            ),
                          ],
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                          decoration: BoxDecoration(
                            color: const Color(0xFF10B981).withOpacity(0.15),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: const Color(0xFF10B981).withOpacity(0.3)),
                          ),
                          child: Text(
                            _client.daysRemaining,
                            style: const TextStyle(
                              color: Color(0xFF10B981),
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    // Circular Gauge
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceAround,
                      children: [
                        CircularPercentIndicator(
                          radius: 54.0,
                          lineWidth: 9.0,
                          percent: usageClamped,
                          animation: true,
                          center: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Text(
                                '${(_client.usagePercent).toInt()}%',
                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: Colors.white),
                              ),
                              const Text('مصرف شده', style: TextStyle(fontSize: 10, color: Color(0xFF94A3B8))),
                            ],
                          ),
                          circularStrokeCap: CircularStrokeCap.round,
                          backgroundColor: const Color(0xFF1E293B),
                          progressColor: usageClamped > 0.85 ? const Color(0xFFEF4444) : const Color(0xFF6366F1),
                        ),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _QuotaMetricRow(
                              label: 'باقیمانده:',
                              value: '${_client.trafficRemainingGb} GB',
                              valueColor: const Color(0xFF38BDF8),
                              icon: Icons.pie_chart_outline_rounded,
                            ),
                            const SizedBox(height: 8),
                            _QuotaMetricRow(
                              label: 'مصرف شده:',
                              value: '${_client.trafficUsedGb} GB',
                              valueColor: const Color(0xFFCBD5E1),
                              icon: Icons.data_usage_rounded,
                            ),
                            const SizedBox(height: 8),
                            _QuotaMetricRow(
                              label: 'سقف کل:',
                              value: '${_client.trafficTotalGb} GB',
                              valueColor: const Color(0xFF94A3B8),
                              icon: Icons.cloud_done_outlined,
                            ),
                          ],
                        ),
                      ],
                    ),
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
                  child: Row(
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

              const SizedBox(height: 20),

              // Smart Connect & Quick Controls Row
              Row(
                children: [
                  // 1-Tap Smart Connect
                  Expanded(
                    child: InkWell(
                      onTap: _smartConnect,
                      borderRadius: BorderRadius.circular(16),
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                        decoration: BoxDecoration(
                          color: const Color(0xFF1E1B4B),
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: const Color(0xFF4338CA)),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            _isSmartConnecting
                                ? const SizedBox(
                                    width: 16,
                                    height: 16,
                                    child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFF818CF8)),
                                  )
                                : const Icon(Icons.bolt_rounded, color: Colors.amber, size: 20),
                            const SizedBox(width: 8),
                            const Text(
                              'اتصال هوشمند',
                              style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  // Hotspot & Proxy Sharing Modal Trigger
                  InkWell(
                    onTap: _openHotspotProxySharingSheet,
                    borderRadius: BorderRadius.circular(16),
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                      decoration: BoxDecoration(
                        color: const Color(0xFF0F172A),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: const Color(0xFF334155)),
                      ),
                      child: Row(
                        children: const [
                          Icon(Icons.wifi_tethering_rounded, color: Color(0xFF34D399), size: 18),
                          SizedBox(width: 6),
                          Text('اشتراک با TV', style: TextStyle(color: Color(0xFFCBD5E1), fontSize: 12)),
                        ],
                      ),
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 24),

              // Connect Button with Glowing Rings
              GestureDetector(
                onTap: _toggleConnection,
                child: Container(
                  width: 165,
                  height: 165,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    gradient: RadialGradient(
                      colors: [
                        statusColor.withOpacity(0.3),
                        Colors.transparent,
                      ],
                    ),
                  ),
                  child: Center(
                    child: Container(
                      width: 125,
                      height: 125,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: const Color(0xFF0F172A),
                        border: Border.all(color: statusColor, width: 3),
                        boxShadow: [
                          BoxShadow(
                            color: statusColor.withOpacity(0.4),
                            blurRadius: 30,
                            spreadRadius: 2,
                          ),
                        ],
                      ),
                      child: Center(
                        child: _isConnecting
                            ? SizedBox(
                                width: 36,
                                height: 36,
                                child: CircularProgressIndicator(color: statusColor, strokeWidth: 3),
                              )
                            : Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(
                                    Icons.power_settings_new_rounded,
                                    size: 44,
                                    color: statusColor,
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    _isConnected ? 'متصل شد' : 'اتصال',
                                    style: TextStyle(
                                      color: statusColor,
                                      fontWeight: FontWeight.bold,
                                      fontSize: 13,
                                    ),
                                  ),
                                ],
                              ),
                      ),
                    ),
                  ),
                ),
              ),

              const SizedBox(height: 14),

              // Timer & Status Tag
              Text(
                _isConnected
                    ? 'زمان اتصال: ${_formatDuration(_connectedSeconds)}'
                    : (_isConnecting ? 'در حال برقراری تونل امن...' : 'جهت برقراری تونل امن لمس نمایید'),
                style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 12),
              ),

              // Live Real-Time Speed Badges (Upload / Download)
              if (_isConnected) ...[
                const SizedBox(height: 10),
                ValueListenableBuilder<V2RayStatus>(
                  valueListenable: _v2rayStatus,
                  builder: (context, status, _) {
                    return Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        _buildSpeedChip(
                          icon: Icons.arrow_downward_rounded,
                          label: 'دانلود',
                          bytes: status.downloadSpeed,
                          color: const Color(0xFF10B981),
                        ),
                        const SizedBox(width: 10),
                        _buildSpeedChip(
                          icon: Icons.arrow_upward_rounded,
                          label: 'آپلود',
                          bytes: status.uploadSpeed,
                          color: const Color(0xFF6366F1),
                        ),
                      ],
                    );
                  },
                ),
              ],

              if (Platform.isWindows) ...[
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
                      Row(
                        children: [
                          const Icon(Icons.router_rounded,
                              size: 16, color: Color(0xFF9333EA)),
                          const SizedBox(width: 8),
                          const Text('حالت اتصال در ویندوز',
                              style: TextStyle(color: Color(0xFFE2E8F0), fontSize: 12, fontWeight: FontWeight.w600)),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Row(
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

              const SizedBox(height: 22),

              // Server Selector Card
              Material(
                color: Colors.transparent,
                child: InkWell(
                  onTap: () async {
                    if (_servers.isEmpty) {
                      await _loadServers();
                    }
                    if (!mounted) return;
                    final selected = await showModalBottomSheet<ServerModel>(
                      context: context,
                      backgroundColor: Colors.transparent,
                      isScrollControlled: true,
                      builder: (modalCtx) => SizedBox(
                        height: MediaQuery.of(modalCtx).size.height * 0.85,
                        child: ServerListModal(
                          servers: _servers,
                          selectedServer: _selectedServer,
                          onRefresh: _manualRefresh,
                          pingFunction: (uri) async {
                            try {
                              final parser = FlutterV2ray.parseFromURL(uri);
                              final delay = await _flutterV2ray.getServerDelay(config: parser.getFullConfiguration());
                              if (delay != null && delay > 0) return delay;
                            } catch (_) {}
                            return await ApiService.pingServerUri(uri);
                          },
                        ),
                      ),
                    );
                    if (selected != null && mounted) {
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
                        // Seamlessly reconnect with newly selected server
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
                    padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
                    decoration: BoxDecoration(
                      color: const Color(0xFF0F172A),
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: const Color(0xFF1E293B)),
                    ),
                    child: Row(
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
                              Text(
                                _selectedServer?.name ?? 'در حال دریافت سرورها...',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.bold,
                                  fontSize: 13,
                                ),
                              ),
                              const SizedBox(height: 3),
                              Text(
                                '${_selectedServer?.protocol.toUpperCase() ?? 'VLESS'} • ${_selectedServer?.operatorName ?? 'پیش‌فرض'}',
                                style: const TextStyle(color: Color(0xFF64748B), fontSize: 11),
                              ),
                            ],
                          ),
                        ),
                        if (_currentServerPing != null)
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                            margin: const EdgeInsets.only(left: 8),
                            decoration: BoxDecoration(
                              color: const Color(0xFF10B981).withOpacity(0.15),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Text(
                              '$_currentServerPing ms',
                              style: const TextStyle(color: Color(0xFF34D399), fontSize: 10, fontWeight: FontWeight.bold),
                            ),
                          ),
                        const Icon(Icons.arrow_forward_ios_rounded, size: 14, color: Color(0xFF64748B)),
                      ],
                    ),
                  ),
                ),
              ),

              const SizedBox(height: 16),

              // Clean Advanced Settings Entry (replaces crowded Pro Settings on main page)
              Material(
                color: Colors.transparent,
                child: InkWell(
                  onTap: _openAdvancedSettings,
                  borderRadius: BorderRadius.circular(18),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                    decoration: BoxDecoration(
                      color: const Color(0xFF0F172A),
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(color: const Color(0xFF1E293B)),
                    ),
                    child: Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(
                            color: const Color(0xFF6366F1).withOpacity(0.15),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: const Icon(Icons.tune_rounded, color: Color(0xFF818CF8), size: 20),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  const Text('تنظیمات پیشرفته', style: TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold)),
                                  const SizedBox(width: 8),
                                  if (_splitTunnelingEnabled)
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                      decoration: BoxDecoration(color: const Color(0xFF10B981).withOpacity(0.2), borderRadius: BorderRadius.circular(6)),
                                      child: const Text('فعال', style: TextStyle(color: Color(0xFF10B981), fontSize: 9, fontWeight: FontWeight.bold)),
                                    ),
                                ],
                              ),
                              const SizedBox(height: 2),
                              Text(
                                _splitTunnelingEnabled ? 'عبور مستقیم بانک‌ها فعال • مدیریت برنامه‌ها' : 'مدیریت عبور مستقیم، اتصال خودکار، حالت ویندوز',
                                style: const TextStyle(color: Color(0xFF64748B), fontSize: 11),
                              ),
                            ],
                          ),
                        ),
                        const Icon(Icons.arrow_forward_ios_rounded, size: 14, color: Color(0xFF64748B)),
                      ],
                    ),
                  ),
                ),
              ),

              const SizedBox(height: 16),

              // Live Traffic Speeds if connected
              ValueListenableBuilder<V2RayStatus>(
                valueListenable: _v2rayStatus,
                builder: (context, status, _) {
                  if (!_isConnected) return const SizedBox.shrink();
                  return Container(
                    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                    decoration: BoxDecoration(
                      color: const Color(0xFF0F172A),
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: const Color(0xFF1E293B)),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceAround,
                      children: [
                        Row(
                          children: [
                            const Icon(Icons.arrow_downward_rounded, size: 16, color: Color(0xFF10B981)),
                            const SizedBox(width: 6),
                            Text(
                              'دانلود: ${_formatSpeed(status.downloadSpeed)}',
                              style: const TextStyle(color: Color(0xFFE2E8F0), fontSize: 12, fontWeight: FontWeight.bold),
                            ),
                          ],
                        ),
                        Container(width: 1, height: 20, color: const Color(0xFF1E293B)),
                        Row(
                          children: [
                            const Icon(Icons.arrow_upward_rounded, size: 16, color: Color(0xFF38BDF8)),
                            const SizedBox(width: 6),
                            Text(
                              'آپلود: ${_formatSpeed(status.uploadSpeed)}',
                              style: const TextStyle(color: Color(0xFFE2E8F0), fontSize: 12, fontWeight: FontWeight.bold),
                            ),
                          ],
                        ),
                      ],
                    ),
                  );
                },
              ),
              // App Version & Support Footer
              Container(
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      'نسخه $currentAppVersion',
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
                      child: Row(
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
            ],
          ),
        ),
      ),
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
    return Row(
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
      child: Row(
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
