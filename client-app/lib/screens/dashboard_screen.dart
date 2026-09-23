import 'dart:async';
import 'package:flutter/material.dart';
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

  bool _isConnected = false;
  bool _isConnecting = false;
  int _connectedSeconds = 0;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _client = widget.client;
    _loadServers();
  }

  void _loadServers() async {
    final list = await ApiService.getServers();
    if (mounted) {
      setState(() {
        _servers = list;
        if (_servers.isNotEmpty) {
          _selectedServer = _servers.first;
        }
      });
    }
  }

  void _toggleConnection() {
    if (_isConnecting) return;

    if (!_isConnected) {
      // Connect
      setState(() {
        _isConnecting = true;
      });

      Future.delayed(const Duration(milliseconds: 1200), () {
        if (!mounted) return;
        setState(() {
          _isConnecting = false;
          _isConnected = true;
          _connectedSeconds = 0;
        });

        _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
          if (mounted) {
            setState(() {
              _connectedSeconds++;
            });
          }
        });
      });
    } else {
      // Disconnect
      _timer?.cancel();
      setState(() {
        _isConnected = false;
        _isConnecting = false;
        _connectedSeconds = 0;
      });
    }
  }

  String _formatTimer(int seconds) {
    final h = (seconds ~/ 3600).toString().padLeft(2, '0');
    final m = ((seconds % 3600) ~/ 60).toString().padLeft(2, '0');
    final s = (seconds % 60).toString().padLeft(2, '0');
    return "$h:$m:$s";
  }

  void _openServerModal() async {
    final chosen = await showModalBottomSheet<ServerModel>(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (_) => ServerListModal(
        servers: _servers,
        selectedServer: _selectedServer,
      ),
    );

    if (chosen != null && mounted) {
      setState(() {
        _selectedServer = chosen;
      });
    }
  }

  void _logout() async {
    if (_isConnected) _toggleConnection();
    await ApiService.logout();
    if (!mounted) return;
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(builder: (_) => const LoginScreen()),
    );
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF090D16),
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        title: Row(
          children: [
            Container(
              width: 32,
              height: 32,
              decoration: BoxDecoration(
                color: const Color(0xFF9333EA).withOpacity(0.2),
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Icon(Icons.bolt, color: Color(0xFFA855F7), size: 20),
            ),
            const SizedBox(width: 10),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _client.username,
                  style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Colors.white),
                ),
                Text(
                  _client.planTitle,
                  style: const TextStyle(fontSize: 10, color: Color(0xFF94A3B8)),
                ),
              ],
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.power_settings_new, color: Color(0xFF94A3B8)),
            onPressed: _logout,
          ),
        ],
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              // Traffic Quota & Expiry Card
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: const Color(0xFF0F172A),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.pad(BorderSide(color: const Color(0xFF1E293B))),
                ),
                child: Column(
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('حجم باقیمانده:', style: TextStyle(color: Color(0xFF94A3B8), fontSize: 12)),
                        Text(
                          '${_client.trafficRemainingGb} GB از ${_client.trafficTotalGb} GB',
                          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(8),
                      child: LinearProgressIndicator(
                        value: _client.usagePercent / 100,
                        backgroundColor: const Color(0xFF1E293B),
                        valueColor: const AlwaysStoppedAnimation<Color>(Color(0xFF9333EA)),
                        minHeight: 6,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('اعتبار اشتراک:', style: TextStyle(color: Color(0xFF94A3B8), fontSize: 11)),
                        Text(
                          _client.daysRemaining,
                          style: const TextStyle(color: Color(0xFFFBBF24), fontWeight: FontWeight.bold, fontSize: 12),
                        ),
                      ],
                    ),
                  ],
                ),
              ),

              // Central Connection Button
              Column(
                children: [
                  GestureDetector(
                    onTap: _toggleConnection,
                    child: Container(
                      width: 170,
                      height: 170,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        gradient: LinearGradient(
                          colors: _isConnected
                              ? [const Color(0xFF10B981), const Color(0xFF059669)]
                              : [const Color(0xFF1E293B), const Color(0xFF0F172A)],
                        ),
                        border: Border.all(
                          color: _isConnected ? const Color(0xFF34D399) : const Color(0xFF334155),
                          width: 4,
                        ),
                        boxShadow: [
                          if (_isConnected)
                            BoxShadow(
                              color: const Color(0xFF10B981).withOpacity(0.4),
                              blurRadius: 35,
                              spreadRadius: 6,
                            ),
                        ],
                      ),
                      child: Center(
                        child: _isConnecting
                            ? const SizedBox(
                                width: 36,
                                height: 36,
                                child: CircularProgressIndicator(color: Color(0xFFA855F7), strokeWidth: 3),
                              )
                            : Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(
                                    _isConnected ? Icons.shield_rounded : Icons.power_settings_new,
                                    color: _isConnected ? Colors.white : const Color(0xFF94A3B8),
                                    size: 46,
                                  ),
                                  const SizedBox(height: 8),
                                  Text(
                                    _isConnected ? 'متصل شد' : 'لمس برای اتصال',
                                    style: TextStyle(
                                      color: _isConnected ? Colors.white : const Color(0xFFCBD5E1),
                                      fontSize: 12,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                ],
                              ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  if (_isConnected)
                    Text(
                      _formatTimer(_connectedSeconds),
                      style: const TextStyle(
                        fontFamily: 'monospace',
                        color: Color(0xFF34D399),
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                ],
              ),

              // Server Selection Pill
              GestureDetector(
                onTap: _openServerModal,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                  decoration: BoxDecoration(
                    color: const Color(0xFF0F172A),
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(color: const Color(0xFF1E293B)),
                  ),
                  child: Row(
                    children: [
                      Text(
                        _selectedServer?.flag ?? '🌐',
                        style: const TextStyle(fontSize: 22),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _selectedServer?.name ?? 'در حال دریافت سرورها...',
                              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13),
                              overflow: TextOverflow.ellipsis,
                            ),
                            Text(
                              '${_selectedServer?.protocol.toUpperCase() ?? "VLESS"} • ${_selectedServer?.operatorName ?? "بهترین سرور"}',
                              style: const TextStyle(color: Color(0xFF64748B), fontSize: 11),
                            ),
                          ],
                        ),
                      ),
                      const Icon(Icons.arrow_forward_ios, color: Color(0xFF64748B), size: 14),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
