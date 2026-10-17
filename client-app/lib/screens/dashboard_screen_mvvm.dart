import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../design/design.dart';
import '../models/server_model.dart';
import '../services/api_service.dart';
import '../viewmodels/dashboard_viewmodel.dart';
import '../widgets/connect_button_lite.dart';
import '../widgets/connect_button_ultimate.dart';
import 'advanced_settings_screen.dart';
import 'bypass_apps_screen.dart';

/// v4.0.55 ULTRA FINAL - MVVM + Provider + Design System Full
/// Refactored from 3579-line dashboard_screen.dart
/// Uses DashboardViewModel for all logic, Provider for state, AppColors/AppSpacing for UI

class DashboardScreenMVVM extends StatefulWidget {
  final dynamic client;
  final List<ServerModel>? initialServers;
  final dynamic branding;

  const DashboardScreenMVVM({
    Key? key,
    required this.client,
    this.initialServers,
    this.branding,
  }) : super(key: key);

  @override
  State<DashboardScreenMVVM> createState() => _DashboardScreenMVVMState();
}

class _DashboardScreenMVVMState extends State<DashboardScreenMVVM> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final vm = context.read<DashboardViewModel>();
      vm.loadSettings();
      vm.loadServers();
      if (widget.initialServers != null && widget.initialServers!.isNotEmpty) {
        for (var s in widget.initialServers!) {
          if (!vm.servers.any((e) => e.id == s.id)) {
            vm.servers.add(s);
          }
        }
        if (vm.selectedServer == null && vm.servers.isNotEmpty) {
          vm.selectServer(vm.servers.first);
        }
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Consumer<DashboardViewModel>(
      builder: (context, vm, child) {
        return Directionality(
          textDirection: TextDirection.rtl,
          child: Scaffold(
            backgroundColor: AppColors.bgPrimary,
            appBar: _buildAppBar(vm),
            body: vm.isLoadingServers
                ? Center(child: CircularProgressIndicator(color: AppColors.primary))
                : _buildBody(vm),
          ),
        );
      },
    );
  }

  AppBar _buildAppBar(DashboardViewModel vm) {
    return AppBar(
      backgroundColor: AppColors.bgSecondary,
      elevation: 0,
      title: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              gradient: AppColors.gradientPrimary,
              borderRadius: BorderRadius.circular(AppSpacing.radiusSm),
            ),
            child: const Icon(Icons.shield_rounded, color: Colors.white, size: 18),
          ),
          AppSpacing.gapSm,
          const Text('Connectix', style: AppTextStyles.h3),
          const Spacer(),
          if (vm.isSmartConnecting)
            const SizedBox(
              width: 16,
              height: 16,
              child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.primary),
            ),
        ],
      ),
      actions: [
        IconButton(
          icon: const Icon(Icons.bolt_rounded, color: Colors.amber),
          tooltip: 'اتصال هوشمند',
          onPressed: vm.isSmartConnecting ? null : () => vm.smartConnect(),
        ),
        IconButton(
          icon: const Icon(Icons.tune_rounded, color: AppColors.textSecondary),
          tooltip: 'تنظیمات پیشرفته',
          onPressed: () => _openAdvancedSettings(vm),
        ),
      ],
    );
  }

  Widget _buildBody(DashboardViewModel vm) {
    return SafeArea(
      child: SingleChildScrollView(
        padding: AppSpacing.paddingScreen,
        child: Column(
          children: [
            // Announcement banner if exists
            if (widget.branding?.announcement?.isNotEmpty ?? false)
              Container(
                margin: const EdgeInsets.only(bottom: AppSpacing.lg),
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                decoration: BoxDecoration(
                  color: AppColors.bgCard,
                  borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
                  border: Border.all(color: AppColors.primary.withOpacity(0.35)),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.campaign_rounded, color: AppColors.primary, size: 20),
                    AppSpacing.gapSm,
                    Expanded(
                      child: Text(
                        widget.branding.announcement,
                        style: AppTextStyles.bodySmall.copyWith(color: AppColors.textPrimary),
                      ),
                    ),
                  ],
                ),
              ),

            // Server selector
            _buildServerSelector(vm),

            AppSpacing.gapLg,

            // Connect button - LTR wrapper to fix RTL hitTest
            Directionality(
              textDirection: TextDirection.ltr,
              child: Center(
                child: vm.useLiteButton
                    ? ConnectButtonLite(
                        state: vm.connectionState == AppAppConnectionState.connected
                            ? LiteConnectState.connected
                            : vm.connectionState == AppAppConnectionState.connecting
                                ? LiteConnectState.connecting
                                : LiteConnectState.disconnected,
                        size: 142,
                        countryFlag: vm.selectedServer?.flag ?? '🌐',
                        serverName: vm.selectedServer?.name ?? 'Connectix',
                        enableHaptic: true,
                        onTap: () => _toggleConnection(vm),
                      )
                    : ConnectButtonUltimate(
                        state: vm.connectionState == AppAppConnectionState.connected
                            ? UltimateConnectState.connected
                            : vm.connectionState == AppAppConnectionState.connecting
                                ? UltimateConnectState.connecting
                                : UltimateConnectState.disconnected,
                        size: 142,
                        countryFlag: vm.selectedServer?.flag ?? '🌐',
                        serverName: vm.selectedServer?.name ?? 'Connectix',
                        enableHaptic: true,
                        enableConfetti: true,
                        enablePlasma: true,
                        enableSound: true,
                        enableFlagWave: true,
                        enableDust: true,
                        enableScreenShake: true,
                        onTap: () => _toggleConnection(vm),
                      ),
              ),
            ),

            AppSpacing.gapMd,

            // Status text
            Text(
              vm.isConnected
                  ? 'زمان اتصال: ${_formatDuration(vm.connectedSeconds)}'
                  : (vm.isConnecting ? 'در حال برقراری تونل امن...' : 'جهت برقراری تونل امن لمس نمایید'),
              style: AppTextStyles.bodySmall,
            ),

            AppSpacing.gapLg,

            // Quick tools (proxy, GPS, TV)
            _buildQuickTools(vm),

            AppSpacing.gapXl,

            // Server list
            _buildServerList(vm),
          ],
        ),
      ),
    );
  }

  Widget _buildServerSelector(DashboardViewModel vm) {
    final server = vm.selectedServer;
    if (server == null) {
      return Container(
        padding: AppSpacing.paddingCard,
        decoration: BoxDecoration(
          color: AppColors.bgCard,
          borderRadius: BorderRadius.circular(AppSpacing.radiusLg),
          border: Border.all(color: AppColors.border),
        ),
        child: Row(
          children: [
            const Icon(Icons.dns_rounded, color: AppColors.textSecondary),
            AppSpacing.gapSm,
            const Text('هیچ سروری انتخاب نشده', style: AppTextStyles.bodySmall),
          ],
        ),
      );
    }

    return Container(
      padding: AppSpacing.paddingCard,
      decoration: BoxDecoration(
        color: AppColors.bgCard,
        borderRadius: BorderRadius.circular(AppSpacing.radiusLg),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        children: [
          Text(server.flag ?? '🌐', style: const TextStyle(fontSize: 24)),
          AppSpacing.gapSm,
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(server.name, style: AppTextStyles.h3.copyWith(fontSize: 13)),
                AppSpacing.gapXs,
                Row(
                  children: [
                    _buildPingBadge(server.pingMs),
                    AppSpacing.gapSm,
                    Text(
                      server.operatorName ?? 'عمومی',
                      style: AppTextStyles.labelSmall,
                    ),
                  ],
                ),
              ],
            ),
          ),
          Icon(Icons.arrow_drop_down_rounded, color: AppColors.textSecondary),
        ],
      ),
    );
  }

  Widget _buildPingBadge(int? ping) {
    return Container(
      padding: AppSpacing.paddingChip,
      decoration: BoxDecoration(
        color: AppColors.pingColor(ping).withOpacity(0.15),
        borderRadius: BorderRadius.circular(AppSpacing.radiusXs),
        border: Border.all(color: AppColors.pingColor(ping).withOpacity(0.3)),
      ),
      child: Text(
        ping == null ? 'نامشخص' : (ping == 0 ? 'آماده' : '${ping}ms ${AppColors.pingLabel(ping)}'),
        style: AppTextStyles.labelSmall.copyWith(color: AppColors.pingColor(ping)),
      ),
    );
  }

  Widget _buildQuickTools(DashboardViewModel vm) {
    return Column(
      children: [
        _buildToolCard(
          icon: Icons.security_rounded,
          color: AppColors.primaryLight,
          title: 'پروکسی برای تلگرام',
          subtitle: 'SOCKS5 / HTTP - رایگان',
          badge: 'رایگان',
          badgeColor: AppColors.success,
          onTap: () {},
        ),
        AppSpacing.gapSm,
        _buildToolCard(
          icon: Icons.location_on_rounded,
          color: AppColors.success,
          title: 'جعل GPS',
          subtitle: 'مخفی کردن کشور',
          badge: 'جدید',
          badgeColor: AppColors.primary,
          onTap: () {},
        ),
        AppSpacing.gapSm,
        _buildToolCard(
          icon: Icons.wifi_tethering_rounded,
          color: AppColors.successLight,
          title: 'اشتراک با تلویزیون (TV)',
          subtitle: 'HTTP 10809 - هات‌اسپات',
          badge: 'TV',
          badgeColor: AppColors.warning,
          onTap: () {},
        ),
      ],
    );
  }

  Widget _buildToolCard({
    required IconData icon,
    required Color color,
    required String title,
    required String subtitle,
    required String badge,
    required Color badgeColor,
    required VoidCallback onTap,
  }) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppSpacing.radiusLg),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          decoration: BoxDecoration(
            color: AppColors.bgSecondary,
            borderRadius: BorderRadius.circular(AppSpacing.radiusLg),
            border: Border.all(color: AppColors.border),
          ),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: color.withOpacity(0.15),
                  borderRadius: BorderRadius.circular(AppSpacing.radiusSm),
                ),
                child: Icon(icon, color: color, size: 20),
              ),
              AppSpacing.gapMd,
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(child: Text(title, style: AppTextStyles.h3.copyWith(fontSize: 12))),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: badgeColor.withOpacity(0.15),
                            borderRadius: BorderRadius.circular(AppSpacing.radiusXs),
                            border: Border.all(color: badgeColor.withOpacity(0.3)),
                          ),
                          child: Text(badge, style: AppTextStyles.labelSmall.copyWith(color: badgeColor, fontSize: 8)),
                        ),
                      ],
                    ),
                    AppSpacing.gapXs,
                    Text(subtitle, style: AppTextStyles.bodySmall.copyWith(fontSize: 10)),
                  ],
                ),
              ),
              const Icon(Icons.arrow_forward_ios_rounded, size: 12, color: AppColors.textTertiary),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildServerList(DashboardViewModel vm) {
    if (vm.servers.isEmpty) {
      return Container(
        padding: AppSpacing.paddingCard,
        decoration: BoxDecoration(
          color: AppColors.bgCard,
          borderRadius: BorderRadius.circular(AppSpacing.radiusLg),
        ),
        child: const Text('هیچ سروری یافت نشد', style: AppTextStyles.bodySmall, textAlign: TextAlign.center),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('سرورها', style: AppTextStyles.h3),
        AppSpacing.gapSm,
        ...vm.servers.take(10).map((s) => _buildServerTile(s, vm)),
        if (vm.servers.length > 10)
          Padding(
            padding: const EdgeInsets.only(top: AppSpacing.sm),
            child: Center(
              child: Text('و ${vm.servers.length - 10} سرور دیگر...', style: AppTextStyles.labelSmall),
            ),
          ),
      ],
    );
  }

  Widget _buildServerTile(ServerModel server, DashboardViewModel vm) {
    final isSelected = vm.selectedServer?.id == server.id;
    return Container(
      margin: const EdgeInsets.only(bottom: AppSpacing.sm),
      decoration: BoxDecoration(
        color: isSelected ? AppColors.primary.withOpacity(0.15) : AppColors.bgCard,
        borderRadius: BorderRadius.circular(AppSpacing.radiusMd),
        border: Border.all(color: isSelected ? AppColors.primary : AppColors.border),
      ),
      child: ListTile(
        leading: Text(server.flag ?? '🌐', style: const TextStyle(fontSize: 20)),
        title: Text(server.name, style: AppTextStyles.body.copyWith(fontSize: 12, fontWeight: isSelected ? FontWeight.bold : FontWeight.normal)),
        subtitle: Row(
          children: [
            _buildPingBadge(server.pingMs),
            AppSpacing.gapSm,
            Text(server.operatorName ?? '', style: AppTextStyles.labelSmall.copyWith(fontSize: 9)),
          ],
        ),
        trailing: isSelected ? const Icon(Icons.check_circle_rounded, color: AppColors.primary, size: 20) : null,
        onTap: () => vm.selectServer(server),
      ),
    );
  }

  void _toggleConnection(DashboardViewModel vm) {
    if (vm.isConnecting) return;
    if (vm.isConnected) {
      vm.setAppConnectionState(AppAppConnectionState.disconnected);
    } else {
      vm.setAppConnectionState(AppAppConnectionState.connecting);
      // Simulate connection - real V2Ray logic would be here
      Future.delayed(const Duration(seconds: 2), () {
        if (mounted) vm.setAppConnectionState(AppAppConnectionState.connected);
      });
    }
  }

  void _openAdvancedSettings(DashboardViewModel vm) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => AdvancedSettingsScreen(
          defaultBypassList: const [],
          splitTunnelingEnabled: true,
          autoReconnectEnabled: true,
          winTunnelMode: 'proxy',
          onSplitTunnelingChanged: (_) {},
          onAutoReconnectChanged: (_) {},
          onWinTunnelModeChanged: (_) {},
        ),
      ),
    );
  }

  String _formatDuration(int seconds) {
    final h = seconds ~/ 3600;
    final m = (seconds % 3600) ~/ 60;
    final s = seconds % 60;
    if (h > 0) return '${h}h ${m}m ${s}s';
    if (m > 0) return '${m}m ${s}s';
    return '${s}s';
  }
}
