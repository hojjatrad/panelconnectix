import 'dart:async';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/server_model.dart';
import '../services/api_service.dart';

/// v4.0.53 MVVM - Dashboard ViewModel extracted from 3652-line dashboard_screen.dart
/// Handles server list, selected server, ping, connection state, settings

enum ConnectionState { disconnected, connecting, connected }

class DashboardViewModel extends ChangeNotifier {
  // Servers
  List<ServerModel> _servers = [];
  List<ServerModel> get servers => _servers;
  ServerModel? _selectedServer;
  ServerModel? get selectedServer => _selectedServer;

  // Connection
  ConnectionState _connectionState = ConnectionState.disconnected;
  ConnectionState get connectionState => _connectionState;
  bool get isConnected => _connectionState == ConnectionState.connected;
  bool get isConnecting => _connectionState == ConnectionState.connecting;

  int _connectedSeconds = 0;
  int get connectedSeconds => _connectedSeconds;
  Timer? _timer;

  // Ping
  int? _currentPing;
  int? get currentPing => _currentPing;
  bool _isSmartConnecting = false;
  bool get isSmartConnecting => _isSmartConnecting;

  // Settings
  bool _splitTunnelingEnabled = true;
  bool get splitTunnelingEnabled => _splitTunnelingEnabled;
  bool _autoReconnectEnabled = true;
  bool get autoReconnectEnabled => _autoReconnectEnabled;
  bool _useLiteButton = true;
  bool get useLiteButton => _useLiteButton;

  // Loading
  bool _isLoadingServers = false;
  bool get isLoadingServers => _isLoadingServers;

  // Accounts (multi-account)
  List<Map<String, dynamic>> _accounts = [];
  List<Map<String, dynamic>> get accounts => _accounts;

  Future<void> loadSettings() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      _splitTunnelingEnabled = prefs.getBool('split_tunneling_enabled') ?? true;
      _autoReconnectEnabled = prefs.getBool('auto_reconnect_enabled') ?? true;
      _useLiteButton = prefs.getBool('use_lite_button') ?? true;
      notifyListeners();
    } catch (_) {}
  }

  Future<void> loadServers() async {
    _isLoadingServers = true;
    notifyListeners();
    try {
      final servers = await ApiService.fetchServers();
      _servers = servers.where((s) => !s.isInfoBanner && s.configUri.isNotEmpty).toList();
      if (_servers.isNotEmpty && _selectedServer == null) {
        // Try last working
        final prefs = await SharedPreferences.getInstance();
        final lastId = prefs.getString('last_working_server_id');
        if (lastId != null) {
          final found = _servers.where((s) => s.id == lastId).toList();
          if (found.isNotEmpty) _selectedServer = found.first;
        }
        _selectedServer ??= _servers.first;
      }
    } catch (e) {
      debugPrint('VM loadServers error: $e');
    } finally {
      _isLoadingServers = false;
      notifyListeners();
    }
  }

  void selectServer(ServerModel server) {
    _selectedServer = server;
    _currentPing = server.pingMs;
    notifyListeners();
    // Save last working
    SharedPreferences.getInstance().then((prefs) {
      prefs.setString('last_working_server_id', server.id);
    });
  }

  void setConnectionState(ConnectionState state) {
    _connectionState = state;
    if (state == ConnectionState.connected) {
      _connectedSeconds = 0;
      _timer?.cancel();
      _timer = Timer.periodic(const Duration(seconds: 1), (_) {
        _connectedSeconds++;
        notifyListeners();
      });
    } else {
      _timer?.cancel();
      if (state == ConnectionState.disconnected) {
        _connectedSeconds = 0;
      }
    }
    notifyListeners();
  }

  void setPing(int? ping) {
    _currentPing = ping;
    if (_selectedServer != null) _selectedServer!.pingMs = ping;
    notifyListeners();
  }

  Future<void> smartConnect() async {
    if (_isSmartConnecting || _servers.isEmpty) return;
    _isSmartConnecting = true;
    notifyListeners();

    try {
      ServerModel best = _servers.first;
      int lowest = 99999;

      for (int i = 0; i < _servers.length && i < 6; i++) {
        final s = _servers[i];
        if (s.configUri.isEmpty) continue;
        try {
          // TCP ping fallback (V2Ray ping handled in UI layer with timeout)
          final delay = await ApiService.pingServerUri(s.configUri);
          if (delay != null && delay > 0 && delay < lowest) {
            lowest = delay;
            best = s;
          }
        } catch (_) {}
      }

      _selectedServer = best;
      _currentPing = lowest < 99999 ? lowest : null;
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('last_working_server_id', best.id);
    } catch (e) {
      debugPrint('VM smartConnect error: $e');
    } finally {
      _isSmartConnecting = false;
      notifyListeners();
    }
  }

  void updateLiteButtonPreference(bool useLite) {
    _useLiteButton = useLite;
    SharedPreferences.getInstance().then((p) => p.setBool('use_lite_button', useLite));
    notifyListeners();
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }
}
