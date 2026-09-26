import 'dart:io';
import 'package:flutter_v2ray/flutter_v2ray.dart';
import 'xray_service.dart';

/// Cross-platform tunnel facade with the SAME public API as FlutterV2ray
/// (the subset the dashboard uses):
///
///   - Android: delegates to the flutter_v2ray native plugin (unchanged)
///   - Windows: runs the bundled open-source Xray core (XrayService) with
///     system-proxy routing — see xray_service.dart
///
/// The dashboard only swaps `FlutterV2ray` -> `V2RayCompat`; everything
/// else (status model, parseFromURL, config JSON) stays identical.
class V2RayCompat {
  V2RayCompat({void Function(V2RayStatus)? onStatusChanged}) {
    _onStatusChanged = onStatusChanged ?? _noop;
    _xray.onStatusChanged = (s) => _onStatusChanged(s);
  }

  late final void Function(V2RayStatus) _onStatusChanged;

  static void _noop(V2RayStatus status) {}
  final XrayService _xray = XrayService.instance;
  FlutterV2ray? _v2ray;

  bool get isWindows => Platform.isWindows;

  /// Windows: is the app running elevated (admin)?
  Future<bool> isElevated() async => _xray.isElevated();

  Future<void> initializeV2Ray() async {
    if (isWindows) {
      await _xray.initialize();
      return;
    }
    if (_v2ray == null) {
      _v2ray = FlutterV2ray(onStatusChanged: (s) => _onStatusChanged(s));
    }
    await _v2ray!.initializeV2Ray();
  }

  Future<int?> getServerDelay({required String config}) async {
    if (isWindows) {
      return XrayService.measureTcpDelay(config);
    }
    return _v2ray!.getServerDelay(config: config);
  }

  Future<bool> requestPermission() async {
    if (isWindows) return true; // system proxy needs no user permission
    return _v2ray!.requestPermission();
  }

  /// Windows-only tunnel flavor: false = system proxy (Phase 1),
  /// true = full TUN VPN via Wintun (Phase 2). Ignored on Android.
  Future<void> startV2Ray({
    required String remark,
    required String config,
    List<String>? blockedApps,
    List<String>? bypassSubnets,
    bool proxyOnly = false,
    bool tunMode = false,
  }) async {
    if (isWindows) {
      await _xray.start(config, tunMode: tunMode);
      return;
    }
    await _v2ray!.startV2Ray(
      remark: remark,
      config: config,
      blockedApps: blockedApps,
      bypassSubnets: bypassSubnets,
      proxyOnly: proxyOnly,
    );
  }

  Future<void> stopV2Ray() async {
    if (isWindows) {
      await _xray.stop();
      _onStatusChanged(V2RayStatus(state: 'DISCONNECTED'));
      return;
    }
    await _v2ray?.stopV2Ray();
  }

  /// App-exit cleanup (restore system proxy, kill the core).
  Future<void> shutdown() async {
    if (isWindows) {
      try {
        await _xray.disposeOnExit();
      } catch (_) {}
    }
  }
}
