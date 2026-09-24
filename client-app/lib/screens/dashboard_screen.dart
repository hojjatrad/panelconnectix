import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_v2ray/flutter_v2ray.dart';
import 'package:url_launcher/url_launcher.dart';
import '../models/client_model.dart';
import '../models/server_model.dart';
import '../services/api_service.dart';
import 'login_screen.dart';
import 'server_list_modal.dart';

class DashboardScreen extends StatefulWidget {
  final ClientModel client;
  final BrandingModel branding;

  const DashboardScreen({
    Key? key,
    required this.client,
    required this.branding,
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
  int _connectedSeconds = 0;
  Timer? _timer;

  List<Map<String, dynamic>> _announcements = [];
  bool _hasAppUpdate = false;
  Map<String, dynamic>? _updateInfo;

  @override
  void initState() {
    super.initState();
    _client = widget.client;
    _initV2Ray();
    _loadServers();
    _refreshProfile();
    _loadAnnouncements();
    _autoCheckUpdateInBackground();
  }

  static const String currentAppVersion = '3.0.0';

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

        // Only show if backend flags an active update AND version is strictly newer AND user hasn't dismissed it
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
            } else if (status.state == 'DISCONNECTED') {
              _isConnected = false;
              _isConnecting = false;
              _timer?.cancel();
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
      final changelog = updateData['changelog'] ?? '• بهینه‌سازی هسته اتصال و پایداری شبکه';
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
              onPressed: () async {
                Navigator.pop(ctx);
                if (downloadUrl.isNotEmpty) {
                  final uri = Uri.parse(downloadUrl);
                  if (await canLaunchUrl(uri)) {
                    await launchUrl(uri, mode: LaunchMode.externalApplication);
                  }
                }
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF6366F1),
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
              icon: const Icon(Icons.download, size: 16),
              label: const Text('دانلود مستقیم APK'),
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

  void _toggleConnection() async {
    if (_isConnecting) return;

    if (!_isConnected) {
      if (_selectedServer == null || _selectedServer!.configUri.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('لطفاً یک سرور دارای کانکشن معتبر انتخاب فرمایید.')),
        );
        return;
      }

      setState(() {
        _isConnecting = true;
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

        await _flutterV2ray.startV2Ray(
          remark: _selectedServer!.name,
          config: parser.getFullConfiguration(),
          proxyOnly: false, // True device-wide VPN tunnel
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
    } else {
      // Disconnect
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

    return Scaffold(
      backgroundColor: const Color(0xFF090D16),
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        title: Text(
          widget.branding.appName,
          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
        ),
        actions: [
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
          IconButton(
            icon: const Icon(Icons.system_update_alt, color: Color(0xFF818CF8)),
            onPressed: _checkAppUpdate,
            tooltip: 'بررسی نسخه جدید نرم‌افزار',
          ),
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
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
          child: Column(
            children: [
              // New App Update Alert Banner
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
                              'جهت بروزرسانی و نصب مستقیم کلیک کنید',
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
                        child: const Text('آپدیت', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 11)),
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

              // Announcements Banner
              if (_announcements.isNotEmpty)
                Container(
                  margin: const EdgeInsets.only(bottom: 14),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: const Color(0xFF1E293B),
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFF38BDF8).withOpacity(0.3)),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.campaign_rounded, color: Color(0xFF38BDF8), size: 20),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          _announcements.first['message'] ?? _announcements.first['title'] ?? '',
                          style: const TextStyle(color: Color(0xFFE2E8F0), fontSize: 11),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                ),

              // User Quota & Live Stats Card
              Container(
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [Color(0xFF1E1B4B), Color(0xFF0F172A)],
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
                    ClipRRect(
                      borderRadius: BorderRadius.circular(6),
                      child: LinearProgressIndicator(
                        value: (_client.usagePercent / 100).clamp(0.0, 1.0),
                        backgroundColor: const Color(0xFF1E293B),
                        valueColor: AlwaysStoppedAnimation<Color>(
                          _client.usagePercent > 85 ? const Color(0xFFF43F5E) : const Color(0xFF6366F1),
                        ),
                        minHeight: 8,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          'مصرف: ${_client.trafficUsedGb} GB',
                          style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 11),
                        ),
                        Text(
                          'باقیمانده: ${_client.trafficRemainingGb} GB',
                          style: const TextStyle(color: Color(0xFF38BDF8), fontSize: 11, fontWeight: FontWeight.bold),
                        ),
                        Text(
                          'کل: ${_client.trafficTotalGb} GB',
                          style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 11),
                        ),
                      ],
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 28),

              // Connect Button with Glowing Rings
              GestureDetector(
                onTap: _toggleConnection,
                child: Container(
                  width: 170,
                  height: 170,
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
                      width: 130,
                      height: 130,
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
                                    size: 46,
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

              const SizedBox(height: 16),

              // Timer & Status Tag
              Text(
                _isConnected
                    ? 'زمان اتصال: ${_formatDuration(_connectedSeconds)}'
                    : (_isConnecting ? 'در حال برقراری تونل امن...' : 'جهت اتصال لمس نمایید'),
                style: const TextStyle(color: Color(0xFF94A3B8), fontSize: 12),
              ),

              const SizedBox(height: 28),

              // Server Selector Card
              InkWell(
                onTap: () async {
                  if (_servers.isEmpty) {
                    _manualRefresh();
                    return;
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
                    });
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
                      const Icon(Icons.arrow_forward_ios_rounded, size: 14, color: Color(0xFF64748B)),
                    ],
                  ),
                ),
              ),

              const SizedBox(height: 20),

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
            ],
          ),
        ),
      ),
    );
  }
}
