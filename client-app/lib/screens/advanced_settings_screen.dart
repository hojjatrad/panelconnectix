import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'bypass_apps_screen.dart';

class AdvancedSettingsScreen extends StatefulWidget {
  final List<String> defaultBypassList;
  final bool splitTunnelingEnabled;
  final bool autoReconnectEnabled;
  final String winTunnelMode;
  final Function(bool) onSplitTunnelingChanged;
  final Function(bool) onAutoReconnectChanged;
  final Function(String) onWinTunnelModeChanged;

  const AdvancedSettingsScreen({
    Key? key,
    required this.defaultBypassList,
    required this.splitTunnelingEnabled,
    required this.autoReconnectEnabled,
    required this.winTunnelMode,
    required this.onSplitTunnelingChanged,
    required this.onAutoReconnectChanged,
    required this.onWinTunnelModeChanged,
  }) : super(key: key);

  @override
  State<AdvancedSettingsScreen> createState() => _AdvancedSettingsScreenState();
}

class _AdvancedSettingsScreenState extends State<AdvancedSettingsScreen> {
  late bool _splitEnabled;
  late bool _autoReconnect;
  late String _winMode;
  bool _autoPauseEnabled = false;

  static const MethodChannel _channel = MethodChannel('com.connectix.vpn/updater');

  @override
  void initState() {
    super.initState();
    _splitEnabled = widget.splitTunnelingEnabled;
    _autoReconnect = widget.autoReconnectEnabled;
    _winMode = widget.winTunnelMode;
    _loadAutoPause();
  }

  Future<void> _loadAutoPause() async {
    final prefs = await SharedPreferences.getInstance();
    if (mounted) {
      setState(() {
        _autoPauseEnabled = prefs.getBool('auto_pause_for_banking_enabled') ?? false;
      });
    }
  }

  Widget _winModeChip({required String label, required bool selected, required VoidCallback onTap}) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: const EdgeInsets.symmetric(vertical: 10),
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: selected ? const Color(0xFF9333EA) : const Color(0xFF0F172A),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: selected ? const Color(0xFF9333EA) : const Color(0xFF334155)),
        ),
        child: Text(
          label,
          style: TextStyle(
            color: selected ? Colors.white : const Color(0xFF94A3B8),
            fontSize: 12,
            fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Directionality(
      textDirection: TextDirection.rtl,
      child: Scaffold(
        backgroundColor: const Color(0xFF090D16),
        appBar: AppBar(
          backgroundColor: const Color(0xFF0F172A),
          elevation: 0,
          title: const Text('تنظیمات پیشرفته', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.white)),
        ),
        body: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            // Info banner
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: const Color(0xFF1E293B),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: const Color(0xFF334155)),
              ),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(color: const Color(0xFF6366F1).withOpacity(0.15), borderRadius: BorderRadius.circular(10)),
                    child: const Icon(Icons.settings_suggest_rounded, color: Color(0xFF818CF8), size: 20),
                  ),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Text(
                      'عبور مستقیم برنامه‌های ایرانی و بانکی را مدیریت کنید. برای بانک‌هایی که فیلترشکن را شناسایی می‌کنند، توقف خودکار را فعال کنید.',
                      style: TextStyle(color: Color(0xFFCBD5E1), fontSize: 11, height: 1.6),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Split Tunneling Section
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              decoration: BoxDecoration(
                color: const Color(0xFF0F172A),
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: const Color(0xFF1E293B)),
              ),
              child: Column(
                children: [
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('دور زدن برنامه‌های بانکی و ایرانی', style: TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold)),
                    subtitle: const Text('اسنپ، دیوار، روبیکا، ایتا و همراه بانک‌ها بدون فیلترشکن باز می‌شوند', style: TextStyle(color: Color(0xFF64748B), fontSize: 11)),
                    value: _splitEnabled,
                    activeColor: const Color(0xFF10B981),
                    onChanged: (val) async {
                      final prefs = await SharedPreferences.getInstance();
                      await prefs.setBool('split_tunneling_enabled', val);
                      setState(() => _splitEnabled = val);
                      widget.onSplitTunnelingChanged(val);
                    },
                  ),
                  if (_splitEnabled && Platform.isAndroid) ...[
                    const Divider(color: Color(0xFF1E293B), height: 1),
                    const SizedBox(height: 8),
                    InkWell(
                      onTap: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => BypassAppsScreen(defaultBypassList: widget.defaultBypassList),
                          ),
                        );
                      },
                      borderRadius: BorderRadius.circular(12),
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(colors: [Color(0xFF064E3B), Color(0xFF0F172A)], begin: Alignment.topLeft, end: Alignment.bottomRight),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: const Color(0xFF10B981).withOpacity(0.3)),
                        ),
                        child: Row(
                          children: [
                            Container(
                              padding: const EdgeInsets.all(10),
                              decoration: BoxDecoration(color: const Color(0xFF10B981).withOpacity(0.2), borderRadius: BorderRadius.circular(12)),
                              child: const Icon(Icons.apps_rounded, color: Color(0xFF10B981), size: 22),
                            ),
                            const SizedBox(width: 12),
                            const Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text('مدیریت برنامه‌های عبور مستقیم', style: TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold)),
                                  SizedBox(height: 3),
                                  Text('انتخاب دستی برنامه‌هایی که بدون فیلترشکن باز شوند (بانک‌ها، ایتا، روبیکا...)', style: TextStyle(color: Color(0xFF94A3B8), fontSize: 11, height: 1.4)),
                                ],
                              ),
                            ),
                            const Icon(Icons.arrow_forward_ios_rounded, size: 14, color: Color(0xFF10B981)),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 8),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Auto-Pause for Banking (Layer 3 - 100% guarantee to hide tun0)
            if (Platform.isAndroid)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                decoration: BoxDecoration(
                  color: const Color(0xFF0F172A),
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: _autoPauseEnabled ? const Color(0xFF10B981).withOpacity(0.4) : const Color(0xFF1E293B)),
                ),
                child: Column(
                  children: [
                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      title: const Text('توقف خودکار برای بانک‌ها و ایتا (تضمینی)', style: TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold)),
                      subtitle: const Text('هنگام ورود به برنامه بانکی یا ایتا، VPN خودکار متوقف می‌شود تا هیچ اثری از tun0 باقی نماند و بانک فیلترشکن را شناسایی نکند. هنگام خروج، خودکار وصل می‌شود.', style: TextStyle(color: Color(0xFF64748B), fontSize: 10, height: 1.5)),
                      value: _autoPauseEnabled,
                      activeColor: const Color(0xFF10B981),
                      onChanged: (val) async {
                        final prefs = await SharedPreferences.getInstance();
                        await prefs.setBool('auto_pause_for_banking_enabled', val);
                        setState(() => _autoPauseEnabled = val);
                        if (val) {
                          try {
                            final bool hasPerm = await _channel.invokeMethod<bool>('checkUsageStatsPermission') ?? false;
                            if (!hasPerm) {
                              if (context.mounted) {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  const SnackBar(content: Text('لطفا دسترسی «دسترسی به آمار استفاده» را فعال کنید تا توقف خودکار کار کند.'), duration: Duration(seconds: 5)),
                                );
                              }
                              await _channel.invokeMethod('openUsageStatsSettings');
                            }
                          } catch (_) {}
                        }
                      },
                    ),
                    if (_autoPauseEnabled) ...[
                      const Divider(color: Color(0xFF1E293B), height: 1),
                      Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(color: const Color(0xFF10B981).withOpacity(0.1), borderRadius: BorderRadius.circular(10)),
                        child: Row(
                          children: [
                            const Icon(Icons.verified_rounded, color: Color(0xFF10B981), size: 16),
                            const SizedBox(width: 8),
                            const Expanded(
                              child: Text(
                                'این روش تنها روش ۱۰۰٪ تضمینی برای دور زدن شناسایی از طریق getAllNetworks() و tun0 است. VPN هنگام استفاده از بانک کاملا خاموش می‌شود.',
                                style: TextStyle(color: Color(0xFF6EE7B7), fontSize: 10, height: 1.4),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ],
                ),
              ),

            const SizedBox(height: 16),

            // Auto Reconnect Section
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              decoration: BoxDecoration(
                color: const Color(0xFF0F172A),
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: const Color(0xFF1E293B)),
              ),
              child: SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('اتصال خودکار و جایگزینی سرور', style: TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold)),
                subtitle: const Text('در صورت قطع اینترنت یا کندی، سرور به طور خودکار بازتنظیم می‌گردد', style: TextStyle(color: Color(0xFF64748B), fontSize: 11)),
                value: _autoReconnect,
                activeColor: const Color(0xFF6366F1),
                onChanged: (val) async {
                  final prefs = await SharedPreferences.getInstance();
                  await prefs.setBool('auto_reconnect_enabled', val);
                  setState(() => _autoReconnect = val);
                  widget.onAutoReconnectChanged(val);
                },
              ),
            ),

            if (Platform.isWindows) ...[
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: const Color(0xFF0F172A),
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: const Color(0xFF1E293B)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Row(
                      children: [
                        Icon(Icons.router_rounded, size: 18, color: Color(0xFF9333EA)),
                        SizedBox(width: 8),
                        Text('حالت اتصال در ویندوز', style: TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold)),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(child: _winModeChip(label: 'پروکسی سیستمی', selected: _winMode == 'proxy', onTap: () { setState(() => _winMode = 'proxy'); widget.onWinTunnelModeChanged('proxy'); })),
                        const SizedBox(width: 10),
                        Expanded(child: _winModeChip(label: 'VPN کامل (TUN)', selected: _winMode == 'tun', onTap: () { setState(() => _winMode = 'tun'); widget.onWinTunnelModeChanged('tun'); })),
                      ],
                    ),
                    const SizedBox(height: 8),
                    const Text('حالت VPN کامل در اولین اجرا نیاز به اجرای برنامه با دسترسی ادمین دارد.', style: TextStyle(color: Color(0xFF64748B), fontSize: 10)),
                  ],
                ),
              ),
            ],

            const SizedBox(height: 24),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: const Color(0xFF1E293B).withOpacity(0.5), borderRadius: BorderRadius.circular(12)),
              child: const Text(
                'معماری ۳ لایه: ۱) معافیت سیستمی addDisallowedApplication ۲) روتینگ مستقیم geosite:ir/geoip:ir/domain:ir ۳) توقف خودکار هنگام ورود به بانک برای مخفی‌سازی کامل tun0',
                style: TextStyle(color: Color(0xFF94A3B8), fontSize: 11, height: 1.5),
                textAlign: TextAlign.center,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
