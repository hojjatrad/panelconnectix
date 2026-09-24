import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_v2ray/flutter_v2ray.dart';
import 'package:percent_indicator/circular_percent_indicator.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';
import '../models/client_model.dart';
import '../models/server_model.dart';
import '../services/api_service.dart';
import 'login_screen.dart';
import 'server_list_modal.dart';

class DashboardScreen extends StatefulWidget {
  final ClientModel client;
  final BrandingModel branding;
  final List<ServerModel>? initialServers;

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

  late final FlutterV2ray _flutterV2ray;
  final ValueNotifier<V2RayStatus> _v2rayStatus = ValueNotifier<V2RayStatus>(V2RayStatus());

  bool _isConnected = false;
  bool _isConnecting = false;
  bool _isRefreshing = false;
  bool _userIntentionallyDisconnected = true;
  int _connectedSeconds = 0;
  Timer? _timer;

  // New Pro Features States
  bool _splitTunnelingEnabled = true;
  bool _autoReconnectEnabled = true;
  int _reconnectAttempts = 0;
  bool _isSmartConnecting = false;
  int? _currentServerPing;

  List<Map<String, dynamic>> _announcements = [];
  bool _hasAppUpdate = false;
  Map<String, dynamic>? _updateInfo;

  static const String currentAppVersion = '3.0.0';

  // Iranian & Banking Apps Bypass List (Snapp, Divar, Rubika, Neshan, Torob, Digikala, Banking)
  static const List<String> defaultDomesticBypassApps = [
    'cab.snapp.passenger',
    'cab.snapp.driver',
    'ir.divar',
    'com.farsitel.bazaar',
    'ir.torob',
    'com.digikala',
    'ir.neshan.sp',
    'ir.balad',
    'ir.resanememaran.rubika',
    'com.bmi.bam',
    'com.asanpardakht.app',
    'com.sadadpsp.eva',
    'com.tosan.shahr',
    'com.tejaratbank.mobilebank',
    'ir.sep.seso',
    'ir.mbt.android',
    'com.mellat.mobilebank',
    'com.modern.blubank',
    'com.bankino.mobile',
    'com.samanpr.mb',
    'ir.parsian.mobile',
    'com.pasargad.mobilebank'
  ];

  @override
  void initState() {
    super.initState();
    _client = widget.client;
    if (widget.initialServers != null && widget.initialServers!.isNotEmpty) {
      _servers = widget.initialServers!;
      _selectedServer = _servers.first;
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
    setState(() {
      _splitTunnelingEnabled = prefs.getBool('split_tunneling_enabled') ?? true;
      _autoReconnectEnabled = prefs.getBool('auto_reconnect_enabled') ?? true;
    });
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
    try {
      final prefs = await SharedPreferences.getInstance();
      final updateData = await ApiService.checkAppUpdate();
      if (mounted && updateData != null) {
        final bool hasUpdateFlag = updateData['has_update'] == true;
        final latest = (updateData['latest_version'] ?? '').toString();
        final dismissed = prefs.getString('dismissed_version') ?? '';

        if (hasUpdateFlag && isNewerVersion(latest, currentAppVersion) && dismissed != latest) {
          setState(() {
            _hasAppUpdate = true;
            _updateInfo = updateData;
          });
        }
      }
    } catch (_) {}
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
    _flutterV2ray = FlutterV2ray(
      onStatusChanged: (status) {
        _v2rayStatus.value = status;
        if (mounted) {
          setState(() {
            if (status.state == 'CONNECTED') {
              _isConnected = true;
              _isConnecting = false;
              _userIntentionallyDisconnected = false;
              _reconnectAttempts = 0;
            } else if (status.state == 'DISCONNECTED') {
              _isConnected = false;
              _isConnecting = false;
              _timer?.cancel();

              // Failover / Auto-reconnect handling
              if (!_userIntentionallyDisconnected && _autoReconnectEnabled) {
                _handleAutoReconnect();
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

  void _handleAutoReconnect() async {
    if (_reconnectAttempts < 2) {
      _reconnectAttempts++;
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('قطع ناگهانی ارتباط! تلاش مجدد ($_reconnectAttempts از ۲)...'),
            backgroundColor: const Color(0xFFF59E0B),
            duration: const Duration(seconds: 2),
          ),
        );
      }
      await Future.delayed(const Duration(seconds: 2));
      if (!_isConnected && mounted) {
        _startTunnel(isReconnect: true);
      }
    } else if (_servers.length > 1) {
      // Failover to next server
      _reconnectAttempts = 0;
      final currentIndex = _servers.indexWhere((s) => s.id == _selectedServer?.id);
      final nextIndex = (currentIndex + 1) % _servers.length;
      final nextServer = _servers[nextIndex];

      if (mounted) {
        setState(() {
          _selectedServer = nextServer;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('تغییر خودکار به سرور پایدارتر (${nextServer.name})...'),
            backgroundColor: const Color(0xFF6366F1),
            duration: const Duration(seconds: 3),
          ),
        );
      }
      await Future.delayed(const Duration(seconds: 1));
      if (!_isConnected && mounted) {
        _startTunnel(isReconnect: true);
      }
    }
  }

  void _refreshProfile() async {
    final updated = await ApiService.getProfile();
    if (updated != null && mounted) {
      setState(() {
        _client = updated;
      });
    }
  }

  void _loadServers() async {
    final list = await ApiService.getServers();
    if (mounted) {
      setState(() {
        _servers = list;
        if (_servers.isNotEmpty) {
          if (_selectedServer == null || !_servers.any((s) => s.id == _selectedServer!.id)) {
            _selectedServer = _servers.first;
          }
        }
      });
      _measureSelectedServerPing();
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
      setState(() {
        _isRefreshing = false;
        if (updatedProfile != null) {
          _client = updatedProfile;
        }
        if (updatedServers.isNotEmpty) {
          _servers = updatedServers;
          if (_selectedServer == null || !_servers.any((s) => s.id == _selectedServer!.id)) {
            _selectedServer = _servers.first;
          }
        }
      });
      _measureSelectedServerPing();

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            updatedServers.isNotEmpty
                ? 'اطلاعات حساب و ${updatedServers.length} کانکشن سرور با موفقیت بروزرسانی شد.'
                : 'اطلاعات حساب با موفقیت بروزرسانی شد.',
          ),
          backgroundColor: const Color(0xFF10B981),
          duration: const Duration(seconds: 2),
        ),
      );
    }
  }

  void _measureSelectedServerPing() async {
    if (_selectedServer == null || _selectedServer!.configUri.isEmpty) return;
    try {
      final parser = FlutterV2ray.parseFromURL(_selectedServer!.configUri);
      final delay = await _flutterV2ray.getServerDelay(config: parser.getFullConfiguration());
      if (mounted && delay > 0) {
        setState(() {
          _currentServerPing = delay;
        });
      }
    } catch (_) {}
  }

  // 1-Tap Smart Connect to Best Ping Server
  void _smartConnect() async {
    if (_isSmartConnecting || _isConnecting) return;
    if (_servers.isEmpty) {
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

    ServerModel bestServer = _servers.first;
    int lowestPing = 99999;

    for (int i = 0; i < _servers.length && i < 6; i++) {
      final s = _servers[i];
      if (s.configUri.isEmpty) continue;
      try {
        final parser = FlutterV2ray.parseFromURL(s.configUri);
        final delay = await _flutterV2ray.getServerDelay(config: parser.getFullConfiguration());
        if (delay > 0 && delay < lowestPing) {
          lowestPing = delay;
          bestServer = s;
        }
      } catch (_) {}
    }

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

    if (updateData != null && updateData['has_update'] == true && isNewerVersion((updateData['latest_version'] ?? '').toString(), currentAppVersion)) {
      final latestVer = updateData['latest_version'] ?? 'جدید';
      final changelog = updateData['changelog'] ?? '• بهینه‌سازی هسته اتصال و پایداری شبکه\n• امکان دانلود و نصب خودکار درون‌برنامه‌ای';
      final downloadUrl = updateData['download_url'] ?? '';

      showDialog(
        context: context,
        builder: (ctx) => AlertDialog(
          backgroundColor: const Color(0xFF0F172A),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(24),
            side: const BorderSide(color: Color(0xFF312E81)),
          ),
          title: Row(
            children: [
              const Icon(Icons.system_update_alt, color: Color(0xFF818CF8), size: 24),
              const SizedBox(width: 8),
              Text(
                'نسخه جدید Connectix ($latestVer)',
                style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.bold),
              ),
            ],
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'تغییرات نگارش جدید:',
                style: TextStyle(color: Color(0xFFA5B4FC), fontSize: 12, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 8),
              Text(
                changelog,
                style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 12, height: 1.6),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('بعداً', style: TextStyle(color: Color(0xFF64748B))),
            ),
            ElevatedButton.icon(
              onPressed: () {
                Navigator.pop(ctx);
                _startInAppDownloadAndInstall(downloadUrl, latestVer);
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF10B981),
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
              icon: const Icon(Icons.install_mobile_rounded, size: 16),
              label: const Text('نصب خودکار درون‌برنامه‌ای'),
            ),
          ],
        ),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('شما از آخرین نسخه رسمی نرم‌افزار ($currentAppVersion) استفاده می‌فرمایید.'),
          backgroundColor: const Color(0xFF1E293B),
        ),
      );
    }
  }

  // Real-time In-App Downloader Modal Sheet
  void _startInAppDownloadAndInstall(String downloadUrl, String version) {
    if (downloadUrl.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('لینک مستقیم بسته یافت نشد.')),
      );
      return;
    }

    double downloadProgress = 0.0;
    String statusText = 'در حال برقراری ارتباط...';
    String transferredText = '0 MB / ...';
    bool isFailed = false;

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
            // Kick off download once modal mounts
            if (downloadProgress == 0.0 && !isFailed && statusText == 'در حال برقراری ارتباط...') {
              statusText = 'در حال دریافت بسته نگارش $version...';
              ApiService.downloadAndInstallApk(
                downloadUrl: downloadUrl,
                onProgress: (progress, received, total) {
                  setModalState(() {
                    downloadProgress = progress;
                    final recMb = (received / (1024 * 1024)).toStringAsFixed(1);
                    final totMb = total > 0 ? (total / (1024 * 1024)).toStringAsFixed(1) : '...';
                    transferredText = '$recMb MB / $totMb MB (${(progress * 100).toInt()}%)';
                    statusText = 'در حال دریافت مستقیم...';
                  });
                },
                onError: (error) {
                  setModalState(() {
                    isFailed = true;
                    statusText = error;
                  });
                },
                onSuccess: () {
                  setModalState(() {
                    statusText = 'دانلود کامل شد. در حال باز کردن پنجره نصب...';
                    downloadProgress = 1.0;
                  });
                  Future.delayed(const Duration(milliseconds: 900), () {
                    if (Navigator.canPop(bottomSheetContext)) {
                      Navigator.pop(bottomSheetContext);
                    }
                  });
                },
              );
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
                          child: ElevatedButton.icon(
                            onPressed: () async {
                              Navigator.pop(bottomSheetContext);
                              final uri = Uri.parse(downloadUrl);
                              if (await canLaunchUrl(uri)) {
                                await launchUrl(uri, mode: LaunchMode.externalApplication);
                              }
                            },
                            style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF6366F1)),
                            icon: const Icon(Icons.open_in_browser, size: 16),
                            label: const Text('دانلود با مرورگر'),
                          ),
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
    if (_selectedServer == null || _selectedServer!.configUri.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('لطفاً یک سرور دارای کانکشن معتبر انتخاب فرمایید.')),
      );
      return;
    }

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

      final configUri = _selectedServer!.configUri;
      final parser = FlutterV2ray.parseFromURL(configUri);

      // Pass Split Tunneling blocked apps to exclude domestic/banking apps natively
      await _flutterV2ray.startV2Ray(
        remark: _selectedServer!.name,
        config: parser.getFullConfiguration(),
        blockedApps: _splitTunnelingEnabled ? defaultDomesticBypassApps : null,
        proxyOnly: false, // Full device-wide VPN tunnel
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
      _startTunnel();
    } else {
      // Intentional user disconnect
      _userIntentionallyDisconnected = true;
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
      builder: (ctx) => FractionallySizedBox(
        heightFactor: 0.65,
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
                  const Icon(Icons.mark_email_unread_rounded, color: Color(0xFF38BDF8), size: 24),
                  const SizedBox(width: 10),
                  const Text(
                    'صندوق پیام‌ها و اطلاعیه‌ها',
                    style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              if (_announcements.isEmpty)
                const Expanded(
                  child: Center(
                    child: Text(
                      'هیچ اطلاعیه جدیدی وجود ندارد.',
                      style: TextStyle(color: Color(0xFF64748B), fontSize: 13),
                    ),
                  ),
                )
              else
                Expanded(
                  child: ListView.separated(
                    itemCount: _announcements.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 12),
                    itemBuilder: (context, idx) {
                      final item = _announcements[idx];
                      return Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: const Color(0xFF1E293B),
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: const Color(0xFF334155)),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              item['title'] ?? 'اطلاعیه شبکه',
                              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              item['message'] ?? '',
                              style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 12, height: 1.6),
                            ),
                            if (item['created_at'] != null) ...[
                              const SizedBox(height: 8),
                              Text(
                                item['created_at'],
                                style: const TextStyle(color: Color(0xFF64748B), fontSize: 10),
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
        ),
      ),
    );
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
                  final handle = widget.branding.telegramSupport.replaceAll('@', '');
                  launchUrl(Uri.parse('https://t.me/$handle'), mode: LaunchMode.externalApplication);
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
            if (widget.branding.renewalLink.isNotEmpty)
              _SupportActionTile(
                icon: Icons.autorenew_rounded,
                iconColor: const Color(0xFFF59E0B),
                title: 'پورتال تمدید اشتراک',
                subtitle: 'شارژ فوری ترافیک و دوره زمانی',
                onTap: () {
                  launchUrl(Uri.parse(widget.branding.renewalLink), mode: LaunchMode.externalApplication);
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

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final statusColor = _isConnected
        ? const Color(0xFF10B981)
        : (_isConnecting ? const Color(0xFFF59E0B) : const Color(0xFF9333EA));

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
            Text(
              widget.branding.appName,
              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
            ),
          ],
        ),
        actions: [
          // Announcements Inbox with Badge
          Stack(
            alignment: Alignment.center,
            children: [
              IconButton(
                icon: const Icon(Icons.notifications_none_rounded, color: Color(0xFF94A3B8)),
                onPressed: _openAnnouncementsInbox,
                tooltip: 'اطلاعیه‌ها',
              ),
              if (_announcements.isNotEmpty)
                Positioned(
                  top: 10,
                  right: 10,
                  child: Container(
                    width: 8,
                    height: 8,
                    decoration: const BoxDecoration(
                      color: Color(0xFFEF4444),
                      shape: BoxShape.circle,
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
                        onPressed: _checkAppUpdate,
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

              const SizedBox(height: 22),

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

              const SizedBox(height: 22),

              // Server Selector Card
              InkWell(
                onTap: () async {
                  if (_servers.isEmpty && !_isRefreshing) {
                    _loadServers();
                  }
                  final selected = await showModalBottomSheet<ServerModel>(
                    context: context,
                    backgroundColor: Colors.transparent,
                    isScrollControlled: true,
                    builder: (_) => FractionallySizedBox(
                      heightFactor: 0.70,
                      child: ServerListModal(
                        servers: _servers,
                        selectedServer: _selectedServer,
                        onRefresh: _manualRefresh,
                      ),
                    ),
                  );
                  if (selected != null && mounted) {
                    setState(() {
                      _selectedServer = selected;
                      _currentServerPing = null;
                    });
                    _measureSelectedServerPing();
                    if (_isConnected) {
                      _toggleConnection(); // reconnect with new server
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

              const SizedBox(height: 16),

              // Pro Settings Switch (Split Tunneling & Auto-Reconnect)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                decoration: BoxDecoration(
                  color: const Color(0xFF0F172A),
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: const Color(0xFF1E293B)),
                ),
                child: Column(
                  children: [
                    // Split Tunneling Switch
                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      title: const Text(
                        'دور زدن برنامه‌های بانکی و ایرانی',
                        style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold),
                      ),
                      subtitle: const Text(
                        'اسنپ، دیوار، روبیکا و همراه بانک‌ها بدون فیلترشکن باز می‌شوند',
                        style: TextStyle(color: Color(0xFF64748B), fontSize: 10),
                      ),
                      value: _splitTunnelingEnabled,
                      activeColor: const Color(0xFF10B981),
                      onChanged: (val) async {
                        final prefs = await SharedPreferences.getInstance();
                        await prefs.setBool('split_tunneling_enabled', val);
                        setState(() {
                          _splitTunnelingEnabled = val;
                        });
                        if (_isConnected) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(content: Text('جهت اعمال تغییرات، یک‌بار اتصال را مجدداً برقرار کنید.')),
                          );
                        }
                      },
                    ),
                    const Divider(color: Color(0xFF1E293B), height: 1),
                    // Auto Reconnect Switch
                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      title: const Text(
                        'اتصال خودکار و جایگزینی سرور (Failover)',
                        style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold),
                      ),
                      subtitle: const Text(
                        'در صورت قطع اینترنت یا کندی، سرور به طور خودکار بازتنظیم می‌گردد',
                        style: TextStyle(color: Color(0xFF64748B), fontSize: 10),
                      ),
                      value: _autoReconnectEnabled,
                      activeColor: const Color(0xFF6366F1),
                      onChanged: (val) async {
                        final prefs = await SharedPreferences.getInstance();
                        await prefs.setBool('auto_reconnect_enabled', val);
                        setState(() {
                          _autoReconnectEnabled = val;
                        });
                      },
                    ),
                  ],
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
                              'دانلود: ${status.downloadSpeed}',
                              style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 12),
                            ),
                          ],
                        ),
                        Container(width: 1, height: 20, color: const Color(0xFF1E293B)),
                        Row(
                          children: [
                            const Icon(Icons.arrow_upward_rounded, size: 16, color: Color(0xFF38BDF8)),
                            const SizedBox(width: 6),
                            Text(
                              'آپلود: ${status.uploadSpeed}',
                              style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 12),
                            ),
                          ],
                        ),
                      ],
                    ),
                  );
                },
              ),
              const SizedBox(height: 20),
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
